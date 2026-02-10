<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFandango extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = "https://www.fandango.com/accounts/settings?accounttype=email&login=success";
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'fandangoRewards')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.fandango.com/accounts/settings';
//        $arg['SuccessURL'] = 'https://www.fandango.com/accounts/settings';
        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

//        if ($this->attempt == 0) {
//            $this->http->SetProxy($this->proxyStaticIpDOP());
//        } elseif ($this->attempt > 0) {
//            $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
//        } else {
        $this->http->SetProxy($this->proxyReCaptcha());
//        }
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(text(), 'Sign Out')]") && !strstr($this->http->currentUrl(), 'aspxerrorpath=/myaccount/')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->selenium();

        return true;

        $this->http->GetURL('https://www.fandango.com/accounts/sign-in?source=settings&from=https%3A%2F%2Fwww.fandango.com%2Faccounts%2Fsettings');

        if (!$this->http->ParseForm('Form1')) {
            return $this->checkErrors();
        }

        /*
        $sensorDataUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu', '(/.+?)'\]\);#");
        if (!$sensorDataUrl) {
            return $this->checkErrors();
        }
        */

        $this->http->SetInputValue('ctl00$GlobalBody$SignOnControl$UsernameBox', $this->AccountFields['Login']);
        $this->http->SetInputValue('ctl00$GlobalBody$SignOnControl$PasswordBox', $this->AccountFields['Pass']);
        $this->http->SetInputValue('__EVENTTARGET', 'ctl00$GlobalBody$SignOnControl$SignInButton');

        if ($this->http->InputExists('ctl00$GlobalBody$SignOnControl$CaptchaPlaceHolder')) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('ctl00$GlobalBody$SignOnControl$CaptchaPlaceHolder', $captcha);
        }

        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$GooglePlusCheckbox');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$FacebookCheckbox');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$ExpirationYear');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$ExpirationMonth');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$SecurityCode');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$ZipCode');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$CardNumber');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$LastName');
        $this->http->unsetInputValue('ctl00$ctl00$GlobalBody$Body$FirstName');

        /*
        $formUrl = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;
        $this->http->NormalizeURL($sensorDataUrl);
        $data = [
            "sensor_data" => "7a74G7m23Vrp0o5c9170191.6-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36,uaend,11059,20100101,en-US,Gecko,0,0,0,0,392222,4638398,1536,880,1536,960,1536,460,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:1,sc:0,wrc:1,isc:125,vib:1,bat:0,x11:0,x12:1,8962,0.13220182166,797047319199,loc:-1,2,-94,-101,do_en,dm_en,t_dis-1,2,-94,-105,0,-1,0,0,1129,3974,0;1,-1,0,0,1148,3993,0;-1,2,-94,-102,0,-1,0,0,1129,3974,0;1,-1,0,0,1148,3993,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://www.fandango.com/account/signin?from=https://www.fandango.com/account/settings&state=False-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1594094638398,-999999,17053,0,0,2842,0,0,2,0,0,09AA5A6197CC328EFC4739D929FC92D8~-1~YAAQZDorFwliUxFzAQAAyMxxJwRCvFgYvx9ljJZzGck1MkE5LdgxcH6S2fAmg2j/LmJfPJ51yTBZbY/0XxBzuHFpggTveAWu8MRmCA4hn6RLzZKdwmeAzg73blGWE4DwnxGCC8ae/J0cwug9gVpiOCWj7juo0ed//Sl4hjLbiQfHJ3m6kC3l43ETOcHyWpToBoLMAvdK0lesuWE7jyZaTM/zNi90cAJdoFGXXi38emHyT6uNyb9sY98HhUhu+LLARoO2c/NleuPQ4Od7MET9qZ+XQdxvXXSS+VlpaHkl8q+SqrFuo8sAgrAXJKA=~-1~-1~-1,30156,-1,-1,26067385,PiZtE,84546,93-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,1127130864-1,2,-94,-118,87466-1,2,-94,-121,;4;-1;0",
        ];
        $headers = [
            "Accept"        => "*
        /*",
            "Content-type"  => "application/json",
        ];
        sleep(1);
        $this->http->PostURL($sensorDataUrl, json_encode($data), $headers);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;

        $this->http->FormURL = $formUrl;
        $this->http->Form = $form;
        */

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance in Progress
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Maintenance in Progress')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'An unexpected problem occurred during the processing of your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The service is unavailable.
        if ($this->http->FindPreg("/(The service is unavailable\.)/ims")
            // Something funny is definitely going on.
            || $this->http->FindSingleNode("//h2[contains(text(), 'Something funny is definitely going on.')]")
            // There is an unknown connection issue between Cloudflare and the origin web server. As a result, the web page can not be displayed.
            || $this->http->FindSingleNode("//p[contains(text(), 'There is an unknown connection issue between Cloudflare and the origin web server. As a result, the web page can not be displayed.')]")
            // Connection timed out
            || $this->http->FindSingleNode("//title[contains(text(), 'www.fandango.com | 522: Connection timed out')]")
            // ERROR 500: TECHNICAL DIFFICULTIES
            || $this->http->FindSingleNode("//title[contains(text(), 'Fandango | Unable to process your request')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error')]")
            // An error occurred while processing your request.
            || $this->http->currentUrl() === 'https://www.fandango.com/canvas/503'
            || $this->http->currentUrl() === 'https://www.fandango.com/500Error.aspx?aspxerrorpath=/404Error.aspx'
            || $this->http->currentUrl() === 'https://www.fandango.com/canvas/503?aspxerrorpath=/myaccount/VIPSettings.aspx') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our services are being upgraded. Sorry for the inconvenience.
        if ($message = $this->http->FindPreg("/(Our services are being upgraded\.\s*Sorry for the inconvenience\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($this->http->currentUrl() == 'http://www.fandango.com/maintenance'
            || $this->http->FindSingleNode('//h3[contains(text(), "We’ll get you to your tickets as soon as possible.")]')) {
            throw new CheckException("Our services are being upgraded. Sorry for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable - Zero size object
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable - Zero size object')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        /*
        sleep(2);
        $headers = [
            "Referer" => "https://www.fandango.com/account/signin?from=https://www.fandango.com/account/settings&state=False",
        ];

        if (!$this->http->PostForm($headers)) {
            return $this->checkErrors();
        }
        */

        $this->captchaReporting($this->recognizer);

        // Access is allowed
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[@id = "error-notification-msg" and normalize-space(.) != ""] | //span[contains(@class, "-input-error-msg") and normalize-space(.) != ""]')) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Something went wrong on our end. Please try again later.')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'Your current password is too short'
                || $message == 'The account information you have entered does not match our records. Please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        /*
        // The e-mail address and/or the password entered do not match our records.
        if ($message = $this->http->FindPreg('/(The e-?mail address and\/or the password entered do not match our records\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // That email and password don't match our records. Please try again.
        if ($message = $this->http->FindPreg('/(That email and password don\'t match our records\.\s*Please try again\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your account has been locked out due to too many failed sign in attempts.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Your account has been locked out due to too many failed sign in attempts.")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Please create a stronger password before proceeding.
        if ($this->http->FindSingleNode("//i[contains(text(), 'Please create a stronger password before proceeding.')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'Please create your new password. You will need to log in with your new password when you are done.')]")) {
            throw new CheckException("Fandango (My Fandango) website is asking you to change your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        // Reset your Password
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Reset your Password')]")) {
            throw new CheckException("Fandango (My Fandango) website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }
        */

        if ($this->http->currentUrl() == 'https://www.fandango.com/accounts/signin?from=https://www.fandango.com/accounts/settings&state=False') {
            throw new CheckRetryNeededException(3, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode('//input[@id="FirstName"]/@value');
        $lastName = $this->http->FindSingleNode('//input[@id="LastName"]/@value');

        if (isset($lastName) && $lastName != 'lastname') {
            $name = beautifulName($name . ' ' . $lastName);
        }
        $this->SetProperty('Name', $name);

        $this->http->GetURL('https://www.fandango.com/accounts/dashboard');
        // Member since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//p[contains(text(), "Joined ")]', null, true, '/Joined\s*([^<]+)/ims'));

        if (($this->http->Response['code'] == 500 && $this->http->FindSingleNode("//h1[contains(text(), 'ERROR 500: TECHNICAL DIFFICULTIES')]"))
            || $this->http->currentUrl() == 'https://www.fandango.com/account/signin?from=https://www.fandango.com/account/dashboard&state=False') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

//        $this->http->GetURL('https://www.fandango.com/account/vip-plus-mypoints');
        $this->http->GetURL('https://www.fandango.com/snapi/rewards/user-status?useNewAuthMiddleware=true');
        $response = $this->http->JsonLog();
        // Balance - VIP+ Balance
        $this->SetBalance($response->balance ?? null);
        // Points until your next $5 reward
        $this->SetProperty('PointsUntilNextReward', $response->pointsToNextReward ?? null);
        // Lifetime VIP+ Points
        $this->SetProperty('LifetimePoints', $response->lifetimeEarned ?? null);

        if (!empty($response->availableRewards)) {
            foreach ($response->availableRewards as $reward) {
                $exp = strtotime($reward->expirationDate);

                if (!$exp) {
                    continue;
                }
                $this->AddSubAccount([
                    "Code"           => 'fandangoRewards' . $reward->rewardId,
                    "DisplayName"    => $reward->description,
                    "Balance"        => $reward->promoValue,
                    'ExpirationDate' => $exp,
                ], true);
            }
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && isset($response->error)
            && $response->error == "unable to retrieve user status"
        ) {
            throw new CheckException("Sorry, we’re having trouble loading your FanRewards Points balance.", ACCOUNT_PROVIDER_ERROR);
        }

        // My Movies
        $this->http->GetURL('https://www.fandango.com/accounts/my-movies');
        $this->SetProperty('MyMovies', $this->http->FindSingleNode("//h2[contains(text(), 'My Movies')]/span[1]"));
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");
        $retry = false;
        $result = false;

        try {
            $selenium->UseSelenium();

            if ($this->attempt == 1) {
                $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
            } else {
                $selenium->useFirefox();
                $selenium->setKeepProfile(true);
            }

            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.fandango.com/accounts/sign-in?source=settings&from=https%3A%2F%2Fwww.fandango.com%2Faccounts%2Fsettings");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('email'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::id('sign-in-submit-btn'), 0);

            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

//            $captcha = $this->parseReCaptcha();
//
//            if ($captcha === false) {
//                return false;
//            }
//            $selenium->driver->executeScript("$('input[id*=CaptchaPlaceHolder]').val('{$captcha}')");

            $button->click();
            $this->savePageToLogs($selenium);

            $result = (bool) $selenium->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Out')] | //h3[contains(text(), 'Looks like the page you are looking for cannot be found')] | //p[@id = 'error-notification-msg' and normalize-space(.) != ''] | //span[contains(@class, \"-input-error-msg\") and normalize-space(.) != '']"), 15);

            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            UnknownServerException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (UnexpectedJavascriptException $e) {
            $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 5);
        }

        // block workaround
        if ($this->http->FindSingleNode("//h3[contains(text(), 'Looks like the page you are looking for cannot be found')]")) {
            throw new CheckRetryNeededException(2, 0);
        }

        return $result;
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/#GlobalBody_SignOnControl_CaptchaPlaceHolder', '([^\']+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 30;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => "desktop_signon",
            "min_score" => $this->attempt == 0 ? 0.3 : 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
