<?php

namespace AwardWallet\Engine\aviancataca\Email;

use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;

class BookingConfirmed extends \TAccountChecker
{
    public $mailFiles = "aviancataca/it-646662141.eml, aviancataca/it-697224838-es.eml, aviancataca/it-711161878.eml";
    public $subjects = [
        '/(?:^|:\s*)Tu reserva está confirmada$/iu', // es
        '/(?:^|:\s*)(?:Reserva confirmada|Booking confirmed)\s+([A-Z\d]{6})$/iu', // es, en
    ];

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'confNumber'         => ['Código de reserva'],
            'status'             => ['Estado'],
            'hello'              => 'Olá',
            'Passengers (seats)' => 'Passageiros (assentos)',
            //'lifemiles number'   => '',
            'Transaction total'  => 'Total da transação',
            'You earned'         => 'Acumula',
            // 'lifemiles' => '',
            'per'            => 'por',
            'Ticket Adult'   => 'Bilhetes Adulto',
            'Ticket number:' => 'TKT No.:',
            'seat'           => 'Assento',
            'Origin'         => 'Ida',
            'Destination'    => 'Volta',
        ],
        'es' => [
            'confNumber'         => ['Código de reserva', 'Código de reserva:', 'Código de reserva :'],
            'status'             => ['Estado', 'Estado:', 'Estado :'],
            'hello'              => 'Hola',
            'Passengers (seats)' => 'Pasajeros (asientos)',
            'lifemiles number'   => 'Número lifemiles',
            'Transaction total'  => 'Total de la transacción',
            'You earned'         => 'Acumula',
            // 'lifemiles' => '',
            'per'            => 'por cada',
            'Ticket Adult'   => 'Tiquetes Adulto',
            'Ticket number:' => 'Número de tiquete:',
            'seat'           => 'Asiento',
            'Origin'         => 'Ida',
            'Destination'    => 'Vuelta',
        ],
        'en' => [
            'confNumber' => ['Booking code', 'Booking code:', 'Booking code :'],
            'status'     => ['Status', 'Status:', 'Status :'],
            'hello'      => 'Hello',
            // 'Passengers (seats)' => '',
            // 'lifemiles number' => '',
            // 'Transaction total' => '',
            // 'You earned' => '',
            // 'lifemiles' => '',
            // 'per' => '',
            'Ticket Adult'   => ['Ticket Adult', 'Tickets Adult'],
            'Ticket number:' => ['Ticket number:', 'TKT No.:'],
            // 'seat' => '',
            'Origin'      => ['Origin', 'Departure'],
            'Destination' => 'Destination',
        ],
    ];

    public function detectEmailByHeaders(array $headers)
    {
        if (array_key_exists('from', $headers) && array_key_exists('subject', $headers)
            && stripos($headers['from'], '@comms.avianca.com') !== false
        ) {
            foreach ($this->subjects as $subject) {
                if (preg_match($subject, $headers['subject'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->detectEmailFromProvider(rtrim($parser->getHeader('from'), '> ')) !== true
            && $this->http->XPath->query('//a[contains(@href,".avianca.com/") or contains(@href,"booking.avianca.com")]')->length === 0
            && $this->http->XPath->query('//text()[starts-with(normalize-space(),"© Avianca")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]comms.avianca.com$/', $from) > 0;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->ParseFlight($email);

        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

        return $email;
    }

    public function ParseFlight(Email $email): void
    {
        $patterns = [
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02    |    0167544038003-004
        ];

        $f = $email->add()->flight();

        $this->logger->debug("//text()[{$this->eq($this->t('status'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[string-length()>2][2]");

        $f->general()
            ->confirmation($this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[1]", null, true, "/^[A-Z\d]{5,7}$/"))
            ->status($this->http->FindSingleNode("//text()[{$this->eq($this->t('status'))}]/ancestor::tr[1]/following::tr[1]/descendant::td[string-length()>3][2]", null, true, "/^[[:alpha:]]+$/u"))
        ;

        $travellers = array_filter($this->http->FindNodes("//text()[{$this->eq($this->t('Passengers (seats)'))}]/ancestor::tr[2]/following-sibling::tr/td[1]", null, "/^{$patterns['travellerName']}$/u"));
        $areNamesFull = true;

        if (count($travellers) === 0) {
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('hello'))}]", null, "/^{$this->opt($this->t('hello'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        $f->general()->travellers($travellers, $areNamesFull);

        $accounts = [];
        $accountRows = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Passengers (seats)'))}] and *[2][{$this->eq($this->t('lifemiles number'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($accountRows as $accRow) {
            $passengerName = $this->http->FindSingleNode("*[1]", $accRow, true, "/^{$patterns['travellerName']}$/u");
            $account = $this->http->FindSingleNode("*[2]", $accRow, true, "/^[A-Z\d]{7,}$/");

            if ($account && !in_array($account, $accounts)) {
                $f->program()->account($account, false, $passengerName);
                $accounts[] = $account;
            }
        }

        $ticketItems = $this->http->XPath->query("//text()[{$this->starts($this->t('Ticket number:'))}]");

        foreach ($ticketItems as $tItem) {
            $passengerName = $this->http->FindSingleNode("ancestor::*[ preceding-sibling::*[normalize-space()] ][1]/preceding-sibling::*[normalize-space()]", $tItem, true, "/^{$patterns['travellerName']}$/u");
            $ticket = $this->http->FindSingleNode(".", $tItem, true, "/{$this->opt($this->t('Ticket number:'))}\s*({$patterns['eTicket']})$/");

            if ($ticket) {
                $f->issued()->ticket($ticket, false, $passengerName);
            }
        }

        $earned = $this->http->FindSingleNode("//text()[{$this->starts($this->t('You earned'))}]", null, true, "/{$this->opt($this->t('You earned'))}\s*(\d+\s*{$this->opt($this->t('lifemiles'))}.*)/");

        if (!empty($earned)) {
            $f->setEarnedAwards($earned);
        }

        $cabinVal = $this->http->FindSingleNode("//img[contains(@src, 'seat.png')]/ancestor::td[1]");
        $cabin = preg_match("/^(.+?)\s*{$this->opt($this->t('seat'))}$/i", $cabinVal, $m)
            || preg_match("/^{$this->opt($this->t('seat'))}\s*(.+)$/i", $cabinVal, $m)
        ? $m[1] : null;

        $nodes = $this->http->XPath->query("//img[contains(@src, 'journey-departure') or contains(@src, 'journey-return')]/ancestor::tr[1]/following-sibling::tr[1]/ancestor::table[2]");
        $pos = $this->http->XPath->query("//tr[ *[1][{$this->eq($this->t('Passengers (seats)'))}] and *[2][{$this->eq($this->t('lifemiles number'))}] ]")->length > 0 ? 3 : 2;

        foreach ($nodes as $root) {
            $date = strtotime($this->normalizeDate($this->http->FindSingleNode("descendant::img[1]/following::text()[string-length()>5][2]", $root)));

            $passengerNameArray = $this->http->FindNodes("//tr[ *[1][{$this->eq($this->t('Passengers (seats)'))}] and *[{$this->eq($this->t('Origin'))} or {$this->eq($this->t('Destination'))}] ]/following-sibling::tr[normalize-space()]/*[1]", null, "/^{$patterns['travellerName']}$/u");
            $seatArray = $this->http->FindNodes("//tr[ *[1][{$this->eq($this->t('Passengers (seats)'))}] and *[{$this->eq($this->t('Origin'))} or {$this->eq($this->t('Destination'))}] ]/following-sibling::tr[normalize-space()]/*[{$pos}]");
            $pos++;

            $segNodes = $this->http->XPath->query("./descendant::img[contains(@src, 'airplanemode_active')]", $root);

            foreach ($segNodes as $key => $segRoot) {
                $s = $f->addSegment();

                $flightAirline = $this->http->FindSingleNode("./ancestor::tr[1]/preceding::text()[normalize-space()][1]", $segRoot);

                if (preg_match("/^(?<aN>(?:[A-Z][A-Z\d]|[A-Z\d][A-Z]))\s*(?<fN>\d{1,4})$/", $flightAirline, $m)) {
                    $s->airline()
                        ->name($m['aN'])
                        ->number($m['fN']);
                }

                $duration = $this->http->FindSingleNode("./ancestor::tr[1]/following::text()[normalize-space()][1]", $segRoot, true, "/^(\d+(?:h|m).*)/");

                if (!empty($duration)) {
                    $s->setDuration($duration);
                }

                $depInfo = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/descendant::td[1]/descendant::text()[normalize-space()]", $segRoot));

                if (preg_match("/^(?<depTime>{$patterns['time']}).*\n(?<depName>.{2,})\n(?<depCode>[A-Z]{3})$/", $depInfo, $m)) {
                    $s->departure()
                        ->name($m['depName'])
                        ->date(strtotime($m['depTime'], $date))
                        ->code($m['depCode']);
                }

                $arrInfo = implode("\n", $this->http->FindNodes("./ancestor::tr[1]/descendant::td[last()]/descendant::text()[normalize-space()]", $segRoot));

                if (preg_match("/^(?<arrTime>{$patterns['time']}).*\n(?<arrName>.{2,})\n(?<arrCode>[A-Z]{3})$/", $arrInfo, $m)) {
                    $s->arrival()
                        ->name($m['arrName'])
                        ->date(strtotime($m['arrTime'], $date))
                        ->code($m['arrCode']);
                }

                foreach ($seatArray as $i => $seatValue) {
                    $seats = preg_split('/\s*\/\s*/', $seatValue);

                    if (!empty($seats[$key]) && preg_match("/\d+[A-Z]/", $seats[$key]) && !in_array($seats[$key], $s->getSeats())) {
                        $passengerName = count($passengerNameArray) === count($seatArray) && !empty($passengerNameArray[$i]) ? $passengerNameArray[$i] : null;
                        $s->extra()->seat($seats[$key], false, false, $passengerName);
                    }
                }

                if (!empty($cabin)) {
                    $s->extra()
                        ->cabin($cabin);
                }
            }
        }

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->eq($this->t('Transaction total'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<currency>[^\-\d)(]+?)[ ]*(?<amount>\d[,.‘\'\d ]*)$/u', $totalPrice, $matches)
            || preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)
        ) {
            // 4182.30    |    COP 1.594.460    |    3,204.65 USD
            if (!array_key_exists('currency', $matches)) {
                $matches['currency'] = '';
            }

            $currency = empty($matches['currency']) ? $this->http->FindSingleNode("//text()[{$this->starts($this->t('You earned'))} and {$this->contains($this->t('per'))}]", null, true, "/{$this->opt($this->t('per'))}\s*([A-Z]{3})\b/") : $matches['currency'];
            $currencyCode = preg_match('/^[A-Z]{3}$/', $currency) ? $currency : null;
            $f->price()->currency($currency)->total(PriceHelper::parse($matches['amount'], $currencyCode));

            $baseFare = $this->http->FindSingleNode("//tr[ count(*[normalize-space()])=2 and *[normalize-space()][1][{$this->contains($this->t('Ticket Adult'))}] ]/*[normalize-space()][2]", null, true, '/^.*\d.*$/');

            if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $baseFare, $m)
                || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $baseFare, $m)
                || !empty($matches['currency']) && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $baseFare, $m)
            ) {
                $f->price()->cost(PriceHelper::parse($m['amount'], $currencyCode));
            }

            $feeRows = $this->http->XPath->query("//tr[ preceding-sibling::tr/*[normalize-space()][1][{$this->contains($this->t('Ticket Adult'))}] and following-sibling::tr/*[normalize-space()][1][{$this->eq($this->t('Transaction total'))}] ]");

            foreach ($feeRows as $feeRow) {
                $feeCharge = $this->http->FindSingleNode('*[2]', $feeRow, true, '/^(.*?\d.*?)\s*(?:\(|$)/');

                if (preg_match('/^(?<amount>\d[,.‘\'\d ]*)$/u', $feeCharge, $m)
                    || !empty($matches['currency']) && preg_match('/^(?:' . preg_quote($matches['currency'], '/') . ')?[ ]*(?<amount>\d[,.‘\'\d ]*?)$/u', $feeCharge, $m)
                    || !empty($matches['currency']) && preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?:' . preg_quote($matches['currency'], '/') . ')?$/u', $feeCharge, $m)
                ) {
                    $feeName = $this->http->FindSingleNode('*[1]', $feeRow, true, '/^(.+?)[\s:：]*$/u');
                    $f->price()->fee($feeName, PriceHelper::parse($m['amount'], $currencyCode));
                }
            }
        }
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return count(self::$dictionary);
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang)) {
                continue;
            }

            if (!empty($phrases['confNumber']) && $this->http->XPath->query("//node()[{$this->eq($phrases['Ticket Adult'])}]")->length > 0
                || !empty($phrases['status']) && $this->http->XPath->query("//node()[{$this->eq($phrases['Destination'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
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

    private function opt($field): string
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return str_replace(' ', '\s+', preg_quote($s, '/'));
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})(?:\s+de)?\s+([[:alpha:]]{3,})[.\s]+(?:de\s+)?(\d{4})$/u', $text, $m)) {
            // Viernes, 12 Jul. 2024
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        }

        if (isset($day, $month, $year)) {
            if (preg_match('/^\s*(\d{1,2})\s*$/', $month, $m)) {
                return $m[1] . '/' . $day . ($year ? '/' . $year : '');
            }

            if (($monthNew = MonthTranslate::translate($month, $this->lang)) !== false) {
                $month = $monthNew;
            }

            return $day . ' ' . $month . ($year ? ' ' . $year : '');
        }

        return null;
    }
}
