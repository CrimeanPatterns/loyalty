<?php

namespace AwardWallet\Engine\asia\Email;

class It2306624 extends \TAccountCheckerExtended
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
    public $upDate = "29.12.2014, 06:57";
    public $crDate = "29.12.2014, 06:19";
    public $xPath = "";
    public $mailFiles = "asia/it-2306624.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = $this->parser->getHeader('date');
                    $date = totime(uberDateTime($date));
                    $this->anchor = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('Your confirmation number for this booking is: (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							Passenger Information
							(.+?)
							Note:
						');
                        $q = white('> (.+?),');

                        if (preg_match_all("/$q/iu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = reni('([\d.,]+) The fare quoted');

                        return total($x);
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $s = reni('Total \( (\w+) \)');

                        return currency($s);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = re_white('
							Flight Information
							(.+?)
							Passenger Information
						');

                        return splitter('/(>)/iu', $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('(\w+\d+) \w+ Class');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return reni('departing .*? \( (\w+) \)');
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $date = date_carry($date, $this->anchor);

                            $time1 = uberTime(1);
                            $time2 = uberTime(2);
                            $dt1 = date_carry($time1, $date);
                            $dt2 = date_carry($time2, $dt1);

                            return [
                                'DepDate' => $dt1,
                                'ArrDate' => $dt2,
                            ];
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return reni('arriving .*? \( (\w+) \)');
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('(\w+) Class');
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
