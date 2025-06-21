<?php
// Configuration
define('ENV_FILE', __DIR__ . '/.env');
define('RATE_LIMIT_FILE', __DIR__ . '/data/rate_limit.json');
define('RATE_LIMIT_SECONDS', 60);
define('MODEL', 'gpt-3.5-turbo');
define('OPENROUTER_ENDPOINT', 'https://api.openrouter.ai/v1/chat/completions');

session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

function loadEnv($path) {
    $vars = [];
    if (!file_exists($path)) return $vars;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (!strpos($line, '=')) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (preg_match('/^"(.*)"$/', $value, $m) || preg_match("/^'(.*)'$/", $value, $m)) {
            $value = $m[1];
        }
        $vars[$name] = $value;
    }
    return $vars;
}

// Utility: get client IP
function clientIp() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) return $_SERVER['HTTP_CLIENT_IP'];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

// Handle AJAX submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    $idea = trim($data['idea'] ?? '');
    if ($idea === '' || mb_strlen($idea) > 500) {
        http_response_code(400);
        echo json_encode(['success'=>false, 'error'=>'Invalid input.']);
        exit;
    }
    $ip = clientIp();
    // Ensure data directory
    if (!is_dir(dirname(RATE_LIMIT_FILE))) {
        mkdir(dirname(RATE_LIMIT_FILE), 0755, true);
    }
    // Load and enforce rate limit
    $fp = fopen(RATE_LIMIT_FILE, 'c+');
    if (!$fp) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'error'=>'Server error.']);
        exit;
    }
    flock($fp, LOCK_EX);
    $content = stream_get_contents($fp);
    $limits = $content ? json_decode($content, true) : [];
    $now = time();
    if (isset($limits[$ip]) && $now - $limits[$ip] < RATE_LIMIT_SECONDS) {
        flock($fp, LOCK_UN);
        fclose($fp);
        http_response_code(429);
        echo json_encode(['success'=>false, 'error'=>'Rate limit exceeded. Please wait before retrying.']);
        exit;
    }
    $limits[$ip] = $now;
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($limits));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    // Sanitize idea
    $idea_sanitized = htmlspecialchars(strip_tags($idea), ENT_QUOTES, 'UTF-8');
    // Load API key
    $env = loadEnv(ENV_FILE);
    $apiKey = $env['OPENROUTER_API_KEY'] ?? '';
    if (!$apiKey) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'error'=>'API key not configured.']);
        exit;
    }
    // Prepare AI request
    $prompt = "User submitted app idea: \"$idea_sanitized\"\nIs this a viable startup or app idea? Reply with only YES or NO.";
    $payload = [
        'model' => MODEL,
        'messages' => [
            ['role'=>'user','content'=> $prompt]
        ],
        'max_tokens' => 10,
        'temperature' => 0.0
    ];
    $ch = curl_init(OPENROUTER_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err || !$response) {
        http_response_code(500);
        echo json_encode(['success'=>false, 'error'=>'AI service error.']);
        exit;
    }
    $respData = json_decode($response, true);
    $content = $respData['choices'][0]['message']['content'] ?? '';
    $verdict = preg_match('/\bYES\b/i', $content) ? 'YES' : (preg_match('/\bNO\b/i', $content) ? 'NO' : 'UNKNOWN');
    echo json_encode(['success'=>true, 'verdict'=>$verdict, 'message'=>trim($content)]);
    exit;
}

// Render HTML
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Quick Idea Validator</title>
    <link rel="stylesheet" href="responsiveFormComponents.css">
</head>
<body>
  <header>
    <h1>Quick Idea Validator</h1>
  </header>

  <form id="ideaForm" class="idea-form" action="aivalidationhandler.php" method="post" novalidate>
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

  <div id="resultContainer" aria-live="polite"></div>

  <script src="formSubmissionController.js" defer></script>
</body>
</html>
