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

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;
use Doctrine\Common\Cache\VoidCache;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Uid\Uuid;

class TingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $xmlLoader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $xmlLoader->load('services.xml');

        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ting.cache_file', $config['cache_file']);
        $container->setParameter('ting.repositories', $config['repositories']);
        $container->setParameter('ting.connections', $config['connections']);
        $container->setParameter('ting.database_options', $config['databases_options']);
        
        $metadataRepository = $container->getDefinition('ting.metadatarepository');
        if (method_exists($container, 'registerAttributeForAutoconfiguration') === true) {
            // SF 5.4+
            $container->registerAttributeForAutoconfiguration(Table::class, function(ChildDefinition $definition, Table $attribute, \ReflectionClass $reflector) use ($container, $metadataRepository): void {
                $newMetadata = $this->getMetadata($reflector, $attribute);
                $metadataRepository->addMethodCall('addMetadata', [$attribute->repository, $newMetadata]);
            });
        }
        
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

    /**
     * @param \ReflectionClass $reflector
     * @param Table $attribute
     * @return Definition
     */
    function getMetadata(\ReflectionClass $reflector, Table $attribute): Definition
    {
        $newMetadata = new Definition(Metadata::class);
        $newMetadata->addArgument(new Reference('ting.serializerfactory'));
        $newMetadata->addMethodCall('setEntity', [$reflector->name]);
        $newMetadata->addMethodCall('setTable', [$attribute->name]);
        $newMetadata->addMethodCall('setDatabase', [$attribute->database]);
        $newMetadata->addMethodCall('setConnectionName', [$attribute->connection]);
        $newMetadata->addMethodCall('setRepository', [$attribute->repository]);

        foreach ($reflector->getProperties() as $property) {
            $mappingAttributes = $property->getAttributes(Column::class, \ReflectionAttribute::IS_INSTANCEOF);
            if (count($mappingAttributes) === 0) {
                continue;
            }
            if (count($mappingAttributes) > 1) {
                throw new \RuntimeException(sprintf('Property %s from class %s cannot have multiple mapping attributes, currently %d', $property->getName(), $attribute->name, count($mappingAttributes)));
            }
            $mappingAttribute = $mappingAttributes[0];

            $newField = [
                'fieldName' => $property->getName(),
                'columnName' => $mappingAttribute->getArguments()['column'] ?? strtolower(preg_replace('/[A-Z]/', '_\\0', lcfirst($property->getName()))), // snake case by default in database
            ];
            if ($mappingAttribute->getArguments()['autoIncrement'] ?? false) {
                $newField['autoIncrement'] = true;
            }
            if ($mappingAttribute->getArguments()['primary'] ?? false) {
                $newField['primary'] = true;
            }

            if (is_subclass_of($property->getType()->getName(), '\Brick\Geo\Geometry')) {
                $newField['type'] = 'geometry';
            } else {
                $newField['type'] = match ($property->getType()->getName()) {
                    'string' => 'string',
                    'int' => 'int',
                    'float' => 'double',
                    'bool' => 'bool',
                    'array' => 'json',
                    \DateTimeImmutable::class => 'datetime_immutable',
                    \DateTime::class => 'datetime',
                    \DateTimeZone::class => 'datetimezone',
                    Uuid::class => 'uuid',
                    default => 'string'
                };
            }

            if ($mappingAttribute->getArguments()['serializer'] ?? false) {
                $newField['serializer'] = $mappingAttribute->getArguments()['serializer'];
            }

            $newMetadata->addMethodCall('addField', [$newField]);
        }
        return $newMetadata;
    }
}
