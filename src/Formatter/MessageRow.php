<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Formatter;

use DateTimeInterface;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

/**
 * Projection of a {@see MessageAggregate} into the minimal set of fields
 * {@see MessageRenderer} emits, keyed by their canonical column name. The
 * values are always scalars (or null) so that every formatter — compact, json,
 * ndjson, yaml, pretty — can render them without additional conversion.
 */
final readonly class MessageRow implements RowInterface
{
    private function __construct(
        /** @var array<string, scalar|null> */
        public array $fields,
    ) {
    }

    public static function fromAggregate(MessageAggregate $aggregate): self
    {
        return new self([
            'id' => $aggregate->periscopeId->toRfc4122(),
            'status' => $aggregate->status->value,
            'class' => self::shorten($aggregate->messageClass),
            'attempts' => $aggregate->attempts,
            'transport' => [] === $aggregate->transports ? null : implode(',', $aggregate->transports),
            'handler' => [] === $aggregate->handlers ? null : self::shorten($aggregate->handlers[0]),
            'scheduled' => $aggregate->scheduled ? 'yes' : 'no',
            'duration_ms' => $aggregate->durationMs,
            'last_error' => $aggregate->lastErrorMessage,
            'last_seen_at' => $aggregate->lastSeenAt->format(DateTimeInterface::ATOM),
        ]);
    }

    public function toColumns(): array
    {
        return $this->fields;
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['id', 'status', 'class', 'attempts', 'transport', 'handler', 'scheduled', 'duration_ms', 'last_error', 'last_seen_at'];
    }

    private static function shorten(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : substr($fqcn, $pos + 1);
    }
}
