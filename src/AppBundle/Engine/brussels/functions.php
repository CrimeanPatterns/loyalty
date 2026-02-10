<?php

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parsing\Html;
use AwardWallet\Engine\ProxyList;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\ItineraryArrays\AirTrip;

class TAccountCheckerBrussels extends TAccountChecker
{
    use ProxyList;

    protected $endHistory = false;

    // for history
    private $customer = [];
    private $transactionsType = [];
    private $totalHistorySize = 0;

    public function InitBrowser()
    {
        parent::InitBrowser();
        $this->KeepState = true;
        $this->http->setDefaultHeader('Accept-Encoding', 'gzip, deflate, br');
        $this->http->setHttp2(true);

        $this->http->SetProxy($this->proxyReCaptchaIt7());
    }

    public function LoadLoginForm()
    {
        $this->http->removeCookies();

        if (filter_var($this->AccountFields['Login'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CheckException('The E-mail address or Password inserted are not valid', ACCOUNT_INVALID_PASSWORD);
        }

        $this->http->RetryCount = 1;
        $this->http->GetURL('https://www.brusselsairlines.com/com/Default.aspx');
        $this->http->RetryCount = 2;

        if (!$this->http->ParseForm('form1')) {
            if (strstr($this->http->Error, 'Network error 28 - Connection timed out after')) {
                throw new CheckRetryNeededException(2, 10);
            }

            return $this->checkErrors();
        }
        $this->http->setCookie("COOKIE_SUPPORT", 'true', "loop.brusselsairlines.com");
//        $data = '{"login":"'.$this->AccountFields['Login'].'","password":"'.$this->AccountFields['Pass'].'","rememberMe":false,"channel":"MP"}';
        $data = [
            "login"      => $this->AccountFields['Login'],
            "password"   => $this->AccountFields['Pass'],
            "rememberMe" => false,
            "channel"    => "MP",
        ];
        $headers = [
            "Content-Type"     => "application/json; charset=UTF-8;",
            "Accept"           => "application/json, text/javascript, */*; q=0.01",
            "X-Requested-With" => "XMLHttpRequest",
            "Accept-Encoding"  => "gzip, deflate, br",
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://loop.brusselsairlines.com/?cwaLogin=true", json_encode($data), $headers);
        $this->http->RetryCount = 2;

//        $this->http->SetInputValue('ctl00$loginBox$txtEmail', $this->AccountFields['Login']);
//        $this->http->SetInputValue('ctl00$loginBox$txtPassword', $this->AccountFields['Pass']);

        return true;
    }

    public function GetRedirectParams($targetURL = null)
    {
        $arg = parent::GetRedirectParams($targetURL);
        $arg['CookieURL'] = 'https://www.brusselsairlines.com/com/my-profile/profile.aspx';
        $arg['SuccessURL'] = 'https://www.brusselsairlines.com/com/my-profile/profile.aspx';

        return $arg;
    }

    public function checkErrors()
    {
        $this->logger->notice(__METHOD__);
        // Maintenance
        if ($message = $this->http->FindSingleNode("//p[contains(text(), 'Our website is currently under maintenance.')]")) {
            throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
        }

        if ($this->http->FindSingleNode("//p[contains(text(), 'Our Brussels Airlines website is currently down for maintenance. Rest assured however that you can still:')]")) {
            throw new CheckException("Dear Brussels Airlines guest, our Brussels Airlines website is currently down for maintenance.", ACCOUNT_PROVIDER_ERROR);
        }
        // Internal Server Error
        if ($this->http->FindSingleNode('
                //title[contains(text(), "Internal Server Error")]
                | //h1[contains(text(), "Service Unavailable - Zero size object")]
            ')
            || $this->http->FindPreg("/An error occurred while processing your request\.<p>/")
        ) {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        return false;
    }

    public function Login()
    {
        $response = $this->http->JsonLog();

        if (isset($response->success, $response->token->access_token) && $response->success == true) {
            $this->http->setCookie("_authtoken", $response->token->access_token, "loop.brusselsairlines.com");
            $this->http->setDefaultHeader("Authorization", "Bearer {$response->token->access_token}");

            return true;
        }
        // Service is unavailable. Please try again later or contact the Contact Center for further assistance.
        if (isset($response->success, $response->globalErrorCode) && $response->success == false && $response->globalErrorCode == 'ERROR_SERVICE_UNAVAILABLE') {
            throw new CheckRetryNeededException(2, 15, "Service is unavailable. Please try again later or contact the Contact Center for further assistance.");
        }

        if (isset($response->success, $response->errors) && $response->success == false) {
            $error = $response->errors[0]->code;
            $this->logger->error("[Error]: {$error}");

            if ($error == 'validation.field.invalid') {
                throw new CheckException('The E-mail address or Password inserted are not valid', ACCOUNT_INVALID_PASSWORD);
            }

            if ($error == 'validation.customer.account.blocked') {
                throw new CheckException('Your account has been blocked', ACCOUNT_LOCKOUT);
            }
        }
        $error = $response->globalErrorCode ?? null;
        $this->logger->error("[Error]: {$error}");

        if ($error == 'validation.customer.link.with.guardian.required') {
            throw new CheckException('Our young LOOP members under 16 years old must be linked to an adult LOOP member. How? If you know a member of your family who is a LOOP member: ask him/her to log in to his LOOP profile and link his/her account to yours. ', ACCOUNT_PROVIDER_ERROR);
        }
        // todo: remove it in the future
        // We are currently working to improve your LOOP experience!
        if ($this->http->Response['code'] == 403 && $this->http->GetURL("https://www.brusselsairlines.com/com/loop/about-loop.aspx")
            && $this->http->FindPreg("/<html xmlns=\"http:\/\/www\.w3\.org\/1999\/xhtml\" lang=\"EN\">/")) {
            $this->http->GetURL("https://sb.monetate.net/img/1/533/1562546.js");

            if ($message = $this->http->FindPreg("/We are currently working to improve your LOOP experience!/")) {
                throw new CheckException($message, ACCOUNT_PROVIDER_ERROR);
            }
        }// if ($this->http->Response['code'] == 403 && $this->http->GetURL("https://www.brusselsairlines.com/com/loop/about-loop.aspx"))

        // The E-mail address or Password inserted are not valid
//        if (in_array($this->AccountFields['Login'], ['debrahba@un.org', 'dhaworth@thehaworths.com', 'czavou01@gmail.com', 'pieter_beerten1@hotmail.com']) && $this->http->Response['code'] == 400)
//            throw new CheckException('The E-mail address or Password inserted are not valid', ACCOUNT_INVALID_PASSWORD);
        // The E-mail address or Password inserted are not valid
//        if (strstr($this->AccountFields['Login'], '-') && $this->http->Response['code'] == 400)
//            throw new CheckException('The E-mail address or Password inserted are not valid', ACCOUNT_INVALID_PASSWORD);

        return $this->checkErrors();
    }

    public function Parse()
    {
        $this->http->GetURL('https://loop.brusselsairlines.com/clm-api/services/cwa/customer');
        $response = $this->http->JsonLog(null, 3, true);

        // Name
        $firstName = ArrayVal($response, 'firstName');
        $lastName = ArrayVal($response, 'lastName');

        if ($firstName && $lastName) {
            $this->SetProperty('Name', beautifulName("$firstName $lastName"));
        }
        // Number
        $this->SetProperty('Number', ArrayVal($response['identifier'][0], 'identifierNo'));

        // refs #11829
        $this->http->GetURL('https://loop.brusselsairlines.com/clm-api/services/cwa/account?_=' . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        // Pending LOOPs
        $this->SetProperty('PendingLOOPs', ArrayVal($response, 'nonAirlineThreshold'));
        // Single flights taken
        $this->SetProperty('SingleFlightsTaken', ArrayVal($response, 'airlineSegmentsThreshold'));
        // Balance - YOUR AVAILABLE LOOPs
        $this->SetBalance(ArrayVal($response, 'totalPointBalance'));

        // for history
        $customers = ArrayVal($response, 'customer', []);

        foreach ($customers as $customer) {
            $this->customer[$customer['customerId']] = $customer['firstName'];
        }

        $this->http->GetURL('https://loop.brusselsairlines.com/clm-api/services/cwa/account/balance?_=' . date("UB"));
        $response = $this->http->JsonLog(null, 3, true);
        // YOUR AVAILABLE LOOPs
        $this->SetBalance(ArrayVal($response, 'totalPointBalance'));
        // Expiration date
        if (ArrayVal($response, 'pointBalance')) {
            $expFromJSON = ArrayVal($response['pointBalance'][0], 'expirationDate');

            if ($expFromJSON) {
                $expFromJSON = $this->ModifyDateFormat($expFromJSON);
                $this->http->Log("Expiration date from JSON: {$expFromJSON}");
//                if ($expFromJSON = strtotime($expFromJSON))
//                    $this->SetExpirationDate($expFromJSON);
            }// if ($expFromJSON)
        }// if (ArrayVal($response, 'pointBalance'))

        // Expiration date  // refs #11829
        if ($this->Balance > 0) {
            $this->logger->info('Expiration date', ['Header' => 3]);
            $this->getExpDate();

            if (!isset($this->Properties['LastActivity']) && $this->totalHistorySize > 10) {
                $this->getExpDate(1);
            }
        }// if ($this->Balance > 0)
    }

    public function getExpDate($pageNo = 0)
    {
        $this->http->GetURL("https://loop.brusselsairlines.com/clm-api/services/cwa/account/transactions?pageNr={$pageNo}");
        $response = $this->http->JsonLog(null, 0, true);
        $items = ArrayVal($response, 'items', []);

        if ($pageNo == 0) {
            $this->totalHistorySize = ArrayVal($response, 'totalSize', 0);
        }
        $this->logger->debug("Total " . count($items) . " items were found (totalSize: {$this->totalHistorySize})");

        foreach ($items as $item) {
            $itemType = ArrayVal($item, 'type');
            $date = ArrayVal($item, 'date');
            $dateExp = $this->ModifyDateFormat($date);

            if ($itemType == 'AI') {
                if (!isset($exp) || ($exp < strtotime($dateExp))) {
                    $exp = strtotime($dateExp);
                    // Last Activity
                    $this->SetProperty("LastActivity", $date);
                    // Expiration date
                    $this->SetExpirationDate(strtotime("+12 month", $exp));
                }// if (!isset($exp) || $exp < strtotime($date))
            }// if ($itemType == 'AI')
        }// foreach ($items as $item)
    }

    public function ParseItineraries()
    {
        $this->http->GetURL('https://www.brusselsairlines.com/en-be/my-profile/upcoming-flights.aspx');

        $nodes = $this->http->FindNodes("//table[@class = 'results']//tr[td]/td[6]/a/@href");
        $this->logger->debug('Total ' . count($nodes) . ' were found nodes');

        $result = [];

        if (count($nodes) > 0) {
            for ($i = 0; $i < count($nodes); $i++) {
                $this->http->NormalizeURL($nodes[$i]);
                $this->http->GetURL($nodes[$i]);

                $result[] = $this->ParseItinerary();
            }// for ($i = 0; $i < count($nodes); $i++)
        }// if (count($nodes) > 0)

        return $result;
    }

    public function GetConfirmationFields()
    {
        return [
            "LastName" => [
                "Type"     => "string",
                "Size"     => 80,
                "Value"    => $this->GetUserField('LastName'),
                "Required" => true,
            ],
            "ConfNo" => [
                "Caption"  => "Booking reference",
                "Type"     => "string",
                "Size"     => 20,
                "Required" => true,
            ],
        ];
    }

    public function ConfirmationNumberURL($arFields)
    {
        return "https://tdp.brusselsairlines.com/BEL/ReservationSearch.do";
//        return "https://www.brusselsairlines.com/en-be/";
    }

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $this->http->SetProxy($this->proxyDOP());
        $this->http->GetURL($this->ConfirmationNumberURL($arFields));

        if (!$this->http->ParseForm("ReservationRetrieveRemoteForm")) {
            $this->sendNotification("brussels - failed to retrieve itinerary by conf #");

            return null;
        }
        $this->http->SetInputValue('remoteSearchCriteria.travelerLastName', $arFields["LastName"]);
        $this->http->SetInputValue('bookingReference', $arFields["ConfNo"]);
        $this->http->SetInputValue('ajaxAction', 'true');
        $this->http->FormURL = 'https://tdp.brusselsairlines.com/BEL/ReservationRetrieveRemote.do';

        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $this->http->RetryCount = 0;

        if (!$this->http->PostForm() && $this->http->Response['code'] !== 405) {
            return null;
        }
        $this->http->RetryCount = 2;
        $this->increaseTimeLimit();
        $this->distil();

        if ($this->http->FindPreg("/location.replace\(\"ApplicationStartAction.do\"\);/")) {
            $this->http->GetURL("https://tdp.brusselsairlines.com/BEL/ApplicationStartAction.do");
            $this->http->FormURL = $formURL;
            $this->http->Form = $form;

            if (!$this->http->PostForm()) {
                return null;
            }
        }

        if ($redirect = $this->http->FindPreg("/redirect: \"([^\"]+)/")) {
            $this->logger->debug(">>> Redirect to -> {$redirect}");
            $this->http->NormalizeURL($redirect);
            $this->http->GetURL($redirect);
        }// if ($redirect = $this->http->FindPreg("/redirect: \"([^\"]+)/"))

        if ($message = $this->http->FindPreg("/(We cannot find your booking. Please check your details and try again.)/ims")) {
            return $message;
        }

        $it = $this->ParseItineraryConfirmationNumberInternal();

        return null;
    }

    public function GetHistoryColumns()
    {
        return [
            "Activity date"   => "PostingDate",
            "Processing date" => "Info.Date",
            "Name"            => "Info",
            "Activity"        => "Description",
            "Type"            => "Info",
            "LOOPs"           => "Miles",
        ];
    }

    public function ParseHistory($startDate = null)
    {
        $result = [];
        $this->logger->debug('[History start date: ' . ((isset($startDate)) ? date('Y/m/d H:i:s', $startDate) : 'all') . ']');
        $startTimer = microtime(true);

        // get all transactions type
        $this->http->GetURL("https://loop.brusselsairlines.com/clm-api/services/cwa/dictionary/list/?dictionaryName=TRANSACTION_TYPES");
        $response = $this->http->JsonLog(null, false);

        if (isset($response[0]->dictionaryItem) && is_array($response[0]->dictionaryItem)) {
            foreach ($response[0]->dictionaryItem as $dictionaryItem) {
                $this->transactionsType[$dictionaryItem->code] = $dictionaryItem->value;
            }
        }// foreach ($response('0')->dictionaryItem as $dictionaryItem)
        $this->logger->debug("Total " . count($this->transactionsType) . " transactions types were found");

        $page = 0;

        do {
            $this->logger->debug("[Page: {$page}]");
            $this->http->GetURL("https://loop.brusselsairlines.com/clm-api/services/cwa/account/transactions?pageNr={$page}");
            $page++;
            $startIndex = sizeof($result);
            $result = array_merge($result, $this->ParsePageHistory($startIndex, $startDate));

            $sizeof = sizeof($result);
            $this->logger->debug("{$sizeof} of {$this->totalHistorySize}");
        } while (
            $page < 20
            && $sizeof < $this->totalHistorySize
            && !$this->endHistory
        );

        $this->getTime($startTimer);

        return $result;
    }

    public function ParsePageHistory($startIndex, $startDate)
    {
        $result = [];
        $response = $this->http->JsonLog(null, false, true);
        $items = ArrayVal($response, 'items', []);
        $this->logger->debug("Total " . count($items) . " items were found");

        foreach ($items as $item) {
            $date = ArrayVal($item, 'date');
            $dateStr = $this->ModifyDateFormat($date);
            $postDate = strtotime($dateStr);

            if (isset($startDate) && $postDate < $startDate) {
                $this->logger->notice("break at date {$dateStr} ($postDate)");
                $this->endHistory = true;

                continue;
            }// if (isset($startDate) && $postDate < $startDate)
            $type = ArrayVal($item, 'type');
            $points = ArrayVal($item, 'points');
            $customerId = ArrayVal($item, 'customerId');

            switch ($type) {
                case 'AI':
                    $airlineSummary = ArrayVal($item, 'airlineSummary');
                    $marketingFlightNo = ArrayVal($airlineSummary, 'marketingFlightNo');
                    $origin = ArrayVal($airlineSummary, 'origin');
                    $destination = ArrayVal($airlineSummary, 'destination');
                    $description = "Participant earned {$points} LOOPs with flight #{$marketingFlightNo} {$origin} to {$destination}.";

                    break;

                case 'AR':
                    $airlineSummary = ArrayVal($item, 'airlineSummary');
                    $marketingFlightNo = ArrayVal($airlineSummary, 'marketingFlightNo');
                    $origin = ArrayVal($airlineSummary, 'origin');
                    $destination = ArrayVal($airlineSummary, 'destination');
                    $description = "Participant spent {$points} LOOPs with flight #{$marketingFlightNo} {$origin} to {$destination}.";

                    break;
                // Post-merge evaluate
                case 'AM':
                case 'BD':
                case 'A6':
                    $description = $this->transactionsType[$type] ?? $type;

                    break;

                case 'CL':
                    $description = "Participant completed the profile.";

                    break;

                case 'CR':
                    $description = "Participant joined LOOP.";

                    break;

                case 'DF':
                case 'DT':
                    $description = "Default transaction type";

                    break;

                case 'ER':
                    $description = "Participant earned {$points} LOOPs.";

                    break;

                case 'MM':
                    $description = "Invitation";

                    break;

                case 'PE':
                    $description = "{$points} ACTIVE LOOPs expired.";

                    break;

                case 'PC':
                    $description = "Points correction {$points} LOOPs.";

                    break;

                case 'RF':
                    $description = "Redemption refund";

                    break;

                case 'WE':
                    $description = "{$points} INACTIVE LOOPs expired.";

                    break;

                default:
                    $description = '';
                    $this->sendNotification("brussels - refs #13448. Unknown transaction type was found: {$type}");

                    break;
            }// switch ($type)

            $result[$startIndex]['Activity date'] = $postDate;
            $processingDate = $this->ModifyDateFormat(ArrayVal($item, 'processDate'));
            $result[$startIndex]['Processing date'] = strtotime($processingDate);
            $result[$startIndex]['Name'] = $this->customer[$customerId] ?? '-';
            $result[$startIndex]['Activity'] = $description;
            $result[$startIndex]['Type'] = $this->transactionsType[$type] ?? $type;
            $result[$startIndex]['LOOPs'] = $points;
            $startIndex++;
        }// foreach ($items as $item)

        return $result;
    }

    private function ParseItinerary()
    {
        /** @var AirTrip $itinerary */
        $result = [];
        $result["Kind"] = "T";
        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode("//h3[contains(text(), 'Booking reference')]", null, true, '/reference\s*([A-Z\d]{6})/');
        // TotalCharge
        $result['TotalCharge'] = $this->http->FindSingleNode("//h6[contains(text(), 'Booking total')]/span", null, true, "/([\d\.\,]+)/ims");
        // Currency
        $result['Currency'] = $this->http->FindSingleNode("//h6[contains(text(), 'Booking total')]/span", null, true, "/([A-Z]+)/");
        // Tax
        $result['Tax'] = $this->http->FindSingleNode("//span[contains(text(), 'Taxes')]/following-sibling::span", null, true, "/([\d\.\,]+)/ims");
        // Passengers
        $passName = $this->http->FindNodes("//ul[@class = 'tickets']/li/text()[1]");

        if (is_array($passName) && count($passName) > 0) {
            $result['Passengers'] = beautifulName(implode(', ', $passName));
        }

        // Air Trip Segments

        $segments = $this->http->XPath->query("//div[contains(@class, 'itinerary_detail')]");
        $this->logger->debug("Total {$segments->length} segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = [];
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//li[contains(text(), 'Flight:')]/span", $segments->item($i));
            // date and codes
            $header = explode('-', $this->http->FindSingleNode("h4[1]", $segments->item($i)));

            if (count($header) != 3) {
                $this->http->Log("Invalid header <pre>" . var_export($header, true) . "</pre>", false);

                continue;
            }
            $date = Html::cleanXMLValue($header[0]);
            // DepCode
            preg_match('/_Sector' . $i . ':\s*\[("[^\"]+\",){3}\"([^\"]+)/ims', $this->http->Response['body'], $m);

            if (isset($m[2])) {
                $segment['DepCode'] = $m[2];
            }
            $segment['DepName'] = Html::cleanXMLValue($header[1]);
            // ArrCode
            preg_match('/_Sector' . $i . ':\s*\[("[^\"]+\",){4}\"([^\"]+)/ims', $this->http->Response['body'], $m);

            if (isset($m[2])) {
                $segment['ArrCode'] = $m[2];
            }
            $segment['ArrName'] = Html::cleanXMLValue($header[2]);
            // DepDate
            $depTime = $this->http->FindSingleNode(".//li[contains(text(), 'Departure:')]/span", $segments->item($i));
            $this->logger->debug("Dep time: $date $depTime");
            $depDateTime = strtotime($date . '  ' . $depTime);

            if ($depDateTime) {
                $segment['DepDate'] = $depDateTime;
            }
            // ArrDate
            $arrTime = $this->http->FindSingleNode(".//li[contains(text(), 'Arrival:')]/span", $segments->item($i));
            $this->logger->debug("Arr time: $date $arrTime");
            $arrDateTime = strtotime($date . ' ' . $arrTime);

            if ($arrDateTime) {
                $segment['ArrDate'] = $arrDateTime;
            }
            // Cabin
            $segment['Cabin'] = $this->http->FindSingleNode(".//li[contains(text(), 'Cabin:')]/span", $segments->item($i));
            // BookingClass
            $segment['BookingClass'] = $this->http->FindSingleNode(".//li[contains(text(), 'Booking class:')]", $segments->item($i), true, '/\:\s*(.+)/');

            $result['TripSegments'][] = $segment;
        }

        return $result;
    }

    private function ParseItineraryConfirmationNumberInternal()
    {
        /** @var AirTrip $itinerary */
        $result = [];
        $result["Kind"] = "T";
        // ConfirmationNumber
        $result['RecordLocator'] = $this->http->FindSingleNode("//td[@class = 'colConfirmNum']/div");
        // Passengers
        $result['Passengers'] = array_map(function ($item) {
            return beautifulName($item);
        }, $this->http->FindNodes("//div[@class = 'passengerInfoBlock']//td[@class = 'colName']/div"));
        // TicketNumbers
        $tickets = array_map(function ($item) {
            return $item;
        }, $this->http->FindNodes("//td[contains(text(), 'Ticket number')]/following-sibling::td[1]/span"));

        if (!empty($tickets)) {
            $result['TicketNumbers'] = $tickets;
        }
        // AccountNumbers
        $numbers = array_map(function ($item) {
            return $item;
        }, $this->http->FindNodes("//td[contains(text(), 'Frequent flyer')]/following-sibling::td[1]", null, "/\-\s*(.+)/"));

        if (!empty($numbers)) {
            $result['AccountNumbers'] = $numbers;
        }

        // Air Trip Segments

        $segments = $this->http->XPath->query("//div[contains(@class, 'flightLeg')]//tr[td[@class = \"colDeparture\"]]");
        $this->logger->debug("Total " . $segments->length . " segments were found");

        for ($i = 0; $i < $segments->length; $i++) {
            $segment = [];
            // FlightNumber
            $segment['FlightNumber'] = $this->http->FindSingleNode(".//span[@class = 'flightNum']/a/text()[last()]", $segments->item($i), true, "#^\w{2}\s*(\d+)$#");
            // AirlineName
            $segment['AirlineName'] = $this->http->FindSingleNode(".//span[@class = 'flightNum']/a/text()[last()]", $segments->item($i), true, "#^(\w{2})\s*\d+$#");
            // Aircraft
            $segment['Aircraft'] = $this->http->FindSingleNode("following-sibling::tr[1]//span[@class = 'plane']", $segments->item($i));
            // DepCode
            $segment['DepCode'] = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'orig' or @class = 'transf']", $segments->item($i), true, "/\(([A-Z]{3})\)/");
            // DepName
            $segment['DepName'] = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'orig' or @class = 'transf']", $segments->item($i));
            // ArrCode
            $segment['ArrCode'] = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'dest' or @class = 'transf']", $segments->item($i), true, "/\(([A-Z]{3})\)/");
            // ArrName
            $segment['ArrName'] = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'dest' or @class = 'transf']", $segments->item($i));
            // DepDate
            $depDate = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'date']", $segments->item($i), true, "/\,\s(.+)/");
            $depDay = $this->http->FindSingleNode('.//td[@class = \'colDeparture\']//span[@class = \'date\']', $segments->item($i), true, "/([^\,]+)/");
            $depTime = $this->http->FindSingleNode(".//td[@class = 'colDeparture']//span[@class = 'time']", $segments->item($i), true);
            $depDateTime = strtotime($depDate . ' ' . date('Y') . '  ' . $depTime);
            $this->logger->debug("Dep time: $depDate $depTime / $depDateTime");
            // fixed year
            $depWeekNum = WeekTranslate::number1($depDay);
            $depDateTime = EmailDateHelper::parseDateUsingWeekDay($depDateTime, $depWeekNum);
            $this->logger->debug("Dep time: $depDate $depTime / $depDateTime");

            if ($depDateTime) {
                $segment['DepDate'] = $depDateTime;
            }
            // ArrDate
            $arrDate = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'date']", $segments->item($i), true, "/\,\s(.+)/");
            $arrDay = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'date']", $segments->item($i), true, "/([^\,]+)/");
            $arrTime = $this->http->FindSingleNode(".//td[@class = 'colArrival']//span[@class = 'time']", $segments->item($i), true);
            $arrDateTime = strtotime($arrDate . ' ' . date('Y') . ' ' . $arrTime);
            $this->logger->debug("Arr time: $arrDate $arrTime / $arrDateTime");
            // fixed year
            $arrWeekNum = WeekTranslate::number1($arrDay);
            $arrDateTime = EmailDateHelper::parseDateUsingWeekDay($arrDateTime, $arrWeekNum);
            $this->logger->debug("Arr time: $arrDate $arrTime / $arrDateTime");

