<?php

namespace AwardWallet\Engine\easternair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Common\Parser\Util\PriceHelper;
use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Engine\WeekTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class AirBooking extends \TAccountChecker
{
    public $mailFiles = "easternair/it-178864829.eml, easternair/it-377949094-taag-pt.eml, easternair/it-378823936.eml, easternair/it-706547666-flyone.eml, easternair/it-701076863-flyone.eml";

    public $lang = '';

    public static $dictionary = [
        'pt' => [
            'confNumber'        => ['Referência de reserva'],
            'Booking status:'   => 'Status',
            'statusVariants'    => ['Confirmado'],
            'Dear'              => 'Querido',
            'Flight'            => 'Voo',
            'Passenger details' => 'Detalhes do passageiro',
            'Flight number'     => 'Voar',
            'Seat'              => 'Assento',
            'Total amount'      => 'Valor total',
        ],
        'en' => [
            'confNumber' => ['Booking reference:', 'Ticket code:'],
            // 'Booking status:' => '',
            'statusVariants' => ['Confirmed'],
            // 'Dear' => '',
            // 'Flight' => '',
            // 'Passenger details' => '',
            // 'Flight number' => '',
            // 'Seat' => '',
            // 'Total amount' => '',
        ],
    ];

    private $subjects = [
        'en' => ['Booking Confirmed', 'Booking Updated', 'Before Flight', 'Check-In open for your reservation'],
    ];

    private $providerCode = '';

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]easternairways\.com$/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider(rtrim($headers['from'], '> ')) !== true) {
            return false;
        }

        foreach ($this->subjects as $phrases) {
            foreach ((array) $phrases as $phrase) {
                if (is_string($phrase) && array_key_exists('subject', $headers) && stripos($headers['subject'], $phrase) !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignProvider($parser->getCleanFrom(), $parser->getSubject()) && $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignProvider($parser->getCleanFrom(), $parser->getSubject());
        $this->assignLang();
        $email->setProviderCode($this->providerCode);
        $email->setType('AirBooking' . ucfirst($this->lang));

        $emailDate = strtotime($parser->getDate());
        $year = date('Y', $emailDate ? $emailDate : null);
        $this->logger->debug('Email Year: ' . $year);

        $xpathTime = 'contains(translate(.,"0123456789：","∆∆∆∆∆∆∆∆∆∆:"),"∆:∆∆")';
        $xpathNoEmpty = '(normalize-space() or descendant::img)';

        $patterns = [
            'date'          => '\b[-[:alpha:]]+\s*,\s*(?:\d{1,2}\s+[[:alpha:]]+\s+\d{4}|[[:alpha:]]+\s+\d{1,2})\b', // Saturday, Aug 03    |    sexta-feira, 19 mai 2023
            'time'          => '\d{1,2}[:：]\d{2}(?:[ ]*[AaPp](?:\.[ ]*)?[Mm]\.?)?', // 4:19PM    |    2:00 p. m.
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
        ];

        $f = $email->add()->flight();

        $confirmation = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('confNumber'))}] and *[normalize-space()][2][{$this->eq($this->t('Booking status:'))}] ]/following-sibling::tr[normalize-space()][1]/*[1]", null, true, '/^[A-Z\d]{5,8}$/');
        $confirmationTitle = $this->http->FindSingleNode("//tr[ *[normalize-space()][2][{$this->eq($this->t('Booking status:'))}] and following-sibling::tr[normalize-space()][1]/*[1][normalize-space()] ]/*[1][{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');

        if (empty($confirmation)) {
            // it-178864829.eml
            $confirmation = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,8}$/');
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->eq($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:：]*$/u');
        }
        $f->general()->confirmation($confirmation, $confirmationTitle);

        $status = $this->http->FindSingleNode("//tr[ *[1][{$this->eq($this->t('confNumber'))}] and *[3][{$this->eq($this->t('Booking status:'))}] ]/following-sibling::tr[normalize-space()][1]/*[3]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/iu")
            ?? $this->http->FindSingleNode("//text()[{$this->eq($this->t('Booking status:'))}]/following::text()[normalize-space()][1]", null, true, "/^{$this->opt($this->t('statusVariants'))}$/iu");

        if ($status) {
            $f->general()->status($status);
        }

        $segments = $this->http->XPath->query("//*[count(*[normalize-space()])=2 and count(*[{$xpathTime}])=2]");

        if ($segments->length === 0) {
            // it-377949094-taag-pt.eml
            $segments = $this->http->XPath->query("//*[*[{$xpathNoEmpty}][1][{$xpathTime}] and not(*[{$xpathNoEmpty}][2][{$xpathTime}]) and *[{$xpathNoEmpty}][3][{$xpathTime}] and count(*[{$xpathNoEmpty}])=3]");
        }

        foreach ($segments as $root) {
            $s = $f->addSegment();

            $date = $dateVal = null;
            $preRoots = $this->http->XPath->query("preceding::tr[not(.//tr[normalize-space()]) and normalize-space()][1]", $root);
            $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;

            while ($preRoot) {
                $dateVal = $this->http->FindSingleNode(".", $preRoot, true, "/^(?:{$this->opt($this->t('Flight'))}\s+-\s+)?({$patterns['date']})$/iu");

                if ($dateVal) {
                    break;
                }

                $preRoots = $this->http->XPath->query("preceding::tr[not(.//tr[normalize-space()]) and normalize-space()][1]", $preRoot);
                $preRoot = $preRoots->length > 0 ? $preRoots->item(0) : null;
            }

            if (preg_match("/\b\d{4}$/", $dateVal)) {
                $date = strtotime($this->normalizeDate($dateVal));
            } elseif (preg_match("/^(?<wday>[-[:alpha:]]+)\s*,\s*(?<date>[[:alpha:]]+\s+\d{1,2}|\d{1,2}\s+[[:alpha:]]+)$/u", $dateVal, $m)) {
                // it-178864829.eml
                $weekDateNumber = WeekTranslate::number1($m['wday']);
                $dateNormal = $this->normalizeDate($m['date']);
                $date = EmailDateHelper::parseDateUsingWeekDay($dateNormal . ' ' . $year, $weekDateNumber);
            }

            $column1 = implode("\n", $this->http->FindNodes("../*/*[{$xpathNoEmpty}][1]", $root));
            $column2 = implode("\n", $this->http->FindNodes("../*/*[{$xpathNoEmpty}][2]", $root));
            $column3 = implode("\n", $this->http->FindNodes("../*/*[{$xpathNoEmpty}][3]", $root));

            if (preg_match("/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])[- ]*(?<number>\d+)$/m", $column2, $m)) {
                $s->airline()->name($m['name'])->number($m['number']);
                $flightVariants = [$m['name'] . $m['number'], $m['name'] . ' ' . $m['number']];

                $seatsRows = $this->http->XPath->query("//*[ *[normalize-space()][1][{$this->starts($this->t('Flight number'))} and descendant::node()[{$this->eq($flightVariants)}] and following-sibling::*[normalize-space()][1][{$this->starts($this->t('Seat'))}]] ]");

                foreach ($seatsRows as $seatRow) {
                    $passengerName = $this->http->FindSingleNode("ancestor::table[1]/preceding-sibling::table[normalize-space()][1]", $seatRow, true, "/^{$patterns['travellerName']}$/u");
                    $seat = $this->http->FindSingleNode("*[{$this->starts($this->t('Seat'))}]", $seatRow, true, "/^{$this->opt($this->t('Seat'))}\s*[:]+\s*(\d+[A-Z])$/");

                    if ($seat) {
                        $s->extra()->seat($seat, false, false, $passengerName);
                    }
                }
            }

            $timeDep = $this->http->FindSingleNode("*[normalize-space()][1]", $root, true, "/^{$patterns['time']}/");
            $timeArr = $this->http->FindSingleNode("*[{$xpathNoEmpty}][3]", $root, true, "/^{$patterns['time']}/");

            if ($date && $timeDep) {
                $s->departure()->date(strtotime($timeDep, $date));
            }

            if ($date && $timeArr) {
                $s->arrival()->date(strtotime($timeArr, $date));
            }

            $pattern1 = "/(?:^|\n){$patterns['time']}.*\n([A-Z]{3})(?:\n|$)/";
            $codeDep = preg_match($pattern1, $column1, $m) ? $m[1] : null;
            $codeArr = preg_match($pattern1, $column3, $m) ? $m[1] : null;
            $s->departure()->code($codeDep);
            $s->arrival()->code($codeArr);

            $pattern1 = "/^(.{2,})\n{$patterns['time']}/";
            $pattern2 = "/^{$patterns['time']}[\s\S]*\n(.{2,})$/";
            $cityDep = preg_match($pattern1, $column1, $m) || preg_match($pattern2, $column1, $m) ? $m[1] : null;
            $cityArr = preg_match($pattern1, $column3, $m) || preg_match($pattern2, $column3, $m) ? $m[1] : null;

            if ($cityDep && $cityDep !== $codeDep) {
                $s->departure()->name($cityDep);
            }

            if ($cityArr && $cityArr !== $codeArr) {
                $s->arrival()->name($cityArr);
            }

            $duration = preg_match("/^((?:\d+[ ]*[hm][ ]*)+)$/im", $column2, $m) ? $m[1] : null;
            $s->extra()->duration($duration, false, true);
        }

        $travellers = array_filter($this->http->FindNodes("//*[{$this->eq($this->t('Passenger details'))}]/following::tr[not(.//tr[normalize-space()]) and normalize-space() and following::text()[normalize-space()][1][{$this->starts($this->t('Flight number'))}] and not({$this->contains($this->t('Flight number'))})]", null, "/^{$patterns['travellerName']}$/u"));
        $areNamesFull = true;

        if (count($travellers) === 0) {
            // it-706547666-flyone.eml
            $travellerNames = array_filter($this->http->FindNodes("//text()[{$this->starts($this->t('Dear'))}]", null, "/^{$this->opt($this->t('Dear'))}[,\s]+({$patterns['travellerName']})(?:\s*[,;:!?]|$)/u"));

            if (count(array_unique($travellerNames)) === 1) {
                $traveller = array_shift($travellerNames);
                $travellers = [$traveller];
                $areNamesFull = null;
            }
        }

        if (count($travellers) > 0) {
            $f->general()->travellers($travellers, $areNamesFull);
        }

        $totalPrice = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Total amount'))}]", null, true, "/^{$this->opt($this->t('Total amount'))}[:\s]+(.*\d.*)$/");

        if (preg_match('/^(?<amount>\d[,.‘\'\d ]*?)[ ]*(?<currency>[^\-\d)(]+)$/u', $totalPrice, $matches)) {
            // 9,271.04 BRL
            $currencyCode = preg_match('/^[A-Z]{3}$/', $matches['currency']) ? $matches['currency'] : null;
            $f->price()->currency($matches['currency'])->total(PriceHelper::parse($matches['amount'], $currencyCode));
        }

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

    public static function getEmailProviders()
    {
        return ['flyone', 'taag', 'easternair'];
    }

    private function assignProvider(?string $from, string $subject): bool
    {
        if (preg_match('/[.@]flyone\.eu$/i', $from) > 0
            || strpos($subject, 'FLYONE') !== false
            || $this->http->XPath->query('//a[contains(@href,"//flyone.eu/") or contains(@href,".flyone.eu/") or contains(@href,"www.flyone.eu") or contains(@href,"bookings.flyone.eu")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for choosing FLYONE")]')->length > 0
        ) {
            $this->providerCode = 'flyone';

            return true;
        }

        if (preg_match('/[.@]flytaag\.com$/i', $from) > 0
            || $this->http->XPath->query('//a[contains(@href,".taag.com/") or contains(@href,"www.taag.com")]')->length > 0
            || $this->http->XPath->query('//text()[starts-with(normalize-space(),"© Taag")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Obrigado por confiar à TAAG")]')->length > 0
        ) {
            $this->providerCode = 'taag';

            return true;
        }

        if (preg_match('/[.@]easternairways\.com$/i', $from) > 0
            || $this->http->XPath->query('//a[contains(@href,".easternairways.com/") or contains(@href,"www.easternairways.com")]')->length > 0
            || $this->http->XPath->query('//text()[starts-with(normalize-space(),"© Eastern Airways")]')->length > 0
            || $this->http->XPath->query('//*[contains(normalize-space(),"Thank you for booking with Eastern Airways") or contains(normalize-space(),"Thanks for booking with Eastern Airways")]')->length > 0
        ) {
            $this->providerCode = 'easternair';

            return true;
        }

        return false;
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber'])) {
                continue;
            }

            if ($this->http->XPath->query("//text()[{$this->eq($phrases['confNumber'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase, string $lang = '')
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return $phrase;
        }

        if ($lang === '') {
            $lang = $this->lang;
        }

        if (empty(self::$dictionary[$lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$lang][$phrase];
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

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     *
     * @param string|null $text Unformatted string with date
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/\b(\d{1,2})\s+([[:alpha:]]+)\s+(\d{4})$/u', $text, $m)) {
            // Tuesday, 25 Apr 2023
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^([[:alpha:]]+)\s+(\d{1,2})$/u', $text, $m)) {
            // Aug 03
            $month = $m[1];
            $day = $m[2];
            $year = '';
        } elseif (preg_match('/^(\d{1,2})\s+([[:alpha:]]+)$/u', $text, $m)) {
            // 03 Aug
            $day = $m[1];
            $month = $m[2];
            $year = '';
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
