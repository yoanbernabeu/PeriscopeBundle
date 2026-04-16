<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Cli;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use YoanBernabeu\PeriscopeBundle\Cli\CommonOptions;
use YoanBernabeu\PeriscopeBundle\Formatter\OutputFormat;

#[CoversClass(CommonOptions::class)]
final class CommonOptionsTest extends TestCase
{
    public function testConfigureRegistersAllOptions(): void
    {
        $command = new Command('test');
        CommonOptions::configure($command);

        $definition = $command->getDefinition();

        foreach (['format', 'fields', 'since', 'until', 'limit', 'offset'] as $name) {
            self::assertTrue($definition->hasOption($name), \sprintf('Option --%s should be registered', $name));
        }
    }

    public function testResolveFormatDefaultsToAuto(): void
    {
        self::assertSame(OutputFormat::Auto, CommonOptions::resolveFormat($this->buildInput()));
    }

    public function testResolveFormatAcceptsValidValue(): void
    {
        self::assertSame(OutputFormat::Ndjson, CommonOptions::resolveFormat($this->buildInput(['--format' => 'ndjson'])));
    }

    public function testResolveFormatRejectsUnknownValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid --format value "xml"');

        CommonOptions::resolveFormat($this->buildInput(['--format' => 'xml']));
    }

    public function testResolveFieldsDefaultsToNull(): void
    {
        self::assertNull(CommonOptions::resolveFields($this->buildInput(), ['id', 'status']));
    }

    public function testResolveFieldsParsesCsvAndTrimsWhitespace(): void
    {
        self::assertSame(
            ['id', 'status'],
            CommonOptions::resolveFields($this->buildInput(['--fields' => ' id , status ']), ['id', 'status', 'class']),
        );
    }

    public function testResolveFieldsRejectsUnknownField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown field "foo"');

        CommonOptions::resolveFields($this->buildInput(['--fields' => 'id,foo']), ['id', 'status']);
    }

    public function testResolveSinceReadsRelativeDuration(): void
    {
        $before = new \DateTimeImmutable();
        $since = CommonOptions::resolveSince($this->buildInput(['--since' => '2h']));
        $after = new \DateTimeImmutable();

        self::assertNotNull($since);
        self::assertLessThanOrEqual($before->modify('-2 hours')->getTimestamp() + 1, $since->getTimestamp());
        self::assertGreaterThanOrEqual($after->modify('-2 hours')->getTimestamp() - 1, $since->getTimestamp());
    }

    public function testResolveSinceAcceptsIsoTimestamp(): void
    {
        $since = CommonOptions::resolveSince($this->buildInput(['--since' => '2026-04-16T12:00:00Z']));

        self::assertNotNull($since);
        self::assertSame('2026-04-16T12:00:00+00:00', $since->format(\DateTimeInterface::ATOM));
    }

    public function testResolveUntilIsNullWhenUnset(): void
    {
        self::assertNull(CommonOptions::resolveUntil($this->buildInput()));
    }

    public function testResolveLimitDefault(): void
    {
        self::assertSame(20, CommonOptions::resolveLimit($this->buildInput()));
    }

    public function testResolveLimitParsesOverride(): void
    {
        self::assertSame(5, CommonOptions::resolveLimit($this->buildInput(['--limit' => '5'])));
    }

    public function testResolveLimitRejectsZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--limit must be >= 1');

        CommonOptions::resolveLimit($this->buildInput(['--limit' => '0']));
    }

    public function testResolveOffsetRejectsNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('--offset must be >= 0');

        CommonOptions::resolveOffset($this->buildInput(['--offset' => '-1']));
    }

    /**
     * @param array<string, string> $options
     */
    private function buildInput(array $options = []): ArrayInput
    {
        $command = new Command('test');
        CommonOptions::configure($command);

        $input = new ArrayInput($options, $command->getDefinition());
        $input->bind($command->getDefinition());

        return $input;
    }
}
