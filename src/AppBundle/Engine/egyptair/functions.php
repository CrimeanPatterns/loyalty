<?php

class TAccountCheckerEgyptair extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.egyptairplus.com/StandardWebsite/Login.jsp?activeLanguage=EN');

        if (!$this->http->ParseForm("form1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('txtUser', $this->AccountFields['Login']);
        $this->http->Form['txtPass'] = $this->AccountFields['Pass'];
        $this->http->Form['clickedButton'] = 'Login';

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // HTTP Status 500
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 500')]")
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // 404 - File or directory not found.
            || $this->http->FindSingleNode("//title[contains(text(), '404 - File or directory not found.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (strpos($this->http->Error, 'Network error 7 - Failed to connect to www.egyptairplus.com port 80:') !== false) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode('//a[contains(@href, "code=Logout")]/@href')) {
            return true;
        }

        if ($message = $this->http->FindPreg("/(Your user id or password is not correct)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked
        if ($message = $this->http->FindPreg("/document\.getElementById\(\'errorPanelDiv\'\)\.innerHTML\s*=\s*\'UtilityPack\.HException\:\s*ACCOUNT_LOCKED\'/ims")) {
            throw new CheckException("Your account has been locked.", ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindPreg("/document\.getElementById\(\'errorPanelDiv\'\)\.innerHTML\s*=\s*\'([^\']+)\'/ims")) {
            throw new CheckException('Your user id or password is not correct', ACCOUNT_INVALID_PASSWORD);
        }

        $this->checkErrors();

        return true;
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[@class="LoginContent"]/h4[@class="LoginName"]', null, false)));
        //# Tier
        $this->SetProperty('Tier', $this->http->FindSingleNode('//div[@class="LoginContent"]/div[@class="LoginDetails"]', null, false, '/Tier (.*)/ims'));
        //# Card Number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//div[@class="LoginContent"]/div[@class="LoginDetails"]', null, false, '/(\d+)/ims'));
        //# Balance - Award Miles
        $balance = $this->http->FindSingleNode('//div[@class="LoginContent"]/div[@class="LoginAwd"]', null, false, '/[\d,]+/ims');

        if ($balance !== null) {
            $balance = str_replace(',', '', $balance);
            $this->SetBalance($balance);
        }

        $this->http->getURL('https://www.egyptairplus.com/StandardWebsite/StatusMilesToExpire.jsp?activeLanguage=EN');
        $expirationDate = $this->http->FindSingleNode('//table[@class="GridTableSmall"]/tr[2]/td[1]', null, false);
        $expirationDate = DateTime::createFromFormat('d/m/Y', $expirationDate);

        if ($expirationDate !== false && strtotime($expirationDate->format('m/d/y')) !== false) {
            $this->SetExpirationDate(strtotime($expirationDate->format('m/d/y')));
        }
        //# Award Miles To Expire
        $this->SetProperty('AwardMilesToExpire', $this->http->FindSingleNode('//table[@class="GridTableSmall"]/tr[2]/td[2]', null, false));
        //# Total Award Miles
        $this->SetProperty('TotalAwardMiles', $this->http->FindSingleNode('//div[contains(text(), "Total Award Miles")]/../div[2]', null, false));
        //# Total Status Miles
        $this->SetProperty('TotalStatusMiles', $this->http->FindSingleNode('//div[contains(text(), "Total Status Miles")]/../div[2]', null, false));
        //# Tier Count
        $this->SetProperty("TierCount", $this->http->FindSingleNode('//div[contains(text(), "Tier Count")]/../div[2]', null, false));

        $this->http->GetURL('https://www.egyptairplus.com/StandardWebsite/TierUpgradeCalculator.jsp?activeLanguage=EN');
        //# Miles To Next Tier
        $this->SetProperty('MilesToNextTier', $this->http->FindPreg('/need to collect\s+([0-9]+)\s+/ims'));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["SuccessURL"] = "https://www.egyptairplus.com/StandardWebsite/rd.jsp?pageURL=https%3A%2F%2Fwww.egyptairplus.com%2FMS_Member_WebSite%2Ffrequent.jsp%3Fcode%3DStatusMilesToExpire%26lang%3Den";

        return $arg;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.egyptair.com/en/Book/Pages/my-reservations.aspx';
    }

    public function notifications()
    {
        $this->logger->notice("notifications");
        $this->sendNotification("egyptair - failed to retrieve itinerary by conf #");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        // set cookies
        $this->http->setCookie("SelectLanguageName", "English", "www.egyptair.com");
        $this->http->setCookie("SelectCountry", "1", "www.egyptair.com");
        $this->http->setCookie("SelectCountryName", "United States", "www.egyptair.com");
        $this->http->setCookie("SelectLanguage", "1", "www.egyptair.com");
        // get URL
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        // fields
        $reservationNumber = $this->http->FindSingleNode("//input[contains(@name, 'txtReservationNumber')]/@name");
        $this->http->Log(">>> $reservationNumber");
        $LastName = $this->http->FindSingleNode("//input[contains(@name, 'txtLastName')]/@name");
        $this->http->Log(">>> $LastName");
        $btnSubmit = $this->http->FindSingleNode("//input[contains(@name, 'btnSubmit')]/@name");
        $this->http->Log(">>> $btnSubmit");

        if (!$this->http->ParseForm('aspnetForm') || !isset($reservationNumber, $LastName)) {
            return $this->notifications();
        }
        $this->http->SetInputValue($reservationNumber, $arFields['ConfNo']);
        $this->http->SetInputValue($LastName, $arFields['LastName']);
        $this->http->Form[$btnSubmit] = 'Display';
        $this->http->PostForm();
        // next form
        $eventTarget = $this->http->FindPreg("/document\.getElementById\('__EVENTTARGET\'\)\.value=\"([^\"]+)/ims");
        $formAction = $this->http->FindPreg("/document\.forms\[0\]\.action\s*=\s*\"([^\"]+)/ims");

        if (!$this->http->ParseForm('aspnetForm') || !isset($eventTarget, $formAction)) {
            return $this->notifications();
        }
        $this->http->SetInputValue('__EVENTTARGET', $eventTarget);
        $this->http->NormalizeURL($formAction);
        $this->http->FormURL = $formAction;
        $this->http->PostForm();
        // next form
        $postURL = $this->http->FindSingleNode("//input[@id = 'ENC_URL']/@value");

        if (!$this->http->ParseForm('form1') || !isset($postURL)) {
            return $this->notifications();
        }
        $this->http->FormURL = $postURL;
        $this->http->PostForm();
        // We are unable to find this confirmation number...
        if ($messages = $this->http->FindPreg("/WDSError.add\(\"([^\"]+)/ims")) {
            return $messages;
        }

        // if ($this->http->FindSingleNode("//td[contains(text(), 'Booking reservation number')]/span"))
        //     $it = $this->ParseConfirmationItinerary();
        $it = $this->ParseConfirmationItineraryJson();

        return null;
    }

    public function ArrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    public function ParseConfirmationItineraryJson()
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => 'T'];

        $data = $this->http->FindPreg('/PlnextPageProvider.init\(\{\s*config\s*:\s*(\{.+?\})\s*,\s*pageEngine/');
        $data = $this->http->JsonLog($data, false, true);
        $reservationInfo = $this->ArrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'RESERVATION_INFO']);

        if (!$reservationInfo) {
            $this->logger->info('Json has changed, cannot find RESERVATION_INFO');

            return [];
        }
        $listItinerary = $this->ArrayVal($data, ['pageDefinitionConfig', 'pageData', 'business', 'ItineraryList', 'listItinerary']);

        if (!$listItinerary) {
            $this->logger->info('Json has changed, cannot find listItinerary');

            return [];
        }

        // RecordLocator
        $result['RecordLocator'] = ArrayVal($reservationInfo, 'locator');
        // Passengers
        $passengers = [];

        foreach (ArrayVal($reservationInfo, 'liTravellerInfo') as $trav) {
            $lastName = $this->ArrayVal($trav, ['identity', 'lastName']);
            $firstName = $this->ArrayVal($trav, ['identity', 'firstName']);
            $name = beautifulName(sprintf('%s %s', $firstName, $lastName));
            $passengers[] = $name;
        }
        $result['Passengers'] = $passengers;
        // ReservationDate
        $result['ReservationDate'] = strtotime(ArrayVal($reservationInfo, 'creationDate'));
        // TripSegments
        $tripSegments = [];
        $segments = $listItinerary;

        foreach ($segments as $seg) {
            $seg = $this->ArrayVal($seg, ['listSegment', 0]);

            if (!$seg) {
                $this->logger->info('Json has changed, cannot find listSegment');

                continue;
            }
            $ts = [];
            // DepCode
            $ts['DepCode'] = $this->ArrayVal($seg, ['beginLocation', 'locationCode']);
            // ArrCode
            $ts['ArrCode'] = $this->ArrayVal($seg, ['endLocation', 'locationCode']);
            // DepartureTerminal
            $ts['DepartureTerminal'] = $this->ArrayVal($seg, ['beginTerminal']);
            // ArrivalTerminal
            $ts['ArrivalTerminal'] = $this->ArrayVal($seg, ['endTerminal']);
            // FlightNumber
            $ts['FlightNumber'] = $this->ArrayVal($seg, ['flightNumber']);
            // AirlineName
            $ts['AirlineName'] = $this->ArrayVal($seg, ['airline', 'code']);
            // Stops
            $ts['Stops'] = $this->ArrayVal($seg, ['nbrOfStops']);
            // Duration
            $dur = ArrayVal($seg, 'flightTime', 0);

            if ($dur) {
                $ts['Duration'] = date('G\h i\m', $dur / 1000);
            }
            // DepDate
            $ts['DepDate'] = strtotime($this->ArrayVal($seg, ['beginDate']));
            // ArrDate
            $ts['ArrDate'] = strtotime($this->ArrayVal($seg, ['endDate']));
            // Aircraft
            $ts['Aircraft'] = $this->ArrayVal($seg, ['equipment', 'name']);
            // Cabin
            $ts['Cabin'] = $this->ArrayVal($seg, ['listCabin', 0, 'name']);
            $tripSegments[] = $ts;
        }
        $result['TripSegments'] = $tripSegments;

        return $result;
    }

    public function ParseConfirmationItinerary()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(), 'Booking reservation number')]/span");
        // Status
        $result['Status'] = $this->http->FindSingleNode("//td[contains(text(), 'Trip status')]/span");
        // Passengers
        $result['Passengers'] = array_map(function ($elem) {
            if ($elem == 'Contact information') {
                return '';
            } else {
                return beautifulName($elem);
            }
        }, $this->http->FindNodes("//table[@id = 'pax1']//tr[td[2]]/td/span[contains(@class, 'textBold')]"));
        // remove empty elements
        $result['Passengers'] = array_diff($result['Passengers'], ['']);
        $passengersCount = count($result['Passengers']);
        $this->http->Log("Total {$passengersCount} passenger(s) found");
        // AccountNumbers
        if ($accountNumbers = $this->http->FindNodes("//td[contains(text(), 'Frequent flyer(s)')]/following-sibling::td[1]")) {
            $result['AccountNumbers'] = implode(', ', $accountNumbers);
        }
        // TotalCharge
        $segment['TotalCharge'] = $this->http->FindSingleNode("//div[@id = 'sh_fltPayment']//td[contains(text(), 'Payment')]/following-sibling::td[1]", null, true, '/([\d\.\,]+)/ims');
        // Currency
        $segment['Currency'] = $this->http->FindSingleNode("//div[@id = 'sh_fltPayment']//td[contains(text(), 'Payment')]/following-sibling::td[1]", null, true, '/([A-Z]+)/ims');

        $nodes = $this->http->XPath->query("//div[@id = 'sh_fltItinerary']/table");
        $this->http->Log("Found {$nodes->length} legs");

        for ($i = 0; $i < $nodes->length; $i++) {
            // headline
            $title = $this->http->FindSingleNode('tr/th', $nodes->item($i));
            $title = str_replace('to', '-', $title);
            $this->http->Log("Leg # {$i}: $title");
            // segments
            $segments = $this->http->XPath->query("tr[contains(td, 'Flight')]/following-sibling::tr[1]", $nodes->item($i));
            $this->http->Log("Found {$segments->length} segments");

            for ($j = 0; $j < $segments->length; $j++) {
                $segment = [];
                $seg = $segments->item($j);
                // Date
                $date = $this->http->FindSingleNode("tr[contains(td, 'Flight')]/td[2]", $nodes->item($i), true, null, $j);
                $this->http->Log("Date: {$date}");
                // Status
                $segment['Status'] = $this->http->FindSingleNode('td[1]', $seg);

                // DepDate
                $depTime = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Departure:')]]/following-sibling::td[1]", $seg);
                $segment['DepDate'] = strtotime($date . ' ' . $depTime);
                // DepName
                $depName = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Departure:')]]/following-sibling::td[2]/text()[1]", $seg);
                $segment['DepName'] = $depName;
                // DepCode
                $segment['DepCode'] = $this->findAirCode($depName);

                // ArrDate
                $arrTime = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Arrival:')]]/following-sibling::td[1]", $seg, true, '/(\d{2}\:\d{2})/ims');
                $this->http->Log("arrTime: {$arrTime}");
                $arrDate = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Arrival:')]]/following-sibling::td[1]", $seg, true, '/\+\s*(\d+)\s*day/ims');
                $this->http->Log("arrDate: + {$arrDate} day(s)");

                if ($arrDate) {
                    $segment['ArrDate'] = strtotime("+ {$arrDate} day", strtotime($date . ' ' . $arrTime));
                } else {
                    $segment['ArrDate'] = strtotime($date . ' ' . $arrTime);
                }
                // ArrName
                $arrName = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Arrival:')]]/following-sibling::td[2]/text()[1]", $seg);
                $segment['ArrName'] = $arrName;
                // ArrCode
                $segment['ArrCode'] = $this->findAirCode($arrName);

                // Stops
                if ($stops = $this->http->FindSingleNode("td[2]/table//td[span[contains(text(), 'Arrival:')]]/following-sibling::td[2]/span", $seg, true, '/includes\s*(\d+)\s*(?:technical|)\s*stop/ims')) {
                    $segment['Stops'] = $stops;
                }
                // Duration
                $segment['Duration'] = $this->http->FindSingleNode("td[2]/table//td[contains(text(), 'Duration:')]/following-sibling::td[1]", $seg);
                // Aircraft
                $segment['Aircraft'] = $this->http->FindSingleNode("td[2]/table//td[contains(text(), 'Aircraft:')]/following-sibling::td[1]", $seg);
                // Cabin (Fare type)
                $segment['Cabin'] = $this->http->FindSingleNode("td[2]/table//td[contains(text(), 'Fare type:')]/following-sibling::td[1]", $seg);
                // FlightNumber
                $segment['FlightNumber'] = $this->http->FindSingleNode("td[2]/table//td[contains(text(), 'Airline:')]/following-sibling::td[1]", $seg, true, '/(MS[\w\d]+)\s*$/');
                // AirlineName
                $segment['AirlineName'] = $this->http->FindSingleNode("td[2]/table//td[contains(text(), 'Airline:')]/following-sibling::td[1]", $seg, true, '/(.+)\s*MS[\w\d]+\s*$/');
                // Seats
                $detailsXpath = '//th[contains(text(), "' . addslashes($title) . '")]/parent::tr[@class = "boundTitle"]/following-sibling::tr[@class = "segmentTitle"][' . ($j + 1) . ']';
                $segment['Seats'] = $this->http->FindSingleNode($detailsXpath . '/td[2]');
                // Meal
                $segment['Meal'] = $this->http->FindSingleNode($detailsXpath . '/td[3]');

                for ($k = 1; $k < $passengersCount; $k++) {
                    $detailsXpathNextItem = $detailsXpath . "//following-sibling::tr[{$k}]";
                    $seat = $this->http->FindSingleNode($detailsXpathNextItem . "/td[2]");

                    if (!empty($seat)) {
                        $segment['Seats'] .= ', ' . $seat;
                    }
                    $meal = $this->http->FindSingleNode($detailsXpathNextItem . "/td[3]");

                    if (!empty($meal)) {
                        $segment['Meal'] .= ', ' . $meal;
                    }
                }

                $result['TripSegments'][] = $segment;
            }// for ($i = 0; $i < $segments->length; $i++)
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    public function findAirCode($airportCode)
    {
        preg_match('/(.+),\s*.+-\s*([^\,]+)/ims', $airportCode, $parts);

        if (isset($parts[1])) {
            if (isset($parts[2])) {
                $city = addslashes(CleanXMLValue($parts[1]));
                $air = addslashes(CleanXMLValue($parts[2]));
                $criteria = ['CityName' => $city, 'AirName' => $air];
                $airport = $this->db->getAirportBy($criteria, true);
                $code = ArrayVal($airport, 'AirCode');
                $this->logger->info('getAirportBy:');
                $this->logger->info(var_export([
                    'criteria' => $criteria, 'partial' => true, 'code' => $code,
                ], true), ['pre' => true]);
            }// if (empty($code) && isset($parts[2]))

            if (empty($code)) {
                $city = addslashes(CleanXMLValue($parts[1]));
                $criteria = ['CityName' => $city];
                $airport = $this->db->getAirportBy($criteria, true);
                $code = ArrayVal($airport, 'AirCode');
                $this->logger->info('getAirportBy:');
                $this->logger->info(var_export([
                    'criteria' => $criteria, 'partial' => true, 'code' => $code,
                ], true), ['pre' => true]);
            }// if (empty($code))
        }// if (isset($parts[1])

        if (empty($code)) {
            $code = $airportCode;
        }

        return $code;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 10,
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Passenger's last name",
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }
}
