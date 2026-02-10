<?php

class TAccountCheckerEgencia extends TAccountChecker
{
    protected $links = null; // Reservation links

    protected $host = 'www.egencia.com';

//    function TuneFormFields(&$arFields, $values = null) {
//        parent::TuneFormFields($arFields, $values);
//        $result = Cache::getInstance()->get('egencia_countries');
//        if (($result !== false) && (count($result) > 1)) {
//            $arFields["Login2"]["Options"] = $result;
//        } else {
//            $arFields["Login2"]["Options"] = array(
//                "" => "Select a region",
//            );
//            $browser = new HttpBrowser("none", new CurlDriver());
//            if ($browser->GetURL("http://www.egencia.com/en/")) {
//                $options = $browser->XPath->query(".//*[@id='site-chooser']//option");
//                foreach ($options as $option) {
//                    $text = $browser->FindSingleNode("./text()", $option);
//                    if ($text != 'Any other Country') {
//                        $host = parse_url($browser->FindSingleNode("./@value", $option), PHP_URL_SCHEME)
//                            . '://' . parse_url($browser->FindSingleNode("./@value", $option), PHP_URL_HOST);
//                        $arFields['Login2']['Options'][$host] = $text;
//                    }// if ($text != 'Any other Country')
//                }// foreach ($options as $option)
//            }// if ($browser->GetURL("http://www.egencia.com/en/"))
//        }
//    }

    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->FilterHTML = false;
        $this->http->removeCookies();
//        $us = false;
        // fixed bugs in TuneFormFields
//        $host = parse_url($this->AccountFields['Login2'], PHP_URL_SCHEME).'://'.parse_url($this->AccountFields['Login2'], PHP_URL_HOST);
//        switch ($host) {
//            case 'http://www.egencia.co.uk':
        //				$this->http->Log('UK');
        //				$this->host = 'www.egencia.co.uk';
//                $market = 'GB';
//                break;
        //			case 'http://www.egencia.ca':
        //				$this->http->Log('Canada');
        //				$this->host = 'www.egencia.ca';
//                $market = 'CA';
//                $us = true;
//                break;
//            case 'http://www.egencia.fi':
        //				$this->http->Log('Finland');
        //				$this->host = 'www.egencia.fi';
//                $market = 'FI';
//                break;
        //			case 'http://www.egencia.com.au':
        //				$this->http->Log("Australia");
        //				$this->host = 'www.egencia.com.au';
//                $market = 'AU';
//                break;
        //			default:
        //				//USA
        //				$this->http->Log('USA');
        //				$this->host = 'www.egencia.com';
//                $market = 'US';
//                $us = true;
//                break;
        //		}
//        if ($us)
        $this->http->GetURL('https://' . $this->host . '/pub/agent.dll?qscr=logi&&lang=en');
//        else
//            $this->http->GetURL("https://{$this->host}/app?service=external&page=Login&mode=form&market={$market}&lang=en");
        $this->http->MultiValuedForms = true;
        // from js script - for USA
        if ($this->http->ParseForm('QSREDIR')) {
            $this->logger->notice("Execute DoLoad()");
            $this->http->SetInputValue('zz', time() . date("B"));

            if (!$this->http->PostForm()) {
                return false;
            }
        }

        if (!$this->http->ParseForm(null, "//input[@name = 'userName']//ancestor::form[1]")) {
            return false;
        }
//        $this->http->FormURL = 'https://www.egencia.com/auth/v1/accessToken';
        $this->http->SetInputValue('userName', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        if (strpos($this->AccountFields['Login'], '@') === false) {
            if (!$this->http->PostForm()) {
                return false;
            }

            if ($this->http->FindPreg("/body\s+id=\"login-page\"\s+class=.+?reset-password-page/ims")) {
                if (!$this->http->ParseForm(null, "//input[@name = 'userName']//ancestor::form[1]")) {
                    return false;
                }
                $userNameNew = $this->http->FindSingleNode("//input[@name='userName']/@value");

                if (empty($userNameNew)) {
                    $message = $this->http->FindSingleNode("//*[@id='error-message']");

                    if (!empty($message)) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    } else {
                        $this->sendNotification('egencia - Unhandled Login - might be changed');

                        return false;
                    }
                }
                $this->http->SetInputValue('userName', $userNameNew);
                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            }
        }

