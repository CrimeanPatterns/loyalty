<?php

namespace AwardWallet\Engine\eurostar\Email;

class It3931624 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "eurostar/it-1947911.eml, eurostar/it-1949949.eml, eurostar/it-2788323.eml, eurostar/it-2807827.eml, eurostar/it-289099459.eml, eurostar/it-3.eml, eurostar/it-3033387.eml, eurostar/it-3931624.eml, eurostar/it-4.eml, eurostar/it-5.eml";

    public $reFrom = "no-reply@eurostar.com";
    public $reSubject = [
        "en"=> "Your Eurostar booking confirmation",
        "nl"=> "Uw Eurostarreserveringsbevestiging",
        "fr"=> "Votre confirmation de réservation Eurostar",
    ];
    public $reBody = 'eurostar.com';
    public $reBody2 = [
        "en"=> "Your Eurostar ticket options",
        "nl"=> "Mogelijkheden om uw Eurostar-tickets af te halen:",
        "fr"=> "Vos options de billet Eurostar:",
    ];

    public static $dictionary = [
        "en" => [],
        "nl" => [
            "Booking reference" => "Boekingsreferentie",
            "Adult"             => "Volwassen",
            "Amount"            => "Bedrag",
            "point"             => "NOTTRANSLATED",
            "Departs"           => "Vertrek",
            "Arrives"           => "Aankomst",
            "Eurostar seats"    => "Eurostar zitplaatsen",
            "Carriage"          => "Rijtuig",
            "Seat"              => "Zitplaats",
            "Duration"          => "Duur",
            "at"                => "om",
            //			"on" => "op",

            "Coach"            => "NOTTRANSLATED",
            "Class:"           => "NOTTRANSLATED",
            "Seating Details:" => "NOTTRANSLATED",
            "Train no:"        => "NOTTRANSLATED",
        ],

        "fr" => [
            "Booking reference" => "Référence de réservation",
            "Adult"             => "Adulte",
            "Amount"            => "Montant",
            "point"             => "NOTTRANSLATED",
            "Departs"           => "Départ de",
            "Arrives"           => "Arrivée à",
            "Eurostar seats"    => "Sièges Eurostar",
            "Carriage"          => "Voiture",
            "Seat"              => "Siège",
            "Duration"          => "Durée",
            "at"                => "à",
            //			"on" => "",

            "Coach"            => "NOTTRANSLATED",
            "Class:"           => "NOTTRANSLATED",
            "Seating Details:" => "NOTTRANSLATED",
            "Train no:"        => "NOTTRANSLATED",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("(//*[contains(text(), '" . $this->t("Booking reference") . "')]/ancestor::tr[1]/following::tr//td[1])[1]");

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[contains(., '" . $this->t("Adult") . "')]/..", null, "#(.*?)\s+\(?" . $this->t("Adult") . "#"));

        // AccountNumbers
        // Cancelled
        if (strpos($total = $this->http->FindSingleNode("(//*[contains(text(), '" . $this->t("Amount") . "')])[1]/ancestor::table[1]/descendant::node()[1]/following-sibling::node()//td[last()]"), $this->t("point")) === false) {
            // TotalCharge
            $it['TotalCharge'] = $this->cost(preg_replace("#[.,](\d{3})#", "$1", $total));

            // Currency
            $it['Currency'] = $this->currency($total);
        }

        // BaseFare
        // Tax
        // SpentAwards
        if (strpos($total = $this->http->FindSingleNode("(//*[contains(text(), '" . $this->t("Amount") . "')])[1]/ancestor::table[1]/descendant::node()[1]/following-sibling::node()//td[last()]"), $this->t("point")) !== false) {
            $it['SpentAwards'] = $total;
        }

        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $it['TripCategory'] = TRIP_CATEGORY_TRAIN;

        // with train number - it-2807827.eml
        $xpath = "//*[contains(text(), '" . $this->t("Departs") . "')]/ancestor::tr[1][contains(., '" . $this->t("Arrives") . "')]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length > 0) {
            foreach ($nodes as $root) {
                $itsegment = [];
                // FlightNumber
                $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[2]//text()[contains(normalize-space(.),'" . $this->t("Train no:") . "')]//ancestor::p[1]", $root, true, "#:\s*(.+)#");

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./td[1]/p[1]", $root, true, "#" . $this->t("Departs") . "\s*:\s*(.+?)\s+" . $this->t("at") . "#");

                // DepAddress
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/p[1]", $root, true, "#" . $this->t("Departs") . "\s*:\s*.+?\s*(\d+\s+\S+)\s*$#i") . ',' . $this->http->FindSingleNode("./td[1]/p[1]", $root, true, "#" . $this->t("Departs") . "\s*:\s*.+?\s+" . $this->t("at") . "\s*(\d+:\d+(?:\s*[ap]m)?)\s+#i")));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./td[1]/p[2]", $root, true, "#" . $this->t("Arrives") . "\s*:\s*(.+?)\s+" . $this->t("at") . "#");

                // ArrAddress
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]/p[1]", $root, true, "#" . $this->t("Departs") . "\s*:\s*.+?\s*(\d+\s+\S+)\s*$#i") . ',' . $this->http->FindSingleNode("./td[1]/p[2]", $root, true, "#" . $this->t("Arrives") . "\s*:\s*.+?\s+" . $this->t("at") . "\s*(\d+:\d+(?:\s*[ap]m)?)#i")));

                // Type
                // $itsegment['Type'] = $this->http->FindSingleNode("./following-sibling::tr[1]//text()[normalize-space(.)='" . $this->t("Seating Details:") . "']/following::text()[string-length(normalize-space(.))>1][1]", $root, true, "#" . $this->t("Coach") . "\s+(\d+)#");

                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = $this->http->FindSingleNode("./td[2]//text()[contains(normalize-space(.),'" . $this->t("Class:") . "')]//ancestor::p[1]", $root, true, "#:\s*(.+)#");

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $seats = implode("\n", $this->http->FindNodes("./following-sibling::tr[1][.//text()[normalize-space(.)='" . $this->t("Seating Details:") . "']]//text()[string-length(normalize-space(.))>1]", $root));

                if (preg_match_all("#" . $this->t("Coach") . "\s*(\w+)\s*,\s*" . $this->t("Seat") . "\s+(\d+)\b#", $seats, $m)) {
                    foreach ($m[1] as $k => $v) {
                        $itsegment['Seats'][] = $m[1][$k] . ' - ' . $m[2][$k];
                    }
                }

                // Duration
                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
            }
        } else {
            //without train number
            $xpath = "//*[contains(text(), '" . $this->t("Departs") . "')]/ancestor::tr[1]/..";
            $nodes = $this->http->XPath->query($xpath);

            foreach ($nodes as $root) {
                // FlightNumber
                // $itsegment['FlightNumber'] = FLIGHT_NUMBER_UNKNOWN;

                // DepCode
                $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

                // DepName
                $itsegment['DepName'] = $this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)][1]", $root);

                // DepAddress
                // DepDate
                $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)][5]", $root) . ',' . $this->http->FindSingleNode("./tr[1]/td[2]/descendant::text()[normalize-space(.)][3]", $root)));

                // ArrCode
                $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

                // ArrName
                $itsegment['ArrName'] = $this->http->FindSingleNode("./tr[2]/td[2]/descendant::text()[normalize-space(.)][1]", $root);

                // ArrAddress
                // ArrDate
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./tr[2]/td[2]/descendant::text()[normalize-space(.)][5]", $root) . ',' . $this->http->FindSingleNode("./tr[2]/td[2]/descendant::text()[normalize-space(.)][3]", $root)));

                // Type
                $itsegment['Type'] = $this->http->FindSingleNode("(//*[contains(text(), '" . $this->t("Eurostar seats") . "')]/ancestor::table[1]//*[contains(text(), '" . $this->t("Carriage") . "')])[1]", null, true, "#" . $this->t("Carriage") . " ([0-9]+)#");

                // TraveledMiles
                // Cabin
                $itsegment['Cabin'] = trim($this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//tr[2]/td/descendant::text()[normalize-space(.)][1]", $root), ' :');

                // BookingClass
                // PendingUpgradeTo
                // Seats
                $itsegment['Seats'] = implode(", ", array_map(function ($s) { return str_replace(" " . $this->t("Seat") . " ", '-', $s); }, $this->http->FindNodes("//*[contains(text(), '" . $this->t("Eurostar seats") . "')]/ancestor::table[1]//*[contains(text(), '" . $this->t("Carriage") . "')]", null, "#" . $this->t("Carriage") . " ([0-9]+ " . $this->t("Seat") . " [0-9]+)#")));

                // Duration
                $itsegment['Duration'] = $this->http->FindSingleNode("./ancestor::tr[1]/following-sibling::tr[1]//text()[normalize-space(.)='" . $this->t("Duration") . "']/following::text()[normalize-space(.)][1]", $root);

                // Meal
                // Smoking
                // Stops
                $it['TripSegments'][] = $itsegment;
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
        // $this->http->log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\d\W]+\s+(\d+)[^\d\W]+\s+([^\d\W]+),(\d+:\d+)$#",
            "#^[^\d\W]+\s+(\d+)\s+(\w+),(\d+:\d+)$#",
            "#^([^\d\W]+)\s+([^\d\W]+),(\d+:\d+)$#",
            "#^(\d+)\s+([^\s\d]+),(\d+:\d+)$#", //31 Oct,06:18
        ];
        $out = [
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
            "$1 $2 $year, $3",
        ];
        // $this->http->log(preg_replace($in, $out, $str));
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
