<?php

namespace AwardWallet\Engine\amadeus\Email;

class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-10.eml, amadeus/it-13.eml, amadeus/it-14.eml, amadeus/it-15.eml, amadeus/it-2274006.eml, amadeus/it-7498217.eml";

    public $processors = [];

    public $reText = "#Cc:\s+onlinebooking@amadeus.com|@amadeus.com|emailserver2@pop3\.amadeus\.net|From:\s*no-reply@amadeus\.com|From:\s+webmaster@amadeus\.net|Pending approval trip plan for|ISSUING AIRLINE[^:]*:[^\w]*(SOUTH\s+AFRICAN\s+AIRWAYS|EGYPTAIR|QATAR AIRWAYS|THAI AIRWAYS INTERNATIONAL)|SERVICE\s+DATE\s+FROM\s+TO\s+DEPART\s+ARRIVE|From:\s*Thai Intl Hong Kong|Please do not respond fnd@amadeus|FLYSAA SOUTH AFRICA|TAP INTERNET SALES#i";

    public $reHtml = "#THANK YOU FOR CHOOSING ICELANDAIR#";

    public $xInstance = null;
    public $lastRe = null;

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "amadeus/it-18.eml"
            "#Thank\s+you\s+for\s+choosing\s+KLM#" => function (&$it) {
                $this->xBase($this->http); // helper
                $it['Kind'] = "T";

                $it['RecordLocator'] = $this->http->FindSingleNode("//span[contains(text(), 'Booking code')]", null, true, "#Booking code:\s+([^\s]+)#");
                $it['Passengers'] = implode(', ', $this->http->FindNodes("//*[contains(text(), 'Passenger Information')]/ancestor::tr[1]/following-sibling::tr[1]/following-sibling::tr/td[1]"));

                $it['TotalCharge'] = $this->mkCost($this->http->FindSingleNode("//*[contains(text(), 'Total amount:')]/following-sibling::td[1]"));
                $it['BaseFare'] = $this->mkCost($this->http->FindSingleNode("//*[contains(text(), 'Fare amount:')]/following-sibling::td[1]"));
                $it['Currency'] = $this->mkClear("#[^A-Z]#", $this->http->FindSingleNode("//*[contains(text(), 'Fare amount:')]/following-sibling::td[1]"));

                $it['TripSegments'] = [];

                $nodes = $this->http->XPath->query("//*[contains(text(), 'Itinerary Information')]/ancestor::tr[1]/following-sibling::tr");

                for ($i = 1; $i < $nodes->length; $i += 2) {
                    $tr = $nodes->item($i);
                    $tr2 = $nodes->item($i + 1);
                    $seg = [];

                    if (preg_match("#^(\w+)\s+(\d+)$#", $this->http->FindSingleNode("td[2]", $tr), $m)) {
                        $seg['FlightNumber'] = $m[2];
                        $seg['AirlineName'] = $m[1];
                    }

                    $seg['DepDate'] = strtotime($this->http->FindSingleNode("td[1]", $tr) . ', ' . $this->http->FindSingleNode("td[1]", $tr2), $this->date);
                    $seg['DepName'] = $this->http->FindSingleNode("td[3]", $tr);
                    $seg['DepCode'] = $this->http->FindSingleNode("td[3]", $tr2);

                    $seg['ArrDate'] = strtotime($this->http->FindSingleNode("td[5]", $tr) . ', ' . $this->http->FindSingleNode("td[5]", $tr2), $this->date);
                    $seg['ArrName'] = $this->http->FindSingleNode("td[4]", $tr);
                    $seg['ArrCode'] = $this->http->FindSingleNode("td[4]", $tr2);

                    $seg['Cabin'] = $this->http->FindSingleNode("td[6]", $tr);

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsed file "amadeus/it-16.eml"
            "#>Trip Plan<#" => function (&$it) {
                $this->xBase($this->http); // helper
                $all = [];

                $nodes = $this->http->XPath->query("//*[text() = 'Flights' or text()='Cars' or text() = 'Hotels']/ancestor::tr[1]");

                $number = $this->http->FindPreg("#Reservation number\s*:\s*<[^>]+>(.*?)</[^>]+>#");
                $name = $this->http->FindPreg("#Trip Plan for\s*:\s*<[^>]+>(.*?)</[^>]+>#");

                for ($i = 0; $i < $nodes->length; $i++) {
                    $tr = $nodes->item($i);

                    $type = $this->http->FindSingleNode("td[1]", $tr);
                    $it = [];

                    if ($type === 'Flights') {
                        $it['Kind'] = 'T';
                        $it['RecordLocator'] = $number;
                        $it['Passengers'] = $name;
                        $it['TripSegments'] = [];

                        $it['TotalCharge'] = $this->mkCost($this->http->FindSingleNode("td[2]", $tr));
                        $it['Currency'] = $this->mkCurrency($this->http->FindSingleNode("td[2]", $tr));

                        $tr = $this->http->XPath->query("ancestor::tr[1]", $tr);

                        if (!$tr->length) {
                            break;
                        }
                        $tr = $tr->item(0);

                        while (1) {
                            $tr = $this->http->XPath->query("following-sibling::tr[position()=1]", $tr); // segment

                            if (!$tr->length) {
                                break;
                            }
                            $tr = $tr->item(0);

                            if ($this->http->FindSingleNode(".//*[contains(text(), 'Additional')]", $tr)) {
                                continue;
                            }

                            $seg = [];

                            if ($this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table//*[contains(text(), 'policy justification')]", $tr)->length) {
                                $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[2]/td[2]/table", $tr)->item(0);
                            } else {
                                $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table", $tr)->item(0);
                            }

                            // check for other types begin (again)
                            if ($this->http->FindNodes(".//*[text() = 'Flights' or text()='Cars' or text() = 'Hotels' or text() = 'More Services']", $table)) {
                                break;
                            }

                            if (preg_match("#^(.*?)\s+(\w+\d+)#", $this->http->FindSingleNode("tbody/tr[1]/td[1]", $table), $m)) {
                                $seg['FlightNumber'] = $m[2];
                                $seg['AirlineName'] = $m[1];
                            }

                            $info = $this->http->FindNodes(".//*[contains(text(), 'Segment:')]/text()", $tr);

                            foreach ($info as $line) {
                                if (preg_match("#Aircraft:\s*(.*?)$#", $line, $m)) {
                                    $seg['Aircraft'] = $m[1];
                                }

                                if (preg_match("#Cabin:\s*(.*?)$#", $line, $m)) {
                                    $seg['Cabin'] = $m[1];
                                }

                                if (preg_match("#Number of stop\(s\):\s*(.*?)$#", $line, $m)) {
                                    $seg['Stops'] = $m[1] === 'Non-stop' ? 0 : $m[1];
                                }
                            }

                            $plan = $this->http->FindSingleNode("tbody/tr[2]/td[1]", $table);

                            if (!$plan) {
                                continue;
                            }

                            [$dep, $arr] = explode(">", $plan);

                            if (preg_match("#^(.*?)\s+\((\w{3})\)\s*(.*?)\s+(\d{2}:\d{2})\s+(.*?)$#", $dep, $m)) {
                                $seg['DepName'] = trim($m[1]);
                                $seg['DepCode'] = $m[2];
                                $seg['DepDate'] = strtotime($m[5] . ', ' . $m[4], $this->date);
                            }

                            if (preg_match("#^(.*?)\s+\((\w{3})\)\s*(.*?)\s+(\d{2}:\d{2})\s+(.*?)$#", $arr, $m)) {
                                $seg['ArrName'] = trim($m[1]);
                                $seg['ArrCode'] = $m[2];
                                $seg['ArrDate'] = strtotime($m[5] . ', ' . $m[4], $this->date);
                            }

                            $it['TripSegments'][] = $seg;
                        }
                    }

                    if ($type === 'Cars') {
                        $it['Kind'] = 'L';

                        $tr = $this->http->XPath->query("ancestor::tr[1]/following-sibling::tr[1]", $tr); // segment

                        if (!$tr->length) {
                            break;
                        }
                        $tr = $tr->item(0);

                        $it['RenterName'] = $name;
                        $it['TotalCharge'] = $this->mkCost($this->http->FindSingleNode(".//*[contains(text(), 'Estimated price for car rental')]", $tr, true, "#:\s*(.*)#"));
                        $it['Currency'] = $this->http->FindSingleNode(".//*[contains(text(), 'Estimated price for car rental')]", $tr, true, "#:\s*[\d.]+\s*(.*)#");

                        if ($this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table//*[contains(text(), 'policy justification')]", $tr)->length) {
                            $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[2]/td[2]/table", $tr)->item(0);
                        } else {
                            $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table", $tr)->item(0);
                        }

                        // check for other types begin (again)
                        if ($this->http->FindNodes(".//*[text() = 'Flights' or text()='Cars' or text() = 'Hotels' or text() = 'More Services']", $table)) {
                            break;
                        }

                        $it['RentalCompany'] = next($this->http->FindNodes("tbody/tr[1]/td[1]//table[1]//td[1]", $table));

                        $info = $this->http->FindNodes(".//*[contains(., 'Confirmation code:')]/text()", $tr);

                        foreach ($info as $line) {
                            if (preg_match("#Vehicle type:\s*(.*?)$#", $line, $m)) {
                                $it['CarType'] = $m[1];
                            }

                            if (preg_match("#Confirmation code:\s*(.*?)$#", $line, $m)) {
                                $it['Number'] = $m[1];
                            }
                        }

                        $plan = $this->http->FindSingleNode("tbody/tr[2]/td[1]", $table);

                        if (!$plan) {
                            continue;
                        }

                        [$pickup, $dropoff] = explode(">", $plan);

                        if (preg_match("#^(.*?)(\s+Phone:\s+(.*?))*(\s+Opening Hours:\s+(.*?))*(\d{2}:\d{2})\s+\w+,\s*(\w+\s+\d+,\s*\d{4})#", $pickup, $m)) {
                            $it['PickupLocation'] = trim($m[1]);
                            $it['PickupPhone'] = trim($m[3]);
                            $it['PickupHours'] = trim($m[5]);
                            $it['PickupDatetime'] = strtotime(trim($m[7]) . ', ' . trim($m[6]), $this->date);
                        }

                        if (preg_match("#^(.*?)(\s+Phone:\s+(.*?))*(\s+Opening Hours:\s+(.*?))*(\d{2}:\d{2})\s+\w+,\s*(\w+\s+\d+,\s*\d{4})#", $dropoff, $m)) {
                            $it['DropoffLocation'] = trim($m[1]);
                            $it['DropoffPhone'] = trim($m[3]);
                            $it['DropoffHours'] = trim($m[5]);
                            $it['DropoffDatetime'] = strtotime(trim($m[7]) . ', ' . trim($m[6]), $this->date);
                        }
                    }

                    if ($type === 'Hotels') {
                        $it['Kind'] = 'R';

                        $tr = $this->http->XPath->query("ancestor::tr[1]/following-sibling::tr[1]", $tr); // segment

                        if (!$tr->length) {
                            break;
                        }
                        $tr = $tr->item(0);

                        if ($this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table//*[contains(text(), 'policy justification')]", $tr)->length) {
                            $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[2]/td[2]/table", $tr)->item(0);
                        } else {
                            $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table", $tr)->item(0);
                        }

                        // check for other types begin (again)
                        if ($this->http->FindNodes(".//*[text() = 'Flights' or text()='Cars' or text() = 'Hotels' or text() = 'More Services']", $table)) {
                            break;
                        }

                        $it['GuestNames'] = $name;

                        $it['HotelName'] = next($this->http->FindNodes("tbody/tr[1]//table//td[1]", $table));
                        $it['Address'] = trim(implode(', ', $this->http->FindNodes("tbody/tr[2]//text()", $table)), ', ');

                        $plan = $this->http->FindSingleNode("tbody/tr[3]", $table);

                        if (!$plan) {
                            continue;
                        }

                        [$in, $out] = explode(">", $plan);

                        $it['CheckInDate'] = preg_match("#Check\-in\s+(.*)#", $in, $m) ? strtotime($m[1]) : null;
                        $it['CheckOutDate'] = preg_match("#Check\-out\s+(.*)#", $out, $m) ? strtotime($m[1]) : null;

                        $info = $this->http->FindNodes(".//*[contains(., 'Confirmation code:')]/text()", $tr);

                        foreach ($info as $line) {
                            if (preg_match("#Cancellation policy:\s*(.*?)$#", $line, $m)) {
                                $it['CancellationPolicy'] = $m[1];
                            }

                            if (preg_match("#Confirmation code:\s*(.*?)$#", $line, $m)) {
                                $it['ConfirmationNumber'] = $m[1];
                            }
                        }

                        $it['Total'] = $this->mkCost($this->http->FindSingleNode(".//*[contains(text(), 'additional taxes and charges may apply')]/ancestor::td[1]", $tr));
                        $it['Currency'] = $this->http->FindSingleNode(".//*[contains(text(), 'additional taxes and charges may apply')]/ancestor::td[1]", $tr, true, "#\d+\s+(\w+)#");

                        $table = $this->http->XPath->query(".//img[not(@height)]/ancestor::tr[1]/following-sibling::tr[1]/td[2]/table", $tr)->item(0);
                    }

                    $all[] = $it;
                }

                $it = $all;
            },

            // Parsed file "amadeus/it-10.eml"
            "#Pending approval trip plan for|Trip has been booked in Community#" => function (&$it) {
                $all = [];

                $this->xBase($this->http); // helper
                $body = $this->http->Response['body']; // full html

                $fullText = $text = $this->mkText($body);

                $totalPNR = $this->re("#\n\s*Reservation\s+[Nn]umber\s*:\s*([-A-Z\d]{5,})#", $fullText);

                // prepare record locators
                $PNRs = [];

                if ($this->re("#\nFlight\s+[Rr]eservation\s+[Nn]umber\(s\)\s+((([^\n:]+\s*:\s*[-A-z\d]{4,10})\s+)+)#ms", $text)) {
                    $locatorsRows = explode("\n", $this->re(1));

                    foreach ($locatorsRows as $row) {
                        if (preg_match('/^(?<airline>[^:]+):(?<recordlocator>[^:]+)$/m', $row, $matches)) {
                            $PNRs[trim($matches['airline'])] = trim($matches['recordlocator']);
                        }
                    }
                }

                if (preg_match_all('/Vendor\s*:\s*(?<airline>[^:]+?)Reservation\s+[Nn]umber\s*:\s*(?<recordlocator>[-A-Z\d]{5,})$/m', $text, $locatorMatches, PREG_SET_ORDER)) {
                    foreach ($locatorMatches as $matches) {
                        $PNRs[trim($matches['airline'])] = $matches['recordlocator'];
                    }
                }

                // prepare passengers
                $names = $this->re("#Trip Plan for\s*:\s*([^\n]+)#", $text);

                $reservations = preg_split('/(FLIGHT|HOTEL|CAR)\s+RESERVATION\s+/', $text, null, PREG_SPLIT_DELIM_CAPTURE);
                array_shift($reservations);

                for ($i = 0; $i < count($reservations); $i += 2) {
                    $type = $reservations[$i];
                    $text = $reservations[$i + 1];
                    $it = [];

                    if ($type === 'FLIGHT') {
                        $segments = preg_split('/^[>\s]*Flight\s*:/m', $text, null, PREG_SPLIT_DELIM_CAPTURE);
                        array_shift($segments);

                        foreach ($segments as $segment) {
                            $itFlight = [];
                            $itFlight['Kind'] = 'T';
                            $itFlight['Passengers'] = (array) $names;

                            $seg = [];

                            $airlineNameFull = trim($this->re('/([^\n]*?)([A-Z\d]{2})(\d+)\n/', $segment));
                            $seg['AirlineName'] = $this->re(2);
                            $seg['FlightNumber'] = $this->re(3);
                            $itFlight['RecordLocator'] = $PNRs[$airlineNameFull] ?? $totalPNR;

                            $seg['Aircraft'] = $this->re("#\nAircraft\s*:\s*([^\n]+)#", $segment);

                            $seg['DepName'] = $this->re("#\nFrom\s*:\s*(.*?)\s+\((\w{3})\)#", $segment);
                            $seg['DepCode'] = $this->re(2);

                            if (preg_match('/^[>\s]*From\s*:[^:]+(?:Terminal|TERMINAL|terminal)[:\s]+([\w\s]+?)$/m', $segment, $matches)) {
                                $seg['DepartureTerminal'] = $matches[1];
                            }
                            $seg['DepDate'] = strtotime($this->re("#\nDeparting\s*:\s*([^\n]+)#", $segment), $this->date);

                            $seg['ArrName'] = $this->re("#\nTo\s*:\s*(.*?)\s+\((\w{3})\)#", $segment);
                            $seg['ArrCode'] = $this->re(2);

                            if (preg_match('/^[>\s]*To\s*:[^:]+(?:Terminal|TERMINAL|terminal)[:\s]+([\w\s]+?)$/m', $segment, $matches)) {
                                $seg['ArrivalTerminal'] = $matches[1];
                            }
                            $seg['ArrDate'] = strtotime($this->re("#\nArriving\s*:\s*([^\n]+)#", $segment), $this->date);

                            if (preg_match('/Non[-\s]*stop/i', $segment, $matches)) {
                                $seg['Stops'] = 0;
                            } elseif ($this->re("#\nNumber of stop\(s\)\s*:\s*(\d+)#", $segment)) {
                                $seg['Stops'] = $this->re(1);
                            }

                            if ($this->re("#\nSeat\(s\)\s*:\s*(\d{1,2}[A-Z])#", $segment)) {
                                $seg['Seats'] = [$this->re(1)];
                            }

                            $seg['Cabin'] = $this->re("#\nCabin\s*:\s*([^\n]+)#", $segment);

                            $itFlight['TripSegments'][] = $seg;

                            if (($key = $this->recordLocatorInArray($itFlight['RecordLocator'], $all)) !== false) {
                                $all[$key]['Passengers'] = array_merge($all[$key]['Passengers'], $itFlight['Passengers']);
                                $all[$key]['Passengers'] = array_unique($all[$key]['Passengers']);
                                $all[$key]['TripSegments'] = array_merge($all[$key]['TripSegments'], $itFlight['TripSegments']);
                            } else {
                                $all[] = $itFlight;
                            }
                        }
                    }

                    if ($type === 'HOTEL') {
                        $it['Kind'] = 'R';

                        $it['Total'] = $this->re('/(?:Total price for hotel stay|Estimated price for hotel stay)\s*:\s*([.\d\n]+)\s+([^\d)(\]\[]+)/', $text);
                        $it['Currency'] = $this->re(2);

                        if ($this->re("#\nReservation [Ss]pans\s*:\s*([^\n]+)\s+to\s+([^\n]+)#", $text)) {
                            $it['CheckInDate'] = strtotime($this->re(1), $this->date);
                            $it['CheckOutDate'] = strtotime($this->re(2), $this->date);
                        }

                        $it['RoomType'] = $this->re("#\nRoom Type\s*:\s*([^\n]+)#", $text);
                        $it['RoomTypeDescription'] = $this->re("#\nTraveler requirement\s*:\s*([^\n]+)#", $text);

                        $it['2ChainName'] = $this->re("#\nHotel [Cc]hain\s*:\s*([^\n]+)#", $text);
                        $it['HotelName'] = $this->re("#\nHotel [Nn]ame\s*:\s*([^\n]+)#", $text);

                        $it['Address'] = $this->re("#\nHotel Address\s*:\s*([^\n]+)#", $text);

                        if ($this->re("#\nZip/Postal Code\s*:\s*([^\n]+)#", $text)) {
                            $it['Address'] .= ', ' . $this->re(1);
                        }

                        if ($this->re("#\nCity\s*:\s*([^\n]+)#", $text)) {
                            $it['Address'] .= ', ' . $this->re(1);
                        }

                        if ($this->re("#\nCountry\s*:\s*([^\n]+)#", $text)) {
                            $it['Address'] .= ', ' . $this->re(1);
                        }

                        $it['CancellationPolicy'] = $this->re("#\nCancellation policy\s*:\s*([^\n]+)#", $text);
                        $it['Phone'] = $this->re("#\nTel\s*:\s*([^\n]+)#", $text);

                        $bookedFor = $this->re("#\nBooked [Ff]or\s*:*\s*:\s*([^\n]+)#", $text);

                        if ($bookedFor) {
                            $it['GuestNames'] = (array) $bookedFor;
                        }

                        $it['Status'] = $this->re("#\nStatus\s*:\s*([^\n]+)#", $text);

                        $it['ConfirmationNumber'] = $this->re("#\nConfirmation code\s*:\s*([^\n]+)#", $text);

                        $all[] = $it;
                    }

                    if ($type === 'CAR') {
                        $it['Kind'] = 'L';

                        $it['TotalCharge'] = $this->re('/(?:Total price for car rental|Estimated price for car rental)\s*:\s*([.\d]+)\s+([^\d\n)(\]\[]+)/', $text);
                        $it['Currency'] = $this->re(2);

                        $it['PickupDatetime'] = strtotime($this->re("#\nPick-up\s*:*\s*:\s*([^\n]*?\s+\d+:\d+(?:\s*[AaPp][Mm])?),\s*(.*)#", $text), $this->date);
                        $it['PickupLocation'] = $this->re(2);

                        $it['DropoffDatetime'] = strtotime($this->re("#\nDrop-off\s*:*\s*:\s*([^\n]*?\s+\d+:\d+(?:\s*[AaPp][Mm])?),\s*(.*)#", $text), $this->date);
                        $it['DropoffLocation'] = $this->re(2);

                        $parts = preg_split('/^[>\s]*Drop-off\s*:/m', $text);

                        if (count($parts) === 2) {
                            foreach (['Pickup', 'Dropoff'] as $keyPart => $prefix) {
                                if (preg_match('/^[>\s]*Address\s*:\s*(.+)$/m', $parts[$keyPart], $matches)) {
                                    $it[$prefix . 'Location'] = trim($it[$prefix . 'Location'] . ', ' . $matches[1], ' ,');
                                }

                                if (preg_match('/^[>\s]*Phone\s*:[\sA-Z]*([-\d\s]+)$/m', $parts[$keyPart], $matches)) {
                                    $it[$prefix . 'Phone'] = $matches[1];
                                }

                                if (preg_match('/^[>\s]*Opening\s+[Hh]ours\s*:\s*(.+)$/m', $parts[$keyPart], $matches)) {
                                    $it[$prefix . 'Hours'] = $matches[1];
                                }
                            }
                        }

                        $it['RentalCompany'] = $this->re("#\nCompany\s*:*\s*:\s*([^\n]+)#", $text);
                        $it['CarType'] = $this->re("#\nVehicle type\s*:*\s*:\s*([^\n]+)#", $text);
                        $it['RenterName'] = $this->re("#\nBooked for\s*:*\s*:\s*([^\n]+)#", $text);

                        $it['Status'] = $this->re("#\nStatus\s*:\s*([^\n]+)#", $text);

                        $it['Number'] = $this->re("#\nConfirmation code\s*:\s*([^\n]+)#", $text);

                        $all[] = $it;
                    }
                }

                $it = $all;
            },

            // Parsed file "amadeus/it-2.eml"
            "#THANK YOU FOR CHOOSING ICELANDAIR#" => function (&$it) {
                $this->xBase($this->http); // helper

                $body = $this->http->Response['body']; // full html
                $text = $this->mkText($body); // text representation
                //$tabbed = $this->mkText($body, true); // text with tabs

                // @Handlers
                $it['Kind'] = "T";

                $it['RecordLocator'] = trim($this->re("#\sBOOKING REF\s*([^\n]+)#", $text));

                $it['Passengers'] = $this->re("#\n([^\n]+)TICKET:([^\n]+)#", $text);

                $it['ReservationDate'] = $this->mkDate($this->re("#DATE\s+(\d{2}\w{3}\d{2})\n#", $text));

                $year = date('Y', $it['ReservationDate']);

                // Parse segments
                $flights = $this->re("#\n(SERVICE\s+FROM.*?)RESERVATION NUMBER#ms", $body);
                $array = preg_split("#\n([\w\d]+ *\- *[^\n]+)\n#", $flights, -1, PREG_SPLIT_DELIM_CAPTURE);
                array_shift($array);

                for ($i = 0; $i < count($array) - 1; $i += 2) {
                    $seg = [];
                    $seg['FlightNumber'] = trim($this->re("#([^\-]+)\s*\-\s*([^\n\-]+)$#", $array[$i], 2));
                    $seg['AirlineName'] = $this->re(1);

                    // simplify code
                    $r = preg_replace("# {2,100}#", '|', $array[$i + 1]);

                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;

                    $seg['DepDate'] = $this->mkDate($this->re("#([^\|]+)\|([^\|]+)\|([^\|]+)\|(\d{2})(\d{2})\|(\d{2})(\d{2})\n#", $r) . $year . ' ' . $this->re(4) . ':' . $this->re(5));
                    $seg['DepName'] = $this->re(2);
                    $seg['ArrName'] = $this->re(3);
                    $seg['ArrDate'] = $this->re(6) . ':' . $this->re(7); // time

                    $baseDate = $this->re(1);

                    $seg['DepName'] .= ", " . $this->re("#\n\|([^\|]+)\|([^\|]+)\|*([^\|]+)*\n#", $r);
                    $seg['ArrName'] .= ", " . $this->re(2);
                    $seg['ArrDate'] = $this->mkDate(($this->re(3) ? $this->re(3) : $baseDate) . "$year " . $seg['ArrDate']);

                    $seg['Aircraft'] = trim($this->re("#EQUIPMENT:([^\n]+)#", $r));
                    $seg['Cabin'] = trim($this->re("#RESERVATION WAITLISTED\s*\-\s*([^\n]+)#", $r));
                    $seg['Duration'] = trim($this->re("#\|DURATION\s*([^\n]+)#", $r));

                    $it['TripSegments'][] = $seg;
                }
            },
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/amadeus\.com/i', $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (isset($headers["from"])) {
            return preg_match('/[@.]amadeus\.(com|net)/i', $headers['from']);
        } else {
            return false;
        }
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->FindPreg('#\n\s*ELECTRONIC\s+TICKET\s*\n\s*PASSENGER\s+ITINERARY\s+RECEIPT#i')) {
            return false;
        } // Such emails are supported by TAccountCheckerAmadeusEmailelectronicticketplaintext
        $this->xBase($this->http);

        if (empty($textBody = $this->http->Response['body'])) {
            $textBody = $parser->getPlainBody();
        }

        if (stripos($textBody, 'amadeus.com') === false && stripos($textBody, 'amadeus.net') === false) {
            return false;
        }

        if (strpos($textBody, 'FLIGHT RESERVATION') !== false || strpos($textBody, 'HOTEL RESERVATION') !== false || strpos($textBody, 'CAR RESERVATION') !== false) {
            return true;
        }

        return (isset($this->reText) && $this->reText ? $this->smartMatch($parser) : false)
            || (isset($this->reHtml) && $this->reHtml ? preg_match($this->reHtml, $textBody) : false);
    }

    public function smartMatch(\PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody();

        $isRe = preg_match("#[\|\(\)\[\].\+\*\!\^]#", $this->reText) ? true : false;

        if ($isRe) {
            return preg_match($this->reText, $body);
        } else {
            $find = preg_replace("#^\#([^\#]+)\#[imxse]*$#", '\1', $this->reText);

            if (stripos($body, $find) !== false) {
                return true;
            }
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        /*
                return false; // This parser catches new email examples which it should not work with. Also it is awful and non supportable. During work with #10575 new format parsers will be created for email examples which this parser supports
        */
        $this->date = strtotime($parser->getHeader('date'));
        $itineraries = [];
        $itineraries['Kind'] = 'R';

        if (empty($textBody = $parser->getPlainBody())) {
            $textBody = text($parser->getHTMLBody());
        }

        $this->http->SetBody($textBody);

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $textBody)) {
                $processor($itineraries);

                break;
            }
        }

        return [
            'emailType'  => 'reservations',
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];
    }

    public function brToEn($date)
    {
        $date = preg_replace("#set(embro|)#i", 'sep', $date);
        $date = preg_replace("#out(ubro|)#i", 'oct', $date);
        $date = preg_replace("#nov(embro|)#i", 'nov', $date);
        $date = preg_replace("#dez(|embro)#i", 'dec', $date);
        $date = preg_replace("#jan(eiro|)#i", 'jan', $date);
        $date = preg_replace("#fev(ereiro|)#i", 'feb', $date);
        $date = preg_replace("#mar(ço|)#i", 'mar', $date);
        $date = preg_replace("#abr(il|)#i", 'apr', $date);
        $date = preg_replace("#mai(o|)#i", 'may', $date);
        $date = preg_replace("#jun(ho|)#i", 'jun', $date);
        $date = preg_replace("#jul(ho|)#i", 'jul', $date);
        $date = preg_replace("#ago(sto|)#i", 'aug', $date);

        return preg_replace("#\s+#", '', $date);
    }

    public function mkCost($value)
    {
        if (preg_match("#,#", $value) && preg_match("#\.#", $value)) { // like 1,299.99
            $value = preg_replace("#,#", '', $value);
        }

        $value = preg_replace("#,#", '.', $value);
        $value = preg_replace("#[^\d\.]#", '', $value);

        return is_numeric($value) ? (float) number_format($value, 2, '.', '') : null;
    }

    public function mkDate($date, $reltime = null)
    {
        if (!$reltime) {
            $check = strtotime($this->glue($this->mkText($date), ' '));

            return $check ? $check : null;
        }

        $unix = is_numeric($date) ? $date : strtotime($this->glue($this->mkText($date), ' '));

        if ($unix) {
            $guessunix = strtotime(date('Y-m-d', $unix) . ' ' . $reltime);

            if ($guessunix < $unix) {
                $guessunix += 60 * 60 * 24;
            } // inc day

            return $guessunix;
        }

        return null;
    }

    public function mkText($html, $preserveTabs = false, $stringifyCells = true)
    {
        $html = preg_replace("#&" . "nbsp;#uims", " ", $html);
        $html = preg_replace("#&" . "amp;#uims", "&", $html);
        $html = preg_replace("#&" . "quot;#uims", '"', $html);
        $html = preg_replace("#&" . "lt;#uims", '<', $html);
        $html = preg_replace("#&" . "gt;#uims", '>', $html);

        if ($stringifyCells && $preserveTabs) {
            $html = preg_replace_callback("#(</t(d|h)>)\s+#uims", function ($m) {return $m[1]; }, $html);

            $html = preg_replace_callback("#(<t(d|h)(\s+|\s+[^>]+|)>)(.*?)(<\/t(d|h)>)#uims", function ($m) {
                return $m[1] . preg_replace("#[\r\n\t]+#ums", ' ', $m[4]) . $m[5];
            }, $html);
        }

        $html = preg_replace("#<(td|th)(\s+|\s+[^>]+|)>#uims", "\t", $html);

        $html = preg_replace("#<(p|tr)(\s+|\s+[^>]+|)>#uims", "\n", $html);
        $html = preg_replace("#</(p|tr|pre)>#uims", "\n", $html);

        $html = preg_replace("#\r\n#uims", "\n", $html);
        $html = preg_replace("#<br(/|)>#uims", "\n", $html);
        $html = preg_replace("#<[^>]+>#uims", ' ', $html);

        if ($preserveTabs) {
            $html = preg_replace("#[ \f\r]+#uims", ' ', $html);
        } else {
            $html = preg_replace("#[\t \f\r]+#uims", ' ', $html);
        }

        $html = preg_replace("#\n\s+#uims", "\n", $html);
        $html = preg_replace("#\s+\n#uims", "\n", $html);
        $html = preg_replace("#\n+#uims", "\n", $html);

        return trim($html);
    }

    public function xBase($newInstance)
    {
        $this->xInstance = $newInstance;
    }

    public function xHtml($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }

        return $instance->FindHTMLByXpath($path);
    }

    public function xNode($path, $instance = null)
    {
        if (!$instance) {
            $instance = $this->xInstance;
        }
        $nodes = $instance->FindNodes($path);

        return count($nodes) ? implode("\n", $nodes) : null; //$instance->FindSingleNode($path);
    }

    public function xText($path, $preserveCaret = false, $instance = null)
    {
        if ($preserveCaret) {
            return $this->mkText(xHtml($path, $instance));
        } else {
            return $this->xNode($path, $instance);
        }
    }

    public function mkImageUrl($imgTag)
    {
        if (preg_match("#src=(\"|'|)([^'\"]+)(\"|'|)#ims", $imgTag, $m)) {
            return $m[2];
        }

        return null;
    }

    public function glue($str, $with = ", ")
    {
        return implode($with, explode("\n", $str));
    }

    public function re($re, $text = false, $index = 1)
    {
        if (is_numeric($re) && $text == false) {
            return ($this->lastRe && isset($this->lastRe[$re])) ? $this->lastRe[$re] : null;
        }

        $this->lastRe = null;

        if (is_callable($text)) { // we have function
            // go through the text using replace function
            return preg_replace_callback($re, function ($m) use ($text) {
                return $text($m);
            }, $index); // index as text in this case
        }

        if (preg_match($re, $text, $m)) {
            $this->lastRe = $m;

            return $m[$index] ?? $m[0];
        } else {
            return null;
        }
    }

    public function mkNice($text, $glue = false)
    {
        $text = $glue ? $this->glue($text, $glue) : $text;

        $text = $this->mkText($text);
        $text = preg_replace("#,+#ms", ',', $text);
        $text = preg_replace("#\s+,\s+#ms", ', ', $text);
        $text = preg_replace_callback("#([\w\d]),([\w\d])#ms", function ($m) {return $m[1] . ', ' . $m[2]; }, $text);
        $text = preg_replace("#[,\s]+$#ms", '', $text);

        return $text;
    }

    public function mkCurrency($text)
    {
        if (preg_match("#\\$#", $text)) {
            return 'USD';
        }

        if (preg_match("#£#", $text)) {
            return 'GBP';
        }

        if (preg_match("#€#", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bCAD\b#i", $text)) {
            return 'CAD';
        }

        if (preg_match("#\bEUR\b#i", $text)) {
            return 'EUR';
        }

        if (preg_match("#\bUSD\b#i", $text)) {
            return 'USD';
        }

        if (preg_match("#\bBRL\b#i", $text)) {
            return 'BRL';
        }

        if (preg_match("#\bCHF\b#i", $text)) {
            return 'CHF';
        }

        if (preg_match("#\bHKD\b#i", $text)) {
            return 'HKD';
        }

        if (preg_match("#\bSEK\b#i", $text)) {
            return 'SEK';
        }

        if (preg_match("#\bZAR\b#i", $text)) {
            return 'ZAR';
        }

        if (preg_match("#\bIN(|R)\b#i", $text)) {
            return 'INR';
        }

        return null;
    }

    public function arrayTabbed($tabbed, $divRowsRe = "#\n#", $divColsRe = "#\t#")
    {
        $r = [];

        foreach (preg_split($divRowsRe, $tabbed) as $line) {
            if (!$line) {
                continue;
            }
            $arr = [];

            foreach (preg_split($divColsRe, $line) as $item) {
                $arr[] = trim($item);
            }
            $r[] = $arr;
        }

        return $r;
    }

    public function arrayColumn($array, $index)
    {
        $r = [];

        foreach ($array as $in) {
            $r[] = $in[$index] ?? null;
        }

        return $r;
    }

    public function orval()
    {
        $array = func_get_args();
        $n = sizeof($array);

        for ($i = 0; $i < $n; $i++) {
            if (((gettype($array[$i]) === 'array' || gettype($array[$i]) === 'object') && sizeof($array[$i]) > 0) || $i === $n - 1) {
                return $array[$i];
            }

            if ($array[$i]) {
                return $array[$i];
            }
        }

        return '';
    }

    public function mkClear($re, $text, $by = '')
    {
        return preg_replace($re, $by, $text);
    }

    public function grep($pattern, $input, $flags = 0)
    {
        if (gettype($flags) === 'function') {
            $r = [];

            foreach ($input as $item) {
                $res = preg_replace_callback($pattern, $flags, $item);

                if ($res !== false) {
                    $r[] = $res;
                }
            }

            return $r;
        }

        return preg_grep($pattern, $input, $flags);
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

    public function xPDF($parser, $wildcard = null)
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

    public function correctByDate($date, $anchorDate)
    {
        // $anchorDate should be earlier than $date
        // not implemented yet
        return $date;
    }

    public static function getEmailLanguages()
    {
        return ['en', 'es', 'de'];
    }

    public static function getEmailTypesCount()
    {
        return 3; // +4 in new formats
    }

    public function IsEmailAggregator()
    {
        return true;
    }

    // функция возвращает ключ из $array в котором был найден $recordLocator, иначе FALSE
    protected function recordLocatorInArray($recordLocator, $array)
    {
        foreach ($array as $key => $value) {
            if ($value['Kind'] === 'T') {
                if ($value['RecordLocator'] === $recordLocator) {
                    return $key;
                }
            }
        }

        return false;
    }
}
