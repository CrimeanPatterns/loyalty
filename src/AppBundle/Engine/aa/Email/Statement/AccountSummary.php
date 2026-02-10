<?php

namespace AwardWallet\Engine\aa\Email\Statement;

use AwardWallet\Engine\MonthTranslate;
use AwardWallet\Schema\Parser\Email\Email;
use PlancakeEmailParser;
use TAccountChecker;

class AccountSummary extends TAccountChecker
{
    public $mailFiles = "aa/statements/it-156043558.eml, aa/statements/it-215021470.eml, aa/statements/it-215024382.eml";

    public $detectSubjectRe = [
        // en
        '/[\w\']+, view your \w+ \d{4} account summary/',
        // en, pt
        '/AAdvantage eSummary - \w+ \d{4}\s*$/u',
    ];
    public $lang;
    private static $dictionary = [
        'en' => [
            "AAdvantage account summary" => [
                ["AAdvantage", 'account summary'],
                'AAdvantage account summary',
            ],
            //            "Hello," => "",
            "Your account summary as of" => "Your account summary as of",
            //            "Manage your account" => "",
            //            "Award miles balance" => "",
            //            "AWARD MILES" => "",
            //            "Miles expire" => "",
            //            "LOYALTY POINTS" => "",
        ],
        'pt' => [
            "AAdvantage account summary" => [
                "Resumo da conta AAdvantage",
                ['Resumo da conta', 'AAdvantage'],
            ],
            "Hello,"                     => "Olá,",
            "Your account summary as of" => "Resumo da sua conta em",
            "Manage your account"        => "Gerenciar sua conta",
            "Award miles balance"        => "Saldo de milhas prêmio",
            "AWARD MILES"                => "MILHAS PRÊMIO",
            "Miles expire"               => "Milhas expiram em",
            "LOYALTY POINTS"             => "LOYALTY POINTS",
        ],
        'es' => [
            "AAdvantage account summary" => [
                ["Resumen de cuenta AAdvantage"],
                ["Resumen de cuenta", 'AAdvantage'],
            ],
            "Hello,"                     => "Hola,",
            "Your account summary as of" => ["El resumen de su cuenta al", "Resumen de su cuenta a"],
            "Manage your account"        => "Administre su cuenta",
            "Award miles balance"        => ["Saldo de millas premio", "Saldo de millas de premio"],
            "AWARD MILES"                => ["MILLAS PREMIO", "MILLAS DE PREMIO"],
            "Miles expire"               => "Las millas expiran el",
            "LOYALTY POINTS"             => "LOYALTY POINTS",
        ],
        'fr' => [
            "AAdvantage account summary" => "Votre compte AAdvantage",
            "Hello,"                     => "Bonjour,",
            "Your account summary as of" => "Votre compte au",
            "Manage your account"        => "Consultez votre compte",
            "Award miles balance"        => "Vos miles de prime",
            "AWARD MILES"                => "Miles de prime",
            "Miles expire"               => "Vos miles expirent le",
            "LOYALTY POINTS"             => "LOYALTY POINTS",
        ],
        'ko' => [
            "AAdvantage account summary" => [
                ['AAdvantage', '계정 현황'],
            ],
            "Hello,"                     => "안녕하세요,",
            "Your account summary as of" => "기준, 나의 계정 현황",
            "Manage your account"        => ["나의 계정 관리하기", "Manage your account"],
            "Award miles balance"        => "어워드 마일 잔액",
            "AWARD MILES"                => "AWARD MILES",
            "Miles expire"               => "마일 만료",
            "LOYALTY POINTS"             => "LOYALTY POINTS",
        ],
        'zh' => [
            "AAdvantage account summary" => [
                ['AAdvantage', '账户摘要'],
            ],
            "Hello,"                     => "尊敬的,",
            "Your account summary as of" => "您的帐户摘要截至",
            "Manage your account"        => "管理您的账户",
            "Award miles balance"        => "奖励里程余额",
            "AWARD MILES"                => "AWARD MILES",
            "Miles expire"               => "里程有效期截至",
            "LOYALTY POINTS"             => "LOYALTY POINTS",
        ],
    ];

    public function detectEmailFromProvider($from)
    {
        return preg_match('/[@.]aa[.]com$/', $from) > 0;
    }

    public function detectEmailByHeaders(array $headers)
    {
        if (!isset($headers['subject']) || !isset($headers['from']) || stripos($headers['from'], 'loyalty@loyalty.ms.aa.com') === false) {
            return false;
        }

        foreach ($this->detectSubjectRe as $reS) {
            if (preg_match($reS, $headers['subject'])) {
                return true;
            }
        }

        return false;
    }

