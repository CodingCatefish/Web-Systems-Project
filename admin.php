<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$adminUser = require_admin();

function format_dashboard_count(?int $count): string
{
    return $count === null ? 'N/A' : number_format($count);
}

function format_admin_money($amount): string
{
    return '$' . number_format((float) $amount, 2);
}

function format_admin_date(?string $value): string
{
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? 'Unknown' : date('M j, Y', $timestamp);
}

function admin_role_filter(string $value): string
{
    return in_array($value, ['all', 'admin', 'author', 'customer'], true) ? $value : 'all';
}

function admin_catalog_filter(string $value): string
{
    return in_array($value, ['all', 'live', 'pending'], true) ? $value : 'all';
}

function admin_dashboard_url(array $params = [], string $fragment = ''): string
{
    $filtered = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $filtered[$key] = (string) $value;
    }

    $url = 'admin.php';
    if ($filtered !== []) {
        $url .= '?' . http_build_query($filtered);
    }
    if ($fragment !== '') {
        $url .= '#' . ltrim($fragment, '#');
    }

    return $url;
}

function admin_notice_message(string $code): string
{
    $messages = [
        'book_saved' => 'Book changes saved.',
        'role_saved' => 'User role updated.',
        'review_deleted' => 'Review removed.',
        'invalid_action' => 'That admin action is not supported.',
        'invalid_book' => 'That book could not be found.',
        'invalid_user' => 'That user could not be found.',
        'invalid_role' => 'That role is not supported.',
        'cannot_change_own_role' => 'Your own admin role cannot be removed from this dashboard.',
        'invalid_review' => 'That review could not be found.',
        'invalid_title' => 'Book titles are required and must stay within the field limit.',
        'invalid_price' => 'Prices must be numeric and zero or higher.',
        'invalid_blurb' => 'Blurbs must stay within the supported length.',
        'invalid_image_path' => 'Cover image paths must stay within the supported length.',
        'invalid_pdf_reference' => 'PDF reference paths must end in .pdf when provided.',
        'catalog_unavailable' => 'The catalog tables could not be resolved in the current database schema.',
        'server_error' => 'The change could not be saved right now.',
    ];

    return $messages[$code] ?? 'The admin action could not be completed.';
}

function admin_download_transactions_csv(): never
{
    $rows = get_admin_transaction_rows(null);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pagemark-transactions.csv"');
    header('Cache-Control: no-store');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        send_text(500, 'Could not open export stream.');
    }

    fputcsv($output, ['purchase_date', 'user_id', 'customer_name', 'customer_email', 'book_id', 'book_title', 'current_price']);

    foreach ($rows as $row) {
        fputcsv($output, [
            (string) ($row['date_of_purchase'] ?? ''),
            (string) ($row['user_id'] ?? ''),
            (string) ($row['customer_name'] ?? ''),
            (string) ($row['customer_email'] ?? ''),
            (string) ($row['bookID'] ?? ''),
            (string) ($row['book_title'] ?? ''),
            number_format((float) ($row['current_price'] ?? 0), 2, '.', ''),
        ]);
    }

    fclose($output);
    exit;
}

if (request_method() === 'GET' && (string) ($_GET['download'] ?? '') === 'transactions_csv') {
    admin_download_transactions_csv();
}

