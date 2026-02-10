<?php

class TAccountCheckerChoiceMobile extends TAccountCheckerChoice
{
    protected $collectedHistory = false;
    protected $endHistory = false;

    public function LoadLoginForm()
    {
        $this->http->Log(">>>> Mobile Version");
        $this->http->removeCookies();
        $this->http->GetURL('https://m.choicehotels.com/cpacct');

        if (!$this->http->ParseForm(null, 1, "//form[@action = 'https://m.choicehotels.com/cpacct']")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->Form['login'] = 'Login';

        return true;
    }

    public function checkErrors()
    {
        //# The site is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The site is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Failure of server APACHE bridge')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")
            || $this->http->FindPreg("/Error 404--Not Found/ims")) {
            throw new CheckException('The website is temporarily unavailable. Please try again later', ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        //# An error occurred while processing your request
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['PreloadAsImages'] = true;

        return $arg;
    }

    public function Login()
    {
        $this->http->PostForm();
        $this->checkErrors();

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout=true')]/@href")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Error logging in')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//li[contains(text(), 'Invalid username')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Invalid password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

//        if($message = $this->http->FindPreg("/Sign in was not successful/ims"))
//            throw new CheckException("Sign in was not successful", ACCOUNT_INVALID_PASSWORD);
//
//        if($message = $this->http->FindPreg("/>Join now/ims"))
//            throw new CheckException("Please go to Choice website and apply for their reward program", ACCOUNT_PROVIDER_ERROR);
//
//        if(!$this->http->FindSingleNode("//a[contains(text(), 'My Account')]"))
//            throw new CheckException("Sign in was not successful", ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
//        ## User is not member loyalty program
//        if($this->http->FindSingleNode("//a[contains(text(), 'Join now')]"))
//            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);

        //# Balance - Choice Privileges Points
        if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'You have')]", null, true, "/You have\s*(\d+[\.\,]?\d*)/ims"))) {
            $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'You have')]", null, true, "/You have\s*(\d+[\.\,]?\d*)/ims"));
        }
        //# Member Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//td[contains(text(),"Member Name:")]/following::td[1]')));
        //# Member Number
        $this->SetProperty("Number", $this->http->FindSingleNode('//td[contains(text(),"Member Number:")]/following::td[1]'));
        //# Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode('//td[contains(text(),"Member Since:")]/following::td[1]'));
        //# Elite Status
        $this->SetProperty("ChoicePrivileges", $this->http->FindPreg("/Status:\s*<\/td>\s*<td[^>]+>([^<]+)/ims"));
        //# Points expiring
        $this->SetProperty("PointsExpiring", $this->http->FindSingleNode('(//td[contains(text(),"Points expiring")]/following::td[1])[1]'));
        //# YTD eligible nights to next status
        $this->SetProperty("Eligible", $this->http->FindSingleNode('//td[contains(text(),"YTD eligible nights toward ")]/following::td[1]'));

        // expiration date
        $expirationDate = null;
        $d = $this->http->FindSingleNode('(//td[contains(text(),"Points expiring")]/..)[1]', null, true, '/Points expiring ([^:]+)/');

        if (isset($d)) {
            $d = strtotime($d);

            if ($d !== false) {
                $expirationDate = $d;
            }
        }

        // stay activity, parse to get expiration date
        $links = $this->http->FindNodes("//a[contains(@href, 'statementID')]/@href");

        if (isset($links)) {
            //get current activity
            /*foreach($links as $link){
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);

                $activities = $this->http->XPath->query("//caption[contains(text(), 'Points Earned')]/following-sibling::tr/td[1]");
                if ($activities->length == 0)
                    $activities = $this->http->XPath->query("//caption[contains(text(), 'Points Redeemed')]/following-sibling::tr/td[1]");
//                if ($activities->length == 0)
//                    $activities = $this->http->XPath->query("//table[caption[contains(text(), 'Adjustments'')]]/following-sibling::table//tr[td]/td[1]");
                if ($activities->length == 0)
                    $this->http->Log(">>> Activity is not found");
                else
                    break;
            }*/

            foreach ($links as $link) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);

                if (!isset($expirationDate)) {
                    $expires = $this->http->XPath->query("//caption[contains(text(), 'Point Expirations')]/ancestor::table[1]//tr[td[2]]");

                    for ($n = 0; $n < $expires->length; $n++) {
                        $exp = $this->http->FindSingleNode("td[1]", $expires->item($n), true, "/points\s*expiring\s*on\s*\D*(\d*\/\d*\/\d*)/ims");
                        $pointsExpiring = $this->http->FindSingleNode("td[2]", $expires->item($n));

                        if (strtotime($exp)) {
                            $eDate = strtotime($exp);
                            $this->http->Log("Date: $exp - " . var_export(strtotime($eDate), true), true);

                            if ($eDate < $expirationDate || !isset($expirationDate)) {
                                //# Expiration Date
                                $expirationDate = $eDate;
                                //# Points expiring
                                $this->SetProperty("PointsExpiring", $pointsExpiring);

                                break;
                            }// if ($eDate < $expirationDate || !isset($expirationDate))
                        }// if (preg_match("/POINTS\s*EXPIRING\s*\D*(\d*\/\d*\/\d*)/ims",$expires[$n],$match)){
                    }// for($n = 0; $n < count($expires); $n++)
                }// if (!isset($expirationDate))
                else {
                    break;
                }
            }// foreach($links as $link)

            // refs #5406, #6731
            /*$pointsEarned = preg_replace("/.*-/ims", "", $this->http->FindSingleNode("//caption[contains(text(), 'Points Earned')]/following-sibling::tr[1]/td[1]"));
            $pointsRedeemed = preg_replace("/.*-/ims", "", $this->http->FindSingleNode("//caption[contains(text(), 'Points Redeemed')]/following-sibling::tr[1]/td[1]"));
            $expPointsEarned = $this->DateUnixTime($pointsEarned, "Points Earned");
            $expPointsRedeemed = $this->DateUnixTime($pointsRedeemed, "Points Redeemed");
            if ($expPointsEarned > $expPointsRedeemed){
                $lastActivity = $pointsEarned;
                $exp = $expPointsEarned;
            }
            else{
                $lastActivity = $pointsRedeemed;
                $exp = $expPointsRedeemed;
            }
            ## Last Activity
            $this->SetProperty("LastActivity", $lastActivity);
            if ($exp != false)
                $exp = strtotime("+18 months", $exp);
            $this->http->Log(">>> POINTS EXPIRING = ".date("m/d/Y", $expirationDate));
            $this->http->Log(">>> [LastActivity] + 18 months = ".date("m/d/Y", $exp));
            if (($exp != false) && (!isset($expirationDate) || (isset($expirationDate) && ($expirationDate > $exp))))
                $expirationDate = $exp;*/

            //# BEGINNING BALANCE
            $this->SetProperty("BeginningBalance", $this->http->FindSingleNode('//td[contains(text(),"Beginning Balance:")]/following::td[1]'));
            //# POINTS EARNED
            $this->SetProperty("PointsEarned", $this->http->FindSingleNode('//td[contains(text(),"Points Earned:")]/following::td[1]'));
            //# POINTS REDEEMED
            $this->SetProperty("PointsRedeemded", $this->http->FindSingleNode('//td[contains(text(),"Points Redeemed:")]/following::td[1]'));
            //# POINTS ADJUSTED
            $this->SetProperty("PointsAdjusted", $this->http->FindSingleNode('//td[contains(text(),"Adjustments")]/following::td[1]'));
            //# Points expiring
            $this->SetProperty("PointsExpiring", $this->http->FindSingleNode("(//td[contains(text(), 'points expiring')]/following-sibling::td[1])[1]"));
        }

        if (isset($expirationDate)) {
            $this->SetExpirationDate($expirationDate);
        }
    }

    public function DateUnixTime($activity, $caption)
    {
        if (empty($activity)) {
            return false;
        }
        $this->http->Log($caption . ": {$activity} - " . var_export(strtotime($activity), true), true);
        $activity = strtotime($activity);

        return $activity;
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
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ParseItineraries()
    {
        $result = [];

        $this->http->GetURL('http://m.choicehotels.com/cpacct');

        if ($this->http->FindSingleNode("//span[@class='no-reservation'][contains(text(), 'No reservations were found.')]")) {
            return $this->noItinerariesArr();
        }

        //# All Reservations
        $links = [];
        $nodes = $this->http->XPath->query("//li[@class = 'chh-reservation']");
        $this->http->Log("Total nodes found: " . $nodes->length);

        for ($n = 0; $n < $nodes->length; $n++) {
            // url
            $link = $this->http->FindSingleNode("a[1]/@href", $nodes->item($n));
            // ConfirmationNumber
            $confirmationNumber = $this->http->FindSingleNode("p[contains(text(), 'Confirmation Number')]", $nodes->item($n), true, "/Confirmation\s*Number\s*:\s*([^<]+)/ims");
            // Status
            $status = $this->http->FindSingleNode("p[contains(text(), 'Status:')]", $nodes->item($n), true, "/Status\s*:\s*([^<]+)/ims");

            if (strtolower($status) == 'cancelled') {
                $result[] = ['ConfirmationNumber' => $confirmationNumber, 'Cancelled' => true];

                continue;
            } elseif (isset($link)) {
                $links[] = $link;
            }
        }

        foreach ($links as $url) {
            //# Parse
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $conf = $this->ParseItineraryMobile();

            if (count($conf) > 0) {
                $result[] = $conf;
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://secure.choicehotels.com/ires/en-US/html/ViewResForm";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$http->MultiSiteCookies = true; ???
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("viewresbyconf")) {
            $this->sendNotification("choice - failed to retrieve itinerary by conf #", 'all', true,
                "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

            return null;
        }
        $this->http->FormURL = "https://secure.choicehotels.com/ires/en-US/html/DisplayReservation";
        $this->http->Form["ienc"] = "UTF-8";
        $this->http->Form["confnum"] = $arFields["ConfNo"];
        $this->http->Form["last_name"] = $arFields["LastName"];

        if (!$this->http->PostForm()) {
            return null;
        }

        if (preg_match("/<div class=\"error\"><img src=\"\/images\/eflash.gif\">([^<]+)</ims", $this->http->Response['body'], $arMatch)) {
            return $arMatch[1];
        }
        $it = $this->ParseConfirmationChoice();
        $it = [$it];

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Dates" => "PostingDate",
            "Description"    => "Description",
            "Points"         => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->FilterHTML = false;
        $this->http->Log("[History start date: " . intval($startDate) . " ]");
        $result = [];
        $startTimer = microtime(true);

        $this->http->GetURL('https://m.choicehotels.com/cpacct');

        $activityNodes = $this->http->FindNodes('//ul[@class="chh-list-noimage"]/li/a/@href');

        for ($i = 0; $i < count($activityNodes) && !$this->endHistory && !$this->collectedHistory; $i++) {
            $this->http->GetURL("https://m.choicehotels.com" . $activityNodes[$i]);
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate));
        }

        usort($result, function ($a, $b) {
            $key = 'Activity Dates';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");
        $this->http->Log("[Total: " . count($result) . "]");

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query('//caption[contains(text(), "Points Earned")]/following::tr[td]');
        $this->http->Log("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);

            $dateStr = $this->http->FindSingleNode("td[1]", $node, true, "/-?\s*([^-]+)$/ims");
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->http->Log("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Activity Dates'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[2]", $node);
            $lines = preg_replace('/\s+/', '', $this->http->FindNodes("td[3]/text()", $node));
            $points = 0;

            foreach ($lines as $line) {
                $points += str_replace(',', '', $line);
            }
            $result[$startIndex]['Points'] = preg_replace('/\.00$/', '', number_format($points, 2, '.', ','));
            $startIndex++;
        }

        return $result;
    }

    private function ParseItineraryMobile()
    {
        $result = [];

        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindSingleNode("//span[contains(text(), 'Confirmation Number:')]/following::span[1]");

//        $result['GuestNames'] = $this->http->FindSingleNode('//td[@class="mod-reserve-detl-name"]');
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode("//div[contains(@class, 'chh-reservation-hotel-name')]");
        // CheckInDate
        $date = $this->http->FindSingleNode("//td[contains(text(), 'Check-In:')]/following-sibling::td[1]/text()[1]");
        $time = $this->http->FindSingleNode("//td[contains(text(), 'Check-In:')]/following-sibling::td[1]/span[contains(@class, 'time')]");

        if (isset($date)) {
            $result['CheckInDate'] = strtotime("$date $time");
        }
        // CheckOutDate
        $date = $this->http->FindSingleNode("//td[contains(text(), 'Check-Out:')]/following-sibling::td[1]/text()[1]");
        $time = $this->http->FindSingleNode("//td[contains(text(), 'Check-Out:')]/following-sibling::td[1]/span[contains(@class, 'time')]");

        if (isset($date)) {
            $result['CheckOutDate'] = strtotime("$date $time");
        }
        // Address
        $arAddress = $this->http->FindNodes('//div[contains(@class, "hotel-address")]/div');

        if (isset($arAddress[0])) {
            $result['Address'] = trim(implode(", ", $arAddress));
        }
//        //DetailedAddress
//        $result["DetailedAddress"] = array(array(
//            "AddressLine" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-street")]'),
//            "CityName" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-city")]'),
//            "PostalCode" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-postcode")]'),
//            "StateProv" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-state")]'),
//            "Country" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-country")]'),
//        ));
        // Phone
//        $result['Phone'] = $this->http->FindSingleNode("//div[@class='hotel-details-wrap']/div[@class='summary-content-wrapper']/ul/li[contains(text(), 'Phone:')]", null, true, '/Phone: (.*)/im');
        // RateType
//        $arType = $this->http->FindNodes('//dd[@class="mod-reserve-detl-rate-program"]/node()');
//        if (isset($arType[0]))
//            $result['RateType'] = trim(implode(" ", $arType));
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode("//span[contains(text(), 'Cancellation Deadline:')]/following-sibling::span[1]");
        // Rate, RoomType, RoomTypeDescription, Guests, Kids
        $nodes = $this->http->XPath->query('//table[@class="room-description-table"]/tbody/tr'); // count rooms
        // Rooms
        $result['Rooms'] = $this->http->FindSingleNode("//td[contains(text(), '# of Rooms:')]/following-sibling::td[1]", null, true, "/(\d+)/ims");
        // Guests
        $result['Guests'] = $this->http->FindSingleNode("//td[contains(text(), '# of Guests:')]/following-sibling::td[1]", null, true, "/(\d+)\s*Adult/ims");
        // Kids
        $result['Kids'] = $this->http->FindSingleNode("//td[contains(text(), '# of Guests:')]/following-sibling::td[1]", null, true, "/(\d+)\s*Child/ims");

        if (!isset($result['Kids'])
            && $this->http->FindSingleNode("//td[contains(text(), '# of Guests:')]/following-sibling::td[1]", null, true, "/(No Children)/ims")) {
            $result['Kids'] = '0';
        }
        // Rate
        $result['Rate'] = $this->http->FindSingleNode("(//div[contains(text(), 'Nightly Rate')])[1]", null, true, "/Nightly\s*Rate\s*([^<]+)/ims");
        // RoomType
        $result['RoomType'] = $this->http->FindSingleNode("(//div[contains(text(), 'Nightly Rate')]/following-sibling::h4[1])[1]", null, true);
        // RoomTypeDescription
        $result['RoomTypeDescription'] = $this->http->FindSingleNode("(//div[contains(text(), 'Nightly Rate')]/parent::*/text()[last()])[1]", null, true);
        // Cost
        $result['Cost'] = $this->http->FindSingleNode("//div[contains(text(), 'Sub Total')]/span");

        if (!isset($result['Cost'])) {
            $result['Cost'] = $this->http->FindPreg("/Sub\s*Total:\s*<[^>]+>\s*<[^\"]+\"sub-total\">([^<]+)/ims");
        }
        // Taxes
        $result['Taxes'] = $this->http->FindSingleNode("//div[contains(text(), 'Estimated Tax')]/span");

        if (!isset($result['Taxes'])) {
            $result['Taxes'] = $this->http->FindPreg("/Estimated\s*Tax:\s*<[^>]+>\s*<[^\"]+\"sub-total\">([^<]+)/ims");
        }
        // Total
        $result['Total'] = $this->http->FindSingleNode("//td[contains(text(), 'Estimated Total')]/following-sibling::td[1]/text()[1]");
        // Currency
        $result['Currency'] = $this->http->FindSingleNode("//td[contains(text(), 'Estimated Total')]/following-sibling::td[1]/text()[1]", null, true, '/([A-Z]{3})/ims');
        // AccountNumber
        $result['AccountNumbers'] = $this->http->FindSingleNode("//td[contains(text(), 'CP #:')]/following-sibling::td[1]");
        // Status
        $result['Status'] = beautifulName($this->http->FindSingleNode("//h3[contains(text(), 'Your reservation is')]", null, true, "/Your\s*reservation\s*is\s*([^<\.]+)/ims"));

        return $result;
    }

    private function ParseConfirmationChoice()
    {
        $result = [];

        // ConfirmationNumber
        $result['ConfirmationNumber'] = $this->http->FindSingleNode('//td[@class="mod-reserve-detl-confirmation"]');

        $result['GuestNames'] = beautifulName($this->http->FindSingleNode('//td[@class="mod-reserve-detl-name"]'));
        // HotelName
        $result['HotelName'] = $this->http->FindSingleNode('//div[@class="hotel-details-wrap"]/div[@class="summary-content-wrapper"]/h4/a');

        if (is_null($result['HotelName'])) {
            $result['HotelName'] = $this->http->FindSingleNode('//div[@class="hotel-details-wrap"]/div[@class="summary-content-wrapper"]/div/h4/a');
        }
        // CheckInDate
        $date = $this->http->FindSingleNode('//td[contains(text(), "Check-in Date")]/following-sibling::td[contains(@class, "check-date")]');
        $time = $this->http->FindSingleNode('//td[contains(text(), "Check-in Date")]/following-sibling::td[contains(@class, "check-time")]/span');

        if (isset($date)) {
            $result['CheckInDate'] = strtotime("$date $time");
        }
        // CheckOutDate
        $date = $this->http->FindSingleNode('//td[contains(text(), "Check-out Date")]/following-sibling::td[contains(@class, "check-date")]');
        $time = $this->http->FindSingleNode('//td[contains(text(), "Check-out Date")]/following-sibling::td[contains(@class, "check-time")]/span');

        if (isset($date)) {
            $result['CheckOutDate'] = strtotime("$date $time");
        }
        // Address
        $arAddress = $this->http->FindNodes('//span[contains(@class, "hotel-addr-")]');

        if (isset($arAddress[0])) {
            $result['Address'] = trim(implode(", ", $arAddress));
        }
        //DetailedAddress
        $result["DetailedAddress"] = [[
            "AddressLine" => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-street")]'),
            "CityName"    => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-city")]'),
            "PostalCode"  => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-postcode")]'),
            "StateProv"   => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-state")]'),
            "Country"     => $this->http->FindSingleNode('//span[contains(@class, "hotel-addr-country")]'),
        ]];
        // Phone
        $result['Phone'] = $this->http->FindSingleNode("//div[@class='hotel-details-wrap']/div[@class='summary-content-wrapper']/ul/li[contains(text(), 'Phone:')]", null, true, '/Phone: (.*)/im');
        // RateType
        $arType = $this->http->FindNodes('//dd[@class="mod-reserve-detl-rate-program"]/node()');

        if (isset($arType[0])) {
            $result['RateType'] = trim(implode(" ", $arType));
        }
        // CancellationPolicy
        $result['CancellationPolicy'] = $this->http->FindSingleNode('//p[@class="cancel-deadline"]');
        // Rate, RoomType, RoomTypeDescription, Guests, Kids
        $nodes = $this->http->XPath->query('//table[@class="room-description-table"]/tbody/tr'); // count rooms
        // Rooms
        $result['Rooms'] = $nodes->length;

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $nights = $this->http->FindSingleNode('td[@class="nights-and-rate"]/descendant::td[@class="nights"]', $nodes->item($i));
                $price = $this->http->FindSingleNode('td[@class="nights-and-rate"]/descendant::td[@class="nightly-rate"]', $nodes->item($i));
                $roomType = $this->http->FindSingleNode('td[@class="room-description"]/descendant::td[@class="room-details"]/h4', $nodes->item($i));
                $roomDesc = $this->http->FindSingleNode('td[@class="room-description"]/descendant::td[@class="room-details"]/div', $nodes->item($i));
                $roomAdult = $this->http->FindSingleNode('td[@class="adults"]', $nodes->item($i));
                $roomKids = $this->http->FindSingleNode('td[@class="children"]', $nodes->item($i));

                $arRate[$i] = $nights . ' : ' . $price;
                $roomTypeAr[$i] = $roomType;
                $roomDescAr[$i] = $roomDesc;
                $roomAdultAr[$i] = $roomAdult;
                $roomKidsAr[$i] = $roomKids;
            }

            if (isset($arRate[0])) {
                $result['Rate'] = implode(' | ', $arRate);
            }

            if (isset($roomTypeAr[0])) {
                $result['RoomType'] = implode(' | ', $roomTypeAr);
            }

            if (isset($roomDescAr[0])) {
                $result['RoomTypeDescription'] = implode(' | ', $roomDescAr);
            }

            if (isset($roomAdultAr[0])) {
                $result['Guests'] = array_sum($roomAdultAr);
            }

            if (isset($roomKidsAr[0])) {
                $result['Kids'] = array_sum($roomKidsAr);
            }
        }

        // Cost
        $result['Cost'] = $this->http->FindSingleNode('//div[@class="sub-totals"]/ul/li[1]', null, true, '/(\d+.\d+)/');
        // Taxes
        $result['Taxes'] = $this->http->FindSingleNode('//div[@class="sub-totals"]/ul/li[2]', null, true, '/(\d+.\d+)/');
        // Total
        $result['Total'] = $this->http->FindSingleNode('//li[@class="estimated-total"]', null, true, '/(\d+.\d+)/');
        // Currency
        $result['Currency'] = $this->http->FindSingleNode('//li[@class="currency"]', null, true, '/\((.*)\)/im');
        // AccountNumber
        $result['AccountNumbers'] = $this->http->FindSingleNode("//td[b[contains(text(), 'Choice Privileges® Number:')]]/following-sibling::td[1]");
        // Status
        $result['Status'] = $this->http->FindSingleNode('//dt[contains(text(),"Status")]/following-sibling::dd');

        if (!isset($result['Status'])) {
            $result['Status'] = $this->http->FindSingleNode("//td[contains(text(), 'Reservation Status:')]/following-sibling::td[1]");
        }

        return $result;
    }
}
