<?php

namespace ApexToolbox\SymfonyLogger\Tests\DependencyInjection;

use ApexToolbox\SymfonyLogger\DependencyInjection\ApexToolboxLoggerExtension;
use ApexToolbox\SymfonyLogger\EventListener\LoggerListener;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ApexToolboxLoggerExtensionTest extends AbstractTestCase
{
    private ApexToolboxLoggerExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new ApexToolboxLoggerExtension();
        $this->container = new ContainerBuilder();
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('apex_toolbox_logger', $this->extension->getAlias());
    }

    public function testLoadSetsDefaultConfiguration(): void
    {
        $this->extension->load([], $this->container);
        
        $this->assertTrue($this->container->hasParameter('apex_toolbox_logger'));
        
        $config = $this->container->getParameter('apex_toolbox_logger');
        $this->assertTrue($config['enabled']);
        $this->assertEquals('', $config['token']);
        $this->assertEquals(['api/*'], $config['path_filters']['include']);
    }

    public function testLoadWithCustomConfiguration(): void
    {
        $configs = [
            [
                'enabled' => false,
                'token' => 'test-token',
                'path_filters' => [
                    'include' => ['webhook/*']
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);
        
        $config = $this->container->getParameter('apex_toolbox_logger');
        $this->assertFalse($config['enabled']);
        $this->assertEquals('test-token', $config['token']);
        $this->assertEquals(['webhook/*'], $config['path_filters']['include']);
    }

    public function testLoadRegistersEventListeners(): void
    {
        $this->extension->load([], $this->container);
        
        $this->assertTrue($this->container->hasDefinition(LoggerListener::class));
        
        // Check that LoggerListener service is tagged as event subscriber
        $loggerListenerDef = $this->container->getDefinition(LoggerListener::class);
        
        $this->assertTrue($loggerListenerDef->hasTag('kernel.event_subscriber'));
    }

    public function testLoadWithMultipleConfigs(): void
    {
        $configs = [
            [
                'enabled' => true,
                'token' => 'first-token'
            ],
            [
                'token' => 'second-token',
                'path_filters' => [
                    'include' => ['api/v2/*']
                ]
            ]
        ];

        $this->extension->load($configs, $this->container);
        
        $config = $this->container->getParameter('apex_toolbox_logger');
        // Last config should override
        $this->assertEquals('second-token', $config['token']);
        $this->assertEquals(['api/v2/*'], $config['path_filters']['include']);
    }
}