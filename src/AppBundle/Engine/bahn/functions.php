<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Bus;
use AwardWallet\Schema\Parser\Common\Train;
use AwardWallet\Schema\Parser\Email\Email;

// refs #1987, bahn

class TAccountCheckerBahn extends TAccountChecker
{
    use PriceTools;
    use StringTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    private const XPATH_PAGE_HISTORY = "//table[contains(@class, 'bcpunktedetails')]//tr[not(@id) and not(contains(@class, 'rowlines'))]";

    protected $collectedHistory = true;
    protected $endHistory = false;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $history = [];

    private $seleniumAuth = false;

    public function UpdateGetRedirectParams(&$arg)
    {
        $redirectURL = 'https://fahrkarten.bahn.de/privatkunde/start/start.post?scope=login&lang=en';

        if ($this->AccountFields['Login2'] == 'Business') {
            $redirectURL = "https://fahrkarten.bahn.de/grosskunde/start/kmu_start.post?lang=de&scope=login#stay";
        }

        $arg["RedirectURL"] = $redirectURL;
    }

    public function TuneFormFields(&$arFields, $values = null)
    {
        parent::TuneFormFields($arFields);
        $arFields["Login2"]["Options"] = [
            ""         => "Please select your account type",
            "Personal" => "Personal account",
            "Business" => "Business account",
        ];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setHttp2(true);
        $this->http->SetProxy($this->proxyReCaptchaVultr());
    }

