<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Stamp;

use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Envelope stamp that gives a message a stable identity across transports and
 * retries. Periscope groups recorded events by this id to reconstruct a
 * message's timeline.
 */
final readonly class PeriscopeIdStamp implements StampInterface
{
    public function __construct(public Uuid $id)
    {
    }

    public static function generate(): self
    {
        return new self(Uuid::v7());
    }
}
