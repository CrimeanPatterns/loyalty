<?php

namespace AwardWallet\Engine\flysaa\Email;

class It3023224 extends \TAccountCheckerExtended
{
    public $rePlain = "";
    public $reHtml = [
        ['//title[contains(., \'South African Airways\')]&&//*[normalize-space(text())="Heure Limite d\'Embarquement:"]', 'blank', '/1'],
    ];
    public $rePDF = "";
    public $reSubject = [
        ['Online Check-in Confirmation', 'blank', ''],
    ];
    public $reFrom = [
        ['#[@.]flysaa#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]flysaa#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "31.08.2015, 20:15";
    public $crDate = "31.08.2015, 19:49";
    public $xPath = "";
    public $mailFiles = "flysaa/it-3023224.eml";
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
                        return re("#Booking\s+Reference\s*:\s*([\w-]+)#i");
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        if (re("#\n\s*Passenger\s*:\s*([^\n]+)#i")) {
                            return [re(1)];
                        }
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (re("#been successfully checked\-in#")) {
                            return "checked-in";
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return splitter("#(Flight:.+?To:)#si", re("#\n\s*Booking\s+Details\s*?(\n.+?\n\s*)Baggage\s+Information\s*?\n#si"));
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $data['AirlineName'] = re("#Flight:\s*(\w+?)\s*(\d+)#i");
                            $data['FlightNumber'] = re(2);

                            return $data;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#From:\s*(.+?)\s+\d+\/\d+\/\d+#si"));
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return timestamp_from_format(re("#From:\s*.+?\s+(\d+\/\d+\/\d+)\s+\-\s+(\d+:\d+)#si") . ", " . re(2), "d/m/Y, H:i");
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return nice(re("#To:\s*(.+?)\s+\d+\/\d+\/\d+#si"));
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return timestamp_from_format(re("#To:\s*.+?\s+(\d+\/\d+\/\d+)\s+\-\s+(\d+:\d+)#si") . ", " . re(2), "d/m/Y, H:i");
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return re("#Flight:\s*\w+\s+\-\s+([^\n]+?)\s+CLASS#i");
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
