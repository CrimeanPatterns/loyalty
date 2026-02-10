<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Engine\MonthTranslate;

class TripDetails extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-27331272.eml, cleartrip/it-27748641.eml, cleartrip/it-9901235.eml";
    public $reFrom = "no-reply@cleartrip.com";
    public $reSubject = [
        "en"=> "Trip Details",
    ];
    public $reBody = 'Cleartrip';
    public $reBody2 = [
        "en"=> "Trip ID",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq("Leaves") . "]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $roots) {
            $flights = $this->http->XPath->query("./following-sibling::tr", $roots);
            unset($rls);

            $codes = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $roots, true, "#\(([A-Z]{3})\)#") . '-' . $this->http->FindSingleNode("./following-sibling::tr[last()]/td[2]", $roots, true, "#\(([A-Z]{3})\)#");
            $position = count($this->http->FindNodes("//text()[" . $this->eq("{$codes} PNR") . "]/ancestor::td[1]/preceding-sibling::td"));

            if (!empty($position)) {
                $rls = $this->http->FindSingleNode("//text()[" . $this->eq("{$codes} PNR") . "]/ancestor::tr[1]/following-sibling::tr[1]/td[" . ($position + 1) . "]");
            }

            if (empty($rls)) {
                $this->logger->info("RL not matched " . $codes);

                return;
            }
            $rls = array_filter(array_map('trim', explode(",", $rls)));

            if (count($rls) == 1 || count($rls) == $flights->length) {
                foreach ($flights as $key => $root) {
                    $airs[$rls[$key] ?? $rls[0]][] = $root;
                }
            } else {
                $this->logger->info("count of RLS don't matched " . $codes);

                return;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            if ($it['RecordLocator'] == 'On Hold') {
                $it['RecordLocator'] = CONFNO_UNKNOWN;
            }

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts("Trip ID") . "]", null, true, "#Trip ID (\d+)#");

            // Passengers
            $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq("Name") . "]/ancestor::tr[1][./td[3]]/following-sibling::tr/td[1]", null, "#(.+?)(\s*\(.*\))?$#"));

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
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::h2[1]", $root, true, "# on (.+)#")));
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[6]", $root, true, "#^\w{2}-(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root), $date);

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\(([A-Z]{3})\)#");

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)\s*\([A-Z]{3}\)#");

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[4]", $root), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[6]", $root, true, "#^(\w{2})-\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
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

        if (stripos($body, $this->reBody) === false) {
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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $price = $this->http->FindSingleNode("//text()[normalize-space() = 'Pricing details']/following::text()[normalize-space()='Total']/ancestor::td[1]/following-sibling::td[normalize-space()][1]");

        if (!empty($price) && !empty($itineraries)
                && (preg_match('#^\s*(?<curr>[^\d\s]+)\s*(?<total>\d[\d\., ]*)\s*$#', $price, $m)
                || preg_match('#^\s*(?<total>\d[\d\., ]*)\s*(?<curr>[^\d\s]+)\s*$#', $price, $m))) {
            $total = str_replace(",", '', $m['total']);

            if (count($itineraries) == 1) {
                $itineraries[0]['TotalCharge'] = is_numeric($total) ? (float) $total : null;
                $itineraries[0]['Currency'] = $this->currency($m['curr']);
            } else {
                $totalAll = [
                    'Amount'   => is_numeric($total) ? (float) $total : null,
                    'Currency' => $this->currency($m['curr']),
                ];
            }
        }
        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => $totalAll ?? [],
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
        // $this->http->log($word);
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
            "#^[^\s\d]+, (\d+ [^\s\d]+ \d{4})$#", //Sat, 09 Sep 2017
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#(\d[\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'  => 'EUR',
            '$'  => 'USD',
            '£'  => 'GBP',
            '₹'  => 'INR',
            'Rs.'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if ($s == $f) {
                return $r;
            }
        }

        return null;
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
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
    }
}
