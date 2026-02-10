<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class It2160306 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]myusairways[.]com#i', 'us', ''],
    ];
    public $reHtml = "";
    public $rePDF = "";
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]myusairways[.]com#i', 'us', ''],
    ];
    public $reProvider = [
        ['#[@.]myusairways[.]com#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "02.03.2015, 23:23";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "dividendmiles/it-1.eml, dividendmiles/it-2160306.eml, dividendmiles/it-2521621.eml";
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
                        return re_white('Travel confirmation:  (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $ppl = nodes("
							//*[contains(text(), 'Party of')]/ancestor::tr[1]
							/following-sibling::tr/td[1]
						");

                        return nice($ppl);
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        $nums = nodes("
							//*[contains(text(), 'Party of')]/ancestor::tr[1]
							/following-sibling::tr/td[2]
						");
                        $nums = filter($nums);

                        return implode(',', $nums);
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Grand Total  (.[\d,.]+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Fare  (.[\d,.]+)');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = re_white('Taxes & Fees  (.[\d,.]+)');

                        return cost($x);
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#your reservation has been ([^.\n]+)#ix", $this->text());
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        $dt = re_white('
							Grand total
							\w+
							(.+? (?:PM|AM))
						');

                        $dt = clear('/at/', $dt);
                        $dt = strtotime($dt);
                        $this->reserv = $dt;

                        return $dt;
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'Meal:')]/ancestor::tr[1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            $num = node('./td[2]');

                            return nice($num);
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $info = node('td[3]');

                            return re_white('([A-Z]{3})\/?', $info);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time = uberTime(1);

                            $year = date('Y', $this->reserv);
                            $dt = "$date $year, $time";
                            $dt = strtotime($dt);

                            if ($dt < $this->reserv) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "ArrCode" => function ($text = '', $node = null, $it = null) {
                            $info = node("td[4]");

                            return re_white('([A-Z]{3})\/?', $info);
                        },

                        "ArrDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(2);
                            $time = uberTime(2);

                            $year = date('Y', $this->reserv);
                            $dt = "$date $year, $time";
                            $dt = strtotime($dt);

                            if ($dt < $this->reserv) {
                                $dt = strtotime('+1 year', $dt);
                            }

                            return $dt;
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $info = node('./following::tr[1]');
                            $name = re_white('by (.+) $', $info);

                            return nice($name);
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $info = node('./td[3]');
                            $x = re_white(', \w+ (.+) $', $info);

                            if (re("#Determined#i", $x)) {
                                $x = "";
                            }

                            return nice($x);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return nice(re_white('Class: (.+)'));
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return re("#\n\s*Meal\s*:\s*([^\n]*?)\s{2,}#");
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
