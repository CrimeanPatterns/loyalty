<?php

use AwardWallet\Common\Parsing\Html;

class TAccountCheckerCartwheel extends TAccountChecker
{
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.target.com/circle/dashboard';

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerCartwheelSelenium.php";

        return new TAccountCheckerCartwheelSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
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
        /*
                // Json
                $this->selenium();
                $this->http->RetryCount = 0;
                $clientKey = $this->http->FindSingleNode('//script[contains(.,"loyaltyClientKey")]', null, true, '/{\\\\"loyaltyClientKey\\\\":\\\\"(.+?)\\\\",/');
                $apiKey = $this->http->FindSingleNode('//script[contains(.,"loyaltyApiKey")]', null, true, '/,\\\\"loyaltyApiKey\\\\":\\\\"(.+?)\\\\"}/');
                $this->logger->debug("apiKey: " . $apiKey . " | clientKey: " . $clientKey);
                if (!$apiKey || !$clientKey) {
                    return false;
                }
                $headers = [
                    "Accept"          => "text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,* /*;q=0.8",
                    "Accept-Encoding" => "gzip, deflate, br",
                ];
                $this->http->GetURL("https://profile.target.com/TargetGuestWEB/guests/v5/profile?responseGroup=address,profile", $headers, 60);
                $response = $this->http->JsonLog();
        */

