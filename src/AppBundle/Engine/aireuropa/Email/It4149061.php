<?php

namespace AwardWallet\Engine\aireuropa\Email;

use AwardWallet\Engine\MonthTranslate;

class It4149061 extends \TAccountChecker
{
    public $mailFiles = "aireuropa/it-15473998.eml, aireuropa/it-15730807.eml, aireuropa/it-27554590.eml, aireuropa/it-27595906.eml, aireuropa/it-29818303.eml, aireuropa/it-29820513.eml, aireuropa/it-4097608.eml, aireuropa/it-4121019.eml, aireuropa/it-4149061.eml, aireuropa/it-4223785.eml";

    public $reSubject = [
        'es' => ['Confirmación de reserva', 'Confirmacin Reserva'],
        'pt' => ['Confirmação de reserva'],
        'it' => ['Conferma della prenotazione'],
        'fr' => ['Confirmation de réservation'],
        'en' => ['Booking details', 'Notification of change in your flight'],
    ];

    public $reBody2 = [
        'es'  => 'Llegada',
        'pt'  => 'Chegada',
        'it'  => 'Arrivo',
        'fr'  => 'Arrivée',
        'fr2' => 'Arrive',
        'en'  => 'Arrival',
    ];

    public static $dictionary = [
        "es" => [
            "Reservation code" => "Localizador",
            //			"booking reference" => "",
            "Passengers" => "Pasajeros",
            "Cabin"      => "Cabina",
            "Flight"     => "Vuelo",
            //			"Canceled" => "",
            "Operated by:" => "Operado por:",
        ],
        "pt" => [
            "Reservation code" => ["Cdigo de reserva", "Código de reserva"],
            //			"booking reference" => "",
            "Passengers" => "Passageiro",
            "Cabin"      => "Cabine",
            "Flight"     => "Voo",
            //			"Canceled" => "",
            "Operated by:" => "Operado pela:",
        ],
        "it" => [
            "Reservation code" => "Códice di prenotazione",
            //			"booking reference" => "",
            "Passengers" => "Passeggeri",
            "Cabin"      => "Cabina",
            "Flight"     => "Volo",
            //			"Canceled" => "",
            "Operated by:" => "Operato da:",
        ],
        "fr" => [
            "Reservation code" => ["Référence de dossier", "Rfrence de dossier"],
            //			"booking reference" => "",
            "Passengers" => "Passagers",
            "Cabin"      => "Classe",
            "Flight"     => "Vol",
            //			"Canceled" => "",
            "Operated by:" => "Opéré par:",
        ],
        "en" => [],
    ];

