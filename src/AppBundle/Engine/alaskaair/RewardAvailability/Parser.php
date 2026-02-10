<?php

namespace AwardWallet\Engine\alaskaair\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $supportedCurrencies = ['USD'];
    private $codesForSearch = [];
    private $validRouteProblem;
    private $response;

    public static function getRASearchLinks(): array
    {
        return ['https://www.alaskaair.com/'=>'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();

        $this->UseSelenium();
        $this->seleniumOptions->recordRequests = true;

        switch (random_int(0, 4)) {
            case 0:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_84);
                $type = 'ff';

                break;

            case 1:
                $this->useFirefox(\SeleniumFinderRequest::FIREFOX_100);
                $type = 'ff';

                break;

            case 2:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
                $type = 'chrome';

                break;

            case 3:
                $this->useChromePuppeteer(\SeleniumFinderRequest::CHROME_PUPPETEER_103);
                $type = 'chrome';

                break;

            default:
                $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_100);
                $type = 'chrome';
        }

        $request = ($type == 'ff') ? FingerprintRequest::firefox() : FingerprintRequest::chrome();
        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
        $this->http->setUserAgent($fingerprint->getUseragent());

        $resolutions = [
            [1152, 864],
            [1152, 864],
            [1280, 720],
            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];

        $chosenResolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($chosenResolution);

        $this->http->saveScreenshots = true;
        $this->disableImages();

        if ($this->attempt > 1) {
//            $array = ['us', 'fi', 'de'];
            $array = ['us', 'es', 'de'];
            $targeting = $array[array_rand($array)];
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
        } else {
            $regions = ['us', 'au', 'ca'];
            $region = $regions[random_int(0, count($regions) - 1)];

            $this->setProxyGoProxies(null, $region);
        }

        $this->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

        $this->KeepState = false;

        $this->logger->notice("Running Selenium...");
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => $this->supportedCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function IsLoggedIn()
    {
        return true;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug('Params: ' . var_export($fields, true));
        $browserInfo = $this->http->driver->getBrowserInfo();
        $key = $this->getKeyConfig($browserInfo);
        $this->logger->debug('selenium browser: ' . $key);

        if (in_array($fields['Currencies'][0], $this->getRewardAvailabilitySettings()['supportedCurrencies']) !== true) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 7) {
            $this->SetWarning("It's too much travellers");

            return [];
        }

        // statistic
        $memStatBrowsers = \Cache::getInstance()->get('ra_alaskaair_stBrowsers');

        if (!is_array($memStatBrowsers)) {
            $memStatBrowsers = [];
        }
        $browserInfo = $this->http->driver->getBrowserInfo();
        $key = $this->getKeyConfig($browserInfo);

        // load data
        try {
            $result = $this->loadPage($fields);
        } catch (\WebDriverCurlException | \WebDriverException | \UnrecognizedExceptionException | \NoSuchDriverException
        | \NoSuchWindowException | \TimeOutException | \StaleElementReferenceException
        | \Facebook\WebDriver\Exception\WebDriverCurlException | \UnexpectedResponseException
        | \Facebook\WebDriver\Exception\UnknownErrorException
        | \Facebook\WebDriver\Exception\WebDriverException
        | \Facebook\WebDriver\Exception\UnrecognizedExceptionException $e) {
            $this->logger->error(get_class($e) . ": " . $e->getMessage());
            $this->logger->error($e->getTraceAsString());

            if (time() - $this->requestDateTime < 60) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw new \CheckException('WebDriverCurlException', ACCOUNT_ENGINE_ERROR);
        } catch (\Exception | \TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        if (!isset($memStatBrowsers[$key])) {
            $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
        }
        $memStatBrowsers[$key]['success']++;
        \Cache::getInstance()->set('ra_alaskaair_stBrowsers', $memStatBrowsers, 60 * 60 * 24);
        $this->logger->warning(var_export($memStatBrowsers, true), ['pre' => true]);

        if (is_string($result)) {
            $this->logger->info('load page error');
            $this->SetWarning($result);

            return [];
        }

        if (isset($this->response) && $this->response !== '') {
            if (is_array($this->response)) {
                $this->logger->info('selenium failed');
                $this->logger->error(var_export($this->response, true), ['pre' => true]);

                if (is_array($this->response) && isset($this->response['message'])
                    && $this->response['message'] !== 'Connection failure') {
                    $this->sendNotification("check restart (failed) // ZM");
                }

                throw new \CheckRetryNeededException(5, 0);
            }
            $result = $this->parseRewardFlightsJson($fields, $this->response);
        } else {
            $this->logger->error('empty response');
            $this->sendNotification("check failed // ZM");

            throw new \CheckException('something went wrong', ACCOUNT_ENGINE_ERROR);
        }

        if (is_string($result)) {
            $this->logger->info('load page error');
            $this->logger->error($result);

            throw new \CheckException($result, ACCOUNT_ENGINE_ERROR);
        }

        if (ConfigValue(CONFIG_SITE_STATE) === SITE_STATE_DEBUG) {
            $this->logger->debug('Parsed data:');
            $this->logger->debug(var_export($result, true), ['pre' => true]);
        }
        $this->logger->notice('no captcha. All ok. Save session');
        $this->keepSession(true);

        return ['routes' => $result];
    }

    private function getKeyConfig(array $info)
    {
        return $info[\SeleniumStarter::CONTEXT_BROWSER_FAMILY] . '-' . $info[\SeleniumStarter::CONTEXT_BROWSER_VERSION];
    }

    private function decodeCabin($cabin)
    {
        switch ($cabin) {
            case 'Saver':
            case 'Coach':
            case 'Main':
            case 'Partner Premium':
                return 'economy';

            case 'Premium':
            case 'Premium Coach':
            case 'Premium Economy':
                return 'premiumEconomy';

            case 'Business':
            case 'Partner Business':
                return 'business';

            case 'First':
            case 'First Class':
                return 'firstClass';
        }
        $this->sendNotification("check cabin data: {$cabin} // ZM");

        return null;
    }

    private function decodeCabinColumn($column)
    {
        if ($m = $this->http->FindPreg("/^REFUNDABLE_(.+)/", false, $column)) {
            $cabin = ucwords(strtolower(str_replace('_', ' ', $m)));
        } else {
            $this->sendNotification('new cabin column // ZM');

            return null;
        }
        $cabin = $this->clearCOS($cabin);

        return $cabin;
    }

    private function startPage()
    {
        $this->logger->notice(__METHOD__);

        try {
            $this->http->GetURL("https://www.alaskaair.com");
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            $this->driver->executeScript('window.stop();');

            try {
                $this->http->GetURL("https://www.alaskaair.com");
            } catch (\ScriptTimeoutException | \TimeOutException $e) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        $this->checkPage();
    }

    private function fillForm($fields)
    {
        $this->logger->notice(__METHOD__);
        // debug
        $this->http->saveScreenshots = true;
        $res = $this->saveResponse2();

        if (is_string($res) && strpos($res, 'invalid session id') !== false) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($msg = $this->http->FindSingleNode("//h4[contains(text(), 'Looks like we are experiencing a temporary technical issue.')]")) {
            // sometimes help
            try {
                $this->http->GetURL("https://www.alaskaair.com");
            } catch (\ScriptTimeoutException | \TimeOutException $e) {
                $this->logger->error("TimeOutException: " . $e->getMessage());

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
        }

        if ($msg = $this->http->FindSingleNode("//h4[contains(text(), 'Looks like we are experiencing a temporary technical issue.')]")) {
            if ($bookFlight = $this->waitForElement(\WebDriverBy::xpath("//a[contains(text(),'Book a flight')]"),
                0)) {
                $bookFlight->click();
                $this->saveResponse2();
            }

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }
        $this->checkPage();

        $this->checkCaptcha();

        // debug
        $this->http->saveScreenshots = false;

        // Find and fill form
        $from = $this->waitForElement(\WebDriverBy::id('fromCity1'), 20);

        if (!$from) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $form = $this->waitForElement(\WebDriverBy::id('booking'), 10);

        if (!$form) {
            throw new \CheckException("Can't load form", ACCOUNT_ENGINE_ERROR);
        }
        $isValidRoute = $this->validRoute($fields);

        $y = $form->getLocation()->getY() - 20;
        $this->driver->executeScript("window.scrollBy(0, $y)");
        sleep(1);

        $this->saveResponse2();

        try {
            $oneWay = $this->waitForElement(\WebDriverBy::id("oneWay"), 0);
            $awardReservation = $this->waitForElement(\WebDriverBy::id("awardReservation"), 0);

            if (!$oneWay || !$awardReservation) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->driver->executeScript("$('#oneWay').click();");
            $this->driver->executeScript("$('#awardReservation').click();");
            //                $oneWay->click();
            //                $awardReservation->click();
        } catch (\NoSuchDriverException $e) {
            $this->logger->error('NoSuchDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\UnexpectedJavascriptException $e) {
            $this->logger->error('UnexpectedJavascriptException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $from->click();
        $from->sendKeys($fields['DepCode']);

        //            $this->logger->notice('no captcha. Save session');
        //            $this->keepSession(true);
        $city = null;

        if (isset($this->codesForSearch[$fields['DepCode']])) {
            if (($pos = strpos($this->codesForSearch[$fields['DepCode']], "'")) !== false) {
                $starts = substr($this->codesForSearch[$fields['DepCode']], 0, $pos);
                $city = $this->waitForElement(\WebDriverBy::xpath('(//li[starts-with(@title,"' . $starts . '")])[1]'),
                    10);
            } else {
                $city = $this->waitForElement(\WebDriverBy::xpath('(//li[@title = "' . $this->codesForSearch[$fields['DepCode']] . '"])[1]'),
                    10);
            }
        }

        if (!$city) {
            $city = $this->waitForElement(\WebDriverBy::xpath("(//li[@citycode = '" . $fields['DepCode'] . "'])[1]"),
                10);
        }
        $this->saveResponse2();

        if (!$city) {
            $msg = "Can't select departure city";

            if ($isValidRoute || $this->validRouteProblem) {
                $this->logger->error($msg);

                throw new \CheckRetryNeededException(5, 0);
            }

            return $msg;
        }

        $city->click();

        $to = $this->waitForElement(\WebDriverBy::id('toCity1'), 20);

        if (!$to) {
            throw new \CheckException("Can't load form", ACCOUNT_ENGINE_ERROR);
        }
        $to->click();
        $to->sendKeys($fields['ArrCode']);

        $city = null;

        if (isset($this->codesForSearch[$fields['ArrCode']])) {
            if (($pos = strpos($this->codesForSearch[$fields['ArrCode']], "'")) !== false) {
                $starts = substr($this->codesForSearch[$fields['ArrCode']], 0, $pos);
                $city = $this->waitForElement(\WebDriverBy::xpath('(//li[starts-with(@title,"' . $starts . '")])[last()]'),
                    10);
            } else {
                $city = $this->waitForElement(\WebDriverBy::xpath('(//li[@title = "' . $this->codesForSearch[$fields['ArrCode']] . '"])[last()]'),
                    10);
            }
        }

        if (!$city) {
            $city = $this->waitForElement(\WebDriverBy::xpath("(//li[@citycode = '" . $fields['ArrCode'] . "'])[last()]"),
                10);
        }
        $this->saveResponse2();

        if (is_null($city)) {
            $msg = "Can't select arrival city";

            if ($isValidRoute || $this->validRouteProblem) {
                $this->logger->error($msg);

                throw new \CheckRetryNeededException(5, 0);
            }

            return $msg;
        }

        try {
            $city->click();
        } catch (\StaleElementReferenceException $e) {
            // sometimes help
            $this->logger->error("exception: " . $e->getMessage());
            $this->saveResponse2();
            $city = $this->waitForElement(\WebDriverBy::xpath("//li[@citycode = '" . $fields['ArrCode'] . "']"),
                10);

            if (is_null($city)) {
                $this->logger->error("Can't select arrival city");

                throw new \CheckRetryNeededException(5, 0);
            }
            $city->click();
        }

        $checkedAdults = $this->driver->executeScript("return $('#adultCount').val();");
        $this->logger->notice($checkedAdults);

        if (strpos($checkedAdults, $fields['Adults'] . ' adult') === false) {
            $needToCheckAdults = true;
        }

        if (isset($needToCheckAdults)) {
            $adults = $this->waitForElement(\WebDriverBy::xpath('//select[@name="AdultCount"]'), 0);

            if (!$adults) {
                //debug
                $this->http->saveScreenshots = true;
                $this->saveResponse2();

                $this->logger->error("Can't select adults");

                throw new \CheckRetryNeededException(5, 0);
            }
            $adults->click();
            $onPageAdult = $this->http->FindPreg("/(\d+) adult/", false, $checkedAdults);

            if (null === $onPageAdult) {
                // not stable way
                $value = $this->waitForElement(\WebDriverBy::xpath("//option[starts-with(normalize-space(), '" . $fields['Adults'] . " adult')]"),
                    3);

                if (!$value) {
                    //debug
                    $this->http->saveScreenshots = true;
                    $this->saveResponse2();
                    $adults = $this->waitForElement(\WebDriverBy::xpath('//select[@name="AdultCount"]'), 0);
                    $adults->click();
                    sleep(1);
                    $value = $this->waitForElement(\WebDriverBy::xpath("//option[starts-with(normalize-space(), '" . $fields['Adults'] . " adult')]"),
                        0);

                    if (!$value) {
                        $this->logger->error("Can't select adults");
                        $to = $this->waitForElement(\WebDriverBy::id('toCity1'), 0);

                        if ($to) {
                            $to->click();
                        }
                        $this->saveResponse2();
                        $onPageAdult = $this->http->FindPreg("/(\d+) adult/", false, $checkedAdults);

                        if (null !== $onPageAdult) {
                            $this->checkAdultType2($onPageAdult, $adults, $fields);
                        } else {
                            $this->logger->error("Can't select adults");

                            throw new \CheckRetryNeededException(5, 0);
                        }
                    }
                    $this->http->saveScreenshots = false;
                    $this->sendNotification('check adult // ZM');
                }
                $value->click();
            } else {
                $this->checkAdultType2($onPageAdult, $adults, $fields);
            }
        }

        $date = $this->waitForElement(\WebDriverBy::id('departureDate1'), 2);

        if (!$date) {
            //debug
            $this->http->saveScreenshots = true;
            $this->saveResponse2();

            $this->logger->error("Can't find date");

            throw new \CheckRetryNeededException(5, 0);
        }
        $date->clear();
        $date->click();

        $dateFormat = date("n/j/y", $fields['DepDate']);
        $date->sendKeys($dateFormat);

        try {
            $this->driver->executeScript("document.querySelector('#departureDate1').setAttribute('aria-hidden', true);");
        } catch (\UnexpectedJavascriptException $e) {
            $this->sendNotification('check retry js // ZM');
            $this->saveResponse2();
            $this->driver->executeScript("document.querySelector('#departureDate1').setAttribute('aria-hidden', true);");
        }
        //debug
        $this->http->saveScreenshots = true;

        $this->saveResponse2();

        return true;
    }

    private function checkAdultType2($onPageAdult, $adults, $fields)
    {
        $onPageAdult = (int) $onPageAdult;
        $needCount = (int) $fields['Adults'];

        if ($onPageAdult < $needCount) {
            $typeClick = \WebDriverKeys::ARROW_DOWN;
        } else {
            $typeClick = \WebDriverKeys::ARROW_UP;
        }
        $numClicks = abs($onPageAdult - $needCount);

        while ($numClicks > 0) {
            $this->logger->notice('click arrow');
            $adults->sendKeys($typeClick);
            $numClicks--;
        }
        $checkedAdults = $this->driver->executeScript("return $('#adultCount').val();");
        $this->logger->notice($checkedAdults);

        if (strpos($checkedAdults, $fields['Adults'] . ' adult') === false) {
            $this->http->saveScreenshots = true;
            $this->saveResponse2();
            // not stable way
            $value = $this->waitForElement(\WebDriverBy::xpath("//option[starts-with(normalize-space(), '" . $fields['Adults'] . " adult')]"),
                3);

            if (!$value) {
                //debug
                $this->http->saveScreenshots = true;
                $this->saveResponse2();
                $adults = $this->waitForElement(\WebDriverBy::xpath('//select[@name="AdultCount"]'), 0);
                $adults->click();
                sleep(1);
                $value = $this->waitForElement(\WebDriverBy::xpath("//option[starts-with(normalize-space(), '" . $fields['Adults'] . " adult')]"),
                    0);

                if (!$value) {
                    $this->logger->error("Can't select adults");

                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->http->saveScreenshots = false;
            }
            $value->click();
        }
    }

    private function saveResponse2()
    {
        $res = $this->saveResponse();

        if (is_string($res)
            && (strpos($res, 'invalid session id') !== false
                || strpos($res, 'JSON decoding of remote response failed') !== false
                || strpos($res, 'Failed to connect to') !== false
                || strpos($res, 'Tried to run command without establishing a connection') !== false
                || strpos($res, 'expects parameter 2 to be string, array given') !== false // отвал
            )
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $res;
    }

    private function checkCaptcha($keepParse = false): bool
    {
        $this->logger->notice(__METHOD__);

        // statistic
        $memStatBrowsers = \Cache::getInstance()->get('ra_alaskaair_stBrowsers');

        if (!is_array($memStatBrowsers)) {
            $memStatBrowsers = [];
        }
        $browserInfo = $this->http->driver->getBrowserInfo();
        $key = $this->getKeyConfig($browserInfo);

        if (!isset($memStatBrowsers[$key])) {
            $memStatBrowsers[$key] = ['success' => 0, 'failed' => 0];
        }

        if ($this->http->FindSingleNode("(//p[contains(normalize-space(),'Access to this page has been denied because we believe you are using automation tools to browse the website')] | // div[contains(normalize-space(),'Hold to confirm you are')])[1]")
            && $this->http->FindPreg("/\/captcha\.js\?/")
            && $this->http->FindPreg("/perimeterx\.(?:com|net)\//")) {
            $this->logger->debug("find iframe");

            if ($keepParse) {
                return false;
            }

            if ($this->attempt > 1) {
                $this->markProxyAsInvalid();
            }

            $memStatBrowsers[$key]['failed']++;
            \Cache::getInstance()->set('ra_alaskaair_stBrowsers', $memStatBrowsers, 60 * 60 * 24);
            $this->logger->warning(var_export($memStatBrowsers, true), ['pre' => true]);

            throw new \CheckRetryNeededException(5, 0);
        }

        return true;
    }

    private function checkPage()
    {
        if ($this->http->FindSingleNode("//span[contains(text(), 'This site can’t be reached')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Unable to connect')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'Secure Connection Failed')]")
            || $this->http->FindSingleNode("//h1[contains(text(), 'The connection has timed out')]")
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }
    }

    private function loadPage(array $fields, $isRetry = false)
    {
        $this->logger->notice(__METHOD__);

        if ($isRetry) {
            // check data
            $shoppingParams = $this->driver->executeScript("return localStorage.getItem('shoppingParams');");
            $shoppingParams = $this->http->JsonLog($shoppingParams, 1, true);

            if (!isset(
                    $shoppingParams['flightType'], $shoppingParams['AwardOption'],
                    $shoppingParams['IsOneWay'], $shoppingParams['IsAwardReservation'],
                    $shoppingParams['DepartureDate1'], $shoppingParams['AdultCount'],
                    $shoppingParams['ArrivalCity1'], $shoppingParams['DepartureCity1']
                )
                || $shoppingParams['flightType'] !== '2'
                || $shoppingParams['AwardOption'] !== 'MilesOnly'
                || $shoppingParams['IsOneWay'] !== true
                || $shoppingParams['IsAwardReservation'] !== true
                || $shoppingParams['DepartureDate1'] !== date("n/j/y", $fields['DepDate'])
                || strpos($shoppingParams['AdultCount'], $fields['Adults'] . ' adult') !== 0
                || strpos($shoppingParams['ArrivalCity1'], '(' . $fields['ArrCode']) === false
                || strpos($shoppingParams['DepartureCity1'], '(' . $fields['DepCode']) === false
            ) {
                $this->logger->error("it's better restart");

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->http->GetURL("https://www.alaskaair.com/planbook?lid=nav:book-flights&int=AS_NAV_Book_Flights_-prodID:ShoppingBooking");

            $miles = $this->waitForElement(\WebDriverBy::id('awardReservation'), 10);

            if (!$miles) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->driver->executeScript("
            if (!document.querySelector('#awardReservation').checked)
                document.querySelector('#awardReservation').click();");
        } else {
            // for hot clear from
            if (strpos($this->http->currentUrl(), 'alaskaair.com') !== false) {
                $this->driver->executeScript("localStorage.removeItem('shoppingParams');");
            }
            $this->startPage();
        }

        try {
            /*
            if (!$isRetry) {
                $res = $this->fillForm($fields);

                if (is_string($res)) {
                    return $res;
                }
            } else {
                $this->checkCaptcha();
                $from = $this->waitForElement(\WebDriverBy::id('fromCity1'), 10);
            }
            // send form
            $button = $this->waitForElement(\WebDriverBy::id("findFlights"), 10);

            if (!$button) {
                throw new \CheckException("Can't find btn", ACCOUNT_ENGINE_ERROR);
            }

//            $this->runScriptFetch();
            $this->driver->executeScript("document.querySelector('#findFlights').click();");
//            usleep(10000);
//            $this->runScriptFetch();
            */
            $dateFormat = date("Y-m-d", $fields['DepDate']);

            $urlSearch = "https://www.alaskaair.com/search/results?A={$fields['Adults']}&C=0&L=0&O={$fields['DepCode']}&D={$fields['ArrCode']}&OD={$dateFormat}&RT=false&ShoppingMethod=onlineaward";
            $this->http->GetURL($urlSearch);

            // Check load error
            $this->waitFor(function () {
                return $this->waitForElement(\WebDriverBy::id('CalendarResultColumn'), 0)
                    || $this->waitForElement(\WebDriverBy::id('MatrixResultColumn'), 0)
                    || $this->waitForElement(\WebDriverBy::xpath('
                    //h1[contains(.,"Lowest available awards by date")]
                    | //*[(self::auro-alert or self::auto-alert) and contains(normalize-space(),"We are unable to complete your search request")]
                    | //h2[contains(.,"Start your search")]
                    | //table[@id="MatrixTable_0"]'), 0);
            }, 40);

            $this->saveResponse2();

            if ($emptyField = $this->http->FindSingleNode("//*[@id='toCity' or @id='fromCity'][contains(.,'match any')]/@id")) {
                $this->logger->notice('set warning and save session');
                $this->keepSession(true);

                switch ($emptyField) {
                    case 'toCity':
                        $msg = sprintf("No flights from %s to %s", $fields['DepCode'], $fields['ArrCode']);

                        break;

                    case 'fromCity':
                        $msg = sprintf("No flights from %s", $fields['DepCode']);

                        break;

                    default:
                        $msg = "Can't get information on the selected parameters.";

                        break;
                }

                return $msg;
            }

            if ($msg = $this->waitForElement(\WebDriverBy::xpath('//*[(self::auro-alert or self::auto-alert) and contains(normalize-space(),"We are unable to complete your search request")]'),
                0)) {
                $this->logger->notice('set warning and save session');
                $this->keepSession(true);

                return $msg->getText();
            }

            if ($this->waitForElement(\WebDriverBy::xpath('//h1[contains(.,"Lowest available awards by date")]'), 0)
                && ($msg = $this->waitForElement(\WebDriverBy::xpath('//li[contains(normalize-space(),"There are no options available on one")]'),
                    0))) {
                $this->logger->error($msg->getText());
                $this->sendNotification("check error // ZM");

                return $msg->getText();
            }
//            $this->response = $this->driver->executeScript('return localStorage.getItem("searchbff");');

            $table = $this->waitForElement(\WebDriverBy::id('MatrixResultColumn'), 0);
            $this->saveResponse2();

//            $keepParse = !$isRetry;
            $keepParse = false;

            if (!$this->checkCaptcha($keepParse)) {
                if ($isRetry) {
                    // на всякий проверка (если checkCaptcha неаккуратно "поправят")
                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->http->removeCookies();

                return $this->loadPage($fields, true);
            }

            if (!$table) {
                $table = $this->waitForElement(\WebDriverBy::xpath('//table[@id="MatrixTable_0"]'), 0);

                if (!$table) {
                    $this->logger->notice('set warning and save session');
                    $this->keepSession(true);

                    if ($msg = $this->http->FindSingleNode('//*[(self::auro-alert or self::auto-alert) and contains(normalize-space(),"We are unable to complete your search request. There may not be flights available for those city pairs or for the time or day of week requested.")]')) {
                        $this->logger->error($msg);

                        return $msg;
                    }

                    return "Can't get information on the selected parameters.";
                }

                if (empty($this->response)) {
                    $newFormat = true;
                    $this->response = $this->runMainFetch($fields);

                    if (empty($this->response)) {
                        $dateStr = date('Y-m-d', $fields['DepDate']);
                        $mainURL = "https://www.alaskaair.com/search/results?O={$fields['DepCode']}&D={$fields['ArrCode']}&OD={$dateStr}&A={$fields['Adults']}&C=0&L=0&RT=false&ShoppingMethod=onlineaward";
                        $this->logger->debug($this->http->currentUrl());

                        if ($this->http->currentUrl() == $mainURL) {
                            $this->driver->executeScript("window.location.reload();");
                        } else {
                            $this->http->GetURL($mainURL);
                        }
                        $this->runScriptFetch();
                        $this->waitForElement(\WebDriverBy::id('MatrixTable_0'), 20);
                        $this->response = $this->driver->executeScript('return localStorage.getItem("searchbff");');
                    }

                    if (empty($this->response)) {
                        throw new \CheckRetryNeededException(5, 0);
                    }
                }
            }
        } catch (\UnknownServerException $e) {
            $this->logger->error("exception: " . $e->getMessage());

            if (strpos($e->getMessage(), 'Connection refused') !== false) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->DebugInfo = "exception";
            $this->saveResponse2();
        }

        $this->saveResponse2();

        $this->checkPage();

        if (($msg = $this->waitForElement(\WebDriverBy::xpath("(//text()[contains(normalize-space(), 'There are no flights available with the selected filter options')])/ancestor::*[1]"),
                0)) && !empty($msg->getText())) {
            return "There are no flights available with the selected filter options.";
        }

        $dateStr = date("M", $fields['DepDate']) . ' ' . (int) date("d", $fields['DepDate']); // Oct 1
        $dateStr_ = date("M d", $fields['DepDate']); // Oct 01

        if (!isset($newFormat)
            && ($this->http->FindSingleNode("//div[@class='Month' and (contains(text(),'{$dateStr}') or contains(text(),'{$dateStr_}'))]/following-sibling::*[1][normalize-space()='unavailable']/following-sibling::*[1][@class='Price' and string-length(normalize-space())=1]")
                || $this->http->FindSingleNode("//span[contains(@class,'airportContainer')]/following-sibling::div[1][contains(@class,'dateContainer')]",
                    null, true, "/(?:{$dateStr}|{$dateStr_})/"))
        ) {
            $roots = $this->http->XPath->query("//tr[starts-with(@id, 'flightInfoRow')][contains(.,'Details')]");

            if ($roots->length === 0) {
                $roots = $this->http->XPath->query("//div[starts-with(@id,'segmentContainer')][./following-sibling::div[1][contains(.,'Details')]]");
            }

            if ($roots->length === 0) {
                return "There are no flights available with the selected filter options.";
            }
            $this->sendNotification("check routes. seems error // ZM");
        }

        return true;
    }

    private function parseRewardFlightsJson($fields, $data)
    {
        $this->logger->notice(__METHOD__);
        $routes = [];
        $data = $this->http->JsonLog($data, 1, true);

        if (!isset($data['slices'])) {
            $this->logger->debug('no data');

            throw new \CheckRetryNeededException(5, 0);
        }

        $cnt = count($data['slices']);
        $this->logger->debug("Found {$cnt} routes.");

        foreach ($data['slices'] as $item) {
            $segments = [];
            $stops = -1;

            foreach ($item['segments'] as $segmentData) {
                $depDateTime = strtotime(str_replace('T', ' ', substr($segmentData['departureTime'], 0, 16)));
                $arrDateTime = strtotime(str_replace('T', ' ', substr($segmentData['arrivalTime'], 0, 16)));
                $seg = [
                    'num_stops' => (isset($segmentData['performance'][0]['changeOfPlane']) && $segmentData['performance'][0]['changeOfPlane']) ? 1 : 0,
                    'departure' => [
                        'date'     => date('Y-m-d H:i', $depDateTime),
                        'dateTime' => $depDateTime,
                        'airport'  => $segmentData['departureStation'],
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', $arrDateTime),
                        'dateTime' => $arrDateTime,
                        'airport'  => $segmentData['arrivalStation'],
                    ],
                    'flight'   => [$segmentData['displayCarrier']['carrierCode'] . $segmentData['displayCarrier']['flightNumber']],
                    'aircraft' => $segmentData['aircraft'],
                    'airline'  => $segmentData['displayCarrier']['carrierCode'],
                    'distance' => $segmentData['performance'][0]['distance']['length'] . ' ' . $segmentData['performance'][0]['distance']['unit'],
                ];
//                $stops = $seg['num_stops'] + 1;
                $segments[] = $seg;
            }

            foreach ($item['fares'] as $column => $fare) {
                $route = [
                    'num_stops'   => $stops,
                    'redemptions' => [
                        'miles'   => $fare['milesPoints'],
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => 'USD',
                        'taxes'    => ceil($fare['grandTotal']),
                    ],
                    'tickets'        => $fare['seatsRemaining'],
                    'classOfService' => $this->decodeCabinColumn($column),
                ];
                $segments_ = $segments;

                foreach ($fare['cabins'] as $i => $cabin) {
                    if (!isset($segments[$i])) {
                        $this->logger->error('no segment');
                        $this->logger->error("[id]:" . var_export($item['id'], true));
                        $this->logger->error(var_export($column, true));
                        $this->logger->error(var_export($segments, true));
                        $this->sendNotification('no segment //ZM');

                        throw new \CheckException('no segment', ACCOUNT_ENGINE_ERROR);
                    }
                    $segments_[$i] += [
                        'cabin'          => $this->decodeCabin(ucwords(strtolower($cabin))),
                        'fare_class'     => $fare['bookingCodes'][$i],
                        'classOfService' => $this->clearCOS(ucwords(strtolower($cabin))),
                    ];
                }
                $route += ['connections' => $segments_];
                $route['num_stops'] = count($segments_) - 1;

                $routes[] = $route;
            }
        }

        return $routes;
    }

    private function clearCOS(?string $cos): ?string
    {
        if (!$cos) {
            return $cos;
        }

        if (preg_match("/^(.+\w+) (?:cabin|class)$/i", $cos, $m)) {
            $cos = $m[1];
        }

        return $cos;
    }

    private function validRoute($fields)
    {
        $hasFail = false;
        $valid = true;

        foreach ([$fields['DepCode'], $fields['ArrCode']] as $code) {
            try {
                $tt =
                    '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("POST", "https://www.alaskaair.com/HomeWidget/GetCities", false);
                    xhttp.setRequestHeader("Content-type", "application/x-www-form-urlencoded; charset=UTF-8");
                    xhttp.setRequestHeader("Accept", "application/json, text/javascript, */*; q=0.01");
                    xhttp.setRequestHeader("Accept-Encoding", "gzip, deflate, br");
                    xhttp.setRequestHeader("X-Requested-With", "XMLHttpRequest");
                    xhttp.setRequestHeader("Adrum", "isAjax:true");
        
                    var data = "prefixText=' . $code . '&contextKey=partner";
                    var responseText = null;
                    xhttp.onreadystatechange = function() {
                        if (this.readyState == 4 && this.status == 200) {
                            responseText = this.responseText;
                        }
                    };
                    xhttp.send(data);
                    return responseText;
                ';
                $this->logger->debug($tt, ['pre' => true]);

                try {
                    $returnData = $this->driver->executeScript($tt);
                } catch (\Facebook\WebDriver\Exception\InvalidSelectorException $e) {
                    $this->logger->error('[InvalidSelectorException]: ' . $e->getMessage());
                    $this->driver->executeScript('window.stop();');
                    sleep(2);
                    $returnData = $this->driver->executeScript($tt);
                }
            } catch (\UnexpectedJavascriptException | \InvalidSelectorException
            | \Facebook\WebDriver\Exception\JavascriptErrorException
            | \Facebook\WebDriver\Exception\ScriptTimeoutException
            | \Facebook\WebDriver\Exception\InvalidSelectorException
            | \ScriptTimeoutException | \XPathLookupException $e) {
                $this->logger->error($e->getMessage());
                $fail = true;
            }

            if (isset($fail) || empty($returnData)) {
                $this->logger->error('for ' . $code . ' ' . var_export($returnData ?? null, true));
                $hasFail = true;

                continue;
            }

            $data = $this->http->JsonLog($returnData, 1, true);

            if (is_array($data)) {
                $listCodes = $this->getCodes($data);
            } else {
                $hasFail = true;

                continue;
            }
            $this->codesForSearch = array_merge($this->codesForSearch, $listCodes);

            if (!array_key_exists($code, $listCodes)) {
                $this->logger->error('no in list cities: ' . $code);
                $valid = false;
            }
        }

        if ($hasFail) {
            // if can't check then say valid
            $this->validRouteProblem = true;
            $valid = true;
        }

        return $valid;
    }

    private function getCodes(array $data): array
    {
        $codes = [];

        foreach ($data as $val) {
            if (isset($val['S']) && is_array($val['S'])) {
                foreach ($val['S'] as $v) {
                    if (isset($v['C'], $v['N'])) {
                        $codes[$v['C']] = $v['N'];
                    }
                }
            }

            if (isset($val['C'], $val['N'])) {
                $codes[$val['C']] = $val['N'];
            }
        }

        return $codes;
    }

    private function runScriptFetch(): void
    {
        $this->logger->debug(__METHOD__);
//        $dataURL = "https://www.alaskaair.com/searchbff/V3/search?origins={$fields['DepCode']}&destinations={$fields['DepCode']}&dates={$dateStr}&numADTs={$fields['Adults']}&numINFs=0&fareView=as_awards&sessionID=&solutionSetIDs=&solutionIDs=";

        $this->driver->executeScript(/** @lang JavaScript */ "
            localStorage.setItem('searchbff', '');
            var constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                 return new Promise((resolve, reject) => {
                     constantMock.apply(this, arguments)
                         .then( (response) => { 
                                if (response.url.indexOf('/searchbff/V3/search?origins') > -1) {
                                    response.clone().json().then(
                                        (body) => {
                                            localStorage.setItem('searchbff', JSON.stringify(body));
                                        }
                                    );
                                }
                                if (response.url.indexOf('api/flightresults?origins') > -1) {
                                    response.clone().json().then(
                                        (body) => {
                                            localStorage.setItem('searchbffapi', JSON.stringify(body));
                                        }
                                    );
                                }
                                resolve(response);
                         }).catch((error) => {
                            reject(response);
                         })
                 });
            }
        ");
    }

    private function generateToken()
    {
        $this->logger->notice(__METHOD__);

        $res = $this->driver->executeScript('
        function Ur() {
		    r = crypto || msCrypto;
		    t = 4294967295 & r.getRandomValues(new Uint32Array(1))[0],
		    t >>>= 0;
		    var lr = t;
            for (var e, t = ["0", "1", "2", "3", "4", "5", "6", "7", "8", "9", "a", "b", "c", "d", "e", "f"], r = "", o = 0; o < 4; o++)
                r += t[15 & (e = lr)] + t[e >> 4 & 15] + t[e >> 8 & 15] + t[e >> 12 & 15] + t[e >> 16 & 15] + t[e >> 20 & 15] + t[e >> 24 & 15] + t[e >> 28 & 15];
            var n = t[8 + (3 & lr) | 0];
            return r.substr(0, 8) + r.substr(9, 4) + "4" + r.substr(13, 3) + n + r.substr(16, 3) + r.substr(19, 12)
        }

        var traceId = Ur();
        var spanId = Ur().substr(0, 16);
        return ["|" + traceId + "." + spanId, "00-"+traceId+"-"+spanId+"-01"];
        ');
        $this->logger->error(var_export($res, true));

        if (is_array($res) && count($res) === 2) {
            [$requestID, $traceparent] = $res;
        } else {
            return null;
        }

        return [$requestID, $traceparent];
    }

    private function getTokensFromRecorder()
    {
        $this->logger->notice(__METHOD__);

        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $this->http->driver;

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error(('BrowserCommunicatorException: ' . $e->getMessage()));

            throw new \CheckRetryNeededException(5, 0);
        } catch (\ErrorException $e) {
            $this->logger->error(('ErrorException: ' . $e->getMessage()));

            throw new \CheckRetryNeededException(5, 0);
        }

        $requestID = $traceparent = null;

        foreach ($requests as $n => $xhr) {
            if (strpos($xhr->request->getUri(), '/v1?bookingFlow=REWARD') !== false) {
                if ($xhr->response->getStatus() == 200
                    && is_array($xhr->request->getHeaders())
                    && isset($xhr->request->getHeaders()['request-id'], $xhr->request->getHeaders()['traceparent'])) {
                    $requestID = $xhr->request->getHeaders()['request-id'];
                    $traceparent = $xhr->request->getHeaders()['traceparent'];

                    break;
                }
            }
        }

        return [$requestID, $traceparent];
    }

    private function runMainFetch($fields)
    {
        $this->logger->notice(__METHOD__);
        $dateStr = date('Y-m-d', $fields['DepDate']);
        //$dataURL = "https://www.alaskaair.com/searchbff/V3/search?origins={$fields['DepCode']}&destinations={$fields['ArrCode']}&dates={$dateStr}&numADTs={$fields['Adults']}&numINFs=0&fareView=as_awards&sessionID=&solutionSetIDs=&solutionIDs=";
        $dataURL = "https://www.alaskaair.com/search/api/flightresults?origins={$fields['DepCode']}&destinations={$fields['ArrCode']}&dates={$dateStr}&numADTs={$fields['Adults']}&numINFs=0&fareView=as_awards&sessionID=&solutionSetIDs=&solutionIDs=";
        $mainURL = "https://www.alaskaair.com/search/results?O={$fields['DepCode']}&D={$fields['ArrCode']}&OD={$dateStr}&A={$fields['Adults']}&C=0&L=0&RT=false&ShoppingMethod=onlineaward";

//        $data = $this->getTokensFromRecorder();
        $data = $this->generateToken();

        if (null == $data) {
            $this->logger->error('no token for request');

            return null;
        }
        [$requestID, $traceparent] = $data;
        $this->logger->debug('run fetch');
//                     "adrum": "isAjax:true",
        //                    "accept-language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
        //                  "mode": "cors",
        $this->driver->executeScript('
                fetch("' . $dataURL . '", {
                  "headers": {
                    "accept": "*/*",
                    "sec-fetch-dest": "empty",
                    "sec-fetch-mode": "cors",
                    "sec-fetch-site": "same-site",
                    "request-id": "' . $requestID . '",
                    "traceparent": "' . $traceparent . '",
                    "cookietoggles": ""
                  },
                  "referer": "' . $mainURL . '",
                  "method": "GET",
                }).then( response => response.json())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "searchbff";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
            ');

        $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="searchbff"]'), 10, false);
        $this->saveResponse2();

        if (!$ext) {
            return null;
        }

        return $ext->getAttribute("searchbff");
    }
}
