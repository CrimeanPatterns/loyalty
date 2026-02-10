<?php

require_once __DIR__ . '/../royalcaribbean/functions.php';

class TAccountCheckerAzamara extends TAccountCheckerRoyalcaribbean
{
    /**
     * like as royalcaribbean, celebritycruises, azamara.
     */
    public $headers = [
        "AppKey"           => "rkUhqxhFOowIiMlFuDcjH5ewM4xnzHAP",
        "Accept"           => "application/json, text/plain, */*",
        "X-Requested-With" => "XMLHttpRequest",
        "Access-Token"     => '',
    ];
    public $brand = "C";
    public $domain = "azamara.com";
    public $appID = "Azamara.Web.GuestAccount";
    public $postURL = "https://www.azamara.com/auth/json/authenticate";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.azamara.com/account/upcoming-cruises");

        if ($this->http->FindSingleNode("(//div[normalize-space(text())='Azamara Circle #']/following-sibling::div/text())[1]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.azamara.com/account/upcoming-cruises");

        $clientId = $this->http->FindPreg('/clientId: "(\w+)",/');
        $codeChallenge = $this->http->FindPreg("/codeChallenge: '([\w\-]+)',/");
        $state = $this->http->FindPreg("/state: '([\w=\-]+)',/");
        $nonce = '5EDxA7qn68GRa9UEkRLBD5FcStZIAhbQ1B94rWXt9ieekQFnxh43P3OKOlZLpGUy';

        if (!isset($clientId, $codeChallenge, $state, $nonce)) {
            return $this->checkErrors();
        }

        $headers = [
            "Accept"                     => "application/json",
            "Content-Type"               => "application/x-www-form-urlencoded",
            "X-Okta-User-Agent-Extended" => "okta-auth-js/7.0.1 okta-signin-widget-7.2.1",
            "Origin"                     => "https://www.azamara.com",
            "Referer"                    => "https://www.azamara.com/",
        ];

        $this->http->RetryCount = 0;
        $data = [
            "client_id"             => $clientId,
            "scope"                 => "openid email profile",
            "redirect_uri"          => "https://www.azamara.com/authentication/oidc-callback",
            "code_challenge"        => $codeChallenge,
            "code_challenge_method" => "S256",
            "state"                 => $state,
            "nonce"                 => $nonce,
        ];
        $this->http->PostURL("https://id.azamara.com/oauth2/default/v1/interact", $data, $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->interaction_handle)) {
            return $this->checkErrors();
        }

