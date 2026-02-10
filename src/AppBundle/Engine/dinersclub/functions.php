<?php

class TAccountCheckerDinersclub extends TAccountChecker
{
    use SeleniumCheckerHelper;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $maxAttempt = 4;

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case "jp":
                $url = "https://www.sumitclub.jp/JPCRD/col/action/WA2010101Action/RWA2010101";

                break;

            case "au":
            default:
                $url = "https://cardservicesdirect.com.au/AUCRD/JSO/signon/DisplayUsernameSignon.do";

                break;
        }// switch ($this->AccountFields['Login2'])

        $arg["RedirectURL"] = $url;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        if ($this->AccountFields['Login2'] == 'jp') {
            $this->http->setDefaultHeader("Connection", "keep-alive");
        }
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;

        switch ($this->AccountFields['Login2']) {
            case "jp":
                $this->http->GetURL("https://www.sumitclub.jp/JPCRD/col/action/WA2010301Action/RWA2010301", [], 20);
                $this->http->RetryCount = 2;

                if ($this->http->FindNodes("//input[@value = 'サインアウト']/@value")) {
                    return true;
                }

                break;

            case "au":
            default:
                $this->http->GetURL("https://www.mydinersclub.com/CACWeb/action/Home", [], 20); //todo: need to rewrite on new site

                break;
        }
        $this->http->RetryCount = 2;

        return $this->http->FindSingleNode("//a[contains(text(), 'Log out') or contains(text(), 'Log Out')]") !== null;
    }

    /*
    public function TuneFormFields(&$fields, $values = null)
    {
        parent::TuneFormFields($fields);
        $fields['Login2']['Options'] = [
            ""    => "Select card type",
            "au"  => "Australian cards",
            "jp"  => "Japanese cards",
        ];
    }
    */

    public function uniqueStateKeys()
    {
        $this->logger->notice(__METHOD__);
//        /** @var TAccountChecker $selenium */
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;

            if ($this->AccountFields['Login2'] === 'jp') {
                $selenium->useChromium();
                $selenium->http->setUserAgent($this->http->userAgent);
            } else {
                $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            }
//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();

            switch ($this->AccountFields['Login2']) {
                case "jp":
                    $loginURL = "https://www.sumitclub.jp/JPCRD/col/action/WA2010101Action/RWA2010101";
                    $form = 'loginForm';
                    $login = '//input[@name = "userId"]';
                    $password = '//input[@name = "password"]';
                    $btn = '//input[@name = "nablarch_form1_1"]';
                    $script = '';

                    break;

                default:
                case "au":
                    $loginURL = "https://cardservicesdirect.com.au/AUCRD/JSO/signon/DisplayUsernameSignon.do";
                    $form = 'SignonForm';
                    $login = '//input[@name = "username"]';
                    $password = '//input[@name = "password"]';
                    $btn = '//a[@id = "link_lkSignOn"]';
                    $script = '';

                    break;
            }// switch ($this->AccountFields['Login2'])

            $selenium->http->GetURL($loginURL);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath($login), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath($password), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath($btn), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return $this->checkErrors();
            }

