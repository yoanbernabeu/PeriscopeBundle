<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Internal;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\SentStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Scheduler\Messenger\ScheduledStamp;

/**
 * Turns an envelope's stamp list into a compact, JSON-serialisable map keeping
 * only the information Periscope needs to display.
 */
final readonly class StampSummarizer
{
    /**
     * @return array<string, mixed>
     */
    public function summarize(Envelope $envelope): array
    {
        $summary = [];

        $busName = $envelope->last(BusNameStamp::class);
        if ($busName instanceof BusNameStamp) {
            $summary['bus'] = $busName->getBusName();
        }

        $sent = $envelope->last(SentStamp::class);
        if ($sent instanceof SentStamp) {
            $summary['sent_to'] = $sent->getSenderAlias() ?? $sent->getSenderClass();
        }

        $transportId = $envelope->last(TransportMessageIdStamp::class);
        if ($transportId instanceof TransportMessageIdStamp) {
            $id = $transportId->getId();
            $summary['transport_message_id'] = \is_scalar($id) ? (string) $id : null;
        }

        $redelivery = $envelope->last(RedeliveryStamp::class);
        if ($redelivery instanceof RedeliveryStamp) {
            $summary['retry_count'] = $redelivery->getRetryCount();
        }

        $handled = $envelope->all(HandledStamp::class);
        if ([] !== $handled) {
            $handlers = [];
            foreach ($handled as $stamp) {
                if ($stamp instanceof HandledStamp) {
                    $handlers[] = $stamp->getHandlerName();
                }
            }
            if ([] !== $handlers) {
                $summary['handlers'] = $handlers;
            }
        }

        if (class_exists(ScheduledStamp::class) && null !== $envelope->last(ScheduledStamp::class)) {
            $summary['scheduled'] = true;
        }

        return $summary;
    }

    public function isScheduled(Envelope $envelope): bool
    {
        return class_exists(ScheduledStamp::class)
            && null !== $envelope->last(ScheduledStamp::class);
    }

    /**
     * Extracts the primary handler name (first {@see HandledStamp}) if any.
     */
    public function extractHandler(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getHandlerName() : null;
    }
}
