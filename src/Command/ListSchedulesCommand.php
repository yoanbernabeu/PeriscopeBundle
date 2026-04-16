<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Cli\CommonOptions;
use YoanBernabeu\PeriscopeBundle\Formatter\OutputFormat;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleDescriptor;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleInspector;

/**
 * Lists every recurring message configured across all schedules of the
 * application, including their next run time.
 *
 * Exit codes:
 * - 0: schedules were found
 * - 1: no schedule is registered
 * - 2: invalid input
 */
#[AsCommand(
    name: 'periscope:schedules',
    description: 'List every recurring message configured in the application.',
)]
final class ListSchedulesCommand extends Command
{
    public function __construct(
        private readonly ScheduleInspector $inspector,
        private readonly Renderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        // Schedules are static configuration, so the time-window options do not
        // apply; we register only format/fields/limit from CommonOptions via
        // direct InputOption declarations.
        $formats = implode('|', array_column(OutputFormat::cases(), 'value'));
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, \sprintf('Output format: %s.', $formats), OutputFormat::Auto->value)
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of columns to emit.')
            ->addOption('schedule', 's', InputOption::VALUE_REQUIRED, 'Restrict the output to a single schedule name.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $format = CommonOptions::resolveFormat($input);
            $columns = CommonOptions::resolveFields($input, ScheduleDescriptor::defaultColumns()) ?? ScheduleDescriptor::defaultColumns();
        } catch (InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $descriptors = $this->inspector->describe();

        $scheduleName = $input->getOption('schedule');
        if (\is_string($scheduleName) && '' !== $scheduleName) {
            $descriptors = array_values(array_filter(
                $descriptors,
                static fn (ScheduleDescriptor $descriptor): bool => $descriptor->scheduleName === $scheduleName,
            ));
        }

        if ([] === $descriptors) {
            return 1;
        }

        $this->renderer->render($descriptors, $columns, $format, $output);

        return Command::SUCCESS;
    }
}
