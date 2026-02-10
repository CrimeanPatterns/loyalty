<?php

class TAccountCheckerBcd extends TAccountChecker
{
    private $kinds = ['Airline' => 'T', 'Hotel' => 'R'];
    private $confs = ['T' => 'RecordLocator', 'R' => 'ConfirmationNumber'];
    private $names = ['T' => 'Passengers', 'R' => 'GuestNames'];
    private $heads = ['T' => 'AIR', 'R' => 'HOTEL'];
    private $wd = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', ', '];
    private $airIndex;

    /*
        function ParsePlanEmail(PlancakeEmailParser $parser)
        {
            // $this->http->LiveDebug();
            $headers = $parser->getHeaders();
            $emailType = $this->getEmailType($parser->getHeader("subject"));
            switch ($emailType) {
                case "Text":
                    $result = $this->ParseEmailText($headers);
                    break;
                case "Html":
                    $result = $this->ParseEmailText($headers, 'Html');
                    break;
                case "Html 2":
                    $result = $this->ParseEmailText($headers, 'Html 2');
                    break;
                case "Html 3":
                    $result = $this->ParseEmailHtml($headers);
                    break;
                case "Html 4":
                    $result = $this->ParseEmailHtml4($headers);
                    break;
                case 'MMC':
                    $result = $this->ParseEmailMMC($headers);
                    break;
                default:
                    $result = array('Data' => $this->http);
                    // $result = 'Undefined email type';
                    break;
            }
            return array(
                'parsedData' => $result,
                'emailType' => $emailType
            );
        }
    */
    public function getEmailType()
    {
        if (count($this->http->FindNodes('//text()')) == 1) {
            return 'Text';
        }

        if (count($this->http->FindNodes("//p[@class='ecxMsoPlainText']")) > 0) {
            return 'Html';
        }

        if (count($this->http->FindNodes("//p[@class='MsoPlainText']")) > 0) {
            return 'Html 2';
        }

        if ((count($this->http->FindNodes("//p[@class='MsoNormal']")) > 0) && ($this->http->FindPreg("/Virtually There®/ims"))) {
            return 'Html 4';
        }

        if (count($this->http->FindNodes("//p[@class='MsoNormal']")) > 0) {
            return 'Html 3';
        }

        if (count($this->http->FindNodes("//img[contains(@src, 'BCD_MMC')]")) > 0) {
            return "MMC";
        }

        return 'Undefined';
    }

