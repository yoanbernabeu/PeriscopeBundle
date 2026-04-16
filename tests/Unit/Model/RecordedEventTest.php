<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Model;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;

#[CoversClass(RecordedEvent::class)]
final class RecordedEventTest extends TestCase
{
    public function testEveryFieldIsExposedReadOnly(): void
    {
        $uuid = Uuid::v7();
        $createdAt = new DateTimeImmutable('2026-04-16 12:00:00');

        $event = new RecordedEvent(
            id: 42,
            periscopeId: $uuid,
            eventType: EventType::Handled,
            messageClass: 'App\\Message\\SendEmail',
            transport: 'async',
            bus: 'messenger.bus.default',
            handler: 'App\\MessageHandler\\SendEmailHandler',
            payload: ['to' => 'user@example.com'],
            stampsSummary: ['ReceivedStamp' => 'async'],
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: 120,
            scheduled: false,
            metadata: ['attempt' => 1],
            createdAt: $createdAt,
        );

        self::assertSame(42, $event->id);
        self::assertSame($uuid, $event->periscopeId);
        self::assertSame(EventType::Handled, $event->eventType);
        self::assertSame('App\\Message\\SendEmail', $event->messageClass);
        self::assertSame('async', $event->transport);
        self::assertSame('messenger.bus.default', $event->bus);
        self::assertSame('App\\MessageHandler\\SendEmailHandler', $event->handler);
        self::assertSame(['to' => 'user@example.com'], $event->payload);
        self::assertSame(['ReceivedStamp' => 'async'], $event->stampsSummary);
        self::assertNull($event->errorClass);
        self::assertNull($event->errorMessage);
        self::assertNull($event->errorTrace);
        self::assertSame(120, $event->durationMs);
        self::assertFalse($event->scheduled);
        self::assertSame(['attempt' => 1], $event->metadata);
        self::assertSame($createdAt, $event->createdAt);
    }

    public function testIdIsNullWhenNotYetPersisted(): void
    {
        $event = new RecordedEvent(
            id: null,
            periscopeId: Uuid::v7(),
            eventType: EventType::Dispatched,
            messageClass: 'X',
            transport: null,
            bus: null,
            handler: null,
            payload: null,
            stampsSummary: null,
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: false,
            metadata: null,
            createdAt: new DateTimeImmutable(),
        );

        self::assertNull($event->id);
    }
}
