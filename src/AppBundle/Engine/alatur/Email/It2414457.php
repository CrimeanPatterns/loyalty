<?php

namespace AwardWallet\Engine\alatur\Email;

class It2414457 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*De\s*:[^\n]*?[@.]alatur[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "pt";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.01.2015, 08:09";
    public $crDate = "28.01.2015, 07:34";
    public $xPath = "";
    public $mailFiles = "alatur/it-2414457.eml";
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
                        $conf = node("//*[contains(text(), 'LOCALIZADOR')]
							/following::tr[1]/td[last() - 1]");

                        return nice($conf);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[normalize-space(text()) = 'NOME']/ancestor::tr[1]
							/following-sibling::tr/td[2]");

                        return nice($ppl);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'TOTAL')]
							/following::tr[1]/td[last()]");

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'TOTAL')]
							/following::tr[1]/td[3]");

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = node("//*[contains(text(), 'TOTAL')]
							/following::tr[1]/td[4]");

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = node("//*[contains(text(), 'LOCALIZADOR')]
							/following::tr[1]/td[last()]");

                        return timestamp_from_format($date, 'd / m / Y|');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Bagagem:')]/preceding::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[4]'));
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/i", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);
                            $date = timestamp_from_format($date, 'd / m / Y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w{3}) \)');

                            return ure("/$q/i", 2);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);
                            $date = timestamp_from_format($date, 'd / m / Y|');
                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[3]'));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::tr[1]');

                            return reni('Eqpt[.]: (\w+)', $info);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::tr[1]');

                            return reni('Classe: (\w)\b', $info);
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[last()]'));
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
        return ["pt"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
