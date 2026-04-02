<?php
declare(strict_types=1);

const SESSION_MAX_AGE_SECONDS = 43200;

function env_flag(string $name): bool
{
    $value = getenv($name);
    if (!is_string($value)) {
        return false;
    }

    $normalized = strtolower(trim($value));

    return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
}

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

function app_show_reset_debug_link(): bool
{
    return env_flag('SHOW_RESET_DEBUG_LINK');
}

function remote_address(): string
{
    return normalize_ip_address((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

function is_trusted_proxy_request(): bool
{
    $remoteAddress = remote_address();
    if ($remoteAddress === '') {
        return false;
    }

    return in_array($remoteAddress, trusted_proxy_addresses(), true);
}

function forwarded_proto(): string
{
    if (!is_trusted_proxy_request()) {
        return '';
    }

    $xForwardedProto = trim((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if ($xForwardedProto !== '') {
        $parts = array_map('trim', explode(',', strtolower($xForwardedProto)));
        $proto = (string) ($parts[0] ?? '');
        if ($proto === 'https' || $proto === 'http') {
            return $proto;
        }
    }

    $forwarded = trim((string) ($_SERVER['HTTP_FORWARDED'] ?? ''));
    if ($forwarded !== '' && preg_match('/proto=(https|http)/i', $forwarded, $matches) === 1) {
        return strtolower((string) $matches[1]);
    }

    return '';
}

function is_secure_request(): bool
{
    $forwardedProto = forwarded_proto();
    if ($forwardedProto !== '') {
        return $forwardedProto === 'https';
    }

    $requestScheme = strtolower(trim((string) ($_SERVER['REQUEST_SCHEME'] ?? '')));
    if ($requestScheme === 'https') {
        return true;
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }

    if (trim((string) ($_SERVER['SSL_PROTOCOL'] ?? '')) !== '') {
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
        "frame-src 'self' blob:; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self'; " .
        "frame-ancestors 'none'"
    );
    header('Permissions-Policy: camera=(), geolocation=(), microphone=()');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-Content-Type-Options: nosniff');

    if (app_is_production() && is_secure_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
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

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function request_method(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

function request_host(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    return preg_match('/^[A-Za-z0-9.\-:\[\]]+$/', $host) === 1 ? $host : '';
}

function current_origin(): string
{
    $scheme = is_secure_request() ? 'https' : 'http';
    $host = request_host();

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

function trusted_proxy_addresses(): array
{
    static $trustedProxies = null;

    if (is_array($trustedProxies)) {
        return $trustedProxies;
    }

    $configured = trim((string) (getenv('TRUSTED_PROXY_IPS') ?: ''));
    if ($configured === '') {
        $trustedProxies = [];
        return $trustedProxies;
    }

    $trustedProxies = [];

    foreach (explode(',', $configured) as $candidate) {
        $candidate = trim($candidate);
        if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
            $trustedProxies[] = $candidate;
        }
    }

    return $trustedProxies;
}

function normalize_ip_address(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $validated = filter_var($value, FILTER_VALIDATE_IP);

    return is_string($validated) ? $validated : '';
}

function client_ip(): string
{
    $remoteAddress = remote_address();

    if (is_trusted_proxy_request()) {
        $cfConnectingIp = normalize_ip_address((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cfConnectingIp !== '') {
            return $cfConnectingIp;
        }

        $forwardedFor = trim((string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''));
        if ($forwardedFor !== '') {
            $parts = array_map('trim', explode(',', $forwardedFor));
            $forwardedIp = normalize_ip_address((string) ($parts[0] ?? ''));
            if ($forwardedIp !== '') {
                return $forwardedIp;
            }
        }
    }

    if ($remoteAddress !== '') {
        return $remoteAddress;
    }

    return 'unknown';
}

function absolute_url(string $path, array $query = []): string
{
    $origin = current_origin();
    $url = $origin . '/' . ltrim($path, '/');

    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    return $url;
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

function enforce_https_request(): void
{
    if (!app_is_production() || is_secure_request()) {
        return;
    }

    $host = request_host();
    log_security_event('insecure_production_request_blocked', ['host' => $host]);

    if ($host !== '' && in_array(request_method(), ['GET', 'HEAD'], true)) {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        redirect_to('https://' . $host . $requestUri, 308);
    }

    send_text(400, 'HTTPS required');
}

function log_server_error(string $context, Throwable $error): void
{
    error_log(sprintf('[%s] %s', $context, $error->getMessage()));
}

function log_security_event(string $event, array $context = []): void
{
    $payload = [
        'event' => $event,
        'ip' => client_ip(),
        'path' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
        'method' => request_method(),
        'time' => gmdate('c'),
    ];

    foreach ($context as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        $payload[$key] = $value;
    }

    error_log('[security] ' . json_encode($payload, JSON_UNESCAPED_SLASHES));
}

if (app_is_production() && app_show_reset_debug_link()) {
    error_log('[security] SHOW_RESET_DEBUG_LINK must be disabled when APP_ENV=production.');
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Application configuration error.';
    exit;
}

enforce_https_request();
apply_security_headers();
start_app_session();
