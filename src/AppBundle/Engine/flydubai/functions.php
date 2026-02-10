<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerFlydubai extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $airports = [];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->airports = $this->getAirports();
        // if (ArrayVal($this->AccountFields, 'Login')) {
        $this->UseSelenium();
        $this->useGoogleChrome();
        $this->useCache();
        $this->disableImages();
        // $this->http->saveScreenshots = true;
        $this->increaseTimeLimit();
        //$this->keepCookies(false);
        //$this->http->SetProxy($this->proxyReCaptcha());
        $this->KeepState = true;
        // }
    }

    public function IsLoggedIn()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://openrewards.flydubai.com/en/');

        if ($this->http->FindNodes('//a[contains(@href, "logout")]')) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        //$this->http->removeCookies();
        //$this->http->GetURL('https://www.flydubai.com/en/');
        $this->http->GetURL('https://openrewards.flydubai.com/en/login');

        $login = $this->waitForElement(WebDriverBy::id('Username'), 10);
        $pass = $this->waitForElement(WebDriverBy::id('Password'), 0);
        $button = $this->waitForElement(WebDriverBy::xpath("//button[normalize-space(text())='Log in']"), 0);

        if (!$login || !$pass || !$button) {
            $this->logger->error("something went wrong");
            $this->saveResponse();

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password = $this->AccountFields['Pass'];
        $pass->sendKeys($password);
        $this->saveResponse();
        $button = $this->waitForElement(WebDriverBy::xpath("//button[normalize-space(text())='Log in']"), 0);

        if (!$button) {
            $this->logger->error("something went wrong");

            return $this->checkErrors();
        }
        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();
        // The site database appears to be down
        //if ($message = $this->http->FindPreg("/The site database appears to be down\./"))
        //    throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);

        return false;
    }

    public function Login()
    {
        if ($this->waitForElement(WebDriverBy::xpath("//a[contains(@href,'/en/comp/account/logout')]"), 20, false)) {
            return true;
        }

        //# Login Incorrect
        if ($message = $this->waitForElement(WebDriverBy::xpath("//div[@id='InvalidLoginDiv']/span"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->saveResponse();
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode("//li[contains(@class, 'ffp-dashboard-account')]/h4")));
        // Number
        $this->SetProperty('Number', $this->http->FindSingleNode("//div/span[@class='ffp-db--code']"));
        // Balance - Reward points
        $this->SetBalance($this->http->FindSingleNode("//div/span[text()='Reward points']/preceding-sibling::p"));
        // Tier points
        $this->SetProperty('TierPoints', $this->http->FindSingleNode("//div/span[text()='Tier points']/preceding-sibling::p"));
        // Status
        $this->SetProperty('Status', $this->http->FindSingleNode("//div[@class='range-slider']/p[@class='range-slider--title']/text()[1]"));
        // Earn 6,000 tier points to achieve Gold status.
        $this->SetProperty('PointsNextLevel', $this->http->FindSingleNode("//span[contains(text(), 'tier points to achieve')]", null, false, '/Earn ([,.\d]+) tier/'));

        // Status Expiration Date - Gold member Valid through 20 Aug 2018
        $this->SetProperty('StatusExpirationDate', $this->http->FindSingleNode("//div[@class='range-slider']/p[@class='range-slider--title']/span[@class='valid']", null, false, '/Valid through\s+(\d+ \w+ \d{4})/'));

        // will be closed 31 Jul 2018
        $this->SetExpirationDate(strtotime("31 Jul 2018"));
    }

    public function ParseItineraries()
    {
        $res = [];
        $itinSelector = '//a[contains(@href, "https://flights.flydubai.com/en/booking/viewbooking/")]';

        $this->http->GetURL('https://openrewards.flydubai.com/en/my-booking/my-bookings');
        $this->waitForElement(WebDriverBy::xpath($itinSelector), 5);
        $links = $this->driver->findElements(WebDriverBy::xpath($itinSelector));

        $confSet = [];

        for ($i = 0; $i < count($links); $i++) {
            $this->http->GetURL('https://openrewards.flydubai.com/en/my-booking/my-bookings');
            $this->waitForElement(WebDriverBy::xpath($itinSelector), 10);
            $this->saveResponse();

            $links = $this->driver->findElements(WebDriverBy::xpath($itinSelector));
            $link = ArrayVal($links, $i);

            if (!$link) {
                $this->sendNotification('refs #12258, flydubai - check itineraries');

                continue;
            }

            $text = $link->getText();
            $depCode = $this->http->FindPreg('/\b([A-Z]{3})\b/', false, $text);
            $arrCode = $this->http->FindPreg('/\b[A-Z]{3}\b\s+\b([A-Z]{3})\b/', false, $text);
            $conf = $this->http->FindPreg('/^([A-Z\d]+)\s+-/', false, $text);

            $this->logger->info(sprintf('outside conf = %s', $conf));

            if (ArrayVal($confSet, $conf)) {
                $this->logger->info('Skipping duplicate itinerary');

                continue;
            }

            $link->click();
            $confSelector = '//h2[contains(text(), "Booking reference:")]/following::h3[1]';
            $this->waitForElement(WebDriverBy::xpath($confSelector), 10);
            $this->saveResponse();
            $this->br = $this->http;
            $itin = $this->parseItinerary();

            $res[] = $itin;
            $confSet[$itin['RecordLocator']] = true;
        }

        return $res;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation #",
                "Type"     => "string",
                "Size"     => 6,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 20,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flydubai.com/en/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $http2 = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($http2);
        $httpSaved = $this->http;
        $this->http = $http2;

        $error = $this->checkBooking($arFields);

        if ($error) {
            return $error;
        }
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm('manage-a-booking-form')) {
            $this->notify('flydubai', $arFields);

            return null;
        }
        $this->http->SetInputValue('bookingReference', $arFields['ConfNo']);
        $this->http->SetInputValue('lastName', $arFields['LastName']);
        $this->http->SetInputValue('checkbox-authorised', 'on');
        $this->http->PostForm();

        $it = $this->parseItinerary();

        if (!ArrayVal($it, 'RecordLocator')) {
            $this->notify('flydubai', $arFields);
        }

        $this->http = $httpSaved;

        return null;
    }

    private function xpathQuery($query, $parent = null)
    {
        $this->logger->notice(__METHOD__);
        $res = $this->http->XPath->query($query, $parent);
        $this->logger->info(sprintf('found %s nodes: %s', $res->length, $query));

        return $res;
    }

    private function checkBooking($arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL(sprintf('https://www.flydubai.com/en/Form/ManageBookingCredentials?lastname=%s&referenceNumber=%s',
                $arFields['LastName'],
                $arFields['ConfNo']
        ));

        if ($msg = $this->http->FindPreg('/Sorry, the booking cannot be found/')) {
            return $msg;
        }

        return null;
    }

    private function notify($provider, $arFields)
    {
        $this->logger->notice(__METHOD__);
        $this->sendNotification("{$provider} - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Last Name: {$arFields['LastName']}");

        return null;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);

        $res = ['Kind' => 'T'];
        // RecordLocator
        $confSelector = '//h2[contains(text(), "Booking reference:")]/following::h3[1]';
        $conf = $this->http->FindSingleNode($confSelector, null, true, '/^\s*([A-Z\d]+)\b/');
        $this->logger->info(sprintf('Parse Itinerary #%s', $conf), ['Header' => 3]);
        $res['RecordLocator'] = $conf;
        // Total
        $total = $this->http->FindPreg('/Journey total: ([\w\s,.]+)/');
        $total = preg_replace('/,/', '', $total);
        $res['TotalCharge'] = $this->http->FindPreg('/(\d[\d.]+)/', false, $total);
        // Currency
        $res['Currency'] = $this->http->FindPreg('/^\s*([A-Z]{3})/', false, $total);
        // Passengers
        $passengers = $this->http->FindNodes('//span[contains(@id, "PAXname")]');
        $res['Passengers'] = array_values(array_unique($passengers));
        // TripSegments
        $res['TripSegments'] = [];
        $journeys = $this->xpathQuery('//div[contains(@class, "itinerary")]');

        foreach ($journeys as $journey) {
            $ts = [];
            $seg = $this->xpathQuery('.//th[contains(text(), "Flight no.")]/ancestor::tr[1]/following-sibling::tr[1]', $journey);

            if ($seg) {
                $seg = $seg->item(0);
            } else {
                $this->logger->error('Invalid segment xpath');

                continue;
            }
            // FlightNumber
            $ts['FlightNumber'] = $this->http->FindSingleNode('./td[1]', $seg, true, '/[A-Z]{2}-(\d+)/');
            // AirlineName
            $ts['AirlineName'] = $this->http->FindSingleNode('./td[1]', $seg, true, '/([A-Z]{2})-\d+/');
            // DepName
            $depInfo = $this->http->FindSingleNode('./td[2]', $seg);
            $ts['DepName'] = trim($this->http->FindPreg('/\s*(.+?)\s*\d+:\d+/', false, $depInfo));
            // DepCode
            $ts['DepCode'] = ArrayVal($this->airports, $ts['DepName']) ?: TRIP_CODE_UNKNOWN;

            if ($ts['DepName'] === TRIP_CODE_UNKNOWN) {
                $this->sendNotification("flydubai - refs #16838. TRIP_CODE_UNKNOWN");
            }
            // DepDate
            $time1 = $this->http->FindPreg('/(\d+:\d+)/', false, $depInfo);
            $date1 = $this->http->FindPreg('/(\d+\s+\w+\s+\d{4})/', false, $depInfo);
            $dt1 = strtotime($time1, strtotime($date1));
            $ts['DepDate'] = $dt1;
            // ArrName
            $arrInfo = $this->http->FindSingleNode('./td[3]', $seg);
            $ts['ArrName'] = trim($this->http->FindPreg('/\s*(.+?)\s*\d+:\d+/', false, $arrInfo));
            // ArrCode
            $ts['ArrCode'] = ArrayVal($this->airports, $ts['ArrName']) ?: TRIP_CODE_UNKNOWN;
            // ArrDate
            $time2 = $this->http->FindPreg('/(\d+:\d+)/', false, $arrInfo);
            $date2 = $this->http->FindPreg('/(\d+\s+\w+\s+\d{4})/', false, $arrInfo);
            $dt2 = strtotime($time2, strtotime($date2));
            $ts['ArrDate'] = $dt2;
            // Duration
            $ts['Duration'] = trim($this->http->FindSingleNode('./td[4]', $seg));
            // Cabin
            $ts['Cabin'] = $this->http->FindSingleNode('./td[1]/p[3]', $seg);
            $res['TripSegments'][] = $ts;
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($res, true), ['pre' => true]);

        return $res;
    }

    private function getAirports()
    {
        $this->logger->notice(__METHOD__);
        $airports = \Cache::getInstance()->get('flydubai_airports');

        if (!$airports) {
            $airports = $this->parseAirports();

            if ($airports) {
                \Cache::getInstance()->set('flydubai_airports', $airports, 3600 * 24);
            }
        }

        return $airports;
    }

    private function parseAirports()
    {
        $this->logger->notice(__METHOD__);
        $this->http->GetURL('https://www.flydubai.com/en/Form/GetAirportJs');

        $text = $this->http->FindPreg('/var airports = (.+)/s');
        $data = $this->http->JsonLog($text, null, true);
        $airports = [];

        foreach ($data as $datum) {
            $name = ArrayVal($datum, 'airportTitle');
            $code = ArrayVal($datum, 'airportCode');

            if ($name && $code) {
                $airports[$name] = $code;
            }
        }

        return $airports;
    }
}
