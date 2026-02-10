<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Flight as OutputFlight;
use AwardWallet\Common\Itineraries\FlightPoint;
use AwardWallet\Common\Itineraries\FlightSegment as OutputSegment;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\SegmentsCollection;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\FlightSegment;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;

class Flight extends Itinerary
{

	protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
		return new OutputFlight();
	}

	protected function convertProviderDetails(ParsedItinerary $parsed, Extra $extra)
    {
        /** @var \AwardWallet\Schema\Parser\Common\Flight $parsed */
        $r = parent::convertProviderDetails($parsed, $extra);
        if (empty($r->confirmationNumber))
            foreach($parsed->getSegments() as $segment)
                if ($c = $segment->getConfirmation() ?? $segment->getCarrierConfirmation()) {
                    $r->confirmationNumber = $c;
                    break;
                }
        return $r;
    }

    protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
		/** @var \AwardWallet\Schema\Parser\Common\Flight $parsed */
		/** @var OutputFlight $it */
        if (count($parsed->getTravellers()) > 0) {
            $it->travelers = new PersonsCollection($this->logger);
            foreach($parsed->getTravellers() as $pair) {
                $new = $it->travelers->add();
                $new->fullName = $pair[0];
            }
        }
        if (count($parsed->getTicketNumbers()) > 0)
            $it->ticketNumbers = array_map(function($pair){return $pair[0];}, $parsed->getTicketNumbers());
        if (count($parsed->getSegments()) >0) {
            $it->segments = new SegmentsCollection($this->logger);
            foreach ($parsed->getSegments() as $segment)
                $it->segments[] = $this->convertSegment($segment, $extra);
        }
		return $it;
	}

	protected function convertSegment(FlightSegment $parsed, Extra $extra): OutputSegment
    {
		$r = new OutputSegment($this->logger);
        $r->departure = $this->convertPoint($parsed->getDepCode(), $parsed->getDepName(), $parsed->getDepAddress(), $parsed->getDepDate(), $parsed->getDepTerminal(), $extra);
        $r->arrival = $this->convertPoint($parsed->getArrCode(), $parsed->getArrName(), $parsed->getArrAddress(), $parsed->getArrDate(), $parsed->getArrTerminal(), $extra);
        if (count($parsed->getSeats()) > 0)
            $r->seats = array_values($parsed->getSeats());
        $r->flightNumber = $parsed->getFlightNumber();
        $r->airlineName = $this->convertAirline($parsed->getAirlineName(), $extra);
        if ($a = $this->convertAirline($parsed->getOperatedBy(), $extra))
            $r->operator = $a;
        elseif ($a = $this->convertAirline($parsed->getCarrierAirlineName(), $extra))
            $r->operator = $a;
        $r->aircraft = $this->convertAircraft($parsed->getAircraft(), $extra);
        $r->traveledMiles = $parsed->getMiles();
        $r->cabin = $parsed->getCabin();
        $r->bookingClass = $parsed->getBookingCode();
        $r->duration = $parsed->getDuration();
        $r->meal = implode(", ", array_unique($parsed->getMeals()));
        $r->smoking = $parsed->getSmoking();
        $r->stops = Util::intval($parsed->getStops());
        return $r;
	}

	protected function convertPoint($code, $name, $address, $date, $terminal, Extra $extra) : FlightPoint
    {
		$point = new FlightPoint($this->logger);
		$point->airportCode = $code;
		$point->name = $name;
		$point->localDateTime = Util::date($date);
		$point->terminal = $terminal;
		$point->address = Util::emptyAddress([$name, $address, $code]);
		foreach([$code, $address, $name] as $key) {
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

	protected function convertAirline($name, Extra $extra)
    {
		if (!$name)
			return null;
		if ($a = $extra->data->getAirline($name))
		    return $a->name;
		else
		    return $name;
	}

	protected function convertAircraft($name, Extra $extra)
    {
		if (!$name)
			return null;
		if ($a = $extra->data->getAircraft($name))
		    return $a->name;
		else
		    return $name;
	}
}