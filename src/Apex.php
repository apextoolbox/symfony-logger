<?php

namespace ApexToolbox\SymfonyLogger;

use Psr\Log\LoggerInterface;

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
     * Manually track an HTTP request (use for non-Symfony HTTP clients)
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
