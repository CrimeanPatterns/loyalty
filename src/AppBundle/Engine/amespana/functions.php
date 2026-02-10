<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmespana extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public $regionOptions = [
        ""           => "Select your login type",
        "CardNumber" => "Card Number",
        "Email"      => "Email",
        "Document"   => "Document",
    ];
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;

        $this->setProxyGoProxies(null, "es");
        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL("https://www.travelclub.es/tienes_tarjeta.cfm?url=/home.cfm");

        try {
            switch ($this->AccountFields['Login2']) {
                case 'CardNumber':
                    $this->LoadLoginFormCardNumber();

                    break;

                case 'Document':
                    $this->LoadLoginFormDocument();

                    break;

                case 'Email':
                default:
                    if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                        throw new CheckException('Información de acceso no válida', ACCOUNT_INVALID_PASSWORD);
                    }

                    $this->LoadLoginFormEmail();

                    break;
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
            $this->DebugInfo = "UnknownServerException";

            if (strstr($e->getMessage(), 'Failed to decode response from marionette')) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return true;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // El número no es correcto.
        // Debes introducir la clave.
        // Por favor introduce un email válido
        // Información de acceso no válida
        // Tarjeta dada de baja
        if ($message = $this->waitForElement(WebDriverBy::xpath("
                //form[@id='login_num']//small[contains(text(), 'El número no es correcto.')]
                | //form[@id='login_email']//small[contains(text(), 'Debes introducir la clave.')]
                | //form[@id='login_email']//small[contains(text(), 'Por favor introduce un email válido')]
                | //form[@id='login_dni']//div[contains(text(), 'Información de acceso no válida')]
                | //form[@id='login_dni']//span[contains(text(), 'Introduce tu contraseña')]
                | //div[@id='msjErrorServicio' and @style='display: block;' and contains(text(), 'Tarjeta dada de baja')]
                | //div[@id='msjErrorServicio' and @style='display: block;' and contains(text(), 'Usuario y/o contraseña incorrecta')]
                | //div[@id='msjErrorServicio' and @style='display: block;' and contains(text(), 'Por motivos de seguridad debes cambiar tu contraseña usando la opción ¿Has olvidado tu contraseña o está bloqueada?')]
                | //div[@id='msjErrorServicio' and @style='display: block;' and contains(text(), 'Información de acceso no válida')]
            "), 3)
        ) {
            $this->saveResponse();

            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Introduce tu contraseña
        if ($this->waitForElement(WebDriverBy::xpath("//form[@id='login_email']//span[contains(text(), 'Introduce tu contraseña')]"), 0)) {
            throw new CheckException('Información de acceso no válida', ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(normalize-space(text()), 'En estos momentos no podemos comprobar tus datos.')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href,'mis_movimientos')]"), 7);
        $this->saveResponse();

        if ($this->http->FindSingleNode("(//a[contains(@href, 'desconexion')]/@href)[1]")) {
            return true;
        }

        if ($this->processQuestion()) {
            return false;
        }

        $this->checkCredentials();

        return false;
    }

    public function processQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $question = $this->http->FindSingleNode("//p[contains(text(), 'Introduce el código que te hemos enviado al')]");

        if (!$question) {
            $this->logger->error("question not found");

            return false;
        }

        $questionInput = $this->waitForElement(WebDriverBy::xpath('//input[@name="dosfa"]'), 0);

        if (!$questionInput) {
            $this->logger->error("questionInput not found");

            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $questionInput->clear();
        $questionInput->sendKeys($answer);
        $this->logger->debug("ready to click");
        $this->saveResponse();
        $aceptar2fa = $this->waitForElement(WebDriverBy::xpath('//button[@id = "aceptar2fa"]'), 0);
        $this->saveResponse();

        if (!$aceptar2fa) {
            $this->logger->error("btn not found");

            return false;
        }

        $this->logger->debug("clicking next");
        $aceptar2fa->click();

        sleep(5);
        $this->saveResponse();

        /*
        if ($error = $this->waitForElement(WebDriverBy::xpath(''), 0)) {
            $message = $error->getText();

            if (strstr($message, '')) {
                $this->holdSession();
                $this->AskQuestion($question, $message, "Question");
            }

            return false;
        }
        */

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'Question') {
            $this->saveResponse();

            return $this->processQuestion();
        }

        return true;
    }

    public function Parse()
    {
        // Mis puntos
        $this->SetBalance($this->http->FindSingleNode("//p[small[contains(text(), 'Mis puntos')]]/following-sibling::p"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//h1[contains(@class, "text-capitalize")]')));
        // Socio desde
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//p[small[contains(text(), 'Socio desde')]]/following-sibling::p"));
        // Nº de tarjeta
        $this->SetProperty("CardNumber", $this->http->FindSingleNode("//p[small[contains(text(), 'Nº de tarjeta')]]/following-sibling::p"));
    }

    protected function parseHCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id=\"hCaptcha\"]/iframe[contains(@src, 'sitekey')]/@src", null, true, "/sitekey=([^&]+)/");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"    => "hcaptcha",
            "pageurl"   => $currentUrl ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function LoadLoginFormCardNumber()
    {
        $this->logger->notice(__METHOD__);

        if ($tab = $this->waitForElement(WebDriverBy::xpath("//a[@data-toggle='tab' and text()='Tarjeta ']"), 7)) {
            $tab->click();
        }

        $login = $this->waitForElement(WebDriverBy::id('numTarj'), 0);
        $login2 = $this->waitForElement(WebDriverBy::id('numTarj1'), 0);
        $pass = $this->waitForElement(WebDriverBy::id('pass_num'), 0);
        $button = $this->waitForElement(WebDriverBy::id('btn_login'), 0);

        if (!$login || !$login2 || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $this->AccountFields['Login'] = preg_replace('/^6015470/', '', $this->AccountFields['Login']);
        $this->logger->debug("set Login, part 1");

        $x = $login->getLocation()->getX();
        $y = $login->getLocation()->getY() - 200;
        $this->driver->executeScript("window.scrollBy($x, $y)");

        $login->sendKeys(trim(substr($this->AccountFields['Login'], 0, -1)));
        $this->logger->debug("set Login, part 2");
        $login2->sendKeys(substr($this->AccountFields['Login'], -1));
        $this->logger->debug("set Pass");
        $password = substr($this->AccountFields['Pass'], 0, 20);
        $pass->sendKeys($password);
        $this->saveResponse();
        $this->logger->debug($button->getAttribute('disabled'));
        $this->logger->debug("click btn");

        if (!$button->getAttribute('disabled')) {
            $this->driver->executeScript("$('#hCaptcha_help-block').remove();");
            $captcha = $this->parseHCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->driver->executeScript("$('[name = \"h-captcha-response\"]').val(\"{$captcha}\");;");
            $this->driver->executeScript("document.getElementById('btn_login').click()");
            $button->click();
        } else {
            $this->checkCredentials();
        }

        return true;
    }

    private function LoadLoginFormEmail()
    {
        $this->logger->notice(__METHOD__);

        if ($tab = $this->waitForElement(WebDriverBy::xpath("//a[@data-toggle='tab' and text()='Email']"), 7)) {
            $tab->click();
        }

        $login = $this->waitForElement(WebDriverBy::id('email'), 0);
        $pass = $this->waitForElement(WebDriverBy::id('pass_email'), 0);
        $button = $this->waitForElement(WebDriverBy::id('btn_login'), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $password = substr($this->AccountFields['Pass'], 0, 20);

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(10000, 60000);
        $mover->steps = rand(30, 60);

        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 6);
        $mover->moveToElement($pass);
        $mover->click();
        $mover->sendKeys($pass, $password, 6);

//        $login->sendKeys($this->AccountFields['Login']);
//        $pass->sendKeys($password);
        $this->saveResponse();

        if (!$button->getAttribute('disabled')) {
            /*
            $this->driver->executeScript("$('#hCaptcha_help-block').remove();");
            $captcha = $this->parseHCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->driver->executeScript("$('[name = \"h-captcha-response\"]').val(\"{$captcha}\");;");
            */
            $this->driver->executeScript("document.getElementById('btn_login').click()");
        }
//            $button->click();
        else {
            $this->checkCredentials();
        }

        return true;
    }

    private function LoadLoginFormDocument()
    {
        $this->logger->notice(__METHOD__);

        if ($tab = $this->waitForElement(WebDriverBy::xpath("//a[@data-toggle='tab' and text()='DNI']"), 7)) {
            $tab->click();
        }

        $login = $this->waitForElement(WebDriverBy::id('dni'), 0);
        $pass = $this->waitForElement(WebDriverBy::id('pass_dni'), 0);
        $button = $this->waitForElement(WebDriverBy::id('btn_login'), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return false;
        }
        $password = substr($this->AccountFields['Pass'], 0, 20);

        /*
        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($password);
        */
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(10000, 60000);
        $mover->steps = rand(30, 60);

        $mover->moveToElement($login);
        $mover->click();
        $mover->sendKeys($login, $this->AccountFields['Login'], 6);
        $mover->moveToElement($pass);
        $mover->click();
        $mover->sendKeys($pass, $password, 6);

        $this->saveResponse();

        if (!$button->getAttribute('disabled')) {
            /*
            $this->driver->executeScript("$('#hCaptcha_help-block').remove();");
            $captcha = $this->parseHCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->driver->executeScript("$('[name = \"h-captcha-response\"]').val(\"{$captcha}\");;");
            $this->driver->executeScript("document.getElementById('btn_login').click()");
            */
            $this->driver->executeScript("document.getElementById('btn_login').click()");
        }
//            $button->click();
        else {
            $this->checkCredentials();
        }

        return true;
    }
}
