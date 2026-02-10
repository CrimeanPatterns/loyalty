<?php
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCtripSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private ?TAccountCheckerCtrip $checker = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $checker = $this->getChecker();

        try {
            $this->http->GetURL("https://www.trip.com/account/signin");
        } catch (StaleElementReferenceException | UnexpectedJavascriptException | UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            if (
                strstr($e->getMessage(), 'Timed out waiting for page load')
                || strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
            ) {
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            } else {
                $retry = true;
            }
        }
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Please enter an email address"]'), 10);
        $this->saveResponse();

        if (!$loginInput) {
            $this->logger->error("something went wrong");

            return false;
        }

        $loginInput->sendKeys($this->AccountFields['Login']);
        $this->saveResponse();
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "ibu_login_submit"] | //button[contains(., "Continue")]'), 0)->click();

        sleep(5);
        $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-drop-btn")] 
            | //input[@placeholder="Please enter your password"] 
            | //*[self::div or self::span][contains(text(), "Sign in with Password")]'), 15);

        //if (!in_array($this->AccountFields['Login'], ['veresch80@yahoo.com','kingweirong@gmail.com']))
        if ($signInWithPassword = $this->waitForElement(WebDriverBy::xpath('//*[self::div or self::span][contains(text(), "Sign in with Password")]'),
            0)) {
            $this->saveResponse();
            $signInWithPassword->click();
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-drop-btn")] | //input[@placeholder="Please enter your password"]'),
                10);
            $this->saveResponse();
        }

        // CAPTCHA 1
        $this->slideCaptcha();
        // CAPTCHA 2
//            $this->clickCaptchaCtrip($selenium, 5, 180, 180);
//            $this->saveToLogs($selenium);

        // password
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Please enter your password"]'),
            0);

        if (!$passwordInput) {
            // Enter Verification Code
            $verification = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(),"We have sent a verification code to ")]'),
                0);
            if ($verification) {
                return $this->parseQuestion();
            } else {
                $this->saveResponse();
                // CAPTCHA 2
                if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "cpt-choose-box")] | //div[contains(@class, "slider")]/div[contains(@class, "container")]'),
                    0)) {
                    return false;
                }
                $this->logger->error("something went wrong");

                if ($this->http->FindSingleNode('//div[contains(text(), "Set Password") or contains(text(), "Set Your Password")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                if ($this->http->FindSingleNode('//div[contains(text(), "Create an Account")]')) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $errorMessage = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "s_error_tips")]'),
                    0);

                if ($errorMessage) {
                    $message = $errorMessage->getText();
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'Password error, please try again')
                        || $message == 'Please enter a valid email'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;
                }

                if ($this->AccountFields['Login'] == 'cfleejeff@gmail.com') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
        }

        $passwordInput->sendKeys($this->AccountFields['Pass']);
