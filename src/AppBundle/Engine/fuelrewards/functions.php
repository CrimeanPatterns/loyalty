<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFuelrewards extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptchaVultr());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.fuelrewards.com/fuelrewards/loggedIn.html", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.fuelrewards.com/fuelrewards/login-signup?utm_source=HP&utm_medium=um&utm_campaign=login');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm("loginform")) {
            if (
                $this->http->Error == 'Network error 0 - '
                || $this->http->Error == 'Network error 52 - Empty reply from server'
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        $this->http->SetInputValue('userId', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('rememberMe', "true");
        $this->http->SetInputValue('_rememberMe', "on");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Our site is currently down for scheduled maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is currently down for scheduled maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.fuelrewards.com/");

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 're currently in the middle of something exciting')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 're currently conducting maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Our site is currently down for maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our site is currently down for maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // The server is temporarily unable to service your request due to maintenance downtime or capacity problems.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The server is temporarily unable to service')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        $this->CheckError($this->http->FindPreg('/class=\\\\"warn error\\\\"\>([^<]*)/ims'), ACCOUNT_INVALID_PASSWORD);
        // User name or password not recognized
        $this->CheckError($this->http->FindPreg("/\(\'\#serverErrors\'\)\.text\(\"(User name or password not recognized)/ims"), ACCOUNT_INVALID_PASSWORD);
        // Your login has been locked due to too many failed login attempts
        $this->CheckError($this->http->FindPreg("/\(\'\#serverErrors\'\)\.text\(\"(Your login has been locked due to too many failed login attempts[^\"])/ims"), ACCOUNT_LOCKOUT);
        // Data retrieval error, unable to process request
        $this->CheckError($this->http->FindPreg("/\(\'\#serverErrors\'\)\.text\(\"(Data retrieval error, unable to process request[^\"])/ims"), ACCOUNT_PROVIDER_ERROR);
        $this->CheckError($this->http->FindPreg('/\$\(\'\#serverErrors\'\).text\("((?:General error, unable to process request|Data retrieval error, unable to process request))"\)/ims'), ACCOUNT_PROVIDER_ERROR);
        //# Password update
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Password Update')]")) {
            throw new CheckException("FreeBirds (Fanatic Rewards) website is asking you to update your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        // Please verify that you are not a robot.
        if ($this->http->FindPreg('/\$\(\'\#serverErrors\'\).text\("(Please verify that you are not a robot\.)"\)/ims')) {
            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }

        return $this->checkErrors();
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['SuccessURL'] = 'https://www.fuelrewards.com/fuelrewards/dashboard.html';
        $arg['CookieURL'] = 'https://www.fuelrewards.com/fuelrewards.html';

        return $arg;
    }

    public function Parse()
    {
        // debug
        if (!$this->http->FindSingleNode("//input[@id = 'totalRewardBal']/@value")) {
            $this->http->GetURL("https://www.fuelrewards.com/fuelrewards/loggedIn.html");
        }

        // set Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//div[@class="user-name"]'));
        // set Account Number
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//div[@class="user-account"]', null, true, '/Account#\s+(.*)/ims'));
        // set Member Since
        $this->SetProperty('MemberSince', $this->http->FindSingleNode('//div[@class="user-date"]', null, true, '/Member since\s+(.*)/ims'));
        // set Total Amount Saved on Fuel
        $this->SetProperty('TotalAmountSaved', $this->http->FindSingleNode('//div[contains(@class, "balance_ts")]/h2', null, false));
        // Status
        $trier = $this->http->FindPreg("/tier\s*=\s*(?:\'|\")([^\'\"]+)/");
        $this->logger->debug("[Status]: {$trier}");

        if (!in_array($trier, [
            'SEGREACTIVATIONDEC19',
            'NCALDEBRAND919',
        ])) {
            $this->SetProperty("Status", beautifulName($trier));
        }
        // set Balance
        $this->SetBalance($this->http->FindSingleNode("//input[@id = 'totalRewardBal']/@value"));
        // Expiration Date
        $exp = $this->http->FindSingleNode("//span[contains(text(), 'Rewards Expiring')]/following-sibling::span[1]", null, true, "/on\s*([\d\/]+)/ims");

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
        // Rewards to expire
        $this->SetProperty('RewardsToExpire', $this->http->FindSingleNode("//span[contains(text(), 'Rewards Expiring')]/following-sibling::span[2]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // Account ID: 4363450, SetBalance(0);
            if (
                !empty($this->Properties['Name'])
                && !empty($this->Properties['AccountNumber'])
                && !empty($this->Properties['MemberSince'])
                && !isset($this->Properties['TotalAmountSaved'])
                && empty($this->Properties['AccountExpirationDate'])
                && empty($this->Properties['RewardsToExpire'])
                && $this->http->FindPreg('/<input type="hidden" name="totalRewardBal" id="totalRewardBal" value=""\/>/')
            ) {
                $this->SetBalance(0);
            }
        }
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'loginform']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//a[@class="toplink" and contains(text(), "logout")]')) {
            return true;
        }

        return false;
    }
}
