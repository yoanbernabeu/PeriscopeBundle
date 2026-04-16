<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Health;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Health\HealthReport;

#[CoversClass(HealthReport::class)]
final class HealthReportTest extends TestCase
{
    public function testDefaultColumns(): void
    {
        self::assertSame(
            ['since', 'total', 'succeeded', 'failed', 'running', 'pending', 'failure_rate'],
            HealthReport::defaultColumns(),
        );
    }

    public function testToColumnsExposesEveryField(): void
    {
        $since = new \DateTimeImmutable('2026-04-16 11:45:00', new \DateTimeZone('UTC'));

        $report = new HealthReport(
            total: 100,
            succeeded: 90,
            failed: 5,
            running: 3,
            pending: 2,
            failureRate: 0.0526,
            since: $since,
        );

        $columns = $report->toColumns();

        self::assertSame('2026-04-16T11:45:00+00:00', $columns['since']);
        self::assertSame(100, $columns['total']);
        self::assertSame(90, $columns['succeeded']);
        self::assertSame(5, $columns['failed']);
        self::assertSame(3, $columns['running']);
        self::assertSame(2, $columns['pending']);
        self::assertSame(0.0526, $columns['failure_rate']);
    }
}
