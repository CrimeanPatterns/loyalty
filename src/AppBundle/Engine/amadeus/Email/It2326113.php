<?php

namespace AwardWallet\Engine\amadeus\Email;

class It2326113 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#Printed\s*from\s*Amadeus\s*Cruise#i', 'us', '500'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#amadeus#i', 'us', ''],
    ];
    public $reProvider = [
        ['#amadeus#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "17.01.2015, 01:06";
    public $crDate = "15.01.2015, 10:14";
    public $xPath = "";
    public $mailFiles = "amadeus/it-2326113.eml";
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
                        return "C";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Confirmation Number\s*:\s*([A-Z\d-]+)#");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $nodes = nodes("//*[contains(text(), 'Guest Details')]/following::table[.//td[contains(., '.')][contains(@style, 'border-width: 0px 0px 1pt')]]");

                        foreach ($nodes as &$node) {
                            if (re("#^\d+\.\s*(.+)#", $node)) {
                                $node = re(1);
                            } else {
                                break;
                            }
                        }

                        return $nodes;
                    },

                    "ShipName" => function ($text = '', $node = null, $it = null) {
                        return re("#\s{2,}Ship\s*:\s*([^\n]+)#");
                    },

                    "CruiseName" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Cruise\s+Line\s*:\s*([^\n]*?)\s{2,}#");
                    },

                    "RoomNumber" => function ($text = '', $node = null, $it = null) {
                        return re("#\s+Cabin\s*:\s*([^\n]*?)\s{2,}#");
                    },

                    "RoomClass" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Category\s*:\s*([^\s]+)#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Status\s*:\s*([^\n]+)#");
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        //return cost(re("#\n\s*Amount\s+Received\s*:\s*([^\n]+)#"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#\n\s*Booking Currency\s*:\s*([^\n]+)#"));
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return totime(uberDateTime(re("#\n\s*Printed from Amadeus Cruise on ([^\n]+)#ix")));
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        re("#\n\s*Sailing\s+Information.*?\s+From\s*/\s*To\s*:\s*([^\n]*?)\s*/\s*([^\n]*?)\n#ims");
                        $from = re(1);
                        $to = re(2);

                        $dep = totime(uberDate(re("#\n\s*Date\s*:\s*([^\n]+)#")));
                        $length = re("#\n\s*Cruise Length\s*:\s*(\d+)#");

                        $arr = strtotime('+' . $length . ' days', totime(date('Y-m-d', $dep)));

                        return ["$from|$dep", "$to|$arr"];
                    },

                    "TripSegments" => [
                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return [
                                'DepName' => re("#^(.*?)\|(.*?)$#"),
                                'DepDate' => re(2),
                                'ArrDate' => re(2),
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
