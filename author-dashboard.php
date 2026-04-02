<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

no_cache();
$user = require_login();
$role = (string) ($user['role'] ?? '');

if ($role !== 'author' && $role !== 'admin') {
  send_forbidden_page('Forbidden', 'Your account is signed in, but it does not have author dashboard access.');
}

$bookCount = count_books_by_author();
$bookSoldCount = count_books_sold_by_author();

$SalesInfo = sales_by_author_by_date();
$Dates = e((string) json_encode($SalesInfo['Dates'] ?? [], JSON_UNESCAPED_SLASHES));
$Sales = e((string) json_encode($SalesInfo['Sales'] ?? [], JSON_UNESCAPED_SLASHES));
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Author dashboard for Pagemark.">
  <title>Pagemark - Author Dashboard</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    .dashboard {
      text-align: center;
    }

    h2 {
      display: inline;
      padding: 10%;
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
          <a class="nav-link nav-account" href="author-dashboard.php" id="account-nav-link">Dashboard</a>
          <a class="nav-link" href="admin.php" id="admin-nav-link" style="display: none; font-weight: 800; color: var(--color-accent);">Admin</a>
          <a class="cart-link" href="books.html">Bag <span id="cart-count">0</span></a>
        </nav>
      </div>
    </header>

    <main id="main-content">
      <section class="page-hero">
        <div class="container reveal">
          <h1 class="section-title">Author Dashboard</h1>
        </div>
      </section>
      <section class="dashboard">
        <h2>Books Published:
          <?php
          echo $bookCount;
          ?>
        </h2>
        <h2>Total Books Sold:
          <?php
          echo $bookSoldCount;
          ?>
        </h2>
        <div class="chart-container" style="position: relative; height: 40vh; width: 90vw; display: inline-block;">
          <canvas
            id="myChart"
            data-chart-title="Total Book Sales"
            data-chart-labels='<?= $Dates ?>'
            data-chart-values='<?= $Sales ?>'
            aria-label="Line chart showing total book sales over time"
            role="img"
            style="margin: 0 auto; width: 100%; height: 100%; display: block;"></canvas>
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
  <script src="js/author-dashboard.js"></script>
</body>

</html>
