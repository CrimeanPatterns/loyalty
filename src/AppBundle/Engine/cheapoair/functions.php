<?php

use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCheapoair extends TAccountChecker
{
    use PriceTools;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->http->TimeLimit = 500;
        $this->http->SetProxy($this->proxyReCaptcha());
        //$this->http->setRandomUserAgent();
    }

//    function IsLoggedIn() {
//        $this->http->GetURL("https://www.cheapoair.com/profiles/api/v2/Traveler");
//        $response = $this->http->JsonLog(null, true, true);
//        $name = CleanXMLValue(ArrayVal($response, 'FirstName')." ".ArrayVal($response, 'MiddleName')." ".ArrayVal($response, 'LastName'));
//        if (!empty($name))
//            return true;
//
//        return false;
//    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.cheapoair.com/", [
            'Cache-Control' => 'max-age=0',
            'Connection'    => 'keep-alive',
            'Host'          => 'www.cheapoair.com',
        ]);
//        if (!$this->http->ParseForm("formTag"))
        if ($this->http->Response['code'] != 200) {
            return $this->checkErrors();
        }
//        $this->http->SetInputValue('txtUserName', $this->AccountFields['Login']);
//        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);

        $captcha = $this->parseCaptcha();

        if ($captcha === false) {
            return false;
        }
        // set data
        $data = [
            "EmailExpressOtherInfo" => [
                "PageComponent"        => "Air-Profile-Email-SignIn",
                "PageCategory"         => "HP-dweb-SM-Air",
                "PageReferrer"         => "https://www.cheapoair.com/",
                "ApplicationOwnerName" => "cheapoair.com",
                "FPAffiliate"          => "",
                "AffiliateSubCode"     => "",
            ],
            "IsRememberMe"     => true,
            "LoginAccepted"    => false,
            "LoginFailed"      => false,
            "PageReferrer"     => 'https://www.cheapoair.com/',
            "UserName"         => $this->AccountFields['Login'],
            "Password"         => $this->AccountFields['Pass'],
            "RecaptChallenge"  => "",
            "RecaptResponse"   => $captcha,
            "SignupSource"     => "Home.UserProfileBox1.SignIn.CRM",
            "userRememberMe"   => false,
        ];
        // set headers
        $this->http->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
        $this->http->setDefaultHeader("Content-Type", "application/json");
        $this->http->setDefaultHeader("Accept", "application/json, text/javascript, */*; q=0.01");
        $this->http->setDefaultHeader("X-AuthType", "1");
        $this->http->setDefaultHeader("X-DomainId", "www.cheapoair.com");
