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

use CCMBenchmark\Ting\Serializer\SerializerInterface;

class SymfonySerializer implements SerializerInterface
{
    public function __construct(private readonly ?\Symfony\Component\Serializer\SerializerInterface $serializer = null) {}

    public function serialize($toSerialize, array $options = [])
    {
        $this->throwOnNullSerializer();
        return $this->serializer->serialize($toSerialize, 'json', $options['context'] ?? []);
    }

    public function unserialize($serialized, array $options = [])
    {
        if ($serialized === null) {
            return null;
        }
        $this->throwOnNullSerializer();
        if (!isset($options['type'])) {
            throw new \RuntimeException('SymfonySerializer requires type option to be set');
        }
        return $this->serializer->deserialize($serialized, $options['type'], 'json', $options['context'] ?? []);
    }

    private function throwOnNullSerializer()
    {
        if ($this->serializer === null) {
            throw new \RuntimeException('SymfonySerializer requires symfony/serializer to be installed. Use composer require symfony/serializer to add it.');
        }
    }
}