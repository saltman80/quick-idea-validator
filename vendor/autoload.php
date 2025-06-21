<?php
spl_autoload_register(function ($class) {
    $prefix = 'QuickIdeaValidator\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

foreach ([
    __DIR__ . '/../src/AI/promptApiProcessor.php',
    __DIR__ . '/../src/RateLimit/ipRateLimiter.php',
    __DIR__ . '/../defineOpenRouterApiKey.php'
] as $file) {
    if (is_file($file)) {
        require_once $file;
    }
}

