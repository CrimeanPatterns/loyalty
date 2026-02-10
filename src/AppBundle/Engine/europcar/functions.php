<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerEuropcar extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private $currentItin = 0;
    private $skipItin = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->FilterHTML = false;
        $this->http->setHttp2(true);
//        $this->http->SetProxy($this->proxyDOP());
        $this->setProxyMount();
    }

    public function IsLoggedIn()
    {
        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();
        $this->http->GetURL("https://www.europcar.com/EBE/module/driver/DriverCardProgram.do");

        if (!$this->http->ParseForm("driverPasswordForm1000")) {
            return $this->checkErrors();
        }

        $this->http->SetInputValue('driverID', $this->AccountFields['Login']);
        $this->http->SetInputValue('password', $this->AccountFields['Pass']);
        $this->http->SetInputValue('formIsBuild', 'true');
        $this->http->SetInputValue('saveID', 'on');

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Service Temporarily Unavailable
        if ($message = $this->http->FindSingleNode("//h1[contains(text(), 'Service Temporarily Unavailable')]", null, true, null, 0)) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# Service Temporarily Unavailable
        if ($message = $this->http->FindPreg("/(Sorry\. Service Temporarily Unavailable)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //# An error occurred while processing your request.
        if ($message = $this->http->FindPreg("/(An error occurred while processing your request.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        //#  The server is temporarily unable to service your request. Please try again later.
        if ($message = $this->http->FindPreg("/(The\s*server\s*is\s*temporarily\s*unable\s*to\s*service\s*your\s*request\.\s*Please\s*try\s*again\s*later\.)/ims")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(),"We are currently facing a tempory issue with our servers")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(),"just taking a peak under the hood to make some changes")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        /*
        // hard code (AccountID: 1091188)
        if (empty($this->http->Response['body'])
            || $this->http->Response['code'] == 502) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        */

        return false;
    }

    public function Login()
    {
        $this->getCookiesFromSelenium();
        /*
        if (!$this->http->PostForm([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*
        /*;q=0.8',
            'Accept-Encoding' => 'gzip, deflate, br',
            'Origin' => 'https://www.europcar.com',
        ])) {
            return $this->checkErrors();
        }
        */

        // for curl
        if ($this->http->FindPreg("/onload=\"document\.forms\[0\]\.submit\(\);\"/ims") && $this->http->ParseForm()) {
            $this->http->PostForm();
        }

        if ($message = $this->http->FindSingleNode("//label[@id = 'passwordErrorLabel']", null, true, '/(Password is invalid)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        if ($message = $this->http->FindPreg('/Sorry, this ID number or email address was not found in our system/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This Europcar ID does not exist: please double-check via our search driver function.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = '']", null, true, '/(This Europcar ID does not exist: please double-check via our search driver function\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // These criteria are already linked to an existing Europcar ID number: for data security reasons, we kindly ask you to request your ID number on arrival at our counter.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = '']", null, true, '/(These criteria are already linked to an existing Europcar ID number: for data security reasons, we kindly ask you to request your ID number on arrival at our counter.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // This Europcar ID is no longer valid. Please contact your Europcar customer service department.
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = '']", null, true, '/(This Europcar ID is no longer valid\. Please contact your Europcar customer service department\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Technical error, contact your Europcar data centre. (CARDO_ROW_003)
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = '']", null, true, '/(Technical error, contact your Europcar data centre\. \(CARDO_ROW_003\))/ims')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // strange error
        if ($message = $this->http->FindSingleNode("//div[contains(@class, 'error') and @style = '']", null, true, '/(\?\?\?en_US\.error\.greenway\.(?:INVALID_CONTRACT|DVR_INFO_0134)\?\?\?)/ims')) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }
        /*
         * Invalid Europcar ID
         *
         * More than one driver profile is linked to this email address: please use your driver ID number
         */
        if ($message = $this->http->FindSingleNode("(//label[@id = 'driverIDErrorLabel'])[1]", null, true, '/(?:Invalid Europcar ID|More than one driver profile is linked to this email address: please use your driver ID number\.)/ims')) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }
        // Driver ID (email address) and/or password are invalid: please double-check and try again
        if ($message = $this->http->FindPreg("/(Driver ID \(email address\) and\/or password are invalid: please double-check and try again)/ims")) {
            throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
        }

        $this->checkErrors();

        if ($this->loginSuccessful()) {
            return true;
        }

        // hard code: no errors, no session (AccountID: 3413699)
        if (in_array($this->AccountFields['Login'], [
            'gathomas67@yahoo.co.uk',
            '56824169',
            '82650352',
            '22795354',
            '97903177',
            '90023750',
            '84295426',
            '85406039',
            'Jens.Neuhof@t-online.de',
            'sebastian.rupprecht@hurrax.com',
            'bjoern.c.hansen@gmail.com',
            '81216856',
            '82688448',
            '73757863',
            '90655147',
            '90803584',
            'hey@alexma.uk',
        ])
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // Name
        $name = $this->http->FindSingleNode("//div[@class='lfield']/span[@class='strong']");

        if (isset($name)) {
            $this->SetProperty("Name", beautifulName($name));
        }
        // Europcar Driver Id
        $this->SetProperty("AccountNumber", $this->http->FindSingleNode("//div[label[contains(text(), 'Europcar Driver Id')]]/text()[last()]"));
        // Status
        $this->SetProperty("Status", $this->http->FindSingleNode("//div[div[h2[contains(text(), 'My Loyalty Information')]]]/following-sibling::div/table[@class = 'result']/tbody/tr[1]/td[1]", null, true, null, 0));
        // refs #8679
        if (!isset($this->Properties['Status'])
            && ($status = $this->http->FindSingleNode("//img[@alt = 'Card Program']/@src"))) {
            $status = basename($status);
            $this->http->Log(">>> Status " . $status);

            switch ($status) {
                case 'PX_121x77.jpg': case 'privilege_executive_159x103.png':
                    $this->SetProperty("Status", "Privilege Executive");

                    break;

                case 'PE_121x77.jpg': case 'privilege_elite_159x103.png':
                    $this->SetProperty("Status", "Privilege Elite");

                    break;

                case 'PC_121x77.jpg':
                    $this->SetProperty("Status", "Privilege Club");

                    break;

                case 'PV_121x77.jpg':
                    $this->SetProperty("Status", "Privilege Elite Vip");

                    break;

                default:
                    if ($this->ErrorCode == ACCOUNT_CHECKED && !empty($status)) {
                        $this->sendNotification("europcar: newStatus: $status");
                    }
            }// switch ($status)
        }// if ($status = $this->http->FindSingleNode('//img[@alt = 'Card Program']/@src'))

        // Qualifying rentals
        $this->SetProperty("QualifyingRentals", $this->http->FindSingleNode("//div[div[h2[contains(text(), 'My Loyalty Information')]]]/following-sibling::div/table[@class = 'result']/tbody[tr/td[4]]/tr[1]/td[3]", null, true, null, 0));
        // Qualifying days
        $this->SetProperty("QualifyingDays", $this->http->FindSingleNode("//div[div[h2[contains(text(), 'My Loyalty Information')]]]/following-sibling::div/table[@class = 'result']/tbody[tr/td[4]]/tr[1]/td[4]", null, true, null, 0));

        // Balance  // refs #5743
        if (isset($this->Properties['QualifyingDays'])) {
            $this->SetBalance($this->Properties['QualifyingDays']);
        } elseif (($this->http->FindPreg("/(Click here to enroll in Europcar\'s card program)/ims")
                || $this->http->FindPreg("/(Please remember to use your Europcar ID each time your book a car)/ims")
                || $this->http->FindPreg("/(After receiving the card<\/strong>, you can start enjoying the program)/ims")
                || !$this->http->FindPreg("/(My Loyalty Information)/ims")
                || $this->http->FindPreg("/(Welcome to your\s*<strong>Privilege (?:Executive|Club)<\/strong>\s*summary page!)/ims")
                || (isset($this->Properties['Status']) && $this->Properties['Status'] == 'Business'))
                && (!empty($this->Properties['Name']) && isset($this->Properties['AccountNumber']))) {
            $this->SetBalanceNA();
        }

        // Expiration Date
        $exp = str_replace('/', '.', $this->http->FindSingleNode("//div[div[h2[contains(text(), 'My Loyalty Information')]]]/following-sibling::div/table[@class = 'result']/tbody/tr[1]/td[2]", null, true, null, 0));
        $this->http->Log(var_export("Expiration Date - " . $exp, true), true);

        if ($exp = strtotime($exp)) {
            $this->SetExpirationDate($exp);
        }
    }

    public static function FormatBalance($fields, $properties)
    {
        if ($fields['Balance'] == 1) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d day");
        } else {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], "%d days");
        }
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        //$arg['SuccessURL'] = 'https://www.europcar.com/EBE/module/driver/DriverSummary.do';
        $arg["NoCookieURL"] = true;

        return $arg;
    }

    public function ParseItineraries()
    {
        $this->http->GetURL("https://www.europcar.com/EBE/module/driver/DriverExistingBookings.do");

        if ($this->http->FindSingleNode("//div[contains(@class,'maincontent')]//div[contains(.,'We do not appear to have any pending reservations for you.')]")) {
            return $this->itinerariesMaster->setNoItineraries(true);
        }

        $nodes = $this->http->XPath->query("//table[.//td/h2[contains(., 'Reservation number:')]]");
        $this->logger->info("Total nodes found: {$nodes->length}");
//        $httpExisting = clone $this->http;
//        $this->http->brotherBrowser($httpExisting);
//        $httpExisting->GetURL('https://www.europcar.com/EBE/module/driver/DriverExistingBookings.do');

        foreach ($nodes as $node) {
            $this->parseRental($node);
        }

        if (count($this->itinerariesMaster->getItineraries()) === 0
            && $nodes->length > 0
            && $nodes->length === $this->skipItin
        ) {
            $this->logger->debug("all skipped(past)-> noItineraries");

            return $this->noItinerariesArr();
        }

        return [];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.europcar.com/EBE/module/driver/AuthenticateDrivers1000.do?action=7";
    }

    public function notifications($arFields)
    {
        $this->logger->notice("notifications");
        $this->sendNotification("europcar - failed to retrieve itinerary by conf #", 'all', true,
            "Conf #: <a target='_blank' href='https://awardwallet.com/manager/loyalty/logs?ConfNo={$arFields['ConfNo']}'>{$arFields['ConfNo']}</a><br/>Name: {$arFields['LastName']}");

        return null;
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("driverExistingBookingsForm")) {
            return $this->notifications($arFields);
        }

        if (filter_var($arFields["LastName"], FILTER_VALIDATE_EMAIL) === false) {
            $this->http->SetInputValue('reservationNumber', $arFields["ConfNo"]);
            $this->http->SetInputValue('submit', 'search1');
            $this->http->SetInputValue('lastName', $arFields["LastName"]);
        } else {
            $this->http->SetInputValue('reservationNumber2', $arFields["ConfNo"]);
            $this->http->SetInputValue('submit', 'search2');
            $this->http->SetInputValue('email', $arFields["LastName"]);
        }

        if (!$this->http->PostForm()) {
            return $this->notifications($arFields);
        }
        $message = $this->http->FindSingleNode("
        //h2[contains(text(), 'Sorry, we have not found any additional booking with the details submitted')] |
        //div[@class='rederror' and contains(text(), 'For names or addresses containing an apostrophe')]");

        if ($message) {
            return $message;
        }

        $this->parseRentalByConfirmation();

        return null;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Reservation number",
                "Type"     => "string",
                "Size"     => 33,
                "Required" => true,
            ],
            "LastName"      => [
                "Caption"  => "Last Name or Email",
                "Type"     => "string",
                "Size"     => 50,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    private function parseRental($node, HttpBrowser $httpExisting = null)
    {
        $this->logger->notice(__METHOD__);
        // pickup and dropoff datetime
        $pickupDate = $this->ModifyDateFormat($this->http->FindSingleNode(".//td[./label[contains(text(),'Pick-Up date:') or contains(text(),'Delivery date:')]]/following-sibling::td", $node));
        $dropoffDate = $this->ModifyDateFormat($this->http->FindSingleNode(".//td[./label[contains(text(),'Return date:') or contains(text(),'Collection date:')]]/following-sibling::td", $node));
        $pastItin = $dropoffDate && strtotime($dropoffDate) < strtotime('-1 day', strtotime('now'));

        if ($pastItin && !$this->ParsePastIts) {
            $this->logger->info('Skipping itinerary in the past');
            $this->skipItin++;

            return;
        }

        $rental = $this->itinerariesMaster->createRental();
        // confirmation number
        $conf = $this->http->FindSingleNode(".//td[./h2[contains(text(),'Reservation number:')]]/following-sibling::td[1]", $node);
        $this->logger->info("[{$this->currentItin}] Parse Rental #{$conf}", ['Header' => 3]);
        $this->currentItin++;
        $rental->addConfirmationNumber($conf, 'Reservation number', true);
        $rental->setPickUpDateTime(strtotime($pickupDate));

        if ($rental->getPickUpDateTime() == strtotime($dropoffDate)) {
            $rental->setDropOffDateTime(strtotime('+1 minute', strtotime($dropoffDate)));
        } else {
            $rental->setDropOffDateTime(strtotime($dropoffDate));
        }
        // pickup location
        $rental->setPickUpLocation($this->http->FindSingleNode(".//td[./label[contains(text(),'Pick-Up station:') or contains(text(),'Delivery:')]]/following-sibling::td", $node));
        // dropoff location
        $rental->setDropOffLocation($this->http->FindSingleNode(".//td[./label[contains(text(),'Return station:')] or label[contains(text(),'Collection:')]]/following-sibling::td", $node));

        if ($str = $this->http->FindSingleNode(".//div[@id='rateDiv']", $node)) {
            // total
            $totalStr = $this->http->FindPreg('/[A-Z]{3}\s*([\d.,\s]+)/', false, $str);
            $rental->price()->total(PriceHelper::cost($totalStr));
            // currency
            $rental->price()->currency($this->http->FindPreg('/([A-Z]{3})\s*[\d.,\s]+/', false, $str));
        }
        // car image url
        $imgUrl = $this->http->FindSingleNode(".//td//img[contains(@src,'/carvisuals/')]", $node);

        if (isset($imgUrl)) {
            $this->http->NormalizeURL($imgUrl);
            $rental->setCarImageUrl($imgUrl);
        }
        // car type
        $rental->setCarType($this->http->FindSingleNode(".//td[./label[contains(text(),'Car category:')]]/following-sibling::td", $node));

        $this->logger->info('Parsed Rental:');
        $this->logger->info(var_export($rental->toArray(), true), ['pre' => true]);
    }

    private function parseRentalByConfirmation()
    {
        $rental = $this->itinerariesMaster->createRental();

        if (($roots = $this->http->XPath->query("//form[@id='formC'][.//*[contains(.,'Reservation number:')]]"))->length > 1) {
            $root = $roots->item(0);
        } else {
            $root = null;
        }
        // confirmation number
        $conf = $this->http->FindSingleNode(".//td[h2[contains(text(), 'Reservation number:')]]/following-sibling::td[1]", $root);
        $rental->addConfirmationNumber($conf, 'Reservation number', true);
        // pickup and dropoff datetime
        $pickupDate = $this->http->FindSingleNode(".//td[label[contains(text(), 'Pick-Up date:') or contains(text(), 'Delivery date:')]]/following-sibling::td[1]", $root);
        $pickupDate = Html::cleanXMLValue($this->ModifyDateFormat($pickupDate));
        $dropoffDate = $this->http->FindSingleNode(".//td[label[contains(text(), 'Return date:') or contains(text(), 'Collection date:')]]/following-sibling::td[1]", $root);
        $dropoffDate = $this->ModifyDateFormat($dropoffDate);
        $addExtrasUrl = $this->http->FindSingleNode('.//a[contains(@href, "stepExtrasDirect.action")]/@href', $root);
        $pickupTime = $dropoffTime = null;

        if ($addExtrasUrl) {
            $this->http->NormalizeURL($addExtrasUrl);
            $httpExtra = clone $this->http;
            $this->http->brotherBrowser($httpExtra);
            $httpExtra->GetURL($addExtrasUrl);
            $pickupTime = $httpExtra->FindSingleNode('.//span[contains(@class, "pickupdate")]', $root, true, '/- (\d+:\d+\s*(?:AM|PM)?)/');
            $dropoffTime = $httpExtra->FindSingleNode('.//span[contains(@class, "returndate")]', $root, true, '/- (\d+:\d+\s*(?:AM|PM)?)/');
        }

        if ($pickupTime) {
            $rental->setPickUpDateTime(strtotime($pickupTime, strtotime($pickupDate)));
        } else {
            $rental->setPickUpDateTime(strtotime($pickupDate));
        }

        if ($dropoffTime) {
            $rental->setDropOffDateTime(strtotime($dropoffTime, strtotime($dropoffDate)));
        } else {
            $rental->setDropOffDateTime(strtotime($dropoffDate));
        }
        // pickup location
        $rental->setPickUpLocation($this->http->FindSingleNode(".//td[label[contains(text(), 'Pick-Up station:') or contains(text(), 'Delivery:')]]/following-sibling::td[1]", $root));
        // dropoff location
        $rental->setDropOffLocation($this->http->FindSingleNode(".//td[label[contains(text(), 'Return station:') or contains(text(), 'Collection:')]]/following-sibling::td[1]", $root));
        // car type
        $rental->setCarType($this->http->FindSingleNode(".//td[label[contains(text(), 'Car category:')]]/following-sibling::td[1]", $root));

        if (stripos($this->http->Response['body'], 'price') !== false) {
            // total
            $totalStr = $this->http->FindSingleNode(".//div[contains(text(), 'Total price')]", $root, true,
                '/(\d+[\,\.]\d+[\,\.]\d+|\d+[\,\.]\d+|\d+)/');
            $rental->obtainPrice()->setTotal(PriceHelper::cost($totalStr));
            // currency
            $rental->obtainPrice()->setCurrencyCode($this->http->FindSingleNode(".//div[contains(text(), 'Total price')]",
                $root, true, '/([A-Z]{3})/'));
        }
        // car image url
        $imgUrl = $this->http->FindSingleNode(".//td[h2[contains(text(), 'Reservation number:')]]/preceding-sibling::td[1]//img/@src", $root);

        if (isset($imgUrl)) {
            $this->http->NormalizeURL($imgUrl);
            $rental->setCarImageUrl($imgUrl);
        }
        $this->logger->info('Parsed Rental:');
        $this->logger->info(var_export($rental->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);
        // for English version
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://www.europcar.com/EBE/module/driver/DriverSummary.do");
        $this->http->RetryCount = 2;
        // Access is allowed
        if ($this->http->FindSingleNode("(//*[contains(text(), 'Log out')])")) {
            return true;
        }

        return false;
    }

    private function getCookiesFromSelenium()
    {
        $this->logger->notice(__METHOD__);

        $selenium = clone $this;
        $retry = false;
        $this->http->brotherBrowser($selenium->http);

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            // this is chrome on macBook, NOT serever Puppeteer
            $selenium->useChromePuppeteer();
            $selenium->seleniumRequest->setOs(SeleniumFinderRequest::OS_MAC);
            $selenium->seleniumOptions->addHideSeleniumExtension = false;

            $selenium->http->saveScreenshots = true;
            $selenium->http->start();
            $selenium->Start();

            try {
                $selenium->http->GetURL("https://www.europcar.com/EBE/module/driver/DriverCardProgram.do");
            } catch (TimeOutException $e) {
                $this->logger->error("TimeOutException exception: " . $e->getMessage());
                $selenium->driver->executeScript('window.stop();');
            }

            $login = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginForm"]//input[@name = "driverID"]'), 5);

            if ($agreeBtn = $selenium->waitForElement(WebDriverBy::xpath('//button[@id = "didomi-notice-agree-button"]'), 0)) {
                $this->savePageToLogs($selenium);
                $agreeBtn->click();
            }

            $password = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginForm"]//input[@name = "password"]'), 0);
            $signIn = $selenium->waitForElement(WebDriverBy::xpath('//form[@id = "loginForm"]//a[@data-bind="click: eca.myEuropcar.myaccount.loginController.submit"]'), 0);
            $this->savePageToLogs($selenium);

            if (!isset($login, $password, $signIn)) {
                return null;
            }

            $login->sendKeys($this->AccountFields['Login']);
            $password->sendKeys($this->AccountFields['Pass']);
            $this->savePageToLogs($selenium);
            $this->logger->debug("click by btn");
            $signIn->click();
            sleep(2);

            $selenium->waitForElement(WebDriverBy::xpath("
                //*[contains(text(), 'Log out')]
                | //div[contains(@class, 'error') and @style = '']
            "), 20);
            $this->savePageToLogs($selenium);

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3);
            }
        }

        return null;
    }
}
