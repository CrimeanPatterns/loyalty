<?php


namespace Tests\Functional\RewardAvailability;


use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;

class RaHotelCest
{

    private $providerCode;
    private $request;

    public function _before(\FunctionalTester $I)
    {
        $this->providerCode = "test" . bin2hex(random_bytes(8));
        $I->createAwProvider(null, $this->providerCode);

        eval("namespace AwardWallet\\Engine\\{$this->providerCode}\\RewardAvailability;
        
        class HotelParser extends \\Tests\\Functional\\RewardAvailability\\RaHotelParser {}
        ");
    }

    public function testParse(\FunctionalTester $I)
    {
        $I->wantToTest('error when empty and no warning');
        $request = $this->getRequest();
        RaHotelParser::$checkState = RaHotelParser::noWarningEmpty;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('empty and warning');
        $request = $this->getRequest();
        RaHotelParser::$checkState = RaHotelParser::warningEmpty;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_WARNING, $response->getState());
        $hotels = $response->getHotelsToSerialize();
        $I->assertEmpty($hotels);

        $I->wantToTest('error with wrong miles');
        $request = $this->getRequest();
        RaHotelParser::$checkState = RaHotelParser::wrongPoints;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());

        $I->wantToTest('check no preview');
        $request = $this->getRequest();
        RaHotelParser::$checkState = RaHotelParser::preview;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertNull($response->getHotelsToSerialize()[0]->getPreview());
        $I->wantToTest('check with preview');
        $request = $this->getRequest();
        $request->setDownloadPreview(true);
        RaHotelParser::$checkState = RaHotelParser::preview;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertNotNull($response->getHotelsToSerialize()[0]->getPreview());
        $I->assertNull($response->getHotelsToSerialize()[0]->getOriginalCurrency());
        $I->assertNull($response->getHotelsToSerialize()[0]->getConversionRate());

        $I->wantToTest('check no cashPerNight');
        $request = $this->getRequest();
        $request->setDownloadPreview(true);
        RaHotelParser::$checkState = RaHotelParser::noCash;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_ENGINE_ERROR, $response->getState());
        $I->assertEmpty($response->getHotelsToSerialize());
// tmp
/*        $I->wantToTest('check with currency');
        $request = $this->getRequest();
        $request->setDownloadPreview(true);
        RaHotelParser::$checkState = RaHotelParser::currency;
        $response = $I->searchRaHotel($request);
        $I->assertEquals(ACCOUNT_CHECKED, $response->getState());
        $I->assertNotNull($response->getHotelsToSerialize()[0]->getPreview());
        $I->assertEquals('AUD', $response->getHotelsToSerialize()[0]->getOriginalCurrency());
        $I->assertNotNull($response->getHotelsToSerialize()[0]->getConversionRate());
        $I->assertNotEquals(1,$response->getHotelsToSerialize()[0]->getConversionRate());*/

    }

    private function getRequest():RaHotelRequest
    {
        $this->checkIn = date("Y-m-d", strtotime('+1 month', strtotime(date('Y-m-d'))));
        $this->checkOut = date("Y-m-d", strtotime('+1 day', strtotime($this->checkIn)));
        return (new RaHotelRequest())
            ->setProvider($this->providerCode)
            ->setCheckInDate(new \DateTime($this->checkIn))
            ->setCheckOutDate(new \DateTime($this->checkOut))
            ->setDestination('Philadelphia')
            ->setNumberOfAdults(2)
            ->setNumberOfKids(1)
            ->setNumberOfRooms(2)
            ->setPriority(7)
            ->setUserData('{requestId: "blah"}');
    }
}