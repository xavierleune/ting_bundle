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
        $this->generateServiceCache();
    }


    protected function generateServiceCache()
    {
        $cache = new \CCMBenchmark\Ting\Cache\Memcached();
        $cache->setConfig($this->container->getParameter('ting.memcached'));
        $cache->setConnection(new \Memcached($cache->getPersistentId()));
        $this->container->set('ting_cache_memcached', $cache);
    }
}
