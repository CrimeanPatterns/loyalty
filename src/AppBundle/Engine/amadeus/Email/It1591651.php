<?php

namespace AwardWallet\Engine\amadeus\Email;

class It1591651 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s]*From\s*:[^\n]*?amadeus#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $xPath = "";
    public $mailFiles = "amadeus/it-1591651.eml, amadeus/it-1899207.eml";
    public $pdfRequired = "0";
    public $isAggregator = "1";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*YOUR BOOKING REFERENCE:\s*([A-Z\d\-]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = [];
                        re("#\n\s*Passenger\s*\d+:\s*([^\n]+)#ms", function ($m) use (&$names) {
                            $names[] = $m[1];
                        }, $text);

                        return $names;
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Frequent flyer\s*:\s*([^\n]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(\n\s*[^\n]+\s+\w+\s+\d+\s+\w+\s+\d{4})#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*([A-Z\d]{2})(\d+)\s*(?:\||[A-Z])#"),
                                'FlightNumber' => re(2),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#(.*?)\s*\(.*?\)\s*\-\s*(.*?)\s*\(.*?\)#"),
                                'ArrName' => re(2),
                            ];
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();
                            $dep = strtotime($date . ', ' . uberTime());
                            $arr = strtotime($date . ', ' . uberTime(2));

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*(\w+)\s+Cabin#i");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            $flight = re("#\n\s*([A-Z\d]{2}\d+)\s*(?:\||[A-Z])#");

                            return re("#\n\s*$flight\s+(\d+\w+)#", $this->text());
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
        return true;
    }
}
