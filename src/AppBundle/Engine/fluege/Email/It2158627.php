<?php

namespace AwardWallet\Engine\fluege\Email;

class It2158627 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*Von\s*:[^\n]*?[@.]fluege-service[.]de|vielen\s*Dank\s*für\s*Ihre\s*Buchung\s*bei\s*fluege[.]de#i";
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
    public $mailFiles = "fluege/it-2158627.eml, fluege/it-2158903.eml";
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
                        return re_white('
							Buchungsreferenz \/ Airlinecode
							(?:\w+)? \s+ (\w+)
							Hinweise
						');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('
							((?: MR | MRS | MS) \s+ .+?)
							$
						');

                        if (preg_match_all("/$q/imsu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = uberDate(1);
                        $date = totime($date);
                        $this->reserv = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('Abflug Ankunft Gepäck (.+) Ticketnummer\(n\):');
                        $q = white('
							^ [^()]+?
							^ [^()]+?
							[A-Z]+ \d+
							\b[A-Z]\b
							.*?
							\d+:\d+
							\d+:\d+
						');

                        if (preg_match_all("/($q)/imu", $info, $m)) {
                            return $m[1];
                        }
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = re_white('([A-Z]+ \d+) \s+ [A-Z]');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								^ (?P<DepName> .+?)
								^ (?P<ArrName> .+?) $
							');
                            $res = re2dict($q, $text, 'imu');

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = re_white('(\d+[A-Z]+) \d+:\d+');
                            $date = en($date);
                            $year = date('Y', $this->reserv);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $dt1 = "$date $year, $time1";
                            $dt2 = "$date $year, $time2";
                            correctDates($dt1, $dt2);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return re_white('\s+ ([A-Z]) \s+');
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