//            $mover = new MouseMover($selenium->driver);
//            $mover->logger = $this->logger;
//            $mover->duration = rand(300, 1000);
//            $mover->steps = rand(10, 20);
//
//            $mover->moveToElement($loginInput);
//            $mover->click();
//            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 6);
//
//            $mover->moveToElement($passwordInput);
//            $mover->click();
//            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 6);

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // Sign In
            if (in_array($this->AccountFields['Login2'], [
                'jp',
                'au',
            ])
            ) {
                $this->solvePuzzleCaptcha($selenium);
                // save page to logs
                $this->savePageToLogs($selenium);

                try {
                    $this->logger->debug("click by btn");
                    $this->increaseTimeLimit(120);
                    $button->click();
                } catch (TimeOutException | Facebook\WebDriver\Exception\TimeoutException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());
                    $this->increaseTimeLimit(120);
                    $selenium->driver->executeScript('window.stop();');
                    $this->savePageToLogs($selenium);

                    $button = $selenium->waitForElement(WebDriverBy::xpath($btn), 0);
                    $this->logger->debug("click by btn");
                    $button->click();
                }

                if (
                    in_array($this->AccountFields['Login2'], ['au'])
                ) {
                    $rewards = $selenium->waitForElement(WebDriverBy::xpath("//li[a[contains(., 'View Reward Points Balance')]] | //li[a[contains(., 'View / Redeem Rewards')]] | //li[a[contains(., 'Review rewards balance now')]] | //li[a[contains(., 'View Rewards Balance & Redeem')]] | //h1[contains(text(), 'Access Denied')]"), 10);
                    // Name
                    $this->savePageToLogs($selenium);
                    $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'welcome_msg']", null, true, "/(?:Welcome to Card\s*Services Online|Happy\s*Birthday\s*!)\s*\!?\s*([^<]+)/")));
                    // Open rewards popup
                    if ($rewards) {
                        $this->logger->notice("Open rewards popup");
                        $rewards->click();

                        $selenium->waitForElement(WebDriverBy::xpath("//div[@class = 'cA-rewHom-displaySummaryCard']"), 40);
                    } else {
                        // The User ID and/or Password you entered is invalid or you are not registered.
                        if ($message = $this->http->FindPreg("/(The information entered is not recognised. Please check you have entered the correct User ID and Password\.)/ims")) {
                            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                        }

                        if ($message = $this->http->FindPreg("/(Your sign on attempt has failed due to expiration\. Pls reset your password)/ims")) {
                            throw new CheckException($message . ".", ACCOUNT_INVALID_PASSWORD);
                        }

                        if ($this->http->FindPreg("/>Please check your information and try again. You may have recently received a new Card Services Credit Card. Be sure you are entering your new card details.<br>/")) {
                            $this->throwProfileUpdateMessageException();
                        }

                        return false;
                    }

                    $this->savePageToLogs($selenium);

                    $cards = $this->http->XPath->query("//div[@class = 'cA-rewHom-displaySummaryCard']");
                    $this->logger->debug("Total {$cards->length} cards were found");
                    $detectedCards = $subAccounts = [];

                    for ($i = 0; $i < $cards->length; $i++) {
                        $card = $cards->item($i);
                        $code = $this->http->FindSingleNode("div[1]", $card, true, "/XXXXXXXXXX(\d{4})/ims");
                        $displayName = $this->http->FindSingleNode("div[1]", $card);
                        $balance = $this->http->FindSingleNode("div[2]", $card);

                        if (!empty($displayName) && !empty($code)) {
                            if (isset($balance)) {
                                if (!isset($subAccountBalance)) {
                                    $subAccountBalance = 0;
                                }

                                $subAccountBalance += floatval(str_replace([',', '.'], ['', ','], $balance));
                                $cardDescription = C_CARD_DESC_ACTIVE;
                            } else {
                                $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                            }
                            $detectedCards[] = [
                                "Code"            => 'citybankau' . $code,
                                "DisplayName"     => $displayName,
                                "CardDescription" => $cardDescription,
                            ];
                            $subAccount = [
                                'Code'              => 'citybankau' . $code,
                                'DisplayName'       => $displayName,
                                'Balance'           => $balance,
                                "Number"            => $code,
                                "BalanceInTotalSum" => true,
                            ];
                            $subAccounts[] = $subAccount;
                        }// if (!empty($displayName) && !empty($code))
                    }// for ($i = 0; $i < $cards->length; $i++)
                    // detected cards
                    if (!empty($detectedCards)) {
                        $this->SetProperty("DetectedCards", $detectedCards);
                    }

                    if (!empty($subAccounts)) {
                        // Set Sub Accounts
                        $this->logger->debug("Total subAccounts: " . count($subAccounts));
                        // SetBalance n\a
                        $this->SetBalanceNA();

                        if (isset($subAccountBalance)) {
                            $this->SetBalance($subAccountBalance);
                        }

                        // Set SubAccounts Properties
                        $this->SetProperty("SubAccounts", $subAccounts);
                    }// if(isset($subAccounts))
                    /*
                     * Credit Card Rewards
                     *
                     * We are unable to process your request at the moment.
                     * If you continue to encounter this problem,
                     * please call our 24-Hour Citiphone Banking at 13 24 84 or at +61 2 8225 0615
                     * if you are calling from overseas.
                     */
                    elseif (($this->http->FindSingleNode("//div[contains(text(), 'We are unable to process your request at the moment.')]")
                            /*
                             * Credit Card Rewards
                             *
                             * To access Cards Services, you need to have an eligible product.
                             */
                            || $this->http->FindSingleNode("//div[contains(text(), 'To access Cards Services, you need to have an eligible product.')]")
                            /*
                             * Reward Catalogue
                             *
                             * Your card is not eligible for this service. For enquiries, please contact our 24-hour CitiPhone Banking at (852) 2860 0333.
                             */
                            || $this->http->FindSingleNode("//div[contains(normalize-space(text()), 'Your card is not eligible for this service.')]")
                            /*
                             * Credit Card Rewards
                             *
                             * We apologise, an error has occurred whilst processing your request. For assistance, please contact us on 1800 801 732.
                             */
                            || $this->http->FindSingleNode("//div[contains(text(), 'We apologise, an error has occurred whilst processing your request.')]"))
                        && !empty($this->Properties['Name'])
                    ) {
                        $this->SetBalanceNA();
                    }

                    // click "Sign Out"
                    $this->logger->debug('click "Sign Out"');
                    $selenium->http->GetURL("https://cardservicesdirect.com.au/AUCRD/JSO/signoff/SummaryRecord.do?logOff=true");
                }
            } else {
                $selenium->driver->executeScript("{$script};window.stop();");
            }

            if ($this->AccountFields['Login2'] == 'jp') {
                $selenium->waitForElement(WebDriverBy::xpath("
                    //input[@value = 'サインアウト' or @value = 'Sign out']/@value
                    | //span[contains(text(), 'ユーザーID・パスワードのいずれか、もしくは両方に誤りがあります。再度ご入力をお願いします。')]
                    | //span[contains(., 'ユーザーIDまたはパスワードが正しくない　・パズル認証のパズルが完成していない（パズル認証が表示されている場合）')]
                "), 10);

//                $cookies = $selenium->driver->manage()->getCookies();
//                foreach ($cookies as $cookie)
//                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);

                // save page to logs
                $this->savePageToLogs($selenium);

                if ($this->http->FindNodes("//input[@value = 'サインアウト' or @value = 'Sign out']/@value")) {
                    $this->logger->info("Parse", ['Header' => 2]);
                    // Name
                    $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'name']")));

                    // collect all cards with points
                    $cards = $this->http->FindNodes("//select[@name = 'WA20111_form.cardInsSeq']//option[not(@selected)]/@value");
                    $this->logger->debug("Total " . count($cards) . " cards were found");

                    do {
                        if (isset($card, $displayName)) {
                            $selenium->driver->executeScript("document.querySelector(\"select[name = 'WA20111_form.cardInsSeq']\").selectedIndex = document.querySelector(\"select[name = 'WA20111_form.cardInsSeq'] option[value = '{$card}']\").index;");
                            $selenium->driver->executeScript("$('input[name = \"nablarch_form3_1\"]').get(0).click();");
                            $selenium->waitForElement(WebDriverBy::xpath('
                                    //select[@name = "WA20111_form.cardInsSeq"]//option[@selected and not(contains(text(), "' . $displayName . '"))]
                            '), 10);
                            // save page to logs
                            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                            $this->http->SaveResponse();
                        }// if (isset($card) && $this->http->ParseForm("ICardCommon/ICARD/icardRewardInquiryContext"))
                        // DisplayName
                        $displayName = $this->http->FindSingleNode("//select[@name = 'WA20111_form.cardInsSeq']//option[@selected]");
                        $this->logger->info("Card: {$displayName}", ['Header' => 3]);
                        // Card #
                        $code = $this->http->FindSingleNode("//select[@name = 'WA20111_form.cardInsSeq']//option[@selected]", null, true, "/(?:\d+$|_(\d+))/ims");
                        // Balance - Point balance
                        $balance = $this->http->FindSingleNode("//div[contains(@class, 'point-head')]/span/span", null, true, self::BALANCE_REGEXP_EXTENDED);

                        if (isset($balance, $code)) {
                            if (!isset($subAccountBalance)) {
                                $subAccountBalance = 0;
                            }

                            $subAccountBalance += floatval(str_replace([',', '.'], ['', ','], $balance));

                            $this->AddSubAccount([
                                'Code'              => 'dinersclub' . $code,
                                'DisplayName'       => $displayName,
                                "Balance"           => $balance,
                                'Number'            => $code,
                                'BalanceInTotalSum' => true,
                            ], true);

                            $this->SetBalance($subAccountBalance);
                        }
                        $card = array_shift($cards);
                    } while ($this->http->ParseForm("nablarch_form3") && !empty($card));

                    if (isset($this->Properties['SubAccounts'])) {
                        $this->SetBalanceNA();

                        if (isset($subAccountBalance)) {
                            $this->SetBalance($subAccountBalance);
                        }
                    }
                }
            } else {
                // uniqueStateKey
                if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "' . $form . '"]//input[contains(@name, "X-") and not(contains(@name, "uniqueStateKey"))]'), 5, false)) {
                    foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@name = "' . $form . '"]//input[contains(@name, "X-")]', 0, false)) as $index => $xKey) {
                        $xKeys[] = [
                            'name'  => $xKey->getAttribute("name"),
                            'value' => $xKey->getAttribute("value"),
                        ];
                    }
                    $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
                }
            }

            // save page to logs
            $this->savePageToLogs($selenium);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $xKeys;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case "au":
                $xKeys = $this->uniqueStateKeys();

                return false;
                /*
                if (empty($xKeys))
                    return false;

                $this->http->GetURL("https://www.mydinersclub.com/caclogon/action/LandingPage");
                if (!$this->http->ParseForm("form1"))
                    return $this->checkErrors();
                $this->http->SetInputValue("name", $this->AccountFields['Login']);
                $this->http->SetInputValue("password", $this->AccountFields['Pass']);
                */
                break;

            case "jp":
                if ($this->AccountFields['Login2'] == 'jp') {
                    throw new CheckException('Unfortunately, we are currently do not support this region.', ACCOUNT_PROVIDER_ERROR);
                }

                $this->http->RetryCount = 0;
                $this->http->GetURL("https://www.sumitclub.jp/JPCRD/col/action/WA2010101Action/RWA2010101");
                $this->http->RetryCount = 2;

                if (!$this->http->ParseForm(null, '//input[@name = "userId"]/ancestor::form[1]')) {
                    if ($message = $this->http->FindSingleNode('//strong[contains(text(), "下記の作業日程で、システムメンテナンスを実施しています。")]')) {
                        throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                    }

                    return false;
                }

                $xKeys = $this->uniqueStateKeys();

                return true;

                if (empty($xKeys)) {
                    return false;
                }

                $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
