<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerCheckmytrip extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $headers = [
        'Accept'       => 'application/json, text/plain, */*',
        'Content-Type' => 'application/x-www-form-urlencoded;charset=utf-8',
        'Origin'       => 'https://www.checkmytrip.com',
        'x-distil-ajax'=> 'uxyfdfqfvu',
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        //	    $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->setHttp2(true);
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['headers'])) {
            return false;
        }
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/account/getProfile?LANGUAGE=GB&SITE=NCMTNCMT", [], $this->State['headers']);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();

        if ($this->loginSuccessful($response)) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        // Login must be correct email
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Invalid e-mail and/or password', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        $this->http->RetryCount = 0;

        $this->selenium();

        $this->http->GetURL('https://www.checkmytrip.com/');

        if ($this->http->FindSingleNode('//form[@id="distilCaptchaForm"]') && !$this->parseGeetestCaptcha()) {
            return false;
        }

        if ($this->http->Response['code'] !== 200) {
            return false;
        }

        $data = [
            'userId'             => $this->AccountFields['Login'],
            'password'           => $this->AccountFields['Pass'],
        ];

        $this->http->RetryCount = 0;
        $this->http->PostURL('https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/account/loginWithEmail?LANGUAGE=GB&SITE=NCMTNCMT&OCTX=TCPROD', ['data' => json_encode($data)], $this->headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        if ($this->http->FindSingleNode('//h1[normalize-space()="Access To Website Blocked"]')) {
            throw new CheckRetryNeededException(3);
        }

        $response = $this->http->JsonLog();

        if ($this->loginSuccessful($response)) {
            $this->State['headers'] = [
                "Accept"        => "application/json, text/plain, */*",
                "Authorization" => "Bearer {$response->model->accessToken}",
            ];

            return true;
        }

        $message = $response->model->errorCodes[0] ?? null;

        if ($message) {
            $this->logger->error("[errorCode]: {$message}");

            if ($message == '2026322') {
                throw new CheckException('Authentication failed. Please check your credentials and try again.', ACCOUNT_INVALID_PASSWORD);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $result = $this->http->JsonLog(null, 0);

        if (empty($result->model->user->firstName) || empty($result->model->user->lastName)) {
            return;
        }
        // Name
        $this->SetProperty('Name', beautifulName($result->model->user->firstName . ' ' . $result->model->user->lastName));
        // Loyalty Cards
        $this->SetProperty('LoyaltyCards', $result->model->user->loyaltyCards);

        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }

        if (!empty($this->Properties['LoyaltyCards'])) {
            $this->sendNotification("refs #6802 LoyaltyCards were found");
        }
    }

    public function ParseItineraries()
    {
        $result = $this->http->JsonLog(null, 0);

        if (isset($this->State['headers']['Authorization'])) {
            $this->headers["Authorization"] = $this->State['headers']['Authorization'];
        }

        if (!empty($result->model->user->triplist->trips)) {
            $result = $result->model->user->triplist->trips;
        } else {
            $this->http->PostURL("https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/trips/triplistRefresh?LANGUAGE=GB&SITE=NCMTNCMT&OCTX=TCPROD",
                [
                    'data' => json_encode(['triplistTimeWindow' => 'Future']),
                ], $this->headers);
            $result = $this->http->JsonLog(null, 3);
            $result = $result->model->triplist->trips;
        }

        $noFuture = isset($result) && $this->http->FindPreg('/"triplist":\s*\{"trips":\[\]\}/');

        foreach ($result as $trip) {
            $this->http->PostURL('https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/trips/retrieveTrip?LANGUAGE=GB&SITE=NCMTNCMT&OCTX=TCPROD',
                [
                    'data' => json_encode([
                        'tripId'    => $trip->tripId,
                        'lastName'  => $trip->bookingLastName,
                        'firstName' => $trip->bookingFirstName,
                    ]),
                ], $this->headers);
            $this->parseItinerariesAll();
        }

        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);
            $this->http->PostURL("https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/trips/triplistRefresh?LANGUAGE=GB&SITE=NCMTNCMT&OCTX=TCPROD",
                [
                    'data' => json_encode(['triplistTimeWindow' => 'Past']),
                ], $this->headers);
            $result = $this->http->JsonLog(null, 3);
            $noPast = isset($result->model->triplist->trips) && $this->http->FindPreg('/"triplist":\s*\{"trips":\[\]\}/');

            foreach ($result->model->triplist->trips as $trip) {
                $this->http->PostURL('https://www.checkmytrip.com/cmt2/web/apf/mobile/v1/trips/retrieveTrip?LANGUAGE=GB&SITE=NCMTNCMT',
                    [
                        'data' => json_encode([
                            'tripId'       => $trip->tripId,
                            'lastName'     => $trip->bookingLastName,
                            'creationDate' => [
                                'utcTimestampMillis' => $trip->creationDate->utcTimestampMillis,
                                'offsetMinutes'      => 'null',
                                'hasTime'            => 'true',
                            ],
                        ]),
                    ], $this->headers + ['x-distil-ajax' => 'uxyfdfqfvu']);
                $this->parseItinerariesAll();
            }

            if ($noFuture && $noPast) {
                return $this->noItinerariesArr();
            }
        } elseif ($noFuture) {
            return $this->noItinerariesArr();
        }

        return [];
    }

    private function loginSuccessful($response)
    {
        $this->logger->notice(__METHOD__);

        if (isset($response->model->success) && $response->model->success == true) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        return false;
    }

    private function parseItinerariesAll()
    {
        $response = $this->http->JsonLog();

        if (empty($response->model->trip)) {
            return;
        }
        $airs = $hotels = $car = $transfer = $moveMisc = $train = $cruise = [];

        foreach ($response->model->trip->tripDetails->segments as $trip) {
            if ($trip->type == 'Air') {
                $airs[] = $trip;
            } elseif ($trip->type == 'Hotel') {
                $hotels[] = $trip;
            } elseif ($trip->type == 'Car') {
                $car[] = $trip;
            } elseif ($trip->type == 'Transfer') {
                $transfer[] = $trip;
            } elseif ($trip->type == 'MoveMisc') {
                $moveMisc[] = $trip;
            } elseif ($trip->type == 'Train') {
                $train[] = $trip;
            } elseif ($trip->type == 'Cruise') {
                $cruise[] = $trip;
            }
        }

        $confirmations = $response->model->trip->associatedRecords;
        $count = [];

        if (count($airs) > 0) {
            $count[] = "flights " . count($airs);
        }

        if (count($hotels) > 0) {
            $count[] = "hotels " . count($hotels);
        }

        if (count($car) > 0) {
            $count[] = "car " . count($car);
        }

        if (count($transfer) > 0) {
            $count[] = "transfer " . count($transfer);
            $this->sendNotification('Check transfer // MI');
        }

        if (count($moveMisc) > 0) {
            $count[] = "moveMisc " . count($moveMisc);

            if ($this->AccountFields['Login'] != 'veresch80@yahoo.com') {
                $this->sendNotification('Check moveMisc - bus? // MI');
            }
        }

        if (count($train) > 0) {
            $count[] = "train " . count($train);
        }

        if (count($cruise) > 0) {
            $count[] = "cruise " . count($cruise);
            $this->sendNotification('Check Cruise // MI');
        }

        $this->logger->info("Parse Itinerary #{$confirmations[0]} - " . join(', ', $count), ['Header' => 3]);

        if (!empty($airs)) {
            $this->parseItineraryAir($confirmations, $airs);
        }

        if (!empty($hotels)) {
            foreach ($hotels as $h) {
                $this->parseItineraryHotel($confirmations, $h);
            }
        }

        if (!empty($car)) {
            foreach ($car as $c) {
                $this->parseItineraryCar($confirmations, $c);
            }
        }

        if (!empty($moveMisc)) {
            foreach ($moveMisc as $m) {
                $this->parseItineraryMoveMisc($confirmations, $m);
            }
        }

        if (!empty($train)) {
            foreach ($train as $t) {
                $this->parseItineraryTrain($confirmations, $t);
            }
        }
    }

    private function parseItineraryDate($offsetMinutes, $utcTimestampMillis)
    {
        if (isset($offsetMinutes)) {
            return strtotime("{$offsetMinutes} minutes", ($utcTimestampMillis / 1000));
        } else {
            return $utcTimestampMillis / 1000;
        }
    }

    private function parseItineraryLocation($loc)
    {
        $address = [];

        if (!empty($loc->addressLine1)) {
            $address[] = $loc->addressLine1;
        }

        if (!empty($loc->addressLine2)) {
            $address[] = $loc->addressLine2;
        }

        if (!empty($loc->cityName)) {
            $address[] = $loc->cityName;
        } elseif (!empty($loc->name)) {
            $address[] = $loc->name;
        }

        if (!empty($loc->countryName)) {
            $address[] = $loc->countryName;
        }

        if (!empty($loc->zip)) {
            $address[] = $loc->zip;
        }

        return join(', ', $address);
    }

    private function parseItineraryTrain($confirmations, $t)
    {
        $this->logger->notice(__METHOD__);
        $train = $this->itinerariesMaster->createTrain();
        /*foreach ($confirmations as $conf) {
            $train->ota()->confirmation($conf);
        }*/
        if (isset($t->confirmationNumber)) {
            $train->general()->confirmation($t->confirmationNumber, 'Confirmation number');
        } elseif (!isset($t->confirmationNumber)) {
            $train->general()->noConfirmation();
        }
        $s = $train->addSegment();

        if (isset($t->trainNumber)) {
            $s->extra()->number($t->trainNumber);
        } else {
            $s->extra()->noNumber();
        }
        $s->departure()->date($this->parseItineraryDate($t->startDate->offsetMinutes, $t->startDate->utcTimestampMillis));
        $s->arrival()->date($this->parseItineraryDate($t->endDate->offsetMinutes, $t->endDate->utcTimestampMillis));

        $s->departure()->name($t->fromLocation->name);
        $s->arrival()->name($t->toLocation->name);
        $s->departure()->address($this->parseItineraryLocation($t->fromLocation));
        $s->arrival()->address($this->parseItineraryLocation($t->toLocation));

        $travellers = [];

        foreach ($t->travellers as $traveller) {
            $travellers[] = beautifulName("{$traveller->firstName} {$traveller->lastName}");
        }

        if (!empty($travellers)) {
            $train->general()->travellers(array_unique($travellers));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($train->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryMoveMisc($confirmations, $m)
    {
        /*if ($m->provider->name != 'Avis') {
            return;
        }*/
        $this->logger->notice(__METHOD__);
        $car = $this->itinerariesMaster->createRental();
        $car->extra()->company($m->provider->name);
        /*foreach ($confirmations as $conf) {
            $car->ota()->confirmation($conf);
        }*/
        if (isset($m->confirmationNumber)) {
            $car->general()->confirmation($m->confirmationNumber, 'Confirmation number');
        } elseif (!isset($m->confirmationNumber)) {
            $car->general()->noConfirmation();
        }
        $car->pickup()->date($this->parseItineraryDate($m->startDate->offsetMinutes, $m->startDate->utcTimestampMillis));
        $car->dropoff()->date($this->parseItineraryDate($m->endDate->offsetMinutes, $m->endDate->utcTimestampMillis));

        $car->pickup()->location($this->parseItineraryLocation($m->fromLocation));
        $car->dropoff()->location($this->parseItineraryLocation($m->toLocation));

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($car->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryCar($confirmations, $c)
    {
        $this->logger->notice(__METHOD__);
        $car = $this->itinerariesMaster->createRental();
        /*foreach ($confirmations as $conf) {
            $car->ota()->confirmation($conf);
        }*/
        if (isset($c->reservationNumber)) {
            $car->general()->confirmation($c->reservationNumber, 'Confirmation number');
        } elseif (!isset($c->reservationNumber)) {
            $car->general()->noConfirmation();
        }
        $car->pickup()->date($this->parseItineraryDate($c->pickupDateTime->offsetMinutes, $c->pickupDateTime->utcTimestampMillis));
        $car->dropoff()->date($this->parseItineraryDate($c->dropoffDateTime->offsetMinutes, $c->dropoffDateTime->utcTimestampMillis));

        $car->pickup()->location($this->parseItineraryLocation($c->pickupLocation));
        $car->dropoff()->location($this->parseItineraryLocation($c->dropoffLocation));

        $travellers = [];

        foreach ($c->travellers as $traveller) {
            $travellers[] = beautifulName("{$traveller->firstName} {$traveller->lastName}");
        }

        if (!empty($travellers)) {
            $car->general()->travellers(array_unique($travellers));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($car->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryHotel($confirmations, $h)
    {
        $this->logger->notice(__METHOD__);
        $hotel = $this->itinerariesMaster->createHotel();
        /*foreach ($confirmations as $conf) {
            $hotel->ota()->confirmation($conf);
        }*/
        if (isset($h->reservationNumber)) {
            $hotel->general()->confirmation($h->reservationNumber, 'Confirmation number');
        } elseif ($h->reservationNumber === null) {
            $hotel->general()->noConfirmation();
        }
        $hotel->hotel()->name($h->name);
        $hotel->hotel()->address($this->parseItineraryLocation($h->location));
        $hotel->hotel()->phone($h->phone, false, true);
        $hotel->general()->status($h->status);

        $hotel->booked()->checkIn($this->parseItineraryDate($h->checkin->offsetMinutes, $h->checkin->utcTimestampMillis));

        if ($h->checkout === null) {
            $hotel->booked()->noCheckOut();
        } else {
            $hotel->booked()->checkOut($this->parseItineraryDate($h->checkout->offsetMinutes, $h->checkout->utcTimestampMillis));
        }

        $hotel->booked()->guests($h->numberOfAdults, false, true);
        $travellers = [];

        foreach ($h->travellers as $traveller) {
            $travellers[] = beautifulName("{$traveller->firstName} {$traveller->lastName}");
        }

        if (!empty($travellers)) {
            $hotel->general()->travellers(array_unique($travellers));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($hotel->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryAir($confirmations, $airs)
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $flight->general()->noConfirmation();
        /*foreach ($confirmations as $conf) {
            $flight->ota()->confirmation($conf);
        }*/
        $travellers = $tickets = $accounts = [];

        foreach ($airs as $air) {
            if (isset($air->fromLocation->code) && $air->fromLocation->code == $air->toLocation->code) {
                $this->logger->error('Codes are identical. Skip segment');

                continue;
            }
            $seg = $flight->addSegment();

            if (isset($air->confirmationNumber) && $air->confirmationNumber != 'INFO') {
                $seg->setConfirmation($air->confirmationNumber);
            }
            $seg->departure()->date($this->parseItineraryDate($air->startDate->offsetMinutes, $air->startDate->utcTimestampMillis));
            $seg->arrival()->date($this->parseItineraryDate($air->endDate->offsetMinutes, $air->endDate->utcTimestampMillis));

            $seg->airline()->name($air->flight->airlineCode);
            $seg->airline()->number($air->flight->flightNumber);

            if (isset($air->flight->aircraft->name)) {
                $seg->extra()->aircraft($air->flight->aircraft->name);
            }

            if (isset($air->fromLocation->cityName, $air->fromLocation->name)) {
                $seg->departure()->name("{$air->fromLocation->cityName}, {$air->fromLocation->name}");
            }
            $seg->departure()->code($air->fromLocation->code);

            if (isset($air->toLocation->cityName, $air->toLocation->name)) {
                $seg->arrival()->name("{$air->toLocation->cityName}, {$air->fromLocation->name}");
            }
            $seg->arrival()->code($air->toLocation->code);

            $seg->departure()->terminal($air->departureTerminal, false, true);
            $seg->arrival()->terminal($air->arrivalTerminal, false, true);

            if (!empty($air->bookingClass)) {
                $seg->extra()->cabin($air->bookingClass->name, false, true);
                $seg->extra()->bookingCode($air->bookingClass->code);
            }

            if (!empty($air->meals)) {
                $seg->extra()->meal(join(', ', array_map('beautifulName', array_column($air->meals, 'description'))));
            }
            $seg->extra()->status($air->status);

            $hours = floor($air->durationMinutes / 60);
            $minutes = ($air->durationMinutes % 60);
            $seg->extra()->duration(sprintf('%02dh %02dm', $hours, $minutes));

            $seats = [];

            foreach ($air->travellers as $traveller) {
                $travellers[] = beautifulName("{$traveller->firstName} {$traveller->lastName}");

                if (!empty($traveller->seat)) {
                    $seats[] = $traveller->seat;
                }

                if (!empty($traveller->ticket->number)) {
                    $tickets[] = $traveller->ticket->number;
                }

                if (!empty($traveller->ticket->frequentFlyer)) {
                    $accounts[] = $traveller->ticket->frequentFlyer;
                    $this->sendNotification('refs#6802, Check frequentFlyer // MI');
                }
            }
        }

        if (!empty($travellers)) {
            $flight->general()->travellers(array_unique($travellers));
        }
        $flight->issued()->tickets(array_unique($tickets), false);
        $flight->ota()->accounts(array_unique($accounts), false);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function parseGeetestCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $gt = $this->http->FindPreg("/gt:\s*'(.+?)'/");
        $apiServer = $this->http->FindPreg("/api_server:\s*'(.+?)'/");
        $ticket = $this->http->FindSingleNode('//input[@name = "dCF_ticket"]/@value');

        if (!$gt || !$apiServer || !$ticket) {
            $this->logger->notice('Not a geetest captcha');

            return false;
        }

        // watchdog workaround
        $this->increaseTimeLimit(180);

        /** @var HTTPBrowser $http2 */
        $http2 = clone $this->http;
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
        $request = $this->http->JsonLog($captcha, 3, true);

        if (empty($request)) {
            $this->logger->info('Retrying parsing geetest captcha');
            $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters);
            $request = $this->http->JsonLog($captcha, 3, true);
        }

        if (empty($request)) {
            $this->logger->error("geetest failed = true");

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $request['geetest_challenge'],
            'geetest_validate'  => $request['geetest_validate'],
            'geetest_seccode'   => $request['geetest_seccode'],
            //            'isAjax'            => "1",
        ];
        $this->http->PostURL($verifyUrl, $payload);

//        if ($this->http->Response['code'] == 204) {
//            $this->http->GetURL('https://www.checkmytrip.com/');
//        }

        return true;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $retry = false;
        $result = false;
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
            $selenium->useFirefox(\SeleniumFinderRequest::FIREFOX_59);
            $selenium->disableImages();
            $selenium->useCache();
            $selenium->setProxyGoProxies();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.checkmytrip.com/');
            $btn = $selenium->waitForElement(WebDriverBy::id("login-submit"), 7);
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

            if (!$btn) {
                $this->logger->error("something went wrong");
//                return false;
            }

            $result = true;
            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();
        } catch (TimeOutException $e) {
            $this->logger->error("TimeOutException exception: " . $e->getMessage());
            $this->DebugInfo = "TimeOutException";
            // retries
            if (strstr($e->getMessage(), 'timeout')) {
                $retry = true;
            }
        }// catch (TimeOutException $e)
        finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup();

            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 5);
            }
        }

        return $result;
    }
}
