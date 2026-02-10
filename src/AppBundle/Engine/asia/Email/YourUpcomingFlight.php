<?php

namespace AwardWallet\Engine\asia\Email;

use AwardWallet\Engine\MonthTranslate;

class YourUpcomingFlight extends \TAccountChecker
{
    public $mailFiles = "asia/it-10428250.eml, asia/it-11096729.eml, asia/it-38885278.eml, asia/it-56521644.eml, asia/it-56546416.eml";
    public $reFrom = "@notification.cathaypacific.com";
    public $reSubject = [
        "en"  => "Your upcoming flight",
        "en2" => "Your booking has been cancelled",
        "zh"  => "您將乘搭的下一個航班為",
    ];
    public $reBody = 'Cathay Pacific';
    public $reBody2 = [
        "en" => ["Where you should go", 'Your cancelled itinerary'],
        "zh" => ["航班資訊", "您已取消的行程", '您的預訂已取消'],
    ];

    public static $dictionary = [
        "en" => [
            'Hello'             => ['Hello', 'Dear'],
            'Booking reference' => ['Booking reference', 'Booking reference:'],
            //            'Cabin class' => '',
            //            'Class' => '',
            //            'Your booking has been cancelled' => '',
            //            'Passengers' => '',
            //            'Name' => '',
            //            'Arrives' => '',
        ],
        "zh" => [
            'Hello'                           => ['您好,', '親愛的'],
            'Booking reference'               => ['預訂參考編號:', '預訂參考編號：'],
            'Departure'                       => "出發",
            'Terminal'                        => '客運大樓',
            'Operated by'                     => '營運航空公司',
            'to'                              => ['前往', '至'],
            'Cabin class'                     => '客艙級別',
            'Class'                           => '座位等级',
            'Your booking has been cancelled' => '您的預訂已取消',
            'Passengers'                      => '乘客',
            'Name'                            => '姓名',
            //            'Arrives' => '',
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Booking reference"));

        // TripNumber
        // Passengers

        $passengers = $this->http->FindNodes("//text()[" . $this->eq($this->t("Passengers")) . "]/following::text()[" . $this->eq($this->t("Name")) . "]/ancestor::tr[1]/following-sibling::tr//text()[normalize-space()]");

        if (!empty($passengers)) {
            $it['Passengers'] = $passengers;
        } else {
            $pax = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Hello"))}]/ancestor::td[1]", null, "#{$this->opt($this->t('Hello'))}\s*(.+)#"));

            if (empty($pax)) {
                $pax = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Hello"))}]/ancestor::td[1]", null, true, "#{$this->opt($this->t('Hello'))}\s*(.+)#");
            }

            $pax = preg_replace('/^\s*passenger$/i', '', $pax);

