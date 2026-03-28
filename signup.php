<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() === 'POST') {
    try {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = normalize_email((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');
        $redirectParams = [
            'name' => $name,
            'email' => $email,
        ];

        require_valid_form_post('signup.php', $redirectParams);

        $validationError = validate_signup_input($name, $email, $password, $confirmPassword);
        if ($validationError !== '') {
            redirect_with_query('signup.php', $redirectParams + ['auth_error' => $validationError]);
        }

        $existingUser = find_user_by_email($email);
        if ($existingUser !== null) {
            redirect_with_query('signup.php', $redirectParams + ['auth_error' => 'duplicate_email']);
        }

        try {
            $insertStatement = db()->prepare(
                'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
            );
            $insertStatement->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                'customer',
            ]);
        } catch (Throwable $error) {
            if (is_unique_constraint_violation($error)) {
                redirect_with_query('signup.php', $redirectParams + ['auth_error' => 'duplicate_email']);
            }

            throw $error;
        }

        log_security_event('signup_success', ['email' => $email]);
        redirect_with_query('login.php', [
            'auth_notice' => 'account_created',
            'email' => $email,
        ]);
    } catch (Throwable $error) {
        log_server_error('signup', $error);
        redirect_with_query('signup.php', [
            'auth_error' => 'server_error',
            'name' => $name ?? '',
            'email' => $email ?? '',
        ]);
    }
}

$user = current_user();
if ($user !== null) {
    redirect_to(((string) ($user['role'] ?? 'customer')) === 'admin' ? 'admin.php' : 'index.html');
}

$feedback = get_auth_feedback('signup');
$nameValue = auth_feedback_value($feedback, 'name');
$emailValue = auth_feedback_value($feedback, 'email');
$nameError = auth_feedback_field_error($feedback, 'name');
$emailError = auth_feedback_field_error($feedback, 'email');
$passwordError = auth_feedback_field_error($feedback, 'password');
$confirmPasswordError = auth_feedback_field_error($feedback, 'confirmPassword');
$summaryVisible = $feedback['summary'] !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Pagemark</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body class="auth-page">
    <a href="#main-content" class="skip-link">Skip to main content</a>
    <main id="main-content" class="auth-card">
        <a class="auth-home-link" href="index.html">Back to home</a>
        <div class="auth-logo">
            <h1>Create your <span>Pagemark</span> account</h1>
        </div>
        <p class="auth-subtitle">Sign up to start discovering and saving your favourite books.</p>
        <p class="auth-notice">This form submits to the server. Passwords are hashed on the backend before storage.</p>

        <div class="form-status" id="signup-status" aria-live="polite"><?= e($feedback['status']) ?></div>
        <form class="auth-form" id="signup-form" action="signup.php" method="post" novalidate>
            <?= csrf_input() ?>
            <div class="form-errors-summary" id="signup-errors" role="alert"<?= $summaryVisible ? ' tabindex="-1"' : ' hidden' ?>><?= e($feedback['summary']) ?></div>
            <div class="field">
                <label for="name">Full name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Jane Doe"
                    autocomplete="name"
                    aria-describedby="signup-name-error"
                   <?= $nameError !== '' ? ' aria-invalid="true"' : '' ?>
                    value="<?= e($nameValue) ?>"
                    required
                >
                <p class="field-error" id="signup-name-error"<?= $nameError !== '' ? '' : ' hidden' ?>><?= e($nameError) ?></p>
            </div>

            <div class="field">
                <label for="signup-email">Email address</label>
                <input
                    type="email"
                    id="signup-email"
                    name="email"
                    placeholder="you@example.com"
                    autocomplete="email"
                    aria-describedby="signup-email-error"
                   <?= $emailError !== '' ? ' aria-invalid="true"' : '' ?>
                    value="<?= e($emailValue) ?>"
                    required
                >
                <p class="field-error" id="signup-email-error"<?= $emailError !== '' ? '' : ' hidden' ?>><?= e($emailError) ?></p>
            </div>

            <div class="field">
                <label for="signup-password">Password</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="signup-password"
                        name="password"
                        placeholder="Create a password"
                        minlength="8"
                        autocomplete="new-password"
                        aria-describedby="signup-password-help signup-password-error"
                       <?= $passwordError !== '' ? ' aria-invalid="true"' : '' ?>
                        required
                    >
                    <button class="password-toggle" type="button" data-toggle-password="signup-password" aria-label="Show password">Show</button>
                </div>
                <p class="helper" id="signup-password-help">Use at least 8 characters for a stronger password.</p>
                <p class="field-error" id="signup-password-error"<?= $passwordError !== '' ? '' : ' hidden' ?>><?= e($passwordError) ?></p>
            </div>

            <div class="field">
                <label for="signup-confirm-password">Confirm password</label>
                <div class="password-field">
                    <input
                        type="password"
                        id="signup-confirm-password"
                        name="confirmPassword"
                        placeholder="Re-enter your password"
                        minlength="8"
                        autocomplete="new-password"
                        aria-describedby="signup-confirm-password-error"
                       <?= $confirmPasswordError !== '' ? ' aria-invalid="true"' : '' ?>
                        required
                    >
                    <button class="password-toggle" type="button" data-toggle-password="signup-confirm-password" aria-label="Show password">Show</button>
                </div>
                <p class="field-error" id="signup-confirm-password-error"<?= $confirmPasswordError !== '' ? '' : ' hidden' ?>><?= e($confirmPasswordError) ?></p>
            </div>

            <button class="auth-submit" type="submit">Sign up</button>
        </form>

        <p class="auth-footer-text">
            Already have an account?
            <a href="login.php">Back to login</a>
        </p>
    </main>
    <script src="js/main.js"></script>
</body>
</html>
