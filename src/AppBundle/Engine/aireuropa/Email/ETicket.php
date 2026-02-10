<?php

namespace AwardWallet\Engine\aireuropa\Email;

class ETicket extends \TAccountCheckerExtended
{
    public $mailFiles = "aireuropa/it-29984896.eml, aireuropa/it-3285047.eml, aireuropa/it-3298709.eml, aireuropa/it-3394459.eml, aireuropa/it-3599198.eml, aireuropa/it-3813575.eml, aireuropa/it-3853290.eml, aireuropa/it-4070248.eml, aireuropa/it-4116885.eml, aireuropa/it-4118431.eml, aireuropa/it-4132236.eml, aireuropa/it-4137746.eml, aireuropa/it-5445317.eml, aireuropa/it-5459425.eml, aireuropa/it-5462723.eml";
    public $reBody = 'Air Europa';
    public $reBody2 = [
        "fr" => "Merci de voyager avec Air Europa",
        "es" => "Gracias por volar con AirEuropa",
        "de" => "Vielen Dank, dass Sie sich für einen Flug mit AirEuropa",
        "pt" => "Obrigado por voar com a AirEuropa",
        "nl" => "Bedankt voor het kiezen van Air Europa voor uw reis",
        "it" => "Grazie per aver scelto di volare con AirEuropa",
        "en" => "Thank you for flying Air Europa",
    ];

    public static $dictionary = [
        "fr" => [
            "Booking locator" => "Code de réservation",
            //			"TICKET NUMBER" => "",
            //			"RESERVED SEATS" => "",
            //			"Seat" => "",
            "Adult base fare" => "Prix de base adulte",
            "TOTAL AMOUNT"    => "MONTANT TOTAL",
            "Taxes"           => "Taxes",
            "OUTBOUND"        => "ALLER LE",
            "INBOUND"         => "RETOUR LE",
            "Operated by"     => "Opéré par",
            //            "Cabin" => "",
        ],
        "es" => [
            "Booking locator" => "Localizador",
            "TICKET NUMBER"   => "NÚMERO BILLETE",
            "RESERVED SEATS"  => "ASIENTOS RESERVADOS",
            "Seat"            => "Asiento",
            "Adult base fare" => "Tarifa base adulto",
            "TOTAL AMOUNT"    => "TOTAL IMPORTE",
            "Taxes"           => "Tasas",
            "OUTBOUND"        => "IDA",
            "INBOUND"         => "VUELTA",
            "Operated by"     => "Operado por",
            //            "Cabin" => "",
        ],
        "de" => [
            "Booking locator" => "Buchungsnummer",
            //			"TICKET NUMBER" => "",
            "RESERVED SEATS"  => "RESERVIERTE SITZPLÄTZE",
            "Seat"            => "Sitzplatz",
            "Adult base fare" => "Grundtarif Erwachsene",
            "TOTAL AMOUNT"    => "GESAMTBETRAG",
            "Taxes"           => "Gebühren",
            "OUTBOUND"        => "HINFLUG",
            "INBOUND"         => "RÜCKFLUG",
            //            "Operated by" => "",
            "Cabin" => "Klasse",
        ],
        "pt" => [
            "Booking locator" => "Localizador",
            //			"TICKET NUMBER" => "",
            //			"RESERVED SEATS" => "",
            //			"Seat" => "",
            "Adult base fare" => "Tarifa base adulto",
            "TOTAL AMOUNT"    => "MONTANTE TOTAL",
            "Taxes"           => "Taxas",
            "OUTBOUND"        => "IDA",
            "INBOUND"         => "VOLTA",
            //            "Operated by" => "",
            //            "Cabin" => "",
        ],
        "nl" => [
            "Booking locator" => "RESERVERINGSNUMMER",
            //			"TICKET NUMBER" => "",
            "RESERVED SEATS"  => "GERESERVEERDE ZITPLAATSEN",
            "Seat"            => "Zitplaats",
            "Adult base fare" => "Basistarief volwassene",
            "TOTAL AMOUNT"    => "TOTAALBEDRAG",
            "Taxes"           => "Luchthavenbelastingen/Toeslagen",
            "OUTBOUND"        => "HEENREIS",
            "INBOUND"         => "TERUGREIS",
            //            "Operated by" => "",
            //            "Cabin" => "",
        ],
        "it" => [
            "Booking locator" => "Numero identificativo",
            //			"TICKET NUMBER" => "",
            //			"RESERVED SEATS" => "",
            //			"Seat" => "",
            "Adult base fare" => "Tariffa di base adulto",
            "TOTAL AMOUNT"    => "IMPORTO TOTALE",
            "Taxes"           => "Tasse",
            "OUTBOUND"        => "ANDATA",
            "INBOUND"         => "RITORNO",
            //            "Operated by" => "",
            //            "Cabin" => "",
        ],
        "en" => [
            "Cabin" => ["Cabin", "cabin"],
        ],
    ];

