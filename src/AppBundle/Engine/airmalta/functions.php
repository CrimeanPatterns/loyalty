<?php

class TAccountCheckerAirmalta extends TAccountChecker
{
    private const REWARDS_PAGE_URL = "https://member.airmalta.com/dashboard";

    private $sessionToken = null;
    private $accountId = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
//        $this->http->RetryCount = 2;
//
//        if ($this->loginSuccessful()) {
//            return true;
//        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://member.airmalta.com/login");

        if ($this->http->Response['code'] != 200) {
//        if (!$this->http->ParseForm("profileLoginForm")) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"              => "application/json",
            "Content-Type"        => "application/json",
            "Alloy-Session-Token" => "48d7e569-4938-4a15-a0b2-da8410fafa9e",
            "Origin"              => "https://member.airmalta.com",
        ];

        $this->http->PostURL("https://member.airmalta.com/api/acquireSession/DEFAULT", "{}", $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->sessionToken)) {
            $this->logger->error("sessionToken not found");

            return false;
        }

        $this->sessionToken = $response->sessionToken;

        if (
            strpos($this->AccountFields['Login'], 'KM') !== 0
            && $this->http->FindPreg('/^\d+$/ims', false, $this->AccountFields['Login'])
        ) {
            $this->AccountFields['Login'] = 'KM' . $this->AccountFields['Login'];
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "startURL"      => "/services/oauth2/authorize?response_type=token&client_id=3MVG9HxRZv05HarQRwGJ9PN1FR_lqO5dn0CDOoH8RtJs1_lZ5qlGQC3qRIReYyRgSOm9P2vdmDw==&redirect_uri=https%3A%2F%2Fmember.airmalta.com%2F_callback.html&state=https%3A%2F%2Fmember.airmalta.com%2Flogin",
            "mode"          => "modal",
            "maskRedirects" => "false",
        ];
        $this->http->PostURL("https://loyalty.airmalta.com/servlet/servlet.loginwidgetcontroller?type=login", $data);

