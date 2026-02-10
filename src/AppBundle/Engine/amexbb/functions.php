<?php

class TAccountCheckerAmexbb extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private $headers = [
        "Accept"               => "application/json, text/plain, */*",
        "Accept-Language"      => "en-US",
        "Accept-Encoding"      => "gzip, deflate, br",
        "Channel"              => "WEB",
        "Content-Type"         => "application/json;charset=utf-8",
        "Origin"               => "https://secure.bluebird.com",
    ];

    public static function GetAccountChecker($accountInfo)
    {
        return new \AwardWallet\Engine\amexbb\AmexbbSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        /*
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://secure.bluebird.com/Account/Dashboard?omnlogin=US_Login_Bluebird", [], 20);
        $this->http->RetryCount = 2;
        if ($this->loginSuccessful()) {
            return true;
        }
        */

        return false;
    }

    public function LoadLoginForm()
    {
        //return AmexbbSeleniumPreloader::loginWithSeleniumPreload($this);

        $this->http->removeCookies();
        // for cookies
        $this->http->GetURL("https://ui.bluebird.com/api/features");
//        $this->http->GetURL("https://secure.bluebird.com/login");
//        https://secure.bluebird.com/env-config.js recaptcha keys
        /*
        $this->incapsula();
        */
        if ($this->http->Response['code'] != 200) {
            /*
            // debug
            if ($this->http->FindPreg("/<head>\s*<META NAME=\"robots\" CONTENT=\"noindex,nofollow\">\s*<script src=\"\/_Incapsula_Resource\?SWJIYLWA=[^\"]+\">\s*<\/script>\s*<body>/")) {
                throw new CheckRetryNeededException(3);
            }
            */

            return $this->checkErrors();
        }

        $xsrf = $this->http->getCookieByName("XSRF-TOKEN", ".bluebird.com");

        if (!$xsrf) {
            $this->logger->error("XSRF-TOKEN not found");

            return $this->checkErrors();
        }
        $this->headers += [
            "X-XSRF-TOKEN" => $xsrf,
        ];
        $data = [
            "username"          => $this->AccountFields['Login'],
            "password"          => $this->AccountFields['Pass'],
            "deviceProfileId"   => "41c672bf-30b9-49a7-9bb3-0bf1c7fde3c3",
            "deviceFingerprint" => "1db8ab2fbf6f1c73b2908a6773a0574e",
            "captchaV3Answer"   => "INVALID_TOKEN",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://ui.bluebird.com/api/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    /*
    protected function incapsula() {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $incapsula = $this->http->FindSingleNode("//iframe[contains(@src, '/_Incapsula_Resource?')]/@src");
        if (isset($incapsula)) {
            sleep(2);
            $this->http->NormalizeURL($incapsula);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($incapsula);
            $this->http->RetryCount = 2;
        }// if (isset($distil))
        $this->logger->debug("parse captcha form");
        $formURL = $this->http->FindPreg("/\"POST\", \"(\/_Incapsula_Resource\?[^\"]+)\"\s*,/");
        if (!$formURL)
            return false;
        $captcha = $this->parseReCaptcha();
        if ($captcha === false)
            return false;
        $this->http->NormalizeURL($formURL);
        $this->http->FormURL = $formURL;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        sleep(2);
        $this->http->GetURL($referer);

        if ($this->http->Response['code'] == 503) {
            $this->http->GetURL($this->http->getCurrentScheme()."://".$this->http->getCurrentHost());
            sleep(1);
            $this->http->GetURL($referer);
        }

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@class = 'form_container']/div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");
        if (!$key)
            return false;
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->increaseTimeLimit(300);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy" => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, true, 3, 1);

        $this->increaseTimeLimit($recognizer->RecognizeTimeout);

        return $captcha;
    }
    */

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('
                //h1[contains(normalize-space(), "website is undergoing routine maintenance")]
            ')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->accessToken)) {
            // Verify with a One Time Password
            if (isset($response->authentication->type)) {
                $this->parseQuestion();

                return false;
            }

            return $this->loginSuccessful($response->accessToken);
        }

        $message = $response->messages[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "The username and password combination isn't right.")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        /*
        if ($this->ParseQuestion()) {
            return false;
        }
        */

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 0);
        $email = $response->authentication->channel->emailAddress;
        $phone = $response->authentication->channel->phoneNumber ?? null;

        if (!$email) {
            return false;
        }

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $data = [
            "channel"      => "email",
            "phoneNumber"  => $phone,
            "emailAddress" => $email,
        ];

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $this->State['headers'] = $this->headers;
        $this->http->PostURL("https://ui.bluebird.com/api/requestOtp", json_encode($data), $this->headers);
        $this->http->JsonLog();
        $question = "Please enter temporary six digit verification code which was sent to the following email: {$email}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "VerificationCode";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->headers = $this->State['headers'];

        $data = [
            "verificationCode" => $this->Answers[$this->Question],
        ];
        unset($this->Answers[$this->Question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://ui.bluebird.com/api/validateOtp", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        // Invalid answer
        $message = $response->messages[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                strstr($message, "The verification code you entered is not correct.")
            ) {
                $this->AskQuestion($this->Question, "The verification code you entered is incorrect. Please re-enter your code.", "VerificationCode");

                return false;
            }

            return false;
        }

        return true;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstName . " " . $response->lastName));
        // Account Number
        $this->SetProperty("Number", $response->accountNumber ?? null);

        $this->http->GetURL("https://ui.bluebird.com/api/accounts", $this->headers);
        $this->SetProperty("CombineSubAccounts", false);
        $accounts = $this->http->JsonLog();

        foreach ($accounts as $account) {
            switch ($account->type) {
                case 'main':
                    // Balance - Available balance
                    $this->SetBalance($account->availableBalance);

                    break;

                case 'sub':
                    if (count($accounts) == 1) {
                        // Balance - Available balance
                        $this->SetBalance($account->availableBalance);
                    } else {
                        $this->AddSubAccount([
                            'Code'        => 'amexbb' . $account->id,
                            'DisplayName' => $account->summaryName,
                            'Balance'     => $account->availableBalance,
                            'Number'      => $account->lastFourDigitsOfCardNumber,
                        ], true);
                    }

                    break;

                case "reserved":
                    $this->logger->debug("skip '{$account->name}'");

                    break;

                case 'smartPurse':
                    // SubAccount - Walmart® Buck$
                    $this->AddSubAccount([
                        'Code'        => 'amexbbWalmartBucks',
                        'DisplayName' => "Walmart® Buck$",
                        'Balance'     => $account->availableBalance,
                    ], true);

                    break;

                default:
                    $this->sendNotification("unknown account type {$account->type} // RR");
            }
        }// foreach ($accounts as $account)
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "Authorization" => "Bearer {$token}",
        ];
        $this->http->GetURL("https://ui.bluebird.com/api/me", $this->headers += $headers);
        $response = $this->http->JsonLog();

        if (
            isset($response->username)
            && strtolower($response->username) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers += $headers;

            return true;
        }

        return false;
    }
}
