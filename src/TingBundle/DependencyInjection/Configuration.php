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
                    ->defaultValue('ting.php')
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
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('connections')
                    ->prototype('array')
                        ->children()
                            ->scalarNode('namespace')
                                ->isRequired()
                            ->end()
                            ->scalarNode('charset')
                            ->end()
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
                ->arrayNode('memcached')
                    ->canBeDisabled()
                    ->children()
                        ->scalarNode('persistent_id')
                            ->isRequired()
                        ->end()
                        ->arrayNode('servers')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('host')
                                        ->isRequired()
                                    ->end()
                                    ->integerNode('port')
                                        ->defaultValue(11211)
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('options')
                            ->prototype('array')
                                ->children()
                                    ->scalarNode('key')
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('value')
                                        ->isRequired()
                                    ->end()
                                ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
