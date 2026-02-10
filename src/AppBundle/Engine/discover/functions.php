<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerDiscover extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

//    private const REWARDS_PAGE_URL = "https://portal.discover.com/dfs/accounthome/portal?flowExecutionSnapshotId=e1s1&flowStateName=beforeSummary";
    private const REWARDS_PAGE_URL = "https://www.discovercard.com/cardmembersvcs/achome/homepage";

    private const XPATH_NUMBER = "//p[contains(text(), 'Discover it Card') or contains(text(), 'Discover More Card') or contains(text(), 'Discover it chrome Card')]/following-sibling::p[@class = 'card-last-digits']";
    private const XPATH_NUMBER_EXTENDED = "//p[contains(text(), 'Discover it Card') or contains(text(), 'Discover Card') or contains(text(), 'Discover More Card') or contains(text(), 'Discover it Miles Card') or contains(text(), 'Discover it chrome Card')]/following-sibling::p[@class = 'card-last-digits']";
    // refs #15406 Chase Freedom 5% cash back tracking
    protected $allowHtmlProperties = ["CashBack", "CashBackNextQuarter"];

    private $questionQuery = "//div[@class = 'bodyText' and contains(text(), 'For your security, please answer the question')]/b";
    private $questionIdCode = 'Please enter Identification Code which was sent to your email address. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.'; /*checked*/
    private $questionIdCodeToPhone = 'Please enter Identification Code which was sent to your phone. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.'; /*checked*/

    private $seleniumURL = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        /*
        $this->http->SetProxy($this->proxyStaticIpDOP());
        */
        $this->setProxyBrightData();
        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . \HttpBrowser::BROWSER_VERSION_MIN . '.0.0.0 Safari/537.36');
    }

    public function get_pm_fp()
    {
        $this->logger->notice(__METHOD__);

        return 'version=-1&pm_fpua=' . strtolower($this->http->userAgent) . '|' . str_replace('Mozilla/', '', strtolower($this->http->userAgent)) . '|MacIntel&pm_fpsc=30|1536|960|871&pm_fpsw=&pm_fptz=5&pm_fpln=lang=en-US|syslang=|userlang=&pm_fpjv=0&pm_fpco=1';
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

    public function uniqueStateKeys()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $xKeys = [];
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->http->saveScreenshots = true;
//            switch (rand(0, 2)) {
//                case 0:
//                    $selenium->useFirefox();
//                    break;
//            $selenium->http->setUserAgent($this->http->userAgent);
//                case 1:
//                    $selenium->useGoogleChrome();
//                    break;
//                case 2:

            $selenium->useFirefox();
            $selenium->setKeepProfile(true);

//                    break;
//            }
            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->usePacFile(false);

            unset($this->State['Resolution']);

            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.discover.com/");

            $loginInput = $selenium->waitForElement(WebDriverBy::id('userid'), 10, false);
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('password'), 0, false);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput) {
                return $this->checkErrors();
            }
