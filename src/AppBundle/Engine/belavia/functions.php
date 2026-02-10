<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBelavia extends TAccountChecker
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
//        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptcha());
//        $this->http->FilterHTML = false;
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://leader.belavia.by/loyalty/frame/index/');

        return $this->loginSuccessful();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL('https://leader.belavia.by/loyalty/frame/?lang=5');

        if (!$this->http->ParseForm(null, '//form[@name="f1" and @action="./"]')) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('subm', '');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//b[contains(text(), "504 - Gateway Timeout")]')) {
            throw new CheckRetryNeededException();
        }

        if ($this->http->FindPreg('/Uncaught exception \'Exception\' with message \'XML error parsing WSDL from https:\/\/ibe.belavia.by\/reverse-sso\/profile.asmx\?WSDL on line 1:/')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://en.belavia.by/leader/registration/");

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//div[@class = "container-fluid login_form"]/div[@class = "bs-callout bs-callout-danger"]/h4[contains(text(), "Incorrect CAPTCHA code was entered")]')) {
            $this->captchaReporting($this->recognizer, false);

            $this->retryWithCaptcha();
        }

        // Access is allowed
        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($error = $this->http->FindSingleNode('//div[@class = "container-fluid login_form"]/div[@class = "bs-callout bs-callout-danger"]/h4')) {
            $this->logger->error("[Error]: {$error}");

            if (strstr($error, 'Cannot authenticate this user. Please check entered credentials')) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($error, 'Incorrect CAPTCHA code was entered')) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 0, self::CAPTCHA_ERROR_MSG);
            }

            $this->DebugInfo = $error;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//div[contains(@class, "card-username")]')));
        // Number
        $this->SetProperty('MemberID', $this->http->FindSingleNode('//div[contains(@class, "card-account")]/b'));
        // Balance: 123
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "card-miles")]', null, true, '/Balance: (\d+)/'));

        /*
        // Load required data page
        if ($this->http->GetURL("https://en.belavia.by/leader/balance/")) {
            // Points available for award execution
            $this->SetBalance($this->http->FindSingleNode("//div[span[contains(text(), 'Balance:')]]/following-sibling::div[1]/strong", null, true, self::BALANCE_REGEXP_EXTENDED));
            // 1. Name (Name *)
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'member ID')]/preceding-sibling::div[1]")));
            // 2. Member ID (AccountNumber *)
            $this->SetProperty("MemberID", $this->http->FindSingleNode("//div[contains(text(), 'member ID')]", null, true, "#member\s*ID\s*(\w+)#"));
            // 3. Level (Status *)
            $this->SetProperty("Level", $this->http->FindSingleNode("//div[span[contains(text(), 'Level:')]]/following-sibling::div[1]/strong"));
            // Member Since (absent)
            // 4. Number of qualifying points in the current year
            $this->SetProperty("QualifyingPoints", $this->http->FindSingleNode("//div[span[contains(text(), 'Number of qualifying points in the current year:')]]/following-sibling::div[1]/strong", null, true, self::BALANCE_REGEXP));
            // 5. Number of segments in the current year
            $this->SetProperty("NumberOfSegments", $this->http->FindSingleNode("//div[span[contains(text(), 'Number of segments in the current year:')]]/following-sibling::div[1]/strong", null, true, self::BALANCE_REGEXP));
            // You need «Silver»:
            $silver = $this->http->FindSingleNode("//div[span[contains(text(), 'You need «SILVER»:')]]/following-sibling::div[1]/strong");
            // Divide silver in to 2 properties
            if (preg_match("#(\d+) points, (\d+) segments to move to the next level#", $silver, $m)) {
                // 6. Points to next level
                $this->SetProperty("PointsToNextLevel", $m[1]);
                // 7. Segments to next level
                $this->SetProperty("SegmentsToNextLevel", $m[2]);
            }// if (preg_match("#(\d+) points, (\d+) segments to move to the next level#", $silver, $m))
            else {
                $this->logger->notice("PointsToNextLevel and SegmentsToNextLevel aren't found");
            }
        }// if ($this->http->GetURL("https://en.belavia.by/leader/balance/"))
        */
    }

    private function retryWithCaptcha($attempts = 3)
    {
        $this->logger->notice(__METHOD__);

        if ($attempts == 0
            || !$this->http->ParseForm(null, '//form[@name="f1" and @action="./"]')
            || !$this->http->FindSingleNode('//img[@src = "../../securimage/securimage_show.php"]/@src')
        ) {
            return false;
        }
        $captchaImg = $this->http->DownloadFile('https://leader.belavia.by/loyalty/securimage/securimage_show.php', 'png');
        $this->recognizer = $this->getCaptchaRecognizer();
        $captcha = $this->recognizer->recognizeFile($captchaImg);

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('capcha_num', $captcha);
        $this->http->SetInputValue('subm', '');

        if (!$this->http->PostForm()) {
            return false;
        }

        return true;
    }

    private function loginSuccessful()
    {
        return !is_null($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href"));
    }
}
