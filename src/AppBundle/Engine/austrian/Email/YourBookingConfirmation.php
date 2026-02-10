<?php

namespace AwardWallet\Engine\austrian\Email;

use AwardWallet\Engine\MonthTranslate;

class YourBookingConfirmation extends \TAccountChecker
{
    public $mailFiles = "austrian/it-1622067.eml, austrian/it-1748615.eml, austrian/it-1790625.eml, austrian/it-2085069.eml, austrian/it-4009162.eml, austrian/it-4102315.eml, austrian/it-5205997.eml, austrian/it-5313540.eml, austrian/it-6108606.eml, austrian/it-6187064.eml, austrian/it-6641416.eml, austrian/it-8562812.eml, austrian/it-8659383.eml, austrian/it-8661626.eml";
    public $reFrom = "no-reply@austrian.com";
    public $reSubject = [
        "en"=> "Your Austrian booking confirmation",
        "de"=> "Ihre Austrian Buchungsbestätigung",
    ];
    public $reBody = 'Austrian';
    public $reBody2 = [
        "en"=> "Flight number",
        "de"=> "Flugnummer",
        "it"=> "Numero di volo",
        "ru"=> "Номер рейса",
        "uk"=> "Номер рейсу",
        "pl"=> "Numer rejsu",
        "ro"=> "Numar de zbor",
        "fr"=> "Numéro de vol",
    ];
    public $date;

    public static $dictionary = [
        "en" => [],
        "de" => [
            "Your Booking code:"      => "Buchungscode",
            "Name:"                   => "Name:",
            "Total for all passengers"=> "Gesamt für alle Reisenden",
            "Flight number"           => "Flugnummer",
        ],
        "it" => [
            "Your Booking code:"      => "Codice della sua prenotazione:",
            "Name:"                   => "Nome:",
            "Total for all passengers"=> "NOTTRANSLATED",
            "Flight number"           => "Numero di volo",
        ],
        "ru" => [
            "Your Booking code:"      => "Код бронирования:",
            "Name:"                   => "Имя:",
            "Total for all passengers"=> "Итого за всех пассажиров",
            "Flight number"           => "Номер рейса",
        ],
        "uk" => [
            "Your Booking code:"      => "Код бронювання:",
            "Name:"                   => "Ім'я:",
            "Total for all passengers"=> "Загальна вартість за всіх пасажирів",
            "Flight number"           => "Номер рейсу",
        ],
        "pl" => [
            "Your Booking code:"      => "Kod rezerwacji:",
            "Name:"                   => "Nazwisko:",
            "Total for all passengers"=> "Całkowita cena dla wszystkich pasażerów",
            "Flight number"           => "Numer rejsu",
        ],
        "ro" => [
            "Your Booking code:"      => "codul de rezervare:",
            "Name:"                   => "Nume:",
            "Total for all passengers"=> "Tatal pentru toti pasagerii",
            "Flight number"           => "Numar de zbor",
        ],
        "fr" => [
            "Your Booking code:"      => "Votre code de réservation",
            "Name:"                   => "Nom:",
            "Total for all passengers"=> "Prix total pour tous les passagers",
            "Flight number"           => "Numéro de vol",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Your Booking code:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[" . $this->eq($this->t("Name:")) . "]/following::text()[normalize-space(.)][1]");

        // TicketNumbers
        // AccountNumbers
        // Cancelled
        // TotalCharge
        $it['TotalCharge'] = $this->amount($this->nextText($this->t("Total for all passengers")));

        // BaseFare
        // Currency
        $it['Currency'] = $this->currency($this->nextText($this->t("Total for all passengers")));

        // Tax
        // SpentAwards
        // EarnedAwards
        // Status
        // ReservationDate
        // NoItineraries
        // TripCategory
        $xpath = "//tr[count(./td[normalize-space(.)])=5][not(" . $this->contains($this->t("Flight number")) . ")]";
        $nodes = $this->http->XPath->query($xpath);

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root)));
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#\w{2}\s+(\d+)#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]/descendant::text()[normalize-space(.)!=''][1]", $root);

            // DepartureTerminal
            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!=''][1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][3]/descendant::text()[normalize-space(.)!=''][2]", $root);

            // ArrivalTerminal
            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][2]/descendant::text()[normalize-space(.)!=''][2]", $root)), $date);

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#(\w{2})\s+\d+#");

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#\((\w{2})\)#");

            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root, true, "#(.*?)\s*/#");

            // BookingClass
            $itsegment['BookingClass'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][5]", $root, true, "#/\s*(\w)$#");

            // PendingUpgradeTo
            // Seats
            $itsegment['Seats'] = array_unique(array_filter($this->http->FindNodes("//text()[" . $this->contains("{$itsegment['AirlineName']} {$itsegment['FlightNumber']}:") . "]", null, "#{$itsegment['AirlineName']} {$itsegment['FlightNumber']}:\s*(\d+\w)#")));

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
        //		$this->http->Log($str);
        $year = date("Y", $this->date);
        $in = [
            "#^[^\s\d]+, ([^\s\d]+)\. (\d+), (\d{4})$#", //Wed, Dec. 16, 2015
            "#^[^\s\d]+, (\d+)\. (\w+)\. (\d{4})$#", //Sa, 9. Aug. 2014
            "#^[^\s\d]+, (\d+\.\d+\.\d{4})$#", //mer, 14.08.2013
            "#^[^\s\d]+, (\d+ \w+ \d{4})$#u", //Пн, 5 янв 2015
            "#^[^\s\d]+, (\d+)-\w (\w+)\.? (\d{4})$#u", //Чт, 7-е Трав. 2015
            "#^[^\s\d]+, (\d+) (\w+)\.? (\d{4})$#u", //Mi, 02 Mar. 2016

            "#^(\d+:\d+)\+1$#", //08:40+1
            "#^(\d+:\d+)\(\+1\)$#", //08:40(+1)
        ];
        $out = [
            "$2 $1 $3",
            "$1 $2 $3",
            "$1",
            "$1",
            "$1 $2 $3",
            "$1 $2 $3",

            "$1, +1 day",
            "$1, +1 day",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            } elseif ($en = MonthTranslate::translate($m[1], 'fr')) {
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

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)!=''][1]", $root);
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
