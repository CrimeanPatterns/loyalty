<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBestbuySelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->http->LogHeaders = true;
        $this->UseSelenium();
        $this->setProxyMount();
        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        //$this->disableImages(); // Invalid credentials
        $this->useCache();
        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->http->GetURL('https://www.bestbuy.com/profile/c/rwz/overview');
        // password
        $passwordInput = $this->waitForElement(WebDriverBy::id('fld-p1'), 10);
        // login
        $loginInput = $this->waitForElement(WebDriverBy::id('fld-e'), 0);
        // Sign In
        $button = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "cia-form__controls__submit")]'), 0);
        $this->saveResponse();
        $email = strtolower(Html::cleanXMLValue($this->http->FindSingleNode('//div[@class = "prefilled-value"]')));

        if ((!$loginInput && $email != strtolower($this->AccountFields['Login'])) || !$passwordInput || !$button) {
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        if ($loginInput) {
            $loginInput->sendKeys($this->AccountFields['Login']);
        }
        $passwordInput->sendKeys($this->AccountFields['Pass']);
        sleep(1);
        $button = $this->waitForElement(WebDriverBy::xpath('//*[contains(@class, "cia-form__controls__submit")]'), 0);
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //*[self::h2 or self::h1][contains(text(), "Rewards Overview")]
            | //div[@role="alert"]
            | //h1[contains(text(), "Enter a New Password")]
            | //h1[contains(text(), "Complete Your Account")]
            | //p[contains(text(), "Please enter a valid email address.")]
            | //div[contains(text(), "The password was incorrect. Please try again.")]
            | //h2[contains(text(), "We’ve made a change to the My Best Buy® Membership program that will affect your account.")]
            | //h1[contains(text(), "Start enjoying your new account features")]
            | //h1[contains(text(), "Let\'s verify your email address.")]
            | //h1[contains(text(), "Employee Verification")]
        '), 15);
        $this->saveResponse();
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //span[contains(text(), "The e-mail or password did not match our records.")]
                | //span[contains(normalize-space(), "Please enter a valid email address.")]
                | //p[.= "In order to protect your account, we need to verify that you are you."]
                | //div[text() = "Oops! The email or password did not match our records. Please try again."]
                | //div[text() = "The password was incorrect. Please try again."]
                | //div[contains(text(), "We didn\'t find an account with that email address.")]
                | //p[contains(text(), "Please enter a valid email address.")]
                | //div[contains(text(), "The password was incorrect. Please try again.")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Sorry, a problem occurred during sign in.
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //*[contains(text(), "Sorry, a problem occurred during sign in.")]
                | //p[contains(text(), "Rewards information is currently unavailable.")]
                | //div[contains(text(), "Sorry, something went wrong. Please try again.")]
                | //h1[contains(text(), "Sorry, something went wrong.")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // We just need to verify that you're you.
        if ($this->waitForElement(WebDriverBy::xpath('
                //h1[contains(text(), "Start Enjoying Your New Account Features")]
                | //*[contains(text(), "We just need to verify that you\'re you.")]
                | //h1[contains(text(), "Enter a New Password")]
                | //h1[contains(text(), "Complete Your Account")]
                | //h1[contains(text(), "Please confirm your email.")]
                | //h1[contains(text(), "Employee Verification")]
                | //h2[contains(text(), "Complete Your Account")]
            '), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "We’ve made a change to the My Best Buy® Membership program that will affect your account.")]
            '), 0)
        ) {
            $this->throwAcceptTermsMessageException();
        }

        if ($skip = $this->waitForElement(WebDriverBy::xpath('
                //button[contains(text(), "Skip For Now") or contains(text(), "Skip for now")]
            '), 0)
        ) {
            $skip->click();

            if ($contAnyway = $this->waitForElement(WebDriverBy::xpath('
                //button[contains(text(), "Continue Anyway")]
            '), 5)) {
                $this->saveResponse();
                $contAnyway->click();
            }

            $this->waitForElement(WebDriverBy::xpath('
                //*[self::h2 or self::h1][contains(text(), "Rewards Overview")]
            '), 10);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        }

        // save page to logs
        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->notMember()) {
            return false;
        }

        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Wait! You haven’t verified your information yet.")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if (!$this->http->FindSingleNode('//h1[contains(text(), "2-Step Verification") or contains(text(), "Verify Your Account")]')) {
            $this->http->GetURL('https://www-ssl.bestbuy.com/profile/c/rwz/overview', ['Host' => 'www-ssl.bestbuy.com']);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "2-Step Verification") or contains(text(), "Verify Your Account")]')) {
            $this->parseQuestion();

            return false;
        }

        return $this->checkErrors();
    }

    public function notMember()
    {
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

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@name = "verificationCode"]'), 0);

        if (!$code && ($agreeBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Agree & Continue")]'), 0))) {
            $this->saveResponse();
            $agreeBtn->click();
            $code = $this->waitForElement(WebDriverBy::xpath('//input[@name = "verificationCode"]'), 5);
        }

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue")]'), 0);
        $this->saveResponse();
        $question = $this->http->FindSingleNode('//p[
            contains(text(), "Enter the code from your authenticator app.")
            or contains(text(), "We sent a text message with a security code to")
            or contains(text(), "We\'ve sent a code to")
            or contains(text(), "Please enter the verification code we emailed to")
        ]');

        if (!isset($question) || !$code || !$button) {
            return false;
        }

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, "Question");

            return false;
        }
        $code->clear();
        $code->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);

        $this->driver->executeScript("var remember = $('#cia-trust-me') ; if (remember) remember.prop('checked', 'checked');");

        $this->logger->debug("click button...");
        $button->click();

        sleep(5);

        $result = $this->waitForElement(WebDriverBy::xpath('
            //*[self::h2 or self::h1][contains(text(), "Rewards Overview")]
            | //h1[contains(text(), "Enter a New Password")]
            | //div[@role="alert"]
        '), 5);
        $this->saveResponse();

        if ($result && strstr($result->getText(), 'The code you entered may be incorrect or expired.')) {
            $this->holdSession();
            $this->AskQuestion($question, 'The code you entered may be incorrect or expired. Please check the code and try again.', "Question");

            return false;
        }

        if ($this->waitForElement(WebDriverBy::xpath('
                //h1[contains(text(), "Enter a New Password")]
            '), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "Question") {
            return $this->parseQuestion();
        }

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->LogHeaders = true;
        $this->browser->setDefaultHeader('Accept-Encoding', 'gzip');
        $this->browser->setHttp2(true);
        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function Parse()
    {
        if ($this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "Complete Your Account")]
            '), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        $this->parseWithCurl();
        // if URL https://www-ssl.bestbuy.com/profile/rest/c/rwz/detail is not unavailable
        $this->SetBalance($this->browser->FindPreg("/\"pointsBalance\":(\d+)/ims"));

        if (!empty($this->Properties['Name']) && $this->notMember()) {
            return;
        }
        // Complete Your Account
        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->browser->FindPreg("/var topNavData = \{\"isLinked\":\"false\",\"linkStatus\":null,\"firstName\":\"[^\"]+\",\"lastName\":\"[^\"]+\",\"authenticated\":true,\"emailAddress\":\"[^\"]+\",\"loyaltyMemberId\":null,\"globalBbyId\":\"[^\"]+\",\"loyaltyMemberType\":null\};/")
        ) {
            $this->throwAcceptTermsMessageException();

            return;
        }

//        $this->browser->GetURL("https://www.bestbuy.com/loyalty/rewards/overview");
        $data = $this->http->JsonLog($this->browser->FindPreg("/var\s*initData\s*=\s*(.+);/"));
        // Account Number (Full Member #)
        $this->SetProperty("AccountNumber", $this->browser->FindPreg("/\"memberSku\":\"([^\"]+)/ims"));
        // Name
        $this->SetProperty("Name", beautifulName($this->browser->FindPreg("/\"firstName\":\"([^\"]+)/ims") . ' ' . $this->browser->FindPreg("/\"lastName\":\"([^\"]+)/ims")));
        // Member ID
        $this->SetProperty("Number", $this->browser->FindPreg("/\"memberId\":\"([^\"]+)/ims"));
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

        // SubAccounts - My Reward Certificates  // refs #4349

        $this->logger->info('My Reward Certificates', ['Header' => 3]);
        $this->browser->GetURL("https://www.bestbuy.com/loyalty/api/rewards/certificate?page=1&size=20");
        $certificates = $this->browser->JsonLog();
        // Number of Valid Certificates
        $validCertificates = $this->browser->FindPreg("/\"totalMatched\":(\d+)/ims");

        if (!empty($validCertificates)) {
            $this->SetProperty("ValidCertificates", $validCertificates);
        }
        // SubAccounts - My Reward Certificates  // refs #4349
        if (!empty($certificates->records)) {
            $this->logger->debug("Total " . count($certificates->records) . " certificates were found");

            foreach ($certificates->records as $certificate) {
//                        $this->browser->Log("<pre>".var_export($certificate, true)."</pre>", false);
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

        // Expiration date  // refs #10202, 10202
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->browser->setDefaultHeader('Content-Type', 'application/json; charset=utf-8');
        $data = '{"pageNum":1,"numOfDays":720}';
        $this->browser->PostURL("https://www.bestbuy.com/loyalty/api/rewards/history/lookup", $data);
        $response = $this->browser->JsonLog(null, 0);

        if (isset($response->records[0]->date)) {
            // Last Activity
            $lastActivity = preg_replace("/T.+/", "", $response->records[0]->date);
            $this->SetProperty("LastActivity", $lastActivity);
            $this->SetExpirationDate(strtotime("+24 month", strtotime($lastActivity)));
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode('(//button[@id = "logout-button"])[1]')
            || strstr($this->http->currentUrl(), 'profile/c/rwz/overview')
            || strstr($this->http->currentUrl(), 'loyalty/rewards/overview')
            || strstr($this->http->currentUrl(), '://www.bestbuy.com/identity/signin/verifyEmployee')
        ) {
            return true;
        }

        return false;
    }
}
