<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage;

use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;

/**
 * Append-only event store backing every Periscope read command.
 *
 * Write path is {@see record()}: event subscribers turn Messenger/Scheduler
 * events into {@see RecordedEvent} instances and hand them over here. The
 * storage never mutates an existing row.
 *
 * Read path is {@see findMessages()} and {@see findEvents()}: implementations
 * are expected to aggregate events on the fly — no denormalised state is kept.
 */
interface StorageInterface
{
    public function record(RecordedEvent $event): void;

    /**
     * @return list<MessageAggregate>
     */
    public function findMessages(MessageFilter $filter): array;

    /**
     * Returns every recorded event belonging to the given periscope id in
     * chronological order.
     *
     * @return list<RecordedEvent>
     */
    public function findEvents(Uuid $periscopeId): array;

    public function countMessages(MessageFilter $filter): int;

    /**
     * Removes events older than the given cutoff. Returns the number of rows
     * deleted.
     */
    public function purgeOlderThan(\DateTimeImmutable $cutoff): int;
}
