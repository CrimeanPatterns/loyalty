<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBarclaycard extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    public const PN_FP = 'version%3D1%26pm%5Ffpua%3Dmozilla%2F5%2E0%20%28macintosh%3B%20intel%20mac%20os%20x%2010%5F15%5F7%29%20applewebkit%2F537%2E36%20%28khtml%2C%20like%20gecko%29%20chrome%2F120%2E0%2E0%2E0%20safari%2F537%2E36%7C5%2E0%20%28Macintosh%3B%20Intel%20Mac%20OS%20X%2010%5F15%5F7%29%20AppleWebKit%2F537%2E36%20%28KHTML%2C%20like%20Gecko%29%20Chrome%2F120%2E0%2E0%2E0%20Safari%2F537%2E36%7CMacIntel%26pm%5Ffpsc%3D30%7C1512%7C982%7C866%26pm%5Ffpsw%3D%26pm%5Ffptz%3D5%26pm%5Ffpln%3Dlang%3Den%2DUS%7Csyslang%3D%7Cuserlang%3D%26pm%5Ffpjv%3D0%26pm%5Ffpco%3D1';
    public const SECURITY_QUESTION_UK = 'Please enter your memorable word';

    private $newDesign = false;

    private $ukHost = 'as2r-clb-bcc1-bcol.barclaycard.co.uk';
    private $headersUK = [
        "Accept"           => "application/json, text/javascript",
        "Content-Type"     => "application/json",
        "X-Requested-With" => "XMLHttpRequest",
    ];

    public function get_pm_fp()
    {
        $this->logger->notice(__METHOD__);

        return urlencode('version=-1&pm_fpua=' . strtolower($this->http->userAgent) . '|' . str_replace('Mozilla/', '', strtolower($this->http->userAgent)) . '|MacIntel&pm_fpsc=24|1440|900|829&pm_fpsw=&pm_fptz=5&pm_fpln=lang=en-US|syslang=|userlang=&pm_fpjv=0&pm_fpco=1');
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        if ($this->AccountFields["Login2"] == 'UK') {
            $this->http->setHttp2(true);
            $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        } else {
            $this->setProxyMount();
            $this->http->setHttp2(true);
            $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/' . HttpBrowser::BROWSER_VERSION_MIN . '.0.0.0 Safari/537.36');
        }
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""    => "Please select your website",
            "USA" => "www.barclaycardus.com",
            "UK"  => "www.barclaycard.co.uk",
        ];
    }

    public function IsLoggedIn()
    {
        if ($this->AccountFields["Login2"] == 'UK') {
            return false;
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.barclaycardus.com/servicing/SwitchAccount.action?sourceAction=LOGIN");
        $this->http->RetryCount = 2;

        return $this->GetBalance() !== null || $this->http->FindSingleNode("//span[contains(text(), 'Welcome,') or contains(text(), 'Aloha,')] | //p[@class = 'b-greeting']", null, true, "/,\s*([^\!\.<]+)/ims");
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->notice("Login2 => " . $this->AccountFields['Login2']);
        $this->logger->debug("[Site URL]: https://www.barclaycard.co.uk/personal");

        $this->logger->debug(var_export($this->State, true), ['pre' => true]);

        if ($this->AccountFields["Login2"] == 'UK') {
            // AccountID: 3492297
            if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) !== false && strlen($this->AccountFields['Login']) > 16) {
                throw new CheckException('Please enter a valid Username or ID number', ACCOUNT_INVALID_PASSWORD);
            }

            /*
            $lastName = trim($this->AccountFields['Login3']);
            if (empty($lastName))
                throw new CheckException("To update this Barclaycard account you need to fill in the ‘Last name/family name’ field. To do so, please click the Edit button which corresponds to this account. Until you do so we would not be able to retrieve your bonus information.", ACCOUNT_PROVIDER_ERROR);/*review*/

            $this->http->GetURL("https://{$this->ukHost}/ecom/as2/initialLogon.do");

            // fixed host
            $this->ukHost = $this->http->getCurrentHost();
            $this->logger->debug("Host: {$this->http->getCurrentHost()}");

            if (!$this->http->FindSingleNode("//h2[contains(text(), 'Please enable JavaScript')]")) {
                return $this->checkErrors();
            }
            $data = [
                //                "lastName" => $lastName,
                "rememberMe"    => true,
                "usernameAndID" => $this->AccountFields['Login'],
            ];
            // AccountID: 3251637
            if (is_numeric($this->AccountFields['Login']) && strlen($this->AccountFields['Login']) == 16) {
                $data['cardNumber'] = $this->AccountFields['Login'];
                $data['usernameAndID'] = "";
            }

            $csrf = $this->http->getCookieByName("CSRF-TOKEN", ".barclaycard.co.uk", "/ecom/as2/");

            if (!$csrf) {
                return $this->checkErrors();
            }
            $this->headersUK["X-CSRF-TOKEN"] = $csrf;
            $this->headersUK["Referer"] = "https://{$this->ukHost}/ecom/as2/UI/";

            $this->http->RetryCount = 0;
            $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/initialLogin", json_encode($data), $this->headersUK);
            $responseCode = $this->http->JsonLog()->responseCode ?? null;
            $body = $this->http->Response['body'];

            if ($body != '{"responseCode":"success"}' && $responseCode != 'success') {
                $code = $this->http->Response['code'];
                // Your surname must contain only letters, spaces, or the characters ' or -
                // or
                // Please make sure the information you’re entering is correct.
                if ($code == 400 && $body == '{"status":"error","errors":[{"fieldName":"lastName","errorCode":"ERR_003"}]}') {
                    throw new CheckException("Your surname must contain only letters, spaces, or the characters ' or -", ACCOUNT_INVALID_PASSWORD);
                }
                // Please make sure the information you’re entering is correct.
                // The username you created when your account was registered (8-16 letters and numbers)
                if ($code == 400 && $body == '{"status":"error","errors":[{"fieldName":"loginId","errorCode":"ERR_003"}]}') {
                    throw new CheckException("Please make sure the information you’re entering is correct.", ACCOUNT_INVALID_PASSWORD);
                }
                // We're not able to complete your login request right now, but we'll be able to help over the phone.
                // We're open every day 7am-midnight, so please give us a call on 0800 161 5300 (from your landline),
                // 0333 202 7900 (from a mobile) or +44 1452 828001 (from abroad).
                if ($code == 401 && $body == '{"responseCode":"deregistered"}') {
                    throw new CheckException("We're not able to complete your login request right now, but we'll be able to help over the phone. We're open every day 7am-midnight, so please give us a call on 0800 161 5300 (from your landline), 0333 202 7900 (from a mobile) or +44 1452 828001 (from abroad).", ACCOUNT_INVALID_PASSWORD);
                }
                // AccountID: 5276339
                if ($code == 401 && $body == '{"responseCode":"ERR_007","forcedJourney":false,"isNewJourney":true}') {
                    throw new CheckException("Please enter the correct details", ACCOUNT_INVALID_PASSWORD);
                }

                if ($code == 400 && in_array($body, [
                    '{"status":"error","errors":[{"fieldName":"loginId","errorCode":"ERR_003"}]}',
                    '{"status":"error","errors":[{"fieldName":"usernameAndID","errorCode":"LoginRequest.usernameAndID[length]"}]}',
                    '{"responseCode":"failure","errorList":[{"fieldName":"usernameAndID","errorCode":"ERR_003"}],"forcedJourney":false,"isNewJourney":true}',
                ])
                ) {
                    throw new CheckException("Please make sure the information you’re entering is correct.", ACCOUNT_INVALID_PASSWORD);
                }
                // Please make sure the information you’re entering is correct.
                if ($code == 200 && $body == '{"responseCode":"failure"}') {
                    throw new CheckException("Please make sure the information you’re entering is correct.", ACCOUNT_INVALID_PASSWORD);
                }
                // Sorry, the details you've entered couldn't be recognised, please check and try again.
                if ($code == 502 && $this->http->FindPreg("/Processing of this request was delegated to a server that is not functioning properly\./")) {
                    throw new CheckException("Sorry, the details you've entered couldn't be recognised, please check and try again.", ACCOUNT_INVALID_PASSWORD);
                }

                // Sorry, there's been a problem with our system. // AccountID: 6504347
                if ($body == '{"errorType":"internalError"}') {
                    throw new CheckException("We're currently unable to log you in. Please try again later.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/verificationEligibleMethods", null, $this->headersUK);
            $this->State['Methods'] = $this->http->JsonLog(null, 5);

            if ($this->http->Response['code'] == 500) {
                $title = $this->State['Methods']->error->title ?? null;

                if ($title == 'The request failed due to an internal error.') {
                    throw new CheckException("Sorry, there's been a problem with our system.", ACCOUNT_PROVIDER_ERROR);
                }

                return false;
            }

            // Enter your 6-digit passcode (AccountID: 3251637)
            /*
            $data = [
                "passcode" => substr($this->AccountFields['Pass'], 0, 6)
            ];
            $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/validatePasscode", json_encode($data), $this->headersUK);
            $this->http->RetryCount = 2;
            if (!$this->http->ParseForm("initiallogon"))
                return $this->checkErrors();
            $this->http->SetInputValue("username", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("remember", "true");
//            unset($this->http->Form['remember']);
            */
        } else {
//            $this->http->GetURL("https://www.barclaycardus.com/");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/home?secureLogin=");

            $formInputs = $this->getFormFields();

            if (empty($formInputs)) {
                return $this->checkErrors();
            }
            $this->http->Form = [];

            foreach ($formInputs as $input) {
                if (!empty($input['name'])) {
                    $this->http->Form[$input['name']] = $input['value'];

                    // chrome 95 / pp workaround
                    if (
                        $input['name'] == 'uxLoginForm.username' && $input['value'] == ''
                        || $input['name'] == 'uxLoginForm.password' && $input['value'] == ''
                    ) {
                        $this->http->SetInputValue("uxLoginForm.username", $this->AccountFields['Login']);
                        $this->http->SetInputValue("uxLoginForm.password", $this->AccountFields['Pass']);
                    }
                }
            }
            $this->http->FormURL = 'https://www.barclaycardus.com/servicing/authenticate';

//            if (!$this->http->ParseForm("loginSecureLoginForm"))
//                return $this->checkErrors();
//            $this->http->SetInputValue("uxLoginForm.username", $this->AccountFields['Login']);
//            $this->http->SetInputValue("uxLoginForm.password", $this->AccountFields['Pass']);
//            $this->http->SetInputValue("pm_fp", $this->get_pm_fp());
            $this->http->SetInputValue("login", "Log in");
            $this->http->SetInputValue("uxLoginForm.rememberUsernameCheckbox", "true");

            $captcha = $this->parseReCaptcha();

            if ($captcha !== false) {
                $this->http->SetInputValue('g-recaptcha-response', $captcha);
            }
        }

        return true;
    }

    public function getFormFields()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $formInputs = [];

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            /*
            $selenium->useGoogleChrome();
            */

            $selenium->useChromium();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

            $selenium->disableImages();
//            $selenium->useCache();
//            $selenium->keepCookies(false);
            $selenium->http->saveScreenshots = true;
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->Start();

            $selenium->http->GetURL("https://www.barclaycardus.com/servicing/home?secureLogin=");

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 10);
            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            // Sign In
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "loginButton"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                $this->logger->error("Something went wrong");
                // This site can’t be reached
                if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")) {
                    $this->DebugInfo = "This site can’t be reached";

                    throw new CheckRetryNeededException(5, 10);
                }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]"))

                return $this->checkErrors();
            }// if (!$loginInput || !$passwordInput || !$button)
            $loginInput->click();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->click();
            $passwordInput->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);

            /*
            $selenium->driver->executeScript('document.getElementById(\'loginButton\').click(); window.stop();');
            */
            $button->click();
            $selenium->driver->executeScript('window.stop();');
            $this->savePageToLogs($selenium);

            if ($selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginSecureLoginForm"]//input'), 5, false)) {
                foreach ($selenium->driver->findElements(WebDriverBy::xpath('//form[@id = "loginSecureLoginForm"]//input', 0, false)) as $index => $xKey) {
                    $formInputs[] = [
                        'name'  => $xKey->getAttribute("name"),
                        'value' => $xKey->getAttribute("value"),
                    ];
                }// foreach ($this->driver->findElements(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input', 0, false)) as $index => $xKey)
                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }
