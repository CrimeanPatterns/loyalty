<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirchina extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /** @var CaptchaRecognizer */
    private $recognizer;

    private $seleniumURL = null;
    private $seleniumLoginSuccess = false;

    private $china = true;
    private $maxSlideTries = false; // whether we tried to slide max times
    private $maxAttempt = 4;

    /*
     * refs #15498
     */
    private $badRegExp = "/<meta http-equiv=\"refresh\" content=\"10; url=\/distil_r_(?:drop\.html|captcha\.html\?Re)/ims";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        // $this->http->SetProxy($this->proxyReCaptcha());
        $this->setProxyBrightData();

        if (isset($this->State['User-agent'])) {
            $this->http->setUserAgent($this->State['User-agent']);
        } else {
            $this->http->setRandomUserAgent(10);
            $this->State['User-agent'] = $this->http->userAgent;
        }
    }

    public function IsLoggedIn()
    {
        if (isset($this->State['US']) && $this->State['US'] === true && !$this->china) {
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.airchina.us/US/GB/booking/account/", [], [], 10);
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode("//span[span[contains(text(), 'Member Account Number')]]/following-sibling::span/span[contains(@class, 'value')]")) {
                return true;
            }
        }// if (isset($this->State['US']) && $this->State['US'] === true)
        else {
            $this->http->RetryCount = 0;
            // $this->http->GetURL('http://ffp.airchina.com.cn/app/index/member');
            $this->http->GetURL("https://ffp.airchina.com.cn/appen/login/member", [], 30);
            $this->http->RetryCount = 2;

            if ($this->http->FindSingleNode("//span[contains(@class, 'person-card-name')]")) {
                return true;
            }
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // refs #15498
        // if (isset($this->State['US']) && $this->State['US'] === true) {
        //     $this->usSiteVersion();
        //     return false;
        // }// if (isset($this->State['US']) && $this->State['US'] === true)

        // refs #19548
        if ($this->getShenzenair()) {
            return false;
        }

        if ($this->selenium()) {
            $this->seleniumLoginSuccess = true;

            return true;
        }

        return false;

        // todo: temporarily gag, new captcha on https://ffp.airchina.com.cn/appen/login/member
        $this->usSiteVersion();

        if ($this->ErrorCode == ACCOUNT_CHECKED) {
            $this->State['US'] = true;
        }

        return false;

        $this->http->removeCookies();
        $this->http->GetURL("https://ffp.airchina.com.cn/appen/login/member");

        if (!$this->http->ParseForm("login_form")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://ffp.airchina.com.cn/appen/login/member/submit';

        $login = $this->AccountFields['Login'];

        if (stripos($login, 'CA') !== false || strpos($login, 'CA') > 0) {
            $login = str_ireplace('CA', '', $login);
        }
        $this->http->SetInputValue("loginUid", $login);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("loginCaptcha", $captcha);

        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->PostURL('http://ffp.airchina.com.cn/appen/security/rest/getEncryptKey', null, [
            'X-Requested-With' => 'XMLHttpRequest',
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
        ]);
        $response = $this->http->JsonLog();
        $pub = <<<SOMEDATA777
-----BEGIN PUBLIC KEY-----
$response->publickKey
-----END PUBLIC KEY-----
SOMEDATA777;
        $data = $this->AccountFields['Pass'];
        $pk = openssl_get_publickey($pub);
        openssl_public_encrypt($data, $encrypted, $pk);
        // chunk_split(base64_encode($encrypted));

        $this->http->FormURL = $formURL;
        $this->http->Form = $form;

        $this->http->SetInputValue("loginPwd", base64_encode($encrypted));
        $this->http->SetInputValue("backUrl", "");
        $this->http->unsetInputValue('ref_url');

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $this->checkLoginInfo();
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;

        /** @var TAccountCheckerAirchina $selenium */
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
            $selenium->useChromium(SeleniumFinderRequest::CHROMIUM_80);
            /*
            $selenium->useGoogleChrome();
            */
            $selenium->useCache();
            // $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->driver->manage()->window()->maximize();
            } catch (UnknownServerException $e) {
                $this->logger->error("UnknownServerException exception: " . $e->getMessage());
                $this->DebugInfo = "UnknownServerException";
                $this->logger->error("failed maximize window");
            }

            if (isset($this->State['selenium-user-agent'])) {
                $selenium->http->setUserAgent($this->State['selenium-user-agent']);
            } else {
                $selenium->http->setRandomUserAgent(10, false, true, true);
                $this->State['selenium-user-agent'] = $selenium->http->userAgent;
            }

            $success = false;
            $noSliderCaptcha = false;

            for ($try = 1; $try <= $this->maxAttempt; $try++) {
                $this->logger->notice("[Outer try]: {$try}");

                if (!$this->maxSlideTries) {
                    $this->logger->info('Forcing new captcha');

                    try {
                        $selenium->http->GetURL("https://ffp.airchina.com.cn/appen/login/member");
                    } catch (ScriptTimeoutException $e) {
                        $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
                        $retry = true;

                        return false;
                    }
                }

                $this->increaseTimeLimit(200);

                $slider = $selenium->waitForElement(WebDriverBy::id('dx_captcha_basic_slider_1'), 20);

                if (!$slider) {
                    $success = true;
                    $noSliderCaptcha = true;
                    $this->logger->notice('no captcha yay');

                    break;
                }
                $success = $this->slideCaptchaChinese($selenium, $try);
                $this->savePageToLogs($selenium);

                if ($success) {
                    break;
                }
            }

            if (!$success) {
                // retries
                if ($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please drag the slider")] | //div[@id = "dx_captcha_basic_slider_1"]'), 5)) {
                    $this->captchaRetries();
                }

                return false;
            }
            $try = 0;

            do {
                $this->logger->notice('second captcha ' . $try . ' try');
                $isOrderCaptcha = $selenium->waitForElement(WebDriverBy::cssSelector('#dx_captcha_clickword_hits_2'), 5);

                if ($isOrderCaptcha) {
                    $success = $this->solveOrderCaptcha($selenium);
                }
                $try++;
            } while (
                !$success && $try < 3
            );

            if (!$success) {
                // retries
                if ($selenium->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please drag the slider")] | //div[@id = "dx_captcha_basic_slider_1"]'), 5)) {
                    $this->captchaRetries();
                }

                throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            // Login
            $loginInput = $selenium->waitForElement(WebDriverBy::id('loginUid'), 10);
            // Password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('loginPwd'), 0);

            if (!$loginInput || !$passwordInput) {
                $this->savePageToLogs($selenium);

                // retries
                if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached)/ims')) {
                    throw new CheckRetryNeededException(4);
                }

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->getCleanedLogin());
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::id('submitBtn'), 5);
            $this->logger->notice('click "Sign In"');
            $this->increaseTimeLimit(180);

            if ($button) {
                $button->click();
            }

            $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'sign-out']"), 20);
            $this->increaseTimeLimit(120);

            if (!$loggedIn) {
                $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'sign-out']"), 0);
            }
            $this->savePageToLogs($selenium);
            $this->increaseTimeLimit(120);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
            // $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@data-href, "onlinelogout")]'), 10, false);
            if (!$loggedIn && !$this->http->FindPreg("/<a id=\"sign-out\"/") && /*debug*/ $selenium->http->currentUrl() != 'http://ffp.airchina.com.cn/appen/en/index-en.html') {
                // Verify Your Identity
                if ($selenium->waitForElement(WebDriverBy::xpath('//h1[b[contains(text(), "Verify Your Identity")]]'), 0)) {
                    $this->throwProfileUpdateMessageException();
                }
                // Invalid credentials
                // Captcha is incorrect
                // New invalid credentials
                $errorMessage = $selenium->waitForElement(WebDriverBy::xpath('//div[
                    @id = "errors" and (
                        contains(text(), "Captcha is incorrect")
                        or contains(text(), "Captcha Error.")
                        or contains(text(), "Account or password is incorrect.")
                        or contains(text(), "The length of your password is incorrect.")
                        or contains(text(), "Please enter 12digits card number")
                        or contains(text(), "请输入正确的帐户或密码，您还有")
                        or contains(text(), "Oops! Something went wrong. Please try again.")
                        or contains(text(), "CRM服务异常")
                        or contains(text(), "The account digits are incorrect")
                        or contains(text(), "Other Error")
                        or contains(text(), "You have more than one account, please call 95583 to merge them.")
                        or contains(text(), "Your current network environment is unstable.")
                        or contains(text(), "Please confirm that you fully agree to the PhoenixMiles Terms")
                    )
                ]'), 0);

                if ($errorMessage) {
                    $message = $errorMessage->getText();

                    if (
                        strstr($message, 'Captcha is incorrect')
                        || strstr($message, 'Captcha Error.')
                    ) {
                        if ($noSliderCaptcha) {
                            $this->captchaRetries(3, 10);
                        } else {
                            $this->captchaRetries();
                        }

                        return;
                    }
                    // Oops! Something went wrong. Please try again.
                    if (
                        strstr($message, 'Oops! Something went wrong. Please try again.')
                        || strstr($message, 'Other Error')
                        || strstr($message, 'You have more than one account, please call 95583 to merge them.')
                        || strstr($message, 'Your current network environment is unstable.') // AccountID: 4531828
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (strstr($message, 'CRM服务异常')) {
                        throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (strstr($message, 'Please confirm that you fully agree to the PhoenixMiles Terms and Conditions and')) {
                        $this->throwAcceptTermsMessageException();
                    }
                    // account lockout
                    if (strstr($message, '密码已输入错误6次，账户已被锁定，请于1小时后再尝试')) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($errorMessage = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "errors"]'), 0)) {
                    $this->savePageToLogs($selenium);
                    $message = $errorMessage->getText();
                    $this->logger->error($message);

                    // retry, empty error on login form
                    if (empty($message) && $this->http->FindPreg("/<div class=\"msg-error\" id=\"errors\">\s*<b><\/b>\s*<\/div>/")) {
                        throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
                    }

                    if ($message) {
                        $this->DebugInfo = $message;

                        return false;
                    }
                }// if ($errorMessage = $selenium->waitForElement(WebDriverBy::xpath('//div[@id = "errors"]'), 0))

                $this->savePageToLogs($selenium);

                // provider bug fix
                if ($selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "For your account security, please confirm your identity.")]'), 0)) {
                    $frozenBtn = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "frozenBtn"]'), 0);

                    if (!$frozenBtn) {
                        return false;
                    }
                    $frozenBtn->click();
                    $authenticateBy = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Authenticate by Face recognition")]'), 5);
                    $this->savePageToLogs($selenium);

                    if (!$authenticateBy) {
                        if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(., "There is no ID document information for your account . Please add the document before the confirmation of your identity.If you have any questions, please call 95583.") or contains(.,"Your account information is incomplete,thus identity authentication can’t be made,please contact 95583 for any questions!")]'), 0)) {
                            $this->throwProfileUpdateMessageException();
                        }

                        return false;
                    }
                    $authenticateBy->click();

                    $switchToEng = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'English')]"), 10);
                    $this->savePageToLogs($selenium);

                    if (!$switchToEng) {
                        return false;
                    }
                    $switchToEng->click();
                    $myAccount = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'My Account')]"), 10);
                    $this->savePageToLogs($selenium);

                    if (!$myAccount) {
                        return false;
                    }
                    $myAccount->click();
                    $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(@class, 'person-info-name')]"), 5);
                    $this->savePageToLogs($selenium);
                }

                if ($selenium->waitForElement(WebDriverBy::cssSelector('#dx_captcha_basic_bar-success_1[style]'), 0)) {
                    $this->logger->notice("provider bug fix");
                    // Sign In
                    $button = $selenium->waitForElement(WebDriverBy::id('submitBtn'), 5);

                    if ($button) {
                        $this->savePageToLogs($selenium);
                        $button->click();
                        sleep(2);
                        $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'sign-out']"), 10);
                        $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Exit')]"), 10);
                        $this->savePageToLogs($selenium);

                        if (!$loggedIn) {
                            $loggedIn = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'sign-out']"), 0);
                        }
                        $this->savePageToLogs($selenium);
                    }
                    $retry = true;
                }
                // AccountID: 4931361
                if (
                    stripos($selenium->http->currentUrl(), "https://ffp.airchina.com.cn/appen/auth/member/index?backUrl=/forget/member/resetPassword&keyname") !== false
                    && $selenium->waitForElement(WebDriverBy::xpath('//b[contains(text(), "Authenticate by card No.")]'), 0)
                ) {
                    throw new CheckException('Please change your password', ACCOUNT_PROVIDER_ERROR);
                }

                if (!$loggedIn) {
                    return false;
                }
            }// if (!$loggedIn)

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->savePageToLogs($selenium);
            $this->seleniumLoginSuccess = true;
            $this->logger->info("seleniumLoginSuccess = true");

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
            $result = true;
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            $retry = true;
        }// catch (TimeOutException $e)
        catch (WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException exception: " . $e->getMessage());
            $this->DebugInfo = "WebDriverCurlException";
            // retries
            $retry = true;
        }// catch (WebDriverCurlException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3, 1);
            }
        }

        return $result;
    }

    public function Login()
    {
        if ($this->seleniumLoginSuccess) {
            return true;
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog();

        if (isset($response->redirect, $response->url) && $response->redirect == true) {
            $redirect = $response->url;
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);

            // Privacy Policy
            if ($this->http->FindSingleNode("//h3[contains(text(),'Privacy Policy')]")) {
                $this->throwAcceptTermsMessageException();
            }
        }// if (isset($response->redirect, $response->url) && $response->redirect == true)

        if (isset($response->message)) {
            $this->logger->debug("[Message]: {$response->message}");
            // successful login
            if ($response->message == 'Log In successfully') {
                return true;
            }
            // Captcha Error
            if ($response->message == 'Captcha Error.') {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }// if ($response->message == 'Captcha Error.')
            // Account or password is incorrect.
            if ($response->message == 'Account or password is incorrect.'
                || strstr($response->message, '请输入正确的帐户或密码，您还有')) {
                throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);
            }
            // Please change your password
            if ($response->message == 'Please change your password') {
                throw new CheckException($response->message, ACCOUNT_PROVIDER_ERROR);
            }
            // CRM服务异常
            if ($response->message == 'CRM服务异常') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // 密码已输入错误6次，账户已被锁定，请于1小时后再尝试
            if ($response->message == '密码已输入错误6次，账户已被锁定，请于1小时后再尝试') {
                throw new CheckException($response->message, ACCOUNT_LOCKOUT);
            }

            // Other Error
//            if ($response->message == 'Other Error') {
            $this->usSiteVersion();

            if ($this->ErrorCode == ACCOUNT_CHECKED) {
                $this->State['US'] = true;
            }

            return false;
//            }// if ($response->message == 'Other Error')
        }// if (isset($response->message))

