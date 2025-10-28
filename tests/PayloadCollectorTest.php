<?php

namespace ApexToolbox\Symfony\Tests;

use ApexToolbox\Symfony\PayloadCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use PHPUnit\Framework\TestCase;

class PayloadCollectorTest extends TestCase
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

    public function test_collect_stores_request_and_response_data()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token',
            'headers' => ['exclude' => []],
            'body' => ['exclude' => [], 'mask' => []],
            'response' => ['exclude' => [], 'mask' => []]
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'POST', ['key' => 'value']);
        $response = new Response('test response', 200);

        $startTime = microtime(true);
        $endTime = $startTime + 0.1; // 100ms duration

        PayloadCollector::collect($request, $response, $startTime, $endTime);

        // Use reflection to access private data
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $requestDataProperty = $reflection->getProperty('requestData');
        $requestDataProperty->setAccessible(true);
        $requestData = $requestDataProperty->getValue();

        $responseDataProperty = $reflection->getProperty('responseData');
        $responseDataProperty->setAccessible(true);
        $responseData = $responseDataProperty->getValue();

        $this->assertNotNull($requestData);
        $this->assertNotNull($responseData);
        $this->assertEquals('POST', $requestData['method']);
        $this->assertEquals('/api/test', $requestData['uri']);
        $this->assertEquals(['key' => 'value'], $requestData['payload']);
        $this->assertEquals(200, $responseData['status_code']);
        $this->assertEqualsWithDelta(100, $responseData['duration'], 1); // 100ms ± 1ms
    }

    public function test_collect_ignores_when_disabled()
    {
        $config = [
            'enabled' => false,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('test', 200);

        PayloadCollector::collect($request, $response, microtime(true));

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $requestDataProperty = $reflection->getProperty('requestData');
        $requestDataProperty->setAccessible(true);

        $this->assertNull($requestDataProperty->getValue());
    }

    public function test_collect_ignores_when_no_token()
    {
        $config = [
            'enabled' => true,
            'token' => ''
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('test', 200);

        PayloadCollector::collect($request, $response, microtime(true));

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $requestDataProperty = $reflection->getProperty('requestData');
        $requestDataProperty->setAccessible(true);

        $this->assertNull($requestDataProperty->getValue());
    }

    public function test_set_exception_stores_exception_data()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $exception = new Exception('Test exception', 500);
        PayloadCollector::setException($exception);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);
        $exceptionData = $exceptionDataProperty->getValue();

        $this->assertNotNull($exceptionData);
        $this->assertEquals('Test exception', $exceptionData['message']);
        $this->assertEquals('Exception', $exceptionData['class']);
        $this->assertEquals(500, $exceptionData['code']);
        $this->assertArrayHasKey('hash', $exceptionData);
        $this->assertArrayHasKey('stack_trace', $exceptionData);
        $this->assertIsArray($exceptionData['stack_trace']);
    }

    public function test_exception_stack_trace_has_in_app_field()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $exception = new Exception('Test exception for in_app detection');
        PayloadCollector::setException($exception);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);
        $exceptionData = $exceptionDataProperty->getValue();

        $this->assertArrayHasKey('stack_trace', $exceptionData);
        $stackTrace = $exceptionData['stack_trace'];

        // At least one frame should exist
        $this->assertNotEmpty($stackTrace);

        // Each frame should have in_app field
        foreach ($stackTrace as $frame) {
            $this->assertArrayHasKey('in_app', $frame);
            $this->assertIsBool($frame['in_app']);
            $this->assertArrayHasKey('file', $frame);
            $this->assertArrayHasKey('line', $frame);
        }

        // At least one frame should be from app code (this test file)
        $appFrames = array_filter($stackTrace, fn($frame) => $frame['in_app'] === true);
        $this->assertNotEmpty($appFrames, 'Expected at least one frame to be marked as app code');
    }

    public function test_vendor_code_is_detected_correctly()
    {
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $isAppCodeMethod = $reflection->getMethod('isAppCode');
        $isAppCodeMethod->setAccessible(true);

        // Test app code paths
        $this->assertTrue($isAppCodeMethod->invoke(null, '/var/www/project/src/Controller/TestController.php'));
        $this->assertTrue($isAppCodeMethod->invoke(null, '/home/user/app/tests/SomeTest.php'));
        $this->assertTrue($isAppCodeMethod->invoke(null, 'C:\\project\\src\\Service\\MyService.php'));

        // Test vendor paths
        $this->assertFalse($isAppCodeMethod->invoke(null, '/var/www/project/vendor/symfony/http-kernel/Kernel.php'));
        $this->assertFalse($isAppCodeMethod->invoke(null, '/home/user/app/vendor/monolog/monolog/Logger.php'));
        $this->assertFalse($isAppCodeMethod->invoke(null, 'C:\\project\\vendor\\package\\Class.php'));
    }

    public function test_add_log_stores_log_data()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $logData = [
            'level' => 'INFO',
            'message' => 'Test log message',
            'context' => ['key' => 'value'],
            'timestamp' => '2025-01-01 12:00:00'
        ];

        PayloadCollector::addLog($logData);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertCount(1, $logs);
        $this->assertEquals($logData, $logs[0]);
    }

    public function test_add_log_ignores_when_disabled()
    {
        $config = [
            'enabled' => false,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        PayloadCollector::addLog(['message' => 'test']);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertEmpty($logs);
    }

    public function test_clear_resets_all_data()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('test', 200);
        $exception = new Exception('Test exception');

        PayloadCollector::collect($request, $response, microtime(true));
        PayloadCollector::setException($exception);
        PayloadCollector::clear();

        $reflection = new \ReflectionClass(PayloadCollector::class);

        $requestDataProperty = $reflection->getProperty('requestData');
        $requestDataProperty->setAccessible(true);

        $responseDataProperty = $reflection->getProperty('responseData');
        $responseDataProperty->setAccessible(true);

        $exceptionDataProperty = $reflection->getProperty('exceptionData');
        $exceptionDataProperty->setAccessible(true);

        $this->assertNull($requestDataProperty->getValue());
        $this->assertNull($responseDataProperty->getValue());
        $this->assertNull($exceptionDataProperty->getValue());
    }

    public function test_collect_handles_null_response()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token',
            'headers' => ['exclude' => []],
            'body' => ['exclude' => [], 'mask' => []],
            'response' => ['exclude' => [], 'mask' => []]
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');

        PayloadCollector::collect($request, null, microtime(true));

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $responseDataProperty = $reflection->getProperty('responseData');
        $responseDataProperty->setAccessible(true);
        $responseData = $responseDataProperty->getValue();

        $this->assertNotNull($responseData);
        $this->assertNull($responseData['status_code']);
        $this->assertNull($responseData['response']);
    }

    public function test_collect_calculates_duration_correctly()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token',
            'headers' => ['exclude' => []],
            'body' => ['exclude' => [], 'mask' => []],
            'response' => ['exclude' => [], 'mask' => []]
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('test', 200);

        $startTime = microtime(true);
        $endTime = $startTime + 0.5; // 500ms

        PayloadCollector::collect($request, $response, $startTime, $endTime);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $responseDataProperty = $reflection->getProperty('responseData');
        $responseDataProperty->setAccessible(true);
        $responseData = $responseDataProperty->getValue();

        $this->assertEquals(500, $responseData['duration']);
    }

    public function test_clear_resets_logs()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        PayloadCollector::addLog(['message' => 'test']);
        PayloadCollector::clear();

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertEmpty($logs);
    }

    public function test_build_payload_includes_logs_trace_id_when_logs_present()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);

        PayloadCollector::addLog(['level' => 'INFO', 'message' => 'Test log']);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $buildPayloadMethod = $reflection->getMethod('buildPayload');
        $buildPayloadMethod->setAccessible(true);
        $payload = $buildPayloadMethod->invoke(null);

        $this->assertArrayHasKey('logs_trace_id', $payload);
        $this->assertArrayHasKey('logs', $payload);
        $this->assertCount(1, $payload['logs']);
    }

    public function test_recursively_filter_sensitive_data_excludes_fields()
    {
        $data = [
            'password' => 'secret123',
            'username' => 'john',
            'email' => 'john@example.com',
            'nested' => [
                'token' => 'abc123',
                'public_info' => 'visible'
            ]
        ];

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $filterMethod = $reflection->getMethod('recursivelyFilterSensitiveData');
        $filterMethod->setAccessible(true);

        $filtered = $filterMethod->invoke(null, $data, ['password', 'token'], []);

        $this->assertArrayNotHasKey('password', $filtered);
        $this->assertArrayHasKey('username', $filtered);
        $this->assertArrayHasKey('email', $filtered);
        $this->assertArrayNotHasKey('token', $filtered['nested']);
        $this->assertArrayHasKey('public_info', $filtered['nested']);
    }

    public function test_recursively_filter_sensitive_data_masks_fields()
    {
        $data = [
            'ssn' => '123-45-6789',
            'phone' => '555-1234',
            'name' => 'John Doe',
            'nested' => [
                'email' => 'john@example.com',
                'address' => '123 Main St'
            ]
        ];

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $filterMethod = $reflection->getMethod('recursivelyFilterSensitiveData');
        $filterMethod->setAccessible(true);

        $filtered = $filterMethod->invoke(null, $data, [], ['ssn', 'phone', 'email']);

        $this->assertEquals('*******', $filtered['ssn']);
        $this->assertEquals('*******', $filtered['phone']);
        $this->assertEquals('John Doe', $filtered['name']);
        $this->assertEquals('*******', $filtered['nested']['email']);
        $this->assertEquals('123 Main St', $filtered['nested']['address']);
    }

    public function test_recursively_filter_sensitive_data_exclude_takes_precedence()
    {
        $data = [
            'sensitive_field' => 'secret_value'
        ];

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $filterMethod = $reflection->getMethod('recursivelyFilterSensitiveData');
        $filterMethod->setAccessible(true);

        // Field appears in both exclude and mask - exclude should take precedence
        $filtered = $filterMethod->invoke(null, $data, ['sensitive_field'], ['sensitive_field']);

        $this->assertArrayNotHasKey('sensitive_field', $filtered);
    }

    public function test_generate_exception_hash_creates_consistent_hash()
    {
        $exception1 = new Exception('Test message', 500);
        $exception2 = new Exception('Test message', 500);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $hashMethod = $reflection->getMethod('generateExceptionHash');
        $hashMethod->setAccessible(true);

        $hash1 = $hashMethod->invoke(null, $exception1);
        $hash2 = $hashMethod->invoke(null, $exception2);

        // Different exceptions from same line should produce different hashes
        $this->assertNotEquals($hash1, $hash2);
        $this->assertEquals(64, strlen($hash1)); // SHA-256 hash length
    }

    public function test_extract_code_context_returns_null_for_nonexistent_file()
    {
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $extractMethod = $reflection->getMethod('extractCodeContext');
        $extractMethod->setAccessible(true);

        $result = $extractMethod->invoke(null, '/nonexistent/file.php', 10);

        $this->assertNull($result);
    }

    public function test_get_real_ip_address_handles_forwarded_headers()
    {
        $request = Request::create('/test');
        $request->server->set('HTTP_X_FORWARDED_FOR', '192.168.1.100, 10.0.0.1');

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $ipMethod = $reflection->getMethod('getRealIpAddress');
        $ipMethod->setAccessible(true);

        $ip = $ipMethod->invoke(null, $request);

        // Should return the first valid public IP
        $this->assertNotEquals('192.168.1.100', $ip); // Private IP should be rejected
        $this->assertNotNull($ip);
    }
}