<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Model;

/**
 * Aggregated status of a message across all its recorded events. Computed at
 * read time from the event stream; never persisted as a column.
 */
enum MessageStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    /**
     * @param list<EventType> $eventTypes the events of a single periscope_id, in chronological order
     */
    public static function fromEventTypes(array $eventTypes): self
    {
        $last = end($eventTypes);

        if (false === $last) {
            return self::Pending;
        }

        return match ($last) {
            EventType::Handled, EventType::ScheduledAfter => self::Succeeded,
            EventType::Failed, EventType::ScheduledFailed => self::Failed,
            EventType::Received, EventType::ScheduledBefore => self::Running,
            EventType::Dispatched, EventType::Retried => self::Pending,
        };
    }
}
