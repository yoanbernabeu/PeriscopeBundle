<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Model;

/**
 * Every row persisted by Periscope is a single, append-only event belonging to
 * one of these types. They line up with the Messenger/Scheduler events the
 * bundle subscribes to.
 */
enum EventType: string
{
    case Dispatched = 'dispatched';
    case Received = 'received';
    case Handled = 'handled';
    case Failed = 'failed';
    case Retried = 'retried';
    case ScheduledBefore = 'scheduled_before';
    case ScheduledAfter = 'scheduled_after';
    case ScheduledFailed = 'scheduled_failed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Handled, self::Failed, self::ScheduledAfter, self::ScheduledFailed => true,
            default => false,
        };
    }

    public function isFailure(): bool
    {
        return match ($this) {
            self::Failed, self::ScheduledFailed => true,
            default => false,
        };
    }
}
