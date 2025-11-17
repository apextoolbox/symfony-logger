<?php

namespace ApexToolbox\SymfonyLogger\HttpClient;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;
use Throwable;

/**
 * HttpClient decorator that automatically tracks all outgoing HTTP requests
 */
class TrackedHttpClient implements HttpClientInterface
{
    private HttpClientInterface $client;
    private bool $enabled;

    public function __construct(HttpClientInterface $client, array $config = [])
    {
        $this->client = $client;
        $this->enabled = $config['track_http_requests'] ?? true;
    }

    /**
     * @throws Throwable
     * @throws TransportExceptionInterface
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        if (!$this->enabled) {
            return $this->client->request($method, $url, $options);
        }

        $startTime = microtime(true);

        try {
            $response = $this->client->request($method, $url, $options);

            // Wrap the response to track when it's actually consumed
            return new TrackedResponse($response, function () use ($method, $url, $startTime, $response) {
                $this->trackRequest($method, $url, $startTime, $response, null);
            });
        } catch (Throwable $e) {
            $error = $e;
            $this->trackRequest($method, $url, $startTime, null, $error);
            throw $e;
        }
    }

    /**
     * @param ResponseInterface|iterable $responses
     * @param float|null $timeout
     * @return ResponseStreamInterface
     */
    public function stream($responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    /**
     * @param array $options
     * @return self
     */
    public function withOptions(array $options): self
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        return $clone;
    }

    private function trackRequest(
        string $method,
        string $url,
        float $startTime,
        ?ResponseInterface $response,
        ?Throwable $error
    ): void {
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        $data = [
            'type' => 'http_request',
            'method' => strtoupper($method),
            'url' => $url,
            'duration_ms' => round($duration, 2),
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if ($response) {
            try {
                $data['status_code'] = $response->getStatusCode();
                $data['success'] = $response->getStatusCode() < 400;
            } catch (Throwable $e) {
                $data['status_code'] = null;
                $data['success'] = false;
                $data['error'] = $e->getMessage();
            }
        }

        if ($error) {
            $data['success'] = false;
            $data['error'] = $error->getMessage();
            $data['exception_class'] = get_class($error);
        }

        PayloadCollector::addLog($data);
    }
}
