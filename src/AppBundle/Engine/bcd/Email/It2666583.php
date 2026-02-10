<?php

namespace AwardWallet\Engine\bcd\Email;

class It2666583 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s*]*From\s*:[^\n]*?[@.]bcdtravel[.]#i', 'blank', ''],
    ];
    public $reHtml = "";
    public $rePDF = [
        ['@bcdtravel.#i', 'blank', '/1'],
    ];
    public $reSubject = "";
    public $reFrom = [
        ['#[@.]bcdtravel[.]#i', 'blank', ''],
    ];
    public $reProvider = "";
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "0";
    public $caseReference = "";
    public $upDate = "13.05.2015, 10:13";
    public $crDate = "13.05.2015, 09:44";
    public $xPath = "";
    public $mailFiles = "bcd/it-2666583.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "0";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $date = orval(
                        uberDate(1),
                        $this->parser->getHeader('date')
                    );
                    $this->anchor = totime($date);

                    $text = $this->setDocument('application/pdf', 'text');

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        return reni('\( PNR \) : (\w+)');
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        $q = white('Traveler \d+ : ([a-z].+?) \n');

                        if (preg_match_all("/$q/isu", $text, $m)) {
                            return nice($m[1]);
                        }
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Flight Total : ([\d.,]+ \w+)');

                        return total($x);
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Base Airfare : ([\d.,]+ \w+)');

                        return cost($x);
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        $x = rew('Taxes and Fees : ([\d.,]+ \w+)');

                        return cost($x);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        $q = white('\w+ , \w+ \d+
							\d+:\d+
						');

                        return splitter("/($q)/isu");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return reni('Flight (\d+)');
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $airline = reni('Fare Rules (.+?) Operated by');

                            return $airline;
                        },

                        "Operator" => function ($text = '', $node = null, $it = null) {
                            $airline = reni('Operated by (.+?) Flight \d+');

                            return $airline;
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            $q = white('\( (\w+) \)');

                            return ure("/$q/isu", 1);
                        },

                        "DepDate" => function ($text = '', $node = null, $it = null) {
                            $date = uberDate(1);
                            $time1 = uberTime(1);
                            $time2 = uberTime(2);

                            $date = date_carry($date, $this->anchor);

                            $dt1 = strtotime($time1, $date);
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

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return reni('Class: (\w.+?) \n');
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return reni('Seat (\w+)');
                        },

                        "FlightLocator" => function ($text = '', $node = null, $it = null) {
                            $air = reni('Fare Rules (.+?) Operated by');

                            return reni("$air : (\w+)", $this->text());
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