    public $lang = 'es';

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@air-europa.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'AirEuropa') === false && strpos($headers['subject'], 'Air Europa') === false) {
            return false;
        }

        foreach ($this->reSubject as $phrases) {
            foreach ($phrases as $phrase) {
                if (strpos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $condition1 = $this->http->XPath->query('//node()[contains(normalize-space(.),"Air Europa") or contains(.,"AirEuropa")]')->length === 0;
        $condition2 = $this->http->XPath->query('//a[contains(@href,"//www.aireuropa.com")] | //img[contains(@src,"aireuropa.com") or contains(normalize-space(@alt),"Air Europa")]')->length === 0;

        if ($condition1 && $condition2) {
            return false;
        }

        foreach ($this->reBody2 as $re) {
            if (strpos($parser->getHTMLBody(), $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->year = getdate(strtotime($parser->getHeader('date')))['year'];

        $this->http->FilterHTML = false;
        $this->http->SetEmailBody(str_replace(" ", " ", $this->http->Response['body'])); // bad fr char " :"

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response['body'], $re) !== false) {
                $this->lang = substr($lang, 0, 2);

                break;
            }
        }

        $itineraries = [];
        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'BookingFlight' . ucfirst($this->lang),
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

    private function parseHtml(&$itineraries)
    {
        $patterns = [
            'PNR' => '/^([A-Z\d]{5,})$/',
        ];

        $year = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Reservation code"))}]/ancestor::tr[1]/preceding::tr[normalize-space(.) and not(.//tr)][1]", null, true, '/^[,\w\s]{4,}(\d{4})$/');

        if ($year) {
            $this->year = $year;
        }

        $it = [];
        $it['Kind'] = 'T';

        // RecordLocator
        $it['RecordLocator'] = $this->http->FindSingleNode("//text()[{$this->eq($this->t("Reservation code"))}]/ancestor::tr[1]/following-sibling::tr[normalize-space(.)!=''][1]", null, true, $patterns['PNR']);

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//*/text()[{$this->contains($this->t('booking reference'))}]/following::text()[normalize-space(.)!=''][1]", null, true, $patterns['PNR']);
        }

        // Passengers
        // TicketNumbers
        $passengers = [];
        $ticketNumbers = [];
        $passengerRows = $this->http->XPath->query("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr[count(./td[normalize-space(.)])>1]");

        foreach ($passengerRows as $passengerRow) {
            if ($passenger = $this->http->FindSingleNode('./td[position()=1 and normalize-space(.)]', $passengerRow)) {
                $passengers[] = $passenger;
            }

            if ($ticketNumber = $this->http->FindSingleNode('./td[position()=2 and normalize-space(.)]', $passengerRow, true, '/^([-\d\s]+)$/')) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (empty($passengers[0])) {
            $passengerTexts = $this->http->FindNodes("//text()[normalize-space(.)='" . $this->t("Passengers") . "']/ancestor::tr[1]/following-sibling::tr/td[1]/descendant::text()[normalize-space(.)]");
            $passengerValues = array_values(array_filter($passengerTexts));

            if (!empty($passengerValues[0])) {
                $passengers = $passengerValues;
            }
        }

        if (!empty($passengers[0])) {
            $it['Passengers'] = array_values(array_unique($passengers));
        }

        if (!empty($ticketNumbers[0])) {
            $it['TicketNumbers'] = array_values(array_unique($ticketNumbers));
        }

        $xpathFragment1 = 'normalize-space(.) and not(./ancestor::strike)';

        // TripSegments
        $it['TripSegments'] = [];
        $xpath = "//text()[" . $this->eq($this->t("Flight")) . "]/ancestor::tr[1]/following-sibling::tr[string-length(normalize-space(.))>8 and ./descendant::text()[$xpathFragment1] and not(./td[1][contains(.,'" . $this->t("Canceled") . "')])]";
        $segments = $this->http->XPath->query($xpath);

        if ($segments->length === 0) {
            $this->logger->alert("segments root not found: " . $xpath);
        }

        foreach ($segments as $root) {
            $itsegment = [];

            // Cabin
            if ($this->http->XPath->query("./preceding-sibling::tr/td[position()=1 and ./descendant::text()[normalize-space(.)='" . $this->t("Cabin") . "']]", $root)->length > 0) {
                $itsegment['Cabin'] = $this->http->FindSingleNode('./td[1]', $root);
            }

            // FlightNumber
            // AirlineName
            $flight = $this->http->FindSingleNode('./td[2]/descendant::text()[' . $xpathFragment1 . '][1]', $root);

            if (preg_match('/^([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d+)$/', $flight, $matches)) {
                $itsegment['FlightNumber'] = $matches[2];
                $itsegment['AirlineName'] = $matches[1];
            }

            // Operator
            $itsegment['Operator'] = $this->http->FindSingleNode("./descendant::text()[" . $this->contains($this->t("Operated by:")) . " and not(./ancestor::strike)]", $root, true, '/' . $this->opt($this->t('Operated by:')) . '\s*(.+)/');

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode('./td[3]/descendant::text()[' . $xpathFragment1 . '][1]', $root);

            // DepartureTerminal
            $terminalDepTexts = $this->http->FindNodes('./td[3]/descendant::text()[' . $xpathFragment1 . ']', $root, '/\(T-([A-Z\d]+)\)/i');
            $terminalDepTexts = array_values(array_filter($terminalDepTexts));

            if (count($terminalDepTexts) === 1) {
                $itsegment['DepartureTerminal'] = $terminalDepTexts[0];
            }

            // DepDate
            $dateDepTexts = $this->http->FindNodes('./td[4]/descendant::text()[' . $xpathFragment1 . ']', $root);
            $dateDep = implode(' ', $dateDepTexts);

            if ($dateDep) {
                $itsegment['DepDate'] = strtotime($this->normalizeDate($dateDep));
            }

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode('./td[5]/descendant::text()[' . $xpathFragment1 . '][1]', $root);

            // ArrivalTerminal
            $terminalArrTexts = $this->http->FindNodes('./td[5]/descendant::text()[' . $xpathFragment1 . ']', $root, '/\(T-([A-Z\d]+)\)/i');
            $terminalArrTexts = array_values(array_filter($terminalArrTexts));

            if (count($terminalArrTexts) === 1) {
                $itsegment['ArrivalTerminal'] = $terminalArrTexts[0];
            }

            // ArrDate
            $dateArrTexts = $this->http->FindNodes('./td[6]/descendant::text()[' . $xpathFragment1 . ']', $root);
            $dateArr = implode(' ', $dateArrTexts);

            if ($dateArr) {
                $itsegment['ArrDate'] = strtotime($this->normalizeDate($dateArr));
            }

            // DepCode
            // ArrCode
            if (!empty($itsegment['DepName']) && !empty($itsegment['ArrName']) && !empty($itsegment['DepDate']) && !empty($itsegment['ArrDate'])) {
                $itsegment['ArrCode'] = $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;
            }

            $it['TripSegments'][] = $itsegment;
        }
        $itineraries[] = $it;
    }

    private function normalizeDate($string)
    {
        if (preg_match('/(\d{1,2})\s*([^\d\s]{3,})\s*(\d{1,2}:\d{2})$/', $string, $matches)) { // 14Jun 07:40
            $day = $matches[1];
            $month = $matches[2];
            $year = $this->year;
            $time = $matches[3];
        }

        if ($day && $month && $year && $time) {
            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ' ' . $year . ', ' . $time;
        }

        return false;
    }

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }

    private function eq($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'normalize-space(' . $node . ')="' . $s . '"'; }, $field)) . ')';
    }

    private function contains($field, $node = '.'): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) { return 'contains(normalize-space(' . $node . '),"' . $s . '")'; }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) { return preg_quote($s, '/'); }, $field)) . ')';
    }
}
