<?php

namespace AwardWallet\Engine\adirect\Email;

class It2748762 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]airline-direct[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]airline-direct[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "21.05.2015, 14:26";
    public $crDate = "21.05.2015, 14:08";
    public $xPath = "";
    public $mailFiles = "adirect/it-2748762.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Buchungscode: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('\b(?: MR | MRS | MS)\b (.+?) \d+');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Summe Preis \+ Tax : ([\d.,]+)');

                        return cost($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return reni('Alle Angaben in (\w+)');
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('([\d.]+)  Buchungscode');

                        return timestamp_from_format($date, 'd . m . Y|');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Datum (.+)');
                        $q = white(' \d+[.]\d+[.]\d+ ');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('\b(\w{2} \s+ \d+\b)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w+) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('( \d+[.]\d+[.]\d+ )');
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = timestamp_from_format($date, 'd . m . y|');
                            $dt1 = strtotime($time1, $dt1);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w+) \)');

                            return ure("/$q/isu", 2);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('\b([A-Z])\b');
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
