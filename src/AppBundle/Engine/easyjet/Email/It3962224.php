<?php

namespace AwardWallet\Engine\easyjet\Email;

class It3962224 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?easyjet#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]easyjet#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]easyjet#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "23.06.2016, 14:18";
    public $crDate = "23.06.2016, 13:54";
    public $xPath = "";
    public $mailFiles = "";
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
                        return re('#gracias por realizar la reserva (\w+)#');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes('//img[contains(@src, "flightdetailsplane-v1")]/ancestor::table[2]/following-sibling::table[1]//tr/td[1]');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath('.');
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $s = node('//img[contains(@src, "flightdetailsplane-v1")]/ancestor::tr[1]/td[1]');

                            if (preg_match('#(\w{3})(\d+)#', $s, $m)) {
                                return [
                                    'AirlineName'  => $m[1],
                                    'FlightNumber' => $m[2],
                                ];
                            }
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $s = node('//img[contains(@src, "flightdetailsplane-v1")]/ancestor::tr[2]/preceding-sibling::tr[1]');

                            if (preg_match('#(.*)\s+a\s+(.*)#i', $s, $m)) {
                                return [
                                    'DepName' => $m[1],
                                    'ArrName' => $m[2],
                                ];
                            }
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $s = en(re('#\d+\s+\w+\s+\d+:\d+#', cell('Salida:', +1)));

                            if (preg_match('#(\d+\s+\w+)\s+(\d+:\d+)#', $s, $m)) {
                                return strtotime($m[1] . ', ' . $m[2]);
                            }
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $s = en(re('#\d+\s+\w+\s+\d+:\d+#', cell('Llegada:', +1)));

                            if (preg_match('#(\d+\s+\w+)\s+(\d+:\d+)#', $s, $m)) {
                                return strtotime($m[1] . ', ' . $m[2]);
                            }
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return str_replace('Asiento ', '', nodes('//img[contains(@src, "flightdetailsplane-v1")]/ancestor::table[2]/following-sibling::table[1]//tr/td[2]'));
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
