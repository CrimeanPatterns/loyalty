<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\british\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerBritish extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;
    public const REG_EXP_PAGE_HISTORY_LINK = '/var\s*replaceURL\s*=\s*(?:\"|\')([^\"\']*)(?:\"|\')/ims';
    public const XPATH_PAGE_HISTORY = "//div[@class='info-detail-main-transaction-row']";

    protected $collectedHistory = false;

    protected $tryOldForm = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    private $link;
    private $airCodes;
    private $activityIgnore = [
        "Expired Avios",
        "Points Reset for New Membership Year",
        "Combine My Avios",
        "Manual Avios Adjustment",
        "Redemption Redeposit",
        "Avios Adjustment",
        "Tier Points Adjustment",
    ];
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->setProxyGoProxies();

        $this->http->setHttp2(false);
        // refs #13486
        //$this->setProxyBrightData();

        $this->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->logger->info('chosenResolution:');
        $this->logger->info(var_export($chosenResolution, true));
        $this->setScreenResolution($chosenResolution);

        // if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
        //     $this->useCache();// Cause of appearance of page 'The page you requested cannot be found.'
        if ($this->attempt !== 1 || true) { // TODO: useChromePuppeteer broken now
            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
            $this->seleniumOptions->addHideSeleniumExtension = false;
        } else {
            $this->useChromePuppeteer();
        }

        $this->seleniumOptions->userAgent = null;
        $this->usePacFile(false);

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = HttpBrowser::BROWSER_VERSION_MIN;
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($fingerprint)) {
            $this->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
            $this->http->setUserAgent($fingerprint->getUseragent());
        }

        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.britishairways.com/travel/viewaccount/execclub/_gf/en_us", [], 20);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//p[@class='membership-details']/span[@class='personaldata']")) {
            return true;
        }

        return false;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $result = Cache::getInstance()->get('british_countries_1');

        if (($result !== false) && (count($result) > 1)) {
            $arFields["Login2"]["Options"] = $result;
        } else {
            $arFields["Login2"]["Options"] = [
                "" => "Select a region",
            ];
            // Proxy
            $browser = new HttpBrowser("none", new CurlDriver());
//            $browser->SetProxy($this->proxyStaticIpDOP());
            $browser->GetURL("https://www.britishairways.com/travel/country_choice/public/en_us");
            $nodes = $browser->XPath->query("//select[@id = 'countrycode']/option");

            for ($n = 0; $n < $nodes->length; $n++) {
                $country = Html::cleanXMLValue($nodes->item($n)->nodeValue);
                $code = Html::cleanXMLValue($nodes->item($n)->getAttribute("value"));

                if ($country != "" && $code != "") {
                    $arFields['Login2']['Options'][$code] = $country;
                }
            }

            if (count($arFields['Login2']['Options']) > 1) {
                Cache::getInstance()->set('british_countries_1', $arFields['Login2']['Options'], 3600 * 24);
            } else {
                if (!$browser->FindSingleNode("
                    //p[contains(text(), 'Sorry, our website is unavailable while we make a quick update to our systems.')]
                    | //p[contains(text(), 'Both ba.com and our apps are temporarily unavailable while we make some planned improvements to our systems.')]
                ")) {
                    $this->sendNotification("Regions aren't found", 'all', true, $browser->Response['body']);
                }
                $arFields['Login2']['Options'] = array_merge($arFields['Login2']['Options'], TAccountCheckerbritish::BritishRegions());
            }
        }
    }

    public function LoadLoginForm()
    {
        $this->Answers = [];
        $this->http->removeCookies();
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $countryCode = $this->getCountryCode();

        // AccountID: 308446
        if (strstr($this->AccountFields['Pass'], '❺❽❼❽❽❻❷❻')) {
            throw new CheckException("The username and password do not match what is held on our system", ACCOUNT_INVALID_PASSWORD);
        }

        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_' . $countryCode, [], 30);

        // fix Ireland region
        if ($countryCode == 'ie'
                && $this->http->currentUrl() == 'https://www.britishairways.com/en-gb/traveltrade') {
            $this->logger->notice('fix Ireland region');
            $this->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_gb');
        }

        $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 7);

        if (
            !$loginInput
            && ($agreeBtn = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Agree to all cookies") or @aria-label="Accept all cookies" or @id = "ensAcceptAll"]'), 0))
        ) {
            $this->saveResponse();
            $agreeBtn->click();
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 7);
        }

        if (
            !$loginInput
            && ($headerLoginBtn = $this->waitForElement(WebDriverBy::xpath('//span[contains(text(), "Log in")]'), 3))
        ) {
            $this->saveResponse();
            $headerLoginBtn->click();
            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 7);
        }

        // login
        if (!$loginInput) {
            $this->logger->error("something went wrong");
            $this->saveResponse();
            // retries
            if ($this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.')]"), 0)) {
                $this->DebugInfo = "Request has been blocked";
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $retry = true;
            } else {
                $retry = $this->doRetry();

                if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")] | //body[contains(text(), "An error (502 Bad Gateway) has occurred in response to this request.")]')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
            // freezing script workaround
            if (strstr($this->http->currentUrl(), 'https://www.britishairways.com/travel/loginr/public')
                    && $this->http->FindPreg("/<noscript>Please enable JavaScript to view the page content.<\/noscript>/")) {
                $retry = true;
            }

            if ($this->http->FindPreg("/^<head><\/head><body><\/body>$/")) {
                $retry = true;
            }

            return $this->checkErrors();
        }// if (!$loginInput)

        if ($ensCloseBanner = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ensCloseBanner"]'), 0)) {
            $ensCloseBanner->click();
            $this->saveResponse();
        }

        $this->logger->notice("js injection");

        try {
            $this->driver->executeScript("
                $('#ensCloseBanner').click();
                $('input[name=membershipNumber], input#loginid, input#username').val('{$this->AccountFields['Login']}');
                $('input[name=password], input#password').val('" . str_replace(["\\", "'"], ["\\\\", "\'"], $this->AccountFields['Pass']) . "');");
        } catch (UnexpectedJavascriptException | WebDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

            $loginInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "username"]'), 0);
            $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $btn = $this->waitForElement(WebDriverBy::xpath('//button[@name="action"]'), 0);

            if (!$loginInput || !$passInput || !$btn) {
                return $this->checkErrors();
            }

            $this->saveResponse();
            $mover = new MouseMover($this->driver);
            $mover->logger = $this->logger;
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
//            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->sendKeys($this->AccountFields['Pass']);

            $captcha = $this->parseCaptcha();

            if ($captcha !== false) {
                $this->driver->executeScript("document.querySelector('iframe[data-hcaptcha-response]').setAttribute('data-hcaptcha-response', '{$captcha}');");
                $this->driver->executeScript("document.querySelector('[name=\"g-recaptcha-response\"]').value = '{$captcha}';");
                $this->driver->executeScript("document.querySelector('[name=\"h-captcha-response\"]').value = '{$captcha}';");
                $this->driver->executeScript("document.querySelector('input[name=\"captcha\"]').value = '{$captcha}';");
            }

            $this->saveResponse();
            $btn->click();
        }

        $this->driver->executeScript("var remember = document.getElementById('rememberMe'); if (remember) remember.checked = true;");
        $this->saveResponse();

        return true;
    }

    public function doRetry()
    {
        $this->logger->notice(__METHOD__);
        // retries
        if ($this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), 'This page isn’t working')]")
            || $this->http->FindSingleNode("//*[self::h1 or self::span][contains(text(), \"Your connection was interrupted\")]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'The page you requested cannot be found.')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Error 404--Not Found')]")
            || $this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|An error \(502 Bad Gateway\) has occurred in response to this request\.)/ims')
            || $this->http->FindPreg('/<h2>Error 404--Not Found<\/h2>/ims')
            /*|| !$this->http->FindSingleNode("//body")*/) {
            return true;
        }

        return false;
    }

    public function UpdateGetRedirectParams(&$arg)
    {
        $countryCode = $this->getCountryCode();
        $arg['RedirectURL'] = 'https://www.britishairways.com/travel/home/public/en_' . $countryCode;
    }

    public function getCountryCode()
    {
        if (!isset($this->AccountFields['Login2']) || $this->AccountFields['Login2'] == '' || strlen($this->AccountFields['Login2']) > 2) {
            $countryCode = 'us';
        } else {
            $countryCode = strtolower($this->AccountFields['Login2']);
        }

        return $countryCode;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Sorry, our website is unavailable while we make a quick update to our systems.
        if ($message = $this->http->FindSingleNode("
                //p[contains(text(), 'Sorry, our website is unavailable while we make a quick update to our systems.')]
                | //p[contains(text(), 'Both ba.com and our apps are temporarily unavailable while we make some planned improvements to our systems.')]
            ")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System Upgrade
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to the Executive Club System Upgrade you will experience limited access to your account')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We regret to advise that this section of the site is temporarily unavailable.
        if ($message = $this->http->FindPreg("/(We regret to advise that this section of the site is temporarily unavailable\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unfortunately our systems are not responding
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'Unfortunately our systems are not responding')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently carrying out site maintenance between ...
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently carrying out site maintenance')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There is currently no access to your account while we upgrade our system
        if ($message = $this->http->FindSingleNode("//li[contains(text(),'There is currently no access to your account while we upgrade our system')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a technical problem. Please try again in a few minutes.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing technical issues today with our website.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing technical issues today with our website.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Sorry, there seems to be a technical problem. Please try again in a few minutes, and please contact us if it still doesn't work.
         * We apologise for the inconvenience.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Major IT system failure . latest information at 23.30 Saturday May 27
         *
         * Following the major IT system failure experienced throughout Saturday,
         * we are continuing to work hard to fully restore all of our global IT systems.
         *
         * Flights on Saturday May 27
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Following the major IT system failure experienced throughout')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Internal Server Error - Read
            $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindPreg("/An error occurred while processing your request\./")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $responseCode = $this->http->Response['code'] ?? null;
        $this->logger->debug("[CODE]: " . $responseCode);
        // retries
        if (in_array($responseCode, [0, 301, 302, 403])
            || ($responseCode == 200 && empty($this->http->Response['body']))) {
            if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new CheckRetryNeededException(3, 5);
        // error in selenium
        } elseif (
            $responseCode == 200
            && $this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further") or contains(text(), "We are experiencing high demand on ba.com at the moment.")]')
        ) {
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        }

        $this->logger->debug('[checkErrors. date: ' . date('Y/m/d H:i:s') . ']');

        return false;
    }

    public function Login()
    {
        // Sign In
        $this->waitFor(function () {
            $timeout = 0;

            if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ecuserlogbutton"]'), 0)) {
                $this->saveResponse();
                $this->logger->debug('btn->click');
                $btn->click();
                $timeout = 7;
            }

            if ($btn = $this->driver->findElement(WebDriverBy::xpath('//button[@id = "ensAcceptAll"]'))) {
                $this->logger->notice("close cookies popup");
                $this->logger->debug('ensAcceptAll click');
                $btn->click();
                sleep(7);
                $this->saveResponse();
            }

            return $this->waitForElement(WebDriverBy::xpath('
                //li[@class = "logout"]/a[@class = "logOut" and normalize-space() = "Log out"]
                | //input[@id="membershipNumber"]
                | //div[contains(@class, "warning") and not(@hidden)]
                | //p[contains(text(), "Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.")]
                | //h3[contains(text(), "We need to confirm your identity")]
                | //p[contains(text(), "We\'ve sent an email with your code")]
                | //p[contains(text(), "We\'ve sent a text message to")]
                | //p[contains(text(), "Check your preferred one-time password application for a code")]
                | //p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]
                | //h3[contains(text(), "We have updated our Terms and Conditions.")]
                | //p[contains(text(), "Sorry we can\'t show you this page at the moment.")]
                | //span[contains(text(), "This site can’t be reached")]
                | //span[@jsselect="heading" and contains(text(), "This page isn’t working")]
                | //span[contains(text(), "No internet")]
                | //body[contains(text(), "An error (502 Bad Gateway) has occurred in response to this request.")]
                | //p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]
                | //span[contains(text(), "This site can’t be reached")]
                | //span[contains(text(), "This page isn’t working")]
                | //span[contains(text(), "Your connection was interrupted")]
                | //h1[contains(text(), "Welcome to your Executive Club")]
                | //h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]
                | //span[contains(@class, "ulp-input-error-message")]
                | //div[@id = "prompt-alert"]/p
                | //h1[contains(text(), "Sorry, we couldn\'t log you in")]
                | //pre[contains(text(), "Bad Request")]
                | //p[contains(text(), "We are experiencing high demand on ba.com at the moment.")]
                | //p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]
                | //button[contains(., "Try another method")]
            '), $timeout);
        }, 50);
        $this->saveResponse();

        if ($this->http->FindSingleNode("//p[contains(text(), 'Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.')] | //pre[contains(text(), 'Bad Request')]")) {
            $this->DebugInfo = "Request has been blocked";
            $this->ErrorReason = self::ERROR_REASON_BLOCK;
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 3);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Sorry, we couldn\'t log you in")]')) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "We\'ve sent a text message to")] | //p[contains(text(), "Check your preferred one-time password application for a code")]')) {
            $this->captchaReporting($this->recognizer);
            $btnAnotherMethod = $this->waitForElement(WebDriverBy::xpath("//button[contains(., 'Try another method')]"), 0);

            if ($btnAnotherMethod) {
                $btnAnotherMethod->click();

                $emailOption =
                    $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Email"]'), 5)
                    ?? $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "SMS"]'), 0)
                    ?? $this->waitForElement(WebDriverBy::xpath('//button[@aria-label = "Google Authenticator or similar"]'), 0)
                ;
                $this->saveResponse();

                if ($emailOption) {
                    $emailOption->click();

                    $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 've sent an email with') or contains(text(), 'Check your preferred one-time password application for a code')]"), 5);
                    $this->saveResponse();
                }
            }// if ($btnAnotherMethod)
        }// if ($this->http->FindSingleNode('//h1[contains(text(), "We\'ve sent a text message to")]'))

        if ($this->http->ParseForm("captcha_form") && $this->http->FindSingleNode('//span[contains(., "Verify you are human")]')) {
            $this->captchaReporting($this->recognizer, false);
//            throw new CheckRetryNeededException(0, 7, self::CAPTCHA_ERROR_MSG);
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }

            $this->driver->executeScript("document.querySelector('iframe[data-hcaptcha-response]').setAttribute('data-hcaptcha-response', '{$captcha}');");
            $this->driver->executeScript("document.querySelector('[name=\"g-recaptcha-response\"]').value = '{$captcha}';");
            $this->driver->executeScript("document.querySelector('[name=\"h-captcha-response\"]').value = '{$captcha}';");
            $this->driver->executeScript("document.querySelector('input[name=\"captcha\"]').value = '{$captcha}';");

            $passInput = $this->waitForElement(WebDriverBy::xpath('//input[@id = "password"]'), 0);
            $passInput->sendKeys($this->AccountFields['Pass']);

            $submit = $this->waitForElement(WebDriverBy::xpath('//button[@name="action"]'), 0);
            $this->saveResponse();

            if (!$submit) {
                return $this->checkErrors();
            } else {
                $this->logger->debug("Submit button was found");
            }

            $submit->click();
            // Sign In
            $this->waitFor(function () {
                $timeout = 0;

                if ($btn = $this->driver->findElement(WebDriverBy::xpath('//button[@id = "ensAcceptAll"]'))) {
                    $this->logger->notice("close cookies popup");
                    $this->logger->debug('ensAcceptAll click');
                    $btn->click();
                    sleep(7);
                    $this->saveResponse();
                }

                return $this->waitForElement(WebDriverBy::xpath('
                //li[@class = "logout"]/a[@class = "logOut" and normalize-space() = "Log out"]
                | //input[@id="membershipNumber"]
                | //div[contains(@class, "warning") and not(@hidden)]
                | //p[contains(text(), "Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.")]
                | //h3[contains(text(), "We need to confirm your identity")]
                | //p[contains(text(), "We\'ve sent an email with your code")]
                | //p[contains(text(), "We\'ve sent a text message to")]
                | //p[contains(text(), "Check your preferred one-time password application for a code")]
                | //p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]
                | //h3[contains(text(), "We have updated our Terms and Conditions.")]
                | //p[contains(text(), "Sorry we can\'t show you this page at the moment.")]
                | //span[contains(text(), "This site can’t be reached")]
                | //span[@jsselect="heading" and contains(text(), "This page isn’t working")]
                | //span[contains(text(), "No internet")]
                | //body[contains(text(), "An error (502 Bad Gateway) has occurred in response to this request.")]
                | //p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]
                | //span[contains(text(), "This site can’t be reached")]
                | //span[contains(text(), "This page isn’t working")]
                | //span[contains(text(), "Your connection was interrupted")]
                | //h1[contains(text(), "Welcome to your Executive Club")]
                | //h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]
                | //span[contains(@class, "ulp-input-error-message")]
                | //div[@id = "prompt-alert"]/p
                | //h1[contains(text(), "Sorry, we couldn\'t log you in")]
                | //pre[contains(text(), "Bad Request")]
                | //p[contains(text(), "We are experiencing high demand on ba.com at the moment.")]
                | //p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]
                | //button[contains(., "Try another method")]
            '), $timeout);
            }, 50);
            $this->saveResponse();

            $this->waitForElement(WebDriverBy::xpath("//th[contains(text(), 'Membership number')]/following-sibling::td[1]"), 0);

            // capthca error
            if ($capthcaError = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'Error parsing response') or contains(text(), 'You did not validate successfully. Please try again.')]"), 0)) {
                $this->logger->error(">>> " . $capthcaError->getText());

                throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
            }

            // save page to logs
            $this->logger->debug("save page to logs");
            $this->saveResponse();
        }// if ($this->http->ParseForm("captcha_form") && $this->http->FindSingleNode('//span[contains(., "Verify you are human")]'))

        // Two Factor Authentication    // refs #14276
        if ($this->http->FindSingleNode("//h3[contains(text(), 'We need to confirm your identity')]")
            && ($btnContinue = $this->waitForElement(WebDriverBy::xpath("//form[contains(@action, 'twofactorauthentication') or contains(@class, 'form')]/button[contains(text(), 'Continue')]"), 0))) {
            $this->captchaReporting($this->recognizer);
            $this->logger->notice("Two Factor Authentication Login");
            $btnContinue->click();
            $this->driver->executeScript("try { $('button.continue-button, button#action-btn').click(); } catch (e) {}");
            $this->waitForElement(WebDriverBy::xpath("//form[@id = 'select-option']//input[@id ='email']"), 5);
        }

        if ($this->http->FindSingleNode("//h3[contains(text(), 'We have updated our Terms and Conditions.')]")) {
            $this->captchaReporting($this->recognizer);
            $this->logger->notice("We have updated our Terms and Conditions");
            $this->logger->notice("Current Url: " . $this->http->currentUrl());

            if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability
                && ($btnAgree = $this->waitForElement(WebDriverBy::xpath("//*[self::a or self::button][contains(.,'Agree and continue')]"), 0))
            ) {
                $btnAgree->click();
            } else {
                $this->throwAcceptTermsMessageException();
            }
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]')) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }

        // retries - This page is not available.
        if ($loginFail = $this->waitForElement(WebDriverBy::xpath('
                //p[contains(text(), "Sorry we can\'t show you this page at the moment.")]
                | //span[contains(text(), "This site can’t be reached")]
                | //span[contains(text(), "This page isn’t working")]
                | //span[@jsselect="heading" and contains(text(), "This page isn’t working")]
                | //body[contains(text(), "An error (502 Bad Gateway) has occurred in response to this request.")]
                | //button[contains(text(), "Please wait...")]
                | //p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]
                | //span[contains(text(), "Your connection was interrupted")]
                | //h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]
                | //p[contains(text(), "We are experiencing high demand on ba.com at the moment.")]
            '), 0)
        ) {
            $this->logger->error(">>> " . $loginFail->getText());

            if (
                /*
                $this->attempt > 0
                &&
                */
                trim($loginFail->getText()) == 'An error (502 Bad Gateway) has occurred in response to this request.'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                $this->markProxyAsInvalid();

                throw new CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 0);
        } else {
            $this->logger->notice(">>> error 'This page is not available.' not found");
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        // Two Factor Authentication    // refs #14276
        if ($this->parseQuestion()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }

        // Confirm contact details
        if ($this->http->FindSingleNode("//p[contains(text(), 'confirm the details displayed')]")
            && ($link = $this->http->FindSingleNode("//a[contains(@href, 'main_nav&link=main_nav') and strong[contains(text(), 'My Executive Club')]]/@href"))) {
            $this->logger->notice("Skip update account details");
            $this->http->GetURL($link);
        }
        // You have not yet validated your email address
        if ($this->http->FindPreg('/You have not yet validated your email address/ims')
            // We are currently missing the following information from your details
            || $this->http->FindPreg("/We are currently missing the following information from your details\. To keep your details up to date please complete\/amend the fields below/ims")
           ) {
            $this->captchaReporting($this->recognizer);
            $this->throwProfileUpdateMessageException();
        }
        /*
         * Sorry, you have made too many invalid login attempts.
         * We don't have an email address associated with your account,
         * therefore we cannot send you a new PIN/Password.
         */
        if ($message =
                $this->http->FindPreg("/(Sorry, you have made too many invalid login attempts\.\s*We don\'t have an email address associated with your account\,\s*therefore we cannot send you a new PIN\/Password\.)/ims")
                ?? $this->http->FindSingleNode('//h1[contains(text(), "Your account is now locked")]')
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Please change your PIN to a password
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Please change your PIN to a password')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // We regret to advise that this section of the site is temporarily unavailable
        if ($message = $this->http->FindPreg("/t-logo-topic-content\">\s*<p>\s*([^<]+)/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Please change your PIN to a password
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Please change your PIN to a password')]")
            // Please could you confirm the details displayed, amend or supply them as necessary.
            || $this->http->FindSingleNode("//div[contains(text(), 'Please could you confirm the details displayed, amend or supply them as necessary.')]")
            // We have had problems delivering information to you.
            || $this->http->FindSingleNode("//p[contains(text(), 'We have had problems delivering information to you.')]")
        ) {
            $this->captchaReporting($this->recognizer);
            // throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            $this->http->GetURL('https://www.britishairways.com/travel/echome/execclub/_gf/en_' . $this->getCountryCode() . '?link=main_nav');

            $this->waitForElement(WebDriverBy::xpath('
                //li[@class = "logout"]/a[@class = "logOut" and normalize-space() = "Log out"]
            '), 10);
            $this->saveResponse();
        }

        $notError = $this->http->FindPreg("/(Welcome to)/ims");

        if (
            (isset($notError) && !strstr($this->http->currentUrl(), 'https://www.britishairways.com/travel/loginr/public/en_'))
            || $this->http->FindSingleNode("(//a[contains(text(), 'Log out')])[1]")
        ) {
            $this->captchaReporting($this->recognizer);
            $this->DebugInfo = null;
            $this->markProxySuccessful();

            return true;
        }

        // Invalid password
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'We are not able to recognise the')]")) {
            if ($this->isRewardAvailability) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The username and password do not match what is held on our system')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'You had more than one Executive Club account')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'It has not been possible to log in as our records')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'We are unable to find your username')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Password cannot be e-mailed as no email address is present')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The Login ID you have entered does not match what is held on our system')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The userid/password you have entered is not correct')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Sorry, we don\'t recognise the membership number or PIN/password you have entered")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Sorry, we don\'t recognise the email address you have entered")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "The email address you have entered is already being used on another Executive Club account")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "You are requested to change your password. Please enter a new password.")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Invalid frequent flyer status.")])[1]')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Unable to process request please retry later.
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Unable to process request please retry later")])[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error While getting customer details
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Error While getting customer details")])[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a technical problem
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, there seems to be a technical problem")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there's a problem with our systems. Please try again, and if it still doesn't work, you might want to try again later.
        if ($message = $this->http->FindSingleNode('//li[contains(text(), "Sorry, there\'s a problem with our systems. Please try again, and if it still doesn\'t work, you might want to try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# There is a problem in logging into the system. Please try later.
        if ($message = $this->http->FindPreg("/(There is a problem in logging into the system\.\s*Please try later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Please update your details
        if ($this->http->FindSingleNode("//span[contains(text(), 'We are currently missing the following information from your details')]")) {
            $this->throwProfileUpdateMessageException();
        }
        // You have made too many invalid login attempts
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You have made too many invalid login attempts') or contains(text(), 'Your account is now locked for up to 24 hours')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account has been locked due to too many invalid login attempts.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been locked due to too many invalid login attempts.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        /*
         * Your account is temporarily unavailable
         *
         * We have locked your account temporarily to keep it safe and secure.
         * For more information please refer to the email or letter you received from us.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We have locked your account temporarily to keep it safe and secure.')]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your user id has been locked
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Your user id has been locked')])[1]")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We're sorry, but ba.com is very busy at the moment, and couldn't deal with your request.
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "We\'re sorry, but ba.com is very busy at the moment, and couldn\'t deal with your request.")])[1]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
            $this->DebugInfo = "request has been blocked";
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "warning") and not(@hidden)] | //span[contains(@class, "ulp-input-error-message") and normalize-space(.) != ""] | //div[@id = "prompt-alert"]/p')) {
            $this->logger->error("[Error]: {$message}");

            if ($message == "Verify you are human.") {
                $this->captchaReporting($this->recognizer, false);

                throw new CheckRetryNeededException(0, 7, self::CAPTCHA_ERROR_MSG);
            }

            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Sorry, something went wrong. Please try again or')
                || strstr($message, 'We are having technical difficulties verifying your credentials')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($message, 'We couldn\'t sign you in at the moment. Please review your login details.')) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'We have detected a potential security issue with this account.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            // TODO: provider bug workaround
            if (!strstr($message, 'Please complete the following fields Membership number')) {
                return false;
            }// if (strstr($message, 'Please complete the following fields Membership number'))
        }

        // TODO: provider bug workaround
        if (
            $this->http->FindSingleNode("//input[@id='membershipNumber']/@id")
            && $this->tryOldForm === false
        ) {
            $this->tryOldForm = true;

            try {
                $this->logger->notice("js injection");
                $this->driver->executeScript("
                        $('input[name=membershipNumber], input#loginid, input#username').val('{$this->AccountFields['Login']}');
                        $('input[name=password], input#password').val('" . str_replace(["\\", "'"], ["\\\\", "\'"], $this->AccountFields['Pass']) . "');
                    ");
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ecuserlogbutton"]'), 0)) {
                $this->saveResponse();
                $this->logger->debug('btn->click');
                $btn->click();
                $timeout = 7;
                sleep($timeout);
                $this->saveResponse();

                return $this->Login();
            }// if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ecuserlogbutton"]'), 0))
        }// if (strstr($message, 'Please complete the following fields Membership number'))

        if ($this->http->FindSingleNode('//button[@id = "ecuserlogbutton"]')) {
            throw new CheckRetryNeededException(3, 7);
        }

        return $this->checkErrors();
    }

    // Two Factor Authentication    // refs #14276
    // TODO: need to rewrite to selenium
    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $email = $this->http->FindSingleNode("//form[@id = 'select-option']//input[@id ='email']/following-sibling::label/span");
        $phone = $this->http->FindSingleNode("//form[@id = 'select-option']//input[@id ='phone']/following-sibling::label/span");
        $this->logger->debug("email: {$email}");
        $this->logger->debug("phone: {$phone}");

        if (!$this->http->ParseForm("select-option") || (!isset($email) && !isset($phone))) {
            $this->logger->error("failed to find answer form or question");

            if ($question = $this->http->FindSingleNode("//p[contains(text(), \"We've sent an email with your code\") or contains(text(), \"We've sent a text message to:\") or contains(text(), 'Check your preferred one-time password application for a code')]")) {
                $this->holdSession();

                $email = $this->http->FindSingleNode('//span[contains(@class, "ulp-authenticator-selector-text")]');

                if (!$email && !stristr($question, '@') && !stristr($question, 'password application')) {
                    $this->DebugInfo = "email not found";

                    return false;
                }

                $this->Question = Html::cleanXMLValue($question . " " . $email);

                if (
                    !QuestionAnalyzer::isOtcQuestion($this->Question)
                    && !strstr($this->Question, 'XXXXXXX')
                    && !strstr($this->Question, 'preferred one-time password application for a code')
                ) {
                    $this->sendNotification("Please fix QuestionAnalyzer");
                }

                $this->ErrorCode = ACCOUNT_QUESTION;
                $this->Step = "Question";

                return true;
            }

            $form = $this->http->FindSingleNode("//form[@id = 'select-option']");
            $this->logger->debug(">{$form}<");

            if ($form == 'I already have a code Continue') {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }// if (!$this->http->ParseForm("select-option") || !isset($email))

        $this->logger->info("Two Factor Authentication Login", ['Header' => 3]);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $option = isset($email) ?
            $this->waitForElement(WebDriverBy::xpath("//form[@id = 'select-option']//input[@id ='email']/following-sibling::label"), 0) :
            $this->waitForElement(WebDriverBy::xpath("//form[@id = 'select-option']//input[@id ='phone']/following-sibling::label"), 0)
        ;
        $this->saveResponse();

        if (!$option) {
            return false;
        }

        $option->click();

        $btnCont = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'submit-2FA-option-button' and not(contains(@class, 'disable'))]"), 3);
        $this->saveResponse();

        if (!$btnCont) {
            $this->logger->notice("click one more time");
            $option->click();
            $btnCont = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'submit-2FA-option-button' and not(contains(@class, 'disable'))]"), 3);
            $this->saveResponse();
        }

        if (!$btnCont) {
            return false;
        }

        $btnCont->click();
        $this->waitForElement(WebDriverBy::xpath("//input[@name = '2FACode']"), 10);

        if ($btnCont = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'submit-2FA-option-button' and not(contains(@class, 'disable'))]"), 0)) {
            $this->saveResponse();
            $btnCont->click();
            $this->waitForElement(WebDriverBy::xpath("//input[@name = '2FACode']"), 10);
        }

        $this->saveResponse();
