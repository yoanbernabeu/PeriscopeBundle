<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use YoanBernabeu\PeriscopeBundle\Command\QueuesCommand;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Internal\TransportFilter;
use YoanBernabeu\PeriscopeBundle\Transport\MessageCountAwareInspector;
use YoanBernabeu\PeriscopeBundle\Transport\QueueDepthProbe;

#[CoversClass(QueuesCommand::class)]
#[CoversClass(QueueDepthProbe::class)]
final class QueuesCommandTest extends TestCase
{
    public function testEmptyTransportsExitsWithNoResult(): void
    {
        $tester = $this->buildTester([], new TransportFilter());

        self::assertSame(1, $tester->execute([]));
    }

    public function testReportsCountForCountAwareTransports(): void
    {
        $tester = $this->buildTester([
            'async' => $this->countAwareTransport(12),
            'failed' => $this->countAwareTransport(0),
        ], new TransportFilter());

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        self::assertStringContainsString('async', $display);
        self::assertStringContainsString('12', $display);
        self::assertStringContainsString('failed', $display);
    }

    public function testUnsupportedTransportEmitsEmptyCount(): void
    {
        $tester = $this->buildTester([
            'opaque' => $this->opaqueTransport(),
        ], new TransportFilter());

        self::assertSame(Command::SUCCESS, $tester->execute(['--format' => 'ndjson']));

        $line = trim($tester->getDisplay());
        $decoded = json_decode($line, true);
        self::assertIsArray($decoded);
        self::assertNull($decoded['count']);
    }

    public function testTransportFilterExcludesDisabledTransports(): void
    {
        $tester = $this->buildTester([
            'async' => $this->countAwareTransport(5),
            'failed' => $this->countAwareTransport(2),
        ], new TransportFilter(exclude: ['failed']));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringNotContainsString('failed', $tester->getDisplay());
    }

    /**
     * @param array<string, TransportInterface> $transports
     */
    private function buildTester(array $transports, TransportFilter $filter): CommandTester
    {
        $factories = [];
        foreach ($transports as $name => $transport) {
            $factories[$name] = static fn (): TransportInterface => $transport;
        }

        $probe = new QueueDepthProbe(
            transports: new ServiceLocator($factories),
            inspectors: [new MessageCountAwareInspector()],
            filter: $filter,
        );

        return new CommandTester(new QueuesCommand($probe, new Renderer()));
    }

    private function countAwareTransport(int $count): TransportInterface
    {
        return new class($count) implements TransportInterface, MessageCountAwareInterface {
            public function __construct(private readonly int $count)
            {
            }

            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }

            public function getMessageCount(): int
            {
                return $this->count;
            }
        };
    }

    private function opaqueTransport(): TransportInterface
    {
        return new class implements TransportInterface {
            public function get(): iterable
            {
                return [];
            }

            public function ack(Envelope $envelope): void
            {
            }

            public function reject(Envelope $envelope): void
            {
            }

            public function send(Envelope $envelope): Envelope
            {
                return $envelope;
            }
        };
    }
}
