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
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('environment')->defaultValue('prod')
            ->end()
            ->children()
                ->scalarNode('contact_email')->defaultValue("")->end()
            ->end()
            ->children()
                ->arrayNode('error_emails')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('from_email')->defaultValue("")->end()
                        ->scalarNode('to_email')->defaultValue("")->end()
                    ->end()
                ->end() // error_emails
            ->end()
        ;

        return $treeBuilder;
    }
}
