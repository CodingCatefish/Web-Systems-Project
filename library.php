<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$user = require_login();
$books = fetch_library_books_for_user($user);
$libraryNotice = flash_get('library_notice');

function library_meta_text(array $book): string
{
    $parts = [];

    if (!empty($book['author_names'])) {
        $parts[] = (string) $book['author_names'];
    }

    if (!empty($book['date_of_purchase'])) {
        $parts[] = 'Added to your library on ' . (string) $book['date_of_purchase'];
    }

    return implode(' | ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Your purchased Pagemark digital books.">
  <title>Pagemark - My Library</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .library-shell {
      display: grid;
      gap: 1.5rem;
    }

    .library-hero {
      display: grid;
      gap: 0.7rem;
    }

    .library-kicker {
      margin: 0;
      color: var(--color-accent);
      font-size: 0.78rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .library-title {
      margin: 0;
      font-family: 'Playfair Display', serif;
      font-size: clamp(2rem, 4vw, 3rem);
      color: var(--color-primary);
    }

    .library-copy {
      margin: 0;
      max-width: 65ch;
      color: var(--color-muted);
      line-height: 1.7;
    }

    .library-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 1rem;
    }

    .library-notice {
      padding: 0.95rem 1.1rem;
      border-radius: 18px;
      border: 1px solid rgba(26, 32, 44, 0.08);
      font-weight: 700;
      line-height: 1.6;
    }

    .library-notice-success {
      background: #ddf5e5;
      color: #1a6b3d;
    }

    .library-card {
      display: grid;
      gap: 1rem;
      padding: 1.15rem;
      border-radius: 24px;
      background: var(--color-paper);
      border: 1px solid rgba(26, 32, 44, 0.08);
      box-shadow: var(--shadow-sm);
    }

    .library-cover {
      aspect-ratio: 2 / 3;
      border-radius: 18px;
      overflow: hidden;
      background: linear-gradient(160deg, rgba(245, 158, 11, 0.18), rgba(26, 32, 44, 0.04));
    }

    .library-cover img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .library-card h2 {
      margin: 0;
      color: var(--color-primary);
      font-size: 1.18rem;
    }

    .library-meta,
    .library-blurb,
    .library-empty p {
      margin: 0;
      color: var(--color-muted);
      line-height: 1.65;
    }

    .library-actions {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
    }

    .library-empty {
      padding: 1.2rem 1.3rem;
      border-radius: 22px;
      background: rgba(26, 32, 44, 0.04);
      border: 1px solid rgba(26, 32, 44, 0.08);
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
          <a class="nav-link nav-account" href="library.php" id="account-nav-link">Library</a>
          <a class="nav-link" href="admin.php" id="admin-nav-link" style="display: none; font-weight: 800; color: var(--color-accent);">Admin</a>
          <form class="nav-search" action="books.html" method="get" role="search" aria-label="Search books">
            <label class="visually-hidden" for="nav-search-input">Search books by title or author</label>
            <div class="nav-search__shell">
              <input class="nav-search__input" id="nav-search-input" name="search" type="search" placeholder="Search books or authors" autocomplete="off" enterkeyhint="search">
              <button class="nav-search__button" type="submit" aria-label="Search books">
                <svg class="nav-search__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                  <circle cx="11" cy="11" r="6.5"></circle>
                  <path d="M16 16L21 21"></path>
                </svg>
              </button>
            </div>
          </form>
          <a class="cart-link" href="books.html">Bag <span id="cart-count">0</span></a>
        </nav>
      </div>
    </header>

    <main id="main-content">
      <section class="section">
        <div class="container library-shell">
          <section class="library-hero reveal">
            <p class="library-kicker">Digital Shelf</p>
            <h1 class="library-title">Your Library</h1>
            <p class="library-copy">Books you purchase through the digital checkout are available here to open in the embedded reader.</p>
          </section>

          <?php if (is_array($libraryNotice) && isset($libraryNotice['message'], $libraryNotice['type'])): ?>
            <section class="library-notice library-notice-<?= e((string) $libraryNotice['type']) ?> reveal" role="status" aria-live="polite">
              <?= e((string) $libraryNotice['message']) ?>
            </section>
          <?php endif; ?>

          <?php if ($books === []): ?>
            <section class="library-empty reveal">
              <p>Your library is empty right now. Buy a digital title from the catalog and it will appear here automatically.</p>
            </section>
          <?php else: ?>
            <section class="library-grid">
              <?php foreach ($books as $book): ?>
                <article class="library-card reveal">
                  <div class="library-cover">
                    <?php if (!empty($book['image'])): ?>
                      <img src="<?= e((string) $book['image']) ?>" alt="<?= e((string) $book['title']) ?> cover">
                    <?php endif; ?>
                  </div>
                  <div>
                    <h2><?= e((string) $book['title']) ?></h2>
                    <p class="library-meta"><?= e(library_meta_text($book)) ?></p>
                  </div>
                  <p class="library-blurb"><?= e((string) ($book['blurb'] ?? 'Open this title in the browser-based reader.')) ?></p>
                  <div class="library-actions">
                    <a class="btn btn-primary" href="reader.php?book=<?= (int) ($book['bookID'] ?? 0) ?>">Read now</a>
                    <a class="btn btn-soft" href="books.html">Browse more books</a>
                  </div>
                </article>
              <?php endforeach; ?>
            </section>
          <?php endif; ?>
        </div>
      </section>
    </main>

    <footer class="site-footer">
      <div class="container footer-grid">
        <section>
          <h4>Pagemark</h4>
          <p>Curated books, a welcoming space, and a strong reading community all year round.</p>
        </section>
        <section>
          <h4>Explore</h4>
          <ul class="footer-links">
            <li><a href="books.html">View Books</a></li>
            <li><a href="library.php">My Library</a></li>
            <li><a href="services.html">Services</a></li>
          </ul>
        </section>
        <section>
          <h4>Information</h4>
          <ul class="footer-links">
            <li><a href="about.html">About Us</a></li>
            <li><a href="reviews.html">Reviews</a></li>
            <li><a href="about.html">Contact</a></li>
          </ul>
        </section>
      </div>
      <div class="container copy">&copy; <span class="js-year"></span> Pagemark Bookstore. All rights reserved.</div>
    </footer>
  </div>

  <div class="toast" id="cart-toast" role="status" aria-live="polite"></div>
  <script src="js/main.js"></script>
</body>
</html>
