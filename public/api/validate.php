<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use QuickIdeaValidator\Logging\RequestErrorLogManager;

session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfHeader)) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Malformed request body']);
    exit;
}
$idea = sanitizeIdea($data['idea'] ?? '');
if ($idea === '') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid input']);
    exit;
}

try {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    enforceRateLimit($ip);

    $instruction = buildInstruction();
    $apiResp = callOpenRouterAPI($instruction, $idea);
    $parsed = parseAIResponse($apiResp);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'valid'    => strtoupper($parsed['verdict']) === 'YES',
        'feedback' => implode(' ', $parsed['tips']),
        'raw'      => $parsed['raw'],
    ]);
} catch (Throwable $e) {
    $logger = new RequestErrorLogManager();
    $logger->logError($e->getCode(), $e->getMessage(), [
        'payload' => $raw,
    ]);
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

