<?php

namespace AwardWallet\Engine\asia\Email;

class It5497697 extends \TAccountChecker
{
    public $mailFiles = "asia/it-10479016.eml, asia/it-5497697.eml";

    public $reFrom = "@cathaypacific.com";
    public $reSubject = [
        "en"=> "CATHAY PACIFIC - FLIGHT SCHEDULE CHANGE",
    ];
    public $reBody = 'Cathay Pacific Airways';
    public $reBody2 = [
        "en" => "Thank you for booking with us",
        "en2"=> "We would like to advise you that the following",
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
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), 'BOOKING REF ')]", null, true, "#\w+$#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[starts-with(normalize-space(.), 'TICKET:')]/preceding::text()[normalize-space(.)][1]");

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

        $xpath = "//text()[starts-with(normalize-space(.), 'DURATION ')]/ancestor::p[1]/preceding-sibling::p[2]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $fixCity = [
            'ANGELES MABALACA PH',
        ];

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./following-sibling::p[1]/descendant::text()[normalize-space(.)][1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./preceding-sibling::p[1]", $root, true, "#\w+\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()-3]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime(preg_replace("#^(\d+)(\d{2})$#", "$1:$2", $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()-1]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()-2]", $root);

            if (empty($itsegment['DepName']) && preg_match("#^(" . implode("|", $fixCity) . ") (.+)$#", $itsegment['ArrName'], $m)) {
                $itsegment['DepName'] = $m[1];
                $itsegment['ArrName'] = $m[2];
            }

            // ArrDate
            $itsegment['ArrDate'] = strtotime(preg_replace("#^(\d+)(\d{2})$#", "$1:$2", $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()]", $root)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./preceding-sibling::p[1]", $root, true, "#(\w+)\s+\d+$#");

            // Operator
            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(.), 'EQUIPMENT: ')][1]", $root, null, "#EQUIPMENT: (.+)#");

            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./following-sibling::p[2]/descendant::text()[normalize-space(.)][last()]", $root, null, "#DURATION (.+)#");

            // Meal
            $itsegment['Meal'] = $this->http->FindSingleNode("./following::text()[starts-with(normalize-space(.), 'ON BOARD: ')][1]", $root, null, "#ON BOARD: (.+)#");

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
        // $this->http->setBody(str_replace(" ", " ", $this->http->Response["body"]));// bad fr char " :"

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = trim($lang, '1234567890');

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'It5497697',
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
            "#^[^\d\s]+\s+(\d+)([^\d\s]+)$#",
        ];
        $out = [
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);

        if (strtotime($str) < $this->date) {
            $str = preg_replace("#\d{4}#", $year + 1, $str);
        }
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
