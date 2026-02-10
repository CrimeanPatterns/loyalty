<?php

namespace AppBundle\VersionConverter\ItineraryConverter;

use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;


interface ConverterInterface
{

    public function convert(ParsedItinerary $parsed, Extra $extra);

}