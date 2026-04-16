<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Formatter\EventRow;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;

#[CoversClass(EventRow::class)]
final class EventRowTest extends TestCase
{
    public function testDefaultColumns(): void
    {
        self::assertSame(
            ['at', 'event', 'transport', 'handler', 'duration_ms', 'error'],
            EventRow::defaultColumns(),
        );
    }

    public function testFromEventProjectsEverySupportedField(): void
    {
        $row = EventRow::fromEvent(new RecordedEvent(
            id: 7,
            periscopeId: Uuid::v7(),
            eventType: EventType::Handled,
            messageClass: 'App\\Message\\SendEmail',
            transport: 'async',
            bus: 'messenger.bus.default',
            handler: 'App\\MessageHandler\\SendEmailHandler',
            payload: null,
            stampsSummary: null,
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: 42,
            scheduled: false,
            metadata: null,
            createdAt: new \DateTimeImmutable('2026-04-16 12:00:00', new \DateTimeZone('UTC')),
        ));

        self::assertSame('2026-04-16T12:00:00+00:00', $row->fields['at']);
        self::assertSame('handled', $row->fields['event']);
        self::assertSame('async', $row->fields['transport']);
        self::assertSame('SendEmailHandler', $row->fields['handler']);
        self::assertSame(42, $row->fields['duration_ms']);
        self::assertNull($row->fields['error']);
    }

    public function testErrorMessageIsProjected(): void
    {
        $row = EventRow::fromEvent(new RecordedEvent(
            id: null,
            periscopeId: Uuid::v7(),
            eventType: EventType::Failed,
            messageClass: 'X',
            transport: null,
            bus: null,
            handler: null,
            payload: null,
            stampsSummary: null,
            errorClass: \RuntimeException::class,
            errorMessage: 'boom',
            errorTrace: null,
            durationMs: null,
            scheduled: false,
            metadata: null,
            createdAt: new \DateTimeImmutable(),
        ));

        self::assertSame('boom', $row->fields['error']);
    }
}
