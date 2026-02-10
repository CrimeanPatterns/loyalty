<?php

namespace AwardWallet\Engine\asia\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourAccountSummary extends \TAccountChecker
{
    public $mailFiles = "asia/statements/it-66331317.eml, asia/statements/it-66331220.eml, asia/statements/it-66403050.eml, asia/statements/it-66329522.eml";

    public $lang = '';

    public static $dictionary = [
        'ja' => [
            'Membership No.'  => ['会員番号'],
            'Statement as of' => '利用明細書',
            'Club points'     => 'クラブ・ポイント',
            'Asia Miles'      => 'アジア・マイル',
            //            'noBalancePhrases' => '',
        ],
        'zh' => [
            'Membership No.'  => ['會員號碼', '会员号码'],
            'Statement as of' => ['結算截至', '结算截至'],
            'Club points'     => ['會籍積分', '会籍积分'],
            'Asia Miles'      => ['「亞洲萬里通」里數', '「亚洲万里通」里数'],
            //            'noBalancePhrases' => '',
        ],
        'en' => [
            'Membership No.'   => ['Membership No.'],
            'noBalancePhrases' => 'For details of your Asia Miles balance, please visit your Account Summary',
        ],
    ];

    private $subjects = [
        'ja' => ['口座明細'],
        'zh' => ['賬戶概要', '账户概要'],
        'en' => ['Your Account Summary for'],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, '@club.cathaypacific.com') !== false;
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
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".cathaypacific.com/") or contains(@href,"e.cathaypacific.com")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"© Cathay Pacific") or contains(normalize-space(),"@club.cathaypacific.com")]')->length === 0
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
        $email->setType('YourAccountSummary' . ucfirst($this->lang));

        $st = $email->add()->statement();

        $name = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership No.'))}]/preceding-sibling::tr[normalize-space()]", null, true, '/^[[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]]$/u');
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership No.'))}]", null, true, "/{$this->opt($this->t('Membership No.'))}[:：\s]+([-Xx\d ]{5,})$/u");
        $number = str_replace(' ', '', $number);

        if (preg_match("/^(\d+)[Xx]+(\d+)$/", $number, $m)) {
            // 172XXXX649
            $numberMasked = $m[1] . '**' . $m[2];
            $st->setNumber($numberMasked)->masked('center')
                ->setLogin($numberMasked)->masked('center');
        } elseif (preg_match("/^\d+$/", $number)) {
            // 1723548649
            $st->setNumber($number)
                ->setLogin($number);
        }

        $clubPoints = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Club points'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");

        if ($clubPoints !== null) {
            $st->addProperty('ClubPoints', $clubPoints);
        }

        $balance = $this->http->FindSingleNode("//tr/*[{$this->eq($this->t('Asia Miles'))}]/following-sibling::*[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");

        if ($balance !== null) {
            $st->setBalance($this->normalizeAmount($balance));

            $balanceDate = $this->http->FindSingleNode("//tr[not(.//tr) and {$this->starts($this->t('Statement as of'))}]", null, true, "/{$this->opt($this->t('Statement as of'))}[:：\s]+(.{6,})$/u");
            $balanceDateNormal = $this->normalizeDate($balanceDate);
            $st->parseBalanceDate($balanceDateNormal);
        } elseif ($this->http->XPath->query("//*[{$this->contains($this->t('noBalancePhrases'))}]")->length > 0
            || $name || $number
        ) {
            $st->setNoBalance(true);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    /**
     * Dependencies `use AwardWallet\Engine\MonthTranslate` and `$this->lang`.
     */
    private function normalizeDate(?string $text): ?string
    {
        if (preg_match('/^(\d{1,2})\s+([[:alpha:]]{3,})\s+(\d{4})$/', $text, $m)) {
            // 16 Sep 2020
            $day = $m[1];
            $month = $m[2];
            $year = $m[3];
        } elseif (preg_match('/^(\d{4})\s*年\s*(\d{1,2})\s*月\s*(\d{1,2})\s*日\D*$/u', $text, $m)) {
            // 2020年9月16日
            $year = $m[1];
            $month = $m[2];
            $day = $m[3];
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

    private function assignLang(): bool
    {
        if (!isset(self::$dictionary, $this->lang)) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!is_string($lang) || empty($phrases['Membership No.'])) {
                continue;
            }

            if ($this->http->XPath->query("//tr[{$this->starts($phrases['Membership No.'])}]")->length > 0) {
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
}
