<?php

namespace AwardWallet\Engine\austrian\Email;

class It4336897 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "austrian/it-10182641.eml, austrian/it-4048449.eml, austrian/it-4336897.eml, austrian/it-4348167.eml, austrian/it-8158950.eml";

    public $reFrom = "austrian@smile.austrian.com";
    public $reSubject = [
        "en"=> "Your flight",
        "de"=> "Ihr Flug",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en"=> "Flight number",
        "de"=> "Flugnummer",
    ];

    public static $dictionary = [
        "en" => [
            "Passenger:"=> ["Passenger:", "Passenger", "Pessenger"],
        ],
        "de" => [
            "Passenger:"    => ["Passagier:", "Passagier"],
            "Flight number" => "Flugnummer",
            "From"          => "Von",
            "To"            => "Nach",
            "Date"          => "Datum",
            "Departure time"=> "Abflugzeit",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = TRIP_CODE_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t('Passenger:'))}]/following::text()[normalize-space(.)!=''][1]");

        $xpath = "//text()[{$this->eq($this->t('Flight number'))}]/ancestor::tr[{$this->contains($this->t('From'))}][1]/following-sibling::tr[normalize-space(.)!=''][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $it['TripSegments'] = $this->parseSegmentType1($nodes);
            $itineraries[] = $it;

            return true;
        }

        $xpath = "//text()[{$this->eq($this->t('Flight number'))}]/ancestor::tr[{$this->contains($this->t('From'))}][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            $it['TripSegments'] = $this->parseSegmentType2($nodes);
            $itineraries[] = $it;

            return true;
        }

        $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);

        return false;
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

    private function parseSegmentType1($nodes)
    {
        $itsegments = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->http->FindSingleNode("(./td[normalize-space(.)!=''])[4]", $root));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[1]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[2]", $root, true, "#\(([A-Z]{3})#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[2]", $root, true, "#(.+?)\s*(?:\(|$)#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("(./td[normalize-space(.)!=''])[5]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[3]", $root, true, "#\(([A-Z]{3})#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[3]", $root, true, "#(.+?)\s*(?:\(|$)#");

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("(./td[normalize-space(.)!=''])[1]", $root, true, "#^(\w{2})\d+$#");

            $itsegments[] = $itsegment;
        }

        return $itsegments;
    }

    private function parseSegmentType2($nodes)
    {
        $itsegments = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Date'))}]/following::text()[normalize-space(.)!='' and not({$this->eq($this->t('Departure time'))})][1]", $root));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^\w{2}(\d+)$#");

            if (in_array($this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('From'))}]/following::text()[normalize-space(.)!=''][1]", $root), (array) $this->t('To'))) {
                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#\(([A-Z]{3})#");
                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#(.+?)\s*(?:\(|$)#");
                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][2]", $root, true, "#\(([A-Z]{3})#");
                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][2]", $root, true, "#(.+?)\s*(?:\(|$)#");
            } else {
                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('From'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#\(([A-Z]{3})#");
                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('From'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#(.+?)\s*(?:\(|$)#");
                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#\(([A-Z]{3})#");
                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('To'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#(.+?)\s*(?:\(|$)#");
            }
            // DepDate
            $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure time'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#\d+:\d+#");

            if (empty($time)) {
                $time = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Departure time'))}]/following::text()[normalize-space(.)!=''][2]", $root, true, "#\d+:\d+#");
            }

            if (!empty($time) && !empty($date)) {
                $itsegment['DepDate'] = strtotime($time, $date);
            }

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[{$this->eq($this->t('Flight number'))}]/following::text()[normalize-space(.)!=''][1]", $root, true, "#^(\w{2})\d+$#");

            $itsegments[] = $itsegment;
        }

        return $itsegments;
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
        $in = [
            "#^(\d+\.\d+\.\d{4})$#",
        ];
        $out = [
            "$$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'normalize-space(.)="' . $s . '"';
        }, $field));
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
}
