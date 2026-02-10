<?php

namespace AwardWallet\Engine\aeroflot\Email;

class BookingInformationHtml extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-3023012.eml, aeroflot/it-4538357.eml, aeroflot/it-4891983.eml, aeroflot/it-4892010.eml, aeroflot/it-5049135.eml, aeroflot/it-5064236.eml, aeroflot/it-5239234.eml, aeroflot/it-5327659.eml, aeroflot/it-5665166.eml, aeroflot/it-5670828.eml, aeroflot/it-5675648.eml, aeroflot/it-5675716.eml";

    public $reFrom = "@aeroflot.ru";
    public $reSubject = [
        "ru"=> "Открыта регистрация на рейс для бронирования",
    ];
    public $reBody = 'Aeroflot';
    public $reBody2 = [
        "ru"=> "Вылет",
    ];

    public static $dictionary = [
        "ru" => [],
    ];

    public $lang = "ru";

    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public $dateTimeToolsMonths = [
        "ru" => [
            "январь"  => 0, "янв" => 0, "января" => 0,
            "февраля" => 1, "фев" => 1, "февраль" => 1,
            "марта"   => 2, "мар" => 2, "март" => 2,
            "апреля"  => 3, "апр" => 3, "апрель" => 3,
            "мая"     => 4, "май" => 4,
            "июн"     => 5, "июня" => 5, "июнь" => 5,
            "июля"    => 6, "июль" => 6, "июл" => 6,
            "августа" => 7, "авг" => 7, "август" => 7,
            "сен"     => 8, "сентябрь" => 8, "сентября" => 8,
            "окт"     => 9, "октября" => 9, "октябрь" => 9,
            "ноя"     => 10, "ноября" => 10, "ноябрь" => 10,
            "дек"     => 11, "декабрь" => 11, "декабря" => 11,
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(., 'Код бронирования:')]", null, true, "#:\s+(\w+)$#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Пассажиры']/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)][1]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='Вылет']/ancestor::tr[1]/following-sibling::tr[contains(., 'Самолет')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[2]/descendant::text()[string-length(normalize-space(.))=3]", $root, true, "#^([A-Z]{3})$#");

            // DepName
            $itsegment['DepName'] = implode(" ", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()=3 or position()=4]", $root));

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes("./td[2]/descendant::text()[normalize-space(.)][position()=1 or position()=2]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[3]/descendant::text()[string-length(normalize-space(.))=3]", $root, true, "#^([A-Z]{3})$#");

            // ArrName
            $itsegment['ArrName'] = implode(" ", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)][position()=3 or position()=4]", $root));

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate(implode(",", $this->http->FindNodes("./td[3]/descendant::text()[normalize-space(.)][position()=1 or position()=2]", $root))));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            $itsegment['Operator'] = $this->nextText("Выполняется авиакомпанией:", $root);

            // Aircraft
            $itsegment['Aircraft'] = $this->nextText("Самолет:", $root);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#^(.*?)\s+/#", $this->nextText("Класс:", $root));

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#(?:^|\s)(\w)$#", $this->nextText("Класс:", $root));

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->nextText("Полет:", $root);

            // Meal
            $itsegment['Meal'] = $this->nextText("Тип питания:", $root);

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
            if (strpos($headers["subject"], $re) !== false) {
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

        foreach ($this->reBody2 as $re) {
            if (strpos($body, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'reservations',
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

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
    }

    private function nextText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function nextCol($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//td[not(.//td) and normalize-space(.)='{$field}'])[{$n}]/following-sibling::td[1]", $root);
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+:\d+),(\d+\s+[^\d\s]+\s+\d{4})$#",
        ];
        $out = [
            "$2, $1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = $this->translateMonth($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
