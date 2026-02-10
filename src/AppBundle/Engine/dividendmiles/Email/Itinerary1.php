<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "dividendmiles/it-12.eml, dividendmiles/it-13.eml, dividendmiles/it-15.eml, dividendmiles/it-2.eml, dividendmiles/it-21.eml, dividendmiles/it-22.eml, dividendmiles/it-23.eml, dividendmiles/it-3.eml, dividendmiles/it-4.eml, dividendmiles/it-5.eml, dividendmiles/it-6.eml, dividendmiles/it-7.eml, dividendmiles/it-8.eml, dividendmiles/it-9.eml";

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers['from']) && (stripos($headers['from'], "dividendmiles@myusairways.com") !== false || stripos($headers['from'], '@usairways.com') !== false || stripos($headers['from'], '@email-usairways.com') !== false))
        || (isset($headers['subject']) && (
                stripos($headers['subject'], "US Airways") !== false
                || preg_match("/Schedule Change impacting [\w\s]+ \- Confirmation code/", $headers['subject'])
                //not very reliable but necessary for restricted detection
                || strpos($headers["subject"], "Check in for your upcoming trip") !== false
                || strpos($headers["subject"], "Your First Class upgrade status") !== false))
        || (isset($headers['references']) && stripos($headers['references'], "usairways.com") !== false);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return stripos($body, 'Please add dividendmiles@myusairways.com to your personal address book') !== false
            || stripos($body, 'Make sure the name in your Dividend Miles account matches the name on your ticket') !== false
            || stripos($body, "usairways.com") !== false;
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match("/@myusairways\.com/i", $from);
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader("date"));
        $emailType = $this->getEmailType($parser);

        switch ($emailType) {
            case "TravelConfirmation":
                $result = $this->ParseTravelConfirmation();

            break;

            case "PlainYourReservation":
                $result = $this->ParsePlainYourReservation();

            break;

            case "YourReservation":
                $result = $this->ParseYourReservation();

            break;

            case "YourReservation2":
                $result = $this->ParseYourReservation2();

            break;

            case "MultipleReservation":
                $result = $this->ParseMultipleReservations();

            break;

            case "ScheduleChange":
                $result = $this->ParseScheduleChange();

            break;

            case "YourUpcomingItinerary":
                $result = $this->ParseYourUpcomingItinerary();

            break;

            case "ListingDetails":
                $result = $this->ParseListingDetails();

            break;

            case "Receipt":
                $result = $this->ParseReceipt();

            break;

            default:
                $result = "Undefined email type";

            break;
        }

        if ($this->RefreshData && !empty($result['RecordLocator']) && !empty($result['TripSegments'][0]['DepDate'])) {
            /*
            $checker = new \TAccountCheckerDividendmiles();
            $errorMsg = $checker->CheckConfirmationNumberInternal([
                    'RecordLocator' => $result['RecordLocator'],
                    'Depart' => date('m/d/Y', $result['TripSegments'][0]['DepDate'])
                ], $itinerary);

            if($errorMsg === null && !empty($itinerary)){
                $result = $itinerary;
                $emailType = 'CheckConfirmationNumberInternal';
            }
            */
        }

        return [
            'emailType'  => $emailType,
            'parsedData' => $result,
        ];
    }

    public function ParseReceipt()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'T'];

        $result['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "Confirmation Code:")]', null, true, '/Confirmation Code:\s*(\S+)/ims');

        $segments = [];
        $segmentsNodes = $xpath->query('//tr[
            *[(self::td or self::th) and not(.//table)][contains(., "Flight")] and
            *[self::td or self::th][contains(., "Flight Itinerary")] and
            *[self::td or self::th][contains(., "Date")] and
            *[self::td or self::th][contains(., "Travel Duration")]
        ]/following-sibling::tr');

        foreach ($segmentsNodes as $segmentNode) {
            $segment = [];

            if (preg_match('/(.+?)\s*(\d+)/ims', $http->FindSingleNode('./td[1]', $segmentNode), $matches)) {
                $segment['FlightNumber'] = $matches[2];
                $segment['AirlineName'] = $matches[1];
            }
            $baseDate = $http->FindSingleNode('./td[3]', $segmentNode);

            foreach ([['Dep', 'Depart'], ['Arr', 'Arrival']] as $keys) {
                [$Dep, $Depart] = $keys;

                if (preg_match('/(\d+.\d+\s*(?:am|pm))\s*(.+?)\s*\((\S+)\)/ims', $http->FindSingleNode("./td[2]//text()[contains(., '{$Depart}')]/following::text()[normalize-space()][1]", $segmentNode), $matches)) {
                    $segment["{$Dep}Code"] = $matches[3];
                    $segment["{$Dep}Name"] = $matches[2];

                    if (!empty($baseDate)) {
                        $segment["{$Dep}Date"] = strtotime(trim($baseDate . ', ' . $matches[1], "\t\n\r\0\x0B,"), $this->date);
                    }
                }
            }
            $segment['Duration'] = $http->FindSingleNode('./td[4][not(normalize-space(.) = "NA")]');
            $segment['Aircraft'] = $http->FindSingleNode('./td[5][not(normalize-space(.) = "NA")]');
            $segment['Cabin'] = $http->FindSingleNode('./td[6][not(normalize-space(.) = "NA")]');
            $segment['Seats'] = $http->FindSingleNode('./td[7][not(normalize-space(.) = "NA")]');

            $segments[] = $segment;
        }
        $passengers = [];
        $accountNumbers = [];
        $passengersNodes = $xpath->query('//tr[
            *[self::td or self::th and not(.//table)][contains(., "Passenger Name")] and
            *[self::td or self::th and not(.//table)][contains(., "Passenger Type")] and
            *[self::td or self::th and not(.//table)][contains(., "Frequent flyer #")]
        ]/following-sibling::tr[count(./td) > 5]');

        foreach ($passengersNodes as $passengerNode) {
            $passengers[] = $http->FindSingleNode('./td[1]', $passengerNode);
            $accountNumbers[] = $http->FindSingleNode('./td[3][not(normalize-space(.) = "NA")]', $passengerNode);
        }
        $result['AccountNumbers'] = implode(',', array_unique(array_filter($accountNumbers, 'strlen')));
        $result['Passengers'] = array_unique(array_filter($passengers, 'strlen'));

        if (preg_match('/(\d+.\d+|\d+)\s*([A-Z]+)?/ims', $http->FindSingleNode('//td[contains(., "Total Amount Due") and not(.//table)]/following-sibling::td[1]'), $matches)) {
            if (!empty($matches[2])) {
                $result['Currency'] = $matches[2];
            }
            $result['TotalCharge'] = $matches[1];
        }

        if (!empty($segments)) {
            $result['TripSegments'] = $segments;
        }

        return [
            'Itineraries' => [$result],
        ];
    }

    /**
     * @example dividendmiles/it-7.eml
     * @example dividendmiles/it-8.eml
     * @example dividendmiles/it-9.eml
     */
    public function ParseYourUpcomingItinerary()
    {
        $itineraries = [];
        $itineraries['Kind'] = 'T';

        // Confirmation Number: X4MYAT
        $itineraries['RecordLocator'] = $this->http->FindSingleNode('//table//table//*[contains(text(), "CONFIRMATION CODE")]/ancestor::tr[1]/td[2]');

        $segments = [];

        foreach ($this->http->XPath->query('//td/span/strong[contains(text(), "Travel Date/Time")]/ancestor::tr[1]') as $row) {
            $segment = [];
            /** @var \DOMNode $row */
            $DepDate = $this->http->FindSingleNode(".//td[2]", $row);
            $DepDate = str_replace(' at ', ' ', $DepDate);

            if (preg_match('/^\w{3},\s+(.*)$/i', $DepDate, $matches)) {
                $DepDate = $matches[1];
            }
            $ArrDate = $DepDate;

            $FlightNumber = $this->http->FindNodes('./following-sibling::tr/td/strong[contains(text(), "Flight")]/ancestor::tr[1]/td[2]', $row);

            if (count($FlightNumber)) {
                $FlightNumber = $FlightNumber[0];
            }

            $DepCode = $this->http->FindNodes('./following-sibling::tr/td/strong[contains(text(), "Depart")]/ancestor::tr[1]/td[2]', $row);

            if (count($DepCode)) {
                $DepCode = $DepCode[0];
            }

            $ArrCode = $this->http->FindNodes('./following-sibling::tr/td/strong[contains(text(), "Arrive")]/ancestor::tr[1]/td[2]', $row);

            if (count($ArrCode)) {
                $ArrCode = $ArrCode[0];
            }

            // Status 	Your upgrade is confirmed
            $status = $this->http->FindNodes('./following-sibling::tr/td/strong[contains(text(), "Status")]/ancestor::tr[1]/td[2]', $row);

            if (count($status)) {
                $status = $status[0];
            }

            if (!empty($FlightNumber) && !empty($DepDate)) {
                $segment['FlightNumber'] = trim($FlightNumber);
                $segment['DepCode'] = trim($DepCode);
                $segment['DepName'] = $segment['DepCode'];
                $segment['DepDate'] = strtotime($DepDate, $this->date);
                $segment['ArrCode'] = trim($ArrCode);
                $segment['ArrName'] = $segment['ArrCode'];
                $segment['ArrDate'] = strtotime($ArrDate, $this->date);

                $segments[] = $segment;
            }
        }

        $itineraries['TripSegments'] = $segments;

        return [
            'Itineraries' => [$itineraries],
            'Properties'  => [],
        ];
    }

    /**
     * @example dividendmiles/it_aw-1.eml
     * @example dividendmiles/it-6.eml
     */
    public function ParseMultipleReservations()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'T'];

        //$baseTable=$xpath->query('');
        $result['Passengers'] = implode(', ', array_filter($http->FindNodes('//*[contains(text(), "Passenger name")]/ancestor::tr[1]/following-sibling::tr/td[1]')));
        $result['AccountNumbers'] = implode(', ', array_filter($http->FindNodes('//*[contains(text(), "Passenger name")]/ancestor::tr[1]/following-sibling::tr/td[2]')));

        $table = $this->http->FindNodes('//table//tr[1]/td[contains(./div,"Confirmation code:")]//table//tr/td');
        $codes = [];
        $code = '';

        foreach ($table as $cell) {
            if (!empty($cell)) {
                if (empty($code)) {
                    $code = $cell;
                } else {
                    $codes[$cell] = $code;
                    $code = '';
                }
            }
        }
        $date = $this->http->FindSingleNode('//td[contains(.,"You\'re confirmed") and not(.//td)]/*[contains(.,"Date issued:")]', null, true, '/Date\s+issued:\s*(.*)/');

        if (!empty($date)) {
            $result['ReservationDate'] = strtotime($date, $this->date);
        }

        // TotalCharge
        $charge = $http->FindSingleNode('//td[contains(text(), "Total") and not(contains(text(), "travel"))]/following-sibling::td[3]');

        if (empty($charge)) {
            $charge = $this->http->FindSingleNode('//*[contains(text(), "You paid")]');
        }

        if (!empty($charge)) {
            // 2482.33 USD
            if (preg_match('/(\d+.\d+|\d+)/ims', $charge, $matches)) {
                $result['TotalCharge'] = $matches[1];
            }

            if (preg_match('/(\d+.\d+|\d+)\s+(\S+)/ims', $charge, $matches)) {
                $result['Currency'] = $matches[2];
            }
            // You paid $261.90
            if (preg_match('/([\$]{1})/', $charge)) {
                $result['Currency'] = 'USD';
            }

            if (!isset($result['Currency'])) {
                unset($result['TotalCharge']);
            }
        }

        $segments = [];
        $tripSegments = [];
        $airportCodes = [];
        $tripRowsBase = $xpath->query('//table//tr[1]/td[contains(.//table,"Trip details")]/parent::tr/following-sibling::tr');
        $baseData = [];
        $lastFlight = '';

        foreach ($tripRowsBase as $tripRow) {
            if ($http->FindSingleNode('./td//text()[contains(., "Return:") or contains(., "Depart:")]', $tripRow)) {
                if (!empty($baseData)) {
                    //saving segments
                    $tripSegments[] = &$baseData;
                    unset($baseData);
                }

                $baseData = [
                    'Date'  => '',
                    'Status'=> '',
                    'Points'=> [
                        $http->FindSingleNode('./td[1]//img/preceding-sibling::text()[1]', $tripRow),
                        $http->FindSingleNode('./td[1]//img/following-sibling::text()[1]', $tripRow),
                    ],
                    'segments'    => [],
                    'segmentStart'=> false,
                ];

                continue;
            }

            if (!empty($baseData)) {
                $cont = false;

                if (empty($baseData['Date'])) {
                    $nodes = $http->FindNodes('./td//*[contains(text(),"Date:")]/following-sibling::node()', $tripRow);

                    if (!empty($nodes)) {
                        $baseData['Date'] = implode($nodes);
                        $cont = true;
                    }
                }

                if (empty($baseData['Status'])) {
                    $nodes = $http->FindNodes('./td//*[contains(text(),"Status:")]/following-sibling::node()', $tripRow);

                    if (!empty($nodes)) {
                        $baseData['Status'] = implode($nodes);
                        $cont = true;
                    }
                }

                if (empty($baseData['Status']) or empty($baseData['Date']) or $cont) {
                    continue;
                }

                // handle stops
                if ($stopData = $http->FindSingleNode('.//*[contains(text(), "Stop:")]', $tripRow)) {
                    if (preg_match('/Stop:.*\bin\s+(.*)$/ims', $stopData, $matches)) {
                        $baseData['Points'][] = $matches[1];
                    }
                    // handle time shifts
                } elseif ($stopData = $http->FindSingleNode('.//*[contains(text(), "Operated by")]', $tripRow)) {
                    if (preg_match('/.*\bby\s+(.*)$/ims', $stopData, $matches)) {
                        $baseData['changeOperator'][$lastFlight] = $matches[1];
                    }
                    // handle time shifts
                } elseif ($shifts = $http->FindNodes('.//*[contains(text(), "Flight #") and (contains(text(), "next day") or contains(text(), "days later"))]', $tripRow)) {
                    foreach ($shifts as $shift) {
                        if (preg_match('/Flight\s*#\s*(\d+)\s*:\s*(\S+)\s*(?:next day|days later),\s*(.+)$/ims', $shift, $matches)) {
                            $baseData['dayShift'][$matches[1]] = [ // $dateShift['1234'] = [
                                'Action' => $matches[2], // Action => 'Arrive'
                                'Date'   => $matches[3],  // Date =>	'Monday, 23 November 2039']
                            ];
                        }
                    }
                    // handle segment data
                } elseif ($xpath->query('./td', $tripRow)->length > 6) {
                    if ($http->FindSingleNode('./td[contains(text(),"Carrier")]', $tripRow)) {
                        continue;
                    }
                    $segment = [];
                    $baseData['segments'][] = &$segment;

                    foreach ([['Dep', 2], ['Arr', 3]] as $vars) {
                        [$Dep, $index] = $vars;
                        $time = $http->FindSingleNode("./td[{$index}]", $tripRow);
                        preg_match('/(.*)\s+(\S+)$/ims', $time, $matches);
                        $segment["{$Dep}Time"] = ArrayVal($matches, 1);
                        $segment["{$Dep}Code"] = ArrayVal($matches, 2);
                    }
                    //$segment['AirlineName'] = preg_replace("/\sdba.*$/ims", "", $http->FindSingleNode("//td[img[@src='" . $http->FindSingleNode('./td[1]//img/@src', $tripRow) . "']]/following-sibling::td[1][contains(text(), 'Operated by')]", null, true, '/Operated by\s+(.*)/ims'));

                    $lastFlight = $segment['FlightNumber'] = $http->FindSingleNode('./td[1]', $tripRow);
                    $segment['Duration'] = $http->FindSingleNode("./td[4]", $tripRow);
                    $segment['Meal'] = $http->FindSingleNode("./td[5]", $tripRow);
                    $segment['Aircraft'] = $http->FindSingleNode("./td[6]", $tripRow);
                    $segment['Cabin'] = $http->FindSingleNode("./td[7]", $tripRow);
                    $segment['Seats'] = $http->FindSingleNode("./td[8]", $tripRow);

                    unset($segment);
                }
            }
        }
        $itineraries = [];

        if (!empty($baseData)) {
            $tripSegments[] = &$baseData;
        }

        foreach ($tripSegments as &$baseData) {
            foreach ($baseData['Points'] as $key=>&$point) {
                if (is_numeric($key)) {
                    if (preg_match('/(.*)\s+\((.*)\)$/ims', $point, $matches)) {
                        $baseData['Points'][$matches[2]] = $matches[1];
                    }
                    unset($baseData['Points'][$key]);
                }
            }
            unset($point);

            foreach ($baseData['segments'] as &$segment) {
                $airportCodes = &$baseData['Points'];
                $dateShifts = &$baseData['dayShift'];
                $operators = &$baseData['changeOperator'];

                if (isset($operators[$segment['FlightNumber']])) {
                    $operator = $operators[$segment['FlightNumber']];
                    $segment['AirlineName'] = $operator;
                    $resultSegment = $result;

                    foreach ($codes as $name=>$code) {
                        if (strpos($operator, $name) !== false) {
                            $resultSegment['RecordLocator'] = $code;

                            break;
                        }
                    }

                    foreach ($itineraries as &$group) {
                        if (isset($group['RecordLocator']) && isset($resultSegment['RecordLocator']) && $group['RecordLocator'] == $resultSegment['RecordLocator']) {
                            $resultSegment = &$group;

                            break;
                        }
                    }

                    if (isset($resultSegment['TripSegments'])) {
                        $resultSegment['TripSegments'][] = &$segment;
                    } else {
                        $itineraries[] = &$resultSegment;
                        $resultSegment['TripSegments'] = [&$segment];
                    }

                    unset($resultSegment);
                }

                foreach (['Dep', 'Arr'] as $Dep) {
                    // set (Arr|Dep)Name
                    if (isset($airportCodes[$segment["${Dep}Code"]])) {
                        $segment["{$Dep}Name"] = $airportCodes[$segment["{$Dep}Code"]];
                    }

                    // set time
                    if (isset($dateShifts[$segment['FlightNumber']]) && stripos($dateShifts[$segment['FlightNumber']]['Action'], $Dep) !== false) {
                        $segment["${Dep}Date"] = strtotime($dateShifts[$segment['FlightNumber']]['Date'] . ' ' . $segment["{$Dep}Time"], $this->date);
                    } else {
                        $segment["{$Dep}Date"] = strtotime($baseData['Date'] . ' ' . $segment["{$Dep}Time"], $this->date);
                    }
                    unset($segment["{$Dep}Time"]);
                }
            }
        }

        return ["Itineraries" => $itineraries];
    }

    /**
     * @example dividendmiles/it-13.eml
     */
    public function ParseListingDetails()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = ($confNo = $http->FindSingleNode('//text()[contains(., "Listing Details -")]', null, true, '/Listing Details -\s*(\S+)/ims')) ? $confNo : CONFNO_UNKNOWN;
        $it['Passengers'] = array_map('beautifulName', array_filter($http->FindNodes('//text()[contains(., "Listed Passengers:")]/following-sibling::text()'), 'strlen'));
        $segmentNodes = $xpath->query('
            //*[contains(., "Flight")]
            /following-sibling::*[1][contains(., "Departure")]
            /following-sibling::*[1][contains(., "Departure Time")]
            /ancestor::table[1]//tr[position() > 1]
        ');
        $segments = [];

        foreach ($segmentNodes as $segmentNode) {
            $segment = [];
            $segment['FlightNumber'] = $http->FindSingleNode('./td[1]', $segmentNode);

            foreach ([['Arr', 3], ['Dep', 4]] as $dstData) {
                $baseDate = $http->FindSingleNode("./td[2]", $segmentNode);
                [$Dep, $nodeIdx] = $dstData;

                if (preg_match('/(.+)\((\S+)\)/ims', $http->FindSingleNode("./td[{$nodeIdx}]", $segmentNode), $matches)) {
                    $segment["{$Dep}Code"] = $segment["{$Dep}Name"] = $matches[2];
                    $segment["{$Dep}Date"] = strtotime($baseDate . ' ' . $matches[1], $this->date);
                }
            }
            $it['TripSegments'][] = $segment;
        }

        return [
            'Itineraries' => [$it],
        ];
    }

    /**
     * @example dividendmiles/it-12.eml
     */
    public function ParsePlainYourReservation()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $this->convertPlainToDom($http->Response['body'], $http);

        $it = ['Kind' => 'T'];

        if (preg_match('/^(\S+)\s*(.+)/i', $http->FindSingleNode('//text()[contains(., "Confirmation code:")]/following-sibling::text()[not(contains(., "(One or more of your flights are on another airline)"))][1]'), $matches)) {
            $it['RecordLocator'] = $matches[1];
            $airlineName = $matches[2];
        }
        $passengers = array_filter($http->FindNodes('//text()[contains(., "Passenger name Frequent flyer #")]/following-sibling::text()[count(./following-sibling::text()[contains(., "Day of departure phone:")]) = 1]'), 'strlen');
        $accountNumbers = [];

        foreach ($passengers as $passenger) {
            if (preg_match('/(.+?)\s*(\d+)/i', $passenger, $matches)) {
                $it['Passengers'][] = $matches[1];
                $accountNumbers[] = $matches[2];
            }
        }

        if (!empty($accountNumbers)) {
            $it['AccountNumbers'] = implode(', ', $accountNumbers);
        }

        if (preg_match('/(.+?)\s*\((\S{3})\)\s*(.+?)\s*\((\S{3})\)/ims', $http->FindSingleNode('//text()[starts-with(., "Depart:")]', null, true, '/Depart:\s*(.+)/ims'), $matches)) {
            $codes[$matches[2]] = $matches[1];
            $codes[$matches[4]] = $matches[3];
        }
        $baseDate = $http->FindSingleNode('//text()[starts-with(., "Date:")][1]', null, true, '/Date:\s*(.+)/i');
        $segmentRows = array_filter($http->FindNodes('
            //text()[starts-with(., "Flight #/ Carrier Depart")]
            /following-sibling::text()[
                . != "" and
                count(./following-sibling::text()[contains(., "Total travel cost")]) = 1]'),
        'strlen');

        foreach ($segmentRows as $segmentRow) {
            //552     06:25 AM DFW 10:27 AM PHL 3h 2m MarketPlace(tm) A319 Coach 20E 20D
            $segment = [];

            if (preg_match('/^(\d+)\s+(\d+:\d+\s*(?:AM|PM)?)\s+(\S+)\s+(\d+:\d+\s*(?:AM|PM)?)\s+(\S+)\s+(\d+h\s*\d+m)\s+(.+)\s+(\S+\d+)\s+(\S+)\s+((?:\d+\S\s*)+)$/i', $segmentRow, $matches)) {
                $segment['FlightNumber'] = $matches[1];

                if (isset($airlineName)) {
                    $segment['AirlineName'] = $airlineName;
                }

                if (isset($baseDate)) {
                    $segment['DepDate'] = strtotime("{$baseDate} {$matches['2']}", $this->date);
                    $segment['ArrDate'] = strtotime("{$baseDate} {$matches['4']}", $this->date);
                }
                $segment['DepCode'] = $matches[3];

                if (isset($codes[$segment['DepCode']])) {
                    $segment['DepName'] = $codes[$segment['DepCode']];
                }
                $segment['ArrCode'] = $matches[5];

                if (isset($codes[$segment['DepCode']])) {
                    $segment['ArrName'] = $codes[$segment['ArrCode']];
                }
                $segment['Meal'] = $matches[7];
                $segment['Aircraft'] = $matches[8];
                $segment['Cabin'] = $matches[9];
                $segment['Seats'] = str_replace(' ', ', ', $matches[10]);
                $it['TripSegments'][] = $segment;
            }
        }

        if (preg_match('/Total fare\s*(?:\(All passengers\))?\s*(\S)(\d+.\d+|\d+)/ims', $http->FindSingleNode('//text()[starts-with(., "Total fare")]'), $matches)) {
            $it['Currency'] = ('$' == $matches[1]) ? 'USD' : $matches[1];
            $it['TotalCharge'] = $matches[2];
        }

        return [
            'Itineraries' => [$it],
        ];
    }

    /**
     * @example dividendmiles/it-2.eml
     * @example dividendmiles/it-3.eml
     * @example dividendmiles/it-4.eml
     * @example dividendmiles/it-5.eml
     */
    public function ParseYourReservation()
    {
        // pass stripos for case insensitive string comparison
        $http = $this->http;
        $xpath = $http->XPath;
        // stripos passthrough
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions('stripos');

        $result = ['Kind' => 'T'];
        $result['Passengers'] = implode(', ', array_filter($http->FindNodes('//*[contains(text(), "Passenger name")]/ancestor::tr[1]/following-sibling::tr/td[1]')));
        $result['AccountNumbers'] = implode(', ', array_filter($http->FindNodes('//*[contains(text(), "Passenger name")]/ancestor::tr[1]/following-sibling::tr/td[2]')));

        // Confirmation number
        $result['RecordLocator'] = $http->FindSingleNode('//*[php:functionString("stripos", text(), "Confirmation code:") != false()]', null, true, '/Confirmation code:\s*(.*)/ims');

        if (empty($result['RecordLocator'])) {
            $result['RecordLocator'] = $http->FindSingleNode('//div[contains(text(), "Confirmation code:")]/following-sibling::node()[1 and self::div]//tr[count(td) > 1]/td[1]');
        }

        if (!$result['RecordLocator']) {
            $result['RecordLocator'] = $http->FindSingleNode("//*[contains(text(), 'One or more of your flights are on another airline')]/ancestor-or-self::div[2]//tr[1]", null, true, "#^([A-Z\d\-]{5,})\s+#");
        }

        // Reservation Date
        if (($dateIssued = strtotime($http->FindSingleNode('//*[contains(text(), "Date issued:")]', null, true, '/Date issued:\s+(.*)/ims'))) !== false) {
            $result['ReservationDate'] = $dateIssued;
        }
        // TotalCharge
        $charge = $http->FindSingleNode('//td[contains(text(), "Total") and not(contains(text(), "travel"))]/following-sibling::td[3]');

        if (empty($charge)) {
            $charge = $this->http->FindSingleNode('//*[contains(text(), "You paid")]');
        }

        if (!empty($charge)) {
            // 2482.33 USD
            if (preg_match('/(\d+.\d+|\d+)/ims', $charge, $matches)) {
                $result['TotalCharge'] = $matches[1];
            }

            if (preg_match('/(\d+.\d+|\d+)\s+(\S+)/ims', $charge, $matches)) {
                $result['Currency'] = $matches[2];
            }
            // You paid $261.90
            if (preg_match('/([\$]{1})/', $charge)) {
                $result['Currency'] = 'USD';
            }

            if (!isset($result['Currency'])) {
                unset($result['TotalCharge']);
            }
        }

        $segments = [];
        $dateShifts = [];
        $airportCodes = [];
        $baseDataRowNodes = $xpath->query('//*[contains(text(), "Return:") or contains(text(), "Depart:")]/ancestor::tr[1]');

        $http->Log("Side count: " . $baseDataRowNodes->length);

        foreach ($baseDataRowNodes as $baseDataRowNode) {
            $baseData['Date'] = $http->FindSingleNode('.//*[contains(text(), "Date:")]/ancestor-or-self::td[1]', $baseDataRowNode, true, '/Date:\s+(.*)/ims');

            if (empty($baseData['Date'])) {
                $baseData['Date'] = $http->FindSingleNode('./following-sibling::tr[1]//*[contains(text(), "Date:")]/ancestor-or-self::td[1]', $baseDataRowNode, true, '/Date:\s+(.*)/ims');
            }
            $baseData['Status'] = $http->FindSingleNode('.//*[contains(text(), "Status:")]/ancestor-or-self::td[1]', $baseDataRowNode, true, '/Status:\s+(.*)/ims');

            foreach (['following', 'preceding'] as $following) {
                if (preg_match('/(.*)\s+\((.*)\)$/ims', $http->FindSingleNode(".//td[1]//img/{$following}-sibling::text()[1]", $baseDataRowNode), $matches)) {
                    $airportCodes[$matches[2]] = $matches[1];
                }
            }
            $tripSegments = [];
            $segment = [];
            $dataRowNodes = $xpath->query('./following-sibling::tr[not(.//*[contains(text(), "Flight #/ Carrier")])]', $baseDataRowNode); // exclude heading rows "Flight #/ Carrier"

            foreach ($dataRowNodes as $dataRowNode) {
                // skip empty rows
                $flightNodeValue = CleanXMLValue($dataRowNode->nodeValue);

                if (empty($flightNodeValue)) {
                    continue;
                }

                // set data from baseRowData at last segment in series
                if ($xpath->query('.//*[contains(text(), "Depart:") or contains(text(), "Return:")]', $dataRowNode)->length > 0) {
                    break;
                }

                // handle stops
                if ($stopData = $http->FindSingleNode('.//*[contains(text(), "Stop:")]', $dataRowNode)) {
                    if (preg_match('/Stop:.*\s+in\s+(.*)\s+\(([^\(]+)\)$/ims', $stopData, $matches)) {
                        $airportCodes[$matches[2]] = $matches[1]; // $airportCodes['PHX'] = 'Phoenix, Zimbabwe'
                    }
                    // handle time shifts
                } elseif ($shifts = $http->FindNodes('.//*[contains(text(), "Flight #") and contains(text(), "next day")]', $dataRowNode)) {
                    foreach ($shifts as $shift) {
                        if (preg_match('/Flight # (\d+) : (\S+) next day, (.+)$/ims', $shift, $matches)) {
                            $dateShifts[$matches[1]] = [ // $dateShift['1234'] = [
                                'Action' => $matches[2], // Action => 'Arrive'
                                'Date'   => $matches[3],  // Date =>	'Monday, 23 November 2039']
                            ];
                        }
                    }
                    // handle segment data
                } elseif ($xpath->query('./td', $dataRowNode)->length > 6) {
                    foreach ([['Dep', 2], ['Arr', 3]] as $vars) {
                        [$Dep, $index] = $vars;
                        $time = $http->FindSingleNode("./td[{$index}]", $dataRowNode);
                        preg_match('/(.*)\s+([^\s]+)$/ims', $time, $matches);
                        $segment["{$Dep}Time"] = ArrayVal($matches, 1);
                        $segment["{$Dep}Code"] = ArrayVal($matches, 2);
                    }
                    $segment['AirlineName'] = preg_replace("/\sdba.*$/ims", "", $http->FindSingleNode("//td[img[@src='" . $http->FindSingleNode('./td[1]//img/@src', $dataRowNode) . "']]/following-sibling::td[1][contains(text(), 'Operated by')]", null, true, '/Operated by\s+(.*)/ims'));

                    $segment['FlightNumber'] = $http->FindSingleNode('./td[1]', $dataRowNode);
                    $segment['Duration'] = $http->FindSingleNode("./td[4]", $dataRowNode);
                    $segment['Meal'] = $http->FindSingleNode("./td[5]", $dataRowNode);
                    $segment['Aircraft'] = $http->FindSingleNode("./td[6]", $dataRowNode);
                    $segment['Cabin'] = $http->FindSingleNode("./td[7]", $dataRowNode);
                    $segment['Seats'] = $http->FindSingleNode("./td[8]", $dataRowNode);

                    $tripSegments[] = $segment;
                    $segment = [];
                }
            }

            // fill missing data
            foreach ($tripSegments as &$segment) {
                foreach (['Dep', 'Arr'] as $Dep) {
                    // set (Arr|Dep)Name
                    if (isset($airportCodes[$segment["${Dep}Code"]])) {
                        $segment["{$Dep}Name"] = $airportCodes[$segment["{$Dep}Code"]];
                    }
                    // set time
                    if (isset($dateShifts[$segment['FlightNumber']]) && stripos($dateShifts[$segment['FlightNumber']]['Action'], $Dep) !== false) {
                        $segment["${Dep}Date"] = strtotime($dateShifts[$segment['FlightNumber']]['Date'] . ' ' . $segment["{$Dep}Time"], $this->date);
                    } else {
                        $segment["{$Dep}Date"] = strtotime($baseData['Date'] . ' ' . $segment["{$Dep}Time"], $this->date);
                    }
                    unset($segment["{$Dep}Time"]);
                }
            }
            unset($segment);
            $dateShifts = [];

            $segments = array_merge($segments, $tripSegments);
        }

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }

        return ["Itineraries" => [$result]];
    }

    /**
     * @example dividendmiles/it-10.eml
     * @example dividendmiles/it-11.eml
     * @example dividendmiles/it-16.eml
     * @example dividendmiles/it-17.eml
     * @example dividendmiles/it-18.eml
     * @example dividendmiles/it-19.eml
     */
    public function ParseYourReservation2()
    {
        return null; // replaced by new format

        $it = ['Kind' => 'T'];

        $http = $this->http;

        $xpath = $http->XPath;
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions('CleanXMLValue');

        // TODO: it needs more emails to hanle multiple record locators
        if (!$it['RecordLocator'] = $http->FindSingleNode('(//div[contains(text(), "Confirmation code")]/following-sibling::node()[1 and self::div]//tr[count(td) = 3]/td[1])[1]')) {
            $it['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "Confirmation code")]/following::text()[normalize-space() and not(contains(., "or more"))][1]');
        }

        $accountNumbers = [];
        $passengerNodes = $xpath->query('//tr[td[contains(., "Passenger summary") and not(.//td)]]/following-sibling::tr[position() > 1]');

        foreach ($passengerNodes as $passengerNode) {
            if ($http->FindSingleNode('./preceding-sibling::tr[last() - 1]/td[1 and contains(., "Passenger name")]', $passengerNode)) {
                $it['Passengers'][] = $http->FindSingleNode('./td[1]', $passengerNode);
            }

            if ($http->FindSingleNode('./preceding-sibling::tr[last() - 1]/td[2 and contains(., "Frequent flyer")]', $passengerNode)) {
                if (preg_match('/([^\(]+)\s+?(\((.+)\))?/ims', $http->FindSingleNode('./td[2]', $passengerNode), $matches)) {
                    $accountNumbers[] = $matches[1];
                }
            }
        }

        if (!empty($accountNumbers)) {
            $it['AccountNumbers'] = implode(', ', $accountNumbers);
        }

        $airCodes = [];

        $tripHeadingNodes = $xpath->query('//td[contains(., "Trip details") and not(.//td)]/ancestor::tr[count(td) = 1]
            /following-sibling::tr[
                .//td[.//img[contains(@src, "arrow_right")] and not(.//td)]
                /following-sibling::td[contains(., " to ")]
            ]
        ');
        $tripHeadingNodes = $xpath->query('//tr[
            td[
                .//img[
                    contains(@src, "depart") or
                    contains(@src, "return")
                ]
            ]
        ]/following-sibling::tr[
            .//td[
                .//img[contains(@src, "arrow")]
            ]/following-sibling::td[contains(., " to ")]
        ]
        ');

        foreach ($tripHeadingNodes as $tripHeadingNodeIndex => $tripHeadingNode) {
            // divide by trips (ex. "BOS -> TLH"). "BOS -> TLH" might be BOS -> LAX -> FLL -> TLH so then divide by segments too
            if ($xpath->query('./following-sibling::tr', $tripHeadingNode)->length < 4) {
                /** @example dividendmiles/it-18.eml */
                $tripNodes = $xpath->query(". |
                ./ancestor::table[1]/following-sibling::table[1]/tbody/tr", $tripHeadingNode);
            } else {
                $tripNodes = $xpath->query(". |
                ./following-sibling::tr[
                    count(
                        following-sibling::tr[
                            .//td[.//img[contains(@src, 'arrow')] and not(.//td)]
                            /following-sibling::td[contains(., ' to ')]
                        ]
                    ) = {$tripHeadingNodes->length} - {$tripHeadingNodeIndex} - 1
                ]", $tripHeadingNode);
            }

            if ($tripNodes->length == 0) {
                continue;
            }
            $tripHttp = $this->CreateDOMbyNodeList($tripNodes);

            // get end points aircodes
            if (count($endPointCodes = array_values(array_filter($tripHttp->FindNodes('//td[
                .//img[contains(@src, "arrow")] and not(.//td)
            ]/preceding-sibling::td[1]
            |
            //td[
                .//img[contains(@src, "arrow")] and not(.//td)
            ]/following-sibling::td[1]'), 'strlen'))) == 2
            && preg_match('/^(.+) to (.+)$/ims', $http->FindSingleNode('.//text()[contains(., " to ")]', $tripHeadingNode), $matches)) {
                $airCodes[$endPointCodes[0]] = $matches[1];
                $airCodes[$endPointCodes[1]] = $matches[2];
            }
            $tripBaseDate = $http->FindSingleNode('(.//*)[last()]', $tripHeadingNode);

            // get stop points aircodes
            foreach ($tripHttp->FindNodes('//*[contains(text(), "Stop:")]') as $stopLine) {
                if (preg_match('/Stop: Change \S+ in (.+)\s+?\((\S+)\)/ims', $stopLine, $matches)) {
                    $airCodes[$matches[2]] = $matches[1];
                }
            }

            // divide by segments
            $segmentStartNodes = $tripHttp->XPath->query('//td[(contains(., "FLIGHT#") or contains(., "FLIGHT #")) and not(.//td)]/ancestor::tr[last()]');

            $segmentStartNodes = $tripHttp->XPath->query('//td[(contains(., "FLIGHT#") or contains(., "FLIGHT #")) and not(.//td)]/ancestor::tr[last()]');

            foreach ($segmentStartNodes as $segmentStartNodesIndex => $segmentStartNode) {
                $segmentNodes = $tripHttp->XPath->query(". |
                ./following-sibling::tr[
                    count(following-sibling::tr[
                        .//td[
                            contains(., 'FLIGHT#') or
                            contains(., 'FLIGHT #')
                            and not(.//td)
                         ]
                    ]) = {$segmentStartNodes->length} - {$segmentStartNodesIndex} - 1
                ]", $segmentStartNode);

                if ($segmentNodes->length == 0) {
                    continue;
                }
                $segmentHttp = $this->CreateDOMbyNodeList($segmentNodes);

                $segment = [];
                $segment['FlightNumber'] = $segmentHttp->FindSingleNode('//td[(contains(., "FLIGHT#") or contains(., "FLIGHT #")) and not(.//td)]', null, true, '/FLIGHT\s*#\s*(.+)/ims');
                $segment['AirlineName'] = $segmentHttp->FindSingleNode('//td[(contains(., "FLIGHT#") or contains(., "FLIGHT #")) and not(.//td)]/following-sibling::td[2]', null, true, '/Operated by (.+?)(?: dba .+)?$/ims');

                if (preg_match('/(.+)\s+(\w{3})/ims', $segmentHttp->FindSingleNode('//td[contains(., "DEPART") and not(.//td)]/following-sibling::td[2]'), $matches)) {
                    $segment['DepDate'] = strtotime($tripBaseDate . ' ' . $matches[1], $this->date);
                    $segment['DepCode'] = $matches[2];

                    if (isset($airCodes[$matches[2]])) {
                        $segment['DepName'] = $airCodes[$matches[2]];
                    }
                }

                if (preg_match('/(.+)\s+(\w{3})/ims', $segmentHttp->FindSingleNode('//td[contains(., "ARRIVE") and not(.//td)]/following-sibling::td[2]'), $matches)) {
                    $segment['ArrDate'] = strtotime($tripBaseDate . ' ' . $matches[1], $this->date);
                    $segment['ArrCode'] = $matches[2];

                    if (isset($airCodes[$matches[2]])) {
                        $segment['ArrName'] = $airCodes[$matches[2]];
                    }
                }
                $segment['Duration'] = $segmentHttp->FindSingleNode('//td[contains(., "TRAVEL TIME") and not(.//td)]/following-sibling::td[2]');
                $segment['Cabin'] = $segmentHttp->FindSingleNode('//td[contains(., "CABIN") and not(.//td)]/following-sibling::td[2]');
                $segment['Aircraft'] = $segmentHttp->FindSingleNode('//td[contains(., "AIRCRAFT") and not(.//td)]/following-sibling::td[2]');
                $segment['Seats'] = $segmentHttp->FindSingleNode('//td[contains(., "SEATS") and not(.//td)]/following-sibling::td[2]');
                $segment['Meal'] = $segmentHttp->FindSingleNode('//td[contains(., "MEAL") and not(.//td)]/following-sibling::td[2]');

                $it['TripSegments'][] = $segment;
            }
        }

        $it['BaseFare'] = $http->FindSingleNode('//td[contains(., "Fare Total") and not(.//td)]/following-sibling::td[1]');
        $this->setPrice($http->FindSingleNode('//td[contains(., "Taxes and fees") and not(.//td)]/following-sibling::td[1]'), $it['Currency'], $it['Tax']);
        $this->setPrice($http->FindSingleNode('(//td[(contains(., "Fare total") or contains(., "Total fare")) and not(.//td)]/following-sibling::td[1])[1]'), $it['Currency'], $it['BaseFare']);

        $this->setPrice($http->FindSingleNode('//td[contains(., "Total") and php:functionString("CleanXMLValue", .) = "Total" and not(.//td)]/following-sibling::td[1]'), $it['Currency'], $it['TotalCharge']);
        $this->setPrice($http->FindSingleNode('//td[contains(., "You paid") and not(.//td)]', null, true, '/You paid\s+(.+)/ims'), $it['Currency'], $it['TotalCharge']);

        return [
            'Itineraries' => [$it],
            'Properties'  => [],
        ];
    }

    public function ParseScheduleChange()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $result = ['Kind' => 'T'];
        $result['RecordLocator'] = $http->FindPreg('/Confirmation code:\s*([^\n<]*)[\n<]/ims');
        $result['Passengers'] = beautifulName($http->FindPreg('/Schedule change impacting\s+(.+)\s+- confirmation code:/ims'));
        $textNodes = $xpath->query('//text()[contains(., "A schedule change has resulted")]/following-sibling::text()');
        $segments = [];
        $segment = [];

        foreach ($textNodes as $textNode) {
            $textNodeValue = CleanXMLValue($textNode->nodeValue);
            // Departing on: Jul 13
            if (preg_match('/Departing on:\s+(.*)/ims', $textNodeValue, $matches)) {
                if (!empty($segment)) {
                    $segments[] = $segment;
                    $segment = [];
                }
                $segment['DepDateText'] = $matches[1];

                continue;
            }
            // US Airways Flight # 157
            if (preg_match('/(.*)\s+Flight\s+#\s+(.*)$/ims', $textNodeValue, $matches)) {
                $segment['AirlineName'] = $matches[1];
                $segment['FlightNumber'] = $matches[2];

                continue;
            }
            // Depart: San Diego (SAN) at 11:00 AM
            if (preg_match('/Depart:\s+(.*)\s+\(([^\)]+)\)\s+at\s+(.*)/ims', $textNodeValue, $matches)) {
                $segment['DepName'] = $matches[1];
                $segment['DepCode'] = $matches[2];
                $segment['DepTimeText'] = $matches[3];

                continue;
            }
            // Arrive: Philadelphia (PHL) at 3:30 PM
            if (preg_match('/Arrive:\s+(.*)\s+\(([^\)]+)\)\s+at\s+(.*)/ims', $textNodeValue, $matches)) {
                $segment['ArrName'] = $matches[1];
                $segment['ArrCode'] = $matches[2];
                $segment['ArrTimeText'] = $matches[3];
            }

            if (!empty($segment['DepDateText']) && !empty($segment['DepTimeText']) && !empty($segment['ArrTimeText'])) {
                $segment['DepDate'] = strtotime($segment['DepDateText'] . ' ' . $segment['DepTimeText'], $this->date);

                //				if(strtotime(ArrayVal($segment, 'ArrTimeText')) > strtotime(ArrayVal($segment, 'DepTimeText'))){
//					$arriveTimeShift = SECONDS_PER_DAY;
//				}else{
//					$arriveTimeShift = 0;
//				}
                $segment['ArrDate'] = strtotime($segment['DepDateText'] . ' ' . $segment['ArrTimeText'], $this->date); // + $arriveTimeShift;

                unset($segment['DepDateText']);
                unset($segment['DepTimeText']);
                unset($segment['ArrTimeText']);
            }
        }

        if (!empty($segment)) {
            $segments[] = $segment;
        }

        if (count($segments) > 0) {
            $result['TripSegments'] = $segments;
        }

        return ["Itineraries" => [$result]];
    }

    public static function getEmailTypesCount()
    {
        return 9;
    }

    protected function ParseTravelConfirmation()
    {
        $result = ["Kind" => "T"];
        $result["RecordLocator"] = $this->http->FindSingleNode("//*[contains(text(), 'Travel Confirmation:')]/following-sibling::span[1]");
        $nodes = $this->http->XPath->query("//tr[contains(., 'Passenger Information') and not(.//tr)]/following-sibling::tr[not(contains(., 'Dividend Miles #'))]");
        $result["Passengers"] = [];
        $result["AccountNumbers"] = [];

        foreach ($nodes as $node) {
            $result["Passengers"][] = $this->http->FindSingleNode("td[1]", $node);
            $result["AccountNumbers"][] = $this->http->FindSingleNode("td[2]", $node);
        }
        $result["AccountNumbers"] = implode(",", $result["AccountNumbers"]);
        $result["BaseFare"] = str_ireplace(",", "", $this->http->FindSingleNode("//td[contains(text(), 'Fare')]/following-sibling::td[1]", null, true, "/[\d\.\,]+/"));
        $result["Tax"] = str_ireplace(",", "", $this->http->FindSingleNode("//td[contains(text(), 'Taxes & Fees')]/following-sibling::td[1]", null, true, "/[\d\.\,]+/"));
        $result["TotalCharge"] = str_ireplace(",", "", $this->http->FindSingleNode("//td[contains(text(), 'Grand Total')]/following-sibling::td[1]", null, true, "/[\d\.\,]+/"));
        $result["Currency"] = $this->http->FindSingleNode("//td[contains(text(), 'Grand Total')]/following-sibling::td[1]", null, true, "/^\D+/");
        $result["TripSegments"] = [];
        $idx = 0;
        $nodes = $this->http->XPath->query("//tr[contains(., 'Flight Itinerary') and not(.//tr)]/following-sibling::tr[not(contains(., 'Depart'))]");

        foreach ($nodes as $node) {
            if ($this->http->XPath->query("td", $node)->length > 1) {
                $segment = [];
                $datetime = $this->http->FindSingleNode("td[1]", $node);

                if (preg_match("/(\d+:\d+ [AP]M)(.+)/", $datetime, $m)) {
                    $segment["DepDate"] = strtotime($m[2] . " " . $m[1], $this->date);
                } else {
                    $segment["DepDate"] = "";
                }
                $segment["FlightNumber"] = $this->http->FindSingleNode("td[2]", $node);
                $text = $this->http->FindNodes("td[3]/text()", $node);

                if (isset($text[0]) && preg_match("/^([A-Z]{3})\/(.+)$/", $text[0], $m)) {
                    $segment["DepCode"] = $m[1];
                    $segment["DepName"] = $m[2];
                } else {
                    $segment["DepCode"] = "";
                    $segment["DepName"] = "";
                }

                if (isset($text[1])) {
                    $segment["Aircraft"] = $text[1];
                }
                $text = $this->http->FindSingleNode("td[4]", $node);

                if (preg_match("/^([A-Z]{3})\/(.+)$/", $text, $m)) {
                    $segment["ArrCode"] = $m[1];
                    $segment["ArrName"] = $m[2];
                } else {
                    $segment["ArrCode"] = "";
                    $segment["ArrName"] = "";
                }
                $datetime = $this->http->FindSingleNode("td[5]", $node);

                if (preg_match("/(\d+:\d+ [AP]M)(.+)/", $datetime, $m)) {
                    $segment["ArrDate"] = strtotime($m[2] . " " . $m[1], $this->date);
                } else {
                    $segment["ArrDate"] = "";
                }

                if (!empty($segment['DepDate']) && ($segment['DepDate'] < strtotime('- 5 months'))) {
                    $segment['DepDate'] = strtotime('+ 1 year', $segment['DepDate']);
                }

                if (!empty($segment['ArrDate']) && ($segment['ArrDate'] < strtotime('- 5 months'))) {
                    $segment['ArrDate'] = strtotime('+ 1 year', $segment['ArrDate']);
                }
                $result["TripSegments"][$idx] = $segment;
                $idx++;
            }

            if ($idx > 0 && $airline = $this->http->FindSingleNode("td[1]", $node, true, '/FLIGHT OPERATED BY (.+)/i')) {
                $result['TripSegments'][$idx - 1]["AirlineName"] = $airline;
            }
        }

        return ["Itineraries" => [$result]];
    }

    private function getEmailType(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindSingleNode("//text()[contains(., 'Travel Confirmation:')]")) {
            return "TravelConfirmation";
        }

        if ($this->http->XPath->query('//text()[contains(., "Your First Class upgrade status")]')->length > 0) {
            return "YourUpcomingItinerary";
        }

        if ($this->http->XPath->query('//tr[
            *[(self::td or self::th) and not(.//table)][contains(., "Flight")] and
            *[self::td or self::th][contains(., "Flight Itinerary")] and
            *[self::td or self::th][contains(., "Date")] and
            *[self::td or self::th][contains(., "Travel Duration")]
        ]/following-sibling::tr')->length > 0) {
            return 'Receipt';
        }

        if ($this->http->FindPreg("/Your\s+(?:Dividend\s+Miles\s+)?reservation|You(?:'|')re\s+confirmed/ims")) {
            if ($this->http->XPath->query('//img[contains(@src, "arrow_right") or contains(@src, "-arrow-")]')->length > 0) {
                return "YourReservation2";
            } elseif ($this->http->FindPreg("/Passenger name Frequent flyer #/ims")) {
                return "PlainYourReservation";
            } else {
                return "YourReservation";
            }
        }

        if (/*$this->http->FindSingleNode('//td[contains(.,"You\'re confirmed") and not(.//td) and ./following-sibling::td[contains(.,"Scan")]]')
        || */ $this->http->XPath->query('//div[contains(text(), "Confirmation code:")]/following-sibling::node()[1 and self::div]//tr[count(td) >= 2]')->length > 1) {
            return "MultipleReservation";
        }

        if ($this->http->FindNodes('//node()[contains(., "Booking Type:") and not(.//td) and count(./following-sibling::table) = 1]')) {
            return "ListingDetails";
        }

        if ($this->http->FindPreg('/Schedule change/ims')) {
            return "ScheduleChange";
        }

        return 'Undefined';
    }

    private function convertPlainToDom($plainText, $http)
    {
        $lines = explode("\n", $plainText);
        $document = new \DOMDocument();

        foreach ($lines as $line) {
            $document->appendChild($document->createTextNode($line));
        }
        $http->DOM = $document;
        $http->XPath = new \DOMXPath($this->http->DOM);
    }

    private function setPrice($data, &$currency, &$price)
    {
        if (preg_match('/([^\d]+)?(\d+.\d+|\d+)/ims', $data, $matches)) {
            if (!empty($matches[1])) {
                if ('$' === $matches[1]) {
                    $currency = 'USD';
                } else {
                    $currency = $matches[1];
                }
            }
            $price = $matches[2];

            return true;
        }

        return false;
    }

    private function CreateDOMbyNodeList(\DOMNodeList $nodes)
    {
        $browser = new \HttpBrowser("none", new \CurlDriver());
        $browser->LogMode = null;
        $browser->DOM = new \DOMDocument();

        foreach ($nodes as $node) {
            $browser->DOM->appendChild($browser->DOM->importNode($node, true));
        }
        $browser->XPath = new \DOMXPath($browser->DOM);

        return $browser;
    }
}
