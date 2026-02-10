<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAarpSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const XPATH_OF_RESULT = "
        (
            //button[contains(text(), 'Get Started with AARP Rewards')]
            | //button[@id = 'REWARDS-GETREWARDS-SIGNUP-BTN-CLK-AUTH-TOP' and contains(text(), 'Sign up for free')]
            | //button[@id = 'REWARDS-GETREWARDS-SIGNUP-BTN-CLK-TOP' and contains(text(), 'Sign up for free')]
            | //div[not(contains(@class, 'aarp-u-hidden'))]/div/span[@class = 'rewards-c-status-bar__your-balance__point-balance-number']
        )[1]
    ";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->setProxyMount();

        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
//        $this->disableImages();

        $this->keepCookies(false);

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['cookies'])) {
            return false;
        }

        try {
            /*
                //$this->http->GetURL("https://www.aarp.org");
                $this->http->GetURL("https://secure.aarp.org/dsfsfsegseg");
                $this->driver->manage()->deleteAllCookies();
                foreach ($this->State['cookies'] as $cookie) {
                    $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");

                    // fixed domain
                    $cookie['domain'] = str_replace('.www.aarp.org', '.aarp.org', $cookie['domain']);
                    $cookie['domain'] = str_replace('www.aarp.org', '.aarp.org', $cookie['domain']);

                    $this->driver->manage()->addCookie(['name' => $cookie['name'], 'value' => $cookie['value'], 'domain' => $cookie['domain']]);
                }
            */

            $this->http->GetURL('https://secure.aarp.org/applications/acct/myAccount.action');

            if ($this->loginSuccessful()) {
                return true;
            }
        } catch (ScriptTimeoutException | TimeOutException | StaleElementReferenceException | UnexpectedJavascriptException | NoSuchWindowException | UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            try {
                $this->http->GetURL("https://secure.aarp.org/applications/user/login/memberAuthLogin?referrer=https%3A%2F%2Fsecure.aarp.org%2Faccount%2Fmyaarp");
//                $this->http->GetURL("http://xhaus.com/headers");
//                return false;
//                $this->http->GetURL("https://login.aarp.org/online-community/loginform.action");
//                $this->http->GetURL("https://secure.aarp.org/applications/user/login/memberAuthLogin?referrer=https%3A%2F%2Fsecure.aarp.org%2Faccount%2Fmyaarp");
//                $this->http->GetURL("https://secure.aarp.org/applications/user/login?intcmp=DSO-HDR-LOGIN&referrer=https%3A%2F%2Fwww.aarp.org%2F");
//                $this->http->GetURL("https://secure.aarp.org/applications/user/login?promo=AARPREWARDS&referrer=http%3A%2F%2Fwww.aarp.org%2Fdiscoverrewards");
//                $this->http->GetURL("https://www.aarp.org/");
//                $this->http->GetURL("https://secure.aarp.org/applications/acct/myAccount.action");
                /*
                $signIn = $this->waitForElement(WebDriverBy::xpath('//a[@data-formelementid = "login"]'), 10);
                $this->saveResponse();
                if (!$signIn) {
                    return $this->loginSuccessful();
                }
                $signIn->click();
                */
            } catch (UnexpectedAlertOpenException | UnexpectedJavascriptException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                try {
                    $error = $this->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $this->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                    $this->logger->error("LoadLoginForm -> exception: " . $e->getMessage());
                } finally {
                    $this->logger->debug("LoadLoginForm -> finally");
                }
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            }

            $this->saveResponse();

            if (
                $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"10; url=\/(distil_r_captcha.html([^\"]+))/")
                || $this->http->FindSingleNode("//h1[
                        contains(text(), 'Pardon Our Interruption...')
                        or contains(text(), 'Unable To Identify Your Browser')
                    ]")
            ) {
                throw new CheckRetryNeededException(2, 1);
            }

            $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-login' or @id = 'btn-mem-login']"), 10);
            $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 0);
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $this->saveResponse();

            if (empty($login) || empty($pass) || empty($submitButton)) {
                $this->logger->error('something went wrong');

                return $this->checkErrors();
            }
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->clear();
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->logger->debug("Input captcha");
                $captchaInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'nucaptcha-answer']"), 10);

                if ($captchaInput) {
                    $captchaInput->sendKeys($captcha);
                } else {
                    $this->logger->error("captcha input not found");
                }
                $this->saveResponse();
            }
            $this->logger->debug("Posting form");
            $submitButton->click();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            // retries
            if (
                strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'Timeout loading page')
            ) {
                throw new CheckRetryNeededException(3, 1);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//img[contains(@alt, "Site Under Maintenance") and @data-image]/@alt')) {
            throw new CheckException('We are currently performing maintenance on our website. Please check back later.');
        }

        if (!empty($this->http->FindNodes('//img[@alt = "Pagenotavailable"]'))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//iframe[@id="main-iframe" and starts-with(@src, "/_Incapsula_Resource")]')) {
            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $img = $this->waitForElement(WebDriverBy::xpath("//img[@id = 'nucaptcha-media'] | //canvas[contains(@aria-label, 'Captcha Letters')]"), 0);

        if (!$img) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        /*
        $this->logger->debug("Captcha URL -> " . $img->getAttribute('src'));

        return $this->recognizeCaptchaByURL($this->recognizer, $img->getAttribute('src'), "gif");
        */
        $pathToScreenshot = $this->takeScreenshotOfElement($img);
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // Invalid credentials
        if ($message = $this->waitForElement(WebDriverBy::xpath("
            //div[not(contains(@class, 'hidden')) and (
                contains(text(), 'Please enter a valid email address and password')
                or contains(text(), 'Please re-enter your email address and/or password')
                or contains(text(), 'Your registered account information was not recognized.')
                or contains(text(), 'The email address you entered is invalid. Please make sure you entered your correct email address.')
                or contains(text(), 'The email address that you provided, was not associated with a registered account.')
                or contains(text(), 'Sorry, security challenge letters did not match.')
                or contains(text(), 'You must change your password before you log in to AARP')
            )]
            | //h1[contains(text(), 'Update Verified Online Account')]
            | //div[not(contains(@class, 'hidden')) and span[contains(text(), 'You last logged in using passwordless login. Please continue to')]]
            "), 0)
        ) {
            $message = $message->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Sorry, security challenge letters did not match.')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                !in_array(strtolower($this->AccountFields['Login']), [
                    "jonathangraber@bnin.net", // AccountID: 4495492
                    "katrina.heaven@hotmail.com", // AccountID: 4249057
                    "djmikeyt@hotmail.com", // AccountID: 4663670
                    "lindat0307@gmail.com", // AccountID: 2582448
                    "rrenf001@gmail.com", // AccountID: 5383642
                ])
                && strstr($message, 'The email address that you provided, was not associated with a registered account. ')
                && $this->attempt == 0
            ) {
                throw new CheckRetryNeededException(2, 2);
            }

            if (strstr($message, 'Update Verified Online Account')) {
                $this->throwProfileUpdateMessageException();
            }

            if (strstr($message, 'You last logged in using passwordless login. Please continue to log in without a password or reset your password')) {
                throw new CheckException("You last logged in using passwordless login. Please continue to log in without a password or reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("
            //div[@id = 'genericError' and @class = 'serverError' and (
                contains(text(), 'We are currently experiencing technical difficulties and are unable to complete your request.')
            )]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Enter the email address you used at registration to reset your password.
        if ($this->http->FindSingleNode("//h1[normalize-space(text())='Password Assistance'] | //h1[span[normalize-space(text())='Password Assistance']]")) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);

        try {
            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(2);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("Login UnexpectedAlertOpenException exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->error("Login UnexpectedAlertOpenException -> NoAlertOpenException exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("Login UnexpectedAlertOpenException -> finally");
                // it's worked
                if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'do so manually')]"), 0)) {
                    $this->http->GetURL('https://secure.aarp.org/applications/acct/myAccount.action');
                }// if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'do so manually')]", 0)))
            }
        } catch (NoAlertOpenException $e) {
            $this->logger->error("Login NoAlertOpenException exception: " . $e->getMessage());
            $this->logger->debug("no alert, skip");
        }

        $this->checkProviderErrors();

        $this->saveResponse();
        // captcha
        $wrongCaptchaXpath = "//div[not(contains(@class, 'hidden')) and (
            contains(text(), 'Sorry, security code did not match.')
            or contains(text(), 'Sorry, security challenge letters did not match.')
        )]";

        if ($this->http->FindSingleNode("//div[contains(text(), 'Please re-enter your password along with the Security Challenge.')] | {$wrongCaptchaXpath}")) {
            if ($this->http->FindSingleNode($wrongCaptchaXpath) && $this->recognizer) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
            }

            $submitButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-login' or @id = 'btn-mem-login']"), 10);
            $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 0);
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $this->saveResponse();

            if (empty($login) || empty($pass) || empty($submitButton)) {
                $this->logger->error('something went wrong');

                return $this->checkErrors();
            }
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->clear();
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->logger->debug("Input captcha");
                $captchaInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'nucaptcha-answer']"), 10);

                if ($captchaInput) {
                    $captchaInput->sendKeys($captcha);
                } else {
                    $this->logger->error("captcha input not found");
                }
                $this->saveResponse();
            }
            $this->logger->debug("Posting form");
            $submitButton->click();
        }

        try {
            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }

            $this->checkProviderErrors();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            sleep(2);
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("Login UnexpectedAlertOpenException exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->error("Login UnexpectedAlertOpenException -> NoAlertOpenException exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("Login UnexpectedAlertOpenException -> finally");

                if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'do so manually')]"), 0)) {
                    $this->http->GetURL('https://secure.aarp.org/applications/acct/myAccount.action');

                    if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Unable To Identify Your Browser')]"), 3)) {
                        $this->markProxyAsInvalid();

                        throw new CheckRetryNeededException();
                    }
                }// if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'do so manually')]", 0)))
            }
        } catch (NoAlertOpenException $e) {
            $this->logger->error("Login NoAlertOpenException exception: " . $e->getMessage());
            $this->logger->debug("no alert, skip");
        } finally {
            $this->logger->debug("finally");

            try {
                $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Processing')] | //h3[contains(text(), 'Loading...')]"), 0);

                if ($this->loginSuccessful()) {
                    return true;
                }
                $this->logger->notice("fail");

                if ($this->http->FindSingleNode("//div[contains(text(), 'Processing')] | //h3[contains(text(), 'Loading...')]")) {
                    // todo: debug
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();
                    /*
                    // todo: AccountID: 5212072, 3095268, 2617087, 5195563
                    if (in_array($this->AccountFields['Login'], ['kim@tierra.ws', 'StevenHamilton99@gmail.com', 'john.a.viloria@gmail.com', 'sagy@sagy.net'])) {
                    */
                    try {
                        $this->http->GetURL("https://secure.aarp.org/account/myaarp");
                        $this->http->GetURL("https://www.aarp.org/rewards/?intcmp=GLOBAL-HDR-LNK-CLK-AARP_REWARDS");
                    } catch (UnexpectedJavascriptException $e) {
                        $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
                    } catch (NoSuchWindowException $e) {
                        $this->logger->error("NoSuchWindowException exception: " . $e->getMessage());

                        throw new CheckRetryNeededException(3, 1);
                    } catch (TimeOutException $e) {
                        $this->logger->error("TimeOutException exception: " . $e->getMessage());
                        $this->driver->executeScript('window.stop();');
                        $this->saveResponse();
                    }

                    try {
                        $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 10);
                    } catch (StaleElementReferenceException $e) {
                        $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                        $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 10);
                    }
                    $this->saveResponse();
                    $balance = $this->http->FindSingleNode("//div[@class = 'rewardsStatusBar']//span[@class = 'rewards-c-status-bar__your-balance__point-balance-number']");

                    if (!$this->SetBalance($balance)) {
                        $notMember = $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 0);
                        $this->saveResponse();

                        if ($notMember) {
                            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                        }
                    }

                    return true;
                }
            } catch (NoAlertOpenException $e) {
                $this->logger->error("finally NoAlertOpenException exception: " . $e->getMessage());
                $this->logger->debug("no alert, skip");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 1);
            }
        }

        // AccountID: 3070553, 3368931
        if ($this->waitForElement(WebDriverBy::xpath('//img[@title="site not available image"]'), 0)) {
            throw new CheckException("You've come to the right place, but this page currently unavailable.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        try {
            $this->saveResponse();
            $this->State['cookies'] = $cookies = $this->driver->manage()->getCookies();
        } catch (Exception | SessionNotCreatedException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        $dr = null;

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'dr') {
                $dr = urldecode($cookie['value']);

                break;
            }
        }
        // Balance - Your Points
        $this->logger->debug("Cookie: $dr");

        if (!empty($dr) && ($balance = $this->http->FindPreg("/bal\=([^\&\=]+)/ims", false, $dr))) {
            $this->SetBalance($balance);
        } elseif (($balance =
            $this->http->FindSingleNode('//div[@class="aarpe-page-container"]//span[contains(@class, "points") and not(contains(@class, "-"))]', null, true, "/([\d\,\.\-\s]+)\s+p/ims")
            ?? $this->http->FindSingleNode('//div[@class = "cmp-myaccount__tiles"]/div/h4[contains(text(), "AARP Rewards:")]', null, true, '/AARP Rewards: ([\d\,]+) points/')) !== null
        ) {
            $this->SetBalance($balance);
        } elseif ($this->http->FindPreg("/errorCode=999\&errorMessage=We\'re sorry, there was an error processing your request./ims", false, $dr)) {
            $this->SetBalanceNA();
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Whoops! We've run into a small error on the page. Please wait one moment and try again.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve run into a small error on the page.")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // hard code
            if ($this->AccountFields['Login'] == 'augi001@gmail.com') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (in_array($this->AccountFields['Login'], [
                'bnason@live.com',
                'valleypr@yahoo.com',
                'michaelgreid77@gmail.com',
                'nvkris123@gmail.com',
                'brian@statuscapital.co.za',
            ])
            ) {
                $this->SetBalanceNA();
            }
        }// elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // Member Since
        $at = null;

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'at') {
                $at = urldecode($cookie['value']);

                break;
            }
        }
        $this->logger->debug("Cookie: $at");
        $memberSince = $this->http->FindPreg("/\&mj=([^\&\=]+)/ims", false, $at);
        $this->SetProperty("MemberSince", $memberSince !== 'null' ? $memberSince : null);
        // Membership Expires
        $membershipExpires = $this->http->FindPreg("/\&me=([^\&\=]+)/ims", false, $at);
        $this->SetProperty("MembershipExpires", $membershipExpires !== 'null' ? $membershipExpires : null);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\&f=([^\&\=]+)/ims", false, $at)));
        // Name
        $name = $this->http->FindSingleNode("//div[@id = 'applet-wallet-print-card-membershipCardPrint']//div[@class = 'memberName']");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Membership Number
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//div[@id = 'applet-wallet-print-card-membershipCardPrint']//div[@class = 'membershipNumber']"));

        // new program is coming -> https://campaigns.aarp.org/RewardsforGood/?_ga=2.63403304.621803266.1569387979-1161490579.1569387979
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name'])
                && $this->http->FindSingleNode('//div[@class = "cmp-myaccount__tiles" and div[contains(@class, "myaccount-tile-rewards")]]/div/h4')
            ) {
                $points = $this->http->FindSingleNode('//div[@class = "cmp-myaccount__tiles" and div[contains(@class, "myaccount-tile-rewards")]]/div/h4');
                $this->logger->debug("Balance: {$points}");

                if (
                    $points === 'AARP Rewards'
                    && $this->waitForElement(WebDriverBy::xpath("//a[@data-formelementid = 'AM-NAV-MYAARP-GET-BFT']"), 0)
                ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            if (
//                ($this->http->FindSingleNode("//div[@class = 'memberName']") || $this->AccountFields["Login"] == 'pdcareypmp@gmail.com') &&
//                !empty($this->Properties['MemberID']) &&
                !empty($this->Properties['Name'])
                && !empty($this->Properties['MemberSince'])
                && !empty($this->Properties['MembershipExpires'])
            ) {
                try {
                    $this->http->GetURL("https://www.aarp.org/rewards/?intcmp=GLOBAL-HDR-LNK-CLK-AARP_REWARDS");
                    sleep(3);

                    try {
                        $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT . " | //h1[contains(text(), \"ERROR with user state NNP\")]"), 10);
                    } catch (StaleElementReferenceException $e) {
                        $this->logger->error("exception: " . $e->getMessage());
                    }

                    // it helps
                    if ($this->http->FindSingleNode('//h1[contains(text(), "ERROR with user state NNP")]')) {
                        $this->http->GetURL("https://secure.aarp.org/account/myaarp");
                        $this->http->GetURL("https://www.aarp.org/rewards/?intcmp=GLOBAL-HDR-LNK-CLK-AARP_REWARDS");
                        $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 10);
                    }
                } catch (StaleElementReferenceException | UnknownServerException $e) {
                    $this->logger->error("exception: " . $e->getMessage());
                    sleep(7);
                    $this->waitForElement(WebDriverBy::xpath(self::XPATH_OF_RESULT), 10);
                } catch (WebDriverException | NoSuchWindowException | SessionNotCreatedException $e) {
                    $this->logger->error("exception: " . $e->getMessage());

                    if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                        throw new CheckRetryNeededException(2, 0);
                    }
                }
                $this->saveResponse();
                // Balance - YOUR BALANCE
                $balance = $this->http->FindSingleNode("//div[@class = 'rewardsStatusBar']//span[contains(@class, 'rewards-c-status-bar__your-balance__point-balance-number')]");

                if (!$this->SetBalance($balance)) {
                    sleep(1); // prevent StaleElementReferenceException
                    $notMember = $this->waitForElement(WebDriverBy::xpath("
                        //button[contains(text(), 'Get Started with AARP Rewards')]
                        | //button[@id = 'REWARDS-GETREWARDS-SIGNUP-BTN-CLK-ANON-TOP' and contains(text(), 'Sign up for free')]
                        | //button[@id = 'REWARDS-GETREWARDS-SIGNUP-BTN-CLK-TOP' and contains(text(), 'Sign up for free')]
                        | //h1[contains(text(), 'Get ahead of whatever life has ahead')]
                        | //button[@data-formelementid = 'USER-VERIFY-GETSTARTED-' and contains(text(), 'Get Started')]
                    "), 0);
                    $this->saveResponse();

                    if ($notMember) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    // hard code, selenium can't open profile for these 2 accounts
                    if (
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        /*in_array($this->AccountFields['Login'], [
                        "pmcarrion@gmail.com",
                        "alinambacker@gmail.com",
                        "michael@cameta.com",
                        ])*/ && $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Please enter the phone number you would like to use to verify your identity. We will never share or sell your number, only use it to make sure it is you.')]"), 0)
                    ) {
                        $this->throwProfileUpdateMessageException();
                    }

                    if (
                        $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                        && ($message = $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'AARP Rewards is currently undergoing scheduled maintenance')]"), 0))
                    ) {
                        throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                    }
                }

                // TOTAL SAVINGS
                $this->SetProperty("TotalSaving", $this->http->FindSingleNode("//div[@class = 'rewardsStatusBar']//span[contains(@class, 'rewards-c-status-bar__total-savings__savings-dollar-sign')]") . $this->http->FindSingleNode("//div[@class = 'rewardsStatusBar']//span[contains(@class, 'rewards-c-status-bar__total-savings__savings-amount')]"));

                if (isset($this->Properties['TotalSaving']) && $this->Properties['TotalSaving'] == '$undefined') {
                    unset($this->Properties['TotalSaving']);
                    // AccountID: 2811429
                    if ($this->http->FindSingleNode("//div[@class = 'rewardsStatusBar']//span[contains(@class, 'rewards-c-status-bar__your-balance__point-balance-number')]") == '-') {
                        $this->SetBalanceNA();
                    }
                }// if (isset($this->Properties['TotalSaving']) && $this->Properties['TotalSaving'] == '$undefined')
            }
        }

        /*
        if (!empty($this->Properties['MemberID'])) {
            return;
        }

        try {
            $this->http->GetURL('https://secure.aarp.org/applications/account/myAccount.action?intcmp=DSO-HDR-MYACCT-EWHERE');
        } catch (UnexpectedJavascriptException | UnknownServerException | NoSuchWindowException | SessionNotCreatedException | NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && strstr($e->getMessage(), 'Tried to run command without establishing a connection Build info: version')
            ) {
                throw new CheckRetryNeededException(2, 0);
            }
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        // Membership Number
        try {
            sleep(1);
            $memberId = $this->waitForElement(WebDriverBy::xpath("(//label[contains(text(), 'Membership Number')]/following-sibling::label[1] | //label[contains(text(), 'Membership Number')]/following-sibling::span[1] | //div[@class = 'membershipNumber'])[1]"), 10);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $memberId = $this->waitForElement(WebDriverBy::xpath("(//label[contains(text(), 'Membership Number')]/following-sibling::label[1] | //label[contains(text(), 'Membership Number')]/following-sibling::span[1] | //div[@class = 'membershipNumber'])[1]"), 10);
        }
        $this->saveResponse();

        if (!empty($memberId)) {
            $this->SetProperty("MemberID", $memberId->getText());
        }
        */
    }

    /*
    function IsLoggedIn()
    {
        $this->http->GetURL('https://secure.aarp.org/applications/acct/myAccount.action');
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }
    */

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // look for logout link
        $logout =
            $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 5, false)
            ?? $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Hello ')]"), 0)
        ;

        try {
            $this->saveResponse();
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
            sleep(5);
            $this->saveResponse();
        } catch (NoSuchWindowException $e) {
            $this->logger->error("Exception on saveResponse: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        if ($logout && !$this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Browser Not Supported')]"), 0)) {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            // todo: debug
            if (strstr($this->http->currentUrl(), 'https://secure.aarp.org/applications/user/auth?response_type=code&client_id=')) {
                sleep(5);
                $this->saveResponse();
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            }

            if (
                $this->http->currentUrl() == 'https://www.aarp.org/'
                || strstr($this->http->currentUrl(), 'https://secure.aarp.org/applications/user/auth?response_type=code&client_id=')
            ) {
                try {
                    $this->http->GetURL("https://secure.aarp.org/applications/acct/myAccount.action");
                } catch (UnexpectedAlertOpenException $e) {
                    $this->logger->error("exception: " . $e->getMessage());
                    $error = $this->driver->switchTo()->alert()->getText();
                    $this->logger->debug("alert -> {$error}");
                    $this->driver->switchTo()->alert()->accept();
                    $this->logger->debug("alert, accept");
                } catch (NoAlertOpenException $e) {
                    $this->logger->debug("no alert, skip");
                } catch (TimeOutException $e) {
                    $this->logger->error("TimeOutException exception: " . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
                    $this->saveResponse();
                }
            }

            try {
                $res = $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Membership Number:')] | //button[@id = 'btn-login'] | //a[contains(text(), 'Not Now')]"), 10);
                $this->saveResponse();

                if ($res && in_array($res->getText(), ['Not Now', 'Log In'])) {
                    $res->click();

                    return false;
                }

                if (!$res && $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Loading...')]"), 0)) {
                    $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Membership Number:')] | //button[@id = 'btn-login']"), 10);
                    $this->saveResponse();
                }
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            } finally {
                $this->logger->debug("loginSuccessful finally");

                if (empty($res)) {
                    $this->waitForElement(WebDriverBy::xpath("//label[contains(text(), 'Membership Number:')] | //button[@id = 'btn-login']"), 10);
                    $this->saveResponse();
                }
            }
            // Renew your expired membership
            if ($msg = $this->http->FindSingleNode('//div[contains(@class, "nonMembershipHeader") and contains(text(), "Renew your expired membership")] | //div[@class = "cmp-myaccount__user-card-info"]/h3[
                    contains(text(), "Renew your expired membership")
                    or contains(text(), "Reactivate your cancelled mem")
                ]')
            ) {
                throw new CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//div[contains(@class, "nonMembershipHeader visible-xs") and (
                        contains(text(), "Reactivate your cancelled membership")
                        or contains(text(), "You are not an AARP member")
                    )]
                    | //a[contains(text(), "BECOME A MEMBER TODAY")]
                ')
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (empty($res) && $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-login']"), 0)) {
                throw new CheckRetryNeededException(2, 0);
            }

            return true;
        }

        return false;
    }
}
