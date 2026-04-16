<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle\Tests\Functional\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use YoanBernabeu\PeriscopeBundle\PeriscopeBundle;

/**
 * Minimal Symfony kernel used by the bundle's functional test suite.
 *
 * It registers only the bundles required to boot a container and wire the
 * PeriscopeBundle configuration: FrameworkBundle, DoctrineBundle, PeriscopeBundle.
 */
final class Kernel extends BaseKernel
{
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new DoctrineBundle(),
            new PeriscopeBundle(),
        ];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'test' => true,
                'secret' => 'test',
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            ]);

            $container->loadFromExtension('doctrine', [
                'dbal' => [
                    'driver' => 'pdo_sqlite',
                    'url' => 'sqlite:///:memory:',
                ],
            ]);
        });
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/periscope-bundle-tests/cache/' . $this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/periscope-bundle-tests/log';
    }
}
