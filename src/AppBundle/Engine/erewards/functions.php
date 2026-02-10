<?php

class TAccountCheckerErewards extends TAccountChecker
{
    use \AwardWallet\Engine\ProxyList;

    public $retryCount = 0;

    public $securityQuestions = [
        '' => "Select a question",
        1  => "What is your pet's name?",
        2  => "What is your father's middle name?",
        3  => "In what town were you born?",
        4  => "What was your childhood nickname?",
        5  => "What was the make of your first car?",
    ];

    public $regionOptions = [
        ""       => "Select your region",
        "com.au" => "Australia",
        "com.br" => "Brazil",
        "ca"     => "Canada",
        "fr"     => "France",
        "de"     => "Germany",
        "com.mx" => "Mexico",
        "nl"     => "Netherlands",
        "es"     => "Spain",
        //        "se"     => "Sweden",                 // we didn't find the site // IZ and EK 27.08.2024
        //        "dk"     => "Denmark",                // we didn't find the site // IZ and EK 27.08.2024
        //        "in"     => "India",                  // closed ~7 Mar 2020
        //        "sa.com" => "Saudi Arabia",           // closed ~7 Mar 2020
        //        "ch"     => "Switzerland",            // closed ~7 Mar 2020
        //        "ae"     => "United Arab Emirates",   // closed ~15 Feb 2020
        "co.uk"  => "United Kingdom",
        "com"    => "United States",
    ];
    private $domain = 'com';

    /* parser like as airmilessurvey, perspectives, valuedopinions, opinionmiles, erewards (com.au) */
    private $jsonAuth = [//todo: form.js should be changed
        'com.br',
        'com.au',
        'ca',
        'co.uk',
        'de',
        'com.mx',
        'es',
        'com',
        'fr',
        'nl',
    ];
    private $headers = [
        "Accept"                => "*/*",
        "Accept-Language"       => "en-US,en;q=0.5",
        "Accept-Encoding"       => "gzip, deflate, br",
        "amz-sdk-request"       => "attempt=1; max=3",
        "content-type"          => "application/x-amz-json-1.1",
        "x-amz-target"          => "AWSCognitoIdentityProviderService.InitiateAuth",
        "x-amz-user-agent"      => "aws-sdk-js/3.490.0 ua/2.0 os/macOS#10.15 lang/js md/browser#Firefox_123.0 api/cognito-identity-provider#3.490.0",
    ];

    private $regionalSetting = [
        'com.au' => [
            'headers' => [
                'panelDomainId' => '532',
            ],
            'formData' => [
                "panelId" => 53,
            ],
        ],
        'ca' => [
            'headers' => [
                'panelDomainId' => '511',
            ],
            'formData' => [
                "panelId" => 51,
            ],
        ],
        'com.br' => [
            'headers' => [
                'panelDomainId' => '552',
            ],
            'formData' => [
                "panelId" => 55,
            ],
        ],
        'co.uk' => [
            'headers' => [
                'panelDomainId' => '521',
            ],
            'formData' => [
                "panelId" => 52,
            ],
        ],
        'de' => [
            'headers' => [
                'panelDomainId' => '601',
            ],
            'formData' => [
                "panelId" => 60,
            ],
        ],
        'com.mx' => [
            'headers' => [
                'panelDomainId' => '651',
            ],
            'formData' => [
                "panelId" => 65,
            ],
        ],
        'es' => [
            'headers' => [
                'panelDomainId' => '741',
            ],
            'formData' => [
                "panelId" => 74,
            ],
        ],
        'com' => [
            'headers' => [
                'panelDomainId' => '501',
            ],
            'formData' => [
                "panelId" => 50,
            ],
        ],
        'fr' => [
            'headers' => [
                'panelDomainId' => '591',
            ],
            'formData' => [
                "panelId" => 59,
            ],
        ],
        'nl' => [
            'headers' => [
                'panelDomainId' => '661',
            ],
            'formData' => [
                "panelId" => 66,
            ],
        ],
    ];

    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        /*
        $fields['Login2']['Options'] = $this->securityQuestions;
        ArrayInsert($fields, "Login", true, ["Login3" => [
            "Type"      => "string",
            "InputType" => "select",
            "Required"  => true,
            "Caption"   => "Region",
            "Options"   => $this->regionOptions,
        ]]);
        */

