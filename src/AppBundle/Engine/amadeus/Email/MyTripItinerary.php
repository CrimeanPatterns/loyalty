<?php

namespace AwardWallet\Engine\amadeus\Email;

// parsers with similar formats: amadeus/It1824289(array), amadeus/It1977890(array), hoggrob/It6083284(array)

class MyTripItinerary extends \TAccountCheckerExtended
{
    public $reFrom = "#webmaster@amadeus\.net#i";
    public $reProvider = "#amadeus\.net#i";
    public $rePlain = "#Trip\s+Confirmation\s+Trip\s+status#is";
    public $rePlainRange = "";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1749072.eml";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->fullText = $text;

                    $reservations = [];

                    // Air trip reservations
                    $xpath = '//tr[.//td[normalize-space(.) = "Economy Restricted" or normalize-space(.) = "Economy"] and not(.//tr) and count(.//td) = 5]';

                    foreach ($this->http->XPath->query($xpath) as $n) {
                        $reservations[] = $n;
                    }

                    // Hotel reservations
                    $xpath = '//text()[contains(., "Room Type")]/ancestor::tr[3]';

                    foreach ($this->http->XPath->query($xpath) as $n) {
                        $reservations[] = $n;
                    }

                    return $reservations;
                },

                "#Hotel\s*:#i" => [
                    "ConfirmationNumber" => function ($text = '', $node = null, $it = null) {
                        return re('#Confirmation\s+Number:\s+([\w\-]+)#i');
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "R";
                    },

                    "HotelName" => function ($text = '', $node = null, $it = null) {
                        $xpath = './/td[contains(., "Hotel") and contains(., ":")]/following-sibling::td[1]//text()';
                        $subj = implode("\n", nodes($xpath));
                        $regex = '#(?:^|\n)\s*(.*)\s+((?s).*)\s+Tel:\s+(.*)\s+Fax:\s+(.*)#';

                        if (preg_match($regex, $subj, $m)) {
                            return [
                                'HotelName' => $m[1],
                                'Address'   => nice($m[2], ','),
                                'Phone'     => $m[3],
                                'Fax'       => $m[4],
                            ];
                        }
                    },

                    "CheckInDate" => function ($text = '', $node = null, $it = null) {
                        $res = null;

                        foreach (['Check-In', 'Check-in'] as $value) {
                            $subj = cell($value);
                            $res['CheckInDate'] = strtotime(re('#\w+\s+\d+,\s+\d+#', $subj));

                            if (!empty($res['CheckInDate'])) {
                                break;
                            }
                        }

                        foreach (['Check-Out'] as $value) {
                            $subj = cell($value);
                            $res['CheckOutDate'] = strtotime(re('#\w+\s+\d+,\s+\d+#', $subj));
                        }

                        return $res;
                    },

                    "CancellationPolicy" => function ($text = '', $node = null, $it = null) {
                        return cell('Cancellation Policy', +1);
                    },

                    "RoomType" => function ($text = '', $node = null, $it = null) {
                        return cell('Room Type', +1);
                    },

                    "Total" => function ($text = '', $node = null, $it = null) {
                        $regex = '#([\d.,]+\s+\w{3})\s+\(.*\)\s+(Confirmed)#';

                        if (preg_match($regex, $node->nodeValue, $m)) {
                            return [
                                'Total'    => cost($m[1]),
                                'Currency' => currency($m[1]),
                                'Status'   => $m[2],
                            ];
                        }
                    },
                ],

                "#Economy\s+Restricted|Economy#i" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $xpath = '//ancestor::*//img[contains(@src, "IcoFlight")]/ancestor::td[1]/following-sibling::td[contains(., "Number")]';
                        $subj = re('#:\s*(.*)#', node($xpath));

                        if (!$subj) {
                            $subj = re('#Testernulldreiat\s+Tester\s+\((\w+)\)#', $this->fullText);
                        }

                        return $subj;
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            if (preg_match('#(.*)\s+(\d+)(?:\s+Operated\s+by\s+(.*))?#i', node('./td[3]'), $m)) {
                                return [
                                    'AirlineName'  => (isset($m[3])) ? $m[3] : $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = null;

                            foreach (['Dep' => 1, 'Arr' => 2] as $key => $value) {
                                $subj = node('./td[' . $value . ']');
                                $regex = '#';
                                $regex .= '(?P<Name1>.*)\s*\((?P<Code>\w+)\),\s*(?P<Name2>.*?)\s*';
                                $regex .= '(?P<Time>\d+:\d+)\s+.*?';
                                $regex .= '\w+,\s+(?P<Date>\w+\s+\d+,\s+\d+)';
                                $regex .= '#i';

                                if (preg_match($regex, $subj, $m)) {
                                    $res[$key . 'Name'] = $m['Name1'] . ', ' . $m['Name2'];
                                    $res[$key . 'Code'] = $m['Code'];
                                    $res[$key . 'Date'] = strtotime($m['Date'] . ', ' . $m['Time']);
                                }
                            }

                            return $res;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return node('./td[4]');
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            $subj = node('./td[5]');

                            if ($subj) {
                                return [
                                    'Duration' => re('#\d+h\s*\d+m#', $subj),
                                    'Stops'    => (stripos($subj, 'Direct') !== false) ? 0 : null,
                                ];
                            }
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1 && isset($itNew[0]['Kind']) && $itNew[0]['Kind'] !== 'R') {
                        $subj = re('#([\d\.,]+\s+\w{3})(?:\s+\d+.*flight\s+ticket\(s\))?\s+Confirmed#', $this->fullText);
                        $itNew[0]['TotalCharge'] = cost($subj);
                        $itNew[0]['Currency'] = currency($subj);
                        $itNew[0]['Status'] = re('#Trip\s+status:\s+(.*)#i', $this->fullText);
                    }

                    return $itNew;
                },
            ],
        ];
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
        return true;
    }
}
