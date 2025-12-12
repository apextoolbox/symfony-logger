<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Throwable;

class ApexToolboxExceptionHandler
{
    /**
     * Log an exception
     */
    public static function logException(Throwable $exception): void
    {
        PayloadCollector::setException($exception);
    }
}