<?php

// test hook10

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAce extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.acehardware.com/myaccount';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $headers = [
        'Accept'       => 'application/json',
        'Content-Type' => 'application/json',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please be certain you have entered a valid email address.
        if (!strstr($this->AccountFields["Login"], '@')) {
            throw new CheckException("Please be certain you have entered a valid email address.", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->FilterHTML = false;
        $this->http->removeCookies();

        $this->selenium();

        return true;

        $this->http->GetURL("https://www.acehardware.com/user/login?returnUrl=%2fmyaccount");
        $data = $this->http->JsonLog($this->http->FindSingleNode("//script[@id='data-mz-preload-apicontext']"), 3, true);

        if (!isset($data['headers'])) {
            if ($this->http->Response['code'] == 403) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 0);
            }

            return false;
        }
        $this->headers = array_merge($this->headers, $data['headers']);

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            'email'    => $this->AccountFields['Login'],
            'password' => $this->AccountFields['Pass'],
            'token'    => $captcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.acehardware.com/user/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $response->message ?? null;

        if (!$message && strlen($this->http->Response['body']) < 200) {
            $message = $this->http->Response['body'];
        }

        if (
            $this->http->FindSingleNode('//div[contains(@class, "desktop-active")]//div[contains(@class, "accountName")]')
            ?? $this->http->FindSingleNode('//a[@data-mz-action="logout"]')
        ) {
            return $this->loginSuccessful();
        }

        if (isset($message)) {
            // Access is allowed
            if ($this->http->FindPreg('/^\"?Logged in as .+/i', false, $message)) {
                $this->captchaReporting($this->recognizer);

                return $this->loginSuccessful();
            }
            // Invalid credentials
            if ($this->http->FindPreg('/\"?Login as .+? failed\. Please try again\./i', false, $message)) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // The User account is locked for security purposes.
            if (strstr($message, 'The User account is locked for security purposes.')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("The User account is locked for security purposes.", ACCOUNT_LOCKOUT);
            }
            // One or more errors occurred.
            if (strstr($message, 'One or more errors occurred.')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckRetryNeededException();
            }
        }// if (isset($message))

        if ($message = $this->http->FindSingleNode('
                //span[contains(@class, "validationmessage")]
                | //div[contains(@class, "alert-message")]/span/p
            ')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Your email or password is incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // AccountID: 2351111
        if ($this->http->FindPreg('/<h2 class="mz-errordetail-header">Our software encountered an error it couldn\&\#39;t resolve.<\/h2>/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
        $accountId = $this->http->FindPreg('/,"id":(\d+),"/');

        if (!$accountId) {
            // Our site is currently under maintenance. Please check back soon.
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is currently under maintenance. Please check back soon.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        $this->http->PostURL('https://www.acehardware.com/aceRewards/getAccount', json_encode(['accountId' => $accountId]), $this->headers);
        $response = $this->http->JsonLog();

        if (!isset($response->TotalQty, $response->FirstName, $response->CustomerID, $response->NextRewardQty)) {
            // Link Your Ace Rewards Card
            if ($this->http->FindPreg("/(?:CustomerAccountAttribute not found with accountAttributeId:tenant~ace-rewards-id|function AceH\.ace_rewards_arc\.1\.0\.35\.Release getAccount timed out|\{\"message\":\"\{\}\",\"stack\":\"Error: \{\}.n\s*at left \(V8Runtime \[\d+\]|\{\"message\":\"429\",\"stack\":\"Error: 429.n\s*at left \(V8Runtime \[\d+\])/")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($response->message, $response->stack)
                && $response->message == 500
                && $this->http->FindPreg('/Error: 500/', false, $response->stack)) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 5460995
            if (
                $this->http->Response['code'] == 404
                && $this->http->Response['body'] == '{}'
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Ace Rewards Number
        $this->SetProperty("Number", ltrim($response->CustomerID, '0'));
        // Name
        $this->SetProperty("Name", beautifulName("{$response->FirstName} {$response->LastName}"));
        // You are 1,997 points away from your next reward
        $this->SetProperty("PointsToNextReward", $response->NextRewardQty);
        // Balance - Current Points
        $this->SetBalance($response->TotalQty);
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/recaptcha\/enterprise\.js\?render=([^\"]+)\">/");

        if (!$key) {
            return false;
        }

//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl"   => $this->http->currentUrl(),
//            "proxy"     => $this->http->GetProxy(),
//            "invisible" => 1,
//            "action"    => "LOGIN",
//            "version"   => "enterprise",
//        ];
//
//        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "LOGIN",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        // it works
        if ($this->http->Response['code'] === 200 && empty($this->http->Response['body'])) {
            sleep(5);
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        }

        $email = $this->http->FindPreg('/"emailAddress":"([^"]+)","/');
        $userName = $this->http->FindPreg('/"userName":"([^"]+)","/');
        $this->logger->debug("Email: {$email}");
        $this->http->RetryCount = 2;

        if (
            ($email && strtolower($email) == strtolower($this->AccountFields['Login']))
            || $userName && strtolower($userName) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefoxPlaywright();
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            try {
                $selenium->http->GetURL("https://www.acehardware.com");
                $selenium->http->GetURL("https://www.acehardware.com/user/login?returnUrl=%2fmyaccount");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            sleep(1);

            $login = $this->waitForElementIgnoreStaleness($selenium, WebDriverBy::xpath("//input[@id = 'sign-in-customer-login-email']"), 5);
            $this->savePageToLogs($selenium);
            $pass = $this->waitForElementIgnoreStaleness($selenium, WebDriverBy::xpath("//input[@id = 'sign-in-customer-login-password']"), 0);
            $signInButton = $this->waitForElementIgnoreStaleness($selenium, WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);

            if (!$login || !$pass || !$signInButton) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(2, 0);
                }

                return false;
            }

            $this->logger->debug("close popup");
            $selenium->driver->executeScript('
                let closePopupBtn = document.querySelector(\'button[class="close"]\');
                if (closePopupBtn) {
                    closePopupBtn.click();
                }
            ');

            $this->logger->debug("set login");

            try {
//                $login->sendKeys($this->AccountFields['Login']);
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->sendKeys($login, $this->AccountFields['Login'], 5);
            } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $login = $this->waitForElementIgnoreStaleness($selenium, WebDriverBy::xpath("//input[@id = 'sign-in-customer-login-email']"), 5);
                $login->sendKeys($this->AccountFields['Login']);
            }
            $this->savePageToLogs($selenium);

            $this->logger->debug("set pass");

            try {
                $pass->sendKeys($this->AccountFields['Pass']);
            } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $pass = $this->waitForElementIgnoreStaleness($selenium, WebDriverBy::xpath("//input[@id = 'sign-in-customer-login-password']"), 5);
                $pass->sendKeys($this->AccountFields['Pass']);
            }
            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/www\.acehardware\.com\/user\/login/g.exec( url )) {
                            localStorage.setItem("responseData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
            ');

            $this->logger->debug("click 'Sign in'");
            $this->savePageToLogs($selenium);
            $signInButton->click();

            if (!is_null($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "window.grecaptcha is undefined")]'), 3))
            ) {
                $this->logger->error('window.grecaptcha is undefined, repeating auth'); // it helps

                return false;
            }

            try {
                $selenium->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "desktop-active")]//div[contains(@class, "accountName")]
                    | //span[contains(@class, "validationmessage")]
                    | //div[contains(@class, "alert-message")]/span/p
                    | //a[@data-mz-action="logout"]
                '), 7);
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }

            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: " . $responseData);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData, false);
            }

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "alert-message")]/span/p[contains(text(), "Your email or password is incorrect")]'), 0)) {
                throw new CheckException('Your email or password is incorrect', ACCOUNT_INVALID_PASSWORD);
            }
        } catch (
            NoSuchDriverException
            | StaleElementReferenceException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return true;
    }

    private function waitForElementIgnoreStaleness($selenium, WebDriverBy $by, $timeout = 60, $visible = true)
    {
        try {
            return $selenium->waitForElement($by, $timeout, $visible);
        } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            return $this->waitForElementIgnoreStaleness($selenium, $by, $timeout, $visible);
        }
    }
}
