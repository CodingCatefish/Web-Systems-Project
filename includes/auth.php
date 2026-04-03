<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const LOGIN_RATE_LIMIT_MAX_ATTEMPTS = 5;
const LOGIN_RATE_LIMIT_WINDOW_SECONDS = 900;
const PASSWORD_RESET_TTL_SECONDS = 3600;

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

function validate_password_reset_request_input(string $email): string
{
    if ($email === '') {
        return 'missing_reset_email';
    }

    if (!is_valid_email($email) || strlen($email) > 254) {
        return 'invalid_email';
    }

    return '';
}

function validate_password_reset_input(string $password, string $confirmPassword): string
{
    if ($password === '' || $confirmPassword === '') {
        return 'missing_reset_fields';
    }

    if (strlen($password) < 8 || strlen($password) > 72) {
        return 'invalid_password_length';
    }

    if ($password !== $confirmPassword) {
        return 'password_mismatch';
    }

    return '';
}

function auth_feedback_catalog(string $formKey): array
{
    $catalog = [
        'login' => [
            'value_fields' => ['email'],
            'errors' => [
                'missing_login_fields' => [
                    'summary' => 'Email and password are required.',
                    'fields' => [
                        'email' => 'Email address is required.',
                        'password' => 'Password is required.',
                    ],
                ],
                'invalid_email' => [
                    'summary' => 'Enter a valid email address.',
                    'fields' => [
                        'email' => 'Enter a valid email address.',
                    ],
                ],
                'invalid_password_length' => [
                    'summary' => 'Enter a password between 8 and 72 characters.',
                    'fields' => [
                        'password' => 'Enter a password between 8 and 72 characters.',
                    ],
                ],
                'invalid_credentials' => [
                    'summary' => 'Invalid email or password.',
                ],
                'csrf_invalid_origin' => [
                    'summary' => 'Your session could not be verified. Please try signing in again from this page.',
                ],
                'csrf_invalid_token' => [
                    'summary' => 'Your session expired. Please try signing in again.',
                ],
                'login_rate_limited' => [
                    'summary' => 'Too many sign-in attempts. Please wait before trying again.',
                ],
                'login_required' => [
                    'summary' => 'Sign in to continue.',
                ],
                'server_error' => [
                    'summary' => 'We could not sign you in right now. Please try again.',
                ],
            ],
            'notices' => [
                'account_created' => 'Account created. You can sign in now.',
                'password_reset_completed' => 'Password updated. You can sign in with your new password now.',
            ],
        ],
        'signup' => [
            'value_fields' => ['name', 'email'],
            'errors' => [
                'missing_signup_fields' => [
                    'summary' => 'Please complete all required fields.',
                    'fields' => [
                        'name' => 'Full name is required.',
                        'email' => 'Email address is required.',
                        'password' => 'Password is required.',
                        'confirmPassword' => 'Please confirm your password.',
                    ],
                ],
                'invalid_email' => [
                    'summary' => 'Enter a valid email address.',
                    'fields' => [
                        'email' => 'Enter a valid email address.',
                    ],
                ],
                'signup_name_too_long' => [
                    'summary' => 'Name must be 80 characters or fewer.',
                    'fields' => [
                        'name' => 'Name must be 80 characters or fewer.',
                    ],
                ],
                'invalid_password_length' => [
                    'summary' => 'Use a password between 8 and 72 characters.',
                    'fields' => [
                        'password' => 'Use a password between 8 and 72 characters.',
                    ],
                ],
                'password_mismatch' => [
                    'summary' => 'Password confirmation must match.',
                    'fields' => [
                        'confirmPassword' => 'Password confirmation must match.',
                    ],
                ],
                'duplicate_email' => [
                    'summary' => 'An account with this email already exists.',
                    'fields' => [
                        'email' => 'An account with this email already exists.',
                    ],
                ],
                'csrf_invalid_origin' => [
                    'summary' => 'Your session could not be verified. Please submit the form again from this page.',
                ],
                'csrf_invalid_token' => [
                    'summary' => 'Your session expired. Please submit the form again.',
                ],
                'server_error' => [
                    'summary' => 'We could not create your account right now. Please try again.',
                ],
            ],
            'notices' => [],
        ],
        'forgot-password' => [
            'value_fields' => ['email'],
            'errors' => [
                'missing_reset_email' => [
                    'summary' => 'Enter the email address for your account.',
                    'fields' => [
                        'email' => 'Email address is required.',
                    ],
                ],
                'invalid_email' => [
                    'summary' => 'Enter a valid email address.',
                    'fields' => [
                        'email' => 'Enter a valid email address.',
                    ],
                ],
                'csrf_invalid_origin' => [
                    'summary' => 'Your session could not be verified. Please submit the form again from this page.',
                ],
                'csrf_invalid_token' => [
                    'summary' => 'Your session expired. Please submit the form again.',
                ],
                'server_error' => [
                    'summary' => 'We could not start a password reset right now. Please try again.',
                ],
            ],
            'notices' => [
                'password_reset_requested' => 'If an account exists for that email, a reset link is ready.',
            ],
        ],
        'reset-password' => [
            'value_fields' => [],
            'errors' => [
                'missing_reset_fields' => [
                    'summary' => 'Enter and confirm your new password.',
                    'fields' => [
                        'password' => 'Password is required.',
                        'confirmPassword' => 'Please confirm your password.',
                    ],
                ],
                'invalid_password_length' => [
                    'summary' => 'Use a password between 8 and 72 characters.',
                    'fields' => [
                        'password' => 'Use a password between 8 and 72 characters.',
                    ],
                ],
                'password_mismatch' => [
                    'summary' => 'Password confirmation must match.',
                    'fields' => [
                        'confirmPassword' => 'Password confirmation must match.',
                    ],
                ],
                'invalid_reset_token' => [
                    'summary' => 'This reset link is invalid. Request a new password reset link.',
                ],
                'expired_reset_token' => [
                    'summary' => 'This reset link has expired. Request a new password reset link.',
                ],
                'csrf_invalid_origin' => [
                    'summary' => 'Your session could not be verified. Please submit the form again from this page.',
                ],
                'csrf_invalid_token' => [
                    'summary' => 'Your session expired. Please submit the form again.',
                ],
                'server_error' => [
                    'summary' => 'We could not reset your password right now. Please try again.',
                ],
            ],
            'notices' => [],
        ],
    ];

    return $catalog[$formKey] ?? ['value_fields' => [], 'errors' => [], 'notices' => []];
}

function get_auth_feedback(string $formKey): array
{
    $config = auth_feedback_catalog($formKey);
    $feedback = [
        'values' => [],
        'field_errors' => [],
        'summary' => '',
        'status' => '',
        'error_code' => trim((string) ($_GET['auth_error'] ?? '')),
        'notice_code' => trim((string) ($_GET['auth_notice'] ?? '')),
        'retry_after' => max(0, (int) ($_GET['retry_after'] ?? 0)),
    ];

    foreach ($config['value_fields'] as $fieldName) {
        $feedback['values'][$fieldName] = trim((string) ($_GET[$fieldName] ?? ''));
    }

    if ($feedback['error_code'] !== '' && isset($config['errors'][$feedback['error_code']])) {
        $entry = $config['errors'][$feedback['error_code']];
        $feedback['summary'] = (string) ($entry['summary'] ?? '');
        $feedback['status'] = $feedback['summary'];
        $feedback['field_errors'] = $entry['fields'] ?? [];
    }

    if ($feedback['notice_code'] !== '' && isset($config['notices'][$feedback['notice_code']])) {
        $feedback['status'] = (string) $config['notices'][$feedback['notice_code']];
    }

    if ($feedback['error_code'] === 'login_rate_limited' && $feedback['retry_after'] > 0) {
        $minutes = (int) ceil($feedback['retry_after'] / 60);
        $feedback['summary'] .= ' Try again in about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's') . '.';
        $feedback['status'] = $feedback['summary'];
    }

    return $feedback;
}

