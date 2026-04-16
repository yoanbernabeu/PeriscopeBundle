<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Unit\Stamp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Stamp\StampInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;
use YoanBernabeu\PeriscopeBundle\Stamp\PeriscopeIdStamp;

#[CoversClass(PeriscopeIdStamp::class)]
final class PeriscopeIdStampTest extends TestCase
{
    public function testImplementsStampInterface(): void
    {
        self::assertContains(
            StampInterface::class,
            (array) \class_implements(PeriscopeIdStamp::class),
        );
    }

    public function testGenerateProducesV7Uuid(): void
    {
        $stamp = PeriscopeIdStamp::generate();

        self::assertInstanceOf(UuidV7::class, $stamp->id);
    }

    public function testGenerateProducesDistinctIdentifiers(): void
    {
        $first = PeriscopeIdStamp::generate();
        $second = PeriscopeIdStamp::generate();

        self::assertNotSame($first->id->toRfc4122(), $second->id->toRfc4122());
    }

    public function testConstructorAcceptsExistingUuid(): void
    {
        $uuid = Uuid::v7();
        $stamp = new PeriscopeIdStamp($uuid);

        self::assertSame($uuid, $stamp->id);
    }
}
