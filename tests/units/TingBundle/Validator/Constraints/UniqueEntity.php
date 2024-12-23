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

use Symfony\Component\Validator\Constraint;

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

    public function testGetRequiredOptions()
    {
        $this->mockGenerator->orphanize('__construct');

        $this
            ->if($mockUniqueEntity = new \mock\CCMBenchmark\TingBundle\Validator\Constraints\UniqueEntity())
            ->then
                ->array($mockUniqueEntity->getRequiredOptions())
                    ->isIdenticalTo(['fields', 'repository'])
        ;
    }
}
