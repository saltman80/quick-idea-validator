<?php
if (!defined('OPENROUTER_API_KEY')) {
    try {
        // 1. Retrieve API key from environment variables
        $key = getenv('OPENROUTER_API_KEY');
        if ($key === false || $key === '') {
            $key = $_ENV['OPENROUTER_API_KEY'] ?? $_SERVER['OPENROUTER_API_KEY'] ?? null;
        }

        // 2. If not found in environment, load from config.json
        if (empty($key)) {
            $configPath = __DIR__ . '/config.json';
            if (!is_readable($configPath)) {
                throw new Exception("Configuration file not found at {$configPath}");
            }
            $content = file_get_contents($configPath);
            $config = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON in configuration file: ' . json_last_error_msg());
            }
            if (empty($config['openrouter_api_key'])) {
                throw new Exception('Missing openrouter_api_key in configuration file');
            }
            $key = $config['openrouter_api_key'];
        }

        $key = trim($key);

        // 3. Validate API key format (starts with 'orkey-' followed by 10?120 valid chars)
        if (!preg_match('/^orkey-[A-Za-z0-9_-]{10,120}$/', $key)) {
            throw new Exception('OpenRouter API key does not match expected format');
        }

        // 4. Define constant for use by caller
        define('OPENROUTER_API_KEY', $key);

        // 5. Sync environment so consumers using getenv() get the same key
        putenv('OPENROUTER_API_KEY=' . $key);
        $_ENV['OPENROUTER_API_KEY'] = $key;
        $_SERVER['OPENROUTER_API_KEY'] = $key;

    } catch (Exception $e) {
        // Log detailed error server-side
        error_log('[OpenRouterConfig] ' . $e->getMessage());
        // Throw generic exception for caller to handle HTTP response or other logic
        throw new RuntimeException('Failed to load OpenRouter API key');
    }
}
