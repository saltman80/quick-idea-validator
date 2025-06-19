<?php
// Configuration
define('ENV_FILE', __DIR__ . '/.env');
define('RATE_LIMIT_FILE', __DIR__ . '/data/rate_limit.json');
define('RATE_LIMIT_SECONDS', 60);
define('MODEL', 'gpt-3.5-turbo');
define('OPENROUTER_ENDPOINT', 'https://api.openrouter.ai/v1/chat/completions');

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
    <style>
        body { font-family: Arial, sans-serif; background: #f4f4f4; margin: 0; padding: 20px; }
        #container { max-width: 600px; margin: auto; background: #fff; padding: 20px; border-radius: 5px; }
        textarea { width: 100%; height: 100px; padding: 10px; margin-bottom: 10px; resize: vertical; }
        button { padding: 10px 20px; background: #007BFF; color: #fff; border: none; cursor: pointer; border-radius: 3px; }
        button:disabled { background: #aaa; cursor: not-allowed; }
        #result { margin-top: 20px; font-size: 1.2em; }
    </style>
</head>
<body>
<div id="container">
    <h1>Quick Idea Validator</h1>
    <p>Enter your app or startup idea and we'll tell you if it's viable.</p>
    <textarea id="idea" placeholder="Describe your idea..."></textarea>
    <button id="submit">Validate Idea</button>
    <div id="result"></div>
</div>
<script>
document.getElementById('submit').addEventListener('click', function(){
    var btn = this;
    var idea = document.getElementById('idea').value.trim();
    var resultDiv = document.getElementById('result');
    resultDiv.textContent = '';
    if (!idea) { resultDiv.textContent = 'Please enter an idea.'; return; }
    btn.disabled = true;
    resultDiv.textContent = 'Validating...';
    fetch('', {
        method:'POST',
        headers: {'Content-Type':'application/json','X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify({idea: idea})
    })
    .then(function(res){
        if (!res.ok) throw res;
        return res.json();
    })
    .then(function(data){
        if (data.success) {
            resultDiv.textContent = 'Verdict: ' + data.verdict + (data.message ? ' ('+data.message+')' : '');
        } else {
            resultDiv.textContent = 'Error: ' + data.error;
        }
    })
    .catch(function(err){
        if (err.json) {
            err.json().then(function(e){ resultDiv.textContent = 'Error: ' + (e.error||'Unknown'); });
        } else {
            resultDiv.textContent = 'Network error.';
        }
    })
    .finally(function(){
        btn.disabled = false;
    });
});
</script>
</body>
</html>