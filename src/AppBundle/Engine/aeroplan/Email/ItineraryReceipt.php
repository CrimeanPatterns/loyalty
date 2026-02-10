<?php

namespace AwardWallet\Engine\aeroplan\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers aeroplan/It3354114, aeroplan/It1884792, aeroplan/It4192054 (in favor of aeroplan/ItineraryReceipt)

class ItineraryReceipt extends \TAccountChecker
{
    public $mailFiles = "aeroplan/it-1730490.eml, aeroplan/it-1730494.eml, aeroplan/it-1884792.eml, aeroplan/it-1926300.eml, aeroplan/it-2.eml, aeroplan/it-2071944.eml, aeroplan/it-2414694.eml, aeroplan/it-3.eml, aeroplan/it-3170388.eml, aeroplan/it-3354114.eml, aeroplan/it-3779107.eml, aeroplan/it-3780441.eml, aeroplan/it-3801687.eml, aeroplan/it-3801693.eml, aeroplan/it-3801728.eml, aeroplan/it-3801831.eml, aeroplan/it-3812670.eml, aeroplan/it-4045403.eml, aeroplan/it-4109242.eml, aeroplan/it-4134366.eml, aeroplan/it-4139441.eml, aeroplan/it-4148039.eml, aeroplan/it-4166227.eml, aeroplan/it-4192054.eml, aeroplan/it-4211915.eml, aeroplan/it-5827361.eml, aeroplan/it-634364155.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [// en first
            // 'booking ref' => '', // in subject
            // 'Booking Date:'   => '',
            'confNumber'    => ['Booking Reference', 'Booking Reference:'],
            'Flight'        => 'Flight',
            'Stops'         => 'Stops',
            'Duration'      => 'Duration',
            'Aircraft'      => 'Aircraft',
            'Fare Type'     => 'Fare Type',
            'Meals'         => ['Meals', 'Meal'],
            'mealVariants'  => [
                'F' => 'Food for Purchase',
                'B' => 'Breakfast',
                'M' => 'Meal (Non Specific)',
                'K' => 'Continental breakfast',
                'S' => 'Snack or Brunch',
            ],
            // 'Passenger Information'   => '',
            // 'Ticket Number'   => '',
            'Frequent Flyer'   => ['Frequent Flyer', 'Frequent Flyer Pgm', 'Air Canada Aeroplan:'],

