<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFamousfootwear extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.famousfootwear.com/account/dashboard';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $requestVerificationToken;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->setProxyDOP();
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
//        $this->http->GetURL('https://www.famousfootwear.com/account/sign-in');
        $this->getCookiesFromSelenium();
        $requestVerificationToken = $this->http->FindSingleNode('//form[@id="_CRSFform"]/input[@name="__RequestVerificationToken"]/@value');
        $key = $this->http->FindSingleNode("//div[@id = 'js-global-captcha-trigger']/@data-sitekey");

        /*
        if ($this->sendSensorData() === false) {
            return false;
        }
        */

        if (!$requestVerificationToken) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha($key);

        if ($captcha === false) {
            return false;
        }

        $headers = [
            "Accept"                     => "*/*",
            "__RequestVerificationToken" => $requestVerificationToken,
            "Content-Type"               => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With"           => "XMLHttpRequest",
            "x-newrelic-id"              => "VQ4BVldaCxAGXFlTAAIAUlA=",
        ];
        $data = [
            "Username"                   => strtolower($this->AccountFields['Login']),
            "Password"                   => $this->AccountFields['Pass'],
            "CaptchaToken"               => $captcha,
            "__RequestVerificationToken" => $requestVerificationToken,
        ];

        $this->http->PostURL('https://www.famousfootwear.com/api/calxa/account/login?sc_site=Famous%20Footwear', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $customerNumber = $response->CustomerNumber ?? null;
        $bazaarvoiceUserToken = $response->BazaarvoiceUserToken ?? null;
        $success = $response->Success ?? null;
        $jsonRequestBehavior = $response->JsonRequestBehavior ?? null;

        // Example: 1843836
        if ($this->http->FindPreg('/\{"CustomerNumber":"","CustomerName":".+?","BazaarvoiceUserToken":"",/')) {
            $this->SetBalanceNA();

            return false;
        }

        if (!$customerNumber
            || !$bazaarvoiceUserToken
            || $success != true
            || $jsonRequestBehavior != 1
        ) {
            $message = $response->Errors[0] ?? null;

            if ($message) {
                $this->logger->error("[message]: {$message}");

                if (
                    $message == "One or more errors occurred."
                    || $message == "An error occurred while processing this request."
                    || $message == "Unable to connect to the remote server"
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message == 'The Email Address or Password provided is incorrect1.') {
                    $this->DebugInfo = 'request blocked';

                    return false;
                }

                if (
                    strstr($message, "Your password has been reset and is no longer valid.")
                    || strstr($message, "The Email Address or Password provided is incorrect")
                    || strstr($message, "Your password has expired and is no longer valid.")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Derek, You're a STAR
        $this->SetProperty('MembershipLevel', $this->http->FindSingleNode('//span[@class="nameandtierinfo__status-name"]'));
        // Reward Cash
        // 100 POINTS TO GO
        $this->SetProperty('PointsToNextReward', $this->http->FindSingleNode('//div[contains(@class, "cxa-loyaltypointsprogress-component")]//span[@class = "circular-progress__remaining-value"]'));
        $headers = [
            "__RequestVerificationToken" => $this->requestVerificationToken,
            "Content-Type"               => "application/x-www-form-urlencoded; charset=UTF-8",
            "X-Requested-With"           => "XMLHttpRequest",
        ];
        $data = [
            "__RequestVerificationToken" => $this->requestVerificationToken,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.famousfootwear.com/api/cxa/loyalty/GetCustomerLoyalty?sc_site=Famous Footwear', $data, $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        $firstName = $response->ProfileInformation->Profile->FirstName ?? null;
        $lastName = $response->ProfileInformation->Profile->LastName ?? null;
        // Name
        if (!empty($firstName) || !empty($lastName)) {
            $this->SetProperty("Name", beautifulName($firstName . " " . $lastName));
        }
        // Rewards Number
        $this->SetProperty("RewardsNumber", $response->ProfileInformation->Profile->MemberNumber ?? null);

        $membershipTypeCode = $response->ProfileInformation->Profile->MembershipTypeCode ?? null;

        /*
        if (isset($membershipTypeCode)) {
            $this->SetProperty("MembershipLevel", $membershipTypeCode);

            if (
                $membershipTypeCode !== "REGL"
                && $membershipTypeCode !== "GOLD"
                && $membershipTypeCode !== ""
            ) {
                $this->sendNotification("New MembershipLevel");
            }
        }
        */

        $pointsBalance = $response->ProfileInformation->Profile->PointsBalance ?? null;
        $rewardsBalance = $response->ProfileInformation->Profile->RewardsBalance ?? null;

        if ($pointsBalance == $rewardsBalance) {
            // Balance - Your Points
            $this->SetBalance($pointsBalance);
        }
        $dollarsToGold = $response->ProfileInformation->Profile->DollarsToGold ?? null;
        // Only $200 until you unlock
        if (isset($membershipTypeCode)) {
            $this->SetProperty("SpendNextLevel", "$" . (int) $dollarsToGold);
        }

        $responseStatusDescription = $response->LoyaltyInformation->ResponseStatusDescription ?? null;

        if (empty($this->Properties['Name'])
           && empty($this->Properties['Balance'])
           && empty($this->Properties['MembershipLevel'])
           && $this->Properties['SpendNextLevel'] == "$200"
           && $responseStatusDescription == "Invalid Input: Missing Member Number."
        ) {
            $this->SetBalanceNA();
        } elseif (
            empty($this->Properties['Name'])
            && empty($this->Properties['Balance'])
            && empty($this->Properties['MembershipLevel'])
        ) {
            $this->sendNotification("empty properties");
        }
    }

    protected function parseReCaptcha($key)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => 'https://www.famousfootwear.com/account/sign-in',
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><(?:\/body|link)#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data url not found");

            return false;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            // 0
            "7a74G7m23Vrp0o5c9366331.75-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408719,1882008,1920,1050,1920,1080,1920,544,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7648,0.220939596110,830570941004,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,1198,0;-1,2,-94,-102,-1,-1,0,0,-1,339,0;0,-1,0,0,-1,1198,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,625,-1,-1,-1;-1,2,-94,-109,0,625,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.famousfootwear.com/-1,2,-94,-115,1,32,32,625,625,0,1250,643,0,1661141882008,17,17770,0,0,2961,0,0,644,1250,0,B9956E7772CAF2C8CE05D5A2B2F39BD8~-1~YAAQiZs+F6mqG7qCAQAArJXFwwjXGFf8nDBjk2OT28TjHI4xv2+XSiiiXZUTpPtPg9qvvJ3fDjfZMNd49+PIqHv0RHrg0qjS5f8c1/CNIlf6JwIXu6N3mL2iwB8kZV+2tMBfPAnYG2NNp+poA40bUwUlfHH6+z8dJxGwIBMrpqCL3vqJBpBBrMiMFFCeUpApkv1PJsygzY84yFv+C+ltjpY6mH1hV+5IV3BKp70yqf21oDYCQB+ELddlPh7qbJT+XPVEH7LOQGdN6g6wkwGigNsXj+GUvcs63wP2qbeIi09Wi59SNdOzHW1cTaUL7fNbcZTzYmlQgDrxbmBMdmpWn0RTsPNyhtTVsf9hmWm8+Sfs7sbtYx0Ns9li1eVnTChFJkoAkYQFJ3gDxcadPOZn4xQ=~-1~||1-gJEeRHxLrv-1-10-1000-2||~-1,39614,550,404337583,30261689,PiZtE,91143,75,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,457327863-1,2,-94,-118,92846-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;2;39;0",
            // 1
            "7a74G7m23Vrp0o5c9366331.75-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408720,3225260,1920,1050,1920,1080,1920,538,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7648,0.225632052112,830571612630,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,1739,-1,0;-1,2,-94,-102,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,1739,-1,0;-1,-1,0,0,-1,339,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.famousfootwear.com/account/sign-in?logoff=1-1,2,-94,-115,1,32,32,0,0,0,0,652,0,1661143225260,27,17770,0,0,2961,0,0,653,0,0,3CD81E639288094AA993BE3BCA9ACEFE~-1~YAAQiZs+F+xgHrqCAQAAvBTawwgO48C7mdAdNeGyFYwsi121XtWUPdDsDVLldiBy60ExOOI1Ls8eERHwmEhoY6Tat2fphQof7mlwkwXYTpeN2urtlb64C+PiieREK84C5e/xQo8bRRo3TwxPNTtDv53whHioZS/ctlMSz4XJ6fcsBMC6zo+KdSTn+3j4MGxhoFN5vSBsDqh7hEYn5A+Z7v8Ua4oJLoKSyhKq20db/5VWosp5+NMeoAuWOd79d4sqtiHGNdx8f5e9echhSa07vnjx3m2nbQadKbNmKFAGYmEGE0BtNu2iJbxb/U5h/C3jirrc/wkPr2mHFXOgG4wvg1Ws4O2wLmF06dJYLb4tQSiewLswDAW/KJwfFdc2I5oVWFhjSgpCNMnstAvQZX91PHI=~-1~||1-JMmQMlidRy-1-10-1000-2||~-1,39873,231,-970535065,30261689,PiZtE,85022,78,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,9675759-1,2,-94,-118,97416-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;3;12;0",
        ];

        $secondSensorData = [
            // 0
            "7a74G7m23Vrp0o5c9366331.75-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408719,1882008,1920,1050,1920,1080,1920,544,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7648,0.521777681260,830570941004,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,1198,0;-1,2,-94,-102,-1,-1,0,0,-1,339,0;0,-1,0,0,-1,1198,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,625,-1,-1,-1;-1,2,-94,-109,0,625,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.famousfootwear.com/-1,2,-94,-115,1,32,32,625,625,0,1250,1078,0,1661141882008,17,17770,0,0,2961,0,0,1079,1250,0,B9956E7772CAF2C8CE05D5A2B2F39BD8~0~YAAQiZs+F/6qG7qCAQAA1pfFwwhBxAfVE0wpIlWI4+gjZ8RhCrTwN+PseIQsGo9jmiAzYFx1hnoLMLV58Q0qWOG3rn336SsBDyB17WCUC82lgp3+la+culxai4t1D9CQFUfsjMDZRQ1O6eQNaAtAFDLVLHdbloUuRmxsB8zEc7sxAbXtQe8dfHGJOQsgH+4SYhpIiH1PP+LQp2U2xaeQYw87CyitKufNtRAQIab8z/95tPRDPkYwezMp35FVc2lwM38SO8u6E7AeZTwPL8YoGYdjZuBD3IXtgkO225dWN7X5kKEbwF++cvxBn8/xteqTn6qE2gjC1qf9RqIszJLJRcj2L7x+ppDcNS8Yt23J+Y6V9MDPvpE21nZdA1v44Se+RwcM+7QPk6iF57+W8kQWkqMpXYsWEaVW+PwuHWV/dw==~-1~||1-gJEeRHxLrv-1-10-1000-2||~-1,40554,550,404337583,30261689,PiZtE,92475,64,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.e741fedd4a964,0.031be4be25a55,0.9b73a7fe46f0a,0.155c4524babb6,0.2f571bfdbf997,0.11ed5649d2f28,0.6d6d7fa102d1b,0.1c43c73ddbb4e,0.a4f1091eedde2,0.aecd69a11a79e;1,4,0,0,1,2,2,1,1,0;0,3,2,0,1,9,9,11,8,5;B9956E7772CAF2C8CE05D5A2B2F39BD8,1661141882008,gJEeRHxLrv,B9956E7772CAF2C8CE05D5A2B2F39BD81661141882008gJEeRHxLrv,1,1,0.e741fedd4a964,B9956E7772CAF2C8CE05D5A2B2F39BD81661141882008gJEeRHxLrv10.e741fedd4a964,5,134,238,47,105,62,74,139,86,26,137,42,238,136,58,153,136,235,0,69,75,17,188,32,63,100,142,127,250,157,78,9,496,0,1661141883085;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,457327863-1,2,-94,-118,126456-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;2;39;0",
            // 1
            "7a74G7m23Vrp0o5c9366331.75-1,2,-94,-100,Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/103.0.5060.134 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408720,3225260,1920,1050,1920,1080,1920,538,1920,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,7648,0.550310290275,830571612630,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,1739,-1,0;-1,2,-94,-102,0,-1,0,0,-1,-1,0;1,-1,0,0,-1,-1,0;0,-1,0,0,-1,-1,0;0,-1,0,0,1739,-1,0;-1,-1,0,0,-1,339,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,0,665,-1,-1,-1;-1,2,-94,-109,0,664,-1,-1,-1,-1,-1,-1,-1,-1,-1;-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.famousfootwear.com/account/sign-in?logoff=1-1,2,-94,-115,1,32,32,665,664,0,1329,1308,0,1661143225260,27,17770,0,0,2961,0,0,1312,1329,0,3CD81E639288094AA993BE3BCA9ACEFE~0~YAAQiZs+FzBhHrqCAQAAtRfawwjkw9vNxpyHqUC6ExK7D2nE3bPD4WnpBo2A9TLrE5RlcqkWM5CFYMCerToNkDZidF+rAqomy64XetCDR/21MO5RYphn8PMulZhooQaDka8PX7FwQEtFEs4lweTz3kCk912DTN0yLqzzILK/M5XJHSToXvOjFO7lV36K6HhJEGAcH8bZV6XJi+xImp94rwfbe9VHNTgQdDxivcdYMQDgR02LR56X5Bev938z5xXjrXoP6tZOafu+wRdmzMEdxjF33dtloxIx2BITQ9Z1DI+/uEiYiDtORbll9wcbAWOUbP42OU/BYPPfAfK3B7CNAISvSMRcxsdUTKP3RaNhzujcfnd4tmcWTyE7KniKsiVoUw/bYJiVtAWv2bOAXymv6vx0CS7109Yg97TAjlZYbw==~-1~||1-JMmQMlidRy-1-10-1000-2||~-1,41398,231,-970535065,30261689,PiZtE,88946,66,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.04e947fa49c2,0.4b65450bb50b,0.688e0d83cdeed,0.772f3dd31266,0.91d9c021bde2a,0.419f7828daab6,0.96716d8756b03,0.3ad92f374d75,0.d4222cff87acd,0.15fb64b6a831e;1,0,6,0,0,1,2,1,1,0;0,1,3,0,0,4,3,5,2,0;3CD81E639288094AA993BE3BCA9ACEFE,1661143225260,JMmQMlidRy,3CD81E639288094AA993BE3BCA9ACEFE1661143225260JMmQMlidRy,1,1,0.04e947fa49c2,3CD81E639288094AA993BE3BCA9ACEFE1661143225260JMmQMlidRy10.04e947fa49c2,211,199,128,14,178,249,87,27,39,79,153,249,247,220,137,205,80,145,212,245,215,238,28,189,97,142,4,33,146,217,172,157,550,0,1661143226568;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1764636789;-1301919687;dis;,7;true;true;true;-180;true;24;24;true;false;-1-1,2,-94,-80,5597-1,2,-94,-116,9675759-1,2,-94,-118,133754-1,2,-94,-129,,,0,,,,0-1,2,-94,-121,;13;12;1",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("set key: {$key}");

        $headers = [
            "Accept"       => "*/*",
            "Content-Type" => "text/plain;charset=UTF-8",
        ];
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData[$key]]), $headers);
        $this->http->JsonLog();
        sleep(1);

        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $secondSensorData[$key]]), $headers);
        $this->http->JsonLog();
        sleep(1);

        return $key;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice('Running Selenium...');
            $selenium->UseSelenium();

            $selenium->useFirefox();
            $selenium->usePacFile(false);
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->seleniumOptions->setResolution([$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()]);
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }
            */

            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.famousfootwear.com/account/sign-in');
            $cookiesBtn = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 3);

            if ($cookiesBtn) {
                $cookiesBtn->click();
                sleep(2);
            }
            $this->savePageToLogs($selenium);

            foreach ($selenium->driver->manage()->getCookies() as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } finally {
            $selenium->http->cleanup();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;
        $requestVerificationToken = $this->http->FindSingleNode('//form[@id="_CRSFform"]/input[@name="__RequestVerificationToken"]/@value');

        if (
            $requestVerificationToken
            && $this->http->FindPreg("/data\.set\('user\.profile\.profileInfo\.loginStatus', 'logged in'\);/")
            && $this->http->FindSingleNode('//a[contains(@href,"sign-out")]')
        ) {
            $this->requestVerificationToken = $requestVerificationToken;

            return true;
        }

        return false;
    }
}
