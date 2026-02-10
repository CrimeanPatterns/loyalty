<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAarp extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerAarpSelenium.php";

        return new TAccountCheckerAarpSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->LogHeaders = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://login.aarp.org/online-community/loginform.action");

        if ($url = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"10; url=\/(distil_r_captcha.html([^\"]+))/")) {
            throw new CheckRetryNeededException(2, 1);
//            sleep(5);
//            $this->http->NormalizeURL($url);
//            $this->http->GetURL($url);
        }
//            $this->parseGeetestCaptcha();

        sleep(rand(1, 5));

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha !== false) {
            $this->http->SetInputValue('nucaptcha-answer', $captcha);
        }

        return true;
    }

    public function checkErrors()
    {
        if ($this->http->currentUrl() == "https://www.aarp.org/Maintenance/") {
            throw new CheckException("We are currently performing maintenance on our website. Please check back later. Thank you.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://login.aarp.org/online-community/loginform.action";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm(['Accept-Encoding' => 'gzip, deflate, br'])) {
            if ($this->http->Response['code'] == 504
                && (empty($this->http->Response['body']) || $this->http->FindSingleNode("//h1[contains(text(), 'Gateway Time-out')]"))) {
                throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
            }

            return $this->checkErrors();
        }

        if ($oSessionToken = $this->http->getCookieByName("oSessionToken", ".aarp.org")) {
            sleep(rand(1, 2));
            $this->http->GetURL("https://aarp.okta.com/login/sessionCookieRedirect?checkAccountSetupComplete=true&token={$oSessionToken}&redirectUrl=https%3A%2F%2Flogin.aarp.org%2Fonline-community%2FloginProxy.action%3Fredirect_uri%3Dhttp%3A%2F%2Fwww.aarp.org%3Fintcmp%3DDSO-LOGIN");
            sleep(rand(1, 2));
            $this->http->GetURL("http://www.aarp.org/?intcmp=DSO-LOGIN");
        }

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Please enter a valid email address and password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Please re-enter your email address and/or password')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your registered account information was not recognized.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Enter the email address you used at registration to reset your password.
        if ($this->http->FindSingleNode("//h1[normalize-space(text())='Password Assistance']")) {
            $this->throwProfileUpdateMessageException();
        }
        // captcha
        if ($this->http->FindSingleNode("//div[contains(text(), 'Please re-enter your password along with the Security Challenge.') or contains(text(), 'Sorry, security code did not match. Please try again.')]")) {
            if ($this->http->FindSingleNode("//div[contains(text(), 'Sorry, security code did not match. Please try again.')]") && $this->recognizer) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
            }

            if (!$this->http->ParseForm("loginForm")) {
                return $this->checkErrors();
            }
            $this->http->SetInputValue("email", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            if ($this->http->FindSingleNode("//form[@id = 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey")) {
                $this->http->SetInputValue('gRecpatchaResponse', $captcha);
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            } else {
                $this->http->SetInputValue('nucaptcha-answer', $captcha);
            }
            $this->http->PostForm();

            if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
                return true;
            }
        }// if ($this->http->FindSingleNode("//span[contains(text(), 'Please re-enter your password along with the Security Challenge.')]"))

        return $this->checkErrors();
    }

    public function Parse()
    {
//        $this->http->GetURL("http://www.aarp.org/rewards-for-good/");
        // Balance - Your Points
        $dr = urldecode($this->http->getCookieByName("dr", ".aarp.org"));
        $this->logger->debug("Cookie: $dr");

        if (!empty($dr) && ($balance = $this->http->FindPreg("/bal\=([^\&\=]+)/ims", false, $dr))) {
            $this->SetBalance($balance);
        } elseif ($this->http->FindPreg("/errorCode=999\&errorMessage=We\'re sorry, there was an error processing your request./ims", false, $dr)) {
            $this->SetBalanceNA();
        } elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Whoops! We've run into a small error on the page. Please wait one moment and try again.
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve run into a small error on the page.")]')) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // har code
            if ($this->AccountFields['Login'] == 'augi001@gmail.com') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

//        $this->http->GetURL("https://secure.aarp.org/applications/user/account/myAccount.action?request_locale=en&intcmp=DSO-HDR-MYACCT-EWHERE");
//        $this->http->GetURL("https://secure.aarp.org/applications/user/account/printPrimaryCard.action");
        // Membership Number
        $this->SetProperty("MemberID", $this->http->FindSingleNode("//label[contains(text(), 'Membership Number')]/following-sibling::label[1]"));
        // Member Since
        $at = urldecode($this->http->getCookieByName("at", ".aarp.org"));
        $this->logger->debug("Cookie: $at");
        $this->SetProperty("MemberSince", $this->http->FindPreg("/\&mj=([^\&\=]+)/ims", false, $at));
        // Membership Expires
        $this->SetProperty("MembershipExpires", $this->http->FindPreg("/\&me=([^\&\=]+)/ims", false, $at));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\&f=([^\&\=]+)/ims", false, $at)));

        // Name
//        $this->http->GetURL("https://secure.aarp.org/applications/user/account/editAccount.action?request_locale=en");
//        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//label[contains(@id, 'fullMemberName')]")));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'loginForm']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
//            $captcha = $this->http->FindSingleNode("//input[@id = 'nucaptcha-epd']/@value");
            $captcha = $this->http->FindSingleNode("//img[@id = 'nucaptcha-media']/@src", null, true, "/token=([^\&]+)/");
            $r = $this->http->FindPreg("/\"r\":\"([^\"]+)/");

            if (!$r || !$captcha) {
                return false;
            }
            $file = $this->http->DownloadFile("https://api-us-east-1.nd.nudatasecurity.com/1.0/w/3.74.119619/w-712739/captcha?type=VIDEO&lang=eng&index=1&token={$captcha}&r={$r}&ptype=SCRIPT", "gif");

            if ($this->http->Response['code'] == 500) {
                return false;
            }
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 120;
            $captcha = $this->recognizeCaptcha($this->recognizer, $file);
            unlink($file);

            return $captcha;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    private function parseGeetestCaptcha($retry = false)
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $request = $this->http->JsonLog($captcha, true, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
            $request = $this->http->JsonLog($captcha, true, true);
        }

        if (empty($request)) {
            $this->geetestFailed = true;
            $this->logger->error("geetestFailed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'ticket'            => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }
}
