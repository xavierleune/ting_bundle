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

use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;

#[Table('entity_with_value_objects', 'default', 'default', 'default')]
class EntityWithValueObjects
{
    #[Column(primary: true)]
    public int $id;

    #[Column]
    public Status $status;

    #[Column]
    public Money $price;

    #[Column]
    public Address $address;

    #[Column(mutable: false)]
    public \DateTime $checkedAt;

    #[Column(serializer: \CCMBenchmark\Ting\Serializer\Ip::class)]
    public string $ip;

    #[Column(serializer: \CCMBenchmark\TingBundle\Serializer\SymfonySerializer::class, serializerOptions: ['unserialize' => ['type' => Status::class]])]
    public Status $customStatus;
}
