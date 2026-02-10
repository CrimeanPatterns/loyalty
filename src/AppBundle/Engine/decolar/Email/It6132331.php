<?php

namespace AwardWallet\Engine\decolar\Email;

class It6132331 extends \TAccountChecker
{
    public $mailFiles = "";

    public $reSubject = [
        'pt' => 'Houve uma pequena alteração no seu voo - Reserva No.',
    ];

    public $lang = '';

    public $reBody = 'Decolar';
    public $reBody2 = [
        'pt' => 'Houve uma pequena alteração no seu voo',
    ];

    public static $dictionary = [
        'pt' => [],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->nextText("Reserva no.");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->starts($this->t("Olá")) . "]", null, "#" . $this->t("Olá") . "\s+(.+),$#");

        $xpath = "//text()[" . $this->starts([$this->t("TRECHO"), $this->t("IDA"), $this->t("VOLTA")]) . "]/ancestor::table[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./table[1]/descendant::text()[normalize-space(.)][2]", $root)));

            // FlightNumber
            $itsegment['FlightNumber'] = $this->nextText($this->t("Vôo Nº"), $root);

            // DepCode
            if (!$itsegment['DepCode'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z]{3}$#")) {
                $itsegment['DepCode'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#^[A-Z]{3}$#");
            }

            // DepName
            if ($itsegment['DepName'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][2]", $root)) {
                $itsegment['DepName'] .= ', ' . $this->http->FindSingleNode("./table[4]//td[3]/../td[1]/descendant::text()[normalize-space(.)][last()]", $root);
            } elseif ($itsegment['DepName'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][2]", $root)) {
                $itsegment['DepName'] .= ', ' . $this->http->FindSingleNode("./table[5]//td[3]/../td[1]/descendant::text()[normalize-space(.)][last()]", $root);
            }

            // DepDate
            if (!$time = $this->http->FindSingleNode("./table[4]//td[3]/../td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+.+#")) {
                $time = $this->http->FindSingleNode("./table[5]//td[3]/../td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+.+#");
            }
            $itsegment['DepDate'] = strtotime($time, $date);

            // ArrCode
            if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][3]", $root, true, "#^[A-Z]{3}$#")) {
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][3]", $root, true, "#^[A-Z]{3}$#");
            }

            // ArrName
            if ($itsegment['ArrName'] = $this->http->FindSingleNode("./table[3]/descendant::text()[normalize-space(.)][4]", $root)) {
                $itsegment['ArrName'] .= ', ' . $this->http->FindSingleNode("./table[4]//td[3]/descendant::text()[normalize-space(.)][last()]", $root);
            } elseif ($itsegment['ArrName'] = $this->http->FindSingleNode("./table[4]/descendant::text()[normalize-space(.)][4]", $root)) {
                $itsegment['ArrName'] .= ', ' . $this->http->FindSingleNode("./table[5]//td[3]/descendant::text()[normalize-space(.)][last()]", $root);
            }

            // ArrDate
            if (!$time = $this->http->FindSingleNode("./table[4]//td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+.+#")) {
                $time = $this->http->FindSingleNode("./table[5]//td[3]/descendant::text()[normalize-space(.)][1]", $root, true, "#\d+.+#");
            }
            $itsegment['ArrDate'] = strtotime($time, $date);

            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Classe")) . "]", $root, true, "#" . $this->t("Classe") . "\s+(.+)#");

            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode(".//text()[" . $this->starts($this->t("Duração do voo:")) . "]", $root, true, "#" . $this->t("Duração do voo:") . "\s+(.+)#");

            // Stops
            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@decolar.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers['subject'], $re) !== false) {
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlightChanges_' . $this->lang,
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)\.\s+(\d{4})$#", // Sex 21 Abr. 2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    //	private function re($re, $str, $c=1){
    //		preg_match($re, $str, $m);
    //		if(isset($m[$c])) return $m[$c];
    //		return null;
    //	}

    //	private function amount($s){
    //		return (float)str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    //	}

    //	private function currency($s){
    //		$sym = [
    //			'€'=>'EUR',
    //			'$'=>'USD',
    //		];
    //		if($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) return $code;
    //		foreach($sym as $f=>$r)
    //			if(strpos($s, $f) !== false) return $r;
    //		return null;
    //	}

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'normalize-space(.)="' . $s . '"'; }, $field)) . ')';
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false';
        }

        return '(' . implode(' or ', array_map(function ($s) { return 'starts-with(normalize-space(.),"' . $s . '")'; }, $field)) . ')';
    }
}
