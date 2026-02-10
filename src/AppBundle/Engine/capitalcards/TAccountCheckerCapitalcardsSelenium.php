<?php

class TAccountCheckerCapitalcardsSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
//        $this->disableImages();
        $this->useGoogleChrome();
//        $this->useCache();
        $this->http->saveScreenshots = true; //todo: debug
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] != 'CA') {
            if (ArrayVal($this->AccountFields, 'Partner', 'awardwallet') == 'awardwallet') {
                throw new CheckException('Please edit this account to authenticate yourself via the "Connect with Capital One" button.', ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException('Unsupported provider');
            }
        }
//        $this->http->GetURL("https://myaccounts.capitalone.com/#/welcome");

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->processOTCEntering();

                break;

            case "PhoneEntering":
                return $this->processPhoneEntering();

                break;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://verified.capitalone.com/sic-ui/#/esignin?Product=Card&CountryCode=CA&Locale_Pref=en_CA");

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@data-controlname="username" or @id="usernameInputField"]'), 10);

        /*
        if (!$loginInput && $this->waitForElement(WebDriverBy::id('username'), 0)) {
            $this->logger->notice("Switch country to Canada / English");
            $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->driver->manage()->addCookie(['name' => 'locale_pref', 'value' => 'en_CA', 'domain' => ".capitalone.com"]);
            $this->driver->manage()->addCookie(['name' => 'ISSO_CNTRY_CODE', 'value' => 'CA', 'domain' => ".capitalone.com"]);
            $this->http->GetURL("https://servicing.capitalone.com/c1/login.aspx?CountryCode=CA");
            $loginInput = $this->waitForElement(WebDriverBy::id('usernameForCA'), 10);
        }
        */
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@data-controlname="password" or @id="pwInputField"]'), 0);
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'sign-in-button') or @data-testtarget=\"sign-in-submit-button\"]"), 0);

        if (!$loginInput || !$passwordInput || !$button) {
            $this->logger->error("something went wrong");
            $this->checkErrors();

            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }

            return false;
        }// if (!$loginInput || !$passwordInput || !$button)
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // We are sorry but the application that you are trying to access is unavailable at this time.
        if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'We are sorry but the application that you are trying to access is unavailable at this time.')]"), 0)) {
            throw new CheckException('We are sorry but the application that you are trying to access is unavailable at this time. Please try again later.', ACCOUNT_PROVIDER_ERROR);
        }
        // The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The requested service is temporarily unavailable. It is either overloaded or under maintenance. Please try later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $sleep = 30;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $logout = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'id-signout-icon-text']"), 0);
            $this->saveResponse();

            if ($logout) {
                return true;
            }
            // It looks like you might need a reminder for your user name and password.
            if ($error = $this->waitForElement(WebDriverBy::xpath("//*[self::p or self::div][*[self::div or self::span or self::p][contains(text(), 'ooks like you might need a reminder for your')]]"), 0)) {
                if ($message = $this->http->FindPreg('/(?:It looks|Looks) like you might need a reminder for your user\s*name and password./', false, $error->getText())) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
            }
            // You have limited sign-in attempts. Your user name and/or password doesn't match what we have on file.
            // You only have a couple more attempts to sign in before you're locked out.
            /*
             * You're running low on sign-in attempts.
             *
             * Choose Forgot User name or Password or try to sign in again.
             */
            if ($message = $this->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "You have limited sign-in attempts.")]
                    | //span[contains(text(), "You only have a couple more attempts to sign in before you")]
                    | //span[contains(text(), "You\'re running low on sign-in attempts.")]
                    | //p[contains(text(), "What you entered doesn\'t match what we have on file.")]
                    | //p[contains(text(), "You have a couple more attempts before you get locked out")]
                    | //p[contains(text(), "You are running low on sign in attempts. Choose Forgot Username or Password or try to sign in again.")]
                '), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            /*
             * We hit a snag
             *
             * Looks like we don't have the necessary contact info to complete a security step.
             * Give us a call and we'll get you back on track.
             */
            /*
             * Oop!
             * It looks like something went wrong, but we're working on it!
             */
            /*
             * We hit a snag
             *
             * Looks like something went wrong, but we're working on it.
             * Give it another try in a bit
             */
            /**
             * Oops!
             * There was an issue accessing your account. Please try again. If you continue to see this message, give us a call so we can help.
             * Reference: SB0418001 43924357
             * Call 1 (877) 383-4802.
             */
            if (
                $message = $this->waitForElement(WebDriverBy::xpath('
                    //p[contains(text(), "Looks like we don\'t have the necessary contact info to complete a security step.")]
                    | //span[contains(text(), "It looks like something went wrong, but we\'re working on it!")]
                    | //p[contains(text(), "Looks like something went wrong, but we\'re working on it. ")]
                '), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // | //p[contains(text(), "There was an issue accessing your account. Please try again.")]

            // To provide you with the best protection, choose a 2-Step Verification method to verify your identity.
            if (
                $this->waitForElement(WebDriverBy::id('choice-p-0'), 0)
                || $this->waitForElement(WebDriverBy::xpath('//p[(contains(text(), "To provide you with the best protection, choose a 2-Step Verification method to verify your identity."))]'), 0)
            ) {
                $email = $this->waitForElement(WebDriverBy::xpath('//p[(contains(@id, "choice-single-p-") or contains(@id, "choice-multi-") or @class = "message--sub-header") and (contains(., "Select an existing email") or contains(., "Select an existing number") or contains(., "send an email") or contains(., "send a text to") or contains(., "need to verify your mobile number first"))]'));
                $this->saveResponse();

                if (!$email) {
                    return false;
                }
                $email->click();
                /*
                 * Here is the email address we have for you
                 * ********@**.com
                 */
                $sendCode = $this->waitForElement(WebDriverBy::xpath('//button[@id = "otp-button-0" or @data-testtarget = "otp-button"]'), 10);

                if (!$sendCode) {
                    if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Enter your mobile phone number")]'), 0)) {
                        return $this->processPhoneEntering();
                    } else {
                        $sendCode = $this->waitForElement(WebDriverBy::id('otp-button'), 0);
                    }
                }
                $choose = $this->waitForElement(WebDriverBy::xpath('//label[@id = "otp-multi-label-0"] | //label[@class="mobilephone"]//span'), 0);
                $this->saveResponse();

                if ($choose) {
                    try {
                        $choose->click();
                    } catch (UnknownServerException $e) {
                        $this->logger->error("UnknownServerException: {$e->getMessage()}");
                    }
                }
                $email = $this->http->FindSingleNode('//span[@id = "otp-pin-span-0"] | //span[@id = "otp-single-span-0"] | //label[@id = "otp-multi-label-0"] | //div[img[@id = "otp-single-img"]] | //span[@data-testtarget = "singleContactPointEntryValue"] | //label[@class="mobilephone"]//span');

                if (!$sendCode || !$email) {
                    $this->logger->error("something went wrong");

                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $sendCode->click();

                return $this->processOTCEntering();
            }// if ($this->waitForElement(WebDriverBy::id('choice-p-0'), 0))

            sleep(1);
            $this->saveResponse();
        }// while ((time() - $startTime) < $sleep)

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (strstr($this->http->currentUrl(), 'https://verified.capitalone.com/auth/systemerror?id')) {
            $this->DebugInfo = 'systemerror';

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $view = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'View Account')]"), 10);
        $this->saveResponse();

        if (!$view && $this->waitForElement(WebDriverBy::xpath('//p[
                    contains(text(), "Update your personal information to ensure we can contact you as needed.")
                    or contains(text(), "Veuillez valider vos coordonnées afin que nous puissions communiquer avec vous au besoin.")
                ]
                | //span[contains(text(), "Our security checks require you to create a new password.")]
            '), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        $rows = $this->http->XPath->query('//ul[@id = "summaryParent"]/li | //div[@id = "summaryParent"]/div[@class = "account"] | //div[contains(@class, "tiles-layout__tile")]//div[contains(@class, "account-tile ")]');
        $this->logger->debug("Total {$rows->length} cards were found");

        for ($n = 0; $n < $rows->length; $n++) {
            $this->logger->debug("row: " . $n);
            $row = $rows->item($n);
            $title = $this->http->FindSingleNode('.//h2[contains(@class, "headerTruncateLarge") or contains(@class, "headerTruncateSmall")] | .//div[contains(@class, "primary-detail__identity__name")]', $row);
            $code = $this->http->FindSingleNode('.//span[contains(@class, "accnumbertrail")] | .//div[contains(@class, "primary-detail__identity__account-number")]', $row, false, "/([\d]+)/ims");
            $balance = $this->http->FindSingleNode('.//div[span[(contains(text(), "Rewards") or contains(text(), "Miles de récompenses")) and not(contains(text(), "Cash"))]]/preceding-sibling::div//span[@class = "screen-reader-only" or @class = "milesPointsValue"]/following-sibling::div', $row, false, self::BALANCE_REGEXP)
                ?? $this->http->FindSingleNode('.//div[span[contains(text(), "Rewards") or contains(text(), "Remises en argent")  or contains(text(), "Miles de récompenses")]]/preceding-sibling::div//span[@class = "screen-reader-only" or @class = "milesPointsValue"]', $row, false, self::BALANCE_REGEXP)
                ?? $this->http->FindSingleNode('.//div[span[contains(text(), "Rewards") or contains(text(), "Remises en argent")  or contains(text(), "Miles de récompenses")]]/preceding-sibling::div//span[@class = "screen-reader-only" or @class = "milesPointsValue"]', $row, false, self::BALANCE_REGEXP)
                ?? $this->http->FindSingleNode('.//div[span[contains(text(), "Rewards Miles")]]/preceding-sibling::div[contains(@class, "secondary-content__amount")]', $row)
                ?? $this->http->FindSingleNode('.//div[contains(text(), "Rewards Miles")]/preceding-sibling::div[contains(@class, "secondary-content__amount")]', $row)
                ?? $this->http->FindSingleNode('.//div[contains(text(), "reward miles")]/preceding-sibling::div[contains(@class, "secondary-content__amount")]', $row)
            ;

            if (!isset($balance) && $this->http->FindSingleNode('.//div[span[contains(text(), "Cash Back") or contains(text(), "Rewards Cash")]]/preceding-sibling::div[contains(@class, "secondary-content__amount")]//div[contains(@class, "secondary-content__amount__dollar")]', $row, false, self::BALANCE_REGEXP)) {
                $balance =
                    $this->http->FindSingleNode('.//div[span[contains(text(), "Cash Back") or contains(text(), "Rewards Cash")]]/preceding-sibling::div[contains(@class, "secondary-content__amount")]//div[contains(@class, "secondary-content__amount__dollar")]', $row)
                    . "." .
                    $this->http->FindSingleNode('.//div[span[contains(text(), "Cash Back") or contains(text(), "Rewards Cash")]]/preceding-sibling::div[contains(@class, "secondary-content__amount")]//div[contains(@class, "secondary-content__amount__superscript")]', $row);
            }
            $this->logger->debug("code: $code, title: $title, balance: $balance");

            if (isset($title, $balance, $code)) {
                $currency = $this->http->FindSingleNode('.//div[span[contains(text(), "Rewards") or contains(text(), "Remises en argent")]]/preceding-sibling::div//span[@class = "screen-reader-only"] | .//div[span[contains(text(), "Cash Back") or contains(text(), "Rewards Cash")]]/preceding-sibling::div[contains(@class, "secondary-content__amount")]/div[contains(@class, "secondary-content__amount__sign")]', $row);
                $this->AddSubAccount([
                    "Code"        => 'capitalcards' . $code,
                    "DisplayName" => $title . ' ...' . $code,
                    "Balance"     => $balance,
                    'Currency'    => (strstr($currency, '$')) ? '$' : '',
                ], true);
                $this->AddDetectedCard([
                    "Code"            => 'capitalcards' . $code,
                    "DisplayName"     => $title . ' ...' . $code,
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);
            }// if (isset($title, $balance, $code))
            elseif (isset($code, $title)) {
                if ($this->http->FindSingleNode('.//h3[contains(text(), "Account Closed")]', $row)) {
                    $cardDescription = C_CARD_DESC_CLOSED;
                } else {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                }
                $this->AddDetectedCard([
                    "Code"            => 'capitalcards' . $code,
                    "DisplayName"     => $title . ' ...' . $code,
                    "CardDescription" => $cardDescription,
                ]);
            }
        }// for ($n = 0; $n < $rows->length; $n++)

        if (!empty($this->Properties['SubAccounts'])
            || (isset($this->Properties['DetectedCards']) && count($this->Properties['DetectedCards']) > 0)) {
            $this->SetBalanceNA();
        }
    }

    protected function processPhoneEntering()
    {
        $this->logger->notice(__METHOD__);
        $question = 'Enter your mobile phone number';

        $otp = $this->waitForElement(WebDriverBy::id('phone-input-0'), 10);
        $sendCode = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Check My Mobile Number")]'), 0);
        $this->saveResponse();

        if (!$sendCode || !$otp) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "PhoneEntering");

            return false;
        }// if (!isset($this->Answers[$question]))
        $otp->clear();
        $otp->sendKeys($this->Answers[$question]);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $sendCode->click();

        return $this->processOTCEntering();
    }

    protected function processOTCEntering()
    {
        $this->logger->notice(__METHOD__);
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "pin" or @id = "pinEntry"]'), 5);
        $this->saveResponse();
        $email = $this->http->FindSingleNode('//span[@id = "otp-pin-span-0"] | //span[@id = "otp-single-span-0"] | //span[@class = "poc--contact-info"] | //div[img[@id = "otp-single-img"]] | //span[@data-testtarget="singleContactPointEntryValue"]');

        if (strstr($email, '@')) {
            $question = "Please enter 6-digit code which was sent to the following email address: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        } else {
            $question = "Please enter 6-digit code which was sent to the following phone number: $email. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
        }

        if (!$otp || !$question) {
            $this->logger->error("something went wrong");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }// if (!isset($this->Answers[$question]))
        $otp->clear();
        $otp->sendKeys($this->Answers[$question]);
        // Next button
        $submitCode = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'otp-pin-button-0' or contains(text(), 'Submit My Code') or contains(., 'Submit code') or @data-testtarget = 'otp-submit']"), 5);

        if (!$submitCode) {
            $this->logger->error("something went wrong");

            return false;
        }
        $submitCode->click();
        unset($this->Answers[$question]);
        // Looks like the code you entered is invalid. Please try again.
        $error = $this->waitForElement(WebDriverBy::xpath("//*[
                contains(text(), 'Looks like the code you entered is invalid.')
                or contains(text(), 'Looks like the code you entered is too short. It must be 6 digits. Please try again.')
                or contains(text(), 'That looks like the phone number that sent you the code. Please look for the 6-digit code within the message you received.')
        ]"), 5);
        $this->saveResponse();

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $this->AskQuestion($question, $error, "Question");
        }// if (!empty($error))

        return true;
    }
}
