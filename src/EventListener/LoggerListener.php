<?php

namespace ApexToolbox\SymfonyLogger\EventListener;

use ApexToolbox\SymfonyLogger\Handler\ApexToolboxLogHandler;
use ApexToolbox\SymfonyLogger\Handler\ApexToolboxExceptionHandler;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use ApexToolbox\SymfonyLogger\Service\QueryLoggerService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Uid\Uuid;

class LoggerListener implements EventSubscriberInterface
{
    private array $config;
    private ?float $startTime = null;
    private ?QueryLoggerService $queryLoggerService = null;
    private ?Connection $connection = null;

    public function __construct(
        ParameterBagInterface $parameterBag,
        ?QueryLoggerService $queryLoggerService = null,
        ?Connection $connection = null
    ) {
        $this->config = $parameterBag->get('apex_toolbox') ?? [];
        $this->queryLoggerService = $queryLoggerService;
        $this->connection = $connection;
        PayloadCollector::configure($this->config);
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST => ['onKernelRequest', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            ConsoleEvents::COMMAND => ['onConsoleCommand', 0],
        ];

        if (class_exists(WorkerMessageReceivedEvent::class)) {
            $events[WorkerMessageReceivedEvent::class] = ['onWorkerMessageReceived', 0];
        }

        if (class_exists(WorkerMessageHandledEvent::class)) {
            $events[WorkerMessageHandledEvent::class] = ['onWorkerMessageHandled', 0];
        }

        if (class_exists(WorkerMessageFailedEvent::class)) {
            $events[WorkerMessageFailedEvent::class] = ['onWorkerMessageFailed', 0];
        }

        return $events;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->startTime = microtime(true);

        // Generate unique request ID for correlation
        $requestId = Uuid::v7()->toRfc4122();
        PayloadCollector::setRequestId($requestId);

        // Enable query logging if Doctrine is available
        if ($this->queryLoggerService && $this->connection) {
            $this->queryLoggerService->enable($this->connection);
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Detect N+1 queries before sending (always, even if request not tracked)
        if ($this->queryLoggerService) {
            $this->queryLoggerService->detectAndMarkN1Queries();
        }

        // Only collect request/response data if path matches filters
        if ($this->shouldTrack($request)) {
            PayloadCollector::collect($request, $response, $this->startTime);
        }

        // Always send if we have ANY data (logs, exceptions, queries, or tracked request)
        PayloadCollector::send();
        PayloadCollector::clear();

        // Clear query logger for next request
        if ($this->queryLoggerService) {
            $this->queryLoggerService->clear();
        }
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Always capture exceptions, regardless of path filters
        // Exceptions are critical and should never be lost
        ApexToolboxExceptionHandler::capture($event->getThrowable());
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        // Generate unique request ID for console command tracking
        $requestId = Uuid::v7()->toRfc4122();
        PayloadCollector::setRequestId($requestId);

        // Enable query logging if Doctrine is available
        if ($this->queryLoggerService && $this->connection) {
            $this->queryLoggerService->enable($this->connection);
        }

        // Register shutdown function to flush buffer after console command
        register_shutdown_function(function () {
            // Detect N+1 queries before sending
            if ($this->queryLoggerService) {
                $this->queryLoggerService->detectAndMarkN1Queries();
            }

            ApexToolboxLogHandler::flushBuffer($this->config);

            // Clear query logger
            if ($this->queryLoggerService) {
                $this->queryLoggerService->clear();
            }
        });
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        // Generate unique request ID for each queue message
        $requestId = Uuid::v7()->toRfc4122();
        PayloadCollector::setRequestId($requestId);

        // Enable query logging if Doctrine is available
        if ($this->queryLoggerService && $this->connection) {
            $this->queryLoggerService->enable($this->connection);
        }
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        // Detect N+1 queries before sending
        if ($this->queryLoggerService) {
            $this->queryLoggerService->detectAndMarkN1Queries();
        }

        ApexToolboxLogHandler::flushBuffer($this->config);

        // Clear query logger
        if ($this->queryLoggerService) {
            $this->queryLoggerService->clear();
        }
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        // Detect N+1 queries before sending
        if ($this->queryLoggerService) {
            $this->queryLoggerService->detectAndMarkN1Queries();
        }

        ApexToolboxLogHandler::flushBuffer($this->config);

        // Clear query logger
        if ($this->queryLoggerService) {
            $this->queryLoggerService->clear();
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
        
        // Remove leading slash from path for consistent matching
        $normalizedPath = ltrim($path, '/');
        $normalizedPattern = ltrim($pattern, '/');
        
        // Use fnmatch for pattern matching
        return fnmatch($normalizedPattern, $normalizedPath);
    }

    protected function getRealIpAddress(Request $request): string
    {
        $headers = [
            'CF-Connecting-IP',     // Cloudflare
            'X-Forwarded-For',      // Standard proxy header
            'X-Real-IP',            // Nginx proxy
            'X-Client-IP',          // Apache mod_proxy
            'HTTP_X_FORWARDED_FOR', // Alternative format
            'HTTP_X_REAL_IP',       // Alternative format
            'HTTP_CF_CONNECTING_IP', // Alternative Cloudflare format
        ];

        foreach ($headers as $header) {
            $value = $request->headers->get($header) ?? $_SERVER[$header] ?? null;
            
            if ($value) {
                // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
                $ips = explode(',', $value);
                $ip = trim($ips[0]);
                
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback to request IP
        return $request->getClientIp() ?? '127.0.0.1';
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

    /**
     * @return array|string|null
     */
    protected function getResponseContent(Response $response)
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