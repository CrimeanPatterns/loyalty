<?php

namespace AwardWallet\Engine\aireuropa\Email;

class It4062072 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aireuropa/it-4062072.eml, aireuropa/it-4113728.eml, aireuropa/it-4123742.eml, aireuropa/it-4134311.eml, aireuropa/it-4137533.eml, aireuropa/it-4142937.eml, aireuropa/it-4184378.eml";

    public $reFrom = "info-noreply@air-europa.com";
    public $reSubject = [
        "en"=> "Purchase of Air Europa ticket",
        "es"=> "Compra de Billete Air Europa",
        "fr"=> "Achat de billet Air Europa",
    ];
    public $reBody = 'Air Europa';
    public $reBody2 = [
        "en"=> "Booking locator",
        "es"=> "Localizador",
        "fr"=> "Merci de voyager avec Air Europa",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Seat"            => "Asiento",
            "Booking locator" => "Localizador",
            "TOTAL AMOUNT"    => ["TOTAL IMPORTE", "MONTANTE TOTAL"],
            "Adult base fare" => "Tarifa base adulto",
            "Taxes"           => ["Tasas", "Taxas"],
            "Flight:"         => "Vuelo:",
            "Op."             => "Op.",
            "Class:"          => "Clase:",
        ],
        "fr" => [
            "Seat"            => "NOTTRANSLATED",
            "Booking locator" => "Code de réservation",
            "TOTAL AMOUNT"    => "MONTANT TOTAL",
            "Adult base fare" => "Prix de base adulte",
            "Taxes"           => "Taxes",
            "Flight:"         => "Vol:",
            "Op."             => "Op.",
            "Class:"          => "Classe:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $seats = [];
        $seatrows = $this->http->FindNodes("//img[contains(@src, '/icon_seat')]/..");

        foreach ($seatrows as $row) {
            $seat = $this->re("#" . $this->t("Seat") . "\s+(\d+\w)\s#", $row);
            $flight = $this->re("#\s\w{2}(\d+)$#", $row);

            if ($flight && $seat) {
                $seats[$flight][] = $seat;
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->prevText($this->t("Booking locator"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, 'icon_passenger_big.pn')]/preceding::text()[normalize-space(.)][1]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->cost($this->http->FindSingleNode("//text()[" . $this->eq($this->t("TOTAL AMOUNT")) . "]/ancestor::p[1]/following-sibling::p[1]"));

        // BaseFare
        $it['BaseFare'] = $this->cost($this->http->FindSingleNode("//text()[contains(normalize-space(.), '" . $this->t("Adult base fare") . "')]/following::text()[normalize-space(.)][1]"));

        // Currency
        $it['Currency'] = $this->http->FindSingleNode("//text()[" . $this->eq($this->t("TOTAL AMOUNT")) . "]/ancestor::p[1]/following-sibling::p[2]");

        // Tax
        $it['Tax'] = $this->cost($this->nextText($this->t("Taxes")));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory

        $xpath = "//img[contains(@src, 'icon_stop.png')]/ancestor::tr[./parent::*[contains(., '" . $this->t(":") . "')]][1]/..";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::img[contains(@src, '/icon_plane')][1]/..", $root)));

            $itsegment = [];
            // FlightNumber
            $flight = $this->http->FindSingleNode(".//text()[contains(., '" . $this->t("Flight:") . "')]", $root);

            if (!$flight) {
                $flight = $this->http->FindSingleNode("./tr[last()]/td[1]/descendant::text()[normalize-space(.)][2]", $root);
            }
            $itsegment['FlightNumber'] = $this->re("#\w{2}\s*(\d+)$#", $flight);

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./tr[1]/td[1]", $root, true, "#(?:\s|^)([A-Z]{3})(?:\s|$)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./tr[3]/td[1]/descendant::text()[normalize-space(.)][1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[1]/descendant::text()[normalize-space(.)][2]", $root, true, "#\d+:\d+#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./tr[1]/td[2]", $root, true, "#(?:\s|^)([A-Z]{3})(?:\s|$)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[3]/td[2]/descendant::text()[normalize-space(.)][1]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\d+:\d+#"), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->re("#(\w{2})\s*\d+$#", $flight);

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[contains(., '" . $this->t("Op.") . "')]", $root, true, "#" . $this->t("Op.") . "\s+(.+)$#");

            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode(".//text()[contains(., '" . $this->t("Class:") . "')]", $root, true, "#" . $this->t("Class:") . "\s+(\w)$#");

            // PendingUpgradeTo
            // Seats
            if (isset($seats[$itsegment['FlightNumber']])) {
                $itsegment['Seats'] = implode(', ', $seats[$itsegment['FlightNumber']]);
            }
            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;

            if ($this->http->FindSingleNode("./tr[1]/td[3]", $root)) {
                $itsegment2 = $itsegment;

                $itsegment2['FlightNumber'] = $this->http->FindSingleNode("./tr[last()]/td[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#\w{2}\s*(\d+)$#");
                $itsegment2['AirlineName'] = $this->http->FindSingleNode("./tr[last()]/td[2]/descendant::text()[normalize-space(.)][2]", $root, true, "#(\w{2})\s*\d+$#");
                $itsegment2['DepCode'] = $itsegment2['ArrCode'];
                $itsegment2['DepName'] = $itsegment2['ArrName'];
                $itsegment2['ArrCode'] = $this->http->FindSingleNode("./tr[1]/td[3]", $root, true, "#(?:\s|^)([A-Z]{3})(?:\s|$)#");
                $itsegment2['ArrName'] = $this->http->FindSingleNode("./tr[3]/td[3]/descendant::text()[normalize-space(.)][1]", $root);
                $itsegment2['DepDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)][last()]", $root, true, "#\d+:\d+#"), $date);

                if ($itsegment2['DepDate'] < $itsegment['ArrDate']) {
                    $itsegment2['DepDate'] = strtotime("+1 day", $itsegment2['DepDate']);
                }

                $itsegment2['ArrDate'] = strtotime($this->http->FindSingleNode("./tr[1]/td[3]/descendant::text()[normalize-space(.)][2]", $root, true, "#\d+:\d+#"), $date);

                if ($itsegment2['ArrDate'] < $itsegment2['DepDate']) {
                    $itsegment2['ArrDate'] = strtotime("+1 day", $itsegment2['ArrDate']);
                }
                $it['TripSegments'][] = $itsegment2;
            }
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
            'emailType'  => 'reservations' . ucfirst($this->lang),
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
        if (!is_array($field)) {
            $field = [$field];
        }
        $rules = [];

        foreach ($field as $s) {
            $rules[] = "normalize-space(.)='{$s}'";
        }

        return $this->http->FindSingleNode("(.//text()[" . implode(' or ', $rules) . "])[{$n}]/following::text()[normalize-space(.)][1]", $root);
    }

    private function prevText($field, $root = null, $n = 1)
    {
        return $this->http->FindSingleNode("(.//text()[normalize-space(.)='{$field}'])[{$n}]/preceding::text()[normalize-space(.)][1]", $root);
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
            "#^(?:OUTBOUND|INBOUND)\s+\w+\s+(\d+)\s+OF\s+(\w+)$#",
            "#^(?:IDA|VUELTA|VOLTA)\s+[^\s\d]+\s+(\d+)\s+DE\s+([^\s\d]+)$#",
            "#^(?:RETOUR|ALLER)(?: LE)?\s+[^\d\s]+\s+(\d+)\s+jj\s+([^\d\s]+)$#",
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
            "$1 $2 $year",
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

    private function eq($vars, $in = '.', $dl = ' or ')
    {
        if (!is_array($vars)) {
            $vars = [$vars];
        }
        $rules = [];

        foreach ($vars as $s) {
            $rules[] = "normalize-space({$in})='{$s}'";
        }

        return implode($dl, $rules);
    }
}