    public function detectEmailByBody(PlancakeEmailParser $parser)
    {
        if ($this->http->XPath->query('//text()[contains(., "aa.com/aadvantage") or contains(., "https://l.loyalty.ms.aa.com/") or contains(., "https://l.info.ms.aa.com/")]')->length === 0
            && $this->http->XPath->query('//a[contains(@href, "https://l.loyalty.ms.aa.com/")]')->length < 6
        ) {
            // return false;
        }

        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['AAdvantage account summary']) && !empty($dict['Your account summary as of'])
                && $this->http->XPath->query("//td[{$this->starts($dict['AAdvantage account summary'])} and {$this->contains($dict['Your account summary as of'])}]")->length > 0
            ) {
                return true;
            }
        }

        return false;
    }

    public function ParsePlanEmailExternal(PlancakeEmailParser $parser, Email $email)
    {
        foreach (self::$dictionary as $lang => $dict) {
            if (!empty($dict['AAdvantage account summary']) && !empty($dict['Your account summary as of'])
                && $this->http->XPath->query("//td[{$this->starts($dict['AAdvantage account summary'])} and {$this->contains($dict['Your account summary as of'])}]")->length > 0
            ) {
                $this->lang = $lang;

                break;
            }
        }

        $st = $email->add()->statement();

        $rows = array_values(array_filter(array_map('trim',
            $this->http->FindNodes("//td[{$this->starts($this->t('AAdvantage account summary'))}]/following-sibling::td[{$this->contains($this->t('Manage your account'))}]//tr"))));

        if (count($rows) === 3) {
            if (preg_match('/^# ?([A-Z\d]{5,10})$/', $rows[1], $m)) {
                // #23CWT10
                $st->setNumber($m[1])->setLogin($m[1]);
            } elseif (preg_match('/^# ?([A-Z\d]{1,9})[*]+$/', $rows[1], $m)) {
                // #23C****
                $st->setNumber($m[1])->masked('right')->setLogin($m[1])->masked('right');
            } elseif (preg_match('/^# ?[*]+([A-Z\d]{1,9})$/', $rows[1], $m)) {
                // #****23C
                $st->setNumber($m[1])->masked()->setLogin($m[1])->masked();
            } else {
                $st->setNumber(null); // for 100% fail
            }

            if (!empty($st->getNumber())) {
                $st->addProperty('Status', ucwords(preg_replace(["/^\s*AAdvantage\W*/", '/\W*$/'], '', $rows[0])));
            }
        } else {
            $st->setNumber(null); // for 100% fail
        }

        $name = $this->http->FindSingleNode("//text()[{$this->starts($this->t("Hello,"))}]", null, true,
            "/^{$this->opt($this->t("Hello,"))}\s*([[:alpha:]\- ']+?)\s*$/");
        $st->addProperty('Name', $name);

        $rows = array_values(array_filter(array_map('trim',
            $this->http->FindNodes("//*[" . $this->eq($this->t("Award miles balance")) . "]/following-sibling::*"))));

        if (isset($rows[0]) && preg_match("/^\s*(\d[\d,.]*) " . $this->opt($this->t("AWARD MILES")) . "\s*$/u", $rows[0], $m) > 0) {
            $st->setBalance(str_replace([',', '.'], '', $m[1]));

            $date = $this->http->FindSingleNode("(//text()[{$this->contains($this->t('Your account summary as of'))}])[1]",
                null, true,
                in_array($this->lang, ['ko']) ? "/(.+)\s*{$this->opt($this->t('Your account summary as of'))}/u"
                    : "/{$this->opt($this->t('Your account summary as of'))}\s*(.+)/");
            $st->setBalanceDate($this->normalizeDate($date));

            if ((in_array($this->lang, ['ko']) && preg_match("/^(.+)\s*{$this->opt($this->t('Miles expire'))}/u", $rows[1] ?? '', $m) > 0)
                || (preg_match("/{$this->opt($this->t('Miles expire'))} (.+\d{4})\s*$/u", $rows[1] ?? '', $m) > 0)
            ) {
                $st->setExpirationDate($this->normalizeDate($m[1]));
            }
        }

        $points = $this->http->FindSingleNode("//*[" . $this->eq($this->t("LOYALTY POINTS")) . "]/preceding::text()[normalize-space()][1]",
            null, true, "/^\s*(\d[\d,.]*)\s*$/");
        $st->addProperty('ElitePoints', str_replace([',', '.'], '', $points));

        return $email;
    }

    public static function getEmailTypesCount()
    {
        return 0;
    }

    private function t(string $phrase)
    {
        if (!isset(self::$dictionary, $this->lang) || !isset(self::$dictionary[$this->lang][$phrase])) {
            return $phrase;
        }

        return self::$dictionary[$this->lang][$phrase];
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

    private function starts($field): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        $result = [];

        foreach ($field as $f) {
            $r = '';

            if (is_array($f)) {
                if (!empty($f[0])) {
                    $r = 'starts-with(normalize-space(), "' . $f[0] . '")';
                    unset($f[0]);

                    if (!empty($f)) {
                        $r .= ' and ' . $this->contains($f, false);
                    }
                }
            } else {
                $r = (!empty($f)) ? 'starts-with(normalize-space(), "' . $f . '")' : '';
            }

            if (!empty($r)) {
                $result[] = '(' . $r . ')';
            } else {
                return 'false()';
            }
        }

        return (!empty($result)) ? implode(' or ', $result) : 'false()';
    }

    private function contains($field, $isOR = true): string
    {
        $field = (array) $field;

        if (count($field) === 0) {
            return 'false()';
        }

        return '(' . implode($isOR ? ' or ' : ' and ', array_map(function ($s) {
            return 'contains(normalize-space(.),"' . $s . '")';
        }, $field)) . ')';
    }

    private function opt($field)
    {
        $field = (array) $field;

        return '(?:' . implode("|", array_map(function ($s) {
            return preg_quote($s, '/');
        }, $field)) . ')';
    }

    private function normalizeDate($str)
    {
//        $this->logger->debug('$str = '.print_r( $str,true));
        $in = [
            // 28/mar/2022
            "/^\s*(\d+)\\/([[:alpha:]]+)\\/(\d{4})\s*$/u",
            // 26/09/2022
            "/^\s*(\d+)\\/(\d{2})\\/(\d{4})\s*$/u",
        ];
        $out = [
            "$1 $2 $3",
            "$1.$2.$3",
        ];

        $str = preg_replace($in, $out, $str);

        if (preg_match("#^\s*\d+\s+([[:alpha:]]{3,})\s+\d{4}\s*$#u", $str, $m)) {
            if ($en = MonthTranslate::translate($m[1], $this->lang)) {
                $str = str_replace($m[1], $en, $str);
            }
        }

        return strtotime($str);
    }
}
