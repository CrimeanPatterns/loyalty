<?php

namespace AwardWallet\Engine\avis\Email;

use AwardWallet\Engine\MonthTranslate;

class Confirmation extends \TAccountChecker
{
    public $mailFiles = "";
    public $reFrom = "reservations@avis";
    public $reSubject = [
        "de"=> "Reservierungsbestätigung",
    ];
    public $reBody = 'Avis';
    public $reBody2 = [
        "de"=> "Reservierungsbestätigung",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->nextText("Reservierungsnr.:");
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->nextText("Anmietdatum:")));

        // PickupLocation
        $it['PickupLocation'] = $this->http->FindSingleNode("//text()[" . $this->eq("Anmietstation:") . "]/ancestor::td[1]/following-sibling::td[1]");

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->nextText("Rückgabedatum:")));

        // DropoffLocation
        $it['DropoffLocation'] = $this->http->FindSingleNode("//text()[" . $this->eq("Rückgabestation:") . "]/ancestor::td[1]/following-sibling::td[1]");

        // PickupPhone
        // PickupFax
        // PickupHours
        $it['PickupHours'] = $this->nextText("Öffnungszeiten am Anmiettag:");

        // DropoffPhone
        // DropoffHours
        $it['DropoffHours'] = $this->nextText("Öffnungszeiten am Rückgabetag:");

        // DropoffFax
        // RentalCompany
        // CarType
        $it['CarType'] = $this->re("#(.*?)\s+\(#", $this->nextText("Fahrzeuggruppe:"));

        // CarModel
        $it['CarModel'] = $this->re("#\((.*?)\)#", $this->nextText("Fahrzeuggruppe:"));

        // CarImageUrl
        // RenterName
        $it['RenterName'] = $this->nextText("Name:");

        // PromoCode
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText("Preis: (inkl. MwSt.)"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Preis: (inkl. MwSt.)"));

        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
        // ServiceLevel
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

        $result = [
            'emailType'  => end(explode('\\', __CLASS__)) . ucfirst($this->lang),
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
            "#^[^\d\s]+\s+(\d+\.\d+.\d{4})\s+(\d+:\d+)\s+Uhr$#", //Montag 07.08.2017 19:10 Uhr
        ];
        $out = [
            "$1, $2",
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
