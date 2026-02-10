<?php

namespace AwardWallet\Engine\aviancataca\RewardAvailability;

use AwardWallet\Engine\ProxyList;

require_once __DIR__ . '/../../aviancataca/TAccountCheckerAviancatacaSelenium.php';

class Parser extends \TAccountCheckerAviancatacaSelenium
{
    use ProxyList;
    use \SeleniumCheckerHelper;

    private $inCabin;
    private $warning;
    private $bearer;
    private $ip;

    private $retry = 0;
    private $config;
    private $isHot;

    public static function getRASearchLinks(): array
    {
        return ['https://www.lifemiles.com/fly/find' => 'search page'];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        $this->useSelenium();
        $this->KeepState = true;

        $this->http->setHttp2(true);
        $array = ['fr', 'de', 'us', 'au', 'pt', 'ca'];
        $targeting = $array[array_rand($array)];

        if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyMount();
        } else {
            $this->setProxyGoProxies(null, $targeting);
        }

        $this->http->saveScreenshots = true;

        if ($this->attempt == 1) {
            $this->useFirefoxPlaywright();
            $this->seleniumOptions->addHideSeleniumExtension = false;
            $this->seleniumOptions->userAgent = null;
            $this->disableImages();
        } else {
            $this->useChromePuppeteer();
        }

        $this->seleniumRequest->setHotSessionPool(
            self::class,
            $this->AccountFields['ProviderCode'],
            $this->AccountFields['AccountKey']
        );

