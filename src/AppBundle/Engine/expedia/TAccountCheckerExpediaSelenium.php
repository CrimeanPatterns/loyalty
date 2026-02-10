<?php

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\expedia\AuthException;
use AwardWallet\Engine\expedia\QuestionAnalyzer;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerExpediaSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    protected TAccountCheckerExpedia $expedia;
    private HttpBrowser $browser;
    public string $host, $langEnUrl;
    public string $provider = 'expedia';
    private bool $isLoggedIn = false;
    private $currentItin = 0;
    /**
     * @var mixed
     */
    private string $currency = '';

    function setHost($region = null)
    {
        $this->logger->notice(__METHOD__);

        if ($this->provider == 'expedia') {
            switch ($region ?? null) {
                case 'AR':
                    $this->host = 'www.expedia.com.ar';
                    $this->langEnUrl = 'https://www.expedia.com.ar/?langid=1033&pwaDialog=disp-settings-picker';
                    break;
                case 'AU':
                    $this->host = 'www.expedia.com.au';
                    break;
                case 'AT':
                    $this->host = 'www.expedia.at';
                    break;
                case 'BE':
                    $this->host = 'www.expedia.be';
                    $this->langEnUrl = 'https://www.expedia.be/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'BR':
                    $this->host = 'www.expedia.com.br';
                    $this->langEnUrl = 'https://www.expedia.com.br/?langid=1033&pwaDialog=disp-settings-picker';
                    break;
                case 'CA':
                    $this->host = 'www.expedia.ca';
                    break;
                case 'EU':
                    $this->host = 'euro.expedia.net';
                    break;
                case 'DK':
                    $this->host = 'www.expedia.dk';
                    break;
                case 'FI':
                    $this->host = 'www.expedia.fi';
                    break;
                case 'FR':
                    $this->host = 'www.expedia.fr';
                    $this->langEnUrl = 'https://www.expedia.fr/en/?langid=2057';
                    break;
                case 'DE':
                    $this->host = 'www.expedia.de';
                    $this->langEnUrl = 'https://www.expedia.de/en/?langid=2057';
                    break;
                case 'HK':
                    $this->host = 'www.expedia.com.hk';
                    $this->langEnUrl = 'https://www.expedia.com.hk/en/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'IN':
                    $this->host = 'www.expedia.co.in';
                    break;
                case 'India':
                case 'ID':
                    $this->host = 'www.expedia.co.id';
                    $this->langEnUrl = 'https://www.expedia.co.id/en/?langid=2057&';
                    break;
                case 'IE':
                    $this->host = 'www.expedia.ie';
                    break;
                case 'IT':
                    $this->host = 'www.expedia.it';
                    $this->langEnUrl = 'https://www.expedia.it/en/?langid=2057&';
                    break;
                case 'JP':
                    $this->host = 'www.expedia.co.jp';
                    break;
                case 'MS':
                case 'MY':
                    $this->host = 'www.expedia.com.my';
                    $this->langEnUrl = 'https://www.expedia.com.my/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'MX':
                    $this->host = 'www.expedia.mx';
                    $this->langEnUrl = 'https://www.expedia.mx/en/?langid=1033&';
                    break;
                case 'NL':
                    $this->host = 'www.expedia.nl';
                    $this->langEnUrl = 'https://www.expedia.nl/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'NZ':
                    $this->host = 'www.expedia.co.nz';
                    break;
                case 'NO':
                    $this->host = 'www.expedia.no';
                    $this->langEnUrl = 'https://www.expedia.no/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'PH':
                    $this->host = 'www.expedia.com.ph';
                    break;
                case 'SG':
                    $this->host = 'www.expedia.com.sg';
                    break;
                case 'KR':
                    $this->host = 'www.expedia.co.kr';
                    $this->langEnUrl = 'https://www.expedia.co.kr/?langid=1033&pwaDialog=disp-settings-picker';
                    break;
                case 'ES':
                    $this->host = 'www.expedia.es';
                    $this->langEnUrl = 'https://www.expedia.es/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'SV':
                case 'SE':
                    $this->host = 'www.expedia.se';
                    $this->langEnUrl = 'https://www.expedia.se/en/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'CH':
                    $this->host = 'www.expedia.ch';
                    $this->langEnUrl = 'https://www.expedia.ch/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'TW':
                    $this->host = 'www.expedia.com.tw';
                    $this->langEnUrl = 'https://www.expedia.com.tw/?langid=1033&pwaDialog=disp-settings-picker';
                    break;
                case 'TH':
                    $this->host = 'www.expedia.co.th';
                    $this->langEnUrl = 'https://www.expedia.co.th/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                case 'GB':
                case 'UK':
                    $this->host = 'www.expedia.co.uk';
                    break;
                case 'VN':
                    $this->host = 'www.expedia.com.vn';
                    $this->langEnUrl = 'https://www.expedia.com.vn/?langid=2057&pwaDialog=disp-settings-picker';
                    break;
                // EU - https://euro.expedia.net/?currency=EUR&siteid=4400
                // US, CL, CN, CO, CR, DK, EG, PE, SA, AE
                default:
                    if ($region != null && !in_array($region, ['US', 'USA', 'United States', 'SA', 'CR'])) {
                        $this->sendNotification("region $region // MI");
                    }
                    $this->host = 'www.expedia.com';

                    break;
            }
        } elseif ($this->provider == 'hotels') {
            switch ($region ?? null) {
               /* case 'BR':
                    $this->host = 'hotels.com';
                    $this->langEnUrl = 'https://www.hoteis.com/?currency=BRL&eapid=3&locale=pt_BR&pos=HCOM_BR&siteid=301800003&tpid=3018&langid=1046';
                    break;
                case 'Canada':
                case 'CA':
                    $this->host = 'ca.hotels.com';
                    break;
                case 'UK':
                    $this->host = 'uk.hotels.com';
                    break;*/
                case 'US':
                case '':
                    if ($region != null && $region != 'US') {
                        $this->sendNotification("region $region // MI");
                    }
                    $this->host = 'www.hotels.com';
                    break;
                default:
                    $this->host = 'uk.hotels.com';
                    break;
            }
        } elseif ($this->provider == 'homeaway') {
            $this->host = 'www.vrbo.com';
        }

    }

    function setLangEn() {
        $this->logger->notice(__METHOD__);
        try {
            if (!empty($this->langEnUrl)) {
                $landId = $this->http->FindPreg('/langid=([^&]+)/', false, $this->langEnUrl);
                $host = preg_replace('/www\./', '.', $this->host);
                //v.4,|0|0|255|1|0||||||||2057|0|0||0|0|0|-1|-1
                //v.4,|0|0|255|1|0||||||||2057|0|0||0|0|0|-1|-1
                $this->http->setCookie('linfo', "v.4,|0|0|255|1|0||||||||$landId|0|0||0|0|0|-1|-1", $host);
                $this->driver->executeScript("document.cookie=\"linfo=v.4,|0|0|255|1|0||||||||$landId|0|0||0|0|0|-1|-1; path=/; domain=$host;\"");
            } else {
                $this->logger->error('Empty lang url');
            }
        } catch (Exception $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->UseSelenium();

        $this->http->saveScreenshots = true;

        if ($this->attempt == 0 || $this->attempt == 1) {
            $this->useChromePuppeteer();
//            $this->seleniumOptions->addHideSeleniumExtension = false;
//            $this->seleniumOptions->userAgent = null;
        } elseif ($this->attempt == 2) {
//            $this->useGoogleChrome();
            $this->useFirefoxPlaywright();
            $this->seleniumOptions->addHideSeleniumExtension = false;
        }

        if ($this->attempt == 0) {
            $this->setProxyGoProxies();
        } elseif ($this->attempt == 1) {
            $this->setProxyMount();
        } elseif ($this->attempt == 2) {
            $this->setProxyGoProxies();
        }

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $this->setScreenResolution($resolutions[array_rand($resolutions)]);
    }

    public function IsLoggedIn()
    {
        $this->setHost($this->AccountFields['Login2']);
        $this->setLangEn();
        try {
            $this->http->GetURL("https://$this->host/user/account");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->logger->debug("https://$this->host/user/account");
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            try {
                $this->http->GetURL("https://$this->host/user/account");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);

                if ($this->http->FindSingleNode('//p[contains(text(), "The server at hotels.com is taking too long to respond.")]')) {
                    $this->markProxyAsInvalid();

                    throw new CheckRetryNeededException(3, 0);
                }
            } catch (Facebook\WebDriver\Exception\InvalidSessionIdException $e) {
                $this->logger->error("InvalidSessionIdException: " . $e->getMessage(), ['HtmlEncode' => true]);
                throw new CheckRetryNeededException(2, 0);
            }
        }
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Enter a valid email.', ACCOUNT_INVALID_PASSWORD);
        }

        //$this->http->removeCookies();
        $this->setHost($this->AccountFields['Login2']);
        $this->http->saveScreenshots = true;
        if ($this->provider == 'expedia') {
            try {
                $this->http->GetURL($this->langEnUrl ?? "https://$this->host");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->http->GetURL($this->langEnUrl ?? "https://$this->host");
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
        }

        $this->setLangEn();

        if ($this->provider == 'homeaway') {
            try {
                $this->http->GetURL("https://www.vrbo.com/login?enable_login=true&uurl=e3id%3Dredr%26rurl%3D%2F");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->debug("https://$this->host/user/account");
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
        } else {
            try {
                $this->http->GetURL("https://$this->host/user/account");
            } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->debug("https://$this->host/user/account");
                $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            }
        }

        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->duration = rand(300, 1000);
        $mover->steps = rand(50, 60);

        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
        $this->saveResponse();

        if (!$login || !$loginButton) {
            $this->logger->error('Failed to find login button');

            if ($this->loginSuccessful()) {
                return true;
            }

            if (
                $this->waitForElement(WebDriverBy::xpath("
                    //div[@aria-hidden='false']//iframe
                    | //iframe[@sandbox='allow-scripts']
                    | //*[contains(text(),'Something went wrong on our end. Please try again now, or come back')]
                    | //*[contains(text(),'re having some difficulties. Please give us a moment and try again.')]
                "), 0)
                || $this->http->FindSingleNode('//*[self::h1 or self::span][contains(text(), "This site can’t be reached") or contains(text(), "The connection has timed out") or contains(text(), "Access Denied")] | //p[contains(text(), "You don\'t have authorization to view this page.")]')
            ) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3);
            }

            /*
            if ($this->loginSuccessful()) {
                $this->isLoggedIn = true;

                return true;
            }
            */

            return false;
        }

        try {
            $mover->moveToElement($login);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | WebDriverException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }
        $login->clear();
        $mover->sendKeys($login, $this->AccountFields['Login'], 7);
        $this->driver->executeScript("document.getElementById('loginFormSubmitButton').click()");

        $goToPassword = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'passwordButton']"), 5);
        $this->saveResponse();

        if (!$goToPassword) {
            $this->logger->error('Failed to find "goToPassword" input');

            // Other ways to sign in
            $otherWays = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Other ways to sign in')]"), 0);
            if ($otherWays) {
                $otherWays->click();
                sleep(1);
                $this->saveResponse();
                $sendCodeEmail = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Send code to email')]"), 0);
                if ($sendCodeEmail) {
                    $sendCodeEmail->click();
                    sleep(10);
                    //$goToPassword = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'passwordButton']"), 5);
                    $this->saveResponse();
                    return $this->parseQuestion();
                }
            } elseif (!$this->waitForElement(WebDriverBy::xpath("//input[@id = 'enterPasswordFormPasswordInput' or @id = 'loginFormPasswordInput']"), 0)) {
                return $this->parseQuestion();
            }
        }

        if ($goToPassword) {
            $goToPassword->click();
        }

        $passwordInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'enterPasswordFormPasswordInput' or @id = 'loginFormPasswordInput']"), 5);
        $this->saveResponse();

        if (!$passwordInput) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }

        try {
            $mover->moveToElement($passwordInput);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | WebDriverException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
        }
        $passwordInput->clear();
        $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 7);

        $passwordButton = $this->waitForElement(WebDriverBy::xpath("
        //form//button[(@id='enterPasswordFormSubmitButton' or @id='loginFormSubmitButton' or contains(text(),'Sign in')) and not(@disabled)]"), 3);
        $this->saveResponse();

        if (!$passwordButton) {
            $this->logger->error('Failed to find "password" input');

            return false;
        }

        if ($this->attempt > 2) {
            $funCaptcha = $this->http->FindSingleNode("//div[@id = 'atoshield-wrapper-{$this->provider}-login']/@id");

            if ($funCaptcha) {
                $currentUrl = $this->driver->executeScript('return document.location.href;');
                $this->logger->debug("[Current URL]: {$currentUrl}");
                $captcha = $this->parseFunCaptcha($currentUrl);

                $this->driver->executeScript("
                    var atoShieldWrapper = document.getElementById('atoshield-wrapper-{$this->provider}-login');
                    var hiddenTokenField = document.createElement('input');
                    hiddenTokenField.setAttribute('id', 'fc-token-id-{$this->provider}-login');
                    hiddenTokenField.setAttribute('type', 'hidden');
                    hiddenTokenField.setAttribute('name', 'fc-token');
                    hiddenTokenField.setAttribute('value', '$captcha');
                    atoShieldWrapper.appendChild(hiddenTokenField);
                ");
            }
        }

        sleep(1);
        try {
            $mover->moveToElement($passwordButton);
            $mover->click();
        } catch (Facebook\WebDriver\Exception\MoveTargetOutOfBoundsException | MoveTargetOutOfBoundsException | WebDriverException $e) {
            $this->logger->error('[MoveTargetOutOfBoundsException]: ' . $e->getMessage(), ['pre' => true]);
            $this->driver->executeScript("document.querySelector('#enterPasswordFormSubmitButton, #loginFormSubmitButton').click()");
        }

        return $this->loginPreview();
    }

    private function parseFunCaptcha($pageUrl, $step = 'enterpassword', $retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindPreg("#expedia-api\.arkoselabs\.com\/v2\/([^\/]+)\/api\.js#");

        if (!$key) {
            return false;
        }

        if ($this->attempt == 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $pageUrl,
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }


        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 180;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $pageUrl,
            "proxy"   => $this->http->GetProxy(),
            /*"proxytype" => "HTTP",
            "surl" => "expedia-api.arkoselabs.com"*/
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    public function Login()
    {
        if ($this->isLoggedIn) {
            return true;
        }

        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'uitk-banner-description')] | //h3[contains(@class, 'uitk-error-summary-heading')]")) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == "Email and password don't match. Please try again."
                || $message == "Enter a valid email."
                || $message == "Your account was paused in a routine security check. Your info is safe, but a password reset is required."
                || $message == "電郵地址及密碼不符，請再試一次。"
                || $message == "E-posten og passordet stemmer ikke overens. Prøv igjen."
                || $message == "O e-mail e a senha não correspondem. Tente outra vez."
                || $message == "E-mail e password non corrispondono, riprova."
                || $message == "L’adresse e-mail et le mot de passe ne correspondent pas. Veuillez réessayer."
                || $message == "La dirección de correo electrónico y la contraseña no coinciden. Prueba de nuevo."
                || $message == "E-postadressen och lösenordet matchar inte varandra. Försök igen."
                || $message == "Eメールとパスワードが一致しません。もう一度お試しください。"
                || $message == "Das Passwort passt nicht zur eingegebenen E-Mail-Adresse. Bitte versuche es erneut."
                || $message == "Email dan kata sandi tidak sesuai. Silakan coba lagi."
                || $message == "Insira um e-mail válido."
                || $message == "E-mailadressen og adgangskoden stemmer ikke overens. Prøv igen."
            ) {
                if($this->attempt == 2) {
                    throw new CheckException("Email and password don't match. Please try again.", ACCOUNT_INVALID_PASSWORD);
                } else
                    throw new CheckRetryNeededException(3, 5, self::PROVIDER_ERROR_MSG);
            }

            if (
                $message == 'Sorry, something went wrong on our end. Please wait a moment and try again.'
                || $message == 'Se ha producido un error. Espera un momento y vuelve a intentarlo.'
                || $message == 'Er is bij ons iets misgegaan. Wacht even en probeer het opnieuw.'
                || $message == 'Rất tiếc, chúng tôi đang gặp trục trặc. Vui lòng đợi trong giây lát và thử lại.'
                || $message == '죄송합니다. 문제가 있는 것 같습니다. 잠시 기다린 후 다시 시도해 주세요.'
                || $message == 'Sorry, something went wrong on our end.' // cheaptickets
                || strstr($message, 'Leider ist bei uns etwas schiefgegangen')
            ) {
                //throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                throw new CheckRetryNeededException(3, 7, self::PROVIDER_ERROR_MSG);
            }

            if (strstr($message, 'Your account was paused in a routine security check. Your info is safe, but a password reset is required.')) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        return $this->checkErrors();
    }

    function loginPreview()
    {
        $this->logger->notice(__METHOD__);
        sleep(5);
        $this->waitForElement(WebDriverBy::xpath("
            //*[contains(text(), 'Set a new password so you can easily use your account across our family of brands to unlock a world of travel.')]
            //*[contains(text(), 'Get early access to a more rewarding experience')]
            | //*[@aria-describedby='header-menu-account_circle-description'] 
            | //div[contains(@class, 'uitk-banner-description')]
            | //h3[contains(@class, 'uitk-error-summary-heading')]
            | //h3[contains(text(),'Now you can explore')]
            | //h3[contains(text(),'Welcome to ')]
            | //h1[contains(text(),'Set a new password')]
            | //div[@aria-hidden='false']//iframe
            | //iframe[@sandbox='allow-scripts']
            | //div[@id = 'dashboard-content']
            | //div[contains(text(), 'Set a new password to easily use your account across Expedia, Hotels.com, and Vrbo')]
            | //button[normalize-space() = 'Skip for now']
            | //div[@data-testid=\"memberprofile-mediumview\"]
            | //*[self::div or self::button][@data-testid=\"header-menu-button\"]
        "), 15);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath("
            //*[contains(text(), 'Set a new password so you can easily use your account across our family of brands to unlock a world of travel.')]
            | //h1[contains(normalize-space(text()),'Set a new password')]
            | //div[contains(text(), 'Set a new password to easily use your account across Expedia, Hotels.com, and Vrbo')]
        "), 0)) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Get early access to a more rewarding experience')]"),
            0)) {
            $notNow = $this->waitForElement(WebDriverBy::xpath("//*[contains(text(), 'Not now')] | //button[contains(text(), 'Continue')]"), 0);
            if ($notNow) {
                $notNow->click();
                sleep(7);
            }
        }

        if ($skipBtn = $this->waitForElement(WebDriverBy::xpath("//button[normalize-space() = 'Skip for now']"), 0)) {
            $skipBtn->click();
            sleep(7);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(),'Now you can explore')]"), 0)) {
            $continue = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Continue')]"), 0);
            if ($continue) {
                $continue->click();
                sleep(7);
                $this->waitFor(function () {
                    return is_null($this->waitForElement(WebDriverBy::xpath('//div[normalize-space() = "Give us a sec to finish setting up your account..."]'), 0));
                }, 20);
                $this->saveResponse();
            }
            $continue = $this->waitForElement(WebDriverBy::xpath("//button[contains(text(), 'Get started')]"), 0);
            if ($continue) {
                $this->throwProfileUpdateMessageException();
            }
        }

        $this->waitForElement(WebDriverBy::xpath("
            //*[@aria-describedby='header-menu-account_circle-description'] 
            | //div[contains(@class, 'uitk-banner-description')]
            | //h3[contains(@class, 'uitk-error-summary-heading')]
            | //h3[contains(text(),'Welcome to ')]
            | //div[@aria-hidden='false']//iframe
            | //iframe[@sandbox='allow-scripts']
            | //div[@id = 'dashboard-content']
            | //div[@data-testid=\"memberprofile-mediumview\"]
            | //*[self::div or self::button][@data-testid=\"header-menu-button\"]
        "), 10);
        $this->saveResponse();

        if ($this->waitForElement(WebDriverBy::xpath("//h3[contains(text(),'Welcome to ')]"), 3)) {
            $continue = $this->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Let\'s go")]'), 0);
            if ($continue) {
                $continue->click();
                sleep(7);
            }
        }

        $this->saveResponse();

        // FunCaptcha
        if ($this->waitForElement(WebDriverBy::xpath("//div[@aria-hidden='false']//iframe | //iframe[@sandbox='allow-scripts']"), 0)
            || $this->waitForElement(WebDriverBy::xpath("//*[contains(text(),'Something went wrong on our end. Please try again now, or come back')]"), 0)) {
            $this->saveResponse();
            $this->markProxyAsInvalid();

            throw new CheckRetryNeededException(3, 7, self::CAPTCHA_ERROR_MSG);
        }

        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->http->currentUrl() == "https://www.$this->host/") {
            $this->http->GetURL("https://www.$this->host/user/account");
        }

        return true;
    }

    function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

        if ($this->http->FindSingleNode('//body[@class = "neterror"]')) {
            return false; // "This site can’t be reached" and similar things
        }

        if (
            (
                strstr($this->http->currentUrl(), '/account')
                && $this->http->FindSingleNode('//div[@id = "dashboard-content"]/@id | //title[contains(text(),"My Account") or contains(text(), "我的帳戶") or contains(text(), "Mein Konto") or contains(text(), "Mi cuenta")]')
            )
            || (
                strstr($this->http->currentUrl(), '/profile/landing.html')
                && !$this->http->FindSingleNode('//h2[contains(text(), "Show us your human side...")]')
            )
            || $this->http->FindSingleNode('//div[@data-testid="memberprofile-mediumview"]')
            || $this->http->FindSingleNode('//*[self::div or self::button][@data-testid="header-menu-button"]')
        ) {
            $this->logger->debug("login success");

            return true;
        }

        return false;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We are currently making improvements to our site and it is temporarily unavailable.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindPreg("/www.expedia.com<\/strong> redirected you too many times\./")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
            $this->Cancel();
        }

        $q = $this->waitForElement(WebDriverBy::xpath("
            //div[contains(text(), 'Enter the secure code we sent to ')]
            | //div[contains(text(), 'Enter the secure code we sent to your email. Check junk mail if it’s not in your inbox.')]
            | //div[contains(text(), 'To continue, enter the secure code we sent to')]
            | //div[contains(text(), 'A secure code will be sent to ***')]
            | //div[contains(text(), 've made updates to our experience and need to confirm your email. Enter the secure code we sent to your inbox.')]
            | //div[contains(text(), 'Fizemos algumas alterações e precisamos confirmar o seu e-mail.')]
            | //div[contains(text(), 'To continue, enter the secure code we sent to')]
        "), 0);
        $this->saveResponse();

        if (!$q) {
            return true;
        }

        $question = $q->getText();
        $this->logger->debug($question);

        if (
            !QuestionAnalyzer::isOtcQuestion($question)
            && strstr($question, '@')
        ) {
            $this->sendNotification("need to fix QuestionAnalyzer");
        }

        $this->holdSession();
        $this->AskQuestion($question, null, 'emailCode');

        return false;
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $answer = $this->Answers[$this->Question];
        unset($this->Answers[$this->Question]);
        $this->logger->debug("Question to -> $this->Question=$answer");

        $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[@id='verifyOtpFormCodeInput' or @id='verify-sms-one-time-passcode-input']"), 0);

        if (!$securityAnswer) {
            $this->saveResponse();
            return false;
        }

        $securityAnswer->clear();
        $securityAnswer->sendKeys($answer);

        $button = $this->waitForElement(WebDriverBy::xpath("//button[@id='verifyOtpFormSubmitButton' or @type='submit']"), 0);
        if (!$button) {
            $this->saveResponse();
            return false;
        }
        $button->click();

        $error = $this->waitForElement(WebDriverBy::xpath("
            //form//div[contains(text(), 'Invalid code, please try again')]
        "), 7);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($this->Question, $error->getText(), 'emailCode');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: ".$this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'emailCode') {
            //$this->sendNotification('check 2fa // MI');
            $this->setHost($this->AccountFields['Login2']);
            if ($this->processSecurityCheckpoint()) {
                $this->saveResponse();
                $this->loginPreview();

                return $this->loginSuccessful();
            }
        }

        return false;
    }

    protected function getExpedia()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->expedia)) {
            $this->expedia = new TAccountCheckerExpedia();
            $this->expedia->http = new HttpBrowser("none", new CurlDriver());
            $this->expedia->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->expedia->http);
            $this->expedia->AccountFields = $this->AccountFields;
            $this->expedia->itinerariesMaster = $this->itinerariesMaster;
            $this->expedia->HistoryStartDate = $this->HistoryStartDate;
            $this->expedia->historyStartDates = $this->historyStartDates;
            $this->expedia->http->LogHeaders = $this->http->LogHeaders;
            $this->expedia->ParseIts = $this->ParseIts;
            $this->expedia->ParsePastIts = $this->ParsePastIts;
            $this->expedia->WantHistory = $this->WantHistory;
            $this->expedia->WantFiles = $this->WantFiles;
            $this->expedia->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);

            $this->expedia->globalLogger = $this->globalLogger;
            $this->expedia->logger = $this->logger;
            $this->expedia->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == 'currency') {
                $this->currency = $cookie['value'];
            }
            $this->expedia->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->expedia;
    }

    public function Parse()
    {
        $this->expedia = $this->getExpedia();

        $this->http->GetURL("https://{$this->host}/account/profile");
        $this->waitForElement(WebDriverBy::xpath("//h3[contains(text(), 'Name')]/following-sibling::div[normalize-space(text()) != '']"), 10);
        $this->waitForElement(WebDriverBy::xpath("//div[div[contains(text(), 'OneKeyCash') or contains(text(), 'Points value')]]/following-sibling::div[normalize-space(text()) != '$']"), 5);
        $this->saveResponse();
        // Balance - OneKeyCash TM
        $this->SetBalance($this->http->FindSingleNode("//div[div[contains(text(), 'OneKeyCash') or contains(text(), 'Points value')]]/following-sibling::div"));
        // Currency
        $this->SetProperty('Currency', $this->currency);
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//h3[contains(text(), 'Name')]/following-sibling::div[normalize-space(text()) != '']")));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//span[contains(@class, 'uitk-badge-') and @aria-hidden]"));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->http->GetURL("https://{$this->host}/users/account/rewardsheader");
            $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Point value')]/following-sibling::span"), 2);
            $this->saveResponse();
            // No balance info, no "Join" links // AccountID: 7228866
            if ($this->AccountFields['Login'] == 'camponez@gmail.com') {
                $this->SetBalanceNA();
            }
        }

        // refs #23902
        $this->logger->info('Expiration date', ['Header' => 3]);
        $this->http->GetURL("https://{$this->host}/account/rewards");
        $activity = $this->waitForElement(WebDriverBy::xpath('//h3[contains(text(), "Your rewards activity")]/following-sibling::div//span[contains(@class, "uitk-expando-title")]//p[contains(@class, "uitk-subheading") and contains(text(), "Redeemed")]'), 5);
        $this->saveResponse();

        /*
        if ($activity) {
            $lastActivity = $this->http->FindPreg("/Redeemed\s*(.+)/", false, $activity->getText());
            $this->SetProperty("LastActivity", $lastActivity);
            $exp = strtotime($lastActivity);

            if ($lastActivity && $exp) {
                $this->SetExpirationDate(strtotime("+18 month", $exp));
            }
        }
        */

        $this->parseWithCurl();
        $duaid = $this->browser->getCookieByName("DUAID", str_replace('www', '', $this->host), "/", true);
        $info = $this->browser->Response['headers']['x-app-info'] ?? null;

        if ($duaid && $info) {
            $headers = [
                "Accept"       => "*/*",
                "Referer"      => "https://{$this->host}/account/rewards",
                "content-type" => "application/json",
                "client-info"  => 'universal-profile-ui,809b5aa88e8bb51caa798a9b1eb9740112033e2a,us-west-2',
                "x-page-id"    => "page.User.Rewards",
            ];
            $data = '[{"operationName":"LoyaltyRewardsActivityQuery","variables":{"context":{"siteId":1,"locale":"en_US","eapid":0,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":null,"tuid":null,"authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}},"rewardFilterSelection":null},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"a9f81dedee2ebc813d64ccc78e6ff21eb716b2a723764326b1cae380798b9731"}}}]';
            $this->browser->PostURL("https://{$this->host}/graphql", $data, $headers);
            $response = $this->browser->JsonLog(null, 3, false, "collapsedLabel");
            $records = $response[0]->data->loyaltyRewardsActivity->content->records ?? [];
            $this->logger->debug("Total ".count($records)." history rows were found");
            $refundedRows = [];
            if (count($records) > 0) {
                $this->sendNotification('check exp date // MI');
            }

            foreach ($records as $record) {
                $recordName = $record->expando->expandoCard->collapsedLabel;
                $recordSubtitle = $record->expando->expandoCard->subtitle;
                $recordAmount = $record->expando->status->amountChanged->text;
                $this->logger->debug("[{$recordName} / {$recordSubtitle}]: {$recordAmount}");

                if (strstr($recordSubtitle, 'Refunded')) {
                    $this->logger->notice("Skip Refunded transsaction");
                    $refundedRows[$recordName] = $recordAmount;

                    continue;
                }// if (strstr($recordSubtitle, 'Refunded'))

                $this->logger->debug(var_export($refundedRows, true), ['pre' => true]);

                if (isset($refundedRows[$recordName]) && $recordAmount == str_replace('+', '-', $refundedRows[$recordName])) {
                    $this->logger->notice("[Skip Refunded transsaction]: {$recordName} - {$recordSubtitle} / {$recordAmount}");

                    continue;
                }// if (isset($refundedRows[$recordName]) && $recordAmount == str_replace('+', '-', $refundedRows[$recordName]))

                $lastActivity = $this->http->FindPreg("/ (\w{3} \d+, \d{4})$/ims", false, $recordSubtitle);
                $this->logger->debug("[LastActivity]: {$lastActivity}");

                break;
            }// foreach ($records as $numRecord => $record)

            if (isset($lastActivity)) {
                $this->SetProperty("LastActivity", $lastActivity);
                $exp = strtotime($lastActivity);

                if ($lastActivity && $exp) {
                    $this->SetExpirationDate(strtotime("+18 month", $exp));
                }// if ($lastActivity && $exp)
            }// if (isset($lastActivity))

            // refs#23861
            $data = '[{"operationName":"LoyaltyTierProgressionQuery","variables":{"context":{"siteId":1,"locale":"en_US","eapid":0,"currency":"USD","device":{"type":"DESKTOP"},"identity":{"duaid":"'.$duaid.'","expUserId":null,"tuid":null,"authState":"AUTHENTICATED"},"privacyTrackingState":"CAN_TRACK","debugContext":{"abacusOverrides":[]}}},"extensions":{"persistedQuery":{"version":1,"sha256Hash":"df584c176b30186fd865aa9d83965ebf2d714133e6a91278657d05db53cc0933"}}}]';
            $this->browser->PostURL("https://{$this->host}/graphql", $data, $headers);
            $progressions = $this->browser->JsonLog(null, 3, false, "sections");
            foreach ($progressions ?? [] as $progression) {
                if (empty($progression->data->loyaltyTierProgression->__typename) || $progression->data->loyaltyTierProgression->__typename != 'LoyaltyTierProgression') {
                    break;
                }
                foreach ($progression->data->loyaltyTierProgression->sections ?? [] as $section) {
                    $this->logger->debug('__typename: ' . $section->content->__typename);
                    // TODO: maybe returning old json to some accounts
                    // example: 2923249
                    if ($section->content->__typename == 'LoyaltyClarityDetails') {
                        foreach ($section->content->contents ?? [] as $content) {
                            $this->logger->debug('_  contents -> __typename: ' . $content->__typename);
                            if ($content->__typename == 'LoyaltyTierProgressionBarElement') {
                                $this->logger->debug('__      value: ' . $content->radialBar->accessibilityLabel);
                                $value = $this->http->FindPreg('/^(\d+) of \d+ trip elements/', false,
                                    $content->radialBar->accessibilityLabel ?? '');
                                if (isset($value)) {
                                    $this->SetProperty('TripsToTheNextTier', $value);
                                }
                            }
                        }
                    } elseif ($section->content->__typename == 'LoyaltyTierProgressionDetails') {
                        // 8 of 15 trip elements collected to reach Gold
                        $value = $this->http->FindPreg('/^(\d+) of \d+ trip elements/', false,
                            $section->content->tierProgressionDetails->title ?? '');
                        $this->logger->debug('title: ' . $value);
                        if (isset($value)) {
                            $this->SetProperty('TripsToTheNextTier', $value);
                        }
                    } elseif ($section->content->__typename == 'EGDSBasicSectionHeading') {
                        // You'll need to collect 16 trip elements to continue enjoying Platinum perks next year
                        $value = $this->http->FindPreg('/collect (\d+) trip elements/', false,
                            $section->content->subheading ?? '');
                        if ($value) {
                            $this->SetProperty('TripsNeededToMaintainCurrentTier', $value);
                        }
                    }
                }
            }
        }

        /*
        $activities = $this->http->XPath->query('//h3[contains(text(), "Your rewards activity")]/following-sibling::div[@role="group"]//span[contains(@class, "uitk-expando-title") and normalize-space() != ""]');
        $this->logger->debug("Total {$activities->length} activities were found");

        foreach ($activities as $activity) {
            $this->logger->debug(">>> {$activity->nodeValue}");
            $lastActivity = $this->http->FindSingleNode('.//p[contains(@class, "uitk-subheading")]', $activity, true, "/Redeemed\s*(.+)/");
            $this->SetProperty("LastActivity", $lastActivity);
            $exp = strtotime($lastActivity);

            if ($lastActivity && $exp) {
                $this->SetExpirationDate(strtotime("+18 month", $exp));
            }

            if ($lastActivity) {
                break;
            }
        }// foreach ($activities as $activity)
        */

        $this->logger->info('Airline credits', ['Header' => 3]);
        $this->http->GetURL("https://{$this->host}/account/credits");
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "credits-content"]//h1[contains(text(), "Credits")]/following-sibling::div//div[contains(@class, "uitk-card ")]'), 5);
        $this->saveResponse();

        $credits = $this->http->XPath->query('//div[@id = "credits-content"]//h1[contains(text(), "Credits")]/following-sibling::div//div[contains(@class, "uitk-card ")]');
        $this->logger->debug("Total {$credits->length} credits were found");

        foreach ($credits as $credit) {
            $displayName = $this->http->FindSingleNode('.//h3[contains(@class, "heading")]', $credit);
            $for = $this->http->FindSingleNode('.//div[contains(text(), "For ")]', $credit);
            $issued = $this->http->FindSingleNode('.//div[contains(text(), "Issued for itinerary")]', $credit, true, "/itinerary\s*(#[^<]+)/");
            $exp = $this->http->FindSingleNode('.//div[contains(text(), "Expires on")]', $credit, true, "/on\s*([^<]+)/");

            if ($for) {
                $displayName .= " ({$for})";
            }

            if ($issued) {
                $displayName .= " - {$issued}";
            }

            if (strtotime($exp) < strtotime("today")) {
                $this->logger->notice("skip expired credit: " . strtotime($exp));

                continue;
            }

            $this->AddSubAccount([
                'Code'           => 'expediaCredits'.md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($credits as $credit)

        $this->logger->info('Active coupons', ['Header' => 3]);
        $this->http->GetURL("https://{$this->host}/account/coupons");
        $this->waitForElement(WebDriverBy::xpath('//div[@id = "coupons-content"]//h3[contains(text(), "Active coupons")]/following-sibling::div/div[contains(@class, "uitk-card ")]'), 5);
        $this->saveResponse();

        $coupons = $this->http->XPath->query('//div[@id = "coupons-content"]//h3[contains(text(), "Active coupons")]/following-sibling::div/div[contains(@class, "uitk-card ")]');
        $this->logger->debug("Total {$coupons->length} coupons were found");

        foreach ($coupons as $coupon) {
            $displayName = $this->http->FindSingleNode('.//div[contains(@class, "uitk-type-bold uitk-text-default-theme")]', $coupon);
            $exp = $this->http->FindSingleNode('.//div[contains(text(), "Book by")]', $coupon, true, "/by\s*([^<]+)/");

            $this->AddSubAccount([
                'Code'           => 'expediaCoupons'.md5($displayName),
                'DisplayName'    => $displayName,
                'Balance'        => null,
                'ExpirationDate' => strtotime($exp),
            ]);
        }// foreach ($coupons as $coupon)

        if (in_array($this->AccountFields['Login2'], ['US', 'USA', 'United States'])) {
            return;
        }

        $this->http->GetURL("https://{$this->host}/user/rewards?defaultTab=2&");
        // Member ID
        $this->SetProperty("RewardsID", $this->http->FindSingleNode("//strong[contains(text(), 'Member ID:')]/following-sibling::text()"));
        /*
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            // At your request, we've cancelled your Expedia Rewards membership and no further action is required
            $message = $this->http->FindSingleNode("//h2[contains(text(),'ve cancelled your Expedia Rewards membership and no further action is required')]");
            if ($message) {
                $this->throwProfileUpdateMessageException();
            }

            $message = $this->http->FindSingleNode("//h1[contains(text(),'Now is a great time to join Expedia Rewards!')]");
            if ($message) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
        */
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);

        $this->browser->setUserAgent($this->http->userAgent);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        $this->browser->RetryCount = 0;
        $this->browser->GetURL($this->http->currentUrl());
        $this->browser->RetryCount = 2;
    }

    /*public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $expedia = $this->getExpedia();

        $result = $expedia->ParseItineraries($this->host, $this->ParsePastIts);

        return $result;
    }*/

    private function savePageToBody()
    {
        $this->logger->notice(__METHOD__);
        $this->http->SaveResponse();
        try {
            $this->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
    }

    private function savePageToBodyToCurl()
    {
        $this->logger->notice(__METHOD__);
        $this->expedia->SaveResponse();
        try {
            $this->expedia->http->SetBody($this->driver->executeScript('return document.documentElement.innerHTML'));
        } catch (UnexpectedJavascriptException | Facebook\WebDriver\Exception\JavascriptErrorException $e) {
            $this->logger->error("exception: " . $e->getMessage());
        }
    }

    public function ParseItineraries($providerHost = null, $ParsePastIts = false)
    {
        $this->http->FilterHTML = false;
        $startTimer = $this->getTime();
        if ($providerHost == null) {
            $providerHost = $this->host;
        }
        //$this->setHost($this->AccountFields['Login2']);
        $this->http->GetURL($url = "https://{$providerHost}/trips");
        $close = $this->waitForElement(WebDriverBy::xpath("//div[@id='app-layer-customer-notification-centered-sheet-dialog']//button[@id='close']"), 5);
        if ($close) {
            $close->click();
            sleep(1);
        }
        $this->savePageToBody();
        if ($this->http->FindNodes("//div[@id='app-layer-base']//div[contains(text(),'have no upcoming') and contains(text(),'Where are you going next?')]")
            && !$this->ParsePastIts && !in_array($this->AccountFields['Login'], ['wendydaan@gmail.com'])) {
            $this->itinerariesMaster->setNoItineraries(true);
            return [];
        }
        $this->expedia = $this->getExpedia();
        $this->ParseItinerariesNew($providerHost, $ParsePastIts);
        $this->getTime($startTimer);
        return [];
    }

    public function ParseItinerariesNew($providerHost, $ParsePastIts = false)
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = preg_replace('/\?langid=\d+/', '', $this->http->currentUrl());
//        $isExpedia = strpos($providerHost, 'expedia') !== false ||
//            strpos($providerHost, 'orbitz') !== false;
        $links = [];
        $noIt = [];
        /*// Page Main
        if ($link = $this->http->FindSingleNode("//a[contains(text(),'See all current')]/@href")) {
            $links[] = $link;
        } else {
            $links[] = $currentUrl . '/list/2';
        }*/

        if ($link = $this->http->FindSingleNode("//a[contains(text(),'See all upcoming')]/@href")) {
            $links[] = $link;
        } else {
            $links[] = $currentUrl . '/list/7';
        }

        if ($this->ParsePastIts || in_array($this->AccountFields['Login'], ['wendydaan@gmail.com'])) {
            if ($link = $this->http->FindSingleNode("//section//a[normalize-space()='Past']/@href")) {
                $links[] = $link;
            } else {
                $links[] = $currentUrl . '/list/3';
            }
        }

        $this->logger->debug(var_export($links, true));

        foreach ($links as $link) {
            $this->logger->info("{$link}", ['Header' => 3]);
            $this->http->GetURL($link);
            $xpath = "//div[@id='app-layer-base']//div[contains(text(),'you have no ') and contains(text(),'Where are you going next?')]";
            $this->waitForElement(WebDriverBy::xpath($xpath), 5);
            $this->savePageToBody();
            if ($this->http->FindNodes($xpath)) {
                $path = parse_url($link, PHP_URL_PATH);
                $noIt[$path] = true;
                continue;
            }
            // Page Category
            $nodes = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-")]/@href');

            foreach ($nodes as $node) {
                $this->increaseTimeLimit();
                $this->http->GetURL($node);
                sleep(5);
                $this->savePageToBody();
                // Page Itinerary
                $its = $this->http->FindNodes('//div[@role="main"]//a[contains(@href, "/trips/egti-") and contains(.,"View booking")]/@href');

                foreach ($its as $it) {
                    //$this->delay();
                    $this->increaseTimeLimit();
                    $this->http->GetURL($it);
                    sleep(5);
                    $this->savePageToBodyToCurl();
                    if ($this->expedia->ParseItineraryDetectType($providerHost) === false) {
                        //$this->delay();
                        $this->http->GetURL($it);
                        sleep(5);
                        $this->savePageToBodyToCurl();
                        $this->increaseTimeLimit();
                        try {
                            $this->expedia->ParseItineraryDetectType($providerHost);
                        } catch (Facebook\WebDriver\Exception\WebDriverCurlException $e) {
                            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
                        }
                    }

                    // AccountID: 3463484, 1553247
                    $this->logger->debug("[I]: {$this->currentItin}");

                    if ($this->currentItin > 30) {
                        $this->logger->notice("Break parsing: many reservations");

                        break 2;
                    }
                    $this->currentItin++;
                }
            }
        }
        $this->logger->debug(var_export($noIt, true));

        if (isset($noIt['/trips/list/7'])
            && !$this->ParsePastIts &&  !in_array($this->AccountFields['Login'], ['wendydaan@gmail.com'])) {
            // refs#21589
            if (strpos($providerHost, 'hotels') == false) {
                $this->itinerariesMaster->setNoItineraries(true);
                return [];
            }
        } elseif (isset($noIt['/trips/list/7'])
            && isset($noIt['/trips/list/3'])) {
            // refs#21589
            if (strpos($providerHost, 'hotels') == false) {
                $this->itinerariesMaster->setNoItineraries(true);
                return [];
            }
        }

        return [];
    }


}
