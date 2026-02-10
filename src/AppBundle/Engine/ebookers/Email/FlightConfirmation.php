<?php

namespace AwardWallet\Engine\ebookers\Email;

use AwardWallet\Engine\MonthTranslate;

class FlightConfirmation extends \TAccountChecker
{
    public $mailFiles = "ebookers/it-1.eml, ebookers/it-1924136.eml, ebookers/it-2048139.eml, ebookers/it-2935200.eml, ebookers/it-3986055.eml, ebookers/it-3990184.eml, ebookers/it-3995532.eml, ebookers/it-5246939.eml, ebookers/it-5340457.eml, ebookers/it-5348911.eml, ebookers/it-5417622.eml, ebookers/it-5667403.eml, ebookers/it-5682103.eml, ebookers/it-6603239.eml, ebookers/it-6613729.eml";
    public $reFrom = "travellercare@ebookers.com";
    public $reSubject = [
        "en"=> "E-ticket / Flight confirmation",
        "fr"=> "Billet électronique/Facture",
        "nl"=> "Vlucht Reserveringsaanvraag",
        "fi"=> "Valmistaudu matkallesi",
        "de"=> "Vorbereitung für Ihre Reise",
    ];
    public $reBody = 'ebookers';
    public $reBody2 = [
        "en"=> "Flight information",
        "fr"=> "Itinéraire de vol",
        "nl"=> "Vluchtschema",
        "fi"=> "Lentotiedot",
        "de"=> "Flugstrecke",
    ];
    public $date;
    public static $dictionary = [
        "en" => [
            "Leave" => ["Leave", "Return", "Flight"],
        ],
        "fr" => [
            "Record locator"          => "Numéro de dossier",
            "ebookers record locator:"=> "Numéro de dossier ebookers:",
            "Total trip cost"         => "Tarif total du voyage",
            "Traveller"               => "Passager",
            "Airline Ticket Number:"  => "Numéro de billet :",
            "Leave"                   => ["Départ", "Retour", "Vol"],
            "Seats:"                  => "Sièges :",
        ],
        "nl" => [
            "Record locator"          => "reserveringsnummer:",
            "ebookers record locator:"=> ["ebookers-reserveringsnummer", "ebookers reserveringsnummer:"],
            "Total trip cost"         => "Totale reissom",
            "Traveller"               => "Reiziger",
            "Airline Ticket Number:"  => "Ticketnummer:",
            "Leave"                   => ["Vertrek", "Retour", "Vlucht"],
            "Seats:"                  => "NOTTRANSLATED",
        ],
        "fi" => [
            "Record locator"          => "varausnumero:",
            "ebookers record locator:"=> ["Ebookers varaustunnus:"],
            "Total trip cost"         => "Matkan kokonaishinta",
            "Traveller"               => "Matkustaja",
            "Airline Ticket Number:"  => "Lentolipun numero:",
            "Leave"                   => ["Lähtö", "Paluu"],
            "Seats:"                  => "NOTTRANSLATED",
        ],
        "de" => [
            "Record locator"          => "Auftragsnummer",
            "ebookers record locator:"=> ["ebookers.de Auftragsnummer"],
            "Total trip cost"         => "Gesamtreisepreis",
            "Traveller"               => "Reisender",
            "Airline Ticket Number:"  => "Ticketnummer:",
            "Leave"                   => ["Abreise", "Rückreise"],
            "Seats:"                  => "Sitzplätze:",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $xpath = "//text()[" . $this->eq($this->t("Record locator")) . "]/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);
        $rls = [];

        foreach ($nodes as $root) {
            $rls[$this->http->FindSingleNode("./td[1]", $root)] = $this->http->FindSingleNode("./td[2]", $root);
        }
        $xpath = "//text()[" . $this->contains($this->t("Record locator")) . "]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (!$airline = $this->http->FindSingleNode(".", $root, true, "#" . $this->opt($this->t("Record locator")) . "\s+(.+)#")) {
                $airline = $this->http->FindSingleNode(".", $root, true, "#(.*?)\s+" . $this->opt($this->t("Record locator")) . "#");
            }
            $rls[$airline] = $this->http->FindSingleNode("./following::text()[normalize-space(.)!=''][1]", $root);
        }

        $xpath = "//tr/td[3]//img[contains(@src, 'logos/air/airline') or contains(@alt, 'airline logo')]/ancestor::tr[1]";
        $nodes = $this->http->XPath->query($xpath);
        $airs = [];

        foreach ($nodes as $root) {
            $airline = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#(.*?)\s+\d+$#");

            if (isset($rls[$airline])) {
                $airs[$rls[$airline]][] = $root;
            } elseif ($rl = trim($this->nextText($this->t("ebookers record locator:")), '- ')) {
                $airs[$rl][] = $root;
            } else {
                $this->http->log("RL NOT FOUND");

                return;
            }
        }

        foreach ($airs as $rl=>$roots) {
            $it = [];

            $it['Kind'] = "T";

            // RecordLocator
            $it['RecordLocator'] = $rl;

            // TripNumber
            if (!$it['TripNumber'] = trim($this->nextText($this->t("ebookers record locator:")), '- ')) {
                $it['TripNumber'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("ebookers record locator:")) . "]", null, true, "#" . $this->opt($this->t("ebookers record locator:")) . "\s+(.+)#");
            }

