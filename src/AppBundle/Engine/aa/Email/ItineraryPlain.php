<?php

namespace AwardWallet\Engine\aa\Email;

class ItineraryPlain extends \TAccountCheckerAa
{
    public $mailFiles = "aa/it-14803668.eml";

    public $lang = "en";
    private $reFrom = "@aa.com";
    private $reSubject = [
        "en" => "Itinerary:",
    ];
    private $reBody = 'Thank you for choosing American Airlines';
    private $reBody2 = [
        "en" => "Here is the itinerary you requested",
    ];

    private static $dictionary = [
        "en" => [],
    ];

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
        $body = $this->http->Response["body"];

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
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $text = strip_tags(preg_replace("#<br\s*/?>#i", "\n", $parser->getPlainBody()));
        $its = $this->flight($text);

        $class = explode('\\', __CLASS__);
        $result = [
            'emailType'  => end($class) . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $its,
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

    private function flight(string $text)
    {
        $it = [];
        $it['Kind'] = "T";
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $segments = $this->split("#\n(Flight[ ]*\d+:)#", $text);

        foreach ($segments as $stext) {
            $seg = [];

            // Airline
            if (preg_match("#Flight[ ]*\d+:(.+?)(\d+)\s*\n#", $stext, $m)) {
                $seg['AirlineName'] = trim($m[1]);
                $seg['FlightNumber'] = $m[2];
            }

            if (preg_match("#Departure Date:[ ]*(.+)#", $stext, $m)) {
                $date = trim($m[1]);
            }

            // Departure
            if (preg_match("#Departure Airport:[ ]*([A-Z]{3})\b#", $stext, $m)) {
                $seg['DepCode'] = $m[1];
            }

            if (!empty($date) && preg_match("#Departure Time:[ ]*(.+)#", $stext, $m)) {
                $seg['DepDate'] = strtotime($date . ', ' . $m[1]);
            }

            if (preg_match("#Departure Terminal:[ ]*(?:--|(\S.*))#", $stext, $m)) {
                if (!empty($m[1])) {
                    $seg['DepartureTerminal'] = $m[1];
                }
            }

            // Arrival
            if (preg_match("#Arrival Airport:[ ]*([A-Z]{3})\b#", $stext, $m)) {
                $seg['ArrCode'] = $m[1];
            }

            if (!empty($date) && preg_match("#Arrival Time:[ ]*(.+)#", $stext, $m)) {
                $seg['ArrDate'] = strtotime($date . ', ' . $m[1]);
            }

            if (preg_match("#Arrival Terminal:[ ]*(?:--|(\S.*))#", $stext, $m)) {
                if (!empty($m[1])) {
                    $seg['ArrivalTerminal'] = $m[1];
                }
            }
            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function t($word)
    {
        // $this->http->log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
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
