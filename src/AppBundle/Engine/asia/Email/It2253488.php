<?php

namespace AwardWallet\Engine\asia\Email;

class It2253488 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]cathaypacific[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]cathaypacific[.]com#i";
    public $reProvider = "#[@.]cathaypacific[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "29.12.2014, 06:54";
    public $crDate = "26.12.2014, 09:49";
    public $xPath = "";
    public $mailFiles = "asia/it-2253488.eml";
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
                        return reni('ELECTRONIC TICKET NUMBER : ([\w-]+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return [reni('NAME OF PASSENGER: (.+?) \n')];
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('TOTAL : (\w+ [\d.,]+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = reni('FARE : (\w+ [\d.,]+)');

                        return cost($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('DATE/PLACE OF TICKET ISSUANCE: (\d+ \w+ \d+)');
                        $date = totime($date);
                        $this->anchor = $date;

                        return $date;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							TICKET DETAILS
							(.+?)
							TOTAL :
						');

                        return splitter('/(\s+DEP\s+)/iu', $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('\d+ \s+ ([a-z]{2} \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            return reni('DEP (.+?) \d+\w+');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('DEP .+?
								(\d+\w+)
							');
                            $date = date_carry($date, $this->anchor);

                            $q = white('DEP .*? \w+ \s+
								(?P<h> \d{2}) (?P<i> \d{2}) \s+
							');

                            if (!preg_match("/$q/isu", $text, $m)) {
                                return;
                            }
                            $time = "${m['h']}:${m['i']}";

                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrName" => function ($text = '', $node = null, $it = null) {
                            return reni('ARR (.+?) \d+\w+');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('ARR .+?
								(\d+\w+)
							');
                            $date = date_carry($date, $this->anchor);

                            $q = white('ARR .*? \w+ \s+
								(?P<h> \d{2}) (?P<i> \d{2}) \s+
							');

                            if (!preg_match("/$q/isu", $text, $m)) {
                                return;
                            }
                            $time = "${m['h']}:${m['i']}";

                            $dt = strtotime($time, $date);

                            return $dt;
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            return reni('ARR .*?
								\d{4} (\d+\w+)
							');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            $q = white('\s+
								(?P<Cabin> \w+) -
								(?P<BookingClass> \w)
							');
                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            return reni('BOOKING REFERENCE: (\w+)');
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
