<?php

namespace AwardWallet\Engine\cheaptickets\Email;

class It3880599 extends \TAccountCheckerExtended
{
    public $reBody = 'CheapTickets';
    public $reBody2 = [
        "en"=> "Your updated flight itinerary is below",
    ];

    public static $dictionary = [
        "en" => [
            "FlightsBlock" => "normalize-space(.)='Flight Change Details' or normalize-space(.)='Flight Details'",
        ],
    ];

    public $lang = "en";

    public function html_own(&$itineraries)
    {
        // record locators
        $xpath = "//text()[contains(., '" . $this->t("confirmation code:") . "')]";
        $nodes = $this->http->XPath->query($xpath);

        $rls = [];

        foreach ($nodes as $root) {
            $airline = strtolower($this->http->FindSingleNode(".", $root, true, "#(.*?)\s+" . $this->t("confirmation code:") . "#"));
            $rl = $this->http->FindSingleNode("./following::text()[normalize-space(.)][1]", $root);
            $rls[$airline] = $rl;
        }

        // airs
        $xpath = "//*[" . $this->t("FlightsBlock") . "]/ancestor::tr[1]/preceding-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./following-sibling::tr[1]", $root);
            $count = 0;

            while ($root2->length > 0) {
                $count++;
                $root2 = $this->http->XPath->query("./following-sibling::tr[1][not(normalize-space(./td[1]))]", $root2->item(0));
            }
            $flightNodes = $this->http->XPath->query(".|./following-sibling::tr[position()<={$count}]", $root);
            $html = "";

            foreach ($flightNodes as $row) {
                $html .= $row->ownerDocument->saveHTML($row);
            }

            $flight = clone $this->http;
            $flight->SetBody($html);

            $airline = strtolower($flight->FindSingleNode("//tr[3]/td[2]"));

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $flight;
            }
        }

        // parse
        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            $it['TripNumber'] = $this->http->FindSingleNode("//text()[normalize-space(.)='CheapTickets.com Itinerary Number:']/following::text()[normalize-space(.)][1]");

            // Passengers
            $it['Passengers'] = array_map('trim', explode(",", $this->http->FindSingleNode("//text()[normalize-space(.)='Passenger(s):']/following::text()[normalize-space(.)][1]")));

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

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($root->FindSingleNode("//tr[2]/td[2]")));

                $itsegment = [];
                // FlightNumber
                if (!$itsegment['FlightNumber'] = $root->FindSingleNode("//text()[contains(., 'Flight Number:')]", null, true, "#Flight Number:\s+\w{2}(\d+)#")) {
                    $itsegment['FlightNumber'] = $root->FindSingleNode("//text()[contains(., 'Flight Number:')]/ancestor::td[1]", null, true, "#Flight Number:\s+\w{2}\s+\d+ (\d+) \(change\)#");
                }

                // DepCode
                $itsegment['DepCode'] = $root->FindSingleNode("//text()[contains(., 'From:')]", null, true, "#\(([A-Z]{3})#");

                // DepName
                // DepDate
                $itsegment['DepDate'] = strtotime(orval(
                    $root->FindSingleNode("//text()[contains(., 'Depart:')]", null, true, "#\d+:\d+\s+[AP]M#"),
                    $root->FindSingleNode("(//text()[contains(., 'Depart:')]/ancestor::td[1]//text()[normalize-space(.)])[last()]", null, true, "#\d+:\d+\s+[AP]M#")
                ), $date);

                // ArrCode
                $itsegment['ArrCode'] = $root->FindSingleNode("//text()[contains(., 'To:')]", null, true, "#\(([A-Z]{3})#");

                // ArrName
                // ArrDate
                $itsegment['ArrDate'] = strtotime(orval(
                    $root->FindSingleNode("//text()[contains(., 'Arrive:')]", null, true, "#\d+:\d+\s+[AP]M#"),
                    $root->FindSingleNode("(//text()[contains(., 'Arrive:')]/ancestor::td[1]//text()[normalize-space(.)])[last()]", null, true, "#\d+:\d+\s+[AP]M#")
                ), $date);

                // AirlineName
                $itsegment['AirlineName'] = $root->FindSingleNode("//text()[contains(., 'Flight Number:')]", null, true, "#Flight Number:\s+(\w{2})#");

                // Operator
                $itsegment['Operator'] = $root->FindSingleNode("//text()[contains(., 'Operated By:')]", null, true, "#Operated By:\s+(.+)#");

                // Aircraft
                $itsegment['Aircraft'] = $root->FindSingleNode("//text()[contains(., 'Equipment:')]", null, true, "#Equipment:\s+(.+)#");

                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $root->FindSingleNode("//text()[contains(., 'Class:')]", null, true, "#Class:\s+(.+)#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = array_filter(array_map('trim', explode(",", $root->FindSingleNode("//text()[contains(., 'Seat:') or contains(., 'Seats:')]", null, true, "#Seats?:\s+(.+)#"))));

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
            $itineraries[] = $it;
        }
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

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetBody($body);
        }

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->html_own($itineraries);

        $result = [
            'emailType'  => 'Flight',
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

    private function normalizeDate($str)
    {
        $in = [
            "#^\w+,\s+(\w+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];

        return en(preg_replace($in, $out, $str));
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
}
