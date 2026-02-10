<?php

class TAccountCheckerBiltSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private const REWARDS_PAGE_URL = 'https://api.biltcard.com/user/profile';
    private const XPATH_QUESTION = "//span[contains(text(), 'Enter the ') and contains(text(), 'code we sent to your phone number')]";
    private const XPATH_SUCESS = "(//div[//span[contains(text(), 'Good ')]]//div[contains(text(), ' pts')])[1]";

    /**
     * @var HttpBrowser
     */
    public $browser;

    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        'Accept-Encoding' => 'gzip, deflate',
        'Lang'            => 'en',
        'User-Agent'      => 'iOS',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->disableImages();
        $this->http->saveScreenshots = true;

        $this->setProxyGoProxies(); // amazon complaince workaround
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $this->parseWithCurl();

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        unset($this->State['Authorization']);

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('We do not recognize this email', ACCOUNT_INVALID_PASSWORD);
        }

        try {
            $this->http->removeCookies();
            $this->http->GetURL('https://www.biltrewards.com/login');
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(3, 0);
        } catch (TimeOutException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->driver->executeScript('window.stop();');
        }

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 5);
        $this->saveResponse();

        if ($login) {
            $login->sendKeys($this->AccountFields['Login']);
            $this->saveResponse();
            $signInButton = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Continue")]'), 3);

            if (!$signInButton) {
                return false;
            }

            $signInButton->click();
        }

        sleep(3);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 3);

        if (!$pass) {
            $this->saveResponse();
            $this->driver->executeScript("let pass = document.querySelector('input[name = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 2);
        }

        $this->saveResponse();

        // provider bug fix (captcha workaround)
        if (!$pass && $btn = $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Log in")]'), 0)) {
            $btn->click();
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 5);
            $this->saveResponse();
        }

        if (!$pass) {
            if ($this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Request failed with status code 403') or contains(text(), 'Network Error') or contains(text(), 'recaptcha score is less than minimum allowed') or contains(text(), 'recaptcha score is less than minimum allowed') or contains(text(), 'Unknown error')]"), 0)) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Access to your account has been restricted. Please contact support for more information.')]"), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }

            if (
                (
                    $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 0)
                    && $this->waitForElement(WebDriverBy::xpath('//div[@disabled]/input[@value = "Next"]'), 0)
                )
                || $this->waitForElement(WebDriverBy::xpath('//button[contains(., "Log in")]'), 0)
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 5);
            }

            return $this->checkErrors();
        }

        try {
            $pass->sendKeys($this->AccountFields['Pass']);
        } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
        }

        // TODO: Debug
        if ($login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 0)) {
            $this->saveResponse();

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $this->saveResponse();
        }

        $this->driver->executeScript('
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                    .then((response) => {
                        if (response.url.indexOf("/v1/token") > -1) {
                            response
                            .clone()
                            .json()
                            .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                    }
                        resolve(response);
                    })
                .catch((error) => {
                        reject(response);
                    })
                });
            }
        ');

        $signInButton = $this->waitForElement(WebDriverBy::xpath('//div[not(@disabled)]/input[@value = "Next"] | //input[@value="Log in" and not(@disabled)] | //button[contains(., "Log in")]'), 3);
        $this->saveResponse();

        if (!$signInButton) {
            if ($message = $this->http->FindSingleNode("//div[contains(@class, 'UserHeaderTitle') and contains(text(), 'Create password')] | //span[contains(text(), 'Create your Bilt account')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // provider bug may be
            if ($this->waitForElement(WebDriverBy::xpath('//input[@value="Log in" and @disabled] | //button[contains(., "Log in")]'), 0)) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $signInButton->click();

        return true;
    }

    public function Login()
    {
        $res = $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_SUCESS
            . " | //span[contains(text(), 'We do not recognize this email / password combination.')]
            | //span[contains(text(), 'Request failed with status code 500')]
            | //span[contains(text(), 'Network Error')]
            | //div[span[contains(text(), 'Create password')]]
            | " . self::XPATH_QUESTION . "
            | //input[@autocomplete = \"one-time-code\"]
            | //div[contains(@class, 'ErrorTextWrapper')]
            | //div[contains(text(), 'Confirm your information to complete your account')]
            | //button[contains(text(), 'Complete Account')]
            | //div[contains(text(), 'Invalid username or password.')]
            | //div[contains(text(), 'We couldn't process your request at this time.')]
            | //div[contains(text(), 'Request failed with status code 502')]
        "), 40);
        $this->saveResponse();

        $resText = null;

        if ($res) {
            try {
                $resText = $res->getText();
            } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);

                $res = $this->waitForElement(WebDriverBy::xpath(
                    self::XPATH_SUCESS
                    . " | //span[contains(text(), 'We do not recognize this email / password combination.')]
                    | //span[contains(text(), 'Request failed with status code 500')]
                    | //span[contains(text(), 'Network Error')]
                    | //div[span[contains(text(), 'Create password')]]
                    | " . self::XPATH_QUESTION . "
                    | //input[@autocomplete = \"one-time-code\"]
                    | //div[contains(@class, 'ErrorTextWrapper')]
                    | //div[contains(text(), 'Confirm your information to complete your account')]
                    | //div[contains(text(), 'Error loading status')]
                    | //button[contains(text(), 'Complete Account')]
                    | //div[contains(text(), 'Invalid username or password.')]
                    | //div[contains(text(), 'We couldn't process your request at this time.')]
                    | //div[contains(text(), 'Request failed with status code 502')]
                "), 15);
                $resText = $res ? $res->getText() : null;
                $this->saveResponse();
            }
        }

        $this->logger->debug("[resText]: {$resText}");

        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: '" . $responseData . "'");

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (!empty($responseData)) {
            $this->http->SetBody($responseData);
        }

        $response = $this->http->JsonLog();

        if ($this->parseQuestion()) {
            $this->markProxySuccessful();

            return false;
        }

        if (isset($response->id_token)) {
            $this->markProxySuccessful();
            $this->State['Authorization'] = $response->id_token;
            $this->parseWithCurl();

            return $this->loginSuccessful();
        }

        if ($this->http->FindNodes(self::XPATH_SUCESS)) {
            $this->markProxySuccessful();

            return true;
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We do not recognize this email')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($resText == 'Error authenticating user') {
            throw new CheckException($resText, ACCOUNT_INVALID_PASSWORD);
        }

        if ($resText == 'Error loading status') {
            throw new CheckException($resText, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Network Error")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode("//div[span[contains(text(), 'Create password')]]")
            || $this->http->FindSingleNode("//div[contains(text(), 'Confirm your information to complete your account')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Create an account password to complete this payment')]")
            || $resText === 'COMPLETE ACCOUNT'
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindPreg("/>Request failed with status code 500<\/span>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'We could not register you due to security settings.')]")) {
            $this->DebugInfo = 'captcha issue';
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'ErrorTextWrapper')] | //div[contains(text(), 'Invalid username or password.')] | //div[contains(text(), 't process your request at this time.')] | //div[contains(text(), 'Request failed with status code')]")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Invalid username or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'An unexpected error occurred. Please try again')
                || strstr($message, 'Unexpected error while authenticating user, please try again!')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'We couldn\'t process your request at this time. If you\'re using a shared network or VPN')
                || strstr($message, 'Request failed with status code ')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // provider bug fix, it helps
        if (
            $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0)
            && $this->waitForElement(WebDriverBy::xpath('//div[@disabled]/input[@value = "Next"]'), 0)
        ) {
            $this->saveResponse();
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2, 5);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode(self::XPATH_QUESTION);
        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@autocomplete = "one-time-code"]'), 0);

        if (!$question || !$codeInput) {
            return false;
        }

        $this->holdSession();

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse()
    {
        if (!isset($this->State['Authorization'])) {
            // Name
            $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("(//div/span[contains(@class, 'tertiary')]/following-sibling::span)[1]")));
            // Balance - Bilt points
            $this->SetBalance($this->http->FindSingleNode(self::XPATH_SUCESS));
            // Bilt Rewards Member Number
