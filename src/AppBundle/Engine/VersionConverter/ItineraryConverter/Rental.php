<?php


namespace AppBundle\VersionConverter\ItineraryConverter;


use AwardWallet\Common\Itineraries\Car;
use AwardWallet\Common\Itineraries\CarRental;
use AwardWallet\Common\Itineraries\CarRentalDiscountsCollection;
use AwardWallet\Common\Itineraries\CarRentalPoint;
use AwardWallet\Common\Itineraries\FeesCollection;
use AwardWallet\Common\Itineraries\Person;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Itineraries\Itinerary as OutputItinerary;
use AwardWallet\Schema\Parser\Common\Itinerary as ParsedItinerary;


class Rental extends Itinerary
{

    protected function initItinerary(ParsedItinerary $parsed, Extra $extra): OutputItinerary
    {
        return new CarRental();
    }

    protected function convertItinerary(ParsedItinerary $parsed, OutputItinerary $it, Extra $extra): OutputItinerary
    {
        /** @var \AwardWallet\Schema\Parser\Common\Rental $parsed */
        /** @var \AwardWallet\Common\Itineraries\CarRental $it */
        $it->pickup = $this->convertPoint(
            $parsed->getPickUpLocation(),
            $parsed->getPickUpDateTime(),
            $parsed->getPickUpOpeningHours(),
            $parsed->getPickUpPhone(),
            $parsed->getPickUpFax(),
            $extra
        );
        $it->dropoff = $this->convertPoint(
            $parsed->getDropOffLocation(),
            $parsed->getDropOffDateTime(),
            $parsed->getDropOffOpeningHours(),
            $parsed->getDropOffPhone(),
            $parsed->getDropOffFax(),
            $extra
        );
        if (count($parsed->getTravellers()) > 0) {
            $it->driver = new Person($this->logger);
            $it->driver->fullName = $parsed->getTravellers()[0][0];
        }
        $it->rentalCompany = $parsed->getCompany();
        if ($parsed->getCarType() || $parsed->getCarModel() || $parsed->getCarImageUrl()) {
            $it->car = new Car($this->logger);
            $it->car->type = $parsed->getCarType();
            $it->car->model = $parsed->getCarModel();
            $it->car->imageUrl = $parsed->getCarImageUrl();
        }
        if (count($parsed->getDiscounts()) > 0) {
            $it->discounts = new CarRentalDiscountsCollection($this->logger);
            foreach ($parsed->getDiscounts() as $pair) {
                $new = $it->discounts->add();
                $new->code = $pair[0];
                $new->name = $pair[1];
            }
        }
        if (count($parsed->getEquipment()) > 0) {
            $it->pricedEquipment = new FeesCollection($this->logger);
            foreach($parsed->getEquipment() as $pair) {
                $new = $it->pricedEquipment->add();
                $new->name = $pair[0];
                $new->charge = Util::floatval($pair[1]);
            }
        }
        return $it;
    }

    protected function convertPoint($loc, $date, $hours, $phone, $fax, Extra $extra)
    {
        $r = new CarRentalPoint($this->logger);
        $r->address = Util::emptyAddress([$loc]);
        if ($loc && ($geo = $extra->data->getGeo($loc)))
            $r->address = Util::address($loc, $geo);
        $r->localDateTime = Util::date($date);
        $r->openingHours = $hours !== null ? implode('|', $hours) : null;
        $r->phone = $phone;
        $r->fax = $fax;
        return $r;
    }

}