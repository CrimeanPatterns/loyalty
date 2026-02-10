<?php


namespace AppBundle\DependencyInjection\Compiler;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ItinerariesFilterPass implements CompilerPassInterface
{

    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if(!$container->has('aw.itineraries_filter')) {
            return;
        }

        $definition = $container->getDefinition('aw.itineraries_filter');
        $filters = $container->findTaggedServiceIds('aw.itinerary_filter');
        foreach ($filters as $id => $tags) {
            $definition->addMethodCall('addItineraryFilter', [new Reference($id)]);
        }

        $segmentFilters = $container->findTaggedServiceIds('aw.trip_segment_filter');

        foreach ($segmentFilters as $id => $tags) {
            $definition->addMethodCall('addSegmentFilter', [new Reference($id)]);
        }
    }
}