//            $selenium->driver->executeScript("
//                    $('div.input-wrapper').removeClass('input-wrapper');
//                ");
//            $loginInput->sendKeys($this->AccountFields['Login']);
//            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $selenium->driver->executeScript("
                        $('form[name = \"loginForm\"]').find('input[name = \"userID\"]').val('{$this->AccountFields['Login']}');
                        $('form[name = \"loginForm\"]').find('input[name = \"password\"]').val('" . addcslashes($this->AccountFields['Pass'], "'\\") . "');
                    ");
            // Sign In
            $selenium->driver->executeScript("
                $('#log-in-button').click();
            "); // window.stop();

            /*
            // uniqueStateKey
            if ($xKey = $selenium->waitForElement(WebDriverBy::xpath('//form[@name = "loginForm"]//input[contains(@name, "X-") and not(contains(@name, "uniqueStateKey"))]'), 5, false)) {
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@name = "loginForm"]//input[contains(@name, "X-")]', 0, false)) as $index => $xKey) {
                    $xKeys[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value"),
                    ];
                }
                $this->logger->debug(var_export($xKeys, true), ["pre" => true]);
            }
            */

            $selenium->waitForElement(WebDriverBy::xpath("
                //a[contains(@href, 'logout')]
                | //div[contains(@class, 'category-group') and div[contains(@class, 'col-account-name')] and div/h4/em[contains(text(), 'Cashback Bonus') or contains(text(), 'Miles')]]
                | //h1[@data-testid = 'greetingMessage']
                | //div[@class = 'module']//tr[td[@class = 'first']/a[contains(@class, 'cashback-checking')]]
                | //strong[contains(text(), 'temporary identification code')]
                | //span[contains(text(), 'For security purposes, your online account has been locked')]
                | //h1[contains(text(), 'Your account cannot currently be accessed.')]
            "), 20);

            try {
                $this->savePageToLogs($selenium);
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage());
            }

            if ($close = $selenium->waitForElement(WebDriverBy::xpath("//button[contains(@class, 'at-overlay-close')] | //a[@id = 'goToAccountHome']"), 0)) {
                $close->click();
                sleep(1);
                $this->savePageToLogs($selenium);
            }

            $cookies = $selenium->driver->manage()->getCookies();
            $this->http->removeCookies();
            $this->logger->debug("set cookies");

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current selenium URL]: {$this->seleniumURL}");
            // save page to logs
            $this->savePageToLogs($selenium);
        } catch (
            TimeOutException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\UnknownErrorException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup();

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 7);
            }
        }

        return $xKeys;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (strlen($this->AccountFields['Login']) > 16) {
            throw new CheckException("User ID or Account Number should be no more than 16 characters", ACCOUNT_INVALID_PASSWORD);
        } /*checked*/

        $this->http->FilterHTML = false;
        $this->http->GetURL("https://www.discover.com/");

        $xKeys = $this->uniqueStateKeys();

        return true;

        /*
        if (empty($xKeys)) {
            return false;
        }
        */

        if (!$this->http->ParseForm("loginForm")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("userID", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("pm_fp", $this->get_pm_fp());
        $this->http->unsetInputValue("choose-card");
        // The "Remember User ID" feature will not save your Discover card account number.
        if (!(is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) == 16)) {
            $this->http->SetInputValue("rememberOption", "on");
        }
//        $this->http->SetInputValue("ssid", "8a84d652-f4c7-4df1-880d-a438179f33b-".time().date("B"));

        foreach ($xKeys as $xKey) {
            if (isset($xKey['name'], $xKey['value'])) {
                $this->http->SetInputValue($xKey['name'], $xKey['value']);
            }
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindPreg("/We are currently experiencing technical difficulties/ims")) {
            throw new CheckException("Discover website are currently experiencing technical difficulties. We apologize for the inconvenience.", ACCOUNT_PROVIDER_ERROR);
        }
        /* Your request cannot be completed because the rewards application is currently under maintenance.We apologize for the inconvenience. */
        if ($message = $this->http->FindPreg("/Your request cannot be completed because the rewards application is currently under maintenance\.\s*We apologize for the inconvenience\./ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // KMaintenance
        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "we are performing system maintenance")]
                | //p[contains(text(), "We\'re sorry. We are currently updating our system and cannot complete your request at this time.")]
                | //b[contains(text(), "Sorry for the inconvenience, but we are performing system maintenance from")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Need to register a new credit card
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to special conditions related to your credit card account, we cannot provide you with access to your account information at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Your request cannot be completed because the web server or the web application has experienced a technical error.
        if ($message = $this->http->FindPreg("/(Your request cannot be completed because the web server or the web application has experienced a technical error\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request\.)/ims")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // CREATE A NEW USER ID
        if ($this->http->FindSingleNode("//h1[contains(text(), 'CREATE A NEW USER ID')]")) {
            throw new CheckException("Discover Rewards website is asking you to create a new user id, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/
        // Please update your password to help better protect your account.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Please update your password to help better protect your account.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Account Unlock Process
        if ($this->http->FindPreg("/form\s*action=\"\/cardmembersvcs\/registration\/reg\/goto\?forwardName=accountunlock\"/ims")) {
            throw new CheckException("You Have Exceeded the Maximum Number of Login Attempts", ACCOUNT_LOCKOUT);
        }
        // Your request cannot be completed on the site at this time.
        if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your request cannot be completed on the site at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // site bug ?
        if (empty($this->http->Response['body'])
            // Service Unavailable
            || $this->http->FindSingleNode("//h1[contains(text(), 'Service Unavailable')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // same as in in strangeRedirect()
        // Sorry! You have submitted identification codes too many times.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Sorry! You have submitted identification codes too many times.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * We'll need just a bit more information.
         *
         * We've noticed you don't have any contact information on file.
         * We'll need a valid phone number and/or email address before you can log into your account.
         * Please contact us at 1-800-347-2655 for immediate assistance.
         *
         * or
         *
         * We've noticed you don't have any contact information on file. Please contact us at 1-800-347-2655 for immediate assistance and to edit your contact information.
         */
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve noticed you don\'t have any contact information on file. ")]')) {
            $this->throwProfileUpdateMessageException();
        }

        return false;
    }

    public function Login()
    {
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;

        $headers = [
            'User-Agent' => \HttpBrowser::PROXY_USER_AGENT,
            'Origin'     => 'https://www.discover.com',
        ];

        /*
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm($headers) && $this->http->Response['code'] != 404) {
            return $this->checkErrors();
        }

        $this->http->RetryCount = 2;
        */
        /**
         * Your account cannot currently be accessed.
         *
         * Outdated browsers can expose your computer to security risks.
         * To get the best experience on Discover.com, you may need to update your browser to the latest version and try again.
         */
        if (
            $this->http->currentUrl() == 'https://portal.discover.com/psv1/notification.html'
            && $this->http->FindSingleNode("//h1[contains(text(), 'Your account cannot currently be accessed.')]")
        ) {
            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                return false;
            }

            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(2);
        }

        if ($this->http->Response['code'] == 404) {
            $this->http->Form = $form;
            $this->http->FormURL = $formURL;
            $this->http->PostForm();
        }

        if ($this->http->currentUrl() == 'https://www.discover.com/discover/data/misc/error404.shtml') {
            $this->logger->notice("provider bug fix");
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }// if ($this->http->currentUrl() == 'https://www.discover.com/discover/data/misc/error404.shtml')

        if (
            $this->http->currentUrl() == 'https://portal.discover.com/web/customer/portal'
            || $this->seleniumURL == 'https://portal.discover.com/web/customer/portal'
        ) {
            $this->http->GetURL("https://portal.discover.com/enterprise/portal/customeraccountinfo/v1/summary");

            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[@class = 'error']")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg("/You Have Exceeded the Maximum Number of Unsuccessful Attempts/ims")) {
            throw new CheckException("You Have Exceeded the Maximum Number of Unsuccessful Attempts. Please contact Customer Service at <strong>1-877-742-7822</strong> for more information or assistance.", ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//p[@class='red']/strong")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // You may no longer use this Discover Card account number to access the Account Center
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You may no longer use this Discover Card account number to access the Account Center')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The information you provided does not match our records.
        if ($message = $this->http->FindSingleNode("//*[contains(text(),'The information you provided does not match our records.')]")) {
            throw new CheckException("The information you provided does not match our records.", ACCOUNT_INVALID_PASSWORD);
        }
        // You Have Exceeded the Maximum Number of Log-in Attempts
        if ($message = $this->http->FindSingleNode("
                //h2[contains(text(), 'You Have Exceeded the Maximum Number of Log-in Attempts')]
                | //span[contains(text(), 'For security purposes, your online account has been locked')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // You Have Exceeded the Maximum Number of Log-In Attempts
        if ($message = $this->http->FindPreg("/<h2 class=\"headline\">(You Have Exceeded the Maximum Number of Log-In Attempts)<\/h2>/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindPreg("/(Due to special conditions related to your credit card account\, we cannot provide you with access to your account information at this time\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are unable to allow access into your account at this time.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are unable to allow access into your account at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * For your added security,
         * the feature you are trying to access is not available for your Discover card account at this time.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'For your added security, the feature you are trying to access is not available for your Discover card account at this time.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // skip activation card
        if ($this->http->FindSingleNode("//img[contains(@alt, 'Please Activate Your New Discover Card')]/@alt")
            && ($link = $this->http->FindSingleNode("//a[contains(@title, 'Goto Discover Home')]/@href"))) {
            $this->logger->notice("skip activation card");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
        }

        // Security question
        if ($this->ParseQuestion()) {
            return false;
        }

        //# Enhanced Account Security
        if ($this->http->FindSingleNode("//img[contains(@title, 'New Security Measures to Protect Your Account')]/@title")
            || $this->http->FindSingleNode("//img[contains(@title, 'Help Us Keep Your Account Information Safe')]/@title")
            // You are seeing this page because your account is past due.
            || $this->http->FindSingleNode("//h1[contains(text(), 'You are seeing this page because your account is past due.')]")
            // Sign up for Paperless Disclosures
            || ($this->http->FindSingleNode("//h1[contains(text(), 'Sign up for Paperless Disclosures.')]")
                && $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/ems/action/esignEnroll')
            /*
             * By clicking 'Continue' you agree to receive fraud text alerts at the number provided.
             * If you do not wish to receive fraud text alerts, please uncheck the box.
             * You will still receive fraud e-mail alerts.
             */
            || $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/personalprofile/pp/RenderFraudIntercept?version=v2') {
            throw new CheckException("Discover Rewards website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/
        // Please call Discover Customer Service at 1-888-251-8003 for information about accessing your Account online.
        if ($message = $this->http->FindSingleNode("//div[@id = 'error']/p[contains(text(), 'for information about accessing your Account online')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->skipOffer();

        // Please confirm your recent account activity
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Please confirm your recent account activity')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your Discover Card Account Number Has Changed Due to a Systems Upgrade
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Your Discover Card Account Number Has Changed Due to a Systems')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Log in Using Your Updated Credentials
        if ($message = $this->http->FindSingleNode("//p[strong[contains(text(), 'Log in Using Your Updated Credentials')]]/following-sibling::p[contains(text(), 'Please log in with the most recent User ID and password associated with your account.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // A replacement card has been sent to you.
        if ($message = $this->http->FindSingleNode("//h6[contains(text(), 'A replacement card has been sent to you.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // A replacement card has been sent to you and your account number has been changed
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'A replacement card has been sent to you and your account number has been changed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->loginSuccessful()) {
            return true;
        }
        // Create a New Password
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to increased security requirements, you must create a new password')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->Response['code'] == 403
            && $this->http->FindPreg("/You don\'t have permission to access\s*\/cardmembersvcs\/loginlogout\/app\/signin\/zflubHome/ims")
            && $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/loginlogout/app/signin/zflubHome') {
            throw new CheckException("Due to special conditions related to your credit card account, we cannot provide you with access to your account information at this time.", ACCOUNT_PROVIDER_ERROR);
        }
        // We need to get some additional information from you before you log in to help protect your account.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We need to get some additional information from you before you log in to help protect your account.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Income and Housing Information
        if ($this->http->FindSingleNode("//p[contains(text(), 'By selecting the Accept Terms & Enroll button, you agree to receive Paperless Disclosures electronically and have the ability to do so.')]")
            || ($this->http->FindSingleNode("//p[contains(text(), 'Income and Housing Information')]")
                && $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/personalprofile/pp/editIncome')
            // Paperless Statements Sign Up
            || ($this->http->FindSingleNode("//h1[contains(text(), 'Paperless Statements Sign Up')]")
                && $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/paperless/app/enroll')
            || ($this->http->FindSingleNode("//h1[contains(text(), 'Fight Fraud. Faster')]")
                && $this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/personalprofile/pp/RenderFraudIntercept?version=v1')
            // Need to active card
            || ($this->http->FindSingleNode("//img[contains(@alt, '3 Digit Sequence ID')]/@alt")
                && ($link = $this->http->FindSingleNode("//a[contains(@title, 'Return to Account Center Home')]/@href")))) {
            $this->throwProfileUpdateMessageException();
        }
        // Improve your password strength to better protect your account.
        if ($this->http->ParseForm("interceptStoreForm")) {
            $this->http->Form['hjckAcptInd'] = 'Y';
            $this->http->PostForm();
            $this->logger->debug("Improve your password strength to better protect your account.");
        }

        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Improve your password strength to better protect your account')]")) {
            throw new CheckException("Discover Rewards website is asking you to improve your password strength to better protect your account, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/
        // For Your Added Security
        if ($this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/personalprofile/pp/updatePassword?intercept=Y&m=Y') {
            throw new CheckException("Discover Rewards website is asking you to create a New Password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*review*/
        // We've been informed that your Discover card account may have been compromised.
        if ($message = $this->http->FindSingleNode("//h6[contains(text(), 'been informed that your Discover card account may have been compromised')]")) {
            throw new CheckException("Sorry! You've requested identification codes too many times.", ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry! You've requested identification codes too many times.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'requested identification codes too many times')]")) {
            throw new CheckException("Sorry! You've requested identification codes too many times.", ACCOUNT_LOCKOUT);
        }
        // You Have Exceeded the Maximum Number of Log-In Attempts
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'You Have Exceeded the Maximum Number of Log-In Attempts')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We are unable to allow access into your account at this time.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'We are unable to allow access into your account at this time')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Your Account Cannot Currently Be Accessed
        if ($message = $this->http->FindSingleNode("//strong[contains(text(), 'Your Account Cannot Currently Be Accessed')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // This page is temporarily unavailable
        if ($this->http->FindSingleNode("//h3[contains(text(), 'This page is temporarily unavailable')]")
            && (
                strstr($this->http->currentUrl(), 'universalLogin/technicalError')
                || strstr($this->seleniumURL, 'universalLogin/technicalError')
            )
        ) {
            throw new CheckException("This page is temporarily unavailable. We apologize for the inconvenience. We're working on resolving the issue. Please try again later or contact us at 1-800-DISCOVER (1-800-347-2683).", ACCOUNT_PROVIDER_ERROR);
        }

        // skip activation card
        if ($this->http->FindSingleNode('//h2[contains(text(), "Let\'s activate your new card.")]')
            && ($link = $this->http->FindSingleNode("(//a[contains(text(), 'Go to Account Home')]/@href)[1]"))) {
            $this->logger->notice("skip activation card v.2");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            if ($this->loginSuccessful()) {
                return true;
            }
        }
        /*
         * zzzzzzzzzzz.
         *
         * You weren't really clicking around any more, so we logged you out for your protection.
         * To get back in, just log in on the right
         */
        if ($this->http->currentUrl() == 'https://www.discovercard.com/cardmembersvcs/loginlogout/app/timeout_confirmed'
            && $this->http->FindSingleNode('//p[contains(text(), "You weren\'t really clicking around any more, so we logged you out for your protection.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        // Discover Card: Account Unlock Redirect
        if ($this->http->FindSingleNode("//title[contains(text(), 'Discover Card: Account Unlock Redirect')]")) {
            throw new CheckException("You Have Exceeded the Maximum Number of Login Attempts", ACCOUNT_LOCKOUT);
        }

        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Your account cannot currently be accessed.')]")) {
            $this->DebugInfo = "access blocked";
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        if (
            $this->seleniumURL == 'https://portal.discover.com/customersvcs/universalLogin/logoff_confirmed'
            && $this->AccountFields['Login'] == 'vcarey2000'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->seleniumURL == 'https://www.discovercard.com/discover/data/account/error/common/strongauth_accesserror.shtml'
            /*
            && in_array($this->AccountFields['Login'], [
                'paulkistler123!',
                'ccyanger',
                'jleeann83',
                'rlshore3',
                'nancytuhtan',
                'robertg1a',
                'nagatokana',
            ])
            */
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function checkAnswers()
    {
        $this->logger->debug("state: " . var_export($this->State, true));

        if (isset($this->LastRequestTime)) {
            $timeFromLastRequest = time() - $this->LastRequestTime;
        } else {
            $timeFromLastRequest = SECONDS_PER_DAY * 30;
        }
        $this->logger->debug("time from last code request: " . $timeFromLastRequest);

        if ($timeFromLastRequest > 300 && (!empty($this->Answers[$this->questionIdCode]) || !empty($this->Answers[$this->questionIdCodeToPhone]))) {
            $this->http->Log("resetting answers, expired");
            unset($this->Answers[$this->questionIdCode]);
            unset($this->Answers[$this->questionIdCodeToPhone]);
        }
    }

    public function sendCodeToEmail()
    {
        $this->logger->notice("sending code to email / phone");

        // refs #6042
        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $email = $this->http->FindSingleNode("(//div[@class = 'email-input'])[1]");
        $this->logger->debug("email: $email");
        // phone
        if (!isset($email)) {
            $email = $this->http->FindSingleNode("(//tr[@class = 'phone-first']/td[1]/label)[1]");
            $this->logger->debug("phone: $email");
        }

        if ($this->http->FindPreg("/you like to receive (?:your|the) temporary identification code/ims") !== null
            && $this->http->ParseForm("codeChoiceForm") && isset($email)) {
            $this->logger->debug("sending code to email / phone: $email");
            $this->http->Form["codeChoice"] = 'EMAIL0';

            if (!strstr($email, '@')) {
                $this->http->Form["codeChoice"] = 'TEXT0';
            }
            $this->State["CodeSent"] = true;
            $this->State["CodeSentDate"] = time();

            // selenium hacks
            if ($this->http->FormURL == 'https://www.discover.com/cardmembersvcs/strongauth/app/oobRequest') {
                $this->http->FormURL = "https://card.discover.com/cardmembersvcs/strongauth/app/oobRequest";
            }

            if ($this->http->PostForm()) {
                $this->logger->debug("code form parsed");

                return true;
            }
        }

        return false;
    }

    public function ParseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode($this->questionQuery);
        $this->logger->debug("question found: " . $question);
        // Identification Code
        if (!isset($question)
            && $this->http->FindPreg("/you like to receive (?:your|the) temporary identification code/ims") !== null) {
            $this->logger->debug("ParseQuestion, v.2");
            $this->checkAnswers();
            $question = $this->questionIdCode;

            if (!$this->http->FindSingleNode("//div[@class = 'email-input']") && $this->http->FindSingleNode("//p[contains(@class, \"direction\") and strong]/text()[1]", null, true, "/(.*) as a/")) {
                $question = $this->questionIdCodeToPhone;
            }

            $this->logger->debug("question: " . $question);

            if (!isset($this->Answers[$question]) || $this->http->ParseForm("codeChoiceForm")) {
                $this->sendCodeToEmail();
//                $this->sendNotification("Discover. Code was sent");

                if ($this->http->FindSingleNode('//p[contains(@class, "direction") and strong[contains(text(), "text message")]]/text()[1]', null, true, "/(.*) as a/")) {
                    $question = $this->questionIdCodeToPhone;
                }
            }// if (!isset($this->Answers[$question]))
            else {
                $this->logger->debug(">> Answers exist: " . $this->Answers[$question]);
            }

            if (!$this->http->ParseForm("codeEntryForm")) {
                $this->logger->error("failed to find answer form");
                $this->strangeRedirect();

                return false;
            }
        }// if (!isset($question) && ...)
        // Just question
        elseif (!$this->http->ParseForm("challengeForm") || !isset($question)) {
            $this->logger->error("failed to find answer form or question");

            return false;
        }

        if ($question == $this->questionIdCode && $this->getWaitForOtc()) {
            $this->sendNotification("refs #21207 - mailbox was found // RR");
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if (isset($this->Question) && strstr($this->Question, 'Previous codes will not work.')) {
            $this->logger->debug("ProcessStep, v.2");
            $this->logger->debug("Current URL: " . $this->http->currentUrl());

            $this->logger->debug("ok, proceed to code entering");
            $this->http->SetInputValue("oobCode", $this->Answers[$this->Question]);
            $this->http->SetInputValue("pm_fp", $this->get_pm_fp());
            /*
             * Would you like us to remember this computer?
             * (*) Yes. This is my personal computer.
             * ( ) No. This is a public or shared computer.
             */
            $this->http->SetInputValue("bindDeviceOption", "true");

            if ($this->http->PostForm() || $this->http->Response['code'] != 200) {
                $this->logger->debug("the code was entered");
                unset($this->Answers[$this->questionIdCode]);
//                $this->sendNotification("discover. Code was entered");
                // Invalid Code. Please re-enter.
                if ($this->http->FindSingleNode("//p[contains(text(), 'Invalid Code. Please re-enter.') and @style = 'display : block;']")
                    || $this->http->FindPreg("/<p class=\"err-msg entry-error\" style = \"display : block;\" >- Invalid code. Please re\-enter\. If your next attempt is invalid\, you will be asked to contact us\.</")) {
                    $this->logger->error(">>> Invalid Code. Please re-enter.");
                    $this->sendCodeToEmail();
                    $this->AskQuestion($this->Question, "Invalid Code. Please re-enter.", "Question");

                    return false;
                }
                // Expired identification code.
                if ($this->http->FindSingleNode("//p[contains(text(), 'Expired identification code.') and @style = 'display : block;']")) {
                    $this->logger->debug(">>> Expired identification code.");
                    $this->logger->debug("remove answer");
                    unset($this->Answers[$this->questionIdCode]);

                    throw new CheckRetryNeededException(2, 1);
                }
                $this->strangeRedirect();

                // 404 after code posting    // refs #13882
                if (
                    $this->http->Response['code'] != 200
                    || strstr($this->http->currentUrl(), 'discover/data/misc/error404.shtml')
                ) {
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                }

                $this->forceRedirect();

                $this->skipOffer();

                // it helps
                if ($this->http->currentUrl() == 'https://portal.discover.com/customersvcs/universalLogin/logoff_confirmed') {
                    throw new CheckRetryNeededException(2, 1);
                }

                return true;
            }// if ($this->http->PostForm())
        }// if (isset($this->Question) && strstr($this->Question, 'Previous codes will not work.'))
        else {
            $this->logger->debug("ProcessStep, v.1");
            $this->http->Form["challengeAnswer"] = $this->Answers[$this->Question];
            $this->http->PostForm();

            if ($this->http->FindPreg("/(we ended your Account Center session after extended inactivity)/ims") !== null) {
                $this->logger->notice("Timed out");
                $this->http->removeCookies();

                return false;
            }
            $refresh = $this->http->FindPreg('/<meta http-equiv="Refresh" content="10;URL=([^"]+)">/ims');

            if (isset($refresh)) {
                $this->http->NormalizeURL($refresh);
                $this->http->GetURL($refresh);
            }
            $question = $this->http->FindSingleNode($this->questionQuery);

            if (isset($question)) {
                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Question = $question;
                $this->Step = "question";

                if (!$this->http->ParseForm("challengeForm")) {
                    $this->logger->error("failed to find answer form");
                }
            }
            $error = $this->http->FindSingleNode("//span[@class='error']/strong");

            if (isset($error)) {
                $this->ErrorMessage = $error;
            }
            //# Please call Discover Customer Service at 1-888-251-8003 for information about accessing your Account online.
            if ($message = $this->http->FindSingleNode("//div[@id = 'error']/p[contains(text(), 'for information about accessing your Account online')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($error) || isset($question)) {
                return false;
            }
        }

        return true;
    }

    public function strangeRedirect()
    {
        $this->logger->notice(__METHOD__);
        // Opening error page...
        if ($errorPage = $this->http->FindPreg("/<meta http-equiv=\"Refresh\" content=\"0;URL=(http:\/\/www\.discovercard\.com\/discover\/data\/account\/error\/common\/Bv_nonsecure\.shtml)\"/")) {
            $this->logger->debug("Opening error page...");
            $this->http->GetURL($errorPage);
            // We are experiencing technical difficulties
            if ($this->http->FindSingleNode("//p[contains(text(), 'Your request cannot be completed because the web server or the web application has experienced a technical error.')]")) {
                throw new CheckException("Your request cannot be completed because the web server or the web application has experienced a technical error.", ACCOUNT_PROVIDER_ERROR);
            }
        }
        // Sorry! You have submitted identification codes too many times.
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Sorry! You have submitted identification codes too many times.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->skipOffer();
    }

    public function forceRedirect()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = $this->http->currentUrl();
        $this->logger->debug("[CurrentURL]: {$currentUrl}");

        if (in_array($currentUrl, [
            'https://card.discover.com/web/customer/portal?',
            'https://card.discover.com/cardmembersvcs/achome/technicalError?',
            'https://card.discover.com/cardmembersvcs/achome/technicalError?source=achome&transOnly=Y',
            'https://portal.discover.com/web/customer/portal',
            'https://card.discover.com/web/achome/homepage?',
            'https://card.discover.com/?',
            'https://www.discover.com/?',
            'https://card.discover.com/cardissuer/transactions/v1/recent?source=achome&transOnly=Y',
        ])
        ) {
            $this->http->GetURL("https://portal.discover.com/enterprise/portal/customeraccountinfo/v1/summary");
        }
    }

    public function Parse()
    {
        $ficoscore = $this->http->FindSingleNode("(//a[contains(@href, 'ficoscore')]/@href)[1]");

        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'customer-name')]")));

        // Checking (new account type)
        $cards = $this->http->XPath->query("//div[@class = 'module']//tr[td[@class = 'first']/a[contains(@class, 'cashback-checking')]]");
        $this->logger->debug("Total {$cards->length} subCards were found");

        if ($cards->length > 0) {
            $this->sendNotification("discover - refs #15433. subCards {$cards->length}, debug");
        }

        foreach ($cards as $card) {
            $subAccount = $this->parseSubCards($card);

            if (!empty($subAccount)) {
                $subAccounts[] = $subAccount;
            }
        }
        $cardsV2 = $this->http->XPath->query("//div[contains(@class, 'category-group') and div[contains(@class, 'col-account-name')] and div/h4/em[contains(text(), 'Cashback Bonus') or contains(text(), 'Miles')]]");
        $this->logger->debug("Total {$cardsV2->length} subCards v.2 were found");

        foreach ($cardsV2 as $cardV2) {
            $subAccount = $this->parseSubCardsV2($cardV2);

            if (!empty($subAccount)) {
                $subAccounts[] = $subAccount;
            }
        }// foreach ($cardsV2 as $cardV2)

        // refs #21301
        if ($this->http->currentUrl() == "https://portal.discover.com/enterprise/portal/customeraccountinfo/v1/summary") {
            $summaryInfo = $this->http->JsonLog(null, 3, false, 'rewardsBalance');

            // debug
            if (isset($summaryInfo->redirectUrl)) {
                /*
                $this->http->GetURL($summaryInfo->redirectUrl);
                $this->http->GetURL("https://card.discover.com/cardissuer/transactions/v1/recent?source=achome&transOnly=Y");
                $summaryInfo = $this->http->JsonLog(null, 3, false, 'rewardsBalance');
                */
                $headers = [
                    "Accept"       => "application/json",
                    "Content-Type" => "application/json",
                ];
                $this->http->PostURL("https://card.discover.com/cardissuer/achome/v1/card-account-info", [], $headers);
                $accountInfo = $this->http->JsonLog(null, 3, false, 'offerQualPeriod');
                // Name
                $this->SetProperty("Name", beautifulName($accountInfo->displayName ?? null));
                // Balance
                $this->SetBalance($accountInfo->availableCashbackBonus ?? null);

                if ($accountInfo->incentiveTypeCode == 'MI2') {
                    $this->SetProperty("Currency", "Miles");
                }
                // Card Ending (main card)
                $this->SetProperty("Number", $accountInfo->last4CardNumber ?? null);

                if (isset($accountInfo->offers->offerDetails)) {
                    $this->logger->info('Discover 5% cashback tracking', ['Header' => 3]);

                    foreach ($accountInfo->offers->offerDetails as $offerDetail) {
                        // 5% Cashback Bonus
                        $cashBackPeriod = $offerDetail->offerQualStrtShortMth . "-" . $offerDetail->offerQualEndShortMth;
                        $description = $this->http->FindPreg("/Cashback Bonus Q\d+ (.+)/ims", false, $offerDetail->promotionText);

                        if ($description) {
                            $description = " for <br> {$description}";
                        }

                        if ($cashBackPeriod && $cashBackPeriod != '-') {
                            $propertyKey = 'CashBack';

                            if (!strstr($cashBackPeriod, 'Now')) {
                                $propertyKey = 'CashBackNextQuarter';
                            }

                            $cashBackPeriod = $this->currentQuarter($cashBackPeriod);

                            if ($offerDetail->offerEnrolled == true) {
                                $cashBackPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackPeriod}</a>";
                                $this->SetProperty($propertyKey, "Activated ({$cashBackPeriod}){$description}");
                            } else {
                                $this->logger->notice("CashBack not activated");
                                $cashBackPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackPeriod}</a>";
                                $this->SetProperty($propertyKey, "Not Activated ({$cashBackPeriod}){$description}");
                            }
                        }// if ($cashBackPeriod && $cashBackPeriod != '-')
                    }// foreach ($accountInfo->offers->offerDetails as $offerDetail)
                }// if (isset($accountInfo->offers->offerDetails))

                $this->http->GetURL("https://www.discovercard.com/cardmembersvcs/rewards/app/dashboard?ICMPGN=ACHOME_RWDSUMM_RWDDET_TXT");
                // Newly Earned Miles
                $this->SetProperty("NewlyEarned", $this->http->FindSingleNode("
                        //p[contains(text(), 'Newly Earned:')]
                        | //li[h4[contains(text(), 'Newly Earned')]]/text()[last()]
                    ", null, true, "/([$\d\.\,]+)/ims")
                    ?? $this->http->FindSingleNode("//span[@class = 'newly-earned-amount']/text()[1]")
                    ?? $this->http->FindSingleNode("//span[@class = 'title' and contains(text(), 'Newly')]/following-sibling::span[@class = 'cost']")
                );

                // refs #14493
                $this->getFICOLink();

                return;
            }// if (isset($summaryInfo->redirectUrl))

            // Name
            $this->SetProperty("Name", beautifulName($summaryInfo->firstName));
            // Cards
            $cardAccounts = $summaryInfo->customerAccountSummaryVO->cardSummaryVO->cardAccounts ?? [];
            $this->logger->debug("Total " . count($cardAccounts) . " cards were found");

            foreach ($cardAccounts as $cardAccount) {
                // ending in
                $number = $cardAccount->acctNbr;
                $displayName = $cardAccount->cardInfo->cardTypeDesc ?? 'Card';
                // Cashback Bonus Balance / Total Miles
                $balance = $cardAccount->rewardsSummary->rewardsBalance ?? null;
                // Newly Earned
                $this->logger->debug("Card -> $displayName: $balance");

                if (isset($number, $displayName, $balance)) {
                    if (count($cardAccounts) == 1 || $cardAccount->rewardsSummary->earningMiles == true) {
                        $this->SetBalance($balance);
                        $this->SetProperty("Number", $number);
                        $this->SetProperty("Currency", $cardAccount->rewardsSummary->earningMiles === false ? "$" : "Miles");

                        continue;
                    }

                    $subAccounts[] = [
                        'Code'        => 'discover' . $number,
                        'DisplayName' => $displayName . " ending in " . $number,
                        'Balance'     => $balance,
                        "Number"      => $number,
                        "Currency"    => $cardAccount->rewardsSummary->earningMiles === false ? "$" : "Miles",
                    ];
                }
            }// foreach ($cardAccounts as $cardAccount)

            if (!empty($subAccounts) && count($subAccounts) > 1) {
                foreach ($subAccounts as $s) {
                    if (strstr($s['DisplayName'], 'Discover it Card') || strstr($s['DisplayName'], 'Discover it chrome Card')) {
                        $this->SetBalanceNA();
                    }
                }// foreach ($subAccounts as $s)
            }// if (!empty($subAccounts) && count($subAccounts) == 1)
        }

        if (isset($subAccounts)) {
            // Set Sub Accounts
            $this->SetProperty("CombineSubAccounts", false);
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))

        // site bug fix
        $balance = $this->http->FindSingleNode("//li[contains(text(), 'Miles Balance')]/following-sibling::li[@class = 'amount']");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Miles Available')]/following-sibling::p[@class = 'equal-spacing']/span[contains(@class, 'miles-balance')]");

            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode('//h3[contains(text(), "Miles Available")]/following-sibling::div[1]//span[@class = "cashback-bonus-value"]');
            }
            // Newly Earned
            $newlyEarned = $this->http->FindSingleNode("//span[@class = 'newly-earned-miles']");

            if (!$newlyEarned) {
                $newlyEarned = $this->http->FindSingleNode("//p[@class = 'newly-earned-amt']", null, true, '#([$\d\.\,\-]+)#ims');
            }
            $this->SetProperty("NewlyEarned", $newlyEarned);
            // Card Ending (main card)
            $number = $this->http->FindSingleNode("//span[contains(@class, 'account-ending-number')]");

            if (!$number) {
                $number = $this->http->FindSingleNode('
                    //a[contains(@class, "card-details-text")]
                    | //span[@data-testid = "at-card-details"]
                ', null, true, "/ending\s*([\d]+)\)/");
            }
            $this->SetProperty("Number", $number);

            $this->cashbackProperties(false, $this->http);
        }

        if ($this->ErrorCode != ACCOUNT_CHECKED && isset($balance)) {
            $this->SetBalance($balance);
            $this->SetProperty("Currency", "Miles");
        }

        if ($this->ErrorCode != ACCOUNT_CHECKED) {
            if ($cardsV2->length > 0 || ($cardsV2->length == 0 && $cards->length == 0)) {
                // Balance - Total Cashback Bonus (main card)
                if (!$this->SetBalance($this->http->FindSingleNode('//p[contains(text(), "Newly Earned")]/preceding-sibling::strong[1]'))) {
                    if (!$this->SetBalance($this->http->FindSingleNode('//h2[contains(text(), "Cashback Bonus Summary")]/following-sibling::div[1]//span[contains(@class, "cashback-bonus-bal")]'))) {
                        $this->SetBalance($this->http->FindSingleNode('
                            //h3[contains(., "Cashback Bonus") and contains(., "Available")]/following-sibling::div[1]//span[@class = "cashback-bonus-value"]
                            | //span[@class = "rewards-balance-text"]
                        '));
                    }
                }
                // Newly Earned Miles
                $this->SetProperty("NewlyEarned", $this->http->FindSingleNode("
                        //p[contains(text(), 'Newly Earned:')]
                        | //li[h4[contains(text(), 'Newly Earned')]]/text()[last()]
                    ", null, true, "/([$\d\.\,]+)/ims")
                    ?? $this->http->FindSingleNode("//span[@class = 'newly-earned-amount']/text()[1]")
                    ?? $this->http->FindSingleNode("//span[@class = 'title' and contains(text(), 'Newly')]/following-sibling::span[@class = 'cost']")
                );

                // Card Ending (main card)
                $this->SetProperty("Number", $this->http->FindSingleNode("//p[contains(text(), \"Newly Earned\")]/ancestor::div[contains(@class, 'category-group')]//p[@class = 'card-last-digits']", null, true, "/\((\d+)/ims"));

                if (!isset($this->Properties["Number"])) {
                    $this->SetProperty("Number", $this->http->FindSingleNode(self::XPATH_NUMBER, null, true, "/\((\d+)/ims"));
                }

                if (!isset($this->Properties["Number"])) {
                    $this->SetProperty("Number", $this->http->FindSingleNode(self::XPATH_NUMBER_EXTENDED, null, true, "/\((\d+)/ims"));
                }

                // refs #15433
                if (/*$this->ErrorCode == ACCOUNT_CHECKED && */ isset($this->Properties['Number']) && !empty($this->Properties['SubAccounts'])) {
                    $subAccounts = $this->Properties['SubAccounts'];
                    unset($this->Properties['SubAccounts']);
                    unset($subAcc);
                    $this->logger->notice("remove duplicates from subAccounts");

                    foreach ($subAccounts as $subAcc) {
                        if ($subAcc['Number'] != $this->Properties['Number']) {
                            $this->AddSubAccount($subAcc);
                        } else {
                            if ($this->ErrorCode != ACCOUNT_CHECKED && isset($subAcc['Balance'])) {
                                $this->SetBalance($subAcc['Balance']);
                            }

                            if (isset($subAcc['CashBack'])) {
                                $this->SetProperty("CashBack", $subAcc['CashBack']);
                            }

                            if (isset($subAcc['CashBackNextQuarter'])) {
                                $this->SetProperty("CashBackNextQuarter", $subAcc['CashBackNextQuarter']);
                            }
                        }
                    }// foreach ($subAccounts as $subAcc)
                }// if ($this->ErrorCode == ACCOUNT_CHECKED && isset($this->Properties['Number']) && !empty($this->Properties['SubAccounts']))
                elseif (
                    $this->ErrorCode != ACCOUNT_CHECKED
                    && !empty($this->Properties['SubAccounts'])
                    && count($this->http->FindNodes(self::XPATH_NUMBER_EXTENDED)) > 1
                ) {
                    foreach ($this->Properties['SubAccounts'] as $s) {
                        if (strstr($s['DisplayName'], 'Discover it Card') || strstr($s['DisplayName'], 'Discover it chrome Card')) {
                            $this->SetBalanceNA();
                        }
                    }

                    if ($this->ErrorCode != ACCOUNT_CHECKED && !empty($this->Properties['SubAccounts']) && count($this->http->FindNodes(self::XPATH_NUMBER_EXTENDED)) >= 2) {
                        $this->SetBalanceNA();
                    }
                } elseif (
                    $this->ErrorCode != ACCOUNT_CHECKED
                    && $this->AccountFields['Login'] == 'smaaacd'
                    && count($this->http->FindNodes(self::XPATH_NUMBER_EXTENDED)) > 1
                ) {
                    $this->SetBalanceNA();
                }

                // 2 credit card with balance showing now
                if ($this->ErrorCode != ACCOUNT_CHECKED
                    && count($this->http->FindNodes('//p[contains(text(), "Newly Earned")]/preceding-sibling::strong[1]')) > 1
                    && !empty($subAccounts)) {
                    $this->SetBalanceNA();
                }
                // AccountID: 4207962
                elseif (
                    $this->ErrorCode != ACCOUNT_CHECKED
                    && !empty($this->Properties['Number'])
                    && !empty($this->Properties['Name'])
                    && !empty($this->Properties['Number'])
                    && $cardsV2->length == 0
                    && $cards->length == 0
                ) {
                    $this->SetWarning($this->http->FindSingleNode('//span[contains(text(), "We have detected unusual activity on your account.")]'));
                }
            }// if ($cardsV2->length > 0 || ($cardsV2->length == 0 && $cards->length == 0))
            else {
                $this->SetBalance($this->http->FindSingleNode("//li[contains(text(), 'Cashback Bonus Balance')]/following-sibling::li[@class = 'amount']"));
            }

            /*
            if ($this->ErrorCode == ACCOUNT_CHECKED) {
                $this->SetProperty("Currency", "$");
            }
            */
            // You do not have any open bank account
            if ($this->ErrorCode != ACCOUNT_CHECKED) {
                if ($noOpenAccounts = $this->http->FindSingleNode("//h4[contains(text(), 'You do not have any open bank account(s).')]")) {
                    $this->logger->notice($noOpenAccounts);
                }
            }
            // Your Discover Card account is currently unavailable.
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                $this->SetWarning($this->http->FindSingleNode("//h4[contains(text(), 'Your Discover Card account is currently unavailable.')]"));
            }
            // hard code - only DP CASHBACK CHECKING card was found in profile (AccountID: 1782039)
            if ($this->ErrorCode != ACCOUNT_CHECKED && $this->AccountFields['Login'] == 'prathi116'
                && !empty($this->Properties['Name']) && !empty($this->Properties['SubAccounts'])) {
                $this->SetBalanceNA();
            }
        }

        if ($cardsV2->length == 0) {
            $this->http->GetURL("https://www.discovercard.com/cardmembersvcs/rewards/app/dashboard?ICMPGN=ACHOME_RWDSUMM_RWDDET_TXT");
            // We've detected unusual activity on your account. (Main card)
            if ($message = $this->http->FindSingleNode("//p[contains(text(), 'For your added security, the feature you are trying to access is not available for your Discover card account at this time.')]")) {
                $this->http->GetURL("https://www.discovercard.com/cardmembersvcs/achome/homepage?ICMPGN=SSO_PORTAL_CARD_DISCOVER_CARD_TXT");
            }

            if (($message = $this->http->FindPreg("/(Account Center Maintenance in Process)/ims"))
                || ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently updating our system')]"))
                || ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your request cannot be completed on the site at this time')]"))
                || ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry for the inconvenience, but we are performing system maintenance')]"))
                || ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry for the inconvenience, but we are performing system maintenance')]"))
                || ($message = $this->http->FindSingleNode('//span[@class = "bodyText"]//node()[contains(., "Due to a system update, you won\'t be able to view or redeem your rewards online")]'))
                || ($message = $this->http->FindSingleNode("//p[contains(normalize-space(text()), 'Our rewards experience just hit a snag.')]"))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $balance = $this->http->FindPreg("/<h4>Miles Balance<\/h4> (\S+) Miles/ims");

            if (isset($balance)) {
                $this->SetBalance($balance);
                $this->SetProperty("Currency", "Miles");
            }

            if ($this->ErrorCode != ACCOUNT_CHECKED) {
                $this->SetBalance($this->http->FindPreg('/<h4>Cashback Bonus Balance<\/h4>\s*\$([^<]+)</ims'));
                // (new account type)
                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->SetBalance($this->http->FindPreg('/Cashback Bonus<\/em>\s*Balance\s*<\/h4>\s*\$([^<]+)/ims'));
                }
                // New design
                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->SetBalance($this->http->FindSingleNode("//span[@class = 'title' and contains(., 'Cashback Bonus Balance')]/following-sibling::span[@class = 'cost']"));
                }
                // We've detected unusual activity on your account. (Main card)
                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->SetBalance($this->http->FindSingleNode("//li[contains(text(), 'Cashback Bonus Balance')]/following-sibling::li[@class = 'amount']"));
                }

                if ($this->ErrorCode != ACCOUNT_CHECKED) {
                    $this->SetBalance($this->http->FindSingleNode('//h2[contains(., "Cashback Bonus Available")]/following-sibling::p[@class = "cashback_avail_bonus"]'));
                }

                if ($this->ErrorCode == ACCOUNT_CHECKED) {
                    $this->SetProperty("Currency", "$");
                }
                // You do not have any open bank account
                if ($this->ErrorCode != ACCOUNT_CHECKED && !empty($noOpenAccounts)) {
                    throw new CheckException($noOpenAccounts, ACCOUNT_PROVIDER_ERROR);
                }
            }
            // Earned Since Anniversary
            $this->SetProperty('EarnedSinceAnniversary', $this->http->FindPreg("/<h4>Earned\s*Since\s*Anniversary<\/h4>\s*(\S+)\s*Miles/ims"));

            if (!isset($this->Properties["EarnedSinceAnniversary"])) {
                $this->SetProperty('EarnedSinceAnniversary', $this->http->FindPreg("/<h4>\s*Earned\s*Since[^<]+Anniversary<\/h4>\s*\$([^<]+)</ims"));

                if (isset($this->Properties["EarnedSinceAnniversary"]) && strstr($this->Properties["EarnedSinceAnniversary"], 'freemarker.core.InvalidReferenceException:')) {
                    $this->logger->notice(">>> remove broken property 'EarnedSinceAnniversary'");
                    unset($this->Properties["EarnedSinceAnniversary"]);
                }
            }

            if (!isset($this->Properties["EarnedSinceAnniversary"])) {
                $this->SetProperty('EarnedSinceAnniversary', $this->http->FindSingleNode("//li[h4[contains(text(), 'Earned Since')]]/text()[last()]", null, true, "/([\d\.\,]+)/ims"));
            }

            if (!isset($this->Properties["EarnedSinceAnniversary"])) {
                $this->SetProperty('EarnedSinceAnniversary', $this->http->FindSingleNode("//span[@class = 'title' and contains(text(), 'Earned since')]/following-sibling::span[@class = 'cost']"));
            }
            // Newly Earned Miles
            $this->SetProperty("NewlyEarned", $this->http->FindSingleNode("//li[h4[contains(text(), 'Newly Earned')]]/text()[last()]", null, true, "/([$\d\.\,]+)/ims"));

            if (!isset($this->Properties["NewlyEarned"])) {
                $this->SetProperty('NewlyEarned', $this->http->FindSingleNode("//span[@class = 'title' and contains(text(), 'Newly')]/following-sibling::span[@class = 'cost']"));
            }
            // Account Ending
            $this->SetProperty("Number", $this->http->FindPreg("/Account\s*Ending\s*in\s*(\d+)/ims"));
            // Name
            $name = $this->http->FindSingleNode("//li[@class = 'name']");

            if (isset($name)) {
                $this->SetProperty("Name", beautifulName($name));
            }
        }// if ($cardsV2->length == 0)

        // refs #14493
        $this->getFICO($ficoscore);

        if (!$ficoscore) {
            $this->getFICOLink();
        }
    }

    public function cashbackProperties($subAccount = false, $browser)
    {
        /** @var $browser HttpBrowser */
        $cashBack = null;
        $cashBackNextQuarter = null;
        $this->logger->info('Discover 5% cashback tracking', ['Header' => 3]);
        // Discover 5% cashback tracking    // refs #15433
        $this->logger->notice("Try to find 5% Cashback Bonus");

        // 5% Cashback Bonus
        $cashBackPeriod = $browser->FindSingleNode('
            //span[contains(@class, "cbb-category-status") and contains(.,"Earning")]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
            | //div[@class = "last-spacing"]/following-sibling::div[1]//a[span[contains(., "Earning")]]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
            | //div[p[@class = "now-date-text"] and following-sibling::p[position() = 1 and contains(., "Earning")]]
            | //div[not(contains(@class, "hide"))]/div[div[p[@class = "now-date-text"]] and following-sibling::p[position() = 1 and contains(., "Earning")]]
            | //div[contains(@class, "offers-container")]/div[position() = 1]//div[contains(@class, "rewards-enrolled")]/preceding-sibling::div[contains(@class, "qtr-text")]
        ', null, true, "/([^\d\s]+)/ims");

        if ($cashBackPeriod) {
            $description = $browser->FindSingleNode('
                //span[contains(@class, "cbb-category-status") and contains(.,"Earning")]/following-sibling::span[contains(@class, "cashback-place-link")]
                | //div[@class = "last-spacing"]/following-sibling::div[1]//a[span[contains(., "Earning")]]/following-sibling::span
                | //div[p[@class = "now-date-text"]]/following-sibling::p[position() = 1 and contains(., "Earning")]/following-sibling::p[1]
                | //div[not(contains(@class, "hide"))]/div[div[p[@class = "now-date-text"]] and following-sibling::p[position() = 1 and contains(., "Earning")]]/ancestor::div[contains(@class, "activation-duration")]/following-sibling::p[contains(@class, "cashback-content")]
                | //div[contains(@class, "offers-container")]/div[position() = 1 and //div[contains(@class, "rewards-enrolled")]]/div[contains(@class, "offer-title")]
            ');

            if ($description) {
                $description = " for <br> {$description}";
            }
            $cashBackPeriod = $this->currentQuarter($cashBackPeriod);
            $cashBackPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackPeriod}</a>";

            if (!$subAccount) {
                $this->SetProperty("CashBack", "Activated ({$cashBackPeriod}){$description}");
            } else {
                $cashBack = "Activated ({$cashBackPeriod}){$description}";
            }
        } else {
            $this->logger->notice("CashBack period not found");
//            $this->sendNotification("discover - CashBack period not found");
            $cashBackPeriod = $browser->FindSingleNode('
                //a[contains(.,"Activate Now")]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
                | //div[@class = "last-spacing"]/following-sibling::div[1]//a[span[normalize-space(text()) = "Activate"]]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
                | //div[contains(@class, "cbbOffer") and position() = 2]//div/p[a[contains(text(), "Activate")]]/span[@class = "quarter-interval"]
                | //div[contains(@class, "offers-container")]/div[position() = 1]/div[contains(@class, "rewards-container-unenrolled")]/div[contains(@class, "qtr-text")]
            ', null, true, "/([^\d\s]+)/ims");

            if ($cashBackPeriod) {
                $description = $browser->FindSingleNode('
                    //div[@class = "last-spacing"]/following-sibling::div[1]//a[span[normalize-space(text()) = "Activate"]]/following-sibling::span
                    | //div[contains(@class, "cbbOffer") and position() = 2]//div[p[a[contains(text(), "Activate")] and span[@class = "quarter-interval"]]]/following-sibling::p[1]
                    | //div[contains(@class, "offers-container")]/div[position() = 1]/div[contains(@class, "rewards-container-unenrolled")]/following-sibling::div[contains(@class, "offer-title")]
                ');

                if ($description) {
                    $description = " for <br> {$description}";
                }
//                $this->sendNotification("discover - refs #15433. CashBack Not Activated");
                $cashBackPeriod = $this->currentQuarter($cashBackPeriod);
                $cashBackPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackPeriod}</a>";

                if (!$subAccount) {
                    $this->SetProperty("CashBack", "Not Activated ({$cashBackPeriod}){$description}");
                } else {
                    $cashBack = "Not Activated ({$cashBackPeriod}){$description}";
                }
            }// if ($cashBackPeriod)
        }
        // 5% cash back next quarter
        $cashBackNextQuarterDescription = $browser->FindSingleNode('
            //span[contains(@class, "cbb-category-status") and contains(.,"Activated")]/following-sibling::span[contains(@class, "cashback-place-link")]
            | //div[@class = "last-spacing"]/following-sibling::div[2]//a[span[contains(., "Earning")]]/following-sibling::span
            | //div[p[@class = "now-date-text"]]/following-sibling::p[position() = 3 and contains(., "Earning")]/following-sibling::p[1]
            | //div[contains(@class, "cbbOffer") and position() = 3]//div[div[not(contains(@class, "hide"))]/div[contains(@class, "now-date") and following-sibling::p[contains(., "Activated")]]//p[@class = "now-date-text"]]/following-sibling::p
            | //div[contains(@class, "offers-container")]//div[position() = 2 and contains(@class, "rewards-enrolled")]/following-sibling::div[contains(@class, "offer-title")]
        ');

        $cashBackNextQuarterPeriod = $browser->FindSingleNode('
            //span[contains(@class, "cbb-category-status") and contains(.,"Activated")]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
            | //div[contains(@class, "cbbOffer") and position() = 3]//div[not(contains(@class, "hide"))]/div[contains(@class, "now-date") and following-sibling::p[contains(., "Activated")]]//p[@class = "now-date-text"]
            | //div[contains(@class, "offers-container")]//div[position() = 2 and contains(@class, "rewards-enrolled")]/div[contains(@class, "qtr-text")]
        ', null, true, "/([^\d\s]+)/ims");

        if (!$cashBackNextQuarterPeriod) {
            $cashBackNextQuarterPeriod = $browser->FindPreg("/([^\d\s]+)/ims", false, implode('', $browser->FindNodes('//div[p[@class = "now-date-text"]]/following-sibling::p[position() = 3 and contains(., "Earning")]/span')));
        }

        if ($cashBackNextQuarterPeriod && $cashBackNextQuarterDescription) {
//            $this->sendNotification("discover - refs #15433. CashBackNextQuarter");
            if ($cashBackNextQuarterDescription) {
                $cashBackNextQuarterDescription = " for <br> {$cashBackNextQuarterDescription}";
            }
            $cashBackNextQuarterPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackNextQuarterPeriod}</a>";

            if (!$subAccount) {
                $this->SetProperty("CashBackNextQuarter", "Activated ({$cashBackNextQuarterPeriod}){$cashBackNextQuarterDescription}");
            } else {
                $cashBackNextQuarter = "Activated ({$cashBackNextQuarterPeriod}){$cashBackNextQuarterDescription}";
            }
        }// if ($cashBackNextQuarterPeriod && $cashBackNextQuarterDescription)
        else {
            $this->logger->notice("CashBackNextQuarter period not found");
//            $this->sendNotification("discover - CashBackNextQuarter period not found");
            $cashBackNextQuarterDescription = $browser->FindSingleNode('
                //div[span[contains(@class, "cbb-category-status") and contains(.,"Earning")]]/following-sibling::div[1]//a[span[text() = "Activate"] and contains(@class, "cashback-place")]/following-sibling::span[contains(@class, "cashback-place-link")]
                | //div[@class = "last-spacing"]/following-sibling::div[2]//a[span[normalize-space(text()) = "Activate"]]/following-sibling::span
                | //div[p[@class = "now-date-text"]]/following-sibling::p[position() = 3 and contains(., "Activate")]/following-sibling::p[1]
                | //div[contains(@class, "offers-container")]/div[position() = 2]/div/following-sibling::div[contains(@class, "offer-title")]
            ');

            if (!$cashBackNextQuarterDescription) {
                $cashBackNextQuarterDescription = $browser->FindSingleNode('//div[contains(@class, "cbbOffer") and position() = 3]//div[p[a[contains(text(), "Activate")] and span[@class = "quarter-interval"]]]/following-sibling::p[1]', null, true, "/([^\d\s]+)/ims");
            }

            $cashBackNextQuarterPeriod = $browser->FindSingleNode('
                //div[span[contains(@class, "cbb-category-status") and contains(.,"Earning")]]/following-sibling::div[1]//a[span[text() = "Activate"] and contains(@class, "cashback-place")]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
                | //div[@class = "last-spacing"]/following-sibling::div[2]//a[span[normalize-space(text()) = "Activate"]]/preceding-sibling::span[contains(@class, "cashback-period")]/span[@aria-hidden]
                | //div[contains(@class, "offers-container")]/div[position() = 2]/div/div[contains(@class, "qtr-text")]
            ', null, true, "/([^\d]+)/ims");

            if (!$cashBackNextQuarterPeriod) {
                $cashBackNextQuarterPeriod = $browser->FindPreg("/([^\d\s]+)/ims", false, implode('', $browser->FindNodes('//div[p[@class = "now-date-text"]]/following-sibling::p[position() = 3 and contains(., "Activate")]/span')));
            }

            if (!$cashBackNextQuarterPeriod) {
                $cashBackNextQuarterPeriod = $browser->FindSingleNode('//div[contains(@class, "cbbOffer") and position() = 3]//div/p[a[contains(text(), "Activate")]]/span[@class = "quarter-interval"]');
            }

            if ($cashBackNextQuarterPeriod && $cashBackNextQuarterDescription) {
//                $this->sendNotification("discover - refs #15433. CashBackNextQuarter Not Activated");
                $cashBackNextQuarterPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackNextQuarterPeriod}</a>";

                if ($cashBackNextQuarterDescription) {
                    $cashBackNextQuarterDescription = " for <br> {$cashBackNextQuarterDescription}";
                }
                $cashBackNextQuarterPeriod = "<a target='_blank' href='https://awardwallet.com/blog/link/DiscoverQuarterlyBonus'>{$cashBackNextQuarterPeriod}</a>";

                if (!$subAccount) {
                    $this->SetProperty("CashBackNextQuarter", "Not Activated ({$cashBackNextQuarterPeriod}){$cashBackNextQuarterDescription}");
                } else {
                    $cashBackNextQuarter = "Not Activated ({$cashBackNextQuarterPeriod}){$cashBackNextQuarterDescription}";
                }
            }// if ($cashBackPeriod)
        }

        return ['CashBack' => $cashBack, 'CashBackNextQuarter' => $cashBackNextQuarter];
    }

    public function currentQuarter($cashBackPeriod)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("CashBack period: {$cashBackPeriod}");

        if (!strstr($cashBackPeriod, 'Now')) {
            return $cashBackPeriod;
        }
        $current_quarter = ceil(date('n') / 3);
        $this->logger->debug("current_quarter: {$current_quarter}");

        switch ($current_quarter) {
            case 1:
                $cashBackPeriod = 'Jan-Mar';

                break;

            case 2:
                $cashBackPeriod = 'Apr-Jun';

                break;

            case 3:
                $cashBackPeriod = 'Jul-Sep';

                break;

            case 4:
                $cashBackPeriod = 'Oct-Dec';

                break;
        }// switch ($current_quarter)
        $this->logger->notice("CashBack period: {$cashBackPeriod}");

        return $cashBackPeriod;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = "http://www.discovercard.com/";

        return $arg;
    }

    public function parseSubCards($card)
    {
        $this->logger->notice(__METHOD__);
        $subAccount = [];
        // ending in
        $number = $this->http->FindSingleNode("td[@class = 'first']", $card, true, "/ending\s*in\s*(\d+)/ims");
        $displayName = $this->http->FindSingleNode("td[@class = 'first']", $card);
        // Cashback Bonus Balance
        $balance = $this->http->FindSingleNode("td[contains(text(), 'Cashback Bonus Balance')]/div/strong", $card);

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("td[@class = 'fourth']/span[@class = 'num']", $card);
        }
        // Newly Earned
        $newlyEarned = $this->http->FindSingleNode(".//p[contains(text(), 'Newly Earned:')]", $card, true, "/:\s*([^<]+)/ims");
        $this->logger->debug("Card -> $displayName: $balance");

        if (isset($number, $displayName, $balance)) {
            $subAccount = [
                'Code'        => 'discover' . $number,
                'DisplayName' => $displayName,
                'Balance'     => $balance,
                "Number"      => $number,
                "Currency"    => "$",
                "NewlyEarned" => $newlyEarned,
            ];
        }

        return $subAccount;
    }

    public function parseSubCardsV2($card)
    {
        $this->logger->notice(__METHOD__);
        $subAccount = [];
        // ending in
        $number = $this->http->FindSingleNode(".//p[@class = 'card-last-digits']", $card, true, "/\((\d+)/ims");
        $displayName = $this->http->FindSingleNode(".//p[@class = 'account-name']", $card);
        // Cashback Bonus Balance / Total Miles
        $balance = $this->http->FindSingleNode(".//h4[em[contains(text(), 'Cashback Bonus') or contains(text(), 'Miles')]]/following-sibling::strong", $card);
        // Newly Earned
        $newlyEarned = $this->http->FindSingleNode(".//p[contains(text(), 'Newly Earned:')]", $card, true, "/:\s*([^<]+)/ims");
        $this->logger->debug("Card -> $displayName: $balance");

        if (isset($number, $displayName, $balance)) {
            $subAccount = [
                'Code'        => 'discover' . $number,
                'DisplayName' => $displayName . " ending in " . $number,
                'Balance'     => $balance,
                "Number"      => $number,
                "Currency"    => strstr($balance, "$") ? "$" : "Miles",
                "NewlyEarned" => $newlyEarned,
            ];
        }
        // Discover 5% cashback tracking    // refs #15433
        $browser = clone $this->http;
        $this->http->brotherBrowser($browser);

        if ($cashbackLink = $this->http->FindSingleNode("(.//a[contains(@class, 'account-subcategory') and not(@id)]/@href)[1]", $card)) {
            $browser->GetURL($cashbackLink);
            $cashbackProperties = $this->cashbackProperties(true, $browser);
            $subAccount['CashBack'] = $cashbackProperties['CashBack'];
            $subAccount['CashBackNextQuarter'] = $cashbackProperties['CashBackNextQuarter'];
        }

        return $subAccount;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency']) && ($properties['Currency'] == '$')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");
        } elseif (isset($properties['Currency']) && ($properties['Currency'] == 'Miles')) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d Miles");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "");
        }
    }

    private function getFICOLink()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://portal.discover.com/enterprise/navigation-api/v1/navigation/card?");
        $navigation = $this->http->JsonLog(null, 2);
        $navigationItems = $navigation->navigationItems ?? [];

        foreach ($navigationItems as $navigationItem) {
            if ($navigationItem->heading != 'Activity') {
                continue;
            }

            foreach ($navigationItem->items as $item) {
                if ($item->id != 'fico') {
                    continue;
                }

                $ficoscore = $item->link;
                $this->getFICO($ficoscore);

                break;
            }// foreach ($navigationItem->items as $item)
        }// foreach ($navigation->navigationItems as $navigationItem)
    }

    private function getFICO($ficoscore)
    {
        if (!$ficoscore) {
            return;
        }

        $this->logger->info('FICO® Score', ['Header' => 3]);
        $this->http->NormalizeURL($ficoscore);
        $this->http->GetURL($ficoscore);
        // FICO® SCORE
        $fcioScore = $this->http->FindSingleNode("//span[@class = 'ff-score']"); // deprecated?

        if (!$fcioScore) {
            $fcioScore = $this->http->FindSingleNode("//span[@id = 'score-num']");
        }
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindSingleNode("//div[@class = 'fico-score-date']", null, true, "/of\s*([^<]+)/"); // deprecated?

        if (!$fcioUpdatedOn) {
            $fcioUpdatedOn = $this->http->FindSingleNode("//span[@class = 'date-details']");
        }

        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1 && $this->Balance === null) {
                foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                    if (in_array($key, ['Code', 'DisplayName'])) {
                        continue;
                    } elseif ($key == 'Balance') {
                        $this->SetBalance($value);
                    } elseif ($key == 'ExpirationDate') {
                        $this->SetExpirationDate($value);
                    } else {
                        $this->SetProperty($key, $value);
                    }
                }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                unset($this->Properties['SubAccounts']);
            }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
            $this->SetProperty("CombineSubAccounts", false);
            $this->AddSubAccount([
                "Code"               => "discoverFICO",
                "DisplayName"        => "FICO® Bankcard Score 8 (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//a[contains(@href, 'logout')]/@href")
            || $this->http->FindSingleNode("//div[@id = 'greetmsg']")
            || $this->http->FindSingleNode("//button[contains(text(), 'Log Out')]")
            || $this->http->FindSingleNode("//h1[@data-testid = 'greetingMessage']")
            || $this->http->FindSingleNode("//span[@class = 'cm-name' or contains(@class, 'greeting-name')]")) {
            return true;
        }

        return false;
    }

    private function skipOffer()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
        // skip offer
        if (($this->http->FindSingleNode("//a[contains(text(), 'Maybe Later') or contains(text(), 'Maybe later')]")
                || $this->http->FindSingleNode("//a[contains(@alt, 'See offers')]/@alt")
                || $this->http->FindSingleNode("//a[contains(@title, 'Update Now')]/@title")
                || $this->http->FindSingleNode("//a[contains(@title, 'Go Paperless')]/@title"))
            && ($declineIntercept = $this->http->FindSingleNode("//a[contains(text(), 'Continue to Account Home')]/@onclick", null, true, '/declineIntercept\(\'icmpgn=([^\']+)/ims'))) {
            $this->logger->notice("Skip offer -> {$declineIntercept}");

            if ($this->http->ParseForm("interceptStoreForm")) {
                $this->http->SetInputValue("hjckAcptInd", "1101_hjk_IncomeCapture_OVER_top_cnt");
                $this->http->PostForm();
                // provider bug fix
                if ($this->http->Response['code'] == 500) {
                    $this->logger->notice("Provider bug fix: force redirect");
                    $this->http->GetURL(self::REWARDS_PAGE_URL);
                }// if ($this->http->Response['code'] == 500)
            }//if ($this->http->ParseForm("interceptStoreForm"))

            // Skip Balance Transfer page
            if (stristr($this->http->currentUrl(), '.com/cardmembersvcs/portfoliobt/app/toSplashPage')
                || stristr($this->http->currentUrl(), 'https://www.discovercard.com/cardmembersvcs/personalprofile/pp/MyProfilePage')) {
                $this->logger->notice("Skip 'Balance Transfer'/Profile page");
                $this->http->GetURL(self::REWARDS_PAGE_URL);
            }
        }
        /**
         * Welcome to Cardmember Support Center.
         * Please continue to our secure site by selecting Continue below.
         * CardmemberSupport.com can help you meet your specific account needs.
         *
         * You may be eligible for:
         * - Flexible payment options
         * - Reduced Monthly Payments
         * - Other balance reduction options
         */
        if ($this->http->FindSingleNode("//h1[@id = 'title']", null, true, "/Welcome to Cardmember Support Center.\s*Please continue to our secure site by selecting Continue below\./") && $this->http->ParseForm("myform")) {
            $this->http->PostForm();
            $this->logger->notice("Skip 'secure site' page");
            $this->http->GetURL(self::REWARDS_PAGE_URL);

            // AccountID: 2851408
            if ($this->AccountFields['Login'] == 'kameleonkidd' && $this->http->currentUrl() == 'https://portal.discover.com/customersvcs/universalLogin/logoff_confirmed') {
                $this->SetBalanceNA();
            }
        }
        // Skip account personalization
        if (stristr($this->http->currentUrl(), 'https://card.discover.com/cardmembersvcs/personalprofile/ppw/wizard')
            // Skip view of all cards
            || stristr($this->http->currentUrl(), '/cardmembersvcs/cardinventory/action/viewAllCards')) {
            $this->logger->notice("Skip account personalization / view of all cards");
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        }// if (stristr($this->http->currentUrl(), 'https://card.discover.com/cardmembersvcs/personalprofile/ppw/wizard'))
        // skip update profile
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Take Action. Update Your Profile Today.')]")
            && ($declineIntercept = $this->http->FindSingleNode("//a[contains(text(), 'No Thanks')]/@onclick", null, true, '/declineIntercept\(\'icmpgn=([^\']+)/ims'))) {
            $this->logger->notice("Skip update profile -> {$declineIntercept}");

            if ($this->http->ParseForm("interceptStoreForm")) {
                $this->http->SetInputValue("hjckAcptInd", $declineIntercept);
                $this->http->PostForm();
            }//if ($this->http->ParseForm("interceptStoreForm"))
        }

        if ($this->http->FindSingleNode("//h1[contains(text(), \"We've made your login simpler.\")]")
            && $this->http->FindSingleNode("//a[contains(text(), 'Skip for Now')]")) {
            $this->logger->notice("Skip reminder");

            $headers = ["X-Requested-With" => "XMLHttpRequest", "Accept" => "*/*"];
            $this->http->RetryCount = 0;
//            $this->http->OptionsURL("https://portal.discover.com/customersvcs/ssomerge/ssoskip", [], $headers);
            $this->http->PostURL("https://portal.discover.com/customersvcs/ssomerge/ssoskip", ["_csrf" => $this->http->getCookieByName("XSRF-TOKEN")], $headers);
            $this->http->RetryCount = 2;
            $this->http->GetURL(self::REWARDS_PAGE_URL);
        } elseif ($this->http->FindSingleNode("//h1[contains(text(), \"We've made your login simpler.\")]")
            && $this->http->FindSingleNode("//a[contains(text(), 'No, Thank You')]")) {
            $this->throwProfileUpdateMessageException();
        }
    }

    /*
    function ParseFiles($filesStartDate) {
        $this->http->TimeLimit = 500;
        $this->http->GetURL("https://www.discovercard.com/cardmembersvcs/statements/app/stmt?ICMPGN=AC_NAV_L3_STMTS_TXT");
        $accountName = $this->http->FindSingleNode("//li[@class = 'card-type']");
        $result = [];
        $statements = $this->http->XPath->query("//div[@id = 'statement-items']//tr[td]");
        $this->logger->debug("Total {$statements->length} statements were found");
        $files = [];
        for ($i = 0; $i < $statements->length; $i++) {
            $statement = $statements->item($i);
            $file = [
                'title' => $this->http->FindSingleNode("td[1]", $statement),
                'id' => $this->http->FindSingleNode("td[2]/a/@href", $statement),
            ];
            $this->logger->debug("node: {$file['title']}, {$file['id']}");
            $this->http->NormalizeURL($file['id']);
            $files[] = $file;
        }
        foreach ($files as $file) {
            $this->logger->debug("downloading {$file['title']}, {$file['id']}");
            $date = null;
            if (!empty($file['title']) && preg_match('#\-\s*([^<]+)$#ims', $file['title'], $matches))
                $date = strtotime($matches[1]);
            $code = null;
            if (intval($date) >= $filesStartDate) {
                $fileName = $this->http->DownloadFile($file['id']);

                if (strpos($this->http->Response['body'], '%PDF') === 0) {
                    $result[] = [
                        "FileDate" => $date,
                        "Name" => $file["title"],
                        "Extension" => "pdf",
                        "AccountNumber" => (isset($this->Properties["Number"])) ? $this->Properties["Number"] : null,
                        "AccountName" => $accountName,
                        "AccountType" => '',
                        "Contents" => $fileName,
                    ];
                }// if (strpos($this->http->Response['body'], '%PDF') === 0)
                else
                    $this->logger->notice("not a PDF");
            }// if (intval($date) >= $filesStartDate)
            else
                $this->logger->notice("skip by date");
        }// foreach ($files as $file)

        return $result;
    }
    */
}
