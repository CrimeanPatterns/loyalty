<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\ProxyList;

class TAccountCheckerCostravel extends TAccountChecker
{
    use ProxyList;
    use SeleniumCheckerHelper;

    private const REWARDS_PAGE_URL = 'https://www.costcotravel.com/?h=12';

    private const WAIT_TIMEOUT = 10;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setHttp2(true);
        $this->UseSelenium();

        /*
        $this->useFirefoxPlaywright();
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;

        $this->http->setUserAgent("curl/7.88.1");
        */

        $this->useGoogleChrome(SeleniumFinderRequest::CHROME_95);
        $this->seleniumOptions->addHideSeleniumExtension = false;
        $this->seleniumOptions->userAgent = null;

        $this->http->saveScreenshots = true;
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
        // Invalid email address or password
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("The email address and/or password you entered are invalid.", ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->GetURL('https://www.costcotravel.com/?h=12');
        $this->waitForElement(WebDriverBy::xpath('//form[@id="localAccountForm"]'), self::WAIT_TIMEOUT);

        $this->saveResponse();

        $login = $this->waitForElement(WebDriverBy::xpath('//input[@id="signInName"]'), 0);
        $password = $this->waitForElement(WebDriverBy::xpath('//input[@id="password"]'), 0);

        $rememberMe = $this->waitForElement(WebDriverBy::xpath('//input[@id="rememberMe"]'), 0);
        $submit = $this->waitForElement(WebDriverBy::xpath('//button[@id="next"]'), 0);

        if (!$login || !$password || !$submit || !$rememberMe) {
            if ($this->http->FindSingleNode('//title[contains(text(), "Costco Travel")]')) {
                throw new CheckRetryNeededException(3, 3); // prevent page crush
            }

            return $this->checkErrors();
        }

        $login->sendKeys($this->AccountFields['Login']);
        $password->sendKeys($this->AccountFields['Pass']);
        $rememberMe->click();
        $submit->click();

        return true;
    }

