<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAirmilesme extends TAccountChecker
{
    use ProxyList;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerAirmilesmeSelenium.php";

        return new TAccountCheckerAirmilesmeSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = false;
        $this->http->TimeLimit = 350;
        //$this->http->SetProxy($this->proxyReCaptcha());
        $proxy = $this->http->getLiveProxy("https://www.airmilesme.com/en-qa/memberlogin");
        $this->http->SetProxy($proxy);

        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.117 Safari/537.36');
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.airmilesme.com/en-qa/memberlogin");

        if ($myAccount = $this->http->FindSingleNode("//a[contains(text(), 'MY ACCOUNT')]/@href")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid email address", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL('https://www.airmilesme.com/');

        if ($this->http->FindPreg('/Request unsuccessful. Incapsula incident ID/')) {
            throw new CheckRetryNeededException(2, 7);
        }

        if (!$this->http->ParseForm("loginform")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = 'https://www.airmilesme.com/?_hn:type=component-rendering&_hn:ref=r8_r3_r2';
        $this->http->SetInputValue("name", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        //# Zend Optimizer not installed
        if ($message = $this->http->FindSingleNode("//h1[contains(text(),'Zend Optimizer not installed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing some technical difficulties.
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'We are experiencing some technical difficulties.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# System Upgrade
        if (preg_match('/article\/general\/system-upgrade\.html/ims', $this->http->currentUrl())) {
            throw new CheckException('System Upgrade. We will be back shortly. Sorry for any inconvenience', ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->Response['code'] == 503) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        $response = $this->http->JsonLog(null, true, true);
        // login successful
        if (ArrayVal($response, 'login_error') == 'success') {
            return true;
        }
        // Incorrect password. Please try again.
        if (ArrayVal($response, 'login_error') == 'Invalid_Grant') {
            throw new CheckException('Incorrect password. Please try again.', ACCOUNT_INVALID_PASSWORD);
        }
        // The email address you have entered is not registered
        if (ArrayVal($response, 'login_error') == 'Unauthorized') {
            throw new CheckException('The email address you have entered is not registered', ACCOUNT_INVALID_PASSWORD);
        }

        // captcha error
        if (ArrayVal($response, 'login_error', null) == null && ArrayVal($response, 'recaptcha_error') == 'recaptchaerror') {
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.airmilesme.com/en-qa/memberlogin");

        if ($myAccount = $this->http->FindSingleNode("//a[contains(text(), 'MY ACCOUNT')]/@href")) {
            $this->http->NormalizeURL($myAccount);
            $this->http->GetURL($myAccount);
        }
        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//h2[contains(text(), 'Welcome')]", null, true, "/Welcome\, (.+)/ims"));
        // Card No
        $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), 'Card No')]/following-sibling::p[1]"));
        // Balance - YOU CAN SPEND
        $this->SetBalance($this->http->FindSingleNode("//p[contains(text(), 'YOU CAN SPEND')]/following-sibling::p[1]/text()[1]"));
        // Air Miles Collected
        $this->SetProperty("Collected", $this->http->FindSingleNode("//span[contains(., 'COLLECTED')]", null, true, self::BALANCE_REGEXP));
        // Air Miles Spent
        $this->SetProperty("Spent", $this->http->FindSingleNode("//span[contains(., 'SPENT')]", null, true, self::BALANCE_REGEXP));
        // Air Miles Adjusted
        $this->SetProperty("Adjusted", $this->http->FindSingleNode("//span[contains(., 'ADJUSTED')]", null, true, self::BALANCE_REGEXP));
        // Miles to Expire
        $this->SetProperty("MilesToExpire", $this->http->FindSingleNode("//p[contains(text(), 'AIR MILES EXPIRING')]/following-sibling::p[1]/text()[1]"));
        // Expiration Date
        $exp = $this->http->FindSingleNode("//p[contains(text(), 'AIR MILES EXPIRING')]/following-sibling::p[2]", null, true, "/on\s*([^<]+)/ims");

        if (isset($this->Properties["MilesToExpire"], $exp)) {
            if ($this->Properties["MilesToExpire"] === '0') {
                $this->ClearExpirationDate();
            } else {
                $this->SetExpirationDate(strtotime($exp));
            }
        }// if (isset($this->Properties["MilesToExpire"], $exp))
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.airmilesme.com/en-ae/auth/login';
        $arg['SuccessURL'] = 'http://www.airmilesme.com/en-ae';

        return $arg;
    }

    protected function parseReCaptcha($referer = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $referer ? $referer : $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }
}
