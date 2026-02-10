<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAireuropa extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected const ABCK_CACHE_KEY = 'aireuropa_abck';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if (
            !isset($this->State['accessToken'])
            || !isset($this->State['idUser'])
        ) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->RetryCount = 0;
        $this->http->removeCookies();

        $this->logger->notice(__METHOD__);

        // prevent 403
        /*
        $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
        $this->logger->debug("_abck from cache: {$abck}");
        if (!$abck || $this->attempt > 0) {
            $this->selenium();
            $abck = Cache::getInstance()->get(self::ABCK_CACHE_KEY);
            $this->logger->notice("get reese84 from cache");
        }
        $this->http->setCookie('_abck', $abck, ".aireuropa.com");
        */

        $this->http->GetURL('https://www.aireuropa.com/us/en/home?market=US&market=OT');

        $this->distil();
        $this->parseGeetestCaptcha();

        if ($this->http->Response['code'] == 403) {
            return false;
        }

        $this->selenium();

        /*
        $this->checkBotProtection();

        if (strstr($this->http->currentUrl(), 'webmantenance') || $this->http->Response['code'] == 405) {
            return $this->checkErrors();
        }

        $params = [
            'username'        => $this->AccountFields['Login'],
            'password'        => $this->AccountFields['Pass'],
        ];

        $headers = [
            "Accept"        => "application/json",
            "Content-Type"  => "application/json",
            "Authorization" => "Basic amFuby13ZWI6ZCFlKnUmZ1A3TW0+WThHRCYiWCpfZnM8czU5XURQSFA=",
            "locale"        => "en-US",
            "market"        => "US",
            "rskid"         => "befdcb65-8bbb-469f-9805-6e8bdb0cfd8f",
            "sessionid"     => "befdcb65-8bbb-469f-9805-6e8bdb0cfd8f",
        ];
        $this->http->PostURL('https://www.aireuropa.com/usvc/jano-login/v2/users/login', json_encode($params), $headers);

        if ($this->http->Response['code'] == 403) {
            throw new CheckRetryNeededException(2, 0);
        }
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // La información proporcionada no ha podido sincronizarse con el proveedor de fidelización.
        if ($this->http->FindPreg('/"message":"At this time it has not been possible to access your Air Europa SUMA account, please try again later/')) {
            throw new CheckException("La información proporcionada no ha podido sincronizarse con el proveedor de fidelización.", ACCOUNT_PROVIDER_ERROR);
        }

        if (strstr($this->http->currentUrl(), 'webmantenance')) {
            throw new CheckException("Estamos actualizando la web, para más información llame al 911 401 501 ", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $loginJson = $this->http->JsonLog(null, 3, true);

        if (ArrayVal($loginJson, 'error') === 'invalid_grant'
            && ($message = ArrayVal($loginJson, 'error_description', null))) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $accessToken = ArrayVal($loginJson, 'accessToken');

        if ($accessToken) {
            $this->State['accessToken'] = $accessToken;
            $this->State['idUser'] = ArrayVal($loginJson, 'idUser');

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        if ($message =
                $this->http->FindSingleNode("//div[contains(@class, 'invalid-credentials')]")
                ?? $this->http->FindSingleNode("(//div[contains(@class, 'alert-danger')])[1]")
                ?? $this->http->FindSingleNode("//p[contains(text(), 'Please check your email and confirm your identity to log in to your account.')]")
                ?? $this->http->FindSingleNode("//h2[contains(text(), 'Sorry, there was an error')]")
        ) {
            $this->logger->error("[Error]: '{$message}'");

            if (
                $message == 'Wrong username or password'
                || $message == 'E-mail or number invalid'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Please check your email and confirm your identity to log in to your account.')
                || $message == 'Sorry, there was an error'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $userJson = $this->http->JsonLog(null, 0);
        $frequentFlyer = $userJson->frequentFlyer ?? null;
        // Balance - Air Europa SUMA Miles
        $this->SetBalance($frequentFlyer->sumaMiles ?? null);
        // Name
        $this->SetProperty("Name", beautifulName($frequentFlyer->fullName) ?? null);
        // Number
        $this->SetProperty("Number", $frequentFlyer->ffn ?? null);
        // Elite Status
        $this->SetProperty("EliteStatus", $frequentFlyer->tier ?? null);
        // YTD Qualifying Miles
        $this->SetProperty("QualifyingMiles", $frequentFlyer->tierMiles ?? null);
        // Miles To Next Level
        $this->SetProperty("MilesToNextLevel", $frequentFlyer->nextTierMiles ?? null);
        // Flights To Next Level
        $this->SetProperty("FlightsToNextLevel", $frequentFlyer->nextTierFlights ?? null);
        // Status Expiration Date
        $this->SetProperty("StatusExpirationDate", $frequentFlyer->tierEndOn ?? null);
        // Expiration Date
        if (isset($frequentFlyer->milesExpiresOn)) {
            $exp = $frequentFlyer->milesExpiresOn;

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
        }
        // Member Since
        $this->SetProperty("MemberSince", $frequentFlyer->enrolledOn ?? null);

//        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
//        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    protected function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        if ($this->http->FindPreg("/Validating JavaScript Engine/")) {
            $this->http->GetURL($this->http->currentUrl());
        }
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

        if (!$this->http->ParseForm(null, "//form[@id = 'distilCaptchaForm' and @class = 'recaptcha2']")) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('isAjax', "1");

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('
            //form[@id = "distilCaptchaForm" and @class = "recaptcha2"]//div[@class = "g-recaptcha"]/@data-sitekey
            | //h1[contains(text(), "Pardon Our Interruption")]/following-sibling::div[@class = "g-recaptcha"]/@data-sitekey
        ');
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

    protected function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Authorization' => sprintf('Bearer %s', $this->State['accessToken']),
            'Content-Type'  => 'application/json',
        ];

        foreach ($headers as $key => $value) {
            $this->http->setDefaultHeader($key, $value);
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.aireuropa.com/usvc/jano-service/v1/users/' . $this->State['idUser'], $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, false, 'status');
        $email = $response->email ?? null;
        $this->logger->debug("[email]: {$email}");
        $number = $response->frequentFlyer->ffn ?? null;
        $this->logger->debug("[number]: {$number}");

        if (
            ($number && $number == $this->AccountFields['Login'])
            || ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
        ) {
            return true;
        }

        return false;
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'ticket'            => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
//            $selenium->disableImages();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.aireuropa.com/us/en/home");

            $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "My account")] | //button[@id = "ensCloseBanner"]'), 15);
            $timeout = 0;

            if ($accept = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "ensCloseBanner"]'), 0)) {
                $this->savePageToLogs($selenium);
                $accept->click();
                $timeout = 15;
            }

            if ($applyBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "ensAcceptAll"]'), 0)) {
                $this->savePageToLogs($selenium);
                $applyBtn->click();
                $timeout = 15;
            }

            $this->applySetting($selenium);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "My account")]'), $timeout);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            $this->applySetting($selenium);

            if ($btn) {
//                $btn->click();
                $selenium->driver->executeScript("document.querySelector('.my-account button').click();");
//                $this->savePageToLogs($selenium);
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'email']"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                $this->logger->error("something went wrong");

                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[not(contains(@class, 'disabled')) and contains(., 'Log in')] | //div[contains(@class, 'alert-danger')]"), 10);

            if (!$button) {
                $this->logger->error("something went wrong");
                $this->savePageToLogs($selenium);

                return false;
            }

            $this->logger->debug("click 'Sign In'");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'passenger-name')] | //div[contains(@class, 'invalid-credentials')] | //p[contains(text(), 'Please check your email and confirm your identity to log in to your account.')] | //h2[contains(text(), 'Sorry, there was an error')]"), 10);
            $this->savePageToLogs($selenium);

            $this->logger->debug("get localStorage item");
            $janoToken = $selenium->driver->executeScript("return localStorage.getItem('janoToken');");
            $this->logger->info("[janoToken]: " . $janoToken);

            if (!empty($janoToken)) {
                $this->http->SetBody($janoToken);
            }

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return false;
    }

    private function applySetting($selenium)
    {
        $xpath = '//div[@aria-label="dialog"]//button[contains(text(), "Apply")]';

        if ($applyBtn = $selenium->waitForElement(WebDriverBy::xpath($xpath), 0)) {
            $this->savePageToLogs($selenium);
            $applyBtn->click();

            $this->waitFor(function () use ($selenium, $xpath) {
                return !$selenium->waitForElement(WebDriverBy::xpath($xpath), 0);
            }, 10);
        }
    }
}
