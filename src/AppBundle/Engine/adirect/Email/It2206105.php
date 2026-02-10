<?php

namespace AwardWallet\Engine\adirect\Email;

class It2206105 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*(?:From|Von)\s*:[^\n]*?[@.]airline[-]direct[.]de#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]airline[-]direct[.]de#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]airline[-]direct[.]de#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "21.05.2015, 14:00";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "adirect/it-2206105.eml, adirect/it-2212236.eml, adirect/it-2748749.eml";
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
                        return rew('(?: Reservierungsnummer | Buchungscode ) : ([\w]+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("//*[contains(text(), 'Erwachsener:')]/following::td[1]");
                        $ppl = array_map(function ($x) {
                            return re_white('^ (.+?) \(', $x);
                        }, $ppl);

                        return nice($ppl);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('GESAMTPREIS		([\d.,]+ .?)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Flugpreis  (\d.+?) \n');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Steuern  (\d.+?) \n');

                        return cost($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							\w+ , \d+[.]\d+[.] .+? \( [A-Z]{3} \) .+?
							\( [A-Z]{3} \)
						');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('
								\d:\d+
								.*? \( (.+?) \)
							');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('
								\( ([A-Z]+) \)
							');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = rew('(\d+[.]\d+[.]\d+)');
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = strtotime($date);
                            $dt1 = strtotime($time1, $dt1);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('
								\( [A-Z]+ \) .+?
								\( ([A-Z]+) \)
							');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $s = re_white('
								\d:\d+
								.*?	\( .*? \)
								(.+?) \n
							');

                            return nice($s);
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
