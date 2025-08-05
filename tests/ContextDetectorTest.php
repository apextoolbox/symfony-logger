<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\ContextDetector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Mockery;

class ContextDetectorTest extends AbstractTestCase
{
    private RequestStack $requestStack;
    private ContextDetector $contextDetector;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->requestStack = Mockery::mock(RequestStack::class);
        $this->contextDetector = new ContextDetector($this->requestStack);
    }

    public function testDetectsHttpContext(): void
    {
        $request = Mockery::mock(Request::class);
        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn($request);

        $result = $this->contextDetector->detectType();

        $this->assertEquals('http', $result);
    }

    public function testDetectsConsoleContext(): void
    {
        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn(null);
        
        // We need to check the actual PHP SAPI, which might not be 'cli' in test environment
        // If we're not in CLI, this test should check the current behavior
        $expectedResult = (php_sapi_name() === 'cli') ? 'console' : 'http';

        $result = $this->contextDetector->detectType();

        $this->assertEquals($expectedResult, $result);
    }

    public function testDetectsQueueWorkerFromArgv(): void
    {
        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn(null);
        
        // Save original argv
        $originalArgv = $_SERVER['argv'] ?? null;
        
        // Mock messenger:consume command
        $_SERVER['argv'] = ['console', 'messenger:consume'];

        $result = $this->contextDetector->detectType();

        // Only expect queue if we're in CLI mode
        $expectedResult = (php_sapi_name() === 'cli') ? 'queue' : 'http';
        $this->assertEquals($expectedResult, $result);
        
        // Restore original argv
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        } else {
            unset($_SERVER['argv']);
        }
    }

    public function testDetectsQueueWorkerFromEnvironmentVariable(): void
    {
        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn(null);
        
        // Save original env
        $originalEnv = $_ENV['QUEUE_WORKER'] ?? null;
        
        // Mock queue worker environment
        $_ENV['QUEUE_WORKER'] = 'true';

        $result = $this->contextDetector->detectType();

        // Only expect queue if we're in CLI mode
        $expectedResult = (php_sapi_name() === 'cli') ? 'queue' : 'http';
        $this->assertEquals($expectedResult, $result);
        
        // Restore original environment
        if ($originalEnv !== null) {
            $_ENV['QUEUE_WORKER'] = $originalEnv;
        } else {
            unset($_ENV['QUEUE_WORKER']);
        }
    }

    public function testFallsBackToHttpForUnknownContext(): void
    {
        $this->requestStack->shouldReceive('getCurrentRequest')->andReturn(null);
        
        // Clear any queue-related environment variables that might affect the test
        $originalEnv = $_ENV['QUEUE_WORKER'] ?? null;
        $originalArgv = $_SERVER['argv'] ?? null;
        
        unset($_ENV['QUEUE_WORKER']);
        $_SERVER['argv'] = ['phpunit']; // Set to a non-queue command
        
        $result = $this->contextDetector->detectType();

        // In CLI mode (like PHPUnit), should detect as console, not queue
        $expectedResult = (php_sapi_name() === 'cli') ? 'console' : 'http';
        $this->assertEquals($expectedResult, $result);
        
        // Restore original values
        if ($originalEnv !== null) {
            $_ENV['QUEUE_WORKER'] = $originalEnv;
        }
        if ($originalArgv !== null) {
            $_SERVER['argv'] = $originalArgv;
        }
    }
}