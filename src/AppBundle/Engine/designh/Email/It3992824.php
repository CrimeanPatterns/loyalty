<?php

namespace AwardWallet\Engine\designh\Email;

class It3992824 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "ybartens@deloitte.de"; // [?]
    public $reSubject = [
        "de"=> "Ihre Reservierungsbestätigung",
    ];
    public $reBody = 'deloitte.com';
    public $reBody2 = [
        "de"=> "IHRE RESERVIERUNGSBESTÄTIGUNG",
    ];

    public static $dictionary = [
        "de" => [],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "R";

        // ConfirmationNumber
        $it['ConfirmationNumber'] = $this->getField("Bestätigungsnummer");

        // TripNumber
        // ConfirmationNumbers

        // Hotel Name
        $it['HotelName'] = $this->http->FindSingleNode("//text()[contains(., 'dass Sie sich für das')]", null, true, "#dass Sie sich für das\s+(.*?)\s+entschieden haben#");

        // 2ChainName

        // CheckInDate
        $it['CheckInDate'] = strtotime($this->normalizeDate($this->getField("Anreisedatum") . ', ' . $this->getField("Anreise")));

        // CheckOutDate
        $it['CheckOutDate'] = strtotime($this->normalizeDate($this->getField("Abreisedatum") . ', ' . $this->getField("Abreise")));

        // Address
        $it['Address'] = $this->http->FindSingleNode("//a[contains(@href, 'www.facebook.com')]/ancestor::table[1]/../table[1]/descendant::text()[normalize-space(.)][1]");

        // DetailedAddress

        // Phone
        $it['Phone'] = trim($this->http->FindSingleNode("//a[contains(@href, 'www.facebook.com')]/ancestor::table[1]/../table[1]", null, true, "#T\s+([\d\+\s]+)#"));

        // Fax
        $it['Fax'] = trim($this->http->FindSingleNode("//a[contains(@href, 'www.facebook.com')]/ancestor::table[1]/../table[1]", null, true, "#F\s+([\d\+\s]+)#"));

        // GuestNames
        $it['GuestNames'] = [$this->getField("Gastname")];

        // Guests
        $it['Guests'] = $this->re("#(\d+)\s+Erwachsener#", $this->getField("Personenanzahl"));

        // Kids
        // Rooms
        $it['Rooms'] = $this->re("#(\d+)#", $this->getField("Zimmerkategorie"));

        // Rate
        $it['Rate'] = $this->getField("Rate pro Nacht");

        // RateType
        // CancellationPolicy
        // RoomType
        $it['RoomType'] = $this->re("#\d+\s+(.+)#", $this->getField("Zimmerkategorie"));

        // RoomTypeDescription
        $it['RoomTypeDescription'] = $this->getField("Inklusive");

        // Cost
        // Taxes
        // Total
        $it['Total'] = $this->cost($this->getField("Gesamtpreis"));

        // Currency
        $it['Currency'] = $this->currency($this->getField("Gesamtpreis"));

        // SpentAwards
        // EarnedAwards
        // AccountNumbers
        // Status
        // Cancelled
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'RommersHotel',
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[last()]");
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
            "#^[^\s\W]+,\s+(\d+)\.\s+([^\s\W]+)\s+(\d{4}),\s+(?:Ab|Bis)\s+(\d+:\d+)\s+Uhr$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        return $this->dateStringToEnglish(preg_replace($in, $out, $str));
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
