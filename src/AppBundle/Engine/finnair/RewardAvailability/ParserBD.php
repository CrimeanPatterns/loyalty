<?php

namespace AwardWallet\Engine\finnair\RewardAvailability;

use AwardWallet\Common\Selenium\FingerprintFactory;
use AwardWallet\Common\Selenium\FingerprintRequest;
use AwardWallet\Engine\ProxyList;

//include_once $_SERVER['DOCUMENT_ROOT'] . '/admin/sdebug/index.php';

class ParserBD extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use ProxyList;

    private const CABIN_ALIAS = [
        'business'       => 'BUSINESS',
        'economy'        => 'ECONOMY',
        'premiumEconomy' => 'ECOPREMIUM',
        'all'            => 'MIXED',
    ];
    private const CABIN_HUMAN_ALIAS = [
        'BUSINESS'   => 'Business',
        'ECONOMY'    => 'Economy',
        'ECOPREMIUM' => 'Premium Economy',
        'MIXED'      => 'Mixed',
    ];

    private int $selenium_start_count;

    public function InitBrowser()
    {
        $this->selenium_start_count = 0;
        \TAccountChecker::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $array = ['fr', 'es', 'de', 'us', 'au', 'gb', 'pt', 'ca'];
        $targeting = $array[random_int(0, count($array) - 1)];
        $this->setProxyGoProxies(null, $targeting);

        $request = FingerprintRequest::chrome();
        $request->browserVersionMin = 100;
        $request->platform = 'Win32';
        $this->fingerprint = $this->services->get(FingerprintFactory::class)->getOne([$request]);

        if (isset($this->fingerprint)) {
            $this->http->setUserAgent($this->fingerprint->getUseragent());
        }

//        $this->http->setRandomUserAgent(null, false, true, false, true, false);
        $this->KeepState = false;
    }

    public function isLoggedIn()
    {
//        return false;
        // Check Access
        $success = $this->http->getCookieByName("CASTGC", "auth.finnair.com", "/cas", true);

        if (!$success) {
            $this->logger->error("CASTGC cookie not found");

            return false;
        }
        $this->http->GetURL("https://auth.finnair.com/content/en/login/finnair-plus/");
        if (!$this->http->FindSingleNode('//p[contains(text(), "You have logged in to Finnair’s service.")]')) {
            return false;
        }

        if ($this->isBadProxy()
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'empty body') !== false
            || $this->http->Response['code'] != 200
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        $this->logger->notice("success -> {$success}");

        return true;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        $this->http->RetryCount = 0;
        $this->http->GetURL("https://auth.finnair.com/content/en/login/finnair-plus/");

        if ($this->isBadProxy()
            || strpos($this->http->Error, 'Network error 28 - Operation timed out after') !== false
            || strpos($this->http->Error, 'empty body') !== false
            || $this->http->Response['code'] != 200
        ) {
            throw new \CheckRetryNeededException(5, 0);
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Access Denied")]')) {
            return false;
        }

        $abck = $this->getABCKToken();
        $this->http->setCookie("_abck", $abck);

        return true;
    }

    public function Login()
    {
        $headers = [
            "Accept"       => "application/json, text/plain, */*",
            "Content-Type" => "application/x-www-form-urlencoded",
            "Origin"       => "https://auth.finnair.com",
            "User-Agent"   => "curl/7.88.1",
        ];

        // get execution value
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://auth.finnair.com/cas/login', $headers);
        $response = $this->http->JsonLog();
        $execution_param = $response->execution;

        $data = [
            "_eventId"     => "submit",
            "username"     => $this->AccountFields['Login'],
            "password"     => $this->AccountFields['Pass'],
            "execution"    => $execution_param,
            "redirectJson" => "true",
            "rememberMe"   => "true",
        ];


        $this->http->PostURL("https://auth.finnair.com/cas/login", $data, $headers);

        if ($this->http->Response["code"] == 403) {
            $this->logger->error("sensor_data (_abck) expired");

            $abck = $this->getABCKToken(true);
            $this->http->setCookie("_abck", $abck);

            $this->http->PostURL("https://auth.finnair.com/cas/login", $data, $headers);
        }

        if ($this->http->Response["code"] == 403) {
            $error_message = 'fresh _abck not valid';
            $this->logger->error($error_message);

            throw new \CheckException($error_message, ACCOUNT_ENGINE_ERROR);
        }

        // Check Access
        $success = $this->http->getCookieByName("CASTGC", "auth.finnair.com", "/cas", true);
        $this->logger->notice("success -> {$success}");

        if (!$success) {
            $this->logger->error("exception: CASTGC cookie not found. Retry");

            throw new \CheckRetryNeededException(5, 0);
        }

        // Login failed. Please check your username (your email or Finnair Plus membership number without the AY prefix and spaces) and password.
        if ($message = $this->http->FindPreg('/"(Login failed\. Please check your username.+?)"/')) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Login failed. Please check your username and password.
        if ($message = $this->http->FindSingleNode("//p[contains(text(),'Login failed. Please check your username')]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // The membership number or email address you entered is invalid.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'The membership number or email address you entered is invalid.')]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        // Your Traveller ID / Password combination is not correct.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Your Traveller ID / Password combination is not correct.')]")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Invalid credentials.
        if ($message = $this->http->FindPreg("/Invalid credentials\./")) {
            throw new \CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // An error occurred, please try again later.
        if ($message = $this->http->FindPreg('/.show\(\)\.text\(\'(An error occurred, please try again later\.)\'\)\;/')) {
            throw new \CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return true;
    }

    public function ParseRewardAvailability(array $fields)
    {
        if ($this->selenium_start_count > 1) {
            $this->sendNotification("During the parsing process, Selenium was launched more than 1 time");
        }

        if (!$this->checkFields($fields)) {
            return ['routes' => []];
        }

        if (in_array($fields['Currencies'][0], $this->getRewardAvailabilitySettings()['supportedCurrencies']) !== true) {
            $fields['Currencies'][0] = $this->getRewardAvailabilitySettings()['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if ($this->AccountFields['ParseMode'] === 'awardwallet') {
            $cabin = self::CABIN_ALIAS[$fields['Cabin']];
        } else {
            $cabin = self::CABIN_ALIAS['all'];
        }

        if (!$cabin) {
            $this->SetWarning('No flights for checked cabin');

            return ['routes' => []];
        }

        $bearer = $this->getBearerToken();

        $this->logger->debug('start ParseRewardAvailability');

        $data = [
            "itinerary"     => [
                [
                    "departureLocationCode"   => $fields['DepCode'],
                    "destinationLocationCode" => $fields['ArrCode'],
                    "departureDate"           => date('Y-m-d', $fields['DepDate']),
                    "isRequestedBound"        => true,
                ],
            ],
            "adults"        => (int)$fields['Adults'],
            "children"      => 0,
            "c15s"          => 0,
            "infants"       => 0,
            "cabin"         => $cabin,
            "directFlights" => false,
            "locale"        => "en_IN",
            "isAward"       => true,
        ];

        $headers = [
            'Content-type'  => 'application/json',
            'Authorization' => $bearer,
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://api.finnair.com/d/fcom/offers-prod/current/api/offerList', json_encode($data), $headers);
        $response_array = $this->http->JsonLog(null, 1, true);

        // bearer QVQ token error
        if (($response_array['errorMessage'] ?? '') == 'Unable to authorize request with or without token') {
            $this->sendNotification("token timelimit is over");

            $bearer = $this->getBearerToken(true);

            $headers = [
                'Content-type'  => 'application/json',
                'Authorization' => $bearer,
            ];
            $this->http->PostURL('https://api.finnair.com/d/fcom/offers-prod/current/api/offerList', json_encode($data), $headers);
            $response_array = $this->http->JsonLog(null, 1, true);
        }

        // check errors
        if (!isset($response_array['offers'])) {
            $status = $response_array['status'] ?? '';

            if ($status == 'NO_FLIGHTS_FOUND') {
                $this->SetWarning('No flights found');

                return ['routes' => []];
            }

            if ($status == 'OTHER_ERROR') {
                $this->logger->error(var_export($status, true));

                $error_message = $response_array['errorMessage'] ?? 'Unknown error';

                throw new \CheckException($error_message, ACCOUNT_ENGINE_ERROR);
            }

            throw new \CheckException('Unknown error', ACCOUNT_ENGINE_ERROR);
        }

        // parse json
        return $this->fillOutputArray($response_array, $fields);
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['EUR'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'EUR',
        ];
    }

    public static function getRASearchLinks(): array
    {
        return ['https://www.finnair.com/en' => 'search page'];
    }

    private function seleniumCreateSensorData()
    {
        $this->selenium_start_count += 1;
        try {
            $selenium = clone $this;

            $this->http->brotherBrowser($selenium->http);
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            $selenium->disableImages();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = 100;
            $request->platform = 'Win32';
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

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\UnknownServerException|\SessionNotCreatedException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            $selenium->http->GetURL('https://www.finnair.com/us-en');

            if (!$selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Your journey starts here']"), 20)) {
                $this->logger->error('element not found');

                throw new \CheckException('not load', ACCOUNT_ENGINE_ERROR);
            }

            if ($button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Allow all cookies')]"), 5)) {
                $button->click();
            }
            $abck = $selenium->driver->manage()->getCookieNamed('_abck')['value'];

            if (!$abck) {
                throw new \CheckException('cookie _abck not found or empty', ACCOUNT_ENGINE_ERROR);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $abck ?? false;
    }

    private function seleniumBearerToken()
    {
        $this->selenium_start_count += 1;
        $this->logger->notice(__METHOD__);

        $allCookies = array_merge($this->http->GetCookies(".finnair.com"), $this->http->GetCookies(".finnair.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("auth.finnair.com"), $this->http->GetCookies("auth.finnair.com", "/", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("auth.finnair.com"), $this->http->GetCookies("auth.finnair.com", "/cas", true));
        $allCookies = array_merge($allCookies, $this->http->GetCookies("www.finnair.com"), $this->http->GetCookies("www.finnair.com", "/", true));

        try {
            $selenium = clone $this;

            $this->http->brotherBrowser($selenium->http);
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();
            $selenium->seleniumOptions->recordRequests = true;
            $selenium->useGoogleChrome(\SeleniumFinderRequest::CHROME_99);
            $selenium->disableImages();

            $request = FingerprintRequest::chrome();
            $request->browserVersionMin = 100;
            $request->platform = 'Win32';
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

            try {
                $selenium->http->start();
                $selenium->Start();
            } catch (\UnknownServerException|\SessionNotCreatedException $e) {
                $this->markProxyAsInvalid();
                $this->logger->error("exception: " . $e->getMessage());

                throw new \CheckRetryNeededException(5, 0);
            }

            $selenium->http->GetURL('https://www.finnair.com/us-en');

            if (!$selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Your journey starts here']"), 20)) {
                $this->logger->error('element not found');

                throw new \CheckException('not load', ACCOUNT_ENGINE_ERROR);
            }

            if ($button = $selenium->waitForElement(\WebDriverBy::xpath("//button[contains(.,'Allow all cookies')]"), 5)) {
                $button->click();
            }

//                sleep(5);

            foreach ($allCookies as $key => $value) {
                $selenium->driver->manage()->addCookie(['name' => $key, 'value' => $value, 'domain' => ".finnair.com"]);
            }

            $selenium->http->GetURL('https://www.finnair.com/us-en');

            if (!$selenium->waitForElement(\WebDriverBy::xpath("//h1[normalize-space()='Your journey starts here']"), 20)) {
                $this->logger->error('element not found');
                $this->savePageToLogs($selenium);

                throw new \CheckException('not load', ACCOUNT_ENGINE_ERROR);
            }

//            sleep(5);

            $url = 'https://www.finnair.com/en/my-finnair-plus';

            $selenium->http->GetURL($url);

            $element = $selenium->waitForElement(\WebDriverBy::xpath("//span[normalize-space()='Available award points']"), 40);
            $this->savePageToLogs($selenium);

            if (!$element) {
                $this->logger->error('element not found');

                throw new \CheckException('not load', ACCOUNT_ENGINE_ERROR);
            }

            // get list of xhr requests
            $seleniumDriver = $selenium->http->driver;

            $requests = $seleniumDriver->browserCommunicator->getRecordedRequests();

            // get Auth Bearer Token
            $auth = false;

            foreach ($requests as $xhr) {
                if (strpos($xhr->request->getHeaders()['Authorization'] ?? '', 'Bearer QVQ') === 0) {
                    $auth = $xhr->request->getHeaders()['Authorization'];

                    break;
                }
            }

            if (!$auth) {
                $this->logger->debug('bearer from selenium error');

                return false;
            }

            $this->logger->debug("bearer: $auth");
        } catch (\Facebook\WebDriver\Exception\UnknownErrorException|\Facebook\WebDriver\Exception\WebDriverCurlException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            throw new \CheckRetryNeededException(5, 0);
        } finally {
            // close Selenium browser
            $selenium->http->cleanup();
        }

        return $auth;
    }

    private function fillOutputArray($response_array, $fields)
    {
        $return = [];
        $outbound_info = [];

        if (!isset($response_array['outbounds'])) {
            throw new \CheckException('unknown response format', ACCOUNT_ENGINE_ERROR);
        }

        $outbounds = $response_array['outbounds'];

        foreach ($response_array['offers'] as $key => $offer) {
            $return['routes'][$key] = [
                'distance'       => null,
                'num_stops'      => $outbounds[$offer['outboundId']]['stops'],
                'tickets'        => null,
                'award_type'     => null,
                'classOfservice' => self::CABIN_HUMAN_ALIAS[$offer['outboundFareInformation'][0]['cabinClass']],
                'payments'       => [
                    'currency' => $response_array['currency'],
                    'taxes'    => $offer['totalPrice'] / (int)$fields['Adults'],
                    'fees'     => null,
                ],
                'redemptions'    => [
                    'miles'   => $offer['totalPointsPrice'] / (int)$fields['Adults'],
                    'program' => $this->AccountFields['ProviderCode'],
                ],
                'times'          => [
                    'flight'  => false, // time in air hours:minutes
                    'layover' => false, // time on surface hours:minutes
                ],
                'connections'    => [
                ],
            ];

            foreach ($offer['outboundFareInformation'] as $information) {
                $outbound_info[$information['segmentId']] = $information;
            }

            $flip_cabin_alias = array_flip(self::CABIN_ALIAS);

            foreach ($outbounds[$offer['outboundId']]['itinerary'] as $connection) {
                $type = $connection['type'];

                if ($type == 'FLIGHT') {
                    $return['routes'][$key]['connections'][] = [
                        'departure'      => [
                            'date'     => substr($connection['departure']['dateTime'], 0, -6),
                            'airport'  => $connection['departure']['locationCode'],
                            'terminal' => $connection['departure']['terminal'] ?? 1,
                        ],
                        'arrival'        => [
                            'date'     => substr($connection['arrival']['dateTime'], 0, -6),
                            'airport'  => $connection['arrival']['locationCode'],
                            'terminal' => $connection['arrival']['terminal'] ?? 1,
                        ],
                        'cabin'          => $flip_cabin_alias[$outbound_info[$connection['id']]['cabinClass']],
                        'classOfservice' => self::CABIN_HUMAN_ALIAS[$outbound_info[$connection['id']]['cabinClass']],
                        'fare_class'     => $outbound_info[$connection['id']]['bookingClass'],
                        'flight'         => [$connection['flightNumber']],
                        'airline'        => $connection['operatingAirline']['code'],
                        'aircraft'       => $connection['aircraftCode'],
                        'tickets'        => null,
                        'meal'           => null,
                        'times'          => [
                            'flight'  => null,
                            'layover' => null,
                        ],
                    ];
                }
            }
        }

        return $return;
    }

    private function getBearerToken($refresh = false)
    {
        if (($bearer = \Cache::getInstance()->get('ra_finnair_qvq_bearer')) && !$refresh) {
            $this->logger->debug('bearer from cache');
        } else {
            $this->logger->debug('try get bearer from selenium');

            if ($bearer = $this->seleniumBearerToken()) {
                \Cache::getInstance()->set('ra_finnair_qvq_bearer', $bearer, 60 * 60 * 24); // 24hours
                $this->logger->debug('bearer from selenium success');
            } else {
                $this->sendNotification("bearer token not received");

                throw new \CheckRetryNeededException(2, 0);
            }
        }

        return $bearer;
    }

    private function getABCKToken($refresh = false)
    {
        if (($abck = \Cache::getInstance()->get('ra_finnair_abck_token')) && !$refresh) {
            $this->logger->debug('_abck from cache');
        } else {
            $this->logger->debug('try get _abck from selenium');

            if ($abck = $this->seleniumCreateSensorData()) {
                \Cache::getInstance()->set('ra_finnair_abck_token', $abck, 60 * 60 * 24); // 24hours
                $this->logger->debug('_abck from selenium success');
            } else {
                $this->sendNotification("_abck token not received");

                throw new \CheckRetryNeededException(2, 0);
            }
        }

        return $abck;
    }

    private function checkFields($fields)
    {
        if ($fields['Adults'] > 9) {
            $this->SetWarning('Unacceptable number of passengers');

            return false;
        }

        $diff_in_seconds = time() - $fields['DepDate'];
        $days_in_diff = floor($diff_in_seconds / (60 * 60 * 24));

        if ($days_in_diff > 355) {
            $this->SetWarning('No flights available for the selected date');

            return false;
        }

        return $this->validRoute($fields);
    }

    private function validRoute($fields)
    {
        $http2 = new \HttpBrowser("none", new \CurlDriver());
        $this->http->brotherBrowser($http2);

        $to_check = [
            'departure' => $fields['DepCode'],
            'arrival'   => $fields['ArrCode'],
        ];

        foreach ($to_check as $type => $code) {
            if ('available' == (\Cache::getInstance()->get('ra_finnair_airports_' . $code)) ?? false) {
                $this->logger->debug('airport ' . $code . ' available - checked from cache');

                continue;
            }

            if ('not_available' == (\Cache::getInstance()->get('ra_finnair_airports_' . $code)) ?? false) {
                $this->logger->debug('airport ' . $code . ' not_available - checked from cache');

                continue;
            }

            if ('invalid' == (\Cache::getInstance()->get('ra_finnair_airports_' . $code)) ?? false) {
                $this->SetWarning('Invalid location code of ' . $type . ': ' . $code);
                $this->logger->debug('airport ' . $code . ' invalid - checked from cache');

                return false;
            }

            $success = false;

            $http2->GetURL('https://api.finnair.com/d/fcom/locations-prod/current/locationmatch?query=' . $code . '&locale=en&continentCode');
            $response_array = $http2->JsonLog(null, 0, true);

            if (!isset($response_array['ok'])) {
                $this->sendNotification("Location code search unknown error");

                return true;
            }

            if (($response_array['count'] ?? '') == 0) {
                $this->SetWarning('Invalid location code of ' . $type . ': ' . $code);
                \Cache::getInstance()->set('ra_finnair_airports_' . $code, 'invalid', 60 * 60 * 24);

                return false;
            }

            if (isset($response_array['items']) && is_array($response_array['items'])) {
                foreach ($response_array['items'] as $airport_data) {
                    if ($airport_data['locationCode'] == $code) {
                        $success = true;
                    }
                    \Cache::getInstance()->set('ra_finnair_airports_' . $code, 'available', 60 * 60 * 24);
                }
            }

            if (!$success) {
                $this->SetWarning('no flights ' . ($type == 'departure' ? 'from ' : 'to ') . $code);
                \Cache::getInstance()->set('ra_finnair_airports_' . $code, 'not_available', 60 * 60 * 24);

                return false;
            }
        }

        return true;
    }

    private function isBadProxy(): bool
    {
        return
            $this->http->Response['code'] == 403
            || strpos($this->http->Error, 'Network error 0 -') !== false
            || strpos($this->http->Error, 'Network error 52 - Empty reply from server') !== false
            || strpos($this->http->Error, 'Network error 56 - OpenSSL SSL_read: error') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 490 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Received HTTP code 502 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 7 - Failed to connect to') !== false
            || strpos($this->http->Error, 'Network error 56 - Recv failure: Connection reset by peer') !== false;
    }
}
