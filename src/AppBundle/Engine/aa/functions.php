<?php

// test hook 29
use AwardWallet\Common\Parsing\Html;
use CPNRV3\OAOperatingAirSegment;
use CPNRV3\PNROtherAirSegment;
use LMIV6\LoyaltyMemberInformationV6Service;
use LMIV6\MemberInformationRetrieveRequest;
use LMIV6\MemberInformationRetrieveRequestItem;
use LMRI2\LoyaltyMemberReservationInfoServiceV2Service;
use LMSV4\LoyaltyMemberSecurityService;

class TAccountCheckerAa extends TAccountChecker
{
    // THIS SECTION FOR TEST PURPOSES ONLY

    public const TEST_LOGIN = 'ZNjWKCO';
    public const TEST_NUMBER_PROVIDED_BY_API = '9876543210';
    public const TEST_PASS = '5uJyKet6RiBe';
    public const TEST_LOGIN2 = 'cVr2dukNxZzR9hC3hAk8rMx3YSgK1nOC2BIbA8TT';
    public const TEST_LOGIN3 = 'SGVdsJgNARdV2jo6iHy4qWM3HwnG7qliXyKQeLBJ';

    protected $retry = 0;

    private $firstName = null;
    private $lastName = null;

    private $detailedBookingClass = [
        "A" => "First Class Discounted",
        "B" => "Coach Economy Discounted",
        "C" => "Business Class",
        "D" => "Business Class Discounted",
        "F" => "First Class",
        "H" => "Coach Economy Discounted",
        "J" => "Business Class Premium",
        "K" => "Thrift",
        "L" => "Thrift Discounted",
        "M" => "Coach Economy Discounted",
        "P" => "First Class Premium",
        "Q" => "Coach Economy",
        "R" => "Supersonic",
        "S" => "Standard Class",
        "T" => "Coach Economy Discounted",
        "V" => "Thrift Discounted",
        "W" => "Coach Economy Premium",
        "Y" => "Coach Economy",
    ];

    public function TuneFormFields(&$arFields, $values = null)
    {
        unset($arFields['NotRelated']);
    }

    public function LoadLoginForm()
    {
        $this->ArchiveLogs = true;

        if ($this->isTestEnvironment($this->AccountFields)) {
            return true;
        }

        if ($this->Redirecting) {
            if (ConfigValue(CONFIG_TRAVEL_PLANS)) {
                $container = getSymfonyContainer();
                $host = $container->getParameter("requires_channel") . "://" . $container->getParameter("host");
            } else {
                $host = parse_url(DEBUG_SERVICE_LOCATION, PHP_URL_SCHEME) . '://' . parse_url(DEBUG_SERVICE_LOCATION, PHP_URL_HOST);
            }

            $this->http->PostURL($host . "/aa/get-form", ["loginId" => $this->AccountFields["Login"], "password" => $this->AccountFields["Pass"], "lastName" => $this->AccountFields['Login2']]);
        }

        return true;
    }