        $this->seleniumOptions->recordRequests = true;
    }

    public function LoadLoginForm()
    {
        if (strpos($this->http->currentUrl(), 'lifemiles.com') !== false) {
            $this->isHot = true;
            $this->http->GetURL("https://www.lifemiles.com/fly/find");

            $this->waitForElement(\WebDriverBy::xpath("//input[@id='ar_bkr_fromairport']"), 15);
            $token = $this->driver->manage()->getCookieNamed("rfhtkn");
            $token = $token["value"];

            $token = $this->runRefreshToken($token);

            if (isset($token["TokenGrantResponse"]["access_token"])) {
                $this->State['access_token'] = $token["TokenGrantResponse"]["access_token"];
            }

            if (!isset($this->State['access_token'])) {
                return parent::LoadLoginForm();
            }

            return true;
        }

        return parent::LoadLoginForm();
    }

    public function Login()
    {
        if ($this->isHot) {
            return true;
        }

        try {
            $login = parent::Login();
        } catch (\CheckException $e) {
            if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR) {
                throw new \CheckRetryNeededException(5, 0);
            }

            throw $e;
        }

        return $login;
    }

    public function getRewardAvailabilitySettings()
    {
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0, // 3
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->http->FilterHTML = false;
        $this->http->LogHeaders = true;

        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        $this->bearer = $this->State['access_token'];

        try {
            $cabinData = $this->getCabinFields(false);
            $this->inCabin = $fields['Cabin'];
            $fields['cabinName'] = $cabinData[$this->inCabin]['cabinName'];
            $fields['Cabin'] = $cabinData[$this->inCabin]['cabin'];

            if ($fields['Currencies'][0] !== 'USD') {
                $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
                $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
            }

            if ($fields['Adults'] > 8) {
                $this->SetWarning("you can check max 8 travellers");

                return [];
            }

            [$airportsOrigin, $airportsDestination] = $this->getAirports();

            if (!array_key_exists($fields['DepCode'], $airportsOrigin)) {
                $this->SetWarning('no flights from ' . $fields['DepCode']);

                return [];
            }

            if (!array_key_exists($fields['ArrCode'], $airportsDestination)) {
                $this->SetWarning('no flights to ' . $fields['ArrCode']);

                return [];
            }

            $fields['DepCity'] = $airportsOrigin[$fields['DepCode']];
            $fields['ArrCity'] = $airportsDestination[$fields['ArrCode']];
            $payload = "{\"cabin\":\"{$fields['Cabin']}\",\"ftNum\":\"\",\"internationalization\":{\"language\":\"es\",\"country\":\"us\",\"currency\":\"usd\"},\"itineraryName\":\"One-Way\",\"itineraryType\":\"OW\",\"numOd\":1,\"ods\":[{\"id\":1,\"origin\":{\"cityName\":\"{$fields['DepCity']}\",\"cityCode\":\"{$fields['DepCode']}\"},\"destination\":{\"cityName\":\"{$fields['ArrCity']}\",\"cityCode\":\"{$fields['ArrCode']}\"}}],\"paxNum\":{$fields['Adults']},\"selectedSearchType\":\"SMR\"}";
            $headers = [
                'Accept'         => 'application/json',
                'Authorization'  => 'Bearer ' . $this->bearer,
                'Content-Type'   => 'application/json',
                'realm'          => 'lifemiles',
                'Origin'         => 'https://www.lifemiles.com',
                'Referer'        => 'https://www.lifemiles.com/',
                'Sec-Fetch-Dest' => 'empty',
                'Sec-Fetch-Mode' => 'cors',
                'Sec-Fetch-Site' => 'same-site',
                'TE'             => 'trailers',
            ];
            $this->http->RetryCount = 0;

//            $res = $this->getFetch('post1', 'POST',
//                "https://api.lifemiles.com/svc/air-redemption-par-header-private",
//                $headers, $payload, 'https://www.lifemiles.com/');
            $res = $this->getXHRPost("https://api.lifemiles.com/svc/air-redemption-par-header-private", $headers, $payload);

            $data = $this->http->JsonLog($res, 1, false, 'schHcfltrc');

            if (!isset($data->idCotizacion) || !isset($data->sch, $data->sch->schHcfltrc)) {
                if (isset($data->description)
                    && $data->description === 'The origin/destination entered is not available for the selected airline. Please try with another search') {
                    $this->SetWarning($data->description);

                    return ['routes' => []];
                }

                if ($this->http->Response['code'] == 403) {
                    throw new \CheckRetryNeededException(5, 0);
                }

                throw new \CheckException('no data with idCotizacion & schHcfltrc', ACCOUNT_ENGINE_ERROR);
            }
            $idCoti = $data->idCotizacion;
            $schHcfltrc = $data->sch->schHcfltrc;
            $searchTypePrioritize = $data->searchTypePrioritize;
//        // no range
            $fields['DepDate'] = date("Y-m-d", $fields['DepDate']);

            if (!isset($this->State["proxy-ip"])) {
                $http = clone $this->http;
                $http->GetURL('https://ipinfo.io/ip');
                $ip = $http->Response['body'];
                $ip = $http->FindPreg("/^(\d+\.\d+\.\d+\.\d+)$/", false, $ip);

                if (isset($ip)) {
                    $this->ip = $ip;
                }
                unset($http);
            } else {
                $this->ip = $this->State["proxy-ip"];
            }

            $routes = $this->ParseRewardAirlines($fields, $idCoti, $schHcfltrc, $searchTypePrioritize);
        } finally {
            if ($this->ErrorCode !== 9 && empty($routes)) {
                $this->keepSession(false);

                throw new \CheckRetryNeededException(5, 5);
            }
        }
        $this->keepSession(true);

        return ['routes' => $routes];
    }

    protected function parseReCaptcha($key = null)
    {
        $this->logger->notice(__METHOD__);

        if (!$key) {
            $key = $this->http->FindSingleNode("//div[@class = 'g-recaptcha']/@data-sitekey");
        }
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $postData = [
            "type"       => "HCaptchaTaskProxyless",
            "websiteURL" => $this->http->currentUrl(),
            "websiteKey" => $key,
        ];
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_ANTIGATE_API_V2);
        $recognizer->RecognizeTimeout = 120;

        return $this->recognizeAntiCaptcha($recognizer, $postData);
    }

    private function getAirports(?string $airline = '')
    {
        $this->logger->notice(__METHOD__);
        $extKey = '';

        if (!empty($airline)) {
            $extKey = '_' . $airline;
            $airline = '/' . $airline;
        }

        $airportsOrigin = \Cache::getInstance()->get('aviancataca_ra_depcodes' . $extKey);
        $airportsDestination = \Cache::getInstance()->get('aviancataca_ra_arrcodes' . $extKey);

        if ($airportsOrigin === false || $airportsDestination === false) {
            $headers = [
                'Accept'        => 'application/json',
                'Referer'       => 'https://www.lifemiles.com/',
                'Origin'        => 'https://www.lifemiles.com/',
                'Authorization' => 'Bearer ' . $this->bearer,
                'realm'         => 'lifemiles',
            ];
            $url = "https://api.lifemiles.com/svc/air-redemption-par-booker/en/us/usd" . $airline;
            $response = $this->getXHRGet($url, $headers);
            $data = $this->http->JsonLog($response, 0);

            if (isset($data->find, $data->find->booker, $data->find->booker->airports, $data->find->booker->airports->destination, $data->find->booker->airports->origin)) {
                $airportsOrigin = $airportsDestination = [];

                foreach ($data->find->booker->airports->origin as $item) {
                    $airportsOrigin[$item->code] = $item->cityName;
                }

                foreach ($data->find->booker->airports->destination as $item) {
                    $airportsDestination[$item->code] = $item->cityName;
                }

                if (!empty($airportsOrigin) && !empty($airportsDestination)) {
                    \Cache::getInstance()->set('aviancataca_ra_depcodes' . $extKey, $airportsOrigin, 60 * 60 * 24);
                    \Cache::getInstance()->set('aviancataca_ra_arrcodes' . $extKey, $airportsDestination, 60 * 60 * 24);
                } else {
                    $this->logger->error('other format json');

                    throw new \CheckException('no list airports', ACCOUNT_ENGINE_ERROR);
                }
            } else {
                throw new \CheckException('no list airports', ACCOUNT_ENGINE_ERROR);
            }
        }

        return [$airportsOrigin, $airportsDestination];
    }

    private function getXHRGet($url, array $headers)
    {
        $this->logger->notice(__METHOD__);
        $headersString = "";

        foreach ($headers as $key => $value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                xhttp.open("GET", "' . $url . '", false);
                ' . $headersString . '
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send();
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        return $this->driver->executeScript($script);
    }

    private function getXHRPost($url, array $headers, $payload)
    {
        $this->logger->notice(__METHOD__);
        $headersString = "";

        foreach ($headers as $key => $value) {
            $headersString .= 'xhttp.setRequestHeader("' . $key . '", "' . $value . '");
        ';
        }

        if (is_array($payload)) {
            $payload = json_encode($payload);
        }

        $payload = base64_encode($payload);
        $script = '
                var xhttp = new XMLHttpRequest();
                xhttp.withCredentials = true;
                var data = atob("' . $payload . '");
                xhttp.open("POST", "' . $url . '", false);
                ' . $headersString . '
                var responseText = null;
                xhttp.onreadystatechange = function() {
                    if (this.readyState == 4 && (this.status == 200 || this.status == 202)) {
                        responseText = this.responseText;
                    }
                };
                xhttp.send(data);
                return responseText;
            ';
        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);

        return $this->driver->executeScript($script);
    }

    private function getCabinFields($onlyKeys = true): array
    {
        // show - means, show in answers
        $cabins = [
            'economy'        => ['Economy', 'cabin' => 1, 'cabinName' => ['Economy on sale', 'Economy'], 'show' => true],
            'premiumEconomy' => [
                'Economy',
                'cabin'     => 1,
                'cabinName' => ['Economy on sale', 'Economy'],
                'show'      => false,
            ],
            'firstClass' => ['Business or First', 'cabin' => 2, 'cabinName' => ['First class'], 'show' => true],
            'business'   => [
                'Business or First',
                'cabin'     => 2,
                'cabinName' => ['Business on sale', 'Business'],
                'show'      => true,
            ],
        ];

        if ($onlyKeys) {
            return array_keys($cabins);
        }

        return $cabins;
    }

    private function parseRewardFlights($data, ?bool $isRetry = false): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        $cabinArr = array_filter($this->getCabinFields(false), function ($v) {
            return $v['show'];
        });
        $cabinNames = [];

        foreach ($cabinArr as $k => $v) {
            foreach ($v['cabinName'] as $name) {
                $cabinNames[$name] = $k;
            }
        }

        $this->warning = null;

        if (isset($data->tripsList) && is_array($data->tripsList)) {
            $this->logger->debug("Found " . count($data->tripsList) . " routes");

            foreach ($data->tripsList as $numRoot => $trip) {
                $result = ['connections' => []];
                $this->logger->notice("route " . $numRoot);

                $itOffers = null;

                foreach ($trip->products as $list) {
                    // no filter
//                    if (in_array($list->cabinName, $cabin) && $list->soldOut !== true) { //cabin = $fields['cabinName']
//                        $itOffers[] = $list;
//                    }
                    if (!isset($list->soldOut)) {
                        continue;
                    }

                    if ($list->soldOut !== true) {
                        $itOffers[] = ['cabin' => $list->cabinName, 'cabinCode' => $list->cabinCode, 'offer' => $list];
                    }
                }

                if (!isset($itOffers)) {
                    $this->logger->debug('skip rout ' . $numRoot . ' (soldOut for cabin...)');
                    $warningSoldOut = true;

                    continue;
                }
                $tickets = null;

                // for debug
//                $this->http->JsonLog(json_encode($trip), 1);

                $segments = [];

                foreach ($trip->flightsDetail as $flightsDetail) {
                    $seg = [
                        'departure' => [
                            'date'     => $flightsDetail->departingDate . ' ' . $flightsDetail->departingTime,
                            'dateTime' => strtotime($flightsDetail->departingDate . ' ' . $flightsDetail->departingTime),
                            'airport'  => $flightsDetail->departingCityCode,
                        ],
                        'arrival' => [
                            'date'     => $flightsDetail->arrivalDate . ' ' . $flightsDetail->arrivalTime,
                            'dateTime' => strtotime($flightsDetail->arrivalDate . ' ' . $flightsDetail->arrivalTime),
                            'airport'  => $flightsDetail->arrivalCityCode,
                        ],
                        'num_stops' => $flightsDetail->numberOfStops,
                        'flight'    => [$flightsDetail->marketingCompany . $flightsDetail->flightNumber],
                        'airline'   => $flightsDetail->marketingCompany,
                        'operator'  => $flightsDetail->operatedCompany,
                        'times'     => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                    ];
                    $segments[] = ['id' => $flightsDetail->id, 'seg' => $seg];
                }

                $isOld = false;

                try {
//                    $conn = $this->getConnections($segments, $itOffers);
                    $conn = $this->getConnectionsNew($segments, $itOffers, $isOld);
                } catch (\ErrorException $e) {
                    $this->logger->error($e->getMessage());
                    $this->logger->error('something went wrong with getConnections');
                    $this->logger->warning(var_export($segments, true), ['pre' => true]);
                    $this->logger->error(var_export($itOffers, true), ['pre' => true]);

                    if (!$isRetry) {
                        return $this->parseRewardFlights($data, true);
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }

                $isOkParseConnections = true;

                foreach ($conn as $item) {
                    if (count($item) !== count($segments)) {
                        $isOkParseConnections = false;

                        break;
                    }
                }

                if (!$isOkParseConnections) {
                    $isOld = true;
                    $conn = $this->getConnections($segments, $itOffers);
                }

                if (empty($conn)) {
                    $this->logger->debug('skip rout ' . $numRoot . ' (soldOut for cabin...)');
                    $warningSoldOut = true;

                    continue;
                }

                foreach ($conn as $numConn => $con) {
                    $connections = [];
                    $tickets = null;
                    $totalMiles = 0;
                    $award_types = [];
                    $award_type = null;
                    $classOfService = [];

                    foreach ($con as $seg) {
                        if (!isset($seg['seg'])) {
                            $this->logger->error('something went wrong');
                            $this->logger->error(var_export($conn, true), ['pre' => true]);

                            if (!$isRetry) {
                                return $this->parseRewardFlights($data, true);
                            }

                            throw new \CheckRetryNeededException(5, 0);
                        }
                        $segData = $seg['seg'];
                        $segData['aircraft'] = $seg['aircraft'];
                        $segData['fare_class'] = $seg['fare_class'];

                        if (!$isOld) {
                            $award_type = $this->getAwardType($seg['awardTitle']) ?? $award_type;
                        }

                        switch ($seg['cabinCode']) {
                            case 1:// Economy
                                $segData['cabin'] = 'economy';
                                $segData['classOfService'] = 'Economy';

                                break;

                            case 2:
                                $segData['cabin'] = 'business';
                                $segData['classOfService'] = 'Business';

                                break;

                            case 3:
                                $segData['cabin'] = 'firstClass';
                                $segData['classOfService'] = 'First';

                                break;

                            default:
                                if ($isOld && isset($cabinNames[$seg['award_type']])) {
                                    $segData['cabin'] = $cabinNames[$seg['award_type']];
                                } else {
                                    $this->sendNotification('check cabin // ZM');
                                    $segData['cabin'] = $this->inCabin;
                                }
                        }

                        if (isset($segData['classOfService'])) {
                            $classOfService[] = $segData['classOfService'];
                        }

                        if (!isset($tickets)) {
                            $tickets = $seg['tickets'];
                        } else {
                            $tickets = min($seg['tickets'], $tickets);
                        }
                        $totalMiles += $seg['total_miles'];
                        $award_types[] = $seg['award_type'];

                        if (!isset($segData['classOfService'])) {
                            $this->logger->warning('no classOfService');
                            $checkFormat = true;
                        }
                        $connections[] = $segData;
                    }
                    $award_types = array_values(array_unique($award_types));

                    if ($isOld && !isset($award_type) && count($award_types) === 1) {
                        $award_type = $award_types[0];
                    }
                    $result = [
                        'num_stops'  => count($connections) - 1 + array_sum(array_column($connections, 'num_stops')),
                        'award_type' => $award_type ?? null,
                        'times'      => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                        'redemptions' => [
                            'miles'   => $totalMiles,
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => 'USD',
                            'taxes'    => $trip->usdTaxValue,
                            'fees'     => null,
                        ],
                        'tickets'     => $tickets,
                        'connections' => $connections,
                    ];
                    $classOfService = array_values(array_unique($classOfService));

                    if (count($classOfService) === 1) {
                        $result['classOfService'] = $classOfService[0];
                    }
                    $routes[] = $result;
                    $this->logger->emergency('result #' . $numConn . ':');
                    $this->logger->debug(var_export($result, true), ['pre' => true]);
                }
            }

            if (isset($checkFormat)) {
                $this->sendNotification('check Format (no ClassOfService) // ZM');
            }

            if (empty($routes) && isset($warningSoldOut)) {
                $this->SetWarning('All tickets are sold out');
            }
        } else {
            $this->logger->debug('no flights. tripsList is empty');

            if (isset($data->status) && $data->status === 'success') {
                $this->warning = 'Not available';
            } // else because of timeouts
        }

        return $routes;
    }

    private function getAwardType($awardTitle)
    {
        $award_type = null;

        switch ($awardTitle) {
            case 'XS':
                $award_type = 'Lowest Price';

                break;

            case 'S':
                $award_type = 'Basic travel';

                break;

            case 'M':
                $award_type = 'More comfort';

                break;

            case 'L':
                $award_type = 'More flexibility';

                break;

            case 'XL':
                $award_type = 'Premium travel';

                break;

            case 'XXL':
                $award_type = 'Total comfort';

                break;

            default:
                $this->sendNotification('check award_type ' . $awardTitle . ' // ZM');
        }

        return $award_type;
    }

    private function getConnections($segments, $products): array
    {
        $matrix = [];

        foreach ($segments as $s) {
            $flight = [];

            foreach ($products as $p) {
                foreach ($p['offer']->flights as $fl) {
                    if (isset($p['offer']->totalMiles) && empty($p['offer']->totalMiles)) {
                        continue;
                    }

                    if (isset($p['offer']->regularMiles) && !empty((int) $p['offer']->regularMiles)) {
                        // club lifemiles discount
                        continue;
                    }

                    if ($s['id'] == $fl->id) {
                        if (!property_exists($fl, 'soldOut')) {
                            continue;
                        }

                        if (!$fl->soldOut) {
                            $resSegment = $s;
                            $resSegment['aircraft'] = $fl->eqp;
                            $resSegment['fare_class'] = $fl->class;
                            $resSegment['tickets'] = $fl->remainingSeats;
                            $resSegment['total_miles'] = $fl->miles;
                            $resSegment['award_type'] = $p['offer']->cabinName;
                            $resSegment['cabinCode'] = $p['offer']->cabinCode;
                            $resSegment['awardTitle'] = $p['offer']->cabinCode;
                            $flight[] = $resSegment;
                        } else {
                            $flight[] = [];
                        }
                    }
                }
            }
            $matrix[] = $flight;
        }

        return $this->getRoutes($matrix);
    }

    private function getConnectionsNew($segments, $products, &$isOld): array
    {
        $result = [];

        foreach ($products as $p) {
            if (!isset($p['offer']->title)) {
                $isOld = true;

                return $this->getConnections($segments, $products);
            }

            if (!isset($p['offer']->showBundle) || !$p['offer']->showBundle) {
                continue;
            }
            $flight = [];

            foreach ($p['offer']->flights as $fl) {
                if (isset($p['offer']->totalMiles) && empty($p['offer']->totalMiles)) {
                    continue 2;
                }

                if (isset($p['offer']->regularMiles) && !empty((int) $p['offer']->regularMiles)) {
                    // club lifemiles discount
                    continue 2;
                }

                foreach ($segments as $s) {
                    if ($s['id'] == $fl->id) {
                        if (!property_exists($fl, 'soldOut')) {
                            continue 2;
                        }

                        if (!$fl->soldOut) {
                            $resSegment = $s;
                            //$resSegment['distance'] = $fl->miles;
                            $resSegment['aircraft'] = $fl->eqp;
                            $resSegment['fare_class'] = $fl->class;
                            $resSegment['tickets'] = $fl->remainingSeats;
                            $resSegment['total_miles'] = $fl->miles;
                            $resSegment['award_type'] = $p['offer']->cabinName;
                            $resSegment['cabinCode'] = $p['offer']->cabinCode;
                            $resSegment['awardTitle'] = $p['offer']->title;
                            $flight[] = $resSegment;
                        } else {
                            break 2;
                        }
                    }
                }
            }
            $result[] = $flight;
        }

        return $result;
    }

    private function getRoutes($a, $start_route = 0): array
    {
        if (is_array($a) && $start_route != count($a)) {
            $result = [];
            $products_quantity = count($a[0]);
            $routes_quantity = count($a);

            for ($i = 0; $i < $products_quantity; $i++) {
                if ($a[$start_route][$i]) {
                    if ($start_route == $routes_quantity - 1) {
                        $result[] = [0 => $a[$start_route][$i]];
                    } else {
                        $result_r = $this->getRoutes($a, $start_route + 1);

                        if (is_array($result_r) && $result_r) {
                            foreach ($result_r as $route_part) {
                                $result_route = [$a[$start_route][$i]];
                                $result_route = array_merge($result_route, $route_part);
                                $result[] = $result_route;
                            }
                        }
                    }
                }
            }

            return $result;
        }

        return [];
    }

    private function checkErrorProxy(?int $attemptsCount = 5)
    {
        if (strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure') !== false
        ) {
            throw new \CheckRetryNeededException($attemptsCount, 0);
        }
    }

    private function ParseRewardAirlines($fields, $idCoti, $schHcfltrc, $searchTypePrioritize)
    {
        $this->logger->notice(__METHOD__);
        $this->http->RetryCount = 0;
        $dataAirlines = $this->getPartnerAirlines($fields);
        $fields['DepCity'] = $dataAirlines['dep'];
        $fields['ArrCity'] = $dataAirlines['arr'];
        $validAirlines = $dataAirlines['airlines'];
        array_unshift($validAirlines, 'AVH');
        $validAirlines[] = 'SSA';
        $this->logger->debug(var_export($validAirlines, true));

        $headers = [
            'Accept'       => 'application/json',
            'Content-Type' => 'application/json',
            //            'Origin'         => 'https://www.lifemiles.com/',
            //            'Referer'        => 'https://www.lifemiles.com/',
            'Authorization'  => 'Bearer ' . $this->bearer,
            'realm'          => 'lifemiles',
            'Sec-Fetch-Dest' => 'empty',
            'Sec-Fetch-Mode' => 'cors',
            'Sec-Fetch-Site' => 'same-site',
            'TE'             => 'trailers',
        ];

        if (!isset($idCoti) || !isset($schHcfltrc)) {
            $this->sendNotification('check getting idCoti // ZM');

            throw new \CheckRetryNeededException(5, 0);
        }

        $routes = [];
        $warnings = [];

        $this->http->RetryCount = 0;

        $this->logger->critical('parse for searchTypePrioritize: ' . $searchTypePrioritize);
        $res = $this->findFlight($fields, $searchTypePrioritize, $headers, $idCoti, $schHcfltrc);

        if (isset($res->page) && $res->page == false) {
            if (strpos($res->description,
                    'Sorry, we could not process your request, please try again. If the problem persists contact our') !== false) {
                $warnings[] = 'No flights. Try to choose more fare options';
            } else {
                $warnings[] = $res->description;
            }
        }

        if (empty($res)) {
            $this->logger->critical('something went wrong. skip searchTypePrioritize');
        } elseif (empty($warnings)) {
            $routes_ = $routes;
            $routes = array_merge($this->parseRewardFlights($res), $routes_);
        }

        if (($key = array_search($searchTypePrioritize, $validAirlines)) !== false) {
            unset($validAirlines[$key]);
        }

        foreach ($validAirlines as $airline) {
            if (!$this->validAirline($fields, $airline, $headers)) {
                $this->logger->notice('skip airline');

                if (!isset($skipped)) {
                    $skipped = 1;
                } else {
                    $skipped++;
                }

                continue;
            }
            $this->logger->critical('parse for airline: ' . $airline);
            $res = $this->findFlight($fields, $airline, $headers, $idCoti, $schHcfltrc);

            if ($res === null) {
                $this->logger->notice('skip airline');

                continue;
            }

            if (isset($res->page) && $res->page == false) {
                if (strpos($res->description,
                        'Sorry, we could not process your request, please try again. If the problem persists contact our') !== false) {
                    $warnings[] = 'No flights. Try to choose more fare options';

                    continue;
                }

                if (empty($res->description) && isset($res->otaButtonTex) && $res->otaButtonTex === 'Find more fares') {
                    $warnings[] = 'Not available';
                } else {
                    $warnings[] = $res->description;
                }

                continue;
            }
            $routes_ = $routes;
            $newRoutes = $this->parseRewardFlights($res);

            if (empty($newRoutes) && !empty($this->warning)) {
                $warnings[] = $this->warning;
            } else {
                $routes = array_merge($routes_, $newRoutes);
            }

            if (time() - $this->requestDateTime > 100) {
                $this->logger->warning('stop parsing. time is running out...');

                break;
            }
        }

        $warnings = array_values(array_unique(array_filter($warnings)));
        $this->logger->warning('warnings met:');
        $this->logger->warning(var_export($warnings, true));

        if (empty($routes)) {
            if (!empty($warnings)) {
                $this->SetWarning($warnings[0]);
            } elseif (isset($skipped) && $skipped === count($validAirlines)) {
                $this->logger->debug(var_export([$skipped, count($validAirlines)], true));
                $this->SetWarning('The entered route is not available');
            } else {
                throw new \CheckRetryNeededException(5, 0);
            }
        }

        $allRoutes = $routes;
        $routes = array_map('unserialize', array_unique(array_map('serialize', $allRoutes)));

        return $routes;
    }

    private function findFlight($fields, $typeSearch, $headers, $idCoti, $schHcfltrc)
    {
        $this->logger->notice(__METHOD__);
        $searchType = $searchTypePrioritized = $typeSearch;

        /*        if ($typeSearch === 'SSA') {
        //            должно быть SSA - SSA - private - token
                    $searchType = 'SMR';
                }*/
        $payload = '{"internationalization":{"language":"en","country":"us","currency":"usd"},"currencies":[{"currency":"USD","decimal":2,"rateUsd":1}],"passengers":' . $fields['Adults'] . ',"od":{"orig":"' . $fields['DepCode'] . '","dest":"' . $fields['ArrCode'] . '","departingCity":"' . $fields['DepCity'] . '","arrivalCity":"' . $fields['ArrCity'] . '","depDate":"' . $fields['DepDate'] . '","depTime":""},"filter":false,"codPromo":null,"idCoti":"' . $idCoti . '","officeId":"","ftNum":"","discounts":[],"promotionCodes":["DBEP21"],"context":"D","ipAddress":"' . $this->ip . '","channel":"COM","cabin":"' . $fields['Cabin'] . '","itinerary":"OW","odNum":1,"usdTaxValue":"0","getQuickSummary":false,"ods":"","searchType":"' . $searchType . '","searchTypePrioritized":"' . $searchTypePrioritized . '","sch":{"schHcfltrc":"' . $schHcfltrc . '"},"posCountry":"US","odAp":[{"org":"' . $fields['DepCode'] . '","dest":"' . $fields['ArrCode'] . '","cabin":' . $fields['Cabin'] . '}],"suscriptionPaymentStatus":""}';

        if (empty($this->ip)) {
            $payload = str_replace('/"ipAddress":"\d+\.\d+\.\d+\.\d+",/', '', $payload);
        }
        $this->http->RetryCount = 0;
//        $res = $this->getFetch('post' . $searchType, 'POST',
//            'https://api.lifemiles.com/svc/air-redemption-find-flight-private', $headers, $payload,
//            'https://www.lifemiles.com/');
        $res = $this->getXHRPost('https://api.lifemiles.com/svc/air-redemption-find-flight-private', $headers, $payload);
        $this->http->SetBody($res);

        return $this->http->JsonLog(null, 1, false, 'tripsList');
    }

    private function validAirline($fields, $typeSearch, $headers)
    {
        if ($typeSearch === 'SSA' || $typeSearch === 'SMR' || $typeSearch === 'AVH') {
            return true;
        }

        $payload = '{"cabin":"' . $fields['Cabin'] . '","ftNum":"","internationalization":{"language":"en","country":"us","currency":"usd"},"itineraryName":"One-Way","itineraryType":"OW","numOd":1,"ods":[{"id":1,"origin":{"cityName":"' . $fields['DepCity'] . '","cityCode":"' . $fields['DepCode'] . '"},"destination":{"cityName":"' . $fields['ArrCity'] . '","cityCode":"' . $fields['ArrCode'] . '"}}],"paxNum":1,"selectedSearchType":"' . $typeSearch . '"}';

        $result = $this->getXHRPost('https://api.lifemiles.com/svc/air-redemption-par-header-private', $headers,
            $payload);
        $res = $this->http->JsonLog($result, 1, false);

        if (null == $res) {
            $result = $this->getXHRPost('https://api.lifemiles.com/svc/air-redemption-par-header-private', $headers,
                $payload);
            $res = $this->http->JsonLog($result, 1, false);
        }

        if (isset($res->description) && $res->description === 'The entered route is not available for the selected airline. Would you like us to search with other airlines?') {
            $this->logger->info($res->description);

            return false;
        }

        // default value - чтобы не было ложно-положительных ответов. лучше не нашли, чем нашли то, чего нет
        return false;
    }

    private function getPartnerAirlines($fields)
    {
        $this->logger->notice(__METHOD__);
        $partners = \Cache::getInstance()->get('aviancataca_ra_partners');

        if (!$partners || !is_array($partners)) {
            $partners = [];
            $headers = [
                'Accept'        => 'application/json',
                'Origin'        => 'https://www.lifemiles.com/',
                'Referer'       => 'https://www.lifemiles.com/',
                'Authorization' => 'Bearer ' . $this->bearer,
                'realm'         => 'lifemiles',
            ];

            $result = $this->getXHRGet("https://api.lifemiles.com/svc/air-redemption-par-booker/en/us/usd", $headers);
            $data = $this->http->JsonLog($result, 0, true);

            foreach ($data['find']['booker']['airlines'] as $main) {
                if (strlen($main['code']) === 2) {
                    $partners[] = $main['code'];
                }
            }

            if (!empty($partners)) {
                \Cache::getInstance()->set('aviancataca_ra_partners', $partners, 60 * 60 * 24);
            } else {
                throw new \CheckException('no partners', ACCOUNT_ENGINE_ERROR);
            }
        }

        $validAirlines = [];
        $depCity = $arrCity = null;

        foreach ($partners as $airline) {
            [$airportsOrigin, $airportsDestination] = $this->getAirports($airline);

            if (!array_key_exists($fields['DepCode'], $airportsOrigin)
                || !array_key_exists($fields['ArrCode'], $airportsDestination)) {
                $this->logger->debug('no flights ' . $fields['DepCode'] . '->' . $fields['ArrCode']);

                continue;
            }
            $validAirlines[] = $airline;

            if (!isset($depCity, $arrCity)) {
                $depCity = $airportsOrigin[$fields['DepCode']];
                $arrCity = $airportsDestination[$fields['ArrCode']];
            }
        }

        return ['airlines' => $validAirlines, 'dep' => $depCity, 'arr' => $arrCity];
    }

    private function runRefreshToken(string $oldToken): ?array
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            "accept"          => "application/json",
            "accept-Encoding" => "gzip, deflate, br, zstd",
            "realm"           => "lifemiles",
            "Origin"          => "https://www.lifemiles.com",
            "Sec-Fetch-Dest"  => "empty",
            "Sec-Fetch-Mode"  => "cors",
            "Sec-Fetch-Site"  => "same-site",
        ];
        $referrer = "https://www.lifemiles.com/";
        $body = addslashes('{"authorizationCode":"' . $oldToken . '","applicationID":"lm"}');
        $result = $this->getFetch("refreshtoken", "POST", "https://oauth.lifemiles.com/authentication/token/refresh",
            $headers, $body, $referrer, false);

        return $this->http->JsonLog($result, 1, true);
    }

    private function getFetch($id, $method, $url, array $headers, $payload, $referer, $addAuth = true)
    {
        if ($addAuth) {
            $headers['Authorization'] = 'Bearer ' . $this->bearer;
        }

        if (is_array($headers)) {
            $headers = json_encode($headers);
        }

        $script = '
                fetch("' . $url . '", {
                  "headers": ' . $headers . ',
                  "referrer": "' . $referer . '",
                  "body": \'' . $payload . '\',
                  "method": "' . $method . '",
                  "credentials": "omit",
                }).then( response => response.json())
                  .then( result => {
                    let script = document.createElement("script");
                    let id = "' . $id . '";
                    script.id = id;
                    script.setAttribute(id, JSON.stringify(result));
                    document.querySelector("body").append(script);
                })
                .catch(error => {                    
                    let newDiv = document.createElement("div");
                    let id = "' . $id . '";
                    newDiv.id = id;
                    let newContent = document.createTextNode(error);
                    newDiv.appendChild(newContent);
                    document.querySelector("body").append(newDiv);
                });;
            ';

        $this->logger->debug("[run script]:");
        $this->logger->debug($script, ['pre' => true]);
        $this->driver->executeScript($script);
        $this->driver->executeScript($script);

        $ext = $this->waitForElement(\WebDriverBy::xpath('//script[@id="' . $id . '"]'), 10, false);
        $this->saveResponse();

        if (!$ext) {
            if ($error = $this->waitForElement(\WebDriverBy::xpath('//div[@id="' . $id . '"]'), 0, false)) {
                $this->logger->error($error->getText());
            }

            return null;
        }

        return htmlspecialchars_decode($ext->getAttribute($id));
    }
}
