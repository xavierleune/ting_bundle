Installation
============

1. Clone this repository into  vendor/ccmbenchmark/TingBundle

2. Clone Ting into vendor/ccmbenchmark/Ting

3. Add the following lines to your projects autoload.php file:
```
#!php

        $loader->addPsr4(
            'CCMBenchmark\\TingBundle\\',
            __DIR__ . '/../vendor/ccmbenchmark/TingBundle/src/TingBundle'
        );

        $loader->addPsr4(
            'CCMBenchmark\\Ting\\',
            __DIR__ . '/../vendor/ccmbenchmark/Ting/src/Ting'
        );
```

4. Load Bundle in AppKernel.php
```
#!php

        new CCMBenchmark\TingBundle\TingBundle(),
```

Configuration
=============
```
#!yaml

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
```

Utilisation
===========
From your controller call
```
#!php

    $this->container->get('ting')->get('\Acme\DemoBundle\Entity\AcmeRepository');
```
