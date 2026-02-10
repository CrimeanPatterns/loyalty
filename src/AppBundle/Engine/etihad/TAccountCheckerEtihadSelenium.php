<?php

// Is extended in TAccountCheckerEtihadbusiness
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEtihadSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;
    protected $host = 'www.etihadguest.com';
    private $ip = '0.0.0.0';

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;
        $this->useFirefox();
        $this->setKeepProfile(true);

        if (
            isset($this->AccountFields['Login'])
            && in_array($this->AccountFields['Login'], [
                '700000092503',
                '700000061386',
                'saleem@fortunemetals.com',
                '700000061283',
            ])
        ) {
            $this->http->SetProxy($this->proxyReCaptcha());
        } else {
            $this->http->SetProxy($this->proxyStaticIpDOP());
        }

        $this->disableImages();
        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://{$this->host}/en/my-account/activity-history.html");
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $error = $this->driver->switchTo()->alert()->getText();
            $this->logger->debug("alert -> {$error}");
            $this->driver->switchTo()->alert()->accept();
            $this->logger->debug("alert, accept");
        }// catch (UnexpectedAlertOpenException $e)
        catch (NoAlertOpenException $e) {
            $this->logger->debug("no alert, skip");
        }

        try {
            if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 5)) {
                return true;
            }
            $this->saveResponse();
            $this->checkProviderErrors();
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://{$this->host}/en/login-standalone");
        } catch (TimeOutException | ScriptTimeoutException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        }

        try {
//            $this->http->GetURL("https://{$this->host}");
            /*
            $loginLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(@class, "join-link")]'), 10, false);
            if ($loginLink) {
                $this->driver->executeScript("
                    $('a.join-link').click();
                ");
            }
            */

            $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'emailOrGuestNumber']"), 5);
            $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginPass']"), 5);

            // debug
            if ($this->waitForElement(WebDriverBy::xpath("//img[contains(@class, 'eyg-loader')]"), 0)) {
                $this->saveResponse();
                $this->logger->notice("increase delay");
                $delay = 0;

                while (
                    $this->waitForElement(WebDriverBy::xpath("//img[contains(@class, 'eyg-loader')]"), 0)
                    && $delay < 15
                ) {
                    $delay++;
                    $this->saveResponse();
                }
                $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'emailOrGuestNumber']"), 0);
                $pass = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'loginPass']"), 0);
            }

            if (!$login || !$pass) {
                $this->logger->error("something went wrong");

                if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 0)) {
                    return true;
                }

                return $this->checkErrors();
            }
            $this->saveResponse();
            $login->click();
            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->click();
            $pass->clear();
            $pass->sendKeys(html_entity_decode($this->AccountFields['Pass']));
            sleep(2);
            $submitButton = $this->waitForElement(WebDriverBy::id("submitLogin"), 5);

            if (!$submitButton) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }
            $this->saveResponse();
            $submitButton->click();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                throw new CheckRetryNeededException(3, 10);
            }
        } catch (UnrecognizedExceptionException $e) {
            $this->logger->error("UnrecognizedExceptionException exception: " . $e->getMessage());
            // retries
            if (
                strstr($e->getMessage(), 'is not reachable by keyboard')
                || strstr($e->getMessage(), 'could not be scrolled into view')
                || strstr($e->getMessage(), 'is not clickable at point')
            ) {
                throw new CheckRetryNeededException(3);
            }
        } catch (ElementNotVisibleException $e) {
            $this->logger->error("ElementNotVisibleException exception: " . $e->getMessage());
            // retries
            throw new CheckRetryNeededException(3, 10);
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // maintenance
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //h2[contains(text(), 'Scheduled Maintenance')]/ancestor::div[1]//text()[contains(.,'We are currently undergoing a planned maintenance on our site')]
                | //p[contains(.,'We are experiencing technical difficulties and hence site login will not be functional.')]
                | //p[contains(text(), 'The Etihad Guest website is currently undergoing a planned maintenance')]
                | //p[contains(text(), 'The Etihad Guest website will be undergoing a planned maintenance')]
                | //p[contains(text(), 'The Etihad Guest website is undergoing planned maintenance')]
                | //p[contains(text(), 'Our website is undergoing a planned maintenance')]
                | //p[contains(text(), 'Our website will be undergoing a planned maintenance')]
                | //p[contains(text(), 'Error! We are currently experiencing technical difficulties.')]
                | //p[contains(text(), 'We are currently undergoing a planned maintenance.')]
                | //p[contains(text(), 'Error! Sorry, we could not process your request. Please try again after later.')]
                | //strong[contains(text(), 'We’re unable to process your request at the moment')]
            "), 1)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//p[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->logger->notice(__METHOD__);
        $sleep = 50;
        $startTime = time();
        sleep(5);

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            try {
                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 0);
                $this->saveResponse();
            } catch (StaleElementReferenceException $e) {
                $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("finally");
                $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 0);
                $this->saveResponse();

                if ($logout && !$this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Error! Please update your email address') or contains(text(), 'Error! Please update your email id')]"), 0)) {
                    // Your session has expired
                    $this->waitForElement(WebDriverBy::xpath('//span[@data-ng-bind="::accSummeryModel.accdata.memberNumber"]'), 5);
                    $this->saveResponse();

                    return true;
                }

                try {
                    $this->checkProviderErrors();
                    sleep(1);
                    // it's works!
                    // OTP
                    if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'One time password is sent to your email id. Please verify.')]"), 0)) {
                        $this->saveResponse();

                        return $this->processSecurityCheckpoint();
                    }// if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'One time password is sent to your email id. Please verify.')]"), 0))
    //                $this->saveResponse();
                } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                }
            }
        }
        $this->logger->debug("time out");
        // debug
        if ($this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 10)) {
            $this->saveResponse();

            return true;
        }
        // Error! Sorry, your email address or membership number is incorrect. (AccountID: 1720597, 613884)
        if (!is_numeric($this->AccountFields['Login']) && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Error! Sorry, your email address or membership number is incorrect.', ACCOUNT_INVALID_PASSWORD);
        }
        // error not shown on the site (error catched from json)
        if (in_array($this->AccountFields['Login'], ['106838459173', '106829770143', '100273438856', '100017642233'])) {
            throw new CheckException("Your account is locked.", ACCOUNT_LOCKOUT);
        }
        // error not shown on the site (error catched from json)
        if (in_array($this->AccountFields['Login'], ['shawnalstout@gmail.com'])) {
            throw new CheckException("Your account is not active. Please click on the link received in email to activate your account.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'One time password is sent to your email id. Please verify.')]"), 0);
        $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name = "otp"]'), 0);
        $button = $this->waitForElement(WebDriverBy::id('OTPDetails'), 0);
        $this->saveResponse();

        if (!$question || !$otp || !$button) {
            return false;
        }
        $this->holdSession();

        if ($question && !isset($this->Answers[$question->getText()])) {
            $this->AskQuestion($question->getText(), null, 'Question');

            return false;
        }
        $otp->clear();
        $otp->sendKeys($this->Answers[$question->getText()]);
        unset($this->Answers[$question->getText()]);
        $this->logger->debug("click button...");
        $button->click();

        sleep(10);

        // refs #19092
        $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')] | //p[contains(text(), 'Error! You have entered an incorrect one-time password.') or contains(text(), 'Error! You have reached the maximum number of login attempts.') or contains(text(), 'Error! The one-time password provided is invalid.')]"), 3);
        $this->saveResponse();

        // OTP entered is incorrect
        $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Error! You have entered an incorrect one-time password.') or contains(text(), 'Error! You have reached the maximum number of login attempts.') or contains(text(), 'Error! The one-time password provided is invalid.')]"), 0);

        if (!$error && $this->waitForElement(WebDriverBy::xpath("//img[contains(@class, 'eyg-loader')]"), 0)) {
            $this->logger->notice("increase delay");
            $error = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Error! You have entered an incorrect one-time password.') or contains(text(), 'Error! You have reached the maximum number of login attempts.')]"), 5);
        }
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $otp->clear();
            $this->AskQuestion($question->getText(), $error->getText(), 'Question');

            return false;
        }
        $this->logger->debug("success");
        sleep(3);
        // Access is allowed
        $logout = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Logout')]"), 0);
        $this->saveResponse();

        // provider bug fix, sometimes site ask OTP twice
        if (!$logout && $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Please enter in the one-time password sent to your email address.')]"), 0)) {
            $this->logger->notice("provider bug fix, ask OTP second time");
            $question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'One time password is sent to your email id. Please verify.')]"), 0);
            $otp = $this->waitForElement(WebDriverBy::xpath('//input[@name = "otp"]'), 0);
            $button = $this->waitForElement(WebDriverBy::id('OTPDetails'), 0);
            $this->saveResponse();

            if (!$question || !$otp || !$button) {
                return false;
            }
            $this->holdSession();

            if ($question && !isset($this->Answers[$question->getText()])) {
                $this->AskQuestion($question->getText(), null, 'Question');

                return false;
            }
        }
        $this->checkProviderErrors();

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
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();
        $cookiesArrGeneral = [];

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            $cg = "document.cookie=\"{$cookie['name']}={$cookie['value']}; path={$cookie['path']}; domain={$cookie['domain']}\";";
            $cookiesArrGeneral[] = $cg;
        }
        $this->logger->debug("==============================");
        $this->logger->debug(var_export(implode(' ', $cookiesArrGeneral), true));
        $this->logger->debug("==============================");

        $this->browser->setHttp2(true);
        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->RetryCount = 1;
        $this->browser->GetURL($this->http->currentUrl(), [], 30);
        $this->browser->RetryCount = 2;
    }

    public function Parse()
    {
        $this->browser = $this->http;
        // use curl
        $this->parseWithCurl();

        if ($this->browser->Response['code'] == 403) {
            if ($message = $this->browser->FindSingleNode('//strong[contains(text(), "We’re unable to process your request at the moment")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }

//        $this->browser->GetURL("https://{$this->host}/en/my-account/activity-history.html?_linkNav=Activity_history");
        $ip = explode(":", $this->http->GetProxy());
        $this->ip = "{$ip[0]}";
        $data = [
            "securityContext" => [
                "oAuth"      => null,
                "ipType"     => "MEMBER",
                "macAddress" => null,
                "otp"        => null,
                "ipAddress"  => $this->ip,
            ],
            "requestHeader" => [
                "screenName" => null,
            ],
            "userCtx"      => ["languageCode" => "en_us"],
            "customerInfo" => [
                "userId" => null,
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ];

        $host = $this->http->getCurrentHost();
        $this->logger->debug("[Host]: {$host}");

        switch ($host) {
            // Business account, AccountID: 3417675
            case 'www.eapbusinessconnect.com':
                $this->host = 'www.eapbusinessconnect.com';

                break;
            // AccountID: 2227509
            case 'www.etihadbusinessconnect.com':
                $this->host = 'www.etihadbusinessconnect.com';

                break;

            case 'www.etihadguest.com':
                $this->host = 'www.etihadguest.com';

                break;

            default:
                $this->logger->debug("[Default Host]: {$this->host}");
        }
        // Business account, AccountID: 4419741

        $this->browser->PostURL("https://{$this->host}/services/dynamic/glc/account/private/v1/get-summary", json_encode($data), $headers);

        if ($this->browser->Response['code'] == 404 && empty($this->browser->Response['body'])) {
            $this->browser->PostURL("https://{$this->host}/services/dynamic/glc/profile/private/v1/get-customerdata", json_encode($data), $headers);
        }

        // provider bug fix
        if ($this->browser->FindPreg("/\"soaperror\":\{\"errCode\":\"WAS100F\",\"errDesc\":\"java.net.ConnectException: HTTP \( 404 \) Not Found address/")) {
            $this->browser->PostURL("https://{$this->host}/services/dynamic/glc/account/private/v1/get-summary", json_encode($data), $headers);
        }

        $response = $this->browser->JsonLog(null, 3, true, 'personalDetails');
        $personalDetails = ArrayVal($response, 'personalDetails', []);
        // Name
        $name = ArrayVal($personalDetails, 'firstName') . " " . ArrayVal($personalDetails, 'lastName');
        $this->SetProperty("Name", beautifulName($name));
        // Balance - Guest Miles
        $this->SetBalance(ArrayVal($personalDetails, 'guestmiles', ArrayVal($personalDetails, 'guestMiles')));

        // session lost workaround
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $errDesc = ArrayVal(ArrayVal($response, 'soaperror'), 'errDesc');

            if ($errDesc == 'Your session has expired') {
                throw new CheckRetryNeededException(3, 3);
            }
        }

        // No:
        $this->SetProperty("Number", ArrayVal($personalDetails, 'memberNumber'));
        // Tier
        $this->SetProperty("TierLevel", ArrayVal($personalDetails, 'memberTierName'));
        // Since
        $this->SetProperty("Since", ArrayVal($personalDetails, 'memberSince'));
        // Tier miles
        $this->SetProperty("TierMiles", ArrayVal($personalDetails, 'currentTierMiles'));
        // Miles needed to next tier
        $this->SetProperty("TierMilesNeed", ArrayVal($personalDetails, 'tierMilesRequiredToUpgrade'));
        // Guest Tier Segments
        $this->SetProperty("TierSegments", ArrayVal($personalDetails, 'currentTierSegments'));
        // Segments needed to next tier
        $this->SetProperty("TierSegmentsNeed", ArrayVal($personalDetails, 'tierSegmentsRequiredToUpgrade'));
        // Mileage expiry
        $milesExpiryEarlyDate = $this->ModifyDateFormat(ArrayVal($personalDetails, 'milesExpiryEarlyDate', null));

        $milesExpiryEarlyPoint = ArrayVal($personalDetails, 'milesExpiryEarlyPoint');
        $this->logger->notice("[Exp date]: {$milesExpiryEarlyDate}, miles: {$milesExpiryEarlyPoint}");

        $milesExpiringAfter12Months = ArrayVal($personalDetails, 'milesExpiringAfter12Months');
        $milesExpiryAfter12MonthsDate = $this->ModifyDateFormat(ArrayVal($personalDetails, 'milesExpiryAfter12MonthsDate'));
        $this->logger->notice("[Expiration date in the future]: {$milesExpiryAfter12MonthsDate} - {$milesExpiringAfter12Months}");

        if (($milesExpiryEarlyDate || $milesExpiryEarlyDate === null) && $milesExpiryEarlyPoint == 0) {
            $this->logger->notice("Setting expiration date in the future...");
            $milesExpiryEarlyDate = $milesExpiryAfter12MonthsDate;
            $milesExpiryEarlyPoint = $milesExpiringAfter12Months;

            // refs #22059
            $this->browser->PostURL("https://{$this->host}/services/dynamic/glc/profile/private/v1/get-customerdata", json_encode($data), $headers);
            $response = $this->browser->JsonLog(null, 3, false, 'milesExpiryEarlyDate');

            if (
                isset($response->personalDetails->milesExpiryEarlyPoint)
                && $response->personalDetails->milesExpiryEarlyPoint
            ) {
                $this->logger->notice("set date from profile details: {$response->personalDetails->milesExpiryEarlyDate}");
                $milesExpiryEarlyDate = $this->ModifyDateFormat($response->personalDetails->milesExpiryEarlyDate);
            }
        } else {
            $this->logger->notice("Setting nearest expiration date...");
        }

        if ($milesExpiryEarlyPoint > 0 && $milesExpiryEarlyDate && strtotime($milesExpiryEarlyDate)) {
            $this->SetExpirationDate(strtotime($milesExpiryEarlyDate));
        }
        $this->SetProperty("MilesToExpire", $milesExpiryEarlyPoint);

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (ArrayVal(ArrayVal($response, 'soaperror'), 'errDesc') == 'Your session has expired') {
                throw new CheckRetryNeededException(2, 10);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Activity"    => "Description",
            "Guest Miles" => "Miles",
            "Tier Miles"  => "Info",
            "Bonus Miles" => "Bonus",
        ];
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $data = [
            "securityContext" => [
                "oAuth"      => null,
                "ipType"     => "MEMBER",
                "macAddress" => null,
                "otp"        => null,
                "ipAddress"  => $this->ip,
            ],
            "requestHeader" => [
                "screenName" => null,
            ],
            "userCtx"  => ["languageCode" => "en_us"],
            "transReq" => [
                "offSetValue" => 0,
                "userId"      => "",
                "transType"   => "ALL ACTIVITY",
                "months"      => "",
                "from"        => "2005-01-01",
                "to"          => date('Y-m-d'),
                "noOfTrans"   => "1000",
            ],
        ];
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=utf-8",
        ];
        $this->browser->PostURL("https://{$this->host}/services/dynamic/glc/transaction/private/v1/get-txn-activities", json_encode($data), $headers);

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->browser->JsonLog(null, 3, true);
        $nodes = ArrayVal($response, 'transDetails', []);
        $this->logger->debug("Total " . count($nodes) . " history items were found");

        foreach ($nodes as $node) {
            $dateStr = ArrayVal($node, 'date');
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Activity'] = ArrayVal($node, 'dynamicDescription');

            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Activity'])) {
                $result[$startIndex]['Bonus Miles'] = ArrayVal($node, 'guestPoints');
            } else {
                $result[$startIndex]['Guest Miles'] = ArrayVal($node, 'guestPoints');
            }
            $result[$startIndex]['Tier Miles'] = ArrayVal($node, 'tierPoints');
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    private function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);
        // invalid credentials
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //span[contains(text(), 'Error! Sorry, your email address or membership number is incorrect.')]
                | //span[contains(text(), 'Error! Sorry email or membership number is incorrect. Please check and try again.')]
                | //span[contains(text(), 'Error! For your online security, please create a new password with atleast one number, one uppercase letter and one lowercase letter')]
                | //p[contains(text(), 'Sorry, either your username or password is incorrect. Please check your details and try again')]
                | //p[contains(text(), 'Your password is either expired or considered weak.') or contains(text(), 'Your Password is Weak/Expired.')]
                | //p[contains(text(), 'Error! Your password needs to be updated as it is weak or expired.')]
                | //p[contains(text(), 'Error! Email address not registered. Join today and login to your account')]
                | //p[contains(text(), 'Error! Authentication failed.')]
                | //p[contains(text(), 'Error! Password expired. Please change your password.')]
            "), 0)
        ) {
            if (!empty($message->getText())) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
        }
        // provider errors
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'Error! Sorry, we could not process your request. Please try again later.')]
                | //p[contains(text(), 'Error! We are currently experiencing technical difficulties.')]
            "), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // Error! Please update your email address
        // Your password is either expired or considered weak. In order to protect your security, please change your password before you access your account.
        if ($message = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Your password is either expired or considered weak. In order to protect your security, please change your password before you access your account.') or contains(text(), 'Error! Please update your email address') or contains(text(), 'Error! Please update your email id')]"), 0)) {
            $this->throwProfileUpdateMessageException();
        }
        // Error! For your security, we've locked your account, as the maximum number of log-in attempts has been exceeded. Please click on “Forgot password” link to reset your password and unlock your account.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Error! For your security, we\'ve locked your account, as the maximum number of log-in attempts has been exceeded.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Error! Sorry, this field cannot be blank.')]"), 0)) {
            throw new CheckException("Please check that your email address/membership number and password are correct", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode("//title[contains(text(), 'Etihad BusinessConnect - Site Maintenance')]")) {
            throw new CheckException("We are currently undergoing a planned maintenance on our website. We will be back shortly", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
