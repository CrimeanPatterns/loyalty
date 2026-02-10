<?php

namespace AwardWallet\Engine\dividendmiles\Email;

class It19 extends \TAccountCheckerExtended
{
    public $rePlain = [
        ['#\n[>\s]*From\s*:[^\n]*Your US Airways#i', 'blank', ''],
    ];
    public $reHtml = [
        ['#For\s+information\s+about\s+our\s+privacy\s+policy\s+visit\s*<[^>]+>usairways#i', 'blank', '-1000'],
    ];
    public $rePDF = "";
    public $reSubject = [
        ['#Your US Airways#i', 'us', ''],
    ];
    public $reFrom = [
        ['#\busairways\b|\bmyusairways\b#i', 'us', ''],
    ];
    public $reProvider = [
        ['#\busairways\b|\bmyusairways\b#i', 'us', ''],
    ];
    public $fnLanguage = "";
    public $langSupported = "en";
    public $typesCount = "1";
    public $isAggregator = "";
    public $caseReference = "";
    public $upDate = "03.03.2015, 22:33";
    public $crDate = "";
    public $xPath = "";
    public $mailFiles = "dividendmiles/it-10.eml, dividendmiles/it-11.eml, dividendmiles/it-14.eml, dividendmiles/it-16.eml, dividendmiles/it-17.eml, dividendmiles/it-18.eml, dividendmiles/it-19.eml, dividendmiles/it-1917852.eml, dividendmiles/it-20.eml, dividendmiles/it-2513100.eml, dividendmiles/it-2521724.eml, dividendmiles/it-2522867.eml";
    public $re_catcher = "#.*?#";
    public $pdfRequired = "";

