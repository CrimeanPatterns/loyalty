<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEpoll extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.epollsurveys.com/epoll/clients/index.htm";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->FilterHTML = false;
        $this->KeepState = true;

        $this->UseSelenium();

        /*
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        */
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->http->setUserAgent(HttpBrowser::FIREFOX_USER_AGENT);
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
//        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.epollsurveys.com/epoll/clients/index.htm");

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Log In")]
            | //span[contains(text(), "We are checking your browser...")]
            | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe
            | //div[contains(@class, "cf-turnstile-wrapper")] | //div[contains(@style, "margin: 0px; padding: 0px;")]
        '), 10);

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Log In")]
                | //span[contains(text(), "We are checking your browser...")]
            '), 10);
            $this->saveResponse();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log In")]'), 0);
        $this->saveResponse();

        if (!$login) {
            if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "We are checking your browser...")]'), 0)) {
                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }

        $login->click();

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "emailAddress"]'), 10);
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log In")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return $this->checkErrors();
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();
        /*
        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('emailAddress', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# E-Poll is currently performing maintenance on its servers
        if ($message = $this->http->FindPreg('/is currently performing maintenance on its\s*servers/ims')) {
            throw new CheckException("E-Poll is currently performing maintenance on its servers. Please check back shortly.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindPreg("/(E-Poll is currently performing maintenance on its\s*servers\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error: only one membership per residence allowed
        if ($message = $this->http->FindPreg("/(Error\: only one membership per residence allowed)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * There appears to be a problem with the page you have requested.
         * Some pages on the E-Poll website require you to be logged in to access them.
         * Please return to the home page and login.
         */
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'There appears to be a problem with the page you have requested.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        */

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Logout")]
            | //span[contains(text(), "We are checking your browser...")]
            | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe
        '), 10);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Logout")]
                | //span[contains(text(), "We are checking your browser...")]
            '), 10);
            $this->saveResponse();
        }

        // one more submit if nothing happens
        if (!($this->http->FindSingleNode("//a[contains(text(), 'Logout')]") || strlen($this->AccountFields['Pass']) < 5 || $this->http->FindPreg("/\\$\(\"#error\"\)\.html\(\"([^\"]+)\"\);/"))) {
            $this->driver->executeScript('let f = document.forms.loginform; if (f) f.submit();');
            $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Logout")]
            | //span[contains(text(), "We are checking your browser...")]
        '), 10);
            $this->saveResponse();
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(text(), 'Logout')]")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindPreg("/\\$\(\"#error\"\)\.html\(\"([^\"]+)\"\);/")) {
            $this->logger->error($message);
            // Username or Password incorrect
            if (strstr($message, 'Username or Password incorrect')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }// if ($message = $this->http->FindPreg("/\\$\(\"#error\"\)\.html\(\"([\"]+)\"\);/"))

        // AccountID: 2534659
        if (strlen($this->AccountFields['Pass']) < 5) {
            throw new CheckException("Username or Password incorrect", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "We are checking your browser...")]'), 0)) {
            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $this->http->GetURL("https://www.epollsurveys.com/epoll/clients/shopping/rewardsList.htm?action=getCategories");
        } catch (UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }
        $this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'Points in cart')] | //input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe"), 5);

        if ($this->cloudFlareworkaround($this)) {
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath("//li[contains(text(), 'Points in cart')]"), 5);
        }

        $this->saveResponse();
        // Balance - you have ... points left to spend
        $this->SetBalance($this->http->FindPreg("/you have (\S+) points left to spend/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[contains(@class, \"list-group-item-info\")]/strong")));
        // Total: Points in cart
        $this->SetProperty("TotalPoints", $this->http->FindSingleNode("//li[contains(text(), 'Points in cart')]", null, true, "/([\d\.\,]+) Points in cart/ims"));
    }
}