//				$this->http->GetURL("https://www.sumitclub.jp/JPCRD/JPS/portal/SignonLocaleSwitch.do?locale=en_JP");
//                if (!$this->http->ParseForm("SignonForm"))
//                    return $this->checkErrors();
//                $this->http->SetInputValue("username", $this->AccountFields['Login']);
//                $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//                $this->http->SetInputValue("remember", "Y");
//                $this->http->SetInputValue("x", "44");
//                $this->http->SetInputValue("y", "16");
                $this->http->GetURL("https://www.sumitclub.jp/JPCRD/col/action/WA2010101Action/RWA2010101");

                if (!$this->http->ParseForm("nablarch_form1")) {
                    return $this->checkErrors();
                }
                $this->http->FormURL = 'https://www.sumitclub.jp/JPCRD/col/action/WA2010101Action/RWA2010102';
                $this->http->SetInputValue("form.userId", $this->AccountFields['Login']);
                $this->http->SetInputValue("form.password", $this->AccountFields['Pass']);
                $this->http->SetInputValue("nablarch_submit", "nablarch_form1_1");
                $this->http->SetInputValue("save-id", "1");

                break;

            default: //us
                throw new CheckException("Login profile has been deactivated. The Diners Club loyalty program has been transferred to another website.", ACCOUNT_PROVIDER_ERROR);
        }// switch ($this->AccountFields['Login2'])

        if (!empty($xKeys)) {
            foreach ($xKeys as $xKey) {
                if (isset($xKey['name'], $xKey['value'])) {
                    $this->http->SetInputValue($xKey['name'], $xKey['value']);
                }
            }
        }

        return true;
    }

    public function getCSRF()
    {
        $this->logger->notice(__METHOD__);
        // get OWASP_CSRFTOKEN
        $http2 = clone $this->http;
        $http2->PostURL("https://www.dinersclubnorthamerica.com/ptl/JavaScriptServlet?x=" . time() . date("B"), [], ['FETCH-CSRF-TOKEN' => '1']);
        $OWASP_CSRFTOKEN = $http2->FindPreg("/OWASP_CSRFTOKEN:([^<]+)/");

        if (!isset($OWASP_CSRFTOKEN)) {
            $this->logger->error("OWASP_CSRFTOKEN not found");

            return false;
        }// if (!isset($OWASP_CSRFTOKEN))

        return $OWASP_CSRFTOKEN;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case "jp":
                break;

            case "au":
            default:
                if ($this->http->FindSingleNode('//h1[contains(text(), "SRVE0255E: A WebGroup/Virtual Host to handle /AUCRD/JSO/signon/DisplayUsernameSignon.do has not been defined.")]')) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode('
                        //p[contains(text(), "Card Services online is currently unavailable due to scheduled maintenance.")]
                        | //div[contains(text(), "We are having some temporary delays please try again later.")]
                    ')
                ) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;
        }// switch ($this->AccountFields['Login2'])

        return false;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case "jp":
