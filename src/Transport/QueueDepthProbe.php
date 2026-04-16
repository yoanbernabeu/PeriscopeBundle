<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Transport;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Messenger\Transport\TransportInterface;
use YoanBernabeu\PeriscopeBundle\Internal\TransportFilter;

/**
 * Walks every transport known to Messenger and asks each registered
 * {@see TransportInspectorInterface} for its current queue depth.
 */
final readonly class QueueDepthProbe
{
    /**
     * @param ServiceLocator<mixed>                 $transports
     * @param iterable<TransportInspectorInterface> $inspectors
     */
    public function __construct(
        #[AutowireLocator('messenger.receiver', 'alias')]
        private ServiceLocator $transports,
        private iterable $inspectors,
        private TransportFilter $filter,
    ) {
    }

    /**
     * @return list<QueueDepthSnapshot>
     */
    public function snapshot(): array
    {
        $snapshots = [];

        foreach (\array_keys($this->transports->getProvidedServices()) as $name) {
            if (!\is_string($name) || !$this->filter->accepts($name)) {
                continue;
            }

            $transport = $this->transports->get($name);
            if (!$transport instanceof TransportInterface) {
                continue;
            }

            $count = null;
            $supported = false;
            foreach ($this->inspectors as $inspector) {
                if ($inspector->supports($transport)) {
                    $supported = true;
                    $count = $inspector->countMessages($transport);
                    break;
                }
            }

            $snapshots[] = new QueueDepthSnapshot(
                transport: $name,
                count: $count,
                supported: $supported,
                adapter: $this->describeTransport($transport),
                takenAt: new \DateTimeImmutable(),
            );
        }

        return $snapshots;
    }

    private function describeTransport(TransportInterface $transport): string
    {
        $class = $transport::class;
        $pos = \strrpos($class, '\\');

        return false === $pos ? $class : \substr($class, $pos + 1);
    }
}
