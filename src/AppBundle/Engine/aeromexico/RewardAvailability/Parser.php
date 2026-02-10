<?php

namespace AwardWallet\Engine\aeromexico\RewardAvailability;

use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountChecker
{
    use \PriceTools;
    use ProxyList;
    use \SeleniumCheckerHelper;

    public $isRewardAvailability = true;

    private $exchange;

    public static function getRASearchLinks(): array
    {
        return ['https://vuelaconpuntos.aeromexicorewards.com/' => 'search page'];
    }

    public static function GetAccountChecker($accountInfo)
    {
        return new static();
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();
        $this->UseSelenium();
        $this->KeepState = true;

        $this->seleniumRequest->setHotSessionPool(self::class, $this->AccountFields['ProviderCode']);

        if (random_int(0, 1)) {
            $regions = ['fi', 'de', 'us'];
            $this->setProxyBrightData(null, 'static', $regions[random_int(0, count($regions) - 1)]);
        } else {
            $this->setProxyDOP(Settings::DATACENTERS_NORTH_AMERICA);
        }

        $resolutions = [
            //            [1152, 864],
            //            [1280, 720],
            //            [1280, 768],
            [1280, 800],
            [1360, 768],
            [1366, 768],
        ];
        $resolution = $resolutions[array_rand($resolutions)];
        $this->setScreenResolution($resolution);

        $this->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->usePacFile(false);
        $this->seleniumOptions->userAgent = null;
        $this->http->setUserAgent(null);

        $this->disableImages();
        $this->http->saveScreenshots = true;
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['MXN', 'USD'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'MXN',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if (!in_array($fields['Currencies'][0], ['MXN', 'USD'])) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 7) {
            $this->SetWarning("over max adults");

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime("+330 days")) {
            $this->SetWarning("too late flight");

            return ['routes' => []];
        }

        try {
            $this->http->GetURL("https://vuelaconpuntos.clubpremier.com/");
            sleep(7);
            $this->logger->debug("[Current URL]: {$this->http->currentUrl()}");

            $this->waitFor(function () {
                return !$this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'Loading__Wrapper')]"), 0);
            }, 15);

            if ($this->waitForElement(\WebDriverBy::xpath("//div[contains(@class,'Loading__Wrapper')]"), 0)) {
                if ($this->attempt < 3) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($soloIda = $this->waitForElement(\WebDriverBy::xpath("//p[contains(normalize-space(),'lo ida')]/ancestor::div[1]"),
                0)) {
                $soloIda->click();
                $this->saveResponse();
            }

            $locations = [];
            $this->driver->executeScript($script = '
            fetch("https://apibpamr.aeromexicorewards.com/cp/searchairports", {
              "headers": {
                "accept": "*/*",
                "accept-language": "en",
                "content-type": "application/json",
                "sec-fetch-dest": "empty",
                "sec-fetch-mode": "cors",
                "sec-fetch-site": "same-site"
              },
              "referrer": "https://vuelaconpuntos.aeromexicorewards.com/",
              "referrerPolicy": "strict-origin-when-cross-origin",
              "body": "{\"language\":\"ES\",\"canal\":\"WEB\",\"user\":\"\",\"customerClient\":\"\",\"deviceType\":\"Desktop Linux\",\"browser\":\"Chrome\"}",
              "method": "POST",
              "mode": "cors",
              "credentials": "omit"
            }).then( response => response.json())
              .then( result => {
                let script = document.createElement("script");
                let id = "searchairports";
                script.id = id;
                script.setAttribute(id, JSON.stringify(result));
                document.querySelector("body").append(script);
            });
        ');
            $this->logger->info($script, ['pre' => true]);
            $searchAirports = $this->waitForElement(\WebDriverBy::xpath('//script[@id="searchairports"]'), 10, false);
            $this->saveResponse();

            if (!$searchAirports) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $searchAirportsData = $this->http->JsonLog($searchAirports->getAttribute("searchairports"), 1, true);

            if (!isset($searchAirportsData['response'])) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (isset($searchAirportsData['response']) && is_array($searchAirportsData['response'])) {
                foreach ($searchAirportsData['response'] as $item) {
                    $locations[$item['tiataCode']] = [
                        'id'         => $item['cidAirport'],
                        'zoneOrigin' => $item['maczone']['ciataCode'],
                    ];
                }
            }
            $this->logger->debug("keep session");
            $this->keepSession(true);

            $idBlogLog = $searchAirportsData['headerResponse']['idBlogLog'];
            $reservationNumber = $searchAirportsData['headerResponse']['reservationNumber'];

            if (!array_key_exists($fields['DepCode'], $locations)) {
                $this->SetWarning('no flights from ' . $fields['DepCode']);

                return ['routes' => []];
            }

            if (!array_key_exists($fields['ArrCode'], $locations)) {
                $this->SetWarning('no flights to ' . $fields['ArrCode']);

                return ['routes' => []];
            }

            if ($fields['Currencies'][0] === ['MXN']) {
                $this->exchange = 1;
            } else {
                $this->driver->executeScript($script = '
                fetch("https://apibpamr.aeromexicorewards.com/cp/getexchangerate", {
                  "headers": {
                    "accept": "*/*",
                    "accept-language": "en",
                    "content-type": "application/json",
                    "sec-fetch-dest": "empty",
                    "sec-fetch-mode": "cors",
                    "sec-fetch-site": "same-site"
                  },
                  "referrer": "https://vuelaconpuntos.aeromexicorewards.com/",
                  "referrerPolicy": "strict-origin-when-cross-origin",
                  "body": "{\"header\":{\"idBlogLog\":' . $idBlogLog . ',\"reservationNumber\":\"' . $reservationNumber . '\"},\"currencyCode\":\"' . $fields['Currencies'][0] . '\"}",
                  "method": "POST",
                  "mode": "cors",
                  "credentials": "omit"
                }).then( response => response.json())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "getexchangerate";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
            ');
                $this->logger->info($script, ['pre' => true]);

                $getExchangeRate = $this->waitForElement(\WebDriverBy::xpath('//script[@id="getexchangerate"]'), 10,
                    false);
                $this->saveResponse();

                if (!$getExchangeRate) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $getExchangeRateData = $this->http->JsonLog($getExchangeRate->getAttribute("getexchangerate"), 1, true);

                if (!isset($getExchangeRateData['response'])) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->exchange = $getExchangeRateData['response']['value'];
            }

            $fields['origin'] = $locations[$fields['DepCode']];
            $fields['destination'] = $locations[$fields['ArrCode']];

            $dateStr = date("Y-m-d", $fields['DepDate']);

            $script = 'fetch("https://apibpamr.aeromexicorewards.com/cp/searchflights", {
                  "headers": {
                    "accept": "*/*",
                    "accept-language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
                    "content-type": "application/json",
                    "sec-fetch-dest": "empty",
                    "sec-fetch-mode": "cors",
                    "sec-fetch-site": "same-site"
                  },
                  "referrer": "https://vuelaconpuntos.aeromexicorewards.com/",
                  "referrerPolicy": "strict-origin-when-cross-origin",
                  "body": "{\"header\":{\"solutionSet\":\"\",\"solutionId\":\"\",\"sessionId\":\"\",\"idBlogLog\":' . $idBlogLog . ',\"reservationNumber\":\"' . $reservationNumber . '\",\"exchangerate\":{\"value\":\"' . $this->exchange . '\",\"code\":\"' . $fields['Currencies'][0] . '\",\"simbol\":\"$\"},\"language\":\"ES\",\"canal\":\"WEB\"},\"user\":{},\"searchParameters\":{\"typeFlight\":\"ONE WAY\",\"typeSegment\":\"OUT GOING\",\"passenger\":[{\"type\":\"adult\",\"count\":' . $fields['Adults'] . '}],\"slice\":{\"origin\":\"' . $fields['DepCode'] . '\",\"originId\":' . $fields['origin']['id'] . ',\"destination\":\"' . $fields['ArrCode'] . '\",\"destinationId\":' . $fields['destination']['id'] . ',\"date\":\"' . $dateStr . '\",\"zoneOrigin\":\"' . $fields['origin']['zoneOrigin'] . '\",\"zoneDestination\":\"' . $fields['destination']['zoneOrigin'] . '\"},\"couponCode\":\"\"}}",
                  "method": "POST",
                  "mode": "cors",
                  "credentials": "omit"
                }).then( response => response.json())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "searchflights";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });';
            $this->logger->info($script, ['pre' => true]);

            $this->driver->executeScript($script);
            sleep(10);
            $this->driver->executeScript($script);