function auth_feedback_value(array $feedback, string $fieldName): string
{
    return (string) ($feedback['values'][$fieldName] ?? '');
}

function auth_feedback_field_error(array $feedback, string $fieldName): string
{
    return (string) ($feedback['field_errors'][$fieldName] ?? '');
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

function current_script_name(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

function is_current_script(string $scriptName): bool
{
    return current_script_name() === $scriptName;
}

function current_user_id(): int
{
    $user = current_user();
    if (is_array($user)) {
        return (int) ($user['id'] ?? 0);
    }

    $sessionUser = $_SESSION['user'] ?? null;
    if (is_array($sessionUser)) {
        return (int) ($sessionUser['id'] ?? 0);
    }

    return max(0, (int) ($_SESSION['id'] ?? 0));
}

function current_user_role(): string
{
    $user = current_user();
    if (is_array($user)) {
        return strtolower((string) ($user['role'] ?? ''));
    }

    $sessionUser = $_SESSION['user'] ?? null;
    if (is_array($sessionUser)) {
        return strtolower((string) ($sessionUser['role'] ?? ''));
    }

    return strtolower((string) ($_SESSION['role'] ?? ''));
}

function csrf_token(): string
{
    $token = $_SESSION['csrf_token'] ?? null;
    if (is_string($token) && $token !== '') {
        return $token;
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(string $token): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($sessionToken) && $sessionToken !== '' && hash_equals($sessionToken, $token);
}

function flash_set(string $key, $value): void
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }

    $_SESSION['flash'][$key] = $value;
}

function flash_get(string $key, $default = null)
{
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash']) || !array_key_exists($key, $_SESSION['flash'])) {
        return $default;
    }

    $value = $_SESSION['flash'][$key];
    unset($_SESSION['flash'][$key]);

    return $value;
}

function create_session_user(array $user): void
{
    session_regenerate_id(true);
    csrf_token();

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

function handle_csrf_failure(string $redirectPath, array $params = [], string $errorCode = 'csrf_invalid_token'): never
{
    $params['auth_error'] = $errorCode;
    redirect_with_query($redirectPath, $params);
}

function require_valid_form_post(string $redirectPath, array $params = []): void
{
    if (!is_same_origin_request()) {
        handle_csrf_failure($redirectPath, $params, 'csrf_invalid_origin');
    }

    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        handle_csrf_failure($redirectPath, $params, 'csrf_invalid_token');
    }
}

function find_user_by_email(string $email): ?array
{
    $statement = db()->prepare('SELECT id, name, email, password_hash, role FROM Users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();

    return is_array($user) ? $user : null;
}

function get_login_rate_limit_state(string $email, string $ipAddress): array
{
    $state = [
        'limited' => false,
        'retry_after' => 0,
        'attempt_count' => 0,
    ];

    if ($email === '' || $ipAddress === '') {
        return $state;
    }

    try {
        $statement = db()->prepare(
            'SELECT attempt_count, UNIX_TIMESTAMP(first_attempt_at) AS first_attempt_ts
             FROM LoginAttempts
             WHERE email = ? AND ip_address = ?
             LIMIT 1'
        );
        $statement->execute([$email, $ipAddress]);
        $row = $statement->fetch();

        if (!is_array($row)) {
            return $state;
        }

        $firstAttemptTs = (int) ($row['first_attempt_ts'] ?? 0);
        $elapsed = time() - $firstAttemptTs;

        if ($firstAttemptTs <= 0 || $elapsed >= LOGIN_RATE_LIMIT_WINDOW_SECONDS) {
            clear_login_rate_limit_state($email, $ipAddress);
            return $state;
        }

        $attemptCount = (int) ($row['attempt_count'] ?? 0);

        return [
            'limited' => $attemptCount >= LOGIN_RATE_LIMIT_MAX_ATTEMPTS,
            'retry_after' => max(0, LOGIN_RATE_LIMIT_WINDOW_SECONDS - $elapsed),
            'attempt_count' => $attemptCount,
        ];
    } catch (Throwable $error) {
        log_server_error('login-rate-limit-read', $error);
        return $state;
    }
}

function record_failed_login_attempt(string $email, string $ipAddress): array
{
    if ($email === '' || $ipAddress === '') {
        return ['limited' => false, 'retry_after' => 0, 'attempt_count' => 0];
    }

    try {
        $state = get_login_rate_limit_state($email, $ipAddress);

        if ($state['attempt_count'] > 0) {
            $statement = db()->prepare(
                'UPDATE LoginAttempts
                 SET attempt_count = attempt_count + 1, last_attempt_at = UTC_TIMESTAMP()
                 WHERE email = ? AND ip_address = ?'
            );
            $statement->execute([$email, $ipAddress]);
        } else {
            $statement = db()->prepare(
                'INSERT INTO LoginAttempts (email, ip_address, attempt_count, first_attempt_at, last_attempt_at)
                 VALUES (?, ?, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            );
            $statement->execute([$email, $ipAddress]);
        }
    } catch (Throwable $error) {
        log_server_error('login-rate-limit-write', $error);
    }

    return get_login_rate_limit_state($email, $ipAddress);
}

function clear_login_rate_limit_state(string $email, string $ipAddress): void
{
    if ($email === '' || $ipAddress === '') {
        return;
    }

    try {
        $statement = db()->prepare('DELETE FROM LoginAttempts WHERE email = ? AND ip_address = ?');
        $statement->execute([$email, $ipAddress]);
    } catch (Throwable $error) {
        log_server_error('login-rate-limit-clear', $error);
    }
}

function is_unique_constraint_violation(Throwable $error): bool
{
    if (!$error instanceof PDOException) {
        return false;
    }

    if ((string) $error->getCode() === '23000') {
        return true;
    }

    $errorInfo = $error->errorInfo ?? null;

    return is_array($errorInfo) && (string) ($errorInfo[0] ?? '') === '23000';
}

function create_password_reset(string $email): ?string
{
    $user = find_user_by_email($email);
    $pdo = null;

    log_security_event('password_reset_requested', ['email' => $email]);

    if ($user === null) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $debugLink = null;

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $invalidateStatement = $pdo->prepare(
            'UPDATE PasswordResets
             SET used_at = UTC_TIMESTAMP()
             WHERE user_id = ? AND used_at IS NULL'
        );
        $invalidateStatement->execute([(int) $user['id']]);

        $insertStatement = $pdo->prepare(
            'INSERT INTO PasswordResets (user_id, token_hash, expires_at, requested_ip, user_agent)
             VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? SECOND), ?, ?)'
        );
        $insertStatement->execute([
            (int) $user['id'],
            $tokenHash,
            PASSWORD_RESET_TTL_SECONDS,
            client_ip(),
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);

        $pdo->commit();
        log_security_event('password_reset_token_created', ['email' => $email, 'user_id' => (int) $user['id']]);
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_server_error('password-reset-create', $error);
        throw $error;
    }

    if (app_show_reset_debug_link()) {
        $debugLink = absolute_url('reset-password.php', ['token' => $token]);
    }

    return $debugLink;
}

function find_valid_password_reset(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $statement = db()->prepare(
        'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email, u.name
         FROM PasswordResets pr
         INNER JOIN Users u ON u.id = pr.user_id
         WHERE pr.token_hash = ?
         LIMIT 1'
    );
    $statement->execute([hash('sha256', $token)]);
    $reset = $statement->fetch();

    return is_array($reset) ? $reset : null;
}

function password_reset_status(string $token): string
{
    $reset = find_valid_password_reset($token);

    if ($reset === null) {
        return 'invalid_reset_token';
    }

    if (!empty($reset['used_at'])) {
        return 'invalid_reset_token';
    }

    $expiresAt = strtotime((string) ($reset['expires_at'] ?? ''));
    if ($expiresAt === false || $expiresAt < time()) {
        return 'expired_reset_token';
    }

    return '';
}

