<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Parsing\WrappedProxyClient;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAutozone extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.autozone.com/user/rewards';

    private $selenium = true;
    private $responseData = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        //$this->http->SetProxy($this->proxyReCaptchaIt7());
        $this->setProxyNetNut();
        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 0) {
            $this->http->setRandomUserAgent(5, false, true);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "autozoneCreditsEarned"))) {
            return $fields['Balance'] . " of 5";
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && strpos($this->http->Error, 'Network error 92 - ') === false) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (strlen($this->AccountFields['Pass']) < 6) {
            throw new CheckException("Your password must be at least 6 characters", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.autozone.com/favicon.ico');

        $this->checkAccess();

        if ($this->selenium === true) {
            $this->getCookiesFromSelenium();

            return true;
        }

//        $this->http->GetURL("https://www.autozone.com/");
        $this->http->setMaxRedirects(0);
        $this->http->GetURL("https://www.autozone.com/signin");
        $this->http->setMaxRedirects(7);

        $i = $this->http->FindPreg("/var i = (\d+);/");
        $this->logger->debug("i: {$i}");
        $jOne = $this->http->FindPreg("/var j = i \+ Number\(\"(\d+)\" \+ \"\d+\"\);/");
        $this->logger->debug("jOne: {$jOne}");
        $jTwo = $this->http->FindPreg("/var j = i \+ Number\(\"\d+\" \+ \"(\d+)\"\);/");
        $this->logger->debug("jTwo: {$jTwo}");
        $j = $i + $jOne + $jTwo;
        $this->logger->debug("j: {$j}");
        $bm = $this->http->FindPreg('/"bm-verify": "([^\"]+)/');

        if ($bm && $j) {
            $this->http->PostURL("https://www.autozone.com/_sec/verify?provider=interstitial", json_encode([
                "bm-verify" => $bm,
                "pow"       => $j,
            ]));
            $this->http->GetURL("https://www.autozone.com/signin", ["Referer" => "https://www.autozone.com/signin"]);
        }

        $this->checkAccess();

        if ($this->selenium === false) {
            /*
            if (!$this->sendSensorData()) {
                return $this->checkErrors();
            }
            $this->http->GetURL("https://www.autozone.com/signin");
            */
        }

        if (!$this->http->ParseForm("signInForm")) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha($this);

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue("googleRecaptchaToken", $captcha);
        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('redirectTo', "https://www.autozone.com/");
        $this->http->SetInputValue('remember_me', "on");
        $this->http->SetInputValue('action', "SIGN+IN");

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/<title>502 Proxy Error<\/title>/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // This server is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("
                //font[contains(text(), 'This server is temporarily unavailable.')]
                | //div[contains(text(), 'All password-protected areas of this website are temporarily unavailable.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // AutoZone.com Is Currently Under Maintenance
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'AutoZone.com Is Currently Under Maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($this->http->FindSingleNode("//p[contains(., 'For a tune-up and some') and contains(., 'scheduled maintenance')]")) {
            throw new CheckException("We are currently in the shop. For a tune-up and some scheduled maintenance. We apologize & will be back as soon as possible", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->selenium === false && !$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();

            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@id = "loginError" or @id = "sign-in-page-error"]')) {
            $this->logger->error($message);

            if (
                strstr($message, 'We do not recognize that username and password combination. Please try again.')
                || strstr($message, 'This account has begun a password reset. Please use the link in the password reset email you received to create your new password, or click the Forgot Password link below to receive a new email.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'There seems to be a problem logging in with our server at the moment. We apologize for the inconvenience. Please try again at a later time.')
                || strstr($message, 'There was a problem with your request. Please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, '{"config":{"transformRequest":{},"transformResponse":{},"timeout":0,"xsrfCookieName":"XSRF-TOKEN')
            ) {
                throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Your account has been locked due to too many failed login attempts.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // We do not recognize that username and password combination. Please try again or sign up today!
        if ($message = $this->http->FindSingleNode("//label[contains(text(), 'We do not recognize that username and password combination.')] | //div[contains(text(), 'We couldn’t find an account connected to the email address you’ve entered.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, we didn't recognize your user name or password. Please try again.
        if ($message = $this->http->FindSingleNode('//div[contains(@class, "rewardsLessPadTop")]/ul/li[contains(text(), "Sorry, we didn\'t recognize your user name or password. Please try again.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // To link your AutoZone® Rewards℠ and MyZone℠ account, we just need a few pieces of information.
        if ($this->http->FindSingleNode("//strong[contains(text(), 'Do you want to link your MyZone and AutoZone Rewards Accounts?')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'Link Your Account')]")
//            || $this->http->ParseForm("mergePopup")//todo: do not use it, captcha issue
        ) {
            $this->throwProfileUpdateMessageException();
        }
        // Sorry, the account entered has already been linked. For help, please call customer care at 1-800-741-9179.
        if ($message = $this->http->FindSingleNode("(//li[contains(text(), 'Sorry, the account entered has already been linked. For help, please call customer care at 1-800-741-9179.')])[1]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We seem to be having a problem logging you in right now. We are sorry for the inconvenience. Please try again later!
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'We seem to be having a problem logging you in right now. We are sorry for the inconvenience. Please try again later!')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // We do not recognize that username and password combination. Please try again or sign up today!
        if ($this->http->FindPreg("/\{\"atgResponse\":\s*\{\s*\"statusCode\": 200,\s*\"userType\": \"GUEST\"\s*\}\}/")) {
            throw new CheckException("We do not recognize that username and password combination. Please try again or sign up today!", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->DebugInfo = 'Access Denied';

            return false;
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'Sign In')]") || $this->http->Response['code'] == 403) {
            if ($this->http->Response['code'] == 403) {
                $this->DebugInfo = "403 after auth, need to check sensor_data url";
            }

            throw new CheckRetryNeededException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->getCookieByName('isSuccessFullLogin')) {
            $this->logger->notice("New site");
            $headers = [
                "X-Requested-By"   => "MF",
                "Accept"           => "application/json",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Referer"          => "https://www.autozone.com/errorPage",
            ];

            if (isset($this->responseData) && strstr($this->responseData, 'rewardBalance')) {
                $response = $this->http->JsonLog($this->responseData);
            } else {
                $this->http->GetURL("https://www.autozone.com/ecomm/b2c/v1/currentUser/rewards", $headers);
                $response = $this->http->JsonLog();
            }

            if ($this->http->Response['code'] == 503 && $this->http->FindPreg('/For a tune up and some<br>scheduled maintenance/')) {
                throw new CheckException("We are currently in the shop for a tune up and some scheduled maintenance. We apologize & will be back as soon as possible.", ACCOUNT_PROVIDER_ERROR);
            } elseif (
                in_array($this->http->Response['code'], [
                    500,
                    403,
                ])
            ) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                    throw new CheckRetryNeededException(3, 0);
                }

                return;
            }

            // Balance - Available Rewards
            if ($this->http->FindPreg('/"rewardBalance":null,/', false, json_encode($response))) {
                $response->rewardBalance = 0;
            }
            $this->SetBalance($response->rewardBalance ?? null);
            // Total Rewards Earned
            $this->SetProperty("TotalEarned", "$" . preg_replace('/\.0$/', '', $response->totalRewardsCurrentYear));
            // Total Rewards Redeemed
            $this->SetProperty("TotalRedeemed", "$" . preg_replace('/\.0$/', '', $response->totalRewardsRedeemedCurrentYear));
            // in Rewards will expire on ...
            $this->SetProperty("BalanceToExpire", preg_replace('/\.0$/', '', $response->nextRewardExpAmount));
            $expDate = $response->nextRewardExpDate;

            if ($expDate = strtotime($expDate)) {
                $this->SetExpirationDate($expDate);
            }
            // Rewards Account ID
            $this->SetProperty("MemberID", $response->loyaltyCardNumber ?? null);

            // SubAccount - Credits Earned

            $balance = $response->currentNumberOfCredits ?? null;
            $this->logger->debug("currentNumberOfCredits -> {$balance}");

            if ($balance == 5 && $this->ErrorCode == ACCOUNT_CHECKED) {
                $this->sendNotification("Credits Earned. Please check this account");
            }
            $exp = $response->nextCredExpDate ?? null;

            if (isset($balance)) {
                $subAccount = [
                    'Code'        => 'autozoneCreditsEarned',
                    'DisplayName' => 'Credits Earned',
                    'Balance'     => $balance,
                ];
                $this->logger->debug("Credits Expiration Date " . $exp . " - " . strtotime($exp) . " ");

                if ($exp = strtotime($exp)) {
                    $subAccount['ExpirationDate'] = $exp;
                }
                $this->AddSubAccount($subAccount);
            }// if (isset($balance, $displayName))

            // Do you have an AutoZone Rewards account or card?
            if ($this->http->FindPreg("/^\{\"loyaltyCardNumber\":null,\"currentNumberOfCredits\":null,\"nextRewardExpDate\":null,\"rewardsImageUrl\":\"\/images\/azRewards\/rewardscard.png\",\"userFirstName\":null,\"creditsPerReward\":null,\"rewardBalance\":null,\"nextRewardExpAmount\":null,\"planRewardDollarAmount\":null,\"nextCredExpAmount\":null,\"numberOfCreditsNeedForNextReward\":null,\"links\":\[\],\"totalRewardsCurrentYear\":null,\"nextCredExpDate\":null,\"canRequestReplacement\":null,\"totalRewardsRedeemedCurrentYear\":null\}$/")) {
                $this->http->GetURL("https://www.autozone.com/ecomm/b2c/v1/currentUser/rewards/validateRewardsAccountExistence", $headers);
                $response = $this->http->JsonLog();

                if ($response->isRewardsExist == false && $response->userType == '4') {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }

            $this->http->GetURL("https://www.autozone.com/ecomm/b2c/v1/currentUser", $headers);
            $response = $this->http->JsonLog();
            // Name
            $name = ($response->firstName ?? null) . " " . ($response->lastName ?? null);
            $this->SetProperty("Name", beautifulName($name));

            return;
        }

        $this->logger->notice("Old site");
        // TODO: Does not work the first time
        if (!$this->http->FindSingleNode("//p[contains(@class, 'rewardsBalanceLarge')]/span", null, false)) {
            $this->http->GetURL("https://www.autozone.com/myzone/loggedIn.jsp");
        }

//        $this->http->GetURL("https://www.autozone.com/myzone/rewards/rewardsActivity.jsp");

        // refs #11968
        // Balance - Available Rewards
        $this->SetBalance($this->http->FindSingleNode("//p[contains(@class, 'rewardsBalanceLarge')]/span"));
        // Total Rewards Earned
        $this->SetProperty("TotalEarned", Html::cleanXMLValue($this->http->FindPreg("/Total\s*Rewards\s*Earned\s*:\s*([^<]+)/ims")));
        // Total Rewards Redeemed
        $this->SetProperty("TotalRedeemed", Html::cleanXMLValue($this->http->FindPreg("/Total\s*Rewards\s*Redeemed\s*:\s*([^<]+)/ims")));
        // in Rewards will expire on ...
        $this->SetProperty("BalanceToExpire", $this->http->FindSingleNode("//span[@id = 'balanceExpiry']", null, true, "/\s*([^<]+)\s*in \s*Reward/ims"));
        $expDate = $this->http->FindSingleNode("//span[@id = 'balanceExpiry']", null, true, "/Expire\s*on\s*([^<]+)/ims");

        if ($expDate = strtotime($expDate)) {
            $this->SetExpirationDate($expDate);
        }

        // SubAccount - Credits Earned

        $balance = $this->http->FindSingleNode("//img[contains(@src, '5credits')]/@src", null, false, "/(?:\%20[\%09]*|\s*)(\d+)of(?:\%20[\%09]*|\s*)5credits/");

        if ($balance === null) {
            $balance = $this->http->FindSingleNode("//img[contains(@data-blzsrc, '5credits')]/@data-blzsrc", null, true, "/(?:\%20[\%09]*|\s*)(\d+)of(?:\%20[\%09]*|\s*)5credits/");
        }
        $this->logger->debug("balance -> {$balance}");

        if (($balance == null || $balance == 5) && $this->ErrorCode == ACCOUNT_CHECKED) {
            $this->sendNotification("autozone - Credits Earned. Please check this account");
        }
        $exp = $this->http->FindSingleNode("//div[@id = 'nextcredexp'] | //span[@id = 'creditsExpiry']", null, true, "/on\s*([^<]+)/");

        if (isset($balance)) {
            $subAccount = [
                'Code'        => 'autozoneCreditsEarned',
                'DisplayName' => 'Credits Earned',
                'Balance'     => $balance,
            ];
            $this->logger->debug("Credits Expiration Date " . $exp . " - " . strtotime($exp) . " ");

            if ($exp = strtotime($exp)) {
                $subAccount['ExpirationDate'] = $exp;
            }
            $subAccounts[] = $subAccount;
        }// if (isset($balance, $displayName))

        if (isset($subAccounts)) {
            // Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->notice("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        // todo: debug
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->ParseForm("profileLogoutForm")) {
            $this->http->PostForm();

            throw new CheckRetryNeededException(2, 10);
        }

        $this->http->GetURL("https://www.autozone.com/myzone/profile/profile.jsp?invokeGET=true");
        // Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//span[strong[contains(text(), 'First Name')]]/text()[last()]")
            . ' ' . $this->http->FindSingleNode("//span[strong[contains(text(), 'Last Name')]]/text()[last()]"));
        $this->SetProperty("Name", beautifulName($name));
        // AutoZone Rewards ID#
        $this->SetProperty("MemberID", $this->http->FindPreg("/Rewards\s*ID\s*#:\s*[^<]*<[^>]+>\s*([\d\s]+)/ims"));
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(text(), 'Log Out')]")) {
            return true;
        }
        // new view
        if ($this->http->getCookieByName('isSuccessFullLogin')) {
            return true;
        }

        if (isset($this->responseData) && strstr($this->responseData, 'rewardBalance')) {
            return true;
        }

        return false;
    }

    private function checkAccess()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 7);
        }
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            return false;
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];

        $sensorData = [
            null,
        ];

        $secondSensorData = [
            null,
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = 0; // array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $data = [
            "sensor_data" => $sensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "sensor_data" => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return true;
    }

    private function getCookiesFromSelenium($retry = false)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;

            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            */
            $selenium->useGoogleChrome();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint) {
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            /*
            $wrappedProxy = $this->services->get(WrappedProxyClient::class);
            $proxy = $wrappedProxy->createPort($selenium->http->getProxyParams());
            $selenium->seleniumOptions->antiCaptchaProxyParams = $proxy;
            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            */

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.autozone.com/signin");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 5);
            $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-testid = "submit-button" or @data-testid="st-button-st-actionable"]'), 0);
            $this->savePageToLogs($selenium);

            if ($login && $password && $signIn) {
                $login->sendKeys($this->AccountFields['Login']);
                $password->sendKeys($this->AccountFields['Pass']);

                /*
                $captcha = $this->parseReCaptcha();

                if ($captcha === false) {
                    return false;
                }

                $selenium->driver->executeScript("validatereCaptchaSignIn('{$captcha}');");// todo: old
                */

//                $selenium->waitFor(function () use ($selenium) {
//                    return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
//                }, 140);
//                $this->savePageToLogs($selenium);

                $this->logger->debug("click by btn");
                $signIn->click();
                sleep(2);

                $selenium->waitForElement(WebDriverBy::xpath('
                    //a[contains(text(), "Log Out")]
                    //div[@id = "at_user_rewards_link"]
                    | //div[@id = "loginError" or @id = "sign-in-page-error"]
                    | //div[contains(text(), "Your account has been locked due to too many failed login attempts.")]
                '), 10);

                $seleniumDriver = $selenium->http->driver;
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

                foreach ($requests as $n => $xhr) {
//                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
//                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
//
                    if (preg_match('#/currentUser/rewards#', $xhr->request->getUri())) {
                        $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                        $this->responseData = json_encode($xhr->response->getBody());

                        break;
                    }
                }

                $this->savePageToLogs($selenium);
            } elseif ($login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name="login"]'), 0)) {
                $selenium->waitForElement(WebDriverBy::xpath("//div[@class='grecaptcha-badge']"), 7);
                $login->sendKeys($this->AccountFields['Login']);
                $continue = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Continue" or contains(., "Continue")]'), 3);
                //$selenium->anticaptchaWait($selenium);
                /*$captcha = $this->parseReCaptcha($this, 'https://www.autozone.com/signin');

                if ($captcha) {
                    $selenium->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');
                }*/
                $this->savePageToLogs($selenium);

                if (!$continue) {
                    return false;
                }

                $selenium->waitFor(function () use ($selenium) {
                    $this->logger->warning("Solving is in process...");
                    sleep(3);
                    $this->savePageToLogs($selenium);

                    return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                }, 180);

                $solvingStatus =
                    $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                    ?? $this->http->FindSingleNode('//a[@class = "status"]')
                ;
                $this->logger->debug("[AntiCaptcha]: {$solvingStatus}");

                $selenium->driver->executeScript('try { document.querySelector(\'#SignInButton [data-testid="st-button-st-actionable"]\').removeAttribute(\'disabled\'); } catch (e) {}');

                if ($continue = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Continue" or contains(., "Continue")]'), 0)) {
                    $continue->click();
                }

                $password = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 10);
                $this->savePageToLogs($selenium);

                if (!$password) {
                    if ($message = $this->http->FindSingleNode('//div[@role="alert"]//div[@data-testid="st-text" and normalize-space() != ""]')) {
                        if (strstr($message, 'There was a problem with your request. Please try again.')) {
                            $retry = true;
                        }

                        $this->DebugInfo = $message;
                    }

                    return false;
                }

                $password->sendKeys($this->AccountFields['Pass']);
                $this->increaseTimeLimit(100);

                $selenium->waitFor(function () use ($selenium) {
                    $this->logger->warning("Solving is in process...");
                    sleep(3);
                    $this->savePageToLogs($selenium);

                    return !$this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]');
                }, 180);

                $solvingStatus =
                    $this->http->FindSingleNode('//a[@title="AntiCaptcha: Captcha solving status"]')
                    ?? $this->http->FindSingleNode('//a[@class = "status"]')
                ;
                $this->logger->debug("[AntiCaptcha]: {$solvingStatus}");

                //$selenium->anticaptchaWait($selenium);

                /*$captcha = $this->parseReCaptcha($this, 'https://www.autozone.com/signin');

                if ($captcha) {
                    $selenium->driver->executeScript('document.getElementsByName("g-recaptcha-response")[0].value = "' . $captcha . '";');
                }*/

                /*if ($selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(),'Recaptcha server reported that site key is invalid') or contains(text(),'There was a problem with your request. Please try again.')]"), 2)) {
                    $password->clear();
                    $password->sendKeys($this->AccountFields['Pass']);
                    sleep(5);
                }*/

                $selenium->driver->executeScript('try { document.querySelector(\'#SignInButton [data-testid="st-button-st-actionable"]\').removeAttribute(\'disabled\'); } catch (e) {}');
                $passwordBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@aria-label="Sign In" or contains(., "Sign In")]'), 0);

                if ($passwordBtn) {
                    $passwordBtn->click();
                }

                $selenium->waitForElement(WebDriverBy::xpath('
                    //a[contains(text(), "Log Out")]
                    | //div[@id = "at_user_name_icon"]/div/div[contains(text(), "Hi, ")]
                    | //div[@id = "loginError" or @id = "sign-in-page-error"]
                    | //div[contains(text(), "Your account has been locked due to too many failed login attempts.")]
                    | //h1[contains(text(), "Access Denied")]
                '), 10);

                $seleniumDriver = $selenium->http->driver;
                $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

                foreach ($requests as $n => $xhr) {
                    //                    $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                    //                    $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                    //
                    if (preg_match('#/currentUser/rewards#', $xhr->request->getUri())) {
                        $this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                        $this->responseData = json_encode($xhr->response->getBody());

                        break;
                    }
                }

                $this->savePageToLogs($selenium);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                $this->markProxyAsInvalid();
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    private function parseReCaptcha(TAccountCheckerAutozone $selenium, $url)
    {
        $this->logger->notice(__METHOD__);
        $key = '6LcNhicUAAAAANst_eyoRaKjf0OaUCTHR8Kza1GK';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $this->logger->debug($selenium->http->currentUrl());
        $parameters = [
            "pageurl"   => $url,
            "proxy"     => $selenium->http->GetProxy(),
            "proxytype" => "HTTP",
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function anticaptchaWait($selenium)
    {
        $captchaSolving = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 2);

        if (!$captchaSolving) {
            return;
        }

        $selenium->waitFor(function () use ($selenium) {
            return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
        }, 160);

        if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "error.createuser.recaptcha.invalid")]'), 0)) {
            $this->savePageToLogs($selenium);

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }
    }
}
