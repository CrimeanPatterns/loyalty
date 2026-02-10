<?php

namespace AwardWallet\Engine\asia\Email;

class It4006126 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "asia/it-4006126.eml, asia/it-4006966.eml, asia/it-4008668.eml, asia/it-4008839.eml, asia/it-4008930.eml";

    public $reFrom = "@cathaypacific.com"; // ?
    public $reSubject = [
        "en"=> "notiFLY Pre-flight Reminder",
    ];
    public $reBody = 'Cathay Pacific';
    public $reBody2 = [
        "en"=> "Your flight is now ready for",
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
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
        $it['Passengers'][] = $this->http->FindSingleNode("//text()[contains(., 'Passenger Name:')]", null, true, "#Passenger Name:\s+(.+)#");

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

        $xpath = "//text()[normalize-space(.)='Flight No.']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[4]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#\w{2}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[5]", $root, true, "#(\d+:\d+)\s+/#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[5]", $root, true, "#/\s+(\d+:\d+)#"), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#([A-Z\d]{2})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[6]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = trim($this->http->FindSingleNode("./td[7]", $root), ' -');

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
            'emailType'  => 'It4006126',
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

    private function getField($field)
    {
        return $this->http->FindSingleNode("//td[not(.//td) and normalize-space(.)='{$field}']/following-sibling::td[1]");
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
            "#^(\d+)([^\d\s]+)(\d+)$#",
        ];
        $out = [
            "$1 $2 20$3",
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
