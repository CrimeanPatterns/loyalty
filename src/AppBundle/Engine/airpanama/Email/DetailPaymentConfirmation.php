<?php

namespace AwardWallet\Engine\airpanama\Email;

use AwardWallet\Engine\MonthTranslate;

class DetailPaymentConfirmation extends \TAccountChecker
{
    public $mailFiles = "airpanama/it-10052537.eml, airpanama/it-8532522.eml, airpanama/it-8532525.eml, airpanama/it-8698305.eml";
    public $reFrom = "no-reply@airpanama.com";
    public $reSubject = [
        "en" => "Detail Payment Confirmation",
        "en2"=> "Air Panama Web - Reservation Detail",
        "es" => " Air Panama Web - Detalle de Reservación",
    ];
    public $reBody = 'airpanama.com';
    public $reBody2 = [
        "en" => "Destination",
        'es' => 'Detalles de su cuenta con Air Panama',
    ];

    public static $dictionary = [
        "en" => [
            'Your Booking code'   => ["YOUR BOOKING CODE:", "Your Booking code:"],
            'Total'               => ["Total Paid", "Total Cost to Pay"],
            'Thanks for choosing' => ["Thanks for choosing", "Thank you for choosing"],
        ],
        'es' => [
            'Last Name'            => 'Apellido',
            'Number of E-Ticket'   => 'Num. Documento',
            'Your Booking code'    => 'DE RESERVA',
            'Total'                => 'Costo Total a Pagar',
            'Total Taxes and fees' => 'Impuestos y Recargos',
            'Flight Date'          => 'Fecha de Vuelo',
            'Thanks for choosing'  => 'Gracias por preferir',
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode('(.//text()[' . $this->contains($this->t('Your Booking code')) . '])[1]/following::text()[normalize-space(.)][1]');

        // TripNumber
        // Passengers
        $it['Passengers'] = [];
        $nodes = $this->http->XPath->query("//text()[" . $this->eq($this->t("Last Name")) . "]/ancestor::table[1]/descendant::tr[position()>1]");

        foreach ($nodes as $root) {
            $it['Passengers'][] = implode(" ", $this->http->FindNodes("./*[position()=3 or position()=4]", $root));
        }

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Number of E-Ticket")) . "]/ancestor::table[1]/descendant::tr[position()>1]/*[last()][not(contains(., 'NO'))]");

        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t('Total')));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total Taxes and fees")));

        // Tax
        $it['Tax'] = $this->amount($this->nextText($this->t("Total Taxes and fees")));

        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//text()[" . $this->eq($this->t("Flight Date")) . "]/ancestor::table[1]/tbody/tr[./*[7]]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./*[4]", $root);

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./*[2]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[6]", $root)), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./*[3]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./*[7]", $root)), $date);

            // AirlineName
            if (!empty($this->http->FindSingleNode("(//text()[" . $this->contains($this->t("Thanks for choosing")) . "]/ancestor::*[position()<4][contains(., 'airpanama.com') or contains(.//a/@href, 'airpanama.com')])[1]"))) {
                $itsegment['AirlineName'] = '7P';
            }

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./*[5]", $root, true, "#(.*?)(?:\s+\(\w\)|$)#");

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./*[5]", $root, true, "#\((\w)\)#");

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
            if (stripos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response["body"];

        if (strpos($body, $this->reBody) === false) {
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
            "#^([^\s\d]+) (\d+), (\d{4})$#", //OCTOBER 09, 2017
            '/^(\d{1,2}) de (\w+) de (\d{2,4})$/i',
        ];
        $out = [
            "$1 $2 $3",
            '$1 $2 $3',
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
        return (float) str_replace(",", ".", preg_replace("#[.,](\d{3})#", "$1", $this->re("#\b([\d\,\.]+)#", $s)));
    }

    private function currency($s)
    {
        $sym = [
            '€'   => 'EUR',
            '$'   => 'USD',
            '£'   => 'GBP',
            'B/.' => 'PAB',
        ];

        if ($code = $this->re("#(?:^|\s)([A-Z]{3})(?:$|\s)#", $s)) {
            return $code;
        }
        $s = $this->re("#(?:^|\b)([^\d\,]+)\b#", trim($s));

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
