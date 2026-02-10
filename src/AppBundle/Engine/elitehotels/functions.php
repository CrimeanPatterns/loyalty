<?php

class TAccountCheckerElitehotels extends TAccountChecker
{
    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.elite.se/en/rewards/");
//        $this->http->GetURL("https://www.elite.se/api/en/login/login");
        $this->http->GetURL("https://idservereu.maverickcrm.com/connect/authorize?client_id=Elite&redirect_uri=http%3A%2F%2Feu.maverickcrm.com%2Fguest%2FAccount%2FCallback%3FClientID%3D22&response_type=code&scope=openid%20profile%20restApi%20profileID&state=00c7ecc668d846e2b4824e89909e43a3&code_challenge=eYtqHC5ELAlb3-SdGPrB3Yki3M29iFFB7iJlvDEnVZM&code_challenge_method=S256");

//        if ($this->http->ParseForm(null, '//form[contains(@action, "/Account/Login")]')) {
//            $this->http->PostForm();
//        }

        if (!$this->http->ParseForm(null, '//form[contains(@action, "/Account/Login")]')) {
            return false;
        }

        $this->http->SetInputValue('memberId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "client_id"     => "Elite",
                "code"          => $code,
                "redirect_uri"  => "http://eu.maverickcrm.com/guest/Account/Callback?ClientID=22", // trim($response->url),
                "code_verifier" => "fbf33dfcdc8a4fdb985073f77b1fc4a499086a616c4c426ea432f8299462f3062773d157d9d74b5a9df98768e353ea5b",
                "grant_type"    => "authorization_code",
            ];
            $headers = [
                "Accept"       => "*/*",
                "Content-Type" => "application/x-www-form-urlencoded",
                "Origin"       => "https://eu.maverickcrm.com",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://idservereu.maverickcrm.com/connect/token", $data, $headers);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->id_token)) {
                return false;
            }

            $idTokenParts = explode('.', $response->id_token);

            foreach ($idTokenParts as $str) {
                $json = $this->http->JsonLog(base64_decode($str));

                if (isset($json->profileID)) {
                    $guestID = $json->profileID;

                    break;
                }
            }

            if (empty($guestID)) {
                return false;
            }

            $this->http->GetURL("https://eu.maverickcrm.com/guest/Account/MyProfile?guestID={$guestID}&clientID=22&brandingId=1");

            return true;
        }
        // Login failed
        $message = $this->http->FindSingleNode('//span[@id = "ErrorMessage"]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'PASSWORD IS INVALID')
                || strstr($message, 'EMAIL IS INVALID')
                || strstr($message, 'MEMBER ID IS INVALID')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your membership is inactive. ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return false;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h2[@id = "layout_userdata_Name"]')));
        // Membership number
        $this->SetProperty("MembershipNumber", $this->http->FindSingleNode("//tr[@id = 'overview_section_Profile_MemberID']/td[2]"));
        // Membership Level
        $this->SetProperty("MembershipLevel", $this->http->FindSingleNode("//tr[@id = 'overview_section_Profile_MemberLevelName']/td[2]"));
        // Joined
        $this->SetProperty("Joined", $this->http->FindSingleNode("//tr[@id = 'overview_section_Profile_SignUpTime']/td[2]"));
        // Balance - AVAILABLE POINTS
        $this->SetBalance($this->http->FindSingleNode("//tr[@id = 'overview_section_Profile_AvailablePoints']/td[2]"));
        // TIER NIGHTS
        $this->SetProperty("TierNights", $this->http->FindSingleNode("//tr[@id = 'overview_section_Profile_Nights']/td[2]"));

        // refs#23581
        $expPoints = $this->http->FindSingleNode("//td[contains(text(),'POINTS TO EXPIRE (next 30 days)')]/following-sibling::td",
            null, false, '/^(\d+)$/');
        $this->SetProperty("ExpiringPoints", $expPoints);
    }

    public function ParseItineraries()
    {
        $this->logger->info(__METHOD__);
        $data = [
            'resStatus' => 'N',
            'months'    => '3',
            'ran'       => (float) rand() / (float) getrandmax(),
        ];
        $this->http->PostURL('https://eu.maverickcrm.com/guest/Account/InitUpcomingHistoryReservations', $data);
        $data = $this->http->JsonLog();

        if ($this->http->FindPreg('/\{"total":0,"rows":\[\]\}/')) {
            return $this->noItinerariesArr();
        }

        foreach ($data->rows as $row) {
            $this->parseItineraryHotel($row);
        }

        if ($this->ParsePastIts) {
            $data = [
                'resStatus' => '0',
                'months'    => '24',
                'ran'       => (float) rand() / (float) getrandmax(),
            ];
            $this->http->PostURL('https://eu.maverickcrm.com/guest/Account/InitUpcomingHistoryReservations', $data);
            $data = $this->http->JsonLog();

            foreach ($data->rows as $row) {
                $this->parseItineraryHotel($row);
            }
        }

        return [];
    }

    private function parseItineraryHotel($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('Parse Itinerary #%s', $data->CrsResvID), ['Header' => 3]);
        $h = $this->itinerariesMaster->add()->hotel();
        $h->general()
            ->status(beautifulName($data->ResStatus))
            ->confirmation($data->CrsResvID, 'Confirmation number')
            ->date2($data->BookTimeFormate)
            ->traveller("$data->FirstName $data->LastName")
        ;

        $h->hotel()
            ->name($data->HotelName);

        $address = [];

        if (!empty($data->Address1)) {
            $address[] = $data->Address1;
        }

        if (!empty($data->Address2)) {
            $address[] = $data->Address2;
        }

        if (!empty($address)) {
            $h->hotel()->address(join(', ', $address));
        } elseif ($data->Address1 === null) {
            $h->hotel()->noAddress();
        }

        if (isset($data->DayInStr, $data->DayOutStr)) {
            $dayIn = strtotime(str_replace('-', '/', $data->DayInStr));
            $dayOut = strtotime(str_replace('-', '/', $data->DayOutStr));
        } else {
            $dayIn = $this->http->FindPreg('/Date\((\d+)\)/', false, $data->DayIn) / 1000;
            $dayOut = $this->http->FindPreg('/Date\((\d+)\)/', false, $data->DayOut) / 1000;
        }

        $h->booked()
            ->checkIn($dayIn)
            ->checkOut($dayOut)
            ->rooms($data->Rooms);

        $h->booked()->guests($data->Adults)
            ->kids($data->Children);

        $h->price()
            ->total(round($data->TotalChargeFormate, 2))
            ->currency($data->CurrencySymbol)
        ;

        $r = $h->addRoom();
        $r->setType($data->RoomType);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return [];
    }
}
