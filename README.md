> **ℹ️ This is a maintained fork of [ccmbenchmark/ting_bundle](https://github.com/ccmbenchmark/ting_bundle).**
> The upstream project is no longer maintained. This fork keeps the full history and tags of the original repository,
> and is published as `xavierleune/ting_bundle`. PHP namespaces (`CCMBenchmark\TingBundle`) are unchanged, so switching
> only requires replacing the package name in your `composer.json`.

Installation
============

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
    - [Main configuration](#main-configuration)
    - [About public properties](#about-public-properties)
    - [Declare metadata with attributes](#declare-metadata-with-attributes)
- [Using Ting as a User Provider](#using-ting-a-user-provider)
- [Declare a unique constraint](#declare-a-unique-constraint-in-a-table)
- [Using Ting as a Value Resolver](#using-ting-as-a-value-resolver)

Configuration
=============

## Main configuration
```
#!yaml

    ting:
        repositories: # Unnecessary if entities registered with attributes
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
                primary:
                    host:     localhost
                    user:     world_sample
                    password: world_sample
                    port:     3306
                replicas:
                    replica1:
                        host:     127.0.0.1
                        user:     world_sample_ro
                        password: world_sample_ro
                        port:     3306
                    replica2:
                        host:     127.0.1.1
                        user:     world_sample_ro
                        password: world_sample_ro
                        port:     3306

        databases_options:
            baz:
                timezone: 'Europe/Paris'

        # Optional: id of a Symfony cache pool, used by cached queries (a NullAdapter by default)
        cache_provider: cache.ting

        # Optional: how properties typed with a backed enum are stored, see "Declare metadata with attributes"
        # backed_enum (default): the value of the case; symfony_serializer: its JSON encoding, as ting_bundle < 4.0
        enum_serializer: backed_enum
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

For PHP >= 8.4, you may use a property hook instead. This hook will be bypassed by Ting for hydratation.

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
    };
}
```

### A note about uninitialized typed properties
- When persisting an entity with uninitialized typed property, the property will be ignored ; a default value must be defined in your database for this column to prevent a failure
- You cannot access an uninitialized typed property, PHP will trigger an error

## Declare metadata with attributes
Attributes are provided to declare an entity. Relevant attributes are available in `CCMBenchmark\TingBundle\Schema`.

### Table
- Full name: `CCMBenchmark\TingBundle\Schema\Table`
- This attribute must be added to your class, with all relevant options (table, connection, etc.).

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
Ting writes a field when its setter notifies the change (`propertyChanged()`). A value that can be modified in place
(`$city->updatedAt->modify('+1 day')`, `$city->address->street = '...'`) calls no setter: Ting 4 handles such a
*mutable* field by writing it on every save of the entity.

- Ting decides from the serializer: `\DateTime`, and objects of a custom serializer (including `symfony_serializer`), are
  mutable; scalars, arrays, `\DateTimeImmutable`, backed enums... are not.
- The bundle also marks as immutable the values of a custom serializer whose property type is a scalar, an array, an
  enum, a `\DateTimeImmutable` or a `readonly` class.
- `#[Column(mutable: true|false)]` overrides both.

Prefer `\DateTimeImmutable` dates and `readonly` value objects: their changes always go through a setter, and saving
an entity only writes what changed.

### Full example

```php
// src/Entity/City.php
<?php

namespace App\Entity;

namespace tests\fixtures;

use App\Repository\CityRepository;
use Brick\Geo\Point;
use CCMBenchmark\Ting\Entity\NotifyProperty;
use CCMBenchmark\Ting\Entity\NotifyPropertyInterface;
use CCMBenchmark\TingBundle\Schema;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV4;

#[Schema\Table('city_table', 'connectionName', '%env(DATABASE_NAME)%', CityRepository::class)]
class City implements NotifyPropertyInterface
{
    use NotifyProperty;
    #[Schema\Column(autoIncrement: true, primary: true)]
    public int $id {
        set(int $id) {
            $this->propertyChanged('id', $this->id ?? null, $id);
            $this->id = $id;
        }
    };
    
    #[Schema\Column(column: 'field')]
    public string $fieldWithSpecifiedColumnName {
        set (string $fieldWithSpecifiedColumnName) {
            $this->propertyChanged('fieldWithSpecifiedColumnName', $this->fieldWithSpecifiedColumnName ?? null, $fieldWithSpecifiedColumnName);
            $this->fieldWithSpecifiedColumnName = $fieldWithSpecifiedColumnName;
        }
    };
}
```
```php
// src/Repository/CityRepository.php
<?php

namespace App\Repository;

class CityRepository extends CCMBenchmark\Ting\Repository\Repository {

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

Your entity will have to implements the following interfaces: `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` (for password authenticated users) and `Symfony\Component\Security\Core\User\UserInterface` (common to all kind of users).
It needs to implement `__serialize` too.

## Declare a Unique constraint in a table
If you use the component `symfony/validator`, you may need to ensure that a value (or a combination of them) is unique in your table.

You can use the Constraint `CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity` to do so, as an attribute with named arguments (`fields`, `repository`, and optionally `identityFields`, `message`, `groups`, `payload`).
Passing an array of options (`options: [...]`) is deprecated since 3.12.

Example:
```php
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

#[Table(name: 'users', connection: 'main', database: '%env(DATABASE_DB_NAME)%', repository: UserRepository::class)]
#[UniqueEntity(fields: ['email'], repository: UserRepository::class, groups: ['create'])]
class User implements UserInterface, NotifyPropertyInterface {
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
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/users', methods:['POST'], format: 'json')]
class createUserController extends AbstractController {
    public function __construct (private readonly ValidatorInterface $validator, private readonly UserRepository $userRepository) { }
    public function __invoke(
        #[MapRequestPayload(serializationContext: ['groups' => ['create']])] User $user
    ) :JsonResponse {
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

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController {
    #[Route('/api/users/{userId:myUser}', name: 'get_user', methods: ['GET'], format: 'json' )]
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

Example:

```php
<?php

namespace App\Controller;

use CCMBenchmark\TingBundle\Attribute\MapEntity;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class UserController {
    #[Route('/api/users/{firstname}/{lastname}', name: 'get_user', methods: ['GET'], format: 'json' )]
    #[IsGranted('ROLE_USER')]
    public function getUser(#[MapEntity(expr: 'repository.getOneBy({"firstname": firstname, "lastname": lastname}')] User $user): JsonResponse
    {
        return new JsonResponse($this->serializer->serialize($user, 'json', ['groups' => 'default']), json: true);
    }
}
```