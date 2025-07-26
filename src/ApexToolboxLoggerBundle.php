<?php

namespace ApexToolbox\SymfonyLogger;

use ApexToolbox\SymfonyLogger\DependencyInjection\ApexToolboxLoggerExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ApexToolboxLoggerBundle extends Bundle
{
    public function getContainerExtension(): ApexToolboxLoggerExtension
    {
        if (null === $this->extension) {
            $this->extension = new ApexToolboxLoggerExtension();
        }

        return $this->extension;
    }
}