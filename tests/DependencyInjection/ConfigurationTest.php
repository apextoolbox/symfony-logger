<?php

namespace ApexToolbox\SymfonyLogger\Tests\DependencyInjection;

use ApexToolbox\SymfonyLogger\DependencyInjection\Configuration;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
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
        $this->assertEquals(['*'], $config['path_filters']['include']);
        $this->assertEquals(['_debugbar/*', 'telescope/*', 'horizon/*', 'api/health', 'api/ping'], $config['path_filters']['exclude']);
        $this->assertEquals(
            ['authorization', 'x-api-key', 'cookie', 'x-auth-token', 'x-access-token', 'x-refresh-token', 'bearer', 'x-secret', 'x-private-key', 'authentication'],
            $config['headers']['exclude']
        );
        $this->assertArrayNotHasKey('include_sensitive', $config['headers']);
        $this->assertArrayNotHasKey('max_size', $config['body']);
        $this->assertArrayNotHasKey('universal_logging', $config);
        $this->assertEquals(
            ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'private_key', 'auth', 'authorization', 'social_security', 'credit_card', 'card_number', 'cvv', 'pin', 'otp'],
            $config['body']['exclude']
        );
        $this->assertEquals(
            ['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'private_key', 'auth', 'authorization', 'social_security', 'credit_card', 'card_number', 'cvv', 'pin', 'otp'],
            $config['response']['exclude']
        );
    }

    public function testCustomConfiguration(): void
    {
        $customConfig = [
            'apextoolbox' => [
                'enabled' => false,
                'token' => 'custom-token',
                'path_filters' => [
                    'include' => ['webhook/*'],
                    'exclude' => ['webhook/health']
                ],
                'headers' => [
                    'exclude' => ['custom-header']
                ],
                'body' => [
                    'exclude' => ['secret_field']
                ],
            ]
        ];

        $config = $this->processor->processConfiguration($this->configuration, $customConfig);

        $this->assertFalse($config['enabled']);
        $this->assertEquals('custom-token', $config['token']);
        $this->assertEquals(['webhook/*'], $config['path_filters']['include']);
        $this->assertEquals(['webhook/health'], $config['path_filters']['exclude']);
        $this->assertEquals(['custom-header'], $config['headers']['exclude']);
        $this->assertEquals(['secret_field'], $config['body']['exclude']);
    }

    public function testPartialConfiguration(): void
    {
        $partialConfig = [
            'apextoolbox' => [
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
        $this->assertEquals(['_debugbar/*', 'telescope/*', 'horizon/*', 'api/health', 'api/ping'], $config['path_filters']['exclude']); // default
    }

    public function testEmptyArraysAreValid(): void
    {
        $configWithEmptyArrays = [
            'apextoolbox' => [
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
}
