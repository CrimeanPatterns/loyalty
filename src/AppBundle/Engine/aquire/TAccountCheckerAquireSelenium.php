<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAquireSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->http->saveScreenshots = true;

//        $this->useFirefoxPlaywright();// TODO: not working now
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->setProxyBrightData(null, "static", "au");
        //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        */
        /*
        $this->setProxyBrightData(null, "static", "au");
        $this->seleniumOptions->userAgent = null;

//        $this->http->SetProxy($this->proxyAustralia(), false);
        $this->disableImages();
//        $this->useCache();
        */
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.qantasbusinessrewards.com/myaccount");
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $error = $this->driver->switchTo()->alert()->getText();
            $this->logger->debug("alert -> {$error}");
            $this->driver->switchTo()->alert()->accept();
            $this->logger->debug("alert, accept");
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }

        if ($step == "Code") {
            return $this->processVerificationCode();
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.qantasbusinessrewards.com/myaccount");
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'LOG IN')]"), 0);

        if (!$login || !$pass || !$submitButton) {
            $this->logger->error("something went wrong");

            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $submitButton->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->debug(__METHOD__);
        $this->saveResponse();
        // We're performing some site maintenance to make your ride as smooth as possible
        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'re performing some site maintenance to make your ride as smooth as possible')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/The server is temporarily unable to service your request\.\s*Please try again later\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // retries
        if (
            $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->waitForElement(WebDriverBy::xpath("
                //div[contains(@class, 'ErrorComponent__Message-sc-')]
                | //p[contains(@lass, 'InputError-sc-')]
            "), 0)
        ) {
            $message = $error->getText();
            // The login details you entered don't match our records.
            if (
                strstr($message, 'The login details you entered don\'t match our records.')
                || $this->http->FindPreg('/We need the following information to continue:\s*Email/', false, $message)
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Something went wrong! Please try again or contact our Service Centre on 13 74 78, Mon to Sat, 7am to 7pm (AEST).
            if (
                strstr($message, 'Something went wrong! Please try again or contact our Service Centre on')
            ) {
                $this->DebugInfo = "block";
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
//                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Your account has been locked after several unsuccessful attempts.
            if (
                strstr($message, 'Your account has been locked after several unsuccessful attempts.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // Unfortunately, there's an issue with your account. Please contact our Qantas Business Rewards Service Centre on 13 74 78, Mon to Sat, 7am to 7pm (AEST).
            if (
                strstr($message, 'Unfortunately, there\'s an issue with your account. Please contact our Qantas Business Rewards Service Centre ')
                || strstr($message, 'Something went wrong! Please try again.')
                || $this->http->FindPreg('/You\'re unable to proceed due to multiple unsuccessful attempts\./', false, $message)
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are currently experiencing an issue with logging in to Qantas Business Reward accounts, please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $sleep = 20;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->checkProviderErrors()) {
                return false;
            }
            // Answer a security question and email the verification code to
            if ($choice = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Answer a security question and email')]"), 0)) {
                $choice->click(); // TODO: not working in selenium now
//                $this->driver->executeScript("document.querySelector('[value=\"EMAIL\"]').click()");
                sleep(1);
                $this->saveResponse();
                $next = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'NEXT')]"), 0);
                $this->saveResponse();

                if (!$next) {
                    return false;
                }
                $next->click();

                return $this->processSecurityCheckpoint();
            }// if ($choice = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Answer a security question and email')]"), 0))

            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please answer the security question to verify your identity.')]/following-sibling::label"), 0)) {
                return $this->processSecurityCheckpoint();
            }
            sleep(1);
            $this->saveResponse();
        }
        // hard code, no error, no auth
        if (in_array($this->AccountFields['Login'], [
            'robrob3@hotmail.com',
            'finleyrach@gmail.com',
            'aishah.coogee@ljh.com.au',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please answer the security question to verify your identity.')]/following-sibling::label"), 5);
        $securityAnswer = $this->waitForElement(WebDriverBy::xpath('//input[@name = "securityAnswer"]'), 0);
        $this->saveResponse();

        if (!$question || !$securityAnswer) {
            return false;
        }
        $this->holdSession();

        if ($question && !isset($this->Answers[$question->getText()])) {
            $this->AskQuestion($question->getText(), null, 'Question');

            return false;
        }
        $securityAnswer->clear();
        $securityAnswer->sendKeys($this->Answers[$question->getText()]);
        sleep(1);
        $next = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'NEXT')]"), 0);
        $this->saveResponse();

        if (!$next) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->logger->debug("click button...");
        $next->click();
        sleep(5);
        // OTP entered is incorrect
        $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "The answer you\'ve entered is incorrect.")]'), 0);
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($question->getText(), $error->getText(), 'Question');

            return false;
        }
        $this->logger->debug("success");

        if (!$this->processVerificationCode()) {
            return false;
        }

        return true;
    }

    public function processVerificationCode()
    {
        $this->logger->notice(__METHOD__);
        $questionCode = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please enter the code we sent')]"), 10);
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name = "otp"]'), 0);
        $this->saveResponse();

        if (!$questionCode || !$otp) {
            return false;
        }
        $this->holdSession();
        $question = $questionCode->getText();

        if ($questionCode && !isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, 'Code');

            return false;
        }

        $otp->clear();
        $otp->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        sleep(1);
        $next = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'NEXT')]"), 0);
        $this->saveResponse();

        if (!$next) {
            return false;
        }
        $this->logger->debug("click button...");
        $next->click();
        sleep(5);
        // The verification code you've entered is incorrect. You have ... more tries. Please try again or resend code.
        $error = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "The verification code you\'ve entered is incorrect.")]
            | //p[contains(text(), "The verification code has expired.")]
            | //p[contains(text(), "Something went wrong! Please try again.")]
        '), 0);

        if ($error) {
            $this->logger->notice("resetting answers");
            $otp->clear();
            $this->AskQuestion($question, str_replace('Resend code.', '', $error->getText()), 'Code');

            return false;
        }

        if ($this->checkProviderErrors()) {
            return false;
        }
        // Access is allowed
        $this->loginSuccessful();

        return true;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $balance = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Qantas Points")]/preceding-sibling::div | //div[contains(text(), "Qantas Points")]/following-sibling::div[1]/div'), 15);
        $this->saveResponse();
        // provider bug fix
        if (!$balance) {
            $this->logger->debug("provider bug fix");
            $this->http->GetURL("https://www.qantasbusinessrewards.com/myaccount");
            $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Qantas Points")]/preceding-sibling::div | //div[contains(text(), "Qantas Points")]/following-sibling::div[1]/div'), 15);
            $this->saveResponse();
        }

        // Balance - Qantas Points
        $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Qantas Points")]/preceding-sibling::div | //div[contains(text(), "Qantas Points")]/following-sibling::div[1]/div/text()[1]'));
        // Member since
