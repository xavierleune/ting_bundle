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

namespace CCMBenchmark\TingBundle\ArgumentResolver;

use CCMBenchmark\Ting\Exception;
use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Attribute\MapEntity;
use CCMBenchmark\TingBundle\Repository\RepositoryFactory;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Heavily inspired by https://github.com/symfony/symfony/blob/7.2/src/Symfony/Bridge/Doctrine/ArgumentResolver/EntityValueResolver.php
 */

final class EntityValueResolver implements ValueResolverInterface
{
    private MapEntity $defaults;
    public function __construct(
        private MetadataRepository  $metadataRepository,
        private RepositoryFactory   $repositoryFactory,
        private ?ExpressionLanguage $expressionLanguage = null,
        ?MapEntity           $defaults = null,
    ) {
        $this->defaults = $defaults ?? new MapEntity();
    }

    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        if (\is_object($request->attributes->get($argument->getName()))) {
            return [];
        }

        $options = $argument->getAttributes(MapEntity::class, ArgumentMetadata::IS_INSTANCEOF);
        $options = ($options[0] ?? $this->defaults)->withDefaults($this->defaults, $argument->getType());

        if (!$options->class || $options->disabled) {
            return [];
        }
        $repository = null;

        $this->metadataRepository->findMetadataForEntity($options->class, function (Metadata $metadata) use (&$repository) {
            // Metadata used for hydration only have no repository to read the entity
            $repositoryClass = $metadata->getRepository();
            if ($repositoryClass !== null) {
                $repository = $this->repositoryFactory->get($repositoryClass);
            }
        }, fn () => null);
        if ($repository === null) {
            return [];
        }
        
        $message = '';
        if (null !== $options->expr) {
            if (null === $object = $this->findViaExpression($repository, $request, $options)) {
                $message = \sprintf(' The expression "%s" returned null.', $options->expr);
            }
            // find by identifier?
        } elseif (false === $object = $this->find($repository, $request, $options, $argument)) {
            // find by criteria
            if (!$criteria = $this->getCriteria($request, $options, $argument)) {
                return [];
            }
            try {
                $object = $repository->getOneBy($criteria);
            } catch (Exception $e) {
                $object = null;
            }
        }

        if (null === $object && !$argument->isNullable()) {
            throw new NotFoundHttpException($options->message ?? (\sprintf('"%s" object not found by "%s".', $options->class, self::class).$message));
        }

        return [$object];
    }

    private function find(Repository $repository, Request $request, MapEntity $options, ArgumentMetadata $argument): false|object|null
    {
        if ($options->mapping || $options->exclude) {
            return false;
        }

        $id = $this->getIdentifier($request, $options, $argument);
        if (false === $id || null === $id) {
            return $id;
        }
        if (\is_array($id) && \in_array(null, $id, true)) {
            return null;
        }
        try {
            return $repository->get($id, $options->forcePrimary);
        } catch (Exception $e) {
            return null;
        }
    }

    private function getIdentifier(Request $request, MapEntity $options, ArgumentMetadata $argument): mixed
    {
        if (\is_array($options->id)) {
            $id = [];
            foreach ($options->id as $field) {
                // Convert "%s_uuid" to "foobar_uuid"
                if (str_contains($field, '%s')) {
                    $field = \sprintf($field, $argument->getName());
                }

                $id[$field] = $request->attributes->get($field);
            }

            return $id;
        }

        if ($options->id) {
            return $request->attributes->get($options->id) ?? ($options->stripNull ? false : null);
        }

        $name = $argument->getName();

        if ($request->attributes->has($name)) {
            if (\is_array($id = $request->attributes->get($name))) {
                return false;
            }

            foreach ($request->attributes->get('_route_mapping') ?? [] as $parameter => $attribute) {
                if ($name === $attribute) {
                    $options->mapping = [$name => $parameter];

                    return false;
                }
            }

            return $id ?? ($options->stripNull ? false : null);
        }

        if ($request->attributes->has('id')) {
            return $request->attributes->get('id') ?? ($options->stripNull ? false : null);
        }

        return false;
    }

    private function getCriteria(Request $request, MapEntity $options, ArgumentMetadata $argument): array
    {
        if (!($mapping = $options->mapping) && \is_array($criteria = $request->attributes->get($argument->getName()))) {
            foreach ($options->exclude as $exclude) {
                unset($criteria[$exclude]);
            }

            if ($options->stripNull) {
                $criteria = array_filter($criteria, static fn ($value) => null !== $value);
            }

            return $criteria;
        } elseif (null === $mapping) {
            throw new \RuntimeException("Auto-mapping is not supported for Ting entities. Declare the identifier using either the #[MapEntity] attribute or mapped route parameters.");
        }
        
        if ($mapping && array_is_list($mapping)) {
            $mapping = array_combine($mapping, $mapping);
        }

        foreach ($options->exclude as $exclude) {
            unset($mapping[$exclude]);
        }
        if (!$mapping) {
            return [];
        }
        
        $criteria = [];

        foreach ($mapping as $attribute => $field) {
            $criteria[$field] = $request->attributes->get($attribute);
        }

        if ($options->stripNull) {
            $criteria = array_filter($criteria, static fn ($value) => null !== $value);
        }

        return $criteria;
    }

    private function findViaExpression(Repository $repository, Request $request, MapEntity $options): object|iterable|null
    {
        if (!$this->expressionLanguage) {
            throw new \LogicException(\sprintf('You cannot use the "%s" if the ExpressionLanguage component is not available. Try running "composer require symfony/expression-language".', __CLASS__));
        }

        $variables = array_merge($request->attributes->all(), [
            'repository' => $repository,
            'request' => $request,
        ]);

        try {
            return $this->expressionLanguage->evaluate($options->expr, $variables);
        } catch (Exception $e) {
            return null;
        }
    }
}