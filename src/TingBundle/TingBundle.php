<?php

namespace CCMBenchmark\TingBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TingBundle extends Bundle
{

    public function boot()
    {
        $metadataRepository = $this->container->get('ting_metadatarepository');

        foreach ($this->container->getParameter('ting.repositories') as $bundle) {
            $metadataRepository->batchLoadMetadata($bundle['namespace'], $bundle['directory']);
        }

        $this->container->get('ting_connectionpool')->setConfig($this->container->getParameter('ting.connections'));
    }
}
