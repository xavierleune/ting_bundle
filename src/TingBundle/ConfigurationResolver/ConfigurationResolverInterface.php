<?php


namespace CCMBenchmark\TingBundle\ConfigurationResolver;


interface ConfigurationResolverInterface
{
    /**
     * @param string $alias The alias for the group of repositories in the configuration
     * @param array $configuration Configuration options
     * @return array
     */
    public function resolveConf($alias, array $configuration);
}
