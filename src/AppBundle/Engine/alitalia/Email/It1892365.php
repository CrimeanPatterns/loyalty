<?php

namespace AwardWallet\Engine\alitalia\Email;

class It1892365 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?alitalia#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = [
        ['#ALITALIA\s+ELECTRONIC\s+TICKET\s+RECEIPT#', 'blank', ''],
    ];
    public $reFrom = [
        ['#alitalia#i', 'us', ''],
    ];
    public $reProvider = [
        ['#alitalia#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "it";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "10.06.2015, 12:52";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $r = nodes("//*[contains(text(), 'Passeggero / Passenger')]/ancestor-or-self::td[1]/following-sibling::td[1]//text()");

                        if (sizeof($r) < 3) {
                            return;
                        }

                        return [
                            'RecordLocator'  => re("#^([A-Z\d\-]{5,})$#", $r[1]),
                            'Passengers'     => [$r[0]],
                            'AccountNumbers' => (sizeof($r) == 4) ? re("#^([A-Z\d\-]{5,})$#", $r[3]) : null,
                        ];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $s = nodes('//*[contains(text(), "Total*:")]/ancestor-or-self::td[1]/following-sibling::td[1]//text()');
                        $costs = array_values(array_filter($s));

                        if (count($costs) == 4) {
                            return [
                                'BaseFare'    => cost($costs[0]),
                                'Tax'         => cost($costs[1]),
                                'TotalCharge' => cost($costs[3]),
                                'Currency'    => currency($costs[3]),
                            ];
                        }

                        return null;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Partenza - data')]/ancestor::tr[1]/following-sibling::tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return uberAir(node("td[5]"));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return node("td[1]");
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(en(uberDateTime(node("td[3]"))), $this->date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return node("td[2]");
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            return strtotime(en(uberDateTime(node("td[4]"))), $this->date);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return node("td[6]");
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
        return ["it"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
