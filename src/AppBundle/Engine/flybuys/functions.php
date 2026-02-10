<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFlybuys extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var HttpBrowser
     */
    public $browser;

    public $regionOptions = [
        "Australia"    => "Australia",
        "New Zealand"  => "New Zealand",
    ];

    private $timeout = 10;
    private $questionUrl;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;
    }

    public static function isClientCheck(AwardWallet\MainBundle\Entity\Account $account, $isMobile)
    {
        if ($account->getLogin2() == 'New Zealand') {
            return false;
        }

        return null;
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->KeepState = true;

        if ($this->AccountFields['Login2'] == 'Australia') {
            $this->UseSelenium();
            $this->http->saveScreenshots = true;

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
                [1920, 1080],
            ];

            if (!isset($this->State['Resolution']) || $this->attempt > 1) {
                $this->logger->notice("set new resolution");
                $chosenResolution = $resolutions[array_rand($resolutions)];
                $this->State['Resolution'] = $chosenResolution;
            } else {
                $this->logger->notice("get resolution from State");
                $chosenResolution = $this->State['Resolution'];
                $this->logger->notice("restored resolution: " . join('x', $chosenResolution));
            }
            $this->setScreenResolution($chosenResolution);

//            $this->disableImages();
            $this->useChromePuppeteer();
            $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['macos']];
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;

            $this->usePacFile(false);
            // refs #14848
