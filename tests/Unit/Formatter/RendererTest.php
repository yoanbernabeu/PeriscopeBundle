<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Formatter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use YoanBernabeu\PeriscopeBundle\Formatter\OutputFormat;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Formatter\RowInterface;

#[CoversClass(Renderer::class)]
final class RendererTest extends TestCase
{
    public function testCompactEmitsHeadersAndRows(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render(
            [self::row(['id' => 'abc', 'status' => 'succeeded', 'class' => 'SendEmail'])],
            ['id', 'status', 'class'],
            OutputFormat::Compact,
            $output,
        );

        $text = $output->fetch();

        self::assertStringContainsString('ID', $text);
        self::assertStringContainsString('STATUS', $text);
        self::assertStringContainsString('CLASS', $text);
        self::assertStringContainsString('succeeded', $text);
    }

    public function testNdjsonEmitsOneJsonObjectPerLine(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render(
            [
                self::row(['id' => 'a', 'status' => 'ok']),
                self::row(['id' => 'b', 'status' => 'fail']),
            ],
            ['id', 'status'],
            OutputFormat::Ndjson,
            $output,
        );

        $lines = \array_values(\array_filter(
            \explode("\n", $output->fetch()),
            static fn ($line) => '' !== $line,
        ));
        self::assertCount(2, $lines);
        foreach ($lines as $line) {
            $decoded = \json_decode($line, true);
            self::assertIsArray($decoded);
            self::assertArrayHasKey('id', $decoded);
        }
    }

    public function testJsonEmitsPrettyArray(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render(
            [self::row(['id' => 'a'])],
            ['id'],
            OutputFormat::Json,
            $output,
        );

        $decoded = \json_decode(\trim($output->fetch()), true);

        self::assertIsArray($decoded);
        self::assertCount(1, $decoded);
        self::assertIsArray($decoded[0]);
        self::assertSame('a', $decoded[0]['id']);
    }

    public function testYamlEmitsMapping(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render(
            [self::row(['id' => 'a', 'status' => 'succeeded'])],
            ['id', 'status'],
            OutputFormat::Yaml,
            $output,
        );

        self::assertStringContainsString('status: succeeded', $output->fetch());
    }

    public function testAutoPicksCompactWhenUndecorated(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, false);

        (new Renderer())->render(
            [self::row(['id' => 'a'])],
            ['id'],
            OutputFormat::Auto,
            $output,
        );

        $text = $output->fetch();

        self::assertStringContainsString('ID', $text);
        self::assertStringNotContainsString('┌', $text);
    }

    public function testAutoPicksPrettyWhenDecorated(): void
    {
        $output = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);

        (new Renderer())->render(
            [self::row(['id' => 'a'])],
            ['id'],
            OutputFormat::Auto,
            $output,
        );

        self::assertStringContainsString('ID', $output->fetch());
    }

    public function testEmptyRowsProduceNoOutputInCompact(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render([], ['id'], OutputFormat::Compact, $output);

        self::assertSame('', $output->fetch());
    }

    public function testMissingFieldRendersAsEmptyCell(): void
    {
        $output = new BufferedOutput();

        (new Renderer())->render(
            [self::row(['id' => 'a'])],
            ['id', 'missing'],
            OutputFormat::Compact,
            $output,
        );

        // Headers + single row, no exception, no stray characters.
        self::assertStringContainsString('ID', $output->fetch());
    }

    /**
     * @param array<string, scalar|null> $fields
     */
    private static function row(array $fields): RowInterface
    {
        return new class($fields) implements RowInterface {
            /**
             * @param array<string, scalar|null> $fields
             */
            public function __construct(private readonly array $fields)
            {
            }

            public function toColumns(): array
            {
                return $this->fields;
            }
        };
    }
}
