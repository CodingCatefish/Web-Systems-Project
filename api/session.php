<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (request_method() !== 'GET') {
    send_text(405, 'Method Not Allowed');
}

no_cache();
$user = current_user();
$payload = [
    'user' => $user,
    'csrf_token' => csrf_token(),
];

send_json(200, $payload, ['Cache-Control' => 'no-store']);
