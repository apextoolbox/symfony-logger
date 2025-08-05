<?php

namespace ApexToolbox\SymfonyLogger\Tests\Handler;

use ApexToolbox\SymfonyLogger\ContextDetector;
use ApexToolbox\SymfonyLogger\Handler\ApexToolboxHandler;
use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\SourceClassExtractor;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
use Monolog\LogRecord;
use Monolog\Level;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Mockery;

class ApexToolboxHandlerTest extends AbstractTestCase
{
    private ContextDetector $contextDetector;
    private SourceClassExtractor $sourceClassExtractor;
    private HttpClientInterface $httpClient;
    private ApexToolboxHandler $handler;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->contextDetector = Mockery::mock(ContextDetector::class);
        $this->sourceClassExtractor = Mockery::mock(SourceClassExtractor::class);
        $this->httpClient = Mockery::mock(HttpClientInterface::class);

        $this->config = [
            'token' => 'test-token',
            'universal_logging' => [
                'enabled' => true,
                'types' => ['http', 'console', 'queue'],
            ],
        ];

        $this->handler = new ApexToolboxHandler(
            $this->config,
            $this->contextDetector,
            $this->sourceClassExtractor,
            $this->httpClient
        );

        LogBuffer::flush();
    }

    public function testHandlesHttpLogs(): void
    {
        $this->contextDetector->shouldReceive('detectType')->andReturn('http');
        $this->sourceClassExtractor->shouldReceive('extractSourceClass')->andReturn('App\\Controller\\TestController');

        $record = $this->createLogRecord('Test message', ['key' => 'value']);

        $this->handler->handle($record);

        $logs = LogBuffer::all();
        $this->assertCount(1, $logs);
        $this->assertEquals('Test message', $logs[0]['message']);
        $this->assertEquals('http', $logs[0]['type']);
        $this->assertEquals('App\\Controller\\TestController', $logs[0]['source_class']);
    }

    public function testHandlesConsoleLogs(): void
    {
        $this->contextDetector->shouldReceive('detectType')->andReturn('console');
        $this->sourceClassExtractor->shouldReceive('extractSourceClass')->andReturn('App\\Command\\TestCommand');

        $record = $this->createLogRecord('Console message');

        $this->handler->handle($record);

        $logs = LogBuffer::all();
        $this->assertCount(1, $logs);
        $this->assertEquals('console', $logs[0]['type']);
        $this->assertEquals('App\\Command\\TestCommand', $logs[0]['source_class']);
    }

    public function testHandlesQueueLogs(): void
    {
        $this->contextDetector->shouldReceive('detectType')->andReturn('queue');
        $this->sourceClassExtractor->shouldReceive('extractSourceClass')->andReturn('App\\Job\\TestJob');

        $response = Mockery::mock(ResponseInterface::class);
        $this->httpClient->shouldReceive('request')->once()->andReturn($response);

        $record = $this->createLogRecord('Queue message');

        $this->handler->handle($record);

        // Queue logs are sent immediately, not buffered
        $logs = LogBuffer::all();
        $this->assertCount(0, $logs);
    }

    public function testIgnoresLogsWhenUniversalLoggingDisabled(): void
    {
        $config = $this->config;
        $config['universal_logging']['enabled'] = false;

        $handler = new ApexToolboxHandler(
            $config,
            $this->contextDetector,
            $this->sourceClassExtractor,
            $this->httpClient
        );

        $record = $this->createLogRecord('Test message');

        $handler->handle($record);

        $logs = LogBuffer::all();
        $this->assertCount(0, $logs);
    }

    public function testIgnoresLogsForDisabledTypes(): void
    {
        $config = $this->config;
        $config['universal_logging']['types'] = ['http']; // Only HTTP enabled

        $handler = new ApexToolboxHandler(
            $config,
            $this->contextDetector,
            $this->sourceClassExtractor,
            $this->httpClient
        );

        $this->contextDetector->shouldReceive('detectType')->andReturn('console');

        $record = $this->createLogRecord('Console message');

        $handler->handle($record);

        $logs = LogBuffer::all();
        $this->assertCount(0, $logs);
    }

    public function testSendsLogsOnShutdownForConsole(): void
    {
        $this->contextDetector->shouldReceive('detectType')->andReturn('console');
        $this->sourceClassExtractor->shouldReceive('extractSourceClass')->andReturn('App\\Command\\TestCommand');

        // Add a log to buffer
        LogBuffer::add([
            'message' => 'Test log',
            'type' => 'console',
            'source_class' => 'App\\Command\\TestCommand',
        ]);

        $response = Mockery::mock(ResponseInterface::class);
        $this->httpClient->shouldReceive('request')->once()->with(
            'POST',
            'https://apextoolbox.com/api/v1/logs',
            Mockery::on(function ($options) {
                return isset($options['headers']['Authorization']) &&
                       $options['headers']['Authorization'] === 'Bearer test-token' &&
                       isset($options['json']['type']) &&
                       $options['json']['type'] === 'console' &&
                       isset($options['json']['logs']) &&
                       count($options['json']['logs']) === 1;
            })
        )->andReturn($response);

        $this->handler->sendLogsOnShutdown();
        
        // Verify that the logs were flushed from the buffer
        $remainingLogs = LogBuffer::all();
        $this->assertCount(0, $remainingLogs);
    }

    public function testHandlesHttpClientExceptionsGracefully(): void
    {
        $this->contextDetector->shouldReceive('detectType')->andReturn('queue');
        $this->sourceClassExtractor->shouldReceive('extractSourceClass')->andReturn('App\\Job\\TestJob');

        $this->httpClient->shouldReceive('request')->andThrow(new \Exception('Network error'));

        $record = $this->createLogRecord('Queue message');

        // Should not throw exception
        $this->handler->handle($record);

        $this->assertTrue(true); // Test passes if no exception is thrown
    }

    private function createLogRecord(string $message, array $context = [])
    {
        if (class_exists(LogRecord::class)) {
            // Monolog 3.x
            return new LogRecord(
                new \DateTimeImmutable(),
                'test',
                Level::Info,
                $message,
                $context
            );
        }

        // Monolog 2.x compatibility - return array format
        return [
            'datetime' => new \DateTime(),
            'channel' => 'test',
            'level' => 200, // INFO level
            'level_name' => 'INFO',
            'message' => $message,
            'context' => $context,
        ];
    }
}