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
    private array $config;

    public function __construct(HttpClientInterface $client, array $config = [])
    {
        $this->client = $client;
        $this->enabled = $config['track_http_requests'] ?? true;
        $this->config = $config;
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
            return new TrackedResponse($response, function () use ($method, $url, $options, $startTime, $response) {
                $this->trackRequest($method, $url, $options, $startTime, $response, null);
            });
        } catch (Throwable $e) {
            $error = $e;
            $this->trackRequest($method, $url, $options, $startTime, null, $error);
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
        return str_contains($url, 'apextoolbox.com');
    }

    private function trackRequest(
        string $method,
        string $url,
        array $options,
        float $startTime,
        ?ResponseInterface $response,
        ?Throwable $error
    ): void {
        $duration = (microtime(true) - $startTime) * 1000;

        $data = [
            'method' => strtoupper($method),
            'uri' => $url,
            'headers' => $this->filterHeaders($options['headers'] ?? []),
            'payload' => $this->filterBody($options['body'] ?? $options['json'] ?? []),
            'duration' => round($duration, 2),
            'timestamp' => date('c'),
        ];

        if ($response) {
            try {
                $data['status_code'] = $response->getStatusCode();
                $data['response_headers'] = $response->getHeaders(false);
                $content = $response->getContent(false);
                $decoded = json_decode($content, true);
                $data['response'] = $this->filterResponse($decoded !== null ? $decoded : $content);
            } catch (Throwable $e) {
                $data['status_code'] = null;
                $data['response_headers'] = [];
                $data['response'] = null;
            }
        }

        if ($error) {
            $data['status_code'] = null;
            $data['response_headers'] = [];
            $data['response'] = null;
        }

        PayloadCollector::addOutgoingRequest($data);
    }

    private function filterHeaders(array $headers): array
    {
        $excludeFields = $this->config['headers']['exclude'] ?? [];
        $maskFields = $this->config['headers']['mask'] ?? [];
        return PayloadCollector::recursivelyFilter($headers, $excludeFields, $maskFields);
    }

    private function filterBody($body): array
    {
        if (!is_array($body)) {
            return [];
        }
        $excludeFields = $this->config['body']['exclude'] ?? [];
        $maskFields = $this->config['body']['mask'] ?? [];
        return PayloadCollector::recursivelyFilter($body, $excludeFields, $maskFields);
    }

    private function filterResponse($response): mixed
    {
        if (!is_array($response)) {
            return $response;
        }
        $excludeFields = $this->config['response']['exclude'] ?? [];
        $maskFields = $this->config['response']['mask'] ?? [];
        return PayloadCollector::recursivelyFilter($response, $excludeFields, $maskFields);
    }
}
