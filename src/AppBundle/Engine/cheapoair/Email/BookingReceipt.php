<?php

namespace AwardWallet\Engine\cheapoair\Email;

class BookingReceipt extends \TAccountCheckerExtended
{
    public $reBody = 'CheapOair.com';
    public $reBody2 = 'CheapOair.ca';
    public $reBody3 = 'Booking receipt';
    public $reBody4 = 'Booking Receipt';

    public $mailFiles = "cheapoair/it-0.eml, cheapoair/it-1589819.eml, cheapoair/it-1603954.eml, cheapoair/it-1621628.eml, cheapoair/it-1642044.eml, cheapoair/it-1646742.eml, cheapoair/it-1745971.eml, cheapoair/it-1771547.eml, cheapoair/it-1827816.eml, cheapoair/it-1903629.eml, cheapoair/it-1903633.eml, cheapoair/it-1919042.eml, cheapoair/it-1973253.eml, cheapoair/it-2305292.eml, cheapoair/it-3023470.eml, cheapoair/it-3023472.eml, cheapoair/it-3210085.eml";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->reservationDate = strtotime(re('#Booked\s+on:\s+\w+,\s+(\w+\s+\d+,\s+\d+)#'));

                    $this->fullText = $text;

                    $this->total = cell('Total Cost', +1);

                    $reservations = [];
                    // Air trips
                    $xpaths['Air'] = '//tr[contains(., "From") and contains(., "To") and not(.//tr) and not(contains(., "To:"))]';
                    // Car rentals
                    $xpaths['Car'] = "//text()[contains(., 'Car Summary')]/ancestor::tr[1]/following-sibling::tr";
                    // Hotel reservations
                    $xpaths['Hotel'] = '//tr[contains(., "Hotel Booking Details") and not(.//tr)]/ancestor::table[1]/following-sibling::table[2]';

                    foreach ($xpaths as $type => $xpath) {
                        $nodes = $this->http->XPath->query($xpath);

                        if ($type == 'Air') {
                            $this->airReservationsCount = $nodes->length;
                        }

                        for ($i = 0; $i < $nodes->length; $i++) {
                            $reservations[] = $nodes->item($i);
                        }
                    }