function reset_password_with_token(string $token, string $password): string
{
    $pdo = null;

    try {
        $pdo = db();
        $pdo->beginTransaction();

        $resetStatement = $pdo->prepare(
            'SELECT pr.id, pr.user_id, pr.expires_at, pr.used_at, u.email
             FROM PasswordResets pr
             INNER JOIN Users u ON u.id = pr.user_id
             WHERE pr.token_hash = ?
             LIMIT 1
             FOR UPDATE'
        );
        $resetStatement->execute([hash('sha256', $token)]);
        $reset = $resetStatement->fetch();

        if (!is_array($reset) || !empty($reset['used_at'])) {
            $pdo->rollBack();
            return 'invalid_reset_token';
        }

        $expiresAt = strtotime((string) ($reset['expires_at'] ?? ''));
        if ($expiresAt === false || $expiresAt < time()) {
            $pdo->rollBack();
            return 'expired_reset_token';
        }

        $updatePassword = $pdo->prepare('UPDATE Users SET password_hash = ? WHERE id = ?');
        $updatePassword->execute([
            password_hash($password, PASSWORD_DEFAULT),
            (int) $reset['user_id'],
        ]);

        $markUsed = $pdo->prepare(
            'UPDATE PasswordResets
             SET used_at = UTC_TIMESTAMP()
             WHERE id = ? AND used_at IS NULL AND expires_at >= UTC_TIMESTAMP()'
        );
        $markUsed->execute([(int) $reset['id']]);

        if ($markUsed->rowCount() !== 1) {
            $pdo->rollBack();
            return 'expired_reset_token';
        }

        $clearAttempts = $pdo->prepare('DELETE FROM LoginAttempts WHERE email = ?');
        $clearAttempts->execute([(string) $reset['email']]);

        $pdo->commit();
        log_security_event('password_reset_completed', [
            'user_id' => (int) $reset['user_id'],
            'email' => (string) $reset['email'],
        ]);
    } catch (Throwable $error) {
        if ($pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_server_error('password-reset-complete', $error);
        return 'server_error';
    }

    return '';
}

function send_forbidden_page(string $title = 'Forbidden', string $message = 'You do not have permission to access this page.'): never
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');
    no_cache();
?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | Pagemark</title>
        <link rel="stylesheet" href="css/styles.css">
    </head>

    <body class="auth-page">
        <a href="#main-content" class="skip-link">Skip to main content</a>
        <main id="main-content" class="auth-card">
            <a class="auth-home-link" href="index.html">Back to home</a>
            <div class="auth-logo">
                <h1>Pagemark <span><?= e($title) ?></span></h1>
            </div>
            <p class="auth-subtitle"><?= e($message) ?></p>
            <p class="auth-notice">If you believe this is incorrect, sign in with an administrator account.</p>
            <p class="auth-footer-text"><a href="login.php">Go to login</a></p>
        </main>
    </body>

    </html>
<?php
    exit;
}

function require_admin(): array
{
    $user = current_user();

    if ($user === null) {
        log_security_event('admin_access_requires_login');
        redirect_with_query('login.php', ['auth_error' => 'login_required']);
    }

    if (($user['role'] ?? '') !== 'admin') {
        log_security_event('admin_access_forbidden', [
            'user_id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'role' => (string) $user['role'],
        ]);
        send_forbidden_page('Forbidden', 'Your account is signed in, but it does not have administrator access.');
    }

    return $user;
}

function require_author_access(string $forbiddenMessage = 'Your account is signed in, but it does not have author access.'): array
{
    $user = require_login();
    $role = strtolower((string) ($user['role'] ?? ''));

    if ($role !== 'author' && $role !== 'admin') {
        log_security_event('author_access_forbidden', [
            'user_id' => (int) ($user['id'] ?? 0),
            'email' => (string) ($user['email'] ?? ''),
            'role' => (string) ($user['role'] ?? ''),
        ]);
        send_forbidden_page('Forbidden', $forbiddenMessage);
    }

    return $user;
}

function dashboard_counts(): array
{
    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];
    $reviewsTable = $schema['reviews_table'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];

    $counts = [
        'books' => safe_table_count($booksTable),
        'live_books' => null,
        'pending_books' => null,
        'users' => safe_table_count('Users'),
        'authors' => null,
        'transactions' => safe_table_count($transactionsTable),
        'reviews' => safe_table_count($reviewsTable),
        'author_links' => safe_table_count($authorListsTable),
    ];

    if ($booksTable !== null) {
        $counts['live_books'] = safe_scalar_count(
            'SELECT COUNT(*) FROM ' . sql_identifier($booksTable) . ' WHERE vetted = 1'
        );
        $counts['pending_books'] = safe_scalar_count(
            'SELECT COUNT(*) FROM ' . sql_identifier($booksTable) . ' WHERE vetted = 0'
        );
    }

    $counts['authors'] = safe_scalar_count('SELECT COUNT(*) FROM `Users` WHERE role = ?', ['author']);

    if ($transactionsTable !== null && $transactionsUserColumn !== null) {
        $counts['transactions'] = safe_scalar_count(
            'SELECT COUNT(*) FROM ' . sql_identifier($transactionsTable)
        );
    }

    if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
        $counts['author_links'] = safe_scalar_count(
            'SELECT COUNT(*) FROM ' . sql_identifier($authorListsTable)
        );
    }

    return $counts;
}

function sql_identifier(string $identifier): string
{
    if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
        throw new InvalidArgumentException('Invalid SQL identifier: ' . $identifier);
    }

    return '`' . $identifier . '`';
}

function safe_table_count(?string $tableName): ?int
{
    if (!is_string($tableName) || $tableName === '') {
        return null;
    }

    try {
        $statement = db()->query('SELECT COUNT(*) AS aggregate_count FROM ' . sql_identifier($tableName));
        $row = $statement->fetch();
        return is_array($row) ? (int) ($row['aggregate_count'] ?? 0) : 0;
    } catch (Throwable $error) {
        log_server_error('dashboard-count-' . strtolower($tableName), $error);
        return null;
    }
}

function safe_scalar_count(string $sql, array $params = []): ?int
{
    try {
        $statement = db()->prepare($sql);
        $statement->execute($params);
        $value = $statement->fetchColumn();

        return $value === false ? 0 : (int) $value;
    } catch (Throwable $error) {
        log_server_error('dashboard-scalar-count', $error);
        return null;
    }
}

function first_available_table(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        try {
            db()->query('SELECT 1 FROM ' . sql_identifier($candidate) . ' LIMIT 1');
            return $candidate;
        } catch (Throwable $error) {
            continue;
        }
    }

    return null;
}

function first_available_column(string $tableName, array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (!is_string($candidate) || $candidate === '') {
            continue;
        }

        try {
            db()->query(
                'SELECT ' . sql_identifier($candidate) . ' FROM ' . sql_identifier($tableName) . ' LIMIT 1'
            );
            return $candidate;
        } catch (Throwable $error) {
            continue;
        }
    }

    return null;
}

function admin_catalog_schema(): array
{
    static $schema = null;

    if (is_array($schema)) {
        return $schema;
    }

    $authorListsTable = first_available_table(['AuthorLists', 'AuthorList']);
    $transactionsTable = first_available_table(['Transactions']);

    $schema = [
        'books_table' => first_available_table(['Books', 'Book']),
        'author_lists_table' => $authorListsTable,
        'author_lists_author_column' => $authorListsTable === null ? null : first_available_column($authorListsTable, ['author_id', 'authorID']),
        'transactions_table' => $transactionsTable,
        'transactions_user_column' => $transactionsTable === null ? null : first_available_column($transactionsTable, ['user_id', 'userID']),
        'reviews_table' => first_available_table(['Reviews', 'Review']),
    ];

    return $schema;
}

