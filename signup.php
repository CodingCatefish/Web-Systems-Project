<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() !== 'POST') {
    redirect_to('signup.html');
}

try {
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    if (!is_same_origin_request()) {
        redirect_with_query('signup.html', [
            'auth_error' => 'csrf_invalid_origin',
            'name' => $name,
            'email' => $email,
        ]);
    }

    $validationError = validate_signup_input($name, $email, $password, $confirmPassword);
    if ($validationError !== '') {
        redirect_with_query('signup.html', [
            'auth_error' => $validationError,
            'name' => $name,
            'email' => $email,
        ]);
    }

    $existingUserStatement = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $existingUserStatement->execute([$email]);

    if ($existingUserStatement->fetch()) {
        redirect_with_query('signup.html', [
            'auth_error' => 'duplicate_email',
            'name' => $name,
            'email' => $email,
        ]);
    }

    $insertStatement = db()->prepare(
        'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
    );
    $insertStatement->execute([
        $name,
        $email,
        password_hash($password, PASSWORD_DEFAULT),
        'customer',
    ]);

    redirect_with_query('login.html', [
        'auth_notice' => 'account_created',
        'email' => $email,
    ]);
} catch (Throwable $error) {
    log_server_error('signup', $error);
    redirect_with_query('signup.html', [
        'auth_error' => 'server_error',
        'name' => $name ?? '',
        'email' => $email ?? '',
    ]);
}
