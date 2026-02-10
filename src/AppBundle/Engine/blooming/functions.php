<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBlooming extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    private $uid;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
//        $this->useChromium();

        $this->keepCookies(false); // 94 chrome workaround: exception caught - WebDriverException: JSON decoding of remote response failed. Error code: 4 The response: '404: POST /wd/hub/session/TSZjH0NCwqwDPYce7VRtu/cookie' trace: #0 /www/loyalty/current/vendor/facebook/webdriver-old/lib/remote/RemoteWebDriver.php(491): HttpCommandExecutor->execute(Object(WebDriverCommand)) #1 /www/loyalty/current/vendor/facebook/webdriver-old/lib/remote/RemoteExecuteMethod.php(36): RemoteWebDriver->execute('addCookie', Array)

        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumRequest->setOs("mac");
        $this->http->setUserAgent(null);
        */
//        $this->useCache();
//        $this->disableImages();
        $this->usePacFile(false);
        $this->http->FilterHTML = false;
        $this->http->saveScreenshots = true;

//        if ($this->attempt > 0) {
        $this->setProxyBrightData();

//            return;
//        }
//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->GetURL("https://www.bloomingdales.com/loyallist/accountsummary?cm_sp=my_account-_-loyallist-_-view_loyallist_account");
        } catch (WebDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
        $this->waitForElement(WebDriverBy::xpath("//p[@class='loyalty-number-value']"), 3);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//p[@class=\'loyalty-number-value\']')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (!strstr($this->AccountFields["Login"], '@')) {
            throw new CheckException("Your Email Address must be entered in this format: jane@company.com. Please try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();

        try {
            if ($this->attempt != 0) {
                $this->http->GetURL('https://www.bloomingdales.com');
                $this->http->GetURL('https://www.bloomingdales.com/account/signin');
            }
        } catch (WebDriverCurlException | TimeOutException | UnexpectedAlertOpenException | NoSuchWindowException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            if ((ConfigValue(CONFIG_SITE_STATE) !== SITE_STATE_DEBUG)) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        try {
            $res = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Denied')] | //input[@id = 'email']"), 10);

            if (!$res && $this->waitForElement(WebDriverBy::xpath("//svg[@id = 'loader']"), 0)) {
                $this->saveResponse();
                $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Denied')] | //input[@id = 'email']"), 10);
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
            sleep(3);
            $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Denied')] | //input[@id = 'email']"), 7);
        }

        // Access Denied
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }

        sleep(rand(1, 3));

        $login = $this->waitForElement(WebDriverBy::id('email'), 5);
//        $pass = $this->waitForElement(WebDriverBy::id('password'), 0);
//        $button = $this->waitForElement(WebDriverBy::id('accountSignInBtn'), 0);
        $pass = $this->waitForElement(WebDriverBy::id('pw-input'), 4);
        $button = $this->waitForElement(WebDriverBy::id('sign-in'), 4);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            if ($this->http->FindSingleNode('//span[contains(text(), "We\'re sorry, your session has timed out. Please sign in to your account again.")]')) {
                throw new CheckRetryNeededException(2, 0);
            }

            if ($this->http->FindSingleNode('//a[contains(text(), "Sign Out")]')) {
                return true;
            }

            return $this->checkErrors();
        }

        $login->click();
        $login->sendKeys($this->AccountFields['Login']);
        $pass->click();
        $password = substr($this->AccountFields['Pass'], 0, 16);
        $pass->sendKeys($password);

//        // angularjs 10
//        $this->driver->executeScript(
//            "function triggerInput(enteredName, enteredValue) {\n" .
//            "      const input = document.getElementById(enteredName);\n" .
//            "      var createEvent = function(name) {\n" .
//            "            var event = document.createEvent('Event');\n" .
//            "            event.initEvent(name, true, true);\n" .
//            "            return event;\n" .
//            "      }\n" .
//            "      input.dispatchEvent(createEvent('focus'));\n" .
//            "      input.value = enteredValue;\n" .
//            "      input.dispatchEvent(createEvent('change'));\n" .
//            "      input.dispatchEvent(createEvent('input'));\n" .
//            "      input.dispatchEvent(createEvent('blur'));\n" .
//            "}\n" .
//            "triggerInput('email', '{$this->AccountFields['Login']}');\n" .
//            "triggerInput('pw-input', '{$password}');"
//        );

        $this->saveResponse();
        $button->click();

        return true;
    }

    /*function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie("cmTPSet", "Y", "www.bloomingdales.com");
        $this->http->setCookie("shippingCountry", "US", ".bloomingdales.com");

        $this->http->GetURL('https://www.bloomingdales.com/account/signin');

        $authwebUrl = $this->http->FindPreg("/authwebUrl\s*:\s*'([^\']+)/");
        $authwebKey = $this->http->FindPreg("/authwebKey\s*:\s*'([^\']+)/");
        if (!$authwebUrl || !$authwebKey) {
            return $this->checkErrors();
        }

        $sensorPostUrl = 'https://www.bloomingdales.com/assets/5133962625151e494dbcd5689d19';
        $sensorData = "7a74G7m23Vrp0o5c9177581.6-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391992,7083807,1536,880,1536,960,1536,367,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8961,0.05420461627,796578541903.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.bloomingdales.com/account/signin-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1593157083807,-999999,17043,0,0,2840,0,0,5,0,0,FD6996CFB81875A739F5664ED4054946~-1~YAAQWJTcF6PIzu1yAQAAj9yP7wTjpETrZrAsstyH6Bu+oxgVcu4DB8H1B9FRH7GeAihthTEpI46mC1a3fIzn5jaCGX7rmZZRF1gNv+yNxRBgEYURelMYJRJEuf8bAi8K+cWAtDKRGBjhxapUJev7QmPNPeLxBjTkUOa44gwcj5gBdJkkYmyqap6zDArGe9fBx85yND0Fqz+1v3uv3xURCl6ozB7YgwXcuRP4MooKc8Yk872vkNCU/i96qihzw0mSybEHGbe+U9zGv3Amzcp3KAu3U7diawNKlZ/HpLbPgCgOpGMSQwp1nTKE2DtwYg6uVA==~-1~-1~-1,30692,-1,-1,30261693,PiZtE,66806,57-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1721363049-1,2,-94,-118,78619-1,2,-94,-121,;7;-1;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $sensorData = "7a74G7m23Vrp0o5c9177581.6-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.106 Safari/537.36,uaend,12147,20030107,en-US,Gecko,3,0,0,0,391992,7083807,1536,880,1536,960,1536,367,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8961,0.668487580334,796578541903.5,loc:-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,-1,2,-94,-102,-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.bloomingdales.com/account/signin-1,2,-94,-115,1,32,32,0,0,0,0,538,0,1593157083807,28,17043,0,0,2840,0,0,539,0,0,FD6996CFB81875A739F5664ED4054946~-1~YAAQWJTcF6PIzu1yAQAAj9yP7wTjpETrZrAsstyH6Bu+oxgVcu4DB8H1B9FRH7GeAihthTEpI46mC1a3fIzn5jaCGX7rmZZRF1gNv+yNxRBgEYURelMYJRJEuf8bAi8K+cWAtDKRGBjhxapUJev7QmPNPeLxBjTkUOa44gwcj5gBdJkkYmyqap6zDArGe9fBx85yND0Fqz+1v3uv3xURCl6ozB7YgwXcuRP4MooKc8Yk872vkNCU/i96qihzw0mSybEHGbe+U9zGv3Amzcp3KAu3U7diawNKlZ/HpLbPgCgOpGMSQwp1nTKE2DtwYg6uVA==~-1~-1~-1,30692,101,899809789,30261693,PiZtE,65560,63-1,2,-94,-106,9,1-1,2,-94,-119,40,43,43,44,69,59,37,9,7,6,6,1333,1236,335,-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-36060876;-1849314799;dis;,7,8;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5578-1,2,-94,-116,1721363049-1,2,-94,-118,82087-1,2,-94,-121,;3;9;0";
        $this->http->PostURL($sensorPostUrl, json_encode(['sensor_data' => $sensorData]));
        $this->http->JsonLog();
        sleep(1);

        $data = [
            "grant_type"        => "password",
            "username"          => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
            "registrySignIn"    => false,
            "request_url"       => "https://www.bloomingdales.com/account/signin",
            "deviceFingerPrint" => "",
            "authWebKey"        => urldecode($authwebKey),
        ];
        $headers = [
            "Accept"        => "application/json",
            "Content-Type"  => "application/x-www-form-urlencoded",
            "Authorization" => "Basic ".urldecode($authwebKey),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL($authwebUrl, $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }*/

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Thank you for visiting bloomingdales.com. We are in the process of upgrading our site')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // look for maintenance image http://netstorage.bloomingdales.com/netstorage/img/misc/pleaseWait.gif
        if ($this->http->FindSingleNode('//img[contains(@src, "pleaseWait.gif")]/@src')
            || $this->http->FindSingleNode('//img[contains(@src, "error_siteUnavailable_v2.jpg")]/@src')) {
            throw new CheckException('Site maintenance', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // Loyallist information is temporarily unavailable
        if ($this->http->FindSingleNode('//img[contains(@src, "loyalty/accountassociation/header-unavailable.png")]/@src')) {
            throw new CheckException('Loyallist information is temporarily unavailable', ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'http://www.bloomingdales.com/signin/index.ognc';
        $arg['SuccessURL'] = 'https://www.bloomingdales.com/loyallist/accountsummary';

        return $arg;
    }

    public function Login()
    {
        sleep(5);

        if ($this->waitForElement(WebDriverBy::id("sec-cpt-if"), 0)) {
            $this->waitFor(function () {
                return !$this->waitForElement(WebDriverBy::id("sec-cpt-if"), 0);
            }, 30);

            /*// broken script workaround
            if ($this->waitForElement(\WebDriverBy::id("sec-cpt-if"), 0)) {
                throw new CheckRetryNeededException();
            }*/

            $this->saveResponse();
        }
        $this->waitForElement(WebDriverBy::xpath("
            //div[@id = 'ul-login-error']/div[@class = 'alert error']
            | //div[contains(text(), \"Your Loyallist account has been deactivated\")]
            | //div[contains(text(), \"Your email address must be entered in this format: jane@company.com\")]
            | //p[contains(., \"Your password must be between 5-16 characters, and cannot include . , - | \ / = _ or spaces. Please try again.\")]
            | //div[contains(text(), \"We value your security and privacy. To ensure your profile is secure, it's time to reset your password.\")]
            | //div[contains(text(), \"That email address/password combination is not in our records\")]
            | //h1[contains(text(), \"Complete Your Profile\")]
        "), 10);
        $this->saveResponse();

        $uid = $this->checkLoginErrors();

        if ($button = $this->waitForElement(WebDriverBy::id('sign-in'), 0)) {
            $button->click();

            $this->waitForElement(WebDriverBy::xpath("//div[@id = 'ul-login-error']/div[@class = 'alert error']"), 10);
            $this->saveResponse();
            $uid = $this->checkLoginErrors();
        }

        // success
        if (isset($uid['value'])) {
            $this->uid = $uid['value'];
            $this->http->GetURL("https://www.bloomingdales.com/account-xapi/api/myaccount/user/{$this->uid}?cardType=Wallet%2COrderHistory%2CWishList%2CLoyalty%2CRegistry");
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)] | //div[@id = "json"]'));

            // We're sorry, but we cannot display your information at this time.
            if (isset($response->cards->loyalty->error->message) && $response->cards->loyalty->error->message == "We're sorry, but we cannot display your information at this time. ") {
                throw new CheckException($response->cards->loyalty->error->message, ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($response->cards, $response->cards->loyalty)) {
                return true;
            }
        }// if (isset($uid['value']))

        return $this->checkErrors();
    }

    public function Parse()
    {
        // TODO: Loyalist information is temporarily unavailable
        $unavailable = $this->http->FindSingleNode("//div[@id = 'userGeneralMsgText' and contains(text(), 'Loyalist information is temporarily unavailable')]");
        // not a member
        $notMember = $this->http->FindPreg('/"loyalty":\{\}/');
        // TODO: no Loyallist Account in profile
        $noAccount = $this->http->FindSingleNode("//a[@id = 'myAcct_loyallistAddNowLink' and contains(text(), 'Add my Loyallist Account')]");

        try {
            $this->http->GetURL('https://www.bloomingdales.com/loyallist/accountsummary?cm_sp=my_account-_-loyallist-_-view_loyallist_account');
        } catch (WebDriverCurlException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 1);
        }

        // Name
        if ($name = $this->waitForElement(WebDriverBy::xpath("//li[@class='loyallist-name']"), 10)) {
            $this->SetProperty('Name', beautifulName($name->getText()));
        }
        $this->saveResponse();
        // Loyallist Number
        if ($number = $this->waitForElement(WebDriverBy::xpath("//p[@class='loyalty-number-value']"), 0)) {
            $this->SetProperty('LoyallistNumber', beautifulName($number->getText()));
        }

        // Available points
        // TODO: wait balance
        sleep(3);

        if ($balance = $this->waitForElement(WebDriverBy::xpath("//span[@class='lty-as-balance']"), 0)) {
            $this->SetBalance($balance->getText());
            $balance = str_replace(',', '', $this->Balance);

            if ($balance >= 4) {
                // set Expiration Date
                $this->SetExpirationDate(strtotime('01/01/' . date('Y', strtotime('+1 year'))));
                // set Points to Expire
                $this->SetProperty('PointsToExpire', ceil($balance * 0.85));
            }// if ($this->Balance >= 4)
        }

        // points until your next reward card
        $this->SetProperty('PointsUntilNextRewardCard', $this->http->FindSingleNode('//div[contains(@class, "lty-next-award-text")]/following-sibling::div[contains(@class, "lty-reward-number")]'));
        // reward card balance
        $this->SetProperty('RewardCardBalance', $this->http->FindSingleNode('//div[contains(@class, "lty-reward-balance")]/following-sibling::div[contains(@class, "lty-reward-number")]'));
        // Pending Points
        // Base Points
        $this->SetProperty('BasePoints', $this->http->FindSingleNode('//div[contains(text(), "Base Points")]/following-sibling::div/span'));
        // Bonus Points
        $this->SetProperty('BonusPoints', $this->http->FindSingleNode('//div[contains(text(), "Bonus Points")]/following-sibling::div/span'));
        // Power Points
        $this->SetProperty('PowerPoints', $this->http->FindSingleNode('//div[contains(text(), "Power Points")]/following-sibling::div/span'));
        // Total Pending Points
        $this->SetProperty('TotalPendingPoints', $this->http->FindSingleNode('//div[contains(text(), "Total Pending Points")]/following-sibling::div/span'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // not a member
            if ($notMember) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // no Loyallist Account in profile
            if ($noAccount) {
                throw new CheckException("Your Loyallist Account hasn't been added yet.", ACCOUNT_PROVIDER_ERROR);
            }/*review*/
            // Loyalist information is temporarily unavailable
            if ($unavailable) {
                $this->ErrorCode = ACCOUNT_WARNING;
                $this->ErrorMessage = $unavailable;
            }// if ($unavailable)

            if ($message = $this->http->FindSingleNode('//div[contains(text(), "Internal server error")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }
    }

    private function checkLoginErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($error = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'ul-login-error']/div[@class = 'alert error']"), 0)) {
            $message = Html::cleanXMLValue($error->getText());
            $this->logger->error("[Error]: " . $message);

            if (strstr($message, 'That email address/password combination is not in our records.')) {
                throw new CheckException("That email address/password combination is not in our records.", ACCOUNT_INVALID_PASSWORD);
            }
            // We value your security and privacy. To ensure your profile is secure, it's time to reset your password.
            if (strstr($message, 'We value your security and privacy. To ensure your profile is secure, it\'s time to reset your password.')) {
                throw new CheckException("We value your security and privacy. To ensure your profile is secure, it's time to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'User is Not Found')) {
                throw new CheckException("User is Not Found", ACCOUNT_INVALID_PASSWORD);
            }
            // We're sorry, but we cannot display your information at this time.
            if (strstr($message, 'We\'re sorry, but we cannot display your information at this time.')) {
                throw new CheckException("We're sorry, but we cannot display your information at this time.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        // Your Loyallist account has been deactivated
        // Your email address must be entered in this format: jane@company.com
        // Your password must be between 5-16 characters, and cannot include . , - | \ / = _ or spaces. Please try again.
        // That email address/password combination is not in our records
        // We value your security and privacy. To ensure your profile is secure, it's time to reset your password.
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(text(), "Your Loyallist account has been deactivated")]
                | //div[contains(text(), "Your email address must be entered in this format: jane@company.com")]
                | //p[contains(., "Your password must be between 5-16 characters, and cannot include . , - | \ / = _ or spaces. Please try again.")]
                | //div[contains(text(), "We value your security and privacy. To ensure your profile is secure, it\'s time to reset your password.")]
                | //div[contains(text(), "That email address/password combination is not in our records")]
            '), 0)
        ) {
            if (strstr($message->getText(), 'That email address/password combination is not in our records')) {
                throw new CheckException("That email address/password combination is not in our records.", ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Complete Your Profile")]'), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        $currentURL = $this->http->currentUrl();
        $this->logger->debug("[Current URL]: {$currentURL}");
        $this->saveResponse();

        if (stripos($currentURL, 'https://auth.bloomingdales.com/forgot-password?redirectURL=') !== false) {
            throw new CheckException("Bloomingdale's (Loyallist) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        $uid = $this->driver->manage()->getCookieNamed('bloomingdales_online_uid');
        $error = $this->http->FindSingleNode("//div[@id = 'ul-login-msg']/div[@class = 'alert error'] | //div[@id = 'ul-msg-section']//div[@class = 'fixed-container notification-error']");
        $this->logger->debug("uid");
        $this->logger->debug(var_export($uid, true), ['pre' => true]);
        $this->logger->error("error -> {$error}");

        if (!empty($error)) {
            // That email address & password combination isn’t in our records
            if (
                strstr($error, 'That email address & password combination isn’t in our records')
                || strstr($error, 'Your security and privacy is important to us. To keep your account secure, please reset your password.')
                || strstr($error, 'Your security and privacy is important to us. To keep your account secure, reset your password.')
                || strstr($error, 'Your email address or password is incorrect. Forgot your password?')
            ) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // Hmm...looks like we're having some technical issues.
            if (strstr($error, 'Hmm...looks like we\'re having some technical issues.')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 1, $error, ACCOUNT_PROVIDER_ERROR);

                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }
            // We value your security and privacy. To ensure your profile is secure, it's time to reset your password.
            if (strstr($error, 'We value your security and privacy. To ensure your profile is secure, it\'s time to reset your password.')) {
                throw new CheckException("We value your security and privacy. To ensure your profile is secure, it's time to reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Sorry, it looks like there\'s a problem on our end. For assistance, please call ')) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $error;

            return false;
        }

        return $uid;
    }

    /*
       function Parse() {
           if (!isset($this->uid))
               return;
           // TODO: Loyalist information is temporarily unavailable
           $unavailable = $this->http->FindSingleNode("//div[@id = 'userGeneralMsgText' and contains(text(), 'Loyalist information is temporarily unavailable')]");
           // not a member
           $notMember = $this->http->FindPreg('/"loyalty":\{\}/');
           // TODO: no Loyallist Account in profile
           $noAccount = $this->http->FindSingleNode("//a[@id = 'myAcct_loyallistAddNowLink' and contains(text(), 'Add my Loyallist Account')]");

           $browser = new HttpBrowser("none", new CurlDriver());
           $this->http->brotherBrowser($browser);
           $browser->LogHeaders = true;
           $cookies = $this->driver->manage()->getCookies();
           foreach ($cookies as $cookie)
               $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);

           $headers = [
               'Accept' => 'application/json, text/javascript, **; q=0.01',
               'x-macys-signedin' => 1,
               'x-macys-uid' => $this->uid,
               'Referer' => 'https://www.bloomingdales.com/loyallist/accountsummary?cm_sp=my_account-_-loyallist-_-view_loyallist_account',
               'x-macys-userguid' => $this->http->getCookieByName('bloomingdales_online_guid'),
               'x-requested-with' => 'XMLHttpRequest',
               'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:59.0) Gecko/20100101 Firefox/59.0'
           ];
           $browser->GetURL('https://www.bloomingdales.com/xapi/loyalty/v1/accountsummary?_=' . time() . date('B'), $headers);
           //$json = $this->http->JsonLog($this->http->FindPreg('#(?:<pre.* >|<div id="json">)(.+?)(?:</pre>|</div>)#'));
           $json = $browser->JsonLog();
           // Balance
           if (isset($json->loyalty->points->totalAvailablePoints))
               $this->SetBalance($json->loyalty->points->totalAvailablePoints);
           # Expiration Date   // refs #5003
           $balance = str_replace(',', '', $this->Balance);
           if ($balance >= 4) {
               // set Expiration Date
               $this->SetExpirationDate(strtotime('12/31/' . date('Y')));
               // set Points to Expire
               $this->SetProperty('PointsToExpire', ceil($balance * 0.75));
           }// if ($this->Balance >= 4)
           // Name
           if (isset($json->loyalty->firstName, $json->loyalty->lastName)) {
               $name = "{$json->loyalty->firstName} {$json->loyalty->lastName}";
               $this->SetProperty('Name', beautifulName($name));
           }
           else
               $this->logger->notice("Name is not found");
           // Loyallist Number
           if (isset($json->loyalty->id))
               $this->SetProperty('LoyallistNumber', $json->loyalty->id);
           else
               $this->logger->notice("Loyallist Number is not found");

           if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
               // not a member
               if ($notMember)
                   throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
               // no Loyallist Account in profile
               if ($noAccount)
                   throw new CheckException("Your Loyallist Account hasn't been added yet.", ACCOUNT_PROVIDER_ERROR);/*review*
       /
               // Loyalist information is temporarily unavailable
               if ($unavailable) {
                   $this->ErrorCode = ACCOUNT_WARNING;
                   $this->ErrorMessage = $unavailable;
               }// if ($unavailable)
           }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
       }
*/
}
