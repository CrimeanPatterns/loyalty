<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
require_once __DIR__ . '/../expedia/TAccountCheckerExpediaSelenium.php';

class TAccountCheckerCheapticketsSelenium extends TAccountCheckerExpediaSelenium
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    protected $hotels;
    private HttpBrowser $browser;
    public string $host = 'www.cheaptickets.com';
    public string $provider = 'cheaptickets';

    public function LoadLoginForm()
    {
        try {
            $this->http->GetURL("https://www.cheaptickets.com/login?ckoflag=0&uurl=qscr%3Dreds%26rurl%3D%252Fuser%252Faccount%253F&selc=0");
        } catch (Facebook\WebDriver\Exception\UnknownErrorException $e) {
            $this->http->GetURL("https://www.cheaptickets.com/login?ckoflag=0&uurl=qscr%3Dreds%26rurl%3D%252Fuser%252Faccount%253F&selc=0");
            $this->logger->error("Exception: " . $e->getMessage(), ['HtmlEncode' => true]);
        }


        $login = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormEmailInput']"), 5);
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@id = 'loginFormPasswordInput']"), 5);
        $loginButton = $this->waitForElement(WebDriverBy::xpath("//button[@id = 'loginFormSubmitButton']"), 0);
        $this->saveResponse();

        if (!$login || !$pass || !$loginButton) {
            $this->logger->error('Failed to find login button');
            if (
                $this->waitForElement(WebDriverBy::xpath("
                    //div[@aria-hidden='false']//iframe
                    | //iframe[@sandbox='allow-scripts']
                    | //*[contains(text(),'Something went wrong on our end. Please try again now, or come back')]
                    | //*[contains(text(),'re having some difficulties. Please give us a moment and try again.')]
                "), 0)
                || $this->http->FindSingleNode('//*[contains(text(), "This site can’t be reached") or contains(text(), "The connection has timed out") or contains(text(), "Access Denied")]', null, true, null, 0)
            ) {
                $this->markProxyAsInvalid();
                throw new CheckRetryNeededException(3);
            }

            return false;
        }

//        $login->sendKeys($this->AccountFields['Login']);
        $mover = new MouseMover($this->driver);
        $mover->logger = $this->logger;
        $mover->sendKeys($login, $this->AccountFields['Login'], 5);
        $pass->sendKeys($this->AccountFields['Pass']);

//        $loginButton->click();
        $this->driver->executeScript("document.getElementById('loginFormSubmitButton').click()");

        return $this->loginPreview();
    }

    /*
    public function Login()
    {

        $this->saveResponse();

        if ($this->loginSuccessful()) {
            return true;
        }
        return false;
    }
    */

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key =
            $this->http->FindSingleNode('//iframe[contains(@src, "-api.arkoselabs.com")]/@src', null, true, "/pkey=([^&]+)/")
            ?? $this->http->FindPreg('/pkey=([^&\\\]+)/')
        ;

        if (!$key) {
            return false;
        }

        if ($this->attempt == 1) {
            $postData = array_merge(
                [
                    "type"                     => "FunCaptchaTaskProxyless",
                    "websiteURL"               => $this->http->currentUrl(),
                    "funcaptchaApiJSSubdomain" => 'expedia-api.arkoselabs.com',
                    "websitePublicKey"         => $key,
                ],
                []
//            $this->getCaptchaProxy()
            );
            $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
            $recognizer->RecognizeTimeout = 120;

            return $this->recognizeAntiCaptcha($recognizer, $postData, $retry);
        }

        // RUCAPTCHA version
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);
    }

    protected function getCheaptickets()
    {
        $this->logger->notice(__METHOD__);
        if (!isset($this->cheaptickets)) {
            $this->cheaptickets = new TAccountCheckerCheaptickets();
            $this->cheaptickets->http = new HttpBrowser("none", new CurlDriver());
            $this->cheaptickets->http->setProxyParams($this->http->getProxyParams());

            $this->http->brotherBrowser($this->cheaptickets->http);
            $this->cheaptickets->AccountFields = $this->AccountFields;
            $this->cheaptickets->itinerariesMaster = $this->itinerariesMaster;
            $this->cheaptickets->HistoryStartDate = $this->HistoryStartDate;
            $this->cheaptickets->historyStartDates = $this->historyStartDates;
            $this->cheaptickets->http->LogHeaders = $this->http->LogHeaders;
            $this->cheaptickets->ParseIts = $this->ParseIts;
            $this->cheaptickets->ParsePastIts = $this->ParsePastIts;
            $this->cheaptickets->WantHistory = $this->WantHistory;
            $this->cheaptickets->WantFiles = $this->WantFiles;
            $this->cheaptickets->strictHistoryStartDate = $this->strictHistoryStartDate;
//            $this->logger->debug(var_export($this->http->getDefaultHeaders(), true), ['pre' => true]);
            /*$this->logger->debug("set headers");
            $defaultHeaders = $this->http->getDefaultHeaders();

            foreach ($defaultHeaders as $header => $value) {
                $this->hotels->http->setDefaultHeader($header, $value);
            }*/

            $this->cheaptickets->globalLogger = $this->globalLogger;
            $this->cheaptickets->logger = $this->logger;
            $this->cheaptickets->onTimeLimitIncreased = $this->onTimeLimitIncreased;
        }

        $cookies = $this->driver->manage()->getCookies();
        $this->logger->debug("set cookies");

        foreach ($cookies as $cookie) {
            $this->cheaptickets->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }

        return $this->cheaptickets;
    }

    public function Parse()
    {
        /*
         * this method like a travelocity
         */
        if ($this->http->currentUrl() != TAccountCheckerCheaptickets::REWARDS_PAGE_URL) {
            $this->http->GetURL(TAccountCheckerCheaptickets::REWARDS_PAGE_URL);
            sleep(2);
            $this->saveResponse();
        }

        // Name
        $name = $this->http->FindSingleNode("//li[@id = 'fullname']");

        if (!isset($name)) {
            $name = $this->http->FindPreg('/>([^\'>]*)\'s information/ims');
            $name = preg_replace('/&nbsp;/ims', ' ', $name);
        }

        if (empty($name)) {
            if ($prop11 = $this->http->FindPreg("/\"prop11\":\"([^\"]+)/ims")) {
                $this->http->GetURL("https://www.cheaptickets.com/users/{$prop11}/profile?_=" . time() . date("B"));
            }
            $response = $this->http->JsonLog($this->http->FindSingleNode('//pre[not(@id)]'), 3, false, 'firstname');

            if (isset($response->firstname, $response->middlename, $response->lastname)) {
                $name = Html::cleanXMLValue($response->firstname . " " . $response->middlename . " " . $response->lastname);
            }
        }
        $this->SetProperty("Name", beautifulName($name));

        // CheapCash balance
        $this->http->GetURL("https://www.cheaptickets.com/account/myclub");
        sleep(2);
        $this->saveResponse();
        $this->SetBalance($this->http->FindSingleNode("//h2[contains(text(), 'CheapCash balance:')]/span"));
        // Expiration Date
        $expNodes = $this->http->XPath->query("//td[@data-table-category = 'expiring-date']");
        $this->logger->debug("Total {$expNodes->length} exp nodes were found");

        for ($i = 0; $i < $expNodes->length; $i++) {
            $date = Html::cleanXMLValue($expNodes->item($i)->nodeValue);
            $date = strtotime($date);

            if ($date && (!isset($exp) || $date < $exp) && $date > time()) {
                $exp = $date;
                $this->SetExpirationDate($exp);
            }// if ($date && (!isset($exp) || $date < $exp) && $date > time())
        }// for ($i = 0; $i < $expNodes->length; $i++)

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
            if (!empty($this->Properties['Name']) || isset($response->id)) {
                $this->SetBalanceNA();
            }
        }// if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR)
    }

    public function ParseItineraries($providerHost = 'www.cheaptickets.com', $ParsePastIts = false)
    {
        parent::ParseItineraries($providerHost, $ParsePastIts);

        return [];
    }
}
