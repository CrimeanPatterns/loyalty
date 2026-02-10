<?php

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;
use AwardWallet\Schema\Parser\Common\Event;

class TAccountCheckerBookingSelenium extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    use PriceTools;

    public const LOGOUT_LINK_XPATH = "//span[contains(@class, 'header_name user_firstname') or @id = 'profile-menu-trigger--title']";
    /**
     * @var HttpBrowser
     */
    public $browser;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        /*
        $this->useChromium();
        */
        //$this->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);
        $this->useFirefox(SeleniumFinderRequest::FIREFOX_84);
        $this->usePacFile(false);
        $this->http->SetProxy($this->proxyDOP(Settings::DATACENTERS_USA));
        $this->http->saveScreenshots = true;
        $this->useCache();
        $this->keepCookies(false);
        $this->disableImages();
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);

        try {
            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } catch (UnknownServerException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        try {
            if ($currentUrl == 'https://www.booking.com/Apps/Dashboard?auth_success=1') {
                $this->http->GetURL("https://www.booking.com/Apps/Dashboard");
            } else {
                $this->http->GetURL("https://secure.booking.com/myreservations.en-us.html");
            }
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');
        } catch (UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        try {
            $cookies = $this->driver->manage()->getCookies();
        } catch (NoSuchDriverException | NoSuchWindowException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 0);
        }

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->curl = true;

        $this->browser->LogHeaders = true;
        //$this->browser->setProxyParams($this->http->getProxyParams());
        $this->browser->GetURL($currentUrl);
    }

    public function IsLoggedIn()
    {
        $this->http->GetURL('https://secure.booking.com/myreservations.en-us.html');

        if ($this->loginSuccessful(5, true)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());

            throw new CheckRetryNeededException(3);
        }

        try {
            $this->http->GetURL('https://secure.booking.com/myreservations.en-us.html');
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";

            if (strstr($e->getMessage(), 'Timeout loading page after')) {
                throw new CheckRetryNeededException(3);
            }
        }

        if ($signIn = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Sign in')]"), 10)) {
            $signIn->click();
        }

        $form = "//form[contains(@class, 'user_access_form_js') and not(contains(@class, 'hidden'))]";
        $formNew = "//form[contains(@class, 'nw-signin')]";
        $login = $this->waitForElement(WebDriverBy::xpath($form . '//input[@name = "username"] | ' . $formNew . '//input[@id = "loginname" or @name = "username"]'), 10);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath($formNew . '//input[@id = "loginname" or @name = "username"]'), 0)) {
            $login->sendKeys($this->AccountFields['Login']);
            $btn = $this->waitForElement(WebDriverBy::xpath($formNew . '//button[@type = "submit"]'), 0);

            if (!$btn) {
                $this->logger->error("btn not found");

                return $this->checkErrors();
            }

            $this->saveResponse();

            try {
                $btn->click();
            } catch (UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);

                $this->driver->executeScript("document.querySelector('button[type = \"submit\"]').click();");
            }

            $pass = $this->waitForElement(WebDriverBy::xpath($formNew . '//input[@id = "password"]'), 10);
            $this->saveResponse();

            try {
                $btn = $this->waitForElement(WebDriverBy::xpath($formNew . '//button[@type = "submit"]'), 0);
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(3, 0);
            }

            if (!$btn || !$pass) {
                $this->logger->error("password field or btn not found");
                $this->saveResponse();

                try {
                    $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
                } catch (NoSuchDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage());

                    throw new CheckRetryNeededException(3, 0);
                }

                // Make sure the email address you entered is correct.
                // Looks like there isn't an account associated with this email address.
                if ($message = $this->waitForElement(WebDriverBy::xpath("
                        //div[contains(text(), 'Make sure the email address you entered is correct.')]
                        | //div[contains(text(), \"Looks like there isn't an account associated with this email address.\")]
                        | //div[contains(text(), 'Too many attempts – try again later.')]
                    "), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->waitForElement(WebDriverBy::xpath('//h1[
                        contains(text(), "Account disabled")
                        or contains(text(), "Account locked")
                    ]'), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
                }

                if ($message = $this->waitForElement(WebDriverBy::xpath("
                        //div[contains(text(), 're having technical difficulties – try again later.')]
                        | //div[contains(text(), 'Sorry, too many attempts')]
                    "), 0)
                ) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }

                // Just confirm your email, then we'll send you a link to reset your password
                if ($this->waitForElement(WebDriverBy::xpath('//p[
                        contains(text(), "Just confirm your email, then we\'ll send you a link to reset your password")
                        or contains(text(), "You haven\'t signed in with this email before. To log in with this email, you need to verify it.")
                        or contains(text(), "You\'re trying to sign in to an account that doesn\'t have a password. To sign in, verify your email.")
                        or contains(text(), "Your password must be a minimum of 10 characters and include at least one uppercase letter, one lowercase letter, and one number.")
                    ]
                    | //h1[contains(text(), "Create a password for your new account")]
                    | //h1[contains(text(), "Create password")]
                    '), 0)
                ) {
                    $this->throwProfileUpdateMessageException();
                }
                // Something went wrong – please try again later.
                if ($message = $this->http->FindSingleNode('//div[contains(text(), "Something went wrong – please try again later.")]')) {
                    throw new CheckRetryNeededException(3, 5, $message);
                }

                if ($question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'We just emailed a verification link to')]/strong"), 0)) {
                    $this->saveResponse();
                    $this->holdSession();
                    $this->AskQuestion("Please copy-paste an authorization link which was sent to your email {$question->getText()} to continue the authentication process.", null, "VerificationViaLink"); /*review*/

                    return false;
                }// if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Two Factor Authentication')]"), 0))

                return $this->checkErrors();
            }
            $pass->sendKeys($this->AccountFields['Pass']);
            sleep(1);
            $btn->click();

            return true;
        }
        $pass = $this->waitForElement(WebDriverBy::xpath($form . '//input[@name = "password"]'), 0);
        $btn = $this->waitForElement(WebDriverBy::xpath($form . '//input[@type = "submit"]'), 0);

        $this->saveResponse();
        // new form
        if ((!$pass || !$btn) && $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Become a member to access secret deals –')]"), 0)
            && ($signInBtn = $this->waitForElement(WebDriverBy::xpath("//a[div[contains(@class, 'sign_in_wrapper') and contains(., 'Sign in')]]"), 0))) {
            $signInBtn->click();
            $login = $this->waitForElement(WebDriverBy::xpath($form . '//input[@name = "username"]'), 10);
            $this->saveResponse();
            $pass = $this->waitForElement(WebDriverBy::xpath($form . '//input[@name = "password"]'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath($form . '//input[@type = "submit"]'), 0);
        }

        if (!$pass || !$btn) {
            $this->logger->error("something went wrong");
            $this->saveResponse();
            // Sign In tab was not activated
            if ($this->http->FindSingleNode("//form[contains(@class, ' user_access_form_js')]")) {
                $this->DebugInfo = 'Sign In tab was not activated';

                throw new CheckRetryNeededException(4, 10);
            }

            // ff 84 workaround
            try {
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 0);
            }

            return $this->checkErrors();
        }

        if ($login) {
            $login->sendKeys($this->AccountFields['Login']);
        }
        $pass->sendKeys($this->AccountFields['Pass']);

        $this->driver->executeScript("$('.user_access_form_js .bootstrapped-label').click();");
        sleep(1);
        $this->driver->executeScript("$('.user_access_form_js input[value=\"Sign in\"]').focus();");
        $btn->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("
                //h1[contains(text(), '503 Service Unavailable')]
            ")
        ) {
            throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
        }

        return false;
    }

    public function Login()
    {
        sleep(2);

        if ($this->loginSuccessful(10)) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-displayed')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Please enter a valid email address.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'alert-error') and @style = 'display: block;']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The email and password combination you entered doesn't match.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "The email and password combination you entered doesn\'t match.")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Your password is incorrect – try again or use a verification link")]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[
                contains(@role, "alert")
                and @id = "password-description"
                and (
                    contains(text(), "This password is incorrect")
                    or contains(text(), "The email and password combination entered doesn\'t match.")
                    or contains(text(), "Your password is incorrect")
                )
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@role, "alert") and @id = "password-description"]')) {
            $this->DebugInfo = $message;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@role, "alert") and contains(@id, "password-") and contains(text(), "re having technical difficulties – try again later.")] | //p[contains(text(), "We\'re having technical difficulties – try again later.")] | //div[contains(text(), "Sign in failed – try again later")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Two Factor Authentication
        if ($this->waitForElement(WebDriverBy::xpath('
                //*[self::h1 or self::h3 or self::h4][contains(text(), "Two Factor Authentication")]
                | //h1[contains(text(), "Verify that it\'s you")]
            '), 0)
        ) {
            $this->saveResponse();

            return $this->processSecurityCheckpoint();
        }// if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Two Factor Authentication')]"), 0))

        // Sign In was not success
        if ($form = $this->http->FindSingleNode("//form[contains(@class, ' user_access_form_js')]")) {
            $this->logger->debug($form);
            $this->DebugInfo = 'Sign In was not success';
            //throw new CheckRetryNeededException(2, 10);

            // hard code
            if ($this->AccountFields['Login'] == 'frank@weltlichs.de') {
                throw new CheckException("You entered an email address/password combination that doesn't match", ACCOUNT_INVALID_PASSWORD);
            }

            if (in_array($this->AccountFields['Login'], [
                'admin@hannahoreilly.com',
                'crimasito@gmail.com',
            ])
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        }
        // hard code
        if (in_array($this->AccountFields['Login'], [
            'crimasito@gmail.com',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // This page isn’t working - does not authorize on the site
        if (
            $this->AccountFields['Login'] == 'covrefilho@gmail.com' // AccountID: 5367099
            && $this->http->FindSingleNode("//span[contains(text(), 'This page isn’t working')]")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
             'wodair07@hotmail.com',
            'drslobo@gmx.de',
            'jpatto9809@aol.com',
            'mail@graememcmullen.com',
            AccountID: 5022047
        */
        if ($this->http->FindSingleNode('//div[contains(@class, "bui-has-error")]/label[contains(text(), "Booking.com password")]')) {
            throw new CheckException("You entered an email address/password combination that doesn't match", ACCOUNT_INVALID_PASSWORD); //hard code: no errors on the website only highlighting
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Confirm your age")]')) {
            $this->throwProfileUpdateMessageException();
        }

        // Something went wrong – please try again later.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Something went wrong – please try again later.")]')) {
            throw new CheckRetryNeededException(3, 5, $message);
        }

        $logout = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), 10);
        $this->saveResponse();

        if ($logout || $this->http->FindNodes("//input[@name='logout']/@value")) {
            return true;
        }

        if ($this->http->FindNodes("//span[
                contains(text(), 'My profile')
                or contains(text(), 'Good afternoon, ')
                or contains(text(), 'Good morning, ')
                or contains(text(), 'Good evening, ')
                or contains(text(), 'Good nigth, ')
                or contains(text(), 'Buongiorno, ')
                or contains(text(), 'Bom dia, ')
            ]")
            || $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(text(), "View and manage all bookings linked to your account.")]
                    | //a[contains(text(), "My Dashboard")]
                '), 0)
        ) {
            $this->http->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");

            if ($this->loginSuccessful(5)) {
                return true;
            }
        }

        if ($switch = $this->waitForElement(WebDriverBy::xpath('//a[contains(., "Switch to Booking.com Global")]'), 0)) {
            $switch->click();

            if ($this->loginSuccessful(5)) {
                return true;
            }
            // sometimes click not working (rare cases)
            $this->sendNotification("Switch to Booking.com Global // RR");

            throw new CheckRetryNeededException(2, 1);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Account locked")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode('//div[
                contains(text(), "The email and password combination entered doesn\'t match.")
                or contains(text(), "Too many attempts – try again later")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('//div[contains(text(), "Oops! An error has occurred.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        try {
            $currentUrl = $this->http->currentUrl();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(2, 5);
        }

        $this->logger->debug("[Current URL]: {$currentUrl}");

        if (
            $this->AccountFields['Login'] == 'anthonybiddulph@gmail.com'// AccountID: 5371360
            && in_array($currentUrl, [
                'https://admin.business.booking.com/direct-sso',
                'https://admin.business.booking.com/migration',
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->FindSingleNode('//body/pre[contains(text(), "Internal Server Error")]')
            && $currentUrl == 'https://secure.booking.com/myreservations.en-us.html?auth_success=1'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        // A text message with a 6-digit verification code was just sent to the phone number associated with this account.
        $q = $this->waitForElement(WebDriverBy::xpath("
            //label[@class = 'auth_next_step_sms_verification_form__code_sent']
            | //p[contains(text(), 'We sent a verification code to your phone.')]
            | //p[contains(text(), 'We sent a verification code to the phone number attached to your account.')]
            | //p[contains(text(), 'Enter the verification code generated by your')]
        "), 0);

        if ($q) {
            $question = $q->getText();
        }

        if (isset($question) && strstr($question, 'We sent a verification code to ') && strstr($question, 'phone')) {
            $question = "We sent a verification code to your phone. Please enter this code in the box below.";
        }
        $code = $this->waitForElement(WebDriverBy::xpath('//input[@name = "confirmation_code"] | //input[@id = "sms_code"]'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath('//input[contains(@class, "auth_next_step__sms_send_verification_code_submit")] | //form[contains(@class, "form-sms")]//button[@type = "submit"] | //button[contains(., "Verify and sign in")]'), 0);
        $this->saveResponse();

        if (!isset($question) || !$code || !$button) {
            if ($this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                "), 0)
            ) {
                return $this->LoadLoginForm() && $this->Login();
            }

            return false;
        }
        $this->holdSession();

        if ($question && !isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, "Question");

            return false;
        }
        $code->clear();
        $code->sendKeys($this->Answers[$question]);
        unset($this->Answers[$question]);
        $this->logger->debug("click button...");
        $button->click();
        $error = (
            // Enter a valid verification code
            $this->waitForElement(WebDriverBy::xpath("//div[
                contains(text(), 'Enter a valid verification code')
                or contains(text(), 'Verification code needs to contain 6 digits')
            ]"), 5)
            /*
            // We're having technical difficulties – try again later.
            ?: $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'We\'re having technical difficulties – try again later.')]"), 5)
            */
        );
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($question, $error->getText(), "Question");

            return false;
        }
        $this->logger->debug("success");
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath("//span[@class = 'header_name user_firstname']"), 10);
        $this->saveResponse();

        return true;
    }

    public function processVerificationViaLink()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question: verification via Link', ['Header' => 3]);

        if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL)) {
            unset($this->Answers[$this->Question]);
            $this->AskQuestion($this->Question, "The link you entered seems to be incorrect", "VerificationViaLink"); /*review*/

            return false;
        }// if (!filter_var($this->Answers[$this->Question], FILTER_VALIDATE_URL))
        $this->http->GetURL($this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);

        $error = $this->waitForElement(WebDriverBy::xpath("//*[self::span or self::p][
            contains(text(), 'This verification link is invalid or has already been used')
        ]"), 5);
        $this->saveResponse();

        if ($error) {
            $this->logger->notice("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), "VerificationViaLink");

            return false;
        }

        if ($signMeIn = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Yes, sign me in')]"), 0)) {
            $signMeIn->click();
        }

        $this->logger->debug("success");
        // Access is allowed
        $this->waitForElement(WebDriverBy::xpath("//span[@class = 'header_name user_firstname']"), 10);
        $this->saveResponse();

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        /*
        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }
        */

        if ($step == "Question") {
            return $this->processSecurityCheckpoint();
        } elseif ($step == "VerificationViaLink") {
            return $this->processVerificationViaLink();
        }

        return false;
    }

    public function switchAccount($primaryType = 'business')
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("switch to {$primaryType} account");
        $secondAccountLink = $this->browser->FindSingleNode("//a[contains(text(), 'Switch to the') and contains(text(), 'account')]/@href");
        $userid = $this->browser->FindPreg("/\"{$primaryType}UserId\":\s*\"?([^\,\"]+)\"?/"); // fix for '0'

        if (
            isset($userid)
            || $this->browser->FindPreg("/b_connected_user_accounts:\s*(\[.+\}\])\,/")
        ) {
            $secondAccountLinkNew = "https://secure.booking.com/profileswitch.en-us.html";
            $this->logger->debug("set secondAccountLinkNew -> '{$secondAccountLinkNew}'");
        }

        if (!$secondAccountLink && empty($secondAccountLinkNew)) {
            $secondAccountLinkNew = $this->browser->FindPreg("/b_profile_switch_url:\s*'([^\']+)/");
            $this->logger->debug("parse secondAccountLinkNew -> '{$secondAccountLinkNew}'");
        }

        if (empty($secondAccountLink) && empty($secondAccountLinkNew)) {
            $this->logger->notice("switcher not found");

            return;
        }
        // new switcher
        if (!empty($secondAccountLinkNew)) {
            $this->logger->debug("new switcher");
            $this->browser->NormalizeURL($secondAccountLinkNew);
            $this->browser->FormURL = $secondAccountLinkNew;
            $this->browser->Form = [];
            $token = (
                $this->browser->FindPreg("/, token\s*=\s*'(.+?)', input/") ?:
                $this->browser->FindPreg("/\"csrfToken\":\"([^\"]+)/") ?:
                $this->browser->FindPreg("/'b_csrf_token':\s*'(.+?)'/")
            );

            if (!$token) {
                $this->sendNotification('switch account token not found // MI');
            }
            $this->browser->SetInputValue("bhc_csrf_token", $token);
            $accounts = $this->http->JsonLog($this->browser->FindPreg("/b_connected_user_accounts:\s*(\[.+\}\])\,/"));
            $account = $this->browser->FindPreg("/\"{$primaryType}UserId\":\s*\"?([^\,\"]+)\"?\,/");

            if (is_array($accounts)) {
                foreach ($accounts as $account) {
                    if ($account->b_active == 0 && $account->b_type == $primaryType) {
                        $this->browser->SetInputValue("switch_to_user_id", $account->b_user_id);
                        $type = $account->b_type;

                        if ($type == 'business') {
                            $this->browser->SetInputValue("redirect_url", $this->browser->FindPreg('/fe_this_url_travel_purpose_business:\s*"([^\"]+)/'));
                        } else {
                            $this->browser->SetInputValue("redirect_url", $this->browser->FindPreg('/fe_this_url_travel_purpose_leisure:\s*"([^\"]+)/'));

                            if (in_array($this->AccountFields['Login'], [
                                'jtran411@gmail.com', // AccountID: 2904191
                                'travel@radoslavlorkovic.com', // AccountID: 3299939
                                'relder110152@yahoo.com', // AccountID: 5456564
                                'osuhami@gmail.com', // AccountID: 4815409
                                'sancheztallone@hotmail.com', // AccountID: 2675791
                                'steve@jumbocruiser.com', // AccountID: 3482677
                                'hannesvongoesseln@gmail.com', // AccountID: 4604218
                                'alesv8@gmail.com', // AccountID: 4821221
                                'roberto.appel@utschbrasil.com', // AccountID: 5514525
                                'kiwi@macsportstravel.com', // AccountID: 4066597
                                'ecscesar@hotmail.com', // AccountID: 5528687
                                'sbhavin50@gmail.com', // AccountID: 4674972
                                'chokous@gmail.com', // AccountID: 4374483
                                'lilianalevintza@gmail.com', // AccountID: 4606794
                                'vinicius.augustoaz@gmail.com', // AccountID: 5445772
                                'anthonybiddulph@gmail.com', // AccountID: 5371360
                                'mauriciobbastos@gmail.com', // AccountID: 4827401
                                'bernardocavalcante@hotmail.com', // AccountID: 4696986
                                'guillaume@milesaddict.com', // AccountID: 4947644
                            ])
                                || $this->browser->currentUrl() == 'https://admin.business.booking.com/direct-sso'
                            ) {
                                $this->browser->SetInputValue("redirect_url", preg_replace("/^https:\/\/www.booking.com\/index\.html/", "https://secure.booking.com/myreservations.html", $this->browser->Form['redirect_url']));
                            }
                        }
                        $this->browser->PostForm();

                        break;
                    }// if ($account->b_active == 0 && $account->b_type == $primaryType)
                }// foreach ($accounts as $account)
            }// if (is_array($accounts))
            elseif (isset($account)) {
                $this->browser->SetInputValue("switch_to_user_id", $account);

                if ($primaryType == 'business') {
                    $this->browser->SetInputValue("redirect_url", "https://secure.booking.com/company/search.en-us.html?sb_travel_purpose=business");
                } else {
                    $this->browser->SetInputValue("redirect_url", "https://www.booking.com/index.en-us.html");
                }
                $this->browser->PostForm();
            }
        }// if (!empty($secondAccountLinkNew))
        else {
            $this->browser->NormalizeURL($secondAccountLink);
            $this->browser->GetURL($secondAccountLink);
        }
    }

    public function Parse()
    {
        $this->DebugInfo = null;
        // use curl
        $this->parseWithCurl();

        if (str_contains($this->browser->currentUrl(), 'https://account.booking.com/sign-in?op_token=')) {
            throw new CheckRetryNeededException();
        }

        if (strstr($this->browser->currentUrl(), 'https://account.booking.com/sign-in?op_token=')) {
            $this->browser->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
            $this->switchAccount("personal");
        }

        $this->switchAccount("personal");

        if ($this->browser->currentUrl() == 'https://admin.business.booking.com/direct-sso') {
            $this->browser->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
            $this->switchAccount("personal");
        }

        // Name
        $this->SetProperty("Name", beautifulName(trim(preg_replace(['/^Hi /', '/!$/'], "", $this->browser->FindSingleNode("//a[contains(@class, 'popover_trigger')]//span[contains(@class, 'firstname')]")) . ' ' . $this->browser->FindSingleNode("//a[contains(@class, 'popover_trigger')]//span[contains(@class, 'lastname')]"))));

        $this->parseProperties();

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        /*
        // refs #14675
        $this->browser->FilterHTML = false;
        $xBookingAid = $this->browser->FindPreg("/'X-Booking-AID'\s*:\s*'([^\']+)/");
        $bLabel = $this->browser->FindPreg("/b_label\s*:\s*'([^\']+)/");
        $sid = $this->browser->FindPreg("/b_sid\s*:\s*'([^\']+)/");
        $this->browser->GetURL("https://www.booking.com/index.html?aid={$xBookingAid};label={$bLabel};sid={$sid};sb_travel_purpose=leisure");

        if ($this->browser->FindSingleNode("//div[contains(@class, 'book-challenge-roadtrip__progress')]/i/@class", null, true, "/gesprite/")) {
            $this->SetProperty("Status", "Genius");
        } elseif ($this->browser->FindSingleNode("
                //p[contains(@class, 'genius_member_text')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //div[contains(@class, 'genius_member_text')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //span[contains(@class, 'user_avatar')]//svg[contains(@class, 'genius-genius-logo')]/@class
                | //span[contains(@class, 'user_avatar')]//svg[contains(@class, '-genius-levels-logo')]/@class
                | //span[contains(@class, 'user_name_block')]//*[contains(@class, 'genius_logo_profile_split')]/@class
            ")
        ) {
            $this->SetProperty("Status", "Genius");
        } else {
            $this->SetProperty("Status", $this->browser->FindSingleNode('
                //span[(@class = "user_account_indication") and contains(text(), "Genius Level")]
                | //span[(@id = "profile-menu-trigger--content")]//span[contains(text(), "Genius Level")]
            '));
        }

        if (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->browser->FindPreg("/b_user_emails:\s*\[\s*\{\s*email: \"{$this->AccountFields['Login']}/")
            && $this->browser->FindPreg("/b_reg_user_full_name: \"\",/")
            && !empty($this->Properties['Status'])
        ) {
            $this->SetBalanceNA();
        }
        */

        // Balance - Book
        if (count($this->browser->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'ge_challenge_check')]")) > 0) {
            $this->SetBalance(count($this->browser->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'e_challenge_check-booked')]")));
            $deadline = $this->browser->FindPreg('/"deadline\":\"([^\"]+)/ims');

            if ($deadline && strtotime($deadline)) {
                $this->SetExpirationDate(strtotime($deadline));
            }
        }// if (count($this->browser->FindNodes("//ul[contains(@class, 'ge_challenge_roadtrip')]/li[contains(@class, 'ge_challenge_check')]")) > 0)
        $this->browser->FilterHTML = true;

        // refs #18373
        $this->logger->info('My Rewards', ['Header' => 3]);
        $this->browser->GetURL("https://secure.booking.com/rewardshub/overview.en-us.html");

        $this->parseProperties();

        $activities = $this->browser->XPath->query('//div[contains(text(), "Reward activity")]/following-sibling::div[1]/div');
        $this->logger->debug("Total " . $activities->length . " rewards were found");

        foreach ($activities as $activity) {
//            $status = $this->browser->FindSingleNode("(.//div[contains(., 'Paid') and span])[1]", $activity);
            $exp = $this->browser->FindSingleNode('.//div[span[contains(text(), "Expires:")]]', $activity, true, "/:\s*(.+)/");
            $token = $this->browser->FindSingleNode('.//div[span[contains(text(), "Details:")]]', $activity, true, "/Number\)\s*(\d+)/");

            if (/*in_array($status, ['cancelled', 'rejected']) || */ strtotime($exp) < time()) {
//                $this->logger->debug("skip {$token} / {$status} / {$exp}");
                $this->logger->debug("skip {$token} / {$exp}");

                continue;
            }

            /*
            if (!in_array($status, ['sent', 'promised', 'action_needed', 'onhold', 'transaction_pending'])) {
                $this->sendNotification("rewards [{$status}]");
            }
            */

            if ($token) {
                $displayName = "Reward (ARN #{$token})";
            } else {
                $displayName = "Reward: " . $this->browser->FindSingleNode(".//span[contains(text(), '$') or contains(text(), '€')]", $activity);
            }

            $this->AddSubAccount([
                "Code"           => "bookingMyRewards" . md5($token) . strtotime($exp),
                "DisplayName"    => $displayName,
                "Balance"        => $this->browser->FindSingleNode(".//span[contains(text(), '$') or contains(text(), '€')]", $activity),
                "Currency"       => $this->browser->FindSingleNode(".//span[contains(text(), '$') or contains(text(), '€')]", $activity, true, "/([^\d]+)/"),
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($activities as $activity)

        $this->logger->info('My Wallet', ['Header' => 3]);
        $walletData = $this->browser->FindPreg('#<script data-capla-namespace="b-booking-pay-rewards-and-wallet-caplacbfISBMG"[^>]+>([^<]+)#');
//        $this->logger->debug(var_export($walletData, true), ['pre' => true]);
        $response = $this->browser->JsonLog($walletData, 3, false, "earnedAmountPretty");

        $myWallet = $response->ROOT_QUERY->rewardswallet->{'rewards({"input":{"includeTransactions":true,"pagination":{"offset":0,"rowsPerPage":10}}})'}->earnedAmountPretty ?? null;

        // Balance - Credits // refs #21937
        $this->SetProperty("CombineSubAccounts", false);
        $this->SetBalance($myWallet);
        // Currency
        $this->SetProperty("Currency", $this->http->FindPreg("/([^\d]+)/", false, $myWallet));
    }

    public function ParseItineraries()
    {
        $result = $this->parsePersonalItineraries();
        //$result = array_merge($result, $this->parseBusinessItineraries());

        return $result;
    }

    public function submitBookingForm($res, $caption)
    {
        $this->logger->notice(__METHOD__);

        if ($this->browser->ParseForm('my_bookings_order')) {
            $this->logger->info($caption, ['Header' => 3]);
            $this->logger->notice(">>> Show only {$caption}");
            $this->browser->Form = [];
            $this->browser->SetInputValue("res", $res);
            $this->browser->PostForm();

            return true;
        }// if ($this->browser->ParseForm($formName))
        else {
            $this->logger->error('Failed to parse reservation form');
        }

        return false;
    }

    public function parseCancelledReservations($cancelled)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        if (!$this->submitBookingForm($cancelled, 'Cancelled reservations')) {
            return $result;
        }

        $canceledIts = $this->browser->XPath->query('//div[contains(@class, "js-booking_block") and //div[contains(@class, "mb-block--cancelled")] and //span[(contains(@class, "mb-positive-text-fc") or contains(@class, "mb-negative-text")) and contains(text(), "Canceled")]]');
        $this->logger->debug("Total {$canceledIts->length} canceled itineraries were found");

        foreach ($canceledIts as $canceled) {
            $confNo = $this->browser->FindSingleNode('.//div[contains(@class, "mb-block__book-number")]/b[@class = "marginRight_5"]', $canceled);

            if (!$confNo) {
                continue;
            }
            $this->sendNotification("Canceled its // RR");
            $result[] = [
                'Kind'               => 'R',
                'ConfirmationNumber' => $confNo,
                'Cancelled'          => true,
            ];
        }// foreach ($canceledIts as $canceled)
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function ParseCancelled($conf)
    {
        $this->logger->notice(__METHOD__);

        $result = [];
        $this->browser->FilterHTML = false;
        // ConfirmationNumber
        $result['Kind'] = "R";
        $result['ConfirmationNumber'] = $conf;
        $result['Status'] = $this->browser->FindSingleNode("//text()[normalize-space()='Your booking was']/following::text()[normalize-space()!=''][1]");
        $result['Cancelled'] = true;

        $this->logger->info('Parse Itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);

        // HotelName
        $result['HotelName'] = $this->browser->FindSingleNode("//div[@class='cancelled-view__hotel-name']/a");
        // CheckInDate
        $result['CheckInDate'] = strtotime($this->browser->FindSingleNode("//div[normalize-space()='Check-in']/following-sibling::div[1]"));
        // CheckOutDate
        $result['CheckOutDate'] = strtotime($this->browser->FindSingleNode("//div[normalize-space()='Check-out']/following-sibling::div[1]"));
        // Rooms
        $result['Rooms'] = $this->browser->FindSingleNode("//div[normalize-space()='Stay Details' or normalize-space()='Stay details' ]/following-sibling::div[1]",
            null, true, "/(\d+) rooms?/");

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    public function parseTime($timeStr)
    {
        $res = null;

        foreach (["/(\d{4}\-\d{2}\-\d{2})/ims", "/([^\(]*)(\(?)(after|before|from|to|until)/ims", "/^\w+\, (\w+\s\d{1,2}\, \d{4}) \(/ims"] as $regexp) {
            if (preg_match($regexp, $timeStr, $matches)) {
                if (isset($matches[1])) {
                    $time = strtotime($matches[1]);

                    if ($time !== false) {
                        $res = $time;
                    }
                }
            }
        }

        return $res;
    }

    public function ParseJson($data, $item)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data->publicReference->formattedReference)) {
            return false;
        }
        $this->logger->info('Parse Itinerary #' . $data->publicReference->formattedReference, ['Header' => 3]);

        $f = $this->itinerariesMaster->add()->flight();

        $f->ota()->confirmation($data->publicReference->formattedReference);

        if (isset($data->airOrder->airlineReferencesByLeg)) {
            foreach ($data->airOrder->airlineReferencesByLeg as $conf) {
                $f->general()->confirmation($conf->reference, 'Booking reference');
            }
        } else {
            $f->general()->noConfirmation();
        }

        $f->general()->status($this->arrayVal($item, ['status', 'display_text']));
        $status = $this->arrayVal($item, ['status', 'status']);

        if ($status == 'CANCELLED') {
            $f->general()->cancelled();
        }

        foreach ($data->passengers as $passenger) {
            $f->general()->traveller("{$passenger->firstName} {$passenger->lastName}");
        }

        foreach ($data->airOrder->flightSegments as $flightSegment) {
            foreach ($flightSegment->legs as $leg) {
                $s = $f->addSegment();
                $s->airline()->name($leg->flightInfo->carrierInfo->operatingCarrier);
                $s->airline()->number($leg->flightInfo->flightNumber);

                $s->departure()->name($leg->departureAirport->name);
                $s->departure()->code($leg->departureAirport->code);
                $s->departure()->date2($leg->departureTime);
                $s->arrival()->name($leg->arrivalAirport->name);
                $s->arrival()->code($leg->arrivalAirport->code);
                $s->arrival()->date2($leg->arrivalTime);
                $s->extra()->cabin($leg->cabinClass);
            }
        }
        $f->price()->total($data->totalPrice->total->units);
        $f->price()->currency($data->totalPrice->total->currencyCode);
        $f->price()->cost($data->totalPrice->baseFare->units);
        //$f->price()->fee($data->totalPrice->fee->units);
        $f->price()->tax($data->totalPrice->tax->units);
    }

    public function parseItineraryFlight($item, $headers)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->add()->flight();
        $f->ota()->confirmation($item->identifiers->publicId);
        $this->logger->info(sprintf('[%s] Flight Parse Itinerary #%s', $this->currentItin, $item->identifiers->publicId), ['Header' => 3]);

        $this->browser->GetURL("https://flights.booking.com/api/order/" . $item->encryptedOrderId . "?includeAvailableExtras=1", $headers);
        $data = $this->browser->JsonLog();

        if (!isset($data->publicReference->formattedReference)) {
            return false;
        }
        //$f->ota()->confirmation($data->publicReference->formattedReference);

        if (isset($data->airOrder->airlineReferencesByLeg)) {
            $confs = [];

            foreach ($data->airOrder->airlineReferencesByLeg as $conf) {
                $confs[] = $conf->reference;
            }

            foreach (array_unique($confs) as $conf) {
                $f->general()->confirmation($conf, 'Booking reference');
            }
        } else {
            $f->general()->noConfirmation();
        }

        $f->general()->status(beautifulName($item->reservationStatus));

        if ($item->reservationStatus == 'CANCELLED') {
            $f->general()->cancelled();
        }

        foreach ($data->passengers as $passenger) {
            $f->general()->traveller("{$passenger->firstName} {$passenger->lastName}");
        }

        foreach ($data->airOrder->flightSegments as $flightSegment) {
            foreach ($flightSegment->legs as $leg) {
                $s = $f->addSegment();
                $s->airline()->name($leg->flightInfo->carrierInfo->operatingCarrier);
                $s->airline()->number($leg->flightInfo->flightNumber);

                $s->departure()->name($leg->departureAirport->name);
                $s->departure()->code($leg->departureAirport->code);
                $s->departure()->date2($leg->departureTime);
                $s->arrival()->name($leg->arrivalAirport->name);
                $s->arrival()->code($leg->arrivalAirport->code);
                $s->arrival()->date2($leg->arrivalTime);

                $s->extra()->cabin($leg->cabinClass);
            }
        }
        $f->price()->total(round($data->totalPrice->total->units, 2));
        $f->price()->currency($data->totalPrice->total->currencyCode);
        $f->price()->cost(round($data->totalPrice->baseFare->units, 2));
        //$f->price()->fee($data->totalPrice->fee->units);
        $f->price()->tax($data->totalPrice->tax->units);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseProperties()
    {
        $this->logger->notice(__METHOD__);

        if (empty($this->Properties['Name'])) {
            $this->SetProperty("Name", beautifulName(trim(preg_replace(['/^Hi /', '/!$/'], "", $this->browser->FindSingleNode("//span[@id = 'profile-menu-trigger--title']")))));
        }

        if (empty($this->Properties['Status'])) {
            $this->SetProperty("Status", $this->browser->FindSingleNode('
                //span[(@class = "user_account_indication") and contains(text(), "Genius Level")]
                | //span[(@id = "profile-menu-trigger--content")]//span[contains(text(), "Genius Level")]
            '));
        }
    }

    private function loginSuccessful($delay = 10, $isLoggedIn = false)
    {
        $this->logger->notice(__METHOD__);
        $name = $this->waitForElement(WebDriverBy::xpath(self::LOGOUT_LINK_XPATH), $delay);
        $this->saveResponse();

        if (
            $isLoggedIn == true
            && $this->http->FindSingleNode('//a[contains(@class, "user_access_menu_auth_low_not_me")]')
        ) {
            return false;
        }
        // Access is allowed
        if (
            $this->http->FindSingleNode("//input[contains(@value, 'Sign out')]/@value")
            || $this->http->FindPreg('/input type=."hidden." name=."logout."/')
            || $this->http->FindPreg('/b_user_emails:\s*\[\s*\{\s*email: "([^\"]+)"/')
            || $this->http->FindPreg('/businessUserId":\d+/')
            || $name
        ) {
            return true;
        }

        return false;
    }

    private function parseItineraryEvent($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('[%s] Event Parse Itinerary #%s', $this->currentItin, $data->identifiers->publicId), ['Header' => 3]);

        $h = $this->itinerariesMaster->add()->event();
        $h->place()->type(Event::TYPE_EVENT);

        $h->general()
            ->status(beautifulName($data->reservationStatus))
            ->confirmation($data->identifiers->publicId, 'Confirmation number')
        ;

//        if (!empty($data->created_timestamp)) {
//            $h->general()->date($data->created_timestamp);
//        }

        if ($data->reservationStatus == 'CANCELLED') {
            $h->general()->cancelled();
        }
        $address = [];

        if (!empty($data->product->location->address->value)) {
            $address[] = $data->product->location->address->value;
        } else {
            $address[] = $data->product->name;
        }

        if (!empty($data->product->location->city)) {
            $address[] = $data->product->location->city;
        }

        if (!empty($data->product->location->cc1)) {
            $address[] = $data->product->location->cc1;
        }
        $h->place()
            ->name($data->product->name)
            ->address(join(', ', $address))
        ;

        $h->booked()->start2($this->dateFormat($data->startDateTime));
        $h->booked()->end2($this->dateFormat($data->endDateTime));

        $h->price()
            ->total(round($data->price->amount, 2))
            ->currency($data->price->currency)
        ;
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return [];
    }

    private function dateFormat($date)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Date: {$date}");

        return str_replace('T', ', ',
            $this->http->FindPreg('/^(.+?T\d+:\d+)/', false, $date));
    }

    private function parseItineraryHotel($data)
    {
        $this->logger->notice(__METHOD__);
        $conf = $data->identifiers->hotelReservationId ?? $data->identifiers->publicId;
        $this->logger->info(sprintf('[%s] Hotel Parse Itinerary #%s', $this->currentItin, $conf), ['Header' => 3]);

        if ($data->checkIn->start == null && $data->checkOut->end == null && strtotime($data->endDateTime) < time()) {
            $this->logger->error("Skip: Reservation has ended");

            return [];
        }
        $h = $this->itinerariesMaster->add()->hotel();

        $h->general()
            ->status(beautifulName($data->reservationStatus))
            ->confirmation($conf, 'Confirmation number')
        ;

        if ($data->reservationStatus == 'CANCELLED') {
            $h->general()->cancelled();
        }
        $address = [];

        if (!empty($data->propertyData->location->address)) {
            $address[] = $data->propertyData->location->address;
        }

        if (!empty($data->propertyData->location->city)) {
            $address[] = $data->propertyData->location->city;
        }

        if (!empty($data->propertyData->location->countryCode)) {
            $address[] = $data->propertyData->location->countryCode;
        }

        if (mb_strlen($data->propertyData->name) > 250) {
            $name = preg_split('/\s+(?:-|by)\s+/', $data->propertyData->name)[0];
        } else {
            $name = $data->propertyData->name;
        }
        $h->hotel()
            ->name($name)
            ->address(join(', ', $address));

        if (!empty($data->propertyData->phoneNumbers[0]) && mb_strlen($data->propertyData->phoneNumbers[0]) > 7) {
            $h->hotel()->phone(iconv("UTF-8", "ASCII//IGNORE",
                str_replace(['=', '`', '*'], ['-', '', ''], preg_replace('/(\x{200e}|\x{200f})/u', '', $data->propertyData->phoneNumbers[0]))
            ));
        }
        $checkIn = $data->checkIn->start ?? $data->startDateTime ?? null;

        if (isset($checkIn)) {
            $h->booked()->checkIn2($this->dateFormat($checkIn));
        }
        $checkOut = $data->checkOut->end ?? $data->checkOut->start ?? $data->endDateTime ?? null;

        if (isset($checkOut)) {
            $h->booked()->checkOut2($this->dateFormat($checkOut));
        }
        $h->booked()->rooms($data->numOfRooms);

        if ($data->price->amount > 0) {
            $h->price()
                ->total(round($data->price->amount, 2))
                ->currency($data->price->currency);
        }

        if (isset($data->policy->message) && $data->policy->type == 'CANCELLATION') {
            $h->setCancellation($data->policy->message);
        } elseif (isset($data->policy)) {
            $this->sendNotification('new type policy // MI');
        }

        /*if (isset($data->cancellation_status->valid_until) && $data->cancellation_status->cs_type == 'FREE_CANCELLATION') {
            $h->booked()->deadline($data->cancellation_status->valid_until);
        }*/

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItineraryTransfer($data)
    {
        $this->logger->notice(__METHOD__);
        $conf = $data->bookingRef ?? $data->identifiers->publicId;
        $this->logger->info(sprintf('[%s] Transfer Parse Itinerary #%s', $this->currentItin, $conf), ['Header' => 3]);

        $t = $this->itinerariesMaster->add()->transfer();
//        $t->ota()
//            ->confirmation($data->id);
        $t->general()
            ->status(beautifulName($data->reservationStatus))
            ->confirmation($conf);

        if ($data->reservationStatus == 'CANCELLED') {
            $t->general()->cancelled();
        }

        if ($data->price->amount > 0) {
            $t->price()
                ->total(round($data->price->amount, 2))
                ->currency($data->price->currency);
        }

        if (empty($data->bookingUrl)) {
            $this->logger->error('Empty url');

            return [];
        }

        try {
            $this->browser->RetryCount = 0;
            $this->browser->disableOriginHeader();
            $this->browser->GetURL("https://taxi.booking.com/en-gb/mybooking/validate?bookingId={$data->bookingRef}&emailAddress={$data->bookerEmail}&adplat=www-mytrips-my_trip_item-taxi-card-list-trip", [
                'Accept'                    => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
                'Accept-Encoding'           => 'gzip, deflate, br',
                'Referer'                   => null,
                'User-Agent'                => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/117.0.0.0 Safari/537.36 (AwardWallet Service. Contact us at awardwallet.com/contact)',
            ]);
            $this->browser->RetryCount = 2;
        } catch (Exception $e) {
            $this->logger->warning("Exception: " . $e->getMessage());
        }

        $props = urldecode($this->browser->FindSingleNode("//div[@data-mb-react-component-name='App']/@data-mb-props"));
        $props = $this->http->JsonLog($props, 3, false, 'journeyLegs');

        if (!isset($props->journeyLegs)) {
            if ($this->browser->Response['code'] == 503) {
                $this->logger->error('The error is the same as on the website');
                $this->itinerariesMaster->removeItinerary($t);
            }

            return [];
        }

        foreach ($props->journeyLegs as $journeyLegs) {
            $s = $t->addSegment();

            // Car Info
            $s->extra()->image($data->product->icon);
            $s->extra()->type($data->product->vehicleTypeText);

            // Departure
            if (!empty($journeyLegs->pickup->location)) {
                $s->departure()->name($journeyLegs->pickup->location);
            }

            if (!empty($journeyLegs->pickup->iata)) {
                $s->departure()->code($journeyLegs->pickup->iata);
            }
            $s->departure()->date2($this->dateFormat($journeyLegs->pickup->dateTime));

            // Arrival
            if (!empty($journeyLegs->dropoff->location)) {
                $s->arrival()->name($journeyLegs->dropoff->location);
                //$s->arrival()->address($journeyLegs->dropoff->location)
            }

            if (!empty($journeyLegs->dropoff->iata)) {
                $s->arrival()->code($journeyLegs->dropoff->iata);
            }
            $s->arrival()->date2($this->dateFormat($journeyLegs->dropoff->dateTime));
        }
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($t->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItineraryCar($data)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('[%s] Car Parse Itinerary #%s', $this->currentItin, $data->id), ['Header' => 3]);

        $r = $this->itinerariesMaster->add()->rental();
//        $t->ota()
//            ->confirmation($data->id);
        $r->general()
            ->status(beautifulName($data->reservationStatus))
            ->confirmation($data->id)
            //->date($data->created_timestamp)
        ;

        if ($data->reservationStatus == 'CANCELLED') {
            $r->general()->cancelled();
        }

        $r->extra()->company($data->product->supplier);

        // Car Info
        $r->car()->image($data->product->photos[0]->absoluteUrl ?? null, false, true);

        if (!empty($data->product->carClass)) {
            $r->car()->type($data->product->carClass);
        }
        $r->car()->model($data->product->name);

        // Departure
        $address = [];

        if (count((array) $data->pickUpLocation) > 5) {
            $this->sendNotification('check pickUpLocation // MI');
        }

        if (!empty($data->pickUpLocation->city)) {
            $address[] = $data->pickUpLocation->city;
        }

        if (!empty($data->pickUpLocation->countryCode)) {
            $address[] = $data->pickUpLocation->countryCode;
        }
        $r->pickup()->location(join(', ', $address));
        $r->pickup()->date2($this->dateFormat($data->startDateTime));

        // Arrival
        $address = [];

        if (!empty($data->dropOffLocation->city)) {
            $address[] = $data->dropOffLocation->city;
        }

        if (!empty($data->dropOffLocation->countryCode)) {
            $address[] = $data->dropOffLocation->countryCode;
        }
        $r->dropoff()->location(join(', ', $address));
        $r->dropoff()->date2($this->dateFormat($data->endDateTime));

        if ($data->price->amount > 0) {
            $r->price()
                ->total(round($data->price->amount, 2))
                ->currency($data->price->currency);
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parsePersonalItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Parse Personal', ['Header' => 3]);
        $this->browser->GetURL('https://secure.booking.com/mytrips.en-us.html');
        sleep(random_int(1, 5));
        $csrf = $this->browser->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");

        if (!$csrf) {
            sleep(3);
            $this->browser->GetURL('https://secure.booking.com/mytrips.en-us.html');
            $csrf = $this->browser->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");

            if ($csrf) {
                $this->sendNotification('success retry // MI');
            }
        }

        if (!$csrf) {
            $this->switchAccount('personal');

            if ($this->browser->currentUrl() == 'https://admin.business.booking.com/direct-sso') {
                $this->browser->GetURL("https://www.booking.com/Apps/Dashboard?auth_success=1");
                $this->switchAccount("personal");
            }

            $csrf = $this->browser->FindPreg("/'X-Booking-CSRF':\s*'(.+?)'/");
        }

        if (!$csrf) {
            $this->sendNotification('check personal csrf // MI');

            return [];
        }

        $xBookingAid = $this->browser->FindPreg("/'X-Booking-AID'\s*:\s*'([^\']+)/");
        $sid = $this->browser->FindPreg("/b_sid\s*:\s*'([^\']+)/");
        $headers = [
            'X-Booking-CSRF' => $csrf,
        ];
        $data = '{"operationName":"TimelineQuery","variables":{"input":{"stages":["PAST","CURRENT","UPCOMING"],"pagination":{"rowsPerPage":0,"paginationToken":"eyJwYWdlU2l6ZSI6NTAsInBhZ2VObyI6MH0="},"thumbnailSize":{"width":2192,"height":548},"selectConnectorChannels":["MY_TRIPS_TIMELINE"],"supportedConnectors":["ACCOMMODATION_POB","ADD_ARRIVAL_TIME","ADD_REVIEW","APP_MANAGE_RESERVATION","ATTR_FIND_THINGS_TO_DO","BASIC_TRIP","BOOK_AGAIN","CANCEL_BOOKING","CONTACT_HELP_CENTER","DEALS_UNLOCKED","EARLY_CHECK_IN","EMERGENCY_MESSAGE_CONNECTOR","FLIGHT_ONLINE_CHECK_IN","FLIGHTS_WHERE_TO_STAY","GET_DIRECTION","GUEST_DATE_CHANGE","HELP_CENTER","INVALID_PAYMENT","KEY_COLLECTION_INFO","LATE_CHECK_IN_SURVEY","LATE_CHECK_IN","MENU_ITEM_ADD_REVIEW","MENU_ITEM_CANCEL_RESERVATION","MENU_ITEM_GET_DIRECTION","MENU_ITEM_HC_LINK","MENU_ITEM_HIDE_RESERVATION","MENU_ITEM_INVALID_PAYMENT","MENU_ITEM_MANAGE_RESERVATION","MENU_ITEM_MODIFY_DATE_RESERVATION_APPROVAL","MENU_ITEM_MODIFY_DATE_RESERVATION","MENU_ITEM_MSG_TO_RESERVATION","MENU_ITEM_RECOVER_RESERVATION","MENU_ITEM_SHARE_RESERVATION","MENU_ITEM_USER_CHANGE_DATE","MENU_ITEM_USER_REQUEST_DATE_CHANGE","MENU_ITEM_VIEW_CANCEL_POLICY","MENU_ITEM_VIEW_RESERVATION","MESSAGE_PROPERTY","PARKING_INFORMATION","PARTNER_DATE_CHANGE","PAY_NOW","SURVEY","TAXI_COMPANION","UPGRADE_ROOM","VIEW_RESERVATION","CAR_PICK_UP_BEFORE_LANDING","TAXI_BOOK_RETURN_RIDE"],"supportedExperiences":["ACCOMMODATION_ARRIVAL","ACCOMMODATION_INSTAY","BHOME_ARRIVAL","POST_TRIP","TAXI_ARRIVAL"],"extraParameters":{"injectFakeReservationsForPrebookTaxi":false}}},"extensions":{},"query":"query TimelineQuery($input: TripsListInput!) {\n  tripsQueries {\n    tripList(input: $input) {\n      ... on TripsList {\n        backfillStatus\n        nextPageData {\n          rowsPerPage\n          paginationToken\n          __typename\n        }\n        timelines {\n          trip {\n            id\n            title\n            startDateTime\n            endDateTime\n            lastUpdatedAt\n            localizedTripDateRange\n            headerPhotos {\n              id\n              size\n              absoluteUrl\n              __typename\n            }\n            canceled\n            __typename\n          }\n          timelineGroups {\n            tripItems {\n              type\n              ... on ConnectorTripItem {\n                connector {\n                  ...GenericAdditionalConnectorFields\n                  __typename\n                }\n                __typename\n              }\n              ... on ReservationTripItem {\n                reservation {\n                  localisedDatetimeRange\n                  identifiers {\n                    publicFacingIdentifier\n                    publicId\n                    reserveOrderId\n                    ... on AccommodationReservationIdentifiers {\n                      hotelReservationId\n                      __typename\n                    }\n                    __typename\n                  }\n                  startDateTime\n                  endDateTime\n                  verticalType\n                  reservationStatus\n                  lastUpdated\n                  bookingUrl\n                  price {\n                    amount\n                    currency\n                    __typename\n                  }\n                  userPriceWeb {\n                    userCurrencyPrice\n                    __typename\n                  }\n                  ... on AccommodationReservation {\n                    pinCode\n                    authKey\n                    checkIn {\n                      start\n                      end\n                      __typename\n                    }\n                    checkOut {\n                      start\n                      end\n                      __typename\n                    }\n                    propertyData {\n                      ... on ReservationPropertyData {\n                        ...ReservationPropertyDataFields\n                        __typename\n                      }\n                      __typename\n                    }\n                    reservationDetailsURL\n                    policy {\n                      type\n                      name\n                      message\n                      __typename\n                    }\n                    numOfRooms\n                    __typename\n                  }\n                  ... on BookingBasicReservation {\n                    pinCode\n                    checkIn {\n                      start\n                      end\n                      __typename\n                    }\n                    checkOut {\n                      start\n                      end\n                      __typename\n                    }\n                    propertyData {\n                      ... on ReservationPropertyData {\n                        ...ReservationPropertyDataFields\n                        __typename\n                      }\n                      __typename\n                    }\n                    numOfGuests\n                    numOfRooms\n                    __typename\n                  }\n                  ... on RocketMilesReservation {\n                    pinCode\n                    checkIn {\n                      start\n                      end\n                      __typename\n                    }\n                    checkOut {\n                      start\n                      end\n                      __typename\n                    }\n                    propertyData {\n                      ... on ReservationPropertyData {\n                        ...ReservationPropertyDataFields\n                        __typename\n                      }\n                      __typename\n                    }\n                    numOfGuests\n                    numOfNights\n                    numOfRooms\n                    __typename\n                  }\n                  ... on AttractionReservation {\n                    product {\n                      location {\n                        city\n                        latitude\n                        longitude\n                        __typename\n                      }\n                      name\n                      photos {\n                        absoluteUrl\n                        __typename\n                      }\n                      __typename\n                    }\n                    ticketCount\n                    __typename\n                  }\n                  ... on CarReservation {\n                    id\n                    bookerEmail\n                    pickUpLocation {\n                      city\n                      latitude\n                      longitude\n                      countryCode\n                      __typename\n                    }\n                    dropOffLocation {\n                      city\n                      latitude\n                      longitude\n                      countryCode\n                      __typename\n                    }\n                    product {\n                      carClass\n                      name\n                      supplier\n                      photos {\n                        absoluteUrl\n                        __typename\n                      }\n                      __typename\n                    }\n                    __typename\n                  }\n                  ... on FlightReservation {\n                    encryptedOrderId\n                    passengerCount\n                    flightComponents {\n                      localisedDatetimeRange\n                      parts {\n                        startDateTime\n                        endDateTime\n                        startLocation {\n                          iata\n                          location {\n                            city\n                            countryCode\n                            latitude\n                            longitude\n                            __typename\n                          }\n                          __typename\n                        }\n                        endLocation {\n                          iata\n                          location {\n                            city\n                            countryCode\n                            latitude\n                            longitude\n                            __typename\n                          }\n                          __typename\n                        }\n                        marketingCarrier {\n                          code\n                          name\n                          logo {\n                            absoluteUrl\n                            __typename\n                          }\n                          __typename\n                        }\n                        flightNumber\n                        __typename\n                      }\n                      __typename\n                    }\n                    __typename\n                  }\n                  ... on PrebookTaxiReservation {\n                    bookerEmail\n                    bookingRef\n                    pickUp {\n                      datetime\n                      location {\n                        city\n                        latitude\n                        longitude\n                        airportCode\n                        airportName\n                        __typename\n                      }\n                      __typename\n                    }\n                    dropOff {\n                      datetime\n                      location {\n                        city\n                        latitude\n                        longitude\n                        __typename\n                      }\n                      __typename\n                    }\n                    product {\n                      providerName\n                      vehicleTypeText\n                      icon\n                      __typename\n                    }\n                    prebookTaxiComponents {\n                      status\n                      price {\n                        amount\n                        currency\n                        __typename\n                      }\n                      userPriceWeb {\n                        userCurrencyPrice\n                        __typename\n                      }\n                      start {\n                        datetime\n                        location {\n                          city\n                          countryCode\n                          latitude\n                          longitude\n                          airportCode\n                          airportId\n                          airportName\n                          ufi\n                          __typename\n                        }\n                        __typename\n                      }\n                      end {\n                        datetime\n                        location {\n                          city\n                          countryCode\n                          latitude\n                          longitude\n                          ufi\n                          __typename\n                        }\n                        __typename\n                      }\n                      product {\n                        providerName\n                        vehicleTypeText\n                        icon\n                        __typename\n                      }\n                      __typename\n                    }\n                    __typename\n                  }\n                  ... on PublicTransportReservation {\n                    ticketUrl\n                    parts {\n                      icons {\n                        absoluteUrl\n                        __typename\n                      }\n                      displayText\n                      numberOfTickets\n                      arrivalStation {\n                        stationName\n                        latitude\n                        longitude\n                        __typename\n                      }\n                      validityPeriod {\n                        localisedDatetime\n                        start\n                        __typename\n                      }\n                      __typename\n                    }\n                    __typename\n                  }\n                  ... on SingleTripInsuranceReservation {\n                    numberOfInsuredPeople\n                    __typename\n                  }\n                  __typename\n                }\n                __typename\n              }\n              ... on ExperienceTripItem {\n                code\n                channel\n                additionalData {\n                  ... on SimpleTripExperienceData {\n                    textualInfo\n                    __typename\n                  }\n                  ... on InStayExperienceData {\n                    breakfastSchedule {\n                      from {\n                        hours\n                        minutes\n                        __typename\n                      }\n                      to {\n                        hours\n                        minutes\n                        __typename\n                      }\n                      dayOfWeek\n                      __typename\n                    }\n                    __typename\n                  }\n                  __typename\n                }\n                connectors {\n                  ...GenericAdditionalConnectorFields\n                  __typename\n                }\n                __typename\n              }\n              __typename\n            }\n            __typename\n          }\n          __typename\n        }\n        __typename\n      }\n      ... on TripsListError {\n        statusCode\n        response\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment GenericAdditionalConnectorFields on Connector {\n  ...SharedGenericConnectorFields\n  additionalData {\n    type\n    ... on CommuteAfterFlightData {\n      ...CommuteAfterFlightDataFields\n      __typename\n    }\n    ... on ReservationParkingDetails {\n      ...ReservationParkingDetailsDataFields\n      __typename\n    }\n    ... on AdditionalDescription {\n      description\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment SharedGenericConnectorFields on Connector {\n  __typename\n  code\n  channel\n  associatedReservations {\n    __typename\n    publicId\n    publicFacingIdentifier\n    reserveOrderId\n  }\n  associatedTripID\n  content {\n    __typename\n    contentType\n    ... on MenuItemConnectorContent {\n      __typename\n      ...MenuItemConnectorFields\n    }\n    ... on BasicConnectorContent {\n      __typename\n      ...BasicConnectorFields\n    }\n  }\n}\n\nfragment MenuItemConnectorFields on MenuItemConnectorContent {\n  __typename\n  icon\n  action {\n    __typename\n    actionType\n    ... on DeeplinkAction {\n      __typename\n      cta\n      url\n      deepLink\n    }\n    ... on DialogAction {\n      __typename\n      title\n      description\n      primaryAction {\n        __typename\n        actionType\n        ... on DeeplinkAction {\n          __typename\n          cta\n          url\n          deepLink\n        }\n      }\n    }\n  }\n}\n\nfragment BasicConnectorFields on BasicConnectorContent {\n  __typename\n  icon\n  severity\n  headline\n  headlineLong\n  action {\n    __typename\n    actionType\n    ... on DeeplinkAction {\n      __typename\n      cta\n      url\n      deepLink\n    }\n    ... on DialogAction {\n      __typename\n      title\n      description\n      primaryAction {\n        __typename\n        actionType\n        ... on DeeplinkAction {\n          __typename\n          cta\n          url\n          deepLink\n        }\n      }\n    }\n  }\n}\n\nfragment CommuteAfterFlightDataFields on CommuteAfterFlightData {\n  __typename\n  flightBeforeCommute {\n    __typename\n    componentIndex\n    partIndex\n  }\n  commute {\n    __typename\n    timeInAirport {\n      __typename\n      minimum\n      maximum\n      timeUnit\n    }\n    drivingTime {\n      __typename\n      minimum\n      maximum\n      timeUnit\n    }\n    totalCommuteTime {\n      __typename\n      minimum\n      maximum\n      timeUnit\n    }\n  }\n  propertyArrival {\n    __typename\n    estimatedTime {\n      __typename\n      start\n      end\n    }\n  }\n}\n\nfragment ReservationParkingDetailsDataFields on ReservationParkingDetails {\n  __typename\n  payload {\n    __typename\n    parkingInfo {\n      __typename\n      details {\n        __typename\n        caption\n        icon\n      }\n    }\n  }\n}\n\nfragment ReservationPropertyDataFields on ReservationPropertyData {\n  __typename\n  id\n  name\n  pageName\n  location {\n    __typename\n    countryCode\n    latitude\n    longitude\n    city\n    inCityName\n    ufi\n    ... on AccommodationLocation {\n      __typename\n      address\n    }\n  }\n  photos {\n    __typename\n    size\n    absoluteUrl\n  }\n  isBookingHome\n  phoneNumbers\n  url\n}\n"}';
        $this->browser->PostURL("https://secure.booking.com/dml/graphql?aid={$xBookingAid}&sid={$sid}&lang=en-us", $data);
        sleep(random_int(1, 5));
        //$this->browser->GetURL("https://secure.booking.com/trip/timeline?aid=1473858&stype=1&lang=en-gb&bhc_csrf_token={$csrf}&bhc_csrf_token_check=1&thumbnail_width=312&thumbnail_height=172&page_size=10&vertical_products=BOOKING_HOTEL,BASIC,ATTRACTIONS,CARS,FLIGHTS,PREBOOK_TAXIS,PUBLIC_TRANSPORT,ROCKETMILES&include_cancelled_in_trip_name_date=0&include_components=1&header_size=1096x274,2192x548", $headers);
        $data = $this->browser->JsonLog(null, 3);

        if (!isset($data->data->tripsQueries->tripList->timelines)) {
            //$this->sendNotification('check personal itineraries // MI');

            return [];
        }

        foreach ($data->data->tripsQueries->tripList->timelines as $timelines) {
            /*if ($trip->cancelled === true) {
                $h = $this->itinerariesMaster->add()->hotel();
                $h->general()->confirmation("");
                $h->general()->cancelled();
            }*/

            foreach ($timelines->timelineGroups as $timeline) {
                foreach ($timeline->tripItems as $item) {
                    if (!isset($item->reservation->verticalType)) {
                        continue;
                    }

                    /*if (isset($item->reservation_data->booker_email)) {
                        $params = http_build_query([
                            'emailAddress' => $item->reservation_data->booker_email,
                            'bookingId'    => $item->reservation_data->id,
                            'adplat'       => 'www-mytrips-my_trip_item-taxi-card-list-trip',
                        ]);

                        try {
                            $this->http->GetURL("https://taxi.booking.com/en-gb/mybooking/validate?{$params}");
                        } catch (Exception $e) {
                            $this->logger->error("exception: " . $e->getMessage());
                        }
                    }*/

                    if (in_array($item->reservation->verticalType, ['ACCOMMODATION', 'BASIC'])) {
                        $this->parseItineraryHotel($item->reservation);
                    } elseif ($item->reservation->verticalType == 'PREBOOK_TAXI') {
                        $this->parseItineraryTransfer($item->reservation);
                    } elseif ($item->reservation->verticalType == 'CAR') {
                        $this->parseItineraryCar($item->reservation);
                    } elseif ($item->reservation->verticalType == 'FLIGHT') {
                        $this->parseItineraryFlight($item->reservation, $headers);
                    } elseif ($item->reservation->verticalType == 'ATTRACTION') {
                        $this->parseItineraryEvent($item->reservation);
                    } elseif (!in_array($item->reservation->verticalType, ['INSURANCE', 'SINGLE_TRIP_INSURANCE'])) {
                        $this->sendNotification("new it {$item->reservation->verticalType} // MI", 'awardwallet');
                    }
                    $this->currentItin++;
                    $this->increaseTimeLimit(60);
                }
            }
        }

        return [];
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        foreach ($indices as $index) {
            if (isset($res[$index])) {
                $res = $res[$index];
            } else {
                $this->logger->debug('Invalid indices:');
                $this->logger->debug(var_export($indices, true));

                return $default;
            }
        }

        if (is_string($res)) {
            $res = trim($res);
        }

        return $res;
    }

    private function getCurrencyCode($symbol)
    {
        switch ($symbol) {
            case '€':
                $currency = 'EUR';

                break;

            case '$':
                $currency = 'USD';

                break;

            case '£':
                $currency = 'GBP';

                break;

            case 'R$':
                $currency = 'BRL';

                break;

            case 'A$':
                $currency = 'AUD';

                break;

            case 'S$':
                $currency = 'SGD';

                break;

            case 'Rp':
                $currency = 'IDR';

                break;

            case 'Rs':
                $currency = 'INR';

                break;

            case 'P':
                $currency = 'PHP';

                break;

            case '¥':
                $currency = 'JPY';

                break;

            case 'TL':
                $currency = 'TRY';

                break;

            case 'RM':
                $currency = 'MYR';

                break;

            case 'NT$':
                $currency = 'TWD';

                break;

            case 'zł':
                $currency = 'PLN';

                break;

            default:
                if (!empty($currency)) {
                    $this->sendNotification("booking. Migrating to v2, need to fix currency code: {$currency}");
                }
                $currency = null;
        }

        return $currency;
    }
}
