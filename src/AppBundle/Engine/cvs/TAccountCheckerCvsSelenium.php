<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerCvsSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const START_URL = "https://www.cvs.com/account/login-responsive.jsp";

    private $fromIsLoggedIn = false;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        if ($this->attempt == 1) {
            $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
        }

        if (isset($this->State['User-Agent']) && $this->attempt != 2) {
            $this->http->setUserAgent($this->State['User-Agent']);
        } else {
            $this->http->setRandomUserAgent();
            $this->State['User-Agent'] = $this->http->userAgent;
        }

        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $resolutions = [
            [1280, 768],
        ];

        if (!isset($this->State['Resolution']) || $this->attempt > 1) {
            $this->logger->notice("set new resolution");
            $resolution = $resolutions[array_rand($resolutions)];
            $this->State['Resolution'] = $resolution;
        } else {
            $this->logger->notice("get resolution from State");
            $resolution = $this->State['Resolution'];
            $this->logger->notice("restored resolution: " . join('x', $resolution));
        }
        $this->setScreenResolution($resolution);

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->setProxyGoProxies();
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        $this->disableImages();

        $this->http->saveScreenshots = true;

        $this->seleniumOptions->recordRequests = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL("https://www.cvs.com/home.jsp?_requestid=2236", [], 20);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("IsLoggedIn -> exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            } finally {
                $this->logger->debug("IsLoggedIn -> finally");
            }
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }
        $this->http->RetryCount = 0;

        if ($this->loginSuccessful()) {
            $this->fromIsLoggedIn = true;

            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        try {
            $this->http->GetURL("https://www.cvs.com/account/login?icid=cvsheader:signin&screenname=/");
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        $this->waitForElement(WebDriverBy::xpath('
            //input[@id = "emailField"]
            | //p[contains(text(), "Your traffic behavior has been determined cause harm to this website.")]
            | //iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src
            | //p[contains(., "is not available to customers or patients who are located outside of the United States or U.S. territories.")]
        '), 10);
        // login
        $loginInput = $this->waitForElement(WebDriverBy::id('emailField'), 0);
        $contBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$contBtn) {
            if ($this->http->FindSingleNode('//iframe[contains(@src, "_Incapsula_Resource") and @id = "main-iframe"]/@src | //p[contains(., "is not available to customers or patients who are located outside of the United States or U.S. territories.")]')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        if ($rememberMe = $this->waitForElement(WebDriverBy::xpath('//label[contains(., "Remember me")]'), 0)) {
            $rememberMe->click();
        }
        $this->saveResponse();

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 1000);
        $mover->steps = rand(10, 20);
        $mover->moveToElement($loginInput);
        $loginInput->click();
        $mover->click();
        $loginInput->clear();
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);

        try {
            $mover->moveToElement($contBtn);
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }

        $contBtn->click();

        // wait for loading
        $loadingSuccess = $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//cvs-loading-spinner'), 0));
        }, 30);
        $this->saveResponse();

        try {
            $this->logger->debug("find errors...");
            $this->saveResponse();
            $error = $this->driver->executeScript("
                return document.querySelector('#emailField__error').shadowRoot.querySelector('.form-error-text span').innerText
            ");
        } catch (Facebook\WebDriver\Exception\JavascriptErrorException | UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $error = null;
        }

        $this->logger->debug("[Error]: {$error}");

        $message = $this->waitForElement(WebDriverBy::xpath("//cvs-password-prompt | //p[contains(text(), 'Your email address does not match our records.')]"), 0);
        $error = $message ? $message->getText() : $error;

        if ($error) {
            // It's time to reset your password.
            if (strstr($error, "It's time to reset your password.")) {
                throw new CheckException("It's time to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($error, "Your email address does not match our records")
                || strstr($error, "Enter a valid email address")
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if (!$loadingSuccess) {
            return $this->checkErrors();
        }

        // password
        $passwordInput = $this->waitForElement(WebDriverBy::id('cvs-password-field-input'), 3);
        $signInBtn = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Sign in")] | //button[contains(text(), "Sign in")]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$passwordInput || !$signInBtn) {
            /*$this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "errorContainer")]
                | //div[contains(@class, "error-container")]
                | //h1[contains(text(), "It\'s time to reset your password.")]
            '), 0);*/
            $this->saveResponse();

            try {
                $this->logger->debug("find errors...");
                $this->saveResponse();
                $error = $this->driver->executeScript("
                    return document.querySelector('#emailField__error').shadowRoot.querySelector('.form-error-text span').innerText
                ");
            } catch (Facebook\WebDriver\Exception\JavascriptErrorException | UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $error = null;
            }

            $this->logger->debug("[Error]: {$error}");

            if ($message = $this->http->FindSingleNode('//div[contains(@class, "errorContainer") or contains(@class, "error-container")]') ?? $error) {
                $this->logger->error("[Login Error]: {$message}");

                if (
                    strstr($message, 'An unexpected error occured')
                    || strstr($message, 'An unexpected error occurred')
                ) {
//                throw new CheckException("An unexpected error occurred. Please try again", ACCOUNT_PROVIDER_ERROR);
                    throw new CheckRetryNeededException(2, 0, "An unexpected error occurred. Please try again");
                }

                if ($message == "Couldn't sign In Enter a valid email address") {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, 'Account not found Your email address does not match our records')) {
                    throw new CheckException("Account not found. Your email address does not match our records. Make sure you're typing your email address correctly.", ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            if ($message = $this->http->FindSingleNode('//h1[contains(text(), "It\'s time to reset your password.")]')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Set up Rx access with a new password")]')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($this->http->FindSingleNode('//div[contains(text(), "To get started, we\'ll send a 6-digit verification code to")]')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $signInBtn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "user-profile-aerclub-card")]
            | //div[@id = "scroll_messages" and normalize-space(text()) != ""]
            | //div[contains(@class, "errorContainer")]
            | //div[contains(@class, "error-container")]
            | //h1[contains(text(), "It\'s time to reset your password.")]
            | //p[contains(text(), "Enter your date of birth to access your account.")]
            | //h1/span[contains(text(), "Verify your date")]
            | //div[div[contains(text(), "A 6-digit verification code was sen")]]
            | //div[contains(text(), "Email me at")]
            | //h4[@class = "alert-header" and not(contains(text(), "Forgot password?"))]
            | //p[contains(@class, "alert-description")]
        '), 10);
        $this->saveResponse();

        if ($emailMe = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Email me at")]'), 0)) {
            $this->logger->notice("send code");
            $emailMe->click();
            $sendCode = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Send Code")]'), 0);

            if (!$sendCode) {
                $this->logger->error("something went wrong");
                $this->saveResponse();

                return false;
            }

            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $sendCode->click();

            $this->waitForElement(WebDriverBy::xpath('//div[div[contains(text(), "A 6-digit verification code was sen")]]'), 10);
            $this->saveResponse();
        }

        if ($this->parseQuestion()) {
            if ($this->loginSuccessful()) {
                $this->markProxySuccessful();

                return true;
            }

            return false;
        }

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@id = 'formerrors']", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//td[@id = 'errorscontainer']/div/ol/li[1]", null, false)) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message =
                $this->http->FindSingleNode('//div[contains(@class, "errorContainer") or contains(@class, "error-container")] | //h4[@class = "alert-header" and not(contains(text(), "Forgot password?"))]')
                ?? $this->http->FindSingleNode('//p[contains(@class, "alert-description")]')
        ) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Email not found.You have an ExtraCare card. To sign in, provide a little more information to set up your profile.Sign in with a different email address')) {
                $this->throwProfileUpdateMessageException();
            }

            if (
                strstr($message, 'We\'re sorry For security reasons, your account has been temporarily locked.')
                || strstr($message, 'Your account is locked for ')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'Your account will be locked')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Enter a valid password')
                || strstr($message, 'Invalid password')
            ) {
                throw new CheckException("Invalid password. Check your spelling and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Enter a valid email address')
            ) {
                throw new CheckException("Couldn't sign In. Enter a valid email address.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Account not foundRe-enter your email addressOr create an account')) {
                throw new CheckException("Account not found. Re-enter your email address.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'An unexpected error occured')
                || strstr($message, 'An unexpected error occurred')
            ) {
//                throw new CheckException("An unexpected error occurred. Please try again", ACCOUNT_PROVIDER_ERROR);
                throw new CheckRetryNeededException(2, 0, "An unexpected error occurred. Please try again");
            }

            if (preg_match("/We're sorry.+We can't complete your request right now due to technical issues.+Please try again/", $message)) {
                throw new CheckRetryNeededException(2, 10, "We're sorry. We can't complete your request right know due to technical issues. Please try again");
            }

            if (strstr($message, 'Couldn\'t sign InThere was a problem with the email format you entered. Enter a valid email address')) {
                throw new CheckException("Couldn't sign In. There was a problem with the email format you entered. Enter a valid email address", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Account not foundYour email address does not match our records. Make sure you\'re typing your email address correctly. Re-enter your email addressOr create an account')) {
                throw new CheckException("Account not found. Your email address does not match our records. Make sure you're typing your email address correctly.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindPreg("/Attach your ExtraCare card/ims")) {
            throw new CheckException("To view your ExtraCare rewards, print Extra Bucks and to take advantage of convenient new online features, you need to Attach an ExtraCare card.", ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * For continued access to your existing CVS photos, you must upgrade to a free CVS.com account.
         * This upgrade allows you to manage your family's prescriptions, earn ExtraBucks and shop for everyday essentials.
         */
        if ($message = $this->http->FindPreg("/For continued access to your existing CVS photos, you must upgrade to a free CVS.com account. This upgrade allows you to manage your family's prescriptions, earn ExtraBucks and shop for everyday essentials./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//p[contains(normalize-space(text()), "Enter your date of birth to access your account.")] | //h1/span[contains(text(), "Verify your date")]');

        if ($question == 'Verify your date') {
            $question = 'Enter your date of birth to access your account.';
        }

        if ($question) {
            $this->holdSession();

            if (!isset($this->Answers[$question . " (MM/DD/YYYY)"])) {
                $this->AskQuestion($question . " (MM/DD/YYYY)", null, 'Question');

                return false;
            }

            $this->markProxySuccessful();
            $dob = $this->waitForElement(WebDriverBy::xpath('//input[@name = "dob" or @id = "cvs-form-0-input-dob"]'), 10);
            $this->saveResponse();
            $dob->sendKeys($this->Answers[$question . " (MM/DD/YYYY)"]);
            $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Confirm and sign in")]'), 0)->click();

            $res = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "user-profile-aerclub-card")]
                | //div[@id = "scroll_messages" and normalize-space(text()) != ""]
                | //div[contains(@class, "errorContainer")]
                | //div[contains(@class, "error-container")]
            '), 10);

            $this->saveResponse();
            $this->logger->debug("check results");

            if ($res) {
                $this->logger->debug("[Error]: '{$res->getText()}'");

                if (
                    strstr($res->getText(), 'Enter a valid 8-digit date of birth')
                    || strstr($res->getText(), 'That date of birth does not match our records')
                    || strstr($res->getText(), 'Enter an 8-digit date of birth')
                ) {
                    unset($this->Answers[$question . " (MM/DD/YYYY)"]);
                    $this->holdSession();
                    $this->AskQuestion($question . " (MM/DD/YYYY)", $res->getText(), 'Question');

                    return false;
                }
            }// if ($res)
        } elseif ($question2fa = $this->http->FindSingleNode('//div[div[contains(text(), "A 6-digit verification code was sen")]]')) {
            $this->holdSession();
            $this->saveResponse();

            if (!isset($this->Answers[$question2fa])) {
                $this->AskQuestion($question2fa, null, 'Question2fa');

                return false;
            }

            $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "forget-password-otp-input"]'), 0);
            $this->saveResponse();
            $answer = $this->Answers[$question2fa];
            unset($this->Answers[$question2fa]);
            $otp->sendKeys($answer);
            $this->saveResponse();
            $confirm = $this->waitForElement(WebDriverBy::xpath('//button[@id = "forgot-password-verify-submit"]'), 0);
            $confirm->click();

            sleep(5);

            $notNow = $this->waitForElement(WebDriverBy::xpath("//h1[contains(normalize-space(text()), 'Go Passwordless')]/following::button[normalize-space(text())='Not Now']"), 0);

            if ($notNow) {
                $notNow->click();
            }

            $res = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "user-profile-aerclub-card")]
                | //div[@id = "error-action"]
                | //p[contains(normalize-space(text()), "Enter your date of birth to access your account.")]
                | //h1/span[contains(text(), "Verify your date")]
            '), 5);

            $this->saveResponse();

            if ($res) {
                $message = $res->getText();
                $this->logger->debug("[Error]: '{$message}'");

                try {
                    $otp->clear();
                } catch (StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
                }

                if ($message == 'Enter a valid verification code') {
                    $this->AskQuestion($question2fa, $message, 'Question2fa');

                    return false;
                }

                if (
                    strstr($message, 'Enter your date of birth')
                    || strstr($message, 'Verify your date')
                ) {
                    $question = $message;
                }

                if ($question == 'Verify your date') {
                    $question = 'Enter your date of birth to access your account.';
                }

                if ($question) {
                    $this->holdSession();

                    $this->Question = $question . " (MM/DD/YYYY)";
                    $this->ErrorCode = ACCOUNT_QUESTION;
                    $this->Step = "Question";

                    return false;
                }
            }
        } else {
            if ($this->Step == 'Question2fa') {
                unset($this->Answers[$this->Question]);
            }

            return false;
        }
        $notNow = $this->waitForElement(WebDriverBy::xpath("//h1[contains(normalize-space(text()), 'Go Passwordless')]/following::button[normalize-space(text())='Not Now']"), 0);

        if ($notNow) {
            $notNow->click();
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->saveResponse();

        switch ($step) {
            case 'Question':
            case 'Question2fa':
                if (!$this->parseQuestion()) {
                    if ($this->http->FindSingleNode("
                        //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                        | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                        | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                        | //p[contains(text(), 'Health check')]
                    ")
                    ) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException(4, 0);
                    }

                    return false;
                }
        }

        return true;
    }

    public function Parse()
    {
        $this->http->GetURL(self::START_URL);
        $this->logger->debug("history start dates: " . json_encode($this->historyStartDates));
        $cvs = $this->getCvs();

        $this->logger->debug("get cookies");

        $this->http->GetURL('https://www.cvs.com/account/dashboard');

        $this->waitForElement(WebDriverBy::xpath('//cvs-my-profile'), 10);

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $cvs->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $cvs->http->RetryCount = 0;

        try {
            $cvs->http->GetURL($this->http->currentUrl(), [], 40);
        } catch (WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $cvs->http->RetryCount = 2;

        $this->stopSeleniumBrowser();
        $cvs->Parse();
        $this->SetBalance($cvs->Balance);
        $this->Properties = $cvs->Properties;
        $this->ErrorCode = $cvs->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $cvs->ErrorMessage;
            $this->DebugInfo = $cvs->DebugInfo;
        }
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //h2[contains(text(), "We are working to enhance your digital experience")]
                | //h1[contains(text(), "We\'re sorry but the site is unavailable at the moment")]
        ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    protected function getCvs()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->cvs)) {
            $this->cvs = new TAccountCheckerCvs();
            $this->cvs->http = new HttpBrowser("none", new CurlDriver());
            $this->cvs->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->cvs->http);
            $this->cvs->AccountFields = $this->AccountFields;
            $this->cvs->HistoryStartDate = $this->HistoryStartDate;
            $this->cvs->historyStartDates = $this->historyStartDates;
            $this->cvs->http->LogHeaders = $this->http->LogHeaders;
            $this->cvs->ParseIts = $this->ParseIts;
            $this->cvs->ParsePastIts = $this->ParsePastIts;
            $this->cvs->WantHistory = $this->WantHistory;
            $this->cvs->WantFiles = $this->WantFiles;
            $this->cvs->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->cvs->http->setDefaultHeader($header, $value);
            }

            $this->cvs->globalLogger = $this->globalLogger;
            $this->cvs->logger = $this->logger;
            $this->cvs->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->cvs;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//a[contains(text(), 'Sign Out')] | //span[contains(@class, 'ec-member')]")
            && !stristr($this->http->currentUrl(), "https://www.cvs.com/retail-easy-account/create-account")
        ) {
            return true;
        }

        return false;
    }

    private function delay()
    {
        $delay = rand(3, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }
}
