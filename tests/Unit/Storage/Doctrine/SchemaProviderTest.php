<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Storage\Doctrine;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaProvider;

#[CoversClass(SchemaProvider::class)]
final class SchemaProviderTest extends TestCase
{
    public function testDefaultTableName(): void
    {
        self::assertSame('periscope_events', (new SchemaProvider())->tableName());
    }

    public function testCustomPrefix(): void
    {
        self::assertSame('obs_events', (new SchemaProvider(tablePrefix: 'obs_'))->tableName());
    }

    public function testPgSchemaTakesPrecedenceOverPrefix(): void
    {
        $provider = new SchemaProvider(tablePrefix: 'periscope_', pgSchema: 'periscope');

        self::assertSame('periscope.events', $provider->tableName());
    }

    public function testSchemaExposesEventsTableWithRequiredColumns(): void
    {
        $schema = (new SchemaProvider())->getSchema();

        self::assertTrue($schema->hasTable('periscope_events'));

        $table = $schema->getTable('periscope_events');

        foreach ([
            'id',
            'periscope_id',
            'event_type',
            'message_class',
            'transport',
            'bus',
            'handler',
            'payload',
            'stamps_summary',
            'error_class',
            'error_message',
            'error_trace',
            'duration_ms',
            'scheduled',
            'metadata',
            'created_at',
        ] as $column) {
            self::assertTrue($table->hasColumn($column), \sprintf('Column %s should exist', $column));
        }
    }

    public function testSchemaDeclaresExpectedIndexes(): void
    {
        $schema = (new SchemaProvider())->getSchema();
        $table = $schema->getTable('periscope_events');

        self::assertTrue($table->hasIndex('ix_periscope_id'));
        self::assertTrue($table->hasIndex('ix_created_at'));
        self::assertTrue($table->hasIndex('ix_event_type_created_at'));
        self::assertTrue($table->hasIndex('ix_message_class_created_at'));
        self::assertTrue($table->hasIndex('ix_transport_created_at'));
    }

    public function testPrimaryKeyIsOnId(): void
    {
        $table = (new SchemaProvider())->getSchema()->getTable('periscope_events');

        $primary = $table->getPrimaryKey();
        self::assertNotNull($primary);
        self::assertSame(['id'], $primary->getColumns());
    }
}
