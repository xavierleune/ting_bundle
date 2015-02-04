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

namespace CCMBenchmark\TingBundle\Cache;

use CCMBenchmark\Ting\Repository\MetadataCacheGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class MetadataWarmer implements CacheWarmerInterface
{
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        $metadataRepository = $this->container->get('ting.metadatarepository');

        $repositories = [];
        foreach ($this->container->getParameter('ting.repositories') as $bundle) {
            $directory = $this->container->get('file_locator')->locate($bundle['directory']) . '/';
             $repositories = array_merge(
                 $repositories,
                 $metadataRepository->batchLoadMetadata($bundle['namespace'], $directory . $bundle['glob'])
             );
        }

        $metadataCacheGenerator = new MetadataCacheGenerator(
            $cacheDir,
            $this->container->getParameter('ting.cache_file')
        );
        $metadataCacheGenerator->createCache($repositories);
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * Optional warmers can be ignored on certain conditions.
     *
     * A warmer should return true if the cache can be
     * generated incrementally and on-demand.
     *
     * @return bool    true if the warmer is optional, false otherwise
     */
    public function isOptional()
    {
        return true;
    }
}
