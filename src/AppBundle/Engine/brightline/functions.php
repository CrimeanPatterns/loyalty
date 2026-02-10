<?php

class TAccountCheckerBrightline extends TAccountChecker
{
    private $headers = [
        'Accept'           => 'application/json, text/plain, */*',
    ];
    private $currentItin = 0;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email Address is not valid.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://prod-gobrightline.us.auth0.com/authorize?client_id=ZAiyHqBblBM91tT7DAzC83DUZkqhLYYq&scope=openid+profile+email+offline_access&redirect_uri=https%3A%2F%2Fwww.gobrightline.com%2Faccount&ui_locales=en&data=%5Bobject+Object%5D&audience=Brightline+BFF&response_type=code&response_mode=query&state=N1VrMkRDb3p3Z29xNkpsTDBSa18xNWk1Q0FvMk5LQ3hCMkRyU2NvMWpBYg%3D%3D&nonce=dDhLNHAxZzNVTUxPVDFJcjBmQkU0TXhDM09zUkFXb1VYWDNGOUoyMlpINg%3D%3D&code_challenge=pnLOWCl4Usy0leUq3493bOFDxwBm3gr0K5S0sEJqGIY&code_challenge_method=S256&auth0Client=eyJuYW1lIjoiYXV0aDAtc3BhLWpzIiwidmVyc2lvbiI6IjIuMC40In0%3D");

        if (!$this->http->ParseForm(null, '//form[@data-form-primary="true"]')) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('captcha', $captcha);
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] != 400) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "client_id"     => "ZAiyHqBblBM91tT7DAzC83DUZkqhLYYq",
                "code_verifier" => "5MEgGEm8ORYM95mvTTeH_jllIQTGWDVfVFoMD4pDzZg",
                "grant_type"    => "authorization_code",
                "code"          => $code,
                "redirect_uri"  => "https://www.gobrightline.com/account",
            ];

            $headers = [
                'Accept'       => '*/*',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ];
            $this->http->PostURL("https://prod-gobrightline.us.auth0.com/oauth/token", $data, $headers);
        }

        $loginResponse = $this->http->JsonLog(null, 4);

        if (!empty($loginResponse->access_token)) {
            $this->State['token'] = $loginResponse->access_token;

            return $this->loginSuccessful();
        }

        $message =
            $loginResponse->message
            ?? $this->http->FindSingleNode('//span[contains(@class, "ulp-input-error-message") and normalize-space(.) != ""]')
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'unauthorized, please try again') {
                throw new CheckException("Invalid. Please try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Wrong email or password') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $preferences = $this->http->JsonLog(null, 4);
        $profile = $preferences->profile ?? null;
        // Name
        $firstName = $profile->firstName ?? '';
        $lastName = $profile->lastName ?? '';
        $this->SetProperty('Name', beautifulName($firstName . " " . $lastName) ?? null);
        // TOTAL RIDES
        $this->SetProperty('TotalRides', $preferences->numberOfRides);

        $this->http->GetURL("https://www.gobrightline.com/rest/api.wallet-passes.en-us.xjson", $this->headers);
        $response = $this->http->JsonLog();
        // Number
//        $this->SetProperty('Number', $preferences->personDetails->personId ?? null);
        // PARKING PASSES
        if (!empty($response->parkingPasses)) {
            $this->sendNotification("check properties");
        }
        $this->SetProperty('Passes', count($response->parkingPasses));
        // TRAIN PASSES
        if (!empty($response->travelPasses)) {
            $this->sendNotification("check properties");
        }
        $this->SetProperty('TrainPasses', count($response->travelPasses));
        // One-Way Rides
//        $this->SetProperty('OneWayRides', count($response->creditPasses));
        // Balance - Brightline Credits
        $balance = 0;

        foreach ($response->creditPasses as $creditPass) {
            $balance += $creditPass->creditAvailable;
        }
        $this->SetBalance($balance);
    }

    public function ParseItineraries()
    {
        // Upcoming Trips
        $this->http->GetUrl('https://www.gobrightline.com/rest/api.trips.en-us.xjson', $this->headers);
        $userBookings = $this->http->JsonLog(null, 4);
        $journeys = $userBookings->upcoming->trips ?? null;

        if ((!$this->ParsePastIts || $this->http->FindPreg("/\"past\":{\"trips\":\[\]\}\}/")) && $journeys === []) {
            $this->itinerariesMaster->setNoItineraries(true);

            return;
        }

        foreach ($journeys as $journey) {
            $this->http->GetURL("https://www.gobrightline.com/rest/api.trip-details.en-us.xjson?referenceNumber={$journey->referenceNumber}", $this->headers);
            $this->parseItinerary();
        }
    }

    public function parseItinerary()
    {
        $this->logger->debug(__METHOD__);
        $response = $this->http->JsonLog();
        $data = $response->tripDetails ?? [];
        $t = $this->itinerariesMaster->createTrain();
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $data->trip->referenceNumber), ['Header' => 3]);
        $t->general()->confirmation($data->trip->referenceNumber);
        $t->general()->status(beautifulName($data->status));

        // todo
        if ($data->status == 'CANCELLED') {
            $t->general()->cancelled();
        }

        $costSummary = $data->costSummary;
        $t->price()->total($costSummary->bookingTotal->total);

        foreach ($costSummary->items as $item) {
            if ($item->productName == 'Taxes and Fees') {
                $t->price()->tax($item->totalPrice);

                break;
            }
        }

        $travellers = [];

        foreach ($data->passengerSeatingDetails as $passengerSeatingDetail) {
//            $s->setCarNumber($passenger->coach_no);
//            $s->extra()->cabin(beautifulName($passenger->cabin));
//            $s->extra()->seat($passenger->seat_no);

            $travellers[] = beautifulName($passengerSeatingDetail->passenger->firstName . ' ' . $passengerSeatingDetail->passenger->lastName);
        }

        $t->general()->travellers(array_unique($travellers));

        $trips = array_filter([$data->trip->outboundRoute, $data->trip->inboundRoute ?? []]);

        foreach ($trips as $trip) {
            $s = $t->addSegment();
            $s->setNumber($trip->service->name);

            $s->departure()->date2($trip->departureDateTime);
            $s->departure()->name($trip->origin->name);
            $s->departure()->code($trip->origin->abbreviation);

            $s->arrival()->date2($trip->arrivalDateTime);
            $s->arrival()->name($trip->destination->name);
            $s->arrival()->code($trip->destination->abbreviation);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.gobrightline.com/rest/api.user-profile.en-us.xjson', $this->headers + ['Authorization' => "Bearer {$this->State['token']}"], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 4);
        $email = $response->profile->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            !empty($email)
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers += ['Authorization' => "Bearer {$this->State['token']}"];

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@data-captcha-provider="recaptcha_v2"]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
