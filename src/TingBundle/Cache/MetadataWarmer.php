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

use CCMBenchmark\Ting\MetadataRepository;
use CCMBenchmark\Ting\Repository\MetadataCacheGenerator;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class MetadataWarmer implements CacheWarmerInterface
{
    /**
     * @var MetadataRepository
     */
    protected $metadataRepository;

    /**
     * @var FileLocator
     */
    protected $fileLocator;

    /**
     * @var array
     */
    protected $repositories;

    /**
     * MetadataWarmer constructor.
     *
     * @param MetadataRepository $metadataRepository
     * @param FileLocator        $fileLocator
     * @param array              $repositories
     * @param string             $cacheFile
     */
    public function __construct(
        MetadataRepository $metadataRepository,
        FileLocator $fileLocator,
        array $repositories,
        $cacheFile
    ) {
        $this->metadataRepository = $metadataRepository;
        $this->fileLocator        = $fileLocator;
        $this->repositories       = $repositories;
        $this->cacheFile          = (string) $cacheFile;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir): array
    {
        $repositories = [];
        foreach ($this->repositories as $key => $bundle) {
            $directory = $this->fileLocator->locate($bundle['directory']) . '/';

            if (isset($bundle['options']) === true) {
                $options = $bundle['options'];
            } else {
                $options = [];
            }

            $repositories[$key] = [
                'repositories' =>
                    $this->metadataRepository
                        ->batchLoadMetadata($bundle['namespace'], $directory . $bundle['glob'], $options),
                'options' => $options
            ];
        }

        $metadataCacheGenerator = new MetadataCacheGenerator(
            $cacheDir,
            $this->cacheFile
        );

        return [$metadataCacheGenerator->createCache($repositories)];
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
