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

use CCMBenchmark\TingBundle\DependencyInjection\TingExtension;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use Symfony\Component\Config\FileLocatorInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use tests\fixtures\EntityWithAttributes;
use tests\fixtures\EntityWithValueObjects;

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

    /**
     * @param class-string $entity
     * @return array<string, array<string, mixed>> fields given to Metadata::addField(), by property name
     */
    private function getFieldsOfEntity(string $entity): array
    {
        $containerBuilder = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.cache_dir' => sys_get_temp_dir()
        ]));
        $containerBuilder->register('entity', $entity)->setAutoconfigured(true)->setPublic(true);
        $containerBuilder->register('file_locator', FileLocatorInterface::class);

        (new TingExtension())->load([], $containerBuilder);
        $containerBuilder->compile();

        $fields = [];
        foreach ($containerBuilder->getDefinition('ting.metadatarepository')->getMethodCalls()[0][1][1]->getMethodCalls() as [$method, $arguments]) {
            if ($method === 'addField') {
                $fields[$arguments[0]['fieldName']] = $arguments[0];
            }
        }

        return $fields;
    }
}
