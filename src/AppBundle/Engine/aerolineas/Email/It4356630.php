<?php

namespace AwardWallet\Engine\aerolineas\Email;

class It4356630 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "aerolineas/it-4168916.eml, aerolineas/it-4266218.eml, aerolineas/it-4356630.eml";

    public $reFrom = "@aerolineas.com";
    public $reSubject = [
        "es"=> "Aerolineas Argentinas",
    ];
    public $reBody = 'aerolineas.com';
    public $reBody2 = [
        "es"=> "TARJETA DE EMBARQUE",
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
        $it['RecordLocator'] = $this->nextText("Código de Reserva:");

        // TripNumber
        // Passengers
        $it['Passengers'] = [$this->nextText("Nombre del pasajero:")];

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

        // $xpath = "//*[normalize-space(text())='Depart']/ancestor::tr[1]/..";
        // $nodes = $this->http->XPath->query($xpath);
        // if($nodes->length == 0){
        // $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        // }

        // foreach($nodes as $root){
        $date = strtotime($this->normalizeDate($this->nextText("Fecha:")));

        $itsegment = [];
        // FlightNumber
        $itsegment['FlightNumber'] = $this->re("#^\w{2}\s+(\d+)#", $this->nextText("Aerolínea y Vuelo:"));

        // DepCode
        $itsegment['DepCode'] = $this->re("#\(([A-Z]{3})#", $this->nextText("De:"));

        // DepName
        // DepDate
        $itsegment['DepDate'] = strtotime($this->nextText("Hora de Partida:"), $date);

        // ArrCode
        $itsegment['ArrCode'] = $this->re("#\(([A-Z]{3})#", $this->nextText("A:"));

        // ArrName
        // ArrDate
        $itsegment['ArrDate'] = strtotime($this->nextText(["Hora de arribo:", "Hora de llegada:"]), $date);

        // AirlineName
        $itsegment['AirlineName'] = $this->re("#^(\w{2})\s+\d+#", $this->nextText("Aerolínea y Vuelo:"));

        // Operator
        $itsegment['Operator'] = $this->re("#Operado por\s+(.+)#", $this->nextText("Aerolínea y Vuelo:"));

        // Aircraft
        // TraveledMiles
        // Cabin
        // BookingClass
        // PendingUpgradeTo
        // Seats
        $itsegment['Seats'] = $this->nextText("Asiento:");

        // Duration
        // Meal
        // Smoking
        // Stops
        $it['TripSegments'][] = $itsegment;
        // }
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
        if (!is_array($field)) {
            $field = [$field];
        }
        $rule = implode(" or ", array_map(function ($s) { return "normalize-space(.)='{$s}'"; }, $field));

        return $this->http->FindSingleNode("(.//text()[{$rule}])[{$n}]/following::text()[normalize-space(.)][1]", $root);
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
            "#^\w+\s+(\d+)\s+(\w+),\s+(\d{4})$#",
        ];
        $out = [
            "$1 $2 $3",
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
