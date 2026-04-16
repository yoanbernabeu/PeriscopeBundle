<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Internal;

/**
 * Decides whether a given transport name should be observed by Periscope,
 * based on the `transports.include` / `transports.exclude` configuration.
 */
final readonly class TransportFilter
{
    /**
     * @param list<string> $include empty means "all transports are allowed"
     * @param list<string> $exclude
     */
    public function __construct(
        private array $include = [],
        private array $exclude = [],
    ) {
    }

    public function accepts(?string $transport): bool
    {
        if (null === $transport) {
            return true;
        }

        if (\in_array($transport, $this->exclude, true)) {
            return false;
        }

        if ([] === $this->include) {
            return true;
        }

        return \in_array($transport, $this->include, true);
    }
}
