<?php

namespace AwardWallet\Engine\bcd\Email;

// parsers with similar formats: bcd/TravelPlanPdf, bcd/TravelReceiptPdf, bcd/TravelReceiptPdf2

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "bcd/it-3.eml";
    private $kinds = ['Airline' => 'T', 'Hotel' => 'R'];
    private $confs = ['T' => 'RecordLocator', 'R' => 'ConfirmationNumber'];
    private $names = ['T' => 'Passengers', 'R' => 'GuestNames'];
    private $heads = ['T' => 'AIR', 'R' => 'HOTEL', 'L' => 'CAR'];
    private $wd = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', ', '];
    private $airIndex;

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        // $this->http->LiveDebug();

        $headers = $parser->getHeaders();
        $emailType = $this->getEmailType($parser); //->getHeader("subject"));
        $this->logger->debug($emailType);

        switch ($emailType) {
            case "Text":
                $result = $this->ParseEmailText($headers);

                break;

            case "Text**":
                $result = $this->ParseEmailTextStars($parser);

                break;

            case "Html":
                $result = $this->ParseEmailText($headers, 'Html');

                break;

            case "Html 2":
                $result = $this->ParseEmailText($headers, 'Html 2');

                break;
            /*
            case "Html 3": // replaced by new format
                $result = $this->ParseEmailHtml($headers);
                break;
                */
            case "Html French":
                $result = $this->ParseEmailFrench($headers);

                break;

            case "Html German":
                $result = $this->ParseEmailGerman($headers);

                break;

            case "TNEF":
                $result = $this->ParseEmailGerman($headers);

                break;
                /* // replaced by new format parser
            case "Html 4":
                $result = $this->ParseEmailHtml4($headers);
                break;
                */
                /* // replaced by new format parser
            case 'MMC':
                $result = $this->ParseEmailMMC($headers);
                break;
            case 'Employee Travel':
                $result = $this->ParseEmailET($parser);
                break;  */
            default:
                $result = []; //'Data' => $this->http);
                // $result = 'Undefined email type';
                                //$result = $this->ParseEmailHtml4($headers);
                break;
        }

        return [
            'parsedData' => $result,
            'emailType'  => $emailType,
        ];
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        if (!$parser->getHtmlBody() && preg_match("#\n\s*\*{5,}#", $parser->getPlainBody())) {
            return 'Text**';
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

        if (count($this->http->FindNodes("//p[@class='MsoNormal']")) > 0 && $this->http->FindPreg("#Reiseplan#")) {
            return 'Html German';
        }

        if (count($this->http->FindNodes("//p[@class='MsoNormal']")) > 0 && $this->http->FindPreg("#Billet electronique#")) {
            return 'Html French';
        }

        if (count($this->http->FindNodes("//p[@class='MsoNormal']")) > 0) {
            return 'Html 3';
        }

        if (count($this->http->FindNodes("//img[contains(@src, 'BCD_MMC')]")) > 0) {
            return "MMC";
        }

        if ($this->http->FindPreg("#YOUR ITINERARY FOLLOWS#")) {
            return "MMC";
        }

        if (preg_match("#Es obliegt Ihrer Verantwortung#", $parser->getPlainBody())) {
            $this->xAttachments($parser);

            return "TNEF";
        }

        if (preg_match("/Employee Travel/i", $parser->getPlainBody())) {
            return "Employee Travel";
        }

        if (count($this->http->FindNodes('//text()')) == 1) {
            return 'Text';
        }

        return 'Undefined';
    }

    public function ParseEmailTextStars($parser)
    {
        $all = [];
        $text = $parser->getPlainBody();

        $its = preg_match("#ITINERARY\s+(.*?)\s\*{5,}#ms", $text, $m) ? $m[1] : null;
        $its = preg_split("#\n{2,}#", $its);

        foreach ($its as $body) {
            if (preg_match("#^HOTEL#", $body)) {
                $it = ['Kind' => 'R'];

                $it['HotelName'] = preg_match("#\nName:\s*(.*?)\s+Location:#", $text, $m) ? $m[1] : null;

                $it['Address'] = preg_match("#\nAddress:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                $it['CheckInDate'] = preg_match("#\nCheck\-in:\s*\w+,\s*(\d+\s+\w{3})\s+(\d+:\d+)#", $text, $m) ? strtotime($m[1] . ',' . $m[2]) : null;

                $it['CheckOutDate'] = preg_match("#\nCheck\-out:\s*\w+,\s*(\d+\s+\w{3})\s+(\d+:\d+)#", $text, $m) ? strtotime($m[1] . ',' . $m[2]) : null;

                $it['ConfirmationNumber'] = preg_match("#\nHotel Confirmation \#:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                $it['Phone'] = preg_match("#\nPhone:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                $it['Fax'] = preg_match("#\nFax:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                $it['Rate'] = preg_match("#\nAverage Rate:\s*([^\n]+)#", $text, $m) ? trim($m[1]) : null;

                $it['GuestNames'] = preg_match("#\nName:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                $all[] = $it;
            }

            if (preg_match("#^AIR#", $body)) {
                $it = ['Kind' => 'T'];

                $it['Passengers'] = preg_match("#\nName:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
                $it['TripSegments'] = [];

                $seg = [];

                if (preg_match("#Flight/Equip.:\s*(.*?)\s*(\d+)\s{3,}\s+([^\n]+)#", $body, $m)) {
                    $seg['FlightNumber'] = $m[2];
                    $seg['AirlineName'] = $m[1];
                    $seg['Aircraft'] = $m[3];

                    $it['RecordLocator'] = preg_match("#\d+\s+([\w\d\-]+)\s+\($m[1]\)#", $text, $m) ? $m[1] : null;
                }

                if (preg_match("#Depart:\s*(.*?)\s*\((\w{3})\)\s+\w+,\s*(\d+\s*\w{3})\s+(\d+:\d+)#", $body, $m)) {
                    $seg['DepName'] = $m[1];
                    $seg['DepCode'] = $m[2];
                    $seg['DepDate'] = strtotime($m[3] . ', ' . $m[4]);
                }

                if (preg_match("#Arrive:\s*(.*?)\s*\((\w{3})\)\s+\w+,\s*(\d+\s*\w{3})\s+(\d+:\d+)#", $body, $m)) {
                    $seg['ArrName'] = $m[1];
                    $seg['ArrCode'] = $m[2];
                    $seg['ArrDate'] = strtotime($m[3] . ', ' . $m[4]);
                }

                if (preg_match("#Stops:\s*([^;]+);#", $body, $m)) {
                    $seg['Stops'] = ($m[1] == 'non-stop') ? 0 : $m[1];
                }

                if (preg_match("#Miles:\s*(\d+)#", $body, $m)) {
                    $seg['TraveledMiles'] = $m[1];
                }

                if (preg_match("#Class:\s*([^\n]+)#", $body, $m)) {
                    $seg['Cabin'] = $m[1];
                }

                if (preg_match("#Status:\s*([^\n]+)#", $body, $m)) {
                    $seg['Status'] = $m[1];
                }

                if (preg_match("#Seats Requested:\s*([^\n]+)#", $body, $m)) {
                    $seg['Seats'] = trim($m[1]);
                }

                $it['TripSegments'][] = $seg;
                $all[] = $it;
            }
        }

        return ['Itineraries' => $all, 'Properties' => []];
    }

    public function ParseEmailGerman()
    {
        $props = [];
        $it = ['Kind' => 'T'];
        $flights = [];

        // Passenger
        $it['Passengers'] = implode(', ', $this->http->FindNodes("//*[contains(text(), 'Reisende(r)')]/ancestor::tr[1]/following-sibling::tr[contains(., 'Ticketdetails')]/td[1]"));

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Reisende(r)')]/ancestor::tr[1]/td[2]", null, true, "#\s+([\w\d]+)$#");

        $it['Currency'] = $this->http->FindSingleNode("//*[contains(text(), 'Taxes:')]/ancestor::tr[1]/td[2]");
        $it['Tax'] = $this->http->FindSingleNode("//*[contains(text(), 'Taxes:')]/ancestor::tr[1]/td[3]");
        $it['TotalCharge'] = $this->http->FindSingleNode("//*[contains(text(), 'Gesamt:')]/ancestor::tr[1]/td[3]");

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Reisende(r)')]/ancestor::tr[1]/following-sibling::tr[contains(., 'Leistungsträger')]/following-sibling::tr[position() mod 2 = 0]");

        for ($i = 0; $i < $nodes->length; $i++) {
            $item = $nodes->item($i);

            $flight = $this->http->FindSingleNode("td[2]", $item);
            $dep = $this->http->FindNodes("td[3]//span/text()", $item);

            $depName = $depCode = $arrName = $arrCode = "";

            if (isset($dep[0]) && preg_match("#^(.*?)\s+\((\w{3})\)$#", $dep[0], $m)) {
                $depName = $m[1];
                $depCode = $m[2];
            }

            if (isset($dep[1]) && preg_match("#^(.*?)\s+\((\w{3})\)$#", $dep[1], $m)) {
                $arrName = $m[1];
                $arrCode = $m[2];
            }

            $time = $this->http->FindNodes("td[4]//span/text()", $item);

            $flights[$flight] = [
                'depName' => $depName,
                'depCode' => $depCode,
                'arrName' => $arrName,
                'arrCode' => $arrCode,
                'depTime' => $time[0] ?? null,
                'arrTime' => $time[1] ?? null,
                'class'   => $this->http->FindSingleNode("td[5]", $item),
            ];
        }

        $it['TripSegments'] = [];

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Flug -')]/ancestor::table[position()=1 and not(contains(.,'Tarif:'))]");

        for ($i = 0; $i < $nodes->length; $i++) {
            $table = $nodes->item($i);

            $seg = [];
            $date = $this->de2en($this->http->FindSingleNode(".//tr[contains(., 'Flug - ')]", $table, true, "#^Flug\s+\-\s*(.*?)$#"));
            $flight = $this->http->FindSingleNode(".//tr[contains(., 'Flug - ')]/following-sibling::tr[1]", $table, true, "#^(\w+\d+)#");

            $seg['FlightNumber'] = $flight;

            if (!isset($flights[$flight])) {
                return false;
            }
            $seg['DepDate'] = strtotime($date . ', ' . $flights[$flight]['depTime']);
            $seg['DepName'] = $flights[$flight]['depName'];
            $seg['DepCode'] = $flights[$flight]['depCode'];

            $seg['ArrDate'] = strtotime($date . ', ' . $flights[$flight]['arrTime']);
            $seg['ArrName'] = $flights[$flight]['arrName'];
            $seg['ArrCode'] = $flights[$flight]['arrCode'];

            $seg['Cabin'] = $flights[$flight]['class'];
            $seg['AirlineName'] = $this->http->FindSingleNode(".//tr[2]/td[2]", $table);

            $seg['Duration'] = $this->http->FindSingleNode(".//tr[contains(., 'Dauer:')]/td[2]", $table);
            $seg['Seats'] = $this->http->FindSingleNode(".//tr[contains(., 'Sitzplatz:')]/td[2]", $table, true, "#^(\d+\w+)#");

            $it['TripSegments'][] = $seg;
        }

        return ['Itineraries' => [$it], 'Properties' => $props];
    }

    public function ParseEmailFrench()
    {
        $props = [];
        $it = ['Kind' => 'T'];
        $flights = [];

        // Passenger
        $it['Passengers'] = $this->http->FindSingleNode("//*[contains(text(), 'Voyageur')]/ancestor::td[1]/following-sibling::td[1]");
        $it['ReservationDate'] = strtotime($this->fr2en(preg_replace("#\.#", '', $this->http->FindSingleNode("//*[contains(text(), 'émission') and contains(text(), 'Date')]/ancestor::td[1]/following-sibling::td[1]"))));
        $year = date('Y', $it['ReservationDate']);

        $airlineName = $this->http->FindSingleNode("//*[contains(text(), 'Compagnie aérienne:')]/ancestor::td[1]/following-sibling::td[1]");

        $total = $this->http->FindSingleNode("//*[contains(text(), 'Total:')]/ancestor::td[1]/following-sibling::td[1]");

        if (preg_match("#(\w{3})\s*([\d.,]+)#", $total, $m)) {
            $it['TotalCharge'] = $m[2];
            $it['Currency'] = $m[1];
        }

        $it['BaseFare'] = $this->http->FindSingleNode("//*[contains(text(), 'Tarif:')]/ancestor::td[1]/following-sibling::td[1]", null, true, "#([\d.,]+)#");

        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(), 'Numéro de dossier:')]/ancestor::td[1]/following-sibling::td[1]");
        $it['TripSegments'] = [];

        $nodes = $this->http->XPath->query("//*[contains(text(), 'Heure')]/ancestor-or-self::tr[1]/following-sibling::tr[count(td)>5]");

        foreach ($nodes as $node) {
            if (!$this->http->FindSingleNode("td[1]", $node)) {
                continue;
            }

            $seg = [];

            $seg['FlightNumber'] = $this->http->FindSingleNode("td[1]", $node);
            $seg['BookingClass'] = $this->http->FindSingleNode("td[2]", $node);

            $seg['AirlineName'] = $airlineName;

            $seg['DepName'] = $this->http->FindSingleNode("td[5]", $node);
            $seg['ArrName'] = $this->http->FindSingleNode("following-sibling::tr[1]/td[5]", $node);

            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

            $seg['DepDate'] = strtotime($this->http->FindSingleNode("td[3]", $node) . $year . '. ' . $this->http->FindSingleNode("td[4]", $node));
            $seg['ArrDate'] = strtotime($this->http->FindSingleNode("following-sibling::tr[1]/td[3]", $node) . $year . '. ' . $this->http->FindSingleNode("following-sibling::tr[1]/td[4]", $node));

            $it['TripSegments'][] = $seg;
        }

        return ['Itineraries' => [$it], 'Properties' => $props];
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

                    if (($it['Kind'] == 'T' and !$airFound)) {
                        $it[$this->confs[$it['Kind']]] = substr($matches[2], strpos($matches[2], '-') + 1);
                        $it['ReservationDate'] = strtotime($headers['date']);
                        $its[] = $it;
                        $airFound = $it['Kind'] == 'T';

                        if ($airFound) {
                            $this->airIndex = count($its) - 1;
                        }
                    }
                    $i++;
                } else {
                    break;
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
                    if (preg_match('/^Depart:\s+(.*?)\((\w{3})\)\s+(.*)/is', $body[$i], $match)) {
                        $ts['DepName'] = $match[1];
                        $ts['DepCode'] = $match[2];

                        if (preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['DepDate'] = strtotime($matches[2] . ' ' . $matches[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        } elseif (preg_match('/(.*?\,){0,1}\s+(\w+)\s+(\d+)\s+(.*)/is', $match[3], $matches)) {
                            $ts['DepDate'] = strtotime($matches[3] . ' ' . $matches[2] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $matches[4]);
                        }
                    }
                    // Arrive: Brisbane(BNE) Wednesday, 7 Aug 8:30 AM
                    if (preg_match('/^Arrive:\s+(.*?)\((\w{3})\)\s+(.*)/is', $body[$i], $match)) {
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
                        $ts['TraveledMiles'] = $match[1];
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
                    if (preg_match('/Check\-in:(.*)/ims', $body[$i], $im)) {
                        if (preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $im[1], $m)) {
                            $ts['CheckInDate'] = strtotime($m[2] . ' ' . $m[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $m[4]);
                        } else {
                            if (preg_match("#\w+,\s*(\w{3}\s+\d+)\s+(\d+:\d+\s*\w{2})#is", $im[1], $m)) {
                                $ts['CheckInDate'] = strtotime($m[1] . ' ' . date("Y", strtotime($headers['date'])) . ', ' . $m[2]);
                            }
                        }
                    }
                    // Check-out: Thursday, 8 Aug 10:00 AM
                    if (preg_match('/Check\-out:(.*)/ims', $body[$i], $im)) {
                        if (preg_match('/(.*?\,){0,1}\s+(\d+)\s+(\w+)\s+(.*)/is', $im[1], $m)) {
                            $ts['CheckOutDate'] = strtotime($m[2] . ' ' . $m[3] . ' ' . date("Y", strtotime($headers['date'])) . ' ' . $m[4]);
                        } else {
                            if (preg_match("#\w+,\s*(\w{3}\s+\d+)\s+(\d+:\d+\s*\w{2})#is", $im[1], $m)) {
                                $ts['CheckOutDate'] = strtotime($m[1] . ' ' . date("Y", strtotime($headers['date'])) . ', ' . $m[2]);
                            }
                        }
                    }

                    if (preg_match('/^Hotel Confirmation #:\s*(.*?)$/ims', $body[$i], $m)) {
                        $ts['ConfirmationNumber'] = $m[1];

                        if (!$ts['ConfirmationNumber']) {
                            if (preg_match("#^Hotel Confirmation \#\d+\s+([\w\-]+)#ims", $this->http->Response['body'], $m)) {
                                $ts['ConfirmationNumber'] = $m[1];
                            }
                        }
                    }

                    if (preg_match('/^Phone:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Phone'] = $match[1];
                    }

                    if (preg_match('/^Fax:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Fax'] = $match[1];
                    }

                    if (preg_match("#^Average Rate:\s*(.*)#ms", $body[$i], $m)) {
                        $ts['Rate'] = trim($m[1]);
                    }

                    // *** Car Reservation ***
                    if (preg_match('/^Confirmation #:\s+(.*)/ims', $body[$i], $match)) {
                        $ts['Number'] = $match[1];
                    }

                    if (preg_match("#^Vendor:\s*(.*)#ms", $body[$i], $m)) {
                        $ts['RentalCompany'] = trim($m[1]);
                    }

                    if (preg_match("#Total Car Cost:\s*(.*)#ims", $body[$i], $m)) {
                        $ts['TotalCharge'] = preg_replace("#[^\d.]#", '', $m[1]);
                        $ts['Currency'] = preg_replace("#[\d.\s]#", '', $m[1]);
                    }

                    if (preg_match("#^Car size:\s*(.*)#ms", $body[$i], $m)) {
                        $ts['CarType'] = trim($m[1]);
                    }

                    if (preg_match("#^Pick-up:\s*(\w+,\s*\w{3}\s+\d+)\s+(\d+:\d+\s*\w{2})\s+(.*)#ims", $body[$i], $m)) {
                        $ts['PickupDatetime'] = strtotime($m[1] . ' ' . date("Y", strtotime($headers['date'])) . ', ' . $m[2]);
                        $ts['PickupLocation'] = $m[3];

                        for ($k = $i + 1; $k < $i + 3; $k++) {
                            if (preg_match("#^Address:\s*(.*)#", $body[$k], $m)) {
                                $ts['PickupLocation'] .= ", " . $m[1];
                            }

                            if (preg_match("#^Tel\.:\s*(.*)#", $body[$k], $m)) {
                                $ts['PickupPhone'] = $m[1];
                            }
                        }
                    }

                    if (preg_match("#^Drop-Off:\s*(\w+,\s*\w{3}\s+\d+)\s+(\d+:\d+\s*\w{2})\s+(.*)#ims", $body[$i], $m)) {
                        $ts['DropoffDatetime'] = strtotime($m[1] . ' ' . date("Y", strtotime($headers['date'])) . ', ' . $m[2]);
                        $ts['DropoffLocation'] = $m[3];

                        for ($k = $i + 1; $k < $i + 3; $k++) {
                            if (preg_match("#^Address:\s*(.*)#", $body[$k], $m)) {
                                $ts['DropoffLocation'] .= ", " . $m[1];
                            }

                            if (preg_match("#^Tel\.:\s*(.*)#", $body[$k], $m)) {
                                $ts['DropoffPhone'] = $m[1];
                            }
                        }
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
            }

            if (preg_match('/^Total Flight \(per person\)\s+([\d\.]*)\s+(.*)/is', $body[$i], $match)) {
                $its[$this->airIndex]['TotalCharge'] = $match[1];
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

        //$air['ReservationDate'] = strtotime($headers['date']);
        $airlineName = $this->http->FindSingleNode('//span[contains(text(), "ElectronicTicket Number:")]', null, true, '/This ticket information applies to the following trip\(s\):\s+(.*?)\s+Flight/ims');

        $total = $this->http->FindSingleNode('//span[contains(text(), "Total Invoice Amount:") or contains(text(), "Total Amount:")]', null, true, '/Total (?:Invoice )*Amount:\s+(.*)/ims');

        if (preg_match('/(\d+\.\d+)\s+(.*)/is', $total, $matches)) {
            $air['TotalCharge'] = $matches[1];
            $air['Currency'] = $matches[2];
        }

        $air['Passengers'] = $this->http->FindSingleNode('(//span[contains(text(), "Traveler")])[ancestor::td]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]');
        $air['BaseFare'] = $this->http->FindSingleNode('//span[contains(text(), "ElectronicTicket Number:")]', null, true, '/Ticket Amount:\s+(\d+.\d+)/ims');

        // Determining AirCodes
        $DepCodes = [];
        $ArrCodes = [];
        $bTypes = [];
        $codeLines = $this->http->XPath->query('//span[contains(text(), "Summary")]/ancestor::table[3]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[last()]/descendant::table[1]/descendant::tr[1]/following-sibling::tr');

        if (!$codeLines->length) {
            $codeLines = $this->http->XPath->query("//*[contains(text(), 'Flight/Vendor')]/ancestor::tr[1]/following-sibling::tr");
        }

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
        // $nodes = $this->http->XPath->query("//span[contains(normalize-space(), 'Add to Calendar') ]/ancestor::table[3]");
        $nodes = $this->http->XPath->query("//td[contains(@style, '#08064E')]/ancestor::table[3][descendant::table[1]/descendant::span[1][contains(text(), 'AIR') or contains(text(), 'HOTEL') or contains(text(), 'CAR')]]");

        foreach ($nodes as $node) {
            $ts = [];
            $title = $this->http->FindSingleNode("descendant::table[1]/descendant::span[1]", $node);
            preg_match('/^(.*?)\s/is', $title, $type);

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

                                    if ($hotel['Address'] == null) {
                                        $hotel['Address'] = $this->http->FindSingleNode('td[2]/descendant::a[1]', $subnodes->item($i - 1));
                                    }

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
                    //$subnodes = $this->http->XPath->query("tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table[1 or 2]/descendant::tr", $node);
                    $subnodes = $this->http->XPath->query(".//tr", $node);
                    $i = 0;

                    if ($subnodes->length) {
                        while ($i < $subnodes->length) {
                            $item = $subnodes->item($i);

                            $s = $item->nodeValue;

                            if (preg_match('#^(.*?)[^\w]+Flight[^\w]+([\d\w]+).*?([\w\d]*)[^\w]+Class#ims', $s, $m)) {
                                $ts['AirlineName'] = trim($m[1]);
                                $ts['FlightNumber'] = $m[2];
                                $ts['Cabin'] = $m[3];
                            }

                            switch ($this->http->FindSingleNode('.//td[1]/descendant::span', $item)) {
                                case "Depart:":
                                $ts['DepName'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));
                                $d = str_replace($this->wd, '', $subnodes->item($i)->nodeValue);
                                $ts['DepDate'] = strtotime($d);
                                $i++;

                                break;

                                case "Arrive:":
                                $ts['ArrName'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));
                                $d = str_replace($this->wd, '', $subnodes->item($i)->nodeValue);
                                $ts['ArrDate'] = strtotime($d);
                                $i++;

                                break;

                                case "Duration:":
                                $ts['Duration'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                break;

                                case "Operated By:":
                                $ts['AirlineName'] = preg_replace("#^/#", '', reset($this->http->FindNodes('td[2]//span[1]', $subnodes->item($i++))));

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
                                $ts['Seats'] = $this->http->FindSingleNode($td2, $subnodes->item($i++), true, '/([\d\w]+)/ims');

                                break;

                                case "Distance:":
                                $ts['TraveledMiles'] = str_replace(' miles', '', $this->http->FindSingleNode($td2, $subnodes->item($i++)));

                                break;

                                default:
                                $i++;
                            }
                        }
                        $air['TripSegments'][] = $ts;
                        $lastId = count($air['TripSegments']) - 1;
                        $air['TripSegments'][$lastId]['DepCode'] = $DepCodes[$lastId];
                        $air['TripSegments'][$lastId]['ArrCode'] = $ArrCodes[$lastId];
                        $air['TripSegments'][$lastId]['BookingClass'] = $bTypes[$lastId];
                    }

                    break;

                case 'CAR':
                    $car = ['Kind' => 'L'];
                    $subnodes = $this->http->XPath->query("tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::tr", $node);
                    $i = 0;

                    while ($i < $subnodes->length) {
                        if ($i == 0) {
                            $car['RentalCompany'] = $this->http->FindSingleNode('descendant::span[1]', $subnodes->item($i++));
                            $i++;
                        } else {
                            switch ($this->http->FindSingleNode('td[1]/descendant::span', $subnodes->item($i))) {
                                case 'Confirmation:':
                                    $car['Number'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Pick Up:':
                                    $pl = $this->http->FindSingleNode($td2, $subnodes->item($i));

                                    if (preg_match('/(.+?)\s+Tel:\s+(.+)/i', $pl, $m)) {
                                        $car['PickupLocation'] = $m[1];
                                        $car['PickupPhone'] = $m[2];
                                    } else {
                                            $car['PickupLocation'] = $pl;
                                        }
                                    $dt = $this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[2]', $subnodes->item($i++));
                                    $car['PickupDatetime'] = strtotime($this->subdays($dt));

                                    break;

                                case 'Drop Off:':
                                    $pl = $this->http->FindSingleNode($td2, $subnodes->item($i));

                                    if (preg_match('/(.+?)\s+Tel:\s+(.+)/i', $pl, $m)) {
                                        $car['DropoffLocation'] = $m[1];
                                        $car['DropoffPhone'] = $m[2];
                                    } else {
                                            $car['DropoffLocation'] = $pl;
                                        }
                                    $dt = $this->http->FindSingleNode('following-sibling::tr[1]/descendant::td[2]', $subnodes->item($i++));
                                    $car['DropoffDatetime'] = strtotime($this->subdays($dt));

                                    break;

                                case 'Type:':
                                    $car['CarType'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Status:':
                                    $car['Status'] = $this->http->FindSingleNode($td2, $subnodes->item($i++));

                                    break;

                                case 'Corp. Discount:':
                                    $car['Discounts'] = ['Corp. Discount' => $this->http->FindSingleNode($td2, $subnodes->item($i++))];

                                    break;

                                case 'Estimated Total:':
                                    $car['TotalCharge'] = $this->http->FindSingleNode($td2, $subnodes->item($i), true, '/([\d\.]+)/i');
                                    $car['Currency'] = $this->http->FindSingleNode($td2, $subnodes->item($i++), true, '/(.*?)\s+[\d\.]+/i');

                                    break;

                                default:
                                    $i++;
                            }
                        }
                    }

                    break;

                default:
                    DieTrace('NEW RESERVATION TYPE, PLEASE NOTICE THIS TRACE:' . $type[1]);
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

        if (isset($car)) {
            $its[] = $car;
        }

        return ['Itineraries' => $its, 'Properties' => $props];
    }

    /*
        function ParseEmailMMC($headers){
            // $this->http->LiveDebug();
            $its = array();
            $props = array();
            $list = array();

            $name = $this->http->FindSingleNode('//td[contains(text(), "Traveler")]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]');
            $listNodes = $this->http->XPath->query('//td[contains(text(), "Traveler")]/ancestor::table[1]/descendant::tr[1]/following-sibling::tr[last()]/descendant::table[1]/descendant::tr[1]/following-sibling::tr[position() > 1]');
            for ($i = 0; $i < $listNodes->length; $i++){
                $l = array();
                $l['From-To']       = $this->http->FindSingleNode('td[2]', $listNodes->item($i));

                $dir = explode('-', $l['From-To']);
                $l['From'] = isset($dir[0])?$dir[0]:TRIP_CODE_UNKNOWN;
                $l['To'] = isset($dir[1])?$dir[1]:TRIP_CODE_UNKNOWN;

                $l['Flight/Vendor'] = $this->http->FindSingleNode('td[3]', $listNodes->item($i));
                $l['Status']        = $this->http->FindSingleNode('td[4]', $listNodes->item($i));
                $l['Depart/Arrive'] = $this->http->FindSingleNode('td[5]', $listNodes->item($i));
                $l['Class/Type']    = $this->http->FindSingleNode('td[6]', $listNodes->item($i));
                $list[] = $l;
            }
            $nodes = $this->http->XPath->query('//table[2]/descendant::tr[1]/following-sibling::tr[position() > 2]/descendant::tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::tbody[1]/descendant::tbody[1]');
            $weirdNodes = $this->http->XPath->query('//table[2]/descendant::tr[1]/following-sibling::tr[position() > 2]/descendant::tbody[1]/descendant::tr[1]/following-sibling::tr[2]/descendant::tbody[1]/descendant::tbody[2]');
            $listCount = 0;
            $w = 0;
            for ($i = 0; $i < $nodes->length; $i++){
                // Case AirTrip
                if ('Depart:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))){
                    if (!isset($air))
                        $air = array('Kind' => 'T', 'Passengers' => $name, 'ReservationDate' => strtotime($headers['date']));
                    $ts = array();
                    $ts['FlightNumber'] = $list[$listCount]['Flight/Vendor'];
                    $air['Status'] = $list[$listCount]['Status'];
                    $ts['DepCode'] = $list[$listCount]['From'];#substr($list[$listCount]['From-To'], 0, strpos($list[$listCount]['From-To'], '-'));
                    $ts['ArrCode'] = $list[$listCount]['To'];#substr($list[$listCount]['From-To'], strpos($list[$listCount]['From-To'], '-') + 1);
                    if (preg_match('/(.*?)\s*\/\s*(.*)/ims', $list[$listCount]['Class/Type'], $match)){
                        $ts['Cabin'] = $match[1];
                        $ts['BookingClass'] = $match[2];
                    }
                    else
                        $ts['Cabin'] = $list[$listCount]['Class/Type'];
                    $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                    $weirdLength = 0;
                    if ($weirdNodes->length > 0){
                        $subWeirdNodes = $this->http->XPath->query('tr', $weirdNodes->item($w));
                        $weirdLength = $subWeirdNodes->length;
                        $w++;
                    }
                    $j = 0;
                    while ($j < $subNodes->length + $weirdLength)
                    {
                        if ($j < $subNodes->length)
                            $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                        else
                            $td1 = $this->http->FindSingleNode('td[1]', $subWeirdNodes->item($j - $subNodes->length));

                        if ($j < $subNodes->length)
                            $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                        else
                            $td2 = $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length));
                        #print $td1."\n";
                        if ($j == 0){
                            preg_match('/^(.*?)\s+Flight/ims', $td1, $matches);
                            $ts['AirlineName'] = $matches[1];
                        }
                        else
                            switch ($td1){
                                case 'Depart:':
                                    $ts['DepName'] = $td2;
                                    $j++;
                                    $ts['DepDate'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subNodes->item($j))));
                                    break;
                                case 'Arrive:':
                                    $ts['ArrName'] = $td2;
                                    $j++;
                                    if ($j < $subNodes->length)
                                        $ts['ArrDate'] = strtotime($this->http->FindSingleNode('td[2]', $subNodes->item($j)));
                                    else
                                        $ts['ArrDate'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length))));
                                    break;
                                case 'Duration:':
                                    if (preg_match("#^(.*?)\s*(Non\-stop)*$#", $td2, $m)){
                                        $ts['Duration'] = $m[1];
                                        $ts['Stops'] = isset($m[1])?0:null;
                                    }
                                    break;
                                case 'Status:':
                                    preg_match('/Locator:\s+(.*)/ims', $td2, $matches);
                                    if (!$matches) {
                                        return false;
                                    }
                                    $air['RecordLocator'] = $matches[1];
                                    break;
                                case 'Equipment:':
                                    $ts['Aircraft'] = $td2;
                                    break;
                                case 'Seat:':
                                    if (preg_match("#^(\d{2}\w)\s+(\(Non smoking\))*#", $td2, $m)){
                                        $ts['Seats'] = $m[1];
                                        $ts['Smoking'] = isset($m[2])?false:null;
                                    }
                                    break;
                                case 'Distance:':
                                    $ts['TraveledMiles'] = $td2;
                                    break;
                            }
                        $j++;
                    }
                    $air['TripSegments'][] = $ts;
                    $listCount++;
                }
                // Case Rental
                elseif ('Pick Up:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))){
                    $rental = array(
                        'Kind' => 'L',
                        'ReservationDate' => strtotime($headers['date']),
                        'RentalCompany' => $list[$listCount]['Flight/Vendor'],
                        'RenterName' => $name
                    );

                    $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                    $weirdLength = 0;
                    if ($weirdNodes->length > 0){
                        $subWeirdNodes = $this->http->XPath->query('tr', $weirdNodes->item($w));
                        $weirdLength = $subWeirdNodes->length;
                        $w++;
                    }
                    $j = 0;
                    while ($j < $subNodes->length + $weirdLength){
                        if ($j < $subNodes->length)
                            $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                        else
                            $td1 = $this->http->FindSingleNode('td[1]', $subWeirdNodes->item($j - $subNodes->length));
                        if ($j < $subNodes->length)
                            $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                        else
                            $td2 = $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length));
                        switch ($td1){
                            case 'Confirmation:':
                                $rental['Number'] = $td2;
                                break;
                            case 'Pick Up:':
                                preg_match('/(.*);\s+Tel:\s+(.*);\s+Fax:\s+(.*)/ims', $td2, $matches);
                                if (!$matches) {
                                    return false;
                                }
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
                                if ($j < $subNodes->length)
                                    $rental['DropoffDatetime'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subNodes->item($j))));
                                else
                                    $rental['DropoffDatetime'] = strtotime(str_replace($this->wd, '', $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length))));
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
                                $rental['Fees'] = array(0 => array('Name' => 'Extra Charges', 'Charge' => $td2));
                                break;
                        }
                        $j++;
                    }

                    $its[] = $rental;
                    $listCount++;
                }
                // Case Rental (2)
                elseif ('Pickup Location:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))){
                    $rental = array(
                        'Kind' => 'L',
                        'ReservationDate' => strtotime($headers['date']),
                        'RentalCompany' => $list[$listCount]['Flight/Vendor'],
                        'RenterName' => $name
                    );

                    $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                    $weirdLength = 0;
                    if ($weirdNodes->length > 0){
                        $subWeirdNodes = $this->http->XPath->query('tr', $weirdNodes->item($w));
                        $weirdLength = $subWeirdNodes->length;
                        #$w++;
                    }
                    $j = 0;
                    while ($j < $subNodes->length + $weirdLength)
                    {
                        if ($j < $subNodes->length)
                            $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                        else
                            $td1 = $this->http->FindSingleNode('td[1]', $subWeirdNodes->item($j - $subNodes->length));
                        if ($j < $subNodes->length)
                            $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                        else
                            $td2 = $this->http->FindSingleNode('td[2]', $subWeirdNodes->item($j - $subNodes->length));
                        #print $td1."!\n".$td2."!\n";

                        switch ($td1){
                            case 'Confirmation Number:':
                                $rental['Number'] = $td2;
                                break;
                            case 'Pickup Location:':
                                $rental['PickupLocation'] = $td2;
                                break;
                            case 'Pickup Date and Time:':
                                $rental['DropoffDatetime'] = $rental['PickupDatetime'] = strtotime($td2);
                                break;
                            case 'Dropoff Location:':
                                $rental['DropoffLocation'] = $td2;
                                break;
                            case 'Dropoff Date and Time:':
                                $rental['DropoffDatetime'] = strtotime($td2);
                                break;
                        }
                        $j++;
                    }

                    $its[] = $rental;
                    $listCount++;
                }
                // Case Hotel
                elseif ('Address:' == $this->http->FindSingleNode('tr[3]/td[1]', $nodes->item($i))){
                    $hotel = array(
                        'Kind' => 'R',
                        'ReservationDate' => strtotime($headers['date']),
                        'HotelName' => $list[$listCount]['Flight/Vendor'],
                        'GuestNames' => $name
                    );
                    $hf = array(
                        'Address:'              => 'Address',
                        'Tel:'                  => 'Phone',
                        'Fax:'                  => 'Fax',
                        'Confirmation:'         => 'ConfirmationNumber',
                        'Number of Persons:'    => 'Guests',
                        'Number of Rooms:'      => 'Rooms',
                        'Rate per night:'       => 'Rate',
                        'Remarks:'              => 'CancellationPolicy',
                        'Description:'          => 'RoomTypeDescription'
                    );
                    $subNodes = $this->http->XPath->query('tr', $nodes->item($i));
                    $j = 0;
                    while ($j < $subNodes->length){
                        $td1 = $this->http->FindSingleNode('td[1]', $subNodes->item($j));
                        $td2 = $this->http->FindSingleNode('td[2]', $subNodes->item($j));
                        if (array_key_exists($td1, $hf))
                            $hotel[$hf[$td1]] = $td2;
                        else
                            switch ($td1){
                                case 'Check In/Check Out:':
                                    preg_match('/(.*)\s+\-\s+(.*)/ims', $td2, $matches);
                                    $hotel['CheckInDate'] = strtotime(str_replace($this->wd, '', $matches[1]));
                                    $hotel['CheckOutDate'] = strtotime(str_replace($this->wd, '', $matches[2]));
                                    break;
                            }
                        $j++;
                    }
                    $its[] = $hotel;
                    $listCount++;
                }
            }

            if (isset($air))
                $its[] = $air;

            return array('Itineraries' => $its, 'Properties' => $props);
        }
    */
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

    /*
        private function ParseEmailET($parser){
            $its = array();
            $props = array();
            $trip = array("Kind" => "T");
            preg_match('/Record Locator:\s+([\w\d]+)/i', $parser->GetPlainBody(), $m);
            $trip['RecordLocator'] = $m[1];
            $trip['Passengers'] = $this->http->FindSingleNode("//td[contains(text(), 'Traveler')][1]/ancestor::tr[1]/following-sibling::tr[2]/descendant::td[1]");
            $trip['TotalCharge'] = str_replace(',', '', $this->http->FindSingleNode("//td[contains(text(), 'Total Amount')]", null, true, '/Total Amount:\s+([\d.,]+)/i'));
            $trip['Currency'] = $this->http->FindSingleNode("//td[contains(text(), 'Total Amount')]", null, true, '/Total Amount:\s+[\d.,]+\s+(.+)/i');
            $nodes = $this->http->XPath->query("//td/ancestor::table[3][descendant::table[1]/descendant::font[1][contains(text(), 'AIR') or contains(text(), 'HOTEL') or contains(text(), 'CAR')]]");
            for ($i = 0; $i < $nodes->length; $i++){
                $type = $this->http->FindSingleNode("descendant::font[1]", $nodes->item($i), true, '/(.*?)\s-/i');
                switch ($type){
                    case 'AIR':
                        $ts = array();
                        $subnodes = $this->http->XPath->query("descendant::tr[1]/following-sibling::tr[2]/descendant::table[1]/descendant::table/tbody/tr", $nodes->item($i));
                        for ($j = 0; $j < $subnodes->length; $j++){
                            if ($j == 0){
                                $ts['AirlineName'] = $this->http->FindSingleNode('td[1]', $subnodes->item($j), true, '/(.*)\s+Flight/i');
                                $ts['FlightNumber'] = $this->http->FindSingleNode('td[1]', $subnodes->item($j), true, '/Flight\s+([\w\d]*)/i');
                            }
                            else
                                switch ($this->http->FindSingleNode('td[1]', $subnodes->item($j))){
                                    case "Duration:":
                                        $ts['Duration'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case "Meal:":
                                        $ts['Meal'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case "Depart:":
                                        $ts['DepName'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        $ts['DepDate'] = strtotime($this->subdays($this->http->FindSingleNode('following-sibling::tr[1]/td[2]', $subnodes->item($j))));
                                        $x = $i + 2;
                                        $ts['DepCode'] = $this->http->FindSingleNode("//table[@id='travelSummaryTable']/descendant::tr[1]/following-sibling::tr[$x]/descendant::td[1]/following-sibling::td[1]", null, true, '/(.*)-/ims');
                                        break;
                                    case "Arrive:":
                                        $ts['ArrName'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        $ts['ArrDate'] = strtotime($this->subdays($this->http->FindSingleNode('following-sibling::tr[1]/td[2]', $subnodes->item($j))));
                                        $x = $i + 2;
                                        $ts['ArrCode'] = $this->http->FindSingleNode("//table[@id='travelSummaryTable']/descendant::tr[1]/following-sibling::tr[$x]/descendant::td[1]/following-sibling::td[1]", null, true, '/.*-(.*)/ims');
                                        break;
                                    case "Equipment:":
                                        $ts['Aircraft'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case "Seat:":
                                        $ts['Seats'] = str_replace(' Confirmed', '', $this->http->FindSingleNode('td[2]', $subnodes->item($j)));
                                        break;
                                    case "Distance:":
                                        $ts['TraveledMiles'] = str_replace(',' ,'', $this->http->FindSingleNode('td[2]', $subnodes->item($j), true, '/([\d.,]+)/i'));
                                        break;
                                    default:
                                }
                        }
                        $trip['TripSegments'][] = $ts;
                        break;
                    case 'HOTEL':
                        $hotel = array('Kind' => 'R');
                        $subnodes = $this->http->XPath->query("descendant::tr[1]/following-sibling::tr[2]/descendant::table[2]/tbody/tr", $nodes->item($i));
                        for ($j = 0; $j < $subnodes->length; $j++){
                            if ($j == 0)
                                $hotel['HotelName'] = $this->http->FindSingleNode('td[1]', $subnodes->item($j));
                            else
                                switch ($this->http->FindSingleNode('td[1]', $subnodes->item($j))){
                                    case 'Address:':
                                        $hotel['Address'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case 'Check In/Check Out:':
                                        $hotel['CheckInDate'] = strtotime($this->subdays($this->http->FindSingleNode('td[2]', $subnodes->item($j), true, '/(.*?)\s+-/i')));
                                        $hotel['CheckOutDate'] = strtotime($this->subdays($this->http->FindSingleNode('td[2]', $subnodes->item($j), true, '/.*?\s+-\s+(.*)/i')));
                                        break;
                                    case 'Status:':
                                        $hotel['Status'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case 'Rate per night:':
                                        $hotel['Rate'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case 'Confirmation:':
                                        $hotel['ConfirmationNumber'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        break;
                                    case 'Additional Information:':
                                        if ($hotel['Address'] == null){
                                            $hotel['Address'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                            if (preg_match('/PHONE\s*(\d*)/i', $hotel['Address'], $m)){
                                                $hotel['Phone'] = $m[1];
                                                $hotel['Address'] = $this->http->FindSingleNode('td[2]', $subnodes->item($j), true, '/(.*)PHONE/ims');
                                            }
                                        }
                                        break;
                                    case 'Remarks:':
                                        $r = $this->http->FindSingleNode('td[2]', $subnodes->item($j));
                                        if (preg_match('/cancel/i', $r))
                                            $hotel['CancellationPolicy'] = $r;
                                        break;
                                    default:
                                }
                        }
                        $its[] = $hotel;
                        break;
                    default:
                        DIE("NEW FORMAT '$type' HAS BEEN FOUND, PLEASE NOTICE THIS TRACE AND COMPLETE ParseEmailET() function!");
                }
            }
            $its[] = $trip;
            return array('Itineraries' => $its, 'Properties' => $props);
        }*/
    /*
        function ParseEmailHtml4($headers){
            $its = array();
            $props = array();
            $conf = $this->http->FindPreg('/Código de reservación:\s+(.*?)</ims');
            $this->http->Response['body'] = preg_replace('/(<.*?>)/ims', "\r\n", $this->http->Response['body']);
            $body = preg_split('/[\r\n]+/', $this->http->Response['body']);
            $body = array_map('CleanXMLValue', $body);
            foreach ($body as $k => $v)
                if (preg_match('/^\s*$/ims', $v))
                    unset ($body[$k]);
            $body = array_values($body);
            $body = array_filter($body, 'strlen');
            // var_dump($body);
            $i = 0;
            while ($body[$i]!='Itinerario')
                $i++;
            $trip = array('Kind' => 'T');
            $scan = true;

            while ($scan){
                $i++;
                $state = 'parse';
                if ($body[$i] == 'OBSERVACIONES DEL ORGANIZADOR:')
                    $state = 'stop';
                elseif (strpos($body[$i], 'Vuelos:') === 0)
                    $state = 'newAir';
                elseif (strpos($body[$i], 'Hotel y') === 0)
                    $state = 'newHotel';
                if ($state == 'parse'){
                    if (preg_match('/^Desde:\s+(.*)\((.*)\)/ims', $body[$i], $match)){
                        $it['DepName'] = $match[1];
                        $it['DepCode'] = $match[2];
                    }
                    elseif (preg_match('/^Hasta:\s+(.*)\((.*)\)/ims', $body[$i], $match)){
                        $it['ArrName'] = $match[1];
                        $it['ArrCode'] = $match[2];
                    }
                    elseif (preg_match('/^Confirmación de aerolínea:\s*(.*)/ims', $body[$i], $match))
                        $trip['RecordLocator'] = $match[1];
                    elseif (preg_match('/^Sale:\s*(\d+:\d+\s*\w+)/ims', $body[$i], $match))
                        $it['DepDate'] = $this->processDate($fy, $fm, $fd, $match[1], $headers['date']);
                    elseif (preg_match('/^Llega:\s*(\d+:\d+\s*\w+)/ims', $body[$i], $match)){
                        $it['ArrDate'] = $this->processDate($fy, $fm, $fd, $match[1], $headers['date']);
                        if ($it['ArrDate'] < $it['DepDate'])
                            $it['ArrDate'] += 24*60*60;
                    }
                    elseif (preg_match('/^Clase:\s*(.*)/ims', $body[$i], $match))
                        $it['Cabin'] = $match[1];
                    elseif (preg_match('/^Asiento:/ims', $body[$i], $match))
                        $it['Seats'] = $body[$i+1];
                    elseif (preg_match('/^Estado:\s*(.*)/ims', $body[$i], $match))
                        $it['Status'] = $match[1];
                    elseif (preg_match('/^Comida:\s*(.*)/ims', $body[$i], $match))
                        $it['Meal'] = $match[1];
                    elseif (preg_match('/^Se permite fumar:\s*(.*)/ims', $body[$i], $match))
                        $it['Smoking'] = (strtolower($match[1]) <> 'no');
                    elseif (preg_match('/^Aeronave:\s*(.*)/ims', $body[$i], $match))
                        $it['Aircraft'] = $match[1];
                    elseif (preg_match('/^Millaje:\s*(.*)/ims', $body[$i], $match))
                        $it['TraveledMiles'] = $match[1];
                    elseif (preg_match('/^Duración:\s*(.*)/ims', $body[$i], $match))
                        $it['Duration'] = $match[1];
                    // Hotel
                    elseif (preg_match('/^Dirección:\s*(.*)/ims', $body[$i], $match))
                        $it['Address'] = $match[1].' '.$body[$i+2];
                    elseif (preg_match('/^Teléfono:\s*(.*)/ims', $body[$i], $match))
                        $it['Phone'] = $match[1];
                    elseif (preg_match('/^FAX:\s*(.*)/ims', $body[$i], $match))
                        $it['Fax'] = $match[1];
                    elseif (preg_match('/^Habitación\(es\):\s*(.*)/ims', $body[$i], $match))
                        $it['Rooms'] = $match[1];
                    elseif (preg_match('/^Tarifa:\s*(.*)/ims', $body[$i], $match))
                        $it['Rate'] = $match[1];
                    elseif (preg_match('/^Confirmación:\s*(.*)/ims', $body[$i], $match))
                        $it['ConfirmationNumber'] = $match[1];
                    elseif (preg_match('/^Cancelación:\s*(.*)/ims', $body[$i], $match))
                        $it['CancellationPolicy'] = $match[1];
                    elseif (preg_match('/^Detalles de las habitaciones:\s*(.*)/ims', $body[$i], $match)){
                        $it['RoomTypeDescription'] = $match[1];
                        if (preg_match('/(\d+\.\d+)\s+(\w+)\s+APPROX\. TTL PRICE/ims', $body[$i], $match)){
                            $it['Currency'] = $match[2];
                            $it['Total'] = $match[1];
                        }
                        if (preg_match('/(\d+\.\d+) TTL TAX/ims', $body[$i], $match)){
                            $it['Taxes'] = $match[1];
                        }
                    }
                }
                else{
                    if (isset($it)){
                        if ($it['Kind'] == 'T'){
                            if (isset($it['Status'])){
                                $trip['Status'] = $it['Status'];
                                unset($it['Status']);
                            }
                            unset($it['Kind']);
                            $trip['TripSegments'][] = $it;
                            if ($state != 'newAir'){
                                $its[] = $trip;
                                $trip = array('Kind' => 'T');
                            }
                        }
                        else
                        $its[] = $it;
                    }
                    $it = array();
                    $fy = date("Y", strtotime($headers['date']));
                    # $d = str_replace(
                    #    array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic', ', ', ' ','lun', 'mar', 'mié', 'mie', 'jue', 'vie', 'sáb', 'sab', 'dom'),
                    #    array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December', '', '-', ''),
                    #    $body[$i-1]);
                    #if (strtotime($d.'-'.$oyear) < time())
                    #    $oyear++;
                    #$d = $d.'-'.$oyear;
                    #$it['Debug'] = strtotime($d);
                    if ($state != 'stop'){
                        preg_match('/^.*?,\s+(.*?)\s+(\d+)( - .*?,\s+(.*?)\s+(\d+))*$/ims', $body[$i-1], $match);
                        $spm = array('ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic');
                        $fm = array_search($match[1], $spm)+1;
                        $fd = $match[2];
                        if (isset($match[3])){
                            $tm = array_search($match[4], $spm)+1;
                            $td = $match[5];
                        }
                        if ($state == 'newHotel'){
                            $it['Kind'] = 'R';
                            if (preg_match('/Hotel.*?:\s+(.*)/ims', $body[$i], $match))
                                $it['HotelName'] = $match[1];
                            $it['CheckInDate'] = $this->processDate($fy, $fm, $fd, '00:00:00', $headers['date']);
                            $it['CheckOutDate'] = $this->processDate($fy, $tm, $td, '00:00:00', $headers['date']);
                        }
                        if ($state == 'newAir'){
                            $it['Kind'] = 'T';
                            if (preg_match('/Vuelos:\s+(.*),\s*(\w{2}\s*\d+)/ims', $body[$i], $match)){
                                $it['AirlineName'] = $match[1];
                                $it['FlightNumber'] = $match[2];
                            }
                        }
                    }
                }
                            $scan = ($state != 'stop');
            }
            return array('Itineraries' => $its, 'Properties' => $props);
        }
        */
    public function de2en($date)
    {
        $reMonth = [
            'jan' => 'jan',
            'feb' => 'feb',
            'mär' => 'mar',
            'mar' => 'mar',
            'apr' => 'apr',
            'mai' => 'may',
            'jun' => 'jun',
            'jul' => 'jul',
            'aug' => 'aug',
            'sep' => 'sep',
            'okt' => 'oct',
            'nov' => 'nov',
            'dez' => 'dec',
        ];

        if (preg_match("#(\d+)\s+(\w{3})\w+\s+(\d{4})#", $date, $m)) {
            return $m[1] . ' ' . $reMonth[strtolower($m[2])] . ' ' . $m[3];
        }

        return null;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        //  forwarded message

        if (preg_match("#From:\s*BCD Travel#", $parser->getPlainBody())) {
            return true;
        }

        if (preg_match("#De\s*:[\s\"]*BCD TRAVEL\s*\(MX\)#", $parser->getPlainBody())) {
            return true;
        }

        $body = $parser->getHtmlBody();

        return stripos($body, '@bcdtravel') !== false;

        return stripos($body, 'BCD Travel') !== false || stripos($body, '@bcdtravel') !== false;
        //return preg_match("#From:\s*(?:<[^>]+>\s*)*(?:BCD Travel|BCD TRAVEL \(MX\)|[^\@]+@bcdtravel.\w+)|BCD Travel acts only as an agent for the airlines#", $body);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($headers["from"]) && preg_match("/sap@bcdtravel.com.au/i", $headers['from']))
            || (isset($headers['subject']) && preg_match("/BCD TRAVEL \(MX\)/i", $headers['subject']));
    }

    public function detectEmailFromProvider($from)
    {
        return false;
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#[^\w\d\t\r\n :;,./\(\)\[\]\{\}\-\\\$+=_<>&\#%^&!]#", ' ', $html);

        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);
        $html = preg_replace("# +#ums", " ", $html);

        return $html;
    }

    public function extractPDF($parser, $wildcard = null)
    {
        $pdfs = $parser->searchAttachmentByName($wildcard ? $wildcard : '.*pdf');
        $pdf = "";

        foreach ($pdfs as $pdfo) {
            if (($html = \PDF::convertToHtml($parser->getAttachmentBody($pdfo), \PDF::MODE_SIMPLE)) !== null) {
                $pdf .= $html;
            }
        }

        return $pdf;
    }

    public function xAttachments($parser)
    {
        $all = [];

        for ($i = 0; $i < $parser->countAttachments(); $i++) {
            $a = $parser->getAttachment($i);
            $body = $parser->getAttachmentBody($i);

            $type = $a['headers']['content-disposition'];

            if (preg_match("#\.pdf$#i", $type)) {
                $all[] = \PDF::convertToHtml($body, \PDF::MODE_SIMPLE);

                continue;
            }

            if (preg_match("#\.xml$#i", $type)) {
                $xml = simplexml_load_string($body);
                $all[] = json_encode($xml);

                continue;
            }

            $all[] = $body;
        }

        $this->http->SetBody(implode("\n<!--delimeter begin-->\n<BR>\n<BR><!--delimeter end-->\n", $all));
    }

    public function fr2en($date)
    {
        $arr = ['jan.*'=>'jan', 'f.v.*'=>'feb', 'mar.*'=>'mar', 'avr.*'=>'apr', 'mai.*'=>'may', 'juin'=>'jun', 'juil'=>'jul', 'a..t*'=>'aug', 'sep.*'=>'sep', 'oct.*'=>'oct', 'nov.*'=>'nov', 'd.c.*'=>'dec'];
        $date = preg_replace_callback("#\s+(\w{3})\w*\s+#u", function ($m) use (&$arr) {
            foreach ($arr as $name => $en) {
                if (preg_match("#$name#i", $m[1])) {
                    return " $en ";
                }
            }

            return $m[0];
        }, $date);

        return $date;
    }

    public static function getEmailLanguages()
    {
        return ["en", "es", "de", "fr"];
    }

    public static function getEmailTypesCount()
    {
        return 7;
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    private function subdays($s)
    {
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', ','];

        return str_replace($days, '', $s);
    }

    private function pushSegment($ts, &$its)
    {
        if ($ts['type'] == 'HOTEL') {
            unset($ts['type']);

            $ts['Kind'] = 'R';
            $ts['GuestNames'] = preg_match("#\nName:\s*([^\n]+)#", $this->http->Response['body'], $m) ? $m[1] : null;

            $its[] = $ts;

            return;
        }

        if ($ts['type'] == 'CAR') {
            unset($ts['type']);

            if (isset($ts['Address'])) {
                unset($ts['Address']);
            }

            $ts['Kind'] = 'L';
            $ts['RenterName'] = preg_match("#\nName:\s*([^\n]+)#", $this->http->Response['body'], $m) ? $m[1] : null;

            $its[] = $ts;

            return;
        }

        if ($ts['type'] == 'AIR') {
            unset($ts['type']);

            if (isset($ts['Status'])) {
                $its[$this->airIndex]['Status'] = $ts['Status'];
                unset($ts['Status']);
            }
            $its[$this->airIndex]['TripSegments'][] = $ts;
        }
    }
}
