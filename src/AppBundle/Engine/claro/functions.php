<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerClaro extends TAccountChecker
{
    use ProxyList;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->attempt == 2) {
            $this->setProxyGoProxies(null, "br");
        } else {
            $this->setProxyBrightData(null, "static", "br");
        }
    }

    public function LoadLoginForm()
    {
        // Claro login form have been changed
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("To update this Claro (Claro Clube) account you need to change your credentials (please use an email address as a login). To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        // Utilize no Mínimo 8 dígitos
        if (strlen($this->AccountFields['Pass']) < 8) {
            throw new CheckException("Senha Incorreta", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
//        $this->http->GetURL("https://minhaclaro.claro.com.br");
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://minhaclaro.claro.com.br/mcpf_version.js?_=" . time() . date("B"));
        $this->http->RetryCount = 2;
        $packageSite = $this->http->FindPreg("/packageSite=\"([^\"]+)/");

        if (!$packageSite) {
            $this->logger->error("packageSite not found");

            $this->checkConnectionErrors();

//            $this->http->GetURL("http://claro.com.br/portal/site/MinhaClaro/WelcomeHome");
//
//            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Olá! Nosso site está em atualização neste momento e muito em breve estará ativo novamente.')]")) {
//                throw new CheckException("Olá! Nosso site está em atualização neste momento e muito em breve estará ativo novamente.", ACCOUNT_PROVIDER_ERROR);
//            }

            return false;
        }
        $this->http->GetURL("https://minhaclaro.claro.com.br/{$packageSite}/index.html#.html/autenticacao/email/login");

        if ($this->http->Response['code'] != 200) {
            return false;
        }

        /*        $this->http->GetURL("https://auth.netcombo.com.br/authorize?client_id=Area_Cliente&response_type=code&scope=openid+minha_net&redirect_uri=https%3A%2F%2Fwww.claro.com.br%2Fcredencial%2Fcallback");

        if (!$this->http->ParseForm("loginForm")) {
            return false;
        }
//        $this->http->SetInputValue("inputEmail", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        $data = [
            "Email"         => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "client_id"     => "Area_Cliente",
            "response_type" => "code",
            "scope"         => "openid minha_net",
            "redirect_uri"  => "https://www.claro.com.br/credencial/callback",
            "authMs"        => "EP,UP,DOCP,OTP",
            "Auth_method"   => "EP",
        ];
        $this->http->PostURL("https://auth.netcombo.com.br/login", $data);

        if (strstr($this->http->currentUrl(), 'https://auth.netcombo.com.br/web/permissions.html?client_name=MinhaClaroDIG')) {
            $data = [
                "auth_method"      => "EP",
                "response_type"    => "code",
                "confirmed_scopes" => "openid minha_net",
                "confirmed_claims" => "",
                "nonce"            => "qkN6BFK9k1bH1n7Z",
                "scope"            => "openid minha_net",
                "redirect_uri"     => "https://www.claro.com.br/credencial/callback",
                "client_id"        => "Area_Cliente",
                "client_name"      => "Area_Cliente",
            ];
            $this->http->PostURL("https://auth.netcombo.com.br/permissions", $data);
        }

        $headers = [
            "Accept"        => "application/json, text/plain, *
        /*",
            "x-client-key"  => "jD1Sl3aaojjJY2WFiqumDMI9PjkZqWBt",
            "Authorization" => "Bearer " . $this->http->getCookieByName("accesstoken", ".claro.com.br"),
            "Origin"        => "https://www.claro.com.br",
        ];

        $this->http->GetURL("https://api.claro.com.br/app/v1/credentialsmyprofile/customer", $headers);
        $this->http->JsonLog();*/
//        $this->http->SetInputValue("inputEmail", $this->AccountFields['Login']);
//        $this->http->SetInputValue("password", $this->AccountFields['Pass']);

        // AccountID: 5340444
        if ($this->AccountFields['Login'] == 'marcella.martins1@hotmail.com') {
            return $this->newAuth();
        }

        $data = [
            "usuario" => [
                "id"      => $this->AccountFields['Login'],
                "senha"   => $this->AccountFields['Pass'],
                "dominio" => "CLARO_SUE",
            ],
        ];
        $headers = [
            "X-Requested-With"    => "XMLHttpRequest",
            "Content-Type"        => "application/json; charset=UTF-8",
            "Accept"              => "application/json, text/javascript, */*; q=0.01",
            "Application-Key"     => "ASDFKSD7DSFG98DF79F8GH98D7D8G6GSH3G6MCPF",
            "Application-Version" => "1.1.0",
        ];
        $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/SegurancaExterna/v2/autenticacao/realizar", json_encode($data), $headers);

        return true;
    }

    public function newAuth()
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "Email"         => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "auth_method"   => "EP",
            "client_id"     => "MinhaClaroDIG",
            "response_type" => "code",
            "scope"         => "minha_claro_dig openid",
            "redirect_uri"  => "https://minhaclaro.claro.com.br",
            "authMs"        => "UP,EP,DOCP",
        ];
        $this->http->PostURL("https://auth.netcombo.com.br/login", $data);

        if (strstr($this->http->currentUrl(), 'https://auth.netcombo.com.br/web/permissions.html?client_name=MinhaClaroDIG')) {
            $data = [
                "auth_method"      => "EP",
                "response_type"    => "code",
                "confirmed_scopes" => "openid minha_claro_dig",
                "confirmed_claims" => "",
                "nonce"            => "cgaW97uT1FWmOWdS",
                "scope"            => "openid minha_claro_dig",
                "redirect_uri"     => "https%253A%252F%252Fminhaclaro.claro.com.br",
                "client_id"        => "MinhaClaroDIG",
                "client_name"      => "MinhaClaroDIG",
            ];
            $headers = [
                "Accept"       => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
                "Content-Type" => "application/x-www-form-urlencoded",
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://auth.netcombo.com.br/permissions", $data, $headers);
            $this->http->RetryCount = 2;
            $this->http->JsonLog();
        }

        $code = $this->http->FindPreg("/br\?code=([^\&]+)/", null, $this->http->currentUrl());

        if (!$code) {
            if (strstr($this->http->currentUrl(), 'error=temporary_unavailable&error_description=System+is+currently+unavailable.')) {
                throw new CheckException("System is currently unavailable.", ACCOUNT_PROVIDER_ERROR);
            }

            // AccountID: 5340444
            if (strstr($this->http->currentUrl(), 'description%22%3A+%22Dados+de+cadastro%22%7D%7D&auth_method=')) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }
        $data = [
            "autorizacao-oauth" => [
                "codigo-autorizacao" => $code,
            ],
        ];
        $headers = [
            "X-Requested-With"    => "XMLHttpRequest",
            "Content-Type"        => "application/json; charset=UTF-8",
            "Accept"              => "application/json, text/javascript, */*; q=0.01",
            "Application-Key"     => "ASDFKSD7DSFG98DF79F8GH98D7D8G6GSH3G6MCPF",
            "Application-Version" => "1.0.0",
        ];
        $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/SegurancaExternaSSO/v1/sso/codigo/gerar", json_encode($data), $headers);
        $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/SegurancaExterna/v2/autenticacao/sso/realizar", "{}", $headers);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Ocorreu uma falha técnica não esperada
        if ($message = $this->http->FindPreg('/"mensagem":"(Ocorreu uma falha técnica não esperada)"/u')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Registration is not complete
        if ($this->http->FindPreg("/es abaixo para concluir o seu cadastro e acessar a Minha Claro\./ims")) {
            throw new CheckException("Preencha as informações abaixo para concluir o seu cadastro e acessar a Minha Claro.", ACCOUNT_PROVIDER_ERROR);
        }
        // Error 404--Not Found
        if ($this->http->FindPreg("/<H2>Error 404--Not Found<\/H2>/")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // provider error
        if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/No backend server available for connection: timed out after 12 seconds or idempotent set to OFF\./")) {
            throw new CheckException("Falha Técnica. Por favor aguarde alguns instantes e tente novamente, persistindo o problema, entre em contato com o atendimento da Claro informando esta mensagem", ACCOUNT_PROVIDER_ERROR);
        }
        // Site maintenance
        if ($this->http->FindPreg("/Estamos atualizando nossa infraestrutura de autoatendimento/i")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();
        // Invalid credentials
        if (isset($response->erro, $response->erro->motivo->descricao)
            && in_array($response->erro->motivo->descricao, ['Usuário não encontrado', 'Senha Incorreta'])) {
            throw new CheckException($response->erro->motivo->descricao, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg("/\"erro\":null/")) {
            $this->markProxySuccessful();

            return true;
        }
        // provider error
        if (isset($response->erro->mensagem)) {
            $message = $response->erro->mensagem;
            $this->logger->error($message);

            if (in_array($message, ['Ocorreu uma falha técnica não esperada'])) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // account lockout
            if (strstr($message, 'incorreta, atingiu limite máximo de tentativas')
                || strstr($message, ' em situação inválida [BLOQUEADO]')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }
        // Falha Técnica
        if (isset($response->erro->instrucao)
            && strstr($response->erro->instrucao, 'Por favor aguarde alguns instantes e tente novamente, persistindo o problema, entre em contato com o suporte técnico informando esta mensagem')) {
            throw new CheckException("Falha Técnica. Serviço Indisponível. Por favor aguarde alguns instantes e tente novamente, persistindo o problema, entre em contato com o suporte técnico informando esta mensagem", ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // TODO: https://minhaclaro.claro.com.br/mcpf/index.html#.html/claro-clube/meus-pontos

        $headers = [
            "X-Requested-With"    => "XMLHttpRequest",
            "Content-Type"        => "application/json; charset=UTF-8",
            "Accept"              => "application/json, text/javascript, */*; q=0.01",
            "Application-Key"     => "ASDFKSD7DSFG98DF79F8GH98D7D8G6GSH3G6MCPF",
            "Application-Version" => "1.1.0",
        ];
        // get ID profile
        $this->logger->notice("Loading ID profile...");
        $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/UsuarioExterno/v2/usuario/recursos/autorizados/listar", [], $headers);
        $response = $this->http->JsonLog();

        if (isset($response->recurso[0]->id)) {
            // get Account Number
            $data = '{"recurso":{"id":"' . $response->recurso[0]->id . '"}}';
            $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/UsuarioExterno/v2/usuario/recurso/autorizacao/escolher", $data, $headers);
            $response = $this->http->JsonLog(null, 3);
            // Number
            if (isset($response->atendimento->protocolo->numero)) {
                $this->SetProperty("Number", $response->atendimento->protocolo->numero);
            } else {
                $this->logger->error("Number not found");
                // provider error
                if (isset($response->erro->mensagem)
                    && in_array($response->erro->mensagem, ["O produto [21981428750] associado ao recurso [230409] não foi encontrado", 'A operação [${operacao}] não pode ser realizada para o produto [${produto}]', 'O parâmetro [NUMERO_TELEFONE_ATENDIMENTO] associado ao recurso [31351719] não foi encontrado'])) {
                    throw new CheckException("Ocorreu um problema técnico, por favor entre em contato no Atendimento Claro ligando 1052.", ACCOUNT_PROVIDER_ERROR);
                }
                // AccountID: 4753467
                if (
                    isset($response->erro->mensagem, $response->erro->instrucao)
                    && (stripos($response->erro->mensagem, "Titularidade incorreta para o recurso") !== false)
                    && (stripos($response->erro->instrucao, "Verifique se os dados de titularidade para o recurso informado estão corretos e se o mesmo se encontra cadastrado para a titularidade informada") !== false)
                ) {
                    throw new CheckException("Email e/ou senha inválido(s)!", ACCOUNT_INVALID_PASSWORD);
                }
            }

            // get User Info
            $this->logger->notice("Loading User Info...");
            $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/AplicacaoRecurso/v2/aplicacao/sessao/consultar", [], $headers);
            $response = $this->http->JsonLog(null, 3, true);
            // Account Number
            if (isset($response['contexto-aplicacao']['atendimento']['protocolo']['numero'])) {
                $this->SetProperty("Number", beautifulName($response['contexto-aplicacao']['atendimento']['protocolo']['numero']));
            } else {
                $this->logger->error("Number not found");
            }
            // Name
            if (isset($response['contexto-aplicacao']['cliente']['nome'])) {
                $this->SetProperty("Name", beautifulName($response['contexto-aplicacao']['cliente']['nome']));
            } else {
                $this->logger->error("Name not found");
            }

            $headers = [
                "Accept"           => "application/json, text/javascript, */*; q=0.01",
                "Application-Key"  => "ASDFKSD7DSFG98DF79F8GH98D7D8G6GSH3G6MCPF",
                "Content-Type"     => "application/json",
                "X-Requested-With" => "XMLHttpRequest",
            ];
            // set callback
            $this->logger->notice("set callback...");
            $this->http->PostURL("https://minhaclaro.claro.com.br/woa/rest/ClaroClube/v1/fidelizacao/dados/consultar", [], $headers);
            $response = $this->http->JsonLog(null, 3, true);
            // Balance - Saldo em pontos
            if (isset($response['dados-fidelizacao']['saldo-pontos'])) {
                $this->SetBalance($response['dados-fidelizacao']['saldo-pontos']);
            } else {
                $this->logger->notice("Balance not found");
            }
            // Claro Clube desde
            if (isset($response['dados-fidelizacao']['data-inscricao'])) {
                $this->SetProperty("MemberSince", date('d/m/Y', strtotime($response['dados-fidelizacao']['data-inscricao'])));
            } else {
                $this->logger->notice("MemberSince not found");
            }

            $this->logger->info('Expiration Date', ['Header' => 3]);

            if (isset($response['dados-fidelizacao']['previsao-pontos'])) {
                foreach ($response['dados-fidelizacao']['previsao-pontos'] as $expNode) {
                    // Data
                    $date = $expNode['data'];
                    $this->logger->debug("expirar -> " . $date);

                    if (!isset($exp) || $exp > strtotime($date)) {
                        $exp = strtotime($date);

                        if ($exp) {
                            $this->SetExpirationDate($exp);
                        }
                        // Pontos a expirar
                        $this->SetProperty("PointsToExpire", $expNode['pontos-a-expirar']);
                    }// if (!isset($exp) || $exp > strtotime($date))
                }// foreach ($response['dados-fidelizacao']['previsao-pontos'] as $expNode)
            }// if (isset($response['dados-fidelizacao']['previsao-pontos']))

            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if (isset($this->Properties['Name'], $this->Properties['Number'])
                    && $this->http->FindPreg("/Verifique se o cliente informado está correto e se o mesmo se encontra cadastrado/")) {
                    $this->SetBalanceNA();
                }
                // AccountID: 4868467, 6003673
                elseif (
                    !empty($this->Properties['Name'])
//                    && $this->AccountFields['Login'] == 'carlose2509@hotmail.com'
                    && $this->http->FindPreg("/Verifique se todos os parâmetros de entrada esperados foram informados e se os valores preenchidos estão corretos e são compatíveis com o domínio esperado/")
                ) {
                    $this->SetBalanceNA();
                }
                $this->checkErrors();
            }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
        }// if (isset($response->recurso[0]->id))
        else {
            $this->logger->error("ID not found");
            $this->checkErrors();
            /*
             * Por favor, Informe seu número de celular.
             * Iremos verificar se o seu número está ativo em nossa base.
             * Se você não tiver um número Claro, clique no botão não sou cliente.
             */
            if ($message = $this->http->FindPreg('/\{\"erro\":null,\"recurso\":\[\]\}/')) {
                $this->throwProfileUpdateMessageException();
            }
        }
    }

    public function checkConnectionErrors()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if (
            strstr($this->http->Error, 'Network error 35 - OpenSSL SSL_connect: SSL_ERROR_SYSCALL in connection to minhaclaro.claro.com.br:443 ')
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }
    }
}
