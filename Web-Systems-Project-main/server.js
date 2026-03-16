'use strict';

const http = require('http');
const fs = require('fs');
const path = require('path');
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
    const email = (formData.email || '').trim();
    const password = formData.password || '';

    if (!email || !password) {
      response.writeHead(400, { 'Content-Type': 'text/plain; charset=utf-8' });
      response.end('Email and password are required.');
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

      response.writeHead(302, { Location: '/index.html' });
      response.end();
    } finally {
      connection.release();
    }
  } catch (error) {
    console.error('Error handling login:', error);
    response.writeHead(500, { 'Content-Type': 'text/plain; charset=utf-8' });
    response.end('Internal Server Error');
  }
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
