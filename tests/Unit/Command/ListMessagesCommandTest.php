<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Command\ListMessagesCommand;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(ListMessagesCommand::class)]
final class ListMessagesCommandTest extends TestCase
{
    private InMemoryStorage $harness;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->harness = new InMemoryStorage();
        $this->tester = new CommandTester(new ListMessagesCommand($this->harness->storage, new Renderer()));
    }

    public function testEmptyStorageExitsWithNoResult(): void
    {
        self::assertSame(1, $this->tester->execute([]));
    }

    public function testSuccessfulMessagesAreListed(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['--since' => '1h']));
        self::assertStringContainsString('succeeded', $this->tester->getDisplay());
    }

    public function testStatusFilterNarrowsResults(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);
        $this->record(EventType::Dispatched, EventType::Received, EventType::Failed);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['--status' => 'failed', '--since' => '1h']));

        $display = $this->tester->getDisplay();
        self::assertStringContainsString('failed', $display);
        self::assertStringNotContainsString('succeeded', $display);
    }

    public function testUnknownStatusReturnsInvalid(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['--status' => 'wobbling']));
        self::assertStringContainsString('Invalid --status value', $this->tester->getDisplay());
    }

    public function testNdjsonEmitsOneJsonPerLine(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--format' => 'ndjson',
            '--fields' => 'id,status',
            '--since' => '1h',
        ]));

        $lines = \array_values(\array_filter(\explode("\n", $this->tester->getDisplay()), static fn ($line) => '' !== $line));
        self::assertCount(1, $lines);
        $decoded = \json_decode($lines[0], true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('status', $decoded);
    }

    public function testUnknownFieldReturnsInvalid(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['--fields' => 'not_a_field']));
    }

    public function testScheduledFilterAcceptsTrueFalse(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        self::assertSame(1, $this->tester->execute(['--scheduled' => 'true']));
        self::assertSame(Command::SUCCESS, $this->tester->execute(['--scheduled' => 'false']));
    }

    public function testScheduledFilterRejectsGarbage(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['--scheduled' => 'maybe']));
    }

    private function record(EventType ...$types): Uuid
    {
        $id = Uuid::v7();
        $clock = new \DateTimeImmutable();

        foreach ($types as $offset => $type) {
            $this->harness->storage->record(new RecordedEvent(
                id: null,
                periscopeId: $id,
                eventType: $type,
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
                createdAt: $clock->modify(\sprintf('+%d seconds', $offset)),
            ));
        }

        return $id;
    }
}
