<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Quick Idea Validator</title>
  <link rel="stylesheet" href="responsiveFormComponents.css">
</head>
<body>
  <header>
    <h1>Quick Idea Validator</h1>
  </header>

  <form id="ideaForm" action="aivalidationhandler.php" method="post" novalidate>
    <label for="ideaInput" class="sr-only">Describe your idea</label>
    <textarea
      id="ideaInput"
      name="idea"
      maxlength="200"
      rows="4"
      placeholder="Enter your idea (max 200 characters)"
      required
      aria-required="true"
    ></textarea>
    <input type="hidden" id="csrfToken" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8'); ?>">
    <button type="submit" id="submitBtn" disabled>Validate Idea</button>
  </form>

  <div id="spinner" class="spinner" hidden aria-live="polite" aria-busy="true"></div>
  <div id="resultContainer" aria-live="polite"></div>

  <script src="ariaLiveAnnouncer.js" defer></script>
  <script src="formSubmissionController.js" defer></script>

  <footer>
    <p>&copy; <?php echo date('Y'); ?> Quick Idea Validator. <a href="privacy.html">Privacy</a> | <a href="terms.html">Terms</a></p>
  </footer>
</body>
</html>
