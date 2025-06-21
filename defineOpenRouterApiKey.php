<?php
if (!defined('OPENROUTER_API_KEY')) {
    $envKey = getenv('OPENROUTER_API_KEY');
    if ($envKey !== false && $envKey !== '') {
        define('OPENROUTER_API_KEY', $envKey);
    } else {
        define('OPENROUTER_API_KEY', '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');
    }
}
