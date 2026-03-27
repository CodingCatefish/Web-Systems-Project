<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() === 'POST') {
    try {
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $redirectParams = ['email' => $email];

        require_valid_form_post('login.php', $redirectParams);

        $validationError = validate_login_input($email, $password);
        if ($validationError !== '') {
            redirect_with_query('login.php', $redirectParams + ['auth_error' => $validationError]);
        }

        $ipAddress = client_ip();
        $rateLimitState = get_login_rate_limit_state($email, $ipAddress);
        if ($rateLimitState['limited']) {
            log_security_event('login_rate_limited', ['email' => $email, 'retry_after' => $rateLimitState['retry_after']]);
            redirect_with_query('login.php', $redirectParams + [
                'auth_error' => 'login_rate_limited',
                'retry_after' => $rateLimitState['retry_after'],
            ]);
        }

        if ($email === admin_email() && admin_password() !== '' && hash_equals(admin_password(), $password)) {
            clear_login_rate_limit_state($email, $ipAddress);
            create_session_user([
                'id' => 0,
                'email' => $email,
                'name' => 'Admin',
                'role' => 'admin',
            ]);
            log_security_event('login_success', ['email' => $email, 'role' => 'admin']);
            redirect_to('admin.php');
        }

        $user = find_user_by_email($email);
        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            $rateLimitState = record_failed_login_attempt($email, $ipAddress);
            log_security_event('login_failed', ['email' => $email, 'attempt_count' => $rateLimitState['attempt_count']]);

            if ($rateLimitState['limited']) {
                redirect_with_query('login.php', $redirectParams + [
                    'auth_error' => 'login_rate_limited',
                    'retry_after' => $rateLimitState['retry_after'],
                ]);
            }

            redirect_with_query('login.php', $redirectParams + ['auth_error' => 'invalid_credentials']);
        }

        clear_login_rate_limit_state($email, $ipAddress);
        create_session_user([
            'id' => (int) $user['id'],
            'email' => (string) $user['email'],
            'name' => (string) ($user['name'] ?: explode('@', $email)[0]),
            'role' => (string) ($user['role'] ?? 'customer'),
        ]);
        log_security_event('login_success', ['email' => (string) $user['email'], 'role' => (string) ($user['role'] ?? 'customer')]);

        redirect_to(((string) ($user['role'] ?? 'customer')) === 'admin' ? 'admin.php' : 'index.html');
    } catch (Throwable $error) {
        log_server_error('login', $error);
        redirect_with_query('login.php', [
            'auth_error' => 'server_error',
            'email' => $email ?? '',
        ]);
    }
}

$user = current_user();
if ($user !== null) {
    redirect_to(((string) ($user['role'] ?? 'customer')) === 'admin' ? 'admin.php' : 'index.html');
}

$feedback = get_auth_feedback('login');
$emailValue = auth_feedback_value($feedback, 'email');
$emailError = auth_feedback_field_error($feedback, 'email');
$passwordError = auth_feedback_field_error($feedback, 'password');
$summaryVisible = $feedback['summary'] !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Pagemark</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <main id="main-content" class="auth-card">
        <a class="auth-home-link" href="index.html">Back to home</a>
        <div class="auth-logo">
            <h1>Pagemark <span>Login</span></h1>
        </div>
        <p class="auth-subtitle">Sign in to access your account and manage your books.</p>
        <p class="auth-notice">Sign-in is submitted to the server and protected with a session cookie. Use an account created from the sign-up page.</p>

        <div class="form-status" id="login-status" aria-live="polite"><?= e($feedback['status']) ?></div>
        <form class="auth-form" id="login-form" action="login.php" method="post" novalidate>
            <?= csrf_input() ?>
            <div class="form-errors-summary" id="login-errors" role="alert"<?= $summaryVisible ? ' tabindex="-1"' : ' hidden' ?>><?= e($feedback['summary']) ?></div>
            <div class="field">
                <label for="login-email">Email address</label>
                <input
                    type="email"
                    id="login-email"
                    name="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    aria-describedby="login-email-help login-email-error"
                   <?= $emailError !== '' ? ' aria-invalid="true"' : '' ?>
                    value="<?= e($emailValue) ?>"
                    required
                >
                <p class="helper" id="login-email-help">Use the same email you registered with on this device.</p>
                <p class="field-error" id="login-email-error"<?= $emailError !== '' ? '' : ' hidden' ?>><?= e($emailError) ?></p>
            </div>

            <div class="field">
                <label for="login-password">Password</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="login-password"
                        name="password"
                        placeholder="Enter your password"
                        autocomplete="current-password"
                        aria-describedby="login-password-help login-password-error"
                       <?= $passwordError !== '' ? ' aria-invalid="true"' : '' ?>
                        required
                    >
                    <button class="password-toggle" type="button" data-toggle-password="login-password" aria-label="Show password">Show</button>
                </div>
                <p class="helper" id="login-password-help">Passwords must be at least 8 characters.</p>
                <p class="field-error" id="login-password-error"<?= $passwordError !== '' ? '' : ' hidden' ?>><?= e($passwordError) ?></p>
            </div>

            <div class="actions auth-actions-single">
                <span class="forgot">
                    <a href="forgot-password.php">Forgot your password?</a>
                </span>
                <span class="forgot">
                    <a href="signup.php">Need an account first?</a>
                </span>
            </div>

            <button class="auth-submit" type="submit">Sign in</button>
        </form>

        <p class="auth-footer-text">
            Don't have an account?
            <a href="signup.php">Sign up</a>
        </p>
    </main>
    <script src="js/main.js"></script>
</body>
</html>
