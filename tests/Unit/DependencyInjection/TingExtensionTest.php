<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2020 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

use CCMBenchmark\Ting\Serializer\BackedEnum;
use CCMBenchmark\TingBundle\DependencyInjection\TingExtension;
use CCMBenchmark\TingBundle\Serializer\SymfonySerializer;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use tests\fixtures\EntityWithAttributes;
use tests\fixtures\EntityWithoutRepository;
use tests\fixtures\EntityWithTableDefaults;
use tests\fixtures\EntityWithUnionType;
use tests\fixtures\EntityWithUntypedProperty;
use tests\fixtures\EntityWithValueObjects;
use tests\fixtures\SimpleRepository;
use tests\fixtures\Status;

class TingExtensionTest extends TestCase
{
    public function testAutoConfigurationWithAttributes(): void
    {
        $fixtureInstance = new Definition(EntityWithAttributes::class);
        $fixtureInstance->setAutowired(true);
        $fixtureInstance->setAutoconfigured(true);
        $fixtureInstance->setPublic(true);
        $fileLocator = new Definition(FileLocatorInterface::class);
        $fileLocator->setAutowired(true);
        $fileLocator->setAutoconfigured(true);
        $containerBuilder = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.cache_dir' => sys_get_temp_dir()
        ]));
        $containerBuilder->setDefinition('entity_with_attributes', $fixtureInstance);
        $containerBuilder->setDefinition('file_locator', $fileLocator);

        (new TingExtension())->load([], $containerBuilder);
        $containerBuilder->compile();
        $calls = $containerBuilder->getDefinition('ting.metadatarepository')->getMethodCalls();

        $this->assertEquals('addMetadata', $calls[0][0]);
        $this->assertSame(
            [
                ['setEntity', ['tests\\fixtures\\EntityWithAttributes']],
                ['setTable', ['entity_with_attributes']],
                ['setDatabase', ['default']],
                ['setConnectionName', ['default']],
                ['setRepository', ['default']],
                ['addField', [
                    ['fieldName' => 'id', 'columnName' => 'id', 'autoincrement' => true, 'primary' => true, 'type' => 'int']]
                ],
                ['addField', [['fieldName' => 'fieldWithSpecifiedColumnName', 'columnName' => 'field', 'type' => 'string']]],
                ['addField', [['fieldName' => 'fieldAsCamelCase', 'columnName' => 'field_as_camel_case', 'type' => 'string']]],
                ['addField', [['fieldName' => 'dateImmutable', 'columnName' => 'date_immutable', 'type' => 'datetime_immutable', 'serializer_options' => ['format' => 'Y-m-d H:i:s']]]],
                ['addField', [['fieldName' => 'dateMutable', 'columnName' => 'date_mutable', 'type' => 'datetime']]],
                ['addField', [['fieldName' => 'timeZone', 'columnName' => 'time_zone', 'type' => 'datetimezone']]],
                ['addField', [['fieldName' => 'json', 'columnName' => 'json', 'type' => 'json', 'serializer_options' => ['unserialize' => ['assoc' => true]]]]],
                ['addField', [['fieldName' => 'point', 'columnName' => 'point', 'type' => 'geometry']]],
                ['addField', [['fieldName' => 'genericUuid', 'columnName' => 'generic_uuid', 'type' => 'uuid']]],
                ['addField', [['fieldName' => 'uuidV4', 'columnName' => 'uuid_v4', 'type' => 'uuid']]],
            ],
            $calls[0][1][1]->getMethodCalls()
        );
    }

    public function testMutabilityIsDeducedFromThePropertyType(): void
    {
        $fields = $this->getFieldsOfEntity(EntityWithValueObjects::class);

        // Enums and readonly classes can't change in place: they don't need to be written on every save
        $this->assertFalse($fields['status']['mutable']);
        $this->assertFalse($fields['price']['mutable']);
        // Ting decides for the other fields (mutable by default for an object of a custom serializer)
        $this->assertArrayNotHasKey('mutable', $fields['address']);
        $this->assertArrayNotHasKey('mutable', $fields['id']);
        // A scalar handled by a custom serializer is immutable too
        $this->assertFalse($fields['ip']['mutable']);
        // The attribute wins
        $this->assertFalse($fields['checkedAt']['mutable']);
    }

    public function testBackedEnumsUseTheBackedEnumSerializerByDefault(): void
    {
        $fields = $this->getFieldsOfEntity(EntityWithValueObjects::class);

        $this->assertSame('string', $fields['status']['type']);
        $this->assertSame(BackedEnum::class, $fields['status']['serializer']);
        $this->assertSame(['unserialize' => ['enum' => Status::class]], $fields['status']['serializer_options']);
        // A serializer given by the attribute keeps its own options
        $this->assertSame(SymfonySerializer::class, $fields['customStatus']['serializer']);
        $this->assertSame(Status::class, $fields['customStatus']['serializer_options']['unserialize']['type']);
        $this->assertArrayNotHasKey('enum', $fields['customStatus']['serializer_options']['unserialize']);
    }

    public function testEnumsCanUseTheSymfonySerializer(): void
    {
        $fields = $this->getFieldsOfEntity(EntityWithValueObjects::class, ['enum_serializer' => 'symfony_serializer']);

        $this->assertSame('symfony_serializer', $fields['status']['type']);
        $this->assertSame(SymfonySerializer::class, $fields['status']['serializer']);
        $this->assertSame(Status::class, $fields['status']['serializer_options']['unserialize']['type']);
    }

    private const TABLE_DEFAULTS = [
        'default_connection' => 'main',
        'default_database' => '%env(DATABASE_NAME)%',
        'connections' => ['main' => ['namespace' => '\\CCMBenchmark\\Ting\\Driver\\Mysqli']],
    ];

    public function testTableTakesTheDefaultConnectionAndDatabase(): void
    {
        $table = $this->getTableOfEntity(EntityWithTableDefaults::class, self::TABLE_DEFAULTS);

        $this->assertSame('main', $table['setConnectionName']);
        // The placeholder of an environment variable, resolved at runtime
        $this->assertMatchesRegularExpression('/^env_\w+_DATABASE_NAME_\w+$/', $table['setDatabase']);
        $this->assertSame(SimpleRepository::class, $table['setRepository']);
    }

    public function testTableValuesWinOverTheDefaults(): void
    {
        $table = $this->getTableOfEntity(EntityWithAttributes::class, self::TABLE_DEFAULTS);

        $this->assertSame('default', $table['setConnectionName']);
        $this->assertSame('default', $table['setDatabase']);
    }

    public static function missingTableValueProvider(): array
    {
        return [
            'connection' => [['default_database' => 'app'], 'connection', 'ting.default_connection'],
            'database' => [['default_connection' => 'main', 'connections' => self::TABLE_DEFAULTS['connections']], 'database', 'ting.default_database'],
        ];
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('missingTableValueProvider')]
    public function testTableWithoutValueNeedsADefault(array $config, string $argument, string $option): void
    {
        $this->assertThrows(
            \LogicException::class,
            fn () => $this->getTableOfEntity(EntityWithTableDefaults::class, $config),
            sprintf('The #[Table] attribute of %s has no %s: give it one, or set %s.', EntityWithTableDefaults::class, $argument, $option)
        );
    }

    public function testTableNeedsARepository(): void
    {
        $this->assertThrows(
            \LogicException::class,
            fn () => $this->getTableOfEntity(EntityWithoutRepository::class),
            sprintf('The #[Table] attribute of %s has no repository.', EntityWithoutRepository::class)
        );
    }

    public function testDefaultConnectionMustBeDeclared(): void
    {
        $this->assertThrows(
            InvalidConfigurationException::class,
            fn () => $this->getTableOfEntity(EntityWithTableDefaults::class, ['default_connection' => 'other'] + self::TABLE_DEFAULTS),
            'Invalid configuration for path "ting.default_connection": the connection "other" is not declared in ting.connections.'
        );
    }

    public static function unmappableTypeProvider(): array
    {
        return [
            'union' => [EntityWithUnionType::class, 'string|int'],
            'untyped' => [EntityWithUntypedProperty::class, 'no type'],
        ];
    }

    /**
     * @param class-string $entity
     */
    #[DataProvider('unmappableTypeProvider')]
    public function testAColumnNeedsASingleType(string $entity, string $given): void
    {
        $this->assertThrows(
            \LogicException::class,
            fn () => $this->getFieldsOfEntity($entity),
            sprintf('Property %s::$reference must have a single type (nullable or not) to be mapped by #[Column], %s given.', $entity, $given)
        );
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $config
     * @return array<string, array<string, mixed>> fields given to Metadata::addField(), by property name
     */
    private function getFieldsOfEntity(string $entity, array $config = []): array
    {
        $fields = [];
        foreach ($this->getMetadataCallsOfEntity($entity, $config) as [$method, $arguments]) {
            if ($method === 'addField') {
                $fields[$arguments[0]['fieldName']] = $arguments[0];
            }
        }

        return $fields;
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $config
     * @return array<string, mixed> first argument of the setters called on the metadata, by method
     */
    private function getTableOfEntity(string $entity, array $config = []): array
    {
        $calls = $this->getMetadataCallsOfEntity($entity, $config);

        return array_column(array_map(fn (array $call) => [$call[0], $call[1][0]], $calls), 1, 0);
    }

    /**
     * @param class-string $entity
     * @param array<string, mixed> $config
     * @return list<array{string, array<mixed>}> method calls on the metadata of the entity
     */
    private function getMetadataCallsOfEntity(string $entity, array $config = []): array
    {
        // As in a kernel, %env()% parameters are resolved at runtime
        $containerBuilder = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => false,
            'kernel.cache_dir' => sys_get_temp_dir()
        ]));
        $containerBuilder->register('entity', $entity)->setAutoconfigured(true)->setPublic(true);
        $containerBuilder->register('file_locator', FileLocatorInterface::class);

        (new TingExtension())->load([$config], $containerBuilder);
        $containerBuilder->compile();

        return $containerBuilder->getDefinition('ting.metadatarepository')->getMethodCalls()[0][1][1]->getMethodCalls();
    }
}
