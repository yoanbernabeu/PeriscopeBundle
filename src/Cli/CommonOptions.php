<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Cli;

use DateTimeImmutable;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Throwable;
use YoanBernabeu\PeriscopeBundle\Formatter\OutputFormat;

/**
 * Shared configuration and parsing of CLI options consumed by every
 * `periscope:*` command.
 *
 * This type deliberately only performs *parsing* — it returns raw typed
 * values, leaving the final filter assembly to the caller. Splitting the
 * responsibility keeps commands small while enforcing a single, consistent
 * UX across the whole bundle.
 */
final readonly class CommonOptions
{
    /**
     * Registers `--format`, `--fields`, `--since`, `--until`, `--limit`,
     * `--offset` on the given command, with sane defaults.
     */
    public static function configure(Command $command, int $defaultLimit = 20, string $defaultSince = '1h'): void
    {
        $formats = implode('|', array_column(OutputFormat::cases(), 'value'));

        $command
            ->addOption('format', 'f', InputOption::VALUE_REQUIRED, \sprintf('Output format: %s.', $formats), OutputFormat::Auto->value)
            ->addOption('fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of columns to emit.')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Filter events after this duration (e.g. "1h", "30m", "2d") or ISO-8601 timestamp.', $defaultSince)
            ->addOption('until', null, InputOption::VALUE_REQUIRED, 'Filter events before this duration or ISO-8601 timestamp.')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of rows to return.', (string) $defaultLimit)
            ->addOption('offset', null, InputOption::VALUE_REQUIRED, 'Number of rows to skip.', '0')
        ;
    }

    public static function resolveFormat(InputInterface $input): OutputFormat
    {
        $raw = $input->getOption('format');
        if (!\is_string($raw)) {
            return OutputFormat::Auto;
        }

        $format = OutputFormat::tryFrom($raw);
        if (null === $format) {
            throw new InvalidArgumentException(\sprintf('Invalid --format value "%s".', $raw));
        }

        return $format;
    }

    /**
     * @param list<string> $allowed
     *
     * @return list<string>|null null when no field restriction was requested
     */
    public static function resolveFields(InputInterface $input, array $allowed): ?array
    {
        $raw = $input->getOption('fields');
        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        $fields = array_values(array_filter(
            array_map(\trim(...), explode(',', $raw)),
            static fn (string $field): bool => '' !== $field,
        ));

        foreach ($fields as $field) {
            if (!\in_array($field, $allowed, true)) {
                throw new InvalidArgumentException(\sprintf('Unknown field "%s". Allowed: %s.', $field, implode(', ', $allowed)));
            }
        }

        return [] === $fields ? null : $fields;
    }

    public static function resolveSince(InputInterface $input): ?DateTimeImmutable
    {
        return self::parseTime($input->getOption('since'), 'since');
    }

    public static function resolveUntil(InputInterface $input): ?DateTimeImmutable
    {
        return self::parseTime($input->getOption('until'), 'until');
    }

    public static function resolveLimit(InputInterface $input): int
    {
        $value = self::toInt($input->getOption('limit'), 'limit');
        if ($value < 1) {
            throw new InvalidArgumentException(\sprintf('--limit must be >= 1, got %d.', $value));
        }

        return $value;
    }

    public static function resolveOffset(InputInterface $input): int
    {
        $value = self::toInt($input->getOption('offset'), 'offset');
        if ($value < 0) {
            throw new InvalidArgumentException(\sprintf('--offset must be >= 0, got %d.', $value));
        }

        return $value;
    }

    private static function parseTime(mixed $value, string $name): ?DateTimeImmutable
    {
        if (!\is_string($value) || '' === $value) {
            return null;
        }

        // Relative duration shortcut: "1h", "15m", "2d".
        if (preg_match('/^(\d+)\s*([smhd])$/i', $value, $matches) === 1) {
            $amount = (int) $matches[1];
            $unit = strtolower($matches[2]);
            $seconds = match ($unit) {
                's' => $amount,
                'm' => $amount * 60,
                'h' => $amount * 3600,
                'd' => $amount * 86400,
                default => throw new InvalidArgumentException(\sprintf('Unsupported duration unit "%s".', $unit)),
            };

            return (new DateTimeImmutable('now'))->modify(\sprintf('-%d seconds', $seconds));
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            throw new InvalidArgumentException(\sprintf('Invalid --%s value "%s": %s', $name, $value, $exception->getMessage()), 0, $exception);
        }
    }

    private static function toInt(mixed $value, string $name): int
    {
        if (\is_int($value)) {
            return $value;
        }
        if (\is_string($value) && is_numeric($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException(\sprintf('Invalid --%s value: expected integer.', $name));
    }
}
