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

namespace tests\units\CCMBenchmark\TingBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\InvalidOptionsException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

class UniqueEntity extends \atoum
{
    public function testGetTargetShouldReturnClassConstraint()
    {
        $this->mockGenerator->orphanize('__construct');

        $this
            ->if($mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity())
            ->then
                ->string($mockUniqueEntity->getTargets())
                    ->isIdenticalTo(Constraint::CLASS_CONSTRAINT)
        ;
    }

    public function testConstructWithNamedArguments()
    {
        $this
            ->if($uniqueEntity = new \CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity(
                fields: ['email'],
                repository: 'App\Repository\UserRepository',
                identityFields: ['id'],
                message: 'Already used',
                groups: ['create'],
                payload: 'payload'
            ))
            ->then
                ->array($uniqueEntity->fields)->isIdenticalTo(['email'])
                ->string($uniqueEntity->repository)->isIdenticalTo('App\Repository\UserRepository')
                ->array($uniqueEntity->identityFields)->isIdenticalTo(['id'])
                ->string($uniqueEntity->message)->isIdenticalTo('Already used')
                ->array($uniqueEntity->groups)->isIdenticalTo(['create'])
                ->string($uniqueEntity->payload)->isIdenticalTo('payload')
        ;
    }

    public function testConstructWithDefaults()
    {
        $this
            ->if($uniqueEntity = new \CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity(fields: 'email', repository: 'App\Repository\UserRepository'))
            ->then
                ->string($uniqueEntity->fields)->isIdenticalTo('email')
                ->array($uniqueEntity->identityFields)->isEmpty()
                ->string($uniqueEntity->message)->isIdenticalTo('Another entity exists for this data: {{ data }}')
                ->array($uniqueEntity->groups)->isIdenticalTo([Constraint::DEFAULT_GROUP])
        ;
    }

    public function testConstructWithOptionsArrayIsDeprecated()
    {
        // trigger_deprecation() silences its error, so it is captured with a dedicated handler
        $deprecations = [];
        set_error_handler(function (int $type, string $message) use (&$deprecations) {
            $deprecations[] = $message;
            return true;
        }, E_USER_DEPRECATED);
        try {
            $uniqueEntity = new \CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity(
                ['fields' => ['email'], 'repository' => 'App\Repository\UserRepository', 'message' => 'Already used'],
                groups: ['create']
            );
        } finally {
            restore_error_handler();
        }

        $this
            ->array($deprecations)
                ->isIdenticalTo(['Since xavierleune/ting_bundle 3.12: Passing an array of options to configure the "CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity" constraint is deprecated, use named arguments instead.'])
            ->array($uniqueEntity->fields)->isIdenticalTo(['email'])
            ->string($uniqueEntity->repository)->isIdenticalTo('App\Repository\UserRepository')
            ->string($uniqueEntity->message)->isIdenticalTo('Already used')
            ->array($uniqueEntity->groups)->isIdenticalTo(['create'])
        ;
    }

    public function testConstructWithUnknownOptionShouldThrow()
    {
        $this
            ->exception(function () {
                @new \CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity(['fields' => ['email'], 'repository' => 'R', 'unknown' => true]);
            })
                ->isInstanceOf(InvalidOptionsException::class)
        ;
    }

    public function testConstructWithoutRequiredOptionsShouldThrow()
    {
        $this
            ->exception(function () {
                new \CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity(fields: ['email']);
            })
                ->isInstanceOf(MissingOptionsException::class)
                ->message->contains('"repository"')
        ;
    }
}
