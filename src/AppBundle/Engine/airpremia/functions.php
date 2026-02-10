<?php

use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerAirpremia extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://www.airpremia.com/mypage/myInfo';

    private $customerNumber = null;
    private $firstName = null;
    private $lastName = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.airpremia.com/login');

        if (!$this->http->FindSingleNode('//div[@id = "fn_login"]')) {
            return $this->checkErrors();
        }

        $data = [
            'email'     => $this->AccountFields['Login'],
            'password'  => $this->AccountFields['Pass'],
            'autoLogin' => "Y",
        ];

        $headers = [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/json',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.airpremia.com/user/login', json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $result = $response->RESULT ?? null;

        switch ($result) {
            case "success":
                return $this->loginSuccessful();

            case "notUSER":
            case "loginFail":
                //Check your ID or password.
                throw new CheckException("Check your ID or password.", ACCOUNT_INVALID_PASSWORD);

            case "Login_Lock":
                //Your login has been restricted. Please try again in a moment.
                throw new CheckException("Your login has been restricted. Please try again in a moment.", ACCOUNT_LOCKOUT);

            default:
                $this->logger->error("[Error]: {$result}");

                return $this->checkErrors();
        }
    }

    public function Parse()
    {
        $userDataJson = $this->http->FindPreg("/'({\"agree_personal_collection_option.*)',\n\s*'/imu");
        $userData = $this->http->JsonLog($userDataJson, 3, true);

        if (!$userData) {
            $this->logger->error("Failed to parse user json data");

            return;
        }

        $this->customerNumber = $userData["customer_number"];
        $this->firstName = $userData['passport_first_name'];
        $this->lastName = $userData['passport_last_name'];

        // Name
        $fullName = $userData['first_name'] . " " . $userData['last_name'];
        $this->SetProperty('Name', beautifulName($fullName));
        // Membership Number
        $this->SetProperty('Number', $userData["program_number"]);
        // Balance - Points
        $balance = $this->http->FindSingleNode('//span[@id="showPointNum"]');
        $this->SetBalance($balance);
        // My member status
        $this->SetProperty('EliteLevel', $userData["grade"]);

        $couponsDataJson = $this->http->JsonLog($this->http->FindPreg("/var\s*couponList\s*=\s*'({\"myCouponList\".*)\s*';/imu"));
        // My vouchers
        $this->SetProperty('VouchersTotal', count($couponsDataJson->myCouponList));

        if (!empty($couponsDataJson->myCouponList)) {
            $this->sendNotification("refs #23078 -  Voucher detected // IZ");
        }

        $this->http->PostURL('https://www.airpremia.com/loyalty/QualifyingPointsPTD?memberId=' . $userData["program_number"], []);
        $promotionScoreResponse = $this->http->JsonLog();

        if (isset($promotionScoreResponse->data)) {
            // Promotion Score
            $this->SetProperty('PromotionScore', $promotionScoreResponse->data);
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        $upcoming = $this->getUpcomingItineraries();
        $previous = $this->getPastItineraries();

        $upcomingItinerariesIsPresent = $upcoming !== false;
        $previousItinerariesIsPresent = $previous !== false;

        if ($upcomingItinerariesIsPresent) {
            foreach ($upcoming as $itinerary) {
                $this->parseItinerary($itinerary);
            }
        }

        if ($previousItinerariesIsPresent && $this->ParsePastIts) {
            foreach ($previous as $itinerary) {
                $this->parseItinerary($itinerary);
            }
        }

        // check for the no its
        $seemsNoIts = !$upcomingItinerariesIsPresent && !$previousItinerariesIsPresent;

        $this->logger->debug('Upcoming itineraries is present: ' . (int) $upcomingItinerariesIsPresent);
        $this->logger->debug('Past itineraries is present: ' . (int) $previousItinerariesIsPresent);
        $this->logger->debug('ParsePastIts: ' . (int) $this->ParsePastIts);
        $this->logger->debug('Seems no itineraries: ' . (int) $seemsNoIts);

        if (!$upcomingItinerariesIsPresent && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        if ($seemsNoIts && $this->ParsePastIts && !$previousItinerariesIsPresent) {
            $this->itinerariesMaster->setNoItineraries(true);
        }
    }

    private function getItineraryEticketData($itinerary)
    {
        $this->logger->notice(__METHOD__);

        $data = [
            'eticketPnr'          => $itinerary->record_locator,
            'eticketLastName'     => $this->lastName,
            'eticketFirstName'    => $this->firstName,
            'eticketDomainCode'   => 'WWW',
            'eticketLocationCode' => 'WWW',
        ];

        $this->http->PostURL('https://www.airpremia.com/mypage/eticket', $data);
        $data = $this->http->FindPreg('/const\s*data\s*=\s*([^;]*)/');

        return $this->http->JsonLog($data);
    }

    private function getItineraryData($itinerary)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info($itinerary->record_locator, ['Header' => 3]);

        $data = "------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"type\"\r\n\r\napplication/json\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"method\"\r\n\r\nGET\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"uri\"\r\n\r\n/api/nsk/v2/booking/retrieve\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"param\"\r\n\r\n{\"RecordLocator\":\"{$itinerary->record_locator}\",\"LastName\":\"{$this->lastName}\",\"FirstName\":\"{$this->firstName}\"}\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P\r\nContent-Disposition: form-data; name=\"body\"\r\n\r\nucFE/uOEEv7wyE3kbWwBKw==\r\n------WebKitFormBoundaryb8PV3lvAMAd6aF2P--\r\n";
        $headers = [
            "Accept"       => 'application/json, text/plain, */*',
            "Content-Type" => 'multipart/form-data; boundary=----WebKitFormBoundaryb8PV3lvAMAd6aF2P',
            "x-context-id" => "h5GJakpYoE1542Tohesr_1713172459492",
        ];

        $this->http->PostURL("https://www.airpremia.com/pssapi/query", $data, $headers);

        $result = $this->http->JsonLog();
        $data = $this->http->JsonLog($result->data);

        return $data->data ?? null;
    }

    private function parseItinerary($itinerary)
    {
        $itineraryDataFull = $this->getItineraryData($itinerary);

        if (empty($itineraryDataFull)) {
            return;
        }

        $eticketData = $this->getItineraryEticketData($itinerary);

        $f = $this->itinerariesMaster->createFlight();

        $f->general()->confirmation($itineraryDataFull->recordLocator, "Booking Reference");

        $f->general()->date2($itineraryDataFull->info->createdDate);

        $f->setStatus($eticketData->bookingStatus);

        $f->issued()->confirmation($itineraryDataFull->recordLocator);

        $total = $this->http->FindPreg('/[\d,\.]+/', false, $eticketData->payments->totalAmount);
        $totalCurrency = $this->http->FindPreg('/[A-z]+/', false, $eticketData->payments->totalAmount);
        $f->price()->total(PriceHelper::parse($total, $totalCurrency));

        $f->price()->cost(PriceHelper::parse($eticketData->payments->fare, $eticketData->payments->fareCurrencyCode));
        $f->obtainPrice()->addFee('tax', PriceHelper::parse($eticketData->payments->tax, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('fuel surcharge', PriceHelper::parse($eticketData->payments->fuelSurcharge, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('optional service fee', PriceHelper::parse($eticketData->payments->optionalServiceFee, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('fee', PriceHelper::parse($eticketData->payments->fee, $eticketData->payments->currencyCode));
        $f->obtainPrice()->addFee('etc', PriceHelper::parse($eticketData->payments->etc, $eticketData->payments->currencyCode));

        $f->price()->currency($eticketData->payments->currencyCode);
        $f->price()->discount(PriceHelper::parse($eticketData->payments->discount, $eticketData->payments->currencyCode));
        $f->price()->spentAwards($eticketData->payments->point);

        foreach ($itineraryDataFull->journeys as $journey) {
            foreach ($journey->segments as $segment) {
                $segmentDataFull = $this->getSegmentDataFull($journey->designator->origin, $journey->designator->destination, $eticketData);

                $s = $f->addSegment();

                if (isset($segment->fares) && count($segment->fares) == 1) {
                    $fare = $segment->fares[0];
                    $s->extra()->bookingCode($fare->fareClassOfService);
                }

                if (isset($segment->fares) && count($segment->fares) != 1) {
                    $this->sendNotification("refs #23078 -  fares length != 1 // IZ");
                }

                $s->airline()->name($segment->identifier->carrierCode);
                $s->airline()->number($segment->identifier->identifier);

                $s->departure()->code($journey->designator->origin);
                $s->departure()->name($segmentDataFull->originStation);
                $s->departure()->terminal($segmentDataFull->originTerminal);
                $s->departure()->date2($journey->designator->departure);

                $s->arrival()->code($journey->designator->destination);
                $s->arrival()->name($segmentDataFull->destinationStation);
                $s->arrival()->terminal($segmentDataFull->destinationTerminal);
                $s->arrival()->date2($journey->designator->arrival);

                $s->extra()->aircraft($segmentDataFull->airCraftType);

                foreach ($segmentDataFull->passengers as $passenger) {
                    $f->addTraveller(beautifulName($passenger->name), true);
                    $s->extra()->seat($passenger->seatName);

                    if ((isset($passenger->passengerSpecial) && count($passenger->passengerSpecial) > 0) || (isset($passenger->specialReq) && count($passenger->specialReq) > 0)) {
                        $this->sendNotification("refs #23078 - need to check passenger data // IZ");
                    }
                }
                $s->extra()->duration($segmentDataFull->flyingTime);

                foreach ($segment->legs as $leg) {
                    if (isset($leg->legInfo->operatingCarrier)) {
                        $this->sendNotification("refs #23078 - need to check carrier data // IZ");
                    }
                }
            }

            /*
            $this->logger->debug('CHECKING FOR BOARDING PASS');

            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.airpremia.com/api/v1/checkin/pss/bookings/{$itineraryDataFull->recordLocator}/segments/{$journey->journeyKey}/boardingPasses");
            $this->http->RetryCount = 2;

            $this->http->JsonLog();

            if ($this->http->Response['code'] != 400) {
                $this->sendNotification("refs #23078 - need to check boarding pass // IZ");
            } else {
                $this->logger->debug('BOARDING PASS NOT FOUND');
            }
            */
        }
    }

    private function getSegmentDataFull($origin, $destination, $eticketData)
    {
        foreach ($eticketData->journeys as $journey) {
            if ($journey->origin == $origin && $journey->destination == $destination) {
                return $journey;
            }
        }
    }

    private function getPastItineraries()
    {
        $itineraries = [];

        $this->http->PostURL("https://www.airpremia.com/mypage/lastJourneyData?pageIndex=1&customerNumber=" . $this->customerNumber, null);
        $response = $this->http->JsonLog();

        $itineraries = array_merge($itineraries, $response->list);

        $totalRecords = $response->paginationInfo->totalRecordCount;

        if ($totalRecords === 0) {
            return false;
        }

        $totalPages = $response->paginationInfo->totalPageCount;

        if ($totalPages > 1) {
            $this->sendNotification("refs #23078 - need to check pagination on past itineraries // IZ");
        }

        for ($i = 1; $i < $totalPages; $i++) {
            $this->http->PostURL("https://www.airpremia.com/mypage/lastJourneyData?pageIndex={$i}&customerNumber=" . $this->customerNumber, null);
            $itineraries = array_merge($itineraries, $response->list);
        }

        return $itineraries;
    }

    private function getUpcomingItineraries()
    {
        $itineraries = [];

        $this->http->PostURL("https://www.airpremia.com/mypage/upcomingJourneyData?pageIndex=1&customerNumber=" . $this->customerNumber, null);
        $response = $this->http->JsonLog();

        $itineraries = array_merge($itineraries, $response->list);

        $totalRecords = $response->paginationInfo->totalRecordCount;

        if ($totalRecords === 0) {
            return false;
        }

        $totalPages = $response->paginationInfo->totalPageCount;

        if ($totalPages > 1) {
            $this->sendNotification("refs #23078 - need to check pagination on upcoming itineraries // IZ");
        }

        for ($i = 1; $i < $totalPages; $i++) {
            $this->http->PostURL("https://www.airpremia.com/mypage/upcomingJourneyData?pageIndex={$i}&customerNumber=" . $this->customerNumber, null);
            $itineraries = array_merge($itineraries, $response->list);
        }

        return $itineraries;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//strong[@id="loginUserName"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
