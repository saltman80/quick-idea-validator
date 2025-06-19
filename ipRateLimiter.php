<?php

public static function enforceRateLimit(string $ip): void
{
    // Validate IP
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        throw new InvalidArgumentException('Invalid IP address');
    }

    $window = 60;

    // Use APCu if available for in-memory rate limiting
    if (function_exists('apcu_add')) {
        $key = 'rl_' . $ip;
        // apcu_add returns false if key already exists
        if (!@apcu_add($key, time(), $window)) {
            throw new RuntimeException('Too many requests');
        }
        return;
    }

    // Fallback to file-based rate limiting
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/requests.log';

    // Ensure log directory exists
    if (!is_dir($logDir) && !mkdir($logDir, 0755, true)) {
        throw new RuntimeException('Unable to create log directory');
    }

    // Ensure log file exists
    if (!file_exists($logFile) && !touch($logFile)) {
        throw new RuntimeException('Unable to create log file');
    }

    $now = time();
    $recentEntries = [];

    $fp = fopen($logFile, 'c+');
    if ($fp === false) {
        throw new RuntimeException('Unable to open log file');
    }

    try {
        if (!flock($fp, LOCK_EX)) {
            throw new RuntimeException('Unable to lock log file');
        }

        rewind($fp);
        while (($line = fgets($fp)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            [$timestamp, $entryIp] = array_pad(explode(',', $line, 2), 2, null);
            if (!ctype_digit($timestamp) || $entryIp === null) {
                continue;
            }
            $ts = (int)$timestamp;
            if ($now - $ts < $window) {
                $recentEntries[] = $ts . ',' . $entryIp . PHP_EOL;
                if ($entryIp === $ip) {
                    throw new RuntimeException('Too many requests');
                }
            }
        }

        // Append the current request
        $recentEntries[] = $now . ',' . $ip . PHP_EOL;

        // Truncate and rewrite only recent entries
        ftruncate($fp, 0);
        rewind($fp);
        foreach ($recentEntries as $entry) {
            fwrite($fp, $entry);
        }
        fflush($fp);
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
?>