            if (!empty($pax)) {
                $it['Passengers'] = (array) $pax;
            }
        }
        //text()[starts-with(normalize-space(.),"Dear")]
        // TicketNumbers
        // AccountNumbers
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // Cancelled
        if (!empty($this->http->FindSingleNode("//text()[" . $this->contains($this->t("Your booking has been cancelled")) . "]"))) {
            $it['Status'] = 'Cancelled';
            $it['Cancelled'] = true;
        }
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[2]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $itsegment = [];

            unset($dateStr);

            $xpathTrTable = "(self::tr or self::table)";

            $airName = $flightNo = $depName = $arrName = $dateStr = null;
            $flight = implode(' ', $this->http->FindNodes("ancestor-or-self::*[ {$xpathTrTable} and preceding-sibling::*[{$xpathTrTable} and normalize-space()] ][1]/preceding-sibling::*[{$xpathTrTable} and normalize-space()][1]/descendant::text()[normalize-space()]", $root));
            // CX 419 Seoul to Hong Kong
            $re1 = "(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s+(?<depName>.{3,})\s+{$this->opt($this->t('to'))}\s+(?<arrName>.{3,})";
            // CX 976 Manila to Hong Kong on Wed, 29 May 2019
            $re2 = $re1 . "\s+{$this->opt($this->t('on'))}\s+(?<date>.{6,})";
            // Sat, 04 Jan 2020 CX 254 From London, Heathrow to Hong Kong; Sun, 12 Apr 2020 | CX 746 Bahrain to Dubai
            $re3 = "(?<date>.{6,})\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\|?\s*(?<number>\d+)\s+{$this->opt($this->t('From'))}\s+(?<depName>.{3,})\s+{$this->opt($this->t('to'))}\s+(?<arrName>.{3,})";
            // Sun, 12 Apr 2020 | CX 746 Bahrain to Dubai, 2020年5月15日 周五 | CX 564 台北 至 大阪
            $re4 = "(?<date>.{6,}?)\s*\|?\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)\s+(?<depName>.+)\s+{$this->opt($this->t('to'))}\s+(?<arrName>.+)";

            if (preg_match('/^' . $re4 . '$/u', $flight, $m)
                || preg_match('/^' . $re3 . '$/u', $flight, $m)
                || preg_match('/^' . $re2 . '$/u', $flight, $m)
                || preg_match('/^' . $re1 . '$/u', $flight, $m)
            ) {
                $airName = $m['name'];
                $flightNo = $m['number'];
                $depName = $m['depName'];
                $arrName = $m['arrName'];

                if (!empty($m['date'])) {
                    $dateStr = $m['date'];
                }
            }

            // FlightNumber
            if (empty($flightNo)) {
                $flightNo = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[2]", $root, true, "#^(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})#");
            }

            if (!empty($flightNo)) {
                $itsegment['FlightNumber'] = $flightNo;
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            if (empty($depName)) {
                $depName = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[1]", $root, true, "#^From\s(.+)\sto\s#");
            }

            if (empty($depName)) {
                $depName = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[1]", $root, true, "#^(.+)\s" . $this->opt($this->t("to")) . "\s#");
            }

            if (!empty($depName)) {
                $itsegment['DepName'] = $depName;
            }
            // DepartureTerminal
            if ($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Terminal"))}]/ancestor::td[1]", $root)) {
                $cnt = $this->http->XPath->query(".//text()[{$this->eq($this->t("Terminal"))}]/ancestor::td[1]/preceding-sibling::td", $root)->length + 1;
                $terminal = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Terminal")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[{$cnt}]", $root);

                if (!empty($terminal)) {
                    $itsegment['DepartureTerminal'] = $terminal;
                }
            }

            // DepDate
            // ArrDate
            $date = 0;

            if (isset($dateStr)) {
                $date = $this->normalizeDate($dateStr);
            } else {
                $date = $this->date;
            }

            if (!empty($date)) {
                if ($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Departure"))}]/ancestor::td[1]", $root)) {
                    $cnt = $this->http->XPath->query(".//text()[{$this->eq($this->t("Departure"))}]/ancestor::td[1]/preceding-sibling::td", $root)->length + 1;
                    $time = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[{$cnt}]", $root);
                    $itsegment['DepDate'] = strtotime($time, $date);
                }

                if ($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Arrives"))}]/ancestor::td[1]", $root)) {
                    $cnt = $this->http->XPath->query(".//text()[{$this->eq($this->t("Arrives"))}]/ancestor::td[1]/preceding-sibling::td", $root)->length + 1;
                    $time = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Arrives")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[{$cnt}]", $root);

                    if (preg_match("#^(\d{1,2}:\d{2})([+-]\d)$#", $time, $m)) {
                        $time = $m[1];
                        $itsegment['ArrDate'] = strtotime($m[1], $date);
                        $itsegment['ArrDate'] = strtotime($m[2] . "day", $itsegment['ArrDate']);
                    } else {
                        $itsegment['ArrDate'] = strtotime($time, $date);
                    }
                } else {
                    $itsegment['ArrDate'] = MISSING_DATE;
                }
            }

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            if (empty($arrName)) {
                $arrName = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[1]", $root, true, "#^From\s.+\sto\s(.+)$#");
            }

            if (empty($arrName)) {
                $arrName = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[1]", $root, true, "#^.+\s" . $this->opt($this->t("to")) . "\s(.+)$#");
            }

            if (!empty($arrName)) {
                $itsegment['ArrName'] = $arrName;
            }

            // AirlineName
            if (empty($airName)) {
                $airName = $this->http->FindSingleNode("./ancestor::tr[2]/preceding::td[2]", $root, true, "#^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*\d{1,5}#");
            }

            if (!empty($airName)) {
                $itsegment['AirlineName'] = $airName;
            }

            // Operator
            $operator = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Operated by")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[1]", $root);

            if (empty($operator)) {
                $operator = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Operated by")) . "]/ancestor::td[2]", null, true, '#' . $this->opt($this->t('Operated by')) . '\s(.+)#');
            }

            if (!empty($operator)) {
                $itsegment['Operator'] = $operator;
            }
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            if ($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Cabin class"))}]/ancestor::td[1]", $root)) {
                $cnt = $this->http->XPath->query(".//text()[{$this->eq($this->t("Cabin class"))}]/ancestor::td[1]/preceding-sibling::td", $root)->length + 1;
                $value = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Cabin class")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[{$cnt}]", $root);

                if (!empty($value)) {
                    $itsegment['Cabin'] = $value;
                }
            }
            // BookingClass
            if ($this->http->FindSingleNode(".//text()[{$this->eq($this->t("Class"))}]/ancestor::td[1]", $root)) {
                $cnt = $this->http->XPath->query(".//text()[{$this->eq($this->t("Class"))}]/ancestor::td[1]/preceding-sibling::td", $root)->length + 1;
                $value = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Class")) . "]/ancestor::tr[1]/following-sibling::tr[1]/td[{$cnt}]", $root);

                if (!empty($value)) {
                    $itsegment['BookingClass'] = $value;
                }
            }

            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (strpos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($body, $re) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->parser = $parser;
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$reBody2) {
            foreach ($reBody2 as $re) {
                if (strpos($this->http->Response["body"], $re) !== false) {
                    $this->lang = $lang;

                    break;
                }
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s));
        }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
        $in = [
            "#^\s*(\d{4})年(\d{1,2})月(\d{1,2})日\s+\D*\s*$#u", // 2020年5月15日 周五
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);
//        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)){
//            if ($en = MonthTranslate::translate($m[1], $this->lang))
//                $str = str_replace($m[1], $en, $str);
//        }

        return strtotime($str);
    }
}
