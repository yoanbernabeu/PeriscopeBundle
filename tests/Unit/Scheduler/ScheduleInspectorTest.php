<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Scheduler;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Scheduler\Trigger\PeriodicalTrigger;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleInspector;

#[CoversClass(ScheduleInspector::class)]
final class ScheduleInspectorTest extends TestCase
{
    public function testDescribesRecurringMessagesAcrossProviders(): void
    {
        $now = new DateTimeImmutable('2026-04-16 12:00:00', new DateTimeZone('UTC'));

        $provider = $this->providerWith([
            RecurringMessage::every('30 seconds', new stdClass()),
            RecurringMessage::every('5 minutes', new stdClass()),
        ]);

        $locator = self::locator(['default' => $provider]);

        $inspector = new ScheduleInspector($locator, $now);

        $descriptors = $inspector->describe();

        self::assertCount(2, $descriptors);
        self::assertSame('default', $descriptors[0]->scheduleName);
        self::assertSame(stdClass::class, $descriptors[0]->messageClass);
        self::assertSame(0, $descriptors[0]->position);
        self::assertSame(1, $descriptors[1]->position);
    }

    public function testTriggerLabelAndNextRunAreResolved(): void
    {
        $now = new DateTimeImmutable('2026-04-16 12:00:00', new DateTimeZone('UTC'));

        $trigger = new PeriodicalTrigger('5 minutes', $now);
        $recurring = RecurringMessage::trigger($trigger, new stdClass());

        $provider = $this->providerWith([$recurring]);
        $locator = self::locator(['timed' => $provider]);

        $descriptors = (new ScheduleInspector($locator, $now))->describe();

        self::assertNotSame('', $descriptors[0]->triggerLabel);
        self::assertNotNull($descriptors[0]->nextRunAt);
        self::assertGreaterThan($now, $descriptors[0]->nextRunAt);
    }

    public function testSkipsProvidersThatCannotBeResolved(): void
    {
        self::assertSame([], (new ScheduleInspector(self::locator([])))->describe());
    }

    /**
     * @param array<string, ScheduleProviderInterface> $providers
     *
     * @return ServiceLocator<mixed>
     */
    private static function locator(array $providers): ServiceLocator
    {
        $factories = [];
        foreach ($providers as $name => $provider) {
            $factories[$name] = static fn (): ScheduleProviderInterface => $provider;
        }

        return new ServiceLocator($factories);
    }

    /**
     * @param list<RecurringMessage> $messages
     */
    private function providerWith(array $messages): ScheduleProviderInterface
    {
        return new class($messages) implements ScheduleProviderInterface {
            /**
             * @param list<RecurringMessage> $messages
             */
            public function __construct(private readonly array $messages)
            {
            }

            public function getSchedule(): Schedule
            {
                return (new Schedule())->with(...$this->messages);
            }
        };
    }
}
