<?php

namespace AwardWallet\Engine\bcd\Email;

class It2752668 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Travel Summary – Agency Record Locator#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]bcd[.]#i', 'us', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.06.2015, 13:00";
    public $crDate = "02.06.2015, 12:44";
    public $xPath = "";
    public $mailFiles = "bcd/it-2752668.eml";
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
                        return reni('Agency Record Locator (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $name = nice(node("//*[normalize-space(text()) = 'Traveler']/following::td[1]"));

                        return [$name];
                    },

                    "TripCategory" => function ($text = '', $node = null, $it = null) {
                        return TRIP_CATEGORY_TRAIN;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), '*RAIL-')]/ancestor::table[1]");
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return reni('FR - (.+?) \/');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::tr[1]');
                            $date = uberDate($info, 1);

                            $hour = reni('\bLV - (\d{2})');
                            $min = reni('\bLV - \d{2}(\d{2})');
                            $time = sprintf('%s:%s', $hour, $min);

                            $dt = strtotime($date);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return reni('AT - (.+?) \/');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $info = node('./preceding::tr[1]');
                            $date = uberDate($info, 1);
                            $date_arr = reni('DT - (.+?) \/');

                            $hour = reni('\bAR - (\d{2})');
                            $min = reni('\bAR - \d{2}(\d{2})');
                            $time = sprintf('%s:%s', $hour, $min);

                            $dt = strtotime($date);
                            $dt = strtotime($date_arr, $dt);
                            $dt = strtotime($time, $dt);

                            return $dt;
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('SP - (.+?) \/');
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
