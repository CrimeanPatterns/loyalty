<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerFinishline extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = "https://www.finishline.com/store/myaccount/profile.jsp?responsive=true";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader("User-Agent", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7");
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('We could not match your username and password, please try again.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://www.finishline.com/store/myaccount/login.jsp");

        $this->selenium();

        return true;

        $this->http->GetURL($loginLink);
//        $this->http->GetURL("https://account.finishline.com/account/oauth/authorize?response_type=code&client_id=finl-web&scope=read&state=profile&code_challenge_method=s256&code_challenge=MjllZTU2ZDIzN2IxNGFlNzY3ZWY4NWU3NDcwZTAwYTcyYzNmYWJjNjEwZTIwYWZkYmZkOTVmOWI2ODczNTI1Zg==");
        $this->http->RetryCount = 2;
//        if (!$this->http->ParseForm("loginForm")) {
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://account.finishline.com/account/login';
        $this->http->SetInputValue("username", $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseReCaptcha("6LcPD74ZAAAAAFtuJvnpIV5VjKE16Ma7oRCcENIN", $this->http->currentUrl());

        if ($captcha == false) {
            return false;
        }

        $this->http->SetInputValue('recaptchaToken', $captcha);

//                $this->http->SetInputValue('recaptchaToken', '03AGdBq277mQrJjh452dhdjo-PRTsQXjIFQNRI_K3i0Zn2hoGLdODsWS2bp6YLzGZA6DDWPakDxTDnOzxrK_UwPM90P6pQTzpPzSNTLj5xrh7VU3KgvE-016dOByfnGcytj_HXREhmHXvWuCf58R5WX6yNbZN0X-F7CaIvFzlYFPiszO-0vGsj9Tx6uJMN_xRqKR1PJCdXCtAGr3N3uCdcvT_7Gv6sEFrWR4l3LAvLQOu7SFVhoiHLa9_MfJhyQt76NftIVUx1GLzjpMH2cztpyN8oxtXPIQvKZi_SML93AwflaitucZpSo57RuJUMSxvRRFhtxSRexT3OV5LYOQv-HTEKDWXvRP0TBCvP-zlYrJYqB1nWjg3JuSpkc8hnq7Sd-r_cJeqFa8hBS0jvYbbH104gjFTHAnVDZwF9nob6pQC5yuHBCvPMFTrmsYcbXrzQqVfGot8Fdqe-wq8Ona9dXvaSRDzUjfuK36fkkVoCTH6GZv6envcrl9UnecXQNR66v8r4poozS9k7pCjbrf-7b7fYT9fAnS4wVrTNJqOXUt_n0a9VK7QHuG16ykgSJObWHphmWUXuTN368rGvmS8BfeuIXmvmTdUNNKnVC6kXj4q4NlX6oy-kMwV-tkhTWheIDTFhJ7aQJFzh245iLTcf5NhR_Lpo9VsDmqyRQ4LHNhRgJ99G8P4_74gIvtp_NrZdmam2ArQi-0MJmoiURWdKgqea6SfxmaoBgSVq7c345qREKLEPRU1exbx7WAkQ8e0du_NPnAF9KHXLzfL3a_NmVhuCvsbOllvF54nA_3UQ9epIutinM4khQjXnM1S6HOu4iMR-VYHNcSksWNyxeqbJkPfPkdURTUqm4D8mmailPOGmy4ue6D1fzvOatb9G5peGhGokvClcw3stybdSEfPqv5MnShrstxKr4qLpuZFhBOU6cYKJuv1Tp5uEAXsV4GYR21v5SC9G_BnI3aM--3EkA1FgUcVxGcr8EparvbFQlkba5S8s6W6DjKVzzSglSnTvzwlbO9692ISGAzBlOX82wE-MwSNEzF2Bh4S7Rd7mXk1f6u2OMTPJuuJdQTdgVtyrepxsnztoNYn6kBOSmgDCtZiQpEzO-smmt8IyGI5i_HPC5i83pDOkhJuc3hhU9u6-v7Uo9idX0nHva_1kvHP9kaumtfgtdhACEnvIawj3gd7JGyXaWD9ZoFMJoR9lramTKso0PsT6yuMvi-FpcQJLIkrEm0fBkLrAZdXnqJ2FhSSTJ7yz0pdQminx4txp7oyP_F2y6X-xcVyANv_l00Z5h9ddPM3CWUkuAXPeXDx1aRb0bJ1G33cT7deFJoKEfSv34e4-HohMBOudUXAvgMmrqrcmESFcqO66IosBopNslQnEi6Vc4PyxpDFN5jLNM7YfrQVW45Gt3Z3OQ5n_vkY5g3LOM5y8fQMZarQmO95xfF5sivTC1G97jMOnsMb9JFx0n9CJuSeiPqrjdaK8Jr1zUVWu061cuOmj2Q');//todo: hard code

        $this->sendSensorData();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We just dropped some new shoes, so our site is a little crowded right now.
        // But don't worry about pounding that refresh button—we'll let you in as soon as something opens up. Trust us, it's worth the wait.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We just dropped some new shoes, so our site is a little crowded right now')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is undergoing maintenance
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Our site is undergoing maintenance')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# The request couldn't be processed correctly. Please try again soon.
        if ($message = $this->http->FindPreg("/(The request couldn\'t be processed correctly\.\s*Please try again soon\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Not Found
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Not Found')]")) {
            throw new CheckException("Page not found", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/
        //# FinishLine.com is currently down for maintenance
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'FinishLine.com is currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is not down, we're just using a little crowd control.
        if ($message = $this->http->FindPreg('/Our site is up and running, we\&\#39;re just using a little crowd control\./')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# We just droppped some new shoes, so our site is a little crowded right now
        if ($message = $this->http->FindPreg("/We just droppped some new shoes, so our site is a little crowded right now/ims")) {
            throw new CheckException('We are currently down for maintenance', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $headers = [
            'Accept-Encoding' => 'gzip, deflate, br',
            "Referer"         => "https://account.finishline.com/login",
        ];

        /*
        if (!$this->http->PostForm($headers)) {
            // TODO: bug accounts: 3671989, 2841571
            if ($this->http->Response['code'] == 500) {
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            } else {
                return $this->checkErrors();
            }
        }
        */
        $this->http->RetryCount = 2;
        /*
        $response = $this->http->JsonLog();

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, this does not match our records')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        */

        if (strstr($this->http->currentUrl(), 'state=profile')) {
            $this->captchaReporting($this->recognizer);

            $this->http->GetURL("https://account.finishline.com/account/oauth/authorize?response_type=code&client_id=finl-web&scope=read&state=profile&code_challenge_method=s256&code_challenge=MjllZTU2ZDIzN2IxNGFlNzY3ZWY4NWU3NDcwZTAwYTcyYzNmYWJjNjEwZTIwYWZkYmZkOTVmOWI2ODczNTI1Zg==");

            parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
            $code = $output['code'] ?? null;

            if (!$code) {
                $this->logger->error("something went wrong");

                return false;
            }

            $headers = [
                "Accept"        => "*/*",
                "Authorization" => "Basic ZmlubC13ZWI6",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://account.finishline.com/account/oauth/token?grant_type=authorization_code&scope=read&code={$code}&code_verifier=3f43bc06-bae0-4b95-a8ce-ca48edd95e00", [], $headers);
            $this->http->RetryCount = 2;

            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                $this->logger->error("something went wrong");

                return false;
            }

            $this->http->GetURL("https://www.finishline.com/store/myaccount/jwtCloudLogin.jsp?jwtCloudLogin={$response->access_token}&state=profile");

            return true;
        }

        /*
        // Access is allowed
        if ($this->loginSuccessful()/* || isset($response->success) && $response->success == true* /) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        */

        parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
        $message = $output['displayError'] ?? $this->http->FindSingleNode('//div[contains(@class, "text-danger")]') ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'Error signing in. Please contact customer support.')
            ) {
                $this->captchaReporting($this->recognizer, false);
                $this->DebugInfo = $message;

                throw new CheckRetryNeededException(2, 15, $message);

                return false;
            }

            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'We could not match your username and password, please try again.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "CHECK YOUR EMAIL TO RESET YOUR PASSWORD")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        /*
        $message = $response->invalid ?? $this->http->FindSingleNode("//span[
            contains(text(), 'We are unable to match your email and password')
            or contains(text(), 'We could not match your username and password, please try again.')
            or contains(text(), 'The supplied user name') and contains(text(), 'is incorrect')
        ]");
        //
        if (
            strstr($message, 'We are unable to match your email and password')
            || strstr($message, 'We could not match your username and password, please try again.')
            || (strstr($message, 'The supplied user name') && strstr($message, 'is incorrect'))
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // bug account
        if (
            $this->http->Response['code'] == 403
            && $this->AccountFields['Login'] == "philgustafson@gmail.com" // AccountID: 3648174
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (
            !strstr($this->http->currentUrl(), 'https://www.finishline.com/store/myaccount/profile.jsp')
            || $this->http->Response['code'] == 403
        ) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            if (strstr($this->http->currentUrl(), "https://www.finishline.com/store/myaccount/login.jsp?redirectUrl=profile&_requestid=")
                && in_array($this->AccountFields['Login'], [
                    'rvjdonjuan@comcast.net',
                    'siobanking@msn.com',
                    'davedemmy@gmail.com',
                    'moneymanmike2@aol.com',
                    'rgosine@gmail.com',
                    // 'luan_van@hotmail.com',
                    'steve1183@me.com',
                    'rhonda_66441@yahoo.com',
                    'rbennett23@gmail.com',
                    'notterstein@gmail.com',
                    'adam.murad@gmail.com',
                    'mmgabriele@verizon.net',
                    'npasic75@gmail.com',
                ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Hello, ')]", null, false, '/Hello, (.+)/')));

        if ($name = $this->http->FindSingleNode("//div[contains(text(), 'Primary Address')]/preceding-sibling::div/div/strong[contains(text(),'{$this->Properties['Name']}')]")) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Winner's Circle Number / Status #
        $loyaltyNumber = $this->http->FindSingleNode('//input[@id = "loyaltyNumber"]/@value');
        $this->SetProperty("CircleNumber", $this->http->FindSingleNode('//div[contains(text(), "Winner\'s Circle #")]', null, true, "/\#([\w\-]+)/") ?? $loyaltyNumber);
        // Balance - Winners Circle Points
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Points to spend')]/preceding-sibling::div[1]"));
        // User is not member of this loyalty program
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode("//button[@id = 'myAccountJoinNow']")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 4791036 - New program 'Status'
        if ($this->parseJson()) {
            return;
        }

        $this->logger->info("Rewards", ['Header' => 3]);
        $this->http->GetURL("https://www.finishline.com/store/myaccount/rewards.jsp?responsive=true");
        // STATUS #
        $this->SetProperty("CircleNumber", $this->http->FindSingleNode('//span[contains(text(), "STATUS #")]', null, true, "/\#([\w\-]+)/") ?? $loyaltyNumber);

        if ($this->parseJson()) {
            return;
        }

        // You're ... points away from a ... Reward.
        $balance = $this->http->FindSingleNode("//div[contains(text(), 'Points to spend')]/preceding-sibling::div[1]");
        $total = $this->http->FindSingleNode("//div[@id = 'wcPointsMeter']/@data-total");

        if (isset($balance, $total)) {
            $this->SetProperty("UntilToNextReward", $total - $balance);
        }
        // Available Reward(s)
        $rewards = $this->http->XPath->query("//div[contains(@class,'rewards-row')]//div[contains(@class,'reward-box') and .//text()[contains(.,'Use code:')]]");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $code = $this->http->FindSingleNode(".//text()[contains(.,'Use code:')]", $reward, false, '/:\s*(\d+)/');
            $exp = $this->http->FindSingleNode(".//text()[contains(.,'Expires:')]", $reward, false, '#:\s*(\d+-\d+-\d+)#');
            $balance = $this->http->FindSingleNode(".//span[@class='reward-amount']", $reward);
            $subAccount = [
                'Code'        => 'finishlineReward' . $code,
                'DisplayName' => "Reward #" . $code,
                'Balance'     => $balance,
            ];

            if ($exp = strtotime($exp, false)) {
                $subAccount['ExpirationDate'] = $exp;
            }
            $this->AddSubAccount($subAccount, true);
        }// foreach ($rewards as $reward)
    }

    private function parseJson()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->Properties['CircleNumber'])) {
            return false;
        }

        $loyaltyNumber = $this->Properties['CircleNumber'];

        $this->http->GetURL("https://www.finishline.com/store/myaccount/json/loyaltyAccountSummaryJsonResponse.jsp?loyaltyNumber={$loyaltyNumber}");

        $response = $this->getJson();
        $loyaltyAccount = $response->data->myAccount->customerProfile->loyaltyAccount;

        if (isset($loyaltyAccount->loyaltyPointsBalance)) {
            // Balance - Status Points
            $this->SetBalance(intval($loyaltyAccount->loyaltyPointsBalance));
        }

        if (isset($loyaltyAccount->loyaltyProgram->currentTierName)) {
            // Status
            $this->SetProperty('Status', $loyaltyAccount->loyaltyProgram->currentTierName);
        }

        if (isset($loyaltyAccount->loyaltyProgram->pointsUntilTierMaintenance)) {
            // Points Until Tier Maintenance
            $this->SetProperty('PointsUntilStatusMaintenance', intval($loyaltyAccount->loyaltyProgram->pointsUntilTierMaintenance));
        }

        if (isset($loyaltyAccount->loyaltyProgram->tierExpirationDate)) {
            // Status Expiration Date
            $this->SetProperty('StatusExpirationDate', strtotime($loyaltyAccount->loyaltyProgram->tierExpirationDate));
        }

        $this->logger->info("Rewards", ['Header' => 3]);
        $this->http->GetURL("https://www.finishline.com/store/myaccount/json/LoyaltyAllActiveRewardsJsonResponse.jsp");
        $response = $this->getJson();
        // Available Reward(s)
        $rewards = $response->content ?? [];
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $code = $reward->couponNumber;
            $exp = $reward->expirationDate;
            $balance = null;

            if (!isset($reward->amount) && $code == 'FREESHIPSTAT') {
                $balance = null;
            } elseif (isset($reward->amount)) {
                $balance = $reward->amount;
            }

            $subAccount = [
                'Code'        => 'finishlineReward' . $code,
                'DisplayName' => "Reward #" . $code,
                'Balance'     => $balance,
            ];

            if ($exp = strtotime($exp, false)) {
                $subAccount['ExpirationDate'] = $exp;
            }
            $this->AddSubAccount($subAccount, true);
        }// foreach ($rewards as $reward)

        return true;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && (strstr($properties['SubAccountCode'], "finishlineReward"))) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);
        $sensorPostUrl =
            $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#")
            ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
        ;

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9202531.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.55 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403068,4501389,1536,871,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8934,0.897634748448,819087250694.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://account.finishline.com/login-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1638174501389,-999999,17524,0,0,2920,0,0,3,0,0,77D7701BC605770ADA9F6A4B9C5B75BA~-1~YAAQp50ZuCoXgE19AQAA7SnPagYaFKROl/0nDZW9Ilg5sfqEqGS69l7LbkeUvDbN63cyffL1hEy2gTasNPPZDoKBxqliZX0rFoX4l8lJxzfbgB3vcFL4EretHWlX/L7Bm+YbVaedqgiGe5g+BSbzLpxwJ5a4t3j9xHw+f+toshD5OsISiN7ywXl2oX7lByoSbT99vAJJ6gVx8Jbe8xSTCYIa+c9kuH6LLJUk9K3UPD0ETkSjlRVqWojGgMQJ+eIUU4y5Cm1d6zNmovx42911C/TkqzCqE3ZwFqLTftXut1samrVMF9lqytP3OK64BVxGV5UXmZMMv6bePgBTQrxBmmdGC75zN+vcaivbad7zPmIg3GiD/oSeiS0KDRPzhopRx1hdnB+EqSE6KKpmAMu7NUt8ArUdasBtNFCBP/7QPwnAtw8=~-1~-1~-1,39875,-1,-1,30261693,PiZtE,15170,74,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,121537767-1,2,-94,-118,87387-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9202531.7-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/96.0.4664.55 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,403068,4501389,1536,871,1536,960,1536,461,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8934,0.625385993312,819087250694.5,0,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,0,-1,0,0,-1,864,0;1,-1,0,0,-1,883,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://account.finishline.com/login-1,2,-94,-115,1,32,32,0,0,0,0,693,0,1638174501389,92,17524,0,0,2920,0,0,694,0,0,77D7701BC605770ADA9F6A4B9C5B75BA~-1~YAAQp50ZuGsXgE19AQAAjCvPagZ+A7S5r3NhN1+leEwgcHZorBBYZ8I/6IlD4JXa/FLklGgp/Tonzje/UNpCd9zdOsx4X1OAl43r5VF6c6D6djfIo5mD2vJE4r15BKs+bmVnKccnqMOQKp3n8dW2FQtYdAzLkZF1ZcdXI5UFOdDmP4wQni82FgqEAjp8FlsFEZrkVjY/YegIhBOz5rndObUYGg0UV0JjYQMzA4KLXMGOkIY654sgwVeMc3ijurSrXReS3tI2mccVQq2G2euDwSpoi0nZLXCS0jGmyzFLximONkFoTgSdhLxYBFT1f5RRfhENGejsmofkWed0ZaFVPzVA/AE/Ju9ShW2/wreIXadbGyMEZ6qTQWCTq8Ke/gdcvx7OxY2b1DGhvj+8qFhtO9KUw7//maNI+yxuaoVezCRjl5eZ2gJpc4PUQC5/EcXQ~-1~-1~-1,40944,555,-1180502355,30261693,PiZtE,10102,80,0,-1-1,2,-94,-106,9,1-1,2,-94,-119,20,20,40,20,40,20,0,20,0,0,0,0,20,160,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,121537767-1,2,-94,-118,93279-1,2,-94,-129,87838b6b28b2e952ff9e4e3f1f00f6c6146fa8090ee5d0541c06ecdc180622da,2,0,Intel Inc.,Intel(R) UHD Graphics 630,f437e95c71eb8e0326eb22e9cba9c05b86068086180a4ca09b3bcd32320a1928,31-1,2,-94,-121,;29;6;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "text/plain;charset=UTF-8",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    protected function parseReCaptcha($key, $currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");
        /*
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => isset($currentUrl) ? $currentUrl : $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
            "action"    => "login",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        */

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $currentUrl ?? $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindNodes("//a[normalize-space(text()) = 'Sign Out']")) {
            return true;
        }

        return false;
    }

    /*
    private function getJson()
    {
        $body = htmlspecialchars_decode($this->http->Response['body']);
        $body = preg_replace('/^\\{"result":"/', '', $body);
        $body = preg_replace('/\"\}$/', '', $body);
        $this->logger->debug(var_export($body, true), ['pre' => true]);
        $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]') ?? $body);

        return $response;
    }
    */

    private function getJson()
    {
        $jsonEscaped = $this->http->FindSingleNode('//pre[not(@id)]/text()');
        $jsonPrepared = htmlspecialchars_decode($jsonEscaped);
        $jsonPrepared = preg_replace('/^\\{"result":"/', '', $jsonPrepared);
        $jsonPrepared = preg_replace('/\"\}$/', '', $jsonPrepared);

        return $this->http->JsonLog($jsonPrepared);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
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
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            /*
            if ($this->attempt == 0) {
                $selenium->useFirefox();
                $selenium->setKeepProfile(true);
            } else
            */
//            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
//            $selenium->disableImages();

            $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_99);

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

//            $selenium->useCache();
            $selenium->usePacFile(false);

            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            $selenium->http->GetURL('https://www.finishline.com/');

            $selenium->waitForElement(WebDriverBy::xpath('
                //a[contains(@class, "cl-sign-in-link")]
                | //button[contains(text(), "No Thanks")]
            '), 5);

            $notThanks = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "No Thanks")]'), 0);
            $this->savePageToLogs($selenium);

            if ($notThanks) {
                $notThanks->click();
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@class, "cl-sign-in-link")]'), 5);

            if (!$login) {
                $selenium->http->GetURL('https://www.finishline.com/store/myaccount/login.jsp');
            } else {
                $selenium->driver->executeScript('
                    let l = document.querySelector(".cl-sign-in-link");
                    l.dispatchEvent(new Event("mouseover", {bubbles: true}));
                    l.dispatchEvent(new Event("mouseenter", {bubbles: true}));
                    l.dispatchEvent(new Event("mousemove", {bubbles: true}));
                    l.click();
                ');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 6);
            $pass = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 0);
            $this->savePageToLogs($selenium);

            if ($login && $pass && $signInButton) {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $selenium->logger;
                $mover->duration = rand(300, 1000);
                $mover->steps = rand(10, 20);

                $mover->moveToElement($login);
                $mover->click();
//                $login->sendKeys($this->AccountFields['Login']);
                $mover->sendKeys($login, $this->AccountFields['Login'], 6);
                $mover->moveToElement($pass);
                $mover->click();
                $mover->sendKeys($pass, $this->AccountFields['Pass'], 6);
//                $pass->sendKeys($this->AccountFields['Pass']);

                $signInButton->click();

                $selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(),'Access Denied')] | //a[@href = '/myaccount/profile.jsp'] | //div[contains(@class, 'text-danger')]"), 10);

                /*if ($selenium->waitForElement(WebDriverBy::xpath("//h1[contains(text(),'Access Denied')]"), 0)) {
                    $this->sendNotification('Access Denied // MI');
                    $selenium->driver->executeScript('history.back();');
                    $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'username']"), 3);
                    $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);
                    $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//input[@value = "Sign In"]'), 0);
                    $this->savePageToLogs($selenium);

                    if ($login && $pass && $signInButton) {
                        $login->sendKeys($this->AccountFields['Login']);
                        $pass->sendKeys($this->AccountFields['Pass']);

                        $signInButton->click();


                        $selenium->waitForElement(WebDriverBy::xpath('
                            //a[@href = "/store/myaccount/profile.jsp"]
                            | //div[contains(@class, "text-danger")]
                        '), 10);
                        $this->savePageToLogs($selenium);
                    }
                }*/
                $this->savePageToLogs($selenium);

                if ($this->loginSuccessful()) {
                    $this->captchaReporting($this->recognizer);
                    $selenium->Parse();

                    $this->SetBalance($selenium->Balance);
                    $this->Properties = $selenium->Properties;
                    $this->ErrorCode = $selenium->ErrorCode;

                    if ($this->ErrorCode != ACCOUNT_CHECKED) {
                        $this->ErrorMessage = $selenium->ErrorMessage;
                        $this->DebugInfo = $selenium->DebugInfo;
                    }

                    return false;
                }
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return true;
    }
}
