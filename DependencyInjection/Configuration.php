<?php


namespace LCV\ExceptionPackBundle\DependencyInjection;


use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('lcv_exception_pack');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('error_emails')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('from_email')->end()
                        ->scalarNode('to_email')->end()
                    ->end()
                ->end() // error_emails
            ->end()
        ;

        return $treeBuilder;
    }
}
