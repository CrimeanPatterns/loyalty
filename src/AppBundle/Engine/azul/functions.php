<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAzul extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->setProxyGoProxies(null, "us", null, null, "https://www.voeazul.com.br/br/pt/home");
//        $this->setProxyNetNut();
//        $this->http->SetProxy($this->proxyReCaptchaVultr());

        unset($this->State['ComarchToken']);
    }

    public function IsLoggedIn()
    {
        return false;
    }

    // for Elite Level Tab / itineraries
    public function LoadLoginForm()
    {
        $this->logger->notice(__METHOD__);
        $this->http->removeCookies();

//        if ($this->AccountFields['Login'] == '35942704830') {
//            $this->ParseItineraries();
//
//            return false;
//        }

        unset($this->State['Authorization']);
        /*
        $this->http->GetURL("https://www.voeazul.com.br/us/en/home/minhas-viagens");

        if (
            $this->http->Response['code'] == 403
            // crocked server workaround
            || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false
        ) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://tudoazul.voeazul.com.br/login");
        }

        if ($maintenance = $this->http->FindSingleNode('//p[contains(text(), "We apologize for the inconvenience, but we are currently performing maintenance on our website. Please check back later.")]')) {
            throw new CheckException($maintenance, ACCOUNT_PROVIDER_ERROR);
        }

        if (!in_array($this->http->Response['code'], [200, 503])) {
            $this->sendNotification("refs #15147 exp date failed");

            return false;
        }
        */
        $this->getSensorDataFromSelenium();

        /*
        $data = [
            "channel"  => "W",
            "username" => $this->AccountFields['Login'],
            "password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"          => "application/json, text/plain, *
        /*",
            "Content-Type"    => "application/x-www-form-urlencoded",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://tudoazul.voeazul.com.br/b2c/login", $data, $headers);
        $this->http->RetryCount = 2;
        */

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->access_token)) {
            $this->http->setDefaultHeader("Authorization", "Bearer {$response->access_token}");
            $this->State['Authorization'] = "Bearer {$response->access_token}";

            return true;
        }

        if ($this->http->FindNodes('//p[contains(text(), "Pontos Qualificáveis")]')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Usuário ou senha inválidos.") or contains(text(), "Username or password is invalid.")] | //span[contains(text(), "Senha deve ter no m") and contains(text(), "nimo 6 caracteres")] | //span[contains(text(), "CPF ou N") and contains(text(), "obrigat")] | //span[contains(text(), "TudoAzul deve ter no m") and contains(text(), "nimo 10 caracteres.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Conta suspensa")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->http->FindSingleNode('//h2[contains(text(), "Parece que tivemos um problema para carregar a página")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(urldecode($this->http->getCookieByName("TudoAzul", ".voeazul.com.br")));
        $returnObject = $response->comarch_token->ReturnObject ?? $response;
        // Balance - Você possui ... pontos
        $this->SetBalance($returnObject->RedeemablePoints ?? $this->http->FindSingleNode('//p[normalize-space(text()) = "Pontos Azul"]/following-sibling::p[1]'));
        // Name
        $this->SetProperty("Name", beautifulName($returnObject->Name . " " . $returnObject->LastName));
        // Status
        $this->SetProperty("Status", $returnObject->LoyaltyLevel ?? $this->http->FindSingleNode('//p[contains(text(), "Você é")]/b'));
        // Status expiration date
        $this->SetProperty("StatusExpiration", $this->http->FindSingleNode('//p[contains(text(), "Você é") and b]', null, true, "/Válido até\s*([^\)]+)/"));

        if (isset($this->Properties['Status'])) {
            switch ($this->Properties['Status']) {
                case 'TUDOAZUL.DIA':
                    $this->SetProperty("Status", "Diamante");

                    break;

                case 'TUDOAZUL.SAF':
                    $this->SetProperty("Status", "Safira");

                    break;

                case 'TUDOAZUL.TA+':
                    $this->SetProperty("Status", "Topázio");

                    break;
            }
        }

        // Número TudoAzul
        $this->SetProperty("AccountNumber", $returnObject->Id ?? $this->http->FindPreg("/\{\"Id\":\"([^\"]+)/", false, $this->http->getCookieByName("ComarchToken", ".voeazul.com.br")));
        // Qualifying points - Pontos qualificáveis
        $this->SetProperty("CreditQualifying", $returnObject->QualifyingPoints ?? $this->http->FindSingleNode('//p[contains(text(), "Pontos Qualificáveis")]/following-sibling::p[1]'));
        // Trechos
        $this->SetProperty("QualifyingFlights", $this->http->FindSingleNode('//p[contains(text(), "Trechos")]/following-sibling::p[1]'));
        // Saldo Azul
        $this->SetProperty("CashBalance", $this->http->FindSingleNode('//p[contains(text(), "Saldo Azul")]/following-sibling::p[1]'));

        // Expiration Date  // refs #8518,  https://redmine.awardwallet.com/issues/8518#note-19
        /*
        $this->getExpDate();
        */

        // TODO: debug its
        /*if ($this->AccountFields['Login'] == '83305793520') {
            $this->http->RetryCount = 0;
            $this->logger->notice("get gsessionid");
            $this->http->PostURL("https://firestore.googleapis.com/google.firestore.v1.Firestore/Listen/channel?database=projects%2Fazul-storage-prd%2Fdatabases%2F(default)&VER=8&RID=40716&CVER=22&X-HTTP-Session-Id=gsessionid&%24httpHeaders=X-Goog-Api-Client%3Agl-js%2F%20fire%2F8.10.0%0D%0AContent-Type%3Atext%2Fplain%0D%0AX-Firebase-GMPID%3A1%3A478966920505%3Aweb%3A9735b377f01bbb4cfa24e8%0D%0AAuthorization%3ABearer%20eyJhbGciOiJSUzI1NiIsImtpZCI6IjgwNzhkMGViNzdhMjdlNGUxMGMzMTFmZTcxZDgwM2I5MmY3NjYwZGYiLCJ0eXAiOiJKV1QifQ.eyJwcm92aWRlcl9pZCI6ImFub255bW91cyIsImlzcyI6Imh0dHBzOi8vc2VjdXJldG9rZW4uZ29vZ2xlLmNvbS9henVsLXN0b3JhZ2UtcHJkIiwiYXVkIjoiYXp1bC1zdG9yYWdlLXByZCIsImF1dGhfdGltZSI6MTcwOTMwMDIzNSwidXNlcl9pZCI6IlJVOW9ZcjBVNUZaUXZuR3lITWhCenFnZVVnajIiLCJzdWIiOiJSVTlvWXIwVTVGWlF2bkd5SE1oQnpxZ2VVZ2oyIiwiaWF0IjoxNzEyMzA0Nzk2LCJleHAiOjE3MTIzMDgzOTYsImZpcmViYXNlIjp7ImlkZW50aXRpZXMiOnt9LCJzaWduX2luX3Byb3ZpZGVyIjoiYW5vbnltb3VzIn19.GLgtE0tX59iagtP8lfaDGow7jOVUfv-4wZh8VmwFRdBHhf2DBRmsic3E-C3NSV_5WhkOdYFGxRKeJELMfPbR5g_Mibojk7ZMqT3RU2oCHGcHGxXOZU8bVbeFEgA-wKpGVov-DKwB2srEh1oDD95AiQoMj6o4SWhnp9KiOQGRp9R3CMcgwDG0-luG0NUz_GZlrL47t7xHXLe-52fD76zARnoDlM3Hb-eCmECbsaf8GzZvUp9uLpKySIlkvpII_EXp8ZqXnW6q9YcFLAuRjjOkQBnnBwgU98ZhX7SymuJ6L7M8pKTCNh4Q7fzRhT926Yi9qNLB-odUh9C4xXOp4gcKeg%0D%0A&zx=7hf3idss29t1&t=1", "count=10&ofs=0&req0___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22formOfPayment%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222022-06-22T05%3A14%3A57.306000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A2%7D%7D&req1___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22program%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222021-03-01T22%3A37%3A54.984000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A4%7D%7D&req2___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22country%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222023-01-05T20%3A49%3A02.867000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A6%7D%7D&req3___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22documentType%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222023-01-19T20%3A43%3A09.470000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A8%7D%7D&req4___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22fidelityProgram%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222024-03-12T02%3A12%3A44.978000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A10%7D%7D&req5___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22gender%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222021-03-01T22%3A37%3A54.910000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A12%7D%7D&req6___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22stations%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222024-03-26T19%3A59%3A24.000000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A14%7D%7D&req7___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22company%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222021-03-01T22%3A38%3A04.981000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A16%7D%7D&req8___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22provinceState%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222023-05-19T01%3A31%3A21.269000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A18%7D%7D&req9___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22reservations%22%7D%5D%2C%22where%22%3A%7B%22compositeFilter%22%3A%7B%22op%22%3A%22AND%22%2C%22filters%22%3A%5B%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22nextTrip%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Atrue%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22expired%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Afalse%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22unusable%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Afalse%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22updateBookingsKey%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22stringValue%22%3A%221769b98d%22%7D%7D%7D%5D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%2C%22limit%22%3A1%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%2Fusers%2F65974e50dc0072a80fe73ece312375d4039e403da0b711a612cfca91%22%7D%2C%22targetId%22%3A20%7D%7D");

            $gsessionid = $this->http->Response['headers']['x-http-session-id'] ?? null;

            if (!$gsessionid) {
                return;
            }

            $this->logger->notice("get SID");
//            $this->http->PostURL("https://firestore.googleapis.com/google.firestore.v1.Firestore/Listen/channel?database=projects%2Fazul-storage-prd%2Fdatabases%2F(default)&VER=8&RID=25883&CVER=22&X-HTTP-Session-Id=gsessionid&%24httpHeaders=X-Goog-Api-Client%3Agl-js%2F%20fire%2F8.10.0%0D%0AContent-Type%3Atext%2Fplain%0D%0AX-Firebase-GMPID%3A1%3A478966920505%3Aweb%3A9735b377f01bbb4cfa24e8%0D%0AAuthorization%3ABearer%20eyJhbGciOiJSUzI1NiIsImtpZCI6IjgwNzhkMGViNzdhMjdlNGUxMGMzMTFmZTcxZDgwM2I5MmY3NjYwZGYiLCJ0eXAiOiJKV1QifQ.eyJwcm92aWRlcl9pZCI6ImFub255bW91cyIsImlzcyI6Imh0dHBzOi8vc2VjdXJldG9rZW4uZ29vZ2xlLmNvbS9henVsLXN0b3JhZ2UtcHJkIiwiYXVkIjoiYXp1bC1zdG9yYWdlLXByZCIsImF1dGhfdGltZSI6MTcwMTM0MDgxMywidXNlcl9pZCI6Ik9IUTRjNEFmQlpOanZraFRlVGxNdEhxd2ZBWTIiLCJzdWIiOiJPSFE0YzRBZkJaTmp2a2hUZVRsTXRIcXdmQVkyIiwiaWF0IjoxNzEyMTI5OTQzLCJleHAiOjE3MTIxMzM1NDMsImZpcmViYXNlIjp7ImlkZW50aXRpZXMiOnt9LCJzaWduX2luX3Byb3ZpZGVyIjoiYW5vbnltb3VzIn19.iSi97xm5YDne95dwOkSRz0b2vMWBRiY6JRtFXnzMg3Rh30zAJSzAH0ld43eUgc4pXudn0AxQvTUyyAYy05y5SiFZtu45j-mATNbEab3IcZUh9adX3Ud-pG7e7ZIve4OfiC04LfGrMYFMBsrbQWT3JwAe9Wm9pL14UbQnJJNEp3ZOJPG25Mj59Yd7ILunVzkAH5Rae5b0PL8kU-0a31S7W4kAVVoeMtEuEtj9jLB0QKai_TajVRPi-0iFiUjN2m9Nae_0yil4NQtr8YqZXZJ70julbWDN6xoAhNXiJfaUob5Sp_S0oT-AV6noGdIKXVxGQNoxYdQE3SxkXYaF2oXrkw%0D%0A&zx=tc8hkjf3q85w&t=1", "count=2&ofs=0&req0___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22country%22%7D%5D%2C%22where%22%3A%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22op%22%3A%22GREATER_THAN%22%2C%22value%22%3A%7B%22timestampValue%22%3A%222023-01-05T20%3A49%3A02.867000000Z%22%7D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22lastModified%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%2C%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%22%7D%2C%22targetId%22%3A20%7D%7D&req1___data__=%7B%22database%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%22%2C%22addTarget%22%3A%7B%22query%22%3A%7B%22structuredQuery%22%3A%7B%22from%22%3A%5B%7B%22collectionId%22%3A%22reservations%22%7D%5D%2C%22where%22%3A%7B%22compositeFilter%22%3A%7B%22op%22%3A%22AND%22%2C%22filters%22%3A%5B%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22nextTrip%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Atrue%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22expired%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Afalse%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22unusable%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22booleanValue%22%3Afalse%7D%7D%7D%2C%7B%22fieldFilter%22%3A%7B%22field%22%3A%7B%22fieldPath%22%3A%22updateBookingsKey%22%7D%2C%22op%22%3A%22EQUAL%22%2C%22value%22%3A%7B%22stringValue%22%3A%224494f5cb%22%7D%7D%7D%5D%7D%7D%2C%22orderBy%22%3A%5B%7B%22field%22%3A%7B%22fieldPath%22%3A%22__name__%22%7D%2C%22direction%22%3A%22ASCENDING%22%7D%5D%2C%22limit%22%3A1%7D%2C%22parent%22%3A%22projects%2Fazul-storage-prd%2Fdatabases%2F(default)%2Fdocuments%2Fusers%2F65974e50dc0072a80fe73ece312375d4039e403da0b711a612cfca91%22%7D%2C%22targetId%22%3A22%7D%7D");

            $sid = $this->http->FindPreg("/\[\"c\",\"([^\"]+)/");

            if (!$sid) {
                return;
            }
            $headers = [
                'Accept'         => '* / *',
                'Origin'         => 'https://www.voeazul.com.br',
                'Referer'        => 'https://www.voeazul.com.br/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'cross-site',
            ];
            $this->http->GetURL("https://firestore.googleapis.com/google.firestore.v1.Firestore/Listen/channel?database=projects%2Fazul-storage-prd%2Fdatabases%2F(default)&gsessionid=$gsessionid&VER=8&RID=rpc&SID=$sid&CI=0&AID=0&TYPE=xmlhttp&zx=wdxnlikc7pv6&t=1", $headers);

            $this->http->RetryCount = 2;
        }*/
    }

    public function ParseItineraries()
    {
        $result = [];

        // TODO: refs #23607, 23811
        /*
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $resolutions = [
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug("resolution: " . implode("x", $resolution));
            $selenium->setScreenResolution($resolution);

            $browsers = [
                SeleniumFinderRequest::CHROME_94,
                SeleniumFinderRequest::CHROME_95,
            ];
            $selenium->useGoogleChrome($browsers[array_rand($browsers)]);
            //$selenium->useFirefox();
            //$selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_84);
            //$selenium->seleniumRequest->setOs(\SeleniumFinderRequest::OS_MAC);
            //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;
            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

            if ($fingerprint !== null) {
                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                $selenium->http->setUserAgent($fingerprint->getUseragent());
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.voeazul.com.br/us/en/home/');

            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//div/div/div/button[@aria-label="Log in"]'), 15);
            $this->savePageToLogs($selenium);

            if (!$loginBtn) {
                return false;
            }

            $loginBtn->click();

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-test-id="modal-login-document"]'), 10);
            $pwd = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-test-id="modal-login-password"]'), 0);

            if (!$login || !$pwd) {
                $this->logger->error("login field(s) not found");
                $this->savePageToLogs($selenium);

                return false;
            }

            $login->clear();
            $login->sendKeys($this->AccountFields['Login']);
            $pwd->clear();
            $pwd->sendKeys($this->AccountFields['Pass']);
            sleep(2);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test-id="login-submit-button"]'), 0);
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript('
            let oldXHROpen = window.XMLHttpRequest.prototype.open;
            window.XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
                this.addEventListener("load", function() {
                    if (/Firestore\/Listen\/channel/g.exec(url)) {
                        localStorage.setItem("responseData3", "result 2");
                    }
                    if (/Firestore\/Listen\/channel/g.exec(url) && /"journey"/g.exec(this.responseText) && method == "GET") {
                        localStorage.setItem("responseData", this.responseText);
                    }
                });
                return oldXHROpen.apply(this, arguments);
            };
            ');
            $btn->click();

            sleep(10);
            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript("document.querySelector('a[aria-label=\"Buy Ticket\"]').click()");
            /*$btn = $selenium->waitForElement(WebDriverBy::xpath('(//a[@href="/us/en/home.html"])[1]'), 0);
            $btn->click();* /
            sleep(10);
            $this->savePageToLogs($selenium);

            $selenium->driver->executeScript("document.querySelector('button[aria-label=\"My Trips\"]').click()");

//            $btn = $selenium->waitForElement(WebDriverBy::xpath('(//button[@aria-label="My Trips"])[1]'), 0);
//            $btn->click();
            sleep(15);
            $this->savePageToLogs($selenium);
            $seleniumDriver = $selenium->http->driver;
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $responseData = null;

            foreach ($requests as $n => $xhr) {
                $this->logger->debug("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));
                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");

                if (stripos($xhr->request->getUri(), 'Firestore/Listen/channel') !== false && stripos($xhr->request->getVerb(), 'get') !== false) {
                    $this->logger->debug("xhr response {$n} body: " . htmlspecialchars(json_encode($xhr->response->getBody())));
                    $responseData = json_encode($xhr->response->getBody());
                }
            }

            $responseData2 = $selenium->driver->executeScript("return localStorage.getItem('responseData');");
            $responseData3 = $selenium->driver->executeScript("return localStorage.getItem('responseData3');");

            $this->logger->info("[Form responseData]: " . $responseData2);
            $this->logger->info("[Form responseData]: " . $responseData3);

            $this->savePageToLogs($selenium);

            $this->logger->debug("responseData: " . $responseData);

//            $selenium->http->GetURL("https://www.voeazul.com.br/us/en/home/minhas-viagens");
//            sleep(10);
//            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo
        }
        */

        return $result;
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);

        // TODO: deprecated

        // No itineraries
        $noIt = $this->http->FindPreg("/^\{\"Status\":true,\"Message\":\"sucesso\",\"StackTrace\":null,\"ReturnObject\":\[\]\}$/ims")
            || $this->http->FindPreg("/^\{\"Status\":false,\"Message\":\"withoutReservationInList\",\"StackTrace\":null,\"ReturnObject\":null}$/ims");

        if ($noIt && !$this->ParsePastIts) {
            return $this->noItinerariesArr();
        }
        $detailLinks = $response->ReturnObject ?? [];
        $this->logger->debug("Total " . count($detailLinks) . " itineraries were found");

        foreach ($detailLinks as $detailLink) {
            $pnr = $detailLink->RecordLocator ?? null;
            $culture = $detailLink->CultureCode ?? null; // todo: this is not right value
            $lastName = $detailLink->ItineraryPassengerList[0]->LastName ?? null;

            if (!$pnr || !$culture || !$lastName) {
                $this->logger->error("something went wrong: [PNR: {$pnr} | CULTURE: {$culture} | LASTNAME: {$lastName}]");

                continue;
            }// if (!$pnr || !$culture || !$lastName)
            $this->logger->info("Parse Itinerary #{$pnr}", ['Header' => 3]);
            $itinerary = $this->ParseItinerary($detailLink);

            if (!empty($itinerary)) {
                $result[] = $itinerary;
            }
        }// foreach ($detailLinks as $detailLink)

        if ($this->ParsePastIts) {
            $pastItineraries = $this->parsePastItineraries();

            if (!empty($pastItineraries)) {
                $result = array_merge($result, $pastItineraries);
            } elseif ($noIt) {
                return $this->noItinerariesArr();
            }
        }

        return $result;
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"              => "PostingDate",
            "Description"       => "Description",
            "Points"            => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
