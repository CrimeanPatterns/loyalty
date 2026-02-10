<?php

namespace AwardWallet\Engine\british\RewardAvailability;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use MouseMover;

class Parser extends \TAccountChecker
{
    use \PriceTools;
    use ProxyList;
    use \SeleniumCheckerHelper;

    public $isRewardAvailability = true;
    public $statistics = [
        'selenium'  => false,
        'hot'       => false,
        'isLoggedIn'=> false,
        'accountKey'=> null,
    ];

    public static $useNew = true;

    protected $memSelenium;

    protected $seleniumURL;

    // for RA
    protected $memBrowser;
    protected $memRegion;

    private $link;
    private $depDateIn;
    /**
     * @var \CaptchaRecognizer
     */
    private $recognizer;

//    CREDENTIALS FOR TEST
    //   ['Login' => '27416234', 'Login2' => 'US', 'Pass' => 'Zaq12wsX'];
    private $inCabin;
    private $inDateDep;

    public static function GetAccountChecker($accountInfo)
    {
        if (!self::$useNew) {
            return new static();
        }

        require_once __DIR__ . "/ParserNew.php";

        return new ParserNew();
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.britishairways.com/travel/redeem/execclub/_gf/en_us?eId=106019&tab_selected=redeem&redemption_type=STD_RED'=>'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->KeepState = true;

        $array = ['ca', 'fr', 'us', 'de'];
        $targeting = $array[array_rand($array)];
        $this->setProxyGoProxies(null, $targeting);
    }

    public function IsLoggedIn()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.britishairways.com/travel/viewaccount/execclub/_gf/en_us", [], 5);
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode("//p[@class='membership-details']/span[@class='personaldata']")) {
            $this->statistics['isLoggedIn'] = true;

            return true;
        }

        return false;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['USD'],
            'supportedDateFlexibility' => 0, // 1
            'defaultCurrency'          => 'USD',
        ];
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

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->logger->debug('[Parse start date: ' . date('Y/m/d H:i:s') . ']');
        $countryCode = $this->getCountryCode();

        /*if ($this->isRewardAvailability) {
            $this->logger->error('parsing disabled');

            return false;
        }*/
        $this->http->setCookie("Allow_BA_Cookies", 'accepted', 'www.britishairways.com', '/');
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_' . $countryCode, [], 30);
        $this->http->RetryCount = 2;

        if (in_array($this->http->Response['code'], [0, 403])) {
            $this->markProxyAsInvalid();
            $this->logger->notice("new Proxy");
            $delay = 5;
            sleep($delay);
            $this->logger->notice("Delay -> {$delay}");

            if ($this->attempt == 2) {
                if ($this->AccountFields['ParseMode'] === 'awardwallet') {
                    $this->setProxyMount();
                } else {
                    $this->setProxyGoProxies(null, 'us', 'https://www.britishairways.com/travel/loginr/public/en_' . $countryCode);
                }
            } else {
                $this->setProxyGoProxies();
            }
            $this->http->RetryCount = 0;
            $this->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_' . $countryCode, [], 30);
            $this->http->RetryCount = 2;
        }// if (in_array($this->http->Response['code'], [0, 403]))
        // fix Ireland region
        if ($countryCode === 'ie'
            && $this->http->currentUrl() === 'https://www.britishairways.com/en-gb/traveltrade') {
            $this->logger->notice('fix Ireland region');
            $this->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_gb');
        }
        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
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
            $selenium->setScreenResolution($chosenResolution);

            // if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG)
            //     $selenium->useCache();// Cause of appearance of page 'The page you requested cannot be found.'
            if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability) {
                switch (random_int(0, 3)) {
                    case 0:
                        $cfg = 'CHROME_PUPPETEER_100';

                        break;

                    case 1:
                        $cfg = 'CHROME_95';

                        break;

                    case 2:
                        $cfg = 'CHROME_84';

                        break;

                    default:
                        $cfg = 'CHROME_PUPPETEER_103';

                        break;
                }

                switch ($cfg) {
                    case 'CHROME_PUPPETEER_100':
                        $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_100);

                        break;

                    case 'CHROME_95':
                        $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);

                        break;

                    case 'CHROME_84':
                        $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_84);

                        break;

                    default: // if in State not in list
                        $this->memBrowser = $cfg = 'CHROME_PUPPETEER_103';
                        $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);

                        break;
                }
                $this->memBrowser = $cfg;

                if ($cfg !== 'CHROME_PUPPETEER_103') {
                    $selenium->usePacFile(false);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                }

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if (isset($fingerprint)) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                    $selenium->seleniumOptions->userAgent = $fingerprint->getUseragent();
                } else {
                    $selenium->http->setRandomUserAgent(null, false, true, false, true, false);
                }

                $selenium->seleniumRequest->setHotSessionPool(
                    self::class,
                    $this->AccountFields['ProviderCode'],
                    $this->AccountFields['AccountKey']
                );
            } else {
                /*
                $selenium->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
                $selenium->seleniumOptions->addHideSeleniumExtension = false;
                $selenium->http->setUserAgent(null);
                */

                if ($this->attempt !== 1) {
                    $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_95);
                    $selenium->seleniumOptions->addHideSeleniumExtension = false;
                } else {
                    $selenium->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
                }

                $selenium->seleniumOptions->userAgent = null;
                $selenium->usePacFile(false);

                $request = FingerprintRequest::chrome();
                $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

                if (isset($fingerprint)) {
                    $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                    $selenium->http->setUserAgent($fingerprint->getUseragent());
                }
            }

