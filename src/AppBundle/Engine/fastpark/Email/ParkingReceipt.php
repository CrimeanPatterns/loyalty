<?php

namespace AwardWallet\Engine\fastpark\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ParkingReceipt extends \TAccountChecker
{
    public $mailFiles = "fastpark/it-62231829.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'checkIn'    => ['Check-In:', 'Check-in:'],
            'checkOut'   => ['Check-Out:', 'Check-out:'],
            'amountPaid' => ['Amount Paid:', 'Amount paid:'],
        ],
    ];

    private $detectors = [
        'en' => ['Parking Receipt'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thefastpark.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Your Fast Park Receipt') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".thefastpark.com/") or contains(@href,"www.thefastpark.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(.,"www.thefastpark.com") or contains(.,"@thefastpark.com")]')->length === 0
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

        $this->parseParking($email);
        $email->setType('ParkingReceipt' . ucfirst($this->lang));

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

    private function parseParking(Email $email): void
    {
        $p = $email->add()->parking();

        $p->general()->noConfirmation();

        $address = implode(', ', $this->http->FindNodes("//tbody[{$this->starts($this->t('PARKING DETAILS'))}]/preceding-sibling::tbody[normalize-space()][1]/descendant::text()[normalize-space()]"));
        $p->place()->address($address);

        $checkIn = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('checkIn'))}] ]/*[2]");
        $checkOut = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('checkOut'))}] ]/*[2]");
        $p->booked()
            ->start2(preg_replace('/(\d{1,2}[:]+\d{1,2})[:]+\d{1,2}/', '$1', $checkIn))
            ->end2(preg_replace('/(\d{1,2}[:]+\d{1,2})[:]+\d{1,2}/', '$1', $checkOut));

        $totalPrice = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('amountPaid'))}] ]/*[2]");

        if (preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $totalPrice, $m)) {
            // $37.00
            $p->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));

            $m['currency'] = trim($m['currency']);
            $grossAmount = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('Gross Amount:'))}] ]/*[2]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $grossAmount, $matches)) {
                $p->price()->cost($this->normalizeAmount($matches['amount']));
            }

            $discounts = $this->http->FindSingleNode("//tr[ count(*)=2 and *[1][{$this->eq($this->t('Discounts:'))}] ]/*[2]");

            if (preg_match('/^(?:' . preg_quote($m['currency'], '/') . ')?[ ]*(?<amount>\d[,.\'\d ]*)$/', $discounts, $matches)) {
                $p->price()->discount($this->normalizeAmount($matches['amount']));
            }
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
            if (!is_string($lang) || empty($phrases['checkIn']) || empty($phrases['amountPaid'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['amountPaid'])}]")->length > 0
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
}
