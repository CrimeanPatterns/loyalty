<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerChickfil extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const REWARDS_PAGE_URL = 'https://www.chick-fil-a.com/myprofile/points';

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerChickfilSelenium.php";

        return new TAccountCheckerChickfilSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //$this->http->setRandomUserAgent();
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip');
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//a[contains(@href, '/Account/Logout')]")) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Please enter a valid email address.
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address.', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;
//        $this->http->setMaxRedirects(1);
        /*
        $this->http->SetProxy($this->proxyDOP());
        */

        $this->http->GetURL('https://www.chick-fil-a.com/Assets/CFACOM/css/img/favicon.ico');
//        if ($loginForm = $this->http->FindPreg('#form action="(https:\/\/www.chick-fil-a.com\/identity\/externallogin\?authenticationType=Crn[^"]+)#')) {
//            $this->http->PostURL($loginForm, []);
//        }
//        if (isset($this->http->Response['headers']['location'])) {
//            $location = $this->http->Response['headers']['location'];
//            $this->http->NormalizeURL($location);
//            $this->http->setMaxRedirects(5);
//            $this->http->PostURL($location, []);
//        }
//        $this->http->RetryCount = 2;
        if ($this->http->Response['code'] != 200) {
            // Network error 56 - Received HTTP code 407 from proxy after CONNECT
            if (strstr($this->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT')) {
                throw new CheckRetryNeededException(4);
            }

            return $this->checkErrors();
        }
        /*
        $this->http->GetURL('https://www.chick-fil-a.com/MyProfile/Points');
        if ($loginForm = $this->http->FindPreg('#form action="(\/identity\/externallogin\?authenticationType=Crn[^"]+)#')) {
            sleep(2);
            $this->http->NormalizeURL($loginForm);
            $this->http->PostURL($loginForm, []);
        }
        if (!$this->http->ParseForm('login-form')) {
            return $this->checkErrors();
        }
        */
        $this->selenium();
        $this->http->SetInputValue('pf.username', $this->AccountFields["Login"]);
        $this->http->SetInputValue('pf.pass', $this->AccountFields["Pass"]);
        $this->http->SetInputValue('pf.rememberUsername', 'on');
        $this->http->SetInputValue('pf.ok', '');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        /*
         * site blocked access
         */
        // Not Acceptable
        if ($this->http->FindSingleNode('//p[contains(text(), "An appropriate representation of the requested resource / could not be found on this server.")]')) {
            $this->DebugInfo = "request has been blocked";

            throw new CheckRetryNeededException();
        }

        return false;
    }

    public function Login()
    {
        /*
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->ParseForm(null, "//form[contains(@action, 'signincrn')]")) {
            $this->http->PostForm();
        }
        */
        // We're having one of those days. Our site is experiencing some technical issues, but we're working hard to get it fixed. Check back soon.
        if (
        $message = $this->http->FindSingleNode('
                //p[contains(text(), "We\'re having one of those days. Our site is experiencing some technical issues, but we\'re working hard to get it fixed. Check back soon.")]
        ')) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->http->FindSingleNode("//a[contains(@href, '/Account/Logout')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(text(), 't recognize the username or password you entered. Please try again.')]")) {
            throw new CheckRetryNeededException(2, 1, $message, ACCOUNT_INVALID_PASSWORD);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // site blocked access?
        if (
            $message = $this->http->FindSingleNode('
                //div[@class = "err" and contains(text(), "You entered an incorrect email address or password. You may reset your password.")]
                | //p[contains(text(), "An appropriate representation of the requested resource /as/") and contains(text(), "/resume/as/authorization.ping could not be found on this server.")]
            ')
        ) {

            if (
                $this->attempt == 3
                && strstr($message, 'You entered an incorrect email address or password. You may reset your password.')) {
                throw new CheckException("You entered an incorrect email address or password. You may reset your password.", ACCOUNT_INVALID_PASSWORD);
            }

            throw new CheckRetryNeededException(4, 1);
        }
        // This site can’t be reached
        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")) {
            $this->DebugInfo = "This site can’t be reached";
            $this->logger->error(">>> This site can’t be reached");

            throw new CheckRetryNeededException(4, 1);
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')

        if ($this->parseQuestion()) {
            return false;
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "VERIFY YOUR EMAIL")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode('//h1[contains(text(), "VERIFY YOUR DEVICE")]')) {
            return false;
        }
        // Please verify your device by clicking on Verify Device link sent to your email
        $question = "Please copy-paste a verification link which was sent to your email to continue the authentication process."; /*review*/

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))

        $this->http->GetURL($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        $this->sendNotification("chickfil - link was entered // RR");

//        // if wrong/old link was entered
//        if ($this->http->FindSingleNode("//form[@id = 'login']") || $this->http->Response['code'] == 0) {
//            throw new CheckException("Something went wrong, perhaps you entered incorrect or expired link. Please try update your account one more time.", ACCOUNT_PROVIDER_ERROR);
//        }/*review*/

        return true;
    }

    public function Parse()
    {
        if ($this->http->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }
        // Balance - AVAILABLE POINTS
        if (!$this->SetBalance($this->http->FindSingleNode('//span[contains(text(), "Rewards balance:")]', null, true, "/:\s*(.+)\s+pts/"))) {
            $this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Points last updated")]/following-sibling::h1'));
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//div[@class='cp-nav__details']//h4")));
        // CHICK-FIL-A ONE MEMBER
        $this->SetProperty('Status', $this->http->FindSingleNode('//div[contains(@class, "member-tier")]/p[2]'));
        // Your Chick-fil-A One™ red status is valid through ...
        $this->SetProperty('StatusExpiration', $this->http->FindSingleNode("//div[contains(@class, 'status-until')]/p[2]", null, false, '/([^\.]+)/'));
        // Lifetime points earned
        $this->SetProperty('TotalPointsEarned', $this->http->FindSingleNode("//h5[contains(text(),'Lifetime points earned')]/following-sibling::p"));
        // Earn ... to reach ... Status.
        $this->SetProperty('PointsNextLevel', $this->http->FindSingleNode('//div[contains(@class, "progress-bar-anim")]/p', null, true, "/Earn\s*(\d+)/ims"));
        // MEMBERSHIP #
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode("//h5[contains(text(),'Membership #')]/following-sibling::p"));
        // MEMBER SINCE
        $this->SetProperty('MemberSince', $this->http->FindSingleNode("//h5[contains(text(),'Member Since')]/following-sibling::p"));

        $this->http->GetURL('https://www.chick-fil-a.com/myprofile/myrewards');

        if ($this->http->FindSingleNode("//h5[contains(text(),'You currently do not have any rewards available')]")) {
            $this->logger->notice("Rewards not found");

            return;
        }
        $rewards = $this->http->XPath->query("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')]");
        $this->logger->debug("Total {$rewards->length} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode(".//div[@class = 'reward-details']/h5", $reward);
            $exp = $this->http->FindSingleNode(".//p[contains(text(), 'Valid through')]", $reward, true, "/Valid\s*through\s*(.+)/");
            $this->logger->debug("{$displayName} / Exp date: {$exp}");
            $exp = strtotime($exp, false);
            $this->AddSubAccount([
                'Code'           => 'chickfil' . str_replace(' ', '', $displayName) . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }// foreach ($rewards as $reward)
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->http->setUserAgent($this->http->userAgent);
            $resolutions = [
                [1152, 864],
                //                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                //[1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $this->logger->debug("resolution: " . implode("x", $resolution));
            $selenium->setScreenResolution($resolution);
            $selenium->keepCookies(false);
            $selenium->useChromium();

            $selenium->usePacFile(false);

            if ($this->attempt == 0) {
                $selenium->disableImages();
                /*
                    $selenium->useCache();
                */
            }

            $selenium->http->saveScreenshots = true;
            $selenium->http->removeCookies();
            $selenium->http->start();
            $selenium->Start();

//            $allCookies = array_merge($this->http->GetCookies(".chick-fil-a.com"), $this->http->GetCookies(".chick-fil-a.com", "/", true));
//            $allCookies = array_merge($allCookies, $this->http->GetCookies("manage.my.chick-fil-a.com"), $this->http->GetCookies("manage.my.chick-fil-a.com", "/", true));
//            $selenium->http->driver->browserCommunicator->setCookies($allCookies);

            $selenium->http->GetURL("https://www.chick-fil-a.com");

            $loginForm = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 5);

            if ($loginForm) {
//                $loginForm->click();
                $selenium->driver->executeScript('$(\'button:contains("Sign In")\').click();');
            }

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "pf.username"]'), 10);
            $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "pf.pass"]'), 0);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in with Chick")]'), 0);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$loginInput || !$passInput || !$btn) {
                return $this->checkErrors();
            }
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = 100000;
            $mover->steps = 50;
            $mover->moveToElement($loginInput);
            $loginInput->click();
            $mover->click();
            $loginInput->clear();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);

