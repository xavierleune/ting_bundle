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
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Serializer\SerializerFactory;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use CCMBenchmark\TingBundle\Security\EntityUserProvider;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use tests\fixtures\SimpleRepository;
use tests\fixtures\User;

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

    private function createProvider(SimpleRepository $repository, ?string $property): EntityUserProvider
    {
        $metadata = new Metadata(new SerializerFactory());
        $metadata->setEntity(User::class);
        $metadata->setRepository(SimpleRepository::class);
        $metadata->setConnectionName('main');
        $metadata->setDatabase('app');
        $metadata->setTable('usr_user');
        $metadata->addField(['fieldName' => 'id', 'columnName' => 'usr_id', 'type' => 'int', 'primary' => true]);
        $metadata->addField(['fieldName' => 'email', 'columnName' => 'usr_email', 'type' => 'string']);
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