if (request_method() === 'POST') {
    require_valid_form_post('admin.php');

    $roleFilter = admin_role_filter((string) ($_POST['user_role_filter'] ?? 'all'));
    $catalogFilter = admin_catalog_filter((string) ($_POST['catalog_filter'] ?? 'all'));
    $profileUserId = max(0, (int) ($_POST['profile_user_id'] ?? 0));
    $selectedBookId = max(0, (int) ($_POST['selected_book_id'] ?? 0));
    $selectedReviewId = max(0, (int) ($_POST['selected_review_id'] ?? 0));
    $returnSection = (string) ($_POST['return_section'] ?? 'catalog');
    $action = trim((string) ($_POST['admin_action'] ?? ''));

    $redirectParams = ['user_role' => $roleFilter, 'catalog' => $catalogFilter];
    if ($profileUserId > 0) {
        $redirectParams['user'] = $profileUserId;
    }
    if ($selectedBookId > 0) {
        $redirectParams['book'] = $selectedBookId;
    }

    if ($action === 'update_book') {
        $result = update_book_from_admin(
            $selectedBookId,
            (string) ($_POST['title'] ?? ''),
            (string) ($_POST['price'] ?? ''),
            (string) ($_POST['blurb'] ?? ''),
            (string) ($_POST['image'] ?? ''),
            (string) ($_POST['pdf_refrence_path'] ?? ''),
            isset($_POST['is_vetted'])
        );

        flash_set('admin_notice', [
            'type' => $result === '' ? 'success' : 'error',
            'message' => admin_notice_message($result === '' ? 'book_saved' : $result),
        ]);
    } elseif ($action === 'update_user_role') {
        $result = update_user_role_from_admin(
            $profileUserId,
            (string) ($_POST['role'] ?? ''),
            (int) ($adminUser['id'] ?? 0)
        );

        flash_set('admin_notice', [
            'type' => $result === '' ? 'success' : 'error',
            'message' => admin_notice_message($result === '' ? 'role_saved' : $result),
        ]);
    } elseif ($action === 'delete_review') {
        $result = delete_review_from_admin($selectedReviewId);

        flash_set('admin_notice', [
            'type' => $result === '' ? 'success' : 'error',
            'message' => admin_notice_message($result === '' ? 'review_deleted' : $result),
        ]);
    } else {
        flash_set('admin_notice', ['type' => 'error', 'message' => admin_notice_message('invalid_action')]);
    }

    $allowedSections = ['users', 'catalog', 'reviews', 'reports'];
    $targetSection = in_array($returnSection, $allowedSections, true) ? $returnSection : 'catalog';

    redirect_to(admin_dashboard_url($redirectParams, $targetSection), 303);
}

$counts = dashboard_counts();
$currentRoleFilter = admin_role_filter((string) ($_GET['user_role'] ?? 'all'));
$currentCatalogFilter = admin_catalog_filter((string) ($_GET['catalog'] ?? 'all'));
$selectedUserId = max(0, (int) ($_GET['user'] ?? 0));
$selectedBookId = max(0, (int) ($_GET['book'] ?? 0));
$adminNotice = flash_get('admin_notice');
$users = get_all_users($currentRoleFilter);
$books = get_admin_books($currentCatalogFilter);
$reviews = get_admin_reviews();
$transactionReport = get_admin_transaction_report();

if ($selectedUserId <= 0 && $users !== []) {
    $selectedUserId = (int) ($users[0]['id'] ?? 0);
}

