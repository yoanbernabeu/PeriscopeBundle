<?php

declare(strict_types=1);

namespace YoanBernabeu\PeriscopeBundle;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

/**
 * The PeriscopeBundle entry point.
 *
 * Registers the bundle services and exposes the configuration tree consumed by
 * Periscope (storage, transports, retention, masking). Service wiring lives in
 * {@see __DIR__.'/../config/services.php'}.
 */
final class PeriscopeBundle extends AbstractBundle
{
    protected string $extensionAlias = 'periscope';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('storage')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('connection')
                            ->defaultNull()
                            ->info('Doctrine DBAL connection name. Defaults to the project\'s default connection when null.')
                        ->end()
                        ->scalarNode('table_prefix')
                            ->defaultValue('periscope_')
                            ->cannotBeEmpty()
                            ->info('Prefix applied to every table created by the bundle.')
                        ->end()
                        ->scalarNode('schema')
                            ->defaultNull()
                            ->info('Optional PostgreSQL schema. When set, tables live in this schema and the table_prefix is ignored for namespacing purposes.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('transports')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('include')
                            ->scalarPrototype()->end()
                            ->info('Messenger transport names to observe. Empty means "all async transports".')
                        ->end()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->info('Messenger transport names to ignore. Takes precedence over include.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('retention')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('days')
                            ->defaultValue(30)
                            ->min(1)
                            ->info('Number of days recorded events are kept before periscope:purge removes them.')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('masking')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('fields')
                            ->scalarPrototype()->end()
                            ->info('Payload field names (case-insensitive, dot-paths supported) whose values are replaced with "***" before being persisted.')
                            ->defaultValue(['password', 'token', 'secret', 'authorization', 'api_key'])
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    /**
     * @param array{
     *     storage: array{connection: string|null, table_prefix: string, schema: string|null},
     *     transports: array{include: list<string>, exclude: list<string>},
     *     retention: array{days: int},
     *     masking: array{fields: list<string>},
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->import('../config/services.php');

        $builder->setParameter('periscope.storage.connection', $config['storage']['connection']);
        $builder->setParameter('periscope.storage.table_prefix', $config['storage']['table_prefix']);
        $builder->setParameter('periscope.storage.schema', $config['storage']['schema']);
        $builder->setParameter('periscope.transports.include', $config['transports']['include']);
        $builder->setParameter('periscope.transports.exclude', $config['transports']['exclude']);
        $builder->setParameter('periscope.retention.days', $config['retention']['days']);
        $builder->setParameter('periscope.masking.fields', $config['masking']['fields']);
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$builder->hasExtension('framework')) {
            return;
        }

        // Registering the middleware as the first middleware of every bus the
        // user has declared. If no bus is explicitly declared, FrameworkBundle
        // creates `messenger.bus.default`, so we target that by default.
        $busNames = [];
        foreach ($builder->getExtensionConfig('framework') as $config) {
            if (!\is_array($config)) {
                continue;
            }
            $messenger = $config['messenger'] ?? null;
            if (!\is_array($messenger)) {
                continue;
            }
            $buses = $messenger['buses'] ?? null;
            if (!\is_array($buses)) {
                continue;
            }
            foreach (array_keys($buses) as $name) {
                if (\is_string($name)) {
                    $busNames[$name] = true;
                }
            }
        }

        if ([] === $busNames) {
            $busNames['messenger.bus.default'] = true;
        }

        $buses = [];
        foreach (array_keys($busNames) as $name) {
            $buses[$name] = [
                'middleware' => [
                    ['id' => Middleware\AddPeriscopeIdStampMiddleware::class],
                ],
            ];
        }

        $container->extension('framework', [
            'messenger' => [
                'buses' => $buses,
            ],
        ]);
    }
}
