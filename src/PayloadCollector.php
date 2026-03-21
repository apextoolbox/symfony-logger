<?php

namespace ApexToolbox\SymfonyLogger;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Ramsey\Uuid\Uuid;
use Throwable;

class PayloadCollector
{
    private static ?string $requestId = null;
    private static ?array $incomingRequest = null;
    private static array $logs = [];
    private static array $outgoingRequests = [];
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
     * Set request ID (generated at request start)
     */
    public static function setRequestId(string $requestId): void
    {
        static::$requestId = $requestId;
    }

    /**
     * Get current request ID
     */
    public static function getRequestId(): ?string
    {
        return static::$requestId;
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

        static::$incomingRequest = [
            'method' => $request->getMethod(),
            'uri' => $request->getRequestUri(),
            'headers' => static::filterHeaders($request->headers->all()),
            'payload' => static::filterBody($request->request->all()),
            'ip_address' => static::getRealIpAddress($request),
            'user_agent' => $request->headers->get('User-Agent'),
            'status_code' => $response ? $response->getStatusCode() : null,
            'response' => $response ? static::getResponseContent($response) : null,
            'duration' => round(($endTime - $startTime) * 1000),
        ];
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
     * Add outgoing HTTP request
     */
    public static function addOutgoingRequest(array $requestData): void
    {
        if (!static::isEnabled()) {
            return;
        }

        static::$outgoingRequests[] = $requestData;
    }

    /**
     * Get outgoing requests (for testing)
     */
    public static function getOutgoingRequests(): array
    {
        return static::$outgoingRequests;
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
        if (!static::$incomingRequest && empty(static::$logs) && empty(static::$outgoingRequests)) {
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
        static::$requestId = null;
        static::$incomingRequest = null;
        static::$logs = [];
        static::$outgoingRequests = [];
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

    private static function buildPayload(): array
    {
        $payload = [];

        $payload['trace_id'] = static::$requestId ?? Uuid::uuid7()->toString();

        if (static::$incomingRequest) {
            $payload['request'] = static::$incomingRequest;
        }

        // Add logs if we have some
        if (!empty(static::$logs)) {
            $payload['logs'] = static::$logs;
        }

        // Add outgoing HTTP requests if we have some
        if (!empty(static::$outgoingRequests)) {
            $payload['outgoing_requests'] = static::$outgoingRequests;
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
            CURLOPT_URL => 'https://apextoolbox.com/api/v1/logs',
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . static::$config['token'],
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOSIGNAL => 1,
        ]);

        curl_exec($ch);
        curl_close($ch);
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
     * Recursively filter sensitive data (public alias for use by TrackedHttpClient)
     */
    public static function recursivelyFilter(
        array $data,
        array $excludeFields,
        array $maskFields = [],
        string $maskValue = '*******'
    ): array {
        return static::recursivelyFilterSensitiveData($data, $excludeFields, $maskFields, $maskValue);
    }

    private static function recursivelyFilterSensitiveData(
        array $data,
        array $excludeFields,
        array $maskFields = [],
        string $maskValue = '*******'
    ): array {
        $filtered = [];
        $excludeFieldsLower = array_map('strtolower', $excludeFields);
        $maskFieldsLower = array_map('strtolower', $maskFields);

        foreach ($data as $key => $value) {
            $keyLower = strtolower($key);

            if (in_array($keyLower, $excludeFieldsLower)) {
                continue;
            }

            if (in_array($keyLower, $maskFieldsLower)) {
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
