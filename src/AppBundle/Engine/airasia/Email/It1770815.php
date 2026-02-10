<?php

namespace AwardWallet\Engine\airasia\Email;

class It1770815 extends \TAccountCheckerExtended
{
    public $reFrom = "#airasia#i";
    public $reProvider = "#airasia#i";
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?airasia|@airasia.com[\s>]+wrote#i";
    public $rePlainRange = "";
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
                        return re("#\n\s*Your booking number is\s*:\s*([^\n]+)#");
                    },

                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dear\s+([^\n,]+)#i");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your\s+booking\s+has\s+been\s+(\w+)#i");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Depart')]/ancestor::tr[1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node('td[3]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return node('td[4]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = node('td[2]');

                            $dep = $date . ',' . node('td[5]');
                            $arr = $date . ',' . node('td[7]');

                            correctDates($dep, $arr);

                            return [
                                'DepDate' => $dep,
                                'ArrDate' => $arr,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return node('td[6]');
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
