<?php

namespace AwardWallet\Engine\fcmtravel\Email;

class It3899801 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "fcmtravel/it-3899801.eml";

    public $reFrom = "@cn.fcm.travel";
    public $reBody = [
        "en"=> "Today's Date",
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
        $it['RecordLocator'] = $this->getField("Reservation ID");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='Traveler' or normalize-space(.)='Traveler /']/ancestor::td[1]/following-sibling::td[1]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it["TotalCharge"] = $this->cost($this->getField("Total Price/Traveler"));

        // BaseFare
        // Currency
        $it["Currency"] = $this->currency($this->getField("Total Price/Traveler"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        $it['ReservationDate'] = strtotime($this->normalizeDate($this->getField("Today's Date")));

        // NoItineraries
        // TripCategory

        $xpath = "//text()[contains(., 'Depart')]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[contains(., 'Flight - ')]/ancestor::td[1]/following-sibling::td[2]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[contains(., 'Flight - ')]", $root, true, "#\w{2}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->getField("Depart", $root, 2);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->getField("Depart", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->getField("Arrive", $root, 2);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->getField("Arrive", $root), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[contains(., 'Flight - ')]", $root, true, "#(\w{2})\d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->getField("Aircraft Type", $root);

            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->getField("Class of Service", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->http->FindSingleNode("//text()[normalize-space(.)='Seat' or normalize-space(.)='Seat /']/ancestor::strong[1]/following-sibling::span[1]", $root);

            // Duration
            $itsegment['Duration'] = $this->getField("Flying Time", $root);

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
        if (strpos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        return true;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (!$this->http->FindSingleNode("//text()[contains(., \"Today's Date\")]/ancestor::tr[1]/following-sibling::tr[2][contains(., 'Agent')]")) {
            return false;
        }

        foreach ($this->reBody as $re) {
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

        foreach ($this->reBody as $lang=>$re) {
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

    private function getField($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode(".//text()[normalize-space(.)=\"{$field}\" or normalize-space(.)=\"{$field} /\"]/ancestor::td[1]/following-sibling::td[{$n}]", $root, true, "#(.*?)(?: / |$)#");
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
            "#^(\d+)([^\d\W]+)(\d{4})\(\w+\)$#",
        ];
        $out = [
            "$1 $2 $3",
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
