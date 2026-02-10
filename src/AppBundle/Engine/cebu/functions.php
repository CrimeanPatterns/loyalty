<?php

//  ProviderID: 1181
use AwardWallet\Common\Parser\Util\PriceHelper;

class TAccountCheckerCebu extends TAccountChecker
{
    use \AwardWallet\Engine\ProxyList;
    use SeleniumCheckerHelper;

    private $currentItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");
        $this->http->SetProxy($this->proxyDOP(\AwardWallet\Engine\Settings::DATACENTERS_NORTH_AMERICA));
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->RetryCount = 0;
        $this->http->GetURL('https://www.cebupacificair.com/');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('aspnetForm')) {
            return $this->checkErrors();
        }

        return $this->selenium();

//        $this->http->SetInputValue('txtUserName', $this->AccountFields['Login']);
//        $this->http->SetInputValue('txtPassword', $this->AccountFields['Pass']);
//        $this->http->SetInputValue('rememberMe', 'false');
        $data = [
            "Username" => $this->AccountFields['Login'],
            "Password" => $this->AccountFields['Pass'],
        ];
        $headers = [
            "Accept"                  => "application/json",
            "Accept-Encoding"         => "gzip, deflate, br",
            "Public-API-Access-Token" => $this->http->getCookieByName("PublicApiAccessToken"),
            "content-type"            => "application/json",
            "Show-Loader"             => "true",
            "Origin"                  => "https://www.getgo.com.ph",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://webapi.getgo.com.ph/api/members/login", json_encode($data), $headers);
        $this->http->RetryCount = 2;

        return true;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (!empty($response->Data)) {
            $this->http->setCookie("PrivateAccessToken", $response->Data, ".getgo.com.ph");

            return true;
        }

        if ($this->http->getCookieByName("PrivateAccessToken", ".getgo.com.ph")) {
            return true;
        }
        // catch errors
        $message = $response->ErrorMessage ?? $this->http->FindSingleNode('//div[contains(@class, "error-container") and not(contains(@class, "hide"))]//span[contains(@class, "text")]');

        if (isset($message)) {
            $this->logger->error($message);

            if (
                $message == 'Invalid username or password.'
                || $message == 'Oops! This account does not seem to be active or to exist. Please try again or contact us to check on this issue.'
                || $message == 'Oops! This account does not seem to be active or to exist. Please try again or contact us via the Help Center to check on this issue.'
                || $message == 'Oops! This account does not exist or is inactive. Please check your login details and try again, or contact us if the issue persists.'
                || strstr($message, 'Invalid login credentials. Your account will be locked for security reasons if there are too many failed attempts')
                || $message == 'Account is Inactive'
                || $message == 'Account is Closed'
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }
            // Invalid login credentials -> click the Forgot Password link to reset your password
            if (
                strstr($message, 'You are unable to log in due to one of these reasons: <br /> 1. Invalid login credentials -> click the Forgot Password link to reset your password')
                || strstr($message, 'You are unable to log in due to one of these reasons: 1. Invalid Username or Password -> click the Forgot Password link to reset your password')
            ) {
                throw new CheckException("Invalid login credentials. Click the Forgot Password link to reset your password", ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message == 'Account is Pending'
                || strstr($message, 'Sorry, something went wrong. Please try again')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
            // Your account has been locked for security reasons. To unlock it, click the Forgot Password link and reset your password.
            if (
                $message == 'Your account has been locked for security reasons. To unlock it, click the Forgot Password link and reset your password.'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
        }// if (isset($response->ErrorMessage))
//        if ($this->http->Response['code'] == 401)
//            throw new CheckException("Sorry, something went wrong. Please try again or contact us if the issue persists.", ACCOUNT_PROVIDER_ERROR);

        return $this->checkErrors();
    }

    public function Parse()
    {
//        $this->http->GetURL("https://book.getgo.com.ph/");
        $this->http->GetURL("https://www.getgo.com.ph/member/profile/verification");

        if (
            !$this->http->FindSingleNode('//div[contains(@class, "personal-points")]/span')
            && $this->http->FindSingleNode("//span[contains(text(), 'An unexpected error has occurred.')]")
        ) {
            sleep(3);
            $this->http->GetURL("https://www.getgo.com.ph/member/profile/verification");
        }

        // Balance - points
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "personal-points")]/span', null, true, self::BALANCE_REGEXP_EXTENDED));
        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//label[contains(@class, "nav-private-name")]')));
        // GetGo
        $this->SetProperty('CardNumber', $this->http->FindSingleNode('//label[contains(@class, "nav-private-id")]', null, true, "/\:\s*([^<]+)/"));
    }

    public function ParseItineraries(): array
    {
        $this->http->GetURL("https://book.cebupacificair.com/Member/MyBookings");

        if (!$this->http->ParseForm("getGoMemberLoginForm")) {
            $this->checkErrors();
        }
        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->postCaptcha($captcha);
        }
        $this->http->SetInputValue("cebMemberLogin.Username", $this->AccountFields['Login']);
        $this->http->SetInputValue("cebMemberLogin.Password", $this->AccountFields['Pass']);

        if (!$this->http->PostForm()) {
            $this->checkErrors();
        }
        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->postCaptcha($captcha);
            $this->http->SetInputValue("cebMemberLogin.Username", $this->AccountFields['Login']);
            $this->http->SetInputValue("cebMemberLogin.Password", $this->AccountFields['Pass']);

            if (!$this->http->PostForm()) {
                $this->checkErrors();
            }
        }

        $bookingNodes = $this->http->XPath->query("//form[contains(@action,'/Manage/RetrieveInline')]");
        $this->logger->debug("Total {$bookingNodes->length} reservations found");
        $nobooking = (
            $this->http->FindSingleNode('//div[contains(@class, "bookinglist-nobooking") and contains(text(), "No bookings found.")]')
            || $this->http->FindSingleNode('//div[contains(@class, "bookinglist-nobooking") and contains(text(), "You don\'t have any bookings.")]')
        );

        if ($bookingNodes->length == 0 && $nobooking) {
            return $this->noItinerariesArr();
        }

        $bookingsParams = [];

        foreach ($bookingNodes as $node) {
            $bookingsParams[] = [
                '__RequestVerificationToken'            => $this->http->FindSingleNode("input[@name='__RequestVerificationToken']/@value", $node),
                'retrieveBooking.IsBookingListRetrieve' => 'true',
                'id'                                    => $this->http->FindSingleNode("input[@name='id']/@value", $node),
                'bookingKey'                            => '',
            ];
        }

        foreach ($bookingsParams as $params) {
            /*            $this->http->RetryCount = 0;
                        $this->http->PostURL('https://book.cebupacificair.com/Manage/RetrieveInline', $params);
                        $this->http->RetryCount = 2;
                        if ($this->http->Response['code'] == 302) {
                            $this->http->GetURL('https://book.cebupacificair.com/Member/MyBookings');
                            $this->http->PostURL('https://book.cebupacificair.com/Manage/RetrieveInline', $params);
                        }
            */
            $this->http->PostURL('https://book.cebupacificair.com/Manage/RetrieveInline', $params);
            $captcha = $this->parseCaptcha();

            if ($captcha) {
                $this->postCaptcha($captcha);
                $this->http->PostURL('https://book.cebupacificair.com/Manage/RetrieveInline', $params);
            }

            if ($this->http->FindSingleNode('//p[contains(text(), "The site requires JavaScript to be enabled!")]')) {
                $redirect = $this->http->FindPreg("/document.location.href = '(.+?)'/");

                if ($redirect) {
                    $this->http->NormalizeURL($redirect);
                    $this->http->GetURL($redirect);
                }
            }

            if ($error = $this->http->FindSingleNode('//span[contains(@class, "beyond-allowed-days") and contains(text(), "There has been a change in your flight schedule.")]')) {
                $this->logger->error("Skipping: {$error}");

                continue;
            }

            if ($this->http->FindSingleNode("//span[contains(.,'There has been a change in your flight schedule')]")) {
                $this->sendNotification("a change in your flight schedule //ZM");
            }
            $this->parseItinerary();
            sleep(rand(1, 3));
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Booking Reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Caption"  => "Surname",
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://book.cebupacificair.com/Manage/Retrieve";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it): ?string
    {
        $it = [];
        $this->http->SetProxy($this->proxyReCaptcha());
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if ($this->http->FindSingleNode('//p[contains(text(), "The site requires JavaScript to be enabled!")]')) {
            $redirect = $this->http->FindPreg("/document.location.href = '(.+?)'/");

            if ($redirect) {
                $this->http->NormalizeURL($redirect);
                $this->http->GetURL($redirect);
            }
        }

        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->postCaptcha($captcha);
        }

        if (!$this->http->ParseForm('retrieveBookingByEmailNames')) {
            $this->sendNotification('check rebu retrieve // MI');

            return null;
        }

        if (!$this->doRetrieve($arFields)) {
            return null;
        }

        if ($this->http->FindSingleNode('//p[contains(text(), "The site requires JavaScript to be enabled!")]')) {
            $redirect = $this->http->FindPreg("/document.location.href = '(.+?)'/");

            if ($redirect) {
                $this->http->NormalizeURL($redirect);
                $this->http->GetURL($redirect);
            }
        }

        if ($err = $this->parseRetrieveError()) {
            return $err;
        }

        if (!$err && $this->http->ParseForm('retrieveBookingByEmailNames')) {
            if (!$this->doRetrieve($arFields)) {
                return null;
            }
        }

        if ($err = $this->parseRetrieveError()) {
            return $err;
        }

        if ($this->http->FindSingleNode("//span[contains(.,'There has been a change in your flight schedule')]")) {
            $this->parseItineraryChange($arFields['ConfNo']);
        } else {
            $this->parseItinerary();
        }

        return null;
    }

    private function selenium()
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $result = false;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);
        $this->logger->notice("Running Selenium...");

        try {
            $selenium->UseSelenium();
//            $selenium->http->setUserAgent($this->http->userAgent);
            $selenium->keepCookies(false);
            $selenium->useFirefox();
            $selenium->http->saveScreenshots = true;

            $selenium->http->removeCookies();
            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL("https://www.cebupacificair.com/");

            $logIn = $selenium->waitForElement(WebDriverBy::xpath('//a[contains(text(), "Log in")]'), 10);
            $this->savePageToLogs($selenium);

            if (!$logIn) {
                return false;
            }

            $this->logger->debug("js injection");
            $selenium->driver->executeScript("document.querySelector('omnix-wizard-modal').hidden = true;");
            sleep(1);
            $this->savePageToLogs($selenium);

            $logIn->click();

            $logInBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in")]'), 10);
            $this->savePageToLogs($selenium);

            if (!$logInBtn) {
                return false;
            }

            $logIn->click();

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@formcontrolname = "email"]'), 10);
            $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@placeholder="Enter password"]'), 0);
            // save page to logs
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passInput) {
                return $this->checkErrors();
            }

            $loginInput->click();
            $loginInput->clear();
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->click();
            $passInput->clear();
            $passInput->sendKeys($this->AccountFields['Pass']);

            $btn = $selenium->waitForElement(WebDriverBy::xpath('//button[contains(text(), "Log in")]'), 10);
            $this->savePageToLogs($selenium);

            if (!$btn) {
                return $this->checkErrors();
            }

            $this->savePageToLogs($selenium);

