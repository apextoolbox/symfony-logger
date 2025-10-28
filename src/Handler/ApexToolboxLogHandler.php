<?php

namespace ApexToolbox\Symfony\Handler;

use ApexToolbox\Symfony\PayloadCollector;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ApexToolboxLogHandler extends AbstractProcessingHandler
{
    private array $config;
    private HttpClientInterface $httpClient;

    public function __construct(array $config, ?HttpClientInterface $httpClient = null, $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->config = $config;
        $this->httpClient = $httpClient ?? HttpClient::create(['timeout' => 2]);

        // Configure PayloadCollector
        PayloadCollector::configure($config);
    }

    protected function write(LogRecord $record): void
    {
        if (!($this->config['token'] ?? null)) {
            return;
        }

        $data = $this->prepareLogData($record);
        PayloadCollector::addLog($data);
    }

    protected function prepareLogData(LogRecord $record): array
    {
        return [
        'level' => strtoupper($record->level->getName()),
            'message' => $record->message,
            'context' => $record->context,
            'timestamp' => $record->datetime->format('Y-m-d H:i:s'),
            'channel' => $record->channel,
            'source_class' => $record->extra['class'] ?? null,
            'function' => $record->extra['function'] ?? null,
            'callType' => $record->extra['callType'] ?? null,
        ];
    }

    public static function flushBuffer(array $config, ?HttpClientInterface $httpClient = null): void
    {
        PayloadCollector::configure($config);
        PayloadCollector::send();
        PayloadCollector::clear();
    }
}