<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Storage\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Doctrine DBAL-backed implementation of {@see StorageInterface}.
 *
 * Writes are plain `INSERT`s into a single event table. Reads aggregate the
 * event stream in PHP after fetching all rows belonging to the page — this
 * keeps the SQL portable across PostgreSQL, MySQL and SQLite. Databases that
 * can push the aggregation down (PostgreSQL with window functions) can be
 * targeted by a more specialised implementation later.
 */
final readonly class DoctrineStorage implements StorageInterface
{
    public function __construct(
        private Connection $connection,
        private SchemaProvider $schemaProvider,
    ) {
    }

    public function record(RecordedEvent $event): void
    {
        $this->connection->insert($this->schemaProvider->tableName(), [
            'periscope_id' => $event->periscopeId->toRfc4122(),
            'event_type' => $event->eventType->value,
            'message_class' => $event->messageClass,
            'transport' => $event->transport,
            'bus' => $event->bus,
            'handler' => $event->handler,
            'payload' => $this->encodeJson($event->payload),
            'stamps_summary' => $this->encodeJson($event->stampsSummary),
            'error_class' => $event->errorClass,
            'error_message' => $event->errorMessage,
            'error_trace' => $event->errorTrace,
            'duration_ms' => $event->durationMs,
            'scheduled' => $event->scheduled ? 1 : 0,
            'metadata' => $this->encodeJson($event->metadata),
            'created_at' => $event->createdAt->format('Y-m-d H:i:s.uP'),
        ], [
            'periscope_id' => ParameterType::STRING,
            'event_type' => ParameterType::STRING,
            'message_class' => ParameterType::STRING,
            'transport' => ParameterType::STRING,
            'bus' => ParameterType::STRING,
            'handler' => ParameterType::STRING,
            'payload' => ParameterType::STRING,
            'stamps_summary' => ParameterType::STRING,
            'error_class' => ParameterType::STRING,
            'error_message' => ParameterType::STRING,
            'error_trace' => ParameterType::STRING,
            'duration_ms' => ParameterType::INTEGER,
            'scheduled' => ParameterType::INTEGER,
            'metadata' => ParameterType::STRING,
            'created_at' => ParameterType::STRING,
        ]);
    }

    public function findMessages(MessageFilter $filter): array
    {
        $ids = $this->findPeriscopeIdsMatching($filter);

        if ([] === $ids) {
            return [];
        }

        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->schemaProvider->tableName())
            ->where('periscope_id IN (:ids)')
            ->orderBy('periscope_id', 'ASC')
            ->addOrderBy('id', 'ASC')
            ->setParameter('ids', $ids, \Doctrine\DBAL\ArrayParameterType::STRING);

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();

        /** @var array<string, list<RecordedEvent>> $byId */
        $byId = [];
        foreach ($rows as $row) {
            $event = $this->hydrate($row);
            $byId[$event->periscopeId->toRfc4122()][] = $event;
        }

        $aggregates = [];
        foreach ($byId as $events) {
            $aggregates[] = $this->aggregate($events);
        }

        \usort($aggregates, static fn (MessageAggregate $a, MessageAggregate $b) => $b->lastSeenAt <=> $a->lastSeenAt);

        return $aggregates;
    }

    public function findEvents(Uuid $periscopeId): array
    {
        $query = $this->connection->createQueryBuilder()
            ->select('*')
            ->from($this->schemaProvider->tableName())
            ->where('periscope_id = :id')
            ->orderBy('id', 'ASC')
            ->setParameter('id', $periscopeId->toRfc4122());

        /** @var list<array<string, mixed>> $rows */
        $rows = $query->executeQuery()->fetchAllAssociative();

        return \array_values(\array_map(fn (array $row): RecordedEvent => $this->hydrate($row), $rows));
    }

    public function countMessages(MessageFilter $filter): int
    {
        $qb = $this->buildBaseMessageQuery($filter)
            ->select('COUNT(DISTINCT e.periscope_id) AS total');

        $count = $qb->executeQuery()->fetchOne();

        return self::toInt($count);
    }

    public function purgeOlderThan(\DateTimeImmutable $cutoff): int
    {
        return (int) $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE created_at < :cutoff', $this->schemaProvider->tableName()),
            ['cutoff' => $cutoff->format('Y-m-d H:i:s.uP')],
            ['cutoff' => ParameterType::STRING],
        );
    }

    /**
     * @return list<string> the list of periscope_id values matching the filter, paginated
     */
    private function findPeriscopeIdsMatching(MessageFilter $filter): array
    {
        $qb = $this->buildBaseMessageQuery($filter)
            ->select('e.periscope_id', 'MAX(e.created_at) AS last_seen')
            ->groupBy('e.periscope_id')
            ->orderBy('last_seen', 'DESC')
            ->setMaxResults($filter->limit)
            ->setFirstResult($filter->offset);

        /** @var list<array<string, mixed>> $rows */
        $rows = $qb->executeQuery()->fetchAllAssociative();

        return \array_values(\array_map(static fn (array $row): string => self::toString($row['periscope_id'] ?? null), $rows));
    }

    private function buildBaseMessageQuery(MessageFilter $filter): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->from($this->schemaProvider->tableName(), 'e');

        if ([] !== $filter->messageClasses) {
            $qb->andWhere('e.message_class IN (:classes)')
                ->setParameter('classes', $filter->messageClasses, \Doctrine\DBAL\ArrayParameterType::STRING);
        }

        if ([] !== $filter->transports) {
            $qb->andWhere('e.transport IN (:transports)')
                ->setParameter('transports', $filter->transports, \Doctrine\DBAL\ArrayParameterType::STRING);
        }

        if (null !== $filter->since) {
            $qb->andWhere('e.created_at >= :since')
                ->setParameter('since', $filter->since->format('Y-m-d H:i:s.uP'));
        }

        if (null !== $filter->until) {
            $qb->andWhere('e.created_at <= :until')
                ->setParameter('until', $filter->until->format('Y-m-d H:i:s.uP'));
        }

        if (null !== $filter->scheduledOnly) {
            $qb->andWhere('e.scheduled = :scheduled')
                ->setParameter('scheduled', $filter->scheduledOnly ? 1 : 0, ParameterType::INTEGER);
        }

        // MessageStatus filtering is applied post-aggregation in findMessages,
        // because status is not a persisted column — only events are.
        return $qb;
    }

    /**
     * @param list<RecordedEvent> $events events of a single periscope id, in chronological order
     */
    private function aggregate(array $events): MessageAggregate
    {
        if ([] === $events) {
            throw new \LogicException('Cannot aggregate an empty event list.');
        }

        $first = $events[0];
        $last = $events[\count($events) - 1];

        /** @var list<\YoanBernabeu\PeriscopeBundle\Model\EventType> $eventTypes */
        $eventTypes = \array_values(\array_map(static fn (RecordedEvent $event) => $event->eventType, $events));

        $status = MessageStatus::fromEventTypes($eventTypes);

        $transports = [];
        $handlers = [];
        $attempts = 0;
        $lastDuration = null;
        $lastErrorClass = null;
        $lastErrorMessage = null;
        $scheduled = false;

        foreach ($events as $event) {
            if (null !== $event->transport && !\in_array($event->transport, $transports, true)) {
                $transports[] = $event->transport;
            }
            if (null !== $event->handler && !\in_array($event->handler, $handlers, true)) {
                $handlers[] = $event->handler;
            }
            if (EventType::Received === $event->eventType) {
                ++$attempts;
            }
            if ($event->scheduled) {
                $scheduled = true;
            }
            if (null !== $event->durationMs) {
                $lastDuration = $event->durationMs;
            }
            if ($event->eventType->isFailure()) {
                $lastErrorClass = $event->errorClass;
                $lastErrorMessage = $event->errorMessage;
            }
        }

        return new MessageAggregate(
            periscopeId: $first->periscopeId,
            messageClass: $first->messageClass,
            status: $status,
            attempts: $attempts,
            transports: $transports,
            handlers: $handlers,
            scheduled: $scheduled,
            durationMs: $lastDuration,
            lastErrorClass: $lastErrorClass,
            lastErrorMessage: $lastErrorMessage,
            firstSeenAt: $first->createdAt,
            lastSeenAt: $last->createdAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): RecordedEvent
    {
        return new RecordedEvent(
            id: self::toNullableInt($row['id'] ?? null),
            periscopeId: Uuid::fromString(self::toString($row['periscope_id'] ?? null)),
            eventType: EventType::from(self::toString($row['event_type'] ?? null)),
            messageClass: self::toString($row['message_class'] ?? null),
            transport: self::toNullableString($row['transport'] ?? null),
            bus: self::toNullableString($row['bus'] ?? null),
            handler: self::toNullableString($row['handler'] ?? null),
            payload: $this->decodeJson($row['payload'] ?? null),
            stampsSummary: $this->decodeJson($row['stamps_summary'] ?? null),
            errorClass: self::toNullableString($row['error_class'] ?? null),
            errorMessage: self::toNullableString($row['error_message'] ?? null),
            errorTrace: self::toNullableString($row['error_trace'] ?? null),
            durationMs: self::toNullableInt($row['duration_ms'] ?? null),
            scheduled: self::toBool($row['scheduled'] ?? false),
            metadata: $this->decodeJson($row['metadata'] ?? null),
            createdAt: new \DateTimeImmutable(self::toString($row['created_at'] ?? null)),
        );
    }

    private static function toString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }

        throw new \RuntimeException(\sprintf('Expected string, got %s.', \get_debug_type($value)));
    }

    private static function toNullableString(mixed $value): ?string
    {
        return null === $value ? null : self::toString($value);
    }

    private static function toInt(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && \is_numeric($value)) {
            return (int) $value;
        }

        throw new \RuntimeException(\sprintf('Expected int, got %s.', \get_debug_type($value)));
    }

    private static function toNullableInt(mixed $value): ?int
    {
        return null === $value ? null : self::toInt($value);
    }

    private static function toBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_int($value)) {
            return 0 !== $value;
        }
        if (\is_string($value)) {
            return !\in_array(\strtolower($value), ['', '0', 'false', 'f'], true);
        }

        return false;
    }

    /**
     * @param array<string, mixed>|null $data
     */
    private function encodeJson(?array $data): ?string
    {
        if (null === $data) {
            return null;
        }

        $encoded = \json_encode(
            $data,
            \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        return false === $encoded ? null : $encoded;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJson(mixed $raw): ?array
    {
        if (null === $raw || '' === $raw) {
            return null;
        }

        if (\is_array($raw)) {
            /** @var array<string, mixed> $raw */
            return $raw;
        }

        if (!\is_string($raw)) {
            return null;
        }

        $decoded = \json_decode($raw, true);
        if (!\is_array($decoded)) {
            return null;
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
