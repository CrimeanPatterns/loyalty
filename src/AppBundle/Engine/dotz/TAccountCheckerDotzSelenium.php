<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDotzSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->useSelenium();
        $this->KeepState = true;
//        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
//        $this->setKeepProfile(true);
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        /*
        $this->setProxyBrightData(null, 'static', 'br');
        */
        $this->setProxyGoProxies(null, 'br');
        $this->seleniumOptions->addAntiCaptchaExtension = true;
        $this->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();
        $this->useCache();
//        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
//        $this->disableImages();
//        $this->keepCookies(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if ((!is_numeric($this->AccountFields['Pass']) || mb_strlen($this->AccountFields['Pass']) < 6) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            throw new CheckException("Os Dados não conferem. Por favor, tente novamente.", ACCOUNT_INVALID_PASSWORD);
        }
        // Documento inválido
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException('Documento inválido', ACCOUNT_INVALID_PASSWORD);
        }

        if (strlen($this->AccountFields['Login']) == 16 || strlen($this->AccountFields['Login']) < 7 || preg_match('/^\d{1,2}\.\d{1,2}\.\d{2,4}$/', $this->AccountFields['Login'])) {// AccountID: 5346288
            throw new CheckException('Documento inválido', ACCOUNT_INVALID_PASSWORD);
        }

        if (in_array($this->AccountFields['Login'], ['00062858644', '01523837607', '80125330010'])) { // AccountID: 4884094, 5344769, 6533976 - strange login?
            throw new CheckException('Documento inválido', ACCOUNT_INVALID_PASSWORD);
        }

        $dateOfBirth = explode('.', $this->AccountFields['Login2']);
        $this->logger->debug(var_export($dateOfBirth, true), ["pre" => true]);

        if (!stristr($this->AccountFields['Login2'], '.') || count($dateOfBirth) != 3 || strlen($dateOfBirth[2]) != 2) {
            throw new CheckException("To update this Dotz account you need to correctly fill in the 'Date of Birth' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        // Step 1 - Country
        /*
        try {
            $this->http->GetURL("https://www.dotz.com.br");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
        } catch (TimeOutException $e) {
            $this->increaseTimeLimit();
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        }
        */

        try {
            $this->http->GetURL("https://login.dotz.com.br/");
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
            sleep(3);
            $this->saveResponse();
        } catch (TimeOutException $e) {
            $this->increaseTimeLimit();
            $this->logger->error("TimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
            sleep(3);
            $this->saveResponse();

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("exception: " . $e->getMessage());
            }
        }

        $this->checkProxyErrors();

//        $this->http->GetURL("https://www.dotz.com.br/Selecione-Sua-Regiao.aspx?cityId={FE7DDB30-1F65-4C5A-9BBE-BB9A8574D2CE}");
        $res = $this->waitForElement(WebDriverBy::xpath('//a[@id = "botao_entrar"] | //input[@id = "cpfOrCNPJ"] | //div[contains(text(), "This check is taking longer than expected.")] | //div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")] | //input[@value = "Verify you are human"] | //div[@id = "turnstile-wrapper"]//iframe'));
        $this->saveResponse();

        /*
        if ($verify = $this->waitForElement(WebDriverBy::xpath('//input[@value = "Verify you are human"]'), 0)) {
            $verify->click();
//            $this->driver->executeScript("document.querySelector('input[value = \"Verify you are human\"]').click()");

            $this->waitForElement(WebDriverBy::xpath('//a[@id = "botao_entrar"] | //input[@id = "cpfOrCNPJ"] | //div[contains(text(), "This check is taking longer than expected.")] | //div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 30);
            $this->saveResponse();
        }
        */

        /*
        if ($this->waitForElement(WebDriverBy::xpath('//a[@id = "botao_entrar"]'), 0)) {
            $this->driver->executeScript("document.getElementById('botao_entrar').click()");
        } else*/ if (!$res) {
            $this->checkErrors();
        }

        if ($iframe = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'turnstile-wrapper']//iframe"), 5)) {
            $this->driver->switchTo()->frame($iframe);
            $this->saveResponse();

            if ($captcha = $this->waitForElement(WebDriverBy::xpath("//div[@class = 'ctp-checkbox-container']/label"), 10)) {
                $this->saveResponse();
                $captcha->click();
                $this->logger->debug("delay -> 15 sec");
                $this->saveResponse();
                sleep(15);

                $this->driver->switchTo()->defaultContent();
                $this->saveResponse();
            }
        }

        // Step 2 - Set Login
//        $this->saveResponse();

        if ($this->http->FindSingleNode('//div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Solving is in process...")]')) {
            $this->waitForElement(WebDriverBy::xpath('//input[@id = "cpfOrCNPJ"] | //div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 20);
            $this->saveResponse();
        }

        return $this->enterLogin();
    }

    public function enterLogin()
    {
        $this->logger->notice(__METHOD__);

        $dateOfBirth = explode('.', $this->AccountFields['Login2']);
        $this->logger->debug(var_export($dateOfBirth, true), ["pre" => true]);

        try {
            $login = $this->waitForElement(WebDriverBy::xpath('//input[@id = "cpfOrCNPJ"]'), 0);
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if (!$login) {
            $this->logger->error("something went wrong");
            $this->checkProxyErrors();
            // debug
            if ($this->waitForElement(WebDriverBy::id('botao_entrar'), 0)) {
                $this->driver->executeScript("document.getElementById('botao_entrar').click()");
                $this->waitForElement(WebDriverBy::id('txt-identifier'), 5);
                $this->saveResponse();
            }

            if ($this->waitForElement(WebDriverBy::xpath('//input[@id = "cpfOrCNPJ"]'), 0, false)) {
                throw new CheckRetryNeededException(3, 0);
            }

            if (
                $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Checking if the site connection is secure")]'), 0)
            ) {
                $this->saveResponse();
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $this->DebugInfo = self::ERROR_REASON_BLOCK;

                if ($this->http->FindSingleNode('//div[not(contains(@style, "display: none;")) and @class="hcaptcha-box"]//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")] | //input[@value = "Verify you are human"]')) {
                    $this->markProxyAsInvalid();
                }

                throw new CheckRetryNeededException(3, 0);
            }

            return $this->checkErrors();
        }
        $login->sendKeys($this->AccountFields['Login']);

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Entrar") and not(@disabled)]'), 5);

        if (!$button) {
            $this->logger->error("login step, something went wrong");
            $this->saveResponse();

            $this->needToUpdateProfile();

            return $this->checkErrors();
        }
        $button->click();

        $this->waitForElement(WebDriverBy::xpath('
            //input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"]
            | //input[@formcontrolname = "code"]
            | //a[contains(text(), "Pular")]
            | //p[contains(text(), "Perguntas Secretas")]
            | //h2[contains(text(), "404 - File or directory not found.")]
            | //div[contains(text(), "Erro desconhecido.")]
            | //small[contains(@class, "msg-error")]
        '), 15);
        $this->saveResponse();

        // Check Credentials
        $error = $this->waitForElement(WebDriverBy::xpath('//small[contains(@class, "msg-error")]'), 0);

        if ($error) {
            $message = $error->getText();
            $this->logger->error($message);

            $this->markProxySuccessful();

            if (stripos($message, 'Documento inválido') !== false) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($skipProfileUpdate = $this->waitForElement(WebDriverBy::xpath('
                //a[contains(text(), "Pular")]
                | //p[contains(text(), "Perguntas Secretas")]
                | //input[@formcontrolname = "code"]'), 0)
        ) {
            $this->saveResponse();

            if ($this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "code"]'), 0)) {
                $this->twoStepVerification();

                return false;
            }
            // Security questions
            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Perguntas Secretas")]'), 0)) {
                $this->parseQuestions();

                return false;
            }

            if ($closeBtn = $this->waitForElement(WebDriverBy::xpath('//span[normalize-space(text()) = "X"]'), 0)) {
                $this->driver->executeScript("document.querySelector('div.simple-modal.show').classList.remove('show');");
                sleep(1);
                $skipProfileUpdate = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Pular")]'), 0);
                $this->saveResponse();
            }
            $this->needToUpdateProfile();

            $this->logger->debug("Click 'Pular'");
            $skipProfileUpdate->click();
            $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"] | //input[@id = "cpfOrCNPJ"]'), 10);

            if ($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Aguarde . . ")] | /img[@alt="Dotz - Sua moeda para a vida render mais"]'), 0)) {
                $this->saveResponse();
                $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"] | //input[@id = "cpfOrCNPJ"]'), 10);
                $this->saveResponse();
            }
            $button = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"]'), 0);

            if (!$button && $this->waitForElement(WebDriverBy::xpath('//input[@id = "cpfOrCNPJ"]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }
        } else {
            $button = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"]'), 0);
        }
        $this->saveResponse();

        if (!$button) {
            $this->logger->error("something went wrong");

            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Para sua experiência no programa ficar ainda melhor, estamos atualizando e")]'), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath('//strong[contains(text(), "Conta bloqueada!")]'), 0)) {
                throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Este número está vinculado a outra conta")]'), 0)) {
                throw new CheckException($message->getText() . " " . $this->http->FindSingleNode('//p[contains(text(), "Este número está vinculado a outra conta")]/following-sibling::p[contains(text(), "Utilize outro número de telefone ou entre em contato")]'), ACCOUNT_INVALID_PASSWORD);
            }

            $this->needToUpdateProfile();

            // retries
            if (
                count($this->http->FindNodes('//img[@alt = "Dotz - Sua moeda para a vida render mais"]')) == 4
            ) {
                $this->logger->error("something went wrong");

                // Identificador informado (00138127344) está com algum problema, entre em contato com a Central de Atendimento.
                if (
                    in_array($this->AccountFields['Login'], [
                        '06316380674', // AccountID: 5919168
                        '47914645400', // AccountID: 5787661
                        '79600697515', // AccountID: 2376280
                        '08883153766', // AccountID: 5999791
                        '71679979868', // AccountID: 5611851
                        '90065441168', // AccountID: 6030697
                        '44311680015', // AccountID: 5980069
                        '75900556468', // AccountID: 5788929
                        '07725030441', // AccountID: 5787641
                        '10993390498', // AccountID: 5788925
                        '07681253408', // AccountID: 5621886
                        '07775422777', // AccountID: 5913804
                        '04146687748', // AccountID: 5653697
                        '09958489473', // AccountID: 5654411
                        '08793075448', // AccountID: 5787658
                        '11884149766', // AccountID: 2598103
                        '04896260686', // AccountID: 3606506
                        '09386584921', // AccountID: 6282655
                    ])
                ) {
                    throw new CheckException("Identificador informado ({$this->AccountFields['Login']}) está com algum problema, entre em contato com a Central de Atendimento.", ACCOUNT_PROVIDER_ERROR);
                }

                throw new CheckRetryNeededException(2, 0);
            }

            if (
                $this->http->currentUrl() == 'https://api.dotz.com.br/signup/ui/default/redirect'
                && $this->http->FindSingleNode('//span[contains(text(), "Para se cadastrar")]')
            ) {
                throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->logger->debug("hard code");
            // Identificador informado (00138127344) está com algum problema, entre em contato com a Central de Atendimento.
            if (
                in_array($this->AccountFields['Login'], [
                    '00138127344', // AccountID: 4815080
                    '02969097184', // AccountID: 5564724
                ])
            ) {
                throw new CheckException("Identificador informado ({$this->AccountFields['Login']}) está com algum problema, entre em contato com a Central de Atendimento.", ACCOUNT_PROVIDER_ERROR);
            }

            return $this->checkErrors();
        }

        // Step 2 - Auth
        return $this->enterPassword($dateOfBirth);
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == 'Question') {
            $this->saveResponse();

            return $this->parseQuestions();
        }

        if ($step == '2fa') {
            $this->saveResponse();

            return $this->twoStepVerification();
        }

        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logoff')]"), 5);
        $this->saveResponse();

        return true;
    }

    public function Login()
    {
        $this->saveResponse();
        sleep(5);

        // first layer - acceptCookies
        if ($acceptCookies = $this->waitForElement(WebDriverBy::xpath("//button[@id='onetrust-accept-btn-handler' and normalize-space() = 'Aceitar todos os cookies']"), 0)) {
            $this->saveResponse();
            $acceptCookies->click();
        }
        // next layer
        if ($notShowTour = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'btn-not-show-tour']"), 0)) {
            $this->saveResponse();
            $this->driver->executeScript("document.getElementById('btn-not-show-tour').click()");
        }

        $sleep = 50;
        $startTime = time();

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
            $this->saveResponse();

            if ($this->loginSuccessful()) {
                $this->markProxySuccessful();
                $this->captchaReporting($this->recognizer);

                return true;
            }
            // Invalid credentials
            $error = $this->waitForElement(WebDriverBy::xpath('
                //small[contains(@class, "msg-error")]
                | //div[@class = "simple-modal-wrapper show"]//p
            '), 0);

            if ($error) {
                $message = $error->getText();
                $this->markProxySuccessful();

                if (
                    // Parte da data de nascimento informada é inválida.
                    $this->http->FindPreg('/(Parte da data de nascimento informada é inválida\.)/u', false, $message)
                    // Senha inválida. Você tem 1 tentativas.
                    || $this->http->FindPreg('/(Senha inválida. Você tem \d+ tentativas\.)/u', false, $message)
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $this->http->FindPreg('/^\*\*(Conta bloqueada\.)\*\*$/u', false, $message)
                    || $this->http->FindPreg('/^Desbloqueie sua conta clicando no botão abaixo/', false, $message)
                ) {
                    $this->captchaReporting($this->recognizer);

                    throw new CheckException("Conta bloqueada.", ACCOUNT_LOCKOUT);
                }

                if ($message == '**Recaptcha deve ser informado.**') {
                    $this->captchaReporting($this->recognizer, false);

                    throw new CheckRetryNeededException(2, 3, self::CAPTCHA_ERROR_MSG);
                }

                return false;
            }// if ($error)
            // A sua solicitação de resgate de senha expirou. Por favor, faça uma nova solicitação clicando em “esqueci a senha”.
            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'A sua solicitação de resgate de senha expirou.')]"), 0)) {
                throw new CheckException("A sua solicitação de resgate de senha expirou.", ACCOUNT_INVALID_PASSWORD);
            }
            // Ops! As tentativas para entrar na sua Conta Dotz foram excedidas e, para sua segurança, o login foi bloqueado.
            if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Ops! As tentativas para entrar na sua Conta Dotz foram excedidas e, para sua segurança, o login foi bloqueado.')]"), 0)) {
                throw new CheckException("Ops! As tentativas para entrar na sua Conta Dotz foram excedidas e, para sua segurança, o login foi bloqueado.", ACCOUNT_LOCKOUT);
            }

            $this->needToUpdateProfile();

            sleep(1);
        }

        $this->saveResponse();

        if ($this->loginSuccessful()) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            return true;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
//        $browser = new HttpBrowser("none", new CurlDriver());
//        $this->http->brotherBrowser($browser);
//        $cookies = $this->driver->manage()->getCookies();
//        foreach ($cookies as $cookie)
//            $browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
//
//        $browser->GetURL("https://www.dotz.com.br/Dashboard.aspx");

        $this->SetBalance($this->http->FindSingleNode("//p[@id = 'saldo_atual']"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@id = 'nome_usuario']", null, false, '/,\s*(.+)/u')));

        // AccountID: 4131095, 2379950
        $crookedAccounts = false;

        if (
//            !$this->http->FindNodes("//p[@id='sair_dotz']")
//            && $this->http->FindNodes("//header[@class = 'region-switcher-header']")
//            && ($button = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Brasília")]'), 0))
            $this->http->currentUrl() == 'https://dotz.com.br/Dashboard.aspx'
        ) {
            $crookedAccounts = true;
            //            $button->click();
            $this->waitForElement(WebDriverBy::xpath("//li[@id = 'minha_conta']"), 10);
            $this->saveResponse();

            $this->http->GetURL("https://dotz.com.br/dashboard/extrato");
        } elseif (strstr($this->http->currentUrl(), 'https://troque.dotz.com.br/')) {
            $this->waitForElement(WebDriverBy::xpath("//li[@id = 'minha_conta']"), 10);
            $this->saveResponse();

            $this->http->GetURL("https://troque.dotz.com.br/dashboard/extrato");
        } else {
            if ($this->http->currentUrl() != 'https://www.dotz.com.br/Dashboard.aspx') {
                $this->http->GetURL("https://www.dotz.com.br/Dashboard.aspx");
            }
        }

        // Balance - Seu saldo é de
        $this->waitForElement(WebDriverBy::xpath("//p[@id = 'saldo_atual'] | //input[@id = 'cpfOrCNPJ']"), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//input[@id = "cpfOrCNPJ"]/@id')) {
            return;
            $this->enterLogin();
            $this->waitForElement(WebDriverBy::xpath("//p[@id = 'saldo_atual']"), 5);
            $this->saveResponse();

            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

                $this->http->GetURL("https://dotz.com.br/dashboard/extrato");
                $this->waitForElement(WebDriverBy::xpath("//p[@id = 'saldo_atual']"), 10);
                $this->saveResponse();
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("exception: " . $e->getMessage());

                return;
            }
        }

        $this->SetBalance($this->http->FindSingleNode("//p[@id = 'saldo_atual']"));
        // Name
        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@id = 'nome_usuario']", null, false, '/,\s*(.+)/u')));
        }
        // ExpiringBalance
        //$this->SetProperty("ExpiringBalance", trim(implode('', $browser->FindNodes("//div[@id = 'tourVencimento']//div[@class = 'content']/text()"))));
        // Expiration Date  // refs #12191
        $exp = $this->http->FindSingleNode("//div[@id = 'tourVencimento']//div[@class = 'content']/div[contains(text(), 'Em:')]", null, true, "/:\s*([^<]+)/");

        if ($exp) {
            $exp = $this->ModifyDateFormat($exp);

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }

        if ($crookedAccounts === true) {
//            $this->http->GetURL("https://dotz.com.br/dashboard/meus-dados");
            $this->http->GetURL("https://dotz.com.br/dashboard/meus-dados-pessoais");
        } else {
            try {
                $url = "/dashboard/meus-dados-pessoais";
                $this->http->NormalizeURL($url);
                $this->http->GetURL($url);
            } catch (TimeOutException $e) {
                $this->increaseTimeLimit();
                $this->logger->error("TimeoutException: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
            } catch (UnknownServerException | NoSuchWindowException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
        }
        $this->waitForElement(WebDriverBy::xpath("//input[@id = 'txtNomeCompleto']"), 10);

        try {
            $this->saveResponse();
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        // Name
        $name = $this->http->FindSingleNode("//input[@id = 'txtNomeCompleto']/@value");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    private function parseQuestions()
    {
        $this->logger->notice(__METHOD__);
        $sq = $this->waitForElement(WebDriverBy::xpath('//div[@class="questions-div"]/label'), 10);
        $this->saveResponse();

        if (!$sq && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Carregando...")]'), 0)) {
            if ($this->AccountFields['Login'] == '10182983714') {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }
        $questions = $this->http->FindNodes('//div[@class="questions-div"]/label');

        foreach ($questions as $question) {
            if (!isset($this->Answers[$question])) {
                $this->AskQuestion($question, null, "Question");
                $this->holdSession();

                return false;
            }
            // todo: enter the answer
        }

        return true;
    }

    private function twoStepVerification()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Um código de validação foi enviado para o número")]'), 0);
        $input = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "code"]'), 0);
        $continuar = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continuar")]'), 0);
        $this->saveResponse();

        if (!isset($this->Answers[$question->getText()])) {
            $this->AskQuestion($question->getText(), null, "2fa");
            $this->holdSession();

            return false;
        }
        $input->clear();
        $input->sendKeys($this->Answers[$question->getText()]);
        unset($this->Answers[$question->getText()]);
        $continuar->click();

        sleep(5);

        $this->sendNotification("2fa // RR");
        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logoff')] | //li[contains(text(), 'Ocorreu um erro no processo de validação de sua transação. Entre em contato com nosso suporte')]"), 0);
        $this->saveResponse();

        if ($error = $this->http->FindSingleNode('//li[
                contains(text(), "Ocorreu um erro no processo de validação de sua transação. Entre em contato com nosso suporte")
                or contains(text(), "Não foi possível confirmar o código informado, tente novamente ou solicite outro código.")
            ]')
        ) {
            $this->AskQuestion($question->getText(), $error, "2fa");
            $this->holdSession();

            return false;
        }

        return true;
    }

    private function needToUpdateProfile()
    {
        $this->logger->notice(__METHOD__);

        if ($this->waitForElement(WebDriverBy::xpath('//p[
                contains(text(), "Por favor, faça a validação do seu e-mail:")
                or contains(text(), "Por favor, faça a validação do seu telefone")
                or contains(text(), "Olá! Para ser um Cliente Dotz, é só preencher os dados abaixo. Vamos lá?")
                or contains(text(), "Precisamos que você forneça alguns dados pessoais para o cadastro")
                or contains(text(), "Utilize outro número de telefone para prosseguir com o cadastro ou entre em contato com a Central de Atendimento.")
            ]
            | //h2[contains(text(), "Informe seu celular")]
            '), 0)
        ) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            $this->saveResponse();
            $this->throwProfileUpdateMessageException();
        }

        if (
            $this->waitForElement(WebDriverBy::xpath('//h1[normalize-space() = "Regulamento"]/following::div[normalize-space() = "Eu aceito os termos e condições da Dotz"]'), 0)
            || (
                count($this->http->FindNodes('//img[@alt = "Dotz - Sua moeda para a vida render mais"]')) >= 3
//                && $this->http->FindSingleNode('//span[contains(text(), "Li e concordo com os")]')
                && $this->http->FindSingleNode('//span[contains(text(), "Li e concordo com a")]')
            )
        ) {
            $this->markProxySuccessful();
            $this->captchaReporting($this->recognizer);

            $this->saveResponse();
            $this->throwAcceptTermsMessageException();
        }

        return false;
    }

    private function checkProxyErrors()
    {
        $this->logger->notice(__METHOD__);
        // Incapsula workaround
        if ($this->http->FindPreg("/Request unsuccessful\. Incapsula incident ID: \d+\-\d+<\/iframe><\/body>/")) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3);
        }
        // This site can’t be reached
        if (
            $this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//p[contains(text(), 'There is something wrong with the proxy server, or the address is incorrect.')]")
        ) {
            $this->DebugInfo = "bad proxy";
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(4, 10);
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]"))
    }

    private function enterPassword($dateOfBirth)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        $this->logger->debug("find number elements...");
        $this->waitForElement(WebDriverBy::xpath("
            //p[contains(text(), 'Digite a sua senha')]/following-sibling::ul/li
            | //p[contains(text(), 'Digite sua senha')]/following-sibling::ul/li
            | //h1[contains(text(), 'Central de Atendimento Dotz')]
            | //p[contains(text(), 'Digite sua senha')]/following-sibling::div/div/input[@id = 'password']
            | //p[contains(text(), 'Digite sua senha')]/following-sibling::div/input[@id = 'password']
        "), 15);

        try {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }
        $this->saveResponse();

        // provider bug fix
        if ($this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Central de Atendimento Dotz')]"), 0)) {
            throw new CheckRetryNeededException();
        }

        // provider site slow loading
        if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Carregando..')]"), 0)) {
            $this->logger->notice("provider site slow loading, set delay");
            sleep(5);
            $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'btn-number-senha blocoTeclado noselect')]/p"), 15);
            $this->saveResponse();
        }

        $elements = $this->driver->findElements(WebDriverBy::xpath("//p[contains(text(), 'Digite a sua senha') or contains(text(), 'Digite sua senha utilizando o teclado virtual da')]/following-sibling::ul/li"));

        if (!empty($elements)) {
            $this->logger->debug("entering password...");
            $pass = $this->AccountFields['Pass'];
            $password = '';

            for ($i = 0; $i < strlen($pass); $i++) {
                foreach ($elements as $key => $element) {
                    $keys = explode(' ou ', $element->getText());

                    if (count($keys) != 2) {
                        $this->logger->error("wrong element was found");

                        return false;
                    }

                    if (in_array($pass[$i], [$keys[0], $keys[1]])) {
                        $this->logger->debug("{$pass[$i]} -> {$element->getText()}: ({$keys[0]} or {$keys[1]})");
                        $password .= $key;
                        $click = $this->driver->findElement(WebDriverBy::xpath("//p[contains(text(), 'Digite a sua senha') or contains(text(), 'Digite sua senha utilizando o teclado virtual da')]/following-sibling::ul/li[" . ($key + 1) . "]"));

                        if ($click) {
                            $click->click();
                        } else {
                            $this->logger->error("[Password]: value not found");
                        }

                        break;
                    }// if (in_array($pass[$i], array($keys[0], $keys[1])))
                }// foreach ($elements as $key => $element)
            }// for ($i = 0; $i < strlen($pass); $i++)
        // new password filed
        } else {
            $this->logger->debug("new password field: entering password...");
            $pass = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Digite sua senha")]/following-sibling::div/div/input[@id = "password"]'), 0);

            if (!$pass) {
                $this->logger->error("something went wrong");
                // IP ... não reconhecido. Impossível realizar login.
                if ($this->waitForElement(WebDriverBy::xpath("//div[@id = 'sub-title-msg' and contains(text(), 'não reconhecido. Impossível realizar login.')]"), 0)) {
                    throw new CheckRetryNeededException(3);
                }

                return false;
            }
            $pass->sendKeys($this->AccountFields['Pass']);
        }

        $this->saveResponse();
        $questions = [
            'Confirme seu DIA de nascimento:'                             => 0,
            'Confirme seu MÊS de nascimento:'                             => 1,
            'Confirme seu Mês de nascimento:'                             => 1,
            'Confirme seu ANO ( Os dois últimos dígitos ) de nascimento:' => 2,
            'Confirme seu ANO ( Os dois últimos ) de nascimento:'         => 2,
        ];
        $question = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Confirme seu')]"), 10);
        $this->saveResponse();

        if ($question && isset($questions[trim($question->getText())])) {
            $questionId = $questions[trim($question->getText())];
            $this->logger->debug("Question type: {$questionId} / Question: {$question->getText()} ");

            if (!isset($dateOfBirth[$questionId])) {
                $this->logger->error("question not found");

                return false;
            }
            $dateInput = $this->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "birthday" or @formcontrolname = "birthdate_part"]'), 0);
            $this->saveResponse();

            if (!$dateInput) {
                $this->logger->error("date input not found");

                return false;
            }
            $dateInput->sendKeys($dateOfBirth[$questionId]);
        }

        $button = $this->waitForElement(WebDriverBy::xpath('
            //button[not(@disabled) and contains(text(), "Entrar")] 
            | //button[@id="submit-button"]
        '), 0);
        // Captcha appears both before and after the click
        if (!$button && $this->waitForElement(WebDriverBy::xpath('
            //a[
                contains(text(), "Could not connect to proxy related to the task")
                or contains(text(), "Proxy IP is banned by target service")
                or contains(text(), "Could not connect to proxy related to the task")
            ] 
        '), 40)) {
            $this->parseReCaptchaInit();
        }
        $this->saveResponse();

        if (!$button) {
            sleep(2);

            if ($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0)) {
                if ($this->waitForElement(WebDriverBy::xpath('
                    //a[
                        contains(text(), "Could not connect to proxy related to the task")
                        or contains(text(), "Proxy IP is banned by target service")
                        or contains(text(), "Could not connect to proxy related to the task")
                    ] 
                '), 40)) {
                    $this->parseReCaptchaInit();
                }
                $this->saveResponse();
            }

            $button = $this->waitForElement(WebDriverBy::xpath('
                //button[not(@disabled) and contains(text(), "Entrar")] 
                | //button[@id="submit-button"]
            '), 0);
            $this->saveResponse();
        }

        if (!$button) {
            $this->logger->error("something went wrong");

            return false;
        }
        $button->click();

        return true;
    }

    private function parseReCaptchaInit()
    {
        $this->logger->notice(__METHOD__);
        $recaptchaKey = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "recaptcha-wrapper")]/re-captcha'), 5);
        $this->saveResponse();

        if ($recaptchaKey && $recaptchaKey->getText() != 'Solved') {
            $recaptcha = $this->parseReCaptcha($recaptchaKey->getAttribute('sitekey'));

            if ($recaptcha === false) {
                return false;
            }
            $this->logger->notice("Remove iframe");
            //$this->driver->executeScript("$('div.g-recaptcha iframe').remove();");
//            $this->driver->executeScript("$('textarea#g-recaptcha-response').val('{$recaptcha}');
//            $('form button.btn-orange[type=submit]').prop('disabled', false);");
            //
            $this->driver->executeScript("document.getElementById('g-recaptcha-response').val = '{$recaptcha}';
            document.querySelector('form button[type=submit]').disabled = false;");

            return true;
        }

        return false;
    }

    private function parseReCaptcha($key)
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

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->saveResponse();

            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        // retries
        if ($this->http->FindPreg("/The requested URL was rejected. Please consult with your administrator./")
            || ($error = $this->waitForElement(WebDriverBy::xpath("
                    //h2[contains(text(), '404 - File or directory not found.')]
                    | //div[contains(text(), 'Erro desconhecido.')]
                    | //span[contains(text(), 'This site can’t be reached')]
                    | //h1[contains(text(), 'Unable to connect')]
                "), 0))
        ) {
            $message = null;

            if (isset($error) && $error->getText() == 'Erro desconhecido.') {
                $message = 'Erro desconhecido.';
            }

            throw new CheckRetryNeededException(3, 0, $message);
        }
        // Server Error
        if (
            $this->http->FindPreg("#Server Error in '/' Application#")
            || $this->http->FindSingleNode('//h1[contains(text(), "503 Service Temporarily Unavailable")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG);
        }

        if ($error = $this->waitForElement(WebDriverBy::xpath("//h2[text()='Algo deu errado :(']"), 0)) {
            throw new CheckRetryNeededException(3, 10, $error->getText());
        }
        /*
        if($this->waitForElement(WebDriverBy::xpath("//h2[text()='Algo deu errado :(']"), 5))
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        */
        return false;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes("//p[@id='sair_dotz'] | //header[@class = 'region-switcher-header']")
            || $this->http->currentUrl() == 'https://dotz.com.br/home'
        ) {
            $this->markProxySuccessful();

            return true;
        }

        return false;
    }
}
