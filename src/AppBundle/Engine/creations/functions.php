<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCreations extends TAccountChecker
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
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL("https://www.creationsrewards.net/account");
        // Access is allowed
        if ($this->http->FindPreg('#\.net/account#', false, $this->http->currentUrl())) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        // without this header request return empty page
//        $this->http->setDefaultHeader("accept-encoding", "gzip");
        $this->http->GetURL("http://www.creationsrewards.net/members-area?ac=" . time());

        if (!$this->http->ParseForm(null, '//form[contains(@id, "login-form")]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("username", $this->AccountFields["Login"]);
        $this->http->SetInputValue("passwd", $this->AccountFields["Pass"]);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->SetInputValue("g-recaptcha-response", $captcha);
        $this->http->SetInputValue('Submit', '');

        return true;
    }

    public function checkErrors()
    {
        //# CreationsRewards server updates are in progress
        if ($message = $this->http->FindPreg("/(CreationsRewards server updates are in progress[^<]+)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // form submission
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // check for invalid password
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-danger')]")) {
            $this->logger->error("[Error]: {$message}");

            if (strstr($message, 'Incorrect email or password. Please try again.')
                || str_contains($message, 'Recently, we sent you an email, and it bounced back to us as undeliverable')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }
        // login successful
        if ($this->http->FindNodes("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Username and password do not match or you do not have an account yet.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Username and password do not match or you do not have an account yet.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid email or password. Please try again.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), ' email or password. Please try again.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Invalid Captcha Code
        if ($message = $this->http->FindPreg("/<script type=\"text\/javascript\">alert\(\"Invalid Captcha Code\"\); window.history\.go\(-1\);/")) {
            $this->recognizer->reportIncorrectlySolvedCAPTCHA();

            throw new CheckRetryNeededException(3);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != "https://www.creationsrewards.net/account") {
            $this->http->GetURL("https://www.creationsrewards.net/account");
        }
        // Earned Shopping Points
        $this->SetProperty("ShoppingPoints", $this->http->FindSingleNode("//td[contains(text(), 'Earned Shopping Points:')]/following-sibling::td[1]"));
        // Earned Referral Points
        $this->SetProperty("ReferralPoints", $this->http->FindSingleNode("//td[contains(text(), 'Earned Referral Points:')]/following-sibling::td[1]"));
        // Earned Points
        $this->SetProperty("TotalEarned", $this->http->FindSingleNode("//td[contains(text(), 'Earned Points:')]/following-sibling::td[1]"));
        // Less Points Redeemed/Debited
        $this->SetProperty("Redemptions", $this->http->FindSingleNode("//td[contains(text(), 'Less Points Redeemed/Debited:')]/following-sibling::td[1]"));
        // Balance - Total Points Available to Redeem
        if (!$this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Total Points Available to Redeem')]/following-sibling::td[1]"))) {
            if ($this->http->FindSingleNode("//p[contains(text(), 'You have not yet fully activated your account.')]")) {
                $this->SetWarning('You have not yet fully activated your account.');
            }
        }

        $this->http->GetURL("https://www.creationsrewards.net/account/my-profile");
        $fname = trim($this->http->FindSingleNode("//input[@name = 'form[fname]']/@value"));
        $lname = trim($this->http->FindSingleNode("//input[@name = 'form[lname]']/@value"));
        // set Name
        $this->SetProperty("Name", beautifulName("$fname $lname"));
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //		$arg["SuccessURL"] = "http://www.creationsrewards.net/account";
        return $arg;
    }

    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/\{sitekey:\s*'(.+?)',/");
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