//        $this->SetProperty("Joined", ArrayVal($response, 'joinDate'));
        // ABN - Australian Business Number
        $this->SetProperty("ABN",
            $this->http->FindSingleNode("//strong[contains(text(), 'ABN ')]/span")
            ?? $this->http->FindSingleNode("//div[contains(text(), 'ABN ')]", null, true, "/ABN\s*([^<]+)/")
        );
        // Status
        $this->SetProperty("Status",
            $this->http->FindSingleNode("//div[contains(@class, 'qbrLevel')]/p | //div[contains(@class, 'dnesDN')]")
            ?? $this->http->FindSingleNode("//div[contains(text(), 'Membership')]/following-sibling::div[1]/div[1]")
        );
        // Status expiration
//        $this->SetProperty("StatusExpiration", ArrayVal($response, 'levelExpiryDate'));
        // Qantas Points earned from flying this membership year
//        $this->SetProperty("YTDQantasPoints", intval(ArrayVal($response, 'flyingPoints')));
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//h3[contains(., 'Hi ')]", null, true, "/Hi\s*([^,]+)/")
            ?? $this->http->FindSingleNode("//div[contains(text(), 'ABN ')]/preceding-sibling::div[2]")
        ));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $logout = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Qantas Points")]/preceding-sibling::div | //div[contains(text(), "Qantas Points")]/following-sibling::div[1]/div'), 5);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        return false;
    }
}
