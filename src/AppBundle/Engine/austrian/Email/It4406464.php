<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;

class It4406464 extends \TAccountChecker
{
    public $mailFiles = "austrian/it-10192620.eml, austrian/it-10295063.eml, austrian/it-10392781.eml, austrian/it-12030675.eml, austrian/it-12060446.eml, austrian/it-4406464.eml, austrian/it-8773561.eml";

    public $reFrom = "austrian@smile.austrian.com";
    public $reSubject = [
        "en"=> "Your flight on",
        "de"=> "Ihr Flug",
        "fr"=> "Votre vol",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en" => "Only a few days until your departure",
        "en2"=> "sending you some useful information ",
        "de" => "Nur noch wenige Tage bis zu Ihrem Abflug",
        "de2"=> " Ihnen heute Wissenswertes rund um Ihren Flug",
        "fr" => "À quelques jours de votre départ",
    ];
    public $date;
    public static $dictionary = [
        "en" => [],
        "de" => [
            "Booking code:"       => "Buchungscode:",
            "Dear"                => ["Lieber", "Liebe"],
            "Operated by"         => "durchgeführt von",
            "Flight details for " => "Flugdetails vom ",
            "Departure"           => "Abflug",
        ],
        "fr" => [
            "Booking code:"=> "Code de réservation",
            "Dear"         => "Cher",
            "Operated by"  => "Opéré par",
            //			"Flight details for " => "",
            //			"Departure" => "",
        ],
    ];

    public $lang = "";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Booking code:'))}]", null, true, "#{$this->opt($this->t('Booking code:'))}[\s:]*(\w+)#");
        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->contains($this->t('Dear'))}]", null, "#{$this->opt($this->t('Dear'))}\s+(.+?),#");
        // AccountNumbers

        if (!empty($this->http->FindSingleNode("(//text()[{$this->eq($this->t('Departure'))}])[1]"))) {
            $it['TripSegments'] = $this->parseSegmentType2();
        } else {
            $it['TripSegments'] = $this->parseSegmentType1();
        }
        $itineraries[] = $it;
    }

    public function parseSegmentType1()
    {
        $TripSegments = [];
        $xpath = "//img[contains(@src, '/airlines/')]/ancestor::td[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root1 not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::table[1]", $root)));

            if ($date !== false) {
                $this->date = $date;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./table[1]//td[1]", $root, true, "#([A-Z]{3})#");

            // DepName
            // DepDate
            $dateDep = trim($this->http->FindSingleNode("./table[2]//td[1]/descendant::text()[normalize-space(.)!=''][2]", $root), '.');
            $dateDep = EmailDateHelper::parseDateRelative($dateDep, $this->date, true, EmailDateHelper::FORMAT_DOT_DATE_YEAR);
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./table[2]//td[1]/descendant::text()[normalize-space(.)!=''][1]", $root), $dateDep);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./table[1]//td[3]", $root, true, "#([A-Z]{3})#");

            // ArrName
            // ArrDate
            $dateArr = trim($this->http->FindSingleNode("./table[2]//td[3]/descendant::text()[normalize-space(.)!=''][2]", $root), '.');
            $dateArr = EmailDateHelper::parseDateRelative($dateArr, $this->date, true, EmailDateHelper::FORMAT_DOT_DATE_YEAR);
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./table[2]//td[3]/descendant::text()[normalize-space(.)!=''][1]", $root), $dateArr);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#^(\w{2})\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)!=''][2]", $root, true, "#{$this->opt($this->t('Operated by'))}\s+(.+)#");

            $TripSegments[] = $itsegment;
        }

        return $TripSegments;
    }

    public function parseSegmentType2()
    {
        $TripSegments = [];
        $xpath = "//img[contains(@src, '/airlines/')]/ancestor::tr[3]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root2 not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[{$this->starts($this->t('Flight details for '))}][1]", $root, true, "#" . $this->opt($this->t('Flight details for ')) . "(.+)#")));

            if (!empty($date)) {
                $this->date = $date;
            }
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//img[contains(@src, '/airlines/')]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^\s*[A-Z\d]{2}(\d{1,5})\s*$#");

            // DepName
            // DepCode
            // ArrName
            // ArrCode
            $node = $this->http->FindSingleNode("./descendant::text()[normalize-space()][1]/ancestor::tr[1]", $root);

            if (preg_match("#(.+)\(([A-Z]{3})\)\s*-\s*(.+)\(([A-Z]{3})\)#", $node, $m)) {
                $itsegment['DepName'] = trim($m[1]);
                $itsegment['DepCode'] = $m[2];
                $itsegment['ArrName'] = trim($m[3]);
                $itsegment['ArrCode'] = $m[4];
            }

            // DepDate
            // ArrDate
            $date = $this->normalizeDate($this->http->FindSingleNode(".//img[contains(@src, '/airlines/')]/ancestor::td[1]/following-sibling::td[2]", $root));
            $node = $this->http->FindSingleNode(".//text()[{$this->eq($this->t('Departure'))}]/ancestor::tr[1]/preceding-sibling::tr[normalize-space()][1]", $root);

            if (preg_match("#(\d+:\d+)\s*-\s*(\d+:\d+)\s*(.+)#", $node, $m)) {
                if (!empty($date)) {
                    $itsegment['DepDate'] = strtotime($date . ' ' . $m[1]);

                    if ($itsegment['DepDate'] < $this->date - 60 * 60 * 24) {
                        $itsegment['DepDate'] = strtotime("+1year", $itsegment['DepDate']);
                    }
                    $itsegment['ArrDate'] = strtotime($date . ' ' . $m[2]);

                    if ($itsegment['ArrDate'] < $this->date - 60 * 60 * 24) {
                        $itsegment['ArrDate'] = strtotime("+1year", $itsegment['ArrDate']);
                    }
                }
                $itsegment['Cabin'] = $m[3];
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//img[contains(@src, '/airlines/')]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#^\s*([A-Z\d]{2})\d{1,5}\s*$#");

            $TripSegments[] = $itsegment;
        }

        return $TripSegments;
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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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
            "#^Outbound,\s+(\d+\.\d+\.\d+)$#",
            "#^Outbound,\s+(\d+\.\d+)\.$#",
            "#^\s*(\d+\.\d+)\.\s*$#",
        ];
        $out = [
            "$1",
            "$1.$year",
            "$1.$year",
            "$1.$year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return "(?:" . preg_quote($s) . ")"; }, $field)) . ')';
    }
}
