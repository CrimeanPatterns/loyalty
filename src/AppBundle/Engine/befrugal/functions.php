<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBefrugal extends TAccountChecker
{
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.befrugal.com/account/member/account-summary/';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $login = 0;

    // Error code 404 returned while checking via amazon
    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if (
            $this->loginSuccessful()
            && !stristr($this->http->currentUrl(), 'https://www.befrugal.com/account/member/?ReturnUrl')
            && !in_array($this->http->currentUrl(),
                [
                    'https://www.befrugal.com/account/member/forgot-password/',
                ])
        ) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address, e.g. jon@yahoo.com', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.befrugal.com/home/");

        if (!$this->http->ParseForm(null, "//form[contains(@class, 'Login')]")) {
            return $this->checkErrors();
        }

        $context = $this->http->Form['UIContextLookup'];
        $this->http->setDefaultHeader("RequestVerificationToken", $this->http->Form['__RequestVerificationToken']);
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        unset($this->http->Form['__RequestVerificationToken']);

//        $this->http->FormURL = 'https://www.befrugal.com/ServicePages/Authentication/Authenticate/?handler=PreLogin';
//        $this->http->SetInputValue('Username', $this->AccountFields['Login']);
//        $this->http->SetInputValue('emailLogin', "");
//        $this->http->PostForm();
//        $this->http->JsonLog();

        $this->http->FormURL = 'https://www.befrugal.com/ServicePages/Authentication/Authenticate/?handler=Login';
        $this->http->SetInputValue('UIContextLookup', $context);
        $this->http->SetInputValue('Username', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('emailLogin', "");

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('g-recaptcha-response-invis', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // BeFrugal is currently down for maintenance.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'BeFrugal.com will be back and running in ')]")) {
            throw new CheckException("BeFrugal is currently down for maintenance. " . $message, ACCOUNT_PROVIDER_ERROR);
        }
        // Service Unavailable
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'HTTP Error 503. The service is unavailable.')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//div[div[contains(text(), "2-Step Verification")]]/text()[2]');

        if (
            !$this->http->ParseForm(null, "//form[contains(@action, '/account/member/twofactor')]")
            || !$question
        ) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue('code', $this->Answers[$this->Question]);
        $this->http->SetInputValue('choice', 'submitcode');
        $this->http->PostForm();
        unset($this->Answers[$this->Question]);
        // That code is incorrect. Please try again.
        if ($error = $this->http->FindSingleNode('//span[contains(text(), "That code is incorrect.")]')) {
            $this->AskQuestion($this->Question, $error, 'Question');

            return false;
        }

        return true;
    }

    public function Login()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        if (!$this->http->PostForm() && !strstr($this->http->currentUrl(), "aspxerrorpath=/account/member/two-factor/default.aspx")) {
            if (in_array($this->AccountFields['Login'], [
                'saim.khan1992@hotmail.com',
                'IAD-to-ISB@hotmail.com',
                'siulaw1994@gmail.com',
                'Davidylin@gmail.com',
                'PAGEF5.EM@GMAIL.COM',
                'GMARNECH@gmail.com',
                'neutrongirl4@pm.me',
                'bclapper1010@outlook.com',
                'rubiipham4@gmail.com',
                'meatloaf15-042020@yahoo.com',
                'gmarnech@outlook.com',
                'm.amsyarrafiq@gmail.com',
                'jiangge1991@163.com',
            ])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        $response = $this->http->JsonLog();

        if (isset($response->result)) {
            $this->http->unsetDefaultHeader("RequestVerificationToken");
            $this->http->unsetDefaultHeader("X-Requested-With");

            // 2fa
            if ($response->result == 3) {
                $this->http->GetURL("https://www.befrugal.com/account/member/twofactor/?ReturnUrl=https%3A%2F%2Fwww.befrugal.com%2Fhome%2F");
            } elseif (in_array($response->result, [4, 5])) {
                $this->http->GetURL("https://www.befrugal.com/home/");
            } else {
                $message = $response->error ?? null;

                if (
                    strstr($message, 'We do not recognize this email address')
                    || $message === "Incorrect email address or password."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                // $response->result == 1 | Incorrect email address or password. - wrong error
                if ($message === 'Incorrect email address or password') {
                    $this->DebugInfo = "blocked";

                    throw new CheckRetryNeededException();
                }

                // AccountID: 6653899
                if ($response->result == 0) {
                    throw new CheckException("Your account does not have a password.", ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = "[Result - {$response->result}]: " . $message;

                return false;
            }
        }

        $this->openBroken2FAURL();
        $this->openBroken2FAURL();

        if ($this->loginSuccessful()) {
            return true;
        }
        // 2-Step Verification
        if ($this->parseQuestion()) {
            return false;
        }
        // Invalid credentials
        if ($message = $this->http->FindSingleNode("//span[contains(@id, '_bfLoginStatus_LoginControl_lblMessage')]")) {
            for (; $this->login < 3;) {
                $this->logger->notice("Retry: {$this->login}");
                $this->http->Form = $form;
                $this->http->FormURL = $formURL;
                $this->login++;

                return $this->Login();
            }// for (; $this->login < 3;)

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }// if ($message = $this->http->FindSingleNode("//span[contains(@id, '_bfLoginStatus_LoginControl_lblMessage')]"))

        return $this->checkErrors();
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            $this->openRewardsPageAURL();
            $this->openRewardsPageAURL();
        }

        // Member since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[contains(text(), 'Member since')]/following-sibling::div"));
        // Lifetime Cash Back Paid
        $this->SetProperty("LifetimeCashBack", $this->http->FindSingleNode("//span[contains(text(), 'Lifetime Cash Back')]/following-sibling::span"));

        $this->http->GetURL("https://www.befrugal.com/account/member/cash-back-and-bonuses/");
        // Pending
        $pending = $this->http->FindSingleNode("//div[div[contains(text(), 'Pending')]]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "befrugalPending",
            "DisplayName"       => "Pending",
            "Balance"           => $pending,
            "BalanceInTotalSum" => true,
        ]);
        // Verified
        $verified = $this->http->FindSingleNode("//div[div[contains(text(), 'Verified')]]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "befrugalVerified",
            "DisplayName"       => "Verified",
            "Balance"           => $verified,
            "BalanceInTotalSum" => true,
        ]);
        // Payable
        $payable = $this->http->FindSingleNode("//div[div[contains(text(), 'Payable')]]/following-sibling::div[1]", null, true, self::BALANCE_REGEXP_EXTENDED);
        $this->AddSubAccount([
            "Code"              => "befrugalPayable",
            "DisplayName"       => "Payable",
            "Balance"           => $payable,
            "BalanceInTotalSum" => true,
        ]);
        // Balance - Total
        $this->SetBalance($this->http->FindSingleNode("//div[div[contains(text(), 'Total')]]/following-sibling::div[1]", null, true, "/([\d\.\,]+)/ims"));

        // Name
        $this->http->GetURL("https://www.befrugal.com/account/member/my-settings/");

        // AccountID: 6437926
        if ($this->http->currentUrl() == 'https://www.befrugal.com/account/member/?ReturnUrl=%2Faccount%2Fmember%2Fmy-settings%2F&RecentLogin=true') {
            $this->SetBalance($this->http->FindSingleNode('//span[@class="menu-summary-account-dollar"]'));
            unset($this->Properties['SubAccounts']);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Please login again to access your account")]') && $this->http->ParseForm(null, "//form[contains(@class, 'Login')]")) {
            $this->http->setDefaultHeader("RequestVerificationToken", $this->http->Form['__RequestVerificationToken']);
            $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
            unset($this->http->Form['__RequestVerificationToken']);

            $this->http->SetInputValue('Password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('emailLogin', '');

            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            $this->http->SetInputValue('g-recaptcha-response-invis', $captcha);

            $this->http->FormURL = 'https://www.befrugal.com/ServicePages/Authentication/Authenticate/?handler=Login';
            $this->http->PostForm();

            $this->http->unsetDefaultHeader("RequestVerificationToken");
            $this->http->unsetDefaultHeader("X-Requested-With");

            $response = $this->http->JsonLog();
            $this->http->GetURL("https://www.befrugal.com/account/member/my-settings/");
        }

        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//input[@id = "Address_FirstName"]/@value')
            . ' ' . $this->http->FindSingleNode('//input[@id = "Address_LastName"]/@value')
        ));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // To access your BeFrugal account, please confirm your email address.
            // Click below and we will email you a link to confirm your email address.
            if ($message = $this->http->FindSingleNode("//p[contains(text(),'To access your BeFrugal account, please confirm your email address.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // need create a password for BeFrugal account
            if ($this->http->FindPreg("/Please create a password for your BeFrugal account\./")) {
                throw new CheckException("BeFrugal.com website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } /*checked*/

            // hard code, broken accounts
            if (
                $this->http->currentUrl() == 'https://www.befrugal.com/coupons/error.aspx?aspxerrorpath=/account/member/my-settings/default.aspx'
            ) {
                if (in_array($this->AccountFields['Login'], [
                    'saim.khan1992@hotmail.com',
                    'IAD-to-ISB@hotmail.com',
                    'siulaw1994@gmail.com',
                    'Davidylin@gmail.com',
                    'PAGEF5.EM@GMAIL.COM',
                    'GMARNECH@gmail.com',
                    'neutrongirl4@pm.me',
                    'bclapper1010@outlook.com',
                    'rubiipham4@gmail.com',
                    'meatloaf15-042020@yahoo.com',
                    'gmarnech@outlook.com',
                    'm.amsyarrafiq@gmail.com',
                    'jiangge1991@163.com',
                ])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException();
            }

            if (in_array($this->AccountFields['Login'], [
                'gmarnech@outlook.com',
            ])) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@id, 'ctl00_hdr_bfLoginStatus_lnkLogout')]/@id | //a[contains(@class, 'logOutLink')]")) {
            return true;
        }

        return false;
    }

    private function openBroken2FAURL()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->currentUrl() == 'https://www.befrugal.com/coupons/error.aspx?aspxerrorpath=/account/member/two-factor/default.aspx') {
            sleep(3);
            $this->logger->notice("try to fix 404");
            $this->http->GetURL("https://www.befrugal.com/account/member/two-factor/?ReturnUrl=%2fhome%2f");
        }
    }

    private function openRewardsPageAURL()
    {
        $this->logger->notice(__METHOD__);
        // 404 workaround, it almost always helps
        if ($this->http->currentUrl() == 'https://www.befrugal.com/coupons/error.aspx?aspxerrorpath=/account/member/account-home/default.aspx') {
            sleep(3);
            $this->logger->notice("try to fix 404");
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6Lf2MscpAAAAANHlrwxJMY7manlrIITazmr9BnLF";

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }
}
