<?php

namespace AwardWallet\Engine\amadeus\Email;

use AwardWallet\Engine\MonthTranslate;

class ReservationDate extends \TAccountChecker
{
    public $mailFiles = "amadeus/it-11409709.eml, amadeus/it-41511516.eml, amadeus/it-41821227.eml";
    private $providerCode = '';
    private $reFrom = 'webmaster@amadeus.com';
    private $reSubject = [
        'es' => 'Datos de la reserva',
        'fr' => 'Détails de la réservation',
    ];
    private $reBody2 = [
        'es' => ['Información del Vuelo'],
        'fr' => ['Détails de la réservation'],
    ];
    private $lang = '';
    private static $dict = [
        'es' => [],
        'fr' => [
            'Localizador del vuelo' => 'N° du PNR',
            'Status del PNR'        => 'Statut PNR',
            'Nombre del pasajero'   => 'Nom du passager',
            'Cesta total'           => 'Total du panier',
            'Saliendo de'           => 'Départ',
            'Llegando a'            => 'Arrivée',
            'Número de vuelo'       => 'Numéro du vol',
            'operado por'           => 'Opéré par',
            'Avión'                 => "Type d'Appareil",
            'Clase'                 => 'Classe',
            'Duración'              => 'Duration',
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $this->assignProvider($parser->getHeaders());

        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return null;
        }

        $its = $this->parseEmail();

        return [
            'providerCode' => $this->providerCode,
            'emailType'    => 'ReservationDate' . ucfirst($this->lang),
            'parsedData'   => ['Itineraries' => $its],
        ];
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, $this->reFrom) !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        return $this->assignProvider($parser->getHeaders()) && $this->assignLang();
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    public static function getEmailProviders()
    {
        return ['evol', 'amadeus'];
    }

    protected function re($re, $str, $c = 1)
    {
        preg_match($re, $str, $m);

        if (isset($m[$c])) {
            return $m[$c];
        }

        return null;
    }

