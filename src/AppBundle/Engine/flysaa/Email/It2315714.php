<?php

namespace AwardWallet\Engine\flysaa\Email;

class It2315714 extends \TAccountCheckerExtended
{
    public $rePlain = "#\n[>\s*]*From\s*:[^\n]*?[@.]flysaa[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]flysaa[.]com#i";
    public $reProvider = "#[@.]flysaa[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 10:18";
    public $crDate = "30.12.2014, 10:08";
    public $xPath = "";
    public $mailFiles = "flysaa/it-2315714.eml";
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
                        return reni('Your reference number is: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('Adult (.+?) \s+ [A-Z]{2} \d+');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total: (\w+ [\d.,]+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Airfare: (\w+ [\d.,]+)');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Taxes: (\w+ [\d.,]+)');

                        return cost($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							FLIGHT DETAILS
							(.+?)
							PASSENGER DETAILS
						');

                        $q = white('[A-Z]{2}\d+ \w{3}');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('^ (\w+\d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w{3}) at');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);
                            $dt = "$date, $time";

                            return totime($dt);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return reni('at .*? (\w{3}) at');
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);
                            $dt = "$date, $time";

                            return totime($dt);
                        },

                        "BookingClass" => function ($text = '', $node = null, $it = null) {
                            return reni('\( (\w) \)');
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
