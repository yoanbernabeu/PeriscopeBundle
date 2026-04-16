<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Boots a minimal Symfony kernel to verify the bundle registers, configures
 * and exposes its parameters without throwing.
 */
#[CoversNothing]
final class BundleInitializationTest extends KernelTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony's ErrorHandler registers an exception handler during kernel
        // boot that is not cleaned up automatically, which PHPUnit 11 flags as
        // risky. Popping any handlers that were pushed during the test keeps
        // the suite strict without each test having to remember.
        \restore_exception_handler();
        \restore_exception_handler();
    }

    public function testContainerBootsWithDefaultConfiguration(): void
    {
        $container = self::getContainer();

        self::assertSame('periscope_', $container->getParameter('periscope.storage.table_prefix'));
        self::assertNull($container->getParameter('periscope.storage.connection'));
        self::assertNull($container->getParameter('periscope.storage.schema'));
        self::assertSame([], $container->getParameter('periscope.transports.include'));
        self::assertSame([], $container->getParameter('periscope.transports.exclude'));
        self::assertSame(30, $container->getParameter('periscope.retention.days'));
        self::assertIsArray($container->getParameter('periscope.masking.fields'));
    }
}