function count_books_sold_by_author()
{
    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $userId = current_user_id();

    if ($userId <= 0 || $transactionsTable === null || $authorListsTable === null || $authorListsAuthorColumn === null) {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'SELECT COUNT(*)
             FROM ' . sql_identifier($transactionsTable) . ' t
             INNER JOIN ' . sql_identifier($authorListsTable) . ' al ON al.bookID = t.bookID
             WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = ?'
        );
        $statement->execute([$userId]);
        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    } catch (Throwable $error) {
        log_server_error('count-books-sold-by-author', $error);
        return 0;
    }
}

function count_books_by_author(){
    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $userId = current_user_id();

    if ($userId <= 0 || $booksTable === null || $authorListsTable === null || $authorListsAuthorColumn === null) {
        return 0;
    }

    try {
        $statement = db()->prepare(
            'SELECT COUNT(DISTINCT b.bookID)
             FROM ' . sql_identifier($booksTable) . ' b
             INNER JOIN ' . sql_identifier($authorListsTable) . ' al ON al.bookID = b.bookID
             WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = ?'
        );
        $statement->execute([$userId]);
        $count = $statement->fetchColumn();

        return $count === false ? 0 : (int) $count;
    } catch (Throwable $error) {
        log_server_error('count-books-by-author', $error);
        return 0;
    }
}

function sales_by_author_by_date(){
    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $userId = current_user_id();
    $chart = ['Dates' => [], 'Sales' => []];

    if (
        $userId <= 0
        || $transactionsTable === null
        || $transactionsUserColumn === null
        || $authorListsTable === null
        || $authorListsAuthorColumn === null
    ) {
        return $chart;
    }

    try {
        $statement = db()->prepare(
            'SELECT COUNT(t.' . sql_identifier($transactionsUserColumn) . ') AS sales_count,
                    t.date_of_purchase AS purchase_date
             FROM ' . sql_identifier($transactionsTable) . ' t
             INNER JOIN ' . sql_identifier($authorListsTable) . ' al ON al.bookID = t.bookID
             WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = ?
             GROUP BY t.date_of_purchase
             ORDER BY t.date_of_purchase ASC'
        );
        $statement->execute([$userId]);
        $rows = $statement->fetchAll();

        foreach ($rows as $row) {
            $chart['Dates'][] = (string) ($row['purchase_date'] ?? '');
            $chart['Sales'][] = (int) ($row['sales_count'] ?? 0);
        }

        return $chart;
    } catch (Throwable $error) {
        log_server_error('sales-by-author-by-date', $error);
        return $chart;
    }
}


function upload_book(string $title, float $price, string $blurb, string $image, string $pdf){
    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $userId = current_user_id();

    if ($booksTable === null || $authorListsTable === null || $authorListsAuthorColumn === null) {
        throw new RuntimeException('Catalog tables are unavailable for author uploads.');
    }

    if ($userId <= 0) {
        throw new RuntimeException('A signed-in author account is required to upload a book.');
    }

    $pdo = db();

    try {
        $pdo->beginTransaction();

        $statement = $pdo->prepare(
            'INSERT INTO ' . sql_identifier($booksTable) . '
             (title, price, blurb, image, pdf_refrence_path, vetted, created_date)
             VALUES (?, ?, ?, ?, ?, 0, ?)'
        );
        $statement->execute([$title, $price, $blurb, $image, $pdf, date('Y-m-d')]);

        $lastId = (int) $pdo->lastInsertId();
        if ($lastId <= 0) {
            throw new RuntimeException('The uploaded book could not be persisted.');
        }

        $linkStatement = $pdo->prepare(
            'INSERT INTO ' . sql_identifier($authorListsTable) . ' (bookID, ' . sql_identifier($authorListsAuthorColumn) . ')
             VALUES (?, ?)'
        );
        $linkStatement->execute([$lastId, $userId]);

        $pdo->commit();
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_server_error('upload-book', $error);
        throw $error;
    }
}

function sanitize_uploaded_filename(string $filename, string $fallbackBase): string
{
    $filename = trim(str_replace('\\', '/', $filename));
    $basename = basename($filename);
    $extension = strtolower((string) pathinfo($basename, PATHINFO_EXTENSION));
    $name = (string) pathinfo($basename, PATHINFO_FILENAME);
    $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) ?? '';
    $name = trim($name, '-._');

    if ($name === '') {
        $name = $fallbackBase;
    }

    return $extension !== '' ? $name . '.' . $extension : $name;
}

function unique_upload_target_path(string $directory, string $filename): string
{
    $directory = rtrim($directory, '/\\');
    $pathInfo = pathinfo($filename);
    $name = (string) ($pathInfo['filename'] ?? 'upload');
    $extension = strtolower((string) ($pathInfo['extension'] ?? ''));
    $candidate = $directory . DIRECTORY_SEPARATOR . $filename;
    $suffix = 1;

    while (file_exists($candidate)) {
        $candidateName = $name . '-' . $suffix;
        if ($extension !== '') {
            $candidateName .= '.' . $extension;
        }
        $candidate = $directory . DIRECTORY_SEPARATOR . $candidateName;
        $suffix++;
    }

    return $candidate;
}

function upload_error_message(int $errorCode, string $label): string
{
    $messages = [
        UPLOAD_ERR_INI_SIZE => $label . ' exceeds the server upload limit.',
        UPLOAD_ERR_FORM_SIZE => $label . ' exceeds the supported size limit.',
        UPLOAD_ERR_PARTIAL => $label . ' was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => $label . ' is required.',
        UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory.',
        UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
        UPLOAD_ERR_EXTENSION => $label . ' was blocked by a server extension.',
    ];

    return $messages[$errorCode] ?? ('The server could not upload the ' . strtolower($label) . '.');
}

function validate_legacy_author_upload_file(array $file, string $label, array $extensions, int $maxBytes): string
{
    $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($errorCode !== UPLOAD_ERR_OK) {
        return upload_error_message($errorCode, $label);
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return $label . ' could not be read from the upload stream.';
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return $label . ' is empty.';
    }

    if ($size > $maxBytes) {
        return $label . ' is too large.';
    }

    $extension = strtolower((string) pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true)) {
        return $label . ' must be one of: ' . implode(', ', $extensions) . '.';
    }

    return '';
}

function handle_legacy_author_upload_post_if_needed(): void
{
    if (!is_current_script('author-upload.php') || request_method() !== 'POST') {
        return;
    }

    no_cache();
    require_author_access('Your account is signed in, but it does not have author upload access.');

    $title = trim((string) ($_POST['title'] ?? ''));
    $priceRaw = trim((string) ($_POST['price'] ?? ''));
    $blurb = trim((string) ($_POST['blurb'] ?? ''));

    if ($title === '' || $priceRaw === '' || $blurb === '') {
        send_text(422, 'Title, price, and blurb are required.');
    }

    if (!is_numeric($priceRaw)) {
        send_text(422, 'Price must be a numeric value.');
    }

    $imageFile = $_FILES['image'] ?? null;
    $pdfFile = $_FILES['bookPDF'] ?? null;

    if (!is_array($imageFile) || !is_array($pdfFile)) {
        send_text(422, 'Both the cover image and PDF are required.');
    }

    $imageError = validate_legacy_author_upload_file($imageFile, 'Cover image', ['jpg', 'jpeg', 'png', 'gif'], 100000000);
    if ($imageError !== '') {
        send_text(422, $imageError);
    }

    $pdfError = validate_legacy_author_upload_file($pdfFile, 'PDF file', ['pdf'], 100000000);
    if ($pdfError !== '') {
        send_text(422, $pdfError);
    }

    if (getimagesize((string) ($imageFile['tmp_name'] ?? '')) === false) {
        send_text(422, 'Cover image must be a valid image file.');
    }

    $pdfMime = '';
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $pdfMime = (string) finfo_file($finfo, (string) ($pdfFile['tmp_name'] ?? ''));
            finfo_close($finfo);
        }
    }

    if ($pdfMime !== '' && $pdfMime !== 'application/pdf') {
        send_text(422, 'Book upload must be a PDF file.');
    }

    $uploadsDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads';
    if (!is_dir($uploadsDir) && !mkdir($uploadsDir, 0775, true) && !is_dir($uploadsDir)) {
        send_text(500, 'The uploads directory could not be created.');
    }

    $imageFilename = sanitize_uploaded_filename((string) ($imageFile['name'] ?? ''), 'cover');
    $pdfFilename = sanitize_uploaded_filename((string) ($pdfFile['name'] ?? ''), 'book');
    $imageTarget = unique_upload_target_path($uploadsDir, $imageFilename);
    $pdfTarget = unique_upload_target_path($uploadsDir, $pdfFilename);

    if (!move_uploaded_file((string) ($imageFile['tmp_name'] ?? ''), $imageTarget)) {
        send_text(500, 'The cover image could not be saved.');
    }

    if (!move_uploaded_file((string) ($pdfFile['tmp_name'] ?? ''), $pdfTarget)) {
        @unlink($imageTarget);
        send_text(500, 'The PDF file could not be saved.');
    }

    $relativeImagePath = 'uploads/' . basename($imageTarget);
    $relativePdfPath = 'uploads/' . basename($pdfTarget);

    try {
        upload_book($title, (float) $priceRaw, $blurb, $relativeImagePath, $relativePdfPath);
    } catch (Throwable $error) {
        @unlink($imageTarget);
        @unlink($pdfTarget);
        send_text(500, 'The uploaded files were saved, but the book record could not be created.');
    }

    flash_set('library_notice', [
        'type' => 'success',
        'message' => 'Your book was uploaded successfully and is now linked to your account.',
    ]);
    redirect_to('library.php', 303);
}

