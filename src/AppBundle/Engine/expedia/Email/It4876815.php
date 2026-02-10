<?php

namespace AwardWallet\Engine\expedia\Email;

class It4876815 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "expedia/it-4876815.eml";

    public $reFrom = "expedia@expediamail.com";
    public $reSubject = [
        "en"=> "Expedia travel confirmation",
    ];
    public $reBody = 'Expedia';
    public $reBody2 = [
        "en"=> "Your reservation is booked and confirmed",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $text = "";

        foreach ($this->http->XPath->query("//text()[normalize-space(.)]") as $node) {
            $text .= $node->nodeValue . "\n";
        }

        // record locators
        $r = explode("\n", trim($this->re("#\n\s*CONFIRMED\s*\n(.*?)Your reservation is booked and confirmed#msi", $text)));
        $rls = [];

        foreach ($r as $row) {
            if (preg_match("#^(.*?)\s+(\w{6})$#", trim($row), $m)) {
                if (strpos($m[1], 'Expedia') !== false) {
                    $m[1] = 'Expedia';
                }
                $rls[$m[1]] = $m[2];
            }
        }

        if (count($rls) == 0) {
            return null;
        }

        // dates
        $arr = $this->splitter("#((?:\*\d+/\d+/\d{4}\s*\*|\w+\s+\d+,\s+\d{4})\s+-\s+(?:Departure|Return))#msi", $text);
        $dates = [];

        foreach ($arr as $part) {
            preg_match_all("#(?:" . implode("|", array_keys($rls)) . ")\s+(\d+)#", $part, $m);
            $date = $this->re("#^(.*?)\s+-#", $part);

            foreach ($m[1] as $fl) {
                $dates[$fl] = $date;
            }
        }

        // segments
        preg_match_all("#(?<DepCode>[A-Z]{3})\s+(?<DepTime>\d+:\d+(?:\s*[ap]m)?)\s+" .
        "(?:Terminal\s+(?<DepartureTerminal>\w+)\s+)?" .
        "(?<ArrCode>[A-Z]{3})\s+(?<ArrTime>\d+:\d+(?:\s*[ap]m)?)(?:\s+\+1\s+day)?\s+" .
        "(?:Terminal\s+(?<ArrivalTerminal>\w+)\s+)?" .
        "(?:[\*\(][^\n]+[\*\)]\s+)*" .
        "(?<AirlineName>" . implode("|", array_keys($rls)) . ")\s+(?<FlightNumber>\d+)\s+" .
        "(?<Cabin>[^\n]+)\s*/\s*Coach\s+\((?<BookingClass>\w)\)\s+\|\s+" .
        "(?:Seat\s+(?<Seats>.*?)\s+\|)?#ms", $text, $segments, PREG_SET_ORDER);

        $airs = [];

        foreach ($segments as $segment) {
            if (!isset($dates[$segment['FlightNumber']])) {
                return null;
            }
            $segment['Date'] = $dates[$segment['FlightNumber']];

            if (isset($rls[$segment['AirlineName']])) {
                $airs[$rls[$segment['AirlineName']]][] = $segment;
            } elseif (isset($rls['Expedia'])) {
                $airs[$rls['Expedia']][] = $segment;
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

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

            foreach ($segments as $segment) {
                $date = strtotime($this->normalizeDate($segment['Date']));

                $itsegment = [];
                $keys = [
                    "FlightNumber",
                    "AirlineName",
                    "DepCode",
                    "ArrCode",
                    "DepartureTerminal",
                    "ArrivalTerminal",
                    "Cabin",
                    "BookingClass",
                    "Seats",
                ];

                foreach ($keys as $key) {
                    if (isset($segment[$key]) && !empty($segment[$key])) {
                        $itsegment[$key] = preg_replace("#\s+#", " ", $segment[$key]);
                    }
                }

                $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);

                if (isset($lastdate) && $itsegment['DepDate'] < $lastdate) {
                    $itsegment['DepDate'] = strtotime("+1 day", $itsegment['DepDate']);
                }

                $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);
                //				if($itsegment['ArrDate']<$itsegment['DepDate'])
                //					$itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
                $lastdate = $itsegment['ArrDate'];

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
        $body = $parser->getBody();

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
        $this->http->setBody($parser->getBody());

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
            "#^\*(\d+)/(\d+)/(\d{4})\s*\*$#",
        ];
        $out = [
            "$1.$2.$3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
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

    private function splitter($re, $text)
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
