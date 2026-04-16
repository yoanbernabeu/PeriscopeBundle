<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use YoanBernabeu\PeriscopeBundle\Command\InstallCommand;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;
use YoanBernabeu\PeriscopeBundle\Tests\Support\InMemoryStorage;

#[CoversClass(InstallCommand::class)]
final class InstallCommandTest extends TestCase
{
    public function testFirstRunCreatesSchema(): void
    {
        $harness = new InMemoryStorage();

        // Drop the schema to simulate a fresh install.
        (new SchemaManager($harness->connection, $harness->provider))->dropSchema();

        $tester = new CommandTester(new InstallCommand(new SchemaManager($harness->connection, $harness->provider)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Periscope schema created', $tester->getDisplay());
    }

    public function testSecondRunIsNoOp(): void
    {
        $harness = new InMemoryStorage();

        $tester = new CommandTester(new InstallCommand(new SchemaManager($harness->connection, $harness->provider)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('already installed', $tester->getDisplay());
    }

    public function testDumpSqlDoesNotExecute(): void
    {
        $harness = new InMemoryStorage();
        (new SchemaManager($harness->connection, $harness->provider))->dropSchema();

        $tester = new CommandTester(new InstallCommand(new SchemaManager($harness->connection, $harness->provider)));
        self::assertSame(Command::SUCCESS, $tester->execute(['--dump-sql' => true]));

        $display = $tester->getDisplay();
        self::assertStringContainsString('CREATE TABLE', $display);

        // The table must still be missing: dump-sql prints but does not execute.
        $schemaManager = $harness->connection->createSchemaManager();
        self::assertNotContains('periscope_events', $schemaManager->listTableNames());
    }
}