            if ($arrDateTime) {
                $segment['ArrDate'] = $arrDateTime;
            }
            // AirlineName
            $segment['Operator'] = $this->http->FindSingleNode(".//td[@class = 'colFlight']//span[contains(@class, 'operatedAirline')]", $segments->item($i), true, '/operated\s*by\s*(.+)/');
            // Stops
            $stops = $this->http->FindSingleNode(".//td[@class = 'colDetails']//span[contains(@class, 'stops')]", $segments->item($i));

            if ($stops == 'Non stop') {
                $segment['Stops'] = 0;
            } else {
                $segment['Stops'] = $this->http->FindPreg('/\d+/', false, $stops);
            }
            // Cabin
            $segment['Cabin'] = $this->http->FindSingleNode(".//td[@class = 'colDetails']//span[@class = 'cabin']", $segments->item($i));
            // BookingClass
            $segment['BookingClass'] = implode('', $this->http->FindNodes(".//td[@class = 'colDetails']//span[@class = 'sellingClass']/text()[last()]", $segments->item($i), '/\((.+)\)/'));

            $result['TripSegments'][] = $segment;
        }
        $this->logger->info('Parsed Itinerary:');
        $this->logger->info(var_export($result, true), ['pre' => true]);

        return $result;
    }

    private function distil($retry = true)
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
            $this->parseGeetestCaptcha($retry);
            $this->http->RetryCount = 2;
        } // if (isset($distilLink))

        if ($this->http->FindSingleNode('//form[@id = "distilCaptchaForm" and contains(@class, "geetest_hard")]/@action')) {
            $this->parseGeetestCaptcha($retry);
        }

        if (!$this->http->ParseForm('distilCaptchaForm')) {
            return false;
        }
        $formURL = $this->http->FormURL;
        $form = $this->http->Form;

        $captcha = $this->parseFunCaptcha($retry);

        if ($captcha !== false) {
            $key = 'fc-token';
        } elseif (($captcha = $this->parseReCaptcha($retry)) !== false) {
            $key = 'g-recaptcha-response';
        } else {
            return false;
        }
        $this->http->FormURL = $formURL;
        $this->http->Form = $form;
        $this->http->SetInputValue($key, $captcha);

        $this->logger->debug(var_export($this->http->Form, true), ["pre" => true]);
        $this->http->RetryCount = 0;
        $this->http->PostForm(["Referer" => $referer]);
        $this->http->RetryCount = 2;
        $this->http->FilterHTML = true;

        return true;
    }

    private function parseGeetestCaptcha($retry = false)
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
        $this->http->brotherBrowser($http2);
        $url = '/distil_r_captcha_challenge';
        $this->http->NormalizeURL($url);
        $http2->PostURL($url, []);
        $challenge = $http2->FindPreg('/^(.+?);/');

        if (!$challenge) {
            return false;
        }

        $recognizer = $this->getCaptchaRecognizer();
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "pageurl"    => $this->http->currentUrl(),
            "proxy"      => $this->http->GetProxy(),
            'api_server' => $apiServer,
            'challenge'  => $challenge,
            'method'     => 'geetest',
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $gt, $parameters, $retry);
        $response = $this->http->JsonLog($captcha, true, true);

        if (empty($response)) {
            $this->logger->error('geetest failed');

            return false;
        }

        $verifyUrl = $this->http->FindSingleNode('//form[@id = "distilCaptchaForm"]/@action');
        $this->http->NormalizeURL($verifyUrl);
        $payload = [
            'dCF_ticket'        => $ticket,
            'geetest_challenge' => $response['geetest_challenge'],
            'geetest_validate'  => $response['geetest_validate'],
            'geetest_seccode'   => $response['geetest_seccode'],
        ];
        $this->http->PostURL($verifyUrl, $payload);

        return true;
    }

    private function parseReCaptcha($retry, $xpath = "//form[@id = 'distilCaptchaForm']//div[@class = 'g-recaptcha']/@data-sitekey")
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode($xpath);
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
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        return $captcha;
    }

    private function parseFunCaptcha($retry = true)
    {
        $this->logger->notice(__METHOD__);
        $key = $this->http->FindSingleNode("//div[@id = 'funcaptcha']/@data-pkey");

        if (!$key) {
            $key = $this->http->FindSingleNode("//form[@id = 'distilCaptchaForm']//script[contains(text(), 'loadFunCaptcha')]", null, true, "/public_key\s*:\s*\"([^\"]+)/");
        }

        if (!$key) {
            return false;
        }
        // watchdog workaround
        $this->increaseTimeLimit(180);

        $recognizer = $this->getCaptchaRecognizer(self::CAPTCHA_RECOGNIZER_RUCAPTCHA);
        $recognizer->RecognizeTimeout = 120;
        $parameters = [
            "method"  => 'funcaptcha',
            "pageurl" => $this->http->currentUrl(),
            "proxy"   => $this->http->GetProxy(),
        ];
        $captcha = $this->recognizeByRuCaptcha($recognizer, $key, $parameters, $retry);

        return $captcha;
    }
}
