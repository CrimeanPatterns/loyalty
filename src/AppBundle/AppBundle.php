<?php

namespace AppBundle;

use AppBundle\DependencyInjection\Compiler\CheckExecutorPass;
use AppBundle\DependencyInjection\Compiler\ItinerariesFilterPass;
use AppBundle\DependencyInjection\Compiler\PublicServicesCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class AppBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new ItinerariesFilterPass());
        $container->addCompilerPass(new CheckExecutorPass());
        $container->addCompilerPass(new PublicServicesCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
    }
}
