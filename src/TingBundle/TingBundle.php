<?php

namespace CCMBenchmark\TingBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class TingBundle extends Bundle
{

    public function boot()
    {
        $this->generateServiceCache();

        $metadataRepository = $this->container->get('ting_metadatarepository');

        foreach ($this->container->getParameter('ting.repositories') as $bundle) {
            $metadataRepository->batchLoadMetadata($bundle['namespace'], $bundle['directory']);
        }

        $this->container->get('ting_connectionpool')->setConfig($this->container->getParameter('ting.connections'));
        $this->container->get('ting_cache_memcached')->setConfig($this->container->getParameter('ting.memcached'));
    }


    protected function generateServiceCache()
    {
        $cache = new \CCMBenchmark\Ting\Cache\Memcached();
        $cache->setConnection(new \Memcached($cache->getPersistentId()));
        $this->container->set('ting_cache_memcached', $cache);
    }
}
