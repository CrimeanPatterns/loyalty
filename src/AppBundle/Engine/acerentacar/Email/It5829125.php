<?php

namespace AwardWallet\Engine\acerentacar\Email;

class It5829125 extends \TAccountChecker
{
    public $reFrom = "@acerentacar.com";
    public $reSubject = [
        "en"=> "Online Booking Confirmation - Ace Rental Cars",
    ];
    public $reBody = 'acerentals.com';
    public $reBody2 = [
        "en"=> "Booking Reference",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $puroot = $this->http->XPath->query("//text()[normalize-space(.)='Pick-up']/ancestor::tr[1]/following-sibling::tr[last()]")->item(0);
        $doroot = $this->http->XPath->query("//text()[normalize-space(.)='Drop-off']/ancestor::tr[1]/following-sibling::tr[last()]")->item(0);
        $carroot = $this->http->XPath->query("//text()[normalize-space(.)='Your Rental Car']/ancestor::tr[1]/following-sibling::tr[last()]")->item(0);

        $it = [];

        $it['Kind'] = "L";

        // Number
        $it['Number'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.),'Booking Reference ')]", null, true, "#Booking Reference \#([\d\w-]+)#");
        // TripNumber
        // PickupDatetime
        $it['PickupDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $puroot)));

        // PickupLocation
        $it['PickupLocation'] = $this->http->FindSingleNode(".//img[contains(@src, '/address.jpg')]/following::text()[normalize-space(.)][1]", $puroot);

        // DropoffDatetime
        $it['DropoffDatetime'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $doroot)));

        // DropoffLocation
        $it['DropoffLocation'] = $this->http->FindSingleNode(".//img[contains(@src, '/address.jpg')]/following::text()[normalize-space(.)][1]", $doroot);

        // PickupPhone
        $it['PickupPhone'] = $this->http->FindSingleNode(".//img[contains(@src, '/call.jpg')]/following::text()[normalize-space(.)][1]", $puroot);

        // PickupFax
        // PickupHours
        $it['PickupHours'] = $this->http->FindSingleNode(".//img[contains(@src, '/time.jpg')]/following::text()[normalize-space(.)][1]", $puroot);

        // DropoffPhone
        $it['DropoffPhone'] = $this->http->FindSingleNode(".//img[contains(@src, '/call.jpg')]/following::text()[normalize-space(.)][1]", $doroot);

        // DropoffHours
        $it['DropoffHours'] = $this->http->FindSingleNode(".//img[contains(@src, '/time.jpg')]/following::text()[normalize-space(.)][1]", $doroot);

        // DropoffFax
        // RentalCompany
        // CarType
        $it['CarType'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $carroot);

        // CarModel
        // CarImageUrl
        // RenterName
        // PromoCode
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'Total:')]", $carroot, true, "#Total:\s+\D([\d\.\,]+)#"));

        // Currency
        $it['Currency'] = $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'Total:')]", $carroot, true, "#Total:\s+(\D)[\d\.\,]+#") == '$' ? 'USD' : '';

        // TotalTaxAmount
        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
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
        $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

        foreach ($this->reBody2 as $lang=> $re) {
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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $3, $4",
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
}
