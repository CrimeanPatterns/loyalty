<?php

class TAccountCheckerAmexserve extends TAccountChecker
{
    use \SeleniumCheckerHelper;

    private const X_ERROR_MESSAGE = "//div[@class = 'bb-formatted-error-message']";
    private const X_ONE_TIME_PASSWORD = "//h1[@class = 'page-one-time-password__title']";
    private const X_LOCKED_ACCOUNT = "//p[contains(text(), 'locked your account')] | //h1[contains(text(), 'Your Account is Locked')] | //h1[contains(text(), 'Your Account Is Locked')]";
    private const X_GOTO_HOME = "//button[contains(text(), 'Go To Home')] | //span[contains(text(), 'Logout')] | //span[contains(text(), 'Hi, ')]";
    private const X_LOGIN_SUCCESSFUL = "//span[contains(text(), 'Logout')] | //span[contains(text(), 'Hi, ')]";
    private const X_CODE_NOT_CORRECT = "//span[contains(text(), 'is not correct')]";
    private const X_CODE_OLD = "//span[contains(text(), 'old verification code')]";

    private const X_SET_LOGGED_IN = [self::X_LOCKED_ACCOUNT, self::X_GOTO_HOME, self::X_LOGIN_SUCCESSFUL];
    private const X_SET_BAD_CODE = [self::X_CODE_NOT_CORRECT, self::X_CODE_OLD];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
        $this->setKeepProfile(true);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        try {
            $this->http->GetURL("https://secure.serve.com/Account/Dashboard?omnlogin=US_Login_Serve", [], 20);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
//        $this->http->removeCookies();
        try {
            $this->http->GetURL("https://secure.serve.com/login");
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }
        $loginInput = $this->waitForElement(\WebDriverBy::xpath("
            //input[@id = 'bb-username']
            | //div[contains(text(), 'Access denied')]
            | //iframe[contains(@src, 'Incapsula')]"), 15);
        $this->saveResponse();

        if ($loginInput === null) {
            return $this->checkErrors();
        }

        if ($loginInput->getTagName() === 'iframe' || stripos($loginInput->getText(), 'Access denied') !== false) {
            throw new \CheckRetryNeededException(2, 1);
        }

        $delay = 5;
        $this->logger->error("Delay: {$delay}");
        sleep($delay);

        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput = $this->waitForElement(\WebDriverBy::id('bb-password'), 1);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $button = $this->waitForElement(\WebDriverBy::id('bb-submit'), 1);
        $this->saveResponse();

        $this->driver->executeScript('
            let oldEval = window.eval;
            window.eval = function(str) {
             // do something with the str string you got
             return oldEval(str);
            }
            
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener(\'load\', function() {
                    if (/accessToken/g.exec( this.responseText )) {
                        localStorage.setItem(\'responseData\', this.responseText);
                    }
                });
                           
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $button->click();

        return true;
    }

    public function Login()
    {
        try {
            $element = $this->waitForElement(\WebDriverBy::xpath(implode(" | ", array_merge(
            [
                self::X_ERROR_MESSAGE,
                self::X_ONE_TIME_PASSWORD,
            ],
            self::X_SET_LOGGED_IN
        ))), 20);
            $this->saveResponse();

            if ($element === null) {
                return false;
            }

            if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOGIN_SUCCESSFUL), 0)) {
                return true;
            }

            $this->saveResponse();

            if ($this->waitForElement(\WebDriverBy::xpath(self::X_ONE_TIME_PASSWORD), 0)) {
                if (
                isset($this->Answers[$this->Question])
                && ($haveCode = $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'I have a code.')]"), 0))
            ) {
                    $haveCode->click();
                } else {
                    // Send via email to v*********h@gmail.com
                    $label = $this->waitForElement(\WebDriverBy::xpath("//label[@for ='verifyUsingEmail']"), 1);

                    if ($label === null) {
                        $label = $this->waitForElement(\WebDriverBy::xpath("//label[@for ='verifyUsingEmail']"), 1, false);
                    }

                    $this->saveResponse();

                    if ($label === null) {
                        return false;
                    }

                    if ($this->isBackgroundCheck()) {
                        $this->Cancel();
                    }

                    $label->click();
                    $this->waitForElement(\WebDriverBy::xpath("//button[@class = 'bb-navigation-footer__next-btn']"), 0)->click();
                }

                $this->waitEmailSent();

                if (!$this->processOTP()) {
                    return false;
                }
            }

            if ($element = $this->waitForElement(\WebDriverBy::xpath(self::X_LOCKED_ACCOUNT), 0)) {
                throw new \CheckException($element->getText(), ACCOUNT_LOCKOUT);
            }

            if ($message = $this->http->FindSingleNode(self::X_ERROR_MESSAGE)) {
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'The username and password combination isn\'t right.')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == 'Error') {
                    throw new CheckException('The username and password combination isn\'t right.', ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }
            /*
            if (!$this->http->PostForm())
                return $this->checkErrors();

            if (!$this->parseQuestion())
                return false;

            if ($this->loginSuccessful()) {
                return true;
            }
            // current password is not valid
            if ($message = $this->http->FindSingleNode("//li[contains(text(), 'current password is not valid')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            // Please enter a valid Email Address
            if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please enter a valid Email Address')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            // The username and password combination isn't right.
            if ($message = $this->http->FindSingleNode("//li[contains(text(), 'The username and password combination isn')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            if ($message = $this->http->FindSingleNode('
                    //li[contains(text(), "We\'re sorry, something went wrong! Please try again.")]
                ')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Please enter a valid username or a valid Email Address(ex.username@domain.com)
            if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please enter a valid username or a valid Email Address(ex.username@domain.com)')]"))
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            // For your security, we've locked your Account.
            if ($message = $this->http->FindPreg("/(For your security, we\'ve locked your Account\.)/ims"))
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            */
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Your Account is locked or closed
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your Account is locked or closed')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your Account is locked
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Your Account is locked')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // The website is offline for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("
                //h3[contains(text(), 'The website is offline for scheduled maintenance.')]
                | //h1[
                    contains(text(), 'Oops, we are temporarily unavailable.')
                ]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // American Express Serve® website is offline.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'American Express Serve® website is offline.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Thanks for your application! However, we need additional information from you before we can approve your Account.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thanks for your application! However, we need additional information from you before we can approve your Account.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your account has been closed.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your account has been closed.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The website is offline for scheduled maintenance. We apologize for any inconvenience.
        if ($message = $this->http->FindPreg("/The website is offline for scheduled maintenance. We apologize for any inconvenience\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, but we’re unable to process your request right now.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, but we’re unable to process your request right now.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm(null, "//form[contains(@action, '/User/Login/OneTimePassword')]")) {
            $email = $this->http->FindSingleNode('//input[@name = "EmailAddress"]/@value');

            if (!$email) {
                return false;
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $this->http->SetInputValue("SelectedChannel", "Email");

            if (!$this->http->PostForm()) {
                return false;
            }

            if (!$this->http->ParseForm(null, "//form[contains(@action, '/User/Login/OneTimePasswordVerify')]")) {
                return false;
            }
            $question = "Please enter temporary six digit verification code which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "VerificationCode";

            return true;
        }

        $question = $this->http->FindSingleNode("//input[@id = 'SecurityQuestion']/@value");

        if (!isset($question)) {
            return true;
        }

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'Login/Verify')]")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'VerificationCode') {
            $this->http->SetInputValue("VerificationCode", $this->Answers[$this->Question]);
            $this->http->SetInputValue("action", "Verify");
            unset($this->Answers[$this->Question]);

