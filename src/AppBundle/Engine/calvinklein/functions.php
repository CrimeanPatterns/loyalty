<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCalvinklein extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.calvinklein.us/en/account';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "calvinkleinReward"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        // makes it easier to parse an invalid HTML
        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm("Logon") && $this->http->Response['code'] == 200 && $this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.calvinklein.us/en");
        $this->http->GetURL("https://www.calvinklein.us/LogonForm?myAcctMain=1&catalogId=12101&langId=-1&storeId=10751");

        if (!$this->http->ParseForm("login-form")) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Please verify you are a human")]')) {
                return true;
            }

            if ($this->http->FindSingleNode('//title[contains(text(), "Access to this page has been denied")]')) {
                $this->selenium();

                return false;
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue("loginEmail", $this->AccountFields['Login']);
        $this->http->SetInputValue("loginPassword", $this->AccountFields['Pass']);
        $this->http->SetInputValue("loginRememberMe", "true");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        return $arg;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $logout = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
            $selenium->setProxyMount();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->userAgent = null;

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
//            $selenium->http->GetURL('https://www.calvinklein.us/LogonForm?myAcctMain=1&catalogId=12101&langId=-1&storeId=10751');
            $selenium->http->GetURL(self::REWARDS_PAGE_URL);
            $form = '//div[contains(@class, "modal-content")]//form[@name = "login-form"]';
            $accountLink = $selenium->waitForElement(WebDriverBy::xpath('//div[@aria-label="label.account"]'), 5);

            if (!$accountLink) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $accountLink->click();
            $signInLink = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "acc-popover")]//a[@aria-label="Sign In"]'), 5);

            if (!$signInLink) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $signInLink->click();
            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'loginEmail']"), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("{$form}//input[@name = 'loginPassword']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("{$form}//button[contains(text(), 'Sign In')]"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("something went wrong");

                if ($message = $this->http->FindSingleNode('//p[contains(text(), "CalvinKlein.us is currently down for scheduled maintenance. We apologize for the inconvenience.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $button->click();

            $success = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Personal Information')] | //div[@id = 'fieldErrorMessage'] | //div[@id = 'pageLevelMessage'] | //h2[contains(text(), 'Update Password')]"), 15);
            $this->savePageToLogs($selenium);

            if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                $this->captchaWorkaround($selenium, $key);
                $success = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Personal Information')] | //div[@id = 'fieldErrorMessage'] | //div[@id = 'pageLevelMessage'] | //h2[contains(text(), 'Update Password')]"), 5);
                $this->savePageToLogs($selenium);
            }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))

            if ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Personal Information')]"), 0)) {
                $logout = true;
                $currentUrl = $this->http->currentUrl();
                $this->logger->debug("[Current URL]: {$currentUrl}");

                /*
                if ($currentUrl != self::REWARDS_PAGE_URL) {
                    $selenium->http->GetURL(self::REWARDS_PAGE_URL);
                }
                */
                $success = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Personal Information')]"), 5);

                if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3))) {
                    $this->captchaWorkaround($selenium, $key);
                    $success = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Personal Information')]"), 5);
                }// if (!$success && ($key = $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'g-recaptcha']"), 3)))
            }// if ($selenium->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'My Rewards')]"), 0))
            elseif ($this->http->FindSingleNode("//h2[contains(text(), 'Update Password')]")) {
                $this->throwProfileUpdateMessageException();
            } elseif ($message = $this->http->FindSingleNode('//b[contains(text(), "PLEASE RESET YOUR PASSWORD")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                if ($message = $this->http->FindSingleNode('//div[@id = "pageLevelMessage" and (@style="display: block" or @aria-atomic = "true") and normalize-space(.) != ""] | //div[@id = "fieldErrorMessage"]')) {
                    $this->logger->error("[Error]: {$message}");

                    if (
                        stristr($message, 'The email or password you entered does not match our records. ')
                        || stristr($message, 'Please enter a valid email address.')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    return false;
                }
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            if ($logout) {
                $selenium->saveResponse();
                $selenium->Parse();
                $this->Balance = $selenium->Balance;
                $this->ErrorCode = $selenium->ErrorCode;
                $this->ErrorMessage = $selenium->ErrorMessage;
                $this->Properties = $selenium->Properties;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return $logout;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "Please verify you are a human")]')
                || $this->http->BodyContains('<div class="px-captcha-error-header">', false)
            ) {
                $this->selenium();

                return false;
            }

            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();
        $message = $response->error[0] ?? null;

        // Invalid credentials
        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                stristr($message, 'The email or password you entered does not match our records. ')
                || stristr($message, 'Invalid login or password. Remember that password is case-sensitive. Please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $success = $response->success ?? null;
        $isMigratedCustomerFirstLogin = $response->isMigratedCustomerFirstLogin ?? null;

        // Update Password
        if ($isMigratedCustomerFirstLogin === true) {
            $this->throwProfileUpdateMessageException();
        }

        if ($success === true) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }
        // An error occurred that prevented this page from being displayed. (AccountID: 4367540)
        if ($this->http->FindSingleNode("//div[contains(text(), 'An error occurred that prevented this page from being displayed.')]")
            && $this->AccountFields['Login'] == 'fyhong22@hotmail.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//div[span[contains(text(), "Name")]]/following-sibling::div/span[contains(@class, "personal-info-signin-text")]')));

        // Balance - You currently have ... points
        if (!$this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'rewardsPoint')]", null, true, "/You currently have ([\d\.\,]+) point/"))) {
            /*
            if ($this->http->FindSingleNode("//p[contains(text(), 'You are not currently enrolled')]")
                && $this->http->FindSingleNode("//button[contains(text(), 'Join')] | //a[contains(text(), 'Join')]")) {
                $this->SetWarning(self::NOT_MEMBER_MSG);

                return;
            }
            // You have not verified your Loyalty Account
            if (($message = $this->http->FindSingleNode("//p[contains(text(), 'You have not verified your Loyalty Account')]"))
                && $this->http->FindSingleNode("//a[contains(text(), 'Verify')]")) {
                $this->SetWarning($message);

                return;
            }
            */
        }// if (!$this->SetBalance($this->http->FindSingleNode("//div[@class = 'rewardsAndPoints']/span")))
        // You are ... points away from your next ... reward.
        $this->SetProperty("NeededToNextReward", $this->http->FindSingleNode("//div[contains(@class, 'nextRewards')]", null, true, "/You are (\d+) points? away/"));

        $this->http->GetURL("https://www.calvinklein.us/en/rewards");
        $rewards = $this->http->XPath->query("//div[contains(@class, 'rewards-available-card')]");
        $this->logger->debug("Total {$rewards->length} rewards were found");

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode(".//p[contains(@class, 'rewards-available-card-offer')]", $reward);

            $this->AddSubAccount([
                'Code'        => 'calvinkleinReward' . md5($displayName),
                'DisplayName' => $displayName,
                'Balance'     => null,
            ]);
        }// foreach ($rewards as $reward)
    }

    protected function captchaWorkaround($selenium, $key)
    {
        $this->DebugInfo = 'reCAPTCHA checkbox';
        $captcha = $this->parseReCaptcha($key->getAttribute('data-sitekey'));

        if ($captcha === false) {
            $this->logger->error("failed to recognize captcha");

            return false;
        }
        $selenium->driver->executeScript('window.handleCaptcha(' . json_encode($captcha) . ')');

        return true;
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        // key from https://captcha.perimeterx.net/PXnrdalolX/captcha.js?a=c&u=c7349500-2a90-11e9-a4a6-93984e516e46&v=&m=0
        //        $key = '6Lcj-R8TAAAAABs3FrRPuQhLMbp5QrHsHufzLf7b';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key || !$this->http->FindSingleNode("//script[contains(@src, 'https://captcha.perimeterx.net/PXnrdalolX/captcha')]/@src")) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our store is temporarily closed for maintenance.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Our store is temporarily closed for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // CalvinKlein.us is currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'CalvinKlein.us is currently down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
            return true;
        }

        return false;
    }
}
