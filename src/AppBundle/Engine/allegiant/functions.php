<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAllegiant extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const AIRPORT_CODE_REGEX = '/\(([A-Z]{3})\)/';
    private $customerId;
    private $headers = [
        'Accept'       => '*/*',
        'Content-Type' => 'application/json',
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . '/TAccountCheckerSeleniumAllegiant.php';

        return new TAccountCheckerSeleniumAllegiant();
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], 'allegiantVouchers'))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], '$%0.2f');
        }

        return parent::FormatBalance($fields, $properties);
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
        $this->http->GetURL('https://www.allegiantair.com/home');

        if (
            $this->http->currentUrl() != 'https://www.allegiantair.com/home'
            || $this->http->Response['code'] !== 200
        ) {
            return $this->checkErrors();
        }

        $this->http->GetURL('https://www.allegiantair.com/customer/ajax/login-register');
        $response = $this->http->JsonLog(null, 0);

        if (empty($response[1]->output)) {
            return $this->checkErrors();
        }
        $this->http->SetBody($response[1]->output);

        if (!$this->http->ParseForm('customer-modal-login-form')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('name', $this->AccountFields['Login']);
        $this->http->SetInputValue('pass', $this->AccountFields['Pass']);
        $this->http->SetInputValue('remember_me', 1);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The Allegiant website is currently unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Allegiant website is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/An error occurred while processing your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (!empty($response[1]->output)) {
            $this->http->SetBody($response[1]->output);

            if ($message = $this->http->FindSingleNode("//text()[contains(.,'Wrong email or password.')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if (!empty($response[1]->output))

        if (strpos($this->http->Response['body'], 'modalRedirect') !== false
            && strpos($this->http->Response['body'], '\/my-profile\/my-trips') !== false) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.allegiantair.com/user-info.php');
        $response = $this->http->JsonLog();

        if (!isset($response->customer_data->id, $response->customer_data->auth_token, $response->customer_data->firstName)) {
            return;
        }
        $this->customerId = $response->customer_data->id;
        $this->headers['Auth-Token'] = $response->customer_data->auth_token;

        // Name
        $this->SetProperty('Name', beautifulName("{$response->customer_data->firstName} {$response->customer_data->lastName}"));

        $data = '{"operationName":"pointsSummary","variables":{},"query":"query pointsSummary {\n  application(name: MYALLEGIANT) {\n    ... on MyAllegiant {\n      translations(language: enUS) {\n        points {\n          totalPoints\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  viewer {\n    id\n    loyalty {\n      status\n      bonus\n      expired\n      normal\n      purchased\n      redeemed\n      total\n      pointsValue\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://www.allegiantair.com/graphql", $data, $this->headers);

        if ($message = $this->http->FindSingleNode('
         //div[
         @class = "maintenance-message" 
         and contains(normalize-space(),"Good deals come to those who wait")
         and contains(normalize-space(),"The Allegiant website is currently unavailable.")
         ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $response = $this->http->JsonLog(null, 3, false, 'total');

        if (!isset($response->data->viewer->loyalty->total,
            $response->data->viewer->loyalty->pointsValue,
            $response->data->viewer->loyalty->redeemed)) {
            if (
                $this->AccountFields['Login'] == 'givehim6@hotmail.com' // AccountID: 5431947
                && $response->data->viewer->loyalty == null
                && !empty($this->Properties['Name'])
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }

        // Total Points
        if ($response->data->viewer->loyalty->total == 0 && $response->data->viewer->loyalty->pointsValue == 0
            && $response->data->viewer->loyalty->redeemed == 0 && $response->data->viewer->loyalty->status == 'PENDING') {
            $this->SetBalanceNA();
        } else {
            $this->SetBalance($response->data->viewer->loyalty->total);
            // Points worth
            $this->SetProperty('PointsWorth', '$' . $response->data->viewer->loyalty->pointsValue);
            // Redeemed so far
            $this->SetProperty('Redeemed', $response->data->viewer->loyalty->redeemed);
        }

        // Vouchers
        $data = '{"operationName":"vouchersTotals","variables":{},"query":"query vouchersTotals {\n  application(name: MYALLEGIANT) {\n    ... on MyAllegiant {\n      translations(language: enUS) {\n        vouchers {\n          totalVouchers\n          totalDollarsOff\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  viewer {\n    id\n    vouchers {\n      CRTotal\n      DOTotal\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://www.allegiantair.com/graphql", $data, $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'vouchers');

        // Total Vouchers
        if (isset($response->data->viewer->vouchers->CRTotal)) {
            $this->SetProperty('TotalVouchers', '$' . $response->data->viewer->vouchers->CRTotal);
        }
        // Total Dollars Off
        if (isset($response->data->viewer->vouchers->DOTotal)) {
            $this->SetProperty('TotalDollarsOff', '$' . $response->data->viewer->vouchers->DOTotal);
        }

        $data = '{"operationName":"vouchersHistory","variables":{},"query":"query vouchersHistory {\n  application(name: MYALLEGIANT) {\n    ... on MyAllegiant {\n      translations(language: enUS) {\n        vouchers {\n          emailedTo\n          dateIssued\n          travelByDate\n          issuedTo\n          voucher\n          amount\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  viewer {\n    id\n    vouchers {\n      list {\n        id\n        number\n        balance\n        code\n        expireDate\n        issueDate\n        issueTo\n        email\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL("https://www.allegiantair.com/graphql", $data, $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'list');

        if (!isset($response->data->viewer->vouchers->list) || !is_array($response->data->viewer->vouchers->list) || count($response->data->viewer->vouchers->list) == 0) {
            return;
        }
        $this->SetProperty('CombineSubAccounts', false);

        foreach ($response->data->viewer->vouchers->list as $voucher) {
            if (isset($voucher->balance, $voucher->expireDate, $voucher->number) && $voucher->balance > 0 && strtotime($voucher->expireDate)) {
                $this->AddSubAccount([
                    'Code'           => 'allegiantVouchers' . $voucher->number,
                    'DisplayName'    => 'Voucher #' . $voucher->number,
                    'Balance'        => $voucher->balance,
                    'ExpirationDate' => strtotime($voucher->expireDate),
                    'IssuedDate'     => $voucher->issueDate,
                    'IssuedTo'       => $voucher->issueTo,
                ]);
            }
        }
    }

    public function ParseItineraries()
    {
        $results = [];

        // Query Fligts List
        $data = '{"operationName":"orders","variables":{},"query":"query orders {\n  application(name: MYALLEGIANT) {\n    ... on MyAllegiant {\n      translations(language: enUS) {\n        ... on MyAllegiantTranslations {\n          errors {\n            confirmationCodeIncorrect\n            confirmationCodeAlreadyClaimed\n            itineraryWithoutTraveler\n            confirmationCodeForUnavailableFlight\n            unknownError\n            __typename\n          }\n          common {\n            genericRequiredMessage\n            __typename\n          }\n          trips {\n            title\n            welcomeMessage\n            noTripsWelcomeMessage\n            checkInButtonText\n            availableNowText\n            availabilityText\n            viewEditTripButtonText\n            viewTripButtonText\n            nextFlightTitle\n            firstParagraph\n            secondParagraph\n            thirdParagraph\n            confirmationNumber\n            canceledFlights\n            rescheduledFlights\n            flight\n            departs\n            flightStatus\n            itineraryNumberPlaceholder\n            itineraryButtonText\n            confirmationNumberValidationText\n            pendingFirstParagraph\n            pendingSecondParagraph\n            activeFareClubProgramText\n            expiredFareClubProgramText\n            discountDepartureProgramText\n            renewSubscriptionButtonText\n            cancelSubscriptionButtonText\n            successCancelSubscriptionTitle\n            successCancelSubscriptionText\n            successCancelSubscriptionButton\n            cancelSubscriptionTitle\n            cancelSubscriptionText\n            cancelSubscriptionNoButton\n            cancelSubscriptionYesButton\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  viewer {\n    firstName\n    lastName\n    middleName\n    id\n    isFclubMember\n    programs {\n      id\n      effDateTime\n      expireDateTime\n      statusId\n      __typename\n    }\n    orders {\n      id\n      number\n      tripType\n      isCanceled\n      hasTripflex\n      departureDate\n      policy {\n        flightDisposition {\n          type\n          __typename\n        }\n        __typename\n      }\n      flights {\n        id\n        departureDate\n        number\n        checkInOpenIn\n        canceled\n        isInternational\n        origin {\n          code\n          title\n          displayName\n          city\n          state\n          __typename\n        }\n        destination {\n          code\n          title\n          displayName\n          city\n          state\n          __typename\n        }\n        flightStatus {\n          departure {\n            scheduledDateTime\n            estimatedDateTime\n            status\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.allegiantair.com/graphql", $data, $this->headers);
        $this->http->RetryCount = 2;
        $flights = $this->http->JsonLog(null, 3, false, 'orders');

        if ($this->http->Response['code'] == 200 && $this->http->FindPreg('/"orders":\s*\[\],/')) {
            return $this->noItinerariesArr();
        }

        // provider bug workaround
        if ($this->http->Response['code'] == 503 && empty($flights)) {
            $this->logger->error("something went wrong");

            return [];
        }

        /*
        if($this->http->Response['code'] == 409 && $this->http->FindPreg('/"error":\[\{"/'))
            return $results;
        */

        if (isset($flights->status, $flights->detail) && $flights->status == 500 && $flights->detail === 'Internal Server Error') {
            $this->logger->error("$flights->detail");

            return $results;
        }

        $flights = $flights->data->viewer->orders;
        $this->logger->debug("Total " . ((is_array($flights) || ($flights instanceof Countable)) ? count($flights) : 0) . " itineraries were found");

        foreach ($flights as $flight) {
            $this->logger->info('Parse itinerary #' . $flight->number, ['Header' => 3]);
            // Query Flight Details
            $data = [
                'order' => json_encode([
                    'credentials'      => ['confCode' => $flight->number, 'customerId' => $this->customerId],
                    'saveTriggerEvent' => 'orderDataRequested',
                ]), ];
            $headers = [
                'Accept'           => 'application/json, text/javascript, */*',
                'Content-Type'     => 'application/json',
                'X-Requested-With' => 'XMLHttpRequest',
            ];
            $this->http->RetryCount = 1;
            $this->http->PostURL('https://www.allegiantair.com/g4search/api/modify/' . $flight->number, $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog(null, 3, true);

            if (empty($response)) {
                $this->sendNotification('refs #14091, allegiant - not parsed segment');

                continue;
            }
            /*
             * Sorry, this itinerary cannot be changed online.
             * For assistance, please contact our customer service team at (702) 800-2050.
             */
            if (!ArrayVal($response, 'confCode', null)) {
                $error = ArrayVal($response, 'error', []);
                $errorDescription = ArrayVal($error[0], 'description');

                if (strstr($errorDescription, "Open jaw trips are not supported")) {
                    $this->logger->notice("Sorry, this itinerary cannot be changed online.");

                    continue;
                }// if (strstr($errorDescription, "Open jaw trips are not supported"))
            }// if (!ArrayVal($response, 'confCode', null))
            $itinerary = $this->parseItineraryOld($response);

            // Grup By RecordLocator
            if (isset($results[$flight->number])) {
                $tmp = &$results[$flight->number];
                $tmp['TripSegments'] = array_merge($tmp['TripSegments'], $itinerary['TripSegments']);
            } else {
                $results[$flight->number] = $itinerary;
            }

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($results[$flight->number], true), ['pre' => true]);
        }

        return array_values($results);
    }

    public function parseItineraryOld($details)
    {
        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = ArrayVal($details, 'confCode');
        $result['Status'] = $details['cancelled'] ? 'Cancelled' : 'Confirmed';

        $legs = ['departing', 'returning'];

        foreach ($legs as $leg) {
            if (!isset($details['legs'][$leg]['legs'])) {
                $this->logger->error("leg '{$leg}' not found");

                continue;
            }// if (!isset($details['legs'][$leg]['legs']))

            if (count($details['legs'][$leg]['legs']) > 1) {
                $this->sendNotification('refs #14091, allegiant - check why the segments in JSON are greater > 1');
            }

            // TODO: Contradictory structure of JSON
            $i = [];
            $i['FlightNumber'] = $details['legs'][$leg]['flight_no'];
            $i['AirlineName'] = ArrayVal($details['legs'][$leg], 'airline_code');

            $value = current($details['legs'][$leg]['legs']);
            $i['TraveledMiles'] = ArrayVal($value, 'miles');

            $i['DepCode'] = $value['from'];
            $i['ArrCode'] = $value['to'];
            $i['DepDate'] = strtotime(preg_replace('/\.[\d\-]+/', '', $value['departure_date_i_s_o']), false);
            $i['ArrDate'] = strtotime(preg_replace('/\.[\d\-]+/', '', $value['arrival_date_i_s_o']), false);
            // Seats
            $seats = [];

            foreach ($details['travellers'] as $value) {
                $result['Passengers'][] = beautifulName($value['firstname'] . ' ' . $value['lastname']);

                if (isset($value[$leg]['seat']['id']) && $value[$leg]['seat']['flight_no'] == $i['FlightNumber']) {
                    $seats[] = $value[$leg]['seat']['id'];
                }
            }// foreach ($details['travellers'] as $value)

            if (!empty($result['Passengers'])) {
                $result['Passengers'] = array_unique($result['Passengers']);
            }

            if (!empty($seats)) {
                $i['Seats'] = array_unique($seats);
            }

            $result['TripSegments'][] = $i;
        }// foreach ($legs as $leg)
        /*$this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);*/

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 7,
                "Required" => true,
            ],
            "FirstName"      => [
                "Caption"  => "First Name",
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.allegiantair.com/manage-travel';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $message = $this->selenium($arFields);

        if (is_string($message)) {
            return $message;
        }

        return null;
    }

    public function parseItinerary(): bool
    {
        $airportNameRegex = '/(.+) \([A-Z]{3}\)/';
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        $flights = $this->http->XPath->query("//span[starts-with(@data-hook,'order-item-flight-info_onward-flight-number')]/../../..");
        $r = $this->itinerariesMaster->createFlight();
        $confirmation = $this->http->FindSingleNode("//span[contains(text(),'Confirmation #')]/following-sibling::span[1]");
        $this->logger->info("Parse Itinerary #$confirmation", ['Header' => 3]);
        $r->addConfirmationNumber($confirmation, 'Confirmation number', true);
        $travellers = [];

        foreach ($flights as $flight) {
            $s = $r->addSegment();
            $depDate = $this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-depart-date-time')]",
                $flight);
            $arrDate = $this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-arrival-date-time')]",
                $flight);
            $s->parseDepDate(str_replace(['on ', ' at '], ', ', $depDate));
            $s->parseArrDate(str_replace(['on ', ' at '], ', ', $arrDate));
            $s->setDepCode($this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-origin-display-name')]",
                $flight, true, self::AIRPORT_CODE_REGEX));
            $s->setArrCode($this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-destination-display-name')]",
                $flight, true, self::AIRPORT_CODE_REGEX));
            $s->setDepName($this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-origin-display-name')]",
                $flight, true, $airportNameRegex));
            $s->setArrName($this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-destination-display-name')]",
                $flight, true, $airportNameRegex));
            $s->setFlightNumber($this->http->FindSingleNode(".//span[starts-with(@data-hook,'order-item-flight-info_onward-flight-number')]",
                $flight));
            $s->airline()->name('G4');

            $i = 0;

            foreach ($this->http->FindNodes("//span[starts-with(@data-hook,'order-item-flight-info_onward_traveler-') and contains(@data-hook,'-name')]") as $traveller) {
                $name = beautifulName($traveller);
                $travellers[] = $name;
                $seat = $this->http->FindSingleNode("//span[starts-with(@data-hook,'order-item-flight-info_onward_traveler-$i') and contains(@data-hook,'-seat-number')]");
                $this->logger->info("[Name]: $name");
                $this->logger->info("[Seat]: $seat");

                if (!empty($seat) && $seat != 'Not Assigned') {
                    $s->addSeat($seat);
                }
                $i++;
            }
        }
        $r->setTravellers(array_unique($travellers), true);
        $this->logger->info('Parsed Itinerary: ' . var_export($r->toArray(), true), ['pre' => true]);

        return true;
    }

    private function selenium($arFields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            $selenium->usePacFile(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $this->http->GetURL('https://www.allegiantair.com/');
            sleep(5);
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));
            $firstName = $selenium->waitForElement(WebDriverBy::id('firstName'), 7);
            $lastName = $selenium->waitForElement(WebDriverBy::id('lastName'), 0);
            $orderNumber = $selenium->waitForElement(WebDriverBy::id('orderNumber'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-hook = "lookup-page-lookup-button"]'), 0);

            if (!isset($firstName, $lastName, $orderNumber, $btn)) {
                $this->savePageToLogs($selenium);

                return null;
            }
            $firstName->sendKeys($arFields['FirstName']);
            $lastName->sendKeys($arFields['LastName']);
            $orderNumber->sendKeys($arFields['ConfNo']);
            $btn->click();

            $loadingStartTime = time();
            $loadingSuccess = $selenium->waitForElement(WebDriverBy::xpath('//span[@data-hook="order-item-flight-info_onward-depart-date-time"]'), 10, false);
            $this->increaseTimeLimit(time() - $loadingStartTime);

            $this->savePageToLogs($selenium);

            if (!$loadingSuccess) {
                $error = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(),'Sorry, we were unable to retrieve your reservation.')]/ancestor::div[1]"), 0);

                if ($error) {
                    return $error->getText();
                }

                return null;
            }

            $this->parseItinerary();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }
}
