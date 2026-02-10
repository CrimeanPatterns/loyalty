<?php

namespace AwardWallet\Engine\alitalia\Email;

class ElectronicTicketReceipt extends \TAccountChecker
{
    public $mailFiles = "alitalia/it-1892365.eml, alitalia/it-1929083.eml, alitalia/it-1938114.eml, alitalia/it-2758780.eml, alitalia/it-5561386.eml, alitalia/it-6078562.eml, alitalia/it-6198172.eml, alitalia/it-6211701.eml, alitalia/it-6382991.eml, alitalia/it-6388984.eml, alitalia/it-6631400.eml, alitalia/it-6906009.eml, alitalia/it-6995890.eml";

    public $reFrom = "ETicket@alitalia.it";
    public $reSubject = [
        "en" => "ALITALIA ELECTRONIC TICKET RECEIPT",
    ];
    public $reBody = 'Alitalia';
    public $reBody2 = [
        "it" => "Partenza",
        "de" => "Abflug",
        "fr" => "Départ",
        "ru" => "Вылет",
        "es" => "Salida",
    ];

    public static $dictionary = [
        "it" => [],
        "de" => [],
        "fr" => [],
        "ru" => [],
        "es" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->contains("Reservation code:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]", null, true, "#\w+#");

        // Passengers
        $it['Passengers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains("Passenger:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]")]);

        // TicketNumbers
        $it['TicketNumbers'] = array_filter([$this->http->FindSingleNode("//text()[" . $this->contains("E-ticket number:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][3]")]);

        // TotalCharge
        $totalPayment = $this->http->FindSingleNode("//text()[" . $this->contains(["Total:", "Total*:", "Re-issue value"]) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][last()]", null, true, "#([\d\,\.]+)\s+[A-Z]{3}#");

        if ($totalPayment !== null) {
            $it['TotalCharge'] = $this->amount($totalPayment);
        }

        // BaseFare
        $ticketPrice = $this->http->FindSingleNode("//text()[" . $this->contains("E-Ticket price:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]", null, true, "#([\d\,\.]+)\s+[A-Z]{3}#");

        if ($ticketPrice !== null) {
            $it['BaseFare'] = $this->amount($ticketPrice);
        }

        // Currency
        $currency = $this->http->FindSingleNode("//text()[" . $this->contains(["Total:", "Total*:", "Re-issue value"]) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][last()]", null, true, "#[\d\,\.]+\s+([A-Z]{3})#");

        if ($currency) {
            $it['Currency'] = $currency;
        }

        // Tax
        $taxes = $this->http->FindSingleNode("//text()[" . $this->contains("Taxes and surcharges:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]", null, true, "#([\d\,\.]+)\s+[A-Z]{3}#");

        if ($taxes !== null) {
            $it['Tax'] = $this->amount($taxes);
        }

        // TripSegments
        $it['TripSegments'] = [];

        $xpath = "//text()[" . $this->starts("Departure -") . "]/ancestor::tr[1][./*[4][" . $this->contains("Arrival") . "]]/following-sibling::tr[ ./td[5][normalize-space(.)] ]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('./td[5]', $root);

            if (preg_match('/^(?<airline>[A-Z][A-Z\d]|[A-Z\d][A-Z])?\s*(?<flightNumber>\d+)/', $flight, $matches)) {
                if (!empty($matches['airline'])) {
                    $itsegment['AirlineName'] = $matches['airline'];
                }
                $itsegment['FlightNumber'] = $matches['flightNumber'];
            }

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[3]", $root)));

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            if (!$itsegment['ArrDate']) {
                $itsegment['ArrDate'] = MISSING_DATE;
            }//6382991 - strange arrival time

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[6]", $root);

            // DepCode
            // ArrCode
            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate']) && !empty($itsegment['ArrDate'])) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $this->http->SetBody(html_entity_decode($this->http->Response["body"]));
        $body = $this->http->Response["body"];

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
        $this->http->SetBody(html_entity_decode($this->http->Response["body"]));

        $this->http->FilterHTML = false;
        $itineraries = [];

        foreach ($this->reBody2 as $lang => $re) {
            if (mb_strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
            "#^(\d+)\s*([^\d\s]+)\s+-\s+(\d{1,2}[:\.]?\d{2})$#", //18 dicembre - 20:25; 13OCT - 1145
            "#^(\d+)\s*([^\d\s]+)\s*(\d{4})\s+-\s+(\d+:\d+)$#u", //01 Juni 2016 - 17:30
            "#^(\d+)\s*([^\d\s]+)\s*(\d{4})\s+-\s+(\d+)(\d{2})$#u", //01 Juni 2016 - 1730
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $3, $4",
            "$1 $2 $3, $4:$5",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], "pt")) {//6995890
                $str = str_replace($m[1], $en, $str);
            }
        }

        if (strtotime($str) < $this->date) {
            $str = preg_replace("#\d{4}#", $year + 1, $str);
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

    private function currency($s)
    {
        $sym = [
            '€' => 'EUR',
            '$' => 'USD',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }

        foreach ($sym as $f => $r) {
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
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "normalize-space(.)=\"{$s}\"";
        }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "starts-with(normalize-space(.), \"{$s}\")";
        }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) {
            return "contains(normalize-space(.), \"{$s}\")";
        }, $field));
    }
}
