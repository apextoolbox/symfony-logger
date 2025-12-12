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
        if (!$this->enabled || $this->shouldSkip($url)) {
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

    public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
    {
        return $this->client->stream($responses, $timeout);
    }

    public function withOptions(array $options): static
    {
        $clone = clone $this;
        $clone->client = $this->client->withOptions($options);
        return $clone;
    }

    private function shouldSkip(string $url): bool
    {
        $telemetryEndpoint = $_ENV['APEX_TOOLBOX_DEV_ENDPOINT'] ?? 'https://apextoolbox.com/api/v1/telemetry';

        return str_starts_with($url, $telemetryEndpoint) || str_contains($url, 'apextoolbox.com');
    }

    private function trackRequest(
        string $method,
        string $url,
        float $startTime,
        ?ResponseInterface $response,
        ?Throwable $error
    ): void {
        $duration = (microtime(true) - $startTime) * 1000;

        $data = [
            'method' => strtoupper($method),
            'uri' => $url,
            'duration' => round($duration, 2),
            'timestamp' => date('c'),
        ];

        if ($response) {
            try {
                $data['status_code'] = $response->getStatusCode();
            } catch (Throwable $e) {
                $data['status_code'] = null;
            }
        }

        if ($error) {
            $data['status_code'] = null;
        }

        PayloadCollector::addOutgoingRequest($data);
    }
}
