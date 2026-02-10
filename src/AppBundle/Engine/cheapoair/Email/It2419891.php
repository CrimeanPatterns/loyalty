<?php

namespace AwardWallet\Engine\cheapoair\Email;

class It2419891 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?cheapoair#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#cheapoair#i', 'us', ''],
    ];
    public $reProvider = [
        ['#cheapoair#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "08.02.2015, 04:10";
    public $crDate = "08.02.2015, 03:53";
    public $xPath = "";
    public $mailFiles = "cheapoair/it-2419891.eml";
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
                        return re("#changed its schedule#ix") ? CONFNO_UNKNOWN : null;
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Dear\s+(.*?),\s*\n#");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#\n\s*Your airline (has\s+\w+)#ix");
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//img[contains(@src, 'bound')]/ancestor::table[2]/tr[contains(., 'Flight') and not(contains(., 'Duration'))]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $nodeText = implode("\n", $this->http->FindNodes(".//text()[normalize-space()]", $node));

                            return [
                                'AirlineName'  => re("#^(.*?)\n\s*Flight\s+(\d+)\s+([A-Z\d]+)\n\s*(.+)\s+(.+)#i", $nodeText),
                                'FlightNumber' => re(2),
                                'Aircraft'     => re(3),
                                'DepName'      => re(4),
                                'ArrName'      => re(5),
                            ];
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $year = re("#depart\s+on.*?\s+(\d{4})#", $this->text());

                            return totime(uberDate() . ' ' . $year . ',' . uberTime());
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $year = re("#depart\s+on.*?\s+(\d{4})#", $this->text());

                            return totime(uberDate(2) . ' ' . $year . ',' . uberTime(2));
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return [
                                'Cabin'        => re("#\n\s*([\d\w ]*?)\s*\[([A-Z])\]#"),
                                'BookingClass' => re(2),
                            ];
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return re("#\s+(\d+hr\s*\d+min)#");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Non[\s-]*stop#i") ? 0 : null;
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
