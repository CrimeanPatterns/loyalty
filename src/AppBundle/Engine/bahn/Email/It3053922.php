<?php

namespace AwardWallet\Engine\bahn\Email;

class It3053922 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[\s>]*Reisedaten:[\s>]*\d+.\d+.\d+:\s*[^\n]+?\s\d+:\d+\s*\-\s*[^\n]+?\s\d+:\d+.+?\bbahn\.de#si', 'blank', '10000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Reservierungsbestätigung für', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]bahn\.#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#\bbahn\.#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "15.09.2015, 21:38";
    public $crDate = "15.09.2015, 20:39";
    public $xPath = "";
    public $mailFiles = "bahn/it-3053922.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (empty($text)) {
                        $text = $this->parser->getPlainBody();
                    }
                    $this->times = [];
                    preg_match_all("#\d+\.\d+\.\d{4}\s*:\s*(?<Name1>.*?)\s+(?<Time1>\d+:\d+)\s+-\s+(?<Name2>.*?)\s+(?<Time2>\d+:\d+)#", $text, $m, PREG_SET_ORDER);

                    foreach ($m as $row) {
                        $this->times[$row['Name1']] = $row['Time1'];
                        $this->times[$row['Name2']] = $row['Time2'];
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#Auftragsnummer:\s*([\w-]+)#i");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n[>\s]*Auftraggeber:\s*(.*?)\s*(?:\n|Kundennummer|E-Mail:)#i")) {
                            return [re(1)];
                        }
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n[>\s]*Kundennummer:\s*(.*?)\s*(?:\n|E-Mail:)#i")) {
                            return [re(1)];
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return total(re("#Gesamtpreis\s+Reservierungen:\s*([^\n]+)#i"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        preg_match_all("#(?<Date>\d+.\d+.\d+)\s+(?<AirlineName>.*?)\s+(?<FlightNumber>\d+), (?<DepName>.*?)\s+\(ab\s+(?<DepTime>\d+:\d+)\)\s+-\s+(?<ArrName>.*?),(?<Desc>.+)#", $text, $m, PREG_SET_ORDER);
                        $this->segments = $m;
                        $this->current = 0;

                        return $m;
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $ArrTime = $this->times[$text["ArrName"]] ?? ($this->segments[$this->current + 1]['DepTime'] ?? '0:00');

                            return [
                                "DepName" => $text["DepName"],
                                "ArrName" => $text["ArrName"],
                                "DepDate" => strtotime($text["Date"] . ', ' . $text["DepTime"]),
                                "ArrDate" => strtotime($text["Date"] . ', ' . $ArrTime),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $this->current++;

                            return re("#Wagen\s+\d+\s*,\s*Platz\s+(\d+),#", $text["Desc"]);
                        },
                    ],
                ],
            ],
        ];
    }

    public static function getEmailTypesCount()
    {
        return 1;
    }

    public static function getEmailLanguages()
    {
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
