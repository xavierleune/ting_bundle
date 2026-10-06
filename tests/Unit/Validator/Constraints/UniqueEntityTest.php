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

use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class UniqueEntityTest extends TestCase
{
    // Partial mock without constructor, to run the real getTargets()
    #[AllowMockObjectsWithoutExpectations]
    public function testGetTargetShouldReturnClassConstraint(): void
    {
        $uniqueEntity = $this->getMockBuilder(UniqueEntity::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $this->assertSame(Constraint::CLASS_CONSTRAINT, $uniqueEntity->getTargets());
    }

    public function testConstructWithNamedArguments(): void
    {
        $uniqueEntity = new UniqueEntity(
            fields: ['email'],
            repository: 'App\Repository\UserRepository',
            identityFields: ['id'],
            message: 'Already used',
            groups: ['create'],
            payload: 'payload'
        );

        $this->assertSame(['email'], $uniqueEntity->fields);
        $this->assertSame('App\Repository\UserRepository', $uniqueEntity->repository);
        $this->assertSame(['id'], $uniqueEntity->identityFields);
        $this->assertSame('Already used', $uniqueEntity->message);
        $this->assertSame(['create'], $uniqueEntity->groups);
        $this->assertSame('payload', $uniqueEntity->payload);
    }

    public function testConstructWithDefaults(): void
    {
        $uniqueEntity = new UniqueEntity(fields: 'email', repository: 'App\Repository\UserRepository');

        $this->assertSame('email', $uniqueEntity->fields);
        $this->assertEmpty($uniqueEntity->identityFields);
        $this->assertSame('Another entity exists for this data: {{ data }}', $uniqueEntity->message);
        $this->assertSame([Constraint::DEFAULT_GROUP], $uniqueEntity->groups);
    }

    public function testConstructWithOptionsArrayShouldThrow(): void
    {
        $message = 'Passing an array of options to configure the "CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity" constraint is no longer supported since ting_bundle 4.0, use named arguments instead.';

        $this->assertThrows(ConstraintDefinitionException::class, function () {
            new UniqueEntity(['fields' => ['email'], 'repository' => 'App\Repository\UserRepository']);
        }, $message);
        $this->assertThrows(ConstraintDefinitionException::class, function () {
            new UniqueEntity(options: ['fields' => ['email'], 'repository' => 'App\Repository\UserRepository'], groups: ['create']);
        }, $message);
    }

    public function testConstructWithoutRequiredOptionsShouldThrow(): void
    {
        $exception = $this->assertThrows(MissingOptionsException::class, function () {
            new UniqueEntity(fields: ['email']);
        });
        $this->assertStringContainsString('"repository"', $exception->getMessage());
    }
}
