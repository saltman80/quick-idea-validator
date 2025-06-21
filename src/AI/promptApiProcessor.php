<?php
use QuickIdeaValidator\Logging\RequestErrorLogManager;

function sanitizeIdea(string $idea): string {
    $clean = strip_tags($idea);
    $clean = trim($clean);
    $maxLen = 500;
    if (mb_strlen($clean) > $maxLen) {
        $clean = mb_substr($clean, 0, $maxLen);
    }
    return $clean;
}

function buildInstruction(): string {
    return
        "You are an AI that evaluates business or startup ideas for viability. " .
        "Provide a clear verdict of YES or NO, followed by brief actionable tips. " .
        "Respond in the following format:\n" .
        "Verdict: YES or NO\n" .
        "Tips: tip1; tip2; tip3\n\n" .
        "Now evaluate the user's idea.";
}

function callOpenRouterAPI(string $instruction, string $idea): array {
    // point the logger at your project-root "logs" folder
    $logger = new RequestErrorLogManager(
        dirname(__DIR__, 2) . '/logs',
        'request_errors.log'
    );
    // hard-code your key for this demo:
    $apiKey = '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';

    $url = 'https://openrouter.ai/api/v1/chat/completions';
    $payload = [
        'model'       => 'openai/gpt-4o-mini',
        'messages'    => [
            ['role' => 'system', 'content' => $instruction],
            ['role' => 'user',   'content' => $idea],
        ],
        'temperature' => 0.7,
        'max_tokens'  => 300,
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_POST            => true,
        CURLOPT_HTTPHEADER      => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS      => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT  => 5,
        CURLOPT_TIMEOUT         => 30,
    ]);

    // Optional debug line to confirm the authorization header is set correctly
    error_log('AUTH HEADER → Authorization: Bearer ' . $apiKey);

    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        $logger->logError(0, 'cURL error: ' . $err, [ 'payload' => $payload ]);
        curl_close($ch);
        throw new Exception('cURL error: ' . $err);
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        $logger->logError($httpCode, 'Invalid JSON response from API', [
            'payload'  => $payload,
            'response' => $response,
        ]);
        throw new Exception('Invalid JSON response from API: ' . json_last_error_msg());
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        $msg = $data['error']['message'] ?? 'API request failed with status ' . $httpCode;
        $logger->logError($httpCode, 'API error: ' . $msg, [
            'payload'  => $payload,
            'response' => $response,
        ]);
        throw new Exception('API error: ' . $msg);
    }

    if (!isset($data['choices'][0]['message']['content'])) {
        $logger->logError($httpCode, 'Unexpected API response format', [
            'payload'  => $payload,
            'response' => $response,
        ]);
        throw new Exception('Unexpected API response format.');
    }

    return $data;
}

