<?php

class TAccountCheckerDbs extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const OTP = 'Please enter OTP. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.';

    /*
    public function GetRedirectParams($targetURL = null) {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://rewards.dbs.com/Home.aspx';
        $arg['SuccessURL'] = 'https://rewards.dbs.com/ShoppingCart.aspx';

        return $arg;
    }

    public function TuneFormFields(&$arFields, $values = NULL) {
        parent::TuneFormFields($arFields);
        $arFields["Login"]["Note"] = 'For Singaporeans and PRs holding NRIC, enter your full NRIC details, eg. S1234567A For Malaysians, add a \'M\' before IC details, eg. MXXXXXXXXXXXX For Passport holders for all nationalities, please add a \'P\' before your passport number, eg. PXXXXX ';
        $arFields["Pass"]["Note"] = '15 or 16 digit credit card number';
    }
    */

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        /*
        $this->useFirefox();
        $this->setKeepProfile(true);
        */
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        if (empty($this->AccountFields['Pass'])) {
            throw new CheckException("To update this DBS (DBS Points) account you need to fill Credit Card no. in the 'Password' field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR); /*review*/
        }

        $this->http->removeCookies();
//        $this->http->GetURL('https://rewards.dbs.com/Home.aspx');

        try {
            $this->http->GetURL('https://iam.dbs.com.sg/iam/v1/authorize?response_type=code&client_id=ccrwd01&redirect_uri=https://rewards.dbs.com/redirectPage.aspx');
        } catch (
            Facebook\WebDriver\Exception\WebDriverCurlException
            | WebDriverCurlException $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(3, 0);
        }

        $loginInput = $this->waitForElement(\WebDriverBy::xpath("//input[@name = 'username']"), 10);
        $passInput = $this->waitForElement(\WebDriverBy::id('Password'), 0);
        $button = $this->waitForElement(\WebDriverBy::xpath('//button[contains(text(), "Login")]'), 0);
        $this->saveResponse();

        if ($loginInput === null || $passInput === null || $button === null) {
            return false;
        }
        $loginInput->sendKeys($this->AccountFields['Login']);
        $passInput->sendKeys($this->AccountFields['Pass']);
        $button->click();

        /*
        if (!$this->http->ParseForm(null, '//form[//input[@name = "username"]]')) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('txtIDNo', $this->AccountFields['Login']);
//        $this->http->SetInputValue('txtCreditCardNumber', $this->AccountFields['Pass']);
        $data = [
            "UserID"    => $this->AccountFields['Login'],
            "DBSCardNo" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"           => "*
        /*",
            "Content-Type"     => "application/json; charset=utf-8",
            "X-Requested-With" => "XMLHttpRequest",
        ];
        $this->http->PostURL("https://rewards.dbs.com/Home.aspx/SubmitLoginDetails", json_encode($data), $headers);
        */

        return true;
    }

    public function Login()
    {
        /*
        $response = $this->http->JsonLog();
        if (isset($response->d)) {
            $error = $this->http->FindPreg("/Error::(.+)/", false, $response->d);
            $this->logger->error($error);
            // Sorry, we are unable to find the account / card. Please check the details you have entered or call us at 1800-111-1111.
            if (stristr($error, 'we are unable to find the account / card.')) {
                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            if (
                // Sorry, we have experienced an error. Please contact our customer service at 1800-111-1111.
                stristr($error, 'Sorry, we have experienced an error. Please contact our customer service at ')
                // We have experienced an error. Please contact our customer service at 1800-111-1111.
                || stristr($error, 'We have experienced an error. Please contact our customer service at ')
                // Sorry, we are down for maintenance now. Please contact our customer service at 1800-111-1111.
                || stristr($error, 'Sorry, we are down for maintenance now. ')
                || stristr($error, 'Login Error')
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }
        }
        */

        try {
            $this->waitForElement(\WebDriverBy::xpath('//div[@id = "errormsg"] | //h1[strong[contains(text(), "Two Step Verification")]]'), 10);
            $this->saveResponse();
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException | UnexpectedAlertOpenException $e) {
                $this->logger->error("LoadLoginForm -> exception: " . $e->getMessage());
            } finally {
                $this->logger->debug("LoadLoginForm -> finally");
            }
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->twoStepVerification(true)) {
            return false;
        }

        $this->saveResponse();
        $message = $this->http->FindSingleNode('//div[@id = "errormsg"]');

        if ($message) {
            $this->logger->error("[Error]: {$message}");

            if (
                stristr($message, 'The PIN length should range from 6 to 9 digits.')
                || stristr($message, 'Invalid characters not allowed in PIN.')
                || stristr($message, 'Sorry, you have entered an invalid User ID.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'This service is temporarily unavailable. Please try again at a later time.')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }// if ($message)

        return $this->checkErrors();
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($step == '2fa') {
            $this->saveResponse();

            return $this->twoStepVerification();
        }

        $this->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'Logoff')]"), 5); //todo
        $this->saveResponse();

        return true;
    }

    public function Parse()
    {
        if (!$this->http->FindSingleNode('//span[@id = "spnTotalPts"]')) {
            if ($message = $this->http->FindSingleNode('//td/div[@id = "DivErrMsg" and contains(text(), "You did not log out in the previous session, please login again after 20 mins.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            try {
                $this->http->GetURL("https://rewards.dbs.com/ShoppingCart.aspx");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
            $this->waitForElement(WebDriverBy::xpath('//span[@id = "spnTotalPts"]'), 5);
            $this->saveResponse();
        }
        /*
        if ($this->http->currentUrl() != 'https://rewards.dbs.com/ShoppingCart.aspx') {
            $this->http->GetURL("https://rewards.dbs.com/ShoppingCart.aspx");
        }
        */
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // Current Total
        $this->SetProperty('Total', $this->http->FindSingleNode('//span[@id = "spnTotalPts"]'));
        // Current Redeemed Total
        $this->SetProperty('Redeemed', $this->http->FindSingleNode('//span[@id = "cartTotal"]'));
        // Balance - DBS Points
        /**
         * ar balance = parseInt($('#spnTotalPts').html()) - parseInt($('#cartTotal').html());
         * $('#balancePts').html(balance + '');.
         */
        if (isset($this->Properties['Total'], $this->Properties['Redeemed'])) {
            $this->SetBalance($this->Properties['Total'] - $this->Properties['Redeemed']);
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//span[@id = "lblUserName" and not(contains(text(), "Rewards"))]')));

        $cards = $this->http->XPath->query("//table[@class = 'pointstable']//tr");
        $this->logger->debug("Total {$cards->length} cards were found");

        foreach ($cards as $card) {
            $expiringBalance = 0;
            $exp = null;
            $displayName = trim($this->http->FindSingleNode('td[1]', $card, true, "/XXXX(\d+)/"));
            $balance = trim($this->http->FindSingleNode('td[7]', $card));
            // fin nearest exp date
            for ($i = 2; $i < 6; $i++) {
                $expDate = $this->http->FindSingleNode('//tr[td[table[@class = "pointstable"]]]/preceding-sibling::tr[1]/td[' . $i . ']', null, true, "/Expiring\s*([^<]+)/");
                $expPoints = trim($this->http->FindSingleNode('td[' . $i . ']', $card));
                $this->logger->debug("[Card #{$displayName}]: $expDate - $expPoints");

                if ($expPoints > 0 && (!isset($exp) || $exp >= strtotime($expDate))) {
                    $expiringBalance = $expPoints;
                    $exp = strtotime("+1 month -1 day", strtotime($expDate));
                }// if ($expPoints > 0 && (!isset($exp) || $exp >= strtotime($expDate)))
            }// for ($i = 3; $i < 6; $i++)

            if ($displayName) {
                $this->AddSubAccount([
                    "Code"              => 'dbsCard' . $displayName,
                    "DisplayName"       => "Credit Card ending in {$displayName}",
                    "Balance"           => $balance,
                    "ExpiringBalance"   => $expiringBalance,
                    "ExpirationDate"    => $exp ?? false,
                    "BalanceInTotalSum" => true,
                ], true);
            }
        }// foreach ($cards as $card)

        /**
         * prevent.
         *
         * We are unable to process the request due to the following.
         * You did not log out in the previous session, please login again after 20 mins.
         */
        /*
        if (!$this->http->ParseForm('form1')) {
            $this->logger->error("logout failed!");

            return;
        }
        $this->logger->notice("logout");
        $this->http->SetInputValue("imgLogout.x", "33");
        $this->http->SetInputValue("imgLogout.y", "7");
        $this->http->PostForm();
        */
        $logout = $this->waitForElement(WebDriverBy::xpath('//input[@id = "imgLogout"]'), 0, false);

        if (!$logout) {
            $this->logger->error("logout failed!");

            return;
        }
        $this->driver->executeScript("document.getElementById('imgLogout').click();");
//        $logout->click();

        sleep(5);
        $this->saveResponse();
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('//h1[contains(text(), "Server Error in \'/\' Application.")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function twoStepVerification($generateNewOTP = false)
    {
        $this->logger->notice(__METHOD__);
        $elements = $this->driver->findElements(WebDriverBy::xpath("//h1[strong[contains(text(), 'Two Step Verification')]]/following-sibling::form//input[contains(@name, 'otp')]"));
        $generateOTP = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "GENERATE OTP")]'), 0);
        $authenticate = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "show-element")]//button[contains(text(), "Authenticate")]'), 0);

        if (!$generateOTP || !$authenticate || !$elements) {
            return false;
        }

        if ($generateNewOTP) {
            $generateOTP->click();
            $this->saveResponse();
        }

        if (!isset($this->Answers[self::OTP])) {
            if (!$generateNewOTP) {
                $generateOTP->click();
                $this->saveResponse();
            }

            $this->AskQuestion(self::OTP, null, "2fa");
            $this->holdSession();

            return false;
        }

        if (strlen($this->Answers[self::OTP]) < 6) {
            if (!$generateNewOTP) {
                $generateOTP->click();
                $this->saveResponse();
            }

            $this->AskQuestion(self::OTP, "The one-time password is incorrect.", "2fa");
            $this->holdSession();

            return false;
        }

        $this->logger->debug("entering code...");
        $answer = $this->Answers[self::OTP];
        unset($this->Answers[self::OTP]);

        foreach ($elements as $key => $element) {
            $this->logger->debug("#{$key}: {$answer[$key]}");
            $input = $this->driver->findElement(WebDriverBy::xpath("//h1[strong[contains(text(), 'Two Step Verification')]]/following-sibling::form//input[@name = 'otp" . ($key + 1) . "']"));
            $input->clear();
//            $input = $this->waitForElement(WebDriverBy::xpath("//h1[strong[contains(text(), 'Two Step Verification')]]/following-sibling::form//input[@name = 'otp".($key + 1)."']"), 0);
            $input->click();
            $input->sendKeys($answer[$key]);
            $this->saveResponse();
        }// foreach ($elements as $key => $element)

        $authenticate->click();

        sleep(5);

        if ($authenticate = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "show-element")]//button[contains(text(), "Authenticate")]'), 0)) {
            $this->saveResponse();
            $authenticate->click();
        }

        $this->waitForElement(WebDriverBy::xpath("
            //a[contains(@href, 'Logoff')]
            | //div[@id = 'errormsg']
            | //div[@id = 'DivErrMsg']
        "), 0);
        $this->saveResponse();

        // 1103|The one-time password has expired. Please regenerate a new one.
        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[@id = "errormsg" or @id = "DivErrMsg"]'), 0)) {
            if ($generateOTP = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "GENERATE OTP")]'), 0)) {
                $generateOTP->click();
                $this->saveResponse();
            }

            $this->AskQuestion(self::OTP, $error->getText(), "2fa");
            $this->holdSession();

            return false;
        }

        // You did not log out in the previous session, please login again after 20 mins.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//td/div[@id = "DivErrMsg" and contains(text(), "You did not log out in the previous session, please login again after 20 mins.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/\{\"d\":\"Success::ShoppingCart.aspx\"\}/")) {//todo
            return true;
        }

        return false;
    }
}
