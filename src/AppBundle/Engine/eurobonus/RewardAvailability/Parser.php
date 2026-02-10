<?php

namespace AwardWallet\Engine\eurobonus\RewardAvailability;

use AwardWallet\Common\Selenium\BrowserCommunicatorException;
use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\Settings;

class Parser extends \TAccountCheckerEurobonus
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;
    public $isRewardAvailability = true;

    private $access_token;
    private $reese84;
    private $responseData;
    private $responseDataAlliance;
    private $config;

    private const CONFIGS = [
//        'chrome-95' => [
//            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
//            'browser-version' => \SeleniumFinderRequest::CHROME_95,
//        ],
//        'chrome-84' => [
//            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROME,
//            'browser-version' => \SeleniumFinderRequest::CHROME_84,
//        ],
//        'firefox-84' => [
//            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
//            'browser-version' => \SeleniumFinderRequest::FIREFOX_84,
//        ],
//        'firefox-59' => [
//            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
//            'browser-version' => \SeleniumFinderRequest::FIREFOX_59,
//        ],
        'firefox-53' => [
            'browser-family'  => \SeleniumFinderRequest::BROWSER_FIREFOX,
            'browser-version' => \SeleniumFinderRequest::FIREFOX_53,
        ],
//        'chromium-80' => [
//            'browser-family'  => \SeleniumFinderRequest::BROWSER_CHROMIUM,
//            'browser-version' => \SeleniumFinderRequest::CHROMIUM_80,
//        ],
    ];

    public static function getRASearchLinks(): array
    {
        return [
            'https://www.flysas.com/us-en/' => 'search page',
            'https://www.flysas.com/us-en/eurobonus/star-alliance-award-trips/' => 'allaince'
        ];
    }

    public function InitBrowser()
    {
        \TAccountChecker::InitBrowser();

        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->setDefaultHeader("Content-Type", "application/json;charset=UTF-8");
        $this->http->setDefaultHeader("Accept", "*/*");

        $array = ['de', 'es', 'fr', 'be', ];
        $targeting = $array[array_rand($array)];
        if ($targeting === 'us' && $this->AccountFields['ParseMode'] === 'awardwallet') {
            $this->setProxyMount();
        } else {
            $this->setProxyGoProxies(null, $targeting, null, null, "https://www.flysas.com/us-en/");
        }
        $this->http->setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/15.6.1 Safari/605.1.15');

        $this->config = array_rand(self::CONFIGS);
        $this->logger->debug("Randomly selected configuration {$this->config}");
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
        $arrCurrencies = ['USD'];

        return [
            'supportedCurrencies'      => $arrCurrencies,
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'USD',
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Currencies'][0] !== 'USD') {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($fields['Adults'] > 9) {
            $this->SetWarning('You can check max 9 travellers');

            return ['routes' => []];
        }

        if ($fields['DepDate'] > strtotime('+360 day')) {
            $this->SetWarning('too late');

            return ['routes' => []];
        }

        try {
            if(is_array($result = $this->selenium($fields))) {
                return ['routes' => $result];
            }
        } catch (\NoSuchDriverException $e) {
            $this->logger->error('NoSuchDriverException: ' . $e->getMessage());

            throw new \CheckRetryNeededException(5, 0);
        }

        $this->responseData = $this->getBookingData($fields);
        $resp = $this->http->Response['body'];

        $this->responseDataAlliance = $this->getBookingData($fields, true);
        $respStar = $this->http->Response['body'];

        if (isset($this->responseData['outboundFlights']) && preg_match('/points":\s*0\.0,/', $resp)
            && !preg_match('/points":\s*[1-9]\d*\.\d+,/', $resp)) {
            throw new \CheckRetryNeededException(5, 0);
        }
        if (isset($this->responseDataAlliance['outboundFlights']) && preg_match('/points":\s*0\.0,/', $respStar)
            && !preg_match('/points":\s*[1-9]\d*\.\d+,/', $respStar)) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->logger->warning('parse eurobonus');
        $res = $this->parseResponse($this->responseData, $fields);
        $this->logger->warning('parse star alliance');
        $resAlliance = $this->parseResponse($this->responseDataAlliance, $fields, true);

        if (isset($res) || isset($resAlliance)) {
            $result = array_merge($res['routes'] ?? [], $resAlliance['routes'] ?? []);
            if (empty($result)) {
                return ['routes' => []];
            }
            // reset warning
            $this->ErrorMessage = "Unknown error";
            $this->ErrorCode = ACCOUNT_UNCHECKED;

            return ['routes' => $result];
        }

        throw new \CheckRetryNeededException(5, 0);
    }

    private function parseResponse($responseData, $fields, $isAlliance = false)
    {
        if ($responseData === []) {
            $this->SetWarning('Unfortunately, we can\'t seem to find anything that matches what you\'re looking for. Please refine your search.');

            return [];
        }

        if (!empty($responseData) && (null !== ($noRoute = $this->parseError($responseData, $fields, $isAlliance)))) {
            return $noRoute;
        }

        if (!empty($responseData) && isset($responseData['outboundFlights'])) {
            return ['routes' => $this->parseRewardFlights($fields, $responseData, $isAlliance)];
        }

        return null;
    }

    private function checkRouteOnPoints($access_token, $fields, $isAlliance): ?array
    {
        if ($isAlliance) {
            $bookingFlow = 'star';
        } else {
            $bookingFlow = 'points';
        }
        $headers = [
            'Authorization' => $access_token,
            'Accept'        => 'application/json, text/plain, */*',
            'Content-Type'  => 'application/json',
            'Referer'       => 'https://www.flysas.com/',
        ];
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://api.flysas.com/searchpanel/validate?origin={$fields['DepCode']}&destination={$fields['ArrCode']}&pos=us&paxType=ADT&bookingFlow=" . $bookingFlow,
            $headers);
        $this->http->RetryCount = 2;

        $res = $this->http->JsonLog();

        if (isset($res->status) && $res->status == false) {
            $this->SetWarning('Unfortunately, it\'s not possible to pay for trips to these destinations using points');

            return ['routes' => []];
        }

        return null;
    }

    private function getCabinFields(): array
    {
        $cabins = [
            'economy'        => ['awardType' => ['GO', 'ECONOMY']],
            'premiumEconomy' => ['awardType' => ['PLUS']],
            'firstClass'     => [],
            'business'       => ['awardType' => ['BUSINESS']],
        ];

        return $cabins;
    }

    private function parseError($data, $fields, $isAlliance): ?array
    {
        $this->logger->notice(__METHOD__);

        if (!isset($data['outboundFlights'])) {
            if (isset($data['errors'][0]['errorCode'])) {
                if (isset($this->State['prevErrorCode']) && $data['errors'][0]['errorCode'] !== $this->State['prevErrorCode']) {
                    // не ясно нужен ли рестарт для 225044.. либо при проверке логов просто появились рейсы
                    $this->sendNotification("restart needed (225044) // ZM");
                }

                if (in_array($data['errors'][0]['errorCode'], ['225035', '225036', '225045', '225046', '2075010', '225063', '225050',  '225034', '225026', '2065072'])) {
                    $this->SetWarning('Unfortunately, we can\'t seem to find anything that matches what you\'re looking for. Please refine your search.');

                    return [];
                }

                if ($data['errors'][0]['errorCode'] === '225017'
                    || $data['errors'][0]['errorCode'] === '225025'
                    || $data['errors'][0]['errorCode'] === '225024'
                    || $data['errors'][0]['errorCode'] === '2145015'
                    || $data['errors'][0]['errorCode'] === '225071'
                ) {
                    // 'Please select the length of your trip.' - 225017
                    // 'Unfortunately, these offers aren't available on the date you want to return.' - 225025
                    // 'Unfortunately, these offers aren't available on the date you want to leave.  ' - 225024
                    // 'We are facing issues with your request. Please try again, and if problem still persists contact SAS support [04]' - 2145015
                    // 'We currently have problems showing flights this close to departure. Please select a departure date at least 4 days from now.' - 225071
                    $this->SetWarning($data['errors'][0]['errorMessage']);

                    return [];
                }

                if ((!empty($this->access_token) && (null !== $this->checkRouteOnPoints($this->access_token, $fields, $isAlliance)))) {
                    return [];
                }

                if (in_array($data['errors'][0]['errorCode'],['225014','225064'])) {
                    if ($data['errors'][0]['errorCode'] === '225064' && $isAlliance) {
                        $this->SetWarning('Unfortunately, we can\'t seem to find anything that matches what you\'re looking for. Please refine your search.');

                        return [];
                    }
                    // Sorry, something went wrong when we were processing your request. Please call us and we'll try to help you
                    // Unfortunately, the page you're looking for isn't available right now. Please reload the site by clicking on the SAS logo.
                    if ($this->attempt === 0) { // helping
                        $this->logger->error($data['errors'][0]['errorMessage']);

                        throw new \CheckRetryNeededException(5, 0);
                    }

                    throw new \CheckException($data['errors'][0]['errorMessage'], ACCOUNT_PROVIDER_ERROR);
                }

                if (in_array($data['errors'][0]['errorCode'], ['225044', '2035013'])){
                    $this->SetWarning('There are no available seats on the selected date. Please select another date.');

                    return [];
                }

                if ($data['errors'][0]['errorCode'] === '2065008') {
                    // We are not able to confirm your selected flights. Please resubmit your request.

                    if ($this->attempt === 0) {
                        $this->sendNotification("check restart (2065008) // ZM");
                        $this->logger->error($data['errors'][0]['errorMessage']);

                        throw new \CheckRetryNeededException(5, 0);
                    }
                    return [];
                }

                if (in_array($data['errors'][0]['errorCode'],['2145014','225048'])) {
                    // 2145014 - we are unable to get points price from CLM.
                    // 225048 - Session Timed Out.Please Try Again
                    // 2065020 - The total price for the services of the trip has changed.

                    if ($this->attempt === 0) {
                        $this->logger->error($data['errors'][0]['errorMessage']);

                        throw new \CheckRetryNeededException(5, 0);
                    }
                    return [];
                }

                $this->logger->error($data['errors'][0]['errorMessage']);

                if (!$isAlliance || $this->ErrorMessage === "Unknown error") {
                    $this->sendNotification("check errorMessage // ZM");
                }

                return [];
            }

            throw new \CheckException("something went wrong", ACCOUNT_ENGINE_ERROR);
        }

        return null;
    }

    private function parseRewardFlights($fields, $data, $isAlliance): array
    {
        $this->logger->notice(__METHOD__);
        $routes = [];

        if (null !== ($noRouts = $this->parseError($data, $fields, $isAlliance))) {
            return $noRouts;
        }

        $this->logger->debug("Fount " . count($data['outboundFlights']) . " routes");

        $cabins = $this->getCabinFields();
        $cabinByAwards = [];

        foreach ($cabins as $cabin => $v) {
            if (isset($v['awardType'])) {
                foreach ($v['awardType'] as $awardType) {
                    $cabinByAwards[$awardType] = $cabin;
                }
            }
        }

        foreach ($data['outboundFlights'] as $outboundFlight) {
            if (isset($outboundFlight['isSoldOut']) && $outboundFlight['isSoldOut'] === true) {
                $this->logger->notice("skip route: sold out");

                continue;
            }
            $startTimeInLocal = strtotime(substr($outboundFlight['startTimeInLocal'], 0, 10));

            if ($startTimeInLocal !== $fields['DepDate']) {
                $this->logger->notice("skip route: wrong start date");
                $skippedByDate = true;

                continue;
            }

            $layover = null;
            $totalFlight = null;
            $segments = [];

            foreach ($outboundFlight['segments'] as $s) {
                $seg = [
                    'id'        => $s['id'],
                    'num_stops' => $s['numberOfStops'],
                    'departure' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($s['departureDateTimeInLocal'], 0, 16))),
                        'dateTime' => strtotime(substr($s['departureDateTimeInLocal'], 0, 16)),
                        'airport'  => $s['departureAirport']['code'],
                        'terminal' => $s['departureTerminal'] ?? null,
                    ],
                    'arrival' => [
                        'date'     => date('Y-m-d H:i', strtotime(substr($s['arrivalDateTimeInLocal'], 0, 16))),
                        'dateTime' => strtotime(substr($s['arrivalDateTimeInLocal'], 0, 16)),
                        'airport'  => $s['arrivalAirport']['code'],
                        'terminal' => $s['arrivalTerminal'] ?? null,
                    ],
                    'meal'       => null,
                    'cabin'      => null,
                    'fare_class' => null,
                    'distance'   => null,
                    'aircraft'   => $s['airCraft']['name'] ?? $s['airCraft']['code'],
                    'flight'     => [$s['marketingCarrier']['code'] . $s['flightNumber']],
                    'airline'    => $s['marketingCarrier']['code'],
                    'operator'   => null,
                    'times'      => [
                        'flight'  => null,
                        'layover' => null,
                    ],
                    'tickets' => null,
                ];
                $segments[] = $seg;
            }

            $this->logger->debug("Found " . count($outboundFlight['cabins']) . " offers");

            foreach ($outboundFlight['cabins'] as $key => $value) {
                foreach ($value as $award => $item) {
                    foreach ($item['products'] as $idProd => $product) {
                        $newSeg = $segments;
                        $tickets = 100;

                        foreach ($product['fares'] as $fare) {
                            $fares[(int) $fare['segmentId']] = $fare;
                        }

                        foreach ($segments as $i => $s) {
                            $newSeg[$i]['cabin'] = $cabinByAwards[$key] ?? null;
                            $newSeg[$i]['classOfService'] = $key;

                            if (isset($fares[$s['id']])) {
                                $newSeg[$i]['fare_class'] = $fares[$s['id']]['bookingClass'];
                                $newSeg[$i]['tickets'] = $fares[$s['id']]['avlSeats']; // 9 - means null
                                $newSeg[$i]['classOfService'] = $key;

                                if (null !== $newSeg[$i]['tickets']) {
                                    $tickets = min($newSeg[$i]['tickets'], $tickets);
                                }
                            }
                            unset($newSeg[$i]['id']);
                        }

                        $price = $this->getPrice($product['price'], $fields['Adults']);

                        if ($isAlliance && empty($price['totalTax'])) {
                            if((time() - $this->requestDateTime) > $this->AccountFields['Timeout']) {
                                $this->SetWarning('Not all flights collected taxes');
                                return $routes;
                            }
                            $priceEmpty = $price;
                            $price = $this->getFarePrice($fields, $data['offerId'], $product, $idProd, $outboundFlight);

                            if (null === $price) {
                                $price = $priceEmpty;
                            }
                        }

                        if (empty($price['totalTax'])) {
                            $this->logger->error('no taxes');
                            $price['totalTax'] = null; // if 0 - can't get price, not 0
                        }
                        // for debug
                        $this->logger->debug(var_export($price, true), ['pre' => true]);
                        if (empty($price['pointsAfterDiscount'])){
                            if ($this->attempt === 0) {
                                // restart позволяет получить "потерянные" поинты, глюк прова может.
                                // но если уже не первая попытка, то собрать, что есть
                                throw new \CheckRetryNeededException(5, 0);
                            }
                            $this->logger->error('skip payment. no points');
                            $hasSkippedByPoints = true;
                            continue;
                        }
                        $result = [
                            'award_type'     => $product['productName'],
                            'classOfService' => $key,
                            'num_stops'      => $outboundFlight['stops'],
                            'times'          => [
                                'flight' => $totalFlight,
                                //                                'flight' => substr($outboundFlight['connectionDuration'], 0, -3),
                                'layover' => $layover,
                            ],
                            'redemptions' => [
                                'miles'   => $price['pointsAfterDiscount'],
                                'program' => $this->AccountFields['ProviderCode'],
                            ], // for one
                            'payments' => [
                                'currency' => $price['currency'],
                                'taxes'    => $price['totalTax'],
                                'fees'     => null,
                            ],
                            'tickets'     => $tickets !== 100 ? $tickets : null,
                            'connections' => $newSeg,
                        ];
                        $this->logger->debug(var_export($result, true), ['pre' => true]);
                        $routes[] = $result;
                    }
                }
            }
        }

        if (isset($hasSkippedByPoints)) {
            $this->sendNotification('check skipped (zero points) // ZM');
            if (empty($routes)) {
                throw new \CheckRetryNeededException(5, 0);
            }
        }
        if (isset($skippedByDate) && empty($routes)) {
            $this->logger->error('all skipped. wrong dates');

            $this->SetWarning('There are no flights available for the selected number of passengers on this date.');
        }

        return $routes;
    }

    private function getPrice($data, $adults): array
    {
        $price = null;

        foreach ($data['pricePerPassengerType'] as $perType) {
            if ($perType['type'] === 'ADT') {
                $price = $perType['price'];

                break;
            }
        }

        if (!isset($price)) {
            if ($adults === 1) {
                $price = $data; // for all
            } else {
                $this->sendNotification("check pricePerPassengerType // ZM");

                throw new \CheckException("check pricePerPassengerType", ACCOUNT_ENGINE_ERROR);
            }
        }

        return $price;
    }

    private function selenium(array $fields): ?array
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumRequest->request(self::CONFIGS[$this->config]['browser-family'], self::CONFIGS[$this->config]['browser-version']);

            $selenium->seleniumOptions->recordRequests = true;

            $this->logger->debug('Off extension');
            $selenium->seleniumOptions->addHideSeleniumExtension = false;
            $this->logger->debug('Off pac file');
            $selenium->usePacFile(false);

