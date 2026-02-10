<?php

class TAccountCheckerAirtran extends TAccountChecker
{
    protected $collectedHistory = false;
    protected $endHistory = false;

    private $Logins = 0;
    private $res = null;

    public function checkTechError()
    {
        if ($message = $this->http->FindPreg("/currently offline for maintenance/i")) {
            throw new CheckException("The AirTran Airways Reservations system is currently offline for maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        //# System error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'system error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server error
        if ($message = $this->http->FindPreg("/(The page cannot be displayed because an internal server error has occurred.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Message: Object reference not set to an instance of an object
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Object reference not set to an instance of an object')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The service is unavailable
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->LogHeaders = true;
        $this->http->removeCookies();
        $this->http->keep_alive = true;
        //		$this->http->GetURL("https://tickets.airtran.com/Login.aspx");
        $this->http->GetURL("http://www.aplusrewards.com/aplus/member_home.aspx");

        if (!$this->http->ParseForm("SkySales")) {
            return $this->checkTechError();
        }
        $this->http->FormURL = 'https://tickets.airtran.com/Login.aspx';
        $this->http->Form[urldecode('MemberLoginLoginView$TextBoxUserID')] = $this->AccountFields['Login'];
        $this->http->Form[urldecode('MemberLoginLoginView$PasswordFieldPassword')] = $this->AccountFields['Pass'];
        $this->http->Form[urldecode('__EVENTTARGET')] = 'MemberLoginLoginView$LinkButtonLogIn';
        $this->http->Form[urldecode('__EVENTARGUMENT')] = '';

        return true;
    }

    public function Login()
    {
        $this->http->PostForm();
        $this->http->Log("PostLoginURL:[" . $this->http->currentUrl() . "]"); // must https://www.aplusrewards.com/aplus/Member_Home.aspx, retry?

        if ($message = $this->http->FindSingleNode('//h1[contains(text(),"rewards member log")]/following::div[1][@id="formbox"]/p')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/The system did not recognize your account information/msi")) {
            throw new CheckException("We're sorry. The system did not recognize your account information. Please check your entry and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/NullReferenceException/msi")) {
            throw new CheckException("We're sorry. The system did not recognize your account information. Please check your entry and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/AirTran Airways - Validation Error/msi")
            || $this->http->currentUrl() == 'https://tickets.airtran.com/ValidationError.aspx') {
            throw new CheckException("Please check your entry to remove any unusual characters and try again.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/The password you entered is incorrect/msi")) {
            throw new CheckException("The password you entered is incorrect.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/<h2>change password/i")) {
            throw new CheckException("You should change your password", ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkTechError();

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL($home = 'https://www.aplusrewards.com/aplus/Member_Home.aspx');

        $this->http->Log('PEAR error checking...');

        if ($this->http->currentUrl() == $home && (trim($this->http->Response['body']) == '' || $this->http->FindPreg("/^pear error/ims"))) {
            $this->http->Log('Current page is empty. Previous page...');
            $this->http->GetURL('https://tickets.airtran.com/Search.aspx');
        }

        if ($this->http->currentUrl() == 'https://tickets.airtran.com/Search.aspx') {
            $this->http->GetURL('http://tickets.airtran.com/RewardSearch.aspx');
            $this->SetProperty("Number", $this->http->FindPreg("/<\/b>\s*\((\d+)\)\s*<\/td>/ims"));
            $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Available A+ credits")]/following::td[1]'));
        } else {
            $this->SetBalance($this->http->FindPreg("/<span id=\"ucRewardSummary_lblAvailCred\">([\-\d\.\,]+)<\/span>/msi"));
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[@id="ucRewardSummary_lblFullName"]')));
            $this->SetProperty("Number", $this->http->FindPreg("/<span id=\"ucRewardSummary_lblFTNumber\">([^<]+)<\/span>/ims"));
            $elite = $this->http->FindSingleNode('//tr[@id="ucRewardSummary_trDisplayElite"]');

            if (isset($elite)) {
                $this->SetProperty("Status", $this->http->FindSingleNode('//tr[@id="ucRewardSummary_trDisplayElite"]/td[2]'));
                //# Status Expiration Date
                $this->SetProperty("StatusExpirationDate", $this->http->FindSingleNode('//tr[@id="ucRewardSummary_trEliteExpire"]/td[2]'));
                //# A+ Flight credits needed to renew status
                $this->SetProperty("RenewStatus", $this->http->FindSingleNode('//tr[@id="ucRewardSummary_trEliteRemain"]/td[2]/span'));
            } else {
                $this->SetProperty("Status", "Member");
                $this->SetProperty("LifetimeCredits", $this->http->FindPreg("/<span id=\"ucRewardSummary_lblLifeCred\">([^<]+)<\/span>/ims"));
                $this->SetProperty("Earned90Days", $this->http->FindPreg("/<span id=\"ucRewardSummary_lbl90Earned\">([^<]+)<\/span>/ims"));
                $this->SetProperty("Needed90Days", $this->http->FindPreg("/<span id=\"ucRewardSummary_lbl90Need\">([^<]+)<\/span>/ims"));
                $this->SetProperty("Earned365Days", $this->http->FindPreg("/<span id=\"ucRewardSummary_lbl365Earned\">([^<]+)<\/span>/ims"));
                $this->SetProperty("Needed365Days", $this->http->FindPreg("/<span id=\"ucRewardSummary_lbl365Need\">([^<]+)<\/span>/ims"));
                $this->SetProperty("AvailCredits", $this->http->FindPreg("/<span id=\"ucRewardSummary_lblAvailCred\">([^<]+)<\/span>/ims"));
                $this->SetProperty("LifetimeCredits", $this->http->FindPreg("/<span id=\"ucRewardSummary_lblLifeCred\">([^<]+)<\/span>/ims"));
            }

            //# Retry login
            if (!isset($this->Properties['Number']) && $this->Logins < 3) {
                $this->http->Log("Retry login - " . var_export($this->Logins, true), true);
                $this->Logins++;
                sleep(15);
                $this->LoadLoginForm();
                $this->Login();
                $this->Parse();
            }

            $this->http->GetURL("https://www.aplusrewards.com/aplus/rewards.aspx");
            $xpath = new DOMXPath(TidyDoc($this->http->Response['body']));
            $nodes = $xpath->query("//table[@id='ucRewards_dgStatement']/tr/td[7]/nobr");

            for ($n = 0; $n < $nodes->length; $n++) {
                $s = CleanXMLValue($nodes->item($n)->nodeValue);
                $d = strtotime($s);

                if (!isset($minDate) || ($d < $minDate)) {
                    $minDate = $d;
                }
            }

            if (isset($minDate)) {
                $this->SetExpirationDate($minDate);
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://tickets.airtran.com/BookingList.aspx';

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://tickets.airtran.com/BookingList.aspx');

        if ($noItin = $this->http->FindPreg("/No bookings found for current travel\./ims")) {
            return $this->noItinerariesArr();
        }

        $nodes = $this->http->FindNodes('//h2[contains(text(),"current booking")]/../../td/../../tr/td/a[@id="ATBookingListBookingListView_HyperLinkView"]/@href');
        $numNodes = count($nodes);

        $this->http->Log('Found: ' . $numNodes . ' nodes');
        $buggyLocators = ['FECN7N', 'HIYEME', 'JIN1FR', 'K5IC4L', 'O84J9F', 'O97PQS', 'PD5L7T', 'PE1S4Q', 'PF7JJA', 'PHFF3U', 'Q7VWUI', 'RF554N', 'VF4BYD', 'WBEL3Z', 'WDYVRW', 'WJ3M2R', 'Z5M4UR'];

        if ($this->http->FindNodes('//td[not(.//td) and (' . implode(' or ', array_map(function ($locator) {return 'contains(., \'' . $locator . '\')'; }, $buggyLocators)) . ')]')
        || $this->http->FindNodes('//a[contains(text(), "log in")]')
        || $this->http->FindNodes('//*[id = "agency_info" and contains(., "AirTran Airways")]')
        ) {
            $this->sendNotification("Airtran crazy trips");

            return [];
        }

        $listPage = ArrayVal($this->http->Response, 'body');

        $result = [];

        if ($numNodes > 0) {
            for ($i = 0; $i < $numNodes; $i++) {
                if (preg_match("/\('(\w+)'/", $nodes[$i], $match)) {
                    $oneParam = $match[1];
                }

                if (preg_match("/'(\w+:\w+)'\)/", $nodes[$i], $match)) {
                    $twoParam = $match[1];
                }

                $this->http->FormURL = 'https://tickets.airtran.com/BookingList.aspx';
                $this->http->Form[urldecode('__EVENTTARGET')] = $oneParam;
                $this->http->Form[urldecode('__EVENTARGUMENT')] = $twoParam;
                $this->http->PostForm();

                $result[] = $this->ParseConfirmationAirtran();
            }
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "Origin" => [
                "Caption"  => "Origin",
                "Type"     => "string",
                "Size"     => 3,
                "Options"  => $this->OriginSelect(),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://tickets.airtran.com/RetrieveBooking.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $form[urldecode('ATBookingRetrieveInputRetrieveBookingView$ORIGINCITY1')] = $arFields['Origin'];
        $form[urldecode('ATBookingRetrieveInputRetrieveBookingView$PAXFIRSTNAME1')] = $arFields['FirstName'];
        $form[urldecode('ATBookingRetrieveInputRetrieveBookingView$PAXLASTNAME1')] = $arFields['LastName'];
        $form[urldecode('ATBookingRetrieveInputRetrieveBookingView$CONFIRMATIONNUMBER1')] = $arFields['ConfNo'];
        $form[urldecode('__EVENTTARGET')] = 'ATBookingRetrieveInputRetrieveBookingView$LinkButtonRetrieve';
        $form[urldecode('__EVENTARGUMENT')] = '';
        $form[urldecode('ATBookingRetrieveInputRetrieveBookingView$TEXTBOXLAST4')] = '';

        $this->http->PostURL($this->ConfirmationNumberURL($arFields), $form);

        if ($error = $this->http->FindPreg("/The itinerary details you entered do not match the information in the itinerary that you requested/ims")) {
            return $error;
        }

        if (!$this->http->FindPreg("/flight details/ims")) {
            $this->sendNotification("airtran - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['FirstName']} / {$arFields['LastName']}<br/>Origin: {$arFields['Origin']}");

            return null;
        }

        $it = $this->ParseConfirmationAirtran();

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Posting Date"      => "PostingDate",
            "Details"           => "Description",
            "Credits Earned"    => "Miles",
            "Credits Available" => "Info",
            "Expiring Date"     => "Info",
            "Bonus"             => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        if ($this->http->currentUrl() != 'https://www.aplusrewards.com/aplus/rewards.aspx') {
            $this->http->GetURL("https://www.aplusrewards.com/aplus/rewards.aspx");
        }
        // all history
        $this->http->Log("Get all history");
        $this->http->FormURL = 'https://www.aplusrewards.com/aplus/rewards.aspx';
        $this->http->Form['__EVENTTARGET'] = 'ucRewards:lbtnAll';
        $this->http->PostForm();

        $page = 0;

        do {
            $page++;
            $this->http->Log("[Page: {$page}]");

            if ($page > 1) {
                $this->http->Form['__EVENTTARGET'] = 'ucRewards:lbtnStatementNext';
                $this->http->PostForm();
            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

            if ($page > 30) {
                $this->http->Log("too many pages");

                break;
            }
        } while (
            $this->http->ParseForm("frm")
            && sizeof($this->http->FindNodes("//a[@id='ucRewards_lbtnStatementNext']"))
            && !$this->collectedHistory
            && !$this->endHistory
        );

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@id='ucRewards_dgStatement']/tr");

        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");

            for ($i = 0; $i < $nodes->length; $i++) {
                if ($this->http->FindSingleNode("td[7]", $nodes->item($i)) == 'Expiring Date') {
                    continue;
                }
                $dateStr = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->http->Log("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Posting Date'] = $postDate;
                $result[$startIndex]['Details'] = $this->http->FindSingleNode("td[4]", $nodes->item($i));

                if (preg_match('/Bonus/ims', $result[$startIndex]['Details'])) {
                    $result[$startIndex]['Bonus'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                } else {
                    $result[$startIndex]['Credits Earned'] = $this->http->FindSingleNode("td[5]", $nodes->item($i));
                }
                $result[$startIndex]['Credits Available'] = $this->http->FindSingleNode("td[6]", $nodes->item($i));
                $result[$startIndex]['Expiring Date'] = $this->http->FindSingleNode("td[7]", $nodes->item($i));
                $startIndex++;
            }

            if (!empty($this->res) && array_values($this->res) == array_values($result)) {
                $this->http->Log("endHistory");
                $this->endHistory = true;

                return [];
            }
            $this->res = $result;
        }

        return $result;
    }

    public function ParseConfirmationLetter()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = ['Kind' => 'T'];
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue']);

        $result['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "Confirmation number:")]', null, true, '/Confirmation number:\s*(.+)/ims');
        // find passenger names between "Passengers" and "Flight Information" headings, assume that passenger name does not contain digits which in opposite address line does
        $passengers = [];
        $accountNumbers = [];
        $passengerLines = array_filter($http->FindNodes('//text()[contains(., "Passengers:") or contains(., "Passenger:")]/following::text()[count(following::text()[contains(., "Flight Information")]) = 1 and string-length(.) > 0]'), 'strlen');

        foreach ($passengerLines as $passengerLine) {
            // VASILIY KURDIN 9999999999
            if (preg_match('/^([^,0-9]+)\s*(\d+)?$/', $passengerLine, $matches)) {
                $passengers[] = trim($matches[1]);

                if (isset($matches[2])) {
                    $accountNumbers[] = $matches[2];
                }
            } else {
                // break after first non-name match
                break;
            }
        }
        $result['Origin'] = preg_replace('/\s+\d+/ims', '', end($passengerLines));

        if (!empty($passengers)) {
            $result['Passengers'] = array_filter($passengers);
        }
        $result['AccountNumbers'] = implode(', ', $accountNumbers);

        $segments = [];
        $flightHeaderNodes = $xpath->query('//text()[contains(., "Flight Information:")]/following::*[count(following::text()[contains(., "Payment Information:")]) = 1 and contains(string(), "Flight ") and not(contains(string(), "Departing"))]');

        foreach ($flightHeaderNodes as $flightHeaderIndex => $flightHeaderNode) {
            // parse header
            $segment = [];

            if (preg_match('/(.+)\s+Flight\s+(.+)/ims', CleanXMLValue($flightHeaderNode->nodeValue), $matches)) {
                $segment['DepDay'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];
            }
            // get text of next node for xpath-query limits
            if ($flightHeaderNodes->item($flightHeaderIndex + 1)) {
                $flightHeaderText = CleanXMLValue($flightHeaderNodes->item($flightHeaderIndex + 1)->nodeValue);
            } else {
                $flightHeaderText = 'Payment Information:';
            }
            $flightSegmentRows = array_filter($http->FindNodes("following-sibling::node()[count(
				following::*[
					name() = '{$flightHeaderNode->tagName}' and
					contains(php:functionString('CleanXMLValue', string()), '{$flightHeaderText}')
				]
				) = 1]", $flightHeaderNode), 'strlen');

            foreach ($flightSegmentRows as $flightSegmentRow) {
                // [Non-Stop] Seat: 12C / 34C / 56A / 78A
                if (preg_match('/\[([^\]]+)\]( Seats?: (.+))?/ims', $flightSegmentRow, $matches)) {
                    if ($matches[1] === 'Non-Stop') {
                        $segment['Stops'] = 0;
                    }
                    // 12C / 34C / 56A / 78A
                    if (isset($matches[2]) && preg_match_all('/\s(\w+)\s/ims', $matches[3], $seatsMatches)) {
                        $segment['Seats'] = implode(', ', $seatsMatches[1]);
                    }
                }
                // Departing Perm, PR (PEE) at 05:50 AM
                if (preg_match('/Departing (.+)\s*\((\w+)\) at (.+)/ims', $flightSegmentRow, $matches)) {
                    $segment['DepName'] = $matches[1];
                    $segment['DepCode'] = $matches[2];
                    $segment['DepDate'] = strtotime($segment['DepDay'] . ' ' . $matches[3]);
                }
                // Departing Perm, PR (PEE) at 05:50 AM
                if (preg_match('/Arriving (.+)\s*\((\w+)\) at (.+)/ims', $flightSegmentRow, $matches)) {
                    $segment['ArrName'] = $matches[1];
                    $segment['ArrCode'] = $matches[2];
                    $segment['ArrDate'] = strtotime($segment['DepDay'] . ' ' . $matches[3]);
                }
            }

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        }

        $total = $this->coalesce([
            // Total $100500.42 USD
            $http->FindSingleNode('//b[contains(text(), "Payment Information")]/following-sibling::text()[contains(., "Total")]', null, false, '/Total\s+((\S)?(\d+.\d+|\d+)(\s+\S+)?)/ims'),
            // 100500.42
            $http->FindSingleNode('//td[not(.//td) and contains(string(), "Ticket Total")]/following-sibling::td[1]'),
        ]);

        if (isset($total) && preg_match('/(Total\s+)?((\S)?(\d+.\d+|\d+)(\s+(\w+))?)/ims', $total, $matches)) {
            if (isset($matches[3]) && $matches[3] == '$') {
                $result['Currency'] = 'USD';
            }
            $result['TotalCharge'] = $matches[4];

            if (isset($matches[6])) {
                $result['Currency'] = $matches[6];
            }
        }
        $result['BaseFare'] = $http->FindSingleNode('//td[not(.//td) and contains(string(), "Air Fare")]/following-sibling::td[1]');

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return $result;
    }

    public function ParseInternationalFlightInformation()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $result = ['Kind' => 'T'];
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions(['stripos', 'CleanXMLValue']);
        $result['RecordLocator'] = $http->FindSingleNode("//*[contains(text(), 'Confirmation number:')]", null, false, '/Confirmation number:\s+(\w+)/ims');

        $passengers = [];
        $seats = [];
        $passengerLines = $http->FindNodes('//*[contains(text(), "Passenger(s) and Seat(s)")]/following-sibling::text()');

        foreach ($passengerLines as $passengerLine) {
            // Vasiliy Kurdin - Seats: 2D 2D
            if (preg_match('/(.+)\s+-\s+Seats:\s+(.+)/ims', $passengerLine, $matches)) {
                $passengers[] = $matches[1];
                $seats[] = explode(' ', trim($matches[2]));
            }
        }

        if (!empty($passengers)) {
            $result['Passengers'] = array_filter($passengers);
        }
        $segments = [];
        $fromNodes = $xpath->query('//text()[contains(., "From:") and following-sibling::text()[1][contains(., "To:")]]');

        foreach ($fromNodes as $id => $fromNode) {
            $segment = [];
            $baseDate = null;
            // MARCH 99, 2023, Flight 1030
            if (preg_match('/(.+),\s+Flight\s+(\w+)/ims', $http->FindSingleNode('preceding-sibling::*[contains(text(), ", Flight")][1]', $fromNode), $matches)) {
                $baseDate = $matches[1];
                $segment['FlightNumber'] = $matches[2];
            }
            // From: Perm-BlahBlah International Airport (PEE) / Departing: 830AM
            if (preg_match('|From:\s+(.+)\s+\((\w+)\)\s+/\s+Departing:\s+(.+)|ims', CleanXMLValue($fromNode->nodeValue), $matches)) {
                // 830AM
                if (preg_match('/(\d?\d)(\d\d)(\s+)?(\w+)/ims', $matches[3], $timeMatches)) {
                    $time = "{$timeMatches[1]}:{$timeMatches[2]} {$timeMatches[3]}";
                    $segment['DepDate'] = strtotime($baseDate . ' ' . $time);
                }
                $segment['DepName'] = $matches[1];
                $segment['DepCode'] = $matches[2];
            }

            if (preg_match('|To:\s+(.+)\s+\((\w+)\)\s+/\s+Arriving:\s+(.+)|ims', $http->FindSingleNode('following-sibling::text()[1][contains(., "To:")]', $fromNode), $matches)) {
                // 830AM
                if (preg_match('/(\d?\d)(\d\d)(\s+)?(\w+)/ims', $matches[3], $timeMatches)) {
                    $time = "{$timeMatches[1]}:{$timeMatches[2]} {$timeMatches[4]}";
                    $segment['ArrDate'] = strtotime($baseDate . ' ' . $time);
                }
                $segment['ArrName'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
            }
            $flightSeats = [];

            foreach ($seats as $passenger) {
                if (isset($seats[$id])) {
                    $flightSeats[] = $passenger[$id];
                }
            }
            $segment['Seats'] = implode(', ', $flightSeats);

            if (!empty($segment)) {
                $segments[] = $segment;
            }
        }

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return $result;
    }

    public function coalesce($values)
    {
        $filtered = array_values(array_filter($values, 'strlen'));

        if (!empty($filtered)) {
            return $filtered[0];
        } else {
            return null;
        }
    }

    public function getEmailType()
    {
        if ($this->http->FindPreg("/AirTran Airways Confirmation/ims") || $this->http->FindNodes('//img[contains(@src, "reservations/a-head-blue.git")]')) {
            return "ConfirmationLetter";
        }

        if ($this->http->FindPreg('/Information About Your Upcoming International Trip/ims')) {
            return "InternationalFlightInformation";
        }

        return "Undefined";
    }

    private function ParseConfirmationAirtran()
    {
        // Air Trip

        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode('//b[@class="confirmnumber"]');
        // Passengers
        $passName = $this->http->FindNodes('//tr[@class="subheadrow"]/following-sibling::tr/td[1][not(@colspan)]');

        if (isset($passName[0])) {
            $result['Passengers'] = beautifulName(implode(', ', $passName));
        }
        // AccountNumber
        $passAcc = $this->http->FindNodes('//tr[@class="subheadrow"]/following-sibling::tr/td[2][not(@colspan)]');
        $passAccN = [];

        for ($p = 0; $p < count($passAcc); $p++) {
            if (preg_match('/(\d+)/', $passAcc[$p], $temp)) {
                $passAccN[] = $temp[1];
            }
        }

        if (isset($passAccN[0])) {
            $result['AccountNumbers'] = implode(', ', $passAccN);
        }
        // BookDate
        //$result['BookDate'] = $this->http->FindSingleNode('//p[strong[contains(text(),"Booking date")]]', null, true, '/Booking date:\s*(.*?)\s*Status:/ims');
        // TotalCharge
        //$result['TotalCharge'] = $this->http->FindSingleNode('//td[@id="pricesum_totalPrice"]/b', null, true, '/(\d+.\d+)/');
        // Currency
        $result['Currency'] = 'USD';
        // Tax
        //if ($tax = $this->http->FindSingleNode('//td[@id="pricesum_fareFees"]', null, true, '/(\d+.\d+)/'))
        //	    $result['Tax'] = $tax;
        $totalPrice = 0;
        // Status
        $result['Status'] = $this->http->FindSingleNode('//strong[contains(text(),"Status:")]/following-sibling::node()[1]');
        // ReservationDate
        $reservDate = strtotime($this->http->FindSingleNode('//strong[contains(text(),"Booking date:")]/following-sibling::text()[2]'));

        if ($reservDate) {
            $result['ReservationDate'] = $reservDate;
        }

        // Air Trip Segments

        $tripSeg = [];
        $nodes = $this->http->XPath->query('//tr[@class="flight1"]');

        for ($i = 0; $i < $nodes->length; $i++) {
            $n = $i + 1;
            // FlightNumber
            $tripSeg[$i]['FlightNumber'] = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/td[4]', null, true, '/(\d+)/');
            // DepCode
            $tripSeg[$i]['DepCode'] = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/td[1]', null, true, '/\(([A-Z]{3})\)/');
            // DepName
            $tripSeg[$i]['DepName'] = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/td[1]/b');
            // DepDate
            $depDate = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/preceding-sibling::tr[1]/td[1]/span/b');

            if (isset($depDate)) {
                $frDate = $depDate;
            } elseif (!isset($depDate) && isset($frDate)) {
                $depDate = $frDate;
            }
            $depTime = $this->http->FindSingleNode('//tr[@class="flight2"][' . $n . ']/td[1]');
            $depDateTime = strtotime($depDate . ' ' . $depTime);

            if ($depDateTime) {
                $tripSeg[$i]['DepDate'] = $depDateTime;
            }
            // ArrCode
            $tripSeg[$i]['ArrCode'] = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/td[3]', null, true, '/\(([A-Z]{3})\)/');
            // ArrName
            $tripSeg[$i]['ArrName'] = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/td[3]/b');
            // ArrDate
            $arrTime = $this->http->FindSingleNode('//tr[@class="flight2"][' . $n . ']/td[3]', null, true, '/(\d+\:\d+\s+\w+)/i');
            $arrDateTime = strtotime($depDate . ' ' . $arrTime);
            //if ($this->http->FindSingleNode('//tr[@class="flight2"]['.$n.']/td[2][contains(text(), "(next day)")]'))
            if ($arrDateTime < $depDateTime) {
                $arrDateTime = strtotime("+1 day", $arrDateTime);
            }

            if ($arrDateTime) {
                $tripSeg[$i]['ArrDate'] = $arrDateTime;
            }

            if ($airline = $this->http->FindSingleNode("//tr[@class='flight2'][" . $n . "]/td[4]", null, true, "/Operated by\s+([^\#]+)/ims")) {
                $tripSeg[$i]['AirlineName'] = $airline;
            }
            // Price
            $price = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/preceding-sibling::tr[1]/td[2]/span[1]', null, true, '/^\$([\d\.]+)/');

            if (isset($price)) {
                $totalPrice += $price;
            }
            // Cabin
            $cabin = $this->http->FindSingleNode('//tr[@class="flight1"][' . $n . ']/preceding-sibling::tr[1]/td[2]/span[2]');

            if (isset($cabin)) {
                $frCabin = $cabin;
            } elseif (!isset($cabin) && isset($frCabin)) {
                $cabin = $frCabin;
            }

            if (isset($cabin)) {
                $tripSeg[$i]['Cabin'] = $cabin;
            }
            // Seats
            $seats = $this->http->FindNodes('//tr[@class="subheadrow"]/following-sibling::tr/td[@align="center"][' . $n . ']');

            if (isset($seats[0])) {
                if ($seats[0] != "---") {
                    $tripSeg[$i]['Seats'] = implode(', ', $seats);
                }
            }
        }

        if ($totalPrice > 0) {
            $result["TotalCharge"] = $totalPrice;
        } elseif ($price = $this->http->FindSingleNode("//td[@id='pricesum_totalPrice']/b", null, true, '/\$([\d\.]+)/')) {
            $result['TotalCharge'] = $price;
        }
        $result['TripSegments'] = $tripSeg;

        return $result;
    }

    private function OriginSelect()
    {
        $result = [];

        $cache = Cache::getInstance()->get('airtran_origins');

        if ($cache !== false) {
            $result = $cache;
        } else {
            $browser = new HttpBrowser("none", new CurlDriver());
            $browser->GetURL('https://tickets.airtran.com/RetrieveBooking.aspx');

            preg_match_all('/Station\("([A-Z]{3})",\s*"[A-Z]{0,3}",\s*"(.*?)"/ims', $browser->Response['body'], $match);

            if (isset($match[1])) {
                $optionsVal = $match[1];
            }

            if (isset($match[2])) {
                $optionsTxt = $match[2];
            }

            if (isset($optionsVal) && isset($optionsTxt)) {
                $numOptions = count($optionsVal);

                for ($i = 0; $i < $numOptions; $i++) {
                    $result[$optionsVal[$i]] = $optionsTxt[$i] . ' (' . $optionsVal[$i] . ')';
                }

                if ($numOptions != 0) {
                    Cache::getInstance()->set('airtran_origins', $result, 3600);
                }
            }
        }

        return $result;
    }

    /*
        function ParsePlanEmail(PlancakeEmailParser $parser){
            $emailType = $this->getEmailType();
            $result = null;
            switch ($emailType) {
                case "ConfirmationLetter":
                    $result = $this->ParseConfirmationLetter();
                break;
                case "InternationalFlightInformation":
                    $result = $this->ParseInternationalFlightInformation();
                break;
                default:
                    $result = "Undefined email type";
                break;
            }
            if($this->RefreshData && !empty($result['RecordLocator']) && !empty($result['PassengersArray']) && !empty($result['Origin'])){
                // get last name of the first passenger
                $nameParts = explode(' ', $result['PassengersArray'][0]);

                // "Origin LastName FirstName ConfirmationNumber"
                $errorMsg = $this->CheckConfirmationNumberInternal([
                        'ConfirmationNumber' => $result['RecordLocator'],
                        'FirstName' => strtoupper($nameParts[0]),
                        'LastName'  => strtoupper(end($nameParts)),
                        'Origin' => $this->getOriginCode($result['Origin'], $this->OriginSelect()),
                    ], $itinerary);

                if($errorMsg === null && $this->checkItineraries($itinerary)){
                    $result = $itinerary;
                }
            }
            return array(
                'parsedData' => $result,
                'emailType' => $emailType
            );
        }
    */
    private function getOriginCode($needle, $origins)
    {
        if (empty($needle) || !is_array($origins) || empty($origins)) {
            return null;
        }
        $addressExplodeFunction = function ($name) {
            // Boston, MA (BOS)
            $parts = explode(', ', $name);
            $state = null;

            if (isset($parts[1])) {
                // MA
                $state = explode(' ', $parts[1])[0];
            }

            return [
                'FullName' => $name,
                'City'     => strtolower($parts[0]),
                'State'    => strtolower($state),
            ];
        };
        $needle = $addressExplodeFunction(strtolower($needle));
        $origins = array_map($addressExplodeFunction, $origins);
        // search by FulLName
        foreach ($origins as $code => $data) {
            if (stripos($data['FullName'], $needle['FullName']) !== false) {
                return $code;
            }
        }
        // fallback to search by state
        foreach ($origins as $code => $data) {
            if (strcasecmp($data['State'], $needle['State']) === 0) {
                return $code;
            }
        }

        return null;
    }
}
