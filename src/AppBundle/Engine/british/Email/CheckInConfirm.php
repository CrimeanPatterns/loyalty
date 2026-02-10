<?php

namespace AwardWallet\Engine\british\Email;

use AwardWallet\Engine\MonthTranslate;

// TODO: merge with parsers british/It1880761 (in favor of british/CheckInConfirm)

class CheckInConfirm extends \TAccountChecker
{
    public $mailFiles = "british/it-30147538.eml, british/it-30586042.eml, british/it-4782228.eml, british/it-4862467.eml, british/it-4880157.eml, british/it-9859291.eml, british/it-9899754.eml, british/it-9987643.eml";

    public $reBody = [
        'en' => ['Confirmation of check in', 'Submission of a request does not mean that the reservation has been confirmed until payment has been completed'],
        'it' => ['Conferma del check-in', 'La presentazione di una richiesta non significa che la prenotazione sia stata confermata prima del saldo del pagamento'],
        'fr' => ['Confirmation d\'enregistrement', 'Tous les vols British Airways sont non-fumeurs'],
        'es' => ['Confirmación de la facturación', 'Servicio de Atención al Cliente de British Airways'],
        'pt' => ['Confirmação de check-in', 'Serviço ao cliente da British Airways'],
        'de' => ['Bestätigung des Check-In', 'Das Absenden einer Anfrage bedeutet nicht, dass die'],
        'pl' => ['Bestätigung des Check-In', 'podlega Warunkom kontraktu British Airways'],
    ];
    public $reSubject = [
        'en' => ['BA check-in confirmation'],
        'it' => ['British Airways - Conferma check-in'],
        'fr' => ['Confirmation d\'enregistrement BA'],
        'es' => ['Confirmación de la facturación de BA'],
        'pt' => ['Confirmação de check-in da BA'],
        'de' => ['BA Check-In-Bestätigung'],
        'pl' => ['Potwierdzenie odprawy BA'],
    ];
    public $lang = 'en';

    public static $dict = [
        'en' => [
        ],
        'it' => [
            'Booking reference' => 'Codice di prenotazione',
            'Passenger'         => 'Passeggero',
            'Checked-in'        => 'Check-in effettuato',
            'Seat'              => 'Sede',
        ],
        'fr' => [
            'Booking reference' => 'Référence de réservation',
            'Passenger'         => 'Passager(s)',
            'Checked-in'        => 'Enregistré',
            //			'Seat' => ''
        ],
        'es' => [
            'Booking reference' => 'Referencia de la reserva',
            'Passenger'         => 'Pasajero(s)',
            'Checked-in'        => 'Facturado',
            //			'Seat' => ''
        ],
        'pt' => [
            'Booking reference' => 'Referência de reserva',
            'Passenger'         => 'Passageiro(s)',
            'Checked-in'        => 'Check-in',
            //			'Seat' => ''
        ],
        'de' => [
            'Booking reference' => 'Buchungsreferenz',
            'Passenger'         => 'Fluggäste',
            'Checked-in'        => 'Eingecheckt',
            //			'Seat' => ''
        ],
        'pl' => [
            'Booking reference' => 'Numer rezerwacji',
            'Passenger'         => 'Pasażer(owie)',
            'Checked-in'        => 'Odprawa zakończona',
            //			'Seat' => ''
        ],
    ];