//            $selenium->disableImages();
            $selenium->seleniumOptions->showImages = false;

            $resolutions = [
                [1152, 864],
                [1280, 720],
                [1280, 768],
                [1280, 800],
                [1360, 768],
                [1366, 768],
            ];
            $chosenResolution = $resolutions[array_rand($resolutions)];
            $selenium->setScreenResolution($chosenResolution);

            $selenium->http->saveScreenshots = true;

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\ErrorException $e) {
                $this->logger->error('ErrorException: ' . $e->getMessage());
                throw new \CheckRetryNeededException(5, 0);
            }

//            $airports = $this->getAirports($selenium);
//
//            if (!in_array($fields['DepCode'], $airports)) {
//                $this->SetWarning('Unfortunately, no airports or origins match what you\'ve entered. - ' . $fields['DepCode']);
//
//                return [];
//            }
//
//            if (!in_array($fields['ArrCode'], $airports)) {
//                $this->SetWarning('Unfortunately, no airports or destinations match what you\'ve entered. - ' . $fields['ArrCode']);
//
//                return [];
//            }

            try {
                $selenium->http->GetURL("https://www.flysas.com/us-en/");
                $this->acceptCookies($selenium, 'https://www.flysas.com/us-en/');
                $selenium->waitForElement(\WebDriverBy::xpath('//h2[contains(text(), "Offers right now")]'), 5);

                $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
                $selenium->http->SaveResponse();

                if ($this->http->FindSingleNode("//h1[contains(.,'403 - Forbidden')]
                | //h2[contains(text(),'Forbidden')]")) {
                    $this->logger->error('403 - Forbidden');

                    throw new \CheckRetryNeededException(5, 0);
                }
            } catch (\ScriptTimeoutException | \TimeOutException $e) {
                $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
                // retries
                if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $this->logger->debug("[attempt]: {$this->attempt}");

                    throw new \CheckRetryNeededException(5, 0);
                }
            } catch (\Facebook\WebDriver\Exception\UnknownErrorException $e) {
                $this->logger->error("UnknownErrorException: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if ($cookie['name'] === 'oauthtoken') {
                    $this->access_token = $cookie['value'];
                }

                if ($cookie['name'] === 'reese84') {
                    $this->reese84 = $cookie['value'];
                }
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'],
                    $cookie['expiry'] ?? null);
            }

