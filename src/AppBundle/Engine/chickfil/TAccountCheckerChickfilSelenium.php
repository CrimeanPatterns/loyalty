<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerChickfilSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const REWARDS_PAGE_URL = 'https://www.chick-fil-a.com/myprofile/points';
    /**
     * @var HttpBrowser
     */
    public $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();
        $this->http->saveScreenshots = true;
        $this->setProxyGoProxies();
        $this->useChromePuppeteer();

        $this->usePacFile(false);

        if ($this->attempt == 0) {
            $this->disableImages();
        } else {
            $this->keepCookies(false);
        }

        $resolutions = [
            [800, 600], // not working with chrome 84
            [1152, 864],
            [1280, 720], // not working with chrome 84
            [1280, 768], // not working with chrome 84
            [1440, 900],
            [2560, 1440],
        ];

        if (!isset($this->State['Resolution']) || $this->attempt > 1) {
            $this->logger->notice("set new resolution");
            $resolution = $resolutions[array_rand($resolutions)];
            $this->State['Resolution'] = $resolution;
        } else {
            $this->logger->notice("get resolution from State");
            $resolution = $this->State['Resolution'];
            $this->logger->notice("restored resolution: " . join('x', $resolution));
        }
        $this->setScreenResolution($resolution);
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

        if (!$this->waitForElement(WebDriverBy::xpath('//input[@name = "pf.username"]'), 0)) {
            $this->http->GetURL("https://www.chick-fil-a.com");

            $loginForm = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Sign In")]'), 5);

            if ($loginForm) {
//            $loginForm->click();
                $this->driver->executeScript('$(\'button:contains("Sign In")\').click();');
            }
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "pf.username"]'), 10);
        $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@name = "pf.pass"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="pf.ok"]'), 0);
        // save page to logs
        $this->saveResponse();

        if (!$loginInput || !$passInput || !$btn) {
            if ($this->http->FindSingleNode('//h1[contains(text(), "403 Forbidden")]')) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        $mover = new MouseMover($this->driver);
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
        $this->driver->executeScript('$(\'#remember-username\').prop(\'checked\', true);');

        $this->logger->debug("click 'Sign In'");
        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="pf.ok"]'), 0);
        $btn->click();
