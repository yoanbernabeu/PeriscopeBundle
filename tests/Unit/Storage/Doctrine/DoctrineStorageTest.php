<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Storage\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\DoctrineStorage;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaProvider;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;

#[CoversClass(DoctrineStorage::class)]
#[CoversClass(SchemaManager::class)]
final class DoctrineStorageTest extends TestCase
{
    private Connection $connection;

    private DoctrineStorage $storage;

    protected function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $schemaProvider = new SchemaProvider();
        $schemaManager = new SchemaManager($this->connection, $schemaProvider);
        $schemaManager->createSchema();

        $this->storage = new DoctrineStorage($this->connection, $schemaProvider);
    }

    public function testCreateSchemaIsIdempotent(): void
    {
        $schemaManager = new SchemaManager($this->connection, new SchemaProvider());

        $second = $schemaManager->createSchema();

        self::assertSame([], $second, 'Running createSchema a second time must be a no-op');
    }

    public function testRecordAndFindEvents(): void
    {
        $id = Uuid::v7();
        $dispatched = $this->makeEvent($id, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00'));
        $received = $this->makeEvent($id, EventType::Received, new DateTimeImmutable('2026-04-16 12:00:01'));
        $handled = $this->makeEvent(
            $id,
            EventType::Handled,
            new DateTimeImmutable('2026-04-16 12:00:02'),
            handler: 'App\\MessageHandler\\SendEmailHandler',
            durationMs: 1000,
        );

        $this->storage->record($dispatched);
        $this->storage->record($received);
        $this->storage->record($handled);

        $events = $this->storage->findEvents($id);

        self::assertCount(3, $events);
        self::assertSame(EventType::Dispatched, $events[0]->eventType);
        self::assertSame(EventType::Received, $events[1]->eventType);
        self::assertSame(EventType::Handled, $events[2]->eventType);
        self::assertSame(1000, $events[2]->durationMs);
    }

    public function testFindMessagesAggregatesSuccessfulLifecycle(): void
    {
        $id = Uuid::v7();

        $this->storage->record($this->makeEvent($id, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00'), transport: 'async'));
        $this->storage->record($this->makeEvent($id, EventType::Received, new DateTimeImmutable('2026-04-16 12:00:01'), transport: 'async'));
        $this->storage->record($this->makeEvent(
            $id,
            EventType::Handled,
            new DateTimeImmutable('2026-04-16 12:00:02'),
            transport: 'async',
            handler: 'App\\MessageHandler\\SendEmailHandler',
            durationMs: 500,
        ));

        $messages = $this->storage->findMessages(new MessageFilter());

        self::assertCount(1, $messages);
        self::assertSame($id->toRfc4122(), $messages[0]->periscopeId->toRfc4122());
        self::assertSame(MessageStatus::Succeeded, $messages[0]->status);
        self::assertSame(1, $messages[0]->attempts);
        self::assertSame(['async'], $messages[0]->transports);
        self::assertSame(['App\\MessageHandler\\SendEmailHandler'], $messages[0]->handlers);
        self::assertSame(500, $messages[0]->durationMs);
        self::assertNull($messages[0]->lastErrorClass);
    }

    public function testFindMessagesCapturesRetriesAndFailure(): void
    {
        $id = Uuid::v7();

        $this->storage->record($this->makeEvent($id, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00'), transport: 'async'));
        $this->storage->record($this->makeEvent($id, EventType::Received, new DateTimeImmutable('2026-04-16 12:00:01'), transport: 'async'));
        $this->storage->record($this->makeEvent(
            $id,
            EventType::Failed,
            new DateTimeImmutable('2026-04-16 12:00:02'),
            transport: 'async',
            errorClass: RuntimeException::class,
            errorMessage: 'first try',
        ));
        $this->storage->record($this->makeEvent($id, EventType::Retried, new DateTimeImmutable('2026-04-16 12:00:03'), transport: 'async'));
        $this->storage->record($this->makeEvent($id, EventType::Received, new DateTimeImmutable('2026-04-16 12:00:04'), transport: 'async'));
        $this->storage->record($this->makeEvent(
            $id,
            EventType::Failed,
            new DateTimeImmutable('2026-04-16 12:00:05'),
            transport: 'async',
            errorClass: RuntimeException::class,
            errorMessage: 'second try',
        ));

        $messages = $this->storage->findMessages(new MessageFilter());

        self::assertCount(1, $messages);
        self::assertSame(MessageStatus::Failed, $messages[0]->status);
        self::assertSame(2, $messages[0]->attempts);
        self::assertSame('second try', $messages[0]->lastErrorMessage);
    }

    public function testFindMessagesOrdersByMostRecent(): void
    {
        $old = Uuid::v7();
        $recent = Uuid::v7();

        $this->storage->record($this->makeEvent($old, EventType::Dispatched, new DateTimeImmutable('2026-04-15 12:00:00')));
        $this->storage->record($this->makeEvent($recent, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00')));

        $messages = $this->storage->findMessages(new MessageFilter());

        self::assertCount(2, $messages);
        self::assertSame($recent->toRfc4122(), $messages[0]->periscopeId->toRfc4122());
        self::assertSame($old->toRfc4122(), $messages[1]->periscopeId->toRfc4122());
    }

    public function testFilterByTransport(): void
    {
        $async = Uuid::v7();
        $priority = Uuid::v7();

        $this->storage->record($this->makeEvent($async, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00'), transport: 'async'));
        $this->storage->record($this->makeEvent($priority, EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:01:00'), transport: 'high_priority'));

        $messages = $this->storage->findMessages(new MessageFilter(transports: ['high_priority']));

        self::assertCount(1, $messages);
        self::assertSame($priority->toRfc4122(), $messages[0]->periscopeId->toRfc4122());
    }

    public function testCountMessages(): void
    {
        $this->storage->record($this->makeEvent(Uuid::v7(), EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:00')));
        $this->storage->record($this->makeEvent(Uuid::v7(), EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:01')));
        $this->storage->record($this->makeEvent(Uuid::v7(), EventType::Dispatched, new DateTimeImmutable('2026-04-16 12:00:02')));

        self::assertSame(3, $this->storage->countMessages(new MessageFilter()));
    }

    public function testPurgeOlderThanRemovesOnlyOldRows(): void
    {
        $this->storage->record($this->makeEvent(Uuid::v7(), EventType::Dispatched, new DateTimeImmutable('2026-03-01 12:00:00')));
        $this->storage->record($this->makeEvent(Uuid::v7(), EventType::Dispatched, new DateTimeImmutable('2026-04-15 12:00:00')));

        $deleted = $this->storage->purgeOlderThan(new DateTimeImmutable('2026-04-01 00:00:00'));

        self::assertSame(1, $deleted);
        self::assertSame(1, $this->storage->countMessages(new MessageFilter()));
    }

    public function testPayloadIsRoundTripped(): void
    {
        $id = Uuid::v7();

        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Dispatched,
            messageClass: 'App\\Message\\SendEmail',
            transport: 'async',
            bus: 'messenger.bus.default',
            handler: null,
            payload: ['to' => 'user@example.com', 'subject' => 'Hi', 'nested' => ['a' => 1]],
            stampsSummary: ['BusNameStamp' => 'messenger.bus.default'],
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: false,
            metadata: null,
            createdAt: new DateTimeImmutable('2026-04-16 12:00:00'),
        ));

        $events = $this->storage->findEvents($id);

        self::assertCount(1, $events);
        self::assertSame(['to' => 'user@example.com', 'subject' => 'Hi', 'nested' => ['a' => 1]], $events[0]->payload);
        self::assertSame(['BusNameStamp' => 'messenger.bus.default'], $events[0]->stampsSummary);
    }

    private function makeEvent(
        Uuid $id,
        EventType $type,
        DateTimeImmutable $createdAt,
        ?string $transport = null,
        ?string $handler = null,
        ?int $durationMs = null,
        ?string $errorClass = null,
        ?string $errorMessage = null,
    ): RecordedEvent {
        return new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: $type,
            messageClass: 'App\\Message\\SendEmail',
            transport: $transport,
            bus: 'messenger.bus.default',
            handler: $handler,
            payload: null,
            stampsSummary: null,
            errorClass: $errorClass,
            errorMessage: $errorMessage,
            errorTrace: null,
            durationMs: $durationMs,
            scheduled: false,
            metadata: null,
            createdAt: $createdAt,
        );
    }
}