//        $this->http->GetURL('https://www.voeazul.com.br/perfil/br/pt/extract?_rsc=3dfnu');
        $headers = [
            "Accept"                 => "text/x-component",
            "Referer"                => "https://www.voeazul.com.br/perfil/br/pt/extract",
            "Next-Action"            => "2da3a95be39a317da183543e2f3c2fe4ee28a6df",
            "Next-Router-State-Tree" => "%5B%22%22%2C%7B%22children%22%3A%5B%5B%22currency%22%2C%22br%22%2C%22d%22%5D%2C%7B%22children%22%3A%5B%5B%22locale%22%2C%22pt%22%2C%22d%22%5D%2C%7B%22children%22%3A%5B%22(private)%22%2C%7B%22children%22%3A%5B%22extract%22%2C%7B%22children%22%3A%5B%22__PAGE__%22%2C%7B%7D%5D%7D%5D%7D%5D%7D%2Cnull%2Cnull%2Ctrue%5D%7D%5D%7D%5D",
            "Next-Url"               => "/perfil/br/pt/extract",
            "Content-Type"           => "text/plain;charset=UTF-8",
            "Origin"                 => "https://www.voeazul.com.br",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.voeazul.com.br/perfil/br/pt/extract', "[{\"page\":1,\"pageSize\":99,\"dateFrom\":\"\",\"dateTo\":\"\",\"partnerCode\":\"\",\"transactionTypeCode\":\"\"}]", $headers);
        $this->http->RetryCount = 2;

        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $data = $this->http->FindPreg("/1:(.*)/");
//        $this->logger->debug(var_export($data, true), ['pre' => true]);
        $response = $this->http->JsonLog($data, 3, false, "totalQualifyPoints");
        $transactiuonsDates = $response->extracts ?? [];
        $this->logger->debug("Total " . count($transactiuonsDates) . " transaction dates were found");

        if (empty($response)) {
            return [];
        }

        foreach ($transactiuonsDates as $transactiuonsDate) {
            $this->logger->debug("[{$transactiuonsDate->date}]: Total " . count($transactiuonsDate->operations) . " transaction were found");

            if (isset($startDate) && strtotime($transactiuonsDate->date) < $startDate) {
                $this->logger->notice("break at date {$transactiuonsDate->date}");

                break;
            }

            foreach ($transactiuonsDate->operations as $operation) {
                $dateStr = $operation->date;
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }
                $result[$startIndex]['Date'] = $postDate;
                $result[$startIndex]['Description'] = "{$operation->type->name} - {$operation->partner->name}";
                $result[$startIndex]['Points'] = $operation->points;

                $startIndex++;
            }// foreach ($transactiuonsDate->operations as $operation)
        }// foreach ($transactiuonsDates as $transactiuonsDate)

        return $result;
    }

    public function GetConfirmationFields(): array
    {
        return [
            'ConfNo'    => [
                'Caption'  => 'Reservation code',
                'Type'     => 'string',
                'Size'     => 9,
                'Required' => true,
            ],
            'OriginCode'  => [
                'Caption'  => 'Origin code',
                'Type'     => 'string',
                'Size'     => 3,
                'Required' => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields): string
    {
        return 'https://www.voeazul.com.br/us/en/home';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $this->http->setRandomUserAgent();
        $this->getSensorDataFromSeleniumConfNo();
        //$this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $headers = [
            'Authorization'             => '',
            'Accept'                    => 'application/json, text/plain, */*',
            'Content-Type'              => 'application/json',
            'Culture'                   => 'pt-BR',
            'Device'                    => 'novosite',
            'Ocp-Apim-Subscription-Key' => '0fc6ff296ef2431bb106504c92dd227c',
            'Origin'                    => 'https://www.voeazul.com.br',
            //'Traceparent' => '00-0000000000000000109cc1faff46149f-2b892c1d977ad546-01',
            //'X-Datadog-Origin' => 'rum',
            //'X-Datadog-Parent-Id' => '3137087121047344454',
            //'X-Datadog-Sampling-Priority' => '1',
            //'X-Datadog-Trace-Id' => '1197044884742476959',
        ];
        $this->http->PostURL("https://b2c-api.voeazul.com.br/authentication/api/authentication/v1/token", '',
            $headers);
        $response = $this->http->JsonLog();

        if (!isset($response->data)) {
            return null;
        }
        $headers['Authorization'] = $response->data;
        $this->http->PostURL("https://b2c-api.voeazul.com.br/canonical/api/booking/v5/bookings/{$arFields['ConfNo']}",
            '{"departureStation":"' . $arFields['OriginCode'] . '"}', $headers);
        $response = $this->http->JsonLog();

        $this->parseItinerary($response);

        return null;
    }

    private function getSensorDataFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug("resolution: " . implode("x", $resolution));
            $selenium->setScreenResolution($resolution);

            if ($this->attempt == 1) {
                $selenium->useFirefoxPlaywright();
                //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } elseif ($this->attempt == 2) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            } else {
                $selenium->useChromePuppeteer();
                $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.voeazul.com.br/br/pt/home');

            try {
                $accept = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'onetrust-accept-btn-handler']"), 5);
                $this->savePageToLogs($selenium);

                if ($accept) {
                    $accept->click();
                }
            } catch (WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
//                $selenium->driver->executeScript('try { window.stop(); } catch (e) {}');
                $this->savePageToLogs($selenium);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                $retry = true;

                return false;
            }

            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//div/div/div/button[@aria-label="Log in" or @aria-label="Login"]'), 15);
            $this->savePageToLogs($selenium);

            if (!$loginBtn) {
                return false;
            }

            $loginBtn->click();

            $login = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-test-id="modal-login-document"]'), 10);
            $pwd = $selenium->waitForElement(WebDriverBy::xpath('//input[@data-test-id="modal-login-password"]'), 0);

            if (!$login || !$pwd) {
                $this->logger->error("login field(s) not found");
                $this->savePageToLogs($selenium);

                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR); // TODO: temporarily gag

                return false;
            }

            $login->clear();
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($login, $this->AccountFields['Login'], 5);
//            $login->sendKeys($this->AccountFields['Login']);
            $pwd->clear();
