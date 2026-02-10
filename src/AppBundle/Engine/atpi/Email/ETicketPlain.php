<?php

namespace AwardWallet\Engine\atpi\Email;

use AwardWallet\Engine\MonthTranslate;

class ETicketPlain extends \TAccountChecker
{
    public $mailFiles = "atpi/it-11980442.eml";
    public $reFrom = "@atpi.com";
    public $reSubject = [
        "en"=> "E-ticket",
    ];
    public $reBody = 'www.atpi.com';
    public $reBody2 = [
        "en"=> "Your travel itinerary",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parsePlain(&$itineraries)
    {
        $text = $this->text;
        $airs = [];
        $flights = $this->re("#\nTravellers.*?\n\n(.*?)\nBooked For#ms", $text);
        $segments = $this->split("#([^\s\d]+, \d+ [^\s\d]+ \d{4})#", $flights);

        foreach ($segments as $stext) {
            if (!$rl = $this->re("#Airline reference\s+(.+)#", $stext)) {
                $this->logger->alert('RL not matched!');

                return;
            }
            $airs[$rl][] = $stext;
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];
            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            preg_match_all("#\n(.*?)(?:\s{2,}|$)#m", $this->re("#\nTravellers[^\n]+(\n.*?)\n\n#s", $text), $m);
            $it['Passengers'] = $m[1];

            // TicketNumbers
            preg_match_all("#\s{2,}(\d{10,14})\s#", $this->re("#\nBooked For[^\n]+(\n.*?)\n\n#s", $text), $m);
            $it['TicketNumbers'] = $m[1];

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

            foreach ($segments as $stext) {
                $date = strtotime($this->normalizeDate($this->re("#^(.*?)\s{2,}#", $stext)));
                $itsegment = [];

                if (preg_match("#Flight\s+(?<AirlineName>[^\s\d]+) (?<FlightNumber>\d+)#", $text, $m)) {
                    // FlightNumber
                    $itsegment['FlightNumber'] = $m['FlightNumber'];

                    // AirlineName
                    $itsegment['AirlineName'] = $m['AirlineName'];
                }

                if (preg_match("#Departs\s+(?<Time>\d+:\d+) HRS\s+(?<Name>.*?)\s+(?<Code>[A-Z]{3})\s#", $text, $m)) {
                    // DepCode
                    $itsegment['DepCode'] = $m['Code'];

                    // DepName
                    $itsegment['DepName'] = $m['Name'];

                    // DepartureTerminal

                    // DepDate
                    $itsegment['DepDate'] = strtotime($m['Time'], $date);
                }

                if (preg_match("#Arrives\s+(?<Time>\d+:\d+) HRS\s+(?<Name>.*?)\s+(?<Code>[A-Z]{3})\s#", $text, $m)) {
                    // ArrCode
                    $itsegment['ArrCode'] = $m['Code'];

                    // ArrName
                    $itsegment['ArrName'] = $m['Name'];

                    // ArrivalTerminal

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime($m['Time'], $date);
                }
                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->re("#Equipment\s+(.+)#", $stext);

                // TraveledMiles
                // AwardMiles
                // Cabin
                // BookingClass
                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->re("#Flight time\s+(.+)#", $stext);

                // Meal
                $itsegment['Meal'] = $this->re("#Offered meal\s+(.+)#", $stext);

                // Smoking
                // Stops
                $itsegment['Stops'] = $this->re("#Stopover\s+(.+)#", $stext);

                $it['TripSegments'][] = $itsegment;
            }
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

        $this->http->FilterHTML = false;
        $itineraries = [];
        $this->text = $parser->getPlainBody();

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->text, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parsePlain($itineraries);

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
            "#^([^\s\d]+)\s+(\d+),\s+(\d+:\d+)\s+([ap])\.m\.$#", //August 23, 7:50 p.m.
        ];
        $out = [
            "$2 $1 $year, $3 $4m",
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

    private function split($re, $text)
    {
        $r = preg_split($re, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $ret = [];

        if (count($r) > 1) {
            array_shift($r);

            for ($i = 0; $i < count($r) - 1; $i += 2) {
                $ret[] = $r[$i] . $r[$i + 1];
            }
        } elseif (count($r) == 1) {
            $ret[] = reset($r);
        }

        return $ret;
    }
}
