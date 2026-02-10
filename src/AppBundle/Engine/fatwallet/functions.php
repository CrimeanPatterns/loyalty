<?php

class TAccountCheckerFatwallet extends TAccountChecker
{
    public $ContinueToStep;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->ParseForms = false;
        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.fatwallet.com/account/cashback");

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        //	    $captcha = $this->parseCaptcha();
        //	    if ($captcha === false)
        //		    return false;
        //	    $this->http->SetInputValue("captcha", $captcha);
        //	    $this->http->SetInputValue("captchamyheart", "1");

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.fatwallet.com/i/danish.gif";

        return $arg;
    }

    public function checkErrors()
    {
        // An internal Error has occurred
        if ($error = $this->http->FindSingleNode("//p[contains(text(), 'An internal Error has occurred')]")) {
            throw new CheckException("An internal Error has occurred. The full error details have been sent to the FatWallet technical staff for further investigation.", ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")
            // Internal Server Error - Read
            || $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            // An error occurred while processing your request.
            || $this->http->FindPreg("/An error occurred while processing your request\./ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindPreg("/After a decade on our current platform, we're upgrading our plumbing\./")) {
            throw new CheckException("After a decade on our current platform, we're upgrading our plumbing. The site will be down for a few hours.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        //# Your Cash Back account access has been restricted
        if ($this->http->FindPreg("/(your Cash Back account access has been restricted\.)/ims")) {
            throw new CheckException("Due to an error in your Cash Back account, your Cash Back account access has been restricted. Please send in a <a target=\"_blank\" href=\"https://www.fatwallet.com/support/contact.php?contactReason=cashbackQ&frm_cashbackQ_subject=Restricted%20Cash%20Back%20Account\">support ticket</a> to resolve this problem.", ACCOUNT_PROVIDER_ERROR);
        }

        // Access is allowed
        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")) {
            return true;
        }
        // Invalid login or password
        if ($error = $this->http->FindNodes("//div[contains(@class, 'authenticationFormError generic')]/div")) {
            $message = implode('. ', $error);

            if (!strstr($message, 'Captcha mismatch')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            } else {
                $this->http->Log("Captcha error -> {$message}");

                throw new CheckRetryNeededException(3, 7);
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        //2 cashback type
        //cashback.php (green)
        $balance = $this->http->FindSingleNode("//td[contains(text(), 'Available Cash Back')]/following-sibling::td[2]/b");

        if (isset($balance)) {
            $this->SetBalance($balance);
            $this->SetProperty("PendingCashBack", $this->http->FindSingleNode("//td[contains(text(), 'Pending Cash Back')]/following-sibling::td[2]"));
            $this->SetProperty("InvestigatingCashBack", $this->http->FindSingleNode("//td[contains(text(), 'Investigating Cash Back')]/following-sibling::td[2]"));
            $this->SetProperty("AppealedCashBack", $this->http->FindSingleNode("//td[contains(text(), 'Appealed Cash Back')]/following-sibling::td[2]"));
            $this->SetProperty("CashOutRequested", $this->http->FindSingleNode("//td[contains(text(), 'Payment Requested')]/following-sibling::td[2]"));
            $this->SetProperty("CashOutProcessed", $this->http->FindSingleNode("//td[contains(text(), 'Payment Processed')]/following-sibling::td[2]"));
            $this->SetProperty("LifetimeCashBack", $this->http->FindSingleNode("//td[contains(text(), 'Lifetime Cash Back')]/following-sibling::td[2]"));
        }
        //mycashback.php (vova)
        $balance = $this->http->FindSingleNode("//td[span[contains(text(), 'Available Cash Back')]]/following-sibling::td[2]/span");

        if (isset($balance)) {
            $balance = trim(str_ireplace("Request Payment", "", $balance));
            $balance = str_replace(".", ",", $balance);
            $this->SetBalance($balance);
            $this->SetProperty("PendingCashBack", $this->http->FindSingleNode("//td[span[contains(text(), 'Pending Cash Back')]]/following-sibling::td[2]/span"));
            $this->SetProperty("InvestigatingCashBack", $this->http->FindSingleNode("//td[span[contains(text(), 'Investigating Cash Back')]]/following-sibling::td[2]/span"));
            $this->SetProperty("AppealedCashBack", $this->http->FindSingleNode("//td[span[contains(text(), 'Appealed Cash Back')]]/following-sibling::td[2]/span"));
            $this->SetProperty("CashOutRequested", $this->http->FindSingleNode("//td[span[contains(text(), 'Cash Out Requested')]]/following-sibling::td[2]/span"));
            $this->SetProperty("CashOutProcessed", $this->http->FindSingleNode("//td[span[contains(text(), 'Cash Out Processed')]]/following-sibling::td[2]/span"));
            $this->SetProperty("LifetimeCashBack", $this->http->FindSingleNode("//td[span[contains(text(), 'Lifetime Cash Back')]]/following-sibling::td[2]/span"));
        }
        // Name
        $name = $this->http->FindSingleNode("//form[contains(@action, 'addresses')]/following::div[1]/text()[3]");

        if (!strpos($name, '@')) {
            $this->SetProperty("Name", beautifulName($name));
        }

        // refs #13010
        if ($this->AccountFields['Partner'] == 'awardwallet') {
            throw new CheckException('<a target = "_blank" href="https://www.fatwallet.com/cash-back-announcement-faq/">FatWallet Cash Back is now Ebates</a> - please delete this account and <a href="https://awardwallet.com/account/add/126">add your Ebates account</a> instead.', ACCOUNT_WARNING);
        } else {
            throw new CheckException('<a target = "_blank" href="https://www.fatwallet.com/cash-back-announcement-faq/">FatWallet Cash Back is now Ebates</a> - please delete this account and add your Ebates account instead.', ACCOUNT_WARNING);
        }
    }

    public function GetPartnerFields($values, $width)
    {
        $style = "style='width: {$width}px;'";
        $fields = [
            "Login" => [
                "Type"            => "string",
                "Caption"         => "Email",
                "Size"            => 80,
                "Required"        => true,
                "Value"           => $this->userFields['Email'],
                "InputAttributes" => $style,
            ],
            "Pass" => [
                "Type"            => "string",
                "Caption"         => "Password",
                "Size"            => 80,
                "Required"        => true,
                "Value"           => RandomStr(ord('a'), ord('z'), 10),
                "InputAttributes" => $style,
            ],
            "Agree" => [
                "Type"               => "boolean",
                "Caption"            => "I have read the <a href='http://www.fatwallet.com/useragreement.php' target='_blank'>terms and conditions</a>",
                "Caption"            => "By joining FatWallet, you agree to their <a href='http://www.fatwallet.com/user-agreement' target='_blank'>User Agreement</a> and their <a href='http://www.fatwallet.com/privacy' target='_blank'>Privacy Policy</a>",
                "Size"               => 20,
                "Required"           => true,
                "RegExp"             => '/^1$/ims',
                "Value"              => "1",
                "RegExpErrorMessage" => "You must agree to FatWallet User Agreement and Privacy Policy",
            ],
        ];

        return $fields;
    }

    public function GetPartnerFormBuilder($builder, array $options, $user)
    {
        require_once __DIR__ . '/partnerFormBuilder.php';
        fatwalletPartnerFormBuilder($builder, $options, $user);
    }

    public function GetPartnerFormTemplate()
    {
        return 'common';
    }

    protected function parseCaptcha()
    {
        $this->http->Log("parseCaptcha");
        $http2 = clone $this->http;

        if ($this->http->FindSingleNode("//input[@id = 'captchaLogin']/@name") == 'captcha') {
            $file = $http2->DownloadFile("https://www.fatwallet.com/captcha", "jpg");
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);

            try {
                $captcha = trim($recognizer->recognizeFile($file));
            } catch (CaptchaException $e) {
                $this->http->Log("exception: " . $e->getMessage());
                // Notifications
                if (strstr($e->getMessage(), "ERROR_ZERO_BALANCE")) {
                    $this->sendNotification("WARNING! " . $recognizer->domain . " - balance is null");

                    throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                // retries
                if ($e->getMessage() == 'server returned error: ERROR_CAPTCHA_UNSOLVABLE'
                    || $e->getMessage() == 'timelimit (60) hit'
                    || $e->getMessage() == 'slot not available') {
                    $this->http->Log("parseCaptcha", LOG_LEVEL_ERROR);

                    throw new CheckRetryNeededException(3, 7);
                }

                return false;
            }
            unlink($file);

            return $captcha;
        }// if ($this->http->FindSingleNode("//input[@id = 'captchaLogin']/@name") == 'captcha')

        return false;
    }
}
