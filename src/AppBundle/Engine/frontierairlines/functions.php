<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFrontierairlines extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    protected $collectedHistory = true;

    private $resp;
    private $error = null;

    public static function GetAccountChecker($accountInfo)
    {
        if (!empty($accountInfo['ConfNo']) && !empty($accountInfo['Code'])) {
            return new static();
        } else {
            require_once __DIR__ . "/TAccountCheckerFrontierairlinesSelenium.php";

            return new TAccountCheckerFrontierairlinesSelenium();
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "https://booking.flyfrontier.com/Member/Profile";
        $arg['SuccessURL'] = "https://booking.flyfrontier.com/Member/Profile";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->LogHeaders = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function delay()
    {
        $delay = rand(1, 10);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Member', [], 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->number, $response->totalMiles, $response->name)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        //$this->http->GetURL("https://f9prodcdn.azureedge.net/media/2244/logo_frontier_ldf.png");
        return $this->selenium();

        $this->http->GetURL("https://www.flyfrontier.com/");
        /*
        // distil script with funcaptcha
        if ($distil = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"10; url=([^<\"]+)/")) {
            $this->logger->debug("distil script, try to recognize captcha.");
            $this->http->NormalizeURL($distil);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            sleep(10);
            $this->http->GetURL($distil);
            $this->http->RetryCount = 2;
        }// if ($distil = $this->http->FindSingleNode("//meta[contains(@src, '/distil_r_blocked.html')]/@src"))
        */
        //$this->distil();

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->http->PostURL('https://booking.flyfrontier.com/api/CorpLogin', [
            'un' => $this->AccountFields['Login'],
            'pw' => $this->AccountFields['Pass'],
        ], [
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
        ]);

        return true;
    }

    public function checkErrors()
    {
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'The ability to sign in is currently unavailable. Please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Server Error in '/' Application
        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error")]')
            || $this->http->FindPreg("/(Server Error in \'\/\' Application)/ims")
            //# Error 404
            || $this->http->FindSingleNode("//h2[contains(text(), '404 - File or directory not found.')]")
            || $this->http->FindPreg("/The ability to sign in is currently unavailable/ims")
            || $this->http->FindPreg("/<div>InternalServerError<\/div>/ims")
            || $this->http->FindPreg('/HTTP Error 503. The service is unavailable\./ims')
            || $this->http->FindPreg('/Internal Server Error/ims')
            || $this->http->FindSingleNode('//h1[contains(text(), "504 Gateway Time-out")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($this->http->FindSingleNode("//img[@src = 'maintenance_message.png']/@src")
            || ($this->http->currentUrl() == 'https://booking.flyfrontier.com/sorry' && $this->http->Response['code'] == 404)) {
            throw new CheckException("Sorry! We are currently down for scheduled maintenance", ACCOUNT_PROVIDER_ERROR);
        }
        // Connection could not be made
//        if ($this->http->FindPreg("/No connection could be made because the target machine actively refused it/")) {
//            throw new CheckException("No connection could be made, please try again later.", ACCOUNT_PROVIDER_ERROR);
//        }
        // retries
//        $this->logger->debug("[CODE]: {$this->http->Response['code']}");
//        if ($this->http->Response['code'] == 0)
//            throw new CheckRetryNeededException(2, 7);

        return false;
    }

    public function Login()
    {
        $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Member');
        $this->resp = $this->http->JsonLog();

        if (isset($this->resp->number, $this->resp->totalMiles, $this->resp->name)) {
            return true;
        }

        return false;
    }

    /*function Login() {
        return true;
//        if (!$this->http->PostForm())
//            return $this->checkErrors();
        if ($this->http->FindPreg('/Please wait just a moment while we validate your browser/'))
            $this->http->GetURL($this->http->currentUrl());
        // Access is allowed
        if (($this->http->FindSingleNode('//div[@id = "accountLoginText" and not(contains(text(), "Account sign in"))]')
            || $this->http->FindSingleNode("//span[contains(text(), 'Member #:')]/span"))
            && is_null($this->error))
//        if ($this->http->FindSingleNode("//a[contains(@href, 'Logout')]/@href"))
            return true;
        // Your e-mail and password did not match an account in our system.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your e-mail and password did not match an account in our system.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Sorry! You entered an email address, EarlyReturns® account number, or password that may be incorrect.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Sorry! You entered an email address, EarlyReturns® account number, or password that may be incorrect.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Sorry! You entered an email address, Early Returns account number or password that may be incorrect.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Sorry! You entered an email address, Early Returns account number or password that may be incorrect.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Your login information is incorrect.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your login information is incorrect.')] | //div[@id = 'js_log_in_errors']/div[contains(text(), 'Your login information is incorrect.')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // Object reference not set to an instance of an object.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Object reference not set to an instance of an object.') or contains(text(), 'Invalid length.')]"))
            throw new CheckException("Your e-mail and password did not match an account in our system.", ACCOUNT_INVALID_PASSWORD);
        // An error has occurred please try your search again.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'An error has occurred please try your search again.')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

//          A potentially dangerous Request.Form value was detected from the client (frontierMemberLogin.Password="**PASSWORD**").
//
//          Missing logon information.
//
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'A potentially dangerous Request.Form value was detected from the client')]"))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        // Password must be at least 8 characters and no more than 16 characters.
        if ($this->http->currentUrl() == 'https://booking.flyfrontier.com/Member/ResetPassword')
            throw new CheckException("Frontier Airlines (EarlyReturns) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        // Sorry! Your account is currently locked.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Sorry! Your account is currently locked.')]", null, true, "/(Sorry! Your account is currently locked\.)/"))
            throw new CheckException($message, ACCOUNT_LOCKOUT);

        // Invalid credentials
        if ($this->http->FindSingleNode("//li[contains(text(), 'An error has occurred please try again.')]")) {
            $data = [
                "un" => $this->AccountFields['Login'],
                "pw" => $this->AccountFields['Pass'],
            ];
            $headers = [
                "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
                "Accept" => "*",
            ];
            $this->http->PostURL("https://www.flyfrontier.com/JsonProxy.ashx?service=CorpLogin", $data, $headers);
            $response = $this->http->JsonLog(null, true, true);
            if (ArrayVal($response, 'error') == "The remote server returned an error: (500) Internal Server Error.")
                throw new CheckException("Sorry! You entered an email address, Early Returns account number or password that may be incorrect. Please check your details and try again.", ACCOUNT_INVALID_PASSWORD);
        }// if ($this->http->FindSingleNode("//li[contains(text(), 'An error has occurred please try again.')]"))

        return $this->checkErrors();
    }*/

    public function Parse()
    {
        // Balance - MY MILES:
        $this->SetBalance($this->resp->totalMiles);
        // Name
        $this->SetProperty('Name', beautifulName($this->resp->name));
        // Level - MY STATUS:
        $this->SetProperty('Level', $this->resp->statusName);

        // Number - Member #
        $this->SetProperty('Number', $this->resp->number);

        $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Customer');
        $response = $this->http->JsonLog();

        if (empty($response)) {
            $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Customer');
            $response = $this->http->JsonLog();
        }

        // NextLevel - Miles to Next Level
        if (isset($response->myMiles->milesToNextStatus)) {
            $this->SetProperty('MilesNextLevel', $response->myMiles->milesToNextStatus);
        }
        // StatusMiles - total status miles:
        if (isset($response->statusQualifyingMiles)) {
            $this->SetProperty('StatusMiles', $response->statusQualifyingMiles);
        }
        // StatusExpiration - expiration:
        if (isset($response->statusExpiration)) {
            $this->SetProperty('StatusExpiration', $response->statusExpiration);
        }

        $this->http->GetURL('https://booking.flyfrontier.com/F9Sessionless/Aggregate');
        //$this->waitForElement(WebDriverBy::xpath("//div[@id='json'] | //pre[not(@id)]"), 5);
        sleep(3);
        $response = $this->http->JsonLog();

        if (isset($response->milesExpiration)) {
            if ($exp = strtotime($response->milesExpiration, false)) {
                $this->SetExpirationDate($exp);
            }
        }

        /*if (isset($response->discountDenData->discountDenPrice))
            $this->SetProperty('Discount', $response->discountDenData->discountDenPrice);*/

        if (isset($response->discountDenData->memberSince)) {
            $this->SetProperty('MemberSince', $response->discountDenData->memberSince);
        }
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flyfrontier.com/travel/my-trips/";
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation Code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $startTimer = $this->getTime();
        $this->http->SetProxy($this->proxyDOP());
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->FindSingleNode('//strong[normalize-space(text())="We\'re currently experiencing server issues. Please try again later."]')) {
            $this->logger->error('Retrying conf url');
            sleep(2);
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }

        if (!$this->http->FindSingleNode("//div[@id = 'checkIn']")) {
            if ($msg = $this->http->FindSingleNode('//strong[normalize-space(text())="We\'re currently experiencing server issues. Please try again later."]')) {
                return $msg;
            }
            $this->sendNotification('failed to retrieve itinerary by conf #');

            return null;
        }

        $this->http->RetryCount = 0;
        $itinUrl = "https://booking.flyfrontier.com/Booking/Retrieve?&ln={$arFields['LastName']}&rl={$arFields['ConfNo']}";
        $this->http->GetURL($itinUrl);

        if ($this->http->FindPreg('/sorry$/', false, $this->http->currentUrl())) {
            sleep(2);
            $this->http->GetURL($itinUrl);
        }
        $this->http->RetryCount = 2;

        if (strpos($this->http->currentUrl(), 'https://validate.perfdrive.com/captcha?') !== false) {
            $this->sendNotification("check captcha, retrieve // ZM");
            $captcha = $this->parseReCaptcha(true,
                '//div[@class="captcha-mid"]/form//div[@class = "g-recaptcha"]/@data-sitekey');

            if ($captcha) {
                $this->postCaptcha($captcha);
            }
        }

        $this->increaseTimeLimit();
        $this->distil();
        $this->distil();
        $this->distil();
        // Sorry! You have entered an incorrect last name or reservation code. Please check your reservation details and try again.
        if ($error = $this->http->FindSingleNode("//div[contains(@class, 'error-msg alert alert-error')]")) {
            return $error;
        }
        // The requested itinerary was not found. Please verify the information and try again.
        if ($error = $this->http->FindSingleNode("//div[contains(@class,'error-msg-alert-error-content')]//div[contains(@class,'ibe-text-small')]")) {
            return $error;
        }
        // Credit card payment is invalid.
        if ($error = $this->http->FindSingleNode('//p[contains(text(), "Credit card payment is invalid.")]')) {
            return $error;
        }

        $confNo = $this->http->FindSingleNode("//span[@class = 'pnr']");

        if ($confNo) {
            $it = $this->ParseItinerary();
        } else {
            $this->sendNotification('failed to retrieve itinerary by conf #');
        }

        if (count($it['TripSegments'] ?? []) === 0) {
            $this->http->GetURL('https://booking.flyfrontier.com/Booking/PrintItinerary');

            if ($this->http->FindPreg('/aspxerrorpath=\/Booking\/PrintItinerary/', false, $this->http->currentUrl())) {
                $it = [];

                return self::CONFIRMATION_ERROR_MSG;
            }
        }

        $this->getTime($startTimer);

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Redeemable"  => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!$this->collectedHistory) {
            return $result;
        }

        $page = 0;
        $endDay = date("d");
        $endMonth = date("m");
        $endYear = date("Y");
        $this->delay();
        $this->http->GetURL("https://booking.flyfrontier.com/Member/SearchHistory?startDay=1&startMonth=1&startYear=2002&StartDate=2002-01-01&endDay=" . intval($endDay) . "&endMonth=" . intval($endMonth) . "&endYear={$endYear}&EndDate={$endYear}-{$endMonth}-{$endDay}");
        $this->distil(false);
        $page++;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//th[contains(text(), 'Redeemable')]/ancestor::tr[1]/following::tbody//tr[td[2]]");
        $this->logger->debug("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            $dateStr = $this->http->FindSingleNode("td[1]", $nodes->item($i));
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->debug("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[2]", $nodes->item($i));
            $result[$startIndex]['Redeemable'] = $this->http->FindSingleNode("td[3]", $nodes->item($i), false, self::BALANCE_REGEXP);
            $startIndex++;
        }

        return $result;
    }

    protected function parseReCaptcha($retry, $xpath = "//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey")
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode($xpath);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        return $captcha;
    }

    protected function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//script[contains(text(), 'loadFunCaptcha')]", null, true, "/public_key\s*:\s*\"([^\"]+)/");
        }

        if (!$key) {
            return false;
        }
        // watchdog workaround
        $this->increaseTimeLimit(180);

        // ANTIGATE version
        $postData = array_merge(
            [
                "type"             => "FunCaptchaTask",
                "websiteURL"       => $this->http->currentUrl(),
                "websitePublicKey" => $key,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // RUCAPTCHA version
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        // $recognizer->RecognizeTimeout = 120;
        // $parameters = [
        //     "method" => 'funcaptcha',
        //     "pageurl" => $this->http->currentUrl(),
        //     "proxy" => $this->http->GetProxy(),
        // ];
        // $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        return $captcha;
    }

    /*private function selenium() {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: '.date('Y/m/d H:i:s').']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $resolutions = [
//                [1152, 864],
//                [1280, 720],
//                [1280, 768],
//                [1280, 800],
//                [1360, 768],
//                [1920, 1080],
//            ];
//            $resolution = $resolutions[array_rand($resolutions)];
//            $this->DebugInfo = "Resolution: ".implode("x", $resolution);
//            $selenium->setScreenResolution($resolution);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.flyfrontier.com/');

            $popup = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'user-not-logged-in')]"), 10);
            if(!$popup)
                return false;
            $popup->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//input[@name='email']"), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//input[@name='password']"), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath("//div[@class='slider-container slider-visible']//div[@name='_Submit']"), 0);
            if (!$loginInput || !$passwordInput || !$button) {
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                //$retry = true;
                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $button->click();
            // Error: Your login information is incorrect. Please check your details and try again or reset your password now.
            if ($message = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'error-message') and contains(text(),'Error: Your login information is incorrect. Please check your details and try again or')]"), 5))
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie)
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
            $login = true;
        }
        catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer'))
                $retry = true;
        }
        catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache'))
                $retry = true;
        }
        catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'TypeError: document.body is null Build info'))
                $retry = true;
        }
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");
                throw new CheckRetryNeededException(3, 5);
            }
        }
        $this->getTime($startTimer);
        if (!is_null($this->error)) {
            $this->logger->error($this->error);
            // Your login information is incorrect. Please check your details and try again or reset your password now
            if (strstr($this->error, 'Your login information is incorrect.')
                // Invalid length.
                || $this->error == 'Invalid length.')
                throw new CheckException($this->error, ACCOUNT_INVALID_PASSWORD);
            // Sorry! Your account is currently locked. Please click here to reset your password
            if (strstr($this->error, 'Your account is currently locked'))
                throw new CheckException($this->error, ACCOUNT_LOCKOUT);
            // Instructions on how to reset your password
            if ($this->error == 'Instructions on how to reset your password')
                throw new CheckException("Frontier Airlines (EarlyReturns) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            // Unable to log in.
            if ($this->error == 'Unable to log in.')
                throw new CheckException($this->error, ACCOUNT_PROVIDER_ERROR);

            $this->DebugInfo = $this->error;
        }

        return $login;
    }*/

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();
        $login = false;
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->DebugInfo = "Resolution: " . implode("x", $resolution);
            $selenium->setScreenResolution($resolution);

            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_53);
            // debug
