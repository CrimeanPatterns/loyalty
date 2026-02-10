<?php

class TAccountCheckerAirindia extends TAccountChecker
{
    private $headers = [];
    private string $lastName;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State["authorization"])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("We can't seem to find your account", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://aiflyingreturns.b2clogin.com/aiflyingreturns.onmicrosoft.com/b2c_1a_signup_signin/oauth2/v2.0/authorize?client_id=ac5c8be3-c829-4db6-8eb7-aa4a37c61cbc&scope=ac5c8be3-c829-4db6-8eb7-aa4a37c61cbc%20openid%20profile%20offline_access&redirect_uri=https%3A%2F%2Fwww.airindia.com%2Fin%2Fen%2Fredirect.html&client-request-id=f3a25a38-ef11-42db-97cf-8885f6dbdf53&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=2.31.0&client_info=1&code_challenge=ZkFFGxJ-ygTp5L4BZ0wWMHycEIEszQMlL9Ob_2946rI&code_challenge_method=S256&nonce=fe7f892e-1b7f-427f-86ac-aef894a1298d&state=eyJpZCI6ImZkMmRiZWY3LTcwZmMtNDE1Zi04NzZmLTVjYTk2NjIyODI1MyIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D%7C%2F");

        if (!$this->http->FindPreg("/<form id=\"localAccountForm\"/")) {
            return $this->checkErrors();
        }

        $clientId = $this->http->FindPreg('/client_id=([^&]+)/', false, $this->http->currentUrl());
        $nonce = $this->http->FindPreg('/nonce=([^&]+)/', false, $this->http->currentUrl());

        if (!$clientId || !$nonce) {
            return $this->checkErrors();
        }

        $stateProperties = $this->http->FindPreg('/"StateProperties=(.+?)",/');
        $csrf = $this->http->FindPreg('/"csrf"\s*:\s*"(.+?)",/');
        $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
        $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
        $remoteResource = $this->http->FindPreg("/\"remoteResource\"\s*:\s*\"([^\"]+)/");
        $pageViewId = $this->http->FindPreg("/\"pageViewId\"\s*:\s*\"([^\"]+)/");
        $p = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");

        if (!$stateProperties || !$csrf || !$transId || !$remoteResource || !$pageViewId) {
            return false;
        }

        $data = [
            "request_type" => "RESPONSE",
            "signInName"   => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-CSRF-TOKEN"     => $csrf,
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->State['headers'] = $headers;
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aiflyingreturns.b2clogin.com{$tenant}/SelfAsserted?tx={$transId}&p={$p}", $data, $headers);
        $response = $this->http->JsonLog();

        $status = $response->status ?? null;

        if ($status !== "200") {
            $message = $response->message ?? null;

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $status == "400"
                    && in_array($message, [
                        "We can't seem to find your account",
                        "We can't seem to find your account.",
                        "Your password is incorrect. If this is your first login into the revamped portal, please proceed to forgot your password link to set-up password using your registered email-id.",
                        "Incorrect pattern for [Email Address]",
                        "We need to verify your account to continue. Please click on the link to verify email, phone number and reset your password ",
                        "The password you entered is incorrect. Remember, passwords are case-sensitive. Please try again or click \"Forgot your password.\" ",
                        "Unable to validate the information provided.",
                    ])
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->logger->notice("Logging in...");
        $param = [];
        $param['rememberMe'] = "true";
        $param['csrf_token'] = $csrf;
        $param['tx'] = $transId;
        $param['p'] = $p;
        $param['diags'] = '{"pageViewId":"7bde30fb-1642-4681-8060-6a968432c98f","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1710738523,"acD":0},{"ac":"T021 - URL:https://flyingreturns.blob.core.windows.net/root/loyalty-auth/unified_Prod.html","acST":1710738523,"acD":2162},{"ac":"T019","acST":1710738525,"acD":2},{"ac":"T004","acST":1710738525,"acD":1},{"ac":"T003","acST":1710738525,"acD":0},{"ac":"T035","acST":1710738526,"acD":0},{"ac":"T030Online","acST":1710738526,"acD":0},{"ac":"T002","acST":1710738546,"acD":0},{"ac":"T018T010","acST":1710738545,"acD":1396}]}';
        $this->http->GetURL("https://aiflyingreturns.b2clogin.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
        $this->http->RetryCount = 2;

        $this->State['tenant'] = $tenant;
        $this->State['p'] = $p;
        $this->State['transId'] = $transId;
        $this->State['csrf_token'] = $csrf;

        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code || $this->http->Response['code'] !== 200) {
            $this->logger->error("something went wrong, code not found");
            $response = $this->http->JsonLog();
            $detail = $response->errors[0]->detail ?? null;
            $this->DebugInfo = $detail;

            if ($detail == 'An internal error occurred, please contact your administrator') {
                throw new CheckRetryNeededException(2, rand(10, 15), $detail);
            }

            if ($detail == 'Please reinitiate your login in.') {
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(3, $this->attempt * 7);
            }

            return false;
        }

        $this->logger->notice("Get token...");

        $data = [
            "client_id"                  => $clientId,
            "redirect_uri"               => "https://www.airindia.com/in/en/redirect.html",
            "scope"                      => "ac5c8be3-c829-4db6-8eb7-aa4a37c61cbc openid profile offline_access",
            "code"                       => $code,
            "x-client-SKU"               => "msal.js.browser",
            "x-client-VER"               => "2.31.0",
            "x-ms-lib-capability"        => "retry-after, h429",
            "x-client-current-telemetry" => "5|865,0,,,|,",
            "x-client-last-telemetry"    => "5|0|961,a5688a00-da7e-4385-a987-8c218b967d8d|endpoints_resolution_error|1,0",
            "code_verifier"              => "8-eWwtfKP2P4xudvMtBZ1NIqJD_o6aJl2-KFnG-wRJk",
            "grant_type"                 => "authorization_code",
            "client_info"                => "1",
            "client-request-id"          => "f3a25a38-ef11-42db-97cf-8885f6dbdf53",
            "X-AnchorMailbox"            => "Oid:11e5feae-925d-4f41-a53a-00ffbb325f13-b2c_1a_signup_signin@0f9e128b-004b-493c-940c-c5db0e2ee2b6",
        ];
        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Accept-Language" => "en-US,en;q=0.5",
            "Accept-Encoding" => "gzip, deflate, br",
            "Referer"         => "https://aiflyingreturns.b2clogin.com/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://aiflyingreturns.b2clogin.com/aiflyingreturns.onmicrosoft.com/b2c_1a_signup_signin/oauth2/v2.0/token", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token, $response->token_type)) {
            $this->State['authorization'] = "{$response->token_type} {$response->access_token}";

            if ($this->loginSuccessful()) {
                return true;
            }

            if (
                $this->http->FindPreg("#,\"message\":\"Email And Phone Not Present\"#")
                || $this->http->FindPreg("#,\"message\":\"EmailId is invalid\"#")
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $data = $response->responsePayload->data[0] ?? null;
        // Name
        $names = $data->individual->identity->name;
        $this->lastName = ($names->universal->lastName ?? $names->romanized->lastName);
        $this->SetProperty("Name", beautifulName(($names->universal->firstName ?? $names->romanized->firstName) . " " . $this->lastName));
        // Membership #
        $this->SetProperty("Number", $data->membershipId);
        // Member since
        $this->SetProperty("MemberSince", date("d M Y", strtotime($data->enrolmentDate)));


        $this->logger->info('Balance & Expiration date', ['Header' => 3]);
        $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/membership/getBalance", '{"membershipId":"' . $data->membershipId . '"}', $this->headers);
        $response = $this->http->JsonLog(null, 7);

        if (count($response->responsePayload->data->awards->available) > 1) {
            return;
        }

        // Balance - FR Points
        $this->SetBalance($response->responsePayload->data->awards->available[0]->amount);
        // ... FR Points expiring on 30 Jun 2022
        $expiryBreakdownAwards = $response->responsePayload->data->awards->detailed ?? [];

        foreach ($expiryBreakdownAwards as $expiryBreakdownAward) {
            if (!isset($exp) || $exp > strtotime($expiryBreakdownAward->expiresAt)) {
                $exp = strtotime($expiryBreakdownAward->expiresAt);
                $this->SetExpirationDate($exp);
                $this->SetProperty("ExpiringBalance", $expiryBreakdownAward->amount);
            }
        }

        // Status
        $this->logger->info('Status', ['Header' => 3]);
        $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/tiers/getTiers", '{"membershipId":"' . $data->membershipId . '"}', $this->headers);
        $response = $this->http->JsonLog(null, 3, false, 'amount');

        foreach ($response->responsePayload->data as $tierInfo) {
            if ($tierInfo->tierType != 'MAIN' || isset($tierInfo->validityEndDate)) {
                continue;
            }

            // Tier
            $this->SetProperty("Tier", $tierInfo->label);

            $tierPoints = 0;
            $flightsCompleted = 0;

            foreach ($tierInfo->targets as $target) {
                foreach ($target->awards as $award) {
                    switch ($award->code) {
                        case "TP":
                            $tierPoints += $award->amount;

                            if ($award->targetPointsType == 'SELECTED_PARTNERS') {
                                // Air India Tier Points
                                $this->SetProperty("AirIndiaTierPoints", $award->amount);
                            }

                            break;

                        case "STP":
                            $flightsCompleted += $award->amount;

                            if ($award->targetPointsType == 'SELECTED_PARTNERS') {
                                // Air India flights
                                $this->SetProperty("AirIndiaFlights", $flightsCompleted);
                            }
                    }// switch ($award->code)
                }// foreach ($target->awards as $award)
            }// foreach ($tierInfo->targets as $target)
            // Tier Points
            $this->SetProperty("TierPoints", $tierPoints);
            // Total Flights
            $this->SetProperty("TotalFlights", $flightsCompleted);
        }// foreach ($response->responsePayload->data as $tierInfo)

        /*
        $this->logger->info('Vouchers', ['Header' => 3]);
        $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v3/vouchers/getVouchers", '{"membershipId":"' . $data->membershipId . '","page[offset]":0,"page[limit]":20,"sort":"-issueDate","status":"ALL"}', $this->headers);
        $response = $this->http->JsonLog(null, 7);

        if (!empty($response->responsePayload->data)) {
            $this->sendNotification("vouchers were found - refs #23853");
        }
        */
    }


    public function ParseItineraries()
    {

        $this->http->PostURL('https://api-des.airindia.com/v1/security/oauth2/token', [
            'client_id' => 'DCkj8EM4xxOUnINtcYcUhGXVfP2KKUzf',
            'client_secret' => 'QWgBtA2ARMfdAf1g',
            'grant_type' => 'client_credentials',
            'guest_office_id' => 'NYCAI08AA'
        ]);
        $token = $this->http->JsonLog();

        $loyaltyData = [
            "ffNumber" => $this->Properties['Number'],
            "lastName" => $this->lastName,
        ];
        $this->http->PostURL("https://api.airindia.com/aimobile/api/loyalty-trips", json_encode($loyaltyData),
            $this->headers);
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'SUCCESS' && $this->http->FindPreg('/"responsePayload":\{"data":\[\{\}\]\}\}/')) {
            $this->itinerariesMaster->setNoItineraries(true);
            return;
        }

        foreach ($response->responsePayload->data as $item) {
            $lastName = $item->travelers[0]->names[0]->lastName ?? null;
            $this->http->GetURL("https://api-des.airindia.com/v2/purchase/orders/{$item->id}?lastName=$lastName&showOrderEligibilities=true&checkServicesAndSeatsIssuanceCurrency=false",
                [
                    'Authorization' => 'Bearer ' . $token->access_token
                ]);
            $response = $this->http->JsonLog();
            if (!isset($response->data)) {
                return null;
            }
            $this->parseItinerary($response);
        }
     }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Book Reference",
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

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.airindia.com/in/en/manage/booking.html";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
//        $this->http->GetURL('https://travel.airindia.com/booking/manage-booking/retrieve');
//        if ($this->http->FindSingleNode("//title[contains(text(), 'RefX booking')]"))
//            return null;

        $data = [
            'client_id'     => 'DCkj8EM4xxOUnINtcYcUhGXVfP2KKUzf',
            'client_secret' => 'QWgBtA2ARMfdAf1g',
            'grant_type'    => 'client_credentials',
        ];
        $this->http->PostURL('https://api-des.airindia.com/v1/security/oauth2/token', $data);
        $token = $this->http->JsonLog();

        if (!isset($token->access_token)) {
            return null;
        }

        $headers = [
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
            'Authorization' => "$token->token_type $token->access_token",
        ];
        $this->http->GetURL("https://api-des.airindia.com/v2/purchase/orders/{$arFields['ConfNo']}?lastName={$arFields['LastName']}&showOrderEligibilities=true", $headers);
        $response = $this->http->JsonLog();

        if (isset($response->errors[0]->detail) && $response->errors[0]->detail == 'Order not found.') {
            return 'Your booking cannot be found. Please check the information you have submitted and try again.';
        }

        if (!isset($response->data)) {
            return null;
        }
        $this->parseItinerary($response);

        return null;
    }

    public function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $f->general()->confirmation($data->data->id);
        $this->logger->info("Parse Itinerary #{$data->data->id}", ['Header' => 3]);

        foreach ($data->data->travelers as $traveler) {
            foreach ($traveler->names as $name) {
                $f->general()->traveller("$name->firstName $name->lastName");
            }
        }

        foreach ($data->data->frequentFlyerCards ?? [] as $frequent) {
            $f->program()->account($frequent->cardNumber, false);
        }

        $travelDocuments = $data->data->travelDocuments ?? [];

        foreach ($travelDocuments as $travel) {
            $f->issued()->ticket($travel->id, false);
//            $total += $travel->price->total;
//            $currency = $travel->price->currencyCode;
        }
