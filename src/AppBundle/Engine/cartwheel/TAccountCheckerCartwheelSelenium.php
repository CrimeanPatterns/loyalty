<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCartwheelSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private const REWARDS_PAGE_URL = 'https://www.target.com/circle/dashboard';

    private const XPATH_LOGOUT = '//div[p[contains(normalize-space(), "Available Target Circle Rewards")]]/preceding-sibling::div[1]/span';
    private const XPATH_ERROR = '
        (//span[contains(@id, "--ErrorMessage")])[1]
        | //div[@data-test = "authAlertDisplay"]
    ';
    private const XPATH_RESULT =
        self::XPATH_LOGOUT
        . ' | ' . self::XPATH_ERROR
    ;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);

        $this->UseSelenium();
        $this->setProxyBrightData();
        $this->useFirefoxPlaywright();

//        $this->disableImages();
//        $this->useCache();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        try {
            $this->http->RetryCount = 0;
            $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
            $this->http->RetryCount = 2;
        } catch (ScriptTimeoutException | TimeOutException $e) {
            $this->logger->error("TimeOut exception: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
            $this->saveResponse();
        } catch (UnexpectedAlertOpenException $e) {
            $this->logger->error("UnexpectedAlertOpenException: " . $e->getMessage());

            try {
                $error = $this->driver->switchTo()->alert()->getText();
                $this->logger->debug("alert -> {$error}");
                $this->driver->switchTo()->alert()->accept();
                $this->logger->debug("alert, accept");
            } catch (NoAlertOpenException $e) {
                $this->logger->debug("no alert, skip");
            }
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.target.com/circle/dashboard");

        $this->waitForElement(WebDriverBy::xpath('//button[@data-test="sign-in"] | //input[@id = "username"] | //button[contains(text(), "Sign in")]'), 15);
        $this->saveResponse();

//        if ($signIn = $this->waitForElement(WebDriverBy::xpath('//button[@data-test="sign-in"]'), 0)) {
//            $signIn->click();
//        } else {
        $this->driver->executeScript("try { document.querySelector('a[data-test=\"@web/AccountLink\"]').click(); } catch (e) {}");
        sleep(3);
        $this->saveResponse();
        $this->driver->executeScript("try { document.querySelector('a[data-test=\"accountNav-signIn\"], div[data-component-title=\"Circle Dashboard Sign In\"] button:last-child').click(); } catch (e) {}");
//        }

        $password = $this->waitForElement(WebDriverBy::id('password'), 10);
        $this->saveResponse();

        if (!$password && !$this->loginSuccessful()) {
            $this->http->GetURL("https://gsp.target.com/gsp/authentications/v1/auth_codes?client_id=ecom-web-1.0.0&redirect_uri=https%3A%2F%2Fwww.target.com%2Fl%2Ftarget-circle%2F-%2FN-pzno9&acr=create_session_signin&state=1718961631204&assurance_level=M&trident=true");

            if ($this->waitForElement(WebDriverBy::xpath('//pre[contains(text(), "Access Denied")]'), 5)) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(2, 0);
            }// if ($this->waitForElement(WebDriverBy::xpath('//pre[contains(text(), "Access Denied")]'), 5))
            $this->saveResponse();
        }// if (!$password)

        $username = $this->waitForElement(WebDriverBy::id('username'), 0);
        $button = $this->waitForElement(WebDriverBy::id('login'), 0);

        if (!$password || !$button) {
            if ($this->loginSuccessful()) {
                return true;
            }

            return $this->checkErrors();
        }

        $this->driver->executeScript("document.querySelector('input[name = \"keepMeSignedIn\"]').checked = true;");

        if ($username) {
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($username, $this->AccountFields['Login'], 5);
//            $username->sendKeys($this->AccountFields['Login']);
        }
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();
        $button->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath(
            self::XPATH_RESULT . '
            | //a[contains(text(), "Skip")]
            | //span[contains(text(), "Hi, ")]
            | //span[contains(text(), "We\'ve sent your code") or contains(text(), "We’ve sent your code")]
            | //button[contains(text(), "Maybe later")]
        '), 10);
        $this->saveResponse();

        $this->skipDataUpdate();

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (
            in_array($this->http->currentUrl(), [
                'https://www.target.com/',
                'https://www.target.com/circle',
            ])
            && $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Hi, ")]'), 0)
        ) {
            $this->http->GetURL("https://www.target.com/circle/dashboard");
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_RESULT), 10);
        }

        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->parseQuestion()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode(self::XPATH_ERROR)) {
            $this->logger->error("[Error]: " . $message);

            if (
                $message == "We can't find your account."
                || $message == "That password is incorrect."
                || $message == "Please enter a valid password"
                || $message == "Please enter a valid email or phone number"
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // it is block may be
            if (
                $message == "Sorry, something went wrong. Please try again."
                || $message == "Sorry, something went wrong. Please try again"
                || $message == "Something went wrong. Please try again later."
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

        if ($this->waitForElement(WebDriverBy::xpath('//*[contains(., "By joining Target Circle, you are agreeing to the Target Circle")]'), 0)) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Your account is locked due to unusual activity.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }

        $this->saveResponse();
        // if site too long loading
        if ($this->loginSuccessful()) {
            return true;
        }

        // provider bug fix wokaround
        if ($this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Sign in")]'), 0)) {
            throw new CheckRetryNeededException(2, 0);
        }

        return $this->checkErrors();
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = clone $this->http;
//        $this->browser = new HttpBrowser("none", new CurlDriver());
//        $this->http->brotherBrowser($this->browser);
//        $cookies = $this->driver->manage()->getCookies();
//
//        foreach ($cookies as $cookie) {
//            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
//        }
//
//        $this->browser->LogHeaders = true;
//        $this->browser->SetProxy($this->http->GetProxy());
//        $this->browser->GetURL($this->http->currentUrl());
    }

    public function Parse()
    {
//        $this->parseWithCurl();
        $this->saveResponse();

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
        // Expiration date
        if ($exp = strtotime($this->http->FindSingleNode('//p[@data-test="legal-text-has-earnings"]', null, true, '/Expires (.+) if Target Circle™ Earnings are not earned or redeemed/') ?? '')) {
            $this->SetExpirationDate($exp);
        }
        // Balance - Available Target Circle Earnings
        $this->SetBalance($this->http->FindSingleNode(self::XPATH_LOGOUT));
        // You've saved ... this year
        $this->SetProperty('Savings', $this->http->FindSingleNode('//div[p[contains(normalize-space(), "this year")]]/preceding-sibling::div[1]/span'));
        // Community Support
        $this->SetProperty('Community', $this->http->FindSingleNode('//div[p[contains(normalize-space(), "Community support votes")]]/preceding-sibling::div[1]/span'));

        // Load settings page
        try {
            $this->http->GetURL('https://api.target.com/guest_profile_details/v1/profile_details/profiles?fields=affiliation%2Caddress%2Cloyalty%2Cpaid');
            $script =
                'fetch("https://api.target.com/guest_profile_details/v1/profile_details/profiles?fields=affiliation%2Caddress%2Cloyalty%2Cpaid", {
                    "credentials": "include",
                    "headers": {
                                "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:129.0) Gecko/20100101 Firefox/129.0",
                        "Accept": "application/json",
                        "Accept-Language": "en-US,en;q=0.5",
                        "x-api-key": "a770bb029cbcb909b2d00ef9a5291f7189a4ef19",
                        "Sec-Fetch-Dest": "empty",
                        "Sec-Fetch-Mode": "cors",
                        "Sec-Fetch-Site": "same-site",
                        "Priority": "u=4",
                        "Pragma": "no-cache",
                        "Cache-Control": "no-cache"
                    },
                    "referrer": "https://www.target.com/account/settings",
                    "method": "GET",
                    "mode": "cors"
                }).then((response) => {
                response
                .clone()
                .json()
                .then(body => localStorage.setItem("profile_details", JSON.stringify(body)));
            });';
            $this->logger->debug(var_export($script, true), ["pre" => true]);
            $this->driver->executeScript($script);

            $this->logger->debug("request sent");
            sleep(2);
            $this->logger->debug("get data");
            $profileDetails = $this->driver->executeScript("return localStorage.getItem('profile_details');");
            $this->logger->debug(var_export($profileDetails, true), ["pre" => true]);
        } catch (UnknownServerException | Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("UnknownServerException exception: " . $e->getMessage());
        }
        $json = $this->http->JsonLog($profileDetails, 3, true);
        // Name
        $this->SetProperty('Name', beautifulName($json['profile']['first_name'] . ' ' . ArrayVal($json['profile'], 'last_name')));
        // Days until we celebrate
        $this->setProperty('DaysUntilCelebrate', $json['profile']['days_to_birthday'] ?? null);
        // Member since
        $this->setProperty('MemberSince', $json['profile']['created_date']);

        // refs #20990, #23407
        $this->logger->info('Gift Cards', ['Header' => 3]);
        $this->http->GetURL('https://www.target.com/account/giftcards');
        usleep(400);
        $this->driver->executeScript(/** @lang JavaScript */ "
            localStorage.setItem('giftcardss', '');
            const {fetch: origFetch} = window;
            window.fetch = async (...args) => {
                console.log('fetch called with args:', args);
                const response = await origFetch(...args);
                response
                    .clone()
                    .json()
                    .then(data => {
                        console.log('intercepted response data:', data)
                        if (data.giftcards) {
                            console.log('success')
                            localStorage.setItem('giftcardss', JSON.stringify(data));
                        }
                    })
                    .catch(err => console.error(err));
            };
        ");

        $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Scan or show at register")]'), 5);
        $this->saveResponse();
        sleep(3);
        $responseCards = $this->http->JsonLog($this->driver->executeScript('return localStorage.getItem("giftcardss")'));
        $this->saveResponse();

        foreach ($responseCards->giftcards ?? [] as $card) {
            if ($card->current_balance > 0) {
                $this->AddSubAccount([
                    "Code"               => "GiftCard" . $card->giftcard_id,
                    "DisplayName"        => "Gift Cards #{$card->giftcard_number}",
                    "Balance"            => $card->current_balance,
                ]);
            }
        }
    }

    public function ProcessStep($step)
    {
        $answerInput = $this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter your code"]'), 0);

        if (!$answerInput) {
            return false;
        }

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $answerInput->sendKeys($answer);

        $btn = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'verify' and not(@disabled)]"), 5);

        if (!$btn) {
            return false;
        }

        $btn->click();

        $error = $this->waitForElement(WebDriverBy::xpath(self::XPATH_RESULT), 5);
        $this->saveResponse();

        $this->skipDataUpdate();

        if ($error && $error->getText() == "That code is invalid.") {
            $this->logger->error("answer was wrong");
            $this->AskQuestion($this->Question, $error, "Question");

            return false;
        } elseif (
            $error
            && (
                strstr($error->getText(), 'Sorry, there have been too many unsuccessful verification attempts.')
                || strstr($error->getText(), 'Sorry, something went wrong.')
            )
        ) {
            throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL(self::REWARDS_PAGE_URL, [], 20);
        $this->waitForElement(WebDriverBy::xpath(self::XPATH_RESULT), 10);

        return true;
    }

    private function skipDataUpdate()
    {
        $this->logger->notice(__METHOD__);

        if ($skipLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Skip")] | //button[contains(text(), "Maybe later")]'), 0)) {
            $skipLink->click();
            $this->waitForElement(WebDriverBy::xpath(self::XPATH_RESULT), 10);
            $this->saveResponse();
        }
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode(self::XPATH_LOGOUT)) {
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

    private function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $questionXpath = '//span[contains(text(), "We\'ve sent your code") or contains(text(), "We’ve sent your code")]';
        $emailCodeOption = $this->waitForElement(WebDriverBy::xpath('//*[@id = "emailOptionSelect"]'), 0);

        if ($emailCodeOption) {
            $sendCodeBtn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "continue"]'), 0);

            if (!$sendCodeBtn) {
                return $this->checkErrors();
            }

            $emailCodeOption->click();
            $sendCodeBtn->click();
            $this->waitForElement(WebDriverBy::xpath($questionXpath), 7);
            $this->saveResponse();
        }

        $question = $this->http->FindSingleNode($questionXpath);
        $email = $this->http->FindSingleNode("$questionXpath/following-sibling::span[1]");

        if (!$question || !$email || !$this->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter your code"]'), 0)) {
            return false;
        }

        $question .= ' ' . $email;

        $this->holdSession();

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }
}