    //	function GetRedirectParams($targetURL = NULL) {
    //		$arg = parent::GetRedirectParams($targetURL);
    //		$arg['CookieURL'] = 'https://www.aa.com/login/loginAccess.do?uri=/login/loginAccess.do&previousPage=/myAccount/myAccountAccess.do&continueUrl=/myAccount/myAccountAccess.do';
    //		return $arg;
    //	}
//
    public function ConfirmationNumberURL($arFields)
    {
        return "https://www.aa.com/reservation/findReservationAccess.do";
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
            "FirstName" => [
                "Type"     => "string",
                "Size"     => 40,
                "Value"    => $this->GetUserField('FirstName'),
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

    public function CheckConfirmationNumberInternal($arFields, &$it)
    {
        $it = $this->parseItinerary(strtoupper($arFields['ConfNo']), false);
        $this->http->Log("it: " . var_export($it, true));
        $this->http->Log("fields: " . var_export($arFields, true));
        $match = false;

        if (!empty($it['Passengers'])) {
            if (!empty($arFields['Passengers'])) { // passengers can be supplied as array from email service
                $arFields['Passengers'] = array_map([$this, "normalPassengerName"], $arFields["Passengers"]);
                $it['Passengers'] = array_map([$this, "normalPassengerName"], $it["Passengers"]);
                $this->http->Log("fields passengers: " . var_export($arFields['Passengers'], true));
                $this->http->Log("itinerary passengers: " . var_export($it['Passengers'], true));
                $matches = array_intersect($arFields['Passengers'], $it['Passengers']);

                if (count($matches)) {
                    $match = true;
                } else {
                    // match only by last name, seems enough for emails
                    foreach ($arFields['Passengers'] as $nameFields) {
                        foreach ($it['Passengers'] as $nameIt) {
                            $arr = explode(' ', $nameFields);
                            $lastNameFields = trim(array_pop($arr));
                            $arr = explode(' ', $nameIt);
                            $lastNameIt = trim(array_pop($arr));

                            if (strcasecmp($lastNameFields, $lastNameIt) === 0 || strlen($lastNameFields) > 5 && strlen($lastNameIt) > 5 && (stripos($lastNameFields, $lastNameIt) !== false || stripos($lastNameIt, $lastNameFields) !== false)) {
                                $match = true;

                                break 2;
                            }
                        }
                    }
                }
            }

            if (!$match && !empty($arFields['FirstName'])) {
                foreach ($it['Passengers'] as $passenger) {
                    $names = explode(' ', $passenger);
                    $first = array_shift($names);
                    $last = trim(implode(' ', $names));
                    $this->http->Log("name: $first, $last - {$arFields['FirstName']} {$arFields['LastName']}");
                    // for some reason AA on it's site checks only 10 symbols
                    //					if (strcasecmp(substr($first, 0, 10), substr($arFields['FirstName'], 0, 10)) == 0
                    //					&& strcasecmp(substr($last, 0, 10), substr($arFields['LastName'], 0, 10)) == 0
                    // emailId 2812522 - first name spelled differently (ax - axel)
                    if (strcasecmp(substr($first, 0, 10), substr($arFields['FirstName'], 0, 10)) == 0
                        && (stripos($last, $arFields['LastName']) === 0 || stripos($arFields['LastName'], $last) === 0)
                        || stristr($last, substr($arFields['LastName'], 0, 10))
                            && (stripos($first, $arFields['FirstName']) === 0 || stripos($arFields['FirstName'], $first) === 0)) {
                        $match = true;

                        break;
                    }
                }
            }
        }

        if (!$match) {
            $this->http->Log("no matches by name");
            $it = null;
        }
    }

    public function Login()
    {
        $this->http->Log(__METHOD__);

        if ($this->isTestEnvironment($this->AccountFields)) {
            if ($this->isValidTestCredentials($this->AccountFields)) {
                $this->http->Log("valid test credentials");

                return true;
            } else {
                throw new CheckException("Incorrect password", ACCOUNT_INVALID_PASSWORD);
            }
        }

        if (empty($this->AccountFields['Pass'])) {
            $this->http->Log("empty password, do not validate credentials");

            return true;
        }

        $this->http->Log("validating credentials, pw length: " . strlen($this->AccountFields['Pass']));

        require_once __DIR__ . '/wsdl/lmsv4/LoyaltyMemberSecurityService.php';

        try {
            $service = new LoyaltyMemberSecurityService($this->getWsdlOptions(AA_WSDL_PASSWORD_2017_08_01, null, true), __DIR__ . '/wsdl/LoyaltyMemberSecurityV4.wsdl');
            // refs #11973
            $this->AccountFields['Pass'] = substr($this->AccountFields['Pass'], 0, 16);

            $item = new \LMSV4\ValidateLoyaltyCredentialsRequestItem($this->AccountFields['Pass'], [new \LMSV4\ExtraIdData('LASTNAME', $this->AccountFields['Login2'])], $this->AccountFields['Login'], null);
            $request = new \LMSV4\ValidateLoyaltyCredentialsRequest('AWARDWLT', '4.0', 'LoyaltyMemberSecurity', 'AWARDWLT', $item, 'AWARDWLT');
            $result = @$service->ValidateLoyaltyCredentials($request);
            $this->logLastRequest($service);

            if (/*!empty($result->ResponseStatus->Code) && in_array($result->ResponseStatus->Code, array(11, 12))
        && */ !empty($result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Code)) {
                switch ($result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Code) {
                    case 8007601: // COUNT_LIMIT_HAS_BEEN_EXCEEDED
                        throw new CheckException("Too many incorrect password attempts. Your account has been locked out.", ACCOUNT_LOCKOUT);

                        break;

                    case 8000071: // INCORRECT_PASSWORD
                        throw new CheckException("Incorrect password", ACCOUNT_INVALID_PASSWORD);

                        break;

                    case 8002551: // MEMBER_ACCOUNT_LOGIN_PASSWORD_NOT_FOUND
                    case 8004001: // Member Account Not Found
                        throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);

                        break;

                    case 8000022: // NO_RECORDS_WERE_FOUND_TO_DISPLAY
                        // Password not correct
                        if (isset($result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Message)
                            && strstr($result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Message, 'Password not correct')) {
                            throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
                        }

                        throw new CheckException("Member Account Id cannot have special characters", ACCOUNT_INVALID_PASSWORD);

                        break;

                    case 8110001:
                        // save AA number for login or email based logins
                        if (!empty($result->ValidateLoyaltyCredentialsResult[0]->LoyaltyMemberAccount)) {
                            foreach ($result->ValidateLoyaltyCredentialsResult[0]->LoyaltyMemberAccount as $account) {
                                if ($account->LoyaltyProgramCode == 'ADV') {
                                    $this->http->Log("saved AAAdv number to login");
                                    $this->AccountFields['Login'] = $account->LoyaltyAccountNumber;
                                }
                            }
                        }
                        $this->http->Log("credentials valid");

                        return true;

                        break;

                    default:
                        $this->logger->error("[Error]: {$result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Message}");

                        throw new CheckException($result->ValidateLoyaltyCredentialsResult[0]->ValidateLoyaltyCredentialsStatus->Message, ACCOUNT_INVALID_PASSWORD);
                }
            }
        } catch (SoapFault $e) {
            $this->logger->error("Login exception: " . $e->getMessage());

            if (!empty($service)) {
                $this->logLastRequest($service);
            }

            if ($e->getMessage() == 'Failed to establish a backside connection'
                || $e->getMessage() == 'Could not connect to host'
                || $e->getMessage() == 'Failed to authenticate the user'
            ) {
                throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
            }

            if ($e->getMessage() == 'Failed to process response headers') {
                throw new CheckRetryNeededException(2, 10, self::PROVIDER_ERROR_MSG);
            }
            // SOAP-ERROR: Encoding: string '❼❹❶❺❹\xe2...' is not a valid utf-8 string
            // Password: ❼❹❶❺❹❽❾
            if (strstr($e->getMessage(), 'is not a valid utf-8 string')
                && strstr($e->getMessage(), 'SOAP-ERROR: Encoding: string')) {
                throw new CheckException("Incorrect password", ACCOUNT_INVALID_PASSWORD);
            }
        }

        return false;
    }

    public function Parse()
    {
        $this->logger->notice(__METHOD__);

        if ($this->isTestEnvironment($this->AccountFields)) {
            $this->SetBalance(12345);
            $this->SetProperty('Name', 'Ragnar Petrovich');
            $this->SetProperty('Number', self::TEST_NUMBER_PROVIDED_BY_API);
            $this->SetProperty('Status', 'Gold');

            return true;
        }

        try {
//            require_once __DIR__ . '/wsdl/lmiv3/LoyaltyMemberInformationV3Service.php';
//            $service = new \LMIV3\LoyaltyMemberInformationV3Service($this->getWsdlOptions(AA_WSDL_PASSWORD_2017_08_01, null, "aa-client-prod-2017-08-01"), __DIR__ . '/wsdl/LoyaltyMemberInformationV3.wsdl');
//            $request = new \LMIV3\MemberInformationRetrieveRequest('AWARDWLT', '3.0', 'LoyaltyMemberInformationV3', 'AWARDWLT', [new \LMIV3\MemberInformationRetrieveRequestItem($this->AccountFields['Login'], null)], 'A988', 'A925188', AA_WSDL_AUTHORIZATION_PASSWORD);
            require_once __DIR__ . '/wsdl/lmiv6/LoyaltyMemberInformationV6Service.php';
            $service = new LoyaltyMemberInformationV6Service($this->getWsdlOptions(AA_WSDL_PASSWORD_2017_08_01, 'https://aapartner.esoa.aa.com/LoyaltyMemberInformationService/V6', true), __DIR__ . '/wsdl/LoyaltyMemberInformationV6.wsdl');
            $request = new MemberInformationRetrieveRequest('AWARDWLT', '6.0', 'LoyaltyMemberInformationV6', 'AWARDWLT', [new MemberInformationRetrieveRequestItem($this->AccountFields['Login'], null)], 'A988', 'A925188', AA_WSDL_AUTHORIZATION_PASSWORD);

            $result = @$service->RetrieveLoyaltyMemberInformation($request);
            $this->logLastRequest($service);
        } catch (SoapFault $e) {
            $this->logger->error("Parse exception: " . $e->getMessage());

            if (!empty($service)) {
                $this->logLastRequest($service);
            }

            if (
                $e->getMessage() == 'Connection failure from backend service'
//                $e->getMessage() == 'Failed to establish a backside connection'
//                || $e->getMessage() == 'Could not connect to host'
//                || $e->getMessage() == 'Failed to process response headers'
            ) {
                throw new CheckRetryNeededException(3, 10, self::PROVIDER_ERROR_MSG);
            }

            // AccountID: 5458041
            if (
                strstr($e->getMessage(), "element AAdvantageNumber value '{$this->AccountFields['Login']}' is not a valid instance of the element type")
            ) {
                throw new CheckException("Invalid credentials", ACCOUNT_INVALID_PASSWORD);
            }
        }

        $this->logger->debug("check errors");

        if (!empty($result->ResponseStatus)) {
            $this->logger->debug(var_export($result->ResponseStatus, true), ['pre' => true]);
        }

        if (!empty($result->ResponseStatus->Message) && $result->ResponseStatus->Code === '11' && !empty($result->MemberInformationRetrieveResult[0]->MemberInformationResponseStatus->Message)) {
            throw new CheckException($result->MemberInformationRetrieveResult[0]->MemberInformationResponseStatus->Message);
        }
        // Member Account Id must be less than or equal to 7 characters
        if (!empty($result->ResponseStatus->Message) && $result->ResponseStatus->Code === '12'
            && ((!empty($result->ResponseStatus->Info) && $result->ResponseStatus->Info == 'Member Account Id must be less than or equal to 7 characters') || (isset($result->ResponseStatus->Info[0]) && $result->ResponseStatus->Info[0] == 'Member Account Id must be less than or equal to 7 characters'))) {
            throw new CheckException('Member Account Id must be less than or equal to 7 characters', ACCOUNT_INVALID_PASSWORD);
        }
        // Member Account Id cannot have special characters
        if (!empty($result->ResponseStatus->Message) && $result->ResponseStatus->Code === '12'
            && ((!empty($result->ResponseStatus->Info) && $result->ResponseStatus->Info == 'Member Account Id cannot have special characters')
            || (isset($result->ResponseStatus->Info[0]) && $result->ResponseStatus->Info[0] == 'Member Account Id cannot have special characters'))) {
            throw new CheckException('Member Account Id cannot have special characters', ACCOUNT_INVALID_PASSWORD);
        }
        // Host Connection Failure
        if (!empty($result->MemberInformationRetrieveResult[0]->MemberInformationResponseStatus->Message)
            && $result->MemberInformationRetrieveResult[0]->MemberInformationResponseStatus->Code === '8099995'
            && empty($result->ResponseStatus)
            && empty($result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember)
            && $result->MemberInformationRetrieveResult[0]->MemberInformationResponseStatus->Message == 'Host Connection Failure') {
            throw new CheckException(self::PROVIDER_ERROR_MSG, ACCOUNT_PROVIDER_ERROR);
        }

        $this->logger->debug("parse properties");

        if (!empty($result->ResponseStatus->Message) && $result->ResponseStatus->Code === '0') {
            $this->SetProperty("Number", $result->MemberInformationRetrieveResult[0]->MemberInformationRetrieveRequestItem->AAdvantageNumber);
            $memberSummary = null;

            foreach ($result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->AAdvantageAccountEnrollment->MemberActivitySummary[0]->MemberSummaryDetail as $summaryItem) {
                if ($summaryItem->LoyaltyProgramTimePeriod->LoyaltyProgramTimePeriodName == "Program To Date") {
                    $memberSummary = $summaryItem;

                    break;
                }
            }

            if (!empty($memberSummary)) {
                $this->SetBalance($memberSummary->MemberSummaryByExpiration->ExpiringMileageQuantity);

                if (
                    !empty($memberSummary->MemberSummaryByExpiration->MileageExpirationDate)
                    && ($exp = strtotime($memberSummary->MemberSummaryByExpiration->MileageExpirationDate))
                    && $exp >= time()
                ) {
                    $this->SetExpirationDate($exp);
                }
            }
            // Name
            $this->firstName = $result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->LoyaltyMemberName->FirstName;
            $this->lastName = $result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->LoyaltyMemberName->LastName;
            $this->SetProperty("Name", beautifulName($this->firstName . ' ' . $this->lastName));
            // Last Name    // refs #9903
            $this->SetProperty("LastName", beautifulName($result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->LoyaltyMemberName->LastName));
            $this->http->Log("normal name: " . $this->normalName($this->AccountFields['Login2']));

            if (strcasecmp($this->normalName($result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->LoyaltyMemberName->LastName), $this->normalName($this->AccountFields['Login2'])) != 0) {
                $this->http->Log("names does not match");

                throw new CheckException("Incorrect last name", ACCOUNT_INVALID_PASSWORD);
            }

            // Status
            switch ($result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->AAdvantageAccountEnrollment->EliteStatus->EliteStatusCode) {
                case '':
                    $this->SetProperty("Status", "Member");

                    break;

                case 'G':
                    $this->SetProperty("Status", "Gold");

                    break;

                case 'P':
                    $this->SetProperty("Status", "Platinum");

                    break;

                case 'E':
                    $this->SetProperty("Status", "Executive Platinum");

                    break;

                case 'T':
                    $this->SetProperty("Status", "Platinum Pro");

                    break;

                case 'C':
                    $this->SetProperty("Status", "ConciergeKey");

                    break;

                default:
                    $this->sendNotification("aa. New status - " . $result->MemberInformationRetrieveResult[0]->CustomerLoyaltyMember->AAdvantageAccountEnrollment->EliteStatus->EliteStatusCode);
            }

            return true;
        }

        return false;
    }

    public function ParseItineraries()
    {
        $this->logger->notice(__METHOD__);
        $result = [];

        if ($this->isTestEnvironment($this->AccountFields)) {
            return [];
        }

        require_once __DIR__ . '/wsdl/lmri2/LoyaltyMemberReservationInfoServiceV2Service.php';
        $service = new LoyaltyMemberReservationInfoServiceV2Service($this->getWsdlOptions(null, '/LoyaltyMemberReservationInfoService/V2', true), __DIR__ . '/wsdl/LoyaltyMemberReservationInfoServiceV2.wsdl');
        //		$service = new \LMRI2\LoyaltyMemberReservationInfoServiceV2Service($this->getWsdlOptions(null, 'https://chub.esoa.aa.com/LoyaltyMemberReservationInfoService/V3', true), __DIR__ . '/wsdl/LoyaltyMemberReservationInfoServiceV2.wsdl');

        $item = new LMRI2\customerReservationListRequest();
        $item->asyncProcessMaxThreadCount = 0;
        $item->asyncTimeoutValue = 0;
        $item->maxDurationOfThreadCountAtMax = 0;
        $item->performAsyncProcess = false;
        $item->bookedAacomOrAasegmentOnly = false;
        $item->clientCode = 'AACOM';
        $item->loyaltyCompany = 'AA';
        $item->loyaltyNumber = $this->AccountFields['Login'];
        $item->retrieveLimitedChangePnr = true;
        $item->requestedListCount = 0;

        $list = new LMRI2\RetrieveCustomerReservationList();
        $list->request = $item;

        try {
            $result = $service->RetrieveCustomerReservationList($list);

            if (empty($result)) {
                throw new SoapFault("null_response", "Null response");
            }
        } catch (SoapFault $e) {
            $this->logLastRequest($service);
            //			throw new CheckException("Unknown error", ACCOUNT_ENGINE_ERROR);
        }
        $this->http->Log("RetrieveCustomerReservationList: <pre>" . var_export($result, true) . "</pre>", null, false);

        if (isset($result->return->customerReservationSummaryTable)) {
            $result = array_filter(array_map(function ($info) {
                return $this->parseItinerary($info->recordLocator);
            }, $result->return->customerReservationSummaryTable));
        } else {
            // refs #8459
            if (isset($result->return->asyncProcessCurrentThreadCount, $result->return->roundTripTime, $result->return->contextInfo) && $result->return->contextInfo == '8000022') {
                return $this->noItinerariesArr();
            }

            $result = [];
        }

        return $result;
    }

    public function parseItinerary($recordLocator, $retrieveByConfNo = true)
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(sprintf('Parse Itinerary #%s', $recordLocator), ['Header' => 3]);
//        require_once __DIR__ . '/wsdl/cpnrv3/CustomerPNRV3Service.php';
//        $service = new CPNRV3\CustomerPNRV3Service($this->getWsdlOptions(null, '/CustomerPNRService/V3', true),
//            __DIR__ . '/wsdl/CustomerPNRV3.wsdl');
//        $item = new CPNRV3\RetrieveCustomerPNRDetailsRequestItem($recordLocator);
//        $request = new CPNRV3\RetrieveCustomerPNRDetailsRequest([$item]);
        require_once __DIR__ . '/wsdl/cpnrv5_1/CustomerPNRService.php';
        $service = new CPNRV5_1\CustomerPNRService($this->getWsdlOptions(null, 'https://aapartner.esoa.aa.com/CustomerPNRService/V5_1', true), __DIR__ . '/wsdl/CustomerPNRV5_1.wsdl');
        $item = new CPNRV5_1\RetrieveCustomerPNRDetailsRequestItem($recordLocator);
        $request = new CPNRV5_1\RetrieveCustomerPNRDetailsRequest(false, 'xxx', [$item]);

        try {
            $result = $service->RetrieveCustomerPNRDetails($request);
        } catch (SoapFault $e) {
            $this->http->Log("soapfault: " . $e->getMessage());
            //			$this->http->Log("<pre>" . htmlspecialchars($service->getLastRequestInfo()) . "</pre>", null, false);
            return null;
        }
        //		$this->logger->info("RetrieveCustomerPNRDetails {$recordLocator}: <pre>" . var_export($result, true) . "</pre>");
        $this->http->JsonLog(json_encode($result), 3, false, 'PNRTravelSegment');

        // tmp for email
        if (is_array($this->http->OnLog) && $this->http->OnLog[1] === 'parserLog') {
            $this->http->Log(json_encode($result), LOG_LEVEL_ERROR);
        }

        if (empty($result) || empty($result->ResponseStatus->Message) || !in_array($result->ResponseStatus->Message, ['Success', 'Partial Results'])) {
            return null;
        }

        $itinerary = [
            'RecordLocator' => $recordLocator,
            'Kind'          => 'T',
        ];
        $itinerary['TripSegments'] = [];
        $passengers = [];

        if (!empty($result->RetrieveCustomerPNRDetailsResult)) {
            foreach ($result->RetrieveCustomerPNRDetailsResult as $pnrDetails) {
                if ($pnrDetails->RequestItem->PNRID != $recordLocator) {
                    $this->http->Log("PNRs does not match, expected $recordLocator, got " . $pnrDetails->RequestItem->PNRID);

                    return null;
                }

                if (isset($pnrDetails->PNR->PNRTicket) && is_array($pnrDetails->PNR->PNRTicket)) {
                    foreach ($pnrDetails->PNR->PNRTicket as $ticket) {
                        if ($ticket->TicketStatusCode != 'UNKNOWN') {
                            $itinerary['Status'] = $ticket->TicketStatusCode;
                        }
                    }
                }

                if (!empty($pnrDetails->PNR->PNRCreateDate)) {
                    $itinerary['ReservationDate'] = strtotime($pnrDetails->PNR->PNRCreateDate);
                }

                /** @var PNROtherAirSegment $segment */
                if (isset($pnrDetails->PNR->PNRTravelSegment)) {
                    $idx = 0;
                    $doubles = [];

                    foreach ($pnrDetails->PNR->PNRTravelSegment as $segment) {
                        if (empty($segment->SegmentBeginTimestamp)) {
                            continue;
                        }

                        if (
                        empty($segment->SegmentEndTimestamp)
                        && empty($segment->FlightBooked)
                        && empty($segment->FlightMarketed)
                        && empty($segment->FlightOperated)
                        && $this->http->FindPreg("/^\d{4}-\d{2}-\d{2}-\d{2}:\d{2}$/", false, $segment->SegmentBeginTimestamp)
                        && $segment->SegmentTypeCode === "9"
                    ) {
                            $this->logger->notice("skip wrong segment");
                            $this->logger->debug(var_export($segment, true), ["pre" => true]);

                            continue;
                        }

                        $info = [
                            'DepCode' => $segment->SegmentServiceBeginCode->StationCode,
                            'ArrCode' => $segment->SegmentServiceEndCode->StationCode,
                            'DepDate' => strtotime(preg_replace("#\..+$#ims", "", $segment->SegmentBeginTimestamp)),
                            'ArrDate' => strtotime(preg_replace("#\..+$#ims", "", $segment->SegmentEndTimestamp)),
                            'Seats'   => [],
                        ];
                        // refs #14730
                        if (!empty($segment->FlightBooked->FlightNumber)) {
                            $flightNumber = $segment->FlightBooked->FlightNumber;

                            if (intval($flightNumber) >= 9100 && intval($flightNumber) <= 9199) {
                                continue;
                            }

                            $info['FlightNumber'] = $flightNumber;
                        }

                        if (!empty($segment->ClassOfServiceBookedCode)) {
                            $info['BookingClass'] = $segment->ClassOfServiceBookedCode;
                        }

                        if (!empty($segment->BaseCabinClassCode)) {
                            $info['Cabin'] = $segment->BaseCabinClassCode;
                        }

                        if (!empty($segment->AircraftTypeCode)) {
                            $info['Aircraft'] = $segment->AircraftTypeCode;
                        }

                        if (!empty($segment->MealServiceIndicator)) {
                            $info['Meal'] = 'Yes';
                        }

                        // partner airline name
                        if (!empty($segment->FlightBooked)) {
                            $info['AirlineName'] = $segment->FlightBooked->Airline->AirlineCode;
                        }

                        if (!empty($info['AirlineName'])) {
                            if ($segment instanceof OAOperatingAirSegment && !empty($segment->FlightMarketed->Airline->AirlineCode)) {
                                $info['AirlineName'] = $segment->FlightMarketed->Airline->AirlineCode;
                            }
                        }

                        if ($segment instanceof OAOperatingAirSegment && !empty($segment->FlightOperated->Airline->AirlineCode)) {
                            $info['AirlineName'] = $segment->FlightOperated->Airline->AirlineCode;
                        }

                        // refs #20501 look for changed segments WK, SC
                        $status = (!empty($segment->SegmentStatusCurrentCode) && !empty($segment->SegmentStatusCurrentCode->SegmentStatusCode) && !empty($info['FlightNumber'])) ? $segment->SegmentStatusCurrentCode->SegmentStatusCode : null;
                        $key = sprintf('%s-%s-%s', $info['DepCode'], $info['ArrCode'], $info['FlightNumber']);

                        switch ($status) {
                            case 'WK':
                                // WK will be cancelled and replaced with SC
                                if (isset($doubles[$key]['SC'])) {
                                    $doubles[$key]['WK'] = true;
                                } else {
                                    $itinerary['TripSegments'][$idx] = $info;
                                    $doubles[$key]['WK'] = $idx;
                                    $idx++;
                                }

                                break;

                            case 'SC':
                                // SC is the new one
                                if (isset($doubles[$key]['WK'])) {
                                    if (is_int($doubles[$key]['WK'])) {
                                        $itinerary['TripSegments'][$doubles[$key]['WK']] = $info;
                                    }
                                    $doubles[$key]['SC'] = true;
                                } else {
                                    $itinerary['TripSegments'][$idx] = $info;
                                    $doubles[$key]['SC'] = $idx;
                                    $idx++;
                                }

                                break;

                            default:
                                $itinerary['TripSegments'][$idx] = $info;
                                $idx++;
                        }

//                    $this->logger->debug("Segment #{$info['FlightNumber']}");
//                    $this->logger->debug(var_export($info, true), ["pre" => true]);
                    }
                    // check for inconsistencies in WK-SC pairs
                    foreach ($doubles as $key => $pair) {
                        if (count($pair) !== 2) {
                            $this->logger->error(sprintf('missing WK/SC pair for aa segments %s', $key));
                        }
                    }
                }

                if (!empty($pnrDetails->PNR->PNRPassenger)) {
                    foreach ($pnrDetails->PNR->PNRPassenger as $passenger) {
                        if (!empty($passenger->PNRFirstName)) {
                            $passengers[] = Html::cleanXMLValue($this->removeNamePrefixes($passenger->PNRFirstName) . ' ' . $this->removeNamePrefixes($passenger->PNRLastName));
                        }

                        if (!empty($passenger->PNRPassengerSeat)) {
                            if (!is_array($passenger->PNRPassengerSeat)) {
                                $passenger->PNRPassengerSeat = [$passenger->PNRPassengerSeat];
                            }

                            foreach ($passenger->PNRPassengerSeat as $seat) {
                                foreach ($itinerary['TripSegments'] as &$segment) {
                                    if (
                                        $segment['DepCode'] == $seat->FlightLegServiceBeginCode->StationCode
                                        && $segment['ArrCode'] == $seat->FlightLegServiceEndCode->StationCode
                                        && $segment['FlightNumber'] == $seat->SeatFlightBooked->FlightNumber
                                    ) {
                                        if (isset($seat->SeatRowIdentifier) && isset($seat->RowLetterIdentifier)) {
                                            $segment['Seats'][$seat->PassengerSeatSequenceIdentifier] = $seat->SeatRowIdentifier . $seat->RowLetterIdentifier;
                                        }
                                        $segment['FlightNumber'] = $seat->SeatFlightBooked->FlightNumber;
                                        $segment['AirlineName'] = $seat->SeatFlightBooked->Airline->AirlineCode;
                                    }
                                }// foreach($itinerary['TripSegments'] as &$segment)
                            }// foreach($passenger->PNRPassengerSeat as $seat)
                        }// if(!empty($passenger->PNRPassengerSeat))
                        // moved up ? - no, it still needed for reservation with legs serviced by other airline
                        // partner airline name
                        if (!empty($passenger->PNRSpecialServiceRequest)) {
                            if (!is_array($passenger->PNRSpecialServiceRequest)) {
                                $passenger->PNRSpecialServiceRequest = [$passenger->PNRSpecialServiceRequest];
                            }

                            foreach ($passenger->PNRSpecialServiceRequest as $request) {
                                if ($request->Code == 'TKNE' && !empty($request->SegmentBeginCode->StationCode) && !empty($request->SegmentEndCode->StationCode) && !empty($request->Flight->Airline->AirlineCode)) {
                                    foreach ($itinerary['TripSegments'] as &$segment) {
                                        if (
                                            empty($segment['AirlineName'])
                                            && $segment['DepCode'] == $request->SegmentBeginCode->StationCode
                                            && $segment['ArrCode'] == $request->SegmentEndCode->StationCode
                                            && $segment['FlightNumber'] == $request->Flight->FlightNumber
                                        ) {
                                            $segment['AirlineName'] = $request->Flight->Airline->AirlineCode;
                                        }
                                    }
                                }
                            }
                        }// if(!empty($passenger->PNRSpecialServiceRequest))
                    }// foreach($pnrDetails->PNR->PNRPassenger as $passenger)
                }// if(!empty($pnrDetails->PNR->PNRPassenger))
            }
        }

        $itinerary['Passengers'] = array_map(function ($item) {
            return beautifulName($item);
        }, $passengers);

        foreach ($itinerary['TripSegments'] as &$segment) {
            if (empty($segment['FlightNumber'])) {
                $segment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;
//                $this->sendNotification("aa - FLIGHT_NUMBER_UNKNOWN");
            }
            $segment['Seats'] = array_unique($segment['Seats']);
        }
        unset($segment);
        // filter segments  // refs #8431
        if (!empty($itinerary['TripSegments'])) {
            $filteredSegments = [];
            $countSegments = count($itinerary['TripSegments']);

            for ($i = 0; $i < $countSegments; $i++) {
                $this->http->Log("# $i");
                $segment = $itinerary['TripSegments'][$i];
                $unique = true;
                $wrongSegment = false;

                for ($j = $i + 1; $j < $countSegments; $j++) {
                    $this->http->Log("next # $j");

                    if ($segment['DepCode'] == $itinerary['TripSegments'][$j]['DepCode']
                        && $segment['ArrCode'] == $itinerary['TripSegments'][$j]['ArrCode']
                        && $segment['DepDate'] == $itinerary['TripSegments'][$j]['DepDate']
                        && $segment['ArrDate'] == $itinerary['TripSegments'][$j]['ArrDate']
                        && $segment['FlightNumber'] == $itinerary['TripSegments'][$j]['FlightNumber']
                        /*&& $segment['BookingClass'] != $itinerary['TripSegments'][$j]['BookingClass']*/) {
                        $this->http->Log("The same segment: <pre>" . var_export($segment, true) . "</pre>", false);
                        unset($itinerary['TripSegments'][$j]['BookingClass']);
                        unset($segment['BookingClass']);

                        if (!isset($segment['Cabin'])
                            || (isset($itinerary['TripSegments'][$j]['Cabin'])
                                && $segment['Cabin'] != $itinerary['TripSegments'][$j]['Cabin'])) {
                            unset($segment['Cabin']);
                            unset($itinerary['TripSegments'][$j]['Cabin']);
                        }
                        /*// PendingUpgrade
                        $itinerary['TripSegments'][$j]['PendingUpgradeTo'] = $segment['BookingClass'];
                        // Description
                        if (isset($this->detailedBookingClass[$segment['BookingClass']]))
                            $itinerary['TripSegments'][$j]['PendingUpgradeTo'] .= ' ('.$this->detailedBookingClass[$segment['BookingClass']].')';*/
                        $this->http->Log("New segment: <pre>" . var_export($itinerary['TripSegments'][$j], true) . "</pre>", false);
                        $unique = false;
                        $this->http->Log("next # $j -> not unique");
                    }
                }// for ($j = 0; $j < $countSegments; $j++)

                if (
                    $segment['DepCode'] === $segment['ArrCode']
                    && $segment['DepDate'] === $segment['ArrDate']
                ) {
                    $this->logger->notice("FlightNumber: {$segment['FlightNumber']}");
                    $this->logger->notice("AirlineName: {$segment['AirlineName']}");
                    $this->logger->debug("all segments");
                    $this->logger->debug(var_export($itinerary['TripSegments'], true), ["pre" => true]);
                    $this->logger->debug("remove wrong segment: depCode == arrCode");
                    $this->logger->debug(var_export($segment, true), ["pre" => true]);
                    $wrongSegment = true;
                }

                if ($unique && $wrongSegment === false) {
                    $this->http->Log("#$i unique, add to array");
                    $filteredSegments[] = $segment;
                } else {
                    $this->http->Log("#$i -> not unique / wrong, continue");
                }
            }// for ($i = 0; $i < $countSegments; $i++)
            usort($filteredSegments, function ($s1, $s2) {return $s1['DepDate'] - $s2['DepDate']; });
            $this->http->Log("Filtered segments: <pre>" . var_export($filteredSegments, true) . "</pre>", false);
            $itinerary['TripSegments'] = $filteredSegments;
        }// if (!empty($itinerary['TripSegments']))

        if (isset($wrongSegment, $countSegments) && $countSegments == 1 && $wrongSegment && empty($itinerary['TripSegments'])) {
            $this->logger->notice("skip wrong itinerary #{$recordLocator}");

            return [];
        }
        // api bug fix
        if ($retrieveByConfNo === true && empty($itinerary['TripSegments'])) {
//            $this->sendNotification("aa - empty segments, try to find itinerary via Retrieve by conf #");
            $this->logger->error("empty segments, try to find itinerary via Retrieve by conf #");
            $arFields = [
                'ConfNo'    => $recordLocator,
                'FirstName' => $this->firstName,
                'LastName'  => $this->lastName,
            ];
            $this->CheckConfirmationNumberInternal($arFields, $itinerary);
        }

        return $itinerary;
    }

