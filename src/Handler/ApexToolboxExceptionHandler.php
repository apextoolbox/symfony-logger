<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Throwable;

class ApexToolboxExceptionHandler
{
    /**
     * Capture an exception
     */
    public static function capture(Throwable $exception): void
    {
        PayloadCollector::setException($exception);
    }
}