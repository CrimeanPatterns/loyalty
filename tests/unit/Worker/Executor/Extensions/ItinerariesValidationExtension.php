<?php

namespace Tests\Unit\Worker\Executor\Extensions;

use AwardWallet\ExtensionWorker\AccountOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\Tab;
use AwardWallet\Schema\Parser\Component\Master;

class ItinerariesValidationExtension extends \Tests\Unit\Worker\Executor\Extensions\LoggedInExtension implements ParseInterface
{

    public function parse(Tab $tab, Master $master, AccountOptions $accountOptions): void
    {
        $hotel = $master->add()->hotel();
        $hotel
            ->setAddress("Lenina 17")
            ->addConfirmationNumber('12345')
            // check in date greater than checkout date, will be discarded
            ->setCheckInDate(strtotime('2049-01-01'))
            ->setCheckOutDate(strtotime('2039-02-01'))
            ->setHotelName('Marriott')
        ;
        $master->createStatement()->setBalance(100);
    }
}