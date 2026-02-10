<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAerlingusSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        TAccountChecker::InitBrowser();

        $this->UseSelenium();
        $resolutions = [
            [1280, 720],
            [1280, 800],
            [1360, 768],
            [1920, 1080],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        if ($this->attempt == 2) {
            $this->useChromium();
        } else {
            $this->useFirefox();
            $this->setKeepProfile(true);
        }

        if ($this->attempt == 1) {
            $this->setProxyBrightData();
        } else {
            $this->http->SetProxy($this->proxyReCaptchaVultr());
        }

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['X-XSRF-TOKEN'])) {
            return false;
        }

        $this->http->GetURL("https://www.aerlingus.com/html/user-profile.html#");

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://www.aerlingus.com/api/loyalty/v1/login?redirect=%2Fhtml%2Fuser-profile.html");
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }

        // login
        $this->waitForElement(WebDriverBy::xpath('
            //input[@id = "test_membership_login_page-1"]
            | //p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]
        '), 20);

        $loginInput = $this->waitForElement(WebDriverBy::id('test_membership_login_page-1'), 0);
        // password
        $passwordInput = $this->waitForElement(WebDriverBy::id('test_password_login_page-1'), 0);

        $this->hideOverlay();
        $this->saveResponse();

        if (!$loginInput || !$passwordInput) {
            $this->captchaWorkaround();

            return $this->checkErrors();
        }

        if ($cookieAccept = $this->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 0)) {
            $cookieAccept->click();
            sleep(1);
            $this->saveResponse();
        }

        $loginInput->clear();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->clear();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        // Sign In
        sleep(1);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in")]'), 0);
        $this->saveResponse();
        $this->logger->debug("click by login field");

        // captcha workaround
        if ($this->waitForElement(WebDriverBy::xpath('//div[@id = "captcha"]'), 0)) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->logger->debug('setting captcha: ' . $captcha);

            try {
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
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(2, 5);
            }
            sleep(1);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "One moment please!")]'), 0)) {
            sleep(5);
            $this->saveResponse();
        }
        $loginInput->click(); // for error 'Password must contain at least one uppercase letter, one lowercase letter and one number. Spaces, backslashes, double quotes are not allowed.'
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        try {
            $this->logger->debug("click by 'Log in' button");
            $button->click();
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // Password must contain at least one uppercase letter, one lowercase letter and one number.
            if (
                $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "error-icon")]'), 0)
                && ($passwordError = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Password must contain at least one uppercase letter, one lowercase letter and one number.")]'), 0, false))
            ) {
                throw new CheckException($passwordError->getText(), ACCOUNT_INVALID_PASSWORD);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->debug("waiting results");
        $resultXpath = '
            //div[contains(@class, "user-profile-aerclub-card")]
            | //p[@data-test-id = "test_membership_number"]
            | //div[@id = "scroll_messages"])[normalize-space(text())!=""]
            | //p[contains(@class, "uil-errorRed")]
            | //section[contains(@class, "uil-message-error")]
            | //h4[contains(text(), "We appear to have lost our way a little")]
            | //h4[contains(text(), "Sorry, we couldn\'t find this page")]
            | //span[contains(text(), "We could not send the sms. Please try the recovery code.")]
            | //span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]
            | //p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]
            | //input[@id = "code"]
            | //p[contains(text(), "An error has occurred with your login. This could be due to a connectivity or server issue.")]
        ';

        $result = $this->waitForElement(WebDriverBy::xpath($resultXpath), 15);

        try {
            $this->saveResponse();
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->logger->debug("Need to change ff version");
        }

        if (!$result && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "One moment please!")]'), 0)) {
            $this->saveResponse();
            $this->waitForElement(WebDriverBy::xpath($resultXpath), 15);
        }
        // save page to logs
        $this->saveResponse();

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        }

        if (
            $this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "One moment please!")]
                | //h4[contains(text(), "Sorry, we couldn\'t find this page")]
                | //h4[contains(text(), "{{\'page.notfound.text.title\' | i18n}}")]
                | //p[contains(text(), "Please enter a valid Captcha to continue")]
            '), 0)
            || count($this->http->FindNodes('//p[contains(@class, "uil-errorRed")]')) == 2
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $message = $this->http->FindSingleNode('
            //section[contains(@class, "uil-message-error")] 
            | //p[contains(@class, "uil-errorRed")]
        ');
        $this->logger->error("[Error]: {$message}");
        // Access is allowed
        if (
            (
                empty($message)
                || $this->http->FindSingleNode('//p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]')
                || strstr($this->http->currentUrl(), 'www.aerlingus.com/html/user-profile.html')
            )
            && $this->loginSuccessful()
        ) {
            return true;
        }

        if ($this->questionCode()) {
            return false;
        }

        if ($message) {
            if (
                $message == 'These log in details are incorrect. Please try again or recover your details.'
                || $message == 'Username or Email cannot exceed 48 characters. Please try again.'
                || $message == 'This account is not valid. Please contact AerClub for assistance.'
                || $message == 'Password must contain at least one uppercase letter, one lowercase letter and one number. Spaces, backslashes, double quotes are not allowed'
                || $message == 'Password cannot exceed 20 characters. Please try again.'
                || $message == 'Password must contain at least 8 character(s). Please try again.'
                || strstr($message, 'Username or Email Address may only contain numbers, letters, @, periods, hyphens and underscores.')
                || strstr($message, 'We couldn’t sign you in at the moment, please review your login details')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // We are experiencing some technical difficulties on our side. Please try again in a few minutes or contact us if the problem persists.
            if (
                strstr($message, 'We are experiencing some technical difficulties on our side. Please try again in a few minutes ')
                || strstr($message, 'AerClub is temporarily unavailable while we make some planned improvements. Everything should be back up and running ')
                || strstr($message, 'Sorry, this service is temporarily unavailable due to scheduled maintenance')
                || $message == 'You can’t log in until you verify your account. Please generate a new verification email and click on the link in it within 24 hours.'
                || strstr($message, 'Currently we are updating our system. ')
                || strstr($message, 'AerClub is temporarily unavailable while we make some planned improvements')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'You have attempted to log in with the wrong details too many times and your account is now locked.')
                || strstr($message, 'For security reasons, we have blocked this authentication attempt.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == 'Please fill in your Username or Email Address') {
                throw new CheckRetryNeededException(3, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//div[@class = "page-loader" and @aria-hidden="false"]')) {
            $this->logger->notice('Loading...');
        }

        // broken account
        if (in_array($this->AccountFields['Login'], [
            // AccountID: 6246075
            'chrissakamotozs@gmail.com',
            // AccountID: 6207473
            'shartdainal',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 6207473
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "An error has occurred with your login. This could be due to a connectivity or server issue.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->captchaWorkaround($message);

        return false;
    }

    public function questionCode()
    {
        $this->logger->notice(__METHOD__);

        $code = $this->waitForElement(WebDriverBy::xpath('//input[@id = "code" or @name = "code"]'), 5);
        $next = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue") or @class = "auth0-lock-submit"]'), 0);
        $this->saveResponse();

        $question = $this->http->FindSingleNode('
            //p[contains(text(), "We\'ve sent an email with your code to")]
            | //span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]
            | //span[contains(text(), "We could not send the sms. Please try the recovery code.")]
        ');

        if (!$code || !$next || !$question) {
            $this->logger->error("[Code]: input not found");

            return false;
        }// if (!$Code || !$password)

        if (!isset($this->Answers[$question])) {
            $this->saveResponse();
            $this->holdSession();
            $this->AskQuestion($question, null, "Code");

            return false;
        }

        $code->sendKeys($this->Answers[$question]);
        $this->saveResponse();
        $code->click();
        unset($this->Answers[$question]);
        $this->logger->debug("clicking next");
        $next->click();

        sleep(5);

        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "user-profile-aerclub-card")]
            | //p[@data-test-id = "test_membership_number"]
            | //p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]
        '), 5);
        $this->saveResponse();

//        if ($error = $this->http->FindSingleNode('
//                //div[contains(text(), "The temporary identification code or password you entered is incorrect. Please try again.")]
//        ')) {
//            $this->AskQuestion($this->Question, $error, "Code");
//
//            return false;
//        }

        return $this->loginSuccessful();
    }

    public function ProcessStep($step)
    {
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@id = "code" or @name = "code"]'), 0);
        $this->saveResponse();

        if (
            !$otp
            && (
                $this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                    | //title[contains(text(), 'http://localhost:4448/start?text={$this->AccountFields['ProviderCode']}+%7C+{$this->AccountFields['Login']}+%7C+')]
                    | //pre[not(@id) and normalize-space(text()) = '{\"code\":400,\"message\":\"Unknown method: start\"}']
                    | //p[contains(text(), 'Health check')]
                    | //span[contains(text(), 'This site can’t be reached')]
                "), 0)
            )
        ) {
            $this->saveResponse();

            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Code') {
            $this->saveResponse();

            return $this->questionCode();
        }

        return true;
    }

    public function Parse()
    {
        $aerlingus = $this->getAerlingus();

        $aerlingus->Parse();
        $this->SetBalance($aerlingus->Balance);
        $this->Properties = $aerlingus->Properties;
        $this->ErrorCode = $aerlingus->ErrorCode;

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            $this->ErrorMessage = $aerlingus->ErrorMessage;
            $this->DebugInfo = $aerlingus->DebugInfo;
        }
    }

    public function ParseItineraries()
    {
        $aerlingus = $this->getAerlingus();

        return $aerlingus->ParseItineraries();
    }

    public function ParseHistory($startDate = null)
    {
        $aerlingus = $this->getAerlingus();

        return $aerlingus->ParseHistory($startDate);
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"    => [
                "Type"     => "string",
                "Caption"  => "Family Name",
                "Size"     => 25,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            'Date'         => 'PostingDate',
            'Description'  => 'Description',
            'Avios points' => 'Miles',
            'Bonus'        => 'Bonus',
            'Tier Credits' => 'Info',
        ];
    }

    /** @return TAccountCheckerAerlingus */
    protected function getAerlingus()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->aerlingus)) {
            $this->aerlingus = new TAccountCheckerAerlingus();
            $this->aerlingus->http = new HttpBrowser("none", new CurlDriver());
//            $this->aerlingus->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->aerlingus->http);
            $this->aerlingus->AccountFields = $this->AccountFields;
            $this->aerlingus->HistoryStartDate = $this->HistoryStartDate;
            $this->aerlingus->historyStartDates = $this->historyStartDates;
            $this->aerlingus->History = $this->History;
            $this->aerlingus->http->LogHeaders = $this->http->LogHeaders;
            $this->aerlingus->ParseIts = $this->ParseIts;
            $this->aerlingus->ParsePastIts = $this->ParsePastIts;
            $this->aerlingus->WantHistory = $this->WantHistory;
            $this->aerlingus->WantFiles = $this->WantFiles;
            $this->aerlingus->strictHistoryStartDate = $this->strictHistoryStartDate;
            $this->aerlingus->itinerariesMaster = $this->itinerariesMaster;
            $this->aerlingus->globalLogger = $this->globalLogger;
            $this->aerlingus->logger = $this->logger;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->aerlingus->http->setDefaultHeader($header, $value);
            }

            $this->aerlingus->globalLogger = $this->globalLogger;
            $this->aerlingus->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        return $this->aerlingus;
    }

    private function captchaWorkaround($message = null)
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]')
            || $message == 'Please fill in your Username or Email Address'
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6Lf7m_cZAAAAAHUIGOzHbeMHbzIF6iwk5_SpA-nW";

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function hideOverlay()
    {
        $this->logger->notice(__METHOD__);
        $accept = $this->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 2);
        $this->saveResponse();

        if ($accept) {
            $this->driver->executeScript('var divsToHide = document.getElementsByClassName("onetrust-pc-dark-filter");
                for(var i = 0; i < divsToHide.length; i++) {
                    divsToHide[i].style.display = "none";
                } 
                var overlay2 = document.getElementById("onetrust-banner-sdk"); if (overlay2) overlay2.style.display = "none";
            ');
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("history start dates: " . json_encode($this->historyStartDates));
        $aerlingus = $this->getAerlingus();
        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'XSRF-TOKEN') {
                $token = $cookie['value'];
                $this->State['X-XSRF-TOKEN'] = $token;
            }

            $aerlingus->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if (!isset($token)) {
            $this->logger->error("X-XSRF-TOKEN not found");

            return false;
        }

        $aerlingus->http->RetryCount = 0;

        try {
            $aerlingus->http->GetURL($this->http->currentUrl(), [], 40);
        } catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
        $aerlingus->http->RetryCount = 2;

        if ($aerlingus->http->FindPreg('/Operation timed out after/', false, $aerlingus->http->Error)) {
            unset($this->State["UserAgent"]);
            $this->logger->debug("operation timed out. trying to detect ip, to check proxy");
            $aerlingus->http->RetryCount = 0;
            $aerlingus->http->GetURL("http://ipinfo.io/json", [], 5);

            throw new CheckRetryNeededException(2, 1);
        }

        $aerlingus->delay();

        if ($aerlingus->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            return false;
        }

        $aerlingus->http->setDefaultHeader('X-XSRF-TOKEN', $token);
        $aerlingus->http->RetryCount = 0;

        if ($aerlingus->http->currentUrl() != 'https://www.aerlingus.com/api/profile') {
            $aerlingus->http->GetURL("https://www.aerlingus.com/api/profile", $aerlingus->headers, 20);
        }

        $aerlingus->http->RetryCount = 2;
        $response = $aerlingus->http->JsonLog();

        $this->logger->debug("[Name]: " . ($response->data[0]->firstName ?? ''));

        if (isset($response->data[0]->firstName)) {
            return true;
        }

        return false;
    }
}
