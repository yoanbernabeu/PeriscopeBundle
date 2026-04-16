<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Scheduler\Event\FailureEvent;
use Symfony\Component\Scheduler\Event\PostRunEvent;
use Symfony\Component\Scheduler\Event\PreRunEvent;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Records Scheduler lifecycle events.
 *
 * The Scheduler sits on top of Messenger: every recurring message is also
 * routed through the Messenger pipeline and will be captured by
 * {@see MessengerEventSubscriber} with a {@see \Symfony\Component\Scheduler\Messenger\ScheduledStamp}.
 * This subscriber adds the Scheduler-specific side of the story — pre/post/failure
 * markers attributable to a given schedule, independent of transport behaviour.
 */
final class SchedulerEventSubscriber implements EventSubscriberInterface
{
    /**
     * @var array<string, float>
     */
    private array $startTimes = [];

    public function __construct(
        private readonly StorageInterface $storage,
        private readonly \DateTimeZone $timezone = new \DateTimeZone('UTC'),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PreRunEvent::class => ['onPreRun', 100],
            PostRunEvent::class => ['onPostRun', -100],
            FailureEvent::class => ['onFailure', -100],
        ];
    }

    public function onPreRun(PreRunEvent $event): void
    {
        $context = $event->getMessageContext();
        $id = $this->deriveIdFromContext($context->id);
        $this->startTimes[$id->toRfc4122()] = \microtime(true);

        $this->storage->record($this->makeEvent(
            id: $id,
            type: EventType::ScheduledBefore,
            messageClass: \get_debug_type($event->getMessage()),
            scheduleName: $this->scheduleName($event->getSchedule()),
            triggerLabel: $this->triggerLabel($context->trigger),
        ));
    }

    public function onPostRun(PostRunEvent $event): void
    {
        $context = $event->getMessageContext();
        $id = $this->deriveIdFromContext($context->id);

        $this->storage->record($this->makeEvent(
            id: $id,
            type: EventType::ScheduledAfter,
            messageClass: \get_debug_type($event->getMessage()),
            scheduleName: $this->scheduleName($event->getSchedule()),
            triggerLabel: $this->triggerLabel($context->trigger),
            durationMs: $this->consumeDuration($id),
        ));
    }

    public function onFailure(FailureEvent $event): void
    {
        $context = $event->getMessageContext();
        $id = $this->deriveIdFromContext($context->id);
        $throwable = $event->getError();

        $this->storage->record($this->makeEvent(
            id: $id,
            type: EventType::ScheduledFailed,
            messageClass: \get_debug_type($event->getMessage()),
            scheduleName: $this->scheduleName($event->getSchedule()),
            triggerLabel: $this->triggerLabel($context->trigger),
            durationMs: $this->consumeDuration($id),
            errorClass: $throwable::class,
            errorMessage: $throwable->getMessage(),
            errorTrace: $throwable->getTraceAsString(),
        ));
    }

    /**
     * The Scheduler exposes its own per-message id (UUIDv5-style derived from
     * the recurring message signature). We reuse that id — wrapped in a UUIDv5
     * when necessary — so every event belonging to the same recurring task
     * groups together in Periscope's event stream.
     */
    private function deriveIdFromContext(string $contextId): Uuid
    {
        if (Uuid::isValid($contextId)) {
            return Uuid::fromString($contextId);
        }

        // Non-UUID identifiers are mapped deterministically via UUIDv5 so that
        // the same recurring message keeps the same periscope id.
        return Uuid::v5(Uuid::fromString('00000000-0000-0000-0000-000000000000'), $contextId);
    }

    private function scheduleName(object $runnerSchedule): string
    {
        // The runner's schedule object wraps the user-defined Schedule and its
        // name. We do not hard-depend on Symfony's internal Runner class to
        // avoid coupling to a non-stable class; pull the name via reflection
        // when available, otherwise fall back to the class basename.
        if (\method_exists($runnerSchedule, 'getName')) {
            /** @var mixed $name */
            $name = $runnerSchedule->getName();
            if (\is_string($name) && '' !== $name) {
                return $name;
            }
        }

        $class = $runnerSchedule::class;
        $pos = \strrpos($class, '\\');

        return false === $pos ? $class : \substr($class, $pos + 1);
    }

    private function triggerLabel(object $trigger): string
    {
        if (\method_exists($trigger, '__toString')) {
            return (string) $trigger;
        }

        return $trigger::class;
    }

    private function makeEvent(
        Uuid $id,
        EventType $type,
        string $messageClass,
        string $scheduleName,
        string $triggerLabel,
        ?int $durationMs = null,
        ?string $errorClass = null,
        ?string $errorMessage = null,
        ?string $errorTrace = null,
    ): RecordedEvent {
        return new RecordedEvent(
            id: null,
            periscopeId: $id,
            eventType: $type,
            messageClass: $messageClass,
            transport: null,
            bus: null,
            handler: null,
            payload: null,
            stampsSummary: null,
            errorClass: $errorClass,
            errorMessage: $errorMessage,
            errorTrace: $errorTrace,
            durationMs: $durationMs,
            scheduled: true,
            metadata: [
                'schedule' => $scheduleName,
                'trigger' => $triggerLabel,
            ],
            createdAt: new \DateTimeImmutable('now', $this->timezone),
        );
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
}
