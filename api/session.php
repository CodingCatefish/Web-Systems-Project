<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (request_method() !== 'GET') {
    send_text(405, 'Method Not Allowed');
}

no_cache();
send_json(200, current_user(), ['Cache-Control' => 'no-store']);
