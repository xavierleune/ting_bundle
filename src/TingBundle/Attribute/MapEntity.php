<?php
/***********************************************************************
 *
 * Ting Bundle - Symfony Bundle for Ting
 * ==========================================
 *
 * Copyright (C) 2025 CCM Benchmark Group. (http://www.ccmbenchmark.com)
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

namespace CCMBenchmark\TingBundle\Attribute;

use CCMBenchmark\TingBundle\ArgumentResolver\EntityValueResolver;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Indicates that a controller argument should receive an Entity.
 */
#[\Attribute(\Attribute::TARGET_PARAMETER)]
class MapEntity extends ValueResolver
{
    /**
     * @param class-string|null          $class         The entity class
     * @param string|null                $expr          An expression to fetch the entity using the {@see https://symfony.com/doc/current/components/expression_language.html ExpressionLanguage} syntax.
     *                                                  Any request attribute are available as a variable, and your entity repository in the 'repository' variable.
     * @param array<string, string>|null $mapping       Configures the properties and values to use with the getOneBy() method
     *                                                  The key is the route placeholder name and the value is the property name
     * @param string[]|null              $exclude       Configures the properties that should be used in the getOneBy() method by excluding
     *                                                  one or more properties so that not all are used
     * @param bool|null                  $stripNull     Whether to prevent null values from being used as parameters in the query (defaults to false)
     * @param string[]|string|null       $id            If an id option is configured and matches a route parameter, then the resolver will find by the primary key
     * @param bool                       $forcePrimary    If true, forces Ting to always use the primary / master to retrieve the object
     */
    public function __construct(
        public ?string $class = null,
        public ?string $expr = null,
        public ?array $mapping = null,
        public ?array $exclude = null,
        public ?bool $stripNull = null,
        public array|string|null $id = null,
        public bool $forcePrimary = false,
        bool $disabled = false,
        string $resolver = EntityValueResolver::class,
        public ?string $message = null,
    ) {
        parent::__construct($resolver, $disabled);
        $this->selfValidate();
    }

    public function withDefaults(self $defaults, ?string $class): static
    {
        $clone = clone $this;
        $clone->class ??= class_exists($class ?? '') ? $class : null;
        $clone->expr ??= $defaults->expr;
        $clone->mapping ??= $defaults->mapping;
        $clone->exclude ??= $defaults->exclude ?? [];
        $clone->stripNull ??= $defaults->stripNull ?? false;
        $clone->id ??= $defaults->id;
        $clone->forcePrimary ??= $defaults->forcePrimary ?? false;
        $clone->message ??= $defaults->message;

        $clone->selfValidate();

        return $clone;
    }

    private function selfValidate(): void
    {
        if (!$this->id) {
            return;
        }
        if ($this->mapping) {
            throw new \LogicException('The "id" and "mapping" options cannot be used together on #[MapEntity] attributes.');
        }
        if ($this->exclude) {
            throw new \LogicException('The "id" and "exclude" options cannot be used together on #[MapEntity] attributes.');
        }
        $this->mapping = [];
    }
}