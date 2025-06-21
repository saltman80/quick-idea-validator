require_once __DIR__ . '/requestErrorLogManager.php';

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
    $logger = new RequestErrorLogManager();
    $apiKey = getenv('OPENROUTER_API_KEY');
    if (empty($apiKey)) {
        $logger->logError(500, 'Missing OpenRouter API key');
        throw new Exception('Missing OpenRouter API key.');
    }

    $url = 'https://openrouter.ai/v1/chat/completions';
    $payload = [
        'model'       => 'gpt-3.5-turbo',
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

function parseAIResponse(array $apiResp): array {
    $content = trim($apiResp['choices'][0]['message']['content']);

    // Split into verdict part and tips part
    $parts = preg_split('/\bTips[:\-]?\b/i', $content, 2);
    $verdictPart = $parts[0] ?? '';
    $tipsPart    = $parts[1] ?? '';

    // Extract verdict
    if (preg_match('/\bVerdict[:\-]?\s*(YES|NO)\b/i', $verdictPart, $m)) {
        $verdict = strtoupper($m[1]);
    } else {
        // Fallback to first non-empty line
        $lines = preg_split('/\r?\n/', trim($verdictPart));
        $verdict = strtoupper(trim($lines[0] ?? ''));
    }

    // Clean and split tips
    $tipsRaw = trim($tipsPart);
    $tipsArray = preg_split('/(?:;|\r?\n)+/', $tipsRaw);
    $tipsArray = array_filter(array_map('trim', $tipsArray));

    return [
        'verdict' => $verdict,
        'tips'    => array_values($tipsArray),
        'raw'     => $content,
    ];
}
