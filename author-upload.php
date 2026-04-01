<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

no_cache();
$authorUser = require_author_or_admin();
$isAdminView = ((string) ($authorUser['role'] ?? '')) === 'admin';
$statusMessage = '';
$errorMessage = '';
$titleValue = '';
$priceValue = '';
$blurbValue = '';

if (request_method() === 'POST') {
    $titleValue = trim((string) ($_POST['title'] ?? ''));
    $priceValue = trim((string) ($_POST['price'] ?? ''));
    $blurbValue = trim((string) ($_POST['blurb'] ?? ''));

    try {
        require_valid_form_post('author-upload.php');

        if ($titleValue === '' || $priceValue === '' || $blurbValue === '') {
            throw new RuntimeException('Please complete the title, price, and blurb fields.');
        }

        $titleLength = function_exists('mb_strlen') ? mb_strlen($titleValue) : strlen($titleValue);
        $blurbLength = function_exists('mb_strlen') ? mb_strlen($blurbValue) : strlen($blurbValue);

        if ($titleLength > 512) {
            throw new RuntimeException('Book title must be 512 characters or fewer.');
        }

        if ($blurbLength > 2048) {
            throw new RuntimeException('Blurb must be 2048 characters or fewer.');
        }

        $normalizedPrice = normalize_book_price($priceValue);
        if ($normalizedPrice === null) {
            throw new RuntimeException('Enter a valid non-negative price with up to 2 decimal places.');
        }

        $imageUpload = $_FILES['image'] ?? null;
        $pdfUpload = $_FILES['bookPDF'] ?? null;

        if (!is_array($imageUpload) || !is_array($pdfUpload)) {
            throw new RuntimeException('Select both a cover image and a PDF file.');
        }

        if (($imageUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($pdfUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Both the cover image and PDF upload must succeed before the book can be saved.');
        }

        if (($imageUpload['size'] ?? 0) > 100000000) {
            throw new RuntimeException('Cover image files must be 100MB or smaller.');
        }

        if (($pdfUpload['size'] ?? 0) > 100000000) {
            throw new RuntimeException('PDF files must be 100MB or smaller.');
        }

        $imageExtension = strtolower(pathinfo((string) ($imageUpload['name'] ?? ''), PATHINFO_EXTENSION));
        $allowedImageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($imageExtension, $allowedImageExtensions, true) || getimagesize((string) $imageUpload['tmp_name']) === false) {
            throw new RuntimeException('Cover image must be a valid JPG, JPEG, PNG, GIF, or WEBP file.');
        }

        $pdfExtension = strtolower(pathinfo((string) ($pdfUpload['name'] ?? ''), PATHINFO_EXTENSION));
        if ($pdfExtension !== 'pdf') {
            throw new RuntimeException('Uploaded book files must be PDF documents.');
        }

        $pdfMime = '';
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo !== false) {
            $pdfMime = (string) finfo_file($fileInfo, (string) $pdfUpload['tmp_name']);
            finfo_close($fileInfo);
        }

        if ($pdfMime !== '' && $pdfMime !== 'application/pdf') {
            throw new RuntimeException('Uploaded book files must be valid PDF documents.');
        }

        $uploadDirectory = __DIR__ . DIRECTORY_SEPARATOR . 'uploads';
        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0775, true) && !is_dir($uploadDirectory)) {
            throw new RuntimeException('Upload storage is not available right now.');
        }

        $imageFileName = uniqid('cover-', true) . '.' . $imageExtension;
        $pdfFileName = uniqid('book-', true) . '.pdf';
        $imageRelativePath = 'uploads/' . $imageFileName;
        $pdfRelativePath = 'uploads/' . $pdfFileName;
        $imageDestination = $uploadDirectory . DIRECTORY_SEPARATOR . $imageFileName;
        $pdfDestination = $uploadDirectory . DIRECTORY_SEPARATOR . $pdfFileName;

        if (!move_uploaded_file((string) $imageUpload['tmp_name'], $imageDestination)) {
            throw new RuntimeException('The cover image could not be uploaded.');
        }

        if (!move_uploaded_file((string) $pdfUpload['tmp_name'], $pdfDestination)) {
            @unlink($imageDestination);
            throw new RuntimeException('The PDF file could not be uploaded.');
        }

        try {
            upload_book($titleValue, $normalizedPrice, $blurbValue, $imageRelativePath, $pdfRelativePath);
        } catch (Throwable $error) {
            @unlink($imageDestination);
            @unlink($pdfDestination);
            throw $error;
        }

        $statusMessage = 'Book uploaded successfully. It is stored as unvetted until approved.';
        $titleValue = '';
        $priceValue = '';
        $blurbValue = '';
    } catch (RuntimeException $error) {
        $errorMessage = $error->getMessage();
    } catch (Throwable $error) {
        log_server_error('author-upload', $error);
        $errorMessage = 'We could not upload the book right now. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Upload a book to Pagemark.">
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

    .upload-status {
      margin: 1rem 0;
      padding: 0.9rem 1rem;
      border-radius: var(--radius-md);
      line-height: 1.5;
    }

    .upload-status--error {
      background: rgba(183, 28, 28, 0.08);
      border: 1px solid rgba(183, 28, 28, 0.18);
      color: #7f1d1d;
    }

    .upload-status--success {
      background: rgba(21, 128, 61, 0.08);
      border: 1px solid rgba(21, 128, 61, 0.18);
      color: #166534;
    }

    .upload-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 0.85rem;
      margin-top: 1rem;
    }

    .upload-back-link {
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
          <h1 class="section-title">Upload Your Book Here</h1>
          <p class="section-subtitle">
            <?= $isAdminView
              ? 'Admin upload mode for ' . e((string) $authorUser['email']) . '.'
              : 'Signed in as ' . e((string) $authorUser['name']) . ' (' . e((string) $authorUser['role']) . ').' ?>
          </p>
        </div>
      </section>

      <section class="container reveal">
        <?php if ($errorMessage !== ''): ?>
          <div class="upload-status upload-status--error" role="alert"><?= e($errorMessage) ?></div>
        <?php endif; ?>

        <?php if ($statusMessage !== ''): ?>
          <div class="upload-status upload-status--success" role="status"><?= e($statusMessage) ?></div>
        <?php endif; ?>

        <form action="author-upload.php" method="post" enctype="multipart/form-data" novalidate>
          <?= csrf_input() ?>
          <label for="title">Title:</label>
          <input required type="text" id="title" name="title" maxlength="512" value="<?= e($titleValue) ?>"><br><br>
          <label for="price">Price:</label>
          <input required type="number" id="price" name="price" min="0" step="0.01" inputmode="decimal" value="<?= e($priceValue) ?>"><br><br>
          <label for="blurb">Blurb:</label>
          <textarea required id="blurb" name="blurb" cols="200" rows="10" maxlength="2048"><?= e($blurbValue) ?></textarea><br><br>
          <label for="image" class="custom-file-upload">Upload Cover Image</label>
          <input required type="file" name="image" id="image" accept=".jpg,.jpeg,.png,.gif,.webp,image/*"><br><br>
          <label for="bookPDF" class="custom-file-upload">Upload Book as PDF</label>
          <input required type="file" name="bookPDF" id="bookPDF" accept=".pdf,application/pdf"><br><br>
          <input type="submit" value="Upload Book" name="submit">
        </form>

        <div class="upload-actions">
          <a class="upload-back-link" href="<?= $isAdminView ? 'admin.php' : 'author-dashboard.php' ?>">
            <?= $isAdminView ? 'Back to Admin' : 'Back to Dashboard' ?>
          </a>
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
