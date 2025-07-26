<?php

namespace ApexToolbox\SymfonyLogger\Tests\EventListener;

use ApexToolbox\SymfonyLogger\EventListener\LogSubscriber;
use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\Tests\AbstractTestCase;
use Monolog\Level;
use Monolog\LogRecord;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\ConsoleEvents;
use Mockery;

class LogSubscriberTest extends AbstractTestCase
{
    private LogSubscriber $subscriber;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->logger = Mockery::mock(LoggerInterface::class);
        $this->subscriber = new LogSubscriber($this->logger);
        LogBuffer::flush();
    }

    public function testGetSubscribedEvents(): void
    {
        $events = LogSubscriber::getSubscribedEvents();
        
        $this->assertArrayHasKey(ConsoleEvents::COMMAND, $events);
        $this->assertEquals('onConsoleCommand', $events[ConsoleEvents::COMMAND]);
    }

    public function testOnConsoleCommandAddsLogEntry(): void
    {
        $command = new TestCommand();
        $command->setName('test:command');
        $input = new ArrayInput(['command' => 'test:command', 'arg1' => 'value1']);
        $output = new NullOutput();
        
        $event = new ConsoleCommandEvent($command, $input, $output);
        
        $this->subscriber->onConsoleCommand($event);
        
        $entries = LogBuffer::all();
        $this->assertCount(1, $entries);
        
        $entry = $entries[0];
        $this->assertEquals('info', $entry['level']);
        $this->assertStringContainsString('Console command executed:', $entry['message']);
        $this->assertStringContainsString('test:command', $entry['message']);
        $this->assertArrayHasKey('time', $entry);
        $this->assertInstanceOf(\DateTime::class, $entry['time']);
        $this->assertArrayHasKey('context', $entry);
        $this->assertEquals('test:command', $entry['context']['command']);
    }

    public function testOnConsoleCommandHandlesCommandWithoutName(): void
    {
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('getName')->andReturn(null);
        
        $input = new ArrayInput([]);
        $output = new NullOutput();
        
        $event = new ConsoleCommandEvent($command, $input, $output);
        
        $this->subscriber->onConsoleCommand($event);
        
        $entries = LogBuffer::all();
        $this->assertCount(1, $entries);
        
        $entry = $entries[0];
        $this->assertEquals('info', $entry['level']);
        $this->assertStringContainsString('Console command executed:', $entry['message']);
    }

    public function testInvokeHandlesMonologRecord(): void
    {
        $record = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'Test warning message',
            context: ['key' => 'value', 'user_id' => 123]
        );
        
        $result = ($this->subscriber)($record);
        
        // Should return the same record
        $this->assertSame($record, $result);
        
        // Should add entry to buffer
        $entries = LogBuffer::all();
        $this->assertCount(1, $entries);
        
        $entry = $entries[0];
        $this->assertEquals($record->datetime, $entry['time']);
        $this->assertEquals('WARNING', $entry['level']);
        $this->assertEquals('Test warning message', $entry['message']);
        $this->assertEquals(['key' => 'value', 'user_id' => 123], $entry['context']);
    }

    public function testInvokeHandlesMultipleLogRecords(): void
    {
        $record1 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'First message',
            context: []
        );
        
        $record2 = new LogRecord(
            datetime: new \DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Second message',
            context: ['error' => 'details']
        );
        
        ($this->subscriber)($record1);
        ($this->subscriber)($record2);
        
        $entries = LogBuffer::all();
        $this->assertCount(2, $entries);
        
        $this->assertEquals('INFO', $entries[0]['level']);
        $this->assertEquals('First message', $entries[0]['message']);
        $this->assertEquals('ERROR', $entries[1]['level']);
        $this->assertEquals('Second message', $entries[1]['message']);
        $this->assertEquals(['error' => 'details'], $entries[1]['context']);
    }

    public function testInvokeHandlesDifferentLogLevels(): void
    {
        $levels = [
            ['level' => Level::Debug, 'expected' => 'DEBUG'],
            ['level' => Level::Info, 'expected' => 'INFO'],
            ['level' => Level::Notice, 'expected' => 'NOTICE'],
            ['level' => Level::Warning, 'expected' => 'WARNING'],
            ['level' => Level::Error, 'expected' => 'ERROR'],
            ['level' => Level::Critical, 'expected' => 'CRITICAL'],
            ['level' => Level::Alert, 'expected' => 'ALERT'],
            ['level' => Level::Emergency, 'expected' => 'EMERGENCY']
        ];
        
        foreach ($levels as $levelData) {
            LogBuffer::flush();
            
            $record = new LogRecord(
                datetime: new \DateTimeImmutable(),
                channel: 'test',
                level: $levelData['level'],
                message: 'Test message',
                context: []
            );
            
            ($this->subscriber)($record);
            
            $entries = LogBuffer::all();
            $this->assertCount(1, $entries);
            $this->assertEquals($levelData['expected'], $entries[0]['level']);
        }
    }
}

class TestCommand extends Command
{
    protected static $defaultName = 'test:command';
    
    protected function configure(): void
    {
        $this->setDescription('Test command for testing');
    }
}