    public function ParseEmailText($headers, $html = false)
    {
        $props = [];
        $its = [];

        if ($html) {
            $this->http->Response['body'] = preg_replace('/(<.*?>)/ims', "\r\n", $this->http->Response['body']);
        }
        $body = preg_split('/[\r\n]+/', $this->http->Response['body']);
        $body = array_map('CleanXMLValue', $body);

        foreach ($body as $k => $v) {
            if (preg_match('/^\s*$/ims', $v)) {
                unset($body[$k]);
            }
        }

        if ($html == 'Html 2') {
            foreach ($body as $k => $v) {
                if (strlen($v) < 3) {
                    unset($body[$k]);
                }
            }
        }
        $body = array_values($body);
        $body = array_filter($body, 'strlen');
        // var_dump($body);

        $i = 1;

        while (isset($body[$i]) && !preg_match('(CONFIRMATION NUMBERS)', $body[$i], $matches)) {
            $i++;
        }

        if ($i < count($body)) {
            $i += 2;
            $airFound = false;

            while (!preg_match('/\*{4,4}/ims', $body[$i])) {
                $it = [];
                $s = $body[$i];

                if (preg_match('/^(.*?)\s.*?#.*?\s+(.*?)\s*\((.*?)\)/is', $s, $matches)) {
                    $it['Kind'] = $this->kinds[$matches[1]];

                    if ($it['Kind'] == 'R' or ($it['Kind'] == 'T' and !$airFound)) {
                        $it[$this->confs[$it['Kind']]] = substr($matches[2], strpos($matches[2], '-') + 1);
                        $provider = $matches[3];
                        $it['ProviderCode'] = $provider; // TODO: Get from DB?
                        $it['ReservationDate'] = strtotime($headers['date']);
                        $its[] = $it;
                        $airFound = $it['Kind'] == 'T';

                        if ($airFound) {
                            $this->airIndex = count($its) - 1;
                        }
                    }
                    $i++;
                }
            }

            while (!preg_match('/Name\(s\)/ims', $body[$i++]));
            $names = [];

            while (!preg_match('/\*{4,4}/ims', $body[$i])) {
                if (preg_match('/^Name:\s*(.*)$/ims', $body[$i], $matches)) {
                    $names[] = str_replace(',', '', $matches[1]);
                }
                $i++;
            }
            $names = implode(', ', $names);

            foreach ($its as &$v) {
                $v[$this->names[$v['Kind']]] = $names;
            }

            while (!preg_match('/ITINERARY/ims', $body[$i++]));

            while (!preg_match('/\*{4,4}/ims', $body[$i])) {
                if (in_array($body[$i], $this->heads)) {
                    if (isset($ts)) {
                        $this->pushSegment($ts, $its);
                    }
                    $ts = ['type' => $body[$i]];
                } else {
                    // *** Trip Segment ***
                    // Flight/Equip.: Virgin Australia 909
                    if (preg_match('/^Flight\/Equip\.:\s+(.*?)\s+([\w\d]+\d)(\s(.*)\s+[\w\d]+){0,1}/is', $body[$i], $match)) {
                        $ts['AirlineName'] = $match[1];
                        $ts['FlightNumber'] = $match[2];

                        if (isset($match[3])) {
                            $ts['Aircraft'] = $match[3];
                        }
                    }
                    // Depart: Sydney(SYD) Wednesday, 7 Aug 7:00 AM
                    if (preg_match('/^Depart:\s+(.*?)(\(.*?\))\s+(.*)/is', $body[$i], $match)) {
                        $ts['DepName'] = $match[1];
                        $ts['DepCode'] = $match[2];

                        if (preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['DepDate'] = strtotime($matches[2] . ' ' . $matches[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        } elseif (preg_match('/(.*?\,){0,1}\s+(\w+)\s+(\d+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['DepDate'] = strtotime($matches[3] . ' ' . $matches[2] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        }
                    }
                    // Arrive: Brisbane(BNE) Wednesday, 7 Aug 8:30 AM
                    if (preg_match('/^Arrive:\s+(.*?)(\(.*?\))\s+(.*)/is', $body[$i], $match)) {
                        $ts['ArrName'] = $match[1];
                        $ts['ArrCode'] = $match[2];

                        if (preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['ArrDate'] = strtotime($matches[2] . ' ' . $matches[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        } elseif (preg_match('/(.*?\,){0,1}\s+(\w+)\s+(\d+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['ArrDate'] = strtotime($matches[3] . ' ' . $matches[2] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        }
                    }
                    // Stops: non-stop;     Miles: 467
                    if (preg_match('/^Stops:\s+(.*);/is', $body[$i], $match) or preg_match('/^Stops:\s+(.*)Miles/ims', $body[$i], $match) or preg_match('/^Stops:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Stops'] = $match[1];
                    }

                    if (preg_match('/Miles:\s+(.*)/is', $body[$i], $match)) {
                        $ts['Miles'] = $match[1];
                    }
                    // Class: Economy
                    if (preg_match('/^Class:\s+(.*)/is', $body[$i], $match)) {
                        $ts['Cabin'] = $match[1];
                    }
                    // Status: Confirmed
                    if (preg_match('/^Status:\s+(.*)/is', $body[$i], $match)) {
                        $ts['Status'] = $match[1];
                    }
                    // Seats Requested: 13F
                    if (preg_match('/^Seats Requested:\s+(.*)/is', $body[$i], $match)) {
                        $ts['Seats'] = $match[1];
                    }

                    // *** Hotel Reservation ***
                    if (preg_match('/^Name:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['HotelName'] = $match[1];
                    }

                    if (preg_match('/^Address:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Address'] = $match[1];
                    }
                    // Check-in: Wednesday, 7 Aug 2:00 PM
                    if (preg_match('/Check-in:(.*)/ims', $body[$i], $match)) {
                        preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $match[1], $matches);
                        $ts['CheckInDate'] = strtotime($matches[2] . ' ' . $matches[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                    }
                    // Check-out: Thursday, 8 Aug 10:00 AM
                    if (preg_match('/Check-out:(.*)/ims', $body[$i], $match)) {
                        preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $match[1], $matches);
                        $ts['CheckOutDate'] = strtotime($matches[2] . ' ' . $matches[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                    }

                    if (preg_match('/^Phone:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Phone'] = $match[1];
                    }

                    if (preg_match('/^Fax:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Fax'] = $match[1];
                    }

                    if (preg_match('/Rate:/is', $body[$i])) {
                        preg_match('/(\d.*)/ims', $body[++$i], $match);
                        $ts['Rate'] = $match[1];
                    }
                }
                $i++;
            }
            $this->pushSegment($ts, $its);
        }
        $i++;

        while ($i < count($body)) {
            if (preg_match('/^Base Airfare \(per person\)\s+([\d\.]*)\s+(.*)/is', $body[$i], $match)) {
                $its[$this->airIndex]['BaseFare'] = $match[1];
                $its[$this->airIndex]['Currency'] = $match[2];
            }

            if (preg_match('/^Total Taxes and\/or Applicable fees \(per person\)\s+([\d\.]*)\s+(.*)/is', $body[$i], $match)) {
                $its[$this->airIndex]['Tax'] = $match[1];
                // there is no overheads in parsers...
                $its[$this->airIndex]['Currency'] = $match[2];
            }

            if (preg_match('/^Total Flight \(per person\)\s+([\d\.]*)\s+(.*)/is', $body[$i], $match)) {
                $its[$this->airIndex]['TotalCharge'] = $match[1];
                $its[$this->airIndex]['Currency'] = $match[2];
            }
            $i++;
        }

        // $its[] = $it;

        return ['Itineraries' => $its, 'Properties' => $props];
    }

    public function ParseEmailHtml($headers)
    {
        $td2 = 'td[2]/descendant::span[1]/text()[1]';

        $props = [];
        $air = ['Kind' => 'T'];

        $air['ReservationDate'] = strtotime($headers['date']);
        $airlineName = $this->http->FindSingleNode('//span[contains(text(), "ElectronicTicket Number:")]', null, true, '/This ticket information applies to the following trip\(s\):\s+(.*?)\s+Flight/ims');
        $air['AirlineName'] = $airlineName;
        $total = $this->http->FindSingleNode('//span[contains(text(), "Total Invoice Amount:")]', null, true, '/Total Invoice Amount:\s+(.*)/ims');
        preg_match('/(\d+\.\d+)\s+(.*)/is', $total, $matches);
        $air['TotalCharge'] = $matches[1];
        $air['Currency'] = $matches[2];
        $air['BaseFare'] = $this->http->FindSingleNode('//span[contains(text(), "ElectronicTicket Number:")]', null, true, '/Ticket Amount:\s+(\d+.\d+)/ims');

        // Determining AirCodes
        $DepCodes = [];
        $ArrCodes = [];
        $bTypes = [];
        $codeLines = $this->http->XPath->query('//span[contains(text(), "Summary")]/ancestor::table[3]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[last()]/descendant::table[1]/descendant::tr[1]/following-sibling::tr');

        foreach ($codeLines as $codeLine) {
            if ($code = $this->http->FindSingleNode('td[2]/descendant::span[1]', $codeLine)) {
                if (preg_match('/(.*)\-(.*)/is', $code, $matches)) {
                    $DepCodes[] = $matches[1];
                    $ArrCodes[] = $matches[2];
                }
            }

            if ($btype = $this->http->FindSingleNode('td[6]/descendant::span[1]', $codeLine, true, '/^.*?\s*\/\s*(.*)$/ims')) {
                $bTypes[] = $btype;
            }
        }

        // Parsing data
        $nodes = $this->http->XPath->query('//span[contains(text(), "Add to Calendar")]/ancestor::table[3]');

        foreach ($nodes as $node) {
            $ts = [];
            $title = $this->http->FindSingleNode("descendant::table[1]/descendant::span[1]", $node);
            preg_match('/(.*?)\s/is', $title, $type);

            switch ($type[1]) {
                case 'HOTEL':
                    $hotel = ['Kind' => 'R'];
                    $hotel['ReservationDate'] = strtotime($headers['date']);
                    $subnodes = $this->http->XPath->query("tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1]/descendant::tr", $node);
                    $i = 0;

                    while ($i < $subnodes->length) {
                        if ($i == 0) {
                            $hotel['HotelName'] = $this->http->FindSingleNode('descendant::span[1]', $subnodes->item($i++));
                        } else {
                            switch ($this->http->FindSingleNode('td[1]/descendant::span', $subnodes->item($i))) {
                                case 'Address:':
                                    $hotel['Address'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Tel:':
                                    $hotel['Phone'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Fax:':
                                    $hotel['Fax'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Check In/Check Out:':
                                    $dates = $this->http->FindSingleNode($td2, $subnodes->item($i++));
                                    preg_match('/(.*)\s+\-\s+(.*)/ims', $dates, $matches);
                                    $hotel['CheckInDate'] = strtotime($matches[1]);
                                    $hotel['CheckOutDate'] = strtotime($matches[2]);

                                    break;

                                case 'Status:':
                                    $hotel['Status'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Number of Persons:':
                                    $hotel['Guests'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Number of Rooms:':
                                    $hotel['Rooms'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Rate per night:':
                                    $hotel['Rate'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Confirmation:':
                                    $hotel['ConfirmationNumber'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Description:':
                                    $hotel['RoomTypeDescription'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                default:
                                    $i++;
                            }
                        }
                    }

                    break;

                case 'AIR':
                    // preg_match('/Agency Record Locator\s+(.*)/is', $title, $conf);
                    // $air['RecordLocator'] = $conf[1];
                    // print_r($this->http->FindSingleNode("tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1]/descendant::tr", $node));
                    $subnodes = $this->http->XPath->query("tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1 or 2]/descendant::tr", $node);
                    $i = 0;

                    while ($i < $subnodes->length) {
                        $s = $subnodes->item($i)->nodeValue;

                        if ($i == 0) {
                            preg_match('/Flight\s+([\d\w]+).*?([\w\d]*)\s+Class/ims', $s, $matches);
                            $ts['FlightNumber'] = $matches[1];
                            $ts['Cabin'] = $matches[2];
                            $i++;
                        } else {
                            switch ($this->http->FindSingleNode('td[1]/descendant::span', $subnodes->item($i))) {
                                case "Depart:":
                                    $ts['DepName'] = $this->http->FindSingleNode($td2, $subnodes->item($i));
                                    $i++;
                                    $d = str_replace($this->wd, '', $subnodes->item($i)->nodeValue);
                                    $ts['DepDate'] = strtotime($d);
                                    $i++;

                                    break;

                                case "Arrive:":
                                    $ts['ArrName'] = $this->http->FindSingleNode($td2, $subnodes->item($i));
                                    $i++;
                                    $d = str_replace($this->wd, '', $subnodes->item($i)->nodeValue);
                                    $ts['ArrDate'] = strtotime($d);
                                    $i++;

                                    break;

                                case "Duration:":
                                    $ts['Duration'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case "Status:":
                                    preg_match('/Status:\s+(.*?)\s+\-.*?Locator:\s+([\w\d]+)/ims', $subnodes->item($i++)->nodeValue, $matches);
                                    $ts['Status'] = $matches[1];
                                    $air['RecordLocator'] = $matches[2];

                                    break;

                                case "Equipment:":
                                    $ts['Aircraft'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case "Seat:":
                                    $ts['Seats'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case "Distance:":
                                    $ts['TraveledMiles'] = str_replace(' miles', '', $this->http->FindSingleNode($td2, $subnodes->item($i++)));

                                    break;

                                default:
                                    $i++;
                            }
                        }
                    }
                    $air['TripSegments'][] = $ts;
                    $lastId = count($air['TripSegments']) - 1;
                    $air['TripSegments'][$lastId]['DepCode'] = $DepCodes[$lastId];
                    $air['TripSegments'][$lastId]['ArrCode'] = $ArrCodes[$lastId];
                    $air['TripSegments'][$lastId]['BookingClass'] = $bTypes[$lastId];

                    break;

                default:
                    DieTrace('NEW RESERVATION TYPE, PLEASE NOTICE THIS TRACE');
            }
        }

        if ($rd = $this->http->FindSingleNode('//b[contains(text(), "Invoice Date:")]/ancestor::span[1]/text()[1]')) {
            $air['ReservationDate'] = strtotime($rd);
        }

        $its[] = $air;

        if (isset($hotel)) {
            if (isset($rd)) {
                $hotel['ReservationDate'] = strtotime($rd);
            }
            $its[] = $hotel;
        }

        return ['Itineraries' => $its, 'Properties' => $props];
    }

    public function ParseEmailMMC($headers)
    {
        // $this->http->LiveDebug();
        $its = [];
        $props = [];
        $list = [];

        $name = $this->http->FindSingleNode('//td[contains(text(), "Traveler")]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]');
        $listNodes = $this->http->XPath->query('//td[contains(text(), "Traveler")]/ancestor::table[1]/descendant::tr[1]/following-sibling::tr[last()]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[position() > 1]');

        for ($i = 0; $i < $listNodes->length; $i++) {
            $l = [];
            $l['From-To'] = $this->http->FindSingleNode('td[2]', $listNodes->item($i));
            $l['Flight/Vendor'] = $this->http->FindSingleNode('td[3]', $listNodes->item($i));
            $l['Status'] = $this->http->FindSingleNode('td[4]', $listNodes->item($i));
            $l['Depart/Arrive'] = $this->http->FindSingleNode('td[5]', $listNodes->item($i));
            $l['Class/Type'] = $this->http->FindSingleNode('td[6]', $listNodes->item($i));
            $list[] = $l;
        }
        $nodes = $this->http->XPath->query('//table[2]/descendant::tr[1]/following-sibling::tr[position() > 2]/descendant::tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::tbody[1]/descendant::tbody[1]');
        $weirdNodes = $this->http->XPath->query('//table[2]/descendant::tr[1]/following-sibling::tr[position() > 2]/descendant::tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::tbody[1]/descendant::tbody[2]');
        $listCount = 0;
        $w = 0;

        for ($i = 0; $i < $nodes->length; $i++) {
            // Case AirTrip
            if ('Depart:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))) {
                if (!isset($air)) {
                    $air = ['Kind' => 'T', 'Passengers' => $name, 'ReservationDate' => strtotime($headers['date'])];
                }
                $ts = [];
                $ts['FlightNumber'] = $list[$listCount]['Flight/Vendor'];
                $air['Status'] = $list[$listCount]['Status'];
                $ts['DepCode'] = substr($list[$listCount]['From-To'], 0, strpos($list[$listCount]['From-To'], '-'));
                $ts['ArrCode'] = substr($list[$listCount]['From-To'], strpos($list[$listCount]['From-To'], '-') + 1);

                if (preg_match('/(.*?)\s*\/\s*(.*)/ims', $list[$listCount]['Class/Type'], $match)) {
                    $ts['Cabin'] = $match[1];
                    $ts['BookingClass'] = $match[2];
                } else {
                    $ts['Cabin'] = $list[$listCount]['Class/Type'];
                }
                $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                $weirdLength = 0;

                if ($weirdNodes->length > 0) {
                    $subWeirdNodes = $this->http->XPath->query('tr', $weirdNodes->item($w));
                    $weirdLength = $subWeirdNodes->length;
                    $w++;
                }
                $j = 0;

                while ($j < $subNodes->length + $weirdLength) {
                    if ($j < $subNodes->length) {
                        $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                    } else {
                        $td1 = $this->http->FindSingleNode('td[1]', $subWeirdNodes->item($j - $subNodes->length));
                    }

                    if ($j < $subNodes->length) {
                        $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                    } else {
                        $td2 = $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length));
                    }

                    if ($j == 0) {
                        preg_match('/^(.*?)\s+Flight/ims', $td1, $matches);
                        $ts['AirlineName'] = $matches[1];
                    } else {
                        switch ($td1) {
                            case 'Depart:':
                                $ts['DepName'] = $td2;
                                $j++;
                                $ts['DepDate'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subNodes->item($j))));

                                break;

                            case 'Arrive:':
                                $ts['ArrName'] = $td2;
                                $j++;

                                if ($j < $subNodes->length) {
                                    $ts['ArrDate'] = strtotime($this->http->FindSingleNode('td[2]', $subNodes->item($j)));
                                } else {
                                    $ts['ArrDate'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length))));
                                }

                                break;

                            case 'Duration:':
                                $ts['Duration'] = $td2;

                                break;

                            case 'Status:':
                                preg_match('/Locator:\s+(.*)/ims', $td2, $matches);
                                $air['RecordLocator'] = $matches[1];

                                break;

                            case 'Equipment:':
                                $ts['Aircraft'] = $td2;

                                break;

                            case 'Seat:':
                                $ts['Seats'] = $td2;

                                break;

                            case 'Distance:':
                                $ts['TraveledMiles'] = $td2;

                                break;
                        }
                    }
                    $j++;
                }
                $air['TripSegments'][] = $ts;
                $listCount++;
            }
            // Case Rental
            elseif ('Pick Up:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))) {
                $rental = [
                    'Kind'            => 'L',
                    'ReservationDate' => strtotime($headers['date']),
                    'RentalCompany'   => $list[$listCount]['Flight/Vendor'],
                    'RenterName'      => $name,
                ];

                $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                $weirdLength = 0;

                if ($weirdNodes->length > 0) {
                    $subWeirdNodes = $this->http->XPath->query('tr', $weirdNodes->item($w));
                    $weirdLength = $subWeirdNodes->length;
                    $w++;
                }
                $j = 0;

                while ($j < $subNodes->length + $weirdLength) {
                    if ($j < $subNodes->length) {
                        $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                    } else {
                        $td1 = $this->http->FindSingleNode('td[1]', $subWeirdNodes->item($j - $subNodes->length));
                    }

                    if ($j < $subNodes->length) {
                        $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                    } else {
                        $td2 = $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length));
                    }

                    switch ($td1) {
                        case 'Confirmation:':
                            $rental['Number'] = $td2;

                            break;

                        case 'Pick Up:':
                            preg_match('/(.*);\s+Tel:\s+(.*);\s+Fax:\s+(.*)/ims', $td2, $matches);
                            $rental['PickupLocation'] = $matches[1];
                            $rental['PickupPhone'] = $matches[2];
                            $rental['PickupFax'] = $matches[3];
                            $j++;
                            $rental['PickupDatetime'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subNodes->item($j))));

                            break;

                        case 'Drop Off:':
                            preg_match('/(.*);\s+Tel:\s+(.*);\s+Fax:\s+(.*)/ims', $td2, $matches);
                            $rental['DropoffLocation'] = $matches[1];
                            $rental['DropoffPhone'] = $matches[2];
                            $rental['DropoffFax'] = $matches[3];
                            $j++;

                            if ($j < $subNodes->length) {
                                $rental['DropoffDatetime'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subNodes->item($j))));
                            } else {
                                $rental['DropoffDatetime'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length))));
                            }

                            break;

                        case 'Type:':
                            $rental['CarType'] = $td2;

                            break;

                        case 'Status:':
                            $rental['Status'] = $td2;

                            break;

                        case 'Estimated Total:':
                            preg_match('/^(.*)\s+([\d\.]+)/ims', $td2, $matches);
                            $rental['TotalCharge'] = $matches[2];
                            $rental['Currency'] = $matches[1];

                            break;

                        case 'Extra Charges:':
                            $rental['Fees'] = [0 => ['Name' => 'Extra Charges', 'Charge' => $td2]];

                            break;
                    }
                    $j++;
                }

                $its[] = $rental;
                $listCount++;
            }
            // Case Hotel
            elseif ('Address:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))) {
                $hotel = [
                    'Kind'            => 'R',
                    'ReservationDate' => strtotime($headers['date']),
                    'HotelName'       => $list[$listCount]['Flight/Vendor'],
                    'GuestNames'      => $name,
                ];
                $hf = [
                    'Address:'              => 'Address',
                    'Tel:'                  => 'Phone',
                    'Fax:'                  => 'Fax',
                    'Confirmation:'         => 'ConfirmationNumber',
                    'Number of Persons:'    => 'Guests',
                    'Number of Rooms:'      => 'Rooms',
                    'Rate per night:'       => 'Rate',
                    'Remarks:'              => 'CancellationPolicy',
                    'Description:'          => 'RoomTypeDescription',
                ];
                $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                $j = 0;

                while ($j < $subNodes->length) {
                    $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                    $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));

                    if (array_key_exists($td1, $hf)) {
                        $hotel[$hf[$td1]] = $td2;
                    } else {
                        switch ($td1) {
                            case 'Check In/Check Out:':
                                preg_match('/(.*)\s+\-\s+(.*)/ims', $td2, $matches);
                                $hotel['CheckInDate'] = strtotime(str_replace($this->wd, '', $matches[1]));
                                $hotel['CheckOutDate'] = strtotime(str_replace($this->wd, '', $matches[2]));

                                break;
                        }
                    }
                    $j++;
                }
                $its[] = $hotel;
                $listCount++;
            }
        }

        if (isset($air)) {
            $its[] = $air;
        }

        return ['Itineraries' => $its, 'Properties' => $props];
    }

    public function processDate($year, $month, $day, $time, $controlDate)
    {
        $res = strtotime($year . '-' . $month . '-' . $day . ' ' . $time);
        $diff = $res - strtotime($controlDate);

        if ($diff < (-365 / 2) * 24 * 60 * 60) {
            return strtotime(($year + 1) . '-' . $month . '-' . $day . ' ' . $time);
        } else {
            return $res;
        }
    }

    public function ParseEmailHtml4($headers)
    {
        $its = [];
        $props = [];
        $conf = $this->http->FindPreg('/Código de reservación:\s+(.*?)</ims');
        $this->http->Response['body'] = preg_replace('/(<.*?>)/ims', "\r\n", $this->http->Response['body']);
        $body = preg_split('/[\r\n]+/', $this->http->Response['body']);
        $body = array_map('CleanXMLValue', $body);

        foreach ($body as $k => $v) {
            if (preg_match('/^\s*$/ims', $v)) {
                unset($body[$k]);
            }
        }
        $body = array_values($body);
        $body = array_filter($body, 'strlen');
        // var_dump($body);
        $i = 0;

        while ($body[$i] != 'Itinerario') {
            $i++;
        }
        $trip = ['Kind' => 'T'];
        $scan = true;

        while ($scan) {
            $i++;
            $state = 'parse';

            if ($body[$i] == 'OBSERVACIONES DEL ORGANIZADOR:') {
                $state = 'stop';
            } elseif (strpos($body[$i], 'Vuelos:') === 0) {
                $state = 'newAir';
            } elseif (strpos($body[$i], 'Hotel y') === 0) {
                $state = 'newHotel';
            }

            if ($state == 'parse') {
                if (preg_match('/^Desde:\s+(.*)\((.*)\)/ims', $body[$i], $match)) {
                    $it['DepName'] = $match[1];
                    $it['DepCode'] = $match[2];
                } elseif (preg_match('/^Hasta:\s+(.*)\((.*)\)/ims', $body[$i], $match)) {
                    $it['ArrName'] = $match[1];
                    $it['ArrCode'] = $match[2];
                } elseif (preg_match('/^Confirmación de aerolínea:\s*(.*)/ims', $body[$i], $match)) {
                    $trip['RecordLocator'] = $match[1];
                } elseif (preg_match('/^Sale:\s*(.*)/ims', $body[$i], $match)) {
                    $it['DepDate'] = $this->processDate($fy, $fm, $fd, $match[1], $headers['date']);
                } elseif (preg_match('/^Llega:\s*(.*)/ims', $body[$i], $match)) {
                    $it['ArrDate'] = $this->processDate($fy, $fm, $fd, $match[1], $headers['date']);

                    if ($it['ArrDate'] < $it['DepDate']) {
                        $it['ArrDate'] += 24 * 60 * 60;
                    }
                } elseif (preg_match('/^Clase:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Cabin'] = $match[1];
                } elseif (preg_match('/^Asiento:/ims', $body[$i], $match)) {
                    $it['Seats'] = $body[$i + 1];
                } elseif (preg_match('/^Estado:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Status'] = $match[1];
                } elseif (preg_match('/^Comida:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Meal'] = $match[1];
                } elseif (preg_match('/^Se permite fumar:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Smoking'] = (strtolower($match[1]) != 'no');
                } elseif (preg_match('/^Aeronave:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Aircraft'] = $match[1];
                } elseif (preg_match('/^Millaje:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Mileage'] = $match[1];
                } elseif (preg_match('/^Duración:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Duration'] = $match[1];
                }
                // Hotel
                elseif (preg_match('/^Dirección:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Address'] = $match[1] . ' ' . $body[$i + 2];
                } elseif (preg_match('/^Teléfono:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Phone'] = $match[1];
                } elseif (preg_match('/^FAX:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Fax'] = $match[1];
                } elseif (preg_match('/^Habitación\(es\):\s*(.*)/ims', $body[$i], $match)) {
                    $it['Rooms'] = $match[1];
                } elseif (preg_match('/^Tarifa:\s*(.*)/ims', $body[$i], $match)) {
                    $it['Rate'] = $match[1];
                } elseif (preg_match('/^Confirmación:\s*(.*)/ims', $body[$i], $match)) {
                    $it['ConfirmationNumber'] = $match[1];
                } elseif (preg_match('/^Cancelación:\s*(.*)/ims', $body[$i], $match)) {
                    $it['CancellationPolicy'] = $match[1];
                } elseif (preg_match('/^Detalles de las habitaciones:\s*(.*)/ims', $body[$i], $match)) {
                    $it['RoomTypeDescription'] = $match[1];

                    if (preg_match('/(\d+\.\d+)\s+(\w+)\s+APPROX\. TTL PRICE/ims', $body[$i], $match)) {
                        $it['Currency'] = $match[2];
                        $it['TotalCharge'] = $match[1];
                    }

                    if (preg_match('/(\d+\.\d+) TTL TAX/ims', $body[$i], $match)) {
                        $it['Tax'] = $match[1];
                    }
                }
            } else {
                if (isset($it)) {
                    if ($it['Kind'] == 'T') {
                        if (isset($it['Status'])) {
                            $trip['Status'] = $it['Status'];
                            unset($it['Status']);
                        }
                        unset($it['Kind']);
                        $trip['TripSegments'][] = $it;

                        if ($state != 'newAir') {
                            $its[] = $trip;
                            $trip = ['Kind' => 'T'];
                        }
                    } else {
                        $its[] = $it;
                    }
                }
                $it = [];
                $fy = date("Y", strtotime($headers['date']));
                /* $d = str_replace(
                    array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic', ', ', ' ','lun', 'mar', 'mié', 'mie', 'jue', 'vie', 'sáb', 'sab', 'dom'),
                    array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', '', '-', ''),
                    $body[$i-1]);
                if (strtotime($d.'-'.$oyear) < time())
                    $oyear++;
                $d = $d.'-'.$oyear;
                $it['Debug'] = strtotime($d); */
                if ($state != 'stop') {
                    preg_match('/^.*?,\s+(.*?)\s+(\d+)( - .*?,\s+(.*?)\s+(\d+))*$/ims', $body[$i - 1], $match);
                    $spm = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
                    $fm = array_search($match[1], $spm) + 1;
                    $fd = $match[2];

                    if (isset($match[3])) {
                        $tm = array_search($match[4], $spm) + 1;
                        $td = $match[5];
                    }

                    if ($state == 'newHotel') {
                        $it['Kind'] = 'R';

                        if (preg_match('/Hotel.*?:\s+(.*)/ims', $body[$i], $match)) {
                            $it['HotelName'] = $match[1];
                        }
                        $it['CheckInDate'] = $this->processDate($fy, $fm, $fd, '00:00:00', $headers['date']);
                        $it['CheckOutDate'] = $this->processDate($fy, $tm, $td, '00:00:00', $headers['date']);
                    }

                    if ($state == 'newAir') {
                        $it['Kind'] = 'T';

                        if (preg_match('/Vuelos:\s+(.*)/ims', $body[$i], $match)) {
                            $it['AirlineName'] = $match[1];
                        }
                    }
                }
            }
            $scan = ($state != 'stop');
        }

        return ['Itineraries' => $its, 'Properties' => $props];
    }

    private function pushSegment($ts, &$its)
    {
        if ($ts['type'] == 'HOTEL') {
            $j = 0;

            while ($its[$j]['Kind'] != 'R') {
                $j++;
            }
            unset($ts['type']);
            $its[$j] = array_merge($its[$j], $ts);
        } elseif ($ts['type'] == 'AIR') {
            // for ($j = 0; $its[$j]['ProviderCode'] != $ts['AirlineName']; $j++);
            unset($ts['type']);

            if (isset($ts['Status'])) {
                $its[$this->airIndex]['Status'] = $ts['Status'];
                unset($ts['Status']);
            }
            $its[$this->airIndex]['TripSegments'][] = $ts;
        }
    }
}
