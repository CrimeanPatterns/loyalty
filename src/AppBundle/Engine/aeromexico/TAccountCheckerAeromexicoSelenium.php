<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerAeromexicoSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;

    private $aeromexico;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;

        $this->UseSelenium();

        $resolutions = [
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        /*
        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
        */
        if ($this->attempt == 0) {
            $this->useFirefox();
            $this->setKeepProfile(true);
        } else {
//            $this->useFirefoxPlaywright();
            $this->useGoogleChrome(SeleniumFinderRequest::CHROME_94);
        }

        $this->http->SetProxy($this->proxyDOP(['tor1']));
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->usePacFile(false);
        $this->http->saveScreenshots = true;
    }

    public function LoadLoginForm()
    {
        $this->driver->manage()->window()->maximize();
        $this->http->GetURL('https://member.aeromexicorewards.com/login/auth?lang=en');

        $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_username"] | //a[contains(@href, "/salir")] | //input[@value = \'Verify you are human\'] | //div[@id = \'turnstile-wrapper\']//iframe | //div[contains(@class, "cf-turnstile-wrapper")] | //div[contains(@style, "margin: 0px; padding: 0px;")]'), 30);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouseSettings($this)) {
            $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_username"] | //a[contains(@href, "/salir")]'), 30);
            $this->saveResponse();
        }

        if ($cancelBtn = $this->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Cancel")]'), 0)) {
            $cancelBtn->click();
            sleep(5);
            $this->saveResponse();
        }

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_username"]'), 0);
        $pwd = $this->waitForElement(WebDriverBy::xpath('//input[@name = "j_password"]'), 0);

        if (!isset($login, $pwd)) {
            $this->saveResponse();

            if ($this->loginSuccessful()) {
                return true;
            }

            if ($message = $this->http->FindSingleNode('//span[contains(text(), "Web server is down")]')) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $login->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
        $pwd->sendKeys($this->AccountFields['Pass']);

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btnSubmit" and not(@disabled)]'), 0);
        $this->saveResponse();

        if (!$btn) {
            if ($message = $this->http->FindSingleNode('//div[contains(@class, "invalid-feedback")]/span')) {
                $this->logger->error("[Error]: {$message}");

                if (
                    $message == 'It is not a valid data'
                ) {
                    throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
                }

                $this->DebugInfo = $message;
            }

            return false;
        }

        $btn->click();

        return true;
    }

    private function clickCloudFlareCheckboxByMouseSettings($selenium) {
        return $this->clickCloudFlareCheckboxByMouse(
            $selenium,
            '//div[contains(@class, "cf-turnstile-wrapper")] | //div[contains(@style, "margin: 0px; padding: 0px;")]',
            32,
            32
        );
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'In order to validate your Account, enter the security code previously sent to your e-mail address')] | //a[contains(@href, '/salir')] | //div[contains(@class, 'danger alert-') and not(contains(@class, 'none'))]/p | //div[@id = 'modalNotLogin' and (@aria-hidden = 'false' or style=\"display: block;\")]//p[contains(text(), 'You already have an active session. Log out on your other device or try again later.')] | //input[@value = 'Verify you are human'] | //div[@id = 'turnstile-wrapper']//iframe | //div[contains(@class, \"cf-turnstile-wrapper\")] | //div[contains(@style, \"margin: 0px; padding: 0px;\")]"), 20);
        $this->saveResponse();

        if ($this->clickCloudFlareCheckboxByMouse($this)) {
            $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'In order to validate your Account, enter the security code previously sent to your e-mail address')] | //a[contains(@href, '/salir')] | //div[contains(@class, 'danger alert-') and not(contains(@class, 'none'))]/p | //div[@id = 'modalNotLogin' and (@aria-hidden = 'false' or style=\"display: block;\")]//p[contains(text(), 'You already have an active session. Log out on your other device or try again later.')]"), 20);
            $this->saveResponse();
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        if ($this->processSecurityCheckpoint()) {
            return false;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "danger alert-") and not(contains(@class, "none"))]/p')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message == 'Incorrect Aeroméxico Rewards Account/e-mail address or password.'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Ha ocurrido un error, intente nuevamente más tarde.'
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            // TODO: not lockout, refs #23900
            if ($message == 'You are currently blocked from accessing') {
                throw new CheckRetryNeededException(2, 0, $message, ACCOUNT_PROVIDER_ERROR);
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

        if ($message = $this->http->FindSingleNode("//div[@id = 'modalNotLogin' and (@aria-hidden = 'false' or contains(@class, 'show') and @style='display: block;')]//p[contains(text(), 'You already have an active session. Log out on your other device or try again later.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function processSecurityCheckpoint(): bool
    {
        $q = $this->waitForElement(WebDriverBy::xpath("//p[contains(text(), 'In order to validate your Account, enter the security code previously sent to your e-mail address')]"), 0);

        if (!$q) {
            $this->logger->error("Email not found");

            return false;
        }

        $question = $q->getText();

        if (!isset($this->Answers[$question])) {
            $this->holdSession();
            $this->AskQuestion($question, null, 'Question');

            return true;
        }

        $answer = $this->Answers[$question];
        unset($this->Answers[$question]);

        $codeInput = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'code-security']"), 0);
        $login = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'j_username']"), 0);
        $password = $this->waitForElement(WebDriverBy::xpath("//input[@name = 'j_password']"), 0);
        $this->saveResponse();

        if (!isset($codeInput, $login, $password)) {
            $this->saveResponse();

            return false;
        }

        $codeInput->clear();
        $codeInput->sendKeys($answer);
        $login->sendKeys(str_replace(' ', '', $this->AccountFields['Login']));
        $password->sendKeys($this->AccountFields['Pass']);
        $this->saveResponse();

        $btn = $this->waitForElement(WebDriverBy::xpath('//button[@id = "btnSubmit"]'), 0);

        if (!$btn) {
            return false;
        }

        $btn->click();

        sleep(5);

        $this->waitForElement(WebDriverBy::xpath("
            //a[contains(@href, '/salir')]
            | //div[contains(@class, 'danger alert-') and not(contains(@class, 'none'))]/p
         "), 5);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'danger alert-') and not(contains(@class, 'none'))]/p")) {
            $this->logger->error("[Error]: {$message}");
            $this->holdSession();

            $this->AskQuestion($question, $message, 'Question');

            return false;
        }

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == 'Question') {
            $this->saveResponse();

            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function Parse()
    {
        // switch to English version
        $language = Html::cleanXMLValue($this->http->FindSingleNode("
            //div[contains(@class, 'select-lang')]/a/span
            | //div[contains(@class, 'container')]//div[contains(@class, 'language')]//option[@selected]
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

        // Name
        $this->SetProperty("Name", beautifulName(
            $this->http->FindSingleNode('//div[contains(@class, "row home-tutorial")]//div[@class = "name"]', null, true, "/>?(.+)/ims")
            ?? $this->http->FindSingleNode("//div[@class='userinfo']//span[contains(text(), 'Hello')]/following-sibling::span[1]")
        ));
        // Membership Number
        $this->SetProperty("Number",
            $this->http->FindSingleNode("//div[@class = 'account']/text()/following-sibling::span")
            ?? $this->http->FindSingleNode("//div[@class='userinfo']//span[contains(text(), 'Account number:')]/following-sibling::strong[1]")
        );

        // Level - in english version, level not showing
        $levelEnglish = $this->http->FindSingleNode("//div[contains(@class, \"row home-tutorial\")]//div[@class = 'card-user']//img[@class = 'img-fluid']/@src");

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
//            $this->http->FormURL = 'https://member.aeromexicorewards.com/accountStatement/searchDataAccount';
//            $this->http->PostForm();
//            $this->parseLastActivity(true);
        }
        */
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://www.aeromexico.com/en-us/manage-your-booking");
        sleep(5);
        $this->saveResponse();

        $this->getAeromexico();
        $this->aeromexico->ParseItineraries();
    }

    protected function getAeromexico()
    {
        $this->logger->notice(__METHOD__);

        if (!isset($this->aeromexico)) {
            $this->aeromexico = new TAccountCheckerAeromexico();
            $this->aeromexico->http = new HttpBrowser("none", new CurlDriver());

            $this->aeromexico->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->aeromexico->http);
            $this->aeromexico->AccountFields = $this->AccountFields;
            $this->aeromexico->itinerariesMaster = $this->itinerariesMaster;
            $this->aeromexico->HistoryStartDate = $this->HistoryStartDate;
            $this->aeromexico->historyStartDates = $this->historyStartDates;
            $this->aeromexico->http->LogHeaders = $this->http->LogHeaders;
            $this->aeromexico->ParseIts = $this->ParseIts;
            $this->aeromexico->ParsePastIts = $this->ParsePastIts;
            $this->aeromexico->WantHistory = $this->WantHistory;
            $this->aeromexico->WantFiles = $this->WantFiles;
            $this->aeromexico->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            $this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->aeromexico->http->setDefaultHeader($header, $value);
            }

            $this->aeromexico->globalLogger = $this->globalLogger;
            $this->aeromexico->logger = $this->logger;
            $this->aeromexico->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->aeromexico->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->aeromexico;
    }

    private function parseLastActivity($findExpDate = false)
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

    private function loginSuccessful()
    {
        // Access is allowed
        if ($this->http->FindNodes("//a[contains(@href, '/salir')]/@href")) {
            return true;
        }

        return false;
    }
}
