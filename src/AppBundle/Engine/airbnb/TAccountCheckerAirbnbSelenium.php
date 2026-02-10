<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Schema\Parser\Common\Event;

class TAccountCheckerAirbnbSelenium extends TAccountChecker
{
    use SeleniumCheckerHelper;
    use PriceTools;
    use ProxyList;

    /**
     * @var HttpBrowser
     */
    public $browser;

    private $host = 'www.airbnb.com';
    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->UseSelenium();

        $this->setProxyMount();

        $this->useChromePuppeteer();
        $this->seleniumOptions->fingerprintOptions = ['devices' => ['desktop'], 'operatingSystems' => ['linux']];
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
        $this->KeepState = true;

        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        // TODO: impersonate
        /* $this->http->GetURL("https://www.airbnb.com/dashboard");
         sleep(3);
         $this->driver->manage()->addCookie(['name' => '_aat', 'value' => '0%7CMwLL%2Bo%2F4BS1VlWDlkDCMnwfXWFoe0N%2FIYK3aO71YqDi7OoG4267U1v3CEgMJW6P6', 'domain' => ".airbnb.com"]);

         sleep(2);*/
        $this->http->GetURL("https://www.airbnb.com/dashboard");
        $profile = $this->waitForElement(WebDriverBy::xpath("//a[contains(@href,'/users/show/')]/img"), 10);

        if ($profile) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        if (!$this->http->FindPreg('#/login\?#', false, $this->http->currentUrl())) {
            $this->http->GetURL('https://www.airbnb.com/dashboard');
        }

        /*$navMenu = $this->waitForElement(\WebDriverBy::xpath('//button[contains(@aria-label,"Main navigation menu")]'),
            15);

        if (!$navMenu) {
            return false;
        }
        $navMenu->click();
        $navMenu = $this->waitForElement(\WebDriverBy::xpath('//div[@id="simple-header-profile-menu"]//*[contains(text(),"Log in")]'),
            3);
        !if ($navMenu) {
        return false;
        }
         $navMenu->click();
*/

        $cookie = $this->waitForElement(WebDriverBy::xpath("//div[contains(@data-testid, 'main-cookies-banner-container')]//button[contains(text(),'OK')]"), 5);

        if ($cookie) {
            $cookie->click();
        }
        $this->saveResponse();
        $navMenu = $this->waitForElement(WebDriverBy::xpath('//button[contains(@aria-label,"ontinue with email")]'), 1);

        if ($navMenu) {
            $navMenu->click();
        }
        $this->saveResponse();
        $login = $this->waitForElement(WebDriverBy::id('email-login-email'), 5);

        if (!$login) {
            return false;
        }
        $modalArkose = $this->waitForElement(WebDriverBy::xpath("//div[@id='arkose-modal-container-id']//button[@aria-label='Close']"),
            5);

        if ($modalArkose) {
            $modalArkose->click();
        }

        //$login->sendKeys($this->AccountFields['Login']);