    public function processors()
    {
        return [
            "#.*?#" => [
                "ItinerariesSplitter" => function ($text = '', $node = null, $it = null) {
                    $this->date = strtotime($this->parser->getHeader("date"));

                    return [$text];
                },

                "#.*?#" => [
                    "Kind" => function ($text = '', $node = null, $it = null) {
                        return "T";
                    },

                    "RecordLocator" => function ($text = '', $node = null, $it = null) {
                        $r = orval(
                            node("//*[contains(text(), 'Confirmation code:')]/ancestor-or-self::td[1]"),
                            node("//*[contains(text(), 'Confirmation code')]/ancestor::table[1]/following-sibling::table[contains(.,' ')][1]")
                        );

                        return re("#\b([A-Z\d\-]{5,})\b#", $r);
                    },

                    "Passengers" => function ($text = '', $node = null, $it = null) {
                        return nodes("//*[contains(text(), 'Passenger name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[text() and position()=(count(//*[contains(text(), 'Passenger name')]/ancestor-or-self::td[1]/preceding-sibling::td)+1)]");
                    },

                    "AccountNumbers" => function ($text = '', $node = null, $it = null) {
                        return nice(clear("#Â#", implode(',', nodes("//*[contains(text(), 'Passenger name')]/ancestor-or-self::tr[1]/following-sibling::tr/td[text() and position()=(count(//*[contains(text(), 'Passenger name')]/ancestor-or-self::td[1]/preceding-sibling::td)+2)]"))));
                    },

                    "TotalCharge" => function ($text = '', $node = null, $it = null) {
                        return cost(orval(
                            re("#You paid [.,\d]+ miles \+ ([^\n]+)#ix"),
                            re("#\n\s*Total\s+([^\s\d]*?[\d.,]+)#i"),
                            cell(["Total fare", "Total Fare"], +1)
                        ));
                    },

                    "BaseFare" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#\n\s*Fare\s+total\s+([^\n]+)#i"));
                    },

                    "Currency" => function ($text = '', $node = null, $it = null) {
                        return currency(re("#Taxes and fees\s+([^\n]+)#ix"));
                    },

                    "Tax" => function ($text = '', $node = null, $it = null) {
                        return cost(re("#Taxes and fees\s+([^\s]+)#"));
                    },

                    "SpentAwards" => function ($text = '', $node = null, $it = null) {
                        return re("#You paid ([\d.,]+\s*miles)#ix");
                    },

                    "Status" => function ($text = '', $node = null, $it = null) {
                        return re("#You're\s+(\w+)#");
                    },

                    "ReservationDate" => function ($text = '', $node = null, $it = null) {
                        return strtotime(re("#Date issued:\s*([^\n]+)#i"), $this->date);
                    },

                    "SegmentsSplitter" => function ($text = '', $node = null, $it = null) {
                        return xpath("//*[contains(text(), 'FLIGHT#') or contains(text(), 'FLIGHT #')]/ancestor::tr[following-sibling::tr[contains(., 'DEPART')]][1]");
                    },

                    "TripSegments" => [
                        "FlightNumber" => function ($text = '', $node = null, $it = null) {
                            return re("#FLIGHT[\s\#]*(\d+)#");
                        },

                        "DepCode" => function ($text = '', $node = null, $it = null) {
                            text(xpath("following-sibling::tr[contains(., 'DEPART')][1]"));
                            $depTime = re("#\s*DEPART[^\w\d]*(\d+:\d+\s*\w{2})[^\w\d]*(\b[A-Z]{3}\b)#ms", xpath("following-sibling::tr[contains(., 'DEPART')][1]"));
                            $depCode = re(2);

                            $arrTime = re("#ARRIVE[^\w\d]*(\d+:\d+\s*\w{2})[^\w\d]*(\b[A-Z]{3}\b)#ms", xpath("following-sibling::tr[contains(., 'ARRIVE')][1]"));
                            $arrCode = re(2);

                            // common date
                            $baseDate = re("#(?:^|\n)\s*\w+,\s*(\w+\s*\d+,\s*\d+)\s*$#s", xpath("preceding::tr[string-length(normalize-space(.))>1][1]"));

                            if (!$baseDate) {
                                $baseDate = isset($this->lastDate) ? date('Y-m-d', $this->lastDate) : null;
                            }

                            if ($baseDate) {
                                $baseDate = date('Y-m-d', strtotime($baseDate, $this->date));
                            } // standartize

                            $noCorrection = false;

                            if (re("#\n\s*Flight[\#\s]+{$it['FlightNumber']}[:\s]+Departs next day[\s,]+([^\n]+)#ix", $this->text())) {
                                $baseDate = re(1);
                                $noCorrection = true;
                            }

                            $depDate = $baseDate . ', ' . $depTime;

                            if (re("#\n\s*Flight[\#\s]+{$it['FlightNumber']}[:\s]+Arrives next day[\s,]+\w+,\s*(\w+\s+\d+,\s*\d+)#ix", $this->text())) {
                                $baseDate = nice(re(1));
                                $noCorrection = true;
                            }

                            $arrDate = $baseDate . ', ' . $arrTime;

                            if ($noCorrection) {
                                $depDate = strtotime($depDate, $this->date);
                                $arrDate = strtotime($arrDate, $this->date);
                            } else {
                                correctDates($depDate, $arrDate);
                            }

                            $this->lastDate = $arrDate;

                            return [
                                'DepCode' => $depCode,
                                'ArrCode' => $arrCode,
                                'DepDate' => $depDate,
                                'ArrDate' => $arrDate,
                            ];
                        },

                        "AirlineName" => function ($text = '', $node = null, $it = null) {
                            $full = re("#Operated by\s*(.+)#i");

                            return trim(preg_replace("/flight\s*\#\s*\d+$/", "", $full));
                        },

                        "Aircraft" => function ($text = '', $node = null, $it = null) {
                            $text = clear("#Â#", text(xpath("following-sibling::tr[contains(., 'AIRCRAFT')][1]")));

                            return (re("#AIRCRAFT\s*([^\n]+)#", $text) == 'ARRIVE') ? null : re(1);
                        },

                        "Cabin" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\s*CABIN\s+([^\n]+)#", clear("#Â#", text(xpath("following-sibling::tr[contains(., 'CABIN')][1]")))), "-\n");
                        },

                        "Seats" => function ($text = '', $node = null, $it = null) {
                            return nice(clear("#\s+#", re("#\s+SEATS\s+((?:\d+[A-Z]+\s*)+)#", clear("#Â#", text(xpath("following-sibling::tr[contains(., 'SEATS')][1]")))), ','));
                        },

                        "Duration" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#TRAVEL TIME\s*(\d+\w+(?:\s*\d+\w+)*)#", clear("#Â#", text(xpath("following-sibling::tr[contains(., 'TRAVEL TIME')][1]")))), '-');
                        },

                        "Meal" => function ($text = '', $node = null, $it = null) {
                            return trim(re("#\s*MEAL\s*(.*?)\s+SEATS#", clear("#Â#", text(xpath("following-sibling::tr[contains(., 'MEAL')][1]")))), " -\n");
                        },

                        "Stops" => function ($text = '', $node = null, $it = null) {
                            return re("#Stop:#", xpath("following::tr[contains(., 'Stop:') and position()<12][1]")) ? 1 : 0;
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