//                $this->logger->debug(var_export($formInputs, true), ["pre" => true]);
                // save page to logs
                $this->savePageToLogs($selenium);

                return $formInputs;
            }// if ($this->waitForElement(WebDriverBy::xpath('//form[@id = "aspnetForm"]//input'), 5, false))

//            $button->click();

            if ($skipUpdate = $selenium->waitForElement(WebDriverBy::xpath("//a[@id = 'remindLaterRemoveAu' or @id = 'remindLaterProfile']"), 5)) {
                // save page to logs
                $this->savePageToLogs($selenium);
                $skipUpdate->click();
                sleep(5);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->notice("[Current URL]: {$selenium->http->currentUrl()}");

            if (strstr($selenium->http->currentUrl(), 'https://www.barclaycardus.com/servicing/accountSummaryOnLogin?__fsk=')) {
                $this->logger->notice("Try to open dashboard");
                $selenium->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary");
                $this->logger->notice("[Current URL]: {$selenium->http->currentUrl()}");

                if ($skipUpdate = $selenium->waitForElement(WebDriverBy::xpath("//p[@class = 'b-greeting']"), 5)) {
                    // save page to logs
                    $this->savePageToLogs($selenium);
                    $skipUpdate->click();
                    sleep(5);
                }// if ($skipUpdate = $selenium->waitForElement(WebDriverBy::xpath("//p[@class = 'b-greeting']"), 5))
            }// if (strstr($selenium->http->currentUrl(), 'https://www.barclaycardus.com/servicing/accountSummaryOnLogin?__fsk='))
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return $formInputs;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->AccountFields['Login2'] == 'UK') {
            // maintenance
            $this->CheckError($this->http->FindPreg('/Now and again we make changes, upgrades and improvements to our service[^<]+/'), ACCOUNT_PROVIDER_ERROR);
            // We're sorry that you can't manage your account online at the moment.
            $this->CheckError($this->http->FindSingleNode('//p[contains(text(), "We\'re sorry that you can\'t manage your account online at the moment.")]'), ACCOUNT_PROVIDER_ERROR);
            // maintenance
            $this->CheckError($this->http->FindSingleNode('//p[contains(text(), "We’re sorry – we’re carrying out work right now.")]', null, true, "/(.+)\s+You can still use your card,/"), ACCOUNT_PROVIDER_ERROR);

            if ($this->http->Response['code'] == 503 && $this->http->FindPreg("/An error occurred while processing your request.<p>/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
        } else {
            // We're sorry, but we're currently upgrading our Website
            $this->CheckError($this->http->FindSingleNode('//b[contains(text(), "We\'re sorry, but we\'re currently upgrading our Website")]'), ACCOUNT_PROVIDER_ERROR);
            // We're sorry, we're currently upgrading our website.
            $this->CheckError($this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry, we\'re currently upgrading our website.")]'), ACCOUNT_PROVIDER_ERROR);
            // Barclaycard is in the process of conducting routine system maintenance.
            if ($message = $this->http->FindSingleNode("//b[contains(text(), 'Barclaycard is in the process of conducting routine system maintenance.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Maintenance in Progress
            if ($this->http->FindSingleNode('//div[contains(@class, "maintenance-message-container")]/img[@src = "https://gif.barclaycardus.com/servicing/html/m_error.png"]/@src')) {
                throw new CheckException("Our website is currently undergoing maintenance, we should be back online shortly. We apologies for any inconvenience.", ACCOUNT_PROVIDER_ERROR);
            }
        }

        return false;
    }

    public function LoginUK()
    {
        $this->logger->notice(__METHOD__);
        $this->http->JsonLog();
        $body = $this->http->Response['body'];

        if (strstr($body, 'CARD_CREDENTIALS') && $this->ParseQuestionUK()) {
            return false;
        }

        if (!strstr($body, 'CARD_CREDENTIALS') && strstr($body, 'OTP') && $this->ParseQuestionUK()) {
            return false;
        }

        if ($body == '{"error":{"code":"USER_IDENTITY_MULTIPLE_IDENTITIES_FOUND"}}') {
            throw new CheckException("You’ll need to call us before you’ll be able to log in", ACCOUNT_PROVIDER_ERROR);
        }

        $code = $this->http->Response['code'];

        if ($code == 400 && $body == '{"status":"error","errors":[{"fieldName":"pa55code","errorCode":"PasscodeForm.pa55code[regexp]"}]}') {
            throw new CheckException("Please make sure the information you’re entering is correct.", ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Login()
    {
        if ($this->AccountFields['Login2'] == 'UK') {
            return $this->LoginUK();
        }

        $this->http->setMaxRedirects(10);

        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        $this->http->setMaxRedirects(7);

        $this->CheckError($this->http->FindSingleNode("//div[@class = 'errorIndicatorBlock' and contains(text(), 'we have locked online access')]"), ACCOUNT_LOCKOUT);
        $this->CheckError($this->http->FindPreg("/(For security reasons\, we have locked online access to your account\.)/ims"), ACCOUNT_LOCKOUT);
        $this->CheckError($this->http->FindPreg("/<h1>(Please reset your password)<\/h1>/ims"));
        // Your username must be 6-3(?:0|2) characters
        $this->CheckError($this->http->FindPreg("/(Your username must be 6-3(?:0|2) characters[^<\[]+)/ims"));
        //# There has been a problem processing your login, please try again in a few minutes.
        $this->CheckError($this->http->FindPreg("/(There has been a problem processing your login,\s*please try again in a few minutes\.)/ims"), ACCOUNT_PROVIDER_ERROR);
        // We apologize for the inconvenience, but we could not complete your request. Please try again.
        $this->CheckError($this->http->FindPreg("/(We apologize for the inconvenience, but we could not complete your request\.\s*Please try again\.)/ims"), ACCOUNT_PROVIDER_ERROR);
        // Online access enrollment not complete.
        $this->CheckError($this->http->FindPreg("/(Online access enrollment not complete\.)/ims"), ACCOUNT_PROVIDER_ERROR);
        // Our website is currently unavailable and we are working to resolve this as quickly as possible.
        $this->CheckError($this->http->FindPreg("/enableAlert: true,\s*alertHeader:\s*'(Our website is currently unavailable and we are working to resolve this as quickly as possible\. We apologize for any inconvenience\. Please check again later\.)\s*',/ims"), ACCOUNT_PROVIDER_ERROR);

        if ($this->ParseQuestion(true)) {
            return false;
        }
        // Your username or password is incorrect. Please try again.
        if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your username or password is incorrect. Please try again.')]")) {
            $this->logger->error("[Error]: {$message}");
            $this->logger->debug("[attempt]: {$this->attempt}");
            $retries = 2;

            /*
            if ((isset($this->State['Success']) && $this->State['Success']) || !empty($this->State["QuestionType"])) {
                $retries = 4;
            }
            */
            $this->logger->debug("[retries]: {$retries}");

//            if (($this->attempt == ($retries -1)) && $this->isBackgroundCheck())
//                $this->Cancel();

            if (ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG) {
                $retries = 0;
            }

            throw new CheckRetryNeededException($retries, 7, $message, ACCOUNT_INVALID_PASSWORD); // refs #14720 wrong error
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // todo: old login form
//        if (!$this->SendPassword())
//            return false;
        /*
         * We're sorry, but for security reasons online access to your account is unavailable.
         * Please contact Customer Service at 1-866-922-3725.
         */
        if ($message = $this->http->FindPreg('/We\'re sorry, but for security reasons online access to your account is unavailable\./')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We are sorry something went unexpectedly wrong with your request.
        if ($message = $this->http->FindPreg("/(We are sorry something went unexpectedly wrong with your request\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We apologize for the inconvenience, but we could not complete your request
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience, but we could not complete your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->profileNeedToUpdate();

        // Update profile information
        if ($this->http->FindSingleNode("//form[@id = 'addressForm']")
            && $this->http->FindSingleNode("//a[contains(text(), 'Remind me later')]")) {
            $this->logger->debug(">>> skip Update profile information");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/returnMailInterstitial?remindMeLater=");
        }

        if ($this->http->FindSingleNode("//form[@id = 'eConsentLandingForm']")) {
            $this->logger->debug(">>> skip Help us reach you faster!");
            $this->http->RetryCount = 0;
//            $this->http->PostURL("https://www.barclaycardus.com/servicing/jserv/econsent/cancelenroll", '{"consentAction":"OPT-OUT"}');
            $this->http->RetryCount = 2;
//            https://www.barclaycardus.com/servicing/accountSummary?__fsk=1650837317
            $this->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary");
        }

        // Skip validation of profile
        if ($this->http->FindPreg("/Click 'Save' and we'll know your information is current/ims")
            && $this->http->ParseForm("addressIncomeForm")) {
            $this->logger->debug("Skip validation of profile");
            $this->http->SetInputValue("remindLater", "Remind me later");
            $this->http->PostForm();
        }
        // We're sorry, but for security reasons online access to your account is unavailable.
        if ($message = $this->http->FindSingleNode('//div[contains(text(), "We\'re sorry, but for security reasons online access to your account is unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Retrieve your username and reset your password.
        if (($this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/aiv.action?pageLayout=aivForgotCredentials'
            && $this->http->ParseForm("aivForgotCredentialsForm"))
            || $this->http->FindSingleNode('//h1[contains(text(), "For security reasons, please validate your identity.")]')) {
            throw new CheckException("Barclaycard Rewards website is asking you to retrieve your username and reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }

        if (($skipUpdate = $this->http->FindSingleNode("//a[@id = 'cancelButton']/@href"))
            && $this->http->FindSingleNode("//div[contains(text(), 'Authorized user information needed')]")) {
            $this->logger->debug("Skip profile update");
            $this->http->NormalizeURL($skipUpdate);
            $this->http->GetURL($skipUpdate);
        }

        // need to make a choice
        if ($this->http->FindPreg("/By enrolling today, you are consenting to electronic email delivery of exclusive/ims")
            // validate your profile information
            || $this->http->FindPreg("/To receive account notifications and correspondence more quickly, please validate your email address\./ims")
            || $this->http->FindSingleNode("//div[contains(text(), 'Authorized user information needed')]")
            // Sign up for online access -> Ready to view, manage and pay your account online? Signing up takes just a few minutes.
            || $this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/aiv.action?pageLayout=aivRegistration'
        ) {
            $this->throwProfileUpdateMessageException();
        }

        // AccountID: 2809987
        if ($this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/authenticate?redirectreasoncode=mlp&showSecureLoginPage='
            && $this->AccountFields['Login'] == 'chdabrock') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            $this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/veridchallenge.action?veridCheckReason=Login+Reset&pageLayout=veridOnLogin'
            || $this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/piv.action?pageLayout=personalIdentityChallengeOnLogin'
        ) {
            throw new CheckException("Barclaycard Rewards website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_INVALID_PASSWORD);
        }

        // retries
        if ($this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/home' && $this->http->ParseForm("homePageLoginForm")) {
            $this->logger->error("session was lost after entering answers, retry");

            throw new CheckRetryNeededException(5, 10);
        }// if ($this->http->currentUrl() == 'https://www.barclaycardus.com/servicing/home' && $this->http->ParseForm("homePageLoginForm"))

        return true;
    }

    public function ParseQuestionUK()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->headersUK['X-CSRF-TOKEN'])) {
            return false;
        }
        $this->http->GetURL("https://{$this->ukHost}/ecom/as2/api/login/memorableWordPair", $this->headersUK);
        $response = $this->http->JsonLog();

        if (!isset($response->data[0]->letter) || !isset($response->data[1]->letter)) {
            return false;
        }
        $question = self::SECURITY_QUESTION_UK;
        // N-th letter from memorable word
        foreach ($response->data as $data) {
            switch ($data->id) {
                case '1':
                    $this->State["firstIndex"] = $data->letter;

                    break;

                case '2':
                    $this->State["secondIndex"] = $data->letter;

                break;
            }
        }
        $this->State["headers"] = $this->headersUK;

        if (!isset($question) || !isset($this->State["firstIndex"]) || !isset($this->State["secondIndex"])) {
            return false;
        }
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ParseQuestion($sendAnswers)
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindPreg("/Answer your security questions/ims")
            && ($this->http->ParseForm("rsaChallengeForm")
                // todo: old login form
                || $this->http->ParseForm("loginChallengeForm"))) {
            $this->logger->debug("Just Question");
            $this->State["QuestionType"] = 'Question';
            // todo: old login form
            if ($this->http->ParseForm("loginChallengeForm")) {
                $this->logger->notice("old login form");
                $this->http->SetInputValue("registerDevice", "true");
                $this->http->SetInputValue("handleMultiFactorAuth", "Continue");
            } else {
                $this->http->SetInputValue("submitAnswers", "Continue");
            }
            $this->http->SetInputValue("pm_fp", $this->get_pm_fp());
//            $this->http->SetInputValue("pm_fp", self::PN_FP);
            $needAnswer = false;

            for ($n = 0; $n < 2; $n++) {
                $question = $this->http->FindSingleNode("//div[@class = 'fieldHeaderDivs']", null, false, null, $n);
                $xpath = "//div[@class = 'fieldHeaderDivs']/following-sibling::div//input/@name";
                $xpatQuestionText = "//input[@name = 'rsaForm.rsaQ" . ($n + 1) . "Text']";
                $xpatQuestionCode = "//input[@name = 'rsaForm.rsaQ" . ($n + 1) . "Id']";
                // todo: old login form
                if (!isset($question)) {
                    $this->logger->debug("old login form");
                    $question = $this->http->FindSingleNode("//dt[contains(@class, 'mLabel normal')]", null, false, null, $n);
                    $xpath = "//dt[contains(@class, 'mLabel normal')]/following-sibling::dd/input/@name";
                }

                if (isset($question)) {
                    $this->http->SetInputValue("Question" . ($n + 1), $question);
                    $this->http->SetInputValue("InputQuestion" . ($n + 1), $this->http->FindSingleNode("({$xpath})[" . ($n + 1) . "]"));

                    if (isset($xpatQuestionText, $xpatQuestionCode)) {
                        $this->http->SetInputValue("QuestionText" . ($n + 1), $this->http->FindSingleNode("{$xpatQuestionText}/@value"));
                        $this->http->SetInputValue("QuestionCode" . ($n + 1), $this->http->FindSingleNode("{$xpatQuestionCode}/@value"));
                    }
                    $this->http->SetInputValue("_sourcePage", $this->http->FindSingleNode("//input[@name = '_sourcePage']/@value"));
                    $this->http->SetInputValue("__fp", $this->http->FindSingleNode("//input[@name = '__fp']/@value"));

                    if (!isset($this->Answers[$question])) {
                        $this->AskQuestion($question, null, "Question");
                        $needAnswer = true;
                    }// if (!isset($this->Answers[$question]))
                }// if (isset($question))
            }// for ($n = 0; $n < 2; $n++)

            if (!$needAnswer && $sendAnswers) {
                $this->logger->debug("return to ProcessStep");
                $this->ProcessStep('question');
                $this->Parse();

                return true;
            } else {
                $this->logger->debug("return true");

                return true;
            }
        }// if ($this->http->FindPreg("/Answer your security questions/ims"))
        elseif (strstr($this->http->currentUrl(), 'www.barclaycardus.com/servicing/otp')) {
            $this->logger->debug("SecurPass code");
            $this->State["QuestionType"] = 'SecurPass';
            $this->http->GetURL("https://www.barclaycardus.com/servicing/otp?getOtpMainPage=");
            $response = $this->http->JsonLog(null, 3, false, 'selected');

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            if (isset($response->data->page->components->pageForm->fields->selectemail)
                || isset($response->data->page->components->pageForm->fields->selectphone)
                || isset($response->data->page->components->pageForm->fields->selecttext)
            ) {
                $this->logger->debug("sending SecurPass code to email / phone");
                // email
                $emailData = $response->data->page->components->pageForm->fields->selectemail ?? null;
                // phone
                $phoneData = $response->data->page->components->pageForm->fields->selecttext ?? null;

                if (!$phoneData) {
                    $phoneData = $response->data->page->components->pageForm->fields->selectphone ?? null;
                }
                $emailLabel = $emailData->label ?? null;
                $this->logger->debug("emailData->label: {$emailLabel}");
                $emailValue = $emailData->value ?? null;

                $email = $this->http->FindPreg("/me at (.+)/ims", false, $emailLabel);

                if (isset($phoneData->label)) {
                    $this->logger->debug("phoneData->label: {$phoneData->label}");
                    $phone = $this->http->FindPreg("/me at (.+)/ims", false, $phoneData->label);
                    $phoneDataValue = $phoneData->value;

                    if (in_array($phoneData->label, ['Select a mobile number', 'Select a phone number'])) {
                        unset($option);

                        foreach ($phoneData->options as $option) {
                            if ($option->value == 'HOME_PHONE0') {
                                $phone = $option->label;
                                $phoneDataValue = $option->value;
                                $this->logger->debug("phoneData->label: {$phone} / {$phoneDataValue}");

                                break;
                            }// if ($option->value == 'HOME_PHONE0')

                            // AccountID: 4814809, 4971068, 4814809, 5841789, 1069676
                            if (in_array($option->value, ['ALT1_PHONE0', 'ALT1_PHONE1'])) {
                                $phone = $option->label;
                                $phoneDataValue = $option->value;
                                $this->logger->debug("phoneData->label: {$phone} / {$phoneDataValue}");

                                break;
                            }// if ($option->value == 'HOME_PHONE0')

                            // AccountID: 5883702
                            if (strstr($option->value, "-")) {
                                $phone = $option->label;
                                $phoneDataValue = $option->value;
                                $this->logger->debug("phoneData->label: {$phone} / {$phoneDataValue}");

                                break;
                            }// if (strstr($option->value, "-"))
                        }// foreach ($phoneData->options as $option)
                    }// if (in_array($phoneData->label, ['Select a mobile number', 'Select a phone number']))
                }// if (isset($phoneData->label))

                if ($emailLabel == 'Select an email address.') {
                    unset($option);

                    foreach ($emailData->options as $option) {
                        if ($option->value == 'PRIMARY_EMAIL1') {
                            $emailLabel = $option->label;
                            $emailValue = $option->value;
                            $this->logger->debug("emailData->label: {$emailLabel} / {$emailValue}");
                            $email = $emailLabel;
                        }// if ($option->value == 'PRIMARY_EMAIL1')

                        if ($option->value == 'PRIMARY_EMAIL0') {
                            $emailLabel = $option->label;
                            $emailValue = $option->value;
                            $this->logger->debug("emailData->label: {$emailLabel} / {$emailValue}");
                            $email = $emailLabel;

                            break;
                        }// if ($option->value == 'PRIMARY_EMAIL0')

                        // AccountID: 5016144
                        if (strstr($option->value, "-")) {
                            $emailLabel = $option->label;
                            $emailValue = $option->value;
                            $this->logger->debug("emailData->label: {$emailLabel} / {$emailValue}");
                            $email = $emailLabel;

                            break;
                        }// if (strstr($option->value, "-"))
                    }// foreach ($emailData->options as $option)
                }// if ($emailLabel == 'Select an email address.')

                if (strstr($email, '@')) {
                    $this->logger->notice("sending SecurPass code to email");
                    $this->AskQuestion("Please enter SecurPass™ code which was sent to the following email address: $email. Please note: This SecurPass™ code can only be used once and it expires within 10 minutes.", null, "SecurPass");

                    $this->http->PostURL("https://www.barclaycardus.com/servicing/otp?getOtpEntryPage=", [
                        "channel.emailValue" => $emailValue,
                        "channel.textValue"  => $phoneData->value ?? '',
                        "channel.type"       => "email",
                        "channel.value"      => $emailValue,
                    ]);
                }// if (strstr($email, '@'))
                elseif (isset($phone)) {
                    $this->logger->notice("sending SecurPass code to phone");
                    $this->AskQuestion("Please enter SecurPass™ code which was sent to the following phone number: $phone. Please note: This SecurPass™ code can only be used once and it expires within 10 minutes.", null, "SecurPass");

                    $this->http->PostURL("https://www.barclaycardus.com/servicing/otp?getOtpEntryPage=", [
                        "channel.emailValue" => "",
                        "channel.phoneValue" => "",
                        "channel.textValue"  => $phoneDataValue,
                        "channel.type"       => "text",
                        "channel.value"      => $phoneDataValue,
                    ]);
                }// elseif (isset($phone))
                else {
                    $this->logger->error("options aren't found");

                    return false;
                }

                return $this->checkSendingCode();
            }
        } elseif ($this->http->ParseForm("otpDecision")) {
            $this->logger->debug("SecurPass code [Selenium]");
            $this->State["QuestionType"] = 'SecurPass';
            $this->logger->notice("sending SecurPass code to email / phone [Selenium]");

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }

            $emailValue = $this->http->FindSingleNode("//input[@id = 'emailOption']/@value");
            $email = $this->http->FindSingleNode("//div[input[@id = 'emailOption']]/preceding-sibling::p[1]", null, true, "/Email me at\s*([^<]+)/");

            if (!$email && !$emailValue) {
                $emailValue = 'PRIMARY_EMAIL0';
                $email = $this->http->FindSingleNode("//option[@value = 'PRIMARY_EMAIL0']");

                if (!$email) {
                    $emailValue = 'PRIMARY_EMAIL1';
                    $email = $this->http->FindSingleNode("//option[@value = 'PRIMARY_EMAIL1']");
                }

                if (!$email) {
                    $phoneDataValue = $this->http->FindSingleNode("//input[@id = 'textOption']/@value");
                    $phone = $this->http->FindSingleNode("//div[input[@id = 'textOption']]/preceding-sibling::p[1]", null, true, "/Text me at\s*([^<]+)/");

                    if (!$phoneDataValue && !$phone) {
                        $phoneDataValue = $this->http->FindSingleNode("//input[@id = 'phoneOption']/@value");
                        $phone = $this->http->FindSingleNode("//div[input[@id = 'phoneOption']]/preceding-sibling::p[1]", null, true, "/Call me at\s*([^<]+)/");
                        $channelType = 'phone';
                    }

                    if (!$phone) {
                        $phoneDataValue = 'PRIMARY_PHONE0';
                        $phone = $this->http->FindSingleNode("//option[@value = 'PRIMARY_PHONE0']");
                        $channelType = 'phone';
                    }

                    if (!$phone) {
                        $phoneDataValue = 'PRIMARY_PHONE1';
                        $phone = $this->http->FindSingleNode("//option[@value = 'PRIMARY_PHONE1']");
                        $channelType = 'phone';
                    }

                    if (!$phone) {
                        $phoneDataValue = 'HOME_PHONE0';
                        $phone = $this->http->FindSingleNode("//option[@value = 'HOME_PHONE0']");
                        $channelType = 'phone';
                    }
                }// if (!$email)
            }// if (!$email && !$emailValue)

            if ($email && $emailValue) {
                if ($this->getWaitForOtc()) {
                    $this->sendNotification("2fa - refs #21202 // RR");
                }
                $this->AskQuestion("Please enter SecurPass™ code which was sent to the following email address: " . trim($email) . ". Please note: This SecurPass™ code can only be used once and it expires within 10 minutes.", null, "SecurPass");
                $this->http->SetInputValue("channel.emailValue", $emailValue);
                $this->http->SetInputValue("channel.value", $emailValue);
                $this->http->SetInputValue("channel.type", "email");
                $this->http->PostForm();

                return $this->checkSendingCode();
            }// if ($email && $emailValue)
            elseif (isset($phone, $phoneDataValue)) {
                $this->logger->notice("sending SecurPass code to phone");
                $this->AskQuestion("Please enter SecurPass™ code which was sent to the following phone number: $phone. Please note: This SecurPass™ code can only be used once and it expires within 10 minutes.", null, "SecurPass");

                $this->http->SetInputValue("channel.emailValue", "");
                $this->http->SetInputValue("channel.phoneValue", "");
                $this->http->SetInputValue("channel.textValue", $phoneDataValue);
                $this->http->SetInputValue("channel.value", $phoneDataValue);
                $this->http->SetInputValue("channel.type", $channelType ?? "text");
                $this->http->PostForm();

                return $this->checkSendingCode();
            }// elseif (isset($phone))
        }

        return false;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice("[ProcessStep]: {$this->AccountFields['Login2']}");

        if ($this->AccountFields['Login2'] == 'UK') {
            if ($step == 'QuestionCVV') {
                return $this->enteringCVV($this->Question);
            }

            if ($step == 'QuestionUK2fa') {
                return $this->entering2fa($this->Question);
            }

            $this->logger->notice(__METHOD__);
            $this->logger->debug("{$this->State["firstIndex"]} letter");
            $this->logger->debug("{$this->State["secondIndex"]} letter");

            if (!isset($this->State["headers"])) {
                $this->logger->error("headers not found");

                return false;
            }
            $answer = $this->Answers[$this->Question];

            // Invalid answer
            if (!isset($answer[$this->State["firstIndex"] - 1]) || !isset($answer[$this->State["secondIndex"] - 1])
                || is_numeric($answer)) {
                $this->AskQuestion($this->Question, "Invalid answer", "Question");

                return false;
            }

            $this->headersUK = $this->State["headers"];
            $data = [
                // Enter your 6-digit passcode (AccountID: 3251637)
                "challengeP" => base64_encode(substr($this->AccountFields['Pass'], 0, 6)),
                "challengeM" => [
                    [
                        "id"     => 1,
                        "letter" => strtoupper($answer[$this->State["firstIndex"] - 1]),
                    ],
                    [
                        "id"     => 2,
                        "letter" => strtoupper($answer[$this->State["secondIndex"] - 1]),
                    ],
                ],
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/verifyBasicAuth", json_encode($data), $this->headersUK);
            $this->http->RetryCount = 2;
            $response = $this->http->JsonLog();

            // AccountID: 5375644
            if (
                isset($response->error->code, $response->error->additionalParameters[0]->name)
                && $response->error->code == 'BAD_REQUEST'
                && $response->error->additionalParameters[0]->name == 'challengeP'
            ) {
                throw new CheckException("You've put in the wrong details. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            // AccountID: 4999469
            if (
                isset($response->error->code, $response->error->additionalParameters[0]->name)
                && $response->error->code == 'BAD_REQUEST'
                && $response->error->additionalParameters[0]->name == 'challengeM'
            ) {
                $this->AskQuestion($this->Question, "Invalid answer", "Question");

                return false;
            }

            // AccountID: 6020130
            if (
                isset($response->error->code, $response->error->additionalParameters[0]->name)
                && in_array($response->error->code, ['FAILED_1', 'FAILED_2'])
                && $response->error->additionalParameters[0]->name == 'isOTPEnabled'
            ) {
                throw new CheckException("You've put in the wrong details. Please check and try again.", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                isset($response->error->code, $response->error->additionalParameters[0]->name)
                && $response->error->code == 'LOCKED'
            ) {
                throw new CheckException("Account locked", ACCOUNT_LOCKOUT);
            }

            $this->logger->debug(var_export($this->State['Methods'], true), ['pre' => true]);
            $types = $this->State['Methods']->eligibleMethods[0]->types ?? [];

            if (empty($types)) {
                $this->logger->notice("something went wrong");

                return false;
            }

            foreach ($types as $type) {
                if ($type->name == 'CARD_CVV' && isset($type->cardNumbers[0])) {
                    $this->State['cardNumber'] = $type->cardNumbers[0];
                    // Enter the 3-digit security code (CVV) from the back of your Barclaycard ending **** **** **** ...
                    $question = "Enter the 3-digit security code (CVV) from the back of your Barclaycard ending **** **** **** " . $this->State['cardNumber'];

                    break;
                }

                if ($type->name == 'OTP' && isset($type->mobileNumbers[0])) {
                    $this->State['mobileNumber'] = $type->mobileNumbers[0]->value;
                    $this->State['mobileNumberId'] = $type->mobileNumbers[0]->id;
                    // Enter a one-time verification code to phone number ..*****....
                    $question2fa = "Please enter the verification code which was sent to the following phone number: {$this->State['mobileNumber']}";
                }
            }

            if (!isset($question) && !isset($question2fa)) {
                $this->logger->notice("question not found");

                return false;
            }

            if (!isset($question) && isset($question2fa)) {
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }
                // We'll send a one-time verification code to phone number ..*****....
                $data = [
                    "mobileNumberId" => $this->State['mobileNumberId'],
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/generateOTV", json_encode($data), $this->headersUK);
                $this->http->RetryCount = 2;
                // response 201 - {"mobileNumberId":"...","attemptsRemaining":9}
                $response = $this->http->JsonLog();

                if (!isset($response->mobileNumberId)) {
                    return false;
                }
                $this->AskQuestion($question2fa, null, 'QuestionUK2fa');

                return false;
            }

            if (!isset($this->Answers[$question])) {
                $this->AskQuestion($question, null, 'QuestionCVV');

                return false;
            }

            return $this->enteringCVV($question);

        /*
        if (isset($response->status, $response->redirectUrl) && $response->status == 'success' && $response->redirectUrl == 'deeplinking.do') {
            $redirectUrl = $response->redirectUrl;
            $this->http->PostURL("https://{$this->ukHost}/ecom/as2/{$redirectUrl}", ["_csrf" => $this->headersUK['X-CSRF-TOKEN']]);
        }// if (isset($response->status, $response->redirectUrl) && $response->status == 'success')

        // Sorry, the details you've entered couldn't be recognised, please check and try again.
        if (
            isset($response->status, $response->redirectUrl, $response->responseCode)
            && $response->status == 'success'
            && $response->responseCode == 'error'
            && $response->redirectUrl == 'initialLogon.do?msg=nf'
        ) {
            $this->AskQuestion($this->Question, "Sorry, the details you've entered couldn't be recognised, please check and try again.");
            return false;
        }

        // Invalid answer
        if ($error = $this->http->FindPreg("/Sorry, the details you've entered couldn\'t be recognised\, please check and try again\./")) {
            $this->logger->error(">>> {$error}");
            $this->AskQuestion($this->Question, $error);
            return false;
        }// if ($error = $this->http->FindPreg("/Sorry, the details you've entered couldn\'t be recognised\, please check and try again\./"))
        // Sorry, the details you've entered couldn't be recognised and your account has been locked to protect your security
        if ($message = $this->http->FindPreg("/Sorry, the details you've entered couldn't be recognised and your account has been locked to protect your security/"))
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        // We're sorry - we've been unable to complete your log in request.
        if ($message = $this->http->FindSingleNode('//strong[contains(text(), "We\'re sorry - we\'ve been unable to complete your log in request.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        */
        }// if ($this->AccountFields['Login2'] == 'UK')
        else {// if ($this->AccountFields['Login2'] == 'USA')
            $this->logger->notice("sending answers");

            if (isset($this->State["QuestionType"]) && $this->State["QuestionType"] == 'SecurPass') {
                $this->logger->debug("SecurPass code");

//                $this->sendNotification("barclaycard (USA) - refs #14720. SecurPass code was entered");

                $this->http->PostURL("https://www.barclaycardus.com/servicing/otp?verifyOneTimePasscode=", ["otpPasscode" => $this->Answers[$this->Question]]);
                // remove old SecurPass code
                unset($this->Answers[$this->Question]);

                // if code is bad
                if (!strstr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/accountSummaryOnLogin')) {
                    $this->http->GetURL("https://www.barclaycardus.com/servicing/otp?getOtpEntryPageAsJSON=");
                    $response = $this->http->JsonLog();

                    if (isset($response->status, $response->data->page->globalAlert->alertBody) && $response->status == 'failure') {
                        if (strstr($response->data->page->globalAlert->alertBody, 'Please enter your SecurPass™ code.')) {
                            $this->AskQuestion($this->Question, 'Please enter the SecurPass™ code that we sent to you', 'SecurPass');
                            $this->logger->error("wrong SecurPass code was entered");

                            return false;
                        }// if (strstr($response->data->page->globalAlert->alertBody, 'Please enter your SecurPass™ code.'))
                    }// if (isset($response->status, $response->data->page->globalAlert->alertBody) && $response->status == 'failure')
                }// if (!strstr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/accountSummaryOnLogin'))

                $this->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary"); //todo: prevent fail when update, debug
            }// if (isset($this->State["QuestionType"]) && $this->State["QuestionType"] == 'SecurPass')
            else {// Just Question
                $this->logger->debug("Just Question");

                $questions = [];

                for ($n = 0; $n < 2; $n++) {
                    $question = ArrayVal($this->http->Form, "Question" . ($n + 1));

                    if ($question != '') {
                        $questions[] = $question;

                        if (!isset($this->Answers[$question])) {
                            $this->AskQuestion($question, null, "Question");

                            return false;
                        }// if (!isset($this->Answers[$question]))
                        $this->http->SetInputValue($this->http->Form["InputQuestion" . ($n + 1)], $this->Answers[$question]);

                        if (isset($this->http->Form['QuestionText' . ($n + 1)], $this->http->Form['QuestionCode' . ($n + 1)])) {
                            $this->http->SetInputValue('rsaForm.rsaQ' . ($n + 1) . 'Text', $this->http->Form['QuestionText' . ($n + 1)]);
                            $this->http->SetInputValue('rsaForm.rsaQ' . ($n + 1) . 'Id', $this->http->Form['QuestionCode' . ($n + 1)]);
                        }

                        // change form url
                        if (in_array($this->http->FormURL, ['https://www.barclaycardus.com/servicing/home?secureLogin=', 'https://www.barclaycardus.com/servicing/rsachallenge.action?showRsaChallengePage='])) {
                            $this->http->FormURL = 'https://www.barclaycardus.com/servicing/rsachallenge.action';
                        }

                        unset($this->http->Form["Question" . ($n + 1)]);
                        unset($this->http->Form["InputQuestion" . ($n + 1)]);
                        unset($this->http->Form["QuestionText" . ($n + 1)]);
                        unset($this->http->Form["QuestionCode" . ($n + 1)]);
                    }// if ($question != '')
                }// for ($n = 0; $n < 2; $n++)
                // user_page:homepageV2 ?
                $this->logger->debug("questions: " . var_export($questions, true));

                if (count($questions) != 2) {
                    return false;
                }
                $this->http->PostForm();
                $error = $this->http->FindSingleNode("//div[@id = 'alertBody']/p");
                $error2 = $this->http->FindSingleNode("(//span[@class = 'error'])[1]");
                // For security reasons, we have locked online access to your account.
                if ($message = $this->http->FindPreg("/<h1>(For security reasons, we have locked online access to your account\.)<\/h1>/")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // We apologize for the inconvenience, but we could not complete your request. Please try again.
                if ($message = $this->http->FindSingleNode("
                        //p[contains(text(), 'We apologize for the inconvenience, but we could not complete your request. Please try again.')]
                        | //div[contains(text(), 're sorry, but for security reasons online access to your account is unavailable.')]
                ")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // wrong answer
                if ((isset($error) && $this->http->FindPreg("/The answer\(s\) you entered did not match our records/ims", false, $error))
                    || (isset($error) && $this->http->FindPreg("/The answer you entered did not match our records/ims", false, $error))
                    || (isset($error2) && $this->http->FindPreg("/The answer you entered did not match our records/ims", false, $error2))
                    || (isset($error2) && $this->http->FindPreg("/Your answer must contain only letters, numbers, periods and spaces. No other characters are allowed./ims", false, $error2))) {
                    foreach ($questions as $question) {
                        unset($this->Answers[$question]);
                    }
                    $this->ParseQuestion(true); //false

                    return false;
                }// if (isset($error) && preg_match("/The answer\(s\) you entered did not match our records/ims", $error))
                $this->profileNeedToUpdate();
                // provider error
                if ($this->http->Response['code'] == 500
                    && ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience, but we could not complete your request. Please try again.')]"))) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                $this->retries();

//                // todo: old login form
//                if (!$this->SendPassword())
//                    return false;
            }// Just Question
        }// if ($this->AccountFields['Login2'] == 'USA')

        return true;
    }

    public function ParseUK()
    {
        $csrf = $this->http->getCookieByName("CSRF-TOKEN", ".barclaycard.co.uk", "/ecom/as2/");
        // get uiVersion
        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/uiVersion.do", [
            "_csrf"           => $csrf,
            "functionStartId" => 38,
        ]);
        $this->http->JsonLog();

        // System Error
        if ($message = $this->http->FindPreg('/<p>(We&#039;re sorry there was a problem - we were unable to process your request and as a precaution you have been logged out[^<]+)/')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->setDefaultHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/header.do", [
            "_csrf"      => $csrf,
            "apiVersion" => "1",
            "cardIndex"  => "",
        ]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // Name
        if (isset($response->user->userName)) {
            $this->SetProperty("Name", $response->user->userName);
        }

        if (!isset($response->accounts)) {
            // System Error
            if ($this->http->Response['code'] == 401
                && ($this->http->FindSingleNode('//strong[contains(text(), "We\'re sorry - we\'ve been unable to complete your log in request.")]'))
                || in_array($this->AccountFields["Login"], ['moishekruman1', '4929107389241004', 'andrewmchale81'])) {
                throw new CheckException("We're sorry there was a problem - we were unable to process your request and as a precaution you have been logged out.", ACCOUNT_PROVIDER_ERROR);
            }
            // Sorry, we are unable to display this account right now
            if ($this->http->Response['code'] == 200 && $this->http->FindSingleNode('//span[contains(text(), "Unfortunately we weren\'t able to carry out your request")]')) {
                throw new CheckException("Sorry, we are unable to display this account right now", ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/singleAccountSummary.do", [
            "_csrf"           => $csrf,
            "apiVersion"      => "1",
            "functionStartId" => "38",
            "inPageMessaging" => "true",
            "lozenge"         => "true",
            "messages"        => "false",
            "method"          => "getPanels",
            "offers"          => "true",
            "payYourBill"     => "true",
            "rewards"         => "true",
        ]);

        foreach ($response->accounts as $account) {
            $rewards = $this->http->JsonLog(null, 3, false, 'rewardType');

            if (isset($rewards->rewards->rewardType)
                && (isset($rewards->rewards->rewardPoints) || isset($rewards->rewards->rewardAmount) || $this->http->FindPreg("/\"rewards\":\{\"showRewardPanelFlag\":true,\"rewardType\":\"Cashback\",\"brandIndicator\":\"Barclaycard Cashback\",\"rewardPoints\":null,\"rewardAmount\":null\}/"))
                && (
                    strstr($account->accountName, $rewards->rewards->rewardType)
                    || ($account->accountName == 'Barclaycard Rewards' && $rewards->rewards->rewardType == 'Cashback')
                )
            ) {
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => 'barclaycardUK' . $account->accountLastDigits,
                    "DisplayName"     => $account->accountName . " (Card ending with " . $account->accountLastDigits . ")",
                    "CardDescription" => C_CARD_DESC_ACTIVE,
                ]);
                $balance = $rewards->rewards->rewardPoints ?? $rewards->rewards->rewardAmount;
                // AccountID: 3813842
                if (!$balance && $this->http->FindPreg("/\"rewards\":\{\"showRewardPanelFlag\":true,\"rewardType\":\"Cashback\",\"brandIndicator\":\"Barclaycard Cashback\",\"rewardPoints\":null,\"rewardAmount\":null\}/")) {
                    $balance = 0;
                }
                $this->AddSubAccount([
                    "Code"        => 'barclaycardUK' . $account->accountLastDigits,
                    "DisplayName" => $account->accountName . " (Card ending with " . $account->accountLastDigits . ")",
                    "Balance"     => $balance,
                    'Currency'    => ($rewards->rewards->rewardType == 'Cashback') ? "$" : null,
                ]);
                $this->SetBalanceNA();
            } else {
                // Detected cards
                $this->AddDetectedCard([
                    "Code"            => 'barclaycardUK' . $account->accountLastDigits,
                    "DisplayName"     => $account->accountName . " (Card ending with " . $account->accountLastDigits . ")",
                    "CardDescription" => C_CARD_DESC_DO_NOT_EARN,
                ]);

                if (isset($rewards->rewards->rewardType) && in_array($rewards->rewards->rewardType, ['NO_REWARD_TYPE', 'Avios'])) {
                    $this->SetBalanceNA();
                }
            }
        }// foreach ($response->accounts as $account)

        return true;
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'UK') {
            return $this->ParseUK();
        }

        $this->logger->debug("[CurrentURL]: {$this->http->currentUrl()}");

        $this->retries();

        // Validate your profile information
        if ((strstr($this->http->currentUrl(), 'profileInterstitial') || $this->http->FindSingleNode("//a[@id = 'remindLaterProfile']/@href"))
            && $this->http->ParseForm("addressIncomeForm")) {
            $this->logger->debug(">>> Skip 'Validate your profile information'");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/profileInterstitial?remindLater=");
        }// if (strstr($this->http->currentUrl(), 'profileInterstitial') && $this->http->ParseForm("addressIncomeForm"))

        if ($this->http->FindSingleNode("//form[@id = 'eConsentLandingForm']")) {
            $this->logger->debug(">>> skip Help us reach you faster!");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary");
        }

        if (
            strstr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/returnMailInterstitial')
            || strstr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/econsentInterstitial')
        ) {
            $this->logger->debug(">>> Skip profile update");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary");
        }

        // Your account is currently past due
        if (strstr($this->http->currentUrl(), 'assistanceInterstitial')) {
            $this->logger->debug(">>> Skip 'Your account is currently past due'");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/assistanceInterstitial?remindMeLater=");
        }// if (strstr($this->http->currentUrl(), 'assistanceInterstitial'))
        // Validate your information
        if (stristr($this->http->currentUrl(), 'kycInterstitial') || $this->http->FindSingleNode("//a[@id = 'remindLaterRemoveAu' or @id = 'remindLaterProfile']/@href")
            && $this->http->FindSingleNode("//h1[contains(text(), 'Validate your information')]")) {
            $this->logger->debug(">>> Skip 'Validate your information'");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/accountSummary");
        }// if (stristr($this->http->currentUrl(), 'kycInterstitial') && $this->http->FindSingleNode("//h1[contains(text(), 'Validate your information')"))

        if (stristr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/onboarding')) {
            $this->logger->debug(">>> Skip Offer");
            $this->http->GetURL("https://www.barclaycardus.com/servicing/onboarding?getAccountSummaryResolution");
        }// if (stristr($this->http->currentUrl(), 'https://www.barclaycardus.com/servicing/onboarding'))

        // Name
        $this->SetProperty("Name", $this->http->FindSingleNode("//span[contains(text(), 'Welcome,') or contains(text(), 'Aloha,')] | //p[@class = 'b-greeting']", null, true, "/,\s*([^\!\.<]+)/ims"));

        $this->parseTab();
        $otherTabs = $this->http->FindNodes("//div[@id = 'tabs']//div[@class = 'tabunselected']//a/@href");

        foreach ($otherTabs as $link) {
            if ($this->http->GetURL($link)) {
                $this->parseTab();
            }
        }
        $links = $this->http->FindNodes("//a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'ewards')]]/@href");

        if (count($links) == 0) {
            $links = $this->http->FindNodes("//a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'Extra points')]]/@href");
        }

        if (count($links) == 0) {
            $links = $this->http->FindNodes("//a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=') and h3[contains(text(), 'Carnival')]]/@href");
        }

        if (count($links) == 0) {
            $links = $this->http->FindNodes("//a[contains(@href, '/app/ccsite/action/cardSelector?selectedID=')]/@href");
        }

        if (count($links) == 0) {
            $links = $this->http->FindNodes("//a[contains(@href, 'SwitchAccount.action?accountId=')]/@href");

            foreach ($links as &$link) {
                $this->http->NormalizeURL($link);
            }
        }

//        $links[] = "https://www.barclaycardus.com/servicing/legacy";
        $this->logger->debug(var_export($links, true), ["pre" => true]);
        $links = array_unique($links);
        $this->logger->debug(var_export($links, true), ["pre" => true]);
        // filter links
        foreach ($links as &$link) {
            $pos = strpos($link, '&');

            if ($pos) {
                $link = substr($link, 0, strpos($link, '&'));
            }
        }// foreach ($links as &$link)
        $this->logger->debug(var_export($links, true), ["pre" => true]);
        $links = array_unique($links);
        $this->logger->debug(var_export($links, true), ["pre" => true]);

        $closed = false;
        unset($link);

        foreach ($links as $link) {
            // refs #6178
            //# This account is closed
            if ($link == "https://www.barclaycardus.com/servicing/legacy" && $this->ErrorCode == ACCOUNT_ENGINE_ERROR
                && ($message = $this->http->FindSingleNode("//h1[contains(text(), 'This account is closed')]"))) {
                $closed = true;
            }
            // skip less link
            if (strstr($link, '&redirectAction=/messageCenter')) {
                $this->logger->notice("skip less link -> " . $link);

                continue;
            }

            if (strstr($link, '&rnd=') && !$this->newDesign) {
                $this->logger->notice("skip less link -> " . $link);

                continue;
            }// if (strstr($link, '&rnd=') && !$this->newDesign)
            $this->parseCard($link);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if ($this->http->FindSingleNode("//span[@id = 'lastlogin']") !== null
                || $this->http->FindPreg('/Email Address/ims') !== null) {
                $this->SetBalanceNA();
            }
            //# This account is closed   // refs #6178
            elseif ($closed && isset($message)) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // We apologize for the inconvenience, but we could not complete your request. Please try again.
            elseif ($message = $this->http->FindSingleNode("//p[contains(text(), 'We apologize for the inconvenience, but we could not complete your request. Please try again.')]")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        // refs #11309, 14505
        $this->logger->info('FICO® Score', ['Header' => 3]);
        $this->http->GetURL("https://www.barclaycardus.com/servicing/ficoScore?start");
        // FICO® SCORE
        $fcioScore = $this->http->FindPreg("/var\s*num\s*=\s*([^\;]+)/ims");
        // FICO Score updated on
        $fcioUpdatedOn = $this->http->FindSingleNode("//div[@id = 'lastUpdated']", null, true, "/last \s*updated\s*(?:on\s*|)([^<\.]+)/ims");

        if ($fcioScore && $fcioUpdatedOn) {
            if (isset($this->Properties['SubAccounts']) && count($this->Properties['SubAccounts']) == 1) {
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
                "Code"               => "barclaycardFICO",
                "DisplayName"        => "FICO® Score (TransUnion)",
                "Balance"            => $fcioScore,
                "FICOScoreUpdatedOn" => $fcioUpdatedOn,
            ]);
        }// if ($fcioScore && $fcioUpdatedOn)

        // refs #14720
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->State['Success'] = true;
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)

        return true;
    }

    public function parseCard($link)
    {
        $this->logger->debug("loading card $link");
        $this->http->GetURL($link);
        $this->parseTab();
    }

    public function parseTab()
    {
        $tabName = $this->http->FindSingleNode("//div[@id = 'tabs']//div[@class = 'tabselected']//a");

        if (!isset($tabName)) {
            $tabName = $this->http->FindSingleNode("//h1[@class = 'cardName']");
        }
        // new design
        if (!isset($tabName)) {
            $tabName = $this->http->FindSingleNode("//span[@class = 'b-card-name']");

            if (isset($tabName)) {
                $this->newDesign = true;
            }
        }// if (!isset($tabName))
        $this->logger->debug("tabName: " . $tabName);

        if ($tabName == 'Barclaycard Arrival') {
            $tabName = 'Barclaycard Arrival™ MasterCard®';
        }

        if ($tabName == 'Barclaycard Rewards') {
            $tabName = 'Barclaycard® Rewards MasterCard®';
        }

        if (isset($tabName)) {
            $this->logger->info($tabName, ['Header' => 3]);
        }

        $rewardLink = $this->http->FindPreg("/href=\"([^\"]+)\" id=\"rewards\"/");
        $rewardText = Html::cleanXMLValue($this->http->FindPreg("/<div class=\"tabAction[^\"]+\">\s*([^<]+)<\/div>/ims"));
        $this->logger->debug("rewardText: " . $rewardText);
        // Card ending in ...
        $ending = $this->http->FindSingleNode("//div[@class = 'cardNum']");
        $this->logger->debug("ending: " . $ending);

        $notActive = $this->http->FindPreg("/Activate your account/");

        if (
            !$notActive
            && !strstr($tabName, 'AAdvantage')
            && !strstr($tabName, 'JetBlue')
            && !strstr($tabName, 'Wyndham Rewards')
        ) {
            $http2 = clone $this;
            $http2->http->GetURL("https://www.barclaycardus.com/servicing/jserv/rewardsTile/?_=" . time() . date("B"));

            // We're sorry, but we're currently upgrading our Website.
            if ($this->http->Response['code'] == 503 && ($message = $this->http->FindSingleNode('//b[contains(text(), "We\'re sorry, but we\'re currently upgrading our Website.")]'))) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            } elseif ($this->http->Response['code'] == 500 && $this->http->FindPreg("/Exception occurred while processing the request'/")) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $response = $http2->http->JsonLog(null, 3, true);
            $balance = ArrayVal($response, 'rewardsBalance');
            $this->logger->debug("Balance 1 (from main page) -> " . $balance);

            $this->retries($http2);
        }// if (!$this->http->FindPreg("/Activate your account/"))

        if (
            (
                (
                    !empty($rewardText)
                    && $rewardText != 'VisitUpromise.com View rewards'
                    && $rewardLink != 'Rewards.action?boostRewards'
                )
                || (
                    $this->newDesign
                    && !strstr($tabName, 'AAdvantage')
                    && !strstr($tabName, 'JetBlue')
                    && !strstr($tabName, 'Wyndham Rewards')
                )
            )
            && !$notActive
        ) {
            $this->http->GetURL("https://www.barclaycardus.com/servicing/Rewards.action?rnd=" . time() . date("B"));
            $this->http->SetBody($this->http->Response['body'], true);
            $balance2 = $this->GetBalance();
            $this->logger->debug("Balance 1 (from main page) -> " . $balance);
            $this->logger->debug("Balance 2 (from details) -> " . $balance2);

            if (!isset($balance)) {
                $balance = $balance2;
            }

            $this->retries();
        }
        // improve DisplayName card
        if (isset($ending)) {
            $tabName = $tabName . " ({$ending})";
        }
        $this->logger->debug("tabName: " . $tabName);

        $closed = ($this->http->FindPreg("/This account is closed./ims")) ? true : false;

        if (isset($tabName) && isset($balance) && $balance != '' && !$closed) {
            if (isset($balance) && strpos($balance, '$') !== false) {
                $currency = '$';
            } else {
                $currency = null;
            }
            // adding SubAccount
            $lastStatement = $this->http->FindSingleNode("//dt[contains(text(), 'Miles earned last statement')]/following::dd[1]");

            if (!isset($lastStatement)) {
                $lastStatement = $this->http->FindSingleNode("//p[contains(text(), 'Earned since last statement')]/preceding-sibling::p[1]");
            }

            if (!isset($lastStatement)) {
                $lastStatement = $this->http->FindSingleNode("//p[contains(text(), 'Earned last statement')]/preceding-sibling::p[1]");
            }
            $this->AddSubAccount([
                "Code"          => 'barclaycard' . md5($tabName),
                "DisplayName"   => $tabName,
                "Balance"       => $balance,
                // for US Airways cards
                "LastStatement" => $lastStatement,
                'Currency'      => $currency,
            ]);
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => 'barclaycard' . md5($tabName),
                "DisplayName"     => $tabName,
                "CardDescription" => C_CARD_DESC_ACTIVE,
            ]);

            if ($this->ErrorCode != ACCOUNT_CHECKED) {
                $this->SetBalanceNA();
            }
        }// if (isset($tabName) && isset($balance) && $balance != '' && !$closed && !strstr($rewardText, 'AAdvantage'))
        // cards without balance
        elseif (isset($tabName) && (!isset($balance) || $closed)) {
            $this->logger->notice("Balance not found");
            // This account is closed
            if ($closed) {
                $cardDescription = C_CARD_DESC_CLOSED;
            }
            // if needed to visit Upromise.com
            elseif ($rewardText == 'VisitUpromise.com View rewards') {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Sallie Mae', 117], C_CARD_DESC_UNIVERSAL);
            } elseif (strstr($tabName, 'JetBlue')) {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['JetBlue Airways (TrueBlue)', 13], C_CARD_DESC_UNIVERSAL);
            } elseif (strstr($tabName, 'Wyndham Rewards')) {
                $cardDescription = str_replace(['[Program]', '[Program_ID]'], ['Wyndham Rewards (Days Inn, etc.)', 15], C_CARD_DESC_UNIVERSAL);
            } elseif (strstr($tabName, 'AAdvantage')) {
                $cardDescription = C_CARD_DESC_AA;
            } else {
                $cardDescription = C_CARD_DESC_DO_NOT_EARN;
            }
            // Detected cards
            $this->AddDetectedCard([
                "Code"            => 'barclaycard' . md5($tabName),
                "DisplayName"     => $tabName,
                "CardDescription" => $cardDescription,
            ], true);
            $this->SetBalanceNA();
        } else {
            $this->logger->notice("Balance not found");
        }

        //# Activate your account
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR
            && ($message = $this->http->FindSingleNode("//span[contains(text(), 'To start using your card, please activate your account right away.')]"))) {
            $this->SetWarning("Your account is not activated");
        }
    }

    public function GetBalance()
    {
        $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Rewards')]/following::td[1]");

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'My Rewards')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'Reward Points')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'Reward Points')]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My FunPoints')]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[contains(text(), 'My Princess Rewards')]]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Travelocity Points')]/following::td[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[a[@href = 'https://www.barclaycardus.com/app/ccsite/action/awardsView']]/following::td[1]");
        }
        // US Airways cards
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Miles earned so far this billing cycle')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Total Points available')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Total Travelocity Points available')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Current Coupon Dollars')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Current') and contains(text(), 'iTunes points')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Rewards Points') and contains(text(), 'ready to spend')]", null, false, "/You\s+have\s+([\d\,\.\$\-]+)\s+Rewards\s+Points\s+ready\s+to\s+spend/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//dt[contains(text(), 'Points') and contains(text(), 'earned so far this billing cycle')]/following::dd[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//div[contains(@class, 'rewardsTxt floatLeft')]/span");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//td[contains(text(), 'My Miles &amp More Miles')]/following-sibling::td[1]");
        }
        // new design
        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Current miles')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Current points')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Earned so far this billing cycle')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Points Earned')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindSingleNode("//p[contains(text(), 'Coupon Dollars Earned')]/preceding-sibling::p[1]");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/You have\s*([\d\.\,]+)\s*points\s*ready\s*to\s*spend/ims");
        }

        if (!isset($balance)) {
            $balance = $this->http->FindPreg("/You have\s*([\d\.\,]+)\s*miles\s*ready\s*to\s*spend/ims");
        }

        return $balance;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (isset($properties['Currency'])) {
            return $properties['Currency'] . formatFullBalance($fields['Balance'], $fields['ProviderCode'], $fields['BalanceFormat']);
        } else {
            return parent::FormatBalance($fields, $properties);
        }
    }

    public function ParseFiles($filesStartDate)
    {
        if ($this->AccountFields['Login2'] == 'UK') {
            return [];
        }

        $this->http->TimeLimit = 500;
        $this->http->GetURL("https://www.barclaycardus.com/servicing/mystatements");
        $cards = [];

        foreach ($this->http->XPath->query("//a[contains(@href, 'SwitchAccount.action?accountId=') and contains(text(), 'Card ending')]") as $node) {
            /** @var DOMNode $node */
            $cards[$node->attributes->getNamedItem('href')->nodeValue] = [
                "title" => CleanXMLValue($node->nodeValue),
                "href"  => $node->attributes->getNamedItem('href')->nodeValue,
            ];
        }
        $result = [];

        do {
            if (count($cards) > 0) {
                $card = array_pop($cards);
                $this->logger->debug("loading card " . var_export($card, true));
                $this->http->GetURL("https://www.barclaycardus.com/servicing/" . $card["href"]);
                $this->http->GetURL("https://www.barclaycardus.com/servicing/mystatements");
            } else {
                $card = null;
            }
            $options = $this->http->XPath->query("//select[@id = 'statementsSelect']/option");
            $files = [];
            $cardName = "";

            foreach ($options as $option) {
                /** @var DOMNode $option */
                $file = [
                    'title' => CleanXMLValue($option->nodeValue),
                    'id'    => $option->attributes->getNamedItem('value')->nodeValue,
                ];
                $this->logger->debug("node: {$file['title']}, {$file['id']}");
                $files[] = $file;
            }

            foreach ($files as $file) {
                $this->logger->debug("downloading {$file['title']}, {$file['id']}");
                $date = null;

                if (preg_match('#^\d\d/\d\d/\d\d#ims', $file['title'], $matches)) {
                    $date = strtotime($matches[0]);
                }

                if (preg_match('#^\d\d\d\d#ims', $file['title'], $matches)) {
                    $date = mktime(0, 0, 0, 12, 31, $matches[0]);
                }
                $code = null;

                if (!empty($card['title']) && preg_match('#\d\d\d\d$#ims', $card['title'], $matches)) {
                    $code = $matches[0];
                }

                if (preg_match('#\d\d\d\d$#ims', $file['title'], $matches)) {
                    $code = $matches[0];
                }

                if (intval($date) >= $filesStartDate) {
                    $fileName = $this->http->DownloadFile("https://www.barclaycardus.com/servicing/mystatements?getStatement=DownloadPDF&documentId=" . $file['id']);

                    if (strpos($this->http->Response['body'], '%PDF') === 0) {
                        $result[] = [
                            "FileDate"      => $date,
                            "Name"          => $file["title"],
                            "Extension"     => "pdf",
                            "AccountNumber" => $code,
                            "AccountName"   => !empty($card) ? $card['title'] : '',
                            "AccountType"   => '',
                            "Contents"      => $fileName,
                        ];
                    } else {
                        $this->logger->debug("not a PDF");
                    }
                } else {
                    $this->logger->debug("skip by date");
                }
            }
        } while (count($cards) > 0);

        return $result;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("/'sitekey' : '([^\']+)/");

        if (!$key) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => "1",
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }

    private function profileNeedToUpdate()
    {
        $this->logger->notice(__METHOD__);

        if ((strstr($this->http->currentUrl(), 'interstitial')
                && $this->http->FindSingleNode("//form[@id = 'interstitialPaperlessForm']"))
            || ($this->http->FindSingleNode("//img[contains(@src, 'paperless_treadlightly.png')]/@src") && $this->http->FindSingleNode("//form[@id = 'interstitialPaperlessForm']"))
            || ($this->http->FindSingleNode("//h2[contains(text(), 'Retrieve your username and reset your password.')]"))
            // Fraud alerts sent to your mobile phone!
            || strstr($this->http->currentUrl(), 'smsOptinTakeover')
        ) {
            $this->throwProfileUpdateMessageException();
        }
    }

    private function entering2fa($question)
    {
        $this->logger->notice(__METHOD__);
        $data = [
            "mobileNumberId" => $this->State['mobileNumberId'],
            "challengeO"     => base64_encode($this->Answers[$question]),
        ];
        unset($this->Answers[$question]);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/verifyOTV", json_encode($data), $this->State["headers"]);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        // {"error":{"code":"FAILED_1","additionalParameters":[{"name":"isOTPEnabled","value":true}]}}
        if (isset($response->error->code)) {
            switch ($response->error->code) {
                case 'FAILED_1':
                    unset($this->Answers[$question]);
                    unset($this->Answers[self::SECURITY_QUESTION_UK]);
                    $this->AskQuestion($question, "You\'ve put in the wrong details. Please check and try again.", 'QuestionCVV');

                    break;
            }

            return false;
        }

        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/deeplinking.do", ["_csrf" => $this->State["headers"]['X-CSRF-TOKEN']]);

        return true;
    }

    private function checkSendingCode()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://www.barclaycardus.com/servicing/otp?getOtpEntryPageAsJSON=");
        $response = $this->http->JsonLog();

        if (isset($response->status) && $response->status == 'success') {
            $this->logger->debug("code was sent");

            return true;
        }// if (isset($response->status) && $response->status == 'success')
        else {
            $this->logger->debug("something went wrong");
            $this->retries();

            return false;
        }
    }

    private function enteringCVV($question)
    {
        $this->logger->notice(__METHOD__);
        $this->headersUK = $this->State["headers"];
        // Enter the 3-digit security code (CVV) from the back of your Barclaycard ending **** **** **** ...
        $data = [
            "cardNumber" => $this->State['cardNumber'],
            "challengeC" => base64_encode($this->Answers[$question]),
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/api/login/verifyCardCredentials", json_encode($data), $this->headersUK);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
//        {"error":{"code":"FAILED_1","additionalParameters":[{"name":"isOTPEnabled","value":true}]}}
        if (isset($response->error->code)) {
            switch ($response->error->code) {
                case 'FAILED_1':
                    unset($this->Answers[$question]);
                    unset($this->Answers[self::SECURITY_QUESTION_UK]);
                    $this->AskQuestion($question, "You\'ve put in the wrong details. Please check and try again.", 'QuestionCVV');

                    break;
            }

            return false;
        }

//        if (isset($response->tAndCFlag) && $response->tAndCFlag == false) {
        $this->http->PostURL("https://{$this->ukHost}/ecom/as2/deeplinking.do", ["_csrf" => $this->headersUK['X-CSRF-TOKEN']]);
//        }

        return true;
    }

    private function retries($browser = null)
    {
        $this->logger->notice(__METHOD__);

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            return false;
        }

        if (!$browser) {
            $browser = $this;
        }
        $currentUrl = $browser->http->currentUrl();
        // retries
        if ($currentUrl == 'https://www.barclaycardus.com/servicing/authenticate' && $browser->http->ParseForm("homePageLoginForm")) {
            $this->logger->debug("[attempt]: {$this->attempt}");
            $this->logger->error("session was lost after entering answers, retry");

            if ($this->attempt == 4) {
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }
            }// if ($this->attempt == 4)

            throw new CheckRetryNeededException(5, 10);
        }

        if ((
                $currentUrl == 'https://www.barclaycardus.com/servicing/home?redirectAction=%2FaccountSummary'
                || $currentUrl == 'https://www.barclaycardus.com/servicing/authenticate?showSecureLoginPage'
                || $this->http->FindPreg("/^https:\/\/www.barclaycardus\.com\/servicing\/authenticate\?redirectreasoncode=([^<]+)\&showSecureLoginPage=$/", false, $currentUrl)
            )
            && $browser->http->ParseForm("loginSecureLoginForm")
        ) {
            $this->logger->debug("[attempt]: {$this->attempt}");
            $this->logger->error("retry, session was lost");

            if ($this->attempt == 4) {
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }
            }// if ($this->attempt == 4)

            throw new CheckRetryNeededException(5, 10);
        }
        // session was lost
        if (stristr($currentUrl, 'https://www.barclaycardus.com/servicing/home?redirectAction=%2FRewards.action&rnd=')
            && $browser->http->FindSingleNode("//h3[contains(text(), 'Enter your username and password')]") && $browser->http->ParseForm("loginSecureLoginForm")) {
            $this->logger->debug("[attempt]: {$this->attempt}");
            $this->logger->error("session was lost, retry");

            if ($this->attempt == 4) {
                // prevent code spam    // refs #6042
                if ($this->isBackgroundCheck()) {
                    $this->Cancel();
                }
            }// if ($this->attempt == 4)

            throw new CheckRetryNeededException(5, 10);
        }
    }
}
