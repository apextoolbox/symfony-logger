<?php

namespace ApexToolbox\Symfony\Tests\DependencyInjection;

use ApexToolbox\Symfony\DependencyInjection\Configuration;
use ApexToolbox\Symfony\Tests\AbstractTestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends AbstractTestCase
{
    private Configuration $configuration;
    private Processor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configuration = new Configuration();
        $this->processor = new Processor();
    }

    public function testGetConfigTreeBuilder(): void
    {
        $treeBuilder = $this->configuration->getConfigTreeBuilder();
        
        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }

    public function testDefaultConfiguration(): void
    {
        $config = $this->processor->processConfiguration($this->configuration, []);
        
        $this->assertTrue($config['enabled']);
        $this->assertEquals('', $config['token']);
        $this->assertEquals(['api/*'], $config['path_filters']['include']);
        $this->assertEquals(['api/health', 'api/ping'], $config['path_filters']['exclude']);
        $this->assertFalse($config['headers']['include_sensitive']);
        $this->assertEquals(['authorization', 'x-api-key', 'cookie'], $config['headers']['exclude']);
        $this->assertEquals(10240, $config['body']['max_size']);
        $this->assertEquals(['password', 'password_confirmation', 'token', 'secret'], $config['body']['exclude']);
        $this->assertFalse($config['universal_logging']['enabled']);
        $this->assertEquals(['http', 'console', 'queue'], $config['universal_logging']['types']);
    }

    public function testCustomConfiguration(): void
    {
        $customConfig = [
            'apex_toolbox' => [
                'enabled' => false,
                'token' => 'custom-token',
                'path_filters' => [
                    'include' => ['webhook/*'],
                    'exclude' => ['webhook/health']
                ],
                'headers' => [
                    'include_sensitive' => true,
                    'exclude' => ['custom-header']
                ],
                'body' => [
                    'max_size' => 5120,
                    'exclude' => ['secret_field']
                ],
                'universal_logging' => [
                    'enabled' => true,
                    'types' => ['console']
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);
        
        $this->assertFalse($config['enabled']);
        $this->assertEquals('custom-token', $config['token']);
        $this->assertEquals(['webhook/*'], $config['path_filters']['include']);
        $this->assertEquals(['webhook/health'], $config['path_filters']['exclude']);
        $this->assertTrue($config['headers']['include_sensitive']);
        $this->assertEquals(['custom-header'], $config['headers']['exclude']);
        $this->assertEquals(5120, $config['body']['max_size']);
        $this->assertEquals(['secret_field'], $config['body']['exclude']);
        $this->assertTrue($config['universal_logging']['enabled']);
        $this->assertEquals(['console'], $config['universal_logging']['types']);
    }

    public function testPartialConfiguration(): void
    {
        $partialConfig = [
            'apex_toolbox' => [
                'token' => 'partial-token',
                'path_filters' => [
                    'include' => ['custom/*']
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $partialConfig);
        
        // Should have defaults for unspecified values
        $this->assertTrue($config['enabled']); // default
        $this->assertEquals('partial-token', $config['token']); // custom
        $this->assertEquals(['custom/*'], $config['path_filters']['include']); // custom
        $this->assertEquals(['api/health', 'api/ping'], $config['path_filters']['exclude']); // default
    }

    public function testEmptyArraysAreValid(): void
    {
        $configWithEmptyArrays = [
            'apex_toolbox' => [
                'path_filters' => [
                    'include' => [],
                    'exclude' => []
                ],
                'headers' => [
                    'exclude' => []
                ],
                'body' => [
                    'exclude' => []
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $configWithEmptyArrays);
        
        $this->assertEquals([], $config['path_filters']['include']);
        $this->assertEquals([], $config['path_filters']['exclude']);
        $this->assertEquals([], $config['headers']['exclude']);
        $this->assertEquals([], $config['body']['exclude']);
    }

    public function testUniversalLoggingConfiguration(): void
    {
        $universalConfig = [
            'apex_toolbox' => [
                'universal_logging' => [
                    'enabled' => true,
                    'types' => ['http', 'queue']
                ]
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $universalConfig);
        
        $this->assertTrue($config['universal_logging']['enabled']);
        $this->assertEquals(['http', 'queue'], $config['universal_logging']['types']);
    }
}