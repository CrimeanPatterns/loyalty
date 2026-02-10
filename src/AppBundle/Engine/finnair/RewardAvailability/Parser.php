<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountCheckerFinnair
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $supportedCurrencies = ['USD'];
    private $codesForSearch = [];
    private $validRouteProblem;
    private $response;
    private $bearerToken;
    private $sensorData;
    private $_abck;
    private $sensorDataUrl;
    private $fingerprint;

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = 100;
        $request->platform = (random_int(0, 1)) ? 'MacIntel' : 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);
        $this->http->setUserAgent($this->fingerprint->getUseragent());

        $array = ['us', 'es', 'de'];
        $targeting = $array[array_rand($array)];

        if ($this->attempt > 1) {
            $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
        } else {
            $regions = ['us', 'au', 'ca'];
            $region = $regions[random_int(0, count($regions) - 1)];

            if ($this->AccountFields['ParseMode'] === 'awardwallet') {
                $this->setProxyGoProxies(null, $region);
            } else {
                $this->setProxyBrightData(null, Settings::RA_ZONE_STATIC, $targeting);
            }
        }
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.finnair.com/en' => 'search page'];
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => $this->supportedCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));
        $this->logger->debug(__METHOD__);

        $fields['Cabin'] = $this->getCabinField($fields['Cabin']);

        if (!in_array($fields['Currencies'][0], $this->supportedCurrencies)) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        $this->setBearerToken();

        if (!$this->bearerToken) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $data = [
            "cabin"         => 'MIXED',
            "locale"        => "en",
            "adults"        => $fields['Adults'],
            "c15s"          => 0,
            "children"      => 0,
            "infants"       => 0,
            "directFlights" => false,
            'isAward'       => true,
            "itinerary"     => [
                [
                    "departureDate"           => date('Y-m-d', $fields['DepDate']),
                    "departureLocationCode"   => $fields['DepCode'],
                    "destinationLocationCode" => $fields['ArrCode'],
                    "isRequestedBound"        => true,
                ],
            ],
        ];

        $headers = [
            "Content-Type"        => "application/json",
            "Accept"              => "application/json",
            "Accept-Encoding"     => "gzip, deflate, br, zstd",
            "Accept-Language"     => "q=0.9,en-US;q=0.8,en;q=0.7",
            "Authorization"       => $this->bearerToken,
        ];

        try {
            $this->http->PostURL('https://api.finnair.com/d/fcom/offers-prod/current/api/offerList', json_encode($data), $headers);

            if (isset($this->http->Response["code"]) && $this->http->Response["code"] != 200) {
                $this->logger->error('finnair responded with invalid status code: ' . $this->http->Response["body"]);

                throw new \CheckRetryNeededException(5, 0);
            }

            $response = $this->http->JsonLog(null, 3, true);

            return ['routes' => $this->parseData($response, $fields)];
        } catch (\WebDriverCurlException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }
    }

    protected function getCabinField(string $cabinKey)
    {
        $this->logger->debug(__METHOD__);

        if ($cabinKey === 'firstClass') {
            throw new \ProviderError("Cabin key is wrong. Provider has no cabin of class $cabinKey.");
        }

        $cabins = [
            'economy'        => 'ECONOMY',
            'premiumEconomy' => 'ECOPREMIUM',
            'firstClass'     => null, // has no this cabin
            'business'       => 'BUSINESS',
        ];

        return $cabins[$cabinKey];
    }

    protected function sendSensorData()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");

            $selenium->UseSelenium();
            $this->seleniumOptions->recordRequests = true;

            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

            if ($this->fingerprint) {
                $selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
                $selenium->http->setUserAgent($this->fingerprint->getUseragent());
            }

            $selenium->keepCookies(true);

            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->Start();
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL('https://www.finnair.com/en');
            $selenium->saveResponse();

            $this->sensorDataUrl =
                $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><link rel=\"stylesheet\"#")
                ?? $this->http->FindPreg("# src=\"([^\"]+)\"><\/script><\/body>#")
            ;

            if (!$this->sensorDataUrl) {
                $this->logger->error("sensor_data url not found");

                return null;
            }

            $this->http->NormalizeURL($sensorDataUrl);

            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();
            $i = array_search('_abck', array_column($selenium->getAllCookies(), 'name'));
            $this->_abck = $selenium->getAllCookies()[$i]['value'];

            $this->logger->notice("key: {$this->_abck}");
            $this->DebugInfo = "key: {$this->_abck}";
            $this->http->setCookie("_abck", $this->_abck);

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), $this->sensorDataUrl) !== false) {
                    if ($xhr->response->getStatus() == 200
                        && isset($xhr->request->getBody()['sensor_data'])
                    ) {
                        $this->sensorData = $xhr->request->getBody()['sensor_data'];

                        break;
                    }
                }
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                throw $e;
            }
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "New session attempts retry count exceeded";

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }
    }

    protected function setBearerToken()
    {
        $this->logger->notice(__METHOD__);

        $allCookies = array_merge($this->http->GetCookies(".finnair.com"), $this->http->GetCookies(".finnair.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies(".finnair.com", "/", true));
        $allCookiesAuth = array_merge($this->http->GetCookies("auth.finnair.com"), $this->http->GetCookies("auth.finnair.com", "/", true));
        $allCookiesAuth = array_merge($allCookiesAuth, $this->http->GetCookies(".auth.finnair.com", "/", true));
        $allCookiesAuth = array_merge($allCookiesAuth, $this->http->GetCookies("auth.finnair.com", "/cas", true));

        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");

            $selenium->UseSelenium();
            $this->seleniumOptions->recordRequests = true;

            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);

            if ($this->fingerprint) {
                $selenium->seleniumOptions->userAgent = $this->fingerprint->getUseragent();
                $selenium->http->setUserAgent($this->fingerprint->getUseragent());
            }

            $selenium->keepCookies(true);

            $selenium->http->saveScreenshots = true;
            $selenium->disableImages();

            $selenium->http->start();
            $selenium->Start();
            /** @var \SeleniumDriver $seleniumDriver */
            $seleniumDriver = $selenium->http->driver;

            $selenium->driver->manage()->window()->maximize();

            $selenium->http->GetURL("https://www.finnair.com/en/fdsfoj");

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".finnair.com"]);
            }

            $selenium->http->GetURL("https://auth.finnair.com/content/en/join/finnair-plus");

            foreach ($allCookiesAuth as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".auth.finnair.com"]);
            }

            $selenium->http->GetURL("https://www.finnair.com/en");

            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            foreach ($requests as $n => $xhr) {
                if (strpos($xhr->request->getUri(), '/current/api/profile') !== false) {
                    if ($xhr->response->getStatus() == 200
                        && isset($xhr->request->getHeaders()['Authorization'])
                    ) {
                        $this->bearerToken = $xhr->request->getHeaders()['Authorization'];

                        break;
                    }
                }
            }
        } catch (\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverException | \WebDriverException $e) {
            $this->logger->error($e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception $e) {
            if (strpos($e->getMessage(), 'New session attempts retry count exceeded') === false) {
                throw $e;
            }
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "New session attempts retry count exceeded";

            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            $this->logger->debug("Your Bearer Token: " . $this->bearerToken);
        }
    }

    private function parseData(array $data, array $fields)
    {
        $this->logger->debug(__METHOD__);

        if (isset($data['errorMessage'])
            || (isset($data['messages']['level']) && $data['messages']['level'] === 'ERROR')
            && !isset($data['boundGroups'])
        ) {
            $error = $data['errorMessage'] ?? $data['status'];

            if ($error == 'NO_FLIGHTS_FOUND') {
                $this->SetWarning($error);

                return [];
            }

            $this->logger->error($error);

            throw new \CheckRetryNeededException(5, 0);
        }

        $offers = $data['offers'];
        $outbounds = $data['outbounds'];
        $currency = $data['currency'];
        $fareFamilies = $data['fareFamilies'];

        $routes = [];
        $i = 0;

        foreach ($offers as $key => $offer) {
            $routes[$i]['distance'] = null;
            $routes[$i]['num_stops'] = $outbounds[$offer['outboundId']]['stops'];
            $routes[$i]['payments'] = [
                'currency' => $currency,
                'taxes'    => round(($offer['totalPrice'] / $fields['Adults']), 2),
                'fees'     => null,
            ];
            $routes[$i]['tickets'] = $outbounds[$offer['outboundId']]['quotas'][$offer['outboundFareFamily']];
            $routes[$i]['award_type'] = $fareFamilies[$offer['outboundFareFamily']]['fareFamilyCode'];
            $routes[$i]['classOfService'] = $offer['outboundFareInformation'][0]['cabinClass'];

            foreach ($outbounds[$offer['outboundId']]['itinerary'] as $segment) {
                $routes[$i]['connections'][] = [
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($segment['departure']['dateTime'], 0, 19))),
                        'airport'  => $segment['departure']['locationCode'],
                        'terminal' => $segment['departure']['terminal'],
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($segment['arrival']['dateTime'], 0, 19))),
                        'airport'  => $segment['arrival']['locationCode'],
                        'terminal' => $segment['arrival']['terminal'],
                    ],
                    'cabin'      => $offer['outboundFareInformation'][0]['cabinClass'],
                    'fare_class' => $offer['outboundFareInformation'][0]['bookingClass'],
                    'flight'     => $segment['flightNumber'],
                    'airline'    => $segment['operatingAirlineCode'],
                    'operator'   => $segment['operatingAirlineCode'],
                    'aircraft'   => $segment['aircraftCode'],
                    'tickets'    => $outbounds[$offer['outboundId']]['quotas'][$offer['outboundFareFamily']],
                    'meal'       => null,
                ];
            }

            $routes[$i]['redemptions'] = [
                'miles'   => round(($offer['totalPointsPrice'] / $fields['Adults']), 2),
                'program' => $this->AccountFields['ProviderCode'],
            ];
            $i++;
        }

        return $routes;
    }
}
