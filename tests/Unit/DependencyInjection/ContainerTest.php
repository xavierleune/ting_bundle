<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
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

namespace CCMBenchmark\TingBundle\Tests\Unit\DependencyInjection;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\Cache\CacheInterface as TingCacheInterface;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\TingBundle\ArgumentResolver\EntityValueResolver;
use CCMBenchmark\TingBundle\DependencyInjection\TingExtension;
use CCMBenchmark\TingBundle\Serializer\SymfonySerializer;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use tests\fixtures\EntityWithAttributes;
use tests\fixtures\SimpleRepository;

/**
 * Builds the container as an application does, and instantiates every service of the bundle
 */
class ContainerTest extends TestCase
{
    private const CONNECTIONS = [
        'main' => [
            'namespace' => '\CCMBenchmark\Ting\Driver\Mysqli',
            'charset' => 'utf8mb4',
            'primary' => ['host' => 'db-primary', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
            'replicas' => [
                ['host' => 'db-replica-1', 'user' => 'app', 'password' => 'secret', 'port' => 3306],
            ],
        ],
    ];

    public static function debugProvider(): array
    {
        return ['debug' => [true], 'prod' => [false]];
    }

    #[DataProvider('debugProvider')]
    public function testEveryServiceCanBeInstantiated(bool $debug): void
    {
        $container = $this->buildContainer(['connections' => self::CONNECTIONS], $debug, function (ContainerBuilder $container) {
            // Its #[Table] attribute adds a metadata to ting.metadatarepository
            $container->register('entity_with_attributes', EntityWithAttributes::class)
                ->setAutoconfigured(true);
        });

        $instantiated = 0;
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($definition->isAbstract() || $definition->isSynthetic() || !$this->belongsToBundle($id, $definition)) {
                continue;
            }
            $this->assertIsObject($container->get($id), $id);
            $instantiated++;
        }
        $this->assertGreaterThan(20, $instantiated);
        $found = null;
        $container->get('ting.metadatarepository')->findMetadataForEntity(
            EntityWithAttributes::class,
            function (Metadata $metadata) use (&$found): void {
                $found = $metadata;
            }
        );
        $this->assertInstanceOf(Metadata::class, $found);
    }

    public function testConnectionsArePassedToTheConnectionPool(): void
    {
        $container = $this->buildContainer(['connections' => self::CONNECTIONS]);

        $this->assertSame(self::CONNECTIONS, $container->getParameter('ting.connections'));
        $this->assertInstanceOf(ConnectionPool::class, $container->get('ting.connectionpool'));
    }

    public static function renamedKeyProvider(): array
    {
        return [
            'master' => ['master', 'primary'],
            'slaves' => ['slaves', 'replicas'],
        ];
    }

    #[DataProvider('renamedKeyProvider')]
    public function testRenamedConnectionKeysAreRejected(string $oldKey, string $newKey): void
    {
        $connections = self::CONNECTIONS;
        $connections['main'][$oldKey] = $connections['main'][$newKey];
        unset($connections['main'][$newKey]);

        $this->assertThrows(
            InvalidConfigurationException::class,
            fn () => $this->buildContainer(['connections' => $connections]),
            sprintf(
                'Invalid configuration for path "ting.connections": connection "main": the "%s" key was renamed "%s" in ting_bundle 4.0 (Ting 4.0).',
                $oldKey,
                $newKey
            )
        );
    }

    public function testCacheDefaultsToANullPool(): void
    {
        $container = $this->buildContainer([]);

        $this->assertSame(NullAdapter::class, $container->findDefinition('ting.cache.pool')->getClass());
        $this->assertInstanceOf(Cache::class, $container->get(TingCacheInterface::class));
    }

    public function testCacheProviderIsASymfonyCachePool(): void
    {
        $container = $this->buildContainer(['cache_provider' => 'app.ting_pool'], false, function (ContainerBuilder $container) {
            $container->setDefinition('app.ting_pool', new Definition(ArrayAdapter::class));
        });

        // The alias to a private service is replaced by its definition when the container is compiled
        $this->assertInstanceOf(ArrayAdapter::class, $container->get('ting.cache.pool'));
        $this->assertInstanceOf(Cache::class, $container->get('ting.cache'));
    }

    public function testSymfonySerializerReceivesTheSerializer(): void
    {
        $container = $this->buildContainer([]);

        $symfonySerializer = $container->get(SymfonySerializer::class);
        $this->assertInstanceOf(Serializer::class, (new \ReflectionProperty($symfonySerializer, 'serializer'))->getValue($symfonySerializer));
    }

    public function testValueResolverIsDeclared(): void
    {
        $container = $this->buildContainer([]);

        $this->assertInstanceOf(EntityValueResolver::class, $container->get(EntityValueResolver::class));
        $this->assertSame(EntityValueResolver::class, (string) $container->getAlias('ting.entity_value_resolver'));
    }

    public function testRepositoryServicesAreResetBetweenRequests(): void
    {
        $container = $this->buildContainer([], false, function (ContainerBuilder $container) {
            $container->register('app.repository', SimpleRepository::class)
                ->setAutoconfigured(true)
                ->setPublic(true);
        });

        $this->assertSame([['method' => 'reset']], $container->getDefinition('app.repository')->getTag('kernel.reset'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function buildContainer(array $config, bool $debug = false, ?\Closure $configure = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.debug', $debug);
        $container->setParameter('kernel.cache_dir', sys_get_temp_dir());
        $container->register('file_locator', FileLocator::class)
            ->setArguments([$this->createStub(KernelInterface::class)]);
        $container->register('cache.app', ArrayAdapter::class);
        $container->register(SerializerInterface::class, Serializer::class);
        if ($configure !== null) {
            $configure($container);
        }

        (new TingExtension())->load([$config], $container);

        // Every service of the bundle is made public, to be fetched after compilation
        foreach ($container->getDefinitions() as $id => $definition) {
            if ($this->belongsToBundle($id, $definition)) {
                $definition->setPublic(true);
            }
        }
        foreach ($container->getAliases() as $id => $alias) {
            $alias->setPublic(true);
        }
        $container->compile();

        return $container;
    }

    private function belongsToBundle(string $id, Definition $definition): bool
    {
        return str_starts_with($id, 'ting') || str_starts_with($definition->getClass() ?? $id, 'CCMBenchmark\\');
    }
}
