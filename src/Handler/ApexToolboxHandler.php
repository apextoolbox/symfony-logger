<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\ContextDetector;
use ApexToolbox\SymfonyLogger\LogBuffer;
use ApexToolbox\SymfonyLogger\SourceClassExtractor;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\LogRecord;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApexToolboxHandler extends AbstractProcessingHandler
{
    private array $config;
    private ContextDetector $contextDetector;
    private SourceClassExtractor $sourceClassExtractor;
    private HttpClientInterface $httpClient;
    private static bool $sentOnShutdown = false;

    public function __construct(
        array $config,
        ContextDetector $contextDetector,
        SourceClassExtractor $sourceClassExtractor,
        ?HttpClientInterface $httpClient = null
    ) {
        parent::__construct();
        
        $this->config = $config;
        $this->contextDetector = $contextDetector;
        $this->sourceClassExtractor = $sourceClassExtractor;
        $this->httpClient = $httpClient ?? HttpClient::create(['timeout' => 1]);
        
        // Register shutdown function for non-HTTP contexts
        if (!self::$sentOnShutdown) {
            register_shutdown_function([$this, 'sendLogsOnShutdown']);
            self::$sentOnShutdown = true;
        }
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->shouldHandle($record)) {
            return;
        }

        $type = $this->contextDetector->detectType();
        $sourceClass = $this->sourceClassExtractor->extractSourceClass($record->context);

        $logData = [
            'time' => $record->datetime,
            'level' => $this->getLevelName($record),
            'message' => $record->message,
            'context' => $record->context,
            'type' => $type,
            'source_class' => $sourceClass,
        ];

        // For HTTP requests, add to buffer (will be sent by LoggerListener)
        if ($type === 'http') {
            LogBuffer::add($logData);
            return;
        }

        // For console/queue contexts, handle differently
        $this->handleNonHttpLog($logData, $type);
    }

    private function shouldHandle(LogRecord $record): bool
    {
        // Check if universal logging is enabled
        if (!($this->config['universal_logging']['enabled'] ?? false)) {
            return false;
        }

        // Check if this log type is enabled
        $enabledTypes = $this->config['universal_logging']['types'] ?? ['http', 'console', 'queue'];
        $currentType = $this->contextDetector->detectType();
        
        return in_array($currentType, $enabledTypes, true);
    }

    private function getLevelName(LogRecord $record): string
    {
        // Handle both Monolog 2.x and 3.x
        if (is_object($record->level)) {
            return strtolower($record->level->getName());
        }
        
        return strtolower((string) $record->level);
    }

    private function handleNonHttpLog(array $logData, string $type): void
    {
        // For non-HTTP contexts, we need to send logs immediately or buffer them
        // to be sent on shutdown to avoid blocking the main process
        
        if ($type === 'console') {
            // For console commands, buffer and send on shutdown
            LogBuffer::add($logData);
        } elseif ($type === 'queue') {
            // For queue workers, send immediately but asynchronously
            $this->sendLogAsync($logData, $type);
        }
    }

    private function sendLogAsync(array $logData, string $type): void
    {
        try {
            // Send single log entry for queue workers
            $this->httpClient->request('POST', $this->getEndpointUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => $type,
                    'logs' => [$logData],
                    'timestamp' => (new \DateTime())->format('c'),
                ],
            ]);
        } catch (\Throwable $e) {
            // Silent failure - never break the application
        }
    }

    public function sendLogsOnShutdown(): void
    {
        $logs = LogBuffer::flush();
        if (empty($logs)) {
            return;
        }

        $type = $this->contextDetector->detectType();
        
        // Only send for console contexts (HTTP is handled by LoggerListener)
        if ($type !== 'console') {
            return;
        }

        try {
            $this->httpClient->request('POST', $this->getEndpointUrl(), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->config['token'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'type' => $type,
                    'logs' => $logs,
                    'timestamp' => (new \DateTime())->format('c'),
                ],
            ]);
        } catch (\Throwable $e) {
            // Silent failure - never break the application
        }
    }

    private function getEndpointUrl(): string
    {
        // Use same endpoint logic as LoggerListener
        if (isset($_ENV['APEX_TOOLBOX_DEV_ENDPOINT']) && ($_ENV['APEX_TOOLBOX_DEV_MODE'] ?? '') === 'true') {
            return $_ENV['APEX_TOOLBOX_DEV_ENDPOINT'];
        }

        return 'https://apextoolbox.com/api/v1/logs';
    }
}