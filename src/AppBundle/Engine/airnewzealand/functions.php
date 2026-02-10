<?php

use AwardWallet\Common\OneTimeCode\OtcHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirnewzealand extends TAccountChecker
{
    use ProxyList;
    use OtcHelper;

    public $regionOptions = [
        ""                => "Select your region",
        "Australia"       => "Australia",
        "Canada"          => "Canada",
        "China"           => "China",
        //        "Deutschland"     => "Deutschland",
        //        "France"          => "France",
        "HongKong"        => "Hong Kong",
        "Japan"           => "Japan",
        "NewZealand"      => "New Zealand & Continental Europe",
        "PacificIslands"  => "Pacific Islands",
        //        "FrenchPolynesia" => "French Polynesia",
        "UK"              => "United Kingdom & Republic of Ireland",
        "USA"             => "United States",
    ];

    private $host = 'www.airnewzealand.co.nz';
    private $domain = 'airnewzealand.co.nz';
    private $AirCodes;
    private $familyName = null;
    private $currentItin = 0;

    /** @var CaptchaRecognizer */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerAirnewzealandSelenium.php";

        return new TAccountCheckerAirnewzealandSelenium();
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        //$this->http->SetProxy($this->proxyDOP());

        $this->setRegionSettings();

        if ($this->attempt == 2) {
            $this->setProxyGoProxies(null, 'gb');
        } elseif (in_array($this->AccountFields['Login2'], ['HongKong', 'NewZealand'])) {// setProxyGoProxies issue
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->setProxyMount();
//            $this->setProxyGoProxies();
        }
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {
            $this->http->setRandomUserAgent(10);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances/airpoints");

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances");

        if (strstr($this->http->currentUrl(), '/airpoints-account/airpoints/member/dashboard')) {
//            $this->http->GetURL("https://{$this->host}/airpoints-account/airpoints/auth/login?redirectTo=/member/dashboard");
//            $this->http->GetURL("https://identity.airnewzealand.com/customerairnz.onmicrosoft.com/b2c_1a_airnz_susi/oauth2/v2.0/authorize?client_id=5cfd7b3b-f095-4a1e-9860-5f03ade4d715&scope=openid%20profile%20offline_access&redirect_uri=https%3A%2F%2F{$this->host}%2Fairpoints-account%2Fairpoints%2Fauth%2Flogin&client-request-id=01914548-2b7f-79a7-81fb-fb4b5becedf6&response_mode=fragment&response_type=code&x-client-SKU=msal.js.browser&x-client-VER=3.13.0&client_info=1&code_challenge=m_GP4k7X90sAPGuXSromt3YO0F4Up8DpNsYqrlmg7sI&code_challenge_method=S256&nonce=01914548-2b80-787e-a2c5-5b1e9c621465&state=eyJpZCI6IjAxOTE0NTQ4LTJiN2YtNzgxNS1iOTRhLTUwZGExYTI2Nzk0OSIsIm1ldGEiOnsiaW50ZXJhY3Rpb25UeXBlIjoicmVkaXJlY3QifX0%3D");
            $this->http->GetURL("https://" . str_replace('www', 'auth', $this->host) . "/vauth/oauth2/authorize?response_type=token&redirect_uri=https://{$this->host}/vloyalty/action/mybalances&client_id=6f80c76a-b19f-46c7-8873-a4cb087272b4&state=");
        }

        if ($this->http->FindPreg("/<form id=\"localAccountForm\" action/")) {
            $csrf = $this->http->FindPreg('/"csrf":"(.+?)",/');
            $tenant = $this->http->FindPreg("/\"tenant\"\s*:\s*\"([^\"]+)/");
            $transId = $this->http->FindPreg("/\"transId\"\s*:\s*\"([^\"]+)/");
//            $pageViewId = $this->http->FindPreg("/\"pageViewId\"\s*:\s*\"([^\"]+)/");
            $p = $this->http->FindPreg("/\"policy\"\s*:\s*\"([^\"]+)/");

            if (!$csrf || !$transId || !$p) {
                return false;
            }

            $data = [
                "request_type" => "RESPONSE",
                "signInName"   => $this->AccountFields['Login'],
            ];
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
                "X-CSRF-TOKEN"     => $csrf,
                "X-Requested-With" => "XMLHttpRequest",
            ];
            $this->State['headers'] = $headers;
            $this->http->PostURL("https://identity.airnewzealand.com{$tenant}/SelfAsserted?tx={tx}&p={$p}", $data, $headers);
            $response = $this->http->JsonLog();
            $status = $response->status ?? null;

            if ($status !== "200") {
                $message = $response->message ?? null;

                if ($message) {
                    $this->logger->error("[Error]: {$message}");

                    if ($message == 'Airpoints™ number / username doesn\'t match our records.') {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;
                }

                return false;
            }

            $this->logger->notice("Logging in...");
            $param = [];
            $param['rememberMe'] = "true";
            $param['csrf_token'] = $csrf;
            $param['tx'] = $transId;
            $param['p'] = $p;
            $param['diags'] = '{"pageViewId":"c39227d2-84ba-45ac-9bc9-311c04782b3f","pageId":"CombinedSigninAndSignup","trace":[{"ac":"T005","acST":1702015817,"acD":2},{"ac":"T021 - URL:https://airnzb2cbranding.blob.core.windows.net/b2cbranding/business-customer/v3.html","acST":1702015817,"acD":1312},{"ac":"T019","acST":1702015819,"acD":2},{"ac":"T004","acST":1702015820,"acD":1},{"ac":"T003","acST":1702015821,"acD":1},{"ac":"T035","acST":1702015823,"acD":0},{"ac":"T030Online","acST":1702015823,"acD":0},{"ac":"T002","acST":1702015867,"acD":0},{"ac":"T018T010","acST":1702015866,"acD":1538}]}';
            $this->http->GetURL("https://identity.airnewzealand.com{$tenant}/api/CombinedSigninAndSignup/confirmed?" . http_build_query($param));
            $this->http->RetryCount = 2;

            $this->State['tenant'] = $tenant;
            $this->State['p'] = $p;
            $this->State['transId'] = $transId;
            $this->State['csrf_token'] = $csrf;

//            $this->http->SetInputValue("request_type", "RESPONSE");
//            $this->http->SetInputValue("signInName", $this->AccountFields['Login']);
//
//            $this->http->PostForm();

            return true;
        }

        if (!$this->http->ParseForm("login")) {
            if (
                $this->http->FindSingleNode('//h2[contains(text(), "The request could not be satisfied.")]')
                || $this->http->FindPreg('/(Operation timed out after|Network error 56 - Proxy CONNECT aborted|Network error 28 - Connection timed out after|Connection refused|Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to)/', false, $this->http->Error)
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
            }

            return $this->checkErrors();
        }
        $this->http->SetInputValue("xv_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("xv_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("xv_rememberme", "on");
        $this->http->SetInputValue("password", "");
        // fix cookie
        $host = $this->host;

        if ($this->host === "www.pacificislands.airnewzealand.com") {
            $host = "www.airnewzealand.co.nz";
        }
        // LastLoginTime
        $this->http->setCookie("LastLoginTime", date('UB', strtotime('-1 day')), str_replace("www.", "auth.", $host), "/vauth", null, true);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        return true;
    }

    public function Login()
    {
        $this->brokenRememberMeWorkaround();

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->needsCaptcha()) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("g-recaptcha-response", $captcha);
            $this->http->SetInputValue("canswer", $captcha);
            $this->http->SetInputValue("xv_username", $this->AccountFields['Login']);
            $this->http->SetInputValue("xv_password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("xv_rememberme", "on");
            $this->http->SetInputValue("password", "");

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }
        }

        if ($this->jsRedirect()) {
            $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances");
        }

        // Invalid credentials
        if ($message = $this->http->FindPreg("/The Airpoints(?:&trade;|™)? number\/username or password do not match our records/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/(We could not log you in with your email address\.\s*Please try again using your airpoints(?:&trade;) number\/username\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/So that you get most out of your online account/ims")
            && $this->http->FindPreg("/>Join Airpoints for free<\/a>/ims")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
        }

        // Redirect to the Air New Zealand site that matches account country of residence.
        $this->redirectToRightCountrySite();

        if ($this->loginSuccessful()) {
            return true;
        }
        // To sign in to our website you now need to be an Airpoints™ Member. If you are not already an Airpoints Member, you can join up now for free!
        if ($this->http->FindPreg("/To sign in to our website you now need to be an Airpoints\&trade\;\s*Member\./ims")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_INVALID_PASSWORD);
        }
        // Service unavailable
        if ($message = $this->http->FindSingleNode('//div[@id = "message" and @class = "app-error"]/p[contains(text(), "Service unavailable")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We were not able to log you in. It seems our server is currently under load.
        if ($this->http->FindSingleNode("//p[contains(text(), 'We were not able to log you in. It seems our server is currently under load.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Confirm your contact details')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // gap
        if ($this->host == 'www.airnewzealand.com.sg') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/window\.location\.replace\('\/vloyalty\/action\/serviceunavailable'\);/")) {
            throw new CheckException("Service unavailable", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), '/challenge')
            && !strstr($this->http->currentUrl(), '/token')
        ) {
            return false;
        }

        $token = $this->http->FindPreg("/token=([^&]+)/", false, $this->http->currentUrl());
        $redirectUrl = $this->http->FindPreg("/redirectUrl=([^&]+)/", false, $this->http->currentUrl());
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $this->http->currentUrl());

        if (!$token || !$redirectUrl || !$state) {
            $this->logger->error("token not found");

            return false;
        }

        $this->State['redirectUrl'] = urldecode($redirectUrl);
        $this->State['state'] = urldecode($state);

        $this->http->PostURL("https://au.signal.authsignal.com/v1/client/token", "{}", [
            "Accept"        => "application/json",
            "Authorization" => "Bearer {$token}",
            "Content-Type"  => "application/json",
            "Origin"        => "https://auth.identity.airnewzealand.com",
        ]);

        $responseToken = $this->http->JsonLog();

        if (!isset($responseToken->token)) {
            $this->logger->error("responseToken not found");

            return false;
        }

        $headers = [
            "Accept"        => "application/json",
            "Authorization" => "Bearer {$responseToken->token}",
            "Content-Type"  => "application/json",
            "Origin"        => "https://auth.identity.airnewzealand.com",
            "Referer"       => "https://auth.identity.airnewzealand.com/",
        ];
        $this->http->GetURL("https://au.signal.authsignal.com/v1/client/user-authenticators", $headers);
        $responseAuthenticators = $this->http->JsonLog();

        if (!$responseAuthenticators) {
            return false;
        }

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $email = null;
        $emailOTP = false;
        $emailUserAuthenticatorId = null;
        $emailVerificationMethod = null;
        $phone = null;
        $phoneUserAuthenticatorId = null;
        $phoneVerificationMethod = null;

        foreach ($responseAuthenticators as $responseAuthenticator) {
            // AUTHENTICATOR_APP
            if (!isset($responseAuthenticator->oobChannel)) {
                continue;
            }

            if ($responseAuthenticator->oobChannel == 'EMAIL_MAGIC_LINK') {
                $email = $responseAuthenticator->email;
                $emailUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $emailVerificationMethod = $responseAuthenticator->oobChannel;
            }

            if ($responseAuthenticator->oobChannel == 'SMS') {
                $phone = $responseAuthenticator->phoneNumber;
                $phoneUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $phoneVerificationMethod = $responseAuthenticator->oobChannel;
            }

            if ($responseAuthenticator->oobChannel == 'EMAIL_OTP' && !$email) {
                $emailOTP = true;
                $email = $responseAuthenticator->email;
                $emailUserAuthenticatorId = $responseAuthenticator->userAuthenticatorId;
                $emailVerificationMethod = $responseAuthenticator->oobChannel;
            }
        }// foreach ($responseAuthenticators as $responseAuthenticator)

        $verificationMethod = $email ? $emailVerificationMethod : $phoneVerificationMethod;
        /*
        $this->http->GetURL("https://auth.identity.airnewzealand.com/_next/data/BCVpAKz8rYygiZ5yMJRnw/en/challenge/verification-method.json?verificationMethod={$verificationMethod}");
        */

        $data = [
            "userAuthenticatorId" => $email ? $emailUserAuthenticatorId : $phoneUserAuthenticatorId,
        ];
        $verificationMethodChallenge = strtolower(str_replace('_', '-', $verificationMethod));
        $this->http->PostURL("https://au.signal.authsignal.com/v1/client/challenge/{$verificationMethodChallenge}", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->challengeId)) {
            return false;
        }

        $this->State['headers'] = $headers;
        $this->State['token'] = $responseToken->token;
        $this->State['challengeId'] = $response->challengeId;

        if ($email && !$emailOTP) {
            $this->Question = "A magic link was sent to {$email}. Copy and paste the link below";
            $this->Step = "VerificationViaLink";
        } elseif ($email && $emailOTP) {
            $this->Question = "An authentication code was sent to {$email}.";
            $this->Step = "QuestionEmailOTP";
        } else {
            $this->Question = "An authentication code was sent to {$phone}";
            $this->Step = "Question2fa";
        }

        $this->ErrorCode = ACCOUNT_QUESTION;

        return true;
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        if ($step == 'Question2fa') {
            $data = [
                "sessionToken"     => $this->State['challengeId'],
                "verificationCode" => $answer,
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/sms", json_encode($data), $this->State['headers']);
        } elseif ($step == 'QuestionEmailOTP') {
            $data = [
                "verificationCode" => $answer,
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/email-otp", json_encode($data), $this->State['headers']);
        } else {
            if (!filter_var($answer, FILTER_VALIDATE_URL)) {
                $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "VerificationViaLink"); /*review*/

                return false;
            }

            $this->http->GetURL($answer);

            $this->http->RetryCount = 0;
            $data = [
                'challengeId' => $this->State['challengeId'],
            ];
            $this->http->PostURL("https://au.signal.authsignal.com/v1/client/verify/email-magic-link/finalize", json_encode($data), $this->State['headers']);
            $this->http->RetryCount = 2;
        }

        $response = $this->http->JsonLog();

        if (!empty($response->failureReason)) {
            switch ($response->failureReason) {
                case 'CODE_INVALID_OR_EXPIRED':
                    $this->AskQuestion($this->Question, "Code is invalid or has expired", $step);

                    break;

                default:
                    $this->DebugInfo = "2fa: {$response->failureReason}";
            }

            return false;
        }// if (!empty($response->failureReason))

        $headers = [
            "Accept"  => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Referer" => "https://auth.identity.airnewzealand.com/",
        ];
        $this->http->setMaxRedirects(10);
        $this->http->GetURL("{$this->State['redirectUrl']}&token={$this->State['token']}&state={$this->State['state']}", $headers);
        $this->http->setMaxRedirects(7);

        unset($this->State['redirectUrl']);
        unset($this->State['headers']);
        unset($this->State['token']);
        unset($this->State['state']);
        unset($this->State['challengeId']);
        $this->brokenRememberMeWorkaround();

        // Redirect to the Air New Zealand site that matches account country of residence.
        $this->redirectToRightCountrySite();

        return $this->loginSuccessful();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://{$this->host}/vloyalty/action/mybalances/airpoints") {
            $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances/airpoints");
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg('/"displayName":"(.+?)"}/')));
        $this->familyName = $this->http->FindPreg('/"familyName":"(.+?)"/');
        // Airpoints no.
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(@id, 'airpoints-number-container') or @class = 'airpoints-number']", null, true, '/(?:no\.|number)\s*([^<]+)/ims'));

        if (!empty($this->Properties['Number'])) {
            $this->SetProperty("Number", $this->http->FindPreg('/(?:no\.|number)\s*([^<]+)/ims', false, $this->Properties['Number']) ?? $this->Properties['Number']);
        }
        // Airpoints Advance
        $this->SetProperty("Advance", $this->http->FindSingleNode("//div[contains(text(), '+ Advance') and @class = 'airpoints-tile-label']/preceding-sibling::div[1]"));
        // Available balance
        $this->SetProperty("Available", $this->http->FindSingleNode("//div[contains(text(), 'Available balance') and @class = 'airpoints-tile-label']/preceding-sibling::div[1]"));
        // Status Points Earned to Retain Status
        $this->SetProperty("RetainEarned", $this->http->FindSingleNode("//p[contains(text(), 'To retain')]/following-sibling::p[contains(@class , 'earned')]/span"));
        // Status Points Needed to Retain Status
        $this->SetProperty("RetainNeeded", $this->http->FindSingleNode("//p[contains(text(), 'To retain')]/following-sibling::p[contains(@class , 'needed')]/span"));
        // Status Points Earned to Upgrade Status
        $this->SetProperty("UpgradeEarned", $this->http->FindSingleNode("//p[contains(text(), 'To upgrade to')]/following-sibling::p[contains(@class , 'earned')]/span"));
        // Status Points Needed to Upgrade Status
        $this->SetProperty("UpgradeNeeded", $this->http->FindSingleNode("//p[contains(text(), 'To upgrade to')]/following-sibling::p[contains(@class , 'needed')]/span"));
        // Balance - Airpoints Dollars™
        if (!$this->SetBalance($this->http->FindSingleNode("//h5[contains(text(), 'Airpoints Dollars')]/following-sibling::p[@class = 'airpoints-balance']"))) {
            // Airpoints Dollars™ - start earning now
            if ($this->http->FindSingleNode("//h5[contains(text(), 'Airpoints Dollars')]/following-sibling::p[contains(text(), 'start earning now')]")
                && isset($this->Properties['Number'])) {
                $this->SetBalance(0);
            }
        }
        $this->SetProperty('ShairPoints', $this->http->FindSingleNode('//li[@id="shairpoints-tab"]//p[contains(@class, "airpoints-balance")]'));
        // Status
        if ($status = $this->http->FindPreg('/"membershipTier":"([[:alpha:]]+)",/')) {
            $this->SetProperty('Status', beautifulName($status));
        } elseif ($this->http->FindPreg('/"membershipTier":"",/')) {
            $this->SetProperty('Status', 'Member');
        } elseif ($this->ErrorCode == ACCOUNT_CHECKED) {
            $this->sendNotification("unknown status");
        }

        // Expiration Date
        $exp = $this->http->FindSingleNode("//div[@id = 'airpoints-expiry-tile']//div[@class = 'airpoints-tile-label']");
        $expBalance = $this->http->FindSingleNode("//div[@id = 'airpoints-expiry-tile']//div[@class = 'airpoints-tile-amount']");

        if ($expBalance > 0 && strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
            // Airpoints Dollar Expiry
            $this->SetProperty('ExpiringBalance', $expBalance);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($this->Properties['Number']) && !empty($this->Properties['Name'])
            && ($this->http->FindPreg("/connection closed or chunked data/ims")
                || $this->http->FindPreg("/\[curl\]\s*18\:\s*transfer closed with/ims"))) {
            $this->SetBalance(0);
        }
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL("https://{$this->host}/vloyalty/action/mybookings");
        // no Itineraries
        if ($this->http->FindSingleNode("//p[contains(text(), 'There are no bookings currently associated with your Airpoints number')]")) {
            $this->sendNotification('no its // MI');

            return $this->noItinerariesArr();
        }

        if (empty($this->http->FindSingleNode("//div[@class='booking-date']", null, false))
            && $this->http->FindSingleNode("//div[@class='missing-booking-container missing-booking-no-bookings']//p[contains(text(), 'Enter a flight booking reference, the details of your flight, or a hotel itinerary number to add it to My Bookings.')]")) {
            return $this->noItinerariesArr();
        }

        $trips = $this->http->FindNodes("//ol[@class = 'booking-tiles']/li//div[@class = 'pnr']/p");
        $trips = array_merge(
            $this->http->FindNodes("//ol[@class = 'booking-tiles']/li//div[@class = 'identifier']/p"), $trips);
        $this->logger->debug("Total bookings found: " . count($trips));
        $this->logger->info('bookings:');
        $this->logger->info(var_export($trips, true));

        // fix for hotels
        $bookingListData = $this->http->JsonLog($this->http->FindPreg("/BookingListData\s*=\s*(.+\}\});\s*CustomerData/ims"), 0);
        $bookingGroups = $bookingListData->data->bookingGroups ?? [];
        $hotelsNumbers = [];

        foreach ($bookingGroups as $bookingGroup) {
            foreach ($bookingGroup->bookings as $booking) {
                $this->logger->debug("#{$booking->identifier}: {$booking->bookingType}");

                if ($booking->bookingType == 'HOTEL') {
                    $hotelsNumbers[$booking->identifier] = $booking;
                }// if ($booking->bookingType == 'HOTEL')
            }// foreach ($bookingGroup->bookings as $booking)
        }// foreach ($bookingGroups as $bookingGroup)
        $this->logger->info("Hotels: " . var_export($hotelsNumbers, true), ['pre' => true]);

        foreach ($trips as $pnrRef) {
            $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$pnrRef}", ['Header' => 3]);
            $this->currentItin++;
            $parsedIts = count($this->itinerariesMaster->getItineraries());

            // hotels have no details
            if (isset($hotelsNumbers[$pnrRef])) {
                $this->ParseHotel($hotelsNumbers[$pnrRef]);

                continue;
            }

            $this->http->PostURL("https://{$this->host}/vloyalty/action/getbookingdetail", ["pnrRef" => $pnrRef]);
            $bookingList = $this->http->JsonLog(null, 2);

            if (!$bookingList) {
                $redirect = $this->jsRedirect();

                if ($this->http->FindPreg('/getbookingdetail/', false, $redirect)) {
                    $this->http->PostURL("https://{$this->host}/vloyalty/action/getbookingdetail", ["pnrRef" => $pnrRef]);
                    $bookingList = $this->http->JsonLog(null, 0);
                }
            }

            if ($bookingList) {
                $booking = $bookingList[0]->data ?? [];

                if ($booking) {
                    $this->ParseItinerary($booking);

                    if (count($this->itinerariesMaster->getItineraries()) > $parsedIts) {
                        continue;
                    }
                }
            }

            $this->logger->error("getbookingdetail failed, trying retrieve");

            if ($this->familyName) {
                $arFields = [
                    'ConfNo'     => $pnrRef,
                    'FamilyName' => $this->familyName,
                ];
                $itin = [];
                $itinError = $this->CheckConfirmationNumberInternal($arFields, $itin);

                if ($itinError) {
                    $this->logger->error($itinError);
                }

                if (count($this->itinerariesMaster->getItineraries()) > $parsedIts && !$itinError) {
                    continue;
                }
            }

            $this->logger->error("getbookingdetail failed, trying from the list");
            $this->ParseFlights([$pnrRef], $bookingGroups);
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 20,
                "Cols"     => 7,
                "Required" => true,
            ],
            "FamilyName"    => [
                "Type"     => "string",
                "Caption"  => "Family Name",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://flightbookings.airnewzealand.co.nz/vmanage/actions/managebookingstart";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));

        /*if ($this->http->Response['code'] != 200) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }*/
        $this->http->GetURL("https://flightbookings.airnewzealand.com.au/vmanage/actions/retrieve/manageBooking?pnr={$arFields['ConfNo']}&surname={$arFields['FamilyName']}&vmanageredirected=true&sourcesystem=UNKNOWN");

        if (!$this->http->FindPreg('#/actions/managebooking#', false, $this->http->currentUrl())) {
            sleep(1);
            $this->http->GetURL("https://flightbookings.airnewzealand.com.au/vmanage/actions/retrieve/manageBooking?pnr={$arFields['ConfNo']}&surname={$arFields['FamilyName']}&vmanageredirected=true&sourcesystem=UNKNOWN");

            if (!$this->http->FindPreg('#/actions/managebooking#', false, $this->http->currentUrl())) {
                sleep(1);
                $this->http->GetURL("https://flightbookings.airnewzealand.com.au/vmanage/actions/retrieve/manageBooking?pnr={$arFields['ConfNo']}&surname={$arFields['FamilyName']}&vmanageredirected=true&sourcesystem=UNKNOWN");
            }
        }

        ///$this->http->GetURL("https://flightbookings.airnewzealand.co.nz/vmanage/actions/retrieve/manageBooking?pnr={$arFields['ConfNo']}&surname={$arFields['FamilyName']}");

        if ($this->http->XPath->query("//div[contains(@class,'flight-panel')]")->length > 0) {
            $this->ParseItineraryByConfNo($arFields['ConfNo']);

            if ($this->http->FindSingleNode("//a[contains(.,'Manage hotel booking')]")) {
                $roots = $this->http->XPath->query("//h4[normalize-space()='Hotels']/ancestor::div[1]//div[starts-with(normalize-space(),'Reservation code')]");
                $email = null;
                // uncomment when expedia->objects
//                $email = $this->http->FindSingleNode("//div[normalize-space()='Email address']/following-sibling::span");
                $arFields = [];

                foreach ($roots as $root) {
                    $confNo = $this->http->FindSingleNode("./b", $root);

                    if (!empty($email)) {
                        $arFields[] = ['ConfNo' => $confNo, 'Email' => $email];
                    } else {
                        $h = $this->itinerariesMaster->add()->hotel();
                        $h->general()->confirmation($confNo, 'Reservation code');

                        $h->hotel()
                            ->name($this->http->FindSingleNode("./following-sibling::p[1]", $root, false, "/Hotel:\s*(.+)/"))
                            ->noAddress();
                        $h->booked()
                            ->checkIn(strtotime($this->http->FindSingleNode("./following-sibling::p[2]", $root, false, "/Check-in:\s*(.+)/")))
                            ->checkOut(strtotime($this->http->FindSingleNode("./following-sibling::p[3]", $root, false, "/Check-out:\s*(.+)/")));
                    }
                }
            }
        } else {
            $data = $this->http->FindPreg("/VUI.pageInit\(([\s\S]+?)\);\s*<\/script>/");
            $dataJson = $this->http->JsonLog($data, 3, true);

            if (!empty($dataJson)) {
                if (
                    !is_array($dataJson)
                    || count($dataJson) > 1
                    || (
                        count($dataJson) === 1
                        && (
                            !isset($dataJson[0]['data'])
                            || !isset($dataJson[0]['name'])
                            || !in_array($dataJson[0]['name'], ['ScheduleChange', 'FlightItineraryInfo'])
                        )
                    )
                ) {
                    if ($this->http->currentUrl() === 'https://flightbookings.airnewzealand.co.nz/vmanage/actions/managebookingfeedback'
                        && $msg = $this->http->FindSingleNode("//title[starts-with(normalize-space(),'Your booking cannot be managed online')]")) {
                        return $msg;
                    }

                    $this->sendNotification("check json retrieve // MI");

                    return null;
                }
                $this->ParseItineraryChangeByConfNoJson($dataJson[0]['data']);
            } else {
                if ($message = $this->http->FindPreg('/"validationMessages":\{"fields":\[\{"field":"pnr","message":"(.*?booking reference.*?)"\}\]/')) {
                    return "Sorry we can't find a match for this booking. Please check that your booking reference and the family name is the one you used when booking the flight.";
                }
            }
        }

        return null;
    }

    // Redirect to the Air New Zealand site that matches account country of residence.
    private function redirectToRightCountrySite()
    {
        $this->logger->notice(__METHOD__);

        // Redirect to the Air New Zealand site that matches account country of residence.
        $switchToRightHost = false;

        if ($this->http->FindSingleNode("//p[contains(text(), 'You are being redirected to')]")
            && ($link = $this->http->FindSingleNode("//a[contains(text(), 'Continue')]/@href"))) {
            $this->logger->notice("Redirect to the Air New Zealand site that matches account country of residence.");
            $this->http->GetURL($link);

            // fixes for prod version
            $domain = $this->http->FindPreg('/^https:\/\/auth\.(.+?)\/vauth\/oauth2\/authorize/', false, $this->http->currentUrl());

            if ($domain != $this->domain && $this->http->ParseForm('login')) {
                $this->logger->info("set New domain {$domain}", ['Header' => 3]);
                $this->logger->notice("set New domain {$domain}");

                if (strstr($this->http->currentUrl(), 'client_id=www.en.airnewzealand-ar.com_loyalty')) {
                    $domain = 'airnewzealand.co.nz';
                    $this->logger->notice("fixed New domain {$domain}");
                }

                $this->domain = $domain;
                $this->host = "www.{$this->domain}";
                $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances");
                // fix cookie
                $host = $this->host;

                if ($this->host === "www.pacificislands.airnewzealand.com") {
                    $host = "www.airnewzealand.co.nz";
                }
                // LastLoginTime
                $this->http->setCookie("LastLoginTime", date('UB', strtotime('-1 day')), str_replace("www.", "auth.", $host), "/vauth", null, true);

                if ($this->http->ParseForm('login')) {
                    $this->http->SetInputValue("xv_username", $this->AccountFields['Login']);
                    $this->http->SetInputValue("xv_password", $this->AccountFields['Pass']);
                    $this->http->SetInputValue("xv_rememberme", "on");
                    $this->http->SetInputValue("password", "");

                    if (!$this->http->PostForm()) {
                        return $this->checkErrors();
                    }

                    $this->brokenRememberMeWorkaround();

                    if ($this->parseQuestion()) {
                        return false;
                    }

                    if ($this->needsCaptcha()) {
                        $captcha = $this->parseReCaptcha();

                        if ($captcha === false) {
                            return false;
                        }
                        $this->http->SetInputValue("g-recaptcha-response", $captcha);
                        $this->http->SetInputValue("canswer", $captcha);
                        $this->http->SetInputValue("xv_username", $this->AccountFields['Login']);
                        $this->http->SetInputValue("xv_password", $this->AccountFields['Pass']);
                        $this->http->SetInputValue("xv_rememberme", "on");
                        $this->http->SetInputValue("password", "");

                        if (!$this->http->PostForm()) {
                            return $this->checkErrors();
                        }
                        $switchToRightHost = true;

                        // it's works for some regions
                        if ($this->http->FindSingleNode("//p[contains(text(), 'You are being redirected to')]")
                            && ($link = $this->http->FindSingleNode("//a[contains(text(), 'Continue')]/@href"))) {
                            $this->logger->notice("Redirect to the Air New Zealand site that matches account country of residence.");
                            $this->http->GetURL($link);
                        }
                    }// if ($this->needsCaptcha())
                }// if ($this->http->ParseForm('login'))
            }// if ($domain != $this->domain && $this->http->ParseForm('login'))
        }

        if ($this->loginSuccessful()) {
            if ($switchToRightHost) {
                $this->State['Host'] = $this->host;
            }

            return true;
        }

        return false;
    }

    private function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'NewZealand';
        }

        return $region;
    }

    private function setRegionSettings()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2'] ?? null);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        // Identification host
        if (!empty($this->AccountFields['Login2'])) {
            // http://www.airnewzealand.eu/gateway
            switch ($this->AccountFields['Login2']) {
                case 'Australia':
                    $this->host = 'www.airnewzealand.com.au';

                    break;

                case 'Canada':
                    $this->host = 'www.airnewzealand.ca';

                    break;

                case 'China':
                    $this->host = 'www.airnewzealand.com.cn';

                    break;
                // program is not supported for this region now
//                case 'Deutschland':
//                    $this->host = 'www.airnewzealand.de';
//                    break;
//                case 'France':
//                    $this->host = 'www.airnewzealand.fr';
//                    break;
                case 'HongKong':
                    $this->host = 'www.airnewzealand.com.hk';

                    break;

                case 'Japan':
                    $this->host = 'www.airnewzealand.co.jp';

                    break;

                case 'PacificIslands':
                    $this->host = 'www.pacificislands.airnewzealand.com';

                    break;
//                case 'FrenchPolynesia':
//                    $this->host = 'www.airnewzealand.pf';
//                    break;
                case 'UK':
                    $this->host = 'www.airnewzealand.co.uk';

                    break;

                case 'USA':
                    $this->host = 'www.airnewzealand.com';

                    break;

                default:
                    $this->host = 'www.airnewzealand.co.nz';
            }

            if (isset($this->State['Host'])) {
                $this->logger->notice('Get Region from State (Login2: ' . $this->AccountFields['Login2'] . ') => ' . $this->State['Host']);
                $this->host = $this->State['Host'];
            }

            $this->domain = $this->http->FindPreg('/www\.(.+)$/', false, $this->host);
        }// if (!empty($this->AccountFields['Login2']))
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->jsRedirect()) {
            $this->http->GetURL("https://{$this->host}/vloyalty/action/mybalances");
        }

        if (
            $this->http->FindPreg("/Airpoints no\.|Airpoints number/ims")
            || $this->http->currentUrl() == "https://{$this->host}/airpoints-account/airpoints/member/dashboard"
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg('/(?:Operation timed out after|Network error 56 - Received HTTP code 503 from proxy after CONNECT|Network error 56 - Proxy CONNECT aborted)/', false, $this->http->Error)
        ) {
            throw new CheckRetryNeededException(3, 1);
        }

        // Website temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h1[
                contains(text(), 'Website temporarily unavailable')
                or contains(text(), 'Website Temporarily Unavailable')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The Air New Zealand website is temporarily unavailable.
        if ($this->http->FindSingleNode("//p[contains(text(), 'The Air New Zealand website is temporarily unavailable.')]")
            || $this->http->FindPreg("/The Air\&nbsp;New\&nbsp;Zealand website is temporarily unavailable\./ims")) {
            throw new CheckException("The Air New Zealand website is temporarily unavailable.", ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently performing planned maintenance to our website')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The system is currently not available.
        if ($message = $this->http->FindPreg("/(The system is currently not available\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginStubJs()
    {
        $this->logger->notice("update tokens");
        $this->logger->debug('url: ' . $this->http->currentUrl());

        $stateId = '';

        if ($paramsQuery = parse_url($this->http->currentUrl(), PHP_URL_FRAGMENT)) {
            foreach (explode('&', $paramsQuery) as $param) {
                $temp = explode('=', $param);

                if ($temp[0] == 'state') {
                    $stateId = urldecode($temp[1]);
                }
            }
        }

        $this->logger->debug('state: ' . $stateId);

        $this->http->setCookie('vly_client_id', $this->host . '_loyalty', null, '/vloyalty');
        $domain = $this->fixedDomain();
        $this->http->setCookie('vly_current_session', "https://auth.{$domain}/vauth/oauth2/currentsession", null, '/vloyalty');
        $this->http->setCookie('vly_link_session', '/vloyalty/api/sessionlink', null, '/vloyalty');

        $this->http->GetURL("https://auth.{$domain}/vauth/oauth2/currentsession?client_id={$this->host}_loyalty");
        $response = $this->http->JsonLog();

        if (isset($response->id_token, $response->access_token)) {
            $this->http->setCookie('vly_jwt', $response->id_token, $this->host, '/vloyalty');
            $this->http->setCookie('vly_atok', $response->access_token, $this->host, '/vloyalty');
        } else {
            $this->logger->error('Parameter id_token / access_token is not exist');
        }

        $this->http->GetURL("https://{$this->host}/vloyalty/api/sessionlink?stateId={$stateId}");
        $response = $this->http->JsonLog();

        return $response;
    }

    private function fixedDomain()
    {
        $this->logger->notice(__METHOD__);

        return in_array($this->domain, [
            /*
            'airnewzealand.com.cn',
             */
            'pacificislands.airnewzealand.com',
            /*
            'airnewzealand.co.jp',
             */
        ]) ? 'airnewzealand.co.nz' : $this->domain;
    }

    private function jsRedirect(): ?string
    {
        $this->logger->notice(__METHOD__);
        // We hit the JS-stub
        if (stripos($this->http->currentUrl(), '/vloyalty/action/authorize#access_token=') !== false) {
            $response = $this->loginStubJs();

            if (isset($response->redirect)) {
                return $response->redirect;
            }
        }
        // sometimes it's redirecting to main page after auth instead page with token in url
        elseif ($this->http->currentUrl() == "https://{$this->host}/") {
            return true;
        }

        return null;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recaptchaSiteKey: decodeHtml\('([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function needsCaptcha()
    {
        $this->logger->notice(__METHOD__);

        return
            $this->http->FindPreg("/errorMessage\s*=\s*decodeHtml\('Please complete the additional fields to login\.'\)/")
            && $this->http->FindPreg("/recaptcha: true,/")
            && $this->http->ParseForm("login")
        ;
    }

    private function ParseHotel($booking)
    {
        $this->logger->notice(__METHOD__);

        $r = $this->itinerariesMaster->add()->hotel();
        $r->general()
            ->confirmation($booking->identifier);

        $r->hotel()
            ->name($booking->description)
            ->address($booking->hotelCity . (isset($booking->hotelCountry) ? ', ' . $booking->hotelCountry : ''));
        $r->booked()
            ->checkIn(strtotime(preg_replace("/,\s*[^,]+\,/", "", $booking->checkInDateTime)))
            ->checkOut(strtotime(preg_replace("/,\s*[^,]+\,/", "", $booking->checkOutDateTime)));

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function ParseItinerary($booking)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($booking->flightGroups)) {
            $this->logger->error("segments are not found");

            return;
        }

        $pnr = $booking->pnrRef ?? null;

        if (!$pnr) {
            $pnr = $booking->identifier ?? null;
        }

        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($pnr);
        // Additional information
        if (isset($pnr)) {
            $this->http->GetURL("https://{$this->host}/vloyalty/action/managebooking/VIEW_FARE_INFO/FLIGHT/" . $pnr);

            $client_id = $this->http->FindPreg("/client_id:\s*'([^\']+)/");

            if ($client_id) {
                $itineraryURL = $this->http->currentUrl();
                $domain = $this->fixedDomain();
                $this->http->GetURL("https://auth.{$domain}/vauth/oauth2/currentsession?client_id={$client_id}");
                $response = $this->http->JsonLog();
                $id_token = $response->id_token ?? null;

                if ($id_token) {
                    $this->http->GetURL($itineraryURL);
                }
            } else {
                $this->logger->warning("client_id not found");
            }

            $currency = $this->http->FindSingleNode("(//div[@id='cpnl']//span[@class='flr'])[1]");

            if (!empty($currency)) {
                $r->price()
                    ->currency($currency)
                    ->tax($this->http->FindSingleNode("//div[@class='cpnllhp' and contains(text(), 'govt costs')]/preceding-sibling::div[@class='cpnlrhp'][1]/span[@class='rr']",
                        null, true, '/([\d\.,]+)/'))
                    ->total($total = $this->http->FindSingleNode("//span[@id='gtot']", null, true, '/([\d\.,]+)/'));
            }
        }// if (isset($pnr))

        if (!isset($currency, $total)) {
            // Currency
            $currency = $this->http->FindPreg("/\"otherCurrency\":\"([^\"]+)/");
            // TotalCharge
            $otherTotal = $this->http->FindPreg("/\"otherTotal\":\"([^\"]+)/");

            if ($otherTotal) {
                if ($this->AccountFields['Login2'] === 'PacificIslands') {
                    $total = PriceHelper::cost($otherTotal, ' ', '.');
                } else {
                    $total = PriceHelper::cost($otherTotal);
                }

                if ($total === null) {
                    $this->sendNotification('check total // MI');
                } else {
                    $r->price()
                        ->currency($currency)
                        ->total($total);
                }
            }
        }
        $airpointsStr = $this->http->FindPreg('/"airpointsTotal":"(.+?)"/');

        if ($airpointsStr) {
            $airpoints = PriceHelper::cost($airpointsStr);
            $r->price()->spentAwards($airpoints ? "{$airpoints} airpoints" : null, false, true);
        }

        $passengers = [];

        foreach ($booking->flightGroups as $flights) {
            foreach ($flights->flights as $flight) {
                $s = $r->addSegment();

                $airlineName = $flight->brandingCode ?? null;

                if (empty($airlineName) && isset($flight->flightNumber)) {
                    $airlineName = $this->http->FindPreg("/^([A-Z\d]{2})\d+/", false, $flight->flightNumber);
                }

                $flightNumber = (isset($flight->flightNumber)) ?
                    $this->http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?(\d+)/", false, $flight->flightNumber) : null;

                $operator = $flight->operatorDesc ?? '';

                if (strstr($operator, 'Air&nbsp;New&nbsp;Zealand Link')) {
                    $operator = $this->http->FindPreg("/Zealand\s*Link\s+\-\s+(.+)/", false, $operator);
                } else {
                    $operator = null;
                }

                $s->airline()
                    ->name($airlineName)
                    ->number($flightNumber)
                    ->operator($operator, true, true);

                $s->departure()
                    ->code($flight->origin->airportCode ?? null)
                    ->name($flight->origin->airportCityName ?? null)
                    ->date((isset($flight->departureDate, $flight->departureTime)) ? strtotime("{$flight->departureDate} {$flight->departureTime}") : null);

                $s->arrival()
                    ->code($flight->destination->airportCode ?? null)
                    ->name($flight->destination->airportCityName ?? null)
                    ->date((isset($flight->arrivalDate, $flight->arrivalTime)) ? strtotime("{$flight->arrivalDate} {$flight->arrivalTime}") : null);

                $pax = $booking->pax ?? [];
                $this->logger->debug(var_export(['pax' => $pax], true), ["pre" => true]);

                foreach ($pax as $onePax) {
                    $passenger = str_replace('"', '', $onePax);
                    $passengers[] = preg_replace('/\,\s*\d*\s*years?/ims', '', $passenger);
                }

                $seats = explode(',', $flight->seatString ?? null);
                $seats = array_map(function ($str): string {
                    return trim(str_replace(':', '', $str));
                }, $seats);
                $seats = array_filter($seats, function ($s) {
                    return preg_match("/^\d+[A-z]$/", $s);
                });

                if (!empty($seats)) {
                    $s->extra()->seats($seats);
                }
                $s->extra()
                    ->duration($flight->duration ?? null, false, true)
                    ->aircraft($flight->aircraftType ?? null, false, true);

                $fareType = null;

                if (isset($flight->fareType)) {
                    $fareType = strip_tags($flight->fareType);
                }

                if (!in_array($fareType, ['Seat', 'Mixed', 'Seat + Bag', 'flexirefund'])) {
                    $s->extra()->cabin($fareType);
                }

                $flight = $this->http->FindSingleNode('//p[contains(., "' . $flightNumber . '")]/ancestor::div[contains(@class, "flight-panel")]/@data-flight');
                $detailsXpath = '//div[@data-flight = "' . $flight . '" and contains(@class, "flight-detail")]';

                if ($flight && $detailsXpath) {
                    $s->extra()
                        ->bookingCode($this->http->FindSingleNode($detailsXpath . '//div[contains(text(), "Booking class")]/following-sibling::div[1]/p'), false, true)
                        ->meal(implode('; ', array_unique($this->http->FindNodes('//p[contains(., "' . $flightNumber . '")]/ancestor::div[contains(@class, "flight-panel")]//p[@class = "meals"]/text()[contains(., ":")]', null, "/(?:Meal|Refreshments)\s*\:\s*(.+)/"))), true);
                    $s->departure()
                        ->terminal($this->http->FindSingleNode($detailsXpath . '//div[contains(text(), "Departure terminal")]/following-sibling::div[1]/p'), false, true);
                    $s->arrival()
                        ->terminal($this->http->FindSingleNode($detailsXpath . '//div[contains(text(), "Arrival terminal")]/following-sibling::div[1]/p'), false, true);
                }// if ($flight && $detailsXpath)
            }// foreach($flights->flights as $flight)
        }// foreach ($booking->flightGroups as $flights)

        $passengers = array_unique($passengers);

        if (!empty($passengers)) {
            $r->general()->travellers($passengers, true);
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return;
    }

    private function ParseFlights(array $pnrs, $bookingGroups)
    {
        $this->logger->notice(__METHOD__);

        foreach ($bookingGroups as $bookingGroup) {
            foreach ($bookingGroup->bookings as $booking) {
                if (!in_array($booking->identifier, $pnrs)) {
                    continue;
                }

                if ($booking->bookingType == 'HOTEL') {
                    $this->sendNotification("something went wrong. Hotels should not be in the array {$booking->identifier}");

                    continue;
                }
                $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$booking->identifier} (json)", ['Header' => 3]);
                $this->currentItin++;
                $this->http->JsonLog(json_encode($booking), 3);

                $r = $this->itinerariesMaster->add()->flight();
                $r->general()->confirmation($booking->identifier);

                foreach ($booking->flightGroups as $flightGroup) {
                    foreach ($flightGroup->flights as $flight) {
                        $s = $r->addSegment();
                        $s->departure()
                            ->code($flight->origin->airportCode)
                            ->name($flight->origin->airportName)
                            ->date(strtotime($flight->departureTime, strtotime($flight->departureDate)));

                        $s->arrival()
                            ->code($flight->destination->airportCode)
                            ->name($flight->destination->airportName);

                        if (isset($flight->arrivalTime, $flight->arrivalDate)) {
                            $s->arrival()->date(strtotime($flight->arrivalTime, strtotime($flight->arrivalDate)));
                        } else {
                            $s->arrival()->noDate();
                        }

                        $airline = $this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])$/", false,
                            $flight->brandingCode);

                        if (empty($airline)) {
                            $airline = $this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)/", false,
                                $flight->flightNumber);
                        }
                        $s->airline()
                            ->number($this->http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?(\d+)/", false,
                            $flight->flightNumber))
                            ->name($airline);

                        if (isset($flight->fareTypeHelpUrl)) {
                            $fareCode = $this->http->FindPreg("/^\/([^\/]+)$/", false, $flight->fareTypeHelpUrl);

                            if (empty($fareCode)) {
                                $this->sendNotification("new fareTypeHelpUrl: {$flight->fareTypeHelpUrl} //ZM");
                            } else {
                                switch ($fareCode) {
                                    case "economy-experience":
                                        $s->extra()->cabin('Economy Experience');

                                        break;

                                    case "economy-skycouch":
                                        $s->extra()->cabin('Economy Skycouch');

                                        break;

                                    case "long-haul-experience":
                                        $s->extra()->cabin('Economy');

                                        break;

                                    case "long-haul-premium-economy":
                                        $s->extra()->cabin('Premium Economy');

                                        break;

                                    case "long-haul-business-premier":
                                        $s->extra()->cabin('Business Premier');

                                        break;

                                    case "onboard-your-flight":
                                        $this->logger->debug('fareTypeHelpUrl: "/onboard-your-flight"');

                                        break;

                                    default:
                                        $this->sendNotification("new cabin: {$fareCode} //ZM");
                                }
                            }
                        }
                    }
                }
                $this->logger->info('Parsed Itinerary:');
                $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
            }
        }

        return;
    }

    private function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    /** @return TAccountCheckerExpedia */
    private function getExpedia()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->http = $this->http;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
            $this->expedia->globalLogger = $this->globalLogger; // fixed notifications
        }
        $this->expedia->AccountFields = $this->AccountFields;

        return $this->expedia;
    }

    private function ParseItineraryByConfNo($recordLocator): bool
    {
        $this->logger->notice(__METHOD__);

        $r = $this->itinerariesMaster->add()->flight();
        $r->general()
            ->confirmation($recordLocator);
        $r->price()
            ->currency($this->http->FindPreg("/\"otherCurrency\":\"([^\"]+)/"))
            ->total(PriceHelper::cost($this->http->FindPreg("/\"otherTotal\":\"([^\"]+)/")));
        $pax = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes("//div[contains(text(), 'Adult -') or contains(text(), 'Child -')]/b"));

        if (!empty($pax)) {
            $r->general()->travellers($pax, true);
        }

        $segments = $this->http->XPath->query("//div[contains(@class, 'flight-panel')]");
        $this->logger->info("Total {$segments->length} segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            $s = $r->addSegment();
            $node = $segments->item($i);
            $s->airline()
                ->number($this->http->FindSingleNode(".//div[@class = 'item-header']/div[1]/div/div", $node, true,
                    "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])?(\d+)/"));
            $airline = $this->http->FindSingleNode(".//div[@class = 'item-header']/div[1]/div/div", $node, true,
                "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+/");

            if (empty($airline)) {
                $airline = $this->http->FindSingleNode("following-sibling::div[1]//div[contains(text(), 'Operated by')]/following-sibling::div[1]",
                    $node);
            }
            $s->airline()->name($airline);

            // departure
            $depName = (
                $this->http->FindSingleNode(".//div[@class = 'item-header']//p[@class = 'description']/b[1]", $node)
                ?: $this->http->FindSingleNode(".//div[@class = 'item-header']//p[@class = 'description']/strong[1]", $node)
            );
            $s->departure()->name($depName);
            $code = $this->findAirCode($depName);

            if ($this->http->FindPreg("/^([A-Z]{3})$/", false, $code)) {
                $s->departure()->code($code);
            } else {
                $s->departure()->noCode();
            }
            $depTerminal = $this->http->FindSingleNode('.//div[contains(text(), "Departure terminal")]/following-sibling::div[1]', $node)
                ?? $this->http->FindSingleNode('./following-sibling::div[1]//div[contains(text(), "Departure terminal")]/following-sibling::div[1]', $node);
            $s->departure()->terminal($depTerminal, false, true);
            $depTime = $this->http->FindSingleNode(".//div[@class = 'item-header']/div[1]/div//p[@class = 'time']", $node);
            $depDate = $this->http->FindSingleNode("preceding-sibling::h4[1]", $node);
            $depDate = preg_replace("/(\d{4})年(\d{1,2})月(\d{1,2})日.*/u", '$1-$2-$3', $depDate);
            $this->logger->info("DepDate: {$depDate} {$depTime}");

            if ($depTime === 'Flown') {
                $s->departure()->day(strtotime("{$depDate}"));
            } else {
                $s->departure()->date(strtotime("{$depDate} {$depTime}"));
            }

            // arrival
            $arrName = (
                $this->http->FindSingleNode(".//div[@class = 'item-header']//p[@class = 'description']/b[2]", $node)
                ?: $this->http->FindSingleNode(".//div[@class = 'item-header']//p[@class = 'description']/strong[2]", $node)
            );
            $s->arrival()->name($arrName);
            $code = $this->findAirCode($arrName);

            if ($this->http->FindPreg("/^([A-Z]{3})$/", false, $code)) {
                $s->arrival()->code($code);
            } else {
                $s->arrival()->noCode();
            }
            $arrTerminal = $this->http->FindSingleNode('.//div[contains(text(), "Arrival terminal")]/following-sibling::div[1]', $node) ??
                $this->http->FindSingleNode('./following-sibling::div[1]//div[contains(text(), "Arrival terminal")]/following-sibling::div[1]', $node);
            $s->arrival()->terminal($arrTerminal, false, true);
            // 到达: 2023年8月4日 周五, 05:45
            $arrDate = $this->http->FindSingleNode(".//div[@class = 'item-header']//div[@class = 'arrivaldate']", $node,
                true, "/:\s*([^<]+)/i");
            $arrDate = preg_replace("/(\d{4})年(\d{1,2})月(\d{1,2})日.*/u", '$1-$2-$3', $arrDate);
            $this->logger->info("ArrDate: {$arrDate}");
            $s->arrival()->date(strtotime($arrDate));

            // seats
            $seats = $this->http->FindSingleNode(".//div[@class = 'item-header']//p[@class = 'seats']", $node, false,
                "/Seats:\s*([^<]+)/ims");

            if (!empty($seats)) {
                $s->extra()->seats(array_filter(explode(',', $seats)));
            }
            // meals
            $mealNodes = $this->http->FindNodes(".//div[@class = 'item-header']//p[@class = 'meals']", $node);

            if (count($mealNodes) == 1) {
                $mealNodes = $this->http->FindNodes(".//div[@class = 'item-header']//p[@class = 'meals']/text()", $node);
            } else {
                $this->sendNotification('check meals // MI');
            }
            $meals = array_map(function ($mealNode) {
                return $this->http->FindPreg("/Refreshments:?\s*([^<]+)/ims", false, $mealNode);
            }, $mealNodes);
            $meals = array_values(array_filter($meals));

            if (empty($meals)) {
                $meals = $this->http->FindNodes(".//div[@class = 'item-header']//p[contains(., 'Refreshments')]", $node,
                    "/Refreshments:?\s*(.+)/");
            }

            if (!empty($meals)) {
                $meals = array_unique($meals);
                $s->extra()->meal(implode("|", $meals));
            }
            // extra
            $s->extra()
                ->aircraft($this->http->FindSingleNode("following-sibling::div[1]//div[contains(text(), 'Aircraft type')]/following-sibling::div[1]", $node), true, true)
                ->duration($this->http->FindSingleNode("following-sibling::div[1]//div[contains(text(), 'Flight duration')]/following-sibling::div[1]", $node), false, true)
                ->bookingCode($this->http->FindSingleNode("following-sibling::div[1]//div[contains(text(), 'Booking class')]/following-sibling::div[1]", $node), false, true);
        } // for ($i = 0; $i < $segments->length; $i++)

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return true;
    }

    private function ParseItineraryChangeByConfNoJson($data)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();

        $r->general()
            ->confirmation($data['bookingReference'])
            ->status('Revised');

        $flightsCnt = count($data['flights']);
        $this->logger->info("Total {$flightsCnt} segments were found");

        foreach ($data['flights'] as $flight) {
            if (!in_array($flight['type'], ['revised', ''])) {
                if (!in_array($flight['type'], ['transferred'])) {
                    $this->sendNotification('check type: segments change //ZM');
                }

                continue;
            }
            $s = $r->addSegment();
            $s->airline()
                ->name($this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\d+$/", false, $flight['flightNumber']))
                ->number($this->http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])(\d+)$/", false,
                    $flight['flightNumber']));
            $s->departure()
                ->name($flight['departureAirportName'])
                ->date(strtotime($flight['departureDateTimeLocal']));
            $code = $this->findAirCode($flight['departureAirportName']);

            if ($this->http->FindPreg("/^([A-Z]{3})$/", false, $code)) {
                $s->departure()->code($code);
            } else {
                $s->departure()->noCode();
            }
            $s->arrival()
                ->name($flight['arrivalAirportName'])
                ->date(strtotime($flight['arrivalDateTimeLocal']));
            $code = $this->findAirCode($flight['arrivalAirportName']);

            if ($this->http->FindPreg("/^([A-Z]{3})$/", false, $code)) {
                $s->arrival()->code($code);
            } else {
                $s->arrival()->noCode();
            }
        } // foreach ($data['flights'] as $flight)

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function getAirCodes()
    {
        $this->logger->notice(__METHOD__);
        $cache = Cache::getInstance()->get('airnewzealand_aircodes');

        if ($cache !== false && count($cache) > 1) {
            $this->AirCodes = $cache;

            return $cache;
        } else {
            $browser = clone $this->http;
            $browser->TimeLimit = 15;
            $browser->GetURL("http://www.airnewzealand.co.nz/arrivals-and-departures");
            $nodes = $browser->XPath->query("//select[@name = 'airport']/option[string-length(@value) > 0]");
            $browser->Log("Total codes " . $nodes->length);
            $codes = [];

            for ($n = 0; $n < $nodes->length; $n++) {
                $city = Html::cleanXmlValue($nodes->item($n)->nodeValue);
                $code = Html::cleanXmlValue($nodes->item($n)->getAttribute("value"));

                if ($city != "" && $code != "") {
                    $codes[$city] = $code;
                }
            }
            Cache::getInstance()->set('airnewzealand_aircodes', $codes, 86400);
            $this->AirCodes = $codes;

            return $codes;
        }
    }

    private function findAirCode($name)
    {
        $this->logger->info("findAirCode -> {$name}");
        $code = null;

        if (empty($name)) {
            return $code;
        }

        if (!isset($this->AirCodes[$name])) {
            $airport = $this->db->getAirportBy(['AirName' => $name]);

            if ($airport !== false) {
                $code = $airport['AirCode'];
            }
            $this->logger->info("Lookup { DepCode: {$code} }");

            if (empty($code)) {
                $airport = $this->db->getAirportBy(['AirName' => $name], true);

                if ($airport !== false) {
                    $code = $airport['AirCode'];
                }
                $this->logger->info("LookupBy { DepCode LIKE: " . $code . " }");
            }// if (empty($code))

            if (!empty($code)) {
                $this->AirCodes[$name] = $code;
            }
        }

        if (isset($this->AirCodes[$name])) {
            return $this->AirCodes[$name];
        }

        if ($name == 'Osaka Itami') {
            return $this->findAirCode('Itami Airport');
        }

        if ($name == 'Sapporo Chitose') {
            return $this->findAirCode('Chitose Airport');
        }

        return null;
    }

    private function brokenRememberMeWorkaround()
    {
        $this->logger->notice(__METHOD__);

        // provider bug fix, 'remember me' breaks auth
        if (
            !$this->needsCaptcha()
            && strstr($this->http->currentUrl(), 'xvauth2_notify_not_authenticated=false&client_id=www.airnewzealand')
        ) {
            if (!$this->http->ParseForm("login")) {
                return false;
            }

            $this->http->SetInputValue("xv_username", $this->AccountFields['Login']);
            $this->http->SetInputValue("xv_password", $this->AccountFields['Pass']);
//            $this->http->SetInputValue("xv_rememberme", "on");
            $this->http->SetInputValue("password", "");

            $this->http->RetryCount = 0;
            $maxRedirects = $this->http->getMaxRedirects();
            $this->http->setMaxRedirects($maxRedirects * 3);
            $success = $this->http->PostForm([
                'Origin'       => 'https://auth.airnewzealand.com',
                'Referer'      => 'https://auth.airnewzealand.com/',
            ]);
            $this->http->setMaxRedirects($maxRedirects);

            if (!$success) {
                return $this->checkErrors();
            }
            $this->http->RetryCount = 2;
        }

        return true;
    }
}