    public $lang = "en";

    public function __construct()
    {
        parent::__construct();

        $this->processors = [
            "html" => function (&$itineraries) {
                $it = [];
                $it['Kind'] = "T";

                // RecordLocator
                $it['RecordLocator'] = $this->getField($this->t("Booking locator"), "preceding");

                if (empty($it['RecordLocator'])) {
                    $upper = mb_strtoupper($this->t("OUTBOUND"));
                    $lower = mb_strtolower($this->t("OUTBOUND"));
                    $rule = "translate(normalize-space(.), \"{$upper}\", \"{$lower}\")=\"{$lower}\"";
                    $it['RecordLocator'] = $this->http->FindSingleNode("//a[contains(@href,'?locator=') and ./following::text()[string-length(normalize-space())>2][2][{$rule}]]/preceding::text()[normalize-space()!=''][1]");
                }

                // Passengers
                $it['Passengers'] = $this->http->FindNodes("//img[contains(@src, 'icon_passenger_big.pn')]/preceding::text()[normalize-space(.)][1]");

                // TicketNumbers
                $ticketNumbers = $this->http->FindNodes('//td[' . $this->eq($this->t('TICKET NUMBER')) . ']/following-sibling::td[normalize-space(.)][1]', null, '/^(\d[-\d]{3,}\d)$/');
                $ticketNumberValues = array_values(array_filter($ticketNumbers));

                if (!empty($ticketNumberValues[0])) {
                    $it['TicketNumbers'] = array_unique($ticketNumberValues);
                }

                // TotalCharge
                $totalAmount = $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL AMOUNT'))}]/following::text()[normalize-space()][1]")
                    . $this->http->FindSingleNode("//text()[{$this->eq($this->t('TOTAL AMOUNT'))}]/following::text()[normalize-space()][2]");

                if ($totalAmount !== '') {
                    $it['TotalCharge'] = cost($totalAmount);
                }

                // BaseFare
                $baseFare = $this->getField($this->t("Adult base fare"), 'following', false);

                if ($baseFare !== null) {
                    $it['BaseFare'] = cost($baseFare);
                }

                // Currency
                $it['Currency'] = $this->http->FindSingleNode("//text()[normalize-space(.)='" . $this->t("TOTAL AMOUNT") . "']/following::text()[normalize-space(.)][3]");

                // Tax
                $taxes = $this->getField($this->t("Taxes"));

                if ($taxes !== null) {
                    $it['Tax'] = cost($taxes);
                }

                $xpath = "//img[contains(@src, 'icon_info.png')]/ancestor::tr[1]/following-sibling::tr[1]/td[2]//tr[./td[normalize-space(.)][2]]";
                $segments = $this->http->XPath->query($xpath);

                if ($segments->length === 0) {
                    $xpath = "//img[contains(@src, 'icon_info.png')]/ancestor::tr[1]";
                    $segments = $this->http->XPath->query($xpath);
                }

                foreach ($segments as $root) {
                    $date = strtotime($this->normalizeDate($this->http->FindSingleNode("./preceding::img[contains(@src, 'icon_plane_right_blue.png') or contains(@src, 'icon_plane_left_blue.png')][1]/preceding::text()[string-length(normalize-space(.))>3][1]", $root, true, "#(?:" . $this->t("INBOUND") . "|" . $this->t("OUTBOUND") . ")\s+(.+)#")));

                    if (isset($this->lastDate) && $date < $this->lastDate) {
                        $date = $this->lastDate;
                    }

                    $itsegment = [];

                    $xpathFragment1 = $this->contains(['0072ce', '0072CE', '00beff', '00BEFF'], '@style');

                    // AirlineName
                    // FlightNumber
                    $flightInfo = implode("\n", $this->http->FindNodes('descendant::text()[normalize-space()]', $root));

                    if (preg_match("/\d+:\d+\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<number>\d+)/", $flightInfo, $m)) {
                        $itsegment['AirlineName'] = $m['name'];
                        $itsegment['FlightNumber'] = $m['number'];
                    }

                    if (empty($itsegment['FlightNumber'])
                        && ($flight = $this->http->FindSingleNode("descendant::span[$xpathFragment1][1]", $root))
                        && preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<number>\d+)$/", $flight, $m)
                    ) {
                        // it-3394459.eml
                        $itsegment['AirlineName'] = $m['name'];
                        $itsegment['FlightNumber'] = $m['number'];
                    }

                    if (empty($itsegment['FlightNumber'])
                        && preg_match("/\s+(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+(?<number>\d+)/", $flightInfo, $m)
                    ) {
                        $itsegment['AirlineName'] = $m['name'];
                        $itsegment['FlightNumber'] = $m['number'];
                    }

                    // DepCode
                    $itsegment['DepCode'] = orval(
                        $this->http->FindSingleNode("./descendant::b[1]", $root),
                        $this->http->FindSingleNode("./..//img[contains(@src, 'icon_take_off.png')]/ancestor::tr[1]", $root, true, "#\(([A-Z]{3})\)#")
                    );

                    // DepDate
                    $itsegment['DepDate'] = strtotime(orval(
                        $this->http->FindSingleNode("./descendant::b[1]/following::text()[string-length(normalize-space(.))>1][1]", $root, true, "#\d+:\d+#"),
                        $this->http->FindSingleNode("./..//img[contains(@src, 'icon_take_off.png')]/ancestor::tr[1]/td[3]", $root, true, "#\d+:\d+#")
                    ), $date);

                    // ArrCode
                    $itsegment['ArrCode'] = orval(
                        $this->http->FindSingleNode("./descendant::b[2]", $root),
                        $this->http->FindSingleNode("./..//img[contains(@src, 'icon_landing.png')]/ancestor::tr[1]", $root, true, "#\(([A-Z]{3})\)#"),
                        $this->http->FindSingleNode("./..//img[contains(@src, 'icon_landing.png')]/ancestor::tr[1]", $root, true, "#\s+([A-Z]{3})\s#")
                    );

                    // ArrDate
                    $itsegment['ArrDate'] = strtotime(orval(
                        $this->http->FindSingleNode("./descendant::b[2]/following::text()[string-length(normalize-space(.))>1][1]", $root, true, "#\d+:\d+#"),
                        $this->http->FindSingleNode("./..//img[contains(@src, 'icon_landing.png')]/ancestor::tr[1]/td[3]", $root, true, "#\d+:\d+#")
                    ), $date);
                    $this->lastDate = $itsegment['ArrDate'];

                    // Aircraft
                    $itsegment['Aircraft'] = orval(
                        $this->http->FindSingleNode("td[count(following-sibling::td)<3][1]/descendant::span[$xpathFragment1][1]/following::text()[normalize-space()][1]", $root),
                        $this->http->FindSingleNode(".", $root, true, "/(?:[A-Z][A-Z\d]|[A-Z\d][A-Z])\s+\d+\s+([A-Z\d]+)/")
                    );

                    // Cabin
                    $cabin = $this->http->FindSingleNode("td[contains(@class,'cabin') or {$this->contains($this->t('Cabin'))}]", $root, true, '/^[[:alpha:]].*[[:alpha:]]$/u');

                    if ($cabin) {
                        $itsegment['Cabin'] = $cabin;
                    }

                    // Duration
                    $itsegment['Duration'] = $this->http->FindSingleNode("./td[last()]", $root, true, '/^(\d.+)$/s');

                    // Operator
                    $operator = $this->http->FindSingleNode('./descendant::text()[' . $this->contains($this->t('Operated by')) . ']', $root, true, '/' . $this->opt($this->t('Operated by')) . '\s+(.+)/');

                    if ($operator) {
                        $itsegment['Operator'] = $operator;
                    }

                    // Seats
                    if (!empty($itsegment['DepCode']) && !empty($itsegment['ArrCode']) && !empty($itsegment['AirlineName']) && !empty($itsegment['FlightNumber'])) {
                        $seats = $this->http->FindNodes('//td[' . $this->eq($this->t('RESERVED SEATS')) . ']/following-sibling::td[normalize-space(.)][1]/descendant::text()[' . $this->starts($this->t('Seat')) . ']/ancestor::*[1]', null, '/^' . $this->opt($this->t('Seat')) . '\s+(\d{1,3}[A-Z]).*' . $itsegment['DepCode'] . '.+' . $itsegment['ArrCode'] . '.+' . $itsegment['AirlineName'] . '[0\s]*' . $itsegment['FlightNumber'] . '$/');
                        $seatValues = array_values(array_filter($seats));

                        if (!empty($seatValues[0])) {
                            $itsegment['Seats'] = $seatValues;
                        }
                    }

                    $it['TripSegments'][] = $itsegment;
                }

                $itineraries[] = $it;
            },
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];

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
        $this->year = date("Y", $this->date);

