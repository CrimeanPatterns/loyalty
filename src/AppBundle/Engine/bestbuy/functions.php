<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerBestbuy extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public static function GetAccountChecker($accountInfo)
    {
        if ($accountInfo['Login2'] != 'ca') {
            require_once __DIR__ . "/TAccountCheckerBestbuySelenium.php";

            return new TAccountCheckerBestbuySelenium();
        } else {
            return new static();
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->http->SetProxy($this->proxyStaticIpDOP());
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        switch ($this->AccountFields['Login2']) {
            case "ca":
                $arg['RedirectURL'] = 'https://www.bestbuyrewardzone.ca/';

                break;

            default:
                $arg['RedirectURL'] = 'https://www-ssl.bestbuy.com/site/olspage.jsp?id=pcat17082&type=page&rdct=s';

                break;
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['NoCookieURL'] = true;

        switch ($this->AccountFields['Login2']) {
            case "ca":
                $arg['CookieURL'] = 'https://www.bestbuyrewardzone.ca/css/rewardzone.css';

                break;

            default:
                $arg['CookieURL'] = 'https://www-ssl.bestbuy.com/site/olspage.jsp?id=pcat17082&type=page&rdct=s';

                break;
        }

        return $arg;
    }

    /*
    function TuneFormFields(&$fields, $values = null)
    {
        $fields["Login2"]["Options"] = [
            "us" => "USA",
            //"ca" => "Canada",
        ];
    }
    */

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case "ca":
                throw new CheckException("As of November 7, 2018 the Reward Zone Program is closed.", ACCOUNT_PROVIDER_ERROR);

                break;

            default:
                if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
                    throw new CheckException("Please enter a valid e-mail address.", ACCOUNT_INVALID_PASSWORD);
                }

//                $this->http->GetURL('https://ipinfo.io/json');
//                $this->http->GetURL('https://www-ssl.bestbuy.com/identity/global/signin');
                $formInputs = $this->getFormFields();
//                if (empty($formInputs))
//                    return $this->checkErrors();
//                foreach ($formInputs as $input)
//                    if (!empty($input['name']))
//                        $this->http->Form[$input['name']] = $input['value'];
//                $this->http->FormURL = 'https://www-ssl.bestbuy.com/identity/authenticate';

                /*$pass = $this->http->FindPreg("/\"hash\":\"([^\"]+)/");
                $login = $this->http->FindPreg("/\"emailFieldName\":\"([^\"]+)/");
                $token = $this->http->FindPreg("/\"token\":\"([^\"]+)/");
                $csiToken = $this->http->FindPreg("/\"csiToken\":\"([^\"]+)/");
                if (!isset($pass) || !isset($login) || !isset($token) || !isset($csiToken))
                    return $this->checkErrors();
                $this->http->SetInputValue($login, $this->AccountFields['Login']);
                $this->http->SetInputValue($pass, $this->AccountFields['Pass']);*/

                /*$this->http->GetURL("https://www-ssl.bestbuy.com/api/csiservice/keys?token={$csiToken}");
                $response = $this->http->JsonLog();

                // get thx_guid
                if ($ZPLANK = $this->http->getCookieByName("ZPLANK"))
                    $this->http->GetURL("https://tmx.bestbuy.com/fp/tags.js?org_id=ummqowa2&session_id={$ZPLANK}&_=".time().date('B'));

                // posting credentials
                $data = "{\"{$login}\":\"{$this->AccountFields['Login']}\",\"{$pass}\":\"".str_replace(array('\\', '"'), array('\\\\', '\"'), $this->AccountFields['Pass'])."\",\"token\":\"{$token}\",\"authKey\":\"\",\"Salmon\":\"FA7F2\"}";
//                $data = "{\"{$login}\":\"{$this->AccountFields['Login']}\",\"{$pass}\":\"".str_replace(array('\\', '"'), array('\\\\', '\"'), $this->AccountFields['Pass'])."\",\"token\":\"{$token}\",\"authKey\":\"\",\"Salmon\":\"FA7F2\"}";
                $this->http->PostURL("https://www-ssl.bestbuy.com/identity/authenticate", $data);*/
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# Login is temporarily disabled
        if ($message = $this->http->FindPreg("/temporarily disabled due to database/ims")) {
            throw new CheckException("Login is temporarily disabled due to database maintenance. Please try after some time. Sorry for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        //# Site maintenance in progress
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'our website is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // BestBuy.com is currently unavailable. Check back soon.
        if ($message = $this->http->FindPreg("/BestBuy.com is currently\s*unavailable\.\s*Check back soon./ims")) {
            throw new CheckException(CleanXMLValue($message), ACCOUNT_PROVIDER_ERROR);
        }
        //# Site maintenance in progress
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Site maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // BestBuy.com is temporarily unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'BestBuy.com is temporarily unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // IS TEMPORARILY UNAVAILABLE FOR SCHEDULED MAINTENANCE.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'IS TEMPORARILY UNAVAILABLE FOR SCHEDULED MAINTENANCE.')]")) {
            throw new CheckException("BestBuy.com is temporarily unavailable for scheduled maintenance. Please check back soon.", ACCOUNT_PROVIDER_ERROR);
        }
        //# To enhance your online shopping experience weíre making updates to myrewardzone.bestbuy.com.
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'making updates to myrewardzone.bestbuy.com')]")) {
            throw new CheckException("To enhance your online shopping experience we're making updates to myrewardzone.bestbuy.com.", ACCOUNT_PROVIDER_ERROR);
        }
        //# There was an error when processing your request
        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "error when processing your request")]')) {
            throw new CheckException('We\'re sorry. There was an error when processing your request. Please try again later. We are working to resolve this issue as quickly as possible. You may also call Reward Zone at 1-888-BEST BUY (1-888-237-8289) for assistance.', ACCOUNT_PROVIDER_ERROR);
        }
        // An error occurred while processing your request.
        if ($message = $this->http->FindPreg('/(An error occurred while processing your request\.)/ims')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//p[contains(text(), "Enter the code from your authenticator app.")]');

        if (!$question) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        // todo: not completed
    }

    public function Login()
    {
        /*
        switch ($this->AccountFields['Login2']) {
            case "ca":
                break;
            default:
        */

        if ($this->http->FindSingleNode('//h1[contains(text(), "2-Step Verification")]')) {
            $this->parseQuestion();

            return false;
        }

        /*if (!$this->http->PostForm())
            return false;

        $response = $this->http->JsonLog(null, true, true);
        if (ArrayVal($response, 'status') == 'failure' &&
            (strstr(ArrayVal($response, 'error'), 'Could not authenticate customer; httpStatus=40')
             ||ArrayVal($response, 'shouldRedirect') === false))
            throw new CheckException("Oops! The e-mail or password did not match our records. Please try again.", ACCOUNT_INVALID_PASSWORD);
        // Need to change password after verification, it's not identification code
        if (ArrayVal($response, 'status') == 'stepUpRequired' &&
            (ArrayVal($response, 'error') == 'Step-up authentication is required' || ArrayVal($response, 'shouldRedirect') === false))
            $this->throwProfileUpdateMessageException();

        // Password was sent incorrect! Need to check login form
        if (ArrayVal($response, 'errors')
            && ArrayVal($response['errors'][0], 'errorDescription')
            && ArrayVal($response['errors'][0], 'errorDescription') == 'Internal Server Error'
            && $this->http->Response['code'] == 500) {
            $this->logger->notice(">>> Password was sent incorrect! Need to check login form");
            return false;
        }

        if (in_array(ArrayVal($response, 'status'), array('success', 'enroll'))
            && ArrayVal($response, 'shouldRedirect') == 'true' && isset($response['redirectUrl'])) {
            $this->logger->notice("Redirect to {$response['redirectUrl']}");
            $this->http->GetURL($response['redirectUrl']);
        }

        // Please enter a valid e-mail address in this format: yourname@domain.com
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please enter a valid e-mail address')]"))
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        // There was an error during the login process.
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'There was an error during the login process.')]"))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        // We're sorry, there seems to be a problem on our end.
        if ($message = $this->http->FindSingleNode('//li[contains(text(), "We\'re sorry, there seems to be a problem on our end.")]'))
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);*/

        if (
                    $this->http->FindSingleNode('(//button[@id = "logout-button"])[1]')
                    || strstr($this->http->currentUrl(), 'profile/c/rwz/overview')
                    || strstr($this->http->currentUrl(), 'loyalty/rewards/overview')
                ) {
            return true;
        }

        if ($this->notMember()) {
            return false;
        }
        /*
        }// switch ($this->AccountFields['Login2'])
        */

        return $this->checkErrors();
    }

    public function notMember()
    {
        $this->logger->notice(__METHOD__);
        // Your My Best Buy(Reward Zone) membership is not linked to your BestBuy.com account
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && (
                $this->http->FindPreg("/\"linkStatus\":\"\"/ims")
                || $this->http->FindPreg("/\"linkStatus\":\"I\"/ims")
            )
        ) {
            $this->SetWarning('Your My Best Buy(Reward Zone) membership is not linked to your BestBuy.com account');

            return true;
        }

        return false;
    }

    public function Parse()
    {
        /*
        switch ($this->AccountFields['Login2']) {
            case "ca":
                break;
            default:
        */
        // if URL https://www-ssl.bestbuy.com/profile/rest/c/rwz/detail is not unavailable
        $this->SetBalance($this->http->FindPreg("/\"pointsBalance\":(\d+)/ims"));

        if (!empty($this->Properties['Name']) && $this->notMember()) {
            return;
        }
        // Complete Your Account
        if (
                    $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                    && $this->http->FindPreg("/var topNavData = \{\"isLinked\":\"false\",\"linkStatus\":null,\"firstName\":\"[^\"]+\",\"lastName\":\"[^\"]+\",\"authenticated\":true,\"emailAddress\":\"[^\"]+\",\"loyaltyMemberId\":null,\"globalBbyId\":\"[^\"]+\",\"loyaltyMemberType\":null\};/")
                ) {
            $this->throwAcceptTermsMessageException();

            return;
        }

//        $this->http->GetURL("https://www.bestbuy.com/loyalty/rewards/overview");
        $data = $this->http->JsonLog($this->http->FindPreg("/var\s*initData\s*=\s*(.+);/"));
        // Account Number (Full Member #)
        $this->SetProperty("AccountNumber", $this->http->FindPreg("/\"memberSku\":\"([^\"]+)/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/\"firstName\":\"([^\"]+)/ims") . ' ' . $this->http->FindPreg("/\"lastName\":\"([^\"]+)/ims")));
        // Member ID
        $this->SetProperty("Number", $this->http->FindPreg("/\"memberId\":\"([^\"]+)/ims"));
        // Status
        $this->SetProperty("Status", $data->detail->rewardsOverview->data->tierInfo->tierDescription ?? null);

        if (isset($this->Properties['Status']) && $this->Properties['Status'] == 'CORE TIER') {
            $this->SetProperty("Status", 'Member');
        }
        // Status Expire
        if (isset($data->detail->rewardsOverview->data->tierInfo->expirationDate) && strtotime($data->detail->rewardsOverview->data->tierInfo->expirationDate) > time()) {
            $this->SetProperty("StatusExpire", $data->detail->rewardsOverview->data->tierInfo->expirationDate);
        }
        // Balance - Points
        if (isset($data->detail->rewardsOverview->data->points->pointsBalance)) {
            $this->SetBalance($data->detail->rewardsOverview->data->points->pointsBalance);
        } elseif (isset($data->statusCode, $data->statusMessage) && ($data->statusMessage == "We're sorry something went wrong. Please try again." || $data->statusMessage == "We're sorry, something went wrong. Please try again")) {
            throw new CheckException("We're sorry, Rewards information is currently unavailable.", ACCOUNT_PROVIDER_ERROR);
        } else {
            $this->logger->notice("Balance not found");
        }
        // Pending
        $this->SetProperty("Pending", $data->detail->rewardsOverview->data->points->pendingPoints ?? null);
        // You've spent
        if (isset($data->detail->rewardsOverview->data->points->yearToDateDollarSpent)) {
            $this->SetProperty("Spent", '$' . $data->detail->rewardsOverview->data->points->yearToDateDollarSpent);
        }
        // Certificates Amount
        $this->SetProperty("CertificatesAmount", $data->detail->rewardsOverview->data->certificateInfo->totalAvailableValue ?? null);

        // points to next status, or to retain the current status
        //				$kind = ($this->http->FindSingleNode('//h3[contains(text(), "Retain")]') || $this->http->FindSingleNode('//p[contains(text(), "Requalify")]')) ? 'Retain' : 'Next';
        //				$NeededToStatus = $this->http->FindSingleNode('//ul[@id="StatusStats"]/li[contains(text(),"Still need")]/span');
        //				$NeededToStatus = preg_replace("[^$0-9]", NULL, $NeededToStatus);
        //				$properties["NeededTo{$kind}Status"] = $NeededToStatus;
        //# Still need - My status
        //					$this->SetProperty("NeededTo{$kind}Status", ArrayVal($properties, "NeededTo{$kind}Status"));

        // SubAccounts - My Reward Certificates  // refs #4349

        $this->logger->info('My Reward Certificates', ['Header' => 3]);
        $this->http->GetURL("https://www.bestbuy.com/loyalty/api/rewards/certificate?page=1&size=20");
        $certificates = $this->http->JsonLog();
        // Number of Valid Certificates
        $validCertificates = $this->http->FindPreg("/\"totalMatched\":(\d+)/ims");

        if (!empty($validCertificates)) {
            $this->SetProperty("ValidCertificates", $validCertificates);
        }
        // SubAccounts - My Reward Certificates  // refs #4349
        if (!empty($certificates->records)) {
            $this->http->Log("Total " . count($certificates->records) . " certificates were found");

            foreach ($certificates->records as $certificate) {
//                        $this->http->Log("<pre>".var_export($certificate, true)."</pre>", false);
                $balance = $certificate->certValue;
                $expirationDate = $certificate->expirationDate;
                // barcode  // refs #8508
                $certNumber = $certificate->certNumber;

                if (isset($balance)) {
                    $this->AddSubAccount([
                        'Code'           => 'BestbuyCertificates' . $certNumber,
                        'DisplayName'    => "Reward Certificate # " . $certNumber,
                        'Balance'        => $balance,
                        'ExpirationDate' => strtotime($expirationDate),
                        'BarCode'        => $certNumber,
                        "BarCodeType"    => BAR_CODE_PDF_417,
                    ]);
                }// if (isset($balance))
            }// for($i = 0; $i < $nodes->length; $i++)
        }// if (!empty($certificates))

        // Expiration date  // refs #10202
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
        $data = '{"pageNum":1,"numOfDays":720}';
        $this->http->PostURL("https://www.bestbuy.com/loyalty/api/rewards/history/lookup", $data);
        $response = $this->http->JsonLog(null, false);

        if (isset($response->records[0]->date)) {
            // Last Activity
            $lastActivity = preg_replace("/T.+/", "", $response->records[0]->date);
            $this->SetProperty("LastActivity", $lastActivity);
            $this->SetExpirationDate(strtotime("+12 month", strtotime($lastActivity)));
        }
        /*
        }// switch ($this->AccountFields['Login2'])
        */
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'BestbuyCertificates')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }

    private function getFormFields()
    {
        $this->logger->notice(__METHOD__);
        // refs #13009
        //$allCookies = array_merge($this->http->GetCookies(".bestbuy.com"), $this->http->GetCookies(".bestbuy.com", "/", true));
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $formInputs = [];
        $retry = false;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useChromium();
            //$selenium->disableImages(); // Invalid credentials
            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

//            $selenium->http->GetURL('https://ipinfo.io/json');
//            $selenium->http->GetURL('https://www.bestbuy.com/');
//            $selenium->http->GetURL('https://www-ssl.bestbuy.com/identity/global/signin');
            $selenium->http->GetURL('https://www.bestbuy.com/profile/c/rwz/overview');

            // login
            $loginInput = $selenium->waitForElement(WebDriverBy::id('fld-e'), 10);
            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('fld-p1'), 0);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::className('cia-form__controls__submit'), 0);

            if (!$loginInput || !$passwordInput || !$button) {
                // save page to logs
                $selenium->saveResponse();
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                return $this->checkErrors();
            }
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)');
            sleep(1);
            $button = $selenium->waitForElement(WebDriverBy::className('cia-form__controls__submit'), 0);
            $button->click();
            /*$selenium->driver->executeScript('$(\'form[name = "ciaSignOn"] button.js-submit-button\').get(0).click();window.stop();');

            if ($selenium->waitForElement(WebDriverBy::xpath('//form[@name = "ciaSignOn"]//input'), 5, false)) {
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@name = "ciaSignOn"]//input', 0, false)) as $index => $xKey) {
                    $formInputs[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value")
                    ];
                }// foreach ($this->driver->findElements(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input', 0, false)) as $index => $xKey)
                $cookies = $selenium->driver->manage()->getCookies();
                foreach ($cookies as $cookie)
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], isset($cookie['expiry']) ? $cookie['expiry'] : null);
//                $this->logger->debug(var_export($formInputs, true), ["pre" => true]);
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();

                return $formInputs;
            }// if ($this->waitForElement(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input'), 5, false))*/

            $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Rewards Overview")] | //h1[contains(text(), "Rewards Overview")] | //div[@role="alert"]'), 10);
            // save page to logs
            $selenium->saveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($message = $selenium->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "The e-mail or password did not match our records.")]
                    | //p[.= "In order to protect your account, we need to verify that you are you."]
                    | //div[text() = "Oops! The email or password did not match our records. Please try again."]
                '), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }
            // Sorry, a problem occurred during sign in.
            if ($message = $selenium->waitForElement(WebDriverBy::xpath('
                    //*[contains(text(), "Sorry, a problem occurred during sign in.")]
                    | //p[contains(text(), "Rewards information is currently unavailable.")]
                '), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // We just need to verify that you're you.
            if ($selenium->waitForElement(WebDriverBy::xpath('
                    //h1[contains(text(), "Start Enjoying Your New Account Features")]
                    | //*[contains(text(), "We just need to verify that you\'re you.")]
            '), 0)) {
                $this->throwProfileUpdateMessageException();
            }

            // save page to logs
            $selenium->saveResponse();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            if (!$this->http->FindSingleNode('//h1[contains(text(), "2-Step Verification")]')) {
                $this->http->GetURL('https://www-ssl.bestbuy.com/profile/c/rwz/overview', ['Host' => 'www-ssl.bestbuy.com']);
            }

//                    if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Please check the e-mail we sent to') and contains(., 'to finish adding your new account features.')]"), 0))
//                        $this->throwProfileUpdateMessageException();
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            $this->logger->debug("[attempt]: {$this->attempt}");

            throw new CheckRetryNeededException(3, 10);
        }

        return $formInputs;
    }
}
