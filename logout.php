<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() !== 'POST') {
    send_text(405, 'Method Not Allowed');
}

require_same_origin_post();

clear_session();
redirect_to('login.html');
