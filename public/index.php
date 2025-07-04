<?php
require_once __DIR__ . '/../vendor/autoload.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Render HTML
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validate Your Idea Instantly</title>
    <link rel="stylesheet" href="assets/responsiveFormComponents.css">
</head>
<body>
  <header class="site-header">
    <img
      src="assets/images/quick-idea-validator-banner.png"
      alt="Quick Idea Validator"
      class="banner-img"
    />
    <h1>Validate Your Idea Instantly</h1>
    <p class="instructions">Enter your idea below and get instant AI feedback. Nothing is stored or saved.</p>
  </header>

  <main class="app-main">
  <form id="ideaForm" class="idea-form" action="api/validate.php" method="post" novalidate>
    <label for="ideaInput" class="sr-only">Describe your idea</label>
    <textarea
      id="ideaInput"
      class="idea-form__textarea"
      name="idea"
      maxlength="200"
      rows="4"
      placeholder="Enter your idea (max 200 characters)"
      required
      aria-required="true"
    ></textarea>
    <input type="hidden" id="csrfToken" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" id="submitBtn" class="submit-btn" disabled>
      Validate Idea
      <span id="spinner" class="spinner" aria-hidden="true"></span>
    </button>
  </form>

  <div id="resultContainer" aria-live="polite" class="response-box"></div>

  </main>

  <script src="assets/ariaLiveAnnouncer.js" defer></script>
  <script src="assets/formSubmissionController.js" defer></script>

  <footer>
    <p>&copy; <?php echo date('Y'); ?> Quick Idea Validator. <a href="privacy.php">Privacy</a> | <a href="terms.php">Terms</a></p>
  </footer>
</body>
</html>
