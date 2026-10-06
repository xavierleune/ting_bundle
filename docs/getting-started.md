# Getting started with Symfony

This guide builds a small Symfony application on top of Ting with ting_bundle, from installation to the first queries.
It uses the [world sample database](https://dev.mysql.com/doc/world-setup/en/) of MySQL.

Ting itself is documented in [its repository](https://github.com/xavierleune/ting/tree/main/docs): this guide only
covers what the bundle does for you. Without Symfony, see Ting's
[getting started](https://github.com/xavierleune/ting/blob/main/docs/getting-started.md).

> Based on the Symfony guide of [ccmbenchmark/ting_documentation](https://github.com/ccmbenchmark/ting_documentation)
> (MIT), rewritten for Symfony 7.4 / 8 and Ting 4.

## Requirements

- PHP 8.2 or later (8.4 for Symfony 8)
- Symfony 7.4 or 8
- The `mysqli` extension (or `pgsql` for PostgreSQL)

## Installation

```bash
composer require xavierleune/ting_bundle
```

With Symfony Flex, the bundle is registered in `config/bundles.php`. Otherwise, add it:

```php
// config/bundles.php
return [
    // ...
    CCMBenchmark\TingBundle\TingBundle::class => ['all' => true],
];
```

Ting replaces Doctrine: if your application was created with the `webapp` pack, you can remove `doctrine/orm` and
`doctrine/doctrine-bundle` with their configuration.

## Database

Install the [world database](https://dev.mysql.com/doc/world-setup/en/world-setup-installation.html) in MySQL. This
guide uses its `city` table:

| Column      | Type     |
|-------------|----------|
| ID          | int(11)  |
| Name        | char(35) |
| CountryCode | char(3)  |
| District    | char(20) |
| Population  | int(11)  |

## Configuration

Declare the connections in `config/packages/ting.yaml`. A connection has a `primary` server and optional `replicas`:
reads go to a replica when one is configured, writes always go to the primary.

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
            # replicas:
            #     replica1:
            #         host: '%env(DATABASE_REPLICA_HOST)%'
            #         user: '%env(DATABASE_USER)%'
            #         password: '%env(DATABASE_PASSWORD)%'
            #         port: 3306

    databases_options:
        world:
            timezone: 'Europe/Paris'

    # Optional: a Symfony cache pool for cached queries (a NullAdapter by default)
    # cache_provider: cache.ting
```

`port` is required for the primary and every replica.

## Creating the entity

An entity is a plain PHP object. The `#[Table]` attribute links it to its table, connection, database and repository;
each mapped property gets a `#[Column]` attribute, and its type is deduced from the type of the property (see
[the README](../README.md#declare-metadata-with-attributes)).

Ting writes the properties whose changes are notified: implement `NotifyPropertyInterface` with the `NotifyProperty`
trait, and call `propertyChanged()` in every setter.

```php
<?php
// src/Entity/City.php

namespace App\Entity;

use App\Repository\CityRepository;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;

#[Table(name: 'city', connection: 'main', database: 'world', repository: CityRepository::class)]
class City implements NotifyPropertyInterface
{
    use NotifyProperty;

    #[Column(column: 'ID', autoIncrement: true, primary: true)]
    private ?int $id = null;

    #[Column(column: 'Name')]
    private string $name = '';

    #[Column(column: 'CountryCode')]
    private string $countryCode = '';

    #[Column(column: 'Population')]
    private int $population = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->propertyChanged('id', $this->id, $id);
        $this->id = $id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->propertyChanged('name', $this->name, $name);
        $this->name = $name;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): void
    {
        $this->propertyChanged('countryCode', $this->countryCode, $countryCode);
        $this->countryCode = $countryCode;
    }

    public function getPopulation(): int
    {
        return $this->population;
    }

    public function setPopulation(int $population): void
    {
        $this->propertyChanged('population', $this->population, $population);
        $this->population = $population;
    }
}
```

Without `column`, the column name is the property name in snake case (`countryCode` → `country_code`). With PHP 8.4,
public properties with `set` hooks can replace the setters, see [the README](../README.md#about-public-properties).

> **The entities must be loaded as services.** The bundle reads `#[Table]` through Symfony's autoconfiguration, which
> only sees the classes loaded by `config/services.yaml`. Check that `src/Entity/` is not listed in its `exclude`
> (older skeletons excluded it, for Doctrine):
>
> ```yaml
> # config/services.yaml
> services:
>     _defaults:
>         autowire: true
>         autoconfigure: true
>
>     App\:
>         resource: '../src/'
> ```
>
> The entity services are never instantiated: unused, they are removed from the compiled container.

## Creating the repository

A repository extends `CCMBenchmark\Ting\Repository\Repository`; add your own query methods to it.

```php
<?php
// src/Repository/CityRepository.php

namespace App\Repository;

use App\Entity\City;
use CCMBenchmark\Ting\Repository\Repository;

/**
 * @extends Repository<City>
 */
class CityRepository extends Repository
{
}
```

Repositories declaring their metadata in PHP (`MetadataInitializer`) are loaded with the `repositories` option instead,
see [the README](../README.md#main-configuration).

## Using the repository

Inject the repository in your services and controllers: the bundle wires it with the Ting services (connection pool,
unit of work, cache of Ting), and resets it between requests in worker mode.

```php
<?php
// src/Controller/CityController.php

namespace App\Controller;

use App\Entity\City;
use App\Repository\CityRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class CityController
{
    public function __construct(private readonly CityRepository $cityRepository)
    {
    }

    #[Route('/cities/{countryCode}')]
    public function list(string $countryCode): JsonResponse
    {
        // Criteria and order use property names
        $cities = $this->cityRepository->getBy(['countryCode' => $countryCode], order: ['population' => 'DESC'], limit: 10);

        $names = [];
        foreach ($cities as $city) {
            $names[] = $city->getName();
        }

        return new JsonResponse($names);
    }

    #[Route('/cities', methods: ['POST'])]
    public function create(): JsonResponse
    {
        $city = new City();
        $city->setName('Ting City');
        $city->setCountryCode('FRA');
        $city->setPopulation(42);
        $this->cityRepository->save($city);

        return new JsonResponse(['id' => $city->getId()]); // filled with the auto-increment value
    }
}
```

The repository API:

```php
$city = $cityRepository->get(3);                        // by primary key, or null
$paris = $cityRepository->getOneBy(['name' => 'Paris']); // first match, or null
$cities = $cityRepository->getBy(['countryCode' => 'FRA']);
$all = $cityRepository->getAll();

$paris->setPopulation(2_200_000);
$cityRepository->save($paris);   // only writes the properties that changed
$cityRepository->delete($paris);
```

You can also get a repository from the `CCMBenchmark\Ting\Repository\RepositoryFactory` service (`ting`):
`$repositoryFactory->get(CityRepository::class)`.

See Ting's documentation for [repositories](https://github.com/xavierleune/ting/blob/main/docs/repositories.md)
(criteria, custom queries, transactions) and [entities](https://github.com/xavierleune/ting/blob/main/docs/entities.md)
(change tracking).

## Going further

The bundle also provides, described in [the README](../README.md):

- a [value resolver](../README.md#using-ting-as-a-value-resolver) injecting entities in controller arguments from the
  route parameters, configurable with `#[MapEntity]`;
- a [user provider](../README.md#using-ting-as-a-user-provider) for the security component;
- a [`UniqueEntity` constraint](../README.md#declare-a-unique-constraint-in-a-table) for the validator;
- the queries and cache operations of each request in the Symfony profiler (in debug mode);
- the reset of the connection pool, unit of work and repositories between requests, for worker mode (FrankenPHP,
  RoadRunner...).
