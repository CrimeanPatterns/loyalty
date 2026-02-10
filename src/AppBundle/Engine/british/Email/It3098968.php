<?php

namespace AwardWallet\Engine\british\Email;

class It3098968 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\bBritish\s*Airways\b.+?Flight\s+details.+?Flight\s*-\s*.+?Departure\s*-[^\n]+?\([A-Z]+\)\s*?\n#si', 'blank', '5000'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['Flight details for Booking', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]ba[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]ba[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "24.09.2015, 13:56";
    public $crDate = "24.09.2015, 13:24";
    public $xPath = "";
    public $mailFiles = "british/it-3098968.eml";
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
                        return re("#Booking\s*reference[\s-]+([\w-]+)#");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $raw = re("#Flight\s*details\b(.+?)To\s+manage\s+this\s+booking#si");

                        return splitter("#(Flight\s*-.+?Departure\s*-)#si", $raw);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data['AirlineName'] = re("#\n[>\s]*(\w{2})\s*(\d+)\s#");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $data['DepName'] = re("#Departure\s*-\s*([^\n]+?)\s*\(([A-Z]{3})\)\s#");
                            $data['DepCode'] = re(2);

                            return $data;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#Departure\s*-.+?(\d+ +\S+ +\d{4} +\d+:\d+)#si"));
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $data['ArrName'] = re("#Arrival\s*-\s*([^\n]+?)\s*\(([A-Z]{3})\)\s#");
                            $data['ArrCode'] = re(2);

                            return $data;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(re("#Arrival\s*-.+?(\d+ +\S+ +\d{4} +\d+:\d+)#si"));
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
