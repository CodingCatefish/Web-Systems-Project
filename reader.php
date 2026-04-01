<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$user = require_login();
$bookId = max(0, (int) ($_GET['book'] ?? 0));
$book = find_book_by_id($bookId);

if ($book === null || (int) ($book['vetted'] ?? 0) !== 1) {
    send_forbidden_page('Unavailable', 'That book could not be found in the digital catalog.');
}

if (!user_can_access_book($user, $bookId)) {
    send_forbidden_page('Library Access Required', 'Buy this title first before opening it in the online reader.');
}

if (resolve_uploaded_pdf_path((string) ($book['pdf_refrence_path'] ?? '')) === null) {
    send_forbidden_page('Missing File', 'The PDF for this title is not available in uploads right now.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Read your purchased Pagemark book online.">
  <title>Pagemark - Reader</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .reader-layout {
      display: grid;
      grid-template-columns: minmax(260px, 320px) minmax(0, 1fr);
      gap: 1.25rem;
      align-items: start;
    }

    .reader-panel,
    .reader-stage {
      padding: 1.2rem;
    }

    .reader-panel {
      display: grid;
      gap: 1rem;
    }

    .reader-kicker {
      margin: 0;
      color: var(--color-accent);
      font-size: 0.76rem;
      font-weight: 800;
      letter-spacing: 0.14em;
      text-transform: uppercase;
    }

    .reader-title {
      margin: 0;
      font-family: 'Playfair Display', serif;
      font-size: clamp(1.8rem, 3vw, 2.6rem);
      color: var(--color-primary);
    }

    .reader-meta,
    .reader-copy,
    .reader-status {
      margin: 0;
      color: var(--color-muted);
      line-height: 1.7;
    }

    .reader-actions,
    .reader-toolbar {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .reader-toolbar {
      justify-content: space-between;
      margin-bottom: 0.9rem;
    }

    .reader-progress {
      margin: 0;
      color: var(--color-muted);
      font-weight: 700;
    }

    .reader-toolbar-group {
      display: flex;
      gap: 0.6rem;
      flex-wrap: wrap;
      align-items: center;
    }

    .reader-page-form {
      display: inline-flex;
      gap: 0.45rem;
      align-items: center;
      color: var(--color-primary);
      font-weight: 700;
    }

    .reader-page-form input {
      width: 88px;
      padding: 0.6rem 0.75rem;
      border-radius: 999px;
      border: 1px solid rgba(26, 32, 44, 0.14);
    }

    .reader-book {
      position: relative;
      min-height: 76vh;
      border-radius: 28px;
      padding: 1rem;
      background:
        linear-gradient(90deg, rgba(88, 54, 19, 0.18) 0, rgba(88, 54, 19, 0.08) 4%, transparent 8%),
        linear-gradient(180deg, #f7f1e3 0%, #fffdfa 100%);
      border: 1px solid rgba(26, 32, 44, 0.08);
      box-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
      overflow: hidden;
    }

    .reader-book::after {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(88, 54, 19, 0.12), transparent 18%, transparent 82%, rgba(88, 54, 19, 0.08));
      pointer-events: none;
    }

    .reader-book.is-flipping-forward::before,
    .reader-book.is-flipping-backward::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, rgba(255, 255, 255, 0.1), rgba(255, 248, 220, 0.88), rgba(255, 255, 255, 0.1));
      pointer-events: none;
      z-index: 2;
    }

    .reader-book.is-flipping-forward::before {
      transform-origin: left center;
      animation: page-flip-forward 520ms cubic-bezier(0.2, 0, 0, 1);
    }

    .reader-book.is-flipping-backward::before {
      transform-origin: right center;
      animation: page-flip-backward 520ms cubic-bezier(0.2, 0, 0, 1);
    }

    .reader-spread {
      position: relative;
      z-index: 1;
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 1rem;
      min-height: calc(76vh - 2rem);
      align-items: stretch;
    }

    .reader-spread[data-layout="single"] {
      grid-template-columns: 1fr;
    }

    .reader-page {
      display: grid;
      grid-template-rows: minmax(0, 1fr) auto;
      gap: 0.7rem;
      min-height: 0;
    }

    .reader-page[hidden] {
      display: none;
    }

    .reader-page-surface {
      position: relative;
      min-height: 100%;
      border-radius: 22px;
      overflow: hidden;
      display: grid;
      place-items: center;
      padding: 0.9rem;
      background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(245, 237, 221, 0.95)),
        linear-gradient(180deg, rgba(88, 54, 19, 0.05), rgba(88, 54, 19, 0.12));
      border: 1px solid rgba(88, 54, 19, 0.12);
      box-shadow:
        inset 0 0 0 1px rgba(255, 255, 255, 0.5),
        0 18px 30px rgba(15, 23, 42, 0.08);
    }

    .reader-page-canvas {
      display: block;
      width: 100%;
      height: auto;
      max-width: 100%;
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 8px 22px rgba(15, 23, 42, 0.12);
    }

    .reader-page-placeholder {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      padding: 1.5rem;
      text-align: center;
      color: #7c6448;
      font-weight: 800;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      background:
        radial-gradient(circle at top, rgba(255, 255, 255, 0.92), rgba(245, 233, 209, 0.96)),
        linear-gradient(180deg, rgba(88, 54, 19, 0.08), rgba(88, 54, 19, 0.15));
    }

    .reader-page-placeholder[hidden] {
      display: none;
    }

    .reader-page-label {
      margin: 0;
      text-align: center;
      color: var(--color-primary);
      font-weight: 700;
    }

    .reader-loading,
    .reader-error {
      position: absolute;
      inset: 0;
      display: grid;
      place-items: center;
      text-align: center;
      padding: 1.25rem;
      border-radius: inherit;
      background: rgba(255, 252, 245, 0.92);
      z-index: 3;
    }

    .reader-error[hidden],
    .reader-loading[hidden] {
      display: none;
    }

    @keyframes page-flip-forward {
      from {
        opacity: 0;
        transform: perspective(1200px) rotateY(-74deg);
      }

      30% {
        opacity: 1;
      }

      to {
        opacity: 0;
        transform: perspective(1200px) rotateY(78deg);
      }
    }

    @keyframes page-flip-backward {
      from {
        opacity: 0;
        transform: perspective(1200px) rotateY(74deg);
      }

      30% {
        opacity: 1;
      }

      to {
        opacity: 0;
        transform: perspective(1200px) rotateY(-78deg);
      }
    }

    @media (max-width: 960px) {
      .reader-layout {
        grid-template-columns: 1fr;
      }

      .reader-book,
      .reader-spread {
        min-height: 62vh;
      }
    }
  </style>
