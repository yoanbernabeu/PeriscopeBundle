<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Export;

use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Storage\MessageAggregate;

/**
 * JSON formatter — streams a valid JSON array without loading all rows in memory.
 * Opens with '[', separates rows with ',', closes with ']'.
 */
final class JsonFormatter implements ExportFormatterInterface
{
    /**
     * @param resource $file
     */
    public function writeHeader(mixed $file, bool $includeEvents): void
    {
        fwrite($file, '[');
    }

    /**
     * @param resource $file
     */
    public function writeRow(mixed $file, MessageAggregate $message, bool $firstItem): void
    {
        fwrite($file, ($firstItem ? '' : ',') . json_encode(RowBuilder::fromMessage($message)));
    }

    /**
     * @param resource $file
     */
    public function writeEventRow(mixed $file, RecordedEvent $event, bool $firstItem): void
    {
        fwrite($file, ($firstItem ? '' : ',') . json_encode(RowBuilder::fromEvent($event)));
    }

    /**
     * @param resource $file
     */
    public function writeFooter(mixed $file): void
    {
        fwrite($file, ']');
    }
}