function get_all_users(string $roleFilter = 'all'): array
{
    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];

    $sql = 'SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                u.created_at, ';

    if ($transactionsTable !== null && $transactionsUserColumn !== null) {
        $sql .= '(SELECT COUNT(*)
                  FROM ' . sql_identifier($transactionsTable) . ' t
                  WHERE t.' . sql_identifier($transactionsUserColumn) . ' = u.id) AS purchase_count, ';
    } else {
        $sql .= '0 AS purchase_count, ';
    }

    if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
        $sql .= '(SELECT COUNT(*)
                  FROM ' . sql_identifier($authorListsTable) . ' al
                  WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = u.id) AS authored_book_count ';
    } else {
        $sql .= '0 AS authored_book_count ';
    }

    $sql .= 'FROM Users u';
    $params = [];

    if (in_array($roleFilter, ['admin', 'author', 'customer'], true)) {
        $sql .= ' WHERE u.role = ?';
        $params[] = $roleFilter;
    }

    $sql .= ' ORDER BY u.created_at DESC, u.id DESC';

    try {
        $statement = db()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $error) {
        log_server_error('admin-get-all-users', $error);
        return [];
    }
}

function get_user_profile(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $booksTable = $schema['books_table'];

    $sql = 'SELECT
                u.id,
                u.name,
                u.email,
                u.role,
                u.created_at, ';

    if ($transactionsTable !== null && $transactionsUserColumn !== null) {
        $sql .= '(SELECT COUNT(*)
                  FROM ' . sql_identifier($transactionsTable) . ' t
                  WHERE t.' . sql_identifier($transactionsUserColumn) . ' = u.id) AS purchase_count, ';
    } else {
        $sql .= '0 AS purchase_count, ';
    }

    if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
        $sql .= '(SELECT COUNT(*)
                  FROM ' . sql_identifier($authorListsTable) . ' al
                  WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = u.id) AS authored_book_count ';
    } else {
        $sql .= '0 AS authored_book_count ';
    }

    $sql .= 'FROM Users u WHERE u.id = ? LIMIT 1';

    try {
        $statement = db()->prepare($sql);
        $statement->execute([$userId]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return null;
        }

        $user['recent_purchases'] = [];
        $user['authored_books'] = [];
        $user['authored_sales_count'] = 0;

        if ($transactionsTable !== null && $transactionsUserColumn !== null && $booksTable !== null) {
            $purchases = db()->prepare(
                'SELECT
                    b.bookID,
                    b.title,
                    MAX(t.date_of_purchase) AS purchased_on
                 FROM ' . sql_identifier($transactionsTable) . ' t
                 INNER JOIN ' . sql_identifier($booksTable) . ' b ON b.bookID = t.bookID
                 WHERE t.' . sql_identifier($transactionsUserColumn) . ' = ?
                 GROUP BY b.bookID, b.title
                 ORDER BY purchased_on DESC, b.bookID DESC
                 LIMIT 5'
            );
            $purchases->execute([$userId]);
            $purchaseRows = $purchases->fetchAll();
            $user['recent_purchases'] = is_array($purchaseRows) ? $purchaseRows : [];
        }

        if ($authorListsTable !== null && $authorListsAuthorColumn !== null && $booksTable !== null) {
            $authoredBooksSql = 'SELECT
                    b.bookID,
                    b.title,
                    b.price,
                    b.vetted,
                    b.created_date, ';

            if ($transactionsTable !== null) {
                $authoredBooksSql .= 'COUNT(t.bookID) AS sales_count ';
            } else {
                $authoredBooksSql .= '0 AS sales_count ';
            }

            $authoredBooksSql .= 'FROM ' . sql_identifier($authorListsTable) . ' al
                 INNER JOIN ' . sql_identifier($booksTable) . ' b ON b.bookID = al.bookID ';

            if ($transactionsTable !== null) {
                $authoredBooksSql .= 'LEFT JOIN ' . sql_identifier($transactionsTable) . ' t ON t.bookID = b.bookID ';
            }

            $authoredBooksSql .= 'WHERE al.' . sql_identifier($authorListsAuthorColumn) . ' = ?
                 GROUP BY b.bookID, b.title, b.price, b.vetted, b.created_date
                 ORDER BY b.created_date DESC, b.bookID DESC
                 LIMIT 8';

            $authoredBooks = db()->prepare($authoredBooksSql);
            $authoredBooks->execute([$userId]);
            $authoredRows = $authoredBooks->fetchAll();
            $user['authored_books'] = is_array($authoredRows) ? $authoredRows : [];

            $salesCount = 0;
            foreach ($user['authored_books'] as $book) {
                $salesCount += (int) ($book['sales_count'] ?? 0);
            }
            $user['authored_sales_count'] = $salesCount;
        }

        return $user;
    } catch (Throwable $error) {
        log_server_error('admin-get-user-profile', $error);
        return null;
    }
}

function get_admin_books(string $statusFilter = 'all'): array
{
    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];

    if ($booksTable === null) {
        return [];
    }

    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $transactionsTable = $schema['transactions_table'];

    $sql = 'SELECT
                b.bookID,
                b.title,
                b.price,
                b.blurb,
                b.image,
                b.pdf_refrence_path,
                b.vetted,
                b.created_date, ';

    if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
        $sql .= 'COALESCE(
                    GROUP_CONCAT(DISTINCT authors.name ORDER BY authors.name SEPARATOR ", "),
                    "Pagemark Author"
                 ) AS author_names, ';
    } else {
        $sql .= '"Pagemark Author" AS author_names, ';
    }

    if ($transactionsTable !== null) {
        $sql .= '(SELECT COUNT(*)
                  FROM ' . sql_identifier($transactionsTable) . ' t
                  WHERE t.bookID = b.bookID) AS sales_count ';
    } else {
        $sql .= '0 AS sales_count ';
    }

    $sql .= 'FROM ' . sql_identifier($booksTable) . ' b ';

    if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
        $sql .= 'LEFT JOIN ' . sql_identifier($authorListsTable) . ' al ON al.bookID = b.bookID
                 LEFT JOIN Users authors ON authors.id = al.' . sql_identifier($authorListsAuthorColumn) . ' ';
    }

    $params = [];
    if ($statusFilter === 'live') {
        $sql .= 'WHERE b.vetted = 1 ';
    } elseif ($statusFilter === 'pending') {
        $sql .= 'WHERE b.vetted = 0 ';
    }

    $sql .= 'GROUP BY b.bookID, b.title, b.price, b.blurb, b.image, b.pdf_refrence_path, b.vetted, b.created_date
             ORDER BY b.created_date DESC, b.bookID DESC';

    try {
        $statement = db()->prepare($sql);
        $statement->execute($params);
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $error) {
        log_server_error('admin-get-books', $error);
        return [];
    }
}

