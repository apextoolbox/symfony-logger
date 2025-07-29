<?php

namespace ApexToolbox\SymfonyLogger\EventListener;

use ApexToolbox\SymfonyLogger\LogBuffer;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\ConsoleEvents;
use Monolog\LogRecord;

class LogSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConsoleEvents::COMMAND => 'onConsoleCommand',
        ];
    }

    public function onConsoleCommand(ConsoleCommandEvent $event): void
    {
        // Add log entry to buffer when console commands are executed
        LogBuffer::add([
            'time' => new \DateTime(),
            'level' => 'info',
            'message' => 'Console command executed: ' . $event->getCommand()?->getName(),
            'context' => [
                'command' => $event->getCommand()?->getName(),
                'input' => (string) $event->getInput(),
            ],
        ]);
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        // Handle Monolog records
        LogBuffer::add([
            'time' => $record->datetime,
            'level' => is_object($record->level) ? $record->level->getName() : strtolower($record->level),
            'message' => $record->message,
            'context' => $record->context,
        ]);

        return $record;
    }
}