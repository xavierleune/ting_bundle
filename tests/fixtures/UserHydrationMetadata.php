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


namespace tests\fixtures;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\MetadataInitializer;
use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;

/**
 * Metadata used for hydration only: no repository
 */
class UserHydrationMetadata implements MetadataInitializer
{
    public static function initMetadata(SerializerFactoryInterface $serializerFactory, array $options = []): Metadata
    {
        $metadata = new Metadata($serializerFactory);
        $metadata->setEntity(User::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('app');
        $metadata->setTable('usr_user');
        $metadata->addField(['fieldName' => 'id', 'columnName' => 'usr_id', 'type' => 'int', 'primary' => true]);
        $metadata->addField(['fieldName' => 'email', 'columnName' => 'usr_email', 'type' => 'string']);

        return $metadata;
    }
}
