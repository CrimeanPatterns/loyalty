<?php

namespace AwardWallet\Engine\brussels\Email;

class It2315711 extends \TAccountCheckerExtended
{
    public $rePlain = "#Thank\s+you\s+for\s+using\s+www[.]brusselsairlines[.]com#i";
    public $rePlainRange = "";
    public $reHtml = "";
    public $reHtmlRange = "";
    public $rePDF = "";
    public $rePDFRange = "";
    public $reSubject = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $reFrom = "#[@.]brusselsairlines[.]com#i";
    public $reProvider = "#[@.]brusselsairlines[.]com#i";
    public $caseReference = "";
    public $isAggregator = "0";
    public $upDate = "30.12.2014, 10:05";
    public $crDate = "30.12.2014, 09:45";
    public $xPath = "";
    public $mailFiles = "brussels/it-2315711.eml";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = uberDate(1);
                    $date = totime($date);
                    $this->anchor = $date;

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('booking reference number is (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							Passengers:
							(.+?)
							Total miles
						');

                        $q = white('(?: Mrs | Mr | Ms) (.+?) \n');

                        if (preg_match("/$q/isu", $info, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total taxes charged: (\w+ [\d.,]+)');

                        return currency($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = reni('Total taxes charged: (\w+ [\d.,]+)');

                        return cost($x);
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return reni('Total miles charged: (.+?) \n');
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $info = rew('
							Thank you for using
							(.+?)
							Your booking reference
						');

                        $q = white('[A-Z]{2} \s+ \d+ \s+');

                        return splitter("/($q)/isu", $info);
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $fl = reni('(\w+ \d+)');

                            return uberAir($fl);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "DepName" => function ($text = '', $node = null, $it = null) {
                            $q = white('
								\d+ \w+
								(?P<DepName> .+?) -
								(?P<ArrName> .+?) \n
							');
                            $res = re2dict($q, $text);

                            return $res;
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('dep\. (\d+ \w+)');
                            $date = date_carry($date, $this->anchor);

                            $time = uberTime(1);

                            return strtotime($time, $date);
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            return TRIP_CODE_UNKNOWN;
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = reni('arr\. (\d+ \w+)');
                            $date = date_carry($date, $this->anchor);

                            $time = uberTime(2);

                            return strtotime($time, $date);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('arr\. .*? \s+ (\w+)');
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
