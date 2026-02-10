<?php

namespace AwardWallet\Engine\copaair\Email;

use AwardWallet\Common\Parser\Util\EmailDateHelper;
use AwardWallet\Schema\Parser\Email\Email;

class YourReservation extends \TAccountChecker
{
    public $mailFiles = "copaair/it-56556061.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'confNumber'          => ['Confirmation Number:', 'Confirmation Number :'],
            'reservationTicketed' => ['Reservation Ticketed:', 'Reservation Ticketed :'],
            'cancellationPhrases' => [
                'You have succesfully canceled your reservation',
                'You have succesfully cancelled your reservation',
            ],
            'statusVariants' => ['canceled'],
        ],
    ];

    private $subjects = [
        'en' => ['You have successfully cancelled your reservation'],
    ];

    private $detectors = [
        'en' => ['Your Canceled Itinerary'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@copaair.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
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
        if ($this->http->XPath->query('//a[contains(@href,".copaair.com/") or contains(@href,"www.copaair.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"address provided to Copa Airlines")]')->length === 0
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
        $email->setType('YourReservation' . ucfirst($this->lang));

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

        $status = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('You have succesfully'))}]", null, true, "/^{$this->opt($this->t('You have succesfully'))}\s*({$this->opt($this->t('statusVariants'))})(?:\s|[,.;!?]|$)/");
        $f->general()->status($status);

        $reservationTicketed = $this->http->FindSingleNode("//text()[{$this->starts($this->t('reservationTicketed'))}]/following::text()[normalize-space()][1]");
        $f->general()->date2($reservationTicketed);

        $confirmation = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if ($confirmation) {
            $confirmationTitle = $this->http->FindSingleNode("//text()[{$this->starts($this->t('confNumber'))}]", null, true, '/^(.+?)[\s:]*$/');
            $f->general()->confirmation($confirmation, $confirmationTitle);
        }

        $segments = $this->http->XPath->query("//div[ div/descendant::text()[normalize-space()][1][{$this->eq($this->t('From'))}] and div/descendant::text()[normalize-space()][1][{$this->eq($this->t('To'))}] ]/ancestor::*[ descendant::div/descendant::text()[normalize-space()][1][{$this->eq($this->t('Flight'))}] ][1]");

        foreach ($segments as $segment) {
            $s = $f->addSegment();

            $from = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('From'))}]/following-sibling::tr[normalize-space()]", $segment);
            $s->departure()
                ->name($from)
                ->noCode();

            $to = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('To'))}]/following-sibling::tr[normalize-space()]", $segment);
            $s->arrival()
                ->name($to)
                ->noCode();

            $date = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Date'))}]/following-sibling::tr[normalize-space()]", $segment);

            if ($date && $f->getReservationDate()) {
                $dateDep = EmailDateHelper::parseDateRelative($date, $f->getReservationDate());

                if ($dateDep) {
                    $s->departure()
                        ->day($dateDep)
                        ->noDate();
                    $s->arrival()->noDate();
                }
            }

            $flight = $this->http->FindSingleNode("descendant::tr[{$this->eq($this->t('Flight'))}]/following-sibling::tr[normalize-space()]", $segment);

            if (preg_match('/^(?<name>[A-Z][A-Z\d]|[A-Z\d][A-Z])\s*(?<number>\d+)$/', $flight, $m)) {
                $s->airline()
                    ->name($m['name'])
                    ->number($m['number']);
            }
        }

        $totalPrice = $this->http->FindSingleNode("//div/div[normalize-space()][1][{$this->eq($this->t('Total Fare'))}]/following-sibling::div[normalize-space()]");

        if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?<currency>[A-Z]{3})$/', $totalPrice, $m)) {
            // 1321.28 USD
            $f->price()
                ->total($this->normalizeAmount($m['amount']))
                ->currency($m['currency']);

            $baseFare = $this->http->FindSingleNode("//*/tr[normalize-space()][1][{$this->eq($this->t('Base Fare'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::*/tr[normalize-space()][1]");

            if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $baseFare, $matches)) {
                $f->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $taxes = $this->http->FindSingleNode("//*/tr[normalize-space()][2][{$this->eq($this->t('Taxes and Fees'))}]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()][1]/descendant::*/tr[normalize-space()][2]");

            if (preg_match('/^(?<amount>\d[,.\'\d]*)[ ]*(?:' . preg_quote($m['currency'], '/') . ')?$/', $taxes, $matches)) {
                $f->price()->tax($this->normalizeAmount($matches['amount']));
            }
        }

        $passengers = [];
        $ticketNumbers = [];

        $passengerRows = $this->http->XPath->query("//div[ div[{$this->eq($this->t('PASSENGER NAME'))}] and div[{$this->eq($this->t('TICKET NUMBER'))}] ]/ancestor::*[ following-sibling::*[normalize-space()] ][1]/following-sibling::*[normalize-space()]");

        foreach ($passengerRows as $pRow) {
            $passengerName = $this->http->FindSingleNode("descendant::div/div[normalize-space()][1]", $pRow, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
            $ticketNumber = $this->http->FindSingleNode("descendant::div/div[normalize-space()][2]", $pRow, true, '/^\d{3}[- ]*\d{5,}[- ]*\d{1,2}$/');

            if (!$passengerName && !$ticketNumber) {
                break;
            }

            if ($passengerName) {
                $passengers[] = $passengerName;
            }

            if ($ticketNumber) {
                $ticketNumbers[] = $ticketNumber;
            }
        }

        if (count($passengers)) {
            $f->general()->travellers($passengers);
        } else {
            $travellerHello = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Hello,'))}]", null, true, "/^{$this->opt($this->t('Hello,'))}\s*([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])(?:\s*[,;!?]|$)/");
            $f->general()->traveller($travellerHello);
        }

        if (count($ticketNumbers)) {
            $f->issued()->tickets($ticketNumbers, false);
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
            if (!is_string($lang) || empty($phrases['confNumber']) || empty($phrases['reservationTicketed'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['reservationTicketed'])}]")->length > 0
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
