<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Doctrine\DBAL\Driver as DriverInterface;
use Doctrine\DBAL\Driver\Middleware as MiddlewareInterface;

/**
 * Query logging middleware for Doctrine DBAL 4.x
 */
class QueryMiddleware implements MiddlewareInterface
{
    private array $queries = [];
    private int $sequenceIndex = 0;

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new QueryDriver($driver, $this);
    }

    public function addQuery(array $queryData): void
    {
        $this->sequenceIndex++;

        $normalizedSql = $this->normalizeSql($queryData['sql']);

        $queryData['normalized_sql'] = $normalizedSql;
        $queryData['pattern_hash'] = md5($normalizedSql);
        $queryData['sequence_index'] = $this->sequenceIndex;

        if (!isset($queryData['occurred_at'])) {
            $queryData['occurred_at'] = (new \DateTime())->format('Y-m-d\TH:i:s.u\Z');
        }

        $this->queries[] = $queryData;
    }

    /**
     * Send all collected queries to the backend for analysis
     */
    public function flush(): void
    {
        if (empty($this->queries)) {
            return;
        }

        foreach ($this->queries as $query) {
            PayloadCollector::addQuery($query);
        }
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function clear(): void
    {
        $this->queries = [];
        $this->sequenceIndex = 0;
    }

    private function normalizeSql(string $sql): string
    {
        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Replace quoted strings with placeholder
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        // Replace numeric values with placeholder
        $sql = preg_replace('/(?<=[=<>!\s,\(])(\s*)\d+(?:\.\d+)?(?=\s*[,\)\s]|$)/i', '$1?', $sql);

        // Replace IN clauses with multiple values
        $sql = preg_replace('/IN\s*\([^)]+\)/i', 'IN (?)', $sql);

        return $sql;
    }
}
