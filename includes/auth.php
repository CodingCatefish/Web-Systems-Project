<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function normalize_email(string $value): string
{
    return strtolower(trim($value));
}

function is_valid_email(string $value): bool
{
    return (bool) filter_var($value, FILTER_VALIDATE_EMAIL);
}

function validate_signup_input(string $name, string $email, string $password, string $confirmPassword): string
{
    $nameLength = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);

    if ($name === '' || $email === '' || $password === '' || $confirmPassword === '') {
        return 'missing_signup_fields';
    }

    if (!is_valid_email($email) || strlen($email) > 254) {
        return 'invalid_email';
    }

    if ($nameLength > 80) {
        return 'signup_name_too_long';
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'invalid_password_length';
    }

    if ($password !== $confirmPassword) {
        return 'password_mismatch';
    }

    return '';
}

function validate_login_input(string $email, string $password): string
{
    if ($email === '' || $password === '') {
        return 'missing_login_fields';
    }

    if (!is_valid_email($email)) {
        return 'invalid_email';
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'invalid_password_length';
    }

    return '';
}

function admin_email(): string
{
    return normalize_email((string) (getenv('ADMIN_EMAIL') ?: 'admin@pagemark.local'));
}

function admin_password(): string
{
    $password = getenv('ADMIN_PASSWORD') ?: '';
    return is_string($password) ? $password : '';
}

function create_session_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'role' => (string) ($user['role'] ?? 'customer'),
        'created_at' => time(),
    ];
}

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    if (!is_array($user)) {
        return null;
    }

    $createdAt = isset($user['created_at']) ? (int) $user['created_at'] : 0;
    if ($createdAt <= 0 || (time() - $createdAt) > SESSION_MAX_AGE_SECONDS) {
        clear_session();
        return null;
    }

    return [
        'id' => (int) ($user['id'] ?? 0),
        'email' => (string) ($user['email'] ?? ''),
        'name' => (string) ($user['name'] ?? ''),
        'role' => (string) ($user['role'] ?? 'customer'),
    ];
}

function clear_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => (bool) ($params['secure'] ?? false),
            'httponly' => (bool) ($params['httponly'] ?? true),
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function require_admin(): array
{
    $user = current_user();

    if ($user === null || ($user['role'] ?? '') !== 'admin') {
        redirect_to('login.html');
    }

    return $user;
}
