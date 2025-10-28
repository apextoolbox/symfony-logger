<?php

namespace ApexToolbox\Symfony\Tests\Handler;

use ApexToolbox\Symfony\Handler\ApexToolboxExceptionHandler;
use ApexToolbox\Symfony\PayloadCollector;
use Exception;
use PHPUnit\Framework\TestCase;

class ApexToolboxExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayloadCollector::clear();
    }

    protected function tearDown(): void
    {
        PayloadCollector::clear();
        parent::tearDown();
    }

    public function test_capture_calls_payload_collector()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $exception = new Exception('Test exception message', 500);

        ApexToolboxExceptionHandler::capture($exception);

        // Use reflection to verify exception was stored in PayloadCollector
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);
        $exceptionData = $exceptionDataProperty->getValue();

        $this->assertNotNull($exceptionData);
        $this->assertEquals('Test exception message', $exceptionData['message']);
        $this->assertEquals('Exception', $exceptionData['class']);
        $this->assertEquals(500, $exceptionData['code']);
    }

    public function test_capture_when_disabled()
    {
        $config = [
            'enabled' => false,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $exception = new Exception('Test exception');

        ApexToolboxExceptionHandler::capture($exception);

        // Use reflection to verify exception was not stored
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);
        $exceptionData = $exceptionDataProperty->getValue();

        $this->assertNull($exceptionData);
    }

    public function test_capture_when_no_token()
    {
        $config = [
            'enabled' => true,
            'token' => ''
        ];

        PayloadCollector::configure($config);

        $exception = new Exception('Test exception');

        ApexToolboxExceptionHandler::capture($exception);

        // Use reflection to verify exception was not stored
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);
        $exceptionData = $exceptionDataProperty->getValue();

        $this->assertNull($exceptionData);
    }
}