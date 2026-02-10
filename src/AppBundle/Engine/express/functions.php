<?php

use AwardWallet\Engine\ProxyList;

// Feature #5750
class TAccountCheckerExpress extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'express')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt === 3) {
            $this->http->SetProxy($this->proxyReCaptcha(), false);
        } else {
            $this->http->SetProxy($this->proxyDOP(AwardWallet\Engine\Settings::DATACENTERS_USA), false);
        }

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.express.com/my-account", [], 20);

        if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://www.express.com/my-account", [], 20);
        }

        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter valid e-mail address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter valid e-mail address", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://www.express.com/login");

        if (strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://www.express.com/login");
        }
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200 || $this->http->FindSingleNode('//title[contains(text(), "Express.com Site Down")]')) {
            return $this->checkErrors();
        }

        $sensorPostUrl =
            $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorPostUrl) {
            $this->logger->error("sensor data url not found");
//            return false;
        } elseif ($sensorPostUrl) {
            $this->http->NormalizeURL($sensorPostUrl);

            return $this->selenium();

            $data = [
                "sensor_data" => "7a74G7m23Vrp0o5c9290481.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:94.0) Gecko/20100101 Firefox/94.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,402878,1226513,1536,871,1536,960,1536,482,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6010,0.677506008338,818700613256.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,2398,520,0;-1,2,-94,-102,0,-1,0,0,2398,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.express.com/login-1,2,-94,-115,1,32,32,0,0,0,0,5,0,1637401226513,-999999,17516,0,0,2919,0,0,7,0,0,F8D9206A2F08B861DDF5B1E3B64E2955~-1~YAAQzGAZuG/hdTx9AQAA5tKgPAZsVWgSRakbhmJlMRBR/vEmgU72U8uyPHn0Up9vndIWee3QBdxyz2Aw07URx6b6EXU7zTvQ1joi+CCWAvvRKluPjw3LwyqvmG2ijoCHQwXjM7sf21fYdqYC0EYGsA4sNjZjiFCWdNoLJQBIIBWMC+Qmv5YeLI3zRpwczc+sPaXHzzO/tdvD2qHOV+DUWWci3fohvVjtwVaES4ATiDuOsdNZfjhMHn/xPry8h9pqgkD3/G7oRPzhpZe2+kTSDP6Jn90Z1715Bf/UiuvNBlnMRaAL3C7rIF1zjFoghyWRjt3Jz5fwEUY25Gfp3Dc2ZA10mgaEmRSo631i2NlG0++9kQcNRhR4SyXf+q41ZSPcEvX0xQSHynl3usRV9l02mUXUXpV1rbYj7DbuF0AaI4dITLFmDtvI~0~-1~-1,40030,-1,-1,26067385,PiZtE,52246,55,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,3679521-1,2,-94,-118,85985-1,2,-94,-129,-1,2,-94,-121,;8;-1;0",
            ];
            $headers = [
                "Accept"        => "*/*",
                "Content-type"  => "application/json",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
            sleep(1);

            $data = [
                "sensor_data" => "7a74G7m23Vrp0o5c9290481.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:94.0) Gecko/20100101 Firefox/94.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,402878,1226513,1536,871,1536,960,1536,482,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6010,0.558538281279,818700613263,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,2398,520,0;-1,2,-94,-102,0,-1,0,0,1853,113,0;0,-1,0,0,2398,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.express.com/login-1,2,-94,-115,1,32,32,0,0,0,0,509,0,1637401226526,7,17516,0,0,2919,0,0,510,0,0,F8D9206A2F08B861DDF5B1E3B64E2955~-1~YAAQzGAZuC2Ddjx9AQAAZO63PAaXCiao7N7S0Ds06Daku0id2T4ZoKdKBjXrNFNAPLPZak6Y+ORrvLaOGWaorCKrSDAkuEAIpdrf8dp37YD/sBJJAj2insCxHDi0nSaqA20MjwIskW4uRNNwVWW+fE/APk3F7cjoompx1LbbPCjkgcatCCNljMwk7aY14qBzzPHfz65RSWNxmJBuEmhHxcSbKcWapwCCTOfpMhdHbKjx3xWDkcEaOMQvfG15EtdB3m8fcWUTQ8gLeQrw/6JFzOItf9351uWIX8C/CwLiwuifX2sP7TfDPe1+JXg6DxGOw75NGar2n1j4bXSuyJ9d/N2h/6hQFv5OO4K7uPKedws/B5uuwWeo6F2pyZ4wxvRdBY3o1fCeoIGj0QSHIWD4s3FDknRjLOon7zDERAcWKkaSsgNOI56O1w==~-1~||1-GFbgsgMFCt-1-10-1000-2||~-1,42042,746,-439809511,26067385,PiZtE,16025,41,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,200,0,0,0,0,0,0,0,0,0,0,0,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,3679521-1,2,-94,-118,91488-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,0,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;71;14;0",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
            sleep(1);

            $data = [
                "sensor_data" => "7a74G7m23Vrp0o5c9290481.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:94.0) Gecko/20100101 Firefox/94.0,uaend,11059,20100101,en-US,Gecko,0,0,0,0,402878,1226513,1536,871,1536,960,1536,482,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:138,vib:1,bat:0,x11:0,x12:1,6010,0.622675044311,818700613263,0,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,2398,520,0;-1,2,-94,-102,0,0,0,0,1853,113,0;0,-1,0,0,2039,520,0;1,-1,0,0,-1,883,0;0,-1,0,0,2398,520,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.express.com/login-1,2,-94,-115,1,32,32,0,0,0,0,1712,0,1637401226526,7,17516,0,0,2919,0,0,1713,0,0,F8D9206A2F08B861DDF5B1E3B64E2955~-1~YAAQzGAZuECDdjx9AQAAhPG3PAYFVUg8uHdr1ePPFCUKJ5jgOK9EV/8OvgUqzzPR5ehhqMhouBuIQXfO8TYwwVwtqeKk5s2PoiJBgNcaeEA4NSrC5QHYNJmH6bdKvFuDj8MI0/EOc+EsAK9yZfcJBzlh+YbeSSgUjRolNR1w4jNBqbT+tB3S69qHLuuOraQysOnLjFUfvhRtxIYNHgJZ/N3TYjK8n3aXA3HqSakibP2i3witoMNgfGJcjpJ6kjCc2TfzRWvWf0Dd3lySwTQ+3dPvVW+y9TR7+uj+yhxdC4kxGJwzdyuTfRrIjnGDd0uGoLVDM56Rf/eMYbjNaJ+ZX2SV10sqgXC2u2NbEJobK/+3geWJ1MTsRW3f/eKiuXY1sx3kI+pato4KaCGjuarWRVeg1hGF1ZerZbVY5Ixl41rhdyN1QZdZ8g==~-1~||1-GFbgsgMFCt-1-10-1000-2||~-1,42555,746,-439809511,26067385,PiZtE,25751,68,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,200,0,0,0,0,0,0,0,0,0,0,0,0,0,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.62ccbcc6f77b8,0.80a34ed6019f6,0.2b7cbae5251db,0.3cc02b36563538,0.f2590556b22a48,0.51599eb4427d58,0.e66e5d1aebd8d,0.9ab436f102ef2,0.8a306121027698,0.9706ce43838538;0,1,1,1,1,2,0,1,0,0;0,2,5,0,1,8,1,5,1,0;F8D9206A2F08B861DDF5B1E3B64E2955,1637401226526,GFbgsgMFCt,F8D9206A2F08B861DDF5B1E3B64E29551637401226526GFbgsgMFCt,1,1,0.62ccbcc6f77b8,F8D9206A2F08B861DDF5B1E3B64E29551637401226526GFbgsgMFCt10.62ccbcc6f77b8,60,225,38,131,49,5,8,128,150,221,46,117,111,212,123,43,121,223,156,233,195,215,143,30,116,81,217,96,220,44,168,200,484,0,1637401228238;-1,2,-94,-126,-1,2,-94,-127,11133333331333333333-1,2,-94,-70,50988738;1681675197;dis;;true;true;true;-300;true;24;24;true;false;1-1,2,-94,-80,5258-1,2,-94,-116,3679521-1,2,-94,-118,125956-1,2,-94,-129,05d8487f76a54cd7ab6067b1d89de837fca940f0d4135e7ea77bea438463eed3,2,a37e44b211f9405d2c2fe59f68a6feaca4e73efdea9dd9f72ce5700b40e8a34e,Intel Inc.,Intel(R) HD Graphics 400,faa364726c2d467d321c3121e9ca9e86c8e63c3eae47970c432c83f0c60bbc6e,25-1,2,-94,-121,;5;14;0",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL($sensorPostUrl, json_encode($data), $headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
            sleep(1);

//            $this->http->GetURL("https://www.express.com/login");
        }

        $headers = [
            "Accept"                         => "*/*",
            "Content-type"                   => "application/json",
            "Accept-Encoding"                => "gzip, deflate, br",
            "x-exp-rvn-cache-key"            => "75b8e86c29a35c465b7ba8947f42f43c33c5cfa32e803f34be90f9b1d5678d0a",
            "x-exp-rvn-query-classification" => "login",
            "x-exp-rvn-cacheable"            => "false",
            "x-exp-request-id"               => "0d1b723b-4e74-4c7d-9339-12f165aed09b",
            "origin"                         => "https://www.express.com",
            "Referer"                        => "https://www.express.com/login",
        ];
        $captcha = $this->parseCaptcha();

        if ($captcha == false) {
            return false;
        }
        $data = '{"operationName":"login","variables":{"doNotMerge":false,"captchaToken":"","googleReCaptchaToken":"' . $captcha . '","action":"ACTION_LOGIN_SIGN_IN_BTN","loginName":"' . $this->AccountFields['Login'] . '","password":"' . addcslashes($this->AccountFields['Pass'], '"') . '"},"query":"mutation login($loginName: String!, $password: String!, $doNotMerge: Boolean, $captchaToken: String, $googleReCaptchaToken: String, $action: String) {\n  login(loginName: $loginName, password: $password, doNotMerge: $doNotMerge, captchaToken: $captchaToken, googleReCaptchaToken: $googleReCaptchaToken, action: $action) {\n    loginStatus\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.express.com/graphql", $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We're working on it
        if ($message = $this->http->FindSingleNode('//title[contains(text(), "Express.com Site Down")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//title[contains(text(), "502 Proxy Error")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // We suddenly got a crazy rush for the hottest styles.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We suddenly got a crazy rush for the hottest styles.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently at capacity, but as soon as a spot opens up we'll put you right through.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our site is currently at capacity, but as soon as a spot opens up we\'ll put you right through.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//li[contains(text(), "Message: The specified bucket does not exist")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // provider error
        if ($message = $this->http->FindPreg('/"error":"(OUR SERVICES ARE DOWN, PLEASE TRY AGAIN LATER\.)"/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // retries
        if ($this->http->currentUrl() == 'https://www.express.com/login' && $this->http->Response['code'] == 403) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(4, 7);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // OK - "{"data":{"login":{"loginStatus":"LOGGED_IN","__typename":"AccessToken"}}}"
        if (isset($response->data->login->loginStatus) && $response->data->login->loginStatus == "LOGGED_IN") {
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        if (
            $this->http->FindSingleNode("//p[contains(text(), 'WELCOME BACK')]")
            || (
                $this->http->getCookieByName("accessToken", ".www.express.com")
                && !$this->http->FindSingleNode('//div[
                        contains(text(), "We do not have that e-mail address and password combination on file.")
                        or contains(text(), "We experienced a problem submitting your information")
                    ]
                    | //div[@id = "submit-button-error"]
                ')
            )
        ) {
            return $this->loginSuccessful();
        }
        // We do not have that e-mail address and password combination on file. Please try again.z
        if (strstr($this->http->Response['body'], '\"propertyName\":\"password\",\"message\":\"Please enter your correct password.\"')
            || strstr($this->http->Response['body'], '"errors":[{"message":"503 - {\"httpStatus\":\"SERVICE_UNAVAILABLE\",\"statusCode\":503,\"message\":null}"')
            || strstr($this->http->Response['body'], '\"message\":\"We do not have that e-mail address and password combination on file. Please try again.\"')
            || strstr($this->http->Response['body'], '\"message\":\"invalid credentials\"')
            || strstr($this->http->Response['body'], '"message":"Please use the \"Forgot your password?\" link below to reset your password before signing in."')
            || strstr($this->http->Response['body'], '"500 - {\"httpStatus\":\"INTERNAL_SERVER_ERROR\",\"fieldErrors\":[],\"errors\":[{\"code\":\"INTERNAL_SERVER_ERROR\",\"message\":\"Error Logging In User\"}]}"')
            || strstr($this->http->Response['body'], '\"message\":\"Member without Tier. Message id = ')
            || strstr($this->http->Response['body'], 'errors\":[{\"code\":\"BAD_REQUEST\",\"message\":\"Loyalty Member not found.\"}]}"')
            || strstr($this->http->Response['body'], '\"message\":\"Loyalty Member not found with the ' . $this->AccountFields['Login'])
            || (strstr($this->http->Response['body'], '\"message\":\"Loyalty Member') && strstr($this->http->Response['body'], ' is of status Terminated\"'))
            || (strstr($this->http->Response['body'], '{"errors":[{"message":"500 - {\"timestamp\":\"') && strstr($this->http->Response['body'], '\",\"status\":500,\"error\":\"Internal Server Error\",')) // AccountID: 2964416
            || $this->http->FindSingleNode('//div[contains(text(), "We do not have that e-mail address and password combination on file.")]')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException('We do not have that e-mail address and password combination on file. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }
        // Wrong captcha
        if (strstr($this->http->Response['body'], '"showCaptcha\":true,\"statusCode\":401,\"message\":\"Captcha Failed\"')) {
            $this->captchaReporting($this->recognizer, false);

            throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
        }

        if (
            strstr($this->http->Response['body'], ' E:AUTOMATION_002_003","httpStatus":"CONFLICT","message":"We experienced a problem submitting your information","statusCode":409')
            || strstr($this->http->Response['body'], ' E:UNEXPECTED_ENVIRONMENT_003","httpStatus":"CONFLICT","message":"We experienced a problem submitting your information","statusCode":409')
            || strstr($this->http->Response['body'], '_EUNEXPECTED_ENVIRONMENT_003_M003","httpStatus":"CONFLICT","message":"We experienced a problem submitting your information","statusCode":409')
            || strstr($this->http->Response['body'], '_EAUTOMATION_002_003_M003","httpStatus":"CONFLICT","message":"We experienced a problem submitting your information","statusCode":409')
            || strstr($this->http->Response['body'], '_E000_M003","httpStatus":"CONFLICT","message":"We experienced a problem submitting your information","statusCode":409')
            || $this->http->FindSingleNode('//div[contains(text(), "We experienced a problem submitting your information")]')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 5, "We experienced a problem submitting your information");
        }

        if ($message = $this->http->FindSingleNode("//div[@id = 'submit-button-error']")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'There is an error or two in your inputs above.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'Please use the "Forgot your password?" link below to reset your password before signing in.')) {
                throw new CheckException("Please reset your password before signing in.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            $this->captchaReporting($this->recognizer);
            $this->DebugInfo = "sensor_data";

            return false;
        }

//        $this->logger->debug($this->http->Response['body']);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        $customer = $response->data->customer->customer->customerInfo;
        // Name
        $this->SetProperty('Name', beautifulName("{$customer->firstName} {$customer->lastName}"));
        // NEXT MEMBER I.D.
        $this->SetProperty("ExpressNextID", $customer->loyaltyNumber);
        // until your next Reward
        $this->SetProperty("NeededToNextReward", $customer->totalPointsForNextReward - $customer->pointsBalance);
        // points needed to reach EXPRESS NEXT A-List.
        if ($customer->pointsToNextTier > 0) {
            $this->SetProperty("PointsToNextLevel", $customer->pointsToNextTier);
        }

        // You're on the A-List!
        if ($customer->aList === true) {
            $this->SetProperty('Tier', 'A-List');
        } elseif ($customer->aList === false) {
            $this->SetProperty('Tier', 'Influencer');
        }

        // 1630pts earned
        $this->SetBalance($customer->pointsBalance);
        // Expiration Date  // refs #6336, 12895
        if ($customer->pointsBalance > 0) {
            $this->parseExpDate();
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Click ACCEPT to start earning Rewards with EXPRESS NEXT.')]")) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Express.com Site Down
            if ($message = $this->http->FindSingleNode("//title[contains(text(), 'Express.com Site Down')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            /*
             * Oops! Something went wrong with creating your EXPRESS NEXT account.
             * Please contact customer service at (888) EXP-1980 to resolve the issue.
             */
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Oops! Something went wrong with creating your EXPRESS NEXT account.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // An error occurred retrieving your rewards detail. Please check back later.
            if ($message = $this->http->FindPreg("/An error occurred retrieving your rewards detail\. Please check back later\./")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 2761072, 2046809, 3394486, 2953298, 2122653, 1358400, 1802192
            /*
            if (!empty($this->Properties['Tier'])
                && $this->Properties['Tier'] == 'Express Next'
                && $this->http->FindPreg('/"loginStatus":true,"loyaltyNumber":null,"loyaltyStatus":"NO_LOYALTY_MEMBER","pendingRewardsCount":null,"phoneNumber":null,"pointsBalance":null,"pointsToNextTier":null,/')
            ) {
                $this->SetBalanceNA();
            }
            */
        }

        $rewards = $customer->rewards ?? [];

        if (!$rewards) {
            $this->logger->debug('Empty rewards, nothing to parse');

            return;
        }

        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $name = $reward->shortDescription;
            $balance = $reward->displayAmount;
            $exp = strtotime($reward->expirationDate);

            if ($name && $balance && $exp) {
                $this->AddSubAccount([
                    'Code'           => md5($balance . $name) . $exp,
                    'DisplayName'    => $name,
                    'Balance'        => $balance,
                    'ExpirationDate' => $exp,
                ]);
            } else {
                $this->logger->notice('Name, balance or exp date of a reward is empty');
            }
        }
    }

    public function parseExpDate()
    {
        $this->logger->info(__METHOD__);
        $headers = [
            "Accept"                         => "*/*",
            "Content-type"                   => "application/json",
            "Accept-Encoding"                => "gzip, deflate, br",
            "x-exp-rvn-cache-key"            => "1cf61fbc66e4c87ff678f8e146cbb99e8ecc7c92590159c0e87cbb864d9fe93b",
            "x-exp-rvn-query-classification" => "getPointsHistory",
            "x-exp-rvn-cacheable"            => "false",
            "x-exp-request-id"               => "75cbad78-9e85-4ada-91ea-48e823eb50c2",
            "origin"                         => "https://www.express.com",
        ];
        $data = '{"operationName":"getPointsHistory","variables":{},"query":"query getPointsHistory {\n  getPointsHistory {\n    pointsHistoryList {\n      dateTime\n      earnedPoints\n      eventName\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->PostURL('https://www.express.com/graphql', $data, $headers);

        $response = $this->http->JsonLog(null, 3, true);
        $hist = ArrayVal(ArrayVal(ArrayVal($response, 'data'), 'getPointsHistory'), 'pointsHistoryList', []);

        if (!isset($this->Balance) || !$this->Balance) {
            $this->logger->info('Balance is either not set or zero, nothing to parse as an exp date');

            return;
        }

        $points = $this->Balance;

        foreach ($hist as $index => $item) {
            $earned = ArrayVal($item, 'earnedPoints');

            if ($earned < 0) {
                continue;
            }

            $points -= $earned;

            if ($points <= 0) {
                $date = ArrayVal($item, 'dateTime');
                $date = str_replace('-', '/', $date); // month / day / year expected
                $date = strtotime($date);

                if ($date) {
                    $this->SetProperty("EarningDate", date('j M y', $date));
                    $this->SetExpirationDate(strtotime("+1 year", $date));
                }
                $toExpire = $points + ArrayVal($item, 'earnedPoints');

                if ($this->Balance == $toExpire && $index == 0)
                    ; else {
                        $this->SetProperty("PointsToExpire", $toExpire);
                    }

                break;
            }
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $retry = false;
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
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
//            $selenium->setScreenResolution($resolution);

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.express.com/login');
            } catch (TimeOutException $e) {
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $selenium->waitForElement(WebDriverBy::xpath("
                //input[@id = 'login-form-email-addr']
                | //h1[contains(text(), '429 Too Many Requests')]
            "), 0);

            $loginInput = $selenium->waitForElement(WebDriverBy::id("login-form-email-addr"), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                $this->logger->error('something went wrong');

                if ($this->http->FindSingleNode("//h1[contains(text(), '429 Too Many Requests')]")) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(4, 7);
                }

                return $this->checkErrors();
            }// if (!$loginInput || !$passwordInput)
            // refs #14450
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // save page to logs
            $this->savePageToLogs($selenium);
            $btnLogIn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Sign in') and not(@disabled)]"), 10);

            if (!$btnLogIn) {
                $this->logger->error('something went wrong');

                return $this->checkErrors();
            }

            $btnLogIn->click();

            sleep(3);

            $selenium->waitForElement(WebDriverBy::xpath("
                //p[contains(text(), 'WELCOME BACK')]
                | //div[contains(text(), 'We do not have that e-mail address and password combination on file.')]
                | //div[contains(text(), 'We experienced a problem submitting your information')]
                | //div[@id = 'submit-button-error']
            "), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
        $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if (
                $retry
                && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            ) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return $currentUrl;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            "Accept"                         => "*/*",
            "Content-type"                   => "application/json",
            "Accept-Encoding"                => "gzip, deflate, br",
            "x-exp-rvn-cache-key"            => "09c0b45ea53902ce2742bbbe5a909fa989c1a08a96cc7e876aaf14d4038bac1f",
            "x-exp-rvn-query-classification" => "customer",
            "x-exp-rvn-cacheable"            => "false",
            "x-exp-request-id"               => "75cbad78-9e85-4ada-91ea-48e823eb50c2",
            "origin"                         => "https://www.express.com",
        ];
        $data = '{"operationName":"customerInfo","variables":{},"query":"query customerInfo {\n  customer {\n    customer {\n      customerInfo {\n        aList\n        associateLogin\n        associateStatus\n        associateNumber\n        birthDayReward\n        bopisEligibleStatus\n        country\n        emailAddress\n        firstName\n        fitDetails {\n          AGE\n          WEIGHT\n          SHOESIZE\n          GENDER\n          HEIGHT\n          JEANWAIST\n          BRASIZE\n          __typename\n        }\n        gender\n        id\n        externalId\n        nextTermsAndConditionsAccepted\n        lastName\n        loginStatus\n        loyaltyNumber\n        loyaltyStatus\n        pendingRewardsCount\n        phoneNumber\n        pointsBalance\n        pointsToNextTier\n        pointsToRetainCurrentTier\n        pointsTowardsAListStatus\n        postalCode\n        preferredStore\n        rewardAmountInCentsForAListReward\n        rewardAmountInCentsForNextReward\n        rewardCount\n        rewards {\n          rewardId\n          currency\n          amount\n          displayAmount\n          dateIssued\n          expirationDate\n          shortDescription\n          expirationDays\n          __typename\n        }\n        rewardsTotal\n        tierName\n        tierExpirationDate\n        tierExpirationDays\n        totalPointsForAListReward\n        totalPointsForNextReward\n        nextCreditCardHolder\n        memberRewardChoice {\n          catalogEndDate\n          catalogStartDate\n          currencyToEarn\n          displayName\n          ipCode\n          rewardId\n          __typename\n        }\n        memberRewardChoiceInDollars\n        __typename\n      }\n      __typename\n    }\n    challengeList {\n      customerChallenges {\n        challengeId\n        challengeName\n        headline\n        __typename\n      }\n      __typename\n    }\n    pointsHistoryResponse {\n      pointsHistoryList {\n        dateTime\n        eventName\n        earnedPoints\n        __typename\n      }\n      __typename\n    }\n    customerStore {\n      storeId\n      storeNumber\n      name\n      addressLine1\n      city\n      state\n      postalCode\n      country\n      phoneNumber\n      hoursOfOperation\n      weeklyHoursOfOperation {\n        monday\n        tuesday\n        wednesday\n        thursday\n        friday\n        saturday\n        sunday\n        __typename\n      }\n      bopisEligible\n      __typename\n    }\n    __typename\n  }\n}\n"}';
        $this->http->RetryCount = 1;
        $this->http->PostURL('https://www.express.com/graphql', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!isset($response->data->customer->customer)) {
            return false;
        }

        $emailAddress = $response->data->customer->customer->customerInfo->emailAddress ?? null;
        $this->logger->debug("[Email]: {$emailAddress}");

        return
            strtolower($emailAddress) == strtolower($this->AccountFields['Login'])
            || (
                $response->data->customer->customer->customerInfo->emailAddress == null
                && !empty($response->data->customer->customer->customerInfo->lastName)
                && $response->data->customer->customer->customerInfo->loginStatus === true
            )
            || in_array($this->AccountFields['Login'], [
                'ezvince@hotmail.com',
                'fyan103@outlook.com',
            ])
        ;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
//        $browser = clone $this->http;
//        $this->http->brotherBrowser($browser);
//        $browser->GetURL("https://www.express.com/cdn/static/javascripts/all.js");
//        $key = $browser->FindPreg("/grecaptcha.render\(\"exp-recaptcha\",\{sitekey:\"([^\"]+)/");
        $key = '6LeQ9cMcAAAAAFZHO6v3Hmi-3ljppHJA6RqkC97Q';

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => "https://www.express.com/login", //$this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "ACTION_LOGIN_SIGN_IN_BTN",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.express.com/login", //$this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "enterprise",
            "invisible" => 1,
            "action"    => "ACTION_LOGIN_SIGN_IN_BTN",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
