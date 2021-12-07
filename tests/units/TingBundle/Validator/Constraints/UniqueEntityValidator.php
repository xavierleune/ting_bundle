<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2014 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace tests\units\CCMBenchmark\TingBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Blank;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use tests\fixtures\City;

class UniqueEntityValidator extends \atoum
{
    public function testValidateWithWrongConstraintShouldThrowUnexpectedTypeException()
    {
        $this->mockGenerator->orphanize('__construct');

        $this
            ->if($mockUniqueEntityValidator =
                     new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator())
            ->then
                ->exception(function () use ($mockUniqueEntityValidator) {
                    $mockUniqueEntityValidator->validate('MyEntity', new Blank());
                })
                    ->isInstanceOf(UnexpectedTypeException::class);
        ;
    }

    public function testValidateShouldBuildANewViolation()
    {
        $mockValidator = new \mock\Symfony\Component\Validator\Validator\ValidatorInterface();
        $mockTranslator = new \mock\Symfony\Contracts\Translation\TranslatorInterface();

        $mockConstraintViolationBuilder =
            new \mock\Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface();
        $this->calling($mockConstraintViolationBuilder)->setParameter =
            function ($key, $value) use ($mockConstraintViolationBuilder) {
                return $mockConstraintViolationBuilder;
            };
        $this->calling($mockConstraintViolationBuilder)->addViolation = null;

        $mockExecutionContext =
            new \mock\Symfony\Component\Validator\Context\ExecutionContext(
                $mockValidator,
                '',
                $mockTranslator
            );
        $this->calling($mockExecutionContext)->buildViolation = function () use ($mockConstraintViolationBuilder) {
            return $mockConstraintViolationBuilder;
        };
        $mockExecutionContext->setConstraint(new \mock\Symfony\Component\Validator\Constraint());

        $this->mockGenerator->orphanize('__construct');
        $mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity();

        $mockFakeRepository = new \mock\FakeRepository;
        $this->calling($mockFakeRepository)->getOneBy = function ($params) {
            return new \stdClass();
        };
        $this->calling($mockFakeRepository)->getMetadata = $this->getMockMetadata();

        $this->mockGenerator->orphanize('__construct');
        $mockRepositoryFactory = new \mock\CCMBenchmark\TingBundle\Repository\RepositoryFactory();
        $this->calling($mockRepositoryFactory)->get = $mockFakeRepository;

        $mockUniqueEntityValidator =
            new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator($mockRepositoryFactory);
        $mockUniqueEntityValidator->initialize($mockExecutionContext);

        $this
            ->if($city = new City())
            ->and($city->setName('Luxiol'))
            ->and($mockUniqueEntityValidator->validate($city, $mockUniqueEntity))
            ->then
                ->mock($mockExecutionContext)
                    ->call('buildViolation')
                        ->withArguments($mockUniqueEntity->message)
                            ->once()
                ->mock($mockConstraintViolationBuilder)
                    ->call('setParameter')
                        ->withArguments('{{ data }}', '')
                            ->once();
        ;
    }

    public function testValidateShouldNotBuildANewViolation()
    {
        $mockValidator = new \mock\Symfony\Component\Validator\Validator\ValidatorInterface();
        $mockTranslator = new \mock\Symfony\Contracts\Translation\TranslatorInterface();

        $mockConstraintViolationBuilder =
            new \mock\Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface();
        $this->calling($mockConstraintViolationBuilder)->setParameter =
            function ($key, $value) use ($mockConstraintViolationBuilder) {
                return $mockConstraintViolationBuilder;
            };
        $this->calling($mockConstraintViolationBuilder)->addViolation = null;

        $mockExecutionContext =
            new \mock\Symfony\Component\Validator\Context\ExecutionContext(
                $mockValidator,
                '',
                $mockTranslator
            );
        $this->calling($mockExecutionContext)->buildViolation = function () use ($mockConstraintViolationBuilder) {
            return $mockConstraintViolationBuilder;
        };
        $mockExecutionContext->setConstraint(new \mock\Symfony\Component\Validator\Constraint());

        $this->mockGenerator->orphanize('__construct');
        $mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity();

        $mockFakeRepository = new \mock\FakeRepository;
        $this->calling($mockFakeRepository)->getOneBy = function ($params) {
            return null;
        };
        $this->calling($mockFakeRepository)->getMetadata = $this->getMockMetadata();

        $this->mockGenerator->orphanize('__construct');
        $mockRepositoryFactory = new \mock\CCMBenchmark\TingBundle\Repository\RepositoryFactory();
        $this->calling($mockRepositoryFactory)->get = $mockFakeRepository;

        $mockUniqueEntityValidator =
            new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator($mockRepositoryFactory);
        $mockUniqueEntityValidator->initialize($mockExecutionContext);

        $this
            ->if($city = new City())
            ->and($city->setName('Luxiol'))
            ->and($mockUniqueEntityValidator->validate($city, $mockUniqueEntity))
            ->then
                ->mock($mockExecutionContext)
                    ->call('buildViolation')
                        ->never()
                ->mock($mockConstraintViolationBuilder)
                    ->call('setParameter')
                        ->never()
        ;
    }

