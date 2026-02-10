<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBreezeSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.flybreeze.com/loyalty';

    private $client_id = 'iheO83aDJGfnTD14lCZVEV3MXmDWblo9';
    private $auth0Client = 'eyJuYW1lIjoiYXV0aDAtc3BhLWpzIiwidmVyc2lvbiI6IjEuMTguMCJ9';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'FlightCredits')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        $this->http->SetProxy($this->proxyReCaptcha());

        // this is chrome on macBook, NOT serever Puppeteer
        $this->useChromePuppeteer(SeleniumFinderRequest::CHROME_PUPPETEER_103);
        $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
        $this->seleniumOptions->addHideSeleniumExtension = false;

//        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();
//        $this->http->GetURL("https://auth.flybreeze.com/authorize?audience=https%3A%2F%2Fapi.flybreeze.com&client_id={$this->client_id}&redirect_uri=https%3A%2F%2Fwww.flybreeze.com&mode=login&scope=openid%20profile%20email%20offline_access&response_type=code&response_mode=query&state=aVk1UWpnLWRQRkwzckVhdG5TdjVCT3MwLXNKUmNqWFVsSTc2ZWcwVmVvLg%3D%3D&nonce=SGs5Vmc2UlpOb1RTWGVuYllqTVhZLWRhOXRELXNWa0JMfnJ6aGpPaEpJaA%3D%3D&code_challenge=l2CD1eIiUWQz0J8Q486VnslUoEcC6z2aovzIdkkDpRI&code_challenge_method=S256&auth0Client={$this->auth0Client}");
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        $this->waitForElement(WebDriverBy::xpath('//button[contains(@class,"login") and contains(text(), "Login")] | //input[@id = "username"] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe'), 5);
        $this->saveResponse();

        try {
            if ($this->clickCloudFlareCheckboxByMouse($this)) {
                $this->saveResponse();
            }
        } catch (NoSuchElementException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 5);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="action"]'), 0);

        if (!isset($loginInput, $passInput, $btn)) {
            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")]'), 0)) {
                return true;
            }

            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")] | //span[@class = "ulp-input-error-message"]'), 15);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")] | //span[@class = "ulp-input-error-message"]'), 15);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            $this->saveResponse();
        }

        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")]'), 0)) {
            return true;
        }

        $message = $this->http->FindSingleNode('//span[@class = "ulp-input-error-message"]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "Wrong email or password")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.flybreeze.com/loyalty');
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]'), 5);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")]'), 5);
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "tc-navbar-user-name")]')));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode('//p[contains(text(), "Account") and preceding-sibling::p[contains(text(), "BreezePoint")]]', null, true, "/:\s*(.+)/"));
        // Balance - BreezePoints Available
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Account")]/preceding-sibling::p[contains(text(), "BreezePoint")]', null, true, "/(.+) BreezePoint/ims"));
        // Balance worth
        $this->SetProperty('BalanceWorth', $this->http->FindSingleNode('//div[contains(@class, "tc-navbar-user-name")]/following-sibling::div[contains(@class, "tc-navbar-breeze-points")]'));

        // Flight Credits
        $flightCredits = $this->http->FindSingleNode('//p[contains(text(), "Account")]/preceding-sibling::p[contains(text(), "available")]', null, true, "/(.+) available/ims");

        if ($flightCredits) {
            $this->AddSubAccount([
                'Code'        => 'FlightCredits',
                'DisplayName' => 'Flight Credits',
                'Balance'     => $flightCredits,
                'Number'      => $this->http->FindSingleNode('//p[contains(text(), "Account") and preceding-sibling::p[contains(text(), "available")]]', null, true, "/:\s*(.+)/"),
            ]);
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logoutItemXpath = '//button[contains(@class, "tc-navbar-user-logout-btn")]';
        $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 5);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath($logoutItemXpath), 5);
            $this->saveResponse();
        }

        if ($this->http->FindSingleNode($logoutItemXpath)) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
