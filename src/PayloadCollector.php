<?php

namespace ApexToolbox\SymfonyLogger;

use DateTime;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Throwable;

class PayloadCollector
{
    private static ?array $requestData = null;
    private static ?array $responseData = null;
    private static ?array $exceptionData = null;
    private static array $logs = [];
    private static array $metadata = [];
    private static bool $sent = false;
    private static array $config = [];

    /**
     * Initialize configuration
     */
    public static function configure(array $config): void
    {
        static::$config = $config;
    }

    /**
     * Collect request and response data
     */
    public static function collect(Request $request, ?Response $response, float $startTime, ?float $endTime = null): void
    {
        if (!static::isEnabled()) {
            return;
        }

        $endTime = $endTime ?: microtime(true);

        static::$requestData = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => static::filterHeaders($request->headers->all()),
            'payload' => static::filterBody($request->request->all()),
            'ip_address' => static::getRealIpAddress($request),
        ];

        static::$responseData = [
            'status_code' => $response ? $response->getStatusCode() : null,
            'response' => $response ? static::getResponseContent($response) : null,
            'duration' => round(($endTime - $startTime) * 1000),
        ];

        static::$metadata['start_time'] = $startTime;
        static::$metadata['end_time'] = $endTime;
        static::$metadata['timestamp'] = (new DateTime())->format('c');
    }

    /**
     * Add log entry
     */
    public static function addLog(array $logData): void
    {
        if (!static::isEnabled()) {
            return;
        }

        static::$logs[] = $logData;
    }

    /**
     * Set exception data
     */
    public static function setException(Throwable $exception): void
    {
        if (!static::isEnabled()) {
            return;
        }

        static::$exceptionData = static::parseException($exception);
    }

    /**
     * Send collected data
     */
    public static function send(): void
    {
        if (!static::isEnabled() || static::$sent) {
            return;
        }

        // Don't send if no meaningful data collected
        if (!static::$requestData && !static::$exceptionData && empty(static::$logs)) {
            return;
        }

        try {
            $payload = static::buildPayload();
            static::sendPayload($payload);
            static::$sent = true;
        } catch (Throwable $e) {
            // Silently fail to prevent infinite loops
        }
    }

    /**
     * Clear collected data (for next request)
     */
    public static function clear(): void
    {
        static::$requestData = null;
        static::$responseData = null;
        static::$exceptionData = null;
        static::$logs = [];
        static::$metadata = [];
        static::$sent = false;
    }

    /**
     * Get current logs (for testing)
     */
    public static function getLogs(): array
    {
        return static::$logs;
    }

    /**
     * Check if logging is enabled
     */
    private static function isEnabled(): bool
    {
        return (static::$config['enabled'] ?? true) && !empty(static::$config['token']);
    }

    /**
     * Build unified payload
     */
    private static function buildPayload(): array
    {
        $payload = [
            'timestamp' => static::$metadata['timestamp'] ?? (new \DateTime())->format('c'),
        ];

        // Only add logs if we have some
        if (!empty(static::$logs)) {
            $payload['logs_trace_id'] = Uuid::v7()->toRfc4122();
            $payload['logs'] = static::$logs;
        }

        // Add request data if available
        if (static::$requestData) {
            $payload = array_merge($payload, static::$requestData);
        }

        // Add response data if available
        if (static::$responseData) {
            $payload = array_merge($payload, static::$responseData);
        }

        // Add exception data if available
        if (static::$exceptionData) {
            $payload['exception'] = static::$exceptionData;
        }

        return $payload;
    }

    /**
     * Send payload to endpoint
     */
    private static function sendPayload(array $payload): void
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => static::getEndpointUrl(),
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . static::$config['token'],
                'Content-Type: application/json',
            ],
            CURLOPT_TIMEOUT => 2,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Get endpoint URL
     */
    private static function getEndpointUrl(): string
    {
        if (!empty($_ENV['APEX_TOOLBOX_DEV_ENDPOINT'])) {
            return $_ENV['APEX_TOOLBOX_DEV_ENDPOINT'];
        }

        return 'https://apextoolbox.com/api/v1/logs';
    }

    /**
     * Parse exception into structured data
     */
    private static function parseException(Throwable $exception): array
    {
        // Add the exception throwing location as the first frame
        $trace = $exception->getTrace();

        // Get method info from the first trace frame (if available)
        $firstFrame = $trace[0] ?? [];

        array_unshift($trace, [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'function' => $firstFrame['function'] ?? 'unknown',
            'class' => $firstFrame['class'] ?? '',
            'type' => $firstFrame['type'] ?? '',
            'args' => []
        ]);

        return [
            'hash' => static::generateExceptionHash($exception),
            'message' => $exception->getMessage(),
            'class' => get_class($exception),
            'file_path' => str_replace(getcwd() . DIRECTORY_SEPARATOR, '', $exception->getFile()),
            'line_number' => $exception->getLine(),
            'code' => $exception->getCode(),
            'stack_trace' => static::prepareStackTrace($trace),
            'timestamp' => (new \DateTime())->format('c'),
            'context' => [
                'environment' => $_ENV['APP_ENV'] ?? 'prod',
                'php_version' => PHP_VERSION,
                'symfony_version' => \Symfony\Component\HttpKernel\Kernel::VERSION,
            ],
        ];
    }

    /**
     * Generate unique hash for exception grouping
     */
    private static function generateExceptionHash(Throwable $exception): string
    {
        $key = $exception->getFile() . ':' . $exception->getLine() . ':' . get_class($exception);
        return hash('sha256', $key);
    }

    /**
     * Prepare stack trace with code context
     */
    private static function prepareStackTrace(array $trace): array
    {
        $basePath = getcwd();
        $frames = [];

        foreach ($trace as $entry) {
            if (!isset($entry['file'])) continue;

            // Remove args to avoid sensitive data
            unset($entry['args']);

            $frame = [
                'file' => str_replace($basePath . DIRECTORY_SEPARATOR, '', $entry['file']),
                'line' => $entry['line'] ?? 0,
                'function' => $entry['function'] ?? '',
                'class' => $entry['class'] ?? '',
                'in_app' => static::isAppCode($entry['file']),
                'code_context' => static::extractCodeContext($entry['file'], $entry['line'] ?? 0)
            ];

            $frames[] = $frame;
        }

        return $frames;
    }

    /**
     * Detect if file is application code (not vendor)
     */
    private static function isAppCode(string $filePath): bool
    {
        // Normalize path separators for cross-platform compatibility
        $normalizedPath = str_replace('\\', '/', $filePath);

        // Check if path contains /vendor/
        return !str_contains($normalizedPath, '/vendor/');
    }

    /**
     * Extract code context around a specific line
     */
    private static function extractCodeContext(string $file, int $line): ?array
    {
        if (!file_exists($file) || !is_readable($file)) {
            return null;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES);
        if (!$lines) return null;

        $startLine = max(1, $line - 10);
        $endLine = min(count($lines), $line + 5);

        $context = [];
        for ($i = $startLine; $i <= $endLine; $i++) {
            $code = $lines[$i - 1] ?? '';
            $code = str_replace(["\t", " "], ["&#9;", "&#32;"], $code);

            $context[] = [
                'line_number' => $i,
                'code' => $code,
                'is_error_line' => $i === $line,
            ];
        }

        return [
            'lines' => $context,
            'context_start' => $startLine,
            'context_end' => $endLine
        ];
    }

    /**
     * Filter headers to exclude sensitive data
     */
    private static function filterHeaders(array $headers): array
    {
        $excludeFields = static::$config['headers']['exclude'] ?? [];

        return static::recursivelyFilterSensitiveData($headers, $excludeFields);
    }

    /**
     * Filter body to exclude sensitive data
     */
    private static function filterBody(array $body): array
    {
        $excludeFields = static::$config['body']['exclude'] ?? [];
        $maskFields = static::$config['body']['mask'] ?? [];

        return static::recursivelyFilterSensitiveData($body, $excludeFields, $maskFields);
    }

    /**
     * Get response content with filtering
     */
    private static function getResponseContent(Response $response): false|array|string
    {
        $content = $response->getContent();

        if ($response->headers->get('content-type') &&
            str_contains($response->headers->get('content-type'), 'application/json')) {

            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $excludeFields = static::$config['response']['exclude'] ?? [];
                $maskFields = static::$config['response']['mask'] ?? [];

                return static::recursivelyFilterSensitiveData($decoded, $excludeFields, $maskFields);
            }
        }

        // Truncate large non-JSON content
        if (strlen($content) > 10000) {
            $content = substr($content, 0, 10000) . '... [truncated]';
        }

        return $content;
    }

    /**
     * Get real IP address from request
     */
    private static function getRealIpAddress(Request $request): ?string
    {
        $headers = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED',
                   'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED'];

        foreach ($headers as $header) {
            if ($request->server->get($header)) {
                $ips = explode(',', $request->server->get($header));
                $ip = trim($ips[0]);

                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        return $request->getClientIp();
    }

    /**
     * Recursively filter sensitive data
     */
    private static function recursivelyFilterSensitiveData(
        array $data,
        array $excludeFields,
        array $maskFields = [],
        string $maskValue = '*******'
    ): array {
        $filtered = [];

        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);

            if (in_array($keyLower, array_map('strtolower', $excludeFields))) {
                continue;
            }

            if (in_array($keyLower, array_map('strtolower', $maskFields))) {
                $filtered[$key] = $maskValue;
                continue;
            }

            if (is_array($value)) {
                $filtered[$key] = static::recursivelyFilterSensitiveData($value, $excludeFields, $maskFields, $maskValue);
            } else {
                $filtered[$key] = $value;
            }
        }

        return $filtered;
    }
}