<?php

class TAccountCheckerAmeriprise extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public const WAIT_TIMEOUT = 7;

    /*
    public function IsLoggedIn() {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.ameriprise.com/secure/');
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Log Out')]"), self::WAIT_TIMEOUT);
        $this->saveResponse();
        if ($logout)
            return true;

        return false;
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->disableImages();
        $this->useChromium();
        $this->useCache();
        $this->keepCookies(false);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://www.ameriprise.com/client-login/');

        $loginData = [
            'w-lg-username'    => $this->AccountFields['Login'],
            'w-lg-password'    => $this->AccountFields['Pass'],
        ];

        foreach ($loginData as $key => $val) {
            if (!$elem = $this->waitForElement(WebDriverBy::id($key), self::WAIT_TIMEOUT)) {
                $this->logger->error("Can not find input id='{$key}'");

                return $this->checkErrors();
            }// if (!$elem = $this->waitForElement(WebDriverBy::id($key), self::WAIT_TIMEOUT))
            $elem->sendKeys($val);
        }// foreach ($loginData as $key => $val)

        if ($elem = $this->waitForElement(WebDriverBy::xpath('//label[@for = "w-lg-remember"]'), self::WAIT_TIMEOUT)) {
            $elem->click();
        } else {
            $this->logger->error("There is no remember checkbox");
        }

        if (!$elem = $this->waitForElement(WebDriverBy::id('w-lg-login_submit'), self::WAIT_TIMEOUT)) {
            $this->logger->error("There is no login button");

            return $this->checkErrors();
        }
        $elem->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $startTime = time();
        $time = time() - $startTime;
        $sleep = 30;
        $xpathNotifications = '//p[contains(text(), "Tap the notification that was sent to your device.")]';

        while ($time < $sleep) {
            $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Log out') or contains(., 'Log Out')] | //*[@id = 'sign-out']"), 0);
            $this->saveResponse();

            // AccountID: 7111905
            if ($this->http->FindSingleNode($xpathNotifications)) {
                $sleep = 120;
                $startTime = time();

                do {
                    $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                    sleep(5);
                    $this->saveResponse();
                } while (
                    ((time() - $startTime) < $sleep)
                    && $this->waitForElement(WebDriverBy::xpath($xpathNotifications), 0)
                );

                if ($message = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The notification we sent to your device was not accepted. Log in to try again.')]"), 0)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Log out') or contains(., 'Log Out')] | //*[@id = 'sign-out']"), 0);
                $this->saveResponse();

                if (!$logout && $this->http->FindSingleNode($xpathNotifications)) {
                    throw new CheckException("The notification we sent to your device was not accepted. Log in to try again.", ACCOUNT_PROVIDER_ERROR);
                }
            }

            if ($logout) {
                return true;
            }
            // security questions
            $sq =
                $this->waitForElement(WebDriverBy::xpath('//div[div[h3[contains(text(), "We need to verify your identity")]]]/following-sibling::ul/li//a[contains(., "Security question") or contains(., "question")]'), 3)
                ?? $this->waitForElement(WebDriverBy::xpath('//div[div[h3[contains(text(), "We need to verify your identity")]]]/following-sibling::ul/li//a[contains(., "Text Message") or contains(., "Request a code via text message")]'), 0)
            ;

            if ($sq) {
                $this->saveResponse();
                $sq->click();
                sleep(1);
                $submit = $this->waitForElement(WebDriverBy::xpath('//button[@id = "w-mfa-options-submit"]'), 0);

                if (!$submit) {
                    return false;
                }
                $submit->click();

                return $this->parseQuestion();
            }
            // The User ID or password you entered is incorrect. Please try again. [2011]
            // The User ID or password you entered is incorrect. Try again or use the Forgot User ID or Password link below. [7011-2006]
            if ($error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'The User ID or password you entered is incorrect. ')]"), 0)) {
                throw new CheckRetryNeededException(3, 10, $error->getText(), ACCOUNT_INVALID_PASSWORD);
            }// sometimes it's lie
            // For your protection, your account has been locked. For assistance, please call customer service at 800.862.7919 (M-F, 7 a.m. - 9 p.m. CT; weekends 8 a.m. - 5 p.m. CT). [7011-3001]
            if ($error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'For your protection, your account has been locked.')]"), 0)) {
                throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
            }

            if ($message = $this->http->FindSingleNode("//div[@id = 'login-error-message']")) {
                $this->logger->error("[Error]: {$message}");

                if (strstr($message, 'We were unable to process your request. For assistance, please call Customer Service')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            // Let's confirm some information about your account
            if ($this->waitForElement(WebDriverBy::xpath('//p[
                    contains(text(), "Let\'s confirm some information about your account")
                    or contains(text(), "We need to confirm some information about your account.")
                ]'), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }

            sleep(1);
            $this->saveResponse();
            $time = time() - $startTime;
        }// while ($time < $sleep)

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // select method of verification via security question
        /*
        $elem = $this->waitForElement(WebDriverBy::id("kbaChallengeOption"), 0);
        if ($elem) {
            $elem->click();

            $elem = $this->waitForElement(WebDriverBy::id("continue"), 0);
            if (!$elem) {
                $this->logger->error('There is no continue button');
                $this->logger->debug('return false');
                return false;
            }
            $elem->click();
        }// if ($elem)
        else
            $this->logger->notice('There is no kbaChallengeOption option');
        */
        // security question
        $question = $this->waitForElement(WebDriverBy::xpath('
            //div[div[h3[contains(text(), "Please answer your security question")]]]/following-sibling::h4
            | //p[contains(text(), "Enter the 6-digit passcode we sent to")]
            | //label[contains(text(), "Enter 6-digit code")]
            | //p[contains(text(), "Your code sent to")]
        '), self::WAIT_TIMEOUT);

        if (
            !$question
            && !$this->waitForElement(WebDriverBy::xpath('//div[div[h3[contains(text(), "We need to verify your identity")]]]/following-sibling::ul/li//a[contains(., "Security question")]'), 0)
            && ($choice = $this->waitForElement(WebDriverBy::xpath('//label[contains(@for, "w-mfa-text-message-radio-") or contains(@for, "w-mfa-request-a-code-via-text-message")]'), 0))
        ) {
            $this->saveResponse();
            $choice->click();
            $submit = $this->waitForElement(WebDriverBy::xpath('//button[@id = "w-mfa-options-submit"]'), 0);

            if (!$submit) {
                return false;
            }
            $submit->click();

            $question = $this->waitForElement(WebDriverBy::xpath('
                //div[div[h3[contains(text(), "Please answer your security question")]]]/following-sibling::h4
                | //p[contains(text(), "Enter the 6-digit passcode we sent to")]
                | //p[contains(text(), "Your passcode sent to ")]
                | //p[contains(text(), "Your code sent ")]
            '), self::WAIT_TIMEOUT);
        }

        $this->saveResponse();

        if ($question) {
            $question = $question->getText();
            $this->logger->debug("Question -> {$question}");
        } else {
            $question = $this->http->FindSingleNode('//p[contains(text(), "Your passcode sent to ")]/text()[1]');
        }

        if (!$question) {
            $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Log out') or contains(., 'Log Out')] | //*[@id = 'sign-out']"), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "SecurityCheckpoint");
            $this->logger->debug('return false');

            return false;
        }

        $answer = $this->Answers[$question];

        if (
            strstr($question, 'Enter the 6-digit passcode we sent to')
            || strstr($question, 'Enter 6-digit code')
            || strstr($question, 'Your passcode sent to ')
            || strstr($question, 'will expire after ')
        ) {
            unset($this->Answers[$question]);
        }

        // enter an answer
        if (
            !($input = $this->waitForElement(WebDriverBy::id("w-mfa-otp-answer"), 0))
        ) {
            $this->logger->error("Can not find answer input");
            $this->logger->debug('return true');

            return true;
        }

        $input->clear();
        $input->sendKeys($answer);

        if (
            !($sbm = $this->waitForElement(WebDriverBy::id("w-mfa-submit"), 0))
        ) {
            $this->logger->error("Can not find answer continue button");
            $this->logger->debug('return true');

            return true;
        }
        $sbm->click();
        // waiting an error
        $this->logger->debug("waiting an error...");
        $error = $this->waitForElement(WebDriverBy::xpath("//div[
            contains(text(), 'Your answer does not match our records.')
            or contains(text(), 'The passcode you entered does not match what was provided or has expired.')
        ]"), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($error) {
            $error = $error->getText();
            $this->logger->error("Error -> {$error}");
            $this->holdSession();
            $this->AskQuestion($question, $error, "SecurityCheckpoint");
            $this->logger->debug('return false');

            return false;
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'For your protection, your account has been locked.')]"), 0)) {
            throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
        }

        $this->logger->debug('return true');

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "SecurityCheckpoint":
                return $this->parseQuestion();

                break;
        }// switch ($step)

        return false;
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        /*
         * You are not currently enrolled in a product that supports this feature.
         * Please contact your advisor to learn more about this or other services available through Ameriprise Financial.
         */
        /*
        $noRewards = false;
        if ($this->waitForElement(WebDriverBy::xpath("//div[@id = 'ampsec-overview-account-summary']//p[contains(text(), 'No account information available.')]"), 0))
            $noRewards = true;
        */

        // Balance - Available Points
        $balance = $this->waitForElement(WebDriverBy::xpath('//header[contains(.., "Rewards")]/following-sibling::table[@class = "Table"]//tr/td[2]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($balance) {
            $this->SetBalance($balance->getText());
        } elseif (!$balance) {
            $this->SetBalance($this->http->FindSingleNode('//header[contains(.., "Rewards")]/following-sibling::table[@class = "Table"]//tr/td[2]'));
        }

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode("//span[contains(@class, 'display-name')]")
            ?? $this->http->FindSingleNode("//span[contains(text(), 'Profile:')]", null, true, "/Profile:\s*([^<]+)/")
        ));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (
                !empty($this->Properties['Name'])
                && $this->waitForElement(WebDriverBy::xpath('
                    //header[div/h4[contains(text(), "Cash & Investments")]]/following-sibling::table
                    | //h2[contains(text(), "No accounts to show")]
                    | //div[@id = "ampsec-recent-activity"]/p[contains(text(), "You have no posted activity for the last 30 days.")]
                '), 0)
                && $this->waitForElement(WebDriverBy::xpath('//header[h2[contains(text(), "Portfolio progress")]]'), 0)
//                && in_array($this->AccountFields['Login'], [
//                    'arnieh63',
//                    'subuvalli',
//                    'jsouthrn85',
//                ])
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            /*
                // You are not currently enrolled in a product that supports this feature.
                $this->SetWarning($this->http->FindPreg("/We are temporarily unable to display reward details\./i"));
                // You are not currently enrolled in a product that supports this feature.
                if ($noRewards || $this->http->FindPreg("/You are not currently enrolled in a product that supports this feature\./"))
                    $this->SetWarning("You are not currently enrolled in a product that supports Rewards feature.");/*review* /
                // Please select a policy
                if ($this->http->FindPreg("/Please select a policy from the list below\./i"))
                    $this->throwProfileUpdateMessageException();
                // We are temporarily unable to display rewards details. Please try again later.
                if ($message = $this->http->FindSingleNode('//h1[@id = "card-details-error" and contains(text(), "We are temporarily unable to display rewards details. Please try again later.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->AccountFields['Login'] == 'lorrainetimmen')
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            */
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }
}
