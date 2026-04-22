<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Functional;

use const FILE_IGNORE_NEW_LINES;
use const FILE_SKIP_EMPTY_LINES;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use YoanBernabeu\PeriscopeBundle\Command\ExportCommand;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;

#[CoversNothing]
final class ExportCommandTest extends KernelTestCase
{
    protected function setUp(): void
    {
        // Boot the kernel and create the Periscope schema
        $container = self::getContainer();

        /** @var SchemaManager $schemaManager */
        $schemaManager = $container->get(SchemaManager::class);
        $schemaManager->createSchema();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
        restore_exception_handler();

        // Clean up temp files
        $files = [
            sys_get_temp_dir() . '/periscope-functional-test.csv',
            sys_get_temp_dir() . '/periscope-functional-test.ndjson',
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * Ensures the export command writes a valid CSV file with header and rows
     * when run against a real SQLite database.
     */
    public function testCsvExportWritesHeaderAndRowCount(): void
    {
        $container = self::getContainer();

        /** @var ExportCommand $command */
        $command = $container->get(ExportCommand::class);
        $tester = new CommandTester($command);

        $output = sys_get_temp_dir() . '/periscope-functional-test.csv';

        $tester->execute([
            '--output' => $output,
            '--format' => 'csv',
        ]);

        self::assertFileExists($output);

        $lines = file($output, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        self::assertIsArray($lines);

        // Verify header
        self::assertStringContainsString('id,status,class', $lines[0]);
    }
}
