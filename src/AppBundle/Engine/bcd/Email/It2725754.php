<?php

namespace AwardWallet\Engine\bcd\Email;

class It2725754 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]bcd[.]#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['BCD Travel#i', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = "";
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "19.05.2015, 11:19";
    public $crDate = "19.05.2015, 10:15";
    public $xPath = "";
    public $mailFiles = "bcd/it-2725754.eml";
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
                        return reni('Booking Reference: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Passengers (.+?) Flight');
                        $q = white('\d+[.] (.+?) $');

                        if (preg_match_all("/$q/imu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Total (.[\d.,]+) Paid by');

                        return total($x);
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $date = reni('Date: (.+? \d{4})');

                        return totime($date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('Flight \# (.+?) Charges');
                        $q = white('\b\w{2} \s+ \d+ \b\w\b');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('^ (.+?) \b\w\b');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('\d+:\d+ (?:PM|AM)
								(?P<DepName> [A-Z].+?) \s+ - \s+
								(?P<ArrName> [A-Z].+?) $
							');
                            $res = re2dict($q, $text, 'imu');

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $date = timestamp_from_format($date, 'd / m / Y|');

                            return strtotime($time, $date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $date = timestamp_from_format($date, 'd / m / Y|');

                            return strtotime($time, $date);
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
