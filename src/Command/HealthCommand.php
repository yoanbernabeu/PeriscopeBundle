<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Cli\CommonOptions;
use YoanBernabeu\PeriscopeBundle\Formatter\OutputFormat;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Health\HealthCalculator;
use YoanBernabeu\PeriscopeBundle\Health\HealthReport;

/**
 * Reports an aggregated health snapshot over a time window and exits with a
 * dedicated code when a failure threshold is breached. Designed to be used
 * directly in a cron / alerting script.
 *
 * Exit codes:
 * - 0: all thresholds respected
 * - 2: invalid input
 * - 3: a threshold was breached (failure rate or minimum volume)
 */
#[AsCommand(
    name: 'periscope:health',
    description: 'Compute an aggregated health snapshot and check thresholds.',
)]
final class HealthCommand extends Command
{
    public function __construct(
        private readonly HealthCalculator $calculator,
        private readonly Renderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $formats = implode('|', array_column(OutputFormat::cases(), 'value'));
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Time window to aggregate, e.g. "1h", "30m", "2d" or an ISO-8601 timestamp.', '15m')
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, \sprintf('Output format: %s.', $formats), OutputFormat::Auto->value)
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of columns to emit.')
            ->addOption('threshold-failure-rate', null, InputOption::VALUE_REQUIRED, 'Fail with exit code 3 when the failure rate is strictly greater than this value (0-1).')
            ->addOption('threshold-min-total', null, InputOption::VALUE_REQUIRED, 'Fail with exit code 3 when fewer than N messages were processed in the window.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $since = CommonOptions::resolveSince($input) ?? (new DateTimeImmutable('now'))->modify('-15 minutes');
            $format = CommonOptions::resolveFormat($input);
            $columns = CommonOptions::resolveFields($input, HealthReport::defaultColumns()) ?? HealthReport::defaultColumns();
            $thresholdFailureRate = $this->resolveFloat($input->getOption('threshold-failure-rate'), 'threshold-failure-rate');
            $thresholdMinTotal = $this->resolveInt($input->getOption('threshold-min-total'), 'threshold-min-total');
        } catch (InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $report = $this->calculator->calculate($since);

        $this->renderer->render([$report], $columns, $format, $output);

        if (null !== $thresholdFailureRate && $report->failureRate > $thresholdFailureRate) {
            return 3;
        }

        if (null !== $thresholdMinTotal && $report->total < $thresholdMinTotal) {
            return 3;
        }

        return Command::SUCCESS;
    }

    private function resolveFloat(mixed $value, string $name): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_string($value) && is_numeric($value)) {
            $parsed = (float) $value;
            if ($parsed < 0 || $parsed > 1) {
                throw new InvalidArgumentException(\sprintf('--%s must be between 0 and 1.', $name));
            }

            return $parsed;
        }

        throw new InvalidArgumentException(\sprintf('Invalid --%s value: expected a number between 0 and 1.', $name));
    }

    private function resolveInt(mixed $value, string $name): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException(\sprintf('Invalid --%s value: expected an integer.', $name));
    }
}
