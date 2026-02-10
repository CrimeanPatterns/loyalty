<?php

namespace AwardWallet\Engine\expedia\Email;

use AwardWallet\Engine\MonthTranslate;

class UpdateYourTrip extends \TAccountChecker
{
    public $mailFiles = "expedia/it-6688613.eml";

    public static $dictionary = [
        "fr" => [],
    ];

    public $lang = "fr";
    private $reFrom = "@EXPEDIA";
    private $reSubject = [
        "fr"=> "Mise à jour de votre voyage: confirmation de changement pour",
    ];
    private $reBody = 'Expedia';
    private $reBody2 = [
        "fr"  => "Les informations de votre vol ont changé",
        'fr2' => 'Vos détails de vol ont changé',
    ];
    private $date;

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $this->parseHtml($itineraries);

        $a = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($a) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                // 'TotalCharge' => [
                // "Amount" => $this->cost($this->getField("Total Price:")),
                // "Currency" => $this->currency($this->getField("Total Price:"))
                // ]
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

    private function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->starts("Code de confirmation") . "]";
        $nodes = $this->http->XPath->query($xpath);
        $rls = [];

        foreach ($nodes as $root) {
            if (!$airline = $this->http->FindSingleNode(".", $root, true, "#Code de confirmation\s+(.*?):$#")) {
                $this->http->log("Airline for RL not matched");

                return;
            }
            $rls[$airline] = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root);
        }
        $xpath = "//text()[normalize-space(.)='Détails de vol']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./following-sibling::tr[1]/descendant::text()[normalize-space(.)][1]", $root);

            if (!isset($rls[$airline])) {
                $this->http->log("RL not found");

                return;
            }
            $airs[$rls[$airline]][] = $root;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("Numéro de voyage") . "]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]");

            // Passengers
            $it['Passengers'] = array_map("trim", explode(", ", $this->nextText("Passager(s):")));

            // TicketNumbers
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

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[2]", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true, "#:\s*\w{2}(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true, "#\(([A-Z]{3})#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[2]/td[2]", $root, true, "#\([A-Z]{3}-(.*?)\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeTime($this->http->FindSingleNode("./following-sibling::tr[2]/td[3]", $root, true, "#:\s*(.+)#")), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true, "#\(([A-Z]{3})#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true, "#\([A-Z]{3}-(.*?)\)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeTime($this->http->FindSingleNode("./following-sibling::tr[3]/td[3]", $root, true, "#:\s*(.+)#")), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[3]", $root, true, "#:\s*(\w{2})\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./following-sibling::tr[4]/td[3]", $root, true, "#Classe:\s*(.+)$#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->http->FindSingleNode("./following-sibling::tr[5]/td[3]", $root, true, "#Sièges:\s*(.+)$#");

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+),\s+(\d{4})$#", //Mercredi, 05 Juillet, 2017
        ];
        $out = [
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }

    private function normalizeTime($str)
    {
        // $this->http->log($str);
        $in = [
            "#^(\d+:\d+)\s+\+(\d+)\s+jour$#", //05:55 +1 jour
        ];
        $out = [
            "$1, +$2 day",
        ];
        $str = preg_replace($in, $out, $str);

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
