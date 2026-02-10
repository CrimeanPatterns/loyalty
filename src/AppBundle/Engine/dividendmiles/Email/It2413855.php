<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class It2413855 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?usairways#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#\busairways\b|\bmyusairways\b#i'],
    ];
    public $reProvider = [
        ['#\busairways\b|\bmyusairways\b#i'],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "27.01.2015, 20:23";
    public $crDate = "27.01.2015, 20:13";
    public $xPath = "";
    public $mailFiles = "dividendmiles/it-2413855.eml";
    public $re_catcher = "#.*?#";
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
                        return re("#\n\s*Confirmation\s+code\s+([A-Z\d\-]+)\s*\n#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#Cancellation impacting (.*?)\-#ix", $this->parser->getSubject()));
                    },

                    "Cancelled" => function ($text = '', $node = null, $it = null) {
                        return re("#Your flight is cancel+ed#ix") ? true : false;
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your flight is (\w+)#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*(.*?)\s+flight\s+\#\s*(\d+)\s+from\s+(.*?)\s*\(([A-Z]{3})\)\s+to\s+(.*?)\s*\(([A-Z]{3})\)\s+on\s+(.*?)\s+has\s+been#"),
                                'FlightNumber' => re(2),
                                'DepName'      => re(3),
                                'DepCode'      => re(4),
                                'ArrName'      => re(5),
                                'ArrCode'      => re(6),
                                'DepDate'      => totime(uberDateTime(re(7))),
                                'ArrDate'      => MISSING_DATE,
                            ];
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
        return ["en"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