//        $this->http->setDefaultHeader("X-EmailId", "null");
//        $this->http->setDefaultHeader("X-EncEmailId", "null");
//        $this->http->setDefaultHeader("X-EncUserId", "null");
        // post data
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cheapoair.com/profiles/publicapi/v5/Authentication/RecaptchaSignIn", json_encode($data));
        $this->http->RetryCount = 2;

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode("//h1[contains(text(), '502 Bad Gateway')]")) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode("//p[contains(., 'Our site is currently experiencing outage.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->PersonGuid, $response->SessionToken)) {
            // Reset Password
            if ($response->PersonGuid == "" && $response->SessionToken == "") {
                $this->throwProfileUpdateMessageException();
            } else {
                $this->http->setDefaultHeader("X-SessionToken", $response->SessionToken);
                $this->http->setDefaultHeader("X-AuthType", "1");
                $this->http->setDefaultHeader("X-DomainId", "www.cheapoair.com");

                return true;
            }
        }

        if (isset($response->Message)) {
            switch ($response->Message) {
                case 'The username or password you entered is incorrect.':
                    throw new CheckException($response->Message, ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'Invalid email address.':
                    throw new CheckException($response->Message, ACCOUNT_INVALID_PASSWORD);

                    break;

                case 'Your account has been locked. Click here to reset your password.':
                    throw new CheckException($response->Message, ACCOUNT_LOCKOUT);

                    break;

                case 'You previously logged into your Cheapoair account using Facebook or Google. Please try to login that way again.':
                    throw new CheckException('Sorry, login via Facebook or Google is not supported', ACCOUNT_PROVIDER_ERROR);

                case 'You previously logged into your Cheapoair account using Google+. Please try to login that way again.':
                    throw new CheckException('Sorry, login via Google is not supported', ACCOUNT_PROVIDER_ERROR);

                case 'You previously logged into your Cheapoair account using Facebook. Please try to login that way again.':
                    throw new CheckException('Sorry, login via Facebook is not supported', ACCOUNT_PROVIDER_ERROR);

                case 'Sorry, the service is under maintenance':
                    throw new CheckException($response->Message, ACCOUNT_PROVIDER_ERROR);

                    break;

                case 'Captacha not valid.':
                    throw new CheckRetryNeededException(3, 1, self::CAPTCHA_ERROR_MSG);

                    break;

                case 'Your email or password was not found.':
                    break;

                case 'Execution Timeout Expired.  The timeout period elapsed prior to completion of the operation or the server is not responding.':
                case 'Some error occurred while processing this request.':
                    throw new CheckException("Server error, please try again later.", ACCOUNT_PROVIDER_ERROR);

                    break;
            }// switch ($response->Message)
        }// if ($response->Message)
        // Invalid email address
        if ($message = $this->http->FindPreg("/\{\"signInInfo\.UserName\":\[\"(Invalid email address\.)\"\]\}/")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Your email or password was not found. Let's get you back on track!
        if ($message = $this->http->FindPreg("/\{\"signInInfo\.Password\":\[\"(Invalid character\(s\) in password\.)\"\]\}/")) {
            throw new CheckException("Your email or password was not found. Let's get you back on track!", ACCOUNT_INVALID_PASSWORD);
        }

        return false;
    }

    public function Parse()
    {
        $this->http->GetURL("https://www.cheapoair.com/profiles/api/v2/Traveler");
        $response = $this->http->JsonLog(null, 3, true);
        // Name
        $name = Html::cleanXMLValue(ArrayVal($response, 'FirstName') . " " . ArrayVal($response, 'MiddleName') . " " . ArrayVal($response, 'LastName'));
        $this->SetProperty("Name", beautifulName($name));

        if (ArrayVal($response, 'Message') == 'Session Expired.') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->http->GetURL("https://www.cheapoair.com/profiles/api/v1/Loyalty");
        $response = $this->http->JsonLog(null, 3, true);
        // Balance - available points
        $this->SetBalance(ArrayVal($response, 'ActivePoints'));
        // pending points
        $this->SetProperty("PendingPoints", ArrayVal($response, 'PendingPoints'));
        // Points Until Reward
        $this->SetProperty("PointsUntilReward", ArrayVal($response, 'PointsUntilReward'));

        $this->http->GetURL('https://www.cheapoair.com/clubmiles/api/v1/Loyalty/RewardsSummaryDetails');
        $response = $this->http->JsonLog();
        // Bronze Member
        $this->SetProperty('Status', $response->CurrentTierStatus->CurrentTier ?? null);

        if ($exp = strtotime($response->CurrentTierStatus->TierExpiryDate ?? '')) {
            $this->SetProperty('StatusExpirationDate', date('d M Y', $exp));
        }

        if ($this->Balance > 0
            && is_numeric($response->PointSummary->DomainWiseTotalPoints ?? null)
            && $exp = strtotime($response->PointSummary->ExpiredOn ?? '')
        ) {
            $this->SetExpirationDate($exp);
            $this->SetProperty('ExpiringBalance', $response->PointSummary->DomainWiseTotalPoints);
        }

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && $this->http->Response['code'] == 204) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $result = [];
        $headers = [
            'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'       => 'https://www.cheapoair.com',
            'Referer'      => 'https://www.cheapoair.com/profiles/',
            'request-id'   => '|jgylI.TF64m',
        ];
        $data = [
            'Type'       => 0,
            'PageSize'   => 4,
            'pageNumber' => 1,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://www.cheapoair.com/clubmiles/api/v1/trips/upcoming", $data, $headers);
        $this->http->RetryCount = 0;

        $response = $this->http->JsonLog(null, 2);
        // no Itineraries
        if (empty($response) && $this->http->Response['code'] == 204) {
            return $this->noItinerariesArr();
        }

        if (empty($response->Trips)) {
            $this->sendNotification('not it // MI');

            return [];
        }

        foreach ($response->Trips as $trip) {
            //$this->http->GetURL("https://www.cheapoair.com/confirmation?guid={$itinerary->TransactionGUID}");
            $this->detectItinerary($trip);
        }

        return $result;
    }

    protected function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = '6LfBWIYUAAAAAH-QFfjd8DMfNxGkONqMbmMTpf5W'; // hard code
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }

        $this->recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $this->recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);
    }

    private function detectItinerary($trip)
    {
        $this->logger->notice(__METHOD__);

        if (!empty($trip->AirlineName)) {
            $this->parseFlight($trip);
        } elseif (!empty($trip->HotelName)) {
            $this->parseHotel($trip);
        } elseif (!empty($trip->CarCompanyName)) {
            $this->parseCar($trip);
        } else {
            $this->sendNotification('reservation type not defined // MI');
        }
    }

    private function parseCar($trip)
    {
        $this->logger->notice(__METHOD__);
        $c = $this->itinerariesMaster->createRental();
        $c->ota()->confirmation($trip->TransactionId);
        $this->logger->info(sprintf('[%s] Parse Car #%s', $this->currentItin++, $trip->TransactionId),
            ['Header' => 3]);
        $c->general()->confirmation($trip->PNR);
        // 2023-06-17T23:43:42.593
        $c->general()->date2($trip->BookedOn);
        $c->pickup()->date2($trip->FromDateTime);
        $c->dropoff()->date2($trip->ToDateTime);
        $c->pickup()->location($trip->FromCity);
        $c->dropoff()->location($trip->ToCity);
        $c->extra()->company($trip->CarCompanyName);

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($c->toArray(), true), ['pre' => true]);
    }

    private function parseHotel($trip)
    {
        $this->logger->notice(__METHOD__);
        $h = $this->itinerariesMaster->createHotel();
        $h->ota()->confirmation($trip->TransactionId);
        $this->logger->info(sprintf('[%s] Parse Hotel #%s', $this->currentItin++, $trip->TransactionId),
            ['Header' => 3]);
        $h->general()->confirmation($trip->PNR);
        // 2023-06-17T23:43:42.593
        $h->general()->date2($trip->BookedOn);
        $h->hotel()->name($trip->HotelName);
        $h->hotel()->address($trip->ToCity);
        $h->booked()->checkIn2($trip->FromDateTime);
        $h->booked()->checkOut2($trip->ToDateTime);
        $h->booked()->guests($trip->NumberofGuests);
        $h->booked()->rooms($trip->NumberofRooms);
        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    private function parseFlight($trip)
    {
        $this->logger->notice(__METHOD__);
        $f = $this->itinerariesMaster->createFlight();
        $f->ota()->confirmation($trip->TransactionId);
        $this->logger->info(sprintf('[%s] Parse Flight #%s', $this->currentItin++, $trip->TransactionId),
            ['Header' => 3]);

        if (is_null($trip->PNR)) {
            $f->general()->noConfirmation();
        } else {
            $f->general()->confirmation($trip->PNR);
        }
        // 2023-06-17T23:43:42.593
        $f->general()->date2($trip->BookedOn);

        foreach ($trip->JourneyDetails as $segment) {
            $s = $f->addSegment();
            $s->airline()->name($segment->AirlineCode);
            $s->airline()->number($segment->FlightNumber);
            $s->departure()->code($segment->FromCityCode);
            $s->departure()->name($segment->FromCityName);
            $s->departure()->date2($segment->FromDateTime);
            $s->arrival()->code($segment->ToCityCode);
            $s->arrival()->name($segment->ToCityName);
            $s->arrival()->date2($segment->ToDateTime);

            $s->extra()->stops($segment->Stops);
            $s->extra()->bookingCode($segment->FlightClassName);
            // 03.46 -> 03h 46m
            $s->extra()->duration(preg_replace('/(\d+)\.(\d+)/', '$1h $2m', $segment->FlightDuration));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($f->toArray(), true), ['pre' => true]);
    }
}
