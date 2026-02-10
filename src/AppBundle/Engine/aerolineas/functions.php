<?php

class TAccountCheckerAerolineas extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use \AwardWallet\Engine\ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $locale = 'en-ar';

    private $headers = [
        'Accept'          => 'application/json, text/plain, */*',
        'Accept-Language' => 'es-AR',
        'Accept-Encoding' => 'gzip, deflate, br',
        "Referer"         => "https://www.aerolineas.com.ar/",
        "X-Channel-Id"    => "WEB_AR",
        "Content-Type"    => "application/json",
        "Origin"          => "https://www.aerolineas.com.ar",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));

        $this->locale = 'en-ar'; // todo: captcha issue on en site version
    }

    public function IsLoggedIn()
    {
        if (!isset($this->State['Authorization'])) {
            return false;
        }

        $this->headers['Authorization'] = $this->State['Authorization'];

        if ($this->loginSuccessful()) {
            return true;
        }

        unset($this->State['Authorization']);
        unset($this->headers['Authorization']);

        return false;
    }

    public function LoadLoginForm()
    {
        if (!is_numeric($this->AccountFields['Login']) || strlen($this->AccountFields['Login']) == 1) {
            throw new CheckException("The Membership Number must be numeric. Beside, must Not contain dots, commas nor spaces", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->GetURL("https://www.aerolineas.com.ar/{$this->locale}/arplus/acceso");

        $this->changeLocale();

        $token = $this->http->FindPreg('/window.__ACCESS_TOKEN__ = "([^\"]+)/');

        if ($this->http->Response['code'] != 200 || !$token) {
            return $this->checkErrors();
        }

        // TODO: wrong parameters for recaptcha (etnerprise version may be or v3)
        /*
        $captcha = $this->parseReCaptcha();
//        $captcha = $this->http->FindPreg('/\}\)\(\"([^\"]+)/');

        if (!$captcha || $captcha === false) {
            return false;
        }

        $headers = [
            'Accept'          => 'application/json, text/plain, *
        /*',
            'Accept-Encoding' => 'gzip, deflate, br',
            "Referer"         => "https://www.aerolineas.com.ar/arplus/acceso",
            "Origin"          => "https://www.aerolineas.com.ar",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.aerolineas.com.ar/recaptcha/validate?token={$captcha}", [], $headers);
        $this->http->RetryCount = 2;
        $this->http->JsonLog();
        */

        $this->headers['Authorization'] = "Bearer " . urldecode($token);
        $data = [
            "username" => $this->AccountFields['Login'],
            "password" => bin2hex($this->AccountFields['Pass']),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://api.aerolineas.com.ar/v2/gds-loyalty/login", json_encode($data), $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Technical difficulties
        if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Por razones de mantenimiento no es posible acceder a \"Mi Cuenta Plus\".')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        // Access is allowed
        if (isset($response->accessToken, $response->passengersData[0]->frequentFlyerInformation->number)) {
            $this->State['number'] = $response->passengersData[0]->frequentFlyerInformation->number;
            $this->State['Authorization'] = "Bearer " . $response->accessToken;
            $this->headers['Authorization'] = $this->State['Authorization'];

            // delay for auth, prevent error
            /*
                {
                    statusCode: 401,
                    errorMessage: "UNAUTHORIZED",
                    description: [
                        "Access denied with authorization token"
                    ]
                }
            */
            $delay = 5;
            $this->logger->notice("delay: {$delay}");
            sleep($delay);

            if ($this->loginSuccessful()) {
                return true;
            }

            $response = $this->http->JsonLog();
        }

        if (isset($response->errorMessage, $response->description[0])) {
            $message = $response->description[0];
            $this->logger->error("[Error]: {$message}");

            if ($response->errorMessage == 'UNAUTHORIZED') {
                if ($message == 'gds.loyalty.error.invalidRequest') {
                    throw new CheckException("Alguno de los datos ingresados es incorrecto. Intentalo nuevamente.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == 'Access denied with authorization token') {
                    throw new CheckRetryNeededException(3, ($this->attempt + 1) * 5);
                }
            }

            if ($message == 'loyalty.login.login-request.password.size') {
                throw new CheckException("Algunos de los datos ingresados es incorrecto. Intentalo nuevamente.", ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }// if (isset($response->errorMessage, $response->description[0]))

        // very strange error, may be temporarile provider bug
        // AccountID: 4822284, 4835650, 1538653, 2705643, 3094781, 4779072, 2787981
        if ($warnMessage = $response->baseMetadata->warnMessages[0] ?? null) {
            $this->logger->error("[Error]: {$warnMessage}");

            if ($warnMessage === "gds.loyalty.error.ar-plus.login-fail") {
                throw new CheckException("Alguno de los datos ingresados es incorrecto. Por favor intentelo nuevamente.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($warnMessage === "gds.loyalty.error.ar-plus.unknown-response") {
                throw new CheckException("Error desconocido", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = "[warnMessages]: {$warnMessage}";

            return false;
        }// if ($warnMessage === "gds.loyalty.error.ar-plus.login-fail")

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog();
        // Balance - Earned miles
        $this->SetBalance($response->points);
        // Frequent Flyer #
        $this->SetProperty("AccountNumber", $response->membershipCode);
        // Name
        $this->SetProperty("Name", beautifulName($response->fullName));
        // Miles Expiration
        // Vencen el ...
        if (!empty($response->milesExpirationDate)) {
            $this->SetExpirationDate(strtotime($response->milesExpirationDate));
        }
        // refs #5930
        $this->http->GetURL("https://api.aerolineas.com.ar/v1/loyalty/members/{$this->State['number']}/category?membershipCode={$this->State['number']}", $this->headers);
        $responseMembership = $this->http->JsonLog();
        // Status
        $this->SetProperty("Status", beautifulName($response->tier));

        // Status Expiration
        $this->SetProperty("StatusExpiration", date("m/y", strtotime($responseMembership->expirationDate)));
    }

    private function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        // https://www.aerolineas.com.ar/static/js/bundle.dc4b09a7.js -> RECAPTCHA_CLIENT_KEY:"6Lc0RDYiAAAAACh_7Po-NQav-TShjNZWVcIHMa5s"
        $key = "6Lc0RDYiAAAAACh_7Po-NQav-TShjNZWVcIHMa5s";

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"         => "RecaptchaV3TaskProxyless",
//            "websiteURL"   => $this->http->currentUrl(),
//            "websiteKey"   => $key,
//            "minScore"     => 0.3,
//            "pageAction"   => "",
//            "isEnterprise" => true,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData, false);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
            "action"    => "",
            "min_score" => 0.9,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters, false);
    }

    private function changeLocale()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] == 404) {
            $this->locale = 'es-ar';
            $this->http->GetURL("https://www.aerolineas.com.ar/{$this->locale}/aerolineas_plus");
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.aerolineas.com.ar/v1/loyalty/members/{$this->State['number']}", $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->membershipCode) && $response->membershipCode == $this->AccountFields['Login']) {
            return true;
        }

        return false;
    }
}
