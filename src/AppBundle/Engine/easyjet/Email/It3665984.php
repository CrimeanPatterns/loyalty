<?php

namespace AwardWallet\Engine\easyjet\Email;

class It3665984 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['easyJet stellt keine Flugtickets aus#i', 'blank', '/1'],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]easyjet[.]#i', 'blank', ''],
    ];
    public $reProvider = [
        ['#[@.]easyjet[.]#i', 'blank', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "de";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "30.03.2016, 12:37";
    public $crDate = "25.03.2016, 11:43";
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
                        return reni('danke für Ihre Buchung (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = text(xpath("(//*[contains(text(), 'Sitzplatz automatisch')])[1]/ancestor::table[1]"));
                        $q = white('(Hr\. [A-Z\s]+?) \n');

                        if (preg_match_all("/$q/isu", $info, $m)) {
                            $names = nice($m[1]);
                            $names = array_values(array_unique($names));

                            return $names;
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Zahlung von (.+?) durch');

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = rew('Zahlung von .+? am (.+? \d{4})');
                        $date = strtotime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Der Check-In schließt')]/ancestor::tr[2]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								(?P<AirlineName> [A-Z]{3} | [A-Z]\w)
								(?P<FlightNumber> \d+)
								Abgehende
							');
                            $res = re2dict($q, text($text));

                            return $res;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('^ (?P<DepName> .+?) nach (?P<ArrName> .+?) \n');
                            $res = re2dict($q, text($text));

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $date = en($date);
                            $date = strtotime($date, $this->anchor);
                            $dt = strtotime($time, $date);

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $date = en($date);
                            $date = strtotime($date, $this->anchor);
                            $dt = strtotime($time, $date);

                            if ($dt < $this->anchor) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
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
