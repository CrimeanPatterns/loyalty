<?php

// test

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAeromexico extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    private $grantAccess;
    private $headersIt = [
        "Accept"       => "*/*",
        "Referer"      => "https://www.aeromexico.com/en-us/manage-your-booking",
        "access_type"  => "client_credentials",
        "Content-Type" => "application/lance+json",
        "User-Agent"   => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7",
        "store"        => "us",
        "pos"          => "WEB",
        "newrelic"     => "eyJ2IjpbMCwxXSwiZCI6eyJ0eSI6IkJyb3dzZXIiLCJhYyI6IjExNDM0NTYiLCJhcCI6IjcxODIzNDU4MiIsImlkIjoiNmM5MjQyNjlkYzdlN2RhNyIsInRyIjoiZjYwOWY3ZTMxYTIwOThkZWIyNTY5YzIzZjkwODNjMzAiLCJ0aSI6MTY4OTMzMTE2NjIxNCwidGsiOiI2NjY4NiJ9fQ==",
    ];
    // for Itineraries

    public static function GetAccountChecker($accountInfo)
    {
        require_once __DIR__ . "/TAccountCheckerAeromexicoSelenium.php";

        return new TAccountCheckerAeromexicoSelenium();
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
//        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        $this->http->SetProxy($this->proxyReCaptchaIt7());

        $userAgentKey = "User-Agent";

        if (!isset($this->State[$userAgentKey]) || $this->attempt > 1) {
            $this->http->setRandomUserAgent(5, false, false);
            $agent = $this->http->getDefaultHeader("User-Agent");

            if (!empty($agent)) {
                $this->State[$userAgentKey] = $agent;
            }
        } else {
            $this->http->setUserAgent($this->State[$userAgentKey]);
        }
    }

    public function IsLoggedIn()
    {
        return false;
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://member.aeromexicorewards.com/", [], 20);
        $this->http->RetryCount = 2;

        if ($this->loginSuccessful() && !strstr($this->http->currentUrl(), 'login/auth')) {
            return true;
        }
        sleep(3);

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
//        if (!$this->http->GetURL("https://member.aeromexicorewards.com/login/auth?lang=en") && !$this->http->Response['code'] == 503) {
//            $this->http->GetURL("https://member.aeromexicorewards.com/login/auth?lang=en");
//        }

        /*
        if ($this->attempt > 0) {
        */
        $currentUrl = $this->selenium();
        /*
        }
        */

        $this->http->RetryCount = 1;
        $this->http->GetURL($currentUrl ?? "https://member.aeromexicorewards.com/login/auth?lang=en");
        $this->http->RetryCount = 2;

        if ($this->http->FindSingleNode('//h1[contains(text(), "Sorry, you have been blocked")]')) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(3, 0);
        }

        if (!$this->http->ParseForm("login")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->RetryCount = 2;

        return true;
    }

    public function selenium()
    {
        $this->logger->notice(__METHOD__);
        $currentUrl = null;
        $selenium = clone $this;
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
            $resolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($resolution);

            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            /*
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_94);
            */
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $selenium->seleniumOptions->userAgent = null;

//            $selenium->useCache();
            $selenium->usePacFile(false);
            $selenium->http->start();
            $selenium->http->saveScreenshots = true;
            $selenium->Start();

            try {
                $selenium->http->GetURL('https://member.aeromexicorewards.com/login/auth?lang=en');
            } catch (TimeOutException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath("//input[@name = 'j_username']"), 15);
            $this->savePageToLogs($selenium);

            try {
                if (!$login && $selenium->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'Checking your browser before accessing')] | //h2[contains(text(), 'Checking if the site connection is secure')]"), 0)) {
                    // save page to logs
                    $this->savePageToLogs($selenium);
                    $selenium->http->GetURL('https://member.aeromexicorewards.com/login/auth?lang=en');
                    $selenium->waitForElement(WebDriverBy::id("username"), 10);
                }
            } catch (StaleElementReferenceException | UnexpectedJavascriptException $e) {
                $this->logger->error("Exception: " . $e->getMessage());
            }
            sleep(2);
            // save page to logs
            $this->savePageToLogs($selenium);
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (!in_array($cookie['name'], [
                    'bm_sz',
                    '_abck',
                ])) {
                    continue;
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            $currentUrl = $selenium->http->currentUrl();
            $this->logger->debug("[Current URL]: {$currentUrl}");
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $currentUrl;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        //# We are unable to attend your request
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'We are unable to attend your request')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindPreg("/(We are unable to attend your request\, Please try again later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, at this time there is a system internal error, please try again later.
        if ($message = $this->http->FindSingleNode("//h3[contains(text(), 'at this time there is a system internal error')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Web server is returning an unknown error
        if ($message = $this->http->FindSingleNode("//h2[contains(text(), 'Web server is returning an unknown error')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //# Sorry, an internal server error occurred.
        if ($message = $this->http->FindPreg("/(Sorry, an internal server error occurred\.?)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# The service is temporarily unavailable. Please try again later.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The service is temporarily unavailable. Please try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // En mantenimiento
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'En mantenimiento')]|//p[contains(text(), 'En mantenimiento')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Maintenance
        if ($message = $this->http->FindSingleNode("//img[@src = '45521alerta_co.jpg']/@src")) {
            throw new CheckException("Estamos trabajando en el sitio con el fin de ofrecerte mejor una experiencia. A partir del 11 de mayo a las 19:00 hrs quedará restablecido el servicio", ACCOUNT_PROVIDER_ERROR);
        }

        if ($redirect = $this->http->FindSingleNode("//p[contains(text(), 'Redirecting to')]/a/@href")) {
            $this->http->GetURL($redirect);
        }

        // We are unable to attend your request, Please try again later.
        if ($message = $this->http->FindPreg("/(We are unable to attend your request\, Please try again later\.)/")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function parseQuestion()
    {
        $this->logger->notice(__METHOD__);
        $question = $this->http->FindSingleNode("//p[contains(text(), 'Para ingresar a tu cuenta es necesario ingresar el código de seguridad que hemos enviado a tu cuenta de correo') and not(contains(text(), 'null')) or contains(text(), 'In order to validate your Account, enter the security code previously sent to your e-mail address') and not(contains(text(), 'null'))]");

        if (!$question || !$this->http->ParseForm("login")) {
            return false;
        }
        $this->Question = str_replace('Enviaremos el correo electrónico desde "[email protected]". ', '', $question);
        $this->ErrorCode = ACCOUNT_QUESTION;
        $this->Step = "Question";

        return true;
    }

    public function ProcessStep($step)
    {
        $this->http->SetInputValue("j_username", $this->AccountFields['Login']);
        $this->http->SetInputValue("j_password", $this->AccountFields['Pass']);
        $this->http->SetInputValue("j_otpCode", $this->Answers[$this->Question]);
        unset($this->Answers[$this->Question]);
        $this->http->PostForm();

        if ($error = $this->http->FindSingleNode('//div[@id = "messageException" or contains(@class, "alert-danger")]/p[contains(text(), "Código de verificación inválido.")]')) {
            $this->AskQuestion($this->Question, $error, 'Question');

            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "alert-danger")]/p[contains(text(), "At the moment we can not help you. Try again later.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function Login()
    {
        $this->http->RetryCount = 0;

        if (!$this->http->PostForm()) {
            if ($this->http->Response['code'] == 429) {
                throw new CheckRetryNeededException();
            }

            return $this->checkErrors();
        }
        $this->http->RetryCount = 2;

        $this->markProxySuccessful();

        if ($this->parseQuestion()) {
            return false;
        }

        //# Your information is complete, please verify your data, if it's correct press "Update" to continue
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Your information is complete, please verify your data')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Please provide your address, email and phone number
        if ($message = $this->http->FindSingleNode("//li[contains(text(), 'Please provide your address, email and phone number')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Keep your information updated
        if ($this->http->FindSingleNode("//*[contains(text(), 'Keep your information updated')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'Enjoy the best Club Premier benefits by keeping your information updated')]")
            || $this->http->FindSingleNode('//*[contains(text(), "We\'ve got your information. In order to complete your process please write your: Address & Phone")]')
            || $this->http->FindSingleNode("//*[contains(text(), 'Mantén Actualizados tus Datos')]")
            || $this->http->FindSingleNode("//*[contains(text(), 'un Socio Club Premier manteniendo actualizados tus datos')]")
            || $this->http->currentUrl() == 'https://member.aeromexicorewards.com/individual/mis-datos') {
            throw new CheckException("Aeromexico (Club Premier) website is asking you to update your profile, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
        } /*checked*/

        if ($this->loginSuccessful()) {
            return true;
        }

        // temporarily (bug in site, errors are not visible on the site)
        if (!$this->http->FindSingleNode("//div[@class = 'login_message']")
            && ($this->http->currentUrl() == 'https://member.aeromexicorewards.com/entrar?login_error=1')) {
            $this->http->GetURL("https://member.aeromexicorewards.com/login/auth");
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "danger alert-general") and not(contains(@class, "none"))]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Incorrect Aeroméxico Rewards Account/e-mail address or password.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            // Tu cuenta fue bloqueada porque alcanzó el número máximo de solicitudes de login, por favor intenta de nuevo en 15 min.
            // You exceeded the maximum number of allowed attempts. Your account has been blocked for 20 minutes, please try again later.
            if (
                strstr($message, 'Tu cuenta fue bloqueada porque alcanzó el número máximo de solicitudes de login')
                || strstr($message, 'You exceeded the maximum number of allowed attempts. Your account has been blocked for')
                || strstr($message, 'Excediste el máximo de intentos permitidos. Tu cuenta ha sido bloqueada')
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }

            $this->DebugInfo = $message;

            return false;
        }

        //# Password is not correct
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The account number and/or password are incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Password is not correct
        if ($message = $this->http->FindPreg("/(Password is not correct\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Club Premier's account is invalid
        if ($message = $this->http->FindPreg("/(Club Premier's account is invalid\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Account number and password is required
        if ($message = $this->http->FindPreg("/(Account number and password is required\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        //# Club Premier's account or password is invalid.
        if ($message = $this->http->FindPreg("/(Club Premier\'s account or password is invalid\.)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The Club Premier account or password provided is incorrect.
        if ($message = $this->http->FindSingleNode("(//h5[contains(text(), 'The Club Premier account or password provided is incorrect.')])[1]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Incorrect Club Premier Account/e-mail address or password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Incorrect Club Premier Account/e-mail address or password.') or contains(text(), 'El número de cuenta y/o contraseña son incorrectos.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // La cuenta Club Premier/correo electrónico o contraseña proporcionada es incorrecta
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'La cuenta Club Premier/correo electrónico o contraseña proporcionada es incorrecta')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Ha ocurrido un error, intente nuevamente más tarde.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        //# Status pending
        if ($message = $this->http->FindPreg("/(We require additional information with regard to your account as it has been temporarly suspended\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // Acceso denegado
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Action required')]/following-sibling::p[@align = 'justify']")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // At the moment your access is blocked.
        if ($message = $this->http->FindPreg("/(?:At the moment your access is blocked\.|Por el momento tu acceso se encuentra bloqueado\.)/")) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        // no errors, no auth
        if (in_array($this->AccountFields['Login'], [
            '518477708',
            '202134482',
            '773010202',
            '866539703',
            '900850124',
            '805385762',
            '816037527',
            '742722200',
            '574650404',
            '052246600',
        ])) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        // You already have an active session. Log out on your other device or try again later.
        if (
            $message = $this->http->FindSingleNode("//div[@id = 'modalNotLogin' and @aria-hidden = 'false']//p[contains(text(), 'You already have an active session. Log out on your other device or try again later.')]")
        ) {
            throw new CheckRetryNeededException(2, 15, $message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "Restricted access")]/following-sibling::div[contains(text(), "You are currently blocked from accessing")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        /*
        No ha sido posible iniciar sesión

        Ya tienes una sesión activa. Cierra sesión en tu otro dispositivo o intenta de nuevo más tarde.
        */

        return $this->checkErrors();
    }

    public function Parse()
    {
        // switch to English version
        $language = Html::cleanXMLValue($this->http->FindSingleNode("
            //div[contains(@class, 'select-lang')]/a/span
            | //div[contains(@class, 'language')]//option[@selected]
        "));
        $this->logger->notice(">>> Language: " . $language);

        if (!in_array($language, ['Inglés', 'English']) && !$this->http->FindSingleNode("//p[contains(text(), 'Balance:')]")) {
            $this->logger->notice(">>> Switch to English version");
            $this->http->GetURL("https://member.aeromexicorewards.com/?lang=en");
        }

        // Balance - My balance is ... Premier Points
        $this->SetBalance(
            $this->http->FindSingleNode("//div[@class='userinfo']//p//text()[contains(., 'Balance:')]", null, true, "/:\s*([\-\d\.\,]+)\s*Premier/ims")
            ?? $this->http->FindSingleNode("//div[@class='userinfo']//span[contains(text(), 'Balance')]/following-sibling::span[1]")
        );

        $this->http->PostURL('https://member.aeromexicorewards.com/homeServices/loadIndex', []);
        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//div[@class = "name"]', null, true, "/>?(.+)/ims")
            ?? $this->http->FindSingleNode("//div[@class='userinfo']//span[contains(text(), 'Hello')]/following-sibling::span[1]")
        ));
        // Membership Number
        $this->SetProperty("Number",
            $this->http->FindSingleNode("//div[@class = 'account']/text()/following-sibling::span")
            ?? $this->http->FindSingleNode("//div[@class='userinfo']//span[contains(text(), 'Account number:')]/following-sibling::strong[1]")
        );

        // Level - in english version, level not showing
        $levelEnglish = $this->http->FindSingleNode("//div[@class = 'card-user']//img[@class = 'img-fluid']/@src");

        $this->http->GetURL("https://member.aeromexicorewards.com/individual/movimientos-por-fecha");

        // Balance - Saldo Actual
        $this->SetBalance($this->http->FindSingleNode("//div[contains(text(), 'Saldo Actual')]/following-sibling::div", null, true, "/\s*([\-\d\.\,]+)\s*Puntos/ims"));

        // Level - in english version, level not showing
//        $this->http->GetURL("https://member.aeromexicorewards.com/?lang=es");
//        $level = $this->http->FindSingleNode("//div[@class = 'card-img']//img[@class = 'img-responsive']/@src");
//        if (!isset($level)) {
//            $this->http->Log("Try english version -> {$levelEnglish}");
        $level = $levelEnglish;
//        }
        if ($levelEnglish) {
            $level = basename($level);
            $this->logger->debug(">>> Level " . $level);

            switch ($level) {
                case 'bannerVisa.jpg':
                // may be Clasico
                case 'AeromexicoVisaSignature.png':
                case 'AeromexicoVisaCard.png':
                case 'cp-card-test.png':
                case 'cp-one.png':
                    $this->SetProperty("Level", "Clasico");

                    break;

                case 'AeromexicoGold.png':
                case 'cp-card-test-oro.png':
                    $this->SetProperty("Level", "Gold");

                    break;

                case 'AeromexicoPlatino.png':
                case 'cp-card-test-platino.png':
                    $this->SetProperty("Level", "Platinum");

                    break;

                case 'AeromexicoTitanio.png':
                case 'cp-card-test-titanio.png':
                    $this->SetProperty("Level", "Titanium");

                    break;

                default:
//                    if (!empty($status)) {
                    $this->sendNotification("aeromexico: newStatus: $level");
//                    }
            }// switch ($status)
        }// if ($level = $this->http->FindSingleNode("//img[@class = 'img-card']/@src"))

        // Expiration Date  // refs #12900
        /* https://awardwallet.com/blog/do-aeromexico-club-premier-points-expire/
        $this->logger->info('Expiration date', ['Header' => 3]);

        if ($exp = $this->http->FindSingleNode("//strong[
                    contains(text(), 'they will expire on')
                    or contains(text(), 'éstos vencerán el')
                ]/span[normalize-space(text()) != '01/01/0001']
                | //div[
                    contains(text(), 'Fecha de Expiración')
                ]/following-sibling::div[normalize-space(text()) != '01/01/0001']
            ")
        ) {
            $exp = $this->ModifyDateFormat($exp, "/", true);

            if ($exp = strtotime($exp)) {
                $this->SetExpirationDate($exp);
            }
            $this->parseLastActivity();
        }// if ($exp = $this->http->FindSingleNode("//strong[contains(text(), 'they will expire on') or contains(text(), 'éstos vencerán el')]/span"))
        else {
            if (!$this->http->ParseForm(null, "//form[contains(@action, 'generateAccountStatement')]")) {
                return;
            }
            $this->http->SetInputValue("movementsFrom_year", date("Y") - 1);
            $this->http->FormURL = 'https://member.aeromexicorewards.com/accountStatement/searchDataAccount';
            $this->http->PostForm();
            $this->parseLastActivity(true);
        }
        */
    }

    public function parseLastActivity($findExpDate = false)
    {
        $this->logger->notice(__METHOD__);
        $nodes = $this->http->XPath->query("//table[@id = 'transactionPersonal']//tr[td]");
        $this->logger->debug("Total {$nodes->length} history nodes were found");

        foreach ($nodes as $node) {
            $premierPoints = $this->http->FindSingleNode("td[8]", $node);
            $activityDate = $this->http->FindSingleNode("td[1]", $node);

            if ($premierPoints > 0) {
                $this->SetProperty("LastActivity", $activityDate);
                $activityDate = $this->ModifyDateFormat($activityDate, "/", true);

                if ($findExpDate && ($exp = strtotime($activityDate))) {
                    $this->SetExpirationDate(strtotime("+2 year", $exp));
                }

                break;
            }// if ($premierPoints > 0)
        }// foreach ($nodes as $node)
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity Date"           => "PostingDate",
            "Date Accrual/Redemption" => "Info.Date",
            "Partners"                => "Info",
            "Description"             => "Description",
            "Flight"                  => "Info",
            "Class"                   => "Info",
            "Route"                   => "Info",
            "Premier Points"          => "Miles",
            "Bonus Points"            => "Bonus",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = $this->getTime();

        $this->http->GetURL('https://member.aeromexicorewards.com/individual/movimientos-por-fecha');

        $timespans = $this->http->FindNodes('//select[@name="movementsFrom_year"]/option/@value | //div[@class = "start-date"]//select[@id = "mbr-stm-dat-sel-yer-srt"]/option/@value');
        $timespans = array_values(array_unique($timespans));
        rsort($timespans);
        $timespansCount = count($timespans);

        $stop = false;

        $headers = [
            "Accept"          => "application/json",
            "Accept-Encoding" => "gzip, deflate, br",
            "Content-Type"    => "application/json;charset=utf-8",
        ];

        for ($i = 0; $i < $timespansCount && $i < 30 && !$stop; $i++) {
            $params = [
                'startDate'     => "01/01/" . $timespans[$i],
                'endDate'       => date('Y') == $timespans[$i] ? date('t/m/' . $timespans[$i]) : date('t/12/' . $timespans[$i]),
            ];

            if (isset($startDate) && $params['endDate'] < $startDate) {
                $this->logger->notice("break at date ($startDate)");
                $stop = true;

                continue;
            }

            $this->http->PostURL('https://member.aeromexicorewards.com/accountStatement/getTransactions', json_encode($params), $headers);
            $response = $this->http->JsonLog($this->http->FindPreg("/(\{.+)/"));

            if (isset($response->recordsTotal) && $response->recordsTotal > 0) {
                $this->sendNotification("history found");
            }

            if ($lowest = $this->http->FindSingleNode("//ul/li[contains(text(), 'The minimum search date is')]", null, true, '/The minimum search date is (\w+\s+\d+)/ims')) {
                $this->logger->debug('Lowest date set to ' . $lowest);
                $params['endDate'] = date('t/m/', strtotime($lowest));
                $this->http->PostURL('https://member.aeromexicorewards.com/accountStatement/getTransactions', json_encode($params), $headers);
                $response = $this->http->JsonLog($this->http->FindPreg("/(\{.+)/"));

                if (isset($response->recordsTotal) && $response->recordsTotal > 0) {
                    $this->sendNotification("history found");
                }
            }
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParseHistoryPage($startIndex, $startDate, $stop));
        }

        $this->callLogout();

        $this->getTime($startTimer);

        return $result;
    }

    public function ParseHistoryPage($startIndex, $startDate, &$stop)
    {
        $result = [];
        $nodes = $this->http->XPath->query("//table[@id='transactionPersonal']//tr[td]");
        $this->logger->debug("Total {$nodes->length} history items were found");

        for ($i = 0; $i < $nodes->length && !$stop; $i++) {
            $node = $nodes->item($i);
            $dateStr = $this->http->FindSingleNode("td[1]", $node);
            $date = explode('/', $dateStr);

            if (count($date) < 3) {
                $this->logger->notice('FAIL DATE: ' . var_export($date, true));

                continue;
            }
            [$day, $month, $year] = $date;
            $postDate = mktime(0, 0, 0, $month, $day, $year);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $stop = true;

                continue;
            }
            $result[$startIndex]['Activity Date'] = $postDate;
            [$accrualDay, $accrualMonth, $accrualYear] = explode('/', $this->http->FindSingleNode("td[2]", $node));
            $result[$startIndex]['Date Accrual/Redemption'] = strtotime("{$accrualYear}-{$accrualMonth}-{$accrualDay}");
            $result[$startIndex]['Partners'] = $this->http->FindSingleNode("td[3]", $node);
            $result[$startIndex]['Description'] = $this->http->FindSingleNode("td[4]", $node);
            $result[$startIndex]['Flight'] = $this->http->FindSingleNode("td[5]", $node);
            $result[$startIndex]['Class'] = $this->http->FindSingleNode("td[6]", $node);
            $result[$startIndex]['Route'] = $this->http->FindSingleNode("td[7]", $node);

            if ($this->http->FindPreg('/Bonus/ims', false, $result[$startIndex]['Description'])) {
                $result[$startIndex]['Bonus Points'] = $this->http->FindSingleNode("td[8]", $node);
            } else {
                $result[$startIndex]['Premier Points'] = $this->http->FindSingleNode("td[8]", $node);
            }
            $startIndex++;
        }

        return $result;
    }

    public function combineHistoryBonusToMiles()
    {
        return true;
    }

    public function callLogout()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("Log out from member.aeromexicorewards.com", ['Header' => 2]);
        $this->http->GetURL('https://member.aeromexicorewards.com/j_spring_security_logout?spring-security-redirect=https://www.aeromexicorewards.com/us/welcome/');
//        $this->http->GetURL("https://member.aeromexicorewards.com/salir");
    }

    // Itineraries

    public function authItineraries()
    {
        $this->logger->info("Login in aeromexico.com", ['Header' => 3]);
        $this->http->RetryCount = 0;

        $this->http->setCookie("_abck", "85D4A49A8311337046C573EDD0738652~0~YAAQFIp4aMO4gHCJAQAALltIcwqBx62vgGeeeGqyMBGbHX9LkbfcAnZUrY+SMP58wmTHbPp/fiLRU2bmX1VYjL8hUVsV5bvjUM0vPoEBJBo+vg6BXWz2rUDc8tWusHDkk4v2FgWmZ0fe1TsdOFtxij5Jm7dnPczm32Jl+CX4G5ZID3bC31EGyVIpgq3TrErWn+0oU4rFJMc8tXNsnUYMJFAKPPoZyxUp+skdm8OPprFxXEfzgrIW3+EVwJGIrBs1xA0++5VUovMXK35vVRxog3/afdMFWfiIbWPb06YT0qK7c2zfS52ZbHgswPZtOfvAqTu9f1aOuuzZe7GIadaoLYg9HHegx0QCiHqQj9BT7RHm/gzHAn+KozzyoJUCxBIBaYTmyF1pUq4xvYgR9y+yMMmZ4VoeS8tVGIxav/s=~-1~-1~1689858529", ".aeromexico.com"); // todo: sensor_data workaround

        $headers = [
            "User-Agent" => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_2) AppleWebKit/604.4.7 (KHTML, like Gecko) Version/11.0.2 Safari/604.4.7",
        ];
        $this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking", $headers, 20);
        // Operation timed out after
        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            || $this->http->Response['code'] == 403
        ) {
            // it often helps
//            $this->http->SetProxy($this->proxyReCaptcha());
            $this->setProxyGoProxies(); //TODO: if selenium parser// $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking", $headers, 20);
        }

        if (
            strstr($this->http->Error, 'Network error 28 - Operation timed out after ')
            || $this->http->Response['code'] == 403
        ) {
            $this->setProxyGoProxies();
            $this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking", $headers, 20);
        }

        if ($this->http->Response['code'] != 200) {
            return false;
        }
        $headers = [
            "Authorization" => "Basic Nmc2OVJKaXpPR1F2ckdaOXVBYURORFNiN3lKSGtSa0U6NzFwZlZWQ3ZQZ3ZqNWVoNA==",
        ];
        $this->http->GetURL("https://www.aeromexico.com/api/v1/am-grant/grantAccess", $this->headersIt + $headers);

        // it often helps
        if ($this->http->Response['code'] == 403) {
            sleep(5);
            $this->setProxyGoProxies(); //TODO: if selenium parser// $this->http->SetProxy($this->proxyReCaptcha());
            $this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking", $headers, 20);
            $this->http->GetURL("https://www.aeromexico.com/api/v1/am-grant/grantAccess", $this->headersIt + $headers);
        }

        $response = $this->http->JsonLog();
        $this->grantAccess = $response->grantAccess ?? null;

        if (!$this->grantAccess) {
            return false;
        }
        $key = "6Ld2XeUUAAAAAGZIrQZOT-sU5ocvBKVAb5axS3iw"; // TODO
        $tokenReCaptcha = $this->parseCaptcha($key);

        if ($tokenReCaptcha === false) {
            return false;
        }
        $this->http->setDefaultHeader("Authorization", "Bearer {$this->grantAccess}");
        $headers = [
            "Content-Type" => "application/lance+json",
            "token"        => $tokenReCaptcha,
        ];
        $data = [
            "_meta"             => [
                "class" => "UserProfileSignInRequest",
            ],
            "clubPremierNumber" => "",
            "email"             => "",
            "password"          => $this->AccountFields['Pass'],
        ];

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            $data['clubPremierNumber'] = $this->AccountFields['Login'];
        } else {
            $data['email'] = $this->AccountFields['Login'];
        }

        $this->http->PostURL("https://www.aeromexico.com/api/v2/profile/user-profile-sign-in", json_encode($data), $this->headersIt + $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);
        $userToken = $response->userToken ?? null;

        if (!$userToken) {
            return false;
        }
        // loginSuccessful for ParseItineraries
        if ($this->loginSuccessfulIt($userToken)) {
            $this->State['userToken'] = $userToken;

            return true;
        }

        return false;
    }

    public function IsLoggedInIt()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("IsLoggedIn aeromexico.com", ['Header' => 3]);

        if (!isset($this->State['userToken'])) {
            return false;
        }

        if ($this->loginSuccessfulIt($this->State['userToken'])) {
            return true;
        }

        return false;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);

        //$this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking");

        if (!$this->IsLoggedInIt()) {
            if (isset($this->http->Response['code']) && $this->http->Response['code'] == 403) {
                // it often helps
                $this->http->SetProxy($this->proxyReCaptcha());
            }

            if (!$this->IsLoggedInIt() && !$this->authItineraries()) {
                $this->logger->error("Error in authItineraries");

                return [];
            }
        }

        if ($this->http->FindPreg('/"myTrips":\{"_meta":\{"class":"MyTrips"\},"_collection":\[\]\}\},/')) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $this->logger->info("Find itineraries", ['Header' => 3]);
        $response = $this->http->JsonLog(null, 2, false, 'bookedLegCollection');
        $myTrips = $response->userProfile->myTrips->_collection ?? [];
        $this->logger->debug("Total " . count($myTrips) . " itineraries were found");

        if (
            isset($response->userProfile->myTrips->_collection)
            && $response->userProfile->myTrips->_collection === []
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $this->sendNotification("itineraries were found // RR");

        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "access_type"     => "client_credentials",
            "Authorization"   => "Basic Rlo2YTBHQU9BdlFsRWliV2JQNUJLYUdxYmdWMXdFejk6WGdWbW8yc25KVmFmRkxnSQ==",
            "Content-Type"    => "application/x-www-form-urlencoded",
        ];
        $this->http->GetURL("https://aeromexico.com/api/v1/am-grant/grantAccess", $headers);
        $response = $this->http->JsonLog();
        $grantAccess = $response->grantAccess ?? null;

        if (!$grantAccess) {
            return [];
        }
        $pastItCount = 0;

        foreach ($myTrips as $trip) {
            if (empty($trip->reservationCode)) {
                $this->logger->error("empty reservationCode");

                return [];
            }

            $bookedList = $trip->bookedLegCollection->_collection ?? [];

            if (!$this->detectNewReservation($bookedList)) {
                $pastItCount++;

                continue;
            }

            $this->ParseFlights($grantAccess, $trip->lastName, $trip->reservationCode);
        }// foreach ($myTrips as $trip)

        if (count($myTrips) === $pastItCount) {
            $this->itinerariesMaster->setNoItineraries(true);
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Reservation or ticket number",
                "Type"     => "string",
                "Size"     => 13,
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

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aeromexico.com/en-us/manage-your-booking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "access_type"     => "client_credentials",
            "Authorization"   => "Basic Rlo2YTBHQU9BdlFsRWliV2JQNUJLYUdxYmdWMXdFejk6WGdWbW8yc25KVmFmRkxnSQ==",
            "Content-Type"    => "application/x-www-form-urlencoded",
        ];
        $this->http->GetURL("https://www.aeromexico.com/api/v1/am-grant/grantAccess", $headers);
        $response = $this->http->JsonLog();
        $grantAccess = $response->grantAccess ?? null;

        if (!$grantAccess) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }
        $it = $this->ParseFlights($grantAccess, $arFields['LastName'], $arFields['ConfNo']);

        if (is_string($it)) {
            if ($it == 'HTTP code: 400 , PNR_NOT_FOUND_WEB' || $it == 'HTTP code: 400 , NAME_NOT_FOUND') {
                return 'Sorry, we are unable to find your booking';
            }

            if ($it == "HTTP code: 400 , FLIGHT_SEGMENTS_NOT_LONGER_AVAILABLE_WEB") {
                return "Your reservation was modified. Please contact our Call Center at 1-800-237-6639.";
            }
        }
        $this->http->RetryCount = 2;

        return null;
    }

    // parseCaptcha for ParseItineraries
    protected function parseCaptcha($key)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"   => $this->http->currentUrl(),
            "proxy"     => $this->http->GetProxy(),
            "invisible" => 1,
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, false);
    }

    private function loginSuccessful()
    {
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, '/salir')]/@href")) {
            return true;
        }

        return false;
    }

    private function loginSuccessfulIt($token)
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "user-token" => $token,
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.aeromexico.com/api/v2/profile/user-profile-trips", $this->headersIt + $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog(null, 0);
        $number = $response->userProfile->ffNumber ?? null;

        if (isset($number)) {
            return true;
        }

        return false;
    }

    private function detectNewReservation($bookedList)
    {
        if ($this->ParsePastIts) {
            return true;
        }

        foreach ($bookedList as $it) {
            $result = strtotime($it->scheduledDepartureTime) < strtotime('-1 day');

            if (!$result) {
                return true;
            }
        }

        $this->logger->notice("skip old itinerary");

        return false;
    }

    private function ParseFlights($grantAccess, $lastName, $reservationCode)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $headers = [
            "Accept"          => "*/*",
            "Accept-Encoding" => "gzip, deflate, br",
            "access_type"     => "client_credentials",
            "Authorization"   => "Bearer {$grantAccess}",
            "Content-Type"    => "application/x-www-form-urlencoded",
            "X-AM-User-Auth"  => "PNR name=\"{$lastName}\",reservation=\"{$reservationCode}\"",
        ];
        $this->logger->info("Details for Itinerary #{$reservationCode}", ['Header' => 4]);
        $this->http->GetURL("https://www.aeromexico.com/api/v2/manage/pnr?store=us&pos=WEB&language=en", $headers); //todo: need to check this request
        $this->http->RetryCount = 2;
        $trip = $this->http->JsonLog(null, 2);
        $seats = [];
        $meals = [];

        if (!isset($trip->_collection[0])) {
            if (isset($trip->msg)) {
                return $trip->msg;
            }
        }
        $pnr = $trip->_collection[0];

        if (count($trip->_collection) > 1) {
            $this->sendNotification('_collection > 1 // MI');
        }
        $this->logger->info("Parse Itinerary #{$pnr->pnr}", ['Header' => 3]);
        $f = $this->itinerariesMaster->add()->flight();
        $f->general()
            ->confirmation($pnr->pnr, "Confirmed #");
        //->status($trip->pnrStatus);
        if (isset($pnr->creationDate)) {
            $f->general()->date(strtotime($pnr->creationDate));
        }

        $accounts = [];

        foreach ($pnr->cart->travelerInfo->_collection ?? [] as $key => $traveler) {
            $f->issued()->ticket($traveler->ticketNumber, false);
            $f->general()->traveller(beautifulName($traveler->displayName));

            if (isset($traveler->frequentFlyerNumber)) {
                $accounts[] = $traveler->frequentFlyerNumber;
            }
            // meals
            foreach ($traveler->mealSelected ?? [] as $mealSelected) {
                foreach ($mealSelected->_collection ?? [] as $meal) {
                    $mealSegmentCode = $meal->mealSegmentCode ?? null;

                    if (!$mealSegmentCode) {
                        $this->sendNotification('#19985 check mealSegmentCode');

                        continue;
                    }
                    $meals[$mealSegmentCode][] = $meal->mealName ?? null;
                }
            }
            // seats
            foreach ($traveler->segmentChoices->_collection ?? [] as $k => $seat) {
                $segmentCode = $seat->segmentCode ?? null;

                if (!$segmentCode) {
                    $this->sendNotification('#19985 check segmentCode');

                    continue;
                }
                $seats[$segmentCode][] = $seat->seat->code ?? null;
            }
        }
        $f->program()->accounts(array_unique($accounts), false);

        $bookedList = $pnr->legs->_collection ?? [];

        foreach ($bookedList as $k => $flight) {
            $flightsSegments = $flight->segments->_collection ?? [];

            foreach ($flightsSegments as $key => $flightSegment) {
                $s = $f->addSegment();
                $s->departure()
                    ->code($flightSegment->segment->departureAirport)
                    ->date2($flightSegment->segment->departureDateTime);
                $s->arrival()
                    ->code($flightSegment->segment->arrivalAirport)
                    ->date2($flightSegment->segment->arrivalDateTime);
                $s->airline()
                    ->name($flightSegment->segment->operatingCarrier)
                    ->number($flightSegment->segment->operatingFlightCode);
                $s->extra()
                    ->aircraft($flightSegment->segment->aircraftType, true, false)
                    ->bookingCode($flightSegment->segment->bookingClass, false, true);

                if ($flightSegment->segment->aircraftType === 'TRS') {
                    $this->sendNotification("check train // ZM");
                }
                // cabin
                if (isset($flightSegment->segment->cabin)) {
                    $s->extra()->cabin($flightSegment->segment->cabin);
                }
                // Stops
                if (!empty($flightSegment->segment->stops)) {
                    $this->sendNotification('check stops // MI');
                } elseif (array_key_exists('stops', $flightSegment->segment)) {
                    $s->extra()->stops(0);
                }

                $segmentCode = $flightSegment->segment->segmentCode ?? null;

                if (!$segmentCode) {
                    $this->sendNotification('#19985 check segmentCode');
                }
                // seats
                if (isset($seats[$segmentCode]) && !empty(array_filter($seats[$segmentCode]))) {
                    $s->extra()->seats(array_filter($seats[$segmentCode]));
                }
                // meals
                if (isset($meals[$segmentCode]) && !empty(array_filter($meals[$segmentCode]))) {
                    $s->extra()->meals(array_filter($meals[$segmentCode]));
                }

                $flightDurationInMinutes = $flightSegment->segment->flightDurationInMinutes ?? null;

                if ($flightDurationInMinutes) {
                    $h = floor($flightDurationInMinutes / 60);
                    $m = $flightDurationInMinutes % 60;
                    $s->extra()->duration("{$h}h {$m}m");
                }
            }// foreach ($flightsSegments as $flightSegment)
        }// foreach ($bookedList as $flight)

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);

        return true;
    }
}