//        $this->http->SetInputValue('sfid-username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('sfid-password', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('rememberme', 'on');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are upgrading the website to provide you with a better experience to discover Air Malta.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are upgrading the website to provide you with a better experience to discover Air Malta.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $result = $response->result ?? null;

        if (filter_var($result, FILTER_VALIDATE_URL)) {
            $this->http->GetURL($result);

            if ($location = $this->http->FindPreg("/window.location.replace\(\"([^\"]+)/")) {
                $this->http->NormalizeURL($location);
                $this->http->GetURL($location);

                if ($locationAccess = $this->http->FindPreg("/window.location.replace\(\'([^\']+)/")) {
                    $this->http->NormalizeURL($locationAccess);
                    $this->http->GetURL($locationAccess);
                }

                $url = $this->http->FindPreg("/var url = \"([^\"]+)/");

                if ($this->http->ParseForm(null, "//form[contains(@name, 'thePage')]") && $url) {
                    $this->http->SetInputValue('AJAXREQUEST', "_viewRoot");
                    $this->http->SetInputValue('url', $url);
                    $this->http->SetInputValue('com.salesforce.visualforce.ViewStateCSRF', $this->http->FindSingleNode('//input[@name = "com.salesforce.visualforce.ViewStateCSRF"]/@value'));
                    $this->http->SetInputValue('com.salesforce.visualforce.ViewState', $this->http->FindSingleNode('//input[@name = "com.salesforce.visualforce.ViewState"]/@value'));
                    $this->http->SetInputValue('com.salesforce.visualforce.ViewStateMAC', $this->http->FindSingleNode('//input[@name = "com.salesforce.visualforce.ViewStateMAC"]/@value'));
                    $this->http->SetInputValue('com.salesforce.visualforce.ViewStateVersion', $this->http->FindSingleNode('//input[@name = "com.salesforce.visualforce.ViewStateVersion"]/@value'));
                    $this->http->SetInputValue($this->http->FindSingleNode('//input[contains(@name , "thePage:")]/@name'), $this->http->FindSingleNode('//input[contains(@name , "thePage:")]/@value'));
                    $this->http->SetInputValue($this->http->FindPreg("/parameters\':\{\'([^\']+)'/"), $this->http->FindPreg("/parameters':\{'[^']+':'([^']+)',/"));

                    $this->http->PostForm();
                }

                if ($url || $locationAccess) {
                    $this->changeLocation();

                    $this->changeLocation();

                    $access_token = $this->http->FindPreg("/access_token=([^&]+)/", false, $this->http->currentUrl());
                    $id = urldecode($this->http->FindPreg("/&id=([^&]+)/", false, $this->http->currentUrl()));
                    $id = $this->http->FindPreg("/\.com(.+)/", false, $id);

                    if (!$access_token || !$id) {
                        $this->logger->error("something went wrong");

                        if ($this->http->FindSingleNode("//h2[contains(text(), 'Change Your Password')]")) {
                            $this->throwProfileUpdateMessageException();
                        }

                        return false;
                    }

                    $access_token = urldecode($access_token);

                    $this->http->GetURL("https://loyalty.airmalta.com{$id}?version=latest&format=json&callback=&access_token={$access_token}");

                    $this->http->setDefaultHeader("X-Loyalty-Access-Token", "Bearer {$access_token}");

                    return true;
                }
            }
        }

        if ($this->http->getCookieByName("ICON-CAUTHF", "www.airmalta.com", "/", true)) {
            $this->getAccountPage();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($result == 'invalid') {
            throw new CheckException("We can't log you in. Make sure your username and password are correct.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//input[@name = 'ctl00\$ctl00\$hidInvalidLogin']/@name")) {
            throw new CheckException('Invalid login details...', ACCOUNT_INVALID_PASSWORD);
        }
        // An Application Error has Occurred
        if ($message = $this->http->FindSingleNode("//span[@id = 'lblErrorMsg']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h2[contains(text(), 'Change Your Password')]")) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Name
        $this->SetProperty("Name", beautifulName($response->display_name ?? null));

        if (!isset($response->custom_attributes->AccountId)) {
            $this->logger->error("AccountId no found");

            return;
        }

        $this->accountId = $response->custom_attributes->AccountId;

        $this->http->GetURL("https://member.airmalta.com/login");

        $headers = [
            "Accept"              => "application/json",
            "Accept-Encoding"     => "gzip, deflate, br",
            "Content-Type"        => "application/json",
            "Alloy-Session-Token" => $this->sessionToken,
        ];

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://member.airmalta.com/api/accounts/{$this->accountId}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Member since
        $this->SetProperty("MemberSince", date("jS M Y", strtotime($response->accountRegistrationDate)));
        // FlyPass
        $this->SetProperty("AccountNumber", $response->loyaltyNumber ?? null);

        if (
            !isset($response->loyaltyNumber) && $this->AccountFields['Login'] == 'yuhao13456@outlook.com' // AccountID: 5322397
            || $response->loyaltyNumber === null
            || (
                $response->loyaltyNumber !== null
                && (
                    !isset($response->loyaltyPointsBalance) // AccountID: 7080028
                    || $response->loyaltyPointsBalance === null // AccountID: 2040371
                )
            )
        ) {
            $this->SetBalanceNA();

            return;
        }
        // Balance - Miles
        $this->SetBalance($response->loyaltyPointsBalance);
        // Tier
        $this->SetProperty("TierLevel", $response->loyaltyTier);
    }

    public function ParseItineraries()
    {
        $headers = [
            "Accept"              => "application/json",
            "Accept-Encoding"     => "gzip, deflate, br",
            "Content-Type"        => "application/json",
            "Alloy-Session-Token" => $this->sessionToken,
        ];
        $this->http->GetURL("https://member.airmalta.com/api/accounts/{$this->accountId}/bookings", $headers);
        $response = $this->http->JsonLog();

        if ($this->http->Response['body'] == '{"bookings":[]}') {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        foreach ($response->bookings as $booking) {
            if (!$this->ParsePastIts && $booking->pastBooking == true) {
                $this->logger->debug("skip past itinerary #{$booking->recordLocator}");

                continue;
            }

            $this->parseItinerary($booking);
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 30,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Caption"  => "Last name",
                "Size"     => 30,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://airmalta.com/en";
//        return "https://book.airmalta.com/my-bookings/login";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->RetryCount = 0;

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'booking-search')]")) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $this->http->GetURL("https://book.airmalta.com/deeplink/mmb?UserLanguage=en&Pnr={$arFields['ConfNo']}&Name=" . $arFields['LastName']);

        $headers = [
            "Accept"       => "application/json",
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL("https://book.airmalta.com/api/acquireSession/DEFAULT", "", $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->sessionToken)) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $this->http->setDefaultHeader("Alloy-Session-Token", $response->sessionToken);

        $data = [
            "recordLocator"   => $arFields['ConfNo'],
            "emailOrLastName" => strtoupper($arFields['LastName']),
        ];
        $this->http->PostURL("https://book.airmalta.com/api/booking/retrieve", json_encode($data), $headers);
        $response = $this->http->JsonLog();
        $success = $response->success ?? null;

        if ($success !== true) {
            // PNR ... not found.
            if (isset($response->exception->stackTrace[0]->exception->message)) {
                return $response->exception->stackTrace[0]->exception->message;
            }

            $message = $response->message ?? null;

            if ($message == "retrieve booking failed") {
                return "Unfortunately this reservation cannot be retrieved online. Please contact the Air Malta contact centre.";
            }

            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $this->http->GetURL("https://book.airmalta.com/api/booking");
        $response = $this->http->JsonLog();
        $it = $this->parseItinerary($response->currentBooking);

        return null;
    }

    private function parseItinerary($booking)
    {
        $this->logger->notice(__METHOD__);

        if (isset($booking->itineraryParts) && is_array($booking->itineraryParts) && empty($booking->itineraryParts)) {
            $this->logger->error('Skip empty segments');

            return [];
        }
        $f = $this->itinerariesMaster->add()->flight();
        // RecordLocator
        $confNo = $booking->recordLocator;
        $this->logger->info("Parse Itinerary #{$confNo}", ['Header' => 3]);

        $f->general()->confirmation($confNo, "Booking reference", true);

        // -------------------------------- retrieve ------------------------------------------
        if (isset($booking->status)) {
            $f->general()->status($booking->status);
        }

        // Passengers
        $passengers = $booking->passengers ?? [];

        foreach ($passengers as $pass) {
            $firstName = $pass->firstName;
            $lastName = $pass->lastName;
            $name = trim(beautifulName("{$firstName} {$lastName}"));

            if ($name) {
                $f->addTraveller($name);
            }
        }
        // -------------------------------- retrieve ------------------------------------------

        // TripSegments
        $legs = $booking->itineraryParts;

        foreach ($legs as $leg) {
            $segments = $leg->segments;

            foreach ($segments as $segment) {
                $s = $f->addSegment();

                $s->airline()
                    ->name($segment->airline)
                    ->number($segment->flightNumber)
                    ->operator($segment->operatedBy ?? null, false, true)
                ;

                // DepCode
                $s->departure()
                    ->code($segment->origin)
                    ->date2($segment->departureTime)
                ;

                // ArrCode
                $s->arrival()
                    ->code($segment->destination)
                    ->date2($segment->arrivalTime)
                ;

                // Duration
                $duration = $segment->durationInMinutes;
                $mins = intval(($duration % 60));
                $hours = intval(($duration - $mins) / 60);
                $s->extra()->duration("{$hours}h {$mins}m");

                $s->extra()
                    ->cabin($leg->cabinType->name ?? null, false, true) // retrieve
                    ->bookingCode($leg->cabinType->code ?? null, false, true) // retrieve
                ;
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@id, 'btnLogout')]/@href")) {
            return true;
        }

        return false;
    }

    // provider bug fix, force redirect to account
    private function getAccountPage()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->currentUrl() == self::REWARDS_PAGE_URL) {
            return;
        }

        $i = 0;

        do {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $i++;
        } while (
            $this->http->currentUrl() == 'https://airmalta.com'
            || ($this->http->currentUrl() == self::REWARDS_PAGE_URL && $this->http->Response['code'] == 404)
            && $i < 3
        );
    }

    private function changeLocation()
    {
        $this->logger->notice(__METHOD__);

        if ($location = $this->http->FindPreg("/window.location.replace\(\'([^\']+)/")) {
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }
    }
}
