<?php

namespace ApexToolbox\SymfonyLogger\EventListener;

use ApexToolbox\SymfonyLogger\Handler\ApexToolboxExceptionHandler;
use ApexToolbox\SymfonyLogger\Handler\ApexToolboxLogHandler;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Ramsey\Uuid\Uuid;

class LoggerListener implements EventSubscriberInterface
{
    private array $config;
    private ?float $startTime = null;

    public function __construct(
        ParameterBagInterface $parameterBag,
    ) {
        $this->config = $parameterBag->get('apextoolbox') ?? [];
        PayloadCollector::configure($this->config);

        if ($parameterBag->has('kernel.project_dir')) {
            ApexToolboxExceptionHandler::setBasePath($parameterBag->get('kernel.project_dir'));
        }
    }

    public static function getSubscribedEvents(): array
    {
        $events = [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
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

        // Generate unique request ID for correlation (v7 is time-ordered)
        $requestId = Uuid::uuid7()->toString();
        PayloadCollector::setRequestId($requestId);
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        ApexToolboxExceptionHandler::capture($event->getThrowable());
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only collect request/response data if path matches filters
        if ($this->shouldTrack($request)) {
            PayloadCollector::collect($request, $response, $this->startTime ?? microtime(true));
        }

        // Always send if we have ANY data (logs or tracked request)
        PayloadCollector::send();
        PayloadCollector::clear();
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        // Generate unique request ID for console command tracking (v7 is time-ordered)
        $requestId = Uuid::uuid7()->toString();
        PayloadCollector::setRequestId($requestId);

        // Register shutdown function to flush buffer after console command
        register_shutdown_function(function () {
            ApexToolboxLogHandler::flushBuffer($this->config);
        });
    }

    public function onWorkerMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        // Generate unique request ID for each queue message (v7 is time-ordered)
        $requestId = Uuid::uuid7()->toString();
        PayloadCollector::setRequestId($requestId);
    }

    public function onWorkerMessageHandled(WorkerMessageHandledEvent $event): void
    {
        ApexToolboxLogHandler::flushBuffer($this->config);
    }

    public function onWorkerMessageFailed(WorkerMessageFailedEvent $event): void
    {
        ApexToolboxLogHandler::flushBuffer($this->config);
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
        $includes = $this->config['path_filters']['include'] ?? ['*'];
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

}
