<?php
if (!defined('OPENROUTER_API_KEY')) {
    $envKey = getenv('OPENROUTER_API_KEY');
    if ($envKey !== false && $envKey !== '') {
        define('OPENROUTER_API_KEY', $envKey);
    }
}
