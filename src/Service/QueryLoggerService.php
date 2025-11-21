<?php

namespace ApexToolbox\SymfonyLogger\Service;

use ApexToolbox\SymfonyLogger\Doctrine\QueryLogger;
use ApexToolbox\SymfonyLogger\Doctrine\QueryMiddleware;
use ApexToolbox\SymfonyLogger\PayloadCollector;
use Doctrine\DBAL\Connection;

class QueryLoggerService
{
    private ?QueryLogger $queryLogger = null;
    private ?QueryMiddleware $queryMiddleware = null;
    private bool $enabled = false;
    private bool $isDbal4 = false;

    public function __construct(array $config)
    {
        $this->enabled = $config['enabled'] ?? true;

        // Detect DBAL version: 4.x removed SQLLogger interface
        $this->isDbal4 = !interface_exists('Doctrine\DBAL\Logging\SQLLogger');
    }

    public function enable(Connection $connection): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->isDbal4) {
            // DBAL 4.x: Use middleware API
            if (!$this->queryMiddleware) {
                $this->queryMiddleware = new QueryMiddleware();
                $connection->getConfiguration()->setMiddlewares([$this->queryMiddleware]);
            }
        } else {
            // DBAL 3.x: Use SQLLogger API
            if (!$this->queryLogger) {
                $this->queryLogger = new QueryLogger();
            }
            $connection->getConfiguration()->setSQLLogger($this->queryLogger);
        }
    }

    public function detectAndMarkN1Queries(): void
    {
        if ($this->isDbal4) {
            if ($this->queryMiddleware) {
                $this->queryMiddleware->detectN1Queries();
            }
        } else {
            if ($this->queryLogger) {
                $this->queryLogger->detectN1Queries();
            }
        }
    }

    public function clear(): void
    {
        if ($this->isDbal4) {
            if ($this->queryMiddleware) {
                $this->queryMiddleware->clear();
            }
        } else {
            if ($this->queryLogger) {
                $this->queryLogger->clear();
            }
        }
    }

    public function getQueryLogger(): ?QueryLogger
    {
        return $this->queryLogger;
    }

    public function getQueryMiddleware(): ?QueryMiddleware
    {
        return $this->queryMiddleware;
    }
}
