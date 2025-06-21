<?php
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
