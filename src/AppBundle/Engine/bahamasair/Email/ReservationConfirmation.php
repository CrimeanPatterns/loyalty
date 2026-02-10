<?php

namespace AwardWallet\Engine\bahamasair\Email;

use AwardWallet\Schema\Parser\Email\Email;

// TODO: merge with parsers precision/ReservationConfirmation, winair/TicketConfirmation (in favor of bahamasair/ReservationConfirmation)

class ReservationConfirmation extends \TAccountChecker
{
    public $mailFiles = "bahamasair/it-281953301.eml, bahamasair/it-282546956.eml, bahamasair/it-285624800.eml, bahamasair/it-295262103.eml";

    public $providerCode;
    public static $detectProviders = [
        'bahamasair' => [
            'from' => [
                '@bahamasair.com',
            ],
            'body' => [
                '//a[contains(@href,".bahamasair.com")]',
                'Thank you for making Bahamasair your airline',
                'Sent by Bahamasair Holdings Ltd',
            ],
        ],
        'tanzania' => [
            'from' => ['airtanzania.co.tz'],
            'body' => [
                '//a[contains(@href,"airtanzania.crane.aero")]',
                '@airtanzania.co.tz',
                'Sent by Air Tanzania',
            ],
        ],
    ];
    public $detectSubject = [
        'Reservation Confirmation',
        'Ticket Confirmation',
        'Check-in Information Mail',
        'Online Check-in Information',
    ];

