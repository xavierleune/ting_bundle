<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2020 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace tests\units\CCMBenchmark\TingBundle\DependencyInjection;

use Symfony\Component\Config\FileLocatorInterface;
use tests\fixtures\EntityWithAttributes;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class TingExtension extends \atoum
{
    public function testEmpty()
    {
        // Minimum test to ensure code execution
        $this
            ->if($containerBuilder = new ContainerBuilder(new ParameterBag(['kernel.debug' => false])))
            ->then($this->newTestedInstance->load([], $containerBuilder))
        ;
    }
    
    public function testAutoConfigurationWithAttributes()
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
        if (!method_exists($containerBuilder, 'registerAttributeForAutoconfiguration')) {
            // Method is only sf 5.4+
            return;
        }
        $this
            ->if($containerBuilder->setDefinition('entity_with_attributes', $fixtureInstance))
            ->and($containerBuilder->setDefinition('file_locator', $fileLocator))
            ->then($this->newTestedInstance->load([], $containerBuilder))
            ->and($containerBuilder->compile())
            ->and($calls = $containerBuilder->getDefinition('ting.metadatarepository')->getMethodCalls())
            ->string($calls[0][0])
                ->isEqualTo('addMetadata')
            ->array($calls[0][1][1]->getMethodCalls())
                ->isIdenticalTo([
                    ['setEntity', ['tests\\fixtures\\EntityWithAttributes']],
                    ['setTable',['entity_with_attributes']],
                    ['setDatabase', ['default']],
                    ['setConnectionName', ['default']],
                    ['setRepository', ['default']],
                    ['addField', [
                        ['fieldName' => 'id', 'columnName' => 'id', 'autoIncrement' => true, 'primary' => true, 'type' => 'int']]
                    ],
                    ['addField', [['fieldName' => 'fieldWithSpecifiedColumnName', 'columnName' => 'field', 'type' => 'string']]],
                    ['addField', [['fieldName' => 'fieldAsCamelCase', 'columnName' => 'field_as_camel_case', 'type' => 'string']]],
                    ['addField', [['fieldName' => 'dateImmutable', 'columnName' => 'date_immutable', 'type' => 'datetime_immutable']]],
                    ['addField', [['fieldName' => 'dateMutable', 'columnName' => 'date_mutable', 'type' => 'datetime']]],
                    ['addField', [['fieldName' => 'timeZone', 'columnName' => 'time_zone', 'type' => 'datetimezone']]],
                    ['addField', [['fieldName' => 'json', 'columnName' => 'json', 'type' => 'json']]]
                ])
        ;
    }
}
