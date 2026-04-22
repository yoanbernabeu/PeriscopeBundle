<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Cli\CommonOptions;
use YoanBernabeu\PeriscopeBundle\Export\CsvFormatter;
use YoanBernabeu\PeriscopeBundle\Export\ExportFormatterInterface;
use YoanBernabeu\PeriscopeBundle\Export\JsonFormatter;
use YoanBernabeu\PeriscopeBundle\Export\NdjsonFormatter;
use YoanBernabeu\PeriscopeBundle\Model\MessageStatus;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;
use YoanBernabeu\PeriscopeBundle\Storage\MessageFilter;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;

#[AsCommand(
    name: 'periscope:export',
    description: 'Export Messenger and Scheduler messages to a file.',
)]
final class ExportCommand extends Command
{
    public function __construct(
        private readonly StorageInterface $storage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Path to the output file.')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Export format: csv, json, ndjson.', 'ndjson')
            ->addOption('status', null, InputOption::VALUE_REQUIRED, 'Filter by status.')
            ->addOption('include-events', null, InputOption::VALUE_NONE, 'Emit one row per event instead of per message.')
            ->addOption('transport', 't', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to one or more transport names.')
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Restrict to one or more message classes.')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Filter events after this duration (e.g. "1h", "30m", "2d").')
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'Filter events before this duration.')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of rows to return.', '20')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Retrieve and validate --output
        $outputPath = $this->resolveOutputPath($input, $output);
        if (null === $outputPath) {
            return Command::INVALID;
        }

        // Retrieve and validate --format
        $format = $this->resolveFormat($input, $output);
        if (null === $format) {
            return Command::INVALID;
        }

        // Open file for writing
        $file = $this->openFile($outputPath, $output);
        if (false === $file) {
            return Command::FAILURE;
        }

        // Resolve formatter based on format
        $formatter = match ($format) {
            'csv' => new CsvFormatter(),
            'json' => new JsonFormatter(),
            'ndjson' => new NdjsonFormatter(),
            default => new NdjsonFormatter(),
        };

        $total = $this->streamToFile($file, $formatter, $input);
        fclose($file);

        if (0 === $total) {
            return 1;
        }

        $output->writeln(\sprintf('<info>Exported %d messages to "%s".</info>', $total, $outputPath));

        return Command::SUCCESS;
    }

    /**
     * Retrave and validate --output option,
     * Return null and print error if invalid.
     */
    private function resolveOutputPath(InputInterface $input, OutputInterface $output): ?string
    {
        $outputPath = $input->getOption('output');
        if (!\is_string($outputPath) || '' === $outputPath) {
            $output->writeln('<error>--output option is required.</error>');

            return null;
        }

        return $outputPath;
    }

    /**
     * Retrieve and validate --format option,
     * Return null and print error if invalid.
     */
    private function resolveFormat(InputInterface $input, OutputInterface $output): ?string
    {
        $format = $input->getOption('format');
        if (!\is_string($format) || !\in_array($format, ['csv', 'json', 'ndjson'], true)) {
            $output->writeln('<error>--format must be csv, json, ndjson.</error>');

            return null;
        }

        return $format;
    }

    /**
     * Open file for writing,
     * return file handle or null if failed (and print error).
     *
     * @param string $outputPath Path to the output file
     * @param OutputInterface $output Output interface for printing errors
     *
     * @return resource|false
     */
    private function openFile(string $outputPath, OutputInterface $output): mixed
    {
        $file = fopen($outputPath, 'w');
        if (false === $file) {
            $output->writeln(\sprintf('<error>Cannot open file "%s" for writing.</error>', $outputPath));
        }

        return $file;
    }

    /**
     * Stream messages page by page into the file.
     * Never loads more than one page in memory at a time.
     *
     * @param resource $file
     *
     * @return int total number of rows written
     */
    private function streamToFile(mixed $file, ExportFormatterInterface $formatter, InputInterface $input): int
    {
        // Build filter from options
        $since = CommonOptions::resolveSince($input);
        $until = CommonOptions::resolveUntil($input);
        $limit = CommonOptions::resolveLimit($input);
        $statusFilter = $this->resolveStatus($input);
        $transport = $this->asStringList($input->getOption('transport'));
        $class = $this->asStringList($input->getOption('class'));
        $includeEvents = (bool) $input->getOption('include-events');

        // Write header
        $formatter->writeHeader($file, $includeEvents);

        $offset = 0;
        $total = 0;
        $firstJson = true;

        while (true) {
            $filter = new MessageFilter(
                transports: $transport,
                messageClasses: $class,
                since: $since,
                until: $until,
                limit: $limit,
                offset: $offset,
            );

            $messages = $this->storage->findMessages($filter);
            $pageSize = \count($messages);

            if ([] === $messages) {
                break;
            }

            // Filtrer par status post-aggregation
            if (null !== $statusFilter) {
                $messages = array_values(array_filter(
                    $messages,
                    static fn (MessageAggregate $message): bool => $message->status === $statusFilter,
                ));
            }

            foreach ($messages as $message) {
                if ($includeEvents) {
                    // Une ligne par événement
                    $events = $this->storage->findEvents($message->periscopeId);
                    foreach ($events as $event) {
                        $formatter->writeEventRow($file, $event, $firstJson);
                        $firstJson = false;
                        ++$total;
                    }
                } else {
                    // Une ligne par message
                    $formatter->writeRow($file, $message, $firstJson);
                    $firstJson = false;
                    ++$total;
                }
            }

            $offset += $pageSize;
        }

        $formatter->writeFooter($file);

        return $total;
    }

    /**
     * Resolve --status option.
     * Retun null if not provided or invalid.
     */
    private function resolveStatus(InputInterface $input): ?MessageStatus
    {
        $raw = $input->getOption('status');
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        return MessageStatus::tryFrom(strtolower($raw));
    }

    /**
     * Convert a mixed opyion value to a list of strings.
     * Handles both single string and array  values from symfony console.
     *
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
