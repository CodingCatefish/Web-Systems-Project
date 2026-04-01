<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$authorUser = require_author_or_admin();
$booksByAuthor = count_books_by_author();
$booksSoldByAuthor = count_books_sold_by_author();
$dashboardSummary = current_author_dashboard_summary();
$isAdminView = $dashboardSummary['mode'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Pagemark author dashboard.">
  <title>Pagemark - Author Dashboard</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .author-dashboard-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .author-card {
      background: var(--color-paper);
      border: 1px solid rgba(26, 32, 44, 0.08);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 1.2rem;
    }

    .author-card h2 {
      margin: 0 0 0.5rem;
      color: var(--color-primary);
      font-size: 1rem;
    }

    .author-stat {
      margin: 0;
      color: var(--color-accent);
      font-size: 2rem;
      font-weight: 800;
    }

    .author-note {
      margin-top: 0.6rem;
      color: var(--color-muted);
      line-height: 1.5;
    }

    .author-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      margin-top: 1.5rem;
    }

    .author-back-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0.8rem 1.2rem;
      border-radius: var(--radius-pill);
      border: 1px solid rgba(26, 32, 44, 0.14);
      text-decoration: none;
      color: var(--color-primary);
      background: #fff;
      font-weight: 700;
    }

    @media (max-width: 720px) {
      .author-dashboard-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="site-shell">
    <header class="site-header">
      <div class="container header-row">
        <a class="brand" href="index.html"><span class="brand-mark">B</span> Pagemark</a>
        <nav class="nav-links" aria-label="Main navigation">
          <a class="nav-link" href="index.html">Home</a>
          <a class="nav-link" href="books.html">View Books</a>
          <a class="nav-link" href="services.html">Services</a>
          <a class="nav-link" href="reviews.html">Reviews</a>
          <a class="nav-link" href="about.html">About</a>
          <a class="nav-link nav-account" href="login.php" id="account-nav-link">Login</a>
          <a class="nav-link" href="admin.php" id="admin-nav-link" style="display: none; font-weight: 800; color: var(--color-accent);">Admin</a>
          <a class="cart-link" href="books.html">Bag <span id="cart-count">0</span></a>
        </nav>
      </div>
    </header>

    <main id="main-content">
      <section class="page-hero">
        <div class="container reveal">
          <h1 class="section-title">Author Dashboard</h1>
          <p class="section-subtitle">
            <?= $isAdminView
              ? 'Admin-wide author activity view for ' . e((string) $authorUser['email']) . '.'
              : 'Signed in as ' . e((string) $authorUser['name']) . ' (' . e((string) $authorUser['role']) . ').' ?>
          </p>
        </div>
      </section>

      <section class="container reveal">
        <div class="author-dashboard-grid">
          <article class="author-card">
            <h2><?= e((string) $dashboardSummary['title_label']) ?></h2>
            <p class="author-stat"><?= e((string) $booksByAuthor) ?></p>
            <p class="author-note"><?= e((string) $dashboardSummary['title_note']) ?></p>
          </article>

          <article class="author-card">
            <h2><?= e((string) $dashboardSummary['sales_label']) ?></h2>
            <p class="author-stat"><?= e((string) $booksSoldByAuthor) ?></p>
            <p class="author-note"><?= e((string) $dashboardSummary['sales_note']) ?></p>
          </article>
        </div>

        <div class="author-actions">
          <a class="btn btn-primary" href="author-upload.php">Upload a New Book</a>
          <?php if ($isAdminView): ?>
            <a class="author-back-link" href="admin.php">Back to Admin</a>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <div class="container footer-grid">
        <section>
          <h4>Pagemark</h4>
          <p>Independent bookstore, reading community, and cafe. Built for people who collect stories.</p>
        </section>
        <section>
          <h4>Explore</h4>
          <ul class="footer-links">
            <li><a href="books.html">View Books</a></li>
            <li><a href="books.html">Book Clubs</a></li>
            <li><a href="services.html">Events</a></li>
          </ul>
        </section>
        <section>
          <h4>Information</h4>
          <ul class="footer-links">
            <li><a href="about.html">About</a></li>
            <li><a href="services.html">Services</a></li>
            <li><a href="about.html">Contact</a></li>
            <li><a href="about.html">Policies</a></li>
          </ul>
        </section>
      </div>
      <div class="container copy">&copy; <span class="js-year"></span> Pagemark. All rights reserved.</div>
    </footer>
  </div>

  <div class="toast" id="cart-toast" role="status" aria-live="polite"></div>
  <script src="js/main.js"></script>
</body>
</html>
