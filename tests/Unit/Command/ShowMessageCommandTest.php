<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Command\ShowMessageCommand;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(ShowMessageCommand::class)]
final class ShowMessageCommandTest extends TestCase
{
    private InMemoryStorage $harness;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->harness = new InMemoryStorage();
        $this->tester = new CommandTester(new ShowMessageCommand($this->harness->storage, new Renderer()));
    }

    public function testKnownIdEmitsTimeline(): void
    {
        $id = $this->recordTimeline();

        self::assertSame(Command::SUCCESS, $this->tester->execute(['id' => $id->toRfc4122()]));

        $display = $this->tester->getDisplay();
        self::assertStringContainsString('dispatched', $display);
        self::assertStringContainsString('received', $display);
        self::assertStringContainsString('handled', $display);
    }

    public function testUnknownIdExitsWithCodeOne(): void
    {
        $id = Uuid::v7();

        self::assertSame(1, $this->tester->execute(['id' => $id->toRfc4122()]));
    }

    public function testInvalidUuidIsRejected(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['id' => 'not-a-uuid']));
        self::assertStringContainsString('is not a valid UUID', $this->tester->getDisplay());
    }

    public function testFieldsFilterRestrictsColumns(): void
    {
        $id = $this->recordTimeline();

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            'id' => $id->toRfc4122(),
            '--fields' => 'event,duration_ms',
            '--format' => 'ndjson',
        ]));

        $lines = array_values(array_filter(explode("\n", $this->tester->getDisplay()), static fn ($line) => '' !== $line));
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded);
            self::assertArrayHasKey('event', $decoded);
            self::assertArrayNotHasKey('handler', $decoded);
        }
    }

    private function recordTimeline(): Uuid
    {
        $id = Uuid::v7();
        $clock = new DateTimeImmutable();

        foreach ([EventType::Dispatched, EventType::Received, EventType::Handled] as $offset => $type) {
            $this->harness->storage->record(new RecordedEvent(
                id: null,
                periscopeId: $id,
                eventType: $type,
                messageClass: 'App\\Message\\Demo',
                transport: 'async',
                bus: null,
                handler: EventType::Handled === $type ? 'App\\MessageHandler\\DemoHandler' : null,
                payload: null,
                stampsSummary: null,
                errorClass: null,
                errorMessage: null,
                errorTrace: null,
                durationMs: EventType::Handled === $type ? 42 : null,
                scheduled: false,
                metadata: null,
                createdAt: $clock->modify(\sprintf('+%d seconds', $offset)),
            ));
        }

        return $id;
    }
}
