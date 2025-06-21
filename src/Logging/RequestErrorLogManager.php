<?php

namespace QuickIdeaValidator\Logging;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

class RequestErrorLogManager
{
    private string $logFilePath;
    private int $maxFileSize;

    public function __construct(string $logDir = null, string $fileName = 'request_errors.log', int $maxFileSize = 5242880)
    {
        if ($logDir === null) {
            $logDir = __DIR__ . DIRECTORY_SEPARATOR . 'logs';
        }

        if (strpos($logDir, '..') !== false) {
            throw new InvalidArgumentException('Invalid log directory.');
        }

        $logDir = rtrim($logDir, DIRECTORY_SEPARATOR);

        if (!is_dir($logDir)) {
            if (!@mkdir($logDir, 0755, true)) {
                throw new RuntimeException("Failed to create log directory: {$logDir}");
            }
        }

        if (!is_writable($logDir)) {
            throw new RuntimeException("Log directory is not writable: {$logDir}");
        }

        $fileName = basename($fileName);
        if (!preg_match('/^[A-Za-z0-9._-]+$/', $fileName)) {
            throw new InvalidArgumentException('Invalid log file name.');
        }

        $this->logFilePath = $logDir . DIRECTORY_SEPARATOR . $fileName;
        $this->maxFileSize = $maxFileSize;
    }

    public function logError(int $code, string $message, array $context = []): void
    {
        $entry = [
            'timestamp'   => date('c'),
            'code'        => $code,
            'message'     => $message,
            'request_uri' => $context['request_uri'] ?? ($_SERVER['REQUEST_URI'] ?? ''),
            'method'      => $context['method'] ?? ($_SERVER['REQUEST_METHOD'] ?? ''),
            'ip'          => $context['ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? ''),
            'payload'     => $context['payload'] ?? null,
        ];

        try {
            $json = json_encode(
                $entry,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
        } catch (JsonException $e) {
            error_log('Failed to encode log entry: ' . $e->getMessage());
            return;
        }

        $this->writeLog($json . PHP_EOL);
    }

    private function writeLog(string $content): void
    {
        $lockFile = $this->logFilePath . '.lock';
        $lockFp = @fopen($lockFile, 'c');
        if ($lockFp === false) {
            error_log("Unable to open lock file: {$lockFile}");
            return;
        }

        if (!flock($lockFp, LOCK_EX)) {
            fclose($lockFp);
            error_log('Unable to acquire lock for log file.');
            return;
        }

        try {
            if (file_exists($this->logFilePath) && filesize($this->logFilePath) >= $this->maxFileSize) {
                $this->rotateLogInternal();
            }

            $fp = @fopen($this->logFilePath, 'a');
            if ($fp === false) {
                throw new RuntimeException("Unable to open log file: {$this->logFilePath}");
            }

            try {
                fwrite($fp, $content);
            } finally {
                fclose($fp);
            }
        } catch (Throwable $e) {
            error_log('Logging error: ' . $e->getMessage());
        } finally {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }

    private function rotateLogInternal(): void
    {
        $timestamp   = date('Ymd_His');
        $rotatedFile = $this->logFilePath . '.' . $timestamp;
        if (!@rename($this->logFilePath, $rotatedFile)) {
            throw new RuntimeException("Failed to rotate log file to: {$rotatedFile}");
        }
    }
}
