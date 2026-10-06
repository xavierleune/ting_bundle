<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\TingBundle\Tests\Unit\Validator\Constraints;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity;
use CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Context\ExecutionContext;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use tests\fixtures\City;

class UniqueEntityValidatorTest extends TestCase
{
    public function testValidateWithWrongConstraintShouldThrowUnexpectedTypeException(): void
    {
        $uniqueEntityValidator = new UniqueEntityValidator($this->createStub(RepositoryFactory::class));

        $this->assertThrows(UnexpectedTypeException::class, function () use ($uniqueEntityValidator) {
            $uniqueEntityValidator->validate('MyEntity', new Blank());
        });
    }

    public function testValidateShouldBuildANewViolation(): void
    {
        $constraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $constraintViolationBuilder->expects($this->once())
            ->method('setParameter')
            ->with('{{ data }}', '')
            ->willReturnSelf();
        $executionContext = $this->createExecutionContext();

        $uniqueEntity = new UniqueEntity(fields: [], repository: 'FakeRepository');
        $executionContext->expects($this->once())
            ->method('buildViolation')
            ->with($uniqueEntity->message)
            ->willReturn($constraintViolationBuilder);

        $uniqueEntityValidator = new UniqueEntityValidator($this->createRepositoryFactory(fn ($params) => new \stdClass()));
        $uniqueEntityValidator->initialize($executionContext);

        $city = new City();
        $city->setName('Luxiol');
        $uniqueEntityValidator->validate($city, $uniqueEntity);
    }

    public function testValidateShouldNotBuildANewViolation(): void
    {
        $constraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $constraintViolationBuilder->expects($this->never())->method('setParameter');
        $executionContext = $this->createExecutionContext();
        $executionContext->expects($this->never())->method('buildViolation');

        $uniqueEntity = new UniqueEntity(fields: [], repository: 'FakeRepository');

        $uniqueEntityValidator = new UniqueEntityValidator($this->createRepositoryFactory(fn ($params) => null));
        $uniqueEntityValidator->initialize($executionContext);

        $city = new City();
        $city->setName('Luxiol');
        $uniqueEntityValidator->validate($city, $uniqueEntity);
    }

    public function testValidateShouldNotBuildANewViolationWithSameEntity(): void
    {
        $constraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $constraintViolationBuilder->expects($this->never())->method('setParameter');
        $executionContext = $this->createExecutionContext();
        $executionContext->expects($this->never())->method('buildViolation');

        $uniqueEntity = new UniqueEntity(fields: [], repository: 'FakeRepository');
        $uniqueEntity->identityFields = ['id'];

        $cityId = hexdec(uniqid());
        $uniqueEntityValidator = new UniqueEntityValidator($this->createRepositoryFactory(function ($params) use ($cityId) {
            $city = new City();
            $city->setId($cityId);
            return $city;
        }));
        $uniqueEntityValidator->initialize($executionContext);

        $city = new City();
        $city->setId($cityId);
        $city->setName('Luxiol');
        $uniqueEntityValidator->validate($city, $uniqueEntity);
    }

    public function testValidateShouldBuildANewViolationWithIdentityField(): void
    {
        $constraintViolationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $constraintViolationBuilder->expects($this->once())
            ->method('setParameter')
            ->with('{{ data }}', '')
            ->willReturnSelf();
        $executionContext = $this->createExecutionContext();

        $uniqueEntity = new UniqueEntity(fields: [], repository: 'FakeRepository');
        $uniqueEntity->identityFields = ['id'];
        $executionContext->expects($this->once())
            ->method('buildViolation')
            ->with($uniqueEntity->message)
            ->willReturn($constraintViolationBuilder);

        $cityId = hexdec(uniqid());
        $uniqueEntityValidator = new UniqueEntityValidator($this->createRepositoryFactory(function ($params) use ($cityId) {
            $city = new City();
            $city->setId($cityId);
            return $city;
        }));
        $uniqueEntityValidator->initialize($executionContext);

        $city = new City();
        $city->setId(hexdec(uniqid()));
        $city->setName('Luxiol');
        $uniqueEntityValidator->validate($city, $uniqueEntity);
    }

    /**
     * Real execution context (as the atoum partial mock), only buildViolation() is replaced
     */
    private function createExecutionContext(): ExecutionContext&MockObject
    {
        $executionContext = $this->getMockBuilder(ExecutionContext::class)
            ->setConstructorArgs([
                $this->createStub(ValidatorInterface::class),
                '',
                $this->createStub(TranslatorInterface::class),
            ])
            ->onlyMethods(['buildViolation'])
            ->getMock();
        $executionContext->setConstraint(new class () extends Constraint {
        });

        return $executionContext;
    }

    private function createRepositoryFactory(\Closure $getOneBy): RepositoryFactory
    {
        $metadata = $this->createStub(Metadata::class);
        $metadata->method('getEntityPropertyByFieldName')->willReturnCallback(
            fn ($entity, $fieldName) => $entity->{'get' . $fieldName}()
        );

        $repository = $this->createStub(Repository::class);
        $repository->method('getOneBy')->willReturnCallback($getOneBy);
        $repository->method('getMetadata')->willReturn($metadata);

        $repositoryFactory = $this->createStub(RepositoryFactory::class);
        $repositoryFactory->method('get')->willReturn($repository);

        return $repositoryFactory;
    }
}
