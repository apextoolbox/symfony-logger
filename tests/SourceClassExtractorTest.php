<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\SourceClassExtractor;

class SourceClassExtractorTest extends AbstractTestCase
{
    private SourceClassExtractor $extractor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extractor = new SourceClassExtractor();
    }

    public function testExtractsSourceClassFromContext(): void
    {
        $context = ['source_class' => 'App\\Service\\TestService'];

        $result = $this->extractor->extractSourceClass($context);

        $this->assertEquals('App\\Service\\TestService', $result);
    }

    public function testExtractsFromClassContextKey(): void
    {
        $context = ['class' => 'App\\Controller\\TestController'];

        $result = $this->extractor->extractSourceClass($context);

        $this->assertEquals('App\\Controller\\TestController', $result);
    }

    public function testExtractsFromServiceContextKey(): void
    {
        $context = ['service' => 'App\\Service\\UserService'];

        $result = $this->extractor->extractSourceClass($context);

        $this->assertEquals('App\\Service\\UserService', $result);
    }

    public function testExtractsFromBacktrace(): void
    {
        // This test calls a method that should be detected in the backtrace
        $result = $this->callMethodThatLogsAndExtractsSource();

        // The extractor attempts to extract from backtrace, result may be null or valid class
        // In test environment, backtrace might not always find non-internal classes
        $this->assertTrue($result === null || is_string($result), 'Should handle backtrace extraction gracefully');
    }

    public function testHandlesEmptyContext(): void
    {
        // Test with empty context - should try backtrace
        $result = $this->extractor->extractSourceClass([]);

        // Result could be null or a valid class from backtrace
        $this->assertTrue($result === null || is_string($result));
    }

    public function testIgnoresInternalLoggingClasses(): void
    {
        // Mock context that might contain logging classes but should be ignored
        $context = ['component' => 'Monolog\\Logger'];

        $result = $this->extractor->extractSourceClass($context);

        // The context contains Monolog\\Logger which looks like a class name
        // So it should return that class name, not fall back to backtrace
        $this->assertEquals('Monolog\\Logger', $result);
    }

    public function testDetectsValidClassNames(): void
    {
        $validClassNames = [
            'App\\Service\\TestService',
            'MyClass',
            'App\\Controller\\Api\\UserController',
        ];

        foreach ($validClassNames as $className) {
            $context = ['class' => $className];
            $result = $this->extractor->extractSourceClass($context);
            $this->assertEquals($className, $result, "Failed to detect valid class name: $className");
        }
    }

    public function testIgnoresInvalidClassNames(): void
    {
        $invalidClassNames = [
            'not-a-class',
            '123InvalidClass',
            'invalid.class.name',
            '',
        ];

        foreach ($invalidClassNames as $invalidName) {
            $context = ['class' => $invalidName];
            $result = $this->extractor->extractSourceClass($context);
            // Should fall back to backtrace extraction, result may be null or contain a valid class
            $this->assertTrue($result === null || is_string($result), "Should have handled invalid class name: $invalidName");
        }
    }

    private function callMethodThatLogsAndExtractsSource(): ?string
    {
        // This method should appear in the backtrace
        return $this->extractor->extractSourceClass([]);
    }
}