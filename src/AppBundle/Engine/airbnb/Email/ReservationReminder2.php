<?php

namespace AwardWallet\Engine\airbnb\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationReminder2 extends \TAccountChecker
{
    public $mailFiles = "airbnb/it-1937784.eml, airbnb/it-1937936.eml, airbnb/it-1955382.eml, airbnb/it-1955417.eml, airbnb/it-1955469.eml, airbnb/it-1961280.eml, airbnb/it-9688025.eml, airbnb/it-9733334.eml, airbnb/it-9741717.eml";
    public $reFrom = "@airbnb.com";
    public $reSubject = [
        "en"=> "Reservation reminder",
        "da"=> "Reservationspåmindelse",
    ];
    public $reBody = 'Airbnb';
    public $reBody2 = [
        "en"=> "reminder that you",
        "da"=> "Dette er en påmindelse om",
    ];

    public static $dictionary = [
        "en" => [
            "Listing Address"=> ["Listing Address", "Listing Details"],
            "Arrive"         => ["Arrive", "Check In", "Check-in"],
            "Depart"         => ["Depart", "Check Out", "Checkout", "Check-out"],
        ],
        "da" => [
            "Listing Address"=> "Opslagets detaljer",
            "Arrive"         => ["Tjek ind", "Ankommer"],
            "Depart"         => ["Check ud", "Tager af sted", "Rejser"],
            "Host"           => "Vært",
            "Hi "            => "Hej ",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = CONFNO_UNKNOWN;

        // ReservationDate
        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Listing Address")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][1]");

        // 2ChainName

        // CheckInDate
        if (!$date = trim(implode(" ", $this->http->FindNodes("//tr[" . $this->eq($this->t("Arrive")) . "]/following-sibling::tr[position()<3]")))) {
            $date = $this->nextText($this->t("Arrive"));
        }
        $it['CheckInDate'] = strtotime($this->normalizeDate($date));

        if ($time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Arrive")) . "]/ancestor::td[1]/span[2]", null, true, "#^(\d+:\d+(\s*[ap]m)?)#i")) {
            $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
        }

        if ($time = $this->http->FindSingleNode("///tr[" . $this->eq($this->t("Arrive")) . "]/following-sibling::tr[3]", null, true, "#^(\d+:\d+(\s*[ap]m)?)#i")) {
            $it['CheckInDate'] = strtotime($time, $it['CheckInDate']);
        }

        // CheckOutDate
        if (!$date = trim(implode(" ", $this->http->FindNodes("//tr[" . $this->eq($this->t("Depart")) . "]/following-sibling::tr[position()<3]")))) {
            $date = $this->nextText($this->t("Depart"));
        }
        $it['CheckOutDate'] = strtotime($this->normalizeDate($date));

        if ($time = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Depart")) . "]/ancestor::td[1]/span[2]", null, true, "#^(\d+:\d+(\s*[ap]m)?)#i")) {
            $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
        }

        if ($time = $this->http->FindSingleNode("///tr[" . $this->eq($this->t("Depart")) . "]/following-sibling::tr[3]", null, true, "#^(\d+:\d+(\s*[ap]m)?)#i")) {
            $it['CheckOutDate'] = strtotime($time, $it['CheckOutDate']);
        }

        // Address
        $it['Address'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Listing Address")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][2]");

        // DetailedAddress
        // Phone
        $it['Phone'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("Host")) . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)][last()]");

        // Fax
        // GuestNames
        $it['GuestNames'] = [trim($this->http->FindSingleNode("//text()[" . $this->starts($this->t("Hi ")) . "]", null, true, "#" . $this->opt($this->t("Hi ")) . "(.+)#"), ', ')];

        // Guests
        // Kids
        // Rooms
        // Rate
        // RateType
        // Fees
        // CancellationPolicy
        // RoomType
        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        // Currency
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
        $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d.,]+, ([^\s\d.,]+) (\d+), (\d{4})$#", //Thu, October 30, 2014
            "#^[^\s\d.,]+, ([^\s\d.,]+) (\d+)$#", //Wed, Jul 23
            "#^[^\s\d.,]+, (\d+) ([^\s\d]+), (\d{4})$#", //Sun, 24 August, 2014
            "#^[^\s\d.,]+, (\d+)\. ([^\s\d.,]+) (\d{4})$#", //ti, 8. juli 2014
        ];
        $out = [
            "$2 $1 $3",
            "$2 $1 $year",
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s.,]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        $this->http->log($str);

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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
