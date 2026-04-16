<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage\Doctrine;

use Doctrine\DBAL\Connection;

/**
 * Creates and drops Periscope's schema on a given DBAL connection.
 *
 * The bundle does not rely on a migrations framework: schema evolution is
 * owned by Periscope itself and exposed through `periscope:install` /
 * `periscope:uninstall` commands.
 */
final readonly class SchemaManager
{
    public function __construct(
        private Connection $connection,
        private SchemaProvider $schemaProvider,
    ) {
    }

    /**
     * Creates every table Periscope needs. Safe to call on an already
     * provisioned database: existing tables are left untouched, missing
     * tables are created.
     *
     * @return list<string> the SQL statements that were actually executed
     */
    public function createSchema(): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $targetSchema = $this->schemaProvider->getSchema();

        $existingTables = $this->connection->createSchemaManager()->listTableNames();
        $targetTableName = $this->schemaProvider->tableName();

        if (\in_array($targetTableName, $existingTables, true)) {
            return [];
        }

        $statements = $targetSchema->toSql($platform);
        foreach ($statements as $sql) {
            $this->connection->executeStatement($sql);
        }

        return $statements;
    }

    /**
     * Returns the SQL that {@see createSchema()} would execute, without
     * touching the database. Useful for the `--dump-sql` option or for
     * integrating Periscope's schema into an existing migration pipeline.
     *
     * @return list<string>
     */
    public function toCreateSql(): array
    {
        $existingTables = $this->connection->createSchemaManager()->listTableNames();
        if (\in_array($this->schemaProvider->tableName(), $existingTables, true)) {
            return [];
        }

        return $this->schemaProvider->getSchema()->toSql($this->connection->getDatabasePlatform());
    }

    /**
     * Drops every table owned by Periscope. Intended for development and the
     * `periscope:uninstall` command.
     *
     * @return list<string> the SQL statements that were actually executed
     */
    public function dropSchema(): array
    {
        $platform = $this->connection->getDatabasePlatform();
        $targetSchema = $this->schemaProvider->getSchema();

        $existingTables = $this->connection->createSchemaManager()->listTableNames();
        $targetTableName = $this->schemaProvider->tableName();

        if (!\in_array($targetTableName, $existingTables, true)) {
            return [];
        }

        $statements = $targetSchema->toDropSql($platform);
        foreach ($statements as $sql) {
            $this->connection->executeStatement($sql);
        }

        return $statements;
    }
}
