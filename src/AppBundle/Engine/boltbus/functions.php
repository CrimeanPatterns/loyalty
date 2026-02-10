<?php

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Common\Parsing\Html;

class TAccountCheckerBoltbus extends TAccountChecker
{
    use PriceTools;

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.boltbus.com/';
        $arg['SuccessURL'] = 'https://www.boltbus.com/account/';

        return $arg;
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException("Please enter a valid e-mail address.", ACCOUNT_INVALID_PASSWORD);
        }

        // $this->http->GetURL("https://www.boltbus.com/");
        $this->http->GetURL("https://store.boltbus.com/fare-finder?redirect=https://www.boltbus.com/bus-ticket-search");

        if (!$this->http->ParseForm("loyalty")) {
            return $this->checkErrors();
        }
        $this->http->FormURL = "https://store.boltbus.com/login";
        $this->http->SetInputValue('username', $this->AccountFields['Login']);
        $this->http->SetInputValue("credentials", $this->AccountFields['Pass']);

        return true;
    }

    public function Login()
    {
        if (!$this->http->PostForm()) {
            return $this->checkErrors();
        }

        if ($this->http->FindSingleNode("//button[@id = 'signout']")) {
            return true;
        }

        if ($message = $this->http->FindSingleNode('//div[contains(@class, "login-error active")]')) {
            $this->logger->error("[Error]: {$message}");
            // Invalid username or password
            if (
                strstr($message, 'Invalid username/password')
                || strstr($message, "Unauthorized access. This could indicate that the user is not authorized or the session has expired")
            ) {
                throw new CheckException($message, ACCOUNT_INVALID_PASSWORD);
            }

            if (
                strstr($message, 'An unexpected error occurred with the Quasar API')
            ) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }

            return false;
        }

        return $this->checkErrors();
    }

    public function Parse()
    {
        // First Name
        $this->SetProperty("Name", $this->http->FindPreg("/<p[^>]*>Hi\s+([^,<]+)/ims"));
        // Balance - Points
        $balance = $this->http->FindSingleNode('//p[contains(text(), ", you have")]', null, true, "/have\s*(.+)\s+POINT/");

        $this->http->GetURL("https://store.boltbus.com/view");
        // Full Name
        $name = Html::cleanXMLValue($this->http->FindSingleNode("//input[@id = 'firstName']/@value")
            . ' ' . $this->http->FindSingleNode("//input[@id = 'lastName']/@value"));

        if (strlen($name) > 2) {
            $this->SetProperty("Name", beautifulName($name));
        }

        $this->http->GetURL("https://store.boltbus.com/account");
        // Balance - Points
        $this->SetBalance($this->http->FindSingleNode('//div[contains(@class, "nav-wrapper")]//em[@id = "points"]'));
        // Rewards
        $rewards = $this->http->FindSingleNode('//div[contains(@class, "nav-wrapper")]//em[@id = "rewards"]');
        $this->SetProperty("Rewards", $rewards);

        // AccountID: 842354
        if (
            $this->http->Response['code'] == 500
            && $this->http->FindSingleNode('//p[contains(text(), "There seems to be a problem with your request.")]')
        ) {
            $this->SetBalance($balance);
        }
    }

    public function ParseItineraries()
    {
        $res = [];
        $this->http->GetURL('https://store.boltbus.com/my-trips');

        $tripUrls = $this->http->FindNodes('//button[contains(@data-href, "/receipt?")]/@data-href');
        $tripUrls = array_values(array_unique($tripUrls));
        $this->logger->info(sprintf('found %s trip(s)', count($tripUrls)));

        foreach ($tripUrls as $url) {
            $this->http->NormalizeURL($url);
            $this->http->GetURL($url);
            $res[] = $this->parseItinerary();
        }

        return $res;
    }

    public function GetConfirmationFields()
    {
        return [
            "ConfNo"   => [
                "Caption"  => "Confirmation Number",
                "Type"     => "string",
                "Size"     => 30,
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

    public function ConfirmationNumberURL($arFields)
    {
        return 'https://www.boltbus.com/retrieve-booking/';
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        $postData = [
            'confirmationNumber' => $arFields['ConfNo'],
            'lastName'           => $arFields['LastName'],
        ];
        $this->http->PostURL('https://store.boltbus.com/retrieve/confirmation', $postData);
        $resp = $this->http->JsonLog(null, 3, true);

        if (
            isset($resp['status'])
            && $resp['status'] === 'NOT_FOUND'
            && isset($resp['message'])
            && $resp['message'] === 'Unable to read message from server.'
        ) {
            return 'Sorry, we were unable to retrieve your itinerary and boarding pass.';
        }
        $hash = $resp['hash'] ?? null;

        if (!$hash) {
            $this->sendNotification("failed to retrieve itinerary by conf #");

            return null;
        }

        $itinUrl = "https://store.boltbus.com/receipt?o={$arFields['ConfNo']}&h={$hash}";
        $this->http->GetURL($itinUrl);

        if ($msg = $this->http->FindSingleNode('//p[contains(text(), "There seems to be a problem with your request.")]')) {
            return $msg;
        }

        $it = $this->parseItinerary();

        return null;
    }

    private function checkErrors()
    {
        $this->logger->notice(__METHOD__);

        if ($message = $this->http->FindSingleNode("//td[contains(text(), 'Boltbus.com is currently undergoing maintenance to improve our site.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // BoltBus.com will be unavailable
        if ($message = $this->http->FindSingleNode("//div[contains(text(), 'BoltBus.com will be unavailable')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }
        // Server is in maintenance mode.
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Server is in maintenance mode.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        // permanently redirect
        if ($this->http->currentUrl() == 'https://www.boltbus.com/Error.aspx' && $this->http->Response['code'] == 302) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    private function parseItinerary()
    {
        $this->logger->notice(__METHOD__);
        $res = ['Kind' => 'T'];
        $res['TripCategory'] = TRIP_CATEGORY_BUS;
        // RecordLocator
        $res['RecordLocator'] = $this->http->FindSingleNode('//span[contains(@class, "booking-number")]');
        $this->logger->info(sprintf('Parse Itinerary #%s', $res['RecordLocator']), ['Header' => 3]);
        // Passengers
        $res['Passengers'] = $this->http->FindNodes('//ul[contains(@class, "passenger-list")]/li');
        $res['Passengers'] = array_map(function ($s) {
            return beautifulName($s);
        }, $res['Passengers']);
        // Total
        $totalStr = $this->http->FindSingleNode('//span[text() = "Total"]/ancestor::div[1]/following-sibling::div[1]');
        $total = $this->http->FindPreg('/([\d.,]+)/', false, $totalStr);
        $res['TotalCharge'] = PriceHelper::cost($total);
        // Currency
        $res['Currency'] = $this->currency($totalStr);
        // TripSegments
        $res['TripSegments'] = [];
        $segments = $this->http->XPath->query('//div[contains(@class, "trip-details-container")]');
        $this->logger->info(sprintf('found %s segments', $segments->length));

        foreach ($segments as $node) {
            $ts = [];
            // DepDate
            $date = $this->http->FindSingleNode('.//span[contains(@class, "trip_date")]', $node);
            $date = strtotime($date);
            $time1 = $this->http->FindSingleNode('.//span[contains(@class, "from")]/span[1]', $node, true, '/(\d+:\d+\s*(?:am|pm))/i');
            $ts['DepDate'] = strtotime($time1, $date);
            // ArrDate
            $time2 = $this->http->FindSingleNode('.//span[contains(@class, "to")]/span[1]', $node, false, '/(\d+:\d+\s*(?:am|pm))/i');
            $ts['ArrDate'] = strtotime($time2, $date);
            // DepCode
            $ts['DepCode'] = TRIP_CODE_UNKNOWN;
            // DepName
            $depStr = $this->http->FindSingleNode('.//span[contains(@class, "from")]', $node);
            $depStr = $this->http->FindPreg('/^(.+?),/', false, $depStr) ?: $depStr;
            $ts['DepName'] = $this->http->FindPreg('/^\s*(.+?)s*\(/', false, $depStr) ?: $depStr;
            // DepAddress
            $ts['DepAddress'] = $this->http->FindPreg('/\((.+?)\)/', false, $depStr);
            // ArrCode
            $ts['ArrCode'] = TRIP_CODE_UNKNOWN;
            // ArrName
            $arrStr = $this->http->FindSingleNode('.//span[contains(@class, "to")]', $node);
            $arrStr = $this->http->FindPreg('/^(.+?),/', false, $arrStr) ?: $arrStr;
            $ts['ArrName'] = $this->http->FindPreg('/^\s*(.+?)s*\(/', false, $arrStr) ?: $arrStr;
            // ArrAddress
            $ts['ArrAddress'] = $this->http->FindPreg('/\((.+?)\)/', false, $arrStr);
            $res['TripSegments'][] = $ts;
        }

        return $res;
    }
}
