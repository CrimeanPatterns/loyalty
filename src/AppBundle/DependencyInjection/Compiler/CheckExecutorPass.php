<?php


namespace AppBundle\DependencyInjection\Compiler;


use AppBundle\Worker\CheckWorker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class CheckExecutorPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition(CheckWorker::class);
        $executors = $container->findTaggedServiceIds('aw.check_worker.executor');
        foreach ($executors as $id => $tags) {
            $definition->addMethodCall('addExecutor', [new Reference($id)]);
        }

    }
}