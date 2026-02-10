<?php

namespace AwardWallet\Engine\aeromexico\Email;

class It4709371 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aeromexico/it-4709371.eml, aeromexico/it-5112778.eml, aeromexico/it-5119193.eml, aeromexico/it-6162090.eml";

    public $reFrom = "@aeromexico.com.mx";
    public $reSubject = [
        "es"=> "Tu vuelo a",
        "en"=> "Your flight",
        "pt"=> "Seu voo para ",
    ];
    public $reBody = 'aeromexico.com';
    public $reBody2 = [
        "es"=> "Vuelos:",
        "en"=> "Flights:",
        "pt"=> "Passageiros:",
    ];

    public static $dictionary = [
        "es" => [],
        "en" => [
            "Pasajero:"            => "Passenger:",
            "Clave de Reservación:"=> "Confirmation number:",
            "Vuelos:"              => "Flights:",
            ", saliendo a las"     => "at",
        ],
        "pt" => [
            "Pasajero:"            => "Passageiros:",
            "Clave de Reservación:"=> "Número de confirmação:",
            "Vuelos:"              => "Voos:",
            ", saliendo a las"     => "às",
        ],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $seats = [];

        foreach ($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Pasajero:") . "']/ancestor::tr[1]/following-sibling::tr/td[2][not(contains(., 'N/A'))]") as $s) {
            foreach (array_map('trim', explode("/", $s)) as $i=>$seat) {
                $seats[$i][] = $seat;
            }
        }

        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Clave de Reservación:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Pasajero:") . "']/ancestor::tr[1]/following-sibling::tr/td[1]");

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

        $xpath = "//text()[normalize-space(.)='" . $this->t("Vuelos:") . "']/ancestor::tr[1]/following-sibling::tr";
        $nodes = $this->http->XPath->query($xpath);

        //sort before date & after date flights
        $before = [];
        $after = [];

        foreach ($nodes as $root) {
            $style = strtolower($this->http->FindSingleNode("./@style", $root));

            if (strpos($style, "#ff0000") !== false || strpos($style, "red") !== false) {
                $main = $root;
            } elseif (!isset($main)) {
                $before[] = $root;
            } elseif (isset($main)) {
                $after[] = $root;
            }
        }

        if (!isset($main)) {
            return;
        }

        //calculate dates
        if (!($date = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t(", saliendo a las") . "']/preceding::text()[normalize-space(.)][1]"))) {
            $date = trim($this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("The flight will now depart on") . "']/following::text()[normalize-space(.)][1]"));
        }
        $date = $date2 = strtotime($this->normalizeDate($date));
        $dates = [];

        //main
        $flnum = $this->http->FindSingleNode("./td[1]", $main, true, "#^\d+\s+\w{2}\s+(\d+)#");
        $dates[$flnum] = $date;

        //before
        $rev = array_reverse($before);
        $dep = $this->http->FindSingleNode("./td[3]", $main, true, "#(\d+:\d+)\s+-\s+\d+:\d+#"); //main dep time

        foreach ($rev as $root) {
            $arr = $this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+\s+-\s+(\d+:\d+)#");

            if (strtotime($arr, $date) > strtotime($dep, $date)) {
                $date = strtotime("-1 day", $date);
            }
            $dep = $this->http->FindSingleNode("./td[3]", $root, true, "#(\d+:\d+)\s+-\s+\d+:\d+#");

            if (strtotime($arr, $date) < strtotime($dep, $date)) {
                $date = strtotime("-1 day", $date);
            }

            $flnum = $this->http->FindSingleNode("./td[1]", $root, true, "#^\d+\s+\w{2}\s+(\d+)#");
            $dates[$flnum] = $date;
        }

        $date = $date2;
        //after
        $arr = $this->http->FindSingleNode("./td[3]", $main, true, "#\d+:\d+\s+-\s+(\d+:\d+)#"); //main arr time

        foreach ($after as $root) {
            $dep = $this->http->FindSingleNode("./td[3]", $root, true, "#(\d+:\d+)\s+-\s+\d+:\d+#");

            if (strtotime($arr, $date) > strtotime($dep, $date)) {
                $date = strtotime("+1 day", $date);
            }

            $flnum = $this->http->FindSingleNode("./td[1]", $root, true, "#^\d+\s+\w{2}\s+(\d+)#");
            $dates[$flnum] = $date;

            $arr = $this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+\s+-\s+(\d+:\d+)#");

            if (strtotime($arr, $date) < strtotime($dep, $date)) {
                $date = strtotime("+1 day", $date);
            }
        }
        $i = 0;

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\d+\s+\w{2}\s+(\d+)#");

            $date = $dates[$itsegment['FlightNumber']];

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#(.*?)(?:Term.+?)?\s+-\s+#");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#(\d+:\d+)\s+-\s+\d+:\d+#"), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[2]", $root, true, "#.*?\s+-\s+(.+?)\s*(?:Term.+?)?$#");

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]", $root, true, "#\d+:\d+\s+-\s+(\d+:\d+)#"), $itsegment['DepDate']);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[1]", $root, true, "#^\d+\s+(\w{2})\s+\d+#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            // BookingClass
            // PendingUpgradeTo
            // Seats
            if (isset($seats[$i])) {
                $itsegment['Seats'] = implode(",", $seats[$i]);
            }

            // Duration
            // Meal
            // Smoking
            // Stops
            $it['TripSegments'][] = $itsegment;
            $i++;
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
            "#^[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#",
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})$#",
            "#^[^\d\s]+,\s+([^\d\s]+)\s+(\d+),\s+(\d{4})\s+at\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1 $2 $3",
            "$2 $1 $3",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./]#", $str)) {
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
