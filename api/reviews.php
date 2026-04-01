<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

no_cache();

// GET — return all reviews as JSON
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $reviews = get_reviews();
    json_response(['ok' => true, 'reviews' => $reviews]);
}

// POST — save a new review
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF: check origin then token
    if (!is_same_origin_request()) {
        http_response_code(403);
        json_response(['ok' => false, 'errors' => ['Invalid request origin.']]);
    }

    if (!verify_csrf_token((string) ($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        json_response(['ok' => false, 'errors' => ['Session expired. Please refresh the page and try again.']]);
    }

    $name    = trim((string) ($_POST['name']   ?? ''));
    $book    = trim((string) ($_POST['book']   ?? ''));
    $rating  = (int) ($_POST['rating']         ?? 0);
    $content = trim((string) ($_POST['text']   ?? ''));

    $errors = [];

    if ($name === '') {
        $errors[] = 'Your name is required.';
    } elseif (strlen($name) > 80) {
        $errors[] = 'Name must be 80 characters or fewer.';
    }

    if ($book === '') {
        $errors[] = 'Book title is required.';
    } elseif (strlen($book) > 255) {
        $errors[] = 'Book title must be 255 characters or fewer.';
    }

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Rating must be between 1 and 5 stars.';
    }

    if ($content === '') {
        $errors[] = 'Please enter your review.';
    } elseif (strlen($content) > 600) {
        $errors[] = 'Review must be 600 characters or fewer.';
    }

    if (!empty($errors)) {
        http_response_code(422);
        json_response(['ok' => false, 'errors' => $errors]);
    }

    $saved = save_review($name, $book, $rating, $content);

    if (!$saved) {
        http_response_code(500);
        json_response(['ok' => false, 'errors' => ['Could not save your review. Please try again.']]);
    }

    json_response(['ok' => true]);
}

http_response_code(405);
json_response(['ok' => false, 'errors' => ['Method not allowed.']]);
