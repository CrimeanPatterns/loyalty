<?php

class TAccountCheckerFastpark extends TAccountChecker
{
    public function GetRedirectParams($targetURL = null)
    {
        $args = parent::GetRedirectParams($targetURL);
        $args['CookieURL'] = 'https://www.thefastpark.com/';
        //		$args['SuccessURL'] = 'https://www.thefastpark.com/myrewards/history/';
        return $args;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.thefastpark.com/relaxforrewards/rfr-dashboard');
//        $scriptManager_TSM = $this->http->FindPreg("/TSM_CombinedScripts_=([^\"]+)/");
        $this->http->setCookie("LocalDateTime", date("Y-m-d H:i:s"), "www.thefastpark.com");

        if (!$this->http->ParseForm("Form") || $this->http->Response['code'] !== 200) {
            return $this->checkErrors();
        }
        //$this->http->SetInputValue('ScriptManager_TSM', urldecode($scriptManager_TSM));
        $this->http->SetInputValue('dnn$ctl00$ctl01$signIn_username', $this->AccountFields['Login']);
        $this->http->SetInputValue('dnn$ctl00$ctl01$signIn_password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('dnn$ctl00$ctl01$signIn_submit', "Sign In");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 500 && $this->http->FindSingleNode('//h2[normalize-space(text()) = "DNN Error"]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // HTTP Error 503. The service is unavailable.
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "HTTP Error 503. The service is unavailable.")]
                | //h1[contains(text(), "Server Error in \'/\' Application.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Site Unavailable
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Site Unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Check that login successful
        if ($this->http->FindSingleNode("//a[@id = 'a_btn_SignOut']")) {
            return true;
        }
        // Invalid Username / Email Address or Password. Please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Invalid Username / Email Address or Password. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // There was an error with your (username/ email/ password combination). We'll give you another shot.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "There was an error with your (username/ email/ password combination). We\'ll give you another shot.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // An error has occurred.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "An error has occurred.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there was an error with your username/email and password combination. We'll give you another shot.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, there was an error with your username/email and password combination. We\'ll give you another shot.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials
        if ($this->http->FindPreg("/function ReOpenSignModal\(\)\s*\{\s*var result = ErrorStructure\(4\);/")) {
            throw new CheckException("Sorry, there was an error with your username/email and password combination. We'll give you another shot.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Available Points
        $this->SetBalance($this->http->FindSingleNode("//h1[@id = 'dnn_ctl01_ctl00_MemberAvailablePoints']"));
        // Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//div[@id='dnn_ctl00_headermembername']//p[contains(text(), 'Member Since')]", null, false, '/Since\s*([^<]+)/ims'));

        // refs #5990
        $nodes = $this->http->XPath->query("//div[@id = 'dnn_ctl01_RFRActivityHistory']//tr[td]");
        $this->logger->debug("Total nodes found: " . $nodes->length);

        for ($i = 0; $i < $nodes->length; $i++) {
            $description = $this->http->FindSingleNode("td[3]", $nodes->item($i));
            $yearLastDate = $this->http->FindSingleNode("td[1]", $nodes->item($i), true, '/\d{2},\s*(\d{4})$/ims');
            $this->logger->debug("Year of last date: {$yearLastDate} [{$description}]");

            if ($this->http->FindPreg('/activity/ims', false, $description) && isset($yearLastDate)) {
                //# Account Expiration
                $this->SetProperty('AccountExpiration', '12/12/' . ($yearLastDate + 1));

                break;
            }// if (preg_match('/acility/ims', $description))
        }// for ($i = 0; $i < $nodes->length; $i++)

        /*
        // Expiration date  // refs #5990
        $this->logger->info('Expiration date', ['Header' => 3]);
        $date = $this->http->FindSingleNode('//div[contains(@id, "ActivityHistory")]/table//tr[td and position() = 1]/td[position() = 1 and not(p)]');
        // Last Activity
        $this->SetProperty("LastActivity", $date);
        if ($exp = strtotime($date)) {
            $this->SetExpirationDate(strtotime("+18 months", $exp));
        }
        */

        // Free Parking Certificates

        $this->logger->info('Free Parking Certificates', ['Header' => 3]);
        $nodes = $this->http->XPath->query("//div[@id = 'dnn_ctl01_MyFreeNodays']/div");
        $this->logger->debug("Total {$nodes->length} Free Parking Certificates were found");

        for ($i = 0; $i < $nodes->length; $i++) {
            $displayName = $this->http->FindSingleNode(".//h6", $nodes->item($i));
            $certificateNumber = $this->http->FindSingleNode(".//h6/following-sibling::p[1]", $nodes->item($i));

            if ($displayName && $certificateNumber) {
                $subAccounts[] = [
                    'Code'        => 'fastparkCertificate' . $certificateNumber,
                    'Number'      => $certificateNumber,
                    'DisplayName' => "Certificate #{$certificateNumber} ({$displayName})",
                    'Balance'     => null,
                ];
            }
        }// for ($i = 0; $i < $nodes->length; $i++)

        if (isset($subAccounts)) {
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts
            $this->SetProperty("SubAccounts", $subAccounts);
        }

        $this->http->GetURL("https://www.thefastpark.com/relaxforrewards/my-mobile-card");
        // Name
        $this->SetProperty('UserName', $this->http->FindSingleNode("//div[contains(@id, 'MemberCardsList')]/div[contains(@class,'primary') and not(contains(@class,'nonprimarycard'))]//p[contains(text(), 'Card Number')]/preceding-sibling::h6"));
        // Card Number
        $this->SetProperty('Number', $this->http->FindSingleNode("//div[contains(@id, 'MemberCardsList')]/div[contains(@class,'primary') and not(contains(@class,'nonprimarycard'))]//p[contains(text(), 'Card Number')]/span"));
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.thefastpark.com/relaxforrewards/reservations');

        $receipts = [];
        $nodes = $this->http->XPath->query("//div[@class='reservation']");

        foreach ($nodes as $node) {
            $endDate = strtotime($this->http->FindSingleNode(".//p[@class='bold']", $node, false, '/\w+ \d+, \d{4}/'));
            $reservationId = $this->http->FindSingleNode(".//a[contains(text(),'View Reservation')]/@onclick", $node, false, '/ViewReservation\((\d+),\d+\)/');

            if (!$reservationId || !$endDate) {
                $this->logger->error('empty receipt or date');

                return [];
            }

            if ($endDate < strtotime('now') && !$this->ParsePastIts) {
                $this->logger->notice('Past parking, skip it');

                continue;
            }
            $receipts[] = $reservationId;
        }

        if (!empty($receipts)) {
            foreach ($receipts as $receipt) {
                $headers = [
                    'Accept' => 'application/json, text/javascript, */*; q=0.01',
                ];
                $this->http->GetURL("https://www.thefastpark.com/desktopmodules/restservices/api/apidetails/viewmyreservation?Id={$receipt}&Flag=0", $headers);
                $response = $this->http->JsonLog();

                if (isset($response->message)) {
                    $this->http->SetBody($response->message);
                }

                $this->parseItinerary();
            }
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "Email"  => [
                "Type"     => "string",
                "Caption"  => "E-mail",
                "Size"     => 40,
                "Value"    => $this->GetUserField('Email'),
                "Required" => true,
            ],
            'ConfNo'    => [
                'Caption'  => 'Reservation #',
                'Type'     => 'string',
                'Required' => true,
                'Size'     => 40,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.thefastpark.com/reservation-find";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm('Form')) {
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $this->http->SetInputValue('dnn$ReservationFinder$yourEmail', $arFields['Email']);
        $this->http->SetInputValue('dnn$ReservationFinder$reservationNumber', $arFields['ConfNo']);
        $this->http->SetInputValue('dnn$ReservationFinder$btnFindReservation', 'Find My Reservation');
        $this->http->PostForm();

        // Your Reservation Could Not Be Found
        if ($error = $this->http->FindSingleNode("//p[contains(text(),'having trouble locating your reservation. Mind checking the information you entered')]")) {
            return $error;
        }

        $this->parseItinerary();

        return null;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $p = $this->itinerariesMaster->createParking();
        $confNo = $this->http->FindSingleNode("//p[contains(text(),'Reservation #')]", null, false, '/#(\d+)/');
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);
        $p->general()->confirmation($confNo);

        //$p->place()->location($this->http->FindSingleNode("//p[@id='pAbrivation']"));
        $p->booked()->start2($this->http->FindSingleNode("//p[@id='pSDate' or @id='p_SD']") . ', ' . $this->http->FindSingleNode("//p[@id='pSTime' or @id='p_ST']"));
        $p->booked()->end2($this->http->FindSingleNode("//p[@id='pEDate']|//p[contains(text(),'Check-Out')]/../following-sibling::p[1]") . ', ' . $this->http->FindSingleNode("//p[@id='pETime' or @id='p_ET']"));

        $address = array_values(array_filter($this->http->FindNodes("//div[@class='reservationInfo address']//text()")));
        // Austin AUS, Fast Park & Relax  |  512-385-8877
        $p->place()->location($this->http->FindPreg('/^(.+?)\s+\|/', false, $address[0]));
        $p->place()->phone($this->http->FindPreg('/^.+?\s+\|\s+(.+)/', false, $address[0]));
        $p->place()->address($address[1]);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($p->toArray(), true), ['pre' => true]);
    }
}
