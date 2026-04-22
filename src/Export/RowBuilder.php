<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Export;

use DateTimeInterface;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

final class RowBuilder
{
    /**
     * @return array<string, bool|float|int|string|null>
     */
    public static function fromMessage(MessageAggregate $message): array
    {
        return [
            'id' => $message->periscopeId->toRfc4122(),
            'status' => $message->status->value,
            'class' => $message->messageClass,
            'attempts' => $message->attempts,
            'transport' => implode(',', $message->transports),
            'handler' => implode(',', $message->handlers),
            'duration_ms' => $message->durationMs,
            'last_seen_at' => $message->lastSeenAt->format(DateTimeInterface::RFC3339),
        ];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    public static function fromEvent(RecordedEvent $event): array
    {
        return [
            'id' => $event->periscopeId->toRfc4122(),
            'event_type' => $event->eventType->value,
            'class' => $event->messageClass,
            'transport' => $event->transport,
            'handler' => $event->handler,
            'duration_ms' => $event->durationMs,
            'error' => $event->errorMessage,
            'created_at' => $event->createdAt->format(DateTimeInterface::RFC3339),
        ];
    }
}
