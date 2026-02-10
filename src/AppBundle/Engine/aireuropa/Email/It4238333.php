<?php

namespace AwardWallet\Engine\aireuropa\Email;

class It4238333 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aireuropa/it-4238333.eml";

    public $reFrom = "@air-europa.com";
    public $reSubject = [
        "es"=> "Compra de Billete Air Europa",
    ];
    public $reBody = 'Air Europa';
    public $reBody2 = [
        "es"=> "Este es el Bono de su Reserva, consrvelo como comprobante para su seguridad.",
    ];

    public static $dictionary = [
        "es" => [],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Localizador") . "']/following::text()[normalize-space(.)][1]");

        // TripNumber
        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Apellidos, Nombre") . "']/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)][last()]");

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Apellidos, Nombre") . "']/ancestor::tr[1]/following-sibling::tr/descendant::text()[normalize-space(.)][1]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Importe Total") . "']/following::text()[normalize-space(.)][1]"));

        // BaseFare
        $it['BaseFare'] = $this->cost($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Base Tarifa") . "']/following::text()[normalize-space(.)][1]"));

        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Importe Total") . "']/following::text()[normalize-space(.)][1]"));

        // Tax
        $it['Tax'] = $this->cost($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("Tasas") . "']/following::text()[normalize-space(.)][1]"));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//text()[normalize-space(.)='" . $this->t("Vuelo") . "']/ancestor::tr[1]/following-sibling::tr[normalize-space(./td[1])]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][3]", $root, true, "#^\w{2}\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate(implode(", ", $this->http->FindNodes("./descendant::text()[normalize-space(.)][position()=5 or position()=6]", $root))));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][2]", $root);

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][3]", $root, true, "#^(\w{2})\s*\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][4]", $root);

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
            "#^(\d+)/(\d+)/(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\W]#", $str)) {
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
}
