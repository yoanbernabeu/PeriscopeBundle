<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use YoanBernabeu\PeriscopeBundle\Internal\TransportFilter;

#[CoversClass(TransportFilter::class)]
final class TransportFilterTest extends TestCase
{
    public function testDefaultFilterAcceptsEverything(): void
    {
        $filter = new TransportFilter();

        self::assertTrue($filter->accepts('async'));
        self::assertTrue($filter->accepts('failed'));
        self::assertTrue($filter->accepts(null));
    }

    public function testExcludeTakesPrecedenceOverInclude(): void
    {
        $filter = new TransportFilter(include: ['async'], exclude: ['async']);

        self::assertFalse($filter->accepts('async'));
    }

    public function testIncludeRestrictsTransports(): void
    {
        $filter = new TransportFilter(include: ['async', 'failed']);

        self::assertTrue($filter->accepts('async'));
        self::assertTrue($filter->accepts('failed'));
        self::assertFalse($filter->accepts('high_priority'));
    }

    public function testExcludeFiltersTransports(): void
    {
        $filter = new TransportFilter(exclude: ['sync']);

        self::assertFalse($filter->accepts('sync'));
        self::assertTrue($filter->accepts('async'));
    }

    public function testNullTransportIsAlwaysAccepted(): void
    {
        $filter = new TransportFilter(include: ['async']);

        self::assertTrue($filter->accepts(null));
    }
}
