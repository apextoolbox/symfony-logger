<?php

namespace ApexToolbox\SymfonyLogger\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('apextoolbox');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->booleanNode('enabled')
                    ->defaultTrue()
                    ->info('Enable or disable the logger')
                ->end()
                ->booleanNode('track_http_requests')
                    ->defaultTrue()
                    ->info('Automatically track all outgoing HTTP requests')
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
                            ->defaultValue(['*'])
                            ->info('Paths to include in logging (supports wildcards)')
                        ->end()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['_debugbar/*', 'telescope/*', 'horizon/*', 'api/health', 'api/ping'])
                            ->info('Paths to exclude from logging (supports wildcards)')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('headers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['authorization', 'x-api-key', 'cookie', 'x-auth-token', 'x-access-token', 'x-refresh-token', 'bearer', 'x-secret', 'x-private-key', 'authentication'])
                            ->info('Headers to exclude from logging')
                        ->end()
                        ->arrayNode('mask')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ssn', 'social_security', 'phone', 'email', 'address', 'postal_code', 'zip_code'])
                            ->info('Headers to mask with asterisks')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('body')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'private_key', 'auth', 'authorization', 'social_security', 'credit_card', 'card_number', 'cvv', 'pin', 'otp'])
                            ->info('Fields to exclude from request body logging')
                        ->end()
                        ->arrayNode('mask')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ssn', 'social_security', 'phone', 'email', 'address', 'postal_code', 'zip_code'])
                            ->info('Fields to mask with asterisks in request body logging')
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('response')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('exclude')
                            ->scalarPrototype()->end()
                            ->defaultValue(['password', 'password_confirmation', 'token', 'access_token', 'refresh_token', 'api_key', 'secret', 'private_key', 'auth', 'authorization', 'social_security', 'credit_card', 'card_number', 'cvv', 'pin', 'otp'])
                            ->info('Fields to exclude from response logging')
                        ->end()
                        ->arrayNode('mask')
                            ->scalarPrototype()->end()
                            ->defaultValue(['ssn', 'social_security', 'phone', 'email', 'address', 'postal_code', 'zip_code'])
                            ->info('Fields to mask with asterisks in response logging')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