//            $selenium->http->saveScreenshots = true;

            $selenium->disableImages();

            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $selenium->useCache();
            }
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.flyfrontier.com/');

            $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "accountLoginText"] | //h2[contains(text(), "Pardon Our Interruption ...")]'), 10);

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $loginlayer = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "accountLoginText"]'), 0);
            // funCaptcha in selenium
            if (!$loginlayer && $selenium->waitForElement(WebDriverBy::id('distilCaptchaForm'), 0)) {
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                $complete = $selenium->waitForElement(WebDriverBy::id('dCF_input_complete'), 0);
                // load jq
                $selenium->driver->executeScript("
                    var jq = document.createElement('script');
                    jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
                    document.getElementsByTagName('head')[0].appendChild(jq);
                ");
                $captcha = $this->parseFunCaptcha(false);

                if ($captcha === false) {
                    $retry = true;

                    return false;
                }
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                $selenium->driver->executeScript("$('form#distilCaptchaForm input[name = \"fc-token\"]').val(\"" . $captcha . "\");");

                if ($complete) {
                    $complete->click();
                } else {
                    $selenium->driver->executeScript("$('form#distilCaptchaForm').submit();");
                }

                $loginlayer = $selenium->waitForElement(WebDriverBy::id('lid-loginlayer-toggle'), 10);
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
            }

            if (!$loginlayer) {
                $this->logger->error("form not found");
                $this->DebugInfo = "form not found";
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                $this->checkErrors();

                $attempt = 0;

                do {
                    $this->logger->notice("Attempt #{$attempt}");
                    $attempt++;
                    $selenium->http->GetURL('https://www.flyfrontier.com/');
                    $loginlayer = $selenium->waitForElement(WebDriverBy::id('lid-loginlayer-toggle'), 10);
                    // save page to logs
                    $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                    $this->http->SaveResponse();
                } while (!$loginlayer && $attempt < 2);

                if (!$loginlayer) {
                    $retry = true;

                    return false;
                }
            }// if (!$loginlayer)
            $loginlayer->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::id('login_email'), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('login_password'), 0);

            if (!$loginInput || !$passwordInput) {
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
                $retry = true;

                return false;
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $button = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'login_button']"), 3);

            if (!$button) {
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                return false;
            }
            $button->click();

            $sleep = 30;
            $startTime = time();

            while (((time() - $startTime) < $sleep) && !$login) {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

                if ($selenium->waitForElement(WebDriverBy::xpath('//div[@id = "accountLoginText" and not(contains(., "Account"))]'), 0)) {
                    $login = true;
                    // save page to logs
                    $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                    $this->http->SaveResponse();
                    $selenium->http->GetURL("https://booking.flyfrontier.com/Member/Profile");
                    $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Member #:')]/span | //p[contains(text(), 'Something went wrong')]"), 7);

                    if ($selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Something went wrong')]"), 0)) {
                        $selenium->http->GetURL("https://booking.flyfrontier.com/Member/Profile");
                        $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Member #:')]/span"), 7);
                    }

                    $this->increaseTimeLimit(300);
                    // save page to logs
                    $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                    $this->http->SaveResponse();

                    break;
                }

                if ($message = $selenium->waitForElement(WebDriverBy::id('LoginError'), 0)) {
                    $login = true;
                    $this->error = $message->getText();

                    break;
                }
                // https://booking.flyfrontier.com/Member/ResetPassword?un=...
                if ($selenium->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Instructions on how to reset your password")]'), 0)
                    && ($selenium->http->currentUrl() == 'https://booking.flyfrontier.com/Member/ResetPassword?un=' . $this->AccountFields['Login'])) {
                    $login = true;
                    $this->error = 'Instructions on how to reset your password';

                    break;
                }
            }

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Reached error page: about:neterror')) {
                $retry = true;
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'Element not found in the cache')) {
                $retry = true;
            }
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'TypeError: document.body is null Build info')) {
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 5);
            }
        }
        $this->getTime($startTimer);

        if (!is_null($this->error)) {
            $this->logger->error($this->error);
            // Your login information is incorrect. Please check your details and try again or reset your password now
            if (strstr($this->error, 'Your login information is incorrect.')
                // Invalid length.
                || $this->error == 'Invalid length.') {
                throw new CheckException($this->error, ACCOUNT_INVALID_PASSWORD);
            }
            // Sorry! Your account is currently locked. Please click here to reset your password
            if (strstr($this->error, 'Your account is currently locked')) {
                throw new CheckException($this->error, ACCOUNT_LOCKOUT);
            }
            // Instructions on how to reset your password
            if ($this->error == 'Instructions on how to reset your password') {
                throw new CheckException("Frontier Airlines (EarlyReturns) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            }
            // Unable to log in.
            if ($this->error == 'Unable to log in.') {
                throw new CheckException($this->error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $this->error;
        }

        return $login;
    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->parseGeetestCaptcha($retry);
            $this->http->RetryCount = 2;
        } // if (isset($distilLink))

        if ($this->http->FindSingleNode('//form[@id = "distilCaptchaForm" and contains(@class, "geetest_hard")]/@action')) {
            $this->parseGeetestCaptcha($retry);
        }

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);

        if ($captcha !== false) {
            $key = 'fc-token';
        } elseif (($captcha = $this->parseReCaptcha($retry)) !== false) {
            $key = 'g-recaptcha-response';
        } else {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue($key, $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        return true;
    }

    private function parseGeetestCaptcha($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $this->http->brotherBrowser($http2);
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        if (!$challenge) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $response = $this->http->JsonLog($captcha, true, true);

        if (empty($response)) {
            $this->logger->error('geetest failed');

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $response['geetest_challenge'],
            'geetest_validate'  => $response['geetest_validate'],
            'geetest_seccode'   => $response['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    /*function Parse2() {
        $this->increaseTimeLimit(120);
        if ($this->http->currentUrl() != 'https://booking.flyfrontier.com/Member/Profile') {
            $this->delay();
            $this->http->GetURL('https://booking.flyfrontier.com/Member/Profile');
            $this->distil();
        }
        // Balance - Available Miles
        $this->SetBalance($this->http->FindSingleNode("//label[@class = 'mem-basic-points']"));
        if (!isset($this->Balance))
            $this->SetBalance($this->http->FindSingleNode("//span[@class = 'mem-basic-points']"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//label[@class = 'mem-basic-name']")));
        if (!ArrayVal($this->Properties, 'Name'))
            $this->SetProperty('Name', $this->http->FindSingleNode("//span[@class = 'mem-basic-name-like-label']"));
        // Status
        $this->SetProperty("Level", $this->http->FindSingleNode("//span[contains(text(), 'Status:')]/span"));
        // Member #
        $this->SetProperty("Number", $this->http->FindSingleNode("//span[contains(text(), 'Member #:')]/span"));
        // Elite Qualifying Miles YTD
        $this->SetProperty("YTDQualifyingMiles", $this->http->FindSingleNode("//span[contains(text(), 'Elite Qualifying Miles YTD:')]/span"));
        // Qualifying Status Segments Earned (YTD)
        $this->SetProperty("YTDQualifyingSegments", $this->http->FindSingleNode("//span[contains(text(), 'Elite Qualifying Segments YTD:')]/span"));

        // Expiration Date  // refs #7542 <- provider has a bug so see refs #12207
        $exp = $this->http->FindSingleNode("//span[contains(text(), 'Expiration:')]/span");
        $this->logger->notice("Exp. date from profile: ".$exp);


        // Expiration Date  // refs #12207
        $endDay = date("j");
        $endDay2 = date("d");
        $endMonth = date("n");
        $endMonth2 = date("m");
        $endYear = date("Y");

        $startDate = strtotime("-6 month");
        $startDay = date("j", $startDate);
        $startDay2 = date("d", $startDate);
        $startMonth = date("n", $startDate);
        $startMonth2 = date("m", $startDate);
        $startYear = date("Y", $startDate);

        $this->delay();
        $this->http->GetURL("https://booking.flyfrontier.com/Member/SearchHistory?startDay={$startDay}&startMonth={$startMonth}&startYear={$startYear}&StartDate={$startYear}-{$startMonth2}-{$startDay2}&endDay={$endDay}&endMonth={$endMonth}&endYear={$endYear}&EndDate={$endYear}-{$endMonth2}-{$endDay2}");
        $this->distil();
        $nodes = $this->http->XPath->query("//table[@class = 'mem-my-loyalty-table']//tr[td]");
        $this->logger->debug("Total {$nodes->length} nodes were found");
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $date = $this->http->FindSingleNode("td[1]", $node);
            $description = $this->http->FindSingleNode("td[2]", $node);
            $miles = $this->http->FindSingleNode("td[3]", $node, true, "/(.+)MI/");
            if (strtolower($description) == 'standard accrual' && $miles > 0 && strtotime($date)) {
                // Last Activity
                $this->SetProperty("LastActivity", $date);
                // Exp date
                $this->SetExpirationDate(strtotime("+ 6 month", strtotime($date)));
                break;
            }// if (strtolower($description) == 'standard accrual' && $miles > 0 && strtotime($date))
        }// for ($i = 0; $i < $nodes->length; $i++)

        // provider bug
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->currentUrl() == "https://booking.flyfrontier.com/Flight/Internal") {
            $this->logger->notice("provider bug fix, try to parser properties from main page");
//            $this->http->GetURL('https://www.flyfrontier.com/');
            $data = [
                "pw" => $this->AccountFields['Pass'],
                "un" => $this->AccountFields['Login'],
            ];
            $headers = [
                "Accept" => "*",
                "Content-Type" => "application/x-www-form-urlencoded; charset=UTF-8",
                "X-NewRelic-ID" => "VQICWFdWDhACXVBVBwkBXw==",
                "X-Requested-With" => "XMLHttpRequest",
                "X-Session-Id" => "1kvhf503egx3wwlbbbkefek2",
                "X-Session-Sig" => "9daedb0e1b32bdb885751da5de09b5138f2f3734d173c4ed0512a99a157e6b26"
            ];
            $this->http->PostURL("https://www.flyfrontier.com/JsonProxy.ashx?service=CorpLogin", $data, $headers);
            $response = $this->http->JsonLog(null, true, true);
            $corpPersonResponse = ArrayVal($response, 'corpPersonResponse');
            $name = ArrayVal($corpPersonResponse, 'name');

            // Balance - Available Miles
            $this->SetBalance(ArrayVal($corpPersonResponse, 'pointBalance'));
            // Name
            $this->SetProperty("Name", beautifulName(ArrayVal($name, 'first')." ".ArrayVal($name, 'last')));
            // Member #
            $this->SetProperty("Number", ArrayVal($corpPersonResponse, 'programNumber'));
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->currentUrl() == "https://booking.flyfrontier.com/Flight/Internal")
    }*/

    /*function ParseItineraries() {
        if ($this->http->currentUrl() != 'https://booking.flyfrontier.com/Member/Profile') {
            $this->delay();
            $this->http->GetURL('https://booking.flyfrontier.com/Member/Profile');
            $this->distil();
        }

        $nodes = $this->http->XPath->query("//a[contains(text(), 'Flight Options')]/ancestor::li[@class = 'mem-my-trips-trip-summary']");
        $this->logger->debug('Total: ' . $nodes->length . ' itineraries were found');
        // no Itineraries
        if ($this->http->FindSingleNode("//div[@id = 'myTrips']", null, true, "/(You have no upcoming flights)/ims")
            || $this->http->FindSingleNode("//div[@id = 'myTrips']") === '')
            return $this->noItinerariesArr();

        $result = array();
        for ($i = 0; $i < $nodes->length; $i++) {
            $node = $nodes->item($i);
            $result[] = $this->ParseItinerary($node);
        }

        return $result;
    }

    private function ParseItinerary($node) {
        // @var \AwardWallet\ItineraryArrays\AirTrip $result
        $result = Array();

        # Air Trip

        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode(".//span[@class = 'record-locator']", $node);
        if (!empty($result['RecordLocator'])) {
            $http2 = clone $this->http;
            $http2->GetURL("https://booking.flyfrontier.com/Booking/Manage?state=Passenger&rl=".$result['RecordLocator']);
            $accountNodes = $http2->FindNodes("//input[contains(@name, '].CustomerNumber')]/@value");
            $accountNumbers = [];
            foreach ($accountNodes as $account) {
                if (!empty($account))
                    $accountNumbers[] = $account;
            }
            $accountNumbers = implode(', ', $accountNumbers);
        }
        // AccountNumbers
        $result['AccountNumbers'] = isset($accountNumbers) ? $accountNumbers : null;
        // Passengers
        $passengers = [];

        # Air Trip Segments

        $halfs = $this->http->XPath->query(".//div[@class = 'group']", $node);
        $this->logger->debug("Found " . $halfs->length . " halfs");
        for ($i = 0; $i < $halfs->length; $i++) {
            $tripSeg = array();
            // FlightNumber
            $tripSeg['FlightNumber'] = str_replace('F9 ', '',$this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[1]", $halfs->item($i)));
            // DepCode
            $tripSeg['DepCode'] = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[2]/div[2]", $halfs->item($i), true, '/([A-Z]{3})/');
            // DepName
            $tripSeg['DepName'] = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[2]/div[2]", $halfs->item($i), true, '/[A-Z]{3}\,\s*([^<]+)/');
            // ArrCode
            $tripSeg['ArrCode'] = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[3]/div[2]", $halfs->item($i), true, '/([A-Z]{3})/');
            // ArrName
            $tripSeg['ArrName'] = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[3]/div[2]", $halfs->item($i), true, '/[A-Z]{3}\,\s*([^<]+)/');
            // DepDate
            $date = $this->http->FindSingleNode("div[1]", $halfs->item($i), true, "/,\s*([^<]+)/");
            // DepTime
            $depTime = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[2]/div[1]", $halfs->item($i));
            $this->logger->debug("Dep time: $date $depTime");
            $depDateTime = strtotime($date . ' ' . $depTime);
            if ($depDateTime)
                $tripSeg['DepDate'] = $depDateTime;
            // ArrTime
            $arrTime = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[3]/div[1]", $halfs->item($i));
            $this->logger->debug("Arr time: $date $arrTime");
            $arrDateTime = strtotime($date . ' ' . $arrTime);
            if ($arrDateTime)
                $tripSeg['ArrDate'] = $arrDateTime;
            // Duration
            $tripSeg['Duration'] = $this->http->FindSingleNode("following-sibling::table[position() = 1 and @class = 'my-trips-table-details']//tr[td]/td[4]", $halfs->item($i));
            // Seats
            $tripSeg['Seats'] = implode(', ', $this->http->FindNodes("following-sibling::table[position() = 2 and @class = 'my-trips-table-details']//tr[td]/td[2]", $halfs->item($i)));
            // Passengers
            $passengers = array_merge($passengers, $this->http->FindNodes("following-sibling::table[position() = 2 and @class = 'my-trips-table-details']//tr[td]/td[1]", $halfs->item($i)));
            $result['TripSegments'][] = $tripSeg;
        }
        $result['Passengers'] = array_map(function($item) {
            return beautifulName($item);
        }, $passengers);
        $result['Passengers'] = array_unique($result['Passengers']);

        return $result;
    }*/

    private function ParseItinerary(): array
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $result['RecordLocator'] = $this->http->FindSingleNode("//span[contains(text(),'Trip Confirmation Number:')]/following-sibling::span[@class='pnr']");

        $flights = $this->xpathQuery("//div[@class='itin-flights']//div[contains(@class,'itin-flight itin-body')]");

        foreach ($flights as $flight) {
            // Departure: Friday June 29, 2018
            $date = $this->http->FindSingleNode(".//span[contains(text(),'Departure: ') or contains(text(),'Return: ')]", $flight, false, '/:\s*(\w+ \w+.+)/');
            $this->logger->debug("Departure: {$date}");
            $date = strtotime($date, false);

            $its = $this->xpathQuery(".//div[@class='table-row' and @scope='row']", $flight);
            $this->logger->debug("Found {$its->length} itineraries");

            foreach ($its as $node) {
                $seg = [];
                // AirlineName
                $seg['AirlineName'] = $this->http->FindSingleNode(".//div[@headers='Flight']", $node, false, '/([A-Z\d]+)\s+\d+/');
                // FlightNumber
                $seg['FlightNumber'] = $this->http->FindSingleNode(".//div[@headers='Flight']", $node, false, '/[A-Z\d]+\s+(\d+)/');

                // Depart
                $seg['DepDate'] = strtotime($this->http->FindSingleNode(".//div[@headers='Depart']/span[1]", $node), $date);
                $depart = $this->http->FindSingleNode(".//div[@headers='Depart']/span[2]", $node);
                $seg['DepName'] = $this->http->FindPreg('/^(.+?)\s+\([A-Z]{3}\)/', false, $depart);
                $seg['DepCode'] = $this->http->FindPreg('/\s+\(([A-Z]{3})\)/', false, $depart);
                // Arrive
                $seg['ArrDate'] = strtotime($this->http->FindSingleNode(".//div[@headers='Arrive']/span[1]", $node), $date);
                $arrival = $this->http->FindSingleNode(".//div[@headers='Arrive']/span[2]", $node);
                $seg['ArrName'] = $this->http->FindPreg('/^(.+?)\s+\([A-Z]{3}\)/', false, $arrival);
                $seg['ArrCode'] = $this->http->FindPreg('/\s+\(([A-Z]{3})\)/', false, $arrival);

                // Duration - 3hr 20min
                $seg['Duration'] = $this->http->FindSingleNode(".//div[@headers='Duration']/span[1]", $node);

                if ($this->http->FindPreg('/Non\s*Stop/i', false, $this->http->FindSingleNode(".//div[@headers='Duration']/span[2]", $node))) {
                    $seg['Stops'] = 0;
                }

                $result['TripSegments'][] = $seg;
            }
        }

        $passengers = $this->xpathQuery("//div[contains(@class,'passengers-details-column')]");
        $result['Passengers'] = [];
        $result['AccountNumbers'] = [];

        foreach ($passengers as $node) {
            $result['Passengers'][] = beautifulName($this->http->FindSingleNode(".//span[@class='passenger-name']", $node));
            $result['AccountNumbers'][] = $this->http->FindSingleNode(".//span/i[contains(text(),'Frontier Miles Number:')]/following-sibling::text()", $node, false, '/^\s*(\w+)\s*$/');
        }

        $result['TotalCharge'] = round(array_sum($this->http->FindNodes("//span/b[contains(text(),'Total:')]/following-sibling::text()", null, '/\$([\d+.,]+)/')), 2);
        $result['Currency'] = str_replace('$', 'USD', $this->http->FindSingleNode("(//span/b[contains(text(),'Total:')]/following-sibling::text())[1]", null, false, '/(\$)[\d+.,]+/'));
        $result['SpentAwards'] = $this->http->FindSingleNode("//span/b[contains(text(),'Total:')]/following-sibling::text()[contains(., 'MI')]", null, '/MI\s*([\d+.,]+)/');
        $result['Tax'] = $this->http->FindPreg('/"originTaxesAndFees": ([\d\.]+)00\,/');

        return $result;
    }

    private function xpathQuery(string $query, ?DomNode $parent = null): DOMNodeList
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info("found {$res->length} nodes: {$query}");

        return $res;
    }

    private function postCaptcha($captcha): void
    {
        $this->logger->notice(__METHOD__);
        $this->http->ParseForm(null, "//div[@class='captcha-mid']/form");
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('recaptcha_response', '');
        $headers = [
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Origin'          => 'https://validate.perfdrive.com',
            'Referer'         => $this->http->currentUrl(),
        ];
        $this->http->PostForm($headers);
    }
}
