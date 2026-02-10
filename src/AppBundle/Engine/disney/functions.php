<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class TAccountCheckerDisney extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->disableOriginHeader();
        //$this->http->SetProxy($this->proxyReCaptcha());
        $this->http->SetProxy($this->proxyDOP(array_merge(Settings::DATACENTERS_USA, Settings::DATACENTERS_NORTH_AMERICA)));
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization']) || !isset($this->State['SWID'])) {
            return false;
        }
        $headers = [
            "Authorization" => "BEARER {$this->State['Authorization']}",
            "SWID"          => str_replace(['{', '}'], '', $this->State['SWID']),
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/guest/{$this->State['SWID']}?feature=always-get-entitlements&expand=profile%2Cdisplayname%2Clinkedaccounts%2Cmarketing%2Centitlements%2Cs2&langPref=en-US", $headers, 20);
        $this->http->RetryCount = 2;
        $headers = [
            "Authorization" => $this->State['Authorization'],
            "ClientId"      => "STUDIO-DMR2.0.WEB",
            "SWID"          => str_replace(['{', '}'], '', $this->State['SWID']),
        ];

        if ($this->loginSuccessful($headers)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->GetURL("https://www.disneymovieinsiders.com/");

        if (!$this->http->FindNodes("//button[contains(text(), 'SIGN IN') or contains(text(), 'Enter Code')]")) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode('//img[@alt="Maintenance"]/@alt')) {
            throw new CheckException("Site down for maintenance", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->seleniumAuth();

//        $this->http->GetURL('https://log.go.com/log?action_name=api%3Alaunch%3Alogin&anon=true&appid=DTSS-DISNEYID-UI&client_id=STUDIO-DMR2.0.WEB-PROD&conversation_id=ef495a62-791b-4e2b-a63b-1798cc243d18&correlation_id=b744e44a-9ca0-41f3-8dff-9abfd1a77458&info=tabId(6f6484f6-0461-44dd-aabb-4d1fccf740b6)&os=Mac%20OS%2010.14&process_time=124&reporting=context%2Csource&sdk_version=Web%202.54.2&success=true&swid=c85084ce-1d2b-4818-9cf4-fb61dca01f23&timestamp=2019-10-03T08%3A35%3A22.434Z');

        $this->http->PostURL('https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/api-key?langPref=en-US', []);
        $apiKey = ArrayVal($this->http->Response['headers'], 'api-key');
        $correlationId = ArrayVal($this->http->Response['headers'], 'correlation-id', null);
        $conversationId = ArrayVal($this->http->Response['headers'], 'conversation-id', null);

        $this->http->GetURL('https://cdn.registerdisney.go.com/v2/STUDIO-DMR2.0.WEB-PROD/en-US?include=config,l10n,js,html&?clientID=STUDIO-DMR2.0.WEBscheme=https&postMessageOrigin=https%3A%2F%2Fwww.disneymovieinsiders.com%2F&cookieDomain=www.disneymovieinsiders.com&config=PROD&logLevel=INFO&topHost=www.disneymovieinsiders.com&ageBand=ADULT&countryCode=US&cssOverride=https%3A%2F%2Fdash.media.vr.disney.go.com%2Fnotoken%2Fdmi%2Fsite%2Fdmi_override.css&responderPage=https%3A%2F%2Fwww.disneymovieinsiders.com%2Fresponder&buildId=1764e2f9438');

        // enterprise 6Ldj8_cZAAAAAIWf3G6WwzMFHgoSL3lyYofbqjQL
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "loginValue" => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Content-Type"      => "application/json",
            "Accept"            => "*/*",
            'authorization'     => sprintf('APIKEY %s', $apiKey),
            'g-recaptcha-token' => $captcha,
            "correlation-id"    => $correlationId ?? $this->gen_uuid(),
            "conversation-id"   => $conversationId ?? $this->gen_uuid(),
            "oneid-reporting"   => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
            "device-id"         => 'null',
            "expires"           => -1,
            "Origin"            => "https://cdn.registerdisney.go.com",
            "Referer"           => '',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/guest/login?langPref=en-US", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function gen_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            // 16 bits for "time_mid"
            mt_rand(0, 0xffff),
            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand(0, 0x0fff) | 0x4000,
            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand(0, 0x3fff) | 0x8000,
            // 48 bits for "node"
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message =
            $this->http->FindSingleNode('//p[contains(text(), "We\'re still working on the Disney Movie Insiders program to ensure a magical experience.")]')
            ?? $this->http->FindSingleNode('//img[@alt = "Disney Movie Insiders is currently under maintenance and will be back shortly. Thank you for your patience!"]/@alt')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, 5);
        // Access is allowed
        if (!empty($response->data->token->access_token) /*&& $this->http->Response['code'] == 200*/) {
            $this->captchaReporting($this->recognizer);
            $headers = [
                "Authorization" => $response->data->token->access_token,
                "ClientId"      => "STUDIO-DMR2.0.WEB",
                "SWID"          => str_replace(['{', '}'], '', $response->data->token->swid),
            ];

            if (!$this->loginSuccessful($headers)) {
                return false;
            }
            $this->State['Authorization'] = $response->data->token->access_token;
            $this->State['SWID'] = $response->data->token->swid;

            return true;
        }
        // AccountID: 4812976
        if (
            isset($response->error->keyCategory)
            && !empty($response->data->token->access_token)
            /*&& $this->http->Response['code'] == 400*/
            && $response->error->keyCategory == 'PPU_ACTIONABLE_INPUT'
            && $this->http->FindPreg('/\{"code":"(?:MISSING_VALUE|PPU_LEGAL)","category":"PPU_ACTIONABLE_INPUT","inputName":"(?:profile.addresses|GTOU)/')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        // 500 site error
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"SYSTEM_UNAVAILABLE\",\"category\":\"SYSTEM_UNAVAILABLE\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // The credentials you entered are incorrect. Reminder: passwords are case sensitive.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"AUTHORIZATION_CREDENTIALS\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            // wrong captcha answer
            if ($this->http->FindPreg("/\"data\":\{\"type\":\"GenericReasonCodeErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\"\}/")) {
                $this->logger->error("wrong captcha answer");
                $this->captchaReporting($this->recognizer, false);

                return false;
            }

            // We sent a code to ... . Enter the code here to continue.
            $assessmentId = $response->error->errors[0]->data->assessmentId ?? null;

            if (
                $this->http->FindPreg("/\"data\":\{\"type\":\"GenericErrorData\",\"reasonCode\":\"PALOMINO_CHECK_FAILED\",\"assessmentId/")
                && $assessmentId
            ) {
                $this->logger->notice("2fa");
                $this->captchaReporting($this->recognizer);

                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }

                $this->parseQuestion($assessmentId);

                return false;
            }

            $this->captchaReporting($this->recognizer);

            throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/"errors":\[\{"code":"AUTHORIZATION_ACCOUNT_LOCKED_OUT","category":"FAILURE_CONTACT_CSR"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("You've tried too many passwords. Try again later, or get a temporary password.", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg('/Could not authorize the given credentials/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The credentials you entered are incorrect. Reminder: passwords are case sensitive.", ACCOUNT_INVALID_PASSWORD);
        }
        // It looks like multiple accounts are associated with that email address.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"AUTHORIZATION_MASE_ERROR\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("It looks like multiple accounts are associated with that email address. We'd like to send you a message with instructions to help you choose your primary Disney account.", ACCOUNT_INVALID_PASSWORD);
        }
        // You are not eligible to register on this site. Your information has not been collected.
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"GUEST_GATED_AGEBAND\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("You are not eligible to register on this site. Your information has not been collected.", ACCOUNT_INVALID_PASSWORD);
        }
        // Account Deactivated
        if ($this->http->FindPreg('/"errors\":\[\{\"code\":\"PROFILE_DISABLED\",\"category\":\"FAILURE_BY_DESIGN\"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Account Deactivated", ACCOUNT_PROVIDER_ERROR);
        }
        // We've detected a problem with your account, and need you to reset your password to continue. Please check your email for instructions.
        if ($this->http->FindPreg('/"errors":\[\{"code":"AUTHORIZATION_ACCOUNT_SECURITY_LOCKED_OUT","category":"(?:FAILURE_BY_DESIGN|GUEST_BLOCKED)"/')
            || $this->http->FindPreg('/"errors":\[\{\"code\":\"AUTHORIZATION_INVALID_OR_EXPIRED_TOKEN\",\"category\":\"FAILURE_BY_DESIGN\"/')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("We've detected a problem with your account, and need you to reset your password to continue. Please check your email for instructions.", ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindPreg('/"errors":\[\{"code":"ACCOUNT_INACTIVE_VERIFICATION_REQUIRED","category":"FAILURE_BY_DESIGN"/')) {
            // Broken response, intended to start 2fa but does not contain assessmentId. Seems to appear on next account check after failed 2fa.
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException();
        }

        if ($this->http->FindPreg('/"errors":\[\{"code":"DEVICE_LINK_LOCKOUT_REACHED","category":"GUEST_BLOCKED"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Sorry, we are unable to send you any more codes right now. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function parseQuestion($assessmentId)
    {
        $apiKey = ArrayVal($this->http->Response['headers'], 'api-key', $this->apiKey ?? '');
        $correlationId = ArrayVal($this->http->Response['headers'], 'correlation-id', null);
        $conversationId = ArrayVal($this->http->Response['headers'], 'conversation-id', null);
        $this->http->RetryCount = 0;

        $data = [
            "loginValue" => $this->AccountFields['Login'],
        ];
        $headers = [
            "Accept"          => "*/*",
            "Content-Type"    => "application/json",
            'authorization'   => sprintf('APIKEY %s', $apiKey),
            "correlation-id"  => $correlationId ?? $this->gen_uuid(),
            "conversation-id" => $conversationId ?? $this->gen_uuid(),
            "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
            "device-id"       => 'null',
            "expires"         => -1,
            "Origin"          => "https://cdn.registerdisney.go.com",
            "Referer"         => 'https://cdn.registerdisney.go.com/',
        ];

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/guest/recovery-methods?langPref=en-US", json_encode($data), $headers);
        $response = $this->http->JsonLog(null, 3, false, 'mask');
        $email = $response->data->recoveryMethods[0]->mask ?? null;

        if (!$email) {
            $this->logger->error("email not found");

            // We're sorry, the account system is having a problem.
            if ($this->http->FindPreg("/\"errors\":\[\{\"code\":\"INVALID_VALUE\",\"category\":\"ACTIONABLE_INPUT\",\"inputName\":\"loginValue\",/")) {
                throw new CheckException("We're sorry, the account system is having a problem.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $question = "We sent a code to {$email}. Enter the code here to continue.";

        $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key'));
        $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $this->gen_uuid();
        $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $this->gen_uuid();

        $data = [
            "lookupValue" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/notification/otp/recovery?langPref=en-US", json_encode($data), $headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $sessionId = $response->data->sessionId ?? null;

        if (!$sessionId) {
            $this->logger->error("something went wrong");

            return false;
        }

        $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key'));
        $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $this->gen_uuid();
        $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $this->gen_uuid();

        $this->State['2faHeaders'] = $headers;
        $this->State['2faData'] = [
            "passcode"     => "",
            "sessionIds"   => [
                $sessionId,
            ],
            "assessmentId" => $assessmentId,
        ];

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->State['2faData']["passcode"] = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        $this->http->RetryCount = 0;
        $this->http->PostURL("https://registerdisney.go.com/jgc/v6/client/STUDIO-DMR2.0.WEB-PROD/otp/redeem?langPref=en-US", json_encode($this->State['2faData']), $this->State['2faHeaders']);
        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog();
        $code = $response->error->errors[0]->code ?? null;

        // The code you entered is incorrect or expired.
        if ($code === 'DEVICE_LINK_INVALID_OTP_CODE') {
            $this->AskQuestion($this->Question, "The code you entered is incorrect or expired.", "Question");

            return false;
        }

        if (!empty($response->data->access_token) /*&& $this->http->Response['code'] == 200*/) {
            $this->captchaReporting($this->recognizer);
            $headers = [
                "Authorization" => $response->data->access_token,
                "ClientId"      => "STUDIO-DMR2.0.WEB",
                "SWID"          => str_replace(['{', '}'], '', $response->data->accountRecoveryProfiles[0]->swid),
            ];

            if (!$this->loginSuccessful($headers)) {
                return false;
            }
            $this->State['Authorization'] = $response->data->access_token;
            $this->State['SWID'] = $response->data->accountRecoveryProfiles[0]->swid;

            return true;
        }

        return false;
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Balance - Points
        $this->SetBalance($response->redeemable_points ?? null);
        // Name
        if (!empty($response->last_name)) {
            $this->SetProperty("Name", beautifulName($response->first_name . " " . $response->last_name));
        }

        // Expiration date     // refs #3883
        $this->logger->info('Expiration date', ['Header' => 3]);

        if (!$this->Balance) {
            return;
        }
        $this->http->GetURL("https://api.disneymovieinsiders.com/user/points-history?t=" . date("UB"));
        $historyResponse = $this->http->JsonLog();

        foreach ($historyResponse as $row) {
            if (isset($exp) && $row->date_awarded <= $exp) {
                continue;
            }
            $exp = $row->date_awarded;
            $this->SetProperty("LastActivity", date("m/d/Y", $exp));
            $this->SetExpirationDate(strtotime("+1 year", $exp));
        }// foreach ($historyResponse as $row)
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg('/"recaptchaV3Enabled":true_,"recaptchaV3SiteKey":"([^"]+)/');
        $action = 'homepage';

        if (!$key) {
            $action = 'login';
            $key = $this->http->FindPreg('/"recaptchaV4Config":\{"default":\{"reCaptchaEnabled":true,"reCaptchaSiteKey":"([^"]+)/');
        }

        $this->logger->debug("data-sitekey: {$key}");
        $this->logger->debug("action: {$action}");

        if (!$key) {
            return false;
        }

        /*
        $postData = [
            "type"       => "RecaptchaV3TaskProxyless",
            "websiteURL" => "https://www.disneymovieinsiders.com/", //$this->http->currentUrl(),
            "websiteKey" => $key,
            "minScore"   => 0.9,
            "pageAction" => $action,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => "https://www.disneymovieinsiders.com/",
            "proxy"     => $this->http->GetProxy(),
            "version"   => "v3",
            "action"    => $action,
            "min_score" => 0.3,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($headers)
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $profile =
            $response['data']['profile']
            ?? $response['data']['accountRecoveryProfiles'][0]
            ?? null
        ;
        $this->SetProperty("Name", beautifulName(ArrayVal($profile, 'firstName') . " " . ArrayVal($profile, 'lastName')));

        if (!isset($this->Properties['Name']) || trim($this->Properties['Name']) == '') {
            $this->logger->error("Name not found");

            return false;
        }

        /*
        $needToUpdateAccount = false;
        // Please Update Your Account
        if ($this->http->FindPreg('/\{\"code\":\"PPU_LEGAL\",\"category\":\"PPU_ACTIONABLE_INPUT\"/'))
            $needToUpdateAccount = true;
        */

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.disneymovieinsiders.com/user?t=" . date("UB"), $headers);
        $response = $this->http->JsonLog();
        $email = $response->email_address ?? null;
        $username = $response->username ?? null;
        $firstName = $response->first_name ?? null;
        $lastName = $response->last_name ?? null;

        if (
            !in_array(strtolower($this->AccountFields['Login']), [$email, $username])
            && trim($firstName . " " . $lastName) != trim($this->Properties['Name'])
            && trim($firstName) != trim($this->Properties['Name'])
        ) {
            $this->logger->error("may be wrong data");
            $this->logger->error("[login]: {$this->AccountFields['Login']}");
            $this->logger->error("[email]: {$email}");
            $this->logger->error("[username]: {$username}");
            $this->logger->error("[first_name]: {$firstName}");
            $this->logger->error("[last_name]: {$lastName}");

            if (isset($response->message) && $response->message == 'User does not exist.') {
                $this->http->GetURL("https://api.disneymovieinsiders.com/user/vppa-status?t=" . date("UB"), $headers);
                $response = $this->http->JsonLog();

                if (isset($response->vppa_accepted) && $response->vppa_accepted === false) {
                    $this->throwAcceptTermsMessageException();
                }
            }

            return false;
        }

        foreach ($headers as $name => $value) {
            $this->http->setDefaultHeader($name, $value);
        }

        return true;
    }

    private function seleniumAuth()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
//            $selenium->useFirefox();
//            $selenium->setKeepProfile(true);
            $selenium->useGoogleChrome();
            //$selenium->disableImages();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;
            $selenium->driver->manage()->window()->maximize();
            $selenium->http->GetURL("https://www.disneymovieinsiders.com/");
            $btn = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(@data-cy, 'dmi-signin-button')]"), 7);
            $this->savePageToLogs($selenium);

            if ($btn) {
                $this->logger->info("login form loaded");
                $btn->click();

                $iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'disneyid-iframe' or @id = 'oneid-iframe']"), 30);
                $this->savePageToLogs($selenium);

                if (!$iframe) {
                    $this->logger->error('no iframe');

                    return $this->checkErrors();
                }

                $selenium->driver->switchTo()->frame($iframe);

                $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Email"]'), 7);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);

                if (!$loginInput || !$button) {
                    return $this->checkErrors();
                }

                $loginInput->sendKeys($this->AccountFields['Login']);
                $button->click();

                $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder = "Password"]'), 8);
                $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "BtnSubmit"]'), 0);
                // save page to logs
                $this->savePageToLogs($selenium);

                if (!$passwordInput || !$button) {
                    if ($message = $this->http->FindSingleNode('//*[@id = "InputIdentityFlowValue-error"]')) {
                        $this->logger->error("[Error]: {$message}");

                        if (
                            $message == 'Please enter a valid email address.'
                            || strstr($message, 'This email isn\'t properly formatted.')
                        ) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->DebugInfo = $message;

                        return false;
                    }

                    if ($this->http->FindSingleNode('//h1[span[contains(text(), "Create Your Account")]]')) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    return $this->checkErrors();
                }

                $passwordInput->sendKeys($this->AccountFields['Pass']);

                $selenium->driver->executeScript('
                let oldXHROpen = window.XMLHttpRequest.prototype.open;
                window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                    this.addEventListener("load", function() {
                        if (/"access_token":|"errors\":\[\{"code":/g.exec( this.responseText )) {
                            localStorage.setItem("responseData", this.responseText);
                            localStorage.setItem("stolenAPIKey", this.getResponseHeader("api-key"));
                        }
                        
                        if (/"sessionId":/g.exec( this.responseText )) {
                            localStorage.setItem("responseDataQuestion", this.responseText);
                        }
                    });
                    return oldXHROpen.apply(this, arguments);
                };
                
                (function(fn){
                    XMLHttpRequest.prototype.setRequestHeader = function (...args) {
//                        console.log(args);
                        if (args[0] == "BM-Telemetry") {
                            localStorage.setItem(\'BM-Telemetry\', args[1]);
                        }
                        fn.call(this, ...args);
                    }
                })(XMLHttpRequest.prototype.setRequestHeader);
                ');

//                try {
//                    $executor = $selenium->getPuppeteerExecutor();
//                    $json = $executor->execute(
//                        __DIR__ . '/puppeteer.js'
//                    );
//                } catch (Exception $e) {
//                    $this->logger->error("Exception: " . $e->getMessage());
//                    $retry = true;
//
//                    return false;
//                }
                $button->click();
//                $this->http->JsonLog(json_encode($json));
//                $apiKey = ArrayVal($json['headers'], 'api-key');
//                $apiKey = 'PzdSBxxqRqGWCDnRPfcPw7BegQhw156xxksMtQ3Hav/SFnW7tFCH96X+IfTgTOwr9iKlo/hCFLjVjRf1h+x54XaYCxhlTg==';

                sleep(4);
                $apiKey = $selenium->driver->executeScript("return localStorage.getItem('stolenAPIKey');");
                $this->logger->debug("api-key: {$apiKey}");
                $this->apiKey = $apiKey;
                $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
                $this->logger->info("[Form responseData]: " . $responseData);

                $responseDataQuestion = $selenium->driver->executeScript("return localStorage.getItem('responseDataQuestion');");
                $this->logger->info("[Form responseDataQuestion]: " . $responseDataQuestion);

                $selenium->driver->switchTo()->defaultContent();
                $this->savePageToLogs($selenium);
                $selenium->waitForElement(WebDriverBy::xpath('//div/p[contains(text(), "Hi ")]'), 5);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }

                if (
                    !empty($responseData)
                    && (isset($responseData->data->token->access_token) || !empty($responseDataQuestion))
                ) {
                    if ($questionObject = $selenium->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We sent a code to')]"), 0)) {
                        $question = $questionObject->getText();
                        $error = ArrayVal($this->http->JsonLog($responseData, 5, true), 'error');
                        $assessmentId = $error['errors'][0]['data']['assessmentId'] ?? null;
                        $responseDataQuestion = $this->http->JsonLog($responseDataQuestion);
                        $sessionId = $responseDataQuestion->data->sessionId ?? null;

                        if (!$sessionId) {
                            $this->logger->error("something went wrong, sessionId not found");

                            return false;
                        }

                        $headers = [
                            "Accept"          => "*/*",
                            "Content-Type"    => "application/json",
                            "correlation-id"  => $correlationId ?? $this->gen_uuid(),
                            "conversation-id" => $conversationId ?? $this->gen_uuid(),
                            "oneid-reporting" => 'eyJjb250ZXh0IjoiIiwic291cmNlIjoiIn0=',
                            "device-id"       => 'null',
                            "expires"         => -1,
                            "Origin"          => "https://cdn.registerdisney.go.com",
                            "Referer"         => 'https://cdn.registerdisney.go.com/',
                        ];

                        $headers['authorization'] = sprintf('APIKEY %s', ArrayVal($this->http->Response['headers'], 'api-key', $apiKey));
                        $headers["correlation-id"] = ArrayVal($this->http->Response['headers'], 'correlation-id', null) ?? $this->gen_uuid();
                        $headers["conversation-id"] = ArrayVal($this->http->Response['headers'], 'conversation-id', null) ?? $this->gen_uuid();

                        $this->State['2faHeaders'] = $headers;
                        $this->State['2faData'] = [
                            "passcode"     => "",
                            "sessionIds"   => [
                                $sessionId,
                            ],
                            "assessmentId" => $assessmentId,
                        ];

                        $this->Question = $question;
                        $this->ErrorCode = ACCOUNT_QUESTION;
                        $this->Step = "Question";

                        return false;
                    }

                    if ($message = $this->http->FindSingleNode('//h2[contains(text(), "We\'re sorry, the account system is having a problem.")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->http->FindPreg('/role="alert">We didn\'t find an account matching that information. Make sure you entered it correctly or/')) {
                        throw new CheckException("We didn't find an account matching that information. Make sure you entered it correctly or create an account.", ACCOUNT_INVALID_PASSWORD);
                    }

                    if ($this->http->FindSingleNode("//*[self::h2 or self::span][
                        contains(text(), 'Please Update Your Account')
                        or contains(text(), 'Stay in touch!')
                        or contains(text(), 'We just need a little more info')
                    ]
                        | //h2/span[contains(text(), 'Please Update Your Account')]
                    ")
                    ) {
                        $this->throwAcceptTermsMessageException();
                    }

                    $this->http->JsonLog($responseData, 5, true);
                    $this->http->SetBody($responseData);

                    return true;
                } elseif (!empty($responseData)) {
                    $iframe = $selenium->waitForElement(WebDriverBy::xpath("//iframe[@id = 'disneyid-iframe' or @id = 'oneid-iframe']"), 30);
                    $this->savePageToLogs($selenium);

                    if ($iframe) {
                        $selenium->driver->switchTo()->frame($iframe);
                        $this->savePageToLogs($selenium);
                    }

                    if ($this->http->FindSingleNode("//*[self::h2 or self::span][
                        contains(text(), 'Please Update Your Account')
                        or contains(text(), 'Stay in touch!')
                        or contains(text(), 'We just need a little more info')
                    ]
                        | //h2/span[contains(text(), 'Please Update Your Account')]
                    ")
                    ) {
                        $this->throwAcceptTermsMessageException();
                    }

                    $this->http->SetBody($responseData);

                    return true;
                }
            }
        } catch (StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage());
        } catch (NoSuchDriverException | NoSuchWindowException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return false;
    }
}
