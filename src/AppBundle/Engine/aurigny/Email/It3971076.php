<?php

namespace AwardWallet\Engine\aurigny\Email;

class It3971076 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;

    public $reFrom = "confirmation@aurigny.com";
    public $reSubject = [
        "en"=> "Your Aurigny Air Services Reservation",
    ];
    public $reBody = 'Aurigny';
    public $reBody2 = [
        "en"=> "Flights",
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
        $it['RecordLocator'] = $this->getField("Your Booking:");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//*[normalize-space(text())='Passenger Name']/ancestor::tr[1]/following-sibling::tr/td[2]"));

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//td[normalize-space(.)='Total']/following-sibling::td[1]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//td[normalize-space(.)='Total']/following-sibling::td[1]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//td[normalize-space(.)='From']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\w{2}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\(([A-Z]{3})#");

            // DepName
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[6]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[4]", $root, true, "#\(([A-Z]{3})#");

            // ArrName
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[7]", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\w{2})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[8]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = implode(", ", array_filter($this->http->FindNodes("//*[normalize-space(text())='Passenger Name']/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[1])='{$itsegment['AirlineName']}{$itsegment['FlightNumber']}']/td[4]")));

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
        }
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'Flight',
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
        return $this->http->FindSingleNode("//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][1]");
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
            "#^(\d+)/(\d+)/(\d+)$#",
        ];
        $out = [
            "$2/$1/20$3",
        ];

        return preg_replace($in, $out, $str);
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
