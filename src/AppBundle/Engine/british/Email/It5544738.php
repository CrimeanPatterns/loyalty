<?php

namespace AwardWallet\Engine\british\Email;

class It5544738 extends \TAccountChecker
{
    use \DateTimeTools;
    use \PriceTools;
    public $mailFiles = "british/it-5544738.eml";

    public $reFrom = "@britishairways.com";
    public $reSubject = [
        "es"=> "onfirmación de la facturación de BA",
        "de"=> "BA Check-In-Bestätigung",
    ];
    public $reBody = 'British Airways';
    public $reBody2 = [
        "es"=> "CONFIRMACIÓN DE LA FACTURACIÓN",
        "de"=> "CHECK-IN-BESTÄTIGUNG",
    ];

    public static $dictionary = [
        "es" => [],
        "de" => [
            "Número de vuelo:"=> "Flugnummer:",
            "Salida:"         => "Abflug:",
            "Llegada:"        => "Ankunft:",
            "Clase:"          => "Klasse:",
            "Salida:"         => "Abflug:",
        ],
    ];

    public $lang = "es";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        // TripNumber
        // Passengers
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

        // echo $this->http->Response["body"];

        preg_match_all("#" . $this->t("Número de vuelo:") . "\s+(?<AirlineName>\w{2})(?<FlightNumber>\d+)\s*\n\s*" .
                        $this->t("Salida:") . "\s+(?<DepCode>[A-Z]{3})\s+\((?<DepName>.*?)\)(\s+Terminal \w)?\s*\n\s*" .
                        $this->t("Llegada:") . "\s+(?<ArrCode>[A-Z]{3})\s+\((?<ArrName>.*?)\)(\s+Terminal \w)?\s*\n\s*" .
                        $this->t("Clase:") . "\s+(?<Cabin>[^\n]+)\s*\n\s*" .
                        $this->t("Salida:") . "\s+(?<DepDate>\d+\s+\w+\s+\d{4}\s+\d+:\d+)#", $this->http->Response["body"], $segments, PREG_SET_ORDER);
        // print_r($segments);
        // die();

        foreach ($segments as $segment) {
            $itsegment = [];
            $keys = [
                "AirlineName",
                "FlightNumber",
                "DepCode",
                "DepName",
                "ArrCode",
                "ArrName",
                "Cabin",
            ];

            foreach ($keys as $key) {
                if (isset($segment[$key])) {
                    $itsegment[$key] = $segment[$key];
                }
            }
            $itsegment["DepDate"] = strtotime($segment["DepDate"]);
            $itsegment["ArrDate"] = MISSING_DATE;

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
        $body = $parser->getPlainBody();

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
        $this->http->setBody($parser->getPlainBody());

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
