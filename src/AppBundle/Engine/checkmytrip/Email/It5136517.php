<?php

namespace AwardWallet\Engine\checkmytrip\Email;

class It5136517 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "checkmytrip/it-5136517.eml";

    public $reFrom = "checkmytrip.com";
    public $reSubject = [
        "en"=> "A new request has been submitted by Checkmytrip",
    ];
    public $reBody = 'checkmytrip.com';
    public $reBody2 = [
        "en"=> "YOUR TRIP SUMMARY",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $text = implode("\n", $this->http->FindNodes("./descendant::text()"));

        preg_match_all("#Airline confirmation number\(s\):\s+(?<Airline>[^\n]*?)\s+(?<RecordLocator>\w+)\n#", $text, $m, PREG_SET_ORDER);
        $rls = [];

        foreach ($m as $i) {
            $rls[$i['Airline']] = $i['RecordLocator'];
        }

        preg_match_all("#Flight\s+\d+\s*-\s*(?<Date>[^\n]+)\n" .
                        "Status\s*:\s*[^\n]+\n" .
                        "Departure\s*:\s*(?<DepTime>\d+:\d+)\s*-\s*(?<DepName>[^\n]+)\n" .
                        "Arrival\s*:\s*(?<ArrTime>\d+:\d+)\s*-\s*(?<ArrName>[^\n]+)\n" .
                        "Airline\s*:\s*(?<Airline>[^\n]*?)\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\n" .
                        "Duration\s*:\s*(?<Duration>[^\n]+)\n" .
                        "Fare type\s*:\s*(?<Cabin>[^\n]+)\n" .
                        "Aircraft\s*:\s*(?<Aircraft>.*?)\s+-\s+Operated by\s+(?<Operator>[^\n]+)\n" .
                        "Baggage\s*:\s*[^\n]+\n" .
                        "Fare basis\s*:\s*[^\n]+\n" .
                        "Meal\s*:\s*(?<Meal>[^\n]+)\n#", $text, $segments, PREG_SET_ORDER);
        $airs = [];

        foreach ($segments as $segment) {
            if (isset($rls['Airline'])) {
                $airs[$rls['Airline']][] = $segment;
            } elseif ($rl = $this->re("#Booking reservation number:\s+(\w+)#", $text)) {
                $airs[$rl][] = $segment;
            } else {
                return;
            }
        }

        foreach ($airs as $rl=>$segments) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            // Passengers
            $it['Passengers'] = explode("\n", $this->re("#TRAVELLER INFORMATION[\s\*]+(.*?)\s+Contact Information#ms", $text));
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

            $xpath = "//*[normalize-space(text())='Depart']/ancestor::tr[1]/..";
            $nodes = $this->http->XPath->query($xpath);

            if ($nodes->length == 0) {
                $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
            }

            foreach ($segments as $segment) {
                $date = strtotime($this->normalizeDate($segment['Date']));

                $itsegment = [];

                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                $keys = [
                    'DepName',
                    'ArrName',
                    'AirlineName',
                    'FlightNumber',
                    'Duration',
                    'Cabin',
                    'Aircraft',
                    'Operator',
                    'Meal',
                ];

                foreach ($keys as $key) {
                    if (isset($segment[$key])) {
                        $itsegment[$key] = $segment[$key];
                    }
                }

                $itsegment['DepDate'] = strtotime($segment['DepTime'], $date);
                $itsegment['ArrDate'] = strtotime($segment['ArrTime'], $date);

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
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
        ];
        $out = [
            "$2 $1 $3",
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