                    return $reservations;
                },

                ".//text()[contains(., 'Check-in')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        $subj = node('./following-sibling::table[1]');

                        return [
                            'ConfirmationNumber' => re('#Confirmation\s+Number:\s+([\w\-]+)#i', $subj),
                            'GuestNames'         => [re('#Guest\s+Name:\s+(.*?)\s+Room\s+\d+#i', $subj)],
                            'Guests'             => re('#(\d+)\s+Adult#i', $subj),
                            'RoomType'           => re('#Room\s*\d+\s*\(.*\):\s+(.*?)\s+Room\s+\d+\s+Conf#i', $subj),
                        ];
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $subj = nodes('//img[contains(@src, "/stars/") and ./ancestor::table[2]/preceding-sibling::table[1 and contains(., "Hotel Booking Details")]]/../b');

                        if (count($subj) == 2) {
                            return [
                                'HotelName' => $subj[0],
                                'Address'   => nice($subj[1]),
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['CheckIn' => 'Check-in', 'CheckOut' => 'Check-out'] as $key => $value) {
                            $res[$key . 'Date'] = strtotime(re('#' . $value . ':\s+\w+,\s+(\w+\s+\d+,\s+\d+)#i'));
                        }

                        return $res;
                    },

                    "Rooms" => function ($text = '', $node = null, $it = null) {
                        return re('#Number\s+of\s+Rooms:\s+(\d+)#i');
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return re('#Cancellations\s+or\s+changes\s+made\s+after\s+.*?\.#i', $this->fullText);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return total($this->total, 'Total');
                    },
                ],

                ".//text()[contains(., 'Car Confirmation')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "L";
                    },

                    "Number" => function ($text = '', $node = null, $it = null) {
                        return re('#Car\s+Confirmation:\s+([\w\-]+)#');
                    },

                    "PickupLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Pick-up\s+Location:\s+(.*)#');
                    },

                    "PickupDatetime" => function ($text = '', $node = null, $it = null) {
                        $subj = str_replace([',', '/'], ['', ','], re('#Pick-up\s+Date/Time:\s+\w+,\s+(.*)#'));

                        return strtotime($subj);
                    },

                    "DropoffLocation" => function ($text = '', $node = null, $it = null) {
                        return re('#Drop-off\s+Location:\s+(.*)#');
                    },

                    "DropoffDatetime" => function ($text = '', $node = null, $it = null) {
                        $subj = str_replace([',', '/'], ['', ','], re('#Drop-off\s+Date/Time:\s+\w+,\s+(.*)#'));

                        return strtotime($subj);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reservationDate;
                    },
                ],

                "PostProcessing"       => function ($text = '', $node = null, $it = null) {
                    foreach ($it as $k => $item) {
                        if (count($item) == 0) {
                            unset($it[$k]);
                        }
                    }

                    //					sort($it);
                    $it = uniteAirSegments($it);

                    if (count($it) > 1) {
                        for ($i = 0; $i < count($it); $i++) {
                            unset($it[$i]['TotalCharge'], $it[$i]['Currency']);
                        }
                    }

                    return $it;
                },

                ".//text()[contains(., 'Flight') or contains(., 'Airline')]" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $subj = re('#Airline\s+confirmation:\s+([\w\-]+)#');

                        if (!$subj) {
                            $subj = re('#Booking\s+Number:\s+([\w\-]+)#');
                        }

                        if (!$subj) {
                            $subj = CONFNO_UNKNOWN;
                        }

                        return $subj;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Traveler Name')]/ancestor::tr[1]/following-sibling::tr[position() < last()]/td[2]/b";

                        return nodes($xpath);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $xpath = "//text()[contains(., 'Total Cost:')]/ancestor::td[1]/following-sibling::td[1]";
                        $subj = node($xpath);

                        return ['TotalCharge' => cost($subj), 'Currency' => currency($subj)];
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return $this->reservationDate;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $subj = node('./td[1]');
                            $regex = '#';
                            $regex .= '(?P<AirlineName>.*)\s*';
                            $regex .= 'Flight\s+(?P<FlightNumber>\d+)\s*';
                            $regex .= '(?P<Aircraft>.*?)\s*(?:Seat(?:|s|\(s\)):\s+(?P<Seats>\d+\w+|Pending).*)?\s*(?:Airline\s+confirmation:\s+[\w\-]+\s*)?(?:Select\s+Seats)?';
                            $regex .= '$#';

                            if (preg_match($regex, $subj, $m)) {
                                if (isset($m['Seats']) and strtolower($m['Seats']) == 'pending') {
                                    $m['Seats'] = null;
                                }
                                copyArrayValues($res, $m, ['AirlineName', 'FlightNumber', 'Aircraft', 'Seats']);
                            }

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];
                            $year = re('#,\s*(\d{4})#', node("./preceding-sibling::tr[contains(., 'Flight -') or contains(., 'Departing Flight') or contains(., 'Return Flight')][1]"));

                            foreach (['Dep' => 'From', 'Arr' => 'To'] as $key => $value) {
                                $subj = node('./td[.//text()[normalize-space(.) = "' . $value . '"] and not(.//td)]');
                                $regex = '#';
                                $regex .= $value . '\s*(?P<' . $key . 'Name>.*?)\s+';
                                $regex .= '\((?P<' . $key . 'Code>\w+)\)\s*';
                                $regex .= '(?P<Time>\d+:\d+(?:am|pm|))\s*-\s*(?P<Date>[^\n]+)';
                                $regex .= '#';

                                if (preg_match($regex, $subj, $m)) {
                                    $m[$key . 'Date'] = strtotime(re("#\w+\s+\w+#", $m['Date']) . ' ' . $year . ', ' . $m['Time']);
                                    copyArrayValues($res, $m, [$key . 'Name', $key . 'Code', $key . 'Date']);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Coach|Econ#', node('./td[4]'));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Flight\s+Duration\s*(.*)#', node('./td[4]'));

                            if (!$subj and $this->airReservationsCount == 1) {
                                $subj = node('./following-sibling::tr[string-length(normalize-space(.)) > 1][1]');
                                $subj = re('#Duration:\s+(\d+hr\s+\d+min)#', $subj);
                            }

                            return $subj;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Nonstop#', node('./td[4]'));

                            return ($subj == 'Nonstop') ? (int) 0 : null;
                        },
                    ],
                ],
            ],
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return (
            strpos($body, $this->reBody) !== false
            || strpos($body, $this->reBody2) !== false
        )
        && (
            strpos($body, $this->reBody3) !== false
            || strpos($body, $this->reBody4) !== false
        );
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
