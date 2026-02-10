<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirasia extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    private $browser;

    private $userData = null;
    private $userBalance = null;
    private $providerBug = false;
    private $currentItin = 0;
    private $responseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        // needed for itineraries otherwise 403 after posting form to https://booking2.airasia.com/BookingListLogin.aspx?culture=en-GB
        $this->http->SetProxy($this->proxyDOP());

        $this->KeepState = true;
        $this->UseSelenium();
        $this->useFirefox();
        $this->setKeepProfile(true);
        $this->http->saveScreenshots = true;
        $this->seleniumOptions->recordRequests = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://airasia.com/', [], 20);

        $this->waitFor(function () {
            return
                $this->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 0)
                || $this->waitForElement(WebDriverBy::xpath('//a[@id = "login-tooltip-button"]'), 0)
                ;
        }, 20);

        $openFormBtn =
            $this->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 0)
            ?? $this->waitForElement(WebDriverBy::xpath('//a[@id = "login-tooltip-button"]'), 0)
        ;

        if (!$openFormBtn) {
            $this->saveResponse();

            if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                $this->markProxyAsInvalid();
                $retry = true;
            }

            return false;
        }
        $openFormBtn->click();

        $login = $this->waitForElement(WebDriverBy::id('text-input--login'), 5);
        $pwd = $this->waitForElement(WebDriverBy::id('password-input--login'), 0);
        $btn = $this->waitForElement(WebDriverBy::id('loginbutton'), 0);

        if (!isset($login, $pwd, $btn)) {
            $this->saveResponse();

            return false;
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pwd->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $btn->click();

        return true;

//        return $this->selenium();
        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://airasia.com/", [], 20);
        $this->http->RetryCount = 2;

        if (!$this->http->FindPreg("/aaw-login-tab/")) {
            if (!strstr($this->http->currentUrl(), '/maintenance.html')) {
                $this->http->GetURL('https://sso-widget.airasia.com/bundle.en-GB.js.gz');
            }
        */

        if (!$this->http->FindPreg("/aaw-login-tab/")) {
            return $this->checkErrors();
        }
        /*
        }
        */
        $this->http->RetryCount = 0;
        $keys = $this->getApiKeys("https://www.airasia.com");
        $clientId = $keys['clientId'];
        $apiKey = $keys['apiKey'];

        if (!$clientId || !$apiKey) {
            // proxy issues
            if (
                strstr($this->http->Error, 'Network error 28 - Connection timed out after ')
            ) {
                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }
        $headers = [
            "Accept"       => "application/json",
            "Content-Type" => "application/json",
            "Origin"       => "https://www.airasia.com",
        ];
        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => substr($this->AccountFields['Pass'], 0, 16), // refs #22449
        ];
        $headers = array_merge($headers, ["x-api-key" => $apiKey]);
        $this->http->PostURL("https://ssor.airasia.com/sso/v2/authorization/by-credentials?clientId={$clientId}", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //input[@id = "text-input--additionalSignupFields-firstName"]
            | //div[@class = "aaw-error-message-content"]
            | //div[@class = "aaw-alert-message-content"]
            | //p[contains(text(), "Save your details in your airasia Member account")]
            | //div[contains(text(), "This account will be deleted on")]
            | //p[contains(text(), "To continue, please enter the OTP")]
        '), 15);
        $this->saveResponse();

        if ($question = $this->http->FindSingleNode('//div[contains(@class, "aaw-otp-container")]//node()[contains(., "To continue, please enter the OTP")]')) {
            $this->logger->info('Security Question', ['Header' => 3]);

            if (!\AwardWallet\Engine\airasia\QuestionAnalyzer::isOtcQuestion($question)) {
                $this->sendNotification("Need to check sq");
            }

            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = 'Question';
            $this->holdSession();

            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Save your details in your airasia Member account")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($message = $this->http->FindSingleNode('//div[@class = "aaw-error-message-content"] | //div[@class = "aaw-alert-message-content"] | //div[contains(text(), "This account will be deleted on")]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Password must contain')
                || strstr($message, 'Sorry, you have entered an invalid email and/or password')
                || strstr($message, 'This account will be deleted on')
                || strstr($message, 'You have entered an incorrect password.')
                || $message == 'Please enter a valid email (e.g: example@email.com)'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'Your log in attempt has been unsuccessful. As a security measure, we’ve locked your accoun')
                || strstr($message, 'Your account has been locked')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (
                strstr($message, 'You already have an account with us.Please log-in using facebook.')
                || strstr($message, 'You already have an account with us.Please log-in using google.')
                || strstr($message, 'You already have an account with us.Additionally, since you have a google')
            ) {
                throw new CheckException('Unfortunately, we are currently do not support Login with Social Media', ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'Sorry, something went wrong. Please try again.') {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'You already have an account with us. Please log-in using ')) {
                throw new CheckException('Sorry, login via Google/Apple is not supported', ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        } else {
            $this->http->GetURL('https://www.airasia.com/en/gb');
            $this->http->GetURL('https://www.airasia.com/rewards/');
            $openMenuBtn = $this->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 12);

            if (!$openMenuBtn) {
                $this->saveResponse();

                return false;
            }

            $openMenuBtn->click();
            $this->waitForElement(WebDriverBy::xpath('//div[@id = "bigMemberPoints"]/p[2]'), 5);
            $this->saveResponse();

            $this->SetBalance($this->http->FindSingleNode('//div[@id = "bigMemberPoints"]/p[2]'));
        }

        $this->setResponseData($this);

        $response = $this->http->JsonLog($this->responseData, 3, true);
        $accessToken = ArrayVal($response, 'accessToken', null);
        $refreshToken = ArrayVal($response, 'refreshToken', null);
        $userId = ArrayVal($response, 'userId', null);

        if (!$accessToken || !$refreshToken || !$userId) {
            $message = ArrayVal($response, 'message');
            $this->logger->error("[Error]: '{$message}'");
            // Sorry, you have entered an invalid email and/or password. Please reconfirm your email and/or password and try again.
            if ($message == 'Invalid Username or Password' || $message == 'User Is Terminated') {
                throw new CheckException("Sorry, you have entered an invalid email and/or password. Please reconfirm your email and/or password and try again.", ACCOUNT_INVALID_PASSWORD);
            }
            // Sorry, you have yet to activate your account. Please activate your account from the email sent to [LOGIN]
            if ($message == 'User Is Not Activated') {
                throw new CheckException("Sorry, you have yet to activate your account. Please activate your account from the email sent to {$this->AccountFields['Login']}", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'User Is Locked') {
                throw new CheckException("Your account has been locked. Contact Customer Support to unlock your account.", ACCOUNT_LOCKOUT);
            }

            if ($message == 'This account is scheduled for deletion.') {
                throw new CheckException("You've requested to delete your account. This account will be deleted.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Endpoint request timed out'
                || $message == 'Exception'
                || $message == "Invalid or missing reCaptcha token"
            ) {
                throw new CheckRetryNeededException();
            }

            // Sorry, you have entered an invalid email and/or password. Please reconfirm your email and/or password and try again.
            if (
                isset($this->http->Response['code'])
                && $this->http->Response['code'] == 405
                // AccountID: 3177465
                && $this->http->FindSingleNode('//div[@class = "message" and contains(text(), "Sorry, your request has been blocked as it may cause potential threats to the server\'s security.")]')
            ) {
                if ($this->AccountFields['Login'] == 'spedder@talk21.com') {
                    throw new CheckException("Sorry, you have entered an invalid email and/or password. Please reconfirm your email and/or password and try again.", ACCOUNT_INVALID_PASSWORD);
                }
                /*
                elseif (in_array($this->AccountFields['Login'], ['eemmssii@gmx.de', 'kai-nickelsen@gmx.de', 'casparschwarz@gmx.de', 'torbenpape@gmx.de'])
                ) {
                */
                throw new CheckException("Server error.", ACCOUNT_PROVIDER_ERROR);
                /*
                }
                */
            }

            if (
                $this->http->Response['code'] == 502
                && $this->http->FindSingleNode('//h2[contains(text(), "The server encountered a temporary error and could not complete your request")]')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($message == 'An invalid response was received from the upstream server') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            // proxy issues
            if (
                strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return false;
        }// if (!$accessToken || !$refreshToken || !$userId)

        $this->setUserData($accessToken, $userId);

        if ($this->userData) {
            return true;
        }

        $headers = [
            "Accept"                 => "*/*",
            "Content-Type"           => "application/json; charset=utf-8",
            "X-Requested-With"       => "XMLHttpRequest",
        ];
        sleep(2);
        $this->browser->GetURL("https://member.airasia.com/login.aspx/GetUserData?userId=%22{$userId}%22&accessToken=%22{$accessToken}%22&refreshToken=%22{$refreshToken}%22&redirectionLocation=%22%22", $headers);
        $response = $this->browser->JsonLog();

        if (isset($response->d) && $response->d == 'profile-landing.aspx') {
            $this->browser->GetURL("https://member.airasia.com/profile-landing.aspx");

            if ($this->http->FindPreg('#/Logout\.aspx\?logout=error#', false, $this->http->currentUrl())) {
                throw new CheckRetryNeededException(3);
            }
        } elseif (isset($response->d) && $response->d == 'Logout.aspx?logout=error') {
            throw new CheckRetryNeededException(3);
        } elseif (isset($response->Message) && $response->Message == "One or more errors occurred.") {
            throw new CheckRetryNeededException(3);
        }

        if ($this->browser->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
            && !strstr($this->browser->currentUrl(), 'forgot-your-password.aspx')
            && !strstr($this->browser->currentUrl(), 'request-activation.aspx')) {
            return true;
        }

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $code = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $input = $this->waitForElement(WebDriverBy::xpath('//input[@id = "text-input--secondFA"]'), 0);

        if (!$input) {
            return false;
        }

        $input->click();
        $input->sendKeys($code);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@value="Submit" and not(contains(@class, "disabled"))]'), 5);
        $this->saveResponse();

        if (!$btn) {
            return false;
        }

        $btn->click();

        sleep(5);
        $btn = $this->waitForElement(WebDriverBy::xpath('//form/div[@class = "validation-summary-errors"]'), 15); // TODO: fake xpath
        $message = $this->http->FindSingleNode('//form/div[@class = "validation-summary-errors"]/ul/li/text()');
        $this->saveResponse();
//
//        if ($message) {
//            $this->logger->error($message);
//
//            if (str_contains($message, '')) {
//                $this->AskQuestion($this->Question, $message, 'Question');
//            }
//
//            return false;
//        }

        return $this->Login();
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);

        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        //$this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($currentUrl);
    }

    public function Parse()
    {
        // Member number
        $this->SetProperty('Number', ArrayVal($this->userData, 'loyaltyId'));
        // Name
        $name = trim(beautifulName(sprintf('%s %s',
            ArrayVal($this->userData, 'firstName'),
            ArrayVal($this->userData, 'lastName')
        )));

        if ($name) {
            $this->SetProperty('Name', $name);
        }
        // Status
        $status = ArrayVal($this->userData, 'loyaltyTier');

        switch ($status) {
            case 'T1':
                $this->SetProperty('Status', 'Black');

                break;

            case 'T2':
                $this->SetProperty('Status', 'Platinum');

                break;

            case 'T3':
                $this->SetProperty('Status', 'Gold');

                break;

            case 'T4':
            case 'T5':
                $this->SetProperty('Status', 'Red');

                break;

            default:
                $this->sendNotification("newStatus: $status");

                break;
        }
        // Balance
        /*
        if (is_numeric($this->userBalance)) {
            $this->SetBalance($this->userBalance);
        } else {
            $response = $this->http->JsonLog(null, 0, true);
            $this->setBalanceBig($this->userBalance);

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                // AccountID: 2717277
                $this->logger->debug(ArrayVal($response, 'resp'));

                if (strstr(ArrayVal($response, 'resp'), ',"message":"Account is Closed/Deactivated"')) {
                    $this->SetBalance(0);
                }
            }
        }
        */
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.airasia.com/member/myorders/en/gb');

        $this->http->FilterHTML = false;
        $responseData = $this->http->FindPreg("/__NEXT_DATA__[^>]*>(\{.+\})<\/script>/");
        $response = $this->http->JsonLog($responseData, 0);

        if (!isset($response)) {
            $this->sendNotification('Json empty // MI');
            $this->logger->error("Json empty");
            $this->logger->debug($response);

            return [];
        }

        foreach ($response->props->pageProps->aggregatorResponse->myOrders->flight as $flight) {
            if (!$this->ParsePastIts && !$flight->isUpcoming) {
                $this->logger->debug('skip past reservation: ' . $flight->refId);

                continue;
            }
            $this->parseItinerary($flight);
        }

        return [];
    }

    private function parseItinerary($data)
    {
        $this->currentItin++;
        $this->logger->info("[{$this->currentItin}] Parse Itinerary #{$data->refId}", ['Header' => 3]);
        $its = $this->itinerariesMaster->getItineraries();
        $restore = false;

        foreach ($its as $it) {
            $objConfs = $it->getConfirmationNumbers();
            $this->logger->debug(var_export($objConfs, true));

            foreach ($objConfs as $itConf) {
                $this->logger->debug('getConfirmationNumbers:');
                $this->logger->debug($data->refId . "==" . $itConf[0]);

                if ($data->refId == $itConf[0]) {
                    $this->logger->notice('Restoring a previously saved flight: ' . $data->refId);
                    $f = $it;
                    $restore = true;

                    break;
                }
            }
        }

        if (!isset($f)) {
            $f = $this->itinerariesMaster->add()->flight();
        }

        $exist = false;

        foreach ($f->getConfirmationNumbers() as $itConf) {
            if ($data->refId == $itConf[0]) {
                $exist = true;
            }
        }

        if (!$exist) {
            $f->general()->confirmation($data->refId);
        }
        $f->general()->status($data->journeyStatus);

        $travellers = [];

        foreach ($data->segments as $segment) {
            $s = $f->addSegment();
            $s->airline()->name($segment->carrierCode);
            $s->airline()->number($segment->flightNumber);
            $s->departure()->code($segment->origin);
            $s->departure()->date2("$segment->departureDateLocal");
            $s->arrival()->code($segment->destination);
            $s->arrival()->date2("$segment->arrivalDateLocal");

            foreach ($segment->passengers as $pax) {
                $travellers[] = "{$pax->firstName} {$pax->lastName}";
            }
        }

        if (!$restore) {
            $f->general()->travellers(array_unique($travellers), true);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }

    private function getApiKeys($origin)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "application/json",
            "Origin"       => $origin,
        ];
        $this->http->RetryCount = 0;
        $this->browser->GetURL("https://ssor.airasia.com/config/v2/clients/by-origin", $headers);
        $response = $this->http->JsonLog(null, 3, true);
        $clientId = ArrayVal($response, 'id', null);
        $apiKey = ArrayVal($response, 'apiKey', null);

        if (!$clientId || !$apiKey) {
            return false;
        }

        return ['clientId' => $clientId, 'apiKey' => $apiKey];
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // System Maintenance In Progress
        if ($message = $this->http->FindSingleNode('//title[contains(text(), "System Maintenance In Progress")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        $this->logger->debug("[CURRENT URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);

        if ($this->http->Response['code'] == 0 || $this->http->FindPreg('/<H1>Server Error in \'\/\' Application\./')
            || ($this->http->Response['code'] == 503 && $this->http->FindPreg('/HTTP Error 503\. The service is unavailable\./'))) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is down for maintenance.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "Our site is down for maintenance.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//img[contains(@alt, 'Self check-in and Member login are currently unavailable.')]/@alt")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg(sprintf('/%s/ims', preg_quote("alert('System exception');")))
            || $this->http->FindPreg("/alert\('The HTTP service located at https:\/\/sso\.airasia\.com\/AdminService\.svc is too busy\.\s*'\);/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function setUserData($accessToken, $userId)
    {
        $this->logger->notice(__METHOD__);
        $this->parseWithCurl();
        $keys = $this->getApiKeys('https://www.airasia.com');
        $headers = [
            'Accept'         => 'application/json',
            'Authorization'  => $accessToken,
            'Content-Type'   => 'application/json',
            'Origin'         => 'https://www.airasia.com',
            'x-aa-client-id' => ArrayVal($keys, 'clientId'),
            'x-api-key'      => ArrayVal($keys, 'apiKey'),
            'x-ga-client-id' => '',
        ];
        $this->http->RetryCount = 0;
        $this->browser->GetURL("https://ssor.airasia.com/um/v2/users/{$userId}", $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 3, true);

        if (ArrayVal($response, 'id') === $userId && $userId) {
            $this->userData = $response;
            /*
                $headers['user-id'] = $userId;
                $this->http->RetryCount = 1;
                $headers['Accept'] = "*
            /*";
                $this->http->GetURL('https://k.apiairasia.com/f_bp', $headers);
                $this->http->RetryCount = 2;
                if (in_array($this->http->Response['code'], [429, 500])) {
                    $this->providerBug = true;
                }
                $response = $this->http->JsonLog(null, 3, true);
                $this->userBalance = ArrayVal($response, 'bigPointsBalance');
            */
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
            $selenium->setKeepProfile(true);

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();

            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL('https://airasia.com/', [], 20);

            $selenium->waitFor(function () use ($selenium) {
                return
                    $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 0)
                    || $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "login-tooltip-button"]'), 0)
                ;
            }, 20);

            $openFormBtn =
                $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 0)
                ?? $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "login-tooltip-button"]'), 0)
            ;

            if (!$openFormBtn) {
                $this->savePageToLogs($selenium);

                if ($this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }
            $openFormBtn->click();

            $login = $selenium->waitForElement(WebDriverBy::id('text-input--login'), 5);
            $pwd = $selenium->waitForElement(WebDriverBy::id('password-input--login'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id('loginbutton'), 0);

            if (!isset($login, $pwd, $btn)) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $pwd->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $btn->click();
            $selenium->waitForElement(WebDriverBy::xpath('
                //input[@id = "text-input--additionalSignupFields-firstName"]
                | //div[@class = "aaw-error-message-content"]
                | //div[@class = "aaw-alert-message-content"]
                | //p[contains(text(), "Save your details in your airasia Member account")]
                | //div[contains(text(), "This account will be deleted on")]
            '), 15);
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//p[contains(text(), "Save your details in your airasia Member account")]')) {
                $this->throwProfileUpdateMessageException();
            }

            if ($message = $this->http->FindSingleNode('//div[@class = "aaw-error-message-content"] | //div[@class = "aaw-alert-message-content"] | //div[contains(text(), "This account will be deleted on")]')) {
                $this->logger->error("[Error]: {$message}");

                if (
                    strstr($message, 'Password must contain')
                    || strstr($message, 'Sorry, you have entered an invalid email and/or password')
                    || strstr($message, 'This account will be deleted on')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    strstr($message, 'Your log in attempt has been unsuccessful. As a security measure, we’ve locked your accoun')
                    || strstr($message, 'Your account has been locked')
                ) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                if (strstr($message, 'You already have an account with us. Please log-in using ')) {
                    throw new CheckException('Sorry, login via Google/Apple is not supported', ACCOUNT_INVALID_PASSWORD);
                }

                $this->setResponseData($selenium);

                $this->DebugInfo = $message;

                return isset($this->responseData);
            }

            $selenium->http->GetURL('https://www.airasia.com/en/gb');
            $openMenuBtn = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "login"]/p'), 12);

            if (!$openMenuBtn) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $openMenuBtn->click();
            $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "bigMemberPoints"]/p[2]'), 5);
            $this->savePageToLogs($selenium);
            $this->SetBalance($this->http->FindSingleNode('//div[@id = "bigMemberPoints"]/p[2]'));
            $this->setResponseData($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return isset($this->responseData);
    }

    private function setResponseData($selenium)
    {
        $this->logger->notice(__METHOD__);

        foreach ($selenium->http->driver->browserCommunicator->getRecordedRequests() as $xhr) {
//                $this->logger->debug("<br>xhr request {$xhr->request->getVerb()} {$xhr->request->getUri()}<br>headers: ".json_encode($xhr->request->getHeaders())."<br>body: ".htmlspecialchars(json_encode($xhr->response->getBody())));
            if (str_starts_with($xhr->request->getUri(), 'https://ssor.airasia.com/sso/v2/authorization/by-credentials') && stripos($xhr->request->getVerb(), 'post') !== false) {
                $this->responseData = json_encode($xhr->response->getBody());
                $this->logger->info('xhr response body: ' . $this->responseData);

                break;
            }
        }
    }
}