//        if ($message = $this->http->FindPreg("/card number or password error/ims"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (isset($this->State['US']) && $this->State['US'] === true && !$this->china) {
            $this->ParseUS();

            return;
        }// if (isset($this->State['US']) && $this->State['US'] === true)

        $this->http->GetURL("http://ffp.airchina.com.cn/appen/index/member/", [], 30);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//li[contains(text(), 'Welcome，')]", null, true, "/，\s*([^<]+)/")));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'person-card-name')]"));
        // Membership Level Expiration Date
        $this->SetProperty("StatusExpiration", str_replace(['?', '：'], '', Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'Membership Level Expiration Date')]", null, true, "/Date:?\s*([^<]+)/"))));
        // Balance - Kilometers Balance
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'Kilometers Balance')]/b"));
        // Kilometers to next level
        $this->SetProperty("ClubMiles", $this->http->FindSingleNode("(//p[@class = 'person-mileage-news'])[1]", null, true, "/fly\s*([\d\.\,]+)\s*(?: Air China Lifetime Platinum\s*|)kilometer/"));
        // Segments to next level
        $this->SetProperty("Segments", $this->http->FindSingleNode("(//p[@class = 'person-mileage-news'])[1]", null, true, "/or\s*([\d\.\,]+)\s*segment/"));
        // Will Expire In 3 Months
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//p[contains(text(), 'Kilometers Will Expire In 3 Months')]/b"));
        // Air China Lifetime Platinum mileage
        $this->SetProperty("LifetimeMileage", $this->http->FindSingleNode("//p[contains(text(), 'Lifetime')]/b"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->FindSingleNode('//div[@class = \'my_perogative\' and contains(., \'FreeMarker template error: The following has evaluated to null or missing: ==> memberGrade.memberGradeTypes [in template "member/index.ftl"\')]')) {
            $this->http->GetURL("http://ffp.airchina.com.cn/appen/mileage/member?id=0");
            // Balance - Kilometers Balance
            $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Balance of your account')]/following-sibling::p/text()[1]"));
        }

        $this->http->GetURL("http://ffp.airchina.com.cn/appen/member/manage/index");
        // Card No.
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//dt[contains(text(), 'Card No：')]/following-sibling::dd/text()[1]"));
    }

    /** @ return TAccountCheckerShenzenair */
    protected function getShenzenair()
    {
        $this->logger->notice(__METHOD__);

        require_once __DIR__ . '/TAccountCheckerShenzenair.php';
        $shenzenair = new TAccountCheckerShenzenair();
        $shenzenair->http = new HttpBrowser("none", new CurlDriver());
//        $shenzenair->http->setProxyParams($this->http->getProxyParams());

        $this->http->brotherBrowser($shenzenair->http);
        $shenzenair->AccountFields = $this->AccountFields;
        $shenzenair->HistoryStartDate = $this->HistoryStartDate;
        $shenzenair->http->LogHeaders = $this->http->LogHeaders;
        $shenzenair->ParseIts = $this->ParseIts;
        $shenzenair->ParsePastIts = $this->ParsePastIts;
        $shenzenair->WantHistory = $this->WantHistory;
        $shenzenair->WantFiles = $this->WantFiles;
        $shenzenair->globalLogger = $this->globalLogger; // fixed notifications
//        $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
//        $this->logger->debug("set headers");
//        $defaultHeaders = $this->http->getDefaultHeaders();
//        foreach ($defaultHeaders as $header => $value) {
//            $shenzenair->http->setDefaultHeader($header, $value);
//        }

        if (/* not working $shenzenair->IsLoggedIn() || */ ($shenzenair->LoadLoginForm() && $shenzenair->Login())) {
            $shenzenair->Parse();
            $this->SetBalance($shenzenair->Balance);
            $this->Properties = $shenzenair->Properties;
        }

        return $shenzenair->ErrorCode == ACCOUNT_CHECKED;
    }

    private function usSiteVersion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->LoadLoginFormUS() && $this->LoginUS()) {
            $this->ParseUS();
        }// if ($this->LoadLoginFormUS() && $this->LoginUS())
    }

    private function takeScreenshotOfElement(RemoteWebElement $elem, $selenium = null, $half = false)
    {
        $this->logger->notice(__METHOD__);

        if (!$elem) {
            return false;
        }

        if (!$selenium) {
            $selenium = $this;
        }
        $time = getmypid() . "-" . microtime(true);
        $path = '/tmp/seleniumPageScreenshot-' . $time . '.jpg';
        $selenium->driver->takeScreenshot($path);
        $img = imagecreatefrompng($path);
        unlink($path);

        if (!$img) {
            return false;
        }
        $rect = [
            'x'      => $elem->getLocation()->getX(),
            'y'      => $elem->getLocation()->getY(),
            'width'  => $elem->getSize()->getWidth(),
            'height' => $half ? intval($elem->getSize()->getHeight() / 2) : $elem->getSize()->getHeight(),
        ];
        $cropped = imagecrop($img, $rect);

        if (!$cropped) {
            return false;
        }
        $path = '/tmp/seleniumElemScreenshot-' . $time . '.jpg';
        $status = imagejpeg($cropped, $path);

        if (!$status) {
            return false;
        }
        $this->logger->info('screenshot taken');

        return $path;
    }

    private function parseCaptchaCoordinates($elem, TAccountCheckerAirchina $selenium, $attempt, $isOrder = false)
    {
        $this->logger->notice(__METHOD__);

        if (!$elem) {
            $this->logger->error('Cannot take screenshot of an empty element');

            return false;
        }

        try {
            $half = !$isOrder;
            $pathToScreenshot = $this->takeScreenshotOfElement($elem, $selenium, $half);
        } catch (Throwable $e) {
            $this->logger->error("Throwable exception: " . $e->getMessage());

            return false;
        }

        $this->logger->debug('Path to captcha screenshot ' . $pathToScreenshot);
        $data = [
            'coordinatescaptcha' => '1',
        ];

        if ($isOrder) {
            $data['textinstructions'] = 'click _on_ the symbols in the correct order / кликните _на_ символы в правильном порядке';
        } else {
            try {
                $data['imginstructions'] = $this->getCaptchaInstructions($selenium);
            } catch (ErrorException $e) {
                $this->logger->error("ErrorException: " . $e->getMessage());

                return false;
            }
        }

        try {
            $this->increaseTimeLimit(180);
            $text = $this->recognizer->recognizeFile($pathToScreenshot, $data);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                // almost always solvable
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                if ($attempt == $this->maxAttempt) {
                    $this->captchaRetries();
                }

                return false;
            }

            if (
                strstr($e->getMessage(), 'CURL returned error: Operation timed out after ')
                || strstr($e->getMessage(), 'timelimit (120) hit')
                || strstr($e->getMessage(), 'CURL returned error: Failed to connect to rucaptcha.com port 80')
                || strstr($e->getMessage(), 'slot not available')
                || strstr($e->getMessage(), 'service not available')
            ) {
                if ($attempt == $this->maxAttempt) {
                    $this->captchaRetries();
                }

                return false;
            } else {
                throw $e;
            }
        } finally {
            unlink($pathToScreenshot);
        }

        $coords = $selenium->parseCoordinates($text);

        return $coords;
    }

    private function getCaptchaInstructions(TAccountCheckerAirchina $selenium): CURLFile
    {
        $this->logger->notice(__METHOD__);
        $selenium->saveResponse();
        $shapePath = $selenium->http->FindSingleNode('//div[@id = "dx_captcha_basic_sub-slider_1"]/img[1]/@src');

        if (!$shapePath) {
            return new CURLFile(__DIR__ . '/captcha_instructions.jpg');
        }

        try {
            $shape = imagecreatefromwebp($shapePath);
        } catch (ErrorException $e) {
            $this->logger->error("ErrorException: {$e->getMessage()}");

            return new CURLFile(__DIR__ . '/captcha_instructions.jpg');
        }
        $width = imagesx($shape);
        $height = imagesy($shape);
        // make it gray
        $white = imagecolorallocate($shape, 255, 255, 255);
        $gray = imagecolorallocate($shape, 64, 64, 64);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $width; $y++) {
                $index = imagecolorat($shape, $x, $y);
                $color = imagecolorsforindex($shape, $index);
                $dark = $color['red'] < 20 && $color['green'] < 20 && $color['blue'] < 20;

                if ($dark) {
                    imagesetpixel($shape, $x, $y, $white);
                } else {
                    imagesetpixel($shape, $x, $y, $gray);
                }
            }
        }

        $shape = imagescale($shape, intval($width / 2), intval($height / 2));

        // put red dot in the center
        $red = imagecolorallocate($shape, 255, 0, 0);
        $w = imagesx($shape);
        $h = imagesx($shape);
        $dotSize = 2;

        for ($x = $w / 2 - $dotSize; $x < $w / 2 + $dotSize; $x++) {
            for ($y = $h / 2 - $dotSize; $y < $h / 2 + $dotSize; $y++) {
                imagesetpixel($shape, intval($x), intval($y), $red);
            }
        }

        // put the shape on the template
        $img = imagecreatefromjpeg(__DIR__ . '/captcha_instructions_template.jpg');
        $width = imagesx($img);
        $height = imagesy($img);
        $offset = 2;

        for ($x = $offset; $x < $w - $offset; $x++) {
            for ($y = $offset; $y < $h - $offset; $y++) {
                $index = imagecolorat($shape, $x, $y);
                $newX = $x + intval($width / 2 - $w / 2);
                $newY = $y + intval($height / 2 - $h / 2);
                imagesetpixel($img, $newX, $newY, $index);
            }
        }

        imagejpeg($img, '/tmp/captcha_instructions.jpg', 100);
        imagedestroy($shape);
        imagedestroy($img);

        return new CURLFile('/tmp/captcha_instructions.jpg');
    }

    private function slideTry($mouse, MouseMover $mover, TAccountCheckerAirchina $selenium, $targetAbs)
    {
        $this->logger->notice(__METHOD__);
        $slider = $selenium->waitForElement(WebDriverBy::id('dx_captcha_basic_slider_1'), 5);

        if (!$slider) {
            return false;
        }
        $mover->moveToElement($slider, ['x' => 0, 'y' => 5]);
        // $mouse->mouseDown();
        sleep(1);
        $mover->moveToCoordinates($targetAbs, ['x' => 0, 'y' => 5]);
        sleep(rand(1, 3));
        $selenium->SaveResponse();
        $mouse->mouseUp();
        $success = $selenium->waitForElement(WebDriverBy::cssSelector('#dx_captcha_basic_bar-success_1[style], #dx_captcha_clickword_hits_2'), 10);

        return $success ? true : false;
    }

    private function slideCaptchaChinese(TAccountCheckerAirchina $selenium, $attempt)
    {
        $this->logger->info(__METHOD__);
        $this->maxSlideTries = false;
        $this->increaseTimeLimit(120);
        // setup
        $slider = $selenium->waitForElement(WebDriverBy::id('dx_captcha_basic_slider_1'), 5);

        if (!$slider) {
            return false;
        }
        $this->logger->info('=slider:');
        $coords = $slider->getCoordinates()->inViewPort();
        $this->logger->info(var_export([
            'x' => $coords->getX(),
            'y' => $coords->getY(),
        ], true), ['pre' => true]);

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mover->steps = rand(40, 60);
        $mouse = $selenium->driver->getMouse();

        // captcha
        $mover->moveToElement($slider);
        $mouse->mouseDown();
        sleep(1);
        $captchaElem = $selenium->waitForElement(WebDriverBy::id('demo-form-site'), 5);
        $this->logger->info('=captchaCoords:');
        $captchaCoords = $captchaElem->getCoordinates()->inViewPort();
        $this->logger->info(var_export([
            'x' => $captchaCoords->getX(),
            'y' => $captchaCoords->getY(),
        ], true), ['pre' => true]);
        $targetRel = $this->parseCaptchaCoordinates($captchaElem, $selenium, $attempt, false);

        if (!$targetRel) {
            return false;
        }
        $selenium->saveResponse();
        // $mouse->mouseUp();

        // cleanup
        $maxTargetX = -1;

        foreach ($targetRel as $t) {
            $x = intval($t['x']);

            if ($x > $maxTargetX) {
                $maxTargetX = $x;
            }
        }

        if ($maxTargetX === -1) {
            return false;
        }
        $deltaX = $maxTargetX;
        // $deltaX -= intval($slider->getSize()->getWidth() / 2);
        $deltaX -= 35;
        $deltaX -= 21;
        $this->logger->info('deltaX:');
        $this->logger->info($deltaX);
        // left image was clicked on instead the right one
        if ($deltaX < 50) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            return false;
        }
        $targetAbs = [
            'x' => $coords->getX() + $deltaX,
            'y' => $coords->getY(),
        ];
        $this->logger->info('=targetAbs:');
        $this->logger->info(var_export($targetAbs, true), ['pre' => true]);

        // tries, max 3 possible
        $subSliderSrc = $selenium->http->FindSingleNode('//div[@id = "dx_captcha_basic_sub-slider_1"]/img[1]/@src');

        foreach ([0, +1, -1] as $i => $dx) { // offsets
            $try = $i + 1;
            $this->logger->info("inner try = {$try}, slide dx = {$dx}");
            $tryTargetAbs = ['x' => $targetAbs['x'] + $dx, 'y' => $targetAbs['y']];
            $success = $this->slideTry($mouse, $mover, $selenium, $tryTargetAbs);
            $selenium->saveResponse();

            if ($success) {
                return true;
            }
            $newSubSliderSrc = $selenium->http->FindSingleNode('//div[@id = "dx_captcha_basic_sub-slider_1"]/img[1]/@src');
            $this->logger->info(var_export(['subSliderSrc' => $subSliderSrc, 'newSubSliderSrc' => $newSubSliderSrc], true));

            if ($newSubSliderSrc !== $subSliderSrc) {
                $this->logger->error('captcha has changed');

                return false;
            }
        }

        $this->maxSlideTries = true;

        return false;
    }

    /**
     * Taken from the site.
     */
    private function checkLoginInfo()
    {
        $this->logger->notice(__METHOD__);
        $login = $this->getCleanedLogin();
        $password = $this->AccountFields['Pass'];
        $loginRe = '/^[0-9]*$/';
        $passwordRe = '/^[0-9]{6}/';

        if (!$this->http->FindPreg($loginRe, false, $login) && strlen($login) < 18) {
            throw new CheckException('Please enter 12digits card number', ACCOUNT_INVALID_PASSWORD);
        }

        if (!$this->http->FindPreg($passwordRe, false, $password) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckException('The length of your password is incorrect.', ACCOUNT_INVALID_PASSWORD);
        }
    }

    private function getCleanedLogin()
    {
        $this->logger->notice(__METHOD__);
        $login = $this->AccountFields['Login'];

        if (stripos($login, 'CA') !== false || strpos($login, 'CA') > 0) {
            $login = str_ireplace('CA', '', $login);
        }

        return trim($login);
    }

    private function captchaRetries($checkAttemptsCount = 1, $retryTimeout = 1)
    {
        $this->logger->notice(__METHOD__);

        throw new CheckRetryNeededException($checkAttemptsCount, $retryTimeout, self::CAPTCHA_ERROR_MSG);
    }

    private function solveOrderCaptcha(TAccountCheckerAirchina $selenium)
    {
        $this->logger->notice(__METHOD__);
        $this->increaseTimeLimit(60);
        $captchaElem = $selenium->waitForElement(WebDriverBy::cssSelector('#dx_captcha_basic_two-step_1'), 5);
        $captchaPort = $captchaElem->getCoordinates()->inViewPort();
        $captchaCoords = ['x' => $captchaPort->getX(), 'y' => $captchaPort->getY()];

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mover->steps = 25;
        $mouse = $selenium->driver->getMouse();

        $mover->moveToElement($captchaElem);
        $mouse->click();
        $coords = $this->parseCaptchaCoordinates($captchaElem, $selenium, 1, true);

        if (!$coords || count($coords) === 1) {
            return false;
        }

        $selenium->saveResponse();

        foreach ($coords as $target) {
            $x = intval($captchaCoords['x'] + $target['x']);
            $y = intval($captchaCoords['y'] + $target['y']);
            $mover->moveToCoordinates(['x' => $x, 'y' => $y], ['x' => 0, 'y' => 0]);
            $mouse->click();
        }
        $selenium->saveResponse();

        $success = $selenium->waitForElement(WebDriverBy::cssSelector('#dx_captcha_basic_bar-success_1[style]'), 5);
        $selenium->saveResponse();

        return $success;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $file = $this->http->DownloadFile("http://ffp.airchina.com.cn/appen/generateImageMath", "jpeg");
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["calc" => 1]);

        if (strstr($captcha, '+')) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(4, 1, self::CAPTCHA_ERROR_MSG);
        }

        unlink($file);

        return $captcha;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            // 503 Service Unavailable
            $this->http->FindSingleNode("//h1[contains(text(), '503 Service Unavailable')]")
            // 504 Gateway Time-out
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindSingleNode("//pre[contains(text(), 'Proxy Error')]")
            || $this->http->FindPreg("/<head><\/head><body><h1>504 Gateway Time-out<\/h1>/")
            // Generic Error
            || $this->http->FindSingleNode("//h1[contains(text(), 'Generic Error')]")
            || $this->http->FindSingleNode("//div[contains(text(), '504')]/following-sibling::div[contains(text(), '站不可用')]")
            || $this->http->FindSingleNode("//h3[contains(text(), '哎呀！网络出错了，请稍后再试试！')]")
            || $this->http->FindPreg("/The following error was encountered while trying to retrieve the URL: <a href=\"https?:\/\/ffp\.airchina\.com\.cn\/appen\/login\/member\">/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Webpage not available')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function LoadLoginFormUS()
    {
        $this->logger->info("LoadLoginForm (US Version)", ['Header' => 2]);
        $this->http->removeCookies();
        $this->http->TimeLimit = 500;

//        $this->setProxyBrightData();
//        $this->http->setRandomUserAgent();
//        $this->http->setDefaultHeader("User-Agent", $this->http->userAgent);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.airchina.us/US/GB/Home"); // us version
        $this->http->RetryCount = 2;
        $this->distil();
        $this->distil();
        // retries - Fun Captcha
        if ($this->http->Response['code'] == 405 && $this->http->FindSingleNode("//form[@id='distilCaptchaForm']/@action")) {
            throw new CheckRetryNeededException(2);
        }
        // retries
        if ($this->http->Response['code'] == 0 || $this->http->FindPreg($this->badRegExp) || $this->http->FindSingleNode("//h1[contains(text(), 'Access To Website Blocked')]")) {
            $this->DebugInfo = 'Access To Website Blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(2);
        }

//        $this->http->GetURL("https://m.airchina.com.cn/ac/c/invoke/login@pg");// mobile version
        if (!$this->http->ParseForm(null, 1, true, "//form[@action = '/CAPortal/dyn/portal/ffp/']") || $this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.airchina.us/CAPortal/dyn/portal/ffp/login';
        // credentials
        $login = $this->AccountFields['Login'];

        if (strpos($login, 'CA') !== false || strpos($login, 'CA') > 0) {
            $login = str_replace('CA', '', $login);
        }
        $this->http->SetInputValue('loginId', $login);
        $this->http->SetInputValue('loginPassword', $this->AccountFields['Pass']);
        // captcha
        $captchaTime = time() . date('B');
        $captcha = $this->parseCaptchaUS($captchaTime);

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('captchaTime', $captchaTime);
        $this->http->SetInputValue('captcha', $captcha);

        return true;
    }

//    protected function distil() {
//        $this->logger->notice(__METHOD__);
//        $referer = $this->http->currentUrl();
//        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");
//        if (isset($distilLink)) {
//            sleep(2);
//            $this->http->NormalizeURL($distilLink);
//            $this->http->RetryCount = 0;
//            $this->http->FilterHTML = false;
//            $this->http->GetURL($distilLink);
//            $this->http->RetryCount = 2;
//        }// if (isset($distil))
//        if (!$this->http->ParseForm('distilCaptchaForm'))
//            return false;
//        $captcha = $this->parseReCaptcha();
//        if ($captcha === false)
//            return false;
//        $this->http->SetInputValue('g-recaptcha-response', $captcha);
//        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
//        $this->http->RetryCount = 0;
//        $this->http->PostForm(["Referer" => $referer]);
//        $this->http->RetryCount = 2;
//        $this->http->FilterHTML = true;
//
//        return true;
//    }
//
//    protected function parseReCaptcha() {
//        $this->logger->notice(__METHOD__);
//        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");
//        $this->logger->debug("data-sitekey: {$key}");
//        if (!$key)
//            return false;
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
//        $this->recognizer->RecognizeTimeout = 120;
//        $parameters = [
//            "pageurl" => $this->http->currentUrl(),
//            "proxy" => $this->http->GetProxy(),
//        ];
//        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
//
//        return $captcha;
//    }

    private function distil($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('fc-token', $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        $this->getTime($startTimer);

        return true;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug('[Parse date: ' . date('Y/m/d H:i:s') . ']');
        $startTimer = $this->getTime();

        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindPreg('/funcaptcha.com.+?pkey=([\w\-]+)/');
        }

        if (!$key) {
            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        // $postData = array_merge(
        //     [
        //         "type"             => "FunCaptchaTask",
        //         "websiteURL"       => $this->http->currentUrl(),
        //         "websitePublicKey" => $key,
        //     ],
        //     $this->getCaptchaProxy()
        // );
        // $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        // $recognizer->RecognizeTimeout = 120;
        // $captcha = $this->recognizeAntiCaptcha($recognizer, $postData, $retry);

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        $this->getTime($startTimer);

        return $captcha;
    }

    private function parseCaptchaUS($captchaTime)
    {
        $this->logger->notice(__METHOD__);
        $file = $this->http->DownloadFile("https://www.airchina.us/CAPortal/dyn/portal/ffp/getCaptchaImage?captchaTime={$captchaTime}&SITE=B000CA00&LANGUAGE=GB&COUNTRY_SITE=US", "jpg");
        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function checkErrorsUS()
    {
        return false;
    }

    private function LoginUS()
    {
        $this->logger->info("Login (US Version)", ['Header' => 2]);
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            // Network error 56 - Received HTTP code 502 from proxy after CONNECT
            if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT')) {
                throw new CheckRetryNeededException(4);
            }

            return $this->checkErrors();
        }// if (!$this->http->PostForm())
        $this->http->RetryCount = 2;
        $this->validatingJavaScriptEngine();
        $this->http->JsonLog();
        // retries
        if ($this->http->FindPreg("/The captcha message is wrong/")) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }
        // Access is allowed
        if ($this->http->FindPreg("/\"LOGIN_SUCCESS\":true/")) {
            return true;
        }
        // Invalid login or password
        if ($message = $this->http->FindPreg("/Invalid user or password\(/ims")) {
            throw new CheckException("Invalid user or password", ACCOUNT_INVALID_PASSWORD);
        }
        // refs #15498 -> https://redmine.awardwallet.com/issues/15498#note-13
        // Login system error (UI_700) / Login system error(ui_700)
        if ($message = $this->http->FindPreg("/Login system error\s*\(UI_700\)/ims")) {
            $this->DebugInfo = 'not valid or needed china site';

            throw new CheckException("To update this Air China (PhoenixMiles) account you need to enter PhoenixMiles No as a login (‘Card no’ field). To do so, please click the “Edit” button next to this account. Until you do so, we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        return $this->checkErrors();
    }

    private function validatingJavaScriptEngine()
    {
        $this->logger->notice(__METHOD__);
        // Validating JavaScript Engine
        if ($this->http->FindPreg("/If the page doesn't automatically attempt to reload,/")) {
            $this->logger->notice("Validating JavaScript Engine");
            sleep(2);
            $this->http->GetURL("https://www.airchina.us/US/GB/booking/account/");
            // retries
            if ($this->http->FindPreg($this->badRegExp)) {
                throw new CheckRetryNeededException(4, 10);
            }
        }// if ($this->http->FindPreg("/If the page doesn't automatically attempt to reload,/"))
    }

    private function ParseUS()
    {
        $this->logger->info("Parse (US Version)", ['Header' => 2]);
        sleep(rand(0, 3));
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.airchina.us/US/GB/booking/account/", []);
        $this->http->RetryCount = 2;
        // retries
        if ($this->http->Response['code'] == 0 || $this->http->FindSingleNode("//h1[contains(text(), 'Access To Website Blocked')]")) {
            $this->DebugInfo = 'Access To Website Blocked';
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            throw new CheckRetryNeededException(4);
        }

        if (strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false) {
            throw new CheckRetryNeededException(4);
        }

        $this->validatingJavaScriptEngine();

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[@class = 'capitalize']", null, true, "/Hello\s*([^\,]+)/ims")));
        // Member Account Level
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[span[contains(text(), 'Member Account Level')]]/following-sibling::span/span[contains(@class, 'value')]"));
        // Member Account Number
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//span[span[contains(text(), 'Member Account Number')]]/following-sibling::span/span[contains(@class, 'value')]"));
        // Balance - Useable mileage
        $this->SetBalance($this->http->FindSingleNode("//span[span[contains(text(), 'Useable mileage')]]/following-sibling::span/span"));

        // Member Level
        if (empty($this->Properties['Status'])) {
            // fixed provider bug
            $this->http->GetURL("https://www.airchina.us/CAPortal/dyn/portal/DisplayPage?COUNTRY_SITE=US&SITE=B000CA00&LANGUAGE=GB&PAGE=ACUI");
            $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(text(), 'Member Level:')]/following-sibling::span[1]"));
        }// if (empty($this->Properties['Status']))
    }
}
