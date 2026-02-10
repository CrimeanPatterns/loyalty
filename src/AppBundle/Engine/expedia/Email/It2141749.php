<?php

namespace AwardWallet\Engine\expedia\Email;

class It2141749 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?expedia#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#expedia#i";
    public $reProvider = "#expedia#i";
    public $caseReference = "6735";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "expedia/it-2141749.eml";
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
                        return re("#\n\s*Booking\s+ID\s*:\s*([A-Z\d-]+)#i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#--> (\w+) in Flight <--#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(.*?\s+\([A-Z]{3}\)\s+to\s+.*?\s+\([A-Z]{3}\))#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\n\s*Flight\s*:\s*([A-Z\d]{2}\s*\d+)#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\(([A-Z]{3})\)#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re("#.*?\s+\([A-Z]{3}\)\s+to\s+.*?\s+\([A-Z]{3}\)\s+on\s+\w+,\s*(.+)#");

                            $dep = $date . ',' . uberTime(re("#\n\s*Depart:([^\n]+)#"));
                            $arr = $date . ',' . uberTime(re("#\n\s*Arrive:([^\n]+)#"));

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return ure("#\(([A-Z]{3})\)#", 2);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Aircraft\s*:([^\n<]*?)(?:[<\n]|$)#"));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Cabin\s*:([^\n]*?)\n#"));
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\n\s*Seat\(s\)\s*:([^\n]*?)\n#"));
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
