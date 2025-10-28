<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\ApexToolboxBundle;
use ApexToolbox\SymfonyLogger\DependencyInjection\ApexToolboxExtension;

class ApexToolboxBundleTest extends AbstractTestCase
{
    public function testGetContainerExtension(): void
    {
        $bundle = new ApexToolboxBundle();
        $extension = $bundle->getContainerExtension();

        $this->assertInstanceOf(ApexToolboxExtension::class, $extension);
    }

    public function testGetContainerExtensionReturnsSameInstance(): void
    {
        $bundle = new ApexToolboxBundle();
        $extension1 = $bundle->getContainerExtension();
        $extension2 = $bundle->getContainerExtension();

        $this->assertSame($extension1, $extension2);
    }

    public function testBundleCanBeInstantiated(): void
    {
        $bundle = new ApexToolboxBundle();

        $this->assertInstanceOf(ApexToolboxBundle::class, $bundle);
    }
}