$selectedUser = $selectedUserId > 0 ? get_user_profile($selectedUserId) : null;
if ($selectedUser === null && $users !== []) {
    $selectedUserId = (int) ($users[0]['id'] ?? 0);
    $selectedUser = $selectedUserId > 0 ? get_user_profile($selectedUserId) : null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Pagemark admin dashboard">
  <title>Pagemark Admin</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .admin-main { padding: 2rem 0 3rem; }
    .admin-head, .admin-actions, .admin-stats, .admin-columns, .admin-suggest { display: grid; gap: 1rem; }
    .admin-head { grid-template-columns: 2fr 1fr; align-items: start; }
    .admin-stats { grid-template-columns: repeat(4, minmax(0, 1fr)); margin-top: 1rem; }
    .admin-actions, .admin-suggest { grid-template-columns: repeat(3, minmax(0, 1fr)); margin-top: 1rem; }
    .admin-columns { grid-template-columns: 1.2fr 0.8fr; }
    .admin-card, .admin-section, .admin-link { background: #fff; border: 1px solid rgba(26,32,44,.08); border-radius: 1rem; box-shadow: var(--shadow-sm); }
    .admin-card, .admin-section, .admin-link { padding: 1rem; }
    .admin-link { display: block; text-decoration: none; color: inherit; }
    .admin-head h1, .admin-section h2 { margin: 0; color: var(--color-primary); }
    .admin-card h3 { margin: 0 0 .4rem; color: var(--color-primary); font-size: .95rem; text-transform: uppercase; letter-spacing: .08em; }
    .admin-stat { margin: 0; color: var(--color-accent); font-size: 1.9rem; font-weight: 800; }
    .admin-note, .admin-link p, .admin-head p, .admin-list span { color: var(--color-muted); line-height: 1.55; }
    .admin-notice { margin-top: 1rem; padding: .9rem 1rem; border-radius: .9rem; font-weight: 700; }
    .admin-notice-success { background: #ddf5e5; color: #1a6b3d; }
    .admin-notice-error { background: #fbe6e8; color: #8b2430; }
    .admin-filter-row { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .9rem; }
    .admin-chip { display: inline-flex; padding: .35rem .75rem; border-radius: 999px; border: 1px solid rgba(26,32,44,.12); text-decoration: none; color: var(--color-primary); font-weight: 700; }
    .admin-chip.is-active { background: var(--color-accent); border-color: var(--color-accent); color: #fff; }
    .admin-table-wrap { overflow-x: auto; }
    .admin-table { width: 100%; border-collapse: collapse; }
    .admin-table th, .admin-table td { padding: .75rem .55rem; border-bottom: 1px solid rgba(26,32,44,.08); text-align: left; vertical-align: top; }
    .admin-table th { color: var(--color-muted); text-transform: uppercase; letter-spacing: .08em; font-size: .78rem; }
    .admin-pill { display: inline-flex; padding: .2rem .6rem; border-radius: 999px; background: rgba(26,32,44,.08); font-size: .82rem; font-weight: 800; text-transform: uppercase; }
    .admin-pill.live { background: #ddf5e5; color: #1a6b3d; }
    .admin-pill.pending { background: #ffefcd; color: #8b5a00; }
    .admin-pill.author { background: rgba(210,169,107,.22); color: #714b10; }
    .admin-pill.admin { background: rgba(186,215,255,.32); color: #1d4b8f; }
    .admin-pill.customer { background: rgba(209,232,224,.5); color: #245647; }
    .admin-list { list-style: none; padding: 0; margin: .9rem 0 0; display: grid; gap: .6rem; }
    .admin-list li { padding: .75rem .8rem; border: 1px solid rgba(26,32,44,.08); border-radius: .85rem; }
    .admin-profile-meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .7rem; margin-top: .9rem; }
    .admin-profile-meta div { padding: .75rem; border: 1px solid rgba(26,32,44,.08); border-radius: .85rem; }
    .admin-profile-meta strong { display: block; margin-bottom: .25rem; color: var(--color-muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; }
    .admin-books { display: grid; gap: .85rem; margin-top: 1rem; }
    .admin-book { border: 1px solid rgba(26,32,44,.08); border-radius: .95rem; background: #fff; }
    .admin-book summary { cursor: pointer; padding: .9rem 1rem; display: flex; justify-content: space-between; gap: .75rem; }
    .admin-book-form { padding: 0 1rem 1rem; display: grid; gap: .85rem; }
    .admin-fields { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .8rem; }
    .admin-field { display: grid; gap: .35rem; }
    .admin-field label { font-weight: 700; color: var(--color-primary); }
    .admin-field input, .admin-field textarea, .admin-field select { width: 100%; padding: .75rem .8rem; border: 1px solid rgba(26,32,44,.14); border-radius: .8rem; font: inherit; background: #fff; }
    .admin-field textarea { min-height: 7rem; resize: vertical; }
    .admin-form-row { display: flex; justify-content: space-between; align-items: center; gap: .75rem; flex-wrap: wrap; }
    .admin-button { border: none; border-radius: 999px; padding: .75rem 1rem; background: var(--color-primary); color: #fff; font-weight: 800; cursor: pointer; }
    @media (max-width: 1000px) { .admin-stats, .admin-actions, .admin-suggest { grid-template-columns: repeat(2, minmax(0, 1fr)); } .admin-head, .admin-columns { grid-template-columns: 1fr; } }
    @media (max-width: 720px) { .admin-stats, .admin-actions, .admin-suggest, .admin-fields, .admin-profile-meta { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="site-shell">
    <header class="site-header">
      <div class="container header-row">
        <a class="brand" href="index.html"><span class="brand-mark">B</span> Pagemark</a>
        <nav class="nav-links" aria-label="Admin navigation">
          <a class="nav-link" href="index.html">Public Site</a>
          <a class="nav-link" href="books.html">Storefront</a>
          <form action="logout.php" method="POST">
            <?= csrf_input() ?>
            <button class="admin-button" type="submit">Logout</button>
          </form>
        </nav>
      </div>
    </header>

    <main id="main-content" class="admin-main">
      <div class="container">
        <section class="admin-head reveal">
          <div class="admin-section">
            <h1>Admin Dashboard</h1>
            <p>Operate the user directory and storefront catalog from one place. Signed in as <?= e((string) $adminUser['email']) ?>.</p>
          </div>
          <div class="admin-section">
            <h2>Current scope</h2>
            <p>User profiles, author activity, catalog visibility, pricing, and editable metadata now route through this page.</p>
          </div>
        </section>

        <div class="admin-stats">
          <article class="admin-card reveal"><h3>Total Books</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['books'] ?? null)) ?></p><p class="admin-note">All catalog rows.</p></article>
          <article class="admin-card reveal"><h3>Live Catalog</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['live_books'] ?? null)) ?></p><p class="admin-note">Currently visible titles.</p></article>
          <article class="admin-card reveal"><h3>Pending Vetting</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['pending_books'] ?? null)) ?></p><p class="admin-note">Hidden until approved.</p></article>
          <article class="admin-card reveal"><h3>Users</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['users'] ?? null)) ?></p><p class="admin-note">All registered accounts.</p></article>
          <article class="admin-card reveal"><h3>Authors</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['authors'] ?? null)) ?></p><p class="admin-note">Accounts with author access.</p></article>
          <article class="admin-card reveal"><h3>Recorded Sales</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['transactions'] ?? null)) ?></p><p class="admin-note">Transaction rows from checkout.</p></article>
          <article class="admin-card reveal"><h3>Reviews</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['reviews'] ?? null)) ?></p><p class="admin-note">Stored review entries.</p></article>
          <article class="admin-card reveal"><h3>Author Links</h3><p class="admin-stat"><?= e(format_dashboard_count($counts['author_links'] ?? null)) ?></p><p class="admin-note">Book to author mappings.</p></article>
        </div>

        <div class="admin-actions">
          <a class="admin-link reveal" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter], 'users')) ?>"><h2>User Directory</h2><p>Browse users and open profile details.</p></a>
          <a class="admin-link reveal" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter], 'catalog')) ?>"><h2>Catalog Controls</h2><p>Edit title, price, blurb, cover path, PDF reference, and visibility.</p></a>
          <a class="admin-link reveal" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter], 'reviews')) ?>"><h2>Review Moderation</h2><p>Inspect stored reviews and remove bad content.</p></a>
          <a class="admin-link reveal" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter], 'reports')) ?>"><h2>Sales Reports</h2><p>Check recent purchases, top books, and export transactions.</p></a>
          <a class="admin-link reveal" href="author-dashboard.php"><h2>Author Overview</h2><p>Cross-check the author-facing dashboard.</p></a>
        </div>

        <?php if (is_array($adminNotice) && isset($adminNotice['message'], $adminNotice['type'])): ?>
          <div class="admin-notice admin-notice-<?= e((string) $adminNotice['type']) ?>"><?= e((string) $adminNotice['message']) ?></div>
        <?php endif; ?>

        <section id="users" class="admin-section reveal" style="margin-top:1rem;">
          <h2>Users and Author Profiles</h2>
          <p class="admin-note">Filter by role, then open a profile to inspect signup details, purchases, and authored books.</p>
          <div class="admin-filter-row">
            <?php foreach (['all' => 'All Users', 'author' => 'Authors', 'customer' => 'Customers', 'admin' => 'Admins'] as $filterValue => $filterLabel): ?>
              <a class="admin-chip<?= $currentRoleFilter === $filterValue ? ' is-active' : '' ?>" href="<?= e(admin_dashboard_url(['user_role' => $filterValue, 'catalog' => $currentCatalogFilter, 'user' => $selectedUserId > 0 ? (string) $selectedUserId : null, 'book' => $selectedBookId > 0 ? (string) $selectedBookId : null], 'users')) ?>"><?= e($filterLabel) ?></a>
            <?php endforeach; ?>
          </div>

          <div class="admin-columns" style="margin-top:1rem;">
            <div>
              <?php if ($users === []): ?>
                <p class="admin-note">No users matched this filter.</p>
              <?php else: ?>
                <div class="admin-table-wrap">
                  <table class="admin-table">
                    <thead>
                      <tr><th>Account</th><th>Role</th><th>Purchases</th><th>Authored</th><th>Joined</th><th>Profile</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ($users as $userRow): ?>
                        <?php $rowUserId = (int) ($userRow['id'] ?? 0); $rowRole = (string) ($userRow['role'] ?? 'customer'); ?>
                        <tr>
                          <td><strong><?= e((string) ($userRow['name'] ?? 'Unknown User')) ?></strong><br><span><?= e((string) ($userRow['email'] ?? '')) ?></span></td>
                          <td><span class="admin-pill <?= e($rowRole) ?>"><?= e(ucfirst($rowRole)) ?></span></td>
                          <td><?= e((string) ((int) ($userRow['purchase_count'] ?? 0))) ?></td>
                          <td><?= e((string) ((int) ($userRow['authored_book_count'] ?? 0))) ?></td>
                          <td><?= e(format_admin_date((string) ($userRow['created_at'] ?? ''))) ?></td>
                          <td><a href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter, 'user' => (string) $rowUserId, 'book' => $selectedBookId > 0 ? (string) $selectedBookId : null], 'users')) ?>">View profile</a></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php endif; ?>
            </div>

            <aside class="admin-card">
              <?php if (!is_array($selectedUser)): ?>
                <h2>No profile selected</h2>
                <p class="admin-note">Select a user from the table to view profile details.</p>
              <?php else: ?>
                <?php $selectedRole = (string) ($selectedUser['role'] ?? 'customer'); ?>
                <span class="admin-pill <?= e($selectedRole) ?>"><?= e(ucfirst($selectedRole)) ?></span>
                <h2 style="margin-top:.6rem;"><?= e((string) ($selectedUser['name'] ?? 'Unknown User')) ?></h2>
                <p class="admin-note"><?= e((string) ($selectedUser['email'] ?? '')) ?></p>

                <div class="admin-profile-meta">
                  <div><strong>User ID</strong><?= e((string) ((int) ($selectedUser['id'] ?? 0))) ?></div>
                  <div><strong>Joined</strong><?= e(format_admin_date((string) ($selectedUser['created_at'] ?? ''))) ?></div>
                  <div><strong>Purchases</strong><?= e((string) ((int) ($selectedUser['purchase_count'] ?? 0))) ?></div>
                  <div><strong>Authored Books</strong><?= e((string) ((int) ($selectedUser['authored_book_count'] ?? 0))) ?></div>
                  <div><strong>Author Sales</strong><?= e((string) ((int) ($selectedUser['authored_sales_count'] ?? 0))) ?></div>
                  <div><strong>Role</strong><?= e(ucfirst($selectedRole)) ?></div>
                </div>

                <h3 style="margin-top:1rem;">Role Management</h3>
                <form action="admin.php#users" method="POST">
                  <?= csrf_input() ?>
                  <input type="hidden" name="admin_action" value="update_user_role">
                  <input type="hidden" name="profile_user_id" value="<?= e((string) $selectedUserId) ?>">
                  <input type="hidden" name="selected_book_id" value="<?= e((string) $selectedBookId) ?>">
                  <input type="hidden" name="user_role_filter" value="<?= e($currentRoleFilter) ?>">
                  <input type="hidden" name="catalog_filter" value="<?= e($currentCatalogFilter) ?>">
                  <input type="hidden" name="return_section" value="users">
                  <div class="admin-fields" style="margin-top:.75rem;">
                    <div class="admin-field">
                      <label for="role-<?= e((string) $selectedUserId) ?>">Assign role</label>
                      <select id="role-<?= e((string) $selectedUserId) ?>" name="role">
                        <?php foreach (admin_user_roles() as $roleOption): ?>
                          <option value="<?= e($roleOption) ?>"<?= $selectedRole === $roleOption ? ' selected' : '' ?>><?= e(ucfirst($roleOption)) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="admin-form-row" style="margin-top:.75rem;">
                    <button class="admin-button" type="submit">Save Role</button>
                  </div>
                </form>

                <h3 style="margin-top:1rem;">Recent Purchases</h3>
                <?php if (($selectedUser['recent_purchases'] ?? []) === []): ?>
                  <p class="admin-note">No recorded purchases for this account.</p>
                <?php else: ?>
                  <ul class="admin-list">
                    <?php foreach ((array) $selectedUser['recent_purchases'] as $purchase): ?>
                      <li><strong><?= e((string) ($purchase['title'] ?? 'Book')) ?></strong><br><span>Purchased <?= e(format_admin_date((string) ($purchase['purchased_on'] ?? ''))) ?></span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>

                <h3 style="margin-top:1rem;">Authored Books</h3>
                <?php if (($selectedUser['authored_books'] ?? []) === []): ?>
                  <p class="admin-note">No authored books are linked to this account.</p>
                <?php else: ?>
                  <ul class="admin-list">
                    <?php foreach ((array) $selectedUser['authored_books'] as $authoredBook): ?>
                      <li><strong><?= e((string) ($authoredBook['title'] ?? 'Book')) ?></strong><br><span><?= e(format_admin_money($authoredBook['price'] ?? 0)) ?> · <?= (int) ($authoredBook['vetted'] ?? 0) === 1 ? 'Live' : 'Hidden' ?> · <?= e((string) ((int) ($authoredBook['sales_count'] ?? 0))) ?> sales</span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              <?php endif; ?>
            </aside>
          </div>
        </section>

        <section id="catalog" class="admin-section reveal" style="margin-top:1rem;">
          <h2>Book Catalog Management</h2>
          <p class="admin-note">Update metadata in place. Under the current data model, the vetted toggle controls storefront visibility and also affects normal reader access.</p>
          <div class="admin-filter-row">
            <?php foreach (['all' => 'All Books', 'live' => 'Live', 'pending' => 'Pending'] as $filterValue => $filterLabel): ?>
              <a class="admin-chip<?= $currentCatalogFilter === $filterValue ? ' is-active' : '' ?>" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $filterValue, 'user' => $selectedUserId > 0 ? (string) $selectedUserId : null, 'book' => $selectedBookId > 0 ? (string) $selectedBookId : null], 'catalog')) ?>"><?= e($filterLabel) ?></a>
            <?php endforeach; ?>
          </div>

          <div class="admin-books">
            <?php if ($books === []): ?>
              <p class="admin-note">No books matched this filter.</p>
            <?php else: ?>
              <?php foreach ($books as $index => $book): ?>
                <?php $bookId = (int) ($book['bookID'] ?? 0); $isLive = (int) ($book['vetted'] ?? 0) === 1; $isOpen = $selectedBookId > 0 ? $selectedBookId === $bookId : $index === 0; ?>
                <details class="admin-book"<?= $isOpen ? ' open' : '' ?>>
                  <summary>
                    <div>
                      <strong><?= e((string) ($book['title'] ?? 'Book')) ?></strong><br>
                      <span><?= e((string) ($book['author_names'] ?? 'Pagemark Author')) ?> · <?= e(format_admin_money($book['price'] ?? 0)) ?> · <?= e((string) ((int) ($book['sales_count'] ?? 0))) ?> sales</span>
                    </div>
                    <span class="admin-pill <?= $isLive ? 'live' : 'pending' ?>"><?= $isLive ? 'Live' : 'Hidden' ?></span>
                  </summary>

                  <form class="admin-book-form" action="admin.php#catalog" method="POST">
                    <?= csrf_input() ?>
                    <input type="hidden" name="admin_action" value="update_book">
                    <input type="hidden" name="selected_book_id" value="<?= e((string) $bookId) ?>">
                    <input type="hidden" name="profile_user_id" value="<?= e((string) $selectedUserId) ?>">
                    <input type="hidden" name="user_role_filter" value="<?= e($currentRoleFilter) ?>">
                    <input type="hidden" name="catalog_filter" value="<?= e($currentCatalogFilter) ?>">
                    <input type="hidden" name="return_section" value="catalog">

                    <div class="admin-fields">
                      <div class="admin-field">
                        <label for="title-<?= e((string) $bookId) ?>">Title</label>
                        <input id="title-<?= e((string) $bookId) ?>" type="text" name="title" maxlength="512" required value="<?= e((string) ($book['title'] ?? '')) ?>">
                      </div>
                      <div class="admin-field">
                        <label for="price-<?= e((string) $bookId) ?>">Price</label>
                        <input id="price-<?= e((string) $bookId) ?>" type="number" name="price" min="0" step="0.01" required value="<?= e((string) ($book['price'] ?? '0')) ?>">
                      </div>
                      <div class="admin-field">
                        <label for="image-<?= e((string) $bookId) ?>">Cover Path</label>
                        <input id="image-<?= e((string) $bookId) ?>" type="text" name="image" maxlength="1024" value="<?= e((string) ($book['image'] ?? '')) ?>">
                      </div>
                      <div class="admin-field">
                        <label for="pdf-<?= e((string) $bookId) ?>">PDF Reference</label>
                        <input id="pdf-<?= e((string) $bookId) ?>" type="text" name="pdf_refrence_path" maxlength="1024" value="<?= e((string) ($book['pdf_refrence_path'] ?? '')) ?>">
                      </div>
                    </div>

                    <div class="admin-field">
                      <label for="blurb-<?= e((string) $bookId) ?>">Blurb</label>
                      <textarea id="blurb-<?= e((string) $bookId) ?>" name="blurb" maxlength="2048"><?= e((string) ($book['blurb'] ?? '')) ?></textarea>
                    </div>

                    <div class="admin-form-row">
                      <label><input type="checkbox" name="is_vetted" value="1"<?= $isLive ? ' checked' : '' ?>> Visible on storefront</label>
                      <button class="admin-button" type="submit">Save Book Changes</button>
                    </div>
                  </form>
                </details>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </section>

        <section id="reviews" class="admin-section reveal" style="margin-top:1rem;">
          <h2>Review Moderation</h2>
          <div class="admin-table-wrap" style="margin-top:1rem;">
            <?php if ($reviews === []): ?>
              <p class="admin-note">There are no stored reviews to moderate.</p>
            <?php else: ?>
              <table class="admin-table">
                <thead>
                  <tr><th>Reviewer</th><th>Book</th><th>Rating</th><th>Review</th><th>Date</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <?php foreach ($reviews as $review): ?>
                    <?php $reviewId = (int) ($review['id'] ?? 0); ?>
                    <tr>
                      <td><strong><?= e((string) ($review['name'] ?? 'Anonymous')) ?></strong></td>
                      <td><?= e((string) ($review['book'] ?? 'Book')) ?></td>
                      <td><?= e((string) ((int) ($review['rating'] ?? 0))) ?>/5</td>
                      <td><?= e((string) ($review['text'] ?? '')) ?></td>
                      <td><?= e(format_admin_date((string) ($review['date'] ?? ''))) ?></td>
                      <td>
                        <form action="admin.php#reviews" method="POST" onsubmit="return confirm('Delete this review?');">
                          <?= csrf_input() ?>
                          <input type="hidden" name="admin_action" value="delete_review">
                          <input type="hidden" name="profile_user_id" value="<?= e((string) $selectedUserId) ?>">
                          <input type="hidden" name="selected_book_id" value="<?= e((string) $selectedBookId) ?>">
                          <input type="hidden" name="selected_review_id" value="<?= e((string) $reviewId) ?>">
                          <input type="hidden" name="user_role_filter" value="<?= e($currentRoleFilter) ?>">
                          <input type="hidden" name="catalog_filter" value="<?= e($currentCatalogFilter) ?>">
                          <input type="hidden" name="return_section" value="reviews">
                          <button class="admin-button" type="submit">Delete</button>
                        </form>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            <?php endif; ?>
          </div>
        </section>

        <section id="reports" class="admin-section reveal" style="margin-top:1rem;">
          <h2>Transaction Reporting</h2>
          <div class="admin-stats" style="margin-top:1rem;">
            <article class="admin-card"><h3>Total Transactions</h3><p class="admin-stat"><?= e(format_dashboard_count($transactionReport['summary']['total_transactions'] ?? null)) ?></p><p class="admin-note">All recorded purchase rows.</p></article>
            <article class="admin-card"><h3>Unique Buyers</h3><p class="admin-stat"><?= e(format_dashboard_count($transactionReport['summary']['unique_customers'] ?? null)) ?></p><p class="admin-note">Distinct user IDs in transactions.</p></article>
            <article class="admin-card"><h3>Books Sold</h3><p class="admin-stat"><?= e(format_dashboard_count($transactionReport['summary']['unique_books'] ?? null)) ?></p><p class="admin-note">Distinct book IDs with purchases.</p></article>
            <article class="admin-card"><h3>Est. Revenue</h3><p class="admin-stat"><?= e(format_admin_money($transactionReport['summary']['estimated_revenue'] ?? 0)) ?></p><p class="admin-note">Computed from current catalog pricing.</p></article>
          </div>

          <div class="admin-form-row" style="margin-top:1rem;">
            <span class="admin-note">Most recent purchase: <?= e(format_admin_date((string) ($transactionReport['summary']['most_recent_purchase'] ?? ''))) ?></span>
            <a class="admin-button" href="<?= e(admin_dashboard_url(['user_role' => $currentRoleFilter, 'catalog' => $currentCatalogFilter, 'user' => $selectedUserId > 0 ? (string) $selectedUserId : null, 'book' => $selectedBookId > 0 ? (string) $selectedBookId : null, 'download' => 'transactions_csv'])) ?>">Export CSV</a>
          </div>

          <div class="admin-columns" style="margin-top:1rem;">
            <div class="admin-card">
              <h3>Recent Transactions</h3>
              <div class="admin-table-wrap" style="margin-top:.75rem;">
                <?php if (($transactionReport['recent_transactions'] ?? []) === []): ?>
                  <p class="admin-note">No transactions are available.</p>
                <?php else: ?>
                  <table class="admin-table">
                    <thead>
                      <tr><th>Date</th><th>Customer</th><th>Book</th><th>Current Price</th></tr>
                    </thead>
                    <tbody>
                      <?php foreach ((array) $transactionReport['recent_transactions'] as $transaction): ?>
                        <tr>
                          <td><?= e(format_admin_date((string) ($transaction['date_of_purchase'] ?? ''))) ?></td>
                          <td><strong><?= e((string) ($transaction['customer_name'] ?? 'Unknown User')) ?></strong><br><span><?= e((string) ($transaction['customer_email'] ?? '')) ?></span></td>
                          <td><?= e((string) ($transaction['book_title'] ?? 'Unknown Book')) ?></td>
                          <td><?= e(format_admin_money($transaction['current_price'] ?? 0)) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </div>
            </div>

            <div style="display:grid; gap:1rem;">
              <div class="admin-card">
                <h3>Top Books</h3>
                <?php if (($transactionReport['top_books'] ?? []) === []): ?>
                  <p class="admin-note">No book sales data is available.</p>
                <?php else: ?>
                  <ul class="admin-list">
                    <?php foreach ((array) $transactionReport['top_books'] as $topBook): ?>
                      <li><strong><?= e((string) ($topBook['title'] ?? 'Unknown Book')) ?></strong><br><span><?= e((string) ((int) ($topBook['sales_count'] ?? 0))) ?> sales · <?= e(format_admin_money($topBook['estimated_revenue'] ?? 0)) ?> est. revenue</span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>

              <div class="admin-card">
                <h3>Top Customers</h3>
                <?php if (($transactionReport['top_customers'] ?? []) === []): ?>
                  <p class="admin-note">No customer purchase data is available.</p>
                <?php else: ?>
                  <ul class="admin-list">
                    <?php foreach ((array) $transactionReport['top_customers'] as $topCustomer): ?>
                      <li><strong><?= e((string) ($topCustomer['customer_name'] ?? 'Unknown User')) ?></strong><br><span><?= e((string) ($topCustomer['customer_email'] ?? '')) ?> · <?= e((string) ((int) ($topCustomer['purchase_count'] ?? 0))) ?> purchases</span></li>
                    <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </section>

      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
</body>
</html>
