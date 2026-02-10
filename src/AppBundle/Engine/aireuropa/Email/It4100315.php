<?php

namespace AwardWallet\Engine\aireuropa\Email;

class It4100315 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aireuropa/it-4067653.eml, aireuropa/it-4100315.eml, aireuropa/it-4114928.eml";

    public $reFrom = "@air-europa.com";
    public $reSubject = [
        "en"=> "Air Europa Ticket Purchase",
        "es"=> "Compra de Billete Air Europa",
    ];
    public $reBody = 'www.aireuropa.com';
    public $reBody2 = [
        "en"=> "Flights",
        "es"=> "Vuelos",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Locator"   => "Localizador",
            "Passenger" => "Pasajero",
            "Amount"    => "Importe",
            "Departure" => "Origen",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Locator"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passenger") . "']/ancestor::tr[1]/following-sibling::tr[./following::tr[contains(.,'" . $this->t("Amount") . "')]]/td[1]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Total") . "']/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Total") . "']/ancestor::tr[1]/following-sibling::tr[1]/td[last()]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='" . $this->t("Departure") . "']/ancestor::tr[1]/following-sibling::tr[./td[6]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[5]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[3]", $root, true, "#\w{2}\s*(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[6]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]", $root);

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[3]", $root, true, "#(\w{2})\s*\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[4]", $root);

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
            "#^(\d+)/(\d+)/(\d{4})$#",
        ];
        $out = [
            "$2/$1/$3",
        ];

        return preg_replace($in, $out, $str);
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
