<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Query connection wrapper for Doctrine DBAL 4.x
 */
class QueryConnection implements Connection
{
    public function __construct(
        private readonly Connection $connection,
        private readonly QueryMiddleware $middleware
    ) {
    }

    public function prepare(string $sql): Statement
    {
        return new QueryStatement(
            $this->connection->prepare($sql),
            $this->middleware,
            $sql
        );
    }

    public function query(string $sql): Result
    {
        $start = microtime(true);
        $result = $this->connection->query($sql);
        $duration = (microtime(true) - $start) * 1000;

        $this->logQuery($sql, [], $duration);

        return $result;
    }

    public function exec(string $sql): int|string
    {
        $start = microtime(true);
        $affectedRows = $this->connection->exec($sql);
        $duration = (microtime(true) - $start) * 1000;

        $this->logQuery($sql, [], $duration);

        return $affectedRows;
    }

    public function lastInsertId(): int|string
    {
        return $this->connection->lastInsertId();
    }

    public function beginTransaction(): void
    {
        $this->connection->beginTransaction();
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }

    public function getNativeConnection(): object
    {
        return $this->connection->getNativeConnection();
    }

    private function logQuery(string $sql, array $params, float $duration): void
    {
        // Get caller location
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $caller = $this->findApplicationCaller($backtrace);

        $queryData = [
            'sql' => $sql,
            'bindings' => $params,
            'duration' => round($duration, 4),
            'is_duplicate' => false, // Will be updated during N+1 detection
            'duplicate_count' => 1,
            'is_n1' => false, // Will be detected later
            'n1_group_hash' => null, // Will be set during N+1 detection
            'file_path' => $caller['file'] ?? null,
            'line_number' => $caller['line'] ?? null,
            'occurred_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ];

        $this->middleware->addQuery($queryData);
    }

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
                'function' => $frame['function'] ?? '',
                'class' => $frame['class'] ?? '',
            ];
        }

        return [];
    }
}
