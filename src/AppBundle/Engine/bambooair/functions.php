<?php

class TAccountCheckerBambooair extends TAccountChecker
{
    use PriceTools;

    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/json',
    ];
    private ?string $pAuth;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->http->GetURL('https://www.bambooairways.com/vn/en/bbc/login?redirectUrl=https://www.bambooairways.com/vn/en/bbc/flight-histories');

        $formURL = $this->http->FindPreg("#url: \"(https://www.bambooairways.com(?::443|)/en/bbc/login\?p_p_id=bav_web_portal_login_page_[^\"]+)#");

        if (!$this->http->ParseForm('loginForm') || !$formURL) {
            return $this->checkErrors();
        }

        // function callAjaxLogin(formData)
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue($this->http->FindSingleNode('//input[@id = "login-username"]/@name'), $this->AccountFields['Login']);
        $this->http->SetInputValue($this->http->FindSingleNode('//input[@id = "login-password"]/@name'), $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $headers = [
            "Accept"           => "*/*",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }

        $json = $this->http->JsonLog();
        $message = $json->message ?? null;

        if ($message == 'login-call-api-success') {
            return $this->loginSuccessful();
        }

        if (
            $message == 'invalid-message-error-An-error-has-occurred'
            || $message == 'login-call-api-fail'
        ) {
            throw new CheckException('Invalid Username or Password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->DebugInfo = $message;

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[contains(@class, "title-text")]/following-sibling::span')));
        // Membership Number
        $this->SetProperty('LoyaltyNumber', $this->http->FindSingleNode('//span[contains(text(), "Membership Number")]/following-sibling::span'));
        // Balance - )
        $this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "Current Bonus Points")]/following-sibling::span'));
        // Current Tier
        $this->SetProperty('CurrentTier', $this->http->FindSingleNode('//span[contains(text(), "current tier")]/following-sibling::span'));
        /*
        // Total Bonus Point Accured
        $this->SetProperty('TotalPointAccured', (int) $json->object->accountSummery->bonusPointDetails->totalBonusPointAccural);
        // Qualifying Points
        $this->SetProperty('QualifyingPoints', (int) $json->object->accountSummery->qualifyingPointsDetails->currentPoints);

        if ($json->object->accountSummery->currentTier == 'First') {
            // Points to Remain Tier
            $this->SetProperty('PointsToRemainTier', (int) $json->object->accountSummery->qualifyingPointsDetails->toMatnCrtTrPntLeftOut * -1);
            // Flights to Remain Tier
            $this->SetProperty('FlightsToRemainTier', (int) $json->object->accountSummery->qualifyingPointsDetails->toMatnCrtTrbissFlghtsLeftOut);
        } else {
            // Points to Next Tier
            $this->SetProperty('PointsToNextTier', (int) $json->object->accountSummery->qualifyingPointsDetails->qualifyingPointLeftOut);
            // Flights to Next Tier
            $this->SetProperty('FlightsToNextTier', (int) $json->object->accountSummery->qualifyingPointsDetails->businessFlightsLeftOut);
        }

        // Expiry Bonus Points  // refs #20464
        $this->SetProperty('ExpiryBonusPoints', (int) $json->object->accountSummery->bonusPointDetails->bonusPointExpiryThisMonth);

        if ($json->object->accountSummery->bonusPointDetails->bonusPointExpiryThisMonth > 0) {
            $this->SetExpirationDate(strtotime("last day of this month"));
        }

        // Expiration Date (Tier Expiration Date)
        if (!empty($json->object->accountSummery->currentTierExpirationDate)) {
            $this->SetProperty('TierExpiration', str_replace('-', ' ', $json->object->accountSummery->currentTierExpirationDate));
        }
        */
    }

    public function ParseItineraries(): array
    {
        $this->http->GetURL('https://www.bambooairways.com/vn/en/bbc/flight-histories');

        $headers = [
            "Accept"           => "*/*",
            "X-Requested-With" => "XMLHttpRequest",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            'Origin'           => 'https://www.bambooairways.com',
            'Referer'           => 'https://www.bambooairways.com/vn/en/bbc/flight-histories'
        ];
        $end = date('d-M-Y 23:59:59', strtotime('+6 months'));
        $start = date('d-M-Y 00:00:00');
        $data = [
            '_com_bav_bbc_flights_history_BavBbcFlightsHistoryPortlet_membershipNumber' => $this->Properties['LoyaltyNumber'],
            '_com_bav_bbc_flights_history_BavBbcFlightsHistoryPortlet_endDate'          => $end,
            '_com_bav_bbc_flights_history_BavBbcFlightsHistoryPortlet_startDate'        => $start,
        ];
        $this->http->PostURL("https://www.bambooairways.com/en/bbc/flight-histories?p_p_id=com_bav_bbc_flights_history_BavBbcFlightsHistoryPortlet&p_p_lifecycle=1&p_p_state=normal&p_p_mode=view&_com_bav_bbc_flights_history_BavBbcFlightsHistoryPortlet_javax.portlet.action=processGetFlightHistories&p_auth=$this->pAuth",
            $data, $headers);

        $response = $this->http->JsonLog();

        if (!empty($response->data)) {
            $this->sendNotification('check upcoming it / MI');
        }

        /*
        $this->http->GetURL('https://www.bambooairways.com/reservation/ibe/modify');

        if (!$this->http->ParseForm('retrieveItineraryForm')) {
            return [];
        }
        $form = $this->http->Form;
        $formUrl = $this->http->FormURL;

        $headers = $this->headers + [
            'Authorization' => 'bearer ' . $this->State['access_token'],
        ];
        $this->http->GetURL('https://bambooclub.bambooairways.com/services/bamboo-club/account/member/retrieveUpcomingFlightDetails',
            $headers);

        if ($this->http->FindPreg('/"flightLists":\[\],"/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        // provider bug fix
        if ($this->http->Response['code'] == 500 && empty($this->http->Response['body'])) {
            return [];
        }

        $response = $this->http->JsonLog();
        $locators = [];

        foreach ($response->object->flightLists as $trip) {
            if (in_array($trip->pnrNo, $locators)) {
                continue;
            } else {
                $locators[] = $trip->pnrNo;
            }
            $this->http->Form = $form;
            $this->http->FormURL = $formUrl;
            $this->http->Form['infants'] = 0;
            $this->http->Form['children'] = 0;
            $this->http->Form['_eventId'] = 'View';
            $this->http->Form['lastName'] = beautifulName($this->State['lastName']);
            $this->http->Form['confirmationCode'] = $trip->pnrNo;
            $this->http->RetryCount = 0;
            $this->http->PostForm();
            $this->http->RetryCount = 2;
            $this->parseItinerary();
        }
        */

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.bambooairways.com/vn/en/');
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//div[@id = "logout-button"]')) {
            // &p_auth=Z7jZ3IZt
            $this->pAuth = $this->http->FindPreg('/&p_auth=(\w+)/', false, $this->http->FormURL);
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 30,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://digital.bambooairways.com/book/manage-booking/retrieve?lang=en";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->RetryCount = 0;
        $this->http->LogHeaders = true;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $headers = [
            'Accept' => 'application/json',
            'Origin' => 'https://digital.bambooairways.com'
        ];
        $data = [
            'client_id' => 'JCuICEBLAGSYPTJWGS6l5yRgU7TbZHCL',
            'client_secret' => 'WQa9LaelXWzAw20j',
            'grant_type' => 'client_credentials',
        ];
        $this->http->PostURL("https://api-des.bambooairways.com/v1/security/oauth2/token", $data, $headers);
        $response = $this->http->JsonLog();
        if (!isset($response->token_type))
            return null;

        /*if (!$this->http->ParseForm('manageBookingForm')) {
            return null;
        }

        $data = [
            'lastName' => strtoupper($arFields['LastName']) ,
            'recLoc' => strtoupper($arFields['ConfNo']),
            'lightLogin' => '',
        ];
        $this->http->PostURL('https://digital.bambooairways.com/book/manage-booking/retrieve?lang=en', $data);*/

        $headers = [
            'Authorization' => "$response->token_type $response->access_token",
            'x-d-token' => '3:4ExgpAk/CChjApLXnpgffQ==:0K6zlw1wa+SBXO1m+VmcJ59twqevxOiDla9+pN+8Wx/N0XuD59xO55viDYq5zvxt5S1pVaB0mh8yu2ZFZBjaKx+w779nkXfzAbPjzrtHRVi1QNnZYWvyeD35ipOwPL4pFnXti2gAlCnROIi8UTNqTfCiG2Rj23G/VwBG6a5KIMb33i5GehdNNqQufyVcOmfhymJjCHFH8y1ipc57N1hYMLrRN9m6nwxvlrf08MFVLl9PLaEXWj/9xPue452ekwb0ar4kBOIRt8nQ2Lc83CMEJNPod/IVdJnSvjSSvBHNwy3deH8CClAZMx4NuNi6OBwQR3HMKulDSzU3QhQzjOL9NvI94shi24Dt+oYVmIGWM3bINF8YGqxQnDmPgTKLpLzA7CL1AcdRTRiINBcM/hm6hlkuGJj2AI2uUdnbzv0ZLaCb9uCgmxwpMMXnUcOqm0087YH/a559Q/Wlj8paSkfxdXwKq50HF2lo6uyeJiD+U/B9R1jZE4+AfW8GA0Bwrl4q9kYOSnzoL9MD9sr3vRe3Vnv0o0He8qbY4tYppa8epanW8Rs18nBHhv0NnRC1oI8ZLwjBKIQ8AIvjb6QN+UF+h0XiS+fdU6qT6upc79yJdM2Vxf5pP8MxvKBfBhBkzBDelVVxlvTo7LrqaUe+HDONTGwllP8ZJ/vF0fDbJ/7Dgj0JX96O7GISceGNJMQJkIay:nxxfgVkPvlbSAj/LGqpaBLg/63HysWTyBz04SNhvmaA=',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Origin' => 'https://digital.bambooairways.com'
        ];
        $data = [
            'lastName' => strtoupper($arFields['LastName']) ,
            'recLoc' => strtoupper($arFields['ConfNo']),
        ];
        $this->http->GetURL("https://api-des.bambooairways.com/v2/purchase/orders/{$data['recLoc']}?lastName={$data['lastName']}&showOrderEligibilities=true", $headers);
        $response = $this->http->JsonLog();
        $this->parseItineraryJson($response);
        $this->http->RetryCount = 2;

        return null;
    }

    private function parseItineraryJson($d)
    {
        $this->logger->notice(__METHOD__);
        $confNo = $d->data->id;
        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($confNo, 'Booking reference');

        foreach ($d->data->travelers as $traveler) {
            foreach ($traveler->names as $names) {
                $f->general()->traveller("$names->firstName $names->lastName");
            }
        }
        foreach ($d->data->air->bounds as $bound) {
            foreach ($bound->flights as $flight) {
                if (isset($d->dictionaries->flight->{$flight->id})) {
                    $seg = $d->dictionaries->flight->{$flight->id};
                    $s = $f->addSegment();
                    $s->airline()->name($seg->operatingAirlineCode);
                    $s->airline()->number($seg->operatingAirlineFlightNumber);
                    $s->departure()->code($seg->departure->locationCode);
                    $s->departure()->date2($this->http->FindPreg('/(.+?T\d+:\d+):\d+/', false, $seg->departure->dateTime));
                    $s->departure()->terminal($seg->departure->terminal);
                    $s->arrival()->code($seg->arrival->locationCode);
                    $s->arrival()->date2($this->http->FindPreg('/(.+?T\d+:\d+):\d+/', false, $seg->arrival->dateTime));
                    $s->arrival()->terminal($seg->arrival->terminal);

                    $s->extra()->aircraft($seg->aircraftCode);
                    if ($seg->meals->mealCodes[0] != 'N') {
                        if (isset($d->dictionaries->flight->meal->{$seg->meals->mealCodes[0]})) {
                            $s->extra()->meal($d->dictionaries->flight->meal->{$seg->meals->mealCodes[0]});
                        }
                    }
                    $s->extra()->bookingCode($seg->meals->bookingClass);

                    $hours = floor($seg->duration / 60 / 60);
                    $minutes = ($seg->duration / 60) % 60;
                    $s->extra()->duration($hours > 0 ? sprintf('%01dh %01dmin', $hours, $minutes)
                        : sprintf('%02dmin', $minutes));

                    foreach ($d->data->seats as $seats) {
                        if ($flight->id == $seats->flightId) {
                            foreach ($seats->seatSelections as $seat) {
                                $s->extra()->seat($seat->seatNumber);
                            }
                        }
                    }
                }
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $confNo = $this->http->FindSingleNode("//span[contains(text(),'Booking number')]/strong");
        $this->logger->info('Parse Itinerary #' . $confNo, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($confNo, 'Booking number');
        $nodes = $this->http->XPath->query("//div[contains(@class,'single-flights')]");
        $this->logger->debug("Total nodes found " . $nodes->length);

        foreach ($nodes as $node) {
            $route = $this->http->FindSingleNode(".//div[contains(@class,'flight-date')]/strong/following-sibling::text()", $node, false, '/from (.+)/');
            $date = $this->http->FindSingleNode(".//div[contains(@class,'flight-date')]/strong", $node);
            $date = strtotime($date);

            if (!$date || !$route) {
                $this->logger->error('Invalid Date');

                return;
            }
            $route = str_replace(' to ', ' - ', $route);

            $s = $f->addSegment();
            $airline = $this->http->FindSingleNode(".//div[contains(@class,'time')]//div[contains(@class,'flight-information')]", $node);
            $s->airline()->name($this->http->FindPreg('/([A-Z]{2})\s*\d+/', false, $airline));
            $s->airline()->number($this->http->FindPreg('/[A-Z]{2}\s*(\d+)/', false, $airline));

            $depTime = $this->http->FindSingleNode("(.//div[contains(@class,'departure-time')]/text())[1]", $node);
            $depCode = $this->http->FindSingleNode(".//div[contains(@class,'departure-time')]/span", $node);
            $s->departure()->date(strtotime($depTime, $date));
            $s->departure()->code($depCode);

            $arrTime = $this->http->FindSingleNode("(.//div[contains(@class,'arrival-time')]/text())[1]", $node);
            $arrCode = $this->http->FindSingleNode(".//div[contains(@class,'arrival-time')]/span[contains(@class,'iata-code')]", $node, false, '/^([A-Z]{3})$/');
            $s->arrival()->date(strtotime($arrTime, $date));
            $s->arrival()->code($arrCode);

            $s->extra()->duration($this->http->FindSingleNode(".//div[contains(@class,'duration')]/span", $node));
            $s->extra()->cabin($this->http->FindSingleNode(".//div[contains(@class,'meta-information')]/span", $node));

            $seats = $this->http->FindNodes("//h5[contains(text(),'Seat reservations')]/following-sibling::div[1]//strong[contains(text(),'{$route}')]/ancestor::tr[1]/following-sibling::tr/td[2]/strong", null, '/^(\w{1,3})\s+/');
            $s->extra()->seats($seats);
        }
        $travellers = $this->http->FindNodes("//h3[contains(text(),'Passengers')]/following-sibling::div[1]//table[@id='guestTable']//td[contains(@class,'passengers-data')][1]",
            null, '/\d+\.[msritr]{2,4}\s+(.+)/i');

        if (!empty($travellers)) {
            $f->general()->travellers(array_map('beautifulName', $travellers));
        }

        $total = $this->http->FindSingleNode("//h3[contains(text(),'Payment Details')]/following-sibling::div[1]//table[@id='guestTable']//td[4]");

        if ($total) {
            $f->price()->total($this->http->FindPreg('/([\d.,]+)/', false, str_replace(',', '', $total)));
            $f->price()->currency($this->currency($total), false, true);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
