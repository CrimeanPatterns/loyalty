<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBoloco extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    /**
     * like as huhot, canes, whichwich, boloco.
     */

    // second login form: https://www.pxsweb.com/merchant/j_security_check, see whichwich

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $this->http->GetURL('https://boloco.myguestaccount.com/login/accountbalance.srv?id=CqlEYncE8RM%3d');

        if (!$this->http->ParseForm()) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'printedCard']/@name"), $this->AccountFields['Login']);
        $this->http->SetInputValue($this->http->FindSingleNode("//form[contains(@action, 'account-balance')]//button/@name"), '');

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Invalid card number.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid card number.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Failed to perform operation
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Failed to perform operation')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->SetInputValue($this->http->FindSingleNode("//input[@id = 'registrationCode']/@name"), $this->AccountFields['Pass']);
        $this->http->SetInputValue($this->http->FindSingleNode("//button[contains(@class, 'Button')]/@name"), "");

        return true;
    }

    public function checkErrors()
    {
        /*
         * February 16, 2017
         *
         * The page you are trying to reach has been temporarily disabled due to security concerns.
         *
         * We are working to restore service. It may be days until service is restored for retrieving your balance through this page.
         *
         * Maintaining the security of your account is our highest priority.
         */
        if ($message = $this->http->FindPreg("/We are working to restore service. It may be days until service is restored for retrieving your balance through this page\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/We are working to restore service and expect it to be available later this month. The balance on your card may be obtained at any participating store\./")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return false;
        }
        // Access is successful
        if ($this->http->FindSingleNode("//strong[contains(text(), \"Stored Value\")]")) {
            return true;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Invalid Card Number')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Invalid Card and/or Registration Code')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Stored Value
        $this->SetBalance($this->http->FindSingleNode('//div[strong[contains(text(), "Stored Value")]]/following-sibling::div[1]'));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[contains(@action, '/guest/nologin/account-balance')]//div[@class = 'g-recaptcha']/@data-sitekey");
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
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }
}
