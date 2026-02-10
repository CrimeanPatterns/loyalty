<?php

namespace AppBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PublicServicesCompilerPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        if (!$container->getParameter("public_services")) {
            return;
        }

        foreach ($container->getParameter("public_services_names") as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $definition->setPublic(true);
        }
    }
}
