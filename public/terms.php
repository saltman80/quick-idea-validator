<?php
require_once __DIR__ . '/../vendor/autoload.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terms of Use - Quick Idea Validator</title>
    <link rel="stylesheet" href="assets/responsiveFormComponents.css">
</head>
<body>
  <header class="site-header">
    <img src="assets/images/quick-idea-validator-banner.png" alt="Quick Idea Validator" class="banner-img" />
    <h1>Terms of Use</h1>
  </header>
  <main class="app-main">
    <div class="idea-form">
      <p>This service is provided for demonstration purposes only. Ideas are processed transiently and are not saved.</p>
      <p>By using this site you agree that your data may be sent to third-party APIs to generate a response.</p>
    </div>
  </main>
  <footer>
    <p>&copy; <?php echo date('Y'); ?> Quick Idea Validator. <a href="privacy.php">Privacy</a> | <a href="terms.php">Terms</a></p>
  </footer>
</body>
</html>
