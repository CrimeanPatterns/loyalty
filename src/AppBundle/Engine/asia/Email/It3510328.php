<?php

namespace AwardWallet\Engine\asia\Email;

class It3510328 extends \TAccountCheckerExtended
{
    public $mailFiles = "asia/it-10185609.eml, asia/it-1751552.eml, asia/it-3510328.eml";
    public $reBody = 'Cathay Pacific';
    public $reBody2 = "Departure";
    public $reSubject = "Confirmation";
    public $reFrom = "@dragonair.com";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            $this->reBody2 => function (&$itineraries) {
                $text = text($this->http->Response["body"]);
                $it = [];

                $it['Kind'] = "T";
                // RecordLocator

                $it['RecordLocator'] = re("#Booking\s+Reference\s+Number\s*:\s*(\w+)#", $text);
                // TripNumber
                // Passengers
                if (count($it['Passengers'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='Name']/ancestor::tr[1]/following-sibling::tr/td[1]"))) == 0) {
                    $it['Passengers'] = array_filter($this->http->FindNodes("//text()[normalize-space(.)='Name']/ancestor::table[1]/tbody/tr/td[1]"));
                }

                // AccountNumbers
                // Cancelled
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->nextText("Grand total"));

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->nextText("Grand total"));

                // Tax
                // SpentAwards
                // EarnedAwards
                // Status
                // ReservationDate
                // NoItineraries
                // TripCategory

                $xpath = "//*[normalize-space(text())='Flight']/ancestor::tr[1]/following-sibling::tr";
                $nodes = $this->http->XPath->query($xpath);

                if ($nodes->length == 0) {
                    $xpath = "//*[normalize-space(text())='Flight']/ancestor::table[1]/tbody/tr";
                    $nodes = $this->http->XPath->query($xpath);
                }

                foreach ($nodes as $root) {
                    $date = strtotime($this->http->FindSingleNode("./td[1]", $root));

                    $itsegment = [];
                    // FlightNumber
                    $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]", $root, true, "#\w{2}(\d+)#");

                    // DepCode
                    $itsegment['DepCode'] = $this->http->FindSingleNode("./td[4]", $root, true, "#[A-Z]{3}#");

                    // DepName
                    // DepDate
                    $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[4]", $root, true, "#\d+:\d+#"), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[5]", $root, true, "#[A-Z]{3}#");

                    // ArrName
                    // ArrDate
                    $time = $this->http->FindSingleNode("./td[5]", $root, true, "#\d+:\d+.*#");

                    if (preg_match("#\s*(\d:\d+)\s*((?:[+\-]\s*\d+))\s*$#", $time, $m)) {
                        $itsegment['ArrDate'] = strtotime($m[2] . ' days', strtotime($m[1], $date));
                    } else {
                        $itsegment['ArrDate'] = strtotime($time, $date);
                    }

                    // AirlineName
                    $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(\w{2})\d+#");

                    // Aircraft
                    $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[8]", $root);

                    // TraveledMiles
                    // Cabin
                    $itsegment['Cabin'] = $this->http->FindSingleNode("./td[9]", $root, true, "#(\w+)\s*\(\w\)#");

                    // BookingClass
                    $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[9]", $root, true, "#\w+\s*\((\w)\)#");

                    // PendingUpgradeTo
                    // Seats
                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[7]", $root);

                    // Meal
                    // Smoking
                    // Stops
                    $itsegment['Stops'] = $this->http->FindSingleNode("./td[6]", $root);

                    $itsegment['Operator'] = $this->http->FindSingleNode("./td[3]", $root);

                    $it['TripSegments'][] = $itsegment;
                }
                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        return strpos($body, $this->reBody) !== false && strpos($body, $this->reBody2) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return strpos($headers["subject"], $this->reSubject) !== false && strpos($headers["from"], $this->reFrom) !== false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->processors as $re => $processor) {
            if (stripos($body, $re)) {
                $processor($itineraries);

                break;
            }
        }

        $result = [
            'emailType'  => 'It3510328',
            'parsedData' => [
                'Itineraries' => $itineraries,
            ],
        ];

        return $result;
    }

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field));
    }

    private function starts($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "starts-with(normalize-space(.), \"{$s}\")"; }, $field));
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false';
        }

        return implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field));
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
