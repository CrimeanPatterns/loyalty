<?php

class TAccountCheckerBinny extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.binnys.com/myaccount/account';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.binnys.com/account/login');
//        if (!$this->http->ParseForm("login-form")) {
//            return $this->checkErrors();
//        }
        $requestVerificationToken = $this->http->FindSingleNode('//input[@name = "__RequestVerificationToken"]/@value');

        if ($this->http->Response['code'] != 200 || !$requestVerificationToken) {
            if ($this->http->Response['code'] == 403) {
                return $this->getCookiesFromSelenium();
            }

            return $this->checkErrors();
        }

        $headers = [
            "RequestVerificationToken" => $requestVerificationToken,
            "Accept"                   => "application/json, text/javascript, */*; q=0.01",
            "Accept-Encoding"          => "gzip, deflate, br",
            "Content-Type"             => "application/json",
            "X-Requested-With"         => "XMLHttpRequest",
        ];
        $data = [
            "email"      => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "rememberMe" => true,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.binnys.com/default/en/api/loginapi/login", json_encode($data), $headers);
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
        $response = $this->http->JsonLog(null, 5);

        if (isset($response->redirectUrl)) {
            $this->http->RetryCount = 0;
            $this->http->GetURL(self::REWARDS_PAGE_URL);
            $this->http->RetryCount = 2;

            if ($this->loginSuccessful()) {
                return true;
            }
        }
        $message =
            $response->modelState->serverError->errors[0]->errorMessage
            ?? $response->modelState->ServerError->errors[0]->errorMessage
            ?? $this->http->FindSingleNode('//div[contains(@class, "error-msg")]')
            ?? null
        ;

        if ($message) {
            $this->logger->error("[Message]: {$message}");

            if ($message == 'Your email or password is invalid') {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Unknown error returned from server') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }
        // Server Error in '/' Application.
        if (
            $this->http->Response['code'] == 500
            && $this->http->FindPreg("/(Server Error in \'\/\' Application\.)/ims")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $serverSideViewModel = $this->http->FindSingleNode('//script[contains(text(), "var serverSideViewModel")]');
        // Name
        $this->SetProperty('Name', beautifulName(($this->http->FindPreg('/profile:\{firstName:"([^\"]+)",lastName:"[^\"]+",/', false, $serverSideViewModel)) . " " . ($this->http->FindPreg('/profile:\{firstName:"[^\"]+",lastName:"([^\"]+)",/', false, $serverSideViewModel))));
        // Card Number
        $this->SetProperty('CardNumber', $this->http->FindPreg("/binnysCardNumber:\"([^\"]+)/", false, $serverSideViewModel));
        // Balance - Binny's Card Balance
        $this->SetBalance($this->http->FindPreg("/binnysCardBalance:([^,]+)/", false, $serverSideViewModel));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[contains(@href, "Logout")]')) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL('https://www.binnys.com/account/login');

            $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "signupEmail"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 10);
            $this->savePageToLogs($selenium);

            if ($this->cloudFlareworkaround($selenium)) {
                $this->savePageToLogs($selenium);
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "login-form"]//input[@name = "signupEmail"]'), 5);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "login-form"]//input[@name = "signupPassword"]'), 0);
            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "login-form"]//button[contains(., "Sign In")]'), 0);
            $this->savePageToLogs($selenium);

            if (!isset($login, $password, $signIn)) {
                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $signIn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "Logout")] | //div[contains(@class, "error-msg")]'), 10);
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//a[contains(@href, "Logout")]')) {
                $selenium->http->GetURL(self::REWARDS_PAGE_URL);

                $selenium->waitForElement(WebDriverBy::xpath('//h4[contains(text(), "Account Information")]'), 10);
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            UnknownServerException
            | WebDriverCurlException
            | Facebook\WebDriver\Exception\WebDriverException
            $e
        ) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    /* @deprecated */
    private function cloudFlareworkaround($browser)
    {
        $this->logger->notice(__METHOD__);

        $res = false;

        if ($verify = $browser->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human']"), 0)) {
            $verify->click();
            $res = true;
        }

        if ($iframe = $browser->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper']//iframe"), 5)) {
            $browser->driver->switchTo()->frame($iframe);
            $this->savePageToLogs($browser);

            if ($captcha = $browser->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                $this->savePageToLogs($browser);
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->savePageToLogs($browser);
                sleep(15);

                $browser->driver->switchTo()->defaultContent();
                $this->savePageToLogs($browser);
                $res = true;
            }
        }

        return $res;
    }
}
