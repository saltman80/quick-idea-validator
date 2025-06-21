<?php
require __DIR__ . '/../../vendor/autoload.php';
use QuickIdeaValidator\Logging\RequestErrorLogManager;


session_start();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
        throw new RuntimeException('Invalid CSRF token', 403);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new RuntimeException('Malformed request body', 400);
    }

    $idea = sanitizeIdea($data['idea'] ?? '');
    if ($idea === '') {
        throw new RuntimeException('Invalid input', 400);
    }

    if (!defined('OPENROUTER_API_KEY') || OPENROUTER_API_KEY === '') {
        throw new RuntimeException('Missing API key', 500);
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    enforceRateLimit($ip);

    $instruction = buildInstruction();
    $apiResp     = callOpenRouterAPI($instruction, $idea);
    $parsed      = parseAIResponse($apiResp);

    echo json_encode([
        'valid'    => strtoupper($parsed['verdict']) === 'YES',
        'feedback' => implode(' ', $parsed['tips']),
        'raw'      => $parsed['raw'],
    ]);
} catch (Throwable $e) {
    $errorId = bin2hex(random_bytes(4));
    $logger  = new RequestErrorLogManager(dirname(__DIR__, 3) . '/logs', 'request_errors.log');
    $logger->logError(
        $e->getCode(),
        $e->getMessage() . "\n" . $e->getTraceAsString(),
        [
            'payload'  => $raw ?? '',
            'error_id' => $errorId,
        ]
    );

    $code = $e->getCode();
    if ($e->getMessage() === 'Too many requests') {
        $code = 429;
    }
    if ($code < 400 || $code >= 600) {
        if (strpos($e->getMessage(), 'Invalid CSRF token') !== false) {
            $code = 403;
        } elseif (strpos($e->getMessage(), 'Malformed request body') !== false || strpos($e->getMessage(), 'Invalid input') !== false) {
            $code = 400;
        } elseif (strpos($e->getMessage(), 'Missing API key') !== false) {
            $code = 500;
        } else {
            $code = 500;
        }
    }

    http_response_code($code);
    $message = getenv('APP_ENV') === 'dev' ? $e->getMessage() : 'Server error';
    echo json_encode(['error' => $message, 'id' => $errorId]);
}

