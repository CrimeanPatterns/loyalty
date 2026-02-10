<?php

namespace AwardWallet\Engine\airpanama\Email;

use AwardWallet\Engine\MonthTranslate;

class Confirmed extends \TAccountChecker
{
    public $mailFiles = "airpanama/it-8010872.eml, airpanama/it-8015931.eml, airpanama/it-8015941.eml";
    public $reFrom = "soporte@airpanama.com";
    public $reSubject = [
        "en"=> "AirPanama - Your PNR",
    ];
    public $reBody = 'Airpanama.com';
    public $reBody2 = [
        "en"=> "Air Itinerary Details:",
        "es"=> "Detalles del itinerario:",
    ];

    public static $dictionary = [
        "en" => [
            //			'Thank you for purchasing online' => '',
            //			'Your reservation Code:' => '',
            //			'Passenger Name' => '',
            //			'E-ticket No.' => '',
            //			'Total' => '',
            //			'has been' => '',
            //			'Departure' => '',
        ],
        "es" => [
            'Thank you for purchasing online' => 'Gracias por comprar en',
            'Your reservation Code:'          => 'Su código de reserva:',
            'Passenger Name'                  => 'Nombre de Pasajero',
            'E-ticket No.'                    => 'Núm. de Eticket',
            'Total'                           => 'Total de Impuestos',
            'has been'                        => 'ha sido',
            'Departure'                       => 'Salida',
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your reservation Code:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("Passenger Name")) . "]/ancestor::tr[1]/following-sibling::tr/td[3]"));

        // TicketNumbers
        $pos = count($this->http->FindNodes("//text()[" . $this->eq($this->t("E-ticket No.")) . "]/ancestor::td[1]/preceding-sibling::td"));

        if (!empty($pos)) {
            $it['TicketNumbers'] = array_unique($this->http->FindNodes("//text()[" . $this->eq($this->t("E-ticket No.")) . "]/ancestor::tr[1]/following-sibling::tr/td[" . ($pos + 1) . "]"));
        }

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->http->FindSingleNode("//td[" . $this->eq($this->t("Total")) . "]/following-sibling::td[1]"));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->http->FindSingleNode("//td[" . $this->eq($this->t("Total")) . "]/following-sibling::td[1]"));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        $it['Status'] = $this->re("#" . $this->t("has been") . "\s+(\S+)#", $this->subject);

        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq($this->t("Departure")) . "]/ancestor::tr[1]/following-sibling::tr[1]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $root2 = $this->http->XPath->query("./preceding-sibling::tr[2]", $root)->item(0);
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./descendant::text()[normalize-space(.)][2]", $root2)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[1]", $root);

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()]", $root2, true, "#([A-Z]{3})\s+-\s+[A-Z]{3}#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./descendant::text()[normalize-space(.)][last()]", $root2, true, "#[A-Z]{3}\s+-\s+([A-Z]{3})#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // AirlineName
            if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thank you for purchasing online")) . "]/ancestor::*[position()<4][contains(., 'airpanama.com') or contains(.//a/@href, 'airpanama.com')])[1]"))) {
                $itsegment['AirlineName'] = '7P';
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]", $root, true, "#^\w\s*:\s*(.+)#");

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[5]", $root, true, "#^(\w)\s*:\s*#");

            // PendingUpgradeTo
            // Seats
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]", $root);

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
            if (stripos($headers["subject"], $re) !== false) {
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
        $this->subject = $parser->getSubject();
        $this->date = strtotime($parser->getHeader('date'));

        $this->http->FilterHTML = true;
        $itineraries = [];

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
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
        // $this->http->log($word);
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
            "#^[^\s\d]+\s+(\d+)\w{2}\s+of\s+([^\s\d]+)\s+(\d{4})$#", //Saturday 13th of February 2016
            "#^[^\s\d]+\s+(\d+)\s+de\s+([^\s\d]+)\s+de\s+(\d{4})$#", //Sábado 05 de Diciembre de 2015
        ];
        $out = [
            "$1 $2 $3",
            "$1 $2 $3",
        ];
        $str = preg_replace($in, $out, $str);

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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#([\d\,\.]+)#", $s)));
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
        $s = $this->re("#([^\d\,\.]+)#", $s);

        foreach ($sym as $f=> $r) {
            if (strpos($s, $f) !== false) {
                return $r;
            }
        }

        return null;
    }

    private function nextText($field, $root = null)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][1]", $root);
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
}
