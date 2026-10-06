<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
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


namespace CCMBenchmark\TingBundle\Tests\Unit\Security;

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Serializer\SerializerFactory;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use CCMBenchmark\TingBundle\Security\EntityUserProvider;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use tests\fixtures\SimpleRepository;
use tests\fixtures\User;
use tests\fixtures\UserHydrationMetadata;

class EntityUserProviderTest extends TestCase
{
    public function testLoadUserByIdentifierUsesTheProperty(): void
    {
        $user = new User(42, 'jane@example.com');
        $repository = $this->createMock(SimpleRepository::class);
        $repository->expects($this->once())
            ->method('getOneBy')
            ->with(['email' => 'jane@example.com'])
            ->willReturn($user);

        $provider = $this->createProvider($repository, 'email');

        $this->assertSame($user, $provider->loadUserByIdentifier('jane@example.com'));
    }

    public function testLoadUserByIdentifierThrowsWhenNotFound(): void
    {
        $repository = $this->createStub(SimpleRepository::class);
        $repository->method('getOneBy')->willReturn(null);

        $provider = $this->createProvider($repository, 'email');

        $this->assertThrows(UserNotFoundException::class, fn () => $provider->loadUserByIdentifier('jane@example.com'));
    }

    public function testRefreshUserReloadsTheUserByItsPrimaryKeyProperties(): void
    {
        $refreshedUser = new User(42, 'jane@example.com');
        $repository = $this->createMock(SimpleRepository::class);
        // Ting 4.0 reads a primary key by property names (the column is "usr_id")
        $repository->expects($this->once())
            ->method('get')
            ->with(['id' => 42])
            ->willReturn($refreshedUser);

        $provider = $this->createProvider($repository, 'email');

        $this->assertSame($refreshedUser, $provider->refreshUser(new User(42, 'old@example.com')));
    }

    public function testRepositoryIsTheClassTheMetadataAreRegisteredUnder(): void
    {
        $user = new User(42, 'jane@example.com');
        $repository = $this->createStub(SimpleRepository::class);
        $repository->method('getOneBy')->willReturn($user);

        // As batchLoadMetadata() does: the metadata don't name their repository
        $provider = $this->createProvider($repository, 'email', setRepository: false);

        $this->assertSame($user, $provider->loadUserByIdentifier('jane@example.com'));
    }

    public function testMetadataWithoutRepositoryCannotProvideUsers(): void
    {
        $metadataRepository = new MetadataRepository(new SerializerFactory());
        $metadataRepository->addMetadata(
            UserHydrationMetadata::class,
            UserHydrationMetadata::initMetadata(new SerializerFactory())
        );
        $repositoryFactory = $this->createMock(RepositoryFactory::class);
        $repositoryFactory->expects($this->never())->method('get');

        $provider = new EntityUserProvider($metadataRepository, $repositoryFactory, User::class, 'email');

        $this->assertThrows(
            \InvalidArgumentException::class,
            fn () => $provider->loadUserByIdentifier('jane@example.com'),
            'The metadata of "tests\fixtures\User" have no repository: register them with a Ting repository to load users.'
        );
    }

    private function createProvider(SimpleRepository $repository, ?string $property, bool $setRepository = true): EntityUserProvider
    {
        $metadata = UserHydrationMetadata::initMetadata(new SerializerFactory());
        if ($setRepository) {
            $metadata->setRepository(SimpleRepository::class);
        }
        $metadataRepository = new MetadataRepository(new SerializerFactory());
        $metadataRepository->addMetadata(SimpleRepository::class, $metadata);

        $repositoryFactory = $this->createMock(RepositoryFactory::class);
        $repositoryFactory->expects($this->once())
            ->method('get')
            ->with(SimpleRepository::class)
            ->willReturn($repository);

        return new EntityUserProvider($metadataRepository, $repositoryFactory, User::class, $property);
    }
}