        $fields['Login2']['Options'] = $this->regionOptions;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        /*
        $this->domain = $this->checkRegionSelection($this->AccountFields['Login3']);
        */
        $this->domain = $this->checkRegionSelection();

        $this->logger->notice('Domain => ' . $this->domain);

        if ($this->domain === 'com') {
//            $this->setProxyGoProxies();
//            $this->setProxyBrightData();
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://flare.e-rewards.{$this->domain}/api/1/respondent?_cache=" . date("UB"));
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function RetryLogin()
    {
        $this->retryCount++;
        $this->http->PostForm();
        $this->checkErrors();
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (in_array($this->domain, $this->jsonAuth)) {
            $this->http->GetURL("https://www.e-rewards.{$this->domain}/launch/login");

            $panelId = $this->http->FindPreg('/panelId:\s*([^,]+)/ims');
            $panelDomainId = $this->http->FindPreg('/panelDomainId:\s*([^,]+)/ims');
            $brandId = $this->http->FindPreg("/brandId:\s*(\d+),/");
            $passwordClientId = $this->http->FindPreg("/passwordClientId:\s*\"([^\"]+)/");

            if ($this->http->Response['code'] !== 200 || !$panelDomainId || !$panelId || !$brandId || !$passwordClientId) {
                return false;
            }

            $data = [
                "AuthFlow"       => "USER_PASSWORD_AUTH",
                "ClientId"       => $passwordClientId,
                "AuthParameters" => [
                    "USERNAME" => $this->AccountFields['Login'],
                    "PASSWORD" => $this->AccountFields['Pass'],
                ],
                "ClientMetadata" => [
                    "brand_id" => $brandId,
                    "panel_id" => $panelId,
                ],
            ];
            $this->http->RetryCount = 0;
            $data = array_merge($data, $this->regionalSetting[$this->domain]['formData']);
            $this->headers['Origin'] = "https://www.e-rewards.{$this->domain}";
            $this->http->PostURL("https://cognito-idp.us-east-1.amazonaws.com/", json_encode($data), $this->headers);
            $this->http->RetryCount = 2;

            return true;
        }

        /*
        if ($this->AccountFields['Login2'] == '') {
            throw new CheckException("To update this e-Rewards account you need to answer a security question. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);
        }/*checked*/

        if (in_array($this->domain, ['com.br'])) {
            $this->domain = 'com';
            $this->logger->notice('New Domain => ' . $this->domain);
        }

        $this->http->GetURL("https://www.e-rewards.{$this->domain}/reviewaccount.do");

        if (!$this->http->ParseForm("logonForm")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue("logonHelper.email", $this->AccountFields['Login']);
        $this->http->SetInputValue("logonHelper.password", $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//div[@class = 'errorMessages']")) {
            if (strstr($message, 'Internal Server Error')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } else {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }
        // Email Address is an invalid format.
        if ($message = $this->http->FindSingleNode('//div[
                contains(text(), "Email Address is an invalid format.")
                or contains(text(), "E-mail-adresse er et ugyldigt format.")
                or contains(text(), "Adresse électronique n\'est pas un format valable.")
                or contains(text(), "E-Mail-Adresse ist ein ungültiges Format.")
                or contains(text(), "E-mailadres is een ongeldige indeling.")
        ]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Dirección de correo electrónico no es un formato válido.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Dirección de correo electrónico no es un formato válido.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->retryCount < 2) {
            $error = $this->http->FindPreg("/(Please\s*pardon\s*the\s*inconvenience\.\s*Either\s*this\s*session\s*has\s*timed\s*out\,\s*or\s*this\s*process\s*is\s*currently\s*unavailable\.)/ims");

            if (isset($error)) {
                $this->RetryLogin();
            }
        }

        if ($message = $this->http->FindSingleNode('
                //font[contains(text(), "The e-Rewards Web site and surveys will be unavailable")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Site is currently unavailable
        if ($message = $this->http->FindSingleNode("//font[contains(text(), 'site is currently unavailable') or contains(text(), 'is momenteel onbereikbaar')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Site is currently unavailable
        if ($message = $this->http->FindSingleNode("//b[contains(text(),'website and surveys will be unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Site is currently unavailable
        if ($message = $this->http->FindPreg("/Die e-Rewards-Website ist gegenw\&auml;rtig nicht verf\&uuml;gbar./ims")) {
            throw new CheckException("Die e-Rewards-Website ist gegenwärtig nicht verfügbar. Wir führen ein Upgrade durch, um Ihnen eine optimierte Online-Leistung zu bieten.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Le site Web d\'<nobr>e-Rewards<\/nobr> n'est momentan\&eacute;ment pas disponible/ims")) {
            throw new CheckException("Le site Web d'e-Rewards n'est momentanément pas disponible. Nous effectuons actuellement une mise à niveau afin de vous offrir une meilleur rendement en ligne.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/El sitio Web de <nobr>e\-Rewards<\/nobr>no est\&\#225; disponible en este momento/ims")) {
            throw new CheckException("El sitio Web de e-Rewardsno está disponible en este momento. Estamos realizando una actualización para brindarle un mejor funcionamiento en línea.", ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if (strstr($this->http->currentUrl(), 'sitedowngen.html')) {
            throw new CheckException("The e-Rewards Web site is currently unavailable", ACCOUNT_PROVIDER_ERROR);
        }/*review*/
        // Service Temporarily Unavailable
        if ($this->http->FindSingleNode("
                //h1[contains(text(), 'Service Temporarily Unavailable')]
                | //h2[contains(text(), 'The request could not be satisfied.')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (in_array($this->domain, $this->jsonAuth)) {
            $response = $this->http->JsonLog(null, 3, true);
            $jsResponse = ArrayVal($response, 'AuthenticationResult');
            $IdToken = ArrayVal($jsResponse, 'IdToken');
            $str = base64_decode(explode('.', $IdToken)[1] ?? null);
            $this->logger->debug($str);
            $sessionId = $this->http->FindPreg('/"corona_session":"(.+?)"/', false, $str);

            if (isset($sessionId)) {
                $this->http->setCookie("corona_session", $sessionId, ".e-rewards.{$this->domain}");
                $this->http->GetURL("https://flare.e-rewards.{$this->domain}/api/1/respondent?_cache=" . date("UB"));

                if ($this->loginSuccessful()) {
                    return true;
                }
            }
            // Incorrect login. Please try again.
            $message = ArrayVal($response, 'message');

            if ($message) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'Incorrect username or password.'
                    || $message == 'Password reset required for user due to security reasons'
                    || $message == 'User is disabled.'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if (strstr($message, '2 validation errors detected: Value at \'userAlias\' failed to satisfy constraint')) {
                    throw new CheckException("Informations de connexion incorrectes. Merci de réessayer.", ACCOUNT_INVALID_PASSWORD);
                }

                if ($message == 'PreTokenGeneration failed with error error_invalidCredentials.') {
                    throw new CheckException("Incorrect login. Please try again", ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;

                return false;
            }

            return $this->checkErrors();
        }

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // repeat request for international
        $host = $this->getHost();

        if ($host != "www.e-rewards.{$this->domain}") {
            $domain = str_replace("www.e-rewards.", '', $host);
            $domains = ["com.br", "fr", "de", "nl", "es", "se", "ch", "co.uk", "com", 'com.mx', 'in', 'sa.com', 'ae', 'dk'];
            $this->logger->debug("[Domain]: {$domain}");

            if (in_array($domain, $this->jsonAuth)) {
                $this->AccountFields['Login3'] = $domain;
                $this->LoadLoginForm();

                return $this->Login();
            }

            if (!in_array($domain, $domains)) {
                $this->sendNotification("New host found $host");
            }
            $this->http->GetURL("https://{$host}/reviewaccount.do");

            if ($this->http->ParseForm("logonForm")) {
                $this->http->Form["logonHelper.email"] = $this->AccountFields['Login'];
                $this->http->Form["logonHelper.password"] = $this->AccountFields['Pass'];

                if (!$this->http->PostForm()) {
                    $this->checkErrors();
                }
            }// if ($this->http->ParseForm("logonForm"))
        }// if ($host != "www.e-rewards.com")

        //# Security questions
        if ($this->http->FindSingleNode("//select[@name = 'logonHelper.email']/option[1]")) {
            if (!$this->parseQuestion()) {
                return false;
            }
        }

        //# Access is allowed
        if ($this->http->FindSingleNode("//a[@href = '/Logout.do']/@href")) {
            return true;
        }
        // check Login errors
        $this->checkLoginErrors();

        return $this->checkErrors();
    }

    public function checkLoginErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Survey invitations
        if ($this->http->FindSingleNode("//div[@id = 'Standard']//td[contains(text(), 'Would you like to receive e-mails from us again?')]")
            || $this->http->FindSingleNode("//div[@id = 'Standard']//td[contains(text(), 'Möchten Sie wieder E-Mails von uns erhalten?')]")) {
            throw new CheckException('e-Rewards website is asking you to provide your consent to send you survey invitations, until you do so we would not be able to retrieve your account information.', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        //# Confirm Your e-Mail Address
        if ($this->http->FindSingleNode("//td[@id = 'header1']/b[contains(text(), 'Confirm Your e-Mail Address')]")
            || $this->http->FindSingleNode("//td[@id = 'header1']/b[contains(text(), 'Receber nossos e-mails novamente')]")
            || $this->http->FindSingleNode("//td[@id = 'header1']/b[contains(text(), 'Opnieuw e-mails van ons ontvangen')]")
            || $this->http->FindSingleNode("//td[@id = 'header1']/b[contains(text(), 'Bestätigen Sie Ihre E-Mail-Adresse')]")) {
            throw new CheckException('Please confirm your e-mail address', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        //# Again receive our emails
        if ($this->http->FindPreg("/(Recevez à nouveau nos courriers électroniques)/ims")
            || $this->http->FindPreg("/(recevoir à nouveau des courriers électroniques de notre part)/ims")) {
            throw new CheckException('Please update your account', ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
    }

    /*
    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
//        $question = $this->http->FindSingleNode("//select[@name = 'logonHelper.email']/option[1]");
        $question = $this->securityQuestions[$this->AccountFields['Login2']];

        if (!isset($question)) {
            return true;
        }

        if (!$this->http->ParseForm("logonSecurityForm")) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return false;
    }
    */

    // repeat request for international
    public function getHost()
    {
        $this->logger->notice(__METHOD__);
        // repeat request for international
        $host = parse_url($this->http->currentUrl(), PHP_URL_HOST);
        $this->logger->debug("host: $host");

        return $host;
    }

    /*
    public function ProcessStep($step)
    {
        $host = $this->getHost();

        $this->http->Form["logonHelper.password"] = $this->Answers[$this->Question];
        $this->http->Form["loginButton.x"] = '37';
        $this->http->Form["loginButton.y"] = '13';
        $this->http->Form["logonHelper.email"] = $this->AccountFields['Login2'];
//        $this->http->Form["logonHelper.email"] = $this->http->FindSingleNode("//select[@name = 'logonHelper.email']/option[1]/@value");
        if (!$this->http->PostForm()) {
            return false;
        }
        // Security Question/Answer is not correct
        if (
            $this->http->FindPreg("/(Security Question\/Answer is not correct)/ims")
            || $this->http->FindPreg("/(Sicherheitsfrage\s*\/\s*Antwort ist nicht korrekt)/ims")
            || $this->http->FindPreg("/(Question de sécurité\/réponse de sécurité)/ims")
            || $this->http->FindPreg("/(Geheime vraag\/antwoord is fout)/ims")
            || $this->http->FindPreg("/(Pergunta\/resposta de segurança incorreta)/ims")
            || $this->http->FindPreg("/(La pregunta o respuesta de seguridad son incorrectas)/ims")
        ) {
            $this->parseQuestion();

            return false;
        }

        return true;
    }
    */

    public function Parse()
    {
        if (in_array($this->domain, $this->jsonAuth)) {
            $response = $this->http->JsonLog(null, 0);
            // Name
            $this->SetProperty("Name", beautifulName("{$response->response->firstName} {$response->response->lastName}"));

//            $this->http->GetURL("https://www.e-rewards.com.au/en/auth/dashboard");
            $this->http->GetURL("https://flare.e-rewards.{$this->domain}/api/1/respondent/balance?_cache=" . date("UB"), $this->headers);
            $response = $this->http->JsonLog();
            // Balance - Opinion Points
            $this->SetBalance($response->response->amount ?? null);

            $this->http->GetURL("https://flare.e-rewards.{$this->domain}/api/1/badge/respondent?_cache=" . date("UB"), $this->headers);
            $response = $this->http->JsonLog();

            if (isset($response->response)) {
                foreach ($response->response as $row) {
                    if (!isset($row->parentId, $row->priority) && isset($row->granted, $row->name) && $row->granted
                        && (!isset($priority) || $row->priority < $priority)) {
                        $priority = $row->priority;
                        // Level
                        $this->SetProperty("Level", $row->name);
                    }
                }// foreach ($response->response as $row)

                if (!isset($this->Properties['Level'])) {
                    $this->SetProperty("Level", "Bronze");
                }
            }// if (isset($response->response))

            return;
        }

        // repeat request for international
        $host = $this->getHost();
        $this->http->GetURL("https://{$host}/reviewaccount.do?ln=en");

        // Expiration Date notifications
        if (($this->http->FindPreg("/(Expiring)/ims") || $this->http->FindPreg("/(Expiration)/ims")
            || $this->http->FindPreg("/(>\s*Expire)/ims"))) {
            $this->sendNotification("e-Rewards. Expiration Date found");
        }
        //div[div[contains(text(), 'Account Balance')]]/following-sibling::div[1]
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[contains(text(), 'Welcome,')]/following-sibling::div[1]")));
        // Member Since
        $this->SetProperty("MemberSince", $this->http->FindSingleNode("//div[b[contains(text(), 'Member Since:')]]/text()[last()]"));
        // Balance - Available to Redeem
        $this->SetProperty("AvailableToRedeem", $this->http->FindSingleNode("//div[div[contains(text(), 'Account Balance')]]/following-sibling::div[1]/b"));

        if (isset($this->Properties['AvailableToRedeem'])) {
            // Balance - My Account Balance
            $this->SetBalance($this->Properties['AvailableToRedeem']);
            // Expiration Date  // refs #5689
            $this->getExpirationDate($this->Properties['AvailableToRedeem']);
        }
    }

    public function getExpirationDate($balance)
    {
        if (!isset($this->Properties['MemberSince']) || strtotime($this->Properties['MemberSince']) == false) {
            return null;
        }

        $unixTimexMemberSince = strtotime($this->Properties['MemberSince']);
        $month = date('n', $unixTimexMemberSince);
        $this->http->Log("Months: " . $month);
        $quarter = $month / 3;
//        $this->http->Log("Quarter: ".$quarter);
        switch ($quarter) {
            case $quarter <= 1:
                $this->http->Log("Quarter: 1");
                $membershipYearEnds = mktime(0, 0, 0, 4, 0, date('Y', time()));

                break;

            case $quarter > 1 && $quarter <= 2:
                $this->http->Log("Quarter: 2");
                $membershipYearEnds = mktime(0, 0, 0, 7, 0, date('Y', time()));

                break;

            case $quarter > 2 && $quarter <= 3:
                $this->http->Log("Quarter: 3");
                $membershipYearEnds = mktime(0, 0, 0, 10, 0, date('Y', time()));

                break;

            case $quarter > 3:
                $this->http->Log("Quarter: 4");
                $membershipYearEnds = mktime(0, 0, 0, 1, 0, (date('Y', time()) + 1));

                break;
        }

        if (!isset($membershipYearEnds)) {
            return null;
        }

        if ($membershipYearEnds < time()) {
            $membershipYearEnds = strtotime("+1 year", $membershipYearEnds);
        }

        //# Membership Year Ends
        $this->SetProperty("MembershipYearEnds", date("M j, Y", $membershipYearEnds));

        //# Last Activity
        $endDate = time();
        $startDate = strtotime("-2 month", $endDate);
        $finishDate = strtotime("-1 year", time());
        $stop = false;

        do {
            // Activity for 2 months
            if ($this->http->ParseForm("ReviewAccountForm")) {
                // start Date
                $this->http->Form['member.endDay'] = date('j', $endDate);
                $this->http->Form['member.endMonth'] = date('n', $endDate);
                $this->http->Form['member.endYear'] = date('Y', $endDate);
                // end Date
                $this->http->Form['member.startDay'] = date('j', $startDate) + 1;
                $this->http->Form['member.startMonth'] = date('n', $startDate);
                $this->http->Form['member.startYear'] = date('Y', $startDate);

                $this->http->Form['method'] = 'Go';
                $this->http->PostForm();
            }

            if ($error = $this->http->FindPreg("/(The start date must not be after the end date)/ims")) {
                $this->http->Log(">>> " . $error, LOG_LEVEL_ERROR);
                $stop = true;
            } else {
                // Table "Account Activity"
                $nodes = $this->http->XPath->query("//table[@id = 'main']//table//tr[td[contains(text(), 'As of')]]/following-sibling::tr");

                if ($nodes->length == 0) {
                    $nodes = $this->http->XPath->query("//table[@id = 'main']//table//tr[td[contains(text(), 'Per')]]/following-sibling::tr");
                }
                $this->http->Log("Total nodes found: " . $nodes->length);

                if ($nodes->length == 0) {
                    break;
                }

                for ($i = 0; $i < $nodes->length; $i++) {
                    $lastActivity = $this->http->FindSingleNode("td[1]", $nodes->item($i));

                    if (strtotime($lastActivity)) {
                        $this->http->Log("Last Activity: " . $lastActivity);
                        $stop = true;

                        break;
                    }// if (strtotime($lastActivity))
                }// for ($i = 0; $i < $nodes->length; $i++)
            }

            // Next period
            $endDate = $startDate;
            $startDate = strtotime("-2 month", $endDate);
        } while (empty($lastActivity) && ($startDate > $finishDate) && !$stop);

        //# Last Activity
        if (isset($lastActivity)) {
            $this->SetProperty("LastActivity", $lastActivity);
            $this->SetExpirationDate(strtotime("+1 year", $membershipYearEnds));
        //# Expiration Date - Membership Year Ends
        } else {
            $this->SetExpirationDate($membershipYearEnds);
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        // repeat request for international
        if (!empty($this->AccountFields['Login3'])) {
            $domain = $this->AccountFields['Login3'];
        } else {
            $domain = 'com';
        }

        $arg['CookieURL'] = "https://www.e-rewards.{$domain}/reviewaccount.do";
        $arg["SuccessURL"] = "https://www.e-rewards.{$domain}/reviewaccount.do";

        return $arg;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['AvailableToRedeem']) && (strpos($properties['AvailableToRedeem'], '$') !== false)) {
            $fields['BalanceFormat'] = '$%0.2f';
        }

        return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $fields['BalanceFormat']);
    }

    /*
    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions)) || empty($region)) {
            $region = 'com';
        }

        return $region;
    }
    */

    protected function checkRegionSelection()
    {
        $this->logger->notice(__METHOD__);

        $region = $this->AccountFields['Login3'];

        $this->logger->debug("Login3: {$region}");

        if (in_array($region, array_flip($this->regionOptions)) && !empty($region)) {
            $this->logger->debug("set doman: {$region}");

            return $region;
        }

        $region = $this->AccountFields['Login2'];

        $this->logger->debug("Login2: {$region}");

        if (in_array($region, array_flip($this->regionOptions)) && !empty($region)) {
            $this->logger->debug("set doman: {$region}");

            return $region;
        }

        $this->logger->debug("set doman: com");

        return 'com';
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $response = $this->http->JsonLog();

        if ($response->response->emailAddress ?? null) {
            return true;
        }

        return false;
    }
}
