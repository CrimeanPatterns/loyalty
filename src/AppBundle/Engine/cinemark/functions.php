<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCinemark extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.cinemark.com/movie-rewards/your-reward';

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['SubAccountCode']) && strstr($properties['SubAccountCode'], 'Cash')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

//        $this->http->SetProxy($this->proxyReCaptcha());

        $this->UseSelenium();
        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
        $this->setKeepProfile(true);
        */

        /*
        $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
        $this->setProxyGoProxies();
        $this->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->setKeepProfile(true);
        $this->disableImages();
        */

        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->setKeepProfile(true);

        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;
        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);
        */
//        $this->http->setUserAgent(\HttpBrowser::FIREFOX_USER_AGENT);
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return false; //todo: // Cinemark Cash issue, https://redmine.awardwallet.com/issues/14153#note-11

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        $this->http->GetURL('https://www.cinemark.com/Membership/SignIn');
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'EmailAddress' or @id = 'Email']"), 20);
        $this->saveResponse();

        try {
            if (!$login && $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Checking your browser before accessing')] | //h2[contains(text(), 'Checking if the site connection is secure')]"), 0)) {
                // save page to logs
                $this->saveResponse();
                $this->http->GetURL('https://www.cinemark.com/Membership/SignIn');
                $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'EmailAddress' or @id = 'Email']"), 15);
            }
        } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        $this->saveResponse();
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'Password']"), 5);
        $signInButton = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'signInButton']"), 0);

        if (!$login || !$pass || !$signInButton) {
            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript("var remember = document.getElementById('RememberMe'); if (remember) remember.checked = true;");
        $this->saveResponse();

        $signInButton->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //li[contains(@class, "dropdown")]/a/div[contains(@class, "nav-account-user-name")]
            | //span[contains(text(), "An email has been sent.")]
            | //p[contains(text(), "A code has been sent to your email")]
            | //label[contains(text(), "Send an authentication code ")]
            | //div[@class = "validation-summary-errors"]
            | //p[contains(text(), "Our Privacy Policy has been updated. Please review and confirm that you agree.")]
        '), 15);
        $this->saveResponse();

        if ($mfa = $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Send an authentication code ")]'), 0)) {
            $mfa->click();

            $this->waitForElement(WebDriverBy::xpath('
                //li[contains(@class, "dropdown")]/a/div[contains(@class, "nav-account-user-name")]
                | //span[contains(text(), "An email has been sent.")]
                | //p[contains(text(), "A code has been sent to your email")]
            '), 15);
            $this->saveResponse();
        }

        // Errors
        $message = $this->http->FindNodes('//form[@id="signInForm"]/div[@class = "validation-summary-errors"]/ul/li/text()');

        if ($message) {
            $message = implode(' ', $message);
            $this->logger->error($message);

            if (
                strstr($message, "Invalid Email Address or Password.")
                || $message == "Your account has been locked out for 5 minutes due to multiple failed login attempts."
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Our Privacy Policy has been updated. Please review and confirm that you agree.")]')) {
            $this->throwAcceptTermsMessageException();
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Cinemark Cash, https://redmine.awardwallet.com/issues/14153#note-11
        $this->http->GetURL("https://www.cinemark.com/account/cinemarkcash");
        $this->saveResponse();
        $balance = $this->http->FindSingleNode('//h3[contains(text(), "Your balance is")]', null, true, "/Your balance is\s*(.+)/");
        $this->logger->debug("Cinemark Cash: {$balance}");

        if ($balance) {
            $this->AddSubAccount([
                "Code"           => "cinemarkCash",
                "DisplayName"    => "Cinemark Cash",
                "Balance"        => $balance,
            ]);
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL);

        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//span[@id = "DashboardPointsValue"]'));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//h2[@id = "DashboardMemberName"]')));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[@id = "DashboardMemberStatus"]'));
        // Rewards
        $this->SetProperty('Rewards', $this->http->FindSingleNode('//span[@id = "DashboardRewardsValue"]'));
        // Credits
        $this->SetProperty('Credits', $this->http->FindSingleNode('//span[@id = "DashboardCreditsValue"]'));
        // Tickets
        $this->SetProperty('Tickets', $this->http->FindSingleNode('//span[@id = "DashboardTicketsValue"]'));
        // Number - Movie Rewards ID
        $this->SetProperty('Number', $this->http->FindPreg("/AddOrUpdateAccountData\( '" . $this->AccountFields['Login'] . "',\s?(.+?)\s?\);/"));
        // Exp Balance and Exp Date - XX points expire at the end of the month
        if (is_numeric($expBalance = $this->http->FindSingleNode('//p[@class="dashboard-message" and contains(text(), "points expire at the end of the month")]', null, true, '/(\d+) points/'))) {
            $this->SetProperty('ExpiringBalance', $expBalance);
            $this->SetExpirationDate(strtotime(date('Y-m-01', strtotime('+1 month')))); // first second of the next month
        }

        $rewards = $this->http->XPath->query('//a[contains(@class, "card")]/@href');
        $this->logger->debug("Total {$rewards->length} rewards were found");
        $links = [];

        foreach ($rewards as $reward) {
            $url = $reward->nodeValue;
            $this->http->NormalizeURL($url);
            $links[] = $url;
        }

        foreach ($links as $link) {
            $this->http->GetURL($link);
            $displayName = $this->http->FindSingleNode('//div[@class = "connections-title"]');
            $barCode = $this->http->FindSingleNode('
                //div[@class = "barcode-details"]/span[@class = "flg"]
                | //input[@class = "input-promocode"]/@value
            ');

            if (!empty($barCode)) {
                $displayName .= " ({$barCode})";
            }

            $exp =
                $this->http->FindSingleNode('//div[@class = "barcode-details"]/span[contains(text(), "Expires")]', null, true, "/Expires\s*([^<]+)/")
                ?? $this->http->FindSingleNode('//div[contains(@class, "reward-details")]//p[contains(., "Expires")]', null, true, "/Expires\s*([^<]+)/")
            ;
            $this->AddSubAccount([
                "Code"           => "cinemarkReward" . md5($displayName) . $barCode,
                "DisplayName"    => $displayName,
                "Balance"        => $this->http->FindSingleNode('//div[contains(@class, "reward-details")]', null, true, "/(\d+)\s*Points/"),
                "ExpirationDate" => strtotime($exp),
                'BarCode'        => $barCode,
                "BarCodeType"    => BAR_CODE_QR, // refs #21255
            ]);
        }
        $this->http->GetURL('https://www.cinemark.com/movie-rewards/history/points');

        //p[@class="dashboard-message" and contains(text(), "points expire at the end of the month")]
        // https://www.cinemark.com/movie-rewards/history/points

        /*
        // Cinemark Cash, https://redmine.awardwallet.com/issues/14153#note-11
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.cinemark.com/account/cinemarkcash");
        $this->http->RetryCount = 2;
        $balance = $this->http->FindSingleNode('//h3[contains(text(), "Your balance is")]', null, true, "/Your balance is\s*(.+)/");
        $this->logger->debug("Cinemark Cash: {$balance}");

        if (!$balance) {
            return;
        }

        $this->AddSubAccount([
            "Code"           => "cinemarkcash",
            "DisplayName"    => "Cinemark Cash",
            "Balance"        => $this->http->FindSingleNode('//div[contains(@class, "reward-details")]', null, true, "/(\d+)\s*Points/"),
        ]);
        */
    }

    public function ProcessStep($step)
    {
        $question = $this->Question;
        $code = $this->Answers[$question];
        unset($this->Answers[$this->Question]);
        $input = $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "form-group-2fa-v2")]/input[1]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//input[@id = "submit2faCode"]'), 0);

        if (!isset($input, $btn)) {
            $this->saveResponse();

            return false;
        }

        // Clear old codes
        $this->driver->executeScript('Array.from(document.querySelectorAll(".form-group-2fa-v2 > input")).forEach(function (el) {el.value = "";})');
        // Remember me
        $this->driver->executeScript('let remMe = document.getElementById("RememberBrowser"); if (!remMe.checked) remMe.click();');

        $input->click();
        $input->sendKeys($code);
        $this->saveResponse();
        $btn->click();

        $this->waitForElement(WebDriverBy::xpath('//form/div[@class = "validation-summary-errors"]/ul
                                                            | //a[contains(@href, "SignOut")]
                                                            | //input[@value = "Sign Out"]/@value'), 15);
        $this->saveResponse();
        $message = $this->http->FindNodes('//form/div[@class = "validation-summary-errors"]/ul/li/text()');

        if ($message) {
            $message = implode(' ', $message);
            $this->logger->error($message);

            if (str_contains($message, 'The authentication code entered is invalid. Please try again.')) {
                $this->AskQuestion($question, 'The authentication code entered is invalid. Please try again.', 'Question');
            }

            return false;
        }

        return $this->loginSuccessful();
    }

    protected function parseReCaptcha($isV3 = false)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/var sitekey\s?=\s?'(.+?)';/");
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

        if ($isV3) {
            $parameters += [
                "version"   => "v3",
                "action"    => "/Membership/SignIn",
                "min_score" => 0.9,
            ];
        }

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        $question = $this->http->FindSingleNode('//h1[contains(., "An email has been sent. Enter your")] | //p[contains(text(), "A code has been sent to your email")]');

        if (!$question || !$this->http->ParseForm(null, '//form[contains(@action, "/Membership/VerifyCode")]')) {
            return false;
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';
        $this->holdSession();

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//a[contains(@href, "SignOut")]')
            || $this->http->FindSingleNode('//input[@value = "Sign Out"]/@value')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Our site is currently unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }
}
