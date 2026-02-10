<?php

class TAccountCheckerAirmiles extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->setRandomUserAgent(40);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function LoadLoginForm()
    {
        throw new CheckException("The UK Avios Travel Rewards Programme is closing", ACCOUNT_PROVIDER_ERROR);
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        $this->http->GetURL("https://www.avios.com/gb/en_gb/");

        if (!$this->http->ParseForm("loginForm")) {
            $this->http->GetURL("https://www.avios.com/gb/en_gb/my-account/log-into-avios");
        }

        if (!$this->http->ParseForm("loginForm") && !$this->http->FindSingleNode("//li[@class = \"login\"]//span[contains(text(), \"Login\")]")) {
            return $this->checkErrors();
        }

        $xKeys = $this->uniqueStateKeys();

        if (empty($xKeys)) {
            return false;
        }

        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("rememberUserName", "rememberUserName");

        foreach ($xKeys as $xKey) {
            if (isset($xKey['name'], $xKey['value'])) {
                $this->http->SetInputValue($xKey['name'], $xKey['value']);
            }
        }

        return true;
    }

    public function uniqueStateKeys()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
//            $selenium->useFirefox59();
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->disableImages();
            $selenium->usePacFile(false);
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL("https://www.avios.com/gb/en_gb/my-account/log-into-avios");
            $selenium->http->GetURL($this->http->currentUrl());

            $loginInput = $selenium->waitForElement(WebDriverBy::id('username'), 10, false);

            // prvider bug fix
            if (!$loginInput) {
                $selenium->http->GetURL($this->http->currentUrl());
                $loginInput = $selenium->waitForElement(WebDriverBy::id('username'), 10, false);
            }

            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0, false);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$loginInput || !$passwordInput) {
                // form not loaded
                if ($selenium->waitForElement(WebDriverBy::xpath('//li[@class = "login"]//span[contains(text(), "Login")]'), 0)) {
                    throw new CheckRetryNeededException(3);
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // Sign In
            $selenium->driver->executeScript("
                    $('input.btn-login, button.o-button--background-picton-blue').click();window.stop();
                ");

            // uniqueStateKey
            if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "loginForm" or contains(@class, \'m-form-elements__form\')]//input[contains(@name, "X-") and not(contains(@name, "uniqueStateKey"))]'), 5, false)) {
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@name = "loginForm" or contains(@class, \'m-form-elements__form\')]//input[contains(@name, "X-")]', 0, false)) as $index => $xKey) {
                    $xKeys[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value"),
                    ];
                }
                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
            }

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
        // retries
        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckRetryNeededException(3);
        }

        return $xKeys;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Error 500
        if ($this->http->FindPreg("/Error 500--Internal Server Error/ims")
            //# Error 404
            || $this->http->FindPreg("/Error 404--Not Found/ims")
            // Sorry, we’re experiencing technical difficulties right now
            || $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, we’re experiencing technical difficulties right now')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Website is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'website is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Maintenance
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are currently doing essential overnight work on our system.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * The Avios website is temporarily unavailable
         *
         * We are currently experiencing high demand for our website.
         *
         * We are working to get this resolved as soon as possible and are sorry for any inconvenience caused.
         * Please try again shortly.
         */
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'The Avios website is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Avios.com is temporarily unavailable for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Avios.com is temporarily unavailable for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // hard code - bug on the website
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("Current URL: {$currentUrl}");
        // retries
        if ($currentUrl == 'https://www.avios.com/gb/en_gb/page-not-found') {
            throw new CheckRetryNeededException(2, 7);
        }

        if ($currentUrl == 'https://www.avios.comnull/my-account/login-error'
            || $currentUrl == "https://www.avios.comnull/my-account/log-in-details-error") {
            throw new CheckException("Your details have been entered incorrectly. Please check your details and try again.", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been suspended
        if ($currentUrl == 'https://www.avios.comnull/my-account/account-suspended') {
            throw new CheckException("Your account has been suspended.", ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been disabled
        if ($currentUrl == 'https://www.avios.comnull/my-account/account-disabled') {
            throw new CheckException("Your account has been disabled.", ACCOUNT_PROVIDER_ERROR);
        }
        // We just need a few more details to help maintain the security of your account...
        if ($currentUrl == 'https://www.avios.com/us/en_us/my-account/account-status-check' && $this->http->ParseForm("PartialCustomerDataForm")) {
            $this->http->PostForm();

            if ($this->http->currentUrl() == 'https://www.avios.com/eu/en/my-avios/customer-prompt'
                && $this->http->FindSingleNode("//h1[contains(text(), 'We just need a few more details to help maintain the security of your account...')]")) {
                $this->throwProfileUpdateMessageException();
            }
        }
        /*
         * Sorry there seems to be a problem
         *
         * We are doing our best to get things back to normal
         * Please try again later
         *
         * The Avios team
         */
//        $this->http->GetURL("https://www.avios.com/");
        if ($this->http->FindSingleNode("//p[contains(text(), 'We are doing our best to get things back to normal')]")) {
            throw new CheckException("Sorry there seems to be a problem. We are doing our best to get things back to normal. Please try again later. The Avios team.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, we’re experiencing technical difficulties right now
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, we’re experiencing technical difficulties right now")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // refs #10390
        sleep(1);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        //# login successful
        if ($this->loginSuccessful()) {
            return true;
        }

        //# Invalid login or password
        if ($message = $this->http->FindSingleNode("//div[@class = 'error01']/ul")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account expired
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Your Avios account expired')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been disabled
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Your account has been disabled')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Message: "Sorry, but there is a problem with your details."
        if ($message = $this->http->FindSingleNode("//div[@id = 'gen-pg-error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->currentUrl() == 'https://www.avios.com/gb/en_gb/about-us/error') {
            throw new CheckRetryNeededException(3, 7);
        }

        // AccountID: 3448282, 3384399
        if ($this->http->ParseForm(null, 1, true, "//form[contains(@class, 'm-form-elements__form')]")) {
            $xKeys = $this->uniqueStateKeys();

            if (empty($xKeys)) {
                return false;
            }

            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", urlencode($this->AccountFields['Pass'])); // AccountID: 3987980: fixes for %
            $this->http->SetInputValue("rememberMyUsername", "on");

            foreach ($xKeys as $xKey) {
                if (isset($xKey['name'], $xKey['value'])) {
                    $this->http->SetInputValue($xKey['name'], $xKey['value']);
                }
            }

            if (!$this->http->PostForm()) {
                return $this->checkErrors();
            }

            if ($this->http->FindSingleNode("(//a[contains(@href, 'signout')]/@href)[1]")) {
                $this->logger->info('Parse', ['Header' => 2]);

                // We just need a few more details to help maintain the security of your account...
                if ($this->http->currentUrl() == 'https://www.avios.com/eu/en/my-avios/customer-prompt'
                    && $this->http->FindSingleNode("//h1[contains(text(), 'We just need a few more details to help maintain the security of your account...')]")) {
                    $this->throwProfileUpdateMessageException();
                }

                if ($this->http->currentUrl() != 'https://www.avios.com/eu/en/my-avios') {
                    $this->http->GetURL("https://www.avios.com/eu/en/my-avios");
                }

                if ($this->http->currentUrl() == 'https://www.avios.com/eu/en') {
                    $this->http->GetURL("https://www.avios.com/eu/en/my-avios/personal-details");
                }

                if ($this->http->currentUrl() == 'https://www.avios.com/eu/en/signin'
                    && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                    throw new CheckRetryNeededException(2, 7);
                }

                // Account Number
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//input[@id = 'membership-number']/@value"));
                // Balance - Avios
                $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'You have')]/span/text()[1]"));
                // Name
                if ($this->http->currentUrl() != "https://www.avios.com/eu/en/my-avios/personal-details") {
                    $this->http->GetURL("https://www.avios.com/eu/en/my-avios/personal-details");
                }
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//b[contains(text(), 'First name:')]/following-sibling::node()[1]") . " " . $this->http->FindSingleNode("//b[contains(text(), 'Family Name:')]/following-sibling::node()[1]")));

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
                    && $this->http->currentUrl() == 'https://www.avios.com/eu/en/my-avios/customer-prompt'
                    && $this->http->FindSingleNode("//h1[contains(text(), 'We just need a few more details to help maintain the security of your account...')]")) {
                    $this->throwProfileUpdateMessageException();
                }

                return false;
            }// if ($this->http->FindSingleNode("(//a[contains(@href, 'signout')]/@href)[1]"))
            // We seem to be experiencing some difficulties\. Please try again later.
            elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($message = $this->http->FindPreg("/\{\"message\":\"(We seem to be experiencing some difficulties\. Please try again later\.)\"/"))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->ParseForm(null, 1, true, "//form[contains(@class, 'm-form-elements__form')]"))
        $this->checkErrors();

        if ($this->http->currentUrl() == 'https://www.avios.com/my-account/login-process'
            && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(2, 7);
        }

        // hard code
        if ($this->AccountFields['Login'] == 'staffh') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!stristr($this->http->currentUrl(), "https://www.avios.com/gb/en_gb/my-account/your-avios-account")) {
            $this->http->GetURL("https://www.avios.com/gb/en_gb/my-account/your-avios-account");
        }
        // refs #14020
        if ($this->http->FindPreg('/^https:\/\/www\.avios\.com\/\w{2}\/en_\w{2}\/$/', false, $this->http->currentUrl())) {
            $this->logger->notice("Force redirect to my account");
            $myAccountLonk = "my-account/your-avios-account";
            $this->http->GetURL($this->http->currentUrl() . $myAccountLonk);
        }// if ($this->http->FindPreg('/^https:\/\/www\.avios\.com\/\w{2}\/en_\w{2}\/$/', $this->http->currentUrl()))

        //# Web site is not available
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Sorry there seems to be a problem')]")) {
            throw new CheckException('Our website is currently undergoing essential maintenance', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        //# Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//p[contains(text(),'Hello,')]", null, true, "/Hello,\s*(.+)/ims"));
        // Membership Number
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//h3[contains(text(),'Membership Number')]/following-sibling::p"));
        //# Vouchers
        $this->SetProperty("Vouchers", $this->http->FindSingleNode("//p[contains(text(), 'You have')]", null, true, "/You have\s*(\d+)/ims"));

        if (!isset($this->Properties['Vouchers'])) {
            $this->SetProperty("Vouchers", $this->http->FindSingleNode("//span[contains(text(), 'no vouchers')]"));
        }
        //# Balance - Avios
        $this->SetBalance($this->http->FindSingleNode("(//p[contains(@class,'display')])[1]", null, true, "/([\-\d\,\.]+)/ims"));
        // Expiration Date   // refs #4309
        $activity = $this->http->XPath->query("//*[contains(text(), 'Your recent transactions')]/following-sibling::tbody/tr");
        $this->logger->debug("Total {$activity->length} transactions were found");

        for ($i = 0; $i < $activity->length; $i++) {
            $exp = str_replace('/', '.', $this->http->FindSingleNode('td[1]', $activity->item($i)));

            if (strtotime($exp)) {
                //# Last Activity
                $this->SetProperty("LastActivity", str_replace('.', '/', $exp));
                $this->logger->debug("Expiration Date " . date("m/d/Y", strtotime("+3 year", strtotime($exp))) . " - "
                    . var_export(strtotime("+3 year", strtotime($exp)), true));
                //# Expiration Date
                $this->SetExpirationDate(strtotime("+3 year", strtotime($exp)));

                break;
            }//if(strtotime($exp))
        }// for($i = 0; $i < $activity->length; $i++)

        // SubAccounts - Vouchers

        $nodes = $this->http->XPath->query("//caption[contains(text(), 'Your vouchers')]/following-sibling::tr[td[3]]");
        $this->logger->debug("Total {$nodes->length} vouchers were found");

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $code = $this->http->FindSingleNode('td[6]', $nodes->item($i));
                $displayName = $this->http->FindSingleNode('td[1]', $nodes->item($i));
                $exp = $this->http->FindSingleNode('td[3]', $nodes->item($i));
                $exp = $this->ModifyDateFormat($exp);
                $exp = strtotime($exp);
                $balance = $this->http->FindSingleNode('td[position() = 4 and not(contains(text(), " for "))]', $nodes->item($i), true, "/[\d\.\,]+/ims");

                $subAccounts[] = [
                    'Code'           => 'aviosVouchers' . $code . $exp,
                    'DisplayName'    => "Code " . $code . " - " . $displayName,
                    'Balance'        => $balance,
                    'ValidFrom'      => $this->http->FindSingleNode('td[2]', $nodes->item($i)),
                    'Issuer'         => $this->http->FindSingleNode('td[5]', $nodes->item($i)),
                    'ExpirationDate' => $exp,
                ];
            }// for ($i = 0; $i < $nodes->length; $i++)

            if (isset($subAccounts)) {
                //# Set Sub Accounts
                $this->SetProperty("CombineSubAccounts", false);
                $this->logger->debug("Total subAccounts: " . count($subAccounts));
                //# Set SubAccounts Properties
                $this->SetProperty("SubAccounts", $subAccounts);
            }// if(isset($subAccounts))
        }// if ($nodes->length > 0)

        // refs #14020
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->logger->notice("New design");
            // Name
            $this->SetProperty("Name", $this->http->FindSingleNode("//h2[contains(text(),'Hello,')]", null, true, "/Hello,\s*(.+)/ims"));
            // Account Number
            $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[contains(text(),'Member No:')]/strong"));
            // Balance - Avios
            $this->SetBalance($this->http->FindSingleNode("//div[@class = 'total']/p[contains(@class,'display')]", null, true, "/([\-\d\,\.]+)/ims"));
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.avios.com/gb/en_gb/my-account/log-into-avios';
        $arg['PreloadAsImages'] = true;
        $arg['SuccessURL'] = 'https://www.avios.com/gb/en_gb/my-account/your-avios-account';

        return $arg;
    }

//    function IsLoggedIn() {
//        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://www.avios.com/gb/en_gb/my-account/your-avios-account", [], 20);
//        $this->http->RetryCount = 2;
//        if ($this->loginSuccessful())
//            return true;
//
//        return false;
//    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            && !strstr($this->http->currentUrl(), '-error')
            && !strstr($this->http->currentUrl(), 'account-suspended')
            && !strstr($this->http->currentUrl(), 'account-disabled')) {
            return true;
        }

        return false;
    }
}
