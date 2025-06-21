<?php
require_once __DIR__ . '/promptApiProcessor.php';
require_once __DIR__ . '/ipRateLimiter.php';
require_once __DIR__ . '/requestErrorLogManager.php';
require_once __DIR__ . '/defineOpenRouterApiKey.php';

header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
$idea = sanitizeIdea($data['idea'] ?? '');
if ($idea === '') {
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
    http_response_code(500);
    echo json_encode(['error' => 'Server error']);
}

