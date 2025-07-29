<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\LogProcessor;
use Monolog\LogRecord;
use Monolog\Level;

class LogProcessorTest extends AbstractTestCase
{
    private LogProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->processor = new LogProcessor();
        
        // Clear the LogBuffer
        LogBuffer::flush();
    }

    public function testProcessorHandlesMonolog3xRecord(): void
    {
        $record = new LogRecord(
            new \DateTimeImmutable(),
            'test',
            Level::Info,
            'Test message',
            ['key' => 'value']
        );

        $result = ($this->processor)($record);

        $this->assertSame($record, $result);
        
        $logs = LogBuffer::all();
        $this->assertCount(1, $logs);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('Test message', $logs[0]['message']);
        $this->assertEquals(['key' => 'value'], $logs[0]['context']);
    }

    public function testProcessorHandlesMonolog2xRecord(): void
    {
        $record = [
            'datetime' => new \DateTime(),
            'level_name' => 'INFO',
            'message' => 'Test message',
            'context' => ['key' => 'value']
        ];

        $result = ($this->processor)($record);

        $this->assertSame($record, $result);
        
        $logs = LogBuffer::all();
        $this->assertCount(1, $logs);
        $this->assertEquals('info', $logs[0]['level']);
        $this->assertEquals('Test message', $logs[0]['message']);
        $this->assertEquals(['key' => 'value'], $logs[0]['context']);
    }
}