<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

/**
 * Query statement wrapper for Doctrine DBAL 4.x
 */
class QueryStatement implements Statement
{
    private array $params = [];
    private float $startTime = 0;

    public function __construct(
        private readonly Statement $statement,
        private readonly QueryMiddleware $middleware,
        private readonly string $sql
    ) {
    }

    public function bindValue(int|string $param, mixed $value, ParameterType $type = ParameterType::STRING): void
    {
        $this->params[$param] = $value;
        $this->statement->bindValue($param, $value, $type);
    }

    public function execute(): Result
    {
        $this->startTime = microtime(true);
        $result = $this->statement->execute();
        $duration = (microtime(true) - $this->startTime) * 1000;

        $this->logQuery($duration);

        return $result;
    }

    private function logQuery(float $duration): void
    {
        // Get caller location
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
        $caller = $this->findApplicationCaller($backtrace);

        $queryData = [
            'sql' => $this->sql,
            'bindings' => $this->params,
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
