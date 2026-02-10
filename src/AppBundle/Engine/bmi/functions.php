<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBmi extends TAccountChecker
{
    use ProxyList;

    protected $collectedHistory = true;

    public function LoadLoginForm()
    {
        throw new CheckException("In accordance with the notice given to members of the Diamond Club on 31 August 2016, closure of the Diamond Club took effect on 30 November 2016. Any Destinations Miles that were not transferred to Avios in the British Airways Executive Club have now expired.", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->GetURL("https://www.diamondclub.org/iloyal-MemberPortal/mportal/loginFlow");

        if (!$this->http->ParseForm('loginModel')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('userpassword', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        //# Website is currently unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'website is currently unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Our systems are temporarily unavailable for essential maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(Our systems are temporarily unavailable for essential maintenance\.[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//img[@src = 'images/under_cons.jpg']/@src")) {
            throw new CheckException("The website is under maintenance and will be available soon.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Internal Server Error
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            //# Unexpected Server Error
            || $this->http->FindPreg("/(Unexpected Server Error)/ims")
            //# Gateway Time-out
            || $this->http->FindSingleNode("//h1[contains(text(), 'Gateway Time-out')]")
            // HTTP Status 404
            || $this->http->FindSingleNode("//h1[contains(text(), 'HTTP Status 404')]")
            //# Bad Gateway
            || $this->http->FindSingleNode("//h1[contains(text(), 'Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The website you are trying to reach seems too busy at the moment
        if ($message = $this->http->FindPreg("/(The website you are trying to reach seems too busy at the moment\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("http://diamondclub.org/");
        //# It works!
        if ($this->http->FindSingleNode("//h1[contains(text(), 'It works!')]")
            //# Not Found
            || $this->http->FindSingleNode("//h1[contains(text(), 'Not Found')]")) {
            throw new CheckException("BMI website had a hiccup, please try to check your balance at a later time.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->FilterHTML = false;

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# Access is allowed
        if ($this->http->FindSingleNode("//input[contains(@onclick, 'logout')]/@onclick")) {
            return true;
        }
        $this->http->FilterHTML = true;

        //# Invalid credentials
        if ($message = $this->http->FindPreg("/(The details you have entered are incorrect. Please Check and Try again.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(Incorrect Username\/password\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //  Your Diamond Club account has been closed due to inactivity and any remaining miles forfeited.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your Diamond Club account has been closed due to inactivity and any remaining miles forfeited.')]", null, true, "/^\s*\*\s*(.+)/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Unexpected Server Error
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Unexpected Server Error')]", null, true, "/^\s*\*\s*(.+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Your Diamond Club Account is closed
        if ($message = $this->http->FindPreg("/(Your Diamond Club Account is closed\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The Diamond Club has now closed outside of the UK, Australia & New Zealand
        if ($message = $this->http->FindPreg("/(The Diamond Club has now closed outside (?:of the UK, Australia & New Zealand|the UK\. )[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindNodes('//a[contains(text(), "Log In")]')) {
            throw new CheckRetryNeededException(3, 10);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //# Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h3[contains(text(), 'Welcome')]/span")));
        //# Balance - Destinations miles
        $balance = $this->http->FindSingleNode("//h3[contains(text(), 'Miles Balance')]/span");
        $this->SetBalance($balance);
        $this->SetProperty("DestinationMiles", $balance);
        //# Member number
        $this->SetProperty("Number", $this->http->FindSingleNode("//h3[contains(text(), 'Your Membership Number')]/span"));
    }

//    function ParseItineraries()
//    {
//        $result = array();
//        $this->http->GetURL('https://www.flybmi.com/bmi/en-gb/my-bookings/view-my-booking.aspx');
//
//        if ($this->http->FindPreg('/You do not have any current bookings/ims'))
//            return $this->noItinerariesArr();
//
//        $links = $this->http->XPath->query("//div[@id='CurrentBookings']/table/tr[position()>1]/td[3]/input[@type='submit']");
//        for ($i = 0; $i < $links->length; $i++)
//        {
//            $url = $links->item($i)->getAttribute('onclick');
//            if (preg_match("/http[^']*/ims", $url, $url_match))
//            {
//                $this->http->GetURL($url_match[0]);
//                $itinerary = $this->CheckItinerary();
//                if(!empty($itinerary))
//                    $result[] = $itinerary;
//            }
//        }
//        return $result;
//    }

    public function CheckItinerary()
    {
        $result = [];
        $error = $this->http->FindSingleNode("//*[@class=OmErrorMessage]");

        if ($error) {
            return $result;
        }
        $result['RecordLocator'] = $this->http->FindPreg("/Booking reference\s([^\s]*)\s/ims");

        if (empty($result['RecordLocator'])) {
            return $result;
        }
        // AccountNumbers
        $accounts = $this->http->FindNodes('//div[contains(text(), "Frequent flyer number")]/following-sibling::div[1]');

        if (isset($accounts[0])) {
            $result['AccountNumbers'] = implode(', ', $accounts);
        }
        // Passengers
        $passengers = $this->http->FindNodes('//div[@class="Row Name"]/div[1]');

        if (isset($passengers[0])) {
            $result['Passengers'] = implode(', ', $passengers);
        }

        ///meal is common
        $meal = trim($this->http->FindSingleNode("//div[@id='ctl00_ctl00_BaseContent_Column1B_PassengerDetails_Passengers_ctl00_SelectedMealRow']/div[1]/div[@class='Control']"));
        ///trip segments
        $result['TripSegments'] = [];
        $segment_nodes = $this->http->XPath->query("//div[@class='Module MMB_FlightDetails']//div[@class='Leg']//tbody/tr");

        for ($i = 0; $i < $segment_nodes->length; $i++) {
            $tripSegment = [];
            $flightSummary = $this->http->FindSingleNode(".//td[@class='FlightInfo']/div/div[@class='PopOutContent']", $segment_nodes->item($i));
            ///flight number
            if (preg_match("/Flight number:\s([^\s\n]*)/ims", $flightSummary, $flightNumber)) {
                $tripSegment['FlightNumber'] = $flightNumber[1];
            }
            ///Airline Name
            if (preg_match("/Operating airline:\s([^\n]*)Aircraft:*/ims", $flightSummary, $airlineName)) {
                $tripSegment['AirlineName'] = $airlineName[1];
            }
            ///Aircraft
            if (preg_match("/Aircraft:\s([^\n]*)/ims", $flightSummary, $aircraft)) {
                $tripSegment['Aircraft'] = preg_replace("/\sDeparture terminal: [0-9]+/ims", "", $aircraft[1]);
            }
            ///Departure Terminal
            if (preg_match("/Departure terminal:\s([^\n]*)/ims", $flightSummary, $terminal)) {
                $tripSegment['DepartureTerminal'] = $terminal[1];
            }
            ///depart code and date
            $FlightDays = $this->http->FindNodes("//div[@class='Label Heading']");
            $departNode = $this->http->FindSingleNode(".//td[1]", $segment_nodes->item($i));

            if (!empty($departNode)) {
                if (preg_match('/\s(\w{3}$)/ims', $departNode, $departMatch)) {
                    $tripSegment['DepCode'] = trim($departMatch[0]);
                }
                $depDate = preg_replace('/(\*|\s\w{3}$)/ims', '', $departNode);

                if (strlen($depDate) < 6) {      // only time in field, need to concatenate with date
                    $depDate = $FlightDays[0] . " " . $depDate; //$depDate = $FlightDays[$i]." ".$depDate;
                }
                $departDate = strtotime($depDate);

                if (!empty($departDate)) {
                    $tripSegment['DepDate'] = $departDate;
                }
            }
            ///arrive code and date
            $arriveNode = $this->http->FindSingleNode(".//td[2]", $segment_nodes->item($i));

            if (!empty($arriveNode)) {
                if (preg_match('/\s(\w{3}$)/ims', $arriveNode, $arriveMatch)) {
                    $tripSegment['ArrCode'] = trim($arriveMatch[0]);
                }
                $arrDate = preg_replace('/(\*|\s\w{3}$)/ims', '', $arriveNode);

                if (strlen($arrDate) < 6 && isset($FlightDays[1])) {   // only time in field, need to concatenate with date
                    $arrDate = $FlightDays[1] . " " . $arrDate; //$arrDate = $FlightDays[$i]." ".$arrDate;
                }
                $arriveDate = strtotime($arrDate);

                if (!empty($arriveDate)) {
                    $tripSegment['ArrDate'] = $arriveDate;
                }
            }
            ///airport names
            $tripSegment['DepName'] = $tripSegment['DepCode'];
            $tripSegment['ArrName'] = $tripSegment['ArrCode'];
            ///Cabin
            $tripSegment['Cabin'] = trim($this->http->FindSingleNode(".//td[6]", $segment_nodes->item($i)));
            ///Duration
            if (strlen(trim($this->http->FindSingleNode(".//td[3]", $segment_nodes->item($i)))) < 10) {
                $tripSegment['Duration'] = trim($this->http->FindSingleNode(".//td[3]", $segment_nodes->item($i)));
            }
            ///Meal
            $tripSegment['Meal'] = $meal;
            ///save this segment
            if (!empty($tripSegment)) {
                $result['TripSegments'][] = $tripSegment;
            }
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flybmi.com/check-in/en-gb/find-your-booking.aspx";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
//        $this->http->PostURL(
//            'https://www.flybmi.com/bmi/en-gb/manage-my-booking/manage-my-booking.aspx?em=PMfqjR6DzldSK%2fbG0K0xfn5lukPcjYSbhDkbnJzKNtzA%2b%2bmmEMhQ67vW7M4S3UuvKn8O8NtnE36twL6BOJZQYpwhoSz5FxArYY%2fVPVYH0Y5VKr1xRz5oKmDyVZBpHWzJ%2b67YtSV60SoNux%2fX%2faxyYMqaWmc2mI0sTAE8IsYudvDJOE8L8NcmKDjQX9vqcj%2fwPHgpnZs5XcuOOufF7LStk2K7nKeZW1B3T%2fQmSNk3z1pETtN8MNryUUH29HxdxNv4up8IK7zwricGoRjbW9oUHIOcs2GpJ0sPTKfFMJ5JmgHvVsqZUT4qJANOMNqAnTmAazAOWyduVsMv%2fLxQ3MF2gA%3d%3d&cde=jVhR%2ba%2be2epbK%2f3neUU%2b2w%3d%3d',
//            array(
//                'ctl00%24ctl00%24ChildMaster%24Content%24ctl01%24ctl00%24BookingReference' => $arFields['BookingReference'],
//                'ctl00%24ctl00%24ChildMaster%24Content%24ctl01%24ctl00%24Surname' => $arFields['Surname'],
//                'ctl00_ctl00_scmManager_HiddenField' => 'ctl00_ctl00_scmManager_HiddenField',
//                '__VIEWSTATE' => '/wEPDwUKMTgwMTU5NDQwNA9kFgJmD2QWAmYPZBYEAgMPZBYCAgEPFgIeA3NyYwU9aHR0cHM6Ly9zbWV0cmljcy5mbHlibWkuY29tL2Ivc3MvZmx5Ym1pY29tcHJvZC8xL0guMTkuNC0tTlMvMGQCBxBkZBYCAgMPZBYKAgIPFgIeBFRleHQFFkFzayB5b3VyIHF1ZXN0aW9uIGhlcmVkAggPZBYCAgEPZBYCZg8WAh8BBSFjdGwwMF9jdGwwMF9DaGlsZE1hc3Rlcl9jdGxMb2dpbl9kAgwPZBYCAgEPFgIeB1Zpc2libGVoZAITDxYCHwEFpQI8bGk+PGEgaHJlZj0iL2JtaS9lbi1nYi9hYm91dC11cy9ibWkvcGFydG5lci1haXJsaW5lcy5hc3B4IiB0aXRsZT0iTHVmdGhhbnNhIEdyb3VwIiByZWw9Im5vZm9sbG93Ij5MdWZ0aGFuc2EgR3JvdXA8L2E+Jm5ic3A7PGltZyBzcmM9Ii9pbWFnZXMubmV0L3YyL2dsb2JhbC90ZW1wbGF0ZS9mbHlibWkvdjYvYm1pX3BvcHVwRm9vdGVyLmdpZiIgYWx0PSJUaGlzIGxpbmsgb3BlbnMgaW4gYSBuZXcgd2luZG93IiB0aXRsZT0iVGhpcyBsaW5rIG9wZW5zIGluIGEgbmV3IHdpbmRvdyIvPiZuYnNwO3wmbmJzcDs8L2xpPmQCHg8WAh8BBRcNCjwhLS0gUElEOiAzNjM2NiAtLT4NCmQYAQUeX19Db250cm9sc1JlcXVpcmVQb3N0QmFja0tleV9fFgMFMGN0bDAwJGN0bDAwJENoaWxkTWFzdGVyJGN0bExvZ2luJGxnc1N0YXR1cyRjdGwwMQUwY3RsMDAkY3RsMDAkQ2hpbGRNYXN0ZXIkY3RsTG9naW4kbGdzU3RhdHVzJGN0bDAzBR5jdGwwMCRjdGwwMCRDaGlsZE1hc3RlciRidG5Bc2tQCftM8PuTCfzT5xRSMl8BqqT5uA==',
//                '__PREVIOUSPAGE' => 'QV_N7hzphYQFyaRdIZFk2gXH862COdSN8ftPTzFwLDoLT1uGYlf55Plp8NE2mBmeWY4rvS0km2TCIQ5JkWde6K1a07zP8GlHcVGaDKrokE5x47cWaDz1XXjo9JIAJAxzgQd_hA2',
//                '__EVENTVALIDATION' => '/wEWNgLj2s6MCALm1/DJAQKT1dzDBwL58ZroDgLvu9PDCgKN8Z7oDgLsupPoDAL88a7oDgKNurNJAv3xnugOAui7g68IAofxrugOAuy7y78BAo3xnugOAui7w9wMAovx5ugOAp64+6oOAv7xrugOAse7z5cPAv7xrugOAs+154kPAo7x7ugOAvO2m8ADAvnxmugOAv7xrugOAv3xnugOAvzxrugOAofxrugOAo7x7ugOAo3xnugOAovx5ugOAt+i4qwDAtiixqwDAtmilqwDAqGizqwDAq2ioqwDAtyilqwDArmrsX4ChfGSkQ0CwOef+AMCi7WPlAgC7cKQwQECo4XM5gMCsvCRmg4C/76WgQUCtLrO1g8C5ITXsgcCubWo0A4C7JSdngEC/7nj9gMC2bm2yAoC47SI2wYC1+vsoAMCx/O6rAbnpENWIxobp+uDJGj6ohzLrKHY9g==',
//                'ctl00%24ctl00%24ChildMaster%24ctl00%24drpCountry' => 'gb',
//                'ctl00%24ctl00%24ChildMaster%24ctl00%24drpLanguage' => 'en',
//                'ctl00%24ctl00%24ChildMaster%24ctlLogin%24HiddenLogInText' => 'Login',
//                'ctl00%24ctl00%24ChildMaster%24ctlLogin%24HiddenLogOutText' => 'Logout',
//                'ctl00%24ctl00%24ChildMaster%24txtAsk' => 'Ask+your+question+here',
//                'ctl00%24ctl00%24ChildMaster%24Content%24ctl01%24ctl00%24FindBooking' => 'Find+booking',
//                'ctl00%24ctl00%24ChildMaster%24Content%24ctl01%24ctl00%24EmailMembershipNumber' => ''
//            )
//        );

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

//        if ($this->http->ParseForm("stayLandingFormBean")){
//            $this->http->FormURL = 'http://www.ichotelsgroup.com/ihg/hotels/us/en/reservation/ManageYourStay';
//            $this->http->SetInputValue("confirmationNumber", $arFields["ConfNo"]);
//            $this->http->SetInputValue("lastName", $arFields["LastName"]);
//
//            $this->http->PostForm();
//            $this->http->GetURL("http://www.ichotelsgroup.com/ihg/hotels/us/en/reservation/singlereservationsummary");
//
//        }// if ($this->http->ParseForm("stayLandingFormBean"))
//        else { //if (!$this->http->ParseForm("stayLandingFormBean"))
//            $this->sendNotification("bmi - failed to retrieve itinerary by conf #", 'all', true,
//                "Conf #: {$arFields['ConfNo']} / LastName: {$arFields['LastName']}");
//            return "Failed to get reservation. Please check again later.";
//        }

        $error = $this->http->FindSingleNode('//div[@class="Error status"]');

        if (!empty($error)) {
            return $error;
        }
        $it = $this->CheckItinerary();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            'ConfNo' => [
                'Caption'  => 'Booking reference',
                'Type'     => 'string',
                'Size'     => 40,
                'Required' => true,
            ],
            'Surname' => [
                'Caption'  => 'Last name',
                'Type'     => 'string',
                'Size'     => 40,
                'Value'    => $this->GetUserField('LastName'),
                'Required' => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Amount"      => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $this->http->Log('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];
        $startTimer = microtime(true);

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;

        if ($this->http->currentUrl() != 'https://www.diamondclub.org/iloyal-MemberPortal/mportal/landingFlow?execution=e2s1') {
            $this->http->GetURL("https://www.diamondclub.org/iloyal-MemberPortal/mportal/landingFlow?execution=e2s1");
        }

        do {
            $page++;
            $this->http->Log("[Page: {$page}]");
//            if ($page > 1) {
//                $this->http->PostForm();
//            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        } while ($this->http->ParseForm("navigateForm") && sizeof($this->http->FindNodes("//input[@id='nextPage']")) && !$this->collectedHistory);

        $this->http->Log("[Time parsing: " . (microtime(true) - $startTimer) . "]");

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//div[@id = 'activityHistoryDetailsWidget']/table//tr[td[3]]");

        if ($nodes->length > 0) {
            $this->http->Log("Found {$nodes->length} items");

            for ($i = 0; $i < $nodes->length; $i++) {
                $postDate = strtotime($this->http->FindSingleNode("td[1]", $nodes->item($i)));

                if (isset($startDate) && $postDate < $startDate) {
                    break;
                }
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));
                $result[$startIndex]['Amount'] = $this->http->FindSingleNode("td[3]", $nodes->item($i));
                $startIndex++;
            }
        }

        return $result;
    }
}
