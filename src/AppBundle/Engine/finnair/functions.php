<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFinnair extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.finnair.com/en/my-finnair-plus';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyMount();
        /*
        $this->http->setUserAgent(HttpBrowser::PROXY_USER_AGENT);
        $this->http->setUserAgent("curl/7.88.1"); // "Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)" workaroud
        */
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
        $this->http->removeCookies();
        // $this->http->GetURL("http://www.finnair.com/finnaircom/wps/portal/plus/en_INT");
        $this->http->RetryCount = 0;
        $startTimer = $this->getTime();
        $this->http->GetURL("https://auth.finnair.com/content/en/login/finnair-plus/");

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            return false;
        }

        $this->botCheck();
        $this->getTime($startTimer);
        $this->http->RetryCount = 2;
        $this->checkErrors();

        if ($this->http->Response['code'] != 200) {
            if (
                strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')
                || strstr($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer')
            ) {
                throw new CheckRetryNeededException(4);
            }
            // Access To Website Blocked
            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Access To Website Blocked")]')) {
                $this->DebugInfo = $message;
                $this->ErrorReason = self::ERROR_REASON_BLOCK;

                throw new CheckRetryNeededException(4);
            }

            return $this->checkErrors();
        }

        $key = $this->sendSensorData();

        $data = [
            "client_id"    => "FFfOZx2as6M",
            "redirect_uri" => "https://www.finnair.com/pl/AYPortal/wds/directLoginCAS.action?PAGE=RPLS&COUNTRY_SITE=INT&LANGUAGE=GB&SITE=FINRFINR",
            "lang"         => "en",
        ];
        $this->http->PostURL("https://auth.finnair.com/cas/oauth2.0/authorize?client_id=FFfOZx2as6M&redirect_uri=https%3A%2F%2Fwww.finnair.com%2Fpl%2FAYPortal%2Fwds%2FdirectLoginCAS.action%3FPAGE%3DRPLS%26COUNTRY_SITE%3DINT%26LANGUAGE%3DGB%26SITE%3DFINRFINR&lang=en", $data, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ]);

        // sensor_data issue
        if ($this->http->Response['code'] == 403) {
            $this->sendStatistic(false, false, $key);
//            $this->DebugInfo = 'need to upd sensor_data';

            throw new CheckRetryNeededException();
        }

        $this->sendStatistic(true, false, $key);

        $this->http->GetURL("https://auth.finnair.com/cas/login?service=https%3A%2F%2Fauth.finnair.com%2Fcas%2Foauth2.0%2FcallbackAuthorize%3Fclient_id%3DFFfOZx2as6M%26redirect_uri%3Dhttps%253A%252F%252Fwww.finnair.com%252Fpl%252FAYPortal%252Fwds%252FdirectLoginCAS.action%253FPAGE%253DRPLS%2526COUNTRY_SITE%253DINT%2526LANGUAGE%253DGB%2526SITE%253DFINRFINR%26client_name%3DCasOAuthClient");
        $response = $this->http->JsonLog();

        if (!isset($response->execution)) {
            if ($this->http->Response['code'] == 403 && $this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                throw new CheckRetryNeededException();
            }

            if (isset($response->message) && $response->message == "Loyalty service access is temporarily disabled") {
                throw new CheckException("Service break: some of the Finnair Plus services, including login, are unavailable.", ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }
        $data = [
            "_eventId"     => "submit",
            "execution"    => $response->execution,
            "password"     => $this->AccountFields['Pass'],
            "redirectJson" => "true",
            "rememberMe"   => "true",
            "username"     => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/x-www-form-urlencoded",
            "Origin"       => "https://auth.finnair.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.finnair.com/cas/login", $data, $headers);
        $this->http->RetryCount = 2;

        // sensor_data issue
        if ($this->http->Response['code'] == 403) {
            $this->sendStatistic(false, false, $key);
//            $this->DebugInfo = 'need to upd sensor_data';

            throw new CheckRetryNeededException(2, 5);
        }

//        if (!$this->http->ParseForm("fm1"))
//            return $this->checkErrors();
//        $this->http->SetInputValue("username", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//        $this->http->SetInputValue("_rememberMe", "on");
//
        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindPreg("/(We will begin a system update[^<]+)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "A system error occured. Please try again.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg('/Finnair.com is temporarily unavailable/i')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Internal Server Error
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->route, $response->execution) && in_array($response->route, ["2fa/gauth", "2fa/sms-auth"])) {
            $this->State['execution'] = $response->execution;
            $this->State['route'] = $response->route;

            if ($this->parseQuestion($response->route)) {
                return false;
            }
        }
        $this->http->RetryCount = 1;

        if (isset($response->redirectUrl)) {
            $this->http->GetURL($response->redirectUrl);
        }
        $this->http->RetryCount = 2;

        $this->botCheck();

        if (isset($response->message[0]->text)) {
            $message = $response->message[0]->text;
            $this->logger->error("[Error]: {$message}");

            if ($message == 'LOGIN_FAILED') {
                throw new CheckException("Login failed. Please check the details you provided or try logging in with your membership number.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Redirect
        if ($location = $this->http->FindPreg("/var\s*casUrl\s*=\s*'([^\']+)/")) {
            $this->logger->debug("Redirect to -> {$location}");
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);

            sleep(1);

            if (
                $this->http->Response['code'] == 503
                && $this->http->FindSingleNode('//p[contains(normalize-space(),"The request could not be processed. Please start over or try again later. If the error persists, please contact our customer service. We apologize for the inconvenience.")]')
            ) {
                if ($this->attempt > 1) {
                    $this->sendNotification("check error");

                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(2, 1);
            }
            $this->http->GetURL("https://www.finnair.com/int/gb/plus?section=manage-account");
        }// if ($location = $this->http->FindPreg("/window.location.href\s*=\s*'([^\']+)/"))

        // Access is allowed
        $success = $this->http->getCookieByName("CASTGC", "auth.finnair.com", "/cas", true);
        $this->logger->notice("success -> {$success}");

        if ($success) {
//            https://cdn.finnair.com/finnair-com-ux/prod/assets/js/5e5f29-16-main.js
//            $apiKey = "5mBIAH9Mpzaiu2zcHtN956rX0UYEv3hU8oP7BYb1";
            /*
            $apiKey = $this->http->FindPreg("/'x-api-key'\s*:\s*'([^\']+)/");
            if (!$apiKey) {
                return false;
            }
            */

            if ($this->authComplete()) {
                return true;
            }

            $response = $this->http->JsonLog(null, 0);

            if (
                isset($response->profile->memberStatus)
                && $response->profile->memberStatus === 'UNKNOWN'
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                isset($response->profile->partStatus->code)
                && $response->profile->partStatus->code === 'NOT_FOUND'
            ) {
                $this->throwProfileUpdateMessageException();
            }
        }

        // Login failed. Please check your username (your email or Finnair Plus membership number without the AY prefix and spaces) and password.
        if ($message = $this->http->FindPreg('/"(Login failed\. Please check your username.+?)"/')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Login failed. Please check your username and password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'Login failed. Please check your username')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The membership number or email address you entered is invalid.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The membership number or email address you entered is invalid.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Your Traveller ID / Password combination is not correct.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your Traveller ID / Password combination is not correct.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials.
        if ($message = $this->http->FindPreg("/Invalid credentials\./")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // An error occurred, please try again later.
        if ($message = $this->http->FindPreg('/.show\(\)\.text\(\'(An error occurred, please try again later\.)\'\)\;/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // AccountID: 2954500, 1575109, 1575113, ...
        if ($this->http->FindSingleNode("//div[@id='content']/text()[1]") === 'null') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
        if ($this->parseQuestion())
            return false;
        */

        // A technical error has occured
        if ($message = $this->http->FindSingleNode("//span[@id = 'loginErrorText' and contains(text(), 'A technical error has occured')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // authentication failed, there is no errors (AccountID: 1274682, 282086)
        if (in_array($this->AccountFields['Login'], ['654695998', '645570235'])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $form = $this->http->FindSingleNode("//div[@id = 'site-content']");
        $this->logger->debug("[{$form}]");

        if ($form == 'Please Login Finnair Plus: Your email address or username (membership number without the AY prefix and spaces).Corporate login: Your email address or username for the Finnair Corporate Online website. Email address or username Password Forgot your password? Forgot your username? Keep me logged in Login'
            || $this->http->currentUrl() == 'https://www.finnair.com/int/gb/plus'
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || $this->http->Response['code'] == 302
        ) {
            throw new CheckRetryNeededException(3, 10);
        }

        return $this->checkErrors();
    }

    public function parseQuestion($route)
    {
        $this->logger->notice(__METHOD__);
        /*
         * You have enabled two-factor authentication.
         * Please verify your identity by entering the verification code generated in your authenticator app.
         */
//        $question = $this->http->FindSingleNode("//p[contains(text(), 'You have enabled two-factor authentication.')]");

        if ($route == "2fa/gauth") {
            $question = "Please verify your identity by entering the verification code generated in your authenticator app.";
        } elseif ($route == "2fa/sms-auth") {
            $question = "Please enter the 6-digit verification code that was sent to your phone number by text message.";
        }

        if (!isset($question) /*|| !$this->http->ParseForm("fm1")*/) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        if (!isset($this->State['execution'], $this->State['route'])) {
            return false;
        }
        $data = [
            '_eventId'  => 'submit',
            'token'     => $this->Answers[$this->Question],
            'execution' => $this->State['execution'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://auth.finnair.com/cas/login', $data, [
            'Accept'       => 'application/json, text/plain, */*',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Referer'      => "https://auth.finnair.com/content/en/{$this->State['route']}",
        ]);
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);
        unset($this->State['execution'], $this->State['route']);

        //$error = $this->checkProviderErrors();
        if (
            $this->http->FindPreg('/"text":"Invalid credentials.","severity":"ERROR"/')
            || $this->http->FindPreg('/"text":"authenticationFailure.AuthenticationException"/')
            || $this->http->FindPreg('/"text":"AUTHENTICATION_FAILLED","severity":"ERROR"/')
        ) {
            $this->AskQuestion($this->Question, 'Please check that your verification code is typed correctly.');

            return false;
        }

        if ($this->http->Response['code'] == 403) {
            return false;
        }
        $response = $this->http->JsonLog();

        if (isset($response->redirectUrl)) {
            $this->http->GetURL($response->redirectUrl);
        }

        return $this->authComplete();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->profile->firstname . " " . $response->profile->lastname));
        // Membership number
        $this->SetProperty("Number", $response->profile->memberNumber ?? null);
        // Status
        $this->SetProperty("Status", $response->profile->tier ?? null);
        // Lifetime Tier Points
        $this->SetProperty("LifetimeTierPoints", $response->profile->lifetimeTierPoints ?? null);
        // Lifetime Tier
        if ($response->profile->lifetimeTierFlag != false
            || (isset($response->profile->nextLifetimeTierName) && $response->profile->nextLifetimeTierName != 'Gold')
        ) {
            $this->sendNotification("need to check LifetimeTier // RR");
        }
        $this->SetProperty("LifetimeTier", $this->http->FindPreg("/userLifeTimeTier\s*:\s*'([^']+)/")); // todo
        // Tier points
        $this->SetProperty("Collected", $response->profile->currentPeriod->tierPointsCollected ?? null);
        // Points to next Tier
        $this->SetProperty("PointsToNextTier", $response->profile->nextTier->tierPointsToNextTier ?? null);
        // Flights to next Tier
        $this->SetProperty("FlightsToNextTier", $response->profile->nextTier->flightsToNextTier ?? null);
        // Balance - Award points
        $this->SetBalance($response->profile->awardPoints ?? null);
        // Tracking period
        if (isset($response->profile->currentPeriod->trackingPeriodEnd)) {
            $this->SetProperty("TrackingPeriodEnds", preg_replace('/T.+/ims', '', $response->profile->currentPeriod->trackingPeriodEnd));
        }
        // Flights flown
        $this->SetProperty("Flown", $response->profile->currentPeriod->qualifyingFlightsFlown ?? null);

        // Vouchers
        $vouchers = $response->vouchers->vouchers ?? [];

        foreach ($vouchers as $voucher) {
            if ($voucher->status != "Available") {
                continue;
            }
            $exp = str_replace('T', ' ', $voucher->expirationDate);
            $this->AddSubAccount([
                'Code'           => "finnairVoucher" . $voucher->voucherNumber,
                'DisplayName'    => $voucher->productName,
                'Balance'        => $voucher->amount,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($vouchers as $voucher)

        // Expiration date  // refs #13155
        $exp = $response->profile->expirationDate ?? null;
        // https://redmine.awardwallet.com/issues/13155#note-8
        if (isset($this->Properties['Status']) && in_array($this->Properties['Status'], ['Platinum', 'Platinum Lumo'])) {
            $this->ClearExpirationDate();
            $this->SetProperty("AccountExpirationWarning", "do not expire with elite status");
        } elseif ($exp && ($exp = strtotime($exp))) {
            $this->SetExpirationDate($exp);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.finnair.com/finnaircom/wps/portal/finnair/jump/en_INT?locale=en_INT";

        return $arg;
    }

    protected function distil()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;
        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('fc-token', $captcha);
        //$this->http->SetInputValue('isAjax', 1);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->PostForm(["Content-Type:" => "application/x-www-form-urlencoded", "Referer" => $referer]);
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    protected function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//script[contains(text(), 'loadFunCaptcha')]", null, true, "/public_key\s*:\s*\"([^\"]+)/");
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        // todo: debug
//        $this->sendNotification("finnair - funcaptcha");

        $postData = array_merge(
            [
                "type"             => "FunCaptchaTask",
                "websiteURL"       => $this->http->currentUrl(),
                "websitePublicKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // RUCAPTCHA version
//        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $recognizer->RecognizeTimeout = 180;
//        $parameters = [
//            "method" => 'funcaptcha',
//            "pageurl" => $this->http->currentUrl(),
//            "proxy" => $this->http->GetProxy(),
//        ];
//        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        /*
        if ($this->http->FindNodes("//a[contains(text(), 'Logout')]")) {
            return true;
        }
        */
        $headers = [
            "Accept"       => "*/*",
            "oauth_token"  => $this->State['token'],
            "x-api-key"    => "5mBIAH9Mpzaiu2zcHtN956rX0UYEv3hU8oP7BYb1", // https://cdn.finnair.com/finnair-com-ux/prod/assets/js/5e5f29-16-main.js
            "Content-Type" => "application/json",
        ];
        $this->http->PostURL("https://api.finnair.com/a/customer-service/api/profile", '{"includeCip":true,"profileRequest":{"cache":"REFRESH","type":"FULL"},"vouchersRequest":{"cache":"REFRESH"}}', $headers);
        $response = $this->http->JsonLog();
        $memberNumber = $response->profile->memberNumber ?? null;
        $email = $response->profile->email ?? null;
        $this->logger->debug("[memberNumber]: {$memberNumber}");
        $this->logger->debug("[email]: {$email}");

        if (
            ($memberNumber && $memberNumber == preg_replace('/^AY/i', '', $this->AccountFields['Login']))
            || ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
        ) {
            return true;
        }

        return false;
    }

    private function sendStatistic($success, $retry, $key)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("finnair sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "retry"           => $retry,
            "attempt"         => $this->attempt,
            "sensor_data_key" => $key,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }

    private function botCheck()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Bot check')]")) {
            $this->distil();
            // error: Network error 28 - Operation timed out after 60001 milliseconds with 0 bytes received
            if ($this->http->Response['code'] == 405 && $this->http->FindSingleNode("//h1[contains(text(), 'Bot check')]")) {
                throw new CheckRetryNeededException(3);
            }
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Bot check')]"))
    }

    private function authComplete()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(4);
        $this->http->GetURL("https://auth.finnair.com/cas/oauth2.0/authorize?response_type=token&client_id=1APORTAL&redirect_uri=https://www.finnair.com/int/gb/plus");
        $token = $this->http->FindPreg("/access_token=([^&]+)/", false, $this->http->currentUrl());

        if (!$token) {
            return false;
        }
        $this->State['token'] = $token;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl =
            $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data url not found");

            return null;
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $abck = [
            // 0
            "E185F8B3E9015CF547B9D4C4AECCD0D8~0~YAAQRsgwFzsKgGKRAQAAtKERbgyXvCFiql0IXKWo8F3nNy98YwEj9/kCOXekeQJzfgTcqYljMvITY8dLc5DvCPNauh0QEXAdVvZtFLZRdwXDCYpM20khJnwduB70UkwBoi1ekBjridFNyqIcjuhRmgam/ZQihKQaBk6MyylkXkqcY16lJQEIR/mSB7WKtXxDKAnbu3Cu0/ubnPQNtZt3rn+OXrdc0cenxRdwtcYZDRxIvK73HvTgkaveph8fZTkoJ4qwtZLYnlObrX0/qTpP8Jl063/7dKYw9S8EnvJQ5thJOqXGbkgTis2kwX+kGrOwz0ATgZw8CaL5QPgsaU0mHsUIEFH5xRKJBNCFAjholExMcpEpdeqQodRGaWgIA6N7Lj5gjFFJN6HrD5M04JiKh2X07DlPsPTRFv/JSkY6J80BDV4QonI=~-1~||0||~1724132134",
            // 1
            "182DF9D0E05A1FEE2F396A607C4F97A1~0~YAAQRsgwF/MBgWKRAQAAT6QVbgwcmXH5EX2lDS/8lkqNGAyl9QpjCVAZ6rTU0w4sPIb5RuPaIIKFkr9F5kieZkGDgJH6B6+74njxFf9qz0wP+uB1FVCT4wd01u+VQgnqLJuR0AtL2jLpO/e6QEedvnzhYXN2ws9bwAxfwArEuabr1D8JGuV1K50l7CaPebtJ3v2ATO0fJHogvMDgWS012CRd4k7Avc5o5vtBbr9ITNozH/h4Je2BpWhuiq3oJeB432SJqeG8glD6bvdhuLV3JcduG8MK67s3S4V+G1A8E3TVowFgoyoTM0ZzB43J9CVLLIh8f4081BEBvsQd99KFOB9978jicvAa/FFiTLMS60zzNgv2feWYnEAmzdOsqcgDSgZs76wQ9jj0wUQIVvRJU+xZQpzViBQqayhURPqtEdMxmpRAHw==~-1~||0||~-1",
            // 2
            "416E34C45097308799EA2104AEA68A5D~0~YAAQRsgwFxTlgWKRAQAAcPwYbgxydcjcr2mY7JzwckVkknxgRgYZ2ZNv3eYD8WllqBJePVSCLWSL2IIZWzxdiO7W1BNXhdqix63DuX5PyixitwCy8BK30CGuyFXU2wfGayPK87mcJAYyz8TCLlNuOZTCTFAitcMvDW6bg8gQYBoFP9NJdzEPuP6psrkcwfS5hZBNGIaJw/Ltwkh8jzYNrH4gZ/kQM8Bkm/MsCNDlIc7mPVpsNfmmIF/xarwjWO6GdTLkewoJwYLYchpdNIKF50oQZVcDl9ZpataT3GSmjT8Fg5Gld4SW5lLlhGb23aor1VMqQZY6PtMNWRbgdSszGsfTGS2cugUfvL4HnusgeNDXPSTfdB9ODPcvGMIAzyDaD3tOg/60k8L+mHoVJOuy9ySUPnKmwPRFliFEHbJLlypwvEWYeQ==~-1~||0||~1724132616",
            // 3
            "E905AD87E24B5913C0D2D47CCA9C7DB5~-1~YAAQRsgwF/+0ul+RAQAAQ+9gagxraI3bYAcpWHnahcD0746hYSPQhOru9cKLTyjFvvI/8H9yup/D+cx9xiVnUvL+EhViCoUpJcqQUGT3sKOWynVnwJ0Z4+dDimFrFxF1t9S6w6v5RjNTdSgD3bVd7pBnpbE9MEJt6+gxyLoMf6QHlSwOclNhCq7LbBuRDBBnjYSsgAUO60Ewfj51vXSWttbXI/xBbx9JaZ2mnPtTLnNCdLE0KYWCkKtSfcaZhw2yjvBMh4AWXup38jKxvqpSlW9u4Jprr2+7YtNoqxCV9CVZtkPrYyHos2AiAiGC3t4hD6W/efJH6trnz2Q0WwWssxjY+CuslhIV8z4jBZDp1a1cDNjV96qeGOdmaZkaIQ==~-1~-1~-1",
            // 4
            "980C29A01E53181AEA2C9267352DF425~0~YAAQRsgwFwyVg2KRAQAAdAohbgytuCEy+Ngve5YxG9NWksagomusJeMvuXM78TMZMMRFhGIOjWjm734YQQNtFXW8V7tN4BnoZrDT0PA6jSlJFhcW0H12LRJdLcCLuI7+oqRpsJ+0IrvfQmW20apdXGT15rARMly2QPa4rCv1o75Z9Vu/S9e+0ZFvMOFfwoSZB8z8BYoxJlBj90pYzw9lQAAfQNx5r9E9HoAQLavTqMfI6Fg+wEHYan2C4N9JvDJiWAkfd1isZ1cwZbpbers1PIJj3Y75p3wnAhJOIsEJCdWofEcju/VTqcjcy98mgOiicVbp2Lyu/A9Kyfw7ZKKM9BRk5ZUDCMT7h3LQNF4B/iwgn5lyOr510VdfiPohwo60mGxj2syVJagJqUvUt+8Di45aYVlNX1frAJPZcdFboJTSlTjbrw==~-1~||0||~1724133144",
            // 5
            "0E705423DC5CE4CF298675EBF377C961~0~YAAQRsgwFxLMimKRAQAAjZxGbgxO1FZyG0KQKRUgZoebEa5vzFhbq2sL1diA5IoFdR6Yc1n+ogDdZ7cwQ09ETH5ZkjyxpKyQ0LoPZ3Cd2srpMOgHL4qw56OW6v3GlCiNxW/BLgzhq7WckvoFHLd9+WF2HLzUOo8bHJbzQYrcgLPOF5T7QOL/3gIC/AenhLRtdI0zMvNWbwlhI88LViD2lARKy5BTf2mdW19WH6FR+liXIQ+BnkQwxd0528jOHEYmmsnf30R7Y1IrEES5jN6jGyS89HlHSGWjitCAqCQW1F2Kj4ljSSCR9GfXW89WAkk5fnnOpebqGvOw8t2Td3ccjlZ4E5BAeP1gIdAmwyGeCkQgkaWSI0T+1x2y7FzDYTi4Ee+8Lm9sB2WUHpxmB9uomBKJARPAweIxLb0nDzQTxB8L9ODOjg==~-1~||0||~1724135607",
            // 6
            "1E30CC02E669C9B5F1DD45D78D79D322~0~YAAQRsgwF/LphGKRAQAA9/Unbgxii5+o60xIQbL13qja5iNnnARGc+sCS50e3yweoSV2ar1jHkQcNtYiQKy2r8/l2DPQJzX3pRimAUNzGFvd9AawsEAal8MDyAJ7RWRprrYbpo8Aofy7DXOHwqvT5PWQ0/wl494526qALld8zCGAaEl6afNdGX7U+xaDHMDtgY2VicXrLqkXIPe1gTj4qhVBAoDJRBMzz9EgU5hKDk/5ycxQrd8RmHu1ptxZ/hB4LPjLnhbdZXo3fbrCd7ZK+wUMjHuWSNiaCqgEq3j35oZpTrQrUZyZ5xARYxSmKFDXdFEe+k+9eeZZktA/7CfHwHqKteOYhzexYFGhNd/SRUYMyHzDqatGSYeC/T/C3ToW+ScObMMxBip1bu/MSb5zaKC/WYtaYn7gDes5Q5tB18BssgYNyQ==~-1~||0||~1724133598",
            // 7
            "196021B1C5624AC1A6855C5FF4EC33BE~0~YAAQRsgwF48mh2KRAQAA96wxbgzisX+i/d8eg9YYwxXbuzYd3lNvh/xRqNF2xbOUdqOPvI/2gO4HROt6/f1YR1iAZxUqhypezpy4VXZ582Y9IyLKe7VFwIrBPOvFg5UbXcgk+hwI+IyCKTxXX6aOFy8vcPI5RncdZhokCZtIan1WE23Rv3XlcQT60Jw96LGmrA89IHxMyObHZZtY5Ua1VqpmNeF0w+xJB/sCVtbDGtlJCErw1HOcZ1cuga646LIZwSDxa6eYDNahX6D1NMlZM7VUX/QFDHZ4UxAOo4nu24BeRlqO1APEOuE2VEL1UcuRbWaXpIlNc4siAKNonZfLygnbR+JbZuqT6akb9B9S14V4r1mkhOJVOzt3tbL4MHZjTjKhxAyEYHh2RfUWex/4ID7IBNj0A8wVqJKLoQCBP3zCiaaWRog=~-1~||0||~1724134234",
            // 8
            "D83DEABA0C3A256AD32D32CB4388BE65~0~YAAQRsgwFxFgiWKRAQAAqBw/bgzeLQdh4TcjG1lZADZDphM0hk8jKaVZozPLLMg590PnkWfSQs8zz75Sr1UmOse/H6rB+u/kzqogG8j1TbjE8HKD5FcFQMpSpqJv31hPmATvyHd9qqoXcFemXOcV/50aqfzrfMnjmYGWBDkHloitXOGG+9ez4FF1Q/bDxofXViNcMWYJCwIJpTvwU+rpr8xZPvM9Ma8DwgqFX8JIGRCrT9/l9JQ/hsT4QfGBJc9FY8RfJavmpwPNrH95Pwiso0X0PQz72+XuWvx3NW3lLftsMyMwtmV8tHKmQEx3+yUwet5JswKHzC+GykaRVIxTS6dpjp3Os2k6ZI6E2f4wC2VCIb9C+DRTgHJ2UMEDJiWTP7Ev6+eiAnyqJJrQz6BxOuYQJ8umK/lgakHP9ilgAtbRPUD+8w==~-1~||0||~1724135115",
            // 9
            "0A1EB3E9B41072F958AE32965DD49429~0~YAAQVmpkXxMk3WCRAQAAvQpfbgzoW7kLMJUHEcCcPu7Ttm+wmkavpzRQy7cYz/LAH7oLbHr2xVtohmgo6OUWN/48KQAP2lcGPivgLSll8pae6PUIQE089UJbQhvW7V1MCQDB+L45jOeBJ58R081mRTW7yenZHhf3DJphqJrvr1HHZKRWsAxYBMmeBXZ1DaMKAOxHfEJxMgVNecCkmZibMWIhugUUZC+cRum7OB21LUm8DMAf+ihJt3rmEtXPBfbhszze49E9DNEKZV4G8Vmi057FEh13ZN0mx8QVsqdAgcjcRlF3wRRHdulPP+/1WVEXOfDRldjkXqOWVLBngeuBx2ag7v5dFYxk55rW3cg13kAnF8r9Bw/hhEpK0+KcI5HTesqRXNe8J4D9rSY70WvxWdy20Ael+mRnCrN0jcM47RX68mEteA==~-1~||0||~1724137208",
            // 10
            '04362DFD83511AC40B81E4FFE326DAD2~0~YAAQVmpkX1433WCRAQAAXFZfbgxSF/7ylvO+HrrV5SLh7Amd+uIXVGhYfuDNZ7VN+ErcJsBVhHLadO/WN6MJNGYMDA6aERPuv4NxXIIq9jZOPGoQmr5bMm38jJFCxMNDDwYu02oXQzj2aiA92VH+OrDysRWA2ThJMzVxuqKOYlocUuaYdngSu4HVOjanwsUnGGh1KeOJSYbSlHZs1QYGEr5Pgw6anU9aX6yXp8//a4cwkHIDCCFuUa+RLUjUkq58YhQcWBrKFx7+BZTYz1iC0CDFlK2JnowWtmaEjk6G8sbRPP8bhdw2BO6Si5p6DYPbWzmsXUsAZnm2sBu9X19qCdcILEkTxtpP0c746zUXLfTX3uE8Ml8WCAEqCGvXwibibmYDB0xUKdaMAE2BpxGGYyxuVg0/SMFsB2VZBbabUTK6/D/neg==~-1~||0||~1724137227',
        ];
        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key]);

        $sensorData = [
            // 0
            '2;3158068;3162936;10,0,0,1,1,0;{kI4qi8hy?!LugN0~<03V7+ ,.F<~eiR/&gQw1U@.2wsbpRuu=dSbeXfT_!1UXfBR3f&#X~zgpX:ZWk:3NZ]$)8p%;Zq4EU@%pQ_1o$:1])H=R;og?~n%:6SJM4Zr|HFO3r26|ZVMZjr{.ypZ>VgP1jJmy3m: t*7s^z;W^i#o+2ERVN3[+B0}LI!nQtE~-/B3wIrt/JF%Y-6^JZVTQ$1c$#Y!ro[~VXkm}-SN4]Sz<XkJDC0zJ~tp//r;|Q3QS+/9_|A^4KL@2^l<OcIbxK<Ifgd<iK,U.,vk4DUmm)1cMJeXI?`XRcje8]6me<)H.Mz#{b>7;an?[FZ4P`mi-W`=a%K!ht`4(%w|NopZZu3BtO (.R3g>O2*zW-hdE` c0kxK}Ncc1G5mOk.i?eR3 7Q~YK11kyYEnW]WN#;TBNV:b7}My!A|x1M/^[C474vt<hv?#1pDrK@+ih#J 6Tda~<#^RIKD@ciP)Ss4(He>nY7;qY%j.G=8SuiI@`,.3/+av@fcN~dY5,jwHQ{).{BD[+6L^f1f|;$.4]_,6#z`.Fe[I@ld{5VmK yhik2$9fwfa$9.O/7Nwp_DXYa#N!NCk>b6DD|~3/H?=o>me1ed6cDC8|21GKADzOO4f)5=im0|Lh@V?Bk7V/rqb%ja<v$*!|Xe**Ws3aABP1/ooKwdy8z4YPD:$br)2jneyH/*Yw@EO{$SJo`0u$0WOfj`3<hgYNU]r5ZI{*`r~hMz?$mlS/JVm,,09q)Oa;GM/L#D{C  5f|F:]Mmf%+vw7*I&}Ymsk 6C3l]xM$3OOCoI;)INt}H_+~[]1OkI0XMJ.Sjr:H4^aZ-Z]WvqF_9Wx8RH:]ZqMhE2Kzuv.k- fk4~#t.I]G53J~a:@%c[k.8Dn3C(qt{lZd=&3/KD@ijiMe/+W$X^hHoT9evhd0T8>N:!#1>&tyi)sAias_MV`cyoJ>-:j^pl}_%S!>U!b8$R@W2KQ6)GQ9vvC5?a)mcWHjmJbx8)v52aQzOr$og5E&]2I_?Ey;6TzI71G^CqY63af0h%{84uwgA-FY8OHK v:Z8XzWu-@}A]3qN9bTS ?5+& 21?xI,HGpuu|gbxCreQQ*qCo^i>dPcVWkP1I9C|D}!~5+ktI6E2<te2ffn@wpooND;0c uo@817`V7jkl{`/bHw/<hnH{d8TX*:HW-M*8~Nn=/$126OJ2y8gV[A93+4/%DZi)0;gvo~YAG)70PMzWN&[*]%p|yKJbFqOEXjF/(jg*=.7<Y[||o7B`H`X]U>d&C$3kftVY/1%dln1xf&#[4s)%R?(=M&B&;rvtw[$~<sZNO}&!Il0,4m#`cj^z$w$c,{:o t7/(&_z826e/xji:;yl(&lV`5Y|{py]pAA>gH@,L.$=2)~p+}J-p85HFV&-A4^}n+Z8FNKff:Zs<Fz)}jsn*0(b3r77rh:!Ny{}mINO-1g*D4bKCjX<wG6_-pP209Z%tIyn:4XH|4v:|U.Sm*7p[}Vb13]1BEA^%viO`y0Df*!Y/2uBl(k,Wrg[nQexcsOKzGatsSqoU%h4FlPxVl(f^x[$]M$XZHm>rwqh4{^Nq^b-yWHce-6 aEgq,2!s4^2j{aDI&i3X`^_<TX<~`zCo;_Us4ib7m:(6ha4T]Sz8+mZMX,0nL+PHG,G>2)1RRLYrLw+atWg(`Z;U1]!UFjJBU0a[ZR48#pQ(m{G,A!G$)oxOZY=}%@v)-0&kw|&<I=@ZAM#F2Q{;QfOM_7qaT_e~o[uea5~2pn3cWXSHPgI6lB#d)AJzLY-9}Z9=Dj-8^NDpX{-ayLQrjG9W!xN;?szr:<u1CzthE5P[3Ou7>I2+P9.A%B-[ym0Ei?PM+*wD6+ilC~PGU]W?s%$S)S$hP$ED.;%94xd$Vw/I&VyTaS$bEedv#{EpT,#?v;*8MJR%^L+uf%$={*[>^s:Z,:BRuY!>#Ep!.RNt$KU=^,s{U!Z9MsL.n0s/$44g^1rW@?3qLew${K(2h=f:vL{Xbt^vB<EvsM)?%.xNi&V(k?=Ea.k$aH*Fc4Te+Jxf+;Fvx;8Zfb*VzbkP=FE>5p,*te( E3P@(F[/omI;M_/|V=^kUCaKQlw+eb_gI_t]Tx/ROAP6e8)?Sem(wN%YzkAvdI9o>Qwf;0M`lzjvs?soX,aL& CVHi_FK0p.<V9wo<fV8}8`FfY^E,^$s+dp=98(o[X;{BijqFinR^bwy*%;U~B8v>UzdIJCE/~rW{P/]:-Z<b~~$SZjS0&d4iBwgZKwT:r%T3Wr${[VIfk*=dO.vIWvOm2-cDT2fo[*Grug$WAL;fo2>V]xOK(?}21C,Ab=tajXf-{9M',
            // 1
            '2;4408373;3491126;10,0,0,1,1,0;=;xvNP:]Ol-sP.Xa*b9da?i9lt&td#>jF@$2l%d`7uMz#7@HtbRs2uvf0:LPKYsNEL1)i23yD~/,wlUvec;}uUGsZp-08BiT6=*Vf+D0eJ+WBLn+k;9+/R|[R4IFX/4}S~&!&A5=9(H~h(s0(kz1@>](y>clf8YAXcO8Gr>B2#M -:c/(b`FILL*u>l;N=6%Uq1z|{pt%4M:=P}U;N`d>u(rlAv%0q7`c4bz6advpX,jHP2jYFXLiYlFQy)AisKTGcx>_,5ZL7lo=V2hfXef(/70{^*Z)[>TLv/] S^ual_<nU[=DFktL{927r 8_R/MRx*m$}J( M-Hf~tRgdo+PUruq.&P?-&3E}--]R/[dj{{9G0=PJu,Jg{wn|u8j_TpB,__)u1i#:7B`}vU)?Xi;A[&B0 V-~uA]1[Pue$^]NT<b.u@Wa>Z,?dzC:%h>8Ff)k@xPNwlw?QM}:lh{)`+a*L^3F&W L_sJ8]^&&[H_9Dc~hum8K,B0e1Lrh#]8Xo+VO*nL>0#G*/:/e1M0gCuGw^:E^gE9>L2|I:%T^%~YVi#kMcxT&HqHF/?{}C7z4<D+2;33,{kA+5hd5p7wiE*{OEX[zJlJe*fDB1CK5+3_Qf2)k@@x1B2j]Lq[o6& ImO-3AjqJ/WF=.B5xm#XV[KwL:tc}A<@s4tlrj&n&m4x^7Wo`(EFqmYSYk[kuQTP0QL@9?^wu )z_,p/nkB5H5Uic&[H(gFItfnLhd0.FY*^e5n$oC`saVS_Sb<~@MCZ,Qkj|y(`)/7w@~Uo#P-!1FW%yQITGy4K*gtg1:f):Wipn`CYvE{$o*0LGGBF|gvyZdhg$6l0lq.5>/tZ({{rha,HFqagE)Gml@EZA37ZD$EZouHm)tK`d9)DGnjthJl5@jMeF4e&I.cY1wf=V<:`9p|ALns0ldS.5JZEHoIm9[$>j{!JxnXmpH](0MN-xe1qd[xCvI:ULmId%-;Z9*~=?j/xvSSkz^Bs|%GaAK4C#I6U{,}c/6N~KEUQ,mt5865wzuCelWY:5nLtBpkrp{p=goF!rp3-c-#_FwZCg9%0P- >dv.SWp3]}f#r6h1!*@$`Q6sQq=Q,.+4xno2zez*TkG3!A2J$4T6yc!hkT7G-/XyT%HeNEUg>mL5|W7Jq,NMVp-mdrlC 9(=TC]pup0;{L6vrF=aG^!|l=QJ(&lyd)*sgHj>0%DqJ{kJ574YLc:O$U5f-YS 9UhU` s=@<a8)NmYgNNS} K+CTq9+-@XKmUf;D_4M]nE(iiP;UR2 Is0#g12t6;Vkt%91 O||I%>xcR|}oqkk~.Bb`(10?GlxAOjYS8<WotC;+Q-:0D1/Z/:`E+$};Dz4^l|*(1zL%P4& >;>=RJN{a)8 V|w+Vv5j+?;E;x5]0jao+/SMhZ_H1FaOFdFDkN1[~QShK<Z)fNZUsqHMaWhSYU1xnu(a6O&XwIy>HXl6+o/;Qe}.aTuIFt:,2EU*D:x=p=Svi^u:SGBMZ:jlMQ]OGiEX2%A+#T+VG/:fZZ6WP4l~;SoLzz<@9iDEF`!j7/PBXa|=g{_a^Vr*])PxF Ma-Y-AWl<wFGvd#1]w,7xIKQ-&H]@l>xSP`6nd4m!pVd%1fWvLWyO%D?l6t3#R$Omy,nV]sX<gD435g%kL-5@>`,Wwd7vLQ-m:;4 QB!^[5:-8%p;4VRnh4a0hDtl8;W5PXE<R&[VeY*z,yvGh2C?cj$_<}P?5%O3EdjPCmel^S_`i_k@/C@?E6J%df9)rx7(>_lQi3{*+xS_1F>2!Mjzx7eBr){^<C4h!ZtZ-Tg-PHTawl0kY8$ji?yG>.({eb5Hp<a!^ZB_}5ru5ELDJoWMPrba.@;<)7!uEn-/tAC7R`/.ajnmqM10E*UR8H7GGG7Ea=NcL#8|BO,:Yb**,vHiwV86)<QD;8HOzd{3yQQu2D4`={*-,1~(O+uhs!%>;_NL;t[.-:2=O(fyWdo)3zmWgKm0)6f`i.&-tnJE<+c<w6y-y;&h*=o8VE>hH]rD9g|;la1RbX$7[{`<u55L C0T. h~&Mk<K`y.*oGwtn$aF&3VEzgU4$yY{(}6=b5grF[sin=_dw1A/^P#dG{9spnS}ys7`t!p-J>I;^p#(fJNjyHQ=eE#:?J|U$wgnRuObcr$WrV<JxUi39LEo#C_|k;?U|1-</A2c dW4`lvH7L|@f_f8nmiJ?OOt+X[_p=gTDvwea*$0w=8I2Q7YU$.JX}hN*_{t_A:KlK&`Ia=~/IRf078M7F=O6EDTZsv.GuI={EK<!Pb.{[]{6kbzMzw3PM=666$8CF0sc9Ty{InmT?Zg.;s&?3hc#>v7!!!,cXN*j>-tQMCQbw#)R?3z=o=phi .}*6(`zdQt`:V68b>(vr7~Cu01y?o99*sU)@jp$c;pdf{bRbS^L%,2hF2)2ARtW!!8t0}Hi+ak_1$ #/d{D,Li%N~gx55cmYv87L/q[9]eTEtTz4uOJ@3QjCi~SSb2*/@Q:|RJ&:CzQ:xUH%<KNF4P=/5`hs4j_-74}w{BGb3Xi8kOhB@ 2AO<u/g+Tn8jh%KI,df7t.FbgxxPe|5.GR,V1f>TqSjV{_s0NDSu0]^7;MA1c7J+Pe9l>=7mfy;F[gqs4IS-<r%?K%}gG~}y07>tKwaQ.Amp,G./(>I,Qz3v(@9_G2aQ.T7iY/%R?jD9$g0na|>{Gr9uMXWgWt-',
        ];

        $secondSensorData = [
            // 0
            '2;3158068;3162936;21,15,0,1,1,0;4ld54`tpJ  )OpR;b7`=^a wUY=Eccm,L$RZ+^TJV`L|gs]qJrH0*ebC07%<VZ<Y*<9| QXJ6v1[P_P m<^j!}~z/@bv=Kc_W@t&,wSz0>3E:KEjcHVrG5>2Qd5us#B<O5Y,3}ZXHa8s~(R^*u^kL:PFpy@?r<N%(x7wEybrcw|<wJ[H<*I`I?HO(9o6p&PS^OGo1rYeh,-Q^}owqW~@7-HNz9wBd<~/5&#OviJg` 0Uw+798*.~se(/s;kN99zG-UZx<B4.L&[Yv0MfRFkPG{nVc)CL%+5SHpAJ>lAQ4oGIi5P<5^elpm707r`<TGa PR$2l:@Ygh0t^4M7@sEx./f]jG1ah=mG{(0wLV`~;i|yC(/ROjJm2JJ<7gYMIY^9q#0p,H8?G3;IuOeCAV2 <M&XPb(umWHw[]+@Yh.MHS$/ydLU&r,~7N)jn@bYjLPPlX(O[WUU*tY=pksMR$ke$2!_VLIITD<tZu?9|B_<g*x=jR/h,H=3Z}K>x yv8LL,|AmgX$I)bV-9po?#o_hY{PMp)9rC_EP@<@,07Wwc<3-#omH1N~}-P|ragd)4AOZ>DfCDAxDpjDEA8g=#f[buK>+fuR^q/4K@8luJb,nb?G7D86.rB0A&zU)Kgj?sew,)`[EVe=tFWsegj&FYCx$I#iad*/ax:QsozT>LgUQYY+z5;C:B%>k#tjPe6?t*cK;GO5~=P7(,z-)W)?ij%?X@2v.e,SZC$5]q#f))>+uyq^;ZgX-7{h(XugwV)v!K#o|~96}NI7U=2(eRti+O&)Zvo`}<j6v5}Q&9OLCoF=.DIs%Oa0=X@;?5E,]dB|W.hA.yVf_,^RSyzMY=]#4GAGsWTWW=}K.tgsgB~t)Mit,n0Uwv;g D>;ybXg5eExvCGra%HW8>$(!Ss!hFiK3)54~^goCpU9-qbD=;-v9v[d?W~/eNeipAEV1(7bvQMMulmO9vQ phmYe3WA7Y.y<Z}37i*<{plHP>D-%TCULdO5yX}dl2fL`Tk(lg!A.DR]^FJ:I}`)F06Q`I!V55hr=m.V?:f|kZM2>=Cq]3>=?8oPKYm:D5vdS @{voEXzX3fRix((ulv,=LuHl=W{ejI{n<.H*v5yemCMVahn:rF|l,/,$f5MaOy8~5jE<t[2sJt]f.%LTG}VoZijS3OS5K&kPGy%-lCJ#Luz/2iB(ts{fm~>OBHd[Rag5)kn) dOl8lE32L9t^!h&h3?QY#M7SZ)~[1U^k4`OMrj[V/~/,FLS@0R|qER[/3Z85<Gb+5iI#q<ANefT;^s1u~@9!0 |WJEK4MgiXedc@VT(4T.DzDisT@26?];g|;aRSh]kM0|[if.U@k+>WMt9HPQ 8cxK@r~64dk[&-mqWB[Ur=IZ w>B(:/e0I Mr>=Mn3>Kn<aVxrGpvA{9/yO5&VGkT*5To]0T*Ah=wI-.,`7J;tWL}D6gm1fVkjr*@U$H;L<l$.Y(t^0&{`K|H[GQ5<RI*y:!V2UmZsBjv 6.%UMEH}}?s+K-lql<ht>+o._,Sn-8ViDk`Rh1G{A;z2O]joenaF4v$2S/|A[bz=rWWrPHH:CzWq59%>N.TF2 ^H}[xm!/@p!-8!5/J;.dHSWA6[tfF-pj?@r1LQ76het`dkFn,xLHIY14|t=~gI?dX`A*Z{%{#1T[lby>y0FMm=A{F*Mw+)F{ZPOq;eflJu`p#Idq23P>(`_pzOwQ9g*B 1QlKw~1q#@8U[;c{H&ftL59S&KA8VzEOX;; +dL7hG0KS0DQsAi9&^z4nPC>WEcgkgmF#,ZDU`f!my)Z[V>>.c]aK1,9{}B1$zz_G]|I8K>;&V#Ua`]p`Ty9$fng+3Waxx o]x6,y9Mo6h4:%[]#Nf5U;/;MYoBGte]O?,V<8^v WHmMxO[,qj<^hPFf$RwdTYold!VV}t3d6F@<S4Prno6mrkp/)uPT*5YHH,S`0yHu@EzB(z9VZ_:tw^&Bymh%`t@,?;IPOu@&aR.)YI]$?TcYjd1OdM.S|@VSs[j/A~D+`iLAzV$ea{hET]*A~<-euL&~B(kr-_,!k^O9v{Om5Wkx98h)y:.bDc49WHxE1}CC.QML8!VBXtYC[uYRO%%f*gs+WkXs4 VG-6*8(vXGl!#>{GzN;qh=5n>Trog4T8uxhwstnw@Bbg(+>R7K=!}}.+n7TtdN@:{whFK^XXI-]&0uoG+ixVmeS;vIn`qs_mIN]zW~|D#$Inq!U]dEE?Gm~aT#8PRApBR_f$+RXi_Q,<ksA|:OU;YpK0zY[:J{+0C21*Fnz1u+^6FY<p^?XHg,]UBzWp)W=NDbd+G{ IAL1f~1pMK@E8XaLXId}2F,iz).X-Ao.BA^G6LV@~0~..MU?_o>+U<VLJ[Q5|(d}RQXOGZz]QUNvE3F^_bBD^`ga$.XrIx~fFKVSGo5&ky|> m/>?[M?^!*zu7q:&$#S[5TKIu=9{9>k!@R}5K9%M$0]=pu9zU0ypVn2%eCsc~,s6e!bDttl: )^Adjng3rF%RvQnQ5-0GY%O(Ar)IPU,[',
            // 1
            "7a74G7m23Vrp0o5c9112661.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:82.0) Gecko/20100101 Firefox/82.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395099,4262725,1536,872,1536,960,1536,412,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6005,0.13132539065,802892131362.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://auth.finnair.com/content/en/login/finnair-plus-1,2,-94,-115,1,32,32,0,0,0,0,252,0,1605784262725,7,17178,0,0,2863,0,0,259,0,0,1363AC9D362B037D32C50889A4E34527~0~YAAQngXVF5hMr6B1AQAAPFkz4AQARYuM1IOLUz1ksCyRPoyQDZF8IOWnY2+9Gv4ZksA4xsWMTfzBK0xm6X0m2Ei/D79nQkUQWQ45xXLBY8jkUPzfEnzydB281kev3yf50YaIhzL+QhEifxm1pHhlLM7gNyMK6vEo27B0L6wO4rGipyed9MhHvhH7lgkNBknK5Qv/DpYr5xrGlziaCguQlin4RDcxoiZeobUfdUfDKMLZPdCZzbehuJpNexmIbBpt57UHLqGFDcCveIIYEwrzhfyGiUvGZCly2evhM4CyO3O7QcM7NRAxJerF2GgmnWjbkvDIdpQWiZ+KzZ4WirYeNrIatZGdpEc=~-1~||1-qgFkNTGbUY-1-10-1000-2||~-1,35343,731,1180487403,26067385,PiZtE,39119,30-1,2,-94,-106,8,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.782e02a822672,0.360a893339d11,0.d4340e2fac21a,0.e9c5ff297b2b78,0.94139d6dc218e8,0.532137f37ed3f8,0.530e9170819878,0.48a1ffeaadca38,0.46cdc9d10cf7a,0.405643671ec3e;0,1,0,0,0,0,0,0,0,1;0,0,2,1,4,10,1,1,6,26;1363AC9D362B037D32C50889A4E34527,1605784262725,qgFkNTGbUY,1363AC9D362B037D32C50889A4E345271605784262725qgFkNTGbUY,1,1,0.782e02a822672,1363AC9D362B037D32C50889A4E345271605784262725qgFkNTGbUY10.782e02a822672,135,155,85,67,237,200,110,157,132,0,1,238,77,185,171,205,202,20,181,61,102,191,97,98,192,245,238,238,191,117,92,24,205,0,1605784262977;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,12788130-1,2,-94,-118,116082-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,,,,0-1,2,-94,-121,;9;4;0",
            // 2
            '2;4408373;3491126;3,15,0,0,1,0;B6yxVP:2gSPeW6S]%a>%^!n0ns0|Z#<qF?}4q{gc*!-MA+LUObFxgE=Y;JmM?9!uA@&Thci7x7_}wi-G#ZB!uYIkRu0.yMdS7046:K8:ul+KCMr1fE@)xR[.r-QEXx<-l$}C.42B,&Kxmr:aN1$ft_X-l6d_[xA;,LkHIlu?&6,gkw72#^aHMEH!y06GS-pc}87[V$$/(0V>=SzmC%7#R}~zkWyy>EnvqL_z7YXupzF;AW.jPKaTdQcNT!cr#kK^ShsBQ+7WLi727Y7_bOrn(&4,!fLZ%d8[Pw0W*M~*~;$#!:&dhmv$5IXU[9#?cL-UU70i;Z6SJqRfewtXh_o+VXnj}hpq3.|*E 2b3s+al)stEWL5EOn-Fe%*.tiBi`Sp$] W)l*o~G9;6$Ys%hcA;S|(>0{R. uE]7ZQue$juJH=yEF9Yf9Q3:lv;23_54Ek@k7}[Pw`s;IY0Vdg%1d%^2La(4&kBLWsG7bX&&SJg@>F,w[Ux~6:lDfPSPi8FNY/VOmV@27|K,-:1ONb#j}H*`.-FU^F8FO&hP=+$xXL|[p|kI?PWlH~mF&?{}A6~,<yXV77*)~sA+9iO9Gh9dJ$rU@b;EmhSm$@#uiz/@%2`Ds0}h8F)IF+-XEr[j0!tBlO+3~<3C7/y^+J=3p}a3&epQDsj}XD7v3|dip w7(8ygBWgVXNBCm)]:?~;}XEP,UH>nIZv!Y&x,qKtSKDZC@28*1huK.#.9D#tf|*x`l&, K2)!Kxu[V__p]<;gIGd<jji|)M[z9G;@rbg;T}(rwtw~.|mF#8P~Mpl25b&B[nlyZBZiM#&t+&KDIII j#0}adg ;k/Wz50H3}:P;{zty(>P=3>ma{n,E}[13l2b9MYe^Mq~sRf^$)JOvmpA4D8(j*4d.e4n.ZY$sb5`@5CEh6E?yr0hd,[MCZqKiJme&Og*! Mxh`tvH^Z5^g/tn8lvq$L&P7lYiKy-%+`8.!EJt>v~^`ssjWnl}5,V&!<b(D#0;?ZNQ?_,Nki|>`lC=p-!;*2(:*A+_>b|*ea2~m;;8HgHj+eN<H3Z ATCr{hK)k|[fE;?jV~euJt6qz)stBC71..J,zVi_-y#(|aewh_WPOYWIfqB&r8ex9fn^KE{+K0_cK%fp.aGhdNgqIW<+X|sX1)%Up^b]ID,G1++i?<`nW=blB0(mgs;pHlA+gb_UYgzEMJ]#IFGTY8|_)d@o2G{_@rDobg[EOm+}!pL@n>-Fms3}c!~M;L[4+0wY5uJ,bFfzv1e2oT$3F+WEMhcV?RLy.cgpCJA$Z0%+r# Q/%HY_)s.C(6kQ6B/K-tdXz&vFz0uu+E/1ecMyvcINjP|WF/&#)Qn07J, )A(*~Pg_M[`|scL YBAN!3@wuoM~LL/T[JjI6v[;7>B:s)t?9,ln#4RJ51Gxw]v=BMP4+hQ{%v%.ujX]hMAV-tiy([jBpQsIUmnLx:Fw*5Eb{da&tNSw:d-AS7C7cDh:Iqif#RUCK^s6`xJO0xKtpB73BMuY@s?(A_^[.aeVqrCc1IvzD:7j?ENeBm1F6;P;RrAOyXSQ1_1]0],@T+twn~tN$2wql2d|}m(3DNSyy#R=KnFSv#/UUg/j*zMN0{Yn5tn}O)?Ds4n)#O#Idv-oOerQ}qTU3)s`Vm~6zm%}]$g6n8^1jf;8t@J*VW6>-6x{B1?RQ8m]0`;zgA6N;KbL7_}|60r%#*vuG9Ro`]f0f?$X]-]6iNb^]}X(`cV_Zi^g]*C}oe/R$20rju`7$=dgQ(Y=y,x62Q?>)%Lrro9^G{%f^F@}m Z`c1IO9KDTY{s(ke2)rh@bh=%}!jsMKlD=KzNF6PTmz6?X<bsJWa6bT2748);*vCn%0s?C:EZ)(Xenutd/*D{ON3D0LGh;6fDZiK}0|=O(>Xf)1,4P[oW+<-95IOXDC{`x5y_vu*Dt3^t*AO)w4N&lmlw!6;VTL.~;`M.<Mq(Y~Xi;IHA.QiH@IB]h/,NN^;(St_L`g4,r`;P,6L?7R~rbil$<hf*7?-)Eof]u3]$d9q@r}?74S-{o%CUb;H`u-.o:sva/@xG&aJoR].~}L% {78bB-9x^hj`/`Xg5E&XL{e7x.c^oDhyrKMo6v/Zv41@qDIJ >LbI,W6y,RPT/#~^%:}?xIxR^Se<!7Ch:~sN)0]]xMXtR-INf&i? ?!;{n=nM`yK gK;84oY0e#,|Yr{JvU-aC Ap)=*n-1!TK3(ywka$ZSzjs7@;M2kVKq5 Tw7# cOOQ0EehVz*u,OGCHDDsoC7t02n~|4*QxWCpYj1Ae>{`QK@4btfiWm4nCGQ&J7:n%gYdRbopj~UNu=T<j8&CW2,qpU&N}}&V!f`d_vy<Z~nP~J1D(G_VQBE&-m`~O[b?_^Lhh+,UJe[Q~Y_lDy`LQEI=GYzN!1?rJF>PU{5Y]pznO*9D#YD6HFCAJ=4mDHN/Q-+@V}6UjR!(@J7HR75{(}::K8FyQn2pMeS$-k|LY~y2pdy=kVUS_r}/;=;zZ{?,o-yRo8W9Uzw7ui0J8x{tpB<D1.8t2/r>Z2Nt<m/g+Sk!ql)GH3B6Wh2@gru`PwBg*~V5(6bxd2!d.]VH|lKRD0Tb1k#:kk684{d>eJKdb5TsBy>C>+I+^nr1N=W{l>{t~DW:hc;%`9Lv{%K0<&BT%A*9$+BSPxbj^+XHnU1&]Ij1DN+]fiT}_lB_!K1{a?v2>s5%XT++Y|xQRmE,<4j%h!c9s/i[_b{1:lPv6(=]2@u[n$nOgm>_:kW)v3Zn~k]hyT9-psRbJ5oZe0pbot9^|J,gQvQk!qU)9zMO???C%,YDezYD]E|@=sj<,UETzkfzD+enReOK-b:,Pnj]yD%XO3 aU0x4TPPz6.csqzvA2Izx=f`_+=&pSCC*%dGm<4>m32xvu2GL8V@:`&<8l6n]`@D74la5Y_iFriKit/3TY_nOy^nxDsfMPJTEL}%E!*2uIMAkX}/PbpfeM*P>x:D+<35?<(~_7E_6sww;DbuQWlliR6MXa}i3CZ(z-9[Es3GDTyyZdAso!GCcRKfpM132IDdi%wpP1Tm]RQb*As9jAUP`TI[$pOETnv,.^S`A2Mv`UYo>eCR<J 1K43]seSWb|6Qn,)3wm;cTA_ NlHqV `g$zm=Z[Sjk-:d)IIfSLv?IAb{Xvh0|?q2^JSzSHDUg+M&2_k5#0Uy}#CZE{b5}M.Twq+R,$xA]uIu;rt8<FnY]q#G-`S*+Y=^!5@xMNW%8n-q/,}CKZ3n}%V~m7A!nF:j8YY0{* TjFs]{bv<JOF 5KwO7~uX[G/_,=VJ3D<l3}M8`EkCN^S#FcsFFLI54~o?&:]J0BY,R4yc<!Nr,AA)_`D-zdsUfYN#c-O@9F_ir^4+_|~ZK*!SlXM,-y^v+v!5}m.WRqX<qh}yXuv/Jja6VQH&1X2]Nm8#mqB?dUL>jgOF@*zPo=<<j6|otx13LC_CAd2jq^Z?R[GJ4=tf!if#FhnZng .HH^XU$Skd<_Y;D1W5/&rCs~(j<=+ANm6@E7B%f{!$m6/q~z%N)yhv ]?~]6fz|PW.3FR[8r}6x%9,54dVh4H~V-tiS[Ml)ob<W:u gQb,pl;BwVug$%Ca@jyYNe/7L)B2Mmy=wZwH-m#{(8.?PF_]]r4+h 1Y%YuXy=LdSKtI c |@Dm{FHT,2M}qa};`Gh.u:p~8}(VyqdczAIr/UepfV&OmIY#Y|j;nB~*cYN}FK>^>b&Q@:tSyEP#$!Cb@nY7~D/>op|Eq~w9F 3o5RxA/0e<GN~?~U>xp=+Qx$3b9KGj+OxTzNp+T9n[`mA[O%{dq1v!XM6hcyrA^xME5w.W)8wZlza}wXC79wr2[#20V',
        ];

        if (count($sensorData) !== count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);
        $data = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
