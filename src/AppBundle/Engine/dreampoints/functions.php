<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDreampoints extends TAccountChecker
{
    use ProxyList;

    protected $logoutUrl = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case "University":
                $arg['RedirectURL'] = "https://www.dreampoints.com/uhfcu/";

                break;

            case "UsBank":
                $arg['RedirectURL'] = "https://www.dreampoints.com/usbank/";

                break;

            case "Westerra":
                $arg['RedirectURL'] = "https://www.dreampoints.com/westerra/";

                break;

            default:// School
                $arg['RedirectURL'] = "https://www.dreampoints.com/schoolsfirstfcu/";

                break;
        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""           => "Please select your website",
            "School"     => "SchoolsFirst FCU",
            "University" => "University of Hawai'i FCU",
            "UsBank"     => "U.S. Bank Business Edge",
            "Westerra"   => "Westerra Rewards",
        ];
        $arFields["Login2"]["Note"] = "Please choose the name of the website where you can see your point balance";
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case "University":
                $loginURL = "https://www.dreampoints.com/uhfcu/";

                break;

            case "UsBank":
                $loginURL = "https://www.dreampoints.com/usbank/";

                break;

            case "Westerra":
                $loginURL = "https://www.dreampoints.com/westerra/";

                break;

            default:// School
                $loginURL = "https://www.dreampoints.com/schoolsfirstfcu/";

                break;
        }// switch ($this->AccountFields['Login2'])
        $this->http->GetURL($loginURL);

        if (!$this->http->ParseForm(null, "//input[@name = 'username']/ancestor::form[1]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        // _csrf_token
        if (!$this->getCsrf()) {
            return false;
        }

        if (!$this->http->PostForm()) {
            return false;
        }
        // The user name and/or password entered does not match our records.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "The user name and/or password entered does not match our records.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // password form with reCaptcha
        if (!$this->http->ParseForm(null, "//input[@name = 'secure_element']/ancestor::form[1]")) {
            return false;
        }
        $this->http->SetInputValue('secure_element', $this->AccountFields['Pass']);
        $this->http->SetInputValue('submit', "Sign in »");

        if (!$this->getCsrf()) {
            return false;
        }
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
        // We are currently performing scheduled system maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently performing scheduled system maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm([
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin' => 'https://www.dreampoints.com',
        ])) {
            return $this->checkErrors();
        }

        if (($this->logoutUrl = $this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]"))
            || ($this->logoutUrl = $this->http->FindSingleNode("(//a[contains(@href, 'sign-off')]/@href)[1]"))) {
            return true;
        }
        // The user name and/or password entered does not match our records.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The user name and/or password entered does not match our records.')]", null, true, '/(.+)\sIf you have not registered/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // We have increased our security to better protect your account and you must create a new password.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We have increased our security to better protect your account and you must create a new password.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Oops!  We are unable to recognize you
        if ($message = $this->http->FindSingleNode("//h2[contains(normalize-space(text()), 'Oops! We are unable to recognize you')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//p[contains(text(),'Please enter your password and complete the captcha field to sign in.')]")) {
            throw new CheckRetryNeededException(3, 7);
        }

        // Something has gone wrong
        if ($message = $this->http->FindSingleNode("//h2[normalize-space(text()) = 'Something has gone wrong']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $accountUrl = $this->http->FindSingleNode("(//a[contains(@href, 'statement.php')]/@href)[1]");
        $this->logger->debug("Account URL: [$accountUrl]");

        if (empty($accountUrl)) {
            return;
        }
        $this->http->NormalizeURL($accountUrl);
        $this->http->GetURL($accountUrl);

        // Name
        $name = $this->http->FindSingleNode("//div[contains(@class, 'chart')]/h4", null, true, '/^(.+)\s*account as/ims');

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//strong[contains(text(), 'account as')] | //span[@id = 'statementDisplayName']", null, true, '/^(.+)\'s\s*account/ims');
        }
        $this->SetProperty("Name", $name);

        // Points at the beginning of this month
        $this->SetProperty("PointsAtTheBeginning", $this->http->FindSingleNode("//dt[contains(text(), 'Points at the beginning of this month')]/following::dd[1]", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsAtTheBeginning'])) {
            $this->SetProperty("PointsAtTheBeginning", $this->http->FindSingleNode("//td[contains(text(), 'Points at the beginning of this month')]/span", null, true, self::BALANCE_REGEXP));
        }
        // Points earned this month
        $this->SetProperty("PointsEarnedThisMonth", $this->http->FindSingleNode("//a[contains(text(), 'Points earned this month')]/following::dd[1]", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsEarnedThisMonth'])) {
            $this->SetProperty("PointsEarnedThisMonth", $this->http->FindSingleNode("//td[contains(text(), 'Points earned this month')]/span", null, true, self::BALANCE_REGEXP));
        }
        // Points redeemed this month
        $this->SetProperty("PointsRedeemedThisMonth", $this->http->FindSingleNode("//dt[contains(text(), 'Points redeemed this month')]/following::dd[1] | //span[@id = 'statementMonthRedeemedRewards']", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsRedeemedThisMonth'])) {
            $this->SetProperty("PointsRedeemedThisMonth", $this->http->FindSingleNode("//span[contains(text(), 'Points redeemed this month')]/following-sibling::span[1]", null, true, self::BALANCE_REGEXP));
        }
        // Points expired this month
        $this->SetProperty("PointsExpiredThisMonth", $this->http->FindSingleNode("//dt[contains(text(), 'Points expired this month')]/following::dd[1] | //span[@id = 'statementMonthExpiredRewards']", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsExpiredThisMonth'])) {
            $this->SetProperty("PointsExpiredThisMonth", $this->http->FindSingleNode("//span[contains(text(), 'Points expired this month')]/following-sibling::span[1]", null, true, self::BALANCE_REGEXP));
        }
        // Points adjusted this month
        $this->SetProperty("PointsAdjustedThisMonth", $this->http->FindSingleNode("//dt[contains(text(), 'Points adjusted this month')]/following::dd[1] | //span[@id = 'statementMonthAdjustedRewards']", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsAdjustedThisMonth'])) {
            $this->SetProperty("PointsAdjustedThisMonth", $this->http->FindSingleNode("//span[contains(text(), 'Points adjusted this month')]/following-sibling::span[1]", null, true, self::BALANCE_REGEXP));
        }

        // Available point balance
        if (!$this->SetBalance($this->http->FindSingleNode("//dt[contains(text(), 'Available point balance')]/following::dd[1] | //span[@id = 'statementMonthAvailableRewards']", null, true, self::BALANCE_REGEXP))) {
            if (!$this->SetBalance($this->http->FindSingleNode("//dt[contains(text(), 'Available points balance')]/following-sibling::dd[1]", null, true, self::BALANCE_REGEXP))) {
                if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Available points balance')]/following-sibling::span[1]", null, true, self::BALANCE_REGEXP))) {
                    if (!$this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Available point balance')]/following-sibling::span[1]", null, true, self::BALANCE_REGEXP)) && $this->http->FindPreg("/Account information/")) {
                        $this->SetBalance($this->http->FindPreg("/Available\s*points:\s*<h3[^>]+>([^<]+)/"));
                    }
                }
            }
        }

        //# Points to Expire
        $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//dt[contains(text(), 'Points next expire')]/following::dd[1]", null, true, self::BALANCE_REGEXP));

        if (!isset($this->Properties['PointsToExpire'])) {
            $this->SetProperty("PointsToExpire", $this->http->FindSingleNode("//span[contains(text(), 'Points next expire')]/following::span[1]", null, true, self::BALANCE_REGEXP));
        }
        //# Expiration Date    // refs #4497
        $exp = str_replace('.', '/', $this->http->FindSingleNode("//*[contains(text(), 'Points next expire')]", null, true, "/expire\s*on\s*([^<]+)/ims"));
        $this->logger->debug("Exp date: {$exp}");

        if (($exp = strtotime($exp)) && isset($this->Properties['PointsToExpire']) && $this->Properties['PointsToExpire'] != 0) {
            $this->SetExpirationDate($exp);
        }

        // logout to avoid: "A session for this user name is currently open. Please try to log in again later." ~ 10 minutes
        if ($this->logoutUrl) {
            $this->http->NormalizeURL($this->logoutUrl);
            $this->http->GetURL($this->logoutUrl);
        }// if ($this->logoutUrl)
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.dreampoints.com/';

        return $arg;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/data-sitekey=\"([^\"]+)/");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters);

        return $captcha;
    }

    private function getCsrf()
    {
        $this->logger->notice(__METHOD__);
        $csrf = $this->http->FindPreg("/_csrf_token:\s*'(.+?)'/");

        if (!$csrf) {
            return false;
        }

        $this->http->SetInputValue('_csrf_token', $csrf);

        return true;
    }
}