            // 'Seat Selection'        => '',
            // 'Air Canada - Aeroplan'   => '',
            'Purchase Summary'   => ['Purchase Summary', 'Summary of Payment Details'],
            // 'Grand Total'   => '',
        ],
        'es' => [
            'Stops'       => ['Escalas'],
            'Aircraft'    => ['Aeronave'],
            'booking ref' => 'código de reserva', // in subject
            'confNumber'  => ['Código de reserva', 'Código de reserva:'],
            // 'Booking Date:' => '',
            'mealVariants' => [
                // 'F' => '',
                'B' => 'Desayuno',
                'M' => 'Comida (sin especificar)',
                // 'K' => '',
            ],
            'Passenger Information' => 'Información del pasajero',
            'Ticket Number'         => 'Número de boleto',
            'Frequent Flyer'        => 'Programa de viajero frecuente',
            'Seat Selection'        => 'Selección de asiento',
            'Purchase Summary'      => 'Resumen de compra',
            'Grand Total'           => 'Total general',

            'Flight'        => 'Vuelo',

            'Fare Type'     => ['Tipo de tarifa', 'Tipo detarifa'],
            'Duration'      => 'Duración',
            'Meals'         => ['Comida'],
        ],
        'fr' => [
            'booking ref'   => 'no de réservation', // in subject

            'Stops'         => ['Arrêts'],
            'Aircraft'      => ['Appareil'],
            'confNumber'    => ['Numéro de réservation', 'Numéro de réservation:'],
            'Booking Date:' => 'Date de Réservation:',
            'mealVariants'  => [
                'F' => "Nourriture à l'achat",
                // 'B' => '',
                // 'M' => '',
                // 'K' => '',
            ],
            'Passenger Information' => 'Passagers',
            'Ticket Number'         => 'Numéro de billet',
            'Frequent Flyer'        => 'Programme de fidélisation',
            'Seat Selection'        => 'Place sélectionnée',
            'Purchase Summary'      => "Sommaire de l'achat",
            'Grand Total'           => 'Grand total',
            'Flight'                => 'Vol',

            'Fare Type'     => 'Classe tarifaire',
            'Duration'      => 'Durée',
            'Meals'         => ['Repas'],
            // 'Air Canada - Aeroplan'   => '',
            // 'Purchase Summary'   => '',
            // 'Grand Total'   => '',
        ],
        'it' => [// en first
            // 'booking ref' => '', // in subject
            // 'Booking Date:'   => '',
            'confNumber'    => ['Riferimento prenotazione', 'Riferimento prenotazione:'],
            'Flight'        => 'Volo',
            'Stops'         => 'Scali',
            'Aircraft'      => 'Tipo di aereo',
            'Fare Type'     => 'Tipo di tariffa',
            'Duration'      => 'Durata',
            'Meals'         => ['Pasto'],
            // 'mealVariants' => [
            //     'F' => 'Food for Purchase',
            //     'B' => 'Breakfast',
            //     'M' => 'Meal (Non Specific)',
            //     'K' => 'Continental breakfast',
            // ],
            'Passenger Information'   => 'Informazioni sul passeggero',
            'Ticket Number'           => 'Numero del biglietto',
            'Frequent Flyer'          => 'Prog. Frequent Flyer',
            // 'Seat Selection'        => '',
            'Purchase Summary'   => 'Riepilogo acquisto',
            'Grand Total'        => 'Totale complessivo',
        ],
        'de' => [// en first
            'booking ref' => 'booking ref', // in subject
            // 'Booking Date:'   => '',
            'confNumber'    => ['Buchungsreferenz', 'Buchungsreferenz:'],
            'Flight'        => 'Flug',
            'Stops'         => 'Stops',
            'Aircraft'      => 'Flugzeug',
            'Fare Type'     => 'Tarif',
            'Duration'      => 'Dauer',
            'Meals'         => ['Mahlzeit'],
            // 'mealVariants' => [
            //     'F' => 'Food for Purchase',
            'B' => 'Frühstück',
            'M' => 'Mahlzeit (allgemein)',
            //     'K' => 'Continental breakfast',
            // ],
            'Passenger Information'   => 'Passagierdaten',
            'Ticket Number'           => 'Ticketnummer',
            'Frequent Flyer'          => 'Vielfliegerprogramm',
            'Seat Selection'          => 'Sitzplatzwahl',
            'Purchase Summary'        => 'Übersicht erworbener Tickets',
            'Grand Total'             => 'Endsumme',
        ],
    ];

    private $subjects = [
        'es' => ['(código de reserva'],
        'fr' => ['(no de réservation'],
        'en' => ['(booking ref'],
        'it' => ['l\'itinerario per il prossimo viaggio'],
        'de' => ['(booking ref:'],
    ];

    private $detectors = [
        'en' => ['Flight Itinerary'],
        'es' => ['Itinerario de vuelo'],
        'fr' => ['Itinéraire'],
        'it' => ['Itinerario di volo'],
        'de' => ['Reiseplan'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]aircanada\.(?:com|ca)\b/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true && strpos($headers['subject'], 'Air Canada') === false) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".aircanada.com/") or contains(@href,"www.aircanada.com")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"www.aircanada.com") or contains(.,"@aircanada.ca")]')->length === 0
            && $this->http->XPath->query('//*[contains(.,"Air Canada applies travel document")]')->length === 0
        ) {
            return false;
        }

        return $this->detectBody() && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }
        $email->setType('ItineraryReceipt' . ucfirst($this->lang));

        $this->parseFlight($email, $parser->getSubject());

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

    private function parseFlight(Email $email, string $subject): void
    {
        $xpathTime = 'contains(translate(normalize-space(),"0123456789：","dddddddddd:"),"d:dd")';

        $patterns = [
            'time'          => '\d{1,2}:\d{2}(?:[ ]*[AaPp][Mm])?', // 4:19PM
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        } elseif (preg_match("/\(\s*({$this->opt($this->t('booking ref'))})[:\s]+([A-Z\d]{5,})\s*\)/i", $subject, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        }

        $creditName = $this->http->FindSingleNode("//text()[normalize-space()='Flight Credit Summary']/following::table[1]/descendant::tr[1]/descendant::td[1]");
        $creditValue = $this->http->FindSingleNode("//text()[normalize-space()='Flight Credit Summary']/following::table[1]/descendant::tr[1]/descendant::td[2]", null, true, "/^(\d+)\s+{$this->opt($this->t('Flight Credit'))}/");

        if (!empty($creditName) && !empty($creditValue)) {
            $f->price()
                ->fee($creditName, $creditValue);
        }

        $bookingDate = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking Date:'))}]/following::text()[normalize-space()][1]", null, true, '/^.*\d.*$/');

        if ($bookingDate) {
            $f->general()->date2($bookingDate);
        }

        $seats = [];
        $passengerRows = $this->http->XPath->query("//*[{$this->eq($this->t('Passenger Information'))}]/following::tr[not(.//tr) and following-sibling::tr[normalize-space()] and {$this->contains($this->t('Ticket Number'))}]");

        foreach ($passengerRows as $pRow) {
            // 1:NATHAN A MICHAEL:Adult,Ticket Number:0142140701408
            $pRowText = $this->http->FindSingleNode('.', $pRow);

            if (preg_match("/^\d{1,3}\s*:\s*({$patterns['travellerName']})(?:\s*:|$)/u", $pRowText, $m)) {
                $m[1] = preg_replace("/^(mr|ms|herr|m\.|dr|Mlle|Sr\.) /i", '', $m[1]);
                $f->general()->traveller($m[1], true);
            }

            if (preg_match("/{$this->opt($this->t('Ticket Number'))}\s*:\s*({$patterns['eTicket']})$/", $pRowText, $m)) {
                $f->issued()->ticket($m[1], false);
            }
            $ffNumber = $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->starts($this->t('Frequent Flyer'))}]/following-sibling::*[normalize-space()][1]", $pRow, true, "/^([-A-Z\d]{5,})\s*\(\s*Air Canada/i")
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->starts($this->t('Frequent Flyer'))}]/following-sibling::*[normalize-space()][1]", $pRow, true, "/^\s*(\d{5,})\s*$/i")
                ?? $this->http->FindSingleNode("following::tr[normalize-space()][1]/descendant-or-self::tr/*[{$this->starts($this->t('Air Canada - Aeroplan'))}]/following-sibling::*[normalize-space()][1]", $pRow, true, "/^[-A-Z\d]{5,}(?:\s*\(|$)/i")
            ;

            if ($ffNumber) {
                $f->program()->account($ffNumber, false);
            }
            // AC1837 4F (Preferred) , AC1848 24C    |    AC 1857 (YUL - LAS)- 17E
            $seatsText = implode(' ', $this->http->FindNodes("following::tr[normalize-space()][position()<6]/descendant-or-self::tr/*[{$this->starts($this->t('Seat Selection'))}]/following-sibling::*[normalize-space()][1]/descendant::text()[normalize-space()]", $pRow));

            $seatText = explode(',', $seatsText);

            foreach ($seatText as $st) {
                if (preg_match("/^\s*(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)(?:\s*\([^)(]+\))?[-\s:]+(?<seat>\d{1,3}[A-Z])\b/", $st, $m)) {
                    $seats[$m['name'] . $m['number']][] = $m['seat'];
                }
            }
        }

        /*
            Toronto, Pearson Int'l (YYZ)
            Fri 26- Jun 2015
            10:00 - Terminal 1
        */
        $aiportRe1 = "/^\s*(?<name>.{3,}?)\s*?\(\s*(?<code>[A-Z]{3})\s*\)(?:\s*,\s*[A-Z]{2}\b)?\s*(?<date>.{6,}?)\s+(?<time>{$patterns['time']})(?:\s*-\s*(?<terminal>[-[\w\s]+))?$/u";
        $aiportRe2 = "/^\s*(?<name>.{3,}?\(\s*[A-Z]+\s*\))\s*(?<date>.{6,}?)(?:\s*,\s*[A-Z]{2}\b)?\s+(?<time>{$patterns['time']})(?:\s*-\s*(?<terminal>[-\w\s]+))?$/u";

        $headers = $this->http->FindNodes("//tr[ *[normalize-space()][4][{$this->eq($this->t('Stops'))}] and *[normalize-space()][1][{$this->eq($this->t('Flight'))}] ][following::tr[ *[6] and *[2][{$xpathTime}] and *[3][{$xpathTime}] ]]/*");
        $cols = [
            'cabin'    => null,
            'meals'    => null,
            'duration' => null,
            'aircraft' => null,
        ];

        for ($i = 4; $i < count($headers); $i++) {
            if (empty($cols['cabin']) && preg_match("/^\s*{$this->opt($this->t('Fare Type'))}\s*$/ui", $headers[$i])) {
                $cols['cabin'] = $i + 1;

                continue;
            }

            if (empty($cols['duration']) && preg_match("/^\s*{$this->opt($this->t('Duration'))}\s*$/ui", $headers[$i])) {
                $cols['duration'] = $i + 1;

                continue;
            }

            if (empty($cols['aircraft']) && preg_match("/^\s*{$this->opt($this->t('Aircraft'))}\s*$/ui", $headers[$i])) {
                $cols['aircraft'] = $i + 1;

                continue;
            }

            if (empty($cols['meals']) && preg_match("/^\s*{$this->opt($this->t('Meals'))}\s*$/ui", $headers[$i])) {
                $cols['meals'] = $i + 1;

                continue;
            }
        }

        $xpath = "//tr[ *[normalize-space()][4][{$this->eq($this->t('Stops'))}] and *[normalize-space()][1][{$this->eq($this->t('Flight'))}] ]/following::tr[ *[6] and *[2][{$xpathTime}] and *[3][{$xpathTime}] ][following::text()[{$this->eq($this->t('Passenger Information'))}]]";
        $segments = $this->http->XPath->query($xpath);

        $noDuration = false;

        if (!empty($cols['duration'])) {
            $durations = array_filter($this->http->FindNodes($xpath . "/*[{$cols['duration']}]", null, "/.*\d.*/"));

            if (count($durations) !== $segments->length) {
                $noDuration = true;
            }
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $flight = $this->http->FindSingleNode('*[1]/descendant::text()[normalize-space()][string-length(normalize-space()) > 1][1]', $root);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);

                if (!empty($seats[$m['name'] . $m['number']])) {
                    $s->extra()->seats($seats[$m['name'] . $m['number']]);
                }
            }

            $from = implode(" ", $this->http->FindNodes('*[2]/descendant::text()[normalize-space()]', $root));

            if (preg_match($aiportRe1, $from, $m) || preg_match($aiportRe2, $from, $m)) {
                $s->departure()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                ;

                if (!empty($m['code'])) {
                    $s->departure()
                        ->code($m['code']);
                } else {
                    $s->departure()
                        ->noCode();
                }

                if (!empty($m['terminal'])) {
                    $s->departure()->terminal(preg_replace("/^Terminal\s*/i", '', $m['terminal']));
                }
            }

            $to = implode(" ", $this->http->FindNodes('*[3]/descendant::text()[normalize-space()]', $root));

            if (preg_match($aiportRe1, $to, $m) || preg_match($aiportRe2, $to, $m)) {
                $s->arrival()
                    ->name($m['name'])
                    ->date($this->normalizeDate($m['date'] . ' ' . $m['time']))
                ;

                if (!empty($m['code'])) {
                    $s->arrival()
                        ->code($m['code']);
                } else {
                    $s->arrival()
                        ->noCode();
                }

                if (!empty($m['terminal'])) {
                    $s->arrival()->terminal(preg_replace("/^Terminal\s*/i", '', $m['terminal']));
                }
            }

            $stops = $this->http->FindSingleNode('*[4]', $root, true, "/^(\d+)\s*(?:$|\\/)/");

            if ($stops !== null) {
                $s->extra()->stops($stops);
            }

            if ($noDuration == false && !empty($cols['duration'])) {
                $s->extra()
                    ->duration($this->http->FindSingleNode("*[{$cols['duration']}]", $root, true, "/^\d.+/"), true, true);
            }

            if (!empty($cols['aircraft'])) {
                $s->extra()
                    ->aircraft($this->http->FindSingleNode("*[{$cols['aircraft']}]", $root), true, true);
            }

            if (!empty($cols['cabin'])) {
                $fareType = implode(' ', $this->http->FindNodes("*[{$cols['cabin']}]/descendant::text()[normalize-space()]", $root));

                if (preg_match("/^(?<cabin>.{2,}?)[,\s]+(?<code>[A-Z]{1,2})$/", $fareType, $m)) {
                    // Flex, K
                    $s->extra()
                        ->cabin($m['cabin'])
                        ->bookingCode($m['code']);
                } elseif (preg_match("/^\s*,\s*(?<code>[A-Z]{1,2})$/", $fareType, $m)) {
                    // , K
                    $s->extra()
                        ->bookingCode($m['code']);
                } elseif ($fareType) {
                    $s->extra()->cabin($fareType);
                }
            }

            if (!empty($cols['meals'])) {
                $mealValue = $this->http->FindSingleNode("*[{$cols['meals']}][not({$this->eq(['NA', 'N/A', 'na', 'n/a'])})]", $root,
                    false);
                $meals = array_map(function ($item) {
                    $mealVariants = $this->t('mealVariants');

                    return empty($mealVariants) || !is_array($mealVariants) || empty($mealVariants[$item]) ? $item : $mealVariants[$item];
                }, preg_split('/\s*[,]+\s*/', $mealValue));

                if (!empty($meals)) {
                    $s->extra()->meals($meals);
                }
            }
        }

        $xpathPurchase = "//*[{$this->eq($this->t('Purchase Summary'))}]/following::tr";

        $grandTotal = $this->http->FindSingleNode($xpathPurchase . "[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Grand Total'))}] ]/*[normalize-space()][2]");

        if (preg_match('/^(?<currency>[^\d)(]+?)[ ]*(?<amount>\d[,.\'\d ]*)$/', $grandTotal, $matches)
            || preg_match('/^(?<amount>\d[,.\'\d ]*)[ ]*(?<currency>[^\d)(]+?)$/', $grandTotal, $matches)
        ) {
            // $1,168.02    |    1 503,40 $
            $currencyText = $this->http->FindSingleNode($xpathPurchase . "[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->starts($this->t('Grand Total'))}] ]/*[normalize-space()][1]", null, true, "/{$this->opt($this->t('Grand Total'))}\s*-\s*(.+)$/");

            if (preg_match("/\(\s*([A-Z]{3})\s*\)$/", $currencyText, $m)) {
                $f->price()->currency($currencyCode = $m[1]);
            } elseif (preg_match("/^(?:Canadian Dollars?|Dollars? Canadiens?)$/i", $currencyText, $m)) {
                $f->price()->currency($currencyCode = 'CAD');
            } elseif (preg_match("/^(?:(?:US|American) Dollars?|dólar(?:es)? estadounidenses?)$/i", $currencyText, $m)) {
                $f->price()->currency($currencyCode = 'USD');
            } elseif (preg_match("/^Euro$/i", $currencyText, $m)) {
                $f->price()->currency($currencyCode = 'EUR');
            } elseif (preg_match("/^Pounds Sterling$/i", $currencyText, $m)) {
                $f->price()->currency($currencyCode = 'GBP');
            } else {
                $currencyCode = null;
                $f->price()->currency($matches['currency']);
            }

            $f->price()->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }
    }

    private function detectBody(): bool
    {
        if (!isset($this->detectors)) {
            return false;
        }

        foreach ($this->detectors as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (!is_string($phrase)) {
                    continue;
                }

                if ($this->http->XPath->query("//*[{$this->contains($phrase)}]")->length > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (empty($phrases['Stops']) || $this->http->XPath->query("//*[{$this->eq($phrases['Stops'])}]")->length == 0
            ) {
                continue;
            }

            if (
                (!empty($phrases['Aircraft']) && $this->http->XPath->query("//*[{$this->eq($phrases['Aircraft'])}]")->length > 0)
                || (!empty($phrases['Fare Type']) && $this->http->XPath->query("//*[{$this->eq($phrases['Fare Type'])}]")->length > 0)
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function normalizeDate($str)
    {
        // $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            "/^\s*[-[:alpha:]]+\s*[., ]+\s*(\d+)\s*-\s*([[:alpha:]]+)\s*[.,\s]*\s*(\d{4})\s+(\d+:\d+(\s*[ap]m)?)\s*$/ui",
        ];
        $out = [
            "$1 $2 $3, $4",
        ];
        $str = preg_replace($in, $out, $str);
        // $this->logger->debug('$str2 = '.print_r( $str,true));

        if (preg_match("#\d+\s+([^\d\s]+)\s+\d{4}#", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }
        // $this->logger->debug('$str3 = '.print_r( strtotime($str),true));

        return strtotime($str);
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    private function contains($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'contains(normalize-space(' . $node . '),' . $s . ')';
        }, $field)) . ')';
    }

    private function eq($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'normalize-space(' . $node . ')=' . $s;
        }, $field)) . ')';
    }

    private function starts($field, string $node = ''): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($node) {
            $s = strpos($s, '"') === false ? '"' . $s . '"' : 'concat("' . str_replace('"', '",\'"\',"', $s) . '")';

            return 'starts-with(normalize-space(' . $node . '),' . $s . ')';
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
