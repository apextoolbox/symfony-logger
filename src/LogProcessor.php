<?php

namespace ApexToolbox\SymfonyLogger;

class LogProcessor
{
    public function __invoke($record)
    {
        // Handle both Monolog 2.x and 3.x records
        if (is_array($record)) {
            // Monolog 2.x format
            LogBuffer::add([
                'time' => $record['datetime'] ?? new \DateTime(),
                'level' => strtolower($record['level_name'] ?? 'info'),
                'message' => $record['message'] ?? '',
                'context' => $record['context'] ?? [],
            ]);
        } else {
            // Monolog 3.x LogRecord format
            LogBuffer::add([
                'time' => $record->datetime,
                'level' => is_object($record->level) ? strtolower($record->level->getName()) : strtolower($record->level),
                'message' => $record->message,
                'context' => $record->context,
            ]);
        }

        return $record;
    }
}