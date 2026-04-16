<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Scheduler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleDescriptor;

#[CoversClass(ScheduleDescriptor::class)]
final class ScheduleDescriptorTest extends TestCase
{
    public function testDefaultColumns(): void
    {
        self::assertSame(
            ['schedule', 'class', 'trigger', 'next_run', 'provider'],
            ScheduleDescriptor::defaultColumns(),
        );
    }

    public function testToColumnsShortensFqcnAndFormatsNextRun(): void
    {
        $nextRun = new \DateTimeImmutable('2026-04-16 13:00:00', new \DateTimeZone('UTC'));

        $descriptor = new ScheduleDescriptor(
            scheduleName: 'default',
            messageClass: 'App\\Message\\SendEmail',
            triggerLabel: 'every minute',
            nextRunAt: $nextRun,
            providerClass: 'App\\Scheduler\\AppSchedule',
            position: 0,
        );

        $columns = $descriptor->toColumns();

        self::assertSame('default', $columns['schedule']);
        self::assertSame('SendEmail', $columns['class']);
        self::assertSame('every minute', $columns['trigger']);
        self::assertSame('2026-04-16T13:00:00+00:00', $columns['next_run']);
        self::assertSame('AppSchedule', $columns['provider']);
    }

    public function testNextRunIsNullWhenAbsent(): void
    {
        $descriptor = new ScheduleDescriptor(
            scheduleName: 'cron',
            messageClass: 'X',
            triggerLabel: 't',
            nextRunAt: null,
            providerClass: 'P',
            position: 0,
        );

        self::assertNull($descriptor->toColumns()['next_run']);
    }
}
