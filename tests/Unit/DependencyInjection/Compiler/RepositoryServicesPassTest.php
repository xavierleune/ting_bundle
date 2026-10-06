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


namespace CCMBenchmark\TingBundle\Tests\Unit\DependencyInjection\Compiler;

use CCMBenchmark\TingBundle\DependencyInjection\Compiler\RepositoryServicesPass;
use CCMBenchmark\TingBundle\Tests\Functional\App\Repository\CityRepository;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use tests\fixtures\SimpleRepository;

class RepositoryServicesPassTest extends TestCase
{
    public function testAutowiredRepositoryReceivesTheCacheOfTing(): void
    {
        $container = new ContainerBuilder();
        $definition = $container->register('repository', CityRepository::class)->setAutowired(true);

        (new RepositoryServicesPass())->process($container);

        $this->assertEquals(new Reference('ting.cache'), $definition->getArgument('$cache'));
    }

    public function testCacheGivenByTheApplicationIsKept(): void
    {
        $container = new ContainerBuilder();
        $named = $container->register('named', CityRepository::class)->setAutowired(true)
            ->setArgument('$cache', new Reference('app.cache'));
        $positional = $container->register('positional', CityRepository::class)->setAutowired(true)
            ->setArgument(4, new Reference('app.cache'));

        (new RepositoryServicesPass())->process($container);

        $this->assertEquals(['$cache' => new Reference('app.cache')], $named->getArguments());
        $this->assertEquals([4 => new Reference('app.cache')], $positional->getArguments());
    }

    public function testOtherServicesAreIgnored(): void
    {
        $container = new ContainerBuilder();
        // Constructor without $cache
        $overridden = $container->register('overridden', SimpleRepository::class)->setAutowired(true);
        $notAutowired = $container->register('not_autowired', CityRepository::class);
        $fromFactory = $container->register('from_factory', CityRepository::class)->setAutowired(true)
            ->setFactory([new Reference('ting'), 'get'])->setArguments([CityRepository::class]);
        $other = $container->register('other', \ArrayObject::class)->setAutowired(true);

        (new RepositoryServicesPass())->process($container);

        $this->assertSame([], $overridden->getArguments());
        $this->assertSame([], $notAutowired->getArguments());
        $this->assertSame([CityRepository::class], $fromFactory->getArguments());
        $this->assertSame([], $other->getArguments());
    }
}
