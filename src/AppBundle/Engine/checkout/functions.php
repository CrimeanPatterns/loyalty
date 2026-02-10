<?php

use AwardWallet\Engine\ProxyList;

/**
 * Class TAccountCheckerCheckout
 * Display name: Checkout 51
 * Database ID: 1121
 * Author: APuzakov
 * Created: 26.03.2015 8:13.
 */
class TAccountCheckerCheckout extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->SetProxy($this->proxyDOP());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.checkout51.com/account/profile?lang=EN_US", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            $this->providerErrors();

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.checkout51.com/account/login");

        if (!$this->http->ParseForm(null, "//form[label[input[contains(@name,'password')]]]")) {
            return false;
        }
        $csrfp = $this->http->getCookieByName("CSRFP-Token", null, "/account/");

        if (!$csrfp) {
            $this->logger->error("csrfp_token not found");
        } else {
            $this->http->SetInputValue("CSRFP-Token", $csrfp);
        }

        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("form_login_btn_submit", "Logging in...");

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "https://www.checkout51.com/";

        return $arg;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return false;
        }

        $this->providerErrors();

        if ($this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")) {
            return true;
        }
        // check errors
        if ($message = $this->http->FindSingleNode("//p[contains(@class, 'error')]")) {
            if (strstr($message, 'Incorrect email or password')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Click reCAPTCHA checkbox to log in
            if (strstr($message, 'Click reCAPTCHA checkbox to log in')) {
                throw new CheckRetryNeededException(3, 10, self::CAPTCHA_ERROR_MSG);
            } else {
                $this->logger->error(">>> {$message}");
            }
        }// if ($message = $this->http->FindSingleNode("//p[contains(@class, 'error')]"))
        // Your account has been suspended
        if ($this->http->currentUrl() == 'https://www.checkout51.com/account/login?msg=suspended') {
            throw new CheckException("Your account has been suspended.", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.checkout51.com/account/profile?lang=EN_US");
        // Current balance
        $this->SetBalance($this->http->FindSingleNode("//h4[contains(text(),'Balance')]/following-sibling::p", null, true, '/\$[\d\.]+/ims'));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h2[contains(@class,'name')]")));
        // Receipts pending approval
        $this->SetProperty("ReceiptsPendingApproval", $this->http->FindSingleNode("//p[contains(@class,'pending-approval')]"));
        // Total earned $0.00
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//h4[contains(text(),'Total earned')]/following-sibling::p"));
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[label[input[contains(@name,'password')]]]//div[@class = 'g-recaptcha']/@data-sitekey");
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

    protected function providerErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’ve updated our Terms and Privacy Notice
        if ($this->http->FindSingleNode('//h1[contains(text(), "We’ve updated our Terms and Privacy Notice")]')) {
            $this->throwProfileUpdateMessageException();
        }
    }
}
