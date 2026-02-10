<?php

namespace AwardWallet\Engine\capitalcards\Email;

class It2355316 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?capitalone#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#capitalone#i', 'us', ''],
    ];
    public $reProvider = [
        ['#capitalone#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "14.01.2015, 14:56";
    public $crDate = "14.01.2015, 14:50";
    public $xPath = "";
    public $mailFiles = "capitalcards/it-2355316.eml, capitalcards/it-2355316.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Passenger Name\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(\w{3},\s+\w{3}\s+\d+,\s+\d{4}\n)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*Flight:\s*(.*?)\s+Flight\s+(\d+)\s+\(on\s+([^\)]+)\)#"),
                                'FlightNumber' => re(2),
                                'Aircraft'     => re(3),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(re("#^[^\n]+#"));

                            $dep = $date . ',' . re("#\n\s*Depart\s*:\s*(\d+:\d+\s*[APM]+)#i");
                            $arr = $date . ',' . re("#\n\s*Arrive\s*:\s*(\d+:\d+\s*[APM]+)#i");

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Total Travel Time\s*:\s*([^\n]+)#");
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
