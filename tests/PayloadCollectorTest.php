<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);
        $incomingRequest = $incomingRequestProperty->getValue();

        $this->assertNotNull($incomingRequest);
        $this->assertEquals('POST', $incomingRequest['method']);
        $this->assertEquals('/api/test', $incomingRequest['uri']);
        $this->assertEquals(['key' => 'value'], $incomingRequest['payload']);
        $this->assertEquals(200, $incomingRequest['status_code']);
        $this->assertEqualsWithDelta(100, $incomingRequest['duration'], 1); // 100ms ± 1ms
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
        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);

        $this->assertNull($incomingRequestProperty->getValue());
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
        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);

        $this->assertNull($incomingRequestProperty->getValue());
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
            'token' => 'test-token',
            'headers' => ['exclude' => []],
            'body' => ['exclude' => [], 'mask' => []],
            'response' => ['exclude' => [], 'mask' => []]
        ];

        PayloadCollector::configure($config);

        $request = Request::create('/api/test', 'GET');
        $response = new Response('test', 200);

        PayloadCollector::collect($request, $response, microtime(true));
        PayloadCollector::addLog(['message' => 'test']);
        PayloadCollector::clear();

        $reflection = new \ReflectionClass(PayloadCollector::class);

        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);

        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);

        $this->assertNull($incomingRequestProperty->getValue());
        $this->assertEmpty($logsProperty->getValue());
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
        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);
        $incomingRequest = $incomingRequestProperty->getValue();

        $this->assertNotNull($incomingRequest);
        $this->assertNull($incomingRequest['status_code']);
        $this->assertNull($incomingRequest['response']);
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
        $incomingRequestProperty = $reflection->getProperty('incomingRequest');
        $incomingRequestProperty->setAccessible(true);
        $incomingRequest = $incomingRequestProperty->getValue();

        $this->assertEquals(500, $incomingRequest['duration']);
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

    public function test_build_payload_includes_trace_id_and_logs()
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

        $this->assertArrayHasKey('trace_id', $payload);
        $this->assertArrayHasKey('logs', $payload);
        $this->assertCount(1, $payload['logs']);
    }

    public function test_build_payload_uses_request_id_as_trace_id()
    {
        $config = [
            'enabled' => true,
            'token' => 'test-token'
        ];

        PayloadCollector::configure($config);
        PayloadCollector::setRequestId('custom-request-id-123');
        PayloadCollector::addLog(['level' => 'INFO', 'message' => 'Test log']);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $buildPayloadMethod = $reflection->getMethod('buildPayload');
        $buildPayloadMethod->setAccessible(true);
        $payload = $buildPayloadMethod->invoke(null);

        $this->assertEquals('custom-request-id-123', $payload['trace_id']);
    }

    public function test_build_payload_groups_request_and_response_data()
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
        $response = new Response('{"result": "ok"}', 200, ['Content-Type' => 'application/json']);

        $startTime = microtime(true);
        $endTime = $startTime + 0.1; // 100ms

        PayloadCollector::collect($request, $response, $startTime, $endTime);

        $reflection = new \ReflectionClass(PayloadCollector::class);
        $buildPayloadMethod = $reflection->getMethod('buildPayload');
        $buildPayloadMethod->setAccessible(true);
        $payload = $buildPayloadMethod->invoke(null);

        $this->assertArrayHasKey('trace_id', $payload);
        $this->assertArrayHasKey('request', $payload);
        $requestData = $payload['request'];

        // Request fields
        $this->assertEquals('POST', $requestData['method']);
        $this->assertEquals('/api/test', $requestData['uri']);
        $this->assertArrayHasKey('headers', $requestData);
        $this->assertArrayHasKey('payload', $requestData);
        $this->assertArrayHasKey('ip_address', $requestData);
        $this->assertArrayHasKey('user_agent', $requestData);
        $this->assertArrayNotHasKey('direction', $requestData);

        // Response fields (merged into request object)
        $this->assertEquals(200, $requestData['status_code']);
        $this->assertArrayHasKey('response', $requestData);
        $this->assertEqualsWithDelta(100, $requestData['duration'], 1);
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
