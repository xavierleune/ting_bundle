<?php

namespace CCMBenchmark\TingBundle\Schema;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Table
{
    public function __construct(public string $name, public string $connection, public string $database, public string $repository)
    {
        
    }
}