function update_book_from_admin(
    int $bookId,
    string $title,
    string $price,
    string $blurb,
    string $imagePath,
    string $pdfReferencePath,
    bool $isVetted
): string {
    if ($bookId <= 0) {
        return 'invalid_book';
    }

    $title = trim($title);
    $blurb = trim($blurb);
    $imagePath = trim($imagePath);
    $pdfReferencePath = trim($pdfReferencePath);

    if ($title === '' || strlen($title) > 512) {
        return 'invalid_title';
    }

    if ($price === '' || !is_numeric($price)) {
        return 'invalid_price';
    }

    $priceValue = round((float) $price, 2);
    if ($priceValue < 0) {
        return 'invalid_price';
    }

    if (strlen($blurb) > 2048) {
        return 'invalid_blurb';
    }

    if (strlen($imagePath) > 1024) {
        return 'invalid_image_path';
    }

    if (strlen($pdfReferencePath) > 1024) {
        return 'invalid_pdf_reference';
    }

    if ($pdfReferencePath !== '' && strtolower((string) pathinfo($pdfReferencePath, PATHINFO_EXTENSION)) !== 'pdf') {
        return 'invalid_pdf_reference';
    }

    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];

    if ($booksTable === null) {
        return 'catalog_unavailable';
    }

    try {
        $exists = db()->prepare('SELECT 1 FROM ' . sql_identifier($booksTable) . ' WHERE bookID = ? LIMIT 1');
        $exists->execute([$bookId]);
        if ($exists->fetchColumn() === false) {
            return 'invalid_book';
        }

        $statement = db()->prepare(
            'UPDATE ' . sql_identifier($booksTable) . '
             SET title = ?,
                 price = ?,
                 blurb = ?,
                 image = ?,
                 pdf_refrence_path = ?,
                 vetted = ?
             WHERE bookID = ?
             LIMIT 1'
        );
        $statement->execute([
            $title,
            $priceValue,
            $blurb,
            $imagePath,
            $pdfReferencePath,
            $isVetted ? 1 : 0,
            $bookId,
        ]);

        return '';
    } catch (Throwable $error) {
        log_server_error('admin-update-book', $error);
        return 'server_error';
    }
}

function admin_user_roles(): array
{
    return ['customer', 'author', 'admin'];
}

function review_table_name(): ?string
{
    $schema = admin_catalog_schema();
    return $schema['reviews_table'] ?? null;
}

function update_user_role_from_admin(int $targetUserId, string $newRole, int $actingAdminId): string
{
    $newRole = strtolower(trim($newRole));

    if ($targetUserId <= 0) {
        return 'invalid_user';
    }

    if (!in_array($newRole, admin_user_roles(), true)) {
        return 'invalid_role';
    }

    if ($targetUserId === $actingAdminId && $newRole !== 'admin') {
        return 'cannot_change_own_role';
    }

    try {
        $statement = db()->prepare('SELECT id, role FROM Users WHERE id = ? LIMIT 1');
        $statement->execute([$targetUserId]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return 'invalid_user';
        }

        if ((string) ($user['role'] ?? '') === $newRole) {
            return '';
        }

        $update = db()->prepare('UPDATE Users SET role = ? WHERE id = ? LIMIT 1');
        $update->execute([$newRole, $targetUserId]);

        return '';
    } catch (Throwable $error) {
        log_server_error('admin-update-user-role', $error);
        return 'server_error';
    }
}

function delete_user_from_admin(int $targetUserId, int $actingAdminId): string
{
    if ($targetUserId <= 0) {
        return 'invalid_user';
    }

    if ($targetUserId === $actingAdminId) {
        return 'cannot_delete_own_account';
    }

    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $authorListsTable = $schema['author_lists_table'];
    $authorListsAuthorColumn = $schema['author_lists_author_column'];
    $pdo = db();

    try {
        $statement = $pdo->prepare('SELECT id, email FROM Users WHERE id = ? LIMIT 1');
        $statement->execute([$targetUserId]);
        $user = $statement->fetch();

        if (!is_array($user)) {
            return 'invalid_user';
        }

        $pdo->beginTransaction();

        if ($transactionsTable !== null && $transactionsUserColumn !== null) {
            $deleteTransactions = $pdo->prepare(
                'DELETE FROM ' . sql_identifier($transactionsTable) . '
                 WHERE ' . sql_identifier($transactionsUserColumn) . ' = ?'
            );
            $deleteTransactions->execute([$targetUserId]);
        }

        if ($authorListsTable !== null && $authorListsAuthorColumn !== null) {
            $deleteAuthorLinks = $pdo->prepare(
                'DELETE FROM ' . sql_identifier($authorListsTable) . '
                 WHERE ' . sql_identifier($authorListsAuthorColumn) . ' = ?'
            );
            $deleteAuthorLinks->execute([$targetUserId]);
        }

        $deletePasswordResets = $pdo->prepare('DELETE FROM PasswordResets WHERE user_id = ?');
        $deletePasswordResets->execute([$targetUserId]);

        $deleteLoginAttempts = $pdo->prepare('DELETE FROM LoginAttempts WHERE email = ?');
        $deleteLoginAttempts->execute([(string) ($user['email'] ?? '')]);

        $deleteUser = $pdo->prepare('DELETE FROM Users WHERE id = ? LIMIT 1');
        $deleteUser->execute([$targetUserId]);

        $pdo->commit();
        return '';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_server_error('admin-delete-user', $error);
        return 'server_error';
    }
}

function delete_book_from_admin(int $bookId): string
{
    if ($bookId <= 0) {
        return 'invalid_book';
    }

    $schema = admin_catalog_schema();
    $booksTable = $schema['books_table'];
    $transactionsTable = $schema['transactions_table'];
    $authorListsTable = $schema['author_lists_table'];

    if ($booksTable === null) {
        return 'catalog_unavailable';
    }

    $pdo = db();

    try {
        $exists = $pdo->prepare('SELECT 1 FROM ' . sql_identifier($booksTable) . ' WHERE bookID = ? LIMIT 1');
        $exists->execute([$bookId]);
        if ($exists->fetchColumn() === false) {
            return 'invalid_book';
        }

        $pdo->beginTransaction();

        if ($transactionsTable !== null) {
            $deleteTransactions = $pdo->prepare(
                'DELETE FROM ' . sql_identifier($transactionsTable) . ' WHERE bookID = ?'
            );
            $deleteTransactions->execute([$bookId]);
        }

        if ($authorListsTable !== null) {
            $deleteAuthorLinks = $pdo->prepare(
                'DELETE FROM ' . sql_identifier($authorListsTable) . ' WHERE bookID = ?'
            );
            $deleteAuthorLinks->execute([$bookId]);
        }

        $deleteBook = $pdo->prepare(
            'DELETE FROM ' . sql_identifier($booksTable) . ' WHERE bookID = ? LIMIT 1'
        );
        $deleteBook->execute([$bookId]);

        $pdo->commit();
        return '';
    } catch (Throwable $error) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        log_server_error('admin-delete-book', $error);
        return 'server_error';
    }
}

