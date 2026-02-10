<?php

namespace AwardWallet\Engine\alaskaair\Email;

use AwardWallet\Schema\Parser\Email\Email;

class CanceledReservation extends \TAccountChecker
{
    public $mailFiles = "alaskaair/it-56265187.eml, alaskaair/it-56497726.eml, alaskaair/it-56477739.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Traveler name'       => ['Traveler name'],
            'Confirmation code'   => ['Confirmation code'],
            'cancellationPhrases' => [
                'Purchased reservation cancellation',
                'Purchased reservation cancelation',
                'The following reservation has been cancelled',
                'The following reservation has been canceled',
            ],
            'statusVariants' => ['cancelled and refunded', 'canceled and refunded', 'cancelled', 'canceled'],
        ],
    ];

    private $subjects = [
        'en' => [
            'Cancelled Reservation:', 'Cancelled Reservation :',
            'Canceled Reservation:', 'Canceled Reservation :',
        ],
    ];

    private $detectors = [
        'en' => ['ITINERARY', 'Expiration date', 'Please keep this email until the ticket has been exchanged'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[-.@]alaskaair\.com/i', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if ($this->detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".alaskaair.com/") or contains(@href,"ifly.alaskaair.com")]')->length === 0
            && $this->http->XPath->query('//node()['
                . 'contains(normalize-space(),"Thank you for choosing Alaska Airlines")'
                . ' or contains(normalize-space(),"We hope to see you another time, Alaska Airlines")'
                . ' or contains(normalize-space(),"Alaska Airlines. All rights reserved")'
                . ']')->length === 0
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

        $this->parseFlight($email);
        $email->setType('CanceledReservation' . ucfirst($this->lang));

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

    private function parseFlight(Email $email): void
    {
        $f = $email->add()->flight();

        if ($this->http->XPath->query("//text()[{$this->contains($this->t('cancellationPhrases'))}]")->length > 0) {
            $f->general()->cancelled();
        }

        $status = $this->http->FindSingleNode("//text()[{$this->contains($this->t('reservation has been'))}]", null, true, "/{$this->opt($this->t('reservation has been'))}\s+({$this->opt($this->t('statusVariants'))})(?:\W|$)/");
        $f->general()->status($status);

        $travellers = [];
        $ticketNumbers = [];

        $travelerRows = $this->http->XPath->query("//tr[ td[1][{$this->starts($this->t('Traveler name'))}] and td[2][{$this->starts($this->t('Ticket number'))}] ]/following-sibling::tr[normalize-space()]");

        foreach ($travelerRows as $tRow) {
            $travelerName = $this->http->FindSingleNode('td[1]', $tRow, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');

            if ($travelerName) {
                $travellers[] = $travelerName;
            }
            $ticketNumber = $this->http->FindSingleNode('td[2]', $tRow, true, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (count($travellers)) {
            $f->general()->travellers($travellers);
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets($ticketNumbers, false);
        }

        $confirmation = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Confirmation code'))}]");

        if (preg_match("/^({$this->opt($this->t('Confirmation code'))})[: ]+([A-Z\d]{5,})$/", $confirmation, $m)) {
            $f->general()->confirmation($m[2], $m[1]);
        } elseif (preg_match("/^{$this->opt($this->t('Confirmation code'))}[: ]*$/", $confirmation)) {
            $f->general()->noConfirmation();
        }

        $segments = $this->http->XPath->query("//tr[not(.//tr) and descendant::img and contains(.,'/') and string-length(normalize-space())>6 and string-length(normalize-space())<14]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            if (preg_match('/^([A-Z]{3})\s*\/\s*([A-Z]{3})$/', $this->http->FindSingleNode('.', $segment), $m)) {
                // PDX / TUS
                $s->departure()->code($m[1]);
                $s->arrival()->code($m[2]);
            }

            $airports = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][1]', $segment);

            if (preg_match('/^(.{3,})\s*\/\s*(.{3,})$/', $airports, $m)) {
                // Portland, OR / Tucson
                $s->departure()->name($m[1]);
                $s->arrival()->name($m[2]);
            }

            $dateDep = strtotime($this->http->FindSingleNode('following-sibling::tr[normalize-space()][2]', $segment));

            if ($dateDep) {
                $s->departure()
                    ->day($dateDep)
                    ->noDate();
                $s->arrival()->noDate();
            }

            $flight = $this->http->FindSingleNode('following-sibling::tr[normalize-space()][3]', $segment);

            if (preg_match('/^(?<name>.+?)\s*(?<number>\d+)$/', $flight, $m)) {
                // SkyWest 3486
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }
        }

        $xpathFare = "//td[{$this->eq($this->t('Fare summary'))}]";

        $currencyCode = $this->http->FindSingleNode($xpathFare . '/following-sibling::td[normalize-space()][1]', null, true, '/^\(\s*([A-Z]{3})\s*\)$/');
        $f->price()->currency($currencyCode);

        // $3532.28
        $patterns['price'] = '/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d]*)$/';

        $base = $this->http->FindSingleNode($xpathFare . "/following::td[{$this->eq($this->t('Base:'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match($patterns['price'], $base, $m)) {
            $f->price()->cost($this->normalizeAmount($m['amount']));
        }

        $taxes = $this->http->FindSingleNode($xpathFare . "/following::td[{$this->eq($this->t('Taxes:'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match($patterns['price'], $taxes, $m)) {
            $f->price()->tax($this->normalizeAmount($m['amount']));
        }

        $total = $this->http->FindSingleNode($xpathFare . "/following::td[{$this->eq($this->t('Total:'))}]/following-sibling::td[normalize-space()][1]");

        if (preg_match($patterns['price'], $total, $m)) {
            $f->price()->total($this->normalizeAmount($m['amount']));
        }

//        if ($f->getCancelled() && $f->getNoConfirmationNumber()) {
//            $email->removeItinerary($f);
//            $email->setIsJunk(true);
//        }
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

                if ($this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0) {
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
            if (!is_string($lang) || empty($phrases['Traveler name']) || empty($phrases['Confirmation code'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Traveler name'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['Confirmation code'])}]")->length > 0
            ) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || empty(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
    }

    /**
     * Formatting over 3 steps:
     * 11 507.00  ->  11507.00
     * 2,790      ->  2790    |    4.100,00  ->  4100,00    |    1'619.40  ->  1619.40
     * 18800,00   ->  18800.00  |  2777,0    ->  2777.0.
     *
     * @param string $s Unformatted string with amount
     * @param string|null $decimals Symbols floating-point when non-standard decimals (example: 1,258.943)
     */
    private function normalizeAmount(string $s, ?string $decimals = null): ?float
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
