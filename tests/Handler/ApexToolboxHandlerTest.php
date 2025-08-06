<?php

namespace ApexToolbox\SymfonyLogger\Tests\Handler;

use ApexToolbox\SymfonyLogger\Handler\ApexToolboxLogHandler;
use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApexToolboxHandlerTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear buffer before each test
        LogBuffer::flush();
        LogBuffer::flush(LogBuffer::HTTP_CATEGORY);
    }

    public function testHandlerAddsToBuffer(): void
    {
        $config = ['token' => 'test-token'];
        $handler = new ApexToolboxLogHandler($config);
        
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: ['key' => 'value']
        );

        $handler->handle($record);

        // Should be added to both default and HTTP categories
        $defaultEntries = LogBuffer::get();
        $httpEntries = LogBuffer::get(LogBuffer::HTTP_CATEGORY);
        
        $this->assertCount(1, $defaultEntries);
        $this->assertCount(1, $httpEntries);
        $this->assertEquals('Test message', $defaultEntries[0]['message']);
        $this->assertEquals('INFO', $defaultEntries[0]['level']);
    }

    public function testHandlerSkipsWithoutToken(): void
    {
        $config = [];
        $handler = new ApexToolboxLogHandler($config);
        
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: []
        );

        $handler->handle($record);

        $entries = LogBuffer::get();
        $this->assertCount(0, $entries);
    }

    public function testFlushBufferSendsHttpRequest(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $config = ['token' => 'test-token'];
        
        // Add some data to the buffer
        LogBuffer::add(['message' => 'test']);
        
        $httpClient->expects($this->once())
            ->method('request')
            ->with('POST', 'https://apextoolbox.com/api/v1/logs');

        ApexToolboxLogHandler::flushBuffer($config, $httpClient);
    }
}