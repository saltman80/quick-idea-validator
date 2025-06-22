<?php
function parseAIResponse(array $apiResp): array {
    $content = trim($apiResp['choices'][0]['message']['content']);

    $verdict = '';
    if (preg_match('/Verdict[:\-]?\s*(YES|NO)/i', $content, $m)) {
        $verdict = strtoupper($m[1]);
    }

    $market = '';
    if (preg_match('/Market\s*Demand[:\-]?\s*(.+?)(?=\n\s*Key|\n\s*Validation|$)/is', $content, $m)) {
        $market = trim($m[1]);
    }

    $considerations = [];
    if (preg_match('/Key\s*Considerations[:\-]?\s*(.+?)(?=\n\s*Validation|$)/is', $content, $m)) {
        $considerations = array_values(array_filter(array_map('trim', preg_split('/(?:•|;|\r?\n)+/', $m[1]))));
    }

    $validation = [];
    if (preg_match('/Validation\s*Plan[:\-]?\s*(.+)$/is', $content, $m)) {
        $validation = array_values(array_filter(array_map('trim', preg_split('/(?:\d+\)|;|\r?\n)+/', $m[1]))));
    }

    return [
        'verdict'        => $verdict,
        'market_demand'  => $market,
        'considerations' => $considerations,
        'validation'     => $validation,
        'raw'            => $content,
    ];
}
