<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Formatter\MessageRow;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

#[CoversClass(MessageRow::class)]
final class MessageRowTest extends TestCase
{
    public function testDefaultColumns(): void
    {
        self::assertSame(
            ['id', 'status', 'class', 'attempts', 'transport', 'handler', 'scheduled', 'duration_ms', 'last_error', 'last_seen_at'],
            MessageRow::defaultColumns(),
        );
    }

    public function testFromAggregateProjectsScalarFields(): void
    {
        $uuid = Uuid::v7();
        $firstSeen = new \DateTimeImmutable('2026-04-16 12:00:00', new \DateTimeZone('UTC'));
        $lastSeen = new \DateTimeImmutable('2026-04-16 12:00:05', new \DateTimeZone('UTC'));

        $row = MessageRow::fromAggregate(new MessageAggregate(
            periscopeId: $uuid,
            messageClass: 'App\\Message\\SendEmail',
            status: MessageStatus::Failed,
            attempts: 2,
            transports: ['async', 'failed'],
            handlers: ['App\\MessageHandler\\SendEmailHandler'],
            scheduled: true,
            durationMs: 320,
            lastErrorClass: \RuntimeException::class,
            lastErrorMessage: 'timeout',
            firstSeenAt: $firstSeen,
            lastSeenAt: $lastSeen,
        ));

        self::assertSame($uuid->toRfc4122(), $row->fields['id']);
        self::assertSame('failed', $row->fields['status']);
        self::assertSame('SendEmail', $row->fields['class']);
        self::assertSame(2, $row->fields['attempts']);
        self::assertSame('async,failed', $row->fields['transport']);
        self::assertSame('SendEmailHandler', $row->fields['handler']);
        self::assertSame('yes', $row->fields['scheduled']);
        self::assertSame(320, $row->fields['duration_ms']);
        self::assertSame('timeout', $row->fields['last_error']);
        self::assertSame('2026-04-16T12:00:05+00:00', $row->fields['last_seen_at']);
    }

    public function testNullCollectionsBecomeNull(): void
    {
        $row = MessageRow::fromAggregate(new MessageAggregate(
            periscopeId: Uuid::v7(),
            messageClass: 'App\\Message\\Noop',
            status: MessageStatus::Pending,
            attempts: 0,
            transports: [],
            handlers: [],
            scheduled: false,
            durationMs: null,
            lastErrorClass: null,
            lastErrorMessage: null,
            firstSeenAt: new \DateTimeImmutable(),
            lastSeenAt: new \DateTimeImmutable(),
        ));

        self::assertNull($row->fields['transport']);
        self::assertNull($row->fields['handler']);
        self::assertNull($row->fields['duration_ms']);
        self::assertNull($row->fields['last_error']);
        self::assertSame('no', $row->fields['scheduled']);
    }
}
