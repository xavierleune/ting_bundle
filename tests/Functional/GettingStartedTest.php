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


namespace CCMBenchmark\TingBundle\Tests\Functional;

use CCMBenchmark\Ting\Repository\Metadata;
use CCMBenchmark\Ting\Repository\Repository;
use CCMBenchmark\TingBundle\Tests\Functional\App\Entity\City;
use CCMBenchmark\TingBundle\Tests\Functional\App\Kernel;
use CCMBenchmark\TingBundle\Tests\Functional\App\Repository\CityRepository;
use CCMBenchmark\TingBundle\Tests\Functional\App\Service\CityDirect;
use CCMBenchmark\TingBundle\Tests\Functional\App\Service\CityFinder;
use CCMBenchmark\TingBundle\Tests\Support\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Checks the setup described by the Symfony guide on a real kernel
 */
class GettingStartedTest extends TestCase
{
    private Kernel $kernel;
    private mixed $exceptionHandler;

    protected function setUp(): void
    {
        $this->exceptionHandler = $this->getExceptionHandler();
        $this->kernel = new Kernel('test', false);
        (new Filesystem())->remove($this->kernel->getCacheDir());
        $this->kernel->boot();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        (new Filesystem())->remove($this->kernel->getCacheDir());
        // Symfony 7.4.0 leaves the error handler it registers when the FrameworkBundle boots
        while ($this->getExceptionHandler() !== $this->exceptionHandler) {
            restore_exception_handler();
        }
        parent::tearDown();
    }

    private function getExceptionHandler(): mixed
    {
        $handler = set_exception_handler(null);
        restore_exception_handler();

        return $handler;
    }

    public function testRepositoryFactoryIsAutowiredAndMetadataComeFromAttributes(): void
    {
        $repository = $this->kernel->getContainer()->get(CityFinder::class)->getRepository();

        $this->assertInstanceOf(CityRepository::class, $repository);
        $metadata = $repository->getMetadata();
        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertSame(City::class, $metadata->getEntity());
        $this->assertSame('city', $metadata->getTable());
    }

    public function testMetadataCacheIsWarmedUp(): void
    {
        $container = $this->kernel->getContainer();
        $warmer = $container->get('ting.metadata_warmer');
        $cacheDir = $this->kernel->getCacheDir();

        $warmer->warmUp($cacheDir);

        $this->assertFileExists($cacheDir . '/' . $container->getParameter('ting.cache_file'));
    }

    public function testRepositoryCanBeInjectedWithTheTingServices(): void
    {
        $container = $this->kernel->getContainer();
        $repository = $container->get(CityDirect::class)->repository;

        $this->assertInstanceOf(CityRepository::class, $repository);
        // Not the cache.app pool that Symfony autowires for Symfony\Contracts\Cache\CacheInterface
        $this->assertSame($container->get('ting.cache'), (new \ReflectionProperty(Repository::class, 'cache'))->getValue($repository));
        $this->assertSame($container->get('ting.unitofwork'), (new \ReflectionProperty(Repository::class, 'unitOfWork'))->getValue($repository));
    }
}
