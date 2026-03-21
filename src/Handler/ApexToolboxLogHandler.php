<?php

namespace ApexToolbox\SymfonyLogger\Handler;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Logger;
use Throwable;

class ApexToolboxLogHandler extends AbstractProcessingHandler
{
    private array $config;

    private const SKIP_CLASS_PREFIXES = [
        'Symfony\\',
        'Doctrine\\',
        'Twig\\',
        'Monolog\\',
    ];

    /**
     * @param array $config
     * @param int|string $level Monolog 2: int, Monolog 3: Level enum
     * @param bool $bubble
     */
    public function __construct(array $config, $level = 100, bool $bubble = true)
    {
        parent::__construct($level, $bubble);

        $this->config = $config;

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

        // Skip framework-internal logs (Symfony, Doctrine, Twig, etc.)
        if ($data['source_class'] && $this->isFrameworkClass($data['source_class'])) {
            return;
        }

        PayloadCollector::addLog($data);
    }

    private function isFrameworkClass(string $class): bool
    {
        foreach (self::SKIP_CLASS_PREFIXES as $prefix) {
            if (str_starts_with($class, $prefix)) {
                return true;
            }
        }
        return false;
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
        $callType = $extra['callType'] ?? null;
        $file = $extra['file'] ?? null;
        $line = $extra['line'] ?? null;

        // Determine execution type
        $type = php_sapi_name() === 'cli' ? 'console' : 'http';

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
            'callType' => $callType,
            'type' => $type,
            'file' => $file,
            'line' => $line,
        ];
    }

    public static function flushBuffer(array $config): void
    {
        PayloadCollector::configure($config);
        PayloadCollector::send();
        PayloadCollector::clear();
    }
}