<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2515812 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?amadeus#i', 'blank', ''],
        ['#\n[>\s*]*From\s*:[^\n]*?Colwick\s*Travel#i', 'blank', ''],
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
    public $upDate = "28.02.2015, 06:45";
    public $crDate = "28.02.2015, 06:20";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2515812.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*(\d+\s+[A-Z]{3}\s+\d+\s*\-\s*[A-Z]+\s*\n\s+(?:AIR|CAR|HOTEL)\s+)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#OUR RECORD LOCATOR\s*:\s*([A-Z\d\-]+)#", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*FOR\s*:\s*([A-Z\d,./ ]*?)\s+REF#", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\s+DATE\s*:\s*([^\n]+)#", $this->text()));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$node];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#FLT:\s*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepDate' => totime(uberDate() . ', ' . re("#\n\s*(.*?)\s+(\d+)(\d{2}\s*[AP]*)#", $text, 2) . ':' . re(3) . 'M'),
                                'DepName' => re(1),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return [
                                'ArrDate' => totime(clear("#NM#", uberDate() . ', ' . re("#\d{3,4}[APN].*?\n\s*([^\n]*?)\s+(\d+)(\d{2}\s*[APN])#s", $text, 2) . ':' . re(3) . 'M')),
                                'ArrName' => re(1),
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*AIR\s+(.*?)\s+FLT:#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#EQP:\s*([^\n]+)#");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin' => re("#FLT:\s*\d+\s+(\w+)\s+([^\n]+)#"),
                                'Meal'  => re(2),
                            ];
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+SEAT\-(\d+[A-Z]+)#");
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(\d+HR\s*\d+MIN)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#NON\-STOP#") ? 0 : null;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return re("#CONFIRMATION\s*:\s*([A-Z\d\-]+)#");
                        },
                    ],
                ],

                "PostProcessing" => function ($text = '', $node = null, $it = null) {
                    return correctItinerary($it, true);
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
