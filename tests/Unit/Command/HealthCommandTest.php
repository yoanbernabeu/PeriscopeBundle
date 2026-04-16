<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Command\HealthCommand;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Health\HealthCalculator;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(HealthCommand::class)]
final class HealthCommandTest extends TestCase
{
    private InMemoryStorage $harness;

    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->harness = new InMemoryStorage();
        $this->tester = new CommandTester(new HealthCommand(
            new HealthCalculator($this->harness->storage),
            new Renderer(),
        ));
    }

    public function testClearFailureRateReturnsZero(): void
    {
        $this->recordMessage(EventType::Dispatched, EventType::Received, EventType::Handled);

        self::assertSame(Command::SUCCESS, $this->tester->execute([]));
    }

    public function testBreachingFailureRateReturnsThree(): void
    {
        $this->recordMessage(EventType::Dispatched, EventType::Received, EventType::Failed);

        self::assertSame(3, $this->tester->execute(['--threshold-failure-rate' => '0.1']));
    }

    public function testBreachingMinTotalReturnsThree(): void
    {
        self::assertSame(3, $this->tester->execute(['--threshold-min-total' => '1']));
    }

    public function testInvalidFailureRateRejectsExecution(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['--threshold-failure-rate' => '2.0']));
    }

    public function testInvalidMinTotalRejectsExecution(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute(['--threshold-min-total' => 'not-a-number']));
    }

    public function testJsonFormatEmitsParseableReport(): void
    {
        $this->recordMessage(EventType::Dispatched, EventType::Received, EventType::Handled);

        self::assertSame(Command::SUCCESS, $this->tester->execute(['--format' => 'ndjson']));

        $decoded = \json_decode(\trim($this->tester->getDisplay()), true);
        self::assertIsArray($decoded);
        self::assertArrayHasKey('failure_rate', $decoded);
    }

    private function recordMessage(EventType ...$types): void
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
    }
}
