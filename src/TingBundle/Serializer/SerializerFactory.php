<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2025 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\TingBundle\Serializer;

use CCMBenchmark\Ting\Serializer\SerializerFactoryInterface;
use CCMBenchmark\Ting\Serializer\SerializerInterface;

class SerializerFactory implements SerializerFactoryInterface
{
    private array $serializers = [];

    public function add(SerializerInterface $serializer): void
    {
        $this->serializers[get_class($serializer)] = $serializer;
    }

    public function get(string $serializerName): SerializerInterface
    {
        if (!isset($this->serializers[$serializerName])) {
            // Support old definition, try to magically instanciate.
            if (class_exists($serializerName) && $this->serializers[$serializerName] = new $serializerName()) {
                return $this->serializers[$serializerName];
            }
            throw new \Exception("Serializer $serializerName not found");
        }
        return $this->serializers[$serializerName];
    }
}