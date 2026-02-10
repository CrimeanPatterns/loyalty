<?php

namespace AwardWallet\Engine\fluege\Email;

class It2128737 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Von\s*:[^\n]*?[@.]fluege-service[.]de#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $reFrom = "#[@.]fluege-service[.]de#i";
    public $reProvider = "#[@.]fluege-service[.]de#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $xPath = "";
    public $mailFiles = "fluege/it-2128737.eml, fluege/it-5897111.eml, fluege/it-5897166.eml";
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
                        return re_white('Buchungscode: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							((?: MR | MRS | MS) \s+ .+?)
							\d{7,}
						');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Summe Preis \+ Tax:  ([\d.]+)');

                        return cost($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Summe: ([\d.]+)');

                        return cost($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return re_white('Alle Angaben in (\w{3})\b');
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('([\d.]+) Summe Preis');

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = re_white('
							, (\d+ [.] \d+ [.] \d+)
							Rechnung
						');

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('Abflug  Ankunft (.+?) Reiseteilnehmer:');
                        $q = white('
							\d+ [.] \d+ [.] \d{2}  .+? \n
							.+? \n
							\d+:\d+
							\d+:\d+
						');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('([A-Z\d]{2} \d+) \s+ [A-Z]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },
                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								[\.]\d+ \s+ (?P<DepName>.+?)\s*(?:\((?P<DepCode>[A-Z]{3})\))? \n
								(?P<ArrName>.+?)\s*(?:\((?P<ArrCode>[A-Z]{3})\))? \n
							');
                            $res = re2dict($q, $text);

                            if (empty($res['DepCode'])) {
                                $res['DepCode'] = TRIP_CODE_UNKNOWN;
                            }

                            if (empty($res['ArrCode'])) {
                                $res['ArrCode'] = TRIP_CODE_UNKNOWN;
                            }

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('(\d+ [.] \d+ [.] \d+)');

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = timestamp_from_format($date, 'd.m.y');
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

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re_white('([A-Z]{1,2}) \d+:\d+');
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
