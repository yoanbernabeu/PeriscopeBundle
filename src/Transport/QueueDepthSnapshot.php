<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Transport;

use YoanBernabeu\PeriscopeBundle\Formatter\RowInterface;

/**
 * Single-transport queue depth reading produced by {@see QueueDepthProbe}.
 */
final readonly class QueueDepthSnapshot implements RowInterface
{
    public function __construct(
        public string $transport,
        public ?int $count,
        public bool $supported,
        public string $adapter,
        public \DateTimeImmutable $takenAt,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function defaultColumns(): array
    {
        return ['transport', 'count', 'adapter', 'taken_at'];
    }

    public function toColumns(): array
    {
        return [
            'transport' => $this->transport,
            'count' => $this->supported ? $this->count : null,
            'adapter' => $this->adapter,
            'taken_at' => $this->takenAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
