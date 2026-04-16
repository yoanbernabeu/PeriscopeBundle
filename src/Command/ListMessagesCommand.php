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
use YoanBernabeu\PeriscopeBundle\Formatter\MessageRow;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

/**
 * Lists observed Messenger/Scheduler messages with filters tuned for agents
 * and operators alike.
 *
 * Exit codes:
 * - 0: results returned
 * - 1: no result matched the filter
 * - 2: invalid input
 */
#[AsCommand(
    name: 'periscope:messages',
    description: 'List Messenger and Scheduler messages observed by Periscope.',
)]
final class ListMessagesCommand extends Command
{
    public function __construct(
        private readonly StorageInterface $storage,
        private readonly Renderer $renderer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        CommonOptions::configure($this);

        $this
            ->addOption('status', null, InputOption::VALUE_REQUIRED, \sprintf('Filter by status: %s.', implode(', ', array_column(MessageStatus::cases(), 'value'))))
            ->addOption('transport', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to one or more transport names.')
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to one or more message classes (fully qualified).')
            ->addOption('scheduled', null, InputOption::VALUE_REQUIRED, 'Limit to scheduler-triggered messages (true|false).')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $filter = new MessageFilter(
                transports: $this->asStringList($input->getOption('transport')),
                messageClasses: $this->asStringList($input->getOption('class')),
                since: CommonOptions::resolveSince($input),
                until: CommonOptions::resolveUntil($input),
                scheduledOnly: $this->resolveScheduled($input),
                limit: CommonOptions::resolveLimit($input),
                offset: CommonOptions::resolveOffset($input),
            );
            $format = CommonOptions::resolveFormat($input);
            $columns = CommonOptions::resolveFields($input, MessageRow::defaultColumns()) ?? MessageRow::defaultColumns();
            $statusFilter = $this->resolveStatus($input);
        } catch (InvalidArgumentException $exception) {
            $output->writeln(\sprintf('<error>%s</error>', $exception->getMessage()));

            return Command::INVALID;
        }

        $messages = $this->storage->findMessages($filter);
        if (null !== $statusFilter) {
            $messages = array_values(array_filter(
                $messages,
                static fn (MessageAggregate $message): bool => $message->status === $statusFilter,
            ));
        }

        if ([] === $messages) {
            return 1;
        }

        $rows = array_map(MessageRow::fromAggregate(...), $messages);
        $this->renderer->render($rows, $columns, $format, $output);

        return Command::SUCCESS;
    }

    private function resolveScheduled(InputInterface $input): ?bool
    {
        $value = $input->getOption('scheduled');
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        $lower = strtolower($value);
        if (\in_array($lower, ['1', 'true', 'yes'], true)) {
            return true;
        }
        if (\in_array($lower, ['0', 'false', 'no'], true)) {
            return false;
        }

        throw new InvalidArgumentException(\sprintf('Invalid --scheduled value "%s". Expected true|false.', $value));
    }

    private function resolveStatus(InputInterface $input): ?MessageStatus
    {
        $raw = $input->getOption('status');
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $status = MessageStatus::tryFrom(strtolower($raw));
        if (null === $status) {
            throw new InvalidArgumentException(\sprintf('Invalid --status value "%s". Allowed: %s.', $raw, implode(', ', array_column(MessageStatus::cases(), 'value'))));
        }

        return $status;
    }

    /**
     * @return list<string>
     */
    private function asStringList(mixed $value): array
    {
        if (\is_string($value) && '' !== $value) {
            return [$value];
        }

        if (!\is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $entry) {
            if (\is_string($entry) && '' !== $entry) {
                $result[] = $entry;
            }
        }

        return $result;
    }
}
