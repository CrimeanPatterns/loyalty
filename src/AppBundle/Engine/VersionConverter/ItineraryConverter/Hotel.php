<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\HotelReservation;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Itineraries\RoomsCollection;
use AwardWallet\Common\Itineraries\TotalPrice;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;


class Hotel extends Itinerary
{

	protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
		return new HotelReservation();
	}

	protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
		/** @var \AwardWallet\Schema\Parser\Common\Hotel $parsed */
		/** @var \AwardWallet\Common\Itineraries\HotelReservation $it */
		$it->hotelName = $parsed->getHotelName();
		$it->chainName = $parsed->getChainName();
        $it->checkInDate = Util::date($parsed->getCheckInDate());
        $it->checkOutDate = Util::date($parsed->getCheckOutDate());
        $key = $parsed->getAddress() ?? $parsed->getHotelName();
        $it->address = Util::emptyAddress([$parsed->getAddress()]);
        if ($key && $geo = $extra->data->getGeo($key))
            $it->address = Util::address($key, $geo);
		$it->phone = $parsed->getPhone();
		$it->fax = $parsed->getFax();
		if (count($parsed->getTravellers()) > 0) {
		    $it->guests = new PersonsCollection($this->logger);
		    foreach($parsed->getTravellers() as $pair) {
		        $new = $it->guests->add();
		        $new->fullName = $pair[0];
            }
        }
		$it->guestCount = Util::intval($parsed->getGuestCount());
		$it->kidsCount = Util::intval($parsed->getKidsCount());
		foreach($parsed->getRooms() as $room) {
		    if (!isset($it->rooms))
		        $it->rooms = new RoomsCollection($this->logger);
		    $new = $it->rooms->add();
		    $new->type = $room->getType();
		    $new->description = $room->getDescription();
        }
		$it->roomsCount = Util::intval($parsed->getRoomsCount());
		$it->cancellationPolicy = $parsed->getCancellation();
		return $it;
	}

	protected function convertPrice(ParsedItinerary $parsed)
    {
        /** @var \AwardWallet\Schema\Parser\Common\Hotel $parsed */
        $r = parent::convertPrice($parsed);
        if (null === $r)
            $r = new TotalPrice();
        foreach($parsed->getRooms() as $room)
            if (!empty($room->getRate()) || !empty($room->getRateType())) {
                $r->rate = $room->getRate();
                $r->rateType = $room->getRateType();
            }
        return $r;
    }
}