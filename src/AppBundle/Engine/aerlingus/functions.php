<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerAerlingus extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    public $airCodes = [];
    public $lastName = null;

    public $headers = [
        "Accept" => "application/json, text/plain, */*",
    ];
    protected $currentItin = 0;
    private $loyalty;

    public static function GetAccountChecker($accountInfo)
    {
//        if ($accountInfo["Login"] == '') {
        require_once __DIR__ . "/TAccountCheckerAerlingusSelenium.php";

        return new TAccountCheckerAerlingusSelenium();
//        }

        return new static();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);
        $this->http->disableOriginHeader();
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['X-XSRF-TOKEN'])) {
            return false;
        }

        if ($this->loginSuccessful($this->State['X-XSRF-TOKEN'])) {
            return true;
        }
        $this->delay();

        return false;
    }

    public function LoadLoginForm()
    {
        // password validation
        if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
            if (strpos($this->AccountFields['Pass'], '\\') !== false) {
                throw new CheckException('Password must contain at least one uppercase letter, one lowercase letter and one number. Spaces are not allowed.', ACCOUNT_INVALID_PASSWORD);
            }

            if (strlen($this->AccountFields['Pass']) < 8) {
                throw new CheckException('Password must contain at least 8 character(s). Please try again.', ACCOUNT_INVALID_PASSWORD);
            }
        }

        $this->http->RetryCount = 0;
