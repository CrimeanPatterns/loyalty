<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAnz extends TAccountChecker
{
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizerV3;

    private $clientID = "dd0431f3-e1e7-4185-ac1c-13a1a10bc2cb";

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid Email.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid Email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://auth.anzrewards.com/login?client_id={$this->clientID}&connection=password&state=e792cb26-0422-47a6-8ad8-08f9d90a4c7f&scope=openid,address,email,phone,profile,custom&redirect_uri=https://www.anzrewards.com&response_type=id_token,token");

        $csrf = $this->http->FindSingleNode('//meta[@name = "csrf-token"]/@value');

        if (!$csrf) {
            return $this->checkErrors();
        }

        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $captcha_v3 = $this->parseReCaptchaV3();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "credentials"       => [
                "uid"        => $this->AccountFields['Login'],
                "password"   => $this->AccountFields['Pass'],
                "rememberMe" => true,
            ],
            "_csrf_token"       => $csrf,
            "_captcha_v2_token" => $captcha,
            "_captcha_v3_token" => $captcha_v3,
            "client_id"         => $this->clientID,
        ];

        $headers = [
            "Accept"          => "application/json, text/plain, */*",
            "Content-Type"    => "application/json",
            "Alt-Used"        => "auth.anzrewards.com",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://auth.anzrewards.com/auth/email/callback", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->redirect_uri)) {
            $this->http->GetURL($response->redirect_uri);

            $state = $this->http->FindPreg("/state=([^&]+)/", false, $response->redirect_uri);

            if ($state) {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);
                $this->http->GetURL("https://auth.anzrewards.com/authorize?response_type=web_message&state={$state}&client_id={$this->clientID}&hermes_version=2.0.2");

                if ($access_token = $this->http->FindPreg("/access_token\":\"([^\"]+)/")) {
                    $this->State['headers'] = [
                        "X-Access-Token" => $access_token,
                        "X-Force-Locale" => "en-AU",
                        "X-RD-Local-Preferences", "points_account_id=" . $this->http->FindPreg("/points_account_id\":\"([^\"]+)/"),
                    ];

                    return $this->loginSuccessful();
                }
            }
        }

        $message = $response->errors[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (in_array($message, [
                "Invalid credentials.",
                "Please reset your password to continue",
                "Expired password. A reset password instruction was sent to your email",
            ])
            ) {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Account closed for user') {
                $this->captchaReporting($this->recognizer);
                $this->captchaReporting($this->recognizerV3);

                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $response = $this->http->JsonLog(null, 0);
        $this->SetProperty("Name", beautifulName($response->first_name . " " . $response->last_name));

        $this->http->GetURL("https://anz-nn.kaligo.com/points_accounts?active=false", $this->State['headers']);
        $response = $this->http->JsonLog();
//        $this->http->GetURL("https://anz-nn.kaligo.com/points_summary", $this->State['headers']);
//        $response = $this->http->JsonLog();

        // IsLoggedIn issue
        if (!$response && strstr($this->http->Response['body'], 'Unauthorized')) {
            throw new CheckRetryNeededException(2, 0);
        }

        // Balance - ANZ Rewards Black: ... Reward Points
        $this->SetBalance(floor($response->data[0]->attributes->pointsBalance));

        $tranches = $response->data[0]->attributes->tranches ?? [];

        foreach ($tranches as $tranch) {
            $date = $tranch->expiryDate;

            if (!isset($exp) || strtotime($date) < $exp) {
                $exp = strtotime($date);
                // Reward Points Expiring 31/12/..
                $this->SetProperty("ExpiringBalance", floor($tranch->balance));
                // Expiration Date
                $this->SetExpirationDate($exp);
            }
        }// foreach ($tranches as $tranch)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = "6LfTa2QaAAAAABMBZPJ2but6p-s3B9BFdpni9D3I";

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    protected function parseReCaptchaV3()
    {
        $this->logger->notice(__METHOD__);
        $key = "6Lerp0AcAAAAAD_HGryPRVMwJXD3LvMoi81xtPtS";

        if (!$key) {
            return false;
        }

        $this->recognizerV3 = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizerV3->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
            "version"   => "v3",
            "action"    => "login",
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizerV3, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL('https://auth.anzrewards.com/current_user');
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (!empty($email) && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }
}
