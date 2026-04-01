<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

if (request_method() !== 'GET') {
    send_text(405, 'Method Not Allowed');
}

no_cache();

$user = require_login();
$bookId = max(0, (int) ($_GET['book'] ?? 0));
$book = find_book_by_id($bookId);

if ($book === null || (int) ($book['vetted'] ?? 0) !== 1) {
    send_text(404, 'Book not found');
}

if (!user_can_access_book($user, $bookId)) {
    send_text(403, 'Forbidden');
}

$pdfPath = resolve_uploaded_pdf_path((string) ($book['pdf_refrence_path'] ?? ''));
if ($pdfPath === null) {
    send_text(404, 'PDF not found');
}

$downloadName = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($book['title'] ?? 'book'));
$downloadName = trim((string) $downloadName, '-');
if ($downloadName === '') {
    $downloadName = 'book';
}

header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($pdfPath));
header('Content-Disposition: inline; filename="' . $downloadName . '.pdf"');
header('Cache-Control: private, no-store');

readfile($pdfPath);
exit;
