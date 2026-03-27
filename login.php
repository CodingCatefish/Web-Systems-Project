<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() !== 'POST') {
    redirect_to('login.html');
}

try {
    $email = normalize_email((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if (!is_same_origin_request()) {
        redirect_with_query('login.html', [
            'auth_error' => 'csrf_invalid_origin',
            'email' => $email,
        ]);
    }

    $validationError = validate_login_input($email, $password);
    if ($validationError !== '') {
        redirect_with_query('login.html', [
            'auth_error' => $validationError,
            'email' => $email,
        ]);
    }

    if ($email === admin_email() && admin_password() !== '' && hash_equals(admin_password(), $password)) {
        create_session_user([
            'id' => 0,
            'email' => $email,
            'name' => 'Admin',
            'role' => 'admin',
        ]);
        redirect_to('admin.php');
    }

    $statement = db()->prepare('SELECT id, name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
    $statement->execute([$email]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, (string) $user['password_hash'])) {
        redirect_with_query('login.html', [
            'auth_error' => 'invalid_credentials',
            'email' => $email,
        ]);
    }

    create_session_user([
        'id' => (int) $user['id'],
        'email' => (string) $user['email'],
        'name' => (string) ($user['name'] ?: explode('@', $email)[0]),
        'role' => (string) ($user['role'] ?? 'customer'),
    ]);

    redirect_to(((string) ($user['role'] ?? 'customer')) === 'admin' ? 'admin.php' : 'index.html');
} catch (Throwable $error) {
    log_server_error('login', $error);
    redirect_with_query('login.html', [
        'auth_error' => 'server_error',
        'email' => $email ?? '',
    ]);
}