        $data = [
            "interactionHandle" => $response->interaction_handle,
        ];
        $headers = [
            "Accept"                     => "application/ion+json; okta-version=1.0.0",
            "Content-Type"               => "application/json",
            "X-Okta-User-Agent-Extended" => "okta-auth-js/7.0.1 okta-signin-widget-7.2.1",
            "Origin"                     => "https://www.azamara.com",
            "Referer"                    => "https://www.azamara.com/",
        ];
        $this->http->PostURL("https://id.azamara.com/idp/idx/introspect", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->stateHandle)) {
            return $this->checkErrors();
        }

        $data = [
            "rememberMe"  => true,
            "identifier"  => $this->AccountFields['Login'],
            "credentials" => [
                "passcode" => $this->AccountFields['Pass'],
            ],
            "stateHandle" => $response->stateHandle,
        ];
        $this->http->PostURL("https://id.azamara.com/idp/idx/identify", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // new auth
        if (
            !isset($response->messages->value[0]->message)
            && isset($response->authentication->value->request->state)
        ) {
            $interaction_code = null;

            foreach ($response->successWithInteractionCode->value as $value) {
                if ($value->name != 'interaction_code') {
                    continue;
                }

                $interaction_code = $value->value;
            }

            if (!$interaction_code) {
                return false;
            }

            // Name
            $this->State['Name'] = beautifulName($response->user->value->profile->firstName . " " . $response->user->value->profile->lastName);
            $this->SetProperty('Name', $this->State['Name']);

            $param = [];
            $param['interaction_code'] = $interaction_code;
            $param['state'] = $response->authentication->value->request->state;

            $headers = [
                "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
                "Accept-Encoding" => "gzip",
                "Referer"         => "https://www.azamara.com/login?rUrl=https%3A%2F%2Fwww.azamara.com%2Fb2c%2Faccount%2Fupcoming-cruises",
            ];
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.azamara.com/authentication/oidc-callback?" . http_build_query($param), $headers); //todo: not working
            $this->http->RetryCount = 2;

            return true;
        }

        // old auth
        if (isset($response->tokenId)) {
            $this->http->GetURL("https://www.azamara.com/auth/oauth2/authorize?response_type=id_token%20token&scope=openid%20profile%20vdsid&nonce=7Ta9~AlU955bBG79U&client_id=g9S023t74473ZUk909FN68F0b4N67PSOh92o04vL0BR6537pI2y2h94M6BbU7D6J&redirect_uri=https%3A%2F%2Fwww.azamara.com%2Faccount%2Fauth%2Fprocess-token%2F&state=zcQyUC1tzJvQgsP1hH-%2F");
            $currentUrl = $this->http->currentUrl();
            $access_token = $this->http->FindPreg("/access_token=([^\&]+)/", false, $currentUrl);
            $idToken = $this->http->FindPreg("/id_token=([^\&]+)/", false, $currentUrl);

            $this->http->setCookie("accessToken", $access_token, ".azamara.com");
            $this->http->setCookie("idToken", $idToken, ".azamara.com");

            foreach (explode('.', $idToken) as $str) {
                $str = base64_decode($str);
                $this->logger->debug($str);

                if ($this->vdsid = $this->http->FindPreg('/"sub":"(.+?)"/', false, $str)) {
                    break;
                }
            }

            if (!isset($this->vdsid)) {
                return false;
            }
            $this->headers["Access-Token"] = $access_token;
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://aws-prd.api.rccl.com/en/azamara/web/v3/guestAccounts/{$this->vdsid}", $this->headers);
            $this->http->RetryCount = 2;

            if (!$this->http->FindPreg('/^G/', false, $this->vdsid)) {
                $this->http->JsonLog();
                /*
                 * We're improving your account
                 * To ensure your account works with recent updates, please complete all fields below.
                 */
                if ($this->http->FindPreg('/"internalMessage"\s*:\s*"(Both a access token and account ID are required in the request to continue\.")/')) {
                    $this->throwProfileUpdateMessageException();
                }
            }// if (!$this->http->FindPreg('/^G/', false, $this->vdsid))

            return true;
        }// if (isset($response->tokenId))

        // Invalid email and password combination.
        if ($this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":""}'
            || $this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"User has already been migrated"}') {
            throw new CheckException("Invalid email and password combination.", ACCOUNT_INVALID_PASSWORD);
        }
        // We're unable to complete your request, so please try again later.
        if ($this->http->Response['body'] == '{"code":500,"reason":"Internal Server Error","message":"Authentication Error!!"}') {
            throw new CheckException("We're unable to complete your request, so please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Pardon the interruption
         * Our enhanced security requires a one-time account validation.
         */
        if (strstr($this->http->Response['body'], '{"code":401,"reason":"Unauthorized","message":"User Needs to be Migrated",')) {
            $this->throwProfileUpdateMessageException();
        }
        // You're locked out
        if ($this->http->Response['body'] == '{"code":401,"reason":"Unauthorized","message":"Your account has been locked."}') {
            throw new CheckException("You're locked out", ACCOUNT_LOCKOUT);
        }
        // We've got your info. However, we're unable to bring up your account right now, so please try again later.
        if ($this->http->Response['code'] == 502) {
            throw new CheckException("We've got your info. However, we're unable to bring up your account right now, so please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Please try again. Make sure you enter the email and password associated with your account.
         * Your account will be frozen after 10 unsuccessful sign-in attempts.
         */
        if ($attempts = $this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"\s*(\d+)"/')) {
            throw new CheckException("Please try again. Make sure you enter the email and password associated with your account. Your account will be frozen after {$attempts} unsuccessful sign-in attempts.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/\{"code":401,"reason":"Unauthorized","message":"Login failure"(?:,"detail":\{"failureUrl":""\}|)\}/')) {
            throw new CheckException("Please try again. Make sure you enter the email and password associated with your account. Your account will be frozen after 10 unsuccessful sign-in attempts.", ACCOUNT_INVALID_PASSWORD);
        }

        $message = $response->messages->value[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'There is no account with the Username')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Authentication failed') {
                throw new CheckException("Log in failed!", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode("(//div[normalize-space(text())='Points']/following-sibling::div)[1]"));
        // Number - Azamara Circle #
        $this->SetProperty('ClubNumber', $this->http->FindSingleNode("(//div[normalize-space(text())='Azamara Circle #']/following-sibling::div/text())[1]"));
        // Status - Current Tier
        $this->SetProperty('Status', beautifulName($this->http->FindSingleNode("(//div[normalize-space(text())='Current Tier']/following-sibling::div)[1]")));

        if ($this->Properties['Status'] == 'Elite') {
            $this->sendNotification('Tier Elite // MI');
            //$this->SetProperty('Status', "Discoverer");
        }
        $name = $this->http->FindSingleNode("(//div[contains(@class,'user-greeting')])[1]", null, false, '/Hi\s+(.+)!/');

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // no any loyalty info (AccountID: 6503385)
            if (!empty($name) && $this->http->FindPreg("/<div class=\"account-page__top-bar\">\s*<div class=\"user-greeting me-auto\">\s*Hi \w+!\s*<\/div>\s*(?:<span id=\"loyalty-container\">\s*<\/span>\s*|)<\/div>/")) {
                $this->SetBalanceNA();
            }
        }

        if (isset($this->State['Name'])) {
            if (strstr($this->State['Name'], $name)) {
                $this->SetProperty('Name', $this->State['Name']);
            }
        } else {
            $this->SetProperty('Name', $name);
        }
    }

    public function ParseItineraries()
    {
        if ($this->http->FindSingleNode("//h2[contains(text(),'You have no upcoming cruises')]")) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $nodes = $this->http->XPath->query("//section[@class='account-upcoming-cruises']");

        $browser = clone $this->http;
        $this->http->brotherBrowser($browser);
        $browser->RetryCount = 0;
        // Statuses
        $browser->GetURL("https://seaware.azamara.com/touchb2c/rest/entity/list/resStatus?locale=en_US");
        $statuses = $browser->JsonLog(null, 2);
        // Ports
        $browser->GetURL("https://seaware.azamara.com/touchb2c/rest/entity/list/port?locale=en_US");
        $ports = $browser->JsonLog(null, 0);
        $browser->RetryCount = 2;

        foreach ($nodes as $root) {
            // PURSUIT | Apr 8 - Apr 15, 2023
            $date = strtotime($this->http->FindSingleNode(".//h4[contains(@class,'eyebrow large')]", $root, false, '/- (\w+ \d+, \d{4})/'));

            if (empty($date)) {
                $this->logger->error('Date not found');

                return [];
            }
            $url = $this->http->FindSingleNode(".//a[contains(@href,'touchb2c/?inJsonGet=') and not(contains(text(), 'Make a payment'))]/@href", $root);

            if (!$this->ParsePastIts && $date < time()) {
                $this->logger->debug('skip past reservation: ' . $url);

                return [];
            }

            $this->logger->debug($url);
            parse_str(parse_url($url, PHP_URL_QUERY), $array);
            $json = $this->http->JsonLog($array['inJsonGet']);
            $this->http->GetURL("https://seaware.azamara.com/touchb2c/rest/booking?lock=false&withAccessCheck=true&resGUID={$json->resGUID}&locale=en_US");
            $data = $this->http->JsonLog(null, 1);

            // itineraryInfo
            $params = http_build_query([
                'ship'   => $data->cabinsVal[0]->ctgInfo->ship,
                'from'   => $data->legsVal[0]->dep->departureTimeVal->utc,
                'to'     => $data->legsVal[0]->arr->arrivalTimeVal->utc,
                'locale' => 'en_US',
            ]);
            $this->http->GetURL("https://seaware.azamara.com/touchb2c/rest/entity/itineraryInfo?$params");
            $itineraries = $this->http->JsonLog(null, 1);

            $this->parseCruise($data, $statuses, $ports, $itineraries);
        }

        return [];
    }

    protected function parseCruise($data, $statuses, $ports, $itineraries)
    {
        $this->logger->notice(__METHOD__);
        $conf = round($data->resIdVal);
        $this->logger->info("Parse Cruise#{$conf}", ['Header' => 3]);

        $c = $this->itinerariesMaster->createCruise();
        $c->general()->confirmation($conf, 'Res#', true);
        //$c->general()->date2($data->resIdVal);

        foreach ($statuses as $val) {
            if ($val->statusVal === $data->resStatusVal) {
                break;
            }
        }
        $c->setStatus($val->name);

        foreach ($data->guestsVal as $traveller) {
            $c->general()->traveller(beautifulName("{$traveller->customer->lastName} {$traveller->customer->firstName}"));
        }
        $ships = [
            'QS' => 'Azamara Quest®',
            'JR' => 'Azamara Quest®',
            'PR' => 'Azamara Pursuit®',
            'ON' => 'Azamara Onward℠',
        ];

        foreach ($data->cabinsVal as $cabin) {
            $c->details()->ship($ships[$cabin->ctgInfo->ship]);
            $c->details()->room($cabin->ctgInfo->code);
            $c->details()->shipCode($cabin->cabinNumber ?? null, false, true);
        }
        $this->logger->info("found " . count($itineraries) . " cruise segments");

        foreach ($itineraries as $item) {
            if (in_array($item->activityType, ['CRUISE START', 'ARRIVAL'])) {
                $s = $c->addSegment();

                foreach ($ports as $val) {
                    if ($val->code === $item->port) {
                        break;
                    }
                }
                $s->setName($val->name);
                $s->parseAshore($item->dateTimeVal->local);
            } elseif (isset($s) && empty($s->getAboard()) && in_array($item->activityType,
                    ['BALANCING', 'DEPARTURE'])) {
                $s->parseAboard($item->dateTimeVal->local);
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);
    }
}
