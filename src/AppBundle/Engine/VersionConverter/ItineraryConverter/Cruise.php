<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Cruise as OutputCruise;
use AwardWallet\Common\Itineraries\CruiseDetails;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment as OutputSegment;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\SegmentsCollection;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;

class Cruise extends Itinerary
{

    protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
        return new OutputCruise();
    }

    protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
        /** @var \AwardWallet\Schema\Parser\Common\Cruise $parsed */
        /** @var OutputCruise $it */
        if (count($parsed->getTravellers()) > 0) {
            $it->travelers = new PersonsCollection($this->logger);
            foreach($parsed->getTravellers() as $pair) {
                $new = $it->travelers->add();
                $new->fullName = $pair[0];
            }
        }
        if (count($parsed->getSegments()) >0) {
            $it->segments = new SegmentsCollection($this->logger);
            $depPort = $arrPort = $depDate = $arrDate = $depCode = $arrCode = null;
            foreach($parsed->getSegments() as $s) {
                if (($s->getAboard() || $s->getAshore()) && ($s->getName() && (strcasecmp($s->getName(), 'At Sea') !== 0) || $s->getCode())) {
                    $arrPort = $s->getName();
                    $arrCode = $s->getCode();
                    $arrDate = $s->getAshore();
                    if ($depPort && $arrPort && $depDate && $arrDate)
                        $it->segments[] = $this->convertSegment($depPort, $depDate, $depCode, $arrPort, $arrDate, $arrCode, $extra);
                    $depPort = $s->getName();
                    $depCode = $s->getCode();
                    $depDate = $s->getAboard();
                }
            }
        }
        $it->cruiseDetails = new CruiseDetails($this->logger);
        $it->cruiseDetails->room = $parsed->getRoom();
        $it->cruiseDetails->description = $parsed->getDescription();
        $it->cruiseDetails->class = $parsed->getClass();
        $it->cruiseDetails->shipCode = $parsed->getShipCode();
        $it->cruiseDetails->ship = $parsed->getShip();
        $it->cruiseDetails->deck = $parsed->getDeck();
        return $it;
    }

    protected function convertSegment($depPort, $depDate, $depCode, $arrPort, $arrDate, $arrCode, Extra $extra): OutputSegment
    {
        $r = new OutputSegment($this->logger);
        $r->departure = $this->convertPoint($depPort, $depCode, $depDate, $extra);
        $r->arrival = $this->convertPoint($arrPort, $arrCode, $arrDate, $extra);
        return $r;
    }

    protected function convertPoint($name, $code, $date, Extra $extra) : FlightPoint
    {
        $point = new FlightPoint($this->logger);
        $point->stationCode = $code;
        $point->name = $name;
        $point->localDateTime = Util::date($date);
        $point->address = Util::emptyAddress([$name, $code]);
        if ($geo = $extra->data->getGeo($name)) {
            $point->address = Util::address($name, $geo);
        }
        return $point;
    }

}