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


namespace CCMBenchmark\TingBundle\Tests\Functional\App;

use CCMBenchmark\TingBundle\TingBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

/**
 * A minimal application configured as the Symfony guide (docs/getting-started.md) explains
 */
class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TingBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/ting_bundle_functional/' . md5(__DIR__) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/ting_bundle_functional/' . md5(__DIR__) . '/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'cache' => ['pools' => ['cache.ting' => ['adapter' => 'cache.adapter.array']]],
        ]);
        $container->extension('ting', [
            'connections' => [
                'main' => [
                    'namespace' => 'CCMBenchmark\Ting\Driver\Mysqli',
                    'primary' => ['host' => 'localhost', 'user' => 'world', 'password' => 'world', 'port' => 3306],
                ],
            ],
            'cache_provider' => 'cache.ting',
        ]);

        // As config/services.yaml: the entities are not excluded, so that their #[Table] attribute is read
        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->load(__NAMESPACE__ . '\\', __DIR__ . '/*')
                ->exclude([__DIR__ . '/Kernel.php']);
        $container->services()->get(Service\CityFinder::class)->public();
        $container->services()->get(Service\CityDirect::class)->public();
    }
}
