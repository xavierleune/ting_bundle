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

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TingBundle extends Bundle
{

    public function boot()
    {
        $metadataRepository = $this->container->get('ting.metadatarepository');

        $cacheFile = $this->container->getParameter('kernel.cache_dir') . '/' .
            $this->container->getParameter('ting.cache_file');

        if (file_exists($cacheFile)) {
            $repositories = include($cacheFile);
            $metadataRepository->batchLoadMetadataFromCache($repositories);
        } else {
            foreach ($this->container->getParameter('ting.repositories') as $bundle) {
                $directory = $this->container->get('file_locator')->locate($bundle['directory']) . '/';
                $metadataRepository->batchLoadMetadata($bundle['namespace'], $directory . $bundle['glob']);
            }
        }

        $this->container->get('ting.connectionpool')->setConfig($this->container->getParameter('ting.connections'));
    }
}
