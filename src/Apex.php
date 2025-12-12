<?php

namespace ApexToolbox\SymfonyLogger;

use ApexToolbox\SymfonyLogger\Handler\ApexToolboxExceptionHandler;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Apex Toolbox Facade - Convenient exception logging
 */
class Apex
{
    private static ?LoggerInterface $logger = null;

    /**
     * Set the logger instance (called automatically by Symfony)
     */
    public static function setLogger(LoggerInterface $logger): void
    {
        self::$logger = $logger;
    }

    /**
     * Log an exception with optional context
     *
     * @param Throwable $exception The exception to log
     * @param array $context Additional context data
     * @return void
     */
    public static function logException(Throwable $exception, array $context = []): void
    {
        // Log exception for tracking
        ApexToolboxExceptionHandler::logException($exception);

        // Also log it via standard logger if available
        if (self::$logger) {
            $context['exception'] = $exception;
            self::$logger->error($exception->getMessage(), $context);
        }
    }

    /**
     * Manually track an HTTP request (use for non-Symfony HTTP clients)
     *
     * @param string $method HTTP method (GET, POST, etc.)
     * @param string $url Request URL
     * @param int|null $statusCode Response status code
     * @param float|null $durationMs Request duration in milliseconds
     * @return void
     */
    public static function trackHttpRequest(
        string $method,
        string $url,
        ?int $statusCode = null,
        ?float $durationMs = null
    ): void {
        PayloadCollector::addOutgoingRequest([
            'method' => strtoupper($method),
            'uri' => $url,
            'status_code' => $statusCode,
            'duration' => $durationMs,
            'timestamp' => date('c'),
        ]);
    }
}
