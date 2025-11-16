<?php

namespace ApexToolbox\SymfonyLogger\Service;

use ApexToolbox\SymfonyLogger\Doctrine\QueryLogger;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use Doctrine\DBAL\Connection;

class QueryLoggerService
{
    private ?QueryLogger $queryLogger = null;
    private bool $enabled = false;

    public function __construct(array $config)
    {
        $this->enabled = $config['enabled'] ?? true;
    }

    public function enable(Connection $connection): void
    {
        if (!$this->enabled) {
            return;
        }

        if (!$this->queryLogger) {
            $this->queryLogger = new QueryLogger();
        }

        // Attach logger to Doctrine connection
        $connection->getConfiguration()->setSQLLogger($this->queryLogger);
    }

    public function detectAndMarkN1Queries(): void
    {
        if (!$this->queryLogger) {
            return;
        }

        // Detect N+1 queries
        $n1Groups = $this->queryLogger->detectN1Queries();

        // The queries are already updated with is_n1 flags in the QueryLogger
        // They're already added to PayloadCollector, so nothing more to do here
    }

    public function clear(): void
    {
        if ($this->queryLogger) {
            $this->queryLogger->clear();
        }
    }

    public function getQueryLogger(): ?QueryLogger
    {
        return $this->queryLogger;
    }
}
