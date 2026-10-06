UPGRADE FROM 3.X to 4.0
=======================

ting_bundle 4.0 requires [Ting 4.0](https://github.com/xavierleune/ting): read
[its upgrade guide](https://github.com/xavierleune/ting/blob/main/UPGRADE-4.0.md) too, most changes of your code
(repository reads, serializers, transactions...) come from Ting.

Requirements
------------

* PHP >= 8.2, Symfony `^7.4 || ^8.0` (Symfony 8 requires PHP 8.4).
* `ccmbenchmark/ting` is replaced by `xavierleune/ting` `^4.0`, which declares `replace: ccmbenchmark/ting`.
* `doctrine/cache` is no longer required.

Connections: primary / replicas
-------------------------------

The `master` and `slaves` keys of a connection are renamed `primary` and `replicas`. The old keys are not accepted any
more: the container fails to build with an `InvalidConfigurationException`, e.g.
`connection "main": the "master" key was renamed "primary" in ting_bundle 4.0 (Ting 4.0).`

```yaml
# Before (3.x):
ting:
    connections:
        main:
            namespace: CCMBenchmark\Ting\Driver\Mysqli
            master:
                host: db-primary
                user: app
                password: secret
                port: 3306
            slaves:
                replica1:
                    host: db-replica-1
                    user: app
                    password: secret
                    port: 3306

# After (4.0):
ting:
    connections:
        main:
            namespace: CCMBenchmark\Ting\Driver\Mysqli
            primary:
                host: db-primary
                user: app
                password: secret
                port: 3306
            replicas:
                replica1:
                    host: db-replica-1
                    user: app
                    password: secret
                    port: 3306
```

In your code, `forceMaster:` becomes `forcePrimary:` (see Ting's upgrade guide). `#[MapEntity(forcePrimary: true)]`
is unchanged.

Cache
-----

Ting 4.0 replaces doctrine/cache with Symfony cache pools.

* `cache_provider` is now the id of a Symfony cache pool (any `Symfony\Contracts\Cache\CacheInterface`, for instance
  a pool declared under `framework.cache.pools`), instead of a doctrine/cache provider. Without it, a `NullAdapter`
  is used, as `VoidCache` was.
* The `Doctrine\Common\Cache\Cache` autowiring alias of `ting.cache` is removed: type your arguments with
  `CCMBenchmark\Ting\Cache\CacheInterface`.
* A TTL of `0` now means the default lifetime of the pool, and keys containing `{}()/\@:` are rejected: see the
  "Cache" section of Ting's upgrade guide.

```yaml
framework:
    cache:
        pools:
            cache.ting:
                adapter: cache.adapter.redis

ting:
    cache_provider: cache.ting
```

Backed enums
------------

A property typed with a backed enum is now stored with Ting's `BackedEnum` serializer: the value of the case
(`active`), in a `string` field. ting_bundle 3.x used the Symfony serializer, which stored its JSON encoding (`"active"`,
with the quotes, for a string-backed enum; `1` for an int-backed one). A pure enum (without backing type) still uses
the Symfony serializer.

The values stored by 3.x can't be read by the new serializer. Either convert them:

```sql
-- string-backed enums: remove the JSON quotes (int-backed values are already right)
UPDATE city SET status = TRIM(BOTH '"' FROM status);
```

or keep the 3.x storage with the `enum_serializer` option:

```yaml
ting:
    enum_serializer: symfony_serializer
```

A `serializer` given to `#[Column]` is still used as is.

UniqueEntity
------------

Passing an array of options, deprecated since 3.12, throws a `ConstraintDefinitionException`: use named arguments.

```php
// Before (3.x):
#[UniqueEntity(options: ['repository' => UserRepository::class, 'fields' => ['email']], groups: ['create'])]

// After (4.0):
#[UniqueEntity(fields: ['email'], repository: UserRepository::class, groups: ['create'])]
```

Extending the bundle
--------------------

The classes implementing Ting interfaces declare their native types, as the interfaces do in Ting 4.0: a subclass
overriding one of these methods must use compatible types.

* `Logger\DriverLogger`: `addConnection(string, string, array): void`, `startQuery(string, array, string, string): void`,
  `startPrepare(string, string, string): void`, `startStatementExecute(string, array = []): void`,
  `stopQuery(string $event = 'query'): void`, `stopPrepare(string): void`, `stopStatementExecute(string): void`
* `Logger\CacheLogger`: `startOperation(string, array|string): void`, `stopOperation(bool $miss = false): void`
* `Serializer\SymfonySerializer`: `serialize(mixed, array = []): mixed`, `unserialize(mixed, array = []): mixed`
* `Serializer\SerializerFactory`: `add(SerializerInterface): void`, `get(string): SerializerInterface`
* The `ting` service (`Repository\RepositoryFactory`) no longer receives the `SerializerFactory` (removed from
  Ting's `RepositoryFactory` constructor).
