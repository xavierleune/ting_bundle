<?php

namespace CCMBenchmark\TingBundle\Schema;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        $autoIncrement = false,
        $primary = false,
        $column = null,
        $serializer = null
    ) {
        
    }
}