    protected function removeNamePrefixes($name)
    {
        return preg_replace("#(^(mr|ms|mrs|sr|dr)\s)|(\s(mr|ms|mrs|sr|dr)$)#ims", "", $name);
    }

    private static function useNewAPI($accountInfo)
    {
        return !self::isTestEnvironment($accountInfo) && ConfigValue(CONFIG_SITE_STATE) == SITE_STATE_DEBUG && in_array($accountInfo['UserID'], [7, 2110]);
    }

    private function normalPassengerName($passenger)
    {
        $passenger = trim($this->removeNamePrefixes($passenger));
        $names = explode(' ', $passenger);

        if (count($names) == 2) {
            $first = array_shift($names);
            $last = array_pop($names);

            return strtolower(substr($first, 0, 10) . ' ' . substr($last, 0, 10));
        } else {
            return strtolower($passenger);
        }
    }

    private function logLastRequest(TExtSoapClient $service)
    {
        $this->http->Log("request: <div>" . htmlspecialchars(str_replace(AA_WSDL_PASSWORD_2017_08_01, 'xxx', str_replace(AA_WSDL_PASSWORD, 'xxx', $service->__getLastRequest()))) . "</div>", LOG_LEVEL_NORMAL, false);
        $this->http->Log("response: <div>" . htmlspecialchars($service->__getLastResponseHeaders() . $service->__getLastResponse()) . "</div>", LOG_LEVEL_NORMAL, false);

        if ($service instanceof CurlSoapClient && !empty($service->getLastError())) {
            $this->http->Log("curl error: <pre>" . var_export($service->getLastError(), true) . "</pre>");
        }
    }

