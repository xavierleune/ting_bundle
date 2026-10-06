<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 * Copyright (C) 2026 Xavier Leune
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
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\ArgumentResolver\EntityValueResolver;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;
use CCMBenchmark\TingBundle\Serializer\SymfonySerializer;
use Symfony\Component\DependencyInjection\ChildDefinition;
use CCMBenchmark\TingBundle\TingBundle;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class TingExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.php');

        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ting.cache_file', $config['cache_file']);
        $container->setParameter('ting.repositories', $config['repositories']);
        $container->setParameter('ting.connections', $config['connections']);
        $container->setParameter('ting.database_options', $config['databases_options']);
        
        $metadataRepository = $container->getDefinition('ting.metadatarepository');
        // Repositories declared as services keep a unit of work and a connection: reset them between requests
        $container->registerForAutoconfiguration(Repository::class)
            ->addTag('kernel.reset', ['method' => 'reset']);

        $container->registerAttributeForAutoconfiguration(Table::class, function(ChildDefinition $definition, Table $attribute, \ReflectionClass $reflector) use ($metadataRepository): void {
            $newMetadata = $this->getMetadata($reflector, $attribute);
            $metadataRepository->addMethodCall('addMetadata', [$attribute->repository, $newMetadata]);
        });
        
        if (isset($config['cache_provider']) === true) {
            $container->setAlias('ting.cache.pool', $config['cache_provider']);
        } else {
            $container->register('ting.cache.pool', NullAdapter::class);
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

        if (class_exists(ExpressionLanguage::class)) {
            $definition = new Definition(ExpressionLanguage::class);
            $definition->addArgument(new Reference('cache.app'));
            $container->setDefinition('ting.expression_language', $definition);
        }
        
        $definition = new Definition(EntityValueResolver::class);
        $definition->setArguments([
            new Reference('ting.metadatarepository'),
            new Reference('ting'),
            new Reference('ting.expression_language', ContainerInterface::NULL_ON_INVALID_REFERENCE)
        ]);

        $definition->addTag('controller.argument_value_resolver', ['priority' => 110]);

        $container->setDefinition(EntityValueResolver::class, $definition);

        $serializerFactoryDefinition = $container->getDefinition('ting.serializerfactory');
        foreach ($container->findTaggedServiceIds('ting.serializer') as $id => $tags) {
            $serializerFactoryDefinition->addMethodCall('add', [new Reference($id)]);
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
                $newField['autoincrement'] = true;
            }
            if ($mappingAttribute->getArguments()['primary'] ?? false) {
                $newField['primary'] = true;
            }

            if (is_subclass_of($property->getType()->getName(), '\Brick\Geo\Geometry')) {
                $newField['type'] = 'geometry';
            } elseif (is_subclass_of($property->getType()->getName(), Uuid::class)) {
                $newField['type'] = 'uuid';
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
                    default => (interface_exists(SerializerInterface::class) ? 'symfony_serializer' : 'string')
                };
            }
            $options = $mappingAttribute->getArguments()['serializerOptions'] ?? [];
            if ($newField['type'] === 'json' && $property->getType()->getName() === 'array') {
                // Without assoc, a JSON object is decoded to a stdClass, which an array property cannot hold
                $options = array_replace_recursive(['unserialize' => ['assoc' => true]], $options);
            }
            if ($newField['type'] === 'symfony_serializer') {
                $defaultOptions = [
                    'serialize' => ['context' => ['groups' => ['*']]],
                    'unserialize' => ['context' => ['groups' => ['*']], 'type' => $property->getType()->getName()]
                ];
                $options = array_merge_recursive($defaultOptions, $options);
                $newField['serializer'] = SymfonySerializer::class;
            }

            if ($mappingAttribute->getArguments()['serializer'] ?? false) {
                $newField['serializer'] = $mappingAttribute->getArguments()['serializer'];
            }
            if ($options !== []) {
                $newField['serializer_options'] = $options;
            }

            if (array_key_exists('mutable', $mappingAttribute->getArguments())) {
                $newField['mutable'] = (bool) $mappingAttribute->getArguments()['mutable'];
            } elseif (isset($newField['serializer']) && $this->isImmutableType($property->getType()->getName())) {
                // Ting considers the values of a custom serializer mutable: the type of the property tells better
                $newField['mutable'] = false;
            }

            $newMetadata->addMethodCall('addField', [$newField]);
        }
        return $newMetadata;
    }

    /**
     * Whether a value of this type can't be modified in place, so that its changes are always notified by a setter
     */
    private function isImmutableType(string $type): bool
    {
        if (\in_array($type, ['string', 'int', 'float', 'bool', 'array'], true) || $type === \DateTimeImmutable::class) {
            return true;
        }
        if (enum_exists($type)) {
            return true;
        }

        return class_exists($type) && (new \ReflectionClass($type))->isReadOnly();
    }
}
