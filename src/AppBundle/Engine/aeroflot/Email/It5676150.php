<?php

namespace AwardWallet\Engine\aeroflot\Email;

class It5676150 extends \TAccountChecker
{
    public $mailFiles = "aeroflot/it-1.eml, aeroflot/it-2.eml, aeroflot/it-3.eml, aeroflot/it-4.eml, aeroflot/it-5.eml, aeroflot/it-5676150.eml, aeroflot/it-5737119.eml";

    public $reFrom = "@aeroflot.ru";
    public $reSubject = [
        "en"=> "Electronic ticket receipt",
        "ru"=> "Квитанция об оплате электронного билета",
        "it"=> "Ricevuta  biglietto elettronico",
    ];
    public $reBody = 'Aeroflot';
    public $reBody2 = [
        "en"=> "Flight",
        "ru"=> "Рейсы",
        "it"=> "Voli",
    ];

    public static $dictionary = [
        "en" => [],
        "ru" => [
            "Reservation code:"=> "Код бронирования:",
            "Pre-order code:"  => "Код предварительного заказа:",
            "Passenger"        => "Пассажир(ы):",
            "Flight"           => "Рейсы",
        ],
        "it" => [
            "Reservation code:"=> "Codice di prenotazione:",
            "Passenger"        => "Passeggero/i:",
            "Flight"           => "Voli",
        ],
    ];

    public $lang = "en";

