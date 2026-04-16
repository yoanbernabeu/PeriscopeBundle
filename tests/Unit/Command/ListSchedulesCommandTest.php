<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use YoanBernabeu\PeriscopeBundle\Command\ListSchedulesCommand;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleInspector;

#[CoversClass(ListSchedulesCommand::class)]
final class ListSchedulesCommandTest extends TestCase
{
    public function testEmptyInspectorReportsNothing(): void
    {
        $tester = $this->buildTester([]);

        self::assertSame(1, $tester->execute([]));
    }

    public function testScheduleIsListed(): void
    {
        $tester = $this->buildTester([
            'default' => $this->provider([
                RecurringMessage::every('30 seconds', new \stdClass()),
            ]),
        ]);

        self::assertSame(Command::SUCCESS, $tester->execute([]));

        $display = $tester->getDisplay();
        self::assertStringContainsString('default', $display);
        self::assertStringContainsString('stdClass', $display);
    }

    public function testScheduleFilterNarrowsOutput(): void
    {
        $tester = $this->buildTester([
            'cron' => $this->provider([RecurringMessage::every('1 minute', new \stdClass())]),
            'default' => $this->provider([RecurringMessage::every('10 seconds', new \stdClass())]),
        ]);

        self::assertSame(Command::SUCCESS, $tester->execute(['--schedule' => 'cron']));

        $display = $tester->getDisplay();
        self::assertStringContainsString('cron', $display);
        self::assertStringNotContainsString('default', $display);
    }

    public function testUnknownScheduleExitsWithNoResult(): void
    {
        $tester = $this->buildTester([
            'default' => $this->provider([RecurringMessage::every('10 seconds', new \stdClass())]),
        ]);

        self::assertSame(1, $tester->execute(['--schedule' => 'missing']));
    }

    /**
     * @param array<string, ScheduleProviderInterface> $providers
     */
    private function buildTester(array $providers): CommandTester
    {
        $factories = [];
        foreach ($providers as $name => $provider) {
            $factories[$name] = static fn (): ScheduleProviderInterface => $provider;
        }

        $inspector = new ScheduleInspector(new ServiceLocator($factories));
        $command = new ListSchedulesCommand($inspector, new Renderer());

        return new CommandTester($command);
    }

    /**
     * @param list<RecurringMessage> $messages
     */
    private function provider(array $messages): ScheduleProviderInterface
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