    public function testValidateShouldNotBuildANewViolationWithSameEntity()
    {
        $mockValidator = new \mock\Symfony\Component\Validator\Validator\ValidatorInterface();
        $mockTranslator = new \mock\Symfony\Contracts\Translation\TranslatorInterface();

        $mockConstraintViolationBuilder =
            new \mock\Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface();
        $this->calling($mockConstraintViolationBuilder)->setParameter =
            function ($key, $value) use ($mockConstraintViolationBuilder) {
                return $mockConstraintViolationBuilder;
            };
        $this->calling($mockConstraintViolationBuilder)->addViolation = null;

        $mockExecutionContext =
            new \mock\Symfony\Component\Validator\Context\ExecutionContext(
                $mockValidator,
                '',
                $mockTranslator
            );
        $this->calling($mockExecutionContext)->buildViolation = function () use ($mockConstraintViolationBuilder) {
            return $mockConstraintViolationBuilder;
        };
        $mockExecutionContext->setConstraint(new \mock\Symfony\Component\Validator\Constraint());

        $this->mockGenerator->orphanize('__construct');
        $mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity();
        $mockUniqueEntity->identityFields = ['id'];

        $mockFakeRepository = new \mock\FakeRepository;
        $cityId = hexdec(uniqid());
        $this->calling($mockFakeRepository)->getOneBy = function ($params) use ($cityId) {
            $city = new City();
            $city->setId($cityId);
            return $city;
        };
        $this->calling($mockFakeRepository)->getMetadata = $this->getMockMetadata();

        $this->mockGenerator->orphanize('__construct');
        $mockRepositoryFactory = new \mock\CCMBenchmark\TingBundle\Repository\RepositoryFactory();
        $this->calling($mockRepositoryFactory)->get = $mockFakeRepository;

        $mockUniqueEntityValidator =
            new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator($mockRepositoryFactory);
        $mockUniqueEntityValidator->initialize($mockExecutionContext);

        $this
            ->if($city = new City())
            ->and($city->setId($cityId))
            ->and($city->setName('Luxiol'))
            ->and($mockUniqueEntityValidator->validate($city, $mockUniqueEntity))
            ->then
                ->mock($mockExecutionContext)
                    ->call('buildViolation')
                        ->never()
                ->mock($mockConstraintViolationBuilder)
                    ->call('setParameter')
                        ->never()
        ;
    }

    public function testValidateShouldBuildANewViolationWithIdentityField()
    {
        $mockValidator = new \mock\Symfony\Component\Validator\Validator\ValidatorInterface();
        $mockTranslator = new \mock\Symfony\Contracts\Translation\TranslatorInterface();

        $mockConstraintViolationBuilder =
            new \mock\Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface();
        $this->calling($mockConstraintViolationBuilder)->setParameter =
            function ($key, $value) use ($mockConstraintViolationBuilder) {
                return $mockConstraintViolationBuilder;
            };
        $this->calling($mockConstraintViolationBuilder)->addViolation = null;

        $mockExecutionContext =
            new \mock\Symfony\Component\Validator\Context\ExecutionContext(
                $mockValidator,
                '',
                $mockTranslator
            );
        $this->calling($mockExecutionContext)->buildViolation = function () use ($mockConstraintViolationBuilder) {
            return $mockConstraintViolationBuilder;
        };
        $mockExecutionContext->setConstraint(new \mock\Symfony\Component\Validator\Constraint());

        $this->mockGenerator->orphanize('__construct');
        $mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity();
        $mockUniqueEntity->identityFields = ['id'];

        $mockFakeRepository = new \mock\FakeRepository;
        $cityId    = hexdec(uniqid());
        $this->calling($mockFakeRepository)->getOneBy = function ($params) use ($cityId) {
            $city = new City();
            $city->setId($cityId);
            return $city;
        };
        $this->calling($mockFakeRepository)->getMetadata = $this->getMockMetadata();

        $this->mockGenerator->orphanize('__construct');
        $mockRepositoryFactory = new \mock\CCMBenchmark\TingBundle\Repository\RepositoryFactory();
        $this->calling($mockRepositoryFactory)->get = $mockFakeRepository;

        $mockUniqueEntityValidator =
            new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntityValidator($mockRepositoryFactory);
        $mockUniqueEntityValidator->initialize($mockExecutionContext);

        $this
            ->if($city = new City())
            ->and($city->setId(hexdec(uniqid())))
            ->and($city->setName('Luxiol'))
            ->and($mockUniqueEntityValidator->validate($city, $mockUniqueEntity))
            ->then
                ->mock($mockExecutionContext)
                    ->call('buildViolation')
                        ->withArguments($mockUniqueEntity->message)
                            ->once()
                ->mock($mockConstraintViolationBuilder)
                    ->call('setParameter')
                        ->withArguments('{{ data }}', '')
                            ->once();
        ;
    }

    public function testGetDefaultOption()
    {
        $this->mockGenerator->orphanize('__construct');

        $this
            ->if($mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity())
            ->then
                ->array($mockUniqueEntity->getDefaultOption())
                    ->isIdenticalTo(['fields', 'repository'])
        ;
    }

    private function getMockMetadata()
    {
        $this->mockGenerator()->orphanize('__construct');
        $this->mockGenerator()->shuntParentClassCalls();
        $metadata = new \mock\CCMBenchmark\Ting\Repository\Metadata();
        $this->calling($metadata)->getGetter = function ($fieldName) {
            return 'get' . $fieldName;
        };

        return $metadata;
    }
}
