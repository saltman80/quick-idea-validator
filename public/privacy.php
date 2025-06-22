<?php
require_once __DIR__ . '/../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy - Quick Idea Validator</title>
    <link rel="stylesheet" href="assets/responsiveFormComponents.css">
</head>
<body>
  <header class="site-header">
    <img src="assets/images/quick-idea-validator-banner.png" alt="Quick Idea Validator" class="banner-img" />
    <h1>Privacy Policy</h1>
  </header>
  <main class="app-main">
    <div class="idea-form">
      <p>This demo site does not store or inspect any ideas you submit. All data is discarded after processing.</p>
      <p>Your submissions are used solely to generate a response from the OpenRouter API.</p>
    </div>
  </main>
  <footer>
    <p>&copy; <?php echo date('Y'); ?> Quick Idea Validator. <a href="privacy.php">Privacy</a> | <a href="terms.php">Terms</a></p>
  </footer>
</body>
</html>