</head>
<body data-reader-book-id="<?= (int) ($book['bookID'] ?? 0) ?>">
  <a href="#main-content" class="skip-link">Skip to main content</a>
  <div class="site-shell">
    <header class="site-header">
      <div class="container header-row">
        <a class="brand" href="index.html"><span class="brand-mark">B</span> Pagemark</a>
        <nav class="nav-links" aria-label="Main navigation">
          <a class="nav-link" href="index.html">Home</a>
          <a class="nav-link" href="books.html">View Books</a>
          <a class="nav-link" href="library.php">My Library</a>
          <a class="nav-link" href="about.html">About</a>
          <a class="nav-link nav-account" href="library.php" id="account-nav-link">Library</a>
          <a class="nav-link" href="admin.php" id="admin-nav-link" style="display: none; font-weight: 800; color: var(--color-accent);">Admin</a>
          <a class="cart-link" href="books.html">Bag <span id="cart-count">0</span></a>
        </nav>
      </div>
    </header>

    <main id="main-content">
      <section class="section">
        <div class="container reader-layout">
          <aside class="panel reader-panel reveal">
            <div>
              <p class="reader-kicker">Flipbook Reader</p>
              <h1 class="reader-title"><?= e((string) ($book['title'] ?? 'Book')) ?></h1>
            </div>
            <p class="reader-meta"><?= e((string) ($book['author_names'] ?? 'Pagemark Author')) ?></p>
            <p class="reader-copy"><?= e((string) ($book['blurb'] ?? 'Use the embedded reader to page through your purchased PDF without downloading it first.')) ?></p>
            <p class="reader-status" id="reader-status">Loading the protected PDF from uploads.</p>
            <div class="reader-actions">
              <a class="btn btn-primary" href="library.php">Back to library</a>
              <a class="btn btn-soft" href="book-file.php?book=<?= (int) ($book['bookID'] ?? 0) ?>" target="_blank" rel="noopener">Open in browser</a>
            </div>
          </aside>

          <section class="panel reader-stage reveal">
            <div class="reader-toolbar">
              <div class="reader-toolbar-group">
                <button class="btn btn-soft" type="button" id="reader-prev">Previous page</button>
                <button class="btn btn-soft" type="button" id="reader-next">Next page</button>
                <p class="reader-progress" id="reader-progress">Preparing pages.</p>
              </div>
              <form class="reader-page-form" id="reader-page-form">
                <label for="reader-page-input">Page</label>
                <input id="reader-page-input" type="number" min="1" value="1" inputmode="numeric">
                <button class="btn-mini" type="submit">Go</button>
              </form>
            </div>

            <div class="reader-book" id="reader-book">
              <div class="reader-spread" id="reader-spread" data-layout="spread">
                <article class="reader-page" id="reader-page-left" aria-label="Left page">
                  <div class="reader-page-surface">
                    <canvas class="reader-page-canvas" id="reader-canvas-left"></canvas>
                    <div class="reader-page-placeholder" id="reader-placeholder-left" hidden>Front cover</div>
                  </div>
                  <p class="reader-page-label" id="reader-label-left">Front cover</p>
                </article>

                <article class="reader-page" id="reader-page-right" aria-label="Right page">
                  <div class="reader-page-surface">
                    <canvas class="reader-page-canvas" id="reader-canvas-right"></canvas>
                    <div class="reader-page-placeholder" id="reader-placeholder-right" hidden>Page</div>
                  </div>
                  <p class="reader-page-label" id="reader-label-right">Page 1</p>
                </article>
              </div>

              <div class="reader-loading" id="reader-loading">
                <p>Preparing your book for in-browser reading.</p>
              </div>
              <div class="reader-error" id="reader-error" hidden>
                <div>
                  <p>The embedded reader could not load this PDF.</p>
                  <p><a href="book-file.php?book=<?= (int) ($book['bookID'] ?? 0) ?>" target="_blank" rel="noopener">Open the protected PDF directly</a></p>
                </div>
              </div>
            </div>
          </section>
        </div>
      </section>
    </main>
  </div>

  <div class="toast" id="cart-toast" role="status" aria-live="polite"></div>
  <script src="js/main.js"></script>
  <script type="module" src="js/reader.js"></script>
</body>
</html>