//            $this->useCache();
        }
    }

    public function IsLoggedIn()
    {
        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                $this->http->RetryCount = 0;
                $this->http->GetURL('https://www.flybuys.co.nz/members/points_details', [], 20);
                $this->http->RetryCount = 2;

                $response = $this->http->JsonLog();

                if (isset($response->points_balance)) {
                    return true;
                }

            break;

            case 'Australia':
            default:
                if (!isset($this->State['cookies'])) {
                    return false;
                }

                $this->http->RetryCount = 0;
                $this->parseWithCurl();
                $this->browser->GetURL('https://www.flybuys.com.au/flybuys-web/api/member/session', [], 20);
                $this->http->RetryCount = 2;

                $response = $this->browser->JsonLog();

                if (isset($response->loggedIn) && $response->loggedIn) {
                    return true;
                }

                break;
        }
        $this->browser = null;

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);

        if (!empty($this->browser)) {
            return;
        }
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        // TODO:
        if (isset($this->State['cookies'])) {
            $tmp = [];

            foreach ($this->State['cookies'] as &$cookie) {
                if (empty($tmp[$cookie['name']])) {
                    $cookie['domain'] = preg_replace('/\.www\./', 'www.', $cookie['domain']);
                    $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                    $tmp[$cookie['name']] = $cookie['value'];
                }
            }
            unset($tmp);
        } else {
            $cookies = $this->driver->manage()->getCookies();
            $this->State['cookies'] = $cookies;

            foreach ($cookies as $cookie) {
                $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        }

        $this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->RetryCount = 0;

        if ($this->http->FindPreg('/^http/', false, $this->http->currentUrl())) {
            $this->browser->GetURL($this->http->currentUrl());
        }
        $this->browser->RetryCount = 2;
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);

        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                $this->http->GetURL("https://www.flybuys.co.nz/sign_in");
                $token = $this->http->FindPreg("/type=\"hidden\" name=\"authenticity_token\" value=\"([^\">]+)/ims");

                if (!$this->http->FindPreg("/action=\"\/authenticate\"/ims") || !isset($token)) {
                    return $this->checkErrors();
                }
                $this->http->FormURL = 'https://www.flybuys.co.nz/authenticate';
                $this->http->SetInputValue("authenticity_token", $token);
                $this->http->SetInputValue("user[username]", $this->AccountFields['Login']);
                $this->http->SetInputValue("user[password]", $this->AccountFields['Pass']);
                $this->http->SetInputValue("remember_me", "1");
                $this->http->SetInputValue("button", "");

                break;

            case 'Australia':
            default:
                unset($this->State['cookies']);
                $this->AccountFields['Login'] = str_replace(' ', '', $this->AccountFields['Login']);
                $this->logger->debug("Login => {$this->AccountFields['Login']}");

                if (strlen($this->AccountFields['Login']) < 16) {
                    throw new CheckException("Invalid card number.", ACCOUNT_INVALID_PASSWORD);
                }

                if (!is_numeric($this->AccountFields['Login'])) {
                    throw new CheckException("Please enter your 16 digit card number", ACCOUNT_INVALID_PASSWORD);
                }

                $links = [
                    'https://www.flybuys.com.au/home',
                    'https://www.flybuys.com.au/collect/',
                    'https://www.flybuys.com.au/rewards',
                    'https://www.flybuys.com.au/about/',
                ];

                try {
                    $this->http->GetURL($links[array_rand($links)]);
                } catch (StaleElementReferenceException | Exception $e) {
                    $this->logger->error("Exception: " . $e->getMessage());

                    if (
                        strstr($e->getMessage(), 'The element reference of')
                        || strstr($e->getMessage(), 'Curl error thrown for http POST to')
                    ) {
                        throw new CheckRetryNeededException(3, 7);
                    }
                }

                try {
                    $this->http->GetURL('https://www.flybuys.com.au/sign-in');
                } catch (UnexpectedJavascriptException $e) {
                    $this->logger->error("UnexpectedJavascriptException exception: " . $e->getMessage());
                }

                sleep(rand(1, 5));
                $elem = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), $this->timeout);
                $this->saveResponse();

                if (!$elem) {
                    // retries
                    if ($this->http->ParseForm("memberSignInForm") || $this->http->FindSingleNode("//h1[contains(text(), 'Access Denied')]")) {
                        throw new CheckRetryNeededException(3, 7);
                    }
                    $this->logger->debug("page reload");
                    $this->http->GetURL('https://www.flybuys.com.au/sign-in');
                    sleep(2);

                    if (!($elem = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), $this->timeout))) {
                        $this->saveResponse();

                        if (
                            $this->http->FindPreg("/<script>window.pfb.loginUrl = \"\/as\/3Q0qr\/resume\/as\/authorization\.ping\";<\/script>/")
                            || $this->http->FindPreg("/iframe src=\"https:\/\/id\.flybuys\.com.au\/[^\"]+\" style=\"width: 0px; height: 0px; border: 0px;\"><\/iframe><a[^\>]+><\/a><a[^\>]+><\/a><\/body>/")
                        ) {
                            throw new CheckRetryNeededException(3, 7);
                        }

                        return $this->checkErrors();
                    }
                }

                $this->AccountFields['Login'] = substr(str_replace(' ', '', $this->AccountFields['Login']), 0, 16);
                $elem->sendKeys($this->AccountFields['Login']);

                $pass = $this->waitForElement(WebDriverBy::xpath('//input[@id = "default-pass"]'), $this->timeout);

                if (!$pass) {
                    return $this->checkErrors();
                }
                // AccountID: 4444616
                $maxlength = $this->http->FindSingleNode('//input[@id = "default-pass"]/@maxlength');
                // $ngMaxlength = $this->http->FindSingleNode('//input[@id = "default-pass"]/@ng-maxlength');
                $this->logger->debug("Password maxlength: {$maxlength}");

                if ($maxlength && $maxlength == 20) {
                    $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, $maxlength);
                }

                $pass->sendKeys($this->AccountFields['Pass']);

                /*$this->driver->executeScript("
                    var FindReact = function (dom) {
                        for (var key in dom) if (0 == key.indexOf(\"__reactEventHandlers$\")) {
                            return dom[key];
                        }
                        return null;
                    };
                    FindReact(document.querySelector('input[id = \"username\"]')).onChange({target:{value:'{$this->AccountFields['Login']}'}});
                    FindReact(document.querySelector('input[id = \"default-pass\"]')).onChange({target:{value:'" . str_replace('\\', '\\\\', $this->AccountFields['Pass']) . "'}});
                ");*/

                $link = $this->waitForElement(WebDriverBy::xpath("(//button[contains(@class,'fb-btn-primary-lg')])[1]"), $this->timeout);

                if (!$link) {
                    return $this->checkErrors();
                }
                sleep(1);
                $this->saveResponse();
                $this->logger->debug("click 'Sign in'");
                $this->driver->executeScript("let btn = document.querySelector('button.fb-btn-primary-lg'); if (btn) btn.click();");

                sleep(5);

                if ($this->waitForElement(WebDriverBy::xpath("(//button[contains(@class,'fb-btn-primary-lg')])[1]"), 0)) {
                    $this->logger->debug("click 'Sign in' one more time");
                    $this->saveResponse();
                    $this->driver->executeScript("let btn = document.querySelector('button.fb-btn-primary-lg'); if (btn) btn.click();");
                }

            break;
        }// switch ($this->AccountFields['Login2'])

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                // Sorry, our website is temporarily out of action.
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Sorry, our website is temporarily out of action.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // We're very sorry but an error occurred processing your request.
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re very sorry but an error occurred processing your request.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Australia': default:
                $this->saveResponse();
                // Maintenance
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'re busy working through some scheduled maintenance for the website")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

            break;
        }// switch ($this->AccountFields['Login2'])

        return false;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                if (!$this->http->PostForm()) {
                    return $this->checkErrors();
                }
                //# Confirm your details
                if ($message = $this->http->FindPreg("/(We\'ve changed the way members sign in to our site. Please take a moment to confirm your details for us\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                //# Access is allowed
                if ($this->http->FindNodes("//a[contains(@href, 'sign_out')]/@href")) {
                    return true;
                }

                // Sorry, we could not sign you in with those details
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'we could not sign you in with those details')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We can't find a Fly Buys account for the number you entered
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 't find a Fly Buys account for the number you entered')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, your Flybuys number or password was incorrect.
                // Sorry, your username or password was incorrect.
                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Sorry, your ') and contains(text(), ' or password was incorrect.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, you can't sign in or create an account on Fly Buys with this card.
                if ($message = $this->http->FindSingleNode('//h3[contains(text(), "Sorry, you can\'t sign in or create an account on Fly Buys with this card.")]')) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // It looks like you haven't registered this Fly Buys number yet.
                if ($message = $this->http->FindPreg("/(It looks like you haven't registered this Fly Buys number yet\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Check your email and click on the link to sign in immediately.
                if ($this->http->FindSingleNode('//h1[contains(text(), "We\'ve sent a link")]')) {
                    $this->parseQuestion();
                }

                break;

            case 'Australia': default:
                $startTime = time();
                $time = time() - $startTime;
                $sleep = 30;

                try {
                    while ($time < $sleep) {
                        $this->logger->debug("(time() - \$startTime) = {$time} < {$sleep}");

                        $nextBtn = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Next"]'), 0);
                        $this->saveResponse();

                        if ($nextBtn && $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Let\'s make your account more secure")]'), 0)) {
                            $nextBtn->click();

                            $sendCode = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Send verification code"]'), 5);
                            $this->saveResponse();

                            if (!$sendCode) {
                                return false;
                            }

                            if ($this->isBackgroundCheck()) {
                                $this->Cancel();
                            }

                            $sendCode->click();

                            return $this->processSecurityCheckpoint();
                        }

                        if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "It looks like you\'re logging in from a new device. For your security we\'ve sent a verification code to ")]'), 0)
                        || $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Verify your identity")]'), 0)) {
                            return $this->processSecurityCheckpoint();
                        }

                        // Your account has been locked due to multiple failed login attempts. To unlock your account, please
                        if ($error = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your account has been locked due to multiple failed login attempts")]'), 0)) {
                            throw new CheckException($error->getText(), ACCOUNT_LOCKOUT);
                        }
                        // Access is allowed
                        if ($elem = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign out")]'), 0)) {
                            return true;
                        }

                        $this->saveResponse();

                        if ($error = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "You must pair your mobile application.")]'), 0)) {
                            throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
                        }

                        if ($this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Let’s start by confirming your mobile number")]'), 0)) {
                            $this->throwProfileUpdateMessageException();
                        }

                        if ($error = $this->waitForElement(WebDriverBy::xpath("(//span[contains(@class, 'text-fb-rustyRed')])[last()] | //div[contains(@class, 'bg-fb-rustyRed')]"), 0)) {
                            $message = $error->getText();
                            $this->logger->error("[Error]: {$message}");

                            if (
                                strstr($message, 'is invalid.')
                                || strstr($message, 'Incorrect email or password. Please try your flybuys number')
                                || strstr($message, 'Incorrect member number or password.')
                                || strstr($message, 'The email, Flybuys member number or password you entered didn\'t match our records')
                                || strstr($message, 'To keep your account secure, we require that you')
                            ) {
                                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                            }

                            if (strstr($message, 'There was an error processing your request or contact us')) {
                                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                                $this->DebugInfo = "request ha been blocked";

                                throw new CheckRetryNeededException(2, 5, $message, ACCOUNT_PROVIDER_ERROR);
                            }

                            $this->DebugInfo = $message;

                            return false;
                        }// if ($error = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'text-fb-rustyRed')]"), 0))

                        $this->saveResponse();
                        $time = time() - $startTime;
                    }// while ($time < $sleep)
                } catch (NoSuchWindowException $e) {
                    $this->logger->error("NoSuchWindowException exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 3);
                }

                // Access is allowed
                if ($elem = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign out")]'), 0)) {
                    return true;
                }

                $currentUrl = $this->http->currentUrl();
                $this->logger->debug("[Current URL]: {$currentUrl}");

                if ($currentUrl == 'https://my.flybuys.com.au/dashboard') {
                    return true;
                }

                // This error not showing on the website
                // The account number can no longer be used for authentication.
                if (in_array($this->AccountFields['Login'], [6008943538023201, 6008944135629317, 6008943091369702, 6008944625967607])) {
                    throw new CheckException("The account number can no longer be used for authentication.", ACCOUNT_INVALID_PASSWORD);
                }
                // Error not showing on the website
                if (in_array($this->AccountFields['Login'], [
                    6008944014673402,
                    6008943032950701,
                    6008943067802702,
                    6008944998803215,
                    6008943029540317,
                    6008949870539001,
                    6008949763391205,
                ])
                ) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                // We are unable to your request at the moment Please try again later.
                if ($this->http->currentUrl() == 'http://www.flybuys.com.au/cdn/busy') {
                    throw new CheckException('We are unable to your request at the moment Please try again later.', ACCOUNT_PROVIDER_ERROR);
                }

                if (
                    $this->http->FindPreg('/fingerprint\/script\/kpf\.js\?url=/')
                    || $this->http->FindSingleNode('//h1[contains(text(), "Looks like something went wrong")]')
                ) {
                    throw new CheckRetryNeededException(3, 7);
                }

                $this->checkErrors();

                break;
        }// switch ($this->AccountFields['Login2'])

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        // Check your email and click on the link to sign in immediately.
        $message = $this->http->FindSingleNode("//p[contains(text(), 'Check your email')]");

        if (!$message) {
            return false;
        }
        $question = "Please enter the authorization link which was sent to your email (link from 'Sign in with this link' button)"; /*review*/
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $q = $this->waitForElement(WebDriverBy::xpath('
            //span[contains(text(), "We\'ve sent a verification code to")]
            | //p[contains(text(), "It looks like you\'re logging in from a new device. For your security we\'ve sent a verification code to ")]
            | //label[contains(text(), "Enter verification code")]
        '), 5);
        $this->saveResponse();
        $question = $q ? $q->getText() : $this->http->FindSingleNode('
                //span[contains(text(), "We\'ve sent a verification code to")]/text()[1]
                | //p[contains(text(), "It looks like you\'re logging in from a new device. For your security we\'ve sent a verification code to ")]
                | //label[contains(text(), "Enter verification code")]
        ');

        if (strstr($question, 'Enter verification code')) {
            $question = "We've send a text message to: " . $this->http->FindSingleNode("//input[contains(@value, 'XXXXXXX')]/@value");
        }

        if (!$question) {
            $error = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'text-fb-rustyRed')]"), 0);

            if ($error
                && (
                    strstr($error->getText(), 'Mobile number is required.')
                    || strstr($error->getText(), 'Please enter a valid Australian mobile number.')
                )
            ) {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }
        $this->holdSession();

        if (!isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, "Question");

            return false;
        }

        /*
        for ($i = 1; $i <= mb_strlen($this->Answers[$question]) && $i < 7; $i++) {
            $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[contains(@aria-label,'Character ')][$i]"), 0);

            if ($securityAnswer) {
                $securityAnswer->clear();
                $securityAnswer->sendKeys($this->Answers[$question][$i - 1]);
                usleep(100);
            }
        }
        */

        $input = $this->waitForElement(WebDriverBy::xpath('//label[contains(text(), "Enter verification code")]/preceding-sibling::input'), 0);
        $this->saveResponse();
        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        if (!$input) {
            return false;
        }

        $input->sendKeys($answer);

        // remember me
        $this->driver->executeScript('
                var rememberme = document.querySelector(\'label span.rs-checkbox-wrapper\');
                if (rememberme)
                    rememberme.click();
        ');

        $this->logger->debug("click button...");
        //$button->click();
        sleep(1);
        $this->saveResponse();
        $this->driver->executeScript("document.querySelector('button[aria-label=\"Primary large\"], #submitButton, #sign-on').click();");
        // Invalid verification code.
        $error = $this->waitForElement(WebDriverBy::xpath("//div[
            contains(text(), 'Something went wrong, please try again later.')
            or contains(text(), 'OTP validation error')
            or contains(text(), 'Invalid OTP code')
        ]"), 5);
        $this->saveResponse();

        if ($error) {
            $message = $error->getText();
            $this->AskQuestion($question, $message, "Question");

            return false;
        }
        $this->logger->debug("success");
        $this->logger->debug("CurrentUrl: " . $this->http->currentUrl());
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign out")] | //div[h3[contains(text(), "Remember this device")]]/following-sibling::form//input[@value = "Submit"] | //h1[contains(@class, "fb-me-account-summary-title")] | //div[contains(@class, "fb-me-account-summary-section")]//h2'), 10);
        $this->saveResponse();

        // Remember this device?
        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//div[h3[contains(text(), "Remember this device")]]/following-sibling::form//input[@value = "Submit"]'), 0);

        if ($rememberMe) {
            $rememberMe->click();
            $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Sign out")]'), 5);
            $this->saveResponse();
        }

        $this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                $answer = $this->Answers[$this->Question];
                unset($this->Answers[$this->Question]);

                if (!filter_var($answer, FILTER_VALIDATE_URL)) {
                    $this->AskQuestion($this->Question, "The link you entered seems to be incorrect"); /*review*/

                    return false;
                }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
                /*
                 * link from email
                 * https://www.flybuys.co.nz/magic_login?step=2&user%5Bemail_secret_code%5D=d0f7ae5e-dc78-476d-a041-0dfe3409ca65&user%5Busername%5D=6014355753950087
                 *
                 * redirect to
                 * https://www.flybuys.co.nz/myflybuys
                 */
                $this->http->GetURL($answer);

                return true;

                break;

            case 'Australia':
            default:
                if ($this->isNewSession()) {
                    return $this->LoadLoginForm() && $this->Login();
                }

                if ($step == "Question") {
                    return $this->processSecurityCheckpoint();
                }

                break;
        }

        return false;
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
        case 'New Zealand':
            $this->http->GetURL("https://www.flybuys.co.nz/manage_my_details");
            // Name
            $this->SetProperty("Name", beautifulName(
                $this->http->FindSingleNode("//input[@id = 'member_first_name']/@value") . ' ' .
                $this->http->FindSingleNode("//input[@id = 'member_last_name']/@value")
            ));
            // Balance - CURRENT POINTS BALANCE
            $this->http->GetURL("https://www.flybuys.co.nz/points_balance");
            $this->SetBalance($this->http->FindPreg("/[\d\.\,]+/ims"));

            // Expiration date  // refs #8563
            if ($this->Balance > 0) {
//                $this->http->GetURL("https://www.flybuys.co.nz/myflybuys");
                $this->http->GetURL("https://www.flybuys.co.nz/members/points_details");
                $body = $this->http->JsonLog();

                if (isset($body->expiries_html)) {
                    $this->http->SetBody($body->expiries_html);
                }
                $nodes = $this->http->XPath->query("//div[@id = 'points_expiry_table']/table//tr[td]");
                $this->logger->debug("Total {$nodes->length} nodes were found");

                if ($nodes->length == 3) {
                    $points = $this->http->FindSingleNode("td[1]", $nodes->item(2));
                    $exp = $this->http->FindSingleNode("td[1]", $nodes->item(0));
                    $this->logger->debug("Exp -> $exp");

                    if (strlen($exp) == 3) {
                        $this->SetExpirationDate(strtotime("+1 month -1 day", strtotime("01 " . $exp)));
                    }
                    // Expiring Balance
                    $this->SetProperty("ExpiringBalance", $points);
                }// if ($nodes->length == 3)
                elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'You have no expiring points')]")) {
                    $this->logger->notice(">>> $message");
                }
            }// if ($this->Balance > 0)

            break;

        case 'Australia': default:
            // Balance - MY POINTS
            $this->SetBalance($this->http->FindSingleNode('//h1[contains(@class, "fb-me-account-summary-title")] | //div[contains(@class, "fb-me-account-summary-section")]//h2'));
            // Name
            $this->SetProperty("Name", beautifulName($this->http->FindSingleNode('//span[contains(@class, "fb-me-headline-title") and not(normalize-space(.) = "Hi")]')));

            $this->http->GetURL("https://experience.flybuys.com.au/my/details");
            $this->waitForElement(WebDriverBy::xpath('//*[@data-test-id="firstName"]'), 5);
            $this->saveResponse();
            // Name
            $name = $this->http->FindSingleNode('//*[@data-test-id="firstName"]') . " " . $this->http->FindSingleNode('//*[@data-test-id="lastName"]');

            if (!empty($name)) {
                $this->SetProperty("Name", beautifulName($name));
            }
            // Membership No
            $this->SetProperty("MembershipNo", $this->http->FindSingleNode('//node()[*[@data-test-id="firstName"]]/following-sibling::node()'));

            // Expiration Date  // refs #8562
            $this->http->GetURL("https://experience.flybuys.com.au/my/transaction-history");
