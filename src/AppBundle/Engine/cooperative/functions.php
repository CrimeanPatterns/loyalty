<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCooperative extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://membership.coop.co.uk/dashboard';
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && $properties['Currency'] == '£') {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "£%0.2f");
        }// if (isset($properties['Currency']) && $properties['Currency'] == '£')

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = true;
//        $this->http->SetProxy($this->proxyUK());
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
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

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // Check you've typed your email correctly - it must be a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Check you've typed your email correctly - it must be a valid email address.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL("https://membership.coop.co.uk/sign-in");
        $state = $this->http->FindPreg('/state=(.+?)&/', false, $this->http->currentUrl());
        $clientId = $this->http->FindPreg('/client_id=(.+?)&/', false, $this->http->currentUrl());
        $url = $this->http->currentUrl();

        if (!$this->http->ParseForm("sign-in-form")) {
            if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')) {
                throw new CheckRetryNeededException(3, 0);
            }

            if (
                $this->http->Response['code'] == 403
                && $this->http->FindPreg("/We can't connect to the server for this app or website at this time\./")
            ) {
                throw new CheckRetryNeededException(2, 3);
            }

            return $this->checkErrors();
        }
        $captchaKey = $this->http->FindPreg('/enterprise\.js\?render=(.+?)">/');
        $captcha = $this->parseReCaptcha($captchaKey, $url);

        if ($captcha === false) {
            return false;
        }
        $data = [
            "email"        => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "staySignedIn" => true,
            "state"        => $state,
            "clientId"     => $clientId,
        ];
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json",
            "Authorization"   => $captcha,
            "Origin"          => "https://account.coop.co.uk",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.coop.co.uk/account/sign-in", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, this service isn't working
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, this service isn\'t working")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $error =
            $response->errorMessage
            ?? $response->message
            ?? null;
        $location = $response->location ?? null;
        // Error
        if ($error) {
            $this->logger->error($error);

            if ($error == "Incorrect credentials.") {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Your email or password aren’t correct. Check you’ve typed them correctly and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $error == "Account has been locked for security reasons."
                || $error == "Account has been locked due to inactivity."
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($error, ACCOUNT_LOCKOUT);
            }

            if ($error == 'Unauthorized' && $this->http->Response['code'] == 401) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }
        //go to dashboard
        if ($location) {
            $this->http->RetryCount = 0;
            $this->http->GetURL($location);
            $this->http->RetryCount = 2;

            if ($this->loginSuccessful()) {
                $this->captchaReporting($this->recognizer);

                return true;
            }
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        if (!$this->http->FindPreg('#coop.co.uk/dashboard#', false, $this->http->currentUrl())) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // You have ... to spend
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(normalize-space(.), 'You have')]", null, true, '/[^\d]?([\d.,]+)p?\s+to spend/'));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, you can\'t see your reward balance just now. Try again later.")]')) {
                $this->SetWarning($message);
            }
        }
        // Currency
        $this->SetProperty('Currency', $this->http->FindSingleNode("//h2[contains(normalize-space(.), 'You have')]", null, true, '/(£)([\d.,]+)\s+to spend/'));
        // Name
        $this->SetProperty('Name', $this->http->FindSingleNode('//h1[contains(text(), "Welcome to your account,")]', null, true, "/account,\s*(.+)/"));

        $this->http->GetURL('https://membership.coop.co.uk/your-points');
        $this->SetProperty('PointsThisYear', $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'You currently have')]", null, true, '/have ([\d.,]+) points/'));

        // There is no balance and the program may need to close on December 31, 2024.
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (isset($this->Properties['PointsThisYear']) || $this->http->FindSingleNode('//p[contains(text(), "We can\'t currently display your points.")]'))
            && !empty($this->Properties['Name'])
        ) {
            $this->SetBalanceNA();
        }
    }

    protected function parseReCaptcha($key, $currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");
        /*
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => isset($currentUrl) ? $currentUrl : $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
        ];
        /*
        $parameters += [
            "version"   => "v3",
            "action"    => "SignIn",
            "min_score" => 0.9,
        ];
        * /
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        */

        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $currentUrl ?? $this->http->currentUrl(),
            "websiteKey"   => $key,
            "minScore"     => 0.9,
            "pageAction"   => "login",
            "isEnterprise" => true,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('//button[contains(text(), "Sign out") or @data-linktext="Sign out"]')
            && $this->http->FindSingleNode('//h1[contains(text(),"Welcome to your account")]')
        ) {
            return true;
        }

        return false;
    }
}
