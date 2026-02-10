<?php

namespace AwardWallet\Engine\asia\Email;

class FlightStatusUpd extends \TAccountChecker
{
    public $mailFiles = "asia/it-12631614.eml, asia/it-51795248.eml, asia/it-56529818.eml, asia/it-56600942.eml";

    public $detectFrom = ["@notification.cathaypacific.com", "@schedule.cathaypacific.com"];
    public $detectBody = [
        'en' => ['Flight status update', 'Flight schedule change', 'CHANGES TO YOUR UPCOMING FLIGHT'],
        'ja' => ['欠航のご案内'],
        'zh' => ['你即將選乘的航班有所變更', '您即将选乘的航班有所变更'],
    ];
    public $detectSubject = [
        'en'  => 'Important information - your flight has been re-scheduled',
        'en2' => 'has been changed', //Your flight to Hong Kong has been changed
        'ja'  => '行のフライトが変更になりました',
        'zh'  => '的航班有所變更',
    ];
    public $lang = 'en';
    public static $dict = [
        'en' => [
            //			'Booking reference' => '',
            'Hello' => ['Hello', 'Dear'],
            //            'Passenger' => '',
            //            'Depart from' => '',
            //            ' to ' => '',
            'Revised departure'   => 'Revised departure',
            'Revised arrival'     => 'Revised arrival',
            'Scheduled departure' => 'Scheduled departure',
            'Scheduled arrival'   => 'Scheduled arrival',
            'Your new itinerary'  => ['Your new itinerary', 'Your original itinerary'],
            //            'Operated by' => '',
        ],
        'ja' => [
            'Booking reference' => '予約番号',
            'Hello'             => '様',
            //            'Passenger' => '',
            //            'Depart from' => '',
            'Your new itinerary' => '新しいご旅程',
            ' to '               => ' 発 ',
            //            'Revised departure' => '',
            //            'Revised arrival' => '',
            'Scheduled departure' => '定刻の出発時刻',
            'Scheduled arrival'   => '定刻の到着時刻',
            'Operated by'         => '運航航空会社',
        ],
        'zh' => [
            'Booking reference' => ['預訂參考編號', '预订参考编号'],
            'Hello'             => '親愛的',
            //            'Passenger' => '',
            //            'Depart from' => '',
            'Your new itinerary' => '您的新行程',
            ' to '               => ' 前往 ',
            //            'Revised departure' => '',
            //            'Revised arrival' => '',
            'Scheduled departure' => ['預定出發時間', '预定出发时间'],
            'Scheduled arrival'   => ['預定抵達時間', '预定抵达时间'],
            'Operated by'         => '營運航空公司：',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->AssignLang();

        $its = $this->parseEmail();
        $a = explode('\\', __CLASS__);

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => $a[count($a) - 1] . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query("//img[contains(@src,'www.cathaypacific.com') or @alt='Cathay Pacific logo'] | //a[contains(@href,'www.cathaypacific.com')]")->length > 0) {
            return $this->AssignLang();
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['from']) && !isset($headers["subject"])) {
            return false;
        }
        $foundFrom = false;

        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($headers['from'], $detectFrom) !== false) {
                $foundFrom = true;

                break;
            }
        }

        if ($foundFrom === false) {
            return false;
        }

        foreach ($this->detectSubject as $detectSubject) {
            if (stripos($headers["subject"], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        foreach ($this->detectFrom as $detectFrom) {
            if (stripos($from, $detectFrom) !== false) {
                return true;
            }
        }

        return false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    private function parseEmail()
    {
        /** @var \AwardWallet\ItineraryArrays\AirTrip $it */
        $it = ['Kind' => 'T', 'TripSegments' => []];

        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking reference'))}]/following::text()[normalize-space(.)!=''][1]", null, true, "#([A-Z\d]{5,})#");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Booking reference'))}]", null, true, "#([A-Z\d]{5,})#");
        }

        $passenger = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Hello'))}]/ancestor::*[1][not({$this->contains($this->t('Passenger'))})]", null, true, "#{$this->opt($this->t('Hello'))}\s+(.+)#");

        if (!empty($passenger)) {
            $it['Passengers'][] = $passenger;
        }

        $xpath = "//text()[{$this->starts($this->t('Depart from'))}]";

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//table[" . $this->contains($this->t("Your new itinerary")) . " and not(.//table)]/ancestor::table[2]/following-sibling::table[1]/descendant::table[" . $this->contains($this->t("Scheduled departure")) . " and descendant::img[contains(@src, 'flight')]][not(contains(@class, 'deviceWidthLarger'))][1]/descendant::tr[descendant::img[contains(@src, 'flight')]]"
                . "/descendant::text()[" . $this->contains($this->t(" to "), '.') . "]";
        }

        if (0 === $this->http->XPath->query($xpath)->length) {
            $xpath = "//text()[" . $this->starts($this->t("Your new itinerary")) . "]/ancestor::table[1]/following::table[1]/descendant::table[1]/descendant::text()[" . $this->contains($this->t(" to "), '.') . "]";
        }

        $this->logger->debug($xpath);

        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            /** @var \AwardWallet\ItineraryArrays\AirTripSegment $seg */
            $seg = [];

            $node = $this->http->FindSingleNode("./ancestor::tr[1]", $root);
            $this->logger->debug($node);

            if (preg_match("#^(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<FlightNumber>\d+)\s+" . $this->opt($this->t("Depart from")) . " (?<DepName>.*?) \((?<DepCode>[A-Z]{3})\)" . $this->opt($this->t(" to ")) . "(?<ArrName>.*?) \((?<ArrCode>[A-Z]{3})\)$#u", $node, $m)) {
                $keys = ["AirlineName", "FlightNumber", "DepName", "DepCode", "ArrName", "ArrCode"];

                foreach ($keys as $k) {
                    $seg[$k] = $m[$k];
                }
            } elseif (preg_match("#^(?<AirlineName>[A-Z\d][A-Z]|[A-Z][A-Z\d]) *(?<FlightNumber>\d+)\s+" . $this->opt($this->t("Depart from")) . " (?<DepName>.*?) \((?<DepCode>[A-Z]{3})\)$#u", $node, $m)) {
                $keys = ["AirlineName", "FlightNumber", "DepName", "DepCode"];

                foreach ($keys as $k) {
                    $seg[$k] = $m[$k];
                }
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
            } elseif (preg_match("#^(?<AirlineName>[A-Z\d]{2})[ ]+(?<FlightNumber>\d+)[ ]+(?<DepName>.+)[ ]+\((?<DepCode>[A-Z]{3})\)[ ]*" . $this->opt($this->t(" to ")) . "[ ]*(?<ArrName>.+)[ ]+\((?<ArrCode>[A-Z]{3})\)( \w{1,2})?$#u", $node, $m)) {
                foreach (["AirlineName", "FlightNumber", "DepName", "DepCode", "ArrName", "ArrCode"] as $k) {
                    $seg[$k] = $m[$k];
                }
            }

            $time = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Revised departure'))}][1]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Scheduled departure'))}][1]/following::text()[normalize-space(.)!=''][1]",
                    $root);
                $date = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Scheduled departure'))}][1]/following::text()[normalize-space(.)!=''][2]",
                    $root);
            } else {
                $date = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Revised departure'))}][1]/following::text()[normalize-space(.)!=''][2]",
                    $root);
            }
            $seg['DepDate'] = $this->normalizeDate($date . ' ' . $time);

            $time = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Revised arrival'))}][1]/following::text()[normalize-space(.)!=''][1]",
                $root);

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Scheduled arrival'))}][1]/following::text()[normalize-space(.)!=''][1]",
                    $root);
                $date = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Scheduled arrival'))}][1]/following::text()[normalize-space(.)!=''][2]",
                    $root);
            } else {
                $date = $this->http->FindSingleNode("./following::text()[{$this->contains($this->t('Revised arrival'))}][1]/following::text()[normalize-space(.)!=''][2]",
                    $root);
            }
            $seg['ArrDate'] = $this->normalizeDate($date . ' ' . $time);

            $cabin = $this->http->FindSingleNode("./ancestor::table[1]/following::table[" . $this->contains($this->t("Operated by")) . "][1]/descendant::text()[normalize-space()][1]", $root);

            if (!empty($cabin)) {
                $seg['Cabin'] = $cabin;
            }

            $operator = $this->http->FindSingleNode("./ancestor::table[1]/following::table[" . $this->contains($this->t("Operated by")) . "][1]/descendant::text()[" . $this->starts($this->t("Operated by")) . "][1]", $root, true, "#" . $this->opt($this->t("Operated by")) . "\s+(\S.+)#");

            if (!empty($operator)) {
                $seg['Operator'] = $operator;
            }

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function normalizeDate($date)
    {
        $in = [
            '#^\s*\w+,\s+(\d+)\s+(\w+)\s+(d{4})\s+(\d+:\d+)\s*$#',
            '#^\s*(\d{4})年(\d{1,2})月(\d{1,2})日\s+\D*\s+(\d{1,2}:\d{2})\s*$#u', //2020年4月16日 木曜日 20:25
        ];
        $out = [
            '$1 $2 $3 $4',
            '$3.$2.$1, $4',
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $date));

        return strtotime($str);
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function AssignLang()
    {
        foreach ($this->detectBody as $lang => $detectBody) {
            if ($this->http->XPath->query("//*[" . $this->contains($detectBody) . "]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function contains($field, $text = 'normalize-space(.)')
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return "(?:" . preg_quote($s) . ")";
        }, $field)) . ')';
    }
}
