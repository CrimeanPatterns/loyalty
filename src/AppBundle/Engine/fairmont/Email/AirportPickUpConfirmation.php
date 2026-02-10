<?php

namespace AwardWallet\Engine\fairmont\Email;

class AirportPickUpConfirmation extends \TAccountCheckerExtended
{
    public $rePlain = "#This\s+is\s+to\s+confirm\s+the\s+airport\s+transfer.*?fairmont\.com#is";
    public $rePlainRange = "/1";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#monreal@fairmont\.com#i";
    public $reProvider = "#monreal@fairmont\.com#i";
    public $caseReference = "";
    public $isAggregator = "";
    public $upDate = "25.12.2014, 08:02";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "fairmont/it-1940537.eml";
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
                        return CONFNO_UNKNOWN;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [re('#Dear\s+(.*?),#i')];
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re('#This\s+is\s+to\s+confirm\s+the\s+airport#')) {
                            return 'Confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return [$text];
                    },

                    "TripSegments" => [
                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return re('#attached airport map of (.*?) for your reference#i');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $regex = '#This is to confirm the airport transfer.*at (\d+:\d+)H on (\w+ \d+, \d+)#i';

                            if (preg_match($regex, $text, $m)) {
                                return strtotime($m[2] . ', ' . $m[1]);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $subj = re('#Thank you for choosing (.*?) as your home#i');

                            return [
                                'ArrName' => $subj,
                            ];
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return MISSING_DATE;
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
