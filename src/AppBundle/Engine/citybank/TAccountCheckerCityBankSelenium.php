<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCityBankSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const OTHER_REGIONS = ['Australia', 'Singapore', 'HongKong'];
    /**
     * @var HttpBrowser
     */
    public $browser;

    private $closedCards = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();
        $this->http->saveScreenshots = true;

        if (!in_array($this->AccountFields['Login2'], self::OTHER_REGIONS)) {
//            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_99);
//            $this->seleniumOptions->addHideSeleniumExtension = false;
//            $this->seleniumOptions->userAgent = null;

            if ($this->attempt >= 2) {
                $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
//                $this->useChromePuppeteer();
//                $this->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
//                $this->seleniumOptions->addHideSeleniumExtension = false;

                return;
            }

            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
//            $this->seleniumOptions->addHideSeleniumExtension = false;

            if (!isset($this->State['Fingerprint']) || $this->attempt > 0) {
                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if ($fingerprint !== null) {
                    $this->State['UserAgent'] = $fingerprint->getUseragent();
                    $this->State['Fingerprint'] = $fingerprint->getFingerprint();
                    $this->State['Resolution'] = [$fingerprint->getScreenWidth(), $fingerprint->getScreenHeight()];
                }
            }// if (!isset($this->State['Fingerprint']) || $this->attempt > 1)

            if (isset($this->State['Fingerprint'])) {
                $this->logger->debug("set fingerprint");
                $this->seleniumOptions->fingerprint = $this->State['Fingerprint'];
            }

            if (isset($this->State["Resolution"])) {
                $this->setScreenResolution($this->State["Resolution"]);
            }

            if (isset($this->State["UserAgent"])) {
                $this->http->setUserAgent($this->State["UserAgent"]);
            }
        }// if (!in_array($this->AccountFields['Login2'], self::OTHER_REGIONS))
        elseif ($this->AccountFields['Login2'] == 'Australia') {
            $this->useFirefox();
            $this->setKeepProfile(true);
            $this->disableImages();
        } else {
            $this->useGoogleChrome();
            $this->useCache();
        }
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    public function LoadLoginForm()
    {
        try {
            $this->http->removeCookies();
        } catch (
            Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | Facebook\WebDriver\Exception\UnknownErrorException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(4, 0);
        }

        if (in_array($this->AccountFields['Login2'], self::OTHER_REGIONS)) {
            return call_user_func([$this, "LoadLoginForm" . $this->AccountFields['Login2']]);
        }

        // AccountID: 3115902
        if (strlen($this->AccountFields['Pass']) < 6) {
            throw new CheckException("Your password must be at least 6 characters long", ACCOUNT_INVALID_PASSWORD);
        }

        try {
            try {
                $this->driver->manage()->window()->maximize();
            } catch (Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeoutException on maximize: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(4, 0);
            }

            try {
                $this->http->GetURL("https://online.citi.com/US/JSO/loginpage/retarget.action?deepdrop=true&checkAuth=Y");
            } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: " . $e->getMessage(), ['HtmlEncode' => true]);
            } catch (Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                $this->logger->error("JavascriptErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(4, 0);
            } catch (
                Facebook\WebDriver\Exception\UnknownErrorException
                $e
            ) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            try {
                $loginField = $this->waitForElement(WebDriverBy::xpath('//*[@id = "username"] | //*[@id = "USERNAME"] | //input[@name = "User ID"]'), 10);
            } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException on loginField: " . $e->getMessage());
                $this->saveResponse();
                $loginField = $this->waitForElement(WebDriverBy::xpath('//*[@id = "username"] | //*[@id = "USERNAME"] | //input[@name = "User ID"]'), 0);
            }

            // provider bug fix
            if (!$loginField && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "We Can’t Find This Page (404)")]'), 0)) {
                $this->saveResponse();
                $this->http->GetURL("https://online.citi.com/US/JSO/loginpage/retarget.action?deepdrop=true&checkAuth=Y");
                $loginField = $this->waitForElement(WebDriverBy::xpath('//*[@id = "username"] | //*[@id = "USERNAME"] | //input[@name = "User ID"]'), 10);
            }

            try {
                $passField =
                    $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password'] | //input[@id = 'PASSWORD']"), 0)
                    ?? $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0)
                ;
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException on passField: " . $e->getMessage());
            }
            $signIn = $this->waitForElement(WebDriverBy::xpath("//input[@alt = 'Sign On' or @value = 'Sign On'] | //button[@id = 'signInBtn']"), 0);
            $this->saveResponse();

            if (empty($loginField) || empty($passField) || empty($signIn)) {
                $this->logger->error('something went wrong');

                if ($this->loginSuccessful()) {
                    return true;
                }

                return $this->checkErrors();
            }
            $loginField->sendKeys($this->AccountFields['Login']);
//            $this->driver->executeScript("$('#username').trigger('blur')");
            $passField->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();
            $this->logger->debug("click by 'Sign On' button");
