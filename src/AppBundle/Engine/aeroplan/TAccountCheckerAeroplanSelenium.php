<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\Solver\Exception;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAeroplanSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private const XPATH_LOADING = '//*[@id = "ac-sso-loading"]/@alt | //body[contains(@class, " loading")]/@class';
    private const REGEXP_LOADING = '/<body class="mat-typography loading"/ims';
    private const XPATH_LOGOUT = '//a[contains(., "Sign out")] | //div[contains(@class, "credit-card-holder")] | //div[contains(@class, "redeemable-points")] | //div[contains(@class, "ngx-ac-aco-user-balance")]';
    /**
     * @var HttpBrowser
     */
    public $browser;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $sensor_data = '';
    private $sensorDataURL = null;

    private $idToken = null;
    private $fromIsLoggedIn = false;
    private $tokenStorage;

    private $history = []; // refs #21119

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

//        $this->useChromePuppeteer();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN - 5;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        $this->http->SetProxy($this->proxyReCaptcha(), false);

        if (isset($fingerprint)) {
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->usePacFile(false);
        $this->seleniumOptions->addHideSeleniumExtension = false;

        $this->http->saveScreenshots = true;

        unset($this->State['DoNotKeepProfile']);
        unset($this->State['FF_VERSION']);
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL('https://www.aircanada.com/aeroplan/member/dashboard?lang=en-CA');

            if ($this->loginSuccessful(0)) {
                $this->fromIsLoggedIn = true;
                $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "welcome-msg")]'), 5);
                $this->saveResponse();

                return true;
            }
        } catch (UnexpectedJavascriptException | TimeOutException | NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("IsLoggedIn -> exception: " . $e->getMessage());
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->logger->debug("Current URL: " . $this->http->currentUrl());
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if (!strstr($this->http->currentUrl(), 'clogin/pages/login')) {
            try {
                $this->http->GetURL('https://www.aircanada.com/us/en/aco/home.html');
                $this->http->GetURL('https://www.aircanada.com/aeroplan/member/dashboard?lang=en-CA');
            } catch (NoSuchWindowException | NoSuchDriverException $e) {
                $this->logger->error("exception: " . $e->getMessage());
                $this->State['DoNotKeepProfile'] = true;

                throw new CheckRetryNeededException(3, 5);
            } catch (TimeOutException $e) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage());

                try {
                    $this->driver->executeScript('window.stop();');
                } catch (NoSuchDriverException $e) {
                    $this->logger->error("exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
        }

        $this->AccountFields['Login'] = str_replace(" ", "", $this->AccountFields['Login']);

        // AccountID: 666548
        if (is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) == 12) {
            throw new CheckException("Please enter a valid Aeroplan number or email.", ACCOUNT_INVALID_PASSWORD);
        }

        $xpathForm = '//form[@id = "gigya-login-form"]';
        $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"] | //input[contains(@class, "gig-tfa-code-textbox")] | //button[@id = "acUserMenu-aco" or @id = "libraUserMenu-signIn"]'), 20);

        if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "acUserMenu-aco" or @id = "libraUserMenu-signIn"]'), 0)) {
            $btn->click();

            $signIn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "acUserMenu-signIn"]'), 3);
            $this->saveResponse();

            if ($signIn) {
                $signIn->click();

                $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"] | //input[contains(@class, "gig-tfa-code-textbox")]'), 20);
            }// if ($signIn)
        }// if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "acUserMenu-aco"]'), 0))

        $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"]'), 0);
        $this->saveResponse();

        if (
            !$loginInput
            && $this->http->FindSingleNode("
                //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                | //pre[not(@id) and normalize-space(text()) = '{\"code\":400,\"message\":\"Unknown method: start\"}']
                | //p[contains(text(), 'Health check')]
                | //h1[contains(text(), 'Access Denied')]
            ")
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if (!$loginInput && $this->processSecurityCheckpoint()) {
            return false;
        }

        if (!$loginInput && $this->http->FindSingleNode(self::XPATH_LOADING)) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"]'), 15);
            $this->saveResponse();
        }

        if (!$loginInput && $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'re sorry. Something went wrong and your request was not submitted. Please try again.")]'), 0)) {
            $this->http->GetURL('https://www.aircanada.com/clogin/pages/login');
            $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"]'), 10);
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "password"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@value = "Sign in"]'), 0);
        $this->saveResponse();

        if (!$loginInput && $passwordInput && $button) {
            $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"]'), 0);
        }

        if (!$loginInput || !$passwordInput || !$button) {
            $this->callRetry();

            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->logger->error("Remove old answers");
        $this->Answers = [];
        /*
        $this->injectJQ();
        sleep(2);
        */

        /*
        try {
            $loginInput->sendKeys($this->AccountFields['Login']);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $loginInput = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@name = "username"]'), 10);
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $button->click();
        */

        /*
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 10000);
        $mover->steps = rand(10, 30);

        try {
            $mover->moveToElement($loginInput);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }

        $cps = rand(5, 13);
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], $cps);
        $this->saveResponse();

        try {
            $mover->moveToElement($passwordInput);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }

        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], $cps);
        $this->saveResponse();
        */

        try {
            $this->logger->debug("set login");
            $loginInput->click();
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
        } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
            $this->logger->error("ElementClickInterceptedException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->saveResponse();
        }

        try {
            $this->logger->debug("set password");
            $passwordInput->click();
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
        } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
            $this->logger->error("ElementClickInterceptedException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->saveResponse();
        }

        $this->logger->debug('check for captcha progress');
        $this->saveResponse();

        $this->waitFor(function () {
            return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
        }, 140);

        $this->saveResponse();

//        usleep(rand(100000, 500000));
//        $mover->moveToElement($button);
        $button->click();

        /*
        $injection = "
            var emailInput = $('input[name = \"username\"]');
            var passwordInput = $('input[name = \"password\"]');
            emailInput.val(\"{$this->AccountFields['Login']}\");
            sendEvent(emailInput.get(0), 'input');
            passwordInput.val(\"".str_replace('\\', '\\\\', $this->AccountFields['Pass'])."\");
            sendEvent(passwordInput.get(0), 'input');

            function sendEvent (element, eventName) {
                var event;

                if (document.createEvent) {
                    event = document.createEvent(\"HTMLEvents\");
                    event.initEvent(eventName, true, true);
                } else {
                    event = document.createEventObject();
                    event.eventType = eventName;
                }

                event.eventName = eventName;

                if (document.createEvent) {
                    element.dispatchEvent(event);
                } else {
                    element.fireEvent(\"on\" + event.eventType, event);
                }
            }
        ";
        $this->logger->debug("injection");
        $this->driver->executeScript($injection);
        sleep(1);
        $button->click();

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        $error = $this->waitForElement(WebDriverBy::xpath('
            //*[self::div or self::span][contains(@class, "gigya-error-msg-active") and contains(text(), "There are errors in your form, please try again")]
        '), 5);
        $this->saveResponse();
        if ($error) {

            $loginInput->click();
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->click();
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $this->logger->debug("injection");
            $this->driver->executeScript($injection);

            sleep(1);
            $button->click();
            $this->logger->debug("delay");
            sleep(7);
        }
        */

        return true;
    }

    public function injectJQ()
    {
        $this->logger->notice(__METHOD__);
        $this->driver->executeScript("
            var jq = document.createElement('script');
            jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
            document.getElementsByTagName('head')[0].appendChild(jq);
        ");
    }

    public function Login()
    {
        $xpathResult = '
            //div[@id = "ac-sso-login-form_showTfaUI_1_wrapper-email"]
            | //*[self::div or self::span][contains(@class, "gigya-error-msg-active")]
            | //div[contains(text(), "We are not able to retrieve your profile information at the moment. Please try again later.")]
            | //h2[contains(text(), "Terms and Conditions")]
            | //h2[contains(text(), "Aeroplan Privacy Policy")]
            | //label[contains(text(), "One last thing - We’ve sent a verification email to ")]
            | //p[contains(text(), "Sorry the page you\'re looking for was not found.")]
            | //p[contains(text(), "We\'re sorry, something went wrong. We\'re working to resolve the issue.")]
            | //div[contains(@class, "view-activity")]
            | 
        ' . self::XPATH_LOGOUT;
        $this->waitForElement(WebDriverBy::xpath($xpathResult), 15);
        $this->saveResponse();

        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->State['DoNotKeepProfile'] = true;

            throw new CheckRetryNeededException(3, 0);
        }

        // recaptcha
        if ($message = $this->http->FindSingleNode('//form[@id = "gigya-login-form"]//*[self::div or self::span][contains(@class, "gigya-error-msg-active")]')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'To login, confirm you are not a robot') {
                // throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                $this->logger->debug('check for captcha progress');
                $this->saveResponse();
                /*
                $this->waitFor(function () {
                    $this->increaseTimeLimit(120);

                    return is_null($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
                }, 180);
                */

                // captcha
                $captcha = $this->parseRecaptcha();

                if ($captcha === false) {
                    return false;
                }

                $this->logger->notice("Remove iframe");
//                    $this->driver->executeScript("$('div.g-recaptcha iframe').remove();");
                $this->driver->executeScript('document.getElementsByName("g-recaptcha-response").value = "' . $captcha . '";');

                $this->saveResponse();

                $this->logger->notice("Executing captcha callback");

                $this->driver->executeScript('
                var findCb = (object) => {
                    if (!!object["callback"] && !!object["sitekey"]) {
                        return object["callback"]
                    } else {
                        for (let key in object) {
                            if (typeof object[key] == "object") {
                                return findCb(object[key])
                            } else {
                                return null
                            }
                        }
                    }
                }
                findCb(___grecaptcha_cfg.clients[0])("' . $captcha . '")
            ');

                $this->saveResponse();

                $xpathForm = '//form[@id = "gigya-login-form"]';

                if ($button = $this->waitForElement(WebDriverBy::xpath($xpathForm . '//input[@value = "Sign in"]'), 0)) {
                    $button->click();

                    sleep(10);

                    $this->waitForElement(WebDriverBy::xpath($xpathResult), 5);
                    $this->saveResponse();

                    $solvingStatus =
                        $this->http->FindSingleNode($xpathForm . '//a[@title="AntiCaptcha: Captcha solving status"]')
                        ?? $this->http->FindSingleNode($xpathForm . '//a[@class = "status"]')
                    ;

                    if ($solvingStatus) {
                        $this->logger->error("[AntiCaptcha]: {$solvingStatus}");

                        if (
                            strstr($solvingStatus, 'Proxy response is too slow,')
                            || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection refused')
                            || strstr($solvingStatus, 'Could not connect to proxy related to the task, connection timeout')
                            || strstr($solvingStatus, 'Captcha could not be solved by 5 different workers')
                            || strstr($solvingStatus, 'Solving is in process...')
                            || strstr($solvingStatus, 'Proxy IP is banned by target service')
                            || strstr($solvingStatus, 'Recaptcha server reported that site key is invalid')
                        ) {
                            $this->markProxyAsInvalid();

                            throw new CheckRetryNeededException(2, 3, self::CAPTCHA_ERROR_MSG);
                        }

                        $this->DebugInfo = $solvingStatus;
                    }
                }
            }
        }

        if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "ac-sso-login-form_showTfaUI_1_wrapper-email"]'), 0)) {
            $this->injectJQ();

            return $this->processSecurityCheckpoint();
        }

        if ($message = $this->http->FindSingleNode('//form[@id = "gigya-login-form"]//*[self::div or self::span][contains(@class, "gigya-error-msg-active")]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'The credentials you\'ve entered don\'t seem to be valid.')
                || strstr($message, 'Please enter a valid Aeroplan number or email.')
                || $message == 'For security reasons, your account has been locked for the next 30 minutes.'
                || strstr($message, 'We\'re not able to validate the Aeroplan number or email address and password provided.')
            ) {
                $this->markProxySuccessful();

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'There are errors in your form, please try again')
            ) {
                $this->State['NewFingerprint'] = true;
                $this->DebugInfo = 'There are errors in your form';
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 0/*, $message, ACCOUNT_PROVIDER_ERROR*/);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'General Server Error'
            ) {
                $this->markProxySuccessful();

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'Your Aeroplan account has been deactivated.')
            ) {
                $this->markProxySuccessful();

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                // There seems to be an issue with your profile. Please call the Air Canada Contact Centre below to complete any missing field(s).
                strstr($message, 'There seems to be an issue with your profile.')
            ) {
                $this->markProxySuccessful();
                $this->throwProfileUpdateMessageException();
            }

            if ($message == 'To login, confirm you are not a robot') {
                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        $this->needToAcceptTerms();

        if ($this->http->FindSingleNode('//label[contains(text(), "One last thing - We’ve sent a verification email to ")]')) {
            $this->markProxySuccessful();
            $this->throwProfileUpdateMessageException();
        }

        $this->saveResponse();

        // todo: debug
        if ($this->waitForElement(WebDriverBy::xpath(self::XPATH_LOADING), 0)) {
            $this->sendNotification("too long loading // RR");
            $this->waitForElement(WebDriverBy::xpath($xpathResult), 15);
            $this->saveResponse();
        }

        // it often helps
        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//form[@id = "gigya-login-form"]//input[@name = "password"]'), 0);

        if (
            $this->waitForElement(WebDriverBy::xpath('//form[@id = "gigya-login-form"]//input[@value = "Sign in"]'), 0)
            && $passwordInput
            && $passwordInput->getAttribute('value') == $this->AccountFields['Pass']
        ) {
            $this->State['NewFingerprint'] = true;
            $this->State['DoNotKeepProfile'] = true;
            $this->DebugInfo = 'auth failed';

            if (
                $this->AccountFields['Login'] == '969868603'
                && $this->attempt == 2
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(2, 5 * $this->attempt);
        }

        return false;
    }

    public function needToAcceptTerms()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h2[
                contains(text(), "Terms and Conditions")
                or contains(text(), "Aeroplan Privacy Policy")
            ]')
        ) {
            $this->markProxySuccessful();
            $this->throwAcceptTermsMessageException();
        }
    }

    public function processSecurityCheckpoint(): bool
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@class, "gig-tfa-code-textbox")]'), 1);

        if (!$codeInput) {
            $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[contains(@class, "gig-tfa-code-textbox")]'), 1, false);
        }

        $email = $this->waitForElement(WebDriverBy::xpath('//div[@id = "ac-sso-login-form_showTfaUI_1_wrapper-email"]'), 0);

        if (!$email || !$codeInput) {
            return false;
        }

        $this->Question = "We have sent a verification code to the email address: {$email->getText()}. It will expire in 5 minutes.";

        if (!isset($this->Answers[$this->Question])) {
            $this->holdSession();
            $this->AskQuestion($this->Question, null, "Question");

            return false;
        }

        $codeInput->click();
        $codeInput->clear();

        $codeInput->sendKeys($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        $this->logger->debug("click button...");
        $button = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "gig-tfa-button-submit")]'), 5);
        $this->saveResponse();

        if (!$button) {
            return false;
        }
        $button->click();
        sleep(5);
        // Please enter a 6-digit code we sent to you
        try {
            $error = $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'gig-tfa-error')]"), 0);
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if ($error) {
            $message = $error->getText();
            $codeInput->clear();

            if ($this->attempt == 0) {
                $this->State['NewFingerprint'] = true;
                $this->State['DoNotKeepProfile'] = true;

                throw new CheckRetryNeededException(2, 0, $message);
            }

            if (strstr($message, 'Please enter a 6-digit code we sent to you')) {
                $this->AskQuestion($this->Question, $message, "Question");
            }

            return false;
        }
        $this->logger->debug("success");

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        // Access is allowed
        try {
            return $this->loginSuccessful(15);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }
    }

    public function ProcessStep($step)
    {
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        try {
            $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign in"] | //form[@id = "gigya-login-form"]//input[@name = "username"] | //div[@id = "ac-sso-login-form_showTfaUI_1_wrapper-email"] | ' . self::XPATH_LOADING), 5);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        $this->saveResponse();

        if (
            $this->isNewSession()
            || $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign in"] | //form[@id = "gigya-login-form"]//input[@name = "username"] | ' . self::XPATH_LOADING), 0)
//            || $this->http->FindSingleNode('//input[@value = "Sign in"] | //form[@id = "gigya-login-form"]//input[@name = "username"] | '.self::XPATH_LOADING)
        ) {
            unset($this->Answers[$this->Question]);

            return $this->LoadLoginForm() && $this->Login();
        }
        $this->saveResponse();

        if ($step == "Question") {
            if ($this->processSecurityCheckpoint()) {
                $this->markProxySuccessful();

                return true;
            }

            // it helps
            if (
                $this->waitForElement(WebDriverBy::xpath('//input[@value = "Sign in"] | //form[@id = "gigya-login-form"]//input[@name = "username"] | ' . self::XPATH_LOADING), 0)
                || $this->http->FindSingleNode('//form[@id = "gigya-login-form"]//input[@name = "username"]/@name')
            ) {
                unset($this->Answers[$this->Question]);

                return $this->LoadLoginForm() && $this->Login();
            }
        }
        unset($this->Answers[$this->Question]);

        // it works
        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        $accessToken = $this->driver->executeScript("
            let accessToken = null;
            for (var i = 0; i < localStorage.length; i++) {
                var obj = localStorage.getItem(localStorage.key(i));
                if (/\.accessToken/.test(localStorage.key(i))) {
                    console.log(obj);
                    accessToken = obj;
                }
            }
            return accessToken;
        ");
        $this->idToken = $this->driver->executeScript("
            let idToken = null;
            for (var i = 0; i < localStorage.length; i++) {
                var obj = localStorage.getItem(localStorage.key(i));
                if (/\.idToken/.test(localStorage.key(i))) {
                    console.log(obj);
                    idToken = obj;
                }
            }
            return idToken;
        ");

        $this->saveResponse();

        try {
            $this->callRetry();
        } catch (WebDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } catch (UnknownServerException | WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 1);
        }

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException | UnknownServerException | Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 1);
        }

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->setDefaultHeader("authorization", $accessToken);

        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function Parse()
    {
        $this->parseWithCurl();
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "content-type"    => "application/json",
        ];
        $this->browser->RetryCount = 0;
        $this->browser->PostURL("https://akamai-gw.dbaas.aircanada.com/loyalty/profile/getProfileKilo?profiletype=complete", [], $headers);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog(null, 3, false, 'aeroplanProfile');

        if (empty($response->accountHolder)) {
            if ($this->fromIsLoggedIn == true && isset($response->message) && $response->message == 'Unauthorized') {
                throw new CheckRetryNeededException(2, 1);
            }

            return;
        }
        $accountHolder = $response->accountHolder;
        // Name
        $this->SetProperty("Name", beautifulName($accountHolder->name->firstName . " " . $accountHolder->name->lastName));
        // Aeroplan Number
        $this->SetProperty("AccountNumber", $accountHolder->loyalty->fqtvNumber ?? null);
        // Balance - Aeroplan Miles
        // refs #22366, 22458
//        $this->SetBalance($accountHolder->aeroplanProfile->points->totalPoints ?? null);
        // Family balance
        $this->SetProperty("FamilyBalance", $accountHolder->aeroplanProfile->points->totalPoolPoints ?? null);
        // Status
        $this->SetProperty("Status", $accountHolder->aeroplanProfile->statusCode ?? null);
        // Status is valid Until
        $this->SetProperty("StatusIsValidUntil", str_replace('-', ' ', $accountHolder->aeroplanProfile->acTierExpiry ?? null));

        // refs #22458
        $this->logger->info('Balance', ['Header' => 3]);
        $this->browser->RetryCount = 0;
        $this->browser->GetURL("https://akamai-gw.dbaas.aircanada.com/loyalty/pooling/poolDashboard", $headers + ["authorization" => "Bearer " . $this->browser->getDefaultHeader("authorization")]);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog(null, 3, false, 'individualPoints');

        if (isset($response->hohffp) && $this->Properties['AccountNumber'] == $response->hohffp) {
            $this->SetBalance($response->hohPoints);
        } elseif (isset($response->memberDetails)) {
            foreach ($response->memberDetails as $memberDetail) {
                if ($this->Properties['AccountNumber'] !== $memberDetail->memberffp) {
                    continue;
                }

                $this->SetBalance($memberDetail->individualPoints);

                break;
            }
        }// if (isset($response->memberDetails))
        else {
            $this->SetBalance($accountHolder->aeroplanProfile->points->totalPoints);
        }

        // Account expiration date
        $this->logger->info('Expiration date', ['Header' => 3]);

        $this->getHistoryData();

        foreach ($this->history as $activityDetail) {
            $dateStr = $activityDetail->activityDate;
            $lastActivity = strtotime($dateStr);

            $this->SetProperty("LastActivity", date("M d, Y", $lastActivity));

            break;
        }// foreach ($this->history as $activityDetail)

        if ($exp = $accountHolder->aeroplanProfile->points->pointsExpiry ?? null) {
            $exp = strtotime($exp);
            $this->SetExpirationDate($exp);
        }// if ($exp = $accountHolder->aeroplanProfile->points->pointsExpiry ?? null)
        elseif (isset($accountHolder->aeroplanProfile->points) && $accountHolder->aeroplanProfile->points->pointsExpiry === null) {
            // refs #21119
            if (
                isset($accountHolder->aeroplanProfile->statusCode, $lastActivity)
                && $accountHolder->aeroplanProfile->statusCode == 'BASE'
            ) {
                $exp = strtotime("+18 months", $lastActivity);
                $warning = "The balance on this award program due to expire on " . date("m/d/Y", $exp) . "
<br />
<br />
Air Canada (Aeroplan) states the following on their website: <a href=\"https://www.aircanada.com/ca/en/aco/home/aeroplan/your-aeroplan/inactivity-policy.html#/\" target=\"_blank\">&quot;You have 18 months before your Aeroplan points expire if there has been no activity in your account - meaning you haven’t earned, redeemed, donated, transferred or converted any points. But so long as you stay active, your points won’t expire at all&quot;</a>.
<br />
<br />
We determined that the last time you had account activity with Aeroplan was on " . date("m/d/Y", $lastActivity) . ", so the expiration date was calculated by adding 18 months to this date.";

                // 30 Nov 2025, refs #21119
                if ($exp < 1764460800) {
                    $exp = 1764460800;
                    $warning = "<a href='https://www.aircanada.com/ca/en/aco/home/aeroplan/news/points-expiry-suspended.html#/'>Air Canada (Aeroplan) states on its website that all point expirations are suspended through November 30, 2025.</a>";
                }

                $this->SetExpirationDate($exp);
                $this->SetProperty("AccountExpirationWarning", $warning);
            } elseif (
                isset($accountHolder->aeroplanProfile->statusCode, $lastActivity)
                && $accountHolder->aeroplanProfile->statusCode != 'BASE'
            ) {
                $this->SetExpirationDateNever();
                $this->ClearExpirationDate();
                $this->SetProperty("AccountExpirationWarning", "do not expire with elite status");
            }
        }// elseif (isset($accountHolder->aeroplanProfile->points) && $accountHolder->aeroplanProfile->points->pointsExpiry === null)

        /*
        if (!empty($accountHolder->aeroplanProfile->millionMile)) {
            $this->sendNotification("refs #19674 - millionMile not null");
            $this->http->GetURL("https://www.aircanada.com/aeroplan/member/dashboard/status");
            sleep(5);
            $this->saveResponse();
        }
        */

        $this->browser->GetURL("https://akamai-gw.dbaas.aircanada.com/loyalty/currency",
            $headers + [
                "authorization" => "Bearer " . $this->browser->getDefaultHeader("authorization"),
                'version'       => 'V6',
            ]);
        $response = $this->browser->JsonLog();
        // Status Qualifying Dollars
        $this->SetProperty("QualifyingDollars", $response->currencyThresholds->currentValues->SQD ?? null);
        // Status Qualifying Segments
        $this->SetProperty("QualifyingSegments", $response->currencyThresholds->currentValues->SQS ?? null);
        // Status Qualifying Miles
        $this->SetProperty("QualifyingMiles", $response->currencyThresholds->currentValues->SQM ?? null);

        $this->browser->GetURL("https://akamai-gw.dbaas.aircanada.com/loyalty/webviewprofile");
        $response = $this->browser->JsonLog();
        // Member since
        $this->SetProperty('EnrollmentDate', $response->loyaltyProfile->memberAccount->enrolmentDate ?? null);

        // refs #21121
        $this->logger->info('eUpgrade credits / Flight Reward Certificate', ['Header' => 3]);
        $this->browser->GetURL("https://akamai-gw.dbaas.aircanada.com/loyalty/benefits?generateMllpPasses=true");
        $response = $this->browser->JsonLog(null, 3, false, 'EUPGSTANDARD');
        $pointDetails = $response->pointDetails ?? [];

        foreach ($pointDetails as $pointDetail) {
            if (!in_array($pointDetail->pointType, [
                'EUPGSTANDARD', // eUpgrade credits
                'FRC', // Flight Reward Certificate
            ]
            )) {
                continue;
            }

            $expiryDetails = $pointDetail->expiryDetails ?? [];
            unset($exp);

            $expData = [];

            foreach ($expiryDetails as $expiryDetail) {
                if (
                    (!isset($exp) || $exp > strtotime($expiryDetail->expiryDate))
                    && $expiryDetail->points > 0
                ) {
                    $exp = $expiryDetail->expiryDate;
                    $expData = [
                        'ExpiringBalance' => $expiryDetail->points,
                        'ExpirationDate'  => strtotime($exp),
                    ];
                }
            }// foreach ($expiryDetails as $expiryDetail)

            $displayName = 'eUpgrade credits';
            $code = 'UpgradeCredits';

            if ($pointDetail->pointType == 'FRC') {
                $displayName = 'Flight Reward Certificate';
                $code = 'FlightRewardCertificate';
            }

            $this->AddSubAccount([
                'Code'        => $code,
                'DisplayName' => $displayName,
                'Balance'     => $pointDetail->points,
            ] + $expData);
        }// foreach ($pointDetails as $pointDetail)
    }

    public function ParseItineraries()
    {
        /*if (isset($this->tokenStorage)) {
            $data = [
                'COMPANY_CODE'       => 'AC',
                'COUNTRY'            => 'CA',
                'LANGUAGE'           => 'US',
                'LANGUAGE_CHARSET'   => 'utf-8',
                'SITE'               => 'SAADSAAD',
                'aToken'             => $this->tokenStorage->accessToken,
                'countryOfResidence' => 'CA',
                'iToken'             => $this->tokenStorage->idToken,
            ];
            $headers = [
                'Accept'        => 'application/json, text/plain, * / *',
                'Content-Type'  => 'application/json',
                'authorization' => null,
                'Origin'        => 'https://www.aircanada.com',
                'Referer'       => 'https://www.aircanada.com/',
            ];
            $this->browser->PostURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgl?LANGUAGE=US&IS_HOME_PAGE=TRUE', json_encode($data), $headers);
        }*/

        try {
            $this->http->GetURL("https://www.aircanada.com/home/us/en/aco/trips");
            sleep(4);
            $this->saveResponse();

            if ($this->AccountFields['Login'] == 'violaj2011@gmail.com') {
                $this->http->GetURL("https://www.aircanada.com/ca/en/aco/home/app.html#/retrieve?bookingRefNumber=4PBX5K&lastName=Viola");
                sleep(5);
                $this->saveResponse();
            }
            $this->http->GetURL("https://www.aircanada.com/us/en/aco/home/app.html#/retrievepnr");
        } catch (\Exception | NoSuchDriverException | UnknownServerException $e) {
            $this->logger->warning("Exception: " . $e->getMessage());

            return [];
        }

        $this->saveResponse();
        $bookingReference = $this->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'booking_')][1]"), 30);
        //$this->parseWithCurl();

        if (!$bookingReference) {
            $noIts = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You do not have any upcoming flights.")]'), 5);
            $this->saveResponse();

            if ($noIts) {
                return $this->itinerariesMaster->setNoItineraries(true);
            }

            /*$bookingReference = $this->waitForElement(WebDriverBy::xpath("//div[contains(@id, 'booking_')][1]"), 20);

            if (!$bookingReference) {
                $noIts = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "You do not have any upcoming flights.")]'), 5);
                $this->saveResponse();

                if ($noIts) {
                    return $this->itinerariesMaster->setNoItineraries(true);
                }
            }*/
        }
        $this->saveResponse();

        //$this->parseWithCurl();
        $result = [];
        $bookingNumbers = $this->http->FindNodes("//div[contains(@id, 'booking_')]//span[@class='booking-refrence']", null, '/:\s*([A-Z\d]+)/');

        if (!$bookingNumbers) {
            return [];
        }

        //$this->http->cleanup();
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
        //$this->browser->removeCookies();
        $this->browser->LogHeaders = true;
        $this->browser->setRandomUserAgent();
        //$this->browser->setHttp2(true);
        //$this->browser->SetProxy($this->proxyReCaptchaIt7());

        //$this->browser->GetURL("https://www.aircanada.com/ca/en/aco/home.html#/retrievepnr");
        //$this->browser->SetProxy($this->proxyReCaptcha());

        foreach ($bookingNumbers as $bookingNumber) {
            $this->logger->info('Parse Itinerary #' . $bookingNumber, ['Header' => 3]);
            $result = array_merge($result,
                $this->ParseItinerariesAeroplanViaAircanadaRetrieve([$bookingNumber])
            );
            $this->increaseTimeLimit();
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->browser)) {
            // parse with curl
//            $this->browser = new HttpBrowser("none", new CurlDriver());
//            $this->http->brotherBrowser($this->browser);
        }

