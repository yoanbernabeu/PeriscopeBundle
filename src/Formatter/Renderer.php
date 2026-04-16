<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Formatter;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableStyle;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Writes a list of {@see RowInterface} values to the output stream using the
 * format requested by the caller.
 *
 * Every `periscope:*` command funnels its results through this single service,
 * so that agents and humans can rely on the exact same output semantics
 * regardless of what they are listing.
 *
 * The `Auto` format resolves to `Pretty` when the output is decorated (TTY)
 * and to `Compact` otherwise — which is what non-interactive consumers
 * (agents, pipes, CI logs) expect.
 */
final readonly class Renderer
{
    /**
     * @param list<RowInterface> $rows
     * @param list<string>       $columns names of the columns to emit in order
     */
    public function render(
        array $rows,
        array $columns,
        OutputFormat $format,
        OutputInterface $output,
    ): void {
        $format = $this->resolveAutoFormat($format, $output);
        $projected = \array_map(static fn (RowInterface $row): array => $row->toColumns(), $rows);

        match ($format) {
            OutputFormat::Compact, OutputFormat::Auto => $this->writeCompact($projected, $columns, $output),
            OutputFormat::Pretty => $this->writePretty($projected, $columns, $output),
            OutputFormat::Json => $this->writeJson($projected, $columns, $output),
            OutputFormat::Ndjson => $this->writeNdjson($projected, $columns, $output),
            OutputFormat::Yaml => $this->writeYaml($projected, $columns, $output),
        };
    }

    private function resolveAutoFormat(OutputFormat $format, OutputInterface $output): OutputFormat
    {
        if (OutputFormat::Auto !== $format) {
            return $format;
        }

        return $output->isDecorated() ? OutputFormat::Pretty : OutputFormat::Compact;
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     */
    private function writeCompact(array $rows, array $columns, OutputInterface $output): void
    {
        if ([] === $rows) {
            return;
        }

        $widths = $this->columnWidths($rows, $columns);

        $output->writeln($this->joinCells(\array_map(\strtoupper(...), $columns), $columns, $widths));

        foreach ($rows as $row) {
            $cells = \array_map(fn (string $column): string => $this->stringify($row[$column] ?? null), $columns);
            $output->writeln($this->joinCells($cells, $columns, $widths));
        }
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     */
    private function writePretty(array $rows, array $columns, OutputInterface $output): void
    {
        $table = new Table($output);
        $table->setHeaders(\array_map(\strtoupper(...), $columns));

        $style = new TableStyle();
        $style->setHorizontalBorderChars('─');
        $style->setVerticalBorderChars('│');
        $style->setCrossingChars('┼', '┌', '┬', '┐', '┤', '┘', '┴', '└', '├');
        $table->setStyle($style);

        foreach ($rows as $row) {
            $table->addRow(\array_map(fn (string $column): string => $this->stringify($row[$column] ?? null), $columns));
        }

        $table->render();
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     */
    private function writeJson(array $rows, array $columns, OutputInterface $output): void
    {
        $payload = \array_map(fn (array $row): array => $this->project($row, $columns), $rows);

        $output->writeln((string) \json_encode(
            $payload,
            \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
        ));
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     */
    private function writeNdjson(array $rows, array $columns, OutputInterface $output): void
    {
        foreach ($rows as $row) {
            $output->writeln((string) \json_encode(
                $this->project($row, $columns),
                \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
            ));
        }
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     */
    private function writeYaml(array $rows, array $columns, OutputInterface $output): void
    {
        $payload = \array_map(fn (array $row): array => $this->project($row, $columns), $rows);

        $output->write(Yaml::dump($payload, 4, 2, Yaml::DUMP_NULL_AS_TILDE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK));
    }

    /**
     * @param array<string, scalar|null> $row
     * @param list<string>               $columns
     *
     * @return array<string, scalar|null>
     */
    private function project(array $row, array $columns): array
    {
        $projection = [];
        foreach ($columns as $column) {
            $projection[$column] = $row[$column] ?? null;
        }

        return $projection;
    }

    /**
     * @param list<array<string, scalar|null>> $rows
     * @param list<string>                     $columns
     *
     * @return array<string, int>
     */
    private function columnWidths(array $rows, array $columns): array
    {
        $widths = [];
        foreach ($columns as $column) {
            $widths[$column] = \strlen($column);
        }

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $widths[$column] = \max($widths[$column], \strlen($this->stringify($row[$column] ?? null)));
            }
        }

        return $widths;
    }

    /**
     * @param list<string>       $cells
     * @param list<string>       $columns
     * @param array<string, int> $widths
     */
    private function joinCells(array $cells, array $columns, array $widths): string
    {
        $padded = [];
        foreach ($cells as $index => $value) {
            $column = $columns[$index] ?? null;
            $padded[] = null === $column ? $value : \str_pad($value, $widths[$column]);
        }

        return \rtrim(\implode('  ', $padded));
    }

    private function stringify(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value;
        }
        if (\is_string($value)) {
            return $value;
        }

        return '';
    }
}
