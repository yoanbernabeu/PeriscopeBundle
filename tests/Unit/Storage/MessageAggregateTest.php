<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

#[CoversClass(MessageAggregate::class)]
final class MessageAggregateTest extends TestCase
{
    public function testExposesAllFields(): void
    {
        $uuid = Uuid::v7();
        $firstSeen = new \DateTimeImmutable('2026-04-16 12:00:00');
        $lastSeen = new \DateTimeImmutable('2026-04-16 12:00:05');

        $aggregate = new MessageAggregate(
            periscopeId: $uuid,
            messageClass: 'App\\Message\\SendEmail',
            status: MessageStatus::Failed,
            attempts: 3,
            transports: ['async', 'failed'],
            handlers: ['App\\MessageHandler\\SendEmailHandler'],
            scheduled: true,
            durationMs: 230,
            lastErrorClass: \RuntimeException::class,
            lastErrorMessage: 'timeout',
            firstSeenAt: $firstSeen,
            lastSeenAt: $lastSeen,
        );

        self::assertSame($uuid, $aggregate->periscopeId);
        self::assertSame('App\\Message\\SendEmail', $aggregate->messageClass);
        self::assertSame(MessageStatus::Failed, $aggregate->status);
        self::assertSame(3, $aggregate->attempts);
        self::assertSame(['async', 'failed'], $aggregate->transports);
        self::assertSame(['App\\MessageHandler\\SendEmailHandler'], $aggregate->handlers);
        self::assertTrue($aggregate->scheduled);
        self::assertSame(230, $aggregate->durationMs);
        self::assertSame(\RuntimeException::class, $aggregate->lastErrorClass);
        self::assertSame('timeout', $aggregate->lastErrorMessage);
        self::assertSame($firstSeen, $aggregate->firstSeenAt);
        self::assertSame($lastSeen, $aggregate->lastSeenAt);
    }
}