        return true;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->PostForm()) {
            return false;
        }

        // for USA
        if ($this->http->FindPreg("/body onload=javascript:document\.forms\[0\]\.submit\(\)/ims")) {
            $this->http->PostForm();
        }
        // for Canada
        if ($this->http->FindPreg("/body onload=javascript:document\.forms\[0\]\.submit\(\)/ims")) {
            $this->http->PostForm();
        }

        if ($message = $this->http->FindSingleNode("//span[contains(@class, 'errorTextStd')]/font")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Australia
        // Your Member ID or your password is invalid
//        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'Your Member ID or your password is invalid')]"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // You may have entered an unknown user name or an incorrect password.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'You may have entered an unknown user name or an incorrect password.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//*[@id = 'error-message' and normalize-space(.) != ''] | //div[@class = 'alert-message-content']/p")) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'You may have entered an unknown user name or an incorrect password.')
                || strstr($message, 'Your profile is not active. Contact your Travel Manager for assistance.')
                || strstr($message, 'Your password has expired! An email has been sent to your registered email:')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($error = $this->http->FindPreg('#User profile deactivated#i')) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

//        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign out')]"))
        //			// Seems like that site design has been changed and there is no more this button
//            return true;
        if (
            $this->http->FindSingleNode("//a[@id='log-out' and contains(text(),'Sign out') or contains(text(),'Sign Out')]")
            || $this->http->FindNodes('//button[@id="log-out"] | //span[contains(text(), "Log out") or contains(text(), "Log Out")]')
            || $this->http->FindPreg('/service=LogoutService/')
        ) {
            // Condition for new design
            return true;
        }
        // The selected user is currently disabled.
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The selected user is currently disabled.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        $this->CheckError($this->http->FindPreg("/Your user account has been blocked/ims"), ACCOUNT_LOCKOUT);
        // Your company account is disabled
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your company account is disabled')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You are not currently an Egencia customer; please contact your Travel Manager for assistance.
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'You are not currently an Egencia customer; please contact your Travel Manager for assistance.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Set new password
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Set new password')]")) {
            throw new CheckException("Egencia website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($error = $this->http->FindPreg('#Unfortunately,\s+your\s+account\s+has\s+been\s+disabled\.#i')
                    and $tip = $this->http->FindPreg('#For\s+assistance,\s+please\s+contact\s+your\s+Travel\s+Manager\s+or\s+Egencia\s+site\s+administrator\.#i')) {
            throw new CheckException($error . ' ' . $tip, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $this->host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $this->logger->notice("NOW PARSE FROM HOST: " . $this->host);

        if (!in_array($this->host, ["www.egencia.com", "www.egencia.ca", "www.egencia.no", "www.egencia.co.uk", "www.egencia.dk", "www.egencia.se"])) {
            $this->sendNotification("egencia - check ParseItineraries on host: {$this->host} // MI");
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'account-nav-toggle-user']")));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = $this->ParseItinerariesHtml();

        if (!$result) {
            $result = $this->ParseItinerariesJson();
        }

        return $result;
    }

    public function ParseFlightJson($conf, $item, $tripNumber)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $result['Kind'] = 'T';
        $result['RecordLocator'] = $conf;
        $result['TripNumber'] = $tripNumber;
        $this->logger->info(sprintf('Parse Flight #%s', $conf), ['Header' => 4]);

        // Passengers
        $passengers = [];

        foreach (ArrayVal($item, 'travelers', []) as $traveler) {
            $passengers[] = trim(sprintf('%s %s', ArrayVal($traveler, 'first_name'), ArrayVal($traveler, 'last_name')));
        }
        $result['Passengers'] = $passengers;

        $tripSegments = [];

        foreach (ArrayVal($item, 'origin_destinations', []) as $dest) {
            $tripSegment = [];
            $segment = $dest['segments'][0] ?? [];

            if (!$segment) {
                continue;
            }
            // Departure
            if (isset($segment['departure_location']['code'])) {
                $tripSegment['DepCode'] = $segment['departure_location']['code'];
            }

            if (isset($segment['departure_location']['name'])) {
                $tripSegment['DepName'] = $segment['departure_location']['name'];
            }

            if (isset($segment['departure_location']['terminal'])) {
                $tripSegment['DepartureTerminal'] = $segment['departure_location']['terminal'];
            }
            $tripSegment['DepDate'] = strtotime(ArrayVal($segment, 'scheduled_departure_datetime'));
            // Arrival
            if (isset($segment['arrival_location']['code'])) {
                $tripSegment['ArrCode'] = $segment['arrival_location']['code'];
            }

            if (isset($segment['arrival_location']['name'])) {
                $tripSegment['ArrName'] = $segment['arrival_location']['name'];
            }
            $tripSegment['ArrDate'] = strtotime(ArrayVal($segment, 'scheduled_arrival_datetime'));

            if (isset($segment['arrival_location']['terminal'])) {
                $tripSegment['ArrivalTerminal'] = $segment['arrival_location']['terminal'];
            }
            // AirlineName
            if (isset($segment['marketing_designation']['carrier_code'])) {
                $tripSegment['AirlineName'] = $segment['marketing_designation']['carrier_code'];
            }
            // FlightNumber
            if (isset($segment['marketing_designation']['number'])) {
                $tripSegment['FlightNumber'] = $segment['marketing_designation']['number'];
            }
            // Duration
            $tripSegment['Duration'] = sprintf('%.2dh %.2dm', floor($segment['duration'] / 60), $segment['duration'] % 60);
            // Seats
            $seats = [];
            $travelerInfo = ArrayVal($segment, 'traveler_info', []);

            foreach ($travelerInfo as $info) {
                if (isset($info['seating']['seat'])) {
                    $seats[] = $info['seating']['seat'];
                }
            }
            $tripSegment['Seats'] = implode(', ', $seats);
            // Class
            if (isset($segment['class']['code'])) {
                $class = $segment['class']['code'];
                $tripSegment['Cabin'] = trim($this->http->FindPreg('/^\s*(.+?)\s*(?:$|\()/ims', false, $class));
                $tripSegment['BookingClass'] = $this->http->FindPreg('/\(([A-Z])\)/ms', false, $class);
            }

            $tripSegments[] = $tripSegment;
        }
        $result['TripSegments'] = $tripSegments;

        // TotalCharge
        if (isset($item['pricing']['amount'])) {
            $result['TotalCharge'] = $item['pricing']['amount'];
        }
        // Currency
        if (isset($item['pricing']['currency'])) {
            $result['Currency'] = $item['pricing']['currency'];
        }
        // Status
        if (isset($item['status']['code'])) {
            $result['Status'] = $item['status']['code'];
        }

        return $result;
    }

    public function ParseHotelJson($conf, $item, $tripNumber)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $result['Kind'] = 'R';
        $result['ConfirmationNumber'] = $conf;
        $result['TripNumber'] = $tripNumber;
        $this->logger->info(sprintf('Parse Hotel #%s', $conf), ['Header' => 4]);

        // HotelName
        if (isset($item['hotel']['vendor']['name'])) {
            $result['HotelName'] = $item['hotel']['vendor']['name'];
        }
        // Address
        if (isset($item['hotel']['location']['full_address'])) {
            $result['Address'] = $item['hotel']['location']['full_address'];
        }
        // Phone
        if (isset($item['hotel']['vendor']['phone'])) {
            $result['Phone'] = $item['hotel']['vendor']['phone'];
        }
        // Fax
        if (isset($item['hotel']['vendor']['fax']) && strlen($item['hotel']['vendor']['fax']) > 3) {
            $result['Fax'] = $item['hotel']['vendor']['fax'];
        }
        // GuestNames
        $guests = [];

        foreach (ArrayVal($item, 'travelers', []) as $traveler) {
            $guests[] = trim(sprintf('%s %s', ArrayVal($traveler, 'first_name'), ArrayVal($traveler, 'last_name')));
        }
        $result['GuestNames'] = $guests;
        // CheckInDate
        if (isset($item['hotel']['stay']['checkin_date'])) {
            $date = $this->http->FindPreg('/\d+-\d+-\d+T\d+:\d+/', false, $item['hotel']['stay']['checkin_date']);
            $this->logger->debug("CheckInDate: $date");
            $result['CheckInDate'] = strtotime($date);
        }
        // CheckOutDate
        if (isset($item['hotel']['stay']['checkout_date'])) {
            // 2023-03-21T00:00:01-0700
            // 2023-05-03T00:00:01+0900
            $date = $this->http->FindPreg('/\d+-\d+-\d+T\d+:\d+/', false, $item['hotel']['stay']['checkout_date']);
            $this->logger->debug("CheckOutDate: $date");
            $result['CheckOutDate'] = strtotime($date);
        }
        // Rooms
        if (isset($item['hotel']['stay']['number_of_rooms'])) {
            $result['Rooms'] = $item['hotel']['stay']['number_of_rooms'];
        }
        // RoomType
        if (isset($item['hotel']['stay']['room_description'])) {
            $result['RoomTypeDescription'] = $item['hotel']['stay']['room_description'];
        }
        // Guests
        if (isset($item['hotel']['stay']['adults_per_room'])) {
            $result['Guests'] = $item['hotel']['stay']['adults_per_room'];
        }
        // Rate
        if (isset($item['hotel']['stay']['rate_type']['name'])) {
            $result['Rate'] = $item['hotel']['stay']['rate_type']['name'];
        }
        // Total
        if (isset($item['pricing']['amount'])) {
            $result['Total'] = $item['pricing']['amount'];
        }
        // Currency
        if (isset($item['pricing']['currency'])) {
            $result['Currency'] = $item['pricing']['currency'];
        }

        return $result;
    }

    public function ParseCarJson($conf, $item, $tripNumber)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $result['Kind'] = 'L';
        $result['Number'] = str_replace(' ', '-', $conf);
        $result['TripNumber'] = $tripNumber;
        $this->logger->info(sprintf('Parse Car #%s', $conf), ['Header' => 4]);

        // TotalCharge
        if (isset($item['pricing']['amount'])) {
            $result['TotalCharge'] = $item['pricing']['amount'];
        }
        // Currency
        if (isset($item['pricing']['currency'])) {
            $result['Currency'] = $item['pricing']['currency'];
        }
        // PickupDatetime
        $result['PickupDatetime'] = strtotime(ArrayVal($item, 'start_datetime'));
        // DropoffDatetime
        $result['DropoffDatetime'] = strtotime(ArrayVal($item, 'end_datetime'));
        // RentalCompany
        if (isset($item['car']['vendor']['name'])) {
            $result['RentalCompany'] = $item['car']['vendor']['name'];
        }
        // PickupPhone
        if (isset($item['car']['vendor']['phone'])) {
            $result['PickupPhone'] = $item['car']['vendor']['phone'];
        }
        // PickupHours
        if (isset($item['car']['vendor']['business_hours'])) {
            // PICK-UP: 07:00 - 22:00
            if ($str = $this->http->FindPreg('/PICK-UP:\s*(\d+:\d+ - \d+:\d+)/', false, $item['car']['vendor']['business_hours'])) {
                $result['PickupHours'] = $str;
            } else {
                $result['PickupHours'] = $item['car']['vendor']['business_hours'];
            }
        }
        // PickupLocation
        if (isset($item['car']['start_location']['full_address'])) {
            $result['PickupLocation'] = $item['car']['start_location']['full_address'];
        }
        // DropoffLocation
        if (isset($item['car']['end_location']['full_address'])) {
            $result['DropoffLocation'] = $item['car']['end_location']['full_address'];
        }
        // CarType
        if (isset($item['car']['equipment_type']['category'])) {
            $result['CarType'] = $item['car']['equipment_type']['category'];
        }
        // RenterName
        $firstName = '';

        if (isset($item['travelers'][0]['first_name'])) {
            $firstName = $item['travelers'][0]['first_name'];
        }
        $lastName = '';

        if (isset($item['travelers'][0]['last_name'])) {
            $lastName = $item['travelers'][0]['last_name'];
        }
        $result['RenterName'] = trim(sprintf('%s %s', $firstName, $lastName));

        return $result;
    }

    public function ParseTripJson($id)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL(sprintf('https://%s/trip-webapp/%s', $this->host, $id));
        $this->logger->info(sprintf('Parse Trip #%s', $id), ['Header' => 3]);
        $tripResponse = stripcslashes($this->http->FindPreg('/EG\.tripResponse = JSON\.parse\("(\{.+?\})"\);/s'));
        $data = $this->http->JsonLog($tripResponse, 2, true);

        $items = ArrayVal($data, 'items', []);

        foreach ($items as $i => $item) {
            $itin = [];

            if (isset($data['summary']['travelers'][0]['items'][$i]['vendor_references'][0])) {
                $conf = $data['summary']['travelers'][0]['items'][$i]['vendor_references'][0];
            } else {
                $conf = CONFNO_UNKNOWN;
            }
            $tripNumber = null;

            if (isset($data['summary']['travelers'][0]['items'][$i]['booking_reference'])) {
                $tripNumber = $data['summary']['travelers'][0]['items'][$i]['booking_reference'];
            }
            $type = ArrayVal($item, 'item_type');

            switch ($type) {
                case 'FLIGHT':
                    $itin = $this->ParseFlightJson($conf, $item, $tripNumber);

                    break;

                case 'FEE':
                    break;

                case 'HOTEL':
                    $itin = $this->ParseHotelJson($conf, $item, $tripNumber);

                    break;

                case 'CAR':
                    $itin = $this->ParseCarJson($conf, $item, $tripNumber);

                    break;

                default:
                $this->sendNotification(sprintf('egencia - Unhandled itinerary type %s', $type));

                    break;
            }

            if ($itin) {
                $this->logger->debug('Parsed itinerary:');
                $this->logger->debug(var_export($itin, true), ['pre' => true]);
                $result[] = $itin;
            }
        }

        return $result;
    }

    public function ParseItinerariesJson()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // fixed bugs in TuneFormFields
        $host = parse_url($this->AccountFields['Login2'], PHP_URL_SCHEME) . '://' . parse_url($this->AccountFields['Login2'], PHP_URL_HOST);

        $this->logger->notice($host);
        //	    switch ($host) {
        //		    default: // USA
        $this->http->GetURL(sprintf('https://%s/trip-service/v2/trips?include=EMPTY_GROUP_TRIPS&view=SUMMARY,SIMPLE,GROUP_TRIP&direction=ASC&order_by=START_DATE&content=SUMMARY&start=0&count=100&from_date=%s', $this->host, date('Y-m-d')));
        $data = $this->http->JsonLog(null, 3, true);

        $trips = ArrayVal($data, 'trips', []);

        foreach ($trips as $trip) {
            $id = ArrayVal($trip, 'id');
            $itins = $this->ParseTripJson($id);
            $result = array_merge($result, $itins);
        }

        $noFutureRes = false;
        $noPastRes = false;

        // refs #14627
        $noTrips =
                    $this->http->FindPreg("/^\{\}$/ims")
                    || $this->http->FindPreg('/^\{\"more_trips\":false\}/ims');

        if (empty($trips) && $noTrips) {
            $noFutureRes = true;
        }
        $this->logger->info('no future:' . var_export($noFutureRes, true));

        if ($this->ParsePastIts) {
            $this->http->GetURL(sprintf('https://%s/trip-service/v2/trips?include=EMPTY_GROUP_TRIPS&view=SUMMARY,SIMPLE,GROUP_TRIP&direction=ASC&order_by=START_DATE&content=SUMMARY&start=0&count=100&&to_date=%s&current_date=%s', $this->host, date('Y-m-d'), date('Y-m-d')));

            $data = $this->http->JsonLog(null, 3, true);

            $trips = ArrayVal($data, 'trips', []);

            foreach ($trips as $trip) {
                $id = ArrayVal($trip, 'id');
                $itins = $this->ParseTripJson($id);
                $result = array_merge($result, $itins);
            }
            $noTrips =
                        $this->http->FindPreg("/^\{\}$/ims")
                        || $this->http->FindPreg('/^\{\"more_trips\":false\}/ims');

            if (empty($trips) && $noTrips) {
                $noPastRes = true;
            }
            $this->logger->info('no past:' . var_export($noFutureRes, true));
        } else {
            $noPastRes = true;
        }

        if ($noFutureRes && $noPastRes) {
            return $this->noItinerariesArr();
        }

        //	    }
        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->logger->info('Check Itineraries', ['Header' => 3]);
            $this->logger->info($this->checkItineraries($result, true));
        }

        return $result;
    }

    public function ParseItinerariesHtml()
    {
        $this->logger->notice(__METHOD__);
        $trips = [];
        /*// fixed bugs in TuneFormFields
        $host = parse_url($this->AccountFields['Login2'], PHP_URL_SCHEME).'://'.parse_url($this->AccountFields['Login2'], PHP_URL_HOST);
        switch ($host) {
            case "http://www.egencia.com.au":
                $this->http->GetURL("https://www.egencia.com.au/app?page=DossiersListPage&service=page&ts=".time().date("B"));
                // Cancelled itineraries
                $cancelledItineraries = $this->http->XPath->query("//a[contains(@id, 'ExternalLink') and not(@fieldnamefortest) and parent::td/preceding-sibling::td[1]/img[contains(@src, 'refuse')]]/following-sibling::table[1]//tr");
                $this->http->Log("Total {$cancelledItineraries->length} cancelled itineraries were found");
                for ($i = 0; $i < $cancelledItineraries->length; $i++) {
                    $result = $res = array();
                    $type = $this->http->FindSingleNode("td[1]/img/@src", $cancelledItineraries->item($i), true, '/\-([^<.]+).+$/ims');
                    $confNumber = $this->http->FindSingleNode("td[2]", $cancelledItineraries->item($i));
                    $this->http->Log("Type: {$type}");
                    switch ($type) {
                        case 'flight':
                            $res["Kind"] = "T";
                            $key = 'RecordLocator';
                            break;
                        case 'hotel':
                            $res['Kind'] = 'R';
                            $key = 'ConfirmationNumber';
                            break;
                        case 'car':
                            $res['Kind'] = 'L';
                            $key = 'Number';
                            break;
                        default:
                            $this->http->Log("Unknown itineraries type: {$type}");
                            $key = '';
                            break;
                    }// switch ($type)
                    $res['Cancelled'] = true;
                    $confNumbers = explode(",", $confNumber);
                    foreach ($confNumbers as $number) {
                        $res[$key] = $number;
                        $result[] = $res;
                    }
                    if (count($result))
                        $trips = array_merge($trips, $result);
                }// for ($i = 0; $i < $cancelledItineraries->length; $i++)
//				$this->links = $this->http->FindNodes("//a[contains(@id, 'ExternalLink') and not(@fieldnamefortest)]/@href");
                // exclude refused and new itineraries
                $this->links = $this->http->FindNodes("//a[contains(@id, 'ExternalLink') and not(@fieldnamefortest) and not(parent::td/preceding-sibling::td[1]/img[contains(@src, 'refuse') or contains(@src, 'nouveau')])]/@href");
                if ($this->links) {
                    // for debug in curl
                    $this->sendNotification("egencia (Australia). refs #9517 / Need check this in curl", 'awardwallet');
                    foreach ($this->links as $link) {
                        $this->http->NormalizeURL($link);
                        $this->http->setRequestParameter('useBrackets', false);
                        $this->http->GetURL($link);
                        $result = $this->ParseItineraryHtmlUK();
                        if (count($result))
                            $trips = array_merge($trips, $result);
                    }
                }
                break;
            case 'http://www.egencia.co.uk':
            case 'http://www.egencia.fi':
                if ($this->links) {
                    // for debug in curl
                    $this->sendNotification("egencia (UK). refs #9517 / Need check this in curl", 'awardwallet');
                    foreach ($this->links as $link) {
                        $this->http->NormalizeURL($link);
                        $this->http->GetURL($link);
                        $result = $this->ParseItineraryHtmlUK();
                        if (count($result))
                            $trips = array_merge($trips, $result);
                    }
                }
                break;
            case 'http://www.egencia.ca':
            default:*/
        switch ($this->host) {
            //CANADA
            case "www.egencia.ca":
                // maybe no need
                //$this->http->GetURL("https://".$this->host."/pub/agent.dll?qscr=mtrp&fsup=1&uact=10");
                return false;
        }

        //USA
        $this->http->GetURL("https://" . $this->host . "/pub/agent.dll?qscr=mtrp&rfrr=-1075");
        $links = [];
        $next = null;
        $limit = 10;

        do {
            $rows = $this->http->XPath->query("//tr[td[contains(@class, 'left group-icon')]]");

            foreach ($rows as $row) {
                if ($this->http->FindSingleNode("td[5]", $row) && ($date = $this->http->FindSingleNode("td[7]", $row))) {
                    $date = strtotime($date);

                    if ($date !== false && $date >= strtotime("- 1 month")) {
                        $links[] = ["Url" => $this->http->FindSingleNode("td[2]/a/@href", $row), "Name" => $this->http->FindSingleNode("td[4]", $row)];
                    }
                }
            }
            $next = $this->http->FindSingleNode("//tfoot//a[text() = 'Next']/@href");

            if ($next) {
                $this->http->NormalizeURL($next);
                $this->http->GetURL($next);
            }
            $limit--;
        } while (!empty($next) && $limit > 0);
        $this->http->Log("Found " . count($links) . " reservations");

        foreach ($links as $link) {
            $this->sendNotification("egencia - need describe parsing past itinerary on this way");
            $url = $link["Url"];
            $name = $link["Name"];
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);

            if (($this->http->FindSingleNode("//text()[contains(., 'There is a problem updating this itinerary at this time. We will display the last updated version.')]")
                        || $this->http->FindSingleNode("//text()[contains(., 'This trip has already been completed')]"))
                        && $link = $this->http->FindSingleNode("//a[.//b[text() = 'Continue']]/@href")) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
            }
            $result = $this->ParseItineraryHtml($name);

            if (!empty($result) && is_array($result)) {
                $trips = array_merge($trips, $result);
            }
        }
        // no Itineraries
        if (count($links) == 0 && $this->http->FindSingleNode("//td[contains(text(), 'There are no saved trips')]")) {
            return $this->noItinerariesArr();
        }
        //				break;
        //		}
        return $trips;
    }

    /*function ParseItineraryHtmlUK() {
        $this->logger->notice(__METHOD__);
        $reservations = $this->http->XPath->query("//td[contains(text(), 'Your flight') or contains(text(), 'Your hotel') or contains(text(), 'Your car') or contains(text(), 'Your train')]/ancestor::div[@id = '\$BlueRoundBox']");
        $this->logger->debug("Total reservations found: ".$reservations->length);
        if (count($reservations)) {
            $trip = array();
            $statuses = $this->http->FindNodes("//div[@id = '\$BlueRoundBox']//span[contains(@class, 'accueil')]/table//td[img]");
            foreach ($reservations as $key => $reservation) {
                $res = array();
                $status = (strstr($statuses[$key], "Booked")) ? "Booked" : null;
                if (is_null($status))
                    $status = (strstr($statuses[$key], "Waiting for approval")) ? "Waiting for approval" : null;
                if (is_null($status))
                    $status = (strstr($statuses[$key], "Refused")) ? "Refused" : null;

                $res['Status'] = $status;
                $this->logger->debug("statuses:" . $statuses[$key]);

                if ($res['Status'] == 'Refused')
                    continue;

                preg_match("/Your\s*([A-Za-z]+)/ims", $statuses[$key], $match);
                if (isset($match[1]))
                    $type = strtolower($match[1]);
                else {
                    $this->logger->notice("Reservation type undefined:" . $statuses[$key]);
                    continue;
                }

                switch ($type) {
                    case 'flight':
                        $res["Kind"] = "T";
                        $res["RecordLocator"] = $this->http->FindSingleNode("div[contains(@id, 'Any')]//span[contains(@class, 'PNR')]", $reservation);
                        // ReservationDate
                        $res["ReservationDate"] = strtotime($this->http->FindPreg("/Issuing date\s*:<\/b>\s*([A-Za-z\,\d\s]+\d{4})/ims", $reservation));
//                        $res["Passengers"] = $this->http->FindSingleNode(".//td[@bgcolor='#e9e3db'][1]/b", $reservation);
//                        $res["BaseFare"] = $this->http->FindSingleNode(".//td[@bgcolor='#e9e3db'][4]", $reservation, true, "/[\d.]+/");
//                        $res["Tax"] = $this->http->FindSingleNode(".//td[@id='A2003_19850']", $reservation, true, "/[\d.]+/");
//                        $res["TotalCharge"] = $this->http->FindSingleNode(".//tr[5]/td[2]/b", $reservation, true, "/[\d.,]+/");
                        $segments = $this->http->XPath->query("div[@class = 'body']/table[@class = 'result_tab']", $reservation);
                        $segs = array();
                        foreach ($segments as $segment) {
                            $segs["AirlineName"] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[4]", $segment);
                            // DepDate
                            $DepDate = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong", $segment, true, '/(^\s*\d{2}\/\d{2}\/\d{4})/');
                            $this->logger->debug("DepDate {$DepDate}");
                            $DepDate = $this->ModifyDateFormat($DepDate);
                            $DepTime = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong", $segment, true, '/(\d{2}:\d{2})/');
                            $this->logger->debug("DepDate {$DepDate} {$DepTime}");
                            $segs['DepDate'] = strtotime($DepDate.' '.$DepTime);
                            // ArrDate
                            $ArrDate = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[1]", $segment, true, '/(^\s*\d{2}\/\d{2}\/\d{4})/');
                            $this->logger->debug("ArrDate {$ArrDate}");
                            $ArrDate = $this->ModifyDateFormat($ArrDate);
                            $ArrTime = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[1]", $segment, true, '/(\d{2}:\d{2})/');
                            $this->logger->debug("ArrDate {$ArrDate} {$ArrTime}");
                            $segs['ArrDate'] = strtotime($ArrDate.' '.$ArrTime);
                            // DepCode
                            $segs['DepCode'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong/text()[last()]", $segment, true, '/\(([A-Z]{3})\)/');
                            if (!isset($segs['DepCode'])) {
                                $segs['DepCode'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong/text()[last()-1]", $segment, true, '/\(([A-Z]{3})\)/');
                                // DepName
                                $segs['DepName'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong/text()[last()-1]", $segment, true, '/([^\(]+)/ims');
                            }
                            else
                                // DepName
                                $segs['DepName'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[1]/strong/text()[last()]", $segment, true, '/([^\(]+)/ims');
                            // ArrCode
                            $segs['ArrCode'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[last()]", $segment, true, '/\(([A-Z]{3})\)/');
                            if (!isset($segs['ArrCode'])) {
                                $segs['ArrCode'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[last()-1]", $segment, true, '/\(([A-Z]{3})\)/');
                                $segs['ArrName'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[last()-1]", $segment, true, '/([^\(]+)/ims');
                            }
                            else
                                // ArrName
                                $segs['ArrName'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[1]/td[2]/strong/text()[last()]", $segment, true, '/([^\(]+)/ims');
                            // FlightNumber
                            $segs['FlightNumber'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[2]/td[4]", $segment);
                            // Seats
                            $segs['Seats'] = $this->http->FindSingleNode("tr[1]/td[2]/table/tr[2]/td[3]/text()[last()]", $segment);
                            // Stops
                            $segs['Stops'] = $this->http->FindSingleNode("tr[1]/td[3]/span", $segment, true, "/\d+/");
                            // Duration
                            $segs['Duration'] = $this->http->FindSingleNode("tr[1]/td[3]/text()[last()]", $segment);
                            // Cabin
                            $segs['Cabin'] = $this->http->FindSingleNode("tr[2]/td/div/div/div/div/text()[last()]", $segment);
                            // BookingClass
                            $segs['BookingClass'] = $this->http->FindSingleNode("tr[2]/td/div/div/div/span[1]", $segment, true, '/\(?([A-Z]{0,2})\)?/');

                            $res['TripSegments'][] = $segs;
                        }
                        break;

                    case 'car':
                        $res['Kind'] = 'L';
                        $res['Number'] = $this->http->FindSingleNode(".//span[@id='dossierPnr']", $reservation);
                        // PickupDatetime
                        $res['PickupDatetime'] = $this->ModifyDateFormat($this->http->FindSingleNode(".//td[contains(., 'Pick-up')]/following-sibling::td[1]", $reservation, true, '/(\d{2}\/\d{2}\/\d{4})/'));
                        $time = $this->http->FindSingleNode(".//td[contains(., 'Pick-up')]/following-sibling::td[1]", $reservation, true, '/(\d{2}\:\d{2})/');
                        $this->logger->debug("PickupDatetime: {$time} ".$res['PickupDatetime']." / ". strtotime($time.' '.$res['PickupDatetime']));
                        $res['PickupDatetime'] = strtotime($time.' '.$res['PickupDatetime']);
                        // DropoffDatetime
                        $res['DropoffDatetime'] =  $this->ModifyDateFormat($this->http->FindSingleNode(".//td[contains(., 'Drop-off')]/following-sibling::td[1]", $reservation, true, '/(\d{2}\/\d{2}\/\d{4})/'));
                        $time = $this->http->FindSingleNode(".//td[contains(., 'Drop-off')]/following-sibling::td[1]", $reservation, true, '/(\d{2}\:\d{2})/');
                        $this->logger->debug("DropoffDatetime: {$time} ".$res['DropoffDatetime']." / ". strtotime($time.' '.$res['DropoffDatetime']));
                        $res['DropoffDatetime'] = strtotime($time.' '.$res['DropoffDatetime']);
                        // PickupLocation
                        $res['PickupLocation'] = array_map(function($elem){
                            if (empty($elem))
                                return '';
                            return $elem.', ';
                        }, $this->http->FindNodes("//td[contains(., 'Pick-up')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/text()[position() < last()]", $reservation));
                        $res['PickupLocation'] = implode($res['PickupLocation']);
                        $res['PickupLocation'] = preg_replace('/\,\s$/ims', ' ', $res['PickupLocation']);
                        // DropoffLocation
                        $res['DropoffLocation'] = array_map(function($elem){
                            if (empty($elem))
                                return '';
                            return $elem.', ';
                        }, $this->http->FindNodes("//td[contains(., 'Drop-off')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/text()[position() < last()]", $reservation));
                        $res['DropoffLocation'] = implode($res['DropoffLocation']);
                        $res['DropoffLocation'] = preg_replace('/\,\s$/ims', '', $res['DropoffLocation']);

//                        $res['TotalTaxAmount'] = $this->http->FindSingleNode(".//*[@id='A2024_19510']", $reservation, true, "/[\d.]+/");
                        $res['TotalCharge'] = $this->http->FindSingleNode(".//span[contains(text(), 'Price :')]", $reservation, true, "/[\d.]+/");
                        $res['Currency'] = $this->http->FindSingleNode(".//span[contains(text(), 'Price :')]", $reservation, true, "/\:\s*([^\d.]+)/");
                        $res['RentalCompany'] = $this->http->FindSingleNode("//td[contains(., 'Drop-off')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/b", $reservation);
                        $res['CarType'] = $this->http->FindSingleNode(".//td[contains(., 'Car :')]/following-sibling::td[1]", $reservation);
                        break;

                    case 'hotel':
                        $res["Kind"] = "R";
                        // Booking number
//                        $res["ConfirmationNumber"] = $this->http->FindSingleNode("div[contains(@id, 'Any')]//span[contains(@class, 'accueil')]/table//td[img]", $reservation, true, '/([A-Za-z0-9]+)\s*\)/ims');
                        $res["ConfirmationNumber"] = $this->http->FindSingleNode("//span[contains(text(), 'Traveller(s)')]/ancestor::tr[1]/following-sibling::tr[1]/td[3]", $reservation);
                        // HotelName
                        $res["HotelName"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[1]/strong", $reservation);
                        // Address
                        $res["Address"] = CleanXMLValue(implode(', ', $this->http->FindNodes("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[1]/div[@class = 'sit_hotel']/text()", $reservation)));
                        // Phone
                        $res["Phone"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[1]/div[@class = 'sit_hotel']/following-sibling::div[1]", $reservation, true, '/:\s*([^<]+)/ims');
//                        $res["Fax"] = $this->http->FindSingleNode(".//*[@id='A6033_18605']/font/text()[2]", $reservation, true, "/Fax: ([\d() \-]+)/");
                        // CheckInDate
                        $checkInDate = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[strong[contains(text(), 'Check-in')]]/text()[last()]", $reservation);
                        $this->logger->debug("CheckOutDate {$checkInDate}");
                        $checkInDate = $this->ModifyDateFormat($checkInDate);
                        $res["CheckInDate"] = strtotime($checkInDate);
                        // CheckOutDate
                        $checkOutDate = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[2]/td[strong[contains(text(), 'Check-out')]]/text()[last()]", $reservation);
                        $this->logger->debug("CheckOutDate {$checkOutDate}");
                        $checkOutDate = $this->ModifyDateFormat($checkOutDate);
                        $res["CheckOutDate"] = strtotime($checkOutDate);
                        // Rooms
                        $res["Rooms"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[strong[contains(text(), 'Rooms')]]/text()[last()]", $reservation);
                        // Guests
                        $res["Guests"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[1]/td[strong[contains(text(), 'Adults per room')]]/text()[last()]", $reservation);
                        // RoomType
                        $res["RoomType"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[2]/td[1]/strong", $reservation);
                        // RoomTypeDescription
                        $res["RoomTypeDescription"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[2]/td[1]/text()[last()]", $reservation);
                        // GuestNames
                        $res["GuestNames"] = $this->http->FindNodes("div[@class = 'body']//span[contains(text(), 'Traveller(s)')]/ancestor::tr[1]/following-sibling::tr/td[1]", $reservation);
                        // Total
                        $res["Total"] = $this->http->FindSingleNode("div[@class = 'body']//table[@class = 'hotel-conf-table']/tr[2]/td[strong[contains(text(), 'Price')]]/span/text()[1]", $reservation, true, '/([^\(]+)/');
                        // Currency
                        if (preg_match('/([A-Z\$]+)/', $res["Total"], $matches) && isset($matches[1]))
                            $res['Currency'] = $matches[1];
                        elseif (preg_match('/[\$]+/', $res["Total"]))
                            $res['Currency'] = 'USD';
                        elseif (preg_match('/[\€]+/', $res["Total"]))
                            $res['Currency'] = 'EUR';
                        elseif (preg_match('/[\£]+/', $res["Total"]))
                            $res['Currency'] = 'GBP';
                        else {
                            if (preg_match('/[^0-9]+/', $res["Total"], $temp))
                                $res['Currency'] = $temp[0];
                        }
                        break;

                    default:
                        $this->logger->notice("New reservation type");
                        $this->sendNotification("Egencia - egencia (UK). New reservation type found");
                }// switch ($type)

//                $this->http->Log("<pre>".var_export($res, true)."</pre>", false);

                $trip[] = $res;
            }

            return $trip;
        }

        return false;
    }*/

    public function ParseItineraryHtml($name = null)
    {
        $this->logger->notice(__METHOD__);
        $reservations = $this->http->XPath->query("//b[text() = 'Flight:' or text() = 'Hotel:' or text() = 'Car:' or text() = 'Train:']/ancestor::table[1]");
        $this->logger->debug("Total reservations found: " . $reservations->length);

        if (count($reservations)) {
            $trip = [];
            $statuses = $this->http->FindNodes("//table[@class='tripSummaryContainer']//tbody//td[@style='padding-left:1px']/span");

            foreach ($reservations as $key => $reservation) {
                $res = [];
                $res['Status'] = (isset($statuses[$key])) ? $statuses[$key] : null;
                $type = $this->http->FindSingleNode("thead/tr/td[1]/b", $reservation);

                if ($type == "Flight:") {
                    $res["Kind"] = "T";
                    $res['RecordLocator'] = $this->http->FindSingleNode(".//font[text()[contains(., 'Egencia itinerary number:')]]/b[1]", $reservation, true, null, 0);
                    $res['TripNumber'] = $this->http->FindSingleNode(".//*[contains(text(), 'Egencia itinerary number:')]/b[1]", $reservation, true, null, 0);

                    if (empty($res['RecordLocator']) && $res['Status'] == 'Not booked') {
                        $res['RecordLocator'] = CONFNO_UNKNOWN;
                    }

                    if ($res['Status'] == 'Not booked') {
                        // We don't track such reservations (as discussed in techsupport mailing list ('Outdated itineraries' subject, Sep 23))
                        $this->logger->notice("'Not booked' reservation ignored");

                        continue;
                    }
                    $recLocs = array_filter($this->http->FindNodes(".//font/text()[contains(., 'confirmation code:')]", $reservation, "/confirmation code: (\w+)$/"), 'strlen');

                    if (count($recLocs) > 0) {
                        $res["ConfirmationNumbers"] = implode(', ', $recLocs);

                        if (count($recLocs) == 1) {
                            $res['RecordLocator'] = $res["ConfirmationNumbers"];
                        }
                    }
                    $traveler = "Traveler";

                    if ($this->http->XPath->query(".//table[contains(., 'Traveller and cost summary')]")->length > 0) {
                        $traveler = "Traveller";
                    }
                    $res["Passengers"] = beautifulName($this->http->FindSingleNode(".//tr[contains(., '" . $traveler . " and cost summary') and not(.//tr)]/following-sibling::tr[1]/td[1]", $reservation, true, null, 0));

                    if (empty($res["Passengers"]) && !empty($name)) {
                        $res["Passengers"] = $name;
                    }
                    $res["BaseFare"] = str_ireplace(",", "", $this->http->FindSingleNode(".//table[contains(., '" . $traveler . " and cost summary') and not(.//table)]/tr[2]/td[last()]", $reservation, true, "/[\d\.\,]+/", 0));
                    $res["Tax"] = str_ireplace(",", "", $this->http->FindSingleNode(".//table[contains(., '" . $traveler . " and cost summary') and not(.//table)]/tr[3]/td[last()]", $reservation, true, "/[\d\.\,]+/", 0));
                    //$res["TotalCharge"] = str_ireplace(",", "", $this->http->FindSingleNode(".//table[contains(., '".$traveler." and cost summary') and not(.//table)]/tr[td[contains(., 'Total')]]/td[last()]", $reservation, true, "/[\d\.\,]+/"));
                    $res["TotalCharge"] = str_ireplace(",", "", $this->http->FindSingleNode(".//*[@id='A2003_19852']", $reservation, true, "/[\d\.\,]+/", 0));
                    $currency = $this->http->FindSingleNode(".//*[@id='A2003_19852']", $reservation, true, "/^\D+/");

                    if (trim($currency) == '$') {
                        $res["Currency"] = "USD";
                    } else {
                        $res["Currency"] = trim($currency);
                    }
                    $roots = $this->http->XPath->query(".//table[contains(., 'to') and tr[not(.//table) or tr[//span[contains(text(), 'Duration:')]]]]", $reservation);
                    $root = null;

                    foreach ($roots as $table) {
                        if ($this->http->FindSingleNode("tr[1]/td[1]//font", $table, true, "/^\w{3}\s\d{1,2}[\-\s]\w+[\-\s]\d{2,4}$/ims")) {
                            $root = $table;

                            break;
                        }

                        if ($this->http->FindSingleNode("tr[2]/td[1]//font", $table, true, "/^\w{3}\s\d{1,2}[\-\s]\w+[\-\s]\d{2,4}$/ims")) {
                            $root = $table;

                            break;
                        }
                    }
                    $date = null;
                    $idx = -1;
                    $segs = [];
                    $rows = $this->http->XPath->query("tr", $root);
                    $this->logger->debug("Total {$rows->length} segments were found");

                    foreach ($rows as $row) {
                        if (CleanXMLValue($row->nodeValue) == "") {
                            continue;
                        }

                        if (strpos($this->http->FindSingleNode("td[1]", $row), "Depart") !== false && strpos($this->http->FindSingleNode("td[3]", $row), 'Arrive') !== false) {
                            // new segment;
                            $idx++;

                            if (!empty($date) && preg_match("/^(.+)\(([A-Z]{3})\)\s*Depart (\d{1,2}:\d{2}\s*[aApP][mM])/", $this->http->FindSingleNode("td[1]", $row), $m)) {
                                $segs[$idx]["DepCode"] = $m[2];
                                $segs[$idx]["DepName"] = trim($m[1]);
                                $segs[$idx]["DepDate"] = strtotime($date . " " . $m[3]);
                            }

                            if (!empty($date) && preg_match("/^(.+)\(([A-Z]{3})\)\s*Arrive (\d{1,2}:\d{2}\s*[aApP][mM])/", $this->http->FindSingleNode("td[3]", $row), $m)) {
                                $segs[$idx]["ArrCode"] = $m[2];
                                $segs[$idx]["ArrName"] = trim($m[1]);
                                $segs[$idx]["ArrDate"] = strtotime($date . " " . $m[3]);
                                // Correct date
                                if (preg_match("/^.+\([A-Z]{3}\)\s*Arrive.+\+(\d) day/", $this->http->FindSingleNode("td[3]", $row), $m)) {
                                    $this->logger->notice("Correct date: +{$m[1]} day");
                                    $segs[$idx]["ArrDate"] = strtotime("+{$m[1]} day", $segs[$idx]["ArrDate"]);
                                }
                            }
                            $segs[$idx]["Duration"] = $this->http->FindSingleNode("td[4]", $row, true, "/Duration: (\d+hr \d+mn)/");
                            $segs[$idx]["AirlineName"] = $this->http->FindSingleNode("td[5]//img/@alt", $row);
                            $segs[$idx]["FlightNumber"] = $this->http->FindSingleNode("td[5]//text()[contains(., 'Flight:')]/following-sibling::span[1]", $row);

                            if (empty($segs[$idx]["FlightNumber"])) {
                                $segs[$idx]["FlightNumber"] = $this->http->FindSingleNode("td[5]//text()[contains(., 'Flight:')]/following-sibling::b[1]/a", $row);
                            }

                            continue;
                        }

                        if (strpos($this->http->FindSingleNode("td[1]", $row), 'Class(') !== false) {
                            $cabin = $this->http->FindSingleNode("td[1]//b[contains(text(), 'Class(')]", $row);

                            if (preg_match("/^(.+) Class\(([A-Z]*)\)/", $cabin, $m)) {
                                $segs[$idx]["Cabin"] = $m[1];
                                $segs[$idx]["BookingClass"] = $m[2];
                            } else {
                                $segs[$idx]["Cabin"] = $cabin;
                            }
                            $meal = trim($this->http->FindSingleNode("td[1]//b[contains(text(), 'Class(')]/following-sibling::text()[1]", $row), " ,");

                            if (!empty($meal)) {
                                $segs[$idx]["Meal"] = $meal;
                            }
                            $segs[$idx]["Aircraft"] = $this->http->FindSingleNode("td[1]//span[@id='planetype']", $row);

                            if ($seat = $this->http->FindSingleNode("td[1]/b[contains(text(), 'Seat ')]", $row, true, "/Seat (\d+[A-Z]+)/")) {
                                $segs[$idx]["Seats"] = $seat;
                            }
                        }

                        if ($tmp = $this->http->FindSingleNode("td[1]//font", $row, true, "/^\w{3}\s\d{1,2}[\-\s]\w+[\-\s]\d{2,4}$/ims")) {
                            $date = $tmp;

                            continue;
                        }
                    }
                    $res["TripSegments"] = $segs;
                }

                if ($type == "Car:") {
                    $res['Kind'] = 'L';
                    $res['Number'] = $this->http->FindSingleNode(".//span[@id='A2024_19513']", $reservation, true, "/([^\s]+)/");
                    $res['TripNumber'] = $this->http->FindSingleNode(".//*[contains(text(), 'Egencia itinerary number:')]/b[1]", $reservation);
                    $res['PickupDatetime'] = strtotime(str_ireplace("-", " ", $this->http->FindSingleNode(".//*[@id='A1001_7000']", $reservation)));
                    $res['DropoffDatetime'] = strtotime(str_ireplace("-", " ", $this->http->FindSingleNode(".//*[@id='A1001_7001']", $reservation)));
                    $res['PickupLocation'] = "counter in terminal, shuttle to car, " . $this->http->FindSingleNode(".//*[@id='A1001_7010']", $reservation);
                    $res['DropoffLocation'] = "counter in terminal, shuttle to car, " . $this->http->FindSingleNode(".//*[@id='A1001_7010']", $reservation);
                    $res['RenterName'] = $this->http->FindSingleNode(".//*[@id='A2024_19518']/font/text()[2]", $reservation);
                    $res['TotalTaxAmount'] = $this->http->FindSingleNode(".//*[@id='A2024_19510']", $reservation, true, "/[\d.]+/");
                    $res['TotalCharge'] = $this->http->FindSingleNode(".//*[@id='A2024_19515']/b", $reservation, true, "/[\d.]+/");
                    $currency = $this->http->FindSingleNode(".//td[@id='A2024_19515']", $reservation, true, "/^(\D+)/");

                    if ($currency == "$") {
                        $res["Currency"] = "USD";
                    } else {
                        $res["Currency"] = trim($currency);
                    }
                    $res['RentalCompany'] = $this->http->FindSingleNode(".//img[@hspace='2']/@alt", $reservation);
                    $res['CarType'] = $this->http->FindSingleNode(".//div[@id='A1001_7008']", $reservation);
                }

                if ($type == "Hotel:") {
                    $lined = $this->http->XPath->query(".//font[contains(@style, 'line-through')]", $reservation);

                    foreach ($lined as $node) {
                        $node->nodeValue = "";
                    }
                    $res["Kind"] = "R";
                    $res["ConfirmationNumber"] = str_replace("-", "", $this->http->FindSingleNode(".//*[@id='A2023_1016']", $reservation));
                    $res['TripNumber'] = $this->http->FindSingleNode(".//*[contains(text(), 'Egencia itinerary number:')]/b[1]", $reservation);

                    if (empty($res["ConfirmationNumber"])) {
                        $res["ConfirmationNumber"] = str_replace("-", "", $this->http->FindSingleNode(".//font[text()[contains(., 'Egencia itinerary number:')]]/b[1]", $reservation));
                    }
                    $res["HotelName"] = $this->http->FindSingleNode(".//*[@id='A6033_18601']", $reservation);
                    $res["Address"] = $this->http->FindSingleNode(".//*[@id='A6033_18604']/font", $reservation);
                    $res["Phone"] = $this->http->FindSingleNode(".//*[@id='A6033_18605']/font", $reservation, true, "/Tel: ([\d\(\)\s\-]+)/");
                    $res["Fax"] = $this->http->FindSingleNode(".//*[@id='A6033_18605']/font", $reservation, true, "/Fax: ([\d\(\)\s\-]+)/");
                    $res["GuestNames"] = $this->http->FindSingleNode(".//*[@id='A2023_1018']/b", $reservation);
                    //$res["Guests"] = count(explode(",", $res["GuestNames"]));
                    $res["Guests"] = $this->http->FindSingleNode(".//*[@id='A2023_1019']", $reservation, true, "/(\d+) adult/");
                    $res["CheckInDate"] = strtotime(str_ireplace("-", " ", $this->http->FindSingleNode(".//*[@id='A6033_18602']", $reservation)));
                    $res["CheckOutDate"] = strtotime(str_ireplace("-", " ", $this->http->FindSingleNode(".//*[@id='A6033_18603']", $reservation)));
                    $res["RoomType"] = $this->http->FindSingleNode(".//b[contains(text(), 'Preferences and special requests:')]/following-sibling::text()[1]", $reservation);
                    $res["RoomTypeDescription"] = $this->http->FindSingleNode(".//*[@id='A2023_1020']/text()[2]", $reservation);
                    $res["Total"] = $this->http->FindSingleNode(".//td[@id='A2023_1021']", $reservation, true, "/[\d\.]+/");
                    $currency = $this->http->FindSingleNode(".//td[@id='A2023_1021']", $reservation, true, "/^(\D+)/");

                    if ($currency == "$") {
                        $res["Currency"] = "USD";
                    } else {
                        $res["Currency"] = trim($currency);
                    }
                    $res["Rate"] = $this->http->FindSingleNode(".//td[@id='A2023_1014']", $reservation, true, "/[^\:]+$/");
                    $res["Taxes"] = $this->http->FindSingleNode(".//td[@id='A2023_1015']", $reservation, true, "/[\d\.]+/");
                }

                if ($type == "Train:") {
                    if (count($this->links)) {
                        $this->sendNotification("egencia, possible new trips type: {$type}");
                    }
                }
                $trip[] = $res;
            }

            return $trip;
        }

        return false;
    }
}