//        $this->http->SetInputValue("ContactType", isset($email) ? "EMAIL" : "MOBILE");
//        $this->http->PostForm();

        $text = isset($email) ? "email address: {$email}" : "phone number: {$phone}";
        $question = "Please enter Identification Code which was sent to your {$text}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        if (!$this->http->ParseForm("TFA-code-verfication-form")) {
            // Sorry, we can't send you a code at the moment due to a fault with our systems. Please try again later.
            if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Sorry, we can\'t send you a code at the moment due to a fault with our systems.") or contains(text(), "Sorry you have asked for too many codes.")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }// if (!$this->http->ParseForm("TFA-code-verfication-form"))

        // website is asking to set new password after 2fa
        if (
            $this->http->InputExists("newPassword")
            && $this->http->InputExists("confirmpassword")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        $this->holdSession();

        if ($this->getWaitForOtc()) {
            $this->sendNotification("2fa - refs #20433 // RR");
        }

        $this->Question = $question;

        if ($email && !QuestionAnalyzer::isOtcQuestion($this->Question)) {
            $this->sendNotification("Please fix QuestionAnalyzer");
        }

        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    // TODO: need to rewrite to selenium
    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
//        $this->sendNotification("Two Factor Authentication Login (refs #14276). Code was entered");
        $otcInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = '2FACode' or @name = 'code']"), 0);
        $this->saveResponse();

        if (!$otcInput) {
            return false;
        }

        $otcInput->sendKeys($answer);