//                if (!$this->http->PostForm())
//                    return $this->checkErrors();
                // Access is allowed
                if ($this->http->FindNodes("//input[@value = 'サインアウト' or @value = 'Sign out']/@value")) {
                    return true;
                }

//                if ($message = $this->http->FindSingleNode("//b[contains(text(), 'At least one of your entries does not match our records.')]"))
//                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                // There is an error in one or both of the user ID and password. Please input again.
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'ユーザーID・パスワードのいずれか、もしくは両方に誤りがあります。再度ご入力をお願いします。')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                /**
                 * I could not sign on. The following reasons can be considered. After confirmation, please try to sign on again.
                 * ・ User ID or password is incorrect
                 * ・ The puzzle of puzzle authentication is not completed (when puzzle authentication is displayed).
                 */
                if ($message = $this->http->FindSingleNode("//span[contains(., 'ユーザーIDまたはパスワードが正しくない　・パズル認証のパズルが完成していない（パズル認証が表示されている場合）')]")) {
                    $this->logger->error("User ID or password is incorrect | The puzzle of puzzle authentication is not completed (when puzzle authentication is displayed)");
                    $this->captchaRetries();
                }

                return false;

                break;

            case "au":
            default:
//				if (!$this->http->PostForm())
//                    return $this->checkErrors();

                if ($this->http->FindSingleNode('//a[contains(text(), "Sign Off")]')) {
                    return true;
                }

                // The User ID and/or Password you entered is invalid or you are not registered.
                if ($message = $this->http->FindPreg("/(The information entered is not recognised. Please check you have entered the correct User ID and Password\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($this->http->FindSingleNode("//span[contains(text(), 'Please check your information and try again. You may have recently received a new Card Services Credit Card. Be sure you are entering your new card details.')]")) {
                    $this->throwProfileUpdateMessageException();
                }
                /*
                // Your Account Has Been Locked
                if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your Account Has Been Locked')]"))
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                // Your Account Has Been Suspended
                if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your Account Has Been Suspended')]"))
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                // You must read and accept the Terms and Conditions before continuing. Once you have read the Terms, check the box and indicate you accept. Then Save and Continue.
                if ($this->http->FindSingleNode("//p[contains(text(), 'You must read and accept the Terms and Conditions before continuing. Once you have read the Terms, check the box and indicate you accept. Then Save and Continue.')]")
                    || $this->http->FindSingleNode("//h1[contains(text(), 'Register for Online Access')]"))
                    $this->throwProfileUpdateMessageException();
                */
                break;
        }
        $this->CheckError($this->http->FindPreg("/Account deactivated/ims"), ACCOUNT_PROVIDER_ERROR);
        // An error has occurred at the server.
        // Log Id=CSRF
        $this->CheckError($this->http->FindPreg("/(An error has occurred at the server\.\s*)<br><br>Log Id=CSRF/ims"), ACCOUNT_PROVIDER_ERROR);
        $error = $this->http->FindSingleNode("//p[contains(text(), 'The maximum number of registration attempts has been exceeded')]");

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been locked')]");
        }

        if (!isset($error)) {
            $error = $this->http->FindSingleNode("//strong[contains(text(), 'User ID has been locked out')]");
        }

        if (isset($error)) {
            throw new CheckException($error, ACCOUNT_LOCKOUT);
        }
        //# Invalid credentials
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Only Corporate accounts can log in at this screen')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Login profile has been deactivated.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($error = $this->http->FindPreg("/(Please check your sign\-on information and try again)/ims")) {
            throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
        }

        //# Select a question
        if (strstr($this->http->currentUrl(), 'selectSecurityQuestions.jsp')
            && $this->http->FindPreg("/(Select a question from each drop-down menu.\s*Enter your answer in the provided field\.)/ims")) {
            throw new CheckException("Diners Club (Club Rewards) website is asking you to select your questions and enter your answers, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->ParseQuestion()) {
            return false;
        }

        return true;
    }

    public function ParseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // Australia
        if ($this->AccountFields['Login2'] == 'au') {
            $question = $this->http->FindSingleNode("//form[@action = 'StrongAuthentication']//input[@name = 'question']/@value");

            if (!isset($question) || !$this->http->ParseForm(null, "//form[@action = 'StrongAuthentication']")) {
                return false;
            }
            $this->Question = $question;
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "Question";

            return true;
        }// if ($this->AccountFields['Login2'] == 'au')
        else {
            $question = $this->http->FindSingleNode("//form[@action = '/ptl/validateRSAAuth.do']//tr[td[contains(text(), 'Answer:')]]/preceding-sibling::tr[1]/td");

            if (isset($question) && $this->http->ParseForm()) {
                $this->AskQuestion($question, $this->http->FindSingleNode("//font[@color = 'red']"));

                return true;
            }
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'au') {
            $this->http->SetInputValue("answer", $this->Answers[$this->Question]);

            if (!$this->http->PostForm()) {
                return false;
            }
            // The answer does not match your account information
            if ($error = $this->http->FindSingleNode("//div[@class = 'errorMsgHolder' and contains(., 'The answer does not match your account information')]/text()[last()]")) {
                $this->AskQuestion($this->Question, $error);

                return false;
            }

            return true;
        } else {
            if (isset($this->http->Form['answer2'])) {
                $this->http->Form['answer2'] = $this->Answers[$this->Question];
            } else {
                $this->http->Form["answer1"] = $this->Answers[$this->Question];
            }
            $this->http->Form["lang_action"] = "Submit";
            $this->http->PostForm();

            return !$this->ParseQuestion();
        }
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case "jp":
                return;
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[@class = 'name']")));
                // open Point balance page
                if (!$this->http->ParseForm("nablarch_form1")) {
                    return;
                }
                $this->http->FormURL = 'https://www.sumitclub.jp/JPCRD/col/action/WA2040101Action/RWA2040101';
                $this->http->SetInputValue("nablarch_submit", "nablarch_form1_45");
                //User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:71.0) Gecko/20100101 Firefox/71.0
                $headers = [
                    "Accept-Encoding"           => "gzip, deflate, br",
                    "Origin"                    => "https://www.sumitclub.jp",
                    "Upgrade-Insecure-Requests" => "1",
                    "DNT"                       => "1",
                ];
                $this->http->RetryCount = 0;
                $this->http->PostForm($headers);
                $this->http->RetryCount = 2;

                // collect all cards with points
                $cards = $this->http->FindNodes("//select[@name = 'WA20111_form.cardInsSeq']//option[not(@selected)]/@value");
                $this->logger->debug("Total " . count($cards) . " cards were found");

                do {
                    if (isset($card) && $this->http->ParseForm("nablarch_form3")) {
                        $this->http->SetInputValue("WA20111_form.cardInsSeq", $card);
                        $this->http->SetInputValue("nablarch_submit", "nablarch_form3_1");
                        $this->http->PostForm($headers);
                    }// if (isset($card) && $this->http->ParseForm("ICardCommon/ICARD/icardRewardInquiryContext"))
                    // DisplayName
                    $displayName = $this->http->FindSingleNode("//select[@name = 'WA20111_form.cardInsSeq']//option[@selected]");
                    $this->logger->info("Card: {$displayName}", ['Header' => 3]);
                    // Card #
                    $code = $this->http->FindSingleNode("//select[@name = 'WA20111_form.cardInsSeq']//option[@selected]", null, true, "/(\d+)$/ims");
                    // Balance - Point balance
                    $balance = $this->http->FindSingleNode("//p[contains(@class, 'card-form-user-point')]/span/span", null, true, self::BALANCE_REGEXP_EXTENDED);

                    if (isset($balance, $code)) {
                        $this->AddSubAccount([
                            'Code'        => 'dinersclub' . $code,
                            'DisplayName' => $displayName,
                            "Balance"     => $balance,
                            'Number'      => $code,
                        ], true);
                    }
                    $card = array_shift($cards);
                } while ($this->http->ParseForm("nablarch_form3") && !empty($card));

                if (isset($this->Properties['SubAccounts'])) {
                    $this->SetBalanceNA();
                }

                if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->Response['code'] == 403) {
                    throw new CheckRetryNeededException(2);
                }

                break;

            case "au":
            default:
                // Balance - Rewards Points
                $this->SetBalance($this->http->FindSingleNode("//tr[@id = 'rewardpoints']/td[2]"));

                if (!isset($this->Balance)) {
                    $this->SetBalance($this->http->FindSingleNode('//td[contains(text(), "Current Balance")]/following::td[1]'));
                }
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@is = 'welcome_msg']", null, true, "/!\s*([^\,]+)/ims")));
                // Number
                $this->SetProperty("Number", $this->http->FindSingleNode("//div[@class = 'customerInfo']", null, true, "/Acct\.\s*Ending\s*(\d+)\./ims"));

                break;
        }
    }

    private function solvePuzzleCaptcha(TAccountCheckerDinersclub $selenium)
    {
        $this->logger->notice(__METHOD__);

        $mover = new MouseMover($selenium->driver);
        $mover->logger = $selenium->logger;
        $mover->duration = 30;
        $mover->steps = rand(10, 20);
        $mouse = $selenium->driver->getMouse();

        $startCoords = $this->getStartCaptchaCoordinates($selenium);

        if (!$startCoords) {
            return false;
        }
        $mover->moveToCoordinates($startCoords, ['x' => 0, 'y' => 0]);
        // $selenium->saveResponse();
        $this->increaseTimeLimit(120);
        $endCoords = $this->getEndCaptchaCoordinates($selenium);

        if (!$endCoords) {
            return false;
        }

        $mouse->mouseDown();
        sleep(1);
        $mover->moveToCoordinates($endCoords, ['x' => 0, 'y' => 0]);
        $mouse->mouseUp();
        sleep(1);
        $this->savePageToLogs($selenium);

        return true;
    }

    private function getEndCaptchaCoordinates(TAccountCheckerDinersclub $selenium)
    {
        $this->logger->notice(__METHOD__);
        $elem = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@id, "image-area")]'), 10);

        if (!$elem) {
            return false;
        }
        $elemCoords = $elem->getCoordinates()->inViewPort();
        $instructions = 'Click on the very center of gray puzzle / Нажмите на самый центр серого паззла';
        $coords = $this->getCaptchaCoordinates($selenium, $elem, $instructions);

        if (!$coords) {
            return false;
        }

        return [
            'x' => $elemCoords->getX() + $coords[0]['x'],
            'y' => $elemCoords->getY() + $coords[0]['y'],
        ];
    }

    private function getStartCaptchaCoordinates(TAccountCheckerDinersclub $selenium)
    {
        $this->logger->notice(__METHOD__);
        $elem = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@id, "piece-area")]'), 10);

        if (!$elem) {
            return false;
        }
        $elemCoords = $elem->getCoordinates()->inViewPort();
        $instructions = 'Click on very the center of puzzle piece / Нажмите на самый центр кусочка паззла';
        $coords = $this->getCaptchaCoordinates($selenium, $elem, $instructions);

        if (!$coords) {
            return false;
        }

        return [
            'x' => $elemCoords->getX() + $coords[0]['x'],
            'y' => $elemCoords->getY() + $coords[0]['y'],
        ];
    }

    /**
     * @param RemoteWebElement | Facebook\WebDriver\Remote\RemoteWebElement $elem Element which should be screenshoted
     */
    private function getCaptchaCoordinates(TAccountCheckerDinersclub $selenium, $element, string $instructions)
    {
        $this->logger->notice(__METHOD__);

        $this->savePageToLogs($selenium);

        try {
            $screenshotPath = $selenium->takeScreenshotOfElement($element);
            $newPath = preg_replace('/.png$/', '.jpg', $screenshotPath);
            rename($screenshotPath, $newPath);
            $screenshotPath = $newPath;
        } catch (Throwable $e) {
            $this->logger->error("Throwable exception: " . $e->getMessage());

            return false;
        }

        $this->logger->debug('Path to captcha screenshot ' . $screenshotPath);
        $data = [
            'coordinatescaptcha' => '1',
            'textinstructions'   => $instructions,
        ];

        try {
            $this->increaseTimeLimit(300);
            $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
            $this->recognizer->RecognizeTimeout = 120;
            $text = $this->recognizer->recognizeFile($screenshotPath, $data);
        } catch (CaptchaException $e) {
            $this->logger->error("CaptchaException: {$e->getMessage()}");

            if ($e->getMessage() === 'server returned error: ERROR_CAPTCHA_UNSOLVABLE') {
                // almost always solvable
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();

                if ($this->attempt == $this->maxAttempt) {
                    $this->captchaRetries();
                }

                return false;
            }

            if (strstr($e->getMessage(), 'CURL returned error: Operation timed out after ')
                || strstr($e->getMessage(), 'timelimit (120) hit')
                || strstr($e->getMessage(), 'CURL returned error: Failed to connect to rucaptcha.com port 80')
                || strstr($e->getMessage(), 'CURL returned error: Empty reply from server')
                || strstr($e->getMessage(), 'CURL returned error: Connection timed out after ')
            ) {
                if ($this->attempt == $this->maxAttempt) {
                    $this->captchaRetries();
                }

                return false;
            } else {
                throw $e;
            }
        } finally {
            unlink($screenshotPath);
        }
        $this->increaseTimeLimit(120);

        return $selenium->parseCoordinates($text);
    }

    private function captchaRetries()
    {
        $this->logger->notice(__METHOD__);

        throw new CheckRetryNeededException(4, 0, self::CAPTCHA_ERROR_MSG);
    }
}
