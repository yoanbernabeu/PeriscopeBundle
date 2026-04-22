<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Export;

use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

/**
 * CSV formatter — one row per message or event, with header row.
 * Uses fputcsv with RFC 4180 quoting.
 */
final class CsvFormatter implements ExportFormatterInterface
{
    /**
     * @param resource $file
     */
    public function writeHeader(mixed $file, bool $includeEvents): void
    {
        fputcsv(
            $file,
            $includeEvents
            ? ['id', 'event_type', 'class', 'transport', 'handler', 'duration_ms', 'error', 'created_at']
            : ['id', 'status', 'class', 'attempts', 'transport', 'handler', 'duration_ms', 'last_seen_at'],
            ',',
            '"',
            '\\'
        );
    }

    /**
     * @param resource $file
     */
    public function writeRow(mixed $file, MessageAggregate $message, bool $firstItem): void
    {
        fputcsv($file, array_values(RowBuilder::fromMessage($message)), ',', '"', '\\');
    }

    /**
     * @param resource $file
     */
    public function writeEventRow(mixed $file, RecordedEvent $event, bool $firstItem): void
    {
        fputcsv($file, array_values(RowBuilder::fromEvent($event)), ',', '"', '\\');
    }

    /**
     * @param resource $file
     */
    public function writeFooter(mixed $file): void
    {
        // CSV has no footer
    }
}
