<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Export;

use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

/**
 * NDJSON formatter — one JSON object per line.
 * No header, no footer — just one line per row.
 */
final class NdjsonFormatter implements ExportFormatterInterface
{
    /**
     * @param resource $file
     */
    public function writeHeader(mixed $file, bool $includeEvents): void
    {
        // NDJSON has no header
    }

    /**
     * @param resource $file
     */
    public function writeRow(mixed $file, MessageAggregate $message, bool $firstItem): void
    {
        fwrite($file, json_encode(RowBuilder::fromMessage($message)) . "\n");
    }

    /**
     * @param resource $file
     */
    public function writeEventRow(mixed $file, RecordedEvent $event, bool $firstItem): void
    {
        fwrite($file, json_encode(RowBuilder::fromEvent($event)) . "\n");
    }

    /**
     * @param resource $file
     */
    public function writeFooter(mixed $file): void
    {
        // NDJSON has no footer
    }
}
