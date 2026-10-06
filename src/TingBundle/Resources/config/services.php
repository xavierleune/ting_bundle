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

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\Cache\CacheInterface;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\HydratorSingleObject;
use CCMBenchmark\Ting\Serializer;
use CCMBenchmark\Ting\UnitOfWork;
use CCMBenchmark\TingBundle\ArgumentResolver\EntityValueResolver;
use CCMBenchmark\TingBundle\Cache\MetadataWarmer;
use CCMBenchmark\TingBundle\DataCollector\TingCacheDataCollector;
use CCMBenchmark\TingBundle\DataCollector\TingDriverDataCollector;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use CCMBenchmark\TingBundle\Security\EntityUserProvider;
use CCMBenchmark\TingBundle\Serializer\SerializerFactory;
use CCMBenchmark\TingBundle\Serializer\SymfonySerializer;
use CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator;
use Symfony\Component\Serializer\SerializerInterface as SymfonySerializerInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // Auto tag all instances of SerializerInterface with "ting.serializer"
    $services->instanceof(Serializer\SerializerInterface::class)
        ->autowire()
        ->tag('ting.serializer');

    $services->set('ting', RepositoryFactory::class)
        ->public()
        ->args([
            service('ting.connectionpool'),
            service('ting.metadatarepository'),
            service('ting.queryfactory'),
            service('ting.collectionfactory'),
            service('ting.unitofwork'),
            service('ting.cache'),
        ])
        ->call('loadMetadata', [
            param('kernel.cache_dir'),
            param('ting.cache_file'),
            param('ting.repositories'),
            service('file_locator'),
            service('ting.configuration_resolver')->nullOnInvalid(),
        ]);

    $services->set('ting.metadatarepository', MetadataRepository::class)
        ->public()
        ->args([
            service('ting.serializerfactory'),
            service('ting.cache.property_access'),
        ]);
    $services->alias(MetadataRepository::class, 'ting.metadatarepository');

    $services->set('ting.serializerfactory', SerializerFactory::class)
        ->public();
    $services->alias(Serializer\SerializerFactoryInterface::class, 'ting.serializerfactory');

    $services->set('ting.queryfactory', QueryFactory::class)
        ->public();
    $services->alias(QueryFactory::class, 'ting.queryfactory');

    $services->set('ting.driverlogger')
        ->synthetic()
        ->public()
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('ting.connectionpool', ConnectionPool::class)
        ->public()
        ->call('setConfig', [param('ting.connections')])
        ->call('setDatabaseOptions', [param('ting.database_options')])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(ConnectionPool::class, 'ting.connectionpool');

    $services->set('ting.unitofwork', UnitOfWork::class)
        ->public()
        ->args([
            service('ting.connectionpool'),
            service('ting.metadatarepository'),
            service('ting.queryfactory'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);
    $services->alias(UnitOfWork::class, 'ting.unitofwork');

    $services->set('ting.hydrator', Hydrator::class)
        ->share(false)
        ->public()
        ->call('setMetadataRepository', [service('ting.metadatarepository')])
        ->call('setUnitOfWork', [service('ting.unitofwork')]);

    $services->set('ting.hydrator_single_object', HydratorSingleObject::class)
        ->share(false)
        ->public()
        ->call('setMetadataRepository', [service('ting.metadatarepository')])
        ->call('setUnitOfWork', [service('ting.unitofwork')]);

    $services->set('ting.collectionfactory', CollectionFactory::class)
        ->public()
        ->args([
            service('ting.metadatarepository'),
            service('ting.unitofwork'),
            service('ting.hydrator'),
        ]);
    $services->alias(CollectionFactory::class, 'ting.collectionfactory');

    // ting.cache.pool (a Symfony cache pool) is defined by TingExtension from the cache_provider option
    $services->set('ting.cache', Cache::class)
        ->public()
        ->call('setCache', [service('ting.cache.pool')]);
    $services->alias(CacheInterface::class, 'ting.cache');

    $services->set('ting.driver_data_collector', TingDriverDataCollector::class)
        ->public()
        ->tag('data_collector', ['template' => '@Ting/Collector/driverCollector.html.twig', 'id' => 'ting.driver'])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('ting.cache_data_collector', TingCacheDataCollector::class)
        ->public()
        ->tag('data_collector', ['template' => '@Ting/Collector/cacheCollector.html.twig', 'id' => 'ting.cache'])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set('ting.metadata_warmer', MetadataWarmer::class)
        ->public()
        ->args([
            service('ting.metadatarepository'),
            service('file_locator'),
            param('ting.repositories'),
            param('ting.cache_file'),
        ])
        ->tag('kernel.cache_warmer', ['priority' => 0]);

    $services->set('ting.validator.unique.entity', UniqueEntityValidator::class)
        ->public()
        ->args([service('ting')])
        ->tag('validator.constraint_validator');

    $services->set('ting.security.user_provider', EntityUserProvider::class)
        ->abstract()
        ->args([
            service('ting.metadatarepository'),
            service('ting'),
        ]);

    // The resolver itself is defined by TingExtension under its class name
    $services->alias('ting.entity_value_resolver', EntityValueResolver::class);

    $services->set(Serializer\Json::class)->tag('ting.serializer');
    $services->set(Serializer\BackedEnum::class)->tag('ting.serializer');
    $services->set(Serializer\DateTime::class)->tag('ting.serializer');
    $services->set(Serializer\DateTimeImmutable::class)->tag('ting.serializer');
    $services->set(Serializer\DateTimeZone::class)->tag('ting.serializer');
    $services->set(Serializer\Geometry::class)->tag('ting.serializer');
    $services->set(Serializer\Uuid::class)->tag('ting.serializer');
    $services->set(SymfonySerializer::class)
        ->args([service(SymfonySerializerInterface::class)->nullOnInvalid()])
        ->tag('ting.serializer');
};
