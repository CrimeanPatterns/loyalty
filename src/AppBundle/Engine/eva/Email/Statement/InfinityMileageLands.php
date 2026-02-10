<?php

namespace AwardWallet\Engine\eva\Email\Statement;

use AwardWallet\Schema\Parser\Email\Email;

class InfinityMileageLands extends \TAccountChecker
{
    public $mailFiles = "eva/statements/it-110508877.eml, eva/statements/it-66934630.eml, eva/statements/it-66965904.eml, eva/statements/it-66971075.eml, eva/statements/it-73765316.eml";

    public $lang = '';

    public static $dictionary = [
        "en" => [
            'Member No.:'                 => ['Member No.:', 'Member No. :'],
            'Your current card status is' => ['Your current card status is', 'Your membership status is'],
        ],
        "zh" => [
            'Dear'                        => '親愛的',
            'Card Number'                 => '您的會員卡號',
            'Your current card status is' => '您是本公司 綠卡會員，',
            'Award Miles'                 => '獎勵哩程',
            'miles'                       => '哩',
        ],
    ];

    private $detectors = [
        'en' => [
            'We’ve just received your "Forgot Password" inquiry',
            'name in your membership account is',
            'Infinity MileageLands Mileage Statement',
            'If you cannot view this Mileage Statement',
            'Your remaining self Award Miles in your account',
            'Please click the below hyperlink and enter the Verification Code to reset your new password',
        ],
        'zh' => ['含賺取與購買哩程'],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[.@]evaair\.com/i', $from) > 0;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && stripos($parser->getSubject(), 'EVA Air Infinity MileageLands') === false
            && $this->http->XPath->query('//a[contains(@href,".evaair.com/") or contains(@href,"www.evaair.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"Sincerely Yours, Infinity MileageLands Service") or contains(normalize-space(),"EVA AIRWAYS Copyright") or contains(normalize-space(),"© EVA Airways Corp") or contains(.,"www.evaair.com") or contains(.,"@mh1.evaair.com")]')->length === 0
        ) {
            return false;
        }

        return $this->isMembership($parser->getPlainBody()) || $this->detectBody();
    }

    public function ParsePlanEmailExternal(\PlancakeEmailParser $parser, Email $email)
    {
        $patterns = [
            'travellerName' => '[[:alpha:]][-.\'\/[:alpha:] ]*[[:alpha:]]', // Mr. KURTZ JERID    |    Mr. KURTZ/JERID
        ];

        if ($this->http->XPath->query('//node()[contains(normalize-space(),"Statement") or contains(normalize-space(),"哩程核對表")]')->length > 0) {
            return $email;
        }

        $this->AssignLang();

        $st = $email->add()->statement();

        if ($this->isMembership($parser->getPlainBody())) {
            // it-66971075.eml, it-66965904.eml
            $st->setMembership(true);

            return $email;
        }

        // Name
        $familyName = $this->http->FindSingleNode("//text()[{$this->starts('Family Name')}]", null, true, "/{$this->opt('Family Name')}[:\s]+({$patterns['travellerName']})(?:\s*[,.;:!?]|$)/u");
        $givenName = $this->http->FindSingleNode("//text()[{$this->starts('Given Name')}]", null, true, "/{$this->opt('Given Name')}[:\s]+({$patterns['travellerName']})(?:\s*[,.;:!?]|$)/u");
        $name = $familyName && $givenName ? $givenName . ' ' . $familyName : null; // it-66934630.eml

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s+({$patterns['travellerName']})(?:\s*[,.;:!?]|$)/u");
        }

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Dear'))}]", null, true, "/{$this->opt($this->t('Dear'))}\s*({$patterns['travellerName']})\s*(?:先生您好)(?:\s*[,.;:!?]|$)/u");
        }

        if (!$name) {
            $name = $this->http->FindSingleNode("//text()[{$this->starts('Card Number')}]/preceding::text()[normalize-space()][1]", null, true, "/^(?:Mr\.|Ms\.)\s*{$patterns['travellerName']}$/u");
        }

        if (preg_match("/^Member$/i", $name)) {
            $name = null;
        }

        if ($name) {
            $st->addProperty('Name', $name);
        }

        // Number
        $number = $this->http->FindSingleNode("//text()[{$this->eq($this->t('Member No.:'))}]/following::text()[normalize-space()][1]", null, true, '/^[A-Z\d]{5,}$/');

        if (!$number) {
            // it-66954208.eml
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Card Number'))}]", null, true, "/^{$this->opt($this->t('Card Number'))}[:\s]+([A-Z\d ]{5,})$/");
        }

        if (!$number) {
            // it-66954208.eml
            $number = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Card Number'))}]", null, true, "/^{$this->opt($this->t('Card Number'))}\:?\s*+([A-Z\d ]{5,})/");
        }

        if (!$number) {
            // it-66874008.eml
            $number = $this->http->FindSingleNode("//text()[{$this->contains($this->t('Your remaining self Award Miles in your account'))}]", null, true, "/{$this->opt($this->t('Your remaining self Award Miles in your account'))}[:\s]+([A-Z\d]{5,})(?: |from|$)/");
        }
        $number = str_replace(' ', '', $number);

        if (preg_match("/^(\d+)[Xx]+(\d+[A-Z]{0,2})$/", $number, $m)) {
            // 130XXXX771GC
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1309771365
            $st->setNumber($number)
                ->setLogin($number);
        }

        // Status
        $status = $this->http->FindSingleNode("descendant::*[{$this->contains($this->t('Your current card status is'))}][last()]", null, true, "/{$this->opt($this->t('Your current card status is'))}\s+([-A-z]{2,})(?:\s+Card)?(?:\s*[.]|$)/");

        if (!$status) {
            $status = $this->http->FindSingleNode("//text()[{$this->starts($this->t('Your current card status is'))}]", null, true, "/{$this->opt($this->t('Your current card status is'))}\s*(.+)$/");
        }

        if ($status) {
            // it-66954208.eml
            $st->addProperty('Status', $status);
        }

        // Balance
        $balance = $this->http->FindSingleNode("//table/descendant::text()[normalize-space()][1][{$this->eq($this->t('Mileage Balance'))}]/following::text()[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");

        if ($balance === null) {
            // it-66874008.eml
            $balance = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Award Miles'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^(\d[,.\'\d ]*){$this->opt($this->t('miles'))}?$/i");
        }

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));
        } elseif ($name || $number || $status) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function isMembership(?string $text = ''): bool
    {
        $phrases = [
            'Dear Member,',
            '親愛的會員您好',
            '親愛的會員先生/小姐您好',
        ];

        foreach ($phrases as $phrase) {
            if (!empty($text) && stripos($text, $phrase) !== false
                || $this->http->XPath->query("//node()[{$this->contains($phrase)}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    private function detectBody(): bool
    {
        if ($this->AssignLang() == true) {
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
     *
     * @return float|null
     */
    private function AssignLang()
    {
        foreach ($this->detectors as $lang => $words) {
            foreach ($words as $word) {
                if ($this->http->XPath->query("//node()[{$this->contains($word)}]")->length > 0) {
                    $this->lang = $lang;

                    return true;
                }
            }
        }

        return false;
    }

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

    private function t($word)
    {
        if (!isset(self::$dictionary[$this->lang]) || !isset(self::$dictionary[$this->lang][$word])) {
            return $word;
        }

        return self::$dictionary[$this->lang][$word];
    }
}
