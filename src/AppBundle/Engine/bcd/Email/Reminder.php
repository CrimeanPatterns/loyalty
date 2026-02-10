<?php

namespace AwardWallet\Engine\bcd\Email;

use AwardWallet\Engine\MonthTranslate;

class Reminder extends \TAccountChecker
{
    public $mailFiles = "bcd/it-8699930.eml";
    public $reFrom = "@BCDTravel.com";
    public $reSubject = [
        "en"=> "Reminder:",
    ];
    public $reBody = 'BCD Travel';
    public $reBody2 = [
        "en"=> "Please remember",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        foreach ($this->http->XPath->query("//text()[starts-with(normalize-space(.), 'HOTEL -')]/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr[string-length(normalize-space(.))>1][1]") as $root) {
            $it = [];

            $it['Kind'] = "R";

            // ConfirmationNumber
            $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[" . $this->eq("Confirmation:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // TripNumber
            // ConfirmationNumbers

            // Hotel Name
            $it['HotelName'] = $this->http->FindSingleNode("//text()[" . $this->eq("Address:") . "]/preceding::text()[normalize-space(.)][1]", $root);

            // 2ChainName

            // CheckInDate
            $it['CheckInDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check In/Check Out:") . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#(.*?)\s+-#")));

            // CheckOutDate
            $it['CheckOutDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("//text()[" . $this->eq("Check In/Check Out:") . "]/ancestor::td[1]/following-sibling::td[1]", $root, true, "#\s+-\s+(.+)#")));

            // Address
            $it['Address'] = implode(", ", $this->http->FindNodes("//text()[" . $this->eq("Address:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[normalize-space(.)]", $root));

            // DetailedAddress

            // Phone
            $it['Phone'] = $this->http->FindSingleNode("//text()[" . $this->eq("Tel:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Fax
            $it['Fax'] = $this->http->FindSingleNode("//text()[" . $this->eq("Fax:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // GuestNames

            // Guests
            $it['Guests'] = $this->http->FindSingleNode("//text()[" . $this->eq("Number of Persons:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Kids
            // Rooms
            $it['Rooms'] = $this->http->FindSingleNode("//text()[" . $this->eq("Number of Rooms:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Rate
            $it['Rate'] = $this->http->FindSingleNode("//text()[" . $this->eq("Rate per night:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // RateType

            // CancellationPolicy
            $it['CancellationPolicy'] = $this->http->FindSingleNode("//text()[" . $this->eq("Remarks:") . "]/ancestor::td[1]/following-sibling::td[1]/descendant::text()[" . $this->contains("CANCEL") . "]", $root);

            // RoomType
            $it['RoomType'] = $this->http->FindSingleNode("//text()[" . $this->eq("Additional Information:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // RoomTypeDescription
            // Cost
            // Taxes
            // Total
            // Currency
            // SpentAwards
            // EarnedAwards
            // AccountNumbers
            // Status
            $it['Status'] = $this->http->FindSingleNode("//text()[" . $this->eq("Status:") . "]/ancestor::td[1]/following-sibling::td[1]", $root);

            // Cancelled
            // ReservationDate
            // NoItineraries
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
            "#^[^\s\d]+, ([^\s\d]+) (\d+) (\d{4})$#", //Tuesday, September 26 2017
        ];
        $out = [
            "$2 $1 $3",
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