//                $selenium->http->setUserAgent($this->http->userAgent);

            //$selenium->disableImages();

            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
            // Selenium settings end

            $selenium->driver->manage()->window()->maximize();

            try {
                if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability
                    && strpos($selenium->http->currentUrl(), 'https://www.britishairways.com/travel/viewaccount/execclub') !== false
                ) {
                    $isHot = true;
                    $selenium->http->GetURL('https://www.britishairways.com/travel/viewaccount/execclub/_gf/en_' . $countryCode);
                    $retry = $this->doRetry();
                } else {
                    try {
                        $selenium->http->GetURL('https://www.britishairways.com/travel/loginr/public/en_gb');
                    } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
                        $this->logger->error("UnknownErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);

                        if (strpos($e->getMessage(), 'ERR_PROXY_CONNECTION_FAILED') !== false) {
                            $retry = true;
                        }
                    }
                }
            } catch (\TimeOutException | \Facebook\WebDriver\Exception\TimeoutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
                $selenium->driver->executeScript('window.stop();');
                // save page to logs
                $this->savePageToLogs($selenium);
            }

            if (isset($isHot)) {
                // save page to logs
                $this->logger->debug("save page to logs");
                $this->savePageToLogs($selenium);
                $this->memSelenium = $selenium;

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                        $cookie['expiry'] ?? null);
                }

                if ($this->http->FindSingleNode("//li[@class='logout']/a[@class='logOut' and normalize-space()='Log out']")) {
                    $this->logger->notice('logged in, Save session');
                    $selenium->keepSession(true);
                }

                $this->http->LogHeaders = true;
                $this->seleniumURL = $selenium->http->currentUrl();
                $this->logger->debug("[Current URL]: {$this->seleniumURL}");

                return true;
            }

            if ($msg = $this->http->FindSingleNode("//p[normalize-space()='We are experiencing high demand on ba.com at the moment.']")) {
                // most often block by IP (on start)
                $this->markProxyAsInvalid();

                throw new \CheckRetryNeededException(5, 0);
            }

            $this->savePageToLogs($selenium);

            if ($btn = $selenium->waitForElement(\WebDriverBy::xpath('//*[@id="loginLinkDesktop"]/ba-icon'), 5)) {
                $btn->click();
            }
            $selenium->driver->executeScript('
            if (document.querySelector("body > app-root > app-header-wrapper > lib-ba-header > ba-header"))
                document.querySelector("body > app-root > app-header-wrapper > lib-ba-header > ba-header").shadowRoot.querySelector("#loginLinkDesktop").click();
            ');

            // New auth
            $loginInput = $selenium->waitForElement(\WebDriverBy::id('username'), 7);

            if (
                !$loginInput
                && ($agreeBtn = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(text(), "Accept All")]'), 0))
            ) {
                $this->savePageToLogs($selenium);
                $agreeBtn->click();
                $loginInput = $selenium->waitForElement(\WebDriverBy::id('username'), 7);
            }
            // save page to logs
            $this->savePageToLogs($selenium);
            $loginInputOld = $selenium->waitForElement(\WebDriverBy::id('membershipNumber'), 0);

            // login
            if (!$loginInput && !$loginInputOld) {
                $this->logger->error("something went wrong");
                // retries
                if ($selenium->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.')]"), 0)) {
                    $this->DebugInfo = "Request has been blocked";
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $retry = true;
                } else {
                    $retry = $this->doRetry();

                    if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                        $this->markProxyAsInvalid();

                        throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                        $this->markProxyAsInvalid();

                        throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                // freezing script workaround
                if (strpos($selenium->http->currentUrl(),
                        'https://www.britishairways.com/travel/loginr/public') !== false
                    && $this->http->FindPreg("/<noscript>Please enable JavaScript to view the page content.<\/noscript>/")) {
                    $retry = true;
                }

                if ($this->http->FindPreg("/^<head><\/head><body><\/body>$/")) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            if (!$loginInputOld) {
                $mover = new MouseMover($selenium->driver);
                $mover->logger = $this->logger;
                $mover->duration = rand(100000, 120000);
                $mover->steps = rand(50, 70);

                $loginInput = $selenium->waitForElement(\WebDriverBy::id('username'), 7);
                $loginInput->click();
                //$loginInput->sendKeys($this->AccountFields['Login']);
                $mover->sendKeys($loginInput, $this->AccountFields['Login']);
                $this->savePageToLogs($selenium);

                $password = $selenium->waitForElement(\WebDriverBy::xpath('//input[@name="password"]'), 0);

                if (!$password) {
                    $this->sendNotification("check input pwd // ZM");

                    throw new \CheckRetryNeededException(5, 0);
                }
                $password->click();
                //$password->sendKeys($this->AccountFields['Pass']);
                $mover->sendKeys($password, $this->AccountFields['Pass']);
                $this->savePageToLogs($selenium);

                if ($iframe = $selenium->waitForElement(\WebDriverBy::xpath("//div[@id = 'ulp-hcaptcha']/iframe"), 5)) {
                    $currentUrl = $selenium->http->currentUrl();
                    $captcha = $this->parseHCaptcha($currentUrl);

                    if ($captcha === false) {
                        return false;
                    }
                    $this->savePageToLogs($selenium);
//                    $selenium->driver->executeScript('document.querySelector(\'textarea[name="g-recaptcha-response"]\').value = "' . $captcha . '";');
//                    $selenium->driver->executeScript('document.querySelector(\'textarea[name="h-captcha-response"]\').value = "' . $captcha . '";');
                    $selenium->driver->executeScript("document.querySelector('input[name=\"captcha\"]').value = '{$captcha}';");
                }

                if ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Continue')]"), 0)) {
                    $this->savePageToLogs($selenium);
                    $this->logger->debug('btn->click');
                    $btn->click();
                }
                $this->waitFor(function () use ($selenium) {
                    return $selenium->waitForElement(\WebDriverBy::xpath('
                    //button[contains(text(), "Accept All")]
                    | //p[contains(text(), "We are having technical difficulties verifying your credentials, please try again later")]
                    | //h1[contains(text(), "Help us protect your account")]
                    | //p[normalize-space()="We are experiencing high demand on ba.com at the moment."]
                    | //pre[contains(text(),"Bad Request")]
                    | //span[@id="error-element-password"]
                    '), 0)
                        || $selenium->waitForElement(\WebDriverBy::id('membershipNumber'), 0);
                }, 10);

                // !!! captcha once again
                if ($iframe = $selenium->waitForElement(\WebDriverBy::xpath("//div[@id = 'ulp-hcaptcha']/iframe"), 5)) {
                    $currentUrl = $selenium->http->currentUrl();
                    $captcha = $this->parseHCaptcha($currentUrl);

                    if ($captcha === false) {
                        return false;
                    }
                    $this->savePageToLogs($selenium);
                    $selenium->driver->executeScript("document.querySelector('input[name=\"captcha\"]').value = '{$captcha}';");

                    // submit again
                    if ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[@type='submit' and contains(text(),'Continue')]"), 0)) {
                        $this->savePageToLogs($selenium);
                        $this->logger->debug('btn->click');
                        $btn->click();
                    }
                }
                $this->waitFor(function () use ($selenium) {
                    return $selenium->waitForElement(\WebDriverBy::xpath('
                    //button[contains(text(), "Accept All")]
                    | //p[contains(text(), "We are having technical difficulties verifying your credentials, please try again later")]
                    | //h1[contains(text(), "Help us protect your account")]
                    | //p[normalize-space()="We are experiencing high demand on ba.com at the moment."]
                    | //pre[contains(text(),"Bad Request")]
                    '), 0)
                        || $selenium->waitForElement(\WebDriverBy::id('membershipNumber'), 0);
                }, 30);
            }

            if ($selenium->waitForElement(\WebDriverBy::xpath('
            //p[normalize-space()="We are experiencing high demand on ba.com at the moment."]
            | //span[@id="error-element-password"]
            | //pre[contains(text(),"Bad Request")]'), 0)) {
                // most often block by IP (on start) - We are experiencing high demand on ba.com at the moment.
                $this->markProxyAsInvalid();
                $this->savePageToLogs($selenium);

                if ($selenium->waitForElement(\WebDriverBy::xpath('//span[@id="error-element-password"][contains(.,"If issue persists, your account may be locked.")]'),
                    0)) {
                    throw new \CheckException('lock account', ACCOUNT_PREVENT_LOCKOUT);
                }

                throw new \CheckRetryNeededException(5, 0);
            }

            // TODO!!!!
            if ($selenium->waitForElement(\WebDriverBy::xpath('//h1[contains(text(), "Help us protect your account")]'),
                0)) {
                $this->logger->error("it wants 2fa");

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($error = $selenium->waitForElement(\WebDriverBy::xpath('//p[contains(text(), "We are having technical difficulties verifying your credentials, please try again later")]'),
                0)) {
                $msg = $error->getText();
                $this->savePageToLogs($selenium);
                //проверили - полный блок это

                throw new \CheckException($msg, ACCOUNT_LOCKOUT);
            }

            if ($agreeBtn = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(text(), "Accept All")]'),
                10)) {
                $agreeBtn->click();
                $this->savePageToLogs($selenium);
            }

            $loginInput = $selenium->waitForElement(\WebDriverBy::id('membershipNumber'), 20);

            if (
                !$loginInput
                && ($agreeBtn = $selenium->waitForElement(\WebDriverBy::xpath('//button[contains(text(), "Agree to all cookies")]'), 0))
            ) {
                $this->savePageToLogs($selenium);
                $agreeBtn->click();
                $loginInput = $selenium->waitForElement(\WebDriverBy::id('membershipNumber'), 7);
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput) {
                $this->logger->error('something new');

                return false;
            }

            // login
            if (!$loginInput) {
                $this->logger->error("something went wrong");
                // retries
                if ($selenium->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.')]"), 0)) {
                    $this->DebugInfo = "Request has been blocked";
                    $this->ErrorReason = self::ERROR_REASON_BLOCK;
                    $retry = true;
                } else {
                    $retry = $this->doRetry();

                    if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                        $this->markProxyAsInvalid();

                        throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }

                    if ($this->http->FindSingleNode('//span[contains(text(), "This site can’t be reached")]')) {
                        $this->markProxyAsInvalid();

                        throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                    }
                }

                // freezing script workaround
                if (strpos($selenium->http->currentUrl(),
                        'https://www.britishairways.com/travel/loginr/public') !== false
                    && $this->http->FindPreg("/<noscript>Please enable JavaScript to view the page content.<\/noscript>/")) {
                    $retry = true;
                }

                if ($this->http->FindPreg("/^<head><\/head><body><\/body>$/")) {
                    $retry = true;
                }

                return $this->checkErrors();
            }

            /*
            $mover = new MouseMover($selenium->driver);
            $mover->logger = $this->logger;
            $mover->duration = rand(100000, 120000);
            $mover->steps = rand(50, 70);

            $this->logger->debug('move to Password field');
            $mover->moveToElement($loginInput);
            $mover->click();
            $loginInput->clear();
            */

            if ($ensCloseBanner = $selenium->waitForElement(\WebDriverBy::xpath('//button[@id = "ensCloseBanner"]'), 0)) {
                $ensCloseBanner->click();
                $this->savePageToLogs($selenium);
            }

            /*
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 10);
            $loginInput->sendKeys($this->AccountFields['Login']);
            */
            /*
            // password
            $passwordInput = $selenium->waitForElement(WebDriverBy::id('input_password'), 0);

            if (!$passwordInput && !$selenium->waitForElement(WebDriverBy::id('input_password'), 0, false)) {
                return $this->checkErrors();
            }

            $this->logger->debug('move to Password field');
            $mover->moveToElement($passwordInput);
            $mover->click();
            $passwordInput->clear();
//                $passwordInput->sendKeys($this->AccountFields['Pass']);
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);
            */
            $this->logger->notice("js injection");

            try {
                $selenium->driver->executeScript("
                    $('#ensCloseBanner').click();
                    $('input[name=membershipNumber], input#loginid').val('{$this->AccountFields['Login']}');
                    $('input[name=password], input#password').val('" . str_replace(["\\", "'"], ["\\\\", "\'"], $this->AccountFields['Pass']) . "');");
            } catch (\UnexpectedJavascriptException $e) {
                $this->logger->error("UnexpectedJavascriptException: " . $e->getMessage(), ['HtmlEncode' => true]);
            }

//            $remember = $selenium->waitForElement(WebDriverBy::id('showRememberModalIcon'), 1);
            $selenium->driver->executeScript("var remember = document.getElementById('rememberMe'); if (remember) remember.checked = true;");
            $this->savePageToLogs($selenium);

            /*
            if ($remember) {
                $remember->click();
                sleep(2);
            }
            */
            // Sign In
            $this->waitFor(function () use ($selenium) {
                $timeout = 0;

                if ($btn = $selenium->waitForElement(\WebDriverBy::id('ecuserlogbutton'), 0)) {
                    $this->savePageToLogs($selenium);
                    $this->logger->debug('btn->click');
                    $btn->click();
                    $timeout = 7;
                }

                return is_object($selenium->waitForElement(\WebDriverBy::xpath('
                    //li[@class = "logout"]/a[@class = "logOut" and normalize-space() = "Log out"]
                    | //div[contains(@class, "warning") and not(@hidden)]
                    | //p[contains(text(), "Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.")]
                    | //h3[contains(text(), "We need to confirm your identity")]
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
                '), $timeout));
            }, 50);
            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode("//p[contains(text(), 'Unfortunately access to the web page you were trying to visit has been blocked as our systems have detected unusual traffic from your computer network.')]")) {
                $this->DebugInfo = "Request has been blocked";
                $this->ErrorReason = self::ERROR_REASON_BLOCK;
                $retry = true;
            }

            // Two Factor Authentication    // refs #14276
            if ($this->http->FindSingleNode("//h3[contains(text(), 'We need to confirm your identity')]")
                && ($btnContinue = $selenium->waitForElement(\WebDriverBy::xpath("//form[contains(@action, 'twofactorauthentication')]/button[contains(text(), 'Continue')]"), 0))) {
                $this->logger->notice("Two Factor Authentication Login");
                $btnContinue->click();
                $selenium->driver->executeScript("try { $('button.continue-button').click(); } catch (e) {}");
                $selenium->waitForElement(\WebDriverBy::xpath("//form[@id = 'select-option']//input[@id ='email']"), 5);
            }

            if ($this->http->FindSingleNode("//h3[contains(text(), 'We have updated our Terms and Conditions.')]")) {
                $this->logger->notice("We have updated our Terms and Conditions");
                $this->logger->notice("Current Url: " . $this->http->currentUrl());

                if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability
                    && ($btnAgree = $selenium->waitForElement(\WebDriverBy::xpath("//*[self::a or self::button][contains(.,'Agree and continue')]"), 0))
                ) {
                    $btnAgree->click();
                } else {
                    $this->throwAcceptTermsMessageException();
                }
            }

            // save page to logs
            $this->savePageToLogs($selenium);

            if ($this->http->ParseForm("captcha_form")) {
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    return false;
                }
                $selenium->driver->executeScript("$('#g-recaptcha-response').val(\"" . $captcha . "\");");
                $submit = $selenium->waitForElement(\WebDriverBy::xpath("//input[@value = 'Submit']"), 0);

                if (!$submit) {
                    return $this->checkErrors();
                }
                $this->logger->debug("Submit button was found");

                $selenium->driver->executeScript('setTimeout(function(){
                        delete document.$cdc_asdjflasutopfhvcZLawlt_;
                        delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                        $(\'input[value = "Submit"]\').click();
                        delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                        delete document.$cdc_asdjflasutopfhvcZLawlt_;
                    }, 500)');

                // Two Factor Authentication    // refs #14276
                if ($selenium->waitForElement(\WebDriverBy::xpath("//h3[contains(text(), 'We need to confirm your identity')]"), 5)
                    && ($btnContinue = $selenium->waitForElement(\WebDriverBy::xpath("//form[contains(@action, 'twofactorauthentication')]/button[contains(text(), 'Continue')]"), 0))) {
                    $this->logger->notice("Two Factor Authentication Login");
                    $btnContinue->click();
                    $selenium->waitForElement(\WebDriverBy::xpath("//form[@id = 'select-option']//input[@id ='email']"), 5);
                }

                $selenium->waitForElement(\WebDriverBy::xpath("//th[contains(text(), 'Membership number')]/following-sibling::td[1]"), 0);

                // capthca error
                if ($capthcaError = $selenium->waitForElement(\WebDriverBy::xpath("//p[contains(text(), 'Error parsing response') or contains(text(), 'You did not validate successfully. Please try again.')]"), 0)) {
                    $this->logger->error(">>> " . $capthcaError->getText());

                    throw new \CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
                }

                // save page to logs
                $this->logger->debug("save page to logs");
                $this->savePageToLogs($selenium);
            }
            // retries - This page is not available.
            if ($loginFail = $selenium->waitForElement(\WebDriverBy::xpath('
                    //p[contains(text(), "Sorry we can\'t show you this page at the moment.")]
                    | //span[contains(text(), "This site can’t be reached")]
                    | //span[contains(text(), "This page isn’t working")]
                    | //span[@jsselect="heading" and contains(text(), "This page isn’t working")]
                    | //body[contains(text(), "An error (502 Bad Gateway) has occurred in response to this request.")]
                    | //button[contains(text(), "Please wait...")]
                    | //p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]
                    | //span[contains(text(), "Your connection was interrupted")]
                    | //h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]
                '), 0)
            ) {
                $this->logger->error(">>> " . $loginFail->getText());

                if (
                    $this->attempt > 0
                    && trim($loginFail->getText()) === 'An error (502 Bad Gateway) has occurred in response to this request.'
                ) {
                    throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                    $this->markProxyAsInvalid();

                    throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                $retry = true;
            } else {
                $this->logger->notice(">>> error 'This page is not available.' not found");
            }

            if (!$retry) {
                // save page to logs
                $this->logger->debug("save page to logs");
                $this->savePageToLogs($selenium);

                $cookies = $selenium->driver->manage()->getCookies();

                foreach ($cookies as $cookie) {
                    $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
                }

                if (property_exists($this, 'isRewardAvailability') && $this->isRewardAvailability) {
                    $this->logger->notice('logged in, Save session');
                    $selenium->keepSession(true);
                    $this->memSelenium = $selenium;

                    $this->markProxySuccessful();
                }
            }

            $this->seleniumURL = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$this->seleniumURL}");
        } catch (\Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("JavascriptErrorException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (
                strpos($e->getMessage(), 'timeout: Timed out receiving message from renderer') !== false
                || strpos($e->getMessage(), 'timeout') !== false
            ) {
                $retry = true;
            }
        } catch (\TimeOutException | \Facebook\WebDriver\Exception\TimeoutException $e) {
            $this->logger->error("TimeOutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (
                strpos($e->getMessage(), 'Command timed out in client when executing') !== false
            ) {
                $retry = true;
            }
        } catch (\StaleElementReferenceException $e) {
            $this->logger->error("StaleElementReferenceException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strpos($e->getMessage(), 'Element not found in the cache') !== false
                || strpos($e->getMessage(), 'element is not attached to the page document') !== false) {
                $retry = true;
            }
        } catch (
        \Facebook\WebDriver\Exception\WebDriverException
        | \NoSuchDriverException
        $e
        ) {
            $this->logger->error("WebDriverException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        } catch (\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            if (strpos($e->getMessage(), 'Curl error thrown for http') !== false) {
                $retry = true;
            }
        } catch (
        \SessionNotCreatedException $e
        ) {
            $this->logger->error("SessionNotCreatedException: " . $e->getMessage(), ['HtmlEncode' => true]);
            // retries
            // session not created exception from unknown error: failed to close UI debuggers
            if (
                strpos($e->getMessage(), 'debuggers') !== false
                || strpos($e->getMessage(), 'session not created exception') !== false
                || strpos($e->getMessage(), 'session not created: create container') !== false
            ) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        finally {
            if (property_exists($this, 'isRewardAvailability')
                && $this->isRewardAvailability
                && !$retry
                && isset($this->memSelenium)
            ) {
                $this->logger->debug("no close Selenium browser. Parser should do it later");
            } else {
                // close Selenium browser
                $selenium->http->cleanup();
                $this->logger->debug("retry " . $retry);
                $this->logger->debug("CONFIG_SITE_STATE " . ConfigValue(CONFIG_SITE_STATE));
                $this->logger->debug("SITE_STATE_DEBUG " . SITE_STATE_DEBUG);

                if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $this->logger->debug("[attempt]: {$this->attempt}");
                    $this->markProxyAsInvalid();

                    throw new \CheckRetryNeededException(3, 0);
                }
            }
        }

//            $eId = $this->http->FindPreg("/formEvent\('([\d]+)'\);/ims");
//            if (!$this->http->ParseForm("form1") || !isset($eId)) {
//                $this->http->Log("eId -> ".$eId);
//                return $this->checkErrors();
//            }
//            $this->http->FormURL = $this->http->FormURL.'?eId='.$eId;
//            $this->http->SetInputValue("membershipNumber", $this->AccountFields['Login']);
//            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
//            $this->http->Form['loginButton'] = '1';

        return true;
    }

    public function getCountryCode()
    {
        if (!isset($this->AccountFields['Login2']) || $this->AccountFields['Login2'] == '') {
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
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // System Upgrade
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Due to the Executive Club System Upgrade you will experience limited access to your account')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We regret to advise that this section of the site is temporarily unavailable.
        if ($message = $this->http->FindPreg("/(We regret to advise that this section of the site is temporarily unavailable\.)/ims")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Unfortunately our systems are not responding
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'Unfortunately our systems are not responding')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are currently carrying out site maintenance between ...
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are currently carrying out site maintenance')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // There is currently no access to your account while we upgrade our system
        if ($message = $this->http->FindSingleNode("//li[contains(text(),'There is currently no access to your account while we upgrade our system')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a technical problem. Please try again in a few minutes.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes.')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // We are experiencing technical issues today with our website.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We are experiencing technical issues today with our website.')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Sorry, there seems to be a technical problem. Please try again in a few minutes, and please contact us if it still doesn't work.
         * We apologise for the inconvenience.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Sorry, there seems to be a technical problem. Please try again in a few minutes')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
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
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if (
            // Internal Server Error - Read
            $this->http->FindSingleNode("//h1[contains(text(), 'Internal Server Error - Read')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '504 Gateway Time-out')]")
            || $this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")
            || $this->http->FindPreg("/An error occurred while processing your request\./")
        ) {
            throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("[URL]: " . $this->http->currentUrl());
        $this->logger->debug("[CODE]: " . $this->http->Response['code']);
        // retries
        if (in_array($this->http->Response['code'], [0, 301, 302, 403])
            || ($this->http->Response['code'] == 200 && empty($this->http->Response['body']))) {
            if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
                throw new \CheckRetryNeededException(3, 0, self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckRetryNeededException(3, 5);
            // error in selenium
        }

        if ($this->http->Response['code'] == 200
            && $this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(3, 0);
        }

        $this->logger->debug('[checkErrors. date: ' . date('Y/m/d H:i:s') . ']');

        return false;
    }

    // Two Factor Authentication    // refs #14276
    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $email = $this->http->FindSingleNode("//form[@id = 'select-option']//input[@id ='email']/following-sibling::label/span");
        $phone = $this->http->FindSingleNode("//form[@id = 'select-option']//input[@id ='phone']/following-sibling::label/span");
        $this->logger->debug("email: {$email}");
        $this->logger->debug("phone: {$phone}");

        if (!$this->http->ParseForm("select-option") || (!isset($email) && !isset($phone))) {
            $this->logger->error("failed to find answer form or question");

            $form = $this->http->FindSingleNode("//form[@id = 'select-option']");
            $this->logger->debug(">{$form}<");

            if ($form === 'I already have a code Continue') {
                $this->throwProfileUpdateMessageException();
            }

            return false;
        }// if (!$this->http->ParseForm("select-option") || !isset($email))

        $this->logger->info("Two Factor Authentication Login", ['Header' => 3]);

        // prevent code spam    // refs #6042
        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $this->http->SetInputValue("ContactType", isset($email) ? "EMAIL" : "MOBILE");
        $this->http->PostForm();

        $text = isset($email) ? "email address: {$email}" : "phone number: {$phone}";
        $question = "Please enter Identification Code which was sent to your {$text}. Please note that you must provide the latest code that was just sent to you. Previous codes will not work.";

        if (!$this->http->ParseForm("TFA-code-verfication-form")) {
            // Sorry, we can't send you a code at the moment due to a fault with our systems. Please try again later.
            if ($message = $this->http->FindSingleNode('//h4[contains(text(), "Sorry, we can\'t send you a code at the moment due to a fault with our systems.")]')) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
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

        if ($this->getWaitForOtc()) {
            $this->sendNotification("2fa - refs #20433 // RR");
        }

        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function parentLogin()
    {
        $this->logger->debug('[Parse. date: ' . date('Y/m/d H:i:s') . ']');
        //		if (!$this->http->PostForm())
//            return $this->checkErrors();

        // Sorry to interrupt you, we need to check you are a real person before you can continue.
//        if ($this->http->ParseForm("captcha_form")) {
//            $captcha = $this->parseCaptcha();
//            if ($captcha === false)
//                return false;
        ////            elseif (empty($captcha)) {
        ////                $this->http->Log("Login", LOG_LEVEL_ERROR);
        ////                throw new CheckRetryNeededException(4, 7, self::CAPTCHA_ERROR_MSG);
        ////            }
//            $this->http->SetInputValue('g-recaptcha-response', $captcha);
//            if (!$this->http->PostForm())
//                return $this->checkErrors();
//        }

        // Two Factor Authentication    // refs #14276
        if ($this->parseQuestion()) {
            return false;
        }

        // Confirm contact details
        if ($this->http->FindSingleNode("//p[contains(text(), 'confirm the details displayed')]")
            && ($link = $this->http->FindSingleNode("//a[contains(@href, 'main_nav&link=main_nav') and strong[contains(text(), 'My Executive Club')]]/@href"))) {
            $this->logger->notice("Skip update account details");
            $this->http->GetURL($link);
            $this->seleniumURL = $this->http->currentUrl();
        }
        // You have not yet validated your email address
        if ($this->http->FindPreg('/You have not yet validated your email address/ims')
            // We are currently missing the following information from your details
            || $this->http->FindPreg("/We are currently missing the following information from your details\. To keep your details up to date please complete\/amend the fields below/ims")
        ) {
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
            throw new \CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Please change your PIN to a password
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Please change your PIN to a password')]")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // We regret to advise that this section of the site is temporarily unavailable
        if ($message = $this->http->FindPreg("/t-logo-topic-content\">\s*<p>\s*([^<]+)/ims")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Please change your PIN to a password
        if ($this->http->FindSingleNode("//h1[contains(text(), 'Please change your PIN to a password')]")
            // Please could you confirm the details displayed, amend or supply them as necessary.
            || $this->http->FindSingleNode("//div[contains(text(), 'Please could you confirm the details displayed, amend or supply them as necessary.')]")
            // We have had problems delivering information to you.
            || $this->http->FindSingleNode("//p[contains(text(), 'We have had problems delivering information to you.')]")
            // Overiew page
            || $this->http->FindSingleNode("//h1[contains(text(), 'Welcome to your Executive Club')]")
            || $this->http->FindSingleNode("//h2[contains(text(), 'Welcome to British Airways')]")
        ) {
            // throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            $this->http->GetURL('https://www.britishairways.com/travel/echome/execclub/_gf/en_' . $this->getCountryCode() . '?link=main_nav');

            if ($this->http->Response['code'] == 403) {
                $this->http->GetURL('https://www.britishairways.com/travel/myaccount/execclub/_gf/en_' . $this->getCountryCode() . '/home');
            }
        }

        $notError = $this->http->FindPreg("/(Welcome to)/ims");

        if (
            (isset($notError) && $this->seleniumURL !== 'https://www.britishairways.com/travel/loginr/public/en_gb?eId=109001')
            || $this->http->FindSingleNode("(//a[contains(text(), 'Log out')])[1]")
        ) {
            $this->markProxySuccessful();

            return true;
        }

        // Invalid password
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'We are not able to recognise the')]")) {
            if ($this->isRewardAvailability) {
                throw new \CheckException($message, ACCOUNT_LOCKOUT);
            }

            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The username and password do not match what is held on our system')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'You had more than one Executive Club account')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'It has not been possible to log in as our records')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'We are unable to find your username')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Password cannot be e-mailed as no email address is present')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The Login ID you have entered does not match what is held on our system')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'The userid/password you have entered is not correct')])[1]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Sorry, we don\'t recognise the membership number or PIN/password you have entered")])[1]')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Sorry, we don\'t recognise the email address you have entered")])[1]')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "The email address you have entered is already being used on another Executive Club account")])[1]')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "You are requested to change your password. Please enter a new password.")])[1]')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Invalid frequent flyer status.")])[1]')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Unable to process request please retry later.
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Unable to process request please retry later")])[1]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Error While getting customer details
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "Error While getting customer details")])[1]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there seems to be a technical problem
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Sorry, there seems to be a technical problem")]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, there's a problem with our systems. Please try again, and if it still doesn't work, you might want to try again later.
        if ($message = $this->http->FindSingleNode('//li[contains(text(), "Sorry, there\'s a problem with our systems. Please try again, and if it still doesn\'t work, you might want to try again later.")]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# There is a problem in logging into the system. Please try later.
        if ($message = $this->http->FindPreg("/(There is a problem in logging into the system\.\s*Please try later\.)/ims")) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Please update your details
        if ($this->http->FindSingleNode("//span[contains(text(), 'We are currently missing the following information from your details')]")) {
            $this->throwProfileUpdateMessageException();
        }
        //# You have made too many invalid login attempts
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'You have made too many invalid login attempts') or contains(text(), 'Your account is now locked for up to 24 hours')]")) {
            throw new \CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your account has been locked due to too many invalid login attempts.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your account has been locked due to too many invalid login attempts.')]")) {
            throw new \CheckException($message, ACCOUNT_LOCKOUT);
        }
        /*
         * Your account is temporarily unavailable
         *
         * We have locked your account temporarily to keep it safe and secure.
         * For more information please refer to the email or letter you received from us.
         */
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'We have locked your account temporarily to keep it safe and secure.')]")) {
            throw new \CheckException($message, ACCOUNT_LOCKOUT);
        }
        // Your user id has been locked
        if ($message = $this->http->FindSingleNode("(//*[contains(text(), 'Your user id has been locked')])[1]")) {
            throw new \CheckException($message, ACCOUNT_LOCKOUT);
        }
        // We're sorry, but ba.com is very busy at the moment, and couldn't deal with your request.
        if ($message = $this->http->FindSingleNode('(//*[contains(text(), "We\'re sorry, but ba.com is very busy at the moment, and couldn\'t deal with your request.")])[1]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Oops, this page isn&rsquo;t available right now...")]')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "Error 403 - You don\'t have enough permissions to proceed further")]')) {
            $this->DebugInfo = "request has been blocked";
            $this->ErrorReason = self::ERROR_REASON_BLOCK;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "warning") and not(@hidden)]')) {
            $this->logger->error("[Error]: {$message}");

            if (strpos($message, 'Sorry, something went wrong. Please try again or') !== false) {
                throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//button[@id = "ecuserlogbutton"]')) {
            throw new \CheckRetryNeededException(2, 3);
        }

        return $this->checkErrors();
    }

    public function Login()
    {
        try {
            $isLogin = $this->parentLogin();
        } catch (\Exception $e) {
            $isLogin = false;

            throw $e;
        } finally {
            if (!$isLogin && isset($this->memSelenium)) {
                $this->memSelenium->keepSession(false);

                if (isset($this->memBrowser, $this->memRegion)) {
                    $this->State['mem-browser'] = $this->memBrowser;
                    $this->State['mem-region'] = $this->memRegion;
                }
                $this->memSelenium->http->cleanup();
            }
        }

        if (!$isLogin && (time() - $this->requestDateTime) < $this->AccountFields['Timeout'] - 25) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $isLogin;
    }

    public function ParseRewardAvailability(array $fields)
    {
        try {
            $this->logger->info("Parse Reward Availability", ['Header' => 2]);
            $this->logger->debug("params: " . var_export($fields, true));

            $this->logger->notice(
                'parsing started at: ' . date("H:i:s", $this->requestDateTime)
            );

            $this->inDateDep = $fields['DepDate'];

            if ($fields['DepDate'] > strtotime('+355 day')) {
                $this->SetWarning('too late');

                return [];
            }

            if ($fields['Currencies'][0] !== 'USD') {
                $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
                $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
            }
            $this->inCabin = $fields['Cabin'];
            $fields['Cabin'] = $this->getCabinFields(false)[$fields['Cabin']];
            $countryCode = $this->getCountryCode();

            $this->depDateIn = $fields['DepDate'];

            if ($countryCode === 'us') {
                $fields['DepDate'] = date("m/d/y", $fields['DepDate']);
            } else {
                $fields['DepDate'] = date("d/m/y", $fields['DepDate']);
            }

            return ['routes' => $this->ParseReward($fields)];
        } finally {
            if (isset($this->memSelenium)) {
                $this->memSelenium->http->cleanup();
            }
        }
    }

    protected function parseHCaptcha($currentUrl)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@data-captcha-provider="hcaptcha"]/@data-captcha-sitekey');

        if (!$key) {
            return false;
        }
        /*
                $postData = [
                    "type"         => "HCaptchaTaskProxyless",
                    "websiteURL"   => $currentUrl ?? $this->http->currentUrl(),
                    "websiteKey"   => $key,
                ];
                $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
                $recognizer->RecognizeTimeout = 120;

                return $this->recognizeAntiCaptcha($recognizer, $postData);
        */
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

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@name = 'captcha_form']//div[@class = 'g-recaptcha']/@data-sitekey");
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $postData = [
            "type"         => "RecaptchaV2TaskProxyless",
            "websiteURL"   => $this->http->currentUrl(),
            "websiteKey"   => $key,
        ];
        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $this->recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($this->recognizer, $postData);
    }

    private function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);

        $selenium->http->SaveResponse();

        try {
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }

        $this->http->SaveResponse();
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'Economy',
            'premiumEconomy' => 'Premium economy',
            'firstClass'     => 'First',
            'business'       => 'Business Class',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function ParseReward($fields = [], ?bool $isRetry = false)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . $fields['DepDate'] . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        if (strcasecmp($fields['Cabin'], 'Business/Club') === 0
            || strcasecmp($fields['Cabin'], 'Business Class') === 0
        ) {
            $cabin = 'Business Class';
            $fields['Cabin'] = 'Business/Club';
        } else {
            $cabin = ucwords(strtolower($fields['Cabin']));
            $fields['Cabin'] = ucfirst(strtolower($fields['Cabin']));
        }
