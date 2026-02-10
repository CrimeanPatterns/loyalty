<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2411849 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#POWERED\s+BY\s+AMADEUS#i', 'blank', '-500'],
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
    public $upDate = "26.01.2015, 14:57";
    public $crDate = "26.01.2015, 14:43";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2411849.eml";
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
                        return re("#\n\s*BOOKING\s+REF\s*:\s*([A-Z\d\-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*PASSENGER\(S\)\s*:\s*([^\n]+)#");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*BA FREQUENT FLYER ([A-Z\d]+)#", $this->text());
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*BOOKING STATUS\s*:\s*([^\n]+)#", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*ISSUE\s+DATE\s*:\s*([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*([A-Z]{3}\s+\d+\s+[A-Z]{3}\s+\d{4}\s+FLIGHT)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*FLIGHT\s*:\s*([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return clear("#[,\s]*TERMINAL.+#", re("#\n\s*DEPARTURE\s*:\s*\d+\s+[A-Z]+\s+\d+:\d+\s*[APM]*?[\s-]+(.+)#"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#^(\w+\s+\d+\s+\w+\s+\d{4})#");
                            $dep = correctDate(re("#\n\s*DEPARTURE\s*:\s*(\d+\s+[A-Z]+)\s+(\d+:\d+\s*[APM]*?)#") . ", " . re(2), $date);
                            $arr = correctDate(re("#\n\s*ARRIVAL\s*:\s*(\d+\s+[A-Z]+)\s+(\d+:\d+\s*[APM]*?)#") . ", " . re(2), $dep);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return clear("#[,\s]*TERMINAL.+#", re("#\n\s*ARRIVAL\s*:\s*\d+\s+[A-Z]+\s+\d+:\d+\s*[APM]*?[\s-]+(.+)#"));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*EQUIPMENT\s*:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\n\s*CLASS\s*:\s*([^\n]*?)\s*\(([A-Z])\)#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*DURATION\s*:\s*([^\n]+)#");
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*MEAL\s*:\s*([^\n]+)#");
                        },

                        "Smoking" => function ($text = '', $node = null, $it = null) {
                            return re("#NON SMOKING#ix") ? false : null;
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
