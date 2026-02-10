<?php

namespace AwardWallet\Engine\adirect\Email;

class It2211977 extends \TAccountCheckerExtended
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
    public $upDate = "21.05.2015, 14:03";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "adirect/it-2211977.eml, adirect/it-2748757.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'complex');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return re_white('(\w+)	(?: \( \w+ \) )? Hinweise');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							Reisende
							(.+?)
							Ihr Reisebüro
						');

                        $q = white('^ (\w.*?) $');

                        if (preg_match_all("/$q/imu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('Ticketdatum .*? (\d+[.]\d+[.]\d+)');
                        $date = totime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('Von:');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('\b(\w{2} \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('
								\( ([A-Z]+) \)
							');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('(\d+ \w+)		\d+:\d+');
                            $date = en($date);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $year = date('Y', $this->anchor);
                            $dt1 = "$date $year, $time1";
                            $dt2 = "$date $year, $time2";
                            correctDates($dt1, $dt2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return re_white('
								\( [A-Z]+ \) .*?
								\( ([A-Z]+) \)
							');
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re_white('\s+ (\w) \s+ .*? \d+:\d+');
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
