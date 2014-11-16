<?php

namespace CCMBenchmark\TingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Config\FileLocator;


class TingExtension extends Extension
{

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $container->setParameter('ting.repositories', $config['repositories']);
        $container->setParameter('ting.connections', $config['connections']);

        $servers = $config['memcached']['servers'];
        $config['memcached']['servers'] = array_values($servers);

        $options = $config['memcached']['options'];
        $config['memcached']['options'] = [];

        foreach ($options as $data) {
        	if (defined($data['key']) === true) {
        		$data['key'] = constant($data['key']);
        	}

        	if (defined($data['value']) === true) {
        		$data['value'] = constant($data['value']);
        	}

        	$config['memcached']['options'][$data['key']] = $data['value'];
        }

        $container->setParameter('ting.memcached', $config['memcached']);
    }
}
