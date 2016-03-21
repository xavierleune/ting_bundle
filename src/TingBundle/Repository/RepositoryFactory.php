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

namespace CCMBenchmark\TingBundle\Repository;

use CCMBenchmark\TingBundle\ConfigurationResolver\ConfigurationResolverInterface;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\Config\FileLocator;

class RepositoryFactory extends \CCMBenchmark\Ting\Repository\RepositoryFactory
{
    private $metadataLoaded = false;

    /**
     * @param string $cacheDir
     * @param string $cacheFile
     * @param array $repositories
     * @param FileLocator $fileLocator
     * @param ConfigurationResolverInterface|null $configurationResolver
     */
    public function loadMetadata(
        $cacheDir,
        $cacheFile,
        array $repositories,
        FileLocator $fileLocator,
        ConfigurationResolverInterface $configurationResolver = null
    ) {
        if ($this->metadataLoaded === true) {
            return;
        }

        $cacheFile = $cacheDir . '/' . $cacheFile;

        if (file_exists($cacheFile) === true) {
            $repositories = include($cacheFile);
            foreach ($repositories as $alias => $repositoriesConf) {
                $options = $repositoriesConf['options'];
                if ($configurationResolver !== null) {
                    $options = $configurationResolver->resolveConf($alias, $options);
                }

                $this->metadataRepository->batchLoadMetadataFromCache(
                    $repositoriesConf['repositories'],
                    $options
                );
            }
        } else {
            foreach ($repositories as $alias => $bundle) {
                $directory = $fileLocator->locate($bundle['directory']) . '/';

                if (isset($bundle['options']) === true) {
                    $options = $bundle['options'];
                } else {
                    $options = [];
                }
                if ($configurationResolver !== null) {
                    $options = $configurationResolver->resolveConf($alias, $options);
                }
                $this->metadataRepository->batchLoadMetadata(
                    $bundle['namespace'],
                    $directory . $bundle['glob'],
                    $options
                );
            }
        }
        $this->metadataLoaded = true;
    }
}
