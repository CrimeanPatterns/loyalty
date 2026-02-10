<?php

class TAccountCheckerCayman extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.caymanairways.com/login';
        //$arg['SuccessURL'] = 'https://www.caymanairways.com/sir-turtle-club-the-exclusive-travel-experience';
        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.caymanairways.com/login');

        if (!$this->http->ParseForm('loginForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('memberNo', $this->AccountFields['Login']);
        $this->http->SetInputValue('memberPassword', $this->AccountFields['Pass']);
        $this->http->SetInputValue('loginRemember', 'on');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our website is currently down for maintenance as we update the system.')]")) {
            throw new CheckException("Our website is currently down for maintenance as we update the system. The website will be back online shortly.", ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($message = $this->http->FindSingleNode("
                //h1[contains(text(), 'Internal Server Error')]
                | //h1[contains(text(), 'Resource Limit Is Reached')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Successful access
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Invalid login. Please check your credentials and try again
        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'Invalid login. Please check your credentials and try again')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Something went wrong. Please try again later.
        if ($message = $this->http->FindSingleNode("
                //div[contains(text(), 'Something went wrong. Please try again later.')]
            ")
        ) {
            if ($this->attempt > 1) {
                $this->sendNotification('failed login // MI');
            }

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $this->sendNotification('success login // MI');

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[contains(text(), "Welcome ")]/span[1]')));
        //# Account Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode('//li/span[contains(text(), "Account Number:")]/following-sibling::span'));
        //# Balance - Mileage Balance
        $this->SetBalance($this->http->FindSingleNode('//li/span[contains(text(), "Mileage Balance:")]/following-sibling::span'));
        //# Claimed Mileage
        $this->SetProperty("ClaimedMileage", $this->http->FindSingleNode('//li/span[contains(text(), "Claimed Mileage:")]/following-sibling::span', null, false, '/[\d.,]+/'));
        //# Level
        $this->SetProperty("Level", $this->http->FindSingleNode('//li/span[contains(text(), "Membership Level:")]/following-sibling::span'));

        // Expiration Date // https://redmine.awardwallet.com/issues/10004#note-4
        $this->logger->info('Expiration Date', ['Header' => 3]);
        $nodes = $this->http->XPath->query("//th[contains(text(),'Bonus Mileage')]/ancestor::tr[1]/following-sibling::tr");
        $this->logger->debug("Total {$nodes->length} history rows were found");

        foreach ($nodes as $node) {
            $lastActivity = $this->http->FindSingleNode("td[1]", $node, false, '#\d+-\d+-\d{4}#');
            $totalMileage = str_replace(',', '', $this->http->FindSingleNode("td[5]", $node));
            $description = $this->http->FindSingleNode("td[2]", $node);
            $this->logger->debug("[{$lastActivity}]: {$description}, Total Mileage: {$totalMileage}");

            if (
                $totalMileage == 0
                || stristr($description, 'Adjustment')
            ) {
                $this->logger->debug("skip transaction");

                continue;
            }

            $expDate = strtotime(str_replace('-', '/', $lastActivity), false);

            if ($expDate) {
                // Last Activity
                $this->SetProperty("LastActivity", $lastActivity);
                // refs #10004
                $this->SetExpirationDate(strtotime('+2 year', $expDate));

                break;
            }
        }// foreach ($nodes as $node)
    }

    public function ParseItineraries()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://flights.caymanairways.com/dx/KXDX/#/home?tabIndex=1');
        $this->incapsula();
        $token = $this->http->FindPreg("/sabre\['access_token'\] = /");

        if (!$token) {
            return [];
        }
        $headers = [
            'Accept'             => '*/*',
            'Content-Type'       => 'application/json',
            'authorization'      => "Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=",
            'x-sabre-storefront' => 'KXDX',
        ];
        $data = '{"operationName":"signIn","variables":{"userId":"' . $this->AccountFields['Login'] . '","password":"' . $this->AccountFields['Pass'] . '"},"extensions":{},"query":"query signIn($userId: String, $password: String) {\n  signIn(userId: $userId, password: $password) {\n    originalResponse\n    __typename\n  }\n}\n"}';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://flights.caymanairways.com/api/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $responseAuth = $this->http->JsonLog();

        if (isset($responseAuth->data->signIn->originalResponse->status) && $responseAuth->data->signIn->originalResponse->status != 'SUCCESSFUL') {
            return [];
        }

        // All list
        $data = '{"operationName":"getProfileUpcomingTrips","variables":{},"extensions":{},"query":"query getProfileUpcomingTrips {\n  getProfileUpcomingTrips {\n    originalResponse\n    __typename\n  }\n}\n"}';

        // Check auth
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://flights.caymanairways.com/api/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/^\"trips":\[\]\$/')) {
            return $this->noItinerariesArr();
        }

        $trips = $response->data->getProfileUpcomingTrips->originalResponse->trips ?? [];
        $this->logger->debug("Total " . count($trips) . " itineraries were found");

        foreach ($trips as $trip) {
            $arFields = [
                'ConfNo'   => $trip->pnr,
                'LastName' => $responseAuth->data->signIn->originalResponse->result->user->personalDetails->lastName,
            ];
            $error = $this->CheckConfirmationNumberInternal($arFields, $it);

            if ($error) {
                $this->logger->error('Skipping itinerary: ' . $error);
            }
        }
        $this->http->RetryCount = 2;

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Caption"  => "First Name",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => false,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://flights.caymanairways.com/dx/KXDX/#/home?tabIndex=1';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->FilterHTML = false;

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->incapsula();
        $authToken = $this->http->FindPreg("/sabre\['access_token'\] =/");

        if (!$authToken) {
            $this->sendNotification('check retrieve // MI');

            return null;
        }

        $headers = [
            'Accept'             => '*/*',
            'Content-Type'       => 'application/json',
            'x-sabre-storefront' => 'KXDX',
            'Authorization'      => 'Bearer Basic anNvbl91c2VyOmpzb25fcGFzc3dvcmQ=',
        ];

        if (isset($arFields['FirstName'])) {
            $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $arFields['ConfNo'] . '","firstName":"' . $arFields['FirstName'] . '","lastName":"' . $arFields['LastName'] . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        } else {
            $data = '{"operationName":"getMYBTripDetails","variables":{"pnrQuery":{"pnr":"' . $arFields['ConfNo'] . '","lastName":"' . $arFields['LastName'] . '"}},"extensions":{},"query":"query getMYBTripDetails($pnrQuery: JSONObject!) {\n  getMYBTripDetails(pnrQuery: $pnrQuery) {\n    originalResponse\n    __typename\n  }\n  getStorefrontConfig {\n    privacySettings {\n      isEnabled\n      maskFrequentFlyerNumber\n      maskPhoneNumber\n      maskEmailAddress\n      maskDateOfBirth\n      maskTravelDocument\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        }

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://flights.caymanairways.com/api/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 2);

        if (isset($data->extensions->errors[0]->responseData->message)) {
            return $data->extensions->errors[0]->responseData->message;
        }

        if (!isset($data->data->getMYBTripDetails->originalResponse)) {
            return null;
        }
        $this->parseItinerary($data->data->getMYBTripDetails->originalResponse);

        return null;
    }

    private function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $incapsulaSrc = (
        $this->http->FindSingleNode("//script[contains(@src, '/_Incapsula_Resource?')]/@src") ?:
            $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src")
        );

        if (!$incapsulaSrc) {
            return false;
        }

        /** @var TAccountCheckerOman $selenium */
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
            $selenium->http->saveScreenshots = true;
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://flights.caymanairways.com/dx/KXDX/#/home?tabIndex=1");
            $this->savePageToLogs($selenium);

            if (
                $this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource")]')
                || $this->http->FindPreg('/Empty reply/', false, $this->http->Error)
            ) {
                sleep(5);
                $selenium->http->GetURL("https://flights.caymanairways.com/dx/KXDX/#/home?tabIndex=1");
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->pnr->reloc)) {
            return;
        }

        $this->logger->info('Parse Itinerary #' . $data->pnr->reloc, ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();

        $f->general()->confirmation($data->pnr->reloc, 'Record Locator');

        foreach ($data->pnr->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->passengerDetails->firstName} {$passenger->passengerDetails->lastName}"));

            foreach ($passenger->preferences->frequentFlyer as $frequentFlyer) {
                $f->program()->account("$frequentFlyer->airline-$frequentFlyer->number", false);
            }
        }
        $ticketNumbers = [];

        foreach ($data->pnr->travelPartsAdditionalDetails as $travel) {
            foreach ($travel->passengers as $passenger) {
                if (isset($passenger->eticketNumber)) {
                    $ticketNumbers[] = $passenger->eticketNumber;
                }
            }
        }

        if (!empty($ticketNumbers)) {
            $f->issued()->tickets(array_unique($ticketNumbers), false);
        }

        foreach ($data->pnr->itinerary->itineraryParts as $parts) {
            foreach ($parts->segments as $seg) {
                if ($seg->departure === $seg->arrival) {
                    $this->logger->error('Skip: duplicate date');

                    continue;
                }
                $s = $f->addSegment();
                $s->airline()->name($seg->flight->airlineCode);
                $s->airline()->number($seg->flight->flightNumber);

                $s->departure()->code($seg->origin);
                $s->departure()->date2($seg->departure);
                $s->arrival()->code($seg->destination);
                $s->arrival()->date2($seg->arrival);
                $s->extra()->cabin($seg->cabinClass);
                $s->extra()->bookingCode($seg->bookingClass);

                if (isset($seg->equipment)) {
                    $s->extra()->aircraft($seg->equipment);
                }

                if ($seg->duration) {
                    $h = floor($seg->duration / 60);
                    $m = $seg->duration % 60;
                    $s->extra()->duration("$h hr $m mins");
                }

                foreach ($data->pnr->travelPartsAdditionalDetails as $travel) {
                    if ($travel->travelPart->{'@ref'} == $seg->{'@id'}) {
                        foreach ($travel->passengers as $passenger) {
                            if (isset($passenger->seat->seatCode)) {
                                $s->extra()->seat($passenger->seat->seatCode, false);
                            }
                        }
                    }
                }
            }
        }

        if (count($data->pnr->priceBreakdown->price->alternatives) > 1) {
            $this->sendNotification('check price > 1 // MI');
        }
        $f->price()->total($data->pnr->priceBreakdown->price->alternatives[0][0]->amount);
        $f->price()->currency($data->pnr->priceBreakdown->price->alternatives[0][0]->currency);

        foreach ($data->pnr->priceBreakdown->subElements as $subElement) {
            if ($subElement->label == 'farePrice') {
                if ($subElement->price->alternatives[0][0]->currency == 'FFCURRENCY') {
                    $f->price()->spentAwards($subElement->price->alternatives[0][0]->amount . ' miles');
                } elseif ($subElement->price->alternatives[0][0]->currency == $f->getPrice()->getCurrencyCode()) {
                    $f->price()->cost($subElement->price->alternatives[0][0]->amount);
                }
            } elseif ($subElement->label == 'taxesPrice') {
                $f->price()->tax($subElement->price->alternatives[0][0]->amount);
            }
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
