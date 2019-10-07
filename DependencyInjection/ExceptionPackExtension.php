<?php


namespace LCV\ExceptionPackBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class ExceptionPackExtension extends Extension
{

    public function getAlias()
    {
        return 'lcv_exception_pack';
    }

    public function load(array $configs, ContainerBuilder $container)
    {
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__.'/../Resources/config')
        );
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $definition = $container->getDefinition('lcv.exception_listener');
        $definition->replaceArgument(3, $config['error_emails']['enabled']);
        $definition->replaceArgument(4, $config['error_emails']['from_email']);
        $definition->replaceArgument(5, $config['error_emails']['to_email']);

    }
}
