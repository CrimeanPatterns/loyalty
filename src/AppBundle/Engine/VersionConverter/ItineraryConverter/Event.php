<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Event as OutputEvent;
use AwardWallet\Common\Itineraries\PersonsCollection;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;


class Event extends Itinerary
{

    protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
        return new OutputEvent();
    }

    protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
        /** @var \AwardWallet\Schema\Parser\Common\Event $parsed */
        /** @var OutputEvent $it */
        $it->address = Util::emptyAddress([$parsed->getAddress()]);
        if ($parsed->getAddress() && $geo = $extra->data->getGeo($parsed->getAddress()))
            $it->address = Util::address($parsed->getAddress(), $geo);
        $it->eventName = $parsed->getName();
        $it->eventType = $parsed->getType();
        $it->startDateTime = Util::date($parsed->getStartDate());
        $it->endDateTime = Util::date($parsed->getEndDate());
        $it->phone = $parsed->getPhone();
        $it->fax = $parsed->getFax();
        $it->guestCount = Util::intval($parsed->getGuestCount());
        if (count($parsed->getTravellers()) > 0) {
            $it->guests = new PersonsCollection($this->logger);
            foreach($parsed->getTravellers() as $pair) {
                $new = $it->guests->add();
                $new->fullName = $pair[0];
            }
        }
        return $it;
    }

}