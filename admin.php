<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$adminUser = require_admin();
$counts = dashboard_counts();

function format_dashboard_count(?int $count): string
{
    return $count === null ? 'N/A' : (string) $count;
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
    .admin-shell {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .admin-main {
      flex: 1;
      padding: 2.4rem 0 3rem;
    }

    .admin-head {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      align-items: center;
      gap: 1rem;
      margin-bottom: 1.4rem;
    }

    .admin-title {
      margin: 0;
      font-family: 'Playfair Display', serif;
      color: var(--color-primary);
      font-size: clamp(1.6rem, 3.6vw, 2.3rem);
    }

    .admin-subtitle {
      margin: 0.35rem 0 0;
      color: var(--color-muted);
      font-size: 0.95rem;
    }

    .admin-logout {
      border: 1px solid rgba(26, 32, 44, 0.16);
      background: #fff;
      color: var(--color-primary);
      border-radius: var(--radius-pill);
      padding: 0.5rem 0.9rem;
      font-weight: 700;
      cursor: pointer;
    }

    .admin-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 1rem;
      margin-top: 1.2rem;
    }

    .admin-card {
      background: var(--color-paper);
      border: 1px solid rgba(26, 32, 44, 0.07);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 1.2rem;
    }

    .admin-card h2 {
      margin: 0 0 0.6rem;
      font-size: 1rem;
      color: var(--color-primary);
    }

    .admin-stat {
      margin: 0;
      font-size: 1.8rem;
      font-weight: 800;
      color: var(--color-accent);
    }

    .admin-note {
      margin-top: 0.7rem;
      color: var(--color-muted);
      line-height: 1.5;
      font-size: 0.9rem;
    }

    .admin-actions {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      margin-top: 1rem;
    }

    .admin-action-card {
      display: block;
      text-decoration: none;
      background: linear-gradient(145deg, rgba(210, 169, 107, 0.12), rgba(255, 255, 255, 0.96));
      border: 1px solid rgba(26, 32, 44, 0.08);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: 1.2rem;
      color: inherit;
    }

    .admin-action-card h2 {
      margin: 0;
      color: var(--color-primary);
      font-size: 1rem;
    }

    @media (max-width: 920px) {
      .admin-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }
    }

    @media (max-width: 620px) {
      .admin-grid {
        grid-template-columns: 1fr;
      }

      .admin-actions {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="admin-shell">
    <header class="site-header">
      <div class="container header-row">
        <a class="brand" href="index.html"><span class="brand-mark">B</span> Pagemark</a>
        <nav class="nav-links" aria-label="Admin navigation">
          <a class="nav-link" href="index.html">Public Site</a>
          <form action="logout.php" method="POST">
            <?= csrf_input() ?>
            <button class="admin-logout" type="submit">Logout</button>
          </form>
        </nav>
      </div>
    </header>

    <main id="main-content" class="admin-main">
      <div class="container">
        <section class="admin-head reveal">
          <div>
            <h1 class="admin-title">Admin Dashboard</h1>
            <p class="admin-subtitle">Restricted area for bookstore administrators. Signed in as <?= e((string) $adminUser['email']) ?>.</p>
          </div>
        </section>

        <div class="admin-grid">
          <article class="admin-card reveal">
            <h2>Books</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['books'])) ?></p>
            <p class="admin-note">All book records stored in the catalog, including titles that are still waiting for vetting.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Pending Vetting</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['pending_books'])) ?></p>
            <p class="admin-note">Unvetted uploads created through the author workflow and waiting for storefront approval.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Reviews</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['reviews'])) ?></p>
            <p class="admin-note">Persisted review rows in the database. Browser-local drafts still stay outside this count.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Users</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['users'])) ?></p>
            <p class="admin-note">Total accounts across customers, authors, and any database-backed admin users.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Authors</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['authors'])) ?></p>
            <p class="admin-note">User accounts currently marked with the `author` role and eligible for author tools.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Recorded Sales</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['transactions'])) ?></p>
            <p class="admin-note">Transaction rows captured in the `Transactions` table from the checkout flow.</p>
          </article>

          <article class="admin-card reveal">
            <h2>Author Links</h2>
            <p class="admin-stat"><?= e(format_dashboard_count($counts['author_links'])) ?></p>
            <p class="admin-note">Book-to-author assignments stored in `AuthorLists`, which power the author dashboard and sales rollups.</p>
          </article>
        </div>

        <div class="admin-actions">
          <a class="admin-action-card reveal" href="author-dashboard.php">
            <h2>Author Overview</h2>
          </a>

          <a class="admin-action-card reveal" href="author-upload.php">
            <h2>Upload a Book</h2>
          </a>
        </div>
      </div>
    </main>
  </div>

  <script src="js/main.js"></script>
</body>
</html>
