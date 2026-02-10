<?php

class TAccountCheckerCoop extends TAccountChecker
{
    private const REWARDS_PAGE_URL_DK = 'https://medlem.coop.dk/din-profil/';

    public $regionOptions = [
        ""   => "Select your country",
        "DK" => "Denmark",
        "NO" => "Norway",
        "SE" => "Sweden",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (in_array($fields['Login2'], ['SE', 'NO'])) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "kr %0.2f");
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->notice("Region => {$this->AccountFields['Login2']}");

        switch ($this->AccountFields['Login2']) {
            case 'DK':
//                $this->http->GetURL("https://shopping.coop.dk/");
//                $this->http->GetURL("https://coop.dk/login");
                $this->http->GetURL(self::REWARDS_PAGE_URL_DK);
//                $this->http->GetURL("https://medlem.coop.dk/umbraco/surface/Login/RedirectToLogin?from=" . self::REWARDS_PAGE_URL_DK);

                if (!$this->http->ParseForm(null, "//form[contains(@action, '/Account/Login')]")) {
                    $this->http->GetURL("https://medlem.coop.dk/");

                    if ($message = $this->http->FindSingleNode('//p[contains(., "Det er i øjeblikket desværre ikke muligt at logge ind som medlem.")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }
                $this->http->SetInputValue("UserName", $this->AccountFields["Login"]);
                $this->http->SetInputValue("Password", $this->AccountFields["Pass"]);

                break;

            case 'SE':
                $this->http->GetURL("https://www.coop.se/logga-in/?returnUrl=/mitt-coop/");

                if ($this->http->ParseForm(null, "//form[contains(@action, '/connect/authorize')]")) {
                    $this->http->PostForm();
                }

                $token = $this->http->getCookieByName("XSRF-TOKEN");

                if (!$token) {
                    return false;
                }

                $postData = [
                    "email"       => $this->AccountFields["Login"],
                    "password"    => $this->AccountFields["Pass"],
                    "accountType" => "Private",
                    "rememberMe"  => true,
                ];

                $headers = [
                    'RequestVerificationToken' => $token,
                    'X-Requested-With'         => 'XMLHttpRequest',
                    'Content-Type'             => 'application/json',
                    'X-XSRF-TOKEN'             => $token,
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL('https://login.coop.se/local/signin/application-schema/email-password', json_encode($postData), $headers);
                $this->http->RetryCount = 2;

                break;

            default:
                $this->http->setHttp2(true);
//        		$this->http->GetURL("https://secure.coop.no/");
                $this->http->GetURL("https://minside.coop.no/min-side/");
//                $csrf = $this->http->getCookieByName("_csrf", "login.coop.no", "/usernamepassword/login");

                if (!$this->http->ParseForm(null, '//form[contains(@class, "_form-login-id")]')) {
                    if ($message = $this->http->FindPreg("/Grunnet oppgradering av våre systemer, vil enkelte medlemstjenester være utilgjengelige i perioden [^\\\]+/")) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }
                $this->http->SetInputValue('username', $this->AccountFields['Login']);
                $this->http->SetInputValue('js-available', 'true');
                $this->http->SetInputValue('webauthn-available', 'true');
                $this->http->SetInputValue('action', 'default');
                $this->http->PostForm();

                if (!$this->http->ParseForm(null, '//form[contains(@class, "_form-login-password")]')) {
                    return false;
                }
                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
                $this->http->SetInputValue('action', 'default');
                $this->http->RetryCount = 0;
                $this->http->PostForm();
                $this->http->RetryCount = 2;

                if ($this->http->ParseForm(null, '//form[contains(@class, "_form-detect-browser-capabilities")]')) {
                    if ($this->isBackgroundCheck()) {
                        $this->Cancel();
                    }
                    $this->http->SetInputValue('js-available', 'true');
                    $this->http->SetInputValue('webauthn-available', 'true');
                    $this->http->PostForm();
                    $phone = $this->http->FindSingleNode('//span[@class = "ulp-authenticator-selector-text"]');
                    $state = $this->http->InputValue('state');

                    if (isset($phone, $state)) {
                        $this->State['FormState'] = $state;
                        $this->AskQuestion("We've sent a text message to $phone. Enter the 6-digit code.", null, 'Question');
                    }

                    return false;
                } else {
                    // AccountID: 3297982
                    if (
                        $this->http->Response['code'] == 400
                        && filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false
                        // pattern="^(?:\S+@\S+|(?:\d{8}))$"
                        && strlen($this->AccountFields['Login']) > 8
                    ) {
                        throw new CheckException("Please match the format requested. Email address.", ACCOUNT_INVALID_PASSWORD);
                    }
                }

                /*
                $data = [
                    "client_id"     => $this->http->FindPreg("/client=([^&]+)/", false, $this->http->currentUrl()),
                    "redirect_uri"  => "https://coop.no/Login/Auth0LoginCallback?returnurl=https://coop.no/&provider=Auth0",
                    "tenant"        => "coopno-production",
                    "response_type" => "code",
                    "scope"         => "openid offline_access profile email",
                    "audience"      => "https://integration.coop.no/legacy",
                    "_csrf"         => $this->http->getCookieByName("_csrf", "login.coop.no", "/usernamepassword/login"),
                    "state"         => $this->http->FindPreg("/state=([^&]+)/", false, $this->http->currentUrl()),
                    "_intstate"     => "deprecated",
                    "nonce"         => $this->http->FindPreg("/nonce=([^&]+)/", false, $this->http->currentUrl()),
                    "username"      => $this->AccountFields["Login"],
                    "password"      => $this->AccountFields["Pass"],
                    "connection"    => "coopdb",
                ];
                $headers = [
                    "Accept"       => "application/json",
                    "Content-Type" => "application/json",
                    "Auth0-Client" => "eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMyJ9",
                ];

                $this->http->RetryCount = 0;
                $this->http->PostURL('https://login.coop.no/usernamepassword/login', json_encode($data), $headers);
                $this->http->RetryCount = 2;
                */

                break;
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams();

        switch ($this->AccountFields['Login2']) {
            case 'DK':
                $arg['SuccessURL'] = self::REWARDS_PAGE_URL_DK; //todo: autologin not working

                break;

            case 'SE':
                //$arg['RequestMethod'] = "GET";
                //$arg['RedirectURL'] = "https://www.coop.se/Personliga-Baren/Logga-in/?method=Login";
                break;

            default:
                $arg['SuccessURL'] = 'http://coop.no/';

            break;
        }

        return $arg;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'DK':
                if (!$this->http->PostForm()) {
                    return false;
                }
                // login successful
                $this->http->setMaxRedirects(0);

                if ($this->http->ParseForm(null, "//form[contains(@action, 'login/logincallback') or @action = 'https://medlem.coop.dk/']")) {
                    $this->http->PostForm();

                    $redirect = $this->http->Response['headers']['location'] ?? null;
                    $this->logger->debug("Redirect -> '{$redirect}'");
                    $redirect = str_replace("https:/medlem.coop.dk", "https://medlem.coop.dk", $redirect);
                    $this->logger->debug("Redirect -> '{$redirect}'");
                    $this->http->setMaxRedirects(5);

                    if ($redirect) {
                        $this->http->GetURL($redirect);
                    }

                    // 404 workaround
                    if ($this->http->currentUrl() != self::REWARDS_PAGE_URL_DK || $this->http->Response['code'] == 404) {
                        $this->http->GetURL(self::REWARDS_PAGE_URL_DK);
                    }

                    if ($this->http->FindNodes("//a[contains(@href, 'Logout')]")) {
                        return true;
                    }
                }// if ($this->http->ParseForm(null, "//form[contains(@action, 'login/logincallback')]"))
                $this->http->setMaxRedirects(5);
                // Invalid credentials
                if ($message = $this->http->FindPreg("/(Det indtastede passer ikke sammen. Pr&#xF8;v igen)<\/span>/")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Vi kan desværre ikke genkende det mobilnummer, du forsøger at logge ind med
                if ($message = $this->http->FindSingleNode('
                        //span[contains(text(), "Vi kan desværre ikke genkende det")]
                        | //span[contains(text(), "Det du har indtastet er ikke en gyldig e-mail.")]
                        | //span[contains(text(), "Adgangskoden er forkert.")]
                    ')
                ) {
                    throw new CheckException(strip_tags($message), ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->FindSingleNode('//h1[contains(text(), "Nulstil adgangskode")]')) {
                    $this->throwProfileUpdateMessageException();
                }

                break;

            case 'SE':
                $response = $this->http->JsonLog();

                if (isset($response->friendlyMessage)) {
                    $message = $response->friendlyMessage;
                    $this->logger->error($message);
                    // Fel e-postadress och/eller lösenord.
                    if (in_array($message, [
                        'Fel e-postadress och/eller lösenord.',
                        'Felaktigt lösenord.',
                        'Det finns inget användarkonto med den här e-postadressen.',
                    ])) {
                        throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    }
                }// if (isset($response->friendlyMessage))

                if (in_array($this->http->Response['code'], [200, 204])) {
                    $this->http->GetURL("https://www.coop.se/mitt-coop/mina-poang/");

                    if ($this->http->ParseForm(null, "//form[contains(@action, '/connect/authorize')]")) {
                        $this->http->PostForm();
                    }
                }

                if ($this->http->ParseForm(null, "//form[@action = 'https://www.coop.se' or @action = 'https://www.coop.se/signin-oidc']")) {
                    $this->http->PostForm();
                }

                if ($this->http->FindNodes("//li/a[@href='/mitt-coop/']")) {
                    return true;
                }

                break;

            default:
                $this->http->ParseForm("hiddenform");

                if (!$this->http->PostForm() && $this->http->Response['code'] != 401) {
                    if ($message = $this->http->FindSingleNode('//span[@class = "ulp-input-error-message"]')) {
                        $this->logger->error("[Error]: {$message}");

                        if (
                            $message == "Incorrect username or password. Try again."
                            || $message == "Feil brukernavn eller passord. Prøv igjen."
                        ) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        $this->DebugInfo = $message;

                        return false;
                    }// if ($message = $this->http->FindSingleNode('//span[@class = "ulp-input-error-message"]'))

                    return false;
                }

                $this->handlePostForm();

                $response = $this->http->JsonLog();

                // login successful
                if ($this->http->FindPreg("/\"memberId.\":.\"/ims")) {
                    return true;
                }

                /*
                if (strstr($this->http->currentUrl(), 'enable2fa=true&')) {
                    parse_str(parse_url($this->http->currentUrl(), PHP_URL_QUERY), $output);
                    $this->logger->debug(var_export($output, true), ['pre' => true]);

                    if (!isset($output['state'])) {
                        $this->logger->error("state not found");

                        return false;
                    }

                    $this->http->GetURL("https://login.coop.no/mf?state={$output['state']}");
                }
                */

                $requestToken = $this->http->FindPreg("/requestToken:\s*(?:'|\")([^'\"]+)/");

                if ($requestToken) {
                    $headers = [
                        'Accept'          => 'application/json',
                        'Accept-Encoding' => 'gzip, deflate, br',
                        'Authorization'   => "Bearer {$requestToken}",
                        'Origin'          => 'https://login.coop.no',
                        'Content-Type'    => 'application/json',
                    ];
                    $this->http->RetryCount = 0;
                    $this->http->PostURL("https://coopno-production.guardian.eu.auth0.com/api/start-flow", '{"state_transport":"polling"}', $headers);
                    $this->http->RetryCount = 2;
                    $response = $this->http->JsonLog();

                    if (isset($response->transaction_token)) {
                        // Vennligst skriv inn ditt telefonnummer for å sikre din konto. / Please enter your phone number to secure your account.
                        if (
                            !isset($response->device_account->phone_number)
                            || !isset($response->device_account->methods[0])
                            || $response->device_account->methods[0] !== 'sms'
                        ) {
                            if ($response->device_account->status == 'confirmation_pending') {
                                $this->throwProfileUpdateMessageException();
                            }

                            return false;
                        }

                        $headers = [
                            'Accept'          => 'application/json',
                            'Accept-Encoding' => 'gzip, deflate, br',
                            'Authorization'   => "Bearer {$response->transaction_token}",
                            'Origin'          => 'https://login.coop.no',
                            'content-type'    => null,
                        ];
                        $this->http->RetryCount = 0;
                        $this->http->PostURL("https://coopno-production.guardian.eu.auth0.com/api/send-sms", null, $headers);
                        $this->http->RetryCount = 2;

                        if ($this->http->Response['code'] == 204) {
                            $this->State['headers'] = $headers;
                            $this->AskQuestion("VI HAR SENDT EN SMS TIL: {$response->device_account->phone_number}. Skriv inn koden du har mottatt på SMS. 6 siffer.", null, "Question");
                        }

                        return false;
                    }
                }// if (strstr($this->http->currentUrl(), 'enable2fa=true&'))

                $message = $response->message ?? null;

                // check for invalid password
                if ($this->http->FindPreg("/FEIL PASSORD/ims")) {
                    throw new CheckException("Invalid password", ACCOUNT_INVALID_PASSWORD);
                }

                if (
                    $this->http->FindPreg("/\{\"name\":\"Error\",\"message\":\"Something went wrong. \(Error: Unexpected error occurred when logging in with user-service: Error: Request failed with status code 401\)\",\"fromSandbox\":true\}/ims")
                    || $message == "Failed to log in with identifier {$this->AccountFields['Login']}"
                ) {
                    throw new CheckException("Feil brukernavn eller passord. Prøv igjen.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindPreg("/(Brukernavn og passord stemmer ikke.[^<\"]+)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Brukernavn eller passord stemmer ikke. Prøv igjen.
                if ($message = $this->http->FindPreg("/(Brukernavn eller passord stemmer ikke.[^<\"]+)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Feil brukernavn eller passord. Prøv igjen.
                if ($message = $this->http->FindPreg("/\"(Incorrect login for user with identifier [^<\"]+)/ims")) {
                    throw new CheckException("Feil brukernavn eller passord. Prøv igjen.", ACCOUNT_INVALID_PASSWORD);
                }
                // Maintenance
                if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Beklager, coop.no er ikke tilgjengelig akkurat nå.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // broken account
                if (in_array($this->AccountFields['Login'], [
                    'fredrik.leren@gmail.com',
                ])) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                break;
        }

        return false;
    }

    public function ProcessStep($step)
    {
        switch ($this->AccountFields['Login2']) {
            case 'NO':
                $this->http->FormURL = 'https://login.coop.no/u/mfa-sms-challenge?state=' . $this->State['FormState'];
                $this->http->SetInputValue('state', $this->State['FormState']);
                $this->http->SetInputValue('code', $this->Answers[$this->Question]);
                $this->http->SetInputValue('action', 'default');
                unset($this->Answers[$this->Question]);
                $this->http->RetryCount = 0;
                $this->http->PostForm(['Referer' => $this->http->FormURL]);
                $this->http->RetryCount = 2;
                $message = $this->http->InputValue('error_description')
                    ?? $this->http->FindSingleNode('//span[@id = "error-element-code"]');

                if ($message) {
                    $this->logger->error($message);

                    if (str_contains($message, 'The code you entered is invalid')) {
                        $this->AskQuestion($this->Question, 'The code you entered is invalid', 'Question');

                        return false;
                    }

                    if ($message == 'The transaction has expired') {
                        throw new CheckException('The transaction has expired');
                    }
                    $this->DebugInfo = $message;

                    return false;
                }

                $this->handlePostForm();

                break;

            default:
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://coopno-production.guardian.eu.auth0.com/api/verify-otp", '{"type":"manual_input","code":"' . $this->Answers[$this->Question] . '"}', $this->State['headers']);
                unset($this->Answers[$this->Question]);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();

                // {"statusCode":403,"error":"Forbidden","message":"Invalid OTP code","errorCode":"invalid_otp"}
                if ($response->message == "Invalid OTP code") {
                    $this->AskQuestion($this->Question, "KODEN ER IKKE GYLDIG. VENNLIGST KONTROLLER KODEN OG PRØV PÅ NYTT.", 'Question');

                    return false;
                }

                break;
        }

        return true;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'DK':
                if ($this->http->currentUrl() != self::REWARDS_PAGE_URL_DK) {
                    $this->http->GetURL(self::REWARDS_PAGE_URL_DK);
                }

                $this->http->GetURL("https://prdmedlem.coop.dk/member-overview/");

                if ($this->http->ParseForm(null, "//form[contains(@action, 'login/LoginCallback')]")) {
                    $this->http->PostForm();
                }

//                $this->http->GetURL("https://medlem.coop.dk/medlemskonto+og+kvitteringer");
                $this->http->GetURL("https://prdmedlem.coop.dk/member-overview/member-account/");
                // Balance - kr ... Balance
                $this->SetBalance($this->http->FindSingleNode("//td[contains(text(), 'Min bonus')]/following-sibling::td[1]", null, true, self::BALANCE_REGEXP));
                // Indestående andelskapital (Shared capital)
                // Shared capital
                $this->SetProperty("SharedCapital", $this->http->FindSingleNode("//td[contains(text(), 'Indestående andelskapital')]/following-sibling::td[1]"));

                $this->http->GetURL("https://prdmedlem.coop.dk/member-overview/member-profile/?refererUrl=https%3A%2F%2Fmedlem.coop.dk");

                if ($this->http->ParseForm(null, "//form[contains(@action, 'login/LoginCallback')]")) {
                    $this->http->PostForm();
                }
                // Medlemsnummer
                $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//small[contains(text(), 'Medlemsnummer')]/following-sibling::div[1]"));
                // Name
                $this->SetProperty("Name", beautifulName(
                    $this->http->FindSingleNode("//small[contains(text(), 'Fornavn')]/following-sibling::div[1]")
                    . " " . $this->http->FindSingleNode("//small[contains(text(), 'Efternavn')]/following-sibling::div[1]")));

                break;

            case 'SE':
                $this->http->GetURL("https://www.coop.se/api/spa/token?_=" . date("UB"));
                $response = $this->http->JsonLog();

                if (!isset($response->token)) {
                    $this->logger->error("token not found");

                    return;
                }

                $headers = [
                    "Accept"                    => "application/json",
                    "Accept-Language"           => "en-US,en;q=0.5",
                    "Accept-Encoding"           => "gzip, deflate, br",
                    "Authorization"             => "Bearer {$response->token}",
                    "Origin"                    => "https://www.coop.se",
                    "Referer"                   => "https://www.coop.se/",
                    "Ocp-Apim-Subscription-Key" => "3becf0ce306f41a1ae94077c16798187",
                    "Ocp-Apim-Trace"            => "true",
                ];
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://external.api.coop.se/loyalty/account/points/balance?api-version=v1", $headers);
                $this->http->RetryCount = 2;
                $response = $this->http->JsonLog();
                // Balance - Mina poäng / Poäng att använda för uttag
                // 191 005
                $this->SetBalance($response->balance ?? null);
                // ==== AccountNumber, Name ====
                $this->http->GetURL('https://www.coop.se/mitt-coop/installningar/');
                // Medlems-id
                $this->SetProperty("AccountNumber", $this->http->FindPreg('/medmeraId":(\d+)/'));
                // Namn
                $this->SetProperty("Name", beautifulName(str_replace('","lastName":"', ' ', $this->http->FindPreg('/firstName":"([^"]+","lastName":"[^"]+)/'))));

                break;

            default:
                $this->http->GetURL("https://coop.no/min-side/?react=true");
                $response = $this->http->JsonLog();
                // Det er du som bestemmer!
                if (
                    $this->http->FindPreg('/"reConsentInfomationPreambleText":"Dine interesser og ditt personvern er viktig/')
                    || $this->http->FindPreg('/,"type":"CustomerInformationPromptBlock"/')
                ) {
                    $this->throwAcceptTermsMessageException();
                }
                // Account number
                $this->SetProperty("AccountNumber", $response->content->currentMembership->memberId ?? null);
                // Name
                $this->SetProperty("Name", beautifulName($response->content->name ?? null));
                // Balance - Totalt spart hittil i år:
                $this->SetBalance($response->content->totalSaved ?? null);
        }// switch ($this->AccountFields['Login2'])
    }

    private function handlePostForm()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->ParseForm(null, '//form[contains(@action, "https://coop.no/Login/Auth0LoginCallback")]')) {
            $this->http->PostForm([], 80);
        }
    }
}
