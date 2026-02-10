<?php

namespace AwardWallet\Engine\alamo\Email;

class It6091105 extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "reservations@goalamo.com";
    public $reSubject = [
        "en"=> "Updated Alamo Reservation Confirmation",
    ];
    public $reBody = 'Alamo';
    public $reBody2 = [
        "en"  => "Itinerary",
        "en2" => "Updated Alamo Reservation Confirmation",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText($this->t("Confirmation Number:"));
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText($this->t("Pick-up")) . ', ' . $this->nextText($this->t("Pick-up"), null, 2)));

        // PickupLocation
        $it['PickupLocation'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Pick-up")) . "]/following::table[1]/descendant::tr[1]/../tr[position()=1 or position()=2]/td[1]"));

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq($this->t("Return")) . "]/ancestor::tr[1]/following-sibling::tr[1]") . ', ' . $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Return")) . "]/ancestor::tr[1]/following-sibling::tr[2]")));

        // DropoffLocation
        $it['DropoffLocation'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Return")) . "]/following::table[1]/descendant::tr[1]/../tr[position()=1 or position()=2]/td[1]"));

        // PickupPhone
        $it['PickupPhone'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Pick-up")) . "]/following::table[1]/descendant::tr[1]/../tr[3]/td[1]"));

        // PickupFax
        // PickupHours
        $it['PickupHours'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Pick-up")) . "]/following::table[1]/descendant::tr[1]/td[2]"));

        // DropoffPhone
        $it['DropoffPhone'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Return")) . "]/following::table[1]/descendant::tr[1]/../tr[3]/td[1]"));

        // DropoffHours
        $it['DropoffHours'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq($this->t("Return")) . "]/following::table[1]/descendant::tr[1]/td[2]"));

        // DropoffFax
        // RentalCompany
        // CarType
        $it['CarType'] = $this->nextText($this->t("Vehicle"));

        // CarModel
        $it['CarModel'] = $this->nextText($this->t("Vehicle"), null, 2);

        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Driver Name:")) . "]/ancestor::td[1]", null, true, "#" . $this->t("Driver Name:") . "\s+(.+)#");

        // PromoCode
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Estimated Total")) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, true, "#([\d\,\.]+)#"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Estimated Total")));

        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Your reservation has been")) . "]", null, true, "#" . $this->t("Your reservation has been") . "\s+(\w+)#");

        // ServiceLevel
        // Cancelled
        // PricedEquips
        // Discount
        // Discounts
        // Fees
        // ReservationDate
        // NoItineraries
        $itineraries[] = $it;
    }

    public function detectEmailFromProvider($from)
    {
        return strpos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
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
            "#^([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
