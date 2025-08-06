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
        LogBuffer::flush(LogBuffer::HTTP_CATEGORY);
    }

    public function testCanAddLogEntry(): void
    {
        $entry = ['message' => 'test', 'timestamp' => time()];
        
        LogBuffer::add($entry);
        
        $entries = LogBuffer::get();
        $this->assertCount(1, $entries);
        $this->assertEquals($entry, $entries[0]);
    }

    public function testCanAddMultipleEntries(): void
    {
        $entry1 = ['message' => 'test1', 'timestamp' => time()];
        $entry2 = ['message' => 'test2', 'timestamp' => time()];
        
        LogBuffer::add($entry1);
        LogBuffer::add($entry2);
        
        $entries = LogBuffer::get();
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
        
        $entries = LogBuffer::get();
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
        $this->assertCount(0, LogBuffer::get());
    }

    public function testFlushReturnsEmptyArrayWhenNoEntries(): void
    {
        $flushed = LogBuffer::flush();
        
        $this->assertIsArray($flushed);
        $this->assertCount(0, $flushed);
    }

    public function testGetReturnsEmptyArrayWhenNoEntries(): void
    {
        $entries = LogBuffer::get();
        
        $this->assertIsArray($entries);
        $this->assertCount(0, $entries);
    }

    public function testBufferPersistsAcrossCalls(): void
    {
        LogBuffer::add(['message' => 'test1']);
        
        $entries1 = LogBuffer::get();
        $this->assertCount(1, $entries1);
        
        LogBuffer::add(['message' => 'test2']);
        
        $entries2 = LogBuffer::get();
        $this->assertCount(2, $entries2);
    }

    public function testCanAddWithCategories(): void
    {
        $entry1 = ['message' => 'default'];
        $entry2 = ['message' => 'http'];
        
        LogBuffer::add($entry1);
        LogBuffer::add($entry2, LogBuffer::HTTP_CATEGORY);
        
        $defaultEntries = LogBuffer::get();
        $httpEntries = LogBuffer::get(LogBuffer::HTTP_CATEGORY);
        
        $this->assertCount(1, $defaultEntries);
        $this->assertCount(1, $httpEntries);
        $this->assertEquals($entry1, $defaultEntries[0]);
        $this->assertEquals($entry2, $httpEntries[0]);
    }
}