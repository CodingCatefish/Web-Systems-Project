'use strict';

if (require.main === module) {
  console.error('This project now uses the PHP backend. Start it with `php -S 127.0.0.1:3000` from the repository root.');
  process.exit(1);
}

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');
const mysql = require('mysql2/promise');
const bcrypt = require('bcryptjs');

const HOST = '127.0.0.1';
const PORT = Number(process.env.PORT) || 3000;
const ROOT_DIR = __dirname;

const DB_CONFIG = {
  host: process.env.DB_HOST || 'localhost',
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME || 'bookstore',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

const pool = mysql.createPool(DB_CONFIG);
const sessions = new Map();

const IS_PRODUCTION = process.env.NODE_ENV === 'production';
const ADMIN_EMAIL = (process.env.ADMIN_EMAIL || 'admin@pagemark.local').toLowerCase();
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || (IS_PRODUCTION ? '' : 'Admin123!');
const SESSION_MAX_AGE_SECONDS = 60 * 60 * 12;
const SESSION_MAX_AGE_MS = SESSION_MAX_AGE_SECONDS * 1000;

const MIME_TYPES = {
  '.css': 'text/css; charset=utf-8',
  '.gif': 'image/gif',
  '.html': 'text/html; charset=utf-8',
  '.ico': 'image/x-icon',
  '.jpeg': 'image/jpeg',
  '.jpg': 'image/jpeg',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.png': 'image/png',
  '.svg': 'image/svg+xml; charset=utf-8',
  '.txt': 'text/plain; charset=utf-8',
  '.webp': 'image/webp'
};

function applySecurityHeaders(response) {
  response.setHeader('Content-Security-Policy', [
    "default-src 'self'",
    "script-src 'self'",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
    "img-src 'self' data: https://picsum.photos https://fastly.picsum.photos",
    "font-src 'self' https://fonts.gstatic.com",
    "connect-src 'self'",
    "object-src 'none'",
    "base-uri 'self'",
    "form-action 'self'",
    "frame-ancestors 'none'"
  ].join('; '));
  response.setHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
  response.setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
  response.setHeader('X-Content-Type-Options', 'nosniff');
}

function sendText(response, statusCode, message, extraHeaders) {
  response.writeHead(statusCode, Object.assign({
    'Content-Type': 'text/plain; charset=utf-8'
  }, extraHeaders || {}));
  response.end(message);
}

function sendJson(response, statusCode, payload, extraHeaders) {
  response.writeHead(statusCode, Object.assign({
    'Content-Type': 'application/json; charset=utf-8'
  }, extraHeaders || {}));
  response.end(JSON.stringify(payload));
}

function sendFile(filePath, response, request, extraHeaders) {
  const extension = path.extname(filePath).toLowerCase();
  const contentType = MIME_TYPES[extension] || 'application/octet-stream';

  fs.readFile(filePath, (error, content) => {
    if (error) {
      if (error.code === 'ENOENT') {
        sendNotFound(response);
        return;
      }

      sendText(response, 500, 'Internal Server Error');
      return;
    }

    response.writeHead(200, Object.assign({
      'Content-Type': contentType
    }, extraHeaders || {}));

    if (request.method === 'HEAD') {
      response.end();
      return;
    }

    response.end(content);
  });
}

function sendNotFound(response) {
  sendText(response, 404, '404 Not Found');
}

function sendRedirect(response, location) {
  response.writeHead(302, { Location: location });
  response.end();
}

function parseCookies(request) {
  const cookieHeader = request.headers.cookie || '';

  return cookieHeader.split(';').reduce((acc, pair) => {
    const [rawKey, ...rawValue] = pair.split('=');
    const key = (rawKey || '').trim();
    if (!key) {
      return acc;
    }

    acc[key] = decodeURIComponent(rawValue.join('=').trim());
    return acc;
  }, {});
}

function createSession(response, user) {
  const sessionId = crypto.randomBytes(24).toString('hex');
  const cookieParts = [
    `session_id=${sessionId}`,
    'Path=/',
    'HttpOnly',
    'SameSite=Lax',
    `Max-Age=${SESSION_MAX_AGE_SECONDS}`
  ];

  if (IS_PRODUCTION) {
    cookieParts.push('Secure');
  }

  sessions.set(sessionId, {
    id: user.id,
    email: user.email,
    name: user.name,
    role: user.role,
    createdAt: Date.now()
  });

  response.setHeader(
    'Set-Cookie',
    cookieParts.join('; ')
  );
}

function getExpiredSessionId(request) {
  const cookies = parseCookies(request);
  return cookies.session_id;
}

function getSession(request, response) {
  const sessionId = getExpiredSessionId(request);

  if (!sessionId) {
    return null;
  }

  const session = sessions.get(sessionId) || null;

  if (!session) {
    return null;
  }

  if (Date.now() - session.createdAt > SESSION_MAX_AGE_MS) {
    sessions.delete(sessionId);
    if (response) {
      response.setHeader('Set-Cookie', 'session_id=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
    }
    return null;
  }

  return session;
}

function clearSession(request, response) {
  const sessionId = getExpiredSessionId(request);

  if (sessionId) {
    sessions.delete(sessionId);
  }

  response.setHeader('Set-Cookie', 'session_id=; Path=/; HttpOnly; SameSite=Lax; Max-Age=0');
}

function resolveRequestPath(urlPath) {
  const relativePath = path.normalize(urlPath.replace(/^\/+/, '') || 'index.html');

  if (relativePath.startsWith('..') || path.isAbsolute(relativePath)) {
    return null;
  }

  return path.join(ROOT_DIR, relativePath);
}

function isValidEmail(value) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

function validateSignupInput(name, email, password, confirmPassword) {
  if (!name || !email || !password || !confirmPassword) {
    return 'Name, email, password and password confirmation are required.';
  }

  if (!isValidEmail(email) || email.length > 254) {
    return 'Enter a valid email address.';
  }

  if (name.length > 80) {
    return 'Name must be 80 characters or fewer.';
  }

  if (password.length < 8 || password.length > 72) {
    return 'Use a password between 8 and 72 characters.';
  }

  if (password !== confirmPassword) {
    return 'Password confirmation must match.';
  }

  return '';
}

function logRequest(request, response, startedAt, routePath) {
  response.on('finish', () => {
    console.info('%s %s -> %s (%dms)', request.method, routePath, response.statusCode, Date.now() - startedAt);
  });
}

function logServerError(context, routePath, error) {
  console.error('[%s] %s: %s', context, routePath, error && error.message ? error.message : 'Unknown error');
}

function parseFormBody(request) {
  return new Promise((resolve, reject) => {
    let body = '';

    request.on('data', chunk => {
      body += chunk.toString();
      if (body.length > 1e6) {
        request.socket.destroy();
        reject(new Error('Request body too large'));
      }
    });

    request.on('end', () => {
      try {
        const params = new URLSearchParams(body);
        const result = {};
        for (const [key, value] of params.entries()) {
          result[key] = value;
        }
        resolve(result);
      } catch (error) {
        reject(error);
      }
    });

    request.on('error', reject);
  });
}

async function handleLogin(request, response) {
  try {
    const formData = await parseFormBody(request);
    const email = (formData.email || '').trim().toLowerCase();
    const password = formData.password || '';

    if (!email || !password) {
      sendText(response, 400, 'Email and password are required.');
      return;
    }

    if (!isValidEmail(email)) {
      sendText(response, 400, 'Enter a valid email address.');
      return;
    }

    if (password.length < 8 || password.length > 72) {
      sendText(response, 400, 'Enter a password between 8 and 72 characters.');
      return;
    }

    if (email === ADMIN_EMAIL && password === ADMIN_PASSWORD) {
      createSession(response, {
        id: 0,
        email,
        name: 'Admin',
        role: 'admin'
      });
      sendRedirect(response, '/admin');
      return;
    }

    const connection = await pool.getConnection();

    try {
      const [users] = await connection.query(
        'SELECT id, name, password_hash FROM users WHERE email = ?',
        [email]
      );

      if (users.length === 0) {
        sendText(response, 401, 'Invalid email or password.');
        return;
      }

      const user = users[0];
      const passwordMatches = await bcrypt.compare(password, user.password_hash);

      if (!passwordMatches) {
        sendText(response, 401, 'Invalid email or password.');
        return;
      }

      createSession(response, {
        id: user.id,
        email,
        name: user.name || email.split('@')[0],
        role: 'customer'
      });
      sendRedirect(response, '/index.html');
    } finally {
      connection.release();
    }
  } catch (error) {
    logServerError('login', request.url, error);
    sendText(response, 500, 'Internal Server Error');
  }
}

function handleAdminPage(request, response) {
  const session = getSession(request, response);

  if (!session || session.role !== 'admin') {
    sendRedirect(response, '/login.php');
    return;
  }

  const adminPath = path.join(ROOT_DIR, 'admin.html');
  sendFile(adminPath, response, request, { 'Cache-Control': 'no-store' });
}

function handleLogout(request, response) {
  clearSession(request, response);
    sendRedirect(response, '/login.php');
}

function handleSessionInfo(request, response) {
  const session = getSession(request, response);
  sendJson(response, 200, session || null, { 'Cache-Control': 'no-store' });
}

async function handleSignup(request, response) {
  try {
    const formData = await parseFormBody(request);
    const name = (formData.name || '').trim();
    const email = (formData.email || '').trim().toLowerCase();
    const password = formData.password || '';
    const confirmPassword = formData.confirmPassword || '';
    const validationError = validateSignupInput(name, email, password, confirmPassword);

    if (validationError) {
      sendText(response, 400, validationError);
      return;
    }

    const connection = await pool.getConnection();

    try {
      const [existing] = await connection.query(
        'SELECT id FROM users WHERE email = ?',
        [email]
      );

      if (existing.length > 0) {
        sendText(response, 409, 'An account with this email already exists.');
        return;
      }

      const passwordHash = await bcrypt.hash(password, 10);
      await connection.query(
        'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)',
        [name, email, passwordHash]
      );

      response.writeHead(302, { Location: '/login.php' });
      response.end();
    } finally {
      connection.release();
    }
  } catch (error) {
    logServerError('signup', request.url, error);
    sendText(response, 500, 'Internal Server Error');
  }
}

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url, `http://${request.headers.host || HOST}`);
  const routePath = requestUrl.pathname === '/' ? '/index.html' : requestUrl.pathname;
  const startedAt = Date.now();

  applySecurityHeaders(response);
  logRequest(request, response, startedAt, routePath);

  if (request.method === 'POST' && routePath === '/login') {
    handleLogin(request, response);
    return;
  }

  if (request.method === 'POST' && routePath === '/signup') {
    handleSignup(request, response);
    return;
  }

  if (request.method === 'POST' && routePath === '/logout') {
    handleLogout(request, response);
    return;
  }

  if (request.method === 'GET' && routePath === '/admin') {
    handleAdminPage(request, response);
    return;
  }

  if (request.method === 'GET' && routePath === '/api/session') {
    handleSessionInfo(request, response);
    return;
  }

  if (request.method !== 'GET' && request.method !== 'HEAD') {
    sendText(response, 405, 'Method Not Allowed');
    return;
  }

  const filePath = resolveRequestPath(routePath);

  if (!filePath) {
    sendText(response, 403, 'Forbidden');
    return;
  }

  if (routePath === '/admin.html') {
    sendRedirect(response, '/admin');
    return;
  }

  fs.stat(filePath, (error, stats) => {
    if (error || !stats.isFile()) {
      sendNotFound(response);
      return;
    }

    sendFile(filePath, response, request);
  });
});

server.listen(PORT, HOST, () => {
  if (IS_PRODUCTION && !process.env.ADMIN_PASSWORD) {
    console.warn('ADMIN_PASSWORD is not configured. The built-in admin login is disabled in production.');
  }
  console.log(`The Inkwell is running at http://${HOST}:${PORT}`);
});
