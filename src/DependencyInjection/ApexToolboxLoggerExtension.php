<?php

namespace ApexToolbox\SymfonyLogger\DependencyInjection;

use ApexToolbox\SymfonyLogger\EventListener\LoggerListener;
use ApexToolbox\SymfonyLogger\EventListener\LogSubscriber;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ApexToolboxLoggerExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Set configuration as parameters
        $container->setParameter('apex_toolbox_logger', $config);

        // Load services
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Register event listeners
        $container->autowire(LoggerListener::class, LoggerListener::class)
            ->addTag('kernel.event_subscriber');

        $container->autowire(LogSubscriber::class, LogSubscriber::class)
            ->addTag('kernel.event_subscriber');
    }

    public function getAlias(): string
    {
        return 'apex_toolbox_logger';
    }
}