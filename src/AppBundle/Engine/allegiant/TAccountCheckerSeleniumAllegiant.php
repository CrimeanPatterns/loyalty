<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerSeleniumAllegiant extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private HttpBrowser $browser;
    private $allegiant;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        /*
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        */
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        $this->disableImages();
        /*
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        */
        $this->usePacFile(false);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.allegiantair.com/');

        $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@id = 'login-email'] | //span[@data-hook='header-user-menu-item_log-in'] | //span[contains(text(), 'Your connection was interrupted')]"), 10);
        $this->saveResponse();

        if ($this->cloudFlareworkaround($this)) {
            $this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //input[@id = 'login-email'] | //span[@data-hook='header-user-menu-item_log-in'] | //span[contains(text(), 'Your connection was interrupted')]"), 10);
            $this->saveResponse();
        }

        $this->driver->executeScript('try { document.querySelector(\'button[data-hook="overlay-merchandise_ice-pop_close"]\').click() } catch (e) {}');

        if ($acceptCookiesBtn = $this->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 4)) {
            $acceptCookiesBtn->click();
        }

        if ($closeAdBtn = $this->waitForElement(WebDriverBy::xpath('//button[@data-hook="overlay-merchandise_ice-pop_close"]'), 2)) {
            $closeAdBtn->click();
        }
        $showFormBtn = $this->waitForElement(WebDriverBy::xpath('//span[@data-hook="header-user-menu-item_log-in"]'), 0);
        $this->saveResponse();

        if (!$showFormBtn) {
            if (is_null($showFormBtn) && !is_null($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Points Available :")]'), 0))) {
                return true;
            }

            // cloudflare issiue
            if ($this->waitForElement(WebDriverBy::xpath("//input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //span[contains(text(), 'Your connection was interrupted')]"), 0)) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        $showFormBtn->click();

        $login = $this->waitForElement(WebDriverBy::id('login-email'), 7);
        $pwd = $this->waitForElement(WebDriverBy::id('login-password'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@data-hook = "home-login_submit-button_continue"]'), 0);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();

            return $this->checkErrors();
        }
        sleep(2); // prevent ElementNotInteractableException
        $this->driver->executeScript('let remMe = document.querySelector(`input[data-hook="home-login_remember-me_rememberMe"]`); if (remMe != null) remMe.checked = true;');
        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // The Allegiant website is currently unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The Allegiant website is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/An error occurred while processing your request\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Update your password")]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            return true;
        }

        $this->saveResponse();
        $errorEl = $this->waitForElement(WebDriverBy::xpath('//em[starts-with(@class, "FieldError__ErrorText")]'), 0);

        if ($errorEl && $error = $errorEl->getText()) {
            $this->logger->error($error);

            if (stripos($error, 'Wrong email or password') !== false
                || stripos($error, 'Please enter a valid Email Address') !== false
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = "[Error]: $error";

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Update your password")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.allegiantair.com/my-profile');
        $this->waitForElement(WebDriverBy::xpath('//span[@data-hook = "user-info-link-loyalty-points-and-money"]/span'), 3);
        $this->savePageToLogs($this);
        // Hello, %username%
        // X,XXX Points = $XX.XX
        $this->SetBalance(str_replace(',', '', $this->http->FindSingleNode('//span[@data-hook = "user-info-link-loyalty-points-and-money"]/span', null, true, '/([\d,]+) Points/')));
        $this->SetProperty('PointsWorth', $this->http->FindSingleNode('//span[@data-hook = "user-info-link-loyalty-points-and-money"]/span', null, true, '/Points = ([$\d.]+)/'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@data-hook = "dashboard-summary-full-name"]/span')));
        // Allways® #
        $this->SetProperty('Number', $this->http->FindSingleNode('//span[@data-hook = "dashboard-summary-my-allegiant-id"]/span', null, true, '/#(\d+)/'));
        // Total Available Spend
        $this->SetProperty('TotalVouchers', $this->http->FindSingleNode('//span[@data-hook="dashboard-total-available-spend-amount"]'));

        $this->http->GetURL('https://www.allegiantair.com/my-profile/my-account');
        $showVouchersBtn = $this->waitForElement(WebDriverBy::xpath('//button[starts-with(@class, "Tab__TabButton")]/span[text() = "Vouchers"]'), 3);
        $this->savePageToLogs($this);

        // Member Since
        $this->SetProperty('Since', $this->http->FindSingleNode('//span[@data-hook = "account-member-since"]'));

        if (!$showVouchersBtn) {
            return;
        }
        $showVouchersBtn->click();

        $this->waitForElement(WebDriverBy::xpath('//div[@data-hook = "display-voucher-row"] | //span[text() = "You currently have no vouchers available."]'), 4);
        $this->savePageToLogs($this);

        $vouchers = $this->http->XPath->query('//div[@data-hook = "display-voucher-row"]');

        foreach ($vouchers as $voucher) {
            $number = $this->http->FindSingleNode('div/div/div/span[@data-hook = "display-voucher-number"]', $voucher);
            $balance = $this->http->FindSingleNode('div/div/span[@data-hook = "display-voucher-balance"]', $voucher);
            $issuedDate = $this->http->FindSingleNode('div/div/span[@data-hook = "display-voucher-issue-date"]', $voucher);

            if (isset($number, $balance, $issuedDate) && $exp = strtotime($this->http->FindSingleNode('div/div/span[@data-hook = "display-voucher-expiration-date"]', $voucher))) {
                $this->AddSubAccount([
                    'Code'           => 'allegiantVouchers' . preg_filter('/\W/', '', $number),
                    'DisplayName'    => 'Voucher ' . $number,
                    'Balance'        => $balance,
                    'IssuedDate'     => $issuedDate,
                    'ExpirationDate' => $exp,
                ]);
            }
        }
    }

    public function withCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
             $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
//        $this->browser->SetProxy($this->http->GetProxy());
//        $this->browser->GetURL($this->http->currentUrl());
        $this->logger->debug($this->http->currentUrl());
    }

    public function ParseItineraries(): array
    {
        $this->http->GetURL('https://www.allegiantair.com/my-profile/my-trips');
        $tripsContainer = $this->waitForElement(WebDriverBy::xpath('//a[@data-hook = "upcoming-trip-manage-trip-link"] | //div[@data-hook = "no-trips-fallback-section"]/span'), 5);
        $this->savePageToLogs($this);

        if ($tripsContainer && $tripsContainer->getText() == 'No upcoming trips.') {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }
        $manageTripLinks = $this->http->FindNodes('//a[@data-hook = "upcoming-trip-manage-trip-link"]/@href');
        $this->logger->info('total ' . count($manageTripLinks) . ' itineraries were found');

        $this->allegiant = $this->getAllegiant();
        foreach ($manageTripLinks as $link) {
            //$confirmation = $this->http->FindPreg('/orderNumber=(\w+)/', false, $link);
            //$firstName = $this->http->FindPreg('/firstName=(\w+)&/', false, $link);
            //$lastName = $this->http->FindPreg('/lastName=(\w+)&/', false, $link);
            $this->http->GetURL("https://www.allegiantair.com$link");
            $loadingStartTime = time();
            sleep(3);
            $loadingSuccess = $this->waitForElement(WebDriverBy::xpath('//span[@data-hook="order-item-flight-info_onward-depart-date-time"]'), 7, false);
            $this->increaseTimeLimit(time() - $loadingStartTime);
            if (!$loadingSuccess) {
                $this->logger->error('page not loaded');
                return [];
            }
            $this->saveResponse();
            $this->allegiant->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
            //$this->allegiant->SaveResponse();
            $this->allegiant->parseItinerary();
            $this->saveResponse();
        }

        return [];
    }

    protected function getAllegiant()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->allegiant)) {
            $this->allegiant = new TAccountCheckerAllegiant();
            $this->allegiant->http = new HttpBrowser("none", new CurlDriver());
            $this->allegiant->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->allegiant->http);
            $this->allegiant->AccountFields = $this->AccountFields;
            $this->allegiant->itinerariesMaster = $this->itinerariesMaster;
            $this->allegiant->http->LogHeaders = $this->http->LogHeaders;
            $this->allegiant->ParseIts = $this->ParseIts;
            $this->allegiant->ParsePastIts = $this->ParsePastIts;

//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->allegiant->http->setDefaultHeader($header, $value);
            }

            $this->allegiant->globalLogger = $this->globalLogger;
            $this->allegiant->logger = $this->logger;
            $this->allegiant->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->allegiant->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->allegiant;
    }

    private function loginSuccessful(): bool
    {
        $logout = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Points Available :")]'), 5);
        $this->saveResponse();

        return !is_null($logout);
    }
}