        return $this->selenium();
    }

    public function Login()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('
                //span[contains(@id, "--ErrorMessage")]
                | //div[@data-test = "authAlertDisplay"]
            ')
        ) {
            $this->logger->error("[Error]: " . $message);

            if (
                $message == "We can't find your account."
                || $message == "That password is incorrect."
                || $message == "Please enter a valid password"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // may be block
            if (
                $message == "Sorry, something went wrong. Please try again."
            ) {
                throw new CheckRetryNeededException(2, 10/*$message, ACCOUNT_PROVIDER_ERROR*/);
            }

            if (
                $message == "Your account is locked. Please click on forgot password link to reset."
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        /*
                // json
                $response = $this->http->JsonLog(null, 0);
                // Name
                $name = ($response->profile->firstName ?? null).' '.($response->profile->lastName ?? null);
                $this->SetProperty("Name", beautifulName($name));
                // Account since

                $targetCreateDate = $response->profile->targetCreateDate ?? null;
                if ($targetCreateDate) {
                    $this->SetProperty("AccountSince", date('M d, Y', $targetCreateDate));
                }

                        $headers = [
                            "Accept"             => "application/json",
                            "Accept-Encoding"    => "gzip, deflate, br",
                            "x-api-key"          => $apiKey,
                            "loyalty_client_key" => $clientKey,
                            "Referer"            => "https://www.target.com/circle/dashboard",

                        ];
                $this->http->GetURL("https://api.target.com/loyalty_accounts/v2/details", $headers, 60);
                $response = $this->http->JsonLog();
                // Savings
                $this->SetProperty('Savings', $response->available_balance ?? null);
                // Balance -
                $this->SetBalance($response->total_balance);
        */
        // Balance - Available Target Circle Earnings
        $this->SetBalance($this->http->FindSingleNode('
            //h2[contains(normalize-space(),"Target Circle Earnings")]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]/span
            | //h3[contains(normalize-space(),"Available Target Circle Earnings")]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]/span
        ') ?? $this->http->FindPreg("/Available\&nbsp;Target Circle\&nbsp;Earnings\s*<\/h3><sup[^>]+><a[^>]+>[^<]+<\/a><\/sup><\/div><p[^>]+><span[^>]+>([^<]+)/")
        );
        // Target Circle™ Offer Savings
        $this->SetProperty('Savings', $this->http->FindSingleNode('
            //h2[normalize-space() = "Target Circle™ Offer Savings"]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]
            | //h3[normalize-space() = "Lifetime Target Circle offer savings"]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]
            | //h3[contains(text(), "Lifetime") and contains(text(), "savings")]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]
        ') ?? $this->http->FindPreg("/Lifetime&nbsp;Target&nbsp;Circle offer&nbsp;savings<\/h3><p[^>]+>([^<]+)/")
        );
        // Community Support
        $this->SetProperty('Community', $this->http->FindSingleNode('
            //h2[normalize-space() = "Community Support"]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]
            | //h3[normalize-space() = "Community Support"]/following-sibling::p[contains(@class,"BalancesCardTextBalance")]
        ', null, true, '/(.*?) votes/'));

        // Load settings page
        $this->http->GetURL('https://profile.target.com/TargetGuestWEB/guests/v5/profile?responseGroup=address%2Cprofile');
        $json = $this->http->JsonLog(null, 3, true);
        // Name
        $this->SetProperty('Name', beautifulName($json['profile']['firstName'] . ' ' . $json['profile']['lastName']));
        // Member Since Date
        if (isset($json['profile']['targetCreateDate'])) {
            $this->setProperty('MemberSince', date('d M, Y', strtotime($json['profile']['targetCreateDate'])));
        }
        // Days until we celebrate
        $this->setProperty('DaysUntilCelebrate', $json['profile']['daysToBirthday'] ?? null);

        // refs #20990
        $this->logger->info('Gift Cards', ['Header' => 3]);
        $this->http->GetURL('https://profile.target.com/WalletWEB/wallet/v5/giftcards');
        $responseCards = $this->http->JsonLog(null, 3)->cards ?? [];

        foreach ($responseCards as $card) {
            $this->AddSubAccount([
                "Code"               => "GiftCard" . $card->cardId,
                "DisplayName"        => "Gift Cards #{$card->cardNumber}",
                "Balance"            => $card->currentBalanceAmount,
            ]);
        }
    }

    public function ProcessStep($step)
    {
        $this->sendNotification("2fa // RR");

        $data = '{"code":"' . $this->Answers[$this->Question] . '","device_info":{"user_agent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36","language":"en-US","color_depth":"30","device_memory":"8","pixel_ratio":"unknown","hardware_concurrency":"16","resolution":"[1536,960]","available_resolution":"[1536,871]","timezone_offset":"-300","session_storage":"1","local_storage":"1","indexed_db":"1","add_behavior":"unknown","open_database":"1","cpu_class":"unknown","navigator_platform":"MacIntel","do_not_track":"unknown","regular_plugins":"[\"PDF Viewer::Portable Document Format::application/pdf~pdf,text/pdf~pdf\",\"Chrome PDF Viewer::Portable Document Format::application/pdf~pdf,text/pdf~pdf\",\"Chromium PDF Viewer::Portable Document Format::application/pdf~pdf,text/pdf~pdf\",\"Microsoft Edge PDF Viewer::Portable Document Format::application/pdf~pdf,text/pdf~pdf\",\"WebKit built-in PDF::Portable Document Format::application/pdf~pdf,text/pdf~pdf\"]","adblock":"false","has_lied_languages":"false","has_lied_resolution":"false","has_lied_os":"false","has_lied_browser":"false","touch_support":"[0,false,false]","js_fonts":"[\"Andale Mono\",\"Arial\",\"Arial Black\",\"Arial Hebrew\",\"Arial Narrow\",\"Arial Rounded MT Bold\",\"Arial Unicode MS\",\"Comic Sans MS\",\"Courier\",\"Courier New\",\"Geneva\",\"Georgia\",\"Helvetica\",\"Helvetica Neue\",\"Impact\",\"LUCIDA GRANDE\",\"Microsoft Sans Serif\",\"Monaco\",\"Palatino\",\"Tahoma\",\"Times\",\"Times New Roman\",\"Trebuchet MS\",\"Verdana\",\"Wingdings\",\"Wingdings 2\",\"Wingdings 3\"]","navigator_vendor":"Google Inc.","navigator_app_name":"Netscape","navigator_app_code_name":"Mozilla","navigator_app_version":"5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/94.0.4606.81 Safari/537.36","navigator_languages":"[\"en-US\",\"en\",\"ru\"]","navigator_cookies_enabled":"true","navigator_java_enabled":"false","visitor_id":"017C03008A370100FEC1EDD0239350B1","tealeaf_id":"UfKJgiT0LBQV4FMY52VBXULGcEgV13gG","webgl_vendor":"Intel Inc.~Intel(R) UHD Graphics 630","browser_name":"Unknown","browser_version":"Unknown","cpu_architecture":"Unknown","device_vendor":"Unknown","device_model":"Unknown","device_type":"Unknown","engine_name":"Unknown","engine_version":"Unknown","os_name":"Unknown","os_version":"Unknown"}}';
        unset($this->Answers[$this->Question]);
        $this->http->PostURL("https://gsp.target.com/gsp/authentications/v1/secure_code_verifications", $data);
        $response = $this->http->JsonLog();
        $error = $response->errors[0]->errorMessage ?? null;

        if ($error == "That code is invalid.") {
            $this->logger->error("answer was wrong");
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);

        return true;
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindNodes('//div[contains(@class,"AccountLinkContainer")]')
            && $this->http->FindSingleNode('//div[contains(@class,"BalancesContainer")]')
        ) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")) {
            $this->DebugInfo = "This site can’t be reached";
            $this->logger->error(">>> This site can’t be reached");

            throw new CheckRetryNeededException(3, 0);
        }

        return false;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);

            $selenium->http->setUserAgent(\HttpBrowser::PUBLIC_USER_AGENT);

            $selenium->disableImages();
            $selenium->useCache();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();
            /*
            $selenium->http->GetURL("https://www.target.com/circle");

            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test="sign-in"]'), 10);
            $this->saveToLogs($selenium);
            if (!$signIn) {
                return $this->checkErrors();
            }
            $signIn->click();
            */
            $selenium->http->GetURL("https://www.target.com/circle/dashboard");

            $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test="sign-in"] | //input[@id = "username"]'), 10);
            $this->saveToLogs($selenium);
            $timeout = 0;

            if ($signIn = $selenium->waitForElement(WebDriverBy::xpath('//button[@data-test="sign-in"]'), 0)) {
                $signIn->click();
                $timeout = 10;
            }

            $username = $selenium->waitForElement(WebDriverBy::id('username'), $timeout);
            $password = $selenium->waitForElement(WebDriverBy::id('password'), 0);
            $button = $selenium->waitForElement(WebDriverBy::id('login'), 0);
            $this->saveToLogs($selenium);

            if (!$username || !$password || !$button) {
                return $this->checkErrors();
            }

            $selenium->driver->executeScript("document.querySelector('input[name = \"keepMeSignedIn\"]').checked = true;");

            $username->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $this->saveToLogs($selenium);
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath('
                //div[contains(@class,"BalancesContainer")]
                | //span[contains(@id, "--ErrorMessage")]
                | //div[@data-test = "authAlertDisplay"]
                | //a[contains(text(), "Skip")]
                | //span[contains(text(), "We\'ve sent your code")]
            '), 10);
            $this->saveToLogs($selenium);

            if ($skipLink = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Skip")]'), 0)) {
                $skipLink->click();
                $selenium->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class,"BalancesContainer")]
                    | //span[contains(@id, "--ErrorMessage")]
                    | //div[@data-test = "authAlertDisplay"]
                '), 10);
                $this->saveToLogs($selenium);
            }

            if ($selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class,"BalancesContainer")]'), 0)) {
                $this->saveToLogs($selenium);
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 1);
            }
        }

        return true;
    }

    private function saveToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        $selenium->http->SaveResponse();
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//span[contains(text(), "We\'ve sent your code")]');
        $email = $this->http->FindSingleNode('//span[contains(text(), "We\'ve sent your code")]/following-sibling::span[1]');

        if (!$question || !$email) {
            return false;
        }

        if ($this->getWaitForOtc()) {
            $this->sendNotification("mailbox was fount // RR");
        }

        $question .= ' ' . $email;

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
