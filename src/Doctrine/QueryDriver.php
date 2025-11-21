<?php

namespace ApexToolbox\SymfonyLogger\Doctrine;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as ConnectionInterface;

/**
 * Query driver wrapper for Doctrine DBAL 4.x
 */
class QueryDriver implements Driver
{
    public function __construct(
        private readonly Driver $driver,
        private readonly QueryMiddleware $middleware
    ) {
    }

    public function connect(array $params): ConnectionInterface
    {
        $connection = $this->driver->connect($params);
        return new QueryConnection($connection, $this->middleware);
    }

    public function getDatabasePlatform(): \Doctrine\DBAL\Platforms\AbstractPlatform
    {
        return $this->driver->getDatabasePlatform();
    }

    public function getSchemaManager(\Doctrine\DBAL\Connection $conn, \Doctrine\DBAL\Platforms\AbstractPlatform $platform): \Doctrine\DBAL\Schema\AbstractSchemaManager
    {
        return $this->driver->getSchemaManager($conn, $platform);
    }
}
