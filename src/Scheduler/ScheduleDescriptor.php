<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Scheduler;

use DateTimeImmutable;
use DateTimeInterface;
use YoanBernabeu\PeriscopeBundle\Formatter\RowInterface;

/**
 * Read-only projection of a single recurring message belonging to a
 * configured schedule.
 */
final readonly class ScheduleDescriptor implements RowInterface
{
    public function __construct(
        public string $scheduleName,
        public string $messageClass,
        public string $triggerLabel,
        public ?DateTimeImmutable $nextRunAt,
        public string $providerClass,
        public int $position,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['schedule', 'class', 'trigger', 'next_run', 'provider'];
    }

    public function toColumns(): array
    {
        return [
            'schedule' => $this->scheduleName,
            'class' => $this->shorten($this->messageClass),
            'trigger' => $this->triggerLabel,
            'next_run' => $this->nextRunAt?->format(DateTimeInterface::ATOM),
            'provider' => $this->shorten($this->providerClass),
        ];
    }

    private function shorten(string $fqcn): string
    {
        $pos = strrpos($fqcn, '\\');

        return false === $pos ? $fqcn : substr($fqcn, $pos + 1);
    }
}
