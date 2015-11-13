Installation
============

1. Require Ting Bundle with
    ```composer require ccmbenchmark/ting_bundle```
2. Load Bundle in AppKernel.php  
    ```new CCMBenchmark\TingBundle\TingBundle(),```


Configuration
=============
```
#!yaml

    ting:
        repositories:
            Acme:
                namespace: Acme\DemoBundle\Entity
                directory: @DemoBundle\Entity

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

        memcached:
            persistent_id: acme.test
            servers:
                main:
                    host: 127.0.0.1
                    port: 11211

            options:
                - { key: Memcached::OPT_LIBKETAMA_COMPATIBLE, value: true }
                - { key: Memcached::OPT_SERIALIZER, value: Memcached::SERIALIZER_PHP }
                - { key: Memcached::OPT_PREFIX_KEY, value: acme- }
```

Usage
===========
From your controller call
```
#!php

    $this->get('ting')->get('\Acme\DemoBundle\Entity\AcmeRepository');
```