function delete_transaction_from_admin(int $userId, int $bookId, string $purchaseDate): string
{
    if ($userId <= 0 || $bookId <= 0) {
        return 'invalid_transaction';
    }

    $normalizedDate = trim($purchaseDate);
    $timestamp = strtotime($normalizedDate);
    if ($timestamp === false) {
        return 'invalid_transaction';
    }

    $normalizedDate = date('Y-m-d', $timestamp);

    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];

    if ($transactionsTable === null || $transactionsUserColumn === null) {
        return 'invalid_transaction';
    }

    try {
        $exists = db()->prepare(
            'SELECT 1
             FROM ' . sql_identifier($transactionsTable) . '
             WHERE ' . sql_identifier($transactionsUserColumn) . ' = ?
               AND bookID = ?
               AND date_of_purchase = ?
             LIMIT 1'
        );
        $exists->execute([$userId, $bookId, $normalizedDate]);

        if ($exists->fetchColumn() === false) {
            return 'invalid_transaction';
        }

        $delete = db()->prepare(
            'DELETE FROM ' . sql_identifier($transactionsTable) . '
             WHERE ' . sql_identifier($transactionsUserColumn) . ' = ?
               AND bookID = ?
               AND date_of_purchase = ?
             LIMIT 1'
        );
        $delete->execute([$userId, $bookId, $normalizedDate]);

        return '';
    } catch (Throwable $error) {
        log_server_error('admin-delete-transaction', $error);
        return 'server_error';
    }
}

function get_admin_reviews(): array
{
    return get_reviews();
}

function delete_review_from_admin(int $reviewId): string
{
    if ($reviewId <= 0) {
        return 'invalid_review';
    }

    $reviewsTable = review_table_name();
    if ($reviewsTable === null) {
        return 'server_error';
    }

    try {
        $exists = db()->prepare('SELECT 1 FROM ' . sql_identifier($reviewsTable) . ' WHERE reviewID = ? LIMIT 1');
        $exists->execute([$reviewId]);

        if ($exists->fetchColumn() === false) {
            return 'invalid_review';
        }

        $delete = db()->prepare('DELETE FROM ' . sql_identifier($reviewsTable) . ' WHERE reviewID = ? LIMIT 1');
        $delete->execute([$reviewId]);

        return '';
    } catch (Throwable $error) {
        log_server_error('admin-delete-review', $error);
        return 'server_error';
    }
}

