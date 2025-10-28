<?php

namespace ApexToolbox\Symfony\Tests\Handler;

use ApexToolbox\Symfony\Handler\ApexToolboxLogHandler;
use ApexToolbox\Symfony\PayloadCollector;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApexToolboxHandlerTest extends TestCase
{
    private ApexToolboxLogHandler $handler;
    private array $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = [
            'token' => 'test-token',
            'enabled' => true
        ];

        $this->handler = new ApexToolboxLogHandler($this->config);
    }

    protected function tearDown(): void
    {
        PayloadCollector::clear();
        parent::tearDown();
    }

    public function testWriteWithValidConfig(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test message',
            context: ['key' => 'value'],
            extra: []
        );

        $this->handler->handle($record);

        // Use reflection to access PayloadCollector logs
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertCount(1, $logs);

        $logData = $logs[0];
        $this->assertEquals('INFO', $logData['level']);
        $this->assertEquals('Test message', $logData['message']);
        $this->assertEquals(['key' => 'value'], $logData['context']);
        $this->assertEquals('app', $logData['channel']);
    }

    public function testWriteWithoutToken(): void
    {
        $handler = new ApexToolboxLogHandler(['token' => null]);

        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'app',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        $handler->handle($record);

        // Use reflection to access PayloadCollector logs
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertEmpty($logs);
    }

    public function testPrepareLogData(): void
    {
        $datetime = new \DateTimeImmutable('2023-01-01 12:00:00');
        $record = new LogRecord(
            datetime: $datetime,
            channel: 'test',
            level: Level::Error,
            message: 'Error message',
            context: ['error' => 'details'],
            extra: ['class' => 'TestClass', 'function' => 'testMethod']
        );

        $reflection = new \ReflectionClass($this->handler);
        $method = $reflection->getMethod('prepareLogData');
        $method->setAccessible(true);

        $result = $method->invoke($this->handler, $record);

        $this->assertEquals('ERROR', $result['level']);
        $this->assertEquals('Error message', $result['message']);
        $this->assertEquals(['error' => 'details'], $result['context']);
        $this->assertEquals('2023-01-01 12:00:00', $result['timestamp']);
        $this->assertEquals('test', $result['channel']);
        $this->assertEquals('TestClass', $result['source_class']);
        $this->assertEquals('testMethod', $result['function']);
    }

    public function testFlushBuffer(): void
    {
        PayloadCollector::configure($this->config);
        PayloadCollector::addLog(['level' => 'Info', 'message' => 'Test 1']);
        PayloadCollector::addLog(['level' => 'Error', 'message' => 'Test 2']);

        // Use reflection to verify logs are present
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertCount(2, $logs);

        ApexToolboxLogHandler::flushBuffer($this->config);

        // Verify logs are cleared after flush
        $logs = $logsProperty->getValue();
        $this->assertEmpty($logs);
    }

    public function testFlushBufferWithEmptyBuffer(): void
    {
        PayloadCollector::configure($this->config);

        // Use reflection to verify buffer is empty
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logs = $logsProperty->getValue();

        $this->assertEmpty($logs);

        ApexToolboxLogHandler::flushBuffer($this->config);

        $logs = $logsProperty->getValue();
        $this->assertEmpty($logs);
    }

    public function testFlushBufferWithoutToken(): void
    {
        $config = ['token' => null, 'enabled' => false];
        PayloadCollector::configure($config);

        // Manually add log directly using reflection since addLog won't work without token
        $reflection = new \ReflectionClass(PayloadCollector::class);
        $logsProperty = $reflection->getProperty('logs');
        $logsProperty->setAccessible(true);
        $logsProperty->setValue(null, [['message' => 'Test']]);

        $logs = $logsProperty->getValue();
        $this->assertCount(1, $logs);

        ApexToolboxLogHandler::flushBuffer($config);

        // Logs should be cleared after flush
        $logs = $logsProperty->getValue();
        $this->assertEmpty($logs);
    }
}