    public function IsLoggedIn()
    {
        $url = "https://fahrkarten.bahn.de/privatkunde/meinebahn/meine_bahn_portal.go?lang=en&country=DEU";

        if ($this->AccountFields['Login2'] == 'Business') {
            $url = "https://fahrkarten.bahn.de/grosskunde/start/firmenkunden_portal.go?lang=en&country=DEU";
        }

        $this->http->RetryCount = 0;
        $this->http->GetURL($url, [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        if (
            !strstr($this->http->currentUrl(), 'notloggedin')
            && !strstr($this->http->currentUrl(), 'out_of_system_error_pk')
            && $this->http->FindSingleNode("(//a[contains(@href, 'logout')]/@href)[1]")
        ) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        // #14731
        if ($this->AccountFields['Login2'] == 'Business') {
            return $this->LoadLoginFormBusiness();
        }

        $this->http->RetryCount = 0;
        $this->http->setMaxRedirects(10);
        $this->http->GetURL("https://fahrkarten.bahn.de/privatkunde/start/start.post?scope=login&lang=en");
        $this->http->RetryCount = 2;

        // retries
        if (
            ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
            && (
                strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
                || strstr($this->http->Error, 'Network error 7 - Failed to connect to ')
            )
        ) {
            $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->RetryCount = 0;
            $this->http->GetURL("https://www.bahn.de/p/view/meinebahn/login.shtml");
            $this->http->RetryCount = 2;

            if (
                ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG
                && (
                    strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
                    || strstr($this->http->Error, 'Network error 7 - Failed to connect to ')
                )
            ) {
                throw new CheckRetryNeededException(2, 3);
            }
        }

        if ($this->http->FindSingleNode('//label[contains(text(), "chte mich einloggen")]')
            && $this->http->ParseForm('formular')
        ) {
            $this->logger->notice("go to Login Form");

            $this->http->SetInputValue('name.radio.group.login', "1");
            $this->http->SetInputValue('button.weiter_p_js', "true");
            $this->http->SetInputValue('button.weiter', "");
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm('kc-form-login')) {
            return $this->checkErrors();
        }

        if ($this->seleniumAuth === true) {
            $this->seleniumAuth("https://fahrkarten.bahn.de/privatkunde/start/start.post?scope=login&lang=en");

            return true;
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('softLoginCheckbox', "true");
        $this->http->SetInputValue('softLoginVisible', "true");
//        $this->http->SetInputValue('button.weiter_p_js', "true");

        $captcha = $this->parseHCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('h-captcha-response', $captcha);
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode('
            //h1[
                contains(text(), "503 Service Temporarily Unavailable")
                or contains(text(), "502 Bad Gateway")
                or contains(text(), "Internal Server Error - Read")
            ]
            | //h2[contains(text(), "Failure of Web Server bridge:")]
            | //p[contains(., "Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten.")]
            ')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(., "at the moment  our booking engine is used by too many users at the same time.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function LoadLoginFormBusiness()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL("https://fahrkarten.bahn.de/grosskunde/start/kmu_start.post?lang=en&scope=login");

        if ($this->http->FindSingleNode('//label[contains(text(), "chte mich in das Firmenkundenportal")]')
            && $this->http->ParseForm('formular')
        ) {
            $this->logger->notice("go to Login Form");

            $this->http->SetInputValue('button.weiter_p_js', "true");
            $this->http->SetInputValue('button.weiter', "");
            $this->http->PostForm();
        }

        if (!$this->http->ParseForm('kc-form-login')) {
            return $this->checkErrors();
        }

        if ($this->seleniumAuth === true) {
            $this->seleniumAuth("https://fahrkarten.bahn.de/grosskunde/start/kmu_start.post?lang=en&scope=login");

            return true;
        }

        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);

        $captcha = $this->parseHCaptcha();

        if ($captcha === false) {
            return false;
        }

        $this->http->SetInputValue('h-captcha-response', $captcha);
        $this->http->SetInputValue('g-recaptcha-response', $captcha);

        return true;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode('//p[
            contains(text(), "Ein sechsstelliger Bestätigungscode wurde gerade per SMS")
            or contains(text(), "We have sent a text message containing a six-digit confirmation code")
            or contains(text(), "Bitte geben Sie den über Ihre App generierten Code ein, um sich anzumelden.")
            or contains(text(), "Use the code generated by your app to register")
            or contains(text(), "Der er netop blevet sendt en sekscifret bekræftelseskode pr. SMS til")
            or contains(text(), "Per effettuare il login, inserisci il codice generato tramite la tua app.")
        ]');
        $action = $this->http->FindSingleNode("//form[@id = 'reset-password-form' or @id = 'kc-otp-login-form']/@action");

        if (!$question || !$action) {
            return false;
        }

        $this->http->NormalizeURL($action);
        $this->State['action'] = $action;
        $this->Question = $question;
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = 'Question';

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->FormURL = $this->State['action'];
        $name = "smsCode";

        if (strstr($this->Question, 'App generierten Code ein')) {
            $name = "otp";
        }

        $this->http->SetInputValue($name, $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->PostForm();

        if ($error = $this->http->FindSingleNode('//div[contains(@class, "message-error")]')) {
            $this->logger->error("[Error]: {$error}");

            if (stristr($error, 'Geben Sie den richtigen Sicherheitscode ein')) {
                $this->AskQuestion($this->Question, $error, 'Question');
            }

            return false;
        }

        return true;
    }

    public function LoginBusiness()
    {
        $this->logger->notice(__METHOD__);

        if ($this->parseQuestion()) {
            return false;
        }

        // Wrong user name or password.
        if ($message = $this->http->FindSingleNode("//span[
                contains(text(), 'Wrong user name or password.')
                or contains(text(), 'The user name or password is incorrect.')
                or contains(text(), 'Der Benutzername oder das Passwort ist nicht ')
                or contains(text(), 'Der Benutzername oder das Passwort ist ungültig.')
                or contains(text(), 'You are registered under this user name and password at www.bahn.de. Please use a different user name and password here.')
            ]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("
                //div[contains(@class, 'message-error')]/span
                | //div[contains(@class, '-input__error-message') and normalize-space() != '']
        ")) {
            $this->logger->error("[Error]: {$message}");

            if ($message == 'Invalid username or password.') {
                $this->captchaReporting($this->recognizer);

                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (strstr($message, 'You need to change your password to continue.')) {
                $this->captchaReporting($this->recognizer);
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        // Account temporarily blocked
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Account temporarily blocked')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if (!$this->skipProfileUpdate()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }
        /**
         * Dear customer,.
         *
         * at the moment our booking engine is used by too many users at the same time.
         *
         * Please try again later.
         *
         * Thank you for your kind understanding.
         *
         * Yours www.bahn.de team
         */
        if ($message = $this->http->FindSingleNode("//p[
                contains(., 'at the moment our booking engine is used by too many users at the same time.')
            ]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        $this->checkProviderErrors();

        if ($message = $this->http->FindSingleNode("//p[
                contains(normalize-space(.), 'Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten. Bitte versuchen Sie es erneut. ')
            ]")
        ) {
            $this->captchaReporting($this->recognizer, false);
            $this->callRetriesOnCaptcha();
        }

        return $this->checkErrors();
    }

    public function Login()
    {
        if ($this->seleniumAuth === false) {
            $this->http->RetryCount = 0;

            if (!$this->http->PostForm()) {
                if ($this->http->FindSingleNode('//h1[contains(text(), "503 Service Unavailable")]')) {
                    $this->captchaReporting($this->recognizer, false);

                    $this->callRetriesOnCaptcha(self::CAPTCHA_ERROR_MSG);
                }

                return $this->checkErrors();
            }

            $this->http->RetryCount = 2;
        }

        if (
            $this->http->FindSingleNode('//p[
                contains(text(), "Activate 2-factor authentication for your account")
                or contains(text(), "Aktivieren Sie eine 2-Faktor-Authentifizierung für Ihr Konto")
            ]')
            && $this->http->ParseForm("step1")
        ) {
            $this->http->SetInputValue('credentialType', "skip");
            $this->http->PostForm();
        }

        if ($this->AccountFields['Login2'] == 'Business') {
            return $this->LoginBusiness();
        }

        if (preg_match("/<meta[^>]*http\-equiv=\"?refresh\"?[^>]*>/ims", $this->http->Response["body"], $matches)) {
            if (preg_match("/<meta[^>]*content=\"?[^\"]*url=([^\"]+)\"?[^>]*>/ims", $matches[0], $matches)) {
                $this->http->GetURL($matches[1]);
            }
        }

        if ($this->parseQuestion()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }

        if ($message = $this->http->FindPreg("/Sehr geehrte Kundin, sehr geehrter Kunde,<br \/><br \/>im Moment greifen zu viele Nutzer gleichzeitig auf unser Buchungssystem zu\./ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Sehr geehrte Kundin, sehr geehrter Kunde!<br \/><br \/>Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetretenё./ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/Sehr geehrte Kundin, sehr geehrter Kunde! <br\/><br\/>Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten\. Bitte versuchen Sie es erneut\./ims")) {
            $this->callRetriesOnCaptcha();
        }

        if (!$this->skipProfileUpdate()) {
            $this->captchaReporting($this->recognizer);

            return false;
        }

        if ($this->loginSuccessful()) {
            $this->captchaReporting($this->recognizer);

            return true;
        }

        if ($message = $this->http->FindSingleNode("
                //div[contains(@class, 'message-error')]/span
                | //div[contains(@class, '-input__error-message') and normalize-space() != '']
        ")) {
            $this->logger->error("[Error]: {$message}");
            $this->captchaReporting($this->recognizer);

            if (
                strstr($message, 'Invalid username or password.')
                || strstr($message, 'Der Benutzername oder das Passwort ist ungültig.')
                || strstr($message, 'You need to change your password to activate your account.')
                || strstr($message, 'Sie müssen Ihr Passwort ändern, um fortfahren zu können.')
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Account is disabled, contact your administrator.') {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            if (strstr($message, 'You need to change your password to continue.')) {
                $this->throwProfileUpdateMessageException();
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($error = $this->http->FindSingleNode("//*[@class='errormsg']")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The username or password is not valid."/*utf8_encode($error)*/ , ACCOUNT_INVALID_PASSWORD);
        }
        // Why does it needed?
//        ## Der Benutzername oder das Passwort ist nicht
//        if ($error = $this->http->FindSingleNode('//h1[contains(text(),"gesperrt")]'))
//            throw new CheckException( utf8_encode($error), ACCOUNT_PREVENT_LOCKOUT);
        //# Der Benutzername oder das Passwort ist nicht
        if ($this->http->FindPreg("/Der Benutzername oder das Passwort ist nicht/ims")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("Your passcode could not be verified", ACCOUNT_INVALID_PASSWORD);
        }
        //# Account vorubergehend gesperrt
        if (
            $this->http->FindSingleNode("//div[@id='content']//h1[contains(text(),'Account vorubergehend gesperrt')]")
            || $this->http->FindSingleNode("//title[contains(text(),'Account gesperrt')]")
        ) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException("The page has been temporarily blocked", ACCOUNT_LOCKOUT);
        }
        // Ein schwerer Fehler ist aufgetreten
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'Ein schwerer Fehler ist aufgetreten')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sehr geehrte Kundin, sehr geehrter Kunde,
        // im Moment greifen zu viele Nutzer gleichzeitig auf unser Buchungssystem zu.
        if ($this->http->FindPreg('#/error/out_of_system_error_pk.jsp#') && $this->http->FindSingleNode("//p//text()[contains(., 'im Moment greifen zu viele Nutzer gleichzeitig auf unser Buchungssystem zu.')]")) {
            $this->captchaReporting($this->recognizer);

            throw new CheckRetryNeededException(2, 10, 'Im Moment greifen zu viele Nutzer gleichzeitig auf unser Buchungssystem zu.');
        }

        $this->checkProviderErrors();

        return $this->checkErrors();
    }

    public function checkProviderErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//p[
                contains(text(), 'An email with instructions to verify your email address has been sent to you.')
                or contains(text(), 'Eine E-Mail mit Anweisungen zur Überprüfung Ihrer E-Mail-Adresse wurde an Sie gesendet.')
            ]")
        ) {
            $this->captchaReporting($this->recognizer);

            $this->throwProfileUpdateMessageException();
        }
    }

    public function skipProfileUpdate()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindSingleNode("//select[@name = 'loginquestion']/option[1]")
            && $this->http->ParseForm("formular")
            && !$this->http->FindPreg("/Die Verwendung Ihres Nutzernamens ist zuk/")
            && $this->http->FindSingleNode("//input[@value = 'Answer later']/@value")
        ) {
            $this->http->SetInputValue("button.zurueck_p", "Answer later");
            $this->http->PostForm();
        } elseif (
            $this->http->FindNodes('//h3[
                contains(text(), "Please answer a security question")
                or contains(text(), "Hinweis zu Ihren Anmeldedaten")
            ]
            ')
            && $this->http->FindSingleNode('//p[
                    contains(., "In order to better protect your data, we have revised the procedure for recovering forgotten passwords")
                    or contains(., "hen, haben wir das Verfahren zur Wiederherstellung vergessener Passw")
            ]')
            || $this->http->FindPreg("/<h1 id=\"kc-page-title\"[^>]+>\s*Update Account Information\s*<\/h1>/")
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return true;
    }

    public function ParseBusiness()
    {
        $this->logger->notice(__METHOD__);

        if (!$profilePage = $this->http->FindSingleNode("//a[span[contains(text(), 'BahnCard Business')]]/@href")) {
            // AccountID: 2441513
            if (strstr($this->http->currentUrl(), 'https://fahrkarten.bahn.de/grosskunde/login/idmredirect.post?lang=de&country=DEU&scope=RETURN&state=')) {
                throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, aufgrund eines unerwarteten Systemfehlers wurden Sie automatisch vom Buchungssystem der Bahn abgemeldet. Wir bitten um Entschuldigung.", ACCOUNT_PROVIDER_ERROR);
            }

            return;
        }
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//p[@class = 'nobottommargin']/span")));
        // Customer no.
        $this->SetProperty("AccountNumber", $this->http->FindPreg("/(?:Customer\s*no|Kundennr).:\s*([^<]+)/ims"));

        $this->http->GetURL($profilePage);

        if ($pointsPage = $this->http->FindSingleNode("//a[contains(@href, 'bahncardpunkte') and contains(@title, 'Punkte') or contains(@title, 'Go to points overview')]/@href")) {
            $this->http->GetURL(str_replace('lang=de&country=DEU', 'lang=en&country=GBR', $pointsPage));
        }
        // Balance - Your current bonus points level
        $this->SetBalance($this->http->FindSingleNode("//div[label[contains(text(), 'Your current bonus points level')]]/following-sibling::div[1]"));
        // Your current status points level
        $statusPoints = $this->http->FindSingleNode("//div[label[contains(text(), 'Your current status points level')]]/following-sibling::div[1]");
        // refs #14072
        $this->getExpDate($statusPoints);

        // AccountID: 3687895
        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $this->SetWarning($this->http->FindPreg("/The system could not find a points level for your particulars. Please check your BahnCard number and date of birth in your registration data for collecting points online and change if necessary./"));
            // The BahnCard Services are unfortunately not available at the moment. Please try again later.
            $this->SetWarning($this->http->FindSingleNode('//p[contains(., "The BahnCard Services are unfortunately not available at the moment. Please try again later.")]'));
            /**
             * Your card number and date of birth are required in order to display your bonus points / status points.
             * Have you entered these details correctly?
             */
            if (
                $this->http->FindSingleNode("//h2[@class = 'errormsg' and contains(., 'Your card number and date of birth are required in order to display your bonus points / status points. Have you entered these details correctly?')]")
                || $this->http->FindSingleNode("//p[contains(text(), 'Register here for the BahnCard services and enjoy the many advantages such as viewing and updating your personal BahnCard data, retrieving your BahnBonus points level, ordering rewards, ordering a replacement BahnCard etc.')]")
            ) {
                $this->throwProfileUpdateMessageException();
            }

            // AccountID: 3958433, 4096011
            if (
                $this->http->FindSingleNode("//img[
                    contains(@alt, 'LogoSiemens AG')
                    or contains(@alt, 'Logod-fine GmbH')
                    or contains(@alt, 'LogoAccenture GmbH')
                    or contains(@alt, 'LogoIBM Deutschland GmbH')
                ]/@alt")
                && $this->http->FindPreg('/(?:You have been automatically logged out of the Deutsche Bahn reservation system due to an unforeseen system error. Please log on again.|aufgrund eines unerwarteten Systemfehlers wurden Sie automatisch vom Buchungssystem der Bahn abgemeldet. Bitte loggen Sie sich wieder ein\.)/')
            ) {
                $this->SetBalanceNA();
            }
        }
    }

    public function Parse()
    {
        if ($this->AccountFields['Login2'] == 'Business') {
            $this->ParseBusiness();

            return;
        }

        // Skip updating
        $usernameUpdate = $this->http->FindPreg("/ndern Sie Ihren Benutzernamen/ims");
        $addressUpdate = $this->http->FindPreg("/Sie Ihre Adressdaten auf Richtigkeit und teilen uns eventuelle/ims");

        if (($usernameUpdate || $addressUpdate)
            && ($meineBahnLink = $this->http->FindSingleNode("//li[@id = 'mn-meinebahn']/a/@href"))
        ) {
            $this->logger->notice("Skip updating personal data");
            $this->http->GetURL($meineBahnLink);
        }

        // here after Login()
        // Name
        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("(//div[@id = 'content-opener-mpersdaten']/p/span)[1]")));
        // Kundennr
        $this->SetProperty("AccountNumber", $this->http->FindPreg("/Kundennr\.\s*([^<]+)/ims") ?? $this->http->FindSingleNode('//span[@id = "kundennummer"]'));

        // If there is problem on the site then looking for another way
        $anotherLink = $this->http->FindSingleNode("//a[contains(text(), 'Alle BahnCard-Services')]/@href");
        // Popup with properties's form
        $link = $this->http->FindPreg("/WECAJAX\.Portlet\(\"([^\"]+)/ims");

        if ($link || $this->http->FindPreg("/mbahnbonuspunkte/ims")) {
            if ($link) {
                $this->http->GetURL(str_replace('\/', '/', $link));
            }

            // Balance - Ihr aktueller Prämienpunktestand
            $this->SetBalance(
                $this->http->FindSingleNode("//p[contains(text(), 'mienpunkte')]/following-sibling::div[1]/p")
                ?? $this->http->FindSingleNode('//p[@class = "bc-punkte"]')
            );
            // Ihr aktueller Statuspunktestand
            $statusPoints =
                $this->http->FindSingleNode("//p[contains(text(), 'Statuspunkte')]/following-sibling::div[1]/p")
                ?? $this->http->FindSingleNode('//div[@class = "bc-punkte statuspunkte"]')
            ;
            // refs #14072
            $this->getExpDate($statusPoints);

            // if the balance is not found
            if ($this->http->FindPreg("/<p>(Ab sofort finden Sie hier Ihre .+ sowie alle Services und Informationen zu Ihrer Karte\.)<\/p>/ims")) {
                $this->SetBalanceNA();
            }
            // No Bahn Card is linked to your account.
            elseif ($this->http->FindPreg("/<p>(Als registrierter BahnCard-Nutzer haben Sie die [^\,]+, auf Meine Bahn .+ weitere Serviceangebote zu nutzen\.)<\/p>/ims")) {
                $this->SetWarning("No Bahn Card is linked to your account.");
            }
            // This service is currently not available.
            elseif ($this->http->FindPreg("/<p>(Dieser Service ist zurzeit nicht ver[^\.]+\.)<\/p>/ims")) {
                throw new CheckException("This service is currently not available.", ACCOUNT_PROVIDER_ERROR);
            } elseif ($this->http->ParseForm("formularMeineBahnBonusPunkte")) {
                $this->http->Form['mbahnbonuspunkte.button.bahnbonus_p'] = 'Punkteübersicht';
                $this->http->PostForm();
            }// elseif ($this->http->ParseForm("formularMeineBahnBonusPunkte")) {
            elseif ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                if (isset($anotherLink)) {
                    $this->http->GetURL($anotherLink);

                    if ($anotherLink = $this->http->FindSingleNode("//a[contains(text(), 'Meine Punkteübersicht')]/@href")) {
                        $this->http->GetURL($anotherLink);
                    }
                }// if ($link = $this->http->FindSingleNode("//a[contains(text(), 'Alle BahnCard-Services')]/@href"))
                // Provider error
                elseif ($this->http->FindPreg("/Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten.<br[^>]+>Der Fehler wurde protokolliert\./ims")) {
                    throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
                }
            }// else
        }// Popup with properties's form

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            $t = $this->http->XPath->query('//div[@class="bgcolorformbody bottommargin bcpunkteinfo clearfix"]/div[1]');
            $this->logger->debug("Total {$t->length} nodes were found");

            if ($t->length == 1) {
                $t = $t->item(0);
                // Balance - Ihr aktueller Prämienpunktestand
                $this->SetBalance($this->http->FindSingleNode('div[2]/div[2]', $t));
                // Ihr aktueller Statuspunktestand
                $statusPoints = $this->http->FindSingleNode('div[3]/div[2]', $t);
                // refs #14072
                $this->getExpDate($statusPoints);
            } else {
                if ($message = $this->http->FindPreg("/die BahnCard-Services sind derzeit leider nicht verf.?gbar/ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, die BahnCard-Services sind derzeit leider nicht verfgbar. Bitte versuchen Sie es spter noch einmal.", ACCOUNT_PROVIDER_ERROR);
                }
                // Sie haben sich vom bahn.bonus-Programm der Deutschen Bahn AG abgemeldet.
                if ($message = $this->http->FindPreg("/Sie haben sich vom bahn.bonus-Programm der Deutschen Bahn AG abgemeldet/ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, Sehr geehrte Kundin, sehr geehrter Kunde, Sie haben sich vom bahn.bonus-Programm der Deutschen Bahn AG abgemeldet.", ACCOUNT_PROVIDER_ERROR);
                }
                // die BahnCard-Services sind derzeit leider nicht verfgbar. Bitte versuchen Sie es spter noch einmal
                if ($message = $this->http->FindPreg("/die BahnCard-Services sind derzeit leider nicht verfgbar\.\s*Bitte versuchen Sie es spter noch einmal\./ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, Sehr geehrte Kundin, sehr geehrter Kunde, die BahnCard-Services sind derzeit leider nicht verfgbar. Bitte versuchen Sie es spter noch einmal.", ACCOUNT_PROVIDER_ERROR);
                }
                /*
                 * Sehr geehrte Kundin, sehr geehrter Kunde, leider sind Sie für das bahn.bonus-Programm der Deutschen Bahn AG gesperrt.
                 * Bei Rückfragen wenden Sie sich bitte an den bahn.bonus-Service.
                 * Die entsprechende Rufnummer finden Sie unter www.bahn.de/kontakt.
                 */
                if ($message = $this->http->FindPreg("/Sehr geehrte Kundin, sehr geehrter Kunde, leider sind Sie f.+r das bahn.bonus-Programm der Deutschen Bahn AG gesperrt\. Bei R.+ckfragen wenden Sie sich bitte an den bahn.bonus-Service\./ims")) {
                    throw new CheckException("Sehr geehrte Kundin, sehr geehrter Kunde, leider sind Sie für das bahn.bonus-Programm der Deutschen Bahn AG gesperrt. Bei Rückfragen wenden Sie sich bitte an den bahn.bonus-Service. Die entsprechende Rufnummer finden Sie unter www.bahn.de/kontakt.", ACCOUNT_PROVIDER_ERROR);
                }

                if ($message = $this->http->FindSingleNode("//p[contains(text(), 'This service is not available at the moment.')]")) {
                    $this->SetWarning($message);
                }

                // Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten.
                /*
                 * Sehr geehrte Kundin, sehr geehrter Kunde,
                 *
                 * Sie haben sich vom BahnBonus Programm der Deutschen Bahn AG abgemeldet.
                 */
                if ($this->http->FindPreg("/(?:Bei der Verarbeitung Ihrer Anforderung ist ein unerwarteter Fehler aufgetreten|Here you can find all BahnCard Services: changes of address, ordering replacement cards, and more besides.)/ims")
                    && !empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber'])) {
                    $this->SetBalanceNA();
                }

                if ($this->http->FindPreg("/(?:You already have a BahnCard or a BahnBonus card\? Add your BahnCard to your customer account to redeem your reward points and enjoy other benefits\.|Sie haben sich vom BahnBonus Programm der Deutschen Bahn AG abgemeldet\.|Sehr geehrte Kundin, sehr geehrter Kunde, leider sind Sie f.+r das BahnBonus Programm der Deutschen Bahn AG gesperrt\.|No BahnCard has been found for your details. Please log on again here|Travel, collect points, enjoy! By participating in the free BahnBonus programme, you can gain valuable BahnBonus loyalty points and redeem them for attractive rewards.<\/p>|<p>No information was found on your BahnBonus status. Please register again here.<\/p>)/ims")
                    && !empty($this->Properties['Name']) && !empty($this->Properties['AccountNumber'])) {
                    $this->SetWarning(self::NOT_MEMBER_MSG);
                }
                //					throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                // AccountID: 3507113
                if (
                    $this->http->FindSingleNode('//p[contains(text(), "Here you will find all BahnBonus services.")]')
                    && !empty($this->Properties['Name'])
                    && !empty($this->Properties['AccountNumber'])
                ) {
                    $this->SetBalanceNA();
                }
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function getExpDate($statusPoints)
    {
        if (!$this->Balance || $this->Balance <= 0) {
            return;
        }
        $this->logger->info('Expiration Date', ['Header' => 3]);

        $subAccount = [
            'Code'        => 'bahnStatusPoints',
            'DisplayName' => 'Status points',
            'Balance'     => $statusPoints,
        ];
        $balance = str_replace(".", "", $this->Balance);
        $statusPoints = str_replace(".", "", $statusPoints);

        $this->history = $this->ParseHistory(null);

        $this->logger->debug("> Balance: {$balance}");
        $this->logger->debug("> Status points: {$statusPoints}");
        $balanceExpDate = false;
        $statusPointsExpDate = false;

        foreach ($this->history as $key => $row) {
            $date = $row['Date'];
            // bonus points: exp_date = EarningDate + 3 year
            $bonusPts = str_replace(".", "", $row['Bonus points']);

            if ($bonusPts > 0 && !$balanceExpDate) {
                $balance -= $bonusPts;
                $this->logger->debug("[No #{$key}]: Date {$date} - {$row['Bonus points']} / Balance: $balance");
            }// if ($bonusPts > 0 && !$balanceExpDate)

            if (!$balanceExpDate && $balance <= 0) {
                $this->logger->debug("Balance:");
                $this->logger->debug("Date " . $date . " - " . var_export(date("Y-m-d", $date), true));
                $this->logger->debug("Expiration Date " . date("d.m.Y", strtotime("+3 year", $date)) . " - " . var_export(strtotime("+3 year", $date), true));
                // Earning Date     // refs #4936
                $this->SetProperty("EarningDate", date("Y-m-d", $date));
                // Expiration Date
                $this->SetExpirationDate(strtotime("+3 year", $date));
                // Points to Expire
                $balance += $bonusPts;

                for ($k = $key - 1; $k >= 0; $k--) {
                    $this->logger->debug("[#{$k}] > Balance: {$balance}");

                    if (isset($this->history[$k]['Date']) && ($date == $this->history[$k]['Date'] || $balance < 0) && $this->history[$key]['Bonus points'] > 0) {
                        $this->logger->debug("Date " . $date . " - " . var_export(date("Y-m-d", $this->history[$k]['Date']), true));
                        $this->SetExpirationDate(strtotime("+3 year", $this->history[$k]['Date']));
                        $bonusPtsExt = str_replace(".", "", $this->history[$k]['Bonus points']);

                        if ($bonusPtsExt > 0) {
                            $balance += $bonusPtsExt;
                        }
                    }
                }// for ($k = $key-1; $k >= 0; $k--)
                $this->SetProperty("ExpiringBalance", $balance);
                $balanceExpDate = true;

                // refs #22145, Deutsche Bahn (BahnCard) - expiration date corrections
                if (isset($this->Properties["AccountExpirationDate"])) {
                    $this->logger->notice("set exp date as last deay of quarter");
                    $currentDateTime = DateTime::createFromFormat('U', $this->Properties["AccountExpirationDate"]);
                    $monthNum = $currentDateTime->format('m');
                    $quarterNum = ceil($monthNum / 3);
                    $lastDayOfQuarter = (new \DateTime())
                        ->setDate(
                            $currentDateTime->format('Y'),
                            $quarterNum * 3,
                            1
                        )
                        ->setTime(23, 59, 59)
                        ->modify('last day of this month');
                    $this->SetExpirationDate($lastDayOfQuarter->getTimestamp());
                }
            }// if ($balanceExpDate && $balance <= 0)

            // status points: exp_date = EarningDate + 1 year
            $statusPts = str_replace(".", "", $row['Status points']);

            if ($statusPts > 0 && !$statusPointsExpDate) {
                $statusPoints -= $statusPts;
                $this->logger->debug("[No #{$key}]: Date {$date} - {$row['Status points']} / Status points: $statusPoints");
            }// if ($statusPts > 0 && !$statusPointsExpDate)

            if (!$statusPointsExpDate && $statusPoints <= 0) {
                $this->logger->debug("Status Points:");
                $this->logger->debug("Date " . $date . " - " . var_export(date("Y-m-d", $date), true));
                $this->logger->debug("Expiration Date " . date("d.m.Y", strtotime("+1 year", $date)) . " - " . var_export(strtotime("+1 year", $date), true));
                // Earning Date     // refs #4936
                $subAccount["EarningDate"] = date("Y-m-d", $date);
                // Expiration Date
                $subAccount["ExpirationDate"] = strtotime("+1 year", $date);
                // Points to Expire
                $statusPoints += $statusPts;

                for ($k = $key - 1; $k >= 0; $k--) {
                    $this->logger->debug("[#{$k}] > Status points: {$statusPoints}");

                    if (isset($this->history[$k]['Date']) && ($date == $this->history[$k]['Date'] || $statusPoints < 0) && $this->history[$key]['Status points'] > 0) {
                        $this->logger->debug("Date " . $date . " - " . var_export(date("Y-m-d", $this->history[$k]['Date']), true));
                        $subAccount["ExpirationDate"] = strtotime("+1 year", $this->history[$k]['Date']);
                        $statusPointsExt = str_replace(".", "", $this->history[$k]['Status points']);

                        if ($statusPointsExt > 0) {
                            $statusPoints += $statusPointsExt;
                        }
                    }
                }// for ($k = $key-1; $k >= 0; $k--)
                $subAccount["ExpiringBalance"] = $statusPoints;
                $statusPointsExpDate = true;
            }// if (!$statusPointsExpDate && $statusPoints <= 0)

            if ($balanceExpDate && $statusPointsExpDate) {
                break;
            }
        }// foreach ($this->history as $row)

        $this->SetProperty('CombineSubAccounts', false);
        $this->AddSubAccount($subAccount);
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"          => "PostingDate",
            "Category"      => "Description",
            "Amount"        => "Info",
            "Details"       => "Info",
            "Bonus points"  => "Bonus",
            "Status points" => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        if (!empty($this->history)) {
            $this->logger->debug('All history was parsed earlier');

            if ($startDate !== null) {
                $this->logger->debug('Filtering transactions according to history start date');

                return array_filter($this->history, function ($transaction) use ($startDate) {
                    return $transaction['Date'] >= $startDate;
                });
            }

            return $this->history;
        }
        $this->http->FilterHTML = false;
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        if (!$this->collectedHistory) {
            return $result;
        }
        // improvements
        if (empty($this->history) || !$this->http->FindNodes(self::XPATH_PAGE_HISTORY)) {
            // open english version
            if ($this->AccountFields['Login2'] != 'Business') {
                //        $this->http->GetURL("https://fahrkarten.bahn.de/privatkunde/meinebahn/meine_bahn_portal.go?lang=en&country=USA");
//                $this->http->GetURL("https://fahrkarten.bahn.de/privatkunde/service/service.go?lang=en&country=USA&service=mbahnbonuspunkte&now=" . time() . date("B") . "&serviceppage=/meinebahn/meine_bahn_portal&lang=en&country=USA&serviceli=1");
                $link = $this->http->FindSingleNode('//a[contains(@href, "bahncard_punkte_start")]/@href');
                $this->http->GetURL($link);

                if ($this->http->ParseForm("formularMeineBahnBonusPunkte")) {
                    $this->http->Form['mbahnbonuspunkte.button.bahnbonus_p'] = 'Punkteübersicht';
                    $this->http->PostForm();
                }// if ($this->http->ParseForm("formularMeineBahnBonusPunkte")) {
            }// if ($this->AccountFields['Login2'] != 'Business')

            $date = strtotime("-39 month");

            if ($this->http->ParseForm("formular")) {
                $this->http->SetInputValue("auswahl.von", preg_replace('/.$/', '', date("D", $date)) . date(", d.m.y", $date));
                $this->http->SetInputValue("button.aktualisieren_p_js", "true");
                //            $this->http->SetInputValue("auswahl.bis", preg_replace('/.$/', '', date("D, ")).date("d.m.y"));
                $this->http->PostForm();
            }// if ($this->http->ParseForm("formular"))
        }// if (empty($this->history) || !$this->http->FindNodes(self::XPATH_PAGE_HISTORY))

        $page = 0;
        $this->logger->debug("[Page: {$page}]");
        $startIndex = sizeof($result);
        $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $transactions = $this->http->XPath->query(self::XPATH_PAGE_HISTORY);
        $this->logger->debug("Total {$transactions->length} transactions were found");

        foreach ($transactions as $transaction) {
            $dateStr = $this->http->FindSingleNode('td[3]', $transaction);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }
            $result[$startIndex]['Date'] = $postDate;
            $result[$startIndex]['Category'] = $this->http->FindSingleNode('td[4]', $transaction);
            $result[$startIndex]['Amount'] = $this->http->FindSingleNode('td[5]', $transaction);
            $result[$startIndex]['Bonus points'] = $this->http->FindSingleNode('td[6]', $transaction);
            $result[$startIndex]['Status points'] = $this->http->FindSingleNode('td[7]', $transaction);
            $result[$startIndex]['Details'] = implode('; ', array_filter($this->http->FindNodes('td[8]/text()', $transaction)));

            $startIndex++;
        }// foreach ($transactions as $transaction)

        return $result;
    }

    public function ParseItineraries()
    {
        $this->http->FilterHTML = false;
        $result = [];
        $startTimer = $this->getTime();

        $this->logger->debug('[Parse itineraries]');

        if (strstr($this->http->currentUrl(), 'grosskunde')) {
            // business account
            $this->http->GetURL('https://fahrkarten.bahn.de/grosskunde/buchungsrueckschau/brs_uebersicht.go?lang=en&country=GBR');
        } else { // personal account
            $this->http->GetURL('https://fahrkarten.bahn.de/privatkunde/buchungsrueckschau/brs_uebersicht.go?lang=en&country=GBR');
        }

        $date = strtotime("-1 year");

        if ($this->http->ParseForm("formular")) {
            $this->http->SetInputValue("vonDatumTag", date("d", $date));
            $this->http->SetInputValue("vonDatumMonat", date("m", $date));
            $this->http->SetInputValue("vonDatumJahr", date("Y", $date));
            $this->http->SetInputValue("bisDatumTag", date("d"));
            $this->http->SetInputValue("bisDatumMonat", date("m"));
            $this->http->SetInputValue("bisDatumJahr", date("Y"));
            $this->http->SetInputValue("button.aktualisieren_p_js", "true");
            $this->http->PostForm();
        }// if ($this->http->ParseForm("formular"))

        if (!empty($text = $this->http->FindSingleNode("//*[contains(text(),'No order found for selected time period')]"))) {
            $this->logger->debug($text);
            $this->getTime($startTimer);

            return $result;
        }
        $orders = [];
        $flagAll = false;
        $i = 0;

        do {
            $i++;
            $nodes = $this->http->XPath->query("//*[text()='Order']/ancestor::tr[contains(.,'Booking date')]/ancestor::table[1]/descendant::tr[position()>1 and td[5][ string-length(normalize-space(.))>0]]");

            foreach ($nodes as $node) {
                $order = $this->http->FindSingleNode("./td[1]", $node);
                $dateTravel = $this->http->FindSingleNode("./td[3]", $node);
                $this->logger->debug($order . ' - ' . $dateTravel);
                $dateTravel = strtotime($dateTravel);

                if ($dateTravel && $dateTravel >= strtotime(date("Y-m-d"))) {
                    $orders[] = $order;
                } else {
                    $this->logger->notice('skip past itinerary');
                    $flagAll = true;
                }
            }// foreach($nodes as $node)

            if ($this->http->XPath->query("//form[@name='formular']//input[@value='Next']")->length == 0) {
                break;
            }

            if ($flagAll) {
                if ($this->http->ParseForm("formular")) {
                    $this->http->SetInputValue("button.blaettern.next_p", "Next");
                    $this->http->PostForm();
                }// if ($this->http->ParseForm("formular"))
            }
        } while (!$flagAll && $i < 5);
        $this->logger->debug("Count pages with order numbers parse: " . ($i));

        foreach ($orders as $order) {
            $this->logger->info('Parse itinerary #' . $order, ['Header' => 3]);
            $reservation = $this->ParseItinerary($order);

            if (count($reservation)) {
                $result[] = $reservation;
            }
            $this->logger->debug('Parsed itinerary.');
//            $this->logger->debug(var_export($reservation, true), ['pre' => true]);
        }

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePDFTicket(Train $r, $body, $busOut = false, ?Email $email = null)
    {
        $this->logger->notice(__METHOD__);

        if (($html = PDF::convertToHtml($body, PDF::MODE_SIMPLE)) !== null) {
        } else {
            $this->logger->debug("can't convert pdf");

            return false;
        }

        $text = PDF::convertToText($body);

        $http1 = clone $this->http;
        $http2 = clone $this->http;
        $http1->SetBody($html);

        $htmlComplex = PDF::convertToHtml($this->http->Response['body'], PDF::MODE_COMPLEX);
        $http2->SetBody($htmlComplex);

        if (preg_match_all("#Summe\s*([\d,]+)\s*(.+?)\s#", $text, $m)) {
            foreach ($m[1] as $item) {
                if (isset($cost)) {
                    $cost += PriceHelper::cost($item, '.', ',');
                } else {
                    $cost = PriceHelper::cost($item, '.', ',');
                }
            }

            if (isset($m[2][0])) {
                $cur = $this->currency($m[2][0]);
            }
        }

        if (isset($email, $cost, $cur)) {
            $email->price()
                ->total($cost)
                ->currency($cur);
        } elseif (isset($cost, $cur)) {
            $r->price()
                ->total($cost)
                ->currency($cur);
        }

        $texts = $this->splitter("#Ihre Reiseverbindung und Reservierung(.+?)#s", $text);

        if (count($texts) == 0) {
            $texts = $this->splitter("#Ihre Reiseverbindung *\n(.+?)#s", $text);

            if (count($texts) == 0) {
                $this->sendNotification('refs #15268 - other format pdf');

                return false;
            }

            if (count($texts) == 1) {
                $dateTripStrOne = $this->http->FindPreg("#Gültigkeit: am\s*(\d+\.\d+\.\d+)#", false, $text);
            }
        }

        $num = 0;

        foreach ($texts as $text) {
            if (strpos($text, "Seite") !== false) {
                $text = preg_replace("#.*Seite\s*\d+\s*\/\s*\d+#", "", $text);
            }

            if (strpos($text, "Wichtige Nutzungshinweise") !== false) {
                $text = strstr($text, "Wichtige Nutzungshinweise", true);
            }

            if (isset($dateTripStrOne)) {
                $dateTripStr = $dateTripStrOne;
            } else {
                $dateTripStr = $this->http->FindPreg("#am\s*(\d+\.\d+\.\d+)#", false, $text);
            }

            if (empty($dateTripStr)) {
                $this->sendNotification('refs #15268 - other format pdf - can\'t determine date Trip');

                return false;
            }
            //			$this->logger->debug("Date trip: " . $dateTripStr);
            $dateTrip = strtotime($dateTripStr);

            if (empty($cabin = $http1->FindSingleNode("(//text()[contains(.,'(" . $dateTripStr . ")')])[1]/preceding::text()[1][string-length(normalize-space(.))<4]"))) {
                $cabin = $http1->FindSingleNode("(//text()[contains(.,'Klasse:')])[1]/following::text()[string-length(normalize-space(.))>0][1]");
            }

            $arr = $this->splitter("#(.+?\s*\d{2}\.\d{2}\.\s+ab)#", $text);
            //			$this->logger->debug("Total " . count($arr) . " segments were found");
            foreach ($arr as $item) {
                $num++;
                //				$this->logger->debug("[Searched segment]: \n" . $item, ['pre' => true]);//for debug
                $item = preg_replace("/\b(\d{2}\.\d{2}\.[ ]*a[bn][ ]+\d{2}:\d{2})/", '  $1', $item);
                $item = preg_replace("/\d+-\d{2}(\d{2}\.\d{2}\.\s*ab)/u", '       $1', $item);

                $this->logger->debug("[Searched segment]: \n" . $item, ['pre' => true]); //for debug

                $pos = [0];
                $trainInfo = $http2->FindSingleNode("(//p[starts-with(normalize-space(.),'an ')])[{$num}]/following::p[normalize-space(.)!=''][1]");
                $this->logger->debug("[trainInfo]: " . $trainInfo);
                //NB:  '(?:\-[A-Z])?' - ending train name - is for like 'Schw-B'
                if (preg_match("#^([A-z]+(?:-[A-Z])? *[A-Z\d]*)#", $trainInfo, $v) && preg_match("#^(((.+?)[ ]{2}\d{2}\.\d{2}\. +)ab \d{2}:\d{2}(?: +.*?)?){$v[1]}#", $item, $m)) {
                    $posCol2 = mb_strlen($m[3]);
                    $pos[] = $posCol2;
                    $posCol3 = mb_strlen($m[2]);
                    $pos[] = $posCol3;
                    $posCol4 = mb_strlen($m[1]);
                    $pos[] = $posCol4;
                } elseif (preg_match("#^(\d{3,})#", $trainInfo, $v) && preg_match("#^(((.+?)[ ]{2}\d{2}\.\d{2}\. +)ab \d{2}:\d{2}(?: +.*?)?){$v[1]}#", $item, $m)) {
                    $noType = true;
                    $posCol2 = mb_strlen($m[3]);
                    $pos[] = $posCol2;
                    $posCol3 = mb_strlen($m[2]);
                    $pos[] = $posCol3;
                    $posCol4 = mb_strlen($m[1]);
                    $pos[] = $posCol4;
                } elseif (preg_match("#^(((.+?)[ ]{2}\d{2}\.\d{2}\. +)ab \d{2}:\d{2}(?: +.*?)? {2,})[A-z]+(?:-[A-Z])? *[A-Z\d]*#", $item, $m)) {
                    $posCol2 = mb_strlen($m[3]);
                    $pos[] = $posCol2;
                    $posCol3 = mb_strlen($m[2]);
                    $pos[] = $posCol3;
                    $posCol4 = mb_strlen($m[1]);
                    $pos[] = $posCol4;
                } elseif (preg_match("#^(((.+\s+?)\s*\d{2}\.\d{2}\. +)ab \d{2}:\d{2}(?: +(?:[A-z]+)? *[\d/]+(?:[A-z \-]+)?)? +)[A-z]+ *\d+#u", $item, $m)) {
                    //kostyl "with one space, not \s{4,}"
                    //Gleis:   \d+  |  7/8   | Fern 4   |   4 A - D  | + not work with...  14b | 4a/b | ab | 5-6
                    /*
                             Koblenz Hbf                  06.10.    ab 19:00 5 Nord RE 4126
                             Bullay(DB)                   06.10.    an 19:47 2
                     */
                    $posCol2 = mb_strlen($m[3]);
                    $pos[] = $posCol2;
                    $posCol3 = mb_strlen($m[2]);
                    $pos[] = $posCol3;
                    $posCol4 = mb_strlen($m[1]);
                    $pos[] = $posCol4;
                } elseif (preg_match("#^(((.+\s+?)\s*\d{2}\.\d{2}\. +)ab \d{2}:\d{2}(?: +(?:[A-z]+)? *[\d/]+(?:[A-zü \-]+)?)? +)\d+#u", $item, $m)) {
                    $noType = true;
                    $posCol2 = mb_strlen($m[3]);
                    $pos[] = $posCol2;
                    $posCol3 = mb_strlen($m[2]);
                    $pos[] = $posCol3;
                    $posCol4 = mb_strlen($m[1]);
                    $pos[] = $posCol4;
                }

                if (count($pos) !== 4) {
                    $this->sendNotification("refs #15268 - may be another format pdf, need to check (format segment)");
                    $this->logger->debug("[Segment]:\n" . $item, ['pre' => true]);

                    return false;
                }

                /* Example:

                Halle(Saale)Hbf                      21.08.   ab 08:15   8           ICE 1636       1 Sitzplatz, Wg. 22, Pl. 87, 1 Gang, Großraum,
                Erfurt Hbf                           21.08.   an 08:48   2                          Nichtraucher,
                                                                                                    Ruhebereich, Res.Nr. 8011 2005 8950 31
                --------------------------------------------------------------------------------------------------------
                Düren                                    16.01.    ab 18:21   23            RTB90755
                Jülich                                   16.01.    an 18:40
                */
                // convert to table
                $table = $this->splitCols($item, $pos);

                if (empty($this->http->FindPreg("#^\d{2}\.\d{2}\.\s+(\d{2}\.\d{2})\.#", false, $table[1]))) {
                    //kostyl
                    /*
                    Handschuhsheim Hans-Thoma- 02.03.                 ab 15:50               STR 24
                    Platz, Heidelberg
                    Hauptbahnhof (West), Heidelberg 02.03.            an 16:00
                     * */
                    $table[0] = $this->mergeCols($table[0], $table[1]);
                    $subj = explode("\n", $table[0]);
                    $table[0] = "";
                    $table[1] = "";

                    foreach ($subj as $s) {
                        if (preg_match("#(.+)\b(\d{2}\.\d{2}\.)\s*$#", $s, $m)) {
                            $table[0] .= $m[1] . "\n";
                            $table[1] .= $m[2] . "\n";
                        } else {
                            $table[0] .= $s . "\n";
                        }
                    }
                }
//                $this->logger->debug("[Searched segment TABLE COLUMNS]: \n" . var_export($table,true), ['pre' => true]);//for debug

                $trains = ['ICE', 'IC', 'EC', 'ECE', 'CNL', 'IRE', 'RE', 'IRE', 'RB', 'S', 'U', 'ERB', 'RJ', 'RJX', 'ICD',
                    'ABR', 'N', 'E', 'D', 'SE', 'ag', 'as', 'BLB', 'WFB', 'M', 'ME', 'TGV', 'erx', 'STB', 'RNV', 'EBx', 'EB', 'VBG',
                    'SBB', 'UBB', 'RT', 'IR', 'NWB', 'BOB', 'FLX', 'ALX', 'VIA', 'TLX', 'TL', 'OE', 'SWE', 'BRB', 'ENO', 'EST', 'FEX',
                    'EX', 'ICL', 'R', 'HzL', 'REX', 'MEX', 'Os', 'EVB', 'AKN', 'X2', 'STx', 'OPB', 'VEN', 'HLB', 'neg', 'NBE', 'KD', 'RTB', ]; // neg 5742 ???

                $transfer = ['Bus', 'BUS', 'STR', 'Schiff', 'Schw-B', 'BusAirpo', 'RUF', 'AST', 'ALT', 'SB']; //bus, tram, ship (RUF=Ruf bus)
                //////NB: AST,ALT - like transfer - taxi https://www.bahn.de/autokraft/view/angebot/flexible_bedienformen.shtml
                ///  SB - car cable FE: from 'Betten BAB' to 'Bettmeralp'
                $type = '';

                // FE: X2 542, 1 Sitzplatz, Wg. 5, Pl. 45,  |   ICL51357, 1 Sitzplatz, Wg. 61,
                // RTB90755
                if (preg_match("#^([A-z]+(?:-[A-Z]|\d+)?) +[A-Z\d]*, \d+ Sitzplatz#", $table[3], $m)
                    || preg_match("#^([A-z]+(?:-[A-Z])?)\s*[A-Z\d]*#", $table[3], $m)) {
                    $type = $m[1];

                    if (stripos($type, 'Bus') === 0) {
                        $type = substr($type, 0, 3);
                    }

                    if (!in_array($type, $trains) && !in_array($type, $transfer)) {
                        $this->sendNotification("refs #15268 - need to check {$type} transport (check line 1136)");
                        $this->logger->debug("[table[3]]: " . $table[3]);
                    }
//                    if (in_array($type, $transfer)) {
//                        $this->sendNotification("refs #15268 - bus");
//                        $this->logger->debug("[Segment]: ". $item);
//                    }
                }

                if ($busOut && in_array($type, $transfer)) {
                    $this->logger->notice("Parse Bus - busOut=$busOut, type=$type");

                    if (isset($email)) {
                        if (null !== ($b = $this->getBus($email->getItineraries()))) {
//                            // bus already exist
//                            /** @var Bus $b */
                            $newIt = false;
                        } else {
                            /** @var Bus $b */
                            $b = $email->add()->bus();
                            $newIt = true;
                        }
                    } else {
                        if (null !== ($b = $this->getBus($this->itinerariesMaster->getItineraries()))) {
//                            // bus already exist
                            $newIt = false;
                        } else {
                            $b = $this->itinerariesMaster->add()->bus();
                            $newIt = true;
                        }
                    }
                    //# Bus, Tram...
                    if (isset($newIt) && $newIt) {
                        $tripNum = $r->getConfirmationNumbers()[0][0];

                        if (!empty($tripNum)) {
                            $b->general()->confirmation($tripNum, 'Order', true);
                        }

                        if (!empty($status = $r->getStatus())) {
                            $b->general()->status($status);
                        }

                        if (!empty($dateRes = $r->getReservationDate())) {
                            $b->general()->date($dateRes);
                        }
                        $travellers = $r->getTravellers();

                        foreach ($travellers as $tr) {
                            $b->general()->traveller($tr[0], true);
                        }

                        if ($r->getAreNamesFull()) {
                            $b->setAreNamesFull(true);
                        }
                    }

                    $strArr = preg_quote($this->http->FindPreg("#\n(.+) *\d{2}\.\d{2}\. +an \d{2}:\d{2}#", false, $item));

                    $segBus = $b->addSegment();
                    $segBus->departure()
                        ->name($this->http->FindPreg("#(.+)\s+{$strArr}#s", false, $table[0]));
                    $segBus->arrival()
                        ->name($this->http->FindPreg("#({$strArr}.+)#s", false, $table[0]));

                    $depDate = $this->http->FindPreg("#^(\d{2}\.\d{2})\.\s+\d{2}#", false, $table[1]);
                    $arrDate = $this->http->FindPreg("#^\d{2}\.\d{2}\.\s+(\d{2}\.\d{2})\.#", false, $table[1]);
                    $this->logger->debug('Dates: ' . $depDate . '-' . $arrDate);
                    $depDate = EmailDateHelper::parseDateRelative($depDate, $dateTrip, true,
                        EmailDateHelper::FORMAT_DOT_DATE_YEAR);
                    $arrDate = EmailDateHelper::parseDateRelative($arrDate, $dateTrip, true,
                        EmailDateHelper::FORMAT_DOT_DATE_YEAR);

                    $depTime = $this->http->FindPreg("#ab +(\d{2}:\d{2})#", false, $table[2]);
                    $arrTime = $this->http->FindPreg("#an +(\d{2}:\d{2})#", false, $table[2]);

                    if (empty($arrTime)) {
                        $arrTime = $this->http->FindPreg("#\s+(\d{2}:\d{2})$#us", false, $table[2]);
                    }
                    $this->logger->debug('Times: ' . $depTime . '-' . $arrTime);

                    $segBus->departure()
                        ->date(strtotime($depTime, $depDate));
                    $segBus->arrival()
                        ->date(strtotime($arrTime, $arrDate));

                    $Number = $this->http->FindPreg("#{$type} *([A-Z\d]*)#", false, $table[3]);

                    if (empty($Number)) {
                        $segBus->extra()->noNumber();
                    } else {
                        $segBus->extra()->number($Number);
                    }

                    $segBus->extra()->type($type);

                    $descr = implode("-", array_filter([$type, $Number]));
                    $confNo = preg_replace("#\s+#", '',
                        $this->http->FindPreg("#Res.Nr. (\d[\d\s]+)#", false, $table[3]));

                    if (!empty($confNo)) {
                        $b->general()->confirmation($confNo, "Res.Nr.({$descr})");
                    }

                    $descr = $this->http->FindPreg("#{$type} *[A-Z\d]+[,\s]+(.+)#s", false, $table[3]);

                    if (!empty($descr)) {
                        $wg = $this->http->FindPreg("#(Wg\.\s*\d+)#", false, $descr);
                        $pl = $this->http->FindPreg("#(Pl\.\s*\d+)#", false, $descr);
                        $seat = trim($wg . ' ' . $pl);

                        if (!empty($seat)) {
                            $segBus->extra()->seat($seat);
                        }
                    }

                    if (stripos($table[3], 'Nichtraucher') !== false) {
                        $segBus->extra()->smoking(false);
                    }
                    $this->logger->debug(var_export($b->toArray(), true), ['pre'=>true]);
                } else {
                    $this->logger->notice("Parse Train");

                    //#Train
                    $strArr = preg_quote($this->http->FindPreg("#\n(.+) *\d{2}\.\d{2}\. +an \d{2}:\d{2}#", false, $item));

                    $s = $r->addSegment();

                    $s->departure()
                        ->name($this->addCountry($this->http->FindPreg("#(.+)\s+{$strArr}#s", false, $table[0])))
                        ->geoTip('Europe');
                    // TODO: addCounty() =>  Error occurred while solving parsed data: Impossible route between `Germany` <--> `Mexico` in `train-0-2`

                    $s->arrival()
                        ->name($this->addCountry($this->http->FindPreg("#({$strArr}.+)#s", false, $table[0])))
                        ->geoTip('Europe');
                    $s->extra()
                        ->cabin($cabin, false, true);
                    $depDate = $this->http->FindPreg("#^(\d{2}\.\d{2})\.\s+\d{2}#", false, $table[1]);
                    $arrDate = $this->http->FindPreg("#^\d{2}\.\d{2}\.\s+(\d{2}\.\d{2})\.#", false, $table[1]);

                    $depDate = EmailDateHelper::parseDateRelative($depDate, $dateTrip, true,
                        EmailDateHelper::FORMAT_DOT_DATE_YEAR);
                    $arrDate = EmailDateHelper::parseDateRelative($arrDate, $dateTrip, true,
                        EmailDateHelper::FORMAT_DOT_DATE_YEAR);

                    $depTime = $this->http->FindPreg("#ab +(\d{2}:\d{2})#", false, $table[2]);
                    $arrTime = $this->http->FindPreg("#(?:an\s+|\n+)(\d{2}:\d{2})#", false, $table[2]);
                    $this->logger->debug('Times: ' . $depTime . '-' . $arrTime);

                    $s->departure()
                        ->date(strtotime($depTime, $depDate));
                    $s->arrival()
                        ->date(strtotime($arrTime, $arrDate));

                    $Number = $this->http->FindPreg("#{$type} *([A-Z\d]*)#", false, $table[3]);

                    if (empty($Number)) {
                        $s->extra()->noNumber();
                    } else {
                        $s->extra()->number($Number);
                    }

                    if (!isset($noType)) {
                        $s->extra()->type($type);
                    }

                    $descr = implode("-", array_filter([$type, $Number]));
                    $confNo = preg_replace("#\s+#", '',
                        $this->http->FindPreg("#Res.Nr. (\d[\d\s]+)#", false, $table[3]));

                    if (!empty($confNo)) {
                        $r->general()->confirmation($confNo, "Res.Nr.({$descr})");
                    }

                    $descr = $this->http->FindPreg("#{$type} *[A-Z\d]+[,\s]+(.+)#s", false, $table[3]);

                    if (!empty($descr)) {
                        $wg = $this->http->FindPreg("#Wg\.\s*(\d+)#", false, $descr);
                        $pl = $this->http->FindPreg("#Pl\.\s*(\d+)#", false, $descr);

                        if (empty($pl)) {
                            $pl = $this->http->FindPreg("#Wg\.\s*\d+\,\s*\.\s*(\d+)\,#", false, $descr);

                            /*if (!empty($pl)) {
                                $pl = 'Pl. ' . $pl;
                            }*/
                        }

                        /*$seat = trim($wg . ' ' . $pl);*/

                        if (!empty($pl)) {
                            $s->extra()->seat($pl);
                        }

                        if (!empty($wg)) {
                            $s->setCarNumber($wg);
                        }
                    }

                    if (stripos($table[3], 'Nichtraucher') !== false) {
                        $s->extra()->smoking(false);
                    }
                }
            }
        }
        $this->logger->debug(var_export($r->toArray(), true), ['pre'=>true]);

        return true;
    }

    public function GetConfirmationFields()
    {
        return [
            "LastName" => [
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Order number",
                "Type"     => "string",
                "Size"     => 10,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://fahrkarten.bahn.de/privatkunde/start/start.post?from_page=meinebahn&scope=bahnatsuche&lang=en&country=GBR";
        //		return "https://fahrkarten.bahn.de/privatkunde/start/start.post?from_page=meinebahn&scope=bahnatsuche&lang=en&country=GBR#stay";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $counter = 0;

        do {
            if (!$this->http->ParseForm("formular")) {
                if ($msg = $this->http->FindSingleNode("//h1[contains(text(), '503 Service Temporarily Unavailable')]")) {
                    return $msg;
                }
                $this->sendNotification("failed to retrieve itinerary by order #");

                return null;
            }
            $this->http->SetInputValue("reisenderNachname", $arFields["LastName"]);
            $this->http->SetInputValue("auftragsnr", $arFields["ConfNo"]);
            $this->http->SetInputValue("button.suchen_p_js", "true");
            $this->http->SetInputValue("button.suchen", "");

            if (isset($this->http->Form["captcha-request-url"])) {
                $this->sendNotification("check retrieve, captcha again// ZM");
                $captcha = $this->parseCaptcha();
                $this->http->SetInputValue("captchaInputField", $captcha);
                $this->http->unsetInputValue("captcha-request-url");
                $this->http->unsetInputValue("captcha-image-url");
                $this->http->unsetInputValue("captcha-searchtimeout");
            }

            if (!$this->http->PostForm()) {
                return null;
            }

            if (!empty($mes = $this->http->FindSingleNode("//span[@id='invalidEntry']"))) {
                return $mes;
            }

            if (!empty($this->http->FindSingleNode("//span[@class='errormsg' and contains(text(),'This field must not be empty')]"))) {
                return "Fields must not be empty";
            }

            if (!empty($mes = $this->http->FindSingleNode("//img[@id='scrambleImage']/following::span[@class='errormsg']"))) {
                $this->recognizer->reportIncorrectlySolvedCAPTCHA();
                $this->logger->error('[Captcha]: ' . $mes);
            } else {
                break;
            }
            $counter++;
        } while ($counter < 2);

        if (!empty($mes = $this->http->FindSingleNode("//img[@id='scrambleImage']/following::span[@class='errormsg']"))) {
            if (strpos($mes, "code you entered was not correct") !== false) {//for mes on english
                $mes = str_replace("Please enter the following new code", "Please try again one more time", $mes);
            } else { //for others lang-s
                $mes = $this->http->FindPreg("#^(.+?)\.#", false, $mes);
            }

            return $mes;
        }

        if (empty($this->http->FindSingleNode("//text()[normalize-space(.)='Order - Details']"))) {
            return "Something wrong with page or other problem";
        }

        $it = $this->ParseItinerary($arFields["ConfNo"]);

        return null;
    }

    protected function parseCaptcha()
    {
        $src = $this->http->FindSingleNode("//img[@id='scrambleImage']/@src");
        $this->http->NormalizeURL($src);
        $this->logger->debug($src);
        $http2 = clone $this->http;
        $file = $http2->DownloadFile($src, 'jpg');

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $captcha = $this->recognizeCaptcha($this->recognizer, $file, ["regsense" => 1]);

        unlink($file);

        return $captcha;
    }

    protected function splitter($regular, $text)
    {
        $result = [];

        $array = preg_split($regular, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        array_shift($array);

        for ($i = 0; $i < count($array) - 1; $i += 2) {
            $result[] = $array[$i] . $array[$i + 1];
        }

        return $result;
    }

    protected function parseHCaptcha($currentUrl = null)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('
            //button[contains(@class, "h-captcha")]/@data-sitekey
            | //form[@id = "kc-form-login"]//div[contains(@class, "h-captcha")]/@data-sitekey
        ');

        if (!$key) {
            return false;
        }

        $postData = [
            "type"        => "HCaptchaTaskProxyless",
            "websiteURL"  => $currentUrl ?? $this->http->currentUrl(),
            "websiteKey"  => $key,
            //            "isInvisible" => true,
        ];
        /*
        $postData = array_merge(
            [
                "type"        => "HCaptchaTask",
                "websiteURL"  => $currentUrl ?? $this->http->currentUrl(),
                "websiteKey"  => $key,
                //                "isInvisible" => true,
            ],
            $this->getCaptchaProxy()
        );
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;
        return $this->recognizeAntiCaptcha($recognizer, $postData);
        */

        $this->recognizer = $this->getCaptchaRecognizer();
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"    => "hcaptcha",
            "pageurl"   => $currentUrl ?? $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            //            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    // h-captcha issue
    private function callRetriesOnCaptcha($message = null)
    {
        $this->logger->notice(__METHOD__);
        $this->captchaReporting($this->recognizer, false);

        $this->DebugInfo = null;

        if ($this->http->Response['code'] == 503) {
            $this->DebugInfo = 503;
        }

        throw new CheckRetryNeededException(2, 3, $message);
    }

    private function ParseItinerary($order)
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->GetURL('https://fahrkarten.bahn.de/privatkunde/buchungsrueckschau/brs_auftrag_details.go?lang=en&country=GBR&auftragsnr=' . $order);

        if (empty($this->http->FindSingleNode("//text()[normalize-space(.)='Order - Details']"))) {
            $this->logger->error("Something wrong with format page or other problem");

            return [];
        }

        if ($this->http->XPath->query("//td[contains(.,'ltig von') and contains(.,'ltig bis')]")->length > 0) {
            $this->logger->error("skip order. with pass ticket");

            return [];
        }

        if (!empty($msg = $this->http->FindSingleNode("//text()[normalize-space()='Rail journey']/ancestor::h2[1]/following-sibling::span[1][normalize-space()='cancelled']"))) {
            $r = $this->itinerariesMaster->add()->train();
            $r->general()
                ->confirmation($order)
                ->status($msg)
                ->cancelled();

            return $result;
        }

        $r = $this->itinerariesMaster->add()->train();
        $pax = array_map(function ($elem) {
            return beautifulName($elem);
        }, $this->http->FindNodes("//span[normalize-space(text())='Passenger']/following-sibling::span"));
        $r->general()
            ->confirmation($order, 'Order', true)
            ->travellers($pax, true)
            ->status($this->http->FindSingleNode("//span[normalize-space(text())='Booking status']/following-sibling::div[1]/span"))
            ->date(strtotime($this->http->FindSingleNode("//span[normalize-space(text())='Booking date']/following-sibling::span")));

        if ($this->http->ParseForm("formularBausteinGroup")) {
            $this->http->SetInputValue("button.bahnfahrtAbrufen_p_js", "true");
            $this->http->PostForm();
        }// if ($this->http->ParseForm("formularBausteinGroup"))

        $href = $this->http->FindSingleNode("//a[
			contains(.,'View Online') or
			contains(text(), 'Show online ticket') or
			contains(text(), 'Further information')
		]/@href");
        $this->http->NormalizeURL($href);
        $this->logger->debug($href);

        if (!empty($href) && $this->http->FindPreg("#pdf#i", false, $href)) {
            $file = $this->http->DownloadFile($href);
            unlink($file);

            if (!$this->ParsePDFTicket($r, $this->http->Response['body'], true)) {
                $this->logger->debug("can't parse/find segments");
                $this->itinerariesMaster->removeItinerary($r);

                return [];
            } elseif (count($r->getSegments()) === 0) {
                $this->logger->debug("no train segments");
                $this->itinerariesMaster->removeItinerary($r);
            }
        } else {
            $this->itinerariesMaster->removeItinerary($r);
        }

        return $result;
    }

    private function mergeCols($col1, $col2)
    {
        $rows1 = explode("\n", $col1);
        $rows2 = explode("\n", $col2);
        $newRows = [];

        foreach ($rows1 as $i => $row) {
            if (isset($rows2[$i])) {
                $newRows[] = $row . $rows2[$i];
            } else {
                $newRows[] = $row;
            }
        }

        if (($i = count($rows1)) > count($rows2)) {
            for ($j = $i; $j < count($rows2); $j++) {
                $newRows[] = $rows2[$j];
            }
        }

        return implode("\n", $newRows);
    }

    private function getBus(array $its)
    {
        /** AwardWallet\Schema\Parser\Common\Itinerary $it */
        foreach ($its as $it) {
            if ($it->getType() === 'bus') {
                return $it;
            }
        }

        return null;
    }

    // need for Parsing Text Tables;
    private function splitCols($text, $pos = false)
    {
        $cols = [];
        $rows = explode("\n", $text);

        if (!$pos) {
            $pos = $this->rowColsPos($rows[0]);
        }
        arsort($pos);

        foreach ($rows as $row) {
            foreach ($pos as $k => $p) {
                $cols[$k][] = trim(mb_substr($row, $p, null, 'UTF-8'));
                $row = mb_substr($row, 0, $p, 'UTF-8');
            }
        }
        ksort($cols);

        foreach ($cols as &$col) {
            $col = implode("\n", $col);
        }

        return $cols;
    }

    private function rowColsPos($row)
    {
        $head = array_filter(array_map('trim', explode("|", preg_replace("#\s{2,}#", "|", $row))));
        $pos = [];
        $lastpos = 0;

        foreach ($head as $word) {
            $pos[] = mb_strpos($row, $word, $lastpos, 'UTF-8');
            $lastpos = mb_strpos($row, $word, $lastpos, 'UTF-8') + mb_strlen($word, 'UTF-8');
        }

        return $pos;
    }

    private function addCountry(?string $city)
    {
        $cities = [
            'Jossa'            => 'Jossa Deutschland',
            'Oldenburg(Oldb)'  => 'Oldenburg(Oldb), Deutschland',
            'Sande'            => 'Sande, Norwegen',
            'Lauda'            => 'Lauda, Germany',
            'Chiasso'          => 'Chiasso, Switzerland',
            'Hamburg Airport'  => 'Hamburg Airport, Germany',
            'Hamburg-Ohlsdorf' => 'Hamburg-Ohlsdorf, Germany',
            'Winterthur'       => 'Winterthur, Switzerland',
        ];

        foreach ($cities as $c => $fullC) {
            if ($city == $c) {
                return $fullC;
            }
        }

        return $city;
    }

    private function seleniumAuth($loginURL)
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->useGoogleChrome();

            $selenium->seleniumOptions->addAntiCaptchaExtension = true;
            $selenium->seleniumOptions->antiCaptchaProxyParams = $this->getCaptchaProxy();

            /*
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
            $selenium->seleniumOptions->fingerprintParams = FingerprintParams::vanillaFirefox();
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->setKeepProfile(true);
            */

//            $request = FingerprintRequest::chrome();
//            $request->browserVersionMin = \HttpBrowser::BROWSER_VERSION_MIN;
//            $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
//
//            if ($fingerprint !== null) {
//                $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
//                $selenium->http->setUserAgent($fingerprint->getUseragent());
//            }

//            $selenium->disableImages();
//            $selenium->useCache();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->GetURL($loginURL);

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "username"]'), 10);
            $passwordInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"]'), 0);
            $button = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(., "Log In")]'), 5);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passwordInput || !$button) {
                return false;
            }

            $loginInput->sendKeys($this->AccountFields['Login']);
            $passwordInput->sendKeys($this->AccountFields['Pass']);

            /*
            $mover = new MouseMover($selenium->driver);
            $mover->duration = rand(100000, 120000);
            $mover->steps = rand(50, 70);

            $this->logger->debug("login");
            $mover->moveToElement($loginInput);
            $mover->click();
            $mover->sendKeys($loginInput, $this->AccountFields['Login'], 5);
            $this->logger->debug("pass");
            $mover->moveToElement($passwordInput);
            $mover->click();
            $mover->sendKeys($passwordInput, $this->AccountFields['Pass'], 5);
            */

            $selenium->driver->executeScript("var remember = document.getElementById('softLoginCheckbox'); if (remember) remember.checked = true;");

            $this->savePageToLogs($selenium);

            $selenium->waitFor(function () use ($selenium) {
                return is_null($selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Solving is in process...")]'), 0));
            }, 120);

            $this->savePageToLogs($selenium);

            if ($this->http->FindSingleNode('//a[contains(text(), "Solving is in process...")]')) {
                $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Proxy IP is banned by target service") or contains(text(), "Could not connect to proxy related to the task")]'), 20);
                $this->savePageToLogs($selenium);
            }

            $this->logger->debug("click by btn");
            /*
            $captcha = $this->parseHCaptcha($selenium->http->currentUrl());

            if ($captcha === false) {
                return false;
            }

            $this->logger->debug("set captcha g-recaptcha-response");
            $selenium->driver->executeScript("document.querySelector('[name = \"g-recaptcha-response\"]').value = '{$captcha}';");
            $this->logger->debug("set captcha h-recaptcha-response");
            $this->savePageToLogs($selenium);
            $selenium->driver->executeScript("document.querySelector('[name = \"h-captcha-response\"]').value = '{$captcha}';");
            */
//            $selenium->driver->executeScript("loginCaptchaCallback('{$captcha}');");
            $button->click();

            $selenium->waitForElement(WebDriverBy::xpath("//a[contains(@href, 'logout')]"), 10);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->savePageToLogs($selenium);
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");
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
                throw new CheckRetryNeededException(3);
            }
        }

        return true;
    }
}
