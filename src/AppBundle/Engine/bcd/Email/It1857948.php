<?php

namespace AwardWallet\Engine\bcd\Email;

use PlancakeEmailParser;

class It1857948 extends \TAccountCheckerExtended
{
    public $mailFiles = "bcd/it-1857948.eml";

    private $from = '/[@\.]bcd(?:travel)?\.com/i';

    private $date;

    private $provs = [
        'JTB',
        'bcd',
    ];

    private $detects = [
        'Thank you for making your travel reservations through our site', // jtb
        'THIS RESERVATION HAS BEEN', // bcd
    ];

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument("plain", "text");
                    $this->date = strtotime(re("#Record Creation Time: (\d{4}\-\d+\-\d+)#", $text));

                    if (empty($this->date)) {
                        $this->date = $this->parser->getDate();
                    }

                    return splitter("#\n\s*((?:AIR|HOTEL|CAR)\n)#");
                },

                "#^HOTEL#" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        return re("#^HOTEL\s+(.*?)\s+Location:\s*[^\n]+#");
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Check\-in\s*:\s*([^\n]*?)\s+Check\-out#"), $this->date);
                    },

                    "CheckOutDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+Check\-out\s*:\s*([^\n]+)#"), $this->date);
                    },

                    "Address" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Location:\s*([^\n]+)#");
                    },

                    "GuestNames" => function ($text = '', $node = null, $it = null) {
                        return explode("\n", trim(re("#\n\s*Name\(s\)\s+of\s+people\s+Traveling\s*:\s*(.*?)\s+\*+#", $this->text()), "\n "));
                    },

                    "Rate" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Average Rate\s*:\s*([^\n]+)#");
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Price\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Average Rate\s*:\s*([^\n]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#THIS RESERVATION HAS BEEN (\w+)#", $this->text());
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#THIS\s+RESERVATION\s+HAS\s+BEEN\s+CANCELLED#i", $this->text()) ? true : false;
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Record Creation Time\s*:\s*([^\n]+)#", $this->text())));
                    },
                ],

                "#^AIR#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $air = re("#\n\s*Flight/Equip\.\s*:\s*(.*?)\s+\d+\n#");
                        $recLoc = re("#Airline Record Locator\s+\#\d+\s+[A-Z\d]{2}\-([A-Z\d\-]+)\s+\($air#", $this->text());

                        if (empty($recLoc)) {
                            $recLoc = re('/Apollo Record Locator\s+\#\:\s+([A-Z\d\-]+)/', $this->text());
                        }

                        return $recLoc;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $pax = explode("\n", trim(re("#\n\s*Name\(s\)\s+of\s+people\s+Traveling\s*:\s*(.*?)\s+\*+#", $this->text()), "\n "));
                        $pax = array_filter($pax);

                        if (empty($pax)) {
                            $pax = $this->http->FindNodes("(//td[normalize-space(.)='Name:' and not(.//td)]/following-sibling::td[normalize-space(.)][1])[1]");
                        }

                        return $pax;
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#THIS\s+RESERVATION\s+HAS\s+BEEN\s+CANCELLED#i", $this->text()) ? true : false;
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*(?:Price|Total Flight \(per person\) excluding Air Extras)\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*(?:Price|Total Flight \(per person\) excluding Air Extras)\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#THIS RESERVATION HAS BEEN (\w+)#", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Record Creation Time\s*:\s*([^\n]+)#", $this->text())));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*Flight/Equip\.\s*:\s*(.*?)\s+(\d+)\n#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return preg_replace('/\w+, \w+ \d{1,2} \d{1,2}:\d{2}/', '', re("#\n\s*Depart\s*:\s*([^\n\(]+)#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*Depart\s*:\s*([^\n]+)#");

                            if (preg_match('/(\w+) (\d{1,2}) (\d{1,2}:\d{2}(?:\s*[ap]m\b)?)/i', $date, $m)) {
                                return strtotime($m[2] . ' ' . $m[1] . ' ' . date('Y', $this->date) . ', ' . $m[3]);
                            } else {
                                return totime(uberDateTime(re("#\n\s*Depart\s*:\s*([^\n]+)#")), $this->date);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return preg_replace('/\w+, \w+ \d{1,2} \d{1,2}:\d{2}/', '', re("#\n\s*Arrive\s*:\s*([^\n\(]+)#"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#\n\s*Arrive\s*:\s*([^\n]+)#");

                            if (preg_match('/(\w+) (\d{1,2}) (\d{1,2}:\d{2})/', $date, $m)) {
                                return strtotime($m[2] . ' ' . $m[1] . ' ' . date('Y', $this->date) . ', ' . $m[3]);
                            } else {
                                return totime(uberDateTime(re("#\n\s*Arrive\s*:\s*([^\n]+)#")), $this->date);
                            }
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('/\n\s*Class\:\s+(\w+)/');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
                },
            ],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match($this->from, $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return preg_match($this->from, $headers['from']) > 0;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getPlainBody() . $parser->getHTMLBody();
        $anchor = false;

        foreach ($this->provs as $prov) {
            if (false !== stripos($body, $prov)) {
                $anchor = true;
            }
        }

        foreach ($this->detects as $detect) {
            if (false !== stripos($body, $detect) && $anchor) {
                return true;
            }
        }

        return false;
    }

    public function IsEmailAggregator()
    {
        return true;
    }
}
