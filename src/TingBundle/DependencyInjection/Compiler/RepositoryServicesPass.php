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


namespace CCMBenchmark\TingBundle\DependencyInjection\Compiler;

use CCMBenchmark\Ting\Cache\CacheInterface as TingCacheInterface;
use CCMBenchmark\Ting\Repository\Repository;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Repositories declared as autowired services receive the cache of Ting (ting.cache): autowiring would give them the
 * cache.app pool aliased to Symfony's CacheInterface
 */
class RepositoryServicesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $definition) {
            if ($definition->isAutowired() === false || $definition->isAbstract() || $definition->getFactory() !== null) {
                continue;
            }
            $class = $container->getParameterBag()->resolveValue($definition->getClass());
            if (\is_string($class) === false) {
                continue;
            }
            $reflection = $container->getReflectionClass($class, false);
            if ($reflection === null || $reflection->isSubclassOf(Repository::class) === false) {
                continue;
            }

            foreach ($reflection->getConstructor()?->getParameters() ?? [] as $position => $parameter) {
                $type = $parameter->getType();
                if (
                    $parameter->getName() !== 'cache'
                    || !$type instanceof \ReflectionNamedType
                    || \in_array($type->getName(), [CacheInterface::class, TingCacheInterface::class], true) === false
                ) {
                    continue;
                }
                $arguments = $definition->getArguments();
                if (\array_key_exists('$cache', $arguments) === false && \array_key_exists($position, $arguments) === false) {
                    $definition->setArgument('$cache', new Reference('ting.cache'));
                }
            }
        }
    }
}
