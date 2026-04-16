<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;

/**
 * Read-only projection of a message identified by its periscope id, computed
 * by aggregating all events that share the same id. Produced by storage
 * implementations and consumed by commands and formatters.
 */
final readonly class MessageAggregate
{
    /**
     * @param list<string> $transports distinct transports the message was seen on
     * @param list<string> $handlers distinct handlers that processed the message
     */
    public function __construct(
        public Uuid $periscopeId,
        public string $messageClass,
        public MessageStatus $status,
        public int $attempts,
        public array $transports,
        public array $handlers,
        public bool $scheduled,
        public ?int $durationMs,
        public ?string $lastErrorClass,
        public ?string $lastErrorMessage,
        public DateTimeImmutable $firstSeenAt,
        public DateTimeImmutable $lastSeenAt,
    ) {
    }
}
