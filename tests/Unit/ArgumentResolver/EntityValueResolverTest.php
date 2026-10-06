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

namespace CCMBenchmark\TingBundle\Tests\Unit\ArgumentResolver;

use CCMBenchmark\Ting\Cache\Cache;
use CCMBenchmark\Ting\ConnectionPool;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Query\QueryFactory;
use CCMBenchmark\Ting\Repository\CollectionFactory;
use CCMBenchmark\Ting\Repository\Hydrator;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Serializer\SerializerFactory;
use CCMBenchmark\Ting\UnitOfWork;
use CCMBenchmark\TingBundle\ArgumentResolver\EntityValueResolver;
use CCMBenchmark\TingBundle\Attribute\MapEntity;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use tests\fixtures\SimpleRepository;

// Partial mocks are used to run the real code of the methods they do not replace, not to verify calls
#[AllowMockObjectsWithoutExpectations]
class EntityValueResolverTest extends TestCase
{
    private MetadataRepository&MockObject $metadataRepository;
    private RepositoryFactory&MockObject $repositoryFactory;
    private ExpressionLanguage&MockObject $expressionLanguage;
    private EntityValueResolver $resolver;

    protected function setUp(): void
    {
        $this->metadataRepository = $this->getMockBuilder(MetadataRepository::class)
            ->setConstructorArgs([new SerializerFactory()])
            ->onlyMethods(['findMetadataForEntity'])
            ->getMock();
        $connectionPool = new ConnectionPool();
        $queryFactory = new QueryFactory();
        $unitOfWork = new UnitOfWork($connectionPool, $this->metadataRepository, $queryFactory);
        $hydrator = new Hydrator();
        $this->repositoryFactory = $this->getMockBuilder(RepositoryFactory::class)
            ->setConstructorArgs([
                $connectionPool,
                $this->metadataRepository,
                new QueryFactory(),
                new CollectionFactory($this->metadataRepository, $unitOfWork, $hydrator),
                $unitOfWork,
                new Cache(),
                new SerializerFactory()
            ])
            ->onlyMethods(['get'])
            ->getMock();
        $this->expressionLanguage = $this->getMockBuilder(ExpressionLanguage::class)
            ->onlyMethods(['evaluate'])
            ->getMock();

        $this->resolver = new EntityValueResolver(
            $this->metadataRepository,
            $this->repositoryFactory,
            $this->expressionLanguage
        );
    }

    public function testReturnsEmptyArrayWhenArgumentIsAlreadyObject(): void
    {
        $request = new Request(['argumentName' => new \stdClass()]);
        $argument = new ArgumentMetadata('argumentName', null, false, false, null);

        $result = $this->resolver->resolve($request, $argument);

        $this->assertEmpty($result);
    }

    public function testReturnsEmptyArrayWhenMapEntityIsDisabled(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', disabled: true);
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request();

        $result = $this->resolver->resolve($request, $argument);

        $this->assertEmpty($result);
    }

    public function testWithRouteMapping(): void
    {
        $argumentCity = $this->createArgumentMetadataForAttributes(new MapEntity(class: 'City'), 'city');
        $argumentCountry = $this->createArgumentMetadataForAttributes(new MapEntity(class: 'Country'), 'country');
        $request = new Request(attributes: ['city' => 'Paris', 'country' => 'France', '_route_mapping' => ['slug' => 'city', 'country' => 'country']]);
        $city = new \stdClass();
        $country = new \stdClass();

        $repository = $this->createSimpleRepository(['getOneBy']);
        $repository->method('getOneBy')->willReturnCallback(static fn($criteria) => match ($criteria) {
            ['slug' => 'Paris'] => $city,
            ['country' => 'France'] => $country,
        });
        $this->mockRepositoryFactoryAndMetadata($repository);

        $result = $this->resolver->resolve($request, $argumentCity);
        $this->assertCount(1, $result);
        $this->assertContains($city, $result);

        $result = $this->resolver->resolve($request, $argumentCountry);
        $this->assertCount(1, $result);
        $this->assertContains($country, $result);
    }