//            $this->logger->debug("js injection");
//            $selenium->driver->executeScript(
//                "var scope = angular.element(document.querySelector('div.login-panel')).scope();
//                scope.\$apply(function(){
//                    scope.credential.Username = '" . $this->AccountFields['Login'] . "';
//                    scope.credential.Secword = '" . $this->AccountFields['Pass'] . "';
//                });"
//            );
//
//            $this->logger->debug("click 'Login'");
//            $selenium->driver->executeScript('setTimeout(function(){
//                    delete document.$cdc_asdjflasutopfhvcZLmcfl_;
//                    delete document.$cdc_asdjflasutopfhvcZLawlt_;
//                }, 500)');
            $btn->click();

            $selenium->waitForElement(WebDriverBy::xpath('//div[contains(@class, "personal-points")] | //em[@class = "error"] | //div[contains(@class, "error-container") and not(contains(@class, "hide"))]//span[contains(@class, "text")]'));

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
            // save page to logs
            $this->savePageToLogs($selenium);

            $result = true;
        } catch (WebDriverCurlException | NoSuchDriverException $e) {
            $this->logger->error("exception: " . $e->getMessage());
            $this->DebugInfo = "Exception";
            $retry = true;
        } finally {
            $this->logger->debug("close selenium");
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return $result;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We are currently undergoing maintenance.
        if ($message = $this->http->FindSingleNode("//p[
                contains(text(), 'We are currently undergoing maintenance.')
                or contains(text(), 'Sorry, the website is having technical problems.')
                or contains(text(), 'The GetGo website and mobile app is now turned off as we prepare to transition to')
            ]")
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Sorry, the website is currently undergoing scheduled maintenance.
        if ($this->http->FindSingleNode("//iframe[contains(@src,'/ReceiptHelper/GetGo/maintenance.html')]/@src")) {
            throw new CheckException("Sorry, the website is currently undergoing scheduled maintenance.
We will be back soon. Please check back later. Thank you for your patience.", ACCOUNT_PROVIDER_ERROR);
        }
        // maintenance
        if ($this->http->FindSingleNode("//img[@src = 'maintenance/img/maintenance.png']/@src")) {
            throw new CheckException("Our site is undergoing maintenance!", ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $flight = $this->itinerariesMaster->createFlight();
        $conf = $this->http->FindSingleNode("//div[contains(@class,'print-booking-details')]//span[contains(text(), 'Booking Reference:')]/following-sibling::strong");
        $this->logger->info(sprintf('[%s] Parse Itinerary #%s', $this->currentItin++, $conf), ['Header' => 3]);
        $flight->addConfirmationNumber($conf, 'Booking Reference:', true);
        // Status
        $flight->setStatus($this->http->FindSingleNode("//div[contains(@class,'print-booking-details')]//text()[contains(., 'Status:')]/following-sibling::strong"));
        // ReservationDate
        $flight->parseReservationDate($this->http->FindSingleNode("//div[contains(@class,'print-booking-details')]//text()[contains(., 'Booking Date:')]/following-sibling::strong"));
        // Travellers - Miss, Ms, Mr
        if ($travellers = array_filter($this->http->FindNodes("//table[contains(@class,'print-guest-details')]/tbody/tr/td", null, '/\d+\.\s+(.+?) \(\w+\)/'))) {
            $flight->setTravellers(array_map('beautifulName', $travellers));
        }
        // TripSegments
        $segments = $this->http->XPath->query("//table[contains(@class,'print-flight-details')]/tbody/tr[count(td)>3]");
        $this->logger->debug("Total {$segments->length} reservations found");

        foreach ($segments as $segment) {
            $seg = $flight->addSegment();
            // AirlineName
            $seg->setAirlineName($this->http->FindSingleNode(".//p[@class='flight-number']", $segment, false, '/^([\w]{2})\s*\d+/'));
            // FlightNumber
            $seg->setFlightNumber($this->http->FindSingleNode(".//p[@class='flight-number']", $segment, false, '/^[\w]{2}\s*(\d+)/'));
            // DepName
            $seg->setDepName($this->http->FindSingleNode('td[2]/big[1]', $segment));
            // DepCode
            $seg->setDepCode($this->http->FindSingleNode('td[2]/big[2]', $segment, false, '/\b([A-Z]{3})\b/'));
            // DepDate
            $dep = join(' ', $this->http->FindNodes('td[2]/big/following-sibling::text()', $segment));
            // DepTerminal
            if ($terminal = $this->http->FindPreg('/Terminal\s+(\d+)/', false, $dep)) {
                $seg->setDepTerminal($terminal);
            }
            // DepDate Thu. 18 Jul. 2019, 0725H (07:25AM)
            $seg->setDepDate(strtotime($this->http->FindPreg('/\w{3}\. \d{1,2} \w{3}\. \d{4}/', false, $dep) . ', ' . $this->http->FindPreg('/\((\d+:\d+[A-Z]{2})\)/', false, $dep), false));

            // ArrName
            $seg->setArrName($this->http->FindSingleNode('td[4]/big[1]', $segment));
            // ArrCode
            $seg->setArrCode($this->http->FindSingleNode('td[4]/big[2]', $segment, false, '/\b([A-Z]{3})\b/'));
            // ArrDate
            $arr = join(' ', $this->http->FindNodes('td[4]/big/following-sibling::text()', $segment));
            // ArrTerminal
            if ($terminal = $this->http->FindPreg('/Terminal\s+(\d+)/', false, $arr)) {
                $seg->setArrTerminal($terminal);
            }
            // ArrDate Thu. 18 Jul. 2019, 0725H (07:25AM)
            $seg->setArrDate(strtotime($this->http->FindPreg('/\w{3}\. \d{1,2} \w{3}\. \d{4}/', false, $arr) . ', ' . $this->http->FindPreg('/\((\d+:\d+[A-Z]{2})\)/', false, $arr), false));

            // Seats
            $seg->setSeats($this->http->FindNodes("//table[contains(@class,'print-guest-details')]//td/strong
            [normalize-space(text())='{$seg->getDepName()} - {$seg->getArrName()}']/following-sibling::text()[contains(.,'Seat ')]", null, '/Seat\s+([\w]{2,4})\b/'));
        }

        // Fare, Taxes and Fees:
        $items = $this->http->XPath->query("//table[contains(@class,'print-farebreakdown-details')]/tbody/tr");

        foreach ($items as $item) {
            $param = $this->http->FindSingleNode('td[1]', $item);
            $value = $this->http->FindSingleNode('td[2]', $item);

            if ($price = $this->http->FindPreg('/[\d.,]+/', false, $value)) {
                if ($this->http->FindPreg('/^Base\s*Fare/i', false, $param)) {
                    $flight->obtainPrice()->setCost(PriceHelper::cost($price));
                    $flight->obtainPrice()->setCurrencyCode($this->http->FindPreg('/^([A-Z]{3})\s+[\d.,]+/', false, $value));
                } else {
                    $flight->obtainPrice()->addFee($param, PriceHelper::cost($price));
                }
            }
        }

        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($flight->toArray(), true), ['pre' => true]);
    }

    private function parseItineraryChange($pnr = null)
    {
        $cntCancelled = 0;
        $segments = $this->http->XPath->query("//text()[normalize-space()='Arrives']/ancestor::div[contains(.,'Flight')][1]");

        foreach ($segments as $segment) {
            if ($this->http->FindSingleNode("./preceding-sibling::div[@class='flight-title-text']//text()[normalize-space()='Cancelled']",
                $segment)
            ) {
                $cntCancelled++;
            }
        }
        $r = $this->itinerariesMaster->add()->flight();

        if ($segments->length > 0 && $cntCancelled === $segments->length) {
            if ($pnr === null) {
                $this->itinerariesMaster->removeItinerary($r);

                return null;
            }
            $r->general()->cancelled();
        }
        $r->general()
            ->confirmation($pnr)
            ->travellers($this->http->FindNodes("//div[contains(@class,'passengers-container')]//li"));

        foreach ($segments as $segment) {
            $s = $r->addSegment();

            $flightNo = $this->http->FindSingleNode("./div[1]//text()[starts-with(normalize-space(),'Flight No')]",
                $segment, false, "/Flight No.?\s*(.+)/");
            $s->airline()
                ->name($this->http->FindPreg("/([A-Z\d][A-Z]|[A-Z][A-Z\d])\s*\d+/", false, $flightNo))
                ->number($this->http->FindPreg("/(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])\s*(\d+)/", false, $flightNo));
            $depDate = strtotime($this->http->FindSingleNode("./div[2]/div[2]", $segment));
            $depTime = $this->http->FindSingleNode("./div[2]/div[3]", $segment, false,
                "/\(\s*(\d+:\d+\s*(?:[ap]m)?)\s*\)/i");
            $depInfo = $this->http->FindSingleNode("./div[2]/div[4]", $segment);
            $s->departure()
                ->date(strtotime($depTime, $depDate))
                ->name($this->http->FindPreg("/(.+?)\s*(?:Terminal|$)/i", false, $depInfo))
                ->noCode()
                ->terminal($this->http->FindPreg("/Terminal\s+(.+)/i", false, $depInfo), false, true);
            $arrDate = strtotime($this->http->FindSingleNode("./div[3]/div[2]", $segment));
            $arrTime = $this->http->FindSingleNode("./div[3]/div[3]", $segment, false,
                "/\(\s*(\d+:\d+\s*(?:[ap]m)?)\s*\)/i");
            $arrInfo = $this->http->FindSingleNode("./div[3]/div[4]", $segment);
            $s->arrival()
                ->date(strtotime($arrTime, $arrDate))
                ->name($this->http->FindPreg("/(.+?)\s*(?:Terminal|$)/i", false, $arrInfo))
                ->noCode()
                ->terminal($this->http->FindPreg("/Terminal\s+(.+)/i", false, $arrInfo), false, true);

            if ($this->http->FindSingleNode("./preceding-sibling::div[@class='flight-title-text']//text()[normalize-space()='Cancelled']",
                $segment)
            ) {
                $s->setCancelled(true);
            }
        }
    }

    private function parseRetrieveError(): ?string
    {
        $this->logger->notice(__METHOD__);

        if ($msg = $this->http->FindSingleNode("//p[contains(text(), 'ERROR: The booking that you are trying to retrieve does not exist')]")) {
            $msg = $this->http->FindPreg('/ERROR:\s*(.+?)$/', false, $msg);
            $this->logger->error($msg);

            return $msg;
        }

        return null;
    }

    private function doRetrieve(array $arFields): bool
    {
        $this->logger->notice(__METHOD__);
        $this->http->SetInputValue('cebRetrieveBooking.RecordLocator', $arFields['ConfNo']);
        $this->http->SetInputValue('cebRetrieveBooking.LastName', $arFields['LastName']);

        if (!$this->http->PostForm()) {
            return false;
        }

        if ($this->http->FindPreg("/window.location='\/Manage\/Retrieve'/")) {
            $this->http->GetURL($this->ConfirmationNumberURL($arFields));
        }
        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->postCaptcha($captcha);
        }
        $captcha = $this->parseCaptcha();

        if ($captcha) {
            $this->postCaptcha($captcha);
        }

        return true;
    }

    private function postCaptcha($captcha): void
    {
        $this->logger->notice(__METHOD__);
        $this->http->ParseForm();
        $this->http->SetInputValue('g-recaptcha-response', $captcha);
        $this->http->SetInputValue('recaptcha_response', '');
        $headers = [
            'Accept'          => '*/*',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Content-Type'    => 'application/x-www-form-urlencoded',
            'Origin'          => 'https://hkvalidate.perfdrive.com',
            'Referer'         => $this->http->currentUrl(),
        ];
        $this->http->PostForm($headers);
    }

    /** @return string|false */
    private function parseCaptcha()
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode('//div[@class = "g-recaptcha"]/@data-sitekey');
        $this->logger->debug("data-sitekey: {$key}");

        if (!$key) {
            return false;
        }
        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];

        return $this->recognizeByRuCaptcha($recognizer, $key, $parameters);
    }
}
