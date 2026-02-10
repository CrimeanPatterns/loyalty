<?php

namespace AwardWallet\Engine\airbaltic\Email;

class It4613938 extends \TAccountChecker
{
    public $mailFiles = "airbaltic/it-4613938.eml";

    public $reFrom = "noreply@airbaltic.com";
    public $reSubject = [
        "en"=> "Flight schedule change confirmation",
    ];
    public $reBody = 'airBaltic.com';
    public $reBody2 = [
        "en"=> "apologises in advance for any inconvenience what these changes",
        "de"=> "bittet Sie vorab für alle Unannehmlichkeiten um Entschuldigung",
    ];

    public static $dictionary = [
        "en" => [],
        "de" => [
            "FLIGHT BOOKING REFERENCE"=> "BUCHUNGSREFERENZ DES FLUGES",
            "Passengers:"             => "Passagiere:",
            "Outbound:"               => "Abflug:",
            "Arrival:"                => "Ankunft:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSIngleNode("//text()[{$this->starts($this->t('FLIGHT BOOKING REFERENCE'))}]", null, true, "#{$this->opt($this->t('FLIGHT BOOKING REFERENCE'))}\s*:\s*(\w+)#");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t('Passengers:'))}]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)!='']");

        $xpath = "//text()[{$this->starts($this->t('Outbound:'))}]/preceding::text()[normalize-space(.)][1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSIngleNode(".", $root, true, "#\w{2}\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSIngleNode(".", $root, true, "#\w{2}\s+\d+\s+(.*?)\s+-\s+.+#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Outbound:'))}][1]/following::text()[normalize-space(.)!=''][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSIngleNode(".", $root, true, "#\w{2}\s+\d+\s+.*?\s+-\s+(.+)#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[{$this->starts($this->t('Arrival:'))}][1]/following::text()[normalize-space(.)!=''][1]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSIngleNode(".", $root, true, "#(\w{2})\s+\d+#");

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
        $this->subject = $parser->getSubject();
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+\w+\s+\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1, $2",
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

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(' or ', array_map(function ($s) {
            return 'starts-with(normalize-space(.),"' . $s . '")';
        }, $field));
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
