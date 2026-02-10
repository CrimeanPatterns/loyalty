<?php

class TAccountCheckerBilt extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;

    private const REWARDS_PAGE_URL = 'https://api.biltcard.com/user/profile';

    private $headers = [
        "Accept"          => "application/json, text/plain, */*",
        'Accept-Encoding' => 'gzip, deflate',
        'Lang'            => 'en',
        'User-Agent'      => 'iOS',
    ];

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerBiltSelenium.php";

        return new TAccountCheckerBiltSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->RetryCount = 0;

        $this->setProxyGoProxies(); // amazon complaince workaround
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
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

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('We do not recognize this email', ACCOUNT_INVALID_PASSWORD);
        }

        $this->selenium();

        return true;

        $this->http->GetURL('https://www.biltrewards.com/login');
        $headers = [
            "Accept"                     => "application/json",
            'Content-Type'               => 'application/json',
            'X-Okta-User-Agent-Extended' => 'okta-auth-js/6.5.0',
            'Origin'                     => 'https://www.biltrewards.com',
        ];

        /*
        $data = [
            "username" => $this->AccountFields['Login'],
        ];
        $this->http->PostURL("https://api.biltcard.com/user/profile/exists", json_encode($data), $headers);
        $response = $this->http->JsonLog();

        if ($response->userExists == false) {
            throw new CheckException('Looks like there’s no account associated with this email. If you don’t have an account yet, please join the waitlist.', ACCOUNT_INVALID_PASSWORD);
        }
        */

        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $this->http->PostURL("https://auth.biltrewards.com/api/v1/authn", json_encode($data), $headers);

        return true;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog(null, 5);

        if (!isset($response->sessionToken)) {
            // We do not recognize this email / password combination.
            $message = $response->errorSummary ?? null;

            if ($message == 'Authentication failed') {
                throw new CheckException('We do not recognize this email / password combination.', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        $clientId = '0oat1nsr7w5AKUTHX5d6';
        $state = 'SHR-LXJW5';
        $nonce = 'IZUMEGT';
        $code_verifier = '';
        $o = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-._~";

        for ($r = 0; $r < 128; $r++) {
            $pos = (int) (floor((float) rand() / (float) getrandmax() * mb_strlen($o)));
            $code_verifier .= $o[$pos];
        }

        $this->logger->debug("code_verifier: {$code_verifier}");
        $hash = hash('sha256', $code_verifier);
        $code_challenge = $this->base64url_encode(pack('H*', $hash));
        $this->logger->debug("code_challenge: {$code_challenge}");

        $param = [];
        $param['client_id'] = $clientId;
        $param['response_mode'] = 'fragment';
        $param['response_type'] = 'code';
        $param['code_challenge_method'] = 'S256';
        $param['code_challenge'] = $code_challenge;
        $param['scope'] = 'openid email profile phone offline_access';
        $param['redirect_uri'] = 'https://www.biltrewards.com/login/callback';
        $param['nonce'] = $nonce;
        $param['state'] = $state;
        $param['sessionToken'] = $response->sessionToken;

        $headers = [
            "Accept"          => "*
        /*",
            "User-Agent"      => "Bilt/1 CFNetwork/976 Darwin/18.2.0",
            "Accept-Encoding" => "gzip, deflate",
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://biltcard.okta.com/oauth2/default/v1/authorize?" . http_build_query($param), $headers);
        $this->http->RetryCount = 2;
        $code = $this->http->FindPreg("/code=([^&]+)/", false, $this->http->currentUrl());

        if (!$code) {
            $this->logger->error("code not found");

            return $this->checkErrors();
        }

        $headers = [
            "Accept"          => "*
        /
        *",
            "Content-Type"    => "application/x-www-form-urlencoded",
            "User-Agent"      => "Bilt/1 CFNetwork/976 Darwin/18.2.0",
            "Accept-Encoding" => "gzip, deflate",
            "Origin"          => "https://www.biltrewards.com",
        ];
        $data = [
            "grant_type"    => "authorization_code",
            "client_id"     => $clientId,
            "redirect_uri"  => "https://www.biltrewards.com/login/callback",
            "code_verifier" => $code_verifier,
            "code"          => $code,
        ];
        $this->http->PostURL("https://auth.okta.com/oauth2/default/v1/token", $data, $headers);
        */

        $response = $this->http->JsonLog();

        if (isset($response->id_token)) {
            $this->State['Authorization'] = $response->id_token;

            return $this->loginSuccessful();
        }

        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We do not recognize this email')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//span[contains(text(), "Network Error")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'UserHeaderTitle') and contains(text(), 'Create password')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/>Request failed with status code 500<\/span>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//span[contains(text(), 'We could not register you due to security settings.')]")) {
            $this->DebugInfo = 'captcha issue';
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        $this->SetProperty('Name', beautifulName($response->profile->name));

        $this->http->GetURL("https://api.biltcard.com/loyalty/user", $this->headers);
        $response = $this->http->JsonLog();
        // Balance - Bilt points
        $this->SetBalance($response->availablePoints);
        // Bilt Rewards Member Number
        $this->SetProperty('Number', $response->loyaltyId);
        // Status
        $this->SetProperty('Status', $response->currentTierName);
        // Status points
        $this->SetProperty('StatusPoints', $response->tierPoints);
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);
            $selenium->disableImages();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://www.biltrewards.com/login');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'email']"), 5);
            $this->savePageToLogs($selenium);

            if (!$login) {
                return $this->checkErrors();
            }

            $login->sendKeys($this->AccountFields['Login']);
            $this->savePageToLogs($selenium);
            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//div[not(@disavled)]/input[@value = "Next"]'), 3);
            $signInButton->click();

            $pass = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 5);
            $this->savePageToLogs($selenium);

            if (!$pass) {
                if ($selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Request failed with status code 403')]"), 0)) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            $pass->sendKeys($this->AccountFields['Pass']);

            $selenium->driver->executeScript('
                const constantMock = window.fetch;
                window.fetch = function() {
                    console.log(arguments);
                    return new Promise((resolve, reject) => {
                        constantMock.apply(this, arguments)
                        .then((response) => {
                            if (response.url.indexOf("/v1/token") > -1) {
                                response
                                .clone()
                                .json()
                                .then(body => localStorage.setItem("responseData", JSON.stringify(body)));
                        }
                            resolve(response);
                        })
                    .catch((error) => {
                            reject(response);
                        })
                    });
                }
            ');

            $signInButton = $selenium->waitForElement(WebDriverBy::xpath('//div[not(@disavled)]/input[@value = "Next"]'), 3);
            $this->savePageToLogs($selenium);

            if (!$signInButton) {
                return $this->checkErrors();
            }

            $signInButton->click();

            $selenium->waitForElement(WebDriverBy::xpath("
                //span[contains(text(), 'Your points balance')]
                | //span[contains(text(), 'We do not recognize this email / password combination.')]
                | //span[contains(text(), 'Request failed with status code 500')]
                | //span[contains(text(), 'Network Error')]
                | //div[contains(@class, 'UserHeaderTitle') and contains(text(), 'Create password')]
                | //span[contains(text(), 'Enter the code we sent to your phone number')]
            "), 10);
            $this->savePageToLogs($selenium);

            $responseData = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $this->logger->info("[Form responseData]: '" . $responseData . "'");
            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");

            if (!empty($responseData)) {
                $this->http->SetBody($responseData);
            }
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
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

        return $currentUrl;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = [
            'Authorization'   => 'Bearer ' . $this->State['Authorization'],
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, $this->headers + $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->profile->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if ($email && strtolower($email) == strtolower($this->AccountFields['Login'])) {
            $this->headers = $this->headers + $headers;

            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function base64url_encode($plainText)
    {
        $this->logger->notice(__METHOD__);

        $base64 = base64_encode($plainText);
        $base64 = trim($base64, "=");
        $base64url = strtr($base64, '+/', '-_');

        return $base64url;
    }
}
