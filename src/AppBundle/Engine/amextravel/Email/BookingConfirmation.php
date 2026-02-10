<?php

namespace AwardWallet\Engine\amextravel\Email;

class BookingConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Thank you for using American Express One Interactive Business Travel#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#amextravel#i', 'us', ''],
    ];
    public $reProvider = [
        ['#amextravel#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "26.01.2015, 19:58";
    public $crDate = "26.01.2015, 15:59";
    public $xPath = "";
    public $mailFiles = "amextravel/it-2405608.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->year = re('#\d{4}#i', $this->parser->getHeader('date'));
                    $text = $this->setDocument('plain');
                    $this->totals = [];
                    $totalRegexps = [
                        'BaseFare'    => '#Base\s+Airfare\s+\(per person\)\s+(.*)#',
                        'Tax'         => '#Total\s+Taxes\s+and/or\s+Applicable\s+fees\s+\(per\s+person\)\s+(.*)#i',
                        'TotalCharge' => '#Total\s+Flight\s+\(per\s+person\)\s+excluding\s+Air\s+Extras\s+(.*)#i',
                    ];

                    foreach ($totalRegexps as $key => $value) {
                        $this->totals[$key] = cost(re($value));

                        if (!isset($this->totals['Currency']) or !$this->totals['Currency']) {
                            $this->totals['Currency'] = currency(re($value));
                        }
                    }
                    $passInfo = re('#\*{5}\s*(Name\(s\).*?)\s*\*{5}#si');
                    $this->travellers = [];

                    if (preg_match_all('#Name\s*:\s+(.*)#i', $passInfo, $m)) {
                        $this->travellers = $m[1];
                    }

                    if (!$this->travellers or count($this->travellers) > 1) {
                        // Totals is specified only for one person
                        $this->totals = [];
                    }
                    $r = '#Airline\s+Record\s+Locator\s+\#\d+\s+\w{2}-([\w\-]+)\s+\((Air Canada)\)#i';
                    $matches = [];
                    $this->recordLocators = [];

                    if (preg_match_all($r, $text, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $m) {
                            $this->recordLocators[$m[2]] = $m[1];
                        }
                    }
                    $text = re('#\*{5}\s*ITINERARY((?s).*?)\n\s*\*{5}#i');

                    return splitter('#\n\s*(AIR.*)#i', $text);
                },

                "#Depart:#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $an = re('#Equip\.:\s+(' . implode('|', array_keys($this->recordLocators)) . ')\s+\d+#');

                        return $this->recordLocators[$an];
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return $this->travellers;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $r = '#Flight/Equip\.\s*:\s+(' . implode('|', array_keys($this->recordLocators)) . ')\s+(\d+)\s+(.*)#i';

                            if (preg_match($r, $text, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                    'Aircraft'     => $m[3],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $res = [];

                            foreach (['Dep', 'Arr'] as $key) {
                                $r = '#' . $key . '.*?:\s+(.*?)\s*\((\w{3})\)\s+\w+,\s+(\w+\s+\d+)\s+(\d+:\d+\s+(?:am|pm)?)#i';

                                if (preg_match($r, $text, $m)) {
                                    $res[$key . 'Name'] = $m[1];
                                    $res[$key . 'Code'] = $m[2];
                                    $res[$key . 'Date'] = strtotime($m[3] . ', ' . $this->year . ', ' . $m[4]);
                                }
                            }

                            return $res;
                        },

                        "TraveledMiles" => function ($text = '', $node = null, $it = null) {
                            return re('#Miles:\s+(\d+)#');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re('#Class\s*:\s+(.*)#i');
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $stops = re('#Stops:\s+(.*?);#');

                            return $stops == 'non-stop' ? 0 : null;
                        },

                        "Status" => function ($text = '', $node = null, $it = null) {
                            return re('#Status:\s+(.*)#i');
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    $itNew = uniteAirSegments($it);

                    if (count($itNew) == 1) {
                        foreach ($this->totals as $key => $value) {
                            $itNew[0][$key] = $value;
                        }
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
        return false;
    }
}
