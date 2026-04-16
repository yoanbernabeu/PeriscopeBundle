<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Removes Periscope events older than the configured retention window.
 *
 * Exit codes:
 * - 0: rows were deleted (or nothing to delete)
 * - 2: invalid input
 */
#[AsCommand(
    name: 'periscope:purge',
    description: 'Delete Periscope events older than the configured retention window.',
)]
final class PurgeCommand extends Command
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly int $retentionDays,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Override the retention window (e.g. "7d", "12h").')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only report the cutoff timestamp without deleting anything.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $cutoff = $this->resolveCutoff($input);
        } catch (\InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        if ((bool) $input->getOption('dry-run')) {
            $output->writeln(\sprintf('Would delete events older than %s.', $cutoff->format(\DateTimeInterface::ATOM)));

            return Command::SUCCESS;
        }

        $deleted = $this->storage->purgeOlderThan($cutoff);
        $output->writeln(\sprintf(
            'Deleted %d event%s older than %s.',
            $deleted,
            1 === $deleted ? '' : 's',
            $cutoff->format(\DateTimeInterface::ATOM),
        ));

        return Command::SUCCESS;
    }

    private function resolveCutoff(InputInterface $input): \DateTimeImmutable
    {
        $override = $input->getOption('older-than');
        if (\is_string($override) && '' !== $override) {
            return $this->parseDuration($override);
        }

        return (new \DateTimeImmutable('now'))->modify(\sprintf('-%d days', $this->retentionDays));
    }

    private function parseDuration(string $value): \DateTimeImmutable
    {
        if (\preg_match('/^(\d+)\s*([smhd])$/i', $value, $matches) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Invalid --older-than value "%s". Expected a duration like "7d" or "12h".', $value));
        }

        $amount = (int) $matches[1];
        $unit = \strtolower($matches[2]);
        $modifier = match ($unit) {
            's' => \sprintf('-%d seconds', $amount),
            'm' => \sprintf('-%d minutes', $amount),
            'h' => \sprintf('-%d hours', $amount),
            'd' => \sprintf('-%d days', $amount),
            default => throw new \InvalidArgumentException(\sprintf('Unsupported duration unit "%s".', $unit)),
        };

        return (new \DateTimeImmutable('now'))->modify($modifier);
    }
}
