<?php


namespace CCMBenchmark\TingBundle\Repository;

use CCMBenchmark\TingBundle\ConfigurationResolver\ConfigurationResolverInterface;
use Doctrine\Common\Cache\Cache;
use Symfony\Component\HttpKernel\Config\FileLocator;

class RepositoryFactory extends \CCMBenchmark\Ting\Repository\RepositoryFactory
{
    public function loadMetadata(
        $cacheDir,
        $cacheFile,
        array $repositories,
        FileLocator $fileLocator,
        ConfigurationResolverInterface $configurationResolver = null
    ) {
        $cacheFile = $cacheDir . '/' . $cacheFile;

        if (file_exists($cacheFile)) {
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
    }
}
