<?php
declare(strict_types=1);

const SESSION_MAX_AGE_SECONDS = 43200;

function app_is_production(): bool
{
    static $isProduction = null;

    if ($isProduction !== null) {
        return $isProduction;
    }

    $environment = getenv('APP_ENV') ?: getenv('NODE_ENV') ?: '';
    $isProduction = strtolower($environment) === 'production';

    return $isProduction;
}

function is_secure_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    return false;
}

function apply_security_headers(): void
{
    header(
        "Content-Security-Policy: default-src 'self'; " .
        "script-src 'self'; " .
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "img-src 'self' data: https://picsum.photos https://fastly.picsum.photos; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "connect-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none'"
    );
    header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');

    session_name('pagemark_session');
    session_set_cookie_params([
        'lifetime' => SESSION_MAX_AGE_SECONDS,
        'path' => '/',
        'domain' => '',
        'secure' => app_is_production() || is_secure_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'localhost';
    $name = getenv('DB_NAME') ?: 'bookstore';
    $user = getenv('DB_USER') ?: 'root';
    $password = getenv('DB_PASSWORD') ?: '';

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);

    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function current_origin(): string
{
    $scheme = is_secure_request() ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    return $host !== '' ? $scheme . '://' . $host : '';
}

function is_same_origin_request(): bool
{
    $currentOrigin = current_origin();
    if ($currentOrigin === '') {
        return false;
    }

    $fetchSite = strtolower(trim((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')));
    if ($fetchSite === 'same-origin' || $fetchSite === 'same-site') {
        return true;
    }

    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $header) {
        $headerValue = trim((string) ($_SERVER[$header] ?? ''));
        if ($headerValue === '') {
            continue;
        }

        $parts = parse_url($headerValue);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            continue;
        }

        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }

        return hash_equals($currentOrigin, $origin);
    }

    return false;
}

function require_same_origin_post(): void
{
    if (request_method() !== 'POST') {
        return;
    }

    if (!is_same_origin_request()) {
        send_text(403, 'Forbidden');
    }
}

function no_cache(): void
{
    header('Cache-Control: no-store');
}

function redirect_with_query(string $location, array $params = [], int $statusCode = 303): never
{
    $filtered = [];

    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $filtered[$key] = $value;
    }

    $query = http_build_query($filtered);
    if ($query !== '') {
        $location .= (strpos($location, '?') !== false ? '&' : '?') . $query;
    }

    redirect_to($location, $statusCode);
}

function send_text(int $statusCode, string $message, array $extraHeaders = []): never
{
    http_response_code($statusCode);
    header('Content-Type: text/plain; charset=utf-8');

    foreach ($extraHeaders as $name => $value) {
        header($name . ': ' . $value);
    }

    echo $message;
    exit;
}

function send_json(int $statusCode, $payload, array $extraHeaders = []): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    foreach ($extraHeaders as $name => $value) {
        header($name . ': ' . $value);
    }

    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function redirect_to(string $location, int $statusCode = 302): never
{
    header('Location: ' . $location, true, $statusCode);
    exit;
}

function log_server_error(string $context, Throwable $error): void
{
    error_log(sprintf('[%s] %s', $context, $error->getMessage()));
}

apply_security_headers();
start_app_session();