    public $dateTimeToolsMonthsOutMonths = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

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
        "ru" => [
            "январь"  => 0, "янв" => 0, "января" => 0,
            "февраля" => 1, "фев" => 1, "февраль" => 1,
            "марта"   => 2, "мар" => 2, "март" => 2,
            "апреля"  => 3, "апр" => 3, "апрель" => 3,
            "мая"     => 4, "май" => 4,
            "июн"     => 5, "июня" => 5, "июнь" => 5,
            "июля"    => 6, "июль" => 6, "июл" => 6,
            "августа" => 7, "авг" => 7, "август" => 7,
            "сен"     => 8, "сентябрь" => 8, "сентября" => 8,
            "окт"     => 9, "октября" => 9, "октябрь" => 9,
            "ноя"     => 10, "ноября" => 10, "ноябрь" => 10,
            "дек"     => 11, "декабрь" => 11, "декабря" => 11,
        ],
        "de" => [
            "januar"    => 0, "jan" => 0,
            "februar"   => 1, "feb" => 1,
            "mae"       => 2, "maerz" => 2, "märz" => 2, "mrz" => 2,
            "apr"       => 3, "april" => 3,
            "mai"       => 4,
            "juni"      => 5, "jun" => 5,
            "jul"       => 6, "juli" => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sep" => 8,
            "oktober"   => 9, "okt" => 9,
            "nov"       => 10, "november" => 10,
            "dez"       => 11, "dezember" => 11,
        ],
        "nl" => [
            "januari"   => 0,
            "februari"  => 1,
            "mrt"       => 2, "maart" => 2,
            "april"     => 3,
            "mei"       => 4,
            "juni"      => 5,
            "juli"      => 6,
            "augustus"  => 7,
            "september" => 8,
            "oktober"   => 9,
            "november"  => 10,
            "december"  => 11,
        ],
        "no" => [
            "januar"    => 0, "jan" => 0,
            "febr"      => 1, "februar" => 1,
            "mars"      => 2,
            "april"     => 3,
            "mai"       => 4, "kan" => 4,
            "juni"      => 5,
            "juli"      => 6,
            "august"    => 7, "aug" => 7,
            "september" => 8, "sept" => 8,
            "okt"       => 9, "oktober" => 9,
            "nov"       => 10, "november" => 10,
            "des"       => 11, "desember" => 11,
        ],
        "fr" => [
            "janv"     => 0, "janvier" => 0,
            "févr"     => 1, "fevrier" => 1, "février" => 1,
            "mars"     => 2,
            "avril"    => 3, "avr" => 3,
            "mai"      => 4,
            "juin"     => 5,
            "juillet"  => 6, "juil" => 6,
            "août"     => 7, "aout" => 7,
            "sept"     => 8, "septembre" => 8,
            "oct"      => 9, "octobre" => 9,
            "novembre" => 10, "nov" => 10,
            "decembre" => 11, "décembre" => 11, "déc" => 11,
        ],
        "es" => [
            "enero"  => 0, "ene" => 0,
            "feb"    => 1, "febrero" => 1,
            "marzo"  => 2,
            "abr"    => 3, "abril" => 3,
            "mayo"   => 4,
            "jun"    => 5, "junio" => 5,
            "julio"  => 6, "jul" => 6,
            "agosto" => 7,
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
        "it" => [
            "gen"       => 0, "gennaio" => 0,
            "feb"       => 1, "febbraio" => 1,
            "marzo"     => 2, "mar" => 2,
            "apr"       => 3, "aprile" => 3,
            "maggio"    => 4, "mag" => 4,
            "giu"       => 5, "giugno" => 5,
            "luglio"    => 6, "lug" => 6,
            "ago"       => 7, "agosto" => 7,
            "settembre" => 8, "set" => 8,
            "ott"       => 9, "ottobre" => 9,
            "novembre"  => 10, "nov" => 10,
            "dic"       => 11, "dicembre" => 11,
        ],
        "pl" => [
            "styczeń"     => 0, "styczen" => 0,
            "luty"        => 1, "lut" => 1,
            "marzec"      => 2,
            "kwiecień"    => 3, "kwiecien" => 3,
            "maj"         => 4,
            "czerwiec"    => 5,
            "lipiec"      => 6, "lipca" => 6,
            "sierpien"    => 7, "sierpień" => 7,
            "wrzesien"    => 8, "wrzesień" => 8,
            "pazdziernik" => 9, "październik" => 9, "października" => 9,
            "listopad"    => 10, "lis" => 10,
            "grudzien"    => 11, "grudzień" => 11,
        ],
        "hu" => [
            "január"     => 0, "jan" => 0,
            "február"    => 1, "feb" => 1,
            "március"    => 2, "már" => 2,
            "április"    => 3, "ápr" => 3,
            "május"      => 4, "máj" => 4,
            "június"     => 5, "jún" => 5,
            "július"     => 6, "júl" => 6,
            "augusztus"  => 7, "aug" => 7,
            "szeptember" => 8,
            "október"    => 9, "okt" => 9,
            "november"   => 10, "nov" => 10,
            "december"   => 11, "dec" => 11,
        ],
        "ro" => [
            "ian" => 0,
            "feb" => 1,
            "mar" => 2,
            "apr" => 3,
            "mai" => 4,
            "iun" => 5,
            "iul" => 6,
            "aug" => 7,
            "sep" => 8,
            "oct" => 9,
            "noi" => 10,
            "dec" => 11,
        ],
    ];

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(.), '" . $this->t("Reservation code:") . "') or starts-with(normalize-space(.), '" . $this->t("Pre-order code:") . "')]", null, true, "#^(?:" . $this->t("Reservation code:") . "|" . $this->t("Pre-order code:") . ")\s+(\w+)$#");

        // TripNumber
        // Passengers
        $n = count($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passenger") . "']/ancestor::td[1]/preceding-sibling::td")) + 1;
        $it['Passengers'] = array_unique($this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passenger") . "']/ancestor::tr[1]/following-sibling::tr/td[{$n}]"));

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

        $xpath = "//text()[normalize-space(.)='" . $this->t("Flight") . "']/ancestor::tr[./following-sibling::tr][1]/following-sibling::tr//tr[not(.//tr)][normalize-space(.)]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[1]", $root)));

            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[4]", $root, true, "#^\w{2}\s+(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root);

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][3]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root);

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][3]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root), $date);

            if ($itsegment['ArrDate'] < $itsegment['DepDate']) {
                $itsegment['ArrDate'] = strtotime("+1 day", $itsegment['ArrDate']);
            }

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[4]", $root, true, "#^(\w{2})\s+\d+$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // Cabin
            $itsegment['Cabin'] = $this->http->FindSingleNode("./td[5]/descendant::text()[normalize-space(.)][2]", $root);

            // BookingClass
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
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response["body"])); // bad fr char " :"

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

    private function normalizeDate($str)
    {
        $year = date("Y", $this->date);
        $in = [
            "#^(\d+\s+[^\d\s]+\s+\d{4})\s+-\s+\d+\s+[^\d\s]+\s+\d{4}$#",
        ];
        $out = [
            "$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = $this->translateMonth($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
    }
}
