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
    private array $queryPatterns = [];

    public function wrap(DriverInterface $driver): DriverInterface
    {
        return new QueryDriver($driver, $this);
    }

    public function addQuery(array $queryData): void
    {
        $this->queries[] = $queryData;

        // Track pattern for N+1 detection
        $normalizedSql = $this->normalizeSql($queryData['sql']);
        if (!isset($this->queryPatterns[$normalizedSql])) {
            $this->queryPatterns[$normalizedSql] = 0;
        }
        $this->queryPatterns[$normalizedSql]++;

        // Add to PayloadCollector
        PayloadCollector::addQuery($queryData);
    }

    public function getQueries(): array
    {
        return $this->queries;
    }

    public function detectN1Queries(): array
    {
        $n1Groups = [];

        foreach ($this->queryPatterns as $pattern => $count) {
            // If same query pattern executed more than 3 times, it's likely N+1
            if ($count > 3) {
                $hash = hash('sha256', $pattern);
                $n1Groups[$hash] = [
                    'pattern' => $pattern,
                    'count' => $count,
                    'hash' => $hash,
                ];

                // Mark queries as N+1
                foreach ($this->queries as &$query) {
                    if ($this->normalizeSql($query['sql']) === $pattern) {
                        $query['is_n1'] = true;
                        $query['n1_group_hash'] = $hash;
                    }
                }
            }
        }

        return array_values($n1Groups);
    }

    public function clear(): void
    {
        $this->queries = [];
        $this->queryPatterns = [];
    }

    private function normalizeSql(string $sql): string
    {
        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        // Replace numeric values with placeholder
        $sql = preg_replace('/\b\d+\b/', '?', $sql);

        // Replace quoted strings with placeholder
        $sql = preg_replace("/'[^']*'/", '?', $sql);

        return $sql;
    }
}
