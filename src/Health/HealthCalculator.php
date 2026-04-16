<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Health;

use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Computes a {@see HealthReport} by reading aggregated messages over the
 * requested window from the {@see StorageInterface}. Isolating the read
 * logic from the command class keeps the command thin and the calculation
 * independently testable.
 */
final readonly class HealthCalculator
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function calculate(\DateTimeImmutable $since): HealthReport
    {
        // Pull every message in the window. A single page of 10_000 is
        // plenty for the scale v1 targets (10k-100k/day).
        $messages = $this->storage->findMessages(new MessageFilter(
            since: $since,
            limit: 10_000,
        ));

        $counts = [
            MessageStatus::Pending->value => 0,
            MessageStatus::Running->value => 0,
            MessageStatus::Succeeded->value => 0,
            MessageStatus::Failed->value => 0,
        ];

        foreach ($messages as $message) {
            ++$counts[$message->status->value];
        }

        $total = \array_sum($counts);
        $terminal = $counts[MessageStatus::Succeeded->value] + $counts[MessageStatus::Failed->value];
        $failureRate = 0 === $terminal ? 0.0 : $counts[MessageStatus::Failed->value] / $terminal;

        return new HealthReport(
            total: $total,
            succeeded: $counts[MessageStatus::Succeeded->value],
            failed: $counts[MessageStatus::Failed->value],
            running: $counts[MessageStatus::Running->value],
            pending: $counts[MessageStatus::Pending->value],
            failureRate: $failureRate,
            since: $since,
        );
    }
}
