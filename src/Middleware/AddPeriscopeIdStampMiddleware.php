<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Middleware;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use YoanBernabeu\PeriscopeBundle\Stamp\PeriscopeIdStamp;

/**
 * Attaches a {@see PeriscopeIdStamp} to every message when it is first
 * dispatched. Messages that have already been received from a transport (i.e.
 * that carry a {@see ReceivedStamp}) are left untouched — the stamp added on
 * the original dispatch must propagate through retries.
 */
final readonly class AddPeriscopeIdStampMiddleware implements MiddlewareInterface
{
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $alreadyReceived = null !== $envelope->last(ReceivedStamp::class);
        $alreadyStamped = null !== $envelope->last(PeriscopeIdStamp::class);

        if (!$alreadyReceived && !$alreadyStamped) {
            $envelope = $envelope->with(PeriscopeIdStamp::generate());
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
