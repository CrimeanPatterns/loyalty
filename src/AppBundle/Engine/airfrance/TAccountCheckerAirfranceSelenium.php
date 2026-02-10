<?php

use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirfranceSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    public string $host = 'wwws.airfrance.us';
    public ?CaptchaRecognizer $recognizer = null;

    private TAccountChecker $curlChecker;

    /*private string $sha256HashProfileFlyingBlueBenefitsQuery = 'ee0498f9ac6236f86f09013c8621ab2894e36e17dd0d0d8fb80b856514b23379';
    // reservation
    private string $sha256HashReservations = '4e3f2e0b0621bc3b51fde95314745feb4fd1f9c10cf174542ab79d36c9dd0fb2';
    private string $sha256HashReservation = '8ceaed40ef2387f278f78846e6b23f5861483a3277b65adacf0408f9f4a9c9a0';
    private string $sha256HashTripReservationTicketPriceBreakdownQuery = '2645ba4eec72a02650ae63c2bd78d14a3f0025dddfca698f570b96a630667fe0';
    // history
    private string $sha256HashProfileFlyingBlueTransactionHistoryQuery = 'a4da5deea24960ece439deda2d3eac6c755e88ecfe1dfc15711615a87943fba7';*/
    private int $stepItinerary = 0;


    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        switch ($this->host) {
            case 'wwws.airfrance.us':
                $this->useChromePuppeteer();
                $this->useCache();
                //$this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
                $this->setProxyNetNut(null, 'fr');

                if ($this->attempt > 0) {
                    $wrappedProxy = $this->services->get(WrappedProxyClient::class);
                    $proxy = $wrappedProxy->createPort($this->http->getProxyParams());
                    $this->seleniumOptions->antiCaptchaProxyParams = $proxy;
                    $this->seleniumOptions->addAntiCaptchaExtension = true;
                }

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $this->seleniumOptions->setResolution([
                        $fingerprint->getScreenWidth(),
                        $fingerprint->getScreenHeight()
                    ]);
                    $this->http->setUserAgent($fingerprint->getUseragent());
                }
                break;

            case 'www.klm.com':
                $this->useChromePuppeteer();
                //$this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);

                $this->useCache();
                $this->setProxyNetNut(null, 'us');
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $this->seleniumOptions->setResolution([
                        $fingerprint->getScreenWidth(),
                        $fingerprint->getScreenHeight()
                    ]);
                    $this->http->setUserAgent($fingerprint->getUseragent());
                }
                break;
            case 'account.bluebiz.com':
                /*if ($this->attempt == 0) {
                    $this->setProxyGoProxies();
                } elseif ($this->attempt == 1) {
                    $this->http->SetProxy($this->proxyDOP());
                }*/
                $this->setProxyMount();

                $this->useChromePuppeteer();
                $this->useCache();
                //$this->useCache();

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $this->seleniumOptions->setResolution([
                        $fingerprint->getScreenWidth(),
                        $fingerprint->getScreenHeight()
                    ]);
                    $this->http->setUserAgent($fingerprint->getUseragent());
                }
                break;
        }
        $this->KeepState = true;
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://{$this->host}/endpoint/v1/oauth/login?ref=/profile/flying-blue/dashboard", [], 20);
        if ($this->http->FindPreg("/\"isLoggedIn\":true,/")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://{$this->host}/profile");
            $this->acceptCookies();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeoutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: {$e->getMessage()}");

            throw new CheckRetryNeededException(3);
        }

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath($providerBugFix = '//a[@aria-label="Log in with your password instead?"] 
        | //span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] 
        | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")] 
        | //div[contains(text(), "The page you\'re looking for cannot be found")]'), 15);
        $this->savePageToLogs($this);

        // provider bug fix
        if ($this->http->FindSingleNode('//div[contains(text(), "The page you\'re looking for cannot be found")]')) {
            $this->http->GetURL("https://{$this->host}/endpoint/v1/oauth/redirect?loginPrompt=&source=profile&locale=US/en-US");
            $this->waitForElement(WebDriverBy::xpath($providerBugFix), 20);
            $this->savePageToLogs($this);
        }

        $loginWithPass = $this->waitForElement(WebDriverBy::xpath('//a[@aria-label="Log in with your password instead?"]'), 0);

        if (!$loginWithPass) {
            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //h1[contains(text(), "Access Denied")] | //div[contains(text(), "Our security system has detected that your IP address has a bad reputation and has blocked further access to our website")] | //div[@class="bw-spin-circle"]/@class')) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }

        $this->acceptCookies();
        $loginWithPass->click();

        $passwordInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="password"]'), 10);
        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname="loginId"]'), 0);
        $this->savePageToLogs($this);

        if (!$loginInput || !$passwordInput) {
            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                throw new CheckRetryNeededException(3, 1);
            }

            return false;
        }

        $this->acceptCookies();

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(100, 300);
        $mover->steps = rand(50, 60);

        $this->logger->debug("set login");
        $this->saveResponse();
        $mover->sendKeys($loginInput, $this->AccountFields['Login'], 30);
        $mover->click();
        $this->logger->debug("set pass");
        $passwordInput->click();
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 30);
        $mover->click();
        $this->logger->debug("click by 'remember me'");
        $mover->moveToElement($loginInput);
        $mover->click();
        // remember me
        $this->driver->executeScript('
                var rememberme = document.querySelector(\'[id = "mat-slide-toggle-1-input"]\');
                if (rememberme)
                    rememberme.click();
            ');

        $captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0);
        $this->saveResponse();

        if ($captchaField) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                return false;
            }

            $captchaField->sendKeys($captcha);
            $this->saveResponse();
            /*
            $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
            $this->saveResponse();

            if (!$button) {
                return false;
            }

            $button->click();
            */
        }

        $this->logger->debug("click by btn");
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 10);
        $this->saveResponse();

        if (!$button) {
            $this->checkErrors();
            return false;
        }

        $button->click();

        $captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 10);
        $this->saveResponse();

        if ($captchaField || $this->waitForElement(WebDriverBy::xpath("//div[@formcontrolname='recaptchaResponse']"), 0, false)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha !== false) {
                $captchaField->sendKeys($captcha);
            } else {
                $this->waitFor(function () {
                    $this->logger->warning("Solving is in process...");
                    sleep(3);
                    $this->saveResponse();

                    return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                }, 180);

                if ($this->attempt == 0 && $this->http->FindSingleNode('//div[@formcontrolname="recaptchaResponse"]//iframe/@title')) {
                    throw new CheckRetryNeededException(3, 1);
                }
            }

            $this->saveResponse();
            $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3);
            $this->saveResponse();

            if ($button) {
                $this->logger->debug("click by btn");

                try {
                    $button->click();
                } catch (
                    Facebook\WebDriver\Exception\StaleElementReferenceException
                    | Facebook\WebDriver\Exception\ElementClickInterceptedException
                    $e
                ) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
            }// if ($button)
        }// if ($captchaField || $this->waitForElement(WebDriverBy::xpath("//div[@formcontrolname='recaptchaResponse']"), 0, false))

        $result = $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "bwc-logo-header__user-name")]
            | //div[contains(@class, "bw-profile-recognition-box__info")]/h1
            | //span[contains(text(), "Invalid Captcha")]
            | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]
            | //div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Get your one-time PIN code")]
            | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
            | //div[contains(@class, "bwc-form-errors")]/span
        '), 20);
        $this->saveResponse();

        if ($result && str_contains($result->getText(), 'Get your one-time PIN code')) {
            $this->logger->notice('started 2fa');

            return $this->parseQuestion();
        }

        $this->acceptCookies();
        $this->savePageToLogs($this);

        $solvingStatus =
            $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
            ?? $this->http->FindSingleNode('//a[@class = "status"]');

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

                throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
            }

            $this->DebugInfo = $solvingStatus;
        }

        if (
            $this->http->FindSingleNode('//span[contains(@class, "bwc-logo-header__user-name")]')
            || $this->http->FindSingleNode('//div[contains(@class, "bw-profile-recognition-box__info")]/h1')
            || $this->http->FindSingleNode('//h1[contains(text(), "Dashboard")]')
        ) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        $message = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
            ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');

        if ($message) {
            if (
                strstr($message, 'Incorrect username and/or password. Please check and try again.')
                || strstr($message, 'These login details appear to be incorrect. Please verify the information and try again')
                || strstr($message, 't find the e-mail address or Flying Blue number you entered')
                || strstr($message, 't find the e-mail address or Flying Blue number entered')
                || strstr($message, 'The password you entered is not valid.')
                || strstr($message, 'More than 1 passenger is registered with this e-mail address. Please log in with your Flying Blue number instead, so we can uniquely identify you.')
                || $message == 'Please enter a valid password.'
                || $message == 'Please enter a valid e-mail address.'
                || $message == 'Your temporary password has expired.'
                || strstr($message, 'Sorry, we can\'t recognise your password due to a technical error.')
                || strstr($message, 'Sorry, we cannot log you in right now. Contact us via the')
                || strstr($message, 'Oops, the login details you entered are incorrect.')
                || strstr($message, 'Your e-mail address seems to be invalid.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Unfortunately, your account is blocked.')
                || strstr($message, 'Your account is blocked. Please wait 24 hours before clicking "Forgot password?" to reset your password.')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'Authentication failed: recaptchaResponse')
                || strstr($message, 'Access denied: Ineligible captcha score')
                || $message == 'Invalid Captcha'
            ) {
                $this->captchaReporting($this->recognizer, false);
                $this->DebugInfo = $message;

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            if (
                strstr($message, 'Due to a technical error, it is not possible to log you in right now.')
                || strstr($message, 'Due to a technical error, we cannot log you in right now.')
            ) {
                $this->markProxyAsInvalid();

                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = "block, technical error";

                throw new CheckRetryNeededException(2, 0);
            }

            if (
                $message == 'Retrieved unexpected result from mashery.'
                || $message == 'Forbidden'
                || $message == 'Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.'
            ) {
                $this->captchaReporting($this->recognizer);
                $this->DebugInfo = $message;

                throw new CheckException("Sorry, an unexpected technical error occurred. Please try again or contact the Air France customer service team.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Sorry, our system fell asleep. Please restart your login.'
                || strstr($message, 'Communication email is invalid')
                || strstr($message, 'Sorry, we cannot verify your password due to a technical issue')
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re experiencing some unexpected issues, but our team is already working to fix the problem")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        if ($this->http->FindSingleNode('//div[contains(@class, "login-form-converse-stmt-greeting") and contains(text(), "Update your password")]')) {
            $this->throwProfileUpdateMessageException();
        }
        return false;
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        if ($captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                return false;
            }

            $captchaField->sendKeys($captcha);
            $this->saveResponse();
        }

        sleep(2);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]/span[contains(text(),"Continue")]'), 3);
        $this->saveResponse();

        if (!$button) {
            if (isset($captcha) && $captcha === '') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }

        $button->click();
        sleep(10);
        $result = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
        $this->saveResponse();

        /*try {
            if (!$result && $button->isDisplayed()) {
                $this->waitFor(function () {
                    $this->logger->warning("Solving is in process...");
                    sleep(3);
                    $this->saveResponse();

                    return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                }, 180);

                $result = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
            }

            if (!$result && $button->isDisplayed()) {
                $this->logger->info('repeat button click');
                $button->click();
                $result = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
            }

            $this->savePageToLogs($this);
        } catch (StaleElementReferenceException | \Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            $this->logger->error($this->DebugInfo = 'error with repeating button click');
        }*/
        if (!$result) {
            $this->driver->executeScript('document.querySelector("button[aria-label=\'Log in\']").click();');

            sleep(3);
            $result = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
            $this->saveResponse();
        }
        if (!$result) {
            $this->savePageToLogs($this);
            $this->DebugInfo = 'otp input not found';
            $this->logger->error($this->DebugInfo);

            return false;
        }
        $blocking = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Oops, looks like you have missed some fields. Please check the fields and try again.")]'), 0);
        $this->saveResponse();
        if ($blocking) {
            $this->DebugInfo = $blocking->getText();
            $this->logger->error($this->DebugInfo);
            throw new CheckException($this->DebugInfo, ACCOUNT_LOCKOUT);
        }

        $this->holdSession();
        $this->AskQuestion('We’ve sent the PIN code to your e-mail address', null, 'Question');

        return false;
    }

    private function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");
        $otpInput = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'), 5);
        if (!$otpInput) {
            $this->saveResponse();

            return false;
        }

        $this->logger->debug("entering code...");
        $elements = $this->driver->findElements(WebDriverBy::xpath('//*[contains(@class, "bwc-one-time-pin__form-field")]//input | //input[@autocomplete="one-time-code"]'));

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$answer[$key]}");
            $element->click();
            $element->sendKeys($answer[$key]);
            $this->saveResponse();
        }
        if (!$this->solveCaptchaImg()) {
            return false;
        }
        $button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 1);
        if (!$button) {
            return false;
        }
        $button->click();
        $this->waitForElement(WebDriverBy::xpath('
            //span[contains(@class, "bwc-logo-header__user-name")]
            | //div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)]
            | //div[contains(@class, "bwc-form-errors")]/span
        '), 15);
        $this->saveResponse();

        $captchaAttempt = 0;

        while ($this->waitForElement(WebDriverBy::xpath('//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)] | //div[contains(@class, "bwc-form-errors")]/span'), 0)) {
            $this->saveResponse();
            $error = $this->http->FindSingleNode('//div[contains(@class, "bwc-form-errors")]/span')
                ?? $this->http->FindSingleNode('(//div[@class = "bwc-form-errors"]//node()[not(@hidden)]/div[not(@class)])[last()]');
            $this->logger->error("[Error]: $error");

            if (strstr($error, 'Invalid Captcha') && ++$captchaAttempt < 3) {
                if (!$this->solveCaptchaImg()
                    || !$button = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Log in" and not(@disabled)]'), 3)
                ) {
                    return false;
                }
                $button->click();
                sleep(3);

                continue;
            }

            if (
                strstr($error, 'This is not the right PIN code. Please try again.')
                || strstr($error, 'You have entered an incorrect PIN code. Please try again.')
                || strstr($error, 'Your one-time PIN code has expired')
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }

            if (strstr($error, 'Sorry, an unexpected technical error occurred')) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }
        $this->logger->debug("success");


        return true;
    }

    private function solveCaptchaImg()
    {
        $this->logger->notice(__METHOD__);

        if ($captchaField = $this->waitForElement(WebDriverBy::xpath("//input[contains(@data-placeholder,'Please enter the characters display')]"), 0)) {
            $captcha = $this->parseCaptchaImg();

            if ($captcha === false) {
                $this->saveResponse();

                return false;
            }

            $captchaField->sendKeys($captcha);
        }

        return true;
    }
    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            $this->sendNotification('ProcessStep // MI');
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse()
    {
        $checker = $this->getCurlChecker();
        $host = $this->http->getCurrentHost();
        $this->logger->debug("host: $host");
        $checker->Parse();
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
        $checker = $this->getCurlChecker();
        $checker->ParseItineraries();
        return [];
    }

    public function ParseHistory($startDate = null)
    {
        $checker = $this->getCurlChecker();
        $checker->ParseHistory($startDate);
    }

    public function acceptCookies()
    {
        $this->logger->notice(__METHOD__);
        try {
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "accept_cookies_btn"]'), 3);
            $this->saveResponse();
            if (!$btn) {
                return;
            }
            $btn->click();
            sleep(3);
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }
    }

    protected function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->waitForElement(WebDriverBy::xpath("//span[@class = 'asfc-svg-captcha']"), 0);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $parameters = [
            "regsense" => 1,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, $parameters);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function getCurlChecker()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->curlChecker)) {
            $this->curlChecker = new TAccountCheckerAirfrance();
            $this->curlChecker->http = new HttpBrowser("none", new CurlDriver());
            $this->curlChecker->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->curlChecker->http);
            $this->curlChecker->AccountFields = $this->AccountFields;
            $this->curlChecker->itinerariesMaster = $this->itinerariesMaster;
            $this->curlChecker->HistoryStartDate = $this->HistoryStartDate;
            $this->curlChecker->historyStartDates = $this->historyStartDates;
            $this->curlChecker->http->LogHeaders = $this->http->LogHeaders;
            $this->curlChecker->ParseIts = $this->ParseIts;
            $this->curlChecker->ParsePastIts = $this->ParsePastIts;
            $this->curlChecker->WantHistory = $this->WantHistory;
            $this->curlChecker->WantFiles = $this->WantFiles;
            $this->curlChecker->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);

            $this->curlChecker->globalLogger = $this->globalLogger;
            $this->curlChecker->logger = $this->logger;
            $this->curlChecker->onTimeLimitIncreased = $this->onTimeLimitIncreased;

            $cookies = $this->driver->manage()->getCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'currency') {
                    $this->currency = $cookie['value'];
                }
                $this->curlChecker->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }

        return $this->curlChecker;
    }
}
