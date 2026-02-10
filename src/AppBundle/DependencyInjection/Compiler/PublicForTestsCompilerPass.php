<?php

namespace AppBundle\DependencyInjection\Compiler;

use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class PublicForTestsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $containerBuilder): void
    {
        $containerBuilder->getDefinition("doctrine_mongodb.odm.default_document_manager")->setPublic(true);

//        foreach ($containerBuilder->getDefinitions() as $definition) {
//            $definition->setPublic(true);
//        }
//
//        foreach ($containerBuilder->getAliases() as $definition) {
//            $definition->setPublic(true);
//        }
    }

}