<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2347148 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:.*?amadeus\.net#is', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#amadeus#i', 'us', ''],
    ];
    public $reProvider = [
        ['#amadeus#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "16.01.2015, 10:51";
    public $crDate = "16.01.2015, 10:34";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2347148.eml";
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
                        return "B";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#BOOKING REF\s+([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#[A-Z\d]+\s+([A-Z]+/[A-Z]+)\n#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+DATE\s+(\d+[A-Z]+\d+)#"));
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(TRAIN)#");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $anchor = re("#\s+DATE\s+(\d+[A-Z]+\d+)#", $this->text());
                            $year = date('Y', totime($anchor));

                            re("#\n\s*(\d+[A-Z]{3})\s+(.*?)\s+(\d+)(\d{2})\n\s*[A-Z]+\s+(.*?)\s+(\d+)(\d{2})\n#");

                            $dep = re(1) . $year . ',' . re(3) . ":" . re(4);
                            $arr = re(1) . $year . ',' . re(6) . ":" . re(7);

                            correctDates($dep, $arr, $anchor);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                                'DepName' => re(2),
                                'ArrName' => re(5),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Type" => function ($text = '', $node = null, $it = null) {
                            return re("#^TRAIN\s+([A-Z]+\s+\d+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin' => re("#\n\s*([A-Z]+\s+\d+),\s*SEAT\s+([A-Z\d]+)#"),
                                'Seats' => re(2),
                            ];
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+CLASS:\s*([A-Z])#");
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
