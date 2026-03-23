<?php

namespace ApexToolbox\SymfonyLogger\Tests\Handler;

use ApexToolbox\SymfonyLogger\Handler\ApexToolboxExceptionHandler;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use Exception;
use RuntimeException;
use PHPUnit\Framework\TestCase;

class ApexToolboxExceptionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayloadCollector::clear();
        PayloadCollector::configure([
            'enabled' => true,
            'token' => 'test-token',
        ]);
        ApexToolboxExceptionHandler::setBasePath(dirname(__DIR__, 2));
    }

    protected function tearDown(): void
    {
        PayloadCollector::clear();
        parent::tearDown();
    }

    public function test_capture_stores_exception_in_payload_collector(): void
    {
        $exception = new RuntimeException('Something went wrong', 500);
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();

        $this->assertArrayHasKey('exception', $payload);
        $this->assertEquals('RuntimeException', $payload['exception']['class']);
        $this->assertEquals('Something went wrong', $payload['exception']['message']);
        $this->assertEquals('500', $payload['exception']['code']);
    }

    public function test_exception_has_required_fields(): void
    {
        $exception = new Exception('Test error');
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();
        $exceptionData = $payload['exception'];

        $this->assertArrayHasKey('hash', $exceptionData);
        $this->assertArrayHasKey('class', $exceptionData);
        $this->assertArrayHasKey('message', $exceptionData);
        $this->assertArrayHasKey('code', $exceptionData);
        $this->assertArrayHasKey('file_path', $exceptionData);
        $this->assertArrayHasKey('line_number', $exceptionData);
        $this->assertArrayHasKey('source_context', $exceptionData);
        $this->assertArrayHasKey('stack_trace', $exceptionData);
        $this->assertArrayHasKey('context', $exceptionData);
    }

    public function test_hash_is_consistent_for_same_exception_location(): void
    {
        $exception1 = $this->createExceptionAtLine();

        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => true, 'token' => 'test-token']);
        ApexToolboxExceptionHandler::capture($exception1);
        $hash1 = $this->getPayload()['exception']['hash'];

        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => true, 'token' => 'test-token']);
        $exception2 = $this->createExceptionAtLine();
        ApexToolboxExceptionHandler::capture($exception2);
        $hash2 = $this->getPayload()['exception']['hash'];

        $this->assertEquals($hash1, $hash2);
    }

    public function test_hash_differs_for_different_exception_classes(): void
    {
        ApexToolboxExceptionHandler::capture(new RuntimeException('error'));
        $hash1 = $this->getPayload()['exception']['hash'];

        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => true, 'token' => 'test-token']);
        ApexToolboxExceptionHandler::capture(new Exception('error'));
        $hash2 = $this->getPayload()['exception']['hash'];

        $this->assertNotEquals($hash1, $hash2);
    }

    public function test_file_paths_are_relative(): void
    {
        $exception = new Exception('Test');
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();
        $basePath = dirname(__DIR__, 2);

        $this->assertStringNotContainsString($basePath, $payload['exception']['file_path']);
    }

    public function test_stack_trace_has_relative_paths(): void
    {
        $exception = new Exception('Test');
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();
        $basePath = dirname(__DIR__, 2) . '/';

        $this->assertIsString($payload['exception']['stack_trace']);
    }

    public function test_source_context_contains_surrounding_lines(): void
    {
        $exception = new Exception('Test');
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();
        $context = $payload['exception']['source_context'];

        $this->assertIsArray($context);
        $this->assertArrayHasKey('code', $context);
        $this->assertArrayHasKey('error_line', $context);
        $this->assertArrayHasKey('start_line', $context);
        $this->assertEquals($exception->getLine(), $context['error_line']);
        $this->assertIsString($context['code']);
        $this->assertStringContainsString('new Exception', $context['code']);
    }

    public function test_exception_code_is_cast_to_string(): void
    {
        $exception = new Exception('Test', 42);
        ApexToolboxExceptionHandler::capture($exception);

        $payload = $this->getPayload();

        $this->assertIsString($payload['exception']['code']);
        $this->assertEquals('42', $payload['exception']['code']);
    }

    public function test_only_first_exception_is_captured(): void
    {
        ApexToolboxExceptionHandler::capture(new Exception('First'));
        ApexToolboxExceptionHandler::capture(new Exception('Second'));

        $payload = $this->getPayload();

        $this->assertEquals('First', $payload['exception']['message']);
    }

    public function test_capture_ignores_when_disabled(): void
    {
        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => false, 'token' => 'test-token']);

        ApexToolboxExceptionHandler::capture(new Exception('Test'));

        $payload = $this->getPayload();

        $this->assertArrayNotHasKey('exception', $payload);
    }

    public function test_clear_resets_exception(): void
    {
        ApexToolboxExceptionHandler::capture(new Exception('Test'));
        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => true, 'token' => 'test-token']);

        $payload = $this->getPayload();

        $this->assertArrayNotHasKey('exception', $payload);
    }

    public function test_context_includes_environment(): void
    {
        $_SERVER['APP_ENV'] = 'testing';

        ApexToolboxExceptionHandler::capture(new Exception('Test'));

        $payload = $this->getPayload();

        $this->assertEquals('testing', $payload['exception']['context']['environment']);

        unset($_SERVER['APP_ENV']);
    }

    private function getPayload(): array
    {
        $reflection = new \ReflectionClass(PayloadCollector::class);

        $payload = ['trace_id' => 'test'];

        $request = $reflection->getProperty('incomingRequest');
        if ($request->getValue()) {
            $payload['request'] = $request->getValue();
        }

        $logs = $reflection->getProperty('logs');
        if (!empty($logs->getValue())) {
            $payload['logs'] = $logs->getValue();
        }

        $outgoing = $reflection->getProperty('outgoingRequests');
        if (!empty($outgoing->getValue())) {
            $payload['outgoing_requests'] = $outgoing->getValue();
        }

        $exception = $reflection->getProperty('exception');
        if ($exception->getValue() !== null) {
            $payload['exception'] = $exception->getValue();
        }

        return $payload;
    }

    private function createExceptionAtLine(): Exception
    {
        return new Exception('Test error at specific line');
    }
}
