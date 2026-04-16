<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Model\EventType;

#[CoversClass(EventType::class)]
final class EventTypeTest extends TestCase
{
    /**
     * @param list<EventType> $terminal
     */
    #[DataProvider('terminalCases')]
    public function testIsTerminalReturnsTrueForTerminalEvents(array $terminal): void
    {
        foreach ($terminal as $case) {
            self::assertTrue($case->isTerminal(), \sprintf('%s should be terminal', $case->value));
        }
    }

    /**
     * @param list<EventType> $nonTerminal
     */
    #[DataProvider('nonTerminalCases')]
    public function testIsTerminalReturnsFalseForNonTerminalEvents(array $nonTerminal): void
    {
        foreach ($nonTerminal as $case) {
            self::assertFalse($case->isTerminal(), \sprintf('%s should not be terminal', $case->value));
        }
    }

    public function testIsFailureReturnsTrueForFailureEvents(): void
    {
        self::assertTrue(EventType::Failed->isFailure());
        self::assertTrue(EventType::ScheduledFailed->isFailure());
    }

    public function testIsFailureReturnsFalseForNonFailureEvents(): void
    {
        self::assertFalse(EventType::Dispatched->isFailure());
        self::assertFalse(EventType::Received->isFailure());
        self::assertFalse(EventType::Handled->isFailure());
        self::assertFalse(EventType::Retried->isFailure());
        self::assertFalse(EventType::ScheduledBefore->isFailure());
        self::assertFalse(EventType::ScheduledAfter->isFailure());
    }

    public function testValuesAreStable(): void
    {
        self::assertSame('dispatched', EventType::Dispatched->value);
        self::assertSame('received', EventType::Received->value);
        self::assertSame('handled', EventType::Handled->value);
        self::assertSame('failed', EventType::Failed->value);
        self::assertSame('retried', EventType::Retried->value);
        self::assertSame('scheduled_before', EventType::ScheduledBefore->value);
        self::assertSame('scheduled_after', EventType::ScheduledAfter->value);
        self::assertSame('scheduled_failed', EventType::ScheduledFailed->value);
    }

    /**
     * @return iterable<string, array{list<EventType>}>
     */
    public static function terminalCases(): iterable
    {
        yield 'all terminal' => [[
            EventType::Handled,
            EventType::Failed,
            EventType::ScheduledAfter,
            EventType::ScheduledFailed,
        ]];
    }

    /**
     * @return iterable<string, array{list<EventType>}>
     */
    public static function nonTerminalCases(): iterable
    {
        yield 'all non-terminal' => [[
            EventType::Dispatched,
            EventType::Received,
            EventType::Retried,
            EventType::ScheduledBefore,
        ]];
    }
}
