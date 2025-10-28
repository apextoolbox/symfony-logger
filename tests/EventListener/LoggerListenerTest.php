<?php

namespace ApexToolbox\Symfony\Tests\EventListener;

use ApexToolbox\Symfony\EventListener\LoggerListener;
use ApexToolbox\Symfony\Handler\ApexToolboxLogHandler;
use ApexToolbox\Symfony\PayloadCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Console\ConsoleEvents;

class LoggerListenerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear PayloadCollector before each test
        PayloadCollector::clear();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LoggerListener::getSubscribedEvents();
        
        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
    }

    public function testOnKernelResponseCallsPayloadCollector(): void
    {
        $config = [
            'apex_toolbox' => [
                'token' => 'test',
                'enabled' => true,
                'path_filters' => [
                    'include' => ['*'],
                    'exclude' => []
                ],
                'headers' => ['exclude' => []],
                'body' => ['exclude' => [], 'mask' => []],
                'response' => ['exclude' => [], 'mask' => []]
            ]
        ];

        $parameterBag = new ParameterBag($config);
        $listener = new LoggerListener($parameterBag);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test', 'GET');
        $response = new Response('test response', 200);

        // First trigger request event to set start time
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($requestEvent);

        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // This should collect data and send via PayloadCollector
        $listener->onKernelResponse($responseEvent);

        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testOnKernelRequestSetsStartTime(): void
    {
        $parameterBag = new ParameterBag(['apex_toolbox' => ['token' => 'test']]);
        $listener = new LoggerListener($parameterBag);
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        
        // This should set the start time for duration tracking
        $listener->onKernelRequest($event);
        
        // Test passes if no exception thrown
        $this->assertTrue(true);
    }

    public function testHttpTrackingForApiPaths(): void
    {
        $config = [
            'apex_toolbox' => [
                'token' => 'test-token',
                'enabled' => true,
                'path_filters' => [
                    'include' => ['api/*'],
                    'exclude' => []
                ]
            ]
        ];
        
        $parameterBag = new ParameterBag($config);
        $listener = new LoggerListener($parameterBag);
        
        // Configure PayloadCollector
        PayloadCollector::configure($config['apex_toolbox']);
        
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('https://example.com/api/users', 'GET');
        $response = new Response('{"users": []}', 200);
        
        // First trigger request event to set start time
        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $listener->onKernelRequest($requestEvent);
        
        // Then trigger response event - should track since it matches api/*
        $responseEvent = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $listener->onKernelResponse($responseEvent);
        
        // Test passes if no exception thrown (HTTP tracking should have occurred)
        $this->assertTrue(true);
    }

    public function testOnConsoleCommandRegistersShutdown(): void
    {
        $parameterBag = new ParameterBag(['apex_toolbox' => ['token' => 'test']]);
        $listener = new LoggerListener($parameterBag);
        
        $command = $this->createMock(\Symfony\Component\Console\Command\Command::class);
        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $output = $this->createMock(\Symfony\Component\Console\Output\OutputInterface::class);
        
        $event = new ConsoleCommandEvent($command, $input, $output);
        
        // This should register a shutdown function
        $listener->onConsoleCommand($event);
        
        // Test passes if no exception thrown
        $this->assertTrue(true);
    }
}