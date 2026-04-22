<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Export;

use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

/**
 * Contract for export formatters.
 * Each formatter handles one output format (csv, json, ndjson...).
 * To add a new format, implement this interface — no need to touch ExportCommand.
 */
interface ExportFormatterInterface
{
    /**
     * Write the opening of the file (header for CSV, '[' for JSON, nothing for NDJSON).
     *
     * @param resource $file
     * @param bool $includeEvents whether the export is in event mode or message mode
     */
    public function writeHeader(mixed $file, bool $includeEvents): void;

    /**
     * Write a single message row.
     *
     * @param resource $file
     * @param bool $firstItem used to handle separators (e.g. JSON commas)
     */
    public function writeRow(mixed $file, MessageAggregate $message, bool $firstItem): void;

    /**
     * Write a single event row.
     *
     * @param resource $file
     * @param bool $firstItem used to handle separators (e.g. JSON commas)
     */
    public function writeEventRow(mixed $file, RecordedEvent $event, bool $firstItem): void;

    /**
     * Write the closing of the file (']' for JSON, nothing for CSV/NDJSON).
     *
     * @param resource $file
     */
    public function writeFooter(mixed $file): void;
}
