<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() === 'POST') {
    try {
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $redirectParams = ['email' => $email];

        require_valid_form_post('forgot-password.php', $redirectParams);

        $validationError = validate_password_reset_request_input($email);
        if ($validationError !== '') {
            redirect_with_query('forgot-password.php', $redirectParams + ['auth_error' => $validationError]);
        }

        $debugLink = create_password_reset($email);
        if ($debugLink !== null) {
            flash_set('password_reset_debug_link', $debugLink);
        }

        redirect_with_query('forgot-password.php', [
            'auth_notice' => 'password_reset_requested',
            'email' => $email,
        ]);
    } catch (Throwable $error) {
        log_server_error('forgot-password', $error);
        redirect_with_query('forgot-password.php', [
            'auth_error' => 'server_error',
            'email' => $email ?? '',
        ]);
    }
}

$feedback = get_auth_feedback('forgot-password');
$emailValue = auth_feedback_value($feedback, 'email');
$emailError = auth_feedback_field_error($feedback, 'email');
$summaryVisible = $feedback['summary'] !== '';
$debugLink = flash_get('password_reset_debug_link');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Pagemark</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <main id="main-content" class="auth-card">
        <a class="auth-home-link" href="login.php">Back to login</a>
        <div class="auth-logo">
            <h1>Reset your <span>Pagemark</span> password</h1>
        </div>
        <p class="auth-subtitle">Enter your account email and we will prepare a password reset link.</p>
        <p class="auth-notice">
            <?= app_show_reset_debug_link()
                ? 'Reset links are shown on-screen because debug reset links are enabled for this environment.'
                : 'Reset links are not shown on-screen in this environment.' ?>
        </p>

        <div class="form-status" id="forgot-password-status" aria-live="polite"><?= e($feedback['status']) ?></div>
        <form class="auth-form" id="forgot-password-form" action="forgot-password.php" method="post" novalidate>
            <?= csrf_input() ?>
            <div class="form-errors-summary" id="forgot-password-errors" role="alert"<?= $summaryVisible ? ' tabindex="-1"' : ' hidden' ?>><?= e($feedback['summary']) ?></div>
            <div class="field">
                <label for="forgot-password-email">Email address</label>
                <input
                    type="email"
                    id="forgot-password-email"
                    name="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    aria-describedby="forgot-password-email-help forgot-password-email-error"
                   <?= $emailError !== '' ? ' aria-invalid="true"' : '' ?>
                    value="<?= e($emailValue) ?>"
                    required
                >
                <p class="helper" id="forgot-password-email-help">Use the same email address you used to sign up.</p>
                <p class="field-error" id="forgot-password-email-error"<?= $emailError !== '' ? '' : ' hidden' ?>><?= e($emailError) ?></p>
            </div>

            <button class="auth-submit" type="submit">Create reset link</button>
        </form>

        <?php if (is_string($debugLink) && $debugLink !== ''): ?>
            <section class="auth-debug-link" aria-labelledby="reset-link-title">
                <h2 id="reset-link-title">Reset link</h2>
                <p>Open this link to choose a new password:</p>
                <p><a href="<?= e($debugLink) ?>"><?= e($debugLink) ?></a></p>
            </section>
        <?php endif; ?>

        <p class="auth-footer-text">
            Remembered your password?
            <a href="login.php">Back to login</a>
        </p>
    </main>
    <script src="js/main.js"></script>
</body>
</html>
