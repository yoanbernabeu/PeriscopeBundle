<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Scheduler;

use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * Enumerates all {@see ScheduleProviderInterface} services tagged
 * `scheduler.schedule_provider` by the Symfony Scheduler component.
 *
 * Wrapping the tagged locator behind a dedicated inspector keeps our command
 * independent from the DI container machinery and makes unit tests trivial:
 * pass an in-memory iterator and the inspector gives back a deterministic
 * {@see ScheduleDescriptor} list.
 */
final readonly class ScheduleInspector
{
    /**
     * @param ServiceLocator<mixed> $schedules
     */
    public function __construct(
        #[AutowireLocator('scheduler.schedule_provider', 'name')]
        private ServiceLocator $schedules,
        private \DateTimeImmutable $clock = new \DateTimeImmutable(),
    ) {
    }

    /**
     * @return list<ScheduleDescriptor>
     */
    public function describe(): array
    {
        $descriptors = [];

        foreach (\array_keys($this->schedules->getProvidedServices()) as $name) {
            if (!\is_string($name) || !$this->schedules->has($name)) {
                continue;
            }

            $provider = $this->schedules->get($name);
            if (!$provider instanceof ScheduleProviderInterface) {
                continue;
            }

            $position = 0;
            foreach ($provider->getSchedule()->getRecurringMessages() as $recurring) {
                $descriptors[] = new ScheduleDescriptor(
                    scheduleName: $name,
                    messageClass: $this->describeMessage($recurring, $name),
                    triggerLabel: $this->describeTrigger($recurring->getTrigger()),
                    nextRunAt: $this->nextRun($recurring->getTrigger()),
                    providerClass: $provider::class,
                    position: $position++,
                );
            }
        }

        return $descriptors;
    }

    private function describeMessage(RecurringMessage $recurring, string $scheduleName): string
    {
        // RecurringMessage::getMessages() requires a MessageContext; we build
        // an inspection-only one that is never dispatched so we can peek at
        // the underlying message class without side effects.
        $context = new MessageContext(
            name: $scheduleName,
            id: 'periscope.inspection',
            trigger: $recurring->getTrigger(),
            triggeredAt: $this->clock,
            nextTriggerAt: null,
        );

        try {
            foreach ($recurring->getMessages($context) as $message) {
                if (\is_object($message)) {
                    return $message::class;
                }
                if (\is_string($message)) {
                    return $message;
                }
            }
        } catch (\Throwable) {
            // Some message providers require real runtime state; treat as
            // unknown rather than letting the inspection fail.
        }

        return 'unknown';
    }

    private function describeTrigger(TriggerInterface $trigger): string
    {
        return (string) $trigger;
    }

    private function nextRun(TriggerInterface $trigger): ?\DateTimeImmutable
    {
        try {
            return $trigger->getNextRunDate($this->clock);
        } catch (\Throwable) {
            return null;
        }
    }
}
