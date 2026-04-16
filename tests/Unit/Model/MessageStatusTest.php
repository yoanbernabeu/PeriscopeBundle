<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;

#[CoversClass(MessageStatus::class)]
final class MessageStatusTest extends TestCase
{
    /**
     * @param list<EventType> $events
     */
    #[DataProvider('statusProvider')]
    public function testFromEventTypes(array $events, MessageStatus $expected): void
    {
        self::assertSame($expected, MessageStatus::fromEventTypes($events));
    }

    /**
     * @return iterable<string, array{list<EventType>, MessageStatus}>
     */
    public static function statusProvider(): iterable
    {
        yield 'empty stream is pending' => [
            [],
            MessageStatus::Pending,
        ];

        yield 'only dispatched is pending' => [
            [EventType::Dispatched],
            MessageStatus::Pending,
        ];

        yield 'dispatched + received is running' => [
            [EventType::Dispatched, EventType::Received],
            MessageStatus::Running,
        ];

        yield 'dispatched + received + handled is succeeded' => [
            [EventType::Dispatched, EventType::Received, EventType::Handled],
            MessageStatus::Succeeded,
        ];

        yield 'dispatched + received + failed is failed' => [
            [EventType::Dispatched, EventType::Received, EventType::Failed],
            MessageStatus::Failed,
        ];

        yield 'retried after failure is pending' => [
            [EventType::Dispatched, EventType::Received, EventType::Failed, EventType::Retried],
            MessageStatus::Pending,
        ];

        yield 'retry eventually succeeding is succeeded' => [
            [
                EventType::Dispatched,
                EventType::Received,
                EventType::Failed,
                EventType::Retried,
                EventType::Received,
                EventType::Handled,
            ],
            MessageStatus::Succeeded,
        ];

        yield 'scheduled before is running' => [
            [EventType::ScheduledBefore],
            MessageStatus::Running,
        ];

        yield 'scheduled after is succeeded' => [
            [EventType::ScheduledBefore, EventType::ScheduledAfter],
            MessageStatus::Succeeded,
        ];

        yield 'scheduled failed is failed' => [
            [EventType::ScheduledBefore, EventType::ScheduledFailed],
            MessageStatus::Failed,
        ];
    }
}
