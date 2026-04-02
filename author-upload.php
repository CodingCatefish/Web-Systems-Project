<?php

declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (request_method() === 'POST') {
  $target_dir = "uploads/";
  $image_file = $target_dir . basename($_FILES["image"]["name"]);
  $pdf_file = $target_dir . basename($_FILES["bookPDF"]["name"]);
  $uploadOk = 1;
  $imageFileType = strtolower(pathinfo($image_file, PATHINFO_EXTENSION));
  $pdfFileType = strtolower(pathinfo($pdf_file, PATHINFO_EXTENSION));
  $errormsg = "";
  // Check if all inputs are not null
  if($_POST["title"]==null || $_POST["price"]==null || $_POST["blurb"]==null || $image_file==null || $pdf_file==null){
    $errormsg+="You have empty input fields.\n";
    print($errormsg);
    return;
  }
  
  // Check if image file is a actual image or fake image
  if (isset($_POST["submit"])) {
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check !== false) {
      $uploadOk = 1;
    } else {
      $errormsg += "Image File is not an image.\n";
      $uploadOk = 0;
    }
  }
  // Check if pdf is actual pdf
  if (isset($_POST["submit"])) {
    $check = getimagesize($_FILES["bookPDF"]["tmp_name"]);
    if ($check !== false) {
      $uploadOk = 1;
    } else {
      $errormsg += "PDF File is not a PDF.\n";
      $uploadOk = 0;
    }
  }

  // Check if file already exists
  if (file_exists($image_file)) {
    $errormsg += "Sorry, image file with that name already exists.\n";
    $uploadOk = 0;
  }
  if (file_exists($pdf_file)) {
    $errormsg += "Sorry, pdf file with that name already exists.\n";
    $uploadOk = 0;
  }

  // Check file size
  if ($_FILES["image"]["size"] > 100000000) {
    $errormsge += "Sorry, your image file is too large.\n";
    $uploadOk = 0;
  }
  if ($_FILES["bookPDF"]["size"] > 100000000) {
    $errormsge += "Sorry, your PDF file is too large.\n";
    $uploadOk = 0;
  }

  // Allow certain file formats
  if (
    $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif"
  ) {
    $errormsg += "Sorry, only JPG, JPEG, PNG & GIF image files are allowed.\n";
    $uploadOk = 0;
  }

  // Check if $uploadOk is set to 0 by an error
  if ($uploadOk == 0) {
    echo "Sorry, your file was not uploaded.";
    // if everything is ok, try to upload file
  } else {
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $image_file)) {
      $errormsg += "The image file " . htmlspecialchars(basename($_FILES["image"]["name"])) . " has been uploaded.";
      if (move_uploaded_file($_FILES["bookPDF"]["tmp_name"], $pdf_file)) {
        upload_book($_POST["title"],$_POST["price"],$_POST["blurb"],$image_file,$pdf_file);
        $errormsg += "The PDF file " . htmlspecialchars(basename($_FILES["bookPDF"]["name"])) . " has been uploaded.";

      } else {
        $errormsg += "Sorry, there was an error uploading your PDF file.";
      }
    } else {
      $errormsg += "Sorry, there was an error uploading your image file.";
    }
  }
  print($errormsg);
}

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