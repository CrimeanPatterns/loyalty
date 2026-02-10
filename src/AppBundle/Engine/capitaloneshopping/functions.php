<?php

class TAccountCheckerCapitaloneshopping extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const XPATH_LOGIN_SUCCESSFUL = '//div[@class = "current-credits" and contains(text(), "ou have")] | //h3[contains(text(), "Available Rewards")]';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->http->saveScreenshots = true;
        $this->useCache();
    }

    public static function FormatBalance($fields, $properties)
    {
        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://capitaloneshopping.com/my-rewards");
        } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        } catch (Facebook\WebDriver\Exception\UnknownErrorException | Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        $loginSuccesfull = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_SUCCESSFUL), 7);
        $this->saveResponse();

        if ($loginSuccesfull) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
            $this->http->GetURL("https://capitaloneshopping.com/sign-in?redirectTo=%2Fmy-rewards");
        } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        // from TAccountCheckerCapitalcardsSelenium
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "c1-btn")]/button'), 0)->click();
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "usernameInputField"]'), 5);

            if (empty($loginInput)) {
                $this->saveResponse();
                $this->driver->executeScript("let login = document.querySelector('input#usernameInputField'); login.style.zIndex = '100003';");
                $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "userNameInputField"]'), 5);
            }

            $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "pwInputField"]'), 0);
            $this->saveResponse();

            $button = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'c1-ease-button--action') or @data-testtarget=\"sign-in-submit-button\"]"), 0);

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

        $login = $this->waitForElement(WebDriverBy::id('id_email'), 7);
        $pass = $this->waitForElement(WebDriverBy::id('id_password'), 0);
        $this->saveResponse();

        if (!$login || !$pass) {
            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha('6Lf7Mh8UAAAAALqzvCRguYEINESwRh0ICjlMq2Hh');

        if (!$captcha) {
            return false;
        }

        $this->saveResponse();

        try {
            $this->driver->executeScript("window.verifyCallback('{$captcha}')");
        } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        sleep(1);
        $this->saveResponse();
        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'primary-btn-large') and contains(text(), 'Sign In')]"), 0);
        $btn->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_SUCCESSFUL . '
            | //div[@class = "error"]/span[@class = "note"]
            | //p[(contains(text(), "To provide you with the best protection, choose a 2-Step Verification method to verify your identity."))]
            | //p[contains(@class, "error-warning")]
            | //p[contains(text(), "Looks like you might need a reminder for your username and password")]
        '), 7);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_SUCCESSFUL), 0)) {
            return true;
        }

        $message = $this->http->FindSingleNode('//div[@class = "error"]/span[@class = "note"] | //p[contains(@class, "error-warning")]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Invalid email or password.')
                || strstr($message, "What you entered doesn't match what we have on file")
                || strstr($message, "You have a couple more attempts before you get locked out.")
                || $message == 'Looks like you may need some help, or sign in again.'
                || strstr($message, "You are running low on sign in attempts. Choose Forgot Username or Password or try to sign in again.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Looks like you might need a reminder for your username and password.")]')) {
            throw new CheckException("Looks like you might need a reminder for your username and password.", ACCOUNT_INVALID_PASSWORD);
        }

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
                $choose->click();
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

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Balance - $0.00 available in Shopping Rewards
        $this->SetBalance($this->http->FindSingleNode("//div[contains(@class, 'current-summary')]//span[contains(@class, 'credits')]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='credits']//h2[not(contains(text(), 'My Account')) and not(contains(text(), '$'))]")));
        // All Shopping Savings
        $this->SetProperty('AllSavings', $this->http->FindSingleNode('//span[@class = "all-savings"]'));
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

    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
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

        $this->http->GetURL("https://capitaloneshopping.com/my-rewards");
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGIN_SUCCESSFUL), 7);
        $this->saveResponse();

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
//        if ($message = $this->http->FindSingleNode("//div[contains(text(), '')]")) {
//            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
//        }

        return false;
    }
}
