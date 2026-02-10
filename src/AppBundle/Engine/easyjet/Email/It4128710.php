<?php

namespace AwardWallet\Engine\easyjet\Email;

class It4128710 extends \TAccountChecker
{
    public $mailFiles = "easyjet/it-10022229.eml, easyjet/it-10079711.eml, easyjet/it-10130185.eml, easyjet/it-10170312.eml, easyjet/it-4028423.eml, easyjet/it-4128710.eml, easyjet/it-8465242.eml, easyjet/it-9789404.eml, easyjet/it-9800059.eml";

    public $reFrom = "bookings@email.easyjet.com";
    public $reSubject = [
        "de" => "Ihre Buchung",
        "es" => "Tu reserva",
        "it" => "La tua prenotazione",
        "pt" => "A tua reserva",
        "pl" => "Twoja rezerwacja",
        "el" => "Η κράτησή σας",
        "da" => "Din booking",
    ];
    public $reBody = 'easyJet';
    public $reBody2 = [
        "de"  => "IHRE FLUGDATEN",
        "es"  => "DETALLES DE TU VUELO",
        "it"  => "I DETTAGLI DEL TUO VOLO",
        "pt"  => "AS TUAS INFORMAÇÕES DE VOO",
        "pt2" => "AS SUAS INFORMAÇÕES DE VOO",
        "pl"  => "ZCZEGÓŁY DOTYCZĄCE LOTU",
        "el"  => "ΛΕΠΤΟΜΕΡΕΙΕΣ ΠΤΗΣΗΣ",
        "da"  => "DINE FLYDETALJER",
        'nl'  => 'UW VLUCHTGEGEVENS',
    ];

    public static $dictionary = [
        "de" => [],
        "es" => [
            "Flug"   => "Vuelo",
            "nach"   => "hacia",
            "Abflug" => "Salida el",
        ],
        "it" => [
            "Flug"   => "Volo",
            "nach"   => "a",
            "Abflug" => "Partenza",
        ],
        "pt" => [
            "Flug"   => "Voo",
            "nach"   => "para",
            "Abflug" => "Partida",
        ],
        "pl" => [
            "Flug"   => "Nr lotu:",
            "nach"   => "-",
            "Abflug" => "Wylot:",
        ],
        "el" => [
            "Flug"   => "Πτήση",
            "nach"   => "προς",
            "Abflug" => "Αναχώρηση",
        ],
        "da" => [
            "Flug"   => "Fly",
            "nach"   => "til",
            "Abflug" => "Afgang",
        ],
        "nl" => [
            "Flug"   => "Vlucht",
            "nach"   => "naar",
            "Abflug" => "Vertrek",
        ],
    ];

    public $lang = "de";

    public function parseHtml(&$itineraries)
    {
        $it = [];

        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = CONFNO_UNKNOWN;

        $xpath = "//img[contains(@src, 'plane_blue_right.gif') or contains(@src, 'plane_blue_left.gif')]/ancestor::tr[1]/td[last()]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->http->Log("segments root not found: $xpath", LOG_LEVEL_NORMAL);
        }

        foreach ($nodes as $root) {
            $itsegment = [];

            // FlightNumber
            $itsegment['FlightNumber'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Flug") . "']/following::text()[normalize-space(.)][1]", $root, true, "#^\w{3}(\d+)$#");

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("nach") . "']/preceding::text()[normalize-space(.)][1]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->normalizeDate($this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Abflug") . "']/following::text()[normalize-space(.)][1]", $root) . ', ' . $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Abflug") . "']/following::text()[contains(., ':')][1]", $root)));

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("nach") . "']/following::text()[normalize-space(.)][1]", $root);

            // ArrDate
            $itsegment['ArrDate'] = MISSING_DATE;

            // AirlineName
            $itsegment['AirlineName'] = $this->http->FindSingleNode(".//text()[normalize-space(.)='" . $this->t("Flug") . "']/following::text()[normalize-space(.)][1]", $root, true, "#^(\w{3})\d+$#");

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

        foreach ($this->reBody2 as $lang=>$re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

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

            if ($translatedMonthName = \AwardWallet\Engine\MonthTranslate::translate($monthNameOriginal, $this->lang)) {
                return preg_replace("#$monthNameOriginal#i", $translatedMonthName, $date);
            }
        }

        return $date;
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
        //$year = date("Y", $this->date);
        $in = [
            "#^(\d+)-(\d+)-(\d{4}),\s+(\d+:\d+)$#",
        ];
        $out = [
            "$1.$2.$3, $4",
        ];
        $str = $this->dateStringToEnglish(preg_replace($in, $out, $str));

        return $str;
    }
}
