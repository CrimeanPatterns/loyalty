<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerChinaeastern extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $retrying = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->useChromium();
        $this->useCache();
        $this->http->SetProxy($this->proxyReCaptcha());

        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/json;charset=UTF-8",
        ];
        $this->http->PostURL("https://easternmiles.ceair.com/membershipapi/api/memberApi/member/retrieveCalcualteUpgradaPoint", "{}", $headers);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !$this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        /*
        $this->http->GetURL("https://passport.ceair.com/cesso/login!check.shtml?redirectUrl=https%3A%2F%2Feasternmiles.ceair.com%2Fmembers%2FintegralDetails.html");

        // proxy issues
        if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')) {
            throw new CheckRetryNeededException(3, 7);
        }

        if (!$this->http->ParseForm("form_login1")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('user', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('login', "Login");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->NormalizeURL($formURL);

        $captcha = $this->parseCaptcha('https://passport.ceair.com/cesso/kaptcha.servlet');

        if ($captcha === false) {
            return false;
        }
        $form['validcode'] = $captcha;
        $this->http->RetryCount = 2;
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;

        return true;
        */

//        $this->http->GetURL("https://us.ceair.com/en/");
        $this->http->GetURL("https://us.ceair.com/en/login.html");

        $loginInput = $this->waitForElement(WebDriverBy::id('username'), 5);
        $passwordInput = $this->waitForElement(WebDriverBy::id('password'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);
        $this->saveResponse();

        if (!$loginInput || !$passwordInput || !$button) {
            return false;
        }

        $loginInput->click();
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passwordInput->click();
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $verificationCode = $this->waitForElement(WebDriverBy::id('verificationCode'), 0);
        $captcha = $this->waitForElement(WebDriverBy::xpath('//img[@alt="Verification Code"]'), 0);

        if (!$verificationCode || !$captcha) {
            return false;
        }

        $pathToScreenshot = $this->takeScreenshotOfElement($captcha);

        if (!$pathToScreenshot) {
            $this->logger->error('Failed to get screenshot of iFrame with captcha');

            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, ['language' => 1]);
        unlink($pathToScreenshot);

        $verificationCode->click();
        $verificationCode->sendKeys(strtolower($captcha));

        $button->click();

        $res = $this->waitForElement(WebDriverBy::xpath('
            //dt[contains(text(), "My account")]
            | //label[contains(@class, "showErrorMsg")]//span[@aria-hidden]
            | //div[contains(@class, "errorMsg")]/ul
            | //div[contains(., "To ensure the security of member information, the password system has been upgraded.") or contains(., "The member system has been upgraded and the payment password login is no longer available")]
        '), 10);
        $this->saveResponse();

        // provider bug workaround
        if ($res && strstr($res->getText(), 'Please fill in the following')) {
            $this->logger->debug("click");

            $error = $this->http->FindSingleNode('//input[@name = "verificationCode"][@placeholder = "Verification Code"]/@aria-label');

            if ($error) {
                $this->logger->error("[Error]: {$error}");
                $this->captchaReporting($this->recognizer, false);
            }

            $loginInput = $this->waitForElement(WebDriverBy::id('username'), 0);
            $passwordInput = $this->waitForElement(WebDriverBy::id('password'), 0);
            $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);

            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

            $verificationCode->clear();
            $captcha = $this->waitForElement(WebDriverBy::xpath('//img[@alt="Verification Code"]'), 0);

            if (!$verificationCode || !$captcha) {
                return false;
            }

            $pathToScreenshot = $this->takeScreenshotOfElement($captcha);

            if (!$pathToScreenshot) {
                $this->logger->error('Failed to get screenshot of iFrame with captcha');

                return false;
            }

            $captcha = $this->recognizeCaptcha($this->recognizer, $pathToScreenshot, ['language' => 1]);
            unlink($pathToScreenshot);

            $verificationCode->sendKeys(strtolower($captcha));
            $this->saveResponse();

            $button->click();
            $this->waitForElement(WebDriverBy::xpath('//dt[contains(text(), "My account")]'), 10);

            $this->saveResponse();
        }

        /*
//        if (!$this->http->ParseForm("form_login1")) {
//            return $this->checkErrors();
//        }

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $headers = [
            "Accept"           => "application/json, text/javascript, *
        /*; q=0.01",
            "Accept-Encoding"  => "gzip, deflate, br",
            "Content-Type"     => "application/json; charset=utf-8",
//            "Shakehand"        => "4717614c263b72a7bcadac1384dd2860",//todo
            "Site"             => "en_US",
            "X-Requested-With" => "XMLHttpRequest",
//            "X-Tingyun-Id"     => "Lm0q0Wx4_8Y;r=89890907, DuR5xFLm8eI;r=889890907",//todo
            "Referer"          => "https://us.ceair.com/en/login.html",
        ];
        $this->http->RetryCount = 0;
//        $this->http->GetURL("https://us.ceair.com/mub2c/portal/v2/member/loginWithFFP?loginType=0&username={$this->AccountFields['Login']}&password={$this->AccountFields['Pass']}&verifyCode={$captcha}&_=" . date("UB"), $headers);
        $this->http->GetURL("https://us.ceair.com/mub2c/portal/v2/member/loginWithFFP?loginType=0&username=640250002146&password=00092700&verifyCode={$captcha}&_=" . date("UB"), $headers);
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function Login()
    {
        /*
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $this->http->PostForm();

        // retry on captcha error
        if ($this->http->FindPreg('/Enter correct verification code/ims') && $this->retrying < 3) {
            $this->retrying++;
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();
            $this->logger->debug("retrying " . $this->retrying);
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->Form['validcode'] = $captcha;
            $this->http->PostForm();
            // Access is allowed
            if ($this->loginSuccessful()) {
                return true;
            }

            if ($this->http->FindPreg('/Enter correct verification code/ims') && $this->retrying < 3) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                sleep(1);

                return $this->LoadLoginForm();
            }// if ($this->http->FindPreg('/Enter correct verification code/ims') && $this->retrying < 3)
        }// if ($this->http->FindPreg('/Enter correct verification code/ims') && $this->retrying < 3)

        if ($message = $this->http->FindSingleNode('//span[@id = "errormsg"]')) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, 'If the wrong password is entered for more than a limited number of times,')
                || strstr($message, '密码输入错误超过限定次数，为保证用户安全暂时锁定，24小时后自动解锁')
                || strstr($message, '用户密码不匹配。如有问题，请联系')
                || strstr($message, '用户密码不匹配，请确认你的用户信息正确无误')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Access is allowed
        return $this->loginSuccessful();
        */

        $this->waitForElement(WebDriverBy::xpath('
            //dt[contains(text(), "My account")]
            | //label[contains(@class, "showErrorMsg")]//span[@aria-hidden]
            | //div[contains(@class, "errorMsg")]/ul
            | //div[contains(., "To ensure the security of member information, the password system has been upgraded.") or contains(., "The member system has been upgraded and the payment password login is no longer available")]
            | //p[contains(text(), "To ensure the security of your account, the password system has been upgraded.")]
        '), 0);
        $this->saveResponse();

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        // Sorry! Incorrect verification code, please re-enter.
        $error = $this->http->FindSingleNode('
            //input[@name = "username"][@placeholder = "Frequent flyer / ID Number / E-mail / Mobile phone number" and @aria-label != ""]/@aria-label
            | //input[@name = "verificationCode"][@placeholder = "Verification Code" and @aria-label != ""]/@aria-label
            | //input[@name = "password"][@placeholder = "Password" and @aria-label != ""]/@aria-label
        ');

        if ($error) {
            $this->logger->error("[Error]: {$error}");

            if (
                $error === 'Sorry! Incorrect verification code, please re-enter.'
                || $error === 'Sorry! Incorrect verification code , please re-enter.'
            ) {
                // Sorry! Incorrect verification code, please re-enter.
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($error, 'Sorry, Login failed, account has been locked, please try again in one hour!')
                || strstr($error, 'Password must be entered in Number format!')
                || strstr($error, 'Sorry! Wrong user name or password.')
                || $error == 'Sorry, membership card number does not exist!'
                || $error == 'We cannot find the user name or password you entered. Please note that your username/password is case-sensitive. Please check and enter again.'
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $error;

            return false;
        }

        if ($message = $this->http->FindSingleNode('
                //label[contains(@class, "showErrorMsg")]//span[@aria-hidden]
                | //div[contains(@class, "errorMsg")]/ul
            ')
        ) {
            $this->logger->error("[Error]: {$message}");

            if ($message === 'Verification Code') {
                // Sorry! Incorrect verification code, please re-enter.
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->captchaReporting($this->recognizer);

            if ($message === 'Password') {
                throw new CheckException("Password must be entered in Number format!", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'There is a security risk in the current password.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message === 'Frequent flyer / ID Number / E-mail / Mobile phone number') {
                $this->CheckError($this->http->FindSingleNode('//a[contains(text(), "We cannot find the user name or password you entered. ")]'));
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[p[contains(@class, "login-update-tips")] and (contains(., "To ensure the security of member information, the password system has been upgraded.") or contains(., "The member system has been upgraded and the payment password login is no longer available"))] | //p[contains(text(), "To ensure the security of your account, the password system has been upgraded.")]')) {
            throw new CheckException(str_replace('Friendly reminder ', '', $message), ACCOUNT_INVALID_PASSWORD);
        }

        // no errors, no auth
        if (in_array($this->AccountFields['Login'], [
            '633017019713',
            '610260011121',
            '663012240802',
            '600261119822',
            '603016385993',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//dt[contains(text(), "My account")]')) {
            return true;
        }

        $ffpno = $this->http->getCookieByName("ffpno");
        $this->logger->debug("[ffpno]: '{$ffpno}'");
        $ffpno = trim($ffpno, '"');
        $this->logger->debug("[ffpno]: '{$ffpno}'");

        if (
            $ffpno === $this->AccountFields['Login']
            && !strstr($this->http->Response['body'], '认证失败')
            && !$this->http->FindSingleNode('//div[contains(., "To ensure the security of member information, the password system has been upgraded.") or contains(., "The member system has been upgraded and the payment password login is no longer available")]')
        ) {
            return true;
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

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setProxyParams($this->http->getProxyParams());
    }

    public function Parse()
    {
        $userInfo = $this->driver->executeScript("return localStorage.getItem('userInfo');");
        $response = $this->http->JsonLog($userInfo);

        if (!isset($response->ffpno)) {
            return;
        }

        // Card No.
        $this->SetProperty("CardNumber", $response->ffpno);
        // Name
        $this->SetProperty("Name", beautifulName($response->enGivenname . " " . $response->enFamilyname));
        // Balance - Available balance of Redeemable Points
        $this->SetBalance($response->remainConumsePoint ?? null);

        // Level
        $tierCode = $response->tier ?? null;

        switch ($tierCode) {
            case 'CHD':
                $status = 'Oriental Junior Flyer';

                break;

            case 'STD':
                $status = 'Standard members';

                break;

            case 'SLV':
            case 'SIL':
                $status = 'Silver card member';

                break;

            case 'GOL':
                $status = 'Gold card member';

                break;

            case 'PLT':
                $status = 'Platinum card member';

                break;

            default:
                $this->logger->debug("status: $tierCode");
                $status = '';

                break;
        }// switch ($tierCode)
        $this->SetProperty("Level", $status);

        $this->parseWithCurl();
        $membershipNumber = $response->ffpno;
        $token = $this->browser->getCookieByName("access_token", "us.ceair.com");
        $this->logger->debug("[token]: {$token}");
        $headers = [
            "Accept"           => "*/*",
            "Content-Type"     => "application/json",
            'X-Requested-With' => 'XMLHttpRequest',
            'Referer'          => 'https://us.ceair.com/en/member-home.html',
            'token'            => $token,
            'Site'             => 'en_US',
        ];
        $this->browser->PostURL("https://us.ceair.com/mub2c/portal/v2/member/memberLoginInfo", "{\"membershipNumber\":\"{$membershipNumber}\"}", $headers);
        $response = $this->http->JsonLog($this->browser->Response['body'], 3, true);

        if (!$response) {
            $this->browser->PostURL("https://us.ceair.com/mub2c/portal/v2/member/memberLoginInfo", "{\"membershipNumber\":\"{$membershipNumber}\"}", $headers);
            $response = $this->http->JsonLog($this->browser->Response['body'], 3, true);
        }

        $data = ArrayVal($response, 'data', null);
        $accountSummary = ArrayVal($data, 'memberLoginInfoVO', null);
//        $this->setStatus($accountSummary);

        // Expiration date
//        $expiredPoints = ArrayVal($accountSummary, 'expiredPoints');
//
//        if ($expiredPoints > 0) {
//            $year = ArrayVal($accountSummary, 'year');
//            $exp = strtotime("31 Dec {$year}");
//
//            if ($year && $exp) {
//                $this->SetExpirationDate($exp);
//            }
//            // Points to expire
//            $this->SetProperty("PointsToExpire", $expiredPoints);
//        }
        // Balance - Available balance of Redeemable Points
        $this->SetBalance(ArrayVal($accountSummary, 'remainConumsePoint'));
        // Elite Qualification Segments
        $this->SetProperty("Flights", ArrayVal($accountSummary, 'ucPoint'));
        // Elite Qualification Points
        $this->SetProperty("ElitePoints", ArrayVal($accountSummary, 'upPoint'));
        // EQR-Easternmiles Qualificaton RMB
        $this->SetProperty("EliteRMB", "¥" . ArrayVal($accountSummary, 'umPoint'));
        // Possible overdraft of Redeemable Points
        $this->SetProperty("PossibleOverdraft", ArrayVal($accountSummary, 'availableTotalOverdrawAmount'));
        // Balance of Redeemable Points
        $this->SetProperty("BalanceOfRedeemablePoints", ArrayVal($accountSummary, 'remainConumsePoint'));
        // Overdrawn Redeemable Points
        $this->SetProperty("OverdrawnConsumptionPoints", ArrayVal($accountSummary, 'alreadyOverdrawAmount'));

        return;
//        */

//        $this->http->PostURL("https://us.ceair.com/mub2c/portal/v2/member/memberLoginInfo", "{\"membershipNumber\":\"{$this->http->getCookieByName("ffpno")}\"}", $headers);
//        $this->http->JsonLog();

        if ($this->http->currentUrl() != 'https://easternmiles.ceair.com/membershipapi/api/memberApi/member/retrieveCalcualteUpgradaPoint') {
            $this->http->PostURL("https://easternmiles.ceair.com/membershipapi/api/memberApi/member/retrieveCalcualteUpgradaPoint", "{}", $headers);
        }

        $response = $this->http->JsonLog(null, 3);
        $accountSummary = $response->data->accountSummaryRes ?? null;
        // Balance - Available balance of Redeemable Points
        $this->SetBalance($accountSummary->cumulativePoint ?? null);

        $retrieveCalcualteUpgradaPoint = $response->data->retrieveCalcualteUpgradaPoint ?? null;

        if (empty($this->Properties['Level']) && $this->ErrorCode == ACCOUNT_CHECKED) {
            $tierCode = $this->setStatus($retrieveCalcualteUpgradaPoint);
        }

        if (empty($this->Properties['Level']) && $this->ErrorCode == ACCOUNT_CHECKED) {
            $this->sendNotification("chinaeastern. Unknown status '{$tierCode}'");
        }

        // Expiration date
        $pointSummaryDetails = $accountSummary->pointSummaryDetails ?? [];

        foreach ($pointSummaryDetails as $pointSummaryDetail) {
            if ($pointSummaryDetail->pointFlag == 'OLD') {
                if (isset($exp) && $exp < strtotime($pointSummaryDetail->pointExpiryDate)) {
                    continue;
                }

                $exp = strtotime($pointSummaryDetail->pointExpiryDate);

                if ($exp) {
                    $this->SetExpirationDate($exp);
                }
                // Points to expire
                $this->SetProperty("PointsToExpire", $pointSummaryDetail->points);
            }
        }
        // Elite Qualification Segments
        $this->SetProperty("Flights", $retrieveCalcualteUpgradaPoint->ucpoint ?? null);
        // Elite Qualification Points
        $this->SetProperty("ElitePoints", $retrieveCalcualteUpgradaPoint->uppoint ?? null);
        // EQR-Easternmiles Qualificaton RMB
        if (isset($retrieveCalcualteUpgradaPoint->umPoint)) {
            $this->SetProperty("EliteRMB", "¥" . $retrieveCalcualteUpgradaPoint->umPoint);
        }
        // Possible overdraft of Redeemable Points
        $this->SetProperty("PossibleOverdraft", $accountSummary->availableTotalOverdrawAmount ?? null);
        // Balance of Redeemable Points
        $this->SetProperty("BalanceOfRedeemablePoints", $accountSummary->remainConumsePoint ?? null);
        // Overdrawn Redeemable Points
        $this->SetProperty("OverdrawnConsumptionPoints", $accountSummary->alreadyOverdrawAmount ?? null);
        // Name
        $name = ($accountSummary->enGivenName ?? null) . " " . ($accountSummary->enFamilyName ?? null);

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    public function setStatus($data)
    {
        $this->logger->notice(__METHOD__);
        $tierValidate =
            $data->tierexpdate
            ?? ArrayVal($data, 'tierValidate', null)
            ?? null
        ;
        $this->SetProperty("StatusExpiration", $tierValidate);
        // Level
        $tierCode =
            $data->currenttier
            ?? ArrayVal($data, 'tierCode', null)
            ?? null
        ;

        switch ($tierCode) {
            case 'CHD':
                $status = 'Oriental Junior Flyer';

                break;

            case 'STD':
                $status = 'Standard members';

                break;

            case 'SLV':
            case 'SIL':
                $status = 'Silver card member';

                break;

            case 'GOL':
                $status = 'Gold card member';

                break;

            case 'PLT':
                $status = 'Platinum card member';

                break;

            default:
                $this->logger->debug("status: $tierCode");
                $status = '';

                break;
        }// switch ($tierCode)
        $this->SetProperty("Level", $status);

        return $tierCode;
    }

    protected function parseCaptcha($downFile = 'https://us.ceair.com/mub2c/portal/jcaptcha.servlet?1')
    {
        $this->logger->notice(__METHOD__);
        $file = $this->http->DownloadFile($downFile, "jpeg");
        $this->logger->debug("file: " . $file);
        $this->recognizer = $this->getCaptchaRecognizer();

        return $this->recognizeCaptcha($this->recognizer, $file);
    }
}
