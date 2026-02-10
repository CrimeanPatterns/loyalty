<?php

namespace AwardWallet\Engine\austrian\Email;

class It2100150 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Von\s*:[^\n]*?austrian#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#austrian#i";
    public $reProvider = "#austrian#i";
    public $caseReference = "8661";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "austrian/it-2100150.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*(Outbound flight)#");
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your Booking code\s*:\s*([A-Z\d-]+)#", $this->text());
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#\n\s*Name:\s*([^\n]+)#", function ($m) use (&$names) {
                            $names[trim($m[1])] = 1;
                        }, $text);

                        return array_keys($names);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(\w{3},\s*\d+\.\d+\.\d+\s+\d+:\d+\s+\d+:\d+\s+[^\n]+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            re("#(?:^|\n)\s*(\w{3},\s*\d+\.\d+\.\d+)\s+(\d+:\d+)\s+(\d+:\d+)\s+([^\n]+)\s+(.*?)\s+([A-Z\d]{2})\s+(\d+)#");

                            $dep = re(1) . ',' . re(2);
                            $arr = re(1) . ',' . re(3);

                            correctDates($dep, $arr);

                            return [
                                'DepDate'      => $dep,
                                'ArrDate'      => $arr,
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                                'AirlineName'  => re(6),
                                'FlightNumber' => re(7),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\)\s+(.*?)\s*/\s*([A-Z])\s*(?:\n|$)#"),
                                'BookingClass' => re(2),
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
