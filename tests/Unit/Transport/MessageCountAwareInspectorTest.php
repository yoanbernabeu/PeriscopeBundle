<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Transport;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use YoanBernabeu\PeriscopeBundle\Transport\MessageCountAwareInspector;

#[CoversClass(MessageCountAwareInspector::class)]
final class MessageCountAwareInspectorTest extends TestCase
{
    public function testSupportsReturnsTrueForCountAwareTransports(): void
    {
        $inspector = new MessageCountAwareInspector();

        self::assertTrue($inspector->supports($this->countAwareTransport(5)));
    }

    public function testSupportsReturnsFalseForOpaqueTransports(): void
    {
        $inspector = new MessageCountAwareInspector();

        self::assertFalse($inspector->supports($this->opaqueTransport()));
    }

    public function testReturnsCount(): void
    {
        self::assertSame(12, (new MessageCountAwareInspector())->countMessages($this->countAwareTransport(12)));
    }

    public function testReturnsNullOnFailure(): void
    {
        $transport = new class implements TransportInterface, MessageCountAwareInterface {
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
                throw new RuntimeException('transport unavailable');
            }
        };

        self::assertNull((new MessageCountAwareInspector())->countMessages($transport));
    }

    public function testReturnsNullWhenUnsupported(): void
    {
        self::assertNull((new MessageCountAwareInspector())->countMessages($this->opaqueTransport()));
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