//        $this->http->GetURL("https://www.britishairways.com/travel/redeem/execclub?eId=106019&tab_selected=redeem&redemption_type=STD_RED");
        $countryCode = $this->getCountryCode();
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 30);

        if ($this->isBadProxy()) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (!$this->http->ParseForm("plan_redeem_trip")) {
            $this->logger->debug('try again');
            $this->http->GetURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}?eId=106019&tab_selected=redeem&redemption_type=STD_RED", [], 30);

            if ($this->isBadProxy()) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm("plan_redeem_trip")) {
            $this->logger->error('check parse');

            if ($this->http->Response['code'] == 403) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            throw new \CheckException('not load plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
        }

        if (!$this->fillRedeemTrip($fields, $cabin)) {
            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->postRedeemTrip($isRetry, $fields, $cabin);

        if ($this->http->FindPreg("/There was a problem with your request, please try again later./")
            && ($link = $this->http->FindSingleNode("//a[normalize-space()='Start again']/@href"))
        ) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($msg = $this->http->FindSingleNode("//text()[contains(normalize-space(),'a problem with our systems. Please try again, and if it still doesn')]")) {
            $this->logger->error($msg);

            if ($this->attempt === 0) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->http->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
            $this->logger->error($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }

        if ($msg = $this->http->FindSingleNode("//h3[contains(.,'There is no availability on British Airways for the displayed date')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($this->http->FindSingleNode("//h2[contains(.,'Choose your travel dates')]")) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($msg = $this->http->FindSingleNode("//h3[contains(.,'If there’s no availability on your chosen dates you can') or contains(.,\"If there's no availability on your chosen dates you can\")]")) {
            $this->SetWarning('There’s no availability on your chosen dates');

            return [];
        }

        if ($msg = $this->http->FindSingleNode("//div[@id='blsErrors']//li[contains(.,'There was a problem with your request, please try again later')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode('//div[@id="blsErrors"]//li[contains(.,"It looks like you\'re using multiple tabs") or contains(.,"Resource Not Found Error Received from ASSOCIATE API from AGL Group Loyalty Platform")]')) {
            $this->logger->error($msg);

            if (!$isRetry) {
                return $this->ParseReward($fields, true);
            }

            if ($this->attempt === 0) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
        }

        if ($msg = $this->http->FindSingleNode("//div[@id='blsErrors']//li[contains(.,'British Airways and its partners do not fly this route. Please consider alternative destinations')]")) {
            $this->SetWarning($msg);

            return [];
        }

        if ($msg = $this->http->FindSingleNode("(//div[@id='blsErrors']//li[normalize-space()!=''])[1]")) {
            $this->logger->error($msg);

            if ($this->http->FindPreg("/Sorry, it is only possible to book flights up to 355 days in advance/",
                false, $msg)) {
                $this->SetWarning($msg);

                return [];
            }

            if ($this->http->FindPreg("/Not able to connect to AGL Group Loyalty Platform and IO Error Recieved/",
                false, $msg)) {
                $this->SetWarning('No availability on your chosen dates');

                return [];
            }

            if ($this->http->FindPreg("/We're sorry, but ba.com is very busy at the moment, and couldn't deal with your request. Please do try again/",
                false, $msg)) {
                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/Internal Server Error Received from IAGL API ASSOCIATE/", false, $msg)) {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if ($this->http->FindPreg("/Sorry, something went wrong. Please try again or contact us/", false, $msg)) {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if ($this->http->FindPreg("/(Web Service Query Error|Web Service Connection Error)/", false, $msg)) {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/(?:We are encountering a temporary fault, please try again. If the problem persists, please contact your|Sorry, something went wrong. Please try again or contact us)/",
                false, $msg)) {
                if (time() - $this->requestDateTime < $this->AccountFields['Timeout'] - 5) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/There is currently no access to your account while we upgrade our system./",
                false, $msg)) {
                if ($this->attempt !== 4 && (time() - $this->requestDateTime < $this->AccountFields['Timeout'] - 5)) {
                    throw new \CheckRetryNeededException(5, 10);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
            $this->sendNotification("check msg // ZM");
        }

        try {
            $routes = $this->parseRewardFlights($fields, $cabin);
        } catch (\CheckException $e) {
            if ($e->getMessage() === 'wrong format: departInputDate') {
                if (!$isRetry) {
                    return $this->ParseReward($fields, true);
                }

                throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
            }

            throw $e;
        }

        return $routes;
    }

    private function fillRedeemTrip($fields, $cabin): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue("departurePoint", $fields['DepCode']);
        $this->http->SetInputValue("destinationPoint", $fields['ArrCode']);
        $this->http->SetInputValue("departInputDate", $fields['DepDate']);
        $this->http->SetInputValue("oneWay", "true");

        $cabinNames = $this->http->FindPreg('/data-cabinNames="([^"]+)" data-cabinCodes="[\w:]+"/');
        $cabinCodes = $this->http->FindPreg('/data-cabinCodes="([^"]+)"/');

        if (empty($cabinNames) || empty($cabinNames)) {
            $this->sendNotification('check cabin codes');

            throw new \CheckException('other cabin codes', ACCOUNT_ENGINE_ERROR);
        }
        $cabinNames = explode(":", $cabinNames);
        $cabinCodes = explode(":", $cabinCodes);
        $cabins = array_combine($cabinNames, $cabinCodes);
        $this->logger->notice(var_export($cabin, true));
        $this->logger->notice(var_export($cabins, true));

        if (!isset($cabins[$fields['Cabin']]) && !isset($cabins[$cabin])) {
            $this->logger->notice('change cabin');

            if ($fields['Cabin'] === 'Premium economy') {
                $fields['Cabin'] = 'Economy';
            }
        }

        if (!isset($cabins[$fields['Cabin']]) && !isset($cabins[$cabin])) {
            return false;
        }
        $this->http->SetInputValue("CabinCode", $cabins[$fields['Cabin']] ?? $cabins[$cabin]);
        $this->http->SetInputValue("NumberOfAdults", $fields['Adults']);
        $this->http->SetInputValue("NumberOfYoungAdults", 0);
        $this->http->SetInputValue("NumberOfChildren", 0);
        $this->http->SetInputValue("NumberOfInfants", 0);
        $this->http->Form['DEVICE_TYPE'] = 'DESKTOP';

        if (isset($this->http->Form['returnInputDate'])) {
            unset($this->http->Form['returnInputDate']);
        }

        if (isset($this->http->Form['upgradeInbound'])) {
            unset($this->http->Form['upgradeInbound']);
        }

        if (isset($this->http->Form['upgrade_redemption_type'])) {
            unset($this->http->Form['upgrade_redemption_type']);
        }

        return true;
    }

    private function postRedeemTrip($isRetry, $fields, $cabin): void
    {
        $this->logger->notice(__METHOD__ . ' with isRetry=' . var_export($isRetry, true));

        if (!$this->http->PostForm()) {
            $this->checkErrors();

            throw new \CheckException('error post plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
        }

        if ($this->http->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->FindPreg("/Validation question/") && $this->http->ParseForm("captcha_form")) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);

            if (!$this->http->PostForm()) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($this->http->FindPreg("/Validation question/")
                && $this->http->ParseForm("captcha_form")
                && $this->http->FindSingleNode("//p[normalize-space(text())='You did not validate successfully. Please try again.']")
            ) {
                $this->captchaReporting($this->recognizer, false);
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
                $this->http->SetInputValue('g-recaptcha-response', $captcha);

                if (!$this->http->PostForm()) {
                    throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }
        }

        if (!$this->http->ParseForm("SubmitFromInterstitial")) {
            if ($this->http->FindSingleNode("//li[contains(.,\"It looks like you're using multiple tabs in the same browser. Please use a single tab to continue.\")]")) {
                if (!$this->http->ParseForm("plan_redeem_trip")) {
                    $this->logger->error('check parse');

                    if ($msg = $this->http->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
                        $this->logger->error($msg);

                        throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                    }

                    throw new \CheckException('not load plan_redeem_trip', ACCOUNT_ENGINE_ERROR);
                }

                if (!$isRetry && $this->fillRedeemTrip($fields, $cabin)) {
                    $this->postRedeemTrip(true, $fields, $cabin);

                    return;
                }
                $this->logger->notice('failed retry, try restart');

                throw new \CheckRetryNeededException(5, 0);
            }

            if ($this->http->FindSingleNode("//p[contains(normalize-space(),'You did not validate successfully. Please try again')]")) {
                // TODO m/b it's better retry
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->error('check parse');

            if ($msg = $this->http->FindSingleNode("//text()[contains(.,'Sorry, there seems to be a technical problem. Please try again in a few minutes')]/ancestor::*[1]")) {
                $this->logger->error($msg);

                if ($this->attempt == 0) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckException('no form SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
        }
        $eventId = $this->http->FindPreg("/var eventId = '(\d+)';/");

        if (empty($eventId)) {
            $this->logger->error('can\'t find eventId');

            throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
        }
        $this->http->SetInputValue("eId", $eventId);

        if (!$this->http->PostForm()) {
            $this->checkErrors();

            throw new \CheckException('error post SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
        }

        if ($this->http->FindPreg("/Validation question/") && $this->http->ParseForm("captcha_form")) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $this->http->SetInputValue('g-recaptcha-response', $captcha);

            if (!$this->http->PostForm()) {
                throw new \CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }
            $eventId = $this->http->FindPreg("/var eventId = '(\d+)';/");

            if ($this->http->ParseForm("SubmitFromInterstitial")) {
                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId (2)');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }

                if (!$this->http->PostForm()) {
                    $this->checkErrors();

                    throw new \CheckException('error post SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
                }
            }

            if ($this->http->FindPreg("/Validation question/") && $this->http->ParseForm("captcha_form")) {
                throw new \CheckException('walks in a circle', ACCOUNT_ENGINE_ERROR);
            }
        }

        if ($this->http->ParseForm("plan_trip")) {
            if ($this->http->Form['departurePoint'] !== $fields['DepCode']
                || $this->http->Form['destinationPoint'] !== $fields['ArrCode']
                || $this->http->Form['departInputDate'] !== $fields['DepDate']
            ) {
                // sometimes captcha broke request, fix it
                $this->http->Form['departurePoint'] = $fields['DepCode'];
                $this->http->Form['destinationPoint'] = $fields['ArrCode'];
                $this->http->Form['departInputDate'] = $fields['DepDate'];
            }
            // no stopovers
            if (!$this->http->PostForm()) {
                $this->checkErrors();

                throw new \CheckException('error post plan_trip', ACCOUNT_ENGINE_ERROR);
            }

            if ($this->http->ParseForm("SubmitFromInterstitial")) {
                $eventId = $this->http->FindPreg("/var eventId = '(\d+)';/");

                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }
                $this->http->SetInputValue("eId", $eventId);

                if (!$this->http->PostForm()) {
                    $this->checkErrors();

                    throw new \CheckException('error post SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
                }
            }
        }

        if ($this->http->ParseForm("stopOverForm")) {
            // no stopovers
            if (!$this->http->PostForm()) {
                $this->checkErrors();

                throw new \CheckException('error post stopOverForm', ACCOUNT_ENGINE_ERROR);
            }

            if ($this->http->ParseForm("SubmitFromInterstitial")) {
                $this->logger->error('check parse');
                $eventId = $this->http->FindPreg("/var eventId = '(\d+)';/");

                if (empty($eventId)) {
                    $this->logger->error('can\'t find eventId');

                    throw new \CheckException('can\'t find eventId', ACCOUNT_ENGINE_ERROR);
                }
                $this->http->SetInputValue("eId", $eventId);

                if (!$this->http->PostForm()) {
                    $this->checkErrors();

                    throw new \CheckException('error post SubmitFromInterstitial', ACCOUNT_ENGINE_ERROR);
                }
            }
        }

        if ($this->http->ParseForm("plan_redeem_trip") && $this->http->FindSingleNode("//li[normalize-space()='Book with Avios']")
            && !$this->http->FindSingleNode("//div[@id='blsErrors']")
        ) {
            if (!$isRetry && $this->fillRedeemTrip($fields, $cabin)) {
                $this->postRedeemTrip(true, $fields, $cabin);

                return;
            }

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function parseRewardFlights($fields, $cabinIn): array
    {
        $countryCode = $this->getCountryCode();
        $routes = [];
        // if no filter by cabin
        $xpathAllCabins = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')]/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";

        // if filter by cabin
        $xpath = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";
        // if filter by exclude selected cabin
        $xpathExclude = "//div[contains(@class,'flight-cabin-detail')][./span[contains(@class,'travel-class')][normalize-space()!='{$cabinIn}']/following-sibling::div[1][not(contains(.,'Not Available') or contains(.,'Cabin not operated on this flight'))]]";

        $Roots = $this->http->XPath->query($rootStr = "//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpath}]");
        $this->logger->debug("Found {$Roots->length} routes");
        $this->logger->warning($rootStr);

        if ($Roots->length === 0 && $this->http->FindSingleNode("//li[@data-date-value='{$this->inDateDep}000']/a/span[normalize-space()='No availability']")) {
            $this->SetWarning('No availability');

            return [];
        }
        $formToken = null;
        $flightDetails = [];

        // check date of result
        $segmentsRoot_0 = $this->http->XPath->query(".//div[contains(@class,'travel-time-detail')]", $Roots->item(0));

        if ($segmentsRoot_0->length > 0) {
            $r = $segmentsRoot_0->item(0);
            $date = $this->http->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
            $date1 = $this->http->FindSingleNode("//li[contains(@class,'active-tab')]//span[contains(@class,'datemonth')]");
            $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));
            $depDate1 = EmailDateHelper::parseDateRelative($date1, strtotime('-1 day'));

            if ($depDate !== $this->depDateIn && $depDate1 !== $this->depDateIn) {
                $this->logger->debug('segment Date' . var_export($depDate, true));
                $this->logger->debug('checked Date' . var_export($depDate1, true));
                $this->logger->debug('input Date' . var_export($this->depDateIn, true));

                throw new \CheckException('wrong format: departInputDate', ACCOUNT_ENGINE_ERROR);
            }
        }
        $skippedPrice = null;
        $routesProblemWithBooking = $routesTechnicalProblem = [];

        $wasBreak = $this->collectRoutes($Roots, $xpath, $fields, $countryCode, false, $routes, $formToken, $skippedPrice, $flightDetails, $routesProblemWithBooking, $routesTechnicalProblem);

        if ($wasBreak) {
            return $routes;
        }

        if ((time() - $this->requestDateTime) < $this->AccountFields['Timeout'] || empty($routes)) {
            $this->logger->warning('collect flights with other cabins');
            $Roots = $this->http->XPath->query($rootStr = "//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpathExclude}]");
            $this->logger->debug("Found {$Roots->length} routes");
            $this->logger->warning($rootStr);

            if (empty($routes) && $Roots->length > 0) {
                $segmentsRoot_0 = $this->http->XPath->query(".//div[contains(@class,'travel-time-detail')]",
                    $Roots->item(0));
                $r = $segmentsRoot_0->item(0);
                $date = $this->http->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
                $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                if ($depDate !== $this->depDateIn) {
                    $this->logger->debug(var_export($depDate, true));
                    $this->logger->debug(var_export($this->depDateIn, true));

                    throw new \CheckException('wrong format: departInputDate', ACCOUNT_ENGINE_ERROR);
                }
            }
            $wasBreak = $this->collectRoutes($Roots, $xpathExclude, $fields, $countryCode, true, $routes, $formToken, $skippedPrice, $flightDetails, $routesProblemWithBooking, $routesTechnicalProblem);

            if ($wasBreak) {
                return $routes;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        if (empty($routes)) {
            $this->SetWarning('No results');
        }

        $Roots = $this->http->XPath->query("//div[@class='direct-flight-details' or @class='conn-flight-details'][.{$xpathAllCabins}]");

        if (isset($skippedPrice) && empty($routes) && $Roots->length) {
            $routesProblemWithBooking = array_unique($routesProblemWithBooking);

            if ($Roots->length === count($routesProblemWithBooking)) {
                throw new \CheckException('Sorry, there’s a problem with booking this journey online. You can try selecting other flights or contact us so an agent can help you book the flights you have selected', ACCOUNT_PROVIDER_ERROR);
            }

            $routesTechnicalProblem = array_unique($routesTechnicalProblem);

            if ($Roots->length === count($routesTechnicalProblem)) {
                throw new \CheckException('Sorry, there seems to be a technical problem. Please try again in a few minutes', ACCOUNT_PROVIDER_ERROR);
            }

            throw new \CheckException("can't get prices", ACCOUNT_ENGINE_ERROR);
        }

        return $routes;
    }

    private function collectRoutes(
        $Roots,
        $xpath,
        $fields,
        $countryCode,
        $checkLimit,
        &$routes,
        &$formToken,
        &$skippedPrice,
        &$flightDetails,
        &$routesProblemWithBooking,
        &$routesTechnicalProblem
    ): bool {
        $this->logger->notice(__METHOD__);

        foreach ($Roots as $numRoot => $root) {
            if ($checkLimit && !empty($routes) && (time() - $this->requestDateTime) > $this->AccountFields['Timeout']) {
                return false;
            }
            $result = ['connections' => []];

            $segmentsRoot = $this->http->XPath->query(".//div[contains(@class,'travel-time-detail')]", $root);

            if ($segmentsRoot->length === 0) {
                $this->sendNotification("check segments");

                continue;
            }
            $offerList = $this->http->XPath->query(".{$xpath}", $root);
            $offers = [];

            foreach ($offerList as $offer) {
                $cabinStr = $this->http->FindSingleNode("./span[contains(@class,'travel-class')]", $offer);

                $cabinKey = $this->http->FindSingleNode("./span[contains(@class,'travel-class')]/following-sibling::div[1]//div[contains(@id,'DtlOuterDivRadio')]/@id", $offer, false, '/DtlOuterDivRadio(\d-\d-\w)\s*$/');

                $cabinSeg = $this->http->FindNodes(".//div[contains(@class,'travel-time-detail')]//p[contains(@class,'cabinName')]/span[contains(@id,'cbnName{$cabinKey}')]", $root);

                if (empty($cabinSeg)) {
                    $this->sendNotification('check cabins // ZM');
                }

                $cabin = array_search($cabinStr, $this->getCabinFields(false));

                if (empty($cabin)) {
                    $cabin = array_search(ucfirst(strtolower($cabinStr)), $this->getCabinFields(false));
                }
                $tickets = $this->http->FindSingleNode(".//span[@class='message-number-of-seats']", $offer, false,
                    "/(\d+) left/i");

                $paramForOutbound = $this->http->FindSingleNode(".//input[@name='0']/@value", $offer);
                $paramForInbound = '';
                $eId = $this->http->FindSingleNode("//*[@id='hdnEIdSB']/@value");
                $stopover = json_encode($this->http->FindSingleNode("//*[@id='Stopover']/@value") === 'true');
                $hostAirlineCode = $this->http->FindSingleNode("//*[@id='HostAirlineCode']/@value");
                $BAOnlyRoute = $this->http->FindSingleNode("//*[@id='BAOnlyRoute']/@value");
                $BANotOperateFullOrPartial = $this->http->FindSingleNode("//*[@id='BANotOperateFullOrPartial']/@value");
                $hostAirline = $this->http->FindSingleNode("//*[@id='HostAirline']/@value");

                if (!isset($formToken)) {
                    $formToken = $this->http->FindSingleNode("//*[@id='formToken']/@value");
                }

                $http2 = clone $this->http;
                $http2->FilterHTML = false;
                $this->http->brotherBrowser($http2);

                $headers = [
                    'Accept'           => '*/*',
                    'Origin'           => 'https://www.britishairways.com',
                    'Referer'          => 'https://www.britishairways.com/travel/redeem/execclub/_gf/en_' . $countryCode,
                    'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With' => 'XMLHttpRequest',
                ];
                $x_dtpc = $http2->getCookieByName('dtPC');

                if (!empty($x_dtpc)) {
                    $headers['x-dtpc'] = $x_dtpc;
                }
                $zeroParam = $paramForOutbound . $paramForInbound;
                $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                    $payload, $headers);

                if ($msg = $http2->FindSingleNode("//li[contains(.,'Sorry, there’s a problem with booking this journey online')]")) {
                    $this->logger->error($msg);
                    $routesProblemWithBooking[] = $numRoot;
                    $noPrice = true;
                } elseif (empty($http2->FindSingleNode("//span[@class='totalPriceAviosTxt']"))
                    && $http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") === 'false') {
                    $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                    if (empty($formToken)) {
                        $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                    }
                    $this->logger->debug('formToken for retry: ' . $formToken);

                    if (!empty($formToken)) {
                        $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                        sleep(2); //sometimes works
                        $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                            $payload, $headers);
                    }

                    if ($http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") === 'false') {
                        $this->logger->error('empty price');
                        $noPrice = true;
                    }
                }

                $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                if (empty($price) && ($msg = $http2->FindSingleNode("//p[starts-with(normalize-space(),'Sorry, there seems to be a technical problem. Please try again in a few minutes, and')]"))) {
                    sleep(2);
                    $payload = "0={$zeroParam}&&eId={$eId}&&Stopover={$stopover}&&HostAirlineCode={$hostAirlineCode}&&BAOnlyRoute={$BAOnlyRoute}&&BANotOperateFullOrPartial={$BANotOperateFullOrPartial}&&HostAirline=$hostAirline&&FormToken={$formToken}";
                    $http2->PostURL("https://www.britishairways.com/travel/redeem/execclub/_gf/en_{$countryCode}/device-all",
                        $payload, $headers);
                    $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                    if (empty($price)) {
                        $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                        if (empty($formToken)) {
                            $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                        }

                        if (empty($formToken)) {
                            $this->logger->error($msg);
                            $this->logger->error('empty price');

                            if (!isset($prevToken)) {
                                if (count($routes) > 0) {
                                    return true;
                                }

                                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                            }
                            $formToken = $prevToken;
                            $this->logger->debug("skip journey online");
                            $skippedPrice = true;
                            $routesTechnicalProblem[] = $numRoot;

                            continue;
                        }

                        $msg = $http2->FindSingleNode("//p[starts-with(normalize-space(),'Sorry, there seems to be a technical problem. Please try again in a few minutes, and')]");

                        if ($msg) {
                            $this->logger->error($msg);
                            $this->logger->debug("skip journey online");
                            $skippedPrice = true;
                            $routesTechnicalProblem[] = $numRoot;

                            continue;
                        }

                        if ($http2->FindSingleNode("//div[@name='cacheKeyList']/following-sibling::span[1]") !== 'false') {
                            $this->sendNotification('retry after technical problem on getPrice - helped // ZM');
                        }
                    }
                }
                $price = $http2->FindSingleNode("//span[@class='totalPriceAviosTxt']");

                $prevToken = $formToken;
                $formToken = $http2->FindSingleNode("//div[@name='totalPriceAvios']/@data-formToken");

                if (empty($formToken)) {
                    $formToken = $http2->FindPreg("/name=\"totalPriceAvios\" data-formToken=\"(\d+)\">/");
                }

                if (empty($formToken)) {
                    $this->logger->error('check formToken');

                    if (!empty($prevToken) && (!isset($tryWithPrevToken) || $tryWithPrevToken <= 2)) {
                        $tryWithPrevToken = isset($tryWithPrevToken) ? $tryWithPrevToken + 1 : 0;
                        $formToken = $prevToken;
                        $skippedPrice = true;
                    } elseif (count($routes) > 0 && isset($tryWithPrevToken) && $tryWithPrevToken > 2) {
                        return true;
                    } else {
                        throw new \CheckException('problem with formToken', ACCOUNT_ENGINE_ERROR);
                    }
                }

                if (isset($noPrice)) {
                    $skippedPrice = true;
                    $noPrice = null;
                    $this->logger->debug("skip journey online");

                    continue;
                }

                if (empty($price)) {
                    $this->logger->error('skip offer. price not found');

                    continue;
                }
                $offers[$cabin] = ['price' => $price, 'tickets' => $tickets, 'cabinSeg' => $cabinSeg];
            }
            $stop = -1;
            $price = null;
            $layover = null;
            $totalFlight = null;

            foreach ($segmentsRoot as $i => $r) {
                $stop++;

                $depTime = $this->http->FindSingleNode("./div[1]/descendant::p[contains(@class,'time')][1]", $r);
                $date = $this->http->FindSingleNode("./div[1]/descendant::p[contains(@class,'date')][1]", $r);
                $depDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                $arrTime = $this->http->FindSingleNode("./div[2]/descendant::p[contains(@class,'time')][1]", $r);
                $date = $this->http->FindSingleNode("./div[2]/descendant::p[contains(@class,'date')][1]", $r);
                $arrDate = EmailDateHelper::parseDateRelative($date, strtotime('-1 day'));

                $seg = [
                    'num_stops' => $this->http->FindSingleNode("./div[3]//p[contains(.,'Stops')]", $r, false, "/Stops:\s*(\d+)/") ?? 0,
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($depTime, $depDate)),
                        'dateTime' => strtotime($depTime, $depDate),
                        'airport'  => $this->http->FindSingleNode("./div[1]/div[@class='airport-box']/p[1]", $r),
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($arrTime, $arrDate)),
                        'dateTime' => strtotime($arrTime, $arrDate),
                        'airport'  => $this->http->FindSingleNode("./div[2]/div[@class='airport-box']/p[1]", $r),
                    ],
                    'cabin'   => null,
                    'flight'  => [$this->http->FindSingleNode("./div[3]//a/span[2]", $r)],
                    'airline' => $this->http->FindSingleNode("./div[3]//a/span[2]", $r, false,
                        '/^([A-Z\d]{2})\s*\d+$/'),
                    'distance' => null,
                ];

                $stop += $seg['num_stops'];
                $result['connections'][] = $seg;
            }

            foreach ($offers as $cabin => $data) {
                $fees = $this->http->FindPreg("/\d+\s+Avios\s+\+\s+\D+?(\d[\d.,]+)$/", false, $data['price']);
                $currency = $this->http->FindPreg("/\d+\s+Avios\s+\+\s+(\D+?)\d[\d.,]+$/", false, $data['price']);
                $this->logger->debug("parsed fees: " . $fees);
                $this->logger->debug("parsed currency: " . $currency);

                if ($currency === '¥') {
                    $currency = 'JPY';
                }
                $totalTravel = null;
                $headData = [
                    'distance'  => null,
                    'num_stops' => $stop,
                    'times'     => [
                        'flight'  => $totalTravel,
                        'layover' => $layover,
                    ],
                    'redemptions' => [
                        'miles' => intdiv($this->http->FindPreg("/(\d+)\s+Avios/", false, $data['price']),
                            $fields['Adults']),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $this->currency($currency),
                        'taxes'    => null,
                        'fees'     => round(\AwardWallet\Common\Parser\Util\PriceHelper::cost($fees) / $fields['Adults'],
                            2),
                    ],
                ];

                if (empty($headData['payments']['currency'])) {
                    // TODO может стоит ретрай с обновленным formToken
                    $this->logger->error('can\'t determine currency');
                    $this->DebugInfo = 'can\'t determine currency';

                    if (count($routes) > 0) {
                        return true;
                    }

                    throw new \CheckException('Something went wrong', ACCOUNT_ENGINE_ERROR);
                }

                $offerResult = $result;

                foreach ($result['connections'] as $i => $seg) {
                    if (isset($data['cabinSeg'][$i])) {
                        $offerResult['connections'][$i]['cabin'] = array_search($data['cabinSeg'][$i],
                            $this->getCabinFields(false));

                        if (empty($offerResult['connections'][$i]['cabin'])) {
                            $offerResult['connections'][$i]['cabin'] = $cabin;
                        }
                        $offerResult['connections'][$i]['classOfService'] = $this->clearCOS($data['cabinSeg'][$i]);
                    } else {
                        $offerResult['connections'][$i]['cabin'] = $cabin;
                        $offerResult['connections'][$i]['classOfService'] = $this->clearCOS($this->getCabinFields(false)[$cabin]);
                    }
                }
                $res = array_merge($headData, $offerResult);
                $res['tickets'] = $data['tickets'];
                $res['classOfService'] = $this->clearCOS($this->getCabinFields(false)[$cabin]);
                $this->logger->debug(var_export($res, true), ['pre' => true]);
                $routes[] = $res;
            }
        }

        return false;
    }

    private function clearCOS(string $cos): string
    {
        if (preg_match("/^(.+\w+) (?:class)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function isBadProxy(): bool
    {
        return strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 0 -') !== false
            || strpos($this->http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($this->http->Error, 'Operation timed out after') !== false;
    }
}
