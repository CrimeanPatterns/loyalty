<?php

namespace AwardWallet\Engine\airnewzealand\Email;

class It5782528 extends \TAccountChecker
{
    public $mailFiles = "airnewzealand/it-5782528.eml";

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    private $reFrom = "@airnz.co.nz";
    private $reSubject = [
        "en"=> "last minute tips for your trip to",
    ];
    private $reBody = '@airnz.co.nz';
    private $reBody2 = [
        "en"=> "Not long until your trip to",
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#(?:^|\s)(\w{6})$#", $this->nextText("Your booking reference"));

        // TripNumber
        // Passengers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        // BaseFare
        // Currency
        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[starts-with(normalize-space(.), 'Depart:')]/ancestor::tr[1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->getField("From:", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->getField("Depart:", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->getField("To:", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->getField("Arrive:", $root)));

            // AirlineName
            $itsegment['AirlineName'] = AIRLINE_UNKNOWN;

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='Seat Selection']/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][2]", $root, true, "#\d+\w$#");

            // Duration
            $itsegment['Duration'] = $this->getField("Duration:", $root);

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
            'emailType'  => 'It5782528',
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

    private function getField($field, $root)
    {
        return $this->http->FindSingleNode(".//text()[starts-with(normalize-space(.),'{$field}')]", $root, true, "#{$field}\s*(.+)#");
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
            "#^(\d+:\d+[ap]m),\s+[^\d\s]+\s+(\d+)\s+([^\d\s]+)$#",
        ];
        $out = [
            "$2 $3 $year, $1",
        ];
        $str = preg_replace($in, $out, $str);
        // if(preg_match("#[^\d\s-\./:]#", $str)) $str = $this->dateStringToEnglish($str);
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
