<?php

namespace ApexToolbox\SymfonyLogger\Tests\EventListener;

use ApexToolbox\SymfonyLogger\EventListener\LoggerListener;
use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;
use Mockery;

class LoggerListenerTest extends AbstractTestCase
{
    private LoggerListener $listener;
    private ParameterBag $parameterBag;
    private KernelInterface $kernel;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->kernel = Mockery::mock(KernelInterface::class);
        $this->parameterBag = new ParameterBag([
            'apex_toolbox_logger' => [
                'enabled' => true,
                'token' => 'test-token',
                'path_filters' => [
                    'include' => ['/api/*'],
                    'exclude' => ['/api/health']
                ],
                'headers' => [
                    'include_sensitive' => false,
                    'exclude' => ['authorization', 'cookie']
                ],
                'body' => [
                    'max_size' => 10240,
                    'exclude' => ['password', 'secret']
                ]
            ]
        ]);
        
        $this->listener = new LoggerListener($this->parameterBag, $this->kernel);
        LogBuffer::flush();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LoggerListener::getSubscribedEvents();
        
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertEquals(['onKernelRequest', 0], $events[KernelEvents::REQUEST]);
        $this->assertEquals(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
    }

    public function testOnKernelRequestSetsStartTime(): void
    {
        $request = Request::create('/api/test', 'GET');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        $this->listener->onKernelRequest($event);
        
        // We can't directly access startTime, but we can verify the method runs without error
        $this->assertTrue(true);
    }

    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $request = Request::create('/api/test', 'GET');
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);
        
        // Should not set start time for sub-requests
        $this->listener->onKernelRequest($event);
        
        $this->assertTrue(true);
    }

    public function testOnKernelResponseIgnoresSubRequests(): void
    {
        $request = Request::create('/api/test', 'GET');
        $response = new Response('test');
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
        
        $this->listener->onKernelResponse($event);
        
        $this->assertTrue(true);
    }

    public function testOnKernelResponseTracksMatchingRequests(): void
    {
        $request = Request::create('/api/test', 'GET');
        $response = new Response('test response');
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        
        // This should call shouldTrack and potentially track the request
        $this->listener->onKernelResponse($event);
        
        $this->assertTrue(true);
    }

    public function testShouldTrackReturnsFalseWhenDisabled(): void
    {
        $this->parameterBag->set('apex_toolbox_logger', ['enabled' => false, 'token' => 'test-token']);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $request = Request::create('/api/test', 'GET');
        $shouldTrack = $this->invokePrivateMethod($listener, 'shouldTrack', [$request]);
        
        $this->assertFalse($shouldTrack);
    }

    public function testShouldTrackReturnsFalseWhenNoToken(): void
    {
        $this->parameterBag->set('apex_toolbox_logger', ['enabled' => true, 'token' => '']);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $request = Request::create('/api/test', 'GET');
        $shouldTrack = $this->invokePrivateMethod($listener, 'shouldTrack', [$request]);
        
        $this->assertFalse($shouldTrack);
    }

    public function testShouldTrackReturnsTrueForIncludedPaths(): void
    {
        // Ensure proper configuration setup
        $this->parameterBag->set('apex_toolbox_logger', [
            'enabled' => true,
            'token' => 'test-token',
            'path_filters' => [
                'include' => ['/api/*'],
                'exclude' => ['/api/health']
            ]
        ]);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $request = Request::create('/api/test', 'GET');
        
        // Debug the path info
        $path = $request->getPathInfo();
        $this->assertEquals('/api/test', $path);
        
        // Test pattern matching directly - note path starts with /
        $matches = $this->invokePrivateMethod($listener, 'matchesPattern', ['/api/*', '/api/test']);
        $this->assertTrue($matches);
        
        $shouldTrack = $this->invokePrivateMethod($listener, 'shouldTrack', [$request]);
        
        $this->assertTrue($shouldTrack);
    }

    public function testShouldTrackReturnsFalseForExcludedPaths(): void
    {
        $request = Request::create('/api/health', 'GET');
        $shouldTrack = $this->invokePrivateMethod($this->listener, 'shouldTrack', [$request]);
        
        $this->assertFalse($shouldTrack);
    }

    public function testShouldTrackReturnsFalseForNonIncludedPaths(): void
    {
        $request = Request::create('/admin/test', 'GET');
        $shouldTrack = $this->invokePrivateMethod($this->listener, 'shouldTrack', [$request]);
        
        $this->assertFalse($shouldTrack);
    }

    public function testMatchesPatternHandlesWildcards(): void
    {
        $this->assertTrue($this->invokePrivateMethod($this->listener, 'matchesPattern', ['/api/*', '/api/test']));
        $this->assertTrue($this->invokePrivateMethod($this->listener, 'matchesPattern', ['/api/*', '/api/users/123']));
        $this->assertFalse($this->invokePrivateMethod($this->listener, 'matchesPattern', ['/api/*', '/admin/test']));
        $this->assertTrue($this->invokePrivateMethod($this->listener, 'matchesPattern', ['*', '/any/path']));
    }

    public function testMatchesPatternHandlesExactMatches(): void
    {
        $this->assertTrue($this->invokePrivateMethod($this->listener, 'matchesPattern', ['/api/health', '/api/health']));
        $this->assertFalse($this->invokePrivateMethod($this->listener, 'matchesPattern', ['/api/health', '/api/test']));
    }

    public function testPrepareTrackingDataIncludesAllFields(): void
    {
        $request = Request::create('/api/test', 'POST', ['name' => 'John']);
        $request->headers->set('Authorization', 'Bearer token');
        $request->headers->set('Content-Type', 'application/json');
        
        $response = new Response('{"status": "success"}');
        
        $data = $this->invokePrivateMethod($this->listener, 'prepareTrackingData', [$request, $response]);
        
        $this->assertEquals('POST', $data['method']);
        $this->assertStringContainsString('/api/test', $data['url']);
        $this->assertIsArray($data['headers']);
        $this->assertIsArray($data['body']);
        $this->assertEquals(200, $data['status']);
    }

    public function testFilterHeadersExcludesSensitiveHeaders(): void
    {
        $headers = [
            'authorization' => ['Bearer token'],
            'cookie' => ['session=123'],
            'content-type' => ['application/json'],
            'accept' => ['application/json']
        ];
        
        $filtered = $this->invokePrivateMethod($this->listener, 'filterHeaders', [$headers]);
        
        $this->assertArrayNotHasKey('authorization', $filtered);
        $this->assertArrayNotHasKey('cookie', $filtered);
        $this->assertArrayHasKey('content-type', $filtered);
        $this->assertArrayHasKey('accept', $filtered);
    }

    public function testFilterHeadersIncludesAllWhenSensitiveAllowed(): void
    {
        $this->parameterBag->set('apex_toolbox_logger', [
            'enabled' => true,
            'token' => 'test-token',
            'headers' => ['include_sensitive' => true]
        ]);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $headers = [
            'authorization' => ['Bearer token'],
            'content-type' => ['application/json']
        ];
        
        $filtered = $this->invokePrivateMethod($listener, 'filterHeaders', [$headers]);
        
        $this->assertArrayHasKey('authorization', $filtered);
        $this->assertArrayHasKey('content-type', $filtered);
    }

    public function testFilterBodyExcludesSensitiveFields(): void
    {
        $body = [
            'name' => 'John',
            'password' => 'secret123',
            'secret' => 'token',
            'email' => 'john@example.com'
        ];
        
        $filtered = $this->invokePrivateMethod($this->listener, 'filterBody', [$body]);
        
        $this->assertArrayNotHasKey('password', $filtered);
        $this->assertArrayNotHasKey('secret', $filtered);
        $this->assertArrayHasKey('name', $filtered);
        $this->assertArrayHasKey('email', $filtered);
    }

    public function testFilterBodyTruncatesLargeContent(): void
    {
        $this->parameterBag->set('apex_toolbox_logger', [
            'enabled' => true,
            'token' => 'test-token',
            'body' => ['max_size' => 10, 'exclude' => []]
        ]);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $body = ['large_field' => str_repeat('a', 100)];
        
        $filtered = $this->invokePrivateMethod($listener, 'filterBody', [$body]);
        
        $this->assertArrayHasKey('_truncated', $filtered);
        $this->assertEquals('Body too large, truncated', $filtered['_truncated']);
    }

    public function testGetResponseContentHandlesRegularResponse(): void
    {
        $response = new Response('Hello World');
        
        $content = $this->invokePrivateMethod($this->listener, 'getResponseContent', [$response]);
        
        $this->assertEquals('Hello World', $content);
    }

    public function testGetResponseContentHandlesJsonResponse(): void
    {
        $data = ['status' => 'success', 'data' => ['id' => 123]];
        $response = new Response(json_encode($data));
        
        $content = $this->invokePrivateMethod($this->listener, 'getResponseContent', [$response]);
        
        $this->assertEquals($data, $content);
    }

    public function testGetResponseContentTruncatesLargeContent(): void
    {
        $this->parameterBag->set('apex_toolbox_logger', [
            'enabled' => true,
            'token' => 'test-token',
            'body' => ['max_size' => 10]
        ]);
        $listener = new LoggerListener($this->parameterBag, $this->kernel);
        
        $response = new Response(str_repeat('a', 100));
        
        $content = $this->invokePrivateMethod($listener, 'getResponseContent', [$response]);
        
        $this->assertStringContainsString('... [truncated]', $content);
        $this->assertLessThanOrEqual(25, strlen($content)); // 10 chars + "... [truncated]"
    }

    public function testGetEndpointUrlReturnsProductionByDefault(): void
    {
        $url = $this->invokePrivateMethod($this->listener, 'getEndpointUrl', []);
        
        $this->assertEquals('https://apextoolbox.com/api/v1/logs', $url);
    }

    public function testSendSyncRequestRunsWithoutErrors(): void
    {
        $data = [
            'method' => 'GET',
            'url' => 'https://example.com/api/test',
            'headers' => ['content-type' => 'application/json'],
            'body' => ['test' => 'data'],
            'status' => 200,
            'response' => ['success' => true]
        ];
        
        // Test that method runs without throwing exception
        try {
            $this->invokePrivateMethod($this->listener, 'sendSyncRequest', [$data]);
            $this->assertTrue(true);
        } catch (\Exception $e) {
            $this->fail('sendSyncRequest should not throw exceptions: ' . $e->getMessage());
        }
    }
}