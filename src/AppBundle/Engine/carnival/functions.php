<?php

class TAccountCheckerCarnival extends TAccountChecker
{
    use PriceTools;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setMaxRedirects(0);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.carnival.com/ProfileManagement/api/v1.0/Accounts/Authenticate', json_encode([
            'username' => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
        ]), [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
        ]);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Just a moment
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Carnival.com apologizes for this temporary delay. We are working hard to fulfill your request.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for the error that occurred. Carnival.com technical support staff have been notified and are working to resolve the issue.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We apologize for the error that occurred. Carnival.com technical support staff have been notified and are working to resolve the issue.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'SORRY! TECHNICAL MAINTENANCE UPDATES ARE IN PROGRESS')]")) {
            throw new CheckException(ucfirst(strtolower($message)), ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'site is not currently available')]")
            ?? $this->http->FindSingleNode('//p[contains(text(), "We\'re working on some improvements, so you temporarily")]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Provider error
        if ($message = $this->http->FindSingleNode("//div[contains(text(),'We apologize for the error that occurred')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($message = $this->http->FindSingleNode('//p[contains(text(),"Looks like we’re experiencing a problem with carnival.com.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We at Carnival.com apologize for this temporary delay. We are working hard to fulfill your request.")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->setMaxRedirects(5);
        $this->http->GetURL('https://www.carnival.com/BookedGuest/guestmanagement/mycarnival/logon');
        // Right now we’re working on some improvements to cruise booking, so you temporarily won’t be able to make a new booking,
        // make changes to an existing one, or check-in for a cruise online. Check back a little bit later — we’re scheduled to be
        // done by today at 9 a.m. Eastern.
        if ($this->http->FindSingleNode("//p[contains(text(), 'Right now we’re working on some improvements to cruise booking, so you temporarily')]")) {
            throw new CheckException('Right now we’re working on some improvements to cruise booking, so you temporarily won’t be able to make a new booking.', ACCOUNT_PROVIDER_ERROR);
        }
        // At the moment, we are busy making super fun updates to our site. We’ll be back crazy soon!
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'At the moment, we are busy making super fun updates to our site. We’ll be back crazy soon!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Right now we’re working on some improvements to cruise booking! Check back a little bit later — we’re scheduled to be done by today at 9 a.m. Eastern.
        if ($message = $this->http->FindSingleNode("(//p[contains(text(), 'Right now we’re working on some improvements to cruise booking!')]/text())[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        //if (!$this->http->PostForm())
        //    return $this->checkErrors();
        //$response = $this->http->JsonLog();

        $this->manualRedirect();

        $cookie = array_merge($this->http->GetCookies("carnival.com"), $this->http->GetCookies("carnival.com", "/", true));
        $this->logger->debug(var_export($cookie, true), ['pre' => true]);

        if ($this->getCookie('cclUser') != null) {
            return true;
        }

        $response = $this->http->JsonLog();
        $message = $response->message ?? null;
        $code = $response->code ?? null;
        $error = $response->details[0]->message ?? null;

        if ($code == 'InvalidCredentials' && $message) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($code == 'BadArgument' && $message == 'Request validation failed.' && in_array($error,
                ['Password is invalid.', 'Username is invalid.'])) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        // Internal Server Error
        if ($code == 'InternalServerError' && $message) {
            throw new CheckRetryNeededException(2, 10, $message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function getCookie($name)
    {
        $cookie = array_merge($this->http->GetCookies("carnival.com"), $this->http->GetCookies("carnival.com", "/", true));

        if (isset($cookie[$name])) {
            return $cookie[$name];
        }

        return null;
    }

    public function Parse()
    {
        $cclUser = $this->http->JsonLog(urldecode($this->getCookie('cclUser')));
        //# Name
        if (isset($cclUser->FirstName) && isset($cclUser->LastName)) {
            $this->SetProperty('Name', beautifulName($cclUser->FirstName . ' ' . $cclUser->LastName));
        }
        //# VIFP Club #
        if (isset($cclUser->PastGuestNumber)) {
            $this->SetProperty('VIFPClubNumber', $cclUser->PastGuestNumber);
        }

        $this->http->GetURL('https://www.carnival.com/ProfileManagement/api/v1.0/Profiles');

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Looks like we’re experiencing a problem with carnival.com. Sorry! We’re already working on")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $response = $this->http->JsonLog();
        // Level
        $this->SetProperty('Level', $response->tier->tierTitle ?? null);
        // Cruising since ...
        $this->SetProperty('SailingSince', $response->yearFirstSailed ?? null);
        // Balance - VIFP Points
        $this->SetBalance(intval($response->tier->totalVifpPoints) ?? null);
        // Total cruises
        $this->SetProperty('TotalCruises', count($response->bookings));
        // Your VIFP points breakdown -> Cruise Day Points
        $this->SetProperty('CruiseDayPoints', intval($response->tier->totalCruiseDays) ?? null);
        // Your VIFP points breakdown -> VIFP Points
        $this->SetProperty('VIFPPoints', intval($response->tier->totalEarnedCruiseDays) ?? null);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        //$arg['CookieURL'] = 'https://www.carnival.com/BookedGuest/guestmanagement/mycarnival/logon';
        //$arg['PreloadAsImages'] = true;
        //$arg['SuccessURL'] = 'http://www.carnival.com';
        return $arg;
    }

    public function ParseItineraries()
    {
        $result = [];
        $this->http->setMaxRedirects(7);
        $this->http->GetURL('https://www.carnival.com/BookedGuest/');

        if ($this->http->FindSingleNode("//script[contains(text(), 'initialData')]", null, true, "/^\/\/<!\[CDATA\[\s*window\[\'initialData\'\]\s*=\s*(.+);\s*\/\/\]\]>$/")) {
            return $this->noItinerariesArr();
        }

        $sailings = $bookings = [];
        $data = $this->http->FindSingleNode("//script[contains(text(), 'initialData')]", null, true, "/window\[\'initialData\'\]\s*=\s*(.+);/");

        if (!empty($data)) {
            $initialData = $this->http->JsonLog($data, 0);
            $sailings = $initialData->sailings->sailings ?? [];
        } elseif ($this->http->currentUrl() == 'https://www.carnival.com/booked/home') {
            $sailDate = $this->http->FindSingleNode("//input[@name='sailDate']/@value");
            $shipCode = $this->http->FindSingleNode("//input[@name='shipCode']/@value");

            if (!empty($sailDate) && !empty($shipCode)) {
                $this->http->GetURL("https://www.carnival.com/booked/api/v1.0/app/home?shipCode={$shipCode}&sailDate={$sailDate}");
                $initialData = $this->http->JsonLog(null, 3);
                $sailings = $initialData->sailings->sailings ?? [];
                $bookings = $initialData->sailing->bookings ?? [];
            }
        }
        $this->logger->debug("Total " . count($sailings) . " cruises were found");

        foreach ($sailings as $sailing) {
            $this->ParseItinerary($sailing, $bookings);
        }

        if ($cclSession = $this->http->getCookieByName('cclSession')) {
            $data = urldecode($cclSession);
            // by msg for N days to cruising whish calc from cookies
            if (count($sailings) === 0
                && count($this->itinerariesMaster->getItineraries()) === 0
                && $this->http->FindPreg("/^\{\"BookingNumber\":null,\"SailDate\":null,\"VifpNumberOfDays\":/", false,
                    $data)
            ) {
                return $this->noItinerariesArr();
            }
        }

        // FYI: historical - past (not showing, 404), can get nums, date sailing. not more
        // from https://www.carnival.com/profilemanagement/api/v1.0/Profiles

        return $result;
    }

    public function ParseItinerary($sailing, $bookings)
    {
        $this->logger->info('Parse itinerary #' . $sailing->bookings[0]->bookingNumber, ['Header' => 3]);
//        $this->logger->debug(var_export($sailing, true), ["pre" => true]);

        $c = $this->itinerariesMaster->add()->cruise();

        $c->general()
            ->confirmation($sailing->bookings[0]->bookingNumber, "Booking #");

        $c->details()
            ->shipCode($sailing->itinerary->itinCode ?? null, true)
            ->description($sailing->itinerary->itineraryTitle ?? null, true)
            ->ship($sailing->itinerary->shipName ?? null, true)
            ->shipCode($sailing->itinerary->shipCode ?? null, true);

        $days = $sailing->itinerary->schedule ?? [];
        $this->logger->debug("Total " . count($days) . " segments were found");
        $this->logger->debug("Duration: " . $sailing->itinerary->durationDays);

        foreach ($days as $day) {
            $port = $day->portName;

            if (!isset($day->departureMmmDYyyy) && !isset($day->arrivalMmmDYyyy)) {
                continue;
            }

            if ($this->http->FindPreg("/^Fun Day At Sea$/", false, $port)
                || $this->http->FindPreg("/\s+Transit$/", false, $port)
                || $this->http->FindPreg("/^Cruise\s+$/", false, $port)
            ) {
                //Panama Canal Partial Transit | Cruise Tracy Arm Fjord
                continue;
            }
            $segment = $c->addSegment();
            $segment
                ->setName($port)
                ->setCode($day->portCode);

            if (isset($day->departureMmmDYyyy)) {
                $segment->parseAboard($day->departureMmmDYyyy . " " . $day->departureTime);
            }

            if (isset($day->arrivalMmmDYyyy)) {
                $segment->parseAshore($day->arrivalMmmDYyyy . " " . $day->arrivalTime);
            }
        }

        if (empty($bookings)) {
            // old version
            $this->http->GetURL("https://www.carnival.com/booked/details?shipCode={$sailing->itinerary->shipCode}&sailDate={$sailing->itinerary->sailDate}");
            $initialData = $this->http->JsonLog($this->http->FindSingleNode("//script[contains(text(), 'initialData')]",
                null, true, "/window\[\'initialData\'\]\s*=\s*(.+);/"), 0);
            $bookingInfo = $initialData->sailing->bookings[0] ?? [];
            $this->logger->debug(var_export($bookingInfo, true), ["pre" => true]);
        } else {
            $bookingInfo = $bookings[0];
        }

        if ($bookingInfo->bookingNumber != $sailing->bookings[0]->bookingNumber) {
            $this->logger->error("wrong itinerary details were loaded");

            $this->logger->debug('Parsed itinerary:');
            $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);

            return;
        }

        $c->general()->date2($bookingInfo->bookedDate);

        foreach ($bookingInfo->guests as $guest) {
            $c->general()->traveller($guest->firstName . " " . $guest->lastName, true);

            if (!empty($guest->loyaltyNumber)) {
                $c->program()->account($guest->loyaltyNumber, false);
            }
        }

        $c->details()
            ->room($bookingInfo->stateroomNumber)
            ->roomClass($bookingInfo->stateroomTypeName);

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);
    }

    private function manualRedirect()
    {
        $this->logger->notice(__METHOD__);

        if ($url = $this->http->FindSingleNode('//h2[contains(text(), "Object moved to")]/a/@href')) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
        }
    }
}
