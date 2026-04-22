<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use DateTimeImmutable;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Uuid;
use YoanBernabeu\PeriscopeBundle\Command\ExportCommand;
use YoanBernabeu\PeriscopeBundle\Model\EventType;
use YoanBernabeu\PeriscopeBundle\Model\RecordedEvent;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(ExportCommand::class)]
final class ExportCommandTest extends TestCase
{
    private InMemoryStorage $harness;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->harness = new InMemoryStorage();
        $this->tester = new CommandTester(new ExportCommand($this->harness->storage));
    }

    private function record(EventType ...$types): Uuid
    {
        $id = Uuid::v7();
        $clock = new DateTimeImmutable();

        foreach ($types as $offset => $type) {
            $this->harness->storage->record(new RecordedEvent(
                id: null,
                periscopeId: $id,
                eventType: $type,
                messageClass: 'App\\Message\\Demo',
                transport: 'async',
                bus: null,
                handler: null,
                payload: null,
                stampsSummary: null,
                errorClass: null,
                errorMessage: null,
                errorTrace: null,
                durationMs: null,
                scheduled: false,
                metadata: null,
                createdAt: $clock->modify(\sprintf('+%d seconds', $offset)),
            ));
        }

        return $id;
    }

    /**
     * Ensure the command exists with INVALID when --output is not provided.
     */
    public function testMissingOutputReturnsInvalid(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute([]));
        self::assertStringContainsString('--output option is required', $this->tester->getDisplay());
    }

    /**
     * Ensures the command exits with INVALID when an unknown format is provided.
     */
    public function testInvalidFormatReturnsInvalid(): void
    {
        self::assertSame(Command::INVALID, $this->tester->execute([
            '--output' => sys_get_temp_dir() . '/periscope-test.csv',
            '--format' => 'xml',
        ]));
        self::assertStringContainsString('--format must be csv, json, ndjson', $this->tester->getDisplay());
    }

    /**
     * Ensures the command exits with 1 when no messages are found.
     */
    public function testEmptyStorageReturnsNoResult(): void
    {
        self::assertSame(1, $this->tester->execute([
            '--output' => sys_get_temp_dir() . '/periscope-test.ndjson',
        ]));
    }

    /**
     * Ensures the CSV export writes a header row followed by one row per message.
     */
    public function testCsvExportWritesHeaderAndRows(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        $output = sys_get_temp_dir() . '/periscope-test.csv';

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--output' => $output,
            '--format' => 'csv',
            '--since' => '1h',
        ]));

        $lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        // 1 header + 2 messages =
        self::assertCount(3, $lines);
        // Verify the header
        self::assertStringContainsString('id,status,class', $lines[0]);
    }

    /**
     * Ensures the NDJSON export writes one valid JSON object per line.
     */
    public function testNdjsonExportWritesOneJsonPerLine(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        $output = sys_get_temp_dir() . '/periscope-test.ndjson';

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--output' => $output,
            '--format' => 'ndjson',
            '--since' => '1h',
        ]));

        $lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        // 2 messages = 2 lines in the output
        self::assertCount(2, $lines);
        // Verify each line is a valid JSON
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded);
            self::assertArrayHasKey('id', $decoded);
            self::assertArrayHasKey('status', $decoded);
        }
    }

    /**
     * Ensures the JSON export writes a valid JSON array.
     */
    public function testJsonExportWritesValidArray(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        $output = sys_get_temp_dir() . '/periscope-test.json';

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--output' => $output,
            '--format' => 'json',
            '--since' => '1h',
        ]));

        $content = file_get_contents($output);
        self::assertIsString($content);

        $decoded = json_decode($content, true);
        self::assertIsArray($decoded);
        // 2messages = 2 items in the array
        self::assertCount(2, $decoded);
        self::assertIsArray($decoded[0]);
        // Verify the keys of the first element
        self::assertArrayHasKey('id', $decoded[0]);
        self::assertArrayHasKey('status', $decoded[0]);
    }

    /**
     * Ensures --include-events emits one row per event instead of per message.
     */
    public function testIncludeEventsWritesOneRowPerEvent(): void
    {
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);

        $output = sys_get_temp_dir() . '/periscope-test.ndjson';

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--output' => $output,
            '--format' => 'ndjson',
            '--include-events' => true,
            '--since' => '1h',
        ]));

        $lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        // 1 message with 3 events = 3 lines in the output
        self::assertCount(3, $lines);
        // Verify each line is a event_type
        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            self::assertIsArray($decoded);
            self::assertArrayHasKey('event_type', $decoded);
        }
    }

    /**
     * Ensures the --status filter narrows results to matching messages only.
     */
    public function testStatusFilterNarrowsResults(): void
    {
        // 1 message succeeded
        $this->record(EventType::Dispatched, EventType::Received, EventType::Handled);
        // 1 message failed
        $this->record(EventType::Dispatched, EventType::Received, EventType::Failed);

        $output = sys_get_temp_dir() . '/periscope-test.ndjson';

        self::assertSame(Command::SUCCESS, $this->tester->execute([
            '--output' => $output,
            '--format' => 'ndjson',
            '--status' => 'succeeded',
            '--since' => '1h',
        ]));

        $lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);
        // Only 1 succeeded message = 1 line in the output
        self::assertCount(1, $lines);

        $decoded = json_decode($lines[0], true);
        self::assertIsArray($decoded);
        self::assertSame('succeeded', $decoded['status']);
    }

    /**
     * Cleans up any files created during testing.
     */
    protected function tearDown(): void
    {
        $files = [
            sys_get_temp_dir() . '/periscope-test.csv',
            sys_get_temp_dir() . '/periscope-test.ndjson',
            sys_get_temp_dir() . '/periscope-test.json',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