    public $lang = '';
    public static $dictionary = [
        'en' => [
            'confNumber'     => ['Your PNR No'],
            'Departure Port' => ['Departure Port'],
            'Bundle'         => ['Bundle', 'Gate'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@bahamasair.com') !== false;
    }

    public static function getEmailProviders()
    {
        return array_keys(self::$detectProviders);
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (empty($headers['from']) || empty($headers['subject'])) {
            return false;
        }

        foreach (self::$detectProviders as $code => $detect) {
            if (empty($detect['from'])) {
                continue;
            }

            $byFrom = false;

            foreach ($detect['from'] as $dfrom) {
                if (stripos($headers['from'], $dfrom) !== false) {
                    $byFrom = true;

                    break;
                }
            }

            if ($byFrom == false) {
                continue;
            }

            foreach ($this->detectSubject as $dSubject) {
                if (stripos($headers['subject'], $dSubject) !== false) {
                    $this->providerCode = $code;

                    return true;
                }
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        return $this->assignLang() && $this->getProviderByBody() !== null;
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();
        $this->parseEmailHtml($email);

        if (empty($this->providerCode)) {
            $this->providerCode = $this->getProvider($parser);
        }

        if (!empty($this->providerCode)) {
            $email->setProviderCode($this->providerCode);
        }
        $class = explode('\\', __CLASS__);
        $email->setType(end($class) . ucfirst($this->lang));

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

    private function getProviderByBody(): ?string
    {
        foreach (self::$detectProviders as $code => $detect) {
            if (empty($detect['body'])) {
                continue;
            }

            foreach ($detect['body'] as $search) {
                if ((stripos($search, '//') === 0 && $this->http->XPath->query($search)->length > 0)
                    || (stripos($search, '//') === false && strpos($this->http->Response['body'], $search) !== false)
                ) {
                    $this->providerCode = $code;

                    return $code;
                }
            }
        }

        return null;
    }

    private function getProvider(\PlancakeEmailParser $parser): ?string
    {
        $this->detectEmailByHeaders(['from' => $parser->getCleanFrom(), 'subject' => $parser->getSubject()]);

        if (!empty($this->providerCode)) {
            return $this->providerCode;
        }

        return $this->getProviderByBody();
    }

    private function parseEmailHtml(Email $email): void
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'’[:alpha:] ]*[[:alpha:]]', // Mr. Hao-Li Huang
            'eTicket'       => '\d{3}(?: | ?- ?)?\d{5,}(?: | ?- ?)?\d{1,3}', // 075-2345005149-02  |  0167544038003-004
        ];

        $f = $email->add()->flight();

        // General
        $f->general()
            ->confirmation($this->http->FindSingleNode("//tr[{$this->eq($this->t('Your PNR No'))}]/following-sibling::tr[normalize-space()][1]/descendant::text()[normalize-space()][1]",
                null, true, "/^\s*([A-Z\d]{5,7})\s*$/"))
        ;

        $tickets = $travellers = [];

        // Segments
        $xpath = "//tr[*[normalize-space()][1][{$this->eq($this->t('Flight Number'))}] and *[normalize-space()][2][{$this->eq($this->t('Departure Port'))}]]/following::text()[normalize-space()][1]/ancestor::*[not({$this->contains($this->t('Your PNR No'))})][last()]//tr[count(*[normalize-space()]) > 3][not({$this->contains($this->t('Departure Port'))})]";
        $nodes = $this->http->XPath->query($xpath);
//        $this->logger->debug("[XPATH-flight]: " . $xpath);

        foreach ($nodes as $key => $root) {
            $s = $f->addSegment();

            // Airline
            $node = $this->http->FindSingleNode("td[1]", $root);

            if (preg_match("/^\s*(?<al>[A-Z][A-Z\d]|[A-Z\d][A-Z])[-\s]*(?<fn>\d{1,5})\s*$/", $node, $m)) {
                $s->airline()->name($m['al'])->number($m['fn']);
            }

            // Departure
            $s->departure()
                ->code($this->http->FindSingleNode("td[2]", $root))
                ->date(strtotime($this->http->FindSingleNode("td[4]", $root)))
            ;

            // Arrival
            $s->arrival()
                ->code($this->http->FindSingleNode("td[3]", $root))
            ;
            $date = strtotime($this->http->FindSingleNode("td[5]", $root));

            if (empty($date) && !empty($this->http->FindSingleNode("//text()[{$this->eq($this->t('Departure Date'))}]/following::text()[normalize-space()][1][{$this->eq($this->t('Bundle'))}]"))) {
                $s->arrival()
                    ->noDate();
            } else {
                $s->arrival()
                    ->date($date);
            }

            // Extra
            $key++;
            $travellersRows = $this->http->XPath->query("//text()[{$this->eq('Flight-' . $key)}]/ancestor::table[ descendant::text()[normalize-space()][2] ][1]/following::text()[normalize-space()][1]/ancestor::tr[ *[2][{$this->eq($this->t('Surnmame'))}] and *[4][{$this->eq($this->t('Ticket'))}] ]/ancestor-or-self::tr[ following-sibling::tr[normalize-space()] ][1]/following-sibling::tr[normalize-space()]");

            foreach ($travellersRows as $tRow) {
                $passengerName = $this->normalizeTraveller(implode(' ', $this->http->FindNodes("descendant-or-self::tr[ *[4] ][1]/*[position()<4][normalize-space()]", $tRow)));

                if (!preg_match("/^{$patterns['travellerName']}$/u", $passengerName)) {
                    $passengerName = null;
                }

                $ticket = $this->http->FindSingleNode("descendant-or-self::tr[ *[4] ][1]/*[4]", $tRow, true, "/^{$patterns['eTicket']}$/");
                $seat = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Seat'), "translate(.,':','')")}]/following::text()[normalize-space()][1]", $tRow, true, "/^\d+[A-Z]$/");
                $meal = $this->http->FindSingleNode("descendant::text()[{$this->eq($this->t('Meal'), "translate(.,':','')")}]/ancestor::td[1]", $tRow, true, "/^{$this->opt($this->t('Meal'))}\s*[:]+\s*(.+)$/");

                if (!in_array($passengerName, $travellers)) {
                    $f->general()->traveller($passengerName, true);
                    $travellers[] = $passengerName;
                }

                if ($ticket && !in_array($ticket, $tickets)) {
                    $f->issued()->ticket($ticket, false, $passengerName);
                    $tickets[] = $ticket;
                }

                if ($seat) {
                    $s->extra()->seat($seat, false, false, $passengerName);
                }

                $s->extra()->meal($meal, false, true);
            }
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['Departure Port'])) {
                continue;
            }

            if ($this->http->XPath->query("//*[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//*[{$this->contains($phrases['Departure Port'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t($s)
    {
        if (!isset($this->lang, self::$dictionary[$this->lang], self::$dictionary[$this->lang][$s])) {
            return $s;
        }

        return self::$dictionary[$this->lang][$s];
    }

    // additional methods

    private function contains($field, $text = 'normalize-space(.)'): string
    {
        $field = (array) $field;

        if (count($field) == 0) {
            return 'false()';
        }

        return '(' . implode(' or ', array_map(function ($s) use ($text) {
            return 'contains(' . $text . ',"' . $s . '")';
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

    private function opt($field, $delimiter = '/'): string
    {
        $field = (array) $field;

        if (empty($field)) {
            $field = ['false'];
        }

        return '(?:' . implode("|", array_map(function ($s) use ($delimiter) {
            return str_replace(' ', '\s+', preg_quote($s, $delimiter));
        }, $field)) . ')';
    }

    private function normalizeTraveller(?string $s): ?string
    {
        if (empty($s)) {
            return null;
        }

        $namePrefixes = '(?:MRS\.\/MS|Child|MSTR|MISS|MRS|MR|MS|DR)';

        return preg_replace([
            "/^(.{2,}?)\s+(?:{$namePrefixes}[.\s]*)+$/i",
            "/^(?:{$namePrefixes}[.\s]+)+(.{2,})$/i",
        ], [
            '$1',
            '$1',
        ], mb_strtoupper($s));
    }
}
