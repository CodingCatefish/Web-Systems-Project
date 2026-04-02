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

function dashboard_counts(): array
{
    return [
        'books' => safe_table_count('Book'),
        'reviews' => safe_table_count('Reviews'),
        'users' => safe_table_count('users'),
        ];
}

function safe_table_count(string $tableName): ?int
{
    $allowedTables = ['Book', 'Reviews', 'Users'];
    if (!in_array($tableName, $allowedTables, true)) {
        return null;
    }

    try {
        $statement = db()->query('SELECT COUNT(*) AS aggregate_count FROM ' . $tableName);
        $row = $statement->fetch();
        return is_array($row) ? (int) ($row['aggregate_count'] ?? 0) : 0;
    } catch (Throwable $error) {
        log_server_error('dashboard-count-' . strtolower($tableName), $error);
        return null;
    }
}

function count_books_sold_by_author()
{
    $statement = db()->prepare('SELECT COUNT(*) FROM Transactions INNER JOIN Book on(Book.bookID=Transactions.bookID) INNER JOIN AuthorList on(Book.bookID=AuthorList.bookID) INNER JOIN User on(AuthorList.authorID=User.userID) WHERE User.userID= ?');
    $statement->execute([$_SESSION['id']]);
    $count = $statement->fetch()[0];
    return $count;
}

function count_books_by_author(){
    $statement = db()->prepare('SELECT COUNT(*) FROM Book INNER JOIN AuthorList on(Book.bookID=AuthorList.bookID) INNER JOIN User on(AuthorList.authorID=User.userID) WHERE Book.vetted=1 AND User.userID= ?');
    $statement->execute([$_SESSION['id']]);
    $count = $statement->fetch()[0];
    return $count;
}

function upload_book(string $title, float $price, string $blurb, string $image, string $pdf){
    $statement = db()->prepare('INSERT INTO Book values(?,?,?,?,?,0,?)');
    $statement->execute([$title,$price,$blurb,$image,$pdf,date("Y-m-d")]);

    $last_id=$statement()->lastInsetId();

    $statement = db()->prepare('INSERT INTO AuthorList values(?,?)');
    $statement->execute([$last_id,$_SESSION['id']]);

}

function get_all_users(): array
{
    try {
        $statement = db()->query('SELECT id, name, email, role, created_at FROM Users ORDER BY created_at DESC');
        $rows = $statement->fetchAll();
        return is_array($rows) ? $rows : [];
    } catch (Throwable $error) {
        log_server_error('admin-get-all-users', $error);
        return [];
    }
}

function save_review(string $reviewerName, string $bookTitle, int $rating, string $content): bool
{
    try {
        $statement = db()->prepare(
            'INSERT INTO Reviews (reviewer_name, book_title, content, rating, created_date)
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
    try {
        $statement = db()->query(
            'SELECT reviewID AS id, reviewer_name AS name, book_title AS book,
                    content AS text, rating, created_date AS date
             FROM Reviews
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
