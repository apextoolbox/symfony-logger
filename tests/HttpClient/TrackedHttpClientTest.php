<?php

namespace ApexToolbox\SymfonyLogger\Tests\HttpClient;

use ApexToolbox\SymfonyLogger\HttpClient\TrackedHttpClient;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class TrackedHttpClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure and clear PayloadCollector before each test
        PayloadCollector::configure(['token' => 'test-token', 'enabled' => true]);
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

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(1, $requests);
        $this->assertEquals('GET', $requests[0]['method']);
        $this->assertEquals('https://api.example.com/test', $requests[0]['uri']);
        $this->assertEquals(200, $requests[0]['status_code']);
        $this->assertIsFloat($requests[0]['duration']);
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

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(1, $requests);
        $this->assertEquals('POST', $requests[0]['method']);
        $this->assertEquals('https://api.example.com/error', $requests[0]['uri']);
        $this->assertEquals(500, $requests[0]['status_code']);
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

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(0, $requests);
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

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(3, $requests);

        $this->assertEquals('GET', $requests[0]['method']);
        $this->assertEquals(200, $requests[0]['status_code']);

        $this->assertEquals('POST', $requests[1]['method']);
        $this->assertEquals(201, $requests[1]['status_code']);

        $this->assertEquals('DELETE', $requests[2]['method']);
        $this->assertEquals(404, $requests[2]['status_code']);
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

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(1, $requests);
        $this->assertEquals('GET', $requests[0]['method']);
    }

    public function testSkipsApextoolboxRequests(): void
    {
        $mockClient = new MockHttpClient([
            new MockResponse('{"data": "test"}', ['http_code' => 200])
        ]);

        $trackedClient = new TrackedHttpClient($mockClient);
        $response = $trackedClient->request('GET', 'https://apextoolbox.com/api/v1/telemetry');

        // Consume the response
        $response->getContent();

        $requests = PayloadCollector::getOutgoingRequests();

        $this->assertCount(0, $requests);
    }
}