//            $selenium->waitForElement(WebDriverBy::id('ibu_login_submit'), 0)->click();
        $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Sign In")]'), 0)->click();
        $this->saveResponse();
        // $passwordInput->sendKeys('password');

        $success = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "account-username")] | //*[contains(text(), "An error occurred, please try again later")] | //div[@class="toast_modal"]'),
            10);
        $this->saveResponse();

        if ($success && strstr($success->getText(), 'An error occurred, please try again later')) {
            throw new CheckException($success->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if (!$success) {
            // CAPTCHA 1
            $this->slideCaptcha();
            $this->saveResponse();
            // CAPTCHA 2
            $this->clickCaptchaCtrip($this, 5, 180, 180);
            $this->saveResponse();
        }

        if ($errorMessage = $this->waitForElement(WebDriverBy::xpath('//span[
                    contains(text(), "Incorrect username or password.") or
                    contains(text(), "Sorry! Your sign in details are incorrect. Please try again.") or
                    contains(text(), "Sorry, please sign in on the Ctrip simplified Chinese website and try again")
                    or contains(text(), "Oops! Something went wrong. Please try again.")
                    or contains(text(), "Sorry, please sign in on the Trip.com simplified Chinese website and try again.")
                    or contains(text(), "Please reset your password and sign in again.")
                ]
                | //div[contains(@class, "s_error_tips")]
                | //*[contains(text(), "An error occurred, please try again later")]
                | //*[contains(text(), "Your password may be incorrect, or this account may not exist.")]
                '), 0)
        ) {
            $this->saveResponse();

            if ($errorMessage) {
                $message = $errorMessage->getText();
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'Incorrect username or password')
                    || strstr($message, 'Incorrect password. Please try again.')
                    || strstr($message,
                        'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                    || strstr($message, 'Sorry! Your sign in details are incorrect. Please try again.')
                    || strstr($message,
                        'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                    || strstr($message, 'Please reset your password and sign in again.')
                    || strstr($message, 'Password error, please try again')
                    || strstr($message, 'Your password may be incorrect, or this account may not exist.')
                    || $message == 'Please enter a valid email'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Oops! Something went wrong. Please try again.
                if (
                    strstr($message, 'Oops! Something went wrong. Please try again.')
                    || strstr($message,
                        'Sorry, please sign in on the Trip.com simplified Chinese website and try again.')
                    || strstr($message, 'An error occurred, please try again later')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }
        }

        if (!$success && $this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                "), 0)
        ) {
            $retry = true;
        }
        $checker = $this->getChecker();
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return true;
    }

    private function slideCaptcha()
    {
        $this->logger->notice(__METHOD__);

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = 30;
        $mover->steps = 10;
        $mover->enableCursor();
        $counter = 0;

        if (!$slider = $this->waitForElement(WebDriverBy::cssSelector('div.cpt-drop-btn'), 0)) {
            return;
        }

        do {
            if ($counter++ > 2) {
                /*
                $this->sendNotification('refs #23019 slider captcha not solved // BS');
                */

                break;
            }
            $this->saveResponse();
            $mover->moveToElement($slider);
            $mouse = $this->driver->getMouse()->mouseDown();
            usleep(500000);
            $mouse->mouseMove($slider->getCoordinates(), 200, 0);
            usleep(500000);
            $mouse->mouseUp();
            $this->saveResponse();
            sleep(2);
        } while ($slider = $this->waitForElement(WebDriverBy::cssSelector('div.cpt-drop-btn'), 0));
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }
        $question = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(),"We have sent a verification code to")]'), 0);
        $this->saveResponse();

        if (!$question) {
            return true;
        }

        $question = $question->getText();
        $this->logger->debug($question);
        $this->holdSession();
        $this->AskQuestion($question, null, 'emailCode');

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");

        $securityAnswer = $this->waitForElement(WebDriverBy::xpath('(//input[contains(@class,"inputGroup-module__item_input__")])[1]'), 0);

        if (!$securityAnswer) {
            return false;
        }

        for ($i = 1; $i <= strlen($answer); $i++) {
            $codeInput = $this->waitForElement(WebDriverBy::xpath("(//input[contains(@class,'inputGroup-module__item_input__')])[$i]"), 0);

            if (!$codeInput) {
                $this->logger->error("input not found");

                break;
            }

            $codeInput->clear();
            $codeInput->sendKeys($answer[$i-1]);
        }
        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@class,"tripui-online-btn") and contains(.,"Sign In")]'), 0);
        if (!$button) {
            return false;
        }
        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath("
            //form//*[contains(text(), 'Verification code error, please check and try again')]
        "), 7);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), 'emailCode');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: ".$this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'emailCode') {
            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();
                $checker = $this->getChecker();
                return $checker->loginSuccessful();
            }
        }

        return false;
    }

    protected function getChecker(): TAccountCheckerCtrip
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->checker)) {
            $this->checker = new TAccountCheckerCtrip();
            $this->checker->http = new HttpBrowser("none", new CurlDriver());
            $this->checker->http->setProxyParams($this->http->getProxyParams());
            $this->http->brotherBrowser($this->checker->http);
            $this->checker->AccountFields = $this->AccountFields;
            $this->checker->itinerariesMaster = $this->itinerariesMaster;
            $this->checker->HistoryStartDate = $this->HistoryStartDate;
            $this->checker->historyStartDates = $this->historyStartDates;
            $this->checker->http->LogHeaders = $this->http->LogHeaders;
            $this->checker->ParseIts = $this->ParseIts;
            $this->checker->ParsePastIts = $this->ParsePastIts;
            $this->checker->WantHistory = $this->WantHistory;
            $this->checker->WantFiles = $this->WantFiles;
            $this->checker->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->checker->globalLogger = $this->globalLogger;
            $this->checker->logger = $this->logger;
            $this->checker->onTimeLimitIncreased = $this->onTimeLimitIncreased;

            $cookies = $this->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->logger->debug("set cookies");
                $this->logger->debug($cookie['name']);
                $this->checker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        }
        return $this->checker;
    }

    public function Login()
    {
        $checker = $this->getChecker();
        if ($checker->loginSuccessful()) {
            return true;
        }
        return false;
    }

    public function Parse()
    {
        $checker = $this->getChecker();
        $host = $this->http->getCurrentHost();
        $this->logger->debug("host: $host");
        $checker->Parse($host);
        $this->SetBalance($checker->Balance);
        $this->Properties = $checker->Properties;
        $this->ErrorCode = $checker->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $checker->ErrorMessage;
            $this->DebugInfo = $checker->DebugInfo;
        }
    }

    public function ParseItineraries()
    {
        $checker = $this->getChecker();
        $checker->ParseItineraries();
    }
}
