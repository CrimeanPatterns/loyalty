<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEraalaska extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use ProxyList;
    /**
     * @var CaptchaRecognizer
     */
    private $recognizer;

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        /*
        // todo: remove this gag
        $this->http->GetURL("https://ravnalaska.com/");
        if ($this->http->FindSingleNode('//form[@action = "indexsubmit"]//input[@value = "Notify at Full Relaunch"]/@value')) {
            throw new CheckException("{$this->AccountFields['DisplayName']} program is currently unavailable.", ACCOUNT_PROVIDER_ERROR);
        }
        $this->sendNotification("remove gag from parser");
        */

        $this->http->GetURL("https://www.flyravn.com/rewards/manage-your-account/");

        if (!$this->http->ParseForm(null, "//form[contains(@action, 'manage-your-account')]")) {
            return $this->checkErrors();
        }
        $this->http->SetInputValue('UserName', $this->AccountFields['Login']);
        $this->http->SetInputValue('Password', $this->AccountFields['Pass']);

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if (
            $this->http->FindPreg("/\[Error Message\] 52: Empty reply from server/")
            || $this->http->FindSingleNode('//p[contains(text(), "There has been a critical error on your website.")]')
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }
        // Your rewards number or password is incorrect.
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'Your rewards number or password is incorrect.')]")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Access is allowed
        if ($this->http->FindSingleNode("(//a[contains(@href, 'account-log-out')]/@href)[1]")) {
            return true;
        }
//        ## Invalid credentials
//        if ($message = $this->http->FindPreg("/(Invalid username\.)/ims"))
//            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
//        $this->SetProperty("Name", beautifulName($this->http->FindSingleNode("//td[label[contains(text(), 'Member Name:')]]/following-sibling::td[1]", null, true, "/[^<\(]+/ims")));
        $this->SetProperty("Name", beautifulName($this->http->FindPreg("/Name\:\s*<[^>]+>([^<]+)/ims")));
        // Account Number
//        $this->SetProperty("MemberAccount", $this->http->FindSingleNode("//td[label[contains(text(), 'Member Name:')]]/following-sibling::td[1]", null, true, "/\(([^<\)]+)/ims"));
        // Member #
        $this->SetProperty("MemberAccount", $this->http->FindPreg("/Member\s*\#:\s*([\d]+)/ims"));
        // Tier level
//        $this->SetProperty("TierLevel", $this->http->FindSingleNode("//td[label[contains(text(), 'Tier level:')]]/following-sibling::td[1]"));
        $this->SetProperty("TierLevel", $this->http->FindPreg("/Tier\s*Level\s*:\s*<[^>]+>([^<]+)/ims"));
        // Year to date flight segments
//        $this->SetProperty("YTDFlightSegments", $this->http->FindSingleNode("//td[label[contains(text(), 'Year to date flight segments:')]]/following-sibling::td[1]"));
        $this->SetProperty("YTDFlightSegments", $this->http->FindPreg("/Qualifying\s*Segments\s*:\s*<[^>]+>([^<]+)/ims"));

        // Balance - Points Balance
