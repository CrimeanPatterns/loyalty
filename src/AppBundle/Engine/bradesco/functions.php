<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBradesco extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use DateTimeTools;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $seleniumAuth = true;
    private $seleniumURL = null;

    private $reportingData = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

//        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
//        $this->setProxyGoProxies();
        $this->setProxyBrightData(null, 'static', 'br');

        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['headers'])) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false) {
            throw new CheckException('Usuário ou Senha incorreto', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 1;

        if (
            !$this->http->GetURL("https://www.livelo.com.br/")
            || strstr($this->http->currentUrl(), 'maintenance')
            || $this->http->Response['code'] == 403
        ) {
            // proxy issues
            if (
                strstr($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT')
                || $this->http->Response['code'] == 403
            ) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 1);
            }

            return $this->checkErrors();
        }

        if ($this->seleniumAuth === false) {
            $this->http->RetryCount = 2;
            $this->http->PostURL("https://www.livelo.com.br/ccstoreui/v1/samlAuthnRequest?encode=true", "{}");
            $response = $this->http->JsonLog();

            if (!isset($response->authnRequestTarget) || !isset($response->authnRequest)) {
                return $this->checkErrors();
            }

            $this->http->Form = [];
            $this->http->FormURL = $response->authnRequestTarget;
            $this->http->SetInputValue("SAMLRequest", $response->authnRequest);
            $this->http->setMaxRedirects(7);
            $this->http->PostForm();
            $this->http->setMaxRedirects(5);

            if (!$this->http->ParseForm("kc-form-login") && !$this->http->ParseForm("form-login")) {
                if (
                    strstr($this->http->Error, 'Network error 28 - Operation timed out after')
                    || $this->http->Response['code'] == 403
                ) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 1);
                }

                return $this->checkErrors();
            }

            /*
            $this->http->FormURL = "https://auth-prd.pontoslivelo.com.br/livelo-login/doLogin";
            */
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            //        $this->http->SetInputValue("profiletype", "customers");
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue('g-recaptcha-response', $captcha);
            //        $this->http->SetInputValue("attribute", "uid");
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//p[contains(text(), 'Um site mais adequado aos interesses de quem mais nos importa, você!')]")
        ) {
            throw new CheckException("Neste momento, estamos implementando novas funcionalidades em nossos sistemas para melhor atendê-lo. Por favor, acesse mais tarde ou contate a central de atendimento.", ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $message = $this->http->FindSingleNode("
                //p[contains(text(), 'Estamos em manutenção,')]
                | //h1[contains(text(), \"Estamos em manutenção :)\")]
                | //strong[contains(text(), 'Este site está em manutenção para te oferecer a melhor experiência')]
                | //strong[contains(text(), 'Estamos passando por uma manutenção no nosso site e app, por conta disso alguns serviços podem apresentar instabilidades')]
            ")
        ) {
            throw new CheckException(preg_replace('/instabilidades.+$/', 'instabilidades', $message), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Estamos em manutenção :)')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('
                //h1[contains(text(), "Oracle Access Manager Operation Error")]
                | //h2[contains(text(), "Error 503--Service Unavailable")]
                | //h1[contains(text(), "502 Bad Gateway")]
            ')
            || $this->http->FindPreg("/<TITLE>Error 503--Service Unavailable<\/TITLE>/")
            || $this->http->FindPreg("/<TITLE>Internal Server Error<\/TITLE>/")
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->getCookiesFromSelenium($this->http->currentUrl());
        $this->http->RetryCount = 0;

        if ($token = $this->http->getCookieByName("oauth_token_secret-storefrontUI", "www.livelo.com.br")) {
            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Authorization"    => "Bearer " . trim(urldecode($token), '"'),
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
                "Origin"           => "https://www.livelo.com.br",
                "Referer"          => "https://www.livelo.com.br/",
            ];

            if ($this->loginSuccessful($headers)) {
                $this->captchaReporting($this->recognizer);

                $this->State['headers'] = $headers;

                return true;
            }
        }

        if ($this->seleniumAuth === false && !$this->http->PostForm()) {
            if (in_array($this->http->Error, [
                'Network error 7 - Unexpected EOF',
                'Network error 56 - Unexpected EOF',
                'Network error 28 - Unexpected EOF',
            ])
            ) {
                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;

        $message = $this->http->FindSingleNode('//span[@class = "message"] | //span[@id = "cpfErrorMsg" and not(contains(@class, "undisplayed")) and not(contains(@style, "display: none;"))] | //span[@id = "passwordErrorMsgLogin" and not(contains(@class, "undisplayed")) and not(contains(@style, "display: none;"))] | //div[@id = "keycloakError"]/span/label')
//            ?? $this->http->FindSingleNode('//div[@id = "popupCreatePasswordTitle" and contains(text(), "Crie uma nova senha")]')
        ;

        if ($this->http->ParseForm(null, '//form[contains(@action, "SAML/post")]') && !$message) {
            // sensor_data workaround
            if (!empty($this->reportingData)) {
                $this->reportingData['success'] = true;
                StatLogger::getInstance()->info("bradesco login attempt", $this->reportingData);
            }

            $this->logger->info('SAML 1', ['Header' => 3]);
            $saml = $this->http->FindSingleNode("//form[contains(@action, 'SAML/post')]//input[@name = 'SAMLResponse']/@value");

            if (!$saml) {
                if (
                    $this->http->FindSingleNode('//div[@id = "popupTitle" and contains(text(), "Conta não cadastrada")]')
                    || $this->http->FindSingleNode('//b[contains(text(), "Parece que você é novo por aqui...")]')
                ) {
                    throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }
            $data = [
                "grant_type"    => "saml_credentials",
                "saml_response" => str_replace(' ', '', $saml),
                "relay_state"   => null,
            ];

            if (!$this->http->PostForm()) {
                $this->logger->error("SAML fail");

                return false;
            }

            $this->logger->info('SAML 2', ['Header' => 3]);
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://www.livelo.com.br/ccstoreui/v1/login/", $data);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            if (!isset($response->access_token)) {
                $this->logger->error("access_token not found");

                if (isset($response->error) && $response->error == 'invalid_request') {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $this->http->Response['code'] == 502
                    && $this->http->FindSingleNode('//h1[contains(text(), "502 Bad Gateway")]')
                ) {
                    throw new CheckRetryNeededException(2, 0);
                }

                return false;
            }

            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Accept-Encoding"  => "gzip, deflate, br",
                "Authorization"    => "Bearer {$response->access_token}",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
                "Origin"           => "https://www.livelo.com.br",
                "Referer"          => "https://www.livelo.com.br/",
            ];

            if ($this->loginSuccessful($headers)) {
                $this->captchaReporting($this->recognizer);

                $this->State['headers'] = $headers;

                return true;
            }

            // provider bug fix
            if (
                $this->http->currentUrl() == 'https://apis.pontoslivelo.com.br/customer/v2/customers/me'
                && $this->http->Response['code'] == 403
                && empty($this->http->Response['body'])
            ) {
                throw new CheckRetryNeededException(2, 0);
            }
        }

        // sensor_data workaround
        if ($this->http->Response['code'] == 403) {
            $this->reportingData['success'] = false;
            StatLogger::getInstance()->info("bradesco login attempt", $this->reportingData);

            throw new CheckRetryNeededException(2, 0);

            return false;
        }

        if ($message) {
            $this->logger->error("[Error]: " . $message);

            if ($message == 'Usuário ou senha incorreto'
                || $message == 'Senha incorreta. Tente novamente ou clique em "Esqueci minha senha" para redefini-la.'
                || $message == 'Senha incorreta. Tente novamente ou clique em "Esqueceu a senha?" para redefini-la.'
                || $message == 'Você demorou muito para entrar. Por favor, recomece o processo de login.'
                || $message == 'E-mail incorreto ou inválido. Corrija e tente novamente.'
                || $message == 'CPF incorreto ou inválido. Corrija e tente novamente.'
                || $message == 'Insira a senha para seguir'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Ocorreu um erro. Tente novamente mais tarde.'
                || $message == 'Ocorreu um erro interno no servidor. Tente novamente mais tarde.'
            ) {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // Precisamos que você crie uma nova senha de acesso.
            if ($message == 'Crie uma nova senha') {
                $this->captchaReporting($this->recognizer);

                $this->throwProfileUpdateMessageException();
            }

            if (strstr($message, "A validação da reCAPTCHA falhou.")) {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(2, 0, self::CAPTCHA_ERROR_MSG);
            }

            // todo: false/positive error - sensor_data issue?
            if ($message == 'Usuário ou senha incorretos. Corrija e tente novamente.') {
                throw new CheckRetryNeededException(3, 0, $message, ACCOUNT_INVALID_PASSWORD);
            }

            $this->DebugInfo = $message;

            return false;
        }

        // strange error
        if ($this->http->FindPreg("/\{\s*\"reasons\"\s*:\s*\[\s*\]\,\s*\"details\"\s*:\s*\{\s*\"msgId\"\s*:\s*\"Id-[a-z\d]+\"\s*\}\s*\}/")) {
            throw new CheckRetryNeededException(2, 0);
        }

        // Estamos com dificuldades para validar algumas informações...
        if (
            $this->http->currentUrl() == 'https://auth-prd.pontoslivelo.com.br/livelo-login/validacao'
            && (
                $this->http->FindPreg('/data-text="Estamos com dificuldades para validar algumas informações... "/')
                || $this->http->FindSingleNode('//h2[contains(text(), "Não foi possível criar sua conta :(")]')
            )
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        // Você precisa criar uma nova senha
        if (
            $this->http->currentUrl() == 'https://auth-prd.pontoslivelo.com.br/livelo-login/ssoCreatePassword'
            && $this->http->FindPreg('/data-text="Você precisa criar uma nova senha"/')
        ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        // AccountID: 5259793, 5536604, 6696697
        if (
            ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && (
            /*$this->http->currentUrl() == 'https://auth-prd.pontoslivelo.com.br/livelo-login/register'
            &&*/ !is_numeric($this->AccountFields['Pass'])
            || strlen($this->AccountFields['Pass']) > 6
        )) {
            throw new CheckException("Senha inválida", ACCOUNT_INVALID_PASSWORD);
        }

        if (
            $this->http->currentUrl() == 'https://auth-prd.pontoslivelo.com.br/livelo-login/register'
            && is_numeric($this->AccountFields['Pass'])
        ) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            strstr($this->seleniumURL, 'https://acesso.livelo.com.br/auth/realms/LIV_PF/login-actions/authenticate')
            || $this->seleniumURL === 'https://www.livelo.com.br/myaccount'
        ) {
            throw new CheckRetryNeededException(2, 5);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me/balance");

        if ($this->http->Response['code'] == 502) {
            throw new CheckRetryNeededException(3, 3);
        }

        $response = $this->http->JsonLog();
        // Balance - pontos
        $this->SetBalance($response->amount ?? null);

        // Pontos que não expiram
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me/balance-never-expires");
        $response = $this->http->JsonLog();
        $this->SetProperty("PointsDoNotExpire", $response->amount ?? null);

        // Pontos à receber
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me/balance-for-future");
        $response = $this->http->JsonLog();
        $this->SetProperty("PointsToReceive", $response->amount ?? null);

        // Pontos expirados
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me/balance-expired/12");
        $response = $this->http->JsonLog();
        $this->SetProperty("Expired", $response->amount ?? null);

        // Expiration date  // refs #14795, refs #14795 https://redmine.awardwallet.com/issues/14795#note-5
        $this->logger->info('Expiration date', ['Header' => 3]);

        if (!$this->Balance) {
            return;
        }
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me/balance-to-expire/2147483647");
        $response = $this->http->JsonLog();
        $points = $response->points ?? [];

        foreach ($points as $point) {
            $expDate = $point->expirationDate ?? null;
            $value = $point->amount ?? null;

            if (
                $value > 0
                && (!isset($exp) || strtotime($expDate) < $exp)
            ) {
                // Expiring Balance
                $this->SetProperty("ExpiringBalance", $value);

                if ($exp = strtotime($expDate)) {
                    $this->SetExpirationDate($exp);
                }
            }
        }// foreach ($points as $point)
    }

    public function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $sensorPostUrl = $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#");

        if (!$sensorPostUrl) {
            $this->logger->error("sensorDataUrl not found");

            return false;
        }

        $this->http->NormalizeURL($sensorPostUrl);

        $sensorData = [
            "7a74G7m23Vrp0o5c9367611.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408833,4877216,1536,865,1536,960,1536,415,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8750,0.14553004672,830802438608,0,loc:-1,2,-94,-131,-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,1,1316,-1,0;1,2,0,1,883,883,0;0,2,0,1,1411,-1,0;-1,2,-94,-102,0,2,0,1,1316,-1,0;1,2,0,1,883,883,0;0,2,0,1,1411,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://auth-prd.pontoslivelo.com.br/livelo-login/login;jsessionid=xvDfW1lV_VgSdTtN1gCcTl6XNvR-ZZGHSpuiIJVlYb8L080ukWq9!377745982-1,2,-94,-115,1,32,32,0,0,0,0,1,0,1661604877216,-999999,17775,0,0,2962,0,0,2,0,0,181CF2388AA4866071DEBF2FBEB42F35~-1~YAAQBOHdF3s/jciCAQAAXVNe3witci1JAmSSMlz8CHgyzFFUBgVsZ1S/C/YkUA62jCj+FmTdYI1gbyTzjl0Y4MfwKfIqLKO+onhbM5XU8csu/dxvy3bcviLcr5nL3GtpyoEceCpnV7ccfwkFwNVe1l8nvmpF5iO4XEPCA93BcAciA+T0ZkH6piW77uLABRrlP4KAyPBwSyWeus/Id6cTPHnXG24wtKMJrcUHTKCBcEt8Un/Sn88vcG2AMoN+4U2yFFY5bcPTx1hOv4M6jJVmGomie3vqRO2imGWpgjJI3atfEFo/3dDQ5HX2ilFH7LeWxv5d06jYKd6PgcBBaYt4zLu+yhX9R67I9zxFlO7sy6X+2tmeHrkySO7g0ZHRPcYbV/9OAYutt66xEEXJKfnKdPYEUg==~-1~-1~-1,37775,-1,-1,30261693,PiZtE,79403,102,0,-1-1,2,-94,-106,0,0-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,-1,2,-94,-126,-1,2,-94,-127,8-1,2,-94,-70,-1-1,2,-94,-80,94-1,2,-94,-116,14631576-1,2,-94,-118,98947-1,2,-94,-129,-1,2,-94,-121,;4;-1;0",
        ];

        $secondSensorData = [
            "7a74G7m23Vrp0o5c9367611.75-1,2,-94,-100,Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36,uaend,12147,20030107,en-US,Gecko,5,0,0,0,408833,4877216,1536,865,1536,960,1536,415,1536,,cpen:0,i1:0,dm:0,cwen:0,non:1,opc:0,fc:0,sc:0,wrc:1,isc:0,vib:1,bat:1,x11:0,x12:1,8750,0.330029772165,830802438608,0,loc:-1,2,-94,-131,Mozilla/5.0 (macOS;12.5.1;x86;64;) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.5112.101 Safari/537.36-1,2,-94,-101,do_en,dm_en,t_en-1,2,-94,-105,0,2,0,1,1316,-1,0;1,2,0,1,883,883,0;0,2,0,1,1411,-1,0;-1,2,-94,-102,0,2,0,1,1316,-1,0;1,2,0,1,883,883,0;0,2,0,1,1411,-1,0;-1,2,-94,-108,-1,2,-94,-110,-1,2,-94,-117,-1,2,-94,-111,-1,2,-94,-109,-1,2,-94,-114,-1,2,-94,-103,-1,2,-94,-112,https://auth-prd.pontoslivelo.com.br/livelo-login/login;jsessionid=xvDfW1lV_VgSdTtN1gCcTl6XNvR-ZZGHSpuiIJVlYb8L080ukWq9!377745982-1,2,-94,-115,1,32,32,0,0,0,0,695,0,1661604877216,22,17775,0,0,2962,0,0,696,0,0,181CF2388AA4866071DEBF2FBEB42F35~-1~YAAQBOHdF5o/jciCAQAA3lRe3wjidWQRJad6ZXdXwR0G0ALBERa1D5gHPgh7Js4a6r5BJIf4FmxuwzzQ73YGUW4wKvkjvZ3emXxH6jAbUhcklndr1RIbqvNvRkA/jb0kuV15Q+TCM5KZ62WK8BNYIgmTKd2pYYgcFBidBtaGCdM1hBDKkD3z3jUV+TwxXXXjYpjQ7T2ibHzL0Jl1g5AOo+D6qwan1BxFPS1hYWdmRp1IVy2+lgdpCuD8l5lAYq0m4txoJulx9wvcDfnvQB35Xs0EOQzIo1OPA8UMjKxgqivPuiGxYbpvT71wAClejVRC+aOqcXlPQ6Zq6BDEULwpJB6/byzG3leU9V6XIIrZaC9UnUjAIJcLQByY65GDje3Pe++LByuyKnLRb4T6lGERHtwrAg==~-1~||1-zemHupGbVw-1-10-1000-2||~-1,39877,324,1966664547,30261693,PiZtE,78828,129,0,-1-1,2,-94,-106,8,2-1,2,-94,-119,-1-1,2,-94,-122,0,0,0,0,1,0,0-1,2,-94,-123,-1,2,-94,-124,0.cacef4bc4d1e3,0.afd1f8481fa97,0.adf2cd2c7d6b3,0.b39a2e18f5d4b,0.90ab16479c3a8,0.66f8248c66744,0.1a58a3eaa4ce4,0.123c58d7dda2b,0.4e10338cd3069,0.3fb4e0fcca2fb;1,0,0,0,1,3,1,1,4,1;0,1,1,3,5,16,3,6,18,1;181CF2388AA4866071DEBF2FBEB42F35,1661604877216,zemHupGbVw,181CF2388AA4866071DEBF2FBEB42F351661604877216zemHupGbVw,1,1,0.cacef4bc4d1e3,181CF2388AA4866071DEBF2FBEB42F351661604877216zemHupGbVw10.cacef4bc4d1e3,228,76,60,83,65,104,8,160,40,61,132,20,138,124,212,203,44,171,116,51,127,151,133,29,187,138,181,198,4,234,69,60,408,0,1661604877911;-1,2,-94,-126,-1,2,-94,-127,11321144241322243122-1,2,-94,-70,-1577612800;862128768;dis;,7;true;true;true;-300;true;30;30;true;false;-1-1,2,-94,-80,5475-1,2,-94,-116,14631576-1,2,-94,-118,143407-1,2,-94,-129,,,0d65734a64c01ce05475c3345af06aef5246ddb8f0161ec4776e6f9c160dfd9b,,,,0-1,2,-94,-121,;4;7;0",
        ];

        if (count($sensorData) != count($secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return false;
        }

        $key = array_rand($sensorData);
        $this->logger->notice("key: {$key}");

        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $this->http->RetryCount = 0;
        $sensorDataHeaders = [
            "Accept"        => "*/*",
            "Content-type"  => "application/json",
        ];
        $sensorData = [
            'sensor_data' => $sensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $sensorData = [
            'sensor_data' => $secondSensorData[$key],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        sleep(1);
        $this->http->RetryCount = 2;

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;

        return true;
    }

    protected function parseCaptcha($url = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('(//form[@id = "form-login"]//div[@class = "g-recaptcha"]/@data-sitekey | //div[@id = "recaptcha-enabled"]/@data-key)[1]');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $url ?? $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function loginSuccessful($headers)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me", $headers);

        if (isset($this->http->Response["errorCode"]) && $this->http->Response["errorCode"] == 52) {
            $this->http->userAgent = HttpBrowser::FIREFOX_USER_AGENT;
            $this->http->GetURL("https://apis.pontoslivelo.com.br/customer/v2/customers/me", $headers);
        }

        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (empty($response->fullName)) {
            return false;
        }

        $this->markProxySuccessful();

        // Name
        $this->SetProperty("Name", beautifulName($response->fullName ?? null));

        foreach ($headers as $header => $value) {
            $this->http->setDefaultHeader($header, $value);
        }

        return $response->fullName ?? false;
    }

    private function getCookiesFromSelenium($url)
    {
        $this->logger->notice(__METHOD__);

        $cacheKey = 'bradesco_abck';
        /*
        $result = Cache::getInstance()->get($cacheKey);

        if (!empty($result) && $this->attempt == 0) {
            $this->logger->debug("set _abck from cache: {$result}");

            $this->http->setCookie("_abck", $result, ".pontoslivelo.com.br");

            return null;
        }
        */

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $key = rand(0, 7);
            $this->DebugInfo = "key: {$key}";

            switch ($key) {
                case 0:
                    $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                    $selenium->setKeepProfile(true);
                    $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();

                    break;

                case 1:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);

                    break;

                case 2:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->seleniumOptions->userAgent = null;

                    break;

                case 3:
                    $selenium->useFirefox();
                    $selenium->setKeepProfile(true);
                    $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();

                    break;

                case 4:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

                    break;

                case 5:
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

                    break;

                case 6:
                    $selenium->UseSelenium();
                    $resolutions = [
                        [800, 600],
                        [1280, 720],
                        [1280, 768],
                        [1280, 800],
                        [1360, 768],
                        [1366, 768],
                        [1440, 900],
                        [1920, 1080],
                        [2560, 1440],
                    ];

                    if (!isset($this->State['Resolution']) || $this->attempt > 0) {
                        $this->logger->notice("set new resolution");
                        $resolution = $resolutions[array_rand($resolutions)];
                        $this->State['Resolution'] = $resolution;
                    } else {
                        $this->logger->notice("get resolution from State");
                        $resolution = $this->State['Resolution'];
                        $this->logger->notice("restored resolution: " . join('x', $resolution));
                    }
                    $selenium->setScreenResolution($resolution);

                    $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                    $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                    $selenium->setKeepProfile(true);
                    $selenium->disableImages();

                    break;

                case 7:
                    $selenium->useFirefox();
                    $selenium->setKeepProfile(true);

                    break;
            }
//            $selenium->seleniumOptions->recordRequests = true;
//            $selenium->http->setUserAgent($this->http->getDefaultHeader("User-Agent"));

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
//                $selenium->http->GetURL("https://www.livelo.com.br/");
                $selenium->http->GetURL("https://www.livelo.com.br/myaccount");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

//            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath("//span[@data-gtm-event-label='fazer-login']"), 10);
//            $this->savePageToLogs($selenium);
//
//            if (!$loginBtn) {
//                $this->logger->error('Failed to find "login" btn');
//
//                return $this->checkErrors();
//            }
//
//            $loginBtn->click();

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'fake-username']"), 20);

            if (empty($login)) {
                $selenium->driver->executeScript("let login = document.querySelector('input[id = \"fake-username\"]'); if (login) login.style.zIndex = '100003';");
                $selenium->driver->executeScript("let pass = document.querySelector('input[id = \"password\"]'); if (pass) pass.style.zIndex = '100003';");
                $selenium->driver->executeScript("let loginBtn = document.querySelector('button[id = \"btn-login\"], input[id = \"recaptcha-login-btn\"]'); if (loginBtn) loginBtn.style.zIndex = '100003';");
                $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'fake-username']"), 5);
            }
            $this->savePageToLogs($selenium);

            if (empty($login)) {
                $this->logger->error('Failed to find "login" input');

                $this->checkErrors();

                if (
                    $this->http->FindPreg("/<head><\/head><body>.<[^>]+><\/[^>]+><[^>]+><\/[^>]+><\/body>/")
                    || $this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
                    || $this->http->FindPreg('/<(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><(?:span|div|a|pre) id="[^\"]+" style="display: none;"><\/(?:span|div|a|pre)><\/body>/ims')
                ) {
                    $this->markProxyAsInvalid();
                    $retry = true;
                }

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 0);

            if (!$passwordInput) {
                $this->logger->error('Failed to find "password" input');

                return false;
            }

            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $loginButton = $selenium->waitForElement(WebDriverBy::xpath("//*[@id = 'btn-login'] | //input[@id = 'recaptcha-login-btn']"), 0);
            $this->savePageToLogs($selenium);

            if (!$loginButton) {
                $this->logger->error('Failed to find login button');

                return false;
            }

            if ($this->seleniumAuth === true) {
                $captcha = $this->parseCaptcha($selenium->http->currentUrl());

                if ($captcha === false) {
//                    $selenium->driver->executeScript("submitLogin()");
                    $loginButton->click();
                } else {
                    $selenium->driver->executeScript("onSubmitLogin('{$captcha}')");
                }

                $res = $selenium->waitForElement(\WebDriverBy::xpath('
                    //span[@id = "span-firstNameText" and text() != ""]
                    | //span[@id = "cpfErrorMsg" and not(contains(@class, "undisplayed")) and not(contains(@style, "display: none;"))]
                    | //span[@id = "passwordErrorMsgLogin" and not(contains(@class, "undisplayed")) and not(contains(@style, "display: none;"))]
                    | //div[@id = "keycloakError"]/span/label
                    | //div[@id = "popupCreatePasswordTitle" and contains(text(), "Crie uma nova senha")]
                    | //*[contains(text(), "This site can’t be reached")]
                    | //h2[contains(text(), "Atenção: precisamos validar a criação da sua conta")]
                    | //div[@id = "popupTitle" and contains(text(), "Conta não cadastrada")]
                    | //p[contains(text(), "Você já está logado.")]
                    | //div[contains(text(), "Atenção: precisamos validar a criação da sua conta")]
                    | //h1[contains(text(), "Redefinir senha")]
                    | //b[contains(text(), "Parece que você é novo por aqui...")]
                '), 80);
                $this->savePageToLogs($selenium);

                // Precisamos que você crie uma nova senha de acesso.
                try {
                    if ($res) {
                        $message = $res->getText();

                        if (
                            $message == 'Crie uma nova senha'
                            || $message == "Atenção: precisamos validar a criação da sua conta"
                            || $message == "Redefinir senha"
                        ) {
                            $this->captchaReporting($this->recognizer);

                            $this->throwProfileUpdateMessageException();
                        }

                        if ($message == "This site can’t be reached") {
                            $this->markProxyAsInvalid();
                            $retry = true;
                        }

                        if ($message == 'Você já está logado.') {
                            $retry = true;
                        }
                    }
                } catch (Facebook\WebDriver\Exception\StaleElementReferenceException | StaleElementReferenceException $e) {
                    $this->logger->error("StaleElementReferenceException: " . $e->getMessage());
                }
            }// if ($this->seleniumAuth === true)

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == '_abck' && $cookie['domain'] == '.pontoslivelo.com.br') {
                    $result = $cookie['value'];
                    $this->logger->debug("set new _abck: {$result}");
                    Cache::getInstance()->set($cacheKey, $result, 60 * 60 * 20);

                    $this->http->setCookie("_abck", $result, ".pontoslivelo.com.br");
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");

            if (strstr($selenium->http->currentUrl(), 'https://www.livelo.com.br/SAML/post')) {
                $this->logger->info('SAML 1', ['Header' => 3]);
                $saml = $this->http->FindPreg("/getSamlResponse\(\)\s*\{\s*return\s*\"([^\"]+)/");

                if (!$saml) {
                    return false;
                }
                $data = [
                    "grant_type"    => "saml_credentials",
                    "saml_response" => str_replace(' ', '', $saml),
                    "relay_state"   => null,
                ];

                if (!$this->http->PostForm()) {
                    $this->logger->error("SAML fail");

                    return false;
                }

                $this->logger->info('SAML 2', ['Header' => 3]);
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://www.livelo.com.br/ccstoreui/v1/login/", $data);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
            }

            // Estamos com dificuldades para validar algumas informações...
            if (
                strstr($selenium->http->currentUrl(), 'https://auth-prd.pontoslivelo.com.br/livelo-login/validacao')
                && (
                    $this->http->FindPreg('/data-text="Estamos com dificuldades para validar algumas informações... "/')
                    || $this->http->FindSingleNode('//h2[contains(text(), "Não foi possível criar sua conta :(")]')
                )
            ) {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }

            // Você precisa criar uma nova senha
            if (
                $selenium->http->currentUrl() == 'https://auth-prd.pontoslivelo.com.br/livelo-login/ssoCreatePassword'
                && $this->http->FindPreg('/data-text="Você precisa criar uma nova senha"/')
            ) {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }
        } catch (
            NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } finally {
            // close Selenium browser
            $proxy = "goproxy:";

            $this->reportingData = [
                //                "success"        => !$retry,
                "proxy"          => $proxy . $selenium->http->getProxyAddress(),
                "browser"        => $selenium->seleniumRequest->getBrowser() . ":" . $selenium->seleniumRequest->getVersion(),
                "userAgentStr"   => $selenium->http->userAgent,
                "resolution"     => ($selenium->seleniumOptions->resolution[0] ?? null) . "x" . ($selenium->seleniumOptions->resolution[1] ?? null),
                "attempt"        => $this->attempt,
                "isWindows"      => stripos($this->http->userAgent, 'windows') !== false,
                "config"         => $key,
            ];

            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return null;
    }
}