    public function Login()
    {
        $this->waitForElement(WebDriverBy::xpath('//span[@data-test="memberName"] | //div[@class="error pageLevel"]/p | //font[@data-hook="action_error_text" and @class="taLeft"] | //h1[contains(text(), "Membership Verification")]'), self::WAIT_TIMEOUT * 2);

        $this->saveResponse();

        if ($this->http->FindSingleNode('//span[@data-test="memberName"]')) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[@class="error pageLevel"]/p/text() | //font[@data-hook="action_error_text" and @class="taLeft"]/text()')) {
            $this->logger->error("[Error]: {$message}");

            if (
                $message === 'The information entered is incorrect and does not match a Travel account.'
                || $message === 'Please confirm your last name, membership number and Costco Travel password are all correct and that your membership is not expired.'
                || $message === 'The Costco membership number and password on file do not match those that were supplied. Please verify the number and password and try again.'
                || strstr($message, 'Please verify the Costco member number you entered and try again. It is possible you have not yet created a Costco Travel account.')
                || strstr($message, 'The Costco membership number and password you supplied do not match. Please try again. You have')
                || strstr($message, 'Please confirm your last name, membership number and Costco Travel password are correct. If your Costco Membership has expired')
                || $message === 'The information entered is incorrect and does not match a Costco account.'
                || strstr($message, "The email address and/or password you entered are invalid")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                $message === 'You have exceeded the maximum number of login attempts. Please click "Forgot Password" to reset your password.'
            ) {
                throw new CheckException($message, ACCOUNT_LOCKOUT);
            }
            // AccountID: 5388310, 5265492
            if ($message === "com.cwctravel.apiconnectclient.invoker.ApiException: error") {
                throw new CheckException("We are currently unable to perform this action. Please try again later. Call 1-866-921-7925 if the problem persists.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'An error occurred while processing your request. Please try again'
                || strstr($message, "Sign-in Failure - Failed to Authenticate member, please try again")
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($this->http->FindSingleNode('//h1[contains(text(), "Membership Verification")]')) {
            $this->throwProfileUpdateMessageException();
        }

        if ($this->AccountFields['Login'] == 'dsanok@yahoo.com') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://www.costcotravel.com/h=5001');
        $this->waitForElement(WebDriverBy::xpath('//td[@id="spanMembershipNumber_desktop"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $this->increaseTimeLimit(180);

        // Name
        $this->SetProperty('Name', beautifulName($this->http->FindSingleNode('//li[@id = "logoutDiv" and contains(@class, "displayInline")]//div[@data-test = "divMemberFullName"]')));

        // Costco Membership #
        $this->SetProperty('AccountNumber', $this->http->FindSingleNode('//table[@id = "personal-info-table_desktop"]//*[@data-test = "paragraphMembershipNumber"]'));

        // no Balance
        if (!empty($this->Properties['Name'])) {
            $this->SetBalanceNA();
        }
    }

    public function ParseItineraries()
    {
        $this->http->RetryCount = 0;

        $its = [];

        $this->http->GetURL('https://www.costcotravel.com/h=5002');
        $this->waitForElement(WebDriverBy::xpath('//a[@id="upcoming-tab-id"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        $noItineraries = $this->http->FindSingleNode("//div[@data-hook='Upcoming_Bookings']//span[contains(text(),'You do not have any upcoming bookings.')]");
        $list = $this->http->XPath->query("//div[@data-hook='Upcoming_Bookings']//div[@data-test='divBookingId']//a[contains(@class,'viewLink')]");
        $this->logger->debug("ParsePastIts:" . var_export($this->ParsePastIts, true));

        if ($noItineraries && $list->length === 0 && !$this->ParsePastIts) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        $this->logger->debug("Upcoming itineraries: {$list->length}");

        if (!$noItineraries && $list->length > 0) {
            foreach ($list as $item) {
                $category = strtolower($this->http->FindSingleNode("./@data-category", $item));
                $id = $this->http->FindSingleNode("./@data-id", $item);
                $its[] = [
                    'id'       => $id,
                    'category' => $category,
                    'type'     => 'upcoming',
                ];
            }
            // Canceled
            $this->http->GetURL('https://www.costcotravel.com/h=5002');
            $this->waitForElement(WebDriverBy::xpath('//a[@id="cancel-tab-id"]'), self::WAIT_TIMEOUT)->click();
            $this->waitForElement(WebDriverBy::xpath('//a[@id="cancel-tab-id" and contains(@class, "clicked")]'), self::WAIT_TIMEOUT);
            $this->saveResponse();

            $list = $this->http->XPath->query("//div[@data-hook='Canceled_Bookings']//div[@data-test='divBookingId']//a[contains(@class,'viewLink')]");
            $this->logger->debug("Canceled itineraries: {$list->length}");

            foreach ($list as $item) {
                $category = strtolower($this->http->FindSingleNode("./@data-category", $item));
                $id = $this->http->FindSingleNode("./@data-id", $item);
                $its[] = [
                    'id'       => $id,
                    'category' => $category,
                    'type'     => 'canceled',
                ];
            }
        }

        if ($this->ParsePastIts) {
            $this->http->GetURL('https://www.costcotravel.com/h=5002');
            $this->waitForElement(WebDriverBy::xpath('//a[@id="past-tab-id"]'), self::WAIT_TIMEOUT)->click();
            $this->waitForElement(WebDriverBy::xpath('//a[@id="past-tab-id" and contains(@class, "clicked")]'), self::WAIT_TIMEOUT);
            $this->saveResponse();

            $list = $this->http->XPath->query("//div[@data-hook='Past_Bookings']//div[@data-test='divBookingId']//a[contains(@class,'viewLink')]");
            $this->logger->debug("Past itineraries: {$list->length}");

            foreach ($list as $item) {
                $category = strtolower($this->http->FindSingleNode("./@data-category", $item));
                $id = $this->http->FindSingleNode("./@data-id", $item);
                $its[] = [
                    'id'       => $id,
                    'category' => $category,
                    'type'     => 'past',
                ];
            }
        }

        $i = 0;

        foreach ($its as $it) {
            if ($i == 20) {
                $this->increaseTimeLimit();
            }
            $i++;
            $this->getIdAndParse($it['id'], $it['category'], $it['type']);
        }

        return [];
    }

    private function loginSuccessful($timeout = 20)
    {
        $this->logger->notice(__METHOD__);

        $this->http->RetryCount = 0;
        $this->http->GetURL(self::REWARDS_PAGE_URL, [], $timeout);
        $this->http->RetryCount = 2;

        $this->waitForElement(WebDriverBy::xpath('//span[@data-test="memberName"]'), self::WAIT_TIMEOUT);
        $this->saveResponse();

        if ($this->http->FindSingleNode('//span[@data-test="memberName"]')) {
            return true;
        }

        return false;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode('//img[@alt="Under Scheduled Maintenance"]/@alt')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function getIdAndParse($id, $category, $type)
    {
        $this->logger->notice(__METHOD__ . ' - ' . $category);
        $this->http->RetryCount = 0;

        switch ($category) {
            case 'car':
                $this->http->GetURL("https://www.costcotravel.com/h=3007_" . $id . "_1", [], 30);
                $this->waitForElement(WebDriverBy::xpath('//span[@class="OneLinkNoTx"]'), self::WAIT_TIMEOUT);
                $this->saveResponse();
                $this->parseRental($type);

                break;

            case 'vp':
            case 'package':
                $this->http->GetURL("https://www.costcotravel.com/h=2020_" . $id, [], 30);
                $this->waitForElement(WebDriverBy::xpath('//span[@class="OneLinkNoTx"]'), self::WAIT_TIMEOUT);
                $this->saveResponse();
                $this->parsePackage($type);

                break;

            case 'cruise':
                $this->http->GetURL("https://www.costcotravel.com/h=2612_" . $id, [], 30);
                $this->waitForElement(WebDriverBy::xpath('//span[@class="OneLinkNoTx"]'), self::WAIT_TIMEOUT);
                $this->saveResponse();
                $this->parseCruise($type);

                break;

            case 'hobe':
            case 'hotel':
                $this->http->GetURL("https://www.costcotravel.com/h=3510_" . $id, [], 30);
                $this->waitForElement(WebDriverBy::xpath('//span[@class="OneLinkNoTx"]'), self::WAIT_TIMEOUT);
                $this->saveResponse();
                $this->parseHotel($type);

                break;

            default:
                $this->logger->error('Unknown category');

                if ($category == 'vp' && $type == 'upcoming') {
                    $this->sendNotification('upcoming VP! // MI');
                } else {
                    $this->sendNotification("new category {$category} // MI");
                }

                break;
        }
        $this->http->RetryCount = 2;
    }

    private function parseRental($type)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->rental();

        if ($type == 'canceled') {
            $r->general()->cancelled();
        }
        $XpathConf = '//h2[normalize-space() = "Confirmation Numbers"]/following-sibling::div/p[not(starts-with(normalize-space(),"Costco Travel Booking #"))]';
        $confirmation = preg_replace('/CANCELLED:/', '', $this->http->FindSingleNode($XpathConf, null, true, "/.+? #: (.+?)$/"));
        $confDescription = $this->http->FindSingleNode($XpathConf, null, true, "/(.+?) #: .+?$/");
        $confCTB = $this->http->FindSingleNode('//h2[normalize-space() = "Confirmation Numbers"]/following-sibling::div/p[starts-with(normalize-space(),"Costco Travel Booking #")]', null, true, "/.+? #: (.+)$/");

        $this->logger->info("Parse {$type} itinerary # {$confCTB}", ['Header' => 3]);

        if ($status = $this->http->FindPreg('/(CANCELLED):/', false, $confirmation)) {
            $confirmation = preg_replace('/CANCELLED:/', '', $confirmation);
            $r->general()->status($status);
            $r->general()->cancelled();
        }

        $currency = $this->http->FindPreg('/this\.DEFAULT_CURRENCY_CODE\s*=\s*"([A-Z]{3})";/');
        $r->price()
            ->cost(PriceHelper::cost($this->http->FindSingleNode('(//p[preceding::p[normalize-space()="Car Rental"]])[1]', null, true, "/(\d+[\d.,\s]+)/")))
            ->total(PriceHelper::cost($this->http->FindSingleNode('(//p[preceding::p[normalize-space()="Total Rental Price"]])[1]', null, true, "/(\d+[\d.,\s]+)/")))
            ->tax(PriceHelper::cost($this->http->FindSingleNode('(//p[preceding::p[normalize-space()="Taxes & Fees"]])[1]', null, true, "/(\d+[\d.,\s]+)/")), false, true)
            ->currency($currency);
        $r->ota()->confirmation($confCTB, "Costco Travel Booking");
        $r->program()
            ->keyword($this->http->FindSingleNode($XpathConf, null, true, "/(.+?) Confirmation #: .+$/"));
        $r->general()
            ->confirmation($confirmation, $confDescription)
            ->traveller(beautifulName($this->http->FindSingleNode('//div[@id="renterInformationAccordion"]/descendant::p[starts-with(normalize-space(),"Costco Member Name - ")]', null, true, "/Costco Member Name - (.+)$/")), true);
        $r->pickup()
            ->location(implode(",", $this->http->FindNodes('//div[@id="itineraryAccordion"]/descendant::h3[starts-with(normalize-space(),"Pick-Up -")]/following-sibling::p[position() <= 3]')))
            ->date2($this->http->FindSingleNode('(//div[@id="itineraryAccordion"]/descendant::h3[starts-with(normalize-space(),"Pick-Up -")])[1]', null, true, "/Pick-Up - (.+)$/"));
        $r->dropoff()
            ->location(implode(",", $this->http->FindNodes('//div[@id="itineraryAccordion"]/descendant::h3[starts-with(normalize-space(),"Drop-Off -")]/following-sibling::p[position() <= 3]')))
            ->date2($this->http->FindSingleNode('(//div[@id="itineraryAccordion"]/descendant::h3[starts-with(normalize-space(),"Drop-Off -")])/text()[1]', null, true, "/Drop-Off - (.+)$/"));
        $r->car()
            ->model($this->http->FindSingleNode('//span[@class="carModelName"]'))
            ->type($this->http->FindSingleNode('//div[contains(@class,"car-capacity")]/h2'))
            ->image($this->http->FindSingleNode('//div[contains(@class,"car-image")]/descendant::img/@src'), true, true);

        $this->logger->info('Parsed rental:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function parseHotel($type)
    {
        $this->logger->notice(__METHOD__);
        $r = $this->itinerariesMaster->add()->hotel();

        if ($type == 'canceled') {
            $r->general()->cancelled();
        }
        $pax = array_map("beautifulName", $this->http->FindNodes("//p[@data-test='paragraphTravelerName']"));
        $confStr = $this->http->FindSingleNode("//h3[@data-test='bookingConfirmationNumber']|//span[@data-test='confirmationNumber']");

        if (!$confStr) {
            $confStr = $this->http->FindSingleNode("//span[contains(@class,'confimation-number')]");
        }

        $conf = $this->http->FindPreg('/Confirmation Number: ([\w\s]{3,})$/', false, $confStr);

        if (!$conf) {
            $conf = $this->http->FindPreg('/^(\w+)$/', false, $confStr);
        }

        if (!$conf) {
            $conf = $this->http->FindPreg('/CANCELLED:\s*(\w+)$/', false, $confStr);
        }

        $this->logger->info("Parse {$type} itinerary # {$conf}", ['Header' => 3]);
        $r->ota()->confirmation($conf, 'Confirmation Number');
        $r->general()->travellers($pax, true);

        $cost = $this->http->FindSingleNode("//li[starts-with(normalize-space(),'Base Rate')]/span",
            null, false, "/(\d[\d.,]+)/");

        if (!$cost) {
            $cost = $this->http->FindSingleNode("//p[starts-with(normalize-space(),'Base Package Price')]/../following-sibling::div[1]",
                null, false, "/(\d[\d.,]+)/");
        }
        $tax = $this->http->FindSingleNode("//li[starts-with(normalize-space(),'Taxes and Fees')]/span",
            null, false, "/(\d[\d.,]+)/");

        if (!$tax) {
            $tax = $this->http->FindSingleNode("//p[starts-with(normalize-space(),'Taxes and Fees')]/../following-sibling::div[1]",
                null, false, "/(\d[\d.,]+)/");
        }
        $total = $this->http->FindSingleNode("//li[starts-with(normalize-space(),'Total Price')]//span",
            null, false, "/(\d[\d.,]+)/");

        if (!$total) {
            $total = $this->http->FindSingleNode("//span[@data-test='spantotalPriceAmount_0']",
                null, false, "/(\d[\d.,]+)/");
        }
        $curr = $this->http->FindSingleNode("//li[starts-with(normalize-space(),'Total Price')]//span", null,
            false, "/^(.)\d[\d.,]+/");

        if (!$curr) {
            $curr = $this->http->FindSingleNode("//span[@data-test='spantotalPriceAmount_0']",
                null, false, "/^(.)\d[\d.,]+/");
        }
        $r->price()
            ->cost(PriceHelper::cost($cost))
            ->tax(PriceHelper::cost($tax))
            ->total(PriceHelper::cost($total))
            ->currency($curr);
        $this->parseHotelBlock($r);
    }

    private function parseHotelBlock(AwardWallet\Schema\Parser\Common\Hotel $r, ?DOMNode $root = null): ?string
    {
        $this->logger->notice(__METHOD__);

        if ($this->http->FindSingleNode(".//span[contains(text(), 'Canceled on ')]", $root)) {
            $r->general()->status('Cancelled');
            $r->general()->cancelled();
        }
        $confs = $this->http->FindNodes(".//p[starts-with(normalize-space(),'Hotel Confirmation Number')]/span[@data-test='confirmationNumber']", $root, '/[\w\s]+/');
        $confs = array_filter(array_map(function ($value) {
            return str_replace(' ', '',
                preg_replace('/^CANCELLED:/', '', $value)
            );
        }, $confs));

        if ($confs) {
            foreach ($confs as $conf) {
                $r->general()->confirmation($conf);
            }
        } elseif (!$this->http->FindSingleNode(".//text()[starts-with(normalize-space(),'Hotel Conf')]", $root)) {
            $r->general()->noConfirmation();
        }
        $r->hotel()
            ->name($this->http->FindSingleNode(".//*[@data-test='titleNameOfHotel']", $root))
            ->address($hotelAddress = $this->http->FindSingleNode(".//p[@data-test='paragraphHotelAddress']",
                $root));

        $checkIn = $this->http->FindSingleNode(".//p/span[starts-with(normalize-space(),'Check-In:')]/ancestor::p[1]",
            $root, false, '/Check-In:\s*(.+)/');
        $this->logger->debug($checkIn);

        $checkOut = $this->http->FindSingleNode(".//p/span[starts-with(normalize-space(),'Check-Out:')]/ancestor::p[1]",
            $root, false, '/Check-Out:\s*(.+)/');
        $this->logger->debug($checkOut);
        $r->booked()
            ->checkIn2(str_replace(' - ', ', ', $checkIn))
            ->checkOut2(str_replace(' - ', ', ', $checkOut))
            ->guests(array_sum($this->http->FindNodes(".//p[@data-test='paragraphPassengerNumber']", $root,
                "/(\d+) Adults?/")));

        $roomsNode = $this->http->XPath->query("//div[contains(@class,'roomCategorySelection')]", $root);

        foreach ($roomsNode as $roomNode) {
            $room = $r->addRoom();
            $type = $this->http->FindSingleNode(".//*[self::h3 or self::div][@data-test='titleRoomName']", $roomNode, false, "/Room \d+: (.+)/");
            $room->setType($type);

            $rateType = $this->http->FindSingleNode(".//h3[@data-test='titleRoomName']/following-sibling::div[1][contains(@class,'contract-rate-text')]",
                $roomNode);

            if (empty($rateType)) {
                $rateType = $this->http->FindSingleNode(".//div[contains(@class,'rate-details-link')]/preceding-sibling::div[1][contains(.,'Rate')]",
                    $roomNode);
            }

            if (!empty($rateType)) {
                $room->setRateType($rateType);
            }
        }

        $this->logger->info('Parsed hotel:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);

        return $hotelAddress;
    }

    private function parsePackage($type)
    {
        $this->logger->notice(__METHOD__);
        $pax = array_map("beautifulName", $this->http->FindNodes("//p[@data-test='paragraphTravelerName']"));
        /*
        $confCTB = $this->http->FindSingleNode("//h3[@data-test='bookingConfirmationNumber']", null, false,
            "/Confirmation Number: (\w+)$/");
        */

        $otaConf = $this->http->FindSingleNode(
            "//span[@data-test='spanCostcoTravelConfirmationNumber']/following-sibling::span",
            null, false, "/^(\w+)$/");

        if (!$otaConf) {
            $otaConf = $this->http->FindSingleNode(
                "//p[@data-test='rentalCarConfirmationStatus']/span",
                null, false, "/^(\w+)$/");
        }

        $this->logger->info("Parse {$type} itinerary # {$otaConf}", ['Header' => 3]);
        /*
        $tax = $this->http->FindSingleNode("//p[@data-test='paragraphTaxesFeeAmount']");
        $cost = $this->http->FindSingleNode("//p[@data-test='paragraphVacationPackageAmount']");
        $total = $this->http->FindSingleNode("//p[@data-test='paragraphTotalPackagePriceAmount']");
        */
        //Hotels
        $hotelNodes = $this->http->XPath->query("//div[@data-test='divIncludedHotels'][1]");

        foreach ($hotelNodes as $root) {
            $this->logger->info("Hotel", ['Header' => 4]);
            $r = $this->itinerariesMaster->add()->hotel();

            if ($type == 'canceled') {
                $r->general()->cancelled();
            }

            if ($date = $this->http->FindSingleNode("//span[@data-test='spanBookingDate']")) {
                $r->general()->date2($date);
            }
            $r->ota()->confirmation($otaConf);
            $r->general()->travellers($pax, true);
            $hotelAddress = $this->parseHotelBlock($r, $root);
        }
        // Activities
        // skip, not enough info
        // Transportation

//        if ($this->http->XPath->query("//span[@data-test='spanTransferFlightDate']")->length) {
//            $this->sendNotification('check transfer // MI');
//        }

        $transferNodes = $this->http->XPath->query(
            "//h2[contains(text(),'Transportation')]/../../following-sibling::div[contains(@class,'car-summary')]//div/span[normalize-space()='Flight Details:']/ancestor::div[@class='transportation-card']");

        if ($transferNodes->length !== 1 && $hotelNodes->length !== 1) {
            // skip transfer
            return;
        }

        foreach ($transferNodes as $root) {
            $this->logger->info("Transfer", ['Header' => 4]);

            $r = $this->itinerariesMaster->add()->transfer();
            $r->ota()->confirmation($otaConf, 'Confirmation Number');

            if ($type == 'canceled') {
                $r->general()->cancelled();
            }

            if ($date = $this->http->FindSingleNode("//span[@data-test='spanBookingDate']")) {
                $r->general()->date2($date);
            }

            $r->general()
                ->noConfirmation()
                ->travellers($pax, true);

            $date = $this->http->FindSingleNode(".//span[normalize-space()='Arrival to']/following-sibling::div/span[@data-test='spanTransferFlightDate'][1]",
                $root);

            if ($date) {
                $s = $r->addSegment();
                $time = $this->http->FindSingleNode(".//span[normalize-space()='Arrival to']/following-sibling::div/span[@data-test='spanTransferFlightTime'][1]",
                    $root);
                $s->departure()
                    ->code($this->http->FindSingleNode(".//span[normalize-space()='Arrival to']/following-sibling::span[@data-test='spanTransferFlightCity'][1]",
                        $root, false, "/\(([A-Z]{3})\)/"))
                    ->date(strtotime("+30 min", strtotime($time, strtotime($date))));

                if (isset($hotelAddress)) {
                    $s->arrival()
                        ->noDate()
                        ->address($hotelAddress);
                }
            }

            $date = $this->http->FindSingleNode(".//span[normalize-space()='Depart from']/following-sibling::div/span[@data-test='spanTransferFlightDate'][1]",
                $root);

            if ($date) {
                $s = $r->addSegment();
                $time = $this->http->FindSingleNode(".//span[normalize-space()='Depart from']/following-sibling::div/span[@data-test='spanTransferFlightTime'][1]",
                    $root);
                $s->arrival()
                    ->code($this->http->FindSingleNode(".//span[normalize-space()='Depart from']/following-sibling::span[@data-test='spanTransferFlightCity'][1]",
                        $root, false, "/\(([A-Z]{3})\)/"))
                    ->date(strtotime("-2h", strtotime($time, strtotime($date))));

                if (isset($hotelAddress)) {
                    $s->departure()
                        ->noDate()
                        ->address($hotelAddress);
                }
            }
            $this->logger->info('Parsed transfer:');
            $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
        }

        // Transportation - Rental
        $xpath = "//h2[@data-test='titleTransportationHeading']/ancestor::div[@data-test='divIncludedTransportations'][1][contains(.,'Rental Car Reservation')]";
        $rentalNodes = $this->http->XPath->query($xpath);

        foreach ($rentalNodes as $root) {
            $this->logger->info("Rental", ['Header' => 4]);

            $r = $this->itinerariesMaster->add()->rental();

            if ($type == 'canceled') {
                $r->general()->cancelled();
            }

            if ($date = $this->http->FindSingleNode("//span[@data-test='spanBookingDate']")) {
                $r->general()->date2($date);
            }
            $r->ota()->confirmation($otaConf, 'Confirmation Number');
            $r->general()->travellers($pax, true);
            $r->general()
                ->confirmation($this->http->FindSingleNode(".//p[starts-with(normalize-space(),'Rental Car Confirmation Number:')]/span",
                    $root));
            $r->car()
                ->model($this->http->FindSingleNode(".//ul[@data-test='listMakeModelAndTransmissionType']/li[1]",
                    $root))
                ->image($this->http->FindSingleNode(".//div[contains(@class,'car-image')]/img/@src", $root))
                ->type($this->http->FindSingleNode(".//h3[@data-test='headingCarCategoryName']", $root));
            $r->extra()->company($this->http->FindSingleNode(".//div[contains(@class,'car-attr')]/img/@alt", $root), true);
            $pickup = array_map("trim",
                explode('Time:', $this->http->FindSingleNode(".//h3[@data-test='headingPickUpDateAndTime']", $root)));

            if (count($pickup) === 2) {
                $r->pickup()->date(strtotime($pickup[1], strtotime($pickup[0])));
            }
            $r->pickup()->location($this->http->FindSingleNode(".//p[@data-test='paragraphPickUpLocation']", $root, false)
                ?? $this->http->FindSingleNode(".//p[@data-test='paragraphPickUpLocation']/following-sibling::p[@class='address-line-no-translation']", $root, false));
            $dropoff = array_map("trim",
                explode('Time:', $this->http->FindSingleNode(".//h3[@data-test='headingDropOffDateAndTime']", $root)));

            if (count($dropoff) === 2) {
                $r->dropoff()->date(strtotime($dropoff[1], strtotime($dropoff[0])));
            }
            $dropOff = $this->http->FindSingleNode(".//p[@data-test='paragraphDropOffLocation']", $root);

            if (empty($dropOff)) {
                $dropOff = $this->http->FindSingleNode(".//p[@class='address-line-no-translation']", $root);
            }

            if (empty($dropOff)) {
                $dropOff = $this->http->FindSingleNode(".//span[@data-test='spanDropOffLocation']", $root);
            }

            $r->dropoff()->location($dropOff);

            $this->logger->info('Parsed rental:');
            $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
        }
    }

    private function parseCruise($type)
    {
        $this->logger->notice(__METHOD__);

        $xpath = "//div[contains(@class,'accordion-header no-padding bordered')][count(.//p[@data-test='paragraphItineraryArrivalTime' or @data-test='paragraphItineraryDepartureTime'][not(normalize-space()='—')])>0]";
        $nodesSailings = $this->http->XPath->query($xpath);
        $this->logger->debug("Found {$nodesSailings->length} sailingItinerary");
        // An error occurred while processing your request. Please try again
        if ($nodesSailings->length === 0 && ($error = $this->http->FindPreg('/"message":"(An error occurred while processing your request. Please try again)"/'))) {
            $this->logger->error($error);

            return;
        }

        if ($error = $this->http->FindSingleNode("//p[contains(text(),'The itinerary is currently not available.')]")) {
            $this->logger->error($error);

            return;
        }

        $confCTB = $this->http->FindSingleNode("//h3[@data-test='bookingConfirmationNumber']", null, false,
            "/Confirmation Number: (\w+)$/");

        if (!$confCTB) {
            $confCTB = $this->http->FindSingleNode("//span[@data-test='spanCostcoTravelConfirmationNumber']/following-sibling::span",
                null,
                false, "/^(\w+)$/");
        }

        if ($confCTB && !$this->http->FindSingleNode("(//span[@data-test='cruiseDepartureDate'])[1]")) {
            $this->logger->error('Skip: date empty');

            return;
        }
        $this->logger->info("Parse {$type} itinerary # {$confCTB}", ['Header' => 3]);
        $r = $this->itinerariesMaster->add()->cruise();
        $r->ota()->confirmation($confCTB, 'Confirmation Number');

        if ($type == 'canceled') {
            $r->general()->cancelled();
        }

        $conf = $this->http->FindSingleNode("(/p[@data-test='paragraphCruiselineConfirmation']|//span[@data-test='confirmationNumber'])[1]");
        $congDesc = '';

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("(//p[@data-test='paragraphCruiselineConfirmation']/span|//h3[@data-test='cruiseLineConfirmationNumber']/span)[1]");
            $congDesc = $this->http->FindSingleNode("(//p[@data-test='paragraphCruiselineConfirmation']|//h3[@data-test='cruiseLineConfirmationNumber'])[1]",
                null, false, "/^(.+): \w+$/");
        }

        if (empty($conf)) {
            $conf = $this->http->FindSingleNode("//h3[contains(@class,'vendor-confimation-label')]/span[contains(@class,'vendor-confimation-number')]");
            $congDesc = $this->http->FindSingleNode("//h3[contains(@class,'vendor-confimation-label')]/span[@class='OneLinkNoTx']");
        }

        $r->general()
            ->confirmation($conf, $congDesc)
            ->travellers(array_map("beautifulName",
                $this->http->FindNodes("//span[starts-with(@data-test,'spanPassangerInfo_') or starts-with(@data-test,'spanLeadPassangerInfo_')]")),
                false);

        $accounts = array_unique($this->http->FindNodes(
            "//h3[@data-test='headingNumberOfTravelers']/following-sibling::div//div[@data-test='divKnownTravelerNumber']/span[@class='OneLinkNoTx']", null, '/^\w+$/'));
        $r->program()->accounts($accounts, false);

        $r->price()
            ->cost(PriceHelper::cost($this->http->FindSingleNode("//ul/li[1][normalize-space()='Total']/following-sibling::li/span[@data-test='spanCruisePackageSummaryAmount']",
                null, false, "/\b(\d[\d.,]+)/")), false, true)
            ->tax(PriceHelper::cost($this->http->FindSingleNode("//ul/li[1][normalize-space()='Total']/following-sibling::li/span[@data-test='spanTaxesandFeesSummaryAmount']",
                null, false, "/\b(\d[\d.,]+)/")), false, true)
            ->total(PriceHelper::cost($this->http->FindSingleNode("//ul/li[1][normalize-space()='Total']/following-sibling::li/span[@data-test='spanTotalPriceSummaryAmount']",
                null, false, "/\b(\d[\d.,]+)/")))
            ->currency($this->http->FindSingleNode("//ul/li[1][normalize-space()='Total']/following-sibling::li/span[@data-test='spanTotalPriceSummaryAmount']",
                null, false, "/^(.)\b(?:\d[\d.,]+)$/u"));

        $r->setClass($this->http->FindSingleNode("//p[@data-test='paragraphCategory']", null, false,
            "/Category: (.+)/"), true, true);
        $r->details()
            ->description($this->http->FindSingleNode("(//span[@data-test='labelCruiseTitle'])[1]"))
            ->ship($this->http->FindSingleNode("(//img[@data-test='imageCruiseLineLogo']/@alt)[1]"))
            ->deck($this->http->FindSingleNode("//p[@data-test='paragraphDeckName']"), true, true)
            ->room($this->http->FindSingleNode("//p[@data-test='paragraphStateroomnumber']", null, false,
                "/room: (\w+)/"), true, true);

        //$dateDep = strtotime($this->http->FindSingleNode("//span[@data-test='cruiseDepartureDate']"), false);
        $isBoth = 0;
        $i = 0;

        foreach ($nodesSailings as $root) {
            if ($this->http->FindSingleNode(".//p[@data-test='paragraphItineraryArrivalTime'][normalize-space()='——' or normalize-space()='—']", $root)
                && $this->http->FindSingleNode(".//p[@data-test='paragraphItineraryDepartureTime'][normalize-space()='——' or normalize-space()='—']", $root)
                && $this->http->FindSingleNode(".//p[@data-test='paragraphItineraryPort']", $root)) {
                $this->logger->error("Skip empty date");

                continue;
            }

            if ($isBoth === 0 || $isBoth === 1 || $isBoth === 2) {
                $s = $r->addSegment();
                $isBoth = 0;
            }
            $dateItem = $this->http->FindSingleNode(".//p[@data-test='paragraphItineraryDay']", $root);
            //$this->logger->debug("date: " . $dateItem);
            $date = strtotime($dateItem);
            $s->setName($this->http->FindSingleNode(".//p[@data-test='paragraphItineraryPort']", $root));

            if ($time = $this->http->FindSingleNode(".//p[@data-test='paragraphItineraryArrivalTime'][not(normalize-space()='——') and not(normalize-space()='—')]",
                $root, false, '/\d+:\d+\s*[ap]m/i')
            ) {
                $this->logger->debug("date: " . date("Y-m-d", $date) . ", time: " . $time);
                $s->setAshore(strtotime($time, $date));

                if ($i > 0) {
                    $isBoth++;
                }
            }

            if ($time = $this->http->FindSingleNode(".//p[@data-test='paragraphItineraryDepartureTime'][not(normalize-space()='——') and not(normalize-space()='—')]",
                $root, false, '/\d+:\d+\s*[ap]m/i')
            ) {
                $this->logger->debug("date: " . date("Y-m-d", $date) . ", time: " . $time);
                $s->setAboard(strtotime($time, $date));

                if ($i > 0) {
                    $isBoth++;
                }
            }

            $i++;
        }
        $this->logger->info('Parsed cruise:');
        $this->logger->info(var_export($r->toArray(), true), ['pre' => true]);
    }

    private function copySeleniumCookies($selenium, $curl)
    {
        $this->logger->notice(__METHOD__);

        $cookies = $selenium->driver->manage()->getCookies();

        foreach ($cookies as $cookie) {
            $curl->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
        }
    }

    private function openhttp()
    {
        $this->logger->notice(__METHOD__);

        if (isset($this->http)) {
            return;
        }

        $this->http = new HttpBrowser("none", new httpr());
        $this->http->brotherBrowser($this->http);
        $this->http->setUserAgent("curl/7.88.1");
    }
}
