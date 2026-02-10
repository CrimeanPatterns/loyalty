<?php

namespace AwardWallet\Engine\aeroplan\Email;

class It4103121 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aeroplan/it-4103121.eml, aeroplan/it-4107653.eml, aeroplan/it-4134407.eml";

    public $reFrom = "news@email.aircanada.com";
    public $reSubject = [
        "en"=> "check in for Flight",
    ];
    public $reBody = 'Air Canada';
    public $reBody2 = [
        "en"=> "You can now check in for your flight",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $flight = $this->http->FindSingleNode("//text()[contains(., 'You can now check in for your flight')]");
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#\(([A-Z0-9]{6})\)#", $flight);

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Dear")];

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
        $date = strtotime($this->normalizeDate($this->re("#on\s+(\d+-\w+-\d{2})\s+at#", $flight)));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#for your flight\s+(\d+)#", $flight);

        // DepCode
        $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

        // DepName
        $itsegment['DepName'] = $this->re("#departing\s+(.*?)\s+on#", $flight);

        // DepDate
        $itsegment['DepDate'] = strtotime($this->re("#at\s+(\d+:\d+)#", $flight), $date);

        // ArrCode
        $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

        // ArrName
        $itsegment['ArrName'] = $this->re("#arriving at\s+(.*?)\s+(\(|at)#", $flight);

        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->re("#at\s+(\d+:\d+)\.$#", $flight), $date);

        // AirlineName
        $itsegment['AirlineName'] = AIRLINE_UNKNOWN;

        // Operator
        // Aircraft
        // TraveledMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
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
            "#^\w+\s+-\s+\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+,\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year, $3",
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
