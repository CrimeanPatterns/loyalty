<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\amazongift\GifFpsChanger;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAmazongift extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    public const QUESTIONS_REG_EXP = "/(We haven\&\#39;t seen you using this device before\.\s*To help protect your account\, we just want to make sure it\&\#39;s really you\.|Two-Step Verification|Zwei-Schritt-Verifizierung|>\s*Verification needed\s*<\/h1>|>\s*Security questions?\s*<\/h1>|<h1>Verifying it's you[\.]{0,}<\/h1>|We have one more security question\.|>\s*Authentication required|Überprüfung erforderlich|<h1>Nous vérifions qu'il s'agit bien de vous[\.]{0,}<\/h1>|Vérification en deux étapes|>Vérification requise<\/h1>|Enter verification code|Saisir le code de vérification)/ims";

    public $regionOptions = [
        ""        => "Select your region",
        "Canada"  => "Canada",
        "France"  => "France",
        "Germany" => "Germany",
        "Japan"   => "Japan",
        "UK"      => "UK",
        "USA"     => "USA",
    ];

    public $autologinOptions = [
        "amazonaff"  => "Affiliate Program",
        "amazongift" => "Gift Cards",
        "amazonturk" => "Mechanical Turk",
    ];

    private $LoadForm = 0;
    private $seleniumURL = null;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
    }

    public static function GetAccountChecker($accountInfo)
    {
        $testAccounts = [
            // USA
            'campbellpaulm@gmail.com',
            'foxberg@mac.com',
            'me@wangyz.net',

            // subacc giftcard
            'dzastre@netscape.net',
            'amazon@element77.com',
            'ilovejc012@gmail.com',
            'kott.danny@gmail.com',
            'omri.mandel@gmail.com',
            'rewhitworth@hotmail.com',

            //turk
            'donald_neal@yahoo.com',
            'jakel5564@gmail.com',
            'haverlandt@outlook.com',
            'kedar.r.bhat@gmail.com',
            'gabiginorio@yahoo.com',
            'elopez85@gmail.com',
            'tjipz8@hotmail.com',
            'ZELL22@MSN.COM',
            'sooshegirl@gmail.com',

            // norush
            'rayvergeldedios@gmail.com',
            'andrewwrome@gmail.com',
            'shpml1111@gmail.com',

            // payments
            'adam2884@yahoo.com',
            'davidchu1@att.net',
            'wangshende@hotmail.com',

            // business accounts
            'joshsohn@gmail.com',
            'clarak@outlook.com',
            'claudevsmith@gmail.com',
            'williamjameshyland@gmail.com',
            'sschea@gmail.com',
            'loualexander1@yahoo.com',
            'jonahwy@mac.com', // has all subaccounts
            'michael@migdol.net',
            'cbolanos@verizon.net',
            'jakel5564@gmail.com',

            // Canada
            'brendantaraedwards@gmail.com',
            'curtishemming@gmail.com',
            'chenqian630@gmail.com',
        ];

        if (in_array($accountInfo['Login'], $testAccounts)) {
            require_once __DIR__ . "/TAccountCheckerAmazongiftSelenium.php";

            return new TAccountCheckerAmazongiftSelenium();
        }

        if (in_array($accountInfo['Login2'], ['Germany', 'France', 'Japan', 'UK', 'Canada', 'USA'])) {
            require_once __DIR__ . "/TAccountCheckerAmazongiftSelenium.php";

            return new TAccountCheckerAmazongiftSelenium();
        }

        return new static();
    }

    public static function FormatBalance($fields, $properties)
    {
        switch ($fields['Login2']) {
            case 'UK':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "&pound;%0.2f");

                break;

            case 'France':
            case 'Germany':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "€%0.2f");

                break;

            case 'Canada':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "CDN$%0.2f");

                break;

            case 'Japan':
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "¥%0.2f");

                break;

            default:
                return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "$%0.2f");

                break;
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);

        switch ($this->AccountFields['Login2']) {
            case 'UK':
                $arg['CookieURL'] = 'https://www.amazon.co.uk/gp/yourstore/home?ie=UTF8&path=%2Fgp%2Fyourstore%2Fhome&ref_=gno_signout&signIn=1&useRedirectOnSuccess=1&action=sign-out&';
                $arg['SuccessURL'] = 'https://www.amazon.co.uk/gp/css/gc/balance/ref=ya__34';

                break;

            default:
//                $arg['SuccessURL'] = 'https://www.amazon.com/gp/css/homepage.html';
//                $arg['CookieURL'] = 'https://www.amazon.com/ap/signin?_encoding=UTF8&openid.assoc_handle=usflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=900&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fcss%2Fgc%2Fbalance%3Fie%3DUTF8%26ref_%3Dya_34';
            break;
        }

        return $arg;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = $this->regionOptions;

        ArrayInsert($arFields, "Login2", true, ["Login3" => [
            "Type"     => "string",
            "Required" => true,
            "Caption"  => "Auto-login to",
        ]]);
        $arFields["Login3"]["Options"] = $this->autologinOptions;
    }

    public function LoadLoginForm()
    {
        $this->AccountFields['Login2'] = $this->checkRegionSelection($this->AccountFields['Login2']);
        $this->logger->notice('Region => ' . $this->AccountFields['Login2']);
        $this->AccountFields['Login3'] = $this->checkAutologinSelection($this->AccountFields['Login3']);
        $this->logger->notice('Auto-login to => ' . $this->AccountFields['Login3']);

        $this->http->removeCookies();

        switch ($this->AccountFields['Login2']) {
            case 'UK':
//                $this->http->FilterHTML = false;
                /*
                $this->http->GetURL("https://www.amazon.co.uk/ref=ap_frn_logo");
                $this->captchaBeforeLoginForm();

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.co.uk/ref=ap_frn_logo");

//                if (!$this->http->ParseForm("signIn"))
//                    return false;
//                $this->http->SetInputValue('email', $this->AccountFields['Login']);
//                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//                $this->http->SetInputValue('metadata1', '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');
                break;

            case 'France':
//                $this->http->FilterHTML = false;
                /*
                $this->http->GetURL("https://www.amazon.fr/ref=ap_frn_logo");

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.fr/ref=ap_frn_logo");

//                if (!$this->http->ParseForm("signIn"))
//                    return false;
//                $this->http->FormURL = 'https://www.amazon.fr/ap/signin';
//                $this->http->SetInputValue('email', $this->AccountFields['Login']);
//                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//                $this->http->SetInputValue('metadata1', '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');
//                $this->http->setDefaultHeader('Host','www.amazon.fr');
                break;

            case 'Canada':
                /*
                $this->http->FilterHTML = false;
                $this->http->GetURL("https://www.amazon.ca/ref=nav_logo");
                $this->captchaBeforeLoginForm();

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.ca/ref=nav_logo");

                break;

            case 'Germany':
//                $this->http->FilterHTML = false;
                /*
                $this->http->GetURL("https://www.amazon.de/ref=ap_frn_logo");
                $this->captchaBeforeLoginForm();

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.de/ref=ap_frn_logo");

                break;

            case 'Japan':
//                $this->http->FilterHTML = false;
                /*
                $this->http->GetURL("https://www.amazon.co.jp/");
                $this->captchaBeforeLoginForm();

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.co.jp/");

                break;

            case 'USA': default:
//                $this->http->FilterHTML = false;
                /*
                $this->http->GetURL("https://www.amazon.com/gp/css/homepage.html/ref=ap_frn_ya");
                $this->captchaBeforeLoginForm();

                if (!$this->getRightLoginURL()) {
                    return false;
                }
                $this->http->FilterHTML = true;
                */

                $this->selenium("https://www.amazon.com");

//                if (!$this->http->ParseForm("signIn"))
//                    return false;
//                $this->http->SetInputValue('email', $this->AccountFields['Login']);
//                $this->http->SetInputValue('password', $this->AccountFields['Pass']);
//                $this->http->SetInputValue('metadata1', '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');
//                $this->http->setDefaultHeader('Host','www.amazon.com');

            break;
        }

        return true;
    }

    public function selenium($startURL)
    {
        $this->logger->notice(__METHOD__);
        $checker = clone $this;
        $retry = false;
        $this->http->brotherBrowser($checker->http);

        try {
            $this->logger->notice("Running Selenium...");
            $checker->UseSelenium();
            $checker->useChromium();
            $checker->disableImages();
            $checker->useCache();
            $checker->http->start();
            $checker->Start();

            $this->http->saveScreenshots = true;
            $checker->http->saveScreenshots = true;

            $checker->http->GetURL($startURL);
            $checker->captchaBeforeLoginForm();
            $this->savePageToLogs($checker);

            if (!$checker->getRightLoginURL()) {
                return false;
            }

            $checker->http->GetURL($this->http->currentUrl());

            $login = $checker->waitForElement(WebDriverBy::id('ap_email'), 10);
            $pass = $checker->waitForElement(WebDriverBy::id('ap_password'), 0);
            $sbm = $checker->waitForElement(WebDriverBy::id('signInSubmit'), 0);

            if ($rememberMe = $checker->waitForElement(WebDriverBy::name('rememberMe'), 0)) {
                $rememberMe->click();
            }

            if (!$login) {
                // save page to logs
                $this->savePageToLogs($checker);
                // captcha before login form
                if (!$checker->http->ParseForm("signIn") && $checker->http->ParseForm(null, "//form[@action = '/errors/validateCaptcha']")) {
                    $this->logger->notice("captcha before login form");
                    $captchacharacters = $checker->waitForElement(WebDriverBy::id('captchacharacters'), 0);
                    $btn = $checker->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Continue shopping")]'), 0);

                    if (!$captchacharacters || !$btn) {
                        return false;
                    }
                    // parse captcha
                    $captcha = $this->parseCaptcha();

                    if ($captcha === false) {
                        return false;
                    }

                    $captchacharacters->sendKeys($captcha);
                    $btn->click();
                }// captcha before login form

                $login = $checker->waitForElement(WebDriverBy::id('ap_email'), 10);
                $pass = $checker->waitForElement(WebDriverBy::id('ap_password'), 0);
                $sbm = $checker->waitForElement(WebDriverBy::id('signInSubmit'), 0);

                if ($rememberMe = $checker->waitForElement(WebDriverBy::name('rememberMe'), 0)) {
                    $rememberMe->click();
                }
                $this->savePageToLogs($checker);

                if (!$login) {
                    return false;
                }
            }

            $mover = new MouseMover($checker->driver);
            $mover->logger = $checker->logger;
            $mover->duration = rand(300, 1000);
            $mover->steps = rand(10, 20);

            $mover->moveToElement($login);
            $mover->click();
            $this->savePageToLogs($checker);

            // strange redirect to main page workaround, it works
            if (!$checker->waitForElement(WebDriverBy::id('ap_email'), 0)) {
                $retry = true;

                return false;
            }

            $login->sendKeys($this->AccountFields['Login']);
            // New login form
            if ($login && !$pass && !$sbm) {
                $this->logger->notice("New login form");
                sleep(1);
                $this->savePageToLogs($checker);
                $sbm = $checker->waitForElement(WebDriverBy::id('continue'), 0);
                $checker->driver->executeScript('setTimeout(function(){
                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
                }, 500)'); // document.querySelector('#continue').click();
                $sbm->click();

                $pass = $checker->waitForElement(WebDriverBy::id('ap_password'), 5);
                // save page to logs
                $this->savePageToLogs($checker);
            } else {
                $sbm = $checker->waitForElement(WebDriverBy::id('signInSubmit'), 0);
            }

            if (!$pass || !$sbm) {
                return false;
            }
            $mover->moveToElement($pass);
            $mover->click();
            $pass->sendKeys($this->AccountFields['Pass']);
//            $sbm->click();
//            $mover->moveToElement($sbm);
//            $checker->driver->getKeyboard()->sendKeys(WebDriverKeys::ENTER);
            $checker->driver->executeScript('setTimeout(function(){
                delete document.$cdc_asdjflasutopfhvcZLmcfl_;
                delete document.$cdc_asdjflasutopfhvcZLawlt_;
                document.querySelector(\'#signInSubmit\').click();
            }, 500)');

            $captchaInput = $checker->waitForElement(WebDriverBy::id('auth-captcha-guess'), 5);
            // save page to logs
            $this->savePageToLogs($checker);

            if ($captchaInput && $checker->waitForElement(WebDriverBy::id('auth-captcha-image'), 5)) {
                $pass = $checker->waitForElement(WebDriverBy::id('ap_password'), 0);

                if (!$pass) {
                    $this->logger->error('Failed to parse password field');

                    return false;
                }// if (!$pass)
                $mover->moveToElement($pass);
                $mover->click();
                $pass->clear()->sendKeys($this->AccountFields['Pass']);
                // save page to logs
                $this->savePageToLogs($checker);

                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    $this->logger->error('Failed to parse captcha');

                    return false;
                }// if ($captcha === false)

                $mover->moveToElement($captchaInput);
                $mover->click();
                $captchaInput->sendKeys(str_replace(' ', '', $captcha));

                $checker->waitForElement(WebDriverBy::id('signInSubmit'), 0)->click();

                $checker->waitForElement(WebDriverBy::id('nav-item-signout'), 7, false);
                $this->savePageToLogs($checker);
            }// if ($captchaInput && $checker->waitForElement(WebDriverBy::id('auth-captcha-image'), 5))
            elseif ($captchaInput = $checker->waitForElement(WebDriverBy::xpath('//input[@name = "cvf_captcha_input"]'), 0)) {
                $captcha = $this->parseCaptcha();

                if ($captcha === false) {
                    $this->logger->error('Failed to parse captcha');

                    return false;
                }// if ($captcha === false)

                $mover->moveToElement($captchaInput);
                $mover->click();
                $captchaInput->sendKeys(str_replace(' ', '', $captcha));

                $checker->waitForElement(WebDriverBy::xpath('//input[@name = "cvf_captcha_captcha_action"]'), 0)->click();
                $this->savePageToLogs($checker);
            }

            // To complete the sign-in, you should respond to the notification that was sent to you
            $xpath = '//span[
                contains(text(), "To complete the sign-in, please respond to the notification sent to:")
                or (contains(text(), "To complete the sign-in,") and contains(text(), " the notification sent to:"))
                or (contains(normalize-space(), "To continue, approve the notification sent to:"))
                or contains(text(), "Authentication required. Please respond to the notification sent to:")
                or contains(text(), "Approve the notification sent to:")
                or contains(text(), "Approuver la notification envoyée à")
                or contains(text(), "Authentifizierung erforderlich Bitte antworten Sie auf die Benachrichtigung an:")
                or contains(text(), "Authentification requise. Veuillez répondre à la notification envoyée à")
                or contains(text(), "Pour terminer l’inscription, «")
                or contains(text(), "Pour continuer, approuver la notification envoyée à")
                or contains(., "Pour votre sécurité, validez la notification envoyée")
                or contains(text(), "Genehmigen Sie die Benachrichtigung, die gesendet wurde an:")
                or contains(text(), "Genehmigen Sie zu Ihrer Sicherheit die Benachrichtigung, die ")
                or contains(text(), "Benachrichtigung an folgende Adresse genehmigen:")
                or contains(., "For your security, approve the notification sent to:")
            ]';

            if ($this->http->FindSingleNode($xpath)) {
                $sleep = 60;
                $startTime = time();

                do {
                    $this->logger->debug("(time() - \$startTime) = " . (time() - $startTime) . " < {$sleep}");
                    sleep(5);
                    // save page to logs
                    $this->savePageToLogs($checker);
                } while (
                    ((time() - $startTime) < $sleep)
                    && $checker->waitForElement(WebDriverBy::xpath($xpath), 0)
                );

                if ($checker->waitForElement(WebDriverBy::xpath($xpath), 0)) {
                    throw new CheckException("To complete the sign-in, you should respond to the notification that was sent to you", ACCOUNT_PROVIDER_ERROR);
                }
            }

            $signout = $checker->waitForElement(WebDriverBy::id('nav-item-signout'), 7, false);
            $this->savePageToLogs($checker);

            // site asks to set up a two-factor auth
            if (!$signout
                && $skipBtn = $checker->waitForElement(WebDriverBy::xpath('//a[@id = "ap-account-fixup-phone-skip-link"]'), 0)
            ) {
                $skipBtn->click();
                $checker->waitForElement(WebDriverBy::id('nav-item-signout'), 7, false);
                $this->savePageToLogs($checker);
            }

            $cookies = $checker->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } catch (ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage(), ['HtmlEncode' => true]);
            $this->DebugInfo = "ScriptTimeoutException";
            // retries
            if (strstr($e->getMessage(), 'timeout: Timed out receiving message from renderer')) {
                $retry = true;
            }
        }// catch (ScriptTimeoutException $e)
        catch (WebDriverCurlException $e) {
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
            $retry = true;
        }// catch (WebDriverCurlException $e)
        finally {
            // close Selenium browser
            $this->logger->debug("[Current URL]: {$checker->http->currentUrl()}");
            $this->seleniumURL = $checker->http->currentUrl();
            $checker->http->cleanup(); //todo

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        /**
         * There was a problem.
         *
         * Sorry, you've made too many failed attempts.
         * We blocked your Sign-In to protect it against unauthorized access.
         * Please Sign-In with a device that you've previously used, or contact Amazon Customer Service.
         */
        if ($message = $this->http->FindSingleNode('//div[contains(normalize-space(text()), "Sorry, you\'ve made too many failed attempts. We blocked your Sign-In to protect it against unauthorized access.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Two-Step Verification, 28 Dec 2018
        if (
            $this->http->FindSingleNode("//h1[
                contains(text(), 'Two-Step Verification')
                or contains(text(), 'Zwei-Schritt-Verifizierung')
                or contains(text(), 'Processus de vérification en deux étapes')
            ]")
            && $this->http->ParseForm("auth-select-device-form")
        ) {
            // Text me at my number ending in ...
            $smsOption = $this->http->FindSingleNode("(//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'SMS')]/@value)[1]")
                ?? $this->http->FindSingleNode("//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'TOTP')]/@value")
            ;

            if (!$smsOption) {
                return false;
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            $formURL = 'mfa/new-otp';
            $this->http->NormalizeURL($formURL);
            $this->http->FormURL = $this->seleniumURL ?? $formURL;
            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
            $this->http->SetInputValue("otpDeviceContext", $smsOption);
            $this->http->PostForm();

            /**
             * There was a problem
             * Please wait at least one minute. Codes may take several minutes to arrive.
             */
            /**
             * There was a problem
             * Unable to deliver the OTP over a phone call currently.
             * Please wait 10 minutes and try again.
             * If you continue to get this message, try a different method, such as text or email.
             */
            if (
                ($error = $this->http->FindSingleNode("//span[
                        contains(text(), 'Please wait at least one minute. Codes may take several minutes to arrive.')
                        or contains(text(), 'Unable to deliver the OTP over a phone call currently')]
                    ")
                )
                && $this->http->FindSingleNode("//h1[contains(text(), 'Two-Step Verification')]")
                && $this->http->ParseForm("auth-select-device-form")
            ) {
                $this->logger->error($error);
                // Enter code from Authenticator App
                $smsOption =
                    $this->http->FindSingleNode("//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'TOTP')]/@value")
                    ?? $this->http->FindSingleNode("(//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'VOICE')]/@value)[1]");

                if (!$smsOption) {
                    return false;
                }

                $formURL = 'mfa/new-otp';
                $this->http->NormalizeURL($formURL);
                $this->http->FormURL = $this->seleniumURL ?? $formURL;
                $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
                $this->http->SetInputValue("otpDeviceContext", $smsOption);
                $this->http->PostForm();
            }
            /**
             * There was a problem
             * Unable to deliver the OTP over a phone call currently.
             * Please wait 10 minutes and try again.
             * If you continue to get this message, try a different method, such as text or email.
             */
            if (
                ($error = $this->http->FindSingleNode("//span[
                        contains(text(), 'Please wait at least one minute. Codes may take several minutes to arrive.')
                        or contains(text(), 'Unable to deliver the OTP over a phone call currently')]
                    ")
                )
                && $this->http->FindSingleNode("//h1[contains(text(), 'Two-Step Verification')]")
                && $this->http->ParseForm("auth-select-device-form")
                && count($this->http->FindNodes("//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'VOICE')]/@value")) > 1
            ) {
                $this->logger->error($error);
                // Enter code from Authenticator App
                $smsOption = $this->http->FindSingleNode("(//form[@id = 'auth-select-device-form']//input[@name = 'otpDeviceContext' and contains(@value, 'VOICE')]/@value)[2]");

                if (!$smsOption) {
                    return false;
                }

                $formURL = 'mfa/new-otp';
                $this->http->NormalizeURL($formURL);
                $this->http->FormURL = $this->seleniumURL ?? $formURL;
                $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
                $this->http->SetInputValue("otpDeviceContext", $smsOption);
                $this->http->PostForm();
            }
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'Two-Step Verification')]") && $this->http->ParseForm("auth-select-device-form"))

        if (
            $this->http->FindSingleNode("//span[
                contains(text(), 'Enter verification code')
                or contains(text(), 'Saisir le code de vérification')
            ]")
            && $this->http->ParseForm("verification-code-form")
        ) {
            $question = $this->http->FindSingleNode('//span[
                contains(text(), "For your security, we\'ve sent the code to your")
                or contains(text(), "Pour votre sécurité, nous avons envoyé le code sur votre")
            ]');

            if (!$question) {
                return false;
            }

            $formURL = $this->http->FormURL;
            $this->logger->debug("[FormURL]: {$formURL}");
            $this->http->NormalizeURL($formURL);

            if ($formURL == '/ap/cvf/approval/verifyOtp') {
                $parseSeleniumURL = parse_url($this->seleniumURL);
                $formURL = 'https://' . $parseSeleniumURL['host'] . $formURL;
            }

            $this->http->FormURL = $formURL;
            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");

            $this->Question = Html::cleanXMLValue($question);
            $this->ErrorCode = ACCOUNT_QUESTION;
            $this->Step = "QuestionOTPCode";

            return true;
        }

        // Verification needed
        // Authentication required
        if (
            $this->http->FindSingleNode("//h1[
                contains(text(), 'Verification needed')
                or contains(text(), 'Vérification requise')
                or contains(text(), 'Authentication required')
                or contains(text(), 'Überprüfung erforderlich')
            ]")
            && $this->http->ParseForm("claimspicker")
        ) {
            if ($this->getWaitForOtc()) {
                $this->sendNotification("2fa - refs #20468 // RR");
            }

            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck()) {
                $this->Cancel();
            }

            // hard code, AccountID: 4035667
            $options = $this->http->FindNodes("(//form[@action = 'verify'])[1]//input[@name = 'option']/@value");
            $this->logger->debug(var_export($options, true), ['pre' => true]);
            // As a text message - +1+1723222222
//            if (in_array($this->AccountFields['Login'], ['nanyaer131@gmail.com', 'louisliding@gmail.com']))
            $smsText = $this->http->FindSingleNode("//input[@name = 'option' and @value ='sms']/following-sibling::span");
            $this->logger->debug($smsText);

            if (
                $this->http->FindSingleNode("//input[@name = 'option' and @value ='email']/following-sibling::span")
                || (!$smsText && (isset($this->http->Form['option']) && strstr($this->http->Form['option'], ' Device')))
            ) {
                $this->http->SetInputValue("option", "email");
            } elseif (
                strstr($smsText, 'Text a one-time passcode to +')
                || strstr($smsText, '(OTP) to +')
                || (isset($this->http->Form['option']) && strstr($this->http->Form['option'], ' Device'))
            ) {
                $this->http->SetInputValue("option", "sms");
            }

            $this->http->SetInputValue("openid.return_to", "https://www.amazon.com:80/gp/yourstore/home?ie=UTF8&ref_=nav_custrec_signin");

            $formURL = 'cvf/verify';
            $this->http->NormalizeURL($formURL);
            $this->http->FormURL = $formURL;
            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");

            $formURL = str_replace('cvf/cvf', 'cvf', $formURL);
            $this->http->FormURL = $formURL;
            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");

            $this->State['FormURL'] = $formURL;
            $this->http->PostForm();

            // Your email or password was incorrect. Please try again.
            // AccountID: 4280139
            if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your email or password was incorrect. Please try again')]")) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
        }

        if ($this->http->FindSingleNode('//span[
                contains(text(), "Anti-Automation Challenge")
                or contains(text(), "Please solve this puzzle so we know you\'re a real person")
                or contains(text(), "Please solve this puzzle so we know that you\'re a real person")
                or contains(text(), "Résoudre ce puzzle pour protéger votre compte")
            ]')
        ) {
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue("cvf_captcha_input", $captcha);
            $this->http->PostForm();
        }

        $question = $this->http->FindSingleNode("//div[@id = 'dcq_question_1']");

        if (!isset($question)) {
            $question = $this->http->FindSingleNode("(//label[@for = 'question'])[1]");
        }

        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//input[@name = 'question_dcq_question_subjective_1']/preceding-sibling::label");
        }
        // What is the expiration date for your AmericanExpress ending in...
        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//input[@name = 'question_dcq_question_date_picker_1']/preceding-sibling::label");

            if ($question) {
                $question .= ' (MM/YYYY)';
            }
        }
        // Enter the code generated  by your Authenticator App
        // or
        // Enter the code that has been sent to a phone number ending in ...
        if (!isset($question)) {
            // WARNING: first place, see second place also
            $question = $this->http->FindSingleNode("
                //p[contains(text(), 'Enter the code ')]
                | //p[contains(text(), 'Enter the OTP that has been sent to a phone number')]
                | //p[contains(text(), 'For added security, please enter the One Time Password (OTP) ')]
                | //p[contains(text(), 'Geben Sie den Code ein, der von ')]
                | //p[contains(text(), 'Für mehr Sicherheit geben Sie bitte das Einmalkennwort ein, das ')]
                | //p[contains(text(), 'Für mehr Sicherheit geben Sie bitte das von Ihrer Authentifizierungs-App generierte Einmalkennwort ein.')]
                | //p[contains(text(), 'Enter the One Time Password (OTP) ')]
                | //p[contains(text(), 'Enter the OTP generated by your')]
                | //div[span[contains(text(), 'One Time Password (OTP) sent to ')]]
                | //label[contains(text(), 'Saisir un code')]
                | //label[contains(text(), 'Saisissez le code OTP')]
                | //div[span[contains(text(), 'Code sent') or contains(text(), 'Code envoyé') or contains(text(), 'Code gesendet')]]
                | //div[span[contains(text(), 'Code sent') or contains(text(), 'Code envoyé')]]
            ");
        }
        // For your security, we need to verify your identity. We've sent a code to the email ... . Please enter it below.
        if (!isset($question)) {
            $question = $this->http->FindSingleNode("//div[span[contains(text(), 'For your security, we need to verify your identity.') or contains(text(), 'Pour votre sécurité, nous devons vérifier votre identité.')]]");
        }

        if (empty($question)) {
            return false;
        }

        if (!$this->http->ParseForm("ap_dcq_form") && !$this->http->ParseForm("auth-mfa-form") && !$this->http->ParseForm(null, "//form[@action = 'verify']")) {
            return false;
        }
        $this->Question = Html::cleanXMLValue($question);
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";
        $this->logger->debug("[FormURL]: {$this->http->FormURL}");
        // refs #13176
        if ($this->http->ParseForm(null, "//form[@action = 'verify']")) {
            $formURL = 'cvf/verify';

            if (!strstr($this->http->FormURL, '/ap/cvf/verify')) {
                $this->http->NormalizeURL($formURL);
                $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
                $this->http->FormURL = $formURL;
            }
            $this->State['FormURL'] = $this->http->FormURL;
            $this->State['Form'] = $this->http->Form;
            $this->State['verifyToken'] = $this->http->FindSingleNode("(//form[@action = 'verify'])[1]//input[@name = 'verifyToken']/@value");
            $this->State['metadata1'] = $this->http->FindSingleNode("(//form[@action = 'verify'])[1]//input[@name = 'metadata1']/@value");
            $this->State['cvfDcqAction'] = $this->http->FindSingleNode("(//form[@action = 'verify'])[1]//input[@name = 'cvfDcqAction']/@value");
            $this->logger->debug(var_export($this->State, true), ['pre' => true]);
            $this->logger->debug(var_export($this->http->Form, true), ['pre' => true]);
        }// if ($this->http->ParseForm(null, true, 1, "//form[@action = 'verify']"))

        if ($this->http->ParseForm("ap_dcq_form")) {
            $formURL = 'dcq';
            $this->http->NormalizeURL($formURL);
            $this->http->FormURL = $formURL;
            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
            $this->State['FormURL'] = $formURL;
            $this->State['Form'] = $this->http->Form;
            $this->logger->debug(var_export($this->State, true), ['pre' => true]);
            $this->logger->debug(var_export($this->http->Form, true), ['pre' => true]);
        }// if ($this->http->ParseForm("ap_dcq_form"))

        if ($this->http->ParseForm("auth-mfa-form")) {
            if (!strstr($this->http->FormURL, '/ap/signin')) {
                $formURL = 'signin';
                $this->http->NormalizeURL($formURL);
                $this->http->FormURL = $formURL;
            }

            $this->logger->debug("[FormURL modified]: {$this->http->FormURL}");
            // refs #19094
            $this->http->FormURL = str_replace('ap/mfa/mfa/signin', 'ap/mfa/signin', $this->http->FormURL);
            $this->logger->debug("[FormURL modified - 2]: {$this->http->FormURL}");
            $this->State['FormURL'] = $this->http->FormURL;

            // Two-Step Verification, 28 Dec 2018
            if ($this->http->FindSingleNode("//input[@id = 'auth-signin-button' and @name = 'mfaSubmit']/@name")) {
                $this->http->SetInputValue("mfaSubmit", "Submit");
            }

            $this->State['Form'] = $this->http->Form;
            $this->logger->debug(var_export($this->State, true), ['pre' => true]);
            $this->logger->debug(var_export($this->http->Form, true), ['pre' => true]);
        }// if ($this->http->ParseForm("ap_dcq_form"))

        return true;
    }

    public function ProcessStep($step)
    {
        if ($step == 'QuestionOTPCode') {
            $this->http->SetInputValue("otpCode", $this->Answers[$this->Question]);
            $this->http->SetInputValue("action", "code");
            unset($this->Answers[$this->Question]);

            if (!$this->http->PostForm()) {
                return false;
            }

            return true;
        }

        // WARNING: second place, see first place also
        if (
            strstr($this->Question, 'Enter the code ')
            || strstr($this->Question, 'Saisir un code')
            || strstr($this->Question, 'Enter the OTP')
            || strstr($this->Question, 'One Time Password (OTP)')
            || strstr($this->Question, 'Enter the One Time Password (OTP) ')
            || strstr($this->Question, 'Saisissez le code OTP')
            || strstr($this->Question, 'Geben Sie den Code ein, der von')
            || strstr($this->Question, 'Für mehr Sicherheit geben Sie bitte das von Ihrer Authentifizierungs-App')
        ) {
            $this->logger->debug("Two-Step Verification");

            $this->logger->debug("[FormURL]: {$this->http->FormURL}");

            if (isset($this->State['FormURL'])) {
                $this->http->FormURL = $this->State['FormURL'];
                $this->logger->debug("[Restoring form URL]: {$this->http->FormURL}");
            }// if (isset($this->State['FormURL']))

            if (isset($this->State['Form'])) {
                $this->logger->debug("Restoring form values...");
                $this->http->Form = $this->State['Form'];
            }// if (isset($this->State['FormURL']))

            if (strstr($this->Question, 'One Time Password (OTP) sent to ')) {
                $this->http->SetInputValue("code", $this->Answers[$this->Question]);
                $this->http->SetInputValue("action", "code");
            } else {
                $this->http->SetInputValue("otpCode", $this->Answers[$this->Question]);
            }
            unset($this->Answers[$this->Question]);
            $this->http->SetInputValue("rememberDevice", "");

            if (!$this->http->PostForm()) {
                return false;
            }
            // The code you entered is not valid. Please try again.
            if ($error = $this->http->FindSingleNode('
                    //span[
                        contains(text(), "The code you entered is not valid.")
                        or contains(text(), "Le code que vous avez saisi n\'est pas valide.")
                        or contains(text(), "The code you have entered is not valid.")
                        or contains(text(), "The One Time Password (OTP) you entered is not valid.")
                    ]
                    | //div[
                        contains(text(), "Invalid code. Please check your code and try again.")
                        or contains(text(), "Invalid OTP. Please check your code and try again.")
                    ]
                ')
            ) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }

            if ($error = $this->http->FindSingleNode('
                    //span[
                        contains(text(), "Unable to deliver the OTP over a phone call currently. Please wait 10 minutes and try again. If you continue to get this message, try a different method, such as text.")
                    ]
                ')
            ) {
                throw new CheckException($error, ACCOUNT_PROVIDER_ERROR);
            }

            $this->seleniumHomePage($this->http->currentUrl(), true);
        }// if (strstr($this->Question, 'Enter the code generated by your Authenticator App'))
        elseif (
            strstr($this->Question, 'We\'ve sent a code to the email')
            || strstr($this->Question, 'Nous avons envoyé un code par e-mail sur')
            || strstr($this->Question, 'Code sent to')
            || strstr($this->Question, 'Code envoyé à')
        ) {
            $this->logger->debug("Verification via code which was sent to the email");

            if ($this->getWaitForOtc()) {
                $this->sendNotification("2fa - refs #20468 // RR");
            }

            $this->logger->debug("[FormURL]: {$this->http->FormURL}");

            if (isset($this->State['FormURL'])) {
                $this->http->FormURL = $this->State['FormURL'];
                $this->logger->debug("[Restoring form URL]: {$this->http->FormURL}");
            }// if (isset($this->State['FormURL']))

            if (isset($this->State['Form'])) {
                $this->logger->debug("Restoring form values...");
                $this->http->Form = $this->State['Form'];
            }// if (isset($this->State['FormURL']))

            if (isset($this->State['verifyToken'])) {
                $this->http->SetInputValue("verifyToken", $this->State['verifyToken']);
            }

            if (isset($this->State['metadata1'])) {
                $this->http->SetInputValue("metadata1", $this->State['metadata1']);
            }

            if (isset($this->State['cvfDcqAction'])) {
                $this->http->SetInputValue("cvfDcqAction", $this->State['cvfDcqAction']);
            }

            $this->http->SetInputValue("code", $this->Answers[$this->Question]);
            $this->http->SetInputValue("action", "code");
            unset($this->Answers[$this->Question]);

            if (!$this->http->PostForm()) {
                return false;
            }
            // Invalid code. Please check your code and try again.
            if ($error = $this->http->FindSingleNode("//div[contains(text(), 'Invalid code. Please check your code and try again.') or contains(text(), 'Code non valide.')]")) {
                $this->AskQuestion($this->Question, $error, "Question");

                return false;
            }
        }// elseif (strstr($this->Question, 'We\'ve sent a code to the email'))
        else {// Just Question
            $this->logger->notice("Just Question");
            $questions[] = $this->Question;

            $this->logger->debug("[FormURL]: {$this->http->FormURL}");

            if (isset($this->State['FormURL'])) {
                $this->http->FormURL = $this->State['FormURL'];
                $this->logger->debug("[Restoring form URL]: {$this->http->FormURL}");
            }// if (isset($this->State['FormURL']))

            if (isset($this->State['Form'])) {
                $this->logger->debug("Restoring form values...");
                $this->http->Form = $this->State['Form'];
            }// if (isset($this->State['FormURL']))

            // Question 2
            $question2 = $this->http->FindSingleNode("//div[@id = 'dcq_question_2']");

            if (!isset($question2)) {
                $question2 = $this->http->FindSingleNode("(//label[@for = 'question'])[2]");
            }

            if (!isset($question2)) {
                $question2 = $this->http->FindSingleNode("//input[@name = 'question_dcq_question_subjective_2']/preceding-sibling::label");
            }

            if (isset($question2)) {
                $this->logger->debug("question 2 found");

                if (!isset($this->Answers[$question2])) {
                    $this->logger->debug("ask question 2");
                    $this->AskQuestion($question2);

                    return false;
                }// if (!isset($this->Answers[$question2]))
                else {
                    $questions[] = $question2;
                    $this->http->SetInputValue("dcq_question_subjective_2", $this->Answers[$question2]);
                }
            }// if (isset($question2))
            // Question 1
            if (!isset($this->Answers[$this->Question])) {
                $this->logger->error("answer not found");

                return false;
            }// if (!isset($this->Answers[$this->Question]))
            // What is the expiration date for your AmericanExpress ending in XXXXX (MM/YYYY)
            if ($this->http->InputExists('question_dcq_question_date_picker_1') && $this->http->InputExists('dcq_question_date_picker_1_2')) {
                $questionData = explode('/', $this->Answers[$this->Question]);

                if (count($questionData) != 2) {
                    $this->logger->error("wrong answer");
                    $this->logger->debug(var_export($questionData, true), ['pre' => true]);
                    $this->AskQuestion($this->Question, "Please enter date in appropriate format: MM/YYYY", "Question");

                    return false;
                }// if (count($questionData) != 2)
                $this->http->SetInputValue("dcq_question_date_picker_1_1", $questionData[0]);
                $this->http->SetInputValue("dcq_question_date_picker_1_2", $questionData[1]);
                $this->http->SetInputValue("cvfDcqAction", "verify");
            }// if ($this->http->InputExists('question_dcq_question_date_picker_1') && $this->http->InputExists('dcq_question_date_picker_1_2'))
            else {
                $this->http->SetInputValue("dcq_question_subjective_1", $this->Answers[$this->Question]);

                if (
                    $this->http->FindSingleNode("//input[@name = 'DCQAppActionType']/@value")
                    || $this->http->FindSingleNode("//input[@name = 'dcqSessionId']/@value")) {
                    $this->http->SetInputValue("DCQAppActionType", $this->http->FindSingleNode("//input[@name = 'DCQAppActionType']/@value"));
                    $this->http->SetInputValue("dcq.arb.key", $this->http->FindSingleNode("//input[@name = 'dcq.arb.key']/@value"));
                    $this->http->SetInputValue("dcq.arb.value", $this->http->FindSingleNode("//input[@name = 'dcq.arb.value']/@value"));
                    $this->http->SetInputValue("dcqSessionId", $this->http->FindSingleNode("//input[@name = 'dcqSessionId']/@value"));
                } else {
                    $this->http->SetInputValue("cvfDcqAction", "verify");
                }
            }

            if (!$this->http->PostForm()) {
                return false;
            }

            if (strstr($this->Question, 'One Time Password (OTP)')) {
                $this->logger->notice("remove answer");
                unset($this->Answers[$this->Question]);
            }

            // If error ask again
            if (
                $this->http->FindSingleNode("//div[@id = 'message_error' or @id = 'message_warning']")
                || $this->http->FindSingleNode("
                        //span[contains(text(), 'you entered does not match the code on record.')]
                        | //span[contains(text(), 'you entered do not match the number and the code on record.')]
                    ")
            ) {
                foreach ($questions as $question) {
                    unset($this->Answers[$question]);
                }
                // Internal Error. Please try again later.
                if ($message = $this->http->FindPreg("/(Internal Error\. Please try again later\.)/ims")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                $this->parseQuestion();

                return false;
            }// if ($this->http->FindSingleNode("//div[@id = 'message_error' or @id = 'message_warning']"))
            // Sorry, you've made too many failed attempts. We blocked your sign-in to protect it against unauthorized access.
            if ($message = $this->http->FindPreg("/(?:Sorry, you\'ve made too many failed attempts\.\s*We blocked your sign-in to protect it against unauthorized access\.|Nous sommes désolés, trop de tentatives ont échoué\.\s*Nous avons bloqué votre compte afin de le protéger contre un accès non autorisé\.)/ims")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }// Just Question

        return true;
    }

    public function tryEnterCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $this->http->MultiValuedForms = true;

        if (isset($this->http->XPath)
            && ($this->http->FindSingleNode("//*[contains(text(), 'To better protect your account, please re-enter your password')]")
                || $this->http->FindSingleNode("//li[contains(text(), 'To better protect your account, please re-enter your password')]")
                || $this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image')]")
                || $this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are given in the challenge.')]")
                || $this->http->FindNodes("//*[contains(text(), 'Saisissez les caractères que vous voyez')]")
                || $this->http->FindNodes("//*[contains(normalize-space(text()), 'Geben Sie die dargestellten Zeichen ein')]")
            )
            && ($this->http->ParseForm("ap_signin_form") || $this->http->ParseForm("signIn"))) {
            // parse captcha
            $captcha = $this->parseCaptcha();

            if ($captcha === false) {
                return false;
            }
            $this->http->SetInputValue('email', $this->AccountFields['Login']);
            $this->http->SetInputValue('password', $this->AccountFields['Pass']);
            $this->http->SetInputValue('guess', str_replace(' ', '', $captcha));

            $this->http->PostForm();
        }
        $this->http->MultiValuedForms = false;

        return true;
    }

    public function Login()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                // try to enter the captcha
                $this->tryEnterCaptcha();
                $this->checkProviderErrors();
                $retryCaptcha = 0;

                while ($this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image') or contains(text(), 'Enter the characters as they are given in the challenge.')]") && $retryCaptcha < 2) {
                    $retryCaptcha++;
                    $this->tryEnterCaptcha();

                    $this->checkProviderErrors();
                }

                $this->checkProviderErrors();

                // Access is allowed
                if ($this->http->FindSingleNode("//a[contains(@href, 'sign-out') and contains(., 'Not')]/@href")
                    || $this->http->FindSingleNode("//a[contains(@href, 'sign-out') and contains(., 'Sign Out')]/@href")
                    // Hello ...
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]")
                    || $this->http->FindPreg("/Not [A-Za-z\.]+\? Sign Out/ims")) {
                    return true;
                }

                // Security Questions
                if ($this->http->FindSingleNode("//h1[contains(text(), 'To continue, please answer one of the security questions below')] | //h1[normalize-space(text()) = 'Security questions']")
                    || $this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }
                // E-mail address already in use
                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'E-mail address already in use')]/following::div[@class = 'a-alert-content']")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->captchaMessage();

                // Internal Error. Please try again later.
                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'There was a problem')]/following-sibling::div[contains(., 'Internal Error. Please try again later.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Germany':
                // try to enter the captcha
                $this->tryEnterCaptcha();
                $this->checkProviderErrors();

                // Access is allowed
                if (
                    // Hallo ...
                    $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hallo')]")
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]")// AccountID: 5322066
                ) {
                    return true;
                }

                // Security Questions
                if ($this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }
                // Falsches Passwort
                if ($message = $this->http->FindSingleNode("//h4[
                        contains(text(), 'Ein Problem ist aufgetreten:')
                        or contains(text(), 'There was a problem')
                    ]/following::div[@class = 'a-alert-content']//span[
                        contains(text(), 'Falsches Passwort')
                        or contains(text(), 'Your password is incorrect')
                    ]")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'France':
                // try to enter the captcha
                $this->tryEnterCaptcha();
                $this->checkProviderErrors();
                $retryCaptcha = 0;

                if ($this->http->FindSingleNode("//h4[contains(text(), 'Enter the characters you see below')]") && $this->http->ParseForm(null, "//form[contains(@action, 'validateCaptcha')]")) {
                    // parse captcha
                    $captcha = $this->parseCaptcha2();

                    if ($captcha === false) {
                        return false;
                    }
                    $form['field-keywords'] = $captcha;
                    $amzn = $this->http->FindSingleNode("//input[@name = 'amzn']/@value");
                    $amzn_pt = $this->http->FindSingleNode("//input[@name = 'amzn-pt']/@value");
                    $amzn_r = $this->http->FindSingleNode("//input[@name = 'amzn-r']/@value");
                    $this->http->GetURL("http://www.amazon.fr/errors/validateCaptcha?amzn={$amzn}&amzn-r={$amzn_r}&amzn-pt={$amzn_pt}&field-keywords={$captcha}");
                }// Enter the characters you see below

                while ($this->http->FindNodes("//*[contains(text(), 'Saisissez les caractères que vous voyez')]") && $retryCaptcha < 2) {
                    $retryCaptcha++;
                    $this->tryEnterCaptcha();

                    $this->checkProviderErrors();
                }

                $this->checkProviderErrors();

                // Access is allowed
                if ($this->http->FindPreg("/tes pas ([^\?]+)\? Déconnectez-vous/ims")
                    // Hello ...
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Bonjour')]")
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]")// AccountID: 4939745
                ) {
                    return true;
                }

                // Saisissez les caractères tels qu'ils apparaissent sur l'image.
                if ($this->http->FindNodes('//h4[contains(text(), "Un problème est survenu")]/following-sibling::div[contains(., "Saisissez les caractères tels qu\'ils apparaissent sur l\'image.")]')) {
                    throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }

                // Security Questions
                if (
                    $this->http->FindSingleNode("//h1[normalize-space(text()) = 'Questions de sécurité']")
                    || $this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }

                $this->captchaMessage();

                break;

            case 'Canada':
                // try to enter the captcha
                $this->tryEnterCaptcha();
                $this->checkProviderErrors();
                $retryCaptcha = 0;

                while ($this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image') or contains(text(), 'Enter the characters as they are given in the challenge.')]") && $retryCaptcha < 2) {
                    $retryCaptcha++;
                    $this->tryEnterCaptcha();

                    $this->checkProviderErrors();
                }// while ($this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image')]") && $retryCaptcha < 2)

                $this->checkProviderErrors();

                // Access is allowed
                if ($this->http->FindSingleNode("//a[contains(@href, 'sign-out') and contains(., 'Not')]/@href")
                    || $this->http->FindSingleNode("//a[contains(@href, 'sign-out') and contains(., 'Sign Out')]/@href")
                    // Hello ...
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]")
                    || $this->http->FindPreg("/Not [A-Za-z\.]+\? Sign Out/ims")) {
                    return true;
                }
                // Access is allowed
                if ($this->http->FindPreg("/tes pas ([^\?]+)\? Déconnectez-vous/ims")
                    // Hello ...
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Bonjour')]")) {
                    return true;
                }

                // Security Questions
                if ($this->http->FindSingleNode("//h1[contains(text(), 'To continue, please answer one of the security questions below')] | //h1[normalize-space(text()) = 'Security questions']")
                    || $this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }
                // E-mail address already in use
                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'E-mail address already in use')]/following::div[@class = 'a-alert-content']")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Your password is incorrect
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your password is incorrect')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->captchaMessage();

                // Internal Error. Please try again later.
                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'There was a problem')]/following-sibling::div[contains(., 'Internal Error. Please try again later.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                break;

            case 'Japan':
            case 'USA': default:
                // try to enter the captcha
                $this->tryEnterCaptcha();
                $this->checkProviderErrors();
                $retryCaptcha = 0;

                while ($this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image') or contains(text(), 'Enter the characters as they are given in the challenge.')]") && $retryCaptcha < 2) {
                    $retryCaptcha++;
                    $this->tryEnterCaptcha();

                    $this->checkProviderErrors();
                }// while ($this->http->FindNodes("//*[contains(text(), 'Enter the characters as they are shown in the image')]") && $retryCaptcha < 2)

                // We haven't seen you using this device before
                if ($this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
                    if (!$this->parseQuestion()) {
                        return false;
                    }
                }

                $this->checkProviderErrors();

                // Access is allowed
                if ($this->http->FindNodes("//a[contains(@href, 'sign-out')]/@href")
                    || $this->http->FindNodes("//a[contains(@href, 'signout')]/@href")
                    // Hello ...
                    || $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]")
                    || $this->http->FindPreg("/Not [A-Za-z\.]+\? Sign Out/ims")
                    || $this->http->FindPreg("/No eres [A-Za-z\.]+\? Cerrar sesión/ims")) {
                    return true;
                }

                // name is not found
                if ($this->http->FindPreg("/Not \? Sign Out/ims")) {
                    return true;
                }

                $this->captchaMessage();

                break;
        }

        return false;
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);

        switch ($this->AccountFields['Login2']) {
            case 'UK':
                //# Invalid credentials
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error with your E-Mail/Password combination')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your e-mail or password was incorrect. Please try again.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We cannot find an account with that e-mail address
                if ($message = $this->http->FindSingleNode("//*[
                        contains(text(), 'We cannot find an account with that e-mail address')
                        or contains(text(), 'We cannot find an account with that mobile number')
                    ]")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Your password is incorrect
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your password is incorrect')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Enter a valid e-mail or mobile number
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Enter a valid e-mail or mobile number')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Invalid e-mail address or mobile phone number
                if ($message = $this->http->FindSingleNode("//div[@id = 'auth-error-message-box']//span[contains(text(), 'Invalid e-mail address or mobile phone number')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                //# We've blocked your sign-in to protect it against unauthorised access.
                if ($message = $this->http->FindSingleNode('//p[contains(text(), "We\'ve blocked your sign-in to protect it against unauthorised access.")]')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // Your account has been locked for security purposes.
                if ($message = $this->http->FindSingleNode('//span[contains(text(), "Your account has been locked for security purposes.")]')) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // Password assistance
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Password assistance')]") && $this->http->ParseForm("forgotPassword")) {
                    throw new CheckException("Account has been locked", ACCOUNT_LOCKOUT);
                }
                // For your security, we need you to reset the password on your account.
                if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'Germany':
                // For your security, we need you to reset the password on your account.
                if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'Zu Ihrer eigenen Sicherheit müssen wir das Passwort für Ihr Konto zurücksetzen. Hierfür senden wir Ihnen einen Code zu.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Ein Problem ist aufgetreten:')]", null, true, "/([^\:]+)/")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // We cannot find an account with that e-mail address
                if ($message = $this->http->FindSingleNode("//*[
                        contains(text(), 'We cannot find an account with that e-mail address')
                        or contains(text(), 'We cannot find an account with that mobile number')
                    ]")
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'France':
                // Votre mot de passe est incorrect
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Votre mot de passe est incorrect')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Pour votre sécurité, nous vous demandons de réinitialiser le mot de passe de votre compte.
                if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'Pour votre sécurité, nous vous demandons de réinitialiser le mot de passe de votre compte.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Impossible de trouver un compte correspondant à cette adresse e-mail
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Impossible de trouver un compte correspondant à cette adresse e-mail')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                // hard code (AccountID: 3270884)
                if ($this->AccountFields['Login'] == 'stephanie_lautier@yahoo.fr') {
                    throw new CheckException("Votre mot de passe est incorrect", ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'Canada':
                // We cannot find an account with that e-mail address
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'We cannot find an account with that e-mail address')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // For your security, we need you to reset the password on your account.
                if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                break;

            case 'Japan':
            default:// USA
                // Invalid login or password
                if ($this->http->FindSingleNode("//font[contains(text(), 'The e-mail address and password you entered do not match any accounts on record')]")) {
                    throw new CheckException("The e-mail address and password you entered do not match any accounts on record", ACCOUNT_INVALID_PASSWORD);
                }
                // There was an error with your E-Mail/Password combination. Please try again.
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'There was an error with your E-Mail/Password combination')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'There was an error with your E-Mail/ Password combination.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Internal Error. Please try again later.
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Internal Error. Please try again later.')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // There was a problem with your request
                if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'There was a problem with your request')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }
                // There was an error with your Phone/Password combination. Please try again
                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'There was an error with your Phone/Password combination. Please ')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // The phone number you entered cannot be used to sign in.
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'The phone number you entered cannot be used to sign in.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                //# E-mail Address Already in Use
                if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'E-mail Address Already in Use')]/following::div[@id = 'ap_email_verify_lockout_warn_box']")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Your email or password was incorrect. Please try again
                if ($message = $this->http->FindSingleNode("//*[contains(text(), 'Your email or password was incorrect. Please try again')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Your password is incorrect
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your password is incorrect')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We can not find an account with that email address
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We can not find an account with that email address')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We cannot find an account with that mobile number
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We cannot find an account with that mobile number')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Invalid email address or mobile phone number
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Invalid email address or mobile phone number')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Enter a valid email or mobile number
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Enter a valid email or mobile number')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // We cannot find an account with that email address
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'We cannot find an account with that email address')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                // Your account has been locked for security purposes.
                if ($message = $this->http->FindSingleNode("//span[contains(text(), 'Your account has been locked for security purposes.')]")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }
                // For your security, we need you to reset the password on your account.
                if ($message = $this->http->FindSingleNode("//p[@class = 'a-spacing-none' and contains(., 'For your security, we need you to reset the password on your account.')]")) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }
                /*
                 * There is another Amazon account with the e-mail ... but with a different password.
                 * The e-mail address has already been verified by this other account and only one account can be active
                 * for an e-mail address. The password you signed in with is associated with an unverified account.
                 */
                if (($message = $this->http->FindSingleNode("//p[contains(text(), 'There is another Amazon account with the e-mail')]"))
                    || ($message = $this->http->FindSingleNode("//div[@id = 'ap_email_verify_lockout_warn_box']/p"))) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                if ($message = $this->http->FindSingleNode("//div[contains(text(), 'The information you supplied was reviewed by Amazon but we cannot remove the hold on your account at this time')]")) {
                    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
                }

                // Document submission required
                if ($this->http->FindSingleNode("//h4[
                        contains(text(), 'Document submission required')
                        or contains(text(), 'Account on hold temporarily')
                    ]")
                ) {
                    $this->throwProfileUpdateMessageException();
                }

            if ($message = $this->http->FindSingleNode("//h4[contains(text(), 'Amazon account deactivated')]")) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

                // Password assistance
                if ($this->http->FindSingleNode("//h1[contains(text(), 'Password assistance')]") && $this->http->ParseForm("forgotPassword")) {
                    throw new CheckException("Account has been locked", ACCOUNT_LOCKOUT);
                }
                // Your Amazon account is locked and order(s) are on hold.
                if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Your Amazon account is locked and order(s) are on hold.')]")) {
                    throw new CheckException($message, ACCOUNT_LOCKOUT);
                }

                break;
        }

        $message = $this->http->FindSingleNode('//p[@class = "a-spacing-none" and 
                contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll send a One Time Password (OTP) to authenticate this change.")
                or contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll email you a One Time Password (OTP) to authenticate this change.")
                or contains(., "Please set a new password for your account that you have not used elsewhere. We\'ll send a One Time Password (OTP) to your mobile number to authenticate this change.")
            ]');
        // 3938344, 4860140
        $message2 = $this->http->FindSingleNode("//h1[contains(.,'Password assistance') or contains(.,'Passworthilfe')]
            /following-sibling::p[contains(.,'Enter the email address or mobile phone number associated with your Amazon account.')  
            or contains(.,'Geben Sie die E-Mail-Adresse oder Mobiltelefonnummer ein, die mit Ihrem Amazon-Konto verbunden ist.')]");

        if ($message || $message2) {
            throw new CheckException("Password reset required", ACCOUNT_INVALID_PASSWORD);
        }

        if ($skipLink = $this->http->FindSingleNode('//a[contains(text(), "Not now") or contains(text(), "Pas maintenant")]/@href')) {
            $this->logger->notice("skip profile update");
            $this->http->GetURL($skipLink);
        }

        // This site can’t be reached
        if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]")) {
            $this->DebugInfo = "This site can’t be reached";

            throw new CheckRetryNeededException(5, 10);
        }// if ($this->http->FindSingleNode("//h1[contains(text(), 'This site can’t be reached')]"))
    }

    public function Parse()
    {
        switch ($this->AccountFields['Login2']) {
            case 'UK':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]", null, true, "/Hello,\s*([^<]+)/")));
                // Balance - Available Gift Certificate Balance
                $this->http->GetURL("https://www.amazon.co.uk/gp/css/gc/balance/ref=ya__34");
//                $balance = $this->http->FindSingleNode("//h3[contains(text(), 'Balance')]/span");
//                if (!$balance)
//                    $balance = $this->http->FindPreg("/<h3>Current\s*Balance\:\s*<span>([^<]+)<\/span>/");
                $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
                $this->SetBalance($balance);
                // Full Name
                $this->http->GetURL("https://www.amazon.co.uk/ap/cnep?_encoding=UTF8&openid.assoc_handle=gbflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.co.uk%2Fgp%2Fcss%2Fhomepage.html%3Fie%3DUTF8%26ref_%3Dya_cnep");
                $name = Html::cleanXMLValue($this->http->FindPreg("/<span[^>]*>\s*Name:\s*<\/span>\s*<\/div>\s*<div[^<]+>([^<]+)/"));

                if (strlen($name) > 3) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;

            case 'Germany':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hallo,') or contains(text(), 'Hello,')]", null, true, "/llo,\s*([^<]+)/")));
                //# Balance - Available Gift Certificate Balance
                $this->http->GetURL("https://www.amazon.de/gp/css/gc/balance/ref=ya__34");
                $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
                $this->SetBalance($balance);
                // Full Name
                $this->http->GetURL("https://www.amazon.de/ap/cnep?openid.return_to=https%3A%2F%2Fwww.amazon.de%2Fyour-account&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.assoc_handle=deflex&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.mode=checkid_setup&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&");
                $name = Html::cleanXMLValue($this->http->FindPreg("/<span[^>]*>\s*Name:\s*<\/span>\s*<\/div>\s*<div[^<]+>([^<]+)/"));

                if (strlen($name) > 3) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;

            case 'Japan':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]", null, true, "/Hello,\s*([^<]+)/")));
                // Balance - Available Gift Certificate Balance
                $this->http->GetURL("https://www.amazon.co.jp/gc/balance/ref=gc_balance_legacy_to_newgc");
                $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
                $this->SetBalance($balance);
                // Full Name
                $this->http->GetURL("https://www.amazon.co.jp/manage-your-profiles/profile");
                $name = Html::cleanXMLValue($this->http->FindSingleNode("//p[@id = 'home-profile-0']"));

                if (strlen($name) > 3) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;

            case 'France':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Bonjour')]", null, true, "/Bonjour\s*([^<]+)/")));
                // Balance - Solde de votre compte
                $this->http->GetURL("https://www.amazon.fr/gp/css/gc/balance/ref=ya__34");
                $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
                $this->SetBalance($balance);
                // Full Name
                $this->http->GetURL("https://www.amazon.fr/ap/cnep?_encoding=UTF8&openid.assoc_handle=frflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.fr%2Fgp%2Fcss%2Fhomepage.html%3Fie%3DUTF8%26ref_%3Dya_cnep");
                $name = Html::cleanXMLValue($this->http->FindPreg("/<span[^>]*>\s*Nom[^<]+<\/span>\s*<\/div>\s*<div[^<]+>\s*([^<]+)/ims"));

                if (strlen($name) > 3) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;

            case 'Canada':
                // Name
                $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,') or contains(text(), 'Bonjour,')]", null, true, "/,\s*([^<]+)/")));
                //# Balance - Available Gift Certificate Balance
                $this->http->GetURL("https://www.amazon.ca/gp/css/gc/balance/ref=ya__34");
                /*
                $balance = $this->http->FindSingleNode("//h3[contains(text(), 'Balance')]/span");
                if (!$balance)
                    $balance = $this->http->FindPreg("/<h3>Current\s*Balance\:\s*<span>([^<]+)<\/span>/");
                if (!$balance)
                    $balance = $this->http->FindSingleNode("//h3[contains(text(), 'Solde disponible')]/span");
                if (!$balance)
                    */
                // Balance - Your Gift Card Balance: CDN$ ...
                $balance = $this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']");
                $this->SetBalance($balance);
                // Full Name
                $this->http->GetURL("https://www.amazon.ca/gp/pdp/profile?ref_=ya_your_profile");
                $name = Html::cleanXMLValue($this->http->FindSingleNode("//span[@class = 'public-name-text']"));

                if (!$name) {
                    $name = Html::cleanXMLValue($this->http->FindPreg("/\"(?:field_data|nameHeaderData)\":\{\"name\":\"([^\"]+)/"));
                }

                if (strlen($name) > 3) {
                    $this->SetProperty("Name", beautifulName($name));
                }

                break;

            case 'USA': default:
                /*
                $this->seleniumHomePage('https://www.amazon.com/manage-your-profiles/home?ref_=ya_manage_your_profiles?ref_=ya_your_profile_rich-card');
                $this->http->GetURL("https://www.amazon.com/ap/cnep?_encoding=UTF8&ie=UTF8&openid.assoc_handle=usflex&openid.claimed_id=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.identity=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0%2Fidentifier_select&openid.mode=checkid_setup&openid.ns=http%3A%2F%2Fspecs.openid.net%2Fauth%2F2.0&openid.ns.pape=http%3A%2F%2Fspecs.openid.net%2Fextensions%2Fpape%2F1.0&openid.pape.max_auth_age=0&openid.return_to=https%3A%2F%2Fwww.amazon.com%2Fgp%2Fcss%2Fhomepage.html%3Fie%3DUTF8%26ref_%3Dya_cnep");
                */
                // Name
                $this->SetProperty("Name", beautifulName(
                    $this->http->FindPreg("/<span[^>]*>\s*(?:Name|Nombre):\s*<\/span>\s*<\/div>\s*<div[^<]+>([^<]+)/")
                    ?? $this->http->FindSingleNode("//span[contains(@class, 'nav-line-1') and contains(text(), 'Hello,')]", null, true, "/Hello,\s*([^<]+)/")
                    ?? $this->http->FindSingleNode("//h1[@id = 'profile-name'] | //p[@id = 'home-profile-0']")
                ));

                // SubAccounts   // refs #4861

                // Amazon (Gift Cards)
                $this->logger->info("Amazon (Gift Cards)", ['Header' => 3]);
                unset($balance);
