<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Transport;

use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Throwable;

/**
 * Inspector that delegates to Symfony's own {@see MessageCountAwareInterface}.
 *
 * Every built-in transport worth observing — Doctrine, Redis, AMQP, Beanstalkd,
 * In-Memory — implements this interface, so a single adapter is enough to
 * cover the v1 matrix.
 */
final class MessageCountAwareInspector implements TransportInspectorInterface
{
    public function supports(TransportInterface $transport): bool
    {
        return $transport instanceof MessageCountAwareInterface;
    }

    public function countMessages(TransportInterface $transport): ?int
    {
        if (!$transport instanceof MessageCountAwareInterface) {
            return null;
        }

        try {
            return $transport->getMessageCount();
        } catch (Throwable) {
            return null;
        }
    }
}
