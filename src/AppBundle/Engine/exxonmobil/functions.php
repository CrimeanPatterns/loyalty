<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerExxonmobil extends TAccountChecker
{
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://exxonandmobilrewardsplus.com/welcome/login';
//    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerExxonmobilSelenium.php";

        return new TAccountCheckerExxonmobilSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
//        $this->http->disableOriginHeader();
        $this->http->setDefaultHeader('Origin', 'https://exxonandmobilrewardsplus.com');
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['token'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $response = $this->loginSuccessful();
        $this->http->RetryCount = 2;

        if ($response) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL(self::REWARDS_PAGE_URL);

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

//        return $this->selenium();

        $keyCaptcha = "undefined";
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=MP_RECAPTCHA_DISABLED');

        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }

        if ($this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"MP_RECAPTCHA_DISABLED\"\s*\,\s*\"value\"\s*:\s*\"false\"\}/")) {
            $keyCaptcha = $this->parseCaptcha();

            if ($keyCaptcha === false) {
                return false;
            }
        }

        $keyCaptchaV3 = 'undefined';
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=RECAPTCHA_V3_DISABLED');

        if ($this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"RECAPTCHA_V3_DISABLED\"\s*\,\s*\"value\"\s*:\s*\"false\"\}/")) {
            $keyCaptchaV3 = $this->parseCaptcha(true);

            if ($keyCaptchaV3 === false) {
                return false;
            }
        }

        // set headers
        $headers = [
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US',
            'Referer'         => self::REWARDS_PAGE_URL,
            'CLM-SESSION-ID'  => $this->generateSessionId(),
        ];
        $data = [
            'client_id'        => $this->AccountFields['Login'],
            'client_secret'    => $this->AccountFields['Pass'],
            'grant_type'       => 'customer_token',
            'scope'            => 'mp_access',
            'reCaptchaToken'   => $keyCaptcha,
            'reCaptchaTokenV3' => $keyCaptchaV3,
        ];
        $this->http->PostURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/oauth/token', $data, $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $this->State['token'] = $response->access_token ?? null;
        $this->State['firstName'] = $response->customerContext->firstName ?? null;
        $this->State['lastName'] = $response->customerContext->lastName ?? null;

        if ($this->State['token'] && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        $globalErrorMessage = $response->globalErrorMessage ?? null;
        $this->logger->error("[Error]: {$globalErrorMessage}");

        if ($globalErrorMessage) {
            if ($globalErrorMessage == 'Invalid client_id or client_secret') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('The email or password you entered is invalid.', ACCOUNT_INVALID_PASSWORD);
            }

            // https://rewards.exxon.com/otp
            // Confirm your identity
            if ($globalErrorMessage == 'One-time password required') {
                $this->AskQuestion("To continue, please enter the verification code from the email we sent you.", null, "Question");

                return false;
            }

            if ($globalErrorMessage == 'The account has been blocked due to exceeded number of unsuccessful login attempts. Please try again after {0} minutes.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException('The account has been blocked due to exceeded number of unsuccessful login attempts.', ACCOUNT_LOCKOUT);
            }

            if ($globalErrorMessage == 'Customer deenrolled') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $globalErrorMessage == 'Invalid Google ReCaptcha token value'
                || $globalErrorMessage == "ReCaptcha V3 validation error"
            ) {
//                $this->captchaReporting(false);//todo
                throw new CheckRetryNeededException(1, 1, self::CAPTCHA_ERROR_MSG); //todo:
            }
        }// if ($globalErrorMessage)

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $keyCaptcha = "undefined";
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=MP_RECAPTCHA_DISABLED');

        if ($this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"MP_RECAPTCHA_DISABLED\"\s*\,\s*\"value\"\s*:\s*\"false\"\}/")) {
            $keyCaptcha = $this->parseCaptcha(false, "https://rewards.exxon.com/otp");

            if ($keyCaptcha === false) {
                return false;
            }
        }

        $keyCaptchaV3 = 'undefined';
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=RECAPTCHA_V3_DISABLED');

        if ($this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"RECAPTCHA_V3_DISABLED\"\s*\,\s*\"value\"\s*:\s*\"false\"\}/")) {
            $keyCaptchaV3 = $this->parseCaptcha(true, "https://rewards.exxon.com/otp");

            if ($keyCaptchaV3 === false) {
                return false;
            }
        }

        // set headers
        $headers = [
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Accept'          => 'application/json, text/plain, */*',
            'Accept-Language' => 'en-US',
            'Referer'         => self::REWARDS_PAGE_URL,
            'CLM-SESSION-ID'  => $this->generateSessionId(),
        ];

        $otp = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $data = [
            'client_id'        => $this->AccountFields['Login'],
            'client_secret'    => $this->AccountFields['Pass'],
            'grant_type'       => 'customer_token',
            'scope'            => 'mp_access',
            'otp'              => $otp,
            'reCaptchaToken'   => $keyCaptcha,
            'reCaptchaTokenV3' => $keyCaptchaV3,
        ];
        $this->http->PostURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/oauth/token', $data, $headers);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $globalErrorMessage = $response->globalErrorMessage ?? null;
        $this->logger->error("[Error]: {$globalErrorMessage}");

        // invalid code
        if ($globalErrorMessage == 'One-time password invalid') {
            $this->captchaReporting($this->recognizer);
            $this->AskQuestion($this->Question, "Oops, looks like your verification code is invalid. Try again.", "Question");

            return false;
        }

        $this->State['token'] = $response->access_token ?? null;
        $this->State['firstName'] = $response->customerContext->firstName ?? null;
        $this->State['lastName'] = $response->customerContext->lastName ?? null;

        if ($this->State['token'] && $this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return false;
    }

    public function Parse()
    {
        // Balance - Points
        $this->SetBalance($this->http->FindPreg('/\{"mainPointBalance"\s*:\s*"([\d\.\,]+)"\}/'));
        // Name
        if (isset($this->State['firstName']) && isset($this->State['lastName'])) {
            $this->SetProperty('Name', beautifulName($this->State['firstName'] . ' ' . $this->State['lastName']));
        }

        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json, text/plain, */*',
            'Referer'       => 'https://exxonandmobilrewardsplus.com/profile/details',
            'Authorization' => 'Bearer ' . $this->State['token'],
        ];
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/customer', $headers);
        $response = $this->http->JsonLog($this->http->FindPreg("/\)\]\}\'\,\s*(.+)/"));
        $firstName = $response->customer->firstName ?? null;
        $lastName = $response->customer->lastName ?? null;

        if ($firstName && $lastName) {
            $this->SetProperty('Name', beautifulName($firstName . ' ' . $lastName));
        }
        // Card number
        $this->SetProperty('CardNumber', $response->customer->cardNo ?? null);
    }

    protected function parseCaptcha($isV3 = false, $url = null)
    {
        $this->logger->notice(__METHOD__);

        if ($isV3 === true) {
//            $key = "6Ldxl8EUAAAAADqWbmglTatrzSQ4ytTMRH07Rndf";
            $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=RECAPTCHA_V3_PUBLIC');
            $key = $this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"RECAPTCHA_V3_PUBLIC\"\s*\,\s*\"value\"\s*:\s*\"([^\']+)\"\}\s*/");
        } else {
            $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/system/public/parameters?codes=MP_RECAPTCHA_PUBLIC');
            $key = $this->http->FindPreg("/\s*\{\"code\"\s*:\s*\"MP_RECAPTCHA_PUBLIC\"\s*\,\s*\"value\"\s*:\s*\"([^\']+)\"\}\s*/");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        /*
        if ($isV3 === true) {
            $postData = [
                "type"       => "RecaptchaV3TaskProxyless",
                "websiteURL" => $url ?? self::REWARDS_PAGE_URL,
                "websiteKey" => $key,
                "minScore"   => 0.9,
                "pageAction" => "login", // https://exxonandmobilrewardsplus.com/main.259c7c870248e7b41467.bundle.js -> grecaptcha.execute(t.ReCaptchaV3Key
            ];
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $this->recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        }
//        */

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $url ?? self::REWARDS_PAGE_URL,
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        if ($isV3 === true) {
            $parameters += [
                "version"   => "v3",
                "action"    => "login", // https://exxonandmobilrewardsplus.com/main.259c7c870248e7b41467.bundle.js -> grecaptcha.execute(t.ReCaptchaV3Key
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json, text/plain, */*',
            'Referer'       => 'https://exxonandmobilrewardsplus.com/welcome/home',
            'Authorization' => 'Bearer ' . $this->State['token'],
        ];
        $this->http->GetURL('https://api-exxon-prod.clm-comarch.com/clm-api-mp/services/customer/account/point-balance', $headers, 20);

        if ($this->http->FindPreg('/"mainPointBalance":/')) {
            return true;
        }

        return false;
    }

    private function generateSessionId()
    {
        $this->logger->notice(__METHOD__);
        $ms = round(microtime(true) * 1000) + 24 * 7 * 60 * 60 * 1e3;
        $data = "{%22key%22:%22crossSessionDataExpirationDate%22%2C%22value%22:{$ms}}";
        $this->http->setCookie("CLM_CROSS_SESSION_DATA_crossSessionDataExpirationDate", $data, "exxonandmobilrewardsplus.com");

        $sessionId = strtoupper(base_convert($ms, 10, 36) . '-' . base_convert(substr((float) rand() / (float) getrandmax(), 2), 10, 36));
        $data = "{%22key%22:%22sessionId%22%2C%22value%22:%22{$sessionId}%22}";
        $this->http->setCookie("CLM_SESSION_DATA_sessionId", $data, "exxonandmobilrewardsplus.com");

        return $sessionId;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Break Service. Please try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    /*
    private function selenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $postData = null;
        $this->http->brotherBrowser($selenium->http);
        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox();
//            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL("https://exxonandmobilrewardsplus.com/welcome/login");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 2);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $this->saveToLogs($selenium);
            if (!$loginInput || !$passwordInput) {
                return false;
            }
//            $loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);

            $selenium->driver->executeScript("
                localStorage.clear();
                var jq = document.createElement('script');
                jq.src = \"https://ajax.googleapis.com/ajax/libs/jquery/1.8/jquery.js\";
                document.getElementsByTagName('head')[0].appendChild(jq);
            ");
            $captcha = $this->parseCaptcha(true);
            sleep(3);
            if ($captcha === false) {
                return false;
            }

            $selenium->driver->executeScript("
            $.ajax({
                url: 'https://api-exxon-prod.clm-comarch.com/clm-api-mp/oauth/token',
                type: 'POST',
                data: {
                    client_id: '{$this->AccountFields["Login"]}',
                    client_secret: '{$this->AccountFields["Pass"]}',
                    grant_type: 'customer_token',
                    scope: 'mp_access',
                    reCaptchaToken: 'undefined',
                    reCaptchaTokenV3: '{$captcha}',
                },
                dataType: 'json',
                beforeSend: function (request) {
                    request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
                    request.setRequestHeader('Accept', 'application/json, text/plain, *
    /*');
                    request.setRequestHeader('Accept-Language', 'en-US');
                },
                success: function (data) {
                    console.log('Success captcha: ' + JSON.stringify(data));
                    localStorage.setItem('response', JSON.stringify(data));
                },
                error: function (data, textStatus, error) {
                    console.log('Error captcha: ' + JSON.stringify(error));
                    localStorage.setItem('responseError', JSON.stringify(error));
                    console.log('Error captcha: ' + JSON.stringify(data));
                    localStorage.setItem('responseCaptchaData', JSON.stringify(data));
                }
            });
            ");
            $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "button_logout"] | //div[@id = "login_error_message"]'), 5);
            $this->saveToLogs($selenium);
            $response = $selenium->driver->executeScript("return localStorage.getItem('response');");
            $this->logger->info("[Form response]: " . $response);

            $responseError = $selenium->driver->executeScript("return localStorage.getItem('responseError');");
            $this->logger->info("[Form responseError]: " . $responseError);
            $responseCaptchaData = $selenium->driver->executeScript("return localStorage.getItem('responseCaptchaData');");
            $this->logger->info("[Form responseCaptchaData]: " . $responseCaptchaData);
            $this->logger->debug(var_export($responseCaptchaData, true), ["pre" => true]);

            $cookies = $selenium->driver->manage()->getCookies();
            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
            }

            $this->saveToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer'))
                $retry = true;
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $postData;
    }

    private function saveToLogs($selenium) {
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }
    */
}
