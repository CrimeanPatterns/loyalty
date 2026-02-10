<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;

class BillingReceiptForReservation2014 extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-1586917.eml, airbnb/it-1681111.eml, airbnb/it-1681112.eml, airbnb/it-1681149.eml, airbnb/it-1689671.eml, airbnb/it-1950398.eml, airbnb/it-1955300.eml, airbnb/it-1955484.eml, airbnb/it-1955625.eml, airbnb/it-2253941.eml";
    public $reFrom = "@airbnb.com";
    public $reSubject = [
        "en"=> "Billing receipt for reservation",
        "da"=> "Kvittering for reservation",
    ];
    public $reBody = 'AIRBNB';
    public $reBody2 = [
        "en"=> "Customer Receipt",
        "da"=> "Kundekvittering",
    ];

    public static $dictionary = [
        "en" => [
            "Confirmation Code:"=> ["Confirmation Code:", "CONFIRMATION CODE:"],
            "ACCOMMODATIONS"    => ["ACCOMMODATIONS", "Accommodations"],
            "TOTAL"             => ["TOTAL", "Total"],
        ],
        "da" => [
            "Confirmation Code:"=> ["Bekræftelseskode:"],
            "ACCOMMODATIONS"    => ["OVERNATNINGSMULIGHEDER", "Overnatningsmuligheder"],
            "TOTAL"             => ["TOTAL", "Total"],
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Confirmation Code:")) . "]", null, true, "#" . $this->opt($this->t("Confirmation Code:")) . "\s+(.+)#");

        // ReservationDate
        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//img[contains(@src, 'icons/beveled-icons-medium-house')]/ancestor::td[1]/following-sibling::td[1]");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'icons/beveled-icons-medium-checkin')]/ancestor::td[1]/following-sibling::td[1]")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//img[contains(@src, 'icons/beveled-icons-medium-checkout')]/ancestor::td[1]/following-sibling::td[1]")));

        // Address
        $it['Address'] = implode(" ", $this->http->FindNodes("//img[contains(@src, 'icons/beveled-icons-medium-directions')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"));

        // DetailedAddress
        // Phone
        // Fax
        // GuestNames
        $it['GuestNames'] = array_filter($this->http->FindNodes("//img[contains(@src, 'icons/beveled-icons-medium-user')]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]"), function ($s) { return !preg_match("#[\d@]#", $s); });

        // Guests
        // Kids
        // Rooms
        // Rate
        $it['Rate'] = $this->re("#\((.*?)\)#", $this->http->FindSingleNode("//text()[" . $this->eq($this->t("ACCOMMODATIONS")) . "]/ancestor::tr[1]/*[2]"));

        // RateType
        // Fees
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->http->FindSingleNode("//img[contains(@src, 'icons/beveled-icons-medium-buildings')]/ancestor::td[1]/following-sibling::td[1]");

        // RoomTypeDescription
        // Cost
        $it['Cost'] = $this->amount($this->nextText($this->t("ACCOMMODATIONS")));

        // Taxes
        // Total
        $it['Total'] = $this->amount($this->nextText($this->t("TOTAL")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("TOTAL")));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled

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
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})\s*(\d+:\d+ [AP]M)(?: \(noon\))?$#", //Tue, June 24, 201412:00 PM (noon)
            "#^[^\s\d]+, ([^\s\d]+) (\d+), (\d{4})\s*(Flexible check in time|Flexible check out time)$#", //Sun, July 13, 2014Flexible check out time
            "#^[^\s\d]+, (\d+)\. ([^\s\d]+) (\d{4})(\d+:\d+)$#", //to, 24. juli 201415:00
            "#^[^\s\d]+, (\d+)\. ([^\s\d]+) (\d{4})\s*(Fleksibel check-ind tid|Fleksibel check-ud tid)$#", //Sun, July 13, 2014Flexible check out time
        ];
        $out = [
            "$2 $1 $3, $4",
            "$2 $1 $3",
            "$1 $2 $3, $4",
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
