<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/

namespace CCMBenchmark\TingBundle\DependencyInjection;

use CCMBenchmark\TingBundle\TingBundle;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('ting');

        $rootNode
            ->children()
                ->scalarNode('cache_file')
                    ->defaultValue('ting.' . TingBundle::VERSION . '.php')
                ->end()
                ->scalarNode('configuration_resolver_service')
                    ->info('If provided, this service will receive the configuration at boot time and can alter it.')
                    ->defaultNull()
                ->end()
                ->arrayNode('repositories')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('namespace')
                                ->isRequired()
                            ->end()
                            ->scalarNode('directory')
                                ->isRequired()
                            ->end()
                            ->scalarNode('glob')
                                ->defaultValue('*Repository.php')
                            ->end()
                            ->arrayNode('options')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('connection')->end()
                                        ->scalarNode('database')->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('connections')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('namespace')
                                ->isRequired()
                            ->end()
                            ->scalarNode('charset')->end()
                            ->arrayNode('master')
                                ->children()
                                    ->scalarNode('host')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('user')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('password')
                                        ->isRequired()
                                    ->end()
                                    ->integerNode('port')
                                        ->isRequired()
                                    ->end()
                                ->end()
                            ->end()
                            ->arrayNode('slaves')
                                ->prototype('array')
                                    ->children()
                                        ->scalarNode('host')
                                            ->isRequired()
                                        ->end()
                                        ->scalarNode('user')
                                            ->isRequired()
                                        ->end()
                                        ->scalarNode('password')
                                            ->isRequired()
                                        ->end()
                                        ->integerNode('port')
                                            ->isRequired()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('cache_service')->end()
            ->end();

        return $treeBuilder;
    }
}
