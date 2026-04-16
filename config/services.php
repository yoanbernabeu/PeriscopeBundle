<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;
use YoanBernabeu\PeriscopeBundle\Command\HealthCommand;
use YoanBernabeu\PeriscopeBundle\Command\InstallCommand;
use YoanBernabeu\PeriscopeBundle\Command\ListMessagesCommand;
use YoanBernabeu\PeriscopeBundle\Command\ListSchedulesCommand;
use YoanBernabeu\PeriscopeBundle\Command\PurgeCommand;
use YoanBernabeu\PeriscopeBundle\Command\QueuesCommand;
use YoanBernabeu\PeriscopeBundle\Command\ShowMessageCommand;
use YoanBernabeu\PeriscopeBundle\EventListener\MessengerEventSubscriber;
use YoanBernabeu\PeriscopeBundle\EventListener\SchedulerEventSubscriber;
use YoanBernabeu\PeriscopeBundle\Formatter\Renderer;
use YoanBernabeu\PeriscopeBundle\Health\HealthCalculator;
use YoanBernabeu\PeriscopeBundle\Internal\PayloadExtractor;
use YoanBernabeu\PeriscopeBundle\Internal\StampSummarizer;
use YoanBernabeu\PeriscopeBundle\Internal\TransportFilter;
use YoanBernabeu\PeriscopeBundle\Middleware\AddPeriscopeIdStampMiddleware;
use YoanBernabeu\PeriscopeBundle\Scheduler\ScheduleInspector;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\DoctrineStorage;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaManager;
use YoanBernabeu\PeriscopeBundle\Storage\Doctrine\SchemaProvider;
use YoanBernabeu\PeriscopeBundle\Storage\StorageInterface;
use YoanBernabeu\PeriscopeBundle\Transport\MessageCountAwareInspector;
use YoanBernabeu\PeriscopeBundle\Transport\QueueDepthProbe;
use YoanBernabeu\PeriscopeBundle\Transport\TransportInspectorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure();

    // Storage.
    $services->set(SchemaProvider::class)
        ->arg('$tablePrefix', '%periscope.storage.table_prefix%')
        ->arg('$pgSchema', '%periscope.storage.schema%');
    $services->set(SchemaManager::class);
    $services->set(DoctrineStorage::class);
    $services->alias(StorageInterface::class, DoctrineStorage::class);

    // Ingestion helpers.
    $services->set(StampSummarizer::class);
    $services->set(PayloadExtractor::class)
        ->arg('$maskedFields', '%periscope.masking.fields%');
    $services->set(TransportFilter::class)
        ->arg('$include', '%periscope.transports.include%')
        ->arg('$exclude', '%periscope.transports.exclude%');

    // Middleware + event subscribers.
    $services->set(AddPeriscopeIdStampMiddleware::class);
    $services->set(MessengerEventSubscriber::class);
    $services->set(SchedulerEventSubscriber::class);

    // Rendering.
    $services->set(Renderer::class);

    // Scheduler inspection.
    $services->set(ScheduleInspector::class);

    // Transport inspection (tagged so the probe can iterate over them).
    $services->instanceof(TransportInspectorInterface::class)
        ->tag('periscope.transport_inspector');
    $services->set(MessageCountAwareInspector::class);
    $services->set(QueueDepthProbe::class)
        ->arg('$inspectors', tagged_iterator('periscope.transport_inspector'));

    // Health.
    $services->set(HealthCalculator::class);

    // Commands.
    $services->set(InstallCommand::class);
    $services->set(ListMessagesCommand::class);
    $services->set(ShowMessageCommand::class);
    $services->set(ListSchedulesCommand::class);
    $services->set(QueuesCommand::class);
    $services->set(PurgeCommand::class);
    $services->set(HealthCommand::class)
        ->arg('$calculator', service(HealthCalculator::class));
    $services->get(PurgeCommand::class)
        ->arg('$retentionDays', '%periscope.retention.days%');
};
