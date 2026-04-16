<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;

#[CoversClass(MessageFilter::class)]
final class MessageFilterTest extends TestCase
{
    public function testDefaultsAreSensible(): void
    {
        $filter = new MessageFilter();

        self::assertSame([], $filter->statuses);
        self::assertSame([], $filter->transports);
        self::assertSame([], $filter->messageClasses);
        self::assertNull($filter->since);
        self::assertNull($filter->until);
        self::assertNull($filter->scheduledOnly);
        self::assertSame(20, $filter->limit);
        self::assertSame(0, $filter->offset);
    }

    public function testAcceptsExplicitValues(): void
    {
        $since = new \DateTimeImmutable('2026-04-01');
        $until = new \DateTimeImmutable('2026-04-30');

        $filter = new MessageFilter(
            statuses: [MessageStatus::Failed],
            transports: ['async'],
            messageClasses: ['App\\Message\\SendEmail'],
            since: $since,
            until: $until,
            scheduledOnly: true,
            limit: 50,
            offset: 10,
        );

        self::assertSame([MessageStatus::Failed], $filter->statuses);
        self::assertSame(['async'], $filter->transports);
        self::assertSame(['App\\Message\\SendEmail'], $filter->messageClasses);
        self::assertSame($since, $filter->since);
        self::assertSame($until, $filter->until);
        self::assertTrue($filter->scheduledOnly);
        self::assertSame(50, $filter->limit);
        self::assertSame(10, $filter->offset);
    }

    public function testRejectsLimitBelowOne(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be >= 1');

        new MessageFilter(limit: 0);
    }

    public function testRejectsNegativeOffset(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('offset must be >= 0');

        new MessageFilter(offset: -1);
    }

    public function testRejectsInvertedTimeWindow(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('since must be less than or equal to until');

        new MessageFilter(
            since: new \DateTimeImmutable('2026-04-30'),
            until: new \DateTimeImmutable('2026-04-01'),
        );
    }

    public function testEqualSinceAndUntilIsAccepted(): void
    {
        $moment = new \DateTimeImmutable('2026-04-16 12:00:00');

        $filter = new MessageFilter(since: $moment, until: $moment);

        self::assertSame($moment, $filter->since);
        self::assertSame($moment, $filter->until);
    }
}
