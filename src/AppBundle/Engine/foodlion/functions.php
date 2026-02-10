<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\foodlion\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFoodlion extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);
        $this->setProxyNetNut();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
//        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email/Password combination incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->driver->manage()->window()->maximize();

        $this->http->GetURL("https://foodlion.com/account");

        $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")] | //input[@id = "login-username"] | //h4[contains(text(), "Security Block in Place")]'), 5);
        sleep(3);
        $btnToForm = $this->driver->executeScript('let btn = document.getElementsByClassName("btn btn--primary px-16 mb-6 block mx-auto"); if (btn.length > 0) {btn[0].click(); return true;} return false;')
            ? 'clicked'
            : 'not found';
        $this->logger->debug('"Sign In" button: ' . $btnToForm);

        $this->driver->executeScript("var popup = document.getElementsByClassName('optly-modal'); if (popup.length) popup[0].style.display = 'none';");

        if (
            $btnToForm == 'not found'
            && ($signInButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(normalize-space(), "Sign In")]'), 0))
        ) {
            $this->saveResponse();

            try {
                $signInButton->click();
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
        }

        $login = $this->waitForElement(WebDriverBy::id('login-username'), 15);
        $this->saveResponse();

        // to long loading
        if (
            !$login
            && ($signInButton = $this->waitForElement(WebDriverBy::xpath('//button[@id="nav-account-menu-sign-in"]'), 0))
        ) {
            $this->DebugInfo = "too long loading";
            $this->saveResponse();

            throw new CheckRetryNeededException(3, 0);
        }

        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@id = "current-password" or @id = "SiteTransLogin-password-password"]'), 2); // takes some time occasionally, so timeout is not 0
        $btn = $this->waitForElement(WebDriverBy::id('sign-in-button'), 2);

        if (!isset($login, $btn)) {
            $this->saveResponse();

            $this->restartIfBlocked();

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);

        if ($pwd) {
            $pwd->sendKeys($this->AccountFields['Pass']);
        } else {
            $this->saveResponse();
            $this->driver->executeScript("
            document.querySelector('input[id = \"LoginForm-password-password\"], input[id = \"current-password\"]').value = \"{$this->AccountFields['Pass']}\";
            
            function createNewEvent(eventName) {
                var event;
                if (typeof(Event) === \"function\") {
                    event = new Event(eventName);
                } else {
                    event = document.createEvent(\"Event\");
                    event.initEvent(eventName, true, true);
                }
                return event;
            };
            
            var pass = document.querySelector('input[id = \"current-password\"]');
            document.querySelector(\"select.gig-tfa-phone-register-select\").dispatchEvent(createNewEvent('change'));
        ");
        }
        $this->saveResponse();
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // An error occurred
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Sorry, an error occurred while processing your request')]
                | //h4[contains(text(), 'Site Temporarily Down')]
                | //h4[contains(text(), 'Temporarily Down for Maintenance')]
        ")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is unavailable.
        if ($message = $this->http->FindPreg("/(The service is unavailable\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//button[@id = "header-account-button" and not(contains(text(), "Sign In"))] | //div[contains(@class, "message-box--error")]/p | //iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha/")] | //button[@id = "alert-button_primary-button"] | //p[contains(text(), "We sent a secure code to your email address")]'), 10);
        $this->saveResponse();

        if ($question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We sent a secure code to your email address")]'), 0)) {
            $email = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We sent a secure code to your email address")]/following-sibling::p[1]/strong'), 0);

            if (!$question || !$email) {
                $this->saveResponse();

                return false;
            }

            $question = Html::cleanXMLValue($question->getText() . " " . $email->getText());

            if (!QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("need to check QuestionAnalyzer");
            }

            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        if ($this->finalRedirect()) {
            return true;
        }

        if ($errorEl = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "message-box--error")]/p'), 0)) {
            $error = $errorEl->getText();
            $this->logger->error($error);

            if (stripos($error, 'The sign in information you entered does not match our records') !== false) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            $this->DebugInfo = $error;

            return false;
        }

        $this->saveResponse();

        return $this->checkErrors();

        if ($this->http->FindPreg('/"Unexpected system error\. Please try again\."/')) {
            throw new CheckException('Email/Password combination incorrect. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($this->http->Response['code']) && $this->http->Response['code'] != 200) {
            if (strstr($this->http->Error, 'Network error 28 - Operation time')) {
                $this->DebugInfo = "need to upd sensor_data";
            }

            return false;
        }

        $this->http->GetURL('https://www.foodlion.com/libs/granite/csrf/token.json');

        $this->http->GetURL("https://www.foodlion.com/");
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@aria-label, "One time password input")]'), 5);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "sign-in-button-otp"]'), 0);
        $this->saveResponse();

        if (!$questionInput || !$button) {
            return false;
        }

        $this->logger->debug("entering code...");
        $elements = $this->driver->findElements(WebDriverBy::xpath('//input[contains(@aria-label, "One time password input")]'));

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$answer[$key]}");
            $element->click();
            $element->sendKeys($answer[$key]);
            $this->saveResponse();
        }

        $button = $this->waitForElement(WebDriverBy::xpath('//button[@id = "sign-in-button-otp" and not(@disabled)]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        $this->waitForElement(WebDriverBy::xpath('//button[@id = "header-account-button" and not(contains(text(), "Sign In"))] | //div[contains(@class, "message-box--error")]/p | //iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha/")] | //button[@id = "alert-button_primary-button"]'), 10);
        $this->saveResponse();

        $this->finalRedirect();

        return true;
    }

    public function Parse(): void
    {
        if (!$rewardsBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountmenu--rewards--mainText"]'), 3)) {
            $this->saveResponse();

            return;
        }
        $rewardsBtn->click(); // go to "Rewards & Programs"
        $balanceEl = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "rewards-row_points")]'), 2);
        $this->saveResponse();
        $this->logCurrentUrl();
        $balanceEl = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "rewards-row_points")]/span'), 2);
        $this->saveResponse();
        // Shop & Earn
        // $0.00 Rewards Balance
        $this->SetBalance($balanceEl ? $balanceEl->getText() : $this->http->FindSingleNode('//p[contains(@class, "rewards-row_points")]/span'));

        $totalSavingsBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountmenu--savingshistory--mainText"]'), 5);
        $this->logCurrentUrl();
        $totalSavingsBtn->click(); // go to "Savings History"
        $savingsSummary = $this->waitForElement(WebDriverBy::xpath('//h2[@class = "savings-history-summary_total"]'), 5);
        $this->logCurrentUrl();
        $this->saveResponse();
        // Congrats! You’ve saved:
        $this->SetProperty('SavingYTD', $savingsSummary ? $savingsSummary->getText() : null);

        // go to "User Information"
        $this->goToUserInformation();

        // go to "MVP Card Number & Alt. ID"
        if (!$cardNumberBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountuserloyaltymenu--userloyaltycard--mainText"]'), 1)) {
            $this->saveResponse();
        }
        $this->logCurrentUrl();

        if ($cardNumberBtn) {
            $cardNumberBtn->click();
        } else {
            $this->driver->executeScript("try { document.getElementById('accountuserloyaltymenu--userloyaltycard').click(); } catch(e) {}");
        }

        $cardNumberInput = $this->waitForElement(WebDriverBy::id('loyalty-card'), 2);
        $this->saveResponse();

        if ($cardNumberInput) {
            // Card Number
            $this->SetProperty('Number', $cardNumberInput->getAttribute('value'));
        }

        // go to "User Information"
        $this->goToUserInformation();

        // go to "MVP Card Mailing Address"
        if (!$mailingAddressBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountuserloyaltymenu--userloyaltymailing--mainText"]'), 1)) {
            $this->saveResponse();

            return;
        }
        $this->logCurrentUrl();

        try {
            $mailingAddressBtn->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        sleep(2);
        $this->saveResponse();

        try {
            // Name
            $firstName = $this->driver->findElement(WebDriverBy::id('firstName-input'))->getAttribute('value');
            $lastName = $this->driver->findElement(WebDriverBy::id('lastName-input'))->getAttribute('value');
            $this->SetProperty('Name', beautifulName("$firstName $lastName"));
        } catch (NoSuchElementException | \Facebook\WebDriver\Exception\NoSuchElementException $e) {
            $this->logger->error('Name not found');
        }
    }

    private function finalRedirect()
    {
        $this->logger->notice(__METHOD__);

        if ($okBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "alert-button_primary-button"]'), 0)) {
            $okBtn->click();
            $this->waitForElement(WebDriverBy::xpath('//button[@id = "header-account-button" and not(contains(text(), "Sign In"))] | //div[contains(@class, "message-box--error")]/p | //iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha/")] | //button[@id = "alert-button_primary-button"]'), 10);
            $this->saveResponse();
        }

        $this->restartIfBlocked();

        if ($profileBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "header-account-button" and not(contains(text(), "Sign In"))]'), 0)) {
            $profileBtn->click();

            if ($profileBtn2 = $this->waitForElement(WebDriverBy::id('nav-account-menu-my-account'), 1)) {
                $profileBtn2->click();

                return true;
            }
        }

        return false;
    }

    private function goToUserInformation()
    {
        $this->logger->notice(__METHOD__);

        $userBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountmenu--user--mainText"]'), 0);
        $this->saveResponse();
        $this->logCurrentUrl();

        try {
            $userBtn->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        // go to "MVP Card Information"
        $cardInformationBtn = $this->waitForElement(WebDriverBy::xpath('//span[@id = "accountusermenu--userloyalty--mainText"]'), 3);
        $this->saveResponse();
        $this->logCurrentUrl();

        if (!$cardInformationBtn) {
            return;
        }

        try {
            $cardInformationBtn->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
    }

    private function logCurrentUrl()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
    }

    private function restartIfBlocked()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//h4[contains(text(), "Security Block in Place")]')
            || $this->waitForElement(WebDriverBy::xpath('//iframe[starts-with(@src, "https://geo.captcha-delivery.com/captcha/")]'), 0)
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
    }
}
