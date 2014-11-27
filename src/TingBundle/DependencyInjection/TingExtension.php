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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;

class TingExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ting.repositories', $config['repositories']);
        $container->setParameter('ting.connections', $config['connections']);

        // Adding optional service ting_driverlogger
        if ($container->getParameter('kernel.debug') === true) {
            $definition = new Definition('CCMBenchmark\TingBundle\Logger\DriverLogger');
            $definition->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addArgument(new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $container->setDefinition('ting_driverlogger', $definition);

            $reference = new Reference('ting_driverlogger');

            // Add logger to connection Pool
            $definition = $container->getDefinition('ting_connectionpool');
            $definition->addArgument($reference);

            // Add logger to DataCollector
            $definition = $container->getDefinition('ting_driver_data_collector');
            $definition->addMethodCall('setDriverLogger', [$reference]);
        }

        $servers = $config['memcached']['servers'];
        $config['memcached']['servers'] = array_values($servers);

        $options = $config['memcached']['options'];
        $config['memcached']['options'] = [];

        foreach ($options as $data) {
            if (defined($data['key']) === true) {
                $data['key'] = constant($data['key']);
            }

            if (defined($data['value']) === true) {
                $data['value'] = constant($data['value']);
            }

            $config['memcached']['options'][$data['key']] = $data['value'];
        }

        $container->setParameter('ting.memcached', $config['memcached']);


        // Definition of ting_cache_memcached service
        $definition = $container->getDefinition('ting_cache');
        $definition->addMethodCall('setConfig', [$config['memcached']]);

        if ($container->getParameter('kernel.debug') === true) {
            $definition = new Definition('CCMBenchmark\TingBundle\Logger\CacheLogger');
            $definition->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addArgument(new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $container->setDefinition('ting_cachelogger', $definition);

            $reference = new Reference('ting_cachelogger');

            // Add logger to connection Pool
            $definition = $container->getDefinition('ting_cache');
            $definition->addMethodCall('setLogger', [$reference]);

            // Add logger to DataCollector
            $definition = $container->getDefinition('ting_cache_data_collector');
            $definition->addMethodCall('setCacheLogger', [$reference]);
        }

    }
}
