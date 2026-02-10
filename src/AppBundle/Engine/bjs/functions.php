<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBjs extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private $accountStatus;
    private $question = false;

    private $headers = [
        'Accept'          => 'application/json, text/plain, */*',
        'Content-Type'    => 'application/json',
        'x-ibm-client-id' => '7bf4a236-423b-4565-b221-3d51fbce1cbe',
        'Origin'          => 'https://www.bjs.com',
        'Referer'         => 'https://www.bjs.com/',
    ];

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.bjs.com/';

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $response = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($response) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Email Address is missing ‘@’', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        if (!$this->selenium()) {
            return $this->checkErrors();
        }

        return true;
//        $this->http->GetURL('https://www.bjs.com/signIn');
        /*
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $sensorDataUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#");
        if (!$sensorDataUrl) {
            $this->logger->error("sensor_data URL not found");
            return $this->checkErrors();
        }
        $this->http->NormalizeURL($sensorDataUrl);
        $this->sendSensorData($sensorDataUrl);
        */

        // BJs_test_group
        //$this->http->setCookie("BJs_test_group", "B", "www.bjs.com");
        $data = [
            'logonId'       => $this->AccountFields['Login'],
            'logonPassword' => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.bjs.com/digital/live/api/v1.3/storeId/10201/login', json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $message = $this->http->FindSingleNode('//div[contains(text(), "Either the Email Address or Password entered is incorrect.")]');

        if ($this->question == true) {
            return false;
        }

        if (
            isset($response->moreInformation) && $response->moreInformation == 'Un-Authorized; errorCode-3039'
            || $message == 'Either the Email Address or Password entered is incorrect.'
        ) {
            throw new CheckException('Either the Login ID or password entered is incorrect.', ACCOUNT_INVALID_PASSWORD);
        }

        if (isset($response->httpMessage) && $response->httpMessage == 'Person account is Disabled') {
            throw new CheckException('Your account is temporarily locked, please try to login later', ACCOUNT_LOCKOUT);
        }

        if ($this->loginSuccessful()/* && strtolower($primaryEmailId) == strtolower($this->AccountFields['Login'])*/) {
            return true;
        }

        $response = $this->http->JsonLog();
        $errors = $response->erros ?? null;

        if (isset($errors) && count($errors) > 0) {
            foreach ($errors as $error) {
                $userMessage = $error->userMessage;

                if (strstr($userMessage, "Sorry, APIC runtime error occured")) {
                    throw new CheckException($userMessage, ACCOUNT_PROVIDER_ERROR);
                }
            }

            $this->sendNotification('refs #23950 bjs - need to check loginSuccessful // IZ');
        }

        // error not a member loyalty program
        if ($this->accountStatus === '') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $firstName = $response->getMemberInfoResponse->firstName ?? null;
        $lastName = $response->getMemberInfoResponse->lastName ?? null;

        if ($firstName && $lastName) {
            $this->SetProperty('Name', beautifulName($firstName . ' ' . $lastName));
        } elseif ($firstName) {
            $this->SetProperty('Name', beautifulName($firstName));
        }
        // Last 12 months
        if (isset($response->getMemberInfoResponse->perksLast12Redem)) {
            $this->SetProperty('Last12months', '$' . number_format($response->getMemberInfoResponse->perksLast12Redem, 2));
        }
        // Total Used
        if (isset($response->getMemberInfoResponse->perksCurrMemRedem)) {
            $this->SetProperty('TotalUsed', '$' . number_format($response->getMemberInfoResponse->perksCurrMemRedem, 2));
        }

        // Membership Type
        $MembershipType = $response->getMemberInfoResponse->membershipType ?? null;

        if ($MembershipType && $MembershipType == 'IP') {
            $this->SetProperty('MembershipType', 'BJ\'s Perks Rewards');
        } elseif ($MembershipType) {
            $this->sendNotification("refs #4891 New Membership Type //MI");
        }
        // Spend to Next Award
        if (isset($response->getMemberInfoResponse->perksSpendNeeded)) {
            $this->SetProperty('SpendNextAward', '$' . number_format($response->getMemberInfoResponse->perksSpendNeeded, 2));
        }
        // Earnings Balance - (My Earnings Balance)
        if (isset($response->getMemberInfoResponse->perksTowardNextCert)) {
            $this->SetProperty('EarningBalance', '$' . number_format($response->getMemberInfoResponse->perksTowardNextCert, 2));
        }
        // Balance - (Total Awards Available)
        $this->http->GetURL('https://api.bjs.com/digital/live/api/v1.0/storeId/10201/login/person');
        $response = $this->http->JsonLog(null, 0);
        $this->SetBalance($response->totalAwards ?? null);
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        $mfaResponseData = $this->http->JsonLog($this->State['mfaResponseData']);
        $responseDataURL = $this->State['mfaURL'];
        unset($this->State['mfaURL']);
        unset($this->State['mfaResponseData']);
        // https://api.bjs.com/digital/live/api/v1.0/auth/user/risk/759bb345-8034-420e-9438-1c331b555507/device/mfa
        // https://api.bjs.com/digital/live/api/v1.0/auth/user/risk/759bb345-8034-420e-9438-1c331b555507/mfa
        $data = [
            "mfaToken" => $mfaResponseData->mfaToken,
            "otp"      => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $headers = [
            "Accept"                => "application/json, text/plain, */*",
            "x-queueit-ajaxpageurl" => "https%3A%2F%2Fwww.bjs.com%2FsignIn",
            //            "x-ibm-client-id"       => "7bf4a236-423b-4565-b221-3d51fbce1cbe",
            "Content-Type"          => "application/json",
            "Origin"                => "https://www.bjs.com",
            "Referer"               => "https://www.bjs.com/",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL(str_replace("device/mfa", "mfa", $responseDataURL), json_encode($data), $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();

        // Either the code is not correct or the code could not be authenticated
        if (isset($response->errors[0]->internalMessage) && $response->errors[0]->internalMessage == 'otpError') {
            $this->AskQuestion($this->Question, "Either the code is not correct or the code could not be authenticated", 'Question');

            return false;
        }

        if ($this->loginSuccessful()/* && strtolower($primaryEmailId) == strtolower($this->AccountFields['Login'])*/) {
            return true;
        }

        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.bjs.com/digital/live/api/v1.4/login/memberdetails', "{}", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $this->accountStatus = $response->getMemberInfoResponse->accountStatus ?? null;

        $email = $response->getMemberInfoResponse->email ?? null;
        $this->logger->debug('[EMAIL]: ' . $email);

        if (
            $this->accountStatus
            && (
                strtolower($email) == strtolower($this->AccountFields['Login'])
                || strtolower($this->AccountFields['Login']) == 'rremak@hotmail.com' && strtolower($email) == 'jremak@hotmail.com'
            )
        ) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.bjs.com/signIn");
            $loginInput = $selenium->waitForElement(WebDriverBy::id('emailLogin'), 7);

            if (!$loginInput) {
                $this->savePageToLogs($selenium);

                return false;
            }

            $passInput = $selenium->waitForElement(WebDriverBy::id('inputPassword'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sign-in-submit-btn")]'), 0);

            $loginInput->click();
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->click();
            $passInput->clear();
            $passInput->sendKeys($this->AccountFields['Pass']);

            $rememberMe = $selenium->waitForElement(WebDriverBy::xpath("//label[span[contains(text(), \"Remember Email\")]]"), 0);

            if ($rememberMe) {
                $rememberMe->click();
            }
            $this->savePageToLogs($selenium);

            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(@class, "login-state") and not(contains(text(), "Sign in"))]
                | //div[contains(text(), "Either the Email Address or Password entered is incorrect.")]
                | //p[contains(text(), "Get Verified with Two-Step Authentication")]
                | //span[@id = "userLink"]
                | //div[contains(@class, "UserWrapper__UserNameContainer")]
            '), 10);

            if ($selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0)) {
                $selenium->waitFor(function () use ($selenium) {
                    return !$selenium->waitForElement(WebDriverBy::xpath('//*[@id = "sec-overlay" or @id = "sec-text-if"]'), 0);
                }, 120);
                $this->savePageToLogs($selenium);

                $loginInput = $selenium->waitForElement(WebDriverBy::id('emailLogin'), 7);

                if (!$loginInput) {
                    $this->savePageToLogs($selenium);

                    return false;
                }

                $passInput = $selenium->waitForElement(WebDriverBy::id('inputPassword'), 0);
                $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(@class, "sign-in-submit-btn")]'), 0);

                $loginInput->click();
                $loginInput->clear();
                $loginInput->sendKeys($this->AccountFields['Login']);
                $passInput->click();
                $passInput->clear();
                $passInput->sendKeys($this->AccountFields['Pass']);
                $this->logger->debug("click Log-in one more time");
                $btn->click();

                $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(@class, "login-state") and not(contains(text(), "Sign in"))]
                    | //div[contains(text(), "Either the Email Address or Password entered is incorrect.")]
                    | //p[contains(text(), "Get Verified with Two-Step Authentication")]
                    | //span[@id = "userLink"]
                    | //div[contains(@class, "UserWrapper__UserNameContainer")]
                '), 10);
                $this->savePageToLogs($selenium);
            }

            $this->savePageToLogs($selenium);

            if ($emailOption = $selenium->waitForElement(WebDriverBy::xpath('//label[input[@value="email"] and contains(., "@")]'), 0)) {
                $email = $emailOption->getText();

                $selenium->driver->executeScript('
                    let oldXHROpen = window.XMLHttpRequest.prototype.open;
                    window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                        this.addEventListener("load", function() {
                            if (/device\/mfa/g.exec(url)) {
                                localStorage.setItem("mfaURL", url);
                                localStorage.setItem("mfaResponseData", this.responseText);
                            }
                        });
                        return oldXHROpen.apply(this, arguments);
                    };
                ');

                $emailOption->click();

                $nextBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "NEXT")]'), 0);
                $this->savePageToLogs($selenium);

                if (!$nextBtn) {
                    return false;
                }

                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                    $this->Cancel();
                }

                $nextBtn->click();

                $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Please enter your 6-digit authentication code to sign in")]'), 10);
                $this->savePageToLogs($selenium);

                $responseData = $selenium->driver->executeScript("return localStorage.getItem('mfaResponseData');");
                $this->logger->info("[Form mfaResponseData]: " . $responseData);
                $responseDataURL = $selenium->driver->executeScript("return localStorage.getItem('mfaURL');");
                $this->logger->info("[Form mfaURL]: " . $responseDataURL);

                $this->State['mfaResponseData'] = $responseData;
                $this->State['mfaURL'] = $responseDataURL;

                $this->Question = "Please enter the 6-digit authentication code which was sent to {$email} to sign in";

                if (!\AwardWallet\Engine\bjs\QuestionAnalyzer::isOtcQuestion($this->Question)) {
                    $this->sendNotification("Need to check sq");
                }

                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                $this->question = true;
            }

            if (!$this->question && $this->http->FindSingleNode('//span[@id = "userLink"] | //div[contains(@class, "UserWrapper__UserNameContainer")]')) {
                $selenium->http->GetURL("https://www.bjs.com/account/accountDetails");
                $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(@class, "login-state") and not(contains(text(), "Sign in"))]
                    | //div[contains(text(), "Either the Email Address or Password entered is incorrect.")]
                    | //p[contains(text(), "Get Verified with Two-Step Authentication")]
                    | //span[@id = "userLink"]
                    | //div[contains(@class, "UserWrapper__UserNameContainer")]
                '), 10);
                $this->savePageToLogs($selenium);
            }// if (!$this->question && $this->http->FindSingleNode('//span[@id = "userLink"] | //div[contains(@class, "UserWrapper__UserNameContainer")]')) {

            if (!$this->http->FindSingleNode('//span[contains(@class, "login-state") and not(contains(text(), "Sign in"))]
                    | //div[contains(text(), "Either the Email Address or Password entered is incorrect.")]')) {
                $selenium->http->GetURL("https://api.bjs.com");
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }
        /*catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }*/ finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return true;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function sendSensorData($sensorDataUrl)
    {
        $this->logger->notice(__METHOD__);

        $sensorData = [
            '7a74G7m23Vrp0o5c9142151.68-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/87.0.4280.141 Safari/537.36,uaend,12147,20030107,ru,Gecko,3,0,0,0,397711,3096785,1920,1050,1920,1080,1920,511,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7612,0.247973864123,808201548392.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,1038,113,0;0,-1,0,0,1175,-1,0;0,-1,0,0,1175,-1,0;-1,2,-94,-102,0,-1,0,0,1038,113,0;0,-1,0,0,1175,-1,0;0,-1,0,0,1175,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,234,-1,-1,-1;-1,2,-94,-109,0,233,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.bjs.com/-1,2,-94,-115,1,32,32,234,233,0,467,3255,0,1616403096785,20,17291,0,0,2881,0,0,3258,467,0,6B472FCE1905341AE7D9B96B7B799B66~0~YAAQRJl6XFWtBVV4AQAASuYhWQVCRS2nBSWOa8YqV8c2bOQ0gQ19WyOjcdOrRvZ8bvcSLhpeUOIZ6GVPsHKk2JO6P/C5N8Bsv0G6CBDczt241Uqif11BaNxo94k/mhWFbJ14dSzmY0ONMOgr+iECJVE9vygGA0qhtUUhd/W03uJ3ufxmMAAtAwrYpgTjybTZjt069KTzxbD0AWpFzXyzSnvjy12Ao/tizK0q43ALvO20cFFxNHctCC9OdTsvPlnvuCyoRq11WiwxwcbX0xUaTvAfWhdwl74UimvTImmEdc91pv6rcBBfyIBcr6yQKvCea0bJxpHvwHgNh9LyfdLgb6UAcc5YXqWv1EJKGtFrupItf8X4wFgLq3DC2Qp3u/Nm4Xf7F6P1hhuzfcGtKPxjh6ZcSF4=~-1~||1-YNkoaLdcCA-1500-10-1000-2||~-1,40377,842,809025221,30261689,PiZtE,21231,68-1,2,-94,-106,8,1-1,2,-94,-119,30,34,34,35,364,55,36,32,34,6,6,7,12,151,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.4dae625a01a19,0.b10ba6f353714,0.da106da11ef3f,0.8388d15996949,0.cf2249bfc981c,0.e3de5e085b551,0.7c4bba321cc4f,0.b0ce4ef2c02a1,0.f0253826e8e12,0.a546622a22df;124,43,12,24,47,16,22,49,25,92;872,931,430,1030,1732,533,868,1967,713,3337;6B472FCE1905341AE7D9B96B7B799B66,1616403096785,YNkoaLdcCA,6B472FCE1905341AE7D9B96B7B799B661616403096785YNkoaLdcCA,1500,1500,0.4dae625a01a19,6B472FCE1905341AE7D9B96B7B799B661616403096785YNkoaLdcCA15000.4dae625a01a19,198,167,141,145,250,26,221,96,184,168,251,189,162,146,10,15,103,187,81,68,102,7,6,41,169,85,173,3,233,79,164,60,808,0,1616403100040;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-705080415;1993109966;dis;,7,8;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5585-1,2,-94,-116,83613342-1,2,-94,-118,130130-1,2,-94,-129,282db4276d3b225d73eb4b7940c97b1cc84acb6ecb22ecab4cbe85b5a531004e,1,5c8cf8750d5aa2cdfdd0d627e7e37b97006b59327cf682ce743d825eda183ed2,Google Inc.,ANGLE (Intel Open Source Technology Center, Mesa DRI Intel(R) HD Graphics 630 (KBL GT2), OpenGL 4.6 core),deccf2cc89f0263e782f462c55c4092b5905a39efd0433460e08827135d727aa,35-1,2,-94,-121,;7;14;0',
        ];

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $this->http->RetryCount = 0;
        $data = [
            'sensor_data' => $sensorData[$key],
        ];
        $headers = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }
}
