<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (request_method() !== 'GET') {
    send_text(405, 'Method Not Allowed');
}

no_cache();

$books = array_values(array_filter(array_map(static function (array $book): array {
    return [
        'id' => (int) ($book['bookID'] ?? 0),
        'title' => (string) ($book['title'] ?? 'Book'),
        'author' => (string) ($book['author_names'] ?? 'Pagemark Author'),
        'price' => '$' . number_format((float) ($book['price'] ?? 0), 2),
        'blurb' => (string) ($book['blurb'] ?? ''),
        'image' => (string) ($book['image'] ?? ''),
        'pdf_reference_path' => (string) ($book['pdf_refrence_path'] ?? ''),
        'has_reader' => trim((string) ($book['pdf_refrence_path'] ?? '')) !== '',
    ];
}, fetch_catalog_books()), static function (array $book): bool {
    return $book['has_reader'] === true;
}));

send_json(200, ['books' => $books], ['Cache-Control' => 'no-store']);
