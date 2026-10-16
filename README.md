# ting_bundle

[![Tests](https://github.com/xavierleune/ting_bundle/actions/workflows/tests.yml/badge.svg)](https://github.com/xavierleune/ting_bundle/actions/workflows/tests.yml)
[![Packagist](https://img.shields.io/packagist/v/xavierleune/ting_bundle?include_prereleases)](https://packagist.org/packages/xavierleune/ting_bundle)
[![PHP](https://img.shields.io/packagist/dependency-v/xavierleune/ting_bundle/php)](composer.json)
[![License](https://img.shields.io/packagist/l/xavierleune/ting_bundle)](LICENSE)

**[Ting](https://github.com/xavierleune/ting) for Symfony.** Map your entities with PHP attributes, inject their
repositories, get entities straight from your routes, and see every query in the profiler.

```php
#[Route('/cities/{id:city}/archive', methods: ['POST'])]
public function archive(City $city, CityRepository $cityRepository): Response
{
    $city->status = CityStatus::Archived;
    $cityRepository->save($city); // UPDATE city SET status = 'archived' WHERE id = ...

    return new Response(status: 204);
}
```

## Why ting_bundle

* **Entities mapped with attributes.** `#[Table]` and `#[Column]` on your own classes: the type of each column comes
  from the type of its property, enums, dates, UUIDs, JSON, geometries and value objects included.
* **Repositories as services.** Autowire them anywhere. They are reset between requests, ready for worker mode
  (FrankenPHP, RoadRunner).
* **Entities in your controllers.** Route parameters are resolved to entities; `#[MapEntity]` covers criteria and
  expressions.
* **Security and validation.** A `ting` user provider, and a `UniqueEntity` constraint for the validator.
* **Ting in the profiler.** Every query and cache operation of the request, with its duration, in the web profiler.
* **Symfony cache.** Ting's query cache uses any Symfony cache pool, and the metadata is warmed up with the container.

ting_bundle needs PHP 8.2 or later, Symfony 7.4 or 8 (Symfony 8 needs PHP 8.4) and
[Ting 4](https://github.com/xavierleune/ting).

## Installation

```bash
composer require xavierleune/ting_bundle
```

Without Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    CCMBenchmark\TingBundle\TingBundle::class => ['all' => true],
];
```

New to Ting? The [getting started guide](docs/getting-started.md) builds a small application, from the configuration
to the first queries.

## Quick tour

### Configure

```yaml
# config/packages/ting.yaml
ting:
    connections:
        main:
            namespace: CCMBenchmark\Ting\Driver\Mysqli
            charset: utf8mb4
            primary:
                host: '%env(DATABASE_HOST)%'
                user: '%env(DATABASE_USER)%'
                password: '%env(DATABASE_PASSWORD)%'
                port: 3306

    # Used by the entities that do not name their connection or database
    default_connection: main
    default_database: app
```

### Map an entity

An entity is a class of your own. `#[Table]` links it to its table and repository; each mapped property gets a
`#[Column]`, and its type comes from the type of the property. Ting writes the properties whose changes are notified
with `propertyChanged()`, here in a PHP 8.4 property hook (a setter works too).

```php
<?php

namespace App\Entity;

use App\Repository\CityRepository;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;

#[Table(name: 'city', repository: CityRepository::class)]
class City implements NotifyPropertyInterface
{
    use NotifyProperty;

    #[Column(autoIncrement: true, primary: true)]
    public ?int $id = null;

    #[Column]
    public string $name = '' {
        set(string $name) {
            $this->propertyChanged('name', $this->name, $name);
            $this->name = $name;
        }
    }

    #[Column] // a backed enum: the value of the case is stored
    public CityStatus $status = CityStatus::Active {
        set(CityStatus $status) {
            $this->propertyChanged('status', $this->status, $status);
            $this->status = $status;
        }
    }
}
```

```php
<?php

namespace App\Entity;

enum CityStatus: string
{
    case Active = 'active';
    case Archived = 'archived';
}
```

```php
<?php

namespace App\Repository;

use App\Entity\City;
use CCMBenchmark\Ting\Repository\Repository;

/** @extends Repository<City> */
class CityRepository extends Repository
{
}
```

The bundle reads `#[Table]` through Symfony's autoconfiguration: `src/Entity/` must not be excluded from the services
of `config/services.yaml` (see the [guide](docs/getting-started.md#creating-the-entity)).

### Use it in your controllers

Inject the repository, and let the route give you the entity:

```php
<?php

namespace App\Controller;

use App\Entity\City;
use App\Entity\CityStatus;
use App\Repository\CityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CityController
{
    public function __construct(private readonly CityRepository $cityRepository)
    {
    }

    #[Route('/cities')]
    public function list(): JsonResponse
    {
        $cities = $this->cityRepository->getBy(['status' => CityStatus::Active], order: ['name' => 'ASC'], limit: 20);

        return new JsonResponse(array_map(fn (City $city) => $city->name, $cities->toArray()));
    }

    #[Route('/cities/{id:city}')]
    public function show(City $city): JsonResponse // 404 when no city has this id
    {
        return new JsonResponse(['id' => $city->id, 'name' => $city->name]);
    }
}
```

Reading, writing, transactions and your own SQL are Ting's: see its
[documentation](https://github.com/xavierleune/ting/blob/main/docs/README.md).

### Validate and authenticate

```php
#[Table(name: 'users', repository: UserRepository::class)]
#[UniqueEntity(fields: ['email'], repository: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface, NotifyPropertyInterface
{
    // ...
}
```

```yaml
# config/packages/security.yaml
security:
    providers:
        app_user_provider:
            ting:
                class: App\Entity\User
                property: email
```

## Reference

### Configuration

```yaml
ting:
    connections:
        main:
            namespace: CCMBenchmark\Ting\Driver\Mysqli # or CCMBenchmark\Ting\Driver\Pgsql
            charset: utf8mb4
            primary:
                host: '%env(DATABASE_HOST)%'
                user: '%env(DATABASE_USER)%'
                password: '%env(DATABASE_PASSWORD)%'
                port: 3306
            # Optional: reads go to a replica, writes to the primary
            replicas:
                replica1:
                    host: '%env(DATABASE_REPLICA_HOST)%'
                    user: '%env(DATABASE_USER)%'
                    password: '%env(DATABASE_PASSWORD)%'
                    port: 3306

    databases_options:
        app:
            timezone: 'Europe/Paris'

    # Optional: connection and database of the #[Table] attributes that do not give them
    default_connection: main
    default_database: app

    # Optional: id of a Symfony cache pool, used by cached queries (a NullAdapter by default)
    cache_provider: cache.ting

    # Optional: how properties typed with a backed enum are stored
    # backed_enum (default): the value of the case; symfony_serializer: its JSON encoding, as ting_bundle < 4.0
    enum_serializer: backed_enum
```

`port` is required for the primary and every replica. The cache pool is any Symfony cache pool:

```yaml
framework:
    cache:
        pools:
            cache.ting:
                adapter: cache.adapter.redis
```

Repositories declaring their metadata in PHP (Ting's `MetadataInitializer`) instead of attributes are loaded with
`repositories`:

```yaml
ting:
    repositories:
        app:
            namespace: App\Repository
            directory: '%kernel.project_dir%/src/Repository'
            glob: '*Repository.php' # default
            options:
                # Options of every repository: connection, database and extra (passed to initMetadata())
                default:
                    connection: main
                    database: app
                # Options of one repository
                App\Repository\CityRepository:
                    extra:
                        foo: bar
```

### `#[Table]`

`CCMBenchmark\TingBundle\Schema\Table`, on the entity class:

| Argument     | Description                                                          |
|--------------|----------------------------------------------------------------------|
| `name`       | The table                                                            |
| `repository` | The repository class (required)                                      |
| `connection` | The connection, `default_connection` of the configuration if omitted |
| `database`   | The database, `default_database` of the configuration if omitted     |

Building the container fails with a `LogicException` naming the entity when the connection or the database is neither
given nor configured, or when the repository is missing.

### `#[Column]`

`CCMBenchmark\TingBundle\Schema\Column`, on every property mapped to a column. Its arguments, all optional:

| Argument            | Description                                                                              |
|---------------------|------------------------------------------------------------------------------------------|
| `column`            | The column, the property name in snake case by default (`countryCode` → `country_code`) |
| `primary`           | Part of the primary key                                                                  |
| `autoIncrement`     | Generated by the database, set on the entity after an insert                             |
| `serializer`        | A serializer class, instead of the one deduced from the type                             |
| `serializerOptions` | Options of the serializer                                                                |
| `mutable`           | See [Change tracking](#change-tracking-mutable)                                          |

The type of the column comes from the type of the property, which must be a single type (nullable or not):

| Property type                                | Ting type / serializer                                                                       |
|----------------------------------------------|----------------------------------------------------------------------------------------------|
| `string`, `int`, `float`, `bool`             | `string`, `int`, `double`, `bool`                                                            |
| `array`                                      | `json`, decoded as an associative array                                                      |
| `\DateTimeImmutable` / `\DateTime`           | `datetime_immutable` / `datetime` (`Y-m-d H:i:s`)                                            |
| `\DateTimeZone`                              | `datetimezone`                                                                               |
| `Symfony\Component\Uid\Uuid` and subclasses  | `uuid`                                                                                       |
| `Brick\Geo\Geometry` subclasses              | `geometry`                                                                                   |
| backed enum                                  | `string`, with Ting's `BackedEnum` serializer: the value of the case (see `enum_serializer`) |
| any other class (pure enum, value object...) | `symfony_serializer` (JSON), when symfony/serializer is installed                            |

#### Change tracking: `mutable`

A *mutable* value can be modified in place without a setter (`$city->updatedAt->modify('+1 day')`): Ting compares it
on save instead of relying on `propertyChanged()`. Ting decides from the serializer which values are mutable; the
bundle refines it from the type of the property:

- the values of a custom serializer (including `symfony_serializer`) are immutable when the property type is a scalar,
  an array, an enum, a `\DateTimeImmutable` or a `readonly` class;
- `#[Column(mutable: true|false)]` overrides both.

Prefer `\DateTimeImmutable` dates and `readonly` value objects. See
[Tracking changes](https://github.com/xavierleune/ting/blob/main/docs/entities.md#tracking-changes) in Ting's
documentation.

#### Setters and uninitialized properties

Before PHP 8.4, use private properties, or public ones with a setter calling `propertyChanged()`:

```php
public string $name;

public function setName(string $name): void
{
    $this->propertyChanged('name', $this->name ?? null, $name);
    $this->name = $name;
}
```

Ting bypasses property hooks when it hydrates an entity. An uninitialized typed property is ignored when the entity is
saved: the column needs a default value in the database.

### Value resolver and `#[MapEntity]`

An argument typed with an entity is resolved from the route:

* `{id:city}`: the `City` whose `id` property matches the parameter, injected as `$city`;
* `{id}`: a parameter named `id` is the primary key;
* otherwise, the route parameters named after properties of the entity are the criteria.

The resolver answers 404 when no entity matches, unless the argument is nullable. `#[MapEntity]`
(`CCMBenchmark\TingBundle\Attribute\MapEntity`) configures it:

| Argument       | Description                                                                                                |
|----------------|------------------------------------------------------------------------------------------------------------|
| `id`           | The route parameter(s) holding the primary key                                                             |
| `mapping`      | Route parameter → property, used as criteria                                                               |
| `exclude`      | Route parameters not used as criteria                                                                      |
| `stripNull`    | Ignore the parameters that are null                                                                        |
| `expr`         | An [expression](https://symfony.com/doc/current/components/expression_language.html) returning the entity |
| `forcePrimary` | Read from the primary server instead of a replica                                                          |
| `class`        | The entity class, when the argument type is not enough                                                     |
| `message`      | The message of the 404                                                                                     |
| `disabled`     | Do not resolve this argument                                                                               |

The expression receives `repository` (the repository of the entity), `request`, and every request attribute as a
variable: the route parameters are available by their name (by their mapped name for `{parameter:name}`), whether or
not the action has an argument for them.

```php
#[Route('/users/{firstname}/{lastname}')]
public function show(#[MapEntity(expr: 'repository.getOneBy({"firstname": firstname, "lastname": lastname})')] User $user): JsonResponse
```

### User provider

The `ting` user provider loads users with their repository: by the `property` given in its configuration, or, without
it, by the `loadUserByIdentifier()` method of the repository, which then implements
`CCMBenchmark\TingBundle\Security\UserLoaderInterface`.

The user class implements Symfony's `UserInterface` (and `PasswordAuthenticatedUserInterface` for password
authenticated users). It also implements `__serialize()`: the user is serialized in the session, keep only what
identifies it (and the password hash), not other sensitive data. While supporting Symfony 7.4, keep an empty
`eraseCredentials()` marked `#[\Deprecated]`, as Symfony recommends.

### `UniqueEntity`

`CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity`, on the entity class, checks that no other row has the
same values:

| Argument            | Description                                                                                  |
|---------------------|----------------------------------------------------------------------------------------------|
| `fields`            | The property, or properties, that must be unique together                                    |
| `repository`        | The repository class of the entity                                                           |
| `identityFields`    | The properties identifying the entity, so that it does not conflict with itself when updated |
| `message`           | The violation message                                                                        |
| `groups`, `payload` | As for any constraint                                                                        |

Passing an array of options (`options: [...]`) was deprecated in 3.12 and is no longer supported since 4.0.

### Profiler and worker mode

In debug mode, the web profiler gets a Ting panel for the queries (with their parameters and duration) and one for the
cache operations. The connection pool, the unit of work and the repositories are reset between requests, so that
worker mode (FrankenPHP, RoadRunner) does not leak state from one request to the next.

## Documentation

* [Getting started with Symfony](docs/getting-started.md)
* [Ting's documentation](https://github.com/xavierleune/ting/blob/main/docs/README.md): entities, repositories,
  queries, hydrators, unit of work, cache
* [Changelog](CHANGELOG)
* [Upgrading from 3.x](UPGRADE-4.0.md)
* [Issues](https://github.com/xavierleune/ting_bundle/issues)

## Coming from ccmbenchmark/ting_bundle

ting_bundle was created at [CCM Benchmark Group](https://github.com/ccmbenchmark/ting_bundle), where it is no longer
maintained; development continues here as `xavierleune/ting_bundle`. The PHP namespaces (`CCMBenchmark\TingBundle\...`)
are unchanged: change the package name in your `composer.json` and follow [UPGRADE-4.0.md](UPGRADE-4.0.md). With
Symfony Flex, check `config/bundles.php` afterwards: see
[Switching from ccmbenchmark/ting_bundle](UPGRADE-4.0.md#switching-from-ccmbenchmarkting_bundle).

## License

Apache License 2.0, see [LICENSE](LICENSE) and [NOTICE](NOTICE).
