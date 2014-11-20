<?php

namespace CCMBenchmark\TingBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
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

        // Adding optional service ting_driverlogger
        if ($container->hasParameter('ting_driverlogger_class') === true) {
            $definition = new Definition($container->getParameter('ting_driverlogger_class'));
            $definition->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $definition->addArgument(new Reference('debug.stopwatch', ContainerInterface::NULL_ON_INVALID_REFERENCE));
            $container->setDefinition('ting_driverlogger', $definition);

            $reference = new Reference('ting_driverlogger');

            // Add logger to connection Pool
            $definition = $container->getDefinition('ting_connectionpool');
            $definition->addArgument($reference);

            // Add logger to DataCollector
            $definition = $container->getDefinition('ting_data_collector');
            $definition->addMethodCall('setLogger', [$reference]);
        }

        $servers = $config['memcached']['servers'];
        $config['memcached']['servers'] = array_values($servers);

        $options = $config['memcached']['options'];
        $config['memcached']['options'] = [];

        $config['memcached']['persistentId'] = $config['memcached']['persistent_id'];
        unset($config['memcached']['persistent_id']);

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
