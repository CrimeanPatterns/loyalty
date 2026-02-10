<?php

namespace AwardWallet\Engine\fastpark\Email;

use AwardWallet\Schema\Parser\Email\Email;

class ReservationDetails extends \TAccountChecker
{
    public $mailFiles = "fastpark/it-62232892.eml";

    public $lang = '';

    public static $dictionary = [
        'en' => [
            'Reservation Location' => ['Reservation Location'],
            'confNumber'           => ['Reservation Number'],
            'checkIn'              => ['Check-In', 'Check-in'],
            'checkOut'             => ['Check-Out', 'Check-out'],
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@thefastpark.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        return stripos($headers['subject'], 'Fast Park: Reservation Confirmation for') !== false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//a[contains(@href,".thefastpark.com/") or contains(@href,"www.thefastpark.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Thank you for using the Fast Park") or contains(.,"@thefastpark.com")]')->length === 0
        ) {
            return false;
        }

        return $this->assignLang();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $this->assignLang();

        if (empty($this->lang)) {
            $this->logger->debug("Can't determine a language!");

            return $email;
        }

        $this->parseParking($email);
        $email->setType('ReservationDetails' . ucfirst($this->lang));

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
        $xpathBold = '(self::b or self::strong)';

        $p = $email->add()->parking();

        $traveller = $this->http->FindSingleNode("//tr[ *[2][{$this->starts($this->t('confNumber'))}] and *[3] ]/*[1][not({$this->contains($this->t('Reservation Location'))})]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $p->general()->traveller($traveller);

        $reservationNumber = $this->http->FindSingleNode("//tr[ *[1][not({$this->contains($this->t('Reservation Location'))})] and *[3] ]/*[2][{$this->starts($this->t('confNumber'))}]");

        if (preg_match("/({$this->opt($this->t('confNumber'))})\s*([A-Z\d]{5,})$/", $reservationNumber, $m)) {
            $p->general()->confirmation($m[2], $m[1]);
        }

        $location = $address = [];
        $phone = null;
        $td1Texts = $this->http->XPath->query("//tr[ *[2]/descendant::text()[{$this->eq($this->t('checkIn'))}] and *[3] ]/*[1]/descendant::text()[normalize-space()]");

        foreach ($td1Texts as $textNode) {
            $currentText = $this->http->FindSingleNode('.', $textNode, true, '/^[­­­\s]*(.+?)[­­­\s]*$/');

            if (preg_match("/^{$this->opt($this->t('View on'))}/", $currentText)) {
                break;
            }

            if (preg_match("/^{$this->opt($this->t('Reservation Location'))}$/", $currentText)) {
                $location = $address = [];
                $phone = null;

                continue;
            }

            if (preg_match('/^[+(\d][-. \d)(]{5,}[\d)]$/', $currentText)) {
                $phone = $currentText;

                break;
            }

            if ($this->http->XPath->query("ancestor::*[{$xpathBold}]", $textNode)->length > 0) {
                $location[] = $currentText;
            } else {
                $address[] = $currentText;
            }
        }

        $p->place()
            ->location(implode(' ', $location))
            ->address(implode(', ', $address))
            ->phone($phone, false, true);

        $td2 = implode("\n", $this->http->FindNodes("//tr[ *[1][normalize-space()] and *[3] ]/*[2][ descendant::text()[{$this->eq($this->t('checkIn'))}] ]/descendant::text()[normalize-space()]"));

        $patterns['time'] = '\d{1,2}(?:[:：]\d{2})?(?:\s*[AaPp]\.?[Mm]\.?)?';

        if (preg_match("/^[ ]*{$this->opt($this->t('checkIn'))}[ ]*\n+(.{6,})\s+{$this->opt($this->t('at'))}\s+({$patterns['time']})$/m", $td2, $m)) {
            $p->booked()->start2($m[1] . ' ' . $m[2]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('checkOut'))}[ ]*\n+(.{6,})\s+{$this->opt($this->t('at'))}\s+({$patterns['time']})$/m", $td2, $m)) {
            $p->booked()->end2($m[1] . ' ' . $m[2]);
        }

        if (preg_match("/^[ ]*{$this->opt($this->t('Estimated Total Due at Exit'))}[* ]*\n+(.+)$/m", $td2, $matches)
            && preg_match('/^(?<currency>[^\d)(]+)[ ]*(?<amount>\d[,.\'\d ]*)$/', $matches[1], $m)
        ) {
            // $16.50
            $p->price()
                ->currency($m['currency'])
                ->total($this->normalizeAmount($m['amount']));
        }
    }

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Reservation Location']) || empty($phrases['confNumber']) || empty($phrases['checkIn'])) {
                continue;
            }

            if ($this->http->XPath->query("//node()[{$this->contains($phrases['Reservation Location'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['confNumber'])}]")->length > 0
                && $this->http->XPath->query("//node()[{$this->contains($phrases['checkIn'])}]")->length > 0
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