//        $this->waitForElement(WebDriverBy::xpath("//input[@name = 'submit-2FA-code-button']"), 0);
        $btnCont = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'submit-2FA-code-button' and not(contains(@class, 'disable'))] | //button[@name = 'action' and contains(text(), 'Continue')]"), 3);
        $this->saveResponse();

        if (!$btnCont) {
            return false;
        }

        $btnCont->click();
        sleep(5);
        $res = $this->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'Sorry, the code you have entered is incorrect') or contains(text(), 'Sorry, the code you have entered is no longer valid.')] | //span[contains(@class, 'ulp-input-error-message')]"), 5);
        $this->saveResponse();

        // TODO: debug, provider bug workaround
        if (
            !$res
            && ($otcInput = $this->waitForElement(WebDriverBy::xpath("//input[@name = '2FACode' or @name = 'code']"), 0))
        ) {
            $resend = $this->waitForElement(WebDriverBy::xpath("//button[@value = 'resend-code']"), 0);
            $this->saveResponse();

            if (!$resend) {
                return false;
            }

            if ($this->isRewardAvailability) {
                // TODO !!!
                $this->logger->error('incorrect code, you cannot request a new one on RA');

                throw new CheckRetryNeededException(5, 0);
            }

            $resend->click();
            $this->saveResponse();

            $this->AskQuestion($this->Question, "Something went wrong. Please enter a new code which was sent to you.", "Question");

            return false;

            $otcInput->sendKeys($answer);
            $btnCont = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'submit-2FA-code-button' and not(contains(@class, 'disable'))] | //button[@name = 'action' and contains(text(), 'Continue')]"), 3);
            $this->saveResponse();

            if (!$btnCont) {
                return false;
            }

            $btnCont->click();
            sleep(5);
            $this->waitForElement(WebDriverBy::xpath("//h4[contains(text(), 'Sorry, the code you have entered is incorrect') or contains(text(), 'Sorry, the code you have entered is no longer valid.')] | //span[contains(@class, 'ulp-input-error-message')]"), 5);
            $this->saveResponse();
        }

