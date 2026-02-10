<?php

namespace AwardWallet\Engine\finnair\Email;

class It4042413 extends \TAccountChecker
{
    public $mailFiles = "finnair/it-4042413.eml, finnair/it-4203278.eml, finnair/it-4602358.eml, finnair/it-4981424.eml";
    //	use \DateTimeTools;
    //	use \PriceTools;

    public $reFrom = "noreply.customerservice@finnair.com";
    public $reSubject = [
        "en"=> "Finnair - Before your",
        "fi"=> "Finnair - Ennen lentoasi",
    ];
    public $reBody = 'Finnair';
    public $reBody2 = [
        "en"=> "Departs",
        "fi"=> "Lähtee",
    ];

    public static $dictionary = [
        "en" => [],
        "fi" => [
            "itinerary:" => "tiedot:",
            "Dear"       => "Hei",
            "Flight"     => "Lento",
            "Operated by"=> "NOTTRANSLATED",
            "Terminal"   => "Terminaali",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";
        $it['TripSegments'] = [];

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(., '" . $this->t("itinerary:") . "')])[1]",
            null, true, "#" . $this->t("itinerary:") . "\s+(\w+)#");

        if (empty($it['RecordLocator'])) {
            if ($this->http->XPath->query("(//text()[contains(., '{$this->t("itinerary:")}')])[1]/following::text()[normalize-space(.)!=''][1][contains(.,'{$this->t('Flight')}')]")->length > 0) {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            } else {
                $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[contains(., '{$this->t("itinerary:")}')])[1]/following::text()[normalize-space(.)!=''][1][not(contains(.,'{$this->t('Flight')}'))]",
                    null, true, "#[A-Z\d]{5,}#");
            }
        }

        // TripNumber
        // Passengers
        $it['Passengers'][] = str_replace(",", "", $this->http->FindSingleNode("(//text()[starts-with(normalize-space(.), '" . $this->t("Dear") . "')])[1]", null, true, "#" . $this->t("Dear") . " (.+)#"));

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

        $xpath = "(//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::table[1])[1]//tr[./td[7]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $tmp = implode(' ', $this->http->FindNodes("./td[2]/text()", $root));

            if (preg_match("#(.+)\s*" . $this->t('Terminal') . "[\s:]*(\w*)#", $tmp, $m)) {
                $itsegment['DepName'] = $m[1];
                $itsegment['DepartureTerminal'] = $m[2];
            } else {
                $itsegment['DepName'] = $tmp;
            }
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $tmp = implode(' ', $this->http->FindNodes("./td[3]/text()", $root));

            if (preg_match("#(.+)\s*" . $this->t('Terminal') . "[\s:]*(\w*)#", $tmp, $m)) {
                $itsegment['ArrName'] = $m[1];
                $itsegment['ArrivalTerminal'] = $m[2];
            } else {
                $itsegment['ArrName'] = $tmp;
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s+\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[1]", $root, true, "#" . $this->t("Operated by") . "\s+(.+)#");

            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[7]/descendant::text()[normalize-space(.)][2]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            //			if (!in_array($itsegment,$it['TripSegments']))
            $it['TripSegments'][] = $itsegment;
        }
        $it['TripSegments'] = array_map("unserialize", array_unique(array_map("serialize", $it['TripSegments']))); //exclude duplicates Segment
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
        //		$year = date("Y", $this->date);
        $in = [
            "#^(\d+)([^\d\s]+)(\d{2})\s*(\d+:\d+)$#",
            "#^(\d+)\.(\d+)\.(\d{2})\s*(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 20$3, $4",
            "$1.$2.20$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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
}