//                $this->http->GetURL("https://www.amazon.com/gp/css/gc/balance/ref=ya_34");
                $this->http->GetURL("https://www.amazon.com/gp/product/features/unbox-process-gcpromo.html/ref=atv_dp_gc_getbalance?getBalance=1&showMobileApps=1&r=94208");
                // Balance Amazon (Gift Cards)
                if ($this->http->FindSingleNode("//h4[contains(text(), 'Enter the characters you see below')]") && $this->http->ParseForm(null, "//form[contains(@action, 'validateCaptcha')]")) {
                    // parse captcha
                    $captcha = $this->parseCaptcha2();

                    if ($captcha === false) {
                        return false;
                    }
                    $form['field-keywords'] = $captcha;
                    $amzn = $this->http->FindSingleNode("//input[@name = 'amzn']/@value");
                    $amzn_pt = $this->http->FindSingleNode("//input[@name = 'amzn-pt']/@value");
                    $amzn_r = $this->http->FindSingleNode("//input[@name = 'amzn-r']/@value");
                    $this->http->GetURL("http://www.amazon.com/errors/validateCaptcha?amzn={$amzn}&amzn-r={$amzn_r}&amzn-pt={$amzn_pt}&field-keywords={$captcha}");
                }// Enter the characters you see below
//                $this->http->GetURL("https://www.amazon.com/gp/css/gc/payment/view-gc-balance?ie=UTF8&ref_=ya_35");

                $balanceGiftCards = $this->http->FindSingleNode("//div[contains(text(), 'AMAZON GIFT CARD') or contains(text(), 'TARJETA DE REGALO DE AMAZON')]/div[@class = 'pBalanceAmount']", null, true, '/([\d\.\,]+)/ims');
                //# All balances
                $nodes = $this->http->XPath->query("//div[@class = 'pBalances']//div");
                $this->logger->debug("Total nodes found {$nodes->length}");

                if ($nodes->length == 0) {
                    $this->logger->notice(">>>>> Amazon (Gift Cards). Balances not found ! ! !");
                }
                // https://redmine.awardwallet.com/issues/13176#note-20
                if ($this->http->FindPreg("/You must be signed in to see your balance\./")) {
                    /*
                    return false; // todo: refs $19356
                    */
                    throw new CheckRetryNeededException(3, 7);
                }

                for ($i = 0; $i < $nodes->length; $i++) {
                    $node = $nodes->item($i);
                    $balance = $this->http->FindSingleNode("div[@class = 'pBalanceAmount']", $node, true, '/([\d\.\,]+)/ims');
                    $displayName = Html::cleanXMLValue(implode(' ', $this->http->FindNodes("text()", $node)));

                    if (isset($balance, $displayName) && $balance > 0) {
                        $subAccounts[] = [
                            'Code'        => 'AmazonGiftCards' . $i,
                            'DisplayName' => $displayName,
                            'Balance'     => $balance,
                        ];
                    }// if (isset($balance, $displayName))
                    elseif (isset($balance)) {
                        $this->logger->debug("Node # {$i} >>> {$displayName} - {$balance}");
                    }
                }// for($i = 0; $i < $nodes->length; $i++)

                // refs #20332
                if (!isset($balanceGiftCards) && $nodes->length == 0 && in_array($this->http->Response['code'], [500, 404])) {
                    $this->http->GetURL("https://www.amazon.com/gp/css/gc/balance?ref_=ya_d_c_gc");
                    // Balance - Gift Card Balance
                    $this->SetBalance($this->http->FindSingleNode("//span[@id = 'gc-ui-balance-gc-balance-value']"));
                }
                //# if user not connected to an Associates account and all balances == 0
                elseif (!isset($subAccounts) && isset($balanceGiftCards)) {
                    $subAccounts[] = [
                        'Code'        => 'AmazonGiftCards0',
                        'DisplayName' => 'AMAZON GIFT CARD',
                        'Balance'     => $balanceGiftCards,
                    ];
                }

                // Amazon (Affiliate Program)
                unset($balance);
                $this->logger->info("Amazon (Affiliate Program)", ['Header' => 3]);
                /*
                $this->http->GetURL("https://affiliate-program.amazon.com/gp/associates/network/main.html");
                */
                $this->seleniumHomePage('https://affiliate-program.amazon.com/gp/associates/network/main.html');
                $balance = $this->http->FindPreg("/TOTAL EARNINGS \*<[^>]+>\s*<[^>]+>[\$]+([^<]+)/ims");

                if (isset($balance) && $balance > 0) {
                    $subAccounts[] = [
                        'Code'              => 'AmazonAffiliateProgram',
                        'DisplayName'       => 'Affiliate Program',
                        'Balance'           => $balance,
                        'TotalItemsShipped' => $this->http->FindPreg("/Total items shipped<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                        'ReferralRate'      => $this->http->FindPreg("/Referral Rate<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                        'OrderedItems'      => $this->http->FindPreg("/Ordered items<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                        'Clicks'            => $this->http->FindPreg("/Clicks<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                        'Conversion'        => $this->http->FindPreg("/Conversion<[^<]+>\s*<[^<]+>([^<]+)/ims"),
                    ];
                }// if (isset($balance))
                elseif ($message = $this->http->FindPreg('/(?:The e-mail address and password you are using are not connected to an Associates account\.|The e-mail address \/ mobile number and password you are using are not connected to an Associates account\.|The e-mail\/mobile number you are using is not connected to an Associates account\.)/ims')) {
                    $this->logger->notice(">>>>> Amazon (Affiliate Program): " . $message);
                }

                //# No-Rush Rewards  // refs #17532
                $this->logger->info("No-Rush Rewards", ['Header' => 3]);
                $this->http->GetURL("https://www.amazon.com/norushcredits");
                $balanceNoRushRewards = $this->http->FindSingleNode("//div[h1[contains(text(), 'Your No-Rush Reward Balance') or contains(text(), 'Your No-Rush and Amazon Day Reward Balance')]]/following-sibling::div[1]/h1", null, true, "/\:\s*([^<]+)/");

                if (empty($this->Properties['Name'])) {
                    $this->SetProperty("Name", $this->http->FindSingleNode("//span[@class='nav-shortened-name']"));
                }

                $this->SetProperty("NoRushRewards", $balanceNoRushRewards);
                $rushRewards = $this->http->XPath->query("//div[contains(@class, 'a-spacing-none')]/div[contains(@class, 'a-row')]");
                $this->logger->debug("Total {$rushRewards->length} No-Rush Rewards were found");

                foreach ($rushRewards as $rushReward) {
                    $displayName = $this->http->FindSingleNode("a//h6", $rushReward);
                    $rushRewardBalance = $this->http->FindSingleNode("a//h3", $rushReward);
                    $exp =
                        $this->http->FindSingleNode(".//span[contains(text(), 'expires in')]", $rushReward, true, "/ on ([^<.]+)/")
                        ?? $this->http->FindSingleNode(".//span[contains(text(), 'expires today on')]", $rushReward, true, "/ on ([^<.]+)/")
                        ?? $this->http->FindSingleNode("(.//span[contains(text(), 'expires on')])[1]", $rushReward, true, "/expires on([^<.]+)/");

                    $expiringBalance =
                        $this->http->FindSingleNode(".//span[contains(text(), 'expires in')]", $rushReward, true, "/^(.+) expires in \d+/")
                        ?? $this->http->FindSingleNode(".//span[contains(text(), 'expires today on')]", $rushReward, true, "/([^<]+)\s+expires today on/")
                        ?? $this->http->FindSingleNode("(.//span[contains(text(), 'expires on')])[1]", $rushReward, true, "/([^<]+)\s+expires on/")
                    ;

                    if (!$expiringBalance) {
                        $this->sendNotification("need to check No-Rush Rewards exp date // RR");
                    }

                    if (isset($displayName, $rushRewardBalance)) {
                        $subAccounts[] = [
                            'Code'            => 'amazonNoRushRewards' . str_replace(' ', '', $displayName) . strtotime($exp),
                            'DisplayName'     => $displayName,
                            'Balance'         => $rushRewardBalance,
                            'ExpirationDate'  => strtotime($exp, false),
                            'ExpiringBalance' => $expiringBalance,
                        ];
                    }// if (isset($displayName, $rushRewardBalance))
                }// foreach ($rushRewards as $rushReward)

                //# Amazon payments  // refs #5514
                $amazonPayments = $this->AmazonPayments();

                if (!empty($amazonPayments)) {
                    $subAccounts[] = $amazonPayments;
                }
                // Mechanical Turk // refs #12714
                $amazonTurk = $this->amazonTurk();

                if (!empty($amazonTurk) && is_array($amazonTurk)) {
                    $subAccounts[] = $amazonTurk;
                }

                //# Set SubAccounts
                if (!empty($subAccounts)) {
                    if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                        $this->SetBalanceNA();
                    }
                    //# Set Sub Accounts if them > 1
                    if (count($subAccounts) > 1) {
                        $this->SetProperty("CombineSubAccounts", false);
                    }
                    $this->logger->debug("Total subAccounts: " . count($subAccounts));
                    //# Set SubAccounts Properties
                    $this->SetProperty("SubAccounts", $subAccounts);
                }

                break;
        }
    }

    public function amazonTurk()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Amazon Turk", ['Header' => 3]);
        $subAccounts = [];
        $this->http->GetURL("https://worker.mturk.com/dashboard");

        if (!$this->http->ParseForm("signIn")) {
            return $subAccounts;
        }
        $this->http->SetInputValue("email", $this->AccountFields['Login']);
        $this->http->SetInputValue("password", $this->AccountFields['Pass']);
        $this->http->setMaxRedirects(7);
        $this->http->PostForm();
        $this->http->setMaxRedirects(5);

        $this->tryEnterCaptcha();
        $this->tryEnterCaptcha();

        if ($this->http->FindPreg(self::QUESTIONS_REG_EXP)) {
            if (!$this->parseQuestion()) {
                return false;
            }
        }

        if (!in_array($this->http->currentUrl(), [
            'https://worker.mturk.com/check_registration',
            'https://www.amazon.com/ap/cvf/verify',
        ])
        ) {
            $this->exportToEditThisCookies();
        }

        if (!$this->http->FindSingleNode('//a[contains(text(), "Sign Out")]')) {
            $xpath = '//span[
                contains(text(), "To complete the sign-in, please respond to the notification sent to:")
                or (contains(text(), "To complete the sign-in,") and contains(text(), " the notification sent to:"))
                or (contains(text(), "To continue, approve the notification sent to:"))
                or contains(text(), "Authentication required. Please respond to the notification sent to:")
                or contains(text(), "Authentifizierung erforderlich Bitte antworten Sie auf die Benachrichtigung an:")
                or contains(text(), "Authentification requise. Veuillez répondre à la notification envoyée à")
                or contains(text(), "Pour terminer l’inscription, «")
                or contains(text(), "Pour continuer, approuver la notification envoyée à")
                or contains(text(), "Enter the characters as they are given in the challenge.")
                or contains(text(), "Your login can only be used on the Amazon Shopping app, or by logging in to Amazon.com online")
                or contains(., "For your security, approve the notification sent to:")
                or contains(text(), "Para proteger mejor su cuenta, vuelva a introducir su contrase")
            ]
                | //h2[contains(text(), "Create an Amazon Mechanical Turk Account")]
                | //h2[contains(text(), "Password reset required")]
            ';

            /*
            if (
                $this->ErrorCode != ACCOUNT_QUESTION
                && !$this->http->FindSingleNode($xpath)
                && $this->http->currentUrl() != 'https://worker.mturk.com/check_registration'
            ) {
                $this->sendNotification("Amazon Turk // RR");
            }
            */

            return false;
        }

        if (
            $this->http->currentUrl() == 'https://worker.mturk.com/check_registration'
            && $this->http->FindSingleNode('//p[contains(text(), "This account has been suspended by the Amazon Mechanical Turk team.")]')
        ) {
            $this->logger->error("This account has been suspended by the Amazon Mechanical Turk team.");

            return true;
        }
        $this->http->GetURL("https://worker.mturk.com/dashboard?ref=w_hdr_db");
        // Current Earnings
        // Available for Transfer
        $balance = $this->http->FindSingleNode("//div[strong[contains(text(), 'Current Earnings') or contains(text(), 'Available for Transfer')]]/following-sibling::div");

        if (isset($balance)) {
            $subAccounts = [
                'Code'        => 'AmazonMechanicalTurk',
                'DisplayName' => 'Mechanical Turk',
                'Balance'     => $balance,
            ];
        }// if (isset($balance))
        elseif (($message = $this->http->FindSingleNode("//div[@id = 'message_error']"))
                || ($message = $this->http->FindSingleNode("//div[contains(text(), 'User Registration')]"))) {
            $this->logger->notice(">>>>> Mechanical Turk: " . $message);
        }

        return $subAccounts;
    }

    public function AmazonPayments()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Amazon Payments", ['Header' => 3]);
        $subAccounts = [];
        $this->http->GetURL("https://payments.amazon.com/sdui/sdui/overview");

        if ($this->http->ParseForm("ap_signin_form")) {
            $this->http->SetInputValue("email", $this->AccountFields['Login']);
            $this->http->SetInputValue("password", $this->AccountFields['Pass']);
            $this->http->SetInputValue("x", '28');
            $this->http->SetInputValue("y", '14');
            $this->http->PostForm();
        }

        $balance = $this->http->FindSingleNode("//div[@id = 'balanceValue']");

        if (isset($balance)) {
            $subAccounts = [
                'Code'        => 'AmazonAmazonPayments',
                'DisplayName' => 'Amazon Payments',
                'Balance'     => $balance,
                //# Auto-Deposit
                'AutoDeposit' => $this->http->FindSingleNode("//div[@id = 'autoDepValue']/span/text()[1]"),
            ];
        }// if (isset($balance))
        elseif (($message = $this->http->FindSingleNode("//div[@id = 'message_error']"))
                || ($message = $this->http->FindSingleNode("//p[contains(text(), 'Please provide current and accurate information to obtain an Amazon Payments account')]"))
                || ($message = $this->http->FindPreg("/(An error occurred when we tried to process your request)/ims"))) {
            $this->logger->error(">>>>> Amazon Payments: " . $message);

            return false;
        }

        return $subAccounts;
    }

    protected function checkRegionSelection($region)
    {
        if (!in_array($region, array_flip($this->regionOptions))) {
            $region = 'USA';
        }

        return $region;
    }

    protected function checkAutologinSelection($autologin)
    {
        if (!in_array($autologin, array_flip($this->autologinOptions))) {
            $autologin = 'amazonaff';
        }

        return $autologin;
    }

    protected function getRightLoginURL()
    {
        $this->logger->notice(__METHOD__);
        // old header
        $link = $this->http->FindSingleNode("//a[@id = 'nav-your-account']/@href");
        // new header
        if (!isset($link)) {
            $link = $this->http->FindSingleNode("//div[@id = 'nav-auth']/a/@href");
        }

        if (!isset($link)) {
            $link = $this->http->FindSingleNode("//a[@id = 'nav-link-yourAccount']/@href");
        }

        if (!isset($link)) {
            $link = $this->http->FindPreg("/<div id='nav-flyout-ya-signin' class='nav-flyout-content.+?'><a href='([^\']+signin[^\']+)/");
        }

        if (!isset($link)) {
            $link = $this->http->FindSingleNode('//div[@class = "nav-bb-right"]/a[text() = "Your Account"]/@href');

            if (isset($link)) {
                $this->http->NormalizeURL($link);
                $this->http->GetURL($link);
                $link = $this->http->FindPreg("/<div id='nav-flyout-ya-signin' class='nav-flyout-content.+?'><a href='([^\']+)/");
            }
        }

        if (isset($link)) {
            $this->http->NormalizeURL($link);
            $this->http->GetURL($link);

            return true;
        }

        return false;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $imageData = $this->http->FindSingleNode("//div[contains(@id, 'captcha')]/img/@src", null, true, "/jpeg;base64\,\s*([^<]+)/ims");
        $this->logger->debug("jpeg;base64: {$imageData}");

        if (!empty($imageData)) {
            $this->logger->debug("decode image data and save image in file");
            // decode image data and save image in file
            $imageData = base64_decode($imageData);
            $image = imagecreatefromstring($imageData);
            $file = "/tmp/captcha-" . getmypid() . "-" . microtime(true) . ".jpeg";
            imagejpeg($image, $file);
        } elseif ($link = $this->http->FindSingleNode("//div[contains(@id, 'captcha')]/img/@src | //div[contains(@class, 'cvf-captcha-img')]/img/@src")) {
            $this->logger->debug("Download Image by URL: '{$link}'");
            $http2 = clone $this->http;
            $file = $http2->DownloadFile($link, 'gif');
            // refs #19094
            $file = GifFpsChanger::changeFps($file, 3);
            $this->logger->debug("compressed file: " . $file);
        }
        // captcha before login form (USA)
        elseif ($link = $this->http->FindSingleNode("//form[@action = '/errors/validateCaptcha']//div[@class = 'a-row a-text-center']/img/@src")) {
            $this->logger->debug("Download Image by URL");
            $http2 = clone $this->http;
            $file = $http2->DownloadFile($link, "jpg");
        }

        if (!isset($file)) {
            return false;
        }
        $this->logger->debug("file: " . $file);
        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 100;
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        return $captcha;
    }

    protected function parseCaptcha2()
    {
        $this->logger->notice(__METHOD__);
        $imageURL = $this->http->FindSingleNode("//img[contains(@src, 'captcha')]/@src");

        if (!isset($imageURL)) {
            $this->logger->error("image url was not found");

            return false;
        }
        $file = $this->http->DownloadFile($imageURL, "jpg");
        $this->logger->debug("exception: " . $file);
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $captcha = $this->recognizeCaptcha($recognizer, $file);
        unlink($file);

        if ($captcha) {
            $captcha = str_replace(' ', '', $captcha);
        }

        return $captcha;
    }

    protected function exportToEditThisCookies()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("exportToEditThisCookies", ['Header' => 3]);
        $cookiesArr = [];
        $cookiesArrGeneral = [];
        $domains = [
            ".amazon.com",
            "www.amazon.com",
        ];
        $cookies = [];

        foreach ($domains as $domain) {
            $cookies = array_merge($cookies, $this->http->GetCookies($domain), $this->http->GetCookies($domain, "/", true));
        }
        $i = 1;

        foreach ($cookies as $cookie => $val) {
            $c = [
                "domain"   => ".amazon.com",
                //                "expirationDate" => 1494400127,
                "hostOnly" => false,
                "httpOnly" => false,
                "name"     => $cookie,
                "path"     => "/",
                "secure"   => false,
                "session"  => false,
                "storeId"  => "0",
                "value"    => $val,
            ];
            $cookiesArr[] = $c;
            $cg = "document.cookie=\"{$cookie}=" . str_replace('"', '\"', $val) . "; path=/; domain=.amazon.com\";";
            $cookiesArrGeneral[] = $cg;
            $i++;
        }// foreach ($cookies as $cookie)
        /*
        $this->logger->debug("==============================");
        $this->logger->debug(str_replace("\/", "/", json_encode($cookiesArr)));
        $this->logger->debug("==============================");
        $this->logger->debug("===============2==============");
        $this->logger->debug(var_export( implode(' ', $cookiesArrGeneral) , true));
        $this->logger->debug("==============================");
        */

        $domains = [
            "worker.mturk.com",
            ".mturk.com",
        ];
        $cookies = [];

        foreach ($domains as $domain) {
            $cookies = array_merge($cookies, $this->http->GetCookies($domain), $this->http->GetCookies($domain, "/", true));
        }
        $i = 1;

        foreach ($cookies as $cookie => $val) {
            $c = [
                "domain"   => ".mturk.com",
                //                "expirationDate" => 1494400127,
                "hostOnly" => false,
                "httpOnly" => false,
                "name"     => $cookie,
                "path"     => "/",
                "secure"   => false,
                "session"  => false,
                "storeId"  => "0",
                "value"    => $val,
            ];
            $cookiesArr[] = $c;
            $cg = "document.cookie=\"{$cookie}=" . str_replace('"', '\"', $val) . "; path=/; domain=.mturk.com\";";
            $cookiesArrGeneral[] = $cg;
            $i++;
        }// foreach ($cookies as $cookie)
        /*
        $this->logger->debug("==============================");
        $this->logger->debug(str_replace("\/", "/", json_encode($cookiesArr)));
        $this->logger->debug("==============================");
        $this->logger->debug("===============2==============");
        */
        $this->logger->debug(var_export(implode(' ', $cookiesArrGeneral), true));
        /*
        $this->logger->debug("==============================");
        */
    }

    private function seleniumHomePage($url, $redirectToProfile = false)
    {
        $this->logger->notice(__METHOD__);
        $allCookies = array_merge($this->http->GetCookies(".amazon.com"), $this->http->GetCookies(".amazon.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.amazon.com"), $this->http->GetCookies("www.amazon.com", "/", true));

        $checker = clone $this;
        $this->http->brotherBrowser($checker->http);

        try {
            $this->logger->notice("Running Selenium...");
            $checker->UseSelenium();
            $checker->useChromium();
            $checker->useCache();
            $checker->http->start();
            $checker->Start();
            $checker->http->saveScreenshots = true;

            $checker->http->GetURL('https://www.amazon.com/signins');

            foreach ($allCookies as $key => $value) {
                $checker->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".amazon.com"]);
            }

            $checker->http->GetURL($url);

            $checker->waitForElement(\WebDriverBy::xpath("//p[@id = 'home-profile-0'] | //div[contains(@class, 'a-alert-content')]"), 7);
            $this->savePageToLogs($checker);

            if ($redirectToProfile === true) {
                $checker->http->GetURL('https://www.amazon.com/manage-your-profiles/home?ref_=ya_manage_your_profiles?ref_=ya_your_profile_rich-card');
                $checker->waitForElement(\WebDriverBy::xpath("//p[@id = 'home-profile-0'] | //div[contains(@class, 'a-alert-content')]"), 7);
                $this->savePageToLogs($checker);
            }
        } finally {
            // close Selenium browser
            $checker->http->cleanup(); //todo
        }
    }

    private function captchaBeforeLoginForm()
    {
        $this->logger->notice(__METHOD__);
        // captcha before login form
        if (!$this->http->ParseForm("signIn") && $this->http->ParseForm(null, "//form[@action = '/errors/validateCaptcha']")) {
            $this->logger->notice("captcha before login form (USA)");
            // parse captcha

            if ($link = $this->http->FindSingleNode("//form[@action = '/errors/validateCaptcha']//div[@class = 'a-row a-text-center']/img/@src")) {
                $curlBrowser = new HttpBrowser("none", new CurlDriver());
                $this->http->brotherBrowser($curlBrowser);
                $this->logger->debug("Download Image by URL");
                $file = $curlBrowser->DownloadFile($link, "jpg");

                if (!isset($file)) {
                    return false;
                }

                $this->logger->debug("file: " . $file);
                $recognizer = $this->getCaptchaRecognizer();
                $recognizer->RecognizeTimeout = 100;
                $captcha = $this->recognizeCaptcha($recognizer, $file);
                unlink($file);
            } else {
                $captcha = $this->parseCaptcha();
            }

            if ($captcha === false) {
                return false;
            }
            $amzn_pt = '';

            if (isset($this->http->Form['amzn-pt'])) {
                $amzn_pt = "&amzn-pt={$this->http->Form['amzn-pt']}";
            }
            $this->http->GetURL("https://www.amazon.com/errors/validateCaptcha?amzn={$this->http->Form['amzn']}&amzn-r={$this->http->Form['amzn-r']}{$amzn_pt}&field-keywords={$captcha}");
            // retries
            if ($this->http->Response['code'] == 404 && $this->LoadForm < 2) {
                $this->logger->notice("Retry login - " . $this->LoadForm);
                sleep(5);
                $this->LoadForm++;

                return $this->LoadLoginForm();
            }
        }// captcha before login form

        return false;
    }

    private function captchaMessage()
    {
        $this->logger->notice(__METHOD__);
        // There was a problem // refs #12964
        if ($this->http->FindNodes("//h4[contains(text(), 'There was a problem')]/following-sibling::div[contains(., 'Enter the characters as they are shown in the image.') or contains(., 'Enter the characters as they are given in the challenge.')]")
            || $this->http->FindSingleNode("//span[contains(text(), 'To better protect your account, please re-enter your password and then enter the characters as they are shown in the image below.')]")
            || $this->http->FindSingleNode('//span[contains(text(), "Pour mieux protéger votre compte, veuillez saisir à nouveau votre mot de passe puis saisissez les caractères affichés dans l\'image ci-dessous.")]')
            || $this->http->FindPreg('/Pour mieux protéger votre compte, veuillez saisir à nouveau votre mot de passe puis saisissez les caractères affichés dans l\'image ci-dessous\./')
        ) {
            throw new CheckException(self::CAPTCHA_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
    }
}
