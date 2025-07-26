<?php

namespace ApexToolbox\SymfonyLogger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('apex_toolbox_logger');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable the logger')
                ->end()
                ->scalarNode('token')
                    ->defaultValue('')
                    ->info('Authentication token for the logger service')
                ->end()
                ->arrayNode('path_filters')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('include')
                            ->scalarPrototype()->end()
                            ->defaultValue(['api/*'])
                            ->info('Paths to include in logging (supports wildcards)')
                        ->end()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['api/health', 'api/ping'])
                            ->info('Paths to exclude from logging (supports wildcards)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('include_sensitive')
                            ->defaultFalse()
                            ->info('Include sensitive headers like Authorization')
                        ->end()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['authorization', 'x-api-key', 'cookie'])
                            ->info('Headers to exclude from logging')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('body')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('max_size')
                            ->defaultValue(10240)
                            ->info('Maximum body size in bytes (10KB default)')
                        ->end()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['password', 'password_confirmation', 'token', 'secret'])
                            ->info('Fields to exclude from request body logging')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}