function get_admin_transaction_rows(?int $limit = 25): array
{
    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $booksTable = $schema['books_table'];

    if ($transactionsTable === null || $transactionsUserColumn === null || $booksTable === null) {
        return [];
    }

    $sql = 'SELECT
                t.' . sql_identifier($transactionsUserColumn) . ' AS user_id,
                t.bookID,
                t.date_of_purchase,
                COALESCE(u.name, "Unknown User") AS customer_name,
                COALESCE(u.email, "") AS customer_email,
                COALESCE(b.title, "Unknown Book") AS book_title,
                COALESCE(b.price, 0) AS current_price
            FROM ' . sql_identifier($transactionsTable) . ' t
            LEFT JOIN Users u ON u.id = t.' . sql_identifier($transactionsUserColumn) . '
            LEFT JOIN ' . sql_identifier($booksTable) . ' b ON b.bookID = t.bookID
            ORDER BY t.date_of_purchase DESC, t.bookID DESC';

    if (is_int($limit) && $limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    try {
        $statement = db()->query($sql);
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $error) {
        log_server_error('admin-transaction-rows', $error);
        return [];
    }
}

function get_admin_transaction_report(): array
{
    $schema = admin_catalog_schema();
    $transactionsTable = $schema['transactions_table'];
    $transactionsUserColumn = $schema['transactions_user_column'];
    $booksTable = $schema['books_table'];

    $report = [
        'summary' => [
            'total_transactions' => null,
            'unique_customers' => null,
            'unique_books' => null,
            'estimated_revenue' => null,
            'most_recent_purchase' => null,
        ],
        'recent_transactions' => [],
        'top_books' => [],
        'top_customers' => [],
    ];

    if ($transactionsTable === null || $transactionsUserColumn === null || $booksTable === null) {
        return $report;
    }

    $report['summary']['total_transactions'] = safe_table_count($transactionsTable);
    $report['summary']['unique_customers'] = safe_scalar_count(
        'SELECT COUNT(DISTINCT ' . sql_identifier($transactionsUserColumn) . ') FROM ' . sql_identifier($transactionsTable)
    );
    $report['summary']['unique_books'] = safe_scalar_count(
        'SELECT COUNT(DISTINCT bookID) FROM ' . sql_identifier($transactionsTable)
    );
    try {
        $statement = db()->query(
            'SELECT COALESCE(SUM(b.price), 0) AS estimated_revenue
             FROM ' . sql_identifier($transactionsTable) . ' t
             INNER JOIN ' . sql_identifier($booksTable) . ' b ON b.bookID = t.bookID'
        );
        $row = $statement->fetch();
        if (is_array($row)) {
            $report['summary']['estimated_revenue'] = (float) ($row['estimated_revenue'] ?? 0);
        }
    } catch (Throwable $error) {
        log_server_error('admin-transaction-summary-revenue', $error);
    }

    try {
        $statement = db()->query(
            'SELECT MAX(date_of_purchase) AS most_recent_purchase
             FROM ' . sql_identifier($transactionsTable)
        );
        $row = $statement->fetch();
        if (is_array($row)) {
            $report['summary']['most_recent_purchase'] = (string) ($row['most_recent_purchase'] ?? '');
        }
    } catch (Throwable $error) {
        log_server_error('admin-transaction-summary-date', $error);
    }

    $report['recent_transactions'] = get_admin_transaction_rows(12);

    try {
        $topBooks = db()->query(
            'SELECT
                b.bookID,
                COALESCE(b.title, "Unknown Book") AS title,
                COUNT(*) AS sales_count,
                COALESCE(SUM(b.price), 0) AS estimated_revenue
             FROM ' . sql_identifier($transactionsTable) . ' t
             INNER JOIN ' . sql_identifier($booksTable) . ' b ON b.bookID = t.bookID
             GROUP BY b.bookID, b.title
             ORDER BY sales_count DESC, estimated_revenue DESC, b.bookID DESC
             LIMIT 5'
        );
        $topBookRows = $topBooks->fetchAll();
        $report['top_books'] = is_array($topBookRows) ? $topBookRows : [];
    } catch (Throwable $error) {
        log_server_error('admin-transaction-top-books', $error);
    }

    try {
        $topCustomers = db()->query(
            'SELECT
                t.' . sql_identifier($transactionsUserColumn) . ' AS user_id,
                COALESCE(u.name, "Unknown User") AS customer_name,
                COALESCE(u.email, "") AS customer_email,
                COUNT(*) AS purchase_count
             FROM ' . sql_identifier($transactionsTable) . ' t
             LEFT JOIN Users u ON u.id = t.' . sql_identifier($transactionsUserColumn) . '
             GROUP BY t.' . sql_identifier($transactionsUserColumn) . ', u.name, u.email
             ORDER BY purchase_count DESC, user_id DESC
             LIMIT 5'
        );
        $topCustomerRows = $topCustomers->fetchAll();
        $report['top_customers'] = is_array($topCustomerRows) ? $topCustomerRows : [];
    } catch (Throwable $error) {
        log_server_error('admin-transaction-top-customers', $error);
    }

    return $report;
}

function save_review(string $reviewerName, string $bookTitle, int $rating, string $content): bool
{
    $reviewsTable = review_table_name();
    if ($reviewsTable === null) {
        return false;
    }

    try {
        $statement = db()->prepare(
            'INSERT INTO ' . sql_identifier($reviewsTable) . ' (reviewer_name, book_title, content, rating, created_date)
             VALUES (?, ?, ?, ?, CURDATE())'
        );
        $statement->execute([$reviewerName, $bookTitle, $content, $rating]);
        return true;
    } catch (Throwable $error) {
        log_server_error('save-review', $error);
        return false;
    }
}

function get_reviews(): array
{
    $reviewsTable = review_table_name();
    if ($reviewsTable === null) {
        return [];
    }

    try {
        $statement = db()->query(
            'SELECT reviewID AS id, reviewer_name AS name, book_title AS book,
                    content AS text, rating, created_date AS date
             FROM ' . sql_identifier($reviewsTable) . '
             ORDER BY created_date DESC, reviewID DESC'
        );
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $error) {
        log_server_error('get-reviews', $error);
        return [];
    }
}

function require_login(): array
{
    $user = current_user();

    if ($user === null) {
        log_security_event('customer_access_requires_login');
        redirect_with_query('login.php', ['auth_error' => 'login_required']);
    }

    return $user;
}

function fetch_catalog_books(): array
{
    $statement = db()->prepare(
        'SELECT
            Books.bookID,
            Books.title,
            Books.price,
            Books.blurb,
            Books.image,
            Books.pdf_refrence_path,
            Books.created_date,
            COALESCE(GROUP_CONCAT(DISTINCT Authors.name ORDER BY Authors.name SEPARATOR ", "), "Pagemark Author") AS author_names
         FROM Books
         LEFT JOIN AuthorLists ON AuthorLists.bookID = Books.bookID
         LEFT JOIN Users AS Authors ON Authors.id = AuthorLists.author_id
         WHERE Books.vetted = 1
         GROUP BY Books.bookID, Books.title, Books.price, Books.blurb, Books.image, Books.pdf_refrence_path, Books.created_date
         ORDER BY Books.created_date DESC, Books.bookID DESC'
    );
    $statement->execute();

    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function fetch_library_books_for_user(array $user): array
{
    $role = (string) ($user['role'] ?? '');
    $userId = (int) ($user['id'] ?? 0);

    if ($role === 'admin') {
        $statement = db()->prepare(
            'SELECT
                Books.bookID,
                Books.title,
                Books.price,
                Books.blurb,
                Books.image,
                Books.pdf_refrence_path,
                MAX(Transactions.date_of_purchase) AS date_of_purchase,
                COALESCE(GROUP_CONCAT(DISTINCT Authors.name ORDER BY Authors.name SEPARATOR ", "), "Pagemark Author") AS author_names
             FROM Books
             LEFT JOIN Transactions ON Transactions.bookID = Books.bookID
             LEFT JOIN AuthorLists ON AuthorLists.bookID = Books.bookID
             LEFT JOIN Users AS Authors ON Authors.id = AuthorLists.author_id
             WHERE Books.vetted = 1 AND Books.pdf_refrence_path IS NOT NULL AND Books.pdf_refrence_path <> ""
             GROUP BY Books.bookID, Books.title, Books.price, Books.blurb, Books.image, Books.pdf_refrence_path
             ORDER BY date_of_purchase DESC, Books.bookID DESC'
        );
        $statement->execute();
    } else {
        $statement = db()->prepare(
            'SELECT
                Books.bookID,
                Books.title,
                Books.price,
                Books.blurb,
                Books.image,
                Books.pdf_refrence_path,
                MAX(Transactions.date_of_purchase) AS date_of_purchase,
                COALESCE(GROUP_CONCAT(DISTINCT Authors.name ORDER BY Authors.name SEPARATOR ", "), "Pagemark Author") AS author_names
             FROM Transactions
             INNER JOIN Books ON Books.bookID = Transactions.bookID
             LEFT JOIN AuthorLists ON AuthorLists.bookID = Books.bookID
             LEFT JOIN Users AS Authors ON Authors.id = AuthorLists.author_id
             WHERE Transactions.user_id = ? AND Books.pdf_refrence_path IS NOT NULL AND Books.pdf_refrence_path <> ""
             GROUP BY Books.bookID, Books.title, Books.price, Books.blurb, Books.image, Books.pdf_refrence_path
             ORDER BY date_of_purchase DESC, Books.bookID DESC'
        );
        $statement->execute([$userId]);
    }

    $rows = $statement->fetchAll();

    return is_array($rows) ? $rows : [];
}

function find_book_by_id(int $bookId): ?array
{
    if ($bookId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT
            Books.bookID,
            Books.title,
            Books.price,
            Books.blurb,
            Books.image,
            Books.pdf_refrence_path,
            Books.vetted,
            Books.created_date,
            COALESCE(GROUP_CONCAT(DISTINCT Authors.name ORDER BY Authors.name SEPARATOR ", "), "Pagemark Author") AS author_names
         FROM Books
         LEFT JOIN AuthorLists ON AuthorLists.bookID = Books.bookID
         LEFT JOIN Users AS Authors ON Authors.id = AuthorLists.author_id
         WHERE Books.bookID = ?
         GROUP BY Books.bookID, Books.title, Books.price, Books.blurb, Books.image, Books.pdf_refrence_path, Books.vetted, Books.created_date
         LIMIT 1'
    );
    $statement->execute([$bookId]);
    $book = $statement->fetch();

    return is_array($book) ? $book : null;
}

function user_can_access_book(array $user, int $bookId): bool
{
    if ($bookId <= 0) {
        return false;
    }

    $role = (string) ($user['role'] ?? '');
    $userId = (int) ($user['id'] ?? 0);

    if ($role === 'admin') {
        return true;
    }

    if ($role === 'author' && $userId > 0) {
        $statement = db()->prepare('SELECT 1 FROM AuthorLists WHERE bookID = ? AND author_id = ? LIMIT 1');
        $statement->execute([$bookId, $userId]);
        if ($statement->fetchColumn() !== false) {
            return true;
        }
    }

    if ($userId <= 0) {
        return false;
    }

    $statement = db()->prepare('SELECT 1 FROM Transactions WHERE user_id = ? AND bookID = ? LIMIT 1');
    $statement->execute([$userId, $bookId]);

    return $statement->fetchColumn() !== false;
}

function resolve_uploaded_pdf_path(string $referencePath): ?string
{
    $referencePath = trim(str_replace('\\', '/', $referencePath));
    if ($referencePath === '') {
        return null;
    }

    $uploadsRoot = realpath(dirname(__DIR__) . '/uploads');
    if ($uploadsRoot === false) {
        return null;
    }

    $uploadsRoot = str_replace('\\', '/', $uploadsRoot);
    $absolutePath = realpath(dirname(__DIR__) . '/' . ltrim($referencePath, '/'));
    if ($absolutePath === false) {
        return null;
    }

    $absolutePath = str_replace('\\', '/', $absolutePath);
    if (strpos($absolutePath, $uploadsRoot . '/') !== 0 && $absolutePath !== $uploadsRoot) {
        return null;
    }

    if (!is_file($absolutePath)) {
        return null;
    }

    if (strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION)) !== 'pdf') {
        return null;
    }

    return $absolutePath;
}

function record_transactions_for_user(int $userId, array $bookIds): int
{
    if ($userId <= 0) {
        throw new RuntimeException('A persisted customer account is required to record purchases.');
    }

    $normalizedIds = array_values(array_unique(array_map('intval', $bookIds)));
    $normalizedIds = array_values(array_filter($normalizedIds, function (int $bookId): bool {
        return $bookId > 0;
    }));

    if ($normalizedIds === []) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($normalizedIds), '?'));
    $statement = db()->prepare(
        'SELECT bookID
         FROM Books
         WHERE vetted = 1 AND pdf_refrence_path IS NOT NULL AND pdf_refrence_path <> "" AND bookID IN (' . $placeholders . ')'
    );
    $statement->execute($normalizedIds);
    $allowedIds = array_map('intval', array_column($statement->fetchAll(), 'bookID'));

    if ($allowedIds === []) {
        return 0;
    }

    $inserted = 0;
    $insert = db()->prepare('INSERT IGNORE INTO Transactions (user_id, bookID, date_of_purchase) VALUES (?, ?, ?)');
    $purchaseDate = date('Y-m-d');

    foreach ($allowedIds as $bookId) {
        $insert->execute([$userId, $bookId, $purchaseDate]);
        $inserted += $insert->rowCount();
    }

    return $inserted;
}

handle_legacy_author_upload_post_if_needed();
