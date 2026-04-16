<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Command\PurgeCommand;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(PurgeCommand::class)]
final class PurgeCommandTest extends TestCase
{
    public function testDryRunPreservesEverything(): void
    {
        $harness = new InMemoryStorage();
        $this->seed($harness, new \DateTimeImmutable('2026-01-01 00:00:00'));

        $tester = new CommandTester(new PurgeCommand($harness->storage, retentionDays: 30));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dry-run' => true]));

        self::assertStringContainsString('Would delete', $tester->getDisplay());
        self::assertSame(1, $harness->storage->countMessages(new MessageFilter()));
    }

    public function testDeletesRowsOlderThanRetention(): void
    {
        $harness = new InMemoryStorage();
        $this->seed($harness, new \DateTimeImmutable('2020-01-01 00:00:00'));
        $this->seed($harness, new \DateTimeImmutable()); // recent row

        $tester = new CommandTester(new PurgeCommand($harness->storage, retentionDays: 30));
        self::assertSame(Command::SUCCESS, $tester->execute([]));

        self::assertStringContainsString('Deleted 1', $tester->getDisplay());
        self::assertSame(1, $harness->storage->countMessages(new MessageFilter()));
    }

    public function testOverrideRespectsCustomDuration(): void
    {
        $harness = new InMemoryStorage();
        $this->seed($harness, (new \DateTimeImmutable())->modify('-2 hours'));

        $tester = new CommandTester(new PurgeCommand($harness->storage, retentionDays: 30));
        self::assertSame(Command::SUCCESS, $tester->execute(['--older-than' => '1h']));

        self::assertStringContainsString('Deleted 1', $tester->getDisplay());
    }

    public function testInvalidDurationReturnsInvalid(): void
    {
        $harness = new InMemoryStorage();
        $tester = new CommandTester(new PurgeCommand($harness->storage, retentionDays: 30));

        self::assertSame(Command::INVALID, $tester->execute(['--older-than' => 'not-a-duration']));
        self::assertStringContainsString('Invalid --older-than value', $tester->getDisplay());
    }

    private function seed(InMemoryStorage $harness, \DateTimeImmutable $createdAt): void
    {
        $harness->storage->record(new RecordedEvent(
            id: null,
            periscopeId: Uuid::v7(),
            eventType: EventType::Dispatched,
            messageClass: 'App\\Message\\Demo',
            transport: 'async',
            bus: null,
            handler: null,
            payload: null,
            stampsSummary: null,
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: false,
            metadata: null,
            createdAt: $createdAt,
        ));
    }
}
