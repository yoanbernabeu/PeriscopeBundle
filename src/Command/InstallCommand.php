<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;

/**
 * Creates the Doctrine tables Periscope needs to record events.
 *
 * Safe to run multiple times: already-existing tables are left untouched.
 */
#[AsCommand(
    name: 'periscope:install',
    description: 'Create the Periscope schema on the configured database connection.',
)]
final class InstallCommand extends Command
{
    public function __construct(private readonly SchemaManager $schemaManager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Print the SQL statements that would be executed without running them.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ((bool) $input->getOption('dump-sql')) {
            $statements = $this->schemaManager->toCreateSql();
            if ([] === $statements) {
                $output->writeln('-- Periscope schema is already installed.');

                return Command::SUCCESS;
            }

            foreach ($statements as $sql) {
                $output->writeln($sql . ';');
            }

            return Command::SUCCESS;
        }

        $statements = $this->schemaManager->createSchema();
        if ([] === $statements) {
            $output->writeln('<info>Periscope schema is already installed.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(\sprintf('<info>Periscope schema created (%d statement(s) executed).</info>', \count($statements)));

        return Command::SUCCESS;
    }
}
