<?php

use ApexToolbox\SymfonyLogger\Apex;

if (!function_exists('logException')) {
    /**
     * Log an exception with optional context
     *
     * @param Throwable $exception The exception to log
     * @param array $context Additional context data
     * @return void
     */
    function logException(Throwable $exception, array $context = []): void
    {
        Apex::logException($exception, $context);
    }
}