//            $this->SetProperty('Number', $response->loyaltyId);

            // refs #23276
            $this->http->GetURL("https://www.biltrewards.com/account/status-tracker");
            $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Progress to')]"), 15);
            $this->saveResponse();
            // Status
            $this->SetProperty('Status', $this->http->FindSingleNode("//span[contains(text(), 'Your status')]/following-sibling::div[1]/span"));
            // Status points
            $this->SetProperty('StatusPoints', $this->http->FindSingleNode('//button[//span[contains(text(), "Progress to ")]]/following-sibling::div[contains(., "point")]//div[contains(text(), "Point")]/preceding-sibling::div[1]'));
//            $this->SetProperty('StatusPointsTODO', $this->http->FindHTMLByXpath('//button[//span[contains(text(), "Progress to ")]]/following-sibling::div[contains(., "point")]//div[contains(text(), "Point")]/preceding-sibling::div[1]', "/aaaaaa/"));
            // Reach ... status by earning ... more points or spending $9,976 more in eligible spend by 12/31
            $this->SetProperty('PointsToNextLevel', $this->http->FindSingleNode('//span[contains(text(), "Reach ")]', null, true, "/by earning\s*([\d\,\.]+) more/"));
            // Eligible spend
            $this->SetProperty('EligibleSpend', $this->http->FindSingleNode('//button[//span[contains(text(), "Progress to ")]]/following-sibling::div[contains(., "Spend")]//div[contains(text(), "Spend")]/preceding-sibling::div[1]'));
            // Reach ... status by earning ... more points or spending $9,976 more in eligible spend by 12/31
            // Need to spend to next level
            $this->SetProperty('SpendToNextLevel', $this->http->FindSingleNode('//span[contains(text(), "Reach ")]', null, true, "/spending\s*(.[\d\,\.]+) more/"));

            return;
        }

        $response = $this->browser->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->profile->name));

        $this->browser->GetURL("https://api.biltcard.com/loyalty/user", $this->headers);

        // AccountID: 6776121
        if (
            $this->browser->Response['code'] == 404
            && $response->residence === null
            && $response->paymentAccounts->accounts === []
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        // AccountID: 6782673
        } elseif (
            $this->browser->Response['code'] == 400
            && $response->residence === null
            && $response->paymentAccounts->accounts === []
        ) {
            $this->throwProfileUpdateMessageException();
        }

        $response = $this->browser->JsonLog();
        // Balance - Bilt points
        $this->SetBalance($response->availablePoints);
        // Bilt Rewards Member Number
        $this->SetProperty('Number', $response->loyaltyId);
        // Status
        $this->SetProperty('Status', $response->currentTierName);
        // Status points
        $this->SetProperty('StatusPoints', $response->tierPoints);
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        /*
        $proxyPort = '10000';
        $proxyURL = 'proxy-america.goproxies.com';
        $address = $proxyURL . ":" . $proxyPort;
        $this->browser->SetProxy($address);
        $ipInfoURL = 'https://ip.goproxies.com';
        $siteURL = $siteURL ?? $ipInfoURL;
        $country = "us";

        $this->http->Log("using GoProxies proxy");
        $selector = "-country-{$country}-";
        $browser = new HttpBrowser("none", new CurlDriver());
        $browser->RetryCount = 0;
        $browser->SetProxy($address);
        $selectorMain = $selector;
        $n = 0;

        do {
            $sessionId = random_int(1, 99999999); // uniqid();

            if (
                !empty($this->State["goproxies-session"])
                && $n === 0
                && $this->attempt === 0
            ) {
                $sessionId = $this->State["goproxies-session"];
                $this->logger->info("restored goproxies sessionId from state: $sessionId");
            }

            $selector = $selectorMain . "-sessionid-" . $sessionId;
            $login = "customer-" . GOPROXIES_USERNAME . $selector;
            $browser->setProxyAuth($login, GOPROXIES_PASSWORD);
            $response = $browser->GetURL($siteURL, [], 20);

            $context = [
                'country'      => "us",
                'sid'          => $sessionId,
                'domain'       => $address,
                'siteUrl'      => $siteURL,
                'responseCode' => $browser->Response['code'],
                'success'      => null,
                'numTry'       => $n,
            ];

            if (!$response) {
                $this->logger->warning("failed to get $siteURL, response code: {$browser->Response['code']}");
                $context['success'] = false;
            } else {
                $context['success'] = true;
            }
            StatLogger::getInstance()->info("GoProxies statistic", $context);

            $n++;
        } while ($n < 7 && !$response);

        if (!$response) {
            $context['success'] = false;
            StatLogger::getInstance()->info("GoProxies-live statistic", $context);
            $this->logger->warning("no live GoProxies proxy found");

            return null;
        }

        $context['success'] = true;
        StatLogger::getInstance()->info("GoProxies-live statistic", $context);

        $this->logger->info("live proxy found, response code: {$browser->Response['code']}");
        $this->State["goproxies-session"] = $sessionId;

        $this->browser->SetProxy($address, true, 'goproxies', $country);
        $this->browser->setProxyAuth($login, GOPROXIES_PASSWORD);

        if ($siteURL !== $ipInfoURL) {
            $browser->GetURL($ipInfoURL, [], 10);
        }
        $proxyIp = trim($browser->Response['body']);
        $this->logger->info("proxy ip: " . $proxyIp);
        $this->State["proxy-ip"] = $proxyIp;
        */

        $this->browser->SetProxy($this->proxyReCaptchaIt7());
        $this->browser->RetryCount = 0;
        $this->browser->GetURL($this->http->currentUrl());
        $this->browser->RetryCount = 2;
    }

    private function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $codeInput = $this->waitForElement(WebDriverBy::xpath('//input[@autocomplete = "one-time-code"]'), 0);
        $this->saveResponse();

        if (empty($codeInput)) {
            $this->logger->error("something went wrong");

            return false;
        }

        $answerInputs = $this->driver->findElements(WebDriverBy::xpath('//input[@autocomplete = "one-time-code"]'));

        $this->logger->debug("entering answer...");

        foreach ($answerInputs as $i => $element) {
            $this->logger->debug("#{$i}: {$answer[$i]}");
            $answerInputs[$i]->clear();
            $answerInputs[$i]->sendKeys($answer[$i]);
            $this->saveResponse();
        }

        $this->driver->executeScript('
            const constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                    constantMock.apply(this, arguments)
                    .then((response) => {
                        if (response.url.indexOf("/v1/token") > -1) {
                            response
                            .clone()
                            .json()
                            .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                    }
                        resolve(response);
                    })
                .catch((error) => {
                        reject(response);
                    })
                });
            }
        ');

        $sendCode = $this->waitForElement(WebDriverBy::xpath("//button[not(@disabled) and contains(., 'Continue')]"), 10);
        $this->saveResponse();

        if (!$sendCode) {
            $this->logger->error("something went wrong");

            // TODO: debug
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_SUCESS), 10);
            $this->saveResponse();

            $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: '" . $responseData . "'");

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if ($this->http->FindSingleNode(self::XPATH_SUCESS)) {
                $this->markProxySuccessful();

                return true;
            }

            return false;
        }

        $sendCode->click();

        $res = $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_SUCESS
            . "| //div[contains(text(), 'Code did not pass verification')]
            | //div[contains(text(), 'OTP expired (10 minutes), ')]
        "), 10);
        $this->saveResponse();

        if (
            $res
            && (
                strstr($res->getText(), 'Code did not pass verification')
                || strstr($res->getText(), 'OTP expired (10 minutes), ')
            )
        ) {
            $this->holdSession();
            $this->AskQuestion($this->Question, $res->getText(), "Question");

            return false;
        }

        $responseData = $this->driver->executeScript("return localStorage.getItem('responseData');");
        $this->logger->info("[Form responseData]: '" . $responseData . "'");

        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (!empty($responseData)) {
            $this->http->SetBody($responseData);
        }

        $response = $this->http->JsonLog();

        if (isset($response->id_token)) {
            $this->State['Authorization'] = $response->id_token;
            $this->parseWithCurl();

            return $this->loginSuccessful();
        }

        if ($this->http->FindSingleNode(self::XPATH_SUCESS)) {
            $this->markProxySuccessful();

            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Authorization'   => 'Bearer ' . $this->State['Authorization'],
        ];
        $this->browser->RetryCount = 0;
        $this->browser->GetURL(self::REWARDS_PAGE_URL, $this->headers + $headers, 20);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog();
        $email = $response->profile->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            $this->headers = $this->headers + $headers;

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
