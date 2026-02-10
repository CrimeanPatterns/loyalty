<?php

namespace AwardWallet\Engine\austrian\Email;

class RequestConfirmation extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "austrian/it-9865431.eml";

    public $reFrom = "austrian.com";
    public $reSubject = [
        "en"=> "Private request confirmation",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en"=> "Reservation Confirmation Private ",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[contains(normalize-space(.),'" . $this->t("Reservation Confirmation Private for PNR Code") . "')][1]", null, true, "#" . $this->t("Reservation Confirmation Private for PNR Code") . "\s+([A-Z\d]{5,7})#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.) = '" . $this->t("Name") . "']/ancestor::tr[contains(.,'" . $this->t("Date of Birth") . "')][1]/following-sibling::tr/td[2]");

        // AccountNumbers
        // Cancelled

        // TotalCharge
        // Currency
        $totals = $this->http->FindNodes("//text()[normalize-space(.) = '" . $this->t("Total:") . "']/following::text()[normalize-space(.)][1]");
        $it['TotalCharge'] = 0.0;

        foreach ($totals as $total) {
            $it['TotalCharge'] += $this->amount($total);

            if (empty($it['Currency'])) {
                $it['Currency'] = $this->currency($total);
            }
        }

        // BaseFare
        $fares = $this->http->FindNodes("//text()[normalize-space(.) = '" . $this->t("Fare:") . "']/following::text()[normalize-space(.)][1]");
        $it['BaseFare'] = 0.0;

        foreach ($fares as $fare) {
            $it['BaseFare'] += $this->amount($fare);
        }

        // Tax
        // Fees
        $fees = $this->http->FindNodes("//text()[normalize-space(.) = '" . $this->t("Tax, Fee, Charge:") . "']/following::text()[normalize-space(.)][1]");
        $it['Fees'] = [];

        if (isset($it['Currency'])) {
            foreach ($fees as $fee) {
                if (preg_match_all("#" . $it['Currency'] . "\s+([\d.,]+)\s+(.+)(?=" . $it['Currency'] . "|$)#U", $fee, $m, PREG_SET_ORDER)) {
                    foreach ($m as $key => $value) {
                        foreach ($it['Fees'] as $i => $v) {
                            if (trim($value[2]) == $v["Name"]) {
                                $it['Fees'][$i]["Charge"] += $this->amount($value[1]);

                                continue 2;
                            }
                        }
                        $it['Fees'][] = [
                            "Charge" => $this->amount($value[1]),
                            "Name"   => $value[2],
                        ];
                    }
                }
            }
        }
        // SpentAwards
        // EarnedAwards
        // Status
        $status = [];
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.) = '" . $this->t("Flight") . "']/ancestor::tr[contains(.,'" . $this->t("From") . "')][1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $dateStr = $this->http->FindSingleNode("./td[2]", $root);
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#[A-Z\d]{2}\s*(\d{1,5})#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\b([A-Z]{3})\b#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = strtotime($dateStr . ' ' . $this->http->FindSingleNode("./td[4]", $root, true, "#(\d+:\d+)#"));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#\b([A-Z]{3})\b#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = strtotime($dateStr . ' ' . $this->http->FindSingleNode("./td[6]", $root, true, "#(\d+:\d+)#"));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([A-Z\d]{2})\s*\d{1,5}#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[9]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            // Meal
            // Smoking
            // Stops
            $status[] = $this->http->FindSingleNode("./td[7]", $root);

            $it['TripSegments'][] = $itsegment;
        }
        $it['Status'] = implode(",", array_unique(array_filter($status)));
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

        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'RequestConfirmation',
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

    public function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f=>$r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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
        $amount = (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));

        if ($amount == 0) {
            $amount = null;
        }

        return $amount;
    }
}
