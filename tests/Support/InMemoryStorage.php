<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Support;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\DoctrineStorage;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaProvider;

/**
 * Utility to spin up an in-memory SQLite-backed {@see DoctrineStorage} for
 * command-level tests. Keeping this logic in a single place avoids the Command
 * test suite re-creating the same harness over and over.
 */
final class InMemoryStorage
{
    public readonly DoctrineStorage $storage;

    public readonly Connection $connection;

    public readonly SchemaProvider $provider;

    public function __construct()
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->provider = new SchemaProvider();
        (new SchemaManager($this->connection, $this->provider))->createSchema();

        $this->storage = new DoctrineStorage($this->connection, $this->provider);
    }
}