    public function testThrowsNotFoundHttpExceptionWhenObjectCannotBeFound(): void
    {
        $simpleRepository = $this->createSimpleRepository(['get']);
        $simpleRepository->method('get')->willReturn(null);
        $mapEntity = new MapEntity(class: 'TestEntity', id: 'id');
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request(attributes: ['id' => 123]);

        $this->metadataRepository->method('findMetadataForEntity')->willReturnCallback(
            fn($class, $success) => $success(new Metadata(new SerializerFactory()))
        );
        $this->repositoryFactory->method('get')->willReturn($simpleRepository);

        $this->assertThrows(NotFoundHttpException::class, function () use ($request, $argument) {
            $this->resolver->resolve($request, $argument);
        });
    }

    public function testReturnsObjectWhenFoundInRepository(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', id: 'id');
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request(attributes: ['id' => 123]);

        $repository = $this->createSimpleRepository(['get']);
        $repository->method('get')->willReturn($object = new \stdClass());
        $this->mockRepositoryFactoryAndMetadata($repository);

        $result = $this->resolver->resolve($request, $argument);

        $this->assertCount(1, $result);
        $this->assertContains($object, $result);
    }

    public function testEvaluatesExpressionWhenProvided(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', expr: 'repository.find(id)');
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request(attributes: ['id' => 123]);

        $repository = $this->createSimpleRepository([]);
        $this->mockRepositoryFactoryAndMetadata($repository);
        $this->expressionLanguage->method('evaluate')->willReturn($object = new \stdClass());

        $result = $this->resolver->resolve($request, $argument);

        $this->assertCount(1, $result);
        $this->assertContains($object, $result);
    }

    public function testExpressionFailureReturns404(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', expr: 'repository.find(id)');
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request(attributes: ['id' => 123]);

        $repository = $this->createSimpleRepository([]);
        $this->mockRepositoryFactoryAndMetadata($repository);
        $this->expressionLanguage->method('evaluate')->willReturn(null);

        $this->assertThrows(NotFoundHttpException::class, function () use ($request, $argument) {
            $this->resolver->resolve($request, $argument);
        });
    }

    public function testReturnsEmptyArrayWhenCriteriaCannotBeDetermined(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', mapping: []);
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request();

        $repository = $this->createSimpleRepository(['getOneBy']);
        $repository->method('getOneBy')->willReturn(null);
        $this->mockRepositoryFactoryAndMetadata($repository);

        $result = $this->resolver->resolve($request, $argument);

        $this->assertEmpty($result);
    }

    public function testReturnsObjectWhenCriteriaCanBeDetermined(): void
    {
        $mapEntity = new MapEntity(class: 'TestEntity', mapping: ['name' => 'name', 'id' => 'id']);
        $argument = $this->createArgumentMetadataForAttributes($mapEntity);
        $request = new Request(attributes: ['id' => 123, 'name' => 'foo']);

        $repository = $this->createSimpleRepository(['getOneBy']);
        $repository->method('getOneBy')->willReturn($object = new \stdClass());
        $this->mockRepositoryFactoryAndMetadata($repository);

        $result = $this->resolver->resolve($request, $argument);

        $this->assertCount(1, $result);
        $this->assertContains($object, $result);
    }

    private function createArgumentMetadataForAttributes(MapEntity $attribute, string $argumentName = 'argumentName'): ArgumentMetadata
    {
        return new ArgumentMetadata($argumentName, $attribute->class, false, false, null, false, [$attribute]);
    }

    /**
     * @param list<string> $methods the only methods replaced, the others run the real code (as atoum mocks)
     */
    private function createSimpleRepository(array $methods): SimpleRepository&MockObject
    {
        return $this->getMockBuilder(SimpleRepository::class)
            ->onlyMethods($methods)
            ->getMock();
    }

    private function mockRepositoryFactoryAndMetadata(SimpleRepository $repository): void
    {
        $metadata = $this->getMockBuilder(Metadata::class)
            ->setConstructorArgs([new SerializerFactory()])
            ->onlyMethods(['getRepository'])
            ->getMock();
        $metadata->method('getRepository')->willReturn('RepositoryClass');

        $this->metadataRepository->method('findMetadataForEntity')->willReturnCallback(
            fn($class, $success) => $success($metadata)
        );

        $this->repositoryFactory->method('get')->willReturn($repository);
    }
}
