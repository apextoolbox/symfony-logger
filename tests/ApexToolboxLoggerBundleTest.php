<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\ApexToolboxLoggerBundle;
use ApexToolbox\SymfonyLogger\DependencyInjection\ApexToolboxLoggerExtension;

class ApexToolboxLoggerBundleTest extends AbstractTestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new ApexToolboxLoggerBundle();
        $extension = $bundle->getContainerExtension();
        
        $this->assertInstanceOf(ApexToolboxLoggerExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $bundle = new ApexToolboxLoggerBundle();
        $extension1 = $bundle->getContainerExtension();
        $extension2 = $bundle->getContainerExtension();
        
        $this->assertSame($extension1, $extension2);
    }

    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new ApexToolboxLoggerBundle();
        
        $this->assertInstanceOf(ApexToolboxLoggerBundle::class, $bundle);
    }
}