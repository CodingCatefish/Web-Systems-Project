<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$method = request_method();
if ($method === 'POST') {
    $token = trim((string) ($_POST['token'] ?? ''));
} else {
    $token = trim((string) ($_GET['token'] ?? ''));
}

if ($method === 'POST') {
    try {
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
        $redirectParams = ['token' => $token];

        require_valid_form_post('reset-password.php', $redirectParams);

        $validationError = validate_password_reset_input($password, $confirmPassword);
        if ($validationError !== '') {
            redirect_with_query('reset-password.php', $redirectParams + ['auth_error' => $validationError]);
        }

        $resetError = reset_password_with_token($token, $password);
        if ($resetError !== '') {
            redirect_with_query('reset-password.php', $redirectParams + ['auth_error' => $resetError]);
        }

        redirect_with_query('login.php', ['auth_notice' => 'password_reset_completed']);
    } catch (Throwable $error) {
        log_server_error('reset-password', $error);
        redirect_with_query('reset-password.php', [
            'token' => $token,
            'auth_error' => 'server_error',
        ]);
    }
}

$feedback = get_auth_feedback('reset-password');
$passwordError = auth_feedback_field_error($feedback, 'password');
$confirmPasswordError = auth_feedback_field_error($feedback, 'confirmPassword');

if ($feedback['summary'] === '') {
    $tokenStatus = password_reset_status($token);
    if ($tokenStatus !== '') {
        $feedback['summary'] = (string) (auth_feedback_catalog('reset-password')['errors'][$tokenStatus]['summary'] ?? 'This reset link is not valid.');
        $feedback['status'] = $feedback['summary'];
    }
}

$tokenValid = $feedback['summary'] === '';
$summaryVisible = $feedback['summary'] !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose New Password | Pagemark</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <main id="main-content" class="auth-card">
        <a class="auth-home-link" href="login.php">Back to login</a>
        <div class="auth-logo">
            <h1>Choose a new <span>password</span></h1>
        </div>
        <p class="auth-subtitle">Set a new password for your account.</p>
        <p class="auth-notice">Reset links expire automatically and can only be used once.</p>

        <div class="form-status" id="reset-password-status" aria-live="polite"><?= e($feedback['status']) ?></div>
        <form class="auth-form" id="reset-password-form" action="reset-password.php" method="post" novalidate>
            <?= csrf_input() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="form-errors-summary" id="reset-password-errors" role="alert"<?= $summaryVisible ? ' tabindex="-1"' : ' hidden' ?>><?= e($feedback['summary']) ?></div>
            <div class="field">
                <label for="reset-password">New password</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="reset-password"
                        name="password"
                        placeholder="Create a new password"
                        minlength="8"
                        autocomplete="new-password"
                        aria-describedby="reset-password-help reset-password-error"
                       <?= $passwordError !== '' ? ' aria-invalid="true"' : '' ?>
                       <?= $tokenValid ? '' : ' disabled' ?>
                        required
                    >
                    <button class="password-toggle" type="button" data-toggle-password="reset-password" aria-label="Show password"<?= $tokenValid ? '' : ' disabled' ?>>Show</button>
                </div>
                <p class="helper" id="reset-password-help">Use a password between 8 and 72 characters.</p>
                <p class="field-error" id="reset-password-error"<?= $passwordError !== '' ? '' : ' hidden' ?>><?= e($passwordError) ?></p>
            </div>

            <div class="field">
                <label for="reset-confirm-password">Confirm new password</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="reset-confirm-password"
                        name="confirmPassword"
                        placeholder="Re-enter your new password"
                        minlength="8"
                        autocomplete="new-password"
                        aria-describedby="reset-confirm-password-error"
                       <?= $confirmPasswordError !== '' ? ' aria-invalid="true"' : '' ?>
                       <?= $tokenValid ? '' : ' disabled' ?>
                        required
                    >
                    <button class="password-toggle" type="button" data-toggle-password="reset-confirm-password" aria-label="Show password"<?= $tokenValid ? '' : ' disabled' ?>>Show</button>
                </div>
                <p class="field-error" id="reset-confirm-password-error"<?= $confirmPasswordError !== '' ? '' : ' hidden' ?>><?= e($confirmPasswordError) ?></p>
            </div>

            <button class="auth-submit" type="submit"<?= $tokenValid ? '' : ' disabled' ?>>Save new password</button>
        </form>

        <p class="auth-footer-text">
            Need a fresh link?
            <a href="forgot-password.php">Request another reset</a>
        </p>
    </main>
    <script src="js/main.js"></script>
</body>
</html>
