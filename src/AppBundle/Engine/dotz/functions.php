<?php

class TAccountCheckerDotz extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private $app = null;

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerDotzSelenium.php";

        return new TAccountCheckerDotzSelenium();
    }

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields["Login2"]["Note"] = "e.g. 31.01.80 (with comma)";
    }

    public function LoadLoginForm()
    {
        //$this->http->SetProxy($this->proxyReCaptcha());
        $this->http->removeCookies();
        $this->http->LogHeaders = true;
        // refs #10534
        $dateOfBirth = explode('.', $this->AccountFields['Login2']);
        $this->logger->debug(var_export($dateOfBirth, true), ["pre" => true]);

        if (!stristr($this->AccountFields['Login2'], '.') || count($dateOfBirth) != 3 || strlen($dateOfBirth[2]) != 2) {
            throw new CheckException("To update this Dotz account you need to correctly fill in the 'Date of Birth' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/

        // script workaround
        $this->selenium();

        // VerificationToken header
        $forgeryToken = $this->http->FindSingleNode("//input[@id = 'forgeryToken']/@value");

        $this->app = $this->http->FindSingleNode("//input[@id='hdd-app']/@value");

        $this->http->setDefaultHeader('User-Agent', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/60.0.3112.78 Chrome/60.0.3112.78 Safari/537.36');
        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=UTF-8');

        // check Login
        $this->http->PostURL("https://sso.dotz.com.br/Login/GetAccountInfo", json_encode([
            'identifier' => $this->AccountFields['Login'],
            'app'        => $this->http->FindSingleNode("//input[@id='hdd-app']/@value"),
        ]));
        $response = $this->http->JsonLog();

        $this->checkCredentials();

        if (!$this->http->FindPreg("/\"success\":true/")) {
            $this->logger->error("something went wrong");

            return false;
        }

        // get keyboard and question
        $this->http->PostURL("https://sso.dotz.com.br/Login/GetVirtualKeyboardPresentationInfo", []);
        $response = $this->http->JsonLog(null, true, true);
        // question
        $questionId = ArrayVal($response["question"], 'id');
        $question = ArrayVal($response["question"], 'description');
        $this->logger->debug("Question type: {$questionId} / Question: {$question} ");
        // input password
        $pass = $this->AccountFields['Pass'];
//        $pass = '';//todo
        $password = '';
        // keyboard
        $mapping = ArrayVal($response, 'mapping', []);

        for ($i = 0; $i < strlen($pass); $i++) {
            foreach ($mapping as $key => $value) {
                $leftKey = ArrayVal($value, 'left');
                $rightKey = ArrayVal($value, 'right');

                if (in_array($pass[$i], [$leftKey, $rightKey])) {
                    $this->logger->debug("{$pass[$i]} -> {$key}: ({$leftKey} or {$rightKey})");
                    $password .= $key;

                    break;
                }// if (in_array($pass[$i], array($leftKey, $rightKey)))
            }// foreach ($mapping as $key)
        }// for ($i = 0; $i < strlen($pass); $i++)

        if (!isset($dateOfBirth[$questionId - 1]) || empty($password)) {
            return false;
        }
        // check password and birth date
        if (isset($forgeryToken)) {
            $this->http->PostURL("https://sso.dotz.com.br/Login/Authenticate", json_encode([
                "App"            => $this->app,
                "Identifier"     => $this->AccountFields['Login'],
                "Password"       => $password,
                "QuestionAnswer" => $dateOfBirth[$questionId - 1],
                "CaptchaResponse"=> "",
                "UrlRedirect"    => "https://dotz.com.br/",
            ]), [
                "X-Requested-With" => "XMLHttpRequest", "Accept" => "*/*",
            ]);
        } else {
            $this->http->Log("[Try new authorization]: forgeryToken was found");
            $data = '{ "authReq":{ "App": "' . $this->app . '/","Identifier": "' . $this->AccountFields['Login'] . '","Password":
     "' . $password . '","QuestionAnswer": "' . $dateOfBirth[$questionId - 1] . '"}}';
            $headers = ["X-Requested-With"  => "XMLHttpRequest",
                "Accept"                    => "*/*",
                "Content-Type"              => "application/json; charset=utf-8",
                "VerificationToken"         => $forgeryToken,
            ];
            $this->http->PostURL("https://sso.dotz.com.br/Login/Authenticate", $data, $headers);
        }

        $response = $this->http->JsonLog();

        if (isset($response->res->showCaptcha) && $response->res->showCaptcha) {
            $captcha = $this->parseReCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->PostURL("https://sso.dotz.com.br/Login/Authenticate", json_encode([
                "App"            => $this->app,
                "Identifier"     => $this->AccountFields['Login'],
                "Password"       => $password,
                "QuestionAnswer" => $dateOfBirth[$questionId - 1],
                "CaptchaResponse"=> $captcha,
                "UrlRedirect"    => "https://dotz.com.br/",
            ]));
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->currentUrl() == 'https://www.dotz.com.br/ops.html?aspxerrorpath=/.aspx'
            || stripos($this->http->currentUrl(), 'EmptyPageLayout.aspx')) {
            throw new CheckRetryNeededException(3, 7);
        }

        return false;
    }

    public function checkCredentials()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if (isset($response->message)) {
            switch ($response->message) {
                case 'Campo E-mail, CPF/CNPJ ou Cartão Dotz Inválido. Por favor, preencha os dados novamente':
                case 'Você ainda não possui Cadastro Dotz.':
                    throw new CheckException($response->message, ACCOUNT_INVALID_PASSWORD);

                    break;

                case "Você possui um Cadastro Dotz, porém ainda não tem uma Senha Dotz. Vamos te redirecionar para uma página de criação de senha segura.":
                    throw new CheckException("Você possui um Cadastro Dotz, porém ainda não tem uma Senha Dotz.", ACCOUNT_PROVIDER_ERROR);

                    break;

                default:
                    // Unknown error
                    $this->logger->error("[ERROR] -> {$response->message}");

                    break;
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg["CookieURL"] = "http://www.dotz.com.br/";

        return $arg;
    }

    public function Login()
    {
        $response = $this->http->JsonLog(null, false);

        if (isset($response->token, $response->url)) {
            $this->http->PostURL($response->url, [
                "token"      => $response->token,
                "originUrl"  => "https://www.dotz.com.br/",
                "app"        => $this->app,
                "redirect"   => "https://www.dotz.com.br/",
            ], [
                'User-Agent '               => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Ubuntu Chromium/60.0.3112.78 Chrome/60.0.3112.78 Safari/537.36',
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Content-Type'              => 'application/x-www-form-urlencoded',
                'Referer'                   => 'https://sso.dotz.com.br/',
                'upgrade-insecure-requests' => '1',
                'connection'                => 'keep-alive',
            ]);
        }// if (isset($response->token, $response->url))

        // provider bug workaround (AccountID: 2435956)
        if ($this->http->FindSingleNode("//h2[contains(text(), 'Algo deu errado :(')]") && $this->http->currentUrl() == 'https://www.dotz.com.br/ops.html?aspxerrorpath=/Dashboard.aspx') {
            $this->http->GetURL("https://www.dotz.com.br/Dashboard/Extrato.aspx");
        }

        if ($this->http->FindSingleNode("//a[contains(@href, 'btnLogout')]/@href")) {
            return true;
        }

        if (isset($response->res->message)) {
            switch ($response->res->message) {
                case "Prezado cliente, seu login não pôde ser efetuado. Por favor, entre em contato com a Central de Atendimento Dotz para mais informações.":
                    throw new CheckException($response->res->message, ACCOUNT_PROVIDER_ERROR);

                    break;

                case "As tentativas de acesso foram esgotadas. Para sua segurança, o login na sua conta está bloqueado. Para desbloqueá-lo, entre em contato com a nossa Equipe de Atendimento.":
                    throw new CheckException($response->res->message, ACCOUNT_LOCKOUT);

                    break;

                default:
                    // Invalid credentials
                    if (strstr($response->res->message, 'Os Dados não conferem. Por favor, tente novamente.')) {
                        throw new CheckException($response->res->message, ACCOUNT_INVALID_PASSWORD);
                    }
                    // Você ainda não possui Cadastro Dotz
                    // = Not a member of dotz
                    if ($this->http->FindPreg('/Você ainda não possui Cadastro Dotz\./', false, $response->res->message)) {
                        throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                    // Unknown error
                    $this->logger->error("[ERROR] -> {$response->res->message}");

                    break;
            }// switch ($response->res->message)
        }// if (isset($response->res->message))
        // provider error
        if ((isset($response->res) && $response->res == 'Erro')
            || $this->http->currentUrl() == 'https://dotz.com.br/Selecione-Sua-Regiao.aspx') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Parse()
    {
//        $this->http->GetURL("https://www.dotz.com.br/Dashboard/Extrato.aspx");
        // Balance - Seu saldo é de
        $this->SetBalance($this->http->FindSingleNode("//span[@id = 'spanSaldo']/strong"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@class = 'nav-user-name']/a")));
        // ExpiringBalance
        $this->SetProperty("ExpiringBalance", trim(implode('', $this->http->FindNodes("//div[@id = 'tourVencimento']//div[@class = 'content']/text()"))));
        // Expiration Date  // refs #12191
        $exp = $this->http->FindSingleNode("//div[@id = 'tourVencimento']//div[@class = 'content']/div[contains(text(), 'Em:')]", null, true, "/:\s*([^<]+)/");

        if ($exp) {
            $exp = $this->ModifyDateFormat($exp);

            if (strtotime($exp)) {
                $this->SetExpirationDate(strtotime($exp));
            }
        }

        $this->http->GetURL("https://www.dotz.com.br/dashboard/meus-dados");
        // Name
        $name = $this->http->FindSingleNode("//input[@id = 'txtNomeCompleto']/@value");

        if (!empty($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        $key = '6Le03QoTAAAAAPZgCcQmt4y8_02xq7SIImbqz88c';
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            $selenium->keepCookies(false);
            //if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
            //    $selenium->useCache();
            $selenium->disableImages();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.dotz.com.br/');
            sleep(1);
            $selenium->http->GetURL('https://www.dotz.com.br/Selecione-Sua-Regiao.aspx?cityId={FE7DDB30-1F65-4C5A-9BBE-BB9A8574D2CE}');

            if ($btnLogin = $selenium->waitForElement(WebDriverBy::id('botao_entrar'), 10)) {
                $btnLogin->click();
            }

            sleep(1);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                throw new CheckRetryNeededException(3, 10);
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
    }
}
