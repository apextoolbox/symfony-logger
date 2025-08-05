<?php

namespace ApexToolbox\SymfonyLogger;

use Symfony\Component\HttpFoundation\RequestStack;

class ContextDetector
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function detectType(): string
    {
        // Check if we're in an HTTP request context
        if ($this->requestStack->getCurrentRequest() !== null) {
            return 'http';
        }

        // Check if we're running in CLI mode
        if (php_sapi_name() === 'cli') {
            // Try to detect if this is a queue worker
            if ($this->isQueueWorker()) {
                return 'queue';
            }
            
            return 'console';
        }

        // Fallback for other contexts (e.g., web server CLI scripts)
        return 'http';
    }

    private function isQueueWorker(): bool
    {
        // Check common queue worker indicators
        $argv = $_SERVER['argv'] ?? [];
        
        // Symfony Messenger
        if (in_array('messenger:consume', $argv, true)) {
            return true;
        }
        
        // Laravel queue workers
        if (in_array('queue:work', $argv, true) || in_array('queue:listen', $argv, true)) {
            return true;
        }
        
        // Check for common queue-related environment variables
        $queueEnvVars = [
            'QUEUE_WORKER',
            'MESSENGER_WORKER',
            'SYMFONY_MESSENGER_WORKER'
        ];
        
        foreach ($queueEnvVars as $envVar) {
            if (!empty($_ENV[$envVar]) || !empty($_SERVER[$envVar])) {
                return true;
            }
        }
        
        // Check process title if available
        if (function_exists('cli_get_process_title')) {
            $title = cli_get_process_title();
            if ($title && (
                str_contains($title, 'queue') ||
                str_contains($title, 'worker') ||
                str_contains($title, 'messenger')
            )) {
                return true;
            }
        }
        
        return false;
    }
}