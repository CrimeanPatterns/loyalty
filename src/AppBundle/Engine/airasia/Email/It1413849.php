<?php

namespace AwardWallet\Engine\airasia\Email;

class It1413849 extends \TAccountCheckerExtended
{
    public $reFrom = "#airasia#i";
    public $reProvider = "#airasia#i";
    public $rePlain = "#\n[<>a-z/\s*]*From\s*:[^\n]*?airasia#i";
    public $rePlainRange = "1000";
    public $typesCount = "1";
    public $langSupported = "en";
    public $reSubject = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $xPath = "";
    public $mailFiles = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return [$text];
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#booking\s+number\s+is\s*:\s*([\d\w\-]+)#i");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return trim(re("#\n\s*Dear\s+([^\n,]+)#"));
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your\s+booking\s+has\s+been\s+(\w+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(re("#\n\s*Your\s+booking\s+date\s+is\s+([^\n]+)#"));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#\n\s*(\w{3}\s+\d+\w{3}\s+\d{4}\s+[A-Z\d]{2}\s*\d+\s+[A-Z]{3}\s+\d+:\d+\s+[A-Z]{3}\s+\d+:\d+)#");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(re("#\d{4}\s+([A-Z\d]{2}\s*\d+)\s+#"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+([A-Z]{3})\s+\d+:\d+#");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate();
                            $dep = $date . ',' . uberTime();
                            $arr = $date . ',' . uberTime(2);

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re("#\d+:\d+\s+([A-Z]{3})\s+\d+:\d+#is");
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
}
