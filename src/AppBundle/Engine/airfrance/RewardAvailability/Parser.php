<?php

namespace AwardWallet\Engine\airfrance\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountCheckerAirfrance
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    private const CONFIGS = [
        'firefox-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_100,
        ],
        'chrome-100' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_100,
        ],
        'chromium-80' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROMIUM,
            'browser-version' => \SeleniumFinderRequest::CHROMIUM_80,
        ],
        'chrome-84' => [
            'agent'           => "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/95.0.4638.69 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_84,
        ],
        'chrome-95' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 12_2_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
            'browser-version' => \SeleniumFinderRequest::CHROME_95,
        ],
        'firefox-59' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_59,
        ],
        'puppeteer-103' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME_PUPPETEER,
            'browser-version' => \SeleniumFinderRequest::CHROME_PUPPETEER_103,
        ],
        'firefox-84' => [
            'agent'           => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:97.0) Gecko/20100101 Firefox/97.0",
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
        ],
    ];
    public $isRewardAvailability = true;

    private $token;
    private $mainUrl;

    private $currencySite = [
        'USD' => [
            'url'     => 'wwws.airfrance.us',
            'country' => 'US',
        ],
        'EUR' => [
            'url'     => 'wwws.airfrance.fr/en',
            'country' => 'FR',
        ],
        'CNY' => [
            'url'     => 'wwws.airfrance.com.cn/en', // + 'wwws.airfrance.com.tw/en'
            'country' => 'CN',
        ],
        'INR' => [
            'url'     => 'wwws.airfrance.in',
            'country' => 'IN',
        ],
        'IDR' => [
            'url'     => 'wwws.airfrance.id',
            'country' => 'ID',
        ],
        'HKD' => [
            'url'     => 'wwws.airfrance.com.hk/en',
            'country' => 'HK',
        ],
        'JPY' => [
            'url'     => 'wwws.airfrance.co.jp',
            'country' => 'JP',
        ],
        'CAD' => [
            'url'     => 'wwws.airfrance.ca/en',
            'country' => 'CA',
        ],
        'XPF' => [
            'url'      => 'wwws.airfrance.nc', // only fr + 'wwws.airfrance.pf', //  only fr
            'country'  => 'NC',
            'language' => 'fr',
        ],
    ];

    private $currentUrl;
    private $headers = [
        'AFKL-Travel-Country' => 'US',
        'country'             => 'US',
        'AFKL-TRAVEL-Host'    => 'af',
        'Accept'              => 'application/json, text/plain, *',
        'Content-Type'        => 'application/json',
        'Referer'             => 'https://login.airfrance.com/login/account',
        "Accept-Encoding"     => "gzip, deflate, br",
    ];
    private $routeData;
    private $fromAjax;
    private $config;
    private $newSession;

    public static function getRASearchLinks(): array
    {
        return ['https://wwws.airfrance.us/'=>'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->KeepState = false;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');

        if ($this->attempt < 2) {
            $array = ['us', 'fi', 'il', 'de', 'fr', 'fi'];
            $targeting = $array[array_rand($array)];
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
        } else {
            $array = ['us', 'au', 'il', 'es', 'fr', 'fi'];
            $targeting = $array[array_rand($array)];
            $this->setProxyGoProxies(null, $targeting);
        }

        $successfulConfigs = array_filter(array_keys(self::CONFIGS), function (string $key) {
            return \Cache::getInstance()->get('airfrance_config_' . $key) === 1;
        });

        $neutralConfigs = array_filter(array_keys(self::CONFIGS), function (string $key) {
            return \Cache::getInstance()->get('airfrance_config_' . $key) !== 0;
        });

        if (count($successfulConfigs) > 0) {
            $config = $successfulConfigs[array_rand($successfulConfigs)];
            $this->logger->info("found " . count($successfulConfigs) . " successful configs");
        } elseif (count($neutralConfigs) > 0) {
            $config = $neutralConfigs[array_rand($neutralConfigs)];
            $this->logger->info("found " . count($neutralConfigs) . " neutral configs");
        } else {
            $config = array_rand(self::CONFIGS);
        }

//        $config= array_keys(self::CONFIGS)[6];
        $config = array_keys(self::CONFIGS)[0];
        $this->config = $config;
        $this->logger->info("selected config $config");

//        $this->http->setUserAgent(self::CONFIGS[$config]['agent']);
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

    public function getSupportedCurrencies(): array
    {
        return array_keys($this->currencySite);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => $this->getSupportedCurrencies(),
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'EUR',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug('Params: ' . var_export($fields, true));

        if (in_array($fields['Currencies'][0], $this->getSupportedCurrencies()) !== true) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning("It's too much travellers");

            return [];
        }

        if ($fields['DepDate'] > strtotime('+361 day')) {
            $this->SetWarning('Date out of range');

            return [];
        }

        if (!$this->validRoute($fields)) {
            return [];
        }

        if (!empty($this->currencySite[$fields['Currencies'][0]]) && !empty($this->currencySite[$fields['Currencies'][0]]['url'])) {
            $this->currentUrl = $this->currencySite[$fields['Currencies'][0]];
        } else {
            throw new \CheckException("Not url found for this currency", ACCOUNT_ENGINE_ERROR);
        }

        try {
            $result = $this->selenium($fields);
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error($e->getMessage());
            $this->logger->error($e->getTraceAsString());

            if (time() - $this->requestDateTime < 90) {
                throw new \CheckRetryNeededException(5, 1);
            }

            throw new \CheckException('WebDriverCurlException', ACCOUNT_ENGINE_ERROR);
        }

        if (empty($result)) {
            throw new \CheckException('JSON empty', ACCOUNT_ENGINE_ERROR);
        }

        $routes = $this->parseRewardFlightsNew($result, $fields['Adults'], $fields);

        return ["routes" => $routes];
    }

    protected function savePageToLogs($selenium)
    {
        $this->logger->notice(__METHOD__);
        // save page to logs
        try {
            $selenium->http->SaveResponse();
        } catch (\ErrorException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
        }
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $this->http->SaveResponse();
    }

    private function decodeCabin($cabin)
    {
        switch ($cabin) {
            case 'ECONOMY': return 'economy';

            case 'PREMIUM ECONOMY': return 'premiumEconomy';

            case 'PREMIUM': return 'premiumEconomy';

            case 'BUSINESS': return 'business';

            case 'LA PREMIÈRE': return 'firstClass';

            case 'FIRST': return 'firstClass';
        }

        return null;
    }

    private function encodeCabin($cabin)
    {
        switch ($cabin) {
            case 'economy': return 'ECONOMY';

            case 'premiumEconomy': return 'PREMIUM';

            case 'business': return 'BUSINESS';

            case 'firstClass': return 'FIRST';
        }

        return null;
    }

    private function selenium(array $fields)
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $responseData = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->seleniumOptions->recordRequests = true;

            $resolutions = [
                [1152, 864],
                [1152, 864],
                [1280, 720],
                [1280, 800],
                [1360, 768],
                [1920, 1080],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            $fingerprint = null;

            /*            switch (self::CONFIGS[$this->config]['browser-family']) {
                            case \SeleniumFinderRequest::BROWSER_CHROMIUM:
                                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                                break;

                            case \SeleniumFinderRequest::BROWSER_CHROME:
                                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::chrome()]);

                                break;

                            case \SeleniumFinderRequest::BROWSER_FIREFOX:
                                $fingerprint = $this->services->get(FingerprintFactory::class)->getOne([FingerprintRequest::firefox()]);

                                break;
                        }

                        if ($fingerprint !== null) {
                            $selenium->seleniumOptions->fingerprint = $fingerprint->getFingerprint();
                            $selenium->http->setUserAgent($fingerprint->getUseragent());
                        }*/
            $selenium->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'], self::CONFIGS[$this->config]['browser-version']);

//            $selenium->disableImages();
//            $selenium->useCache();

            $selenium->seleniumRequest->setHotSessionPool($this->config, $this->AccountFields['ProviderCode']);

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\ErrorException $e) {
                $this->logger->error('ErrorException: ' . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            $seleniumDriver = $selenium->http->driver;
            $this->newSession = $seleniumDriver->isNewSession();

            if (strpos($selenium->http->currentUrl(), '.airfrance.') === false) {
                $selenium->http->GetURL("https://{$this->currentUrl['url']}/");

                if ($btn = $selenium->waitForElement(\WebDriverBy::id('accept_cookies_btn'), 15)) {
                    $btn->click();
                }
                $isHot = false;
            } else {
                $isHot = true;
            }

            $this->mainUrl = $url = "https://{$this->currentUrl['url']}/search/offers?pax={$fields['Adults']}:0:0:0:0:0:0:0&cabinClass={$this->encodeCabin($fields['Cabin'])}&activeConnection=0&connections={$this->routeData['depData']}:" . date("Ymd", $fields['DepDate']) . ">{$this->routeData['arrData']}&bookingFlow=REWARD";

            if ($isHot) {
                $responseData = $this->tryFetch($selenium, $url, $fields);
                $this->logger->debug("[Form responseData]: " . $responseData);
            } else {
                $responseData = $this->sendMainRequest($selenium, $url, $fields);
            }
            /* // TODO пока сохраняю камент. может быть полезен

                        if (strpos($responseData, '{"data":{"flightOffers":null},"errors":[{"extensions":{"code":"400"}') !== false
                            || strpos($responseData, '"errors":[{"extensions":{"code":"400"}}]') !== false
                            || strpos($responseData, '"errors":[{"message":"UNKNOWN_ERROR","extensions":{"code":"400"}}]') !== false
                        ) {
                            $responseData = $this->getDataFromXHR($selenium); // it's work (browser has other request with 200)
                        }

                        if (empty($responseData)
                            || strpos($responseData, '"errors":[{"message":"unexpected error occurred",') !== false
                            || strpos($responseData, '"errors":[{"extensions":{"code":"400"}}]') !== false
                            || strpos($responseData, '"errors":[{"message":"UNKNOWN_ERROR","extensions":{"code":"400"}}]') !== false
                            || (strpos($responseData, '{"data":{"flightOffers":{"offers"') !== false
                                && strpos($responseData, '"departureTime":"' . date('Y-m-d', $fields['DepDate'])) === false)
                        ) {
                            $selenium->setProxyBrightData(null, Settings::RA_ZONE_STATIC);
                            // helped
                            $selenium->driver->manage()->deleteAllCookies();
                            $responseData = $this->sendMainRequest($selenium, $url, $fields, false);
                        }

                        if (!empty($responseData)
                            && strpos($responseData, '{"data":{"flightOffers":{"offers"') !== false
                                && strpos($responseData, '"departureTime":"' . date('Y-m-d', $fields['DepDate'])) === false
                        ) {
                            $selenium->setProxyBrightData(null, Settings::RA_ZONE_STATIC);
                            $selenium->driver->executeScript('localStorage.clear();
                                    sessionStorage.clear();
                            ');
                            $selenium->driver->manage()->deleteAllCookies();
                            $responseData = $this->sendMainRequest($selenium, $url, $fields, false);
                        }
            */
            if (!empty($responseData)
                && strpos($responseData, '{"data":{"flightOffers":{"offers"') !== false
                && strpos($responseData, '"departureTime":"' . date('Y-m-d', $fields['DepDate'])) === false
            ) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (empty($responseData)) {
                $this->markConfigAsBad();

                throw new \CheckRetryNeededException(5, 0);
            }
            $this->logger->notice('Data ok, saving session');
            $selenium->keepSession(true);

            if ($this->newSession) {
                $this->logger->info("marking config {$this->config} as successful");
                \Cache::getInstance()->set('airfrance_config_' . $this->config, 1);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $allCook[$cookie['name']] = $cookie['value'];
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";

            //throw new \CheckRetryNeededException(2, 0);
        } catch (\WebDriverException $e) {
            $this->logger->error("WebDriverException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $responseData;
    }

    private function sendMainRequest($selenium, $url, $fields, $firstTime = true)
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->http->GetURL($url);
//            $this->runScriptFetch($selenium);
        } catch (\TimeOutException $e) {
            $this->logger->error('TimeOutException: ' . $e->getMessage());
            $selenium->driver->executeScript('window.stop();');
            $this->savePageToLogs($selenium);
            $selenium->http->GetURL($url);
        } catch (\WebDriverException $e) {
            $this->logger->error('WebDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\UnrecognizedExceptionException
            | \Facebook\WebDriver\Exception\UnknownErrorException
            | \Facebook\WebDriver\Exception\TimeoutException $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());
            sleep(2);
            $selenium->http->GetURL($url);
//            $this->runScriptFetch($selenium);
        }
        $this->savePageToLogs($selenium);

        if ($btn = $selenium->waitForElement(\WebDriverBy::id('accept_cookies_btn'), 15)) {
            $btn->click();
        }

        if ($selenium->waitForElement(\WebDriverBy::xpath("
                    //span[contains(text(), 'This site can’t be reached') or contains(text(), 'This page isn’t working') or contains(text(), 'This site can’t provide a secure connection')]
                    | //h1[normalize-space()='Access Denied']
                    | //h1[normalize-space()='No internet']
                "), 0)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $selenium->savePageToLogs($selenium);

        if ($btn = $selenium->waitForElement(\WebDriverBy::id('accept_cookies_btn'), 15)) {
            $btn->click();
        }

        $selenium->waitForElement(\WebDriverBy::xpath("
            //div[normlize-space()='Departing flight']
            | (//*[contains(@class, 'bw-itinerary-row')])[1]
            "), 20);
        $selenium->savePageToLogs($selenium);
        $responseData = $this->tryFetch($selenium, $url, $fields);

//        $responseData = $this->getDataFromXHR($selenium);
//        $responseData = $selenium->driver->executeScript('return localStorage.getItem("air-bounds");');

        $this->logger->debug("[Form responseData]: " . $responseData);

        return $responseData;
    }

    private function runScriptFetch($selenium): void
    {
        // TODO - ломает работу сайта на браузерах не селеноид. при запуске не работает. т.к идут редиректы и скрипт теряется
        $this->logger->notice(__METHOD__);
        $selenium->driver->executeScript(/** @lang JavaScript */ "
            localStorage.setItem('air-bounds', '');
            var constantMock = window.fetch;
            window.fetch = function() {
                console.log(arguments);
                return new Promise((resolve, reject) => {
                     constantMock.apply(this, arguments)
                         .then( (response) => { 
                                if (response.url.indexOf('/gql/v1?bookingFlow=REWARD') > -1) {
                                    response.clone().json().then(
                                        (body) => {
                                            if (JSON.stringify(body).indexOf('flightOffers') > -1)
                                                localStorage.setItem('air-bounds', JSON.stringify(body));
                                        }
                                    );
                                }
                                resolve(response);
                         }).catch((error) => {
                            reject(error);
                         })
                });
            }
        ");
    }

    private function tryFetch($selenium, $url, $fields)
    {
        $passengers = [];

        for ($i = 1; $i <= $fields['Adults']; $i++) {
            $passengers[] = ["id"=>$i, "type"=>"ADT"];
        }

        if ($selenium->waitForElement(\WebDriverBy::xpath('//script[@id="airfranceResponse"]'), 0, false)) {
            $this->logger->debug('remove old data');
            $selenium->driver->executeScript("if (document.querySelector('#airfranceResponse')) document.querySelector('#airfranceResponse').remove()");
        }
        $payload = [
            "operationName" => "SearchResultAvailableOffersQuery",
            "variables"     => [
                "activeConnectionIndex"     => 0,
                "bookingFlow"               => "REWARD",
                "availableOfferRequestBody" => [
                    "commercialCabins"     => [$this->encodeCabin($fields['Cabin'])],
                    "passengers"           => $passengers,
                    "requestedConnections" => [
                        [
                            "origin"        => ["code" => $fields['DepCode'], "type" => "AIRPORT"],
                            "destination"   => ["code" => $fields['ArrCode'], "type" => "AIRPORT"],
                            "departureDate" => date('Y-m-d', $fields['DepDate']),
                        ],
                    ],
                    "bookingFlow" => "REWARD",
                ],
                "searchStateUuid" => $this->generate_uuid(), //"721d1dcb-a4ff-4c9a-a140-92a770524185",
            ],
            "extensions" => [
                "persistedQuery" => [
                    "version"    => 1,
                    "sha256Hash" => "8cb90d80fe773d01fa8201f39737c5443a386c1d9d54fed362fa1a5471497c9a",
                ],
            ],
        ];
        /*
        $payload = [
            "operationName"=> "SearchFlightOffersForSearchQuery",
            "variables"    => [
                "flightOfferRequestBody"=> [
                    "fareOption"          => null,
                    "commercialCabins"    => [$this->encodeCabin($fields['Cabin'])],
                    "requestedConnections"=> [
                        [
                            "origin"       => ["airport"=>["code"=>$fields['DepCode']]],
                            "destination"  => ["airport"=>["code"=>$fields['ArrCode']]],
                            "departureDate"=> date('Y-m-d', $fields['DepDate']),
                        ],
                    ],
                    "passengers"        => $passengers, //[{"id":1,"type":"ADT"},{"id":2,"type":"ADT"}],
                    "negotiatedFareOnly"=> false,
                ],
                "activeConnection"=> 0,
            ],
            "extensions"=> [
                "persistedQuery"=> [
                    "version"   => 1,
                    "sha256Hash"=> "ca33f904780615e9d32772a40ffc37432d56e184df4a24a10fbc73f9fef2083b",
                ],
            ],
        ];
        https://wwws.airfrance.us/gql/v1?bookingFlow=REWARD"
        */

        $script = '
            fetch("https://wwws.airfrance.us/gql/v1?bookingFlow=LEISURE", {
                "credentials": "include",
                "headers": {
                    "Accept": "application/json;charset=utf-8",
                    "Accept-Language": "en-US",
                    "language": "en",
                    "country": "US",
                    "AFKL-Travel-Country": "US",
                    "AFKL-Travel-Language": "en",
                    "AFKL-TRAVEL-Host": "AF",
                    "X-Aviato-Host": "wwws.airfrance.us",
                    "Content-Type": "application/json",
                    "Sec-GPC": "1",
                    "Sec-Fetch-Dest": "empty",
                    "Sec-Fetch-Mode": "cors",
                    "Sec-Fetch-Site": "same-origin",
                    "Pragma": "no-cache",
                    "Cache-Control": "no-cache"
                },
                "referrer": "' . $url . '",
                "body": \'' . json_encode($payload) . '\',
                "method": "POST",
                "mode": "cors"
            })
                .then( response => response.json())
                .then( result => {
                    let script = document.createElement("script");
                    let id = "airfranceResponse";
                    script.id = id;            
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                })
        ;';
        $this->logger->info($script, ['pre' => true]);
        $selenium->driver->executeScript($script);
        sleep(3);
        $this->savePageToLogs($selenium);

        $airfranceResponse = $selenium->waitForElement(\WebDriverBy::xpath('//script[@id="airfranceResponse"]'), 20, false);

        if (!$airfranceResponse) {
            $this->savePageToLogs($selenium);

            throw new \CheckRetryNeededException(5, 0);
        }
        $airfranceResponse = $airfranceResponse->getAttribute("airfranceResponse");
        $airfranceResponse = htmlspecialchars_decode($airfranceResponse);
        $data = $this->http->JsonLog($airfranceResponse);

        if ($data !== null) {
            return $airfranceResponse;
        }

        return null;
    }

    private function generate_uuid()
    {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    private function getDataFromXHR($selenium)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->notice('ZZZ');
        $responseData = null;
        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $selenium->http->driver;

        try {
            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
        } catch (BrowserCommunicatorException $e) {
            $this->logger->error('BrowserCommunicatorException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->token = null;

        foreach ($requests as $n => $xhr) {
            if (strpos($xhr->request->getUri(), '/v1?bookingFlow=REWARD') !== false) {
                if ($xhr->response->getStatus() == 200
                    && is_array($xhr->response->getBody())
                    && (
                        isset($xhr->response->getBody()['data']['flightOffers'])
                        || (isset($xhr->response->getBody()['data']) && array_key_exists('flightOffers',
                                $xhr->response->getBody()['data'])))
                ) {
                    $responseData = json_encode($xhr->response->getBody());

                    break;
                }
                $this->logger->debug('');
                $this->logger->debug('');
                $this->logger->warning("xhr request {$n}: {$xhr->request->getVerb()} {$xhr->request->getUri()} " . json_encode($xhr->request->getHeaders()));

                if (!isset($this->token) && is_array($xhr->request->getHeaders())) {
                    $this->token = $xhr->request->getHeaders()['X-XSRF-TOKEN'] ?? null;
                }
                $this->logger->debug('');
                $this->logger->debug(json_encode($xhr->request->getBody()));
                $this->logger->debug('');
                $this->logger->debug("xhr response {$n}: {$xhr->response->getStatus()} {$xhr->response->getHeaders()}");
                $this->logger->debug('');
                $this->logger->debug(htmlentities(json_encode($xhr->response->getBody())));
            }
        }

        return $responseData;
    }

    private function parseRewardFlights($result, $cntAdults, $fields)
    {
        $data = $this->http->JsonLog($result, 2);

        if (!empty($data->errors)) {
            $msgs = [];

            foreach ($data->errors as $er) {
                if (isset($er->extensions, $er->extensions->code) && $er->extensions->code == 400 && !isset($er->message)) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $msgs[] = $er->message;
            }
            $msg = implode(', ', array_filter($msgs));
            $this->logger->error(!empty($msg) ? $msg : 'get flights error');

            if ($msg === 'Internal server error' || $msg === 'UNKNOWN_ERROR') {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (strpos($msg, 'alternative itinerary error: no outbound scheduled flight combination found for') !== false
                || strpos($msg, 'Origin Destination not permitted') !== false
                || $this->http->FindPreg("/^(?:Origin|Destination) [A-Z]{3} forbidden$/", false, trim($msg))
            ) {
                // если на этапе validRoutes не отбросили, то тут
                $this->SetWarning('The departure city you selected is not permitted. Please select another city.');
            } elseif (strpos($msg, 'origin city must be different from destination city AND neither or both') !== false) {
                // если на этапе validRoutes не отбросили, то тут
                $this->SetWarning('You must select both a departure and arrival city, and they must be different cities. Please try another search.');
            } elseif (strpos($msg, 'no outbound scheduled flight combination found for') !== false
                || strpos($msg, 'no availability found for the outbound flight combination for O&D') !== false
                || strpos($msg, 'no flights found for O&D') !== false
                || strpos($msg, 'not supported for O&D') !== false
                || strpos($msg, 'amadeus-FMFTBQ.INVALID_CITY') !== false
                || strpos($msg, 'Expected valid value（illegal value or syntax') !== false
                || strpos($msg, 'alternative itinerary error: no itineraries have been returned') !== false
            ) {
                // no outbound scheduled flight combination found for O&D : BLR-MAA
                // no availability found for the outbound flight combination for O&D : LAX-SYD
                // alternative itinerary error: no flights found for O&D : NYC-MIL
                // outbound destination not supported for O&D : RNO-POP
                $this->SetWarning('Sorry, there are no flights available for this combination of departure airport, arrival airport and travel dates. Please try again.');
            } elseif ($this->http->FindPreg("/no fare found$/", false, $msg)) {
                // no fare found
                // fallback call error: no fare found
                // alternative itinerary error: no fare found
                $this->SetWarning('Sorry, there are no fares available for this date. Please try again.');
            } elseif ($msg === 'Date out of range'
                || $msg === 'Travel dates must not be in the past.'
            ) {
                // too late or in the past
                $this->SetWarning($msg);
            } else {
                if (strpos($msg, 'unexpected error occurred') !== false) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                if ($this->fromAjax) {
                    $this->sendNotification('get ErrorMessage fromAjax // ZM');
                } else {
                    $this->sendNotification('get ErrorMessage 1 // ZM');
                }
            }

            return [];
        }

        if (empty($data->data->flightOffers) || empty($data->data->flightOffers) || empty($data->data->flightOffers->offers) || !is_array($data->data->flightOffers->offers)) {
            $this->logger->error('itineraries not found');
            $this->sendNotification('get ErrorMessage 2 // ZM');

            return [];
        }

        $routes = [];

        $this->logger->debug("Found " . count($data->data->flightOffers->offers) . " routes");

        foreach ($data->data->flightOffers->offers as $routeNumber => $it) {
            $this->logger->notice("Start route " . $routeNumber);

            $this->http->JsonLog(json_encode($it), 1);

            $segments = [];

            if (count($it->segments) === 0) {
                return 'can\'t find segments';
            }
            $layovers = [];
            $flights = [];

            if ($it->originCode !== $fields['DepCode']
                || strtotime(substr($it->departureTime, 0, 10)) !== strtotime(date('Y-m-d', $fields['DepDate']))
            ) {
                throw new \CheckRetryNeededException(5, 0);
            }

            foreach ($it->segments as $fSegment) {
                $seg = [
                    'num_stops' => count($fSegment->stopOvers ?? []),
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime($fSegment->departureTime)),
                        'dateTime' => strtotime($fSegment->departureTime),
                        'airport'  => $fSegment->originCode,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime($fSegment->arrivalTime)),
                        'dateTime' => strtotime($fSegment->arrivalTime),
                        'airport'  => $fSegment->destinationCode,
                    ],
                    'aircraft' => $fSegment->equipmentName,
                    'flight'   => ["{$fSegment->marketingCarrier}{$fSegment->marketingFlightNumber}"],
                    'airline'  => $fSegment->marketingCarrier,
                    'operator' => $fSegment->operatingCarrier,
                    'times'    => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];

                $segments[] = $seg;
            }

            foreach ($it->flightProducts as $fare) {
                $result = [
                    //'distance' => null,
                    'num_stops' => count($segments) - 1 + array_sum(array_column($segments, 'num_stops')),
                    'times'     => [
                        // total flight, not total travel
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => intdiv($fare->totalPrice, $cntAdults),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fare->totalTaxDetails->currency,
                        'taxes'    => round($fare->totalTaxDetails->totalPrice / $cntAdults, 2),
                        // or $fare->totalTaxDetails->pricePerPassengerTypes[0]->passengerType=='ADT' -> $fare->totalTaxDetails->pricePerPassengerTypes[0]->taxes
                        //'fees' => null,
                    ],
                    'tickets'        => $fare->seatsLeft,
                    'classOfService' => $this->encodeCabin($fields['Cabin']),
                ];
                $result['connections'] = $segments;
                $detailsUrl = $fare->resources->flightDetails;
                $payload = [
                    "operationName" => "SearchFlightDetailsQuery",
                    "variables"     => [
                        "uniqueResourceId" => $detailsUrl,
                    ],
                    "extensions" => [
                        "persistedQuery" => [
                            "version"    => 1,
                            "sha256Hash" => "512d179c745ad6459c006dd2431f55e26af7fd69f30fde22157a9bc0b731e3e1",
                        ],
                    ],
                ];
                $headers = [
                    'Accept'               => 'application/json, text/plain, */*',
                    'AFKL-Travel-Country'  => 'US',
                    'AFKL-TRAVEL-Host'     => 'AF',
                    'AFKL-Travel-Language' => 'en',
                    'Content-Type'         => 'application/json',
                    'country'              => 'US',
                    //                    'X-XSRF-TOKEN'         => $this->token,
                    'X-Aviato-Host'        => $this->currentUrl['url'],
                    'Referer'              => $this->mainUrl,
                ];
                $this->http->PostURL('https://wwws.airfrance.us/gql/v1?bookingFlow=REWARD', json_encode($payload), $headers);
                $flightDetails = $this->http->JsonLog(null, 1, true);

                if (isset($flightDetails['data']['flightDetailsByResourceId']['segments'])) {
                    foreach ($flightDetails['data']['flightDetailsByResourceId']['segments'] as $k => $s) {
                        $result['connections'][$k]['cabin'] = $this->decodeCabin($s['commercialCabin']);
                    }
                } else {
                    foreach ($result['connections'] as $k => $s) {
                        $result['connections'][$k]['cabin'] = $this->decodeCabin($fare->cabinClass);
                    }
                }

                $routes[] = $result;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function parseRewardFlightsNew($result, $cntAdults, $fields)
    {
        $this->logger->notice(__METHOD__);
        $data = $this->http->JsonLog($result, 2);

        if (empty($data->data->availableOffers)) {
            $this->logger->error('itineraries not found');
            $this->sendNotification('no availableOffers // ZM');

            return [];
        }

        if (empty($data->data->availableOffers->offerItineraries)) {
            if (!empty($data->data->availableOffers->warnings)) {
                if (strpos($data->data->availableOffers->warnings[0]->description, 'no fare found') !== false) {
                    $this->SetWarning($data->data->availableOffers->warnings[0]->description);
                } else {
                    $this->sendNotification('get ErrorMessage 1 // ZM');
                }

                return [];
            }
            $this->logger->error('itineraries not found');
            $this->sendNotification('get ErrorMessage 2 // ZM');

            return [];
        }

        $routes = [];

        $this->logger->debug("Found " . count($data->data->availableOffers->offerItineraries) . " routes");

        foreach ($data->data->availableOffers->offerItineraries as $routeNumber => $offerItinerary) {
            $this->logger->notice("Start route " . $routeNumber);

            $this->http->JsonLog(json_encode($offerItinerary), 1);

            $it = $offerItinerary->activeConnection;
            $offers = $offerItinerary->upsellCabinProducts;
            $segments = [];

            if (count($it->segments) === 0) {
                return 'can\'t find segments';
            }

            foreach ($it->segments as $fSegment) {
                $seg = [
                    'num_stops' => count($fSegment->stopsAt ?? []),
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime(str_replace("T", " ", $fSegment->departureDateTime))),
                        'dateTime' => strtotime(str_replace("T", " ", $fSegment->departureDateTime)),
                        'airport'  => $fSegment->origin->code,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime(str_replace("T", " ", $fSegment->arrivalDateTime))),
                        'dateTime' => strtotime(str_replace("T", " ", $fSegment->arrivalDateTime)),
                        'airport'  => $fSegment->destination->code,
                    ],
                    'aircraft' => $fSegment->equipmentName,
                    'flight'   => ["{$fSegment->marketingFlight->carrier->code}{$fSegment->marketingFlight->number}"],
                    'airline'  => $fSegment->marketingFlight->carrier->code,
                    'operator' => $fSegment->marketingFlight->operatingFlight->carrier->code ?? null,
                    'times'    => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                ];

                $segments[] = $seg;
            }

            foreach ($offers as $offer) {
                if (count($offer->connections) > 1) {
                    $this->sendNotification("more then one connection // ZM");
                }

                if (count($offer->connections) === 0 || !isset($offer->connections[0]->tax)) {
                    $this->logger->warning("skip offer");

                    continue;
                }
                $fare = $offer->connections[0];
                $result = [
                    //'distance' => null,
                    'num_stops' => count($segments) - 1 + array_sum(array_column($segments, 'num_stops')),
                    'times'     => [
                        // total flight, not total travel
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => intdiv($fare->price->amount, $fare->passengerCount),
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fare->tax->currencyCode,
                        'taxes'    => round($fare->tax->amount / $fare->passengerCount, 2),
                        // or $fare->totalTaxDetails->pricePerPassengerTypes[0]->passengerType=='ADT' -> $fare->totalTaxDetails->pricePerPassengerTypes[0]->taxes
                        //'fees' => null,
                    ],
                    'tickets'        => $fare->numberOfSeatsAvailable,
                    'classOfService' => $fare->cabinClassTitle,
                ];
                $result['connections'] = $segments;
                /*                $detailsUrl = $fare->resourceIds->flightDetails;
                                $payload = [
                                    "operationName" => "SearchFlightDetailsQuery",
                                    "variables"     => [
                                        "uniqueResourceId" => $detailsUrl,
                                    ],
                                    "extensions" => [
                                        "persistedQuery" => [
                                            "version"    => 1,
                                            "sha256Hash" => "512d179c745ad6459c006dd2431f55e26af7fd69f30fde22157a9bc0b731e3e1",
                                        ],
                                    ],
                                ];
                                $headers = [
                                    'Accept'               => 'application/json, text/plain, * /*',
                                    'AFKL-Travel-Country'  => 'US',
                                    'AFKL-TRAVEL-Host'     => 'AF',
                                    'AFKL-Travel-Language' => 'en',
                                    'Content-Type'         => 'application/json',
                                    'country'              => 'US',
                                    //                    'X-XSRF-TOKEN'         => $this->token,
                                    'X-Aviato-Host'        => $this->currentUrl['url'],
                                    'Referer'              => $this->mainUrl,
                                ];
                                $this->http->PostURL('https://wwws.airfrance.us/gql/v1?bookingFlow=REWARD', json_encode($payload), $headers);
                                $flightDetails = $this->http->JsonLog(null, 1, true);

                                if (isset($flightDetails['data']['flightDetailsByResourceId']['segments'])) {
                                    foreach ($flightDetails['data']['flightDetailsByResourceId']['segments'] as $k => $s) {
                                        $result['connections'][$k]['cabin'] = $this->decodeCabin($s['commercialCabin']);
                                    }
                                } else {
                */
                foreach ($result['connections'] as $k => $s) {
                    $result['connections'][$k]['cabin'] = $this->decodeCabin($fare->cabinClass);
                }
//                }

                $routes[] = $result;
            }
        }
        $this->logger->debug('Parsed data:');
        $this->logger->debug(var_export($routes, true), ['pre' => true]);

        return $routes;
    }

    private function validRoute($fields)
    {
        // set default
        $this->routeData = [
            'depData' => $fields['DepCode'] . ':A',
            'arrData' => $fields['ArrCode'] . ':A',
        ];

        $validCodes = \Cache::getInstance()->get('ra_airfrance_locations');

        if (empty($validCodes) || !is_array($validCodes)
            || !array_key_exists($fields['DepCode'], $validCodes)
            || !array_key_exists($fields['ArrCode'], $validCodes)
        ) {
            $validCodes = [];
            $http2 = new \HttpBrowser("none", new \CurlDriver());
            $this->http->brotherBrowser($http2);
            $headers = $this->headers;
            $headers['Accept'] = 'application/json, text/plain, */*';
            $headers['Referer'] = 'https://wwws.airfrance.us/?bookingFlow=REWARD&activeConnection=0&cabinClass=ECONOMY&pax=1:0:0:0:0:0:0:0';
            $http2->GetURL("https://wwws.airfrance.us");
            $http2->GetURL("https://wwws.airfrance.us/gql/v1?bookingFlow=REWARD&country=US&language=en&operationName=UbcSearchBoxReferenceDataForSearch&variables=%7B%22bookingFlow%22:%22REWARD%22%7D&extensions=%7B%22persistedQuery%22:%7B%22version%22:1,%22sha256Hash%22:%2234679346e05c97bd0b3b77d961767ea479739fa3f3efb082607f13e48496fab4%22%7D%7D",
                $headers);

            $data = $http2->JsonLog(null, 1, true);

            if (!$data || !isset($data['data']['stations'])) {
                $http2->GetURL("https://wwws.airfrance.us/gql/v1?bookingFlow=REWARD&country=US&language=en&operationName=UbcSearchBoxReferenceDataForSearch&variables=%7B%22bookingFlow%22:%22REWARD%22%7D&extensions=%7B%22persistedQuery%22:%7B%22version%22:1,%22sha256Hash%22:%22e3543538b21018dfab85ac006319844619705ebb0177750976cd67458395561d%22%7D%7D",
                    $headers);
                $data = $http2->JsonLog(null, 1, true);
            }

            if (!$data || !isset($data['data']['stations'])) {
                // try to search in any way
                return true;
            }

            foreach ($data['data']['stations'] as $station) {
                if (count($station['stationList']) > 1
                    || (isset($station['stationList'][0]['code']) && $station['stationList'][0]['code'] !== $station['code'])
                ) {
                    $validCodes[$station['code']]['C'] = [
                        'cityCode'      => $station['code'],
                        'isOrigin'      => $station['isOrigin'],
                        'isDestination' => $station['isDestination'],
                    ];
                }

                foreach ($station['stationList'] as $row) {
                    $validCodes[$row['code']]['A'] = [
                        'cityCode'      => $station['code'],
                        'stationType'   => $row['stationType'],
                        'isOrigin'      => $station['isOrigin'],
                        'isDestination' => $station['isDestination'],
                    ];
                }
            }

            if (empty($validCodes)) {
                // try to search in any way
                return true;
            }
            \Cache::getInstance()->set('ra_airfrance_locations', $validCodes, 60 * 60 * 24);
        }

        if (!array_key_exists($fields['DepCode'], $validCodes)
            || !$this->isStation($validCodes[$fields['DepCode']], 'isOrigin')
        ) {
            $this->SetWarning('The departure city you selected is not permitted. Please select another city.');

            return false;
        }

        if (!array_key_exists($fields['ArrCode'], $validCodes)
            || !$this->isStation($validCodes[$fields['ArrCode']], 'isDestination')
        ) {
            $this->SetWarning('The arrival city you have selected is not permitted. Please select another city.');

            return false;
        }

        if ($this->sameCity($validCodes[$fields['DepCode']], $validCodes[$fields['ArrCode']])) {
            $this->SetWarning('You must select both a departure and arrival city, and they must be different cities. Please try another search.');

            return false;
        }

        $this->routeData = [
            'depData' => isset($validCodes[$fields['DepCode']]['C']) ? $fields['DepCode'] . ':C' : $fields['DepCode'] . ':A',
            'arrData' => isset($validCodes[$fields['ArrCode']]['C']) ? $fields['ArrCode'] . ':C' : $fields['ArrCode'] . ':A',
        ];

        return true;
    }

    private function isStation(array $code, string $type)
    {
        return (isset($code['C']) && $code['C'][$type]) || (isset($code['A']) && $code['A'][$type]);
    }

    private function sameCity(array $codeA, array $codeB)
    {
        $cityA = $codeA['C']['cityCode'] ?? ($codeA['A']['cityCode'] ?? null);
        $cityB = $codeB['C']['cityCode'] ?? ($codeB['A']['cityCode'] ?? null);

        if (!$cityA || !$cityB) {
            $this->logger->debug(var_export($codeA, true));
            $this->logger->debug(var_export($codeB, true));
            $this->sendNotification("check route detect");

            throw new \CheckException('check route detect', ACCOUNT_ENGINE_ERROR);
        }

        return $cityA === $cityB;
    }

    private function markConfigAsBad(): void
    {
        if ($this->newSession) {
            $this->logger->info("marking config {$this->config} as bad");
            \Cache::getInstance()->set('airfrance_config_' . $this->config, 0);
        }
    }
}
