<?php

namespace AwardWallet\Engine\amextravel\Email;

// Do not repeat!
// not used all formats, some of them in YourTravelplan.php
class Itinerary1 extends \TAccountChecker
{
    public $mailFiles = "amextravel/it-1437974.eml, amextravel/it-19.eml, amextravel/it-3.eml"; // and bcdtravel

    public $processors = [];
    public $reFrom = '#([@.]amextravel\.com|American Express)#';
    public $reProvider = '#[@.]amextravel\.com#';
    public $reText = null;
    public $reHtml = "#American Express Global Business Travel is pleased to deliver|please\s+contact\s+American\s+Express|has been posted to the American Express|Thank you for booking with American Express Travel|\d{4}\s+(American Express\s*-|Deem Inc.)\s*All rights|American Express (Global )?(Business )?Travel#i";

    public function __construct()
    {
        parent::__construct();

        // Define processors
        $this->processors = [
            // Parsed file "amextravel/it-1.eml"
            "#Thank you for booking with American Express Travel#" => function (&$it, $parser) {
                $it = ['Kind' => 'T'];

                $it['RecordLocator'] = $this->http->FindSingleNode("//h1[contains(text(), 'RECORD LOCATORS')]/ancestor::tr[1]/following-sibling::tr[1]//span[2]");
                $names = [];

                for ($i = 1; $i < 10; $i++) {
                    $name = $this->http->FindSingleNode("//h1[contains(text(), 'TRAVELERS')]/ancestor::tr[1]/following-sibling::tr[1]//*[contains(text(), 'Passenger $i:')]/following-sibling::*[1]");

                    if ($name) {
                        $names[$name] = 1;
                    }
                }

                $it['Passengers'] = implode(', ', array_keys($names));

                $lps = $this->http->FindNodes("//h1[contains(text(), 'TRAVELERS')]/ancestor::tr[1]/following-sibling::tr[1]//*[contains(text(), 'Loyalty Program')]/ancestor::td[1]/following-sibling::td[1]");
                $it['AccountNumbers'] = implode(', ', $lps);

                $it['TripSegments'] = [];
                $nodes = $this->http->XPath->query("//img[contains(@src, 'your-flight-details.png')]/ancestor::table[1]/tbody/tr");

                for ($i = 1; $i < $nodes->length; $i++) {
                    $tr = $nodes->item($i);
                    $seg = [];

                    $info = $this->http->FindSingleNode(".//*[contains(text(),'Operated by')]/ancestor::td[1]", $tr);

                    if (preg_match("#(\d+)\s+Operated by\s+(.*)#", $info, $m)) {
                        $seg['FlightNumber'] = $m[1];
                        $seg['AirlineName'] = $m[2];
                    }

                    $array = $this->http->FindNodes(".//*[contains(text(),'Operated by')]/ancestor::td[1]/following-sibling::td[1]//td", $tr);

                    foreach ($array as $item) {
                        if (preg_match("#(\d+h\s+\d+m)#", $item, $m)) {
                            $seg['Duration'] = $m[1];
                        }

                        if (preg_match("#Seats:\s*([\d\w]+)#", $item, $m)) {
                            $seg['Seats'] = $m[1];
                        }

                        if (preg_match("#Non\-Stop#", $item, $m)) {
                            $seg['Stops'] = 0;
                        }
                    }

                    if (preg_match("#(\d+:\d+\w+)\s+(.*?)\s*\((\w{3})\)#", $array[3], $m)) {
                        $seg['DepName'] = $m[2];
                        $seg['DepCode'] = $m[3];
                        $seg['DepDate'] = strtotime($array[1] . ', ' . $m[1]);
                    }

                    if (preg_match("#(\d+:\d+\w+)\s+(.*?)\s*\((\w{3})\)#", $array[4], $m)) {
                        $seg['ArrName'] = $m[2];
                        $seg['ArrCode'] = $m[3];
                        $seg['ArrDate'] = strtotime($array[1] . ', ' . $m[1]);
                    }

                    $it['TripSegments'][] = $seg;
                }
            },

            // Parsed file "amextravel/it-2.eml"
            "#has been posted to the American Express#" => function (&$it, $parser) {
                $pdf = $this->toText($this->extractPDF($parser, '.'));

                $pdf = preg_replace("#Page \d+ of \d+\n#", '', $pdf);

                // prepare record locators
                $locators = [];

                if (preg_match("#Airline Record Locators\nAirline Reference\nCarrier\n(.*?)\nAdditional Messages#ms", $pdf, $m)) {
                    preg_replace_callback("#([\w\d]+)\n[^\n]+#ms", function ($m) use (&$locators) {
                        $locators[] = $m[1];
                    }, $m[1]);
                }

                // passengers
                $passengers = preg_match("#Travel Arrangements for\s+([^\n]+)#", $pdf, $m) ? $m[1] : null;

                // get costs from tickets
                $tickets = [];

                if (preg_match("#Invoice Details\n(.*?)\nTravel Details#ms", $pdf, $m)) {
                    $ticketArray = preg_split("#\nCharges\n#ms", $m[1]);
                    array_shift($ticketArray);

                    foreach ($ticketArray as $ticket) {
                        $item = [];

                        if ($item['TotalCharge'] = preg_match("#Total \((\w{3})\) Ticket Amount\s+([^\n]+)#ms", $ticket, $m) ? $m[2] : null) {
                            $item['Currency'] = $m[1];
                        }

                        $item['BaseFare'] = preg_match("#Ticket Base Fare \(\w{3}\)\s+([^\n]+)#ms", $ticket, $m) ? $m[1] : null;
                        $item['Tax'] = preg_match("#Ticket Tax Fare\s+([^\n]+)#ms", $ticket, $m) ? $m[1] : null;

                        $tickets[] = $item;
                    }
                }

                $byDate = preg_split("#\nTravel Details\n#ms", $pdf);
                array_shift($byDate); // junk

                $res = [];

                // create trip array by dates
                foreach ($byDate as $details) {
                    if (preg_match("#^([^\n]+)#", $details, $m)) {
                        $date = $m[1];

                        if (strtotime($date)) {
                            if (!isset($res[$date])) {
                                $res[$date] = [];
                            }
                            $byType = preg_split("#\n((?:Flight|Hotel)\s+Information\n)#ms", $details, -1, PREG_SPLIT_DELIM_CAPTURE);

                            for ($i = 0; $i < count($byType); $i++) {
                                if (preg_match("#((?:Flight|Hotel)\s+Information)#", $byType[$i], $m)) {
                                    $res[$date][] = $m[1] . "\n" . $byType[++$i];
                                }
                            }
                        } else {
                            exit("Something wrong!");
                        }
                    }
                }

                $all = [];

                $ticket = 0;

                // parse array
                foreach ($res as $date => $array) {
                    foreach ($array as $details) {
                        $it = [];

                        // Flight Reservation
                        if (preg_match("#^Flight\s+Information#", $details)) {
                            $it['Kind'] = 'T';
                            $it['RecordLocator'] = $locators[$ticket];

                            if (isset($tickets[$ticket])) {
                                $it['TotalCharge'] = $tickets[$ticket]['TotalCharge'];
                                $it['BaseFare'] = $tickets[$ticket]['BaseFare'];
                                $it['Tax'] = $tickets[$ticket]['Tax'];
                                $it['Currency'] = $tickets[$ticket]['Currency'];
                            }

                            $ticket++;

                            $it['Passengers'] = $passengers;

                            $it['TripSegments'] = [];
                            $seg = [];

                            $seg['AirlineName'] = preg_match("#\nAirline\s+([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['FlightNumber'] = preg_match("#\nFlight\n([^\n]+)#", $details, $m) ? $m[1] : null;

                            $seg['DepName'] = preg_match("#\nOrigin\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['ArrName'] = preg_match("#\nDestination\n([^\n]+)#", $details, $m) ? $m[1] : null;

                            $seg['DepDate'] = strtotime($date . ', ' . (preg_match("#\nDeparting\n([^\n]+)#", $details, $m) ? $m[1] : ''));
                            $seg['ArrDate'] = strtotime($date . ', ' . (preg_match("#\nArriving\n([^\n]+)#", $details, $m) ? $m[1] : ''));

                            $seats = preg_match("#\nSeat\n([^\n]+)#", $details, $m) ? ($m[1] != 'Unassigned' ? $m[1] : null) : null;

                            if ($seats) {
                                $seg['Seats'] = $seats;
                            }

                            $seg['Cabin'] = preg_match("#\nClass\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['Duration'] = preg_match("#\nEstimated time\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['TraveledMiles'] = preg_match("#\nDistance\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['Meal'] = preg_match("#\nMeal Service\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $seg['Aircraft'] = preg_match("#\nPlane\n([^\n]+)#", $details, $m) ? $m[1] : null;

                            $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                            $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                            $it['TripSegments'][] = $seg;
                        }
                        // Hotel Reservation
                        elseif (preg_match("#^Hotel\s+Information#", $details)) {
                            $it['Kind'] = 'R';

                            $it['ConfirmationNumber'] = preg_match("#\nConfirmation Number\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $it['GuestNames'] = $passengers;

                            $it['HotelName'] = preg_match("#\nHotel\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $it['Address'] = preg_match("#\nHotel Address\n(.*?)\nConfirmation Number#ms", $details, $m) ? preg_replace("#[\r\n]+#", ', ', $m[1]) : null;

                            $it['CheckInDate'] = preg_match("#\nCheck in Date\n([^\n]+)#", $details, $m) ? strtotime($m[1]) : null;
                            $it['CheckOutDate'] = preg_match("#\nCheck out Date\n([^\n]+)#", $details, $m) ? strtotime($m[1]) : null;

                            $it['Rate'] = preg_match("#\nHotel Rate\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $it['Phone'] = preg_match("#\nPhone Number\n([^\n]+)#", $details, $m) ? $m[1] : null;
                            $it['Fax'] = preg_match("#\nFax Number\n([^\n]+)#", $details, $m) ? $m[1] : null;
                        }

                        $all[] = $it;
                    }
                }

                $it = $all;
            },

            // Parsed file "amextravel/it-3.eml"
            "#please\s+contact\s+American\s+Express#" => function (&$it, $parser) {
                $it = ['Kind' => 'R'];
                $text = $this->toText($parser->getHtmlBody());

                $it['GuestNames'] = preg_match("#\nTraveler Name:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
                $it['HotelName'] = preg_match("#\nHotel name:\s*([^\n]+)#", $text, $m) ? $m[1] : null;
                $it['ConfirmationNumber'] = preg_match("#Confirmation Number:\s*([\d]+)#", $text, $m) ? $m[1] : null;

                foreach (['CheckIn' => 'Check in', 'CheckOut' => 'Check out'] as $key => $value) {
                    $s = re('#\n' . $value . ':\s*\w+,\s+([^\n]+)#', $text);

                    if ($s) {
                        $it[$key . 'Date'] = strtotime(str_replace(',', '', $s));
                    }
                }
                //				$it['CheckInDate'] = preg_match("#\nCheck in:\s*\w+\s+([^\n]+)#", $text, $m)?strtotime(str_replace(',', '', $m[1])):null;
                //				$it['CheckOutDate'] = preg_match("#\nCheck out:\s*\w+\s+([^\n]+)#", $text, $m)?strtotime(str_replace(',', '', $m[1])):null;

                $it['Guests'] = preg_match("#\nNumber of persons:\s*([\d]+)#", $text, $m) ? $m[1] : null;
                $it['Rate'] = preg_match("#\nRoom price:\s*([\d.,]+)#", $text, $m) ? $m[1] : null;
                $it['Address'] = preg_match("#\nAddress:\s*([^\n]+)#", $text, $m) ? $m[1] : null;

                if ($phfax = preg_match("#\nPhone/Fax:\s*([^\n]+)#", $text, $m) ? $m[1] : null) {
                    [$it['Phone'], $it['Fax']] = explode("/", $phfax);
                }

                if (preg_match("#Estimated Total Room Price\s+\([^\)]+\):\s*([\d.A-Z ]+)#", $text, $m)) {
                    $it['Total'] = preg_replace("#[^\d.,]+#", '', $m[1]);
                    $it['Currency'] = preg_match("#\b([A-Z]{3})\b#", $m[1], $m) ? $m[1] : null;
                }
            },

            // Parsed file "amextravel/it-4.eml"
            // Parsed file "amextravel/it-16.eml"
            "#American Express Global Business Travel is pleased to deliver#" => function (&$it, $parser) {
                $it = ['Kind' => 'T'];
                $body = $parser->getHtmlBody();

                $it['TotalCharge'] = 0;

                preg_replace_callback("#TICKET \d+ \- ([A-Z]{3})([\d.]+)#", function ($m) use (&$it) {
                    $it['Currency'] = $m[1];
                    $it['TotalCharge'] += $m[2];
                }, $body);

                $nodes = $this->http->XPath->query("//*[contains(text(), 'Flight Information')]/ancestor-or-self::*[self::div or self::font][1]/following-sibling::table[1]");

                if ($nodes->length == 0) {
                    $nodes = $this->http->XPath->query("//*[contains(text(), 'Flight Information')]/ancestor-or-self::*[self::div or self::font or starts-with(name(), 'h')][1]/following-sibling::table[1]");
                }
                $it['TripSegments'] = [];

                for ($i = 0; $i < $nodes->length; $i++) {
                    $table = $nodes->item($i);
                    $seg = [];

                    $it['RecordLocator'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Airline Booking Ref']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table);

                    $date = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Date']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table);
                    $seg['AirlineName'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Airline']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table);

                    $seg['FlightNumber'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Flight/Class']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table, true, "#^([^\s]+)#");
                    $seg['Cabin'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Flight/Class']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table, true, "#^[^\s]+\s+(.*)#");

                    $seg['DepName'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Origin']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table, true, "#^[^\s]+\s+(.*)#");
                    $seg['ArrName'] = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Destination']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table, true, "#^[^\s]+\s+(.*)#");

                    $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                    $seg['ArrCode'] = TRIP_CODE_UNKNOWN;

                    $seg['DepDate'] = strtotime($date . ', ' . $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Departing']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table));

                    if ($str = $this->http->FindSingleNode("(.//*[normalize-space(text()) = 'Arriving']/ancestor-or-self::td)[1]/following-sibling::td[1]", $table)) {
                        if (preg_match("#^(.*?)/(.*)$#", $str, $m)) {
                            $seg['ArrDate'] = strtotime($m[2] . ', ' . $m[1]);
                        } else {
                            $seg['ArrDate'] = strtotime($date . ', ' . $str);
                        }
                    }

                    $it['TripSegments'][] = $seg;
                }
            },

            /**
             * @example amextravel/it-5.eml
             * @example amextravel/it-12.eml
             */
            "#\d{4}\s+(American Express\s*\-|Deem Inc.)\s*All rights#" => function (&$it, $parser) {
                $text = $this->toText($parser->getHtmlBody());

                //$amexData['RecordLocator'] = preg_match("#\nRecord locator\s*:\s*([^\s]+)#", $text, $m)?$m[1]:null;

                $tripsByRecordLocator = [];
                $segments = preg_split("#\n(Flight from:|Hotel in)\s*#", $text, -1, PREG_SPLIT_DELIM_CAPTURE);

                array_shift($segments);

                foreach ($segments as $i => $split) {
                    switch ($split) {
                        // FLIGHTS #
                        case "Flight from:":
                            if (isset($segments[$i + 1])) {
                                $segmentData = $segments[$i + 1];
                            } else {
                                break;
                            }
                            $seg = [];
                            $recordLocator = preg_match('#Reservation number:\s*(\S+)#', $segmentData, $m) ? $m[1] : null;
                            $date = null;

                            if (preg_match("#^[^\(]+\s*\(\w{3}\)\s+([^\n]+)\s+([^\n]+)#", $segmentData, $m)) {
                                $seg['FlightNumber'] = preg_replace("#[^\d]#", '', $m[1]);

                                if (preg_match('#^(.+?)\s*(\d+)$#', $m[1], $m1)) {
                                    $seg['FlightNumber'] = $m1[2];
                                    $seg['AirlineName'] = $m1[1];
                                }
                                $date = $m[2];
                            }

                            if (preg_match("#\nEstimated Time:\s*([^\n]+)#", $segmentData, $m)) {
                                $seg['Duration'] = $m[1];
                            }

                            if (preg_match("#\nDistance:\s*([^\n]+)#", $segmentData, $m)) {
                                $seg['TraveledMiles'] = $m[1];
                            }

                            if (preg_match("#\nClass:\s*([^\n]+)#", $segmentData, $m)) {
                                $seg['Cabin'] = $m[1];
                            }

                            $name = null;

                            if (preg_match("#\nSeat:([^:]+):(.*)#", $segmentData, $m)) {
                                $seg['Seats'] = trim($m[2]);
                                $name = trim($m[1]);
                            }

                            if (preg_match("#\nPlane type:\s*([^\n]+)#", $segmentData, $m)) {
                                $seg['Aircraft'] = $m[1];
                            }

                            if (preg_match("#\nDepart:\s*(\d+:\d+\s+\w{2})\s+(.*?)\s*\((\w{3})\)#", $segmentData, $m)) {
                                $seg['DepName'] = $m[2];
                                $seg['DepCode'] = $m[3];

                                if (isset($date)) {
                                    $seg['DepDate'] = strtotime($date . ', ' . $m[1]);
                                }
                            }

                            if (preg_match("#\nArrive:\s*(\d+:\d+\s+\w{2})\s+(.*?)\s*\((\w{3})\)#", $segmentData, $m)) {
                                $seg['ArrName'] = $m[2];
                                $seg['ArrCode'] = $m[3];

                                if (isset($date)) {
                                    $seg['ArrDate'] = strtotime($date . ', ' . $m[1]);
                                }
                            }

                            $membership = preg_match("#Membership:\s*[^:]+:[^-]+-\s*(\S+)#", $segmentData, $m) ? $m[1] : null;

                            if (isset($recordLocator)) {
                                $tripsByRecordLocator[$recordLocator]['RecordLocator'] = $recordLocator;
                                $tripsByRecordLocator[$recordLocator]['TripSegments'][] = $seg;

                                if (isset($name)) {
                                    $tripsByRecordLocator[$recordLocator]['Passengers'][] = $name;
                                }

                                if (isset($membership)) {
                                    $tripsByRecordLocator[$recordLocator]['AccountNumbers'][] = $membership;
                                }
                            }

                        break;
                        // HOTELS #
                        case "Hotel in":
                            if (isset($segments[$i + 1])) {
                                $segmentData = $segments[$i + 1];
                            } else {
                                break;
                            }
                            $lines = explode("\n", $segmentData);

                            $itinerary = ['Kind' => 'R'];

                            if (preg_match("#Reservation Number:\s*(\S+)#", $segmentData, $matches)) {
                                $itinerary['ConfirmationNumber'] = $matches[1];
                            }

                            if (preg_match('#Phone:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['Phone'] = $matches[1];
                            }

                            if (preg_match('#Fax:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['Fax'] = $matches[1];
                            }

                            if (preg_match('#Check-In:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['CheckInDate'] = strtotime($matches[1]);
                            }

                            if (preg_match('#Check-Out:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['CheckOutDate'] = strtotime($matches[1]);
                            }

                            if (preg_match('#Room Type:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['RoomType'] = $matches[1];
                            }

                            if (preg_match('#Hotel Special Request:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['RoomTypeDescription'] = $matches[1];
                            }

                            if (preg_match('#Number of Rooms:\s*(\d+)#', $segmentData, $matches)) {
                                $itinerary['Rooms'] = $matches[1];
                            }

                            if (preg_match('#Number of Guests:\s*(\d+)#', $segmentData, $matches)) {
                                $itinerary['Guests'] = $matches[1];
                            }

                            if (preg_match('#Cancellation Policy:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['CancellationPolicy'] = $matches[1];
                            }

                            if (preg_match('#Rate Details:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['Rate'] = $matches[1];
                            }

                            if (preg_match('#Status:\s*(.+)#', $segmentData, $matches)) {
                                $itinerary['Status'] = $matches[1];
                            }
                            $parsePrice = function ($matches, &$amount, &$currency) {
                                if (!isset($amount)) {
                                    if (!empty($matches[1])) {
                                        $currency = $matches[1];
                                    } else {
                                        if ('$' === $matches[2]) {
                                            $currency = 'USD';
                                        } else {
                                            $currency = $matches[2];
                                        }
                                    }
                                }
                                $amount = $matches[3];
                            };

                            if (preg_match('#Taxes:\s*([A-Z]{3})?\s*(\S)(\d+.\d+|\d+)#', $segmentData, $matches)) {
                                $parsePrice($matches, $itinerary['Taxes'], $itinerary['Currency']);
                            }

                            if (preg_match('#Total:\s*([A-Z]{3})?\s*(\S)(\d+.\d+|\d+)#', $segmentData, $matches)) {
                                $parsePrice($matches, $itinerary['Cost'], $itinerary['Currency']);
                            }

                            $itinerary['HotelName'] = $lines[2];
                            $itinerary['Address'] = $lines[1] . ', ' . $lines[2];
                            $it[] = $itinerary;

                        break;
                    }
                }

                foreach ($tripsByRecordLocator as $recordLocator => &$trip) {
                    if (isset($trip['AccountNumbers'])) {
                        $trip['AccountNumbers'] = implode(', ', $trip['AccountNumbers']);
                    }
                    $trip['Kind'] = 'T';
                    $it[] = $trip;
                }
                unset($trip);
                /*if(preg_match('/([^\d])?(\d+.\d+|\d+)/ims', $this->http->FindSingleNode('//text()[contains(., "Estimated total cost for this traveler")]/ancestor::td[1]/following-sibling::td[1]'), $matches)){
                    if(!empty($matches[1])){
                        if('$' === $matches[1]){
                            $it['Currency'] = 'USD';
                        }else{
                            $it['Currency'] = $matches[1];
                        }
                    }
                    $it['TotalCharge'] = $matches[2];
                }*/
            },
        ];
    }

    public function toText($html)
    {
        $nbsp = '&' . 'nbsp;';
        $html = preg_replace("#<t(d|h)[^>]*>#uims", "\t", $html);
        $html = preg_replace("#&\#160;#ums", " ", $html);
        $html = preg_replace("#$nbsp#ums", " ", $html);
        $html = preg_replace("#<br/*>#uims", "\n", $html);
        $html = preg_replace("#<[^>]*>#ums", " ", $html);
        $html = preg_replace("#\n\s+#ums", "\n", $html);
        $html = preg_replace("#\s+\n#ums", "\n", $html);
        $html = preg_replace("#\n+#ums", "\n", $html);

        $html = preg_replace("#[^\w\d\s:;,./\(\)\[\]\{\}\-\\\$]#", '', $html);

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

    public function ParseYourReservationConfirmation()
    {
        $http = $this->http;
        $xpath = $http->XPath;

        $this->parseMoney($totals = [], 'Tax', $http->FindSingleNode('//td[contains(., "Taxes") and not(.//td)]/following-sibling::td[1]'));
        $this->parseMoney($totals, 'TotalCharge', $http->FindSingleNode('//td[contains(., "Total Cost") and not(.//td)]/following-sibling::td[1]'));

        $segments = [];
        $segmentsNodes = $xpath->query('//*[contains(text(), "Your Seats:")]/ancestor::table[2][.//*[contains(text(), "Operated by")]]');

        foreach ($segmentsNodes as $segmentNode) {
            $segment = [];
//            $segment['ProviderName'] = $http->FindSingleNode('.//*[contains(text(), "Operated by")]/preceding-sibling::*[not(self::img)][last()]', $segmentNode);
            $segment['FlightNumber'] = $http->FindSingleNode('.//*[contains(text(), "Operated by")]/preceding-sibling::*[1]', $segmentNode);
            $segment['AirlineName'] = $http->FindSingleNode('.//*[contains(text(), "Operated by")]/preceding-sibling::*[2]', $segmentNode);
            $segment['Operator'] = $http->FindSingleNode('.//*[contains(text(), "Operated by")]/following-sibling::*[1]', $segmentNode);

            $segment['ProviderName'] = $http->FindSingleNode('.//*[contains(text(), "Operated by")]/preceding-sibling::*[2]', $segmentNode);

            $dataNodeList = $xpath->query('.//table[1]', $segmentNode);

            if ($dataNodeList->length == 1) {
                $dataNode = $dataNodeList->item(0);

                // Duration
                if ($xpath->query('ancestor::table[1]/ancestor::td[1]/table', $dataNode)->length == 1) {
                    $segment['Duration'] = $http->FindSingleNode('.//tr[1]//img/following-sibling::*[1]', $dataNode);
                }

                $baseDate = $http->FindSingleNode('.//tr[not(.//img)][1]/td[1]', $dataNode);
                // "2 Stops | Economy | +1 day"
                // "Non-Stop | Economy"
                // "Economy"
                $timeShift = null;

                if (preg_match('/(((Non-Stop)|((\d+) stops?))\s*\|\s*)?([^\|]+)(\s*\|\s*(.+))?/ims', $http->FindSingleNode('.//tr[not(.//img)][1]/td[2]', $dataNode), $matches)) {
                    if (!empty($matches[5])) {
                        $segment['Stops'] = $matches[5];
                    } elseif (!empty($matches[3])) {
                        $segment['Stops'] = 0;
                    }
                    $segment['Cabin'] = $matches[6];
                }

                // 6:41pm Fort Lauderdale, FL (FLL)
                if (preg_match('/(\d+:\d+(?:\s*[ap]m)?)\s*(.+?),?\s+?\(([A-Z]{3})\)/s', $http->FindSingleNode('.//tr[not(.//img)][2]/td[1]', $dataNode), $matches)) {
                    $segment['DepDate'] = strtotime("{$baseDate}, {$matches[1]}");
                    $segment['DepCode'] = $matches[3];
                    $segment['DepName'] = $matches[2];
                }

                if (preg_match('/(\d+:\d+(?:\s*[ap]m)?)\s*(.+?),?\s+?\(([A-Z]{3})\)/s', $http->FindSingleNode('.//tr[not(.//img)][2]/td[2]', $dataNode), $matches)) {
                    $segment['ArrDate'] = strtotime("{$baseDate}, {$matches[1]}");
                    $segment['ArrCode'] = $matches[3];
                    $segment['ArrName'] = $matches[2];
                }

                if (preg_match('/\d+\w+/ims', $http->FindSingleNode('.//*[contains(text(), "Your Seats:")]/following-sibling::*[1]', $dataNode), $matches)) {
                    $segment['Seats'] = $matches[0];
                }
            }
            $segments[] = $segment;
        }
        $result = [];

        $passengers = $http->FindNodes('//text()[contains(., "TRAVELERS")]/ancestor::tr[1]/following-sibling::tr//text()[contains(., "Passenger")]/ancestor::td[1]', null, '/:\s*(.+)/');
        $ticketNumbers = $http->FindNodes('//text()[contains(., "TRAVELERS")]/ancestor::tr[1]/following-sibling::tr//text()[contains(., "Ticket Number")]/ancestor::td[1]', null, '/:\s*(.+)/');

        // link segments and Record Locators
        $recordLocatorNodes = $xpath->query('//*[contains(text(), "RECORD LOCATORS")]/ancestor::tr[1]/following-sibling::tr/td/*[last()]');
        $linkedSegmentsCounter = 0;

        if ($recordLocatorNodes->length > 0) {
            $segmentsIndex = 0;

            foreach ($recordLocatorNodes as $recordLocatorNode) {
                $recordLocator = CleanXMLValue($recordLocatorNode->nodeValue);

                if (preg_match('/\b[A-Z\d]{5,6}$/', $recordLocator, $matches)) {
                    $recordLocator = $matches[0];
                } else {
                    $recordLocator = CONFNO_UNKNOWN;
                }

                $providerName = $http->FindSingleNode('preceding-sibling::*[1]', $recordLocatorNode);

                // pending record locator and empty airline workaround
                if (empty($providerName)) {
                    $providerName = $http->FindSingleNode('(//*[contains(text(), "Operated by")]/preceding-sibling::*[2])[1]');
                }

                while (isset($segments[$segmentsIndex])) {
                    if (isset($segments[$segmentsIndex]['ProviderName']) && strcasecmp($segments[$segmentsIndex]['ProviderName'], $providerName) === 0) {
                        $linkedSegmentsCounter++;
                        // remove service data
                        unset($segments[$segmentsIndex]['ProviderName']);

                        if (isset($result[$recordLocator])) {
                            $result[$recordLocator]['TripSegments'][] = $segments[$segmentsIndex];
                        } else {
                            $result[$recordLocator] = [
                                'Kind'          => 'T',
                                'RecordLocator' => $recordLocator,
                                /*'ProviderName' => $segments[$segmentsIndex]['ProviderName'],*/
                                'Passengers'    => $passengers,
                                'TicketNumbers' => $ticketNumbers,
                                'TripSegments'  => [
                                    $segments[$segmentsIndex],
                                ],
                            ];
                        }
                    } else {
                        continue 2;
                    }
                    $segmentsIndex++;
                }
            }
        }
        // check if all segments have been linked
        if ($linkedSegmentsCounter == count($segments)) {
            if (1 === count($result)) {
                $result[key($result)] = array_merge($result[key($result)], $totals);
            }

            return array_values($result);
        } else {
            // fallback
            return array_merge([
                'Kind'         => 'T',
                'TripSegments' => $segments,
                'Passengers'   => $passengers,
            ], $totals);
        }
    }

    /**
     * @example amextravel/it-6.eml
     * @example amextravel/it-7.eml
     * @example amextravel/it-8.eml
     * @example amextravel/it-9.eml
     * @example amextravel/it-19.eml
     */
    public function ParsePdf()
    {
        $itineraries = [];
        $http = $this->http;
        $xpath = $http->XPath;
        $this->registerFunctions($xpath);

        $baseDate = null;
        $lastTrip = [];

        $airlinesData = [];
        $recordLocatorsNodes = $xpath->query('//line[p[b[text() = "Airline Record Locators"]]]
            /following-sibling::line[
                p[1][text() = "Airline Reference"] and
                p[2][text() = "Carrier"]
            ]/following-sibling::line[count(following-sibling::line[p[b[text() = "Additional Messages"]]]) = 1]
        ');

        foreach ($recordLocatorsNodes as $recordLocatorNode) {
            if ($airlineName = $http->FindSingleNode('./p[2]', $recordLocatorNode)) {
                $airlinesData[$airlineName]['Kind'] = 'T';
                $airlinesData[$airlineName]['RecordLocator'] = $http->FindSingleNode('./p[1]', $recordLocatorNode);
            }
        }

        $passengersNodes = $xpath->query('//line[p[b[text() = "Loyalty Programs"]]]
            /following-sibling::line[
                p[1][text() = "Vendor"] and
                p[2][text() = "Account"] and
                p[3][text() = "Traveler"]
            ]/following-sibling::line[count(following-sibling::line[p[b[text() = "Airline Record Locators"]]]) = 1]
        ');

        foreach ($passengersNodes as $passengerNode) {
            if ($airlineName = $http->FindSingleNode('./p[1]', $passengerNode)) {
                $airlinesData[$airlineName]['Kind'] = 'T';

                if ($passenger = $http->FindSingleNode('./p[3]', $passengerNode)) {
                    $airlinesData[$airlineName]['Passengers'][] = $passenger;
                }

                if ($accountNumber = $http->FindSingleNode('./p[2]', $passengerNode)) {
                    $airlinesData[$airlineName]['AccountNumbers'][] = $accountNumber;
                }
            }
        }
        $traveler = $http->FindSingleNode('//text()[contains(., "Travel Arrangements for")]/following::text()[normalize-space()][1]');

        foreach ($airlinesData as &$airlineData) {
            if (isset($airlineData['AccountNumbers'])) {
                $airlineData['AccountNumbers'] = implode(', ', $airlineData['AccountNumbers']);
            }
        }
        unset($airlineData);

        $headingNodes = $xpath->query("//line[p[b[
            php:functionString('strcasecmp', text(), 'Flight Information') = 0 or
            php:functionString('strcasecmp', text(), 'Hotel Information') = 0 or
            php:functionString('strcasecmp', text(), 'Rental Car Information') = 0
        ]]]");

        foreach ($headingNodes as $headingN => $headingNode) {
            $itineraryNodes = $xpath->query("./following-sibling::line[count(following-sibling::line[p[b[
                php:functionString('strcasecmp', text(), 'Flight Information') = 0 or
                php:functionString('strcasecmp', text(), 'Hotel Information') = 0 or
                php:functionString('strcasecmp', text(), 'Rental Car Information') = 0
            ]]]) = {$headingNodes->length} - {$headingN} - 1
            or (
                {$headingNodes->length} = {$headingN} + 1
                and
                count(following-sibling::line[p[b[text() = 'Additional Messages']]]) = 1
            )
            ]", $headingNode);

            if ($itineraryNodes->length == 0) {
                continue;
            }
            $date = $http->FindSingleNode('./preceding-sibling::line[1]', $headingNode, false, '/(?:Details)?(\w+ \w+ \d+, \d+)/ims');

            if (!isset($date)) {
                $date = $http->FindSingleNode('./preceding-sibling::line[contains(., "Travel Details")][1]/following-sibling::line[1]', $headingNode, false, '/(?:Details)?(\w+ \w+ \d+, \d+)/ims');
            }

            if (!$date) {
                $date = $http->FindSingleNode('./preceding-sibling::*[1]', $headingNode, false, '/\w+\s+\d+,\s+\d{4}/ims');
            }

            if (!$date) {
                $date = $http->FindSingleNode('./preceding-sibling::*[2]', $headingNode, false, '/\w+\s+\d+,\s+\d{4}/ims');
            }

            if ($date) {
                $baseDate = $date;
            }

            $heading = CleanXMLValue($headingNode->nodeValue);

            switch (strtolower(substr($heading, 0, stripos($heading, " Information")))) {
                case 'flight': $kind = 'T';

break;

                case 'rental car': $kind = 'L';

break;

                case 'hotel': $kind = 'R';

break;

                default:
                    break;
            }
            $parseMethod = "ParsePdf" . $kind;
            $itineraryData = $this->$parseMethod($this->DOMSubstitution($itineraryNodes), $baseDate);

            if ($itineraryData) {
                switch ($kind) {
                    case 'T':
                        if (!empty($itineraryData['AirlineName'])) {
                            if (empty($lastTrip)) {
                                // first segment or airline
                                $lastTrip = [
                                    'AirlineName'  => $itineraryData['AirlineName'],
                                    'TripSegments' => [$itineraryData],
                                ];

                                if (isset($airlinesData[$itineraryData['AirlineName']])) {
                                    $lastTrip = array_merge($lastTrip, $airlinesData[$itineraryData['AirlineName']]);
                                }
                            } else {
                                if ($lastTrip['AirlineName'] === $itineraryData['AirlineName']) {
                                    $lastTrip['TripSegments'][] = $itineraryData;
                                } else {
                                    unset($lastTrip['AirlineName']);
                                    $itineraries[] = $lastTrip;
                                    $lastTrip = [
                                        'AirlineName'  => $itineraryData['AirlineName'],
                                        'TripSegments' => [$itineraryData],
                                    ];
                                    $lastTrip = array_merge($lastTrip, $airlinesData[$itineraryData['AirlineName']]);
                                }
                            }
                        }

                        if (($headingNodes->length == $headingN + 1) && !empty($lastTrip)) {
                            unset($lastTrip['AirlineName']);
                            $itineraries[] = $lastTrip;
                        }

                    break;

                    case 'R':
                        if ($traveler) {
                            $itineraryData['GuestNames'] = $traveler;
                        }
                        // no break
                    default:
                        $itineraries[] = $itineraryData;
                }
            }
        }

        return $itineraries;
    }

    public function ParsePdfT(\HttpBrowser $http, $baseDate)
    {
        //		return null; // Parser toggled of as it is duplication of new format parsers
        $segment['AirlineName'] = $http->FindSingleNode('//p[text() = "Airline"]/following-sibling::p[1]/text()[1]');
        $segment['FlightNumber'] = $http->FindSingleNode('//p[text() = "Flight"]/following-sibling::p[1]');
        $segment['Duration'] = $http->FindSingleNode('//p[text() = "Estimated time"]/following-sibling::p[1]');
        $segment['Aircraft'] = $http->FindSingleNode('//p[text() = "Plane"]/following-sibling::p[1]');
        $segment['Meal'] = $http->FindSingleNode('//p[text() = "Meal Service"]/following-sibling::p[1]');

        $segment['DepName'] = trim($http->FindSingleNode('//p[text() = "Origin"]/following-sibling::p[1]') . ' ' . $http->FindSingleNode('//p[text() = "Departure Terminal"]/following-sibling::p[1]'));
        $segment['DepCode'] = TRIP_CODE_UNKNOWN;

        $segment['ArrName'] = trim($http->FindSingleNode('//p[text() = "Destination"]/following-sibling::p[1]') . ' ' . $http->FindSingleNode('//p[text() = "Arrival Terminal"]/following-sibling::p[1]'));
        $segment['ArrCode'] = TRIP_CODE_UNKNOWN;

        if (null !== $baseDate) {
            $segment['DepDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('//p[text() = "Departing"]/following-sibling::p[1]'));
            $segment['ArrDate'] = strtotime($baseDate . ' ' . $http->FindSingleNode('//p[text() = "Arriving"]/following-sibling::p[1]'));
            correctDates($segment['DepDate'], $segment['ArrDate']);
        }

        $segment['Cabin'] = $http->FindSingleNode('//p[text() = "Class"]/following-sibling::p[1]');
        $segment['Seats'] = $http->FindSingleNode('//p[text() = "Seat"]/following-sibling::p[1]');

        return $segment;
    }

    public function ParsePdfL(\HttpBrowser $http, $baseDate)
    {
        $it = ['Kind' => 'L'];
        $it['RentalCompany'] = $http->FindSingleNode('//p[text() = "Agency"]/following-sibling::p[1]');

        $it['PickupLocation'] = $it['DropoffLocation'] = $http->FindSingleNode('//p[text() = "Location"]/following-sibling::p[1]');

        $it['Number'] = $http->FindSingleNode('//p[text() = "Confirmation Number"]/following-sibling::p[1]', null, true, '/(\S+)/ims');
        $it['PickupDatetime'] = strtotime(str_ireplace('at', '', $http->FindSingleNode('//p[text() = "Pick Up Date"]/following-sibling::p[1]')));
        $it['DropoffDatetime'] = strtotime(str_ireplace('at', '', $http->FindSingleNode('//p[text() = "Drop Off Date"]/following-sibling::p[1]')));
        $it['CarType'] = trim(
            $http->FindSingleNode('//p[text() = "Car Size"]/following-sibling::p[1]') . ' ' .
            $http->FindSingleNode('//p[text() = "Category"]/following-sibling::p[1]')
        );

        $total = $http->FindSingleNode('//p[text() = "Approximate price including taxes"]/following-sibling::p[1]');

        if (empty($total)) {
            $total = $http->FindSingleNode('//text()[contains(., "Approximate price including taxes")]', null, true, '/Approximate price including taxes -\s*(.+)/ims');
        }

        if (preg_match('/(\S)?(\d+.\d+|\d+)/', $total, $matches)) {
            if (!empty($matches[1])) {
                if ('$' === $matches[1]) {
                    $it['Currency'] = 'USD';
                } else {
                    $it['Currency'] = $matches[1];
                }
            }
            $it['TotalCharge'] = $matches[2];
        }
        $it['AccountNumbers'] = $http->FindSingleNode('//text()[contains(., "Membership -")]', null, true, '/Membership -\s*(\S+)/ims');

        return $it;
    }

    public function ParsePdfR(\HttpBrowser $http, $baseDate)
    {
        $it = ['Kind' => 'R'];
        $xpath = $http->XPath;
        $this->registerFunctions($http->XPath);

        // cleanup
        $nodesToStip = $http->XPath->query('//line[contains(., "ONLINE |")]
            |
            //line[contains(., "ONLINE |")]/following-sibling::line[1]
            |
            //line[
                contains(., "Page") and
                contains(., " of ") and
                php:functionString("preg_match", "/Page (\d+) of (\d+)/ims", .) = 1
            ]');

        foreach ($nodesToStip as $nodeToStip) {
            $nodeToStip->parentNode->removeChild($nodeToStip);
        }

        $it['ConfirmationNumber'] = $http->FindSingleNode('//p[contains(., "Confirmation Number")]/following-sibling::p[1]', null, true, "#^([A-Z\d-]+)#");

        $getByLeft = function ($node) use ($http) {
            if ($node) {
                $left = $node->getAttribute('left');

                if ($left) {
                    return $http->FindNodes("(self::node() | ./following::p[
                        @left = '{$left}' and
                        count(./following::text()[
                            contains(., 'Confirmation Number') or
                            contains(., 'Phone Number')]
                        ) = 2])/text()", $node);
                }
            }

            return [];
        };

        if (count($hotelData = $getByLeft($xpath->query('//p[text() = "Hotel" or text() = "Hotel:"]/following-sibling::p[1]')->item(0))) > 1) {
            $it['HotelName'] = $hotelData[0];
            $address = implode(', ', array_slice($hotelData, 1));
        } elseif ($hotelData) {
            $it['HotelName'] = $hotelData[0];
            $address = $it['HotelName'];
        }

        if (count($hotelData = $getByLeft($xpath->query('//p[text() = "Hotel Address"]/following-sibling::p[1]')->item(0))) > 0) {
            $it['Address'] = implode(', ', $hotelData);
        }
        self::setOnEmpty($it['Address'], $address ?? null);

        $it['CheckInDate'] = strtotime($http->FindSingleNode('//p[text() = "Check in Date" or text() = "Check-In:"]/following-sibling::p[1]/text()'));
        $it['CheckOutDate'] = strtotime($http->FindSingleNode('//p[text() = "Check out Date" or text() = "Check-Out:"]/following-sibling::p[1]/text()'));
        $it['Rate'] = $http->FindSingleNode('//p[text() = "Hotel Rate" or text() = "Hotel Rate:"]/following-sibling::p[1]/text()[1]');
        $it['Phone'] = $http->FindSingleNode('//p[contains(text(), "Phone Number")]/following-sibling::p[1]/text()');
        $it['Fax'] = $http->FindSingleNode('//p[contains(text(), "Fax Number")]/following-sibling::p[1]/text()[1]');

        return $it;
    }

    public function ParsePdfInvoice()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $this->registerFunctions($xpath);

        $tripsByConfNo = [];
        $itineraries = [];
        $itineraryDatesNodes = $xpath->query('//line[p[b[
            contains(text(), "day") and
            contains(text(), "20") and
            php:functionString("preg_match", "/\S+day\s+\d+\s+\S+\s+20\d{2}/ims", text()) = 1
        ]]]');

        foreach ($itineraryDatesNodes as $itineraryDateIdx => $itineraryDateNode) {
            // extract itinerary nodes
            $itineraryNodes = $xpath->query("
            self::node() |
            ./following-sibling::line[
                count(./following-sibling::line[p[b[
                    contains(text(), 'day') and
                    contains(text(), '20') and
                    php:functionString('preg_match', '/\S+day\s+\d+\s+\S+\s+20\d{2}/ims', text()) = 1
                ]]]) = {$itineraryDatesNodes->length} - {$itineraryDateIdx} - 1
                or (
                   {$itineraryDatesNodes->length} = {$itineraryDateIdx} + 1
                   and
                   count(following-sibling::line[p[b[text() = 'Additional Messages']]]) = 1
                )
            ]", $itineraryDateNode);

            if ($itineraryNodes->length === 0) {
                continue;
            }
            $baseDate = CleanXMLValue($itineraryNodes->item(0)->nodeValue);
            // parse nodes
            $itineraryHttp = $this->DOMSubstitution($itineraryNodes);
            $this->registerFunctions($itineraryHttp->XPath);

            // parse nodes depending of itinerary type(flight, rental, hotel)
            if ($itineraryHttp->FindSingleNode('(//p[contains(., "Airline Booking Ref:")])[1]')) {
                // FLIGHTS
                $tripHeadings = $itineraryHttp->XPath->query("//line[
                    contains(., '(') and
                    contains(., ')') and
                    contains(., ' to ') and
                    php:functionString('preg_match', '/^.+?\s*\(\S+\) to .+\s*\(\S+\)/ims', .) = 1
                ]");

                foreach ($tripHeadings as $tripHeadingIdx => $tripHeading) {
                    $tripNodes = $itineraryHttp->XPath->query("
                    self::node() |
                    ./following-sibling::line[
                        count(./following-sibling::line[
                            contains(., '(') and
                            contains(., ')') and
                            contains(., ' to ') and
                            php:functionString('preg_match', '/^.+?\s*\(\S+\) to .+\s*\(\S+\)/ims', .) = 1
                        ]) = {$tripHeadings->length} - {$tripHeadingIdx} - 1
                    ]");

                    if ($tripNodes->length === 0) {
                        continue;
                    }
                    $tripHttp = $this->DOMSubstitution($tripNodes);
                    $segment = $this->ParsePdfInvoiceT($tripHttp, $baseDate);

                    if (!empty($segment['_service']['RecordLocator'])) {
                        $recordLocator = $segment['_service']['RecordLocator'];

                        if (!isset($tripsByConfNo[$recordLocator])) {
                            $tripsByConfNo[$segment['_service']['RecordLocator']] = [
                                'Kind'           => 'T',
                                'RecordLocator'  => $recordLocator,
                                'AccountNumbers' => [],
                            ];
                        }
                        $tripsByConfNo[$recordLocator]['AccountNumbers'][] = $segment['_service']['AccountNumbers'];
                        // sacrifice for the Filters
                        unset($segment['_service']);
                        $tripsByConfNo[$recordLocator]['TripSegments'][] = $segment;
                    }
                }
            }

            // HOTELS
            if ($itineraryHttp->FindSingleNode('(//p[contains(., "Check In Date:")])[1]')) {
                $itineraries[] = $this->ParsePdfInvoiceR($itineraryHttp);
            }

            // CAR
            if ($itineraryHttp->FindSingleNode('(//p[contains(., "Pickup:")])[1]')) {
                $itineraries[] = $this->ParsePdfInvoiceL($itineraryHttp);
            }
        }

        foreach ($tripsByConfNo as $trip) {
            $trip['AccountNumbers'] = implode(', ', array_unique(array_filter($trip['AccountNumbers'], 'strlen')));
            $itineraries[] = $trip;
        }

        return $itineraries;
    }

    /**
     * @example amextravel/it-18.eml
     */
    public function ParseYourFlightDetails()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $this->registerFunctions($xpath);
        $it = ['Kind' => 'T'];
        $it['RecordLocator'] = $http->FindSingleNode('//text()[contains(., "American Express Locator")]/following::text()[normalize-space()][1]');
        $segments = [];
        $segmentsNodes = $xpath->query('//tr[td[contains(., "AIR -")]]
            /following-sibling::tr[1][.//a[contains(@href, "Forecast")]]
            /following-sibling::tr[position() = 1 or position() = 2][td[
                contains(., "From:") and
                contains(., "To:")
            ]]');

        foreach ($segmentsNodes as $segmentNode) {
            $segment = [];

            if (preg_match('/(.+)\s+Flight\s+(\d+)(?:\s+(.+))?/ims', $http->FindSingleNode('./preceding-sibling::tr[not(.//td[contains(., "Operated by")])][1]', $segmentNode), $matches)) {
                if (!empty($matches[3])) {
                    $segment['Cabin'] = $matches[3];
                }
                $segment['FlightNumber'] = $matches[2];
                $segment['AirlineName'] = $matches[1];
            }

            if ($operatedBy = $http->FindSingleNode('./preceding-sibling::tr[1][contains(., "Operated by")]', $segmentNode, true, '/Operated\s+by\s+(.+)/ims')) {
                $segment['AirlineName'] = $operatedBy;
            }
            $segment['Status'] = $http->FindSingleNode('.//td[contains(., "Status:") and not(.//td)]/following-sibling::td[1]', $segmentNode);
            $segment['Duration'] = $http->FindSingleNode('.//td[contains(., "Duration:") and not(.//td)]/following-sibling::td[1]', $segmentNode);
            $segment['Seats'] = implode(', ', array_filter($http->FindNodes('.//td[contains(., "Seats:") and not(.//td)]/following-sibling::td[1]//text()', $segmentNode, '/(\d+\s*\S)\s+/ims'), 'strlen'));
            $segment['Aircraft'] = $http->FindSingleNode('.//td[contains(., "Equipment:") and not(.//td)]/following-sibling::td[1]', $segmentNode);

            foreach ([['Dep', 'From'], ['Arr', 'To']] as $keys) {
                [$Dep, $From] = $keys;
                $s = implode(' ', array_reverse(explode(',', $http->FindSingleNode("(.//td[contains(., '{$From}:')]/following::td)[3]//text()[1]", $segmentNode))));

                if (preg_match('#(\w+\s+\d+)\s+\w+\s+(\d+:\d+\s*(?:am|pm)?)#i', $s, $m)) {
                    $s = $m[1] . ', ' . $this->year . ', ' . $m[2];
                }
                $segment["{$Dep}Date"] = strtotime($s);
                $segment["{$Dep}Name"] = $http->FindSingleNode(".//td[contains(., '{$From}:') and not(.//td)]/following-sibling::td[1]", $segmentNode);
                $segment["{$Dep}Code"] = TRIP_CODE_UNKNOWN;
            }
            $segments[] = $segment;
        }

        if (!empty($segments)) {
            $it['TripSegments'] = $segments;
        }
        $accountNumbers = [];
        $passengerNodes = $xpath->query('//td[contains(., "Passengers")]
            /following-sibling::td[1][contains(., "Reference #")]
            /following-sibling::td[1][contains(., "Frequent Flyer #")]
            /ancestor::tr[1]
            /following-sibling::tr');

        foreach ($passengerNodes as $passengerNode) {
            if ($passenger = $http->FindSingleNode('./td[1]', $passengerNode)) {
                $it['Passengers'][] = $passenger;
            }

            if ($accountNumber = $http->FindSingleNode('./td[3]', $passengerNode)) {
                $accountNumbers[] = $accountNumber;
            }
        }

        if ($accountNumbers) {
            $it['AccountNumbers'] = implode(', ', $accountNumbers);
        }

        return $it;
    }

    public function ParsePdfTravelReservation()
    {
        $http = $this->http;
        $xpath = $http->XPath;
        $tripHeadings = $xpath->query('//');
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->reProvider, $from);
    }

    public function detectEmailByHeaders(array $headers)
    {
        return (isset($this->reFrom) && isset($headers['from'])) && preg_match($this->reFrom, $headers["from"]);
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $plainBody = $parser->getPlainBody();
        $isHtmlDetect = ((isset($this->reText) && $this->reText) ? preg_match($this->reText, $plainBody) : false)
            || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $this->http->Response['body']) : false)
            || ((isset($this->reHtml) && $this->reHtml) ? preg_match($this->reHtml, $plainBody) : false);

        if ($isHtmlDetect) {
            return true;
        }

        // empty body: it-1631146.eml + bcdtravel
        $pdf = $parser->searchAttachmentByName('(Itinerary|INV).+?_[A-Z\d]{5,6}\.pdf');

        foreach ($pdf as $value) {
            $text = \PDF::convertToText($parser->getAttachmentBody($value));

            if (stripos($text, 'American Express') !== false && (
                    stripos($text, 'Invoice Booking Reference') !== false
                    || stripos($text, 'Travel Arrangements for') !== false
                    )) {
                return true;
            }
        }
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $itineraries = [];
        $this->year = re('#\d{4}#i', $parser->getHeader('date'));
        $emailType = $this->getEmailType($parser);

        switch ($emailType) {
            case "YourReservationConfirmation":
                $itineraries = $this->ParseYourReservationConfirmation();

            break;

            case "YourFlightDetails":
                $itineraries = $this->ParseYourFlightDetails();

            break;

            case "Pdf":
//            case "PdfInvoice":
//            case "PdfTravelReservation":
                if ($pdfs = $parser->searchAttachmentByName("(MyTravelPlans|Travel\s+Reservation|Airmail|Itinerary_.+?|INV[^'\"]+)\.pdf")) {
                    $converted = \PDF::convertToHtml($parser->getAttachmentBody($pdfs[0]), \PDF::MODE_COMPLEX);

                    if (!empty($converted)) {
                        $this->http->SetBody($converted);
                        \PDF::sortNodes($this->http, 3, true);
                        $method = "Parse" . $emailType;
                        $itineraries = $this->$method();
                    }
                }

            break;

            default:
                break;

                if (is_callable($emailType)) {
                    $emailType($itineraries, $parser);
                    $emailType = 'reservations';
                } else {
                    $itineraries = [];
                }

            break;
        }

        $result = [
            'emailType'  => $emailType,
            'parsedData' => [
                'Itineraries' => isset($itineraries[0]) ? $itineraries : [$itineraries],
            ],
        ];

        return $result;
    }

    public function getEmailType(\PlancakeEmailParser $parser)
    {
        /*        if($this->http->FindPreg('/Your Reservation Confirmation for Trip ID/ims') ||
                        $this->http->XPath->query('//*[contains(text(), "Your Seats:")]/ancestor::table[2][.//*[contains(text(), "Operated by")]]')->length > 0){
                    return "YourReservationConfirmation";
                }
        */
//        if($this->http->FindSingleNode('//text()[contains(., "IMPORTANT TRAVEL UPDATE FROM AMERICAN EXPRES")]')){
//            return "YourFlightDetails";
//        }
//		if($parser->searchAttachmentByName("(MyTravelPlans|Airmail)\.pdf")){
        if ($parser->searchAttachmentByName("(Airmail)\.pdf")) {//MyTravelPlans.*pdf should to parse by PDF.php
            return "Pdf";
        }
//        if($parser->searchAttachmentByName("(Itinerary_.+?|INV[^'\"]+)\.pdf")){
//            return "PdfInvoice";
//        }
        if ($parser->searchAttachmentByName("Travel\s*Reservation[^'\"]+\.pdf")) {
            return "PdfTravelReservation";
        }
        $htmlBody = $parser->getHTMLBody();

        foreach ($this->processors as $re => $processor) {
            if (preg_match($re, $htmlBody)) {
                return $processor;
            }
        }

        return "Undefined";
    }

    public static function getEmailTypesCount()
    {
        return 7;
    }

    protected function parseMoney(&$totals, $fieldName, $value, $currencyName = 'Currency')
    {
        if (preg_match('/(\S)?\s*(\d+.\d+|\d+)/ims', $value, $matches)) {
            if (!empty($matches[1]) && empty($totals['Currency'])) {
                if ('$' === $matches[1]) {
                    $totals['Currency'] = 'USD';
                } else {
                    $totals['Currency'] = $matches[1];
                }
            }
            $totals[$fieldName] = $matches[2];
        }
    }

    private function ParsePdfInvoiceT(\HttpBrowser $http)
    {
        $segment['_service']['RecordLocator'] = $http->FindSingleNode('//p[. = "Airline Booking Ref:"]/following-sibling::p[1]');

        if ($segment['_service']['RecordLocator'] === 'Not Applicable') {
            $segment['_service']['RecordLocator'] = $this->http->FindSingleNode('//p[. = "Invoice Booking Reference"]/following-sibling::p[1]');
        }
        $segment['_service']['AccountNumbers'] = $http->FindSingleNode('//p[. = "Frequent Flyer Number:"]/following-sibling::p[1]');

        $air = $http->FindSingleNode('//p[. = "Flight:"]/following-sibling::p[1]');

        if (preg_match('/([A-Z]{1,2})\s*(\d+)/', $air, $matches)) {
            $segment['AirlineName'] = $matches[1];
            $segment['FlightNumber'] = $matches[2];
        }

        $segment['Duration'] = $http->FindSingleNode('//p[. = "Estimated Time:"]/following-sibling::p[1]');
        $segment['TraveledMiles'] = $http->FindSingleNode('//p[. = "Distance:"]/following-sibling::p[1]', null, true, '/[\d\.,]+/');
        $segment['Aircraft'] = $http->FindSingleNode('//p[. = "Aircraft Type:"]/following-sibling::p[1]');
        $segment['Meal'] = $http->FindSingleNode('//p[. = "Meal Service:"]/following-sibling::p[1]');

        if (preg_match('/(.+)\s+?\((\S+)\)/ims', $http->FindSingleNode('//p[. = "Origin:"]/following-sibling::p[1]'), $matches)) {
            $segment['DepName'] = $matches[1];
            $segment['DepCode'] = $matches[2];
        }

        if (preg_match('/(.+)\s+?\((\S+)\)/ims', $http->FindSingleNode('//p[. = "Destination:"]/following-sibling::p[1]'), $matches)) {
            $segment['ArrName'] = $matches[1];
            $segment['ArrCode'] = $matches[2];
        }
        $segment['DepDate'] = strtotime(str_ireplace(' at ', ' ', $http->FindSingleNode('//p[. = "Departing:"]/following-sibling::p[1]')));
        $segment['ArrDate'] = strtotime(str_ireplace(' at ', ' ', $http->FindSingleNode('//p[. = "Arriving:"]/following-sibling::p[1]')));

        $segment['Cabin'] = $http->FindSingleNode('//p[. = "Class:"]/following-sibling::p[1]');
        $segment['Seats'] = $http->FindSingleNode('//p[. = "Seat:"]/following-sibling::p[1]');
        $segment['Stops'] = $http->FindSingleNode('//p[. = "Number of Stops:"]/following-sibling::p[1]');

        return $segment;
    }

    private function ParsePdfInvoiceR(\HttpBrowser $http)
    {
        $it = ['Kind' => 'R'];
        $it['AccountNumbers'] = $http->FindSingleNode('//p[. = "Membership ID:"]/following-sibling::p[1][not(contains(text(), "Not Applicable"))]');

        if (preg_match('/Number Of Nights:.*?Reference Number:\s*(\w+)Status.*?Number Of Rooms:/', $http->DOM->textContent, $matches)) {
            $it['ConfirmationNumber'] = trim($matches[1]);
        }

        if (preg_match('/(?:Not Applicable|\d+)\s*([A-Z\s]+)Address:/', $http->DOM->textContent, $matches)) {
            $it['HotelName'] = trim($matches[1]);
        }

        $it['Address'] = implode(', ', $http->FindNodes('//p[. = "Address:"]/following-sibling::p[1]'));

        $it['CheckInDate'] = strtotime($http->FindSingleNode('//p[. = "Check In Date:"]/following-sibling::p[1]'));
        $it['CheckOutDate'] = strtotime($http->FindSingleNode('//p[. = "Check Out Date:"]/following-sibling::p[1]'));
        $it['Rate'] = $http->FindSingleNode('//p[. = "Rate:"]/following-sibling::p[1]');
        $it['Rooms'] = $http->FindSingleNode('//p[. = "Number Of Rooms:"]/following-sibling::p[1]');
        $it['Status'] = $http->FindSingleNode('//p[. = "Status:"]/following-sibling::p[1]');
        $it['Phone'] = $http->FindSingleNode('//p[. = "Phone:"]/following-sibling::p[1]');
        $it['Fax'] = $http->FindSingleNode('//p[. = "Fax:"]/following-sibling::p[1]');
        $it['CancellationPolicy'] = $http->FindSingleNode('//p[contains(text(), "CANCEL PERMITTED")]');

        return $it;
    }

    private function ParsePdfInvoiceL(\HttpBrowser $http)
    {
        $it = ['Kind' => 'L'];

        if (preg_match('/Reference Number:\s*(\w+)(?:Status|\s+)/s', $http->DOM->textContent, $matches)) {
            $it['Number'] = trim($matches[1]);
        }

        // Monday 06 March 2017 at 8:18AM
        if (preg_match('#Pick\s*up:\s*Location:\s*(.+?)(?:Pick Up Date/Time|Date and Time):(\w+ \d+ \w+ \d+ at \d+:\d+\s*(?:[AP]M)?)#i', $http->DOM->textContent, $matches)) {
            $it['PickupLocation'] = trim($matches[1]);
            $it['PickupDatetime'] = strtotime(str_replace(' at ', ' ', $matches[2]));
        }

        if (preg_match('#Drop\s*Off:\s*Location:\s*(.+?)(?:Drop Off Date/Time|Date and Time):(\w+ \d+ \w+ \d+ at \d+:\d+\s*(?:[AP]M)?)#i', $http->DOM->textContent, $matches)) {
            $it['DropoffLocation'] = trim($matches[1]);
            $it['DropoffDatetime'] = strtotime(str_replace(' at ', ', ', $matches[2]));
        }

        //Drop Off Date/Time: Sunday 04 May 2014 at 12:00PM Phone: 217 422-4337
        if (preg_match('#Drop Off.+?Phone:\s*([+\s\d-]+)#i', $http->DOM->textContent, $matches)) {
            $it['DropoffPhone'] = trim($matches[1]);
        }

        if (preg_match('/Car Type:\s*(.+?)Rate:.*?Approximate Total Rate:\s*([A-Z]{3})\s*([\d.]+)/', $http->DOM->textContent, $matches)) {
            $it['CarType'] = $matches[1];
            $it['Currency'] = $matches[2];
            $it['TotalCharge'] = (float) $matches[3];
        }

        return $it;
    }

    private function DOMSubstitution(\DOMNodeList $nodes)
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

    private function registerFunctions(\DOMXPath $xpath)
    {
        $xpath->registerNamespace('php', 'http://php.net/xpath');
        $xpath->registerPhpFunctions('CleanXMLValue', 'preg_match', 'strlen', 'strcasecmp');
    }

    private static function setOnEmpty(&$var, $value)
    {
        if ((!isset($var) || '' === $var) && isset($value) && '' !== $value) {
            $var = $value;
        }
    }
}
