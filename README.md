Installation
============

1. Clone Ting into vendor/ccmbenchmark/Ting

2. Add the following lines to your projects autoload.php file:

        $loader->addPsr4(
            'CCMBenchmark\\TingBundle\\',
            __DIR__ . '/../vendor/ccmbenchmark/TingBundle/src/TingBundle'
        );

        $loader->addPsr4(
            'CCMBenchmark\\Ting\\',
            __DIR__ . '/../vendor/ccmbenchmark/Ting/src/Ting'
        );

3. Load Bundle in AppKernel

        new CCMBenchmark\TingBundle\TingBundle(),


Configuration
=============

    ting:
        repositories:
            Acme:
                namespace: Acme\DemoBundle\Entity
                directory: %kernel.root_dir%/../src/Acme/DemoBundle/Entity/*Repository.php

        connections:
            main:
                namespace: CCMBenchmark\Ting\Driver\Mysqli
                master:
                    host:     localhost
                    user:     world_sample
                    password: world_sample
                    port:     3306
                slaves:
                    slave1:
                        host:     127.0.0.1
                        user:     world_sample_ro
                        password: world_sample_ro
                        port:     3306
                    slave2:
                        host:     127.0.1.1
                        user:     world_sample_ro
                        password: world_sample_ro
                        port:     3306