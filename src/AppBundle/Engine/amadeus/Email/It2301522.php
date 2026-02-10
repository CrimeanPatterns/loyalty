<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2301522 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $rePlainRange = "";
    public $reHtml = "#AIRCRAFT:.*?AMADEUS\.COM#s";
    public $reHtmlRange = "-2000";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#amadeus#i";
    public $reProvider = "#amadeus#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "24.12.2014, 13:31";
    public $crDate = "24.12.2014, 13:12";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2301522.eml, amadeus/it-2301524.eml, amadeus/it-2301528.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    if (re("#SERVICE\s+DATE\s+FROM\s+TO\s+DEPART\s+ARRIVE#")) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#BOOKING REF ([A-Z\d\-]+)#x");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*([A-Z.,]+/[A-Z.,]+)\n#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+RESERVATION ([A-Z]+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*DATE\s+([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(.*?\s+\d{2}[A-Z]{3}\s+.*?\s+.*?\s+\d+[AP]\s+\d+[AP])#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#^[^\n]+\n\s*([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re("#^.*?\s+\d+[A-Z]{3}\s+([^\s]+)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $anchor = totime(re("#\n\s*DATE\s+([^\n]+)#", $this->text()));
                            $year = date('Y', $anchor);

                            re("#^.*?\s+(\d+[A-Z]{3})\s+[^\s]+\s+.*?\s+(\d+)(\d{2}[AP])\s+(\d+)(\d{2}[AP])#");

                            $dep = re(1) . $year . ' ' . re(2) . ':' . re(3) . 'M';
                            $arr = re(1) . $year . ' ' . re(4) . ':' . re(5) . 'M';

                            return [
                                'DepDate' => correctDate($dep, $anchor),
                                'ArrDate' => correctDate($arr, $anchor),
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return re("#^.*?\s+\d+[A-Z]{3}\s+[^\s]+\s+(.*?)\s+\d+[AP]\s+#");
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return re("#AIRCRAFT\s*:\s*([^\n]+)#");
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return [
                                'BookingClass' => re("#\n\s*([A-Z])\s+([A-Z]+)#"),
                                'Cabin'        => re(2),
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
