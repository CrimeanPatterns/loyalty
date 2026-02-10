<?php

namespace AwardWallet\Engine\delta\Email;

class YourDeltaAirLinesConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?delta#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Your\s+Delta\s+Air\s+Lines\s+Confirmation|Delta\.com\s+Itinerary\s+from#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#(?:DeltaAirLinesConfirmation|do-not-reply)@delta\.com#i";
    public $reProvider = "#delta\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 13:48";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "delta/it-1.eml, delta/it-2.eml, delta/it-2143498.eml, delta/it-2310280.eml, delta/it-3.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return orval(
                            re('#Itinerary\s+Reference\s*\#\s+-\s+([\w\-]+)#i'),
                            re('#CONFIRMATION\s+\#\s*:\s+([\w\-]+)#i')
                        );
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $s = re('#Passenger\s+Info(?:rmation)?\s+(.*?)\s+(?:Mileage\s+and\s+Fees|Mailing\s+Address|Save\s+money\s+when\s+you\s+book)#is') . "\n\n";
                        $s = preg_replace('#\s*SkyMiles\s+Number:.*?\s*\n#i', "\n", $s);
                        $r = '#(.*)\s*\n\s*Seat\(s\):\s+((?s).*?)\n\s*\n#i';
                        $this->seats = [];

                        if (preg_match_all($r, $s, $m)) {
                            for ($i = 0; $i < count($m[2]); $i++) {
                                if (preg_match_all('#\s*.*\s+(\d+)\s+-\s+(.*)#i', $m[2][$i], $mm, PREG_SET_ORDER)) {
                                    foreach ($mm as $m3) {
                                        $this->seats[$m[1][$i]][$m3[1]][] = $m3[2];
                                    }
                                }
                            }

                            return $m[1];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re('#Total\s+Cost:\s+(.*)#'));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Taxes\s+and\s+Fees:\s+(.*)#'));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Mileage\s+required:\s+([\d,]+\s+miles)#i');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        if (preg_match_all('#\w+\s+\d+\w+\s+.*?\d{1,2}:?\d{2}[ap]m?\s*\n#is', $text, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            static $flightNumberCounter = []; // Some reservations have flights with same number
                            $year = re('#\d{4}#i', $this->parser->getHeader('Date'));

                            if (!$year) {
                                return null;
                            }
                            $r = '#';
                            $r .= '\w+\s+(?P<DepDay>\d+)(?P<DepMonth>\w+)\s+';
                            $r .= '(?P<AirlineName>.*?)\s+(?P<FlightNumber>\d+)\*?\s+';
                            $r .= '(?:(?P<BookingClass>[A-Z])\s+)?';
                            $r .= 'LV\s+(?P<DepName>.*?)\s+\((?P<DepCode>\w{3})\)\s+';
                            $r .= '(?P<DepTime>\d+:?\d+[ap]m?)\s+';
                            $r .= '(?P<Cabin>.*?)\s*(?P<DayShift>\#)?\s*';
                            $r .= '\n\s*(?:\w+\s+(?P<ArrDay>\d+)(?P<ArrMonth>\w+)\s+)?';
                            $r .= 'AR\s+(?P<ArrName>.*?)\s+\((?P<ArrCode>\w{3})\)\s+';
                            $r .= '(?P<ArrTime>\d+:?\d+[ap]m?)';
                            $r .= '#i';
                            $res = null;

                            if (preg_match($r, $text, $m)) {
                                $keys = ['AirlineName', 'FlightNumber', 'BookingClass', 'Cabin', 'DepName', 'ArrName', 'DepCode', 'ArrCode'];

                                foreach ($keys as $k) {
                                    $res[$k] = $m[$k] ? $m[$k] : null;
                                }

                                if (!$m['ArrDay']) {
                                    foreach (['Day', 'Month'] as $k) {
                                        $m['Arr' . $k] = $m['Dep' . $k];
                                    }
                                }

                                foreach (['Dep', 'Arr'] as $k) {
                                    $res[$k . 'Name'] = trim($res[$k . 'Name'], ',');

                                    if (preg_match('#^(\d{1,2})(\d{2})([AP])$#', $m[$k . 'Time'], $mm)) {
                                        $m[$k . 'Time'] = $mm[1] . ':' . $mm[2] . ' ' . $mm[3] . 'M';
                                    }
                                    $res[$k . 'Date'] = strtotime($m[$k . 'Day'] . ' ' . $m[$k . 'Month'] . ' ' . $year . ', ' . $m[$k . 'Time']);
                                }

                                if ($m['DayShift'] and isset($res['ArrDate']) and $res['ArrDate']) {
                                    $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                                }
                                $fn = $res['FlightNumber'];
                                $seats = null;

                                if (!isset($flightNumberCounter[$fn])) {
                                    $flightNumberCounter[$fn] = 0;
                                }

                                foreach ($this->seats as $passSeats) {
                                    if (isset($passSeats[$fn])) {
                                        $s = $passSeats[$fn][$flightNumberCounter[$fn]];

                                        if (strtolower($s) != 'not assigned') {
                                            $seats[] = $s;
                                        }
                                    }
                                }
                                $flightNumberCounter[$fn]++;
                                $res['Seats'] = $seats;
                            }

                            return $res;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    if (isset($it[0]['TripSegments'])) {
                        $yearFix = false;
                        $lastDate = null;

                        foreach ($it[0]['TripSegments'] as &$ts) {
                            if (!isset($ts['DepDate']) or !isset($ts['ArrDate'])
                                    or !$ts['DepDate'] or !$ts['ArrDate']) {
                                continue;
                            }

                            foreach (['Dep', 'Arr'] as $k) {
                                if ($lastDate === null) {
                                    $lastDate = $ts[$k . 'Date'];
                                }

                                if ($ts[$k . 'Date'] < $lastDate - 60 * 60 * 24 * 30) {
                                    // If last dep/arr date is one month more than current - than add one year
                                    $ts[$k . 'Date'] = strtotime('+1 year', $ts[$k . 'Date']);
                                }
                            }
                        }
                    }

                    return $it;
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
        return false;
    }
}