    public function ParsePlanEmail(\PlancakeEmailParser $parser)
    {
        $body = $this->http->Response['body'];
        $this->assignLang($body);

        $its = $this->parseEmail();

        return [
            'parsedData' => ['Itineraries' => $its],
            'emailType'  => "CheckInConfirm" . ucfirst($this->lang),
        ];
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        $body = $parser->getHTMLBody();

        if (stripos($body, 'British Airways') !== false
            && $this->http->XPath->query("//a[contains(@href,'ba.com') or contains(@href,'britishairways.com')] | //img[contains(@alt, 'British Airways') or contains(@src,'ba.com') or contains(@src,'britishairways.com')]")->length > 0
        ) {
            return $this->assignLang($body);
        }

        return false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers["subject"])) {
            return false;
        }

        foreach ($this->reSubject as $re) {
            if (strpos($headers["subject"], $re[0]) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailFromProvider($from)
    {
        return stripos($from, "@ba.com") !== false || stripos($from, ".ba.com") !== false;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dict);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dict);
    }

    protected function getDate($nodeForDate)
    {
        $res = '';

        if ($this->lang !== 'en' && preg_match('/\b([[:alpha:]]+)\b/', $nodeForDate, $m)) {
            $month = MonthTranslate::translate($m[1], $this->lang);
        }

        if (!empty($month) && preg_match("#(?:(?<dayOfWeek>.+)\s+)?(?<day>\d+)\s*(?<month>\S+)\s*(?<year>\d{4})(?:\s+(?<time>\d+\:\d+))?#", $nodeForDate, $m)) {
            $res = $m['day'] . ' ' . $month . ' ' . $m['year'] . ' ' . $m['time'];
        }

        if (empty($res)) {
            $res = $nodeForDate;
        }

        return $res;
    }

    private function parseEmail(): array
    {
        $it = ['Kind' => 'T', 'TripSegments' => []];
        $it['RecordLocator'] = $this->http->FindSingleNode("//*[contains(text(),'" . $this->t('Booking reference') . "')]/span");

        if (empty($it['RecordLocator'])) {
            $it['RecordLocator'] = $this->http->FindSingleNode("//text()[starts-with(normalize-space(),'" . $this->t('Booking reference') . "')]/following::text()[normalize-space()][1]", null, true, "#^\s*([A-Z\d]{5,7})\s*$#");
        }

        $passengers = [];

        $xpath = "//tr[{$this->starts($this->t('Passenger'))} and following-sibling::tr[normalize-space()]]/preceding-sibling::tr[contains(.,':')][1]";
        $segments = $this->http->XPath->query($xpath);

        foreach ($segments as $root) {
            $seg = [];

            $xpathSeg = "descendant::*[count(tr[normalize-space()])=2][1]";

            $node = $this->http->FindSingleNode($xpathSeg . "/tr[normalize-space()][1]", $root);

            if (preg_match("#(?<AirlineName>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<FlightNumber>\d+)(?:\s+(?<Operator>.+))?#", $node, $m)) {
                $seg['AirlineName'] = $m['AirlineName'];
                $seg['FlightNumber'] = $m['FlightNumber'];

                if (!empty($m['Operator']) && stripos($m['Operator'], 'Traveller')) {
                    $seg['Cabin'] = $m['Operator'];
                } elseif (!empty($m['Operator'])) {
                    $seg['Operator'] = $m['Operator'];
                }
            }

            // 19 Nov 2017 21:00 MEX (Mexico (City)) Terminal 1
            $pattern1 = "/(?:^|(?<dateTime>\d+\s*\w+\s*\d+\s+\d+:\d+)\s+)(?<code>[A-Z]{3})\s+\((?<name>.+)\)(?:\s+Terminal\s+(?<terminal>.+))?/";

            // 7 May 2021 15:30 Heathrow (London)
            $pattern2 = "/^(?<dateTime>\d+\s*\w+\s*\d+\s+\d+:\d+)\s+(?<name>.{3,}?)(?:\s+Terminal\s+(?<terminal>.+))?$/";

            $departure = implode(' ', $this->http->FindNodes($xpathSeg . "/tr[normalize-space()][2]/descendant::table[2]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern1, $departure, $m) || preg_match($pattern2, $departure, $m)) {
                $seg['DepDate'] = empty($m['dateTime']) ? MISSING_DATE : strtotime($this->getDate($m['dateTime']));
                $seg['DepCode'] = empty($m['code']) ? TRIP_CODE_UNKNOWN : $m['code'];
                $seg['DepName'] = preg_replace('/^\s*\(\s*(.{3,}?)\s*\)\s*$/', '$1', $m['name']);

                if (!empty($m['terminal'])) {
                    $seg['DepartureTerminal'] = $m['terminal'];
                }
            }

            $arrival = implode(' ', $this->http->FindNodes($xpathSeg . "/tr[normalize-space()][2]/descendant::table[3]/descendant::text()[normalize-space()]", $root));

            if (preg_match($pattern1, $arrival, $m) || preg_match($pattern2, $arrival, $m)) {
                $seg['ArrDate'] = empty($m['dateTime']) ? MISSING_DATE : strtotime($this->getDate($m['dateTime']));
                $seg['ArrCode'] = empty($m['code']) ? TRIP_CODE_UNKNOWN : $m['code'];
                $seg['ArrName'] = preg_replace('/^\s*\(\s*(.{3,}?)\s*\)\s*$/', '$1', $m['name']);

                if (!empty($m['terminal'])) {
                    $seg['ArrivalTerminal'] = $m['terminal'];
                }
            }

            $seats = [];
            $passengerRows = $this->http->XPath->query("following-sibling::tr[{$this->contains($this->t('Seat'))} or {$this->contains($this->t('Checked-in'))}][1]/descendant::tr[*[2] and not(.//tr)]", $root);

            foreach ($passengerRows as $pRow) {
                $passenger = $this->http->FindSingleNode("*[normalize-space()][1][not(contains(.,'(')) and not({$this->starts($this->t('Seat'))}) and not({$this->contains($this->t('Checked-in'))})]", $pRow, true, "/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u");

                if ($passenger) {
                    $passengers[] = $passenger;
                }
                // Seat 9A (window, exit)
                $seat = $this->http->FindSingleNode("*[{$this->starts($this->t('Seat'))}]", $pRow, true, "/^{$this->opt($this->t('Seat'))}\s*(\d+[A-Z])(?:\s*\(|$)/");

                if ($seat) {
                    $seats[] = $seat;
                }
            }

            if (count($seats)) {
                $seg['Seats'] = array_unique($seats);
            }

            $it['TripSegments'][] = $seg;
        }

        if (count($passengers)) {
            $it['Passengers'] = array_unique($passengers);
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ *[normalize-space()][last()][{$this->eq($this->t('Payment total'))}] ]/following-sibling::tr/*[normalize-space()][last()]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d]*)$/', $totalPrice, $m)) {
            // GBP 62.00
            $it['Currency'] = $m['currency'];
            $it['TotalCharge'] = $this->normalizeAmount($m['amount']);
        }

        return [$it];
    }

    private function t($s)
    {
        if (!isset($this->lang) || !isset(self::$dict[$this->lang][$s])) {
            return $s;
        }

        return self::$dict[$this->lang][$s];
    }

    private function assignLang($body): bool
    {
        if (isset($this->reBody)) {
            foreach ($this->reBody as $lang => $reBody) {
                if (stripos($body, $reBody[0]) !== false || stripos($body, $reBody[1]) !== false) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string|null $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(?string $s, ?string $decimals = null): ?float
    {
        if (!empty($decimals) && preg_match_all('/(?:\d+|' . preg_quote($decimals, '/') . ')/', $s, $m)) {
            $s = implode('', $m[0]);

            if ($decimals !== '.') {
                $s = str_replace($decimals, '.', $s);
            }
        } else {
            $s = preg_replace('/\s+/', '', $s);
            $s = preg_replace('/[,.\'](\d{3})/', '$1', $s);
            $s = preg_replace('/,(\d{1,2})$/', '.$1', $s);
        }

        return is_numeric($s) ? (float) $s : null;
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