    private function getWsdlOptions($password = null, $location = null, bool $useCertificate = false)
    {
        $this->http->Log(__METHOD__);
        ini_set("soap.wsdl_cache_enabled", "0");

        if (empty($password)) {
            $password = AA_WSDL_PASSWORD;
        }
        $options = [
            'trace' => true,
            //			'stream_context' => stream_context_create([
            //				'ssl' => [
            //					'verify_peer' => false,
            //					'allow_self_signed' => true,
            //				]
            //			])
        ];

        if (!empty($location)) {
            if (empty(parse_url($location, PHP_URL_HOST))) {
                $location = 'https://aapartner.esoa.aa.com' . $location;
            }
            $options['location'] = $location;
        }

        if (!empty($password)) {
            $options['wsse-login'] = AA_WSDL_LOGIN;
            $options['wsse-password'] = $password;
        }

        if ($useCertificate) {
//            $certificate = "aa-client-prod-2019-04-05";
            $certificate = "aa-client-prod-2021-03-08";
            $options['local_cert'] = '/usr/keys/' . $certificate . '.pem';
            $options['passphrase'] = AA_WSDL_PASSWORD_2017_08_01;
            // remove stream_context? we use curl now
            $options['stream_context'] = stream_context_create([
                'http' => [
                    'header' => "userid: AWRDWLT\r\nfunctionalid: " . AA_WSDL_LOGIN . "\r\n",
                ],
                'ssl' => [
                    'cafile' => '/usr/keys/aa-root-prod.pem',
                ],
            ]);
            $options['curl_ca_file'] = '/usr/keys/aa-root-prod.pem';
            $options['curl_ssl_certificate'] = '/usr/keys/' . $certificate . '.pem';
            $options['curl_ssl_passphrase'] = AA_WSDL_PASSWORD_2017_08_01;
            $options['curl_headers'] = ["userid: AWRDWLT", "functionalid: " . AA_WSDL_LOGIN];
        }

        if (defined('WHITE_PROXY')) {
            // ssh -L localhost:3128:whiteproxy.infra.awardwallet.com:3128 192.168.2.132
            // define('WHITE_PROXY', 'host.docker.internal');
            $options['proxy_host'] = WHITE_PROXY;
        } else {
            $options['proxy_host'] = 'whiteproxy.infra.awardwallet.com';
        }
        $options['proxy_port'] = 3128;

        return $options;
    }

    private function normalName($s)
    {
        return preg_replace('#[^a-z]+#ims', "", $s);
    }

    /**
     * Allows to check AA in test environment.
     *
     * @return bool
     */
    private function isTestEnvironment(array $accountFields)
    {
        return (self::TEST_LOGIN2 === $accountFields['Login2'])
            && (self::TEST_LOGIN3 === $accountFields['Login3'] || self::TEST_PASS === $accountFields['Pass']);
    }

    private function isValidTestCredentials(array $accountFields)
    {
        return in_array($accountFields['Login'], [self::TEST_LOGIN, self::TEST_NUMBER_PROVIDED_BY_API], true);
    }
}
