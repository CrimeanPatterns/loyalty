<?php

namespace AwardWallet\Engine\airfrance\Email;

class It4292598 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "airfrance/it-4292598.eml";

    public $reFrom = "mail.klm.zag@airfrance.fr";
    public $reSubject = [
        "en"=> "Tickets for",
    ];
    public $reBody = 'AIRFRANCE.FR';
    public $reBody2 = [
        "en"=> "RESERVATION NUMBER",
    ];

    public static $dictionary = [
        "en" => [],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $nodes = [];

        foreach ($this->http->XPath->query("//text()") as $node) {
            $nodes[] = $node->nodeValue;
        }
        $this->text = implode("\n", $nodes);
        // echo $this->text;

        $rls = [];

        foreach (explode(" ", $this->re("#RESERVATION NUMBER\(S\)\s+([^\n]+)#")) as $rl) {
            $arr = explode("/", preg_replace("#[^\w/]+#", "", $rl));

            if (isset($arr[0]) && isset($arr[1])) {
                $rls[$arr[0]] = $arr[1];
            }
        }
        $segments = $this->splitter("#(\n\s*[^\n]+\s+-\s+(?:" . implode("|", array_keys($rls)) . ")\s+\d+\s*\n)#", $this->text);
        $airs = [];

        foreach ($segments as $segment) {
            $operator = $this->re("#\n\s*[^\n]+\s+-\s+(" . implode("|", array_keys($rls)) . ")\s+\d+\s*\n#", $segment);

            if (isset($rls[$operator])) {
                $airs[$rls[$operator]][] = $segment;
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
                $rows = explode("\n", trim($segment));

                $date = strtotime($this->normalizeDate($this->re("#(.*?)\s{2,}#", $rows[1])));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->re("#\s+\w{2}\s+(\d+)#", $rows[0]);

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->re("#.*?\s{2,}(.*?)\s{2,}(.*?)\s{2,}#", $rows[1]);

                // DepDate
                $itsegment['DepDate'] = strtotime(preg_replace("#(\d{2})(\d{2})#", "$1:$2", $this->re("#(\d{4})\s+\d{4}$#", $rows[1])), $date);

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->re("#.*?\s{2,}.*?\s{2,}(.*?)\s{2,}#", $rows[1]);

                // ArrDate
                $itsegment['ArrDate'] = strtotime(preg_replace("#(\d{2})(\d{2})#", "$1:$2", $this->re("#\d{4}\s+(\d{4})$#", $rows[1])), $date);

                // AirlineName
                $itsegment['AirlineName'] = $this->re("#\s+(\w{2})\s+\d+#", $rows[0]);

                // Operator
                $itsegment['Operator'] = $this->re("#FLIGHT OPERATED BY\s+(.+)#", $segment);

                // Aircraft
                $itsegment['Aircraft'] = $this->re("#EQUIPMENT:\s*(.+)#", $segment);

                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->re("#-\s+\w\s+(\w+)#", $segment);

                // BookingClass
                $itsegment['BookingClass'] = $this->re("#-\s+(\w)\s+\w+#", $segment);

                // PendingUpgradeTo
                // Seats
                // Duration
                $itsegment['Duration'] = $this->re("#DURATION\s+(.+)#", $segment);

                // Meal
                $itsegment['Meal'] = $this->re("#ON BOARD:\s*(.+)#", $segment);

                // Smoking
                // Stops
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

        $this->http->setBody(str_replace([" ", "&nbsp;"], [" ", " "], '<?xml version="1.0" encoding="UTF-8"?>' . $parser->getHTMLBody())); // bad fr char " :"

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
                'TotalCharge' => [
                    "Amount"   => (float) $this->re("#TICKET PRICE IS\s+\w{3}\s+([\d\.]+)#"),
                    "Currency" => $this->re("#TICKET PRICE IS\s+(\w{3})\s+[\d\.]+#"),
                ],
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
            "#^\w+\s+(\d+)(\w+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
            $str = $this->dateStringToEnglish($str);
        }

        return $str;
    }

    private function re($re, $str = false, $c = 1)
    {
        if ($str === false && isset($this->text)) {
            $str = $this->text;
        }
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function splitter($re, $text = false)
    {
        if ($text === false && isset($this->text)) {
            $text = $this->text;
        }

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
