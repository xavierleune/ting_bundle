<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2024 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

use Brick\Geo\Point;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

#[Table('entity_with_attributes', 'default', 'default', 'default')]
class EntityWithAttributes
{
    #[Column(autoIncrement: true, primary: true)]
    public int $id;
    
    #[Column(column: 'field')]
    public string $fieldWithSpecifiedColumnName;
    
    #[Column]
    public string $fieldAsCamelCase;
    
    #[Column(serializerOptions: ['format' => 'Y-m-d H:i:s'])]
    public \DateTimeImmutable $dateImmutable;
    
    #[Column]
    public \DateTime $dateMutable;
    
    #[Column]
    public \DateTimeZone $timeZone;
    
    #[Column]
    public array $json;
    
    #[Column]
    public Point $point;
    
    #[Column]
    public Uuid $genericUuid;
    
    #[Column]
    public UuidV4 $uuidV4;
}