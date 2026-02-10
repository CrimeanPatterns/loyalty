<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBwwblazin extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_SUCCESS = '//button[@data-gtm-id="accountLogout"] | //span[contains(@class, "pointsCounter_pointsAmount")]';
    private const XPATH_ERROR = '//div[contains(@class, "inputError___")] | //span[contains(@class, "errorText___")]';

    private $client_id = "mLLAi6nx8PX5OykSkTBG79aw5SkfIdKG";
    private $auth0Client = "eyJuYW1lIjoiYXV0aDAtcmVhY3QiLCJ2ZXJzaW9uIjoiMS41LjAifQ%3D%3D";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->RetryCount = 0;

        $this->UseSelenium();
        $this->setProxyGoProxies();
        $this->useFirefoxPlaywright();
        $this->http->saveScreenshots = true;
    }

    /*
    public function IsLoggedIn()
    {
        unset($this->State['clientId']);
        unset($this->State['clientSecret']);
        unset($this->State['idToken']);

        if (!isset($this->State['Authorization'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->driver->manage()->window()->maximize();
        $this->http->GetURL("https://www.buffalowildwings.com/account/rewards/");
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "email"]'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "SIGN IN")]'), 0);

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        /*
        $this->http->GetURL("https://login.buffalowildwings.com/authorize?client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.buffalowildwings.com%2Fcallback&scope=openid%20offline_access&response_type=code&response_mode=query&state=SnhlQi1NQi44cHRiN1RwaWVpR1p5UWM5eWRQOHA0OXZoZkNyN3VwRUhnRw%3D%3D&nonce=QXJsWC5ON2xtLnRhVkFneFlRTV9SQ2lLQ2p3U0ZjOHZ2VFc1OFlTOGFjcw%3D%3D&code_challenge=BIFXp9ipOdJIsAs7YZCFktvCybtlxhh2AivNNMpFrJU&code_challenge_method=S256&auth0Client={$this->auth0Client}");
        $currentUrl = $this->http->currentUrl();
        $client_id = $this->http->FindPreg("/client=([^&]+)/", false, $currentUrl);
        $state = $this->http->FindPreg("/state=([^&]+)/", false, $currentUrl);
        $scope = $this->http->FindPreg("/scope=([^&]+)/", false, $currentUrl);

        if (!$client_id || !$state || !$scope) {
            return false;
        }

        // POST https://login.buffalowildwings.com/usernamepassword/challenge
//        $captcha = $this->parseCaptcha("6LfvxroiAAAAAA24osu3Psx1Ssjcx3oPMlK4H-aJ");
//
//        if ($captcha === false) {
//            return false;
//        }

        $data = [
            //            "captcha"       => $captcha,
            "client_id"     => $client_id,
            "redirect_uri"  => "https://www.buffalowildwings.com/callback",
            "tenant"        => "bww-prd01",
            "response_type" => "code",
            "scope"         => $scope, // "openid profile email read:identity offline_access"
            "_csrf"         => $this->http->getCookieByName("_csrf", "login.buffalowildwings.com", "/usernamepassword/login"),
            "state"         => $state,
            "_intstate"     => "deprecated",
            "nonce"         => "QXJsWC5ON2xtLnRhVkFneFlRTV9SQ2lLQ2p3U0ZjOHZ2VFc1OFlTOGFjcw==",
            "password"      => $this->AccountFields['Pass'],
            "connection"    => "firebase-auth",
            "username"      => $this->AccountFields['Login'],
        ];
        $headers = [
            'Accept'          => '*
        /*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTYuNCJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
            'newrelic'        => 'eyJ2IjpbMCwxXSwiZCI6eyJ0eSI6IkJyb3dzZXIiLCJhYyI6IjI4NzkyOTAiLCJhcCI6IjEzODU4Nzk2NzIiLCJpZCI6ImYxZTRhZDc0ZTc4ZGRlMjUiLCJ0ciI6ImE0ZjZmMmVmMmY5Y2Q1ZjY0Njk5YWFlYjllMGQxZGQwIiwidGkiOjE2NzU1NDM5MzE3NjB9fQ==',
            'traceparent'     => '00-a4f6f2ef2f9cd5f64699aaeb9e0d1dd0-f1e4ad74e78dde25-01',
            'tracestate'      => '2879290@nr=0-1-2879290-1385879672-f1e4ad74e78dde25----' . date("UB"),
        ];
        $this->http->PostURL('https://login.buffalowildwings.com/usernamepassword/login', json_encode($data), $headers);

        if ($this->http->Response['code'] == 403) {
            unset($headers['traceparent']);
            unset($headers['tracestate']);
            $this->http->PostURL('https://login.buffalowildwings.com/usernamepassword/login', json_encode($data), $headers);
        }
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | //button[@aria-label="Close Icon"] | ' . self::XPATH_SUCCESS . ' | //button[contains(text(), "Send code")]'), 10);
        $this->saveResponse();

        if ($closeBtn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Close Icon"]'), 0)) {
            $closeBtn->click();
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | //button[contains(text(), "Send code")] | ' . self::XPATH_SUCCESS), 5);
            $this->saveResponse();
        }

        if ($acceptAllCookieButton = $this->waitForElement(WebDriverBy::xpath('//button[@id = "acceptAllCookieButton"]'), 0)) {
            $acceptAllCookieButton->click();
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR . ' | //button[contains(text(), "Send code")] | ' . self::XPATH_SUCCESS), 5);
            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 0, false)) {
            return true;
        }

        if ($sendCode = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Send code")]'), 0)) {
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $sendCode->click();
            $question = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "We\'ve sent an SMS to")]'), 10);
            $this->saveResponse();

            if ($question) {
                $this->holdSession();
                $this->AskQuestion($question->getText(), null, "Question");
            }

            return false;
        }

        /*
        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        $code = $this->http->FindPreg("/\?code=([^&]+)/", false, $this->http->currentUrl());

        if ($code) {
            $data = [
                "client_id"     => $this->client_id,
                "code_verifier" => "1Mi6k8QRJJOKPMpclA6Jxsv~nEuRSY5~lm.cYXiDTwA",
                "grant_type"    => "authorization_code",
                "code"          => $code,
                "redirect_uri"  => "https://www.buffalowildwings.com/callback",
            ];
            $headers = [
                'Accept'       => '*
        /*',
                'Content-Type' => 'application/json',
            ];
            $this->http->PostURL("https://login.buffalowildwings.com/oauth/token", json_encode($data), $headers);
            $response = $this->http->JsonLog();

            if (isset($response->id_token)) {
                $this->State['Authorization'] = "Bearer {$response->id_token}";

                return $this->loginSuccessful();
            }

            return false;
        }

        if ($session_token = $this->http->FindPreg("/multi-factor-authentication\/\?session_token=([^&]+)/", false, $this->http->currentUrl())) {
            $phoneNumber = $this->http->FindPreg('/"phoneNumber":"(.+?)"/', false, base64_decode(explode('.', $session_token)[1] ?? null));

            if (!$phoneNumber) {
                return false;
            }

            $this->State['headers'] = [
                'Accept'        => '*
        /*',
                'Content-Type'  => 'application/json',
                'Authorization' => "Bearer {$session_token}",
            ];
            $data = [
                "phoneNumber" => [
                    "countryCode" => "1",
                ],
                "type" => "SMS",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://api-idp.buffalowildwings.com/bww/web-exp-api/v1/notification/auth-mfa-send-otp", json_encode($data), $this->State['headers']);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (isset($response->status) && $response->status == 'SUCCESS') {
                // We've sent an SMS to: (***) ***-2408
                $this->Question = "We've sent an SMS to: {$phoneNumber}";
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";
            }

            return false;
        }
        */

        $response = $this->http->JsonLog();
        $message =
            $this->http->FindSingleNode(self::XPATH_ERROR)
            ?? $response->description
            ?? $response->code
            ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Wrong Username Or Password'
                || $message == 'Wrong email or password.'
                || strstr($message, 'This login attempt has been blocked because the password you\'re using was previously disclosed through a data breach (not in this application)')
                || strstr($message, 'We apologize. Our system was unable to verify your email and/or password.')
            ) {
                throw new CheckException("We apologize. Our system was unable to verify your email and/or password. Please try again, or retrieve your forgotten password or setup a new account.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Invalid captcha value') {
                throw new CheckRetryNeededException();
            }

            if (strstr($message, 'We apologize, our system is unavailable at the moment.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'user_exists') {
                throw new CheckException('This email account is already in use by another user. Please specify different one.', ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Your account has been blocked after multiple consecutive login attempts.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $otpCodeInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "otpCode"]'), 5);
        $this->saveResponse();

        if (!$otpCodeInput) {
            return false;
        }

        $otpCodeInput->sendKeys($answer);

        $verify = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "VERIFY")]'), 3);
        $this->saveResponse();

        if (!$verify) {
            return false;
        }

        $verify->click();

        $this->waitForElement(WebDriverBy::xpath(self::XPATH_ERROR), 10);
        $this->saveResponse();
//        $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 10, false);

        /*
        $data = [
            "phoneNumber" => [
                "countryCode" => "1",
            ],
            "otp"         => $answer,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api-idp.buffalowildwings.com/bww/web-exp-api/v1/notification/auth-mfa-verify-otp", json_encode($data), $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        */

        // TODO
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCCESS), 10, false);
        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        // Balance - POINTS
        $this->SetBalance($this->http->FindSingleNode('//span[contains(@class, "pointsCounter_pointsAmount__")]'));
        // Points expire
        $exp = $this->http->FindSingleNode('//span[contains(text(), "Points expire:")]', null, true, "/expire:\s*([^<]+)/");

        if ($exp) {
            // Expiring Balance
            $this->SetExpirationDate(strtotime($exp));
        }

        // Reward Certificates
        $offers = $this->http->XPath->query('//div[contains(@class, "rewardItem_itemContainer")]');
        $this->logger->debug("Total {$offers->length} offers were found");

        foreach ($offers as $offer) {
            $expDate = $this->http->FindSingleNode('.//span[contains(@class, "rewardItem_itemDate")]', $offer, true, "/-([\d\/]+)$/");

            if (!($expDate && strtotime($expDate) > strtotime('-1 day', strtotime('now')))) {
                continue;
            }

            $displayName = $this->http->FindSingleNode('.//span[contains(@class, "rewardItem_itemTitle__")]', $offer);

            $this->AddSubAccount([
                "Code"           => md5($displayName),
                "DisplayName"    => $displayName,
                "ExpirationDate" => strtotime($expDate),
                "Balance"        => null,
            ]);
        }

        // Name
        $this->http->GetURL("https://www.buffalowildwings.com/account/");
        $this->waitForElement(WebDriverBy::xpath('//input[@id = "firstName"]'), 10);
        $this->saveResponse();
        $firstName = $this->http->FindSingleNode('//input[@id = "firstName"]/@value');
        $lastName = $this->http->FindSingleNode('//input[@id = "lastName"]/@value');
        $this->SetProperty('Name', beautifulName(trim("{$firstName} {$lastName}")));

        return;

        $response = $this->http->JsonLog(null, 0);
        // Name
        $firstName = $response->firstName ?? null;
        $lastName = $response->lastName ?? null;
        $this->SetProperty('Name', beautifulName(trim("{$firstName} {$lastName}")));

        // Balance
        $this->http->GetURL("https://api-idp.buffalowildwings.com/bww/web-exp-api/v1/customer/account/loyalty?sellingChannel=WEBOA");
        $pointData = $this->http->JsonLog();
        $pointsBalance = $pointData->pointsBalance ?? null;

        if ($pointsBalance === null) {
            return;
        }

        // Balance - POINTS
        $this->SetBalance($pointData->pointsBalance);
        // Points expire
        if (!empty($pointData->pointsExpiring)) {
            // Expiring Balance
            $this->SetProperty('ExpiringBalance', $pointData->pointsExpiring);
            $this->SetExpirationDate(strtotime($pointData->pointsExpiringDate));
        }

        // Reward Certificates
        $this->http->GetURL("https://api-idp.buffalowildwings.com/bww/web-exp-api/v1/customer/account/rewards?sellingChannel=WEBOA");
        $data = $this->http->JsonLog();
        $offers = $data->offers ?? [];

        foreach ($offers as $offer) {
            $status = $offer->status ?? null;

            /*
            if ($status !== 'Active') {
                continue;
            }
            */
            $expDate = $offer->endDateTime;

            if (!($expDate && strtotime($expDate) > strtotime('-1 day', strtotime('now')))) {
                continue;
            }

            $this->AddSubAccount([
                "Code"           => $offer->code,
                "DisplayName"    => $offer->name,
                "ExpirationDate" => strtotime($expDate),
                "Balance"        => (isset($offer->points) && $offer->points !== 0) ?: null,
            ]);
        }
    }

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            //            "domain"     => "recaptcha.net",
            //            "enterprise" => 1,

            "version"   => "v3",
            //            "action" => "bound ",
            "min_score" => 0.9,
        ];

//        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "type"         => "RecaptchaV2EnterpriseTaskProxyless",
//            "websiteURL"   => $this->http->currentUrl(),
//            "websiteKey"   => $key,
        ////            "apiDomain"    => "www.recaptcha.net",
//        ];
//
//        return $this->recognizeAntiCaptcha($recognizer, $parameters);

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://api-idp.buffalowildwings.com/bww/web-exp-api/v1/customer/account", ["Authorization" => $this->State['Authorization']], 20);
        $response = $this->http->JsonLog(null, 1);
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($this->AccountFields['Login']) === strtolower($email)) {
            $this->http->setDefaultHeader("Authorization", $this->State['Authorization']);

            return true;
        }

        return false;
    }
}
