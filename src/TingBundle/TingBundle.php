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

namespace CCMBenchmark\TingBundle;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TingBundle extends Bundle
{

    const VERSION = '2.0.4';

    public function boot()
    {
        $metadataRepository = $this->container->get('ting.metadatarepository');

        $cacheFile = $this->container->getParameter('kernel.cache_dir') . '/' .
            $this->container->getParameter('ting.cache_file');

        $configurationResolver = $this->container->get(
            'ting.configuration_resolver',
            ContainerInterface::NULL_ON_INVALID_REFERENCE
        );

        if (file_exists($cacheFile)) {
            $repositories = include($cacheFile);
            foreach ($repositories as $alias => $repositoriesConf) {
                $options = $repositoriesConf['options'];
                if ($configurationResolver !== null) {
                    $options = $configurationResolver->resolveConf($alias, $options);
                }

                $metadataRepository->batchLoadMetadataFromCache(
                    $repositoriesConf['repositories'],
                    $options
                );
            }
        } else {
            foreach ($this->container->getParameter('ting.repositories') as $alias => $bundle) {
                $directory = $this->container->get('file_locator')->locate($bundle['directory']) . '/';

                if (isset($bundle['options']) === true) {
                    $options = $bundle['options'];
                } else {
                    $options = [];
                }
                if ($configurationResolver !== null) {
                    $options = $configurationResolver->resolveConf($alias, $options);
                }
                $metadataRepository->batchLoadMetadata($bundle['namespace'], $directory . $bundle['glob'], $options);
            }
        }

        $this->container->get('ting.connectionpool')->setConfig($this->container->getParameter('ting.connections'));
    }
}
