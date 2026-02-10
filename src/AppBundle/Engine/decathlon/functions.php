<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerDecathlon extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $seleniumInSpain = true;

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'AvailableBonus')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $arg["RedirectURL"] = "https://www.decathlon.es/es/account/dashboard";
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->seleniumInSpain === true) {
            $this->UseSelenium();
            /*
            $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $this->http->saveScreenshots = true;
            $this->setProxyGoProxies(null, "es");
            */

            $this->useFirefox();
            $this->seleniumOptions->fingerprintParams = \FingerprintParams::vanillaFirefox();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->setKeepProfile(true);
            $this->disableImages();
            $this->http->saveScreenshots = true;
            $this->setProxyGoProxies(null, "es");
        }
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields['Login2'] == 'Russia') {
            throw new CheckException("Program is not supported for region Russia now", ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->RetryCount = 0;
        $this->http->setCookie('DKT_LOGIN', 'N', 'www.decathlon.es');
        $this->http->GetURL("https://www.decathlon.es/es/account/dashboard", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[@id = 'logout-button']")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->setCookie('DKT_LOGIN', 'N', 'www.decathlon.es');
        $this->http->GetURL('https://www.decathlon.es/es/login?redirectUrl=/es/account/dashboard');

        if ($this->seleniumInSpain === true) {
            $loginInput = $this->waitForElement(WebDriverBy::id("input-email"), 15);
            $lookupBtn = $this->waitForElement(WebDriverBy::id("lookup-btn"), 0);
            $this->saveResponse();

            if (!$loginInput || !$lookupBtn) {
                if ($this->loginSuccessful()) {
                    return true;
                }

                if ($this->http->FindSingleNode('//*[self::h1 or self::h2][contains(text(), "Checking if the site connection is secure")]')) {
                    $this->DebugInfo = self::ERROR_REASON_BLOCK;
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;

                    throw new CheckRetryNeededException(3, 5);
                }

                return $this->checkErrorsFormEs();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $this->saveResponse();
            $lookupBtn->click();

            $passwordInput = $this->waitForElement(WebDriverBy::id("input-password"), 5);
            $signInBtn = $this->waitForElement(WebDriverBy::id("signin-button"), 0);
            $this->saveResponse();

            if (!$passwordInput || !$signInBtn) {
                $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "textfield-error-message")] | //div[@id = "user-without-password-info"]'), 0);

                if ($error) {
                    $message = Html::cleanXMLValue($error->getText());
                    $this->logger->error("[Error]: {$message}");

                    if (
                        strstr($message, 'Unable to find Decathlon account')
                        || strstr($message, 'Recibirás un código para reiniciar tu contraseña en la dirección de email')
                        || $message == 'Cuenta Decathlon no encontrada'
                        || $message == 'Introduce un correo electrónico o un teléfono móvil'
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                if ($this->processSecurityQuestion()) {
                    return false;
                }

                return $this->checkErrorsFormEs();
            }
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
            $signInBtn->click();

            return true;
        }// if ($this->seleniumInSpain === true)

        if ($this->http->Response['code'] !== 200) {
            return false;
        }

        $clientId = $this->http->FindPreg('/client_id=(.+?)&/', false, $this->http->currentUrl());
        $correlationId = $this->http->FindPreg('/&correlation_id=(.+?)$/', false, $this->http->currentUrl());

        if (!$clientId || !$correlationId) {
            return false;
        }
        $referer = $this->http->currentUrl();
        // js url
        $jsApp = $this->http->FindPreg('/src=(\/static\/js\/app\..+?\.js)>/');

        if (!$jsApp) {
            return false;
        }
        //$this->http->setDefaultHeader('Referer', $referer);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://login.decathlon.net" . $jsApp);

        $captchaKey = $this->http->FindPreg('/,CAPTCHA_ENTERPRISE_KEY:\"([^\"]+)"/');
        $apiKey = $this->http->FindPreg('/LOGIN_API_Key:\"([^\"]+)/');

        if (!$apiKey) {
            return false;
        }

        $captcha = $this->parseReCaptchaEnterprise($captchaKey, 'lookup', $referer);

        if ($captcha === false) {
            return false;
        }

        $data = [
            "email" => $this->AccountFields['Login'],
        ];

        $headers = [
            "Accept"             => "application/json, text/plain, */*",
            "Accept-Encoding"    => "gzip, deflate, br",
            "Content-Type"       => "application/json;charset=utf-8",
            "Origin"             => "https://login.decathlon.net",
            "Referer"            => "https://login.decathlon.net/",
            "X-APi-Key"          => $apiKey,
            "X-Correlation-Id"   => $correlationId,
            "X-Requested-With"   => "XMLHttpRequest",
            'X-Selected-Country' => 'ES',
        ];

        $param = [
            'client_id'       => $clientId,
            'responseCaptcha' => $captcha,
        ];
        $this->http->PostURL('https://api-global.decathlon.net/connect/lookup?' . http_build_query($param), json_encode($data), $headers);
        $lookup = $this->http->JsonLog();

        if (empty($lookup->uuid)) {
            return false;
        }

        sleep(rand(2, 5));
        $captcha = $this->parseReCaptchaEnterprise($captchaKey, 'login', $referer);

        if ($captcha === false) {
            return false;
        }
        $param = [
            'client_id'       => $clientId,
            'responseCaptcha' => $captcha,
        ];
        $data = [
            'user_id'    => $lookup->uuid,
            'password'   => $this->AccountFields['Pass'],
            'login_type' => 'email',
        ];
        $headers['Content-Type'] = 'application/x-www-form-urlencoded';
        $headers['x-selected-country'] = $lookup->country;
        $this->http->PostURL('https://api-global.decathlon.net/connect/login?' . http_build_query($param), $data, $headers);

        if ($action = $this->http->FindSingleNode("//form[@name='hiddenform']/@action")) {
            $this->http->PostURL($action, null, $headers);

            return true;
        }

        return false;
    }

    public function checkErrorsFormEs()
    {
        $this->logger->notice(__METHOD__);
        $message = $this->http->FindSingleNode('//div[contains(@class,"textfield-error-message ltr-directio")]');

        if ($message) {
            $this->logger->error($message);

            if (
                strstr($message, 'Contraseña no válida')
                || strstr($message, 'Cuenta Decathlon no encontrada')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return false;
    }

    public function Login()
    {
        if ($this->seleniumInSpain === true) {
            $sleep = 30;
            $startTime = time();

            while ((time() - $startTime) < $sleep) {
                $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

                if ($this->loginSuccessful()) {
                    return true;
                }

                $error = $this->waitForElement(WebDriverBy::xpath('//p[contains(@class, "textfield-error-message")]'), 0);

                if ($error) {
                    $message = $error->getText();

                    if (
                        strstr($message, 'Impossible de trouver un compte correspondant à cette adresse e-mail')
                        || strstr($message, 'Contraseña no válida')
                    ) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }

                    if (
                        strstr($message, 'Account temporarily blocked')
                    ) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    if (strstr($message, 'Error técnico')) {
                        $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    }

                    if (
                        strstr($message, 'Cuenta bloqueada temporalmente, haz clic sobre "he olvidado mi contraseña" para resetear tu contraseña')
                    ) {
                        throw new CheckException($message, ACCOUNT_LOCKOUT);
                    }

                    $this->DebugInfo = $message;

                    return false;
                }

                sleep(1);
            }// while ((time() - $startTime) < $sleep)
            $this->saveResponse();
        }// if ($this->seleniumInSpain === true)

        $response = $this->http->JsonLog();

        if ($this->http->FindPreg('/"errors":\[\]/') || $this->http->FindSingleNode("//div[@class='user-info']/div[@class='name']")) {
            return true;
        }

        if ($message = $this->http->FindPreg('/"message":"(El usuario o contraseña introducido es incorrecto. Por favor, inténtalo de nuevo.)"/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/"httpStatus":500,"message":"Technical Error \{/')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        return $this->processSecurityQuestion();
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->currentUrl() != 'https://www.decathlon.es/es/account/dashboard') {
            $this->http->GetURL('https://www.decathlon.es/es/account/dashboard');
        }

        if ($this->seleniumInSpain === true) {
            $this->waitForElement(WebDriverBy::xpath("
                //div[@class='gift-infos']/i[span[contains(text(), 'Puntos')]]
                | //h2[contains(text(), 'Estamos actualizando nuestra web.')]
            "), 10);
            $this->saveResponse();
        }

        // Balance - points
        $this->SetBalance($this->http->FindSingleNode("//div[@class='gift-infos']/i[span[contains(text(), 'Puntos')]]/text()"));
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//div[contains(@class,"tool tool--account is-loggued dropdown")]')
            ?? ($this->http->FindPreg('/"firstName":"([^\"]+)/') . " " . $this->http->FindPreg('/"lastName":"([^\"]+)/'))
        ));
        // Cuenta
        $this->SetProperty("Number", $this->http->FindSingleNode("//div[contains(@class, 'accountID')]", null, true, "/Cuenta\s*([^<]+)/"));

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode("
                    //p[contains(text(), 'Conoce las ventajas exclusivas MyDecathlon')]
                    | //div[contains(text(), 'Conoce todas las ventajas y servicios de tu cuenta Decathlon')]
                    | //div[contains(text(), 'Se ha producido un problema durante la transferencia de datos. Nuestros equipos están trabajando para resolver el problema lo más pronto posible.')]
                ")
            && !$this->http->FindSingleNode("//div[@class='gift-infos']/i[not(span[contains(text(), 'Puntos')])]/text()")
            && !empty($this->Properties['Name'])
            && !empty($this->Properties['Number'])
        ) {
            $this->SetBalanceNA();

            return;
        } elseif (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Estamos actualizando nuestra web.')]"))
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        } elseif (isset($this->Balance)) {
            $this->sendNotification('decathlon - Check Balance');
        }

        $this->http->GetURL('https://www.decathlon.es/es/account/loyalty');

        if ($this->seleniumInSpain === true) {
            $this->waitForElement(WebDriverBy::xpath("//div[@class = 'vouchers-pict']//div[@class='gift-infos']/i[span[not(contains(text(), 'Puntos'))]]"), 10);
            $this->saveResponse();
        }

        $expDate = $this->http->FindSingleNode("//div[@class = 'vouchers-details']//strong[contains(text(), 'Fecha de caducidad')]", null, true, "/\:\s*([^<]+)/");
        $expDate = $this->ModifyDateFormat($expDate);
        $balance = $this->http->FindSingleNode("//div[@class = 'vouchers-pict']//div[@class='gift-infos']/i[span[not(contains(text(), 'Puntos'))]]/text()");

        if (!$balance || !$expDate) {
            return;
        }
        $barCode = $this->http->FindSingleNode("//div[@class = 'vouchers-details']//span[contains(text(), 'Código del bono')]", null, true, "/\:\s*([^<]+)/");
        $this->AddSubAccount([
            'Code'           => "decathlon{$this->AccountFields['Login2']}AvailableBonus",
            'DisplayName'    => "Available bonus",
            'Balance'        => $balance,
            'ExpirationDate' => strtotime($expDate, false),
            'Pin'            => $this->http->FindSingleNode("//div[@class = 'vouchers-details']//span[contains(text(), 'Código pin')]", null, true, "/\:\s*([^<]+)/"),
            'BarCodeNumber'  => $barCode ?? '',
            //            'BarCode'        => $barCode ?? '',
            //            "BarCodeType"    => ,
        ]);
    }

    protected function parseReCaptchaEnterprise($key = null, $action = null, $referer = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode('//form[@action = "?"]//div[@class = "g-recaptcha"]/@data-sitekey');
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        //$this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $referer ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
            "version"   => "enterprise",
        ];
        $postData = [
            "type"         => "RecaptchaV3TaskProxyless",
            "websiteURL"   => $referer ?? $this->http->currentUrl(),
            "websiteKey"   => $key,
        ];

        if ($action) {
            $parameters += [
                "action"    => $action,
                "min_score" => 0.9,
            ];
            $postData += [
                "minScore"     => 0.9,
                "pageAction"   => $action,
                "isEnterprise" => true,
            ];
        }
        //$captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
        $captcha = $this->recognizeAntiCaptcha($this->recognizer, $postData);

        return $captcha;
    }

    protected function parseReCaptcha($key = null, $action = null, $referer = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode('//form[@action = "?"]//div[@class = "g-recaptcha"]/@data-sitekey');
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $referer ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        if ($action) {
            $parameters += [
                "version"   => "v3",
                "action"    => $action,
                "min_score" => 0.9,
            ];
        }
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
    }

    private function processSecurityQuestion()
    {
        $this->logger->info('Security Question', ['Header' => 3]);
        $questionObject = $this->waitForElement(WebDriverBy::xpath('//*[self::div or self::p][contains(text(), "Como medida de seguridad, autentifícate de nuevo con el código que te hemos enviado a:")]'), 5);
        $this->saveResponse();

        $email = $this->http->FindSingleNode('//p[contains(@class, "account-editor-")]');

        if (!isset($questionObject) || !$email) {
            $this->logger->error("something went wrong");

            return false;
        }

        $answerInputs = $this->driver->findElements(WebDriverBy::xpath("//input[contains(@id, 'input-')]"));

        if (!$answerInputs) {
            return false;
        }

        $question = trim($questionObject->getText()) . " " . $email;
        $this->logger->debug("Question -> {$question}");

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return true;
        }

        $this->logger->debug("Entering answer on question -> {$question}...");
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);
        $this->logger->debug("entering code...");

        foreach ($answerInputs as $key => $answerInput) {
            $this->logger->debug("#{$key}: {$answer[$key]}");
            $input = $this->driver->findElement(WebDriverBy::xpath("//input[contains(@id, 'input-{$key}')]"));
            $input->clear();
            $input->click();
            $input->sendKeys($answer[$key]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)

        $btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Siguiente') and not(@disabled)]"), 3);
        $this->saveResponse();

        if (!$btn) {
            return false;
        }

        $btn->click();

        sleep(5);
        $this->saveResponse();
        // TODO

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $isLogged = $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class,"tool tool--account is-loggued dropdown")]
            | //div[contains(@class,"tool tool--account dropdown")]//span[contains(text(), "Mi perfil")]
            | //div[contains(@class,"tool tool--account dropdown")]//span[contains(text(), "Mi cuenta")]
            | //a[@aria-label="Acceder a Perfil"]
        '), 0);
        $this->saveResponse();

        if ($isLogged) {
            return true;
        }

        return false;
    }
}
