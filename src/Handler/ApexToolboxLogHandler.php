<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\LogBuffer;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Ramsey\Uuid\Uuid;
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
    }

    protected function write(LogRecord $record): void
    {
        if (!($this->config['token'] ?? null)) {
            return;
        }

        $data = $this->prepareLogData($record);

        LogBuffer::add($data);
        LogBuffer::add($data, LogBuffer::HTTP_CATEGORY);
    }

    protected function prepareLogData(LogRecord $record): array
    {
        return [
            'level' => $record->level->getName(),
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
        if (empty(LogBuffer::get())) {
            return;
        }

        $token = $config['token'] ?? null;
        if (!$token) {
            return;
        }

        $client = $httpClient ?? HttpClient::create(['timeout' => 2]);
        $url = $_ENV['APEX_TOOLBOX_DEV_ENDPOINT'] ?? 'https://apextoolbox.com/api/v1/logs';

        try {
            $client->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'logs_trace_id' => (string) Uuid::uuid7(),
                    'logs' => LogBuffer::flush()
                ],
            ]);
        } catch (Throwable $e) {
            // Silently fail...
        }
    }
}