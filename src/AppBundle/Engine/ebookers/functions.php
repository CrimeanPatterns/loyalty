<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEbookers extends TAccountCheckerExtended
{
    use SeleniumCheckerHelper;
    /*
     * these provider like as orbitz, cheaptickets, ebookers, hotelclub, expedia, travelocity
     *
     * YOU NEED TO CHECK ALL PARSERS
     */
    use PriceTools;
    use DateTimeTools;
    use ProxyList;

    public $regionOptions = [
        ""            => "Select your region",
        "UK"          => "United Kingdom",
        "USA"         => "United States",
        "Deutschland" => "Deutschland",
        //        "Netherlands" => "Netherlands",// was closed on May 17, 2016
        "Switzerland" => "Switzerland",
    ];

    protected $expedia;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $host = '';

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            if ($properties['Currency'] == 'CHF') {
                $properties['Currency'] = $properties['Currency'] . " ";
            }

            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['RedirectURL'] = "https://{$this->host}/";

        return $arg;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
//        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->RetryCount = 0;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.ebookers.com/account/myclub", [], 20);
        $this->http->RetryCount = 2;
        $this->delay();

        if ($this->http->Response['code'] == 200
            && $this->http->FindNodes("//a[contains(@href, 'logout')]/@href")
            && !$this->http->FindNodes("//form[contains(@id, 'login-form')]")) {
            $this->setHost();

            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->FilterHTML = false;
        $submit = $this->setHost();
        $this->http->GetURL("https://{$this->host}/login");
        $pointOfSaleId = $this->http->FindPreg("/\"pointOfSaleId.\":.\"([^\\\]+)/");
        $uiBrand = $this->http->FindPreg("/\"uiBrand.\":.\"([^\\\]+)/");
        $deviceId = $this->http->FindPreg("/\"deviceId.\":.\"([^\\\]+)/");
        $remoteAddress = $this->http->FindPreg("/\"remoteAddress.\":.\"([^\\\]+)/");
        $guid = $this->http->FindPreg("/\"guid.\":.\"([^\\\]+)/");
        $traceId = $this->http->FindPreg("/\"traceId.\":.\"([^\\\]+)/");
        $tpid = $this->http->FindPreg("/.\"tpid.\":(\d+)/");
        $site_id = $this->http->FindPreg("/_site_id.\":(\d+)/");
        $eapid = $this->http->FindPreg("/.\"eapid.\":(\d+)/");
        $csrf = $this->http->FindPreg("/\,.\"login.\":.\"([^\\\]+).\",.\"mf/");

        if (!$this->http->ParseForm("loginForm") || !$csrf || !$deviceId || !$uiBrand || !$pointOfSaleId) {
            return $this->checkErrors();
        }

        $this->getCookiesFromSelenium();

        if ($this->loginSuccessful()) {
            return true;
        } else {
            if ($message = $this->http->FindSingleNode("//h3[contains(@class, 'uitk-error-summary-heading')]")) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == "Email and password don't match. Please try again."
                    || $message == "Enter a valid email."
                    || $message == "Your account was paused in a routine security check. Your info is safe, but a password reset is required."
                    || $message == "Das Passwort passt nicht zur eingegebenen E-Mail-Adresse. Bitte versuche es erneut."
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $message == 'Sorry, something went wrong on our end. Please wait a moment and try again.'
                    || $message == 'Leider ist bei uns etwas schiefgegangen.'
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->DebugInfo = $message;

                return false;
            }

            $this->http->GetURL("https://{$this->host}/login");
        }

        $captcha = $this->parseFunCaptcha();

        if ($captcha === false) {
            return false;
        }

        $data = [
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "rememberMe"    => true,
            "channelType"   => "WEB",
            "devices"       => [
                [
                    "payload" => base64_encode('{"configuredDependencies":[{"name":"iovationname","payload":"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;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","status":"COMPLETE"}],"diagnostics":{"timeToProducePayload":1204,"errors":["C1104","C1000","C1004"],"dependencies":[{"timeToProducePayload":324835,"errors":[],"name":"iovationname"}]},"executionContext":{"reportingSegment":"70403,www.ebookers.com,ebookers,ULX","requestURL":"https://www.ebookers.com/login?uurl=e3id%3Dredr%26rurl%3D%2Faccount%2Fmyclub&selc=0","userAgent":"' . $this->http->userAgent . '","placement":"LOGIN","placementPage":"70403","scriptId":"68a00c03-e065-47d3-b098-dd8638b4b39e","version":"1.0","trustWidgetScriptLoadUrl":"https://www.expedia.com/trustProxy/tw.prod.ul.min.js"},"siteInfo":{},"status":"COMPLETE","payloadSchemaVersion":1}'),
                    "type"    => "TRUST_WIDGET",
                ],
            ],
            "atoShieldData" => [
                "atoTokens" => [
                    "fc-token"   => $captcha,
                    "rememberMe" => "",
                    "email"      => $this->AccountFields['Login'],
                ],
                "placement" => "login",
            ],
            "csrfData"      => [
                "csrfToken" => $csrf,
                "placement" => "login",
            ],
        ];
        $headers = [
            "Accept"               => "application/json",
            "accept-encoding"      => "gzip, deflate, br",
            "brand"                => $uiBrand,
            "content-type"         => "application/json",
            "device-type"          => "DESKTOP",
            "device-user-agent-id" => $deviceId,
            "eapid"                => $eapid,
            "pointofsaleid"        => $pointOfSaleId,
            "siteid"               => $site_id,
            "tpid"                 => $tpid,
            "trace-id"             => $traceId,
            "x-mc1-guid"           => $guid,
            "X-REMOTE-ADDR"        => "undefined",
            "X-USER-AGENT"         => "undefined",
            "X-XSS-Protection"     => '1; mode=block',
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->host}/eg-auth-svcs/authenticate/password", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'Deutschland':
                $work = "Wir arbeiten an Verbesserungen auf unserer Seite";

                break;

            default:// USA == UK
                $work = "We're currently working to improve the site";

                break;
        }
        // Maintenance
        if ($notwork = $this->http->FindSingleNode('//*[contains(text(),"' . $work . '")]')) {
            throw new CheckException($notwork, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/^Site temporarily unavailable\.$/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if (
            $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Our service is temporarily down and it appears we’ve been delayed for take off.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our service is temporarily down and it appears we’ve been delayed for take off.')]")) {
            throw new CheckException("We’re Sorry! Our service is temporarily down and it appears we’ve been delayed for take off.", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        $status = $response->status ?? null;

        if ($status === true || $this->loginSuccessful()) {
            $this->delay();

            return true;
        }

        if (
            $this->http->Response['code'] == 401
            && $this->http->FindPreg('/\{"csrfData":\{"csrfToken":"[^"]+","placement":"login"\},"status":false,"failure":null,"requestId":null\}/')
        ) {
            throw new CheckException("Email and password don't match. Try again.", ACCOUNT_INVALID_PASSWORD);
        }

        $message = $response->message ?? null;
        $this->logger->error("[Error]: {$message}");

        if ($message === 'Email constraint not met') {
            throw new CheckException("Enter a valid email.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->DebugInfo = $message;

        return false;
    }

    public function checkProviderErrors()
    {
        switch ($this->AccountFields['Login2']) {
            case 'Deutschland':
                $err = 'Sie es erneut';
                $err_2 = "Sie haben eventuell eine unbekannte E-Mail-Adresse oder ein fehlerhaftes Passwort eingegeben.";
                $err_3 = "Wir entschuldigen uns vielmals für die Systemprobleme. Versuchen Sie es bitte noch einmal.";
                $lockout = '==========================================';

                break;

            case 'Netherlands':
                $err = 'Het e-mailadres en het wachtwoord dat u heeft ingegeven komen niet overeen.';
                $err_2 = '==========================================';
                $err_3 = '==========================================';
                $lockout = '==========================================';

                break;

            case 'Switzerland':
                $err = 'Die E-Mail-Adresse und das Passwort entsprechen sich nicht.';
                $err_2 = "Sie haben eventuell eine unbekannte E-Mail-Adresse oder ein fehlerhaftes Passwort eingegeben.";
                $err_3 = '==========================================';
                $lockout = 'Aus Sicherheitsgründen müssen wir die Anzahl fehlgeschlagener Authentifizierungsversuche beschränken.';

                break;

            default:// USA == UK
                $err = 'Please try again';
                $err_2 = 'You may have entered an unknown email address or an incorrect password.';
                $err_3 = '==========================================';
                $lockout = 'Sorry, for security purposes we must limit the number of invalid authentication attempts.';

                break;
        }

        // Find error message
        if ($message = $this->http->FindSingleNode("//div[contains(text(),'{$err}')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[@id = 'system-error-div']/p[contains(., '{$err_3}')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(),'{$err_2}')] | //h5[contains(text(),'{$err_2}')]/text()[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Account Lockout
        if ($message = $this->http->FindSingleNode("//span[contains(text(),'{$lockout}')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We apologise for our system failure. Please try again.
        if ($message = $this->http->FindSingleNode("//div[@id = 'system-error-div']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function Parse()
    {
        $notMember = $this->http->FindSingleNode("(//strong[contains(text(), 'Werden Sie Mitglied') or contains(text(), 'Join')])[1] | //a[div[contains(text(), 'Join BONUS+') or contains(text(), 'BONUS+ Mitglied werden')]]");

        if ($notMember) {
            $this->logger->warning(self::NOT_MEMBER_MSG);
        }

        switch ($this->AccountFields['Login2']) {
            case 'Deutschland':
            case 'Switzerland':
                $level = 'Mitgliedschaftsebene';
                $protocol = 'https';

                break;

            case 'Netherlands':
                $level = 'Status Level';
                $protocol = 'http';

                break;

            default:// USA == UK
                $level = 'Tier Level';
                $protocol = 'https';

                break;
        }
        /*
         * this page like as orbitz, ebookers page
         *
         * YOU NEED TO CHECK ALL PARSERS
         */
        if ($this->http->currentUrl() != "{$protocol}://{$this->host}/account/myclub") {
            $this->http->GetURL("{$protocol}://{$this->host}/account/myclub");
            $this->delay();

            if (!$notMember) {
                $notMember = $this->http->FindSingleNode("(//strong[contains(text(), 'Werden Sie Mitglied') or contains(text(), 'Join')])[1] | //a[div[contains(text(), 'Join BONUS+') or contains(text(), 'BONUS+ Mitglied werden')]]");
                $this->logger->warning(self::NOT_MEMBER_MSG);
            }
        }
        // Balance - BONUS+ balance: £0.00 in BONUS+
        if (!$this->SetBalance($this->http->FindSingleNode("//div[@class = 'loyaltyRewardsBalance'] | //span[contains(text(), 'Bonus+')]/preceding-sibling::span[1]", null, true, "/([0-9\.\,]+)/"))) {
            $this->SetBalance($this->http->FindSingleNode("//a[div[contains(text(), 'BONUS+')]]/following-sibling::div[@class = 'amountDisplay']", null, true, "/([0-9\.\,]+)/"));
        }
        // Currency
        $this->SetProperty("Currency", trim($this->http->FindSingleNode("//div[@class = 'loyaltyRewardsBalance'] | //span[contains(text(), 'Bonus+')]/preceding-sibling::span[1]", null, true, "/([^0-9\.\,]+)/")));

        if (!isset($this->Properties['Currency'])) {
            $this->SetProperty("Currency", trim($this->http->FindSingleNode("//a[div[contains(text(), 'BONUS+')]]/following-sibling::div[@class = 'amountDisplay']", null, true, "/([^0-9\.\,]+)/")));
        }
        // Tier
        $status = $this->http->FindSingleNode("//div[contains(text(), '{$level}')]/text()[1] | //h2[contains(text(), '{$level}')]/text()[1]", null, true, "/:\s*([^<]+)/ims");

        if (!$status) {
            $status = 'Member';
        }
        $this->SetProperty("Tier", $status);
        // Lifetime BONUS+ earnings
        $this->SetProperty("LifetimeEarnings", $this->http->FindSingleNode("//span[@class = 'lifetimeEarnings'] | //h2[contains(text(), 'Lifetime BONUS+ earnings')]/following-sibling::p[1]/b"));
        // Nights
        $this->SetProperty("Nights", $this->http->FindSingleNode("//div[contains(@class, 'hotelNights')]/strong | //b[contains(., 'Nights')]/span"));
        // BONUS+ expiring next
        $this->SetProperty("ExpiringBalance", $this->http->FindSingleNode("//span[contains(@class, 'expiringNext')]/text()[1] | //h2[contains(text(), 'BONUS+ expiring next')]/following-sibling::p[1]/b"));
        $exp = $this->http->FindSingleNode("//span[contains(@class, 'expiringNext')]/strong | //h2[contains(text(), 'BONUS+ expiring next')]/following-sibling::p[1]", null, true, "/(?:on|am)\s*([^<]+)/ims");

        if (strtotime($exp)) {
            $this->SetExpirationDate(strtotime($exp));
        }

        // Personal info
        $this->http->GetURL("https://{$this->host}/user/account");
        $this->delay();

        if ($this->http->ParseForm(null, "//form[contains(@action, 'personalInfo')]")
            && isset($this->http->Form['nameInput.firstName'], $this->http->Form['nameInput.lastName'])) {
            // Name - from Personal Information form
            $this->SetProperty("Name", beautifulName(Html::cleanXMLValue($this->http->Form['nameInput.firstName'] . ' ' . $this->http->Form['nameInput.lastName'])));
        }// if ($this->http->ParseForm(null, 1, true, "//form[contains(@action, 'personalInfo')]"))
        else {
            // Name
            $name = $this->http->FindSingleNode("//li[@id = 'fullname']");

            if (!isset($name)) {
                $name = $this->http->FindPreg('/>([^\'>]*)\'s information/ims');
                $name = preg_replace('/&nbsp;/ims', ' ', $name);
            }

            if (empty($name)) {
                $userId =
                    $this->http->FindPreg("/\"prop11\":\"([^\"]+)/ims") ?:
                        $this->http->FindPreg("/var tuid = (\d+)/ims");

                if ($userId) {
                    $this->http->GetURL("https://{$this->host}/users/{$userId}/profile?_=" . time() . date("B"));
                    $response = $this->http->JsonLog();

                    if (isset($response->firstname, $response->middlename, $response->lastname)) {
                        $name = Html::cleanXMLValue($response->firstname . " " . $response->middlename . " " . $response->lastname);
                    }
                } else {
                    $this->sendNotification('ebookers - user id not found');
                }
            }
            $this->SetProperty("Name", beautifulName($name));
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($notMember) {
                unset($this->Properties['Tier']);
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }// if ($notMember && !empty($this->Properties['Name']))
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->GetURL("https://{$this->host}/trips?langid=2057");
        $this->delay();
        $expedia = $this->getExpedia();

        $result = $expedia->ParseItineraries($this->host, $this->ParsePastIts);

        return $result;
    }

    protected function checkRegionSelection($region)
    {
        if ($region == "Netherlands") {
            return $region;
        }

        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'USA';
        }

        return $region;
    }

    protected function parseCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindPreg("/recaptchaSiteKey = \"([^\"]+)/");
        }
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
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    /*
     * these itineraries like as orbitz, cheaptickets, ebookers, hotelclub, expedia, travelocity itineraries
     *
     * YOU NEED TO CHECK ALL PARSERS
     */

    /** @return TAccountCheckerExpedia */
    protected function getExpedia()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->http = $this->http;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
        }
        $this->expedia->AccountFields = $this->AccountFields;
        $this->expedia->logger = $this->logger;
        $this->expedia->globalLogger = $this->globalLogger; // fixed notifications

        return $this->expedia;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/pkey=([^&\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 2) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    private function delay()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->verify()) {
            $delay = rand(1, 10);
            $this->logger->debug("Delay -> {$delay}");
            sleep($delay);

            return true;
        }

        return false;
    }

    private function verify()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->Response['code'] != 429 || !$this->http->ParseForm("verifyButton")) {
            return false;
        }
        $currentUrl = $this->http->currentUrl();
        // captcha
        $key = $this->http->FindSingleNode("//form[@id = 'verifyButton']//div[@class = 'g-recaptcha']/@data-sitekey");
        $captcha = $this->parseCaptcha($key);

        if ($captcha === false) {
            return false;
        }
        $this->http->GetURL("https://www.orbitz.com/botOrNot/verify?g-recaptcha-response={$captcha}&destination={$this->http->Form['destination']}");

        if ($this->http->Response['code'] == 302) {
            $this->http->GetURL($currentUrl);
        }

        return true;
    }

    private function setHost()
    {
        $this->logger->notice(__METHOD__);
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);

        switch ($this->AccountFields['Login2']) {
            case 'Deutschland':
                $this->host = 'www.ebookers.de';
                $submit = 'Login';

                break;

            case 'Netherlands':
                $this->host = 'www.ebookers.nl';
                $submit = 'Inloggen';

                throw new CheckException("ebookers.nl maakt nu deel uit van https://www.expedia.nl/. Op 17 mei 2016 sloegen ebookers.nl en https://www.expedia.nl/ de handen ineen met als doel je nog een ruimere keuze aan wereldwijde hotels, vluchten, pakketten, huurautoʼs en activiteiten te kunnen bieden! Je kunt hier bij https://www.expedia.nl/ een nieuwe boeking maken of hieronder klikken om een huidige ebookers-boeking te beheren.", ACCOUNT_PROVIDER_ERROR);

                break;

            case 'Switzerland':
                $this->host = 'www.ebookers.ch';
                $submit = 'Login';

                break;

            default:// USA == UK
                $this->host = 'www.ebookers.com';
                $submit = 'Sign in';

                break;
        }

        return $submit;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();
            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://{$this->host}/login");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormPasswordInput']"), 0);
            $this->savePageToLogs($selenium);

            if (!$login || !$passwordInput) {
                $this->logger->error('Failed to find login button');

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginButton) {
                $this->logger->error('Failed to find "goToPassword" input');

                return false;
            }

            $loginButton->click();

            $selenium->waitForElement(WebDriverBy::xpath("//div[@id = 'gc-custom-header-nav-bar-acct-menu'] | //h3[contains(@class, 'uitk-error-summary-heading')]"), 15);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\InvalidSessionIdException
            | Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg("/\"authState.\":.\"AUTHENTICATED/")
            || $this->http->FindPreg('/"identifier":\{"flow":"ORIGIN","variant":"ORIGIN"\},"csrfData":null,"failure":null/')
        ) {
            return true;
        }

        return false;
    }
}
