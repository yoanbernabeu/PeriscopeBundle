<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Cli\CommonOptions;
use YoanBernabeu\PeriscopeBundle\Formatter\EventRow;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Displays the full event timeline of a message identified by its periscope
 * id. Every row maps to a single {@see \YoanBernabeu\PeriscopeBundle\Model\RecordedEvent}.
 *
 * Exit codes:
 * - 0: timeline emitted
 * - 1: id is unknown
 * - 2: invalid input
 */
#[AsCommand(
    name: 'periscope:message',
    description: 'Show the full event timeline of a single message.',
)]
final class ShowMessageCommand extends Command
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Renderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        CommonOptions::configure($this, defaultLimit: 100);

        $this->addArgument('id', InputArgument::REQUIRED, 'The periscope id (UUID) of the message to inspect.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $id = $this->resolveId($input);
            $format = CommonOptions::resolveFormat($input);
            $columns = CommonOptions::resolveFields($input, EventRow::defaultColumns()) ?? EventRow::defaultColumns();
        } catch (\InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $events = $this->storage->findEvents($id);

        if ([] === $events) {
            return 1;
        }

        $rows = \array_map(EventRow::fromEvent(...), $events);
        $this->renderer->render($rows, $columns, $format, $output);

        return Command::SUCCESS;
    }

    private function resolveId(InputInterface $input): Uuid
    {
        $raw = $input->getArgument('id');
        if (!\is_string($raw) || '' === $raw) {
            throw new \InvalidArgumentException('The id argument must be a non-empty UUID.');
        }

        if (!Uuid::isValid($raw)) {
            throw new \InvalidArgumentException(\sprintf('The id "%s" is not a valid UUID.', $raw));
        }

        return Uuid::fromString($raw);
    }
}