//                $loginInput->click();
//                $loginInput->clear();
//                $loginInput->sendKeys($this->AccountFields['Login']);
//                $passInput->click();
//                $passInput->clear();
//                $passInput->sendKeys($this->AccountFields['Pass']);

            $this->logger->debug("entering password...");
            $mover->moveToElement($passInput);
            $passInput->click();
            $mover->click();
            $passInput->clear();
            $mover->sendKeys($passInput, $this->AccountFields['Pass'], 10);
            $selenium->driver->executeScript('$(\'#remember-username\').prop(\'checked\', true);');

            $this->logger->debug("click 'Sign In'");
            $selenium->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)');
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign in with Chick")]'), 0);
            $btn->click();
//            $selenium->driver->executeScript("$('button[name = \"pf.ok\"]').click();");
//            $selenium->driver->executeScript("$('form#login-form').submit();");

            $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/Account/Logout")] | //div[@class = "err"] | //h1[contains(text(), "VERIFY YOUR DEVICE")]'), 10);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            /*
            if (
                $this->http->FindSingleNode('//h1[contains(text(), "VERIFY YOUR DEVICE")]')
                && ($btn = $selenium->waitForElement(WebDriverBy::xpath('//a[@id = "verify"]'), 0))
            ) {
                $btn->click();
                $selenium->waitForElement(WebDriverBy::xpath('//a[contains(@href, "/Account/Logout")] | //div[@class = "err"]'), 10);
                // save page to logs
                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $this->http->SaveResponse();
            }
            */

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo
        }

        return $result;
    }
}
