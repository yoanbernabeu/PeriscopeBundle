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
use YoanBernabeu\PeriscopeBundle\Transport\QueueDepthProbe;
use YoanBernabeu\PeriscopeBundle\Transport\QueueDepthSnapshot;

/**
 * Displays the current depth of every observable Messenger transport.
 *
 * Exit codes:
 * - 0: at least one transport was probed
 * - 1: no transport is observed
 * - 2: invalid input
 */
#[AsCommand(
    name: 'periscope:queues',
    description: 'Show the current depth of every observed Messenger transport.',
)]
final class QueuesCommand extends Command
{
    public function __construct(
        private readonly QueueDepthProbe $probe,
        private readonly Renderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $formats = implode('|', array_column(OutputFormat::cases(), 'value'));
        $this
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, \sprintf('Output format: %s.', $formats), OutputFormat::Auto->value)
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of columns to emit.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $format = CommonOptions::resolveFormat($input);
            $columns = CommonOptions::resolveFields($input, QueueDepthSnapshot::defaultColumns()) ?? QueueDepthSnapshot::defaultColumns();
        } catch (InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $snapshots = $this->probe->snapshot();
        if ([] === $snapshots) {
            return 1;
        }

        $this->renderer->render($snapshots, $columns, $format, $output);

        return Command::SUCCESS;
    }
}
