<?php

namespace AwardWallet\Engine\flysaa\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;

class ConfirmationOfOnlineBooking extends \TAccountChecker
{
    public $mailFiles = "flysaa/it-12496198.eml, flysaa/it-1694388.eml, flysaa/it-1694389.eml, flysaa/it-1743872.eml, flysaa/it-1743873.eml, flysaa/it-2125443.eml, flysaa/it-23027611.eml, flysaa/it-3966985.eml";

    public $lang = "en";
    private $reFrom = "@flysaa.com";
    private $reSubject = [
        "en"=> "Confirmation of Online booking at flysaa.com",
        "pt"=> "Confirmação da reserva on-line no flysaa.com",
        "de"=> "Bestätigung der Online-Buchung über  flysaa.com",
        "es"=> "Confirmación de reserva en línea en flysaa.com V8AQMM",
    ];
    private $reBody = 'flysaa.com';
    private $reBody2 = [
        "en"=> "We would like to thank you for booking your flight with SAA",
        "pt"=> "Gostaríamos de agradecer-lhe por reservar o seu voo com a SAA",
        "de"=> "Vielen Dank für Ihre Flugbuchung bei SAA",
        "es"=> "Le agradecemos por reservar su vuelo con SAA",
    ];

    private static $dictionary = [
        "en" => [],
        "pt" => [
            "Booking reference:"=> "Referência da reserva:",
            "Age Group"         => "Grupo Etário",
            "Total"             => "Total",
            "Base fare"         => "Tarifa Base",
            "Taxes"             => "Impostos",
            "Sector"            => "Setor",
            "Operator"          => "Operador",
            "Departing"         => "Partida",
            "Arriving"          => "Chegada",
            "Aircraft"          => "Aeronave",
            "Class"             => "Classe",
            "Seat"              => "Assento",
            "Stops"             => "Paradas",
        ],
        "de" => [
            "Booking reference:"=> "Buchungsnummer:",
            "Age Group"         => "Altersgruppe",
            "Total"             => "Summe",
            "Base fare"         => "Lufttransportgebühren",
            "Taxes"             => "Steuern",
            "Sector"            => "Strecke",
            "Operator"          => "Fluggesellschaft",
            "Departing"         => "Abflug",
            "Arriving"          => "Ankunft",
            "Aircraft"          => "Flugzeug",
            "Class"             => "Klasse",
            "Seat"              => "NOTTRANSLATED",
            "Stops"             => "Zwischenstopps",
        ],
        "es" => [
            "Booking reference:" => "Referencia de la reserva:",
            "Age Group"          => "Grupo etáreo",
            "Total"              => "Total",
            "Base fare"          => "Tarifa base",
            "Taxes"              => "Impuestos",
            "Sector"             => "Sector",
            "Operator"           => "Operador",
            "Departing"          => "Salida",
            "Arriving"           => "Llegada",
            "Aircraft"           => "Aeronave",
            "Class"              => "Clase",
            "Seat"               => "Asiento",
            "Stops"              => "Escalas",
        ],
    ];
    private $date = null;

    public function parseHtml()
    {
        $itineraries = [];
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[" . $this->starts($this->t("Booking reference:")) . "]", null, true, "#" . $this->t("Booking reference:") . "\s*(.+)#");

        // TripNumber
        // Passengers
        foreach ($this->http->XPath->query("//text()[" . $this->eq($this->t("Age Group")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)]") as $root) {
            $it['Passengers'][] = implode(" ", $this->http->FindNodes("./td[normalize-space(.)][position()>2 and position()<5]", $root));
        }
        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("Total")));

        // BaseFare
        $it['BaseFare'] = $this->amount($this->nextText($this->t("Base fare")));

        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total")));

        // Tax
        $it['Tax'] = $this->amount($this->nextText($this->t("Taxes")));

        // Fees
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq($this->t("Sector")) . "]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)][not(" . $this->contains($this->t("Operator")) . ")]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            if (($root2 = $this->http->XPath->query("./following::text()[" . $this->eq($this->t("Operator")) . "]/ancestor::table[1]", $root)->item(0)) === null) {
                $this->logger->info("root2 is null");

                return null;
            }

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root, true, "#^\w{2}\s*(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#.*?\s*\(([A-Z]{3})\)\s*-\s*#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#(.*?)\s*\([A-Z]{3}\)\s*-\s*#");

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = $this->normalizeDate(implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Departing")) . "]/following::text()[normalize-space(.)][position()<3]", $root2)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#\s*-\s*.*?\s*\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)][1]", $root, true, "#\s*-\s*(.*?)\s*\([A-Z]{3}\)#");

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = $this->normalizeDate(implode(", ", $this->http->FindNodes(".//text()[" . $this->eq($this->t("Arriving")) . "]/following::text()[normalize-space(.)][position()<3]", $root2)));

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space(.)][2]", $root, true, "#^(\w{2})\s*\d+$#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Operator")) . "]/following::text()[normalize-space(.)][1]", $root2);

            // Aircraft
            $itsegment['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Aircraft")) . "]/following::text()[normalize-space(.)][1]", $root2);

            // TraveledMiles
            // AwardMiles
            // Cabin
            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Class")) . "]/following::text()[normalize-space(.)][1]", $root2);

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = array_filter($this->http->FindNodes("//text()[" . $this->eq($this->t("Seat")) . "]/ancestor::tr[1]/following-sibling::tr/td[normalize-space(.)][5]", $root2, "#{$itsegment['AirlineName']}\s*{$itsegment['FlightNumber']}\s*-\s*(\d+[A-Z])#"));

            // Duration
            // Meal
            // Smoking
            // Stops
            $itsegment['Stops'] = $this->http->FindSingleNode(".//text()[" . $this->eq($this->t("Stops")) . "]/following::text()[normalize-space(.)][1]", $root2);

            $it['TripSegments'][] = $itsegment;
        }

        $itineraries[] = $it;

        return $itineraries;
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
        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $result = [
            'emailType'  => $a[count($a = explode('\\', __CLASS__)) - 1] . ucfirst($this->lang),
            'parsedData' => [
                'Itineraries' => $this->parseHtml(),
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

    private function normalizeDate($instr, $relDate = false)
    {
        if ($relDate === false) {
            $relDate = $this->date;
        }
        // $this->http->log($instr);
        $in = [
            "#^(\d+ [^\s\d]+ \d{4}, \d+:\d+)$#", //03 December 2016, 13:30
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $instr);

        if (preg_match("#\d+\s+([^\d\s]+)\s+(?:\d{4}|%Y%)#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // fix for short febrary
        if (strpos($str, "29 February") !== false && date('m/d', strtotime(str_replace("%Y%", date('Y', $relDate), $str))) == '03/01') {
            $str = str_replace("%Y%", date('Y', $relDate) + 1, $str);
        }

        foreach ($in as $re) {
            if (preg_match($re, $instr, $m) && isset($m['week'])) {
                $str = str_replace("%Y%", date('Y', $relDate), $str);
                $dayOfWeekInt = WeekTranslate::number1($m['week'], $this->lang);

                return EmailDateHelper::parseDateUsingWeekDay($str, $dayOfWeekInt);
            }
        }

        if (strpos($str, "%Y%") !== false) {
            return EmailDateHelper::parseDateRelative(null, $relDate, true, $str);
        }

        return strtotime($str, $relDate);
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
        if (($s = $this->re("#([\d\,\.]+)#", $s)) === null) {
            return null;
        }

        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $s));
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            '$'=> 'USD',
            '£'=> 'GBP',
            '₹'=> 'INR',
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
