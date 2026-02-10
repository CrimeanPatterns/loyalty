<?php

namespace AwardWallet\Engine\alaskaair\Email;

class It1884485 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?alaskaair#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "#Alaska\s+Airlines\s+Boarding\s+Pass#i";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "";
    public $reProvider = "";
    public $xPath = "";
    public $mailFiles = "alaskaair/it-1884435.eml, alaskaair/it-1884480.eml, alaskaair/it-1884485.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    return splitter("#\n\s*(Boarding\s+Pass)#i");
                },

                "#.*?#" => [
                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return [
                            'Passengers'     => [re("#^Boarding Pass\s+([^\n]+)\s+([^\n]+)\n\s*Confirmation\s+Code\s*:\s*([^\n]+)#")],
                            'AccountNumbers' => re(2),
                            'RecordLocator'  => re(3),
                        ];
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return [
                                'AirlineName'  => re("#\n\s*([^\n]*?)\s+(\d+)\n\s*([A-Z]{3})\s*\-\s*([A-Z]{3})\s+(\d+/\d+/\d+)#ims"),
                                'FlightNumber' => re(2),
                                'DepCode'      => re(3),
                                'ArrCode'      => re(4),
                                'DepDate'      => totime(re(5) . ',' . re("#Boarding\s+at\s+(\d+:\d+\s*[APMapm]{2})#")),
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Seat\s+(\d+[A-Z]+)#");
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
