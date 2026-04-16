<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Health;

use YoanBernabeu\PeriscopeBundle\Formatter\RowInterface;

/**
 * Aggregated metrics over a time window used by {@see \YoanBernabeu\PeriscopeBundle\Command\HealthCommand}
 * to decide whether the thresholds passed by the operator or agent are
 * respected.
 */
final readonly class HealthReport implements RowInterface
{
    public function __construct(
        public int $total,
        public int $succeeded,
        public int $failed,
        public int $running,
        public int $pending,
        public float $failureRate,
        public \DateTimeImmutable $since,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['since', 'total', 'succeeded', 'failed', 'running', 'pending', 'failure_rate'];
    }

    public function toColumns(): array
    {
        return [
            'since' => $this->since->format(\DateTimeInterface::ATOM),
            'total' => $this->total,
            'succeeded' => $this->succeeded,
            'failed' => $this->failed,
            'running' => $this->running,
            'pending' => $this->pending,
            'failure_rate' => \round($this->failureRate, 4),
        ];
    }
}
