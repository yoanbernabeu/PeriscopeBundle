<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage;

use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;

/**
 * Read-model filter used by storage implementations to page through messages
 * aggregated by periscope id. Values are safe to pass through SQL parameter
 * binding — never interpolated into a query.
 */
final readonly class MessageFilter
{
    /**
     * @param list<MessageStatus> $statuses
     * @param list<string>        $transports
     * @param list<string>        $messageClasses
     */
    public function __construct(
        public array $statuses = [],
        public array $transports = [],
        public array $messageClasses = [],
        public ?\DateTimeImmutable $since = null,
        public ?\DateTimeImmutable $until = null,
        public ?bool $scheduledOnly = null,
        public int $limit = 20,
        public int $offset = 0,
    ) {
        if ($limit < 1) {
            throw new \InvalidArgumentException(\sprintf('limit must be >= 1, got %d', $limit));
        }

        if ($offset < 0) {
            throw new \InvalidArgumentException(\sprintf('offset must be >= 0, got %d', $offset));
        }

        if (null !== $since && null !== $until && $since > $until) {
            throw new \InvalidArgumentException('since must be less than or equal to until');
        }
    }
}