//            $pwd->sendKeys($this->AccountFields['Pass']);
            $mover->sendKeys($pwd, $this->AccountFields['Pass'], 5);
            sleep(2);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test-id="login-submit-button"]'), 0);

            try {
                $btn->click();
            } catch (WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
//                $selenium->driver->executeScript('try { window.stop(); } catch (e) {}');
                $this->savePageToLogs($selenium);
            }

            sleep(5);
            $this->logger->debug("last saved log");
            $this->savePageToLogs($selenium);
            $name = $selenium->waitForElement(WebDriverBy::xpath('//p[contains(@class, "name__text")]'), 5) ?? $this->http->FindNodes('//p[contains(@class, "name__text")]');
            $this->logger->debug(var_export($this->http->FindNodes('//p[contains(@class, "name__text")]'), true), ['pre' => true]);
            $selenium->driver->executeScript("try { var nodes = document.querySelectorAll('button:has(p.name__text'); nodes[nodes.length-1].click() } catch (e) {}");
            $this->savePageToLogs($selenium);

            $cmptoken = $selenium->driver->executeScript("return sessionStorage.getItem('cmptoken');");
            $this->logger->debug("[Form cmptoken]: " . $cmptoken);

            if ($name) {
                $this->savePageToLogs($selenium);
                $selenium->driver->executeScript("try { var nodes = document.querySelectorAll('a[href *= \"perfil/br/pt/home\"], a[href *= \"group/azul/my-profile\"]'); nodes[nodes.length-1].target = '_parent'; nodes[nodes.length-1].click() } catch (e) {}");
//                $selenium->http->GetURL("https://www.voeazul.com.br/perfil/br/pt/home");
                $selenium->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Pontos Qualificáveis")]/following-sibling::p[1] | //h2[contains(text(), "Parece que tivemos um problema para carregar a página")]'), 40);
                $this->increaseTimeLimit(180);
                $this->savePageToLogs($selenium);
            }

            if (!empty($cmptoken)) {
                $this->http->SetBody($cmptoken);
            } elseif (
                !$name
                && !$this->http->FindSingleNode('//p[contains(text(), "Conta suspensa")] | //p[contains(text(), "Usuário ou senha inválidos.") or contains(text(), "Username or password is invalid.")] | //span[contains(text(), "Senha deve ter no m") and contains(text(), "nimo 6 caracteres")] | //span[contains(text(), "CPF ou N") and contains(text(), "obrigat")] | //span[contains(text(), "TudoAzul deve ter no m") and contains(text(), "nimo 10 caracteres.")] | //h2[contains(text(), "Parece que tivemos um problema para carregar a página")]')
                && $selenium->waitForElement(WebDriverBy::xpath("//input[@data-cy='login-user' or @data-test-id=\"modal-login-document\"]"), 0)
            ) {
                $retry = true;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
            UnknownServerException
            | SessionNotCreatedException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
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

    private function getSensorDataFromSeleniumConfNo()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug("resolution: " . implode("x", $resolution));
            $selenium->setScreenResolution($resolution);

            if ($this->attempt == 1) {
                $selenium->useFirefoxPlaywright();
                //$selenium->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
            } elseif ($this->attempt == 2) {
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->seleniumOptions->userAgent = null;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                if ($fingerprint !== null) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            } else {
                $selenium->useChromePuppeteer();
                $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();
            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.voeazul.com.br/us/en/home');

            try {
                $accept = $selenium->waitForElement(WebDriverBy::xpath("//button[@id = 'onetrust-accept-btn-handler']"), 5);
                $this->savePageToLogs($selenium);

                if ($accept) {
                    $accept->click();
                }
            } catch (WebDriverException $e) {
                $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
//                $selenium->driver->executeScript('try { window.stop(); } catch (e) {}');
                $this->savePageToLogs($selenium);
            }

            if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
                $retry = true;

                return false;
            }

            $loginBtn = $selenium->waitForElement(WebDriverBy::xpath('//div/div/div/button[@aria-label="Log in" or @aria-label="Login"]'), 15);
            $this->savePageToLogs($selenium);

            if (!$loginBtn) {
                return false;
            }
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (
        UnknownServerException
        | SessionNotCreatedException
        | Facebook\WebDriver\Exception\WebDriverCurlException
        | Facebook\WebDriver\Exception\UnknownErrorException
        | WebDriverCurlException
        $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "exception";
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

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    // refs #8518
    private function getExpDate()
    {
        $this->logger->info('Expiration Date', ['Header' => 3]);

        if ($this->Balance <= 0) {
            return;
        }

        $date = date("Y-m-d");
        $dateTo = date("Y-12-31", strtotime("+3 year"));
        $this->http->RetryCount = 1;
        $this->http->GetURL("https://tudoazul.voeazul.com.br/b2c/me/points-expiration-forecast?expirationDateFrom={$date}&expirationDateTo={$dateTo}");

        if (
            $this->http->Response['code'] == 403
            // crocked server workaround
            || strpos($this->http->Error, 'Network error 92 - HTTP/2 stream 0 was not closed cleanly: INTERNAL_ERROR (err 2)') !== false
        ) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://tudoazul.voeazul.com.br/b2c/me/points-expiration-forecast?expirationDateFrom={$date}&expirationDateTo={$dateTo}");
        }

        $this->http->RetryCount = 2;

        $response = $this->http->JsonLog(null, 0, true);
        $this->logger->debug("Total " . count($response) . " exp date were found");
        $exp = null;

        foreach ($response as $expNode) {
            $date = ArrayVal($expNode, 'expirationDate', null);

            if (!isset($exp) && $date || $exp > strtotime($date)) {
                $exp = strtotime($date);
                $this->SetExpirationDate($exp);
                // Expiration Date
                $this->SetProperty("ExpiringBalance", ArrayVal($expNode, 'points'));
            }// if (!isset($exp) && $date || $exp > strtotime($date))
        }// foreach ($expNodes as $expNode)
    }

    private function parseItinerary($data)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->data->recordLocator)) {
            return;
        }

        $this->logger->info('Parse Itinerary #' . $data->data->recordLocator, ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()->confirmation($data->data->recordLocator, 'Reservation code');

        foreach ($data->data->passengers as $passenger) {
            $f->general()->traveller(beautifulName("{$passenger->name->first} {$passenger->name->last}"));
        }

        foreach ($data->data->journeys as $journey) {
            if (count($journey->segments) > 1) {
                $this->sendNotification('check segments // MI');
            }

            foreach ($journey->segments as $segment) {
                $s = $f->addSegment();
                $s->airline()->name($segment->identifier->carrierCode);
                $s->airline()->number($segment->identifier->flightNumber);

                $s->departure()->code($segment->identifier->departureStation);
                $s->departure()->date2($segment->identifier->std);
                $s->arrival()->code($segment->identifier->arrivalStation);
                $s->arrival()->date2($segment->identifier->sta);
                $s->extra()->cabin($segment->cabin, false, true);
                //$s->extra()->bookingCode($segment->cabin, false, true);
                $s->extra()->aircraft($segment->equipment->name);
            }
        }

//        $f->price()->total($data->pnr->priceBreakdown->price->alternatives[0][0]->amount);
//        $f->price()->currency($data->pnr->priceBreakdown->price->alternatives[0][0]->currency);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
