<?php

namespace AwardWallet\Engine\colombia\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class It5942067 extends \TAccountChecker
{
    public $mailFiles = "colombia/it-5942067.eml, colombia/it-73274227.eml, colombia/it-73655220.eml, colombia/it-158737909.eml";

    public $reFrom = [
        "@vivacolombia.co",
        "@vivaair.com",
    ];

    public $reSubject = [
        "en" => "Passenger receipt",
        "es" => "Confirmación de reserva",
    ];

    public $reBody = [
        'VivaColombia',
        '.vivaair.com',
    ];

    public $reBody2 = [
        "en" => ["DEPARTURE", "ITINERARY INFORMATION"],
        "es" => ["SALIDA", 'Salida'],
    ];

    public $lang = "es";
    public static $dictionary = [
        "es" => [
            "Localizador"         => ["Localizador", "Código de reserva"],
            "Núm tiquete"         => ["Núm tiquete", "Número pasaje", "Número de pasaje"],
            "Nombre del pasajero" => ["Nombre del pasajero", "Nombre pasajero"],
            "SALIDA"              => ["SALIDA", "Salida"],
            //        'INFORMACIÓN DE ITINERARIO' => '',
            'TOTAL CARGOS/TARIFA/IMPUESTOS' => ['TOTAL CARGOS/TARIFA/IMPUESTOS', 'Total cargos/tarifa/impuestos'],
            'notTaxes'                      => ['TOTAL SERVICIOS/IMPUESTOS', 'Total servicios/impuestos'],
            "OPERADO POR"                   => ["OPERADO POR", "Operado por"],
        ],
        "en" => [
            "Localizador"         => ["Record locator", "Booking reference"],
            "Nombre del pasajero" => "Passenger name",
            "Núm tiquete"         => "Ticket number",
            "TOTAL RESERVA"       => "TOTAL BOOKING",
            "SALIDA"              => ["DEPARTURE", "Departure"],
            "OPERADO POR"         => ["Operated by", "OPERATED BY"],
            //			'tiempo de espera',
            'Referencia'                    => 'Reference',
            'INFORMACIÓN DE ITINERARIO'     => 'ITINERARY INFORMATION',
            'TOTAL CARGOS/TARIFA/IMPUESTOS' => ['Total charges/fare/taxes', 'TOTAL CHARGES/FARE/TAXES'],
            'notTaxes'                      => ['Total services/taxes', 'TOTAL SURCHARGES/TAXES', 'TOTAL SERVICES/TAXES'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        foreach ($this->reFrom as $re) {
            if (stripos($from, $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        $head = false;

        foreach ($this->reFrom as $re) {
            if (stripos($headers["from"], $re) !== false) {
                $head = true;

                break;
            }
        }

        if ($head === false) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        $finded = false;

        foreach ($this->reBody as $re) {
            if (stripos($body, $re) !== false || $this->http->XPath->query("//a[contains(@href,'.vivaair.com/') or contains(@href,'//vivaair.com') or contains(@href,'www.vivaair.com')]")->length > 0) {
                $finded = true;

                break;
            }
        }

        if ($finded === false) {
            return false;
        }

        foreach ($this->reBody2 as $reBody) {
            foreach ($reBody as $rb) {
                if (strpos($body, $rb) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['SALIDA']) && $this->striposAll($this->http->Response["body"], $dict['SALIDA']) !== false) {
                $this->lang = $lang;

                break;
            }
        }

        $this->parseHtml($email);

        $email->setType('It5942067' . ucfirst($this->lang));

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
    }

    private function parseHtml(Email $email): void
    {
        $patterns = [
            'eTicket' => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $xpath = "//text()[" . $this->eq($this->t("INFORMACIÓN DE ITINERARIO")) . "]/ancestor::*[" . $this->contains($this->t("TOTAL RESERVA")) . "][1]";
        $reservations = $this->http->XPath->query($xpath);

        foreach ($reservations as $res) {
            $f = $email->add()->flight();

            // General
            $confirmation = $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t("Localizador"))}] ]/following::tr[normalize-space()][1]/*[1]", $res, true, '/^\s*([A-Z\d]{5,7})\s*$/')
                ?? $this->http->FindSingleNode("descendant::tr[ *[2][{$this->eq($this->t("Localizador"))}] ]/following::tr[normalize-space()][1]/*[2]", $res, true, '/^\s*([A-Z\d]{5,7})\s*$/')
                ?? $this->http->FindSingleNode("descendant::tr[ *[1][{$this->eq($this->t("Núm tiquete"))}] ]/following::tr[normalize-space()][1]/*[1]", $res, true, '/^\s*([A-Z\d]{5,7})\s*$/')
            ;

            $f->general()
                ->confirmation($confirmation, $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t("Localizador"))}]", $res))
                ->confirmation($this->http->FindSingleNode("descendant::tr[ *[3][{$this->eq($this->t("Referencia"))}] ]/following::tr[normalize-space()][1]/*[3]", $res, true, '/^\s*(\d{5,})\s*$/'), $this->http->FindSingleNode("descendant::tr/*[{$this->eq($this->t("Referencia"))}]", $res))
                ->traveller(implode(' ', $this->http->FindNodes("(.//text()[" . $this->eq($this->t("Nombre del pasajero")) . "])[1]/ancestor::tr[1]/following::tr[1]/td[1][normalize-space()]//text()[normalize-space()]", $res)));

            // Issued
            $tickets = array_filter($this->http->FindNodes("descendant::tr[ *[1][{$this->eq($this->t("Núm tiquete"))}] ]/following::tr[normalize-space()][1]/*[1]", $res, "/^\s*(?:\w*-)?\s*({$patterns['eTicket']})\s*$/"));

            if (count($tickets) === 0) {
                $tickets = array_filter($this->http->FindNodes("descendant::tr[ *[2][{$this->eq($this->t("Núm tiquete"))}] ]/following::tr[normalize-space()][1]/*[2]", $res, "/^\s*(?:\w*-)?\s*({$patterns['eTicket']})\s*$/"));
            }

            if (count($tickets) === 0) {
                $tickets = array_filter($this->http->FindNodes("descendant::tr[ *[2][{$this->eq($this->t("Localizador"))}] ]/following::tr[normalize-space()][1]/*[2]", $res, "/^\s*(?:\w*-)?\s*({$patterns['eTicket']})\s*$/"));
            }

            if (count($tickets) > 0) {
                $f->issued()->tickets(array_unique($tickets), false);
            }

            // Price
            $totalBooking = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("TOTAL RESERVA"))}] ]/*[normalize-space()][2]", $res, true, '/^.*\d.*$/');

            if (preg_match('/^(?<currency>[^\-\d)(]+?)?[ ]*(?<amount>\d[,.\'\d ]*?)[ ]*(?<currencyCode>[A-Z]{3})$/', $totalBooking, $matches)) {
                // $180,906.0 COP    |    180,906.0 COP
                $f->price()->total(PriceHelper::parse($matches['amount'], $matches['currencyCode']))->currency($matches['currencyCode']);

                $cost = $this->http->FindSingleNode("descendant::tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t("TOTAL CARGOS/TARIFA/IMPUESTOS"))}] ]/*[normalize-space()][2]", $res, true, '/^.*\d.*$/');

                if (preg_match('/(?<amount>\d[,.\'\d ]*?)[ ]*' . $matches['currencyCode'] . '$/', $cost, $m) // // $76,580.0 COP
                    || array_key_exists('currency', $matches) && preg_match('/^' . preg_quote($matches['currency'], '/') . '[ ]*(?<amount>\d[,.\'\d ]*?)$/', $cost, $m) // $76,580.0
                ) {
                    $f->price()->cost(PriceHelper::parse($m['amount'], $matches['currencyCode']));
                }
            }

            if (!empty($f->getPrice()) && !empty($f->getPrice()->getCost()) && !empty($f->getPrice()->getTotal())) {
                $taxesXpath = "descendant::tr[ *[normalize-space()][1][{$this->eq($this->t("TOTAL CARGOS/TARIFA/IMPUESTOS"))}] and following-sibling::tr/*[normalize-space()][1][{$this->eq($this->t("TOTAL RESERVA"))}] ]/following-sibling::tr[normalize-space()][not(*[normalize-space()][1][{$this->eq($this->t("notTaxes"))}])]";
                $taxesRows = $this->http->XPath->query($taxesXpath, $res);
                $taxes = [];

                foreach ($taxesRows as $tRoot) {
                    $name = $this->http->FindSingleNode("*[normalize-space()][1]", $tRoot);

                    if (preg_match("/^\s*" . $this->opt($this->t("TOTAL RESERVA")) . "\s*$/i", $name)) {
                        break;
                    }
                    $taxes[] = [
                        'name'   => $name,
                        'amount' => $this->amount($this->http->FindSingleNode("*[normalize-space()][2]", $tRoot, true, "/^\D*(\d[\d., ]*)\D*$/")),
                    ];
                }
                $allTaxesAmount = array_sum((array_column($taxes, 'amount')));

                if (round($f->getPrice()->getCost() + $allTaxesAmount, 2) == $f->getPrice()->getTotal()) {
                    foreach ($taxes as $tax) {
                        $f->price()
                            ->fee($tax['name'], $tax['amount']);
                    }
                }
            }

            // Segments
            $xpath = "(//*[(self::td or self::th) and not(.//td) and not(.//th)][" . $this->eq($this->t("SALIDA")) . "])[1]/ancestor::tr[1]/following-sibling::tr[not({$this->contains($this->t('tiempo de espera'))})]";
            $nodes = $this->http->XPath->query($xpath, $res);

            if ($nodes->length == 0) {
                $xpath = "(//*[(self::td or self::th) and ancestor::thead and not(.//td) and not(.//th)][" . $this->eq($this->t("SALIDA")) . "])[1]/ancestor::table[1]/tbody/tr[not({$this->contains($this->t('tiempo de espera'))})]";
                $nodes = $this->http->XPath->query($xpath, $res);
            }

            if ($nodes->length == 0) {
                $this->logger->debug("segments root not found: $xpath");
            }

            foreach ($nodes as $root) {
                $s = $f->addSegment();

                $values = [];

                foreach ($this->http->XPath->query("*", $root) as $r) {
                    $values[] = implode(' ', $this->http->FindNodes("descendant::text()[normalize-space()]", $r));
                }

                if (count($values) < 7) {
                    $this->logger->debug("error parsed segment");

                    break;
                }

                if (count($values) >= 7 && preg_match("/\b\d{4}\b/", $values[3])) {
                    $table = [
                        'from'    => $values[1],
                        'to'      => $values[2],
                        'date'    => $values[3],
                        'depTime' => $values[4],
                        'arrTime' => $values[5],
                        'flight'  => $values[6],
                    ];
                } else {
                    $table = [
                        'from'    => $values[0],
                        'to'      => $values[1],
                        'date'    => $values[4],
                        'depTime' => $values[2],
                        'arrTime' => $values[3],
                        'flight'  => $values[5],
                    ];
                }

                // Airline
                $s->airline()
                    ->noName()
                    ->number($table['flight']);

                $operatedByColumnNum = count($this->http->FindNodes("preceding::*[self::td or self::th][{$this->eq($this->t("OPERADO POR"))}]/preceding-sibling::*", $root));

                if (!empty($operatedByColumnNum)) {
                    $s->airline()->operator($this->http->FindSingleNode("*[" . ($operatedByColumnNum + 1) . "]", $root));
                }

                // Departure
                if (preg_match('/^\s*([A-Z]{3})\s*$/', $table['from'], $m)) {
                    $s->departure()->code($m[1]);
                } elseif (preg_match('/^\s*(?<name>.{2,}?)\s+-\s+\(\s*(?<code>[A-Z]{3})\s*\)\s*$/', $table['from'], $m)
                    || preg_match('/^\s*(?<name>.{2,}?)\s+(?<code>[A-Z]{3})\s*$/', $table['from'], $m)
                ) {
                    $s->departure()->name($m[1])->code($m[2]);
                }
                $s->departure()->date($this->normalizeDate($table['date'] . ', ' . $table['depTime']));

                // Arrival
                if (preg_match('/^\s*([A-Z]{3})\s*$/', $table['to'], $m)) {
                    $s->arrival()->code($m[1]);
                } elseif (preg_match('/^\s*(?<name>.{2,}?)\s+-\s+\(\s*(?<code>[A-Z]{3})\s*\)\s*$/', $table['to'], $m)
                    || preg_match('/^\s*(?<name>.{2,}?)\s+(?<code>[A-Z]{3})\s*$/', $table['to'], $m)
                ) {
                    $s->arrival()->name($m[1])->code($m[2]);
                }
                $s->arrival()->date($this->normalizeDate($table['date'] . ', ' . $table['arrTime']));

                // Extra
                $s->extra()->bookingCode($this->http->FindSingleNode("*[last()]", $root, null, "/^\s*([A-Z]{1,2})\s*$/"));
            }
        }
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
        $in = [
            "#^(\d{4})/(\d+)/(\d+)$#",
        ];
        $out = [
            "$3.$2.$1",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = \AwardWallet\Engine\MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }

    private function re($re, $str, $c = 1): ?string
    {
        if (preg_match($re, $str, $m) && array_key_exists($c, $m)) {
            return $m[$c];
        }

        return null;
    }

    private function amount($s)
    {
        $s = str_replace(' ', '', $s);
        $s = str_replace(",", ".", preg_replace("#[., ](\d{3})#", "$1", $s));

        if (is_numeric($s)) {
            return round((float) $s, 2);
        }

        return null;
    }

    private function currency($s)
    {
        $sym = [
            '€'=> 'EUR',
            //			'$'=>'USD',
        ];

        if ($code = $this->re("#\b([A-Z]{3})\b#", $s)) {
            return $code;
        }
        $s = preg_replace("/\d[\d,. ]*/", '', $s);

        foreach ($sym as $f => $r) {
            if ($s === $f) {
                return $r;
            }
        }

        return null;
    }

    private function eq($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "normalize-space(.)=\"{$s}\""; }, $field)) . ')';
    }

    private function contains($field)
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(" or ", array_map(function ($s) { return "contains(normalize-space(.), \"{$s}\")"; }, $field)) . ')';
    }

    private function striposAll($text, $needle): bool
    {
        if (empty($text) || empty($needle)) {
            return false;
        }

        if (is_array($needle)) {
            foreach ($needle as $n) {
                if (stripos($text, $n) !== false) {
                    return true;
                }
            }
        } elseif (is_string($needle) && stripos($text, $needle) !== false) {
            return true;
        }

        return false;
    }

    private function opt($field)
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