            // Passengers
            $it['Passengers'] = $this->http->FindNodes("//text()[translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Traveller") . " d' or translate(normalize-space(.), '1234567890', 'dddddddddd')='" . $this->t("Traveller") . " dd']/following::text()[normalize-space(.)][1]");

            // TicketNumbers
            $it['TicketNumbers'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Airline Ticket Number:")) . "]/following::text()[normalize-space(.)][1]", null, "#^[\d\s-]+$#"));

            // AccountNumbers
            // Cancelled
            if (count($airs) == 1) {
                // TotalCharge
                $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total trip cost")) . "])[last()]/following::text()[normalize-space(.)][1]"));

                // BaseFare
                // Currency
                $it['Currency'] = $this->currency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total trip cost")) . "])[last()]/following::text()[normalize-space(.)][1]"));
            }
            // Tax
            // SpentAwards
            // EarnedAwards
            // Status
            // ReservationDate
            // NoItineraries
            // TripCategory

            foreach ($roots as $root) {
                $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding-sibling::tr[./td[1][" . $this->starts($this->t("Leave")) . "]][count(./td)=3][1]/td[2]", $root)));

                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#\s+(\d+)$#");

                // DepCode
                $itsegment['DepCode'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#\(([A-Z]{3})\)#");

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");

                // DepartureTerminal
                $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[1]/td[2]", $root, true, "#Terminal .+#");

                // DepDate
                $time = $this->http->FindSingleNode("./following-sibling::tr[1]/td[1]", $root, true, "#\d+:\d+#");
                $itsegment['DepDate'] = $time ? strtotime($time, $date) : MISSING_DATE;

                // ArrCode
                if (!$itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true, "#\(([A-Z]{3})\)#")) {
                    $itsegment['ArrCode'] = $this->http->FindSingleNode("./following-sibling::tr[4]/td[2]", $root, true, "#\(([A-Z]{3})\)#");
                }

                // ArrName
                if (!$itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#")) {
                    $itsegment['ArrName'] = $this->http->FindSingleNode("./following-sibling::tr[4]/td[2]", $root, true, "#(.*?)\s+\([A-Z]{3}\)#");
                }

                // ArrivalTerminal
                if (!$itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[3]/td[2]", $root, true, "#Terminal .+#")) {
                    $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./following-sibling::tr[4]/td[2]", $root, true, "#Terminal .+#");
                }

                // ArrDate
                if (!$time = $this->http->FindSingleNode("./following-sibling::tr[3]/td[1]", $root, true, "#\d+:\d+#")) {
                    $time = $this->http->FindSingleNode("./following-sibling::tr[4]/td[1]", $root, true, "#\d+:\d+#");
                }
                $itsegment['ArrDate'] = $time ? strtotime($time, $date) : MISSING_DATE;

                // AirlineName
                $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][1]", $root, true, "#(.*?)\s+\d+$#");

                // Operator
                // Aircraft
                $itsegment['Aircraft'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][2]", $root, null, "#\s+\|\s+(.+)#");

                // TraveledMiles
                $itsegment['TraveledMiles'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][3]", $root, null, "#(.*?)\s+\|\s+#");

                // AwardMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][2]", $root, null, "#(\w+)\s+\|#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = $this->http->FindSingleNode("./following-sibling::tr[position()<7][" . $this->contains($this->t("Seats:")) . "]", $root, null, "#" . $this->opt($this->t("Seats:")) . "\s+(.*?)\s+\|#");

                // Duration
                if (!$itsegment['Duration'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][3]", $root, null, "#\s+\|\s+(.+)#")) {
                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)!=''][3]", $root);
                }

                // Meal
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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'FlightConfirmation' . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $itineraries,
                'TotalCharge' => [
                    "Amount"   => $this->amount($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total trip cost")) . "])[last()]/following::text()[normalize-space(.)][1]")),
                    "Currency" => $this->currency($this->http->FindSingleNode("(//text()[" . $this->eq($this->t("Total trip cost")) . "])[last()]/following::text()[normalize-space(.)][1]")),
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][{$n}]", $root);
    }

    private function t($word)
    {
        // $this->http->Log($word);
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function normalizeDate($str)
    {
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\s]+\.\s+(\d+)\s+([^\d\s]+)\.$#", //ven. 11 avr.
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)$#", //Thu, 9 Oct
            "#^[^\d\s]+\s+(\d+)\s+([^\d\s]+)$#", //ma 24 feb
            "#^[^\d\s]+\s+(\d+)\.\s+([^\d\s]+)$#", //to 12. kesä
        ];
        $out = [
            "$1 $2 $year",
            "$1 $2 $year",
            "$1 $2 $year",
            "$1 $2 $year",
        ];
        $str = preg_replace($in, $out, $str);
        //		 $this->http->log($str);
        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
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
        return (float) str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", trim($this->re("#([\d\,\.\s]+)#", $s))));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#([^\d\,\.\s]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", $field) . ')';
    }
}
