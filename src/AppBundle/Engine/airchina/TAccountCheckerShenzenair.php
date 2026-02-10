<?php

class TAccountCheckerShenzenair extends TAccountChecker
{
    private const REWARDS_PAGE_URL = 'https://global.shenzhenair.com/zhair/ibe/profile/editProfile.do?type=queryB2CUser';
    /** @var CaptchaRecognizer */
    private $recognizer;

    private $status = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
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
        $this->waitingPageSubmit();

        if ($this->http->FindSingleNode('//a[contains(text(), "Logout")]')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://global.shenzhenair.com/zhair/ibe/common/flightSearch.do?language=en&market=CN');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        try {
            $captcha = $this->parseCaptcha();
        } catch (ErrorException $e) {
            if (strstr($e->getMessage(), "Use of undefined constant EMAIL_HEADERS")) {
                $this->logger->notice("exception: " . $e->getMessage());
            }

            sleep(5);
            $captcha = $this->parseCaptcha();
        }

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Accept-Encoding'  => 'gzip, deflate, br',
            'Content-Type'     => 'application/json',
            'Referer'          => 'https://global.shenzhenair.com/zhair/ibe/common/flightSearch.do?language=en&market=CN',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $login = $this->AccountFields['Login'];

        if (strpos($login, 'CA') !== false || strpos($login, 'CA') > 0) {
            $login = str_replace('CA', '', $login);
        }

//        $this->AccountFields['Pass'] = '';//todo

        $loginUsernameType = "F";
        $loginMethodType = "logingByFFPid";

        /*
         * wrong determination
         *
        if (strlen($login) == 12) {
            $loginMethodType = "logingB2CUserId";
            $loginUsernameType = "I";
        }
        */

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://global.shenzhenair.com/zhair/ibe/profile/processLogin.do?ConversationID=&captcha%2FverificationType=ImagIdentifyingClick&captcha%2FverificationCode={$captcha}&captcha%2FloginPageSource=LOGIN&credentials%2FloginUsername={$login}&credentials%2FloginPassword=" . base64_encode($this->AccountFields['Pass']) . "&loginMethodType={$loginMethodType}&credentials%2FloginUsernameType={$loginUsernameType}", $headers);
        $this->http->RetryCount = 2;
        $profileData = $this->http->JsonLog(null, 3);
        $email = $profileData->UserProfileSummary->UserProfile->Email ?? null;

        if (!$email) {
            $validator = $profileData->UserProfileSummary->LoginForm->Error->Validator ?? null;
            $this->logger->error("[Error code]: {$validator}");

            if ($validator === 'E100109') {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);
            /*
            if (in_array($validator, [
                    'E100113',
                    'E100113'
                ])
            ) {
                throw new CheckException('User name or password error!', ACCOUNT_INVALID_PASSWORD);
            }
            if (in_array($validator, [
                    'E200020',
                ])
            ) {
            */
//                throw new CheckException('The account number has been disabled. Please contact customer service', ACCOUNT_PROVIDER_ERROR);// todo: need to use airchina parser
            $this->DebugInfo = 'need to use airchina parser';
//
//                $this->logger->notice('Call TAccountCheckerAirchina');
//                $this->logger->debug(var_export($this->AccountFields, true), ['pre' => true]);
//                /* @var TAccountCheckerAirchina $attChecker */
//                $airchinaChecker = new TAccountCheckerAirchina();
//                $airchinaChecker->logger = $this->logger;
//                $airchinaChecker->globalLogger = $this->globalLogger;// fixed notifications
//                $airchinaChecker->http = $this->http;
//                $airchinaChecker->AccountFields = $this->AccountFields;
//                $airchinaChecker->HistoryStartDate = $this->HistoryStartDate;
//                $airchinaChecker->http->LogHeaders = $this->http->LogHeaders;
//                $airchinaChecker->ParseIts = $this->ParseIts;
//                $airchinaChecker->ParsePastIts = $this->ParsePastIts;
//                $airchinaChecker->WantHistory = $this->WantHistory;
//                $airchinaChecker->WantFiles = $this->WantFiles;
//                if ($airchinaChecker->selenium() && $airchinaChecker->Login()) {
//                    $airchinaChecker->Parse();
//                    $this->SetBalance($airchinaChecker->Balance);
//                    $this->Properties = $airchinaChecker->Properties;
//                }
//                return false;
            /*
            }
            */

            return $this->checkErrors();
        }

        $this->status = $profileData->UserProfileSummary->UserProfile->FFPInfo->Grade ?? null;

        $payload = [
            'Loginmethodtype'               => 'logingByFFPid',
            'Successpage'                   => 'F',
            'Credentials/Loginusernametype' => 'F',
            'Checkcode'                     => $captcha,
        ];
        $this->http->PostURL('https://global.shenzhenair.com/zhair/ibe/profile/processLogin.do?SECURE=true', $payload);

        return true;
    }

    public function waitingPageSubmit()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm("waitingForm")) {
            $this->http->FormURL = $this->http->FindPreg("/document.waitingForm.action = '([^\']+)';/");
            $this->http->NormalizeURL($this->http->FormURL);
            $getQueryVariable = $this->http->FindPreg("/getQueryVariable\('([^\']+)/");
            $vars = explode('&', $getQueryVariable);

            foreach ($vars as $var) {
                [$name, $value] = explode('=', $var);
                $this->http->SetInputValue($name, $value);
            }
            $this->http->PostForm();
        }
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - Useable mileage
        $this->SetBalance($this->http->FindSingleNode("//span[contains(text(), 'Useable mileage')]/following-sibling::span[1]"));
        // Name
        $firstName = $this->http->FindSingleNode("//span[contains(text(), 'First Name')]/following-sibling::span[1]");
        $lastName = $this->http->FindSingleNode("//span[contains(text(), 'Last name/Surname')]/following-sibling::span[1]");
        $this->SetProperty('Name', beautifulName("{$firstName} {$lastName}"));
        // Member Number
        $this->SetProperty('CardNumber', $this->http->FindSingleNode("//span[contains(text(), 'Member Number')]/following-sibling::span[1]"));
        // Member Level
//        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(text(), 'Member Level')]/following-sibling::span[1]"));// refs #21100
        $this->SetProperty("Status", $this->http->FindPreg("/Member Level<\/span><span[^>]*>([^<]+)/") ?? $this->status); // refs #21100
        // Membership Level Expiration Date
//        $this->SetProperty("StatusExpiration", str_replace(['?', '：'], '', Html::cleanXMLValue($this->http->FindSingleNode("//p[contains(text(), 'Membership Level Expiration Date')]", null ,true, "/Date:?\s*([^<]+)/"))));

        if ($this->Balance <= 0) {
            return;
        }
        $this->logger->info("Expiration date", ['Header' => 3]);
        $this->http->GetURL("https://global.shenzhenair.com/zhair/ibe/extra/couponManage.do?ConversationID=OJ1601631350955");
        // Will Expire In 3 Months
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//span[contains(text(), 'Number of Kilometers Expected to Expire at the end of this month')]", null, true, "/:(.+)/"));
    }

    private function parseCaptcha(): ?string
    {
        $this->logger->notice(__METHOD__);
        $file = $this->http->DownloadFile("https://global.shenzhenair.com/zhair/ibe/profile/IdentifyingCode.do?{$this->random()}", "jpg");
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file);
        unlink($file);

        return $captcha;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
