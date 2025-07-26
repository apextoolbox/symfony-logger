<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\LogBuffer;

class LogBufferTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Clear the buffer before each test
        LogBuffer::flush();
    }

    public function testCanAddLogEntry(): void
    {
        $entry = ['message' => 'test', 'timestamp' => time()];
        
        LogBuffer::add($entry);
        
        $entries = LogBuffer::all();
        $this->assertCount(1, $entries);
        $this->assertEquals($entry, $entries[0]);
    }

    public function testCanAddMultipleEntries(): void
    {
        $entry1 = ['message' => 'test1', 'timestamp' => time()];
        $entry2 = ['message' => 'test2', 'timestamp' => time()];
        
        LogBuffer::add($entry1);
        LogBuffer::add($entry2);
        
        $entries = LogBuffer::all();
        $this->assertCount(2, $entries);
        $this->assertEquals($entry1, $entries[0]);
        $this->assertEquals($entry2, $entries[1]);
    }

    public function testCanGetAllEntries(): void
    {
        $entry1 = ['message' => 'test1'];
        $entry2 = ['message' => 'test2'];
        
        LogBuffer::add($entry1);
        LogBuffer::add($entry2);
        
        $entries = LogBuffer::all();
        $this->assertIsArray($entries);
        $this->assertCount(2, $entries);
    }

    public function testCanFlushEntries(): void
    {
        $entry1 = ['message' => 'test1'];
        $entry2 = ['message' => 'test2'];
        
        LogBuffer::add($entry1);
        LogBuffer::add($entry2);
        
        $flushed = LogBuffer::flush();
        
        $this->assertCount(2, $flushed);
        $this->assertEquals($entry1, $flushed[0]);
        $this->assertEquals($entry2, $flushed[1]);
        
        // Buffer should be empty after flush
        $this->assertCount(0, LogBuffer::all());
    }

    public function testFlushReturnsEmptyArrayWhenNoEntries(): void
    {
        $flushed = LogBuffer::flush();
        
        $this->assertIsArray($flushed);
        $this->assertCount(0, $flushed);
    }

    public function testAllReturnsEmptyArrayWhenNoEntries(): void
    {
        $entries = LogBuffer::all();
        
        $this->assertIsArray($entries);
        $this->assertCount(0, $entries);
    }

    public function testBufferPersistsAcrossCalls(): void
    {
        LogBuffer::add(['message' => 'test1']);
        
        $entries1 = LogBuffer::all();
        $this->assertCount(1, $entries1);
        
        LogBuffer::add(['message' => 'test2']);
        
        $entries2 = LogBuffer::all();
        $this->assertCount(2, $entries2);
    }
}