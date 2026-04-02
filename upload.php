<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
if (request_method() === 'POST') {
  $target_dir = "uploads/";
  $image_file = $target_dir . basename($_FILES["image"]["name"]);
  $uploadOk = 1;
  $imageFileType = strtolower(pathinfo($image_file, PATHINFO_EXTENSION));
  $errormsg="";
  // Check if image file is a actual image or fake image
  if (isset($_POST["submit"])) {
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if ($check !== false) {
      $uploadOk = 1;
    } else {
      $errormsg+= "File is not an image.\n";
      $uploadOk = 0;
    }
  }

  // Check if file already exists
  if (file_exists($image_file)) {
    $errormsg+= "Sorry, file with that name already exists.\n";
    $uploadOk = 0;
  }

  // Check file size
  if ($_FILES["image"]["size"] > 100000000) {
    $errormsge+= "Sorry, your file is too large.\n";
    $uploadOk = 0;
  }

  // Allow certain file formats
  if (
    $imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg"
    && $imageFileType != "gif"
  ) {
    $errormsg+= "Sorry, only JPG, JPEG, PNG & GIF files are allowed.\n";
    $uploadOk = 0;
  }

  // Check if $uploadOk is set to 0 by an error
  if ($uploadOk == 0) {
    echo $errormsg;
    // if everything is ok, try to upload file
  } else {
    if (move_uploaded_file($_FILES["image"]["tmp_name"], $image_file)) {
      $errormsg+= "The file " . htmlspecialchars(basename($_FILES["fileToUpload"]["name"])) . " has been uploaded.";
    } else {
      $errormsg+= "Sorry, there was an error uploading your file.";
    }
  }
}
?>