<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * Computes the DBAL {@see Schema} owned by Periscope.
 *
 * The schema is intentionally single-table: every observed event is an
 * append-only row in {@see tableName()}. Aggregation across retries,
 * transports and handlers happens at read time.
 */
final readonly class SchemaProvider
{
    /**
     * @param non-empty-string $tablePrefix
     * @param non-empty-string|null $pgSchema   Optional PostgreSQL schema name. When non-null, the table prefix is dropped and the table lives as `<pgSchema>.events`.
     */
    public function __construct(
        private string $tablePrefix = 'periscope_',
        private ?string $pgSchema = null,
    ) {
    }

    public function getSchema(): Schema
    {
        $schema = new Schema();
        $this->configureEventsTable($schema->createTable($this->tableName()));

        return $schema;
    }

    /**
     * @return non-empty-string
     */
    public function tableName(): string
    {
        if (null !== $this->pgSchema) {
            return \sprintf('%s.events', $this->pgSchema);
        }

        return $this->tablePrefix . 'events';
    }

    private function configureEventsTable(Table $table): void
    {
        $table->addColumn('id', Types::BIGINT)
            ->setAutoincrement(true)
            ->setNotnull(true);

        $table->addColumn('periscope_id', Types::GUID)
            ->setNotnull(true);

        $table->addColumn('event_type', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);

        $table->addColumn('message_class', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);

        $table->addColumn('transport', Types::STRING)
            ->setLength(128)
            ->setNotnull(false);

        $table->addColumn('bus', Types::STRING)
            ->setLength(64)
            ->setNotnull(false);

        $table->addColumn('handler', Types::STRING)
            ->setLength(255)
            ->setNotnull(false);

        $table->addColumn('payload', Types::JSON)
            ->setNotnull(false);

        $table->addColumn('stamps_summary', Types::JSON)
            ->setNotnull(false);

        $table->addColumn('error_class', Types::STRING)
            ->setLength(255)
            ->setNotnull(false);

        $table->addColumn('error_message', Types::TEXT)
            ->setNotnull(false);

        $table->addColumn('error_trace', Types::TEXT)
            ->setNotnull(false);

        $table->addColumn('duration_ms', Types::INTEGER)
            ->setNotnull(false);

        $table->addColumn('scheduled', Types::BOOLEAN)
            ->setNotnull(true)
            ->setDefault(false);

        $table->addColumn('metadata', Types::JSON)
            ->setNotnull(false);

        $table->addColumn('created_at', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);

        $table->setPrimaryKey(['id']);

        $table->addIndex(['periscope_id'], 'ix_periscope_id');
        $table->addIndex(['created_at'], 'ix_created_at');
        $table->addIndex(['event_type', 'created_at'], 'ix_event_type_created_at');
        $table->addIndex(['message_class', 'created_at'], 'ix_message_class_created_at');
        $table->addIndex(['transport', 'created_at'], 'ix_transport_created_at');
    }
}
