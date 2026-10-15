> **ℹ️ This is a maintained fork of [ccmbenchmark/ting_bundle](https://github.com/ccmbenchmark/ting_bundle).**
> The upstream project is no longer maintained. This fork keeps the full history and tags of the original repository,
> and is published as `xavierleune/ting_bundle`. PHP namespaces (`CCMBenchmark\TingBundle`) are unchanged, so switching
> only requires replacing the package name in your `composer.json`.

## Installation

Requires PHP 8.2+, Symfony 7.4 or 8 (Symfony 8 requires PHP 8.4) and [Ting 4](https://github.com/xavierleune/ting).
Upgrading from 3.x: see [UPGRADE-4.0.md](UPGRADE-4.0.md).

1. Require Ting Bundle with
    ```composer require xavierleune/ting_bundle```
2. Without Symfony Flex, register the bundle in `config/bundles.php`:

```php
    CCMBenchmark\TingBundle\TingBundle::class => ['all' => true],
```

New to Ting? Follow the [getting started guide](docs/getting-started.md).

## Table of contents
- [Configuration](#configuration)
    - [About public properties](#about-public-properties)
    - [Declare metadata with attributes](#declare-metadata-with-attributes)
- [Using Ting as a User Provider](#using-ting-as-a-user-provider)
- [Declare a unique constraint](#declare-a-unique-constraint-in-a-table)
- [Using Ting as a Value Resolver](#using-ting-as-a-value-resolver)

## Configuration

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

    # Optional: how properties typed with a backed enum are stored, see "Declare metadata with attributes"
    # backed_enum (default): the value of the case; symfony_serializer: its JSON encoding, as ting_bundle < 4.0
    enum_serializer: backed_enum
```

Entities declared with [attributes](#declare-metadata-with-attributes) need nothing more. Repositories declaring their
metadata in PHP (Ting's `MetadataInitializer`) are loaded with `repositories`:

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

The cache pool is any Symfony cache pool, for instance:

```yaml
framework:
    cache:
        pools:
            cache.ting:
                adapter: cache.adapter.redis
```

## About public properties
Public properties can be used in your entities, however for PHP < 8.4, you should declare a setter to notify the property change.

PHP < 8.4:

```php
<?php

namespace App\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class City implements NotifyPropertyInterface {
    use NotifyProperty;
    
    public string $name;
    
    public function setName(string $name): void
    {
        $this->propertyChanged('name', $this->name ?? null, $name);
        $this->name = $name;
    }

}

```

For PHP >= 8.4, you may use a property hook instead. This hook will be bypassed by Ting for hydration.

```php
<?php

namespace App\Entity;

use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;

class City implements NotifyPropertyInterface {
    use NotifyProperty;
    
    public string $name { 
        set(string $name) {
            $this->propertyChanged('name', $this->name ?? null, $name);
            $this->name = $name;
        }
    }
}
```

### A note about uninitialized typed properties
- When persisting an entity with uninitialized typed property, the property will be ignored ; a default value must be defined in your database for this column to prevent a failure
- You cannot access an uninitialized typed property, PHP will trigger an error

## Declare metadata with attributes
Attributes are provided to declare an entity. Relevant attributes are available in `CCMBenchmark\TingBundle\Schema`.

### Table
- Full name: `CCMBenchmark\TingBundle\Schema\Table`
- This attribute must be added to your class.
- Arguments:
  - `name`: the table
  - `repository`: the repository class (required)
  - `connection`: the connection, `default_connection` of the configuration when omitted
  - `database`: the database, `default_database` of the configuration when omitted

```php
// With default_connection and default_database in the configuration
#[Schema\Table(name: 'city', repository: CityRepository::class)]
class City { /* ... */ }

// Explicit values win over the configuration
#[Schema\Table(name: 'log', connection: 'logs', database: 'audit', repository: LogRepository::class)]
class Log { /* ... */ }
```

Building the container fails with a `LogicException` naming the entity when the connection or the database is neither
given nor configured, or when the repository is missing.

### Column
- Full name: `CCMBenchmark\TingBundle\Schema\Column`
- This attribute must be added to every property mapped to the database. Serialization is inferred from the type, if available.
- Arguments (by name): `column`, `primary`, `autoIncrement`, `serializer`, `serializerOptions`, `mutable`.

| Property type                                   | Ting type / serializer                                   |
|-------------------------------------------------|----------------------------------------------------------|
| `string`, `int`, `float`, `bool`                | `string`, `int`, `double`, `bool`                        |
| `array`                                         | `json`, decoded as an associative array                  |
| `\DateTimeImmutable` / `\DateTime`              | `datetime_immutable` / `datetime` (`Y-m-d H:i:s`)        |
| `\DateTimeZone`                                 | `datetimezone`                                           |
| `Symfony\Component\Uid\Uuid` and subclasses     | `uuid`                                                   |
| `Brick\Geo\Geometry` subclasses                 | `geometry`                                               |
| backed enum                                     | `string`, with Ting's `BackedEnum` serializer: the value of the case (see `enum_serializer`) |
| any other class (pure enum, value object...)    | `symfony_serializer` (JSON), when symfony/serializer is installed |

#### Change tracking: `mutable`
A *mutable* value can be modified in place without a setter (`$city->updatedAt->modify('+1 day')`): Ting compares it
on save instead of relying on `propertyChanged()`. Ting decides from the serializer which values are mutable; the
bundle refines it from the type of the property:

- the values of a custom serializer (including `symfony_serializer`) are immutable when the property type is a scalar,
  an array, an enum, a `\DateTimeImmutable` or a `readonly` class;
- `#[Column(mutable: true|false)]` overrides both.

Prefer `\DateTimeImmutable` dates and `readonly` value objects. See
[Tracking changes](https://github.com/xavierleune/ting/blob/main/docs/entities.md#tracking-changes) in Ting's
documentation for the details.

### Full example

```php
<?php
// src/Entity/City.php

namespace App\Entity;

use App\Repository\CityRepository;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\TingBundle\Schema;

// The connection and the database are default_connection and default_database of the configuration
#[Schema\Table(name: 'city_table', repository: CityRepository::class)]
class City implements NotifyPropertyInterface
{
    use NotifyProperty;
    #[Schema\Column(autoIncrement: true, primary: true)]
    public int $id {
        set(int $id) {
            $this->propertyChanged('id', $this->id ?? null, $id);
            $this->id = $id;
        }
    }
    
    #[Schema\Column(column: 'field')]
    public string $fieldWithSpecifiedColumnName {
        set (string $fieldWithSpecifiedColumnName) {
            $this->propertyChanged('fieldWithSpecifiedColumnName', $this->fieldWithSpecifiedColumnName ?? null, $fieldWithSpecifiedColumnName);
            $this->fieldWithSpecifiedColumnName = $fieldWithSpecifiedColumnName;
        }
    }
}
```
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

## Using Ting as a User Provider
User providers (re)load users from a storage based on a "user identifier" (extract from [symfony documentation](https://symfony.com/doc/current/security/user_providers.html)).

Ting can be used as a User Provider, it's automatically registered by the bundle as the provider `ting`. To do so, update your security configuration.

```yaml
security:
  # https://symfony.com/doc/current/security.html#loading-the-user-the-user-provider
  providers:
    app_user_provider:
      ting:
        class: App\Entity\User
        property: email
```

Your entity must implement the following interfaces: `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` (for password authenticated users) and `Symfony\Component\Security\Core\User\UserInterface` (common to all kind of users).
It needs to implement `__serialize` too: the user is serialized in the session, keep only what identifies it (and
the password hash for password authenticated users), not other sensitive data.

## Declare a Unique constraint in a table
If you use the component `symfony/validator`, you may need to ensure that a value (or a combination of them) is unique in your table.

You can use the Constraint `CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity` to do so, as an attribute with named arguments (`fields`, `repository`, and optionally `identityFields`, `message`, `groups`, `payload`).
Passing an array of options (`options: [...]`) was deprecated in 3.12 and is no longer supported since 4.0.

Example:
```php
<?php

namespace App\Entity;

use App\Repository\UserRepository;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\TingBundle\Schema\Column;
use CCMBenchmark\TingBundle\Schema\Table;
use CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[Table(name: 'users', repository: UserRepository::class)]
#[UniqueEntity(fields: ['email'], repository: UserRepository::class, groups: ['create'])]
class User implements UserInterface, NotifyPropertyInterface {
    use NotifyProperty;

    #[Column(autoIncrement: true, primary: true)]
    public int $id { set(int $id) {
        $this->propertyChanged('id', $this->id ?? null, $id);
        $this->id = $id;
    }}
    
    #[Column]
    #[Groups(['default', 'create', 'update', 'service_account'])]
    #[Assert\NotBlank(groups: ["default", "create", "update"])]
    #[Assert\Email(groups: ["default", "create", "update"])]
    public string $email { set(string $email) {
        $this->propertyChanged('email', $this->email ?? null, $email);
        $this->email = $email;
    } }
    
    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    // Required by UserInterface in Symfony 7.4, removed in Symfony 8: #[\Deprecated] silences the 7.3+ deprecation
    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function __serialize(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
```

With that example you can assert, when creating a new user, that the email address is unique:

```php
<?php
namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users', methods: ['POST'], format: 'json')]
class CreateUserController
{
    public function __construct(private readonly ValidatorInterface $validator, private readonly UserRepository $userRepository) { }

    public function __invoke(
        #[MapRequestPayload(serializationContext: ['groups' => ['create']])] User $user
    ): JsonResponse {
        $violations = $this->validator->validate($user, groups: ['create']);
        if ($violations->count() > 0) {
            return new JsonResponse(['message' => 'Errors...'], 422);
        }
        $this->userRepository->save($user);
        return new JsonResponse(['message' => 'User registered'], 201);
    }
}

```

## Using Ting as a Value Resolver
This bundle automatically registers a [Value Resolver](https://symfony.com/doc/current/controller/value_resolver.html#built-in-value-resolvers).

You can automatically map request parameters to entities:
1. Declare a parameter in your route (i.e: `/api/users/{userId}`), using the property in your entity you'll use to fetch data (in this case: `userId`)
2. Map it to your action parameters:
   1. Add it to your signature: `public function getUser(User $user)`
   2. Update the route to do the mapping: `/api/users/{userId:user}` (in this case: the `User` having `userId` matching the request will be fetched and injected to your action with the argument `$user`)

```php
<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

class UserController {
    public function __construct(private readonly SerializerInterface $serializer) { }

    #[Route('/api/users/{userId:user}', name: 'get_user', methods: ['GET'], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function getUser(User $user): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($user, 'json', ['groups' => 'default']), json: true);
    }
}
```

For more advanced use cases, you can leverage:
- [The Expression Language component](https://symfony.com/doc/current/components/expression_language.html)
- The `CCMBenchmark\TingBundle\Attribute\MapEntity` attribute

The expression receives `repository` (the repository of the entity), `request`, and every request attribute as a
variable: the route parameters are available by their name (by their mapped name for `{parameter:name}`), whether or
not the action has an argument for them.

Example:

```php
<?php

namespace App\Controller;

use App\Entity\User;
use CCMBenchmark\TingBundle\Attribute\MapEntity;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;

class UserController {
    public function __construct(private readonly SerializerInterface $serializer) { }

    #[Route('/api/users/{firstname}/{lastname}', name: 'get_user', methods: ['GET'], format: 'json')]
    #[IsGranted('ROLE_USER')]
    public function getUser(#[MapEntity(expr: 'repository.getOneBy({"firstname": firstname, "lastname": lastname})')] User $user): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($user, 'json', ['groups' => 'default']), json: true);
    }
}
```
