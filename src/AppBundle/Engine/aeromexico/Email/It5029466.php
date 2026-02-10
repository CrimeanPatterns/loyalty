<?php

namespace AwardWallet\Engine\aeromexico\Email;

class It5029466 extends \TAccountChecker
{
    public $mailFiles = "aeromexico/it-1.eml, aeromexico/it-1593546.eml, aeromexico/it-1798868.eml, aeromexico/it-2.eml, aeromexico/it-2054928.eml, aeromexico/it-2594914.eml, aeromexico/it-3354115.eml, aeromexico/it-5029466.eml, aeromexico/it-5287249.eml, aeromexico/it-5587520.eml";

    public $reFrom = "@aeromexico.com";
    public $reSubject = [
        "en"=> "Purchase Confirmation",
        "es"=> "Purchase Confirmation de Viaje",
        "pt"=> "Recibo do bilhete eletrônico",
    ];
    public $reBody = 'aeromexico.com';
    public $reBody2 = [
        "en"=> "Flight",
        "es"=> "Vuelo",
        "pt"=> "Voo",
    ];

    public static $dictionary = [
        "en" => [],
        "es" => [
            "Reservation code:"=> "Código de reservación:",
            "Flight"           => "Vuelo",
            "Operated by"      => "Operado por",
        ],
        "pt" => [
            "Reservation code:"=> "Código de reserva:",
            "Flight"           => "N. do Voo",
            //			"Operated by"=>"Operado pela",//maybe need correct
        ],
    ];

    public $lang = "en";

    public $dateTimeToolsMonths = [
        "en" => [
            "january"   => 0,
            "february"  => 1,
            "march"     => 2,
            "april"     => 3,
            "may"       => 4,
            "june"      => 5,
            "july"      => 6,
            "august"    => 7,
            "september" => 8,
            "october"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "es" => [
            "enero"  => 0, "ene" => 0,
            "feb"    => 1, "febrero" => 1,
            "marzo"  => 2,
            "abr"    => 3, "abril" => 3,
            "mayo"   => 4,
            "jun"    => 5, "junio" => 5,
            "julio"  => 6, "jul" => 6,
            "agosto" => 7, "ago" => 7,
            "sept"   => 8, "septiembre" => 8,
            "oct"    => 9, "octubre" => 9,
            "nov"    => 10, "noviembre" => 10,
            "dic"    => 11, "diciembre" => 11,
        ],
        "pt" => [
            "jan"      => 0, "janeiro" => 0,
            "fev"      => 1, "fevereiro" => 1,
            "março"    => 2, "mar" => 2,
            "abr"      => 3, "abril" => 3,
            "maio"     => 4, "mai" => 4,
            "jun"      => 5, "junho" => 5,
            "julho"    => 6, "jul" => 6,
            "ago"      => 7, "agosto" => 7,
            "setembro" => 8, "set" => 8,
            "out"      => 9, "outubro" => 9,
            "novembro" => 10, "non" => 10,
            "dez"      => 11, "dezembro" => 11,
        ],
    ];
    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Reservation code:") . "')]", null, true, "#:\s+(\w+)#");

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::tr[1]/preceding::text()[string-length(normalize-space(.))>1][1]/ancestor::p[1]/descendant::text()[normalize-space(.)][1]");

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

        $xpath = "//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::tr[./following-sibling::tr[1]][1]/following-sibling::tr[1]//tr[not(.//tr) and normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }
        $fls = [];

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode('(./td[1]//text()[normalize-space(.)!=""])[1]', $root, true, "#(.*?)(?:\s*-|$)#")));
            $dateArr = strtotime($this->normalizeDate($this->http->FindSingleNode('(./td[1]//text()[normalize-space(.)!=""])[2]', $root, true, "#-?\s*(.*?)\s*$#")));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#^\w{2}\s*(\d+)$#");

            if (isset($fls[$itsegment['FlightNumber']])) {
                continue;
            }
            $fls[$itsegment['FlightNumber']] = 1;
            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);

            // ArrDate
            if ($dateArr) {
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $dateArr);
            } else {
                $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $date);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]/descendant::text()[normalize-space(.)][1]", $root, true, "#^(\w{2})\s*\d+$#");

            // Operator
            $itsegment['Operator'] = $this->nextText($this->t("Operated by"), $root);

            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][2]", $root);

            // BookingClass
            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = $this->http->FindSingleNode('./td[6]', $root, true, '/^[A-Z\d]{2,3}$/');

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
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (stripos($headers["from"], $this->reFrom) === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, $this->reBody) === false) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (stripos($body, $re) !== false) {
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

    public function dateStringToEnglish($date)
    {
        if (preg_match('#[[:alpha:]]+#iu', $date, $m)) {
            $monthNameOriginal = $m[0];

            if ($translatedMonthName = $this->translateMonth($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
    }

    public function translateMonth($month, $lang)
    {
        $month = mb_strtolower(trim($month), 'UTF-8');

        if (isset($this->dateTimeToolsMonths[$lang]) && isset($this->dateTimeToolsMonths[$lang][$month])) {
            return $this->dateTimeToolsMonthsOutMonths[$this->dateTimeToolsMonths[$lang][$month]];
        }

        return false;
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
            "#^\w+\s+-\s+\w+,\s+(\w+)\s+(\d+)$#",
            "#^\w+,\s+(\w+)\s+(\d+)\s+(\d+:\d+\s+[AP]M)$#",
        ];
        $out = [
            "$2 $1 $year",
            "$2 $1 $year, $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#[^\d\s-\./:]#", $str)) {
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

    private function amount($s)
    {
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }
}
