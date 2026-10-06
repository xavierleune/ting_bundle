<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2024 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\TingBundle\Schema;

/**
 * Maps a property to a column. All arguments are read by name.
 *
 * $mutable: whether the value can be modified in place (Ting then compares its database value on save, see Ting's
 * change tracking).
 * By default, Ting decides from the serializer, and the bundle marks as immutable the scalars, arrays, enums,
 * \DateTimeImmutable and readonly classes handled by a custom serializer.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        $autoIncrement = false,
        $primary = false,
        $column = null,
        $serializer = null,
        $serializerOptions = [],
        ?bool $mutable = null,
    ) {
        
    }
}