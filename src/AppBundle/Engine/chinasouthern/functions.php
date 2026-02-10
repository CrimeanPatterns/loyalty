<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerChinasouthern extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private CaptchaRecognizer $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        // $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // alternative login form
        $this->http->setCookie("language", "en_CN", ".csair.com");
        $this->http->setCookie("globalroute", "gb_CN_cn", ".csair.com");
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://b2c.csair.com/B2C40/modules/bookingnew/manage/login.html?lang=en');

        $i = 0;

        while ($this->http->Response['code'] === 404 && $i < 3) {
            sleep(3);
            $this->http->GetURL('https://b2c.csair.com/B2C40/modules/bookingnew/manage/login.html?lang=en');
            $i++;
        }

        $this->http->RetryCount = 2;

        // proxy issues
        if (
            strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')
            || strstr($this->http->Error, 'Network error 28 - Connection timed out after')
            || $this->http->Response['code'] === 404
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }

        if (strpos($this->http->Response['body'], '/main/modules/loginnew/login.js') !== false) {
            $this->http->FormURL = 'https://b2c.csair.com/portal/main/user/login';
            $this->http->SetInputValue('userId', $this->AccountFields['Login']);
            $this->http->SetInputValue('passWord', $this->AccountFields['Pass']);
            $this->http->SetInputValue('loginType', '1');
            $this->http->SetInputValue('memberType', '1');
        }
        // Old login forms
        elseif ($this->http->ParseForm("loginForm")) {
            $this->logger->notice("Old login form");
            $this->http->SetInputValue("logtype", "1");
            $this->http->SetInputValue("memberType", "Member");
            $this->http->SetInputValue("userID", $this->AccountFields['Login']);
            $this->http->SetInputValue("userid", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        } elseif ($this->http->FindPreg("/main\/modules\/login\/unifiedLoginNew\.js/")) {
            // New login form
            $this->logger->notice("New login form");
            $this->http->FormURL = 'http://b2c.csair.com/B2C40/user/login.ao';
            $this->http->SetInputValue("logtype", "1");
            $this->http->SetInputValue("memberType", "1");
            $this->http->SetInputValue("userId", $this->AccountFields['Login']);
            $this->http->SetInputValue("passWord", $this->AccountFields['Pass']);
            $this->http->SetInputValue("vcode", "");
        } else {
            return $this->checkErrors();
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, the page you are looking for is currently unavailable.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, the page you are looking for is currently unavailable.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//div[@id = "h5_nocaptcha"]')) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);

        if (!$this->http->PostForm(['Accept' => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8', ])) {
            // retries
            if ($this->http->Response['code'] == 0) {
                throw new CheckRetryNeededException(3, 10);
            }

            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if ($this->http->BodyContains('{"success":false,"exceptionMessage":{"message":"登录异常"},"showVcode":false}', false)
            || ($this->http->BodyContains('<div id="nocaptcha" class="nc-container"></div>', false) && $response === null)
        ) {
            if ($this->selenium()) {
                if (isset($this->recognizer)) $this->captchaReporting($this->recognizer);
                return true;
            }
            $message = $this->http->FindSingleNode('//div[@class = "lg-msg error"][1]')
                ?? $this->http->FindSingleNode('//div[@class ="lg-unit error"][1]/div[@class = "help-txt"]');

            if (isset($message)) {
                $this->logger->error("[Error]: $message");

                if (str_contains($message, 'Sorry, the username or password you have entered is incorrect!')
                    || str_contains($message, 'Over five membership cards linked to the phone/ID number/e-mail. Please call')
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (str_contains($message, 'Login failed, please try again later')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                if (strstr($message, 'Login attempts has exceeded the limit')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                $this->DebugInfo = $message;

                return false;
            }
        }

        if (isset($response->data->typeName) && $response->data->typeName == 'MemberInformation') {
            return true;
        }

        if (isset($response->exceptionMessage->message)) {
            $message = $response->exceptionMessage->message;
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Over five membership cards linked to the phone/email/ID number. Please call 95539 to merge cards.'
                || strstr($message, "您的手机/邮箱/证件号对应卡号已超过5个")
                || $message == '登录失败,请稍后再试'
                || $message == 'Login failed, please try again later'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Account or password is incorrect'
                || $message == '登录错误次数过多，请稍后再试!'
                || $message == '账号或密码错误'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == '登陆失败次数超限') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if ($message == "Error number entered too many, please try again later. For any questions, please contact China Southern Airlines' customer service on 95539.") {
                throw new CheckException("登录失败", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == "登录异常") {
                $message = 'Sorry, the page you are looking for is currently unavailable (Request possibly blocked)';
                throw new CheckRetryNeededException(3, 5, "Sorry, the page you are looking for is currently unavailable.");
            }

            $this->DebugInfo = $message;

            return false;
        }

        if (isset($response->data->typeName) && $response->data->typeName == 'NoMemberInformation') {
            throw new CheckException('抱歉，您输入的用户名或者密码有误!', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/"code":"600024","message":"Did not find relevant accounts",/')) {
            throw new CheckException('抱歉，您输入的用户名或者密码有误!', ACCOUNT_INVALID_PASSWORD);
        }

        // Login exception
        if ($this->http->FindPreg('/"code":null,"message":"登录异常"/')) {
            throw new CheckRetryNeededException();
        }

        if ($this->http->FindPreg('/{"success":false,"exceptionMessage":{"message":"登录异常"},"showVcode":false}/')) {
            throw new CheckException("Login failed, please try again later", ACCOUNT_PROVIDER_ERROR);
        }

        // Invalid login
        if ($message = $this->http->FindPreg('/此手机号\/邮箱尚未注册，请点击立即注册按钮/i')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // 系统异常,请稍后再试 / System is abnormal. Please try again later
        if (isset($response->errorMsg) && $response->errorMsg == '系统异常,请稍后再试') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Too many login errors, please try again later
        if ($message = $this->http->FindPreg("/\"(Too many login errors, please try again later)\"/ims")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Login failed, please try again later
        if ($message = $this->http->FindPreg('/\{"code":"600005","message":"((?:登录失败,请稍后再试|Login failed, please try again later))"\}/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        sleep(1);
        $this->http->GetURL('http://b2c.csair.com/B2C40/modules/order/orderManagementFrame.jsp?systime=' . time() . date('B'));
        //		$this->http->GetURL('http://skypearl.csair.com/skypearl/en/tomemberArea.action?urlredirect=integralquery2');

        // Invalid password
        if ($this->http->FindPreg("/<LoginError>([^<]+)<\/LoginError>/ims")) {
            throw new CheckException("Invalid Sky Pearl Number or Password", ACCOUNT_INVALID_PASSWORD);
        } /*checked*/

        //if ($this->http->FindSingleNode("//a[contains(text(), 'Exit')]"))
        // exit links seems to be inserted by JS

        if ($location = $this->http->FindPreg("/window\.location\.replace\(\'([^\']+)/ims")) {
            $this->http->NormalizeURL($location);
            $this->http->GetURL($location);
        }
        // not a member
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Join CZ Skypearl now')]")) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // Sorry, system error, please check detailed information!
        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Sorry, system error, please check detailed information!')]")) {
            throw new CheckRetryNeededException(3, 10, $message);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->data->userEnName ?? null));
        /* wrong balance
        // Balance - Mileage Balance (AccountID: 3333942)
        if (isset($response->data->points)) {
            $this->SetBalance($response->data->points);
        }
        */

        $this->http->PostURL("https://skypearl.csair.com/skypearl/personal/myAccount", 'null');
        $responseMyAccount = $this->http->JsonLog();

        if (
            !isset($responseMyAccount->data->accountAccrualDto)
            && isset($responseMyAccount->msg)
            && strstr($responseMyAccount->msg, '请求核心失败[错误：请求核心失败[状态码：')
        ) {
            $this->http->PostURL("https://skypearl.csair.com/skypearl/personal/myAccount", 'null');
            $responseMyAccount = $this->http->JsonLog();
        }

        // Validity
        if (!isset($responseMyAccount->data->accountAccrualDto)) {
            return;
        }
        $this->SetProperty("StatusExpiration", $responseMyAccount->data->accountAccrualDto->tierExpDate ?? null);
        // Registered Date
        $this->SetProperty("MemberSince", str_replace('-', '/', $responseMyAccount->data->accountAccrualDto->effectiveDate ?? null));

        $headers = [
            "SKYPEARL-CSRF" => $this->http->getCookieByName("SKYPEARL-CSRF", ".csair.com"),
        ];
        $this->http->PostURL("https://skypearl.csair.com/skypearl/mileage/manage/query", 'null', $headers);
        $response = $this->http->JsonLog();
        $data = $response->data ?? null;

        // fixed some accounts: 2222822, 208269, 782823
        if (
            !$response
            && strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
        ) {
            $this->logger->notice("set data from previous request");
            $data = $responseMyAccount->data->accountAccrualDto;
        }

        // Balance - Mileage Balance
        if (
            !isset($data->usefulMileage)
            && !isset($data->remainingMileage)
        ) {
            $this->logger->notice("something went wrong");

            return;
        }

        $this->SetBalance($data->usefulMileage ?? $data->remainingMileage);
        // Current Tier
        switch ($data->currentTier) {
            case 'PTK':
                $this->SetProperty("Tier", "Classic");

                break;

            case 'YK':
                $this->SetProperty("Tier", "Silver");

                break;

            case 'JK':
                $this->SetProperty("Tier", "Gold");

                break;

            case 'BJK':
                $this->SetProperty("Tier", "Platinum");

                break;

            default:
                if (isset($data->currentTier)) {
                    $this->sendNotification("Unknown status: {$data->currentTier}");
                }
        }
        // Name
        if (empty($this->Properties['Name'])) {
            $this->SetProperty('Name', beautifulName($data->enMemberName ?? null));
        }
        // Membership Number
        $this->SetProperty("MembershipNumber", $data->memberNo ?? null);
        // Elite Qualification Mileage
        $this->SetProperty('EliteQualificationMileage', $data->upgradeMileage ?? null);
        // Elite Qualification Segment
        $this->SetProperty('EliteQualificationSegment', $data->upgradeSegment ?? null);
        // Mileages overdraft
        $this->SetProperty('MileagesOverdraft', $data->overdraftLimit ?? null);
        // Mileages Expired
        $this->SetProperty('MileagesExpired', $data->expiringAmount ?? null);

        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $data->loyaltyType == '') {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        $nodes = $data->periodDataList ?? [];
        $this->logger->debug("Total nodes were found: " . count($nodes));
        $expPointsZero = 0;

        foreach ($nodes as $node) {
            $expPoints = $node->expiringAmount;

            if ($expPoints > 0) {
                // Expiring Balance
                $this->SetProperty('ExpiringBalance', $expPoints);
                $expDate = $node->periodEndDate;
                $expDate = explode('-', $expDate);
                $this->logger->debug(var_export($expDate, true), ["pre" => true]);

                if (isset($expDate[0], $expDate[1])
                    && strlen($expDate[0]) == 4 && strlen($expDate[1]) == 2) {
                    $this->SetExpirationDate(mktime(0, 0, 0, ($expDate[1] + 1), 0, $expDate[0]));
                }

                break;
            }// if ($expPoints > 0)
            elseif ($expPoints == 0) {
                $expPointsZero++;
            }
        }// for ($i = 0; $i < $notes->length; $i++)

        if (!isset($this->Properties['ExpiringBalance']) && $expPointsZero == 12) {
            $this->ClearExpirationDate();
        }
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetProxy($this->proxyReCaptcha());
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
            $selenium->http->GetURL('https://b2c.csair.com/B2C40/modules/bookingnew/manage/login.html?lang=en');

            $btnAnotherForm = $selenium->waitForElement(WebDriverBy::xpath('//li[@data-boxname = "memberBox"]'), 20);

            if (!isset($btnAnotherForm)) {
                return false;
            }
            $btnAnotherForm->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::id('userId'), 5);
            $passPlaceholder = $selenium->waitForElement(WebDriverBy::id('passWordPH'), 0);
            $passInput = $selenium->waitForElement(WebDriverBy::id('passWord'), 0, false);
            $vfrn = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-placeholder = "VFRN Code"]'), 0);
            $checkboxPrivacyNotice = $selenium->waitForElement(WebDriverBy::id('loginProtocol'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::id('mem_btn_login'), 0);

            if (!isset($loginInput, $passPlaceholder, $passInput, $checkboxPrivacyNotice, $btn)) {
                return false;
            }

            $selenium->driver->executeScript('let c = document.getElementById("loginProtocol"); if (!c.checked) c.click();');
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passPlaceholder->click();
            $passInput->sendKeys($this->AccountFields['Pass']);

            if (isset($vfrn)) {
                $this->savePageToLogs($selenium);
                $code = $this->parseCaptchaImg();
                if (!$code) {
                    return false;
                }
                $vfrn->sendKeys($code);
            }

            $this->savePageToLogs($selenium);
            $btn->click();

            $logoutBtn = $selenium->waitForElement(WebDriverBy::xpath('//a[@class = "zsl-logout"]'), 30);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");

            return isset($logoutBtn);
        } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("Caught TimeoutException");
            $retry = true;
        } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
            if (str_contains($e->getMessage(), 'about:neterror?e=connectionFailure')) {
                $this->logger->error($this->DebugInfo = 'net error, connection failure');
            }
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }
    }

    private function parseCaptchaImg()
    {
        $this->logger->notice(__METHOD__);
        $imgURL = $this->http->FindSingleNode('//img[starts-with(@src, "https://b2c.csair.com/portal/user/verify/challenge?_=")]/@src');
        if (!$imgURL) {
            return false;
        }
        $imgPath = $this->http->DownloadFile($imgURL);
        if (!$imgPath) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            'numeric' => 2,
            'language' => 2,
        ];
        $captcha = $this->recognizeCaptcha($this->recognizer, $imgPath, $parameters);
        unlink($imgPath);

        return $captcha;
    }
}