//            $searchFlights = $this->waitForElement(\WebDriverBy::xpath('//script[@id="searchflights"]'), 20, false);
            if (!$searchFlights = $this->waitForElement(\WebDriverBy::xpath('//script[@id="searchflights"][@searchflights[not(contains(.,"LOOKING_FOR_FLIGHTS"))]]'),
                20, false)) {
                $this->logger->info('[retry script above]');
                $this->driver->executeScript($script);
                $searchFlights = $this->waitForElement(\WebDriverBy::xpath('//script[@id="searchflights"][@searchflights[not(contains(.,"LOOKING_FOR_FLIGHTS"))]]'),
                    20, false);
            }

            $this->saveResponse();

            if (!$searchFlights) {
                throw new \CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);

                throw new \CheckRetryNeededException(5, 0);
            }
            $searchFlightsData = $this->http->JsonLog($searchFlights->getAttribute("searchflights"), 1);

            if (isset(
                    $searchFlightsData->response,
                    $searchFlightsData->response->searchParameters,
                    $searchFlightsData->response->searchParameters->slice
                )
                && ($searchFlightsData->response->searchParameters->slice->origin !== $fields['DepCode']
                    || $searchFlightsData->response->searchParameters->slice->destination !== $fields['ArrCode']
                    || $searchFlightsData->response->searchParameters->slice->date !== $dateStr
                )
            ) {
                // retrying script above is not work
                throw new \CheckRetryNeededException(5, 0);
            }

            if ((isset($searchFlightsData->status, $searchFlightsData->error) && $searchFlightsData->status == '500' && $searchFlightsData->error === 'Internal Server Error')
                || (isset($searchFlightsData->status, $searchFlightsData->error) && $searchFlightsData->status == '404' && $searchFlightsData->error === 'Not Found')
            ) {
                throw new \CheckRetryNeededException(5, 10);
            }

            if (isset($searchFlightsData->error, $searchFlightsData->error->errorCode)
                && (
                ($searchFlightsData->error->errorCode == '-500' && $searchFlightsData->error->message === 'Error inesperado reportar a administradores')
                )
            ) {
                $this->logger->error($searchFlightsData->error->message);
                // "Algo salió mal, por favor intenta más tarde"
                throw new \CheckException("!Warning!Something went wrong, please try again later", ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($searchFlightsData->error, $searchFlightsData->error->errorCode)
                && ($searchFlightsData->error->errorCode == '-430' && $searchFlightsData->error->message === 'Error al consultar en ITA')
            ) {
                $this->logger->error($searchFlightsData->error->message);

                throw new \CheckRetryNeededException(5, 10, "!Warning!Something went wrong, please try again later", ACCOUNT_PROVIDER_ERROR);
            }

            if (isset($searchFlightsData->error, $searchFlightsData->error->errorCode) && $searchFlightsData->error->errorCode == '-440') {
                $this->logger->error($searchFlightsData->error->message);
                $this->logger->error('No se encontraron vuelos en ITA');
                $this->SetWarning('No flights found, please try with different dates');

                return ['routes' => []];
            }

            if (!isset($searchFlightsData->response)) {
                throw new \CheckRetryNeededException(5, 0);
            }

            if (!isset($searchFlightsData->response->flightDetails)) {
                if (isset($searchFlightsData->error, $searchFlightsData->error->errorCode) && in_array($searchFlightsData->error->errorCode,
                        ['-500', '-430'])) {
                    $msg = $searchFlightsData->error->message;
                } else {
                    if (isset($searchFlightsData->response->searchFlightStatus->description)
                        && $searchFlightsData->response->searchFlightStatus->description === 'LOOKING_FOR_FLIGHTS') {
                        $msg = 'LOOKING_FOR_FLIGHTS';
                    } else {
                        $this->sendNotification('check error // ZM');
                        $msg = 'something went wrong';
                    }
                }

                throw new \CheckException($msg, ACCOUNT_ENGINE_ERROR);
            }

            if (isset($searchFlightsData->response->flightDetails) && is_array($searchFlightsData->response->flightDetails) && empty($searchFlightsData->response->flightDetails)) {
                $this->SetWarning('boletos agotados');

                return ['routes' => []];
            }

            $flightDetailsGoverning = [];
            $extData = null;

            foreach ($searchFlightsData->response->flightDetails as $flight) {
                $yes = false;

                $products = [];

                if (isset($flight->coachProducts) && !empty($flight->coachProducts)) {
                    foreach ($flight->coachProducts as $product) {
                        $products[] = $product;
                    }
                }

                if (isset($flight->businessProducts) && !empty($flight->businessProducts)) {
                    foreach ($flight->businessProducts as $product) {
                        $products[] = $product;
                    }
                    $products[] = $flight->businessProducts[0];
                }

                if (empty($products)) {
                    $products = $flight->products;
                    $oldVersion = true;
                }

                foreach ($products as $product) {
                    if ($product->governingCarrier) {
                        $yes = true;

                        continue;
                    }

                    if ($yes && !$product->unavaliable) {
                        $this->sendNotification("check request when governingCarrier true|false in one flight  // ZM");
                    }
                }

                if ($yes) {
                    $flightDetailsGoverning[] = $flight;
                }
            }

            if (isset($oldVersion) && !empty($flightDetailsGoverning)) {
                $payload = "{\"searchParameters\":{\"searchParameters\":" . json_encode($searchFlightsData->response->searchParameters) . ",\"header\":{\"idBlogLog\":{$idBlogLog},\"reservationNumber\":\"{$reservationNumber}\",\"language\":\"IN\",\"user\":null,\"canal\":null,\"customerClient\":null,\"networkName\":null,\"exchangerate\":{\"value\":\"{$this->exchange}\",\"code\":\"{$fields['Currencies'][0]}\",\"simbol\":\"$\"}}},\"flightDetailsGoverning\":" . json_encode($flightDetailsGoverning) . ",\"countCallGC\":1}";
                $payload = str_replace('\/', '/', $payload);
                $this->driver->executeScript($script = '
                fetch("https://apibpamr.aeromexicorewards.com/cp/getquotetaxesgc", {
                  "headers": {
                    "accept": "*/*",
                    "accept-language": "ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7",
                    "content-type": "application/json",
                    "sec-fetch-dest": "empty",
                    "sec-fetch-mode": "cors",
                    "sec-fetch-site": "same-site"
                  },
                  "referrer": "https://vuelaconpuntos.aeromexicorewards.com/",
                  "referrerPolicy": "strict-origin-when-cross-origin",
                  "body": "' . $payload . '",
                  "method": "POST",
                  "mode": "cors",
                  "credentials": "omit"
                }).then( response => response.json())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "getquotetaxesgc";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                });
            ');
                $this->logger->info($script, ['pre' => true]);

                $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="getquotetaxesgc"]'), 10, false);
                $this->saveResponse();

                if (!$ext) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $extData = $this->http->JsonLog($ext->getAttribute("getquotetaxesgc"), 1);
            }
        } catch (\WebDriverCurlException | \Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("WebDriverCurlException: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\InvalidSessionIdException
        | \Facebook\WebDriver\Exception\UnknownErrorException // unknown error: net::ERR_TUNNEL_CONNECTION_FAILED
        | \Facebook\WebDriver\Exception\UnrecognizedExceptionException $e
        ) {
            $this->logger->error("Exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Facebook\WebDriver\Exception\ScriptTimeoutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        } catch (\Exception | \TypeError $e) {
            $this->logger->error($e->getMessage(), ['pre' => true]);
            $this->logger->error($e->getTraceAsString(), ['pre' => true]);

            if (strpos($e->getMessage(), 'Array to string conversion') !== false
                || strpos($e->getMessage(), 'strlen() expects parameter 1 to be string, array given') !== false
                || strpos($e->getMessage(), 'must be of the type string or null, array given') !== false
                || strpos($e->getMessage(),
                    'Argument 1 passed to Facebook\WebDriver\Remote\JsonWireCompat::getElement()') !== false
            ) {
                // TODO бага селениума
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        return ['routes' => $this->parseRewardFlights($fields, $searchFlightsData, $extData)];
    }

    private function getCabinFields($onlyKeys = true): array
    {
        $cabins = [
            'economy'        => 'COACH',
            'premiumEconomy' => 'PREMIUM-COACH',
            'firstClass'     => 'BUSINESS', // TODO
            'business'       => 'BUSINESS',
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function parseRewardFlights($fields, $data, $extData): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info("ParseReward [" . date('Y-m-d',
                $fields['DepDate']) . "-" . $fields['DepCode'] . "-" . $fields['ArrCode'] . "]",
            ['Header' => 2]);

        $routes = [];

//        $passengers = $data->response->searchParameters->passenger;
//        foreach ($passengers as ){
//
//        }
        $flightDetails = $data->response->flightDetails;
        $this->logger->debug("Found " . count($flightDetails) . " routes");
        $cabins = array_flip($this->getCabinFields(false));

        foreach ($flightDetails as $numRoute => $it) {
            $this->logger->info('route #' . $numRoute);
            $this->http->JsonLog(json_encode($it), 1);
            $products = [];

            if (isset($it->coachProducts) && !empty($it->coachProducts)) {
                foreach ($it->coachProducts as $product) {
                    $products[] = $product;
                }
            }

            if (isset($it->businessProducts) && !empty($it->businessProducts)) {
                foreach ($it->businessProducts as $product) {
                    $products[] = $product;
                }
            }

            if (empty($products)) {
                $products = $it->products;
            }

            $itProducts = array_filter($products, function ($s) {
                return isset($s->totalPoints) && (!isset($s->unavaliable) || !$s->unavaliable);
            });
            $this->logger->debug("Found " . count($products) . " offers");

            foreach ($itProducts as $numOffer => $itOffer) {
                $this->logger->info('offer #' . $numOffer);
                $result = [
                    'distance'  => null,
                    'num_stops' => $it->stopsNumber,
                    'times'     => [
                        'flight' => null,
                        //                        'flight' => $this->sumLayovers(// for formatting
                        //                            '00:00',
                        //                            implode(":", $this->separateTime($it->duration))
                        //                        ),
                        'layover' => null,
                    ],
                    'redemptions' => [
                        'miles'   => $itOffer->totalPoints,
                        'program' => $this->AccountFields['ProviderCode'],
                    ],
                    'payments' => [
                        'currency' => $fields['Currencies'][0],
                        'taxes'    => !empty($itOffer->totalTaxes) ? $itOffer->totalTaxes : $this->getTax($extData,
                            $it->idFlightDetail, $itOffer->idFlightProduct),
                        'fees' => empty($itOffer->totalTaxes) ? $itOffer->bookingFee : null,
                        //                        'taxes' => !empty($itOffer->totalTaxesEQ) ? $itOffer->totalTaxesEQ : $this->getTax($extData,
                        //                            $it->idFlightDetail, $itOffer->idFlightProduct),
                        //                        'fees' => empty($itOffer->totalTaxesEQ) ? $itOffer->bookingFeeEQ : null
                    ],
                    'award_type' => !isset($itOffer->unavaliable) && isset($itOffer->fareFamilyName) ? $itOffer->fareFamilyName :
                        ($itOffer->cabin === 'COACH' ? 'TOURIST CABIN' : 'PREMIER CABIN')
                        . ($itOffer->ticketType === "DYNAMIC" ? " ({$itOffer->ticketType})" : ""),
                    'classOfService' => $itOffer->cabin === 'COACH' ? 'TOURIST' : 'PREMIER',
                    'connections'    => [],
                ];

                if (empty($result['payments']['taxes'])) {
                    $this->logger->notice("skip offer #{$numOffer}, no taxes");
                    $noTaxes = true;

                    continue;
                }

                if ($this->exchange !== 1 || $fields['Adults'] > 1) {
                    // check convert and calc
//                  // FE: for USD can't check taxes 1 - 75.51 , 2 - 151,01
                    if ($result['redemptions']['miles'] === intdiv($result['redemptions']['miles'],
                            $fields['Adults']) * $fields['Adults']
                        && (round($result['payments']['taxes'], 2) ===
                            round($result['payments']['taxes'] / $fields['Adults'], 2) * $fields['Adults'])
                        && (null === $result['payments']['fees']
                            || round($result['payments']['fees'], 2) ==
                            round($result['payments']['fees'] / $fields['Adults'], 2) * $fields['Adults'])
                    ) {
                        $result['redemptions']['miles'] = intdiv($result['redemptions']['miles'], $fields['Adults']);
                        $result['payments']['taxes'] = round($result['payments']['taxes'] / $fields['Adults'] / $this->exchange,
                            2);

                        if (isset($result['payments']['fees'])) {
                            $result['payments']['fees'] = round($result['payments']['fees'] / $fields['Adults'] / $this->exchange,
                                2);
                        }
                    } else {
                        $intPoints = intdiv($result['redemptions']['miles'], $fields['Adults']);
                        $roundPoints = round($intPoints, -2);
                        // ceil
                        if ($roundPoints < $intPoints) {
                            $roundPoints += 100;
                        }
                        $result['redemptions']['miles'] = $roundPoints;
                        $result['payments']['taxes'] = round($result['payments']['taxes'] / $fields['Adults'] / $this->exchange,
                            2);

                        if (isset($result['payments']['fees'])) {
                            $result['payments']['fees'] = round($result['payments']['fees'] / $fields['Adults'] / $this->exchange,
                                2);
                        }
                    }
                }

                $layover = null;
                $noTransfer = true;
                $totalFlight = null;

                foreach ($it->flightSegments as $numSeg => $segment) {
                    $this->logger->info('segment #' . $numSeg);
                    $this->http->JsonLog(json_encode($segment), 1);

                    foreach ($segment->detailedInfo as $info) {
                        if (isset($info->code, $info->name, $info->value)
                            && property_exists($info, 'shortName') && $info->value === ''
                        ) {
                            $aircraft = $info->code; // $info->name;
                        }
                    }
                    $seg = [
                        'num_stops' => 0,
                        'departure' => [
                            'date' => date('Y-m-d H:i', strtotime($segment->departureTimeStringFormat,
                                strtotime($segment->departureDateStringFormat))),
                            'dateTime' => strtotime($segment->departureTimeStringFormat,
                                strtotime($segment->departureDateStringFormat)),
                            'airport' => $segment->originAirport->iataCode,
                        ],
                        'arrival' => [
                            'date' => date('Y-m-d H:i', strtotime($segment->arrivalTimeStringFormat,
                                strtotime($segment->arrivalDateStringFormat))),
                            'dateTime' => strtotime($segment->arrivalTimeStringFormat,
                                strtotime($segment->arrivalDateStringFormat)),
                            'airport' => $segment->destinationAirport->iataCode,
                        ],
                        'cabin'      => $cabins[$itOffer->cabin] ?? null,
                        'fare_class' => $this->getServiceClass($segment->segmentInfoNumber,
                            $itOffer->segmentsServiceClass),
                        'aircraft' => $aircraft ?? null,
                        'flight'   => [$segment->iataCodeAirline . $segment->flightNumber],
                        'airline'  => $segment->iataCodeAirline,
                        'times'    => [
                            //                            'flight' => $this->sumLayovers(// for formatting
                            //                                '00:00',
                            //                                implode(":", $this->separateTime($segment->duration))
                            //                            ),
                            'flight'  => null,
                            'layover' => null, // will calc below $flightSegment->layover->duration->
                        ],
                        'tickets' => null,
                        //                        'classOfService' => $itOffer->cabin === 'COACH' ? 'TOURIST CABIN' : 'PREMIER CABIN',
                    ];
                    /*
                    $totalFlight = $this->sumLayovers($totalFlight, $seg['times']['flight']);
                    // try to calc layover for previous segment
                    $prev = array_key_last($result['connections']);

                    if (isset($prev) && !isset($result['connections'][$prev]['times']['layover'])) {
                        if ($noTransfer && $result['connections'][$prev]['arrival']['airport'] === $seg['departure']['airport']) {
                            $diffMinutes = (int) (($seg['departure']['dateTime'] - $result['connections'][$prev]['arrival']['dateTime']) / 60);
                            [$h, $m] = $this->separateTime($diffMinutes);
                            $result['connections'][$prev]['times']['layover'] = $h . ':'
                                . str_pad($m, 2, "0", STR_PAD_LEFT);
                            $layover = $this->sumLayovers($layover, $h . ':' . $m);
                        } else {
                            $result['connections'][$prev]['times']['layover'] = null; // reset layover for previous
                            $noTransfer = false; // no calc, otherwise error in totalLayover
                            $layover = null;
                        }
                    }
                    */
                    $result['connections'][] = $seg;
                }
                $result['times']['layover'] = $layover;
                // total flight, not total travel
                $result['times']['flight'] = $totalFlight;
                $this->logger->debug(var_export($result, true), ['pre' => true]);
                $routes[] = $result;
            }
        }

        if (empty($routes) && isset($noTaxes)) {
            $this->SetWarning('boletos agotados');
        }

        return $routes;
    }

    private function getServiceClass(int $num, array $data): ?string
    {
        foreach ($data as $v) {
            if ($v->segmentNumber === $num) {
                return $v->serviceClass;
            }
        }

        return null;
    }

    private function getTax($extData, $idFlightDetail, $idFlightProduct)
    {
        if (!isset($extData->response)) {
            return null;
        }

        foreach ($extData->response as $response) {
            if ($response->idFlightDetail === $idFlightDetail) {
                foreach ($response->products as $product) {
                    if ($product->idFlightProduct === $idFlightProduct) {
                        return $product->totalTaxesGC;
//                        return $product->totalTaxesGCEQ;
                    }
                }
            }
        }

        return null;
    }
}
