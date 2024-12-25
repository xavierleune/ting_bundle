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
use Doctrine\Common\Cache\VoidCache;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class TingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container)
    {
        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $xmlLoader->load('services.xml');

        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ting.cache_file', $config['cache_file']);
        $container->setParameter('ting.repositories', $config['repositories']);
        $container->setParameter('ting.connections', $config['connections']);
        $container->setParameter('ting.database_options', $config['databases_options']);
        
        $definition = $container->getDefinition('ting.cache');
        if (isset($config['cache_provider']) === true) {
            $definition->addMethodCall('setCache', [new Reference($config['cache_provider'])]);
        } else {
            $void = new Definition(VoidCache::class);
            $void
                ->setAutoconfigured(false)
                ->setAutoconfigured(false)
            ;
            $container->setDefinition('doctrine_cache.providers.ting_cache_void', $void);
            $definition->addMethodCall('setCache', [new Reference('doctrine_cache.providers.ting_cache_void')]);
        }

        if ($config['configuration_resolver_service'] !== null) {
            $container->setAlias('ting.configuration_resolver', $config['configuration_resolver_service']);
        }
        
        $propertyAccessDefinition = $container->register('ting.cache.property_access', AdapterInterface::class);
        if (!$container->getParameter('kernel.debug')) {
            $propertyAccessDefinition->setFactory([PropertyAccessor::class, 'createCache']);
            $propertyAccessDefinition->setArguments(['', 0, TingBundle::VERSION, new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE)]);
            $propertyAccessDefinition->addTag('cache.pool', ['clearer' => 'cache.system_clearer']);
            $propertyAccessDefinition->addTag('monolog.logger', ['channel' => 'cache']);
        } else {
            $propertyAccessDefinition->setClass(ArrayAdapter::class);
            $propertyAccessDefinition->setArguments([0, false]);
        }

        // Adding optional service ting.driverlogger
        if ($container->getParameter('kernel.debug') === true) {
            $definition = new Definition('CCMBenchmark\TingBundle\Logger\DriverLogger');
            $definition->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addArgument(new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addTag('monolog.logger', ['channel' => 'ting']);
            $container->setDefinition('ting.driverlogger', $definition);

            $reference = new Reference('ting.driverlogger');

            // Add logger to connection Pool
            $definition = $container->getDefinition('ting.connectionpool');
            $definition->addArgument($reference);

            // Add logger to DataCollector
            $definition = $container->getDefinition('ting.driver_data_collector');
            $definition->addMethodCall('setDriverLogger', [$reference]);

            $definition = new Definition('CCMBenchmark\TingBundle\Logger\CacheLogger');
            $definition->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addArgument(new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addTag('monolog.logger', ['channel' => 'ting']);
            $container->setDefinition('ting.cachelogger', $definition);

            $reference = new Reference('ting.cachelogger');

            // Add logger to Cache
            $definition = $container->getDefinition('ting.cache');
            $definition->addMethodCall('setLogger', [$reference]);

            // Add logger to DataCollector
            $definition = $container->getDefinition('ting.cache_data_collector');
            $definition->addMethodCall('setCacheLogger', [$reference]);
        }
    }
}