    private function parseEmail()
    {
        $it = ['Kind' => 'T'];

        // RecordLocator
        $flightLocator = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Localizador del vuelo'))}]/following::text()[normalize-space()][1]", null, true, "/^[A-Z\d]{5,7}$/");

        if ($flightLocator) {
            $it['RecordLocator'] = $flightLocator;
        } elseif ($this->http->XPath->query("//node()[{$this->contains(['La tentative de réservation a échouée!', 'Your reservation has not been completed successfully.'])}]")->length > 0) {
            $it['RecordLocator'] = CONFNO_UNKNOWN;
        }

        // Status
        $status = $this->http->FindSingleNode("descendant::td[{$this->eq($this->t('Status del PNR'))}][1]/following-sibling::td[normalize-space()][1]");

        if ($status !== null) {
            $it['Status'] = $status;
        }

        // Passengers
        $passengers = $this->http->FindNodes("//text()[{$this->eq($this->t('Nombre del pasajero'))}]/ancestor::td[1]/following-sibling::td[normalize-space()][1]", null, "#^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$#u");
        $passengers = array_filter($passengers);

        if ($passengers) {
            $it['Passengers'] = array_unique($passengers);
        }

        // TotalCharge
        // Currency
        $total = $this->http->FindSingleNode("//text()[" . $this->contains($this->t('Cesta total')) . "]/ancestor::td[1]/following-sibling::td[normalize-space()][last()]");

        if (preg_match('/^(?<amount>\d[,.\'\d ]*) ?(?<currency>[^\d)(]+)/', $total, $m)) {
            // 3.544,96 EUR    |    6 224,00 MAD
            $it['TotalCharge'] = $this->normalizeAmount($m['amount']);
            $it['Currency'] = $this->currency($m['currency']);
        }

        $xpath = "//text()[" . $this->eq($this->t('Saliendo de')) . "]/ancestor::table[1]";
        $roots = $this->http->XPath->query($xpath);

        if ($roots->length === 0) {
            $this->logger->debug('Segments not found');

            return $it;
        }

        foreach ($roots as $root) {
            $seg = [];

            // FlightNumber
            // AirlineName
            // Operator
            $nodeValue = implode("\n", $this->http->FindNodes(".//text()[" . $this->contains($this->t('Número de vuelo')) . "]/ancestor::td[1]//text()[normalize-space()]", $root));

            if (preg_match("#(?:" . $this->preg_implode($this->t('Número de vuelo')) . ")\s*:?\s*([A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(\d{1,5})(?:\s+" . $this->t('operado por') . "\s+(.+))?#", $nodeValue, $m)) {
                $seg['AirlineName'] = $m[1];
                $seg['FlightNumber'] = $m[2];

                if (!empty($m[3])) {
                    $seg['Operator'] = $m[3];
                }
            }

            $dateVariants = [
                "[-[:alpha:]]{2,}\s*,\s*\d{1,2}\s+de\s+[[:alpha:]]{3,}\s+de\s+\d{4}", // domingo, 8 de abril de 2018
                "[-[:alpha:]]{2,}\s*,\s*[[:alpha:]]{3,}\s+\d{1,2}\s*,\s*\d{4}", // lundi, août 05, 2019
            ];
            $patterns['date'] = implode('|', $dateVariants);
            $patterns['time'] = '\d{1,2}:\d{2}(?:\s*[AaPp]\.?[Mm]\.?)?';

            // DepCode
            // DepName
            // DepDate
            $departure = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Saliendo de'))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("#^(?<name>.{3,}?)\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})$#u", $departure, $m)) {
                $seg['DepCode'] = TRIP_CODE_UNKNOWN;
                $seg['DepName'] = $m['name'];
                $seg['DepDate'] = strtotime($this->normalizeDate($m['date']) . ' ' . $m['time']);
            }
            // DepartureTerminal
            $terminalDep = implode(' ', $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Saliendo de'))}]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("#^Terminal[:\s]+(.+)$#", $terminalDep, $m)) {
                $seg['DepartureTerminal'] = $m[1];
            }

            // ArrCode
            // ArrName
            // ArrDate
            $arrival = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Llegando a'))}]/ancestor::td[1]/following-sibling::td[1]", $root);

            if (preg_match("#^(?<name>.{3,}?)\s+(?<date>{$patterns['date']})\s+(?<time>{$patterns['time']})$#u", $arrival, $m)) {
                $seg['ArrCode'] = TRIP_CODE_UNKNOWN;
                $seg['ArrName'] = $m['name'];
                $seg['ArrDate'] = strtotime($this->normalizeDate($m['date']) . ' ' . $m['time']);
            }
            // ArrivalTerminal
            $terminalArr = implode(' ', $this->http->FindNodes("descendant::text()[{$this->eq($this->t('Llegando a'))}]/ancestor::td[1]/following-sibling::td[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match("#^Terminal[:\s]+(.+)$#", $terminalArr, $m)) {
                $seg['ArrivalTerminal'] = $m[1];
            }

            // Aircraft
            $seg['Aircraft'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Avión')) . "]/ancestor::div[1]", $root, true, "#:\s*(.+)#");

            // Cabin
            // BookingClass
            $cabin = $this->http->FindSingleNode("descendant::text()[{$this->contains($this->t('Clase'))}]/ancestor::div[1]", $root, true, "#{$this->preg_implode($this->t('Clase'))}[:\s]+(.+)#");

            if (preg_match("#^(.+?)?\s*\(\s*([A-Z]{1,2})\s*\)#", $cabin, $m)) {
                $seg['Cabin'] = $m[1];
                $seg['BookingClass'] = $m[2];
            }

            // Seats
            // Duration
            $seg['Duration'] = $this->http->FindSingleNode(".//text()[" . $this->contains($this->t('Duración')) . "]/ancestor::div[1]", $root, true, "#:\s*(.+)#");

            $it['TripSegments'][] = $seg;
        }

        return [$it];
    }

    private function assignProvider($headers): bool
    {
        if (stripos($headers['from'], '@e-vol.ma') !== false
            || $this->http->XPath->query("//node()[contains(normalize-space(),\"Cordialement, L'équipe E-vol.ma\") or contains(normalize-space(),\"Cordialement,L'équipe E-vol.ma\")]")->length > 0
        ) {
            $this->providerCode = 'evol';

            return true;
        }

        if (stripos($headers['from'], '@amadeus.') !== false
            || $this->http->XPath->query('//img[contains(@src,".amadeus.com/grupogea")]')->length > 0
        ) {
            $this->providerCode = 'amadeus';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        foreach ($this->reBody2 as $lang => $reBody) {
            foreach ($reBody as $re) {
                if ($this->http->XPath->query('//node()[contains(normalize-space(),"' . $re . '")]')->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    private function t($s)
    {
        if (isset($this->lang) && isset(self::$dict[$this->lang][$s])) {
            return self::$dict[$this->lang][$s];
        }

        return $s;
    }

    private function normalizeDate($str)
    {
        $in = [
            // miércoles, 28 de febrero de 2018
            '/^[-[:alpha:]]{2,}\s*,\s*(\d{1,2})\s+de\s+([[:alpha:]]{3,})\s+de\s+(\d{4})$/u',
            // lundi, août 05, 2019
            '/^[-[:alpha:]]{2,}\s*,\s*([[:alpha:]]{3,})\s+(\d{1,2})\s*,\s*(\d{4})$/u',
        ];
        $out = [
            "$1 $2 $3",
            "$2 $1 $3",
        ];
        $str = preg_replace($in, $out, $str);

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return $str;
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

    private function preg_implode($field)
    {
        if (!is_array($field)) {
            $field = [$field];
        }

        return implode("|", array_map(function ($s) { return preg_quote($s); }, $field));
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $string Unformatted string with amount
     */
    private function normalizeAmount(string $s): string
    {
        $s = preg_replace('/\s+/', '', $s);
        $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
        $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);

        return $s;
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
}
