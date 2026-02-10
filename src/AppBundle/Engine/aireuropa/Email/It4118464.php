<?php

namespace AwardWallet\Engine\aireuropa\Email;

class It4118464 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aireuropa/it-4101600.eml, aireuropa/it-4116932.eml, aireuropa/it-4118464.eml, aireuropa/it-4118469.eml, aireuropa/it-4118476.eml, aireuropa/it-4125513.eml";

    public $reFrom = "noreply@air-europa.com";
    public $reSubject = [
        "all"=> "Your Boarding Pass Confirmation",
    ];
    public $reBody = 'Air Europa';
    public $reBody2 = [
        "fr"=> "Nous vous confirmons que votre enregistrement a ete correctement effectue.",
        "es"=> "Le confirmamos que ha facturado con éxito.",
        "en"=> "We confirm you that you have been successfully checked-in.",
    ];

    public static $dictionary = [
        "fr" => [],
        "es" => [
            "Référence de la Réservation :" => "Referencia de la Reserva:",
            "Passager :"                    => "Pasajero:",
            "De :"                          => "Desde:",
            "À destination de :"            => "Con destino a:",
            "Vol :"                         => "Vuelo:",
        ],
        "en" => [
            "Référence de la Réservation :" => "Booking Reference:",
            "Passager :"                    => "Passenger:",
            "De :"                          => "From:",
            "À destination de :"            => "To:",
            "Vol :"                         => "Flight:",
        ],
    ];

    public $lang = "fr";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//text()[normalize-space(.)='" . $this->t("Référence de la Réservation :") . "'])[1]/following::text()[normalize-space(.)][1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passager :") . "']/following::text()[normalize-space(.)][1]"));

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

        $xpath = "//text()[normalize-space(.)='" . $this->t("Vol :") . "']/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Vol :") . "']/following::text()[normalize-space(.)][1]", $root, true, "#\w{2}(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)='" . $this->t("De :") . "'][1]/following::text()[normalize-space(.)][1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)='" . $this->t("De :") . "'][1]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./following::text()[normalize-space(.)='" . $this->t("À destination de :") . "'][1]/following::text()[normalize-space(.)][1]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./following::text()[normalize-space(.)='" . $this->t("À destination de :") . "'][1]/ancestor::td[1]/descendant::text()[normalize-space(.)][last()]", $root)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Vol :") . "']/following::text()[normalize-space(.)][1]", $root, true, "#(\w{2})\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Vol :") . "']/following::text()[normalize-space(.)][3]", $root, true, "#^(\w)$#");

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

        $this->http->FilterHTML = true;
        $itineraries = [];
        $this->http->setBody(str_replace("&nbsp;", " ", str_replace(" ", " ", $this->http->Response["body"]))); // bad fr char " :"
        // echo $this->http->Response["body"];
        // die();

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

    private function nextText($field, $n = 1)
    {
        return $this->http->FindSingleNode(".//text()[normalize-space(.)='{$field}']/following::text()[normalize-space(.)][{$n}]");
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
            "#^(\d+\s+\w+\s+\d{4})\s+-\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1, $2",
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