//        $f->price()->total($total);
//        $f->price()->currency($currency);

        foreach ($data->data->air->bounds as $bound) {
            foreach ($bound->flights as $bFlight) {
                $flight = $data->dictionaries->flight->{$bFlight->id};
                $s = $f->addSegment();
                $s->airline()->name($flight->marketingAirlineCode);
                $s->airline()->number($flight->marketingFlightNumber);
                $s->departure()->code($flight->departure->locationCode);
                $s->departure()->terminal($flight->departure->terminal ?? null, false, true);
                $s->departure()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false,
                    $flight->departure->dateTime));
                $s->arrival()->code($flight->arrival->locationCode);
                $s->arrival()->terminal($flight->arrival->terminal ?? null, false, true);
                $s->arrival()->date2($this->http->FindPreg('/^(\d{4}-.+?\d+:\d+)/', false, $flight->arrival->dateTime));

                $s->extra()->aircraft($data->dictionaries->aircraft->{$flight->aircraftCode});
                $s->extra()->bookingCode($flight->meals->bookingClass ?? null, false, true);
                if ($flight->meals->mealCodes ?? null)
                    $s->extra()->meals($flight->meals->mealCodes ?? null);

                $seats = $data->data->seats ?? [];
                foreach ($seats as $seat) {
                    if ($seat->flightId != $bFlight->id) {
                        continue;
                    }

                    foreach ($seat->seatSelections as $seatSelection) {
                        $s->extra()->seat($seatSelection->seatNumber);
                    }
                }// foreach ($seats as $seat)
            }// foreach ($bound->flights as $bFlight)
        }// foreach ($data->data->air->bounds as $bound)
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"                      => "application/json",
            "Accept-Encoding"             => "gzip, deflate, br, zstd",
            "Access-Control-Allow-Origin" => "*",
            "Referer"                     => "https://www.airindia.com/",
            "Authorization"               => $this->State["authorization"],
            "Content-Type"                => "application/json",
            "Ocp-Apim-Subscription-Key"   => "28af0a775f704c09a792c92d090535e8",
            "Origin"                      => "https://www.airindia.com/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api-loyalty.airindia.com/loyalty-prd/v2/membership/getMemberships", "{}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'address');

        $email = $response->responsePayload->data[0]->contact->emails[0]->address ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            isset($response->responsePayload->data[0]->membershipId)
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers = $headers;

            return true;
        }

        if ($this->http->FindPreg('/"code":37104,"title":"A duplicate membership is found."/')) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }
}
