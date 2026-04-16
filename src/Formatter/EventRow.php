<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Formatter;

use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;

/**
 * Projects a {@see RecordedEvent} into a row fit for display, i.e. the
 * per-event view used by `periscope:message` to show a timeline.
 */
final readonly class EventRow implements RowInterface
{
    private function __construct(
        /** @var array<string, scalar|null> */
        public array $fields,
    ) {
    }

    public static function fromEvent(RecordedEvent $event): self
    {
        return new self([
            'at' => $event->createdAt->format(\DateTimeInterface::ATOM),
            'event' => $event->eventType->value,
            'transport' => $event->transport,
            'handler' => null === $event->handler ? null : self::shorten($event->handler),
            'duration_ms' => $event->durationMs,
            'error' => $event->errorMessage,
        ]);
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['at', 'event', 'transport', 'handler', 'duration_ms', 'error'];
    }

    public function toColumns(): array
    {
        return $this->fields;
    }

    private static function shorten(string $fqcn): string
    {
        $pos = \strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : \substr($fqcn, $pos + 1);
    }
}
