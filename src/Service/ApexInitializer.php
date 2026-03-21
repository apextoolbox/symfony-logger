<?php

namespace ApexToolbox\SymfonyLogger\Service;

use ApexToolbox\SymfonyLogger\Apex;
use Psr\Log\LoggerInterface;

/**
 * Initializes the Apex facade with required dependencies
 */
class ApexInitializer
{
    public function __construct(LoggerInterface $logger)
    {
        Apex::setLogger($logger);
    }
}
