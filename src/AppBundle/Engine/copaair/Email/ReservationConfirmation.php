<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "copaair/it-2188667.eml, copaair/it-2188668.eml, copaair/it-2188684.eml, copaair/it-3016662.eml, copaair/it-3231001.eml, copaair/it-5005143.eml, copaair/it-6614381.eml, copaair/it-9979986.eml";
    public $reFrom = "@copaair.com";
    public $reSubject = [
        "en"=> "Reservation Confirmation",
    ];
    public $reBody = 'Copa Airlines';
    public $reBody2 = [
        "en"=> "Air Itinerary Details",
        "es"=> "Detalles de su Itinerario Aéreo",
        "pt"=> "Detalhes de seu Itinerário Aéreo",
    ];

    public static $dictionary = [
        "en" => [
            "Ticket Number"=> "NOTTRANSLATED",
        ],
        "es" => [
            "Confirmation Number:"=> "Número de Confirmación:",
            "Passengers"          => "Pasajeros",
            "Total Air Fare:"     => "Costo total de reservación:",
            "Departs"             => "Salida",
            "Fare Family:"        => ["Tarifa:", "Clase:"],
            "Equipment:"          => "Equipo:",
            "Ticket Number"       => "Número de boleto",
            "Seats"               => "Asientos",
            "miles"               => "millas",
            "Operated by"         => "Operado por",
        ],
        "pt" => [
            "Confirmation Number:"=> "Número de Confirmação:",
            "Passengers"          => "Passageiros",
            "Total Air Fare:"     => "Total da tarifa aérea:",
            "Departs"             => "Embarque",
            "Fare Family:"        => ["Classe:", "Tarifa:"],
            "Equipment:"          => "Equipamento:",
            "Ticket Number"       => ["Ticket Number", "Número da passagem aérea"],
            "Seats"               => "Assentos",
            "miles"               => "milhas",
            //			"Operated by"=>"",
        ],
    ];

    public $lang = "en";

    public function parseHtml(&$itineraries)
    {
        $it = [];
        $it['Kind'] = "T";

        // RecordLocator
        $it['RecordLocator'] = $this->nextText($this->t("Confirmation Number:"));

        // Passengers
        $it['Passengers'] = $this->http->FindNodes("//text()[{$this->eq($this->t("Passengers"))}]/following::table[1]//tr[ count(./td[string-length(normalize-space(.))>2])>2 or count(./td[string-length(normalize-space(.))>2])=1]/td[string-length(normalize-space(.))>2][1][not({$this->contains($this->t('Seats'))})]");

        // TicketNumbers
        $ticketNumbers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t("Ticket Number"))}]/following::text()[normalize-space()][1]", null, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/'));

        if (count($ticketNumbers)) {
            $it['TicketNumbers'] = $ticketNumbers;
        }

        // TotalCharge
        // Currency
        // SpentAwards
        $tot = $this->nextText($this->t("Total Air Fare:"));

        if (preg_match("/(.+?)\s+{$this->opt($this->t('miles'))}\s+\+\s+(.+)/i", $tot, $m)) {
            $awardCurrency = (array) $this->t('miles');
            $it['SpentAwards'] = $this->amount($m[1]) . ' ' . array_shift($awardCurrency);
            $it['TotalCharge'] = $this->amount($m[2]);
            $it['Currency'] = $this->currency($m[2]);
        } else {
            $it['TotalCharge'] = $this->amount($tot);
            $it['Currency'] = $this->currency($tot);
        }

        $seats = [];
        $paxSeats = $this->http->FindNodes("//text()[{$this->eq($this->t('Seats'))}]/ancestor::td[1]/following-sibling::td[1]");

        foreach ($paxSeats as $paxSeat) {
            $seat = preg_split('/\s*,\s*/', $paxSeat);

            foreach ($seat as $i => $s) {
                $seats[$i][] = $s;
            }
        }

        $xpath = "//text()[" . $this->eq($this->t("Departs")) . "]/ancestor::tr[1]/following-sibling::tr[./td[5]]";
        $nodes = $this->http->XPath->query($xpath);

        if ($nodes->length == 0) {
            $this->logger->debug("segments root not found: " . $xpath);
        }

        foreach ($nodes as $i=>$root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./../tr[1]/*[2]", $root)));

            $itsegment = [];

            // AirlineName
            // FlightNumber
            $flight = $this->http->FindSingleNode('td[1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
                $itsegment['AirlineName'] = $m['name'];
                $itsegment['FlightNumber'] = $m['number'];
            }

            // DepCode
            $itsegment['DepCode'] = TRIP_CODE_UNKNOWN;

            // DepName
            $itsegment['DepName'] = $this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][2]", $root);

            // DepDate
            $itsegment['DepDate'] = strtotime($this->http->FindSingleNode("./td[2]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // ArrCode
            $itsegment['ArrCode'] = TRIP_CODE_UNKNOWN;

            // ArrName
            $itsegment['ArrName'] = $this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][2]", $root);

            // ArrDate
            $itsegment['ArrDate'] = strtotime($this->http->FindSingleNode("./td[3]/descendant::text()[normalize-space(.)][1]", $root), $date);

            // Aircraft
            $itsegment['Aircraft'] = $this->nextText($this->t("Equipment:"));

            // Cabin
            if (!$itsegment['Cabin'] = $this->re("#(.*?)\s+\(\w\)#u", $this->nextText($this->t("Fare Family:")))) {
                $itsegment['Cabin'] = $this->nextText($this->t("Fare Family:"));
            }

            // BookingClass
            $itsegment['BookingClass'] = $this->re("#\((\w)\)#", $this->nextText($this->t("Fare Family:")));

            // Seats
            if (isset($seats[$i])) {
                $itsegment['Seats'] = array_filter($seats[$i], function ($s) {
                    return preg_match("#^\d+[A-z]$#", $s);
                });
            }
            // Duration
            $itsegment['Duration'] = $this->http->FindSingleNode("./td[4]", $root);

            // Operator
            $operator = $this->http->FindSingleNode("following-sibling::tr[normalize-space()][1]/descendant::text()[{$this->starts($this->t('Operated by'))}]", $root, true, "/^{$this->opt($this->t('Operated by'))}\s*(.+)$/");

            if ($operator) {
                $itsegment['Operator'] = $operator;
            }

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
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($itineraries);

        $result = [
            'emailType'  => 'ReservationConfirmation' . ucfirst($this->lang),
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
        $rule = $this->eq($field);

        return $this->http->FindSingleNode("(.//text()[{$rule}])[1]/following::text()[normalize-space(.)][{$n}]", $root);
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
        //		$year = date("Y", $this->date);
        $in = [
            // Seg, 03 de Setembro de 2018
            "#^[^\d\s]+,\s+(\d+)\s+de\s+([^\d\s]+)\s+de\s+(\d{4})$#",
            //Sun, 26 Apr 2015
            "#^[^\d\s]+,\s+(\d+)\s+([^\d\s]+)\s+(\d{4})$#",
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
        return $this->re("#^([A-Z]{3})\s#", $s);
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return '';
        }

        return '(?:' . implode('|', array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }
}
