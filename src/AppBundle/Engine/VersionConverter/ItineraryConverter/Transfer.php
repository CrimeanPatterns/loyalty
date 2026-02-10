<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Transportation as OutputTransfer;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment as OutputSegment;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\SegmentsCollection;
use AwardWallet\Common\Itineraries\Transport;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\TransferSegment;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;

class Transfer extends Itinerary
{

    protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
        return new OutputTransfer();
    }

    protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
        /** @var \AwardWallet\Schema\Parser\Common\Transfer $parsed */
        /** @var OutputTransfer $it */
        $it->type = 'transportation';
        if (count($parsed->getTravellers()) > 0) {
            $it->travelers = new PersonsCollection($this->logger);
            foreach($parsed->getTravellers() as $pair) {
                $new = $it->travelers->add();
                $new->fullName = $pair[0];
            }
        }
        if (count($parsed->getSegments()) >0) {
            $it->segments = new SegmentsCollection($this->logger);
            foreach ($parsed->getSegments() as $segment)
                $it->segments[] = $this->convertSegment($segment, $extra);
        }
        return $it;
    }

    protected function convertSegment(TransferSegment $parsed, Extra $extra): OutputSegment
    {
        $r = new OutputSegment($this->logger);
        $r->departure = $this->convertPoint($parsed->getDepCode(), $parsed->getDepName(), $parsed->getDepAddress(), $parsed->getDepDate(), $extra);
        $r->arrival = $this->convertPoint($parsed->getArrCode(), $parsed->getArrName(), $parsed->getArrAddress(), $parsed->getArrDate(), $extra);
        if (count($parsed->getSeats()) > 0)
            $r->seats = array_values($parsed->getSeats());
        $r->transport = new Transport($this->logger);
        $r->transport->type = 'transport';
        $r->transport->vehicleClass = $parsed->getCarModel();
        $r->transport->name = $parsed->getCarType();
        $r->traveledMiles = $parsed->getMiles();
        $r->cabin = $parsed->getCabin();
        $r->bookingClass = $parsed->getBookingCode();
        $r->duration = $parsed->getDuration();
        $r->meal = implode(", ", array_unique($parsed->getMeals()));
        $r->smoking = $parsed->getSmoking();
        $r->stops = Util::intval($parsed->getStops());
        return $r;
    }

    protected function convertPoint($code, $name, $address, $date, Extra $extra) : FlightPoint
    {
        $point = new FlightPoint($this->logger);
        $point->stationCode = $code;
        $point->name = $name;
        $point->localDateTime = Util::date($date);
        $point->address = Util::emptyAddress([$name, $address, $code]);
        foreach([$address, $name, $code] as $key) {
            if ($key && $geo = $extra->data->getGeo($key)) {
                $point->address = Util::address($key, $geo);
                if(empty($name) && !empty($geo->name)){
                    $point->name = $geo->name;
                }
                break;
            }
        }
        return $point;
    }

}