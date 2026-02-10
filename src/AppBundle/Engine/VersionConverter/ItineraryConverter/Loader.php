<?php


namespace AppBundle\VersionConverter\ItineraryConverter;

use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Schema\Parser\Common as Parsed;
use Psr\Log\LoggerInterface;

class Loader
{

    /**
     * @var Itinerary[] $converters
     */
    protected $converters;

    public function __construct(LoggerInterface $logger)
    {
        $this->converters = [
            Parsed\Flight::class => new Flight($logger),
            Parsed\Bus::class => new Bus($logger),
            Parsed\Train::class => new Train($logger),
            Parsed\Transfer::class => new Transfer($logger),
            Parsed\Hotel::class => new Hotel($logger),
            Parsed\Rental::class => new Rental($logger),
            Parsed\Cruise::class => new Cruise($logger),
            Parsed\Event::class => new Event($logger),
            Parsed\Ferry::class => new Ferry($logger),
        ];
    }

    public function convert(Parsed\Itinerary $parsed, Extra $extra)
    {
        return $this->converters[get_class($parsed)]->convert($parsed, $extra);
    }
}