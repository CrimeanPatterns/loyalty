<?php

namespace AwardWallet\Engine\austrian\Email;

class It4487702 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "austrian/it-4487702.eml";

    public $reFrom = "no-reply@austrian.com";
    public $reSubject = [
        "en"=> "Your Austrian booking confirmation",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en"=> "Thank you for your booking",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $partstr = array_filter(preg_split("#(?:= = = = = = = = = = = = = = = = = = = = = = = = =|\n\s*\n)#", $this->re("#Your booking code is:\s*\w+\s+(.+)#ms", $this->text)));
        $parts = [];

        foreach ($partstr as $part) {
            if (preg_match("#(.*?)\n(.+)#ms", trim($part), $part)) {
                $parts[trim($part[1], ": ")] = trim($part[2]);
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->re("#Your booking code is:\s*(\w+)#");

        // TripNumber
        // Passengers
        if (isset($parts["Passengers"])) {
            if (preg_match_all("#^\w+:\s*(.+)#", $parts["Passengers"], $passangers)) {
                $it['Passengers'] = $passangers[1];
            }
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->re("#Total price:\s*(.+)#"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->re("#Total price:\s*(.+)#"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $segments = [];

        foreach ($parts as $key=>$cont) {
            if (preg_match("#Trip \d+|(?:Outbound|Return) flight#", $key)) {
                $segments[] = $cont;
            }
        }

        foreach ($segments as $text) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->re("#Flight:\s+\w{2}\s+(\d+)#", $text);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->re("#From:\s+(.+)#", $text);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->re("#Departure:\s+(.*?)\s+\(#", $text));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->re("#To:\s+(.+)#", $text);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->re("#Arrival:\s+(.*?)\s+\(#", $text));

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#Flight:\s+(\w{2})\s+\d+#", $text);

            // Operator
            $itsegment['Operator'] = $this->re("#Flight:\s+\w{2}\s+\d+\s+(.+)#", $text);

            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->re("#Tariff:\s+(.*?)\s*/#", $text);

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#Tariff:\s+.*?\s*/\s*(\w)#", $text);

            // PendingUpgradeTo
            // Seats
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
        $body = $parser->getPlainBody();

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
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
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
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }
}
