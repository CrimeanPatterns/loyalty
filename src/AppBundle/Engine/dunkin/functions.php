<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerDunkin extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var HttpBrowser
     */
    public $browser = null;
    private $login = 0;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "dunkinCard"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
//        $proxy = $this->http->getLiveProxy("https://www.dunkindonuts.com/content/dunkindonuts/en/signin.html");
//        $this->http->SetProxy($proxy);
        $this->http->SetProxy($this->proxyReCaptcha());

        // selenium settings
        $this->UseSelenium();
        $this->useChromePuppeteer();
//        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;

        if ($this->attempt > 1) {
            $this->keepCookies(false);
        }
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.dunkindonuts.com/en/sign-in");

            $this->parseWithCurl();
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | Facebook\WebDriver\Exception\NoSuchAlertException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        $headers = [
            "Accept"           => "*/*",
            "CSRF-Token"       => "undefined",
            "Content-Type"     => "application/x-www-form-urlencoded",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => "https://www.dunkindonuts.com/bin/servlet/signin",
        ];
//        $this->gerCSRF();
        try {
            $this->browser->RetryCount = 0;
            $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/profile", 'service=getProfileInfo', $headers, 20);
            $this->browser->RetryCount = 2;
            $response = $this->browser->JsonLog(null, 3, true);
            $data = ArrayVal($response, 'data');

            if (ArrayVal($data, 'email', null)) {
                return true;
            }
        } catch (ErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = [];

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
//        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($this->http->currentUrl());
    }

    /*
        function uniqueStateKeys() {
            $this->logger->notice(__METHOD__);
            $selenium = clone $this;
            $xKeys = [];
            $retry = false;
            $this->http->brotherBrowser($selenium->http);
            try {
                $this->logger->notice("Running Selenium...");
                $selenium->UseSelenium();
                $selenium->useGoogleChrome();
                $selenium->disableImages();
                $selenium->useCache();
                $selenium->keepCookies(false);
                $selenium->http->start();
                $selenium->Start();
                $selenium->http->GetURL("https://www.dunkindonuts.com/en/sign-in");

                $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 10, false);
                $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0, false);
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                if (!$loginInput || !$passwordInput)
                    return $this->checkErrors();
                $selenium->driver->executeScript("
                        $('div.input-wrapper').removeClass('input-wrapper');
                    ");
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passwordInput->sendKeys($this->AccountFields['Pass']);
                // Sign In
    //            $selenium->driver->executeScript("
    //                $('input[value = \"Sign In\"]').click();window.stop();
    //            ");

                $captcha = $this->parseCaptcha();
                if ($captcha === false)
                    return false;
                $sessionId = $this->http->FindSingleNode("//input[@id = 'sessionId']/@value");

                $script = base64_encode("APP.global.postFormData(\"/bin/servlet/signin\", \"service=signin&email=".urlencode($this->AccountFields['Login'])."&password=".urlencode($this->AccountFields['Pass'])."&g-recaptcha-response=".urlencode($captcha)."&currentPageURL=%2Fcontent%2Fdd%2Fen%2Fsign-in&sessionId={$sessionId}\", function(o){
    APP.session.initialize();
    localStorage.setItem('response', JSON.stringify(o));
    APP.recaptcha.validRecaptcha();

    }, function(o,p){
    localStorage.setItem('response', JSON.stringify(o));
    APP.global.getErrorMsg(o,$('form[action *= \"signin\"]'));
    APP.recaptcha.renderRecaptcha();
    })");

    //            window.location.href="/";

    //            $this->logger->notice($script);
    //            $this->logger->debug("atob('$script')");
                $selenium->driver->executeScript("eval(atob('$script'))");

                /*
                // uniqueStateKey
                if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[contains(@action, "/bin/servlet/signin")]//input[contains(@name, "X-") and not(contains(@name, "uniqueStateKey"))]'), 5, false)) {
                    foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[contains(@action, "/bin/servlet/signin")]//input[contains(@name, "X-")]', 0, false)) as $index => $xKey) {
                        $xKeys[] = [
                            'name'  => $xKey->getAttribute("name"),
                            'value' => $xKey->getAttribute("value")
                        ];
                    }
                    $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
                }
                * /

                $selenium->waitForElement(WebDriverBy::xpath('
                    //a[contains(text(), "Sign Out")]
                    | //a[contains(text(), "SIGN OUT")]
                    | //div[contains(@class, "u-page-error")]
                    | //h1[contains(text(), "Help Us Keep Your Account Secure")]
                '), 5);

                $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
                $this->logger->info("[Form response]: " . $response);

                if ($this->http->FindPreg("/\{\"data\":\{\"isMFARequried\":true,\"isLoyaltyEnrollment\":[^,]+,\"successMessage\":true\}\}/", false, $response)) {
                    $selenium->http->GetURL("https://www.dunkindonuts.com/content/dd/en/mfa.html");
                }

                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $cookies = $selenium->driver->manage()->getCookies();
                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
                }

                $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            }
            catch (ScriptTimeoutException $e) {
                $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                $this->DebugInfo = "ScriptTimeoutException";
                // retries
                if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                    || strstr($e->getMessage(), 'timeout '))
                    $retry = true;
            }// catch (ScriptTimeoutException $e)
            catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $this->DebugInfo = "TimeOutException";
                // retries
                if (strstr($e->getMessage(), 'timeout'))
                    $retry = true;
            }// catch (ScriptTimeoutException $e)
            catch (StaleElementReferenceException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                // retries
                $retry = true;
            }// catch (ScriptTimeoutException $e)
            catch (UnexpectedAlertOpenException $e) {
                $this->logger->error("UnexpectedAlertOpenException exception: " . $e->getMessage());
                $this->logger->debug($selenium->driver->switchTo()->alert()->getText());
                // captcha problems workaround
                if (strstr($selenium->driver->switchTo()->alert()->getText(), 'Cannot contact reCAPTCHA'))
                    $retry = true;
                $selenium->driver->switchTo()->alert()->accept();
                $this->logger->notice("alert, accept");
            }
            finally {
                // close Selenium browser
                $selenium->http->cleanup();
            }
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
                throw new CheckRetryNeededException(4, 1);

            return $xKeys;
        }
        */

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
            $this->http->GetURL("https://www.dunkindonuts.com/en/sign-in");

            $this->waitForElement(WebDriverBy::xpath('//body[@class = "body--ready"]'), 10);
            $loginInput = $this->waitForElement(WebDriverBy::id('email'), 10, false);
        } catch (NoSuchDriverException | Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException  $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }

            $loginInput = $this->waitForElement(WebDriverBy::id('email'), 5, false);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception on saveResponse: " . $e->getMessage());
            sleep(5);
            $loginInput = $this->waitForElement(WebDriverBy::id('email'), 5, false);
            $this->saveResponse();
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("UnknownErrorException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        $passwordInput = $this->waitForElement(WebDriverBy::id('password'), 0, false);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            if ($this->http->FindSingleNode('
                //h1[contains(text(), "Access Denied")]
                | //*[self::h1 or self::span][contains(text(), \'This site can’t be reached\')]
            ')
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        try {
            $this->driver->executeScript("
                    try {
                        $('div.input-wrapper').removeClass('input-wrapper');
                    } catch (e) {}
                ");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Facebook\WebDriver\Exception\WebDriverCurlException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage());
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        try {
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
        } catch (Facebook\WebDriver\Exception\ElementNotInteractableException $e) {
            $this->logger->error("ElementNotInteractableException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        try {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $sessionId = $this->http->FindSingleNode("//input[@id = 'sessionId']/@value");
            $query = http_build_query([
                'service'              => 'signin',
                'email'                => $this->AccountFields['Login'],
                'password'             => $this->AccountFields['Pass'],
                'g-recaptcha-response' => $captcha,
                'currentPageURL'       => '/content/dd/en/sign-in',
                'sessionId'            => $sessionId,
            ]);

            $this->driver->executeScript("APP.global.postFormData('/bin/servlet/signin', '$query', APP.formLogin.loginFormSuccess, APP.formLogin.loginFormFailure);");
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage());

            if (strstr($e->getMessage(), 'javascript error: APP is not defined')) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Sign Out")]
            | //a[contains(text(), "SIGN OUT")]
            | //div[contains(@class, "u-page-error")]
            | //ul[contains(@class, "parsley-errors-list")]/li
            | //h1[contains(text(), "Help Us Keep Your Account Secure")]
        '), 5);
        $this->saveResponse();

        /*
        $this->http->GetURL("https://www.dunkindonuts.com/en/sign-in");
        if (!$this->http->FindSingleNode("//form[contains(@action, 'signin')]/@action"))
            return $this->checkErrors();

        $xKeys = $this->uniqueStateKeys();
        /*
        if (empty($xKeys))
            return false;
        */

        return true;

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'signin')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

//        foreach ($xKeys as $xKey)
//            if (isset($xKey['name'], $xKey['value']))
//                $this->http->SetInputValue($xKey['name'], $xKey['value']);

        return true;
    }

    public function Login()
    {
        try {
            $response = $this->driver->executeScript("return localStorage.getItem('response');");
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 5);
        }

        $this->logger->info("[Form response]: " . $response);

        if (
            $this->http->FindPreg("/\{\"data\":\{\"isMFARequried\":true,\"isLoyaltyEnrollment\":[^,]+,\"successMessage\":true\}\}/", false, $response)
            && !$this->http->FindSingleNode('//ul[contains(@class, "parsley-errors-list")]/li')
        ) {
            try {
                $this->http->GetURL("https://www.dunkindonuts.com/content/dd/en/mfa.html");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }
        }

        $this->saveResponse();

        $this->http->RetryCount = 0;
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        //		if (!$this->http->PostForm() && !in_array($this->http->Response['code'], [401, 400]))
//            return $this->checkErrors();
        $response = $this->http->JsonLog($response);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->logger->debug("Name {$cookie['name']} / Value: " . $cookie['value']);

            if ($cookie['name'] == 'user_info') {
                $this->logger->debug("Value: " . $cookie['value']);

                return true;
            }

            if ($cookie['name'] == 'user_token' && empty($response->error->message)) {
                $this->logger->debug("Value: " . $cookie['value']);

                try {
                    $this->http->GetURL("https://www.dunkindonuts.com/en/account/perks-rewards");
                } catch (UnexpectedAlertOpenException | Facebook\WebDriver\Exception\UnexpectedAlertOpenException  $e) {
                    $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage(), ['HtmlEncode' => true]);

                    try {
                        $error = $this->driver->switchTo()->alert()->getText();
                        $this->logger->debug("alert -> {$error}");
                        $this->driver->switchTo()->alert()->accept();
                        $this->logger->debug("alert, accept");
                    } catch (
                        NoAlertOpenException
                        | Facebook\WebDriver\Exception\NoAlertOpenException
                        | Facebook\WebDriver\Exception\NoSuchAlertException
                        $e
                    ) {
                        $this->logger->debug("no alert, skip");
                    }
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                }
                $this->waitForElement(WebDriverBy::xpath('
                    //a[contains(text(), "Sign Out")]
                    | //a[contains(text(), "SIGN OUT")]
                    | //div[contains(@class, "u-page-error")]
                    | //h1[contains(text(), "Help Us Keep Your Account Secure")]
                '), 5);
                $this->saveResponse();

                return true;
            }// if ($cookie['name'] == 'user_token' && empty($response->error->message))
        }

        //		if (isset($response->data) && ($response->data === true || $response->data === false))//not member: data == false}
        if ($this->http->getCookieByName("user_info", "www.dunkindonuts.com")) {
            $this->logger->debug("user_info: " . $this->http->getCookieByName("user_info", "www.dunkindonuts.com"));
//            return true;
        }
        // invalid credentials
        $message = $response->error->message
            ?? $this->http->FindSingleNode('
                //div[contains(@class, "u-page-error")]
            ')
            ?? $this->http->FindSingleNode('
                //ul[contains(@class, "parsley-errors-list")]/li
            ')
            ?? null
        ;

        if ($message) {
            $this->logger->error($message);

            if (
                // Sorry, the information you supplied does not match our records.
                strstr($message, 'Sorry, the information you supplied does not match our records.')
                // Sorry, information you supplied does not match our records.
                || strstr($message, 'Sorry, information you supplied does not match our records.')
                // The password you entered does not match the following requirements: 8+ characters with at least one number, special character, uppercase and lowercase letter.
                || strstr($message, 'The password you entered does not match the following requirements:')
                // Make sure your email is entered in the coffee@dunkindonuts.com format.
                || strstr($message, 'Make sure your email is entered in the')
                || strstr($message, 'Invalid password symbols!')
                || strstr($message, 'Sign In - For your security, please reset your Dunkin\' password by selecting forgot password.')
                || strstr($message, 'Profile ID does not exist.')
                || strstr($message, 'This value should be a valid email.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Account temporarily locked because of too many failed login attempts. Please confirm your username /password and try again later
            if (
                strstr($message, 'Account temporarily locked because of too many failed login attempts')
                || strstr($message, 'Account is temporarily locked because of too many failed login attempts.')
            ) {
                throw new CheckException("Account is temporarily locked because of too many failed login attempts. Please confirm your username and password and try again later.", ACCOUNT_LOCKOUT);
            }
            // The length of  field should not be greater than 50 symbols.
            if (strstr($message, 'The length of  field should not be greater than 50 symbols.')) {
                throw new CheckException("Sorry, the information you supplied does not match our records.", ACCOUNT_INVALID_PASSWORD);
            }
            // not a member
            if (strstr($message, 'The Profile is not enrolled for Loyalty Program.')) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // Sorry, we are unable to process your log in at this time. Please try again later.
            if (strstr($message, 'Sorry, we are unable to process your log in at this time. Please try again later.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'APP5041') {
                throw new CheckException("Sorry, we are unable to process your log in at this time. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }
            // System Error
            if (strstr($message, 'System Error.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'Profile information is invalid')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * retries
             *
             * {"error":{"code":"APP100","message":"Bad Request."}}
             */
            if (strstr($message, 'Bad Request')) {
                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }
        }// if ($message)

        // 502 Bad Gateway
        if (isset($response->responseText)
            && (
                strstr($response->responseText, '<center><h1>502 Bad Gateway</h1></center>')
                || strstr($response->responseText, '<H1>Access Denied</H1>')
                || strstr($response->responseText, '<h1>Error while processing /bin/servlet/signin</h1>')
            )
        ) {
            throw new CheckRetryNeededException(3, 1, self::PROVIDER_ERROR_MSG);
        }

        // Sorry, we are currently experiencing technical difficulty and are unable to process your request.
        if ((strstr($message, 'Sorry, we are currently experiencing technical difficulty and are unable to process your request.'))
            || $this->http->FindPreg('/\{"error":\{\}\}/')) {
            /*
            for (; $this->login < 2;) {
                $this->logger->notice("Retry: {$this->login}");
                $this->http->Form = $form;
                $this->http->FormURL = $formURL;
                $this->login++;
                return $this->Login();
            }
            */
            throw new CheckRetryNeededException(2, 10, $message);
        }

        // Help Us Keep Your Account Secure
        $sendCode = $this->waitForElement(WebDriverBy::xpath('//input[@value = "SEND CODE"]'), 5);
        $this->saveResponse();

        if ($sendCode) {
            // Attach a mobile number to your account to keep your information protected. Enter your mobile number and we will send a text to verify your identity.
            if ($this->http->FindSingleNode("//*[self::p or self::div][
                    contains(text(), 'Attach a mobile number to your account to keep your information protected. Enter your mobile number and we will send a text to verify your identity.')
                    or contains(text(), 'Attach a phone number to your account to keep your information protected. Enter your phone number and we will send a text to verify your identity.')
                ]")
            ) {
                $this->throwProfileUpdateMessageException();
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            try {
                $sendCode->click();
            } catch (Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("ElementClickInterceptedException: {$e->getMessage()}");
                sleep(5);
                $this->saveResponse();
                $sendCode->click();
            }

            return $this->processSecurityCheckpoint();
        }
        /*
        if ($this->parseQuestion()) {
            return false;
        }
        */

        return $this->checkErrors();
    }

    /*
    function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $user_info_mfa = urldecode($this->http->getCookieByName("user_info_mfa", "www.dunkindonuts.com"));
        $user_info_mfa = $this->http->JsonLog($user_info_mfa);
        $phone = $user_info_mfa->primaryPhone ?? null;
        if (!$phone) {
            return false;
        }
        $this->logger->debug(">>> phone number: {$phone}");
        $this->State['phone'] = $phone;
        $question = "Please enter Verification Code which was sent to your phone {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck())
            $this->Cancel();

        $data = [
            "accessCode"       => "",
            "primaryPhone"     => $this->State['phone'],
            "isPhoneUpdate"    => "false",
            "primaryPhoneType" => "WIRELESS",
            "service"          => "sendToken",
            "mode"             => "SMS",
        ];
        $headers = [
            "Accept"           => "*
    /*",
            "Accept-Encoding"  => "gzip, deflate, br",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => "https://www.dunkindonuts.com/en/mfa",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];
        $this->http->PostURL("https://www.dunkindonuts.com/bin/servlet/sendverifytoken", $data, $headers);
        $response = $this->http->JsonLog();
        if (!isset($response->data)) {
            return false;
        }
        // {"data":{"isMFARequried":false,"isLoyaltyEnrollment":false,"successMessage":true}}

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }
    */

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        /*$cookies = $this->driver->manage()->getCookies();
        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'user_info_mfa') {
                $this->logger->debug(var_export($cookie, true), ['pre' => true]);
                $user_info_mfa = $cookie['value'];
                break;
            }
        }

        $user_info_mfa = urldecode($user_info_mfa ?? $this->http->getCookieByName("user_info_mfa", "www.dunkindonuts.com"));
        $user_info_mfa = $this->http->JsonLog($user_info_mfa);*/

        $phoneText = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "security code we just sent to") and contains(text(), "•••")]'), 15);

        if ($phoneText) {
            $phone = $this->http->FindPreg('/sent to (.+?\d{4})\./', false, $phoneText->getText());
        }
        /*$phone = $user_info_mfa->primaryPhone ??
            $this->http->FindSingleNode('//span[contains(@class, "teaser-new__title-01") and contains(text(), "•••")]') ??
            null;*/
        if (!isset($phone)) {
            if ($this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "js-ajax-loader")]'), 0)) {
                throw new CheckRetryNeededException(3, 0);
            }

            $this->saveResponse();

            return false;
        }
        $this->logger->debug(">>> phone number: {$phone}");
        $this->State['phone'] = $phone;
        $question = "Please enter Verification Code which was sent to your phone {$phone}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        $this->saveResponse();
        //$this->waitForElement(WebDriverBy::xpath('//h1/span[contains(text(), "Enter Verification Code")]'), 10);
        $answerInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "accessCodeInput"]'), 0);
        $cont = $this->waitForElement(WebDriverBy::xpath('//input[@value = "CONTINUE"]'), 0);

        if (!$answerInput || !$cont) {
            return false;
        }

        if (empty($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $this->logger->debug("clear input");
        $answerInput->clear();
        $this->driver->executeScript("document.getElementById('accessCodeInput').value = \"\";");
        $this->saveResponse();
        $this->logger->debug("set new answer: {$this->Answers[$question]}");
//        $answerInput->sendKeys($this->Answers[$question]);
        $this->driver->executeScript("document.getElementById('accessCodeInput').value = \"{$this->Answers[$question]}\";");
        unset($this->Answers[$question]);

        $this->saveResponse();
        $cont->click();

        sleep(4);

        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(text(), "Sign Out")]
            | //a[contains(text(), "SIGN OUT")]
            | //div[contains(@class, "u-page-error")]
            | //li[contains(@class, "parsley-accessCodeError")]
        '), 0);

        try {
            $this->saveResponse();

            $wait = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "js-ajax-loader")] | //span[contains(text(), "If you are not redirected")]/a[contains(text(), "click here")]'), 0);
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(3);
            $this->saveResponse();

            $wait = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "js-ajax-loader")] | //span[contains(text(), "If you are not redirected")]/a[contains(text(), "click here")]'), 0);
        }

        $this->saveResponse();
        $error = $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "u-page-error")]
            | //li[contains(@class, "parsley-accessCodeError")]
        '), 0);
        $this->saveResponse();

        if (!$wait && !$error && $cont = $this->waitForElement(WebDriverBy::xpath('//input[@value = "CONTINUE"]'), 0)) {
            $this->logger->debug("click by btn one more time");
            $this->saveResponse();
            $cont->click();

            $this->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Sign Out")]
                | //a[contains(text(), "SIGN OUT")]
                | //div[contains(@class, "u-page-error")]
                | //li[contains(@class, "parsley-accessCodeError")]
            '), 5);
            $this->saveResponse();
        }// if (!$wait && !$error && $cont = $this->waitForElement(WebDriverBy::xpath('//input[@value = "CONTINUE"]'), 0))

        if ($wait) {
            if ($click = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "If you are not redirected")]/a[contains(text(), "click here")]'), 0)) {
                $click->click();
            }
            $this->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Sign Out")]
                | //a[contains(text(), "SIGN OUT")]
                | //div[contains(@class, "u-page-error")]
                | //li[contains(@class, "parsley-accessCodeError")]
            '), 20);
            $this->saveResponse();
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "u-page-error")]
                | //li[contains(@class, "parsley-accessCodeError")]
            '), 0)
        ) {
            $message = $error->getText();
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid access code.')) {
                $this->holdSession();
                $this->AskQuestion($question, null, "Question");

                return false;
            }

            if (strstr($message, 'Sorry, we are currently experiencing technical difficulty and are unable to process your request.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }
        /*
        $data = [
            "accessCode"       => $this->Answers[$this->Question],
            "primaryPhone"     => $this->State['phone'],
            "isPhoneUpdate"    => "false",
            "primaryPhoneType" => "WIRELESS",
            "service"          => "verifyToken",
            "mode"             => "SMS",
        ];
        $headers = [
            "Accept"           => "*
        /*",
            "Accept-Encoding"  => "gzip, deflate, br",
            "X-Requested-With" => "XMLHttpRequest",
            "Referer"          => "https://www.dunkindonuts.com/en/mfa",
            "Content-Type"     => "application/x-www-form-urlencoded; charset=UTF-8",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.dunkindonuts.com/bin/servlet/sendverifytoken", $data, $headers);
        $this->http->RetryCount = 2;
        unset($this->Answers[$this->Question]);
        $response = $this->http->JsonLog();
        */
        // {"error":{"code":"APP4310","message":"Invalid access code."}}
        // {"error":{"code":"SYS100","message":"Hystrix Error"}}
        $error = $response->error->message ?? null;

        if ($error == "Invalid access code." || $error == "Hystrix Error") {
            $this->AskQuestion($this->Question, "The code that you entered did not match the code sent or is invalid. Please check the code and try again.", 'Question');

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $headers = [
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "CSRF-Token"       => "undefined",
            "Content-Type"     => "application/json",
            "X-Requested-With" => "XMLHttpRequest",
        ];

        // Balance - Points You've Earned
        $this->SetBalance($this->http->FindSingleNode('//main//span[contains(@class, "js-user-points-earned")]'));
        // UNTIL NEXT REWARD
        $this->SetProperty("PointsToNextReward", $this->http->FindSingleNode('//main//span[contains(@class, "js-user-points-needed")]'));

        $this->parseWithCurl();

        $this->exportToEditThisCookies();

        $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/cval", [], $headers);
        $user_info = $this->browser->JsonLog($this->browser->JsonLog()->usinf ?? null, 3, true);
        // Name
        $perksUser = ArrayVal($user_info, 'perksUser');
        $loyaltyEnrollment = ArrayVal($user_info, 'loyaltyEnrollment');
        $name = Html::cleanXMLValue(ArrayVal($user_info, 'firstName') . " " . ArrayVal($user_info, 'lastName'));
        $this->SetProperty("Name", beautifulName($name));
        // Balance - Points You've Earned
        $loyaltyPoints = ArrayVal($user_info, 'loyaltyPoints');

        if ($loyaltyPoints !== '') {
            $this->SetBalance($loyaltyPoints);
            // UNTIL NEXT REWARD
            $this->SetProperty("PointsToNextReward", 200 - $this->Balance);
        }// if ($perksUser == true)
        // User is not member of this loyalty program
        elseif ($perksUser === false) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }

        $this->browser->GetURL("https://www.dunkindonuts.com/etc/clientcontext/legacy/config.init.js?path=/content/dd/en/account/perks-rewards", $headers);
        /* it works
        // Balance - Points You've Earned
        if ($this->http->FindPreg("/\"perkUser\": true,/")) {
            $this->SetBalance($this->http->FindPreg("/\"hasRewards\": true,\s*\"loyaltyPoints\":\s*(\d+),/"));
            // UNTIL NEXT REWARD
            $this->SetProperty("PointsToNextReward", 200 - $this->Balance);
        } // User is not member of this loyalty program
        elseif ($this->http->FindPreg("/\"perkUser\": false,/")) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }
        */
        /*
        $user_info = $this->browser->JsonLog(urldecode($this->browser->getCookieByName("user_info", "www.dunkindonuts.com")), 3, true);
        $perksUser = ArrayVal($user_info, 'perksUser');
        // Balance - Points You've Earned
        if ($perksUser == true) {
            $this->SetBalance(ArrayVal($user_info, 'loyaltyPoints'));
            // UNTIL NEXT REWARD
            $this->SetProperty("PointsToNextReward", 200 - $this->Balance);
        }// if ($perksUser == true)
        // User is not member of this loyalty program
        elseif ($perksUser == false)
            $this->SetWarning(self::NOT_MEMBER_MSG);
        // Name
        $name = Html::cleanXMLValue(ArrayVal($user_info, 'firstName')." ".ArrayVal($user_info, 'lastName'));
        $this->SetProperty("Name", beautifulName($name));
        */

        $this->SetProperty("CombineSubAccounts", false);

        /*
        $headers = [
            "Accept" => "*
        /*",
            "CSRF-Token" => "undefined",
            "Content-Type" => "application/x-www-form-urlencoded",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/profile", 'service=getProfileInfo', $headers);
        $response = $this->browser->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data');
        // Name
        $name = Html::cleanXMLValue(ArrayVal($data, 'firstName')." ".ArrayVal($data, 'lastName'));
        $this->SetProperty("Name", beautifulName($name));
        */

        // My Rewards
        $this->logger->info('My Rewards', ['Header' => 3]);
        $this->browser->RetryCount = 0;
        $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/loyalty", '{"service":"getAllCertificates"}', $headers);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog(null, 3, true);
        $data = ArrayVal($response, 'data', []);
        $rewards = ArrayVal($data, 'certificateList', []);
        $this->logger->debug("Total " . count($rewards) . " rewards were found");

        foreach ($rewards as $reward) {
            $displayName = ArrayVal($reward, 'strCertificateName');
            $exp = ArrayVal($reward, 'strExpiryDate');
            $couponNumber = ArrayVal($reward, 'strCertificateNumber');
            $this->AddSubAccount([
                'Code'           => 'dunkinRewards' . $couponNumber,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
                'CouponNumber'   => $couponNumber,
            ]);
        }// foreach ($rewards as $reward)

        // Expiration date  // refs #14211, 23510
        $this->logger->info('Expiration date', ['Header' => 3]);

        if ($this->Balance > 0) {
            $requestDate = time();
            $page = 1;

            do {
                $this->logger->debug("Page: {$page}");
                $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/loyalty", '{"month":"' . date('n', $requestDate) . '","year":"' . date('Y', $requestDate) . '","service":"getAccountTransactions"}', $headers);
                $response = $this->browser->JsonLog(null, 3, true);
                $data = ArrayVal($response, 'data', []);
                $transactions = ArrayVal($data, 'transactionList', []);
                $this->logger->debug("Total " . count($transactions) . " transaction were found");

                foreach ($transactions as $transaction) {
                    $purchase = ArrayVal($transaction, 'transactiontype') == "Purchase" ? true : false;
                    $date = ArrayVal($transaction, 'postedDateTime');

                    if ($purchase) {
                        // Last activity
                        $this->SetProperty("LastActivity", $date);

                        if ($exp = strtotime($date)) {
                            $this->SetExpirationDate(strtotime("+6 month", $exp));
                        }

                        break;
                    }// if ($purchase)
                }// foreach ($rewards as $transaction)
                $requestDate = strtotime("-1 month", $requestDate);
                $page++;
            } while (!isset($this->Properties['LastActivity']) && $page < 12);
        }// if ($this->Balance > 0)

        // All Dunkin Cards
        $this->logger->info('Dunkin Cards', ['Header' => 3]);
        // refs #20935
        // https://www.dunkindonuts.com/etc/designs/dd/scripts/all.min.bcbf2bb47109797cc576d3b914a141ba.js
        try {
            $this->http->GetURL("https://www.dunkindonuts.com/en/account/transaction-history#");
            $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "SELECT MONTH")]'), 5);
            $this->saveResponse();
            $this->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/lastFourDigits/g.exec( this.responseText )) {
                            localStorage.setItem("responseCardsData", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                
                APP.cardManagement.setDDCardsListAjax();
            ');
        } catch (Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("ElementClickInterceptedException: {$e->getMessage()}");
        }
        sleep(2);
        $responseCardsData = $this->driver->executeScript("return localStorage.getItem('responseCardsData');");
        $this->logger->info("[Form responseCardsData]: " . $responseCardsData);
//        $this->browser->RetryCount = 0;
//        $this->browser->PostURL("https://www.dunkindonuts.com/bin/servlet/transaction", '{"service":"getDdCardDetails"}', $headers);
//        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog($responseCardsData, 3, true);
        $data = ArrayVal($response, 'data', []);
        $this->logger->debug("Total " . count($data) . " cards were found");

        foreach ($data as $card) {
            $cardNumber = ArrayVal($card, 'cardNumber');
            $lastFourDigits = ArrayVal($card, 'lastFourDigits');
            $balance = ArrayVal(ArrayVal($card, 'balance'), 'balance');
            $this->AddSubAccount([
                'Code'        => 'dunkinCard' . md5($cardNumber) . $lastFourDigits,
                'DisplayName' => 'Card #••••••••••••' . $lastFourDigits,
                'Balance'     => $balance,
            ]);
        }// foreach ($data as $card)
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[contains(@action, 'signin')]//div[@id = 'recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 100;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function exportToEditThisCookies()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("exportToEditThisCookies", ['Header' => 3]);
        $cookiesArr = [];
        $cookiesArrGeneral = [];
        $domains = [
            ".dunkindonuts.com",
            "www.dunkindonuts.com",
        ];
        $cookies = [];

        foreach ($domains as $domain) {
            $cookies = array_merge($cookies, $this->browser->GetCookies($domain), $this->browser->GetCookies($domain, "/", true));
        }
        $i = 1;

        foreach ($cookies as $cookie => $val) {
            $c = [
                "domain"   => ".dunkindonuts.com",
                //                "expirationDate" => 1494400127,
                "hostOnly" => false,
                "httpOnly" => false,
                "name"     => $cookie,
                "path"     => "/",
                "secure"   => false,
                "session"  => false,
                "storeId"  => "0",
                "value"    => $val,
            ];
            $cookiesArr[] = $c;
            $cg = "document.cookie=\"{$cookie}=" . str_replace('"', '\"', $val) . "; path=/; domain=.dunkindonuts.com\";";
            $cookiesArrGeneral[] = $cg;
            $i++;
        }// foreach ($cookies as $cookie)
        $this->logger->debug("==============================");
        $this->logger->debug(str_replace("\/", "/", json_encode($cookiesArr)));
        $this->logger->debug("==============================");
        $this->logger->debug("===============2==============");
        $this->logger->debug(var_export(implode(' ', $cookiesArrGeneral), true));
        $this->logger->debug("==============================");
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our site is temporarily down for maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is temporarily down for maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($message = $this->http->FindSingleNode('//h1[contains(., "is temporarily unavailable. We are working to quickly correct the issue")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // SCHEDULED MAINTENANCE
        if ($message = $this->http->FindSingleNode('
                //h1[contains(text(), "SCHEDULED MAINTENANCE")]
                | //div[contains(text(), "Please note, we are currently performing system maintenance")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Provider Error
        if ($this->http->FindPreg("/An error occurred while processing your request\.<p>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
