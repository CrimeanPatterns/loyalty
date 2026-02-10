<?php

namespace AwardWallet\Engine\delta\Email;

class DLItinerary extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?delta#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#DL\s+Itinerary#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#DeltaItinerary@delta\.com#i";
    public $reProvider = "#delta\.com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "12.01.2015, 15:10";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "delta/it-2210328.eml, delta/it-2210658.eml, delta/it-2227108.eml, delta/it-2236052.eml, delta/it-2236056.eml, delta/it-2236058.eml, delta/it-2236059.eml, delta/it-2236062.eml, delta/it-2236065.eml, delta/it-2316345.eml, delta/it-5.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->mealAbbr = [
                        'B'   => 'Breakfast',
                        'L'   => 'Lunch',
                        'D'   => 'Dinner',
                        'S'   => 'Snack',
                        'C'   => 'Bagels/Beverages',
                        'T'   => 'Cold Meal',
                        'F'   => 'Food Available for Purchase',
                        'V'   => 'Snacks for Sale',
                        '***' => 'Multi Meals',
                    ];

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
                        $s = re('#Passenger\s+Info(?:rmation)?\s+(.*?)\s+(?:Mileage\s+and\s+Fees|Save\s+money\s+when\s+you\s+book)#is') . "\n\n";
                        $s = preg_replace('#\s*SkyMiles\s+Number:\s+.*\s*#i', "\n", $s);
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
                        return total(re('#Total(?:\s+Cost)?:\s+(.*)#'));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re('#Taxes\s+and\s+Fees:\s+(.*)#'));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re('#Mileage\s+required:\s+([\d,]+\s+miles)#i');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $cabinVariants = [
                            'COACH',
                            'First',
                            'B Elite',
                            'T',
                            'L',
                            'Y',
                            'C',
                            'Upgrade',
                        ];
                        $cabinVariants = implode('|', $cabinVariants);
                        $r = '#';
                        $r .= '\w+ +(?P<DepDay>\d+)(?P<DepMonth>[A-Z]{3}) +';
                        $r .= '(?P<FlightInfo1>.*?) +';
                        $r .= '(?P<Status>OK) +(?P<BookingClass>\w) +';
                        $r .= 'LV +(?P<DepName>.*?) +';
                        $r .= '(?P<DepTime>\d+:?\d+[ap]m?)';
                        $r .= ' {2,10}(?P<Meal>\w)?\s+';
                        $r .= '\s*\**\s*';
                        $r .= '\(?(?P<Cabin1>' . $cabinVariants . ')?\)? *';
                        $r .= '(?:(?P<Seats>\d+[A-Z]))?';
                        $r .= ' *\n *';
                        $r .= '(?:\w+ (?P<ArrDay>\d+)(?P<ArrMonth>[A-Z]+) *)?';
                        $r .= '(?P<FlightInfo2>.*?\d\s)? *\*? *';
                        $r .= '(?:(?P<DepNameAdd>[\w ]+?) *(?:\s(?P<Cabin2>' . $cabinVariants . '))? *\n *)?';
                        $r .= '\n?[ \t]*AR *(?P<ArrName>\w.*?)[  ]*(?P<ArrTime>\d+:?\d+[apn]m?)(?P<DayShift>\#)? *(?P<Cabin3>' . $cabinVariants . ')?';
                        $r .= ' *\n';
                        $r .= ' *(?P<ArrNameAdd>\w.*)? *';
                        $r .= '#i';
                        $this->segmentRegex = $r;
                        $s = preg_replace('#<.*?>#', '', $text);

                        if (preg_match_all($this->segmentRegex, $s, $m)) {
                            return $m[0];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            static $flightNumberCounter = []; // Some reservations have flights with same number
                            $year = orval(
                                re('#Issue\s+Date:\s+\d+/\d+/(\d+)#i', $this->text()),
                                re('#\d{4}#i', $this->parser->getHeader('Date'))
                            );

                            if (strlen($year) == 2) {
                                $year = '20' . $year;
                            }

                            if (!$year) {
                                return null;
                            }
                            $res = null;

                            if (preg_match($this->segmentRegex, $text, $m)) {
                                if (preg_match('#(.*)\s+(\d+)\*?#i', nice($m['FlightInfo1'] . ' ' . $m['FlightInfo2']), $mm)) {
                                    $m['AirlineName'] = $mm[1];
                                    $m['FlightNumber'] = $mm[2];
                                }
                                $keys = ['AirlineName', 'FlightNumber', 'BookingClass', 'DepName', 'ArrName', 'Seats', 'Status'];

                                foreach ($keys as $k) {
                                    $res[$k] = $m[$k] ?? null;
                                }

                                if (!$m['ArrDay']) {
                                    foreach (['Day', 'Month'] as $k) {
                                        $m['Arr' . $k] = $m['Dep' . $k];
                                    }
                                }

                                foreach (['Dep', 'Arr'] as $k) {
                                    $res[$k . 'Name'] = trim($res[$k . 'Name'], ',');
                                    $res[$k . 'Code'] = TRIP_CODE_UNKNOWN;

                                    if (preg_match('#^(\d{1,2})(\d{2})([AP])$#', $m[$k . 'Time'], $mm)) {
                                        $m[$k . 'Time'] = $mm[1] . ':' . $mm[2] . ' ' . $mm[3] . 'M';
                                    }
                                    $res[$k . 'Date'] = strtotime($m[$k . 'Day'] . ' ' . $m[$k . 'Month'] . ' ' . $year . ', ' . $m[$k . 'Time']);

                                    if (isset($m[$k . 'NameAdd']) and $m[$k . 'NameAdd']) {
                                        $res[$k . 'Name'] .= ' ' . $m[$k . 'NameAdd'];
                                    }
                                    $res[$k . 'Name'] = nice($res[$k . 'Name']);
                                    $res[$k . 'Name'] = preg_replace_callback('#([\-/]) +#', create_function('$mm', 'return $mm[1];'), $res[$k . 'Name']);
                                }

                                if (isset($m['DayShift']) and $m['DayShift']
                                        and isset($res['ArrDate']) and $res['ArrDate']) {
                                    $res['ArrDate'] = strtotime('+1 day', $res['ArrDate']);
                                }

                                foreach (['Cabin1', 'Cabin2', 'Cabin3'] as $k) {
                                    if (isset($m[$k]) and $m[$k] and strlen($m[$k]) > 1) {
                                        $res['Cabin'] = $m[$k];
                                    }
                                }

                                if (isset($this->mealAbbr[$m['Meal']])) {
                                    $res['Meal'] = $this->mealAbbr[$m['Meal']];
                                }
                                $fn = $res['FlightNumber'];
                                $seats = null;

                                if (!isset($flightNumberCounter[$fn])) {
                                    $flightNumberCounter[$fn] = 0;
                                }

                                if (!isset($res['Seats']) or !$res['Seats']) {
                                    foreach ($this->seats as $passSeats) {
                                        if (isset($passSeats[$fn][$flightNumberCounter[$fn]])) {
                                            $s = $passSeats[$fn][$flightNumberCounter[$fn]];

                                            if (strtolower($s) != 'not assigned') {
                                                $seats[] = $s;
                                            }
                                        }
                                    }
                                    $res['Seats'] = $seats ? $seats : null;
                                }

                                if (isset($res['Seats']) and !$res['Seats']) {
                                    $res['Seats'] = null;
                                }
                                $flightNumberCounter[$fn]++;
                            }

                            return $res;
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $changed = false;

                    // fixing dates
                    if (isset($itNew[0]['TripSegments'])) {
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

                                if ($ts[$k . 'Date'] < $lastDate) {
                                    $changed = true;
                                    $ts[$k . 'Date'] = strtotime('+1 year', $ts[$k . 'Date']);
                                }
                            }
                        }
                    }

                    if ($changed) {
                        return $it;
                    }
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
