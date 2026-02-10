<?php

namespace AwardWallet\Engine\cleartrip\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;

class TicketFor extends \TAccountChecker
{
    public $mailFiles = "cleartrip/it-12343199.eml, cleartrip/it-12409692.eml, cleartrip/it-330288412.eml, cleartrip/it-335626407.eml, cleartrip/it-9863619.eml";
    public $reFrom = "@cleartrip.com";
    public $reSubject = [
        "en"=> "Ticket for",
    ];
    public $reBody = 'Cleartrip';
    public $reBody2 = [
        "en"=> "You are flying to",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $pnrs = [];
        $pnrsNodes = $this->http->XPath->query("//text()[" . $this->eq("PNR") . "]/ancestor::tr[1]/following-sibling::tr[not({$this->starts('Seat:')})]");
        $firstName = '';
        $seats = [];
        $seatSeg = [];

        foreach ($pnrsNodes as $key => $root) {
            $name = $this->http->FindSingleNode("./*[1]", $root);

            if ($key == 0) {
                $firstName = $name;
            }

            if ($name == $firstName) {
                $pnrs[] = $this->http->FindSingleNode("./*[3]", $root);

                if ($key !== 0) {
                    $seats[] = $seatSeg;
                    $seatSeg = [];
                }
            }
            $seatSeg[] = $this->http->FindSingleNode("following-sibling::tr[1]/*[1]", $root, true, "/^\s*Seat: *(.+)/");
        }
        $seats[] = $seatSeg;

        if (!empty(array_filter($pnrs)) && count($pnrs) !== count(array_filter($pnrs))) {
            $pnrs = array_map(function ($v) { if (!$v) {return CONFNO_UNKNOWN; } else {return $v; }}, $pnrs);
        }

        if (empty(array_filter($pnrs)) && !empty(array_filter($this->http->FindNodes("//text()[" . $this->eq("PNR") . "]/ancestor::tr[1]/following-sibling::tr")))
                && empty(array_filter($this->http->FindNodes("//text()[" . $this->eq("PNR") . "]/ancestor::tr[1]/following-sibling::tr/*[normalize-space()][2]")))) {
            unset($pnrs);
            $pnrs[] = CONFNO_UNKNOWN;
        }

        $xpath = "//img[contains(@src, 'duration.png')]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        if (count(array_unique($pnrs)) == 1) {
            $airs[current($pnrs)] = $nodes;
        } elseif (count($pnrs) > 1 && $nodes->length == count($pnrs)) {
            foreach ($nodes as $i => $root) {
                $airs[current($pnrs)][$i] = $root;
                next($pnrs);
            }
        } elseif ($nodes->length == count(array_diff($pnrs, [CONFNO_UNKNOWN]))) {
            foreach ($nodes as $i => $root) {
                $airs[current($pnrs)][$i] = $root;
                next($pnrs);
            }
        }

        foreach ($airs as $rl => $roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->nexttext("Trip ID:");

            // Passengers
            $it['Passengers'] = preg_replace("/^\s*(Ms|Miss|Mrs|Ms|Mr|Mstr|Dr|Master)\.? /", '',
                array_unique($this->http->FindNodes("//text()[" . $this->eq("TRAVELLERS") . "]/ancestor::tr[1]/following-sibling::tr/*[1][not({$this->starts('Seat:')})]")));

            // TicketNumbers
            // AccountNumbers
            // Cancelled
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->starts("Your booking is") . "]", null, true, "#Your booking is (\w+)#");

            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $segI => $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^\w{2}\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[2]/td[1]/descendant::text()[normalize-space(.)][1]", $root);

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[4]/td[1]", $root);

                // DepartureTerminal
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[3]/td[1]", $root) . ', ' . $this->http->FindSingleNode("./tr[2]/td[1]/descendant::text()[normalize-space(.)][2]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[2]/td[3]/descendant::text()[normalize-space(.)][2]", $root);

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[4]/td[3]", $root);

                // ArrivalTerminal
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[3]/td[3]", $root) . ', ' . $this->http->FindSingleNode("./tr[2]/td[3]/descendant::text()[normalize-space(.)][1]", $root)));

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./tr[1]/descendant::text()[normalize-space(.)][last()]", $root, true, "#^(\w{2})\s+\d+$#");

                // Operator
                // Aircraft
                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                if (!empty(array_filter($seats[$segI]))) {
                    $itsegment['Seats'] = array_filter($seats[$segI]);
                }
                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./tr[3]/td[2]", $root);

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

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $totalStr = $this->http->FindSingleNode("//text()[" . $this->starts("Amount paid") . "]", null, true, "#Amount paid (.+)#");
        $total = $this->getTotal($totalStr);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $total['amount'],
                    "Currency" => $total['currency'],
                ],
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
            "#^(\d+:\d+)\s*\|\s*[^\d\s]+,(\d+)-([^\d\s]+)-(\d{2})$#", //09:25| Tue,30-Dec-14
        ];
        $out = [
            "$2 $3 $4, $1",
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
//        $s = $this->re("#([^\d\,]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
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

    private function getTotal($text)
    {
        $result = ['amount' => null, 'currency' => null];

        if (preg_match("#^\s*(?<currency>[^\d\s]{1,5})\s*(?<amount>\d[\d\., ]*)\s*$#", $text, $m)
            || preg_match("#^\s*(?<amount>\d[\d\., ]*)\s*(?<currency>[^\d\s]{1,5})\s*$#", $text, $m)
            // $232.83 USD
            || preg_match("#^\s*\D{1,5}(?<amount>\d[\d\., ]*)\s*(?<currency>[A-Z]{3})\s*$#", $text, $m)
        ) {
            $m['currency'] = $this->currency(trim($m['currency']));
            $m['amount'] = PriceHelper::parse($m['amount']);

            if (is_numeric($m['amount'])) {
                $m['amount'] = (float) $m['amount'];
            } else {
                $m['amount'] = null;
            }
            $result = ['amount' => $m['amount'], 'currency' => $m['currency']];
        }

        return $result;
    }
}