            if (!$this->http->PostForm()) {
                return false;
            }
            // Invalid answer
            if ($this->http->FindSingleNode("
                    //p[contains(text(), 'The verification code you entered is incorrect. Please re-enter your code.')]
                    | //li[contains(text(), 'Please enter a valid verification code.')]
                ")) {
                $this->AskQuestion($this->Question, "The verification code you entered is incorrect. Please re-enter your code.", "VerificationCode");

                return false;
            }

            return !$this->checkErrors();
        }

        $this->http->SetInputValue("SecurityAnswer", $this->Answers[$this->Question]);
        $this->http->SetInputValue("RememberMe", "true");

        if (!$this->http->PostForm()) {
            return false;
        }
        // Invalid answer
        if ($this->http->FindSingleNode("//li[contains(text(), 'The security question answer does not match our records')]")) {
            $this->parseQuestion();

            return false;
        }

        return !$this->checkErrors();
    }

    public function Parse()
    {
        // Balance - Available Balance
        $this->SetBalance($this->http->FindSingleNode("(//span[@class = 'bb-accounts-list__account-info-balance'])[1]"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(text(), 'Hi, ')]", null, true, "/[^\!\,]+/")));
    }

    protected function incapsula()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");

        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");

        if (!$formURL) {
            return false;
        }
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        sleep(2);
        $this->http->GetURL($referer);

        if ($this->http->Response['code'] == 503) {
            $this->http->GetURL($this->http->getCurrentScheme() . "://" . $this->http->getCurrentHost());
            sleep(1);
            $this->http->GetURL($referer);
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//span[contains(@class, 'welcome-name')]")) {
            return true;
        }

        return false;
    }

    private function processOTP(): bool
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->Answers[$this->Question])) {
            $this->logger->notice("answer not found");

            return false;
        }

        $input = $this->waitForElement(\WebDriverBy::xpath("//input[@data-testid = 'page-one-time-password__textField_verifyCode']"), 3);
        $this->saveResponse();

        if (!$input) {
            return true;
        }
        $input->sendKeys($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        $this->sendNotification("answer was entered // RR");

        $this->driver->executeScript('
            let oldEval = window.eval;
            window.eval = function(str) {
             // do something with the str string you got
             return oldEval(str);
            }
            
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener(\'load\', function() {
                    if (/accessToken/g.exec( this.responseText )) {
                        localStorage.setItem(\'responseData\', this.responseText);
                    }
                });
                           
                return oldXHROpen.apply(this, arguments);
            };
        ');

        $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Verify')]"), 1)->click();

        sleep(5);

        $this->waitForElement(\WebDriverBy::xpath(implode(" | ", array_merge(
            self::X_SET_BAD_CODE,
            self::X_SET_LOGGED_IN
        ))), 10);
        $this->saveResponse();

        if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOCKED_ACCOUNT), 0)) {
            // will be handled in LoadLoginForm
            return true;
        }

        if ($this->waitForElement(\WebDriverBy::xpath(self::X_LOGIN_SUCCESSFUL), 0)) {
            return true;
        }

        if ($element = $this->waitForElement(\WebDriverBy::xpath(self::X_GOTO_HOME), 0)) {
            $element->click();

            return true;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath(self::X_CODE_NOT_CORRECT), 0)) {
            $input->clear();
            $this->holdSession();
            $this->AskQuestion($this->Question, $error->getText(), "Question");

            return false;
        }

        if ($error = $this->waitForElement(\WebDriverBy::xpath(self::X_CODE_OLD), 0)) {
            $input->clear();
            $message = $error->getText();
            $this->waitForElement(\WebDriverBy::xpath("//button[contains(text(), 'Resend it.')]"), 1)->click();
            $this->waitEmailSent($message);

            return false;
        }

        return false;
    }

    private function waitEmailSent($error = null): void
    {
        $this->logger->notice(__METHOD__);
        $sentText = "We just sent a one-time passcode to ";
        $sentElement = $this->waitForElement(\WebDriverBy::xpath("//p[contains(text(), '$sentText')]"), 7);
        $this->saveResponse();

        if (!$sentElement) {
            return;
        }
        $this->holdSession();
        $email = trim(str_replace($sentText, "", trim($sentElement->getText())), " .");
        $this->AskQuestion("Please enter temporary six digit passcode which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.", $error, "Question");
    }
}
