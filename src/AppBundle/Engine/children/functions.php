<?php

use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerChildren extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $reCaptcha = '';
    private $fromIsLoggedIn = false;

    private $headers = [
        "expires"              => 0,
        "catalogId"            => "10551",
        "deviceType"           => "desktop",
        "langId"               => "-1",
        "storeId"              => "10151",
        "Content-Type"         => "application/json",
        "tcp-trace-request-id"         => "CLIENT_1_1658404528450",
        "tcp-trace-session-id"         => "152ca46fc676c70ea3d7a6a40eb28456",
        "siteid"                       => "PLACE_US",
        "platform"                     => "SF",
        'Accept'                       => '*/*',
        "x-tcp-channel"                => "",
        "refresh"                      => 'true',
        'client_source'                => 'tcp-us-web-home',
        'apollographql-client-name'    => 'web',
        'apollographql-client-version' => '0',
    ];
    private $headersV3 = [
        'Accept' => '*/*',
        'Content-Type' => 'application/json',
        'Apollographql-Client-Name' => 'web',
        'Apollographql-Client-Version' => '0',
        'client_source' => 'tcp-us-web-home',
        'Origin' => 'https://www.childrensplace.com',
        'Devicetype' => 'desktop',
        'Platform' => 'SF',
        'Siteid' => 'PLACE_US',
        //'Tcp-Trace-Request-Id' => 'CLIENT_1_1687861422067',
        'Tcp-Trace-Session-Id' => 'not-available',
        //'Usid_dup' => '66202613-ead2-43ed-b574-1a004af16983',
    ];

    private HttpBrowser $curlBrowser;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'childrenCertificate')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt == 0) {
            $this->UseSelenium();
            $this->http->saveScreenshots = true;
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->usePacFile(false);

            $resolutions = [
//                [1152, 864],
//                [1280, 720],
                [1280, 768],
//                [1280, 800],
    //            [1360, 768],
//                [1366, 768],
//                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->setScreenResolution($resolution);

            //$this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        }

        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaVultr());
        $this->http->setRandomUserAgent();
    }



    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $result = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($result === true) {
            $this->fromIsLoggedIn = true;

            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->switchToCurl();
        $data = [
            'operationName' => 'GET_REGISTERED_CUSTOMER',
            'query'     => "query GET_REGISTERED_CUSTOMER {\n  getRegisteredCustomer {\n    zipCode\n    xlanguage\n    xcountry\n    wishListId\n    wicPlccId\n    userSurvey\n    totalReward\n    state\n    shippingAddressId\n    profilePercentageComplete\n    phoneMobile\n    memberStatus\n    login\n    lastName\n    isRememberedUser\n    isRegistered\n    isExpress\n    firstName\n    hasPLcc\n    favoriteStore\n    email\n    customerId\n    currencyToNextReward\n    currencyBalance\n    currency\n    country\n    city\n    sflListId\n    cardLast4\n    cMPRID\n    birthday\n    userBirthday\n    billingAddressId\n    associateId\n    airMilesAccount\n    UserId\n    isPlccIC\n    addresses {\n      address1\n      address2\n      addressId\n      c_addressType\n      c_emailId\n      c_state\n      c_validationCode\n      city\n      companyName\n      countryCode\n      fullName\n      jobTitle\n      phone\n      postalCode\n      preferred\n      salutation\n      stateCode\n      title\n      __typename\n    }\n    addressLine\n    mailingAddressId\n    totalSpend\n    currentPerkLevel\n    nextPerkLevel\n    spendLeftForNextPerk\n    __typename\n  }\n}\n",
            'variables'    => new StdClass(),
        ];

        $this->headersV3['Referer'] = 'https://www.childrensplace.com/us/home/login';
        $this->curlBrowser->PostURL("https://www.childrensplace.com/federation-gateway/v3/graphql?operationName=GET_REGISTERED_CUSTOMER",  json_encode($data), $this->headersV3);
        $response = $this->curlBrowser->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));
        $email = $response->data->getRegisteredCustomer->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ($this->attempt == 0) {
            $this->driver->manage()->window()->maximize();
            $this->http->GetURL("https://www.childrensplace.com/us/home/login");
            $login = $this->waitForElement(WebDriverBy::id('emailAddress'), 10);
            $pass = $this->waitForElement(WebDriverBy::id('password'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath("//form[@name='LoginForm']//button[@type='submit']"), 0);
            $this->saveResponse();

            if (!isset($login, $btn)) {
                return $this->checkErrors();
            }
            sleep(5);
            $login->sendKeys($this->AccountFields['Login']);

            if ($pass) {
                $pass->sendKeys($this->AccountFields['Pass']);
            }
            else {
                $btn->click();

                $pass = $this->waitForElement(WebDriverBy::id('password'), 5);
                $btn = $this->waitForElement(WebDriverBy::xpath("//form[@name='LoginForm']//button[@type='submit']"), 0);
                $this->saveResponse();

                if (!isset($pass, $btn)) {
                    return $this->checkErrors();
                }

                $pass->sendKeys($this->AccountFields['Pass']);
                $this->saveResponse();
            }

            $btn->click();

            return true;
        }

        $this->http->GetURL("https://www.childrensplace.com/us/home/login");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

//        if ($this->attempt > 0) {
//            $this->getCookiesFromSelenium();
//        } else {
            $this->sendSensorData();
//        }

        $data = [
            "storeId"                  => "10151",
            "logonId1"                 => $this->AccountFields['Login'],
            "logonPassword1"           => $this->AccountFields['Pass'],
            "mergeCart"                => true,
            "rememberCheck"            => true,
            "rememberMe"               => true,
            "requesttype"              => "ajax",
            "reLogonURL"               => "TCPAjaxLogonErrorView",
            "URL"                      => "TCPAjaxLogonSuccessView",
            "registryAccessPreference" => "Public",
            "calculationUsageId"       => -1,
            "createIfEmpty"            => 1,
            "deleteIfEmpty"            => "*",
            "fromOrderId"              => "*",
            "toOrderId"                => ".",
            "updatePrices"             => 0,
            "userId"                   => "-1002",
            //"reCaptcha"                => $this->reCaptcha,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.childrensplace.com/api/stateful/account/v2/auth/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;
//        $this->http->SetInputValue("logonId1", $this->AccountFields['Login']);
//        $this->http->SetInputValue("logonPassword1", $this->AccountFields['Pass']);

        if ($this->http->Response['code'] == 403) {
            $this->DebugInfo .= " | need to upd sensor_data";
            throw new CheckRetryNeededException(2, 0);
            return false;
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//img[contains(@src, 'site_maintenance')]/@src")) {
            throw new CheckException("Our site is currently down for routine maintenance.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if ($this->attempt == 0) {
            $this->waitForElement(WebDriverBy::xpath('
                //buttoun[@title = "Log Out"]
                | //div[@class = "sign-out-icon"]
                | //div[contains(@class, "richTextColor")]
            '), 15);
            $this->saveResponse();

            if (
                $this->http->FindNodes('//buttoun[@title = "Log Out"] | //div[@class = "sign-out-icon"]/@class')
                && $this->loginSuccessful()
            ) {
                return true;
            }

            if ($message = $this->http->FindSingleNode('//div[contains(@class, "richTextColor")]')) {
                $this->logger->error("[Error]: {$message}");

                if ($message == 'An error occurred!') {
                    $this->DebugInfo = $message;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    return false;
                }

                $this->DebugInfo = $message;

                return false;
            }
        }

        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'SUCCESS') {
            return $this->loginSuccessful();
        }

        if (!is_array($response) && stristr($response, '<TITLE>Access Denied</TITLE>')) {
            throw new CheckRetryNeededException(2, 0);
        }

        $errorMessage = $response->errors[0]->errorMessage ?? null;
        $errorCode = $response->errors[0]->errorCode ?? null;

        if ($errorMessage) {
            // Invalid credentials
            if ($errorMessage == 'The email address or password you provided is invalid.') {
                throw new CheckException($errorMessage, ACCOUNT_INVALID_PASSWORD);
            }
            // Due to 5 unsuccessful password attempts, you will be unable to logon.
            if (strstr($errorMessage, 'Due to 5 unsuccessful password attempts, you will be unable to logon.')) {
                throw new CheckException($errorMessage, ACCOUNT_LOCKOUT);
            }
            // Oops... There was an issue, please try again.
            if (strstr($errorMessage, 'Execution of the expression "message.inboundProperties.?location.?substring(message.inboundProperties.?location.?indexOf("/webapp"))" failed.')) {
                throw new CheckException("Oops... There was an issue, please try again.", ACCOUNT_PROVIDER_ERROR);
            }

            if ($errorMessage == 'Sorry, we were unable to complete your request. Please try again.') {
                throw new CheckException($errorMessage, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $errorMessage == ''
                && $errorCode == ".ERR_PASSWORD_EXPIRED"
            ) {
                throw new CheckException("Your current password has expired.", ACCOUNT_INVALID_PASSWORD);
            }
        }

        $this->logger->debug($errorCode);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->curlBrowser->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 0);
        // My Points
        $this->SetBalance($response->data->getRegisteredCustomer->currencyBalance);
        // Points To Next Reward
        $this->SetProperty("PointsToNextReward", intval($response->data->getRegisteredCustomer->currencyToNextReward));
        // Name
        $this->SetProperty("Name", beautifulName($response->data->getRegisteredCustomer->firstName . " " . $response->data->getRegisteredCustomer->lastName));
        // My Place Rewards #
        $this->SetProperty("Number", $response->data->getRegisteredCustomer->cMPRID);
        // My Rewards
        $myRewards = $response->data->getRegisteredCustomer->totalReward;

        if (isset($myRewards)) {
            $this->SetProperty('MyPlaceRewards', "$" . intval($myRewards));
        }

        // AccountID: 4415082
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $response->data->getRegisteredCustomer->cMPRID === ''
            && $response->data->getRegisteredCustomer->totalReward === 0
        ) {
            $this->SetBalance(0);
        } elseif ($this->fromIsLoggedIn === true && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            throw new CheckRetryNeededException(2, 0);
        }

        $this->logger->info('Coupons', ['Header' => 3]);
        $data = '{"operationName":"GET_ALL_COUPONS","variables":{"sendRedeemedAndExpiredCoupons":false},"query":"query GET_ALL_COUPONS($sendRedeemedAndExpiredCoupons: Boolean) {\n  getAllCoupons(sendRedeemedAndExpiredCoupons: $sendRedeemedAndExpiredCoupons) {\n    offersCount\n    offers {\n      couponCode\n      isApplied\n      isExpired\n      offerCode\n      offerDescription\n      offerText\n      offerType\n      promotionId\n      sequence\n      validFrom\n      validTo\n      isRedeemed\n      couponType\n      perkLevel\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->curlBrowser->RetryCount = 0;
        $this->curlBrowser->PostURL("https://www.childrensplace.com/federation-gateway/v3/graphql?operationName=GET_ALL_COUPONS", ($data), $this->headersV3);
        $this->curlBrowser->RetryCount = 2;
        if ($this->curlBrowser->BodyContains('"errors":[{"message":"PersistedQueryNotFound"', false)) {
            $this->logger->error('Need to update persisted query');
            $this->sendNotification('update persisted query // BS');
            return;
        }
        $response = $this->curlBrowser->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'));

        if ($this->curlBrowser->Response['code'] == 403) {
            return;
        }

        $this->SetProperty('CombineSubAccounts', false);

        foreach ($response->data->getAllCoupons->offers as $offers) {
            $code = $offers->couponCode ?? null;
            $displayName = $offers->offerText;
            $balance = $this->http->FindPreg('/\$([\d\.\,]+) REWARD/', false, $displayName);
            $expirationDate = $offers->validTo;

            if (!isset($code)) {
                $this->logger->error("skip bad sub Acc");

                continue;
            }
            $this->AddSubAccount([
                'Code'           => "childrenCertificate{$code}",
                'DisplayName'    => $displayName . ' (' . $code . ')',
                'Balance'        => $balance,
                'ExpirationDate' => strtotime($expirationDate),
                'BarCode'        => $code,
                "BarCodeType"    => BAR_CODE_CODE_128,
            ], true);
        }

        // Expiration Date  // refs #19308
        if ($this->Balance <= 0) {
            return;
        }
        $points = $this->Balance;

        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->curlBrowser->RetryCount = 0;
        $this->curlBrowser->GetURL("https://www.childrensplace.com/api/stateful/account/v2/getMyPointHistory", $this->headers);

        if ($this->http->FindPreg('/UNABLE TO PROCESS THE REQUEST/')) {
            sleep(5);
            $this->http->GetURL("https://www.childrensplace.com/api/stateful/account/v2/getMyPointHistory", $this->headers);

            // it helps
            if ($this->http->FindPreg('/UNABLE TO PROCESS THE REQUEST/')) {
                throw new CheckRetryNeededException(2, 1);
            }
        }
        $this->curlBrowser->RetryCount = 2;
        $response = $this->curlBrowser->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, true);
        $pointsHistoryList = $response['data'] ?? [];
        $pointsHistoryData = $pointsHistoryList['pointsHistoryData'] ?? [];
        $transactionsCount = count($pointsHistoryData);
        $this->logger->debug("Total {$transactionsCount} transactions were found");

        for ($i = 0; $i < $transactionsCount; $i++) {
            $historyPoints = $pointsHistoryData[$i]['pointsEarned'];
            $this->logger->debug("Node # $i - Balance: $points, HistoryPoints: $historyPoints");

            if ($historyPoints > 0) {
                $points -= $historyPoints;
            }
            $this->logger->debug("Node # $i - Balance: $points / round: " . round($points, 2));

            if ($points <= 0) {
                $date = $pointsHistoryData[$i]['transactionDate'];
                $this->SetProperty("EarningDate", date('m/d/Y', strtotime($date)));
                $this->SetExpirationDate(strtotime("+1 year last day of this month", strtotime($date)));

                for ($k = $i - 1; $k >= 0; $k--) {
                    $this->logger->debug("> Balance: {$points}");

                    if (isset($pointsHistoryData[$k]['transactionDate']) && $date == $pointsHistoryData[$k]['transactionDate']) {
                        $points += $pointsHistoryData[$k]['pointsEarned'];
                    }// if (isset($pointsHistoryData[$k]['transactionDate']) && $date == $pointsHistoryData[$k]['transactionDate'])
                }// for ($k = $i - 1; $k >= 0; $k--)
                // Expiring balance
                $this->SetProperty("ExpiringBalance", ($points + $historyPoints));

                break;
            }// if ($points <= 0)
        }// for ($i = 0; $i < $historyPoints->length; $i++)
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $key ?? $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => 'https://www.childrensplace.com/us/home/login',
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorDataUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");

            return $this->checkErrors();
        }

        $this->http->NormalizeURL($sensorDataUrl);

        $abck = [
            // 0
            "2A578A5EEFD733C7781B2A4E6B05C68F~-1~YAAQdTe50IQ7h7mEAQAAcNzTvQigqNKcMs2LuHnyLNEtbLxYzK6aPvLovl92wEI7Rnc6ISVvS45mk3ldFwrXeXyDG0PpDTpz38pfYwetbEdkTryTL5dJwgsGkDSnnBzbucMOq94KErpuZcSpIEQ4tRbCh9awhRLMGKnltlUHbc04fgNYH0Z7AnmSQ5Xsn7gOyuL2oVxADLrdiTAeT0qVdlh+J3VzlUesM7Ez3neLBMgolqskWLyHCxGtQW+UhoSHoAwkrjvjHMY3HpHZzF+1Cf/pFeLTYb2jpzVn+XtI7N+ib2xMp0t0CI8EJ5SReIHIXc0eMUuzaR8zBxBmvKZ6Hvr1+ClI5zfkvbJTvWI0k+tMU5tALwpy/FwP4JwI9YijxIu8nke6Y0l+UncgME+fQY7HIeuoGpdOlFImphN3BRHtxdsGVLxCFN5OwoK1Dwc=~-1~||-1||~-1",
            // 1
            "CF3AE6A31EBF3F492875595DFB9F4C2F~-1~YAAQPWdNaGX+xLqEAQAAVi/UvQjR/DEXmvo+0glzVzuJ0pRnr4ciBR7n/tVKl71h4b/jF6zMan3O/KvHEfJ79r+3vEViGozTCs33AM+WnJ6iGtaa7A6fzQJJowy9IkuwkOC/wqYRb/19Mp/vb1YJ76dt6qMrTeC0Kx0eaaWnPCRSyzthCJLK1maPafyf8eZujXi3dvPPimS/j7yRH7f06rY6Re3CY6LW+oE1df6UdDQ6DqC5thQwzpC8wjGZ4Rg1xnPpIwGBEgPGvZ/5ilUkgSO+/vJqd51kK9bbeGf5HOvUFzeoaVxO77Gq1IKilOR2tdi7GDFW2m3fFGdAi+QZGlz95FVdxWU1AEocUUaBVy1aZNpL6b4NQ32MgVXZhzHuQF5vu8I=~-1~-1~-1",
            // 2
            "71331E04E67229836065EAE0BC659079~-1~YAAQxA7GF8dBiYOEAQAAgwpvrwgRRBtOjKF9BrSpWdBfTeJRv1SGBFNXhPwU0BCqHNLG3S5y6FMxB687s3KvLL7+QYjq5vIRBmyqLOsFQx2930AhieiJy7RspOKQgkddG40SnXXtqkjErIVDM288ltzwFTpJFTTXBsA+bYLl/S3fdu3L8l8H4adNh6W0y62pRAXNg48/b8XsW00E5Zub6N1y4O4y1cPJCrqVMUnh46p5QFWz7WszwuOtd2Bz+17PFnHnORj5lV+kbB9Ehk2wbLxzhYYVd0FHT0WJSdxXzo4SuntN+zzsjtTRo2/+Qcco8vYTaTMT47g0oXNdFUaDhtXG5qYNL044kjNVTCCyb1NDRW373AsNnJ8jULDaR6uQl7fwu9Im/sJ5Q1JjqBgtBKAqg15NCtZpW7W3Cy/c5GfjDGZP1T667wUk6IQg~0~-1~-1",
            // 3
            "2A578A5EEFD733C7781B2A4E6B05C68F~-1~YAAQzPo7F8tUcoiEAQAA8YKevQiwIhQnjNkgAfv60nhQfrM492SyAdWx6oxf4w7Dj1cT/BKaXqBNWnaBwGAZGUWjn6PwojldzTTv86GKlfKxh/F3DAUmT1+SsHbApPlwcFlLtFGa65e8Hefbxlq6DOY3j1QnEOatJ3pdDSLCzsr8hw/q6yAlrm56N1qHNcDjKv54XsW3gM9W7rN6dFyBM/3e9WPYD0uZfOf5INUDriZbFePBzhXC3C8pnHbLyJ2sS+I863+0Lz8F/BFAyjGUptMXlATPNBNQdM5oOqqyJADhTrnLzaKdFM0Dz0t8W+ucbWfk6fKlwOX1DluHe+AGPTK2jKVBAnnd/9Kmj/OLRix4itVlDEer1e0v1QD2MUT6StIsbyAFbpoJkNacBpSYdXu/zieBa3MIYGqHa3mO86qTEN7KB1SN+vUCaTBlyA==~-1~-1~-1",
            // 4
            "2A578A5EEFD733C7781B2A4E6B05C68F~-1~YAAQzPo7F73acoiEAQAAVRrDvQigGH8adJ6x9jikLynQMmjC2tIgKirF2FnNBwFLuE3Vykar7kd7VYtiIlHPZRsKEn2Yf/+i7bHnFuIREInF0axmwUSD488QJTki/HyxQuIrWG1AOSM/1fprtSlv+TjGNVVY9wGGR6aPC8CM9f116p22VMw5xc8teZq2Lt2tbr2LasO8Zv4Sf/CaK9gNoOXz/VVwqQV+UiDKeodKwprbhySZh8NS26/RQAgWA2tF/OreqRVgdA+LgMbcfaIxkiFhiqBmr++/BIxvN6uzQzdrQPzyJmTHSGqd0FKfa2siqTl2yXj/XrQRtrTEwbX7j1jcCxhmEyIWB+QliFz0vN+BIoB0SJDUv8aAYPdtoetMr4EDLPxKMkRrvKUEiwRgRCmSWxGzToMpS5yrmFPvetKVYSSxPaAKcZaiVZ6IaQ==~-1~-1~-1",
        ];

        $key = array_rand($abck);
        $this->logger->notice("key: {$key}");
        $this->DebugInfo = "key: {$key}";
        $this->http->setCookie("_abck", $abck[$key], ".childrensplace.com"); // todo: sensor_data workaround

        return;

        $sensorData = [
            '2;3290677;3223875;8,0,0,1,0,0;vyXCDG=/n=?glXczf2yME{Z/h%b9uAdJbvl}d3mU<wiVcQg|c5*aa6qG;HxqlKT8tws!~0ya^o@{BL&Dh&!jdPMqiv>#%j1,ivRu&55: ug]io7]G({bGH6rv.kpg|_[GT^}y.]6:-R*1>i1)hbKt*3kX%Hx,X;)wYj0=ap vms1d=m50$bir/bFmm|6@QY^$qh(`K]q]^G~sA1${hud!}vp2bNS^+:eq2;e+Rl+{(]Tez5/8}21ELa`O28 N4f))B60N)65RpQ@;lQ974RNTq9z1n_DCzr%-6no`BWy3A+&>cI5D=WFYn/JVD/Y}wW|%6cSr4V _t[`%L:&>>Ui<>mu fbh=T<I[Jpyje-UqC3oWRfhw}Y^AY H7-*F*/vHX5:D<X.kz-1Nq7|yfs:R$P!/i:/kNP0g^TAN&N2l-.N_5oen)J|/p2hJI7G(_@d}BErrE4zU/U[jHH11$ZHmCr^cx(Bv[syL!^E:BdQ=mq=<#;>:HnCfY>T6s$*SI}}Bu!`gJ*](Q&4bB,`Wo@$?1}T(Ocp_Rtd^-qXvh:MW*Xg)1lY;!A2p]>EIy=PC+TgQ)-&/8*pc%r4JS{QlJ}4fsupD~/x{iAYC}(u%pNX^@?[BG3e}X9#1RNCx^yr>Gh0~aojt?|$5ug05Zf*J``n^TA<I_1NRX,^U<xBi`~ds%pwi,{G@_UF:L&9|kVddoP{#ry%9Gfk+yrI~5BF@0#~;*i$bZ5g8xM7+*GMu_Gr[d^B]sMrML;E4UEWDC8U^+6nsvU`7C zE8I!Px>{,@|5dugah!It6oO=>N.!KnHPXz#nT|.sW1*Cp~7<AD!t&/, -$W-La9h/=H@5OPTEPMSV?DY^eFiS&rc$9Z[U+k%5W i]MO40<E9d;Gq.~h],SJ>StMhQre.c+:|{RxObjtMc1flp)wvAzvkLO$N/rPufy>;0d91%FY~@y.OMt{ =%.3Ni?puKsRKj]&IuFOHq&s%E4/Xuk@J.<>OvyDL!uL>Ty%? Q]hx@pyoEv<acNPP8F!>=xliF#AhX}/kHijbE7XCqN?(LM5|>N^Kkv j[$)jJZp!4V_5,+ua m8cY xb#BwhJ++s^Mh9t0A8)DX.WAujFgL8CXlBXVIE6},y.|uF?Q}i[#9p=?qpm=^Gc{Q`w>G~|r_e+xPQQo6*,TrT*paaJ_sIs47!OylW]_wppy}q[)tZH~9]?cD?) ]UuBE<{@3b-/OK_bw]Z&a[i.|@($nD>5:L^=FDfILDY=x~9ANMKEDig7pVPR:Eq78&J,8K~UZIiw>lzx;.rMRMd8xlaZ4juvf`5uj&{ZjR~&`0:?^]bG8Ku=nposM+(>lvqIb}rTOXrxNwC^%,;~rF@ lh^@#8A[R4,Z*VCr(Df%l)6r 5aD3O{aIsXf:!C]XaN;e%^U ,Y}O^UoRUMI_y!&y/.S[63E5L;*l(ua]f7d999lGN!gn9_BLac]2s(QgygP/]YZq;e9*K2V:]$rzJwsw1LX._t;Kg[gsU`YQ]:!$e)df[vVau^;/Z]:V7v&qy*aOe;2|A#W@]~4zSa{>w&>Q_@>hRf_*,kz^~} 8Jq0n+<_F2nYUeN9,}nkJFhCG7U;-1f@Wm0w/;_=lH-tf?~95DMW9)Llc^k^F@Ve{x6l!Rh53?~%8NrWqTTT4<78RNOxpc>9lNgHkiV):k`b0LiwgSyp5rAdDR7%@npkr!Y;-pU#K%|9E^W t-:/*~_~V_&=k3lm5oLRdU!W@i@Nqjp8-|Wc(.|Kk+JxPGg9v|GzPeyS5r8Itcp8RX?Z]|ZKVLy(FWi_(:T(m%sKrLw(E8bGqkJU@1?x9j]p}3c13M179<xbEn.Fj#7$C+[,{s>_=Rro2~(kC.O{#es7R:W+)yb]++A/:*q0C8]4=e3z-u(r6CnljB|Ux8Jg&CY(,pZw3F8j5Q7(@Bu C^Xm5EX`3Nj[;jOXl709KRD)VzlTrpJhJnDb-@ZSLol j xtA| K@_I9<~&~8{{@(K&N-AV.A 6@)Hr{%2O5,-%<[>05NN&jH%c3d/VGs~KMJY13?Oohr(.(`v)7^u#)vz$eP[:4D>TIgOsGa?f7Jj]H(`nn^{e,yOQ_XyMFi6o}~<O2TRT5O9(,-@UX0 EzvNMI}JfVU; 5={yUJX3i(dbv[=Pe=p@8^k$gNOOr-aQdY3sJzf7W9`&<$d)Fs{2)1q|n^_v%O;~aTyU7kg3]B$&KD*,d? #voo~gy?reRN<b_9#i53*c7{5TE<c#yK =RF]MM([/rSX 5SLQz,ZX0i.VB6WB-Mgm|C#.6rs<e>c(U80sRP;;NSC2CJmL.w,2O?JSmVzmQY[7PKv xgb`KTd(Y.r>6)H@?a*+n*-Z.AL~MD Pk7~ f^)dw*[.&+CH.)7vX]V/sZ?o^@d2+Vlw+e^lOTGvs(Q@}ir2:zg*rdKE)E$*W<6wE@nWl%vOW#%0atQZ&+B=bqA+V9Ijo4r=BN,+3B0w-1diVo@x3*C.~xWrp8%CGv,<yA,v2^JyCh((f!d~u|9/-H8^=Q[}#TU|#@Dv9n]N4!I R#e*h{V/qmaoZj{9b+?cdvbc.b[Rv<c2xC-_5Qpeq8?Z` #MvEco83,z)JLR%>zD,`:km*S^Pk[Xh!0@ `%[k+}#a+FPq^yEAuc@.QA22nFE l.d+m;i>MGO5lX&Dgv>;2pH=wDLVI~/0h$V)d2f9dhlVALg<k[;/xrUQy[a^SVRpH_j~]Oh8`j&}[D9j?',
        ];

        $secondSensorData = [
            '2;3290677;3223875;25,13,0,1,0,0;wzXbDNE/l=W|j^zPd@9HcuiD^?@;6:{J-7c^/CEUCNFhvXJ#eYfG]y41?O{th0m=,e<e70aH6W_GELu#8@y?kT@Nh-K;<a1$e|ty|WVh!:b{h0`6Xqt_nUW*m8o@hpg`y]ZN!4dEg/**hnp8UgaOY}z4RL{ ,1?<#DlZ:Wau6k;ER^)1*iI?Bpx)|@-1D,[XO>BzXjvl0NyHwiT@r&qp:s!Q|b32E8qrij%Nb/m~qfJ;ZL.lrStc%1C7&iC-}sI$f$Sl-^hR5N!v:lt;EKFHtx7z,dXyZ#y8%X{Hm:w!=`%!BbO1 Bti&pVgc?/U#v[}*Xs(~l~=Pte],D:pvm_n4Blv+ll UXCelEpLxc3UGCH9W@J9TcQb8/_V91%N3&zALiaaybrl!RFErSEKbw@P*Nv`LJ<dCNNn$qE)iNflBUN4A&rmpK}=k(^*J>Dys@Ir5uh**jwF!k9F~X 1]qGmCoSg;8D -|NX!9sswx!pFBUft5GrG6G8~s.CtX1r_K%b3$gm$M3$Y4n]m(<RzD1ZxN-]YcpWLm$^h7(zhGcl3X-D1s,L&E2-YB;=~?UB++sg6#$2t pJOQ`o#g&Si^>~z9MfKru~r=[?|0w W3@1i_,FB,^{Y<+e[NL ZyX@G$0U1xhtw5#5ui0lj^&O7mfbbA@y.0XqQgjjIJ)mx.{+&zVy/|MvrWLGEb<1n.1J>.#{fM(PM_h!Tuf&)vm]4w|}$mzfQ/]3s[Q++EE{y3y@@4B:-R3EP:A5KM[A=mme{y758Ze2bSK,QB(m2;!WbKV^vn~~}Q5KlU4WKq5KDHf!z#nTu;pW,P)o~VCN^zp4M@vPCvBLx7GfDM9-Wk]QRQkk6LceaF_d-/cA4b7U,l%SWzaY/I937GZl7Ilc8ocJLRDZwHdKwd#ae2*|Nq0ix5D]6=h})uv|}+n#K2M3kQs_v::7`?a^B_VA~])<az&UW01Tra/p&:gMv<7y~JUwo#R{Imp,Htv+,?DA1vHP)xvkRUYCVK:m7Fp;/Cz5xcwo!eE-]44iNX#m3K{FxH?vP[/UHpZo9QP9u?GUWY/w,cJWaTayYGN8AxAm^&q<cN>.oxsy[K+,|zd_]s>_3)Ka5TAocC*L39rJDwna@<%1$6~k#Dn%ic,;p=7llL1fM^xlh>UB~xr<|09!ZNtC3/Ti]/w[VA;kSl44!r{vi]Uk0w >hY,RRHU{%(|@@* `Wp=9AtlmQflFF6n1jRJ`j!.SIWb|#_eZ7^,zzb$;S.1Y]60f(4Z1<f<pbJV8IED%J eh/TX`Pko;MnU?J9^&YxEqwiEL_H#PAxP3 ZF:bv#Yi=*EC=M5E[8rxMTSd0xZiQ$i~lG*8GkCn~i=92:o,R g^[$|<:-Wk9r?SH4C;g#`*^y=EeP2OOc[WLjj4H`][F;_&zl#,Vql d0JYebc!>7t/hZs63^^x]OwG81S{;eRsMjI[(nq1bffn|3Q$.Zhrk^vXWH<7vVT(L }s#Y`tP%sAHj5cHN&@1pJ`:*o4h6x7MW<s&9V~ APM!*>)@1UG-4q4&145`&Pkp>~Y,me(bD!ti^:5J{{k1kXLuGZ#7f*%Mq>xaB0V=LDSPR0}7NQ<=)jd~&9%6ssU_#|*]jh~8t;lphC<:395:ZO1^a2b~WvdU0=_F@x&GM*s/{<y=gCQwn&UJ=@NydP%]!F~|0(@.MNTfRI@r-^U 0Yo_GiE&r!qBoSl7>lT_|m`.1OE:_{:^%GNR,eZwT&<3s@}GPwfg7=C|A?N1w~e&+UVlZl1?hKQFs8p1GQCa>d^15xik>X,!.7:^43<# }!R5#<^^}8N4N%Z!of$=u-A5Q@KvJ6J~pSf )n ZT7$t..H.5r`t%x3qe/N0L&<m=:XNY1_0Nlb*B_i//tQv.1t@f#ZSWY#9a+Ri()@wG`(*ke[}w=9(Jb@uoX_4;Gv&anWz3?NZ.] Q5AKfl/)Ia9!i$g(U_72HCnPb1CZP@l)*pzu1Eu*PG[=mc=-rT?,^!$-k35PMG 6r+ZVoIPnJ, BOi68;Uj7iU%g+e+PAJzXKDOf/Lz<Y4/5:Xz`<Wk6)5Y.eYc5*^zVh9evJaXK!*D?A@~,+Et%seA4p`%I{vxo~s<nzP*qs6,i8/fEz^Qn41&O0r=Z#)ZUs6[ta|i5Z# ~:}VPc<udD0!#kX.bt.hQkyEsJ9a>39Sen$`)y jH~!7Y?8Q-yN3y5H%NZkiJw})CQ8^R!Lw#wujte^:v7]K=mvQ(pPC)p: 4QNC`#7R`eJ}ijR(9FwrQXrGZIz_gt8dL^Plh:2i|dBz;D2sq5hX@+h;eD_f<4i[!B?FJQJ%&5MLb*kekvQcc4P~$>}g|]OJW-3<w?:-@Bap!0Okb@=9p}[_ `y=~ gg*^r~S,4 :Cc@>V!TZ7n%os_B!,G34Q&E[CaMAumU9Wq{LZ#rxa<{iL= F)5=7s hRek%3L[ww5]xI8*=ErUu:4roj|X)D#}&Z`/O0{%TcwleYV5H<!|;eD 0]Od|}X=OIq4eQ7Sg5T)M+<z#>,!bt`[PW)+OU9~D:jArYH',
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $sensorData[$key]]), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $sensorDataHeaders);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $this->seleniumOptions->recordRequests = true;
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            $selenium->http->removeCookies();
//            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://www.childrensplace.com/us/home/login");
            $login = $selenium->waitForElement(WebDriverBy::id('emailAddress'), 5);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//form[@name='LoginForm']//button[@type='submit']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$btn) {
                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $btn->click();

            $pass = $selenium->waitForElement(WebDriverBy::id('password'), 5);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//form[@name='LoginForm']//button[@type='submit']"), 0);
            $this->savePageToLogs($selenium);

            if (!$pass || !$btn) {
                return $this->checkErrors();
            }

            $pass->sendKeys($this->AccountFields['Pass']);
            sleep(7);
            $this->savePageToLogs($selenium);
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            $this->requestId = $responseData = null;

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                //$this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                //$this->logger->debug("xhr response {$n} body: " . json_encode($xhr->response->getBody()));
                if (strpos($xhr->request->getUri(), '/account/v2/auth/login') !== false) {
                    //$this->logger->debug("xhr response {$n}");
                    $this->requestId = $xhr->request->getHeaders()['tcp-trace-request-id'] ?? null;
                    $responseData = $xhr->response->getBody();

                    break;
                }
            }
            $this->logger->debug("xhr requestId: $this->requestId");
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            return $responseData;
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        $key = 'children_abck';
        $result = Cache::getInstance()->get($key);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".childrensplace.com");

            return null;
        }

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $this->seleniumOptions->recordRequests = true;
            $resolutions = [
//                [1152, 864],
//                [1280, 720],
                [1280, 768],
//                [1280, 800],
//                [1360, 768],
//                [1366, 768],
//                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->http->removeCookies();
            $selenium->keepCookies(false);
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->GetURL("https://www.childrensplace.com/us/home/login");
            $login = $selenium->waitForElement(WebDriverBy::id('emailAddress'), 5);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
//                    'bm_sz',
                    '_abck',
                ])) {
                    continue;
                }

                $result = $cookie['value'];
                $this->logger->debug("set new _abck: {$result}");
                Cache::getInstance()->set($key, $cookie['value'], 60 * 60 * 20);

                $this->http->setCookie("_abck", $result, ".childrensplace.com");

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (UnknownServerException | SessionNotCreatedException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }

    private function switchToCurl(): void
    {
        $this->logger->notice(__METHOD__);
        $this->curlBrowser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->curlBrowser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->curlBrowser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->curlBrowser->LogHeaders = true;
        $this->curlBrowser->SetProxy($this->http->GetProxy());
    }
}
