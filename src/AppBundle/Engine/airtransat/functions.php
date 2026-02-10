<?php

class TAccountCheckerAirtransat extends TAccountChecker
{
    public function LoadLoginForm()
    {
        //$this->ShowLogs = true;
        // reset cookie
        $this->http->removeCookies();
        $this->http->GetURL('https://reservation.airtransat.com/MyTransat/default.aspx');

        return $this->formFields();
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Login()
    {
        $this->http->FilterHTML = false;
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg('/\|(pageRedirect)\|/ims')) {
            $this->http->GetURL('https://reservation.airtransat.com/MyTransat/Default.aspx');
        }

        if ($this->http->FindSingleNode('//a[@id="ctl00_ctl00_cphHeader_usrHeader_lnkSignOut" and @class="hylSignInOutDisplayB"]')
            || $this->http->FindSingleNode('//a[@id="ctl00_ctl00_cphContent_lnkNotThisPerson"]')) {
            return true;
        }

        $this->CheckError($this->http->FindSingleNode('//div[@class="msgInfoTextDetails"]/span[@id="ctl00_ctl00_cphContent_ucMsgError_lblMsgInfoTxt"]'), ACCOUNT_INVALID_PASSWORD);

        if ($this->formFields()) {
            $this->logger->notice("second attempt");

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            if ($this->http->FindPreg('/\|(pageRedirect)\|/ims')) {
                $this->http->GetURL('https://reservation.airtransat.com/MyTransat/Default.aspx');
            }

            if ($this->http->FindSingleNode('//a[@id="ctl00_ctl00_cphHeader_usrHeader_lnkSignOut" and @class="hylSignInOutDisplayB"]')
                || $this->http->FindSingleNode('//a[@id="ctl00_ctl00_cphContent_lnkNotThisPerson"]')) {
                return true;
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // set Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//a[@id="ctl00_ctl00_cphContent_subMenu_hylMyTransat"]')));

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->logger->debug('[ParseItineraries. date: ' . date('Y/m/d H:i:s') . ']');
        $this->http->FilterHTML = true;
        $this->http->GetURL("https://reservation.airtransat.ca/MyTransat/MyItineraries.aspx");
        // no Itineraries
        if ($this->http->FindPreg("/Current bookings \(0\)/ims")) {
            return $this->noItinerariesArr();
        }

        $links = $this->http->FindNodes("//div[contains(@id, 'rpvBookedItineraries')]//a[contains(@id, 'btnViewReservationDetails')]/@href", null, "/javascript:__doPostBack\(\'([^\']+)/i");
        $this->logger->debug("Total " . count($links) . " links were found");

        if (!$this->http->ParseForm("aspnetForm")) {
            return false;
        }

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $result = [];

        foreach ($links as $link) {
            $this->http->Form = $form;
            $this->http->FormURL = $formURL;
            $this->http->SetInputValue("__EVENTTARGET", $link);
            $this->http->PostForm();
            // Flights
            $legs = $this->http->XPath->query("//div[contains(@id, 'divItemContainer')]/div/div[@class = 'container']");
            $this->http->Log("Total {$legs->length} legs were found");

            for ($i = 0; $i < $legs->length; $i++) {
                $result[] = $this->ParseItinerary($legs->item($i));
            }
        }
        $this->logger->debug('[ParseItineraries. date: ' . date('Y/m/d H:i:s') . ']');

        return $result;
    }

    public function ParseItinerary($parentNode)
    {
        $this->logger->notice(__METHOD__);
        $result = ['Kind' => "T"];
        // RecordLocator
        $result['RecordLocator'] = $this->http->FindSingleNode(".//span[contains(@id, 'lblTicketRefNum')]", $parentNode);
        $this->logger->info('Parse itinerary #' . $result['RecordLocator'], ['Header' => 3]);
        // TripNumber
        $result['TripNumber'] = $this->http->FindSingleNode("//span[contains(@id, 'lblReservationID2')]");
        // Passengers
        $result['Passengers'] = array_map('beautifulName', $this->http->FindNodes(".//span[contains(@id, 'lblListOfAdultPassengers')]", $parentNode));
        // Seats
        $result['Seats'] = implode(', ', array_unique($this->http->FindNodes(".//span[contains(@id, 'lblReturnSeat') or contains(@id, 'lblDepartureSeat')]", $parentNode)));
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode(".//span[contains(@id, 'lblSubtotalAmount')]", $parentNode, true, "/([\d\.\,\s]+)/");
        // Currency
        $result['Currency'] = strstr($this->http->FindSingleNode(".//span[contains(@id, 'lblSubtotalAmount')]", $parentNode), '$') ? 'USD' : null;

        // Segments

        $segments = $this->http->XPath->query("div[contains(@id, 'divFlightInfoDetails')]", $parentNode);
        $this->logger->debug("Total {$segments->length} segment nodes were found");
        $countSegments = $this->http->XPath->query("div[contains(@id, 'divFlightInfoDetails')]/div[@id = 'flightLegDetails']", $parentNode);
        $this->logger->debug("Total {$countSegments->length} segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            for ($k = 0; $k < $countSegments->length; $k++) {
                $segment = [];
                // FlightNumber
                $segment['FlightNumber'] = $this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblFlightId')]", $segments->item($i), true, "/\s([\d]+)\s*/ims", $k);
                // Cabin
                $segment['Cabin'] = $this->http->FindSingleNode("div[@id = 'flightSegmentDetails']//span[contains(@id, 'LblClassName')]", $segments->item($i), true, null, $k);
                // AirlineName
                $segment['AirlineName'] = CleanXMLValue($this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblCarrierName')]", $segments->item($i), true, '/([^\(]+)/ims', $k));
                // Duration
                $segment['Duration'] = CleanXMLValue($this->http->FindSingleNode("div[@id = 'flightSegmentDetails']//span[contains(@id, 'lblDuration')]", $segments->item($i), true, '/time\s*([^<]+)/ims', $k));
                // DepCode
                $segment['DepCode'] = $this->http->FindSingleNode("div[@id = 'flightLegDetails']//a[contains(@id, 'hlDepAirCode')]", $segments->item($i), true, "/\((\w{3})\)/ims", $k);
                // DepName
                $segment['DepName'] = $this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblTakeOnTown')]", $segments->item($i), true, "/\sfrom\s+([^<]+)/ims", $k);
                // DepDate
                $depDate = $this->http->FindSingleNode("div[@id = 'flightSegmentDetails']//span[contains(@id, 'lbltakeOnDate')]", $segments->item($i), true, "/[a-zA-Z]{3}\s*\,\s*([^<]+)/ims", $k);
                $depDate .= ' ' . $this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblTakeOnHour')]", $segments->item($i), true, null, $k);
                $this->logger->debug("DepDate: $depDate / " . strtotime($depDate));

                if (strtotime($depDate)) {
                    $depDate = strtotime($depDate);
                    $segment['DepDate'] = $depDate;
                }// if (strtotime($depDate))
                // ArrCode
                $segment['ArrCode'] = $this->http->FindSingleNode("div[@id = 'flightLegDetails']//a[contains(@id, 'hlRetAirCode')]", $segments->item($i), true, "/\((\w{3})\)/ims", $k);
                // ArrName
                $segment['ArrName'] = $this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblTakeOffTown')]", $segments->item($i), true, "/\sin\s+([^<]+)/ims", $k);
                // ArrDate
                $arrDate = $this->http->FindSingleNode("div[@id = 'flightSegmentDetails']//span[contains(@id, 'lblNextDayArrivalDate')]", $segments->item($i), true, "/[a-zA-Z]{3}\s*\,\s*([^<]+)/ims", $k);
                $arrDate .= ' ' . $this->http->FindSingleNode("div[@id = 'flightLegDetails']//span[contains(@id, 'lblTakeOffHour')]", $segments->item($i), true, null, $k);
                $this->logger->debug("ArrDate: $arrDate / " . strtotime($arrDate));

                if (strtotime($arrDate)) {
                    $arrDate = strtotime($arrDate);
                    $segment['ArrDate'] = $arrDate;
                }// if (strtotime($arrDate))

                $result['TripSegments'][] = $segment;
            }// for ($k = 0; $k < $countSegments->length; $k++)
        }// for ($i = 0; $i < $countSegments->length; $i++)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function formFields()
    {
        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('ctl00$ctl00$cphContent$cphMyTransatContent$UserAccountTabsControl$LoginUserControl$tbxAccountNoEmail', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$ctl00$cphContent$cphMyTransatContent$UserAccountTabsControl$LoginUserControl$tbxNoAccountPwd', $this->AccountFields['Pass']);

        //		$this->http->Form['ctl00$ctl00$cphContent$cphMyTransatContent$MySearchHistory$cpeSearchHistory_ClientState'] = 'false';
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$ctl00$cphContent$cphMyTransatContent$UserAccountTabsControl$LoginUserControl$btnLogin');
        $this->http->SetInputValue('__ASYNCPOST', 'true');

        return true;
    }
}
