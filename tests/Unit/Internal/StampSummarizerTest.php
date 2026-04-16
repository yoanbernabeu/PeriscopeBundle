<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;
use YoanBernabeu\PeriscopeBundle\Internal\StampSummarizer;

#[CoversClass(StampSummarizer::class)]
final class StampSummarizerTest extends TestCase
{
    public function testSummarizesBusAndSender(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new BusNameStamp('messenger.bus.default'),
            new SentStamp('Transport\\Async', 'async'),
        ]);

        $summary = (new StampSummarizer())->summarize($envelope);

        self::assertSame('messenger.bus.default', $summary['bus']);
        self::assertSame('async', $summary['sent_to']);
    }

    public function testHandlerStampsAreListed(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new HandledStamp('return value', 'App\\MessageHandler\\FooHandler'),
        ]);

        $summary = (new StampSummarizer())->summarize($envelope);

        self::assertSame(['App\\MessageHandler\\FooHandler'], $summary['handlers']);
    }

    public function testRetryCountExposed(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new RedeliveryStamp(2),
        ]);

        $summary = (new StampSummarizer())->summarize($envelope);

        self::assertSame(2, $summary['retry_count']);
    }

    public function testTransportMessageIdExposedWhenScalar(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new TransportMessageIdStamp('abc-123'),
        ]);

        $summary = (new StampSummarizer())->summarize($envelope);

        self::assertSame('abc-123', $summary['transport_message_id']);
    }

    public function testScheduledFlagSet(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new ScheduledStamp(new \Symfony\Component\Scheduler\Generator\MessageContext(
                name: 'default',
                id: 'abc',
                trigger: new class() implements \Symfony\Component\Scheduler\Trigger\TriggerInterface {
                    public function __toString(): string
                    {
                        return 'every minute';
                    }

                    public function getNextRunDate(\DateTimeImmutable $run): \DateTimeImmutable
                    {
                        return $run;
                    }
                },
                triggeredAt: new \DateTimeImmutable(),
                nextTriggerAt: null,
            )),
        ]);

        $summarizer = new StampSummarizer();

        self::assertTrue($summarizer->isScheduled($envelope));
        self::assertTrue($summarizer->summarize($envelope)['scheduled']);
    }

    public function testExtractHandlerReturnsPrimaryHandlerName(): void
    {
        $envelope = Envelope::wrap(new \stdClass(), [
            new HandledStamp('ok', 'App\\MessageHandler\\SendEmailHandler'),
        ]);

        self::assertSame('App\\MessageHandler\\SendEmailHandler', (new StampSummarizer())->extractHandler($envelope));
    }

    public function testExtractHandlerReturnsNullWhenAbsent(): void
    {
        self::assertNull((new StampSummarizer())->extractHandler(Envelope::wrap(new \stdClass())));
    }

    public function testSummaryIsEmptyForBareEnvelope(): void
    {
        $summary = (new StampSummarizer())->summarize(Envelope::wrap(new \stdClass()));

        self::assertSame([], $summary);
    }
}
