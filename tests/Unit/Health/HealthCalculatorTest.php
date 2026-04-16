<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Health;

use Doctrine\DBAL\DriverManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Health\HealthCalculator;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\DoctrineStorage;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaProvider;

#[CoversClass(HealthCalculator::class)]
final class HealthCalculatorTest extends TestCase
{
    private DoctrineStorage $storage;

    protected function setUp(): void
    {
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $provider = new SchemaProvider();
        (new SchemaManager($connection, $provider))->createSchema();

        $this->storage = new DoctrineStorage($connection, $provider);
    }

    public function testProducesAggregatedHealthReport(): void
    {
        $this->recordMessage([EventType::Dispatched, EventType::Received, EventType::Handled], transport: 'async');
        $this->recordMessage([EventType::Dispatched, EventType::Received, EventType::Handled], transport: 'async');
        $this->recordMessage([EventType::Dispatched, EventType::Received, EventType::Failed], transport: 'async');
        $this->recordMessage([EventType::Dispatched, EventType::Received], transport: 'async');
        $this->recordMessage([EventType::Dispatched], transport: 'async');

        $report = (new HealthCalculator($this->storage))->calculate(new \DateTimeImmutable('2026-04-16 00:00:00'));

        self::assertSame(5, $report->total);
        self::assertSame(2, $report->succeeded);
        self::assertSame(1, $report->failed);
        self::assertSame(1, $report->running);
        self::assertSame(1, $report->pending);
        self::assertEqualsWithDelta(1 / 3, $report->failureRate, 0.0001);
    }

    public function testEmptyWindowYieldsZeroFailureRate(): void
    {
        $report = (new HealthCalculator($this->storage))->calculate(new \DateTimeImmutable('2026-04-16 00:00:00'));

        self::assertSame(0, $report->total);
        self::assertSame(0.0, $report->failureRate);
    }

    /**
     * @param list<EventType> $events
     */
    private function recordMessage(array $events, string $transport): void
    {
        $id = Uuid::v7();
        $clock = new \DateTimeImmutable('2026-04-16 10:00:00');

        foreach ($events as $index => $type) {
            $this->storage->record(new RecordedEvent(
                id: null,
                periscopeId: $id,
                eventType: $type,
                messageClass: 'App\\Message\\Demo',
                transport: $transport,
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
                createdAt: $clock->modify(\sprintf('+%d seconds', $index)),
            ));
        }
    }
}
