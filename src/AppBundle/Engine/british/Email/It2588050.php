<?php

namespace AwardWallet\Engine\british\Email;

class It2588050 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]ba[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
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
    public $upDate = "15.10.2015, 13:59";
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
                    return null; // covered by 768.php
                    // block it-2793387
                    if (strpos($text, 'Hotel') !== false) {
                        return null;
                    }

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Booking reference: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[normalize-space(text()) = 'Passenger']/following::td[1]");

                        return nice($ppl);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = cell('Payment Total', +1);

                        return total($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (stripos($text, "| Confirmed") !== false) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $xpath = xpath("//*[contains(text(), '| Confirmed')]/ancestor::tr[3]/following-sibling::*[1]/td/table//tr[5]/td[contains(text(), '-')]/ancestor::table[3]");

                        if ($xpath->length == 0) {
                            $xpath = xpath("//*[contains(text(), ' | ')]/ancestor::tr[3]/following-sibling::*[1]/td/table[contains(., ':') and contains(., '(')]/ancestor::table[1]");
                        }

                        return $xpath;
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('^ (\w+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ \s+
								(\w.+?) $
							');

                            return ure("/$q/imu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = nice(uberDateTime(1));

                            if (!totime($date)) {
                                $date = clear("#\s*[APM]{2}$#i", $date);
                            }

                            return totime($date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ \s+
								(\w.+?) $
							');

                            return ure("/$q/imu", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = nice(uberDateTime(2));

                            if (!totime($date)) {
                                $date = clear("#\s*[APM]{2}$#i", $date);
                            }

                            return totime($date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('\| (.+?) \|');
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
