<?php

namespace ApexToolbox\Symfony\Tests\HttpClient;

use ApexToolbox\Symfony\HttpClient\TrackedHttpClient;
use ApexToolbox\Symfony\PayloadCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TrackedHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure and clear PayloadCollector before each test
        PayloadCollector::configure(['token' => 'test-token']);
        PayloadCollector::clear();
    }

    public function testTracksSuccessfulRequest(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test"}', ['http_code' => 200])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient);
        $response = $trackedClient->request('GET', 'https://api.example.com/test');

        // Consume the response to trigger tracking
        $response->getContent();

        $logs = PayloadCollector::getLogs();

        $this->assertCount(1, $logs);
        $this->assertEquals('http_request', $logs[0]['type']);
        $this->assertEquals('GET', $logs[0]['method']);
        $this->assertEquals('https://api.example.com/test', $logs[0]['url']);
        $this->assertEquals(200, $logs[0]['status_code']);
        $this->assertTrue($logs[0]['success']);
        $this->assertIsFloat($logs[0]['duration_ms']);
    }

    public function testTracksFailedRequest(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient);
        $response = $trackedClient->request('POST', 'https://api.example.com/error');

        // Consume the response
        $response->getContent(false); // false to not throw on error

        $logs = PayloadCollector::getLogs();

        $this->assertCount(1, $logs);
        $this->assertEquals('http_request', $logs[0]['type']);
        $this->assertEquals('POST', $logs[0]['method']);
        $this->assertEquals('https://api.example.com/error', $logs[0]['url']);
        $this->assertEquals(500, $logs[0]['status_code']);
        $this->assertFalse($logs[0]['success']);
    }

    public function testDoesNotTrackWhenDisabled(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test"}', ['http_code' => 200])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient, ['track_http_requests' => false]);
        $response = $trackedClient->request('GET', 'https://api.example.com/test');

        // Consume the response
        $response->getContent();

        $logs = PayloadCollector::getLogs();

        $this->assertCount(0, $logs);
    }

    public function testTracksMultipleRequests(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test1"}', ['http_code' => 200]),
            new MockResponse('{"data": "test2"}', ['http_code' => 201]),
            new MockResponse('', ['http_code' => 404])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient);

        $response1 = $trackedClient->request('GET', 'https://api.example.com/test1');
        $response1->getContent();

        $response2 = $trackedClient->request('POST', 'https://api.example.com/test2');
        $response2->getContent();

        $response3 = $trackedClient->request('DELETE', 'https://api.example.com/test3');
        $response3->getContent(false);

        $logs = PayloadCollector::getLogs();

        $this->assertCount(3, $logs);

        $this->assertEquals('GET', $logs[0]['method']);
        $this->assertEquals(200, $logs[0]['status_code']);
        $this->assertTrue($logs[0]['success']);

        $this->assertEquals('POST', $logs[1]['method']);
        $this->assertEquals(201, $logs[1]['status_code']);
        $this->assertTrue($logs[1]['success']);

        $this->assertEquals('DELETE', $logs[2]['method']);
        $this->assertEquals(404, $logs[2]['status_code']);
        $this->assertFalse($logs[2]['success']);
    }

    public function testWithOptionsPreservesTracking(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test"}', ['http_code' => 200])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient);
        $newClient = $trackedClient->withOptions(['headers' => ['X-Test' => 'value']]);

        $response = $newClient->request('GET', 'https://api.example.com/test');
        $response->getContent();

        $logs = PayloadCollector::getLogs();

        $this->assertCount(1, $logs);
        $this->assertEquals('http_request', $logs[0]['type']);
    }
}
