<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Transport;

use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Inspects a Messenger transport to extract its current queue depth.
 *
 * A dedicated interface lets us opt-in to each backend we support (Doctrine,
 * Redis, AMQP) without coupling Periscope to backends we don't ship.
 */
interface TransportInspectorInterface
{
    public function supports(TransportInterface $transport): bool;

    /**
     * Returns the number of messages currently held by the transport, or
     * `null` when the transport does not expose that information.
     */
    public function countMessages(TransportInterface $transport): ?int;
}