//        $this->http->SetInputValue("2FACode", $this->Answers[$this->Question]);
//        $this->http->PostForm();
        // Sorry, the code you have entered is incorrect
        // Sorry, the code you have entered is no longer valid.
        if ($error = $this->http->FindSingleNode("//h4[contains(text(), 'Sorry, the code you have entered is incorrect') or contains(text(), 'Sorry, the code you have entered is no longer valid.')]")) {
            if ($this->http->ParseForm("TFA-code-verfication-form")) {
                $this->AskQuestion($this->Question, $error, "Question");
            }

            return false;
        }// if ($error = $this->http->FindSingleNode("//h4[contains(text(), 'Sorry, the code you have entered is incorrect')]"))

        if ($error = $this->http->FindSingleNode("//span[contains(@class, 'ulp-input-error-message')]")) {
            $this->logger->error("[2fa Error]: $error");

            if (strstr($error, 'The code you entered is invalid')) {
                $this->holdSession();
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }

            $this->DebugInfo = "[2fa Error]: $error";

            return false;
        }// if ($error = $this->http->FindSingleNode("//span[contains(@class, 'ulp-input-error-message')]"))

        if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Unable to process request please retry later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // site needs new authentication
        if (
            $this->http->FindSingleNode("//li[contains(text(), 'To continue, please log in using your usual details.')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Choose your new password')]")
        ) {
            $newPass = $this->http->FindSingleNode("//h1[contains(text(), 'Choose your new password')]");

            sleep(3);

            if (!$this->LoadLoginForm() || !$this->Login()) {
                return false;
            }

            if ($newPass) {
                $this->sendNotification("Success after 2fa // RR");
            }
            /*
            throw new CheckRetryNeededException(2, 3);
            */
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Two-factor authentication is an extra layer of security that removes password")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->http->FindSingleNode('//pre[contains(text(), "Bad Request")]')) {
            // вообще видела, что отправляется письмо Help us to keep your British Airways account secure
            throw new CheckRetryNeededException(2, 0);
        }

        // provider bug workaround
        if ($this->waitForElement(WebDriverBy::xpath('//input[@id = "membershipNumber"]'), 0)) {
            $this->tryOldForm = true;

            if ($ensCloseBanner = $this->driver->findElement(WebDriverBy::xpath('//button[@id = "ensCloseBanner"]'))) {
                $this->logger->notice("close banner");
                $ensCloseBanner->click();
                sleep(1);
                $this->saveResponse();
            }

            try {
                $this->logger->notice("js injection");
                $this->driver->executeScript("
                        $('input[name=membershipNumber], input#loginid, input#username').val('{$this->AccountFields['Login']}');
                        $('input[name=password], input#password').val('" . str_replace(["\\", "'"], ["\\\\", "\'"], $this->AccountFields['Pass']) . "');
                    ");
            } catch (UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

            if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ecuserlogbutton"]'), 0)) {
                $this->saveResponse();
                $this->logger->debug('btn->click');
                $btn->click();
                $timeout = 7;
                sleep($timeout);
                $this->saveResponse();

                return $this->Login();
            }// if ($btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "ecuserlogbutton"]'), 0))
        }

        return true;
    }

    public function Parse()
    {
        // Name
        $this->SetProperty("Name",
            $this->http->FindSingleNode("//li[@class = 'member-name']//span[contains(@class, 'personaldata')]")
            ?? $this->http->FindSingleNode("//span[@data-cy = 'cust-salutaion']", null, true, "/([^.]+)/")
        );
        // My Tier
        $this->SetProperty("Level", /*$this->http->FindSingleNode("//strong[@id='memberTypeValue'] | //div/p[contains(@class, 'tier-membership')] | //p[@id = 'ec-tier']") ??*/
            $this->http->FindSingleNode("//p[contains(text(),', you can ') and contains(text(),'get rewarded every time you fly')]", null, false, '/^\s*As a ([\w\s]+), you can /'));
        // Member Number
        $this->SetProperty("Number",
            $this->http->FindSingleNode("//p[@class='membership-details']/span[@class='personaldata']")
            ?? $this->http->FindSingleNode("//p[contains(text(), 'membership number:')]", null, true, "/\:\s*([^<]+)/")
        );
        // Tier Point collection year ends
        $this->SetProperty("YearEnds",
            $this->http->FindSingleNode("//div[@id='TPYearEndsInfo-content']/following-sibling::p[@class='account-info-right']")
            ?? $this->http->FindSingleNode("//p[contains(text(), 'Tier Point collection year ends')]", null, true, "/year ends:\s*(.+)/")
        );
        // Card expiry date
        $this->SetProperty("CardExpiryDate",
            $this->http->FindSingleNode("//p[contains(normalize-space(text()), 'Card expiry date')]/following-sibling::p[@class='account-info-right']")
            ?? $this->http->FindSingleNode("//p[contains(text(), 'Membership card expiry')]", null, true, "/Membership card expiry:\s*(.+)/i")
        );
        // Tier Points
        $this->SetProperty("TierPoints",
            $this->http->FindSingleNode("//p[normalize-space(text())='My Tier Points']/following-sibling::div/p[contains(@class, 'tier-points-value')]")
            ?? $this->http->FindSingleNode("//p[@data-cy = 'tier-points-progress']", null, true, "/^\s*([^\/]+)/i")
        );
        // My Lifetime Tier Points
        $this->SetProperty("LifetimeTierPoints",
            $this->http->FindSingleNode("//p[normalize-space(text())='My Lifetime Tier Points']/following-sibling::p[contains(@class, 'tier-points-value')]")
            ?? $this->http->FindSingleNode("//p[@data-cy = 'lifetime-tier-points']", null, true, "/^\s*([^\/]+)\s+point/i")
        );
        // Date of joining the club
        $this->SetProperty("DateOfJoining", $this->http->FindSingleNode("//p[normalize-space(text())='Date of joining the club']/following-sibling::p[@class='account-info-right']"));

        // My eVouchers  // refs #7224
        $eVouchersLink =
            $this->http->FindSingleNode("//a[normalize-space(text()) = 'More voucher information']/@href")
            ?? "https://www.britishairways.com/travel/membership/execclub/_gf?eId=188010"
        ;
        $this->logger->debug("My eVouchers link: " . $eVouchersLink);

        // My Household Account Avios
        $this->SetProperty("HouseholdMiles", $this->http->FindSingleNode("//p[normalize-space(text())='My Household Avios']/following-sibling::p[contains(@class, 'tier-points-value')] | //*[@id = 'household-account']//span[@data-cy = 'primary-value']"));

        $balanceElement = $this->waitForElement(WebDriverBy::xpath("//*[@id = 'avios-points']//span[@data-cy = 'primary-value']"), 0);
        $balance = $balanceElement ? $balanceElement->getText() : null;

        if (!$this->SetBalance($balance ?? $this->http->FindSingleNode("//*[contains(@id,  'aviosInfo')]/following-sibling::p[contains(@class, 'tier-points-value')]"))) {
            // Link "Convert to Executive Club for free"
            if (stristr($this->http->currentUrl(), 'https://www.britishairways.com/travel/viewaccount/inet/en_')
                && ($this->http->FindSingleNode("//a[contains(text(), 'Convert to Executive Club')]")
                    || $this->http->FindPreg("/>Convert to Executive Club<\/a>/ims"))) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }

        if ($allFlights = $this->waitForElement(WebDriverBy::xpath('//label[input[@value="allFlights"]]'), 0)) {
            $allFlights->click();
            sleep(1);
            $this->saveResponse();
            // Eligible Flights To Next Tier (1 block) v.3
            $this->SetProperty('EligibleFlightsToNextTier', $this->http->FindSingleNode("//p[@data-cy = 'all-flights-progress']", null, false, '/^\s*([^\/]+)/i'));
        }
        // Flights to Next Tier
        elseif (!$this->http->FindSingleNode("//div[@id = 'hiddenBenefitsDetails']//*[@class='tier-points-label']", null, true, "/to\s+retain/i")) {
            if ($this->http->FindSingleNode("//div[@class = 'TierAndFlightsRight']/p/text()[contains(., 'eligible flights')]", null, false, '/(\d+)\s+eligible/')) {
                $this->sendNotification('Eligible v1,v2 // MI');
            }
            // eligible flights - Eligible Flights To Next Tier (2 blocks)
            $this->SetProperty('EligibleFlightsToNextTierLinked', $this->http->FindSingleNode("//div[@class = 'TierAndFlightsRight']/p/text()[contains(., 'eligible flights')]", null, false, '/(\d+)\s+eligible/'));
            // eligible flights - Eligible Flights To Next Tier '  (1 block)
            $this->SetProperty('EligibleFlightsToNextTier', $this->http->FindSingleNode("//div[@class = 'TierAndFlightsRight']/following::p[strong[text()='or:']]/following-sibling::div[@class='tier-points']//text()[contains(., 'eligible flights')]", null, false, '/(\d+)\s+eligible/'));
        }

        // Last Activity
        // Get Last 10 Transactions (View all transactions)
        $countryCode = $this->getCountryCode();
        $expLink =
            $this->http->FindSingleNode("//a[contains(@href, 'viewstatement')]/@href")
            ?? "https://www.britishairways.com/travel/viewtransaction/execclub/_gf/en_{$countryCode}?eId=172705"
        ;
        $this->logger->debug("My recent transactions link: " . $expLink);
        $this->link = $expLink;

        if ($expLink != false) {
            $this->logger->info('Expiration date', ['Header' => 3]);

            if ($this->http->GetURL($expLink)) {
                if ($replaceURL = $this->http->FindPreg(self::REG_EXP_PAGE_HISTORY_LINK)) {
                    $this->logger->debug("replaceURL: " . $replaceURL);
                    $this->http->GetURL($replaceURL);
                } else {
                    $this->logger->debug("replaceURL is not found");
                }
                $this->logger->debug('[Parse. date: ' . date('Y/m/d H:i:s') . ']');

                $this->waitForElement(WebDriverBy::xpath(self::XPATH_PAGE_HISTORY), 5);
                $this->saveResponse();
                $exp = $this->http->XPath->query(self::XPATH_PAGE_HISTORY);
                $this->logger->debug("Total transactions found: " . $exp->length);
                $ignoreBookings = [];

                foreach ($exp as $row) {
                    // Description
                    $activity = $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][3]/p", $row);
                    // refs #7665 - ignore certain activities
                    if (!$this->ignoreActivity($activity)) {
                        $date = str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][1]/p[1]", $row));
                        // refs #9168 - ignore row with empty avios
                        $avios = $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][5]/p[2]", $row);
                        $this->logger->debug("Date $date / $avios");

                        // refs #7665 - ignore certain activities, part 2
                        $reference = $this->http->FindPreg("/Reference:\s*([^\s]+)/ims", false, $activity);

                        if (strpos($activity, "Avios refund") !== false || isset($ignoreBookings[$reference])) {
                            $this->logger->debug("Booking Reference: {$reference}");

                            if (isset($ignoreBookings[$reference])) {
                                if ($ignoreBookings[$reference] == -floatval($avios)) {
                                    $this->logger->notice("Skip Avios refund: {$reference}");

                                    continue;
                                }// if ($ignoreBookings[$reference] == -$avios)
                                else {
                                    $this->logger->notice("First transaction not found: {$reference}");
                                }
                            }// if (isset($ignoreBookings[$reference]))
                            else {
                                $this->logger->notice("Add Avios refund to ignore transactions: {$reference}");
                                $ignoreBookings[$reference] = $avios;

                                continue;
                            }
                        }// if (strpos($activity, "Avios refund") !== false || isset($ignoreBookings[$reference]))

                        if ($avios != '' && $avios != '-') {
                            $this->SetProperty('LastActivity', $date);
                            $exp = strtotime($date);

                            if ($exp != false) {
                                $this->SetExpirationDate(strtotime("+3 year", $exp));
                            }

                            break;
                        }// if ($avios != '' && $avios != '-')
                    }// if (!$this->ignoreActivity($activity))
                }// foreach ($exp as $row)
            }// if ($this->http->getURL($expLink))
            else {
                $this->SetProperty('LastActivity', 'n/a');
            }
        }// if ($expLink != false)

        // My eVouchers, refs #7224
        if (isset($eVouchersLink)) {
            $this->logger->info('My eVouchers', ['Header' => 3]);
            $this->http->GetURL($eVouchersLink);
            $this->logger->debug('[Parse. date: ' . date('Y/m/d H:i:s') . ']');
            //            $vouchers = $this->http->XPath->query("//section[@id = 'myEvoucher']/table/tbody/tr");
            // @summary = 'List of unused eVouchers' is deprecated
            $vouchers = $this->http->XPath->query("//div[@id = 'unusedVouchers']/div[@class='table-body']");
            $this->logger->debug("Total vouchers found " . $vouchers->length);

            if ($vouchers->length > 0) {
                for ($i = 0; $i < $vouchers->length; $i++) {
                    //# Voucher number
                    $code = Html::cleanXMLValue($this->http->FindSingleNode("p[contains(@class,'voucher-list-number')]/span[not(contains(text(), 'number'))]", $vouchers->item($i)));
                    //# Type
                    $displayName = Html::cleanXMLValue($this->http->FindSingleNode("p[contains(@class,'voucher-list-type')]", $vouchers->item($i)));
                    //# Expiry
                    $exp = $this->http->FindSingleNode("p[contains(@class,'voucher-list-details') and span[normalize-space(text())='Expiry']]/span[@class='text']", $vouchers->item($i));
                    // $this->http->Log(">>>> ".$exp);

                    if (strtotime($exp) && isset($displayName, $code)) {
                        $subAccounts[] = [
                            'Code'           => 'britishVouchers' . $code,
                            'DisplayName'    => "Voucher #" . $code . " - " . $displayName,
                            'Balance'        => null,
                            'ExpirationDate' => strtotime($exp),
                        ];
                    }// if (strtotime($exp) && isset($displayName, $code))
                }// for ($i = 0; $i < $nodes->length; $i++)

                if (isset($subAccounts)) {
                    //# Set Sub Accounts
                    $this->SetProperty("CombineSubAccounts", false);
                    $this->logger->debug("Total subAccounts: " . count($subAccounts));
                    //# Set SubAccounts Properties
                    $this->SetProperty("SubAccounts", $subAccounts);
                }// if(isset($subAccounts))
            }// if ($vouchers->length > 0)
        }// if (isset($eVouchersLink))
        else {
            $this->logger->notice("My eVouchers link is not found!");
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (strstr($this->http->currentUrl(), 'onbusiness')) {
                throw new CheckException("Seems you are adding a wrong account to your profile as you appear to be a member of British Airways corporate program. Please try adding British Airways (On Business, Corporate) instead of British Airways (Executive Club).", ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries()
    {
        $this->logger->info('[ParseItineraries. date: ' . date('Y/m/d H:i:s') . ']');
        $this->http->FilterHTML = false;

        if ($this->ParsePastIts) {
            $this->logger->debug("parse past itineraries = true");
        } else {
            $this->logger->debug("parse only future itineraries");
        }
        $this->logger->debug(var_export($this->ParsePastIts, true), ['pre' => true]);

        // see #8794
//        $this->http->GetURL("https://www.britishairways.com/travel/echome/execclub/_gf/en_us");
//        $countryCode = $this->getCountryCode();
        $this->http->GetURL("https://www.britishairways.com/travel/VIEWACCOUNT/execclub/en_us?eId=106010&dr=&dt=British%20Airways%20%7C%20Book%20Flights,%20Holidays,%20City%20Breaks%20%26%20Check%20In%20Online&scheme=&logintype=execclub&tier=Blue&audience=travel&CUSTSEG=&GGLMember=&clickpage=HOME&source=accountBar");

        if ($this->http->FindPreg('/We can\'t find any bookings for this account\./ims')) {
            if ($this->ParsePastIts) {
                $pastItineraries = $this->parsePastItineraries();

                if (count($this->itinerariesMaster->getItineraries()) > 0) {
                    return $pastItineraries;
                }
            }// if ($this->ParsePastIts)
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }// if ($this->http->FindPreg('/We can\'t find any bookings for this account\./ims')) {

        // try get some info on cancelled (which see in main page)
        $links = $this->http->XPath->query("//a[contains(@class, 'small-btn') and span[contains(text(), 'Manage My Booking')]] | //a[contains(@class, 'mmb-button') and contains(text(), 'Manage My Booking')]");
        $preParseCancelled = [];

        foreach ($links as $root) {
            $pnr = $this->http->FindSingleNode("./ancestor::div[count(./descendant::text()[normalize-space()='Booking Reference'])=1][1]/descendant::text()[normalize-space()='Booking Reference']/following::text()[normalize-space()!=''][1]",
                $root, false, "/^[A-Z\d]{5,}$/");

            if (!empty($this->http->FindSingleNode("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/p/descendant::text()[normalize-space()!=''][1]", $root, false, "/cancelled/i"))
            ) {
                $preParseCancelled[$pnr] = [
                    'flight' => $this->http->FindSingleNode("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/p/descendant::text()[normalize-space()!=''][1]",
                        $root, false, "/^\w{2}\s*(\d+)\b/"),
                    'airline' => $this->http->FindSingleNode("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/p/descendant::text()[normalize-space()!=''][1]",
                        $root, false, "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+\b/"),
                    'depName' => $this->http->FindSingleNode("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/p/descendant::text()[normalize-space()!=''][2]",
                        $root),
                    'arrName' => $this->http->FindSingleNode("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/p/descendant::text()[normalize-space()!=''][4]",
                        $root),
                    'depDate' => strtotime(implode(", ",
                        $this->http->FindNodes("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/div[2]//span",
                            $root))),
                    'arrDate' => strtotime(implode(", ",
                        $this->http->FindNodes("./ancestor::div[./div[1][contains(@class,'flight-details')]]/div[1]/div[3]//span",
                            $root))),
                ];
            }
        }

        // View all bookings
        if ($allbookings = $this->http->FindSingleNode("//a[span[contains(text(), 'View all bookings') or contains(text(), 'View all current flight bookings')]]/@href")) {
            $this->logger->notice(">>> Get page with all bookings");
            $this->http->NormalizeURL($allbookings);
            $this->http->GetURL($allbookings);
        }

        if ($error = $this->http->FindSingleNode("//h1[text()='Sorry']/following-sibling::p[1][starts-with(normalize-space(),'Sorry, there seems to be a technical problem')]")) {
            $this->logger->error($error);

            return [];
        }

        if ($error = $this->http->FindSingleNode("//h1[text()='Sorry']/following-sibling::p[1][starts-with(normalize-space(),'We regret to advise that this section of the site is temporarily unavailable')]")) {
            $this->logger->error($error);

            return [];
        }

        $links = $this->http->XPath->query("//a[contains(@class, 'small-btn') and span[contains(text(), 'Manage My Booking')]] | //a[contains(@class, 'mmb-button') and contains(text(), 'Manage My Booking')]");
        $this->logger->notice(">>> Total {$links->length} reservations were found");
        $result = [];
        $pnrs = [];
        /** @var DOMElement $root */
        foreach ($links as $root) {
            $url = $root->getAttribute('href');
            $pnr = $this->http->FindSingleNode("./ancestor::div[count(./descendant::text()[normalize-space()='Booking Reference'])=1][1]/descendant::text()[normalize-space()='Booking Reference']/following::text()[normalize-space()!=''][1]",
                $root, false, "/^[A-Z\d]{5,}$/");
            $pnrs[$url] = $pnr;
        }
        $this->logger->debug("[preParseCancelled]: " . var_export($preParseCancelled, true), ['pre' => true]);

        for ($i = 0; $i < $links->length; $i++) {
            $url = $links->item($i)->getAttribute('href');
            $this->logger->debug($url);
            $pnr = null;

            if (isset($pnrs[$url])) {
                $pnr = $pnrs[$url];
                $this->logger->debug("[PNR]: " . $pnr);
                $this->increaseTimeLimit();
            }

            if ($path = $this->http->FindPreg("/javascript\:newloc\(\'MANAGEBOOKING\',\'([^\)]*)\'\)/ims", false, $url)) {
                $this->sendNotification('it 1 // MI');
                $this->logger->notice('First reservation parsing variant');
                $this->http->GetURL('https://www.britishairways.com/travel/managebooking/execclub/en_us/_gf/' . $path);
                $this->jsRedirect();
                $cntBefore = count($this->itinerariesMaster->getItineraries());
                $itin = $this->parseItinerary($this, $pnr);

                if (!is_string($itin) && (count($this->itinerariesMaster->getItineraries()) - $cntBefore) > 0) {
                    $this->logger->debug('Reservation parsed');
                } elseif (is_string($itin)) {
                    $this->logger->error($itin);
                } else {
                    $this->logger->error("something went wrong");
                }
            }
            // Page with all bookings
            elseif ($this->http->FindPreg("#https://www\.britishairways\.com\/travel#", false, $url)) {
                $this->logger->notice('Second reservation parsing variant');
                $this->http->RetryCount = 0;
                $this->http->GetURL($url);
                $this->jsRedirect();

                if ($this->http->Response['code'] === 500) {
                    $this->logger->error('Failed to get itin, retrying');
                    sleep(2);
                    $this->http->GetURL($url);
                    $this->jsRedirect();
                }

                if ($this->http->FindSingleNode('//li[contains(.,"We\'re sorry, but ba.com is very busy at the moment, and couldn")]') && $this->http->ParseForm('simpleform')) {
                    $this->http->PostForm();
                    $this->jsRedirect();
                    // sometimes it helps
                }

                if ($err = $this->http->FindSingleNode('//li[contains(.,"We\'re sorry, but ba.com is very busy at the moment, and couldn")]')) {
                    $this->logger->error($err);

                    continue;
                }
                /*
                if (!($this->http->FindSingleNode("//a[contains(.,'Go back to previous design')]/@href")
                    && $this->http->getCookieByName('MMBVERSION') === 'OPT-IN')
                ) {
                    // TODO: temp fix. надо описать сбор на новом дизайне
                    $this->http->setCookie('MMBVERSION', 'NOT-NOW');
                    $this->http->GetURL($this->http->currentUrl());
                    $this->jsRedirect();
                }
*/
                $this->http->RetryCount = 2;

                if ($this->http->FindPreg('/disruption-recovery/', false, $this->http->currentUrl())) {
                    $this->sendNotification('it 2 // MI');
                    $this->parseItineraryDisruption();

                    continue;
                }

                if ($this->http->FindPreg('/Sorry, We are unable to find your booking/i')) {
                    // Sometimes site passes wrong lastname (e.g. 'Max Johnson' for 'Mr Max Johnson' instead of 'Johnson'), fix it
                    if (preg_match('#^(.*?lastname=).*? (\w+)$#i', $url, $m)) {
                        $url = $m[1] . $m[2];
                    }
                    $this->http->GetURL($url);
                    $this->jsRedirect();
                }

                $itinError = (
                    $this->http->FindSingleNode('//li[contains(text(), "we are unable to display your booking")]') ?:
                    $this->http->FindSingleNode('//li[contains(text(), "Sorry, We are unable to find your booking.")]') ?:
                    $this->http->FindSingleNode('//h3[contains(text(), "Sorry, we can\'t display this booking")]') ?:
                    $this->http->FindSingleNode('//span[not(contains(@class, "wrapText")) and contains(text(), "There are no confirmed flights in this booking")]') ?:
                    $this->http->FindSingleNode('//li[contains(text(), "Sorry, we can\'t display this booking")]') ?:
                    $this->http->FindSingleNode('//li[contains(text(), "There was a problem with your request, please try again later.")]')
                );

                if ($itinError) {
                    $this->logger->error($itinError);

                    if (isset($preParseCancelled[$pnr])) {
                        $this->logger->info("[{$this->currentItin}] Parse Flight #{$pnr}", ['Header' => 3]);
                        $this->currentItin++;
                        $r = $this->itinerariesMaster->add()->flight();
                        $r->general()
                            ->confirmation($pnr)
                            ->status('Cancelled')
                            ->cancelled();
                        $this->getSegmentFromPreParse($r, $preParseCancelled[$pnr]);
                    }

                    continue;
                }
                $nonFlightLink = $this->http->FindSingleNode('(//span[contains(text(), "Print non-flight voucher")])[1]/ancestor::a[1]/@href');

                $msgCancelled = (
                    $this->http->FindSingleNode("//h3[contains(@class, 'refund-progress')][contains(normalize-space(),'We are currently processing a cancellation and refund for this booking')]") ?:
                    $this->http->FindSingleNode("//span[contains(@class, 'wrapText') and normalize-space()='There are no confirmed flights in this booking']/following::text()[normalize-space()!=''][1][normalize-space()='There are no confirmed flights in this booking.']")
                );

                $cntBefore = count($this->itinerariesMaster->getItineraries());

                if ($msgCancelled) {
                    $this->logger->info("[{$this->currentItin}] Parse Flight #{$pnr}", ['Header' => 3]);
                    $this->currentItin++;

                    $this->logger->warning($msgCancelled);
                    $r = $this->itinerariesMaster->add()->flight();
                    $r->general()
                        ->confirmation($pnr)
                        ->status('Cancelled')
                        ->cancelled();

                    if (isset($preParseCancelled[$pnr])) {
                        $this->getSegmentFromPreParse($r, $preParseCancelled[$pnr]);
                    }
                    $flight = [];
                } else {
                    $flight = $this->parseItinerary($this, $pnr, $preParseCancelled);
                }

                if (!is_string($flight) && (count($this->itinerariesMaster->getItineraries()) - $cntBefore) > 0) {
                    $this->logger->debug('Reservation parsed');
                    $its = $this->itinerariesMaster->getItineraries();
                    $itLast = end($its);

                    if ($nonFlightLink && !$itLast->getCancelled()) {
                        $this->sendNotification('it 3 // MI');
                        $this->parseVouchers($nonFlightLink);
                    }
                } elseif (isset($flight) && is_string($flight)) {
                    $this->logger->error($flight);
                } else {
                    $this->logger->error("something went wrong");
                }
            }
        }
        $this->logger->info('[ParseItineraries. date: ' . date('Y/m/d H:i:s') . ']');

        if ($this->ParsePastIts) {
            $this->parsePastItineraries();
        }

        return $result;
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.britishairways.com/travel/managebooking/public/en_us";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        // $error = $this->CheckConfirmationNumberInternalCurl($arFields, $it);
        $error = $this->CheckConfirmationNumberInternalSelenium($arFields, $it);

        if ($error) {
            return $error;
        }

        return null;
    }

    public function CheckConfirmationNumberInternalSelenium($arFields, &$it)
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetProxy(null);
        $selenium = null;

        try {
            $selenium = $this->getSelenium();
            $selenium->http->GetURL($this->ConfirmationNumberURL($arFields));

            $agreeBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Agree to all cookies") or @aria-label="Accept all cookies" or @id = "ensAcceptAll"]'),
                5);

            if ($agreeBtn) {
                $agreeBtn->click();
            }
            $ensCloseBanner = $selenium->waitForElement(WebDriverBy::id('ensCloseBanner'), 0);

            if ($ensCloseBanner) {
                $ensCloseBanner->click();
            }

            $confInput = $selenium->waitForElement(WebDriverBy::id('bookingRef'), 0);
            $nameInput = $selenium->waitForElement(WebDriverBy::id('lastname'), 0);

            if (!$confInput || !$nameInput) {
                $this->notifyRetrieveFail($arFields);

                return null;
            }
            $confInput->sendKeys($arFields['ConfNo']);
            $nameInput->sendKeys($arFields['LastName']);

            $findButton = $selenium->waitForElement(WebDriverBy::id('findbookingbuttonsimple'), 0);

            if (!$findButton) {
                $this->notifyRetrieveFail($arFields);

                return null;
            }
            // debug
            $this->savePageToLogs($selenium);
            $findButton->click();
//        $findLink = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(.,"Go back to previous design")]'), 0);
//        if ($findLink) {
//            $this->logger->debug("click Go back to previous design");
//            $findLink->click();
//        }

            $selenium->waitFor(function () use ($selenium) {
                return $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Booking reference:')]/following-sibling::span"), 0)
                    | $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'js-contact-info') and not(contains(@class,'is-hidden'))]//h1[contains(text(), 'Confirm your contact details')]"), 0);
            }, 15);

            if ($selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'js-contact-info') and not(contains(@class,'is-hidden'))]//h1[contains(text(), 'Confirm your contact details')]"), 0)) {
                $this->savePageToLogs($selenium);
                $button = $selenium->waitForElement(WebDriverBy::xpath("//div[contains(@class,'js-contact-info') and not(contains(@class,'is-hidden'))]//button[contains(text(), 'Confirm and continue')]"), 0);

                if ($button) {
                    $button->click();
                }
                $selenium->waitFor(function () use ($selenium) {
                    return $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Booking reference:')]/following-sibling::span"), 0);
                }, 15);
            }
            $booking = $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Booking reference:')]/following-sibling::span"), 0);
            $this->savePageToLogs($selenium);

            if ($booking === null) {
                $booking = $this->http->FindSingleNode("//h1[starts-with(normalize-space(),'Booking') and ./strong]");
            }
            $booking2 = $this->http->FindSingleNode("//span[contains(text(), 'Booking reference:')]/following-sibling::span");

            if (empty($booking2)) {
                $booking2 = $this->http->FindSingleNode("//span[text()='Booking']/ancestor::p[1]/following-sibling::p");

                if (!empty($booking2)) {
                    $this->parseItineraryRetrieve($booking2);

                    return null;
                }
            }

            if (!$booking && !$booking2) {
                $error = $selenium->waitForElement(WebDriverBy::cssSelector('div.bls-error-container'));

                if ($error) {
                    return $error->getText();
                }
                $this->notifyRetrieveFail($arFields);
                $this->savePageToLogs($selenium);

                return null;
            }
            $nonFlightLink = $this->http->FindSingleNode('(//span[contains(text(), "Print non-flight voucher")])[1]/ancestor::a[1]/@href');
            $msg = $this->http->FindSingleNode("//span[contains(@class, 'wrapText') and normalize-space()='There are no confirmed flights in this booking']/following::text()[normalize-space()!=''][1][normalize-space()='There are no confirmed flights in this booking.']");

            if (!empty($msg)) {
                return $msg;
            }
            $flight = $this->parseItinerary($selenium);

            if (!is_string($flight) && count($this->itinerariesMaster->getItineraries()) > 0) {
                $this->logger->debug('Reservation parsed');

                if ($nonFlightLink) {
                    $this->parseVouchers($nonFlightLink);
                }
            } elseif (is_string($flight)) {
                return $flight;
            }
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        } finally {
            // close Selenium browser

            if ($selenium) {
                $selenium->http->cleanup();
            }
        }

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Book Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function GetHistoryColumns()
    {
        return [
            "Transaction date" => "Info.Date",
            "Posted date"      => "PostingDate",
            "Description"      => "Description",
            "Tier Points"      => "Info",
            "Avios"            => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();
        $params = [
            'from_day'         => date("d", strtotime("+1 day", time())),
            'from_month'       => date("m"),
            'from_year'        => date("Y", strtotime("-3 year", time())),
            'search_type'      => 'D',
            'to_day'           => date("d"),
            'to_month'         => date("n"),
            'to_year'          => date("Y"),
            'transaction_type' => '0',
        ];

        if (!$this->collectedHistory) {
            $this->http->PostURL($this->link, $params);

            if ($link = $this->http->FindPreg(self::REG_EXP_PAGE_HISTORY_LINK)) {
                $this->http->GetURL($link);
            }

            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $nodes = $this->http->XPath->query(self::XPATH_PAGE_HISTORY);
        $this->logger->debug("Found {$nodes->length} items");

        for ($i = 0; $i < $nodes->length; $i++) {
            // ---------------------- Cabin Bonus, Tier Bonus, Flights ----------------------- #
            // TODO: wtf?
            if ($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p", $nodes->item($i)) == '') {
                $k = $i;

                while ($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p", $nodes->item($k)) == '' && $k > 0) {
                    $k--;
                }
                $postDate = strtotime(str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p[1]", $nodes->item($k))));
                $transactionDate = strtotime(str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][1]/p[1]", $nodes->item($k))));
            } else {
                $postDate = strtotime($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][2]/p", $nodes->item($i)));
                $transactionDate = strtotime(str_replace('Transaction:', '', $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][1]/p[1]", $nodes->item($i))));
            }
            // ----------------------------------------------------------------------------- #

            if (isset($startDate) && $postDate < $startDate) {
                continue;
            }
            $result[$startIndex]['Transaction date'] = $transactionDate;
            $result[$startIndex]['Posted date'] = $postDate;
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("div[starts-with(@id,'resultRow')][3]/p", $nodes->item($i));
            $result[$startIndex]['Tier Points'] = $this->TextToInt($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][4]/p[last()]", $nodes->item($i)));
            $result[$startIndex]['Avios'] = $this->TextToInt($this->http->FindSingleNode("div[starts-with(@id,'resultRow')][5]/p[2]", $nodes->item($i)));
            $startIndex++;
        }// for ($i = 0; $i < $nodes->length; $i++)

        return $result;
    }

    public function TextToInt($num)
    {
        return intval(str_replace(',', '', $num));
    }

    public function GetExtensionFinalURL(array $arFields)
    {
        return "https://www.britishairways.com/travel/echome/execclub";
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@data-captcha-provider="hcaptcha"]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }

//        $postData = [
//            "type"           => "HCaptchaTaskProxyless",
//            "websiteURL"     => $this->http->currentUrl(),
//            "websiteKey"     => $key,
//        ];
//        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
//        $this->recognizer->RecognizeTimeout = 120;
//
//        return $this->recognizeAntiCaptcha($this->recognizer, $postData);

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => "hcaptcha",
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
            "domain"  => "js.hcaptcha.com",
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function parseItinerary($selenium, ?string $pnr = null, ?array $preParseCancelled = [])
    {
        $this->logger->notice(__METHOD__);
        $selenium->waitForElement(WebDriverBy::xpath("//h1[starts-with(normalize-space(),'Booking') and ./strong]"), 5);
        $this->saveResponse();

        if ($err = $this->http->FindSingleNode("//ul/li[contains(text(), 'Not able to connect to AGL Group Loyalty Platform and IO Error Recieved')]")) {
            $this->logger->error("Skipping: $err");

            return [];
        }

        if ($this->http->FindSingleNode("//h1[starts-with(normalize-space(),'Booking') and ./strong]")) {
            return $this->parseItinerary2021($pnr, $preParseCancelled);
        }
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->flight();

        $conf = $this->http->FindSingleNode("//span[contains(text(), 'Booking reference:')]/following-sibling::span");

        // watchdog workaround
        if (!empty($conf)) {
            $this->increaseTimeLimit();
        } else {
            $conf = $this->http->FindSingleNode("//input[@id = 'bookingRef']/@value");
        }

        if (empty($conf) && !empty($pnr)) {
            $this->logger->debug('RecordLocator from main page');
            $conf = $pnr;
        }
        $r->general()->confirmation($conf, 'Booking reference');

        $this->logger->info("[{$this->currentItin}] Parse Flight #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        // Passengers
        $passengerInfo = $this->http->XPath->query("//div[@id = 'passengerDetails']//div[div/p[contains(@class, 'paxName')]]");

        for ($i = 0; $i < $passengerInfo->length; $i++) {
            $r->general()
                ->traveller($this->http->FindSingleNode("div[1]", $passengerInfo->item($i)), true);
            $accountNumber = $this->http->FindSingleNode("div[2]//p[2]", $passengerInfo->item($i), true,
                '/\w+\s+([^<]+)/');

            if (!empty($accountNumber) && strtolower($accountNumber) !== 'number') {
                $accountNumbers[] = $accountNumber;
            }
        }// for ($i = 0; $i < $passengerInfo->length; $i++)
        // AccountNumbers
        if (!empty($accountNumbers)) {
            $r->program()->accounts(array_unique($accountNumbers), false);
        }

        $nodes = $this->http->XPath->query("//div[contains(@id, 'flightDetail')]");
        $this->logger->debug("Total segments found " . $nodes->length);

        if ($nodes->length > 0) {
            for ($i = 0; $i < $nodes->length; $i++) {
                $node = $nodes->item($i);
                $s = $r->addSegment();
                $flightLink = $this->http->FindSingleNode(".//a[contains(text(), 'flight information')]/@href", $node);

                $s->airline()
                    ->number($this->http->FindPreg('/\&FlightNumber=(\d+)\&/ims', false, $flightLink))
                    ->name($this->http->FindPreg('/Carrier=([^\&]+)\&/ims', false, $flightLink));
                // Seats
                $seatInfo = $this->http->FindSingleNode(".//*[contains(text(), 'Your allocated seats are')]/span[contains(@class, 'allocation')]",
                    $node);

                if ($seatInfo) {
                    $s->extra()
                        ->seats(array_unique(preg_split('/,\s*/', $seatInfo)));
                }

                if ($flightLink
                    && ($depCode = $this->http->FindPreg("/&from=([A-Z]{3})&to=[A-Z]{3}&/ims", false, $flightLink))
                    && ($arrCode = $this->http->FindPreg("/&from=[A-Z]{3}&to=([A-Z]{3})&/ims", false, $flightLink))
                ) {
                    $s->departure()->code($depCode);
                    $s->arrival()->code($arrCode);
                }
                $s->departure()
                    ->name($this->http->FindSingleNode(".//p[contains(text(), 'Depart')]/following-sibling::p[1]/span[1]",
                        $node))
                    ->terminal($this->http->FindSingleNode(".//p[contains(text(), 'Depart')]/following-sibling::p[1]/span[2]",
                        $node, true, '/Terminal\s*(.+)/i'), true, true);
                // DepDate
                $d1 = $this->http->FindSingleNode(".//p[contains(text(), 'Depart')]/following-sibling::p[3]", $node);
                $d2 = $this->http->FindSingleNode(".//p[contains(text(), 'Depart')]/following-sibling::p[2]", $node);
                $this->logger->debug("DepDate: {$d1} {$d2}");

                if ($d1 && $d2) {
                    $s->departure()->date2(preg_replace("/\S*\s/ims", "", $d1, 1) . " " . $d2);
                }

                $s->arrival()
                    ->name($this->http->FindSingleNode(".//p[contains(text(), 'Arrive')]/following-sibling::p[1]/span[1]",
                        $node))
                    ->terminal($this->http->FindSingleNode(".//p[contains(text(), 'Arrive')]/following-sibling::p[1]/span[2]",
                        $node, true, '/Terminal\s*(.+)/i'), true, true);
                // ArrDate
                $d1 = $this->http->FindSingleNode(".//p[contains(text(), 'Arrive')]/following-sibling::p[3]", $node);
                $d2 = $this->http->FindSingleNode(".//p[contains(text(), 'Arrive')]/following-sibling::p[2]", $node);
                $this->logger->debug("ArrDate: {$d1} {$d2}");

                if ($d1 && $d2) {
                    $s->arrival()->date2(preg_replace("/\S*\s/ims", "", $d1, 1) . " " . $d2);
                }
                // Cabin
                $detailInfo = $this->http->FindSingleNode(".//span[contains(@class, 'flightDetailInfo')]", $node);

                if ($detailInfo) {
                    $cabin = explode(',', $detailInfo);

                    if (isset($cabin[2]) && Html::cleanXMLValue($cabin[2]) !== 'Travelled') {
                        $s->extra()->cabin(Html::cleanXMLValue($cabin[2]));
                    }
                }
                // Status
                if ($this->http->FindSingleNode('.//span[contains(@class, "highlight") and normalize-space(text()) = "CANCELLED"]',
                    $node)
                ) {
                    $s->extra()
                        ->status('Cancelled')
                        ->cancelled();
                }

                //adv (other page)
                if ($flightLink) {
                    sleep(rand(1, 3));
                    $href = $flightLink;
                    $this->logger->debug("Details link -> " . $href);
                    $http2 = clone $this->http;
                    $this->http->brotherBrowser($http2);
                    $http2->setHttp2(true);
                    $http2->NormalizeURL($href);
                    $http2->GetURL($href, [
                        'Accept'           => '*/*',
                        'Accept-Encoding'  => 'gzip, deflate, br',
                        'X-Requested-With' => 'XMLHttpRequest',
                        'Referer'          => 'https://www.britishairways.com/travel/managebooking/public/en_us?eId=104501',
                        'x-dtpc'           => '2$456485567_937h50vJHUGPKHFLUMPNAUVMHULNCUUGGPNSOKN-0e0',
                    ], 20);

                    if ($http2->FindSingleNode('//p[contains(text(), "Detailed information not available for this flight number.")]')) {
                        continue;
                    }

                    // Aircraft
                    $s->extra()
                        ->aircraft($http2->FindSingleNode("//th[contains(text(), \"Aircraft type:\")]/following::td[1]"));
                    // Meal
                    $meal = $http2->FindSingleNode("//th[contains(text(), \"Economy catering:\")]/following::td[1]");

                    if (isset($meal) && !empty($meal)) {
                        $s->extra()->meal($meal);
                    }
                    // Stops
                    $s->extra()->stops($http2->FindSingleNode("//th[contains(text(), \"Number of stops:\")]/following::td[1]", null, false, "/^(\d+)\s*(?:\(\w+|$)/u"));
                    // Operator
                    $operatedBy = $http2->FindSingleNode("//th[contains(text(), \"Operated by:\")]/following::td[1]");

                    if ($s->getAirlineName()) {
                        $s->airline()->operator($this->http->FindPreg('/\s+As\s+(.+?)\s+For\s+/i', false,
                            $operatedBy) ?: $operatedBy);
                    } elseif ($operatedBy) {
                        $s->airline()->name($operatedBy);
                    }
                    // Booking class
                    $s->extra()->bookingCode($http2->FindSingleNode("//th[contains(text(), \"Selling class:\")]/following::td[1]"));
                    // Smoking
                    $smoking = $http2->FindSingleNode("//th[contains(text(), \"Flight:\")]/following::td[1]");

                    if (strpos($smoking, ' ')) {
                        $smoking = preg_replace("/\S*\s/", '', $smoking, 1);

                        if ($smoking === 'Non smoking') {
                            $s->extra()->smoking(false);
                        } else {
                            $this->sendNotification('Check smoking');
                        }
                    }
                    // Duration
                    $s->extra()->duration($http2->FindSingleNode("//th[contains(text(), \"Flying duration:\")]/following::td[1]"));
                }// if ($flightLink)
            }// for ($i = 0; $i < $segments->length; $i++)
        }// if ($segments->length > 0)
        elseif ($this->http->FindSingleNode("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]/strong") === $conf) {
            $this->logger->debug($this->http->FindSingleNode("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]"));

            if (isset($preParseCancelled[$conf])) {
                $r->general()
                    ->status('Cancelled')
                    ->cancelled();

                $this->getSegmentFromPreParse($r, $preParseCancelled[$conf]);
            } else {
                $this->itinerariesMaster->removeItinerary($r);

                return [];
            }
        }

        $cancelledMessage = (
        $this->http->FindPreg('#We are currently processing a cancellation and refund for this booking#i') ?:
            $this->http->FindSingleNode('//h1[contains(text(), "We\'re sorry your flight has been cancelled ")]')
        );

        if ($cancelledMessage) {
            $this->logger->error($cancelledMessage);
            $r->general()
                ->status('Cancelled')
                ->cancelled();
        } elseif (
        $message = $this->http->FindSingleNode("
                //li[contains(text(), 'Access to this booking has now been prohibited due to too many unsuccessful attempts. Please check your travel details are correct and retry in 24 hours.')]
                | //li[contains(text(), 'Sorry, we are unable to use your name as entered.')]
                | //li[contains(text(), 'The booking was not made on ba.com')]
                | //li[contains(text(), 'There is currently no access to your account while we upgrade our system. Please visit the')]
            ")
        ) {
            $this->itinerariesMaster->removeItinerary($r);

            return $message;
        } elseif (count($r->getSegments()) === 0) {
            if (isset($preParseCancelled[$conf])) {
                $r->general()
                    ->status('Cancelled')
                    ->cancelled();
                $this->getSegmentFromPreParse($r, $preParseCancelled[$conf]);
            }
        }

        if ($this->allSegmentsCancelled($r)) {
            $r->general()->cancelled();
        }

        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseItinerary2021(?string $pnr = null, ?array $preParseCancelled = [])
    {
        $this->logger->notice(__METHOD__);
        $segments = $this->http->XPath->query("//div[starts-with(normalize-space(@data-modal-name),'flight')]");

        if ($segments->length == 0 && $this->http->FindSingleNode("//h2[starts-with(normalize-space(),'Where will your eVoucher take you?')]")) {
            $this->logger->notice('Skip: Where will your eVoucher take you?');

            return null;
        }

        $conf = $this->http->FindSingleNode("//h1[starts-with(normalize-space(),'Booking')]/strong");
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($conf, 'Booking');

        if ($this->http->FindSingleNode("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]/strong") === $conf) {
            $this->logger->debug($this->http->FindSingleNode("//p[contains(text(),\"We're replacing your booking with the voucher, so you'll no longer be able to use your\")]"));
            $r->general()
                ->status('Cancelled')
                ->cancelled();

            if (isset($preParseCancelled[$conf])) {
                $this->getSegmentFromPreParse($r, $preParseCancelled[$conf]);
            }
        }
        $pax = array_unique(array_filter($this->http->FindNodes("(//div[starts-with(normalize-space(@data-modal-name),'flight')])/descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5")));

        if (!empty($pax) || !$r->getCancelled()) {
            $r->general()->travellers($pax, true);
        }
        $this->logger->info("[{$this->currentItin}] Parse Flight #{$conf}", ['Header' => 3]);
        $this->currentItin++;

        $segmentsStr = $this->http->FindPregAll("/var trackflightArray = \{\};\s+([\s\S]+?)\s+trackFlightsArrayList.push/");
        $segments = $this->http->XPath->query("//div[starts-with(normalize-space(@data-modal-name),'flight')]");

        foreach ($segments as $i => $segment) {
            $s = $r->addSegment();
            $route = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]", $segment);
            $points = explode(' to ', $route);

            if (count($points) !== 2) {
                $this->logger->error("check parse segment $i");
                $this->sendNotification("check parse segment $i");
                //continue;
            }
            $flight = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[1]", $segment);
            $operator = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[3]/span[contains(.,'Operated by')]", $segment, false, "/Operated by (.+)/");

            if (strlen($operator) > 50) {
                if (stripos($operator, 'AMERICAN AIRLINES (AA) ') !== false) {
                    $operator = 'American Airlines';
                }
            }
            $s->airline()
                ->name($this->http->FindPreg("/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/", false, $flight))
                ->number($this->http->FindPreg("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/", false, $flight))
                ->operator($operator)
                ->confirmation($this->http->FindSingleNode("./descendant::text()[normalize-space()!=''][1]/ancestor::h1", $segment));

            if (isset($segmentsStr[$i]) && $this->http->FindPreg("/\.flightnumber = '{$flight}';/", false,
                    $segmentsStr[$i])
            ) {
                $this->logger->debug($segmentsStr[$i]);
                $s->departure()
                    ->code($this->http->FindPreg("/\.airportfrom = '([A-Z]{3})';/", false, $segmentsStr[$i]));
                $s->arrival()
                    ->code($this->http->FindPreg("/\.airportto = '([A-Z]{3})';/", false, $segmentsStr[$i]));
                $s->extra()
                    ->bookingCode($this->http->FindPreg("/\.sellingclass = '([A-Z]{1,2})';/", false, $segmentsStr[$i]));
            } else {
                $s->departure()->noCode();
                $s->arrival()->noCode();
            }
            $depDate = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]",
                $segment, false, "/Depart at (.+)/");
            $arrDate = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]",
                $segment, false, "/Arrive at (.+)/");
            $stop = $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[2]",
                $segment, false, "/^(\d+) stop/");

            if (null !== $stop) {
                $s->extra()->stops($stop);
            }
            $s->departure()
                ->date2(preg_replace("/^(\d+:\d+), (.+)$/", '$2, $1', $depDate))
                ->name($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]/following-sibling::div[1]/descendant::text()[1]",
                    $segment))
                ->terminal($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[1]/following-sibling::div[1]//span",
                    $segment, false, "/Terminal\s+(.+)/"), false, true);
            $s->arrival()
                ->date2(preg_replace("/^(\d+:\d+), (.+)$/", '$2, $1', $arrDate))
                ->name($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]/following-sibling::div[1]/descendant::text()[1]",
                    $segment))
                ->terminal($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p[contains(.,'Arrive')][1]/following-sibling::div[1]//span", $segment, false, "/Terminal\s+(.+)/"), false, true);
            $s->extra()
                ->duration($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[3]/span[contains(@class,'duration')][1]", $segment), false, true)
                ->cabin(preg_replace('/\([\w\s]+\)/', '', $this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[2]", $segment)), true, true)
                ->status($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[3][not(contains(.,'Information only'))]", $segment), true, true)
                ->aircraft($this->http->FindSingleNode("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[1]/descendant::p/following-sibling::div[2]/span[4]", $segment), false, true)
                ->seats(array_unique($this->http->FindNodes("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5/following-sibling::div[1]//h6[contains(.,'Seating')]/following::p[1]/span[2]", $segment)))
                ->meals($this->http->FindNodes("./descendant::div[contains(@class,'flight-itinerary')][1]/following-sibling::div[2]//h5/following-sibling::div[1]//h6[contains(.,'Meal')]/following::p[1][not(contains(.,'Please try again later'))]", $segment));

            if (stripos($s->getStatus(), 'cancelled') !== false || stripos($s->getStatus(), 'canceled') !== false) {
                $s->extra()->cancelled();
            }
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function allSegmentsCancelled(AwardWallet\Schema\Parser\Common\Flight $r)
    {
        $this->logger->notice(__METHOD__);

        $segments = $r->getSegments();

        if (count($segments) === 0) {
            return false;
        }

        foreach ($segments as $seg) {
            if ($seg->getCancelled() !== true) {
                return false;
            }
        }

        return true;
    }

    private function getSegmentFromPreParse(AwardWallet\Schema\Parser\Common\Flight $r, array $preParse)
    {
        $this->logger->notice(__METHOD__);

        if (!isset($preParse['airline'])) {
            return;
        }
        $s = $r->addSegment();
        $s->airline()
            ->name($preParse['airline'])
            ->number($preParse['flight']);
        $s->departure()
            ->noCode()
            ->name($preParse['depName'])
            ->date($preParse['depDate']);
        $s->arrival()
            ->noCode()
            ->name($preParse['arrName'])
            ->date($preParse['arrDate']);
    }

    private function parseItineraryDisruption()
    {
        $this->logger->notice(__METHOD__);
        $conf = $this->http->FindPreg('/id1=(\w+)/', false, $this->http->currentUrl());
        $this->logger->info("[{$this->currentItin}] Parse Flight #{$conf}", ['Header' => 3]);
        $token = $this->http->getCookieByName('token');
        $headers = [
            'Accept'                    => 'application/json',
            'Accept-Encoding'           => 'gzip, deflate, br',
            'Accept-Language'           => 'EN',
            'Authorization'             => "Bearer {$token}",
            'Ba_Client_Applicationname' => 'BA.COM',
            'Content-Type'              => 'application/json',
        ];
        $url = "https://www.britishairways.com/api/sc4/badotcomadapter-dsa/rs/v1/orders/{$conf}/disruptions?locale=en_AF";
        $this->http->GetURL($url, $headers);
        $data = $this->http->JsonLog(null, 3, true);

        if (!$data) {
            if ($this->http->FindSingleNode('//p[contains(text(), "The requested URL was not found on this server.")]')) {
                $this->logger->error("Itinerary data not found");

                return [];
            }

            return [];
        }
        $reason = $data['errors'][0]['reason'] ?? null;

        if ($reason === 'AUTHENTICATION_FAILED') {
            $this->logger->error("Skipping: Something went wrong, please try again");

            return [];
        }

        $r = $this->itinerariesMaster->add()->flight();
        $this->currentItin++;
        $r->general()->confirmation($conf);
        // segments
        $segments = ArrayVal($data, 'itinerary', []);

        if (empty($segments)) {
            // collect from disruptedSegments
            $segments = ArrayVal($data, 'disruptedSegments', []);

            if (empty($segments) || $this->arrayVal($segments[0], ['disruptionStatus']) !== 'Cancelled') {
                $this->logger->error('something new');
                $segments = [];
            }
        }

        foreach ($segments as $seg) {
            $s = $r->addSegment();
            $s->airline()
                ->name($this->arrayVal($seg, ['marketingFlightNumber', 'code']))
                ->number($this->arrayVal($seg, ['marketingFlightNumber', 'number']))
                ->operator($this->arrayVal($seg, ['operatingAirline', 'code']));
            $s->departure()
                ->code($this->arrayVal($seg, ['origin', 'airport', 'code']))
                ->name($this->arrayVal($seg, ['origin', 'airport', 'name']))
                ->terminal($this->arrayVal($seg, ['origin', 'terminal']), true, true)
                ->date(strtotime($this->arrayVal($seg, ['departureDate'])));
            $s->arrival()
                ->code($this->arrayVal($seg, ['destination', 'airport', 'code']))
                ->name($this->arrayVal($seg, ['destination', 'airport', 'name']))
                ->terminal($this->arrayVal($seg, ['destination', 'terminal']), true, true)
                ->date(strtotime($this->arrayVal($seg, ['arrivalDate'])));
            $s->extra()
                ->duration($this->http->FindPreg("/^PT(\d*H\d*M|\d*H|\d*M)$/", false, $this->arrayVal($seg, ['duration'], '')), false, true);
            // Cabin
            $cabinCode = $this->arrayVal($seg, ['cabinCode']);

            if ($cabinCode === 'M') {
                $cabin = 'Economy';
            } elseif ($cabinCode === 'W') {
                $cabin = 'PremiumEconomy';
            } elseif (in_array($cabinCode, ['C', 'J'])) {
                $cabin = 'Business';
            } elseif (in_array($cabinCode, ['A', 'F'])) {
                $cabin = 'First';
            } else {
                $cabin = null;
            }

            if (isset($cabin)) {
                $s->extra()->cabin($cabin);
            }
            // Status
            if ($this->arrayVal($seg, ['disruptionStatus']) === 'Cancelled') {
                $s->extra()
                    ->status('Cancelled')
                    ->cancelled();
            }
        }

        if ($this->allSegmentsCancelled($r)) {
            $r->general()->cancelled();
        }
        $this->logger->debug('Parsed Flight:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function arrayVal($ar, $indices, $default = null)
    {
        $res = $ar;

        if (is_string($indices)) {
            $indices = [$indices];
        }

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

    private function parseVouchers(string $nonFlightLink): array
    {
        $this->logger->notice(__METHOD__);
        $res = [];
        $this->http->GetURL($nonFlightLink);
        $voucherLinks = $this->http->FindNodes('//span[contains(text(), "Print voucher")]/ancestor::a[1]/@href');
        $this->logger->info(sprintf('Found %d vouchers', count($voucherLinks)));
        $index = 2;

        foreach ($voucherLinks as $link) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);
            $voucherType = $this->http->FindSingleNode('//h1[contains(@class, "voucher-heading")]');

            if ($this->http->FindPreg('/Hotel Voucher/', false, $voucherType)) {
                $this->parseHotel($index);
                $index++;
            } elseif ($this->http->FindPreg('/Car Rental Voucher/', false, $voucherType)) {
                $this->parseCar();
            } elseif ($this->http->FindPreg('/Transfer Voucher/', false, $voucherType)) {
                $this->logger->error('Skipping transfer voucher');
            } elseif ($this->http->FindPreg('/Experience Voucher/', false, $voucherType)) {
                $this->logger->error('Skipping experience voucher');
            } else {
                $this->sendNotification('check new voucher');
            }
        }

        return $res;
    }

    private function parseCar()
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();

        // general
        $conf = $this->http->FindSingleNode('//p[contains(text(), "Car confirmation number")]/following-sibling::p[1]');
        $this->logger->info("[{$this->currentItin}] Parse Car #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $names = $this->http->FindNodes('//p[contains(text(), "Traveller(s) name")]/following-sibling::p[1]//span[contains(@class, "personaldata")]');
        $r->general()
            ->confirmation($conf)
            ->travellers($names, true);
        // RentalCompany
        $r->extra()->company($this->http->FindSingleNode('//p[contains(text(), "Rental company")]/following-sibling::p[1]/span[1]'));
        // pickup
        $locationNodes = $this->http->FindNodes('//p[contains(text(), "Rental company")]/following-sibling::p[1]/span[1]/following-sibling::span');
        $locationNodes = array_values(array_filter($locationNodes));
        $location = implode(', ', $locationNodes);
        $date1 = $this->http->FindSingleNode('//p[contains(text(), "Pick-up date")]/following-sibling::p[1]');
        $time1 = $this->http->FindSingleNode('//p[contains(text(), "Pick-up date")]/ancestor::div[1]/following-sibling::div[1]/p[2]');
        $r->pickup()
            ->location($location)
            ->date(strtotime($time1, strtotime($date1)));
        // dropoff
        $date2 = $this->http->FindSingleNode('//p[contains(text(), "Drop-off date")]/following-sibling::p[1]');
        $time2 = $this->http->FindSingleNode('//p[contains(text(), "Drop-off date")]/ancestor::div[1]/following-sibling::div[1]/p[2]');
        $r->dropoff()
            ->location($location)
            ->date(strtotime($time2, strtotime($date2)));
        // car
        $r->car()
            ->model($this->http->FindSingleNode('//p[contains(text(), "Car Group")]/following-sibling::p[1]'));

        $this->logger->debug('Parsed Car:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parseHotel($index)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->hotel();

        // confirmation
        $conf = $this->http->FindSingleNode('//div[contains(@class, "bookingref")]', null, true, '/\b([A-Z0-9]+)\s*$/');
        $conf = sprintf("$conf-%d", $index);
        $this->logger->info("[{$this->currentItin}] Parse Hotel #{$conf}", ['Header' => 3]);
        $this->currentItin++;

        $names = $this->http->FindNodes('//p[contains(text(), "Traveller(s) name")]/following-sibling::p[1]//span[contains(@class, "personaldata")]');
        $r->general()
            ->confirmation($conf)
            ->travellers($names, true);

        // hotel
        $addressNodes = $this->http->FindNodes('//p[contains(text(), "Property Name")]/following-sibling::p[1]/span[1]/following-sibling::span');
        $addressNodes = array_values(array_filter($addressNodes));
        $address = implode(', ', $addressNodes);
        $phone = $this->http->FindSingleNode('//p[contains(text(), "Telephone")]/following-sibling::p[1]');
        $fax = $this->http->FindSingleNode('//p[contains(text(), "Fax")]/following-sibling::p[1]');
        $r->hotel()
            ->name($this->http->FindSingleNode('//p[contains(text(), "Property Name")]/following-sibling::p[1]/span[1]'))
            ->address($address)
            ->phone($this->http->FindPreg('/^([*]+|na)/i', false, $phone) ? null : $phone, false, true)
            ->fax($this->http->FindPreg('/^([*]+|na)/i', false, $fax) ? null : $fax, false, true);

        // CheckIn/CheckOut
        $date1 = $this->http->FindSingleNode('//p[contains(text(), "Check-in date")]/following-sibling::p[1]');
        $date2 = $this->http->FindSingleNode('//p[contains(text(), "Check-out date")]/following-sibling::p[1]');
        $r->booked()
            ->checkIn2($date1)
            ->checkOut2($date2);
        // RoomType and RoomDescription
        $roomText = $this->http->FindSingleNode('//p[contains(text(), "Room Description")]/following-sibling::p[1]');
        $room = $r->addRoom();
        //->setType($this->http->FindPreg('/^(.+?)\s*[,.] /', false, $roomText))
        if ($this->http->FindPreg('/(, )/', false, $roomText)) {
            $type = $this->http->FindPreg('/^(.+?)\s*[,.] /', false, $roomText);

            if (mb_strlen($type) > 1 && mb_strlen($type) <= 200) {
                $room->setType($type)
                    ->setDescription($this->http->FindPreg('/[,.] (.+)$/', false, $roomText));
            } else {
                $room->setDescription($roomText);
            }
        } else {
            $room->setType($roomText);
        }

        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($r->toArray(), true), ['pre' => true]);

        return [];
    }

    private function parsePastItineraries()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Past Itineraries", ['Header' => 2]);
        $startTimer = $this->getTime();
        $this->http->GetURL("https://www.britishairways.com/travel/viewaccount/execclub/_gf/en_us?eId=106062&source=EXEC_LHN_PASTBOOKINGS");
        $pastIts = $this->http->XPath->query("//div[@class = 'past-book']/div[contains(@class, 'airport-arrival')]");
        $this->logger->debug("Total {$pastIts->length} past reservations found");

        if ($pastIts->length == 0) {
            $this->logger->notice(">>> " . $this->http->FindPreg("/We can't find any bookings for this account in the last 12 months\./ims"));
        }

        for ($i = 0; $i < $pastIts->length; $i++) {
            $node = $pastIts->item($i);
            $header = $this->http->FindSingleNode("h4", $node);
            $r = $this->itinerariesMaster->add()->flight();
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//p[contains(@class, 'booking-value')]", $node));
            $s = $r->addSegment();
            $s->airline()
                ->name($this->http->FindPreg("/^(\w{2})\d+/", false, $header))
                ->number($this->http->FindPreg("/^\w{2}(\d+)/", false, $header));
            $s->departure()
                ->noCode()
                ->date(strtotime($this->http->FindSingleNode(".//p[contains(@class, 'departure-value')]", $node)))
                ->name($this->http->FindPreg("/^[A-Z\d]{2}+\d+\s+(.+)\s+to\s+.+/", false, $header));
            $s->arrival()
                ->noCode()
                ->date(strtotime($this->http->FindSingleNode(".//p[contains(@class, 'arrival-value')]", $node)))
                ->name($this->http->FindPreg("/^[A-Z\d]{2}+\d+\s+.+\s+to\s+(.+)/", false, $header));
        }// for ($i = 0; $i < $pastIts->length; $i++)
        $this->getTime($startTimer);

        return [];
    }

    private function jsRedirect()
    {
        $this->logger->notice(__METHOD__);
        $startTimer = $this->getTime();

        if ($redirect = $this->http->FindSingleNode("//div[@id = 'mainContent']/div/@data-redirecturl")) {
            $this->http->GetURL($redirect, ['Accept-Encoding' => 'gzip, deflate, br'], 25);

            if (strstr($this->http->Error, 'Network error 52 - Empty reply from server')
            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                $this->http->GetURL($redirect, ['Accept-Encoding' => 'gzip,'], 25);

                if (strstr($this->http->Error, 'Network error 52 - Empty reply from server')
                    || strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                    $this->http->GetURL($redirect, ['Accept-Encoding' => 'deflate'], 25);

                    if (strstr($this->http->Error, 'Network error 52 - Empty reply from server')
                        || strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                        $this->http->GetURL($redirect, ['Accept-Encoding' => 'br'], 25);

                        if (!strstr($this->http->Error, 'Network error 52 - Empty reply from server')
                            || strstr($this->http->Error, 'Network error 28 - Operation timed out after')) {
                            $this->sendNotification('Retry 4 success // MI');
                        }
                    }
                }
            }
        }
        $this->getTime($startTimer);
    }

    private function parseItineraryRetrieve(string $pnr)
    {
        $r = $this->itinerariesMaster->add()->flight();
        $r->general()->confirmation($pnr, 'Booking');

        if ($this->http->FindSingleNode("//span[normalize-space()='Your cancelled']") && !$this->http->FindSingleNode("//span[normalize-space()='Your new itinerary']")) {
            $r->general()->cancelled();
        }

        if ($this->http->FindSingleNode("//span[normalize-space()='Your new itinerary']")) {
            $segments = $this->http->XPath->query("//span[normalize-space()='Your new itinerary']/following::div[contains(@class,'flight-container')]");
        } else {
            $segments = $this->http->XPath->query("//div[contains(@class,'flight-container')]");
        }

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $s->airline()
                ->name($this->http->FindSingleNode("(.//div[2]/p[2])[1]", $segment, false,
                    "/^([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+$/"))
                ->number($this->http->FindSingleNode("(.//div[2]/p[2])[1]", $segment, false,
                    "/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)$/"));

            $dateDep = strtotime($this->http->FindSingleNode("./div[2]/p[1]", $segment));
            $dateArr = strtotime($this->http->FindSingleNode("./div[2]/div[1]", $segment), false);

            if (!$dateArr) {
                $dateArr = $dateDep;
            }

            $s->departure()
                ->code($this->http->FindSingleNode("./div[1]/div[3]", $segment))
                ->date(strtotime($this->http->FindSingleNode("./div[1]/div[2]", $segment), $dateDep));
            $s->arrival()
                ->code($this->http->FindSingleNode("./div[1]/div[5]", $segment))
                ->date(strtotime($this->http->FindSingleNode("./div[1]/div[6]/descendant::text()[normalize-space()!=''][1]",
                    $segment), $dateArr));

            $s->extra()
                ->cabin($this->http->FindSingleNode("./div[1]//p[contains(@class,'flight-cabin')]", $segment))
                ->duration($this->http->FindSingleNode("./div[1]//p[contains(@class,'flight-duration')]", $segment))
                ->status($this->http->FindSingleNode("./div[3]", $segment, false, "/^cancell?ed$/i"), false, true);
        }
    }

    private function getSelenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $selenium->UseSelenium();
        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
            [1920, 1080],
        ];
        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->logger->info('chosenResolution:');
        $this->logger->info(var_export($chosenResolution, true));
        $selenium->setScreenResolution($chosenResolution);

        $selenium->disableImages();
        $selenium->http->saveScreenshots = true;
        $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $selenium->seleniumOptions->addHideSeleniumExtension = false;
        $selenium->usePacFile(false);
        $selenium->http->start();
        $selenium->Start();

        return $selenium;
    }

    private function notifyRetrieveFail($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("Failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");
    }

    private function ignoreActivity($activity)
    {
        foreach ($this->activityIgnore as $ignore) {
            if (strpos($activity, $ignore) !== false) {
                return true;
            }
        }

        return false;
    }

    private static function BritishRegions()
    {
        return [
            "AF" => "Afghanistan",
            "AL" => "Albania",
            "DZ" => "Algeria",
            "AS" => "American Samoa",
            "AD" => "Andorra",
            "AO" => "Angola",
            "AI" => "Anguilla",
            "AG" => "Antigua",
            "AR" => "Argentina",
            "AM" => "Armenia",
            "AW" => "Aruba",
            "AU" => "Australia",
            "AT" => "Austria",
            "AZ" => "Azerbaijan",
            "BS" => "Bahamas",
            "BH" => "Bahrain",
            "BD" => "Bangladesh",
            "BB" => "Barbados",
            "BY" => "Belarus",
            "BE" => "Belgium",
            "BZ" => "Belize",
            "BJ" => "Benin Republic",
            "BM" => "Bermuda",
            "BT" => "Bhutan",
            "BO" => "Bolivia",
            "BA" => "Bosnia-Herzegovina",
            "BW" => "Botswana",
            "BR" => "Brazil",
            "VG" => "British Virgin Islands",
            "BN" => "Brunei",
            "BG" => "Bulgaria",
            "BF" => "Burkina Faso",
            "BI" => "Burundi",
            "KH" => "Cambodia",
            "CA" => "Canada",
            "CV" => "Cape Verde",
            "KY" => "Cayman Islands",
            "CF" => "Central African Rep",
            "TD" => "Chad",
            "CL" => "Chile",
            "CN" => "China",
            "CX" => "Christmas Island",
            "CC" => "Cocos Islands",
            "CO" => "Colombia",
            "CG" => "Congo",
            "CK" => "Cook Islands",
            "CR" => "Costa Rica",
            "HR" => "Croatia",
            "CU" => "Cuba",
            "CY" => "Cyprus",
            "CZ" => "Czech Republic",
            "DK" => "Denmark",
            "DJ" => "Djibouti",
            "DM" => "Dominica",
            "DO" => "Dominican Rep",
            "EC" => "Ecuador",
            "EG" => "Egypt",
            "SV" => "El Salvador",
            "GQ" => "Equatorial Guinea",
            "ER" => "Eritrea",
            "EE" => "Estonia",
            "ET" => "Ethiopia",
            "FO" => "Faeroe Is",
            "FK" => "Falkland Is",
            "FJ" => "Fiji",
            "FI" => "Finland",
            "FR" => "France",
            "GF" => "French Guyana",
            "PF" => "French Polynesia",
            "GA" => "Gabon",
            "GM" => "Gambia",
            "GE" => "Georgia",
            "DE" => "Germany",
            "GH" => "Ghana",
            "GI" => "Gibraltar (UK)",
            "GR" => "Greece",
            "GL" => "Greenland",
            "GD" => "Grenada",
            "GP" => "Guadeloupe",
            "GU" => "Guam",
            "GT" => "Guatemala",
            "GN" => "Guinea",
            "GW" => "Guinea Bissau",
            "GY" => "Guyana",
            "HT" => "Haiti",
            "HN" => "Honduras",
            "HK" => "Hong Kong",
            "HU" => "Hungary",
            "IS" => "Iceland",
            "IN" => "India",
            "ID" => "Indonesia",
            "IR" => "Iran",
            "IQ" => "Iraq",
            "IE" => "Ireland",
            "IL" => "Israel",
            "IT" => "Italy",
            "CI" => "Ivory Coast",
            "JM" => "Jamaica",
            "JP" => "Japan",
            "JO" => "Jordan",
            "KZ" => "Kazakhstan",
            "KE" => "Kenya",
            "KI" => "Kiribati",
            "XK" => "Kosovo",
            "KW" => "Kuwait",
            "KG" => "Kyrgyzstan",
            "LA" => "Laos",
            "LV" => "Latvia",
            "LB" => "Lebanon",
            "LS" => "Lesotho",
            "LR" => "Liberia",
            "LY" => "Libya",
            "LI" => "Liechtenstein",
            "LT" => "Lithuania",
            "LU" => "Luxembourg",
            "MO" => "Macau",
            "MK" => "Macedonia",
            "MG" => "Madagascar",
            "MW" => "Malawi",
            "MY" => "Malaysia",
            "MV" => "Maldives",
            "ML" => "Mali",
            "MT" => "Malta",
            "MP" => "Mariana Islands",
            "MH" => "Marshall Islands",
            "MQ" => "Martinique",
            "MR" => "Mauritania",
            "MU" => "Mauritius",
            "MX" => "Mexico",
            "FM" => "Micronesia",
            "UM" => "Minor Island",
            "MD" => "Moldova",
            "MC" => "Monaco",
            "ME" => "Montenegro",
            "MS" => "Montserrat",
            "MA" => "Morocco",
            "MZ" => "Mozambique",
            "MM" => "Myanmar",
            "NA" => "Namibia",
            "NR" => "Nauru",
            "NP" => "Nepal",
            "AN" => "Netherland Antilles",
            "NL" => "Netherlands",
            "NC" => "New Caledonia",
            "NZ" => "New Zealand",
            "NI" => "Nicaragua",
            "NE" => "Niger",
            "NG" => "Nigeria",
            "NU" => "Niue",
            "NF" => "Norfolk Island",
            "NO" => "Norway",
            "OM" => "Oman",
            "PK" => "Pakistan",
            "PA" => "Panama",
            "PG" => "Papua New Guinea",
            "PY" => "Paraguay",
            "KP" => "Peoples Rep Korea",
            "PE" => "Peru",
            "PH" => "Philippines",
            "PL" => "Poland",
            "PT" => "Portugal",
            "PR" => "Puerto Rico",
            "QA" => "Qatar",
            "CM" => "Republic Cameroon",
            "RE" => "Reunion",
            "RO" => "Romania",
            "RU" => "Russia",
            "RW" => "Rwanda",
            "SM" => "San Marino",
            "SA" => "Saudi Arabia",
            "SN" => "Senegal",
            "RS" => "Serbia",
            "SC" => "Seychelles",
            "SL" => "Sierra Leone",
            "SG" => "Singapore",
            "SK" => "Slovakia",
            "SI" => "Slovenia",
            "SB" => "Solomon Island",
            "SO" => "Somalia",
            "ZA" => "South Africa",
            "KR" => "South Korea",
            "ES" => "Spain",
            "LK" => "Sri Lanka",
            "KN" => "St Kitts and Nevis",
            "LC" => "St Lucia",
            "VC" => "St Vincent",
            "SD" => "Sudan",
            "SR" => "Suriname",
            "SZ" => "Swaziland",
            "SE" => "Sweden",
            "CH" => "Switzerland",
            "SY" => "Syria",
            "TW" => "Taiwan",
            "TJ" => "Tajikistan",
            "TZ" => "Tanzania",
            "TH" => "Thailand",
            "TL" => "Timor - Leste",
            "TG" => "Togo",
            "TO" => "Tonga",
            "TT" => "Trinidad and Tobago",
            "TN" => "Tunisia",
            "TR" => "Turkey",
            "TM" => "Turkmenistan",
            "TC" => "Turks Caicos",
            "TV" => "Tuvalu",
            "VI" => "US Virgin Islands",
            "US" => "USA",
            "UG" => "Uganda",
            "UA" => "Ukraine",
            "AE" => "United Arab Emirates",
            "GB" => "United Kingdom",
            "UY" => "Uruguay",
            "UZ" => "Uzbekistan",
            "VU" => "Vanuatu",
            "VE" => "Venezuela",
            "VN" => "Vietnam",
            "WS" => "Western Samoa",
            "YE" => "Yemen Republic",
            "ZM" => "Zambia",
            "ZW" => "Zimbabwe",
        ];
    }
}
