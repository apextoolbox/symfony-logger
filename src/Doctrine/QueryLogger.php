<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Doctrine\DBAL\Logging\SQLLogger;

class QueryLogger implements SQLLogger
{
    private array $queries = [];
    private array $currentQuery = [];
    private array $queryPatterns = [];

    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->currentQuery = [
            'sql' => $sql,
            'bindings' => $params,
            'start_time' => microtime(true),
        ];
    }

    public function stopQuery(): void
    {
        if (empty($this->currentQuery)) {
            return;
        }

        $duration = (microtime(true) - $this->currentQuery['start_time']) * 1000; // Convert to milliseconds

        // Normalize SQL for duplicate detection (remove parameter values)
        $normalizedSql = $this->normalizeSql($this->currentQuery['sql']);

        // Check if this is a duplicate query
        $isDuplicate = isset($this->queryPatterns[$normalizedSql]);
        if (!isset($this->queryPatterns[$normalizedSql])) {
            $this->queryPatterns[$normalizedSql] = 0;
        }
        $this->queryPatterns[$normalizedSql]++;

        // Get caller location
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $caller = $this->findApplicationCaller($backtrace);

        $queryData = [
            'sql' => $this->currentQuery['sql'],
            'bindings' => $this->currentQuery['bindings'],
            'duration' => round($duration, 4),
            'is_duplicate' => $isDuplicate,
            'duplicate_count' => $this->queryPatterns[$normalizedSql],
            'is_n1' => false, // Will be detected later
            'n1_group_hash' => null, // Will be set during N+1 detection
            'file_path' => $caller['file'] ?? null,
            'line_number' => $caller['line'] ?? null,
            'occurred_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->queries[] = $queryData;

        // Add to PayloadCollector
        PayloadCollector::addQuery($queryData);

        $this->currentQuery = [];
    }

    /**
     * Get all collected queries
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Normalize SQL for pattern matching (remove values, keep structure)
     */
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

    /**
     * Find the first non-vendor caller in the stack trace
     */
    private function findApplicationCaller(array $backtrace): array
    {
        foreach ($backtrace as $frame) {
            if (!isset($frame['file'])) {
                continue;
            }

            // Skip vendor files
            if (strpos($frame['file'], '/vendor/') !== false) {
                continue;
            }

            return [
                'file' => $frame['file'],
                'line' => $frame['line'] ?? 0,
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
            ];
        }

        return [];
    }

    /**
     * Detect N+1 queries
     */
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
                $normalizedSql = $this->normalizeSql($pattern);
                foreach ($this->queries as &$query) {
                    if ($this->normalizeSql($query['sql']) === $normalizedSql) {
                        $query['is_n1'] = true;
                        $query['n1_group_hash'] = $hash;
                    }
                }
            }
        }

        return array_values($n1Groups);
    }

    /**
     * Clear collected queries
     */
    public function clear(): void
    {
        $this->queries = [];
        $this->queryPatterns = [];
        $this->currentQuery = [];
    }
}
