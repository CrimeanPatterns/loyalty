<?php

namespace AwardWallet\Engine\alitalia\Email;

class It5063122 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "alitalia/it-5063122.eml, alitalia/it-5306712.eml";

    public $reFrom = "noreply@alitalia.com";
    public $reSubject = [
        "it"=> "Ritira la carta di imbarco in Aeroporto",
        "en"=> "Collect your boarding pass at the airport",
    ];
    public $reBody = 'alitalia.com';
    public $reBody2 = [
        "it"=> "CODICE PRENOTAZIONE (PNR)",
        "en"=> "BOOKING CODE (PNR)",
    ];

    public static $dictionary = [
        "it" => [],
        "en" => [
            "CODICE PRENOTAZIONE (PNR)"=> "BOOKING CODE (PNR)",
            "PASSEGGERO"               => "PASSENGER",
        ],
    ];

    public $lang = "it";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("CODICE PRENOTAZIONE (PNR)") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][1]/td[2]");
        $it['RecordLocator'] = 'SDHD33';
        // TripNumber
        // Passengers
        $it['Passengers'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("PASSEGGERO") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]/td[1]"));

        $xpath = "//text()[normalize-space(.)='" . $this->t("CODICE PRENOTAZIONE (PNR)") . "']/ancestor::table[1]/following-sibling::table[1]//tr[./td[5]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $xpath = "//text()[normalize-space(.)='" . $this->t("CODICE PRENOTAZIONE (PNR)") . "']/ancestor::tr[1]/following-sibling::tr//table//tr[./td[5]]";
            $nodes = $this->http->XPath->query($xpath);
        }

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = strtotime(preg_replace("#(\d+:\d+).+#", "$1", $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = strtotime(preg_replace("#(\d+:\d+).+#", "$1", $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][1]", $root)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(\w{2})\d+$#");

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
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+\w{2}\d+$#",
            "#^(\d+)([^\d\s]+)(\d{4})\s+\w{2}\d+$#",
        ];
        $out = [
            "$1",
            "$1 $2 $3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
            $str = $this->dateStringToEnglish($str);
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
