<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Transport\QueueDepthSnapshot;

#[CoversClass(QueueDepthSnapshot::class)]
final class QueueDepthSnapshotTest extends TestCase
{
    public function testDefaultColumns(): void
    {
        self::assertSame(
            ['transport', 'count', 'adapter', 'taken_at'],
            QueueDepthSnapshot::defaultColumns(),
        );
    }

    public function testUnsupportedTransportEmitsNullCount(): void
    {
        $takenAt = new \DateTimeImmutable('2026-04-16 12:00:00', new \DateTimeZone('UTC'));

        $snapshot = new QueueDepthSnapshot(
            transport: 'async',
            count: 42,
            supported: false,
            adapter: 'DoctrineTransport',
            takenAt: $takenAt,
        );

        self::assertNull($snapshot->toColumns()['count']);
    }

    public function testSupportedTransportEmitsCount(): void
    {
        $snapshot = new QueueDepthSnapshot(
            transport: 'async',
            count: 7,
            supported: true,
            adapter: 'DoctrineTransport',
            takenAt: new \DateTimeImmutable('2026-04-16 12:00:00', new \DateTimeZone('UTC')),
        );

        $columns = $snapshot->toColumns();

        self::assertSame('async', $columns['transport']);
        self::assertSame(7, $columns['count']);
        self::assertSame('DoctrineTransport', $columns['adapter']);
        self::assertSame('2026-04-16T12:00:00+00:00', $columns['taken_at']);
    }
}