        $this->http->FilterHTML = false;
        $itineraries = [];

        $body = $parser->getHTMLBody();

        if (empty($body)) {
            $body = $parser->getPlainBody();
            $this->http->SetEmailBody($body);
        }

        foreach ($this->reBody2 as $lang => $re) {
            if (strpos($this->http->Response["body"], $re) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $processor = $this->processors["html"];
        $processor($itineraries);

        $result = [
            'emailType'  => 'Flight' . ucfirst($this->lang),
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

    private function getField($field, $direction = 'following', $equal = true, $num = 1)
    {
        $upper = mb_strtoupper($field);
        $lower = mb_strtolower($field);

        if ($equal) {
            return $this->http->FindSingleNode("//text()[translate(normalize-space(.), \"{$upper}\", \"{$lower}\")=\"{$lower}\"]/{$direction}::text()[normalize-space(.)!=''][{$num}]");
        }

        return $this->http->FindSingleNode("//text()[contains(translate(normalize-space(.), \"{$upper}\", \"{$lower}\"),\"{$lower}\")]/{$direction}::text()[normalize-space(.)!=''][{$num}]");
    }

    private function normalizeDate($str)
    {
        $year = $this->year;

        $in = [
            "#^\w+\s+(\d+)\s+OF\s+(\w+)$#",
            "#^[^\s\d]+\s+(\d+)\s+jj\s+([^\s\d]+)$#",
            "#^[^\d\s]+\s+(\d+)\s+DE\s+([^\d\s]+)$#",
            "#^\s*\w+\s+(\d+)\s+(\w+)\s*$#",
        ];
        $out = [
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$1 $2 {$year}",
            "$1 $2 {$year}",
        ];
        $date = en(preg_replace($in, $out, $str));

        if (strtotime($date) < $this->date && ($this->date - strtotime($date)) < 365 * 2 * 24 * 60 * 60) {
            $this->year++;
            $date = $this->normalizeDate($str);
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

    private function contains($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'contains(normalize-space(' . $node . '),"' . $s . '")';
        }, $field)) . ')';
    }

    private function eq($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'normalize-space(' . $node . ')="' . $s . '"';
        }, $field)) . ')';
    }

    private function starts($field, $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            return 'starts-with(normalize-space(' . $node . '),"' . $s . '")';
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
