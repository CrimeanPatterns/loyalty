<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEurobonus extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://api2.flysas.com/customer/euroBonus/getAccountInfo?customerSessionId=";

    private $selenium = false;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $customerSessionId = null;
    private $sessionId = null;

    private $endHistory = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("Content-Type", "application/json;charset=UTF-8");
        $this->http->setDefaultHeader("Accept", "*/*");

        //$this->http->SetProxy($this->proxyDOP(['fra1']));

        if (
            isset($this->State['botDetectionWorkaround'])
            && $this->State['botDetectionWorkaround'] === true
            && $this->attempt > 1
        ) {
            $this->setProxyGoProxies(null, 'de', null, null, "https://www.flysas.com/us-en/");
        }
        unset($this->State['botDetectionWorkaround']);

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt == 2) {
            if ($agent = $this->http->setRandomUserAgent(10)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $agent = $this->State[$userAgentKey];
        }
        $this->http->setUserAgent($agent);
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['customerSessionId']) || !isset($this->State['sessionId']) || !isset($this->State['authorization'])) {
            return false;
        }
        $this->http->setDefaultHeader("Authorization", $this->State['authorization']);
        $this->customerSessionId = $this->State['customerSessionId'];
        $this->sessionId = $this->State['sessionId'];
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL . $this->customerSessionId, [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);

        if (
            ArrayVal($response, 'euroBonus', null)
            && !strstr($this->http->Response['body'], 'Unfortunately, your session has expired.')
        ) {
            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->unsetDefaultHeader("Authorization");
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.flysas.com/us-en/");
        $this->http->RetryCount = 2;

        // debug
        if (
            $this->http->Response["code"] == 403
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            || $this->http->FindSingleNode('//h2[contains(text(), "403 Forbidden")]')
        ) {
            sleep(3);
            $this->http->removeCookies();
            $this->http->RetryCount = 1;
            $this->http->GetURL("https://www.flysas.com/us-en/");

            if (
                $this->http->Response["code"] == 403
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            ) {
                $this->http->removeCookies();
                $this->http->SetProxy($this->proxyAustralia());
                $this->http->GetURL("https://www.flysas.com/us-en/");

                if (
                    $this->http->Response["code"] == 403
                    || strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
                ) {
                    throw new CheckRetryNeededException(3);
                }
            }
            $this->http->RetryCount = 2;
        }// if ($this->http->Response["code"] == 403 || strstr($this->http->Error, 'Network error 28 - Operation timed out after '))

        if ($this->attempt == 0 || $this->attempt == 1) {
            return $this->seleniumAuth();
        }

        $this->http->GetURL("https://auth.flysas.com/authorize?client_id=fco7ghbiy7YlhhFifg8p0yccBoIctJbJ&audience=flysas-api&response_type=code&redirect_uri=https%3A%2F%2Fwww.flysas.com%2Fcustomer-profile%2Fwww-api%2Flogin-success%2F&ui_locales=en&state=0.jf2sq7br&prompt=login&ext-forgot-password-link=%2Fen%2Fforgot-password&ext-labels-url=%2Fv2%2Fcms-www%2Ffragment%2Flabels%2Fcustomer-profile-common%2F&ext-market=lu-en&ext-register-link=%2Fen%2Fregister%2F&scope=openid%20profile%20email&auth0Client=eyJuYW1lIjoiYXV0aDAuanMiLCJ2ZXJzaW9uIjoiOS4xOC4xIn0%3D");

        if ($this->http->Response["code"] != 200) {
            return $this->checkErrors();
        }

        $state = $this->http->FindPreg('/state=(.+?)&/', false, $this->http->currentUrl());

        if (
            !isset($state)
            || $this->http->Response['code'] != 200
            || stristr($this->http->currentUrl(), 'maintenance')
        ) {
            return $this->checkErrors();
        }

        $data = [
            "state"      => $state,
            "username"   => $this->AccountFields['Login'],
            "uiPassword" => $this->AccountFields['Pass'],
            "action"     => "default",
            "password"   => "{\"labelsUrl\":\"/v2/cms-www/fragment/labels/customer-profile-common/?market=lu-en\",\"password\":\"{$this->AccountFields['Pass']}\"}",
        ];
        $headers = [
            'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $this->http->RetryCount = 1;
        $this->http->PostURL("https://auth.flysas.com/u/login?state={$state}&ui_locales=en", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->delay();
        $this->http->RetryCount = 0;

        if ($redirect = $this->http->FindSingleNode('//p[contains(text(), "Found. Redirecting to")]/a/@href')) {
            $this->http->GetURL($redirect);
        }

        if (
            $this->selenium === false
            && !in_array($this->http->Response['code'], [401, 400])
        ) {
            $response = $this->http->JsonLog();

            if (
                (
                    $this->http->FindPreg('/Operation timed out after/', false, $this->http->Error)
                    || $response === 400
                )
                && ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION
            ) {
                throw new CheckRetryNeededException(3);
            }

            if (
                strstr($this->http->currentUrl(), 'http://http://validate.perfdrive.com/captcha')
                && ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION
            ) {
                $this->State['botDetectionWorkaround'] = true;
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            // Sorry, but we can't process your request right now. Please try again later.
            if ($this->http->FindSingleNode('//title[
                    contains(text(), "503 - Service Not Available")
                    or contains(text(), "ERROR: The requested URL could not be retrieved")
                ]')
                || strstr($this->http->Response['body'], '"status":500,"error":"Internal Server Error","path":"/authorize/oauth/token"}')
                && ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_PRODUCTION
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
            }

            return $this->checkErrors();
        }
        $this->botDetectionWorkaround();
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $message =
            $response->error_description
            ?? $this->http->FindSingleNode('
                //div[@id = "customer-error"]
                | //div[@id = "prompt-alert"]
                | //div[contains(@class, "-notification-error")]//div[@element = "content"]
                | //span[contains(@class, "ulp-input-error-message") and normalize-space(.) != ""]
                | //h1[contains(text(), "NEW MEMBERSHIP CONDITIONS")]
            ')
        ;

        if (isset($response->access_token, $response->sessionId, $response->customerSessionId) && !$message) {
            $this->captchaReporting($this->recognizer);
            $this->http->setDefaultHeader("Authorization", $response->access_token);
            $this->http->setDefaultHeader("Accept-Language", 'en_us');

            $this->customerSessionId = $response->customerSessionId;
            $this->sessionId = $response->sessionId;
            $this->State['sessionId'] = $this->sessionId;
            $this->State['customerSessionId'] = $this->customerSessionId;
            $this->State['authorization'] = $response->access_token;
            $this->State['oauthToken'] = $this->http->getCookieByName('oauthtoken', 'www.flysas.com');

            //		    $this->http->PostURL("https://api.flysas.com//customer/getProfile", json_encode(["id" => $response->sessionId]));
//        $response = $this->http->JsonLog();
            sleep(3);
            $this->http->RetryCount = 0;
            $this->http->GetURL(self::REWARDS_PAGE_URL . $this->customerSessionId);

            if (
                $this->http->Response['code'] === 403
                && $this->http->FindSingleNode("//h2[contains(text(), 'The request is blocked.')]")
            ) {
                throw new CheckRetryNeededException(3, 0);
//                sleep(5);
//                $this->http->GetURL(self::REWARDS_PAGE_URL . $this->customerSessionId);
            }

            $this->http->RetryCount = 2;

            // Sorry, something went wrong.
            if ($this->http->FindPreg('/"errorMessage":"Sorry, something went wrong.\s*","type":"Error"/')) {
                throw new CheckException('Unfortunately, we are having some issues in retrieving your EuroBonus details. You can access all other information in your profile.', ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, but we can't process your request right now. Please try again later.
            if ($this->http->FindSingleNode('//title[contains(text(), "503 - Service Not Available")]')) {
                throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
            }

            if ($this->http->FindPreg("/\"errorCode\":\"1005003\",\"errorMessage\":\"(Sorry, but we can't process your request right now. Please try again later\.)\"/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }// if (isset($response->access_token, $response->sessionId, $response->customerSessionId))

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Solve the challenge question to verify you are not a robot.') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            // Sorry, we could not find you using the Login ID / Password combination. Please try again.
            if (
                strstr($message, "Sorry, we could not find you using the Login ID / Password combination. Please try again")
                || strstr($message, "Sorry, we couldn't find you with this login ID and password combination. Please try again.")
                || strstr($message, "Are you sure you are trying to access the correct customer profile? Please log out and try again")
                || strstr($message, "There is more than one account tagged to this email ID. Please login using your TravelPass or EuroBonus Number")
                || strstr($message, "Your password has expired.")
                || strstr($message, "We couldn't find you using this login ID and password combination. Please try again.")
                || $message == "There's more than one account connected to this email. Please log in using your SAS Travel Pass or EuroBonus number."
                || $message == "Din adgangskode er udløbet. Nulstil din adgangskode ved hjælp af linket til glemt adgangskode."
                || $message == "Vi kunne ikke finde dig med dette login-id og adgangskode. Prøv venligst igen."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, "There is more than one accont tagged to this email ID. Please login using your TravelPass or EuroBonus Number")
            ) {
                throw new CheckException("There is more than one account tagged to this email ID. Please login using your TravelPass or EuroBonus Number", ACCOUNT_INVALID_PASSWORD);
            }

            // Sorry, we are not able to log you in now. Please try after some time.
            if (strstr($message, "Sorry, we are not able to log you in now. Please try after some time.")
                // Your request cannot be processed at this point in time. Please try again later.
                || strstr($message, "Your request cannot be processed at this point in time. Please try again later.")
                // Sorry, something went wrong. Please try again.
                || strstr($message, "Sorry, something went wrong. Please try again.")
                || strstr($message, "Something is wrong. Please try again later.")
                // Sorry, but we can't process your request right now. Please try again later.
                || strstr($message, "Sorry, but we can't process your request right now. Please try again later.")
                // Something went wrong. Please try again later.
                || $message == 'Something went wrong. Please try again later.'
                || $message == 'Unfortunately, something went wrong. Please try again.'
                || $message == 'Something went wrong please try a win.'
                || $message == 'Noget gik desværre galt. Prøv venligst igen.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, but your account has been locked. To unlock your account, please use the Forgot Password link.
            // Unfortunately, your account has been locked. To unlock it, please use the Forgot password link.
            if (
                strstr($message, "Sorry, but your account has been locked.")
                || strstr($message, "Unfortunately, your account has been locked.")
                || strstr($message, "Your account has been blocked")
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // An Unknown error has occurred.
            if (strstr($message, 'An Unknown error has occurred.')) {
                throw new CheckException("We are currently having issues with our websites, which are causing instabilities. We expect the websites to be back to normal soon, and apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "NEW MEMBERSHIP CONDITIONS")]')) {
                $this->throwAcceptTermsMessageException();
            }

            $this->DebugInfo = $message;
        }// if (isset($response->error_description))

        if (
            $message == '' && isset($response->error) && $response->error === '1025012'
            // Something went wrong. Please try again later.
            || strstr($message, "Sorry, Unable to process your request. Please try after some time")
        ) {
            throw new CheckException("Something is wrong. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, true);
        $euroBonus = ArrayVal($response, 'euroBonus');
        $tierUpgrade = ArrayVal($euroBonus, 'tierUpgrade');
        // Balance - EuroBonus balance
        $this->SetBalance(ArrayVal($euroBonus, 'totalPointsForUse'));

        // JOIN EUROBONUS
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindPreg('/"errorCode":"1005008","errorMessage":"Are you sure you are trying to access the correct customer profile\? Please log out and try again","type":"Error"/')) {
            $this->SetWarning(self::NOT_MEMBER_MSG);

            return;
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindPreg('/^\{"errorInfo":\[\{"errorCode":"1015001","errorMessage":"Sorry, something went wrong. Please try again."\}\],"links":\[\]\}$/')) {
            throw new CheckException("Unfortunately, we are having some issues in retrieving your EuroBonus details. You can access all other information in your profile.", ACCOUNT_PROVIDER_ERROR);
        }

        // Name
        $this->SetProperty("Name", ArrayVal($euroBonus, 'nameOnCard'));
        // EuroBonus number
        $this->SetProperty("Number", ArrayVal($euroBonus, 'euroBonusId'));
        // Current level
        $this->SetProperty("Level", ArrayVal($euroBonus, 'currentTierName'));
        // Member since
        $this->SetProperty("MemberSince", date("F Y", strtotime(ArrayVal($euroBonus, 'dateRegistered'))));
        // Qualifying points
        $this->SetProperty("QualifyingPoints", ArrayVal($tierUpgrade, 'pointsEarnedThisYear'));
        // Qualifying flights
        $this->SetProperty("QualifyingFlights", ArrayVal($tierUpgrade, 'flightsFlownThisYear'));
        // Points required for upgrade
        $this->SetProperty("PointsRequiredForUpgrade", ArrayVal($tierUpgrade, 'pointsRequiredForUpgrade'));
        // Flights required for upgrade
        $this->SetProperty("FlightsRequiredForUpgrade", ArrayVal($tierUpgrade, 'flightsRequiredForUpgrade'));
        // Qualifying period
        $this->SetProperty("QualifyingPeriod", date("M d, Y", strtotime(ArrayVal($euroBonus, 'qualifyingPeriodStartDate'))) . " - " . date("M d, Y", strtotime(ArrayVal($euroBonus, 'qualifyingPeriodEndDate'))));

        // Expiration Date
        $this->logger->info('Expiration date', ['Header' => 3]);
        $awardPointsExpiry = ArrayVal($euroBonus, 'awardPointsExpiry', []);
        $this->logger->debug("Total " . count($awardPointsExpiry) . " exp dates were found");
        $exp = null;

        foreach ($awardPointsExpiry as $item) {
            $expiryDate = ArrayVal($item, 'expiryDate');
            $points = ArrayVal($item, 'points');

            if ($expiryDate && strtotime($expiryDate) && (!isset($exp)) || (strtotime($expiryDate) < $exp)) {
                $exp = strtotime($expiryDate);
                // Points To Expire
                $this->SetProperty("ExpiringBalance", $points);
                // Expiration Date
                $this->SetExpirationDate($exp);
            }// if ($expiryDate && strtotime($expiryDate) && (!isset($exp)) || (strtotime($expiryDate) < $exp))
        }// foreach ($awardPointsExpiry as $item)

        // Family Points    // refs #17811
        $this->logger->info('Family Points', ['Header' => 3]);
        $headers = [
            "Accept"  => "application/json, text/plain, */*",
            "Origin"  => "https://www.flysas.com",
            "Referer" => "https://www.flysas.com/us-en/eurobonus/point-sharing/",
        ];
        $data = [
            "ticket" => $this->sessionId,
        ];
        $this->http->RetryCount = 0;
        $this->delay();
        $this->http->PostURL("https://sasgrowth.azure-api.net/api/profile/auth/login", json_encode($data), $headers);

        if ($this->http->Response['code'] != 200) {
            $this->http->JsonLog();

            if (
                ($this->http->Response['code'] == 401
                    && $this->http->FindPreg("/\{\"statusCode\":401,\"error\":\"Unauthorized\",\"message\":\"(?:Invalid token|C-Shark SSO ticket expired)\"\}/"))
                || ($this->http->Response['code'] == 500
                    && $this->http->FindPreg("/\{\s*\"statusCode\":\s*500,\s*\"message\":\s*\"Internal server error\"/"))
                || ($this->http->Response['code'] == 500
                    && $this->http->FindPreg("/\{\s*\"statusCode\":\s*500,\s*\"message\":\s*\"An internal server error occurred\"/"))
                || ($this->http->Response['code'] == 502
                    && $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]"))
                || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                || strstr($this->http->Error, 'Network error 28 - Connection timed out after')
                || strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
                || $this->http->Error === 'Network error 56 - Proxy CONNECT aborted'
                || $this->http->Error === 'Network error 56 - Received HTTP code 502 from proxy after CONNECT'
                || strstr($this->http->Error, 'Network error 6 - Could not resolve host: sasgrowth.azure-api.net')
                || $this->http->Error === 'Network error 0 - '
                || strstr($this->http->Error, 'Network error 7 - Failed to connect to')
                || $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")
            ) {
                throw new CheckRetryNeededException(3, 1);
            }

            // AccountID: 2079106
//            if (!$this->http->FindPreg("/\{\"statusCode\":500,\"error\":\"Internal Server Error\",\"message\":\"An internal server error occurred\"\}/")) {
//                $this->sendNotification("refs #17811. Family pooling");
//            }

            return;
        }

        $this->http->RetryCount = 2;
        $headers = [
            "Accept"        => "application/json, text/plain, */*",
            "Origin"        => "https://www.flysas.com",
            "Referer"       => "https://www.flysas.com/us-en/eurobonus/point-sharing/",
            "Authorization" => "Bearer {$this->http->Response['body']}",
        ];
        $this->delay();
        $this->http->GetURL("https://sasgrowth.azure-api.net/api/profile/point-pool", $headers);
        $response = $this->http->JsonLog(null, 1);
        $number = $this->Properties['Number'] ?? null;

        if (!$number) {
            return;
        }
        $members = $response->members ?? [];

        foreach ($members as $member) {
            if ($member->euroBonusNumber != $number) {
                continue;
            }

            if (!isset($member->joinDate)) {
                break;
            }
            $this->SetBalance($member->pooledPoints);
            $this->AddSubAccount([
                'Code'              => "eurobonusFamilyPoints",
                'DisplayName'       => "Family Points",
                // My points / POINTS ADDED TO GROUP
                'Balance'           => $response->pointsForUse,
                "Role"              => $response->adminEuroBonusNumber == $number ? "Group owner" : "Group member",
                // MEMBER SINCE
                'MemberSince'       => date("d M Y", strtotime($member->joinDate)),
                // LOCK-IN PERIOD
                'LockInPeriod'      => !empty($member->lockInEndDate) ? date("d M Y", strtotime($member->lockInEndDate)) : null,
                "BalanceInTotalSum" => true,
            ], true);

            break;
        }// foreach ($members as $member)
    }

    public function ParseItineraries()
    {
        $result = [];
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Content-Type'    => 'application/json',
            'Referer'         => null,
            'Origin'          => 'https://www.flysas.com',
            'Accept-Language' => 'en_us',
        ];
        $this->delay();
        $this->http->GetURL("https://api2.flysas.com/reservation/reservations?context=RES&customerID={$this->customerSessionId}", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $reservations = ArrayVal($response, 'reservations', []);
        $countItineraries = count($reservations);
        $this->logger->debug("Total {$countItineraries} itineraries were found");

        if (empty($reservations) && ($msg = $this->http->FindPreg("/\"code\":\"3011203\",\"description\":\"Unfortunately, we can't get your bookings at the moment. Please try again./"))) {
            // retry not work
            $this->logger->error($msg);

            return [];
        }

        foreach ($reservations as $reservation) {
            $airlineBookingReference = ArrayVal($reservation, 'airlineBookingReference', null);
            $links = ArrayVal($reservation, 'links', null);
            $status = ArrayVal($reservation, 'status');

            if (!in_array($status, ['Confirmed', 'Waitlisted', 'Cancelled', 'Space Available', 'SpaceAvailable'])) {
                $this->sendNotification("New itinerary status was found: {$status}");
            }
            $this->logger->info("Parse Itinerary #{$airlineBookingReference}", ['Header' => 3]);

            if ($status == 'Cancelled') {
                $result[] = [
                    'Kind'          => 'T',
                    'RecordLocator' => $airlineBookingReference,
                    'Cancelled'     => true,
                ];

                continue;
            }// if ($status == 'Cancelled')

            $itineraryLink = null;

            foreach ($links as $link) {
                $href = ArrayVal($link, 'href', null);

                if (ArrayVal($link, 'rel') == "pnr Retrieve" && $href) {
                    $itineraryLink = preg_replace('#/reservation/reservations\?#', '/reservation/reservation?', $href);
                    $this->http->NormalizeURL($itineraryLink);
                }// if (ArrayVal($link, 'rel') == "pnr Retrieve")
            }// foreach ($links as $link)

            if (!$itineraryLink) {
                continue;
            }
            $headers['Authorization'] = $this->State['oauthToken'];
            $this->http->GetURL($itineraryLink . '&context=RES', $headers);
            $it = $this->ParseItinerary($reservation);
            $result[] = $it;
        }// for ($i = 0; $i < $countItineraries; $i++)
        // no its
        if (empty($result) && $this->http->FindPreg("/\"code\":\"3011204\",\"description\":\"You don't seem to have any active bookings. You could always add a booking to your profile at any time./")) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flysas.com/us-en/managemybooking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->setUserAgent("Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:81.0) Gecko/20100101 Firefox/81.0");
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.flysas.com/authorize/oauth/token?grant_type=client_credentials", [], ["Authorization" => "Basic U0FTLVVJOg=="], 100);
        $this->http->RetryCount = 2;

        if (strpos($this->http->currentUrl(), 'validate.perfdrive.com') !== false) {
            $this->http->removeCookies();
            $this->setProxyBrightData();
            $this->http->setRandomUserAgent(10);
            sleep(3);
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://api.flysas.com/authorize/oauth/token?grant_type=client_credentials", [], ["Authorization" => "Basic U0FTLVVJOg=="], 100);
            $this->http->RetryCount = 2;
        }
        $response = $this->http->JsonLog();

        if (!isset($response->access_token)) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $arFields['LastName'] = urlencode($arFields['LastName']);
        $this->http->setDefaultHeader("Authorization", $response->access_token);
        $this->http->GetURL("https://api.flysas.com/reservation/reservation?bookingReference={$arFields['ConfNo']}&lastName={$arFields['LastName']}&context=RES");
        $response = $this->http->JsonLog();
        $itinerary = $this->ParseItinerary($response);

        if (!empty($itinerary)) {
            $it = $itinerary;
        } elseif (isset($response->responseMessages[0]->description)) {
            return $response->responseMessages[0]->description;
        } elseif (isset($response->notifications[0]->description)) {
            return $response->notifications[0]->description;
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
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

    public function GetHistoryColumns()
    {
        return [
            "Date"         => "PostingDate",
            "Activities"   => "Description",
            "Points"       => "Miles",
            "Basic Points" => "Info",
            "Bonus"        => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $page = 1;
        $this->http->GetURL("https://api.flysas.com/customer/euroBonus/getAccountInfo?pageNumber={$page}&customerSessionId={$this->customerSessionId}");
        $response = $this->http->JsonLog(null, 0, true);
        $euroBonus = ArrayVal($response, 'euroBonus', null);
        $transactionHistory = ArrayVal($euroBonus, 'transactionHistory', null);

        if (!$transactionHistory) {
            return $result;
        }

        $pages = ArrayVal($transactionHistory, 'totalNumberOfPages');
        $this->logger->debug("Total {$pages} history pages were found");

        do {
            $this->logger->info("History page #{$page}", ['Header' => 3]);

            if ($page > 1) {
                if ($page > 35) {
                    $this->increaseTimeLimit();
                }

                $this->http->GetURL("https://api.flysas.com/customer/euroBonus/getAccountInfo?pageNumber={$page}&customerSessionId={$this->customerSessionId}");
                $response = $this->http->JsonLog(null, 0, true);
                $euroBonus = ArrayVal($response, 'euroBonus', null);
                $transactionHistory = ArrayVal($euroBonus, 'transactionHistory', null);
            }// if ($page > 1)
            $transactions = ArrayVal($transactionHistory, 'transaction', []);
            $page++;
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate, $transactions));
        } while (
            $page <= $pages
            && !$this->endHistory
        );

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $imgData = $this->http->FindSingleNode("//img[@alt='captcha']/@src");

        if (!$imgData) {
            return false;
        }

        $this->logger->debug('captcha: ' . $imgData);
        $marker = 'data:image/svg+xml;base64,';

        if (strpos($imgData, $marker) !== 0) {
            $this->logger->debug('no marker');

            return false;
        }
        $imgData = substr($imgData, strlen($marker));
        $file = tempnam(sys_get_temp_dir(), 'captcha');
        $this->logger->debug('captcha file: ' . $file);
        file_put_contents($file, base64_decode($imgData));

        if (!extension_loaded('imagick')) {
            $this->DebugInfo = "imagick not loaded";
            $this->logger->error("imagick not loaded");

            return false;
        }

        $image = new Imagick();
        $image->readImageBlob(file_get_contents($file));
        $image->writeImage($file . '.jpg');

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->reRecognizeTimeout = 120;
        $parameters = [
            "regsense" => 1,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $file . '.jpg', $parameters);
        unlink($file);
        unlink($file . '.jpg');

        return $captcha;
    }

    protected function parseCaptcha($currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@data-captcha-provider="recaptcha_v2"]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $currentUrl,
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function botDetectionWorkaround()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);

        if (
            !$response
            && $this->http->FindSingleNode('//b[
                    contains(text(), "Please solve this CAPTCHA in helping us understand your behavior to grant access")
                    or contains(text(), "Please solve this CAPTCHA to request unblock to the website")
                ]')
        ) {
            $this->State['botDetectionWorkaround'] = true;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);

            if (!$this->http->ParseForm(null, '//div[@class = "captcha-mid"]/form')) {
                return false;
            }
            $captcha = $this->parseReCaptcha($this->http->FindSingleNode('//div[@class = "captcha-mid"]/form/div[@class = "g-recaptcha"]/@data-sitekey'));

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("recaptcha_response", $captcha);
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $headers = [
                'Accept'          => '*/*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Content-Type'    => 'application/x-www-form-urlencoded',
                'Origin'          => 'https://validate.perfdrive.com',
                'Referer'         => $this->http->currentUrl(),
            ];

            if (!$this->http->PostForm($headers)) {
                return false;
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "However, your activity and behavior still make us think that you are a bot. We request you to try accessing the site/app after sometime.")]')) {
                $this->State['botDetectionWorkaround'] = true;
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return false;
    }

    private function delay()
    {
        $delay = rand(1, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || ($this->http->Response['code'] == 502 && $this->http->FindSingleNode("//h1[contains(text(), '502 - Bad Gateway')]"))
            || ($this->http->Response['code'] == 500 && empty($this->http->Response['body']))
            || ($this->http->Response['code'] == 504 && $this->http->FindSingleNode('//h1[contains(text(), "504 - Gateway Timeout")]'))
            || $this->http->FindSingleNode('//p[contains(text(), "HTTP Error 503. The service is unavailable.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Request processing failed; nested exception is org.springframework.jdbc.CannotGetJdbcConnectionException
        if ($this->http->FindSingleNode('//pre[contains(text(), "Request processing failed; nested exception is org.springframework.jdbc.CannotGetJdbcConnectionException")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function ParseItinerary($reservationInfo)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $response = $this->http->JsonLog(null, 0, true);
        $reservations = ArrayVal($response, 'reservations');

        if (isset($reservations[0])) {
            $reservation = $reservations[0];
        } else {
            $this->logger->error($response['responseMessages'][0]['description'] ?? null);

            if (
                isset($response['responseMessages'][0]['description'])
                && $response['responseMessages'][0]['description'] == 'Sorry, something went wrong when we were processing your request. Please try again.'
            ) {
                $this->logger->notice("grab info from main json");
                $reservation = $reservationInfo;
            }

            if (!isset($reservation)) {
                return $result;
            }
        }

        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = ArrayVal($reservation, 'airlineBookingReference');
        $status = ArrayVal($reservation, 'status');
        $bookingClasses = [];

        if (is_array($status)) {
            $statusDetails = ArrayVal($status, 'statusDetails', []);
            $status = ArrayVal($status, 'reservationStatus');

            foreach ($statusDetails as $statusDetail) {
                $resStatus = ArrayVal($statusDetail, 'reservationStatus', []);

                foreach ($resStatus as $id => $flight) {
                    $hk = ArrayVal($flight, 'HK', []);

                    if (!empty($bookingClass = ArrayVal($hk, 'bookingClass'))) {
                        $bookingClasses[$id][] = $bookingClass;
                    }
                }
            }
        }
        $result['Status'] = $status;
        // Passengers
        $passengers = ArrayVal($reservation, 'passengers', []);

        foreach ($passengers as $passenger) {
            $result['Passengers'][] = beautifulName(ArrayVal($passenger, 'firstName') . " " . ArrayVal($passenger, 'lastName'));

            if (isset($passenger['engagements']['euroBonus'][0]['id'])) {
                $result['AccountNumbers'][] = $passenger['engagements']['euroBonus'][0]['id'];
            }
        }

        if (!empty($result['AccountNumbers'])) {
            $result['AccountNumbers'] = array_unique($result['AccountNumbers']);
        }
        // TicketNumbers
        $tickets = [];
        $documents = ArrayVal($reservation, 'documentInformation', []);

        foreach ($documents as $document) {
            $flights = ArrayVal($document, 'flights', []);

            foreach ($flights as $flight) {
                foreach (ArrayVal($flight, 'documentNumber', []) as $ticket) {
                    $tickets[] = $ticket;
                }
            }
        }
        $this->logger->debug(var_export($tickets, true));
        $tickets = array_unique(array_filter($tickets, function ($s) {
            return preg_match("/^\d{3}-\d+$/", $s);
        }));

        if (!empty($tickets)) {
            $result['TicketNumbers'] = $tickets;
        }

        // Air trip segment

        $ancillaryProducts = ArrayVal($reservation, 'ancillaryProducts', []);

        if (empty($ancillaryProducts)) {
            $ancillaryProducts = ArrayVal($reservation, 'ancillaries', []);
        }
        // meal
        $meals = [];

        if (!empty($meal = ArrayVal($ancillaryProducts, 'meal'))) {
            $paxMeals = ArrayVal($meal, 'passengers', []);

            foreach ($paxMeals as $pax) {
                $connections = ArrayVal($pax, 'connections', []);

                foreach ($connections as $connection) {
                    $segments = ArrayVal($connection, 'segments');

                    foreach ($segments as $id => $segment) {
                        $r = ArrayVal($segment, 'allowance', []);

                        if (!empty($r)) {
                            $meals[$id][] = ArrayVal(array_shift($r), 'name');
                        }
                    }
                }
            }
        }
        // seats
        $seats = [];

        if (!empty($seat = ArrayVal($ancillaryProducts, 'seat'))) {
            $paxMeals = ArrayVal($seat, 'passengers', []);

            foreach ($paxMeals as $pax) {
                $connections = ArrayVal($pax, 'connections', []);

                foreach ($connections as $connection) {
                    $segments = ArrayVal($connection, 'segments');

                    foreach ($segments as $id => $segment) {
                        $r = ArrayVal($segment, 'allowance', []);

                        if (!empty($r)) {
                            $number = ArrayVal(array_shift($r), 'number');

                            if ($this->http->FindPreg("/^\d+[A-Z]$/", false, $number)) {
                                $seats[$id][] = $number;
                            }
                        }
                    }
                }
            }
        }

        $connections = ArrayVal($reservation, 'connections', []);
        $this->logger->debug('Total ' . count($connections) . ' legs were found');

        foreach ($connections as $connection) {
            $flightSegments = ArrayVal($connection, 'flightSegments', []);

            if (empty($flightSegments)) {
                foreach ($connection as $bound) {
                    $status = ArrayVal($bound, 'status');

                    if (!in_array($status, ['Active', 'Flown'])) {
                        $this->logger->error('new status out/in-Bound leg');

                        break 2;
                    }
                    $flightSegments = array_merge($flightSegments, ArrayVal($bound, 'flightSegments', []));
                }
            }
            $this->logger->debug('Total ' . count($flightSegments) . ' segments were found');

            foreach ($flightSegments as $flightSegment) {
                $segment = [];

                $segment['FlightNumber'] = ArrayVal(ArrayVal($flightSegment, 'operatingCarrier'), 'flightNumber');

                if (!$segment['FlightNumber']) {
                    $segment['FlightNumber'] = ArrayVal(ArrayVal($flightSegment, 'marketingCarrier'), 'flightNumber');
                }
                $segment['AirlineName'] = ArrayVal(ArrayVal($flightSegment, 'operatingCarrier'), 'code');

                if (!$segment['AirlineName']) {
                    $segment['AirlineName'] = ArrayVal(ArrayVal($flightSegment, 'marketingCarrier'), 'code');
                }
                $segment['Operator'] = ArrayVal(ArrayVal($flightSegment, 'operatingCarrier'), 'name');
                $segment['Aircraft'] = ArrayVal(ArrayVal($flightSegment, 'aircraft'), 'name');
                $segment['Duration'] = $this->http->FindPreg("/^PT(\d.+)$/", false, $flightSegment['duration'] ?? '');
                $segment['DepDate'] = strtotime(ArrayVal($flightSegment, 'scheduledDepartureLocal', null), false);

                if (empty($segment['DepDate'])) {
                    $segment['DepDate'] = strtotime(
                        ($flightSegment['scheduledDepartureDateTimeLocal'] ?? '')
                        ?: ($flightSegment['scheduledDepartureDateLocal'] ?? '')
                    );
                }
                $segment['DepCode'] = ArrayVal(ArrayVal($flightSegment, 'departure'), 'airportCode');
                $segment['DepartureTerminal'] = ArrayVal(ArrayVal($flightSegment, 'departure'), 'terminal');
                $segment['ArrCode'] = ArrayVal(ArrayVal($flightSegment, 'arrival'), 'airportCode');
                $segment['ArrivalTerminal'] = ArrayVal(ArrayVal($flightSegment, 'arrival'), 'terminal');
                $segment['ArrDate'] = strtotime(ArrayVal($flightSegment, 'scheduledArrivalLocal', null), false);

                if (empty($segment['ArrDate'])) {
                    $segment['ArrDate'] = strtotime(
                        ($flightSegment['scheduledArrivalDateTimeLocal'] ?? '')
                        ?: ($flightSegment['scheduledArrivalDateLocal'] ?? '')
                    );
                }
                $segment['Cabin'] = $flightSegment['passengerFlightSegments'][0]['serviceClassName'] ?? null;
                $segment['BookingClass'] = $flightSegment['passengerFlightSegments'][0]['bookingClass'] ?? null;

                $flightSegmentId = $flightSegment['id'] ?? null;

                if ($flightSegmentId) {
                    foreach ($ancillaryProducts as $ancillaryProduct) {
                        $associatedPassengers = ArrayVal($ancillaryProduct, 'associatedPassengers', []);

                        if (isset($associatedPassengers[0]['associatedFlightSegments'][0])
                            && $associatedPassengers[0]['associatedFlightSegments'][0] == $flightSegmentId) {
                            $seat = $ancillaryProduct['seatDetails'][0]['number'] ?? null;

                            if ($seat && $this->http->FindPreg("/^\d+[A-Z]$/", false, $seat)) {
                                $segment['Seats'][] = $seat;
                            }
                        }// && ArrayVal($associatedPassengers[0]['associatedFlightSegments'], 'id') == $flightSegmentId)
                    }// foreach ($ancillaryProducts as $ancillaryProduct)

                    if (isset($meals['FS_' . $flightSegmentId])) {
                        $segment['Meal'] = trim(implode('|', array_unique($meals['FS_' . $flightSegmentId])));
                    }

                    if (isset($seats['FS_' . $flightSegmentId])) {
                        $segment['Seats'] = array_unique($seats['FS_' . $flightSegmentId]);
                    }

                    if (empty($segment['BookingClass']) && isset($bookingClasses['FS_' . $flightSegmentId])) {
                        $segment['BookingClass'] = implode('|', array_unique($bookingClasses['FS_' . $flightSegmentId]));
                    }
                }// if ($flightSegmentId)

                if (
                    $segment['DepCode'] === $segment['ArrCode']
                    && $segment['ArrCode']
                    && $segment['DepCode']
                    && $segment['ArrCode'] === $segment['DepCode']
                ) {
                    $this->logger->error("Skipping invalid segment ({$segment['AirlineName']}{$segment['FlightNumber']}) with the same dep / arr codes and dates");

                    continue;
                }
                $result['TripSegments'][] = $segment;
            }// foreach ($flightSegments as $flightSegment)
        }// foreach ($connections as $connection)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('fc-token', $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindPreg('/funcaptcha.com.+?pkey=([\w\-]+)/');
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        // // anticaptcha version
        // $postData = array_merge(
        //     [
        //         "type"             => "FunCaptchaTask",
        //         "websiteURL"       => $this->http->currentUrl(),
        //         "websitePublicKey" => $key,
        //     ],
        //     []
        //     // $this->getCaptchaProxy()
        // );
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        // $recognizer->RecognizeTimeout = 120;
        // $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // rucaptcha version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function ParseHistoryPage($startIndex, $startDate, $transactions)
    {
        $result = [];
        $this->logger->debug("Total " . count($transactions) . " transactions were found");

        foreach ($transactions as $transaction) {
            $dateStr = ArrayVal($transaction, 'datePerformed');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Activities'] = ArrayVal($transaction, 'description');

            if (!empty($result[$startIndex]['Activities'])) {
                $this->sendNotification("refs #17811, See history // RR");
            } else {
                $result[$startIndex]['Activities'] = ArrayVal($transaction, 'description1') . " " . ArrayVal($transaction, 'description2');
            }
            $basicPointsAfterTransaction = ArrayVal($transaction, 'basicPointsAfterTransaction', null);

            if ($basicPointsAfterTransaction) {
                $result[$startIndex]['Activities'] .= ". " . $basicPointsAfterTransaction;
            }
            // refs #5907
            $points = ArrayVal($transaction, 'availablePointsAfterTransaction');

            if (stristr($basicPointsAfterTransaction, 'Points used') && $points > 0) {
                $points = '-' . $points;
            }

            if ($this->http->FindPreg("/Extra Points/ims", false, $basicPointsAfterTransaction)) {
                $result[$startIndex]['Bonus'] = $points;
            } else {
                $result[$startIndex]['Points'] = $points;
            }
            $result[$startIndex]['Basic Points'] = ArrayVal($transaction, 'basicPoints', 0);
            $startIndex++;
        }

        return $result;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $this->selenium = true;
        $selenium = clone $this;
        $selenium->usePacFile(false);
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
//            $selenium->setProxyGoProxies();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            /*
            $selenium->useFirefoxPlaywright(SeleniumFinderRequest::FIREFOX_PLAYWRIGHT_102);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            */

            /*
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            */
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            /** @var SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $seleniumDriver->browserCommunicator->blockRequests([
                '*://*.doubleclick.net/*',
                '*://*.google-analytics.com/*',
                '*://*.googlesyndication.com/*',
                '*://fonts.gstatic.com/*',
                '*://*.quantummetric.com/*',
                '*://*.facebook.com/*',
                '*://*.tiktok.com/*',
                //'*://*.akstat.io/*', // triggering bot protection
                '*://*.optimizely.com/*',
                '*://*.liveperson.*/*',
                //'*://*.go-mpulse.net/*', // triggering bot protection
                '*://*.tiqcdn.com/*',
                '*://analytics.tiktok.com/*',
                '*://*.googletagmanager.com/*', // ?
                '*://images.ctfassets.net/*',
                '*://cdn-assets-eu.frontify.com/*?width=*',
                '*.woff',
            ]);

            $selenium->http->GetURL("https://www.flysas.com/us-en/");
            $selenium->http->GetURL("https://www.flysas.com/us-en/profile/settings/");

            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@name="username"]
                | //span[@element = "login-label"]
                | //input[@value = "Verify you are human"] | //div[@id = "turnstile-wrapper"]//iframe
            '), 20);
            $this->savePageToLogs($selenium);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 7);

            $this->acceptCookies($selenium);

            if (!$loginInput && ($menu = $selenium->waitForElement(WebDriverBy::xpath('//span[@element = "login-label"]'), 0))) {
                $this->savePageToLogs($selenium);
                $menu->click();
//                $selenium->driver->executeScript('document.querySelector(\'[element="login-label"]\').click()');

                $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "login-button"]'), 7);

                if ($loginBtn) {
                    $this->logger->debug("click by Login btn");
                    $this->savePageToLogs($selenium);
                    $loginBtn->click();
                    sleep(2);
                } else {
                    $this->logger->notice("js injection");

                    try {
                        $selenium->driver->executeScript("document.querySelector('#login-button').click()"); // seems that button just don't appear sometimes, selenium bug or something
                    } catch (Facebook\WebDriver\Exception\JavascriptErrorException | WebDriverException $e) {
                        $this->logger->error("JavascriptErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
//                        $retry = true;
                    }
                }

                $selenium->waitForElement(WebDriverBy::xpath('
                    //input[@name="username"]
                    | //span[@element = "login-label"]
                    | //input[@value = "Verify you are human"] | //div[@id = "turnstile-wrapper"]//iframe
                '), 20);
                $this->savePageToLogs($selenium);
                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 0);

                if (!$loginInput) {
                    $this->savePageToLogs($selenium);
                }
            }

//            $this->clickCloudFlareCheckbox($selenium);
            $this->clickCloudFlareCheckboxByMouse($selenium);

            $loginInput = $loginInput ?? $selenium->waitForElement(WebDriverBy::xpath('//input[@name="username"]'), 0);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@name="action" and not(@style)]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

            $this->acceptCookies($selenium);

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (
                            (/"customerSessionId":/g.exec( this.responseText ) && /"access_token":/g.exec( this.responseText ))
                            || /"error_description\"/g.exec( this.responseText )
                        ) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            //$button->click();
            $selenium->driver->executeScript("document.querySelector('button[name=action]').click();");
            sleep(4);
            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);
            $this->savePageToLogs($selenium);

            /*
            if (
                empty($responseData)
                && $this->clickCloudFlareCheckboxByMouse($selenium)
            ) {
                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="password"]'), 5);

                if (!$passwordInput) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $passwordInput->sendKeys($this->AccountFields['Pass']);
                $this->savePageToLogs($selenium);

                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@name="action" and not(@style)]'), 0);
                //$button->click();
                $selenium->driver->executeScript("document.querySelector('button[name=action]').click();");
                sleep(4);
                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $responseData);
                $this->savePageToLogs($selenium);
            }
            */

            $selenium->waitForElement(WebDriverBy::xpath('
                //a[@id = "logoutLinkAccess"]
                | //span[@class = "points"]
                | //strong[contains(text(), "Log out")]
                | //div[@id = "customer-error"]
                | //div[@id = "prompt-alert"]
                | //span[contains(@class, "ulp-input-error-message") and normalize-space(.) != ""]
                | //div[contains(@class, "-notification-error")]//div[@element = "content"]
                | //h1[contains(text(), "NEW MEMBERSHIP CONDITIONS")]
                | //h1[contains(text(), "502 Bad Gateway")]
            '), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            $response = [];

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'LOGIN_AUTH') {
                    $response['access_token'] = $cookie['value'];

                    foreach (explode('.', $cookie['value']) as $str) {
                        $str = base64_decode($str);
                        $this->logger->debug($str);

                        if ($sessionId = $this->http->FindPreg('/"sessionId":"(.+?)"/', false, $str)) {
                            $response['sessionId'] = $sessionId;

                            if ($customerSessionId = $this->http->FindPreg('/"customerSessionId":"([^"]+)"/', false, $str)) {
                                $response['customerSessionId'] = $customerSessionId;
                            }

                            break;
                        }
                    }
                }

                if ($cookie['name'] == 'PROFILE_ID') {
                    $response['customerSessionId'] = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[responseData]: " . $responseData);

            if (!empty($responseData)) {
                $this->logger->debug("[!responseData]: " . $responseData);
                $this->http->SetBody($responseData, false);
                $this->sendNotification('auth 1 // MI');

                return true;
            }
            $this->logger->debug("[!response]: " . json_encode($response));

            if ($messega = $this->http->FindSingleNode('
                    (//div[@id = "customer-error"]
                    | //div[@id = "prompt-alert"]
                    | //span[contains(@class, "ulp-input-error-message") and normalize-space(.) != ""]
                    | //span[@class = "points"]
                    | //strong[contains(text(), "Log out")]
                    | //h1[contains(text(), "NEW MEMBERSHIP CONDITIONS")])[1]
                    | //h1[contains(text(), "502 Bad Gateway")]
                ')
                ?? $this->http->FindSingleNode('//*[self::strong or self::a][contains(text(), "Log out")] | //span[@class="initials"]')
            ) {
                if (!empty($response['access_token'])
                    && !strstr($messega, 'NEW MEMBERSHIP CONDITIONS')
                    && !strstr($messega, '502 Bad Gateway')
                ) {
                    $this->http->SetBody(json_encode($response));
                }

                $this->setCookieByReservation($selenium);

                return true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } catch (NoSuchDriverException | NoSuchWindowException | TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return false;
    }

    private function setCookieByReservation($selenium)
    {
        $this->logger->debug(__METHOD__);
        $selenium->http->GetURL('https://www.flysas.com/us-en/managemybooking/');
        sleep(5);
        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function acceptCookies($selenium)
    {
        $this->logger->notice(__METHOD__);

        if ($acceptCookies = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "accept")]'), 0)) {
            $acceptCookies->click();

            sleep(2);
            $this->savePageToLogs($selenium);
        }
    }
}
