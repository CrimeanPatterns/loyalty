<?php

use AwardWallet\Engine\ProxyList;

class TAccountCheckerEurostar extends TAccountChecker
{
    use DateTimeTools;
    use ProxyList;
    use SeleniumCheckerHelper;

    private $history = [];

    private $headers = [
        'Accept'       => '*/*',
        'content-type' => "application/json",
    ];

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
    }

    public function IsLoggedIn()
    {
        if (empty($this->State['accessToken'])) {
            return false;
        }

        if ($this->loginSuccessful()) {
            return true;
        }

        return false;
    }

    public static function FormatBalance($fields, $properties)
    {
        if (
            isset($properties['SubAccountCode'])
            && (strstr($properties['SubAccountCode'], "eurostarVoucher"))
            && isset($properties['Currency'])
        ) {
            return formatFullBalance($fields['Balance'], $fields['ProviderCode'], $properties['Currency'] . "%0.2f");
        }

        return parent::FormatBalance($fields, $properties);
    }

    public function LoadLoginForm()
    {
        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('Please enter a valid email address', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->removeCookies();
        unset($this->State['accessToken']);
        $this->http->GetURL("https://accounts.eurostar.com/uk-en");

        if ($this->http->currentUrl() != 'https://www.eurostar.com/customer-dashboard/en?market=uk-en') {
            return $this->checkErrors();
        }

        $this->seleniumAuth();

        return true;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // We’re in the process of moving our loyalty members over to our new rewards club: Club Eurostar.
        // While we do this, you won’t be able to log in and our Customer Care teams won’t be able to access your account.
        if (
            $this->http->currentUrl() == 'https://accounts.eurostar.com/uk-en/coming-soon'
            && !$this->http->FindSingleNode("//div[@id='root']", null, false)
        ) {
            throw new CheckException("We're in the process of moving our loyalty members over to our new rewards club: Club Eurostar. While we do this, you won't be able to log in and our Customer Care teams won't be able to access your account.", ACCOUNT_PROVIDER_ERROR);
        }

        if ($message = $this->http->FindSingleNode('
                //p[contains(text(), "We’re upgrading our booking system. Please come back on Sunday, once we’ve finished the upgrade.")]
                | //strong[contains(text(), "Looks like something went wrong!")]
            ')
        ) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        if (!empty($this->State['accessToken']) && $this->loginSuccessful()) {
            return true;
        }

        /*
        $message = $this->auth->message ?? $this->auth->description ?? null;
        $this->logger->error("[Error]: '{$message}'");

        if ($message) {
//            if ($message == 'server.error') {
//                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
//            }
            if ($message == 'Your account has been closed') {
                throw new CheckException("Sorry, this account doesn't exist anymore.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'The credentials are invalid') {
                throw new CheckException("Sorry, we don't recognise that username or password.", ACCOUNT_INVALID_PASSWORD);
            }

            if ($message == 'Request to Webtask exceeded allowed execution time') {
                throw new CheckException("Sorry, something went wrong. Please try again later.", ACCOUNT_PROVIDER_ERROR);
            }

            if (
                $message == 'This login attempt has been blocked because the password you\'re using was previously disclosed through a data breach (not in this application). Please reset your password.'
                || $message == 'Your account has been blocked'
            ) {
                throw new CheckException("Your account has been blocked, please use the 'Forgotten your password' link to reset your account.", ACCOUNT_LOCKOUT);
            }
        }// if ($message)

        if ($message === '' && isset($this->auth->name, $this->auth->fromSandbox) && $this->auth->fromSandbox == true && $this->auth->name == 'Error') {
            throw new CheckException("Sorry, something went wrong. Please try again later.", ACCOUNT_PROVIDER_ERROR);
        }
        */
        $response = $this->http->JsonLog();
        $message = $response->errors[0]->message ?? null;

        if ($message) {
            $this->logger->error("[Error]: '{$message}'");

            if (strstr($response->errors[0]->message, 'Unable to find a profile for customer with ID')) {
                throw new CheckException("Oops, something went wrong. We're sorry, it seems something has gone wrong, if the same errors occurs please contact us on 02 400 67 31", ACCOUNT_PROVIDER_ERROR);
            }

            if (strstr($response->errors[0]->message, "Cannot read properties of null (reading 'profile')")) {
                throw new CheckException("Oops, something went wrong. We're sorry, it seems something has gone wrong, if the same errors occurs please contact us.", ACCOUNT_PROVIDER_ERROR);
            }

            $this->DebugInfo = $message;

            return false;
        }

        if ($message = $this->http->FindSingleNode('//p[contains(text(), "To access your Eurostar account you’ll need to set a new password.")]')) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        $response = $this->http->JsonLog(null, 3, false, 'pointsToNextTier');
        // Balance - Club Eurostar points
        if (!$this->SetBalance($response->data->customer->profile->loyalty->points ?? null)) {
            if (isset($response->data->customer->profile) && $response->data->customer->profile->loyalty === null) {
                $this->SetWarning(self::NOT_MEMBER_MSG);
            }
        }
        // Name
        $this->SetProperty("Name", beautifulName(($response->data->customer->firstName ?? null) . ' ' . ($response->data->customer->lastName ?? null)));
        // Number - Membership no.
        $this->SetProperty('Number', $response->data->customer->profile->loyalty->membershipNumber ?? null);
        // Tier
        $this->SetProperty('Tier', $response->data->customer->profile->loyalty->tier->name ?? null);
        // On 11 Jun 2018 your new membership
        $tierToDate = $response->data->customer->profile->loyalty->membershipEndDateTime ?? null;

        if ($tierToDate) {
            $this->SetProperty('MembershipEndDate', date('d M Y', strtotime($tierToDate)));
        }
        // PointsToUpgrade - Points required to to upgrade to
        $pointsToNextTier = $response->data->customer->profile->loyalty->tier->pointsToNextTier ?? null;
        $this->SetProperty('PointsToUpgrade', $pointsToNextTier);
        // buy ... return journeys or reach XXX points by 04 May 2019
        $pointsForTierRetention = $response->data->customer->profile->loyalty->tier->tripsToRemainInTier ?? null;
        $this->SetProperty('PointsToRemain', $pointsForTierRetention);

        // Expiration date  // refs #13698
        if ($this->Balance > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->history = $this->ParseHistory();

            foreach ($this->history as $transaction) {
                if (stristr($transaction["Description"], "Points migrated") || $transaction["Points"] <= 0) {
                    $this->logger->notice("[{$transaction["Date"]}]: Skip transaction, {$transaction["Description"]} / {$transaction["Points"]}");

                    continue;
                }
                // Last Activity
                $this->SetProperty("LastActivity", date("d M Y", $transaction["Date"]));
                $this->SetExpirationDate(strtotime("+2 year", $transaction["Date"]));

                break;
            }// foreach ($this->history as $transaction)
        }
    }

    public function GetHistoryColumns()
    {
        return [
            "Date"        => "PostingDate",
            "Description" => "Description",
            "Booking #"   => "Info",
            "Points"      => "Miles",
        ];
    }

    public function ParseHistory($startDate = 0)
    {
        $this->http->FilterHTML = false;
        $this->logger->debug('[History start date: ' . (isset($startDate) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $result = [];

        if (!empty($this->history)) {
            return $this->history;
        }

        $this->http->PostURL("https://site-api.eurostar.com/customer", '{"operationName":"identityLoyaltyPointsStatement","variables":{"page":1,"size":20,"language":"EN"},"query":"query identityLoyaltyPointsStatement($page: Int, $size: Int, $language: Language) {\n  identityLoyaltyPointsStatement(page: $page, size: $size, language: $language) {\n    pagination {\n      currentPage\n      totalItems\n      totalPages\n      __typename\n    }\n    items {\n      createdAt\n      points\n      bookingReference\n      description\n      __typename\n    }\n    __typename\n  }\n}"}', $this->headers);

        $result = array_merge($result, $this->ParsePageHistory($startDate));

        return $result;
    }

    public function ParsePageHistory($startDate)
    {
        $result = [];
        $response = $this->http->JsonLog();

        if (empty($response->data->identityLoyaltyPointsStatement->items)) {
            return $result;
        }

        $this->logger->debug("Total " . count($response->data->identityLoyaltyPointsStatement->items) . " activity rows were found");

        foreach ($response->data->identityLoyaltyPointsStatement->items as $activity) {
            $dateStr = $activity->createdAt;
            $postDate = strtotime($dateStr, false);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");

                break;
            }
            $result[] = [
                'Date'        => $postDate,
                'Description' => $activity->description,
                'Booking #'   => $activity->bookingReference,
                'Points'      => $activity->points,
            ];
        }

        return $result;
    }

    public function ParseItineraries()
    {
        // upcoming booking
        $this->http->PostURL("https://site-api.eurostar.com/customer", '{"operationName":"identityBookings","variables":{"bookingSearchType":"UPCOMING_BOOKINGS","page":1,"size":10},"query":"query identityBookings($bookingSearchType: BookingSearchType, $page: Int, $size: Int) {\n  identityBookings(\n    bookingSearchType: $bookingSearchType\n    page: $page\n    size: $size\n  ) {\n    items {\n      origin\n      destination\n      outboundDateTime\n      returnDateTime\n      outboundDirect\n      inboundDirect\n      outboundServiceClass\n      returnServiceClass\n      reference\n      isEligibleForUpgradeWithPoints\n      lastName\n      __typename\n    }\n    pagination {\n      totalItems\n      totalPages\n      currentPage\n      __typename\n    }\n    __typename\n  }\n}"}', $this->headers);
        $response = $this->http->JsonLog();
        $bookings = $response->data->identityBookings->items ?? [];

        foreach ($bookings as $item) {
            $this->fetchItinerary($item);
        }

        // past booking
        $pastBookings = null;

        if ($this->ParsePastIts) {
            $this->logger->notice("Past bookings");
            $this->http->PostURL("https://site-api.eurostar.com/customer", '{"operationName":"identityBookings","variables":{"bookingSearchType":"PAST_BOOKINGS","page":1,"size":10},"query":"query identityBookings($bookingSearchType: BookingSearchType, $page: Int, $size: Int) {\n  identityBookings(\n    bookingSearchType: $bookingSearchType\n    page: $page\n    size: $size\n  ) {\n    items {\n      origin\n      destination\n      outboundDateTime\n      returnDateTime\n      outboundDirect\n      inboundDirect\n      outboundServiceClass\n      returnServiceClass\n      reference\n      isEligibleForUpgradeWithPoints\n      lastName\n      __typename\n    }\n    pagination {\n      totalItems\n      totalPages\n      currentPage\n      __typename\n    }\n    __typename\n  }\n}"}', $this->headers);
            $response = $this->http->JsonLog();
            $pastBookings = $response->data->identityBookings->items ?? [];

            foreach ($pastBookings as $item) {
                $this->fetchItinerary($item);
            }
        }

        if (
            $bookings === [] && (!$this->ParsePastIts || $pastBookings === [])
        ) {
            $this->itinerariesMaster->setNoItineraries(true);

            return [];
        }

        return [];
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo" => [
                "Caption"  => "Confirmation #",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
            "LastName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.flysas.com/us-en/managemybooking";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $data = [
            'reference' => $arFields['ConfNo'],
            'lastName'  => $arFields['LastName'],
        ];
        $this->fetchItinerary((object) $data);

        return null;
    }

    private function fetchItinerary($item): void
    {
        $this->http->GetURL("https://www.eurostar.com/customer-dashboard/en/booking?pnr={$item->reference}&surname=" . urlencode($item->lastName));
        $accessToken = $this->http->FindPreg('/"accessToken":\s*"(.+?)"/');
        $headers = [
            'Accept'        => '*/*',
            'Authorization' => "Bearer $accessToken",
            'Content-Type'  => 'application/json',
        ];
        $this->http->PostURL('https://api.prod.eurostar.com/gateway',
            '{"operationName":"bookingBySessionWithApi","variables":{"bookingLoginToken":"' . $accessToken . '","reference":"' . $item->reference . '"},"query":"query bookingBySessionWithApi($bookingLoginToken: String!, $reference: String!) {\n  bookingBySession(bookingLoginToken: $bookingLoginToken, reference: $reference) {\n    ...bookingFields\n    outbound {\n      ...legFields\n      __typename\n    }\n    inbound {\n      ...legFields\n      __typename\n    }\n    passengers {\n      ...passengerFields\n      ...advancePassengerInformation\n      __typename\n    }\n    __typename\n  }\n}\n\nfragment bookingFields on Booking {\n  reference\n  isPackage\n  isHomogeneous\n  isLoyalty\n  isWheelchair\n  locale\n  ancillaryProduct {\n    products {\n      type\n      itemId\n      name\n      description\n      priceWithCurrency\n      __typename\n    }\n    __typename\n  }\n  additionalDetails {\n    key\n    value\n    itemRef\n    __typename\n  }\n  outboundOrigin {\n    uic\n    name\n    shortName\n    __typename\n  }\n  inboundOrigin {\n    uic\n    name\n    shortName\n    __typename\n  }\n  outboundDestination {\n    uic\n    name\n    shortName\n    __typename\n  }\n  inboundDestination {\n    uic\n    name\n    shortName\n    __typename\n  }\n  payments(excludePayout: true, excludeNonPublic: true, onlySuccessful: true) {\n    currency\n    method\n    externalReference\n    transactionTimestamp\n    amountWithCurrency\n    __typename\n  }\n  paymentRefundMethod\n  totalPriceWithCurrency\n  cancellable\n  cancelledTimestamp\n  confirmedTimestamp\n  salesChannelCode\n  isGroupBooking\n  hasBeenSoldViaThirdPartySalesChannel\n  isPackage\n  isHomogeneous\n  currency\n  productFamilies {\n    code\n    __typename\n  }\n  hotels {\n    checkInDate\n    hotelAddress\n    hotelId\n    hotelName\n    itemRef\n    jacTravelRef\n    numberOfNights\n    hotelInfo {\n      image\n      region\n      regionId\n      guestCount\n      telephone\n      starRating\n      rooms {\n        roomType\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n  __typename\n}\n\nfragment legFields on Leg {\n  isThroughFare\n  passengers: meals {\n    mealCode: code\n    mealTitle: name\n    passenger {\n      id\n      firstName\n      lastName\n      type\n      __typename\n    }\n    __typename\n  }\n  direction\n  timing {\n    isoDate\n    date\n    departureDate\n    departs\n    arrivalDate\n    arrives\n    duration\n    __typename\n  }\n  cancelled\n  classOfAccommodation {\n    code\n    name\n    __typename\n  }\n  origin {\n    uic\n    city\n    country\n    countryCode\n    name\n    shortName\n    __typename\n  }\n  destination {\n    uic\n    city\n    country\n    countryCode\n    name\n    shortName\n    __typename\n  }\n  serviceType {\n    code\n    name\n    __typename\n  }\n  serviceName\n  classOfAccommodation {\n    code\n    name\n    __typename\n  }\n  meals {\n    code\n    name\n    passenger {\n      id\n      __typename\n    }\n    __typename\n  }\n  equipmentType\n  products {\n    confirmedTimestamp\n    canBeExchangedByCustomer\n    canBeRefundedByCustomer\n    canBeRefundedByAgent\n    canBeUpgradedByCustomer\n    canChangeMealByCustomer\n    canChangeSeatByCustomer\n    canBeForcedRefundedToVoucherByCustomer\n    canBeCancelledByCustomer\n    isCheckedIn\n    exchangeOverrideReason\n    currency\n    priceWithCurrency\n    ticketNumber\n    isCancelled\n    itemRef\n    exchanged\n    inventoryClass\n    passenger {\n      id\n      lastName\n      firstName\n      type\n      disabilityType\n      __typename\n    }\n    passengerId\n    seat {\n      seatNumber\n      carriage\n      __typename\n    }\n    code\n    tariffDetails {\n      tariffCode\n      __typename\n    }\n    productFamily {\n      name\n      code\n      __typename\n    }\n    __typename\n  }\n  cancelled\n  __typename\n}\n\nfragment passengerFields on Passenger {\n  unformattedPhoneNumber {\n    countryCode\n    number\n    __typename\n  }\n  email\n  id\n  uuid\n  firstName\n  lastName\n  birthDate\n  loyaltyNumber\n  infant {\n    firstName\n    lastName\n    __typename\n  }\n  disabilityType\n  type\n  __typename\n}\n\nfragment advancePassengerInformation on Passenger {\n  travelDocumentsRequired(channel: WEB) {\n    required\n    travelDocumentType\n    __typename\n  }\n  travelDocumentsCaptured(bookingReference: $reference) {\n    travelDocumentType\n    captured\n    __typename\n  }\n  __typename\n}\n"}',
            $headers);
        $detail = $this->http->JsonLog();
        $this->parseItinerary($detail);
    }

    private function parseItinerary($detail): void
    {
        $this->logger->notice(__METHOD__);
        $t = $this->itinerariesMaster->createTrain();
        $data = $detail->data->bookingBySession;
        $this->logger->info("Parse Train #{$data->reference}", ['Header' => 3]);
        $t->general()->confirmation($data->reference);

        $t->price()->total($data->totalPriceWithCurrency);
        $t->price()->currency($data->currency);

        foreach ($data->passengers as $passenger) {
            $t->general()->traveller("$passenger->firstName $passenger->lastName");

            if (isset($passenger->loyaltyNumber)) {
                $t->program()->account($passenger->loyaltyNumber, false);
            }
        }

        $bounds = array_merge($data->outbound, $data->inbound);

        foreach ($bounds as $bound) {
            $s = $t->addSegment();
            $s->extra()->number($bound->serviceName);
            //$s->extra()->type($outbound->origin);
            $s->departure()->name("{$bound->origin->name}, {$bound->origin->country}");
            //$s->departure()->code($bound->origin->countryCode);
            $s->departure()->date2("{$bound->timing->departureDate} {$bound->timing->departs}");
            $s->arrival()->name("{$bound->destination->name}, {$bound->destination->country}");
            //$s->arrival()->code($bound->destination->countryCode);
            $s->arrival()->date2("{$bound->timing->arrivalDate} {$bound->timing->arrives}");
            $h = floor($bound->timing->duration / 60);
            $m = $bound->timing->duration % 60;

            if ($h > 0) {
                $s->extra()->duration("$h hr $m min");
            } else {
                $s->extra()->duration("$m min");
            }

            $carNumbers = [];

            foreach ($bound->products as $product) {
                $carNumbers[] = $product->seat->carriage;
                $s->extra()->car(join(',', array_filter(array_unique($carNumbers))));
                $s->extra()->seat($product->seat->seatNumber);
                $s->extra()->bookingCode($product->inventoryClass);
            }
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($t->toArray(), true), ['pre' => true]);
    }

    private function loginSuccessful()
    {
        $this->logger->notice(__METHOD__);

        $headers = $this->headers + [
            'Authorization' => "Bearer {$this->State['accessToken']}",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://site-api.eurostar.com/customer", '{"operationName":"CUSTOMER","variables":{},"query":"query CUSTOMER {\n  customer {\n    id\n    customerId\n    firstName\n    lastName\n    email\n    phoneNumber\n    secondContact {\n      name\n      passphrase\n      __typename\n    }\n    address {\n      street\n      city\n      postCode\n      country\n      __typename\n    }\n    profile {\n      customerId\n      nationality\n      dateOfBirth\n      salesforceId\n      savedCardsReference\n      loyalty {\n        membershipNumber\n        registrationDateTime\n        membershipStartDateTime\n        membershipEndDateTime\n        points\n        status\n        numberOfTrips\n        tier {\n          name\n          pointsToRemainInTier\n          pointsToNextTier\n          tripsToRemainInTier\n          tripsToNextTier\n          code\n          __typename\n        }\n        __typename\n      }\n      savedCards {\n        additionalData {\n          cardBin\n          __typename\n        }\n        alias\n        aliasType\n        billingAddress {\n          houseNumberOrName\n          street\n          city\n          stateOrProvince\n          postalCode\n          country\n          __typename\n        }\n        card {\n          number\n          holderName\n          expiryMonth\n          expiryYear\n          __typename\n        }\n        contractTypes\n        creationDate\n        firstPspReference\n        name\n        paymentMethodVariant\n        recurringDetailReference\n        variant\n        __typename\n      }\n      __typename\n    }\n    __typename\n  }\n}"}', $headers, 20);
        $this->http->RetryCount = 2;
        $response = $this->http->JsonLog();
        $email = $response->data->customer->email ?? null;
        $this->logger->debug("[Email]: {$email}");

        if (
            $email
            && strtolower($email) == strtolower($this->AccountFields['Login'])
        ) {
            $this->headers = $headers;

            return true;
        }

        return false;
    }

    private function seleniumAuth(): bool
    {
        $this->logger->notice(__METHOD__);
        $selenium = clone $this;
        $this->http->brotherBrowser($selenium->http);
        $retry = false;
        $logout = null;

        try {
            $this->logger->notice("Running Selenium...");
            $selenium->UseSelenium();

            $selenium->useFirefox(SeleniumFinderRequest::FIREFOX_84);
            $selenium->setKeepProfile(true);
            $selenium->disableImages();
            $selenium->http->saveScreenshots = true;

            $selenium->http->start();
            $selenium->Start();
            $selenium->http->GetURL('https://www.eurostar.com/login/en?market=uk-en');

            $loginInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "email"] | //div[@style="justify-content: center;"]//input[@autocomplete="username"]'), 7);
            $passInput = $selenium->waitForElement(WebDriverBy::xpath('//input[@name = "password"] | //div[@style="justify-content: center;"]//input[@autocomplete="current-password"]'), 7);
            $btn = $selenium->waitForElement(WebDriverBy::xpath('//div[@style="justify-content: center;"]//button[contains(., "Log in")]'), 0);
            $this->savePageToLogs($selenium);

            if (!$loginInput || !$passInput || !$btn) {
                return $this->checkErrors();
            }

            $this->logger->debug("set credentials");
            $loginInput->sendKeys($this->AccountFields['Login']);
            $passInput->sendKeys($this->AccountFields['Pass']);

            $this->savePageToLogs($selenium);

            $this->logger->debug("click 'Sign In'");
            $btn->click();

            $res = $selenium->waitForElement(WebDriverBy::xpath('
                //span[contains(text(), "Log out")]
                | //h1[contains(text(), "Please reset your password")]
                | //div[@data-testid="snackbar-error-message"]
                | //div[@role="alert"]//p
            '), 7);
            $this->savePageToLogs($selenium);

            // one more login attempt, it helps, provider bug fix
            $this->logger->debug("[Current URL]: {$selenium->http->currentUrl()}");

            if ($res && $this->http->FindSingleNode('//span[contains(text(), "Log out")]')) {
                $this->logger->notice("success");
            } elseif ($res && $res->getText() == 'Please reset your password') {
                throw new CheckException("Eurostar Frequent Traveler website is asking you to reset your password, until you do so we would not be able to retrieve your account information.", ACCOUNT_PROVIDER_ERROR);
            } elseif (
                $res
                && in_array($res->getText(), [
                    "Sorry, we don't recognise that username or password.",
                ])
            ) {
                throw new CheckException($res->getText(), ACCOUNT_INVALID_PASSWORD);
            } elseif (
                $res
                && in_array($res->getText(), [
                    "Sorry, something went wrong. Please try again later.",
                ])
            ) {
                throw new CheckException($res->getText(), ACCOUNT_PROVIDER_ERROR);
            } elseif ($res) {
                $this->DebugInfo = $res->getText();
            }

            $cookies = $selenium->driver->manage()->getCookies();

            foreach ($cookies as $cookie) {
                if (strstr($cookie['name'], 'accessToken')) {
                    $this->State['accessToken'] = $cookie['value'];
                }

                $this->http->setCookie($cookie['name'], $cookie['value'], $cookie['domain'], $cookie['path'], $cookie['expiry'] ?? null);
            }

            $this->logger->debug("[Current Selenium URL]: {$selenium->http->currentUrl()}");
        } catch (NoSuchDriverException $e) {
            $this->logger->error("Exception: " . $e->getMessage());
            $retry = true;
        } catch (UnrecognizedExceptionException $e) {
            $msg = $e->getMessage();
            $this->logger->error("Exception: <pre>$msg</pre>");

            if (str_contains($msg, 'because another element <iframe src="https://geo.captcha-delivery.com/')) {
                $this->DebugInfo = 'Geo captcha';
                $retry = true;
            }
        } finally {
            // close Selenium browser
            $selenium->http->cleanup(); //todo

            // retries
            if ($retry && ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                throw new CheckRetryNeededException(3, 0);
            }
        }

        return isset($logout);
    }
}
