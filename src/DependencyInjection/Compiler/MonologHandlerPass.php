<?php

namespace ApexToolbox\Symfony\DependencyInjection\Compiler;

use ApexToolbox\Symfony\Handler\ApexToolboxLogHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class MonologHandlerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Check if Monolog is available
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }

        // Get the configuration
        if (!$container->hasParameter('apex_toolbox')) {
            return;
        }

        $config = $container->getParameter('apex_toolbox');

        // Check if enabled
        if (!($config['enabled'] ?? true)) {
            return;
        }

        // Check if the handler is already manually configured
        if ($container->hasDefinition('monolog.handler.apex_toolbox')) {
            return;
        }

        // Get the handler service
        if (!$container->hasDefinition(ApexToolboxLogHandler::class)) {
            return;
        }

        $handlerDefinition = $container->getDefinition(ApexToolboxLogHandler::class);

        // Add the monolog.handler tag to auto-register with Monolog
        $handlerDefinition->addTag('monolog.handler', [
            'channel' => '!event', // Exclude event channel to avoid noise
            'priority' => 50, // Higher priority = earlier in the chain
        ]);
    }
}
