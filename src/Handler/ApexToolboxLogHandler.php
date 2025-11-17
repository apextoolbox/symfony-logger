<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Logger;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class ApexToolboxLogHandler extends AbstractProcessingHandler
{
    private array $config;
    private HttpClientInterface $httpClient;

    /**
     * @param array $config
     * @param HttpClientInterface|null $httpClient
     * @param int|string $level Monolog 2: int, Monolog 3: Level enum
     * @param bool $bubble
     */
    public function __construct(array $config, ?HttpClientInterface $httpClient = null, $level = 100, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->config = $config;
        $this->httpClient = $httpClient ?? HttpClient::create(['timeout' => 2]);

        // Add introspection processor to capture source information (file, line, class, function)
        $this->pushProcessor(new IntrospectionProcessor(Logger::DEBUG, ['Monolog\\', 'Symfony\\Component\\HttpKernel\\Log\\']));

        // Configure PayloadCollector
        PayloadCollector::configure($config);
    }

    /**
     * Write log record (compatible with both Monolog 2.x and 3.x)
     *
     * @param array $record Monolog 2.x uses array, Monolog 3.x uses LogRecord but accepts array
     * @return void
     */
    protected function write($record): void
    {
        if (!($this->config['token'] ?? null)) {
            return;
        }

        $data = $this->prepareLogData($record);
        PayloadCollector::addLog($data);
    }

    /**
     * Prepare log data (compatible with both Monolog 2.x array and 3.x LogRecord)
     *
     * @param array $record
     * @return array
     */
    protected function prepareLogData($record): array
    {
        // Handle both Monolog 2.x (array) and 3.x (LogRecord/array)
        $isMonolog3 = is_object($record);

        // Extract source information from extra (if available from IntrospectionProcessor)
        $extra = $isMonolog3 ? ($record->extra ?? []) : ($record['extra'] ?? []);

        // Get source information
        $sourceClass = $extra['class'] ?? null;
        $function = $extra['function'] ?? null;
        $callType = $extra['type'] ?? null;
        $file = $extra['file'] ?? null;
        $line = $extra['line'] ?? null;

        return [
            'level' => strtoupper($isMonolog3 ? $record->level->getName() : $record['level_name']),
            'message' => $isMonolog3 ? $record->message : $record['message'],
            'context' => $isMonolog3 ? $record->context : ($record['context'] ?? []),
            'timestamp' => $isMonolog3
                ? $record->datetime->format('Y-m-d H:i:s')
                : $record['datetime']->format('Y-m-d H:i:s'),
            'channel' => $isMonolog3 ? $record->channel : $record['channel'],
            'source_class' => $sourceClass,
            'function' => $function,
            'file' => $file,
            'line' => $line,
        ];
    }

    public static function flushBuffer(array $config, ?HttpClientInterface $httpClient = null): void
    {
        PayloadCollector::configure($config);
        PayloadCollector::send();
        PayloadCollector::clear();
    }
}