//            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "fb-me-transaction-partner-container")]'), 5);
            $expandLastRow = $this->waitForElement(WebDriverBy::xpath('//button[@aria-label="Expand" or @aria-label="Expanded"]'), 5);

            if ($expandLastRow) {
                $expandRows = $this->driver->findElements(WebDriverBy::xpath('//button[@aria-label="Expand" or @aria-label="Expanded"]'));
                $this->logger->debug("try to expand all rows...");

                foreach ($expandRows as $i => $expandRow) {
                    $this->logger->debug("#{$i}:");
                    $expandRow->click();
                }

                sleep(2);
            }

            $this->saveResponse();

            $transactions = $this->http->XPath->query('//div[contains(@class, "fb-me-transaction-partner-container")]//div[contains(@class, "fb-transition")]//div[contains(@class, "fb-cardview-container") and contains(@class, "fb-me-transaction-partner")]');
            $this->logger->debug("Total {$transactions->length} transactions were found");

            foreach ($transactions as $transaction) {
                $date = $this->http->FindSingleNode('.//div[contains(@class, "fb-me-transaction-partner-date")]', $transaction);
                $points = $this->http->FindSingleNode('.//div[contains(@class, "fb-me-transaction-partner-pointstotal")]', $transaction, false, "/(.+)\s*pts/");
                $this->logger->debug("[{$date}]: {$points}");

                if (
                    isset($date, $points)
                    && ($exp = strtotime($date))
                    && ($points > 0)
                ) {
                    // Last Activity
                    $this->SetProperty("LastActivity", $date);
                    $this->SetExpirationDate(strtotime("+12 month", $exp));
                }

                break;
            }// foreach ($transactions as $transaction)

            break;
        }// switch ($this->AccountFields['Login2'])
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        switch ($this->AccountFields['Login2']) {
            case 'New Zealand':
                $arg['CookieURL'] = 'https://www.flybuys.co.nz/sign_in';

                break;

            case 'Australia': default:
                $arg['CookieURL'] = 'https://www.flybuys.com.au/flybuys/content';

            break;
        }

        return $arg;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'Australia';
        }

        return $region;
    }
}
