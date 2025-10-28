<?php

namespace ApexToolbox\Symfony\Handler;

use ApexToolbox\Symfony\PayloadCollector;
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