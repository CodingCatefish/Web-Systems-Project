<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (request_method() !== 'POST') {
    send_text(405, 'Method Not Allowed');
}

no_cache();

if (!is_same_origin_request()) {
    send_json(403, ['error' => 'csrf_invalid_origin'], ['Cache-Control' => 'no-store']);
}

if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
    send_json(403, ['error' => 'csrf_invalid_token'], ['Cache-Control' => 'no-store']);
}

$user = current_user();
if ($user === null) {
    send_json(401, ['error' => 'login_required'], ['Cache-Control' => 'no-store']);
}

$userId = (int) ($user['id'] ?? 0);
if ($userId <= 0) {
    send_json(403, ['error' => 'persisted_account_required'], ['Cache-Control' => 'no-store']);
}

$rawBookIds = $_POST['book_ids'] ?? [];
if (!is_array($rawBookIds)) {
    $rawBookIds = [$rawBookIds];
}

$requestedBookIds = array_values(array_unique(array_map('intval', $rawBookIds)));
$requestedBookIds = array_values(array_filter($requestedBookIds, static function (int $bookId): bool {
    return $bookId > 0;
}));

if ($requestedBookIds === []) {
    send_json(422, ['error' => 'no_digital_books_selected'], ['Cache-Control' => 'no-store']);
}

try {
    $insertedCount = record_transactions_for_user($userId, $requestedBookIds);
} catch (Throwable $error) {
    log_server_error('checkout-purchase-record', $error);
    send_json(500, ['error' => 'server_error'], ['Cache-Control' => 'no-store']);
}

send_json(200, [
    'ok' => true,
    'requested_count' => count($requestedBookIds),
    'unlocked_count' => $insertedCount,
    'library_url' => 'library.php',
], ['Cache-Control' => 'no-store']);
