Installation
============

1. Require Ting Bundle with
    ```composer require ccmbenchmark/ting_bundle```
2. Load Bundles in AppKernel.php

```php
    new Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
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
                directory: "@DemoBundle/Entity"
                options:
                    #pass options to your repository
                    Acme\DemoBundle\BazRepository:
                        extra:
                            bar: hello
                            foo: world
                    Acme\DemoBundle\FooRepository:
                        extra:
                            bar: hello
                            foo: world
                    default:
                        connection: main
                        database: baz

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

Usage
===========
From your controller call
```
#!php

    $this->get('ting')->get('\Acme\DemoBundle\Entity\AcmeRepository');
```