//            $this->auth();

//            if (empty($this->access_token)) {
//                // if attempt>0 collect without tax
//                $this->logger->error('No access_token!');
//                $retry = true;
//            }

            return null;
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException exception: " . $e->getMessage());
            $this->DebugInfo = "ScriptTimeoutException";
        }// catch (ScriptTimeoutException $e)
        catch (\UnknownServerException | \SessionNotCreatedException | \NoSuchWindowException | \WebDriverException | \WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "exception";
            $retry = true;
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
            // retries
            if (isset($retry) && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }
    }

    private function getResponse($selenium, $url, $fields, $isAlliance = false)
    {
        try {
            $responseData = $this->openPageGetResponse($selenium, $url);
        } catch (\WebDriverException $e) {
            $this->logger->error('WebDriverException: ') . $e->getMessage();

            throw new \CheckRetryNeededException(5, 0);
        }
        $dateStr = date("Ymd", $fields['DepDate']);

        if (strpos($responseData, '"errorMessage":"we are unable to get points price from CLM."') !== false) {
            $this->http->JsonLog($responseData, 1);

            throw new \CheckException('Sorry, it is not possible to book flights using points right now. We are working on getting this fixed as soon as possible.', ACCOUNT_PROVIDER_ERROR);
        }

        if (strpos($responseData, '"errorMessage":"An error has occurred in our central database. Please contact us for further information"') !== false
            || strpos($responseData, '"errorMessage":"Session Timed Out.Please Try Again"') !== false
        ) {
            $this->http->JsonLog($responseData, 1);
            $responseData = $this->openPageGetResponse($selenium, $url);
        }

        if (strpos($responseData, '"errorMessage":"An error has occurred in our central database. Please contact us for further information"') !== false
            || strpos($responseData, '"errorMessage":"Session Timed Out.Please Try Again"') !== false
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if (empty($responseData)) {
            if ($selenium->waitForElement(\WebDriverBy::xpath('//div[@role="alert"]/p[contains(.,"seem to find anything that")]'),
                3)) {
                return [];
            }

            if ($isAlliance) {
                $bookingFlow = 'star';
            } else {
                $bookingFlow = 'points';
            }
            $selenium->http->GetURL("https://www.flysas.com/api/offers/flights?to={$fields['ArrCode']}&from={$fields['DepCode']}&outDate={$dateStr}&adt={$fields['Adults']}&chd=0&inf=0&yth=0&bookingFlow={$bookingFlow}&pos=us&channel=web&displayType=upsell");
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
            $responseData = $this->http->FindPreg("/<pre>({.+})/");
        }

        if (strpos($responseData, '"errorMessage":"we are unable to get points price from CLM."') !== false
            && (time() - $this->requestDateTime) < 70
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        return $responseData;
    }

    private function openPageGetResponse($selenium, $url)
    {
        $this->logger->notice(__METHOD__);

        try {
            $selenium->http->GetURL($url);
        } catch (\ScriptTimeoutException | \TimeOutException $e) {
            $this->logger->error("ScriptTimeoutException: " . $e->getMessage());
            // retries
            if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                $this->logger->debug("[attempt]: {$this->attempt}");

                throw new \CheckRetryNeededException(5, 0);
            }
        }
        $this->acceptCookies($selenium, $url);
        // save page to logs
        $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
        $selenium->http->SaveResponse();

        if ($selenium->waitForElement(\WebDriverBy::xpath("//div[@role='alert']/p[contains(normalize-space(),'As you were browsing something about your browser made us think yor were a bot')]"), 7)
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        // retries
        if ($this->http->FindPreg('/(?:page isn’t working|There is no Internet connection|This site can’t be reached|403 - Forbidden)/ims')) {
            $this->markProxyAsInvalid();

            throw new \CheckRetryNeededException(5, 0);
        }

        if ($btn = $selenium->waitForElement(\WebDriverBy::xpath("//button[normalize-space()='Cookie settings']/following-sibling::button[normalize-space()='Accept']"),
            0)) {
            $btn->click();
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $selenium->http->SaveResponse();
        }
        $this->waitFor(function () use ($selenium) {
            return $selenium->waitForElement(\WebDriverBy::xpath('
                    //h1[contains(text(),"Select flights")]
                  | //a[@id="regular-calendar-tab" 
                     or @id="UpsellBookTdyJrnyDtHeaderoutbound" 
                     or @id="UpsellBookValidateHdrFlight"]
            '), 0);
        }, 15);
        $responseData = null;
        /** @var \SeleniumDriver $seleniumDriver */
        $seleniumDriver = $selenium->http->driver;
        $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

        foreach ($requests as $n => $xhr) {
            if (strpos($xhr->request->getUri(), 'api/offers/flights?to') !== false
                && $xhr->response->getStatus() == 200) {
                $responseData = json_encode($xhr->response->getBody());
            }

            if (strpos($xhr->request->getUri(), '/authorize/oauth/token?grant_type=client_credentials') !== false
                && $xhr->response->getStatus() == 200) {
                $this->access_token = $xhr->response->getBody()['access_token'];
            }

            if (isset($responseData, $this->access_token)) {
                break;
            }
        }

        if (!isset($this->access_token)) {
            $scriptAjax = '
                    var xhttp = new XMLHttpRequest();
                    xhttp.open("POST", "https://api.flysas.com/authorize/oauth/token?grant_type=client_credentials", false);
                    xhttp.setRequestHeader("Accept", "application/json, text/plain, */*");
                    xhttp.setRequestHeader("Content-type", "application/json");
                    xhttp.setRequestHeader("Authorization", "Basic U0FTLVVJOg==");
                    var resData = null;
                    xhttp.onreadystatechange = function() {
                        resData = this.responseText;
                    };
                    xhttp.send(\'{}\');
                    return resData;
                ';
            $this->logger->debug("[run script]");
            $this->logger->debug($scriptAjax, ['pre' => true]);
            sleep(2);

            try {
                $response = $selenium->driver->executeScript($scriptAjax);
            } catch (\UnexpectedJavascriptException | \XPathLookupException | \WebDriverException $e) {
                $this->logger->error($e->getMessage());
                $response = '';
            }
            $data = trim($response);
            $response = $this->http->JsonLog($data, 0, true);

            if (is_array($response)) {
                $this->access_token = $response['access_token'] ?? null;
            }
        }

        return $responseData;
    }

    private function acceptCookies($selenium, $url)
    {
        $cookie = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(@class,'accept')]"), 5);

        if ($cookie) {
            try {
                $cookie->click();
            } catch (\UnrecognizedExceptionException $e) {
                $this->logger->error("UnrecognizedExceptionException: {$e->getMessage()}");
                $selenium->http->GetURL($url);

                $cookie = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(@class,'accept')]"), 5);

                if ($cookie) {
                    $cookie->click();
                }
            }
        }
    }

    private function getFarePrice($fields, $offerId, $product, $idProd, $outboundFlight)
    {
        $this->logger->notice(__METHOD__);

        if (!$this->access_token) {
            return null;
        }

        if (($pos = strpos($idProd, '_')) === false) {
            throw new \CheckException("can't run getFare: unknown recoId", ACCOUNT_ENGINE_ERROR);
        }
        $recoId = substr($idProd, $pos + 1);
        $fares = [];

        foreach ($product['fares'] as $fare) {
            $fares[] = ['bookingClass' => $fare['bookingClass'], 'segmentId' => $fare['segmentId']];
        }
        $fares = json_encode($fares); // [{"bookingClass":"X","segmentId":"11"}]
        $segments = [];

        foreach ($outboundFlight['segments'] as $segment) {
            $seg = [
                'id'                => $segment['id'],
                'departureAirport'  => $segment['departureAirport']['code'],
                'departureCity'     => null,
                'departureDateTime' => preg_replace("/^\s*(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}).+/", '$1$2$3$4$5',
                    $segment['departureDateTimeInLocal']),
                'arrivalAirport'  => $segment['arrivalAirport']['code'],
                'arrivalCity'     => null,
                'arrivalDateTime' => preg_replace("/^\s*(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}).+/", '$1$2$3$4$5',
                    $segment['arrivalDateTimeInLocal']),
                'flightNumber'         => $segment['flightNumber'],
                'marketingCarrierCode' => $segment['marketingCarrier']['code'],
            ];

            if (isset($segment['departureCity']['code'])) {
                $seg['departureCity'] = $segment['departureCity']['code'];
            } else {
                unset($seg['departureCity']);
            }

            if (isset($segment['arrivalCity']['code'])) {
                $seg['arrivalCity'] = $segment['arrivalCity']['code'];
            } else {
                unset($seg['arrivalCity']);
            }
            $segments[] = $seg;
        }
        $segments = json_encode($segments); // [{"id":11,"departureAirport":"KUL","departureCity":"KUL","departureDateTime":"202303161305","arrivalAirport":"BKK","arrivalCity":"BKK","arrivalDateTime":"202303161410","flightNumber":"416","marketingCarrierCode":"TG"}]

        $url = 'https://api.flysas.com/offers/flightproducts/getFare';
        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => $this->access_token,
            'Content-Type'  => 'application/json',
            'Origin'        => 'https://www.flysas.com',
        ];
        $payload = '{"adultCount":' . $fields['Adults'] . ',"childCount":0,"infantCount":0,"youthCount":0,"offerId":"' . $offerId . '","pos":"us","bookingFlow":"star","lang":"en","channel":"web","searchOrigin":null,"searchDestination":null,"flightProducts":[{"boundType":"outbound","recoFlightId":' . $outboundFlight['id'] . ',"recoId":' . $recoId . ',"fares":' . $fares . ',"flightDetails":{"origin":"' . $fields['DepCode'] . '","destination":"' . $fields['ArrCode'] . '","segments":' . $segments . '}}],"cepId":"DEFAULT"}';
        $this->http->RetryCount = 0;
        $this->http->PostURL($url, $payload, $headers);

        if (strpos($this->http->Error, 'Network error 52 - Empty reply from server') !== false) {
            sleep(random_int(0, 1));
            $this->http->PostURL($url, $payload, $headers);
        }
        $this->http->RetryCount = 2;
        $data = $this->http->JsonLog(null, 1, true);

        if ($this->http->Response['code'] == 200 && isset($data['price'])) {
            $product['price']['totalTax'] = $data['price']['totalTax'];
            $product['price']['currency'] = $data['price']['currency'];
        }

        return $product['price'];
    }

    private function getAirports($selenium): array
    {
        $this->logger->notice(__METHOD__);
        $airportCodes = \Cache::getInstance()->get('ra_sk_airports');

        if (!is_array($airportCodes)) {
            $headers = [
                'x-requested-with' => 'XMLHttpRequest',
                'accept'           => 'application/json, text/javascript, */*; q=0.01',
            ];

            $selenium->http->GetURL("https://www.flysas.com/v2/cms-www-api/data/geo/cep/?market=us-en&usertype=default", $headers);


            if (strpos($selenium->http->Error, 'Network error 28 - Connection timed out after') !== false
                || strpos($selenium->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
                || strpos($selenium->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
                || strpos($selenium->http->Error, 'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
                || strpos($selenium->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
                || $selenium->http->Response['code'] == 403
            ) {
                throw new \CheckRetryNeededException(5, 0);
            }

            $json = trim(str_replace(['<pre>', '</pre>'], [''], $selenium->http->Response['body']));

            $airports = $selenium->http->JsonLog($json, 1, true);
            $airportCodes = [];

            if (isset($airports['countries'])) {
                foreach ($airports['countries'] as $region) {
                    foreach ($region['cities'] as $city) {
                        $airportCodes[] = $city['code'];
                    }
                }
            }

            if (!empty($airportCodes)) {
                \Cache::getInstance()->set('ra_sk_airports', $airportCodes, 60 * 60 * 24);
            }
        }

        return $airportCodes;
    }

    private function auth()
    {
        $headers = [
            'Accept' => 'application/json, text/plain, */*',
            'Content-type' => 'application/json',
            'Authorization' => 'Basic U0FTLVVJOg==',
            'Origin'        => 'https://www.flysas.com',
            'Referer'        => 'https://www.flysas.com',
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL(
            "https://api.flysas.com/authorize/oauth/token?grant_type=client_credentials",
            [],
            $headers,
        );
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200) {
            $this->http->RetryCount = 0;
            $this->http->PostURL(
                "https://api.flysas.com/authorize/oauth/token?grant_type=client_credentials",
                [],
                $headers,
            );
            if ($this->http->Response['code'] != 200) {
                throw new \CheckRetryNeededException(5, 0);
            }
            $this->http->RetryCount = 2;
        }

        $auth = $this->http->JsonLog(null, 1, true);

        if (!isset($auth['access_token'])) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->http->setCookie('oauthtoken', $auth['access_token'], 'www.flysas.com');
        $this->access_token = $auth['access_token'];
    }

    private function getBookingData($fields, $isAlliance = false)
    {
        $dateStr = date("Ymd", $fields['DepDate']);
        $search = "OW_{$fields['DepCode']}-{$fields['ArrCode']}-{$dateStr}_a{$fields['Adults']}c0i0y0";

        $headers = [
            'Accept'        => 'application/json, text/plain, */*',
            'Authorization' => $this->access_token,
            'Content-Type'  => 'application/json',
            'Origin'        => 'https://www.flysas.com',
        ];

        if ($isAlliance) {
            if ((time() - $this->requestDateTime) > 75) {
                $this->SetWarning('Without Star Alliance');
                return [];
            }
            $bookingFlow = 'star';
            $headers['Referer'] = "https://www.flysas.com/us-en/book/flights/?search={$search}&view=upsell&bookingFlow=star&origin=eurobonus/star-alliance-award-trips/";
        } else {
            $bookingFlow = 'points';
            $headers['Referer'] = "https://www.flysas.com/us-en/book/flights/?search={$search}&view=upsell&bookingFlow=points&sortBy=stop,stop";
        }

        $query = http_build_query([
            'to' => $fields['ArrCode'],
            'from' => $fields['DepCode'],
            'outDate' => $dateStr,
            'adt' => $fields['Adults'],
            'chd' => 0,
            'inf' => 0,
            'yth' => 0,
            'bookingFlow' => $bookingFlow,
            'pos' => 'us',
            'channel' => 'web',
            'displayType' => 'upsell',
        ]);

        $this->http->RetryCount = 0;
        $this->http->GetURL(
            "https://www.flysas.com/api/offers/flights?{$query}",
            $headers
        );
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] != 200
            || ($dbProblem = strpos($this->http->Response['body'],
                'An error has occurred in our central database. Please contact us for further information') !== false)
        ) {
            if (isset($dbProblem) && $dbProblem) {
                // retry helped 95%
                sleep(1);
            }
            $this->http->RetryCount = 0;
            $this->http->GetURL(
                "https://www.flysas.com/api/offers/flights?{$query}",
                $headers
            );
            $this->http->RetryCount = 2;
            if ($this->http->Response['code'] != 200
                || strpos($this->http->Response['body'],
                        'An error has occurred in our central database. Please contact us for further information') !== false
            ) {
                if (!$isAlliance) {
                    throw new \CheckRetryNeededException(5, 0);
                }
                $this->SetWarning('Without Star Alliance');
                return [];
            }
        }


        return $this->http->JsonLog(null, 1, true);
    }
}