//        $this->http->removeCookies();

        if (strstr($this->http->currentUrl(), "maintenance")) {
            return $this->checkErrors();
        }

        // from IsLoggedIn
        $this->http->unsetDefaultHeader("X-XSRF-TOKEN");

        /*
        if ($this->attempt > 0) {
        */
        $this->selenium();

        return true;
        /*
        }
        */
        /*
        else
            $this->postProof();
        */

        $this->http->GetURL("https://www.aerlingus.com/api/loyalty/v1/login?redirect=%2Fhtml%2Fuser-profile.html");

        $clientId = $this->http->FindPreg('/client=(.+?)&/', false, $this->http->currentUrl());
        $state = $this->http->FindPreg('/state=(.+?)&/', false, $this->http->currentUrl());
        $redirect_uri = $this->http->FindPreg('/redirect_uri=(.+?)&/', false, $this->http->currentUrl());
        $csrf = $this->http->getCookieByName("_csrf", null, "/usernamepassword/login", true);

        if (
            !isset($clientId, $state, $redirect_uri)
            || !$csrf
            || $this->http->Response['code'] != 200
            || stristr($this->http->currentUrl(), 'maintenance')
        ) {
            return $this->checkErrors();
        }

        $this->checkBlocking();

        $this->http->SetInputValue('test_emailSignIn', $this->AccountFields['Login']);
        $this->http->SetInputValue('test_passwordSignIn', $this->AccountFields['Pass']);
        $data = [
            "client_id"     => $clientId,
            "redirect_uri"  => urldecode($redirect_uri),
            "tenant"        => "iagloyalty-prod",
            "response_type" => "code",
            "scope"         => "openid",
            "_csrf"         => $csrf,
            "state"         => $state,
            "_intstate"     => "deprecated",
            "username"      => $this->AccountFields['Login'],
            "password"      => $this->AccountFields['Pass'],
            "connection"    => "EI-Profiles",
        ];
        $headers = [
            'Accept'          => '*/*',
            'Content-Type'    => 'application/json',
            'Auth0-Client'    => 'eyJuYW1lIjoiYXV0aDAuanMtdWxwIiwidmVyc2lvbiI6IjkuMTQuMyJ9',
            'Accept-Encoding' => 'gzip, deflate, br',
        ];
        $this->delay();
        $this->http->PostURL("https://accounts.aerlingus.com/usernamepassword/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkBlocking()
    {
        $this->logger->notice(__METHOD__);
        // Access To Website Blocked
        if (
            $this->http->FindSingleNode('//div[@id = "distilIdentificationBlock"]/@id')
            || $this->http->FindSingleNode('//h1[contains(text(), "Access To Website Blocked")]')
            || $this->http->FindSingleNode('//h1[contains(text(), "Pardon Our Interruption")]')
        ) {
            throw new CheckRetryNeededException(3, 5);
        }
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                //                [1152, 864],
                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            if ($this->attempt == 2) {
                $selenium->useChromium();
            } else {
                $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_59);
                $selenium->setKeepProfile(true);
            }

            if ($this->attempt == 1) {
                $selenium->setProxyBrightData();
            } else {
                $selenium->http->SetProxy($this->proxyReCaptchaVultr());
            }

            /*
            $selenium->useCache();
            $selenium->disableImages();
            */
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.aerlingus.com/api/loyalty/v1/login?redirect=%2Fhtml%2Fuser-profile.html");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            // login
            $loginInput = $selenium->waitForElement(WebDriverBy::id('test_membership_login_page-1'), 20);
            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('test_password_login_page-1'), 0);

            $this->hideOverlay($selenium);

            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                // save page to logs
                $this->savePageToLogs($selenium);

                return $this->checkErrors();
            }

            if ($cookieAccept = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 0)) {
                $cookieAccept->click();
                sleep(1);
                $this->savePageToLogs($selenium);
            }

            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->clear();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            // Sign In
            sleep(1);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in")]'), 0);
            $this->savePageToLogs($selenium);
            $this->logger->debug("click by login field");

            if ($selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "One moment please!")]'), 0)) {
                sleep(5);
                $this->savePageToLogs($selenium);
            }
            $loginInput->click(); // for error 'Password must contain at least one uppercase letter, one lowercase letter and one number. Spaces, backslashes, double quotes are not allowed.'
            $this->savePageToLogs($selenium);

            if (!$button) {
                return false;
            }

            try {
                $this->logger->debug("click by 'Log in' button");
                $button->click();
                /*
                $selenium->driver->executeScript('document.querySelector(\'button[data-test-id="test_button_login_page"]\').click();');
                */
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException exception: " . $e->getMessage());
                // Password must contain at least one uppercase letter, one lowercase letter and one number.
                if (
                    $selenium->waitForElement(WebDriverBy::xpath('//span[contains(@class, "error-icon")]'), 0)
                    && ($passwordError = $selenium->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Password must contain at least one uppercase letter, one lowercase letter and one number.")]'), 0, false))
                ) {
                    throw new CheckException($passwordError->getText(), ACCOUNT_INVALID_PASSWORD);
                }
            }

            $this->logger->debug("waiting results");
            $resultXpath = '
                (//div[contains(@class, "user-profile-aerclub-card")]
                | //p[@data-test-id = "test_membership_number"]
                | //div[@id = "scroll_messages"])[normalize-space(text())!=""]
                | //p[contains(@class, "uil-errorRed")]
                | //section[contains(@class, "uil-message-error")]
                | //h4[contains(text(), "We appear to have lost our way a little")]
                | //h4[contains(text(), "Sorry, we couldn\'t find this page")]
                | //span[contains(text(), "We could not send the sms. Please try the recovery code.")]
                | //span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]
                | //p[contains(text(), "After completing the CAPTCHA below, you will immediately regain access to the site again.")]
            ';

            $result = $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 15);

            try {
                $this->savePageToLogs($selenium);
            } catch (UnknownServerException $e) {
                $this->logger->error("UnknownServerException: " . $e->getMessage());
                $this->logger->debug("Need to change ff version");
            }

            // Please fill in your Username or Email Address - bug fix
            if (
                $result
                && (
                    count($this->http->FindNodes('//p[contains(@class, "uil-errorRed")]')) == 2
                    || $this->http->FindSingleNode('//p[contains(text(), "Please enter a valid Captcha to continue")]')
                )
            ) {
                $retry = true;
            }

            if (!$result && $selenium->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "One moment please!")]'), 0)) {
                $this->savePageToLogs($selenium);
                $result = $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 15);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();
            $token = null;

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'XSRF-TOKEN') {
                    $token = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if (
                $token
                && !in_array($selenium->http->currentUrl(), [
                    'https://www.aerlingus.com/html/login.html',
                    'https://www.aerlingus.com/html/resend-verification-email.html',
                ]
                )
            ) {
                $this->http->setDefaultHeader('X-XSRF-TOKEN', $token);
                $this->http->GetURL($selenium->http->currentUrl());
                $this->http->GetURL("https://www.aerlingus.com/api/profile", $this->headers);
//                $response = $this->http->JsonLog();
            } elseif (
                strstr($selenium->http->currentUrl(), 'https://accounts.aerlingus.com/u/login-email-verification')
                || $this->http->FindSingleNode('//span[contains(text(), "We could not send the sms. Please try the recovery code.")]')
                || $this->http->FindSingleNode('//span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]')
            ) {
                $this->State['Cookies'] = $selenium->driver->manage()->getCookies();
                $this->State['CurrentURL'] = $selenium->http->currentUrl();
                $this->State['state'] = $this->http->FindSingleNode("//input[@name = 'code']/ancestor::form[1]/input[@name = \"state\"]/@value");
            } elseif (
                !strstr($selenium->http->currentUrl(), 'https://accounts.aerlingus.com/login?')
                && !strstr($selenium->http->currentUrl(), 'https://www.aerlingus.com/support/login-error/')
            ) {
                $this->http->GetURL($selenium->http->currentUrl());
            } elseif (!$token && $selenium->waitForElement(WebDriverBy::xpath('
                    //h2[contains(text(), "One moment please!")]
                    | //h4[contains(text(), "Sorry, we couldn\'t find this page")]
                    | //h4[contains(text(), "{{\'page.notfound.text.title\' | i18n}}")]
                '), 0)
            ) {
                $this->markProxyAsInvalid();
                $retry = true;
            }

            $result = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->logger->debug("Need to change ff version");
            $retry = true;
        } catch (NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO

            $this->http->saveScreenshots = false;

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Login and new registrations to AerClub are temporarily unavailable.')]
                | //p[contains(text(), 'site is currently unavailable while essential maintenance is being carried out')]
                | //p[contains(text(), 'Access to your AerClub account between ')]
                | //p[contains(text(), 'Access to your AerClub account will be unavailable until')]
                | //h2[contains(text(), 'Online check-in is unavailable')]
                | //p[contains(text(), 'Our website will be unavailable until ')]
                | //p[contains(text(), 'Our website is currently unavailable to facilitate a scheduled upgrade.')]
                | //p[contains(text(), 'Our website and mobile app are currently unavailable in order to facilitate a scheduled upgrade.')]
                | //p[contains(text(), 'Our website and mobile app are currently unavailable while an upgrade is carried out.')]
                | //p[contains(text(), 's not possible at the moment to log in to your AerClub / Aer Lingus account on aerlingus.com. You can log in using the Aer Lingus app.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // 502 Bad Gateway
        if ($this->http->FindSingleNode("
                //title[contains(text(), '502 Bad Gateway')]
                | //h2[contains(text(), 'The request could not be satisfied.')]
            ")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (
            isset($this->http->Response['code'])
            && $this->http->Response['code'] == 403
            && $this->http->currentUrl() == "https://www.aerlingus.com/api/profile"
        ) {
            throw new CheckRetryNeededException(3, 0);
        }

        $response = $this->http->JsonLog();

        $code = $response->code ?? null;
        $description = $response->description ?? null;

        if ($code && $description) {
            // These log in details are incorrect. Please try again or recover your details.
            if ($code == "invalid_user_password" && $description == "WRONG_USERNAME_OR_PASSWORD") {
                throw new CheckException("These log in details are incorrect. Please try again or recover your details.", ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }
        $message = $response->message ?? null;
        $name = $response->name ?? null;

        if ($name && $message) {
            // We are experiencing some technical difficulties on our side. Please try again in a few minutes or contact us if the problem persists.
            if ($name == 'Error' && $message == 'INTERNAL_ERROR') {
                throw new CheckException("We are experiencing some technical difficulties on our side. Please try again in a few minutes or contact us if the problem persists.", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $this->http->RetryCount = 0;

        if ($this->http->ParseForm("hiddenform")) {
            $this->http->PostForm();
        }

        $this->http->RetryCount = 2;

        if ($this->parseQuestion()) {
            return false;
        }

        /*
        $message = $response->messages[0]->msg ?? $this->http->FindSingleNode('//div[@id = "message-text-1" or @id = "message-text-2"]/p[@class = "ng-scope"]');
        if ($message) {
            switch ($message) {
                case "The password and/or email address you entered is incorrect. Please try again.":
                case "The password and/or username you entered is incorrect. Please try again.":
                case "These log in details are incorrect. Please try again.":
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                    break;
                case "Oops! Something went wrong. Wrong method was used to view this page. If the problem persists, please try again from the homepage.":
                case "Your account is not yet verified so you cannot log in. Verify your account by generating a new email and clicking on the link in it within 24 hours.":
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                case "Unable to login. User is not verified!":
                    throw new CheckException("Your account is not yet verified so you cannot log in.", ACCOUNT_PROVIDER_ERROR);
                    break;
                case "ESB response missing necessary data":
                    throw new CheckRetryNeededException(2, 10);
                    break;
                default:
                    $this->logger->error("[Error]: {$message}");
                    break;
            }// switch ($message)
        }// if ($response->messages[0]->msg)
        */

        $token = $this->http->getCookieByName('XSRF-TOKEN');
        $this->State['X-XSRF-TOKEN'] = $token;
        $this->delay();

        $message = $this->http->FindSingleNode('
            //section[contains(@class, "uil-message-error")] 
            | //p[contains(@class,"uil-errorRed")]
        ');
        $this->logger->error("[Error]: {$message}");

        // Access is allowed
        if (empty($message) && $this->loginSuccessful($token)) {
            return true;
        }

        if ($message) {
            if (
                $message == 'These log in details are incorrect. Please try again or recover your details.'
                || $message == 'Username or Email cannot exceed 48 characters. Please try again.'
                || $message == 'This account is not valid. Please contact AerClub for assistance.'
                || $message == 'Password must contain at least one uppercase letter, one lowercase letter and one number. Spaces, backslashes, double quotes are not allowed'
                || $message == 'Password cannot exceed 20 characters. Please try again.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // We are experiencing some technical difficulties on our side. Please try again in a few minutes or contact us if the problem persists.
            if (
                strstr($message, 'We are experiencing some technical difficulties on our side. Please try again in a few minutes ')
                || strstr($message, 'AerClub is temporarily unavailable while we make some planned improvements. Everything should be back up and running ')
                || $message == 'You can’t log in until you verify your account. Please generate a new verification email and click on the link in it within 24 hours.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                strstr($message, 'You have attempted to log in with the wrong details too many times and your account is now locked.')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        $this->checkBlocking();

        if ($this->http->FindSingleNode('//div[@class = "page-loader" and @aria-hidden="false"]')) {
            $this->logger->notice('Loading...');
        }

        return $this->checkErrors();
    }

    public function parseQuestion()
    {
        $question = $this->http->FindSingleNode('
            //p[contains(text(), "We\'ve sent an email with your code to")]
            | //span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]
            | //span[contains(text(), "We could not send the sms. Please try the recovery code.")]
        ');

        if (!$question || !$this->http->ParseForm(null, '//form[//p[
                contains(text(), "We\'ve sent an email with your code to")]
                or //span[contains(text(), "Enter the 6-digit code we\'ve just sent to your phone.")]
                or //span[contains(text(), "We will generate a new recovery code")]
            ]')
        ) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetProxy($this->proxyReCaptchaVultr());
        $this->http->FormURL = $this->State['CurrentURL'];

        /*
        if ($this->Question === 'We could not send the sms. Please try the recovery code.') {

            $data = [
                "type" => "manual_input",
                "code" => $this->Answers[$this->Question],
            ];
            unset($this->Answers[$this->Question]);

            $this->http->PostURL("https://iagloyalty-prod.guardian.eu.auth0.com/api/verify-otp", json_encode($data));
            $response = $this->http->JsonLog();

            $message = $response->message ?? null;
            if ($message) {
                if ($message == 'Invalid OTP code') {
                    $this->AskQuestion($this->Question, "The verification code you entered is invalid.", 'Question');
                }

                return false;
            }

            $token = $this->http->getCookieByName('XSRF-TOKEN');
            $this->State['X-XSRF-TOKEN'] = $token;
            $this->delay();

            return $this->loginSuccessful($token);
        }
        */

        if (!strstr($this->Question, "We've sent an email with your code to")) {
            return $this->seleniumQuestions();
        }

        $this->sendNotification("2fa (email) - refs #20290 // RR");

        $this->http->SetInputValue("state", $this->State['state']);
        $this->http->SetInputValue("code", $this->Answers[$this->Question]);
        $this->http->SetInputValue("action", "default");
        unset($this->Answers[$this->Question]);
        $headers = [
            "Accept"     => "text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8",
            "Referer"    => $this->State['CurrentURL'],
            "User-Agent" => HttpBrowser::PROXY_USER_AGENT,
        ];
        $this->http->PostForm($headers);
        // The code you entered is invalid
        if ($error = $this->http->FindSingleNode('//li[@id = "error-element-code"]')) {
            $this->AskQuestion($this->Question, $error, 'Question');

            return false;
        }

        $token = $this->http->getCookieByName('XSRF-TOKEN');
        $this->State['X-XSRF-TOKEN'] = $token;
        $this->delay();

        return $this->loginSuccessful($token);
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 0);
        // Name
        if (isset($response->data[0]->lastName)) {
            $this->lastName = $response->data[0]->lastName;
        }

        if (isset($response->data[0]->firstName, $response->data[0]->lastName)) {
            $this->SetProperty("Name", beautifulName($response->data[0]->firstName . " " . $response->data[0]->lastName));
        }
        // Level
        if (isset($response->data[0]->frequentFlyer->tier)) {
            $this->SetProperty("Level", $response->data[0]->frequentFlyer->tier);
        }
        // Gold Circle Number
        if (isset($response->data[0]->frequentFlyer->membershipId)) {
            $this->SetProperty("Number", $response->data[0]->frequentFlyer->membershipId);
        }

        // refs #14014
        // Complete your move from the Gold Circle Club to AerClub and enjoy the benefits of our new loyalty programme.
        if (isset($response->data[0]->frequentFlyer->remark) && $response->data[0]->frequentFlyer->remark == 'New') {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }// if (isset($response->data[0]->frequentFlyer->remark) && $response->data[0]->frequentFlyer->remark == 'New')
        // Balance - Points Balance
        elseif (isset($response->data[0]->frequentFlyer->balance)) {
            $this->SetBalance($response->data[0]->frequentFlyer->balance);

            if (isset($response->data[0]->frequentFlyer->tierCredits)) {
                $this->SetProperty("TierCredits", $response->data[0]->frequentFlyer->tierCredits);
            }

            if (isset($response->data[0]->frequentFlyer->tierCredits)) {
                $this->SetProperty("TierCreditsToNextLevel", $response->data[0]->frequentFlyer->creditsToNextTier);
            }

            if (isset($response->data[0]->frequentFlyer->tierMembershipExpDate)) {
                $this->SetProperty("TierExpiryDate", date("d M Y", strtotime($response->data[0]->frequentFlyer->tierMembershipExpDate)));
            }
            // refs #14018
            $this->http->GetURL("https://www.aerlingus.com/api/profile/loyalty");
            $this->checkBlocking();
            $response = $this->http->JsonLog();
            // Tier credits
            if (isset($response->data[0]->accountDetails->tierCredits)) {
                $this->SetProperty("TierCredits", $response->data[0]->accountDetails->tierCredits);
            }
            // You need to earn ... Tier Credits to reach the [NEXT_TIER] tier (Tier Credits to Next Level)
            if (isset($response->data[0]->accountDetails->tierCredits)) {
                $this->SetProperty("TierCreditsToNextLevel", $response->data[0]->accountDetails->creditsToNextTier);
            }
            // Tier Expiry Date
            if (isset($response->data[0]->accountDetails->tierMembershipExpDate)) {
                $this->SetProperty("TierExpiryDate", date("d M Y", strtotime($response->data[0]->accountDetails->tierMembershipExpDate)));
            }
        }// elseif (isset($response->data[0]->frequentFlyer->balance))
        // User are not a member of this loyalty program
        elseif (!empty($this->Properties['Name']) && $this->http->FindPreg("/,\"frequentFlyer\":null,/")/* && $this->http->FindPreg("/,\"tier\":null/")*/) {
            $this->SetWarning(self::NOT_MEMBER_MSG);
        }// elseif (!empty($this->Properties['Name']) && $this->http->FindPreg("/,\"frequentFlyer\":null,/") && $this->http->FindPreg("/,\"tier\":null/"))

        if (isset($response->data[0]->transactionHistory->transactions)) {
            $this->loyalty = $response->data[0]->transactionHistory->transactions;
        } else {
            $this->loyalty = [];
        }

        // Expiration date refs #14309
        if (!empty($this->Balance)) {
            $tmp = $this->loyalty;
            $transactions = [];

            for ($i = -1, $iCount = count($tmp); ++$i < $iCount;) {
                $date = strtotime($tmp[$i]->date);

                if (!empty($date) && false === strpos($tmp[$i]->description, 'Combine my Avios')) {
                    $transactions[$date] = $tmp[$i];
                }
            }

            if (!empty($transactions)) {
                ksort($transactions, SORT_NUMERIC);
                $transaction = array_pop($transactions);
                $expirationDate = strtotime('+36 month', strtotime($transaction->date));

                if (!empty($expirationDate)) {
                    $this->SetExpirationDate($expirationDate);
                    $this->SetProperty('ExpiringBalance', $this->Balance);
                    $this->SetProperty('LastActivity', date('d/m/Y', strtotime($transaction->date)));
                }
            }
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        if ($this->isMobile()) {
            unset($arg['PostValues']);
            $arg['RedirectURL'] = "https://www.aerlingus.com/html/register-profile.html#/?returnUrl=/html/home.html";

            return $arg;
        }

        if (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/MSIE 8/ims', $_SERVER['HTTP_USER_AGENT'])) {
            unset($arg['PostValues']);
            $arg['RedirectURL'] = "https://www.aerlingus.com/html/register-profile.html#/?returnUrl=/html/home.html";

            return $arg;
        }
        $arg["CookieURL"] = "https://www.aerlingus.com/html/user-profile.html#?tabType=my-gold-circle";

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        if ($this->lastName) {
            $result = $this->ParseItinerariesV2();
        } else {
            $result = $this->ParseItinerariesV1();
        }

        return $result;
    }

    public function ParseItinerariesV2()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        $this->http->GetURL("https://www.aerlingus.com/api/profile/trips");
        $response = $this->http->JsonLog(null, 3, true);
        $c = 0;

        if (isset($response['data'][0]['trips'])) {
            $trips = $response['data'][0]['trips'];
            $this->logger->debug("Total " . count($trips) . " itineraries were found");

            foreach ($response['data'][0]['trips'] as $i => $trip) {
                unset($it);
                $pnr = ArrayVal($trip, 'pnr');
                $flights = ArrayVal($trip, 'flights', []);
                $flown = true;

                foreach ($flights as $flight) {
                    if (ArrayVal($flight, 'flown') === false) {
                        $flown = false;
                    }
                }// foreach ($flights as $flight)

                if ($flown) {
                    if (!$this->ParsePastIts) {
                        $this->logger->notice("skip old itinerary: {$pnr}");
                        // del for detect noIts
                        if (isset($trips[$i]) && $trips[$i]['pnr'] == $pnr) {
                            unset($trips[$i]);
                        }

                        continue;
                    }
                    // Past Itineraries
                    $this->ParseItinerary($trip);

                    continue;
                }// if ($flown)
                $it = $this->ParseItineraryV2($this->lastName, $pnr);

                if (!empty($it)) {
                    $result[] = $it;
                }
                $c++;

                if ($c > 50) {
                    break;
                }
            }// foreach ($response->data[0]->trips as $trip)

            if (empty($trips)) {
                $this->logger->debug('account has only past flights');

                return $this->noItinerariesArr();
            }
        }// if (isset($response->data[0]->trips))
        // no Itineraries
        elseif ($this->http->FindPreg("/(\"data\":\[\{\"trips\":null)/ims")) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    public function ParseItineraryV2($lastName, $pnr)
    {
        $this->logger->notice(__METHOD__);

        if (!$lastName || !$pnr) {
            return [];
        }
        $this->logger->info(
            sprintf('Parse Itinerary #%s [%s]', $pnr, $this->currentItin++), ['Header' => 3]);
        $arFields = [
            'ConfNo'   => $pnr,
            'LastName' => $lastName,
        ];
        $it = [];
        $res = $this->CheckConfirmationNumberInternal($arFields, $it);

        if (is_string($res)) {
            $this->logger->error($res);
        }

        return $it;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aerlingus.com/html/trip-mgmt.html#?select=0";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);

        // try new version
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://webcheckin.aerlingus.com/api/checkin/?pnr={$arFields['ConfNo']}&surname={$arFields['LastName']}", [], 30);
        $data = $this->http->JsonLog();
        $this->http->RetryCount = 2;

        if (isset($data->statusCode)) {
            if ($data->statusCode === 'error') {
                return $data->messages[0]->header . ' ' . $data->messages[0]->msg;
            }

            if ($data->statusCode === 'success') {
                if (!empty($res = $data->data[0]->checkInInfo)) {
                    $r = $this->itinerariesMaster->add()->flight();
                    $r->general()
                        ->confirmation($res->bookingReferenceID);
                    $accCollected = [];
                    $ticketCollected = [];

                    foreach ($res->passengerInfo as $passenger) {
                        $r->general()->traveller(beautifulName($passenger->firstName . ' ' . $res->lastName), true);

                        foreach ($passenger->ticketingInfo as $ticket) {
                            $ticketNum = $ticket->airlineAccountingCode . '-' . $ticket->formAndSerialNumber;

                            if (!in_array($ticketNum, $ticketCollected)) {
                                $r->issued()->ticket($ticketNum, false);
                                $ticketCollected[] = $ticketNum;
                            }
                        }

                        if (isset($passenger->additionalInfo->frequentFlyer->number)
                            && !in_array($passenger->additionalInfo->frequentFlyer->number, $accCollected)
                        ) {
                            $r->program()->account($passenger->additionalInfo->frequentFlyer->number, false);
                            $accCollected[] = $passenger->additionalInfo->frequentFlyer->number;
                        }
                    }

                    foreach ($res->flightInfo as $flight) {
                        foreach ($flight->flightSegmentInfo as $segment) {
                            if ($segment->originAirportCode === 'HDQ' || $segment->destinationAirportCode === 'HDQ') {
                                $this->logger->debug("skip segment with HDQ");

                                continue;
                            }
                            $s = $r->addSegment();
                            $s->extra()
                                ->status($segment->status)
                                ->duration($segment->segmentTripDuration);
//                            $name = $segment->destinationAirportName ??
                            if ($date = str_replace('T', ' ', $segment->departureDate)) {
                                $date = preg_replace("/:\d{2}\.\d{3}.+/", ' ', $date);
                                $s->departure()->date2($date);
                            }
                            $s->departure()
                                ->code($segment->originAirportCode)
                                ->name($segment->originAirportName ?? $segment->originCity);

                            if ($date = str_replace('T', ' ', $segment->arrivalDate)) {
                                $date = preg_replace("/:\d{2}\.\d{3}.+/", ' ', $date);
                                $s->arrival()->date2($date);
                            }
                            $s->arrival()
                                ->code($segment->destinationAirportCode)
                                ->name($segment->destinationAirportName ?? $segment->destinationCity);
                            $s->airline()
                                ->name($segment->marketingAirportCode)
                                ->number($segment->flightNumber);

                            if (isset($segment->operatingAirline, $segment->operatingAirline->airlineCode, $segment->operatingAirline->flightNumber)) {
                                if ($segment->marketingAirportCode !== $segment->operatingAirline->airlineCode) {
                                    $s->airline()
                                        ->carrierName($segment->operatingAirline->airlineCode)
                                        ->carrierNumber($segment->operatingAirline->flightNumber);
                                }
                            }
                        }
                    }

                    return null;
                }
            }
        }

        // go old version
        if (!isset($this->State['X-XSRF-TOKEN'])) {
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
            $this->http->RetryCount = 0;
            $this->postProof();
            $this->http->RetryCount = 2;
        }

        $this->seleniumRetrieve();

        $this->headers += [
            'Content-Type' => 'application/json;charset=UTF-8',
        ];
        $data = '{"pnr":"' . $arFields['ConfNo'] . '","surName":"' . $arFields['LastName'] . '"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.aerlingus.com/api/manage/dashboard", $data, $this->headers);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->statusCode, $response->redirectUrl) && $response->statusCode === 'success') {
            $correlation = ArrayVal($this->http->Response['headers'], 'x-correlation-id');
            $this->http->setDefaultHeader('X-Correlation-ID', $correlation);

//            $this->http->NormalizeURL($response->redirectUrl);
//            $this->http->GetURL($response->redirectUrl);

//            $this->http->setCookie('correlationId', $correlation, '.aerlingus.com');
//            $this->http->setCookie('reese84', '3:Gmi7Q0Mj0pZFSZajOS1Rmg==:fUN6RZyLNKy7rUHygZxWp+bq8YIjz1HRcRXmaShcygX3coZk7yWtdY4gR2votSVUCgXDaT/q7E6DjJcHjUALtK0ppgeMJKxhH3zgNxLG3VWvL2VFTAa2sSPvGQSyX/jznbTQYQQ+TjsRIDJ+93CnRNAz5frJHekQNNSSFq+mraxF/a2qSr09IZA5lMuH5AhgGGou+hbXWFGXQXfb415h8+cZVH4a//JpI6iakYmKixbyS6/LN8kHdjf6UbTHdl7ToUPGL58AcCwOYFZ/7DK/PISvJLtDAZA7gFu0RzdtDuDJU0m12o9cIvbqOuxhX0DR3FBdD15OeJkNgGf13yUiF4xC2N885YGZ3dW0kaKxmir+OMgshW3d0QEFbTmOhfsiSYrUljWG/9MfXQCPPIr+GuxO0Ax22z7tapBcRX8pMEyco+lFaH4vMSIVlox1TEj6:w+n88/omGzCUUnNseQ547D8oi2/gTAlm7zyQ0zzRodo=', '.aerlingus.com');
            $data = '{"pnr":"","surName":""}';
            $this->http->PostURL(sprintf("https://www.aerlingus.com/api/manage/dashboard/data?random=%s", $this->random() . rand(10, 99)), $data, $this->headers);
            $response = $this->http->JsonLog(null, 0);

            if (isset($response->data[0]->bookingReferenceInfo->pnr)) {
                $this->ParseConfirmationItinerary($response);
            } else {
                $this->logger->error("Itinerary not found");

                if (isset($response->data[0]->code)) {
                    $this->http->GetURL("https://www.aerlingus.com/api/messages/en/basic");
                    $this->logger->error("[Error Code]: {$response->data[0]->code}");
                    $messages = $this->http->JsonLog(null, 0) ?: [];

                    foreach ($messages as $message) {
                        if ($message->key == $response->data[0]->code) {
                            return $message->value;

                            break;
                        }// if ($message->key == $response->data[0]->code)
                    }// foreach ($messages as $message)
                }// if (isset($response->data[0]->code))
                else {
                    if (isset($response->messages[0]->msg)) {
                        $this->logger->debug("[Error]: {$response->messages[0]->msg}");
                    }

                    if (isset($response->messages[0]->msg) && $response->messages[0]->msg != 'Oops! Something went wrong. Wrong method was used to view this page. If the problem persists, please try again from the homepage.') {
                        $this->sendNotification("aerlingus - failed to retrieve itinerary by conf #");
                    }
                }
            }
        }// if (isset($response->statusCode, $response->redirectUrl) && $response->statusCode == 'success')
        else {
            $this->logger->error("Itinerary not found");

            if (isset($response->messages[0]->msg)) {
                $this->logger->debug("[Error]: {$response->messages[0]->msg}");
            }

            if (isset($response->messages[0]->msg) && $response->messages[0]->msg != 'Oops! Something went wrong. Wrong method was used to view this page. If the problem persists, please try again from the homepage.') {
                $this->sendNotification("aerlingus - failed to retrieve itinerary by conf #");
            }
        }

        return null;
    }

    public function seleniumRetrieve()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $resolutions = [
                [1152, 864],
                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);
            $selenium->useChromium();
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.aerlingus.com/html/trip-mgmt.html#?select=0");
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            // login
            $selenium->waitForElement(WebDriverBy::id('bookingrefer-1'), 10);
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->logger->debug("Need to change ff version");
        } catch (NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); // TODO

            $this->http->saveScreenshots = false;
        }

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName"    => [
                "Type"     => "string",
                "Caption"  => "Family Name",
                "Size"     => 25,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            'Date'         => 'PostingDate',
            'Description'  => 'Description',
            'Avios points' => 'Miles',
            'Bonus'        => 'Bonus',
            'Tier Credits' => 'Info',
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (empty($this->loyalty)) {
            $this->http->GetURL('https://www.aerlingus.com/api/profile/loyalty');
            $response = $this->http->JsonLog();

            if (isset($response->data[0]->transactionHistory->transactions)) {
                $this->loyalty = $response->data[0]->transactionHistory->transactions;
            } else {
                $this->loyalty = [];
            }
        }// if (empty($this->loyalty))
        $startIndex = count($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate, 'Avios points'));
        // Tier Credits
        $this->http->GetURL('https://www.aerlingus.com/api/profile/tierCredits?cache=true');
        $response = $this->http->JsonLog();

        if (isset($response->data[0]->transactions)) {
            $this->loyalty = $response->data[0]->transactions;
        } else {
            $this->loyalty = [];
        }
        $startIndex = count($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate, 'Tier Credits'));

        // Sort by date
        usort($result, function ($a, $b) {
            $key = 'Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $this->getTime($startTimer);

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function ParsePageHistory($startIndex, $startDate, $type = 'Tier Credits')
    {
        $result = [];

        if (empty($this->loyalty)) {
            $this->logger->error("history not found, something went wrong");

            return $result;
        }// if (empty($this->loyalty))
        $this->logger->debug('Total ' . count($this->loyalty) . ' transactions rows found');

        foreach ($this->loyalty as $item) {
            $postDate = strtotime($item->date);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice('break at date ' . $item->date . ' (' . $postDate . ')');

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Description'] = $item->description;

            if ($type == 'Avios points') {
                if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                    $result[$startIndex]['Bonus'] = empty($item->aviosPoints->debit) ? $item->aviosPoints->credit : -$item->aviosPoints->debit;
                } else {
                    $result[$startIndex]['Avios points'] = empty($item->aviosPoints->debit) ? $item->aviosPoints->credit : -$item->aviosPoints->debit;
                }
                $result[$startIndex]['Tier Credits'] = '-';
            } else {
                $result[$startIndex]['Avios points'] = 0;
                $result[$startIndex]['Tier Credits'] = empty($item->tierCredits->debit) ? $item->tierCredits->credit : -$item->tierCredits->debit;
            }
            $startIndex++;
        }// foreach ($this->loyalty as $item)

        return $result;
    }

    public function postProof()
    {
        $this->logger->notice(__METHOD__);
        $cookies = [
            "ASAFu0iZrzzEbdOeqhlf",
            "AmVmazJWp7KjycWR3JEb5",
            "wnMxIiU1tUebDUVHqdfp",
            "o1EX2d7bEA5Hl63GzGMb",
            "cBhXIPu87saXufSuSp98",
            "05McEEZdKRlPHYoqZ7x4",
        ];
        $data = 'p=%7B%22proof%22%3A%22' . rand(100, 999) . '%3A' . date("UB") . '%' . $cookies[array_rand($cookies)] . '%22%2C%22fp2%22%3A%7B%22userAgent%22%3A%22Mozilla%2F5.0(Macintosh%3BIntelMacOSX10.14%3Brv%3A67.0)Gecko%2F20100101Firefox%2F67.0%22%2C%22language%22%3A%22en-US%22%2C%22screen%22%3A%7B%22width%22%3A1440%2C%22height%22%3A900%2C%22availHeight%22%3A829%2C%22availWidth%22%3A1440%2C%22pixelDepth%22%3A24%2C%22innerWidth%22%3A1440%2C%22innerHeight%22%3A407%2C%22outerWidth%22%3A1440%2C%22outerHeight%22%3A829%2C%22devicePixelRatio%22%3A2%7D%2C%22timezone%22%3A5%2C%22indexedDb%22%3Atrue%2C%22addBehavior%22%3Afalse%2C%22openDatabase%22%3Afalse%2C%22cpuClass%22%3A%22unknown%22%2C%22platform%22%3A%22MacIntel%22%2C%22doNotTrack%22%3A%221%22%2C%22plugins%22%3A%22ShockwaveFlash%3A%3AShockwaveFlash32.0r0%3A%3Aapplication%2Fx-shockwave-flash~swf%2Capplication%2Ffuturesplash~spl%22%2C%22canvas%22%3A%7B%22winding%22%3A%22yes%22%2C%22towebp%22%3Afalse%2C%22blending%22%3Atrue%2C%22img%22%3A%22cdaaf2bf1062dad05bb492a1b2da1a5c9c37e27f%22%7D%2C%22webGL%22%3A%7B%22img%22%3A%22a92b490005e3fb23f4794b4e570dfeacea3ceb7b%22%2C%22extensions%22%3A%22ANGLE_instanced_arrays%3BEXT_blend_minmax%3BEXT_color_buffer_half_float%3BEXT_float_blend%3BEXT_frag_depth%3BEXT_shader_texture_lod%3BEXT_sRGB%3BEXT_texture_compression_rgtc%3BEXT_texture_filter_anisotropic%3BOES_element_index_uint%3BOES_standard_derivatives%3BOES_texture_float%3BOES_texture_float_linear%3BOES_texture_half_float%3BOES_texture_half_float_linear%3BOES_vertex_array_object%3BWEBGL_color_buffer_float%3BWEBGL_compressed_texture_s3tc%3BWEBGL_compressed_texture_s3tc_srgb%3BWEBGL_debug_renderer_info%3BWEBGL_debug_shaders%3BWEBGL_depth_texture%3BWEBGL_draw_buffers%3BWEBGL_lose_context%22%2C%22aliasedlinewidthrange%22%3A%22%5B1%2C1%5D%22%2C%22aliasedpointsizerange%22%3A%22%5B1%2C2047%5D%22%2C%22alphabits%22%3A8%2C%22antialiasing%22%3A%22yes%22%2C%22bluebits%22%3A8%2C%22depthbits%22%3A24%2C%22greenbits%22%3A8%2C%22maxanisotropy%22%3A16%2C%22maxcombinedtextureimageunits%22%3A80%2C%22maxcubemaptexturesize%22%3A16384%2C%22maxfragmentuniformvectors%22%3A1024%2C%22maxrenderbuffersize%22%3A16384%2C%22maxtextureimageunits%22%3A16%2C%22maxtexturesize%22%3A16384%2C%22maxvaryingvectors%22%3A32%2C%22maxvertexattribs%22%3A16%2C%22maxvertextextureimageunits%22%3A16%2C%22maxvertexuniformvectors%22%3A1024%2C%22maxviewportdims%22%3A%22%5B16384%2C16384%5D%22%2C%22redbits%22%3A8%2C%22renderer%22%3A%22Mozilla%22%2C%22shadinglanguageversion%22%3A%22WebGLGLSLES1.0%22%2C%22stencilbits%22%3A0%2C%22vendor%22%3A%22Mozilla%22%2C%22version%22%3A%22WebGL1.0%22%2C%22vertexshaderhighfloatprecision%22%3A23%2C%22vertexshaderhighfloatprecisionrangeMin%22%3A127%2C%22vertexshaderhighfloatprecisionrangeMax%22%3A127%2C%22vertexshadermediumfloatprecision%22%3A23%2C%22vertexshadermediumfloatprecisionrangeMin%22%3A127%2C%22vertexshadermediumfloatprecisionrangeMax%22%3A127%2C%22vertexshaderlowfloatprecision%22%3A23%2C%22vertexshaderlowfloatprecisionrangeMin%22%3A127%2C%22vertexshaderlowfloatprecisionrangeMax%22%3A127%2C%22fragmentshaderhighfloatprecision%22%3A23%2C%22fragmentshaderhighfloatprecisionrangeMin%22%3A127%2C%22fragmentshaderhighfloatprecisionrangeMax%22%3A127%2C%22fragmentshadermediumfloatprecision%22%3A23%2C%22fragmentshadermediumfloatprecisionrangeMin%22%3A127%2C%22fragmentshadermediumfloatprecisionrangeMax%22%3A127%2C%22fragmentshaderlowfloatprecision%22%3A23%2C%22fragmentshaderlowfloatprecisionrangeMin%22%3A127%2C%22fragmentshaderlowfloatprecisionrangeMax%22%3A127%2C%22vertexshaderhighintprecision%22%3A0%2C%22vertexshaderhighintprecisionrangeMin%22%3A24%2C%22vertexshaderhighintprecisionrangeMax%22%3A24%2C%22vertexshadermediumintprecision%22%3A0%2C%22vertexshadermediumintprecisionrangeMin%22%3A24%2C%22vertexshadermediumintprecisionrangeMax%22%3A24%2C%22vertexshaderlowintprecision%22%3A0%2C%22vertexshaderlowintprecisionrangeMin%22%3A24%2C%22vertexshaderlowintprecisionrangeMax%22%3A24%2C%22fragmentshaderhighintprecision%22%3A0%2C%22fragmentshaderhighintprecisionrangeMin%22%3A24%2C%22fragmentshaderhighintprecisionrangeMax%22%3A24%2C%22fragmentshadermediumintprecision%22%3A0%2C%22fragmentshadermediumintprecisionrangeMin%22%3A24%2C%22fragmentshadermediumintprecisionrangeMax%22%3A24%2C%22fragmentshaderlowintprecision%22%3A0%2C%22fragmentshaderlowintprecisionrangeMin%22%3A24%2C%22fragmentshaderlowintprecisionrangeMax%22%3A24%2C%22unmaskedvendor%22%3A%22NVIDIACorporation%22%2C%22unmaskedrenderer%22%3A%22NVIDIAGeForceGT750MOpenGLEngine%22%7D%2C%22touch%22%3A%7B%22maxTouchPoints%22%3A0%2C%22touchEvent%22%3Afalse%2C%22touchStart%22%3Afalse%7D%2C%22video%22%3A%7B%22ogg%22%3A%22probably%22%2C%22h264%22%3A%22probably%22%2C%22webm%22%3A%22probably%22%7D%2C%22audio%22%3A%7B%22ogg%22%3A%22probably%22%2C%22mp3%22%3A%22maybe%22%2C%22wav%22%3A%22probably%22%2C%22m4a%22%3A%22maybe%22%7D%2C%22vendor%22%3A%22%22%2C%22product%22%3A%22Gecko%22%2C%22productSub%22%3A%2220100101%22%2C%22browser%22%3A%7B%22ie%22%3Afalse%2C%22chrome%22%3Afalse%2C%22webdriver%22%3Afalse%7D%2C%22window%22%3A%7B%22historyLength%22%3A1%2C%22hardwareConcurrency%22%3A8%2C%22iframe%22%3Afalse%2C%22battery%22%3Afalse%7D%2C%22location%22%3A%7B%22protocol%22%3A%22https%3A%22%7D%2C%22fonts%22%3A%22Batang%3BCalibri%3BCentury%3BEUROSTILE%3BHaettenschweiler%3BMarlett%3BPMingLiU%3BSimHei%22%2C%22devices%22%3A%7B%22count%22%3A2%2C%22data%22%3A%7B%220%22%3A%7B%22deviceId%22%3A%22pxHnXWfGCLvxlLhtT%2FaA3UyUfZVqpIyKmzsELPQNsfY%3D%22%2C%22kind%22%3A%22videoinput%22%2C%22label%22%3A%22%22%2C%22groupId%22%3A%22iXelrHyY7Y6%2FZRS67frWZAld2Jue8Igu0VueZRr%2F9js%3D%22%7D%2C%221%22%3A%7B%22deviceId%22%3A%22THFJjc428JNhrE6h6KY%2BRmVZnajkRJRRXgB%2B8TolRyE%3D%22%2C%22kind%22%3A%22audioinput%22%2C%22label%22%3A%22%22%2C%22groupId%22%3A%229W963nUwBSdVlpypfQOb0jW9%2F6wv%2FscGxXvtpLEgIQ4%3D%22%7D%7D%7D%7D%2C%22cookies%22%3A1%2C%22setTimeout%22%3A0%2C%22setInterval%22%3A0%2C%22appName%22%3A%22Netscape%22%2C%22platform%22%3A%22MacIntel%22%2C%22syslang%22%3A%22en-US%22%2C%22userlang%22%3A%22en-US%22%2C%22cpu%22%3A%22IntelMacOSX10.14%22%2C%22productSub%22%3A%2220100101%22%2C%22plugins%22%3A%7B%220%22%3A%22ShockwaveFlash32.0.0.207%22%7D%2C%22mimeTypes%22%3A%7B%220%22%3A%22FutureSplashPlayerapplication%2Ffuturesplash%22%2C%221%22%3A%22ShockwaveFlashapplication%2Fx-shockwave-flash%22%7D%2C%22screen%22%3A%7B%22width%22%3A1440%2C%22height%22%3A900%2C%22colorDepth%22%3A24%7D%2C%22fonts%22%3A%7B%220%22%3A%22Calibri%22%2C%221%22%3A%22Cambria%22%2C%222%22%3A%22HoeflerText%22%2C%223%22%3A%22Monaco%22%2C%224%22%3A%22Constantia%22%2C%225%22%3A%22LucidaBright%22%2C%226%22%3A%22Georgia%22%2C%227%22%3A%22Candara%22%2C%228%22%3A%22TrebuchetMS%22%2C%229%22%3A%22Verdana%22%2C%2210%22%3A%22Consolas%22%2C%2211%22%3A%22AndaleMono%22%2C%2212%22%3A%22LucidaConsole%22%2C%2213%22%3A%22LucidaSansTypewriter%22%2C%2214%22%3A%22Monaco%22%2C%2215%22%3A%22CourierNew%22%2C%2216%22%3A%22Courier%22%7D%7D';
        $this->http->PostURL("https://www.aerlingus.com/ahktqsewxjhguuxe.js?PID=93308F68-50BF-3C57-8385-0DE082BCCE5A", $data);
    }

    public function delay()
    {
        $delay = rand(2, 7);
        $this->logger->debug("Delay -> {$delay}");
        sleep($delay);
    }

    private function loginSuccessful($token)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($token)) {
            $this->logger->error("X-XSRF-TOKEN not found");

            return false;
        }

        if ($this->http->FindSingleNode("//h2[contains(text(), 'The request could not be satisfied.')]")) {
            return false;
        }

        $this->http->setDefaultHeader('X-XSRF-TOKEN', $token);
        $this->http->RetryCount = 0;

        if ($this->http->currentUrl() != 'https://www.aerlingus.com/api/profile') {
            $this->http->GetURL("https://www.aerlingus.com/api/profile", $this->headers, 20);
        }
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if (isset($response->data[0]->firstName)) {
            return true;
        }

        return false;
    }

    private function ParseItinerariesV1()
    {
        $this->logger->notice(__METHOD__);

        $this->http->GetURL("https://www.aerlingus.com/api/profile/trips");
        $response = $this->http->JsonLog(null, 3, true);
        $c = 0;

        if (isset($response['data'][0]['trips'])) {
            $trips = $response['data'][0]['trips'];
            $this->logger->debug("Total " . count($trips) . " itineraries were found");

            foreach ($trips as $i => $trip) {
                $pnr = ArrayVal($trip, 'pnr');
                $flights = ArrayVal($trip, 'flights', []);
                $flown = true;

                foreach ($flights as $flight) {
                    if (ArrayVal($flight, 'flown') === false) {
                        $flown = false;
                    }
                }// foreach ($flights as $flight)

                if ($flown && !$this->ParsePastIts) {
                    $this->logger->notice("skip old itinerary: {$pnr}");
                    // del for detect noIts
                    if (isset($trips[$i]) && $trips[$i]['pnr'] == $pnr) {
                        unset($trips[$i]);
                    }

                    continue;
                }// if ($flown)
                $this->ParseItinerary($trip);
                $c++;

                if ($c > 50) {
                    break;
                }
            }// foreach ($response->data[0]->trips as $trip)

            if (empty($trips)) {
                $this->logger->debug('account has only past flights');

                return $this->noItinerariesArr();
            }
        }// if (isset($response->data[0]->trips))
        // no Itineraries
        elseif ($this->http->FindPreg("/(\"data\":\[\{\"trips\":null)/ims")) {
            return $this->noItinerariesArr();
        }

        return [];
    }

    private function ParseItinerary($trip)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();

        $r->general()->confirmation($trip['pnr']);
        // Passengers
        if (isset($trip['passengers'])) {
            $r->general()->travellers(array_map("beautifulName", $trip['passengers']), true);
        }

        // Segments
        $flights = count($trip['flights']);
        $this->logger->debug("Total {$flights} segments were found");

        if (is_array($trip['flights'])) {
            foreach ($trip['flights'] as $flight) {
                $s = $r->addSegment();

                if ($this->http->FindPreg("#^\d+$#", false, $flight['flightNumber'])) {
                    $s->airline()
                        ->noName()
                        ->number($flight['flightNumber']);
                } else {
                    $s->airline()
                        ->number($this->http->FindPreg("#^[A-Z\d]{2}\s*(\d+)$#", false, $flight['flightNumber']))
                        ->name($this->http->FindPreg("#^([A-Z\d]{2})\s*\d+$#", false, $flight['flightNumber']));
                }

                $s->departure()->code($flight['origin']);

                if ($date = str_replace("T", ' ', $flight['departDateTime'])) {
                    $s->departure()->date2($date);
                }

                $s->arrival()->code($flight['destination']);

                if ($date = str_replace("T", ' ', $flight['arriveDateTime'])) {
                    $s->arrival()->date2($date);
                }
                $s->extra()->duration($flight['duration'], false, true);
            }
        }// foreach ($trip->flights as $flight)

        return [];
    }

    private function ParseConfirmationItinerary($response)
    {
        $this->logger->notice(__METHOD__);

        $r = $this->itinerariesMaster->add()->flight();

        if (isset($response->data[0]->bookingReferenceInfo->pnr)) {
            $r->general()->confirmation($response->data[0]->bookingReferenceInfo->pnr);
        }
        // Passengers
        $accountNumbers = [];
        $seats = [];

        if (isset($response->data[0]->travelerInfo->passengerDetails)) {
            foreach ($response->data[0]->travelerInfo->passengerDetails as $passengerDetails) {
                // Passengers
                if (!empty($passengerDetails->passengerName)) {
                    $r->general()->traveller(beautifulName($passengerDetails->passengerName), true);
                }
                // AccountNumbers
                if (!empty($passengerDetails->frequentFlyerNo)) {
                    $accountNumbers[] = $passengerDetails->frequentFlyerNo;
                }
                // Seats
                foreach ($passengerDetails->travelEssentialsInfo->seatsOnSegmentsList->legDetails as $legDetails) {
                    if (!empty($legDetails->displayValue)) {
                        $seats[$legDetails->origin . "-" . $legDetails->destination][] = $legDetails->displayValue;
                    }
                }
            }
        }// foreach ($response->data[0]->travelerInfo->passengerDetails as $passengerDetails)
        // AccountNumbers
        $accountNumbers = array_unique($accountNumbers);

        if (!empty($accountNumbers)) {
            $r->program()->accounts($accountNumbers, false);
        }
        $this->http->Log("Seats: <pre>" . var_export($seats, true) . "</pre>", false);
        // Currency
        if (isset($response->data[0]->fareInfo->currencyCode)) {
            $code = $response->data[0]->fareInfo->currencyCode;
            $currency = $this->currency($code);

            if (!$currency && $code === '&pound;') {
                $currency = 'GBP';
            }
            $r->price()->currency($currency);
        }
        // TotalCharge
        if (isset($response->data[0]->fareInfo->totalPrice)) {
            $r->price()->total($response->data[0]->fareInfo->totalPrice);
        }

        // Segments

        $flights = count($response->data[0]->flightsSummary->journeySummary);
        $this->http->Log("Total {$flights} segments were found");

        foreach ($response->data[0]->flightsSummary->journeySummary as $flight) {
            foreach ($flight->legsSummary as $segments) {
                $s = $r->addSegment();
                $s->airline()
                    ->name($segments->operatingAirlineName)
                    ->number($segments->flightNumber);
                $s->departure()
                    ->code($segments->origin)
                    ->name($segments->originMeaning);

                if ($date = str_replace('T', ' ', $segments->departureDate)) {
                    $date = preg_replace("/:\d{2}\.\d{3}.+/", ' ', $date);
                    $s->departure()->date2($date);
                }
                $s->arrival()
                    ->code($segments->destination)
                    ->name($segments->destinationMeaning);

                if ($date = str_replace('T', ' ', $segments->arrivalDate)) {
                    $date = preg_replace("/:\d{2}\.\d{3}.+/", ' ', $date);
                    $s->arrival()->date2($date);
                }
                $s->extra()->duration($segments->duration);

                // Seats
                $route = $segments->origin . "-" . $segments->destination;

                if (isset($seats[$route])) {
                    $s->extra()->seats($seats[$route]);
                }
            }// foreach ($flight->legsSummary as $segments)
        }// foreach ($response->data[0]->flightsSummary->journeySummary as $flight)

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function seleniumQuestions(): bool
    {
        $this->logger->notice(__METHOD__);

        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);

        /*
        if ($this->Question !== 'We could not send the sms. Please try the recovery code.') {
            $this->http->GetURL($this->State['CurrentURL']);
        }
        */

        // get cookies from curl
        $allCookies = array_merge($this->http->GetCookies(".aerlingus.com"), $this->http->GetCookies(".aerlingus.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.aerlingus.com"), $this->http->GetCookies("www.aerlingus.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("accounts.aerlingus.com"), $this->http->GetCookies("accounts.aerlingus.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".accounts.aerlingus.com"), $this->http->GetCookies(".accounts.aerlingus.com", "/", true));

        $selenium = clone $this;
        $token = null;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $resolutions = [
                //                [1152, 864],
                [1280, 720],
                //                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useChromium();
            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->setKeepProfile(true);
            */
            $selenium->disableImages();

            // It breaks everything
            $selenium->usePacFile(false);
//            $selenium->useCache();

            $selenium->http->saveScreenshots = true;

            $this->logger->debug("open window...");
            $selenium->http->start();
            $selenium->Start();
            $this->logger->debug("open url...");

            $url = $this->State['CurrentURL'];

            /*
            if ($this->Question === 'We could not send the sms. Please try the recovery code.') {
            */
            $url = "https://www.aerlingus.com/404";
            /*
            }
            */

            $selenium->http->GetURL($url);

            $selenium->driver->manage()->deleteAllCookies();
            $this->logger->debug("set cookies...");

            foreach ($this->State['Cookies'] as $cookie) {
                $this->logger->debug("{$cookie['name']}={$cookie['value']}, {$cookie['domain']}");

                try {
                    $selenium->driver->manage()->addCookie(['name'   => $cookie['name'], 'value'  => $cookie['value'], 'domain' => ".aerlingus.com"]);
                } catch (UnableToSetCookieException $e) {
                    $this->logger->error("UnableToSetCookieException exception: " . $e->getMessage());
                }
            }

            $selenium->http->GetURL($this->State['CurrentURL']);

            $codeInput = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'code' or @name = 'recoveryCode']"), 5);
            $sendCode = $selenium->waitForElement(WebDriverBy::xpath("//button[@name = 'action' or @class = 'auth0-lock-submit']"), 0);
            $this->savePageToLogs($selenium);

            $this->hideOverlay($selenium);
            $this->savePageToLogs($selenium);

//            if ($this->Question === 'We could not send the sms. Please try the recovery code.') {
//                sleep(5);
//
//                $selenium->driver->executeScript('
//                    function triggerInput(selector, enteredValue) {
//                        let input = document.querySelector(selector);
//                        input.dispatchEvent(new Event(\'focus\'));
//                        input.dispatchEvent(new KeyboardEvent(\'keypress\',{\'key\':\'a\'}));
//                        let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, \'value\').set;
//                        nativeInputValueSetter.call(input, enteredValue);
//                        let inputEvent = new Event("input", { bubbles: true });
//                        input.dispatchEvent(inputEvent);
//                    }
//                    triggerInput(\'input[name="code"]\', \'' . $answer . '\');
//                ');
//
//                $this->savePageToLogs($selenium);
//                $selenium->driver->executeScript('document.querySelector(\'button.auth0-lock-submit\').click();');
//
//                sleep(5);
//                $this->savePageToLogs($selenium);
//
//                if ($error = $this->http->FindSingleNode('//span[contains(text(), "The verification code you entered is invalid.")]')) {
//                    $this->holdSession();
//                    $this->AskQuestion($this->Question, $error, "Question");
//
//                    return false;
//                }
//            } else {
            if (
                empty($codeInput)
                || empty($sendCode)
            ) {
                $this->logger->error("something went wrong");

                return false;
            }

            $codeInput->clear();
            $codeInput->sendKeys($answer);
            $this->savePageToLogs($selenium);

            $sendCode->click();

            sleep(5);
            $this->savePageToLogs($selenium);

            $resultXpath = '
                //div[contains(@class, "user-profile-aerclub-card")]
                | //p[@data-test-id = "test_membership_number"]
                | //div[@id = "scroll_messages"])[normalize-space(text())!=""]
                | //p[contains(@class, "uil-errorRed")]
                | //li[contains(text(), "The code you entered is invalid.")]
                | //span[contains(text(), "The verification code you entered is invalid.")]
                | //span[contains(text(), "The recovery code must have 24 characters made up of letters and numbers.")]
            ';
            $selenium->waitForElement(WebDriverBy::xpath($resultXpath), 10);
            $this->savePageToLogs($selenium);

            if ($error = $selenium->waitForElement(WebDriverBy::xpath('
                    //li[contains(text(), "The code you entered is invalid.")]
                    | //span[contains(text(), "The verification code you entered is invalid.")]
                    | //span[contains(text(), "The recovery code must have 24 characters made up of letters and numbers.")]
                '), 0)
            ) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error->getText(), "Question");

                if ($this->getWaitForOtc()) {
                    $this->sendNotification("2fa - refs #20616 // RR");
                }

                return false;
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] == 'XSRF-TOKEN') {
                    $token = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if (
                $token
                && !in_array($selenium->http->currentUrl(), [
                    'https://www.aerlingus.com/html/login.html',
                    'https://www.aerlingus.com/html/resend-verification-email.html',
                ])
            ) {
                $this->http->setDefaultHeader('X-XSRF-TOKEN', $token);
                $this->http->GetURL($selenium->http->currentUrl());
                $this->http->GetURL("https://www.aerlingus.com/api/profile", $this->headers);
                $response = $this->http->JsonLog();
            }
        } catch (UnknownServerException $e) {
            $this->logger->error("UnknownServerException: " . $e->getMessage());
            $this->logger->debug("Need to change ff version");
        } finally {
            $selenium->http->cleanup(); //todo
        }

        return $this->loginSuccessful($token);
    }

    private function hideOverlay($selenium)
    {
        $this->logger->notice(__METHOD__);
        $accept = $selenium->waitForElement(WebDriverBy::id('onetrust-accept-btn-handler'), 2);
        $this->savePageToLogs($selenium);

        if ($accept) {
            $selenium->driver->executeScript('var divsToHide = document.getElementsByClassName("onetrust-pc-dark-filter");
                for(var i = 0; i < divsToHide.length; i++) {
                    divsToHide[i].style.display = "none";
                } 
                var overlay2 = document.getElementById("onetrust-banner-sdk"); if (overlay2) overlay2.style.display = "none";');
        }
    }
}
