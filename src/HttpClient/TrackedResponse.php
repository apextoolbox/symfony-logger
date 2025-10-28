<?php

namespace ApexToolbox\SymfonyLogger\HttpClient;

use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Response wrapper that tracks when the response is consumed
 */
class TrackedResponse implements ResponseInterface
{
    private ResponseInterface $response;
    private $trackingCallback;
    private bool $tracked = false;

    public function __construct(ResponseInterface $response, callable $trackingCallback)
    {
        $this->response = $response;
        $this->trackingCallback = $trackingCallback;
    }

    public function getStatusCode(): int
    {
        $this->track();
        return $this->response->getStatusCode();
    }

    public function getHeaders(bool $throw = true): array
    {
        $this->track();
        return $this->response->getHeaders($throw);
    }

    public function getContent(bool $throw = true): string
    {
        $this->track();
        return $this->response->getContent($throw);
    }

    public function toArray(bool $throw = true): array
    {
        $this->track();
        return $this->response->toArray($throw);
    }

    public function cancel(): void
    {
        $this->response->cancel();
    }

    public function getInfo(?string $type = null): mixed
    {
        return $this->response->getInfo($type);
    }

    private function track(): void
    {
        if (!$this->tracked) {
            $this->tracked = true;
            ($this->trackingCallback)();
        }
    }

    public function __destruct()
    {
        // Ensure tracking happens even if response is never consumed
        $this->track();
    }
}
