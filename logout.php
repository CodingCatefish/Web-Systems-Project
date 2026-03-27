<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() !== 'POST') {
    send_text(405, 'Method Not Allowed');
}

if (!is_same_origin_request()) {
    send_text(403, 'Forbidden');
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    send_text(403, 'Forbidden');
}

clear_session();
redirect_to('login.php');
