<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerChaseSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

//    private $chase = null;
    public const INVALID_IP_PREFIX = "chase_lum_ip_v2_";
    private $statContext = [];
    private $actualProxyIp;
    private $debugIpFilter = true;

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->UseSelenium();

        if ($this->Step === null) {
            // we do not want to reconnect to existing session, if there are no question
            // (question could be discarded due to timeout)
            if (isset($this->BrowserState["Driver"]["SessionID"])) {
                $this->logger->info("do not restore session, no step");
                unset($this->BrowserState["Driver"]["SessionID"]);
            }
        }

        $this->http->saveScreenshots = true;

        if ($this->isExperimental()) {
            $this->selectExperimentalSettings();
        } else {
            $this->selectBaseSettings();
        }
    }

    public function LoadLoginForm()
    {
        $this->Answers = [];
//        $this->http->GetURL("https://www.chase.com/");
        try {
            if (
                ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
                && !$this->checkCurrentProxy()
            ) {
                $this->logger->warning("current proxy is not working, reset proxy and retry");
                $this->callRetries();
            }

            try {
                $this->checkProxyIp();
                $this->http->GetURL("https://secure.chase.com/");
            } catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("LoadLoginForm -> exception: " . $e->getMessage());
                $this->callRetries();
            } catch (\WebDriverCurlException $e) {
                $this->logger->error("LoadLoginForm -> WebDriverCurlException: " . $e->getMessage());
            }

            if ($this->http->FindSingleNode("//h2[contains(text(), 'Access Denied')]")) {
                $this->callRetries();
            }

            if ($this->isExperimental()) {
                $this->tuneExpirementalSettings();
            } else {
                $this->tuneBaseSettings();
            }
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage());
            $this->saveResponse();
            $this->callRetries();
        }
        $this->waitForElement(WebDriverBy::xpath("
            //iframe[@id = 'logonbox']
            | //input[@name = 'userId']
            | //h2[contains(text(), 'System requirements')]
            | //button[@id = 'convo-deck-sign-out']
            | //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
            | //h1[contains(text(), 'The connection has timed out')]
            | //p[contains(., 'refused to connect.')]
            | //strong[@jscontent = 'hostName']
        "), 90);
        $logonbox = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'logonbox']"), 0);
        $this->saveResponse();

        if (!$logonbox) {
            $logonbox = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'logonbox']"), 0, false);
        }

        if (!$logonbox) {
            if ($this->waitForElement(WebDriverBy::xpath("//button[@id = 'convo-deck-sign-out']"), 0)) {
                $this->logger->notice("session is active");

                return true;
            }

            $this->logger->error('something went wrong');
            // This site can’t be reached
            if (
                $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]")
                || $this->waitForElement(WebDriverBy::xpath("//div[@class = 'spinnerWrapper']"), 0)
                || $this->http->FindPreg("/<\/head><body><pre><\/pre><\/body>/")
                || $this->http->FindPreg("/<pre><\/pre><pre/")
                || $this->http->FindPreg("/<head><link[^>]+><\/head><body><pre><\/pre><(?:span|a)[^>]+><\/(?:span|a)><(?:span|a)[^>]+><\/(?:span|a)><\/body>/")
                || $this->http->FindPreg('/<(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><\/body>/ims')
                || $this->http->FindPreg('/secure[^\.]+\.chase.com<\/strong> refused to connect\.<\/p>/ims')
            ) {
                $this->logger->info("can't load page. slow proxy?");
                $this->DebugInfo = "This site can’t be reached";
                $this->callRetries();
            }// if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]"))

            return $this->checkErrors();
        }

        if ($logonbox) {
            try {
                $this->driver->switchTo()->frame($logonbox);
            } catch (WebDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
        }

        return $this->sendLoginForm();
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We'll be back shortly.
        if ($this->http->FindSingleNode('//h2[contains(text(), "We\'ll Be Back Shortly")]')) {
            throw new CheckException("We're making improvements to the site and will return as soon as possible.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->waitSomeUI();

            $n = 0;

            while ($this->isLooksLikeNoWorkingError() && $n < 3) {
                $this->logger->info("retrying: $n");

                $tryAgain = $this->waitForElement(WebDriverBy::id('exitLogonError'), 1);

                if (!$tryAgain) {
                    break;
                }

//                $this->markActualProxyInvalid();
//                $this->debugIpFilter = false;
//                $ip = $this->selectBrightDataIpBy([$this, "luminatiProxyFilter"]);
//
//                if ($ip === null) {
//                    break;
//                }

                $tryAgain->click();
                $this->driver->switchTo()->defaultContent();
//                $this->actualProxyIp = $ip;
                /** @var SeleniumDriver $seleniumDriver */
//                $seleniumDriver = $this->http->driver;
                // looks like no reason to change proxy, you need to just repeat sending form
                //$seleniumDriver->browserCommunicator->switchProxyAuth("lum-customer-" . ILLUMINATI_CUSTOMER . "-zone-static-dns-remote-ip-" . $ip, ILLUMINATI_PASS);
                $iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'logonbox']"), 7);
                $this->driver->switchTo()->frame($iframe);
                $this->sendLoginForm();
                $this->waitSomeUI();
                $this->saveResponse();
                $n++;
            }

            if ($this->waitSignoutButton(0)) {
                return true;
            }

            $this->saveResponse();

            $next = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'requestDeliveryDevices-sm'] | //div[@id = 'simplerAuth-dropdownoptions-styledselect']"), 0);

            if (!$next && ($differentWay = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Contact me in a different way")]'), 0))) {
                $differentWay->click();
                $differentWayNext = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'requestIdentificationCode-sm']"), 0);

                if (!$differentWayNext) {
                    $this->saveResponse();

                    return false;
                }

                $differentWayNext->click();
                $next = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'requestDeliveryDevices-sm'] | //div[@id = 'simplerAuth-dropdownoptions-styledselect']"), 5);

                $this->logger->debug("open popup");
                $this->driver->executeScript('
                    $(\'#header-simplerAuth-dropdownoptions-styledselect\').get(0).click();
                ');
                sleep(1);

                $this->saveResponse();
            }// if (!$next && ($differentWay = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Contact me in a different way")]'), 0)))
            // Get a text. We'll text a one-time code to your phone.
            elseif (!$next && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(),"For your security, we need to confirm your identity.")]'), 2)) {
                $this->driver->executeScript('document.querySelector("mds-list").shadowRoot.querySelector("li a").click()');

                $questionMsg = $this->waitForElement(WebDriverBy::xpath('//p[@id="introduction-message"]'), 5);
                sleep(4);
                $this->saveResponse();

                $questionPhone = $this->driver->executeScript('
                if (document.querySelector("#sms-select-text") !== null) 
                    return document.querySelector("#sms-select-text").innerText;
                else if (document.querySelector("mds-select") !== null)
                    return document.querySelector("mds-select").shadowRoot.querySelector("#select-eligibleTextContacts").innerText;
                else "";
                ');

                if (isset($questionMsg, $questionPhone)) {
                    $this->driver->executeScript('document.querySelector("mds-button").shadowRoot.querySelector("button").click();');
                    $this->waitForElement(WebDriverBy::id('caasBody'), 7);
                    sleep(2);
                    $this->logger->debug("Question: {$questionMsg->getText()} $questionPhone");
                    $this->holdSession();
                    $this->AskQuestion("For your security, we'll text a one-time code to your mobile: $questionPhone", null, "oneTimeCode");
                    $this->saveResponse();
                }

                return false;
            }

            if ($next) {
                if ($this->actualProxyIp) {
                    $this->State['illuminati-ip'] = $this->actualProxyIp;
                }
                $next->click();

                $sendEmailBtn = null;
                $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                    //label[contains(@for, "input-deviceoption") and contains(., "@")]
                    | //a[not(@aria-disabled="true") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "@")]]/span[contains(@class, "primary")]
                '), 1);
                // refs #21135, do call instead sending sms
                $exceptions = [
                    'linagoldberg1',
                ];
                // Phone number (if verification by email not supported)
                if (!$sendEmailBtn || in_array($this->AccountFields['Login'], $exceptions)) {
                    if ($this->isStartedByStaff()) {
                        $this->logger->notice("send code via sms");
                        $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                            //a[not(@aria-disabled="true") and not(@rel = "Call") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "-")]]/span[contains(@class, "primary")]
                    '), 0);
                    } else {
                        $this->logger->notice("send code via call");
                        $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                            //a[not(@aria-disabled="true") and not(@rel = "Call") and contains(@rel, "V") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "-")]]/span[contains(@class, "primary")]
                    '), 0);
                    }
                }
                $this->saveResponse();

                if (!$sendEmailBtn) {
                    return false;
                }
                $email = $sendEmailBtn->getText();
                $this->logger->debug("Email: {$email}");

                /*
                if (empty($email)) {
                    $email = $this->http->FindSingleNode('
                        (
                            //label[contains(@for, "input-deviceoption") and contains(., "@")]
                            | //a[not(@aria-disabled="true") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "@")]]/span[contains(@class, "primary")]
                        )[1]
                    ');
                    $this->logger->debug("Email: {$email}");
                }
                */

                if (strstr($email, '@')) {
                    $question = "Please enter Identification Code which was sent to the following email address: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                } else {
                    $question = "Please enter the Identification Code which you received via the following line: {$email} over an automated voice call. Please note that you must provide the latest code that you have received as the previous codes will not work.";

                    if ($this->isStartedByStaff()) {
                        $question = "Please enter Identification Code which was sent to the following phone number: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";
                    }
                }
                $this->logger->debug("Question: {$question}");

                $this->sendStatistic();

                $openPopup = false;
                $next = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'simplerAuth-dropdownoptions-styledselect']"), 0);

                if ($next) {
                    $this->logger->notice("new auth");
                    $this->saveResponse();

                    /*
                    if (!$this->waitForElement(WebDriverBy::xpath('//a[not(@aria-disabled="true") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "@")]]/span[contains(@class, "primary")]'), 0)) {
                    */
                    $next->click();
//                    $this->saveResponse();
                    /*
                    }
                    */
                    $openPopup = true;
                }

                if (
                    isset($this->Answers[$question])
                    && ($code_already_exist = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'request_identification_code_already_exist_message' or @id = 'request-identification-code-already-exist-message']"), 0))
                ) {
                    $openPopup = false;

                    if ($this->waitForElement(WebDriverBy::xpath("//a[@id = 'request-identification-code-already-exist-message']"), 0)) {
                        $openPopup = true;
                        $this->driver->executeScript('$(\'#request-identification-code-already-exist-message\').get(0).click();');
                    } else {
                        $code_already_exist->click();
                    }

                    $this->waitForElement(WebDriverBy::xpath('
                        //input[@id = "otpcode_input-input-field"]
                        | //h2[contains(text(), "We weren\'t able to send your temporary identification code.")]
                        | //div[contains(., "We weren\'t able to send your temporary identification code.")]
                    '), 5);
                    $this->saveResponse();

                    $this->Question = $question;

                    if (!$this->waitForElement(WebDriverBy::xpath('
                            //h2[contains(text(), "We weren\'t able to send your temporary identification code.")]
                            | //div[contains(., "We weren\'t able to send your temporary identification code.")]
                    '), 0)) {
                        return $this->questionIdentificationCode();
                    }

                    if ($openPopup) {
                        $next = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'requestDeliveryDevices-sm'] | //div[@id = 'simplerAuth-dropdownoptions-styledselect']"), 5);
                        $this->saveResponse();
                        $next->click();

                        $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                            //label[contains(@for, "input-deviceoption") and contains(., "@")]
                            | //a[not(@aria-disabled="true") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "@")]]/span[contains(@class, "primary")]
                        '), 3);

                        if (!$sendEmailBtn) {
                            if ($this->isStartedByStaff()) {
                                $this->logger->notice("send code via sms");
                                $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                                    //a[not(@aria-disabled="true") and not(@rel = "Call") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "-")]]/span[contains(@class, "primary")]
                        '), 0);
                            } else {
                                $this->logger->notice("send code via call");
                                $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('
                                    //a[not(@aria-disabled="true") and not(@rel = "Call") and contains(@rel, "V") and span[contains(@id, "simplerAuth-dropdownoptions-styledselect") and contains(., "-")]]/span[contains(@class, "primary")]
                        '), 0);
                            }
                        }// if (!$sendEmailBtn)

                        $this->saveResponse();
                        $this->logger->debug("Question: {$question}");
                    }
                }

                $this->logger->debug("Click by email btn");

                if ($openPopup) {
                    $this->logger->debug("Click by email btn (new auth)");

                    $rel = '[rel *= "V"]';

                    if ($this->isStartedByStaff()) {
                        $this->logger->notice("send code via sms");
                        $rel = '';
                    } else {
                        $this->logger->notice("send code via call");
                    }
                    /*
                    // refs #21135, do call instead sending sms
                    if (in_array($this->AccountFields['Login'], $exceptions)) {
                        $rel = '[rel *= "V"]';
                    }
                    */

                    $this->driver->executeScript('
                    let choice = $(\'a:not([aria-disabled="true"]) span[id *= "simplerAuth-dropdownoptions-styledselect"]:contains("@")\');
                    if (choice.length == 0) {
                        choice = $(\'a:not([aria-disabled="true"]):not([rel = "Call"])' . $rel . ' span.groupingName[id *= "simplerAuth-dropdownoptions-styledselect"]:contains("-")\');
                    }
                    choice.get(0).click();
                ');
                    $this->saveResponse();
                } else {
                    $sendEmailBtn->click();
                }

                sleep(1);
                $getCode = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'requestIdentificationCode-sm' or @id = 'requestIdentificationCode']"), 0);

                if (!$getCode) {
                    return false;
                }

                if ($this->isBackgroundCheck()/* && !$this->getWaitForOtc()*/) {
                    $this->Cancel();
                }

                $getCode->click();

                $this->waitForElement(WebDriverBy::id('otpcode_input-input-field'), 5);
                $this->saveResponse();
                $this->holdSession();
                $this->AskQuestion($question, null, "IdentificationCode");

                return false;
            } elseif ($this->waitForElement(WebDriverBy::xpath('//a[contains(@aria-label, "Get a text")] | //a[contains(@aria-label, "Get a call")]'), 0)) {
                if ($this->isStartedByStaff()) {
                    $this->logger->notice("send code via sms");
                    $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('//a[contains(@aria-label, "Get a text")]'), 0);
                } else {
                    $this->logger->notice("send code via call");
                    $sendEmailBtn = $this->waitForElement(WebDriverBy::xpath('//a[contains(@aria-label, "Get a call")]'), 0);
                }

                if ($this->isBackgroundCheck()/* && !$this->getWaitForOtc()*/) {
                    $this->Cancel();
                }

                $sendEmailBtn->click();

                $this->waitForElement(WebDriverBy::id('otpcode_input-input-field'), 5);
                $this->saveResponse();
//                $this->holdSession();
//                $this->AskQuestion($question, null, "IdentificationCode");

                return false;
            }

            $this->checkProviderError();

            // TODO: debug
            $this->http->removeCookies();

            try {
                $this->http->GetURL("https://www.chase.com/");
            } catch (NoSuchWindowException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
            $iframe = $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'logonbox']"), 7);
            $this->saveResponse();

            if ($iframe) {
                $this->driver->switchTo()->frame($iframe);
                $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'userId']"), 0);
                $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
                $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signin-button']"), 0);
                $this->saveResponse();

                if (
                !empty($login)
                && !empty($pass)
                && !empty($submitButton)
            ) {
                    $this->logger->debug('Login: ' . $login->getText());
                    $this->logger->debug('Pass: ' . $pass->getText());

                    $login->sendKeys($this->AccountFields['Login']);
                    $pass->sendKeys($this->AccountFields['Pass']);

                    $this->logger->debug("clicking submit");
                    $this->saveResponse();
//            if (
//                $login->getText() == $this->AccountFields['Login']
//                && $pass->getText() == $this->AccountFields['Pass']
//            ) {
                    $submitButton->click();

                    $this->waitForElement(WebDriverBy::xpath('
                        //button[@id = "convo-deck-sign-out"]
                        | //h3[contains(text(), "We don\'t recognize the computer you\'re using.")]
                        | //div[@id = "content-logon-error"]
                        | //div[@id = "inactiveAccountDialog"]
                        | //div[@id = "serviceErrorDialog"]
                        | //div[@id = "errorDialog"]
                        | //a[contains(@href, "LogOff")]
                        | //a[contains(@href, "logoffbutton")]
                        | //span[contains(@class, "header-label") and contains(text(), "Accounts")]
                        | //span[contains(@class, "header-label") and contains(text(), "Bank accounts")]
                        | //div[@role="heading" and contains(text(), "Credit cards")]
                    '), 10);
                    $this->saveResponse();

                    // it helps
                    if ($this->http->FindSingleNode('//button[@id = "convo-deck-sign-out"] | //a[contains(@href, "LogOff")] | //a[contains(@href, "logoffbutton")] | //span[contains(@class, "header-label") and contains(text(), "Accounts")] | //span[contains(@class, "header-label") and contains(text(), "Bank accounts")] | //div[@role="heading" and contains(text(), "Credit cards")]')) {
                        $this->sendStatistic();

                        return true;
                    }
//            }

                    $this->checkProviderError();
                }
            } elseif ($this->waitForElement(WebDriverBy::xpath("
                //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working')]
                | //h1[contains(text(), 'The connection has timed out')]
                | //p[contains(., 'refused to connect.')]
            "), 0)) {
                $this->callRetries();
            }
        } catch (WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    public function checkProviderError()
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->http->FindNodes('//div[@id = "content-logon-error"]//span/following-sibling::node()')) {
            $message = Html::cleanXMLValue(implode(' ', $error));
            $this->logger->error("[Error]: {$message}");
            $this->sendStatistic();

            if (strstr($message, "We can't find that username and password.")) {
                throw new CheckException("We can't find that username and password. You can reset your password or try again.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        // It looks like this part of our site isn't working right now.
        // Please try again later. Thanks for your patience.
        if ($error = $this->isLooksLikeNoWorkingError()) {
            $this->markActualProxyInvalid();
            $this->markProxyAsInvalid();
            $this->sendStatistic(false);
            unset($this->State["Fingerprint"]);
            unset($this->State["UserAgent"]);
            unset($this->State["Resolution"]);
            unset($this->State["Proxy"]);
            unset($this->State["illuminati-session"]);
            unset($this->State["illuminati-ip"]);

            if ($this->State['LuminatiZone'] === 'static') {
                $this->logger->debug("excluding luminati {$this->State['LuminatiZone']} zone");
                $this->State['ExcludedLuminatiZones'] = [$this->State['LuminatiZone']];
                unset($this->State['LuminatiZone']);
            }

            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                throw new CheckException($error . " " . $this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1/following-sibling::div[1]'), ACCOUNT_PROVIDER_ERROR);
            }
//            throw new CheckException($error." ".$this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1/following-sibling::div[1]'), ACCOUNT_PROVIDER_ERROR);
            throw new CheckRetryNeededException(2, 1, $error . " " . $this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1/following-sibling::div[1]'));
        }

        if ($error = $this->http->FindNodes('//h1[contains(@class, "dialogTitle")]//span/following-sibling::node()')) {
            $this->sendStatistic();
            $message = Html::cleanXMLValue(implode(' ', $error));
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "We locked your account to protect it from unusual activity.")) {
                throw new CheckException($message . " To unlock it, call us at 877-242-7372.", ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "We locked your account due to unusual activity.")) {
                throw new CheckException($message . " To unlock it, call us at 877-242-7372.", ACCOUNT_LOCKOUT);
            }

            if (strstr($message, "We locked your account to protect it from suspicious activity.")) {
                throw new CheckException($message . " To unlock it, call 877-691-8086.", ACCOUNT_LOCKOUT);
            }

            if ($message == "We locked your account due to suspicious activity.") {
                throw new CheckException($message . " To unlock it, call 877-691-8086.", ACCOUNT_LOCKOUT);
            }

            return false;
        }

        if ($error = $this->http->FindNodes('//div[@id = "errorDialog"]//span/following-sibling::node()')) {
            $this->sendStatistic();
            $message = Html::cleanXMLValue(implode(' ', $error));
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "It looks like this part of our site is down for maintenance.")) {
                throw new CheckException($message . " " . $this->http->FindSingleNode('//div[@id = "errorDialog"]//h1/following-sibling::p[1]'), ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "I agree to the Digital Services Agreement")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        if (in_array($this->AccountFields['Login'], [
            'chrishick68',
            'jbreynolds77',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function sendStatistic($success = true)
    {
        StatLogger::getInstance()->info("chase login attempt", array_merge([
            "success"      => $success,
            "proxy"        => $this->http->getProxyAddress(),
            "browser"      => $this->seleniumRequest->getBrowser() . ":" . $this->seleniumRequest->getVersion(),
            "userAgentStr" => $this->http->userAgent,
            "resolution"   => $this->seleniumOptions->resolution ? ($this->seleniumOptions->resolution[0] . "x" . $this->seleniumOptions->resolution[1]) : "",
            "attempt"      => $this->attempt,
            "isWindows"    => stripos($this->http->userAgent, 'windows') !== false,
        ], $this->statContext));
    }

    public function questionOneTimeCode()
    {
        $this->logger->notice(__METHOD__);
        $caasBody = $this->waitForElement(WebDriverBy::id('caasBody'), 10);

        if (!$caasBody) {
            return false;
        }
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->driver->executeScript('document.querySelector("mds-text-input-secure").shadowRoot.querySelector("#otpInput-input").value = "' . $answer . '";');
        $this->driver->executeScript('document.querySelector("mds-button").shadowRoot.querySelector("button").click();');
        sleep(7);

        try {
            $error = $this->driver->executeScript('return document.querySelector("mds-error-message").shadowRoot.querySelector("#accessible-text-title").innerText;');

            if (!empty($error)) {
                $this->AskQuestion($this->Question, $error, "oneTimeCode");

                return false;
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
        }

        $this->waitSomeUI();

        return $this->waitSignoutButton(0);
    }

    public function questionIdentificationCode()
    {
        $this->logger->notice(__METHOD__);

        $identificationCode = $this->waitForElement(WebDriverBy::id('otpcode_input-input-field'), 5);
        $password = $this->waitForElement(WebDriverBy::id('password_input-input-field'), 0);

        if (!$identificationCode || !$password) {
            $this->logger->error("[IdentificationCode]: input not found");
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath("//button[@id = 'convo-deck-sign-out']"), 0)) {
                return true;
            }

            return false;
        }// if (!$identificationCode || !$password)

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;

        $identificationCode->click();
        sleep(1);
        $identificationCode->clear();
        $this->logger->debug("sending answer, length: " . strlen($this->Answers[$this->Question]));
        sleep(1);
        $mover->sendKeys($identificationCode, $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        $password->click();
        sleep(1);
        $password->clear();
        $this->logger->debug("sending password, length: " . strlen($this->AccountFields['Pass']));
        sleep(1);
        $mover->sendKeys($password, $this->AccountFields['Pass']);

        sleep(1);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $next = $this->waitForElement(WebDriverBy::xpath('//button[@id = "log_on_to_landing_page-sm"]'), 5);
        $this->saveResponse();

        if (!$next) {
            $this->logger->error("[IdentificationCode]: btn not found");

            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }
        $this->logger->debug("clicking next");
        $next->click();

        sleep(5);

        $this->waitForElement(WebDriverBy::xpath('
            //button[@id = "convo-deck-sign-out"]
            | //div[contains(text(), "The temporary identification code or password you entered is incorrect. Please try again.")]
            | //h2[@id = "inner-alert-the-user" and contains(., "We weren\'t able to sign you in.")]
            | //div[label[contains(@class, "error") and contains(text(), "One-time code")]]/following-sibling::div[1]/div[@id = "otpcode_input"]/div[@id = "error-bubble"]
            | //a[contains(@href, "LogOff")]
            | //a[contains(@href, "logoffbutton")]
            | //span[contains(@class, "header-label") and contains(text(), "Accounts")]
            | //span[contains(@class, "header-label") and contains(text(), "Bank accounts")]
            | //div[@role="heading" and contains(text(), "Credit cards")]
        '), 5);
        $this->saveResponse();

        if ($error = $this->http->FindSingleNode('
                //div[contains(text(), "The temporary identification code or password you entered is incorrect. Please try again.")]
                | //h2[@id = "inner-alert-the-user" and contains(., "We weren\'t able to sign you in.")]
            ')) {
            $this->AskQuestion($this->Question, $error, "IdentificationCode");

            return false;
        }

        if ($this->http->FindSingleNode("//div[label[contains(@class, 'error') and contains(text(), 'One-time code')]]/following-sibling::div[1]/div[@id = 'otpcode_input']/div[@id = 'error-bubble']/@class")) {
            $this->AskQuestion($this->Question, "The temporary identification code or password you entered is incorrect. Please try again.", "IdentificationCode");

            return false;
        }

        $this->waitSomeUI();

        // It looks like this part of our site isn't working right now.
        // Please try again later. Thanks for your patience.
        if ($error = $this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1')) {
            $this->markProxyAsInvalid();
            $this->sendStatistic(false);
            unset($this->State["Fingerprint"]);
            unset($this->State["UserAgent"]);
            unset($this->State["Resolution"]);
            unset($this->State["Proxy"]);
            unset($this->State["illuminati-session"]);
            unset($this->State["illuminati-ip"]);

            // it helps

            throw new CheckRetryNeededException(2, 1, $error . " " . $this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1/following-sibling::div[1]'));
        }

        // It's not you, it's us.
        if ($message = $this->http->FindSingleNode('//h2[@id = "inner-alert-the-user"]/text()[last()]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, "It's not you, it's us.")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        $this->saveResponse();

        return $this->waitSignoutButton(0);
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $otp = $this->waitForElement(WebDriverBy::id('otpcode_input-input-field'), 0);
        $this->saveResponse();

        if (
            !$otp
            && (
                    $this->waitForElement(WebDriverBy::xpath("
                        //iframe[@id = 'logonbox']
                        | //input[@name = 'userId']
                        | //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                        | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                        | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                        | //pre[not(@id) and normalize-space(text()) = '{\"code\":400,\"message\":\"Unknown method: start\"}']
                        | //p[contains(text(), 'Health check')]
                        | //span[contains(text(), 'This site can’t be reached')]
                    "), 0)
                    || $this->waitForElement(WebDriverBy::xpath("//iframe[@id = 'logonbox']"), 0, false)
            )
        ) {
            $this->saveResponse();

            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'IdentificationCode') {
            $this->saveResponse();

            return $this->questionIdentificationCode();
        } elseif ($step == 'oneTimeCode') {
            $this->saveResponse();

            return $this->questionOneTimeCode();
        }

        return true;
    }

    public function Parse()
    {
        $this->logger->debug("history start dates: " . json_encode($this->historyStartDates));
        $chase = $this->getChase();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $chase->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $chase->http->RetryCount = 0;

        try {
            $chase->http->GetURL($this->http->currentUrl(), [], 40);
        } catch (WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $chase->http->RetryCount = 2;

        if ($chase->http->FindPreg('/Operation timed out after/', false, $chase->http->Error)) {
            unset($this->State["UserAgent"]);
            $this->logger->debug("operation timed out. trying to detect ip, to check proxy");
            $chase->http->RetryCount = 0;
            $chase->http->GetURL("http://ipinfo.io/json", [], 5);

            throw new CheckRetryNeededException(2, 1);
        }

        $this->stopSeleniumBrowser();
        $chase->Parse();
        $this->SetBalance($chase->Balance);
        $this->Properties = $chase->Properties;
        $this->ErrorCode = $chase->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $chase->ErrorMessage;
            $this->DebugInfo = $chase->DebugInfo;
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"                    => "PostingDate",
            "Description"             => "Description",
            "Points"                  => "Miles",
            "Amount"                  => "Amount",
            "Currency"                => "Currency",
            "Details"                 => "Info",
            "Category"                => "Category",
            "Transaction Description" => "Info",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Transaction Description', 'Details',
        ];
    }

    public function luminatiProxyFilter(array $ipInfo)
    {
        $result =
            stripos($ipInfo['org'], 'digital energy') === false
            && stripos($ipInfo['org'], 'host royal') === false
            && in_array(strtolower($ipInfo['country']), ['us', 'ca'])
            && !$this->isProxyInvalid($ipInfo['ip'])
            && !$this->getProxyMemcached()->get(self::INVALID_IP_PREFIX . $ipInfo['ip'])
            //&& (!$this->debugIpFilter || $ipInfo['ip'] === '176.105.248.170')
        ;

        //$this->logger->info(json_encode($ipInfo) . ": " . json_encode($result));

        return $result;
    }

    /** @return TAccountCheckerChase */
    protected function getChase()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->chase)) {
            $this->chase = new TAccountCheckerChase();
            $this->chase->http = new HttpBrowser("none", new CurlDriver());
            $this->chase->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->chase->http);
            $this->chase->AccountFields = $this->AccountFields;
            $this->chase->HistoryStartDate = $this->HistoryStartDate;
            $this->chase->historyStartDates = $this->historyStartDates;
            $this->chase->http->LogHeaders = $this->http->LogHeaders;
            $this->chase->ParseIts = $this->ParseIts;
            $this->chase->ParsePastIts = $this->ParsePastIts;
            $this->chase->WantHistory = $this->WantHistory;
            $this->chase->WantFiles = $this->WantFiles;
            $this->chase->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->chase->http->setDefaultHeader($header, $value);
            }

            $this->chase->globalLogger = $this->globalLogger;
            $this->chase->logger = $this->logger;
            $this->chase->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->chase;
    }

    private function sendLoginForm()
    {
        $logonApp = $this->waitForElement(WebDriverBy::id("logonApp"), 10);

        if (!$logonApp) {
            return false;
        }
        sleep(1);
        $this->driver->executeScript('
            let sendEvent = function (element, eventName) {
                var event;         
                if (document.createEvent) {
                    event = document.createEvent("HTMLEvents");
                    event.initEvent(eventName, true, true);
                } else {
                    event = document.createEventObject();
                    event.eventType = eventName;
                }
                event.eventName = eventName;
                if (document.createEvent) {
                    element.dispatchEvent(event);
                } else {
                    element.fireEvent("on" + event.eventType, event);
                }
            };
            document.querySelector("mds-text-input").shadowRoot.querySelector("input").value = "' . $this->AccountFields['Login'] . '";
            document.querySelector("mds-text-input-secure").shadowRoot.querySelector("input").value = "' . $this->AccountFields['Pass'] . '";
            sendEvent(document.querySelector("mds-text-input").shadowRoot.querySelector("input"), "input");
            sendEvent(document.querySelector("mds-text-input-secure").shadowRoot.querySelector("input"), "input");
            document.querySelector("mds-button").shadowRoot.querySelector("button").click();
        ');

        return true;

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'userId']"), 10);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
        $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signin-button']"), 0);
        $this->saveResponse();

        if (empty($login) || empty($pass) || empty($submitButton)) {
            $this->logger->error('something went wrong');

            $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'userId']"), 15);
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'signin-button']"), 0);
            $this->saveResponse();

            if ($this->http->FindPreg('/(?:<body style="overflow-x: hidden; overflow-y: auto; height: 100%">\s*<\/body>|<head><\/head><body><\/body>|<body style="overflow-x: hidden; overflow-y: auto; height: 100%">\s*<div id="arcotuserdataDiv" display="none" style="display: none;"><\/div><div id="arcotuserdataDiv" display="none" style="display: none;"><\/div><div id="arcotuserdataDiv" display="none" style="display: none;"><\/div><\/body>)/')
                || $this->http->FindPreg('/<(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><\/body>/ims')
                || $this->http->FindSingleNode('//h1[contains(text(), "The connection has timed out")]')
            ) {
                $this->callRetries();
            }

            if (empty($login) || empty($pass) || empty($submitButton)) {
                $this->logger->error('epic fail');

                return $this->checkErrors();
            }
        }

//        $mover = new MouseMover($this->driver);
//        $mover->logger = $this->logger;
//        $mover->duration = rand(40000, 60000);
//        $mover->steps = rand(20, 40);
//
//        $mover->moveToElement($login);
//        $mover->click();
//        $cps = rand(10, 20);
//        $mover->sendKeys($login, $this->AccountFields['Login'], $cps);
//        $this->saveResponse();
//
//        $mover->moveToElement($pass);
//        $mover->click();
//        $this->saveResponse();
//        $mover->sendKeys($pass, $this->AccountFields['Pass'], $cps);
//
//        usleep(rand(100000, 500000));
//        $mover->moveToElement($submitButton);
//        $submitButton->click();

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->logger->debug("clicking submit");
        $this->saveResponse();

        try {
            $submitButton->click();
        } catch (\WebDriverCurlException | \UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 1);
        }
        //$this->driver->switchTo()->defaultContent();

        return true;
    }

    private function isLooksLikeNoWorkingError()
    {
        $this->logger->notice(__METHOD__);

        if ($errors = $this->http->FindNodes('//div[@id = "errorDialog"]//span/following-sibling::node()')) {
            $message = Html::cleanXMLValue(implode(' ', $errors));
            $this->logger->error("[Error]: {$message}");

            return $message;
        }

        return $this->http->FindSingleNode('//div[@id = "serviceErrorDialog" and contains(@class, "ie-visible")]//h1');
    }

    private function markActualProxyInvalid()
    {
        if ($this->actualProxyIp) {
            $this->logger->warning("marking proxy ip {$this->actualProxyIp} as invalid");
            $this->getProxyMemcached()->set(self::INVALID_IP_PREFIX . $this->actualProxyIp, "invalid", 300);
            unset($this->State["illuminati-ip"]);
        }
    }

    private function isExperimental(): bool
    {
        return true;
        //return in_array($this->AccountFields['UserID'] ?? null, [7, 2110]);
    }

    private function selectExperimentalSettings()
    {
        unset($this->State['User-Agent-2']);
        unset($this->State['Design']);
        unset($this->State['CodeSent']);
        unset($this->State['CodeSentDate']);

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        $this->setKeepProfile(false);
        $this->keepCookies(true);
        $this->usePacFile(false);

        if (in_array($this->AccountFields['UserID'] ?? null, [7, 2110])) {
            $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC_M1);
        }

        if (!isset($this->State['LuminatiZone'])) {
            $zones = ["static"/*, "static", "us_residential", "rotating_residential"*/];
            $zones = array_diff($zones, $this->State['ExcludedLuminatiZones'] ?? []);
            /*
            $this->State['LuminatiZone'] = $zones[array_rand($zones)];
            $this->logger->info("selected luminati zone: {$this->State['LuminatiZone']} from " . implode(", ", $zones));
            */
            $this->State['LuminatiZone'] = 'static';
            $this->logger->info("selected luminati zone: {$this->State['LuminatiZone']}");
        } else {
            $this->logger->info("luminati zone restored: {$this->State['LuminatiZone']}");
        }

        $this->setProxyBrightData(null, "shared_data_center", "us");
//        $this->setProxyBrightData(null, "static", [$this, "luminatiProxyFilter"]);
        $this->seleniumOptions->addHideSeleniumExtension = false;
//        $this->setProxyBrightData(null, $this->State['LuminatiZone'], [$this, "luminatiProxyFilter"]/*, true*/);

        return;

//        if (!isset($this->State['Fingerprint']) || $this->attempt > 0 || !stristr($this->State['UserAgent'], 'firefox')) {
//            $request = \AwardWallet\Common\Selenium\FingerprintRequest::firefox();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $this->logger->info("selected fingerprint {$fingerprint->getId()}, {{$fingerprint->getBrowserFamily()}}:{{$fingerprint->getBrowserVersion()}}, {{$fingerprint->getPlatform()}}, {$fingerprint->getUseragent()}");
//                $this->State['Fingerprint'] = $fingerprint->getFingerprint();
//                $this->State['UserAgent'] = $fingerprint->getUseragent();
//                $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
//            }
//        }

//        if (isset($this->State['Fingerprint'])) {
//            $this->logger->debug("set fingerprint");
//            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
//
//            if (isset($this->State["UserAgent"])) {
//                $this->http->setUserAgent($this->State['UserAgent']);
//            }
//
//            if (!empty($this->State["Resolution"])) {
//                $this->setScreenResolution($this->State["Resolution"]);
//            }
//
//            if (empty($this->State["Resolution"])) {
//                $resolutions = [
//                    [1440, 900],
//                    [1280, 720],
//                    [1280, 768],
//                    [1920, 1080],
//                ];
//                $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
//                $this->setScreenResolution($this->State["Resolution"]);
//            }
//        }// if (isset($this->State['Fingerprint']))

        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        /*
        $this->setProxyBrightData(null, "us_residential", "us", true);
        */
        /// http://host.docker.internal:63484/detect-selenium.php?browser=firefox&version=80
//        $this->setProxyBrightData(null, "static", "us", true);
//        $this->setProxyBrightData(null, "us_residential", "us", true);

        if (!isset($this->State['LuminatiZone'])) {
            $zones = ["static", "static", "us_residential", "rotating_residential"];
            $zones = array_diff($zones, $this->State['ExcludedLuminatiZones'] ?? []);
            /*
            $this->State['LuminatiZone'] = $zones[array_rand($zones)];
            $this->logger->info("selected luminati zone: {$this->State['LuminatiZone']} from " . implode(", ", $zones));
            */
            $this->State['LuminatiZone'] = 'static';
            $this->logger->info("selected luminati zone: {$this->State['LuminatiZone']}");
        } else {
            $this->logger->info("luminati zone restored: {$this->State['LuminatiZone']}");
        }

        $this->setProxyBrightData(null, $this->State['LuminatiZone'], [$this, "luminatiProxyFilter"]/*, true*/);

        if (
            isset($this->State['Fingerprint']) && !isset($this->State["UserAgent"])
        ) {
            $this->logger->info("find new fingerprint");
            $request = \AwardWallet\Common\Selenium\FingerprintRequest::firefox();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fp = $this->services->get(\AwardWallet\Common\Selenium\FingerprintFactory::class)->getOne([$request]);

            if ($fp !== null) {
                $this->logger->info("selected fingerprint {$fp->getId()}, {{$fp->getBrowserFamily()}}:{{$fp->getBrowserVersion()}}, {{$fp->getPlatform()}}, {$fp->getUseragent()}");
                $this->State['Fingerprint'] = $fp->getFingerprint();
                $this->State['UserAgent'] = $fp->getUseragent();
                $this->State['Resolution'] = [$fp->getScreenWidth(), $fp->getScreenHeight()];
            }
        }

        if (isset($this->State['Fingerprint'])) {
            $this->logger->debug("set fingerprint");
            $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
        }

        // TODO: need to reset UserAgent and Resolution (all State?) on errors

        if (
            !isset($this->State["UserAgent"])
            || (isset($this->State['SetNewUserAgent']) && $this->State['SetNewUserAgent'] === true)
        ) {
            $agents = [
                \HttpBrowser::FIREFOX_USER_AGENT,
            ];
            $this->State['UserAgent'] = $agents[array_rand($agents)];
        }
        $this->http->setUserAgent($this->State['UserAgent']);

        unset($this->State['SetNewUserAgent']);

        /*
        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*
        /*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Encoding" => "gzip, deflate, br",
            "User-Agent"      => $this->http->getDefaultHeader("User-Agent"),
        ];
        */

        if (!isset($this->State["Resolution"])) {
            $resolutions = [
                [1440, 900],
                //                [2560, 1440],
                [1280, 720],
                [1280, 768],
                [1920, 1080],
            ];
            $this->State["Resolution"] = $resolutions[array_rand($resolutions)];
        }
        $this->setScreenResolution($this->State["Resolution"]);
    }

    private function tuneExpirementalSettings()
    {
        // no maximize
    }

    private function selectBaseSettings()
    {
        unset($this->State['ChromiumVersion']);
        $this->useChromium();

        $this->usePacFile(false);

        $resolutions = [
            [800, 600], // not working with chrome 84
            [1152, 864],
            [1280, 720], // not working with chrome 84
            [1280, 768], // not working with chrome 84
            //            [1280, 800],
            //            [1360, 768],
            //            [1366, 768],
            [1440, 900],
            //            [1920, 1080],
            [2560, 1440],
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

        /*
        $userAgentKey = "User-Agent";
        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {

            $agents = [
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/82.0.4083.0 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/84.0.4147.135 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/85.0.4183.69 Safari/537.36',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_4) AppleWebKit/537.36 (KHTML like Gecko) Chrome/84.0.4139.0 Safari/537.36',
                \HttpBrowser::PROXY_USER_AGENT,
            ];
            $this->http->setUserAgent($agents[array_rand($agents)]);

            $agent = $this->http->getDefaultHeader("User-Agent");
            if (!empty($agent))
                $this->State[$userAgentKey] = $agent;
        }
        else
            $agent = $this->State[$userAgentKey];
        */

        $headers = [
            "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9",
            "Accept-Encoding" => "gzip, deflate, br",
            "User-Agent"      => $this->http->getDefaultHeader("User-Agent"),
        ];
        $proxyInfo = $this->setProxyGoProxies(null, 'us', null, null, 'https://www.chase.com', $headers);

        if ($proxyInfo === null) {
            throw new CheckRetryNeededException(3, 1);
        }
    }

    private function tuneBaseSettings()
    {
        $this->driver->manage()->window()->maximize();
    }

    private function callRetries()
    {
        $this->logger->notice(__METHOD__);
        unset($this->State["Proxy"]);
        unset($this->State["illuminati-session"]);
        unset($this->State["illuminati-ip"]);
        unset($this->State['LuminatiZone']);
        $this->markProxyAsInvalid();

        if ($this->http->FindSingleNode("//h2[contains(text(), 'System requirements')]")) {
            $this->State['SetNewUserAgent'] = true;
        }

        throw new CheckRetryNeededException(5, 0);
    }

    private function waitSignoutButton(int $seconds): bool
    {
        $this->logger->notice(__METHOD__);
        $result = $this->waitForElement(WebDriverBy::xpath('//button[@id = "convo-deck-sign-out"]| //span[contains(@class, "header-label") and contains(text(), "Accounts")] | //span[contains(@class, "header-label") and contains(text(), "Bank accounts")] | //div[@role="heading" and contains(text(), "Credit cards")]'), $seconds);

        if ($result !== null) {
            $this->sendStatistic();

            if (
                strstr($this->http->currentUrl(), 'intercept/addOrUpdateEmailAddress/update')
                && $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Please update your email address.')]"), 0)
            ) {
                $this->throwProfileUpdateMessageException();
            }
        }

        return $result !== null;
    }

    private function waitSomeUI()
    {
        $this->logger->notice(__METHOD__);
        $this->waitForElement(WebDriverBy::xpath('
            //button[@id = "convo-deck-sign-out"]
            | //h3[contains(text(), "We don\'t recognize the computer you\'re using.")]
            | //h1[contains(text(), "Confirm Your Identity")]
            | //h3[contains(text(), "Signing in on a new device?")]
            | //h2[contains(text(), "We don\'t recognize this device")]
            | //span[contains(text(), "I agree to the Digital Services Agreement")]
            | //div[@id = "content-logon-error"]
            | //div[@id = "inactiveAccountDialog"]
            | //div[@id = "serviceErrorDialog"]
            | //div[@id = "errorDialog"]
            | //h2[@id = "inner-alert-the-user"]/text()[last()]
            | //a[contains(@href, "LogOff")]
            | //a[contains(@href, "logoffbutton")]
            | //span[contains(@class, "header-label") and contains(text(), "Accounts")]
            | //span[contains(@class, "header-label") and contains(text(), "Bank accounts")]
            | //div[@role="heading" and contains(text(), "Credit cards")]
        '), 40);

        $this->saveResponse();
    }

    private function checkProxyIp()
    {
        $this->http->GetURL("http://lumtest.com/myip.json");
        $ipInfo = @json_decode(strip_tags($this->http->Response['body']), true);
        $this->actualProxyIp = $ipInfo['ip'] ?? null;
    }
}
