<?php

class TAccountCheckerDcard extends TAccountChecker
{
    use \AwardWallet\Engine\ProxyList;

    private $headers = [
        "Accept"          => "application/json",
        "Accept-Encoding" => "gzip, deflate, br",
        "Content-Type"    => "application/json",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->setProxyNetNut(null, 'de', 'https://www.deutschlandcard.de');
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.deutschlandcard.de");

        if ($this->http->Response['code'] != 200) {
            if (
                $this->http->Response['code'] == 403
                || strstr($this->http->Error, 'Network error 56 - Received HTTP code 429 from proxy after CONNECT')
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }

        /*
        if (stripos($this->http->Response['body'], '.grecaptcha-badge') !== false) {
            $captcha = $this->parseReCaptcha('6Le0YYIUAAAAACJ4YE6IYRq6KQ74htMtQZmvDVQP');
            if ($captcha !== false) {
                $this->headers['x-recaptcha-v2'] = $captcha;
            }
        }
        */
        $captcha = $this->parseReCaptcha('6Le2S9AUAAAAAD4SMzwje15-swuVJtwV9O1HyL9T', true);

        if ($captcha !== false) {
            $this->headers['x-recaptcha-v3'] = $captcha;
        }

        $data = [
            "grant_type"    => "password",
            "response_type" => "id_token token",
            "scope"         => "deutschlandcardapi offline_access",
            "audience"      => "deutschlandcardapi",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
        ];
        $this->http->RetryCount = 0;
        $this->headers["Authorization"] = "Bearer undefined";
        $this->http->PostURL("https://www.deutschlandcard.de/api/v1/auth/connect/token", json_encode($data), $this->headers);

        if ($this->http->BodyContains('502 Bad Gateway', false)) {
            sleep(5);
            $this->http->PostURL("https://www.deutschlandcard.de/api/v1/auth/connect/token", json_encode($data), $this->headers);
        }
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            ($this->http->Response['code'] == 503 && $this->http->FindPreg("/^Service Unavailable$/"))
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $token = $response->access_token ?? null;

        if ($token) {
            $this->State['token'] = $token;
            $this->captchaReporting($this->recognizer);

            return $this->loginSuccessful();
        }

        $message = $response->error_description ?? $response->data->error_description ?? $response->data->message ?? null;
        $botDetect = $response->data->{'bot-detection-error'} ?? null;

        if ($message) {
            $this->logger->error("[Error]: '$message'");
            $this->logger->error("[bot-detection-error]: '{$botDetect}'");

            if ($message == 'invalid credential' && !$botDetect) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Bitte überprüfen Sie die Eingabe", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Request failed with status code 401' && !$botDetect) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException("Bitte überprüfe deine Eingabe.", ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, '<title>504 Gateway Time-out</title>') && !$botDetect) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'invalid credential'
                && in_array($botDetect, [
                    'InvalidScoreCaptchaV3',
                    'InvalidCaptchaV3Token',
                ])
            ) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(3, 3, self::CAPTCHA_ERROR_MSG);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty("Name", beautifulName($response->firstname . " " . $response->lastname));

        $this->http->GetURL("https://www.deutschlandcard.de/api/v1/profile/memberpoints", $this->headers);
        $response = $this->http->JsonLog();
        // Balance - Punkte
        $this->SetBalance($response->balance ?? null);

        if (isset($response->expiringPoints) && $response->expiringPoints > 0) {
            $this->SetProperty("ExpiringBalance", $response->expiringPoints);
            $this->SetExpirationDate(strtotime($response->dateOfNextExpiry));
        }
    }

    protected function parseReCaptcha($key = null, $isV3 = false)
    {
        $this->logger->notice(__METHOD__);
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

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                "action"    => "login",
                "min_score" => 0.9,
            ];

            $postData = [
                "type"         => "RecaptchaV3TaskProxyless",
                "websiteURL"   => $currentURL ?? $this->http->currentUrl(),
                "websiteKey"   => $key,
                "minScore"     => 0.9,
                "pageAction"   => "login",
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }

//        $postData = array_merge(
//            [
//                "type"       => "NoCaptchaTaskProxyless",
//                "websiteURL" => $currentURL ?? $this->http->currentUrl(),
//                "websiteKey" => $key,
//            ],
//            []
//        );
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->headers["Authorization"] = "Bearer {$this->State['token']}";
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.deutschlandcard.de/api/v1/profile/memberinfo", $this->headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (!empty($response->lastname)) {
            return true;
        }

        return false;
    }
}
