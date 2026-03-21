'use strict';

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
  database: process.env.DB_NAME || 'bookstore_login',
  waitForConnections: true,
  connectionLimit: 10,
  queueLimit: 0
};

const pool = mysql.createPool(DB_CONFIG);
const sessions = new Map();

const ADMIN_EMAIL = (process.env.ADMIN_EMAIL || 'admin@pagemark.local').toLowerCase();
const ADMIN_PASSWORD = process.env.ADMIN_PASSWORD || 'Admin123!';
const SESSION_MAX_AGE_SECONDS = 60 * 60 * 12;

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

function sendFile(filePath, response) {
  const extension = path.extname(filePath).toLowerCase();
  const contentType = MIME_TYPES[extension] || 'application/octet-stream';

  fs.readFile(filePath, (error, content) => {
    if (error) {
      if (error.code === 'ENOENT') {
        sendNotFound(response);
        return;
      }

      response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('Internal Server Error');
      return;
    }

    response.writeHead(200, { 'Content-Type': contentType });
    response.end(content);
  });
}

function sendNotFound(response) {
  response.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
  response.end('404 Not Found');
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

  sessions.set(sessionId, {
    id: user.id,
    email: user.email,
    role: user.role,
    createdAt: Date.now()
  });

  response.setHeader(
    'Set-Cookie',
    `session_id=${sessionId}; Path=/; HttpOnly; SameSite=Lax; Max-Age=${SESSION_MAX_AGE_SECONDS}`
  );
}

function getSession(request) {
  const cookies = parseCookies(request);
  const sessionId = cookies.session_id;

  if (!sessionId) {
    return null;
  }

  return sessions.get(sessionId) || null;
}

function clearSession(request, response) {
  const cookies = parseCookies(request);
  const sessionId = cookies.session_id;

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
      response.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('Email and password are required.');
      return;
    }

    if (email === ADMIN_EMAIL && password === ADMIN_PASSWORD) {
      createSession(response, {
        id: 0,
        email,
        role: 'admin'
      });
      sendRedirect(response, '/admin');
      return;
    }

    const connection = await pool.getConnection();

    try {
      const [users] = await connection.query(
        'SELECT id, password_hash FROM users WHERE email = ?',
        [email]
      );

      if (users.length === 0) {
        response.writeHead(401, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end('Invalid email or password.');
        return;
      }

      const user = users[0];
      const passwordMatches = await bcrypt.compare(password, user.password_hash);

      if (!passwordMatches) {
        response.writeHead(401, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end('Invalid email or password.');
        return;
      }

      createSession(response, {
        id: user.id,
        email,
        role: 'customer'
      });
      sendRedirect(response, '/index.html');
    } finally {
      connection.release();
    }
  } catch (error) {
    console.error('Error handling login:', error);
    response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Internal Server Error');
  }
}

function handleAdminPage(request, response) {
  const session = getSession(request);

  if (!session || session.role !== 'admin') {
    sendRedirect(response, '/login.html');
    return;
  }

  const adminPath = path.join(ROOT_DIR, 'admin.html');
  sendFile(adminPath, response);
}

function handleLogout(request, response) {
  clearSession(request, response);
  sendRedirect(response, '/login.html');
}

function handleSessionInfo(request, response) {
  const session = getSession(request);

  response.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
  response.end(JSON.stringify(session || null));
}

async function handleSignup(request, response) {
  try {
    const formData = await parseFormBody(request);
    const name = (formData.name || '').trim();
    const email = (formData.email || '').trim();
    const password = formData.password || '';

    if (!name || !email || !password) {
      response.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('Name, email and password are required.');
      return;
    }

    const connection = await pool.getConnection();

    try {
      const [existing] = await connection.query(
        'SELECT id FROM users WHERE email = ?',
        [email]
      );

      if (existing.length > 0) {
        response.writeHead(409, { 'Content-Type': 'text/plain; charset=utf-8' });
        response.end('An account with this email already exists.');
        return;
      }

      const passwordHash = await bcrypt.hash(password, 10);
      await connection.query(
        'INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)',
        [name, email, passwordHash]
      );

      response.writeHead(302, { Location: '/login.html' });
      response.end();
    } finally {
      connection.release();
    }
  } catch (error) {
    console.error('Error handling signup:', error);
    response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Internal Server Error');
  }
}

const server = http.createServer((request, response) => {
  const requestUrl = new URL(request.url, `http://${request.headers.host || HOST}`);
  const routePath = requestUrl.pathname === '/' ? '/index.html' : requestUrl.pathname;

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
    response.writeHead(405, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Method Not Allowed');
    return;
  }

  const filePath = resolveRequestPath(routePath);

  if (!filePath) {
    response.writeHead(403, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Forbidden');
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

    sendFile(filePath, response);
  });
});

server.listen(PORT, HOST, () => {
  console.log(`The Inkwell is running at http://${HOST}:${PORT}`);
});