        $this->driver->executeScript("function triggerInput(selector, enteredValue) {
            let input = document.querySelector(selector);
            input.dispatchEvent(new Event('focus'));
            input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
            let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
            nativeInputValueSetter.call(input, enteredValue);
            let inputEvent = new Event('input', { bubbles: true });
            input.dispatchEvent(inputEvent);
        }
        triggerInput('input[name = \"user[email]\"]', '{$this->AccountFields['Login']}');");

        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@data-testid,"signup-login-submit-btn")]'), 5);

        if (!$button) {
            return false;
        }
        sleep(rand(1, 2));
        $button->click();
        sleep(3);
        $this->saveResponse();
        $signUp = $this->waitForElement(WebDriverBy::xpath("//h2/div[contains(text(),'Finish signing up')]"), 6);

        if ($signUp) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        //return;
        $pass = $this->waitForElement(WebDriverBy::xpath("//input[@data-testid = 'email-signup-password']"), 5);
        $this->logger->debug("set pass");

        if (!$pass) {
            $this->saveResponse();
            // Continue with Google
            if ($this->waitForElement(WebDriverBy::xpath("//header[contains(.,'Welcome back,')]//ancestor::div[1]//div[contains(text(),'Continue with ')]"), 0)) {
                throw new CheckException('Sorry, login via Google is not supported', ACCOUNT_PROVIDER_ERROR);
            }

            if ($message = $this->waitForElement(WebDriverBy::xpath("
                    //div[
                        contains(text(), 'You’ve had too many password reset attempts.')
                        or contains(text(), 'Enter a valid email')
                        or contains(text(), 'You haven’t set a password yet. We just sent')
                        or contains(text(), 'Invalid email.')
                        or contains(text(), 'This looks like a business email address that')
                    ]
                "), 0)
            ) {
                throw new CheckException($message->getText(), ACCOUNT_INVALID_PASSWORD);
            }

            if ($this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Finish signing up')]"), 0)) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        $passValue = addslashes($this->AccountFields['Pass']);

        try {
            $this->driver->executeScript(
                "function triggerInput(selector, enteredValue) {
                let input = document.querySelector(selector);
                input.dispatchEvent(new Event('focus'));
                input.dispatchEvent(new KeyboardEvent('keypress',{'key':'a'}));
                let nativeInputValueSetter = Object.getOwnPropertyDescriptor(window.HTMLInputElement.prototype, 'value').set;
                nativeInputValueSetter.call(input, enteredValue);
                let inputEvent = new Event('input', { bubbles: true });
                input.dispatchEvent(inputEvent);
                }
                triggerInput('input[name = \"user[password]\"]', '{$passValue}');"
            );
        } catch (Exception $e) {
            $this->logger->error($this->DebugInfo = 'input[name="user[password]"] not found');
            $pass->sendKeys($this->AccountFields['Pass']);
            $this->saveResponse();

//            return false;
        }
        $button = $this->waitForElement(WebDriverBy::xpath('//button[contains(@data-testid,"signup-login-submit-btn")]'), 1);
        $this->saveResponse();

        if (!$button) {
            return false;
        }

        $button->click();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        $this->saveResponse();

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "We’re experiencing some unexpected issues, but our team is already working to fix the problem")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Airbnb is temporarily unavailable, but we're working hard to fix the problem.
        if ($message = $this->http->FindSingleNode('//p[contains(text(), "Airbnb is temporarily unavailable, but we\'re working hard to fix the problem.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        // The password you entered is incorrect. Try again, or choose another login option.
        if ($error = $this->waitForElement(WebDriverBy::xpath("//form//div[
                    contains(text(), 'The password you entered is incorrect. Try again, or choose another login option.')
                    or contains(text(), 'Invalid login credentials. Please try again.')
                    or contains(text(), 'Your password must be at least 8 characters')
                ]"),
            7)) {
            throw new CheckException($error->getText(), ACCOUNT_INVALID_PASSWORD);
        }

        $this->saveResponse();

        $button = $this->waitForElement(WebDriverBy::xpath("//button[.//div[normalize-space(text())='Email']]"), 0);

        if (!$button) {
            // Old design
            $radio =
                $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Get a code by email at')]"), 0)
                ?? $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Get a code by text message')]"), 0)
            ;

            if ($radio) {
                $radio->click();
                $button = $this->waitForElement(WebDriverBy::xpath("//button[span[text()='Next']]"), 0);
            }

            if (!$button) {
                $button = $this->waitForElement(WebDriverBy::xpath("//div[contains(text(), 'Text message (SMS)')]"), 0);
            }
        }

        if ($button) {
            // prevent code spam    // refs #6042
            if ($this->isBackgroundCheck() && !$this->getWaitForOtc()) {
                $this->Cancel();
            }
            $button->click();

            // AccountID: 6158151
            if ($this->AccountFields['Login'] == 'michael@migdol.net') {
                if ($message = $this->waitForElement(WebDriverBy::xpath("//span[contains(text(), 'We’re unable to call you at this phone number.')]"), 5)) {
                    throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
                }
            }

            $this->saveResponse();

            return $this->processSecurityCheckpoint();
        }

        $this->logger->debug($this->http->currentUrl());

        if ($this->http->FindPreg('#/dashboard$#', false, $this->http->currentUrl())) {
            return true;
        }

        if ($this->waitForElement(WebDriverBy::xpath("//h1/div[contains(text(), 'Finish signing up')]"), 0)) {
            throw new CheckException(self::NOT_MEMBER_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->waitForElement(WebDriverBy::xpath("//form//div[contains(text(), 'internal error')]"), 0)) {
            throw new CheckException($message->getText(), ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//h1[contains(text(), "For security reasons, you\'ll need to reset your password")]')) {
            throw new CheckException($message, ACCOUNT_LOCKOUT);
        }

        if ($this->waitForElement(WebDriverBy::xpath("//div[
                contains(text(), 'Confirm your phone number')
                or contains(text(), 'What were you trying to do?')
            ]
            | //p[contains(text(), 'We prevented someone from signing into your account.')]
            | //h1[div[contains(text(), 'Confirm details')]]
            "), 0)
        ) {
            $this->throwProfileUpdateMessageException();
        }

        return $this->checkErrors();
    }

    public function processSecurityCheckpoint()
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info('Security Question', ['Header' => 3]);

        if ($choice = $this->waitForElement(WebDriverBy::xpath("//button[contains(., '+')]"), 3)) {
            $this->saveResponse();
            $choice->click();
        }

        $q = $this->waitForElement(WebDriverBy::xpath("
            //section//div[contains(text(), 'Enter the code we')]
            | //section//div[contains(text(), 'We emailed your code to')]
            | //section//div[contains(text(), 'We texted your code to')]
        "), 5);
        $this->saveResponse();

        if (!$q) {
            return false;
        }

        $question = $q->getText();

        $this->holdSession();
        $this->logger->debug($question);

        // todo: debug
        //sleep(60);
        //$this->parseWithCurl();

        if ($question && !isset($this->Answers[$question])) {
            $this->AskQuestion($question, null, '2fa');

            return false;
        }

        for ($i = 0; $i < strlen($this->Answers[$question]) && $i < 6; $i++) {
            $securityAnswer = $this->waitForElement(WebDriverBy::xpath("//input[
                @id = 'airlock-code-input_codeinput_$i'
                or @id = 'codeinput_$i'
                or @id = 'airlock-code-input'
            ]"), 0);

            $securityAnswer->clear();
            $securityAnswer->sendKeys($this->Answers[$question][$i]);
            usleep(100);
        }

        unset($this->Answers[$question]);

        // OTP entered is incorrect
        $error = $this->waitForElement(WebDriverBy::xpath("
            //span[contains(text(),'The code you provided is incorrect. Please try again.')]
            | //*[@id = 'code-input-screen-error-text']
        "), 5);
        $this->saveResponse();

        if ($error) {
            $this->logger->error("resetting answers");
            $this->AskQuestion($question, $error->getText(), '2fa');

            return false;
        }

        $this->logger->debug("success");

        return true;
    }

    public function ProcessStep($step)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->debug("Current URL: " . $this->http->currentUrl());

        if ($this->isNewSession()) {
            return $this->LoadLoginForm() && $this->Login();
        }

        if ($step == "2fa") {
            return $this->processSecurityCheckpoint();
        }

        return false;
    }

    public function parseWithCurl()
    {
        $this->logger->notice(__METHOD__);
        // parse with curl
        $this->browser = new HttpBrowser("none", new CurlDriver());
        $this->http->brotherBrowser($this->browser);
        $cookies = $this->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            if ($cookie['name'] == '_csrf_token') {
                $this->browser->setDefaultHeader("X-CSRF-Token", $cookie['value']);
                $this->browser->setDefaultHeader("X-Requested-With", "XMLHttpRequest");
            }// if ($cookie['name'] == '_csrf_token')
            $this->browser->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }// foreach ($cookies as $cookie)

        $this->browser->LogHeaders = true;
//        $this->browser->setProxyParams($this->http->getProxyParams());
//        $this->browser->GetURL($this->http->currentUrl());
        $this->logger->debug($this->http->currentUrl());
        $parse = parse_url($this->http->currentUrl());
        $this->host = $parse['host'];
        $this->logger->debug($this->host);
    }

    public function Parse()
    {
        //$this->browser = $this->http;
        // use curl
        $this->parseWithCurl();

        $this->browser->setDefaultHeader("Accept", "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8");

        if ($this->browser->currentUrl() != "https://{$this->host}/dashboard") {
            $this->browser->GetURL("https://{$this->host}/dashboard");
        }
        // set Balance - Travel Credit Available
        $this->SetBalance($this->browser->FindSingleNode("//div[@id='earned']/div[contains(@class, 'stat_number')]"));
        // set Travel Credit Possible
        $this->SetProperty("TravelCreditPossible", $this->browser->FindSingleNode("//div[@id='possible']/div[contains(@class, 'stat_number')]"));
        // Name
        $this->SetProperty('Name', beautifulName($this->browser->FindSingleNode("//div[@class = 'panel-body']/h2")));

        if ($this->ErrorCode == ACCOUNT_ENGINE_ERROR && (!empty($this->Properties['Name'])
                || $this->browser->currentUrl() == "'https://{$this->host}/hosting'")) {
            $this->SetBalanceNA();
        }

        // Name
        $this->browser->GetURL("https://{$this->host}/users/edit");
        $name = CleanXMLValue($this->browser->FindSingleNode("//input[@id='user_first_name']/@value")
                    . ' ' . $this->browser->FindSingleNode("//input[@id='user_last_name']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty('Name', beautifulName($name));
        }
    }

    public function ParseItineraries()
    {
        $this->browser->setDefaultHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->browser->setDefaultHeader('X-Airbnb-API-Key', 'd306zoyjsyarp7ifhu67rjxn52tv0t20');
        $this->browser->setDefaultHeader('X-Airbnb-GraphQL-Platform', 'web');
        $this->browser->setDefaultHeader('X-Airbnb-GraphQL-Platform-Client', 'minimalist-niobe');
        $this->browser->setDefaultHeader('X-Airbnb-Supports-Airlock-V2', 'true');
        $this->browser->setDefaultHeader('x-client-request-id', '1n25gko1rulq6m0jd68ft18y5fsm');
        $this->browser->setDefaultHeader('X-Client-Version', 'Unknown');
        $this->browser->setDefaultHeader('X-CSRF-Without-Token', '1');
        $this->browser->setDefaultHeader('X-Niobe-Short-Circuited', 'true');

        /*$this->browser->GetURL('https://www.airbnb.com/trips/v1');
        $key = $this->browser->FindPreg('#"baseUrl":"/api","key":"(\w+)"#');

        if (empty($key)) {
            if (!empty($this->key)) {
                $this->sendNotification('check key // MI');
                $key = $this->key;
            } else {
                return [];
            }
        }*/
        $query = [
            'operationName'     => 'TripsQuery',
            'locale'            => 'en',
            //'currency' => $this->http->FindPreg('/currency=([A-Z]{3})/'),
            'variables'     => '{"mockIdentifier":null,"params":{"version":"V2"}}',
            'extensions'    => '{"persistedQuery":{"version":1,"sha256Hash":"e8b7d30fb18e4d746538bafa8de0781206b52b82056e28ed5135b343105ebaf3"}}',
        ];
        $this->browser->GetURL("https://www.airbnb.com/api/v3/TripsQuery?" . http_build_query($query));
        $response = $this->browser->JsonLog();

        if (!isset($response)) {
            $this->ParseItinerariesOld();

            return [];
        }

        if ($this->http->FindPreg('/"title":"No trips booked...yet!"/') && $this->http->FindPreg('/"sectionId":"TRIPS_EMPTY_STATE_FULL_HEIGHT"/')) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $this->ParseItineraryPageNew($response);

        // The site displays 12 reservations
        if ($this->currentItin >= 11) {
            $this->logger->notice('>>> Load More');
            $query = [
                'operationName'     => 'TripsQuery',
                'locale'            => 'en',
                'variables'         => '{"mockIdentifier":null,"params":{"sectionIds":["UPCOMING_TRIP_LIST_V2"],"version":"V2"},"loadMore":true,"sectionIds":["UPCOMING_TRIP_LIST_V2"],"pagination":{"first":12,"after":"X192aWFkdWN0OmlkeDoxMQ=="},"gpRequest":{"expectedResponseType":"PAGINATED"}}',
                'extensions'        => '{"persistedQuery":{"version":1,"sha256Hash":"e8b7d30fb18e4d746538bafa8de0781206b52b82056e28ed5135b343105ebaf3"}}',
            ];
            $this->browser->GetURL("https://www.airbnb.com/api/v3/TripsQuery?" . http_build_query($query));
            $response = $this->browser->JsonLog();
            $this->sendNotification('check new it// MI');
            $this->ParseItineraryPageNew($response);
        }

        if ($this->currentItin > 23) {
            $this->sendNotification('more than 24 reservations // MI');
        }

        return [];
        /*$notUpcoming = $this->browser->FindPreg('/"scheduled_plans":\[\]/');

        if (!$notUpcoming) {
            $result = $this->ParseItineraryPage($response, $query);
        }

        // Past reservations
        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);
            $query['_format'] = 'for_past';
            $query['time_scope'] = 'past';
            $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_plans?" . http_build_query($query));
            $response = $this->browser->JsonLog();

            if (!$this->browser->FindPreg('/"scheduled_plans":\[\]/')) {
                $result = array_merge($result, $this->ParseItineraryPage($response, $query));
            } elseif ($notUpcoming) {
                return $this->noItinerariesArr();
            }
        } elseif ($notUpcoming) {
            return $this->noItinerariesArr();
        }

        return $result;*/
    }

    public function ParseItineraryPageNew($response)
    {
        $this->logger->notice(__METHOD__);
        // data.presentation.trips.configuration.sections[1].section.paginatedItems.edges[0].node.tripCard.title
        $isSend = false;

        foreach ($response->data->presentation->trips->configuration->sections as $section) {
            if (isset($section->section->paginatedItems->edges) && (
                    $section->sectionComponentType == 'TRIP_CARD_LIST'
                    || (strstr($section->sectionComponentType, 'PAST_TRIP') && $this->ParsePastIts)
                )
            ) {
                /*if (!$isSend) {
                    $isSend = true;
                    $this->sendNotification('check new it // MI');
                }*/

                foreach ($section->section->paginatedItems->edges as $edge) {
                    if (isset($edge->node->tripCard->action->url)) {
                        $url = $edge->node->tripCard->action->url;
                        $yearStart = $yearEnd = null;

                        if (preg_match('/^(\d{4})$/', $edge->node->tripCard->leadingDescriptionSubtitle, $m)) {
                            $yearStart = $m[1];
                            $yearEnd = $m[1];
                        } elseif (preg_match('#^(\d{4})/(\d{4})$#', $edge->node->tripCard->leadingDescriptionSubtitle, $m)) {
                            $yearStart = $m[1];
                            $yearEnd = $m[2];
                        }
                        $this->logger->debug("=============================");
                        $this->logger->debug("Year Start: $yearStart, Year End: $yearEnd");

                        // https://www.airbnb.com/trips/v1/65d8baa0-d78f-491f-ab4b-3e0b9dd5a61c/ro/EXPERIENCE_RESERVATION/10447706
                        // https://www.airbnb.com/trips/v1/ae2287a9-7426-42fa-96f3-e065a80dd22f/ro/RESERVATION2_CHECKIN/HMXNH3NTZE
                        // https://www.airbnb.com/trips/v1/29d4bd05-b260-471f-8968-92974ca519ef/ro/RESERVATION_USER_CHECKIN/HMH2DJBCNE
                        $this->browser->NormalizeURL($url);
                        $this->browser->GetURL($url);
                        $type = $this->http->FindPreg('#/\w+/([A-Z\d_]+)/\w+$#', false, $url);
                        $this->logger->debug("Type reservation: $type");

                        switch ($type) {
                            case 'RESERVATION_USER_CHECKIN':
                            case 'RESERVATION2_CHECKIN':
                                $this->ParseItineraryHotelNew($edge, $yearStart, $yearEnd);

                                break;

                            case 'EXPERIENCE_RESERVATION':
                                $this->ParseItineraryEventNew($edge, $yearStart, $yearEnd);

                                break;

                            default:
                                $this->sendNotification("New type reservation: $type // MI");

                                break;
                        }
                    }
                }
            }
        }
    }

    public function ParseItineraryHotelNew($edge, $yearStart, $yearEnd)
    {
        $this->logger->notice(__METHOD__);
        $conf = $this->browser->FindSingleNode("//div[h3[contains(text(),'Confirmation code')]]/following-sibling::div[@class]/p");
        $this->logger->info(sprintf('[%s] Hotel Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);

        $h = $this->itinerariesMaster->createHotel();
        $h->general()->confirmation($conf, 'Confirmation code');
        $h->hotel()->name($edge->node->tripCard->title);
        $address = $this->browser->FindSingleNode("//div[h3[contains(text(),'Address')]]/following-sibling::div[@class]/p");
        $h->hotel()->address($address);

        // Thu, May 4 4:00 PM
        $checkIn = $this->browser->FindSingleNode("(//div[.//h2[normalize-space(text())='Check-in']]/following-sibling::div[@class]/div/div/div/div)[1]");
        $checkInTime = $this->browser->FindSingleNode("(//div[.//h2[normalize-space(text())='Check-in']]/following-sibling::div[@class]/div/div/div/div)[2]");
        $h->booked()->checkIn(strtotime($checkInTime, strtotime("$checkIn $yearStart")));
        $checkOut = $this->browser->FindSingleNode("(//div[.//h2[normalize-space(text())='Check-in']]/following-sibling::div[@class]/div/div/div/div)[3]");
        $checkOutTime = $this->browser->FindSingleNode("(//div[.//h2[normalize-space(text())='Check-in']]/following-sibling::div[@class]/div/div/div/div)[4]");
        $h->booked()->checkOut(strtotime($checkOutTime, strtotime("$checkOut $yearEnd")));

        $guests = $this->browser->FindSingleNode("//div[h3[contains(text(),'Who’s coming')]]/following-sibling::div//p", null, false, '/(\d+) guest/');
        $h->booked()->guests($guests, false, true);

        $cancellation = $this->browser->FindSingleNode("//div[h3[contains(text(),'Cancellation policy')]]/following-sibling::div[@class]/p");
        $h->setCancellation($cancellation, true, true);

        if (!empty($h->getCancellation())) {
            if (preg_match('/Free cancellation before (\d+:\d+\s*[PA]M) on (\w+ \d+)\. After/i', $cancellation, $m)) {
                $h->booked()
                    ->deadlineRelative("$m[2] $yearStart $m[1]");
            }
        }

        $totalStr = $this->browser->FindSingleNode("//div[h3[contains(text(),'Total cost')]]/following-sibling::div[@class]/p");
        $this->logger->debug($totalStr);
        // Total cost: $2,454.77 USD
        if (preg_match('/([\d.,\s]+)\s*([A-Z]{3})/', $totalStr, $m)) {
            $h->price()->total(PriceHelper::cost($m[1]));
            $h->price()->currency($m[2]);
        }
        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($h->toArray(), true), ['pre' => true]);
    }

    public function ParseItineraryEventNew($edge, $yearStart, $yearEnd)
    {
        $this->logger->notice(__METHOD__);
        $conf = $this->browser->FindSingleNode("//div[h3[contains(text(),'Confirmation code')]]/following-sibling::div[@class]/p");
        $this->logger->info(sprintf('[%s] Event Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);

        $e = $this->itinerariesMaster->createEvent();
        $e->place()->type(Event::TYPE_EVENT);

        $e->general()->confirmation($conf, 'Confirmation code');
        $e->place()->name($edge->node->tripCard->title);
        $address = $this->browser->FindSingleNode("//div[h3[contains(text(),'Address')]]/following-sibling::div[@class]/p");
        $e->place()->address($address);

        $checkIn = $this->browser->FindSingleNode("//div[*[normalize-space(text())='Starts']]/following-sibling::div[@class]/div/div[1]");
        $checkInTime = $this->browser->FindSingleNode("//div[*[normalize-space(text())='Starts']]/following-sibling::div[@class]/div/div[2]");
        $e->booked()->start(strtotime($checkInTime, strtotime("$checkIn $yearStart")));
        $checkOut = $this->browser->FindSingleNode("//div[*[normalize-space(text())='Ends']]/following-sibling::div[@class]/div/div[1]");
        $checkOutTime = $this->browser->FindSingleNode("//div[*[normalize-space(text())='Ends']]/following-sibling::div[@class]/div/div[2]");
        $e->booked()->end(strtotime($checkOutTime, strtotime("$checkOut $yearEnd")));

        $totalStr = $this->browser->FindSingleNode("//div[h3[contains(text(),'Total cost')]]/following-sibling::div[@class]/p");
        $this->logger->debug($totalStr);
        // Total cost: € 77.42 EUR
        if (preg_match('/([\d.,\s]+)\s*([A-Z]{3})/', $totalStr, $m)) {
            $e->price()->total($m[1]);
            $e->price()->currency($m[2]);
        }
        $this->logger->debug('Parsed Hotel:');
        $this->logger->debug(var_export($e->toArray(), true), ['pre' => true]);
    }

    public function ParseItinerariesOld()
    {
        $result = [];

        $this->browser->setDefaultHeader('Accept', 'application/json, text/javascript, */*; q=0.01');
        $this->browser->GetURL('https://www.airbnb.com/trips/v1');
        $key = $this->browser->FindPreg('#"baseUrl":"/api","key":"(\w+)"#');

        if (empty($key)) {
            if (!empty($this->key)) {
                $this->sendNotification('check key // MI');
                $key = $this->key;
            } else {
                return [];
            }
        }
        $query = [
            '_order'     => 'desc',
            '_format'    => 'for_upcoming',
            'time_scope' => 'upcoming',
            '_limit'     => '50',
            '_offset'    => '0',
            'now'        => date('c'),
            'key'        => $key,
            //'currency' => $this->http->FindPreg('/currency=([A-Z]{3})/'),
            'locale' => 'en',
        ];
        // Upcoming reservations
        $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_plans?" . http_build_query($query));
        $response = $this->browser->JsonLog();
        $notUpcoming = $this->browser->FindPreg('/"scheduled_plans":\[\]/');

        if (!$notUpcoming) {
            $result = $this->ParseItineraryPage($response, $query);
        }

        // Past reservations
        if ($this->ParsePastIts) {
            $this->logger->info("Past Itineraries", ['Header' => 2]);
            $query['_format'] = 'for_past';
            $query['time_scope'] = 'past';
            $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_plans?" . http_build_query($query));
            $response = $this->browser->JsonLog();

            if (!$this->browser->FindPreg('/"scheduled_plans":\[\]/')) {
                $result = array_merge($result, $this->ParseItineraryPage($response, $query));
            } elseif ($notUpcoming) {
                return $this->noItinerariesArr();
            }
        } elseif ($notUpcoming) {
            return $this->noItinerariesArr();
        }

        return $result;
    }

    private function ParseItineraryPage($response, $query)
    {
        $result = [];

        if (!empty($response->scheduled_plans)) {
            $queryItem = [
                '_format' => 'for_trip_day_view',
                'key'     => $query['key'],
                //'currency' => $query['currency'],
                'locale' => $query['locale'],
            ];
            $this->logger->debug("Total " . count($response->scheduled_plans) . " reservations found");

            foreach ($response->scheduled_plans as $item) {
                $this->logger->debug(" ");
                $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_plans/{$item->uuid}?" . http_build_query($queryItem));
                $item = $this->browser->JsonLog();

                if (!empty($item->scheduled_plan)) {
                    // Group by ConfirmationNumber
                    $groupEvents = $groupSubEvents = [];

                    foreach ($item->scheduled_plan->events as $events) {
                        if ($events->airmoji == 'accomodation_home') {
                            $groupEvents[$events->destination->schedulable_id][] = $events;
                        } else /*if (in_array($events->airmoji, ['food_restaurant', 'trips_lifestyle', 'trips_fitness']))*/ {
                            $groupSubEvents[$events->destination->schedulable_id][] = $events;
                        }
                    }
                    // Iteration for first and last element
                    // R - Hotel
                    foreach ($groupEvents as $groupEvent) {
                        $first = reset($groupEvent);
                        $last = end($groupEvent);

                        if ($res = $this->ParseItinerary($first, $last, $item->scheduled_plan, $queryItem)) {
                            $result[] = $res;
                        }
                    }
                    // E - Event
                    if (!empty($groupSubEvents)) {
                        $this->logger->debug('Group Sub Events');

                        foreach ($groupSubEvents as $groupSubEvent) {
                            $first = reset($groupSubEvent);
                            $last = end($groupSubEvent);

                            if ($res = $this->ParseItineraryEvent($first, $last, $item->scheduled_plan, $queryItem)) {
                                $result[] = $res;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    private function ParseItineraryEvent($first, $last, $plan, $queryItem)
    {
        $this->logger->notice(__METHOD__);

        $result = ['Kind' => 'E'];

        if ($first->airmoji == 'food_restaurant') {
            $result['EventType'] = EVENT_RESTAURANT;
        } elseif ($first->airmoji == 'trips_lifestyle') {
            $result['EventType'] = EVENT_SHOW;
        } elseif ($first->airmoji == 'trips_fitness') {
            $result['EventType'] = EVENT_EVENT;
        }

        $result['ConfNo'] = $first->destination->schedulable_id;
        $this->logger->info('Parse itinerary #' . $result['ConfNo'], ['Header' => 3]);
        $result['Name'] = $first->title;

        $result['StartDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $first->time_range->starts_at)));
        $result['EndDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $last->time_range->ends_at)));

        // Mar 25 - Mar 29
        if (isset($first->map_data->subtitle) && $this->http->FindPreg('/\w+ \d+ - \w+ \d+/', false, $first->map_data->subtitle)) {
            $checkDate = date('M j', $result['StartDate']) . ' - ' . date('M j', $result['EndDate']);
            $this->logger->debug('Date ' . $checkDate . ' == ' . $first->map_data->subtitle);

            if ($checkDate != $first->map_data->subtitle) {
                $this->sendNotification('refs #17439, airbnb - Check date ' . $checkDate . ' !== ' . $first->map_data->subtitle . ' //MI');
            }
        }

        if (!empty($first->actions)) {
            foreach ($first->actions as $action) {
                if (isset($action->type) && in_array($action->type, ['directions', 'action:directions'])) {
                    $result['Address'] = $action->destination->address;

                    break;
                }

                if (isset($action->type) && $action->type == 'contact') {
                    $result['Phone'] = $action->destination->phone_number;

                    break;
                }
            }

            if (empty($result['Address'])) {
                if (isset($first->subtitles[0]->text) && count($first->subtitles) == 1) {
                    $result['Address'] = $first->subtitles[0]->text;
                } elseif (isset($first->subtitles[0]->text) && count($first->subtitles) > 1) {
                    $this->sendNotification('refs #17439 - Check address //MI');
                }
            }
        }

        $queryItem['_format'] = 'for_generic_ro';
        $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_events/{$first->event_key}?" . http_build_query($queryItem));
        $response = $this->browser->JsonLog(null, 0);

        if (isset($response->scheduled_event->rows)) {
            foreach ($response->scheduled_event->rows as $row) {
                if (in_array($row->id, ['payin_details_with_price', 'billing'])) {
                    $result['TotalCharge'] = $this->browser->FindPreg('/[\d.,]+/', false, $row->subtitle);
                    $result['Currency'] = $this->currency($row->subtitle);
                } elseif ($row->id == 'confirmation_code') {
                    // Rewrite confirmation code
                    $result['ConfNo'] = $row->subtitle;
                } elseif (empty($result['Address']) && $row->id == 'home_map' && stripos($row->subtitle ?? null, 'We’ll send you the exact address in') !== false) {
                    $this->logger->notice('Parsed itinerary skip');

                    return null;
                }
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function ParseItinerary($first, $last, $plan, $queryItem)
    {
        $this->logger->notice(__METHOD__);

        $result = ['Kind' => 'R'];
        $result['ConfirmationNumber'] = $first->destination->schedulable_id;
        $this->logger->info('Parse itinerary #' . $result['ConfirmationNumber'], ['Header' => 3]);
        $result['HotelName'] = $plan->header;

        // We’ll send you the exact address in 12 hours and add it to your itinerary.
        if (isset(reset($first->subtitles)->text)) {
            $result['Address'] = reset($first->subtitles)->text;
        }
        $result['CheckInDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $first->time_range->starts_at)));
        $result['CheckOutDate'] = strtotime(str_replace('T', '', $this->http->FindPreg('/\d{4}-\d+-\d+T\d+:\d+/', false, $last->time_range->ends_at)));

        // Mar 25 - Mar 29
        if (isset($first->map_data->subtitle) && $this->http->FindPreg('/\w+ \d+ - \w+ \d+/', false, $first->map_data->subtitle)) {
            $checkDate = date('M j', $result['CheckInDate']) . ' - ' . date('M j', $result['CheckOutDate']);
            $this->logger->debug('Date ' . $checkDate . ' == ' . $first->map_data->subtitle);

            if ($checkDate != $first->map_data->subtitle) {
                $this->sendNotification('refs #17439, airbnb - Check date ' . $checkDate . ' !== ' . $first->map_data->subtitle);
            }
        }

        if (!empty($first->actions)) {
            foreach ($first->actions as $action) {
                if (isset($action->type) && $action->type == 'contact') {
                    $result['Phone'] = $action->destination->phone_number;

                    break;
                }
            }
        }

        $queryItem['_format'] = 'for_generic_ro';
        $this->browser->GetURL("https://www.airbnb.com/api/v2/scheduled_events/{$first->event_key}?" . http_build_query($queryItem));
        $response = $this->browser->JsonLog();

        if (isset($response->scheduled_event->rows)) {
            foreach ($response->scheduled_event->rows as $row) {
                if (in_array($row->id, ['payin_details_with_price', 'billing'])) {
                    $result['Total'] = $this->browser->FindPreg('/[\d.,]+/', false, $row->subtitle);
                    $result['Currency'] = $this->currency($row->subtitle);
                } elseif ($row->id == 'cancellation_policy') {
                    $result['CancellationPolicy'] = $this->browser->FindPreg('/^(.+?)\s+The Airbnb service fee is refundable/is', false, $row->subtitle);
                } elseif (empty($result['Address'])
                    && in_array($row->id, ['home_map', 'map'])
                    && stripos($row->subtitle ?? null, 'We’ll send you the exact address in') !== false
                ) {
                    $this->logger->notice('Parsed itinerary skip');

                    return null;
                }
            }
        }

        $this->logger->debug('Parsed itinerary:');
        $this->logger->debug(var_export($result, true), ['pre' => true]);

        return $result;
    }
}
