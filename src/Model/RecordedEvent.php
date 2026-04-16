<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Model;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * A single, immutable row persisted by Periscope.
 *
 * Rows are append-only. One message going through dispatch → receive → handle
 * produces three separate RecordedEvents, all sharing the same {@see $periscopeId}
 * which groups the message's lifetime across retries and transports.
 */
final readonly class RecordedEvent
{
    /**
     * @param array<string, mixed>|null $payload JSON-serialisable payload snapshot (only on Dispatched)
     * @param array<string, mixed>|null $stampsSummary compact representation of the envelope stamps present when the event was recorded
     * @param array<string, mixed>|null $metadata free-form extension point for future fields
     */
    public function __construct(
        public ?int $id,
        public Uuid $periscopeId,
        public EventType $eventType,
        public string $messageClass,
        public ?string $transport,
        public ?string $bus,
        public ?string $handler,
        public ?array $payload,
        public ?array $stampsSummary,
        public ?string $errorClass,
        public ?string $errorMessage,
        public ?string $errorTrace,
        public ?int $durationMs,
        public bool $scheduled,
        public ?array $metadata,
        public DateTimeImmutable $createdAt,
    ) {
    }
}
