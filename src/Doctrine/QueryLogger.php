<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use ApexToolbox\SymfonyLogger\PayloadCollector;
use Doctrine\DBAL\Logging\SQLLogger;

class QueryLogger implements SQLLogger
{
    private array $queries = [];
    private array $currentQuery = [];
    private int $sequenceIndex = 0;

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

        $this->sequenceIndex++;

        $duration = (microtime(true) - $this->currentQuery['start_time']) * 1000;
        $normalizedSql = $this->normalizeSql($this->currentQuery['sql']);

        $caller = $this->findApplicationCaller(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15));

        $queryData = [
            'sql' => $this->currentQuery['sql'],
            'normalized_sql' => $normalizedSql,
            'pattern_hash' => md5($normalizedSql),
            'duration' => round($duration, 4),
            'file_path' => $caller['file'] ?? null,
            'line_number' => $caller['line'] ?? null,
            'sequence_index' => $this->sequenceIndex,
            'occurred_at' => (new \DateTime())->format('Y-m-d\TH:i:s.u\Z'),
        ];

        $this->queries[] = $queryData;
        $this->currentQuery = [];
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

    /**
     * Get all collected queries
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Clear collected queries
     */
    public function clear(): void
    {
        $this->queries = [];
        $this->sequenceIndex = 0;
        $this->currentQuery = [];
    }

    /**
     * Normalize SQL for pattern matching (remove values, keep structure)
     */
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
            if (str_contains($frame['file'], '/vendor/')) {
                continue;
            }

            return [
                'file' => $frame['file'],
                'line' => $frame['line'] ?? 0,
            ];
        }

        return [];
    }
}
