<?php

namespace AwardWallet\Engine\asia\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;

class YourAccountSummary2023 extends \TAccountChecker
{
    public $mailFiles = "asia/statements/it-678401935.eml, asia/statements/it-681903384.eml";

    public $detectSubjects = [
        // en
        'Your Account Summary for',
        // zh
        '账户概要',
    ];

    public $lang = '';

    public static $dictionary = [
        'en' => [
            // 'Dear' => '',
            'Membership No :'       => 'Membership No :',
            'Your Account Summary ' => 'Your Account Summary',
            // 'Current statement:' => '',
            // 'As of' => '',
            // 'Asia Miles' => '',
            // 'Status Points' => '',
        ],
        'zh' => [
            'Dear'                  => '尊敬的',
            'Membership No :'       => '会员编号 :',
            'Your Account Summary ' => '您的账户概要',
            'Current statement:'    => '结算截至:',
            'As of'                 => '',
            'Asia Miles'            => '「亚洲万里通」里数',
            'Status Points'         => '会籍积分',
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return stripos($from, 'member-noreply@e.cathaypacific.com') !== false;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (self::detectEmailFromProvider($headers['from']) !== true) {
            return false;
        }

        foreach ($this->detectSubjects as $detectSubject) {
            if (stripos($headers['subject'], $detectSubject) !== false) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(\PlancakeEmailParser $parser)
    {
        if (self::detectEmailFromProvider($parser->getHeader('from')) !== true
            && $this->http->XPath->query('//a[contains(@href,".cathaypacific.com/")]')->length === 0
            && $this->http->XPath->query('//node()[contains(normalize-space(),"© Cathay Pacific") or contains(normalize-space(),"member-noreply@e.cathaypacific.com")]')->length === 0
        ) {
            return false;
        }

        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['Your Account Summary']) && $this->http->XPath->query("//tr[{$this->starts($phrases['Your Account Summary'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
    }

    public function assignLang(): bool
    {
        foreach (self::$dictionary as $lang => $phrases) {
            if (!empty($phrases['Membership No :']) && $this->http->XPath->query("//tr[{$this->starts($phrases['Membership No :'])}]")->length > 0) {
                $this->lang = $lang;

                return true;
            }
        }

        return false;
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

        $name = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership No :'))}]/preceding-sibling::tr[normalize-space()]",
            null, true, "/^\s*{$this->opt($this->t('Dear'))}\s*(?:Mr|Mrs|Miss)?\s+([[:alpha:]][-.\'[:alpha:] ]*[[:alpha:]])\s*$/u");
        $st->addProperty('Name', $name);

        $number = $this->http->FindSingleNode("//tr[{$this->starts($this->t('Membership No :'))}]", null, true, "/{$this->opt($this->t('Membership No :'))}\s+(\d{2}[-Xx \d]{4,}\d{3})\s*$/u");
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

        $clubPoints = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Status Points'))}]/following-sibling::tr[normalize-space()][1]",
            null, true, "/^\s*(\d[,.\'\d ]*?)\b\D+$/");

        if ($clubPoints !== null) {
            $st->addProperty('ClubPoints', $clubPoints);
        }

        $tier = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Status Points'))}]/following-sibling::tr[normalize-space()][1]",
            null, true, "/^\s*\d[,.\'\d ]*?\b\s*(\S\D+?)\s*$/");

        if ($tier !== null) {
            $st->addProperty('Tier', $tier);
        }

        $balance = $this->http->FindSingleNode("//tr[{$this->eq($this->t('Asia Miles'))}]/following-sibling::tr[normalize-space()][1]", null, true, "/^\d[,.\'\d ]*$/");

        if ($balance !== null) {
            $st->setBalance(preg_replace("/\D+/", '', $balance));

            $balanceDate = $this->http->FindSingleNode("//*[{$this->eq($this->t('Current statement:'))}]/following::text()[normalize-space()][1]",
                null, true, "/{$this->opt($this->t('As of'))}\s*(.{6,})$/u");
            $balanceDateNormal = $this->normalizeDate($balanceDate);
            $st->parseBalanceDate($balanceDateNormal);
        }

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    public static function getEmailLanguages()
    {
        return array_keys(self::$dictionary);
    }

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

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || !isset(self::$dictionary[$this->lang][$phrase])) {
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
