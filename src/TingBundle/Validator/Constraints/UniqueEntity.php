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

namespace CCMBenchmark\TingBundle\Validator\Constraints;

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\MissingOptionsException;

/**
 * Checks that no other entity in the repository shares the values of the given fields.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueEntity extends Constraint
{
    public string $message = 'Another entity exists for this data: {{ data }}';

    public string $repository;

    /**
     * @var string|string[]
     */
    public array|string $fields = [];

    /**
     * @var string|string[]
     */
    public array|string $identityFields = [];

    /**
     * @param null                      $options        no longer supported since 4.0, throws when set
     * @param string|string[]|null      $fields         fields that must be unique together
     * @param string|null               $repository     repository class used to look for an existing entity
     * @param string|string[]|null      $identityFields fields identifying the validated entity itself (ignored when equal)
     * @param string[]|null             $groups
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        array|string|null $fields = null,
        ?string $repository = null,
        array|string|null $identityFields = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        if ($options !== null) {
            throw new ConstraintDefinitionException(sprintf('Passing an array of options to configure the "%s" constraint is no longer supported since ting_bundle 4.0, use named arguments instead.', static::class));
        }

        $missingOptions = array_keys(array_filter(['fields' => $fields, 'repository' => $repository], fn ($value) => $value === null));
        if ($missingOptions !== []) {
            throw new MissingOptionsException(sprintf('The options "%s" must be set for constraint "%s".', implode('", "', $missingOptions), static::class), $missingOptions);
        }

        parent::__construct(null, $groups, $payload);

        $this->fields = $fields;
        $this->repository = $repository;
        $this->identityFields = $identityFields ?? $this->identityFields;
        $this->message = $message ?? $this->message;
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
