<?php

namespace AwardWallet\Engine\alitalia\Email;

class It2596673 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?alitalia#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]alitalia#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]alitalia#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.04.2015, 18:41";
    public $crDate = "02.04.2015, 18:28";
    public $xPath = "";
    public $mailFiles = "alitalia/it-2596673.eml";
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
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("//text()[contains(., 'RESERVATION CODE')]/ancestor::td[1]/following-sibling::td[1]"));

                        return [
                            "Passengers"    => re("#^([^\n]+)\s+([A-Z\d-]+)\s+\d+#", $info),
                            "RecordLocator" => re(2),
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return [
                            'BaseFare'    => cost(re("#\n\s*Total Price\s*:\s*([\d.,]+\s+[A-Z]+)\s+([\d.,]+\s+[A-Z]+)\s+([\d.,]+\s+[A-Z]+)#")),
                            'Tax'         => cost(re(2)),
                            'TotalCharge' => cost(re(3)),
                            'Currency'    => currency(re(3)),
                        ];
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Departure')]/ancestor::tr[1][contains(., 'Arrival')]/following-sibling::tr[contains(., ':')]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[5]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node('td[1]');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node('td[3]'));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node('td[2]');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return totime(node('td[4]'));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[6]", null, true, "#^([A-Z])$#");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return node("td[7]", null, true, "#^(\d+[A-Z]+)$#");
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
