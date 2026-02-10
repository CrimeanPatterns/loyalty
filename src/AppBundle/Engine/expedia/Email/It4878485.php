<?php

namespace AwardWallet\Engine\expedia\Email;

class It4878485 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "expedia/it-4878485.eml";

    public $reFrom = "booking@axisrooms.com";
    public $reSubject = [
        "en"=> "CANCELLATION on Expedia",
    ];
    public $reBody = 'Expedia';
    public $reBody2 = [
        "en"=> "We have received a",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->http->FindSingleNode("//text()[contains(., 'Confirmation Voucher Number')]", null, true, "#Confirmation Voucher Number\s*:\s*(\w+)#");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->nextText("Hotel Name");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->nextText("Check In")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->nextText("Check Out")));

        // Address
        $it['Address'] = $this->nextText("Address");

        // DetailedAddress

        // Phone
        // Fax
        // GuestNames
        $Guest = $this->nextText("Guest Name");

        if (!empty($Guest) > 0) {
            $it['GuestNames'] = [$Guest];
        }

        // Guests
        $it['Guests'] = $this->nextText("Adults");

        // Kids
        // Rooms
        $it['Rooms'] = $this->nextText("Number Of Rooms");

        // Rate
        // RateType
        $it['RateType'] = $this->nextText("Rate Plan Name");

        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#(.*?)/#", $this->nextText("Room Name"));

        // RoomTypeDescription
        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->cost($this->nextText("Total Amount"));

        // Currency
        $it['Currency'] = $this->currency($this->nextText("Total Amount"));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        $it['Status'] = $this->nextText("We have received a");

        // Cancelled
        if ($this->nextText("Cancellation Time")) {
            $it['Cancelled'] = true;
        }

        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->nextText("Booking Time")));

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
            "#^(\d+)/(\d+)/(\d{4})$#",
            "#^(\d+)/(\d+)/(\d{4})\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3",
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]{2,}#", $str)) {
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
}
