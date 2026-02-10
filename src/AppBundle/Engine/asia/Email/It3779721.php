<?php

namespace AwardWallet\Engine\asia\Email;

class It3779721 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['Cathay Pacific Airways hat für diesen#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]cathaypacific[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]cathaypacific[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "28.04.2016, 11:09";
    public $crDate = "28.04.2016, 10:56";
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
                        return reni('Buchungsnummer : (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $names = nodes("//*[contains(text(), 'Vielflieger-Programm')]/ancestor::table[1]/tbody[1]/tr/td[1]");

                        return nice($names);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $accs = nodes("//*[contains(text(), 'Vielflieger-Programm')]/ancestor::table[1]/tbody[1]/tr/td[2]");

                        return nice($accs);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Gesamtbetrag : (\w+ [\d.,]+)');

                        return total($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = rew('(\w+ [\d.,]+) (?:\w+ [\d.,]+) Gesamtbetrag :');

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        if (rew('Buchung wurde bestätigt')) {
                            return 'confirmed';
                        }
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Durchgeführt von')]/ancestor::table[1]/tbody[1]/tr");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = node('./td[2]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\b ([A-Z]{3}) \b');

                            return ure("/$q/su", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = strtotime($date);
                            $dt1 = strtotime($time1, $date);
                            $dt2 = strtotime($time2, $date);

                            if ($dt2 < $dt1) {
                                $dt2 = strtotime('+1 day', $dt2);
                            }

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\b ([A-Z]{3}) \b');

                            return ure("/$q/su", 2);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return nice(node('./td[last()-1]'));
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[last()]');
                            $q = white('
								(?P<Cabin> \w.+?)
								\( (?P<BookingClass> \w+) \)
							');
                            $res = re2dict($q, $info);

                            return $res;
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            $x = node('./td[6]');

                            return $x;
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
        return ["de"];
    }

    public function IsEmailAggregator()
    {
        return false;
    }
}
