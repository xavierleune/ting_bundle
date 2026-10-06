<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2025 CCM Benchmark Group. (http://www.ccmbenchmark.com)
 * Copyright (C) 2026 Xavier Leune
 *
 ***********************************************************************
 *
 * Licensed under the Apache License, Version 2.0 (the "License"); you
 * may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 *
 **********************************************************************/

namespace CCMBenchmark\TingBundle\Security;

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @template TUser of UserInterface
 *
 * @template-implements UserProviderInterface<TUser>
 */
class EntityUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(
        private readonly MetadataRepository $metadataRepository,
        private readonly RepositoryFactory  $repositoryFactory,
        private readonly string             $class,
        private readonly ?string            $property = null,
    ) {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $repository = $this->getRepository();
        if (null !== $this->property) {
            $user = $repository->getOneBy([$this->property => $identifier]);
        } else {
            if (!$repository instanceof UserLoaderInterface) {
                throw new \InvalidArgumentException(\sprintf('You must either make the "%s" entity Ting Repository ("%s") implement "CCMBenchmark\TingBundle\Security\UserLoaderInterface" or set the "property" option in the corresponding entity provider configuration.', $this->class, get_debug_type($repository)));
            }

            $user = $repository->loadUserByIdentifier($identifier);
        }

        if (null === $user) {
            $e = new UserNotFoundException(\sprintf('User "%s" not found.', $identifier));
            $e->setUserIdentifier($identifier);

            throw $e;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof $this->class) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }
        
        $repository = $this->getRepository();
        if ($repository instanceof UserProviderInterface) {
            $refreshedUser = $repository->refreshUser($user);
        } else {
            // The user must be reloaded via the primary key as all other data
            // might have changed without proper persistence in the database.
            // That's the case when the user has been changed by a form with
            // validation errors.
            if (!$id = $this->getIdentifierValues($user)) {
                throw new \InvalidArgumentException('You cannot refresh a user from the EntityUserProvider that does not contain an identifier. The user object has to be serialized with its own identifier mapped by Ting.');
            }

            $refreshedUser = $repository->get($id);
            if (null === $refreshedUser) {
                $e = new UserNotFoundException('User with id '.json_encode($id).' not found.');
                $e->setUserIdentifier(json_encode($id));

                throw $e;
            }
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return $class === $this->class || is_subclass_of($class, $this->class);
    }

    /**
     * @final
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof $this->class) {
            throw new UnsupportedUserException(\sprintf('Instances of "%s" are not supported.', get_debug_type($user)));
        }

        $repository = $this->getRepository();
        if ($repository instanceof PasswordUpgraderInterface) {
            $repository->upgradePassword($user, $newHashedPassword);
        }
    }
    
    private function getMetadata(): Metadata
    {
        $metadata = null;
        $this->metadataRepository->findMetadataForEntity($this->class, function (Metadata $innerMetadata) use (&$metadata) {
            $metadata = $innerMetadata;
        }, fn () => null);
        
        if ($metadata === null) {
            throw new \InvalidArgumentException(\sprintf('No metadata found for entity "%s".', $this->class));
        }
        
        return $metadata;
    }

    private function getRepository(): Repository
    {
        return $this->repositoryFactory->get($this->getMetadata()->getRepository());
    }
    
    private function getIdentifierValues(UserInterface $user): ?array
    {
        $metadata = $this->getMetadata();
        $primaries = $metadata->getPrimaries();
        if ($primaries === []) {
            return null;
        }
        $identifierValues = [];
        // Primaries are field definitions indexed by column name: Repository::get() takes property names
        foreach ($primaries as $primary) {
            $identifierValues[$primary['fieldName']] = $metadata->getEntityPropertyByFieldName($user, $primary['fieldName']);
        }
        
        return $identifierValues;
    }
}