//        if ($asset = $this->browser->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#") ?? $this->browser->FindPreg('# src="([^\"]+)"></script></body>#')) {
//            $sensorPostUrl = "https://www.aircanada.com{$asset}";
//            $this->browser->NormalizeURL($sensorPostUrl);
//            //$this->sendStaticSensorDataNew($sensorPostUrl);
//            $this->getSensor($sensorPostUrl);
//        }
        $this->retrievePost($arFields);
        $itinData = $this->browser->JsonLog(null, 3, true);

        if ($this->browser->Response['code'] == 403 || !$itinData) {
            $this->sendStatistic(false);

            return null;
        }
        $this->sendStatistic(true);

        if ($this->browser->FindPreg('/"errorCode":"8132"/')
            || $this->browser->FindPreg('/"errorCode":"8104"/')
        ) {
            $msg = "The booking reference you entered doesn't appear to be valid. Make sure you're entering an Air Canada booking reference.";
            $this->logger->error($msg);

            return $msg;
        }

        if ($this->browser->FindPreg('/"errorCode":"RT_PNRT_00[56]"/')) {
            $msg = "We are temporarily unable to process your request. Please try again later. (err-7)";
            $this->logger->error($msg);

            return $msg;
        }
        // This booking has been cancelled
        if ($msg = $this->browser->FindPreg('/(This booking has been cancelled\.)/')) {
            $it = ['Kind' => 'T'];
            $it['RecordLocator'] = $arFields['ConfNo'];
            $it['Cancelled'] = true;

            return null;
        }

        if (/*$this->browser->FindPreg('/"errorCode":"3"/')
            || */ $this->browser->FindPreg('/"errorCode":"4649"/')
        ) {
            $msg = "Sorry, we're not able to display this booking online. To make changes to this booking, please contact Air Canada ReservationsOpens in a new window for assistance or talk to your travel agent.";
            $this->logger->error($msg);

            return $msg;
        }
        $it = $this->parseItineraryAircanadaBkgd($itinData);

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "DATE"        => "PostingDate",
            "DESCRIPTION" => "Description",
            "AMOUNTS"     => "Miles",
            "BONUS"       => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (empty($this->history)) {
            $this->getHistoryData();
        }

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function getHistoryData()
    {
        $this->logger->notice(__METHOD__);

        $fromDate = date('Y-m-d', strtotime('-2 years'));
        $toDate = date('Y-m-d');
        $this->increaseTimeLimit(100);
        $this->browser->GetURL("https://akamai-gw.dbaas.aircanada.com/loyalty/transaction?offset=0&limit=1000&sort=desc&fromdate={$fromDate}&todate={$toDate}&type=all&ispoolingtransaction=false&sortby=date&pointType=BASE,BONUS,SQM,BSQM,RSQM,SQS,BSQS,SQD,BSQD&ln=en");

        $this->history = $this->browser->JsonLog()->activityDetails ?? [];
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//form[@id = "gigya-login-form"]//iframe[not(@style="display: none;")]/@src', null, true, "/\&k=([^\&]+)/");
        $key = '6LdLN7YpAAAAAITafNtN-ZFSybOWKv5HcsHkXeEJ';

        if (!$key) {
            return false;
        }
        /* $postData = [
             "type"          => "RecaptchaV2EnterpriseTaskProxyless",
             "websiteURL"    => $this->http->currentUrl(),
             "websiteKey"    => $key,
             "apiDomain"     => 'www.google.com',
         ];
         $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
         $this->recognizer->RecognizeTimeout = 120;

         return $this->recognizeAntiCaptcha($this->recognizer, $postData);*/

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;

        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($time = 5)
    {
        $this->logger->notice(__METHOD__);
        sleep($time);
        $logout = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), $time + 2, false)
            ?? $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0);
        $this->saveResponse();

        if ($this->http->FindSingleNode(self::XPATH_LOADING) || $this->http->FindPreg(self::REGEXP_LOADING)) {
            $logout = $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), $time + 2, false)
                ?? $this->waitForElement(WebDriverBy::xpath(self::XPATH_LOGOUT), 0);
            $this->saveResponse();
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We are not able to retrieve your profile information at the moment. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//Message[contains(text(), "We encountered an internal error. Please try again.")]')) {
            throw new CheckRetryNeededException(3, 5, $message);
        }

        // it helps
        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "Sorry the page you\'re looking for was not found.")
                or contains(text(), "We\'re sorry, something went wrong. We\'re working to resolve the issue.")
            ]')
        ) {
            $this->State['NewFingerprint'] = true;
            $this->State['DoNotKeepProfile'] = true;

            throw new CheckRetryNeededException(3, 1, $message);
        }

        $this->needToAcceptTerms();

        if ($logout) {
            $this->tokenStorage = $this->driver->executeScript("
        let result = {idToken: '', accessToken: ''};
        for (let i = 0; i < localStorage.length; i++) {
            const key = localStorage.key(i);
            if (key.endsWith('.idToken')) {
                result.idToken = localStorage.getItem(key);
             } else if (key.endsWith('.accessToken')) {
                result.accessToken = localStorage.getItem(key);
             }
        }
        return JSON.stringify(result);
                ");
            $this->logger->debug('tokenStorage: ' . $this->tokenStorage);

            if (!empty($this->tokenStorage)) {
                $this->tokenStorage = $this->http->JsonLog($this->tokenStorage);
            }

            return true;
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function callRetry()
    {
        $this->logger->notice(__METHOD__);
        // We're sorry. Something went wrong and your request was not submitted. Please try again.
        if ($this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We\'re sorry. Something went wrong and your request was not submitted. Please try again.")]'), 0)) {
            $this->State['NewFingerprint'] = true;
            $this->State['DoNotKeepProfile'] = true;

            throw new CheckRetryNeededException(2, 1);
        }
    }

    private function ParseItinerariesAeroplanViaAircanadaRetrieve($confs, $lastName = null)
    {
        $this->logger->notice(__METHOD__);
        $res = [];

        $this->logger->debug("[lastName]: {$lastName}");

        if (!$lastName) {
            $name = ArrayVal($this->Properties, 'Name');
            $this->logger->debug("[lastName]: {$name}");
            $lastName = $this->browser->FindPreg('/(?:Mr|Mrs|Ms|Dr|M)[.]?\s+([\w-]+)\s*$/i', false, $name);
            $this->logger->debug("[lastName]: {$lastName}");

            if (!$lastName) {
                $lastName = $this->browser->FindPreg('/([\w-]+)$/i', false, $name);
                $this->logger->debug("[lastName]: {$lastName}");
            }
        }

        if (!$lastName) {
            $this->sendNotification('empty last name for retrieve');

            return [];
        }
        $this->logger->debug("[lastName]: {$lastName}");

        foreach ($confs as $conf) {
            try {
                $this->http->GetURL("https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook");

                try {
                    sleep(2);
                    $confNoInput = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_bookingRefNumber"), 7);
                    $lastNameInput = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_lastName"), 0);
                    $btn = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_findContent"), 0);
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                    sleep(2);
                    $this->saveResponse();
                    $confNoInput = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_bookingRefNumber"), 7);
                    $lastNameInput = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_lastName"), 0);
                    $btn = $this->waitForElement(WebDriverBy::id("bkmgMyBookings_findContent"), 0);
                }

                if ($confNoInput && $lastNameInput && $btn) {
                    $confNoInput->sendKeys($conf);
                    $lastNameInput->sendKeys($lastName);
                    sleep(2);
                    $this->saveResponse();

                    $cookies = $this->driver->manage()->getCookies();

                    foreach ($cookies as $cookie) {
                        $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                            $cookie['expiry'] ?? null);
                    }
                }
                sleep(1);
                $this->saveResponse();
            } catch (Exception $e) {
                $this->logger->error($e->getMessage());
            }

            $arFields = [
                'ConfNo'   => $conf,
                'LastName' => $lastName,
            ];
            $it = [];
            $this->logger->info(sprintf('Retrieve Parse Itinerary #%s', $conf), ['Header' => 3]);

            if ($this->CheckConfirmationNumberInternal($arFields, $it) === []) {
                return [];
            }

            if ($it && is_array($it)) {
                $res[] = $it;
            }
        }

        return $res;
    }

    private function getSensorDataFromSelenium($ff53)
    {
        $this->logger->notice(__METHOD__);

        $cache = Cache::getInstance();
        $cacheKey = "sensor_data_aeroplan" . sha1($this->browser->getDefaultHeader("User-Agent"));
        $data = $cache->get($cacheKey);

        /* if (!empty($data) || $ff53 === true) {
             $this->logger->info("got cached sensor data:");
             $this->logger->info($data);

             return $data;
         }*/

        $selenium = clone $this;
        $this->browser->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            if ($this->browser->FindPreg('#Chrome|Safari|WebKit#ims', false, $this->browser->getDefaultHeader("User-Agent"))) {
                if ($ff53 === true) {
                    $selenium->useFirefox();
                } elseif (rand(0, 1) == 1) {
                    $selenium->useGoogleChrome();
                } else {
                    $selenium->useChromium();
                }
            } else {
                $selenium->useFirefox();
            }

            $selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->removeCookies();
            $sensor_data = $this->interceptSensorData($selenium);

            if (empty($sensor_data)) {
                $sensor_data = $this->interceptSensorData($selenium);

                if (empty($sensor_data)) {
                    $sensor_data = $this->interceptSensorData($selenium);
                }
            }
            /*
             $selenium->http->GetURL("https://www.aircanada.com/ca/en/aco/home.html#/retrievepnr");

            usleep(300);
            $this->logger->info("confirm dialog loaded");
            $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                        if (/sensor_data/g.exec( data )) {
                            console.log('ajax');
                            console.log(data);
                            localStorage.setItem('sensor_data', data);
                        }
                    };
                })(XMLHttpRequest.prototype.send);");

            // save page to logs
            $selenium->http->SaveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            sleep(2);
            $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");
            */

            if (!empty($sensor_data)) {
                $data = @json_decode($sensor_data, true);

                if (is_array($data) && isset($data["sensor_data"])) {
                    $this->logger->info("got new sensor data:");
                    $this->logger->info($data['sensor_data']);
                    $cache->set($cacheKey, $data["sensor_data"], 1000);
                    $this->sensor_data = $data['sensor_data'];

                    return $data["sensor_data"];
                }
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return null;
    }

    private function interceptSensorData($selenium, $sleep = 300)
    {
        $selenium->http->GetURL("https://www.aircanada.com/ca/en/aco/home.html#/");
        $myBooking = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'My booking')]"), 15);

        if ($myBooking) {
            $myBooking->click();
            sleep(1);
        }

        $input = $this->waitForElement(WebDriverBy::id("bookings_passenger_lastname"), 3);
        $selenium->driver->executeScript("(function(send) {
                    XMLHttpRequest.prototype.send = function(data) {
                        if (/sensor_data/g.exec( data )) {
                            console.log('ajax');
                            console.log(data);
                            localStorage.setItem('sensor_data', data);
                        }
                    };
                })(XMLHttpRequest.prototype.send);");

        if ($input) {
            usleep($sleep);
            $input->click();
        }

        // save page to logs
        $selenium->http->SaveResponse();
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
        sleep(2);
        $sensor_data = $selenium->driver->executeScript("return localStorage.getItem('sensor_data');");

        return $sensor_data;
    }

    private function airCanadaSensorData($sensorPostUrl, $seleniumSensor = false, $ff53 = false)
    {
        $this->logger->notice(__METHOD__);

        $form = $this->browser->Form;
        $formURL = $this->browser->FormURL;
        $referer = $this->browser->currentUrl();
//        $asset = $this->browser->FindPreg('# src="([^\"]+)"></script></body>#');
//
//        if (!$asset) {
//            $asset = $this->browser->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#");
//        }

//        if ($asset) {
//            $sensorPostUrl = "https://www.aircanada.com{$asset}";
//            $this->browser->NormalizeURL($sensorPostUrl);
        sleep(1);
        $this->browser->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        if ($seleniumSensor == true) {
            try {
                $sensorData = [
                    'sensor_data' => stripslashes($this->getSensorDataFromSelenium($ff53)),
                ];
            } catch (UnknownServerException | TimeOutException | SessionNotCreatedException | WebDriverCurlException | ScriptTimeoutException $e) {
                $this->logger->error("SensorData exception: " . $e->getMessage());
                $this->DebugInfo = "SensorData Exception";
                $sensorData = [
                    'sensor_data' => stripslashes($this->getSensorDataFromSelenium($ff53)),
                ];
            }
        } else {
            /*
            $sensorData = [
                'sensor_data' => $this->getSensorData(),
            ];
            $this->browser->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
            sleep(1);
            */
            $sensorData = [
                'sensor_data' => $this->getSensorDataTwo(),
            ];
        }
        $this->browser->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->browser->JsonLog();
        $this->browser->RetryCount = 2;
        sleep(1);
//        } else {
//            $this->logger->error("sensor_data URL not found");
//        }

        $this->browser->Form = $form;
        $this->browser->FormURL = $formURL;
        $this->browser->setDefaultHeader("Referer", $referer);
    }

    private function retrievePost($arFields, $seleniumSensor = false)
    {
        $this->logger->notice(__METHOD__);

        /*if ($asset = $this->browser->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#") ?? $this->browser->FindPreg('# src="([^\"]+)"></script></body>#')) {
            $sensorPostUrl = "https://www.aircanada.com{$asset}";
            $this->browser->NormalizeURL($sensorPostUrl);
            //$this->sendStaticSensorDataNew($sensorPostUrl);
            $this->sendStaticSensorDataNewOne($sensorPostUrl);
        } else {
            $this->sendNotification('failed to retrieve itinerary 1 // MI');

            return null;
        }*/
        $response = null;

        //$this->airCanadaSensorData($arFields, $seleniumSensor);
        //$response = $this->getFromSelenium($arFields);

        if (empty($response)) {
//            $this->browser->OptionsURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd', [
//                'Accept'                         => '*/*',
//                'Content-Type'                   => null,
//                'Access-Control-Request-Headers' => 'content-type',
//                'Access-Control-Request-Method'  => 'POST',
//                'Origin'                         => 'https://www.aircanada.com',
//                'Referer'                        => 'https://www.aircanada.com/',
//                'Pragma'                         => 'no-cache',
//                'Cache-Control'                  => 'no-cache',
//                'Accept-Encoding'                => 'gzip, deflate, br',
//            ]);
            $this->logger->debug("[lastName]: {$arFields['LastName']}");

            $payload = '{"aeroplanNumber":"' . $this->Properties['AccountNumber'] . '","bookingRefNumber":"' . $arFields['ConfNo'] . '","lastName":"' . $arFields['LastName'] . '","iataNumber":"","agencyId":"","agentId":"","SITE":"SAADSAAD","COUNTRY":"CA","LANGUAGE":"US","countryOfResidence":"US","geoProvinceCode":"NY","profileProvinceCode":null,"LANGUAGE_CHARSET":"utf-8"}';
            $headers = [
                'Accept'          => 'application/json, text/plain, */*',
                'Accept-Encoding' => 'gzip, deflate, br',
                'Accept-Language' => 'en-US',
                'Content-Type'    => 'application/json',
                'Origin'          => 'https://www.aircanada.com',
                'Referer'         => 'https://www.aircanada.com/',
            ];
            $this->browser->RetryCount = 0;
            $this->increaseTimeLimit(120);
            $this->browser->PostURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd', $payload,
                $headers);
            $this->browser->RetryCount = 2;

            //$this->sendNotification("{$this->browser->Response['code']} // MI");
        }

        return $response;
    }

    private function sendStaticSensorDataNewOne($sensorPostUrl)
    {
        $this->logger->notice(__METHOD__);
        $ua = $this->http->userAgent;
        $mt = explode(' ', microtime());
        $abck = $this->http->getCookieByName('_abck');
        $r = substr((float) rand() / (float) getrandmax(), 0, -2);
        $r6 = array_rand(array_flip([11, 13, 43]), 1);
        $sensorData = [
            "7a74G7m23Vrp0o5c9281521.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402093,9472263,1920,1050,1920,1080,1920,296,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.200771613100,817104736131,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,0,1,922,739,236;1,1,950,752,234;2,1,951,753,234;3,1,1119,784,251;4,1,1124,785,251;5,1,1146,785,252;6,1,1178,786,253;-1,2,-94,-117,-1,2,-94,-111,0,1147,-1,-1,-1;-1,2,-94,-109,0,1147,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,14545,32,1147,1147,0,16807,1204,0,1634209472262,25,17482,0,7,2913,0,0,1210,9684,0,181E5C3658D221B6D5F5B346E532EEC3~-1~YAAQng1lX5XqBVR8AQAAPp95fgYV2u0ONY4xfvo0/kAm+SxY7aZEpDTjhEPvQJWBVB6uQ2BLHc6Q8SbXQbOYGh2SpfQcHwbDQuWdIkl0jpRVqey3zNulNt0qnBcLbDipRT+DUkT8JgkCOeQ25AhHoAPfNksoL4ZmGGBae+FTyc3oPYPvQ+ODmeTepygAkPLstgw/OfYsvlyxCxs/gvaWKgYZWFK0q5x4LNu1yFFxj5lja3j3ad+btTihIZCNrfip+VI+pyTP1H+dnNzP4z/i6gF3/YmdOgmaWbukTbhhnSK5zI7C+Oi2YGjZleLR383xUpdDLwyM5saGUJ0mZLFhAT+dUKqqOUq+An05+wYRCb0YMG3Ne2b14hZP10nhu02sV767VjGvpFLc2OAW8DUhoIJLoUsGP1ztl2UtR4zR+d3mNryjzAnt~-1~-1~-1,40118,33,1356954094,30261689,PiZtE,94239,27,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,20,20,40,20,20,0,0,0,0,640,340,140,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,28416714-1,2,-94,-118,217060-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;33;24;" . rand(1, 10000),
            "7a74G7m23Vrp0o5c9281521.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402093,9573520,1920,1050,1920,1080,1920,496,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.270001827135,817104786759.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,398,-1,-1,-1;-1,2,-94,-109,0,398,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,398,398,0,796,724,0,1634209573519,170,17482,0,0,2913,0,0,729,796,0,DB4DB88276499932CAEEB66235AF55BB~-1~YAAQng1lX9XuBVR8AQAApSt7fga+ffx59qpnSriAX+HZ/VMab8kZBaCH0bBO2OGMiVRVZZHkvYlZ7Fo4fdxPlkJRJIO/7drFL6RObbHTqQ4nAxQEOulAUPC59NJd5mTkU0Sain9Ku0hZIB5oTQ7bGalWh3wwYpuiUzmVLvHnE499R8Gl8UpJbG9InFY9zNnq7HboS5/qQlCy374VIh4+SwVsP0c67+kvQJmRXuAcgLEncbgjQkwkupWwXBySQSiFXhX+umRyLOpbeNTctCDP2H8oxBVnqeZ2gDMr4xHMQF7VqKS6qQ2PS/b0Ms7j3XgMI987MLXGoCmj0vZw7RAq9mRfG6yXpq6ykKt1ODiwU28nQxUBiemnTt+8h5S23vCHr1DwFhlv/7FNyYZH27xEZHv26ak+3tsVbMCeBzu83EDcbTjj8NYx~-1~-1~-1,39886,660,1128424014,30261689,PiZtE,64749,75,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,20,20,40,60,40,20,340,0,0,380,280,140,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,28720533-1,2,-94,-118,210870-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;54;13;" . rand(1, 10000),
            "7a74G7m23Vrp0o5c9281521.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.71 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,402093,9630964,1920,1050,1920,1080,1920,496,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7566,0.228417813114,817104815481,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,802,0,1634209630962,16,17482,0,0,2913,0,0,806,0,0,587FC0D1816EC2E3B9C131D0EC50ED6F~-1~YAAQng1lXxLxBVR8AQAA9Qt8fgYdFdzuYbUxfxFBdtZyidhVemhPnxnJzbzxHQeEtv0xIyaTGLLvnj0zpRz17MXMCmWLG93eqXgzEHOoXEYeadvCQGxow+h8GW0STHKC98uOgR28BaSlLEQua8zsv5pTOzgfeaakKOs9zTYxMPYfxzytuePYq2CD/k1tQfvMmelfF6VY3crFJAi4qWb0G7mAy1OTgP84GoieQFKFe6ochm2O5XODuAWI136ZmwhEEThENZQOX1F5uFM9kPw0c7ZCHZpjq2GgUcTtQtd73koRj6GZJJIevHyEocLbBnw/TguJ6kdhZPjmd1tSbTgK9sYyWODD6mUp9cImtpnebmVW6bq4VKLfI0rwdkA4USQNw/QmWjmiJvlfV1ZeS5i/r/vk4ubYXsrYfgZsY9yrcgo//CkWSSQK~-1~-1~-1,41327,99,-443441535,30261689,PiZtE,85431,95,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,40,40,40,220,60,40,40,20,0,0,0,420,260,120,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,144464457-1,2,-94,-118,208295-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,0,Google Inc. (Intel Open Source Technology Center),ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 (Core Profile) Mesa 20.0.8),0ee176382a3e6d94b05be93031f55881378b2a2ba4825ecd17f46ae840f0c4ed,36-1,2,-94,-121,;52;49;0" . rand(1, 10000),
        ];
        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->browser->RetryCount = 0;
        $this->browser->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->browser->JsonLog();
        sleep(1);

        return $key;
    }

    private function retrievePostOld($arFields, $seleniumSensor = false)
    {
        $this->logger->notice(__METHOD__);
        $this->browser->setHttp2(true);
        $this->browser->SetProxy($this->proxyReCaptcha());
        $this->browser->GetURL('https://www.aircanada.com/ca/en/aco/home.html#/');

        //if (!$this->browser->ParseForm('pnrRetrieveForm')) {
        if (!$this->browser->FindSingleNode("//div[@id='tab_magnet_panel_3']/@id")) {
            $this->sendNotification('failed to retrieve itinerary 1 // MI');

            return null;
        }

        $main = $this->http->FindPreg('/dist\/(main.bundle.js\?\d+)"/');

        $asset = $this->browser->FindPreg('# src="([^\"]+)"></script></body>#');

        if (!$asset) {
            $asset = $this->browser->FindPreg("#_cf\.push\(\['_setAu', '(/\w+/.+?)'\]\);#");
        }
        $sensorPostUrl = "https://www.aircanada.com{$asset}";
        $this->browser->NormalizeURL($sensorPostUrl);

        $this->airCanadaSensorData($sensorPostUrl, $seleniumSensor);

        $payload = [
            'bookingRefNumber'   => $arFields['ConfNo'],
            'lastName'           => $arFields['LastName'],
            'iataNumber'         => '',
            'agencyId'           => '',
            'agentId'            => '',
            'SITE'               => 'SAADSAAD',
            'mainbundlev'        => 'main.bundle.js?20201119184', // unset below if not found
            'COUNTRY'            => 'CA',
            'LANGUAGE'           => 'US',
            'countryOfResidence' => 'CA',
            'LANGUAGE_CHARSET'   => 'utf-8',
        ];

//        if ($main) {
//            $payload['mainbundlev'] = $main;
//        } else {
        unset($payload['mainbundlev']);
//        }
        $headers = [
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/json',
            'Origin'          => 'https://www.aircanada.com',
            //            'Referer' => 'https://www.aircanada.com/ca/en/aco/home.html',
            'Referer' => 'https://www.aircanada.com',
        ];
        $this->browser->RetryCount = 0;
        $this->increaseTimeLimit(120);
        $this->browser->PostURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd?LANGUAGE=US&IS_HOME_PAGE=TRUE', json_encode($payload), $headers);

//        if ($this->browser->Response['code'] == 403) {
//            $this->airCanadaSensorData($sensorPostUrl, false);
//            $this->browser->PostURL('https://book.aircanada.com/ACWebOnline/ACRetrieve/bkgd?LANGUAGE=US&IS_HOME_PAGE=TRUE', json_encode($payload), $headers);
//
//            if ($this->browser->Response['code'] != 403) {
//                $this->sendNotification('success retry sensor data // MI');
//            }
//        }
        $this->browser->RetryCount = 2;

        return true;
    }

    private function parseItineraryAircanadaBkgd($data)
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];

        $conf = $this->arrayVal($data, ['data', 'id']);
        // RecordLocator
        $res['RecordLocator'] = $conf;
        $this->logger->info("Parse Itinerary #$conf", ['Header' => 3]);
        $totalPrices = $this->arrayVal($data, ['data', 'air', 'prices', 'totalPrices', 0], []);
        // TotalCharge
        $total = $this->arrayVal($totalPrices, ['total', 'value']);

        if ($total) {
            $res['TotalCharge'] = $total / 100;
        }
        // Currency
        $res['Currency'] = $this->arrayVal($totalPrices, ['total', 'currencyCode']);
        // BaseFare
        $baseFare = $this->arrayVal($totalPrices, ['base', 'value']);

        if ($baseFare) {
            $res['BaseFare'] = $baseFare / 100;
        }
        // Tax
        $tax = $this->arrayVal($totalPrices, ['totalTaxes', 'value']);

        if ($tax) {
            $res['Tax'] = $tax / 100;
        }

        if (isset($res['TotalCharge']) && $res['TotalCharge'] == 0) {
            $this->sendNotification("zero TotalCharge // RR");
        }

        if (isset($res['BaseFare']) && $res['BaseFare'] == 0) {
            $this->sendNotification("zero BaseFare // RR");
        }
        // Tax
        if (isset($res['Tax']) && $res['Tax'] == 0) {
            $this->sendNotification("zero Tax // RR");
        }

        // Passengers
        $res['Passengers'] = [];

        foreach ($this->arrayVal($data, ['data', 'travelers'], []) as $traveler) {
            $firstName = $this->arrayVal($traveler, ['names', 0, 'firstName'], '');
            $lastName = $this->arrayVal($traveler, ['names', 0, 'lastName'], '');
            $title = $this->arrayVal($traveler, ['names', 0, 'title'], '');
            $name = trim(beautifulName("$title $firstName $lastName"));

            if ($name) {
                $res['Passengers'][] = $name;
            }
        }
        // AccountNumbers
        $res['AccountNumbers'] = [];

        foreach ($this->arrayVal($data, ['data', 'frequentFlyerCards'], []) as $card) {
            $number = $card['cardNumber'] ?? null;

            if ($number) {
                $res['AccountNumbers'][] = $number;
            }
        }
        // Seats
        $flightIdToSeats = [];

        foreach ($this->arrayVal($data, ['data', 'seats'], []) as $item) {
            $flightId = $item['flightId'] ?? null;

            if (!$flightId) {
                continue;
            }
            $flightIdToSeats[$flightId] = [];

            foreach (($item['seatSelections'] ?? []) as $sel) {
                $seatNumber = $sel['seatNumber'] ?? null;

                if ($seatNumber) {
                    $flightIdToSeats[$flightId][] = $seatNumber;
                }
            }
        }
        $flightDict = $this->arrayVal($data, ['dictionaries', 'flight'], []);
        // TripSegments
        $boundIndex = 0;

        foreach ($this->arrayVal($data, ['data', 'air', 'bounds'], []) as $bound) {
            $boundFlights = $this->arrayVal($bound, ['flights'], []);

            if (!$boundFlights) {
                continue;
            }

            foreach ($boundFlights as $boundFlight) {
                $flightId = $boundFlight['id'] ?? null;
                $flight = $flightDict[$flightId] ?? null;

                if (!$flight) {
                    $this->sendNotification('check aircanada flights // MI');

                    continue;
                }
                // FlightNumber
                $ts['FlightNumber'] = $flight['marketingFlightNumber'] ?? null;
                // AirlineName
                $ts['AirlineName'] = $flight['marketingAirlineCode'] ?? null;
                // DepCode
                $ts['DepCode'] = $flight['departure']['locationCode'] ?? null;
                // ArrCode
                $ts['ArrCode'] = $flight['arrival']['locationCode'] ?? null;
                // DepartureTerminal
                $ts['DepartureTerminal'] = $flight['departure']['terminal'] ?? null;
                // ArrivalTerminal
                $ts['ArrivalTerminal'] = $flight['arrival']['terminal'] ?? null;
                // Duration
                $dur = $flight['duration'] ?? null;

                if ($dur) {
                    $dur = $dur / 60;
                    $mins = intval(($dur % 60));
                    $hours = intval(($dur - $mins) / 60);
                    $ts['Duration'] = "{$hours}h {$mins}m";
                }
                // DepDate
                $depDate = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'departureLocal']);
                $ts['DepDate'] = strtotime($depDate);

                if (!$ts['DepDate']) {
                    $ts['DepDate'] = ($flight['departure']['dateTime'] ?? null) / 1000;
                }
                // ArrDate
                $arrDate = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'arrivalLocal']);
                $ts['ArrDate'] = strtotime($arrDate);

                if (!$ts['ArrDate']) {
                    $ts['ArrDate'] = ($flight['arrival']['dateTime'] ?? null) / 1000;
                }
                // Aircraft
                $ts['Aircraft'] = $flight['aircraftCode'] ?? null;

                if ($ts['Aircraft']) {
                    $ts['Aircraft'] = trim($ts['Aircraft']);
                }
                // Cabin
                $ts['Cabin'] = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'cabinCode']);
                // BookingClass
                $ts['BookingClass'] = $bound['flights'][0]['bookingClass'] ?? null;
                // Meal
                $ts['Meal'] = $this->arrayVal($data, ['meta', 'tripBoundInfo', "$boundIndex", 'tripSegmentInfo', $flightId, 'listMeal', 1, 'mealDesc']);
                $res['TripSegments'][] = $ts;
            }
            $boundIndex++;
        }

        if (empty($flightDict)) {
            $this->sendNotification("check its by fareInfos// ZM");
            $infos = $this->arrayVal($data, ['data', 'travelDocuments', 'fareInfos'], []);

            foreach ($infos as $info) {
                $flight = $info['flight'] ?? null;

                if (!$flight) {
                    $this->sendNotification('check aircanada fareInfos');

                    continue;
                }
                // FlightNumber
                $ts['FlightNumber'] = $flight['marketingFlightNumber'] ?? null;
                // AirlineName
                $ts['AirlineName'] = $flight['marketingAirlineCode'] ?? null;
                // DepCode
                $ts['DepCode'] = $flight['departure']['locationCode'] ?? null;
                // ArrCode
                $ts['ArrCode'] = $flight['arrival']['locationCode'] ?? null;
                // DepartureTerminal
                $ts['DepartureTerminal'] = $flight['departure']['terminal'] ?? null;
                // ArrivalTerminal
                $ts['ArrivalTerminal'] = $flight['arrival']['terminal'] ?? null;
                // DepDate
                $ts['DepDate'] = ($flight['departure']['dateTime'] ?? null) / 1000;
                // ArrDate
                $ts['ArrDate'] = ($flight['arrival']['dateTime'] ?? null) / 1000;

                if (!$ts['ArrDate']) {
                    $ts['ArrDate'] = MISSING_DATE;
                }
                $res['TripSegments'][] = $ts;
            }
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $activityDetails = $this->history;

        foreach ($activityDetails as $activityDetail) {
            $dateStr = $activityDetail->activityDate;
            $postDate = strtotime($dateStr);

            if (!$postDate) {
                $this->logger->notice("skip {$dateStr}");

                continue;
            }

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }// if (isset($startDate) && $postDate < $startDate)

            $result[$startIndex]['DATE'] = $postDate;
            $result[$startIndex]['DESCRIPTION'] = $activityDetail->partnerName . " | " . $activityDetail->activityDescription;

            foreach ($activityDetail->pointDetails as $pointDetail) {
                if ($pointDetail->pointType == 'BONUS') {
                    $result[$startIndex]['BONUS'] = $pointDetail->points;

                    continue;
                }

                if (!isset($result[$startIndex]['AMOUNTS'])) {
                    $result[$startIndex]['AMOUNTS'] = $pointDetail->points;
                } else {
                    $result[$startIndex]['AMOUNTS'] += $pointDetail->points;
                }
            }
            $startIndex++;
        }

        return $result;
    }

    private function getSensorDataTwo($secondSensor = false)
    {
        $this->logger->notice(__METHOD__);
        $sensorData = [
            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en,Gecko,3,0,0,0,400581,6034379,1920,1050,1920,1080,1920,451,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7610,0.228989309114,814033017189,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1628066034378,-999999,17416,0,0,2902,0,0,11,0,0,9EB38B889F2DBA8F931E45AF834F638C~-1~YAAQhl5swZCkjQx7AQAAfTFMEAZ5VYuq10ZdwehsmVvYRDWO+oZVlJ9pDCllidCUQrBia/WoN/cmsmbvdy81poO+fgPEOA9lD5ywdHuB4ivTdtyKKYe34zfKKbML7clmLYeupGq8inOPbItA/zjY1TqQiPxg6qZ8JIBIZfQUCLD3FIzfE8rE0KkWpLj+OxBY2tjOec8AIpN+5f2UOwFaJh7RFqYFcZex5JPp3N5uRxASOjkpNoxjKzbPuBe4Axgw6C0kQQAaDKVSfOCGQ7cNQ3zIKvHH1IwJFBQnigOUb0FDfry31ctfSfCbeoSzx2frDBZcCThEW2nKp3GXSJBbqcP+fEt6AN9KyFsA3/hyjscUzqmRpX4DnFkAozY8MSc3YUKimUcYUbmxhFHH~-1~-1~-1,37517,-1,-1,30261689,PiZtE,36681,51,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,90515643-1,2,-94,-118,202658-1,2,-94,-129,-1,2,-94,-121,;11;-1;0",
            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en,Gecko,3,0,0,0,400581,6093536,1920,1050,1920,1080,1920,451,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7610,0.430852018215,814033046768,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1628066093536,-999999,17416,0,0,2902,0,0,4,0,0,752429DCF12E5782402100A7D12F4BD2~-1~YAAQm3p7XM1GWbB6AQAAJxlNEAbvP2+v6vsOZN1w93D9VT8xvRZ3qW18rapVUHk5gRwilOPlWyCkuvidQEr8brtrk9lQAu3YmQMuEsFr8a/7XqsTN5yX1Gi00IAudkFPJR9fnYdVSLfpK5RlJ1gdO83Yn9WyPtXMjVq4upxYJUYvI+HOKc5SJbEWHZmtFnhRtdOkTM/z4n8zUtdnvG2MAvXQyYYWL+AVTsN4x9WMjDiDa3QzFFw5TheUvVDQ6O9xjJDygZHZNAOWubef/g37Yh/s7zNiJuUw5WdC6viMiK2AalMIOOX7FCG+VK6Li7sbpIqRPWl6oQnv1srRmkEMvA/yUpWnVh8ITQjsIk6CdW+RhAJkHEi+sTJiAiSzXoCU82YXkZD7uqUNvY5a~-1~-1~-1,37113,-1,-1,30261689,PiZtE,33685,42,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,18280611-1,2,-94,-118,202235-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.114 Safari/537.36,uaend,12147,20030107,en,Gecko,3,0,0,0,400581,6115036,1920,1050,1920,1080,1920,451,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7610,0.697323034348,814033057518,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1628066115036,-999999,17416,0,0,2902,0,0,3,0,0,240260DFB849BF45B0864F7948E0DF66~-1~YAAQm3p7XABHWbB6AQAAVGxNEAZHxSvdFmgkW0ul7hMC5LE4NvCrGkJMjsutQVpXrHvQTRQ91zCPpZqu+IMVmus9hmk541hjoJeS5aBISZxA4VbGokLdYNLVC4MYdgiRAx6qIqR4sc+0oJZ6iP8I+E0tWW3ZAZixG9LEcAAbQHawna9koLL5X8Tnzsf+zbQfxvgnI+E7UF4LLq0PXSeAC7M6iOsNnLzLkynqimATHTAUYX4V2WOVCT+sMB33hgTP2qlPIZJE4nlPZHnx/SljourvZ0u3yvVHbo9oe+KrtUPSEYAhcgPz2Z7f8T76zW/5xhJReCx5NH9FHBeW3BDwob/WTE433mESYGipUC1VNjVAqqiTZtjVj/Sv9Kif13a/6RuXCy07/tnc31yh~-1~-1~-1,36625,-1,-1,30261689,PiZtE,58878,62,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,18345099-1,2,-94,-118,201754-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",

            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,400581,6225186,1920,1050,1920,1080,1920,477,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.860229074430,814033112593,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,2,0,1628066225186,-999999,17416,0,0,2902,0,0,6,0,0,05F483D0F66AD8A1CFCD1736C2A3A698~-1~YAAQhl5swZGwjQx7AQAAVhtPEAZrhpdrf/VD3G30MRHgSpYNx/G54E4LLuY/NICQ+y91cpDmZTt/qMJm2Wa/8tWKVwPS7DLnOKfq46pVst3cUuaUpRb2QrwGSeQHefsFUHZDKX1jFVCLBAtLXg1uDnBsqS1hjTNA5C6sIHUUyKVPIO34nC0wqKLSVcYvDZrtwQw7xVDqWGjFtp4T45Dm7G7H5DAMgYBJGlreXLxWaiPGRmNe4y4exx0fUJfCjXom52uTv4cDyKlosVMecGeUTRnHijrERKYjQfUKs0ur49OYub7m7/OdJe7wuJmDqJIa0v+G/x3PB8RvgVidCVQvcfMJURbmIfiKWOpUp/2BfZ0inY4mBv+jtXwN2O5z0TNBEJupWO40a4iKXuBS~-1~-1~-1,36906,-1,-1,30261689,PiZtE,97001,114,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,168079848-1,2,-94,-118,202256-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,400581,6254611,1920,1050,1920,1080,1920,477,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.889233594444,814033127305,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,4,0,1628066254610,-999999,17416,0,0,2902,0,0,6,0,0,718644520A1C29BB97E1FD6114B881D9~-1~YAAQhl5swQCzjQx7AQAAII1PEAbqZlyq9JAD3lIQikmfkEPeGOgKhcJlrkvqeYUxx4uEmjgrNlGXT8cmrgFvKASKT1ToyhSyKvehNyAzkiEjROrHycUBrDWAKGY17XioSA/tbFZW6dfc1KEHjMLClJAac5aaqUqd9P8UkJ+mGo0Oz3LCdJaehC1EGpPMefN3ZbqbE7NDqiknsttuJZ7n5/IaTeqvj8DNclt9d4Ifx52uNvo1m/7fOlq6TqcrB8Q+ELHT57+ldsCMPAyGFlKEMKzOyfq9pwAH7ZFOgd3fiGSgAA9dZwIScBUn/5GlNF9wQMW24pktKwe4875qSUNroeUn4eIEy6siyX5qamNbVvL/bSojzPHDHcXKBVDY/A+oYNEUIsxvmMp8AFLp~-1~-1~-1,37432,-1,-1,30261689,PiZtE,77147,55,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,93819138-1,2,-94,-118,202732-1,2,-94,-129,-1,2,-94,-121,;6;-1;0",
            "7a74G7m23Vrp0o5c9274551.7-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.101 Safari/537.36,uaend,12147,20030107,en-US,Gecko,2,0,0,0,400581,6282776,1920,1050,1920,1080,1920,477,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7606,0.674918859337,814033141386.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1628066282773,-999999,17416,0,0,2902,0,0,8,0,0,28C54716FB08DA539E245016B8D6E708~-1~YAAQhl5swc+0jQx7AQAA5ftPEAbRffPlkjn9ZLqij0xF14KceR40LaHbPAVom0Tk6wq5dFUZbWf/wHqSqSqglnJzio+GIIsimWED2bmeVlmD8NDp0V2oKuAmyhP5cRfDUOFrVsbHtrWfdp1Yze62GXLEi/6EcI/HrKjCsGLKQoliguCFzB07E+RXysFQ+3kdOK0bTA7RSJchdEefJB9awH4eJ6nR3JwqBzw1uC44fwBg0u3yyJGN7E6tDtlPOYGqg8u5XQvEY8Qz8UjHZHS+lt4fJ+8FF8/Ohsw3Zx8DISf/prmQaetERq1Xb8/FDuj8ok1ZuqOGJqmJge4ZZxt7uOHesC2wh4+oNc2rIszrKAtbE/q8MB1xXo0sgsccEQB/Bn7kbNgPKwk13qEM~-1~-1~-1,36920,-1,-1,30261689,PiZtE,84438,41,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,169634250-1,2,-94,-118,202340-1,2,-94,-129,-1,2,-94,-121,;9;-1;0",
        ];

        $secondSensorData = [
        ];

//        if (count($sensorData) != count($secondSensorData)) {
//            $this->logger->error("wrong sensor data values");
//
//            return null;
//        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        //$sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensorData[$this->key];
    }

    private function getSensorData($secondSensor = false)
    {
        $sensorData = [
            // 0
            null,
            // 1
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383297,1082855,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.925319219462,778910541427.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,2519,482,385;1,1,2522,498,379;2,1,2528,530,366;3,1,2542,563,357;4,1,2544,594,348;5,1,2554,623,342;6,1,2559,634,340;7,1,2591,700,335;8,1,2593,715,335;9,1,2600,730,335;10,1,2608,742,335;11,1,2616,751,337;12,1,2624,755,339;13,1,2631,762,342;14,1,2639,773,348;15,1,2990,772,348;16,1,2996,770,348;17,1,3003,767,348;18,1,3011,765,348;19,1,3020,764,348;20,1,3029,762,348;21,1,3037,761,348;22,1,3046,760,348;23,1,3051,759,348;24,1,3060,758,347;25,1,3068,757,345;26,1,3076,756,343;27,1,3086,755,339;28,1,3092,755,336;29,1,3100,755,332;30,1,3108,755,331;31,1,3116,755,327;32,1,3125,755,325;33,1,3131,755,322;34,1,3140,757,320;35,1,3148,759,319;36,1,3158,761,318;37,1,3164,763,317;38,1,3172,765,316;39,1,3181,767,316;40,1,3190,770,315;41,1,3197,772,315;42,1,3204,775,314;43,1,3213,776,314;44,1,3221,778,314;45,1,3229,780,313;46,1,3236,780,313;47,1,3245,781,313;48,1,3252,782,313;49,1,3261,782,313;50,1,3269,782,313;51,1,3413,782,312;52,1,3420,782,311;53,1,3429,783,309;54,1,3437,784,306;55,1,3445,785,303;56,1,3454,787,297;57,1,3460,788,293;58,1,3469,790,289;59,1,3478,792,284;60,1,3484,794,279;61,1,3492,797,274;62,1,3501,799,271;63,1,3509,800,269;64,1,3517,803,267;65,1,3524,805,265;66,1,3531,806,264;67,1,3540,807,264;68,1,3549,808,263;69,1,3556,809,263;70,1,3564,809,262;71,1,3574,809,262;72,1,3581,810,262;73,1,3589,810,262;74,1,3917,810,262;75,1,3932,810,262;76,1,3981,810,263;77,1,4023,811,263;78,1,4076,811,263;79,1,4475,810,263;80,1,4476,809,263;81,1,4489,807,264;82,1,4492,803,264;83,1,4501,797,265;84,1,4514,789,265;85,1,4516,782,266;86,1,4528,771,266;87,1,4539,760,267;88,1,4540,749,268;89,1,4552,739,268;90,1,4567,717,271;91,1,4576,712,272;92,1,4582,705,272;93,1,4590,697,274;94,1,4596,691,274;95,1,4604,685,275;96,1,4612,683,275;97,1,4620,679,276;98,1,4629,675,276;99,1,4637,672,276;128,3,5859,641,270,941;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,5694;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,465130,0,0,0,0,465129,5859,0,1557821082855,11,16665,0,129,2777,1,0,5861,353234,0,06D322CFF2F99985C31091D46B7993F5419EB4BD323E00009776DA5CFB6DB100~-1~wg2PQRrrcy9E3t5OdbcDGuuAeW2Hbo1G59jkGPkjxfM=~-1~-1,8262,183,1433234140,30261693-1,2,-94,-106,1,1-1,2,-94,-119,38,42,38,38,58,50,13,7,7,6,6,484,201,83,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,2130238721;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4989-1,2,-94,-116,87712200-1,2,-94,-118,224351-1,2,-94,-121,;3;8;0",
            // 2
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383298,1196798,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.248813961124,778910598399,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,5,0,1557821196798,-999999,16665,0,0,2777,0,0,7,0,0,D9E8C6F9B7D1E98733D68DB11E1993A2419EB4BD323E00000A77DA5C990FF060~-1~1cttaDdgHwhkYMuG85Sih6OFqLGwHYAZCqIbswOEfDw=~-1~-1,8375,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,10771187-1,2,-94,-118,192049-1,2,-94,-121,;5;-1;0",
            // 3
            "7a74G7m23Vrp0o5c9114551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395267,6802697,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.16261561381,803233401348,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1606466802696,-999999,17185,0,0,2864,0,0,8,0,0,E21F24AE94756F0A9E627345DB029EF1~-1~YAAQfgrGF7daSP91AQAApFxkBAQe9/7x61PG7GWwk3uMUPvIadGjey9YADH/ZjkPsrrkkJUQlunzHGitP2JqJzv3t53FI/NHBtXipP6LcseT+PFXriVEgD9sUaP66dE7+y2XtDwnq0pfrdl886xTzkEvxJLoYvJpMxbal51bMgP7PMUfa6P2nS3/W8BMLg15ah1OFzFhrmHv8R0Vp0ZDIUDGy8K5sWH/7G+CcMD+beHXwzdUtcHiRCxR84AG3EGnATPFfT4sHMgWpdubAeNZ8WQwpq0Uk60IEzxy1/iMQVTiV2vZDUjgX5ZAd0JXOa6W5AoLOpz3kyWpk4oJe17YIbYqRm/t6xn8C45YYk99v/5U2ynkvs0=~-1~-1~-1,34151,-1,-1,30261693,PiZtE,74029,128-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,20408067-1,2,-94,-118,217497-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 4
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383298,1488921,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.825458816412,778910744460,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html?ak_t=E96B73A7AE70B1CEF77F62184C4F9FCA419EB4BD323E00002D78DA5C63416F2B-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1557821488920,-999999,16665,0,0,2777,0,0,6,0,0,932A6E45EB3F54925293A80EB6EF962B419EB4BD323E00002D78DA5C45D80142~-1~GygkuoVSWzoGAhw/0o7syREXFeaZ5B8Lk36UvBdzk1Y=~-1~-1,8274,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,120603330-1,2,-94,-118,196191-1,2,-94,-121,;5;-1;0",
            // 5
            "7a74G7m23Vrp0o5c9079341.41-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383299,7805060,1920,1056,1920,1080,854,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.983062445491,778913902530,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1557827805060,-999999,16665,0,0,2777,0,0,7,0,0,0B7CBF846A2990B7687CF8CE9706CD240215F025E5500000DB90DA5CDCE2D065~-1~/h6pAU0A9/jiT9GdBo5IvqOi0lLzpRXC1q9Ojmx/J4M=~-1~-1,8040,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,7805059-1,2,-94,-118,190367-1,2,-94,-121,;5;-1;0",
            // 6
            "7a74G7m23Vrp0o5c9079451.41-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383299,8405274,1920,1056,1920,1080,854,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.08728449643,778914202637,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1557828405274,-999999,16665,0,0,2777,0,0,5,0,0,0C6B456D2E817E97EA7D5147F4A2A7BF0215F025E55000002C93DA5C65906256~-1~zvUJMoJQA4b57Q69NQMX96tB0XKfnN+eu/uohkQJ6L4=~-1~-1,7924,-1,-1,30261689-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,42026365-1,2,-94,-118,190221-1,2,-94,-121,;4;-1;0",
            // 7
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395178,6744860,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6007,0.04359992821,803053372429.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,8,0,1606106744859,-999999,17181,0,0,2863,0,0,11,0,0,158BE4911B73D83FFB9B209334DCC89B~-1~YAAQFloDFxV3NvJ1AQAAuANs8wQEU+KvZcG/4fHDDFD821i7kWmLLss8BRoYy68Icg91S9ZJQcZWRKoh19VQIbEJ3bx/kr21IHBK8rnJ4BiXxiSpHwVOEtuShunmAyp4BoIu072FGgt855K6sboSaMDl+mb3dhldN9ezsKHNl87ECCjf/L+FtKpK4Vw47gI6ayqlY45+eH2kG1GUW2PbT+lg0xUzv0/enD4RybfqS0+5DmFdrPW7OlmeX3wa7zWDXV4DScZD4StyfL/UVfGbWerDPAybqboi1nE3EynJigoinIsePO2a2SmkU0hDqELQSNXQ9WQFhLBU5cIipwKdgkp8afGV5vowYYJuCnbCOYsAWDvzEnY=~0~-1~-1,34167,-1,-1,26067385,PiZtE,94960,57-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,4552775775-1,2,-94,-118,214901-1,2,-94,-129,-1,2,-94,-121,;5;-1;0",
            // 8
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,383298,3071084,1440,829,1440,900,1440,417,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.959916312479,778911535542,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1557823071084,-999999,16665,0,0,2777,0,0,5,0,0,5CEAA880CBB3823464BB5DF958756AC9419EB4BD323E00005B7EDA5CF69DE305~-1~289aEhnLEwYJ94t/fwA/NWhYb8EyzG4zDr4UhkyoaU0=~-1~-1,8225,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,138198774-1,2,-94,-118,189097-1,2,-94,-121,;3;-1;0",
            // 9
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,383298,3267552,1440,829,1440,900,1440,417,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.552014943276,778911633775.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,5,0,1557823267551,-999999,16665,0,0,2777,0,0,6,0,0,DCD7FD3D0D2E3D39822A51683E67D033419EB4BD323E00000B7FDA5C6BCACC2E~-1~MwG3KzkIWcrl7eBq/ZVx+RYy25KDpTBEx5X4Zh6+cJA=~-1~-1,8128,-1,-1,26067385-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,3267546-1,2,-94,-118,189107-1,2,-94,-121,;2;-1;0",
            // 10
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395178,6997739,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6007,0.948428751474,803053498869.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,7,0,1606106997739,-999999,17181,0,0,2863,0,0,8,0,0,F23F5A6819A06D678571DFC78ED26781~-1~YAAQrh/JF6TP+t51AQAARd5v8wRS1PXAuFUTjhQOV88bU72eDCrkGMEQ9WpqzEYQe2EtIRB1d126KaR02Gdw/+M+Zs4IUwTmO8YlebsPX625UGhKUOhh3VdsFfbu+3qPExglc47vGv04WhfyiD5IKFW6uJqwkqdZboU3UgUX0fWDeUGVMMUXJjkYAB9jDe7dsGRVhF/YJDlBTbETX8U8mRoko+zh9nwzbvVE9ixCN7ljSJ1GpWvQAtKwm9Z0aRdpqHfkE4IMeFFY4ctQTnIf3GYV/2W6cVzEQd7bFbCeCm/+VkiXBZHA3GXZiXh7~-1~-1~-1,29152,-1,-1,26067385,PiZtE,19711,40-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,20993217-1,2,-94,-118,209890-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
            // 11
            "7a74G7m23Vrp0o5c9079341.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:59.0) Gecko/20100101 Firefox/59.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,383299,6838768,1280,777,1280,800,1280,637,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:137,vib:1,bat:0,x11:0,x12:1,6010,0.10335173151,778913419383,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html?ak_t=C634535D8B5617C90B3AF8A113D8C38D0215F025E5500000138DDA5C042AF456-1,2,-94,-115,1,1,0,0,0,0,0,6,0,1557826838766,-999999,16665,0,0,2777,0,0,10,0,0,DFD030DD8AC014BA888286250CDFE1CA0215F025E5500000138DDA5C880A8D1B~-1~Pi5nypqzEyUlmvgZIDX7dAhU9rkLLc8vWQ8HUnhERaU=~-1~-1,8403,-1,-1,26067384-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,553940069-1,2,-94,-118,193434-1,2,-94,-121,;6;-1;0",
            // 12
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395178,7079517,1536,872,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.602416423301,803053539758,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,6,0,1606107079516,-999999,17181,0,0,2863,0,0,8,0,0,87F81014D068487B4BDC43564A36F55C~-1~YAAQHiQcuD4MePF1AQAArBVx8wTO+uviFjGV2U6qjfBxBtbE2D2h0E84X9mf5TnUcDJ4rcZavk4b0VWLasG075jy8/DO3Prb1hBBKnDnAQY2P8e1lPQKsR+dNN3Fh7fWwl+lugrgVX9eIJIXwoPTYsHZYC0Y0d20CmThSpDb3yD8IlWTfEctjRIyU+5IxJkuc04/gvjWpxD1c1phn2W9eSnjrE2sWkADYEBvwpBh9WXskArhg83ErUYgq6PUblmsRdHu324WeEg74ADF34bplRaV8PP3LvTNq4DvXoKbMyoqbTszAor2juM5kHYf~-1~-1~-1,29554,-1,-1,30261693,PiZtE,57983,120-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,21238557-1,2,-94,-118,212903-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
            // 13
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395178,7114359,1536,872,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.387650027193,803053557179,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,7,0,1606107114358,-999999,17181,0,0,2863,0,0,9,0,0,4C0C6AC554A7A70427A3AE415B52FEF3~-1~YAAQHiQcuHINePF1AQAATqdx8wQB/c1PrwIs1ZYW1jDuNOpzSc/iwho1F8uWwPJp9Jq8uW60p9yQEyVaxwcAnUR7nLtNAfPzLw4qacWWu18S28jORj5GKOB/TQaaBcQ5PgjTTUss28tUG5MfjenatsC1yBu2f+m9rwMvqR1/dPbF/qXO6DdgJW2vQ5wd7jaO8kG/T5IDpOYxnJrDU/TKyhq/V2c9gQbT0BbqTuWQbPSj9Ltm7GccfuyeCTW/wlRVyQ1K5J4cDeEkJI8fEROdsYRUVG6P2zmcJBQtpXIzZcbfMmRN7nLCkZ8FLpCJ~-1~-1~-1,29867,-1,-1,30261693,PiZtE,41931,43-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,106715571-1,2,-94,-118,213203-1,2,-94,-129,-1,2,-94,-121,;7;-1;0",
        ];

        $secondSensorData = [
            // 0
            null,
            // 1
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383297,1082855,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.349411613174,778910541427.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1,0,0,0,0,0,3,0,1557821082855,-999999,16665,0,0,2777,0,0,5,0,0,06D322CFF2F99985C31091D46B7993F5419EB4BD323E00009776DA5CFB6DB100~-1~wg2PQRrrcy9E3t5OdbcDGuuAeW2Hbo1G59jkGPkjxfM=~-1~-1,8262,-1,-1,30261693-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,87712200-1,2,-94,-118,191976-1,2,-94,-121,;5;-1;0",
            // 2
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383298,1196798,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.744095345372,778910598399,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,12108,1175,398;1,1,12110,1175,398;2,1,12144,1250,292;3,1,12325,1420,144;4,1,12545,1419,144;5,1,12553,1407,144;6,1,12561,1392,148;7,1,12568,1375,153;8,1,12576,1357,158;9,1,12584,1336,165;10,1,12592,1292,176;11,1,12600,1244,190;12,1,12609,1194,203;13,1,12616,1141,217;14,1,12624,1086,229;15,1,12633,1037,239;16,1,12640,1015,243;17,1,12648,970,249;18,1,12656,930,252;19,1,12664,915,253;20,1,12672,889,254;21,1,12682,880,254;22,1,12689,864,254;23,1,12696,860,254;24,1,12705,852,254;25,1,12713,847,254;26,1,12721,844,254;27,1,13299,821,253;28,1,13307,813,253;29,1,13315,806,252;30,1,13323,801,252;31,1,13333,795,252;32,1,13339,789,252;33,1,13347,784,252;34,1,13355,779,252;35,1,13363,774,253;36,1,13373,770,253;37,1,13380,763,254;38,1,13387,756,256;39,1,13395,750,256;40,1,13403,744,257;41,1,13413,742,258;42,1,13419,738,258;43,1,13427,735,259;44,1,13435,732,259;45,1,13443,730,259;46,1,13450,728,259;47,1,13459,726,260;48,1,13467,726,260;49,1,13475,725,260;50,1,13483,724,260;51,1,13491,724,261;52,1,13499,723,261;53,1,13507,723,261;54,1,13514,722,262;55,1,13523,721,262;56,1,13531,720,262;57,1,13539,719,263;58,1,13547,718,263;59,1,13555,717,263;60,1,13563,715,265;61,1,13571,715,265;62,1,13579,713,266;63,1,13587,713,267;64,1,13595,711,268;65,1,13605,710,269;66,1,13610,709,269;67,1,13619,708,270;68,1,13627,708,270;69,1,13635,707,270;70,1,13643,706,271;71,1,13651,706,271;72,1,13659,705,271;73,1,13666,705,271;74,1,13674,705,271;75,1,13682,704,271;76,1,13690,704,271;77,1,13698,703,271;78,1,13707,703,271;79,1,13715,703,271;80,1,13723,702,271;81,1,13730,702,270;82,1,13738,701,269;83,1,13747,700,268;84,1,13755,699,267;85,1,13763,698,264;86,1,13770,698,262;87,1,13780,697,257;88,1,13786,696,252;89,1,13796,695,248;90,1,13803,695,243;91,1,13813,695,240;92,1,13819,694,236;93,1,13826,694,233;94,1,13834,693,231;95,1,13843,693,229;96,1,13851,692,228;97,1,13859,692,227;98,1,13866,692,227;99,1,13874,692,227;110,3,14237,691,228,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,2113;0,5598;1,11796;3,14228;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1459387,0,0,0,0,1459386,14237,0,1557821196798,8,16665,0,111,2777,1,0,14239,1345319,0,D9E8C6F9B7D1E98733D68DB11E1993A2419EB4BD323E00000A77DA5C990FF060~-1~1cttaDdgHwhkYMuG85Sih6OFqLGwHYAZCqIbswOEfDw=~-1~-1,8375,32,77902505,30261693-1,2,-94,-106,1,1-1,2,-94,-119,7,9,8,10,19,21,14,9,13,7,6,258,198,86,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,2130238721;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4989-1,2,-94,-116,10771187-1,2,-94,-118,231221-1,2,-94,-121,;4;11;0",
            // 3
            "7a74G7m23Vrp0o5c9114551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395267,6802697,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.11789824158,803233401348,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,1380,0,1606466802696,59,17185,0,0,2864,0,0,1382,0,0,E21F24AE94756F0A9E627345DB029EF1~-1~YAAQVGAZuBTNkQZ2AQAA0xTiCAS6zDdHVbHM1L/nK0kapjSyIUWZ9Ip635qKm4OMHgpQBSKSvco+qFHFiU7syffe4faAsV4Du3jpf/ebL6/9vUqlmGmwK1qHf8D0qrw+Nwnpwqjn72qytteEuH6O9BHTSruuhO+Waqcdnj6VEw3LNpveFC+g+T6Eva9dEwKG0xraFVe5pN6ajgH3Wq5YkzIyNTzZtM9K65dIj9eap6SN8tk6Im4uTz9RauDOBwD9rZpmjsqcOVXS0fjkIDP/E3wDfugWwpxVSnQht9cSR9aG2mlKimBc6oAaS0FNuY2gXmo2L8EMnA3TYi+BltSqa+hmrzHL1atstV+4SmOnfg9xdSUMyck=~-1~-1~-1,35026,480,1963656969,30261693,PiZtE,50553,71-1,2,-94,-106,9,1-1,2,-94,-119,27,31,30,31,49,51,33,9,7,6,6,372,275,489,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,20408067-1,2,-94,-118,221745-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,,,,0-1,2,-94,-121,;25;19;0",
            // 4
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383298,1488921,1440,829,1440,900,1440,402,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8970,0.359893749179,778910744460,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,11181,588,396;1,1,11182,613,382;2,1,11188,624,379;3,1,11197,644,370;4,1,11204,652,368;5,1,11213,668,363;6,1,11222,680,360;7,1,11229,688,359;8,1,11236,695,359;9,1,11445,694,359;10,1,11453,692,360;11,1,11462,691,361;12,1,11469,690,363;13,1,11477,690,363;14,1,11484,689,364;15,1,11493,689,364;16,1,11501,688,365;17,1,11508,688,365;18,1,11516,687,365;19,1,11525,686,365;20,1,11533,685,364;21,1,11542,685,360;22,1,11549,684,356;23,1,11557,683,351;24,1,11565,682,345;25,1,11574,682,338;26,1,11581,682,332;27,1,11589,682,325;28,1,11597,682,322;29,1,11605,682,315;30,1,11613,682,314;31,1,11625,682,311;32,1,11630,683,308;33,1,11645,685,305;34,1,11653,686,304;35,1,11661,687,303;36,1,11668,689,301;37,1,11677,689,301;38,1,11684,691,299;39,1,12245,690,299;40,1,12253,689,299;41,1,12261,688,299;42,1,12268,686,299;43,1,12276,686,299;44,1,12285,685,299;45,1,12293,683,299;46,1,12300,681,299;47,1,12308,680,299;48,1,12316,679,300;49,1,12326,677,300;50,1,12333,677,300;51,1,12343,676,301;52,1,12348,675,301;53,1,12356,674,302;54,1,12365,674,302;55,1,12373,674,303;56,1,12380,673,303;57,1,12388,673,303;58,1,12405,673,303;59,1,12533,673,303;60,1,12542,673,302;61,1,12549,673,299;62,1,12556,672,297;63,1,12565,672,294;64,1,12572,671,290;65,1,12582,671,288;66,1,12589,669,283;67,1,12596,669,282;68,1,12604,668,279;69,1,12612,667,277;70,1,12620,667,275;71,1,12629,666,274;72,1,12636,665,273;73,1,12646,664,272;74,1,12653,664,272;75,1,12661,663,271;76,1,12668,663,271;77,1,12677,662,271;78,1,12684,662,271;79,1,12693,661,270;80,1,12700,661,270;81,1,12708,661,270;82,1,12716,660,270;83,1,12726,660,270;84,1,12742,660,270;85,1,12748,660,270;86,1,12765,659,269;87,1,12772,659,269;88,1,12788,659,269;89,1,12806,659,268;90,3,12830,659,268,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,1569;0,4595;1,9958;3,12822;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html?ak_t=E96B73A7AE70B1CEF77F62184C4F9FCA419EB4BD323E00002D78DA5C63416F2B-1,2,-94,-115,1,1193186,0,0,0,0,1193185,12830,0,1557821488920,4,16665,0,91,2777,1,0,12832,1099323,0,932A6E45EB3F54925293A80EB6EF962B419EB4BD323E00002D78DA5C45D80142~-1~GygkuoVSWzoGAhw/0o7syREXFeaZ5B8Lk36UvBdzk1Y=~-1~-1,8274,520,842180157,30261693-1,2,-94,-106,1,1-1,2,-94,-119,8,8,8,12,21,22,13,8,8,6,6,161,186,85,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,2130238721;dis;,7,8;true;true;true;-300;true;24;24;true;false;-1-1,2,-94,-80,4989-1,2,-94,-116,120603330-1,2,-94,-118,224973-1,2,-94,-121,;6;10;0",
            // 5
            "7a74G7m23Vrp0o5c9079341.41-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383299,7805060,1920,1056,1920,1080,854,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.545553025272,778913902530,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,55292,831,55;1,1,55293,831,55;2,1,55363,831,55;3,1,55370,831,56;4,1,55377,831,56;5,1,55384,833,57;6,1,55394,840,62;7,1,134288,846,158;8,1,134298,820,182;9,1,134300,799,204;10,1,134307,778,224;11,1,134312,763,240;12,1,134318,751,251;13,1,134327,737,267;14,1,134334,725,283;15,1,134339,716,296;16,1,134347,712,302;17,1,134353,710,309;18,1,134361,708,310;19,1,134739,704,310;20,1,134746,695,301;21,1,134753,676,284;22,1,134764,660,268;23,1,134771,647,255;24,1,134775,632,237;25,1,134782,620,223;26,1,134794,606,206;27,1,134801,596,194;28,1,134804,581,180;29,1,134810,568,170;30,1,134820,557,163;31,1,134825,547,156;32,1,134834,538,150;33,1,134838,528,144;34,1,134846,518,140;35,1,134852,508,139;36,1,134860,503,136;37,1,134866,498,135;38,1,134873,494,134;39,1,134880,488,133;40,1,134889,482,132;41,1,134895,480,132;42,1,134902,476,131;43,1,134909,473,131;44,1,134916,471,131;45,1,134923,469,131;46,1,134931,468,131;47,1,134937,467,131;48,1,134944,466,131;49,1,134951,464,130;50,1,134959,464,130;51,1,134966,463,130;52,1,134973,461,129;53,1,134980,459,129;54,1,134987,456,128;55,1,134995,455,128;56,1,135001,452,128;57,1,135008,450,128;58,1,135015,448,127;59,1,135023,446,127;60,1,135031,444,127;61,1,135037,443,127;62,1,135044,441,126;63,1,135051,440,126;64,1,135059,439,126;65,1,135065,438,126;66,1,135072,436,126;67,1,135081,435,126;68,1,135086,433,126;69,1,135094,432,126;70,1,135100,431,126;71,1,135109,429,126;72,1,135115,428,126;73,1,135122,426,126;74,1,135131,424,126;75,1,135136,423,126;76,1,135145,421,126;77,1,135150,420,126;78,1,135158,418,126;79,1,135165,416,126;80,1,135172,415,126;81,1,135178,413,126;82,1,135186,412,126;83,1,135192,410,126;84,1,135199,408,126;85,1,135206,407,127;86,1,135213,404,128;87,1,135221,404,129;88,1,135228,402,130;89,1,135235,401,130;90,1,135242,400,131;91,1,135249,400,132;92,1,135257,399,132;93,1,135263,398,133;94,1,135270,397,134;95,1,135277,396,135;96,1,135285,395,135;97,1,135292,394,136;98,1,135299,393,137;99,1,135306,392,138;211,3,139787,369,199,941;-1,2,-94,-117,-1,2,-94,-111,0,412,-1,-1,-1;-1,2,-94,-109,0,411,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,3,139778;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,13150033,0,412,411,0,13150855,139787,0,1557827805060,11,16665,0,212,2777,1,0,139790,13076825,0,0B7CBF846A2990B7687CF8CE9706CD240215F025E5500000DB90DA5CDCE2D065~-1~/h6pAU0A9/jiT9GdBo5IvqOi0lLzpRXC1q9Ojmx/J4M=~-1~-1,8040,359,-897317409,30261689-1,2,-94,-106,1,1-1,2,-94,-119,28,36,32,32,56,67,65,23,25,18,21,491,506,231,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1127778619;dis;,7,8;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5009-1,2,-94,-116,7805059-1,2,-94,-118,235152-1,2,-94,-121,;5;10;0",
            // 6
            "7a74G7m23Vrp0o5c9079451.41-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.131 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,383299,8405274,1920,1056,1920,1080,854,762,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7614,0.09656209048,778914202637,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,2424,751,6;1,1,2425,732,15;2,1,2432,710,21;3,1,2439,688,30;4,1,2446,664,40;5,1,2454,644,52;6,1,2462,630,61;7,1,2468,611,72;8,1,2476,595,81;9,1,2482,583,88;10,1,2489,574,94;11,1,2496,567,98;12,1,2503,559,103;13,1,2511,552,108;14,1,2517,546,111;15,1,2525,545,113;16,1,2532,544,113;17,1,2538,544,114;18,1,2545,544,115;19,1,2553,544,115;20,1,2815,542,115;21,1,2822,531,115;22,1,2830,498,115;23,1,2837,472,115;24,1,2843,452,115;25,1,2850,438,115;26,1,2858,428,115;27,1,2866,419,116;28,1,2874,409,117;29,1,2879,397,122;30,1,2886,381,125;31,1,2893,371,127;32,1,2901,362,130;33,1,2908,355,132;34,1,2914,347,133;35,1,2922,337,136;36,1,2928,328,139;37,1,2936,323,140;38,1,2943,318,142;39,1,2950,315,143;40,1,2957,312,144;41,1,2965,309,145;42,1,2972,305,147;43,1,2979,303,148;44,1,2986,301,148;45,1,2993,300,148;46,1,3002,299,149;47,1,3008,297,149;48,1,3014,296,150;49,1,3021,296,150;50,1,3028,296,150;51,1,3036,295,150;52,1,3043,294,150;53,1,3049,294,150;54,1,3056,293,151;55,1,3063,292,151;56,1,3072,292,151;57,1,3078,292,152;58,1,3085,292,152;59,1,3092,292,152;60,1,3099,291,152;61,1,3108,290,153;62,1,3114,289,153;63,1,3121,288,155;64,1,3127,288,155;65,1,3137,288,156;66,1,3142,287,157;67,1,3150,286,159;68,1,3157,285,160;69,1,3163,284,160;70,1,3171,284,161;71,1,3177,284,162;72,1,3184,283,163;73,1,3192,282,164;74,1,3202,282,165;75,1,3206,281,166;76,1,3213,280,167;77,1,3219,280,168;78,1,3226,279,169;79,1,3235,279,170;80,1,3241,278,171;81,1,3248,277,172;82,1,3255,277,172;83,1,3262,277,173;84,1,3270,276,174;85,1,3277,276,175;86,1,3284,276,176;87,1,3291,276,177;88,1,3297,276,177;89,1,3305,276,178;90,1,3312,276,179;91,1,3319,276,179;92,3,3845,276,179,941;-1,2,-94,-117,-1,2,-94,-111,0,176,-1,-1,-1;-1,2,-94,-109,0,175,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,326718,0,176,175,0,327068,3845,0,1557828405274,3,16665,0,93,2777,1,0,3846,274771,0,0C6B456D2E817E97EA7D5147F4A2A7BF0215F025E55000002C93DA5C65906256~-1~zvUJMoJQA4b57Q69NQMX96tB0XKfnN+eu/uohkQJ6L4=~-1~-1,7924,202,357081214,30261689-1,2,-94,-106,1,1-1,2,-94,-119,38,38,38,35,58,78,55,49,45,32,33,487,581,110,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,-1127778619;dis;,7,8;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5009-1,2,-94,-116,42026365-1,2,-94,-118,216383-1,2,-94,-121,;4;8;0",
            // 7
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395178,6744860,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6007,0.272317603136,803053372436,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,765,0,1606106744872,9,17181,0,0,2863,0,0,768,0,0,158BE4911B73D83FFB9B209334DCC89B~-1~YAAQFloDFxV3NvJ1AQAAuANs8wQEU+KvZcG/4fHDDFD821i7kWmLLss8BRoYy68Icg91S9ZJQcZWRKoh19VQIbEJ3bx/kr21IHBK8rnJ4BiXxiSpHwVOEtuShunmAyp4BoIu072FGgt855K6sboSaMDl+mb3dhldN9ezsKHNl87ECCjf/L+FtKpK4Vw47gI6ayqlY45+eH2kG1GUW2PbT+lg0xUzv0/enD4RybfqS0+5DmFdrPW7OlmeX3wa7zWDXV4DScZD4StyfL/UVfGbWerDPAybqboi1nE3EynJigoinIsePO2a2SmkU0hDqELQSNXQ9WQFhLBU5cIipwKdgkp8afGV5vowYYJuCnbCOYsAWDvzEnY=~0~-1~-1,34167,49,1908716135,26067385,PiZtE,34519,88-1,2,-94,-106,9,1-1,2,-94,-119,0,0,0,0,0,0,0,200,0,0,0,200,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,4552775775-1,2,-94,-118,217635-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,0,,,,0-1,2,-94,-121,;18;18;0",
            // 8
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,383298,3071084,1440,829,1440,900,1440,417,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.612101313306,778911535542,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,32317,876,279;1,1,32333,881,279;2,1,32350,888,280;3,1,32366,896,280;4,1,32383,911,281;5,1,32399,928,281;6,1,32415,945,281;7,1,32432,974,275;8,1,32449,989,273;9,1,32466,1008,269;10,1,32483,1029,268;11,1,32499,1040,268;12,1,32517,1051,269;13,1,32533,1057,270;14,1,32550,1062,272;15,1,32566,1065,273;16,1,32582,1066,274;17,1,32599,1066,274;18,1,32615,1064,274;19,1,32632,1043,272;20,1,32649,1012,267;21,1,32666,985,263;22,1,32683,947,261;23,1,32698,908,260;24,1,32715,886,259;25,1,32735,863,259;26,1,32748,847,259;27,1,32766,837,260;28,1,32783,827,267;29,1,33566,827,267;30,1,33582,818,275;31,1,33599,805,289;32,1,33618,791,305;33,1,33636,735,358;34,1,33653,700,391;35,1,33667,677,412;36,1,35220,646,412;37,1,35234,644,411;38,1,35249,642,410;39,1,35266,641,409;40,1,35283,641,409;41,1,35300,640,409;42,1,35449,640,409;43,1,35482,640,408;44,1,35517,640,408;45,1,35534,639,408;46,1,35551,639,408;47,3,35563,639,408,-1;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,2,3514;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,1661125,0,0,0,0,1661124,35563,0,1557823071084,3,16665,0,48,2777,1,0,35565,1603898,0,5CEAA880CBB3823464BB5DF958756AC9419EB4BD323E00005B7EDA5CF69DE305~-1~289aEhnLEwYJ94t/fwA/NWhYb8EyzG4zDr4UhkyoaU0=~-1~-1,8225,735,-2064762795,26067385-1,2,-94,-106,1,1-1,2,-94,-119,400,0,200,0,200,200,200,200,200,200,200,800,400,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,1241107008;dis;,3;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4835-1,2,-94,-116,138198774-1,2,-94,-118,176632-1,2,-94,-121,;3;7;0",
            // 9
            "7a74G7m23Vrp0o5c9079331.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:66.0) Gecko/20100101 Firefox/66.0,uaend,11059,20100101,en-US,Gecko,1,0,0,0,383298,3267552,1440,829,1440,900,1440,417,1440,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:119,vib:1,bat:0,x11:0,x12:1,6007,0.06449737732,778911633775.5,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,3714,658,412;1,1,3720,669,389;2,1,3737,679,368;3,1,3756,688,349;4,1,3772,696,331;5,1,3787,702,320;6,1,3802,708,308;7,1,3819,711,302;8,1,3835,714,297;9,1,3853,715,295;10,1,4720,712,300;11,1,4739,708,306;12,1,4753,706,313;13,1,4769,704,319;14,1,4791,702,328;15,1,4807,700,335;16,1,4821,698,341;17,1,4837,697,346;18,1,4856,696,350;19,1,4869,694,353;20,1,4890,692,357;21,1,4904,690,360;22,1,4920,689,362;23,1,4936,687,363;24,1,4953,687,363;25,1,4969,686,364;26,1,4987,686,364;27,1,5070,686,364;28,1,5087,686,363;29,1,5109,685,360;30,1,5119,683,357;31,1,5138,682,353;32,1,5156,680,349;33,1,5170,680,346;34,1,5187,678,342;35,1,5204,676,337;36,1,5220,675,335;37,1,5235,674,333;38,1,5258,673,332;39,1,5269,673,332;40,1,5286,672,332;41,1,5302,671,331;42,1,5319,671,331;43,1,5336,671,331;44,1,5354,670,331;45,1,5369,670,331;46,1,5403,670,330;47,1,5437,670,330;48,1,5453,669,330;49,1,5469,669,330;50,1,5487,669,329;51,1,5519,669,329;52,1,5535,669,329;53,3,5618,669,329,941;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html-1,2,-94,-115,1,320218,0,0,0,0,320217,5618,0,1557823267551,3,16665,0,54,2777,1,0,5619,263425,0,DCD7FD3D0D2E3D39822A51683E67D033419EB4BD323E00000B7FDA5C6BCACC2E~-1~MwG3KzkIWcrl7eBq/ZVx+RYy25KDpTBEx5X4Zh6+cJA=~-1~-1,8128,607,331925665,26067385-1,2,-94,-106,1,1-1,2,-94,-119,200,200,0,0,200,200,200,200,200,200,400,1000,200,200,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,1241107008;dis;,3;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,4835-1,2,-94,-116,3267546-1,2,-94,-118,178540-1,2,-94,-121,;2;9;0",
            // 10
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.16; rv:83.0) Gecko/20100101 Firefox/83.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,395178,6997739,1536,872,1536,960,1536,417,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:127,vib:1,bat:0,x11:0,x12:1,6007,0.236478708118,803053498869.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,791,0,1606106997739,8,17181,0,0,2863,0,0,792,0,0,F23F5A6819A06D678571DFC78ED26781~-1~YAAQrh/JF6TP+t51AQAARd5v8wRS1PXAuFUTjhQOV88bU72eDCrkGMEQ9WpqzEYQe2EtIRB1d126KaR02Gdw/+M+Zs4IUwTmO8YlebsPX625UGhKUOhh3VdsFfbu+3qPExglc47vGv04WhfyiD5IKFW6uJqwkqdZboU3UgUX0fWDeUGVMMUXJjkYAB9jDe7dsGRVhF/YJDlBTbETX8U8mRoko+zh9nwzbvVE9ixCN7ljSJ1GpWvQAtKwm9Z0aRdpqHfkE4IMeFFY4ctQTnIf3GYV/2W6cVzEQd7bFbCeCm/+VkiXBZHA3GXZiXh7~-1~-1~-1,29152,877,494947617,26067385,PiZtE,98690,36-1,2,-94,-106,9,1-1,2,-94,-119,200,0,200,0,0,0,0,0,0,0,0,200,400,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,20993217-1,2,-94,-118,212812-1,2,-94,-129,d6350cc6832ca216bbeb88243f8742dbb972e8f7f09960559265cf71b2842f75,2,cd15889e4b58585ec9f3c796725c752f6dd7926965daec54124da062a5aaf8e1,,,,0-1,2,-94,-121,;9;14;0",
            // 11
            "7a74G7m23Vrp0o5c9079341.41-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.13; rv:59.0) Gecko/20100101 Firefox/59.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,383299,6838768,1280,777,1280,800,1280,637,1280,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:137,vib:1,bat:0,x11:0,x12:1,6010,0.274209927137,778913419383,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,1811,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,1884,-1,0;0,-1,0,0,1768,-1,0;0,0,0,0,1677,-1,0;0,-1,0,0,2421,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,0,0,0,968,-1,0;0,0,0,0,1506,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,1611,-1,0;0,-1,0,0,936,936,0;-1,2,-94,-108,-1,2,-94,-110,0,1,28172,858,157;1,1,28188,849,157;2,1,28206,837,157;3,1,28222,824,159;4,1,28252,809,164;5,1,28256,799,171;6,1,28272,791,177;7,1,28292,776,189;8,1,28306,762,198;9,1,28322,751,202;10,1,28340,728,210;11,1,28358,715,215;12,1,28372,699,220;13,1,28388,683,226;14,1,28404,665,232;15,1,28420,654,235;16,1,28442,639,240;17,1,28460,624,244;18,1,28474,613,247;19,1,28488,608,248;20,1,28504,601,250;21,1,28520,591,252;22,1,28538,584,254;23,1,28554,575,258;24,1,28570,570,260;25,1,28588,566,261;26,1,28604,560,263;27,1,28620,553,265;28,1,28636,548,267;29,1,28654,545,268;30,1,28672,542,269;31,1,28688,541,271;32,3,28842,541,271,941;-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,3,27998;-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html?ak_t=C634535D8B5617C90B3AF8A113D8C38D0215F025E5500000138DDA5C042AF456-1,2,-94,-115,1,968646,0,0,0,0,968645,28842,0,1557826838766,8,16665,0,33,2777,1,0,28844,938624,0,DFD030DD8AC014BA888286250CDFE1CA0215F025E5500000138DDA5C880A8D1B~-1~Pi5nypqzEyUlmvgZIDX7dAhU9rkLLc8vWQ8HUnhERaU=~-1~-1,8403,511,-170637264,26067384-1,2,-94,-106,1,1-1,2,-94,-119,400,400,0,0,400,400,0,0,0,0,0,0,800,400,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-70,1787821324;dis;;true;true;true;-300;true;24;24;true;false;unspecified-1,2,-94,-80,5877-1,2,-94,-116,553940069-1,2,-94,-118,165405-1,2,-94,-121,;2;14;0",
            // 12
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395178,7079517,1536,872,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.300411505150,803053539758,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,753,0,1606107079516,10,17181,0,0,2863,0,0,756,0,0,87F81014D068487B4BDC43564A36F55C~-1~YAAQHiQcuFgMePF1AQAAjCRx8wSZVUOfv0lZChhNmWFAQGCNQJ+zeWI+Iz9uJDxQsgU0RkyxbNlKUIc2pOBhsPjZH1rAS0vPKzTcBr0uFaNlIOOQEFFF6zmi5qQa6wsD+ZKkkrJ5rrbuvk6l2qxK/7EPPJFEJdObKkz2bHJ5VAsGM4QSiuc2wA7a2Yua+itEcR4mZrQ2tSGv+W2xYqxCC8yq/gMHFoDXdr52IAn1PyMV//871Pyy5gjf7pXdCER3AYRft5mPP6LM2OPOE0OUROr2v4Ja/7e4NGOx6YXHDN0KXLShuLgOcYCrWt1c1iya1zz8YGZzm1UhLT4xLC6mBK9eF9+gHl7z~-1~-1~-1,32322,425,2106099859,30261693,PiZtE,62398,43-1,2,-94,-106,9,1-1,2,-94,-119,28,30,30,31,47,51,11,8,7,6,6,360,277,354,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,21238557-1,2,-94,-118,218932-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,,,,0-1,2,-94,-121,;10;18;0",
            // 13
            "7a74G7m23Vrp0o5c9113551.66-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 11_0_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/86.0.4240.198 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,395178,7114359,1536,872,1536,960,1536,451,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8919,0.799070799399,803053557179,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-102,0,-1,0,0,1151,113,0;0,-1,0,0,1151,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1200,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1249,113,0;0,-1,0,0,1349,-1,0;0,-1,0,0,936,936,0;0,0,0,0,5546,-1,0;0,0,0,0,6084,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1960,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,0,0,0,1660,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2135,-1,0;0,0,0,0,2135,-1,0;0,-1,0,0,888,-1,0;0,-1,0,0,936,936,0;0,0,0,0,2001,1027,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,1565,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,1335,-1,0;0,-1,0,0,1829,-1,0;0,0,0,0,2001,-1,0;0,0,0,0,2010,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1558,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1590,-1,0;0,0,0,0,1607,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1869,-1,0;0,0,0,0,1816,-1,0;0,0,0,0,1816,-1,0;0,-1,0,0,1417,-1,0;0,-1,0,0,2424,-1,0;0,0,0,0,2124,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1681,-1,0;0,0,0,0,1698,-1,0;0,0,0,0,2144,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1701,-1,0;0,0,0,0,1718,-1,0;0,0,0,0,2657,-1,0;0,0,0,0,2657,-1,0;0,-1,0,0,2243,-1,0;0,-1,0,0,2584,-1,0;0,-1,0,0,2587,-1,0;0,-1,0,0,2424,-1,0;0,-1,0,0,2355,-1,0;0,-1,0,0,2787,-1,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2611,1783,0;0,-1,0,0,2871,1884,0;0,-1,0,0,2421,-1,0;0,-1,0,0,1768,-1,0;0,-1,0,0,2467,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,2801,-1,0;0,0,0,0,3773,-1,0;0,0,0,0,2387,-1,0;0,0,0,0,2925,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,2711,-1,0;0,0,0,0,3683,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.aircanada.com/ca/en/aco/home.html#/home:mngBook-1,2,-94,-115,1,32,32,0,0,0,0,732,0,1606107114358,17,17181,0,0,2863,0,0,734,0,0,4C0C6AC554A7A70427A3AE415B52FEF3~-1~YAAQHiQcuIUNePF1AQAAn6xx8wTUT4mbrULjXf7tyjM5kzsjXaMQIGBl5YCOGHRLmaC86zpTlb9Z+wTbhkCbw4VXa39++yXEdSQusOwi1GEsxfY7fg6aWOxZfHAqShbenRU7I/ipRdH5fFnrqxYnno7QZLqSzbff1Ju8zRnZzOalnr5CVRz/NQ+K7/aSWBc/IBTdYEApqiiTiEstWKB3+jUwXeC4Dbc+WhMylwAHvupHFJ4azH54mA+CSypZ0siKAy+fv5nSKXsAYNgDoMi/AYAkg3Q4RxsFB4RiiH/bP9vJLhlCltvJ9QsqcEjgDD4NSHl2ZxPw4rfS5+Oy/9TQyThK5x/Vcu/n~-1~-1~-1,33225,469,1492174842,30261693,PiZtE,103337,102-1,2,-94,-106,9,1-1,2,-94,-119,29,31,31,535,50,52,32,9,7,6,6,382,266,365,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,106715571-1,2,-94,-118,220021-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,,,,0-1,2,-94,-121,;9;20;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        if (!isset($this->key)) {
            $this->key = array_rand($sensorData);
            $this->logger->notice("set key: {$this->key}");
        }
        $this->logger->notice("key: {$this->key}");

        $sensor_data = $secondSensor === false ? $sensorData[$this->key] : $secondSensorData[$this->key];

        return $sensor_data;
    }

    private function sendStatistic($success)
    {
        $this->logger->notice(__METHOD__);
        StatLogger::getInstance()->info("aeroplan sensor_data attempt", [
            "success"         => $success,
            "userAgentStr"    => $this->http->userAgent,
            "attempt"         => $this->attempt,
            "isWindows"       => stripos($this->http->userAgent, 'windows') !== false,
        ]);
    }
}
