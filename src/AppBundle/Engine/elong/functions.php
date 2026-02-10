<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerElong extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("http://my.elong.com/index_en.html", []);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function parseCaptchaSelenium($browser)
    {
        $this->logger->notice(__METHOD__);
        $url = $browser->waitForElement(WebDriverBy::xpath("//img[@method = 'ChangeVidateCode']"), 0);

        if (!$url) {
            return false;
        }
        $recognizer = $browser->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $pathToScreenshot = $browser->takeScreenshotOfElement($url);
        $captcha = $browser->recognizeCaptcha($recognizer, $pathToScreenshot);
        unlink($pathToScreenshot);

        return $captcha;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
//        $this->http->GetURL("https://secure.elong.com/passport/login_en.html");
        $this->http->GetURL("http://my.elong.com/point_en.html");

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        $this->selenium();

        if ($this->http->FindPreg('/当前风险等级较高，请使用短信验证码登录/u')) {
            if ($this->parseQuestion()) {
                return false;
            }
        }

        return true;

        $this->http->GetURL("http://my.elong.com/point_en.html");
        $this->http->Form = [];
        $this->http->FormURL = 'https://secure.elong.com/passport/ajax/elongLogin';
        $this->http->SetInputValue("userName", $this->AccountFields['Login']);
        $this->http->SetInputValue("passwd", $this->AccountFields['Pass']);
        $this->http->SetInputValue("token", '1bf8s-4a16-4fcf-b23d-69aedc4a3338');
        $this->http->SetInputValue("rememberMe", "true");
        $this->http->SetInputValue("loginLevel", "2");
//        // captcha
//        if ($this->http->FindSingleNode("//div[@id = 'ValidateCodeDiv' and @style = 'display: block;']")) {
//            $captcha = $this->parseCaptcha();
//            if ($captcha === false)
//                return $this->checkErrors();
//            $this->http->SetInputValue("validateCode", $captcha);
//        }
//        else
        $this->http->SetInputValue("validateCode", "Security code");

        return true;
    }

    public function checkErrors()
    {
        return false;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;
        $this->http->PostForm();
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        // Question
        if (isset($response->success) && !$response->success && isset($response->message) && $this->http->FindPreg('/当前风险等级较高，请使用短信验证码登录/u', false, $response->message)
            ) {
            $this->http->GetURL('https://secure.elong.com/passport/login_en.html');

            if ($this->parseQuestion()) {
                return false;
            }
        }

        // Invalid email or password
        if ($message = $this->http->FindPreg('/\"MemberCardList\":null/ims')) {
            throw new CheckException('Invalid email or password', ACCOUNT_INVALID_PASSWORD);
        }
        // Password length is between 6 and 32
        if ($message = $this->http->FindPreg('/密码长度不在6到32之间@Password length is between 6 and 32/ui')) {
            throw new CheckException('Password length is between 6 and 32', ACCOUNT_INVALID_PASSWORD);
        }
        // User not registered
        if ($message = $this->http->FindPreg('/"message":"用户还未注册@User not registered"/ui')) {
            throw new CheckException('User not registered', ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[@class="input_tip" and @name="input_error_tip" and contains(text(), "用户还未注册")]')) {
            throw new CheckException('User not registered', ACCOUNT_INVALID_PASSWORD);
        }
        // Login user name and password do not match
        if ($message = $this->http->FindPreg('/"message":"登录用户名和密码不匹配@Login user name and password do not match"/ims')) {
            throw new CheckException('Login user name and password do not match', ACCOUNT_INVALID_PASSWORD);
        }
        // Account password login has been closed, please select other login
        if ($message = $this->http->FindPreg('/"message":"(账号密码登录已经关闭,请选取其它登录方式)"/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access is allowed
        //if (isset($response->esid))
        //    $this->http->GetURL("http://my.elong.net/Connection_en.html?SessionTag={$response->esid}&nextUrl=http%3A%2F%2Fmy.elong.net%2Findex_en.html&expireTime=1");

        $this->http->GetURL('http://my.elong.com/index_en.html');

        if (strstr($this->http->currentUrl(), 'https://secure.elong.com/passport/login_cn.html?')) {
            $this->selenium();
            $this->http->GetURL('http://my.elong.com/index_en.html');
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Sorry, the page you are looking for does not exist or is temporarily unavailable.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Sorry, the page you are looking for does not exist or is temporarily unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//span[contains(text(), '抱歉，您访问的页面不存在或暂时无法访问。')]") && $this->http->Response['code'] == 500) {
            throw new CheckException("There is an unknown error.", ACCOUNT_PROVIDER_ERROR);
        }
        // System upgrade maintenance, please use the phone dynamic password login!
        if ($message = $this->http->FindPreg("/\"message\":\"(系统升级维护中，请用手机动态密码登录！)\",\"success\":false/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // no errors and no authorization
        if (in_array($this->AccountFields['Login'], [
            'vitalzj@gmail.com',
            'jasonuysim@yahoo.com',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->Question = 'Please enter your phone number with a country code, e.g. +86 18528625051';
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__ . ' step ' . $step);

        if ($step == 'Question') {
            $captcha = 'Security code';
            $this->http->GetURL('https://secure.elong.com/passport/login_en.html');

            if ($this->http->FindSingleNode("//div[@id = 'DynamicLogin']")) {
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return $this->checkErrors();
                }
                $this->http->SetInputValue("validateCode", $captcha);
            }

            // delete country +86
            $phone = preg_replace('/^\s*\+86/', '', str_replace(['+', ' '], '', $this->Answers[$this->Question]));

            $this->http->PostURL('https://secure.elong.com/passport/ajax/getDynamicCode', [
                'phone'        => $phone,
                'validateCode' => $captcha,
            ]);
            $response = $this->http->JsonLog();

            // Security code is incorrect
            if (isset($response->success) && !$response->success && $response->code == '704') {
                $this->AskQuestion($this->Question, 'Security code is incorrect');

                return false;
            }

            if ($response->success) {
                $this->State['phone'] = $phone;
                $this->State['captcha'] = $captcha;
                unset($this->Answers[$this->Question]);
                $this->Question = 'Please enter a dynamic password which was sent to your phone number';
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question2";

                return false;
            }

            if (isset($this->Answers[$this->Question])) {
                unset($this->Answers[$this->Question]);
            }
        } elseif ($step == 'Question2') {
            $this->http->PostURL('https://secure.elong.com/passport/ajax/dynamicLogin', [
                'dynamicCode'     => $this->Answers[$this->Question],
                'extendUserPhone' => $this->State['phone'],
                'validateCode'    => $this->State['captcha'],
                'rememberMe'      => 'true',
                'token'           => 'jcbsx754-39b7-4533-98b3-205a14c769e1',
            ]);
            unset($this->Answers[$this->Question]);
            $response = $this->http->JsonLog();

            // The current verification code input errors
            if (isset($response->success) && $response->success) {
                return true;
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//input[@id='hidden_user_name']/@value")));
        // Balance
        $this->SetBalance($this->http->FindSingleNode("//div[@class='my_linkbox']/a[contains(@href, '/point_')]"));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode("//input[@id='hidden_memberid_user']/@value", null, false, "/^\d+$/"));
        //CashAccount - ￥0
        $this->SetProperty("CashAccount", $this->http->FindSingleNode("//div[@class='my_linkbox']/a[contains(@href, '/cashcoupon_')][1]"));
        // @class=gift_icon - not working
        if ($this->http->FindSingleNode("//div[@class='my_linkbox']/a[contains(@href, '/cashcoupon_')][2]/span[@class='linkbox_num']") > 0) {
            $this->sendNotification('elong: Gift Сard > 0');
        }

        // Level
        if (!isset($this->Properties['MemberLevel'])) {
            $this->http->GetURL("http://my.elong.com/me_getPersonalInfo");
            $response = $this->http->JsonLog(null, 3, true);
            $level = $this->http->FindPreg("/memberLevel':(\d+)/", false, ArrayVal($response, 'data'));
            $this->logger->debug(">>> Status " . $level);

            switch ($level) {
                case '1':
                    $this->SetProperty("MemberLevel", "Member");

                    break;

                case '2':
                    $this->SetProperty("MemberLevel", "VIP");

                    break;

                case '3':
                    $this->SetProperty("MemberLevel", "Dragon");

                    break;

                case '4':
                    $this->SetProperty("MemberLevel", "Platinum");

                    break;

                default:
                    if (!empty($level)) {
                        $this->ArchiveLogs = true;
                        $this->sendNotification("elong. New status was found: $level");
                    }
            }// switch ($status)
        }

        // Search earliest expiration date
        if ($this->Balance) {
            $this->logger->info('Expiration Date', ['Header' => 3]);

            if ($this->http->GetURL("http://my.elong.com/point_en.html?rnd=" . time() . date("B"))) {
                $expNodes = $this->http->XPath->query("//table[contains(@class, 'input_detail')]//tr[td]");
                $this->logger->debug("Total {$expNodes->length} exp nodes were found");

                foreach ($expNodes as $expNode) {
                    $points = $this->http->FindSingleNode('td[1]', $expNode);
                    $exp = $this->http->FindSingleNode('td[5]', $expNode);
                    $this->logger->debug("Date: {$exp} / Points: {$points}");
                    $exp = strtotime($exp, false);

                    if ($points > 0 && $exp && (!isset($expirationDate) || $expirationDate > $exp) && $exp > time()) {
                        $expirationDate = $exp;
                    }// if ($points > 0 && $exp && (!isset($expirationDate) || $expirationDate > $exp) && $exp > time())
                }// foreach ($expNodes as $expNode)
            }// if ($this->http->GetURL("http://my.elong.net/point_en.html?rnd=".time().date("B")))
        }// if ($this->Balance)

        if (isset($expirationDate) && $expirationDate !== false) {
            $this->SetExpirationDate($expirationDate);
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $http2 = clone $this->http;
        $file = $http2->DownloadFile("https://secure.elong.com/passport/getValidateCode?" . $this->random(), "jpg");
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($recognizer, $file, [], 0);
        unlink($file);

        return $captcha;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->useCache();

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
//            https://secure.elong.com/passport/login_en.html?rnd=20210716184714
            $selenium->http->GetURL("https://secure.elong.com/passport/login_cn.html?nexturl=http%3A%2F%2Fmy.elong.com%2Findex_en.html");
            $login = $selenium->waitForElement(WebDriverBy::id("UserName"), 7);
            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@method = 'ValidatePassword']"), 0);
            $validateCode = $selenium->waitForElement(WebDriverBy::id("ValidateCode"), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@class, 'loginbtn')]"), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$login || !$pass || !$btn) {
                $this->logger->error("something went wrong");

                return $this->checkErrors();
            }

            // captcha
            if ($validateCode) {
                $captcha = $this->parseCaptchaSelenium($selenium);
                $validateCode->sendKeys($captcha);
            }

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $btn->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'You have')] | //a[@id = 'n_user_name'] | //div[@id = 'member_div']"), 30);
            $this->savePageToLogs($selenium);

            if ($res) {
                $result = true;
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
            // save page to logs
            $this->savePageToLogs($selenium);
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();
        }

        return $result;
    }
}
