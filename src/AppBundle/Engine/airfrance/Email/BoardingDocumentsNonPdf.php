<?php

namespace AwardWallet\Engine\airfrance\Email;

use AwardWallet\Engine\MonthTranslate;

class BoardingDocumentsNonPdf extends \TAccountChecker
{
    public $mailFiles = "airfrance/it-27923114.eml, airfrance/it-28129018.eml, airfrance/it-7620509.eml, airfrance/it-7872024.eml, airfrance/it-8368860.eml";

    public $reFrom = "@airfrance.";

    public $reSubject = [
        "en" => "Your Air France boarding documents on",
        "es" => "Sus documentos de embarque Air France",
        "ja" => "様へ、エールフランス航空より",
        "fr" => "Vos documents d’embarquement Air France",
        "pt" => "Os seus documentos de embarque Air France",
        "it" => "I suoi documenti d’imbarco Air France",
        "zh" => "的法航登机文件",
    ];

    public $reBody = 'Air France';
    public $date;
    public $reBody2 = [
        "en" => "Your next flight",
        "es" => "Su próximo vuelo",
        "ja" => "ご利用便",
        "fr" => "Votre prochain vol",
        "pt" => "O seu próximo voo",
        "it" => "Prossimo volo",
        "zh" => "您的下一趟航班",
    ];

    public static $dictionary = [
        "en" => [
            "Reservation number:" => ["Reservation number:", "Booking reference:"],
        ],
        "fr" => [
            "Reservation number:" => "Référence de réservation :",
            "Passenger(s) :"      => "Passager(s) :",
            "Ticket number"       => "Numéro de billet",
            "Departing from"      => "Départ de",
        ],
        "es" => [
            "Reservation number:" => "Referencia de la reserva:",
            "Passenger(s) :"      => "Pasajero(s):",
            "Ticket number"       => "Número del billete",
            "Departing from"      => "Salida de",
        ],
        "ja" => [
            "Reservation number:" => "予約番号：",
            "Passenger(s) :"      => "搭乗者：",
            "Ticket number"       => "チケット番号：",
            "Departing from"      => "出発地：",
            "Terminal"            => "ターミナル",
        ],
        "pt" => [
            "Reservation number:" => "Referência de reserva:",
            "Passenger(s) :"      => "Passageiro(s):",
            "Ticket number"       => "Número de bilhete",
            "Departing from"      => "Partida de",
            //            "Terminal" => ""
        ],
        "it" => [
            "Reservation number:" => "Codice di prenotazione:",
            "Passenger(s) :"      => "Passeggero/i:",
            "Ticket number"       => "Numero di biglietto",
            "Departing from"      => "Partenza da",
            //            "Terminal" => ""
        ],
        "zh" => [
            "Reservation number:" => "预订编号：",
            "Passenger(s) :"      => "乘客：",
            "Ticket number"       => "机票号码",
            "Departing from"      => "出发地：",
            //            "Terminal" => ""
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Reservation number:"));

        // TripNumber
        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t('Passenger(s) :') . "']/following::text()[string-length(.)>2][1]");

        // TicketNumbers
        $it['TicketNumbers'] = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t('Ticket number') . "']/following::text()[string-length(.)>2][1]");

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
        $xpath = "//text()[" . $this->eq($this->t("Departing from")) . "]/ancestor::tr[1]/following-sibling::tr[./td[4]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length === 0) {
            $this->logger->info('Segments not found by xpath: ' . $xpath);

            return [];
        }

        foreach ($nodes as $root) {
            $itsegment = [];
            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root, true, "#^\w{2}\s*(\d+)[A-Z]?$#"); //AF0719A

            // DepCode
            $itsegment['DepCode'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root, true, "#\(([A-Z]{3})\)#");

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root, true, "#(.*?)(?:\-?\s*{$this->opt($this->t('Terminal'))}\s+\w+)?\s+\([A-Z]{3}\)#u");

            // DepartureTerminal
            $itsegment['DepartureTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][2]", $root, false, "#{$this->opt($this->t('Terminal'))}\s+(\w+)#u");

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode("./td[normalize-space(.)!=''][3]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#\(([A-Z]{3})\)#");

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#(.*?)(?:\-?\s*{$this->opt($this->t('Terminal'))}\s+\w+)?\s+\([A-Z]{3}\)#u");

            // ArrivalTerminal
            $itsegment['ArrivalTerminal'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][4]", $root, true, "#{$this->opt($this->t('Terminal'))}\s+(\w+)#u");

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode("./td[normalize-space(.)!=''][1]", $root, true, "#^(\w{2})\s*\d+[A-Z]?$#");

            // Operator
            // Aircraft
            // TraveledMiles
            // AwardMiles
            // Cabin
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

        return true;
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
        $body = html_entity_decode($this->http->Response['body']);

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

        $pdfs = $parser->searchAttachmentByName(".*.pdf");

        if (isset($pdfs[0])) {
            $this->logger->debug("go to parsers with Pdf: YourBoardingPassPdf or YourBoardingPassPdf2");

            return null; // pdf parse in YourBoardingPassPdf
        }
        $this->http->FilterHTML = true;
        $this->http->SetBody($parser->getHTMLBody());
        $itineraries = [];

        $body = $parser->getHTMLBody();

        foreach ($this->reBody2 as $lang => $re) {
            if (stripos($body, $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BoardingDocumentsNonPdf' . ucfirst($this->lang),
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
            "#^(\d+)/(\d+)/(\d{4})\s+a\s*las\s+(\d+:\d+)$#", // 03/03/2015 a las 18:35 //es
            "#^(\d+)/(\d+)/(\d{4})\s+às\s+(\d+:\d+)$#", // 03/03/2015 a las 18:35 //зе
            "#^(\d+)/(\d+)/(\d{4})\s+(?:at|a\s*las)\s+(\d+:\d+)$#", // 03/03/2015 at 18:35 //en
            "#^(\d+)/(\d+)/(\d{4})\s+à\s+(\d+:\d+)$#", // 03/03/2015 à 18:35 //fr
            "#^(\d+)/(\d+)/(\d{4})[，]?\s+(\d+:\d+)$#u", // 14/05/2015 10:50 //ja, 06/01/2018， 13:35 //zh

            "#^(\d+)/(\d+)/(\d{4})\s+ore\s+(\d+:\d+)$#", // 03/03/2015 à 18:35 //it
        ];
        $out = [
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",
            "$1.$2.$3, $4",

            "$1.$2.$3, $4",
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
            '€' => 'EUR',
            '$' => 'USD',
            '£' => 'GBP',
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

    private function nextText($field, $root = null, $n = 1)
    {
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[string-length(.)>2][{$n}]", $root);
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

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) { return str_replace(' ', '\s+', preg_quote($s)); }, $field)) . ')';
    }
}