//            $signIn->click();
            $this->driver->executeScript("document.getElementById('signInBtn').click()");
        } catch (
            WebDriverCurlException
            | NoSuchDriverException
            | Facebook\WebDriver\Exception\WebDriverCurlException
            | Facebook\WebDriver\Exception\TimeoutException
            $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckRetryNeededException(4, 0);
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')
                || strstr($e->getMessage(), 'timeout: cannot determine loading status')) {
                throw new CheckRetryNeededException(4, 0);
            }
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);

            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function loginFromMainPage($loadMainPage = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Login From Main Page', ['Header' => 3]);

        if ($loadMainPage == true) {
            $this->http->GetURL("https://online.citi.com/US/login.do");
        }
        $passField = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password']"), 5);
        $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 0);
        $signIn = $this->waitForElement(WebDriverBy::xpath("//*[self::input or self::button][@id = 'signInBtn']"), 0);

        if (empty($loginField) || empty($passField) || empty($signIn)) {
            $this->logger->error('something went wrong');

            return $this->checkErrors();
        }
        /*
        $this->driver->executeScript("
            var login = document.getElementById('usernameMasked');
            if (!login)
                login = document.getElementById('username');
            login.value = '{$this->AccountFields['Login']}';
        ");
        $this->logger->debug('set credentials');
        $this->driver->executeScript("document.getElementById('username').value = '{$this->AccountFields['Login']}';");
        $this->driver->executeScript("document.getElementById('password').value = '" . str_replace(["'", "\\"], ["\'", "\\\\"], $this->AccountFields['Pass']) . "';");
        $this->logger->debug('evaluate changes');
        $this->driver->executeScript("
            function createNewEvent(eventName) {
                var event;
                if (typeof(Event) === \"function\") {
                    event = new Event(eventName);
                } else {
                    event = document.createEvent(\"Event\");
                    event.initEvent(eventName, true, true);
                }
                return event;
            }
            var username = document.getElementById('username');
            username.dispatchEvent(createNewEvent('input'));
            username.dispatchEvent(createNewEvent('change'));

            var password = document.getElementById('password');
            password.dispatchEvent(createNewEvent('input'));
            password.dispatchEvent(createNewEvent('change'));
        ");
        */

        $loginField->sendKeys($this->AccountFields['Login']);
        $passField->sendKeys($this->AccountFields['Pass']);

        $this->saveResponse();
        $this->logger->debug('click "sign in"');

        try {
//            $signIn->click();
            $this->driver->executeScript("document.getElementById('signInBtn').click()");
        } catch (WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->saveResponse();
            $this->logger->debug('click "sign in"');
//            $signIn = $this->waitForElement(WebDriverBy::xpath("//*[self::input or self::button][@id = 'signInBtn']"), 0);
//            $signIn->click();
            $this->driver->executeScript("document.getElementById('signInBtn').click()");
        }

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[
                contains(text(), "We are currently making updates to Citi.com.")
                or contains(text(), "Our online and mobile banking sites will be undergoing routine maintenance")
            ]')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // AccountOnline Temporarily Unavailable
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //h1[contains(text(), "AccountOnline Temporarily Unavailable")]
                | //td[@class = "MTxtBold" and contains(text(), "I am sorry ...the page you requested cannot be found on this server.")]
            '), 0)
        ) {
            $this->Properties = [];

            throw new CheckRetryNeededException(2, 5, $message->getText());
        }
        // Server Error
        if (
            $this->waitForElement(WebDriverBy::xpath('
                //h2[contains(text(), "Server Error")]
                | //span[contains(text(), "This site can’t be reached")]
                | //h1[contains(text(), "502 Bad Gateway")]
                | //span[contains(text(), "You were signed out due to inactivity, but you can log in again whenever you’re ready")]
            '), 0)
        ) {
            throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
        }
        // An error occurred while processing your request
        if ($message = $this->http->FindPreg("/<body>\s*(An error occurred while processing your request)\.<p>/")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (in_array($this->AccountFields['Login2'], self::OTHER_REGIONS)) {
            return call_user_func([$this, "LoginFrom" . $this->AccountFields['Login2']]);
        }

        try {
            if (!$this->processErrorsAndQuestions()) {
                return false;
            }
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        return true;
    }

    public function skipOffer()
    {
        $this->logger->notice(__METHOD__);
//        $this->saveResponse();

        $startTime = time();

        while ($this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Just a moment, please')]"), 0) && (time() - $startTime) < 15) {
            $this->logger->info("waiting page load");
            sleep(1);
        }

        /**
         * Contact Customer Service
         * -------------------------------------------------------------------------------------------
         * We apologize for any inconvenience, please contact our Customer Service Department
         * -------------------------------------------------------------------------------------------.
         */
        $linkContinue = $this->waitForElement(WebDriverBy::id('cbol_cam_okEot'), 0);

        if ($linkContinue && $this->waitForElement(WebDriverBy::xpath("
                //div[contains(text(), 'We apologize for any inconvenience, but to protect your account, further charges may be limited until you have contacted our Customer Service Department')]
                | //div[contains(text(), 'We apologize for any inconvenience, please contact our Customer Service Department')]
            "), 0)) {
            $linkContinue->click();
            sleep(3);
        }
        $linkContinue = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Take Me Back to My Accounts')]"), 0);

        if ($linkContinue && $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Simplify your life by signing up for Account Alerts')]"), 0)) {
            $linkContinue->click();
            sleep(3);
            // I'll exit
            if ($cpoExitButton = $this->waitForElement(WebDriverBy::id("cpoExitButton"), 5)) {
                $cpoExitButton->click();
            } else {
                $this->logger->notice("exit via jq injection");
                $this->driver->executeScript("var btnExit = $('#cpoExitButton'); if (btnExit.length > 0) btnExit.get(0).click();");
            }
        }
        /**
         * Please Update Your Income Info
         * -------------------------------------------------------------------------------------------
         * Keep in touch via email
         * -------------------------------------------------------------------------------------------
         * Please Confirm Your Info
         * -------------------------------------------------------------------------------------------
         * Your payment is past due
         * -------------------------------------------------------------------------------------------
         * You don’t need your paper statement to remember to make a payment anymore.
         */
        if (
            $notNow = $this->waitForElement(WebDriverBy::xpath('
                    //button[contains(text(), "Remind Me Later")]
                    | //a[contains(text(), "Please remind me later")]
                    | //a[contains(text(), "Remind Me Later")]
                    | //a[contains(text(), "REMIND ME LATER")]
                    | //a[contains(text(), "Remind me later")]
                    | //a[normalize-space(text()) = "Skip"]
                    | //button[contains(text(), "Remind Me Later")]
                    | //a[contains(text(), "Go to Account Overview")]
                    | //a[normalize-space(text()) = "X"]
                    | //a[@id = "cmlink_InterstitialRemindMeLater"]
                    | //a[@id = "remindLater"]
                    | //a[@id = "cmlink_SkipLink"]
                    | //a[@id = "remindMeLater"]
                    | //a[@class = "closeSpan"]
                    | //div[@id = "toDashboard" and contains(text(), "Continue to Account")]
                    | //button[@id = "secondaryCTA" and contains(text(), "Go to Account Overview")]
                    | //*[self::button or self::a][contains(text(), "Continue to Account")]
                    | //button[contains(text(), "Skip")]
                    | //a[contains(text(), "Got it")]
            '), 0)
        ) {
            if ($this->http->FindSingleNode('//div[@id = "toDashboard" and contains(text(), "Continue to Account")]')) {
                $this->driver->executeScript("var btnContinue = $('#toDashboard'); if (btnContinue.length > 0) btnContinue.get(0).click();");
            } else {
                $notNow->click();
            }
            sleep(3);
        }// if ($notNow = ...
        // We've Noticed Some Unusual Activity.
        $notNow = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Not now")] | //div[contains(text(), "Not Now")]'), 0);
        $unusualActivity = $this->waitForElement(WebDriverBy::xpath('
            //h3[contains(text(), "We’ve Noticed Some Suspicious Activity")]
            | //h2[contains(text(), "We’ve Noticed Some Unusual Activity")]
            | //p[contains(text(), "We noticed your profile has some missing or outdated information")]
            | //h1[contains(text(), "Action requested: Enroll in Paperless")]
        '), 0);

        if ($notNow && $unusualActivity) {
            $notNow->click();

            $linkContinue = $this->waitForElement(WebDriverBy::xpath('//button[@id = "cbol_cam_cancelOverlayNo" or contains(text(), "Review Later")]'), 5);
            $this->saveResponse();

            if ($linkContinue) {
                $linkContinue->click();
                sleep(3);
            }
        }// if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "We\'ve Noticed Some Unusual Activity.")]'), 0))
        elseif ($unusualActivity) {
            $this->throwProfileUpdateMessageException();
        }
        // Your Action is Required
        $notNow = $this->waitForElement(WebDriverBy::id('doLaterButton'), 0);

        if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Your Action is Required")]'), 0)) {
            $notNow->click();
            sleep(3);
        }// if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Your Action is Required")]'), 0))
        // Your Credit Card May Be at Risk
        $notNow = $this->waitForElement(WebDriverBy::id('btnNotNow'), 0);

        if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Your Credit Card May Be at Risk")]'), 0)) {
            $notNow->click();

            if ($notNow = $this->waitForElement(WebDriverBy::id('cbol_cam_cancelOverlayYes_UjE'), 3)) {
                $notNow->click();
                sleep(3);
            }
        }// if if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Your Credit Card May Be at Risk")]'), 0))
        // Safeguarding Your Account
        $notNow = $this->waitForElement(WebDriverBy::xpath('//a[@id = "cancelLink" and text() = "Continue to Account"]'), 0);

        if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h1[contains(text(), "Safeguarding Your Account") or contains(text(), "Immediate Attention Required")]'), 0)) {
            $notNow->click();
            sleep(3);
        }// if ($notNow && $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "We\'ve Noticed Some Unusual Activity.")]'), 0))

        // skip offer (without 'Remind Me Later' link)
        if (
            ($this->waitForElement(WebDriverBy::xpath('//strong[contains(text(), "Make your purchases more rewarding.")]'), 0)
                && $this->waitForElement(WebDriverBy::xpath("//a[@id = 'mlink_InterstitialNoThanks'] | //a[@id = 'cmlink_InterstitialNoThanks']"), 0))
            || $this->waitForElement(WebDriverBy::xpath("
                    //div[@id = 'copy-sectionBlueCOPS01']/h1[contains(text(), 'Your Account is Past Due')]
                    | //div[@id = 'copy-sectionBlueC']/h1[contains(text(), 'Still writing checks?')]
                    | //h2[contains(text(), \"It's easy to update the delivery method for your statements and legal notices to paperless.\")]
                    | //h2[contains(text(), 'Please Confirm Your Info')]
                    | //h1[contains(text(), 'Account Update Required')]
                "), 0)
        ) {
            $this->logger->debug("try to skip offer (without 'Remind Me Later' link");
            $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
            $this->logger->debug("delay 3 seconds..");
            sleep(3);

            // 'Your Account is Past Due'
            if ($this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'re very sorry we\'re having technical issues. Please try again.")]'), 0)) {
                $this->http->GetURL("https://online.citi.com/US/JPS/portal/Home.do?intc=Searchtest~expb");
                $this->logger->debug("delay 3 seconds..");
                sleep(3);
                $this->saveResponse();
            }
        }

        /**
         * We've encountered an issue and are working to fix the issue
         * -------------------------------------------------------------------------------------------
         * I am sorry ...Our Server generated some error while processing your request.
         * -------------------------------------------------------------------------------------------
         * I am sorry ...the page you requested cannot be found on this server.
         * -------------------------------------------------------------------------------------------
         * We've had a problem processing your request.
         * -------------------------------------------------------------------------------------------
         * Looks like you are having trouble signing on. Please try again.
         * -------------------------------------------------------------------------------------------
         * We've had a problem processing your request.
         * -------------------------------------------------------------------------------------------.
         */
        /*
         * We're very sorry we're having technical issues. Please try again.
         * We apologize for any inconvenience and appreciate your patience. [Citi002]
         */
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                    //span[@class = "dashboardFailSpanMsg" and contains(text(), "We\'ve encountered an issue and are working to fix the issue")]
                    | //td[@class = "MTxtBold" and contains(text(), "I am sorry ...Our Server generated some error while processing your request.")]
                    | //td[@class = "MTxtBold" and contains(text(), "I am sorry ...the page you requested cannot be found on this server.")]
                    | //font[@class = "errortext" and contains(text(), "We\'ve had a problem processing your request.")]
                    | //span[@id = "cbolui-iconDomID-Red Error-iconText" and contains(text(), "Looks like you are having trouble signing on. Please try again.")]
                    | //p[@class = "warning" and contains(text(), "We\'ve had a problem processing your request.")]
                    | //p[contains(text(), "We\'re very sorry we\'re having technical issues. Please try again.")]
                    | //span[@class = "strong" and contains(text(), "We\'ve encountered an issue")]
                '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->waitForElement(WebDriverBy::xpath('
                //span[contains(text(), "Having trouble signing on? Please try again or")]
                | //*[self::h1 or self::h2][contains(text(), "Having Trouble Signing On?")]
            '), 0)
        ) {
            throw new CheckException('Having trouble signing on? Please try again or select "Forgot User ID or Password"', ACCOUNT_INVALID_PASSWORD);
        }

        if ($this->http->FindPreg('/(Effective, [^,\.]+, \d{4}, your Citi<sup><span class="smalltext">[^<]+<\/span><\/sup> Online access is no longer available\.)/')) {
            throw new CheckException("Effective, April 22, 2023, your Citi® Online access is no longer available.", ACCOUNT_PROVIDER_ERROR);
        }
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        switch ($step) {
            case "Question":
                return $this->processATM();

                break;

            case "SecurityCheckpoint":
                return $this->processSecurityCheckpoint();

                break;

            case "SecurityCheckpointV2":
                return $this->processSecurityCheckpointV2();

                break;

            case "IdentificationCode":
                return $this->processIdentificationCodeEntering();

                break;

            case "IdentificationCodeThankyou":
                return $this->processIdentificationCodeThankyouEntering();

                break;

            case "OneTimePin":
                return $this->processOneTimePin();
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        $hasAccountAA = false;

        if (in_array($this->AccountFields['Login2'], self::OTHER_REGIONS)) {
            call_user_func([$this, "ParseForm" . $this->AccountFields['Login2']]);

            return;
        }// if (in_array($this->AccountFields['Login2'], self::OTHER_REGIONS))

        // skip offer
        $remindMeLater = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_InterstitialRemindMeLater'] | //a[@id = 'remindLater'] | //a[contains(text(), 'Remind Me Later')] | //a[@id = 'cmlink_SkipLink'] | //a[@id = 'cmlink_InterstitialsNoThanks'] | //a[@id = 'remindMeLater']"), 3);

        if ($remindMeLater) {
            $this->logger->debug("skip offer");

            try {
                $remindMeLater->click();
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());

                if (strstr($e->getMessage(), 'Command timed out in client when executing')) {
                    throw new CheckRetryNeededException(3, 1);
                }
            }
            sleep(3);

            $currentUrl = $this->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
            // debug
            if (stristr($currentUrl, 'https://online.citi.com/US/login.do?JFP_TOKEN=')) {
                $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
            }
        }// if ($remindMeLater)

        $this->skipOffer();

        try {
            $dashboardURL = $this->http->currentUrl();
        } catch (NoSuchDriverException | UnknownServerException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }
        $this->logger->debug("[Current URL]: {$dashboardURL}");
        $this->saveResponse();

        $this->closePopup();

        // refs #20537
        if (
            strstr($this->http->currentUrl(), 'https://online.citi.com/US/ag/dashboard?accountId=')
            && ($summaryPage = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'summarySelectorButton']"), 0))
        ) {
            $summaryPage->click();
        }

        // Name
        $this->SetProperty("Name", beautifulName($this->getName()));

        /*
         * AccountID: 2561525
         *
         * Feature temporarily unavailable.
         *
         * We're sorry, but this feature is temporarily unavailable. Please try again later.
         * If you continue to see this message, please call 1-800-374-9700 (TTY:800-788-0002) for further assistance.
         *
         * We apologize for any inconvenience and thank you for your patience.
         */
        if (!empty($this->Properties['Name'])
            && $this->waitForElement(WebDriverBy::xpath('//td[contains(text(), "We\'re sorry, but this feature is temporarily unavailable. Please try again later.")]'), 0)
            && $this->waitForElement(WebDriverBy::xpath('//td[contains(., "Feature temporarily unavailable.")]'), 0)
            && ($link = $this->waitForElement(WebDriverBy::xpath("//ul[@id = 'nav_secured']//a[contains(text(), 'Accounts')]"), 0))) {
            $this->logger->notice("Provider bug fix, try to load account info via link 'Accounts' from the menu");
            $link->click();
            $this->logger->debug("delay 3 seconds..");
            sleep(3);
            $this->saveResponse();
        }

        $this->closePopup();

        $this->logger->info("View Linked Accounts & Redeem Points", ['Header' => 3]);
        $this->increaseTimeLimit(120);

        // for multiple cards with one balance - View Linked Accounts & Redeem Points
        $view = $this->waitForElement(WebDriverBy::xpath('//a[span[contains(text(), "View Linked Accounts") and contains(text(), "Redeem Points")] and contains(@class,"hide")]'), 0);

        if ($view) {
            try {
                $view->click();
            } catch (UnknownServerException | Facebook\WebDriver\Exception\ElementClickInterceptedException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                $this->driver->executeScript("
                    try {
                        document.querySelector('a.reward-link-ty[class *= \"hide\"]').click();
                    } catch (e) {}
                ");
            }
        } else {
            $this->driver->executeScript("
                try {
                    document.querySelector('a.reward-link-ty[class *= \"hide\"]').click();
                } catch (e) {}
            ");
        }
        $this->saveResponse();

        // Your Chairman Benefits site has relocated to Account Online. Please use the above navigation bar to explore your card benefits.
        // AccountID: 2636588
        if ($this->http->FindSingleNode('//h3[@class = "cH-accountSummaryHead" and contains(text(), "Citi Chairman Benefits")]')) {
            /*
            if ($viewLink = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "View/Redeem ThankYou® Points")]'), 0)) {
//                $viewLink->click();
//                $this->driver->executeScript("$('a:contains(\"View/Redeem ThankYou® Points\")').attr('target', '_self').get(0).click()");
                $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
                $this->logger->debug("delay 3 seconds..");
                sleep(3);
                $this->saveResponse();
            }
            */
        }

        // Detected cards

        $detectedCards = [];
        // only cards without rewards
        $detectedCardsOnly = $this->http->XPath->query("//div[@id = 'cardsAccountPanel']//div[contains(@id, 'creditCardAccountPanel')]");
        $this->logger->debug("[v.1]: Total {$detectedCardsOnly->length} cards were found");

        if ($detectedCardsOnly->length == 0) {
            $detectedCardsOnly = $this->http->XPath->query("//div[contains(@id, 'accountInfoPanel')]");
            $this->logger->debug("[v.2]: Total {$detectedCardsOnly->length} cards were found");
        }// if ($detectedCardsOnly->length == 0)

        if ($detectedCardsOnly->length == 0) {
            $detectedCardsOnly = $this->http->XPath->query("//tr[contains(@class, 'cT-cardProduct') and //tr[contains(@class, 'cT-firstRow')] and not(contains(., '{{html account.accountName }}'))]");
            $this->logger->debug("[v.4]: Total {$detectedCardsOnly->length} cards were found");
        }// if ($detectedCardsOnly->length == 0)

        if ($detectedCardsOnly->length == 0) {
            $detectedCardsOnly = $this->http->XPath->query("//div[contains(@class,'cA-mrc-accountNameWrapper') and a[not(contains(., 'account.accountName'))]]");
            $this->logger->debug("[v.6]: Total {$detectedCardsOnly->length} cards were found");
        }// if ($detectedCardsOnly->length == 0)

        if ($detectedCardsOnly->length == 0) {
            // 25 Jan 2018
            $detectedCardsOnly = $this->http->XPath->query("//div[@class = 'nG-mrc-accountSummaryWrapper']//div[contains(@class,'nG-mrc-accountWrapper')]");
            $this->logger->debug("[v.7]: Total {$detectedCardsOnly->length} cards were found");
        }

        if ($detectedCardsOnly->length == 0) {
            // 01 Jul 2018
            $detectedCardsOnly = $this->http->XPath->query("//div[contains(@class, 'cA-ada-accountDetailsPanel')]");
            $this->logger->debug("[v.8]: Total {$detectedCardsOnly->length} cards were found");
        }

        if ($detectedCardsOnly->length == 0) {
            // 16 Nov 2018
            $detectedCardsOnly = $this->http->XPath->query("//div[contains(@class, 'cA-mrc-accountWrapper')]");
            $this->logger->debug("[v.9]: Total {$detectedCardsOnly->length} cards were found");
        }

        if ($detectedCardsOnly->length == 0) {
            // 08 Feb 2021
            $detectedCardsOnly = $this->http->XPath->query('//button[@class = "account-selector" and .//div[contains(@class, "account-name")]]');
            $this->logger->debug("[v.11]: Total {$detectedCardsOnly->length} cards were found");
        }

        if ($detectedCardsOnly->length == 0) {
            // 30 Oct 2023
            $detectedCardsOnly = $this->http->XPath->query('//div[@class = "card-header-name"]');
            $this->logger->debug("[v.13]: Total {$detectedCardsOnly->length} cards were found");
        }

        // 21 Aug 2024, refs #24217
        if ($detectedCardsOnly->length == 0) {
            $detectedCardsOnly = $this->http->XPath->query('//div[contains(@class, "account-selector-tile")]');
            $this->logger->debug("[v.14]: Total {$detectedCardsOnly->length} cards were found");
        }

        for ($i = 0; $i < $detectedCardsOnly->length; $i++) {
            $detectedCard = $detectedCardsOnly->item($i);
            $this->logger->warning("#{$i} card without rewards");
            $this->logger->debug("found card: " . $detectedCard->nodeValue);

            $displayName = $this->http->FindSingleNode("
                .//a[contains(@id, 'closedAcctLink')]
                | .//a[contains(@id, 'accountNameLink')]
                | .//div[@id = 'printCardArtHeader']
                | .//div[contains(@id, 'accountName-') and not(contains(@class, 'cA-ada-secondAccountUtilityLink'))]/a
                | .//a[contains(@class, 'cA-mrc-accountName')]
                | .//h4[contains(@class, 'header-level')]
                | .//div[@class = 'cA-mrc-accountName']
                | .//div[contains(@class, 'account-info-container')]
                | .//*[self::h4 or self::div][contains(@class, 'card-title')]
            ", $detectedCard);
            $code = preg_replace("/[^\d]/ims", '', $displayName);

            if (!$code) {
                $code = preg_replace("/\s*/ims", '', $displayName);
            }
            $this->logger->debug('displayName: ' . $displayName . ', code: ' . $code);

            $cardDescription = C_CARD_DESC_DO_NOT_EARN;

            if (
                stristr($displayName, 'AAdvantage')
                || stristr($displayName, 'AA card')
                || stristr($displayName, 'citi aa')
                || stristr($displayName, 'AAPersonal')
                || stristr($displayName, 'AABusiness')
                || stristr($displayName, 'AA #')
                || strstr($detectedCard->nodeValue, 'available AAdvantage® miles in your AAdvantage® Account')
            ) {
                $hasAccountAA = true;
                $cardDescription = C_CARD_DESC_AA;
            }

            if (stristr($displayName, 'Hilton')) {
                $cardDescription = C_CARD_DESC_HHONORS;
            }

            if (
                strstr($detectedCard->nodeValue, 'This account is closed.')
                || strstr($detectedCard->nodeValue, 'This card is closed.')
            ) {
                $cardDescription = C_CARD_DESC_CLOSED;
            }

            if (!empty($displayName) && $code) {
                $code = $this->getSubAccountCardCode($code);

                if ($cardDescription === C_CARD_DESC_CLOSED) {
                    $this->closedCards[] = $code;
                }

                $subAccount = [
                    "Code"            => $code,
                    "DisplayName"     => preg_replace('/x{3,}/', 'x', $displayName),
                    "CardDescription" => $cardDescription,
                ];

                // refs #21504
                $detailsLink = $this->http->FindSingleNode(".//div[@class = 'cA-mrc-accountName']//a/@href", $detectedCard);
                // refs #23640
                if (
                    !$detailsLink
                    && ($detailsLinkTwo = $this->http->FindSingleNode(".//a[contains(@href, 'accountdetails') and contains(text(), '-" . str_replace('citybank', '', $code) . "')]/@href"))) {
                    $detailsLink = 'https://online.citi.com/US/ag' . $detailsLinkTwo;
                }

                $this->logger->debug("DetailsLink -> {$detailsLink}");

                if (
                    $detailsLink
                    && !stristr($displayName, 'AAdvantage')
                    && !stristr($displayName, 'AA card')
                    && !stristr($displayName, 'citi aa')
                    && !stristr($displayName, 'AA #')
                    && !stristr($displayName, 'AAPersonal')
                    && !stristr($displayName, 'AABusiness')
                ) {
                    $this->http->NormalizeURL($detailsLink);
                    $subAccount["DetailsLink"] = $detailsLink;
                }

                // detected cards
                $detectedCards[] = $subAccount;
            }// if (!empty($displayName) && $code)
        }// for ($i = 0; $i < $detectedCardsOnly->length; $i++)

        // subAccounts

        $rewards = $this->http->XPath->query("//div[@class = 'cA-spf-rewardsWrapper' and not(contains(., '{{html rewardAccnt.formattedRwdCardName}}'))]");
        $version = 1;
        $this->logger->debug("[v.1]: Total {$rewards->length} rewards were found");

        if ($rewards->length == 0) {
            $rewards = $this->http->XPath->query("//div[@id = 'categoryType-Rewards']//tr[contains(@class, 'cT-firstRow')]");
            $this->logger->debug("[v.2]: Total {$rewards->length} rewards were found");
            $version = 2;
        }

        if ($rewards->length == 0) {
            $rewards = $this->http->XPath->query("//div[@id = 'accountsPanelInnerContainer']//tr[contains(@class, 'cT-firstRow')]");
            $this->logger->debug("[v.3]: Total {$rewards->length} rewards were found");
            $version = 3;
        }

        if ($rewards->length == 0) {
            $rewards = $this->http->XPath->query("//tr[contains(@class, 'cT-cardProduct') and //tr[contains(@class, 'cT-firstRow')] and not(contains(., '{{html account.accountName }}'))]");
            $this->logger->debug("[v.4]: Total {$rewards->length} rewards were found");
            $version = 4;
        }

        if ($rewards->length == 0) {
            $rewards = $this->http->XPath->query("//div[@id = 'savingsAccountsCategoryWrapper']");
            $this->logger->debug("[v.5]: Total {$rewards->length} rewards were found");
            $version = 5;
        }

        if ($rewards->length == 0) {
            $rewards = $this->http->XPath->query("//div[@id = 'categoryWrapper-Rewards']//div[contains(@class,'cA-mrc-rewardsCategoryWrapper')]");
            $this->logger->debug("[v.6]: Total {$rewards->length} rewards were found");
            $version = 6;
        }

        if ($rewards->length == 0) {
            // 25 Jan 2018
            $rewards = $this->http->XPath->query("//div[@class = 'cA-mrc-rewardsContainer']");
            $this->logger->debug("[v.7]: Total {$rewards->length} rewards were found");
            $version = 7;
        }

        if ($rewards->length == 0) {
            // 01 Jul 2018
            $rewards = $this->http->XPath->query("//div[contains(@class, 'cA-ada-accountDetailsPanel')]");
            $this->logger->debug("[v.8]: Total {$rewards->length} rewards were found");
            $version = 8;
        }

        if ($rewards->length == 0) {
            // 16 Nov 2018
            $rewards = $this->http->XPath->query("//div[contains(@class, 'cA-mrc-rewardsContainer')]");
            $this->logger->debug("[v.9]: Total {$rewards->length} rewards were found");
            $version = 9;
        }

        if ($rewards->length == 0 && $this->http->XPath->query('//button[@class = "account-selector"]')->length == 0) {
            // 18 May 2020
            $rewards = $this->http->XPath->query("//div[contains(@class, 'reward-container')]");
            $this->logger->debug("[v.10]: Total {$rewards->length} rewards were found");
            $version = 10;
        }

        if ($rewards->length == 0) {
            // 08 Feb 2021
            $rewards = $this->http->XPath->query('//div[contains(@class, "reward-container")]//div[contains(@class, "reward-wrapper") and not(contains(., "Big changes are coming soon... "))]');
            $this->logger->debug("[v.11]: Total {$rewards->length} rewards were found");
            $version = 11;
        }

        if ($rewards->length == 0) {
            // 07 Apr 2023
            $rewards = $this->http->XPath->query('//div[contains(@class, "rewardTextSize")]');
            $this->logger->debug("[v.12]: Total {$rewards->length} rewards were found");
            $version = 12;
        }

        if ($rewards->length == 0) {
            // 30 Oct 2023
            $rewards = $this->http->XPath->query('//div[contains(@class, "rewardSC-ContentWrapper")]');
            $this->logger->debug("[v.13]: Total {$rewards->length} rewards were found");
            $version = 13;
        }

        $thankYouPoints = [];

        for ($i = 0; $i < $rewards->length; $i++) {
            $reward = $rewards->item($i);
            $this->logger->debug("found row: " . $reward->nodeValue);
            $allCardsCodes = [];
            $allCardsDisplayNames = [];
            $displayName = null;

            $this->logger->debug("find displayName...");
            $number = $this->http->FindSingleNode("
                .//div[@class = 'cA-spf-rewardsAccountName' and contains(., 'ending in')]
                | .//div[@class = 'card-ending']
                | .//span[contains(@class, 'reward-subheading') and contains(., 'ending in')]
                | .//span[contains(@class, 'clubbed-heading') and contains(., 'ending in')]
                | .//span[@aria-hidden ='true' and contains(., 'ending in')]
            ", $reward, true, "/\d+/");
            $this->logger->debug("number: " . $number);

            if ($number) {
                $displayName = $this->http->FindSingleNode("//a[contains(@id, 'cA-spf-accountNameLink') and contains(., '-{$number}')]")
                    ?? $this->http->FindSingleNode("//a[contains(@id, 'cA-spf-closedAcctLink') and contains(., '-{$number}')]")
                    ?? $this->http->FindSingleNode(".//div[contains(@class, 'cA-spf-rewardsAccountName')]", $reward)
                    ?? $this->http->FindSingleNode('//div[@class = "cA-mrc-accountName" and contains(., "-' . $number . '")]')
                    ?? $this->http->FindSingleNode('//div[contains(@class, "account-info-container") and contains(., "–' . $number . '")]')
                    ?? $this->http->FindSingleNode('//button[@id = "bankAccountSelector1TileBody"]//div[contains(@class, "account-info-container") and contains(., "–' . $number . '")]')
                ;
            }
            // v.2
            if (!$displayName) {
                $displayName =
                    $this->http->FindSingleNode(".//div[contains(@id, 'accountName')]", $reward)
                    ?? $this->http->FindSingleNode("./following-sibling::div[1]//span[contains(@class, 'clubbed-heading')]", $reward)
                ;

                if (!$displayName) {
                    $this->logger->warning("multiple cards earn one reward type");
                    $allCardsDisplayNames = array_map(function ($elem) {
                        $this->logger->debug("displayName v.2: {$elem}");

                        return $elem;
                    }, $this->http->FindNodes(".//div[contains(@id, 'accountName')]", $reward));

                    if (empty($allCardsDisplayNames)) {
                        $allCardsDisplayNames = array_map(function ($elem) {
                            $this->logger->debug("displayName v. 2.1: {$elem}");

                            return $elem;
                        }, $this->http->FindNodes("./following-sibling::div[1]//span[contains(@class, 'clubbed-heading') and normalize-space(.) != ''] | .//span[@aria-hidden ='true' and normalize-space(.) != '']", $reward));
                    }

                    $accountEnding = $this->http->FindSingleNode("@aria-label", $reward, true, "/Total ThankYou® Points for account (.+)/");

                    if (!$accountEnding
                        && (
                            strstr($this->http->FindSingleNode("@aria-label", $reward), 'Total ThankYou® Points. ')
                            || strstr($this->http->FindSingleNode("@aria-label", $reward), 'Citi ThankYou®')
                        )
                    ) {
                        $accountEnding =
                            $this->http->FindSingleNode("(//div[contains(@class, 'reward-clubbed')]/parent::div//span[@class = 'clubbed-content']/span)[1]", null, true, "/Card\s*ending\s*in\s*(.+)/")
                            ?? $this->http->FindSingleNode("(//div[contains(@class, 'reward-clubbed')]/parent::div//span[@class = 'clubbed-content']/span)[contains(text(), 'Card ending in')]", null, true, "/Card\s*ending\s*in\s*(.+)/")
                        ;
                    }

                    $this->logger->debug("[Total ThankYou® Points for account]: $accountEnding");

                    if ($accountEnding && !$allCardsDisplayNames) {
                        $allCardsDisplayNames = array_map(function ($elem) {
                            $this->logger->debug("displayName v.2: {$elem}");

                            return $elem;
                        }, $this->http->FindNodes("//span[contains(text(), '{$accountEnding}')]/ancestor::div[contains(@class, 'reward-clubbed')]/parent::div//span[@class = 'clubbed-content']/span"));
                    }// if ($accountEnding && !$allCardsDisplayNames)

                    sort($allCardsDisplayNames); // refs #23842

                    foreach ($allCardsDisplayNames as $allCardsDisplayName) {
                        $allCardsCodes[] = preg_replace("/[^\d]/ims", '', $allCardsDisplayName);
                    }
                    $displayName = implode(' | ', $allCardsDisplayNames);
                    // AccountID: 3892145
                    $this->logger->debug("v.2 displayNam before truncate: " . $displayName);
                    $originalDisplayName = $displayName;

                    if (mb_strlen(html_entity_decode($displayName, ENT_QUOTES, "UTF-8")) > 240) {
                        $displayName = mb_substr($displayName, 0, 240) . '...';
                    }
                }// if (!$displayName)
            }// if (!$displayName)
            $this->logger->debug("v.2 displayName: " . $displayName);
            // v.4
            if (!$displayName && $version == 4) {
                $displayName = $this->http->FindSingleNode(".//span[contains(@class, 'cT-rewardsHeaderText')]", $reward);
            }// if (!$displayName && $version == 4)

            if (!$displayName && $version == 5) {
                $displayName = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-accountNameWrapper')]", $reward);
            }// if (!$displayName && $version == 5)

            if (!$displayName && $version == 6) {
                $displayName = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-rewardAccountName')]", $reward);
            }// if (!$displayName && $version == 5)

            if (!$displayName && $version == 7) {
                $displayName = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-cardEnding')]", $reward);
            }// if (!$displayName && $version == 7)

            if (!$displayName && $version == 8) {
                $displayName = $this->http->FindSingleNode(".//h4[contains(@class, 'header-level')]", $reward);
            }// if (!$displayName && $version == 8)

            if (!$displayName && $version == 9) {
                $displayName = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-cardEnding')]", $reward);
            }// if (!$displayName && $version == 9)

            if (!$displayName && $version == 10) {
                $displayName = $this->http->FindSingleNode(".//div[@class = 'card-ending']", $reward);
            }
            $code = preg_replace("/[^\d]/ims", '', $displayName);

            if (!$code) {
                $code = preg_replace("/\s*/ims", '', $displayName);
                $code = str_replace("|", '', $code);
            }
            $this->logger->debug("find balance...");
            $balance = $this->http->FindSingleNode(".//div[@class = 'cA-spf-rewardsValue']/span", $reward);
            // v.2
            if (!isset($balance)) {
                $balance = $this->http->FindSingleNode(".//ancestor::tr[contains(@class, 'cT-rewardsFirstRow')]//div[contains(@class, 'cT-valueItem')]/span[contains(@class, 'cT-balanceIndicator1')]", $reward);
            }

            if (!isset($balance) && $version == 2) {
                $balance = $this->http->FindSingleNode(".//ancestor::tr[contains(@class, 'cT-firstRow')]//div[contains(@class, 'cT-valueItem')]/span[contains(@class, 'cT-balanceIndicator1')]", $reward);
            }
            // v.4
            if (!isset($balance) && $version == 4) {
                $balance = $this->http->FindSingleNode(".//ancestor::tr[contains(@class, 'cT-rewardsHeadRow')]//div[contains(@class, 'cT-valueItem')]/span[contains(@class, 'cT-balanceIndicator1')]", $reward);

                // AccountID: 2446561
                if (!isset($balance) && $rewards->length == 1) {
                    $balance = $this->http->FindSingleNode("//tr[contains(@class, 'cT-rewardsHeadRow') and not(contains(., 'Payment Scheduled'))]//div[contains(@class, 'cT-valueItem')]/span[contains(@class, 'cT-balanceIndicator1')]", $reward);
                }
            }
            // v.6
            if (!isset($balance) && $version == 6) {
                $balance = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-rewardsValue')]", $reward);
            }
            // v.7
            if (!isset($balance) && in_array($version, [7, 9])) {
                $balance = $this->http->FindSingleNode(".//div[contains(@class, 'cA-mrc-rewardsvalue')]", $reward);
            }

            if (!isset($balance) && $version == 10) {
                $balance = $this->http->FindSingleNode(".//div[contains(@class, 'rewards-value')]", $reward);
            }
            // v.8
            if (!isset($balance) && $version == 8) {
                $balance = $this->http->FindSingleNode(".//ancestor::div[contains(@class, 'citi-container')]//span[contains(@class, 'cA-ada-availableRewardBalanceWrapper')]/span", $reward);
            }

            if (!isset($balance) && $version == 12) {
                $balance = $this->http->FindSingleNode(".//b", $reward);
            }

            if (!isset($balance) && in_array($version, [10, 11, 13])) {
                $balance =
                    $this->http->FindSingleNode(".//span[contains(@class, 'reward-amount')]/span", $reward, false)
                    ?? $this->http->FindSingleNode(".//span[contains(@class, 'reward-amount')]/node()[not(contains(., 'Total')) and not(contains(., 'ThankYou'))]", $reward)
                    ?? $this->http->FindSingleNode("(.//span[contains(@class, 'reward-amount')]/node()[not(contains(., 'Total')) and not(contains(., 'ThankYou'))])[1]", $reward)
                ;
                // for debug
                if (!$balance) {
                    $nodes = $this->http->FindHTMLByXpath(".//span[contains(@class, 'reward-amount')]/node()", null, $reward);
                    $this->logger->debug(var_export($nodes, true), ['pre' => true]);
                }
            }
            $this->logger->debug('displayName: ' . $displayName . ', code: ' . $code . ', balance: ' . $balance);

            // $('div.cA-spf-rewardsWrapper') does not have card name, it has only balance
            if ($displayName == "" && $code == "" && $rewards->length == 1) {
                $cardName = $this->http->FindNodes("//span[contains(@class, 'cS-accountMenuAccount')]/span | //*[self::h4 or self::div][contains(@class, 'card-title')]");
                $cardNameCount = count($cardName);
                $this->logger->debug('fixed provider bug: ' . $cardNameCount);

                if ($cardNameCount == 1) {
                    $displayName = $cardName[0];
                    $code = preg_replace("/[^\d]/ims", '', $displayName);
                }// if ($cardNameCount == 1)
                $this->logger->debug('displayName: ' . $displayName . ', code: ' . $code . ', balance: ' . $balance);
            }// if ($displayName == "" && $code == "" && $rewards->length == 1)

            $cardDescription = C_CARD_DESC_DO_NOT_EARN;

            if (
                stristr($displayName, 'AAdvantage')
                || stristr($displayName, 'AA card')
                || stristr($displayName, 'citi aa')
                || stristr($displayName, 'AA #')
                || stristr($displayName, 'AAPersonal')
                || stristr($displayName, 'AABusiness')
                || strstr($reward->nodeValue, 'available AAdvantage® miles in your AAdvantage® Account')
            ) {
                $hasAccountAA = true;
                $cardDescription = C_CARD_DESC_AA;
            }

            if (stristr($displayName, 'Hilton')
                || strstr($reward->nodeValue, 'Available points reflect HHonors points earned on the Hilton HHonors account associated')) {
                $cardDescription = C_CARD_DESC_HHONORS;
            }

            if ($displayName && (!in_array(preg_replace("/\s*/ims", '', $displayName), ['', 'CreditCard', 'AutopayStatus:Enrolled']))) {
                if ($rightDisplayName =
                    $this->http->FindSingleNode('//div[@class = "cA-mrc-accountName" and contains(., " - ' . $code . '")]')
                    ?? $this->http->FindSingleNode('//div[@class = "cA-mrc-accountName" and contains(., "-' . $code . '")]')
                ) {
                    $this->logger->notice("fixed displayName");
                    $displayName = $rightDisplayName;
                }

                $code = $this->getSubAccountCardCode($code);
                $subAccount = [
                    "Code"                => $code,
                    "DisplayName"         => preg_replace('/x{3,}/', 'x', $displayName),
                    "Balance"             => $balance,
                    "Currency"            => (strstr($balance, '$')) ? "$" : null,
                ];

                if (isset($balance) && !stristr($balance, 'not available') && $cardDescription == C_CARD_DESC_DO_NOT_EARN) {
                    // detected cards
                    // refs #23384
                    !empty($allCardsDisplayNames) ?
                        $this->setDetectedCardsFromAllCardsDisplayNames($subAccount, $allCardsDisplayNames)
                        : $this->AddDetectedCard(array_merge($subAccount, ["CardDescription" => C_CARD_DESC_ACTIVE]), true)
                    ;
                    $subAccount['Currency'] = (strstr($balance, '$')) ? "$" : null;

                    if (stristr($reward->nodeValue, 'Total Available ThankYou')) {
                        if (
                            isset($thankYouPoints[$balance])
                            && $version != 10 // refs #21504 #note-13
                        ) {
                            $this->logger->notice("skip double ThankYou Points: {$balance}");
                            $thankYouPoints[$balance]++;

                            continue;
                        } else {
                            $this->logger->notice("ThankYou Points: {$balance}");
                            $thankYouPoints[$balance] = 1;
                        }
                    }

                    $this->AddSubAccount($subAccount);
                }// if (isset($balance))
                else { // detected cards
                    // refs #23384
                    !empty($allCardsDisplayNames) ?
                        $this->setDetectedCardsFromAllCardsDisplayNames($subAccount, $allCardsDisplayNames)
                        : $this->AddDetectedCard(array_merge($subAccount, ["CardDescription" => $cardDescription]), true)
                    ;
                }
            }// if ($displayName && $displayName != '' && preg_replace("/\s*/ims", '', $displayName) != 'CreditCard')
        }// for ($i = 0; $i < $rewards->length; $i++)

        // refs 24284
        if ($viewMoreDetails = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "View More Details")]'), 0)) {
            $viewMoreDetails->click();
            $this->waitForElement(WebDriverBy::xpath('//div[contains(@class, "REWARDS_SUMMARY_HIGHLIGHTS")]'), 10);
            $this->saveResponse();

            $rewards = $this->http->XPath->query('//div[contains(@class, "REWARDS_SUMMARY_HIGHLIGHTS")]');
            $this->logger->debug("[View More Details]: Total {$rewards->length} rewards were found");

            for ($i = 0; $i < $rewards->length; $i++) {
                $reward = $rewards->item($i);
                $this->logger->debug("found row: " . $reward->nodeValue);
                $allCardsDisplayNames = [];
                $displayName = null;

                $displayName = $this->http->FindSingleNode('.//span[@aria-hidden="true"]', $reward);
                $code = preg_replace("/[^\d]/ims", '', $displayName);

                if (!$code) {
                    $code = preg_replace("/\s*/ims", '', $displayName);
                    $code = str_replace("|", '', $code);
                }

                $balance = $this->http->FindSingleNode('.//div[@class="value-div"]', $reward);

                $code = $this->getSubAccountCardCode($code);
                $subAccount = [
                    "Code"                => $code,
                    "DisplayName"         => preg_replace('/x{3,}/', 'x', $displayName),
                    "Balance"             => $balance,
                    "Currency"            => (strstr($balance, '$')) ? "$" : null,
                ];

                $cardDescription = C_CARD_DESC_DO_NOT_EARN;

                if (isset($balance) && !stristr($balance, 'not available') && $cardDescription == C_CARD_DESC_DO_NOT_EARN) {
                    // detected cards
                    // refs #23384
                    !empty($allCardsDisplayNames) ?
                        $this->setDetectedCardsFromAllCardsDisplayNames($subAccount, $allCardsDisplayNames)
                        : $this->AddDetectedCard(array_merge($subAccount, ["CardDescription" => C_CARD_DESC_ACTIVE]), true)
                    ;

                    if (stristr($reward->nodeValue, 'Total Available ThankYou')) {
                        if (
                            isset($thankYouPoints[$balance])
                            && $version != 10 // refs #21504 #note-13
                        ) {
                            $this->logger->notice("skip double ThankYou Points: {$balance}");
                            $thankYouPoints[$balance]++;

                            continue;
                        } else {
                            $this->logger->notice("ThankYou Points: {$balance}");
                            $thankYouPoints[$balance] = 1;
                        }
                    }

                    $this->AddSubAccount($subAccount);
                }// if (isset($balance))
                else { // detected cards
                    // refs #23384
                    !empty($allCardsDisplayNames) ?
                        $this->setDetectedCardsFromAllCardsDisplayNames($subAccount, $allCardsDisplayNames)
                        : $this->AddDetectedCard(array_merge($subAccount, ["CardDescription" => $cardDescription]), true)
                    ;
                }
            }// for ($i = 0; $i < $rewards->length; $i++)
        }// if ($viewMoreDetails = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "View More Details")]'), 0))

        $this->logger->debug("Total unique cards with ThankYou Points: " . count($thankYouPoints));

        // refs #21504
        if (
            (
                count($thankYouPoints) === 0
                || $this->AccountFields['Login'] == 'countalex1' // refs #24162
            )
            && !empty($detectedCards)
        ) {
            $this->logger->notice("try to find balance on card details page");

            foreach ($detectedCards as &$detectedCard) {
                if (!isset($detectedCard['DetailsLink'])) {
                    continue;
                }

                $this->logger->info("[Card details]: {$detectedCard['DisplayName']}", ['Header' => 3]);

                try {
                    $this->http->GetURL($detectedCard['DetailsLink']);
                } catch (UnknownServerException | Facebook\WebDriver\Exception\UnknownErrorException $e) {
                    $this->logger->error("UnknownServerException exception: " . $e->getMessage());
                }
                unset($detectedCard['DetailsLink']);

                $b = $this->waitForElement(WebDriverBy::xpath('//span[@id = "availableRewardBalance"] | //span[contains(@class, "reward-amount")]/div[1]'), 5);
                $this->saveResponse();
                // refs #21957
                $rewardsText = $this->http->FindSingleNode('//span[@id = "rewardsText"]');
                $this->logger->debug("[rewardsText]: {$rewardsText}");

                if (
                    $b && !strstr($rewardsText, 'Mile')
                    // refs #24162
                    && !($this->AccountFields['Login'] == 'countalex1' && $detectedCard['DisplayName'] == 'Citi Rewards+℠ Card –7198')
                ) {
                    $balance = $b->getText();
                    $this->AddSubAccount([
                        "Code"        => $detectedCard['Code'],
                        "DisplayName" => $detectedCard['DisplayName'],
                        "Balance"     => $balance,
                        "Currency"    => (strstr($balance, '$')) ? "$" : null,
                    ]);
                    // detected cards
                    $detectedCard['CardDescription'] = C_CARD_DESC_ACTIVE;
                }// if ($b)
                // refs #21957
                elseif (strstr($rewardsText, 'Mile')) {
                    $detectedCard['CardDescription'] = C_CARD_DESC_AA;
                    $this->AddDetectedCard($detectedCard, true, true);
                }

                unset($b);
            }// foreach ($detectedCards as $detectedCard)
        }// if (count($thankYouPoints) === 0 && !empty($detectedCards))

        if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 0) {
            $countSubAccounts = count($this->Properties['SubAccounts']);
            $this->logger->debug("count subAccounts: $countSubAccounts");
            $subAccountBalance = 0;

            for ($i = 0; $i < $countSubAccounts; $i++) {
                if (
                    !isset($this->Properties['SubAccounts'][$i]['Balance'])
                    || $this->Properties['SubAccounts'][$i]['Balance'] == null
                    || !empty($this->Properties['SubAccounts'][$i]['Currency'])
                ) {
                    continue;
                }
                $subAccountBalance += floatval(str_replace([',', '.'], ['', ','], $this->Properties['SubAccounts'][$i]['Balance']));
                $this->Properties['SubAccounts'][$i]['BalanceInTotalSum'] = true;
            }// for ($i = 0; $i < $countSubAccounts; $i++)

            $this->SetBalance($subAccountBalance);
        }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) > 0)

        // detected cards
        if (!empty($detectedCards)) {
            $this->logger->notice("Rewrite detected cards");

            foreach ($detectedCards as $detectedCard) {
                $this->AddDetectedCard($detectedCard, true, false);
            }
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->logger->notice("[Current URL]: " . $this->http->currentUrl());
            // Rewards information temporarily unavailable. Please try again later
            // Sorry, we can't currently load your rewards information. Please try again later.
            if ($error = $this->waitForElement(WebDriverBy::xpath('
                    //span[@class = "cA-spf-rewardsErrorText" and contains(text(), "Rewards information temporarily unavailable")]
                    | //div[@class = "cA-mrc-rewardsErrMessage" and contains(text(), "Sorry, we can\'t currently load your rewards information. Please try again later.")] 
                '), 0)
            ) {
                $this->SetWarning($error->getText());
            }
            // We're sorry. Your credit card information is temporarily unavailable. Please try again later.
            elseif ($error = $this->waitForElement(WebDriverBy::xpath('//span[contains(@class, "colorTextRed") and contains(text(), "We\'re sorry. Your credit card information is temporarily unavailable.")]'), 0)) {
                $this->CheckError($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }
            // This account is closed.
            elseif (
                $this->waitForElement(WebDriverBy::xpath("
                    //span[contains(text(), 'This account is closed.')]
                    | //span[@class = \"cA-ada-criticalAlertMessageWrapper\" and contains(text(), \"Account alert: this account is closed. For inquiries\")]
                "), 0)
            ) {
                $this->CheckError("This account is closed.", ACCOUNT_PROVIDER_ERROR);
            }
            // Account information temporarily unavailable. Please try again later.
            // Feature temporarily unavailable.
            // Account details currently not available.
            // ThankYou® Rewards - This information is temporarily unavailable. Please try again later.
            elseif ($error = $this->waitForElement(WebDriverBy::xpath("
                    //span[@class = 'cA-spf-accountErrorText' and contains(text(), 'Account information temporarily unavailable. Please try again later.')]
                    | //span[contains(text(), 'Feature temporarily unavailable.')]
                    | //span[@class = 'cA-ada-accountErrorText' and contains(text(), 'Account details currently not available.')]
                    | //tbody[@class = 'cT-rewardsProgram']//div[contains(text(), 'This information is temporarily unavailable. Please try again later.')]
                "), 0)
            ) {
                $this->Properties = [];

                throw new CheckRetryNeededException(2, 7, $error->getText());
            }

            // only AAdvantage cards
            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && isset($this->Properties['DetectedCards'])
                && count($this->Properties['DetectedCards']) > 0
                && (
                       $hasAccountAA === true
                       || (count($this->http->FindNodes("//span[contains(text(), 'This card is closed')]")) == count($this->Properties['DetectedCards']))
                       // AccountID: 3098767
                       || ($this->http->FindPreg("/(?:<div tabindex=\"0\" role=\"alert\">\s*Your CitiBusiness Card account was recently converted from Visa|CitiBusiness ThankYou<sup><span[^>]*>®<\/span><\/sup> Card - |T Universal Business Rewards\s*<sup><span[^>]*>®<\/span><\/sup> Card - )/") && count($this->Properties['DetectedCards']) == 1)
                       || in_array($this->AccountFields['Login'], ['jrlamb73', 'edrasp', 'nabeelaj'])// AccountID: 1490528, 3454663, 958653 very strange account, no points
                )
            ) {
                $desc = 'closed';

                if ($hasAccountAA === true) {
                    $desc = 'AAdvantage';
                }
                $this->logger->warning("account has only {$desc} cards");
                $this->SetBalanceNA();
            }

            // account does not have any cards or rewards
            $cardTable = $this->http->FindSingleNode("//div[@id = 'accountsPanelInnerContainer']");
            $this->logger->debug("[Table]: {{$cardTable}}");

            if (
                $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && (
                    strstr($this->http->currentUrl(), 'https://online.citi.com')
                    || strstr($this->http->currentUrl(), 'https://www.accountonline.com/buscards/USBAO/accountsummary/flow.action')
                )
                && (
                    $cardTable == 'Account Name Account Type Amount'
                    || $cardTable == ''
                    || strstr($cardTable, 'Savings Account Savings Plus Account-')
                    || strstr($cardTable, 'Savings Account Citi Savings Account-')
                    || (strstr($cardTable, 'Account Name Account Type Amount') && strstr($cardTable, 'Access Account-'))
                    || $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "Your Chairman Benefits site has relocated to Account Online. Please use the above navigation bar to explore your card benefits.")]'), 0)
                )
                && !empty($this->Properties['Name'])
            ) {
                $this->logger->warning("account does not have any cards or rewards");
                $this->SetBalanceNA();
                // refs #11308
                $this->parseFICO();
                $this->removeOriginalDisplayName();

                return;
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // refs #16337 Gathering transaction history for Citibank
        $this->parsePageSubAccHistory();

        $doubleCashCardThankyouPoints = false;

        if ($this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "ThankYou Rewards")]'), 0, false)) {
            $doubleCashCardThankyouPoints = true;
        }
        // AccountID: 3602527, 2282143
        elseif (
            $this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && $this->http->FindSingleNode('//img[@id = "headerLogo" and contains(@src, "logo_citibusiness.png")]/@src')
            && $this->http->FindSingleNode('//div[@id = "programName-ThankYouRewards"]/a/@href')
            && $this->waitForElement(WebDriverBy::xpath('//div[@id = "programName-ThankYouRewards"]/a'), 0, false)
        ) {
            $link = $this->http->FindSingleNode('//div[@id = "programName-ThankYouRewards"]/a/@href', null, true, "/launchPopup\('([^\']+)/");
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $this->saveResponse();
            $res = $this->waitForElement(WebDriverBy::xpath('
                //div[contains(@class, "card-element-text") and contains(., "Double Cash Card")]
                | //div[contains(@class, "summery-deatil") and contains(., "Double Cash Card")]
                | //h2[contains(text(), "Error 500--Internal Server Error")]
                | //select[@id = "otpDeliveryOptions"]
                | //div[@id = \'otpDropdown1\']
                | //h2[contains(text(), "Help Us Verify Your Identity")]
            '), 7); //todo: check sq
            $this->saveResponse();

            // refs #23185
            if (!$res) {
                $this->logger->notice("force redirect");
                $this->sendNotification("refs #23185 force redirect // RR");
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");
                $this->http->GetURL("https://www.thankyou.com/selectLogin.htm");

                $this->waitForElement(WebDriverBy::xpath('
                    //div[contains(@class, "card-element-text") and contains(., "Double Cash Card")]
                    | //div[contains(@class, "summery-deatil") and contains(., "Double Cash Card")]
                    | //h2[contains(text(), "Error 500--Internal Server Error")]
                    | //select[@id = "otpDeliveryOptions"]
                    | //div[@id = \'otpDropdown1\']
                    | //h2[contains(text(), "Help Us Verify Your Identity")]
                '), 7); //todo: check sq
                $this->saveResponse();
            }

            $tyNumbers = $this->http->XPath->query('//div[@class = "summery-deatil-wrapper"]');
            $this->logger->debug("Total unique {$tyNumbers->length} ThankYou Points numbers were found");

            foreach ($tyNumbers as $tyNumber) {
                $cards = $this->http->XPath->query('div[@class = "summery-deatil"]', $tyNumber);
                $this->logger->debug("Total {$cards->length} cards were found");

                foreach ($cards as $card) {
                    $displayName = $this->http->FindSingleNode('.//p[@class = "card-inf"]', $card);
                    $code = $this->http->FindSingleNode('.//p[@class = "card-inf"]', $card, true, "/\((\d{4})\)/");
                    $balance = $this->http->FindSingleNode('.//div[@class = "total-pts"]', $card);

                    $displayName = str_replace(['Card (', ')', 'Citi PremierSM Card -', 'Rewards+SM'], ['Card -', '', 'Citi Premier® Card-', 'Rewards+℠'], $displayName);
                    $this->logger->debug("[displayName]: {$displayName}");
                    $displayName = preg_replace('/\s*\-\s*/', '-', $displayName);
                    $this->logger->debug("[displayName]: {$displayName}");

                    $mainInfo = [
                        "Code"        => 'citybank' . $code,
                        "DisplayName" => $displayName,
                    ];

                    $balanceInfo = [
                        "Balance"           => $balance,
                        "Currency"          => (strstr($balance, '$')) ? "$" : null,
                        "BalanceInTotalSum" => true,
                    ];

                    $this->AddSubAccount($mainInfo + $balanceInfo);
                    $this->AddDetectedCard($mainInfo + ['CardDescription' => C_CARD_DESC_ACTIVE], true);
                }// foreach ($cards as $n => $card)
            }// foreach ($tyNumbers as $tyNumber)

            $allBalances = [];

            if (!isset($this->Properties['SubAccounts'])) {
                return;
            }

            foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                if (strstr($subAccount['Balance'], '$')) {
                    $this->logger->debug("skip cash balance");

                    continue;
                }

                $this->logger->notice("all Balances:");
                $this->logger->debug(var_export($allBalances, true), ["pre" => true]);

                if (!in_array($subAccount['Balance'], $allBalances) && empty($subAccount['Currency'])) {
                    $allBalances[] = floatval(str_replace([',', '.'], ['', ','], $subAccount['Balance']));
                    $this->SetBalance(array_sum($allBalances));
                }
            }

            $this->removeOriginalDisplayName();

            return;
        }

        $this->increaseTimeLimit();

        // refs #11308
        if (!$this->parseFICO() && $this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // This information is temporarily unavailable. Please try again later.
            if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id = 'savingsAccountsCategoryWrapper']//div[contains(text(), 'This information is temporarily unavailable. Please try again later.')]"), 0)) {
                $this->SetWarning($message->getText());
            }
            // stupid provider bug - retries
            if (stristr($this->http->currentUrl(), 'https://online.citi.com/US/login.do?JFP_TOKEN=')
                || stristr($this->http->currentUrl(), 'https://online.citi.com/US/JSO/signon/uname/HomePageCinless.do?SYNC_TOKEN=')) {
                throw new CheckRetryNeededException(2, 7);
            }
        }// elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // refs #18413
        if ($doubleCashCardThankyouPoints === true && isset($this->Properties['SubAccounts']) && isset($this->Properties['DetectedCards'])) {
            $subAccounts = $this->Properties['SubAccounts'];
            $doubleCashCard = null;
            $updateDetectedCards = false;

            foreach ($subAccounts as $subAccount) {
                $this->logger->debug(var_export($subAccount, true), ['pre' => true]);

                if (strstr($subAccount['DisplayName'], 'Double Cash Card')) {
                    $doubleCashCard['Code'] = str_replace('citybank', 'citybankDoubleCash', $subAccount['Code']);
                    $doubleCashCard['DisplayName'] = str_replace(' - ', ' (Thankyou Points) - ', $subAccount['DisplayName']);

                    break;
                }
            }
            // AccountID: 3860832
            if (!$doubleCashCard) {
                $subAccounts = $this->Properties['DetectedCards'];
                $doubleCashCard = null;

                foreach ($subAccounts as $subAccount) {
                    $this->logger->debug(var_export($subAccount, true), ['pre' => true]);

                    if (strstr($subAccount['DisplayName'], 'Double Cash')) {
                        $updateDetectedCards = true;
                        $doubleCashCard['Code'] = str_replace(['citybank', 'citybankDoubleCash'], 'citybankDoubleCash', $subAccount['Code']);
                        $doubleCashCard['Code'] = str_replace('DoubleCashDoubleCash', 'DoubleCash', $doubleCashCard['Code']);
                        $doubleCashCard['DisplayName'] = str_replace(' - ', ' (Thankyou Points) - ', $subAccount['DisplayName']);

                        break;
                    }
                }
            }

            // refs #21504, exclude duplicates
            /*
            if (!$doubleCashCard) {
                return;
            }
            */
            if ($doubleCashCard) {
                $this->logger->info("[Double Cash Card]: " . $doubleCashCard['DisplayName'], ['Header' => 3]);
            }

            try {
                $this->increaseTimeLimit();
                $this->http->GetURL($dashboardURL);
            } catch (
                WebDriverCurlException
                | NoSuchDriverException
                | Facebook\WebDriver\Exception\WebDriverCurlException
                $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                throw new CheckRetryNeededException(2, 1);
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            // AccountID: 4993310
            if ($this->AccountFields['Login'] == 'ManLee') {
//                $loginField = $this->waitForElement(WebDriverBy::xpath('//*[@id = "username"] | //*[@id = "USERNAME"] | //input[@name = "User ID"]'), 10);
                $passField = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'password'] | //input[@id = 'PASSWORD']"), 5);
                $signIn = $this->waitForElement(WebDriverBy::xpath("//input[@alt = 'Sign On' or @value = 'Sign On']"), 0);
                $this->saveResponse();

                if (/*empty($loginField) ||*/ empty($passField) || empty($signIn)) {
                    $this->logger->error('something went wrong');
                    $this->checkErrors();

                    return;
                }
//                $loginField->sendKeys($this->AccountFields['Login']);
                $passField->sendKeys($this->AccountFields['Pass']);
                $this->saveResponse();
                $signIn->click();
            }

            try {
                if (!$this->loginSuccessful()) {
                    sleep(3);
                    $this->loginSuccessful();
                    $this->saveResponse();
                }
            } catch (UnknownServerException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(2, 1);
            }

            $this->openThankYouRewards();

            try {
                $this->saveResponse();
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

                // refs #22992 provider bug fix
                if (
                    strstr($this->http->currentUrl(), 'dashboard/summary')
                    || strstr($this->http->currentUrl(), '/dashboard')
                ) {
                    $this->logger->error("provider bug fix, try to open 'ThankYou Rewards' one more time");
                    $this->openThankYouRewards();
                }
            } catch (Facebook\WebDriver\Exception\WebDriverCurlException | WebDriverCurlException $e) {
                $this->logger->error("WebDriverCurlException: " . $e->getMessage());

                return;
            } catch (NoSuchDriverException $e) {
                $this->logger->error("NoSuchDriverException: " . $e->getMessage());

                return;
            }

            // Help Us Verify Your Identity     // refs #19047
            if ($this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDropdown'] | //div[@id = 'otpDropdown1']"), 0)) {
                $this->removeOriginalDisplayName();
                $this->processIdentificationCodeThankyou();

                return;
            }

            if (
                $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Help Us Verify Your Identity')]"), 0)
                && ($btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0))
            ) {
                $btn->click();
                $this->removeOriginalDisplayName();
                $this->processIdentificationCodeThankyouEntering();

                return;
            }

            $this->saveResponse();

            if ($this->http->FindSingleNode('//h2[contains(text(), "Error 500--Internal Server Error")]')) {
                $this->sendNotification("no Double Cash - refs #19047 // RR");
            }

            // refs #21504, exclude duplicates
            $tyNumbers = $this->http->XPath->query('//div[@class = "summery-deatil-wrapper"]');
            $this->logger->debug("Total unique {$tyNumbers->length} ThankYou Points numbers were found");

            // refs #21504, exclude duplicates (only one TY number)
            if ($tyNumbers->length === 0
                && (
                    $this->http->currentUrl() == 'https://www.thankyou.com/cms/thankyou/'
                    // refs #23175
                    || strstr($this->http->currentUrl(), 'https://www.thankyou.com/tyAccountLocked.htm?')
                )
            ) {
                $this->logger->notice("exclude duplicates (only one TY number)");
                $allBalances = [];
                $balanceSum = null;

                foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                    if (strstr($subAccount['Balance'], '$') || strstr($subAccount['Code'], 'FICO')) {
                        $this->logger->debug("skip subaccount: {$subAccount['DisplayName']}");

                        continue;
                    }

                    if (!in_array($subAccount['Balance'], $allBalances) && empty($subAccount['Currency'])) {
                        $allBalances[] = $subAccount['Balance'];
                        $balanceSum += floatval(str_replace([',', '.'], ['', ','], $subAccount['Balance']));
                    }
                }// foreach ($this->Properties['SubAccounts'] as &$subAccount)

                $this->logger->notice("all Balances:");
                $this->logger->debug(var_export($allBalances, true), ["pre" => true]);
                $this->logger->notice("Summary: {$balanceSum}");

                if ($balanceSum !== null) {
                    $this->SetBalance($balanceSum);
                }
            }// if ($tyNumbers->length === 0 && $this->http->currentUrl() == 'https://www.thankyou.com/cms/thankyou/')

            foreach ($tyNumbers as $tyNumber) {
                $cards = $this->http->XPath->query('div[@class = "summery-deatil"]', $tyNumber);
                $this->logger->debug("Total {$cards->length} cards were found");
                $this->logger->debug("Main balance: {$this->Balance}");

                foreach ($cards as $n => $card) {
                    $this->logger->debug("card #{$n}");
                    $connectedCard = $this->http->FindSingleNode('.//p[@class = "card-inf"]', $card);
                    $connectedCardBalance = $this->http->FindSingleNode('.//div[@class = "total-pts"]/text()[1]', $card);

                    if ($cards->length != 1 && $n == 0) {
                        $this->logger->debug("skip first card in collection");
                        $this->logger->debug("[connectedCard]: {$connectedCard}");
                        $this->logger->debug("[connectedCardBalance]: {$connectedCardBalance}");

                        continue;
                    }

                    unset($subAccount);

                    foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                        $connectedCard = str_replace(['Card (', ')', 'Citi PremierSM Card -', 'Rewards+SM', 'from CITI (', 'THE AT&T ACCESS MORE CARD', 'Citi® Custom Cash Card'], ['Card -', '', 'Citi Premier® Card-', 'Rewards+℠', 'from CITI - ', 'AT&T ACCESS MORE CARD', 'Citi Custom Cash® Card'], $connectedCard);
                        $this->logger->debug("[connectedCard]: {$connectedCard}");
                        $connectedCard = preg_replace('/\s*\-\s*/', '-', $connectedCard);
                        $this->logger->debug("[connectedCard]: {$connectedCard}");
                        $this->logger->debug("[connectedCardBalance]: {$connectedCardBalance}");
                        $subAccount['DisplayName'] = str_replace(['Card –'], ['Card -'], $subAccount['DisplayName']);
                        $subAccount['DisplayName'] = preg_replace('/\s*\-\s*/', '-', $subAccount['DisplayName']);
                        $this->logger->debug("[subAccount-2]: {$subAccount['DisplayName']}");

                        if (
                            /*
                             * [connectedCard]: Citi® Double Cash Card -5287
                             * [connectedCard]: Citi® Double Cash Card-5287
                             * [subAccount-2]: Citi Prestige® Card ending in 1035 | Citi® Double Cash Card ending in 5287
                             * [connectedCard]: Citi Prestige® Card -1035
                             * [connectedCard]: Citi Prestige® Card-1035
                             * [subAccount-2]: Citi Prestige® Card ending in 1035 | Citi® Double Cash Card ending in 5287
                             * [connectedCard]: Citi® Double Cash Card -5287
                             * [connectedCard]: Citi® Double Cash Card-5287
                             */
                            (
                                stristr($subAccount['DisplayName'], $connectedCard)
                                // refs #23866
                                || (isset($subAccount["OriginalDisplayName"]) && stristr($subAccount["OriginalDisplayName"], $connectedCard))
                                || stristr($subAccount['DisplayName'], str_replace('Citi® Double Cash', 'Citi Double Cash®', $connectedCard))
                                || stristr($subAccount['DisplayName'], str_replace('Rewards+SM', 'Rewards+℠', $connectedCard))
                                || stristr(str_replace(' ending in ', '-', $subAccount['DisplayName']), $connectedCard)
                                || stristr(str_replace('ThankYou® Preferred-', 'ThankYou® Preferred Card-', $subAccount['DisplayName']), $connectedCard)
                                // AccountID: 3086800
                                || ($subAccount['DisplayName'] == 'Card ending 2826' && $connectedCard == 'Citibank® Checking Account and Other Everyday Banking (2826')
                            )
                            // refs #21504 note-12
                            && substr_count($subAccount['DisplayName'], 'ending in') < 2
                            && $subAccount['DisplayName'] != "Citi Premier® Card-1131"// refs #23270
                            // AccountID: 3086800
                            && !stristr($subAccount['DisplayName'], "Citi ThankYou® Preferred Card-2638 | Citi Prestige® Card-0382")// refs #23530
                            && $connectedCardBalance !== null
                        ) {
                            if (
                                (
                                    $cards->length == 1
                                    && $subAccount['Balance'] === $connectedCardBalance
                                )
                                || !empty($subAccount['Balance']['Currency'])
                            ) {
                                $this->logger->notice("One card, already exist, skip it");

                                continue;
                            }

                            $this->logger->notice("exclude duplicate Balance for {$subAccount['DisplayName']}");
                            $this->SetBalance($this->Balance - floatval(str_replace([',', '.'], ['', ','], $subAccount['Balance'])));
                            $subAccount['BalanceInTotalSum'] = true;
                            unset($subAccount['Currency']);
                        }
                    }// $subAccount['DisplayName']
                }// foreach ($cards as $n => $card)
            }// foreach ($tyNumbers as $tyNumber)

            $balance =
                $this->http->FindSingleNode('//div[contains(@class, "card-element-text") and contains(., "Double Cash Card")]/ancestor::td[count(div) = 1]/ancestor::tr[contains(@class, "select-card-element")]//div[contains(@class, "card-element-total-available-points")]/span')
                ?? $this->http->FindSingleNode('//div[contains(@class, "summery-deatil") and contains(., "Double Cash Card")]/ancestor::div[contains(@class, "summery-deatil")]//div[contains(@class, "total-pts")]/text()[1]')
            ;

            // refs #19047
            $connectedCards = $this->http->FindNodes('//div[contains(@class, "summery-deatil") and contains(., "Double Cash Card")]/ancestor::div[contains(@class, "summery-deatil")]//p[@class = "card-inf"]');

            if (isset($balance)) {
                $doubleCashCard['Balance'] = $balance;
                $doubleCashCard['BalanceInTotalSum'] = true;

                if ($updateDetectedCards === true) {
                    $this->AddDetectedCard(array_merge($doubleCashCard, ["CardDescription" => C_CARD_DESC_ACTIVE]), true);
                }

                // refs #19047
                $alreadyExist = false;

                foreach ($this->Properties['SubAccounts'] as $subAccount) {
                    foreach ($connectedCards as $connectedCard) {
                        $connectedCard = str_replace(['Card (', ')', 'Citi PremierSM Card -', 'Rewards+SM', 'from CITI (', 'THE AT&T ACCESS MORE CARD'], ['Card -', '', 'Citi Premier® Card-', 'Rewards+℠', 'from CITI - ', 'AT&T ACCESS MORE CARD'], $connectedCard);
                        $this->logger->debug("[connectedCard]: {$connectedCard}");
                        $connectedCard = preg_replace('/\s*\-\s*/', '-', $connectedCard);
                        $this->logger->debug("[connectedCard]: {$connectedCard}");
                        $subAccount['DisplayName'] = str_replace(['Card –'], ['Card -'], $subAccount['DisplayName']);
                        $subAccount['DisplayName'] = preg_replace('/\s*\-\s*/', '-', $subAccount['DisplayName']);
                        $this->logger->debug("[subAccount-2]: {$subAccount['DisplayName']}");

                        if (
                            /*
                             * [connectedCard]: Citi® Double Cash Card -5287
                             * [connectedCard]: Citi® Double Cash Card-5287
                             * [subAccount-2]: Citi Prestige® Card ending in 1035 | Citi® Double Cash Card ending in 5287
                             * [connectedCard]: Citi Prestige® Card -1035
                             * [connectedCard]: Citi Prestige® Card-1035
                             * [subAccount-2]: Citi Prestige® Card ending in 1035 | Citi® Double Cash Card ending in 5287
                             * [connectedCard]: Citi® Double Cash Card -5287
                             * [connectedCard]: Citi® Double Cash Card-5287
                             */
                            (
                                strstr($subAccount['DisplayName'], $connectedCard)
                                // refs #23866
                                || (isset($subAccount["OriginalDisplayName"]) && stristr($subAccount["OriginalDisplayName"], $connectedCard))
                                || stristr($subAccount['DisplayName'], str_replace('Citi® Double Cash', 'Citi Double Cash®', $connectedCard))
                                || strstr(str_replace(' ending in ', '-', $subAccount['DisplayName']), $connectedCard)
                            )
                            && $subAccount['Balance'] == $doubleCashCard['Balance']
                        ) {
                            $this->logger->debug(var_export($doubleCashCard, true), ['pre' => true]);
                            $this->logger->notice("skip adding " . ($doubleCashCard['DisplayName'] ?? null));
                            $alreadyExist = true;
                        }
                    }// foreach ($connectedCards as $connectedCard)
                }// foreach ($this->Properties['SubAccounts'] as $subAccount)

                if ($alreadyExist === true) {
                    $this->removeOriginalDisplayName();

                    return;
                }

                $this->AddSubAccount($doubleCashCard, true);

                $this->Balance += floatval(str_replace([',', '.'], ['', ','], $balance));
                $this->SetBalance($this->Balance);
            }
        }// if ($doubleCashCardThankyouPoints === true && isset($this->Properties['SubAccounts']) && isset($this->Properties['DetectedCards']))

        $this->removeOriginalDisplayName();
    }

    public function parsePageSubAccHistory()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("SubAcc History", ['Header' => 3]);
        $result = [];

        if (!$this->WantHistory) {
            return $result;
        }
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $JFP_TOKEN = $this->http->FindPreg('/JFP_TOKEN=([^&]+)/', false, $this->http->currentUrl()) ?? $this->http->FindPreg('/JFP_TOKEN=([^&\"]+)/');

        $this->browser->LogHeaders = true;
        $this->browser->SetProxy($this->http->GetProxy());
        $this->browser->GetURL($this->http->currentUrl());

        // refs #20063
        $headers = [
            "Accept"        => "application/json",
            "Content-Type"  => "application/json",
            "client_id"     => $this->browser->getCookieByName("client_id", ".citi.com", "/"),
            "businessCode"  => $this->browser->getCookieByName("businessCode", ".citi.com", "/"),
            "countryCode"   => $this->browser->getCookieByName("countryCode", ".citi.com", "/"),
            "channelId"     => $this->browser->getCookieByName("channelId", ".citi.com", "/"),
            "appVersion"    => $this->browser->getCookieByName("appVersion", ".citi.com", "/"),
            "bizToken"      => $this->browser->getCookieByName("bizToken", ".citi.com", "/"),
            "Authorization" => "Bearer " . $this->browser->getCookieByName("Authorization", ".citi.com", "/"),
        ];
        $this->browser->RetryCount = 0;
        $this->browser->GetURL("https://online.citi.com/gcgapi/prod/public/v1/digital/customerEntitlement/browser/branding", $headers);
        $this->browser->RetryCount = 2;
        $response = $this->browser->JsonLog(null, 0);

        if (!isset($response->accounts)) {
            $this->logger->notice("no history accounts were found, try old request");
            $this->browser->PostURL("https://online.citi.com/US/REST/nga/ngapostbranding.jws?JFP_TOKEN={$JFP_TOKEN}", [], ["Accept" => "application/json, text/javascript, */*; q=0.01", "X-Requested-With" => "XMLHttpRequest"]);
            $response = $this->browser->JsonLog(null, 0);
        }
        // refs #19146
        /*
        if (!isset($response->accounts)) {
            $this->logger->notice("no history accounts were found");
            $this->browser->PostURL("https://online.citi.com/US/REST/nga/ngasessionmanagement.jws", '{"coexistenceNeeded": "N","gemfirePushNeeded": "N"}', ["Accept" => "application/json, text/javascript, *
            /*; q=0.01", "X-Requested-With" => "XMLHttpRequest"]);
            $response = $this->browser->JsonLog(null, 0);
        }
        */
        if (!isset($response->accounts)) {
            $this->logger->notice("no history accounts were found");

            return $result;
        }

        foreach ($response->accounts as $account) {
            $this->logger->debug(var_export($account, true), ['pre' => true]);

            if (!stristr($account->completeDescription, 'prestige')
                && !stristr($account->completeDescription, 'thankyou premier')
                && !stristr($account->completeDescription, 'Premier')
                && !stristr($account->completeDescription, 'thankyou® premier')
                && !stristr($account->completeDescription, 'thankyou preferred')
                && !stristr($account->completeDescription, 'thankyou® preferred')
                && !stristr($account->description, 'prestige')
                && !stristr($account->description, 'preferred')
                && !stristr($account->description, 'premier')
                // refs #22118 - Citi Custom Cash
                && !stristr($account->description, 'Cash')
                && !stristr($account->completeDescription, 'Custom Cash')
                // refs #22118 - Citi Double Cash® Card
                && !stristr($account->description, 'Double Cash')
                && !stristr($account->completeDescription, 'Double Cash')
            ) {
                $this->logger->notice("Skip card: {$account->description} / {$account->completeDescription}");

                continue;
            }
            // refs #16044
            $prestigeCard = false;

            if (stristr($account->completeDescription, 'prestige') || stristr($account->description, 'prestige')) {
                $prestigeCard = true;
            }

            $code = $this->browser->FindPreg("/Card-(\d+)/", false, $account->completeDescription);

            if (!$code) {
                $code =
                    $this->browser->FindPreg("/-(\d{4})$/", false, $account->completeDescription)
                    ?? $account->displayAccountNumber
                    ?? null
                ;
            }

            if (!$code && empty($account->completeDescription) && $account->description) {
                $code = $account->description;
            }

            if (!$code) {
                $this->logger->error("Card code not found: {$account->completeDescription}");

                continue;
            }

            $code = $this->getSubAccountCardCode($code);

            if (in_array($code, $this->closedCards)) {
                $this->logger->error("skip Closed card: {$account->completeDescription}");

                continue;
            }

            $this->logger->info("History for card ...{$code}", ['Header' => 3]);

            // go to transaction page
            $this->browser->GetURL("https://online.citi.com/US/NCPS/accountdetailactivity/flow.action?targetApp=accountactivity&accountInstanceId={$account->accountInstanceId}&recentTransNavLnk=true&JFP_TOKEN={$JFP_TOKEN}");
            $this->increaseTimeLimit();
            $this->browser->GetURL("https://online.citi.com/US/CBOL/ain/caraccdet/flow.action?instanceID={$account->accountInstanceId}&toFocusTJ=true&JFP_TOKEN={$JFP_TOKEN}");

            // get all transactions
            $startDate = $this->getSubAccountHistoryStartDate($code);
            $this->logger->notice("[startDate]: {$startDate}");

            // old request
            $NGACoExistenceCookie = $this->browser->getCookieByName("NGACoExistenceCookie", "online.citi.com");

            if ($NGACoExistenceCookie) {
                $apiSettings = explode('|', $NGACoExistenceCookie);
                $this->logger->debug(var_export($apiSettings, true), ['pre' => true]);
                $headers = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "X-Requested-With" => "XMLHttpRequest",
                    "Accept-Encoding"  => "gzip, deflate, br",
                    "Content-Type"     => "application/json",
                ];

                foreach ($apiSettings as $apiSetting) {
                    [$setting, $value] = explode('=', $apiSetting);

                    switch ($setting) {
                        case 'authToken':
                            $headers['Authorization'] = "Bearer {$value}";

                            break;

                        case 'clientId':
                            $headers['client_id'] = $value;

                            break;

                        case 'bizToken':
                            $headers[$setting] = $value;

                            break;

                        case 'apimBaseUrl':
                            $apimBaseUrl = $value;

                            break;
                    }
                }// foreach ($apiSettings as $apiSetting)
            } else {
                $headers = [
                    "Accept"           => "application/json, text/javascript, */*; q=0.01",
                    "X-Requested-With" => "XMLHttpRequest",
                    "Accept-Encoding"  => "gzip, deflate, br",
                    "Content-Type"     => "application/json",
                    "bizToken"         => $this->browser->getCookieByName("bizToken", ".citi.com", "/"),
                    "client_id"        => $this->browser->getCookieByName("client_id", ".citi.com", "/"),
                    "Authorization"    => "Bearer " . $this->browser->getCookieByName("Authorization", ".citi.com", "/"),
                ];
                $apimBaseUrl = $this->browser->getCookieByName("apimBaseUrl", ".citi.com", "/");
            }

            $lowerBoundDate = date("M. d, Y", strtotime("-3 year"));
            $upperBoundDate = date("M. d, Y");
            $historyWithRewards = false;

            $lowBoundDate = date("m/d/Y", strtotime("-3 year"));
            $upBoundDate = date("m/d/Y");
            $allTransactionsList = [];
            $page = 0;
            $nextTableIndex = null;
            $transactionSource = null;
            $moreTransactionsFlag = null;
            $nextTransactionSequenceNumber = null;

            // refs #24183 get only last history year for 'jordansilber2'
            if ($this->AccountFields['Login'] == 'jordansilber2') {
                $lowBoundDate = date("m/d/Y", strtotime("-1 year"));
                $lowerBoundDate = date("M. d, Y", strtotime("-1 year"));
                $this->logger->notice("[CUSTOM lowBoundDate / lowerBoundDate]: {$lowBoundDate} / {$lowerBoundDate}");
            }

            do {
                $data = [
                    "timePeriodFilter"              => [
                        "groupId"         => "Custom",
                        "filterIndicator" => "DATE_RANGE",
                        "displayValue"    => "Date range",
                        "displayLabel"    => "From {$lowBoundDate} To {$upBoundDate}",
                        "startDate"       => $lowBoundDate,
                        "endDate"         => $upBoundDate,
                    ],
                    "nextTableIndex"                => $nextTableIndex,
                    "transactionSource"             => $transactionSource,
                    "nextTransactionSequenceNumber" => $nextTransactionSequenceNumber,
                    "moreTransactionsFlag"          => $moreTransactionsFlag,
                    "dropDownOperationsCount"       => 1,
                    "searchOperationsCount"         => 1,
                ];
                $this->browser->PostURL("{$apimBaseUrl}/cbol/accounts/{$account->accountInstanceId}/transactions/retrieve", json_encode($data), $headers);
                $transactionsList = $this->browser->JsonLog(null, 2);

                if (empty($transactionsList->accountActivity->postedTransactions)) {
                    $this->logger->notice("no transactions found");

                    break;
                }

                $nextTableIndex = $transactionsList->accountActivity->nextTableIndex ?? null;
                $transactionSource = $transactionsList->accountActivity->transactionSource ?? null;
                $nextTransactionSequenceNumber = $transactionsList->accountActivity->nextTransactionSequenceNumber ?? null;
                $moreTransactionsFlag = $transactionsList->accountActivity->moreTransactionsFlag ?? null;

                $allTransactionsList = array_merge($allTransactionsList, $transactionsList->accountActivity->postedTransactions);
                $this->logger->debug("Total " . count($transactionsList->accountActivity->postedTransactions) . " transactions were found");

                $wording = 'HAS NO';

                if (isset($transactionsList->accountActivity->transactionHeaders[3])) {
                    if ($transactionsList->accountActivity->transactionHeaders[3]->columnName != 'Rewards') {
                        $this->sendNotification("refs #19146 - unknown columnName {$transactionsList->accountActivity->transactionHeaders[3]->columnName}");
                    }
                    $wording = 'HAS';
                    $historyWithRewards = true;
                }
                $this->logger->notice("Main history {$wording} Points info");
            } while (
                    $page < 5
                    && $nextTableIndex
                    && $transactionSource
                    && $moreTransactionsFlag
                    && $nextTransactionSequenceNumber
                );

            $this->logger->debug("Total " . count($allTransactionsList) . " transactions were found");

            if (
                $historyWithRewards === true
                // refs #22118 - Citi Double Cash® Card
                || (
                    stristr($account->description, 'Double Cash')
                    || stristr($account->completeDescription, 'Double Cash')
                )
            ) {
                $result = array_merge($result, $this->parseSubAccHistoryFromMainPage($code, $startDate, $allTransactionsList, $prestigeCard, $account));

                continue;
            }

            $result = array_merge($result, $this->parseSubAccHistoryByCategories($code, $startDate, $allTransactionsList, $prestigeCard, $account, $lowerBoundDate, $upperBoundDate, $JFP_TOKEN, $headers));
        }// foreach ($response->accounts as $account)

//        $this->logger->debug(var_export($result, true), ['pre' => true]);
        foreach ($result as $key => $value) {
            $this->logger->debug("try to find subAccount with code: {$key}");
            $found = false;

            if (!empty($this->Properties['SubAccounts'])) {
                foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                    if ($subAccount['Code'] == $key) {
                        $found = true;
                        $subAccount["HistoryRows"] = $value["HistoryRows"];
                    }// if ($subAccount['Code'] == $key)
                }// foreach ($this->Properties['SubAccounts'] as &$subAccount)
            }// if (!empty($this->Properties['SubAccounts']))

            if (!$found) {
                $this->AddSubAccount([
                    'Code'        => $key,
                    'DisplayName' => $value["DisplayName"],
                    'Balance'     => null,
                    'IsHidden'    => true,
                    'HistoryRows' => $value["HistoryRows"],
                ], true);
            }// if (!$found)
        }// foreach ($result as $key => $value)

        return $result;
    }

    public function parseSubAccHistoryFromMainPage($code, $startDate, $allTransactionsList, $prestigeCard, $account)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        // #note-12
        $specialCategories = [
            'Air Travel',
            'Lodging',
            'Auto Rental',
        ];
        $res = [];
        $startIndex = 0;

        foreach ($allTransactionsList as $allTransactionsInfo) {
            $date = strtotime($this->http->FindPreg("/([^\|]+)/", false, $allTransactionsInfo->columns[0]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[0]->actualValue));
            $additionalInfo = [];

            foreach ($allTransactionsInfo->extendedDescriptions as $extendedDescription) {
                $label = trim(str_replace(":", "", $extendedDescription->label ?? $extendedDescription->displayLabel));

                if (in_array($label, ['Transaction Type', 'Category', 'Reference Number', 'Merchant Country'])) {
                    if ($label == 'Category') {
                        $label = 'Transaction Info';
                        // refs #17468
                        if (
                            stristr($extendedDescription->value[0] ?? $extendedDescription->displayValue, 'cruise')
                            && stristr($extendedDescription->value[0] ?? $extendedDescription->displayValue, 'boat')
                        ) {
                            /*
                            if (isset($allTransactionsInfo->columns[0]->activityColumn[0])) {
                                $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->columns[0]->activityColumn[0]}: Cruise may be was found");
                            } else {
                                $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->transactionColumns[0]->actualValue}: Cruise may be was found");
                            }
                            */
                            $this->logger->debug(var_export($allTransactionsInfo, true), ['pre' => true]);
                        }
                    }
                    $additionalInfo[$label] = $extendedDescription->value[0] ?? $extendedDescription->displayValue;
                }
            }// foreach ($allTransactionsInfo->extendedDescriptions as $extendedDescription)
            $amount = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $allTransactionsInfo->columns[2]->dateForSorting ?? $allTransactionsInfo->transactionColumns[2]->actualValue);

            // refs #17468
            if (
                stristr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'cruise')
                && stristr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'boat')
            ) {
                if (isset($allTransactionsInfo->columns[0]->activityColumn[0])) {
                    $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->columns[0]->activityColumn[0]}: Cruise may be was found");
                } else {
                    $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->transactionColumns[0]->actualValue}: Cruise may be was found");
                }
                $this->logger->debug(var_export($allTransactionsInfo, true), ['pre' => true]);
            }

            if ($prestigeCard
                && (
                    strstr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'Congrats! Citi Air Travel Credit')
                    || Html::cleanXMLValue($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue) == 'Travel Credit'// refs #17620
                )
            ) {
                $airTravelCreditTransactions[] = array_merge(
                    [
                        "Date"             => $this->http->FindPreg("/([^\|]+)/", false, $allTransactionsInfo->columns[0]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[0]->actualValue),
                        'Transaction Data' => json_encode($allTransactionsInfo->extendedDescriptions),
                        'Amount'           => $amount,
                    ],
                    $additionalInfo
                );
            }// if ($prestigeCard && strstr($allTransactionsInfo->columns[1]->activityColumn[0], 'Congrats! Citi Air Travel Credit'))

            $dateStr = $this->http->FindPreg("/([^\|]+)/", false, $allTransactionsInfo->transactionPurchaseDate);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            // Transaction
            $res[$startIndex]['Date'] = $postDate;
            // Merchant
            $res[$startIndex]['Merchant'] = $allTransactionsInfo->transactionDescription;
            // Amount
            $res[$startIndex]['Amount'] = $amount;

            if ($res[$startIndex]['Amount']) {
                $res[$startIndex]['Currency'] = 'USD';
            }

            $res[$startIndex]['Transaction Data'] = json_encode($allTransactionsInfo->extendedDescriptions);

            if (isset($additionalInfo['Transaction Info'])) {
                // Category
                $res[$startIndex]['Category'] = $this->http->FindPreg("/^([^\-]+)\s*\-\s*.+/", false, $additionalInfo['Transaction Info']) ?? $additionalInfo['Transaction Info'];
                $merchant = $this->http->FindPreg("/^[^\-]+\s*\-\s*(.+)/", false, $additionalInfo['Transaction Info']);

                // https://redmine.awardwallet.com/issues/16337#note-79
                if ($res[$startIndex]['Category'] == 'Vehicle Services' && stristr($additionalInfo['Transaction Info'], 'GAS ')) {
                    $res[$startIndex]['Category'] = 'Gas stations';
                }

                unset($additionalInfo['Transaction Info']);

                if ($merchant && in_array($res[$startIndex]['Category'], $specialCategories)) {
                    $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                    $res[$startIndex]['Merchant'] = $merchant;
                }// if ($merchant)
                elseif ($merchant) {
                    $res[$startIndex]['Transaction Info'] = $merchant;
                }

                $res[$startIndex] = array_merge($res[$startIndex], $additionalInfo);
            }// if (isset($additionalInfo['Transaction Info']))
            else {
                $res[$startIndex]['Category'] = 'Miscellaneous';
                $this->logger->debug(var_export($allTransactionsInfo, true), ['pre' => true]);
            }

            // https://redmine.awardwallet.com/issues/16337#note-82
            if (strstr($res[$startIndex]['Merchant'], 'STOP:')
                || strstr($res[$startIndex]['Merchant'], 'FOLIO NUMBER:')
                || strstr($res[$startIndex]['Merchant'], 'AGREEMENT NUMBER:')
            ) {
                $replaceMerchant = false;
                $merchant = null;

                if ($res[$startIndex]['Category'] == 'Air Travel') {
                    $vocabulary = [
                        "AGENT FE"       => "AGENT FEE",
                        "AEGEAN"         => "AEGEAN WEB KIFISIA GRC",
                        "AERLING"        => "AER LINGUS",
                        "AEROMEXI"       => "AEROMEXICO",
                        "AIR BERL"       => "AIR BERLIN",
                        "AIRBERLI"       => "AIR BERLIN",
                        "AIR CAN"        => "AIR CANADA",
                        "AIR CHIN"       => "AIR CHINA",
                        "AIR EURO"       => "AIR EUROPA LINEAS",
                        "AIR FRAN"       => "AIR FRANCE",
                        "AIR-INDI"       => "AIR-INDIA",
                        "AIR NZ"         => "AIR NEW ZEALAND",
                        "ALASKA A"       => "ALASKA AIRLINES",
                        "ALASKA"         => "ALASKA AIRLINES",
                        "ALG"            => "AIRLINE,AIR CARRIER",
                        "ANAAIR"         => "ASIANA AIRLINES",
                        "AMERICAN"       => "AMERICAN AIRLINES",
                        "AMERICAN AIR"   => "AMERICAN AIRLINES",
                        "AUSTRIAN"       => "AUSTRIAN AIRLINES",
                        "AVIANCA"        => "AVIANCA",
                        "AZERBAIJ"       => "AZERBAIJAN AIRLINES",
                        "BRUSSELS"       => "BRUSSELS AIRLINES",
                        "BRITISH"        => "BRITISH AIRWAYS",
                        "BOLIVIAN"       => "BOLIVIANA DE AVIACION",
                        "CATHAYPA"       => "CATHAY PACIFIC",
                        "CATHAY P"       => "CATHAY PACIFIC",
                        "CHINAAIR"       => "CHINA AIRLINES",
                        "CHINA AIRLINES" => "CHINA AIRLINES",
                        "CHINA SO"       => "CHINA SOUTHERN AIRLINES",
                        "CHINA SOUTH"    => "CHINA SOUTHERN AIRLINES",
                        "CHINAEAS"       => "CHINAEAST AIR",
                        "COBALT A"       => "COBALT AIR",
                        "CROATIA"        => "CROATIA AIRLINES",
                        "DELTA"          => "DELTA",
                        "DELTA AIR"      => "DELTA",
                        "ETHIOPIA"       => "ETHIOPIAN AIRLINES",
                        "ETIHADAI"       => "ETIHADAIR",
                        "EMIRATES"       => "EMIRATES AIRLINES",
                        "EASYJET"        => "EASYJET AIR",
                        "EVA AIR"        => "EVA AIRWAYS CORPORATION",
                        "GARUDA"         => "GARUDA (INDONESIA)",
                        "HAWAIIAN"       => "HAWAIIAN AIR",
                        "HAHN"           => "HAHN AIR",
                        "JAL"            => "JAPAN AIRLINES",
                        "JETBLUE"        => "JETBLUE",
                        "IBERIA"         => "IBERIA",
                        "ICELANDA"       => "ICELANDAIR",
                        "ISLAND A"       => "ISLAND AIR",
                        "IslandAi"       => "ISLAND AIR",
                        "LOT"            => "LOT-POLAND",
                        "LATAM AI"       => "LATAM AIRLINES",
                        "LAN AIR"        => "LAN AIRLINES-LAN AIR",
                        "LUFTHANS"       => "LUFTHANSA",
                        "KOREAN"         => "KOREAN AIRLINES",
                        "MALAY AI"       => "MALAYSAIN AIR SYS",
                        "MIDEASTA"       => "MIDDLE EAST AIR",
                        "NORWEGIA"       => "NORWEGIAN AIR SHUTTLE",
                        "PAKISTAN"       => "PAKISTAN INTERNATIONAL AIRLINES",
                        "PORTER A"       => "PORTER AIRLINES",
                        "ROYAL JO"       => "ROYAL JORDANIAN AIRLINES",
                        "ROYAL BR"       => "ROYAL BRUNEI AIRLINES",
                        "RYANAIR"        => "RYANAIR",
                        "SOUTHWES"       => "SOUTHWEST",
                        "SATA INT"       => "SATA INTERNATIONAL",
                        "SCOOT PT"       => "SCOOT",
                        "SINGAPOR"       => "SINGAPORE AIRLINES",
                        "SINGAPORTIX"    => "SINGAPORE AIRLINES",
                        "SICHUAN"        => "SICHUAN AIRLINES",
                        "SILVER A"       => "SINGAPORE AIR",
                        "SNBRU AI"       => "SNBRU AIR",
                        "SPIRIT A"       => "SPIRIT AIRLINES",
                        "SriLanka"       => "SRILANKAN AIRLINES",
                        "SKY AIRL"       => "SKY AIRLINE",
                        "SUNCNTRY"       => "SUN COUNTRY AIRLINES - SUNCNTRY",
                        "SUNCTRYAIR"     => "SUN COUNTRY AIRLINES - SUNCNTRY",
                        "SWISS IN"       => "SWISSINTAIR",
                        "TAP"            => "TAP (PORTUGAL)",
                        "TAP PORT"       => "TAP (PORTUGAL)",
                        "THY"            => "THY (TURKEY)",
                        "TransaviHDM"    => "TRANSAVIA",
                        "QATAR"          => "QATAR AIRWAYS",
                        "QATAR AI"       => "QATAR AIRWAYS",
                        "UNITED AIR"     => "UNITED AIRLINES",
                        "UNITED"         => "UNITED AIRLINES",
                        "VARIG"          => "VARIG (BRAZIL)",
                        "VIR AMER"       => "VIR AMER",
                        "VIRGIN A"       => "VIRGIN ATLANTIC",
                        "VIR ATL"        => "VIRGIN ATLANTIC",
                        "VIVAAERO"       => "VIVA AEROBUS",
                        "WESTJET"        => "WESTJET AIRLINES",
                        "XL AIRWA"       => "XL AIRWAY WASHINGTON DC DC",
                        "WWW.AUST"       => "AUSTRIAN AIRLINES",
                        "WWW"            => "AIRLINE,AIR CARRIER",
                        "HONG KON"       => "AIRLINE,AIR CARRIER",
                        "NAME"           => "AIRLINE,AIR CARRIER",
                        "VAA MOBI"       => "AIRLINE,AIR CARRIER",
                    ];
                    $merchant = $this->http->FindPreg("/^\s*([a-z\s]+)/ims", false, $res[$startIndex]['Merchant']);

                    if (isset($vocabulary[$merchant]) && !in_array($merchant, ['JET'])) {
                        $replaceMerchant = true;
                        $merchant = $vocabulary[$merchant];
                    }// if (isset($vocabulary[$merchant]))
                }// if ($category == 'Air Travel')

                $vocabulary = [];

                if ($res[$startIndex]['Category'] == 'Air Travel' && (empty($merchant) || !$replaceMerchant)) {
                    $vocabulary = [
                        "ADRIA "      => "ADRIA AIRWAYS",
                        "ASIANA"      => "ASIANA AIRLINES",
                        "A"           => "AEGEAN WEB KIFISIA GRC",
                        "AEGEAN"      => "AEGEAN WEB KIFISIA GRC",
                        "BOLIVIANA"   => "BOLIVIANA DE AVIACION",
                        "easyJet"     => "EASYJET AIR",
                        'EUROWING'    => 'EUROWINGS',
                        'Flybe'       => 'FLYBE',
                        'FlyUIA'      => 'FLYBE',
                        'FRONTIER'    => 'FRONTIER AIRLINES',
                        "GARUDA"      => "GARUDA (INDONESIA)",
                        "IslandAi"    => "ISLAND AIRLINES",
                        "JETAIRWAY "  => "JET AIRWAYS - JET AIR",
                        "JET AIRWAY"  => "JET AIRWAYS - JET AIR",
                        "JETSTAR"     => "JETSTAR AIR",
                        "JET2.COM"    => "JET2.COM",
                        "KLM"         => "KLM",
                        "KULULA"      => "AIRLINE,AIR CARRIER",
                        "norwegia"    => "NORWEGIAN AIR SHUTTLE",
                        "PAWA WEB"    => "AIRLINE,AIR CARRIER",
                        "PAWA ARC"    => "AIRLINE,AIR CARRIER",
                        'RYANAIR'     => 'RYANAIR',
                        'ROMA ITA'    => 'ALITALIA',
                        'S7 Air'      => 'S7 AIRLINES',
                        'Transavi'    => 'TRANSAVIA',
                        'QANTAS'      => 'QANTAS',
                        'Vueling'     => 'VUELING AIRLINES',
                        'VOLARIS'     => 'VOLARIS',
                        'WOW AIR'     => 'WOW AIR Reykjavík',
                        'Ukrain'      => 'UKRAINE INTERNATIONAL',
                        'WIZZ AIR'    => 'WIZZ AIR',
                        'XIAMEN '     => 'XIAMEN AIRLINES',
                    ];
                }// if ($category == 'Air Travel' && (empty($merchant) || !$replaceMerchant))
                elseif ($res[$startIndex]['Category'] == 'Lodging') {
                    $vocabulary = [
                        'HYATT PLACE'       => 'HYATT PLACE',
                    ];
                }// if ($category == 'Lodging')

                foreach ($vocabulary as $keyWord => $val) {
                    $this->logger->debug("Word: {$keyWord}");

                    if (stristr(Html::cleanXMLValue($res[$startIndex]['Merchant']), $keyWord)) {
                        $replaceMerchant = true;
                        $merchant = $val;

                        break;
                    }// if (stristr($res[$startIndex]['Merchant'], $keyWord))
                }// foreach ($vocabulary as $word)
                $this->logger->debug("Right merchant => '{$merchant}'");

                if ($replaceMerchant) {
                    $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                    $res[$startIndex]['Merchant'] = $merchant;
                } else {
                    if ($res[$startIndex]['Category'] == 'Air Travel' && !empty($merchant)) {
                        $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                        $res[$startIndex]['Merchant'] = $merchant;
                    } elseif (in_array($res[$startIndex]['Category'], ['Lodging', 'Auto Rental']) && strstr($res[$startIndex]['Merchant'], 'PHONE NUMBER:')) {
                        $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                        $res[$startIndex]['Merchant'] = $this->http->FindPreg("/(.+)PHONE NUMBER:/ms", false, $res[$startIndex]['Merchant']);
                    }

                    // https://redmine.awardwallet.com/issues/16337#note-84
                    if ($res[$startIndex]['Category'] == 'Air Travel' || (in_array($res[$startIndex]['Category'], ['Lodging', 'Auto Rental']) && empty($res[$startIndex]['Merchant']))) {
                        $this->logger->debug("Wrong merchant: {$res[$startIndex]['Merchant']}");
                        //                                $this->sendNotification("refs #16337. Unknown merchant '{$merchant}' ('{$category}') was found");
                    }
                }
            }// if (isset($allTransactions[$hash]['Transaction Info']))

            // Points
            if (isset($allTransactionsInfo->transactionColumns[3]->displayValue) && $allTransactionsInfo->transactionColumns[3]->displayValue != "-----") {
                $res[$startIndex]['Points'] = $allTransactionsInfo->transactionColumns[3]->actualValue;
            } elseif (
                // refs #22118
                (stristr($account->description, 'Double Cash') || stristr($account->completeDescription, 'Double Cash'))
                && isset($allTransactionsInfo->transactionColumns[2]->displayValue) && $allTransactionsInfo->transactionColumns[2]->displayValue != "-----"
            ) {
                $multiplier = 2;
                $res[$startIndex]['Points'] = $allTransactionsInfo->transactionColumns[2]->actualValue * $multiplier;
            }

            $startIndex++;
        }// foreach ($allTransactionsList as $allTransactionsInfo)

        // Sort by date
        usort($res, function ($a, $b) {
            $key = 'Date';

            return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
        });

        $result[$code] = [
            "DisplayName" => $account->completeDescription ?? $account->description,
            "HistoryRows" => $res,
        ];

        // refs #16044
        if ($prestigeCard && isset($airTravelCreditTransactions)) {
            $this->parseTravelCredit($code, $airTravelCreditTransactions);
        }// if ($prestigeCard)

        return $result;
    }

    // refs #16044
    public function parseTravelCredit($code, $airTravelCreditTransactions)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Remaining Citi Travel Credit", ['Header' => 3]);
        $thisYear = date("Y");
        $airTravelCredit = 0;
        $this->logger->debug(var_export($airTravelCreditTransactions, true), ['pre' => true]);

        foreach ($airTravelCreditTransactions as $airTravelCreditTransaction) {
            if ($thisYear != date("Y", strtotime($airTravelCreditTransaction["Date"]))) {
                $this->logger->notice("Skip old transaction");
                $this->logger->debug(var_export($airTravelCreditTransaction, true), ['pre' => true]);

                continue;
            }// if ($thisYear != date("Y", strtotime($airTravelCreditTransaction["Date"])))
            $airTravelCreditTransactionAmount = $airTravelCreditTransaction['Amount'];

            if ($val = $this->http->FindPreg("/^-(.+)/", false, $airTravelCreditTransactionAmount)) {
                $airTravelCreditTransactionAmount = $val;
            }
            $airTravelCreditTransactionAmount = PriceHelper::cost($airTravelCreditTransactionAmount);
            $airTravelCredit += $airTravelCreditTransactionAmount;
        }// foreach ($airTravelCreditTransactions as $airTravelCreditTransaction)
        $airTravelCreditSubAccount = [
            "Code"           => str_replace('citybank', 'citybankAirTravelCredit', $code),
            "DisplayName"    => "Remaining Citi Travel Credit",
            "Balance"        => number_format(250 - $airTravelCredit, 2),
            "Currency"       => "$",
            'ExpirationDate' => mktime(0, 0, 0, 1, 1, $thisYear + 1),
        ];
        $this->AddSubAccount($airTravelCreditSubAccount, true);
    }

    public function parseSubAccHistoryByCategories($code, $startDate, $allTransactionsList, $prestigeCard, $account, $lowerBoundDate, $upperBoundDate, $JFP_TOKEN, $headers)
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        foreach ($allTransactionsList as $allTransactionsInfo) {
            $date = strtotime($this->http->FindPreg("/([^\|]+)/", false, $allTransactionsInfo->columns[0]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[0]->actualValue));
            $additionalInfo = [];

            foreach ($allTransactionsInfo->extendedDescriptions as $extendedDescription) {
                $label = trim(str_replace(":", "", $extendedDescription->label ?? $extendedDescription->displayLabel));

                if (in_array($label, ['Transaction Type', 'Category', 'Reference Number', 'Merchant Country'])) {
                    if ($label == 'Category') {
                        $label = 'Transaction Info';
                        // refs #17468
                        if (
                            stristr($extendedDescription->value[0] ?? $extendedDescription->displayValue, 'cruise')
                            && stristr($extendedDescription->value[0] ?? $extendedDescription->displayValue, 'boat')
                        ) {
                            if (isset($allTransactionsInfo->columns[0]->activityColumn[0])) {
                                $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->columns[0]->activityColumn[0]}: Cruise may be was found");
                            } else {
                                $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->transactionColumns[0]->actualValue}: Cruise may be was found");
                            }
                            $this->logger->debug(var_export($allTransactionsInfo, true), ['pre' => true]);
                        }
                    }
                    $additionalInfo[$label] = $extendedDescription->value[0] ?? $extendedDescription->displayValue;
                }
            }// foreach ($allTransactionsInfo->extendedDescriptions as $extendedDescription)
            $amount = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $allTransactionsInfo->columns[2]->dateForSorting ?? $allTransactionsInfo->transactionColumns[2]->actualValue);

            // refs #17468
            /*
            if (
                stristr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'cruise')
                && stristr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'boat')
            ) {
                if (isset($allTransactionsInfo->columns[0]->activityColumn[0])) {
                    $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->columns[0]->activityColumn[0]}: Cruise may be was found");
                } else {
                    $this->sendNotification("citybank - refs #17468. {$allTransactionsInfo->transactionColumns[0]->actualValue}: Cruise may be was found");
                }
                $this->logger->debug(var_export($allTransactionsInfo, true), ['pre' => true]);
            }
            */

            $allTransactions[Html::cleanXMLValue($date . "-" . $amount . "-" . ($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue))] = array_merge(
                [
                    'Transaction Data' => json_encode($allTransactionsInfo->extendedDescriptions),
                    'Amount'           => $amount,
                ],
                $additionalInfo
            );
            // fixed airlines matching  // #note-69
            if (
                strstr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'STOP:')
            ) {
                $airlineNumber = $this->http->FindPreg("/^[a-zA-z\-\/]+\s*(\d+)/ims", false, $allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue);
                $allTransactions[Html::cleanXMLValue($date . "-" . $amount . "-airlineNumber-" . $airlineNumber)] = array_merge(
                    [
                        'Transaction Data' => json_encode($allTransactionsInfo->extendedDescriptions),
                        'Amount'           => $amount,
                    ],
                    $additionalInfo
                );
            }// if (strstr($allTransactionsInfo->columns[1]->activityColumn[0], 'STOP:'))

            if ($prestigeCard
                && (
                    strstr($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue, 'Congrats! Citi Air Travel Credit')
                    || Html::cleanXMLValue($allTransactionsInfo->columns[1]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[1]->displayValue) == 'Travel Credit'// refs #17620
                )
            ) {
                $airTravelCreditTransactions[] = array_merge(
                    [
                        "Date"             => $this->http->FindPreg("/([^\|]+)/", false, $allTransactionsInfo->columns[0]->activityColumn[0] ?? $allTransactionsInfo->transactionColumns[0]->actualValue),
                        'Transaction Data' => json_encode($allTransactionsInfo->extendedDescriptions),
                        'Amount'           => $amount,
                    ],
                    $additionalInfo
                );
            }// if ($prestigeCard && strstr($allTransactionsInfo->columns[1]->activityColumn[0], 'Congrats! Citi Air Travel Credit'))
        }// foreach ($allTransactionsList as $allTransactionsInfo)

        // refs #16044
        if ($prestigeCard && !empty($airTravelCreditTransactions)) {
            $this->parseTravelCredit($code, $airTravelCreditTransactions);
        }// if ($prestigeCard && !empty($airTravelCreditTransactions))

        // get list of categories
        $this->browser->PostURL("https://online.citi.com/US/REST/PieChart/getCardTransactionByCat.jws?pageIndicator=AAS&instanceID={$account->accountInstanceId}&lowerBoundDate={$lowerBoundDate}&upperBoundDate={$upperBoundDate}&timePeriodIndicator=DateRange&JFP_TOKEN={$JFP_TOKEN}", [], $headers);
        $pieChart = $this->browser->JsonLog(null, 0);

        if (empty($pieChart->pieChartDetails)) {
            $this->logger->error("no categories were found");

            return $result;
        }
        $this->logger->debug("Total " . count($pieChart->pieChartDetails) . " categories were found");
        $res = [];
        $startIndex = 0;

        // https://www.citi.com/credit-cards/credit-card-details/citi.action?ID=citi-prestige-card
        if (stristr($account->completeDescription, 'prestige') || stristr($account->description, 'prestige')) {
            $rates = [
                // Air Travel
                'Air Travel'    => 5,
                // Dining
                'Restaurants'   => 5,
                // Hotel
                'Lodging'       => 3,
                // Cruise lines
                'CRUISE LINES'  => 3,
                // Entertainment
                'Entertainment' => 2, // https://information.citi.com/prestige/intro, (THROUGH 8/31/19)
            ];
        } elseif (stristr($account->completeDescription, 'premier') || stristr($account->description, 'premier')) {
            $rates = [
                // Travel including Gas Stations
                'Air Travel'       => 3,
                'Other Travel'     => 3,
                'Lodging'          => 3,
                'Vehicle Services' => 3,
                'Gas stations'     => 3, // custom category, https://redmine.awardwallet.com/issues/16337#note-79
                'Auto Rental'      => 3,
                // Dining & Entertainment
                'Restaurants'      => 2,
                'Entertainment'    => 2,
            ];
        } elseif (stristr($account->completeDescription, 'preferred') || stristr($account->description, 'preferred')) {
            $rates = [
                // Dining & Entertainment // #note-40
                'Restaurants'   => 2,
                'Entertainment' => 2,
            ];
        } else {
            $rates = [];
        }

        // #note-12
        $specialCategories = [
            'Air Travel',
            'Lodging',
            'Auto Rental',
        ];

        $allCategories = [];

        foreach ($pieChart->pieChartDetails as $pieChartDetail) {
            if (!isset($pieChartDetail->categoryLabel)) {
                $this->logger->error("no category label were found");

                continue;
            }
            $allCategories[] = $pieChartDetail->categoryLabel;
        }// foreach ($pieChart->pieChartDetails as $pieChartDetail)

        if (!in_array('Miscellaneous', $allCategories)) {
            $allCategories[] = 'Miscellaneous';
        }

        foreach ($allCategories as $category) {
            $this->logger->info("{$category}", ['Header' => 4]);
            $this->browser->PostURL("https://online.citi.com/US/REST/PieChart/getCardTransactionByCat.jws?pageIndicator=AAS&instanceID={$account->accountInstanceId}&lowerBoundDate={$lowerBoundDate}&upperBoundDate={$upperBoundDate}&category={$category}&timePeriodIndicator=DateRange&JFP_TOKEN={$JFP_TOKEN}", [], $headers);
            $transactionsList = $this->browser->JsonLog(null, 0);

            if (empty($transactionsList->postedTransactionJournals)) {
                $this->logger->notice("no transactions found");

                continue;
            }
            $transactions = $transactionsList->postedTransactionJournals;
            $this->logger->debug("Total " . count($transactions) . " transactions in category {$category} were found");

            foreach ($transactions as $transaction) {
                $dateStr = $this->http->FindPreg("/([^\|]+)/", false, $transaction->columns[0]->activityColumn[0]);
                $postDate = strtotime($dateStr);

                if (isset($startDate) && $postDate < $startDate) {
                    $this->logger->notice("break at date {$dateStr} ($postDate)");

                    continue;
                }// if (isset($startDate) && $postDate < $startDate)
                // Transaction
                $res[$startIndex]['Date'] = $postDate;
                // Merchant
                $res[$startIndex]['Merchant'] = $transaction->columns[1]->activityColumn[0];
                // Amount
                $res[$startIndex]['Amount'] = $this->http->FindPreg(self::BALANCE_REGEXP_EXTENDED, false, $transaction->columns[2]->dateForSorting);

                if ($res[$startIndex]['Amount']) {
                    $res[$startIndex]['Currency'] = 'USD';
                }
                $res[$startIndex]['Category'] = $category;

                $hash = Html::cleanXMLValue($postDate . "-" . $res[$startIndex]['Amount'] . "-" . $res[$startIndex]['Merchant']);
                // fixed airlines matching  // #note-69
                if (!isset($allTransactions[$hash]) && $category == 'Air Travel') {
                    $airlineNumber = $this->http->FindPreg("/^[a-zA-z\-\/]+\s*(\d+)/ims", false, $res[$startIndex]['Merchant']);
                    $hash = Html::cleanXMLValue($postDate . "-" . $res[$startIndex]['Amount'] . "-airlineNumber-" . $airlineNumber);
                }// if (!isset($allTransactions[$hash]) && $category == 'Air Travel')

                if (isset($allTransactions[$hash])) {
                    if (isset($allTransactions[$hash]['Transaction Info'])) {
                        $merchant = $this->http->FindPreg("/^[^\-]+\s*\-\s*(.+)/", false, $allTransactions[$hash]['Transaction Info']);

                        // https://redmine.awardwallet.com/issues/16337#note-79
                        if ($res[$startIndex]['Category'] == 'Vehicle Services' && stristr($allTransactions[$hash]['Transaction Info'], 'GAS ')) {
                            $res[$startIndex]['Category'] = 'Gas stations';
                        }

                        unset($allTransactions[$hash]['Transaction Info']);

                        if ($merchant && in_array($category, $specialCategories)) {
                            $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                            $res[$startIndex]['Merchant'] = $merchant;
                        }// if ($merchant)
                        elseif ($merchant) {
                            $res[$startIndex]['Transaction Info'] = $merchant;
                        }
                    }// if (isset($allTransactions[$hash]['Transaction Info']))
                    $res[$startIndex] = array_merge($res[$startIndex], $allTransactions[$hash]);
                }// if (isset($allTransactions[$hash]))

                // https://redmine.awardwallet.com/issues/16337#note-82
                if (strstr($res[$startIndex]['Merchant'], 'STOP:')
                    || strstr($res[$startIndex]['Merchant'], 'FOLIO NUMBER:')
                    || strstr($res[$startIndex]['Merchant'], 'AGREEMENT NUMBER:')
                ) {
                    $replaceMerchant = false;
                    $merchant = null;

                    if ($category == 'Air Travel') {
                        $vocabulary = [
                            "AGENT FE"       => "AGENT FEE",
                            "AEGEAN"         => "AEGEAN WEB KIFISIA GRC",
                            "AERLING"        => "AER LINGUS",
                            "AEROMEXI"       => "AEROMEXICO",
                            "AIR BERL"       => "AIR BERLIN",
                            "AIRBERLI"       => "AIR BERLIN",
                            "AIR CAN"        => "AIR CANADA",
                            "AIR CHIN"       => "AIR CHINA",
                            "AIR EURO"       => "AIR EUROPA LINEAS",
                            "AIR FRAN"       => "AIR FRANCE",
                            "AIR-INDI"       => "AIR-INDIA",
                            "AIR NZ"         => "AIR NEW ZEALAND",
                            "ALASKA A"       => "ALASKA AIRLINES",
                            "ALASKA"         => "ALASKA AIRLINES",
                            "ALG"            => "AIRLINE,AIR CARRIER",
                            "ANAAIR"         => "ASIANA AIRLINES",
                            "AMERICAN"       => "AMERICAN AIRLINES",
                            "AMERICAN AIR"   => "AMERICAN AIRLINES",
                            "AUSTRIAN"       => "AUSTRIAN AIRLINES",
                            "AVIANCA"        => "AVIANCA",
                            "AZERBAIJ"       => "AZERBAIJAN AIRLINES",
                            "BRUSSELS"       => "BRUSSELS AIRLINES",
                            "BRITISH"        => "BRITISH AIRWAYS",
                            "BOLIVIAN"       => "BOLIVIANA DE AVIACION",
                            "CATHAYPA"       => "CATHAY PACIFIC",
                            "CATHAY P"       => "CATHAY PACIFIC",
                            "CHINAAIR"       => "CHINA AIRLINES",
                            "CHINA AIRLINES" => "CHINA AIRLINES",
                            "CHINA SO"       => "CHINA SOUTHERN AIRLINES",
                            "CHINA SOUTH"    => "CHINA SOUTHERN AIRLINES",
                            "CHINAEAS"       => "CHINAEAST AIR",
                            "COBALT A"       => "COBALT AIR",
                            "CROATIA"        => "CROATIA AIRLINES",
                            "DELTA"          => "DELTA",
                            "DELTA AIR"      => "DELTA",
                            "ETHIOPIA"       => "ETHIOPIAN AIRLINES",
                            "ETIHADAI"       => "ETIHADAIR",
                            "EMIRATES"       => "EMIRATES AIRLINES",
                            "EASYJET"        => "EASYJET AIR",
                            "EVA AIR"        => "EVA AIRWAYS CORPORATION",
                            "GARUDA"         => "GARUDA (INDONESIA)",
                            "HAWAIIAN"       => "HAWAIIAN AIR",
                            "HAHN"           => "HAHN AIR",
                            "JAL"            => "JAPAN AIRLINES",
                            "JETBLUE"        => "JETBLUE",
                            "IBERIA"         => "IBERIA",
                            "ICELANDA"       => "ICELANDAIR",
                            "ISLAND A"       => "ISLAND AIR",
                            "IslandAi"       => "ISLAND AIR",
                            "LOT"            => "LOT-POLAND",
                            "LATAM AI"       => "LATAM AIRLINES",
                            "LAN AIR"        => "LAN AIRLINES-LAN AIR",
                            "LUFTHANS"       => "LUFTHANSA",
                            "KOREAN"         => "KOREAN AIRLINES",
                            "MALAY AI"       => "MALAYSAIN AIR SYS",
                            "MIDEASTA"       => "MIDDLE EAST AIR",
                            "NORWEGIA"       => "NORWEGIAN AIR SHUTTLE",
                            "PAKISTAN"       => "PAKISTAN INTERNATIONAL AIRLINES",
                            "PORTER A"       => "PORTER AIRLINES",
                            "ROYAL JO"       => "ROYAL JORDANIAN AIRLINES",
                            "ROYAL BR"       => "ROYAL BRUNEI AIRLINES",
                            "RYANAIR"        => "RYANAIR",
                            "SOUTHWES"       => "SOUTHWEST",
                            "SATA INT"       => "SATA INTERNATIONAL",
                            "SCOOT PT"       => "SCOOT",
                            "SINGAPOR"       => "SINGAPORE AIRLINES",
                            "SINGAPORTIX"    => "SINGAPORE AIRLINES",
                            "SICHUAN"        => "SICHUAN AIRLINES",
                            "SILVER A"       => "SINGAPORE AIR",
                            "SNBRU AI"       => "SNBRU AIR",
                            "SPIRIT A"       => "SPIRIT AIRLINES",
                            "SriLanka"       => "SRILANKAN AIRLINES",
                            "SKY AIRL"       => "SKY AIRLINE",
                            "SUNCNTRY"       => "SUN COUNTRY AIRLINES - SUNCNTRY",
                            "SUNCTRYAIR"     => "SUN COUNTRY AIRLINES - SUNCNTRY",
                            "SWISS IN"       => "SWISSINTAIR",
                            "TAP"            => "TAP (PORTUGAL)",
                            "TAP PORT"       => "TAP (PORTUGAL)",
                            "THY"            => "THY (TURKEY)",
                            "TransaviHDM"    => "TRANSAVIA",
                            "QATAR"          => "QATAR AIRWAYS",
                            "QATAR AI"       => "QATAR AIRWAYS",
                            "UNITED AIR"     => "UNITED AIRLINES",
                            "UNITED"         => "UNITED AIRLINES",
                            "VARIG"          => "VARIG (BRAZIL)",
                            "VIR AMER"       => "VIR AMER",
                            "VIRGIN A"       => "VIRGIN ATLANTIC",
                            "VIR ATL"        => "VIRGIN ATLANTIC",
                            "VIVAAERO"       => "VIVA AEROBUS",
                            "WESTJET"        => "WESTJET AIRLINES",
                            "XL AIRWA"       => "XL AIRWAY WASHINGTON DC DC",
                            "WWW.AUST"       => "AUSTRIAN AIRLINES",
                            "WWW"            => "AIRLINE,AIR CARRIER",
                            "HONG KON"       => "AIRLINE,AIR CARRIER",
                            "NAME"           => "AIRLINE,AIR CARRIER",
                            "VAA MOBI"       => "AIRLINE,AIR CARRIER",
                        ];
                        $merchant = $this->http->FindPreg("/^\s*([a-z\s]+)/ims", false, $res[$startIndex]['Merchant']);

                        if (isset($vocabulary[$merchant]) && !in_array($merchant, ['JET'])) {
                            $replaceMerchant = true;
                            $merchant = $vocabulary[$merchant];
                        }// if (isset($vocabulary[$merchant]))
                    }// if ($category == 'Air Travel')

                    $vocabulary = [];

                    if ($category == 'Air Travel' && (empty($merchant) || !$replaceMerchant)) {
                        $vocabulary = [
                            "ADRIA "      => "ADRIA AIRWAYS",
                            "ASIANA"      => "ASIANA AIRLINES",
                            "A"           => "AEGEAN WEB KIFISIA GRC",
                            "AEGEAN"      => "AEGEAN WEB KIFISIA GRC",
                            "BOLIVIANA"   => "BOLIVIANA DE AVIACION",
                            "easyJet"     => "EASYJET AIR",
                            'EUROWING'    => 'EUROWINGS',
                            'Flybe'       => 'FLYBE',
                            'FlyUIA'      => 'FLYBE',
                            'FRONTIER'    => 'FRONTIER AIRLINES',
                            "GARUDA"      => "GARUDA (INDONESIA)",
                            "IslandAi"    => "ISLAND AIRLINES",
                            "JETAIRWAY "  => "JET AIRWAYS - JET AIR",
                            "JET AIRWAY"  => "JET AIRWAYS - JET AIR",
                            "JETSTAR"     => "JETSTAR AIR",
                            "JET2.COM"    => "JET2.COM",
                            "KLM"         => "KLM",
                            "KULULA"      => "AIRLINE,AIR CARRIER",
                            "norwegia"    => "NORWEGIAN AIR SHUTTLE",
                            "PAWA WEB"    => "AIRLINE,AIR CARRIER",
                            "PAWA ARC"    => "AIRLINE,AIR CARRIER",
                            'RYANAIR'     => 'RYANAIR',
                            'ROMA ITA'    => 'ALITALIA',
                            'S7 Air'      => 'S7 AIRLINES',
                            'Transavi'    => 'TRANSAVIA',
                            'QANTAS'      => 'QANTAS',
                            'Vueling'     => 'VUELING AIRLINES',
                            'VOLARIS'     => 'VOLARIS',
                            'WOW AIR'     => 'WOW AIR Reykjavík',
                            'Ukrain'      => 'UKRAINE INTERNATIONAL',
                            'WIZZ AIR'    => 'WIZZ AIR',
                            'XIAMEN '     => 'XIAMEN AIRLINES',
                        ];
                    }// if ($category == 'Air Travel' && (empty($merchant) || !$replaceMerchant))
                    elseif ($category == 'Lodging') {
                        $vocabulary = [
                            'HYATT PLACE'       => 'HYATT PLACE',
                        ];
                    }// if ($category == 'Lodging')

                    foreach ($vocabulary as $keyWord => $val) {
                        $this->logger->debug("Word: {$keyWord}");

                        if (stristr(Html::cleanXMLValue($res[$startIndex]['Merchant']), $keyWord)) {
                            $replaceMerchant = true;
                            $merchant = $val;

                            break;
                        }// if (stristr($res[$startIndex]['Merchant'], $keyWord))
                    }// foreach ($vocabulary as $word)
                    $this->logger->debug("Right merchant => '{$merchant}'");

                    if ($replaceMerchant) {
                        $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                        $res[$startIndex]['Merchant'] = $merchant;
                    } else {
                        if ($category == 'Air Travel' && !empty($merchant)) {
                            $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                            $res[$startIndex]['Merchant'] = $merchant;
                        } elseif (in_array($category, ['Lodging', 'Auto Rental']) && strstr($res[$startIndex]['Merchant'], 'PHONE NUMBER:')) {
                            $res[$startIndex]['Transaction Info'] = $res[$startIndex]['Merchant'];
                            $res[$startIndex]['Merchant'] = $this->http->FindPreg("/(.+)PHONE NUMBER:/ms", false, $res[$startIndex]['Merchant']);
                        }

                        // https://redmine.awardwallet.com/issues/16337#note-84
                        if ($category == 'Air Travel' || (in_array($category, ['Lodging', 'Auto Rental']) && empty($res[$startIndex]['Merchant']))) {
                            $this->logger->debug("Wrong merchant: {$res[$startIndex]['Merchant']}");
//                                $this->sendNotification("refs #16337. Unknown merchant '{$merchant}' ('{$category}') was found");
                        }
                    }
                }

                if (isset($res[$startIndex]['Category'])) {
                    if ($res[$startIndex]['Category'] == 'Miscellaneous') {
                        $res[$startIndex]['Points'] = 0;
                    } elseif ($res[$startIndex]['Amount']) {
                        $amount = $res[$startIndex]['Amount'];
                        $sign = 1;

                        if ($val = $this->http->FindPreg("/^-(.+)/", false, $res[$startIndex]['Amount'])) {
                            $sign = -1;
                            $amount = $val;
                        }
                        $amount = PriceHelper::cost($amount);
                        $amount *= $sign;

                        foreach ($rates as $key => $rate) {
                            if (stristr($res[$startIndex]['Category'], $key)) {
                                // PriceHelper::cost: 1,333.44 -> 1333.44
                                $res[$startIndex]['Points'] = round($amount * $rate);

                                break;
                            }// if (stristr($res[$startIndex]['Category'], $key))
                        }// foreach ($rates as $key => $rate)

                        if (!isset($res[$startIndex]['Points'])) {
                            $res[$startIndex]['Points'] = round($amount);
                        }
                    }// if ($res[$startIndex]['Amount'])
                }// if (isset($res[$startIndex]['Category']))

                $startIndex++;
            }// foreach ($transactions as $transaction)

            // Sort by date
            usort($res, function ($a, $b) {
                $key = 'Date';

                return $a[$key] == $b[$key] ? 0 : ($a[$key] < $b[$key] ? 1 : -1);
            });

            $result[$code] = [
                "DisplayName" => $account->completeDescription ?? $account->description,
                "HistoryRows" => $res,
            ];
        }

        return $result;
    }

    // change also src/engine/citybank/functions.php
    public function GetHistoryColumns()
    {
        return [
            "Date"             => "PostingDate",
            "Merchant"         => "Description",
            "Transaction Info" => "Info",
            "Transaction Data" => "Info",
            "Points"           => "Miles",
            "Amount"           => "Amount",
            "Currency"         => "Currency",
            "Transaction Type" => "Info",
            "Category"         => "Category",
            "Reference Number" => "Info",
            "Merchant Country" => "Info",
        ];
    }

    public function GetHiddenHistoryColumns()
    {
        return [
            'Transaction Data',
            'Reference Number',
            "Merchant Country",
            "Transaction Type",
        ];
    }

    // refs #11308
    public function parseFICO()
    {
        $this->logger->notice(__METHOD__);
        $ficoScoreLink = $this->waitForElement(WebDriverBy::id('ficoScoreLink'), 0);

        if (!$ficoScoreLink) {
            $ficoScoreLink = $this->waitForElement(WebDriverBy::id('cmlink_BBDashboardFicoPage'), 0);
        }

        $ficoScoreLinkXpath = '
            //a[contains(text(), "Your FICO")]
            | //a[contains(text(), "Learn About Your Score")]
            | //a[contains(@class, "fico-link")]
            | //div[@id = "viewFicoScore"]/a
        ';

        if (!$ficoScoreLink) {
            $ficoScoreLink = $this->waitForElement(WebDriverBy::xpath($ficoScoreLinkXpath), 0);
        }

        if (!$ficoScoreLink) {
            $ficoScoreLink = $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'Your FICO')]"), 0, false);
        }

        try {
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("NoSuchDriverException: " . $e->getMessage());
        }

        if ($ficoScoreLink) {
            $this->logger->info('FICO® Score', ['Header' => 3]);

            try {
                // prevent traces if element not visible
                if (
                    $this->waitForElement(WebDriverBy::xpath($ficoScoreLinkXpath), 0)
                    || $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Your FICO')]"), 0, false)
                ) {
                    $this->increaseTimeLimit();
                    $this->logger->debug("document.querySelector(\"a[href *= '=fico']\").removeAttribute('target');");
                    $this->logger->debug("document.querySelector(\"a[href *= '=fico']\").click();");
                    $this->driver->executeScript("var tutorialOverlay = $('#tutorialOverlay-model'); if (tutorialOverlay.length) tutorialOverlay.parent().remove();");
//                    $ficoScoreLink->click();
                    $this->driver->executeScript("
                        let ficoLink = $('#viewFicoScore > a');
                        if (ficoLink.length) {
                            ficoLink.get(0).click();
                        }
                        else {
                            document.querySelector(\"a[href *= '=fico']\").removeAttribute('target');
                            document.querySelector(\"a[href *= '=fico']\").click();
                        }
                    ");
                } else {
                    $id = $ficoScoreLink->getAttribute('id');
                    $this->logger->debug("FICO id: {$id}");
                    $this->driver->executeScript("$('#{$id}').get(0).click();");
                }
            } catch (ScriptTimeoutException | TimeOutException $e) {
                $this->logger->error("TimeoutException exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
                $this->saveResponse();
                // retries
                if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                    throw new CheckRetryNeededException(3, 7);
                }
            } catch (UnknownServerException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                sleep(3);
                $this->saveResponse();
            }

            // Your FICO® Score is ...
            $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Check your FICO')]/a[contains(., 'here')] | //h3[contains(text(), 'Your FICO')]/strong"), 10);
            $this->increaseTimeLimit(120);
            $this->saveResponse();

            $here = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Check your FICO')]/a[contains(., 'here')]"), 0);
            $closeModal = $this->waitForElement(WebDriverBy::xpath('//a[@aria-label="No thanks" and data-dismiss="modal"]'), 0);

            if ($closeModal) {
                $closeModal->click();
                $this->saveResponse();
            }

            if ($here) {
                try {
                    $this->driver->executeScript("$('div.modal:visible, div.modal-backdrop').remove()");
                    $this->driver->executeScript("$('a:contains(\"here\")').get(0).click();");
                } catch (Facebook\WebDriver\Exception\TimeoutException | TimeoutException $e) {
                    $this->logger->error("TimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->driver->executeScript('window.stop();');
                    $this->saveResponse();
                } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
                    $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->saveResponse();

                    $here->click();
                }
            }

            $this->increaseTimeLimit();

            $fcioScore = $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Your FICO')]/strong"), 10);
            $this->saveResponse();

            if ($fcioScore) {
                $fcioScore = $fcioScore->getText();
            } else {
                $fcioScore = $this->http->FindSingleNode("//input[@id = 'fico-score-value']/@value");
            }

            if ($fcioScore) {
                // FICO Score updated on
                $fcioUpdatedOn = $this->waitForElement(WebDriverBy::xpath("//span[contains(@class, 'score-date-info')] | //p[@id = 'as-of-date']"), 0);

                if ($fcioUpdatedOn) {
                    $fcioUpdatedOn = $this->http->FindPreg('/as\s*of\s*([^<*]+)/i', false, $fcioUpdatedOn->getText());
                }
                // refs #14491
                if ($fcioScore && $fcioUpdatedOn) {
                    /*
                    if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
                        foreach ($this->Properties['SubAccounts'][0] as $key => $value) {
                            if (in_array($key, ['Code', 'DisplayName']))
                                continue;
                            elseif ($key == 'Balance')
                                $this->SetBalance($value);
                            elseif ($key == 'ExpirationDate')
                                $this->SetExpirationDate($value);
                            else
                                $this->SetProperty($key, $value);
                        }// foreach ($this->Properties['SubAccounts'][0] as $key => $value)
                        unset($this->Properties['SubAccounts']);
                    }// if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1)
                    */
                    $this->SetProperty("CombineSubAccounts", false);
                    $this->AddSubAccount([
                        "Code"               => "citybankFICO",
                        "DisplayName"        => "FICO® Bankcard Score 8 (Equifax)",
                        "Balance"            => $fcioScore,
                        "FICOScoreUpdatedOn" => $fcioUpdatedOn,
                    ]);
                }// if ($fcioScore && $fcioUpdatedOn)
            }// if ($fcioScore)

            return true;
        }// if ($ficoScoreLink)

        return false;
    }

    public function closePopup()
    {
        $this->logger->notice(__METHOD__);

        $closeBtn = $this->waitForElement(WebDriverBy::xpath('//button[(
                    @aria-label = "Close Account Info Modal Box"
                    or @aria-label = "Close Modal Box"
                    or @aria-label = "Close modal"
                    or @aria-label = "Close"
                )
                and normalize-space(text()) = "×"
            ]
            | //*[@id="ivr-modal"]//button[@aria-label = "Close modal" and normalize-space(text()) = "×"]
            | //button[span[normalize-space(text()) = "Close"]]
        '), 0);

        try {
            $this->saveResponse();
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new CheckRetryNeededException(3, 0);
        }

        if ($closeBtn) {
            try {
                $closeBtn->click();
            } catch (
                UnknownServerException
                | UnknownServerException
                | Facebook\WebDriver\Exception\ElementNotInteractableException
                $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                try {
                    $this->logger->error("try js: " . $e->getMessage());
                    $this->driver->executeScript("var closeBtn = document.querySelector('button.closeButton'); if (closeBtn) closeBtn.click();");
                    $this->logger->error("try js 2");
                    $this->driver->executeScript("
                        var close = $('button[aria-label *= \"Close\"]');
                        try {
                            if (close)
                                close.get(0).click();
                        } catch (e) {}
                    ");
                    $this->logger->error("try js 3");
                    $this->driver->executeScript("var closeBtn = $('button[class *= \"btn btn-link default\"]:contains(\"Close\")'); if (closeBtn) closeBtn.get(0).click();");
                } catch (
                    UnknownServerException
                    | UnexpectedJavascriptException
                    | Facebook\WebDriver\Exception\JavascriptErrorException
                    $e
                ) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $this->DebugInfo = "Js Exception";
                }
            }
        } else {
            try {
                $this->logger->debug("try js");
                $this->driver->executeScript("var closeBtn = document.querySelector('button.closeButton'); if (closeBtn) closeBtn.click();");
                $this->logger->debug("try js 2");
                $this->driver->executeScript("
                    var close = $('button[aria-label *= \"Close\"]');
                    try {
                        if (close)
                            close.get(0).click();
                    } catch (e) {}
                ");
                $this->logger->debug("try js 3");
                $this->driver->executeScript("
                    try {
                        var closeBtn = $('button[class *= \"btn btn-link default\"]:contains(\"Close\")');
                        if (closeBtn)
                            closeBtn.get(0).click();
                    } catch (e) {}
                ");
                $this->logger->debug("try js 4");
                $this->driver->executeScript("
                try {
                    document.querySelector('div.modal-container').style.display = 'none';
                } catch (e) {}
                ");
            } catch (
                UnknownServerException
                | UnexpectedJavascriptException
                | Facebook\WebDriver\Exception\JavascriptErrorException
                $e
            ) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                $this->DebugInfo = "Js Exception";
            } catch (NoSuchDriverException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 0);
            }
        }
    }

    /* ---------------------------- Australia ------------------------------ */

    public function LoadLoginFormAustralia()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.citibank.com.au/AUGCB/JSO/signon/DisplayUsernameSignon.do");

        return true;
    }

    public function LoadLoginFormSingapore()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.citibank.com.sg/SGGCB/JSO/signon/DisplayUsernameSignon.do");

        return true;
    }

    public function LoadLoginFormHongKong()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.citibank.com.hk/HKGCB/JSO/signon/DisplayUsernameSignon.do");

        return true;
    }

    public function LoginFromSingapore()
    {
        $this->logger->notice(__METHOD__);

        return $this->LoginFromAustralia();
    }

    public function LoginFromHongKong()
    {
        $this->logger->notice(__METHOD__);

        return $this->LoginFromAustralia();
    }

    public function LoginFromAustralia()
    {
        $this->logger->notice(__METHOD__);
        $this->waitForElement(WebDriverBy::xpath('//input[@name = "username" or @aria-placeholder="User ID"] | //p[contains(text(), "We are undergoing maintenance on")]'), 30);
        $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username" or @aria-placeholder="User ID"]'), 0);
        $this->saveResponse();

        if (empty($loginField)) {
            if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are undergoing maintenance on")]')) {
                $this->CheckError($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $currentUrl = $this->http->currentUrl();

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 1000);
        $mover->steps = rand(10, 20);

        $mover->moveToElement($loginField);
        $mover->click();

        try {
            $mover->sendKeys($loginField, $this->AccountFields['Login'], 6);
        } catch (Facebook\WebDriver\Exception\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            if (strstr($this->http->currentUrl(), 'https://www1.citibank.com.au/nab')) {
                $this->http->GetURL('https://www.citibank.com.au/AUGCB/JSO/signon/DisplayUsernameSignon.do');
                $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username" or @aria-placeholder="User ID"]'), 10);
            }
            $this->saveResponse();

            // provider bug fix
            if (!$loginField && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Sorry! We cannot find the page you are looking for")]'), 0)) {
                $this->saveResponse();
                $this->http->GetURL($currentUrl);
                $loginField = $this->waitForElement(WebDriverBy::xpath('//input[@name = "username" or @aria-placeholder="User ID"]'), 20);
            }

            $mover->sendKeys($loginField, $this->AccountFields['Login'], 6);
        }
//        $loginField->sendKeys($this->AccountFields['Login']);

//        $this->driver->executeScript("$('#cA-cardsUseridMasked').trigger('blur')");
        $passField = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password' or @aria-placeholder = 'Password']"), 15);

        if (empty($passField)) {
            return false;
        }

        $mover->moveToElement($passField);
        $mover->click();
        $mover->sendKeys($passField, $this->AccountFields['Pass'], 6);
//        $passField->sendKeys($this->AccountFields['Pass']);

        $signOn = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'link_lkSignOn' or @id = 'signon-button'] | //button[@aria-label = 'Sign On']"), 0);
        $this->saveResponse();

        if (!$signOn) {
            return false;
        }

        $mover->moveToElement($signOn);
        $mover->click();
//        $signOn->click();

        /*
         * Make sure the User ID that you select is the one you want to use from now on.
         * NOTE: If you use My Accounts Aggregation or Inter Institution Transfer, be sure to choose the ID that you use to access these features.
         */
        $contBtn = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'link_lkRegUnameListCont']"), 5);

        if (
            $contBtn
            && $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Make sure the User ID that you select is the one you want to use from now on.')]"), 0)
        ) {
            $contBtn->click();
            $loginField = $this->waitForElement(WebDriverBy::id('username'), 10);
            $this->saveResponse();

            if (empty($loginField)) {
                return false;
            }
            $loginField->clear();
            $loginField->sendKeys($this->AccountFields['Login']);
            $passField = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'password']"), 0);

            if (empty($passField)) {
                return false;
            }
            $passField->clear();
            $passField->sendKeys($this->AccountFields['Pass']);

            $this->driver->findElement(WebDriverBy::xpath("//a[@id = 'link_lkRegUnameListCont']"))->click();
        }

        $logout = $this->waitForElement(WebDriverBy::xpath("
            //a[contains(text(), 'Sign Off')]
            | //a[contains(text(), 'Logout')]
            | //a[@id = 'link_lkcnbsignoff']
            | //div[@aria-label = 'Log out']
            | //div[contains(text(), 'Get started')]
            | //li[a[contains(., 'View Rewards Point Balance')]]
        "), 10);
        $this->saveResponse();

        if ($logout) {
            return true;
        }

        if ($question = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Enter the OTP generated on your Citi Mobile')]"), 0)) {
            $this->holdSession();
            $this->AskQuestion($question->getText(), null, "OneTimePin");

            return false;
        }

        $message = $this->http->FindSingleNode("//div[@class = 'appMMWon']");
        // A session is already active for this s userID.
        if (strstr($message, 'A session is already active for this s userID')
            // Please try to login again in 10 minutes, session has not closed
            || (strstr($message, 'Sorry, your login attempt has failed due to enhanced security measures.')
                && strstr($message, 'Please try to login again in 10 minutes'))) {
            $this->CheckError($message, ACCOUNT_PROVIDER_ERROR);
        } else {
            $this->CheckError($message);
        }
        // I'm sorry, your sign on attempt has failed.
        if ($message = $this->http->FindPreg('/(?:I\'m sorry, your sign on attempt has failed\.|I\'m sorry. Your User ID or password may be incorrect\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your sign on attempt has failed. Your User ID or Password may be incorrect, please try again.
        if ($message = $this->http->FindPreg('/(?:Your sign on attempt has failed\. Your User ID or Password may be incorrect, please try again\.|Your sign on attempt was unsuccessful\.\s*The User ID or Password you have entered may be incorrect\.\s*Please try again\.|Your User ID or password may be incorrect\. Please try again\.|Your sign on attempt has failed\. Please try again\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Password must be at least 8 characters
        // I'm sorry. I don't recognize the information you entered.
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //div[@id = "ui-tooltip-csValidationError-content" and contains(text(), "Password must be at least 8 characters")]
                | //div[@id = "ui-tooltip-csValidationError-content" and contains(text(), "The User ID is invalid.")]
                | //div[@id = "ui-tooltip-csValidationError-content" and contains(text(), "User ID may not be the same as password.")]
                | //div[@id = "ui-tooltip-csValidationError-content" and contains(text(), "Please enter password to proceed")]
                | //div[@id = "ui-tooltip-csValidationError-content" and contains(text(), "Your User ID must be greater than 5 alphanumeric character")]
                | //span[contains(text(), "I\'m sorry. I don\'t recognize the information you entered.")]
            '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }
        // Something has gone wrong at our end.
        // I'm sorry, we are having temporary delay. Please try again later.
        // I'm sorry, we can not process your request at the moment.
        if ($message = $this->waitForElement(WebDriverBy::xpath('
                //h3[contains(text(), "Something has gone wrong at our end.")]
                | //div[contains(@class, "cS-subAppContainer") and contains(text(), "I\'m sorry, we are having temporary delay. Please try again later.")]
                | //td[contains(@class, "apptxtlg") and contains(text(), "I\'m sorry, we are having temporary delay. Please try again later.")]
                | //div[@id = "jba-eot-messages" and contains(text(), "I\'m sorry, we can not process your request at the moment.")]
                | //div[contains(@class, "cS-subAppContainer") and contains(normalize-space(text()), "System currently unavailable. Please try again later.")]
                | //strong[contains(normalize-space(text()), "We are unable to process this request now. Please try again later. If the problem persists, please call Citi-Phone Hotline.")]
                | //td[contains(., "Sorry this information is not available at this time.")]
                | //div[contains(text(), "Activate your Physical Card in MBOL and try logging in CBOL again")]
        '), 0)
        ) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }
        // empty error
        if (
            $this->AccountFields['Login2'] == 'Singapore'
            && $this->AccountFields['Login'] == 'malissa89'
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath('//div[@id = "ui-tooltip-csValidationError-content"]'), 0)) {
            $this->DebugInfo = $message->getText();
        }

        $this->saveResponse();

        return false;
    }

    public function ParseFormSingapore()
    {
        $this->logger->notice(__METHOD__);
        $this->ParseFormAustralia('Singapore');
    }

    public function ParseFormHongKong()
    {
        $this->logger->notice(__METHOD__);
        $this->ParseFormAustralia('HongKong');
    }

    public function ParseFormAustralia($region = 'Australia')
    {
        $this->logger->notice(__METHOD__);
        // offer
        $offer = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'ReminderBtn' or @id = 'NotInterested']"), 5);

        if ($offer) {
            $this->logger->notice("Skip offer");
            $offer->click();
        }

        // ff
//        $rewards = $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'View Reward Points Balance')]"), 10);
        // chrome
        $rewards = $this->waitForElement(WebDriverBy::xpath("
            //li[a[contains(., 'View Reward Points Balance')]]
            | //li[a[contains(., 'View / Redeem Rewards')]]
            | //li[a[contains(., 'Review rewards balance now')]]
            | //li[a[contains(., 'Review Your Reward Balance')]]
            | //li[a[contains(., 'View Rewards Balance & Redeem')]]
            | //li[a[contains(., 'View Rewards Point Balance')]]
        "), 10);
        // Name
        $this->saveResponse();
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//div[@id = 'welcome_msg'] | //div[contains(@aria-label, 'Welcome to Citibank® Online!')]", null, true, "/(?:Welcome to Citibank Online|Happy\s*Birthday\s*!|Welcome to Citi Online|Welcome to Citibank® Online)\s*\!?\s*([^<]+)/")));
        // Open rewards popup
        if ($rewards) {
            $this->logger->notice("Open rewards popup");
            $rewards->click();

            // AccountID: 4454752
            if ($region == 'HongKong') {
                $this->waitForElement(WebDriverBy::xpath("//div[@class = 'cA-rewHom-displaySummaryCard'] | //div[contains(text(), 'Your selected card is not eligible for this function, please select again.')]"), 40);
                $this->saveResponse();

                if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Your selected card is not eligible for this function, please select again.')]"), 0)) {
                    $select = $this->waitForElement(WebDriverBy::id("activeIndex-button"), 0);

                    if (!$select) {
                        return;
                    }
                    $select->click();
                    $option = $this->waitForElement(WebDriverBy::id("activeIndex-menu-option-1"), 5);

                    if (!$option) {
                        return;
                    }
                    $option->click();
                }
            }

            $this->waitForElement(WebDriverBy::xpath("
                //div[@class = 'cA-rewHom-displaySummaryCard']
                | //div[contains(text(), 'We are unable to process your request at the moment.')]
                | //div[contains(text(), 'To access Cards Services, you need to have an eligible product.')]
                | //div[contains(normalize-space(text()), 'Your card is not eligible for this service.')]
                | //div[contains(text(), 'We apologise, an error has occurred whilst processing your request.')]
                | //div[contains(text(), 'The card you have selected is currently not eligible for this function.')]
            "), 60);
        }

        $this->saveResponse();
        $cards = $this->http->XPath->query("//div[@class = 'cA-rewHom-displaySummaryCard']");
        $this->logger->debug("Total {$cards->length} cards were found");
        $detectedCards = $subAccounts = [];

        for ($i = 0; $i < $cards->length; $i++) {
            $card = $cards->item($i);
            $code = $this->http->FindSingleNode("div[1]", $card, true, "/XXXXXXXXXXXX(\d{4})/ims");
            $displayName = $this->http->FindSingleNode("div[1]", $card);
            $balance = $this->http->FindSingleNode("div[2]", $card);

            if (!empty($displayName) && !empty($code)) {
                if (isset($balance)) {
                    $cardDescription = C_CARD_DESC_ACTIVE;
                } else {
                    $cardDescription = C_CARD_DESC_DO_NOT_EARN;
                }
                $detectedCards[] = [
                    "Code"            => 'citybank' . $region . $code,
                    "DisplayName"     => $displayName,
                    "CardDescription" => $cardDescription,
                ];
                $subAccount = [
                    'Code'        => 'citybank' . $region . $code,
                    'DisplayName' => $displayName,
                    'Balance'     => $balance,
                    "Number"      => $code,
                ];
                $subAccounts[] = $subAccount;
            }// if (!empty($displayName) && !empty($code))
        }// for ($i = 0; $i < $cards->length; $i++)
        // detected cards
        if (!empty($detectedCards)) {
            $this->SetProperty("DetectedCards", $detectedCards);
        }

        if (!empty($subAccounts)) {
            // Set Sub Accounts
            $this->logger->debug("Total subAccounts: " . count($subAccounts));
            // SetBalance n\a
            $this->SetBalanceNA();
            // Set SubAccounts Properties
            $this->SetProperty("SubAccounts", $subAccounts);
        }// if(isset($subAccounts))
        /*
         * Credit Card Rewards
         *
         * We are unable to process your request at the moment.
         * If you continue to encounter this problem,
         * please call our 24-Hour Citiphone Banking at 13 24 84 or at +61 2 8225 0615
         * if you are calling from overseas.
         */
        elseif (($this->http->FindSingleNode("//div[contains(text(), 'We are unable to process your request at the moment.')]")
                /*
                 * Credit Card Rewards
                 *
                 * To access Cards Services, you need to have an eligible product.
                 */
                || $this->http->FindSingleNode("//div[contains(text(), 'To access Cards Services, you need to have an eligible product.')]")
                /*
                 * Reward Catalogue
                 *
                 * Your card is not eligible for this service. For enquiries, please contact our 24-hour CitiPhone Banking at (852) 2860 0333.
                 */
                || $this->http->FindSingleNode("//div[contains(normalize-space(text()), 'Your card is not eligible for this service.')]")
                /*
                 * Credit Card Rewards
                 *
                 * We apologise, an error has occurred whilst processing your request. For assistance, please contact us on 1800 801 732.
                 */
                || $this->http->FindSingleNode("//div[contains(text(), 'We apologise, an error has occurred whilst processing your request.')]")
                /*
                 * Credit ThankYou Rewards
                 *
                 *  The card you have selected is currently not eligible for this function. For further details on your card benefits, please call our 24-Hour Citiphone for assistance.
                */
                || $this->http->FindSingleNode("//div[contains(text(), 'The card you have selected is currently not eligible for this function.')]")
            )
            && !empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        // click "Sign Out"
        $this->logger->debug('click "Sign Out"');

        if ($region == 'Singapore') {
            $this->http->GetURL("https://www.citibank.com.sg/SGGCB/JSO/signoff/SummaryRecord.do?logOff=true");
        } elseif ($region == 'HongKong') {
            $this->http->GetURL("https://www.citibank.com.hk/HKGCB/JSO/signoff/SummaryRecord.do?logOff=true");
//            $this->http->GetURL("https://www.citibank.com.hk/HKGCB/JSO/signoff/flow.action?logOff=true");
        } else {
            $this->http->GetURL("https://www.citibank.com.au/AUGCB/JSO/signoff/SummaryRecord.do?logOff=true");
        }
    }

    public function getName()
    {
        $this->logger->notice(__METHOD__);
        // var bv_loginName=
        $name = $this->waitForElement(WebDriverBy::xpath("
            //strong[@id = 'user_name']
            | //table[@id = 'accountInformation']//td[contains(text(), 'Welcome')]
            | //div[@id = 'cA-mrc-WelcomeMsgWrapper' and h4[contains(text(), 'WELCOME')]]/h2
        "), 0);

        if (!empty($name)) {
            $name = $name->getText();
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//div[@id = 'cA-spf-WelcomeBarHeadline']", null, true, "/Welcome\s*(?:back|),\s*([^<]+)/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//div[@id = 'welcomeBarHeadline']", null, true, "/Welcome\s*(?:back|),\s*([^<]+)/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("//div[contains(@class, 'bgwelcome')]", null, true, "/Welcome\s*(?:back|),\s*([^<]+)/");
        }

        if (empty($name)) {
            $name = $this->http->FindSingleNode("
                    //div[contains(@class, 'cA-ada-welcomeBarTitleWrapper')]
                    | //h1[contains(@class, 'welcome-header-text')]
                ", null, true, "/(?:Welcome\s*(?:back|)|Good\s*\w+),\s*([^<]+)/"
            );
        }
        $name = str_ireplace(["Welcome, ", "Welcome "], "", $name);
        $this->saveResponse();

        return $name;
    }

    protected function processErrorsAndQuestions()
    {
        $this->logger->notice(__METHOD__);
        sleep(4);

        $sleep = 30;
        $startTime = time();

        $brokenAccounts = [
            'GregKYun87', // AccountID: 4577348
            'RovrAllOvr', // AccountID: 2921679
            'kimkeeton07', // AccountID: 4800530
            'aybassam', // AccountID: 2479384
            'ytliu', // AccountID: 4416586
            'MightyMan908', // AccountID: 702044
            'nobuko65', // AccountID: 3209184
            'sfcywk', // AccountID: 2888867
            'yitzchoki', // AccountID: 2790929
            'angel08032005', // AccountID: 2736952
            'jamesznw', // AccountID: 4617817
            'kcorkish48', // AccountID: 3054006
            'aaronwei272', // AccountID: 2996183
            'charlesjerk8', // AccountID: 2780568
            'deoncarlette', // AccountID: 4020171
            'futu543@gmail.com',
            'bshibao',
            'clee11usa',
            'miguetx5',
            'zkypfer6',
            'debra.schell32@gmail.com',
            'gordonb88',
            'idlewanderlust',
        ];

        while ((time() - $startTime) < $sleep) {
            $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");

            try {
                $this->skipOffer();
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $this->driver->executeScript('window.stop();');
            } catch (WebDriverCurlException $e) {
                $this->logger->error("Exception: " . $e->getMessage());

                throw new CheckRetryNeededException(3, 5);
            }

            // login error
            $error = $this->getErrorText();

            if (!empty($error)) {
                $this->saveResponse();

                throw new CheckException($error, ACCOUNT_INVALID_PASSWORD);
            }
            // For your security, online access has been blocked
            if ($message = $this->waitForElement(WebDriverBy::xpath('//p[
                    contains(text(), "For your security, online access has been blocked.")
                    or contains(text(), "For security reasons, we cannot allow you to proceed.")
                ]'), 0)
            ) {
                throw new CheckException("For your security, online access has been blocked.", ACCOUNT_LOCKOUT);
            }

            if (
                $this->waitForElement(WebDriverBy::xpath('
                    //h1[contains(text(), "AccountOnline Temporarily Unavailable")]
                    | //span[contains(@class, "show") and contains(text(), "Enter a Password")]
                '), 0)
                && $this->loginFromMainPage()
            ) {
                sleep(4);
                $this->saveResponse();
                $this->skipOffer();
            }

            // provider bug workaround
            $this->saveResponse();

            if ((($message = $this->waitForElement(WebDriverBy::xpath('//div[@id = "main-message"]/p[@jsselect = "summary"]'), 0))
                    && $this->http->FindPreg("/online\.citi\.com redirected you too many times\./ims", false, $message->getText()))
                // The page isn't redirecting properly
                || $this->http->FindSingleNode('//h1[@id = "errorTitleText"]', null, true, "/The page isn.t redirecting properly/ims")
                /**
                 * You will find your consolidated payments and transfers activity on the page below including eBills,
                 * scheduled payments and/or transfers and up to your 15 most recent transactions.
                 */
                || $this->http->FindSingleNode('//div[@id = "leftSubApp"]', null, true, "/You will find your consolidated payments and transfers activity on the page below including eBills,/ims")
            ) {
                $this->logger->notice("provider bug, try to load account manually");
//                $this->http->GetURL("https://online.citi.com");

                // The page isn't redirecting properly
                if (in_array($this->AccountFields['Login'], ['gettogwu'])
                    && (($message = $this->waitForElement(WebDriverBy::xpath('//div[@id = "main-message"]/p[@jsselect = "summary"]'), 0))
                        && $this->http->FindPreg("/online\.citi\.com redirected you too many times\./ims", false, $message->getText()))) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if (in_array($this->AccountFields['Login'], ['chris@lowry.me'])
                    && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "You will find your consolidated payments and transfers activity on the page below including eBills, scheduled payments and/or transfers and up to your 15 most recent transactions.")]'), 0)) {
                    $this->throwProfileUpdateMessageException();
                }

                $this->http->GetURL("https://www.citi.com/credit-cards/citi.action");
                $back = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Back to Accounts')]"), 5);

                if ($back) {
                    $back->click();
                }

                sleep(3);
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

                if (
                    $this->http->currentUrl() == 'https://www.citi.com/credit-cards/citi.action'
                    && $this->waitForElement(WebDriverBy::xpath('//div[@class = "cA-cardsLoginItem"]'), 0)
                ) {
                    $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
                }
            }
            /*
            // We Can’t Find This Page (404)
            if ($message = $this->waitForElement(WebDriverBy::xpath('//div[@class = "cS-structRightPanelUpperContainer"]//h2[contains(text(), "We Can’t Find This Page (404)")]'), 0))
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            */
            if (($message = $this->waitForElement(WebDriverBy::xpath('//h2[
                        contains(text(), "We Can’t Find This Page (404)")
                        or contains(text(), "Is your Contact Info up-to-date?")
                        or contains(text(), "Please Confirm Your Info")
                        or contains(text(), "Mortgage Discounts For Room To Grow")
                        or contains(text(), "Mortgage Deals For New Hiding Spots")
                        or contains(., "Mortgage Deals For Room To Grow")
                        or contains(text(), "Please Update Your Info")
                    ]
                    | //span[contains(text(), "Trouble signing on? Select ")]
                    | //span[@class="strong" and contains(text(), "Trouble signing on?")]
                    | //a[contains(text(), "Return to Dashboard")]
                    | //h1[contains(text(), "Oops! Page Not Found")]
                '), 0))
                // The page isn’t redirecting properly
                || $this->http->FindSingleNode('//h1[contains(text(), "The page isn’t redirecting properly")]')
                || $this->http->currentUrl() == 'https://www.citi.com/'
            ) {
                if ($message) {
                    $error = $message->getText();
                    $this->logger->error($error);

                    if ($this->attempt == 1 && $error == 'Trouble signing on?') {
                        throw new CheckException('Trouble signing on? Select "Forgot User ID or Password"', ACCOUNT_INVALID_PASSWORD);
                    }
                } else {
                    $this->logger->error("The page isn’t redirecting properly");
                }

                if (
                    !in_array($this->AccountFields['Login'], $brokenAccounts)
                    && $this->http->currentUrl() !== 'https://www.citi.com/'
                ) {
                    try {
                        $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
                    } catch (TimeOutException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                        $this->driver->executeScript('window.stop();');
                    } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
                        $this->logger->error("Exception: " . $e->getMessage());
                    }
                }
                sleep(3);
                $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

                // AccountID: 4726217, 4658097, 4386059, 4698984, 3246336
                // refs #18011
                if (
                    stristr($this->http->currentUrl(), 'https://online.citi.com/US/login.do?')
                    // AccountID: 3528474
                    || stristr($this->http->currentUrl(), 'https://www.citi.com/?')
                ) {
                    $this->loginFromMainPage();
                } elseif ($this->http->currentUrl() === 'https://www.citi.com/') {
                    $this->loginFromMainPage(false);
                }
            }
            // AccountID: 4020171
            elseif (strstr($this->http->currentUrl(), 'https://online.citi.com/US/ag/OneClickPaperlessEnrollment/Home?offerBankName=EZ_P_ALLE_05183_1N')) {
                $this->http->GetURL("https://online.citi.com/US/ag/mrc/dashboard");
            }
            // Update your Citibank online account with your new ATM/Debit Card or Credit Card
            /*
             * You should have received your new Citibank(r) ATM/Debit Card by now.
             * Once you have entered the required information and activated your new ATM/Debit Card,
             * you will be able to access the full functionality of Citibank Online.
            */
            /**
             * Continue to Limited Site
             * -------------------------------------------------------------------------------------------
             * To access your account online, please create your Security Questions.
             */
            if (
                $this->waitForElement(WebDriverBy::xpath('
                    //strong[contains(text(), "Update your Citibank online account with your new ATM/Debit Card or Credit Card")]
                    | //span[@id = "userLogin"]/p[contains(text(), "You should have received your new Citibank(r) ATM/Debit Card by now.")]
                    | //span[@id = "userLogin"]//p[contains(text(), "If you haven\'t received your new  Citibank(r) ATM/Debit card yet, you can access a limited version of Citibank Online.")]
                    | //span[@class = "jrsintroText" and contains(text(), "Please read the Citi Online Banking User Agreement carefully")]
                    | //a[@id = "cmlink_CyotaCreateQuestionsIntr" and contains(text(), "CREATE SECURITY QUESTIONS")]
                    | //div[@id = "jrsintroText"]/p/b[contains(text(), "Create Your Security Questions")]
                    | //h1[contains(text(), "Want a Credit Limit Increase?")]
                    | //button[@id = "unlinkAccountButton" and contains(text(), "Unlink My Account")]
                    | //button[@id = "updateEntry" and contains(text(), "Update User ID")]
                    | //h1[contains(text(), "You don’t need your paper statement to remember to make a payment anymore.")]
                    | //*[self::div or self::h2][contains(@class, "head-padding") and contains(text(), "Activate Your Card")]
                    | //h2[contains(text(), "Security Questions Help Us Verify Your Identity")]
                    | //span[contains(text(), "Select Create Security")]
                    | //*[self::h2 or self::span][contains(text(), "s Time for a New Password.")]
                '), 0)
                // It's easy to update the delivery method for your statements and legal notices to paperless.
                || $this->waitForElement(WebDriverBy::id('continueButton'), 0) && $this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "It\'s easy to update the delivery method for your statements and legal notices to paperless.")]'), 0)
                // In addition, you may be asked to update your user ID and/or password.
                || $this->waitForElement(WebDriverBy::id('updateUserButton'), 0) && $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "In addition, you may be asked to update your user ID and/or password.")]'), 0)
                || strstr($this->http->currentUrl(), 'profile-update/change-password')
            ) {
                $this->throwProfileUpdateMessageException();
            }
            // Terms & Conditions
            if (
                $this->waitForElement(WebDriverBy::xpath('
                    //span[@class = "jrsintroText" and contains(text(), "Please read the Citi Online Banking User Agreement carefully")]
                    | //div[contains(text(), "Your password doesn’t meet the latest requirements. Take a moment to update it now.")]
                    | //div[contains(text(), "Your User ID doesn’t meet the latest requirements. Take a moment to update it now.")]
                    | //h1[contains(text(), "VERIFY YOUR INFORMATION")]
                    | //h2[contains(text(), "Security Questions Help Us Verify Your Identity")]
                '), 0)
            ) {
                $this->throwAcceptTermsMessageException();
            }
            // two question (ATM typically)
            if ($this->waitForElement(WebDriverBy::xpath('//input[@id = "challengeQuesId0"] | //form[@id = "cinpin_form"]//input[@id = "cds-input-0"]'), 0)) {
                return $this->processATM();
            }
            // Security checkpoint, two question (Mother/city typically)
            if ($this->waitForElement(WebDriverBy::xpath("
                    //label[@for = 'challengeAnswers0' or contains(@for, 'sec_question_0')]
                    | //h2[normalize-space(text()) = 'Security Questions']
                    | //h2[normalize-space(text()) = 'Challenge Questions']
                    | //p[normalize-space(text()) = 'Please answer the question below.']
                "), 0)
            ) {
                if ($res = $this->processSecurityCheckpoint()) {
                    // Security checkpoint, two question (Mother/city typically)
                    if ($this->waitForElement(WebDriverBy::xpath("
                        //label[@for = 'challengeAnswers0' or contains(@for, 'sec_question_0')]
                        | //h2[normalize-space(text()) = 'Security Questions']
                        | //h2[normalize-space(text()) = 'Challenge Questions']
                        | //p[normalize-space(text()) = 'Please answer the question below.']
                    "), 0)
                    ) {
                        return $this->processSecurityCheckpoint();
                    }

                    return $res;
                }
            }
            // Security checkpoint, two question (Last 3 digits on Signature Pane/Security Word typically)
            if ($this->waitForElement(WebDriverBy::xpath("//label[@for = 'CVV2']"), 0)) {
                return $this->processSecurityCheckpointV2();
            }

            if ($this->waitForElement(WebDriverBy::xpath("//h3[normalize-space(text()) = 'OTP - Challenge']"), 0)) {
                return $this->processIdentificationCode();
            }

            $this->saveResponse();

            if ($this->loginSuccessful()) {
                return true;
            }

            // todo: should works
            if (($this->waitForElement(WebDriverBy::xpath('//h2[contains(text(), "Please Update Your Info")]'), 0))) {
                $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");
                $this->saveResponse();

                if ($this->loginSuccessful()) {
                    return true;
                }
            }

            // The server is temporarily unable to service your request. Please try again later.
            if ($message = $this->http->FindPreg("/h1>\s*(The server is temporarily unable to service your request\.\s*Please try again\s*later\.)<p>/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // The server cannot use the error page specified for your application to handle the Original Exception printed below.
            if ($this->http->FindSingleNode("//h4[contains(text(), 'The server cannot use the error page specified for your application to handle the Original Exception printed below.')]")
                /**
                 * Internal Server Error
                 * -------------------------------------------------------------------------------------------
                 * Temporary delay.
                 *
                 * Right Key, Wrong Door // AccountID: 3549754
                 */
                || $this->waitForElement(WebDriverBy::xpath("
                        //h1[contains(text(), 'Internal Server Error - Read')]
                        | //h4[@class = 'appError' and contains(text(), 'Temporary delay')]
                        | //p[contains(text(), 'CMLinkTag returned null for contentId:ReturnToCiti, appId:JPSINFRA')]
                        | //h2[contains(text(), 'Right Key, Wrong Door')]
                    "), 0)
                // AccountID: 6035399
                || (
                    $this->http->currentUrl() == 'https://online.citi.com/US/ag/parent-interstitial?deepdrop=true&checkAuth=Y'
                    && $this->AccountFields['Login'] == '1504gohogs'
                )
                ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            /**
             * Having trouble signing on? Please try again or click here to be reminded of your user ID or reset your password.
             * -------------------------------------------------------------------------------------------
             * Please contact our customer service department immediately at the number on the back of your card to avoid an interruption of your next credit card transaction.
             * -------------------------------------------------------------------------------------------
             * We're sorry. Citi.com is temporarily unavailable.
             * -------------------------------------------------------------------------------------------
             * We're sorry. This page is unavailable.
             * -------------------------------------------------------------------------------------------
             * We're very sorry, but that content you are looking for can't be found.
             * -------------------------------------------------------------------------------------------
             * Our system is experiencing temporary delays.
             * -------------------------------------------------------------------------------------------
             * AccountID: 2942392
             * We're very sorry we're having technical issues. Please try again. We apologize for any inconvenience and appreciate your patience. [Citi002].
             */
            /**
             * You have mobile-only access which means you can’t log in here.
             * The good news is, you can use your app to open a checking account and get access to all the tools and features here, in addition to all the great benefits you currently enjoy.
             */
            if (
                $message = $this->waitForElement(WebDriverBy::xpath('
                    //td[contains(text(), "Please contact our customer service department immediately at the number on the back of your card to avoid an interruption of your next credit card transaction.")]
                    | //h1[contains(text(), "We\'re sorry. Citi.com is temporarily unavailable.")]
                    | //td[@class = "jrsintrotext"]/li[@class = "jrserrortext" and contains(text(), "We\'re sorry. This page is unavailable.")]
                    | //p[contains(text(), "We\'re very sorry, but that content you are looking for can\'t be found.")]
                    | //h2[contains(text(), "Our system is experiencing temporary delays.")]
                    | //p[contains(text(), "We\'re very sorry we\'re having technical issues. Please try again.")]
                    | //p[contains(text(), "You have mobile-only access which means you can’t log in here.")]
                    | //p[contains(text(), "Citi Online Access® is currently down for scheduled maintenance.")]
                '), 0)
            ) {
                if (!empty($message->getText())) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }
            }
            /**
             * General Error.
             *
             * We've had a problem processing your request.
             * Please call the number on the back of your card for assistance.
             */
            if ($this->waitForElement(WebDriverBy::xpath("//font[@color = 'red' and contains(., 'General Error')]"), 0)
                && ($message = $this->http->FindPreg("/We've had a problem processing your request\./"))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Identification Code Delivery Options
            if ($this->waitForElement(WebDriverBy::xpath("//div[@id = 'otpPasswordSecurityHeader'] | //h2[contains(text(), 'Call in for Identification Code')]"), 0)) {
                $this->logger->info('Identification Code Delivery Options', ['Header' => 3]);

                throw new CheckException("We do not support accounts with Identification Code yet", ACCOUNT_PROVIDER_ERROR); /*review*/
                $this->logger->notice('Verification: Identification Code Delivery Options');
                $this->DebugInfo = 'Identification Code';
                $this->saveResponse();

                break;
            }// if ($this->waitForElement(WebDriverBy::xpath("//div[@id = 'otpPasswordSecurityHeader']"), 0))
            // Help Us Verify Your Identity
            if ($this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDeliveryOptions' or @id = 'deliveryMethod']"), 0)) {
                return $this->processIdentificationCode();
            }

            if (
                $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Help Us Verify Your Identity')]"), 0)
                && ($btn = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0))
            ) {
                $this->saveResponse();

                if ($choose = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'cds-dropdown-1-input'] | //button[@id = 'otpDropdown1']"), 0)) {
                    $choose->click();
                    $this->saveResponse();

                    if ($sms =
                        $this->waitForElement(WebDriverBy::xpath("//*[self::span or self::div][contains(text(), 'Text message')]"), 2)
                        ?? $this->waitForElement(WebDriverBy::xpath("//*[self::span or self::div][contains(text(), 'Phone call')]"), 0)
                    ) {
                        $sms->click();

                        if ($choosePhone = $this->waitForElement(WebDriverBy::xpath("//input[@placeholder = 'Choose a preferred number']"), 0)) {
                            $choosePhone->click();
                            // select first number
                            $this->waitForElement(WebDriverBy::xpath('//span[@class = "select-option"]'), 0)->click();
                        }// if ($choosePhone = $this->waitForElement(WebDriverBy::xpath("//input[@placeholder = 'Choose a preferred number']"), 0))
                    }
                    $this->saveResponse(); //todo: debug
                }// if ($choose = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'cds-dropdown-1-input']"), 0))
                $btn->click();

                return $this->processIdentificationCodeThankyouEntering();
            }

            /*
            $loginField = $this->waitForElement(WebDriverBy::xpath('//*[@id = "username"] | //*[@id = "USERNAME"] | //input[@name = "User ID"]'), 0);
            if ($loginField !== null) {
                $this->logger->info("I see login page again. retry");
                throw new CheckRetryNeededException(2, 1);
            }
            */
        }// while ((time() - $startTime) < $sleep)

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        // We're very sorry. AccountOnline is temporarily unavailable. Please stop by later.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "We\'re very sorry. AccountOnline is temporarily unavailable. Please stop by later.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if (strstr($this->http->currentUrl(), 'profile-update/change-password')) {
            $this->throwProfileUpdateMessageException();
        }

        // debug
        if (($topNavAccounts = $this->waitForElement(WebDriverBy::xpath("//li[@id = 'topNavAccounts']/a | //button[contains(text(), 'View Accounts')]"), 0))
            && (stristr($this->http->currentUrl(), 'goallpaperless/flow.action')
                // todo: debug
                || stristr($this->http->currentUrl(), 'portal/Unauthorized.do')
                || stristr($this->http->currentUrl(), 'fraud-interstitial/high-fraud-alert')
            )
        ) {
            $topNavAccounts->click();
            $signOff = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Sign Off')]"), 7);
            $this->saveResponse();

            if ($signOff) {
                return true;
            }
        } elseif (stristr($this->http->currentUrl(), 'fraud-interstitial/high-fraud-alert')) {
            $this->throwProfileUpdateMessageException();
        }

        // todo: debug
        // Contact Customer Service
        $linkContinue = $this->waitForElement(WebDriverBy::id('cbol_cam_okEot'), 0);
        $this->saveResponse();

        if ($linkContinue && $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'We apologize for any inconvenience, but to protect your account, further charges may be limited until you have contacted our Customer Service Department')]"), 0)) {
            $linkContinue->click();
            sleep(3);
            // success - account shown
            if ($this->loginSuccessful()) {
                return true;
            }
        }
        // We’re experiencing a temporary technical issue. Please try again later.
        if ($message = $this->waitForElement(WebDriverBy::xpath('//div[contains(text(), "We’re experiencing a temporary technical issue. Please try again later.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        $this->skipOffer();

        if (
            $this->loginSuccessful()
            || $this->waitForElement(WebDriverBy::xpath("//div[contains(@class, 'cA-mrc-rewardsContainer')] | //div[contains(text(), 'Link Your Other Accounts')]"), 0)
            || $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'Your FICO')]"), 0, false)
        ) {
            return true;
        }

        // AccountID: 3052504
        if (
            $this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Start managing your Citibank accounts today')]"), 0)
            && $this->waitForElement(WebDriverBy::xpath("//a[contains(., 'Customize this page')]"), 0)
        ) {
            $this->SetBalanceNA();

            return false;
        }

        // no session on some accounts
        if (
            strstr($this->http->currentUrl(), 'loginpage/retarget.action?loginscreenId=inactivityLandingPage&')
            || $this->waitForElement(WebDriverBy::xpath('//p[contains(text(), "Thank you for banking with Citi. You\'ve successfully signed off.")]'), 0)
        ) {
            $this->DebugInfo = "You've successfully signed off.";

            throw new CheckRetryNeededException(3, 5);
        }
        $this->saveResponse();

        /*
         * AccountID: 4673817, 5344433
         *
         * Feature temporarily unavailable.
         *
         * We're sorry, but this feature is temporarily unavailable. Please try again later.
         * If you continue to see this message, please call 1-800-374-9700 (TTY:800-788-0002) for further assistance.
         *
         * We apologize for any inconvenience and thank you for your patience.
         */
        if ($message = $this->waitForElement(WebDriverBy::xpath('//td[contains(., "Feature temporarily unavailable.")]'), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        // AccountID: 4721819, 2480741, 2910948
        if (
            $this->http->currentUrl() == 'https://www.citi.com/credit-cards/home'
            || $this->http->currentUrl() == 'https://www.citi.com/login?deepdrop=true&checkAuth=Y&requestURL=%2FUS%2FJSO%2Floginpage%2Fretarget.action'
        ) {
            $this->http->GetURL("https://online.citi.com/US/CBOL/ain/cardasboa/flow.action");

            $this->loginFromMainPage(false);

            sleep(4);
            $this->saveResponse();
            $this->skipOffer();

            $this->saveResponse();

            if ($this->loginSuccessful()) {
                return true;
            }
        }

        if (
            // page not loaded
            strstr($this->http->currentUrl(), 'https://online.citi.com/US/ag/intr/incomecapture?incomeCaptureparam=screenID:Portal_Intr%7CofferName:EC_P_CFIN_')
            // auth failed, provider bug fix
            || strstr($this->http->currentUrl(), 'https://online.citi.com/US/login.do')
            // empty page
            || strstr($this->http->currentUrl(), 'https://online.citi.com/US/JSO/signon/uname/HomePageCinless.do?SYNC_TOKEN=')
            // no errors, no auth
            || $this->http->currentUrl() == 'https://accountonline.citi.com/cards/svc/LoginGet.do'
            || $this->http->currentUrl() == 'https://www.citi.com/credit-cards/home'
            // empty page
            || strstr($this->http->currentUrl(), 'https://online.citi.com/US/JPS/portal/Home.do?JFP_TOKEN=')
            // not auth, no errors
            || strstr($this->http->currentUrl(), 'https://www.citi.com/?JFP_TOKEN=')
            // not auth, no errors
            || $this->http->currentUrl() == 'https://www.citi.com/login?srdr=1&requestURL=%2FUS%2FJPS%2Fportal%2FIndex.do'
            || $this->http->currentUrl() == 'https://www.citi.com/'
            || $this->http->currentUrl() == 'https://www.citi.com/?loginScreenId=inactivityHomePage'
            || $this->http->currentUrl() == 'https://www.citi.com/login?deepdrop=true&checkAuth=Y'
            || $this->http->FindPreg("#^<head><\/head><body><pre style=\"word-wrap: break-word; white-space: pre-wrap;\"><\/pre><\/body>#")
            || $this->http->FindPreg("#^<head><link[^>]+title=\"Wrap Long Lines\"></head><body><pre></pre></body>#")
            || $this->http->FindPreg("#^<head><\/head><body><\/body>#")
            || $this->http->FindSingleNode("//h2[contains(text(), 'The Server Has Timed Out (504)')]")
            || $this->http->FindSingleNode('//span[contains(text(), "Trouble signing on? Select ")]')
            || $this->http->FindSingleNode('//span[@class="strong" and contains(text(), "Trouble signing on?")]')
            || $this->http->FindSingleNode('//p[contains(normalize-space(), "We\'re very sorry, but you are seeing this page due to your browser settings. Please clear your cache and try again. We appreciate your patience and apologize for any inconvenience.")]')
        ) {
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            if (
                $this->attempt == 1
                && $this->http->FindSingleNode('
                    //h2[contains(text(), "The Server Has Timed Out (504)")]
                    | //p[contains(normalize-space(), "We\'re very sorry, but you are seeing this page due to your browser settings. Please clear your cache and try again. We appreciate your patience and apologize for any inconvenience.")]
                ')
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            // Trouble signing on? Select "Forgot User ID" or "Forgot Password".
            if ($this->attempt == 1 && $this->waitForElement(WebDriverBy::xpath('
                    //span[contains(text(), "Trouble signing on? Select ")
                    | //span[@class="strong" and contains(text(), "Trouble signing on?")]
                ]'), 0)
            ) {
                throw new CheckException('Trouble signing on? Select "Forgot User ID or Password"', ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $this->attempt == 1
                && (in_array($this->AccountFields['Login'], array_merge([
                    'sjlinde8860',
                    'zoneraven',
                    'cyclelimbic',
                    'keyshagonzalez',
                    'lgcc4golf',
                    'dannyktam',
                    'mjmsally',
                    'dsteven53',
                    'ankbomb',
                    'sidpande',
                    'andreakickuth2',
                ], $brokenAccounts))
                    || (isset($error) && $error == 'Trouble signing on?')
                )
            ) {
                throw new CheckException('Trouble signing on? Select "Forgot User ID or Password"', ACCOUNT_INVALID_PASSWORD);
//                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if (
                ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG
                || $this->ErrorCode != ACCOUNT_ENGINE_ERROR
            ) {
                return false;
            }

            throw new CheckRetryNeededException(3, 0);
        }

        // permanently loading
        if (
            strstr($this->http->currentUrl(), 'https://online.citi.com/US/ag/offerintr?intrSessionCount=')
            && in_array($this->AccountFields['Login'], [
                'ljunho7', // AccountID: 4159532
            ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        return $this->checkErrors();
    }

    protected function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // success - account shown
        if (
            $this->waitForElement(WebDriverBy::xpath('
                    //div[@id = "cA-spf-WelcomeBarHeadline"]
                    | //div[@id = "cA-mrc-WelcomeMsgWrapper"]
                    | //div[contains(@class, "bgwelcome")]
                    | //div[contains(@class, "cA-ada-welcomeBarTitleWrapper")]
                    | //strong[@id = "user_name"]
                    | //div[@id = "welcomeBarHeadline"]
                    | //h3[contains(@class, "cH-accountSummaryHead")]
                    | //h1[contains(@class, "welcome-header-text")]
                '), 0)
        ) {
            return true;
        }
        $this->saveResponse();

        return false;
    }

    protected function getErrorText()
    {
        $this->logger->notice(__METHOD__);
        $result = $this->waitForElement(WebDriverBy::xpath("
            //font[@class = 'err-new']
            | //h1[contains(text(), 'Incorrect Information Entered')]
            | //div[@class = 'allBadAccountsSpan2Msg']
            | //div[contains(text(), 'This card has been converted to a new number or was closed at your')]
            | //span[(@id = 'lessPasswordChaError' or @id = 'passwordError') and contains(text(), 'Your password must be at least 6 characters long')]
            | //span[@id = 'lessUidChaError' and contains(text(), 'Your User ID must be greater than 2 characters')]
            | //span[@class = 'strong' and contains(text(), 'Your information doesn') and contains(., 't match our records.')] 
            | //div[contains(text(), 'Your password must be at least 6 characters long')] 
        "), 0)
            ?? $this->waitForElement(WebDriverBy::xpath("
                //p[contains(@class, 'invalidModalHeader') and contains(., 'Sorry, we still don') and contains(., 't recognize your information.')] 
        "), 0);

        if (!empty($result)) {// old?
            $result = $result->getText();
            $result = str_ireplace("Please choose one of the two options below to continue.", "", $result);
        }

        return $result;
    }

    protected function processIdentificationCode()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (Identification Code)', ['Header' => 3]);
        $this->saveResponse();

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $question = 'Please enter the Identification Code you just received exactly as it was provided.';

        $otpDeliveryOptions = $this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDeliveryOptions' or @id = 'chosenDeliveryOption']"), 3);

        if (!$otpDeliveryOptions) {
            $this->logger->error("something went wrong");

            return false;
        }

        // I already have an Identification Code
        if (isset($this->Answers[$question]) && ($alreadyHaveCode = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_AlreadyHaveCode']"), 3))) {
            $alreadyHaveCode->click();

            return $this->processIdentificationCodeEntering();
        }

        $otpDeliveryOptions->click();
        $phone = $this->waitForElement(WebDriverBy::xpath("//option[contains(text(), 'Text me at')]"), 3);

        if ($phone) {
            $text = 'Text me at';
        }

        if (!$phone) {
            $phone = $this->waitForElement(WebDriverBy::xpath("//option[contains(text(), 'Call Me at')]"), 0);

            if ($phone) {
                $text = 'Call Me at';
            }
        }

        if (!$phone) {
            $this->logger->error("something went wrong");

            return false;
        }
        $this->driver->executeScript("document.querySelector(\"select[name = 'selectedDeliveryOption']\").selectedIndex = $('option:contains(\"{$text}\")').index();");
        $this->driver->executeScript("checkforSMS();");
//        $phone->submit();
        $smsCheck = $this->waitForElement(WebDriverBy::xpath("//label[@id = 'smsCheck-label']"), 2);
        $this->saveResponse();

        if ($smsCheck) {
            $smsCheck->click();
        } else {
            $this->logger->error("smsCheck not found");
        }
        $cmlink_Next = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_Next']"), 2);
        $this->saveResponse();

        if ($cmlink_Next) {
            $cmlink_Next->click();
        } else {
            $this->logger->error("cmlink_Next not found");
        }
        // Enter Your Identification Code
        $q = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please enter the Identification Code you just received exactly as it was provided.")]'), 10);
        $this->saveResponse();

        if (!$q) {
            $this->logger->error("question not found");

            return false;
        }
        $this->holdSession();
        $this->AskQuestion($question, null, "IdentificationCode");

        return false;
    }

    protected function processIdentificationCodeThankyou()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (Identification Code on Thankyou site)', ['Header' => 3]);
        $this->saveResponse();

        if ($this->isBackgroundCheck()) {
            $this->Cancel();
        }

        $otpDeliveryOptions = $this->waitForElement(WebDriverBy::xpath("//select[@id = 'otpDropdown'] | //div[@id = 'otpDropdown1']"), 0);

        if (!$otpDeliveryOptions) {
            $this->logger->error("something went wrong");

            return false;
        }
        $cont = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
        $this->saveResponse();

        if (!$cont) {
            $this->logger->error("something went wrong");

            return false;
        }
        $cont->click();
        // Code Verification
        // We just sent your One-Time Identification Code to: .. ••• ••• ... (it may be a few minutes before you receive the text message).
        // We just called .. ••• ••• ... to give you a One-Time Identification Code.
        $q = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "We just sent your One-Time Identification Code to:")]
            | //p[contains(text(), "We just called") and contains(text(), "to give you a One-Time Identification Code.")]
        '), 10);
        $this->saveResponse();

        if (!$q) {
            $this->logger->error("question not found");

            return false;
        }
        $this->holdSession();
        $question = $q->getText();
        $this->AskQuestion($question, null, "IdentificationCodeThankyou");

        return false;
    }

    protected function processIdentificationCodeThankyouEntering()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Question]: {$this->Question}");
//        $this->driver->executeScript("$('#otpMaskedDiv input').removeAttr('class');");
//        $this->driver->executeScript("$('#cmlink_VerifyDisabled_Link').removeAttr('aria-disabled');");
//        $this->driver->executeScript("$('#cmlink_VerifyDisabled_Link').removeClass('disabled');");
//        $otp = $this->waitForElement(WebDriverBy::id('otpMasked'), 5);
        $otp = $this->waitForElement(WebDriverBy::id('otpToken'), 5, false);
        $question = $this->waitForElement(WebDriverBy::xpath('
            //p[contains(text(), "We just sent your One-Time Identification Code to:")]
            | //p[contains(text(), "We just called") and contains(text(), "to give you a One-Time Identification Code.")]
        '), 0);
        $this->saveResponse();

        if (!$otp || !$question) {
            $this->logger->error("something went wrong");
            // AccountID: 656077, wrong number in options
            if ($error = $this->waitForElement(WebDriverBy::xpath('//*[contains(text(), "t complete your verification at this time. Please try again later.")]'), 0)) {
                throw new CheckException($error->getText(), ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->waitForElement(WebDriverBy::xpath("
                    //pre[contains(text(), '{$this->AccountFields['ProviderCode']}+|+{$this->AccountFields['Login']}+|+')]
                    | //pre[contains(text(), '{$this->AccountFields['ProviderCode']} | {$this->AccountFields['Login']} | ')]
                "), 0)
            ) {
                return $this->LoadLoginForm() && $this->Login();
            }

            return false;
        }
        $q = $question->getText();

        if (!isset($this->Answers[$q])) {
            $this->holdSession();
            $this->AskQuestion($q, null, "IdentificationCodeThankyou");

            return false;
        }// if (!isset($this->Answers[$question]))
        $otp->clear();
        $otp->sendKeys($this->Answers[$q]);
        unset($this->Answers[$q]);
//        $this->driver->executeScript("$('#otpToken').val('{$this->Answers[$q]}');");
//        $this->driver->executeScript("$('#otpToken').blur();");
//        $this->driver->executeScript("$('#otpToken').keyup();");
        // Next button
        $cont = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
        $this->saveResponse();

        if (!$cont) {
            $this->logger->error("something went wrong");

            return false;
        }
        $cont->click();
        $this->saveResponse();
        // Please enter a valid Identification Code.
        $error = $this->waitForElement(WebDriverBy::xpath("//span[
            contains(text(), 'Please enter a valid Identification Code.')
            or contains(text(), 'Invalid code. Please try again.')
        ]"), 5);
        $this->saveResponse();

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            $this->AskQuestion($q, $error, "IdentificationCodeThankyou");

            return false;
        }// if (!empty($error))

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if (!strstr($this->http->currentUrl(), 'dashboard')) {
            $this->http->GetURL("https://online.citi.com/US/ag/mrc/dashboard");
        }

        return true;
    }

    protected function processIdentificationCodeEntering()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("[Question]: {$this->Question}");
//        $this->driver->executeScript("$('#otpMaskedDiv input').removeAttr('class');");
//        $this->driver->executeScript("$('#cmlink_VerifyDisabled_Link').removeAttr('aria-disabled');");
//        $this->driver->executeScript("$('#cmlink_VerifyDisabled_Link').removeClass('disabled');");
//        $otp = $this->waitForElement(WebDriverBy::id('otpMasked'), 5);
        $otp = $this->waitForElement(WebDriverBy::id('otp'), 5, false);
        $question = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Please enter the Identification Code you just received exactly as it was provided.")]'), 0);
        $this->saveResponse();

        if (!$otp || !$question) {
            $this->logger->error("something went wrong");

            return false;
        }
        $q = $question->getText();

        if (!isset($this->Answers[$q])) {
            $this->holdSession();
            $this->AskQuestion($q, null, "IdentificationCode");

            return false;
        }// if (!isset($this->Answers[$question]))
//        $otp->sendKeys($this->Answers[$q]);
        $this->driver->executeScript("$('#otp').val('{$this->Answers[$q]}');");
        $this->driver->executeScript("$('#otp').blur();");
        $this->driver->executeScript("$('#otp').keyup();");
        // Next button
//        $cmlink_OTPVerify = $this->waitForElement(WebDriverBy::xpath("//a[@id = 'cmlink_OTPVerify']"), 5);
        $cmlink_OTPVerify = $this->waitForElement(WebDriverBy::xpath("//a[contains(text(), 'Submit')]"), 5);
        $this->saveResponse();

        if (!$cmlink_OTPVerify) {
            $this->logger->error("something went wrong");

            return false;
        }
        $cmlink_OTPVerify->click();
        // The code you entered is incorrect. Please enter your Identification Code again exactly as you received it
        $error = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'The code you entered is incorrect.')]"), 5);
        $this->saveResponse();

        if (!empty($error)) {
            $error = $error->getText();
            $this->logger->notice("error: " . $error);
            $this->holdSession();
            unset($this->Answers[$q]);
            $this->AskQuestion($q, $error, "IdentificationCode");

            return false;
        }// if (!empty($error))
        unset($this->Answers[$q]);

        if ($message = $this->waitForElement(WebDriverBy::xpath("//h1[contains(text(), 'Access Blocked')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_LOCKOUT);
        }
        // We have detected potentially unauthorized attempts to access your account on Citi® Online.
        if ($this->waitForElement(WebDriverBy::xpath("//h2[contains(text(), 'Create New Password')]"), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        return true;
    }

    protected function processATM()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $questions = [];

        foreach ($this->driver->findElements(WebDriverBy::xpath("//label[@for = 'challengeQuesId' or contains(@for, 'cds-input-')]")) as $index => $label) {
            $question = trim($label->getText());
            $this->logger->debug("question #{$index}: {$question}");

            if (!isset($this->Answers[$question])) {
                $this->holdSession();
                $this->AskQuestion($question, null, "Question");

                return false;
            }// if (!isset($this->Answers[$question]))
            else {
                $this->logger->debug("entering answer");
                $input = $label->findElement(WebDriverBy::xpath('following::input[@name] | following::div/input[@name]'));
                $input->clear();
                $input->sendKeys($this->Answers[$question]);
                $this->saveResponse();
                $questions[] = $question;
            }
        }// foreach ($this->driver->findElements(WebDriverBy::xpath("//label[@for = 'challengeQuesId']")) as $index => $label)

        if (!empty($questions)) {
            $this->logger->notice("questions not found");
            $this->saveResponse();

            try {
                $this->logger->debug("click 'Continue'");
                $this->driver->findElement(WebDriverBy::xpath("//*[@id = 'cmlink_ContinueBtnMFA'] | //button[contains(text(), 'Verify')]"))->click();
                $this->logger->debug("find errors...");
                $error = $this->waitForElement(WebDriverBy::xpath("//td[@class = 'jrsintroText']"), 5, true);
                $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
                $this->saveResponse();

                if (!empty($error)) {
                    $error = $error->getText();
                    $this->logger->error("error: " . $error);

                    foreach ($questions as $question) {
                        $this->logger->notice("removing question: " . $question);
                        unset($this->Answers[$question]);
                        $this->holdSession();
                        $this->AskQuestion($question, $error, "Question");
                    }// foreach ($questions as $question)

                    return false;
                }// if (!empty($error))
            } catch (UnexpectedAlertOpenException $e) {
                try {
                    $this->logger->error($e->getMessage());
                    $this->logger->debug($this->driver->switchTo()->alert()->getText());

                    if (stristr($this->driver->switchTo()->alert()->getText(), 'Please enter a valid Date of Birth.')) {
                        $error = $this->driver->switchTo()->alert()->getText();
                        $this->driver->switchTo()->alert()->accept();

                        if (isset($this->Answers["Date of Birth"])) {
                            $this->logger->notice("removing question: 'Date of Birth'");
                            unset($this->Answers["Date of Birth"]);
                            $this->holdSession();
                            $this->AskQuestion("Date of Birth", $error, "Question");
                        }// if (isset($this->Answers["Date of Birth"]))

                        return false;
                    }// if (stristr($this->driver->switchTo()->alert()->getText(), 'Please enter a valid Date of Birth.'))

                    if (stristr($this->driver->switchTo()->alert()->getText(), 'ATM/Debit Card # must be only digits')
                        || stristr($this->driver->switchTo()->alert()->getText(), 'ATM/Debit Card # must be at least 14 digits')) {
                        $error = $this->driver->switchTo()->alert()->getText();
                        $this->driver->switchTo()->alert()->accept();

                        if (isset($this->Answers["ATM/Debit Card #"])) {
                            $this->logger->notice("removing question: 'ATM/Debit Card #'");
                            unset($this->Answers["ATM/Debit Card #"]);
                            $this->holdSession();
                            $this->AskQuestion("ATM/Debit Card #", $error, "Question");
                        }// if (isset($this->Answers["ATM/Debit Card #"]))

                        return false;
                    }// if (stristr($this->driver->switchTo()->alert()->getText(), 'ATM/Debit Card # must be only digits'))
                } catch (NoAlertOpenException $e) {
                    $this->logger->debug("[1] no alert, skip");

                    if (isset($this->Answers["Date of Birth"]) && $this->http->FindPreg("/^\d\/\d{2}\/\d{2}$/", false, $this->Answers["Date of Birth"])) {
                        $this->logger->notice("removing question: 'Date of Birth'");
                        unset($this->Answers["Date of Birth"]);
                        $this->holdSession();
                        $this->AskQuestion("Date of Birth", "Please enter a valid Date of Birth (mm/dd/yyyy)", "Question");

                        return false;
                    }// if (isset($this->Answers["Date of Birth"]))
                }
            }// catch (UnexpectedAlertOpenException $e)
            catch (NoAlertOpenException $e) {
                $this->logger->debug("[2] no alert, skip");
            }
            $this->logger->debug("success");
//            $this->sendNotification("citybank - sq. success");
            return true;
        }// if (!empty($questions))

        return false;
    }

    protected function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $questions = [];

        for ($index = 0; $index < 3; $index++) {
            $this->logger->debug("Question #{$index}");

            if ($index == 2) {
                try {
                    $questionElement = $this->driver->findElement(WebDriverBy::xpath("
                        //div[contains(text(), 'Enter CVV (3-digit code on back of card')]
                        | //input[@placeholder = 'CVV']
                    "))
                        ?? $this->driver->findElement(WebDriverBy::xpath("//input[@placeholder = 'Card number']"));
                } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException | WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $questionElement = null;
                }
            } else {
                try {
                    $questionElement = $this->driver->findElement(WebDriverBy::xpath("
                        //label[@for = 'challengeAnswers" . $index . "']
                        | //div[@class = 'securityQuestionText']
                        | //td[input[@id = 'CYOTA_ANS_" . ($index + 1) . "-citiTextBlur']]/preceding-sibling::td/label[contains(@for, 'sec_question_')]
                        | (//div/label[contains(text(), 'Enter answer')]/ancestor::div[@class = 'row']/*/div/label)[" . ($index + 1) . "]
                        | //div[@class = 'row' and //input[@id = 'cds-input-0']]/node()[contains(text(), '?')]
                    "));
                } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException | WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    $questionElement = null;
                }

                if (!$questionElement) {
                    $questionElement = $this->driver->findElement(WebDriverBy::xpath("//input[@placeholder = 'Card number']"));
                }
            }

            if (!$questionElement) {
                $this->logger->debug("Question #{$index} not found");

                continue;
            }

            $question = trim($questionElement->getText());

            if (empty($question)) {
                $this->logger->debug("Question -> {$question}");
                $question = trim($questionElement->getAttribute('placeholder'));
            }

            $this->logger->debug("Question -> {$question}");

            if (!isset($this->Answers[$question])) {
                $this->holdSession();
                $this->AskQuestion($question, null, "SecurityCheckpoint");

                return false;
            }// if (!isset($this->Answers[$question]))
            else {
                $this->logger->debug("Entering answer on question -> {$question}...");

                if ($index == 2) {
                    try {
                        $this->driver->findElement(WebDriverBy::xpath("//input[@placeholder = 'CVV']"))->sendKeys($this->Answers[$question]);
                    } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                    }
                }

                try {
                    $this->driver->findElement(WebDriverBy::xpath("
                        //input[@id = 'challengeAnswers{$index}']
                        | //input[@id = 'CYOTA_ANS_" . ($index + 1) . "-citiTextBlur']
                        | (//div/label[contains(text(), 'Enter answer')]/following-sibling::div//input)[" . ($index + 1) . "]
                        | //input[@id = 'cds-input-0']
                    "))->sendKeys($this->Answers[$question]);
                } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException | WebDriverException $e) {
                    $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                    try {
                        $this->driver->findElement(WebDriverBy::xpath("//input[@placeholder = 'Card number']"))->sendKeys($this->Answers[$question]);
                    } catch (NoSuchElementException | Facebook\WebDriver\Exception\NoSuchElementException | WebDriverException $e) {
                        $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        $question = null;
                    }
                }
//                $this->driver->findElement(WebDriverBy::id("challengeAnswers".$index))->sendKeys($this->Answers[$question]);
                if ($question) {
                    $questions[] = $question;
                }
            }
        }// for ($index = 0; $index < 2; $index++)

        if (!empty($questions)) {
            $this->logger->debug("click 'Submit'...");
            $this->saveResponse();
            $this->driver->findElement(WebDriverBy::xpath("
                //*[@id = 'cmlink_CyotaSubmitBtn']
                | //img[contains(@src, 'btn_submit')]
                | //div[contains(@class, 'form-buttons') or contains(text(), 'citi-modal-controls')]//button[contains(text(), 'Continue')]
                | //button[contains(text(), 'Next') and not(@disabled)]
            "))->click();
//            $this->driver->findElement(WebDriverBy::id("cmlink_CyotaSubmitBtn"))->click();
            $this->logger->debug("find errors...");
            $error = $this->waitForElement(WebDriverBy::xpath("//ul[@class = 'redAlert']/li/span"), 15);
            $this->logger->debug("[Current URL]: " . $this->http->currentUrl());
            $this->saveResponse();

            if (!empty($error)) {
                $error = $error->getText();
                $this->logger->error("error: " . $error);

                foreach ($questions as $question) {
                    $this->logger->notice("removing question: " . $question);
                    unset($this->Answers[$question]);
                    $this->holdSession();
                    $this->AskQuestion($question, $error, "SecurityCheckpoint");
                }// foreach ($questions as $question)
            }// if (!empty($error))
            $this->logger->debug("done");

            return true;
        }// if (!empty($questions))

        return false;
    }

    protected function processSecurityCheckpointV2()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);
        $this->saveResponse();
        $questions = [];
        $labels = ['CVV2' => "CVV", 'SSN' => "MMNMasked"];

        foreach ($labels as $label => $input) {
            $question = trim($this->waitForElement(WebDriverBy::xpath("//label[@for = '{$label}']/span[2]/label"))->getText());

            if (!isset($this->Answers[$question])) {
                $this->holdSession();
                $this->AskQuestion($question, null, "SecurityCheckpointV2");

                return false;
            }// if (!isset($this->Answers[$question]))
            else {
                if ($label == 'SSN') {
                    $this->driver->executeScript('
                        $("#MMNMasked").val("' . addcslashes($this->Answers[$question], "'") . '");
                        $("#MMN").val("' . addcslashes($this->Answers[$question], "'") . '");
                        processSecureError();
                    ');
                } else {
                    $this->driver->findElement(WebDriverBy::id($input))->sendKeys($this->Answers[$question]);
                }
                $questions[] = $question;
            }
        }// foreach ($labels as $label)

        if (!empty($questions)) {
            $this->driver->findElement(WebDriverBy::id("cmlink_NextValidateEntry"))->click();
            $error = $this->waitForElement(WebDriverBy::xpath("//h4[@id = 'errorMsg'] | //label[@for = 'BlockUserEroorMsg']"), 10);
            $this->saveResponse();

            if (!empty($error)) {
                $error = $error->getText();
                $this->logger->notice("error: " . $error);

                foreach ($questions as $question) {
                    $this->logger->notice("removing question: " . $question);
                    unset($this->Answers[$question]);
                    $this->holdSession();
                    $this->AskQuestion($question, $error, "SecurityCheckpointV2");
                }// foreach ($questions as $question)
            }// if (!empty($error))

            return true;
        }// if (!empty($questions))

        return false;
    }

    protected function processOneTimePin()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question (One-Time Pin Authentication)', ['Header' => 3]);
        $this->saveResponse();

        $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "SFTTokenPin")]'), 0);
        $this->saveResponse();

        $pass = $this->AccountFields['Pass'];

        for ($i = 0; $i < strlen($pass); $i++) {
            $elem = $this->waitForElement(WebDriverBy::xpath('//input[contains(@id, "SFTTokenPin' . $i . '")]'), 0);

            if (!$elem) {
                $this->logger->error('Failed to find input element for password symbol');

                continue;
            }
            $elem->sendKeys($pass[$i]);
            $this->saveResponse();
        }

        if (!isset($elem)) {
            $this->logger->error('Fail');

            return false;
        }

        $this->sendNotification("Nalaysia - 2fa, need to check // RR");
        $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 10); //todo
        $this->saveResponse();
        /*
        $q = $this->waitForElement(WebDriverBy::xpath('
        '), 10);
        $this->saveResponse();
        if (!$q) {
            $this->logger->error("question not found");
            return false;
        }
        $this->holdSession();
        $question = $q->getText();
        $this->AskQuestion($question, null, "OneTimePin");
        */

        return true;
    }

    // refs #23866
    private function removeOriginalDisplayName()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice("unset OriginalDisplayName");
        $this->increaseTimeLimit();

        if (!empty($this->Properties['SubAccounts'])) {
            foreach ($this->Properties['SubAccounts'] as &$subAccount) {
                unset($subAccount["OriginalDisplayName"]);
            }
        }

        unset($subAccount);

        if (!empty($this->Properties['DetectedCards'])) {
            foreach ($this->Properties['DetectedCards'] as &$subAccount) {
                unset($subAccount["OriginalDisplayName"]);
            }
        }
    }

    private function openThankYouRewards()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Open ThankYou Rewards", ['Header' => 3]);

        try {
            $this->logger->notice("Open 'ThankYou Rewards'");
            $this->driver->executeScript("
                    var thankYouRewards = $('a:contains(\"ThankYou Rewards\"), a:contains(\"ThankYou® Rewards\")');
                    try {
                        if (thankYouRewards)
                            thankYouRewards.attr('target', '_self').get(0).click();
                    } catch (e) {}
                ");
        } catch (Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }

        $this->saveResponse();
        $this->waitForElement(WebDriverBy::xpath('
            //div[contains(@class, "card-element-text") and contains(., "Double Cash Card")]
            | //div[contains(@class, "summery-deatil") and contains(., "Double Cash Card")]
            | //h2[contains(text(), "Error 500--Internal Server Error")]
            | //select[@id = "otpDeliveryOptions"]
            | //div[@id = \'otpDropdown1\']
            | //h2[contains(text(), "Help Us Verify Your Identity")]
            | //select[@id = \'otpDropdown\'] | //div[@id = \'otpDropdown1\']
        '), 7); //todo: check sq
    }

    private function setDetectedCardsFromAllCardsDisplayNames($subAccount, $allCardsDisplayNames)
    {
        $this->logger->notice(__METHOD__);

        foreach ($allCardsDisplayNames as $allCardsDisplayName) {
            $allCardsDisplayCode = preg_replace("/[^\d]/ims", '', $allCardsDisplayName);

            if (!$allCardsDisplayCode) {
                $allCardsDisplayCode = preg_replace("/\s*/ims", '', $allCardsDisplayName);
                $allCardsDisplayCode = str_replace("|", '', $allCardsDisplayCode);
            }

            $allCardsDisplayCode = $this->getSubAccountCardCode($allCardsDisplayCode);

            $this->AddDetectedCard(array_merge($subAccount, [
                "Code"            => $allCardsDisplayCode,
                "DisplayName"     => $allCardsDisplayName,
                "CardDescription" => C_CARD_DESC_ACTIVE,
            ]
            ), true);
        }
    }

    private function getSubAccountCardCode($code)
    {
        $this->logger->notice(__METHOD__);

        return 'citybank' . str_replace(['&', ' ', '+', "'", '/', '!', '(', ')', '%'], ['', '', 'Plus', '', '_', '', '', '', 'percent'], $code);
    }

    /* ---------------------------- Australia ------------------------------ */
}
