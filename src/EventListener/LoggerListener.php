<?php

namespace ApexToolbox\SymfonyLogger\EventListener;

use ApexToolbox\SymfonyLogger\LogBuffer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class LoggerListener implements EventSubscriberInterface
{
    private array $config;
    private ?float $startTime = null;
    private KernelInterface $kernel;

    public function __construct(ParameterBagInterface $parameterBag, KernelInterface $kernel)
    {
        $this->config = $parameterBag->get('apex_toolbox_logger') ?? [];
        $this->kernel = $kernel;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->startTime = microtime(true);
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if ($this->shouldTrack($request)) {
            $data = $this->prepareTrackingData($request, $response);
            $this->sendSyncRequest($data);
        }
    }

    protected function shouldTrack(Request $request): bool
    {
        if (!($this->config['enabled'] ?? true)) {
            return false;
        }

        if (empty($this->config['token'] ?? '')) {
            return false;
        }

        $path = $request->getPathInfo();
        $includes = $this->config['path_filters']['include'] ?? ['api/*'];
        $excludes = $this->config['path_filters']['exclude'] ?? [];

        // Check excludes first
        foreach ($excludes as $pattern) {
            if ($this->matchesPattern($pattern, $path)) {
                return false;
            }
        }

        // Check includes
        foreach ($includes as $pattern) {
            if ($this->matchesPattern($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesPattern(string $pattern, string $path): bool
    {
        // Handle wildcard '*' to match everything
        if ($pattern === '*') {
            return true;
        }
        
        // Use fnmatch for pattern matching
        return fnmatch($pattern, $path);
    }

    protected function prepareTrackingData(Request $request, Response $response): array
    {
        return [
            'method' => $request->getMethod(),
            'url' => $request->getUri(),
            'headers' => $this->filterHeaders($request->headers->all()),
            'body' => $this->filterBody($request->request->all()),
            'status' => $response->getStatusCode(),
            'response' => $this->getResponseContent($response),
        ];
    }

    protected function filterHeaders(array $headers): array
    {
        if (!($this->config['headers']['include_sensitive'] ?? false)) {
            $excludeHeaders = $this->config['headers']['exclude'] ?? ['authorization', 'x-api-key', 'cookie'];
            
            $filtered = [];
            foreach ($headers as $key => $value) {
                if (!in_array(strtolower($key), array_map('strtolower', $excludeHeaders))) {
                    $filtered[$key] = $value;
                }
            }
            
            return $filtered;
        }

        return $headers;
    }

    protected function filterBody(array $body): array
    {
        $excludeFields = $this->config['body']['exclude'] ?? ['password', 'password_confirmation', 'token', 'secret'];
        
        $filtered = [];
        foreach ($body as $key => $value) {
            if (!in_array($key, $excludeFields)) {
                $filtered[$key] = $value;
            }
        }
        
        $maxSize = $this->config['body']['max_size'] ?? 10240;
        $serialized = json_encode($filtered);
        
        if (strlen($serialized) > $maxSize) {
            return ['_truncated' => 'Body too large, truncated'];
        }
        
        return $filtered;
    }

    protected function getResponseContent(Response $response): array|string|null
    {
        $content = $response->getContent();
        $maxSize = $this->config['body']['max_size'] ?? 10240;
        
        if (strlen($content) > $maxSize) {
            return substr($content, 0, $maxSize) . '... [truncated]';
        }
        
        // Try to decode JSON response
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        
        return $content;
    }

    protected function sendSyncRequest(array $data): void
    {
        try {
            $url = $this->getEndpointUrl();
            
            $client = HttpClient::create([
                'timeout' => 1,
            ]);

            $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'method' => $data['method'],
                    'uri' => $data['url'],
                    'headers' => $data['headers'],
                    'payload' => $data['body'],
                    'status_code' => $data['status'],
                    'response' => $data['response'],
                    'duration' => $this->startTime ? microtime(true) - $this->startTime : 0,
                    'logs' => LogBuffer::flush(),
                ],
            ]);
        } catch (\Throwable $e) {
            // Silently fail
        }
    }

    protected function getEndpointUrl(): string
    {
        // Only override endpoint if explicitly set for ApexToolbox package development
        // This requires both the dev endpoint AND a special dev flag to be set
        if (isset($_ENV['APEX_TOOLBOX_DEV_ENDPOINT']) && ($_ENV['APEX_TOOLBOX_DEV_MODE'] ?? '') === 'true') {
            return $_ENV['APEX_TOOLBOX_DEV_ENDPOINT'];
        }

        // Production endpoint - hardcoded (used by all users, including their local dev)
        return 'https://apextoolbox.com/api/v1/logs';
    }
}