//        $this->SetBalance($this->http->FindSingleNode("//td[label[contains(text(), 'Points Balance:')]]/following-sibling::td[1]", null, true, "/[\d\,\.]+/ims"));
        $this->SetBalance($this->http->FindPreg("/Available\s*Points\s*:\s*<[^>]+>([^<]+)/ims"));
    }

    public function ParseItineraries()
    {
        return $this->parseItinerariesRavn();
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://booking.flyravn.com/SSW2010/8M77/myb.html";
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation Code",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Last Name",
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $startTimer = $this->getTime();
        $this->http->SetProxy($this->proxyReCaptcha());

        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->FindSingleNode('//div[@id = "login_2"]')) {
            $this->distil();
        }

        $data = [
            'reservationCode' => $arFields['ConfNo'],
            'lastName'        => $arFields['LastName'],
            'actionCode'      => 'retrieveBooking',
            'inOverlay'       => 'false',
            'brSubmit'        => 'Find Flight',
            'componentTypes'  => 'bookingretrieval',
        ];
        $this->http->PostURL('https://booking.flyravn.com/SSW2010/8M77/myb.html?d=abc', $data);

        // We are not able to locate the reservation you're looking for. Please check if you've provided the correct information and try again.
        if ($error = $this->http->FindSingleNode("//h2[contains(text(), 'Reservation not found')]")) {
            return $error;
        }

        $it = $this->parseItinerary($arFields['ConfNo']);

        $this->getTime($startTimer);

        return null;
    }

    protected function distil()
    {
        $this->logger->notice(__METHOD__);
        $referer = $this->http->currentUrl();
        $distilLink = $this->http->FindPreg("/meta http-equiv=\"refresh\" content=\"\d+;\s*url=([^\"]+)/ims");

        if (isset($distilLink)) {
            sleep(2);
            $this->http->NormalizeURL($distilLink);
            $this->http->RetryCount = 0;
            $this->http->FilterHTML = false;
            $this->http->GetURL($distilLink);
            $this->http->RetryCount = 2;
        }// if (isset($distil))

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;
        $captcha = $this->parseReCaptcha();

        if ($captcha === false) {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->FilterHTML = true;

        return true;
    }

    protected function parseReCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey");
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
        $captcha = $this->recognizeByRuCaptcha($this->recognizer, $key, $parameters);

        return $captcha;
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
            $selenium->useGoogleChrome();
            $selenium->disableImages();
            $selenium->useCache();

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://booking.flyravn.com/SSW2010/8M77/myb.html');
            // save page to logs
            $this->http->SetBody($selenium->driver->executeScript('return document.documentElement.innerHTML'));
            $this->http->SaveResponse();

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

    private function parseItinerariesRavn()
    {
        $this->logger->notice(__METHOD__);
        $result = [];
        $this->http->GetURL('https://booking.flyravn.com/SSW2010/8M77/myb.html');

        if ($this->http->FindSingleNode("//script[contains(@src,'/_Incapsula_Resource?SWJIYLWA=')]/@src")) {
            $this->selenium();
        }

        if (!$this->http->FindSingleNode('//div[@id = "login_2"]')) {
            $this->distil();
        }

        $data = [
            'username'       => $this->AccountFields['Login'],
            'password'       => $this->AccountFields['Pass'],
            'componentTypes' => 'login',
        ];
        $this->http->PostURL('https://booking.flyravn.com/SSW2010/8M77/myb.html?d=abc', $data);

        if ($itineraries = $this->http->FindPreg('/"reservations":\s*\[\],\s*"aerSystemAvailable"/')) {
            return $this->noItinerariesArr();
        }

        $itineraries = $this->http->JsonLog($this->http->FindPreg('/"reservations":\s*(\[.+?\]),\s*"aerSystemAvailable"/'), false);

        if (empty($itineraries)) {
            return $result;
        }

        if (count($itineraries) > 0) {
            $this->sendNotification('Check itineraries // MI');
        }
        $this->logger->debug("Found: " . count($itineraries) . " itineraries");

        foreach ($itineraries as $item) {
            if (isset($item->reservationNumber)) {
                $this->logger->info(sprintf('Parse Air #%s', $item->reservationNumber), ['Header' => 3]);
                $this->http->GetURL('https://booking.flyravn.com/SSW2010/8M77/mytrips.html?d=abc&viewReloc=' . $item->reservationNumber);

                if (isset($item)) {
                    $this->parseItinerary($item->reservationNumber);
                }
            }
        }

        return $result;
    }

    private function getSegmentsText()
    {
        $this->logger->notice(__METHOD__);
        $segmentsRe = '/"itineraryParts":\s*(\[.+?\]),\s*"passengers"/s';
        $res = $this->http->FindPreg($segmentsRe);
        $res = preg_replace('/"marketingText":"(.+?)"([,\]\}])/s', '"marketingText":""$2', $res);

        return $res;
    }

    private function parseItinerary($recordLocator)
    {
        $this->logger->notice(__METHOD__);
        $segments = $this->http->JsonLog($this->getSegmentsText(), false);

        if (!$segments) {
            $this->distil();
            $segments = $this->http->JsonLog($this->getSegmentsText(), false);
        }

        if (!isset($segments)) {
            $this->sendNotification('refs #16559 - eraalaska: check itinerary');

            return false;
        }

        $flight = $this->itinerariesMaster->createFlight();
        // RecordLocator
        $flight->addConfirmationNumber($recordLocator, null, true);
        // Passengers and AccountNumbers
        $travellers = [];
        $accountNumbers = [];
        $passengers = $this->http->JsonLog($this->http->FindPreg('/"passengers":\s*(\[.+?\]),"ancillariesBreakdowns"/s'), false);

        if (isset($passengers)) {
            foreach ($passengers as $passenger) {
                $travellers[] = beautifulName($passenger->firstName . ' ' . $passenger->lastName);

                if (isset($passenger->frequentFlyer[0]->number)) {
                    $accountNumbers[] = $passenger->frequentFlyer[0]->number;
                }
            }
        }
        $flight->setTravellers($travellers);
        $flight->setAccountNumbers($accountNumbers, false);

        foreach ($segments as $part) {
            foreach ($part->segments as $segment) {
                $seg = $flight->addSegment();
                $seg->airline()
                    ->name($segment->airlineCode)
                    ->number($segment->flightNumber);
                $operator = $segment->wetLessor;
                $operator = $this->http->FindPreg('/as\s+(.+)$/i', false, $operator) ?: $operator;
                $seg->setOperatedBy($operator, false, true);

                $seg->departure()
                    ->code($segment->origin->code)
                    ->date(strtotime($segment->departure, 0));
                $seg->arrival()
                    ->code($segment->destination->code)
                    ->date(strtotime($segment->arrival, 0));

                $seg->extra()
                    ->cabin(beautifulName($segment->bookingClass->cabinClass))
                    ->bookingCode($segment->bookingClass->bookingClass)
                    ->stops($segment->numberOfStops);

                if (!empty($segment->duration)) {
                    $m = $segment->duration / 1000 / 60;
                    $h = floor($m / 60);
                    $seg->setDuration("{$h}hr {$m}min");
                }
            }
        }

        $totalStr = $this->http->FindPreg('/"totalPrice":(.+?)"moneyElements"/');
        // TotalCharge and Currency
        if ($totalStr) {
            $total = $this->http->FindPreg('/"amount":"(.+?)"/', false, $totalStr);
            $flight->price()
                ->total(PriceHelper::cost($total), false, true)
                ->currency($this->http->FindPreg('/"code":"(.+?)"/', false, $totalStr), false, true);
        }
        // BaseFare
        $costStr = $this->http->FindPreg('/"flightsPrice":(.+?)"moneyElements"/');

        if ($costStr) {
            $cost = $this->http->FindPreg('/"amount":"(.+?)"/', false, $costStr);
            $flight->price()->cost(PriceHelper::cost($cost));
        }
        // Taxes
        $taxStr = $this->http->FindPreg('/"taxesPrice":(.+?)"moneyElements"/');

        if ($taxStr) {
            $total = $this->http->FindPreg('/"amount":"(.+?)"/', false, $taxStr);
            $flight->price()->tax(PriceHelper::cost($total));
        }

        $this->logger->debug('Parsed Itinerary:');
        $this->logger->debug(var_export($flight->toArray(), true), ['pre' => true]);

        return true;
    }
}
