<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\SendMessageToTransportsEvent;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageRetriedEvent;
use Symfony\Component\Messenger\Stamp\BusNameStamp;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Internal\PayloadExtractor;
use YoanBernabeu\PeriscopeBundle\Internal\StampSummarizer;
use YoanBernabeu\PeriscopeBundle\Internal\TransportFilter;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Stamp\PeriscopeIdStamp;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Turns Messenger's dispatch / worker events into {@see RecordedEvent}s.
 *
 * Every event is append-only: retried messages produce a new Retried row,
 * followed by fresh Received/Handled|Failed rows, all sharing the
 * {@see PeriscopeIdStamp} identity. Messages crossing a transport that is
 * excluded by the bundle configuration are silently ignored.
 */
final class MessengerEventSubscriber implements EventSubscriberInterface
{
    /**
     * Start timestamps indexed by the periscope id, used to compute the
     * duration when a handled/failed event comes in.
     *
     * @var array<string, float>
     */
    private array $startTimes = [];

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly StampSummarizer $stamps,
        private readonly PayloadExtractor $payload,
        private readonly TransportFilter $transports,
        private readonly \DateTimeZone $timezone = new \DateTimeZone('UTC'),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            SendMessageToTransportsEvent::class => ['onSend', 100],
            WorkerMessageReceivedEvent::class => ['onReceived', 100],
            WorkerMessageHandledEvent::class => ['onHandled', -100],
            WorkerMessageFailedEvent::class => ['onFailed', -100],
            WorkerMessageRetriedEvent::class => ['onRetried', -100],
        ];
    }

    public function onSend(SendMessageToTransportsEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $id = $this->resolveId($envelope);
        if (null === $id) {
            return;
        }

        $transport = $this->firstSender($event);
        if (!$this->transports->accepts($transport)) {
            return;
        }

        $message = $envelope->getMessage();
        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Dispatched,
            messageClass: $message::class,
            transport: $transport,
            bus: $this->resolveBus($envelope),
            handler: null,
            payload: $this->payload->extract($message),
            stampsSummary: $this->stamps->summarize($envelope),
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: $this->stamps->isScheduled($envelope),
            metadata: null,
            createdAt: $this->now(),
        ));
    }

    public function onReceived(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $id = $this->resolveId($envelope);
        if (null === $id) {
            return;
        }

        if (!$this->transports->accepts($event->getReceiverName())) {
            return;
        }

        $this->startTimes[$id->toRfc4122()] = \microtime(true);

        $message = $envelope->getMessage();
        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Received,
            messageClass: $message::class,
            transport: $event->getReceiverName(),
            bus: $this->resolveBus($envelope),
            handler: null,
            payload: null,
            stampsSummary: $this->stamps->summarize($envelope),
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: $this->stamps->isScheduled($envelope),
            metadata: null,
            createdAt: $this->now(),
        ));
    }

    public function onHandled(WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $id = $this->resolveId($envelope);
        if (null === $id) {
            return;
        }

        if (!$this->transports->accepts($event->getReceiverName())) {
            return;
        }

        $message = $envelope->getMessage();
        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Handled,
            messageClass: $message::class,
            transport: $event->getReceiverName(),
            bus: $this->resolveBus($envelope),
            handler: $this->stamps->extractHandler($envelope),
            payload: null,
            stampsSummary: $this->stamps->summarize($envelope),
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: $this->consumeDuration($id),
            scheduled: $this->stamps->isScheduled($envelope),
            metadata: null,
            createdAt: $this->now(),
        ));
    }

    public function onFailed(WorkerMessageFailedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $id = $this->resolveId($envelope);
        if (null === $id) {
            return;
        }

        if (!$this->transports->accepts($event->getReceiverName())) {
            return;
        }

        $throwable = $event->getThrowable();
        $message = $envelope->getMessage();

        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Failed,
            messageClass: $message::class,
            transport: $event->getReceiverName(),
            bus: $this->resolveBus($envelope),
            handler: $this->stamps->extractHandler($envelope),
            payload: null,
            stampsSummary: $this->stamps->summarize($envelope),
            errorClass: $throwable::class,
            errorMessage: $throwable->getMessage(),
            errorTrace: $throwable->getTraceAsString(),
            durationMs: $this->consumeDuration($id),
            scheduled: $this->stamps->isScheduled($envelope),
            metadata: ['will_retry' => $event->willRetry()],
            createdAt: $this->now(),
        ));
    }

    public function onRetried(WorkerMessageRetriedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $id = $this->resolveId($envelope);
        if (null === $id) {
            return;
        }

        if (!$this->transports->accepts($event->getReceiverName())) {
            return;
        }

        $message = $envelope->getMessage();
        $this->storage->record(new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: EventType::Retried,
            messageClass: $message::class,
            transport: $event->getReceiverName(),
            bus: $this->resolveBus($envelope),
            handler: null,
            payload: null,
            stampsSummary: $this->stamps->summarize($envelope),
            errorClass: null,
            errorMessage: null,
            errorTrace: null,
            durationMs: null,
            scheduled: $this->stamps->isScheduled($envelope),
            metadata: null,
            createdAt: $this->now(),
        ));
    }

    private function resolveId(Envelope $envelope): ?Uuid
    {
        $stamp = $envelope->last(PeriscopeIdStamp::class);

        return $stamp instanceof PeriscopeIdStamp ? $stamp->id : null;
    }

    private function resolveBus(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(BusNameStamp::class);

        return $stamp instanceof BusNameStamp ? $stamp->getBusName() : null;
    }

    private function firstSender(SendMessageToTransportsEvent $event): ?string
    {
        // SendMessageToTransportsEvent::getSenders() returns an array keyed by alias.
        foreach (\array_keys($event->getSenders()) as $alias) {
            return \is_string($alias) ? $alias : null;
        }

        return null;
    }

    private function consumeDuration(Uuid $id): ?int
    {
        $key = $id->toRfc4122();
        if (!isset($this->startTimes[$key])) {
            return null;
        }

        $duration = (int) \round((\microtime(true) - $this->startTimes[$key]) * 1000);
        unset($this->startTimes[$key]);

        return $duration;
    }

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->timezone);
    }
}
