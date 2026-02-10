<?php

// refs #2851
class TAccountCheckerFriendchips extends TAccountChecker
{
    use PriceTools;

    private $response = null;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.tuifly.com/GlobalLogin.aspx");
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('SkySales')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ControlGroupGlobalLoginView$GlobalLoginViewExMemberLogin$TextBoxUserID', $this->AccountFields["Login"]);
        $this->http->SetInputValue('ControlGroupGlobalLoginView$GlobalLoginViewExMemberLogin$PasswordFieldPassword', $this->AccountFields["Pass"]);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Unfortunately our website is temporarily unavailable due to maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Unfortunately our website is temporarily unavailable due to maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Aufgrund von Wartungsarbeiten steht unsere Website vorübergehend leider nicht zur Verfügung')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // time out
        if ($this->http->currentUrl() == 'http://www.tuifly.com:9001/ErrorMessage.aspx'
            || $this->http->currentUrl() == 'http://www.tuifly.com:9001/?culture=de-DE%5CErrorMessage.aspx') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // An error has occurred. Please try again. If the error persists, try again later.
        if (stripos($this->http->currentUrl(), '/errormessage.html?aspxerrorpath=/GlobalLogin.aspx') !== false
            || $this->http->FindSingleNode("//p[contains(text(),'An error has occurred. Please try again')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Invalid password
        if (strstr($this->http->currentUrl(), 'passwordIncorrect=1')) {
            throw new CheckException("Das angegebene Passwort war nicht korrekt.<br/>Falls Sie Ihr Passwort vergessen haben, klicken Sie bitte in der Loginbox auf &quot;Passwort vergessen&quot;.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://www.tuifly.com/NewskiesEndpointMemberInformation.aspx");
        $this->response = $this->http->JsonLog();

        if (isset($this->response->Success) && $this->response->Success == true) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (isset($this->response->Data->Name->FirstName)) {
            $data = $this->response->Data;

            if (isset($data->Name->FirstName, $data->Name->MiddleName, $data->Name->LastName)) {
                $this->SetProperty("Name", beautifulName(CleanXMLValue(
                    $data->Name->FirstName
                    . ' ' . $data->Name->MiddleName
                    . ' ' . $data->Name->LastName)));
            } else {
                $this->logger->debug("Name is not found");
            }

            if (isset($data->CustomerNumber)) {
                $this->SetProperty("AccountNumber", $data->CustomerNumber);
            } else {
                $this->logger->debug("AccountNumber is not found");
            }

            if (!empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber'])) {
                $this->SetBalanceNA();
            }
        }// if (isset($this->response->Data->Name->FirstName))
    }

    public function ParseItineraries()
    {
        $result = [];
        // refs #2851
        $this->http->GetURL("https://www.tuifly.com/BookingList.aspx?culture=en-GB");
        // no Itineraries
        if ($this->http->FindSingleNode("//div[@class = 'noBookings' and contains(text(), 'You have not yet booked a flight')]")) {
            return $this->noItinerariesArr();
        }

        $flights = $this->http->FindNodes('//td[@class = "edit"]/a[contains(@href, "ControlGroupBookingListView$BookingListBookingListView")]/@href', null, "/BookingListView','([^\']+)\'\)/");
        $totalFlights = count($flights);
        $this->http->Log("Total {$totalFlights} flights were found");

        if (!$this->http->ParseForm("SkySales")) {
            return $result;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        foreach ($flights as $flight) {
            $this->sendNotification("friendchips - check parse Itinerary");
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;

            $this->http->SetInputValue("__EVENTARGUMENT", $flight);
            $this->http->SetInputValue("__EVENTTARGET", 'ControlGroupBookingListView$BookingListBookingListView');
            $this->http->PostForm();

            $result[] = $this->ParseItinerary();
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    public function ParseItinerary()
    {
        $result = ["Kind" => "T"];

        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode("//td[contains(text(), 'Booking number')]/following-sibling::td[1]");
        // ReservationDate
        $result['ReservationDate'] = strtotime($this->http->FindSingleNode("//td[contains(text(), 'Booking date')]/following-sibling::td[1]", null, true, "/\,([^<]+)/"));
        // Passengers
        $result['Passengers'] = $this->http->FindNodes("//div[@class = 'passengerName']");
        // Currency
        $totalCharge = $this->http->FindSingleNode("//td[@class = 'cell1' and contains(text(), 'Total')]/following-sibling::td[1]");
        $result['Currency'] = $this->currency($totalCharge);
        // TotalCharge
        $result['TotalCharge'] = $this->cost($totalCharge);
        // Tax
        $result['Tax'] = $this->http->FindSingleNode("//td[contains(text(), 'Taxes and fees')]/following-sibling::td[1]");

        // Air trip segments
        $segments = $this->http->XPath->query("//h2[contains(text(), 'flight')]/following-sibling::table[@class = 'flights']//tr[td[strong]]");
        $this->http->Log("Total {$segments->length} segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            $seg = $segments->item($i);
            $segment = [];

            $cityInfo = explode(" - ", $this->http->FindSingleNode('td/strong', $seg));
            $this->http->Log("<pre>" . var_export($cityInfo, true) . "</pre>", false);

            if (count($cityInfo) != 2) {
                $this->http->Log("cityInfo nodes != 2");

                continue;
            }
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode("following-sibling::tr[2]/td[1]", $seg);
            // Aircraft
            $segment['Aircraft'] = $this->http->FindSingleNode("following-sibling::tr[2]/td[2]", $seg);
            // DepDate, ArrDate
            $depDate = $this->http->FindSingleNode('following-sibling::tr[1]/td[1]', $seg);
            $time = explode(" - ", $this->http->FindSingleNode('following-sibling::tr[1]/td[2]', $seg));
            $this->http->Log("<pre>" . var_export($time, true) . "</pre>", false);

            if (count($time) != 2) {
                $this->http->Log("time nodes != 2");

                continue;
            }

            if (strtotime($depDate . " " . $time[0]) !== false) {
                $segment['DepDate'] = strtotime($depDate . " " . $time[0]);
            }

            if (strtotime($depDate . " " . $time[1]) !== false) {
                $segment['ArrDate'] = strtotime($depDate . " " . $time[1]);
            }
            // DepName
            $segment['DepName'] = $this->http->FindPreg("/[^,\(]+/", false, $cityInfo[0]);
            // DepCode
            $segment['DepCode'] = $this->http->FindPreg("/\(([A-Z]{3})/", false, $cityInfo[0]);
            // ArrName
            $segment['ArrName'] = $this->http->FindPreg("/[^,\(]+/", false, $cityInfo[1]);
            // ArrCode
            $segment['ArrCode'] = $this->http->FindPreg("/\(([A-Z]{3})/", false, $cityInfo[1]);

            $result['TripSegments'][] = $segment;
        }// for ($i = 0; $i < $segments->length; $i++) {

        return $result;
    }
}
