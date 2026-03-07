<?php

namespace ApexToolbox\SymfonyLogger\Tests;

use ApexToolbox\SymfonyLogger\Apex;
use ApexToolbox\SymfonyLogger\PayloadCollector;

class ApexTest extends AbstractTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        PayloadCollector::clear();
        PayloadCollector::configure(['enabled' => true, 'token' => 'test-token']);
    }

    protected function tearDown(): void
    {
        PayloadCollector::clear();
        parent::tearDown();
    }

    public function testTrackHttpRequest(): void
    {
        Apex::trackHttpRequest('GET', 'https://api.example.com/users', 200, 45.2);

        $outgoing = PayloadCollector::getOutgoingRequests();

        $this->assertCount(1, $outgoing);
        $this->assertEquals('GET', $outgoing[0]['method']);
        $this->assertEquals('https://api.example.com/users', $outgoing[0]['uri']);
        $this->assertEquals(200, $outgoing[0]['status_code']);
        $this->assertEquals(45.2, $outgoing[0]['duration']);
        $this->assertArrayHasKey('timestamp', $outgoing[0]);
    }

    public function testTrackHttpRequestNullableParams(): void
    {
        Apex::trackHttpRequest('POST', 'https://api.example.com/data');

        $outgoing = PayloadCollector::getOutgoingRequests();

        $this->assertCount(1, $outgoing);
        $this->assertEquals('POST', $outgoing[0]['method']);
        $this->assertNull($outgoing[0]['status_code']);
        $this->assertNull($outgoing[0]['duration']);
    }
}
