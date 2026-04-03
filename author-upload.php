<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
no_cache();
require_author_access('Your account is signed in, but it does not have author upload access.');

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="About Pagemark.">
  <title>Pagemark - Book Upload</title>
  <link rel="stylesheet" href="css/styles.css">
  <style>
    main {
      margin: auto;
    }

    .custom-file-upload {
      border: 1px solid #ccc;
      display: inline-block;
      padding: 6px 12px;
      cursor: pointer;
    }

    input[type=submit] {
      border: 1px solid #ccc;
      display: inline-block;
      padding: 6px 12px;
      cursor: pointer;

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
          <a class="nav-link" href="admin.php" id="admin-nav-link"
            style="display: none; font-weight: 800; color: var(--color-accent);">Admin</a>
          <a class="cart-link" href="books.html">Bag <span id="cart-count">0</span></a>
        </nav>
      </div>
    </header>

    <main id="main-content">

      <section class="page-hero">
        <div class="container reveal">
          <h1 class="section-title">Upload Your Book Here:</h1>
        </div>
      </section>

      <form action="author-upload.php" method="post" enctype="multipart/form-data" novalidate>
        <label for="title">Title:</label>
        <input required type="text" id="title" name="title"><br><br>
        <label for="price">Price:</label>
        <input required type="number" id="price" name="price" min="0"><br><br>
        <label for="blurb">Blurb:</label>
        <textarea required id="blurb" name="blurb" cols="200" rows="10"></textarea><br><br>
        <label for="image" class="custom-file-upload">Upload Cover Image</label>
        <input required type="file" name="image" id="image"><br><br>
        <label for="bookPDF" class="custom-file-upload">Upload Book as PDF</label>
        <input required type="file" name="bookPDF" id="bookPDF"><br><br>
        <input type="submit" value="Upload Book" name="submit">
      </form>


    </main>

    <footer class="site-footer">
      <div class="container footer-grid">
        <section>
          <h2>Pagemark</h2>
          <p>Independent bookstore, reading community, and cafe. Built for people who collect stories.</p>
        </section>
        <section>
          <h2>Explore</h2>
          <ul class="footer-links">
            <li><a href="books.html">View Books</a></li>
            <li><a href="books.html">Book Clubs</a></li>
            <li><a href="services.html">Events</a></li>
          </ul>
        </section>
        <section>
          <h2>Information</h2>
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