//            $this->driver->executeScript("$('button[name = \"pf.ok\"]').click();");
//            $this->driver->executeScript("$('form#login-form').submit();");

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

        if ($this->http->FindSingleNode('//span[contains(text(), "An error occured")]')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('
            //a[contains(@href, "/Account/Logout")]
            | //div[@class = "err"]
            | //h1[contains(text(), "VERIFY YOUR DEVICE")]
            | //h1[contains(text(), "We don\'t recognize this device")]
            | //div[contains(text(), "You may have entered an incorrect email address or password, so please try again.")]
        '), 10);
        $this->saveResponse();

        // We're having one of those days. Our site is experiencing some technical issues, but we're working hard to get it fixed. Check back soon.
        if (
            $this->http->FindSingleNode('
                //p[contains(text(), "We\'re having one of those days. Our site is experiencing some technical issues, but we\'re working hard to get it fixed. Check back soon.")]
        ')) {
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }

        if ($this->http->FindSingleNode("//a[contains(@href, '/Account/Logout')]")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[
                contains(text(), 't recognize the username or password you entered. Please try again.')
                or contains(text(), 're having trouble logging you in. You may have entered an incorrect email address or password')
                or contains(text(), 'You may have entered an incorrect email address or password, so please try again.')
            ]")
        ) {
            throw new CheckRetryNeededException(2, 1, $message, ACCOUNT_INVALID_PASSWORD);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // site blocked access?
        if ($message = $this->http->FindSingleNode('
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

        if ($this->http->FindSingleNode('//h1[
                contains(text(), "VERIFY YOUR EMAIL")
                or contains(text(), "We\'ve sent you an email to verify your address")
            ]')
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if (!$this->http->FindSingleNode('
            //h1[contains(text(), "VERIFY YOUR DEVICE")]
            | //h1[contains(text(), "We don\'t recognize this device")]
        ')) {
            return false;
        }

        $this->holdSession();
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
        $this->http->GetURL(self::REWARDS_PAGE_URL);
        unset($this->Answers[$this->Question]);

//        // if wrong/old link was entered
//        if ($this->http->FindSingleNode("//form[@id = 'login']") || $this->http->Response['code'] == 0) {
//            throw new CheckException("Something went wrong, perhaps you entered incorrect or expired link. Please try update your account one more time.", ACCOUNT_PROVIDER_ERROR);
//        }/*review*/

        return true;
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

        $this->curl = true;

        /*
        $this->browser->setProxyParams($this->http->getProxyParams());
        */
        $this->setProxyGoProxies();
        $this->browser->GetURL($this->http->currentUrl());
    }

    public function Parse()
    {
        if ($this->http->FindSingleNode('//div[@id = "titleSubText" and (contains(text(), "We\'ve made some updates to our Privacy Policy. Learn more about the updates to the Privacy Policy and how we") or contains(text(), "We\'ve made some updates to our Chick-fil-A Terms"))]')) {
            $this->throwAcceptTermsMessageException();
        }

        // use curl
        $this->parseWithCurl();

        if ($this->browser->currentUrl() != self::REWARDS_PAGE_URL) {
            $this->browser->GetURL(self::REWARDS_PAGE_URL);
        }

        // Balance - AVAILABLE POINTS
        if (!$this->SetBalance($this->browser->FindSingleNode('//span[contains(text(), "Rewards balance:")]', null, true, "/:\s*(.+)\s+pts/"))) {
            $this->SetBalance($this->browser->FindSingleNode('//p[contains(text(), "Points last updated")]/following-sibling::h1'));
        }
        // Name
        $this->SetProperty('Name', beautifulName($this->browser->FindSingleNode("//div[@class='cp-nav__details']//h4")));
        // CHICK-FIL-A ONE MEMBER
        $this->SetProperty('Status', $this->browser->FindSingleNode('//div[contains(@class, "member-tier")]/p[2] | //div[contains(@class, "--active")]//h5[contains(@class, "member-category")]'));
        // Your Chick-fil-A One™ red status is valid through ...
        $this->SetProperty('StatusExpiration',
            $this->browser->FindSingleNode("//div[contains(@class, 'status-until')]/p[2]", null, false, '/([^\.]+)/')
            ?? $this->browser->FindSingleNode('//div[contains(@class, "--active")]//span[contains(text(), "Status valid until")]', null, false, '/until\s*([^\.]+)/')
        );
        // Lifetime points earned
        $this->SetProperty('TotalPointsEarned', $this->browser->FindSingleNode('//h5[contains(text(), "Lifetime points earned")]/following-sibling::p | //h5[contains(text(), "Lifetime points earned")]/following-sibling::span'));
        // Earn ... to reach ... Status.
        $this->SetProperty('PointsNextLevel', $this->browser->FindSingleNode('//div[contains(@class, "progress-bar-anim")]/p | //span[contains(text(), "more points by the end of the year")]', null, true, "/Earn\s*(\d+)/ims"));
        // MEMBERSHIP #
        $this->SetProperty('AccountNumber', $this->browser->FindSingleNode("//h5[contains(text(),'Membership #')]/following-sibling::*[self::p or self::span]"));
        // MEMBER SINCE
        $this->SetProperty('MemberSince', $this->browser->FindSingleNode("//h5[contains(text(),'Member Since')]/following-sibling::p"));

        $this->http->GetURL('https://order.chick-fil-a.com/account/pending-orders');
        // provider strange behavior workaround
        $this->waitForElement(WebDriverBy::xpath("//div[contains(text(),'Edit or cancel your upcoming orders if they are outside the 24 hour lead time.')]"), 10);
        $this->saveResponse();

        $this->http->GetURL('https://order.chick-fil-a.com/my-rewards');
        $this->waitForElement(WebDriverBy::xpath("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward'] | //h5[contains(text(),'You currently do not have any rewards available')]"), 10);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//h5[contains(text(),'You currently do not have any rewards available')]")) {
            $this->logger->notice("Rewards not found");

            return;
        }

        $rewards = $this->http->XPath->query("//div[@id = 'my-rewards-set']/div[contains(@class, 'reward-card')] | //li[@data-cy='Reward']");
        $this->logger->debug("Total {$rewards->length} rewards were found");
        $this->SetProperty("CombineSubAccounts", false);

        foreach ($rewards as $reward) {
            $displayName = $this->http->FindSingleNode(".//div[div/div/div[@class = 'reward-details'] and position() = 1]//div[@class = 'reward-details']/h5 | //h4[@data-cy = 'RewardName']", $reward);
            $exp = $this->http->FindSingleNode(".//*[self::p or self::div][contains(text(), 'Valid through')]", $reward, true, "/Valid\s*through\s*(.+)/");
            $this->logger->debug("{$displayName} / Exp date: {$exp}");
            $exp = strtotime($exp, false);
            $this->AddSubAccount([
                'Code'           => 'chickfil' . str_replace([' ', '®', '™', ','], '', $displayName) . $exp,
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => $exp,
            ]);
        }// foreach ($rewards as $reward)
    }
}
